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

                // Envoyer le rappel systématiquement (notifications périodiques)
                $this->notificationService->sendSafeZoneExitReminder(
                    $alert->user_id,
                    $alert->safeZone,
                    $alert->reminder_count + 1
                );

                // Mettre à jour l'alerte
                $alert->recordReminderSent();

                Log::info('Periodic reminder sent successfully', [
                    'alert_id' => $alert->id,
                    'user_id' => $alert->user_id,
                    'reminder_count' => $alert->reminder_count,
                    'safe_zone_name' => $alert->safeZone->name
                ]);

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


}
