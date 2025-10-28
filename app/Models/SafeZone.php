<?php
// app/Models/SafeZone.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

class SafeZone extends Model
{
    use HasFactory, HasSpatial;

    protected $table = 'safe_zones';

    protected $fillable = [
        'owner_id',
        'name',
        'icon',
        'center',
        'radius_m',
        'geom',
        'active_hours',
        'is_active',
    ];

    protected $casts = [
        'center'       => Point::class,
        'geom'         => Polygon::class,
        'active_hours' => 'array',
        'is_active'    => 'boolean',
    ];

    /** Relations **/

    // Propriétaire de la zone
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // Affectations (pivot)
    public function assignments()
    {
        return $this->hasMany(SafeZoneAssignment::class, 'safe_zone_id');
    }

    // Proches affectés (accès via pivot)
    public function contacts()
    {
        return $this->belongsToMany(User::class, 'safe_zone_assignments', 'safe_zone_id', 'assigned_user_id')
            ->withTimestamps()
            ->withPivot(['is_active', 'notify_entry', 'notify_exit', 'assigned_at', 'accepted_at']);
    }

    /** Helpers **/

    public function isCircle(): bool
    {
        return !is_null($this->center) && !is_null($this->radius_m);
    }

    public function isPolygon(): bool
    {
        return !is_null($this->geom);
    }
}
