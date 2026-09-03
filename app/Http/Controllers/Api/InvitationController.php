<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendInvitationNotificationJob;
use App\Models\Invitation;
use App\Models\Relationship;
use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'expires_in_hours' => 'nullable|integer|min:1|max:168',
            'max_uses' => 'nullable|integer|min:1|max:10',
            'require_pin' => 'nullable|boolean',
            'message' => 'nullable|string|max:500',
            'invitee_email' => 'nullable|email',
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

            if ($user->hasReachedContactsLimit()) {
                return $this->contactLimitResponse($user, 'Vous avez atteint votre limite de proches.');
            }

            // Une invitation adressée à un compte existant ne doit pas être
            // créée si le destinataire ne peut pas légalement l'accepter.
            $invitee = $request->filled('invitee_email')
                ? User::where('email', $request->input('invitee_email'))->first()
                : null;

            if ($invitee && $invitee->hasReachedContactsLimit()) {
                return $this->contactLimitResponse(
                    $invitee,
                    'Ce proche a déjà atteint sa limite de proches.'
                );
            }
            
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

            // Notifier le destinataire s'il a déjà un compte
            if ($invitee && $invitee->fcm_token) {
                SendInvitationNotificationJob::dispatch($invitee, $user)
                    ->onQueue('invitations');
            }

            app(PostHogService::class)->capture($user, 'contact_invited', [
                'share_level' => $invitation->default_share_level,
                'has_suggested_zones' => ! empty($invitation->suggested_zones),
                'requires_pin' => $invitation->pin !== null,
                'expires_in_hours' => $expiresInHours,
                'invitee_known' => $invitee !== null,
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
            return DB::transaction(function () use ($request, $user, $invitation): JsonResponse {
                // Verrouiller l'invitation et les deux utilisateurs évite que deux
                // acceptations concurrentes dépassent la limite gratuite.
                $invitation = Invitation::whereKey($invitation->id)->lockForUpdate()->first();
                if (!$invitation || !$invitation->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invitation expirée ou déjà utilisée'
                    ], 410);
                }

                $lockedUsers = User::whereIn('id', [$user->id, $invitation->inviter_id])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $inviter = $lockedUsers->get($invitation->inviter_id);
                $invitee = $lockedUsers->get($user->id);

                if (!$inviter || !$invitee) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Utilisateur introuvable'
                    ], 404);
                }

            // Vérifier qu'une relation n'existe pas déjà
                $existingRelation = Relationship::between($invitee->id, $inviter->id)->first();
            
            if ($existingRelation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une relation existe déjà entre vous'
                ], 409);
            }

                // La limite s'applique aux DEUX personnes : accepter une invitation
                // est aussi l'ajout d'un proche pour l'invité.
                if ($inviter->hasReachedContactsLimit()) {
                    return $this->contactLimitResponse($inviter, 'L\'utilisateur qui vous a invité a atteint sa limite de proches.');
                }
                if ($invitee->hasReachedContactsLimit()) {
                    return $this->contactLimitResponse($invitee, 'Vous avez atteint votre limite de proches.');
                }

            // Créer la relation bidirectionnelle
            // CDC : A (inviteur) voit B (invité) ✅ — B voit A grisé jusqu'à invitation retour
            //
            // Ligne inviteur (Papa) : can_see_me=false → Fils ne peut pas voir Papa encore
            Relationship::create([
                'user_id' => $inviter->id,
                'contact_id' => $invitee->id,
                'status' => 'accepted',
                'share_level' => $invitation->default_share_level ?? 'realtime',
                'can_see_me' => false,
                'accepted_at' => now(),
            ]);

            // Ligne invité (Fils) : can_see_me=true → Papa peut voir Fils ✅
            Relationship::create([
                'user_id' => $invitee->id,
                'contact_id' => $inviter->id,
                'status' => 'accepted',
                'share_level' => $request->input('share_level'),
                'can_see_me' => true,
                'accepted_at' => now(),
            ]);

            $invitation->accept();

            // Déclencher la notification d'acceptation
            \App\Jobs\SendInvitationResponseNotificationJob::dispatch(
                $invitation->inviter,
                $invitee,
                'accepted',
                $request->input('share_level')
            );

                $posthog = app(PostHogService::class);
                $posthog->capture($inviter, 'contact_invitation_accepted', [
                    'role' => 'inviter',
                    'share_level' => $invitation->default_share_level ?? 'realtime',
                    'reciprocal_relation_created' => true,
                ]);
                $posthog->capture($invitee, 'contact_invitation_accepted', [
                    'role' => 'invitee',
                    'share_level' => $request->input('share_level'),
                    'reciprocal_relation_created' => true,
                ]);
                $posthog->capture($inviter, 'aha_1_contact_accepted', [
                    'role' => 'inviter',
                ]);
                $posthog->capture($invitee, 'aha_1_contact_accepted', [
                    'role' => 'invitee',
                ]);
                $this->syncContactPersonProperties($inviter);
                $this->syncContactPersonProperties($invitee);

                return response()->json([
                'success' => true,
                'message' => 'Invitation acceptée avec succès',
                'data' => [
                    'relationship_created' => true,
                    'share_level' => $request->input('share_level'),
                ]
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'acceptation de l\'invitation'
            ], 500);
        }
    }

    private function contactLimitResponse(User $user, string $details): JsonResponse
    {
        $limit = (int) config('alertcontacts.free_tier.contacts_limit', 1);

        return response()->json([
            'success' => false,
            'message' => 'SUBSCRIPTION_LIMIT_REACHED',
            'details' => $details,
            'limit' => $limit,
            'user_id' => $user->id,
        ], 403);
    }

    private function syncContactPersonProperties(User $user): void
    {
        $contactsCount = $user->myContacts()->count();

        app(PostHogService::class)->setPersonProperties($user, [
            'has_active_contact' => $contactsCount > 0,
            'contacts_count_bucket' => $this->countBucket($contactsCount),
        ]);
    }

    private function countBucket(int $count): string
    {
        if ($count === 0) {
            return '0';
        }
        if ($count === 1) {
            return '1';
        }
        if ($count <= 3) {
            return '2-3';
        }

        return '4+';
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
