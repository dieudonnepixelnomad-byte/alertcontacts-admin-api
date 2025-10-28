<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Factory;
use App\Models\User;

class TestNotificationController extends Controller
{
    /**
     * Envoyer une notification de test à un utilisateur spécifique
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id', 1);
            $user = User::find($userId);
            
            Log::info('Testing notification for user', ['user_id' => $userId]);
            
            if (!$user) {
                Log::error('User not found', ['user_id' => $userId]);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ]);
            }
            
            if (!$user->fcm_token) {
                Log::error('No FCM token for user', ['user_id' => $userId, 'user_email' => $user->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'No FCM token for user',
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name
                    ]
                ]);
            }

            Log::info('Sending notification', [
                'user_id' => $userId,
                'user_email' => $user->email,
                'fcm_token' => substr($user->fcm_token, 0, 20) . '...'
            ]);

            $factory = (new Factory)->withServiceAccount(config('firebase.credentials'));
            $messaging = $factory->createMessaging();

            $notification = Notification::create('🔔 Test AlertContact', 'Ceci est un test de notification push depuis le backend Laravel');
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification($notification)
                ->withData([
                    'type' => 'test',
                    'timestamp' => now()->toISOString(),
                    'source' => 'backend_test'
                ]);

            $result = $messaging->send($message);
            
            Log::info('Notification sent successfully', ['result' => $result]);

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name
                ],
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error sending notification: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]);
        }
    }

    /**
     * Envoyer une notification de test à tous les utilisateurs avec un token FCM
     */
    public function sendBroadcastTest(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'type' => 'string|in:danger_zone,safe_zone,test',
            ]);

            $users = User::whereNotNull('fcm_token')->get();
            
            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun utilisateur avec token FCM trouvé'
                ], 400);
            }

            // Initialiser Firebase
            $factory = (new Factory)->withServiceAccount(config('firebase.credentials'));
            $messaging = $factory->createMessaging();

            $successCount = 0;
            $failureCount = 0;
            $results = [];

            foreach ($users as $user) {
                try {
                    // Créer la notification
                    $notification = Notification::create(
                        $request->title,
                        $request->body
                    );

                    // Données personnalisées
                    $data = [
                        'type' => $request->type ?? 'test',
                        'timestamp' => now()->toISOString(),
                        'user_id' => (string) $user->id,
                    ];

                    // Créer le message
                    $message = CloudMessage::withTarget('token', $user->fcm_token)
                        ->withNotification($notification)
                        ->withData($data);

                    // Envoyer la notification
                    $result = $messaging->send($message);
                    
                    $successCount++;
                    $results[] = [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'status' => 'success',
                        'result' => $result,
                    ];

                } catch (\Exception $e) {
                    $failureCount++;
                    $results[] = [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            Log::info('Notification broadcast de test envoyée', [
                'total_users' => $users->count(),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'title' => $request->title,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Notifications envoyées: {$successCount} succès, {$failureCount} échecs",
                'summary' => [
                    'total_users' => $users->count(),
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                ],
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi du broadcast de test', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du broadcast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lister les utilisateurs avec leurs tokens FCM
     */
    public function listUsersWithFcm(): JsonResponse
    {
        try {
            $users = User::whereNotNull('fcm_token')
                ->select('id', 'email', 'name', 'fcm_platform', 'fcm_token_updated_at')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name,
                        'fcm_platform' => $user->fcm_platform,
                        'fcm_token_updated_at' => $user->fcm_token_updated_at,
                        'fcm_token_preview' => $user->fcm_token ? substr($user->fcm_token, 0, 20) . '...' : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'count' => $users->count(),
                'users' => $users,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des utilisateurs FCM', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}