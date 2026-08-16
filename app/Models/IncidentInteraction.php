<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interaction utilisateur → incident : confirm, clear (§4.7a) ou report d'abus.
 *
 * L'unicité (incident_id, user_id, action) en base rend les compteurs
 * idempotents.
 */
class IncidentInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'user_id',
        'action',
        'reason',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
