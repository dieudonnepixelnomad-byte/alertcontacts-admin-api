<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserLocation;
use App\Services\FirebaseRtdbService;
use App\Services\GeoprocessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * UC-G1/G2: Job de traitement géospatial des positions
 *
 * Traite un batch de positions pour détecter les entrées/sorties
 * de zones de danger et zones de sécurité
 */
class ProcessLocationBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public array $locationIds
    ) {
        $this->onQueue('geoprocessing');
    }

    /**
     * Execute the job.
     */
    public function handle(GeoprocessingService $geoprocessingService, FirebaseRtdbService $firebaseRtdbService): void
    {
        try {
            Log::info('Processing location batch', [
                'user_id' => $this->userId,
                'location_count' => count($this->locationIds)
            ]);

            // Récupérer les positions à traiter
            $locations = UserLocation::whereIn('id', $this->locationIds)
                ->where('user_id', $this->userId)
                ->orderBy('captured_at_device')
                ->get();

            if ($locations->isEmpty()) {
                Log::warning('No locations found for processing', [
                    'user_id' => $this->userId,
                    'location_ids' => $this->locationIds
                ]);
                return;
            }

            // Traiter chaque position
            foreach ($locations as $location) {
                $geoprocessingService->processLocation($location);
            }

            // Publier la dernière position dans Firebase Realtime DB
            $lastLocation = $locations->last();
            $user = User::find($this->userId);
            if ($user && $user->firebase_uid) {
                $updatedAtMs = (int) (
                    $lastLocation->captured_at_device instanceof \Carbon\Carbon
                        ? $lastLocation->captured_at_device->timestamp * 1000
                        : strtotime($lastLocation->captured_at_device) * 1000
                );
                $firebaseRtdbService->publishLocation(
                    $user->firebase_uid,
                    (float) $lastLocation->latitude,
                    (float) $lastLocation->longitude,
                    (float) $lastLocation->accuracy,
                    isset($lastLocation->heading) ? (float) $lastLocation->heading : null,
                    $updatedAtMs
                );
            }

            Log::info('Location batch processed successfully', [
                'user_id' => $this->userId,
                'processed_count' => $locations->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Location batch processing failed', [
                'user_id' => $this->userId,
                'location_ids' => $this->locationIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Relancer le job si on n'a pas atteint le nombre max de tentatives
            if ($this->attempts() < $this->tries) {
                $this->release(30); // Attendre 30 secondes avant de réessayer
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Location batch processing failed permanently', [
            'user_id' => $this->userId,
            'location_ids' => $this->locationIds,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
