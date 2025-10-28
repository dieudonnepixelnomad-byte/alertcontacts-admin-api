<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Relationship;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RelationshipController extends Controller
{
    /**
     * Lister tous les proches de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $relationships = Relationship::where('user_id', $user->id)
            ->with('contact')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($relationship) {
                return [
                    'id' => $relationship->id,
                    'contact' => [
                        'id' => $relationship->contact->id,
                        'name' => $relationship->contact->name,
                        'email' => $relationship->contact->email,
                        'avatar_url' => '', // TODO: ajouter avatar
                    ],
                    'status' => $relationship->status,
                    'share_level' => $relationship->share_level,
                    'can_see_me' => $relationship->can_see_me,
                    'created_at' => $relationship->created_at->toISOString(),
                    'accepted_at' => $relationship->accepted_at?->toISOString(),
                    'refused_at' => $relationship->refused_at?->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'relationships' => $relationships
            ]
        ]);
    }

    /**
     * Mettre à jour les paramètres de partage d'une relation
     */
    public function updateShareLevel(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'share_level' => 'required|in:realtime,alert_only,none',
            'can_see_me' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        $relationship = Relationship::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->first();

        if (!$relationship) {
            return response()->json([
                'success' => false,
                'message' => 'Relation introuvable'
            ], 404);
        }

        $oldShareLevel = $relationship->share_level;
        $oldCanSeeMe = $relationship->can_see_me;

        $relationship->update([
            'share_level' => $request->input('share_level'),
            'can_see_me' => $request->input('can_see_me'),
        ]);

        // TODO: Envoyer notification au contact si changement significatif
        if ($oldShareLevel !== $relationship->share_level || $oldCanSeeMe !== $relationship->can_see_me) {
            // Logique de notification à implémenter
        }

        return response()->json([
            'success' => true,
            'message' => 'Paramètres de partage mis à jour',
            'data' => [
                'relationship' => [
                    'id' => $relationship->id,
                    'share_level' => $relationship->share_level,
                    'can_see_me' => $relationship->can_see_me,
                ]
            ]
        ]);
    }

    /**
     * Supprimer une relation (retirer un proche)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        
        $relationship = Relationship::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$relationship) {
            return response()->json([
                'success' => false,
                'message' => 'Relation introuvable'
            ], 404);
        }

        $contactId = $relationship->contact_id;

        // Supprimer les deux relations (bidirectionnelle)
        Relationship::where(function ($query) use ($user, $contactId) {
            $query->where('user_id', $user->id)->where('contact_id', $contactId);
        })->orWhere(function ($query) use ($user, $contactId) {
            $query->where('user_id', $contactId)->where('contact_id', $user->id);
        })->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proche retiré avec succès'
        ]);
    }

    /**
     * Obtenir les détails d'une relation spécifique
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        
        $relationship = Relationship::where('id', $id)
            ->where('user_id', $user->id)
            ->with('contact')
            ->first();

        if (!$relationship) {
            return response()->json([
                'success' => false,
                'message' => 'Relation introuvable'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'relationship' => [
                    'id' => $relationship->id,
                    'contact' => [
                        'id' => $relationship->contact->id,
                        'name' => $relationship->contact->name,
                        'email' => $relationship->contact->email,
                        'avatar_url' => '', // TODO: ajouter avatar
                    ],
                    'status' => $relationship->status,
                    'share_level' => $relationship->share_level,
                    'can_see_me' => $relationship->can_see_me,
                    'created_at' => $relationship->created_at->toISOString(),
                    'accepted_at' => $relationship->accepted_at?->toISOString(),
                    'refused_at' => $relationship->refused_at?->toISOString(),
                ]
            ]
        ]);
    }

    /**
     * Obtenir les statistiques des relations
     */
    public function stats(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $stats = [
            'total' => Relationship::where('user_id', $user->id)->count(),
            'accepted' => Relationship::where('user_id', $user->id)->where('status', 'accepted')->count(),
            'pending' => Relationship::where('user_id', $user->id)->where('status', 'pending')->count(),
            'refused' => Relationship::where('user_id', $user->id)->where('status', 'refused')->count(),
            'realtime_sharing' => Relationship::where('user_id', $user->id)
                ->where('status', 'accepted')
                ->where('share_level', 'realtime')
                ->count(),
            'alert_only_sharing' => Relationship::where('user_id', $user->id)
                ->where('status', 'accepted')
                ->where('share_level', 'alert_only')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats
            ]
        ]);
    }

    /**
     * Rechercher des utilisateurs pour les inviter
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $query = $request->input('query');

        // Rechercher des utilisateurs par nom ou email
        $users = User::where('id', '!=', $user->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                  ->orWhere('email', 'ILIKE', "%{$query}%");
            })
            ->whereNotIn('id', function ($q) use ($user) {
                $q->select('contact_id')
                  ->from('relationships')
                  ->where('user_id', $user->id);
            })
            ->limit(10)
            ->get()
            ->map(function ($foundUser) {
                return [
                    'id' => $foundUser->id,
                    'name' => $foundUser->name,
                    'email' => $foundUser->email,
                    'avatar_url' => '', // TODO: ajouter avatar
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users
            ]
        ]);
    }

    /**
     * Lister les zones assignables à un proche
     * GET /api/proches/{contact_id}/zones
     */
    public function getAssignableZones(Request $request, $contactId): JsonResponse
    {
        $user = Auth::user();
        
        // Vérifier que la relation existe et est acceptée
        $relationship = Relationship::where('user_id', $user->id)
            ->where('contact_id', $contactId)
            ->where('status', 'accepted')
            ->first();

        if (!$relationship) {
            return response()->json([
                'success' => false,
                'message' => 'Relation introuvable ou non acceptée'
            ], 404);
        }

        // Récupérer toutes les zones de sécurité de l'utilisateur
        $zones = \App\Models\SafeZone::where('owner_id', $user->id)
            ->where('is_active', true)
            ->with(['assignments' => function ($query) use ($contactId) {
                $query->where('assigned_user_id', $contactId);
            }])
            ->get()
            ->map(function ($zone) {
                $data = [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'icon' => $zone->icon,
                    'is_assigned' => $zone->assignments->isNotEmpty(),
                    'assignment_status' => $zone->assignments->first()?->is_active ?? null,
                ];

                // Ajouter les données géométriques selon le type
                if ($zone->isCircle()) {
                    $data['center'] = [
                        'lat' => $zone->center->latitude,
                        'lng' => $zone->center->longitude,
                    ];
                    $data['radius_m'] = $zone->radius_m;
                } else {
                    // Pour les polygones, on peut ajouter la géométrie si nécessaire
                    $data['geom'] = $zone->geom;
                }

                return $data;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'contact_id' => $contactId,
                'zones' => $zones
            ]
        ]);
    }

    /**
     * Assigner une zone à un proche
     * POST /api/proches/{contact_id}/zones/{zone_id}
     */
    public function assignZone(Request $request, $contactId, $zoneId): JsonResponse
    {
        $user = Auth::user();
        
        // Vérifier que la relation existe et est acceptée
        $relationship = Relationship::where('user_id', $user->id)
            ->where('contact_id', $contactId)
            ->where('status', 'accepted')
            ->first();

        if (!$relationship) {
            return response()->json([
                'success' => false,
                'message' => 'Relation introuvable ou non acceptée'
            ], 404);
        }

        // Vérifier que la zone appartient à l'utilisateur
        $zone = \App\Models\SafeZone::where('id', $zoneId)
            ->where('owner_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => 'Zone de sécurité introuvable'
            ], 404);
        }

        // Vérifier que l'assignation n'existe pas déjà
        $existingAssignment = \App\Models\SafeZoneAssignment::where('safe_zone_id', $zoneId)
            ->where('assigned_user_id', $contactId)
            ->first();

        if ($existingAssignment) {
            // Si elle existe mais est inactive, la réactiver
            if (!$existingAssignment->is_active) {
                $existingAssignment->update(['is_active' => true]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Zone réassignée avec succès',
                    'data' => [
                        'assignment' => [
                            'zone_id' => $zoneId,
                            'contact_id' => $contactId,
                            'is_active' => true
                        ]
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Cette zone est déjà assignée à ce proche'
            ], 409);
        }

        // VÉRIFICATION DES RESTRICTIONS D'ABONNEMENT
        // Compter le nombre de proches déjà assignés à cette zone
        $currentAssignedCount = \App\Models\SafeZoneAssignment::where('safe_zone_id', $zoneId)
            ->where('is_active', true)
            ->count();

        // Pour l'instant, on considère tous les utilisateurs comme gratuits (limite: 1 proche par zone)
        // TODO: Implémenter un vrai système d'abonnement avec vérification Premium
        $isUserPremium = false; // À remplacer par une vraie vérification d'abonnement
        $maxContactsPerZone = $isUserPremium ? PHP_INT_MAX : 1;

        if ($currentAssignedCount >= $maxContactsPerZone) {
            return response()->json([
                'success' => false,
                'message' => 'Limite atteinte: vous ne pouvez assigner qu\'un seul proche par zone en mode gratuit. Passez à Premium pour une surveillance multi-proches.',
                'error_code' => 'SUBSCRIPTION_LIMIT_REACHED',
                'data' => [
                    'current_count' => $currentAssignedCount,
                    'max_allowed' => $maxContactsPerZone,
                    'is_premium' => $isUserPremium
                ]
            ], 403);
        }

        // Créer l'assignation
        $assignment = \App\Models\SafeZoneAssignment::create([
            'safe_zone_id' => $zoneId,
            'assigned_user_id' => $contactId,
            'assigned_by_user_id' => $user->id,
            'is_active' => true,
            'assigned_at' => now()
        ]);

        // TODO: Envoyer notification au proche selon son share_level
        // if ($relationship->share_level !== 'none') {
        //     $this->sendZoneAssignmentNotification($contactId, $zone, 'assigned');
        // }

        return response()->json([
            'success' => true,
            'message' => 'Zone assignée avec succès',
            'data' => [
                'assignment' => [
                    'zone_id' => $zoneId,
                    'contact_id' => $contactId,
                    'is_active' => true
                ]
            ]
        ]);
    }

    /**
     * Retirer l'assignation d'une zone à un proche
     * DELETE /api/proches/{contact_id}/zones/{zone_id}
     */
    public function unassignZone(Request $request, $contactId, $zoneId): JsonResponse
    {
        $user = Auth::user();
        
        // Vérifier que la relation existe
        $relationship = Relationship::where('user_id', $user->id)
            ->where('contact_id', $contactId)
            ->first();

        if (!$relationship) {
            return response()->json([
                'success' => false,
                'message' => 'Relation introuvable'
            ], 404);
        }

        // Vérifier que la zone appartient à l'utilisateur
        $zone = \App\Models\SafeZone::where('id', $zoneId)
            ->where('owner_id', $user->id)
            ->first();

        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => 'Zone de sécurité introuvable'
            ], 404);
        }

        // Supprimer l'assignation
        $deleted = \App\Models\SafeZoneAssignment::where('safe_zone_id', $zoneId)
            ->where('assigned_user_id', $contactId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune assignation trouvée pour cette zone et ce proche'
            ], 404);
        }

        // TODO: Envoyer notification au proche selon son share_level
        // if ($relationship->share_level !== 'none') {
        //     $this->sendZoneAssignmentNotification($contactId, $zone, 'unassigned');
        // }

        return response()->json([
            'success' => true,
            'message' => 'Zone retirée avec succès'
        ]);
    }

    /**
     * Mettre en pause/reprendre l'assignation d'une zone
     * PATCH /api/proches/{contact_id}/zones/{zone_id}
     */
    public function toggleZoneAssignment(Request $request, $contactId, $zoneId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        // Vérifier que la relation existe
        $relationship = Relationship::where('user_id', $user->id)
            ->where('contact_id', $contactId)
            ->first();

        if (!$relationship) {
            return response()->json([
                'success' => false,
                'message' => 'Relation introuvable'
            ], 404);
        }

        // Vérifier que la zone appartient à l'utilisateur
        $zone = \App\Models\SafeZone::where('id', $zoneId)
            ->where('owner_id', $user->id)
            ->first();

        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => 'Zone de sécurité introuvable'
            ], 404);
        }

        // Trouver et mettre à jour l'assignation
        $assignment = \App\Models\SafeZoneAssignment::where('safe_zone_id', $zoneId)
            ->where('assigned_user_id', $contactId)
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune assignation trouvée pour cette zone et ce proche'
            ], 404);
        }

        $isActive = $request->input('is_active');
        $assignment->update(['is_active' => $isActive]);

        $action = $isActive ? 'activée' : 'mise en pause';

        return response()->json([
            'success' => true,
            'message' => "Assignation {$action} avec succès",
            'data' => [
                'assignment' => [
                    'zone_id' => $zoneId,
                    'contact_id' => $contactId,
                    'is_active' => $isActive
                ]
            ]
        ]);
    }
}
