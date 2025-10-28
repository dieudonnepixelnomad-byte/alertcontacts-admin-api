<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Relationship;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class InvitationController extends Controller
{
    /**
     * Créer une nouvelle invitation
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'default_share_level' => 'required|in:realtime,alert_only,none',
            'suggested_zones' => 'nullable|array',
            'expires_in_hours' => 'nullable|integer|min:1|max:168', // Max 1 semaine
            'max_uses' => 'nullable|integer|min:1|max:10',
            'require_pin' => 'nullable|boolean',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $expiresInHours = $request->input('expires_in_hours', 24);
            
            $invitation = Invitation::createInvitation([
                'inviter_id' => $user->id,
                'default_share_level' => $request->input('default_share_level'),
                'suggested_zones' => $request->input('suggested_zones', []),
                'expires_at' => now()->addHours($expiresInHours),
                'max_uses' => $request->input('max_uses', 1),
                'require_pin' => $request->input('require_pin', false),
                'inviter_name' => $user->name,
                'message' => $request->input('message'),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'invitation' => [
                        'id' => $invitation->id,
                        'token' => $invitation->token,
                        'pin' => $invitation->pin,
                        'invitation_url' => $invitation->invitation_url,
                        'expires_at' => $invitation->expires_at->toISOString(),
                        'max_uses' => $invitation->max_uses,
                        'remaining_uses' => $invitation->remaining_uses,
                        'default_share_level' => $invitation->default_share_level,
                        'suggested_zones' => $invitation->suggested_zones,
                        'message' => $invitation->message,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'invitation'
            ], 500);
        }
    }

    /**
     * Vérifier la validité d'une invitation par token
     */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $invitation = Invitation::where('token', $request->input('token'))->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation introuvable'
            ], 404);
        }

        if (!$invitation->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation expirée ou déjà utilisée'
            ], 410);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'invitation' => [
                    'id' => $invitation->id,
                    'inviter_name' => $invitation->inviter_name,
                    'inviter_avatar_url' => '', // TODO: ajouter avatar
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'remaining_uses' => $invitation->remaining_uses,
                    'requires_pin' => !is_null($invitation->pin),
                    'pin' => $invitation->pin, // Retourner le PIN pour l'affichage dans l'app
                    'default_share_level' => $invitation->default_share_level,
                    'suggested_zones' => $invitation->suggested_zones,
                    'message' => $invitation->message,
                ]
            ]
        ]);
    }

    /**
     * Accepter une invitation
     */
    public function accept(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'pin' => 'nullable|string|size:4',
            'share_level' => 'required|in:realtime,alert_only,none',
            'accept_relation' => 'required|boolean',
            'accepted_zones' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $invitation = Invitation::where('token', $request->input('token'))->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation introuvable'
            ], 404);
        }

        if (!$invitation->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation expirée ou déjà utilisée'
            ], 410);
        }

        // Vérifier le PIN si requis
        if ($invitation->pin && $request->input('pin') !== $invitation->pin) {
            return response()->json([
                'success' => false,
                'message' => 'Code PIN incorrect'
            ], 422);
        }

        $user = Auth::user();

        if (!$request->input('accept_relation')) {
            $invitation->refuse();
            
            // Déclencher la notification de refus
            \App\Jobs\SendInvitationResponseNotificationJob::dispatch(
                $invitation->inviter,
                $user,
                'refused'
            );
            
            return response()->json([
                'success' => false,
                'message' => 'Invitation refusée'
            ], 422);
        }

        try {
            $inviter = $invitation->inviter;

            // Vérifier qu'une relation n'existe pas déjà
            $existingRelation = Relationship::between($user->id, $inviter->id)->first();
            
            if ($existingRelation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une relation existe déjà entre vous'
                ], 409);
            }

            // Vérifier les restrictions d'abonnement pour l'inviteur
            // Un utilisateur gratuit ne peut avoir que 3 proches maximum
            $isInviterPremium = false; // TODO: Implémenter la vérification d'abonnement réelle
            $maxContactsForFreeUser = 3;
            
            if (!$isInviterPremium) {
                $currentContactsCount = $inviter->myContacts()->count();
                
                if ($currentContactsCount >= $maxContactsForFreeUser) {
                    return response()->json([
                        'success' => false,
                        'message' => 'SUBSCRIPTION_LIMIT_REACHED',
                        'details' => "L'utilisateur qui vous a invité a atteint la limite de proches en mode gratuit ($maxContactsForFreeUser proches maximum)."
                    ], 403);
                }
            }

            // Créer la relation bidirectionnelle
            // Relation: inviter -> user (celui qui accepte)
            Relationship::create([
                'user_id' => $inviter->id,
                'contact_id' => $user->id,
                'status' => 'accepted',
                'share_level' => $request->input('share_level'),
                'can_see_me' => true,
                'accepted_at' => now(),
            ]);

            // Relation inverse: user -> inviter
            Relationship::create([
                'user_id' => $user->id,
                'contact_id' => $inviter->id,
                'status' => 'accepted',
                'share_level' => 'none', // Par défaut, l'invité ne partage pas avec l'inviteur
                'can_see_me' => false,
                'accepted_at' => now(),
            ]);

            $invitation->accept();

            // Déclencher la notification d'acceptation
            \App\Jobs\SendInvitationResponseNotificationJob::dispatch(
                $invitation->inviter,
                $user,
                'accepted',
                $request->input('share_level')
            );

            return response()->json([
                'success' => true,
                'message' => 'Invitation acceptée avec succès',
                'data' => [
                    'relationship_created' => true,
                    'share_level' => $request->input('share_level'),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'acceptation de l\'invitation'
            ], 500);
        }
    }

    /**
     * Lister les invitations créées par l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $invitations = Invitation::where('inviter_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'token' => $invitation->token,
                    'status' => $invitation->status,
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'max_uses' => $invitation->max_uses,
                    'used_count' => $invitation->used_count,
                    'remaining_uses' => $invitation->remaining_uses,
                    'default_share_level' => $invitation->default_share_level,
                    'suggested_zones' => $invitation->suggested_zones,
                    'message' => $invitation->message,
                    'created_at' => $invitation->created_at->toISOString(),
                    'is_valid' => $invitation->isValid(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'invitations' => $invitations
            ]
        ]);
    }

    /**
     * Supprimer/annuler une invitation
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        
        $invitation = Invitation::where('id', $id)
            ->where('inviter_id', $user->id)
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation introuvable'
            ], 404);
        }

        $invitation->update(['status' => 'expired']);

        return response()->json([
            'success' => true,
            'message' => 'Invitation annulée'
        ]);
    }
}
