<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingSafeZoneAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'safe_zone_id',
        'safe_zone_event_id',
        'first_alert_sent_at',
        'last_reminder_sent_at',
        'reminder_count',
        'confirmed',
        'confirmed_at',
        'confirmed_by',
        'metadata',
    ];

    protected $casts = [
        'first_alert_sent_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'confirmed' => 'boolean',
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Relation avec l'utilisateur qui est sorti de la zone
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec la zone de sécurité
     */
    public function safeZone(): BelongsTo
    {
        return $this->belongsTo(SafeZone::class);
    }

    /**
     * Relation avec l'événement de sortie de zone
     */
    public function safeZoneEvent(): BelongsTo
    {
        return $this->belongsTo(SafeZoneEvent::class);
    }

    /**
     * Relation avec l'utilisateur qui a confirmé l'alerte
     */
    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Scope pour les alertes non confirmées
     */
    public function scopeUnconfirmed($query)
    {
        return $query->where('confirmed', false);
    }

    /**
     * Scope pour les alertes qui nécessitent un rappel
     * Limite à 4 rappels maximum pour éviter le spam infini
     */
    public function scopeNeedingReminder($query, int $reminderIntervalMinutes = 15)
    {
        $cutoffTime = now()->subMinutes($reminderIntervalMinutes);
        $maxReminders = 4; // Maximum 4 rappels (1 heure de rappels toutes les 15 minutes)
        
        return $query->unconfirmed()
            ->where('reminder_count', '<', $maxReminders) // Limiter le nombre de rappels
            ->where(function ($q) use ($cutoffTime) {
                $q->whereNull('last_reminder_sent_at')
                  ->where('first_alert_sent_at', '<=', $cutoffTime)
                  ->orWhere('last_reminder_sent_at', '<=', $cutoffTime);
            });
    }

    /**
     * Marquer l'alerte comme confirmée
     */
    public function markAsConfirmed(int $confirmedBy): bool
    {
        return $this->update([
            'confirmed' => true,
            'confirmed_at' => now(),
            'confirmed_by' => $confirmedBy,
        ]);
    }

    /**
     * Enregistrer l'envoi d'un rappel
     */
    public function recordReminderSent(): bool
    {
        return $this->update([
            'last_reminder_sent_at' => now(),
            'reminder_count' => $this->reminder_count + 1,
        ]);
    }

    /**
     * Vérifier si l'alerte peut recevoir un rappel
     * Limite à 4 rappels maximum pour éviter le spam infini
     */
    public function canReceiveReminder(int $reminderIntervalMinutes = 15): bool
    {
        if ($this->confirmed) {
            return false;
        }

        // Vérifier la limite de rappels (maximum 4)
        $maxReminders = 4;
        if ($this->reminder_count >= $maxReminders) {
            return false;
        }

        $cutoffTime = now()->subMinutes($reminderIntervalMinutes);

        // Premier rappel après l'alerte initiale
        if (is_null($this->last_reminder_sent_at)) {
            return $this->first_alert_sent_at <= $cutoffTime;
        }

        // Rappels suivants
        return $this->last_reminder_sent_at <= $cutoffTime;
    }
}