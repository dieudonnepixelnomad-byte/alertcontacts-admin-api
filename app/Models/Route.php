<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trajet — CDC V4.1 §7.3
 */
class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'origin_lat',
        'origin_lng',
        'origin_label',
        'destination_lat',
        'destination_lng',
        'destination_label',
        'transport_mode',
        'polyline',
        'alternatives',
        'selected_index',
        'distance_m',
        'duration_s',
        'avoidance_applied',
        'avoidance_partial',
        'avoided_incident_ids',
        'bbox_north',
        'bbox_south',
        'bbox_east',
        'bbox_west',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'origin_lat'           => 'float',
        'origin_lng'           => 'float',
        'destination_lat'      => 'float',
        'destination_lng'      => 'float',
        'alternatives'         => 'array',
        'selected_index'       => 'integer',
        'distance_m'           => 'integer',
        'duration_s'           => 'integer',
        'avoidance_applied'    => 'boolean',
        'avoidance_partial'    => 'boolean',
        'avoided_incident_ids' => 'array',
        'bbox_north'           => 'float',
        'bbox_south'           => 'float',
        'bbox_east'            => 'float',
        'bbox_west'            => 'float',
        'started_at'           => 'datetime',
        'ended_at'             => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hits(): HasMany
    {
        return $this->hasMany(RouteIncidentHit::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
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
}
