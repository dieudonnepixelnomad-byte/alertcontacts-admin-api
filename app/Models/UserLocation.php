<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UC-A1: Modèle pour stocker les positions GPS des utilisateurs
 * 
 * @property int $id
 * @property int $user_id
 * @property float $latitude
 * @property float $longitude
 * @property float|null $accuracy
 * @property float|null $speed
 * @property float|null $heading
 * @property string $captured_at_device
 * @property string $source
 * @property bool $foreground
 * @property int|null $battery_level
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class UserLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'captured_at_device',
        'source',
        'foreground',
        'battery_level',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'speed' => 'float',
        'heading' => 'float',
        'captured_at_device' => 'datetime',
        'foreground' => 'boolean',
        'battery_level' => 'integer',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope pour filtrer par utilisateur
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour filtrer par période
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('captured_at_device', [$startDate, $endDate]);
    }

    /**
     * Scope pour les positions récentes
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('captured_at_device', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope pour les positions avec une précision acceptable
     */
    public function scopeAccurate($query, float $maxAccuracy = 100.0)
    {
        return $query->where(function($q) use ($maxAccuracy) {
            $q->whereNull('accuracy')
              ->orWhere('accuracy', '<=', $maxAccuracy);
        });
    }

    /**
     * Calculer la distance avec une autre position (en mètres)
     */
    public function distanceTo(float $lat, float $lng): float
    {
        $earthRadius = 6371000; // Rayon de la Terre en mètres

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Vérifier si la position est dans un rayon donné d'un point
     */
    public function isWithinRadius(float $lat, float $lng, float $radiusMeters): bool
    {
        return $this->distanceTo($lat, $lng) <= $radiusMeters;
    }

    /**
     * Formater pour l'API
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'speed' => $this->speed,
            'heading' => $this->heading,
            'captured_at_device' => $this->captured_at_device ? $this->captured_at_device->toISOString() : null,
            'source' => $this->source,
            'foreground' => $this->foreground,
            'battery_level' => $this->battery_level,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}