<?php

namespace App\Services\Routing;

use App\Services\Routing\DTO\AvoidArea;
use App\Services\Routing\DTO\RouteAlternative;
use App\Services\Routing\DTO\RouteRequest;
use App\Services\Routing\DTO\RouteResult;
use App\Support\FlexiblePolyline;
use App\Support\Geo;

/**
 * Fournisseur de secours hors production — CDC V4.1 §5.3
 *
 * Sélectionné automatiquement quand `services.here.api_key` est absent. Il
 * permet de développer et de tester l'intégralité des phases Trajets sans clé
 * ni facture : géométrie synthétique, zéro appel réseau.
 *
 * Il n'a AUCUNE connaissance du réseau routier. Ce qu'il produit est une
 * approximation à vol d'oiseau, jamais un itinéraire réel — il n'est
 * volontairement pas utilisable en production.
 */
class FakeRoutingProvider implements RoutingProvider
{
    /** Vitesses moyennes grossières, en km/h. */
    private const SPEEDS = ['car' => 35, 'scooter' => 30, 'pedestrian' => 5];

    /** Détour appliqué à chaque alternative, en fraction de la distance. */
    private const ALTERNATIVE_OFFSET = 0.12;

    public function name(): string
    {
        return 'fake';
    }

    public function route(RouteRequest $request): RouteResult
    {
        $origin = [$request->originLat, $request->originLng];
        $destination = [$request->destinationLat, $request->destinationLng];

        $alternatives = [];
        $count = max(1, $request->alternatives + 1);

        for ($i = 0; $i < $count; $i++) {
            // Chaque alternative bombe un peu plus fort d'un côté ou de l'autre
            $bulge = $i === 0 ? 0.0 : self::ALTERNATIVE_OFFSET * ceil($i / 2) * ($i % 2 === 0 ? 1 : -1);
            $points = $this->buildPath($origin, $destination, $bulge, $request->avoidAreas);

            $distance = (int) round(Geo::polylineLength($points));
            $speed = self::SPEEDS[$request->transportMode] ?? 35;

            $alternatives[] = new RouteAlternative(
                polyline: FlexiblePolyline::encode($points),
                distanceM: $distance,
                durationS: (int) round($distance / ($speed * 1000 / 3600)),
                labels: [$i === 0 ? 'itinéraire direct' : 'variante ' . $i],
                notices: $this->noticesFor($points, $request->avoidAreas),
            );
        }

        return new RouteResult($alternatives, $this->name());
    }

    /**
     * Trace un arc entre deux points, écarté des zones à éviter.
     *
     * @param  array{0: float, 1: float}  $origin
     * @param  array{0: float, 1: float}  $destination
     * @param  array<int, AvoidArea>  $avoidAreas
     * @return array<int, array{0: float, 1: float}>
     */
    private function buildPath(array $origin, array $destination, float $bulge, array $avoidAreas): array
    {
        $steps = 40;
        $points = [];

        // Normale au segment, pour bomber l'arc perpendiculairement
        $dLat = $destination[0] - $origin[0];
        $dLng = $destination[1] - $origin[1];

        // Un évitement demandé pousse l'arc un peu plus loin
        $bulge += $avoidAreas === [] ? 0.0 : self::ALTERNATIVE_OFFSET;

        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            // Amplitude maximale au milieu du trajet, nulle aux extrémités
            $arc = sin($t * M_PI) * $bulge;

            $points[] = [
                round($origin[0] + $dLat * $t - $dLng * $arc, 7),
                round($origin[1] + $dLng * $t + $dLat * $arc, 7),
            ];
        }

        return $points;
    }

    /**
     * Signale une traversée résiduelle, comme le ferait HERE (§5.2).
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     * @param  array<int, AvoidArea>  $avoidAreas
     * @return array<int, array{code: string, title: string, severity: string}>
     */
    private function noticesFor(array $points, array $avoidAreas): array
    {
        foreach ($avoidAreas as $area) {
            foreach ($points as $point) {
                $distance = $area->type === 'polygon'
                    ? Geo::distancePointToPolygon($point[0], $point[1], $area->points)
                    : Geo::distancePointToPolyline($point[0], $point[1], $area->points);

                if ($distance <= ($area->radiusM ?? 0)) {
                    return [[
                        'code'     => 'violatedBlockedRoad',
                        'title'    => 'Violated road that is blocked, due to `avoid[areas]`.',
                        'severity' => 'critical',
                    ]];
                }
            }
        }

        return [];
    }
}
