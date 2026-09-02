<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GpsTracker;
use App\Models\TrackerLocation;
use App\Services\PostHogService;
use App\Services\TrackerGeofencingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InternalTrackerTelemetryController extends Controller
{
    public function store(Request $request, TrackerGeofencingService $geofencing, PostHogService $posthog): JsonResponse
    {
        $secret = (string) config('services.trackers.ingest_secret', '');
        if ($secret === '' || !hash_equals($secret, (string) $request->header('X-Tracker-Ingest-Secret'))) {
            $posthog->capture('server', 'tracker_telemetry_rejected', [
                'reason' => 'invalid_ingest_secret',
            ]);
            abort(403);
        }
        $data = $request->validate(['provider'=>'required|string|max:50','external_identifier'=>'required|string|max:191','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','accuracy'=>'nullable|numeric|min:0','speed'=>'nullable|numeric|min:0','heading'=>'nullable|numeric|between:0,360','battery_level'=>'nullable|integer|between:0,100','captured_at_device'=>'required|date']);
        $tracker = GpsTracker::where('provider',$data['provider'])->where('external_identifier',$data['external_identifier'])->firstOrFail();
        if ($tracker->status !== 'active') {
            $posthog->capture($tracker->owner, 'tracker_telemetry_rejected', [
                'reason' => 'tracker_not_active',
                'tracker_status' => $tracker->status,
            ]);
            abort(403);
        }

        $isPremium = $tracker->owner->hasPremiumAccess();
        if (!$isPremium) {
            $tracker->update(['status' => 'suspended']);
            $posthog->capture($tracker->owner, 'tracker_suspended', [
                'reason' => 'subscription_required',
            ]);
            $posthog->capture($tracker->owner, 'tracker_telemetry_rejected', [
                'reason' => 'premium_required',
                'tracker_status' => 'suspended',
            ]);
            return response()->json(['status' => 'premium_required'], 403);
        }

        $location = TrackerLocation::create($data + ['tracker_id'=>$tracker->id, 'received_at'=>now(), 'source'=>$data['provider']]);
        $tracker->update(['last_position_at'=>$location->captured_at_device,'last_seen_at'=>now(),'battery_level'=>$location->battery_level,'status'=>'active']);
        $posthog->capture($tracker->owner, 'tracker_telemetry_received', [
            'tracker_status' => 'active',
            'has_accuracy' => array_key_exists('accuracy', $data),
            'has_battery_level' => array_key_exists('battery_level', $data),
        ]);
        if ($isPremium) $geofencing->process($location);
        return response()->json(['status'=>'ok','location_id'=>$location->id], 201);
    }
}
