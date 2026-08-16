<?php

namespace App\Services\Incidents;

use App\Models\AlertReport;
use App\Models\Incident;
use App\Support\Geo;

/**
 * Construction de la géométrie d'un incident — CDC V4.1 §4.6
 *
 * Contrainte absolue : l'utilisateur ne saisit JAMAIS de géométrie. Une
 * personne qui signale une agression est en état de stress, elle ne dessinera
 * pas de polygone. Le système déduit tout du type et de la trace GPS.
 *
 * Cas 1 — le signaleur est en déplacement : les ~120 derniers mètres de sa
 *          propre trace SONT la géométrie de la voie. Zéro appel externe.
 * Cas 2 — le signaleur est à l'arrêt ou piéton : repli sur un polygone serré
 *          de 80 m (20 100 m², frontière du régime précis de HERE).
 */
class IncidentGeometryBuilder
{
    /**
     * Géométrie d'un incident naissant, depuis un signalement unique.
     *
     * @return array{
     *     geometry_type: string,
     *     geometry: array<int, array{0: float, 1: float}>,
     *     danger_buffer_m: int,
     *     notify_radius_m: int,
     *     display_radius_m: int,
     *     centroid_lat: float,
     *     centroid_lng: float,
     *     bbox_north: float, bbox_south: float, bbox_east: float, bbox_west: float
     * }
     */
    public function buildFromReport(AlertReport $report): array
    {
        $config = $this->typeConfig($report->type);
        $corridorAllowed = ($config['geometry_type'] ?? 'polygon') === 'corridor';

        if ($corridorAllowed && $report->hasUsableTrace()) {
            $geometry = $this->corridorFromTrace($report->tracePoints());

            if ($geometry !== []) {
                return $this->assemble('corridor', $geometry, $report->type, $report->gps_accuracy_m);
            }
        }

        // Cas 2 — repli polygone serré
        $radius = (int) ($config['polygon_fallback_radius_m'] ?? 80);
        $polygon = Geo::circleToPolygon(
            $report->lat,
            $report->lng,
            $radius,
            (int) config('incidents.geometry.polygon_vertices', 12)
        );

        return $this->assemble('polygon', $polygon, $report->type, $report->gps_accuracy_m);
    }

    /**
     * Géométrie recalculée après fusion d'un nouveau signalement — §4.5.
     *
     * Le centroïde de N signalements indépendants est significativement plus
     * précis que chacun pris isolément : la géométrie devient fiable *grâce à*
     * la nature communautaire de la donnée.
     *
     * @param  \Illuminate\Support\Collection<int, AlertReport>  $reports
     */
    public function rebuildFromReports(Incident $incident, $reports): array
    {
        $reports = $reports->values();
        $config = $this->typeConfig($incident->type);
        $hullThreshold = (int) config('incidents.clustering.convex_hull_min_reports', 4);

        $points = $reports->map(fn (AlertReport $r) => [$r->lat, $r->lng])->all();
        $bestAccuracy = $reports->min('gps_accuracy_m');

        // ≥ 4 signalements → l'enveloppe convexe donne l'étendue réelle.
        // Une manifestation qui progresse dessine sa propre forme.
        if (count($points) >= $hullThreshold) {
            $hull = Geo::convexHull($points);

            if (count($hull) >= 3) {
                // Le polygone d'évitement doit couvrir la surface, pas juste
                // les points signalés : on l'élargit du rayon de repli.
                $expanded = $this->expandPolygon(
                    $hull,
                    (float) ($config['polygon_fallback_radius_m'] ?? 80)
                );

                return $this->assemble('polygon', $expanded, $incident->type, $bestAccuracy);
            }
        }

        // Sinon : on garde la géométrie du signalement le plus précis, recentrée
        // sur le centroïde de l'ensemble.
        $best = $reports->sortBy(fn (AlertReport $r) => $r->gps_accuracy_m ?? 999)->first();
        $centroid = Geo::centroid($points);

        if (($config['geometry_type'] ?? 'polygon') === 'corridor' && $best?->hasUsableTrace()) {
            $corridor = $this->corridorFromTrace($best->tracePoints());

            if ($corridor !== []) {
                return $this->assemble('corridor', $corridor, $incident->type, $bestAccuracy);
            }
        }

        $polygon = Geo::circleToPolygon(
            $centroid[0],
            $centroid[1],
            (float) ($config['polygon_fallback_radius_m'] ?? 80),
            (int) config('incidents.geometry.polygon_vertices', 12)
        );

        return $this->assemble('polygon', $polygon, $incident->type, $bestAccuracy);
    }

    /**
     * Derniers mètres de la trace GPS → polyligne du corridor.
     *
     * @param  array<int, array{0: float, 1: float}>  $trace
     * @return array<int, array{0: float, 1: float}>
     */
    private function corridorFromTrace(array $trace): array
    {
        $maxLength = (float) config('incidents.geometry.corridor_length_m', 120);
        $minPoints = (int) config('incidents.geometry.corridor_min_points', 2);

        if (count($trace) < $minPoints) {
            return [];
        }

        // La trace arrive du plus ancien au plus récent : on remonte depuis la
        // fin jusqu'à couvrir corridor_length_m.
        $reversed = array_reverse($trace);
        $kept = [$reversed[0]];
        $accumulated = 0.0;

        for ($i = 1, $n = count($reversed); $i < $n; $i++) {
            $accumulated += Geo::haversine(
                $reversed[$i - 1][0],
                $reversed[$i - 1][1],
                $reversed[$i][0],
                $reversed[$i][1]
            );

            $kept[] = $reversed[$i];

            if ($accumulated >= $maxLength) {
                break;
            }
        }

        // Une trace immobile (tous les points confondus) ne fait pas un corridor
        if (Geo::polylineLength($kept) < 10) {
            return [];
        }

        return array_reverse($kept);
    }

    /**
     * Élargit un polygone en poussant chaque sommet de $marginM depuis le centroïde.
     *
     * @param  array<int, array{0: float, 1: float}>  $polygon
     * @return array<int, array{0: float, 1: float}>
     */
    private function expandPolygon(array $polygon, float $marginM): array
    {
        $centroid = Geo::centroid($polygon);
        $cosLat = max(cos(deg2rad($centroid[0])), 0.000001);

        return array_map(static function (array $p) use ($centroid, $marginM, $cosLat) {
            $dLatM = ($p[0] - $centroid[0]) * Geo::METERS_PER_DEGREE_LAT;
            $dLngM = ($p[1] - $centroid[1]) * Geo::METERS_PER_DEGREE_LAT * $cosLat;
            $dist = sqrt($dLatM * $dLatM + $dLngM * $dLngM);

            if ($dist < 0.001) {
                return $p;
            }

            $factor = ($dist + $marginM) / $dist;

            return [
                round($centroid[0] + ($dLatM * $factor) / Geo::METERS_PER_DEGREE_LAT, 7),
                round($centroid[1] + ($dLngM * $factor) / (Geo::METERS_PER_DEGREE_LAT * $cosLat), 7),
            ];
        }, $polygon);
    }

    /**
     * Assemble la géométrie et les trois rayons découplés (§4.1).
     *
     * @param  array<int, array{0: float, 1: float}>  $geometry
     */
    private function assemble(
        string $geometryType,
        array $geometry,
        string $type,
        ?int $gpsAccuracy
    ): array {
        $config = $this->typeConfig($type);
        $centroid = Geo::centroid($geometry);
        $bbox = Geo::bboxOf($geometry);

        return [
            'geometry_type'    => $geometryType,
            'geometry'         => $geometry,
            'danger_buffer_m'  => $this->dangerBuffer($config, $gpsAccuracy),
            'notify_radius_m'  => $this->clamp(
                (int) ($config['notify_radius_m'] ?? 500),
                (int) config('incidents.geometry.notify_radius_min_m', 200),
                (int) config('incidents.geometry.notify_radius_max_m', 2000)
            ),
            'display_radius_m' => $this->displayRadius($config, $gpsAccuracy),
            'centroid_lat'     => $centroid[0],
            'centroid_lng'     => $centroid[1],
            'bbox_north'       => $bbox['north'],
            'bbox_south'       => $bbox['south'],
            'bbox_east'        => $bbox['east'],
            'bbox_west'        => $bbox['west'],
        ];
    }

    /**
     * Buffer d'évitement, élargi quand le fix GPS est mauvais (§4.8).
     */
    private function dangerBuffer(array $config, ?int $gpsAccuracy): int
    {
        $base = $config['danger_buffer_m'] ?? config('incidents.geometry.danger_buffer_min_m', 15);

        $widenAbove = (int) config('incidents.gps.accuracy_widen_m', 40);
        $factor = (float) config('incidents.gps.accuracy_widen_factor', 1.5);

        if ($gpsAccuracy !== null && $gpsAccuracy > $widenAbove) {
            $base = (int) round($base * $factor);
        }

        return $this->clamp(
            (int) $base,
            (int) config('incidents.geometry.danger_buffer_min_m', 15),
            (int) config('incidents.geometry.danger_buffer_max_m', 60)
        );
    }

    /**
     * Rayon d'affichage — le halo exprime l'incertitude (§4.4), il grandit
     * donc avec l'imprécision du fix.
     */
    private function displayRadius(array $config, ?int $gpsAccuracy): int
    {
        $base = (int) ($config['display_radius_m'] ?? 200);

        if ($gpsAccuracy !== null) {
            $base = max($base, $gpsAccuracy * 2);
        }

        return $this->clamp($base, 50, 2000);
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private function typeConfig(string $type): array
    {
        $types = config('incidents.types', []);

        return $types[$type] ?? $types['other'] ?? [];
    }
}
