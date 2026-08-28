<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GpsTracker;
use App\Models\TrackerLocation;
use App\Services\TrackerGeofencingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InternalTrackerTelemetryController extends Controller
{
    public function store(Request $request, TrackerGeofencingService $geofencing): JsonResponse
    {
        $secret = (string) config('services.trackers.ingest_secret', '');
        abort_if($secret === '' || !hash_equals($secret, (string) $request->header('X-Tracker-Ingest-Secret')), 403);
        $data = $request->validate(['provider'=>'required|string|max:50','external_identifier'=>'required|string|max:191','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','accuracy'=>'nullable|numeric|min:0','speed'=>'nullable|numeric|min:0','heading'=>'nullable|numeric|between:0,360','battery_level'=>'nullable|integer|between:0,100','captured_at_device'=>'required|date']);
        $tracker = GpsTracker::where('provider',$data['provider'])->where('external_identifier',$data['external_identifier'])->firstOrFail();
        abort_if($tracker->status !== 'active', 403);

        $isPremium = $tracker->owner->hasPremiumAccess();
        $freeIntervalHours = (int) config('services.trackers.free_location_interval_hours', 6);
        if (!$isPremium && $tracker->last_position_at?->gt(now()->subHours($freeIntervalHours))) {
            return response()->json(['status' => 'accepted_limited'], 202);
        }

        $location = TrackerLocation::create($data + ['tracker_id'=>$tracker->id, 'received_at'=>now(), 'source'=>$data['provider']]);
        $tracker->update(['last_position_at'=>$location->captured_at_device,'last_seen_at'=>now(),'battery_level'=>$location->battery_level,'status'=>'active']);
        if ($isPremium) $geofencing->process($location);
        return response()->json(['status'=>'ok','location_id'=>$location->id], 201);
    }
}
