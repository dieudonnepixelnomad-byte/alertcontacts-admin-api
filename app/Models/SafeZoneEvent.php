<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class SafeZoneEvent extends Model
{
    use HasFactory, HasSpatial;

    protected $fillable = [
        'user_id',
        'safe_zone_id',
        'event_type', // 'enter' ou 'exit'
        'location',
        'accuracy',
        'speed_kmh',
        'heading',
        'battery_level',
        'source',
        'foreground',
        'captured_at_device',
        'notification_sent',
        'notification_sent_at',
        'distance_m',
    ];

    protected $casts = [
        'location' => Point::class,
        'accuracy' => 'decimal:2',
        'speed_kmh' => 'decimal:2',
        'distance_m' => 'decimal:2',
        'heading' => 'decimal:2',
        'battery_level' => 'integer',
        'foreground' => 'boolean',
        'captured_at_device' => 'datetime',
        'notification_sent' => 'boolean',
        'notification_sent_at' => 'datetime',
    ];

    /**
     * Relation avec l'utilisateur
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
     * Scope pour les événements d'entrée
     */
    public function scopeEnters($query)
    {
        return $query->where('event_type', 'enter');
    }

    /**
     * Scope pour les événements de sortie
     */
    public function scopeExits($query)
    {
        return $query->where('event_type', 'exit');
    }

    /**
     * Scope pour les événements récents
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope pour les événements d'un utilisateur
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour les événements d'une zone
     */
    public function scopeForZone($query, int $safeZoneId)
    {
        return $query->where('safe_zone_id', $safeZoneId);
    }

    /**
     * Vérifier si c'est un événement d'entrée
     */
    public function isEnter(): bool
    {
        return $this->event_type === 'enter';
    }

    /**
     * Vérifier si c'est un événement de sortie
     */
    public function isExit(): bool
    {
        return $this->event_type === 'exit';
    }

    /**
     * Formater pour l'API
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'safe_zone_id' => $this->safe_zone_id,
            'safe_zone_name' => $this->safeZone?->name,
            'event_type' => $this->event_type,
            'location' => [
                'lat' => $this->location->latitude,
                'lng' => $this->location->longitude,
            ],
            'accuracy' => (float) $this->accuracy,
            'speed_kmh' => (float) $this->speed_kmh,
            'heading' => (float) $this->heading,
            'battery_level' => $this->battery_level,
            'source' => $this->source,
            'foreground' => $this->foreground,
            'captured_at_device' => $this->captured_at_device?->toISOString(),
            'notification_sent' => $this->notification_sent,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Marquer la notification comme envoyée
     */
    public function markNotificationSent(): void
    {
        $this->update(['notification_sent' => true]);
    }

    /**
     * Définir les valeurs par défaut
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (!isset($event->notification_sent)) {
                $event->notification_sent = false;
            }
        });
    }
}