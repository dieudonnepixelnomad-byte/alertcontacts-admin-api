<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SafeZone;
use App\Models\SafeZoneAssignment;
use App\Models\UserLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ZoneController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $ownedZones = SafeZone::where('owner_id', $userId)
            ->where('is_active', true)
            ->with('contacts')
            ->get()
            ->map(fn($z) => $this->formatZone($z));

        // Zones créées par d'autres utilisateurs où cet utilisateur est assigné
        $assignedZones = SafeZone::whereHas('assignments', fn($q) =>
                $q->where('assigned_user_id', $userId)->where('is_active', true)
            )
            ->where('is_active', true)
            ->where('owner_id', '!=', $userId)
            ->with('contacts')
            ->get()
            ->map(fn($z) => $this->formatZone($z));

        return response()->json([
            'status' => 'ok',
            'data'   => $ownedZones->merge($assignedZones)->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name'        => 'required|string|max:30',
            'lat'         => 'required|numeric|between:-90,90',
            'lng'         => 'required|numeric|between:-180,180',
            'radius'      => 'required|integer|between:50,500',
            'icon'        => 'nullable|in:home,school,work,sport,shopping,other',
            'color'       => 'nullable|string|max:20',
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'integer|exists:users,id',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $zone = SafeZone::create([
            'owner_id' => Auth::id(),
            'name'     => $v->validated()['name'],
            'icon'     => $v->validated()['icon'] ?? 'other',
            'center'   => new Point($v->validated()['lat'], $v->validated()['lng']),
            'radius_m' => $v->validated()['radius'],
            'is_active' => true,
        ]);

        if (!empty($v->validated()['contact_ids'])) {
            foreach ($v->validated()['contact_ids'] as $contactId) {
                SafeZoneAssignment::create([
                    'safe_zone_id'        => $zone->id,
                    'assigned_user_id'    => $contactId,
                    'assigned_by_user_id' => Auth::id(),
                    'is_active'           => true,
                    'notify_entry'        => true,
                    'notify_exit'         => true,
                    'assigned_at'         => now(),
                ]);
            }
        }

        return response()->json(['status' => 'ok', 'data' => $this->formatZone($zone->fresh('contacts'))], 201);
    }

    public function update(Request $request, SafeZone $zone): JsonResponse
    {
        if ($zone->owner_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Accès refusé'], 403);
        }

        $v = Validator::make($request->all(), [
            'name'   => 'sometimes|string|max:30',
            'lat'    => 'sometimes|numeric|between:-90,90',
            'lng'    => 'sometimes|numeric|between:-180,180',
            'radius' => 'sometimes|integer|between:50,500',
            'icon'   => 'sometimes|in:home,school,work,sport,shopping,other',
            'color'  => 'sometimes|string|max:20',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();
        if (isset($data['lat']) || isset($data['lng'])) {
            $lat = $data['lat'] ?? $zone->center?->latitude;
            $lng = $data['lng'] ?? $zone->center?->longitude;
            $data['center'] = new Point($lat, $lng);
            unset($data['lat'], $data['lng']);
        }
        if (isset($data['radius'])) {
            $data['radius_m'] = $data['radius'];
            unset($data['radius']);
        }

        $zone->update($data);

        return response()->json(['status' => 'ok', 'data' => $this->formatZone($zone->fresh('contacts'))]);
    }

    public function destroy(SafeZone $zone): JsonResponse
    {
        if ($zone->owner_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Accès refusé'], 403);
        }

        $zone->update(['is_active' => false]);

        return response()->json(['status' => 'ok'], 204);
    }

    public function status(SafeZone $zone): JsonResponse
    {
        if ($zone->owner_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Accès refusé'], 403);
        }

        $assignments = SafeZoneAssignment::where('safe_zone_id', $zone->id)
            ->where('is_active', true)
            ->with('assignedUser')
            ->get();

        $statuses = $assignments->map(function ($assignment) use ($zone) {
            $contact = $assignment->assignedUser;
            $lastLocation = UserLocation::where('user_id', $contact->id)
                ->orderByDesc('created_at')
                ->first();

            $isInside = false;
            if ($lastLocation && $zone->isCircle()) {
                $isInside = $this->isInsideCircle(
                    $lastLocation->latitude,
                    $lastLocation->longitude,
                    $zone->center->latitude,
                    $zone->center->longitude,
                    $zone->radius_m
                );
            }

            return [
                'contact_id'   => $contact->id,
                'contact_name' => $contact->display_name ?? $contact->name,
                'is_inside'    => $isInside,
                'last_seen_at' => $lastLocation?->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'status' => 'ok',
            'zone_id' => $zone->id,
            'data'   => $statuses,
        ]);
    }

    private function formatZone(SafeZone $zone): array
    {
        return [
            'id'             => $zone->id,
            'type'           => 'safe',
            'name'           => $zone->name,
            'description'    => null,
            'icon_key'       => $zone->icon,
            'address'        => null,
            'center'         => [
                'lat' => $zone->center?->latitude,
                'lng' => $zone->center?->longitude,
            ],
            'radius_meters'  => $zone->radius_m,
            'is_active'      => $zone->is_active,
            'member_ids'     => $zone->assignments()
                ->where('is_active', true)
                ->pluck('assigned_user_id')
                ->map(fn($id) => (string) $id)
                ->values()
                ->all(),
            'created_at'     => $zone->created_at->toISOString(),
            'updated_at'     => $zone->updated_at->toISOString(),
        ];
    }

    private function isInsideCircle(float $lat, float $lng, float $centerLat, float $centerLng, float $radiusM): bool
    {
        $R  = 6371000;
        $φ1 = deg2rad($centerLat);
        $φ2 = deg2rad($lat);
        $Δφ = deg2rad($lat - $centerLat);
        $Δλ = deg2rad($lng - $centerLng);
        $a  = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a)) <= $radiusM;
    }
}
