<?php

namespace App\Models;

use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Incident communautaire — CDC V4.1 §7.2
 *
 * Objet publié, affiché et routé. Agrège 1 à N signalements (§4.5).
 *
 * @property string $type
 * @property string $severity
 * @property string $geometry_type
 * @property array  $geometry
 */
class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'severity',
        'geometry_type',
        'geometry',
        'danger_buffer_m',
        'notify_radius_m',
        'display_radius_m',
        'centroid_lat',
        'centroid_lng',
        'bbox_north',
        'bbox_south',
        'bbox_east',
        'bbox_west',
        'report_count',
        'confirm_count',
        'clear_count',
        'confidence_score',
        'affects_routing',
        'status',
        'expires_at',
        'resolved_at',
    ];

    protected $casts = [
        'geometry'         => 'array',
        'danger_buffer_m'  => 'integer',
        'notify_radius_m'  => 'integer',
        'display_radius_m' => 'integer',
        'centroid_lat'     => 'float',
        'centroid_lng'     => 'float',
        'bbox_north'       => 'float',
        'bbox_south'       => 'float',
        'bbox_east'        => 'float',
        'bbox_west'        => 'float',
        'report_count'     => 'integer',
        'confirm_count'    => 'integer',
        'clear_count'      => 'integer',
        'confidence_score' => 'float',
        'affects_routing'  => 'boolean',
        'expires_at'       => 'datetime',
        'resolved_at'      => 'datetime',
    ];

    public function reports(): HasMany
    {
        return $this->hasMany(AlertReport::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(IncidentInteraction::class);
    }

    public function routeHits(): HasMany
    {
        return $this->hasMany(RouteIncidentHit::class);
    }

    /**
     * Incidents encore vivants — §4.10 règle 3 : eux seuls comptent.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }

    /**
     * Incidents autorisés à modifier un itinéraire — §7.2.
     * Toute la politique des §4.9/§4.10 est condensée dans le booléen dérivé.
     */
    public function scopeAffectingRouting(Builder $query): Builder
    {
        return $query->active()->where('affects_routing', true);
    }

    /**
     * Incidents dont la bbox recoupe celle fournie — utilise idx_active_bbox.
     *
     * @param  array{north: float, south: float, east: float, west: float}  $bbox
     */
    public function scopeInBbox(Builder $query, array $bbox): Builder
    {
        return $query->where('bbox_south', '<=', $bbox['north'])
            ->where('bbox_north', '>=', $bbox['south'])
            ->where('bbox_west', '<=', $bbox['east'])
            ->where('bbox_east', '>=', $bbox['west']);
    }

    /**
     * Configuration §4.9 du type de cet incident.
     */
    public function typeConfig(): array
    {
        $types = config('incidents.types', []);

        return $types[$this->type] ?? $types['other'] ?? [];
    }

    public function emoji(): string
    {
        return $this->typeConfig()['emoji'] ?? '🔔';
    }

    public function label(): string
    {
        return $this->typeConfig()['label'] ?? 'Incident';
    }

    /**
     * Géométrie normalisée en liste de points [lat, lng].
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function geometryPoints(): array
    {
        return array_map(
            static fn ($p) => [(float) $p[0], (float) $p[1]],
            $this->geometry ?? []
        );
    }

    /**
     * Distance d'un point à la géométrie d'évitement, en mètres.
     * 0 si le point est à l'intérieur d'un polygone.
     */
    public function distanceTo(float $lat, float $lng): float
    {
        $points = $this->geometryPoints();

        if ($points === []) {
            return Geo::haversine($lat, $lng, $this->centroid_lat, $this->centroid_lng);
        }

        return $this->geometry_type === 'polygon'
            ? Geo::distancePointToPolygon($lat, $lng, $points)
            : Geo::distancePointToPolyline($lat, $lng, $points);
    }

    /**
     * @return array{north: float, south: float, east: float, west: float}
     */
    public function bbox(): array
    {
        return [
            'north' => (float) $this->bbox_north,
            'south' => (float) $this->bbox_south,
            'east'  => (float) $this->bbox_east,
            'west'  => (float) $this->bbox_west,
        ];
    }

    /**
     * Fiabilité affichée sur la fiche — §6.5.
     */
    public function reliabilityLabel(): string
    {
        return $this->report_count >= 3 ? 'confirmé' : 'non confirmé';
    }
}
