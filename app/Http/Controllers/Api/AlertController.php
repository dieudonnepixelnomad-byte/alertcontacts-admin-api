<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DangerZone;
use App\Models\DangerZoneConfirmation;
use App\Models\DangerZoneReport;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AlertController extends Controller
{
    private const GRAVITY_CONFIG = [
        'low'    => ['duration_minutes' => 30,  'radius_meters' => 200],
        'medium' => ['duration_minutes' => 60,  'radius_meters' => 500],
        'high'   => ['duration_minutes' => 120, 'radius_meters' => 1000],
    ];

    public function nearby(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        if ($v->fails()) {
            Log::warning('[AlertController.nearby] validation échouée', ['errors' => $v->errors()->toArray()]);
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $userId = Auth::id();

        Log::info('[AlertController.nearby] requête reçue', [
            'user_id' => $userId,
            'lat'     => $lat,
            'lng'     => $lng,
        ]);

        $contactIds = DB::table('relationships')
            ->where('user_id', $userId)
            ->where('status', 'accepted')
            ->pluck('contact_id')
            ->toArray();

        Log::debug('[AlertController.nearby] contacts acceptés', ['count' => count($contactIds)]);

        // Filtre d'expiry basé sur la gravity — low=30min, medium=1h, high=2h
        $alerts = DangerZone::active()
            ->whereRaw("
                last_report_at >= DATE_SUB(NOW(), INTERVAL
                    CASE severity
                        WHEN 'low'    THEN 30
                        WHEN 'medium' THEN 60
                        WHEN 'high'   THEN 120
                        ELSE 60
                    END MINUTE)
            ")
            ->where(function ($q) use ($userId, $contactIds) {
                $q->where('visibility', 'public')
                  ->orWhere(function ($q2) use ($userId, $contactIds) {
                      $q2->where('visibility', 'contacts_only')
                         ->whereIn('reported_by', array_merge([$userId], $contactIds));
                  });
            })
            ->whereRaw("
                (6371000 * acos(LEAST(1.0,
                    cos(radians(?)) * cos(radians(center_lat)) *
                    cos(radians(center_lng) - radians(?)) +
                    sin(radians(?)) * sin(radians(center_lat))
                ))) <= radius_m
            ", [$lat, $lng, $lat])
            ->get();

        Log::info('[AlertController.nearby] résultat', [
            'user_id'        => $userId,
            'lat'            => $lat,
            'lng'            => $lng,
            'alerts_count'   => $alerts->count(),
            'alert_ids'      => $alerts->pluck('id')->toArray(),
            'severities'     => $alerts->pluck('severity')->toArray(),
            'notify_radius'  => 'radius_m * 2 (approach buffer)',
        ]);

        if ($alerts->isEmpty()) {
            Log::debug('[AlertController.nearby] aucune alerte — vérifier: is_active=1, last_report_at récent, position dans radius_m');
        }

        $formatted = $alerts->map(fn($a) => $this->formatAlert($a));

        return response()->json(['status' => 'ok', 'data' => $formatted]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'gravity'      => 'required|in:low,medium,high',
            'type'         => 'required|in:accident,suspect,fire,aggression,suspicious_package,other',
            'lat'          => 'required|numeric|between:-90,90',
            'lng'          => 'required|numeric|between:-180,180',
            'description'  => 'nullable|string|max:200',
            'visibility'   => 'nullable|in:public,contacts_only',
            'is_anonymous' => 'nullable|boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();
        $config = self::GRAVITY_CONFIG[$data['gravity']];

        $alert = DangerZone::create([
            'title'         => $this->gravityTitle($data['gravity'], $data['type']),
            'description'   => $data['description'] ?? null,
            'center_lat'    => $data['lat'],
            'center_lng'    => $data['lng'],
            'radius_m'      => $config['radius_meters'],
            'severity'      => $data['gravity'],
            'danger_type'   => $data['type'],
            'reported_by'   => ($data['is_anonymous'] ?? true) ? null : Auth::id(),
            'is_active'     => true,
            'visibility'    => $data['visibility'] ?? 'public',
            'is_anonymous'  => $data['is_anonymous'] ?? true,
            'confirmations' => 0,
            'last_report_at' => now(),
        ]);

        // Notifier en asynchrone les utilisateurs à proximité via FCM
        dispatch(function () use ($alert) {
            (new FirebaseNotificationService())->sendCommunityAlert($alert);
        })->onQueue('alerts');

        return response()->json(['status' => 'ok', 'data' => $this->formatAlert($alert)], 201);
    }

    public function confirm(Request $request, DangerZone $alert): JsonResponse
    {
        if (!$alert->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Alerte inactive'], 404);
        }

        DangerZoneConfirmation::firstOrCreate(
            ['danger_zone_id' => $alert->id, 'user_id' => Auth::id()],
            ['confirmed_at' => now()]
        );

        $alert->increment('confirmations');

        return response()->json(['status' => 'ok', 'confirmations' => $alert->fresh()->confirmations]);
    }

    public function deny(DangerZone $alert): JsonResponse
    {
        DangerZoneConfirmation::where('danger_zone_id', $alert->id)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    public function report(Request $request, DangerZone $alert): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:200',
        ]);

        DangerZoneReport::firstOrCreate(
            ['danger_zone_id' => $alert->id, 'user_id' => Auth::id()],
            ['reason' => $v->validated()['reason'] ?? null]
        );

        return response()->json(['status' => 'ok']);
    }

    private function formatAlert(DangerZone $alert): array
    {
        $durationMinutes = self::GRAVITY_CONFIG[$alert->severity]['duration_minutes'] ?? 60;
        $createdAt = $alert->created_at ?? $alert->last_report_at ?? now();
        $expiresAt = $createdAt->copy()->addMinutes($durationMinutes);

        return [
            'id'            => $alert->id,
            'type'          => $alert->danger_type,
            'gravity'       => $alert->severity,
            'lat'           => (float) $alert->center_lat,
            'lng'           => (float) $alert->center_lng,
            'radius'        => (int) $alert->radius_m,
            'description'   => $alert->description,
            'confirmations' => (int) $alert->confirmations,
            'denials'       => 0,
            'visibility'    => $alert->visibility ?? 'public',
            'is_anonymous'  => (bool) ($alert->is_anonymous ?? true),
            'creator_name'  => $alert->is_anonymous ? null : $alert->reporter?->display_name,
            'created_at'    => $createdAt->toISOString(),
            'expires_at'    => $expiresAt->toISOString(),
            'is_active'     => (bool) $alert->is_active,
        ];
    }

    private function gravityTitle(string $gravity, string $type): string
    {
        $types = [
            'accident'           => 'Accident',
            'suspect'            => 'Personne suspecte',
            'fire'               => 'Incendie',
            'aggression'         => 'Agression',
            'suspicious_package' => 'Colis suspect',
            'other'              => 'Incident',
        ];
        return ($types[$type] ?? 'Incident') . ' signalé';
    }
}
