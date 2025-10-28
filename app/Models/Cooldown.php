<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Modèle pour la gestion des cooldowns
 * 
 * Remplace le système Redis pour stocker les cooldowns en base de données
 */
class Cooldown extends Model
{
    protected $fillable = [
        'key',
        'expires_at',
        'metadata'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Scope pour les cooldowns actifs (non expirés)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope pour les cooldowns expirés
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Vérifier si le cooldown est encore actif
     */
    public function isActive(): bool
    {
        return $this->expires_at > now();
    }

    /**
     * Obtenir le temps restant en secondes
     */
    public function getRemainingSeconds(): int
    {
        if (!$this->isActive()) {
            return 0;
        }

        return max(0, $this->expires_at->diffInSeconds(now()));
    }

    /**
     * Créer ou mettre à jour un cooldown
     */
    public static function setCooldown(string $key, int $durationSeconds, array $metadata = []): self
    {
        return self::updateOrCreate(
            ['key' => $key],
            [
                'expires_at' => now()->addSeconds($durationSeconds),
                'metadata' => $metadata
            ]
        );
    }

    /**
     * Vérifier si une clé est en cooldown
     */
    public static function isInCooldown(string $key): bool
    {
        return self::where('key', $key)->active()->exists();
    }

    /**
     * Supprimer un cooldown
     */
    public static function removeCooldown(string $key): bool
    {
        return self::where('key', $key)->delete() > 0;
    }

    /**
     * Nettoyer les cooldowns expirés
     */
    public static function cleanupExpired(): int
    {
        return self::expired()->delete();
    }

    /**
     * Obtenir les statistiques des cooldowns
     */
    public static function getStats(): array
    {
        $total = self::count();
        $active = self::active()->count();
        $expired = $total - $active;

        return [
            'total_cooldowns' => $total,
            'active_cooldowns' => $active,
            'expired_cooldowns' => $expired
        ];
    }
}