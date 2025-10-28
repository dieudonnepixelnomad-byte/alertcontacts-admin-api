<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'message',
        'rating',
        'app_version',
        'device_info',
        'status',
        'admin_response',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'rating' => 'integer',
    ];

    /**
     * Types de feedback disponibles
     */
    public const TYPES = [
        'bug' => 'Bug/Problème',
        'feature' => 'Demande de fonctionnalité',
        'compliment' => 'Compliment',
        'complaint' => 'Plainte',
        'other' => 'Autre',
    ];

    /**
     * Statuts disponibles
     */
    public const STATUSES = [
        'pending' => 'En attente',
        'reviewed' => 'Examiné',
        'resolved' => 'Résolu',
        'closed' => 'Fermé',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour filtrer par statut
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pour les feedbacks récents
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Marquer comme examiné
     */
    public function markAsReviewed(): void
    {
        $this->update([
            'status' => 'reviewed',
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Ajouter une réponse admin
     */
    public function addAdminResponse(string $response): void
    {
        $this->update([
            'admin_response' => $response,
            'status' => 'resolved',
            'reviewed_at' => now(),
        ]);
    }
}
