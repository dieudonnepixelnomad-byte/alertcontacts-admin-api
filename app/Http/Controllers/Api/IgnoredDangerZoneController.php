<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IgnoredDangerZoneService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Contrôleur API pour gérer les zones de danger ignorées
 */
class IgnoredDangerZoneController extends Controller
{
    public function __construct(
        private IgnoredDangerZoneService $ignoredDangerZoneService
    ) {}

    /**
     * Ignorer une zone de danger
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function ignore(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'danger_zone_id' => 'required|integer|exists:danger_zones,id',
                'reason' => 'nullable|string|max:255'
            ]);

            $userId = Auth::id();
            $dangerZoneId = $validated['danger_zone_id'];
            $reason = $validated['reason'] ?? null;

            // Vérifier si la zone n'est pas déjà ignorée
            if ($this->ignoredDangerZoneService->isZoneIgnored($userId, $dangerZoneId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette zone est déjà ignorée'
                ], 409);
            }

            $ignoredZone = $this->ignoredDangerZoneService->ignoreDangerZone($userId, $dangerZoneId, $reason);

            Log::info('User ignored danger zone', [
                'user_id' => $userId,
                'danger_zone_id' => $dangerZoneId,
                'reason' => $reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Zone ignorée avec succès',
                'data' => [
                    'id' => $ignoredZone->id,
                    'danger_zone_id' => $ignoredZone->danger_zone_id,
                    'ignored_at' => $ignoredZone->ignored_at,
                    'expires_at' => $ignoredZone->expires_at,
                    'reason' => $ignoredZone->reason
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to ignore danger zone', [
                'user_id' => Auth::id(),
                'danger_zone_id' => $request->get('danger_zone_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ignorage de la zone'
            ], 500);
        }
    }

    /**
     * Réactiver les alertes pour une zone de danger
     * 
     * @param int $dangerZoneId
     * @return JsonResponse
     */
    public function reactivate(int $dangerZoneId): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$this->ignoredDangerZoneService->isZoneIgnored($userId, $dangerZoneId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette zone n\'est pas ignorée'
                ], 404);
            }

            $this->ignoredDangerZoneService->reactivateDangerZone($userId, $dangerZoneId);

            Log::info('User reactivated danger zone alerts', [
                'user_id' => $userId,
                'danger_zone_id' => $dangerZoneId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Alertes réactivées avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reactivate danger zone alerts', [
                'user_id' => Auth::id(),
                'danger_zone_id' => $dangerZoneId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réactivation des alertes'
            ], 500);
        }
    }

    /**
     * Lister les zones ignorées par l'utilisateur
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $ignoredZones = $this->ignoredDangerZoneService->getUserIgnoredZones($userId);

            return response()->json([
                'success' => true,
                'data' => $ignoredZones->map(function ($ignoredZone) {
                    return [
                        'id' => $ignoredZone->id,
                        'danger_zone_id' => $ignoredZone->danger_zone_id,
                        'danger_zone' => [
                            'id' => $ignoredZone->dangerZone->id,
                            'name' => $ignoredZone->dangerZone->name,
                            'type' => $ignoredZone->dangerZone->type,
                            'severity' => $ignoredZone->dangerZone->severity,
                            'latitude' => $ignoredZone->dangerZone->latitude,
                            'longitude' => $ignoredZone->dangerZone->longitude,
                            'radius' => $ignoredZone->dangerZone->radius
                        ],
                        'ignored_at' => $ignoredZone->ignored_at,
                        'expires_at' => $ignoredZone->expires_at,
                        'reason' => $ignoredZone->reason,
                        'is_active' => $ignoredZone->isActive(),
                        'is_expired' => $ignoredZone->isExpired()
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch ignored danger zones', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des zones ignorées'
            ], 500);
        }
    }

    /**
     * Prolonger l'expiration d'une zone ignorée
     * 
     * @param int $dangerZoneId
     * @return JsonResponse
     */
    public function extend(int $dangerZoneId): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$this->ignoredDangerZoneService->isZoneIgnored($userId, $dangerZoneId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette zone n\'est pas ignorée'
                ], 404);
            }

            $success = $this->ignoredDangerZoneService->extendIgnoredZone($userId, $dangerZoneId);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de prolonger cette zone'
                ], 404);
            }

            Log::info('User extended ignored danger zone', [
                'user_id' => $userId,
                'danger_zone_id' => $dangerZoneId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Durée d\'ignorage prolongée avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to extend ignored danger zone', [
                'user_id' => Auth::id(),
                'danger_zone_id' => $dangerZoneId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la prolongation'
            ], 500);
        }
    }

    /**
     * Vérifier si une zone est ignorée
     * 
     * @param int $dangerZoneId
     * @return JsonResponse
     */
    public function check(int $dangerZoneId): JsonResponse
    {
        try {
            $userId = Auth::id();
            $isIgnored = $this->ignoredDangerZoneService->isZoneIgnored($userId, $dangerZoneId);

            return response()->json([
                'success' => true,
                'data' => [
                    'danger_zone_id' => $dangerZoneId,
                    'is_ignored' => $isIgnored
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check ignored danger zone status', [
                'user_id' => Auth::id(),
                'danger_zone_id' => $dangerZoneId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification'
            ], 500);
        }
    }
}