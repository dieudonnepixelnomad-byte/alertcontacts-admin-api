<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PendingSafeZoneAlert;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AlertConfirmationController extends Controller
{
    /**
     * Confirmer qu'une alerte de zone de sécurité a été vue
     */
    public function confirmSafeZoneAlert(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'alert_id' => 'required|integer|exists:pending_safe_zone_alerts,id',
            ]);

            $user = Auth::user();
            $alertId = $request->input('alert_id');

            // Récupérer l'alerte en attente
            $pendingAlert = PendingSafeZoneAlert::with(['safeZone', 'user'])
                ->where('id', $alertId)
                ->where('is_confirmed', false)
                ->first();

            if (!$pendingAlert) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alerte non trouvée ou déjà confirmée'
                ], 404);
            }

            // Vérifier que l'utilisateur connecté est bien le créateur de la zone
            if ($pendingAlert->safeZone->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à confirmer cette alerte'
                ], 403);
            }

            // Marquer l'alerte comme confirmée
            $pendingAlert->markAsConfirmed($user->id);

            Log::info("Alerte de zone de sécurité confirmée", [
                'alert_id' => $alertId,
                'safe_zone_id' => $pendingAlert->safe_zone_id,
                'confirmed_by' => $user->id,
                'user_in_alert' => $pendingAlert->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Alerte confirmée avec succès',
                'data' => [
                    'alert_id' => $alertId,
                    'safe_zone_name' => $pendingAlert->safeZone->name,
                    'confirmed_at' => $pendingAlert->confirmed_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur lors de la confirmation d'alerte", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation de l\'alerte'
            ], 500);
        }
    }

    /**
     * Récupérer les alertes en attente pour l'utilisateur connecté
     */
    public function getPendingAlerts(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Récupérer toutes les alertes en attente pour les zones créées par l'utilisateur
            $pendingAlerts = PendingSafeZoneAlert::with(['safeZone', 'user', 'safeZoneEvent'])
                ->whereHas('safeZone', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->where('is_confirmed', false)
                ->orderBy('created_at', 'desc')
                ->get();

            $alertsData = $pendingAlerts->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'safe_zone' => [
                        'id' => $alert->safeZone->id,
                        'name' => $alert->safeZone->name,
                    ],
                    'user' => [
                        'id' => $alert->user->id,
                        'name' => $alert->user->name,
                    ],
                    'first_sent_at' => $alert->first_sent_at,
                    'last_reminder_at' => $alert->last_reminder_at,
                    'reminder_count' => $alert->reminder_count,
                    'created_at' => $alert->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $alertsData,
                'count' => $alertsData->count(),
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des alertes en attente", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des alertes'
            ], 500);
        }
    }
}