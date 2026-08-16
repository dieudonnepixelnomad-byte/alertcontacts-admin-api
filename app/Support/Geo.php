<?php

namespace App\Support;

/**
 * Point de vérité géométrique — CDC V4.1 §17.B
 *
 * Toute la géométrie du module Incidents/Trajets passe par ici. Le repo
 * comptait cinq implémentations Haversine divergentes avant V4.1 ; aucun
 * code neuf n'en ajoute une sixième.
 *
 * Convention : un point est un couple [lat, lng] en degrés décimaux.
 */
final class Geo
{
    /** Rayon terrestre moyen, en mètres. */
    public const EARTH_RADIUS_M = 6371000;

    /** Longueur d'un degré de latitude, en mètres (approximation §17.A). */
    public const METERS_PER_DEGREE_LAT = 111320;

    /**
     * Distance orthodromique entre deux points, en mètres.
     */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lng2 - $lng1);

        $a = sin($dPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }

    /**
     * Cercle → polygone à N sommets — §17.A.
     *
     * Erreur d'aire avec N = 12 : ~3,4 % par défaut. Acceptable et
     * conservatrice, puisqu'elle sous-estime légèrement la zone évitée.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function circleToPolygon(float $lat, float $lng, float $radiusM, int $n = 12): array
    {
        $points = [];
        $cosLat = max(cos(deg2rad($lat)), 0.000001); // garde-fou proche des pôles

        for ($k = 0; $k < $n; $k++) {
            $theta = 2 * M_PI * $k / $n;
            $points[] = [
                round($lat + ($radiusM / self::METERS_PER_DEGREE_LAT) * cos($theta), 7),
                round($lng + ($radiusM / (self::METERS_PER_DEGREE_LAT * $cosLat)) * sin($theta), 7),
            ];
        }

        return $points;
    }

    /**
     * Boîte englobante d'un ensemble de points, élargie d'une marge en mètres.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array{north: float, south: float, east: float, west: float}
     */
    public static function bboxOf(array $points, float $marginM = 0): array
    {
        if ($points === []) {
            throw new \InvalidArgumentException('bboxOf: aucun point fourni.');
        }

        $lats = array_column($points, 0);
        $lngs = array_column($points, 1);

        $north = max($lats);
        $south = min($lats);
        $east = max($lngs);
        $west = min($lngs);

        if ($marginM > 0) {
            $dLat = $marginM / self::METERS_PER_DEGREE_LAT;
            // Marge longitudinale calculée à la latitude la plus proche de l'équateur :
            // c'est là qu'un degré vaut le plus de mètres, donc la marge la plus large.
            $refLat = min(abs($north), abs($south));
            $cosLat = max(cos(deg2rad($refLat)), 0.000001);
            $dLng = $marginM / (self::METERS_PER_DEGREE_LAT * $cosLat);

            $north = min(90, $north + $dLat);
            $south = max(-90, $south - $dLat);
            $east = min(180, $east + $dLng);
            $west = max(-180, $west - $dLng);
        }

        return [
            'north' => round($north, 7),
            'south' => round($south, 7),
            'east'  => round($east, 7),
            'west'  => round($west, 7),
        ];
    }

    /**
     * Centroïde arithmétique d'un ensemble de points.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array{0: float, 1: float}
     */
    public static function centroid(array $points): array
    {
        if ($points === []) {
            throw new \InvalidArgumentException('centroid: aucun point fourni.');
        }

        $n = count($points);

        return [
            round(array_sum(array_column($points, 0)) / $n, 7),
            round(array_sum(array_column($points, 1)) / $n, 7),
        ];
    }

    /**
     * Distance minimale d'un point à un segment, en mètres.
     *
     * Projection en plan local équirectangulaire : sur des segments de rue
     * (quelques centaines de mètres), l'écart avec un calcul sphérique exact
     * est très inférieur à la précision d'un signalement communautaire.
     */
    public static function distancePointToSegment(
        float $lat,
        float $lng,
        float $latA,
        float $lngA,
        float $latB,
        float $lngB
    ): float {
        $cosLat = max(cos(deg2rad($lat)), 0.000001);

        // Passage en mètres, origine au point A
        $toM = static fn (float $dLat, float $dLng): array => [
            $dLat * self::METERS_PER_DEGREE_LAT,
            $dLng * self::METERS_PER_DEGREE_LAT * $cosLat,
        ];

        [$px, $py] = $toM($lat - $latA, $lng - $lngA);
        [$bx, $by] = $toM($latB - $latA, $lngB - $lngA);

        $segLenSq = $bx * $bx + $by * $by;

        if ($segLenSq < 1e-9) {
            return self::haversine($lat, $lng, $latA, $lngA);
        }

        // Paramètre de projection, borné au segment
        $t = max(0.0, min(1.0, ($px * $bx + $py * $by) / $segLenSq));

        $dx = $px - $t * $bx;
        $dy = $py - $t * $by;

        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Distance minimale d'un point à une polyligne, en mètres.
     *
     * @param  array<int, array{0: float, 1: float}>  $polyline
     */
    public static function distancePointToPolyline(float $lat, float $lng, array $polyline): float
    {
        $count = count($polyline);

        if ($count === 0) {
            return INF;
        }

        if ($count === 1) {
            return self::haversine($lat, $lng, $polyline[0][0], $polyline[0][1]);
        }

        $min = INF;
        $polyline = array_values($polyline);

        for ($i = 0; $i < $count - 1; $i++) {
            $d = self::distancePointToSegment(
                $lat,
                $lng,
                $polyline[$i][0],
                $polyline[$i][1],
                $polyline[$i + 1][0],
                $polyline[$i + 1][1]
            );

            if ($d < $min) {
                $min = $d;

                // Court-circuit : on ne peut pas faire mieux que zéro
                if ($min === 0.0) {
                    return 0.0;
                }
            }
        }

        return $min;
    }

    /**
     * Distance minimale d'un point à un polygone (0 si le point est dedans).
     *
     * @param  array<int, array{0: float, 1: float}>  $polygon  anneau extérieur, non fermé
     */
    public static function distancePointToPolygon(float $lat, float $lng, array $polygon): float
    {
        if (self::pointInPolygon($lat, $lng, $polygon)) {
            return 0.0;
        }

        // Fermer l'anneau pour mesurer la distance au bord
        $ring = array_values($polygon);
        $ring[] = $ring[0];

        return self::distancePointToPolyline($lat, $lng, $ring);
    }

    /**
     * Test d'appartenance à un polygone — algorithme du lancer de rayon.
     *
     * @param  array<int, array{0: float, 1: float}>  $polygon
     */
    public static function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $polygon = array_values($polygon);
        $count = count($polygon);

        if ($count < 3) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lngI] = $polygon[$i];
            [$latJ, $lngJ] = $polygon[$j];

            $intersects = (($lngI > $lng) !== ($lngJ > $lng))
                && ($lat < ($latJ - $latI) * ($lng - $lngI) / (($lngJ - $lngI) ?: 1e-12) + $latI);

            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Enveloppe convexe (monotone chain) — §4.5.
     *
     * À partir de 4 signalements, elle donne l'étendue réelle de l'incident :
     * une manifestation qui progresse dessine sa propre forme.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    public static function convexHull(array $points): array
    {
        $unique = [];
        foreach ($points as $p) {
            $unique[$p[0] . ',' . $p[1]] = [(float) $p[0], (float) $p[1]];
        }
        $points = array_values($unique);

        if (count($points) < 3) {
            return $points;
        }

        usort($points, static fn ($a, $b) => $a[1] <=> $b[1] ?: $a[0] <=> $b[0]);

        // Produit vectoriel — > 0 : virage à gauche
        $cross = static fn (array $o, array $a, array $b): float =>
            ($a[1] - $o[1]) * ($b[0] - $o[0]) - ($a[0] - $o[0]) * ($b[1] - $o[1]);

        $build = static function (array $seq) use ($cross): array {
            $chain = [];
            foreach ($seq as $p) {
                while (count($chain) >= 2 && $cross($chain[count($chain) - 2], $chain[count($chain) - 1], $p) <= 0) {
                    array_pop($chain);
                }
                $chain[] = $p;
            }
            array_pop($chain); // le dernier point est repris par l'autre chaîne

            return $chain;
        };

        $hull = array_merge($build($points), $build(array_reverse($points)));

        return count($hull) >= 3 ? $hull : $points;
    }

    /**
     * Longueur cumulée d'une polyligne, en mètres.
     *
     * @param  array<int, array{0: float, 1: float}>  $polyline
     */
    public static function polylineLength(array $polyline): float
    {
        $polyline = array_values($polyline);
        $total = 0.0;

        for ($i = 0, $n = count($polyline) - 1; $i < $n; $i++) {
            $total += self::haversine(
                $polyline[$i][0],
                $polyline[$i][1],
                $polyline[$i + 1][0],
                $polyline[$i + 1][1]
            );
        }

        return $total;
    }

    /**
     * Sous-échantillonne une polyligne : un point tous les ~$stepM mètres.
     *
     * Utilisé par IncidentIntersectionService (§5.4 étape 3) — on ne teste
     * jamais les 4 000 points bruts d'un itinéraire.
     *
     * @param  array<int, array{0: float, 1: float}>  $polyline
     * @return array<int, array{0: float, 1: float}>
     */
    public static function samplePolyline(array $polyline, float $stepM): array
    {
        $polyline = array_values($polyline);
        $count = count($polyline);

        if ($count <= 2 || $stepM <= 0) {
            return $polyline;
        }

        $sampled = [$polyline[0]];
        $accumulated = 0.0;

        for ($i = 1; $i < $count; $i++) {
            $accumulated += self::haversine(
                $polyline[$i - 1][0],
                $polyline[$i - 1][1],
                $polyline[$i][0],
                $polyline[$i][1]
            );

            if ($accumulated >= $stepM) {
                $sampled[] = $polyline[$i];
                $accumulated = 0.0;
            }
        }

        // Le point d'arrivée compte toujours
        $last = $polyline[$count - 1];
        if (end($sampled) !== $last) {
            $sampled[] = $last;
        }

        return $sampled;
    }

    /**
     * Deux boîtes englobantes se recoupent-elles ?
     *
     * @param  array{north: float, south: float, east: float, west: float}  $a
     * @param  array{north: float, south: float, east: float, west: float}  $b
     */
    public static function bboxIntersects(array $a, array $b): bool
    {
        return $a['south'] <= $b['north']
            && $a['north'] >= $b['south']
            && $a['west'] <= $b['east']
            && $a['east'] >= $b['west'];
    }
}
