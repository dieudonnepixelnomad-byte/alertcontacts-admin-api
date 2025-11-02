<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Jobs\CleanupOldDataJob;
use Carbon\Carbon;

/**
 * Commande pour nettoyer les données anciennes
 * 
 * Usage:
 * php artisan cleanup:old-data
 * php artisan cleanup:old-data --dry-run
 * php artisan cleanup:old-data --stats
 */
class CleanupOldDataCommand extends Command
{
    /**
     * Signature de la commande
     */
    protected $signature = 'cleanup:old-data 
                            {--dry-run : Afficher ce qui serait supprimé sans supprimer}
                            {--stats : Afficher les statistiques des tables}
                            {--force : Forcer l\'exécution sans confirmation}';

    /**
     * Description de la commande
     */
    protected $description = 'Nettoie les données anciennes des tables critiques pour maintenir les performances';

    /**
     * Configuration des rétentions (en jours)
     */
    private array $retentionConfig = [
        'user_locations' => 30,
        'telescope_entries' => 7,
        'user_activities' => 90,
        'safe_zone_events' => 180,
        'jobs' => 7,
        'failed_jobs' => 30,
        'job_batches' => 30,
    ];

    /**
     * Exécution de la commande
     */
    public function handle(): int
    {
        $this->info('🧹 Nettoyage automatique des données anciennes - AlertContact');
        $this->newLine();

        // Affichage des statistiques uniquement
        if ($this->option('stats')) {
            $this->showTableStatistics();
            return 0;
        }

        // Mode dry-run
        if ($this->option('dry-run')) {
            $this->info('🔍 Mode DRY-RUN - Aucune donnée ne sera supprimée');
            $this->showWhatWouldBeDeleted();
            return 0;
        }

        // Confirmation avant suppression
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  Êtes-vous sûr de vouloir supprimer les données anciennes ?')) {
                $this->info('❌ Opération annulée');
                return 1;
            }
        }

        // Exécution du nettoyage
        $this->info('🚀 Démarrage du nettoyage...');
        $this->newLine();

        try {
            // Dispatch du job
            CleanupOldDataJob::dispatch();
            
            $this->info('✅ Job de nettoyage lancé avec succès !');
            $this->info('📊 Consultez les logs pour suivre le progrès : storage/logs/laravel.log');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors du lancement du job : ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Affiche les statistiques des tables
     */
    private function showTableStatistics(): void
    {
        $this->info('📊 STATISTIQUES DES TABLES CRITIQUES');
        $this->newLine();

        $tables = [
            'user_locations' => 'Positions GPS utilisateurs',
            'telescope_entries' => 'Logs Telescope (debug)',
            'user_activities' => 'Activités utilisateurs',
            'safe_zone_events' => 'Événements zones sécurisées',
            'cooldowns' => 'Cooldowns notifications',
            'jobs' => 'Jobs en queue',
            'failed_jobs' => 'Jobs échoués',
            'job_batches' => 'Batches de jobs',
            'personal_access_tokens' => 'Tokens d\'accès API'
        ];

        $totalSize = 0;

        foreach ($tables as $table => $description) {
            try {
                $count = DB::table($table)->count();
                $size = $this->getTableSize($table);
                $totalSize += $size;

                $this->line(sprintf(
                    '  %-25s %s entrées (%s)',
                    $table . ':',
                    number_format($count),
                    $this->formatBytes($size)
                ));
            } catch (\Exception $e) {
                $this->line("  {$table}: ❌ Erreur - " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("💾 Taille totale estimée : " . $this->formatBytes($totalSize));
        $this->newLine();

        // Affichage des données anciennes
        $this->showOldDataCounts();
    }

    /**
     * Affiche ce qui serait supprimé en mode dry-run
     */
    private function showWhatWouldBeDeleted(): void
    {
        $this->newLine();
        $this->info('📋 DONNÉES QUI SERAIENT SUPPRIMÉES :');
        $this->newLine();

        $totalToDelete = 0;

        foreach ($this->retentionConfig as $table => $days) {
            try {
                $cutoffDate = Carbon::now()->subDays($days);
                
                $count = match($table) {
                    'jobs' => DB::table($table)->where('created_at', '<', $cutoffDate->timestamp)->count(),
                    'job_batches' => DB::table($table)->where('created_at', '<', $cutoffDate->timestamp)->count(),
                    'failed_jobs' => DB::table($table)->where('failed_at', '<', $cutoffDate)->count(),
                    default => DB::table($table)->where('created_at', '<', $cutoffDate)->count()
                };

                $totalToDelete += $count;

                $this->line(sprintf(
                    '  %-25s %s entrées (> %d jours)',
                    $table . ':',
                    number_format($count),
                    $days
                ));
            } catch (\Exception $e) {
                $this->line("  {$table}: ❌ Erreur - " . $e->getMessage());
            }
        }

        // Cooldowns expirés
        try {
            $expiredCooldowns = DB::table('cooldowns')
                ->where('expires_at', '<', Carbon::now())
                ->count();
            
            $totalToDelete += $expiredCooldowns;
            
            $this->line(sprintf(
                '  %-25s %s entrées (expirées)',
                'cooldowns:',
                number_format($expiredCooldowns)
            ));
        } catch (\Exception $e) {
            $this->line("  cooldowns: ❌ Erreur - " . $e->getMessage());
        }

        // Tokens expirés
        try {
            $expiredTokens = DB::table('personal_access_tokens')
                ->where('expires_at', '<', Carbon::now())
                ->count();
            
            $totalToDelete += $expiredTokens;
            
            $this->line(sprintf(
                '  %-25s %s entrées (expirées)',
                'personal_access_tokens:',
                number_format($expiredTokens)
            ));
        } catch (\Exception $e) {
            $this->line("  personal_access_tokens: ❌ Erreur - " . $e->getMessage());
        }

        $this->newLine();
        $this->info("🗑️  Total à supprimer : " . number_format($totalToDelete) . " entrées");
    }

    /**
     * Affiche le nombre de données anciennes par table
     */
    private function showOldDataCounts(): void
    {
        $this->info('🕒 DONNÉES ANCIENNES PAR TABLE :');
        $this->newLine();

        foreach ($this->retentionConfig as $table => $days) {
            try {
                $cutoffDate = Carbon::now()->subDays($days);
                
                $oldCount = match($table) {
                    'jobs' => DB::table($table)->where('created_at', '<', $cutoffDate->timestamp)->count(),
                    'job_batches' => DB::table($table)->where('created_at', '<', $cutoffDate->timestamp)->count(),
                    'failed_jobs' => DB::table($table)->where('failed_at', '<', $cutoffDate)->count(),
                    default => DB::table($table)->where('created_at', '<', $cutoffDate)->count()
                };

                $totalCount = DB::table($table)->count();
                $percentage = $totalCount > 0 ? round(($oldCount / $totalCount) * 100, 1) : 0;

                $this->line(sprintf(
                    '  %-25s %s / %s (%s%%) - Rétention: %d jours',
                    $table . ':',
                    number_format($oldCount),
                    number_format($totalCount),
                    $percentage,
                    $days
                ));
            } catch (\Exception $e) {
                $this->line("  {$table}: ❌ Erreur - " . $e->getMessage());
            }
        }

        // Données expirées
        $this->newLine();
        $this->info('⏰ DONNÉES EXPIRÉES :');
        $this->newLine();

        // Cooldowns expirés
        try {
            $expiredCooldowns = DB::table('cooldowns')
                ->where('expires_at', '<', Carbon::now())
                ->count();
            $totalCooldowns = DB::table('cooldowns')->count();
            $percentage = $totalCooldowns > 0 ? round(($expiredCooldowns / $totalCooldowns) * 100, 1) : 0;

            $this->line(sprintf(
                '  %-25s %s / %s (%s%%) - Expirés',
                'cooldowns:',
                number_format($expiredCooldowns),
                number_format($totalCooldowns),
                $percentage
            ));
        } catch (\Exception $e) {
            $this->line("  cooldowns: ❌ Erreur - " . $e->getMessage());
        }

        // Tokens expirés
        try {
            $expiredTokens = DB::table('personal_access_tokens')
                ->where('expires_at', '<', Carbon::now())
                ->count();
            $totalTokens = DB::table('personal_access_tokens')->count();
            $percentage = $totalTokens > 0 ? round(($expiredTokens / $totalTokens) * 100, 1) : 0;

            $this->line(sprintf(
                '  %-25s %s / %s (%s%%) - Expirés',
                'personal_access_tokens:',
                number_format($expiredTokens),
                number_format($totalTokens),
                $percentage
            ));
        } catch (\Exception $e) {
            $this->line("  personal_access_tokens: ❌ Erreur - " . $e->getMessage());
        }
    }

    /**
     * Obtient la taille approximative d'une table
     */
    private function getTableSize(string $table): int
    {
        try {
            $dbType = config('database.default');
            
            if ($dbType === 'mysql') {
                $result = DB::select("
                    SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ?
                ", [$table]);
                
                return isset($result[0]) ? (int)($result[0]->size_mb * 1024 * 1024) : 0;
            }
            
            if ($dbType === 'pgsql') {
                $result = DB::select("
                    SELECT pg_total_relation_size(?) AS size_bytes
                ", [$table]);
                
                return isset($result[0]) ? (int)$result[0]->size_bytes : 0;
            }
            
            // Estimation basique pour SQLite ou autres
            $count = DB::table($table)->count();
            return $count * 1000; // Estimation 1KB par entrée
            
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Formate les bytes en unités lisibles
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}