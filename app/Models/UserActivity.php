<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    protected $fillable = [
        'user_id',
        'activity_type',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Constantes pour les types d'activités
    const ACTIVITY_AUTH = 'auth';
    const ACTIVITY_ZONE = 'zone';
    const ACTIVITY_NOTIFICATION = 'notification';
    const ACTIVITY_RELATIONSHIP = 'relationship';
    const ACTIVITY_SETTINGS = 'settings';
    const ACTIVITY_LOCATION = 'location';

    // Constantes pour les actions d'authentification
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_REGISTER = 'register';
    const ACTION_PASSWORD_RESET = 'password_reset';

    // Constantes pour les actions de zones
    const ACTION_CREATE_DANGER_ZONE = 'create_danger_zone';
    const ACTION_CREATE_SAFE_ZONE = 'create_safe_zone';
    const ACTION_UPDATE_ZONE = 'update_zone';
    const ACTION_DELETE_ZONE = 'delete_zone';
    const ACTION_ENTER_DANGER_ZONE = 'enter_danger_zone';
    const ACTION_ENTER_SAFE_ZONE = 'enter_safe_zone';
    const ACTION_EXIT_SAFE_ZONE = 'exit_safe_zone';

    // Constantes pour les actions de notifications
    const ACTION_SEND_DANGER_ALERT = 'send_danger_alert';
    const ACTION_SEND_SAFE_ZONE_ALERT = 'send_safe_zone_alert';
    const ACTION_RECEIVE_ALERT = 'receive_alert';

    // Constantes pour les actions de relations
    const ACTION_SEND_INVITATION = 'send_invitation';
    const ACTION_ACCEPT_INVITATION = 'accept_invitation';
    const ACTION_REJECT_INVITATION = 'reject_invitation';
    const ACTION_REMOVE_CONTACT = 'remove_contact';

    // Constantes pour les actions de paramètres
    const ACTION_UPDATE_SETTINGS = 'update_settings';
    const ACTION_UPDATE_PRIVACY = 'update_privacy';
    const ACTION_EXPORT_DATA = 'export_data';
    const ACTION_DELETE_ACCOUNT = 'delete_account';

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope pour filtrer par type d'activité
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('activity_type', $type);
    }

    /**
     * Scope pour filtrer par action
     */
    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope pour les activités récentes
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Obtenir une description lisible de l'activité
     */
    public function getDescriptionAttribute(): string
    {
        return match($this->activity_type) {
            self::ACTIVITY_AUTH => $this->getAuthDescription(),
            self::ACTIVITY_ZONE => $this->getZoneDescription(),
            self::ACTIVITY_NOTIFICATION => $this->getNotificationDescription(),
            self::ACTIVITY_RELATIONSHIP => $this->getRelationshipDescription(),
            self::ACTIVITY_SETTINGS => $this->getSettingsDescription(),
            default => "Activité {$this->action}"
        };
    }

    private function getAuthDescription(): string
    {
        return match($this->action) {
            self::ACTION_LOGIN => 'Connexion à l\'application',
            self::ACTION_LOGOUT => 'Déconnexion de l\'application',
            self::ACTION_REGISTER => 'Création de compte',
            self::ACTION_PASSWORD_RESET => 'Réinitialisation du mot de passe',
            default => 'Action d\'authentification'
        };
    }

    private function getZoneDescription(): string
    {
        return match($this->action) {
            self::ACTION_CREATE_DANGER_ZONE => 'Création d\'une zone de danger',
            self::ACTION_CREATE_SAFE_ZONE => 'Création d\'une zone de sécurité',
            self::ACTION_UPDATE_ZONE => 'Modification d\'une zone',
            self::ACTION_DELETE_ZONE => 'Suppression d\'une zone',
            self::ACTION_ENTER_DANGER_ZONE => 'Entrée dans une zone de danger',
            self::ACTION_ENTER_SAFE_ZONE => 'Entrée dans une zone de sécurité',
            self::ACTION_EXIT_SAFE_ZONE => 'Sortie d\'une zone de sécurité',
            default => 'Action sur une zone'
        };
    }

    private function getNotificationDescription(): string
    {
        return match($this->action) {
            self::ACTION_SEND_DANGER_ALERT => 'Envoi d\'alerte de danger',
            self::ACTION_SEND_SAFE_ZONE_ALERT => 'Envoi d\'alerte de zone sécurisée',
            self::ACTION_RECEIVE_ALERT => 'Réception d\'une alerte',
            default => 'Action de notification'
        };
    }

    private function getRelationshipDescription(): string
    {
        return match($this->action) {
            self::ACTION_SEND_INVITATION => 'Envoi d\'invitation',
            self::ACTION_ACCEPT_INVITATION => 'Acceptation d\'invitation',
            self::ACTION_REJECT_INVITATION => 'Refus d\'invitation',
            self::ACTION_REMOVE_CONTACT => 'Suppression d\'un contact',
            default => 'Action sur les relations'
        };
    }

    private function getSettingsDescription(): string
    {
        return match($this->action) {
            self::ACTION_UPDATE_SETTINGS => 'Modification des paramètres',
            self::ACTION_UPDATE_PRIVACY => 'Modification de la confidentialité',
            self::ACTION_EXPORT_DATA => 'Export des données',
            self::ACTION_DELETE_ACCOUNT => 'Suppression du compte',
            default => 'Action sur les paramètres'
        };
    }
}
