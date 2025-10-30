<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DangerZone;
use App\Models\DangerZoneConfirmation;
use App\Models\DangerZoneReport;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;

class DangerZonesController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/danger-zones",
     *     tags={"Danger Zones"},
     *     summary="Récupérer les zones de danger",
     *     description="Récupère les zones de danger actives dans un rayon donné avec filtres optionnels",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="lat",
     *         in="query",
     *         description="Latitude du centre de recherche",
     *         @OA\Schema(type="number", format="float", minimum=-90, maximum=90)
     *     ),
     *     @OA\Parameter(
     *         name="lng",
     *         in="query",
     *         description="Longitude du centre de recherche",
     *         @OA\Schema(type="number", format="float", minimum=-180, maximum=180)
     *     ),
     *     @OA\Parameter(
     *         name="radius_km",
     *         in="query",
     *         description="Rayon de recherche en kilomètres",
     *         @OA\Schema(type="number", format="float", minimum=0.1, maximum=50)
     *     ),
     *     @OA\Parameter(
     *         name="min_severity",
     *         in="query",
     *         description="Sévérité minimale",
     *         @OA\Schema(type="string", enum={"low", "med", "high"})
     *     ),
     *     @OA\Parameter(
     *         name="max_age_days",
     *         in="query",
     *         description="Âge maximum en jours",
     *         @OA\Schema(type="integer", minimum=1, maximum=365)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des zones de danger récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/DangerZone")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     * 
     * Récupérer les zones de danger dans un rayon donné
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'lat' => 'nullable|numeric|between:-90,90',
                'lng' => 'nullable|numeric|between:-180,180',
                'radius_km' => 'nullable|numeric|min:0.1|max:50',
                'min_severity' => 'nullable|in:low,med,high',
                'max_age_days' => 'nullable|integer|min:1|max:365',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Paramètres de requête invalides.',
                        'details' => $validator->errors()
                    ]
                ], 422);
            }

            $query = DangerZone::active();

            // Filtrage géographique
            if ($request->has(['lat', 'lng', 'radius_km'])) {
                $query->withinRadius(
                    $request->lat,
                    $request->lng,
                    $request->radius_km ?? 10.0
                );
            }

            // Filtrage par gravité minimale
            if ($request->has('min_severity')) {
                $query->minSeverity($request->min_severity);
            }

            // Filtrage par âge maximum
            $maxAgeDays = $request->max_age_days ?? 30;
            $query->recent($maxAgeDays);

            $dangerZones = $query->with('reporter:id,name')
                ->orderBy('last_report_at', 'desc')
                ->get()
                ->map(function ($zone) {
                    return [
                        'id' => $zone->id,
                        'title' => $zone->title,
                        'description' => $zone->description,
                        'center' => [
                            'lat' => $zone->center->latitude,
                            'lng' => $zone->center->longitude,
                        ],
                        'radius_meters' => $zone->radius_m,
                        'severity' => $zone->severity,
                        'danger_type' => $zone->danger_type,
                        'confirmations' => $zone->confirmations,
                        'last_report_at' => $zone->last_report_at->toISOString(),
                        'created_at' => $zone->created_at->toISOString(),
                        'updated_at' => $zone->updated_at->toISOString(),
                        'reported_by' => $zone->reported_by,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $dangerZones
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONES_FETCH_ERROR',
                    'message' => 'Erreur lors de la récupération des zones de danger.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/danger-zones",
     *     tags={"Danger Zones"},
     *     summary="Créer une zone de danger",
     *     description="Crée une nouvelle zone de danger avec titre, description, localisation et type de danger",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "center", "radius_m", "severity", "danger_type"},
     *             @OA\Property(property="title", type="string", maxLength=255, example="Agression signalée", description="Titre de la zone de danger"),
     *             @OA\Property(property="description", type="string", maxLength=1000, nullable=true, example="Agression signalée hier soir vers 22h", description="Description détaillée"),
     *             @OA\Property(
     *                 property="center",
     *                 type="object",
     *                 description="Centre de la zone de danger",
     *                 @OA\Property(property="lat", type="number", format="float", example=48.8566),
     *                 @OA\Property(property="lng", type="number", format="float", example=2.3522)
     *             ),
     *             @OA\Property(property="radius_m", type="integer", minimum=10, maximum=1000, example=50, description="Rayon en mètres"),
     *             @OA\Property(property="severity", type="string", enum={"low", "med", "high"}, example="high", description="Niveau de sévérité"),
     *             @OA\Property(
     *                 property="danger_type", 
     *                 type="string", 
     *                 enum={"agression", "vol", "braquage", "harcelement", "zone_non_eclairee", "zone_marecageuse", "accident_frequent", "deal_drogue", "vandalisme", "zone_deserte", "construction_dangereuse", "animaux_errants", "manifestation", "inondation", "autre"}, 
     *                 example="agression", 
     *                 description="Type de danger"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Zone de danger créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/DangerZone")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     * 
     * Créer une nouvelle zone de danger
     */
    public function store(Request $request): JsonResponse
    {

        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'center.lat' => 'required|numeric|between:-90,90',
                'center.lng' => 'required|numeric|between:-180,180',
                'radius_m' => 'required|numeric|min:10|max:1000',
                'severity' => 'required|in:low,med,high',
                'danger_type' => 'required|in:agression,vol,braquage,harcelement,zone_non_eclairee,zone_marecageuse,accident_frequent,deal_drogue,vandalisme,zone_deserte,construction_dangereuse,animaux_errants,manifestation,inondation,autre',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Données de zone invalides.',
                        'details' => $validator->errors()
                    ]
                ], 422);
            }

            DB::beginTransaction();

            $user = Auth::user();
            $data = $validator->validated();

            $dangerZone = DangerZone::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'center' => new Point($data['center']['lat'], $data['center']['lng'], 4326),
                'radius_m' => $data['radius_m'],
                'severity' => $data['severity'],
                'danger_type' => $data['danger_type'],
                'confirmations' => 1,
                'last_report_at' => now(),
                'reported_by' => $user->id,
                'is_active' => true,
            ]);

            // Créer la première confirmation automatiquement
            DangerZoneConfirmation::create([
                'danger_zone_id' => $dangerZone->id,
                'user_id' => $user->id,
                'confirmed_at' => now(),
            ]);

            // Enregistrer l'activité de création de zone de danger
            $this->activityLogService->logCreateDangerZone($user->id, $dangerZone->id, [
                'name' => $dangerZone->title,
                'type' => $dangerZone->danger_type,
                'severity' => $dangerZone->severity,
                'latitude' => $dangerZone->center->latitude,
                'longitude' => $dangerZone->center->longitude
            ], $request);

            DB::commit();

            $response = [
                'id' => $dangerZone->id,
                'title' => $dangerZone->title,
                'description' => $dangerZone->description,
                'center' => [
                    'lat' => $dangerZone->center->latitude,
                    'lng' => $dangerZone->center->longitude,
                ],
                'radius_meters' => $dangerZone->radius_m,
                'severity' => $dangerZone->severity,
                'danger_type' => $dangerZone->danger_type,
                'confirmations' => $dangerZone->confirmations,
                'last_report_at' => $dangerZone->last_report_at->toISOString(),
                'created_at' => $dangerZone->created_at->toISOString(),
                'updated_at' => $dangerZone->updated_at->toISOString(),
                'reported_by' => $dangerZone->reported_by,
            ];

            return response()->json([
                'success' => true,
                'data' => $response
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('DangerZonesController.store: Exception caught', [
                'user_id' => Auth::id(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONE_CREATION_ERROR',
                    'message' => 'Erreur lors de la création de la zone de danger.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * Récupérer les détails d'une zone de danger spécifique
     */
    public function show(DangerZone $dangerZone): JsonResponse
    {
        try {
            // Vérifier que la zone est active
            if (!$dangerZone->is_active) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ZONE_NOT_FOUND',
                        'message' => 'Zone de danger introuvable ou inactive.'
                    ]
                ], 404);
            }

            $response = [
                'id' => $dangerZone->id,
                'title' => $dangerZone->title,
                'description' => $dangerZone->description,
                'center' => [
                    'lat' => $dangerZone->center->latitude,
                    'lng' => $dangerZone->center->longitude,
                ],
                'radius_meters' => $dangerZone->radius_m,
                'severity' => $dangerZone->severity,
                'danger_type' => $dangerZone->danger_type,
                'confirmations' => $dangerZone->confirmations,
                'last_report_at' => $dangerZone->last_report_at->toISOString(),
                'created_at' => $dangerZone->created_at->toISOString(),
                'updated_at' => $dangerZone->updated_at->toISOString(),
                'reported_by' => $dangerZone->reported_by,
            ];

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONE_FETCH_ERROR',
                    'message' => 'Erreur lors de la récupération de la zone de danger.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/danger-zones/{id}",
     *     tags={"Danger Zones"},
     *     summary="Mettre à jour une zone de danger",
     *     description="Met à jour une zone de danger existante (seul le créateur peut modifier)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la zone de danger",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255, example="Agression signalée - Mise à jour", description="Titre de la zone de danger"),
     *             @OA\Property(property="description", type="string", maxLength=1000, nullable=true, example="Agression confirmée par plusieurs témoins", description="Description détaillée"),
     *             @OA\Property(
     *                 property="center",
     *                 type="object",
     *                 description="Centre de la zone de danger",
     *                 @OA\Property(property="lat", type="number", format="float", example=48.8566),
     *                 @OA\Property(property="lng", type="number", format="float", example=2.3522)
     *             ),
     *             @OA\Property(property="radius_m", type="integer", minimum=10, maximum=1000, example=75, description="Rayon en mètres"),
     *             @OA\Property(property="severity", type="string", enum={"low", "med", "high"}, example="high", description="Niveau de sévérité"),
     *             @OA\Property(
     *                 property="danger_type", 
     *                 type="string", 
     *                 enum={"agression", "vol", "braquage", "harcelement", "zone_non_eclairee", "zone_marecageuse", "accident_frequent", "deal_drogue", "vandalisme", "zone_deserte", "construction_dangereuse", "animaux_errants", "manifestation", "inondation", "autre"}, 
     *                 example="agression", 
     *                 description="Type de danger"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Zone de danger mise à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/DangerZone")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Seul le créateur peut modifier",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Zone de danger non trouvée",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     * 
     * Mettre à jour une zone de danger
     */
    public function update(Request $request, DangerZone $dangerZone): JsonResponse
    {
        try {
            $user = Auth::user();

            // Vérifier que l'utilisateur est le créateur de la zone
            if ($dangerZone->reported_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Vous ne pouvez modifier que vos propres zones de danger.'
                    ]
                ], 403);
            }

            // Validation des données
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'center.lat' => 'required|numeric|between:-90,90',
                'center.lng' => 'required|numeric|between:-180,180',
                'radius_m' => 'required|integer|min:10|max:5000',
                'severity' => 'required|in:low,medium,high,critical',
                'danger_type' => 'required|in:agression,vol,braquage,harcelement,zone_non_eclairee,zone_marecageuse,accident_frequent,deal_drogue,vandalisme,zone_deserte,construction_dangereuse,animaux_errants,manifestation,inondation,autre',
            ]);

            DB::beginTransaction();

            // Mettre à jour la zone de danger
            $dangerZone->update([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'center' => new Point($validated['center']['lat'], $validated['center']['lng']),
                'radius_m' => $validated['radius_m'],
                'severity' => $validated['severity'],
                'danger_type' => $validated['danger_type'],
            ]);

            DB::commit();

            $response = [
                'id' => $dangerZone->id,
                'title' => $dangerZone->title,
                'description' => $dangerZone->description,
                'center' => [
                    'lat' => $dangerZone->center->latitude,
                    'lng' => $dangerZone->center->longitude,
                ],
                'radius_meters' => $dangerZone->radius_m,
                'severity' => $dangerZone->severity,
                'danger_type' => $dangerZone->danger_type,
                'confirmations' => $dangerZone->confirmations,
                'last_report_at' => $dangerZone->last_report_at->toISOString(),
                'created_at' => $dangerZone->created_at->toISOString(),
                'updated_at' => $dangerZone->updated_at->toISOString(),
                'reported_by' => $dangerZone->reported_by,
            ];

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
                    'message' => 'Erreur lors de la mise à jour de la zone de danger.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/danger-zones/{id}",
     *     tags={"Danger Zones"},
     *     summary="Supprimer une zone de danger",
     *     description="Supprime une zone de danger existante (seul le créateur peut supprimer)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la zone de danger",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Zone de danger supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Zone de danger supprimée avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Seul le créateur peut supprimer",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Zone de danger non trouvée",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     * 
     * Supprimer une zone de danger
     */
    public function destroy(DangerZone $dangerZone): JsonResponse
    {
        try {
            $user = Auth::user();

            // Vérifier que l'utilisateur est le créateur de la zone
            if ($dangerZone->reported_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Vous ne pouvez supprimer que vos propres zones de danger.'
                    ]
                ], 403);
            }

            DB::beginTransaction();

            // Supprimer les confirmations et signalements associés
            $dangerZone->confirmations()->delete();
            $dangerZone->reports()->delete();

            // Enregistrer l'activité de suppression avant de supprimer la zone
            $this->activityLogService->logZone(
                $user->id,
                'delete_danger_zone',
                'DangerZone',
                $dangerZone->id,
                [
                    'title' => $dangerZone->title,
                    'severity' => $dangerZone->severity,
                    'latitude' => $dangerZone->center->latitude,
                    'longitude' => $dangerZone->center->longitude
                ]
            );

            // Supprimer la zone de danger
            $dangerZone->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Zone de danger supprimée avec succès.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONE_DELETE_ERROR',
                    'message' => 'Erreur lors de la suppression de la zone de danger.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/danger-zones/{id}/confirm",
     *     tags={"Danger Zones"},
     *     summary="Confirmer une zone de danger",
     *     description="Confirme l'existence d'une zone de danger (un utilisateur ne peut confirmer qu'une seule fois)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la zone de danger",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Zone de danger confirmée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/DangerZone"),
     *             @OA\Property(property="message", type="string", example="Zone de danger confirmée avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Déjà confirmé par cet utilisateur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Vous avez déjà confirmé cette zone de danger")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Zone de danger non trouvée",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     * 
     * Confirmer une zone de danger
     */
    public function confirm(DangerZone $dangerZone): JsonResponse
    {
        try {
            $user = Auth::user();

            // Vérifier que l'utilisateur n'a pas déjà confirmé cette zone
            $existingConfirmation = DangerZoneConfirmation::where('danger_zone_id', $dangerZone->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($existingConfirmation) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ALREADY_CONFIRMED',
                        'message' => 'Vous avez déjà confirmé cette zone de danger.',
                    ]
                ], 409);
            }

            DB::beginTransaction();

            // Créer la confirmation
            DangerZoneConfirmation::create([
                'danger_zone_id' => $dangerZone->id,
                'user_id' => $user->id,
                'confirmed_at' => now(),
            ]);

            // Incrémenter le compteur de confirmations
            $dangerZone->increment('confirmations');
            $dangerZone->update(['last_report_at' => now()]);

            DB::commit();

            $response = [
                'id' => $dangerZone->id,
                'title' => $dangerZone->title,
                'description' => $dangerZone->description,
                'center' => [
                    'lat' => $dangerZone->center->latitude,
                    'lng' => $dangerZone->center->longitude,
                ],
                'radius_meters' => $dangerZone->radius_m,
                'severity' => $dangerZone->severity,
                'confirmations' => $dangerZone->confirmations,
                'last_report_at' => $dangerZone->last_report_at->toISOString(),
                'created_at' => $dangerZone->created_at->toISOString(),
                'updated_at' => $dangerZone->updated_at->toISOString(),
                'reported_by' => $dangerZone->reported_by,
            ];

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CONFIRMATION_ERROR',
                    'message' => 'Erreur lors de la confirmation de la zone.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * Signaler un abus sur une zone de danger
     */
    public function reportAbuse(Request $request, DangerZone $dangerZone): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Raison du signalement requise.',
                        'details' => $validator->errors()
                    ]
                ], 422);
            }

            $user = Auth::user();

            // Vérifier que l'utilisateur n'a pas déjà signalé cette zone
            $existingReport = DangerZoneReport::where('danger_zone_id', $dangerZone->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($existingReport) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ALREADY_REPORTED',
                        'message' => 'Vous avez déjà signalé cette zone.',
                    ]
                ], 409);
            }

            // Créer le signalement d'abus
            DangerZoneReport::create([
                'danger_zone_id' => $dangerZone->id,
                'user_id' => $user->id,
                'reason' => $request->reason,
                'reported_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Signalement d\'abus enregistré avec succès.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REPORT_ERROR',
                    'message' => 'Erreur lors du signalement d\'abus.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * Vérifier les doublons de zones de danger
     */
    public function checkForDuplicates(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'required|numeric|min:10|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Paramètres de recherche invalides.',
                        'details' => $validator->errors()
                    ]
                ], 422);
            }

            $data = $validator->validated();
            $latitude = $data['latitude'];
            $longitude = $data['longitude'];
            $radius = $data['radius'];

            // Rechercher les zones de danger dans un rayon donné
            $duplicates = DangerZone::select([
                'id',
                'title',
                'description',
                'severity',
                'confirmations',
                'last_report_at',
                DB::raw("ST_DISTANCE_SPHERE(center, ST_GeomFromText('POINT({$longitude} {$latitude})', 4326, 'axis-order=long-lat')) as distance")
            ])
            ->where('is_active', true)
            ->whereRaw("ST_DISTANCE_SPHERE(center, ST_GeomFromText('POINT({$longitude} {$latitude})', 4326, 'axis-order=long-lat')) <= ?", [$radius])
            ->where('last_report_at', '>=', now()->subDays(30))
            ->orderBy('last_report_at', 'desc')
            ->get()
            ->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'title' => $zone->title,
                    'description' => $zone->description,
                    'severity' => $zone->severity,
                    'confirmations' => $zone->confirmations,
                    'distance' => round($zone->distance, 2),
                    'last_report_at' => $zone->last_report_at->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'duplicates' => $duplicates,
                    'count' => $duplicates->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONES_FETCH_ERROR',
                    'message' => 'Erreur lors de la récupération des zones de danger.',
                    'details' => config('app.debug') ? $e->getMessage() : $e->getMessage()
                ]
            ], 500);
        }
    }
}