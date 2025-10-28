<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class IgnoredDangerZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'danger_zone_id',
        'ignored_at',
        'expires_at',
        'reason',
    ];

    protected $casts = [
        'ignored_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec la zone de danger
     */
    public function dangerZone(): BelongsTo
    {
        return $this->belongsTo(DangerZone::class);
    }

    /**
     * Scope pour les zones ignorées actives (non expirées)
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope pour les zones ignorées expirées
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
    }

    /**
     * Vérifier si l'ignorage est encore actif
     */
    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Vérifier si l'ignorage a expiré
     */
    public function isExpired(): bool
    {
        return !$this->isActive();
    }

    /**
     * Définir une expiration automatique (par défaut 6 mois)
     */
    public function setDefaultExpiration(): void
    {
        $this->expires_at = now()->addMonths(6);
        $this->save();
    }

    /**
     * Prolonger l'expiration
     */
    public function extendExpiration(int $months = 6): void
    {
        $this->expires_at = now()->addMonths($months);
        $this->save();
    }

    /**
     * Réactiver (supprimer l'expiration)
     */
    public function reactivate(): void
    {
        $this->expires_at = null;
        $this->save();
    }
}