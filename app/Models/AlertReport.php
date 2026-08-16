<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Signalement brut — CDC V4.1 §7.1
 *
 * Ce qu'un utilisateur envoie. Jamais publié tel quel : le clustering (§4.5)
 * le rattache à un incident, seul objet affiché et routé.
 */
class AlertReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'user_id',
        'type',
        'severity',
        'lat',
        'lng',
        'gps_accuracy_m',
        'gps_trace',
        'was_moving',
        'speed_kmh',
        'comment',
        'photo_url',
        'visibility',
    ];

    protected $casts = [
        'lat'            => 'float',
        'lng'            => 'float',
        'gps_accuracy_m' => 'integer',
        'gps_trace'      => 'array',
        'was_moving'     => 'boolean',
        'speed_kmh'      => 'integer',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La trace GPS est-elle exploitable pour construire un corridor ? (§4.6 cas 1)
     */
    public function hasUsableTrace(): bool
    {
        $trace = $this->gps_trace;

        return is_array($trace)
            && count($trace) >= config('incidents.geometry.corridor_min_points', 2);
    }

    /**
     * Trace normalisée en liste de points [lat, lng].
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function tracePoints(): array
    {
        $points = [];

        foreach ($this->gps_trace ?? [] as $point) {
            // Deux formes acceptées : [lat, lng] ou {lat, lng}
            $lat = $point['lat'] ?? $point[0] ?? null;
            $lng = $point['lng'] ?? $point[1] ?? null;

            if ($lat !== null && $lng !== null) {
                $points[] = [(float) $lat, (float) $lng];
            }
        }

        return $points;
    }
}
