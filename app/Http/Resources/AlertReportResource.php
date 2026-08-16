<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Signalement agrégé dans une fiche incident — CDC V4.1 §8.2
 *
 * L'identité du signaleur n'est jamais exposée : la fiche affiche « Signalé
 * par N personnes », pas qui.
 *
 * @mixin \App\Models\AlertReport
 */
class AlertReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'severity'   => $this->severity,
            'comment'    => $this->comment,
            'photo_url'  => $this->photo_url,
            'created_at' => $this->created_at?->toISOString(),
            'is_mine'    => $this->user_id === $request->user()?->id,
        ];
    }
}
