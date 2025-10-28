<?php
// app/Http/Controllers/Api/SafeZonesController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSafeZoneRequest;
use App\Models\SafeZone;
use App\Models\SafeZoneAssignment;
use App\Models\Relationship;
use App\Services\ActivityLogService;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SafeZonesController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    /**
     * Récupérer toutes les zones de sécurité de l'utilisateur
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            $safeZones = SafeZone::where('owner_id', $user->id)
                ->where('is_active', true)
                ->get()
                ->map(function ($zone) {
                    $data = [
                        'id' => $zone->id,
                        'name' => $zone->name,
                        'icon' => $zone->icon,
                        'is_active' => $zone->is_active,
                        'active_hours' => $zone->active_hours,
                        'created_at' => $zone->created_at->toISOString(),
                        'updated_at' => $zone->updated_at->toISOString(),
                    ];

                    // Ajouter les données géométriques selon le type
                    if ($zone->isCircle()) {
                        $data['center'] = [
                            'lat' => $zone->center->latitude,
                            'lng' => $zone->center->longitude,
                        ];
                        $data['radius_m'] = $zone->radius_m;
                    } else {
                        // Pour les polygones, on peut ajouter la géométrie si nécessaire
                        $data['geom'] = $zone->geom;
                    }

                    return $data;
                });

            return response()->json([
                'success' => true,
                'data' => $safeZones
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONES_FETCH_ERROR',
                    'message' => 'Erreur lors de la récupération des zones de sécurité.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * Créer une nouvelle zone de sécurité
     */
    public function store(StoreSafeZoneRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $data = $request->validated();

            // Préparer les données de base avec valeurs par défaut pour les champs géométriques
            // (requis car les colonnes géométriques sont NOT NULL pour les index spatiaux)
            $defaultPoint = new Point(0, 0, 4326); // Point par défaut (0,0)
            $defaultPolygon = new Polygon([
                new LineString([
                    new Point(0, 0, 4326),
                    new Point(0, 0.001, 4326),
                    new Point(0.001, 0.001, 4326),
                    new Point(0.001, 0, 4326),
                    new Point(0, 0, 4326), // Fermer le ring
                ], 4326)
            ], 4326);

            $zoneData = [
                'owner_id' => $user->id,
                'name' => $data['name'],
                'icon' => $data['icon'] ?? 'home',
                'is_active' => true,
                'active_hours' => $data['active_hours'] ?? null,
                'center' => $defaultPoint,
                'geom' => $defaultPolygon,
            ];

            // Déterminer le type de zone (cercle ou polygone)
            if (isset($data['center']) && isset($data['radius_m'])) {
                // Mode CERCLE
                $zoneData['center'] = new Point(
                    $data['center']['lat'],
                    $data['center']['lng'],
                    4326 // SRID WGS84
                );
                $zoneData['radius_m'] = $data['radius_m'];
            } elseif (isset($data['geom'])) {
                // Mode POLYGONE
                $coordinates = $data['geom']['coordinates'][0];

                // Vérifier et fermer le ring si nécessaire
                $firstPoint = $coordinates[0];
                $lastPoint = end($coordinates);
                if ($firstPoint[0] !== $lastPoint[0] || $firstPoint[1] !== $lastPoint[1]) {
                    $coordinates[] = $firstPoint; // Fermer le ring
                }

                // Créer le LineString (attention: ordre lng, lat pour PostGIS)
                $points = array_map(function ($coord) {
                    return new Point($coord[1], $coord[0], 4326); // lat, lng, SRID
                }, $coordinates);

                $lineString = new LineString($points);
                $zoneData['geom'] = new Polygon([$lineString], 4326);
            }

            // Créer la zone
            $safeZone = SafeZone::create($zoneData);

            // Gérer les assignations de contacts si fournis
            $assignedContacts = [];
            if (!empty($data['contact_ids'])) {
                $assignedContacts = $this->assignContacts($safeZone, $data['contact_ids'], $user->id);
            }

            // Enregistrer l'activité de création de zone de sécurité
            $this->activityLogService->logCreateSafeZone($user->id, $safeZone->id, [
                'name' => $safeZone->name,
                'latitude' => $safeZone->isCircle() ? $safeZone->center->latitude : null,
                'longitude' => $safeZone->isCircle() ? $safeZone->center->longitude : null,
                'radius' => $safeZone->radius_m,
                'icon' => $safeZone->icon
            ], $request);

            DB::commit();

            // Préparer la réponse
            $response = [
                'id' => $safeZone->id,
                'name' => $safeZone->name,
                'icon' => $safeZone->icon,
                'is_circle' => $safeZone->isCircle(),
                'is_polygon' => $safeZone->isPolygon(),
            ];

            if ($safeZone->isCircle()) {
                $response['center'] = [
                    'lat' => $safeZone->center->latitude,
                    'lng' => $safeZone->center->longitude,
                ];
                $response['radius_m'] = $safeZone->radius_m;
            }

            if (!empty($assignedContacts)) {
                $response['assigned_contacts'] = $assignedContacts;
            }

            return response()->json([
                'success' => true,
                'data' => $response
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONE_CREATION_ERROR',
                    'message' => 'Erreur lors de la création de la zone de sécurité.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * Assigner des contacts à une zone de sécurité
     * Filtre par les relations acceptées uniquement
     */
    private function assignContacts(SafeZone $safeZone, array $contactIds, int $ownerId): array
    {
        $assignedContacts = [];

        foreach ($contactIds as $contactId) {
            // Vérifier qu'il existe une relation acceptée entre owner et contact (bidirectionnelle)
            $hasAcceptedRelation = Relationship::between($ownerId, $contactId)
                ->accepted()
                ->exists();

            if ($hasAcceptedRelation) {
                // Créer ou mettre à jour l'assignation avec la nouvelle structure
                $assignment = SafeZoneAssignment::firstOrCreate([
                    'safe_zone_id' => $safeZone->id,
                    'assigned_user_id' => $contactId,
                ], [
                    'assigned_by_user_id' => $ownerId,
                    'is_active' => true,
                    'notify_entry' => true,
                    'notify_exit' => true,
                    'assigned_at' => now(),
                ]);

                if ($assignment->wasRecentlyCreated || !$assignment->is_active) {
                    $assignment->update([
                        'is_active' => true,
                        'assigned_by_user_id' => $ownerId,
                        'assigned_at' => now(),
                    ]);
                    $assignedContacts[] = $contactId;
                }
            }
        }

        return $assignedContacts;
    }

    /**
     * Mettre à jour une zone de sécurité
     */
    public function update(StoreSafeZoneRequest $request, SafeZone $safeZone): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Vérifier que l'utilisateur est propriétaire de la zone
            if ($safeZone->owner_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Vous n\'êtes pas autorisé à modifier cette zone.',
                    ]
                ], 403);
            }

            DB::beginTransaction();

            $data = $request->validated();

            // Préparer les données de mise à jour
            $updateData = [
                'name' => $data['name'],
                'icon' => $data['icon'] ?? $safeZone->icon,
                'active_hours' => $data['active_hours'] ?? $safeZone->active_hours,
            ];

            // Mettre à jour la géométrie si fournie
            if (isset($data['center']) && isset($data['radius_m'])) {
                // Mode CERCLE
                $updateData['center'] = new Point(
                    $data['center']['lat'],
                    $data['center']['lng'],
                    4326
                );
                $updateData['radius_m'] = $data['radius_m'];
            }

            $safeZone->update($updateData);

            DB::commit();

            // Préparer la réponse
            $response = [
                'id' => $safeZone->id,
                'name' => $safeZone->name,
                'icon' => $safeZone->icon,
                'is_circle' => $safeZone->isCircle(),
                'is_polygon' => $safeZone->isPolygon(),
            ];

            if ($safeZone->isCircle()) {
                $response['center'] = [
                    'lat' => $safeZone->center->latitude,
                    'lng' => $safeZone->center->longitude,
                ];
                $response['radius_m'] = $safeZone->radius_m;
            }

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONE_UPDATE_ERROR',
                    'message' => 'Erreur lors de la mise à jour de la zone de sécurité.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * Supprimer une zone de sécurité
     */
    public function destroy(SafeZone $safeZone): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Vérifier que l'utilisateur est propriétaire de la zone
            if ($safeZone->owner_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Vous n\'êtes pas autorisé à supprimer cette zone.',
                    ]
                ], 403);
            }

            DB::beginTransaction();

            // Enregistrer l'activité de suppression avant de supprimer la zone
            $this->activityLogService->logZone(
                $user->id,
                'delete_safe_zone',
                'SafeZone',
                $safeZone->id,
                [
                    'name' => $safeZone->name,
                    'latitude' => $safeZone->isCircle() ? $safeZone->center->latitude : null,
                    'longitude' => $safeZone->isCircle() ? $safeZone->center->longitude : null,
                    'icon' => $safeZone->icon
                ]
            );

            // Supprimer les assignations associées
            SafeZoneAssignment::where('safe_zone_id', $safeZone->id)->delete();
            
            // Supprimer la zone
            $safeZone->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Zone de sécurité supprimée avec succès.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONE_DELETE_ERROR',
                    'message' => 'Erreur lors de la suppression de la zone de sécurité.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }
}
