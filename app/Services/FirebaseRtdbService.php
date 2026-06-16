<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Database;
use Illuminate\Support\Facades\Log;

class FirebaseRtdbService
{
    private ?Database $database = null;

    public function __construct()
    {
        $serviceAccountPath = storage_path('app/firebase/service-account.json');
        if (!file_exists($serviceAccountPath)) {
            Log::warning('FirebaseRtdbService: service account introuvable');
            return;
        }
        try {
            $factory = (new Factory)
                ->withServiceAccount($serviceAccountPath)
                ->withDatabaseUri(config('services.firebase.database_url', 'https://alertcontacts-default-rtdb.europe-west1.firebasedatabase.app'));
            $this->database = $factory->createDatabase();
        } catch (\Throwable $e) {
            Log::error('FirebaseRtdbService init failed: ' . $e->getMessage());
        }
    }

    /**
     * Publier la dernière position d'un utilisateur dans Firebase Realtime DB.
     * Appelé après traitement du batch de positions.
     */
    public function publishLocation(
        string $firebaseUid,
        float $lat,
        float $lng,
        float $accuracy,
        ?float $heading,
        int $updatedAtMs
    ): void {
        if (!$this->database) return;
        try {
            $this->database->getReference("locations/{$firebaseUid}")->update([
                'lat'          => $lat,
                'lng'          => $lng,
                'accuracy'     => $accuracy,
                'heading'      => $heading,
                'updated_at'   => $updatedAtMs,
                'is_invisible' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error("FirebaseRtdbService.publishLocation failed for uid=$firebaseUid: " . $e->getMessage());
        }
    }

    /**
     * Marquer un utilisateur comme invisible dans Firebase RDB.
     */
    public function setInvisible(string $firebaseUid, bool $invisible): void
    {
        if (!$this->database) return;
        try {
            $this->database->getReference("locations/{$firebaseUid}/is_invisible")->set($invisible);
        } catch (\Throwable $e) {
            Log::error("FirebaseRtdbService.setInvisible failed for uid=$firebaseUid: " . $e->getMessage());
        }
    }
}
