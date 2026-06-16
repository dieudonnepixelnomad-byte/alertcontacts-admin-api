<?php

namespace App\Services;

use App\Models\User;
use App\Models\DangerZone;
use App\Models\SafeZone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Google\Auth\Credentials\ServiceAccountCredentials;

class FirebaseNotificationService
{
    private ?string $projectId;
    private ?string $serviceAccountPath;
    private string $fcmUrl;

    public function __construct()
    {
        $this->serviceAccountPath = storage_path('app/firebase/service-account.json');

        // Lire le project_id depuis le fichier service account
        if (file_exists($this->serviceAccountPath)) {
            $serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);
            $this->projectId = $serviceAccount['project_id'] ?? config('services.firebase.project_id', 'your_firebase_project_id');
        } else {
            $this->projectId = config('services.firebase.project_id', 'your_firebase_project_id');
        }

        $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        if (!$this->projectId || !file_exists($this->serviceAccountPath)) {
            Log::warning('Firebase configuration incomplete. Notifications will be disabled.');
        }
    }

    /**
     * Envoyer une notification de zone de danger
     */
    public function sendDangerZoneAlert(User $user, DangerZone $dangerZone, float $distance): bool
    {
        if (!$user->fcm_token) {
            Log::warning("Utilisateur {$user->id} n'a pas de token FCM");
            return false;
        }

        Log::debug("Envoi de notification de danger zone à l'utilisateur {$dangerZone->title}");

        $title = "⚠️ Zone de danger détectée";
        $body = "Vous approchez d'une zone de {$dangerZone->title} à {$this->formatDistance($distance)}";

        $data = [
            'type' => 'danger_zone_alert',
            'danger_zone_id' => $dangerZone->id,
            'zone_name' => $dangerZone->title,
            'distance' => $distance,
            'severity' => $dangerZone->severity,
            'latitude' => $dangerZone->center_lat,
            'longitude' => $dangerZone->center_lng,
        ];

        return $this->sendNotification($user->fcm_token, $title, $body, $data, 'high');
    }



    /**
     * Envoyer une notification d'entrée dans une zone de sécurité
     */
    public function sendSafeZoneEntry(User $user, SafeZone $safeZone, User $assignedUser): bool
    {
        if (!$user->fcm_token) {
            Log::warning("Utilisateur {$user->id} n'a pas de token FCM");
            return false;
        }

        $title = "✅ {$assignedUser->name} est arrivé(e)";
        $body  = "{$safeZone->name} — à l'instant";

        $data = [
            'type'               => 'safe_zone_entry',
            'safe_zone_id'       => $safeZone->id,
            'zone_name'          => $safeZone->name,
            'assigned_user_id'   => $assignedUser->id,
            'assigned_user_name' => $assignedUser->name,
        ];

        return $this->sendNotification($user->fcm_token, $title, $body, $data, 'normal');
    }

    /**
     * Envoyer une notification de sortie de zone de sécurité
     */
    public function sendSafeZoneExit(User $user, SafeZone $safeZone, User $assignedUser): bool
    {
        if (!$user->fcm_token) {
            Log::warning("Utilisateur {$user->id} n'a pas de token FCM");
            return false;
        }

        $title = "🚪 {$assignedUser->name} a quitté {$safeZone->name}";
        $body = "Détecté à l'instant";

        $data = [
            'type' => 'safe_zone_exit',
            'safe_zone_id' => $safeZone->id,
            'zone_name' => $safeZone->name,
            'assigned_user_id' => $assignedUser->id,
            'assigned_user_name' => $assignedUser->name,
        ];

        return $this->sendNotification($user->fcm_token, $title, $body, $data, 'normal');
    }

    /**
     * Envoyer un rappel de sortie de zone de sécurité
     */
    public function sendSafeZoneExitReminder(User $user, SafeZone $safeZone, User $assignedUser, int $reminderCount): bool
    {
        if (!$user->fcm_token) {
            Log::warning("Utilisateur {$user->id} n'a pas de token FCM");
            return false;
        }

        $title = "🔔 Rappel - Zone de sécurité";
        $body = "{$assignedUser->name} est toujours hors de la zone '{$safeZone->name}' (Rappel #{$reminderCount})";

        $data = [
            'type' => 'safe_zone_exit_reminder',
            'safe_zone_id' => $safeZone->id,
            'zone_name' => $safeZone->name,
            'assigned_user_id' => $assignedUser->id,
            'assigned_user_name' => $assignedUser->name,
            'reminder_count' => $reminderCount,
            'action_buttons' => json_encode([
                [
                    'id' => 'confirm_seen',
                    'title' => "J'ai vu",
                    'action' => 'confirm_alert'
                ]
            ])
        ];

        return $this->sendNotification($user->fcm_token, $title, $body, $data, 'high');
    }

    /**
     * Envoyer une notification d'invitation
     */
    public function sendInvitationNotification(User $user, User $inviter, string $invitationType): bool
    {
        if (!$user->fcm_token) {
            Log::warning("Utilisateur {$user->id} n'a pas de token FCM");
            return false;
        }

        $title = "👋 {$inviter->name} t'invite sur AlertContacts";
        $body = "Rejoins-le pour voir vos positions mutuelles";

        $data = [
            'type' => 'invitation',
            'inviter_id' => $inviter->id,
            'inviter_name' => $inviter->name,
            'invitation_type' => $invitationType,
        ];

        return $this->sendNotification($user->fcm_token, $title, $body, $data, 'normal');
    }

    /**
     * Envoyer une notification de réponse à une invitation (acceptation/refus)
     */
    public function sendInvitationResponseNotification(User $inviter, User $invitee, string $response, ?string $shareLevel = null): bool
    {
        if (!$inviter->fcm_token) {
            Log::warning("Utilisateur {$inviter->id} n'a pas de token FCM");
            return false;
        }

        if ($response === 'accepted') {
            $title = "🎉 {$invitee->name} a rejoint AlertContacts !";
            $body = "Vous pouvez maintenant vous voir sur la carte";
            $icon = "✅";
        } else {
            $title = "❌ Invitation refusée";
            $body = "{$invitee->name} a refusé votre invitation";
            $icon = "❌";
        }

        $data = [
            'type' => 'invitation_response',
            'invitee_id' => $invitee->id,
            'invitee_name' => $invitee->name,
            'response' => $response,
            'share_level' => $shareLevel,
        ];

        return $this->sendNotification($inviter->fcm_token, $title, $body, $data, 'normal');
    }

    /**
     * Obtenir le nom d'affichage du niveau de partage
     */
    private function getShareLevelDisplayName(?string $shareLevel): string
    {
        switch ($shareLevel) {
            case 'realtime':
                return 'Temps réel';
            case 'alert_only':
                return 'Alertes uniquement';
            case 'none':
                return 'Aucun partage';
            default:
                return 'Non défini';
        }
    }

    /**
     * Envoyer une notification générique
     */
    private function sendNotification(string $token, string $title, string $body, array $data = [], string $priority = 'normal'): bool
    {
        // Vérifier si Firebase est configuré
        if (!$this->isConfigured()) {
            Log::warning("Firebase non configuré - notification ignorée", [
                'title' => $title,
                'body' => $body,
                'token' => substr($token, 0, 20) . '...',
            ]);
            return false;
        }

        // Cooldown supprimé selon les nouvelles spécifications

        // Obtenir le token d'accès OAuth 2.0
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error("Impossible d'obtenir le token d'accès Firebase");
            return false;
        }

        // Payload pour l'API FCM v1
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data), // FCM v1 nécessite des strings
                'android' => [
                    'priority' => $priority === 'high' ? 'high' : 'normal',
                    'notification' => [
                        'channel_id' => $priority === 'high' ? 'alerts' : 'notifications',
                        'sound' => $priority === 'high' ? 'alert' : 'default',
                        'vibrate_timings' => $priority === 'high' ? ['0.2s', '0.1s', '0.2s'] : ['0.1s'],
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => $priority === 'high' ? '10' : '5',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => $priority === 'high' ? 'alert.caf' : 'default',
                            'badge' => 1,
                            'content-available' => 1,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $http = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ]);
            if (config('app.env') === 'local') {
                $http = $http->withoutVerifying();
            }
            $response = $http->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                $result = $response->json();

                // FCM v1 API retourne un 'name' en cas de succès
                if (isset($result['name'])) {
                    Log::info("Notification FCM envoyée avec succès", [
                        'token' => substr($token, 0, 20) . '...',
                        'title' => $title,
                        'message_name' => $result['name'],
                    ]);

                    return true;
                } else {
                    Log::error("Échec de l'envoi FCM", [
                        'token' => substr($token, 0, 20) . '...',
                        'response' => $result,
                    ]);

                    return false;
                }
            } else {
                Log::error("Erreur HTTP lors de l'envoi FCM", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Exception lors de l'envoi FCM", [
                'message' => $e->getMessage(),
                'token' => substr($token, 0, 20) . '...',
            ]);
            return false;
        }
    }

    /**
     * Invalider un token FCM
     */
    private function invalidateToken(string $token): void
    {
        User::where('fcm_token', $token)->update(['fcm_token' => null]);
        Log::info("Token FCM invalidé: " . substr($token, 0, 20) . '...');
    }

    /**
     * Formater la distance
     */
    private function formatDistance(float $distance): string
    {
        if ($distance < 1000) {
            return round($distance) . ' m';
        } else {
            return round($distance / 1000, 1) . ' km';
        }
    }

    /**
     * Notifier un proche qu'il a été assigné à une zone de sécurité
     */
    public function sendZoneAssignmentNotification(User $assignedUser, User $owner, SafeZone $safeZone): bool
    {
        if (!$assignedUser->fcm_token) {
            Log::warning("Utilisateur {$assignedUser->id} n'a pas de token FCM");
            return false;
        }

        $firstName = explode(' ', $owner->name)[0];
        $title = "📍 {$firstName} t'a ajouté(e) à une zone";
        $body  = "Tu es maintenant suivi(e) dans \"{$safeZone->name}\"";

        $data = [
            'type'         => 'zone_assignment',
            'safe_zone_id' => $safeZone->id,
            'zone_name'    => $safeZone->name,
            'owner_id'     => $owner->id,
            'owner_name'   => $owner->name,
        ];

        return $this->sendNotification($assignedUser->fcm_token, $title, $body, $data, 'normal');
    }

    /**
     * Envoyer une notification de test
     */
    public function sendTestNotification(User $user): bool
    {
        if (!$user->fcm_token) {
            return false;
        }

        $title = "🧪 Notification de test";
        $body = "Votre système de notifications fonctionne correctement !";

        $data = [
            'type' => 'test',
            'timestamp' => now()->toISOString(),
        ];

        return $this->sendNotification($user->fcm_token, $title, $body, $data, 'normal');
    }

    /**
     * Obtenir un token d'accès OAuth 2.0 pour Firebase
     */
    private function getAccessToken(): ?string
    {
        try {
            // Vérifier que le fichier service account existe
            if (!file_exists($this->serviceAccountPath)) {
                Log::error("Fichier service account Firebase introuvable: {$this->serviceAccountPath}");
                return null;
            }

            // Charger les informations du service account
            $serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);
            if (!$serviceAccount) {
                Log::error("Impossible de lire le fichier service account Firebase");
                return null;
            }

            // Créer le JWT pour l'authentification
            $now = time();
            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600, // 1 heure
            ];

            // Signer le JWT avec la clé privée
            $privateKey = $serviceAccount['private_key'];
            $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
            $payload = json_encode($payload);

            $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

            $signature = '';
            openssl_sign($base64Header . '.' . $base64Payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

            $jwt = $base64Header . '.' . $base64Payload . '.' . $base64Signature;

            // Échanger le JWT contre un token d'accès
            $http = Http::asForm();
            if (config('app.env') === 'local') {
                $http = $http->withoutVerifying();
            }
            $response = $http->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error("Erreur lors de l'obtention du token d'accès Firebase", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error("Exception lors de l'obtention du token d'accès Firebase", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Vérifier si les notifications sont configurées
     */
    public function isConfigured(): bool
    {
        return !empty($this->projectId) && !empty($this->serviceAccountPath) && file_exists($this->serviceAccountPath);
    }
}
