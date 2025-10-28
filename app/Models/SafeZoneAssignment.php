<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour les assignations de zones de sécurité
 * 
 * @property int $id
 * @property int $safe_zone_id
 * @property int $assigned_user_id
 * @property int $assigned_by_user_id
 * @property bool $is_active
 * @property bool $notify_entry
 * @property bool $notify_exit
 * @property array|null $notification_settings
 * @property \Carbon\Carbon $assigned_at
 * @property \Carbon\Carbon|null $accepted_at
 * @property \Carbon\Carbon|null $last_notification_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SafeZoneAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'safe_zone_id',
        'assigned_user_id',
        'assigned_by_user_id',
        'is_active',
        'notify_entry',
        'notify_exit',
        'notification_settings',
        'assigned_at',
        'accepted_at',
        'rejected_at',
        'last_notification_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notify_entry' => 'boolean',
        'notify_exit' => 'boolean',
        'notification_settings' => 'array',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'last_notification_at' => 'datetime',
    ];

    /**
     * Zone de sécurité associée
     */
    public function safeZone(): BelongsTo
    {
        return $this->belongsTo(SafeZone::class);
    }

    /**
     * Utilisateur assigné à la zone
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Utilisateur qui a fait l'assignation
     */
    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /**
     * Scope pour les assignations actives
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour les assignations acceptées
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Scope pour les assignations en attente
     */
    public function scopePending($query)
    {
        return $query->whereNull('accepted_at');
    }

    /**
     * Scope pour un utilisateur spécifique
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }

    /**
     * Scope pour une zone spécifique
     */
    public function scopeForZone($query, int $zoneId)
    {
        return $query->where('safe_zone_id', $zoneId);
    }

    /**
     * Marquer l'assignation comme acceptée
     */
    public function accept(): bool
    {
        $this->accepted_at = now();
        $this->is_active = true;
        return $this->save();
    }

    /**
     * Désactiver l'assignation
     */
    public function deactivate(): bool
    {
        $this->is_active = false;
        return $this->save();
    }

    /**
     * Mettre à jour la dernière notification
     */
    public function updateLastNotification(): bool
    {
        $this->last_notification_at = now();
        return $this->save();
    }

    /**
     * Vérifier si les notifications d'entrée sont activées
     */
    public function shouldNotifyEntry(): bool
    {
        return $this->is_active && $this->notify_entry;
    }

    /**
     * Vérifier si les notifications de sortie sont activées
     */
    public function shouldNotifyExit(): bool
    {
        return $this->is_active && $this->notify_exit;
    }

    /**
     * Formatage pour l'API
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'safe_zone_id' => $this->safe_zone_id,
            'assigned_user_id' => $this->assigned_user_id,
            'assigned_by_user_id' => $this->assigned_by_user_id,
            'is_active' => $this->is_active,
            'notify_entry' => $this->notify_entry,
            'notify_exit' => $this->notify_exit,
            'notification_settings' => $this->notification_settings,
            'assigned_at' => $this->assigned_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'last_notification_at' => $this->last_notification_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
