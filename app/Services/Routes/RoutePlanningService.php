<?php

namespace App\Services\Routes;

use App\Models\Incident;
use App\Models\Route;
use App\Models\RouteIncidentHit;
use App\Models\User;
use App\Services\Routing\DTO\AvoidArea;
use App\Services\Routing\DTO\RouteAlternative;
use App\Services\Routing\DTO\RouteRequest;
use App\Services\Routing\DTO\RouteResult;
use App\Services\Routing\RoutingProvider;
use App\Services\PostHogService;
use App\Support\FlexiblePolyline;
use App\Support\Geo;
use Illuminate\Support\Collection;

/**
 * Planification et contournement — CDC V4.1 §5.4
 *
 * Économie d'appels, qui est une décision de conception et non une
 * optimisation :
 *   - aperçu           → 1 appel au moteur
 *   - détection HIT    → 0 appel (Haversine local)
 *   - contournement    → 1 appel, UNIQUEMENT si l'utilisateur tape « Contourner »
 *
 * Le cas majoritaire — un trajet sans incident — ne coûte donc qu'un seul
 * appel. Et le taux de tap sur « Contourner » devient un signal produit
 * mesurable de l'utilité réelle de la feature (§13.1).
 */
class RoutePlanningService
{
    public function __construct(
        private readonly RoutingProvider $provider,
        private readonly IncidentIntersectionService $intersection,
    ) {
    }

    /**
     * Étape 2 + 3 — calcul de l'itinéraire, puis détection locale des incidents.
     *
     * @param  array<string, mixed>  $data
     * @return array{route: Route, result: RouteResult, hits: Collection, destination_inside: bool}
     */
    public function preview(User $user, array $data): array
    {
        $request = new RouteRequest(
            originLat: (float) $data['origin']['lat'],
            originLng: (float) $data['origin']['lng'],
            destinationLat: (float) $data['destination']['lat'],
            destinationLng: (float) $data['destination']['lng'],
            transportMode: $data['transport_mode'] ?? 'car',
        );

        $result = $this->provider->route($request);
        $primary = $result->primary();

        $route = $this->persist($user, $data, $result);

        // L'aperçu affiche toutes les alertes communautaires actives proches
        // du trajet, y compris celles créées par l'utilisateur. L'exclusion
        // de son propre signalement ne s'applique qu'aux push en trajet ;
        // elle ne doit jamais masquer une information sur sa propre carte.
        $hits = $this->intersection->detectOnEncodedPolyline(
            $primary->polyline,
            $route->transport_mode,
        );

        $this->recordHits($route, $hits, 'pre_departure');

        app(PostHogService::class)->capture($user, 'route_previewed', [
            'transport_mode' => $route->transport_mode,
            'incident_count' => $hits->count(),
            'incident_count_bucket' => $this->countBucket($hits->count()),
            'destination_inside' => $this->destinationInsideAnyHit($route, $hits),
            'distance_bucket' => $this->distanceBucket((int) $route->distance_m),
            'duration_bucket' => $this->durationBucket((int) $route->duration_s),
        ]);

        return [
            'route'              => $route,
            'result'             => $result,
            'hits'               => $hits,
            'destination_inside' => $this->destinationInsideAnyHit($route, $hits),
        ];
    }

    /**
     * Étape 5 — recalcul avec contournement. Second appel au moteur.
     *
     * @param  array<int, int>  $incidentIds
     * @return array{
     *     result: RouteResult,
     *     evaluations: array<int, array{alternative: RouteAlternative, partial: bool, still_crossed: array<int,int>}>,
     *     partial: bool,
     *     still_crossed: array<int, int>
     * }
     */
    public function avoid(Route $route, array $incidentIds): array
    {
        $incidents = Incident::query()
            ->affectingRouting()
            ->whereIn('id', $incidentIds)
            ->get();

        $areas = $incidents->map(fn (Incident $i) => AvoidArea::fromIncident($i))->all();

        $request = new RouteRequest(
            originLat: (float) $route->origin_lat,
            originLng: (float) $route->origin_lng,
            destinationLat: (float) $route->destination_lat,
            destinationLng: (float) $route->destination_lng,
            transportMode: $route->transport_mode,
            avoidAreas: $areas,
        );

        $result = $this->provider->route($request);

        // Étape 6 — double filet de sécurité
        $evaluations = [];

        foreach ($result->alternatives as $alternative) {
            $evaluations[] = $this->verify($alternative, $incidents, $route->transport_mode);
        }

        // On garde l'itinéraire sûr en premier, même s'il est plus long (§6.4)
        usort($evaluations, static function ($a, $b) {
            return [$a['partial'], $a['alternative']->durationS]
                <=> [$b['partial'], $b['alternative']->durationS];
        });

        $best = $evaluations[0];

        $this->persistAvoidance($route, $best, $incidentIds);

        app(PostHogService::class)->capture($route->user, 'route_avoidance_requested', [
            'incident_count' => count($incidentIds),
            'incident_count_bucket' => $this->countBucket(count($incidentIds)),
            'transport_mode' => $route->transport_mode,
            'avoidance_partial' => (bool) $best['partial'],
        ]);

        if ($best['partial']) {
            app(PostHogService::class)->capture($route->user, 'route_avoidance_partial', [
                'incident_count' => count($incidentIds),
                'incident_count_bucket' => $this->countBucket(count($incidentIds)),
                'transport_mode' => $route->transport_mode,
            ]);
        }

        return [
            'result'        => $result,
            'evaluations'   => $evaluations,
            'partial'       => $best['partial'],
            'still_crossed' => $best['still_crossed'],
        ];
    }

    /**
     * Étape 6 — vérification d'un itinéraire retourné.
     *
     * Deux questions indépendantes :
     *   (1) notices[] contient-il « violatedBlockedRoad » (critical) ?
     *   (2) ma propre mesure Haversine dit-elle « traverse encore » ?
     *
     * OUI à l'un des deux → « contournement partiel ». NON aux deux →
     * « contourne la zone signalée ». On ne promet jamais mieux que ça (§6.7).
     *
     * @param  Collection<int, Incident>  $incidents
     * @return array{alternative: RouteAlternative, partial: bool, still_crossed: array<int, int>}
     */
    public function verify(RouteAlternative $alternative, Collection $incidents, string $transportMode): array
    {
        $polyline = FlexiblePolyline::decode($alternative->polyline);
        $sampled = Geo::samplePolyline(
            $polyline,
            (float) config('incidents.routing.polyline_sample_step_m', 50)
        );

        $stillCrossed = [];

        foreach ($incidents as $incident) {
            foreach ($sampled as $point) {
                if ($incident->distanceTo($point[0], $point[1]) <= $incident->danger_buffer_m) {
                    $stillCrossed[] = $incident->id;
                    break;
                }
            }
        }

        return [
            'alternative'   => $alternative,
            'partial'       => $alternative->hasViolatedBlockedRoad() || $stillCrossed !== [],
            'still_crossed' => $stillCrossed,
        ];
    }

    /**
     * §5.6 — la destination est-elle à l'intérieur d'une zone signalée ?
     *
     * HERE documente que la route traversera dans ce cas. On ne propose donc
     * pas de contournement : on prévient, c'est tout.
     *
     * @param  Collection<int, array{incident: Incident}>  $hits
     */
    private function destinationInsideAnyHit(Route $route, Collection $hits): bool
    {
        foreach ($hits as $hit) {
            $incident = $hit['incident'];

            if ($incident->distanceTo($route->destination_lat, $route->destination_lng) <= $incident->danger_buffer_m) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(User $user, array $data, RouteResult $result): Route
    {
        $primary = $result->primary();
        $polyline = FlexiblePolyline::decode($primary->polyline);
        $bbox = Geo::bboxOf($polyline);

        return Route::create([
            'user_id'           => $user->id,
            'origin_lat'        => $data['origin']['lat'],
            'origin_lng'        => $data['origin']['lng'],
            'origin_label'      => $data['origin']['label'] ?? null,
            'destination_lat'   => $data['destination']['lat'],
            'destination_lng'   => $data['destination']['lng'],
            'destination_label' => $data['destination']['label'] ?? null,
            'transport_mode'    => $data['transport_mode'] ?? 'car',
            'polyline'          => $primary->polyline,
            'alternatives'      => $this->serializeAlternatives($result),
            'selected_index'    => 0,
            'distance_m'        => $primary->distanceM,
            'duration_s'        => $primary->durationS,
            'bbox_north'        => $bbox['north'],
            'bbox_south'        => $bbox['south'],
            'bbox_east'         => $bbox['east'],
            'bbox_west'         => $bbox['west'],
            'status'            => 'planned',
        ]);
    }

    /**
     * @param  array{alternative: RouteAlternative, partial: bool, still_crossed: array<int, int>}  $best
     * @param  array<int, int>  $incidentIds
     */
    private function persistAvoidance(Route $route, array $best, array $incidentIds): void
    {
        $alternative = $best['alternative'];
        $polyline = FlexiblePolyline::decode($alternative->polyline);
        $bbox = Geo::bboxOf($polyline);

        $route->update([
            'polyline'             => $alternative->polyline,
            'distance_m'           => $alternative->distanceM,
            'duration_s'           => $alternative->durationS,
            'avoidance_applied'    => true,
            'avoidance_partial'    => $best['partial'],
            'avoided_incident_ids' => array_values($incidentIds),
            'bbox_north'           => $bbox['north'],
            'bbox_south'           => $bbox['south'],
            'bbox_east'            => $bbox['east'],
            'bbox_west'            => $bbox['west'],
        ]);

        // §13.1 — sans ces chiffres, l'arbitrage sur l'avenir du module se
        // ferait à l'opinion.
        foreach ($incidentIds as $incidentId) {
            $action = in_array($incidentId, $best['still_crossed'], true) ? 'no_alternative' : 'avoided';

            RouteIncidentHit::where('route_id', $route->id)
                ->where('incident_id', $incidentId)
                ->update(['user_action' => $action, 'acted_at' => now()]);
        }
    }

    /**
     * @param  Collection<int, array{incident: Incident, min_distance_m: int}>  $hits
     */
    private function recordHits(Route $route, Collection $hits, string $phase): void
    {
        foreach ($hits as $hit) {
            RouteIncidentHit::updateOrCreate(
                ['route_id' => $route->id, 'incident_id' => $hit['incident']->id],
                [
                    'min_distance_m' => $hit['min_distance_m'],
                    'detected_phase' => $phase,
                    'detected_at'    => now(),
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeAlternatives(RouteResult $result): array
    {
        return array_map(static fn (RouteAlternative $a) => [
            'polyline'   => $a->polyline,
            'distance_m' => $a->distanceM,
            'duration_s' => $a->durationS,
            'label'      => $a->label(),
        ], $result->alternatives);
    }

    private function countBucket(int $count): string
    {
        if ($count <= 1) {
            return (string) $count;
        }
        if ($count <= 3) {
            return '2-3';
        }

        return '4+';
    }

    private function distanceBucket(int $meters): string
    {
        if ($meters < 1000) {
            return '<1km';
        }
        if ($meters < 5000) {
            return '1-5km';
        }
        if ($meters < 15000) {
            return '5-15km';
        }

        return '>15km';
    }

    private function durationBucket(int $seconds): string
    {
        if ($seconds < 300) {
            return '<5min';
        }
        if ($seconds < 900) {
            return '5-15min';
        }
        if ($seconds < 1800) {
            return '15-30min';
        }

        return '>30min';
    }
}
