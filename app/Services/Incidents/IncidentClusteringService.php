<?php

namespace App\Services\Incidents;

use App\Models\AlertReport;
use App\Models\Incident;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Clustering spatio-temporel signalement → incident — CDC V4.1 §4.5
 *
 * Trois signalements du même incendie ne produisent plus trois cercles
 * superposés mais un incident unique, dont la position est le centroïde et la
 * confiance le nombre de témoins indépendants.
 *
 * Deux signalements fusionnent s'ils sont :
 *   - de même type, ou de types compatibles (config incidents.clustering)
 *   - à moins de 150 m l'un de l'autre
 *   - à moins de 10 minutes d'écart
 */
class IncidentClusteringService
{
    public function __construct(
        private readonly IncidentGeometryBuilder $geometryBuilder,
        private readonly RouteAvoidancePolicy $avoidancePolicy,
        private readonly IncidentConfidenceService $confidenceService,
    ) {
    }

    /**
     * Rattache un signalement à un incident existant, ou en crée un.
     *
     * @return array{incident: Incident, merged: bool, routing_changed: bool}
     */
    public function attach(AlertReport $report): array
    {
        return DB::transaction(function () use ($report) {
            $existing = $this->findCompatibleIncident(
                $report->type,
                $report->lat,
                $report->lng,
                $report->created_at ?? now()
            );

            if ($existing !== null) {
                $routingChanged = $this->merge($existing, $report);

                return ['incident' => $existing, 'merged' => true, 'routing_changed' => $routingChanged];
            }

            $incident = $this->create($report);

            return [
                'incident'        => $incident,
                'merged'          => false,
                'routing_changed' => $incident->affects_routing,
            ];
        });
    }

    /**
     * Incident actif compatible dans la fenêtre spatio-temporelle, le plus proche.
     *
     * Sert aussi à la détection de doublon côté formulaire (§6.6) : proposer
     * « Un incident similaire est déjà signalé ici. Confirmer ? » transforme un
     * doublon potentiel en confirmation.
     */
    public function findCompatibleIncident(
        string $type,
        float $lat,
        float $lng,
        ?Carbon $at = null
    ): ?Incident {
        $at = $at ?? now();
        $maxDistance = (float) config('incidents.clustering.max_distance_m', 150);
        $maxAge = (int) config('incidents.clustering.max_age_minutes', 10);

        $types = $this->compatibleTypes($type);

        // Pré-filtre par bbox — le calcul exact ne porte que sur les candidats.
        $bbox = Geo::bboxOf([[$lat, $lng]], $maxDistance);

        $candidates = Incident::active()
            ->whereIn('type', $types)
            ->where('updated_at', '>=', $at->copy()->subMinutes($maxAge))
            ->inBbox($bbox)
            ->get();

        $best = null;
        $bestDistance = INF;

        foreach ($candidates as $candidate) {
            $distance = Geo::haversine($lat, $lng, $candidate->centroid_lat, $candidate->centroid_lng);

            if ($distance <= $maxDistance && $distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * Crée un incident depuis un signalement isolé.
     */
    private function create(AlertReport $report): Incident
    {
        $config = $this->typeConfig($report->type);
        $geometry = $this->geometryBuilder->buildFromReport($report);

        $incident = new Incident(array_merge($geometry, [
            'type'             => $report->type,
            'severity'         => $report->severity,
            'report_count'     => 1,
            'confirm_count'    => 0,
            'clear_count'      => 0,
            'confidence_score' => 0,
            'affects_routing'  => false,
            'status'           => 'active',
            'expires_at'       => now()->addMinutes((int) ($config['ttl_minutes'] ?? 60)),
        ]));

        $incident->save();

        $report->incident_id = $incident->id;
        $report->save();

        $incident->setRelation('reports', collect([$report]));

        $this->confidenceService->refresh($incident);
        $this->avoidancePolicy->refresh($incident);

        return $incident;
    }

    /**
     * Fusionne un signalement dans un incident existant.
     *
     * @return bool  true si `affects_routing` a basculé — déclencheur du §5.5
     */
    private function merge(Incident $incident, AlertReport $report): bool
    {
        $report->incident_id = $incident->id;
        $report->save();

        $reports = $incident->reports()->with('user')->get();

        $incident->report_count = $reports->count();
        // La gravité de l'incident est le maximum des signalements (§7.2)
        $incident->severity = $this->maxSeverity($reports->pluck('severity')->all());

        // Géométrie affinée par le nouveau témoignage
        $incident->fill($this->geometryBuilder->rebuildFromReports($incident, $reports));

        // §4.7c — prolongation automatique. Un incendie qui dure trois heures
        // reste actif trois heures, sans que quiconque ait choisi une durée.
        $config = $this->typeConfig($incident->type);
        if ($config['extendable'] ?? false) {
            $incident->expires_at = $this->extendedExpiry($incident, (int) ($config['ttl_minutes'] ?? 60));
        }

        $incident->save();
        $incident->setRelation('reports', $reports);

        $this->confidenceService->refresh($incident);

        return $this->avoidancePolicy->refresh($incident);
    }

    /**
     * Nouvelle expiration, plafonnée pour qu'un flux continu de signalements
     * ne rende pas un incident éternel.
     */
    private function extendedExpiry(Incident $incident, int $ttlMinutes): Carbon
    {
        $proposed = now()->addMinutes($ttlMinutes);
        $ceiling = $incident->created_at
            ->copy()
            ->addHours((int) config('incidents.resolution.max_extension_hours', 12));

        return $proposed->greaterThan($ceiling) ? $ceiling : $proposed;
    }

    /**
     * Types considérés comme témoignages du même événement (relation symétrique).
     *
     * @return array<int, string>
     */
    private function compatibleTypes(string $type): array
    {
        $map = config('incidents.clustering.compatible_types', []);
        $types = [$type];

        foreach ($map[$type] ?? [] as $compatible) {
            $types[] = $compatible;
        }

        // Symétrie : si A déclare B compatible, B l'est aussi avec A
        foreach ($map as $other => $compatibles) {
            if (in_array($type, $compatibles, true)) {
                $types[] = $other;
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * @param  array<int, string>  $severities
     */
    private function maxSeverity(array $severities): string
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
        $best = 'low';

        foreach ($severities as $severity) {
            if (($rank[$severity] ?? 0) > ($rank[$best] ?? 0)) {
                $best = $severity;
            }
        }

        return $best;
    }

    private function typeConfig(string $type): array
    {
        $types = config('incidents.types', []);

        return $types[$type] ?? $types['other'] ?? [];
    }
}
