<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CleanupOldDataJob;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Planification des tâches
// Schedule::command('safezone:send-reminders')
//     ->everyFiveMinutes()
//     ->withoutOverlapping()
//     ->appendOutputTo(storage_path('logs/safezone-reminders.log'));

/**
 * 🧹 NETTOYAGE AUTOMATIQUE DES DONNÉES ANCIENNES
 *
 * Planification du job de nettoyage pour maintenir les performances
 * et éviter l'accumulation excessive de données dans les tables critiques.
 */

// Configuration du logging pour les schedules
$scheduleLogPath = storage_path('logs/scheduler.log');

// Fonction helper pour logger les événements de schedule
$logScheduleEvent = function (string $scheduleName, string $status) use ($scheduleLogPath) {
    $timestamp = now()->format('Y-m-d H:i:s');
    $memory = round(memory_get_usage(true) / 1024 / 1024, 2);
    $pid = getmypid();

    $logMessage = "[{$timestamp}] [{$status}] {$scheduleName} - PID: {$pid} - Memory: {$memory}MB" . PHP_EOL;

    // Créer le répertoire si nécessaire
    $logDir = dirname($scheduleLogPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Écrire dans le log
    file_put_contents($scheduleLogPath, $logMessage, FILE_APPEND | LOCK_EX);

    // Rotation du log si trop volumineux (> 10MB)
    if (file_exists($scheduleLogPath) && filesize($scheduleLogPath) > 10 * 1024 * 1024) {
        $backupPath = $scheduleLogPath . '.' . date('Y-m-d-H-i-s');
        rename($scheduleLogPath, $backupPath);

        // Garder seulement les 5 derniers fichiers de backup
        $backupFiles = glob(dirname($scheduleLogPath) . '/scheduler.log.*');
        if (count($backupFiles) > 5) {
            usort($backupFiles, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            // Supprimer les anciens backups
            for ($i = 5; $i < count($backupFiles); $i++) {
                unlink($backupFiles[$i]);
            }
        }
    }
};

// Nettoyage quotidien à 2h du matin (heure creuse)
Schedule::job(new CleanupOldDataJob())
    ->dailyAt('02:00')
    ->withoutOverlapping(120) // Timeout de 2h max
    ->onOneServer() // Exécution sur un seul serveur en cas de cluster
    ->appendOutputTo(storage_path('logs/cleanup-old-data.log'))
    ->description('Nettoyage automatique des données anciennes (positions GPS, logs, etc.)')
    ->before(function () use ($logScheduleEvent) {
        $logScheduleEvent('CleanupOldDataJob', 'STARTED');
    })
    ->after(function () use ($logScheduleEvent) {
        $logScheduleEvent('CleanupOldDataJob', 'COMPLETED');
    })
    ->onFailure(function () use ($logScheduleEvent) {
        $logScheduleEvent('CleanupOldDataJob', 'FAILED');
    });

// Nettoyage des cooldowns expirés toutes les heures (plus fréquent car moins lourd)
Schedule::call(function () use ($logScheduleEvent) {
    $logScheduleEvent('cleanup:cooldowns', 'STARTED');

    try {
        $deleted = \Illuminate\Support\Facades\DB::table('cooldowns')
            ->where('expires_at', '<', now())
            ->delete();

        Log::info("Cooldowns nettoyés: {$deleted} entrées supprimées");
        $logScheduleEvent('cleanup:cooldowns', 'COMPLETED');
    } catch (\Exception $e) {
        Log::error("Erreur nettoyage cooldowns: " . $e->getMessage());
        $logScheduleEvent('cleanup:cooldowns', 'FAILED');
        throw $e;
    }
})->hourly()
    ->name('cleanup-expired-cooldowns')
    ->withoutOverlapping(10)
    ->description('Nettoyage des cooldowns expirés');

// Nettoyage des tokens expirés toutes les 6 heures
Schedule::call(function () use ($logScheduleEvent) {
    $logScheduleEvent('cleanup:tokens', 'STARTED');

    try {
        $deleted = \Illuminate\Support\Facades\DB::table('personal_access_tokens')
            ->where('expires_at', '<', now())
            ->delete();

        Log::info("Tokens expirés nettoyés: {$deleted} entrées supprimées");
        $logScheduleEvent('cleanup:tokens', 'COMPLETED');
    } catch (\Exception $e) {
        Log::error("Erreur nettoyage tokens: " . $e->getMessage());
        $logScheduleEvent('cleanup:tokens', 'FAILED');
        throw $e;
    }
})->everySixHours()
    ->name('cleanup-expired-tokens')
    ->withoutOverlapping(5)
    ->description('Nettoyage des tokens d\'accès expirés');

// Nettoyage léger des logs Telescope toutes les 4 heures (très critique)
Schedule::call(function () use ($logScheduleEvent) {
    $logScheduleEvent('telescope:prune', 'STARTED');

    try {
        $cutoffDate = now()->subHours(48); // Garder seulement 48h en continu

        // Supprimer par petits lots pour éviter les timeouts
        $deleted = 0;
        do {
            $batch = \Illuminate\Support\Facades\DB::table('telescope_entries')
                ->where('created_at', '<', $cutoffDate)
                ->limit(500)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0 && $deleted < 5000); // Max 5000 par exécution

        Log::info("Nettoyage léger Telescope: {$deleted} entrées supprimées");
        $logScheduleEvent('telescope:prune', 'COMPLETED');
    } catch (\Exception $e) {
        Log::error("Erreur nettoyage Telescope: " . $e->getMessage());
        $logScheduleEvent('telescope:prune', 'FAILED');
        throw $e;
    }
})->everyFourHours()
    ->name('cleanup-telescope-light')
    ->withoutOverlapping(30)
    ->description('Nettoyage léger des logs Telescope (48h+)');

// Statistiques hebdomadaires du nettoyage
Schedule::command('cleanup:old-data --stats')
    ->weekly()
    ->sundays()
    ->at('08:00')
    ->appendOutputTo(storage_path('logs/cleanup-stats.log'))
    ->description('Rapport hebdomadaire des statistiques de nettoyage')
    ->before(function () use ($logScheduleEvent) {
        $logScheduleEvent('cleanup:stats', 'STARTED');
    })
    ->after(function () use ($logScheduleEvent) {
        $logScheduleEvent('cleanup:stats', 'COMPLETED');
    })
    ->onFailure(function () use ($logScheduleEvent) {
        $logScheduleEvent('cleanup:stats', 'FAILED');
    });
