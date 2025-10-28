<?php

namespace App\Jobs;

use App\Models\PendingSafeZoneAlert;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSafeZoneExitReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private NotificationService $notificationService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->notificationService = app(NotificationService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting safe zone exit reminders job');

        // Récupérer toutes les alertes qui nécessitent un rappel (toutes les 5 minutes)
        $pendingAlerts = PendingSafeZoneAlert::needingReminder(5)
            ->with(['user', 'safeZone', 'safeZoneEvent'])
            ->get();

        Log::info('Found pending alerts needing reminders', [
            'count' => $pendingAlerts->count()
        ]);

        foreach ($pendingAlerts as $alert) {
            try {
                Log::info('Processing reminder for pending alert', [
                    'alert_id' => $alert->id,
                    'user_id' => $alert->user_id,
                    'safe_zone_id' => $alert->safe_zone_id,
                    'reminder_count' => $alert->reminder_count
                ]);

                // Vérifier si l'utilisateur est toujours hors de la zone
                if ($this->isUserStillOutsideZone($alert)) {
                    // Envoyer le rappel
                    $this->notificationService->sendSafeZoneExitReminder(
                        $alert->user_id,
                        $alert->safeZone,
                        $alert->reminder_count + 1
                    );

                    // Mettre à jour l'alerte
                    $alert->recordReminderSent();

                    Log::info('Reminder sent successfully', [
                        'alert_id' => $alert->id,
                        'user_id' => $alert->user_id,
                        'reminder_count' => $alert->reminder_count
                    ]);
                } else {
                    // L'utilisateur est revenu dans la zone, marquer l'alerte comme résolue
                    $alert->markAsConfirmed($alert->user_id);

                    Log::info('User returned to safe zone, alert auto-resolved', [
                        'alert_id' => $alert->id,
                        'user_id' => $alert->user_id,
                        'safe_zone_id' => $alert->safe_zone_id
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('Failed to process reminder for pending alert', [
                    'alert_id' => $alert->id,
                    'user_id' => $alert->user_id,
                    'safe_zone_id' => $alert->safe_zone_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info('Safe zone exit reminders job completed', [
            'processed_alerts' => $pendingAlerts->count()
        ]);
    }

    /**
     * Vérifier si l'utilisateur est toujours hors de la zone de sécurité
     */
    private function isUserStillOutsideZone(PendingSafeZoneAlert $alert): bool
    {
        try {
            // Récupérer la dernière position de l'utilisateur
            $lastLocation = \App\Models\UserLocation::where('user_id', $alert->user_id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastLocation) {
                Log::warning('No location found for user', [
                    'user_id' => $alert->user_id,
                    'alert_id' => $alert->id
                ]);
                return true; // Assumer qu'il est toujours dehors si pas de position
            }

            // Vérifier si la position est récente (moins de 30 minutes)
            if ($lastLocation->created_at->diffInMinutes(now()) > 30) {
                Log::warning('Last location is too old', [
                    'user_id' => $alert->user_id,
                    'alert_id' => $alert->id,
                    'last_location_age_minutes' => $lastLocation->created_at->diffInMinutes(now())
                ]);
                return true; // Assumer qu'il est toujours dehors si position trop ancienne
            }

            // Calculer la distance par rapport à la zone
            $zone = $alert->safeZone;
            if ($zone->isCircle()) {
                $distance = $this->calculateDistance(
                    $lastLocation->latitude,
                    $lastLocation->longitude,
                    $zone->center->latitude,
                    $zone->center->longitude
                );
                return $distance > $zone->radius_m;
            }

            // Pour les zones polygonales, utiliser la géométrie spatiale
            if ($zone->isPolygon()) {
                $point = new \MatanYadaev\EloquentSpatial\Objects\Point(
                    $lastLocation->latitude,
                    $lastLocation->longitude
                );
                return !$zone->geom->contains($point);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error checking if user is still outside zone', [
                'user_id' => $alert->user_id,
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
            return true; // En cas d'erreur, assumer qu'il est toujours dehors
        }
    }

    /**
     * Calculer la distance entre deux points GPS (en mètres)
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // Rayon de la Terre en mètres

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
