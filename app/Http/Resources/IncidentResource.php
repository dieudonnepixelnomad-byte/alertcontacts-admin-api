<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation d'un incident — CDC V4.1 §4.4
 *
 * Affichage ≠ routage. Le client reçoit `display_radius_m` pour dessiner un
 * halo qui exprime honnêtement l'incertitude d'un signalement communautaire.
 * La géométrie d'évitement, elle, ne quitte le serveur que pour les incidents
 * qui ont réellement le droit de modifier un itinéraire : un trait fin sur une
 * seule rue prétendrait une précision que la donnée ne possède pas.
 *
 * @mixin \App\Models\Incident
 */
class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'type'             => $this->type,
            'label'            => $this->label(),
            'emoji'            => $this->emoji(),
            'severity'         => $this->severity,

            'lat'              => (float) $this->centroid_lat,
            'lng'              => (float) $this->centroid_lng,

            // Le halo — §4.4
            'display_radius_m' => (int) $this->display_radius_m,

            // Confiance — §4.8. « Signalé par N personnes », jamais « N confirment » (§6.7)
            'report_count'     => (int) $this->report_count,
            'confirm_count'    => (int) $this->confirm_count,
            'clear_count'      => (int) $this->clear_count,
            'confidence_score' => (float) $this->confidence_score,
            'reliability'      => $this->reliabilityLabel(),

            'affects_routing'  => (bool) $this->affects_routing,
            'status'           => $this->status,
            'expires_at'       => $this->expires_at?->toISOString(),
            // Un incident isolé peut être classé `rejected` à son expiration
            // (§4.10). Dans l'historique de son auteur, cela reste une
            // expiration — à distinguer d'une suppression ou d'une résolution.
            'expired_by_timeout' => $this->status === 'expired'
                || (in_array($this->status, ['active', 'rejected'], true)
                    && $this->expires_at?->isPast()
                    && $this->resolved_at === null),
            'created_at'       => $this->created_at?->toISOString(),

            // Géométrie d'évitement — exposée uniquement quand elle fait autorité
            'geometry_type'    => $this->when($this->affects_routing, $this->geometry_type),
            'geometry'         => $this->when($this->affects_routing, fn () => $this->geometryPoints()),
            'danger_buffer_m'  => $this->when($this->affects_routing, (int) $this->danger_buffer_m),

            'reports'          => AlertReportResource::collection($this->whenLoaded('reports')),
        ];
    }
}
