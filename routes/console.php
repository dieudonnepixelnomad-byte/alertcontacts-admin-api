<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CleanupOldDataJob;

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

// Nettoyage quotidien à 2h du matin (heure creuse)
Schedule::job(new CleanupOldDataJob())
    ->dailyAt('02:00')
    ->withoutOverlapping(120) // Timeout de 2h max
    ->onOneServer() // Exécution sur un seul serveur en cas de cluster
    ->appendOutputTo(storage_path('logs/cleanup-old-data.log'))
    ->emailOutputOnFailure(config('mail.admin_email', 'admin@alertcontact.com'))
    ->description('Nettoyage automatique des données anciennes (positions GPS, logs, etc.)');

// Nettoyage des cooldowns expirés toutes les heures (plus fréquent car moins lourd)
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('cooldowns')
        ->where('expires_at', '<', now())
        ->delete();
})->hourly()
    ->name('cleanup-expired-cooldowns')
    ->withoutOverlapping(10)
    ->description('Nettoyage des cooldowns expirés');

// Nettoyage des tokens expirés toutes les 6 heures
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('personal_access_tokens')
        ->where('expires_at', '<', now())
        ->delete();
})->everySixHours()
    ->name('cleanup-expired-tokens')
    ->withoutOverlapping(5)
    ->description('Nettoyage des tokens d\'accès expirés');

// Nettoyage léger des logs Telescope toutes les 4 heures (très critique)
Schedule::call(function () {
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
    
    \Illuminate\Support\Facades\Log::info("Nettoyage léger Telescope: {$deleted} entrées supprimées");
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
    ->description('Rapport hebdomadaire des statistiques de nettoyage');
