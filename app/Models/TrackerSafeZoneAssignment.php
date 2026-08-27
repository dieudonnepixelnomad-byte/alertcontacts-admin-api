<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TrackerSafeZoneAssignment extends Model
{
    protected $fillable = ['tracker_id','safe_zone_id','is_active','notify_entry','notify_exit'];
    protected $casts = ['is_active'=>'boolean','notify_entry'=>'boolean','notify_exit'=>'boolean'];
    public function tracker(): BelongsTo { return $this->belongsTo(GpsTracker::class, 'tracker_id'); }
    public function safeZone(): BelongsTo { return $this->belongsTo(SafeZone::class, 'safe_zone_id'); }
}
