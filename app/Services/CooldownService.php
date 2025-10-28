<?php

namespace App\Services;

use App\Models\Cooldown;
use Illuminate\Support\Facades\Log;

/**
 * UC-C1: Service de gestion des cooldowns
 * 
 * Gère les cooldowns pour éviter le spam de notifications
 * Utilise la base de données PostgreSQL pour la persistance
 */
class CooldownService
{
    /**
     * UC-C1: Vérifier si une clé est en cooldown
     */
    public function isInCooldown(string $key): bool
    {
        try {
            $isInCooldown = Cooldown::isInCooldown($key);
            
            Log::debug('Cooldown check', [
                'key' => $key,
                'in_cooldown' => $isInCooldown
            ]);
            
            return $isInCooldown;
            
        } catch (\Exception $e) {
            Log::error('Cooldown check failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // En cas d'erreur de base de données, on considère qu'il n'y a pas de cooldown
            // pour éviter de bloquer les notifications critiques
            return false;
        }
    }

    /**
     * UC-C1: Définir un cooldown
     */
    public function setCooldown(string $key, int $durationSeconds, array $metadata = []): bool
    {
        try {
            $cooldown = Cooldown::setCooldown($key, $durationSeconds, $metadata);
            
            Log::debug('Cooldown set', [
                'key' => $key,
                'duration_seconds' => $durationSeconds,
                'expires_at' => $cooldown->expires_at->toISOString(),
                'metadata' => $metadata
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Cooldown set failed', [
                'key' => $key,
                'duration_seconds' => $durationSeconds,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * UC-C1: Supprimer un cooldown
     */
    public function removeCooldown(string $key): bool
    {
        try {
            $result = Cooldown::removeCooldown($key);
            
            Log::debug('Cooldown removed', [
                'key' => $key,
                'existed' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Cooldown removal failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * UC-C1: Obtenir le temps restant d'un cooldown (en secondes)
     */
    public function getRemainingTime(string $key): int
    {
        try {
            $cooldown = Cooldown::where('key', $key)->active()->first();
            
            if (!$cooldown) {
                return 0;
            }
            
            return $cooldown->getRemainingSeconds();
            
        } catch (\Exception $e) {
            Log::error('Cooldown TTL check failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * UC-C1: Nettoyer tous les cooldowns expirés (maintenance)
     */
    public function cleanupExpired(): int
    {
        try {
            $cleaned = Cooldown::cleanupExpired();
            
            Log::info('Cooldown cleanup completed', [
                'cleaned_keys' => $cleaned
            ]);
            
            return $cleaned;
            
        } catch (\Exception $e) {
            Log::error('Cooldown cleanup failed', [
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * UC-C1: Obtenir des statistiques sur les cooldowns
     */
    public function getStats(): array
    {
        try {
            return Cooldown::getStats();
            
        } catch (\Exception $e) {
            Log::error('Cooldown stats failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'total_cooldowns' => 0,
                'active_cooldowns' => 0,
                'expired_cooldowns' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}