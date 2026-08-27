<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TrackerLocation extends Model
{
    protected $fillable = ['tracker_id', 'latitude', 'longitude', 'accuracy', 'speed', 'heading', 'battery_level', 'captured_at_device', 'received_at', 'source'];
    protected $casts = ['latitude'=>'float','longitude'=>'float','accuracy'=>'float','speed'=>'float','heading'=>'float','battery_level'=>'integer','captured_at_device'=>'datetime','received_at'=>'datetime'];
    public function tracker(): BelongsTo { return $this->belongsTo(GpsTracker::class, 'tracker_id'); }
}
