<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrackerSafeZoneEvent extends Model
{
    protected $fillable = ['tracker_id','safe_zone_id','tracker_location_id','event_type','distance_m','occurred_at','notification_sent'];
    protected $casts = ['occurred_at'=>'datetime','notification_sent'=>'boolean'];
}
