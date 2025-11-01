<?php

namespace App\Services;

use App\Models\DangerZone;
use App\Models\SafeZone;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * UC-N1: Service de notifications push
 * 
 * Gère l'envoi de notifications push via Firebase Cloud Messaging
 * pour les alertes de zones de danger et zones de sécurité
 */
class NotificationService
{
    private FirebaseNotificationService $firebaseService;
    private CooldownService $cooldownService;
    private QuietHoursService $quietHoursService;

    public function __construct(
        FirebaseNotificationService $firebaseService,
        CooldownService $cooldownService,
        QuietHoursService $quietHoursService
    ) {
        $this->firebaseService = $firebaseService;
        $this->cooldownService = $cooldownService;
        $this->quietHoursService = $quietHoursService;
    }

    /**
     * UC-N1: Envoyer une alerte de zone de danger
     * Nouvelle logique : pas de cooldown supplémentaire (géré uniquement dans GeoprocessingService)
     */
    public function sendDangerZoneAlert(int $userId, DangerZone $zone, float $distance): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                Log::warning('Cannot send danger zone alert - user not found', [
                    'user_id' => $userId,
                    'zone_id' => $zone->id
                ]);
                return false;
            }

            // Vérifier les heures calmes
            if ($this->quietHoursService->isQuietTime($user)) {
                Log::info('Danger zone alert skipped - quiet hours', [
                    'user_id' => $userId,
                    'zone_id' => $zone->id
                ]);
                return false;
            }

            // Envoyer la notification via Firebase
            $success = $this->firebaseService->sendDangerZoneAlert($user, $zone, $distance);
            
            if ($success) {
                Log::info('Danger zone alert sent', [
                    'user_id' => $userId,
                    'zone_id' => $zone->id,
                    'distance' => $distance,
                    'severity' => $zone->severity
                ]);
                
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to send danger zone alert', [
                'user_id' => $userId,
                'zone_id' => $zone->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }



    /**
     * UC-N3: Envoyer une notification de sortie de zone de sécurité
     * Nouvelle logique : pas de cooldown (selon les nouvelles spécifications)
     */
    public function sendSafeZoneExit(int $contactId, SafeZone $zone, User $assignedUser): bool
    {
        try {
            $contact = User::find($contactId);
            if (!$contact) {
                Log::warning('Cannot send safe zone exit - contact not found', [
                    'contact_id' => $contactId,
                    'zone_id' => $zone->id
                ]);
                return false;
            }

            // Vérifier les heures calmes
            if ($this->quietHoursService->isQuietTime($contact)) {
                Log::info('Safe zone exit notification skipped - quiet hours', [
                    'contact_id' => $contactId,
                    'zone_id' => $zone->id
                ]);
                return false;
            }

            // Envoyer la notification via Firebase
            $success = $this->firebaseService->sendSafeZoneExit($contact, $zone, $assignedUser);
            
            if ($success) {
                Log::info('Safe zone exit notification sent', [
                    'contact_id' => $contactId,
                    'zone_id' => $zone->id,
                    'assigned_user_id' => $assignedUser->id
                ]);
                
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to send safe zone exit notification', [
                'contact_id' => $contactId,
                'zone_id' => $zone->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * UC-N4: Envoyer une notification d'invitation
     * Nouvelle logique : pas de cooldown (selon les nouvelles spécifications)
     */
    public function sendInvitationNotification(User $user, User $inviter, string $invitationType): bool
    {
        try {
            // Envoyer la notification via Firebase
            $success = $this->firebaseService->sendInvitationNotification($user, $inviter, $invitationType);
            
            if ($success) {
                Log::info('Invitation notification sent', [
                    'user_id' => $user->id,
                    'inviter_id' => $inviter->id,
                    'invitation_type' => $invitationType
                ]);
                
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to send invitation notification', [
                'user_id' => $user->id,
                'inviter_id' => $inviter->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envoyer une notification de test
     */
    public function sendTestNotification(User $user): bool
    {
        try {
            return $this->firebaseService->sendTestNotification($user);
        } catch (\Exception $e) {
            Log::error('Failed to send test notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }





    /**
     * UC-N6: Envoyer des notifications de sortie de zone de sécurité au créateur de la zone
     */
    public function sendSafeZoneExitAlert(int $userId, SafeZone $zone): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                Log::warning('Cannot send safe zone exit alert - user not found', [
                    'user_id' => $userId,
                    'zone_id' => $zone->id
                ]);
                return false;
            }

            // Récupérer le créateur/propriétaire de la zone
            $zoneOwner = $zone->owner;
            if (!$zoneOwner) {
                Log::warning('Cannot send safe zone exit alert - zone owner not found', [
                    'user_id' => $userId,
                    'zone_id' => $zone->id,
                    'zone_owner_id' => $zone->owner_id
                ]);
                return false;
            }

            // Ne pas envoyer de notification si l'utilisateur qui sort est le créateur de la zone
            if ($userId === $zoneOwner->id) {
                Log::info('Safe zone exit alert skipped - user is the zone owner', [
                    'user_id' => $userId,
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name
                ]);
                return true;
            }

            // Envoyer la notification au créateur de la zone
            $success = $this->sendSafeZoneExit($zoneOwner->id, $zone, $user);

            if ($success) {
                Log::info('Safe zone exit alert sent to zone owner', [
                    'user_id' => $userId,
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'zone_owner_id' => $zoneOwner->id,
                    'zone_owner_name' => $zoneOwner->name
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('Failed to send safe zone exit alert', [
                'user_id' => $userId,
                'zone_id' => $zone->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * UC-N7: Envoyer un rappel de sortie de zone de sécurité au créateur de la zone
     */
    public function sendSafeZoneExitReminder(int $userId, SafeZone $zone, int $reminderCount): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                Log::warning('Cannot send safe zone exit reminder - user not found', [
                    'user_id' => $userId,
                    'zone_id' => $zone->id
                ]);
                return false;
            }

            // Récupérer le créateur/propriétaire de la zone
            $zoneOwner = $zone->owner;
            if (!$zoneOwner) {
                Log::warning('Cannot send safe zone exit reminder - zone owner not found', [
                    'user_id' => $userId,
                    'zone_id' => $zone->id,
                    'zone_owner_id' => $zone->owner_id,
                    'reminder_count' => $reminderCount
                ]);
                return false;
            }

            // Ne pas envoyer de rappel si l'utilisateur qui sort est le créateur de la zone
            if ($userId === $zoneOwner->id) {
                Log::info('Safe zone exit reminder skipped - user is the zone owner', [
                    'user_id' => $userId,
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'reminder_count' => $reminderCount
                ]);
                return true;
            }

            // Vérifier les heures calmes pour le créateur de la zone
            if ($this->quietHoursService->isQuietTime($zoneOwner)) {
                Log::info('Safe zone exit reminder skipped - quiet hours', [
                    'zone_owner_id' => $zoneOwner->id,
                    'user_id' => $userId,
                    'zone_id' => $zone->id,
                    'reminder_count' => $reminderCount
                ]);
                return true;
            }

            // Envoyer le rappel au créateur de la zone via Firebase
            $success = $this->firebaseService->sendSafeZoneExitReminder($zoneOwner, $zone, $user, $reminderCount);
            
            if ($success) {
                Log::info('Safe zone exit reminder sent to zone owner', [
                    'zone_owner_id' => $zoneOwner->id,
                    'zone_owner_name' => $zoneOwner->name,
                    'user_id' => $userId,
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'reminder_count' => $reminderCount
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('Failed to send safe zone exit reminder', [
                'user_id' => $userId,
                'zone_id' => $zone->id,
                'reminder_count' => $reminderCount,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Vérifier si le service est configuré
     */
    public function isConfigured(): bool
    {
        return $this->firebaseService->isConfigured();
    }
}