<?php

namespace App\Services\Routing\DTO;

use App\Models\Incident;
use App\Support\Geo;

/**
 * Zone à éviter, dans le vocabulaire du moteur de routage — CDC V4.1 §4.2
 *
 * Deux primitives suffisent : `corridor` (le danger est SUR une voie) et
 * `polygon` (le danger couvre réellement une surface). `bbox` n'étant qu'un
 * polygone dégénéré, on ne l'expose pas.
 */
final class AvoidArea
{
    /**
     * @param  'corridor'|'polygon'  $type
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    public function __construct(
        public readonly string $type,
        public readonly array $points,
        public readonly ?int $radiusM = null,
        public readonly ?int $incidentId = null,
    ) {
    }

    /**
     * Construit la zone d'évitement d'un incident.
     *
     * Le corridor est la primitive décisive : 120 m × 20 m = 2 400 m², contre
     * 3 141 000 m² pour le disque de 1 km du V4.0. Facteur 1 300 sur la
     * surface, et surtout bascule dans le régime d'évitement précis de HERE,
     * qui capture même les segments partiellement inclus (§4.3).
     */
    public static function fromIncident(Incident $incident): self
    {
        $points = $incident->geometryPoints();

        if ($points === []) {
            $points = Geo::circleToPolygon(
                $incident->centroid_lat,
                $incident->centroid_lng,
                $incident->display_radius_m,
                (int) config('incidents.geometry.polygon_vertices', 12)
            );

            return new self('polygon', $points, null, $incident->id);
        }

        if ($incident->geometry_type === 'corridor') {
            return new self('corridor', $points, (int) $incident->danger_buffer_m, $incident->id);
        }

        return new self('polygon', $points, null, $incident->id);
    }
}
