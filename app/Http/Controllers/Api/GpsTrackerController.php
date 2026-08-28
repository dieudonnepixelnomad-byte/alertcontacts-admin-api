<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GpsTracker;
use App\Models\SafeZone;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GpsTrackerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Auth::user()
                ->gpsTrackers()
                ->latest()
                ->get()
                ->map(fn (GpsTracker $tracker) => $this->present($tracker)),
            'capabilities' => $this->capabilities(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name'=>'required|string|max:100','provider'=>'nullable|string|max:50','model'=>'nullable|string|max:100','external_identifier'=>'nullable|string|max:191']);
        $tracker = DB::transaction(function () use ($data) {
            $owner = User::query()->lockForUpdate()->findOrFail(Auth::id());
            if (!$owner->hasPremiumAccess() && $owner->gpsTrackers()->count() >= 1) {
                abort(response()->json([
                    'status' => 'error',
                    'code' => 'GPS_TRACKER_FREE_LIMIT_REACHED',
                    'message' => 'L’offre gratuite est limitée à un traceur GPS.',
                    'upgrade_url' => '/api/subscriptions',
                ], 403));
            }

            return $owner->gpsTrackers()->create($data + ['status' => 'draft']);
        });

        return response()->json(['data' => $this->present($tracker)], 201);
    }

    public function update(Request $request, GpsTracker $tracker): JsonResponse
    {
        $this->owned($tracker);
        $data = $request->validate(['name'=>'sometimes|required|string|max:100','provider'=>'nullable|string|max:50','model'=>'nullable|string|max:100','external_identifier'=>'nullable|string|max:191']);
        $tracker->update($data);
        return response()->json(['data' => $this->present($tracker->fresh())]);
    }

    public function destroy(GpsTracker $tracker): JsonResponse { $this->owned($tracker); $tracker->delete(); return response()->json([], 204); }

    public function activate(GpsTracker $tracker): JsonResponse
    {
        $this->owned($tracker);
        abort_unless(Auth::user()->hasPremiumAccess(), 403, 'Un abonnement Premium est requis pour activer le suivi GPS.');
        if (!$tracker->external_identifier) return response()->json(['message'=>'Un identifiant matériel est requis pour activer ce traceur.'], 422);
        $tracker->update(['status'=>'active']);
        return response()->json(['data'=>$this->present($tracker->fresh())]);
    }

    public function deactivate(GpsTracker $tracker): JsonResponse { $this->owned($tracker); $tracker->update(['status'=>'suspended']); return response()->json(['data'=>$this->present($tracker->fresh())]); }

    public function locations(Request $request, GpsTracker $tracker): JsonResponse
    {
        $this->owned($tracker); $limit = min((int) $request->integer('limit', 50), 200);
        return response()->json(['data'=>$tracker->locations()->latest('captured_at_device')->limit($limit)->get()]);
    }

    public function zones(GpsTracker $tracker): JsonResponse
    {
        $this->owned($tracker);
        return response()->json(['data'=>$tracker->zoneAssignments()->with('safeZone')->get()]);
    }

    public function syncZones(Request $request, GpsTracker $tracker): JsonResponse
    {
        $this->owned($tracker);
        $data = $request->validate(['zones'=>'required|array','zones.*.safe_zone_id'=>'required|integer','zones.*.is_active'=>'boolean','zones.*.notify_entry'=>'boolean','zones.*.notify_exit'=>'boolean']);
        $zoneIds = collect($data['zones'])->pluck('safe_zone_id')->unique();
        if (SafeZone::where('owner_id', Auth::id())->whereIn('id', $zoneIds)->count() !== $zoneIds->count()) abort(403, 'Une zone ne vous appartient pas.');
        DB::transaction(function () use ($tracker, $data, $zoneIds) {
            $tracker->zoneAssignments()->whereNotIn('safe_zone_id', $zoneIds)->delete();
            foreach ($data['zones'] as $zone) $tracker->zoneAssignments()->updateOrCreate(['safe_zone_id'=>$zone['safe_zone_id']], $zone);
        });
        return $this->zones($tracker);
    }

    private function owned(GpsTracker $tracker): void { abort_unless($tracker->owner_id === Auth::id(), 404); }

    private function capabilities(): array
    {
        $isPremium = Auth::user()->hasPremiumAccess();

        return [
            'is_premium' => $isPremium,
            'tracker_limit' => $isPremium ? null : 1,
            'location_interval_hours' => $isPremium ? 0 : null,
            'realtime_location' => $isPremium,
            'location_history' => $isPremium,
            'safe_zones' => $isPremium,
            'zone_alerts' => $isPremium,
        ];
    }

    private function present(GpsTracker $tracker): array
    {
        $last = $tracker->locations()->latest('captured_at_device')->first();

        return [
            'id'=>$tracker->id,
            'name'=>$tracker->name,
            'provider'=>$tracker->provider,
            'model'=>$tracker->model,
            'status'=>$tracker->status,
            'battery_level'=>$tracker->battery_level,
            'last_position_at'=>$tracker->last_position_at?->toISOString(),
            'last_seen_at'=>$tracker->last_seen_at?->toISOString(),
            'last_location'=>$last,
        ];
    }
}
