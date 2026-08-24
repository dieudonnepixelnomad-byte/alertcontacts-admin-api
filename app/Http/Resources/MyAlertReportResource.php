<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Signalement appartenant au compte courant.
 *
 * Contrairement à AlertReportResource, cette ressource est utilisée dans
 * l'espace privé « Mes alertes » : elle expose donc l'incident agrégé auquel
 * le signalement a été rattaché, sans divulguer les autres auteurs.
 *
 * @mixin \App\Models\AlertReport
 */
class MyAlertReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'severity'   => $this->severity,
            'comment'    => $this->comment,
            'visibility' => $this->visibility,
            'created_at' => $this->created_at?->toISOString(),
            'incident'   => new IncidentResource($this->whenLoaded('incident')),
        ];
    }
}
