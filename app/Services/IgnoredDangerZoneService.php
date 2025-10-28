<?php

namespace App\Services;

use App\Models\IgnoredDangerZone;
use App\Models\DangerZone;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class IgnoredDangerZoneService
{
    /**
     * Ignorer une zone de danger pour un utilisateur
     */
    public function ignoreDangerZone(int $userId, int $dangerZoneId, ?string $reason = null, ?int $expirationMonths = 6): IgnoredDangerZone
    {
        Log::info('User ignoring danger zone', [
            'user_id' => $userId,
            'danger_zone_id' => $dangerZoneId,
            'reason' => $reason,
            'expiration_months' => $expirationMonths
        ]);

        // Vérifier si la zone existe
        $dangerZone = DangerZone::findOrFail($dangerZoneId);

        // Créer ou mettre à jour l'ignorage
        $ignoredZone = IgnoredDangerZone::updateOrCreate(
            [
                'user_id' => $userId,
                'danger_zone_id' => $dangerZoneId,
            ],
            [
                'ignored_at' => now(),
                'expires_at' => $expirationMonths ? now()->addMonths($expirationMonths) : null,
                'reason' => $reason,
            ]
        );

        Log::info('Danger zone ignored successfully', [
            'user_id' => $userId,
            'danger_zone_id' => $dangerZoneId,
            'ignored_zone_id' => $ignoredZone->id,
            'expires_at' => $ignoredZone->expires_at?->toISOString()
        ]);

        return $ignoredZone;
    }

    /**
     * Réactiver les alertes pour une zone de danger
     */
    public function reactivateDangerZone(int $userId, int $dangerZoneId): bool
    {
        Log::info('User reactivating danger zone alerts', [
            'user_id' => $userId,
            'danger_zone_id' => $dangerZoneId
        ]);

        $deleted = IgnoredDangerZone::where('user_id', $userId)
            ->where('danger_zone_id', $dangerZoneId)
            ->delete();

        if ($deleted > 0) {
            Log::info('Danger zone alerts reactivated', [
                'user_id' => $userId,
                'danger_zone_id' => $dangerZoneId
            ]);
            return true;
        }

        Log::warning('No ignored danger zone found to reactivate', [
            'user_id' => $userId,
            'danger_zone_id' => $dangerZoneId
        ]);

        return false;
    }

    /**
     * Vérifier si un utilisateur a ignoré une zone de danger
     */
    public function isZoneIgnored(int $userId, int $dangerZoneId): bool
    {
        return IgnoredDangerZone::where('user_id', $userId)
            ->where('danger_zone_id', $dangerZoneId)
            ->active()
            ->exists();
    }

    /**
     * Obtenir toutes les zones ignorées par un utilisateur
     */
    public function getUserIgnoredZones(int $userId, bool $includeExpired = false): Collection
    {
        $query = IgnoredDangerZone::where('user_id', $userId)
            ->with('dangerZone');

        if (!$includeExpired) {
            $query->active();
        }

        return $query->orderBy('ignored_at', 'desc')->get();
    }

    /**
     * Obtenir les IDs des zones ignorées par un utilisateur (pour optimisation)
     */
    public function getUserIgnoredZoneIds(int $userId): array
    {
        return IgnoredDangerZone::where('user_id', $userId)
            ->active()
            ->pluck('danger_zone_id')
            ->toArray();
    }

    /**
     * Nettoyer les zones ignorées expirées
     */
    public function cleanupExpiredIgnoredZones(): int
    {
        Log::info('Starting cleanup of expired ignored danger zones');

        $deletedCount = IgnoredDangerZone::expired()->delete();

        Log::info('Cleanup completed', [
            'deleted_count' => $deletedCount
        ]);

        return $deletedCount;
    }

    /**
     * Prolonger l'expiration d'une zone ignorée
     */
    public function extendIgnoredZone(int $userId, int $dangerZoneId, int $additionalMonths = 6): bool
    {
        $ignoredZone = IgnoredDangerZone::where('user_id', $userId)
            ->where('danger_zone_id', $dangerZoneId)
            ->first();

        if (!$ignoredZone) {
            return false;
        }

        $ignoredZone->extendExpiration($additionalMonths);

        Log::info('Ignored zone expiration extended', [
            'user_id' => $userId,
            'danger_zone_id' => $dangerZoneId,
            'new_expires_at' => $ignoredZone->expires_at->toISOString(),
            'additional_months' => $additionalMonths
        ]);

        return true;
    }

    /**
     * Obtenir les statistiques des zones ignorées
     */
    public function getIgnoredZonesStats(int $userId): array
    {
        $total = IgnoredDangerZone::where('user_id', $userId)->count();
        $active = IgnoredDangerZone::where('user_id', $userId)->active()->count();
        $expired = $total - $active;

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
        ];
    }

    /**
     * Filtrer les zones de danger en excluant celles ignorées par l'utilisateur
     */
    public function filterIgnoredZones(Collection $dangerZones, int $userId): Collection
    {
        $ignoredZoneIds = $this->getUserIgnoredZoneIds($userId);

        if (empty($ignoredZoneIds)) {
            return $dangerZones;
        }

        return $dangerZones->reject(function ($zone) use ($ignoredZoneIds) {
            return in_array($zone->id, $ignoredZoneIds);
        });
    }
}