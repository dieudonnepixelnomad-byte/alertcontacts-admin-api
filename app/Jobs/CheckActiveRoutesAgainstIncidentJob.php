<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\Route;
use App\Models\RouteIncidentHit;
use App\Services\FirebaseNotificationService;
use App\Services\QuietHoursService;
use App\Support\FlexiblePolyline;
use App\Support\Geo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Surveillance pendant le trajet — CDC V4.1 §5.5 / §8.3
 *
 * Principe : l'événement déclencheur est la CRÉATION d'un incident, pas
 * l'écoulement du temps. On n'implémente pas « toutes les 2 minutes, le
 * téléphone demande s'il y a du nouveau » mais « quand un incident est créé,
 * le serveur regarde qui roule dessus ».
 *
 * Zéro polling, zéro batterie supplémentaire, zéro appel au moteur de routage.
 * Le recalcul n'a lieu que si l'utilisateur tape « Contourner ».
 */
class CheckActiveRoutesAgainstIncidentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly int $incidentId)
    {
        $this->onQueue('alerts');
    }

    public function handle(
        FirebaseNotificationService $fcm,
        QuietHoursService $quietHours
    ): void {
        $incident = Incident::with('reports')->find($this->incidentId);

        if ($incident === null || !$incident->affects_routing || $incident->status !== 'active') {
            return;
        }

        $authorIds = $incident->reports->pluck('user_id')->filter()->unique()->all();
        $monitoringTiers = (array) config('alertcontacts.routes.monitoring_tiers', ['premium']);

        $routes = Route::query()
            ->active()
            ->inBbox($incident->bbox())
            ->with('user')
            ->get();

        $notified = 0;

        foreach ($routes as $route) {
            // §4.10 règle 1 — jamais sur son propre signalement
            if (in_array($route->user_id, $authorIds, true)) {
                continue;
            }

            // §10.2 — la surveillance en trajet est une feature Solo/Famille
            if (!$route->user || (!$route->user->hasPremiumAccess() && !in_array($route->user->tier ?? 'free', $monitoringTiers, true))) {
                continue;
            }

            $hit = $this->hitAhead($route, $incident);

            if ($hit === null) {
                continue;
            }

            // §9.2 — une seule notification par incident et par trajet.
            // L'unicité en base fait foi, même en cas de rejeu du job.
            try {
                RouteIncidentHit::create([
                    'route_id'       => $route->id,
                    'incident_id'    => $incident->id,
                    'min_distance_m' => $hit['min_distance_m'],
                    'detected_phase' => 'en_route',
                    'notified'       => true,
                    'detected_at'    => now(),
                ]);
            } catch (QueryException) {
                continue; // déjà notifié pour ce trajet
            }

            $user = $route->user;

            if ($user === null || $user->fcm_token === null) {
                continue;
            }

            // §9.4 — silence en heures calmes, sauf gravité Élevé
            if ($incident->severity !== 'high' && $quietHours->isQuietTime($user)) {
                continue;
            }

            $distanceAhead = (int) round($hit['distance_ahead_m']);

            $fcm->sendNotification(
                $user->fcm_token,
                '🔴 Alerte sur ta route',
                "{$incident->label()} signalé dans " . $this->formatDistance($distanceAhead) . '. Contourner ?',
                [
                    'type'          => 'route_incident',
                    'route_id'      => $route->id,
                    'incident_id'   => $incident->id,
                    'incident_type' => $incident->type,
                    'gravity'       => $incident->severity,
                    'distance_ahead_m' => $distanceAhead,
                ],
                $incident->severity === 'high' ? 'high' : 'normal'
            );

            $notified++;
        }

        if ($notified > 0) {
            Log::info('[CheckActiveRoutesAgainstIncidentJob] trajets alertés', [
                'incident_id' => $incident->id,
                'routes'      => $routes->count(),
                'notified'    => $notified,
            ]);
        }
    }

    /**
     * L'incident est-il sur la portion NON ENCORE PARCOURUE du trajet ?
     *
     * §9.1 — jamais de push si l'incident est derrière l'utilisateur.
     *
     * @return array{min_distance_m: int, distance_ahead_m: float}|null
     */
    private function hitAhead(Route $route, Incident $incident): ?array
    {
        $polyline = FlexiblePolyline::decode($route->polyline);

        if (count($polyline) < 2) {
            return null;
        }

        $position = $this->currentPosition($route);
        $progressIndex = $position !== null
            ? $this->nearestIndex($polyline, $position)
            : 0;

        $remaining = array_slice($polyline, $progressIndex);

        if (count($remaining) < 2) {
            return null; // trajet quasi terminé
        }

        $buffer = (float) $incident->danger_buffer_m;
        $step = (float) config('incidents.routing.polyline_sample_step_m', 50);
        $sampled = Geo::samplePolyline($remaining, $step);

        $minDistance = INF;
        $distanceAhead = 0.0;
        $cumulative = 0.0;

        foreach ($sampled as $i => $point) {
            if ($i > 0) {
                $cumulative += Geo::haversine(
                    $sampled[$i - 1][0],
                    $sampled[$i - 1][1],
                    $point[0],
                    $point[1]
                );
            }

            $distance = $incident->distanceTo($point[0], $point[1]);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $distanceAhead = $cumulative;
            }
        }

        if ($minDistance > $buffer) {
            return null;
        }

        return [
            'min_distance_m'   => (int) round($minDistance),
            'distance_ahead_m' => $distanceAhead,
        ];
    }

    /**
     * Dernière position connue de l'utilisateur, si elle est fraîche.
     *
     * @return array{0: float, 1: float}|null
     */
    private function currentPosition(Route $route): ?array
    {
        $row = DB::table('user_locations')
            ->where('user_id', $route->user_id)
            ->where('captured_at_device', '>=', now()->subMinutes(15))
            ->orderByDesc('captured_at_device')
            ->select('latitude', 'longitude')
            ->first();

        return $row === null ? null : [(float) $row->latitude, (float) $row->longitude];
    }

    /**
     * Index du point de la polyligne le plus proche de la position courante.
     *
     * @param  array<int, array{0: float, 1: float}>  $polyline
     * @param  array{0: float, 1: float}  $position
     */
    private function nearestIndex(array $polyline, array $position): int
    {
        $bestIndex = 0;
        $bestDistance = INF;

        foreach ($polyline as $i => $point) {
            $distance = Geo::haversine($position[0], $position[1], $point[0], $point[1]);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestIndex = $i;
            }
        }

        return $bestIndex;
    }

    private function formatDistance(int $meters): string
    {
        return $meters < 1000
            ? "{$meters} m"
            : round($meters / 1000, 1) . ' km';
    }
}
