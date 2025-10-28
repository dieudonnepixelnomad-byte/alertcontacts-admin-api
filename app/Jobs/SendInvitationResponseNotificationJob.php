<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Invitation;
use App\Services\FirebaseNotificationService;
use App\Services\ActivityLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendInvitationResponseNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected User $inviter;
    protected User $invitee;
    protected string $response; // 'accepted' ou 'refused'
    protected ?string $shareLevel;

    /**
     * Créer une nouvelle instance du job
     */
    public function __construct(User $inviter, User $invitee, string $response, ?string $shareLevel = null)
    {
        $this->inviter = $inviter;
        $this->invitee = $invitee;
        $this->response = $response;
        $this->shareLevel = $shareLevel;
    }

    /**
     * Exécuter le job
     */
    public function handle(FirebaseNotificationService $firebaseService, ActivityLogService $activityService): void
    {
        try {
            Log::info('Envoi de notification de réponse d\'invitation', [
                'inviter_id' => $this->inviter->id,
                'invitee_id' => $this->invitee->id,
                'response' => $this->response,
                'share_level' => $this->shareLevel,
            ]);

            // Envoyer la notification Firebase à l'inviteur
            $success = $firebaseService->sendInvitationResponseNotification(
                $this->inviter,
                $this->invitee,
                $this->response,
                $this->shareLevel
            );

            if ($success) {
                Log::info('Notification de réponse d\'invitation envoyée avec succès', [
                    'inviter_id' => $this->inviter->id,
                    'invitee_id' => $this->invitee->id,
                    'response' => $this->response,
                ]);

                // Enregistrer l'activité selon la réponse
                if ($this->response === 'accepted') {
                    $activityService->logAcceptInvitation(
                        $this->invitee->id,
                        $this->inviter->id,
                        [
                            'share_level' => $this->shareLevel,
                            'notification_sent' => true,
                        ]
                    );
                } else {
                    $activityService->logRejectInvitation(
                        $this->invitee->id,
                        $this->inviter->id,
                        [
                            'notification_sent' => true,
                        ]
                    );
                }
            } else {
                Log::warning('Échec de l\'envoi de la notification de réponse d\'invitation', [
                    'inviter_id' => $this->inviter->id,
                    'invitee_id' => $this->invitee->id,
                    'response' => $this->response,
                ]);

                // Enregistrer l'activité même si la notification a échoué
                if ($this->response === 'accepted') {
                    $activityService->logAcceptInvitation(
                        $this->invitee->id,
                        $this->inviter->id,
                        [
                            'share_level' => $this->shareLevel,
                            'notification_sent' => false,
                            'notification_error' => 'Failed to send Firebase notification',
                        ]
                    );
                } else {
                    $activityService->logRejectInvitation(
                        $this->invitee->id,
                        $this->inviter->id,
                        [
                            'notification_sent' => false,
                            'notification_error' => 'Failed to send Firebase notification',
                        ]
                    );
                }
            }

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi de la notification de réponse d\'invitation', [
                'inviter_id' => $this->inviter->id,
                'invitee_id' => $this->invitee->id,
                'response' => $this->response,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-lancer l'exception pour que le job soit marqué comme échoué
            throw $e;
        }
    }

    /**
     * Gérer l'échec du job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de notification de réponse d\'invitation échoué définitivement', [
            'inviter_id' => $this->inviter->id,
            'invitee_id' => $this->invitee->id,
            'response' => $this->response,
            'error' => $exception->getMessage(),
        ]);
    }
}