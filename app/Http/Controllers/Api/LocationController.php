<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LocationBatchRequest;
use App\Jobs\ProcessLocationBatch;
use App\Models\User;
use App\Models\UserLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * UC-A1: API d'ingestion des positions GPS
 * 
 * Contrôleur responsable de recevoir les positions GPS du mobile
 * et de les traiter via une queue pour le géoprocessing
 */
class LocationController extends Controller
{
    /**
     * UC-A1: Ingestion batch des positions GPS
     * 
     * Reçoit un batch de positions du mobile et les traite de manière asynchrone
     * 
     * @param LocationBatchRequest $request
     * @return JsonResponse
     */
    public function batch(LocationBatchRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $locations = $request->validated()['locations'];
            
            Log::info('Location batch received', [
                'user_id' => $user->id,
                'count' => count($locations),
                'first_timestamp' => $locations[0]['captured_at_device'] ?? null,
                'last_timestamp' => end($locations)['captured_at_device'] ?? null
            ]);

            // Persister immédiatement les positions pour traçabilité
            $savedLocations = [];
            foreach ($locations as $locationData) {
                $location = UserLocation::create([
                    'user_id' => $user->id,
                    'latitude' => $locationData['latitude'],
                    'longitude' => $locationData['longitude'],
                    'accuracy' => $locationData['accuracy'] ?? null,
                    'speed' => $locationData['speed'] ?? null,
                    'heading' => $locationData['heading'] ?? null,
                    'captured_at_device' => $locationData['captured_at_device'],
                    'source' => $locationData['source'] ?? 'gps',
                    'foreground' => $locationData['foreground'] ?? false,
                    'battery_level' => $locationData['battery_level'] ?? null,
                ]);
                
                $savedLocations[] = $location;
            }

            // Déclencher le traitement géospatial asynchrone
            ProcessLocationBatch::dispatch($user->id, collect($savedLocations)->pluck('id')->toArray())
                ->onQueue('geoprocessing');

            return response()->json([
                'success' => true,
                'message' => 'Locations received and queued for processing',
                'processed_count' => count($savedLocations),
                'server_timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Location batch processing failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process location batch',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * UC-A1: Récupération des dernières positions (pour debug/admin)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function recent(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = min($request->get('limit', 10), 100); // Max 100 positions

        $locations = UserLocation::where('user_id', $user->id)
            ->orderBy('captured_at_device', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'locations' => $locations,
            'count' => $locations->count()
        ]);
    }
}