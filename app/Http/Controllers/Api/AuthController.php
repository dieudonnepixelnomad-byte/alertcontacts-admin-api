<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\SafeZone;
use App\Models\DangerZone;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * @OA\Post(
     *     path="/auth/firebase-login",
     *     tags={"Authentication"},
     *     summary="Connexion avec Firebase",
     *     description="Authentifie un utilisateur via Firebase et retourne un token d'accès",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"idToken", "userData"},
     *             @OA\Property(property="idToken", type="string", description="Token ID Firebase"),
     *             @OA\Property(
     *                 property="userData",
     *                 type="object",
     *                 required={"uid", "email"},
     *                 @OA\Property(property="uid", type="string", description="UID Firebase de l'utilisateur"),
     *                 @OA\Property(property="email", type="string", format="email", description="Email de l'utilisateur"),
     *                 @OA\Property(property="name", type="string", description="Nom de l'utilisateur"),
     *                 @OA\Property(property="picture", type="string", description="URL de la photo de profil"),
     *                 @OA\Property(property="phone_number", type="string", description="Numéro de téléphone"),
     *                 @OA\Property(property="email_verified", type="boolean", description="Email vérifié"),
     *                 @OA\Property(property="provider", type="string", description="Fournisseur d'authentification")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", description="Token d'accès"),
     *                 @OA\Property(property="user", ref="#/components/schemas/User")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="VALIDATION_ERROR"),
     *                 @OA\Property(property="message", type="string", example="Invalid request data"),
     *                 @OA\Property(property="details", type="object")
     *             )
     *         )
     *     )
     * )
     * 
     * Login avec Firebase
     */
    public function firebaseLogin(Request $request): JsonResponse
    {
        // Validation des données reçues
        $validator = Validator::make($request->all(), [
            'idToken' => 'required|string',
            'userData' => 'required|array',
            'userData.uid' => 'required|string',
            'userData.email' => 'required|email',
            'userData.name' => 'nullable|string',
            'userData.picture' => 'nullable|string',
            'userData.phone_number' => 'nullable|string',
            'userData.email_verified' => 'nullable|boolean',
            'userData.provider' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        try {
            // Créer ou mettre à jour l'utilisateur à partir des données Firebase
            $user = User::createOrUpdateFromFirebase($request->userData);

            // Créer un token Sanctum pour l'utilisateur
            $token = $user->createToken('auth-token')->plainTextToken;

            // Enregistrer l'activité de connexion
            $this->activityLogService->logLogin($user->id, $request);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'firebase_uid' => $user->firebase_uid,
                        'avatar_url' => $user->avatar_url,
                        'phone_number' => $user->phone_number,
                        'email_verified_at' => $user->email_verified_at,
                    ],
                    'token' => $token,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An error occurred during authentication',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * Login classique
     */
    public function login(Request $request): JsonResponse
    {
        // TODO: Implémenter la logique de login
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'Login not implemented yet'
            ]
        ], 501);
    }

    /**
     * Inscription
     */
    public function register(Request $request): JsonResponse
    {
        // TODO: Implémenter la logique d'inscription
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'Register not implemented yet'
            ]
        ], 501);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     tags={"Authentication"},
     *     summary="Déconnexion",
     *     description="Déconnecte l'utilisateur et révoque le token d'accès",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Déconnexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     )
     * )
     * 
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        // TODO: Implémenter la logique de logout
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        // TODO: Implémenter la logique de refresh
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'Refresh not implemented yet'
            ]
        ], 501);
    }

    /**
     * @OA\Get(
     *     path="/me",
     *     tags={"User Profile"},
     *     summary="Profil utilisateur",
     *     description="Récupère les informations de l'utilisateur connecté",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Informations utilisateur récupérées avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="User not authenticated")
     *             )
     *         )
     *     )
     * )
     * 
     * Obtenir les informations de l'utilisateur connecté
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User not authenticated'
                ]
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * Obtenir toutes les zones de l'utilisateur connecté
     */
    public function getMyZones(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'User not authenticated'
                    ]
                ], 401);
            }

            // Récupérer les zones de sécurité de l'utilisateur
            $safeZones = SafeZone::where('owner_id', $user->id)->get()->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'type' => 'safe',
                    'name' => $zone->name,
                    'description' => $zone->description,
                    'center' => [
                        'lat' => $zone->center->latitude,
                        'lng' => $zone->center->longitude,
                    ],
                    'radius_meters' => $zone->radius_meters,
                    'icon_key' => $zone->icon_key,
                    'address' => $zone->address,
                    'member_ids' => $zone->member_ids ?? [],
                    'created_at' => $zone->created_at->toISOString(),
                    'updated_at' => $zone->updated_at->toISOString(),
                ];
            });

            // Récupérer les zones de danger de l'utilisateur
            $dangerZones = DangerZone::where('reported_by', $user->id)->get()->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'type' => 'danger',
                    'title' => $zone->title,
                    'description' => $zone->description,
                    'center' => [
                        'lat' => $zone->center_lat,
                        'lng' => $zone->center_lng,
                    ],
                    'radius_meters' => $zone->radius_m,
                    'severity' => $zone->severity,
                    'confirmations' => $zone->confirmations,
                    'last_report_at' => $zone->last_report_at->toISOString(),
                    'created_at' => $zone->created_at->toISOString(),
                    'updated_at' => $zone->updated_at->toISOString(),
                ];
            });

            // Combiner les deux collections
            $allZones = $safeZones->concat($dangerZones)->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'zones' => $allZones,
                    'stats' => [
                        'total' => $allZones->count(),
                        'safe_zones' => $safeZones->count(),
                        'danger_zones' => $dangerZones->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONES_FETCH_ERROR',
                    'message' => 'Erreur lors de la récupération des zones.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * Supprimer le compte utilisateur
     */
    public function deleteAccount(Request $request)
    {
        try {
            $user = $request->user();
            
            // Enregistrer l'activité de suppression de compte AVANT la suppression
            app(ActivityLogService::class)->logAuth(
                $user->id,
                UserActivity::ACTION_DELETE_ACCOUNT,
                $request
            );
            
            // Supprimer tous les tokens d'accès de l'utilisateur
            $user->tokens()->delete();
            
            // Supprimer l'utilisateur (cascade supprimera les données associées)
            $user->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Compte supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du compte', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du compte'
            ], 500);
        }
    }
}
