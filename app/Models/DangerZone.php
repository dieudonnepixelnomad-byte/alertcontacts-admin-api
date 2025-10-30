<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DangerZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'center_lat',
        'center_lng',
        'radius_m',
        'severity',
        'danger_type',
        'confirmations',
        'last_report_at',
        'reported_by',
        'is_active',
    ];

    protected $casts = [
        'center_lat' => 'float',
        'center_lng' => 'float',
        'radius_m' => 'float',
        'confirmations' => 'integer',
        'last_report_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Relation avec l'utilisateur qui a signalé la zone
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Relation avec les confirmations de cette zone
     */
    public function dangerZoneConfirmations(): HasMany
    {
        return $this->hasMany(DangerZoneConfirmation::class);
    }

    /**
     * Relation avec les signalements d'abus de cette zone
     */
    public function dangerZoneReports(): HasMany
    {
        return $this->hasMany(DangerZoneReport::class);
    }

    /**
     * Scope pour les zones actives
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour les zones récentes (moins de 30 jours)
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('last_report_at', '>=', now()->subDays($days));
    }

    /**
     * Scope pour filtrer par gravité minimale
     */
    public function scopeMinSeverity($query, string $minSeverity)
    {
        $severityOrder = ['low' => 1, 'med' => 2, 'high' => 3];
        $minLevel = $severityOrder[$minSeverity] ?? 1;
        
        return $query->whereIn('severity', array_keys(array_filter($severityOrder, fn($level) => $level >= $minLevel)));
    }

    /**
     * Scope pour filtrer par rayon géographique
     */
    public function scopeWithinRadius($query, float $lat, float $lng, float $radiusKm)
    {
        // Utilisation de la formule haversine pour calculer la distance
        return $query->whereRaw("
            (6371 * acos(
                cos(radians(?)) * 
                cos(radians(center_lat)) * 
                cos(radians(center_lng) - radians(?)) + 
                sin(radians(?)) * 
                sin(radians(center_lat))
            )) <= ?
        ", [$lat, $lng, $lat, $radiusKm]);
    }

    /**
     * Scope pour filtrer par type de danger
     */
    public function scopeByDangerType($query, string $dangerType)
    {
        return $query->where('danger_type', $dangerType);
    }

    /**
     * Scope pour filtrer par types de danger multiples
     */
    public function scopeByDangerTypes($query, array $dangerTypes)
    {
        return $query->whereIn('danger_type', $dangerTypes);
    }
}