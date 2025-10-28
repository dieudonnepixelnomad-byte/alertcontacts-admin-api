<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuietHoursService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * UC-Q1: Contrôleur API pour la gestion des heures calmes
 */
class QuietHoursController extends Controller
{
    private QuietHoursService $quietHoursService;

    public function __construct(QuietHoursService $quietHoursService)
    {
        $this->quietHoursService = $quietHoursService;
    }

    /**
     * UC-Q3: Obtenir les préférences d'heures calmes de l'utilisateur connecté
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $settings = $this->quietHoursService->getQuietHoursSettings($user);

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des préférences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * UC-Q2: Mettre à jour les préférences d'heures calmes
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'enabled' => 'sometimes|boolean',
                'start_time' => ['sometimes', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
                'end_time' => ['sometimes', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
                'timezone' => 'sometimes|string|timezone'
            ], [
                'start_time.regex' => 'L\'heure de début doit être au format HH:MM',
                'end_time.regex' => 'L\'heure de fin doit être au format HH:MM',
                'timezone.timezone' => 'Le fuseau horaire n\'est pas valide'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $data = $validator->validated();

            $success = $this->quietHoursService->updateQuietHours(
                $user,
                $data['start_time'] ?? null,
                $data['end_time'] ?? null,
                $data['enabled'] ?? null,
                $data['timezone'] ?? null
            );

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la mise à jour des préférences'
                ], 500);
            }

            // Retourner les nouvelles préférences
            $settings = $this->quietHoursService->getQuietHoursSettings($user->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Heures calmes mises à jour avec succès',
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des préférences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * UC-Q4: Obtenir le prochain moment où les notifications seront autorisées
     */
    public function nextAllowedTime(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $nextTime = $this->quietHoursService->getNextAllowedTime($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'next_allowed_time' => $nextTime?->toISOString(),
                    'is_currently_quiet' => $this->quietHoursService->isQuietTime($user),
                    'timezone' => $user->timezone ?? 'UTC'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du prochain moment autorisé',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir la liste des fuseaux horaires courants
     */
    public function timezones(): JsonResponse
    {
        try {
            $timezones = $this->quietHoursService->getCommonTimezones();

            return response()->json([
                'success' => true,
                'data' => $timezones
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des fuseaux horaires',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
