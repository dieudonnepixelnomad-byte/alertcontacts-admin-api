<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DangerZoneConfirmation extends Model
{
    use HasFactory;

    protected $fillable = [
        'danger_zone_id',
        'user_id',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    /**
     * Relation avec la zone de danger
     */
    public function dangerZone(): BelongsTo
    {
        return $this->belongsTo(DangerZone::class);
    }

    /**
     * Relation avec l'utilisateur qui a confirmé
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}