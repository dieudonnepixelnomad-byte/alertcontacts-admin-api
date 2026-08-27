<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GpsTracker extends Model
{
    protected $fillable = ['owner_id', 'name', 'provider', 'model', 'external_identifier', 'status', 'last_position_at', 'last_seen_at', 'battery_level', 'metadata'];
    protected $casts = ['last_position_at' => 'datetime', 'last_seen_at' => 'datetime', 'battery_level' => 'integer', 'metadata' => 'array'];
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function locations(): HasMany { return $this->hasMany(TrackerLocation::class, 'tracker_id'); }
    public function zoneAssignments(): HasMany { return $this->hasMany(TrackerSafeZoneAssignment::class, 'tracker_id'); }
}
