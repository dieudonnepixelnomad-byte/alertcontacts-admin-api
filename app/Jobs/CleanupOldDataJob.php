<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Job de nettoyage automatique des données anciennes
 *
 * Ce job supprime les données anciennes des tables critiques pour éviter
 * l'accumulation excessive de données et maintenir les performances.
 *
 * Tables nettoyées :
 * - user_locations (30 jours)
 * - telescope_entries (7 jours)
 * - user_activities (90 jours)
 * - safe_zone_events (180 jours)
 * - cooldowns (expirés)
 * - jobs/failed_jobs (7/30 jours)
 * - personal_access_tokens (expirés)
 */
class CleanupOldDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout du job (30 minutes)
     */
    public int $timeout = 1800;

    /**
     * Nombre de tentatives
     */
    public int $tries = 3;

    /**
     * Configuration des rétentions par table (chargée depuis config/cleanup.php)
     */
    private array $retentionConfig;

    /**
     * Taille des lots pour le nettoyage (éviter les timeouts)
     */
    private int $batchSize = 1000;

    /**
     * Constructeur
     */
    public function __construct()
    {
        // Charger la configuration depuis config/cleanup.php
        $this->retentionConfig = collect(config('cleanup.retention', []))
            ->mapWithKeys(function ($config, $table) {
                return [$table => $config['days'] ?? 10];
            })
            ->toArray();

        // Configuration par défaut si le fichier config n'existe pas
        if (empty($this->retentionConfig)) {
            $this->retentionConfig = [
                'user_locations' => 5,
                'telescope_entries' => 7,
                'user_activities' => 90,
                'safe_zone_events' => 180,
                'jobs' => 7,
                'failed_jobs' => 30,
                'job_batches' => 30,
            ];
        }

        $this->onQueue('default');
    }

    /**
     * Exécution du job de nettoyage
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $totalDeleted = 0;

        Log::info('🧹 Début du nettoyage automatique des données anciennes');

        try {
            // 1. Nettoyage des positions GPS (TRÈS CRITIQUE)
            $deleted = $this->cleanupUserLocations();
            $totalDeleted += $deleted;
            Log::info("✅ user_locations: {$deleted} entrées supprimées");

            // 2. Nettoyage des logs Telescope (TRÈS CRITIQUE)
            $deleted = $this->cleanupTelescopeEntries();
            $totalDeleted += $deleted;
            Log::info("✅ telescope_entries: {$deleted} entrées supprimées");

            // 3. Nettoyage des activités utilisateurs
            $deleted = $this->cleanupUserActivities();
            $totalDeleted += $deleted;
            Log::info("✅ user_activities: {$deleted} entrées supprimées");

            // 4. Nettoyage des événements de zones sécurisées
            $deleted = $this->cleanupSafeZoneEvents();
            $totalDeleted += $deleted;
            Log::info("✅ safe_zone_events: {$deleted} entrées supprimées");

            // 5. Nettoyage des cooldowns expirés
            $deleted = $this->cleanupExpiredCooldowns();
            $totalDeleted += $deleted;
            Log::info("✅ cooldowns: {$deleted} entrées expirées supprimées");

            // 6. Nettoyage des jobs et batches
            $deleted = $this->cleanupJobsAndBatches();
            $totalDeleted += $deleted;
            Log::info("✅ jobs/batches: {$deleted} entrées supprimées");

            // 7. Nettoyage des tokens expirés
            $deleted = $this->cleanupExpiredTokens();
            $totalDeleted += $deleted;
            Log::info("✅ personal_access_tokens: {$deleted} tokens expirés supprimés");

            // 8. Optimisation des tables après nettoyage
            $this->optimizeTables();

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("🎉 Nettoyage terminé avec succès ! {$totalDeleted} entrées supprimées en {$duration}s");

        } catch (\Exception $e) {
            Log::error('❌ Erreur lors du nettoyage automatique', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Nettoyage des positions GPS utilisateurs (PRIORITÉ MAXIMALE)
     */
    private function cleanupUserLocations(): int
    {
        $cutoffDate = Carbon::now()->subDays($this->retentionConfig['user_locations']);
        $totalDeleted = 0;

        do {
            $deleted = DB::table('user_locations')
                ->where('created_at', '<', $cutoffDate)
                ->limit($this->batchSize)
                ->delete();

            $totalDeleted += $deleted;

            // Pause pour éviter la surcharge
            if ($deleted > 0) {
                usleep(100000); // 100ms
            }
        } while ($deleted > 0);

        return $totalDeleted;
    }

    /**
     * Nettoyage des logs Telescope (TRÈS VOLUMINEUX)
     */
    private function cleanupTelescopeEntries(): int
    {
        $cutoffDate = Carbon::now()->subDays($this->retentionConfig['telescope_entries']);
        $totalDeleted = 0;

        // Supprimer d'abord les tags (clés étrangères) - approche compatible MySQL
        do {
            // Récupérer les UUIDs à supprimer par batch
            $uuids = DB::table('telescope_entries')
                ->where('created_at', '<', $cutoffDate)
                ->limit($this->batchSize)
                ->pluck('uuid');

            if ($uuids->isEmpty()) {
                break;
            }

            // Supprimer les tags correspondants
            DB::table('telescope_entries_tags')
                ->whereIn('entry_uuid', $uuids)
                ->delete();

            usleep(25000); // 25ms
        } while (!$uuids->isEmpty());

        // Puis supprimer les entrées principales
        do {
            $deleted = DB::table('telescope_entries')
                ->where('created_at', '<', $cutoffDate)
                ->limit($this->batchSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                usleep(50000); // 50ms
            }
        } while ($deleted > 0);

        return $totalDeleted;
    }

    /**
     * Nettoyage des activités utilisateurs
     */
    private function cleanupUserActivities(): int
    {
        $cutoffDate = Carbon::now()->subDays($this->retentionConfig['user_activities']);
        $totalDeleted = 0;

        do {
            $deleted = DB::table('user_activities')
                ->where('created_at', '<', $cutoffDate)
                ->limit($this->batchSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                usleep(50000); // 50ms
            }
        } while ($deleted > 0);

        return $totalDeleted;
    }

    /**
     * Nettoyage des événements de zones sécurisées
     */
    private function cleanupSafeZoneEvents(): int
    {
        $cutoffDate = Carbon::now()->subDays($this->retentionConfig['safe_zone_events']);
        $totalDeleted = 0;

        do {
            $deleted = DB::table('safe_zone_events')
                ->where('created_at', '<', $cutoffDate)
                ->limit($this->batchSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                usleep(50000); // 50ms
            }
        } while ($deleted > 0);

        return $totalDeleted;
    }

    /**
     * Nettoyage des cooldowns expirés
     */
    private function cleanupExpiredCooldowns(): int
    {
        $now = Carbon::now();
        $totalDeleted = 0;

        do {
            $deleted = DB::table('cooldowns')
                ->where('expires_at', '<', $now)
                ->limit($this->batchSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                usleep(25000); // 25ms
            }
        } while ($deleted > 0);

        return $totalDeleted;
    }

    /**
     * Nettoyage des jobs et batches
     */
    private function cleanupJobsAndBatches(): int
    {
        $totalDeleted = 0;

        // Jobs traités
        $cutoffJobs = Carbon::now()->subDays($this->retentionConfig['jobs'])->timestamp;
        do {
            $deleted = DB::table('jobs')
                ->where('created_at', '<', $cutoffJobs)
                ->limit($this->batchSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                usleep(25000);
            }
        } while ($deleted > 0);

        // Failed jobs
        $cutoffFailed = Carbon::now()->subDays($this->retentionConfig['failed_jobs']);
        do {
            $deleted = DB::table('failed_jobs')
                ->where('failed_at', '<', $cutoffFailed)
                ->limit($this->batchSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                usleep(25000);
            }
        } while ($deleted > 0);

        // Job batches
        $cutoffBatches = Carbon::now()->subDays($this->retentionConfig['job_batches'])->timestamp;
        do {
            $deleted = DB::table('job_batches')
                ->where('created_at', '<', $cutoffBatches)
                ->limit($this->batchSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                usleep(25000);
            }
        } while ($deleted > 0);

        return $totalDeleted;
    }

    /**
     * Nettoyage des tokens d'accès expirés
     */
    private function cleanupExpiredTokens(): int
    {
        $totalDeleted = 0;

        do {
            $deleted = DB::table('personal_access_tokens')
                ->where('expires_at', '<', Carbon::now())
                ->limit($this->batchSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                usleep(25000);
            }
        } while ($deleted > 0);

        return $totalDeleted;
    }

    /**
     * Optimisation des tables après nettoyage
     */
    private function optimizeTables(): void
    {
        $tables = [
            'user_locations',
            'telescope_entries',
            'user_activities',
            'safe_zone_events',
            'cooldowns'
        ];

        foreach ($tables as $table) {
            try {
                // Pour PostgreSQL
                if (config('database.default') === 'pgsql') {
                    DB::statement("VACUUM ANALYZE {$table}");
                }
                // Pour MySQL
                elseif (config('database.default') === 'mysql') {
                    DB::statement("OPTIMIZE TABLE {$table}");
                }
            } catch (\Exception $e) {
                Log::warning("Impossible d'optimiser la table {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * Gestion des échecs du job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ Échec du job de nettoyage automatique', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
