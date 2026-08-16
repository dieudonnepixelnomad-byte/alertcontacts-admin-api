<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rencontre trajet × incident — CDC V4.1 §7.4
 *
 * Tableau de bord produit du module (§13) et compteur de quota (§10.2).
 */
class RouteIncidentHit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'route_id',
        'incident_id',
        'min_distance_m',
        'detected_phase',
        'user_action',
        'notified',
        'detected_at',
        'acted_at',
    ];

    protected $casts = [
        'min_distance_m' => 'integer',
        'notified'       => 'boolean',
        'detected_at'    => 'datetime',
        'acted_at'       => 'datetime',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
