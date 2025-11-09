<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Update FCM token for a user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateFcmToken(Request $request)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string|max:255',
                'platform' => 'required|string|in:android,ios',
                'email' => 'required|email',
                'old_fcm_token' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $request->input('email');
            $fcmToken = $request->input('fcm_token');
            $platform = $request->input('platform');
            $oldFcmToken = $request->input('old_fcm_token');

            // Rechercher l'utilisateur par email
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Vérification de sécurité avec l'ancien token si fourni
            if ($oldFcmToken && $user->fcm_token && $user->fcm_token !== $oldFcmToken) {
                Log::warning('FCM token update failed: invalid old token', [
                    'user_id' => $user->id,
                    'email' => $email,
                    'provided_old_token' => $oldFcmToken,
                    'actual_old_token' => $user->fcm_token
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid old token'
                ], 403);
            }

            // Mise à jour du token FCM
            $user->update([
                'fcm_token' => $fcmToken,
                'fcm_platform' => $platform,
                'fcm_token_updated_at' => now()
            ]);

            Log::info('FCM token updated successfully', [
                'user_id' => $user->id,
                'email' => $email,
                'platform' => $platform,
                'token_length' => strlen($fcmToken)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating FCM token', [
                'error' => $e->getMessage(),
                'email' => $request->input('email', 'unknown')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Met à jour le profil de l'utilisateur.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'photo_url' => 'sometimes|nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('photo_url')) {
            $user->photo_url = $request->photo_url;
        }

        $user->save();

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user' => $user
        ]);
    }
}