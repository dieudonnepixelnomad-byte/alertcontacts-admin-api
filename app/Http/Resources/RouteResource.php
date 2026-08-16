<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation d'un trajet — CDC V4.1 §8.1
 *
 * @mixin \App\Models\Route
 */
class RouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'origin'            => [
                'lat'   => (float) $this->origin_lat,
                'lng'   => (float) $this->origin_lng,
                'label' => $this->origin_label,
            ],
            'destination'       => [
                'lat'   => (float) $this->destination_lat,
                'lng'   => (float) $this->destination_lng,
                'label' => $this->destination_label,
            ],
            'transport_mode'    => $this->transport_mode,
            'polyline'          => $this->polyline,
            'distance_m'        => $this->distance_m,
            'duration_s'        => $this->duration_s,
            'alternatives'      => $this->alternatives ?? [],
            'selected_index'    => (int) $this->selected_index,
            'avoidance_applied' => (bool) $this->avoidance_applied,
            'avoidance_partial' => (bool) $this->avoidance_partial,
            'status'            => $this->status,
            'started_at'        => $this->started_at?->toISOString(),
            'ended_at'          => $this->ended_at?->toISOString(),
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
