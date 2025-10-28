<?php

namespace App\Services;

use App\Models\UserActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivityLogService
{
    /**
     * Enregistrer une activité utilisateur
     */
    public function log(
        int $userId,
        string $activityType,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null,
        ?Request $request = null
    ): ?UserActivity {
        try {
            $activity = UserActivity::create([
                'user_id' => $userId,
                'activity_type' => $activityType,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'metadata' => $metadata,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);

            Log::info('Activity logged', [
                'user_id' => $userId,
                'activity_type' => $activityType,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            return $activity;
        } catch (\Exception $e) {
            Log::error('Failed to log activity', [
                'user_id' => $userId,
                'activity_type' => $activityType,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Enregistrer une activité d'authentification
     */
    public function logAuth(int $userId, string $action, ?Request $request = null, ?array $metadata = null): ?UserActivity
    {
        return $this->log(
            $userId,
            UserActivity::ACTIVITY_AUTH,
            $action,
            null,
            null,
            $metadata,
            $request
        );
    }

    /**
     * Enregistrer une activité de zone
     */
    public function logZone(
        int $userId,
        string $action,
        string $entityType,
        int $entityId,
        ?array $metadata = null,
        ?Request $request = null
    ): ?UserActivity {
        return $this->log(
            $userId,
            UserActivity::ACTIVITY_ZONE,
            $action,
            $entityType,
            $entityId,
            $metadata,
            $request
        );
    }

    /**
     * Enregistrer une activité de notification
     */
    public function logNotification(
        int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null,
        ?Request $request = null
    ): ?UserActivity {
        return $this->log(
            $userId,
            UserActivity::ACTIVITY_NOTIFICATION,
            $action,
            $entityType,
            $entityId,
            $metadata,
            $request
        );
    }

    /**
     * Enregistrer une activité de relation
     */
    public function logRelationship(
        int $userId,
        string $action,
        ?int $relatedUserId = null,
        ?array $metadata = null,
        ?Request $request = null
    ): ?UserActivity {
        return $this->log(
            $userId,
            UserActivity::ACTIVITY_RELATIONSHIP,
            $action,
            'User',
            $relatedUserId,
            $metadata,
            $request
        );
    }

    /**
     * Enregistrer une activité de paramètres
     */
    public function logSettings(
        int $userId,
        string $action,
        ?array $metadata = null,
        ?Request $request = null
    ): ?UserActivity {
        return $this->log(
            $userId,
            UserActivity::ACTIVITY_SETTINGS,
            $action,
            null,
            null,
            $metadata,
            $request
        );
    }

    /**
     * Enregistrer une connexion utilisateur
     */
    public function logLogin(int $userId, ?Request $request = null): ?UserActivity
    {
        return $this->logAuth($userId, UserActivity::ACTION_LOGIN, $request);
    }

    /**
     * Enregistrer une déconnexion utilisateur
     */
    public function logLogout(int $userId, ?Request $request = null): ?UserActivity
    {
        return $this->logAuth($userId, UserActivity::ACTION_LOGOUT, $request);
    }

    /**
     * Enregistrer une inscription utilisateur
     */
    public function logRegister(int $userId, ?Request $request = null): ?UserActivity
    {
        return $this->logAuth($userId, UserActivity::ACTION_REGISTER, $request);
    }

    /**
     * Enregistrer la création d'une zone de danger
     */
    public function logCreateDangerZone(int $userId, int $zoneId, array $zoneData, ?Request $request = null): ?UserActivity
    {
        return $this->logZone(
            $userId,
            UserActivity::ACTION_CREATE_DANGER_ZONE,
            'DangerZone',
            $zoneId,
            [
                'zone_name' => $zoneData['name'] ?? null,
                'zone_type' => $zoneData['type'] ?? null,
                'severity' => $zoneData['severity'] ?? null,
                'latitude' => $zoneData['latitude'] ?? null,
                'longitude' => $zoneData['longitude'] ?? null,
            ],
            $request
        );
    }

    /**
     * Enregistrer la création d'une zone de sécurité
     */
    public function logCreateSafeZone(int $userId, int $zoneId, array $zoneData, ?Request $request = null): ?UserActivity
    {
        return $this->logZone(
            $userId,
            UserActivity::ACTION_CREATE_SAFE_ZONE,
            'SafeZone',
            $zoneId,
            [
                'zone_name' => $zoneData['name'] ?? null,
                'latitude' => $zoneData['latitude'] ?? null,
                'longitude' => $zoneData['longitude'] ?? null,
                'radius' => $zoneData['radius'] ?? null,
            ],
            $request
        );
    }

    /**
     * Enregistrer l'entrée dans une zone de danger
     */
    public function logEnterDangerZone(int $userId, int $zoneId, array $metadata = []): ?UserActivity
    {
        return $this->logZone(
            $userId,
            UserActivity::ACTION_ENTER_DANGER_ZONE,
            'DangerZone',
            $zoneId,
            array_merge($metadata, [
                'timestamp' => now()->toISOString(),
            ])
        );
    }

    /**
     * Enregistrer l'entrée dans une zone de sécurité
     */
    public function logEnterSafeZone(int $userId, int $zoneId, array $metadata = []): ?UserActivity
    {
        return $this->logZone(
            $userId,
            UserActivity::ACTION_ENTER_SAFE_ZONE,
            'SafeZone',
            $zoneId,
            array_merge($metadata, [
                'timestamp' => now()->toISOString(),
            ])
        );
    }

    /**
     * Enregistrer la sortie d'une zone de sécurité
     */
    public function logExitSafeZone(int $userId, int $zoneId, array $metadata = []): ?UserActivity
    {
        return $this->logZone(
            $userId,
            UserActivity::ACTION_EXIT_SAFE_ZONE,
            'SafeZone',
            $zoneId,
            array_merge($metadata, [
                'timestamp' => now()->toISOString(),
            ])
        );
    }

    /**
     * Enregistrer l'envoi d'une alerte de danger
     */
    public function logSendDangerAlert(int $userId, int $zoneId, array $metadata = []): ?UserActivity
    {
        return $this->logNotification(
            $userId,
            UserActivity::ACTION_SEND_DANGER_ALERT,
            'DangerZone',
            $zoneId,
            $metadata
        );
    }

    /**
     * Enregistrer l'envoi d'une invitation
     */
    public function logSendInvitation(int $userId, int $invitedUserId, array $metadata = [], ?Request $request = null): ?UserActivity
    {
        return $this->logRelationship(
            $userId,
            UserActivity::ACTION_SEND_INVITATION,
            $invitedUserId,
            $metadata,
            $request
        );
    }

    /**
     * Enregistrer l'acceptation d'une invitation
     */
    public function logAcceptInvitation(int $userId, int $inviterUserId, array $metadata = [], ?Request $request = null): ?UserActivity
    {
        return $this->logRelationship(
            $userId,
            UserActivity::ACTION_ACCEPT_INVITATION,
            $inviterUserId,
            $metadata,
            $request
        );
    }

    /**
     * Enregistrer le refus d'une invitation
     */
    public function logRejectInvitation(int $userId, int $inviterUserId, array $metadata = [], ?Request $request = null): ?UserActivity
    {
        return $this->logRelationship(
            $userId,
            UserActivity::ACTION_REJECT_INVITATION,
            $inviterUserId,
            $metadata,
            $request
        );
    }

    /**
     * Obtenir les activités d'un utilisateur
     */
    public function getUserActivities(int $userId, int $limit = 50, int $offset = 0): array
    {
        $activities = UserActivity::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'activity_type' => $activity->activity_type,
                'action' => $activity->action,
                'description' => $activity->description,
                'entity_type' => $activity->entity_type,
                'entity_id' => $activity->entity_id,
                'metadata' => $activity->metadata,
                'created_at' => $activity->created_at->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Obtenir les statistiques d'activité d'un utilisateur
     */
    public function getUserActivityStats(int $userId, int $days = 30): array
    {
        $activities = UserActivity::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $stats = [
            'total_activities' => $activities->count(),
            'by_type' => [],
            'by_action' => [],
            'recent_activity' => $activities->first()?->created_at?->toISOString(),
        ];

        foreach ($activities->groupBy('activity_type') as $type => $typeActivities) {
            $stats['by_type'][$type] = $typeActivities->count();
        }

        foreach ($activities->groupBy('action') as $action => $actionActivities) {
            $stats['by_action'][$action] = $actionActivities->count();
        }

        return $stats;
    }

    /**
     * Nettoyer les anciennes activités (pour RGPD)
     */
    public function cleanOldActivities(int $days = 365): int
    {
        $deleted = UserActivity::where('created_at', '<', now()->subDays($days))->delete();
        
        Log::info('Cleaned old activities', ['deleted_count' => $deleted]);
        
        return $deleted;
    }
}