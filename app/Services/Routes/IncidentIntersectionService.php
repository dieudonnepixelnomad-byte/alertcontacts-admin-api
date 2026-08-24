<?php

namespace App\Services\Routes;

use App\Models\Incident;
use App\Support\FlexiblePolyline;
use App\Support\Geo;
use Illuminate\Support\Collection;

/**
 * Détection des incidents sur un trajet — CDC V4.1 §5.4 étape 3
 *
 * Entièrement côté Laravel. Aucun appel externe, aucun coût.
 *
 *   1. Décoder la polyligne → liste de points
 *   2. bbox de la polyligne + marge → SELECT des incidents actifs (index bbox)
 *   3. Sous-échantillonner la polyligne tous les ~50 m
 *   4. Pour chaque candidat : d = min(distance(point, géométrie))
 *      si d <= buffer de routage, ou rayon d'affichage pour une alerte
 *      informative, → HIT
 *   5. Trier par gravité puis par distance au point de départ
 *
 * Optimisation : filtrer d'abord par bbox. On ne teste jamais les 4 000 points
 * d'une polyligne contre l'ensemble des incidents.
 */
class IncidentIntersectionService
{
    private const SEVERITY_RANK = ['high' => 3, 'medium' => 2, 'low' => 1];

    /**
     * @return Collection<int, array{incident: Incident, min_distance_m: int, distance_from_origin_m: float}>
     */
    public function detectOnEncodedPolyline(
        string $encodedPolyline,
    ): Collection {
        return $this->detect(FlexiblePolyline::decode($encodedPolyline));
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $polyline
     * @return Collection<int, array{incident: Incident, min_distance_m: int, distance_from_origin_m: float}>
     */
    public function detect(array $polyline): Collection {
        if (count($polyline) < 2) {
            return collect();
        }

        $sampled = $this->sample($polyline);
        $candidates = $this->candidates($polyline);

        $hits = collect();

        foreach ($candidates as $incident) {
            $result = $this->closestApproach($incident, $sampled);

            if ($result === null) {
                continue;
            }

            $hits->push([
                'incident'               => $incident,
                'min_distance_m'         => (int) round($result['distance']),
                'distance_from_origin_m' => $result['fromOrigin'],
            ]);
        }

        return $this->prioritize($hits);
    }

    /**
     * Alertes communautaires actives dont la bbox recoupe celle du trajet.
     *
     * L'affichage ne se limite pas aux alertes autorisées à modifier le
     * routage : un signalement non confirmé reste utile à l'utilisateur. Le
     * booléen `affects_routing` décide séparément si « Contourner » est offert.
     *
     * @param  array<int, array{0: float, 1: float}>  $polyline
     * @return Collection<int, Incident>
     */
    private function candidates(array $polyline): Collection
    {
        $margin = (float) config('incidents.routing.bbox_margin_m', 2000);
        $bbox = Geo::bboxOf($polyline, $margin);

        return Incident::query()->active()->inBbox($bbox)->get();
    }

    /**
     * Point de la polyligne le plus proche de la géométrie de l'incident.
     *
     * @param  array<int, array{point: array{0: float, 1: float}, fromOrigin: float}>  $sampled
     * @return array{distance: float, fromOrigin: float}|null
     */
    private function closestApproach(Incident $incident, array $sampled): ?array
    {
        // Les alertes purement informatives n'ont pas de buffer de routage.
        // Pour les rendre visibles près du trajet, on utilise alors leur rayon
        // d'affichage, qui exprime justement l'incertitude du signalement.
        $buffer = (float) ($incident->danger_buffer_m ?? $incident->display_radius_m);
        $best = null;

        foreach ($sampled as $entry) {
            [$lat, $lng] = $entry['point'];

            $distance = $incident->distanceTo($lat, $lng);

            if ($distance > $buffer) {
                continue;
            }

            if ($best === null || $distance < $best['distance']) {
                $best = ['distance' => $distance, 'fromOrigin' => $entry['fromOrigin']];
            }
        }

        return $best;
    }

    /**
     * Sous-échantillonnage + distance cumulée depuis l'origine.
     *
     * La distance depuis l'origine sert à trier les HITs (§5.4 étape 6) et à
     * ne tester que la portion non parcourue pendant le trajet (§5.5).
     *
     * @param  array<int, array{0: float, 1: float}>  $polyline
     * @return array<int, array{point: array{0: float, 1: float}, fromOrigin: float}>
     */
    private function sample(array $polyline): array
    {
        $length = Geo::polylineLength($polyline);

        // §5.6 — trajet très long : sous-échantillonner davantage
        $step = $length > (float) config('incidents.routing.long_route_threshold_m', 100000)
            ? (float) config('incidents.routing.polyline_sample_step_long_m', 200)
            : (float) config('incidents.routing.polyline_sample_step_m', 50);

        $sampled = [];
        $cumulative = 0.0;
        $sinceLastSample = 0.0;

        $polyline = array_values($polyline);
        $sampled[] = ['point' => $polyline[0], 'fromOrigin' => 0.0];

        for ($i = 1, $n = count($polyline); $i < $n; $i++) {
            $segment = Geo::haversine(
                $polyline[$i - 1][0],
                $polyline[$i - 1][1],
                $polyline[$i][0],
                $polyline[$i][1]
            );

            $cumulative += $segment;
            $sinceLastSample += $segment;

            if ($sinceLastSample >= $step || $i === $n - 1) {
                $sampled[] = ['point' => $polyline[$i], 'fromOrigin' => $cumulative];
                $sinceLastSample = 0.0;
            }
        }

        return $sampled;
    }

    /**
     * Tri par gravité puis par distance au point de départ, plafonné (§5.6).
     *
     * @param  Collection<int, array{incident: Incident, min_distance_m: int, distance_from_origin_m: float}>  $hits
     * @return Collection<int, array{incident: Incident, min_distance_m: int, distance_from_origin_m: float}>
     */
    public function prioritize(Collection $hits): Collection
    {
        $cap = (int) config('incidents.routing.max_avoid_areas', 20);

        return $hits
            ->sortBy([
                fn ($a, $b) => (self::SEVERITY_RANK[$b['incident']->severity] ?? 0)
                    <=> (self::SEVERITY_RANK[$a['incident']->severity] ?? 0),
                fn ($a, $b) => $a['distance_from_origin_m'] <=> $b['distance_from_origin_m'],
            ])
            ->take($cap)
            ->values();
    }
}
