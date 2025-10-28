<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserActivitiesController extends Controller
{
    /**
     * Récupérer les activités de l'utilisateur connecté
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Paramètres de pagination
            $perPage = min($request->get('per_page', 20), 100); // Max 100 par page
            $page = $request->get('page', 1);
            
            // Filtres optionnels
            $action = $request->get('action');
            $entityType = $request->get('entity_type');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            
            // Construction de la requête
            $query = UserActivity::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');
            
            // Application des filtres
            if ($action) {
                $query->where('action', $action);
            }
            
            if ($entityType) {
                $query->where('entity_type', $entityType);
            }
            
            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }
            
            // Pagination
            $activities = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'activities' => $activities->items(),
                    'pagination' => [
                        'current_page' => $activities->currentPage(),
                        'last_page' => $activities->lastPage(),
                        'per_page' => $activities->perPage(),
                        'total' => $activities->total(),
                        'has_more_pages' => $activities->hasMorePages()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACTIVITIES_FETCH_ERROR',
                    'message' => 'Erreur lors de la récupération des activités.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }
    
    /**
     * Récupérer les statistiques d'activités de l'utilisateur
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Période (par défaut 30 derniers jours)
            $days = min($request->get('days', 30), 365); // Max 1 an
            $dateFrom = now()->subDays($days);
            
            // Statistiques générales
            $totalActivities = UserActivity::where('user_id', $user->id)
                ->where('created_at', '>=', $dateFrom)
                ->count();
            
            // Activités par type d'action
            $activitiesByAction = UserActivity::where('user_id', $user->id)
                ->where('created_at', '>=', $dateFrom)
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->get()
                ->pluck('count', 'action');
            
            // Activités par type d'entité
            $activitiesByEntity = UserActivity::where('user_id', $user->id)
                ->where('created_at', '>=', $dateFrom)
                ->selectRaw('entity_type, COUNT(*) as count')
                ->groupBy('entity_type')
                ->orderBy('count', 'desc')
                ->get()
                ->pluck('count', 'entity_type');
            
            // Activités par jour (7 derniers jours)
            $activitiesByDay = UserActivity::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get()
                ->pluck('count', 'date');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'period_days' => $days,
                    'total_activities' => $totalActivities,
                    'activities_by_action' => $activitiesByAction,
                    'activities_by_entity' => $activitiesByEntity,
                    'activities_by_day' => $activitiesByDay
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACTIVITIES_STATS_ERROR',
                    'message' => 'Erreur lors de la récupération des statistiques.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }
}