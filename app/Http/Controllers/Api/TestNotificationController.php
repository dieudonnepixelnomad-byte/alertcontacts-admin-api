<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Jobs\SendInvitationResponseNotificationJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TestNotificationController extends Controller
{
    /**
     * Tester l'envoi d'une notification d'acceptation d'invitation
     */
    public function testAcceptNotification(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Créer un utilisateur fictif pour simuler l'inviteur
        $inviter = User::factory()->create([
            'name' => 'Inviteur Test',
            'email' => 'inviter@test.com',
            'fcm_token' => 'test_fcm_token_inviter_123',
        ]);

        // Déclencher le job de notification d'acceptation
        SendInvitationResponseNotificationJob::dispatch(
            $inviter,
            $user,
            'accepted',
            'realtime'
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification d\'acceptation d\'invitation envoyée',
            'data' => [
                'inviter_id' => $inviter->id,
                'inviter_name' => $inviter->name,
                'invitee_id' => $user->id,
                'invitee_name' => $user->name,
                'response' => 'accepted',
                'share_level' => 'realtime',
            ]
        ]);
    }

    /**
     * Tester l'envoi d'une notification de refus d'invitation
     */
    public function testRefuseNotification(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Créer un utilisateur fictif pour simuler l'inviteur
        $inviter = User::factory()->create([
            'name' => 'Inviteur Test Refus',
            'email' => 'inviter_refus@test.com',
            'fcm_token' => 'test_fcm_token_inviter_refus_456',
        ]);

        // Déclencher le job de notification de refus
        SendInvitationResponseNotificationJob::dispatch(
            $inviter,
            $user,
            'refused'
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification de refus d\'invitation envoyée',
            'data' => [
                'inviter_id' => $inviter->id,
                'inviter_name' => $inviter->name,
                'invitee_id' => $user->id,
                'invitee_name' => $user->name,
                'response' => 'refused',
            ]
        ]);
    }
}