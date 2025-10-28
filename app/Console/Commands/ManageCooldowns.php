<?php

namespace App\Console\Commands;

use App\Services\CooldownService;
use Illuminate\Console\Command;

class ManageCooldowns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cooldown:manage 
                            {action : Action à effectuer (clear, stats, remove, list)}
                            {--user= : ID utilisateur spécifique}
                            {--zone= : ID zone spécifique}
                            {--type= : Type de cooldown (danger_zone_alert, safe_zone_entry, etc.)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gérer les cooldowns des notifications (clear, stats, remove)';

    private CooldownService $cooldownService;

    public function __construct(CooldownService $cooldownService)
    {
        parent::__construct();
        $this->cooldownService = $cooldownService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'clear':
                return $this->clearCooldowns();
            case 'stats':
                return $this->showStats();
            case 'remove':
                return $this->removeSpecificCooldown();
            case 'list':
                return $this->listCooldowns();
            default:
                $this->error("Action non reconnue: {$action}");
                $this->info("Actions disponibles: clear, stats, remove, list");
                return 1;
        }
    }

    /**
     * Effacer tous les cooldowns ou des cooldowns spécifiques
     */
    private function clearCooldowns(): int
    {
        $user = $this->option('user');
        $zone = $this->option('zone');
        $type = $this->option('type');

        if ($user || $zone || $type) {
            return $this->clearSpecificCooldowns($user, $zone, $type);
        }

        // Effacer tous les cooldowns
        if (!$this->confirm('Êtes-vous sûr de vouloir effacer TOUS les cooldowns ?')) {
            $this->info('Opération annulée.');
            return 0;
        }

        $cleaned = $this->cooldownService->cleanupExpired();
        $this->info("Cooldowns expirés nettoyés: {$cleaned}");

        // Pour effacer TOUS les cooldowns (même actifs), on utiliserait Redis directement
        // Mais c'est dangereux, donc on ne le fait que sur confirmation explicite
        if ($this->confirm('Voulez-vous aussi effacer les cooldowns ACTIFS ? (ATTENTION: Cela peut causer du spam de notifications)')) {
            $stats = $this->cooldownService->getStats();
            $this->clearAllActiveCooldowns();
            $this->warn("TOUS les cooldowns ont été effacés ({$stats['total_cooldowns']} au total)");
        }

        return 0;
    }

    /**
     * Effacer des cooldowns spécifiques
     */
    private function clearSpecificCooldowns(?string $user, ?string $zone, ?string $type): int
    {
        $patterns = [];

        if ($type && $user && $zone) {
            // Pattern très spécifique
            $key = "{$type}_{$user}_{$zone}";
            $patterns[] = $key;
        } elseif ($type && $user) {
            // Tous les cooldowns d'un type pour un utilisateur
            $patterns[] = "{$type}_{$user}_*";
        } elseif ($type && $zone) {
            // Tous les cooldowns d'un type pour une zone
            $patterns[] = "{$type}_*_{$zone}";
        } elseif ($user) {
            // Tous les cooldowns d'un utilisateur
            $patterns[] = "*_{$user}_*";
        } elseif ($zone) {
            // Tous les cooldowns d'une zone
            $patterns[] = "*_{$zone}";
        } elseif ($type) {
            // Tous les cooldowns d'un type
            $patterns[] = "{$type}_*";
        }

        $removed = 0;
        foreach ($patterns as $pattern) {
            if (strpos($pattern, '*') !== false) {
                // Pattern avec wildcard - on doit lister et supprimer
                $removed += $this->removeByPattern($pattern);
            } else {
                // Clé exacte
                if ($this->cooldownService->removeCooldown($pattern)) {
                    $removed++;
                    $this->info("Cooldown supprimé: {$pattern}");
                }
            }
        }

        $this->info("Total cooldowns supprimés: {$removed}");
        return 0;
    }

    /**
     * Supprimer un cooldown spécifique
     */
    private function removeSpecificCooldown(): int
    {
        $user = $this->option('user');
        $zone = $this->option('zone');
        $type = $this->option('type') ?? 'danger_zone_alert';

        if (!$user || !$zone) {
            $this->error('Les options --user et --zone sont requises pour supprimer un cooldown spécifique.');
            $this->info('Exemple: php artisan cooldown:manage remove --user=1 --zone=6 --type=danger_zone_alert');
            return 1;
        }

        $key = "{$type}_{$user}_{$zone}";
        
        if ($this->cooldownService->removeCooldown($key)) {
            $this->info("Cooldown supprimé: {$key}");
        } else {
            $this->warn("Aucun cooldown trouvé pour: {$key}");
        }

        return 0;
    }

    /**
     * Afficher les statistiques des cooldowns
     */
    private function showStats(): int
    {
        $stats = $this->cooldownService->getStats();

        $this->info('=== Statistiques des Cooldowns ===');
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Total cooldowns', $stats['total_cooldowns']],
                ['Cooldowns actifs', $stats['active_cooldowns']],
                ['Cooldowns expirés', $stats['expired_cooldowns']],
            ]
        );

        if (isset($stats['error'])) {
            $this->error("Erreur: {$stats['error']}");
        }

        return 0;
    }

    /**
     * Lister les cooldowns actifs avec leurs détails
     */
    private function listCooldowns(): int
    {
        try {
            $redis = app('redis');
            $keys = $redis->keys('cooldown:*');
            
            if (empty($keys)) {
                $this->info('Aucun cooldown actif trouvé.');
                return 0;
            }

            $cooldowns = [];
            foreach ($keys as $key) {
                $cleanKey = str_replace('cooldown:', '', $key);
                $ttl = $redis->ttl($key);
                
                if ($ttl > 0) {
                    // Analyser la clé pour extraire les informations
                    $parts = explode('_', $cleanKey);
                    $type = $parts[0] ?? 'unknown';
                    $userId = $parts[1] ?? 'unknown';
                    $zoneId = $parts[2] ?? 'unknown';
                    
                    $cooldowns[] = [
                        'Type' => $type,
                        'User ID' => $userId,
                        'Zone ID' => $zoneId,
                        'Temps restant' => $this->formatDuration($ttl),
                        'Clé complète' => $cleanKey
                    ];
                }
            }

            if (empty($cooldowns)) {
                $this->info('Aucun cooldown actif trouvé (tous expirés).');
                return 0;
            }

            $this->info('=== Cooldowns Actifs ===');
            $this->table(
                ['Type', 'User ID', 'Zone ID', 'Temps restant', 'Clé complète'],
                $cooldowns
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Erreur lors de la liste des cooldowns: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Formater une durée en secondes en format lisible
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return "{$minutes}m {$remainingSeconds}s";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }

    /**
     * Supprimer tous les cooldowns actifs (DANGEREUX)
     */
    private function clearAllActiveCooldowns(): void
    {
        // Cette méthode utilise Redis directement pour supprimer tous les cooldowns
        // Elle est marquée comme dangereuse car elle peut causer du spam de notifications
        
        try {
            $redis = app('redis');
            $keys = $redis->keys('cooldown:*');
            
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Exception $e) {
            $this->error("Erreur lors de la suppression: {$e->getMessage()}");
        }
    }

    /**
     * Supprimer par pattern (avec wildcards)
     */
    private function removeByPattern(string $pattern): int
    {
        try {
            $redis = app('redis');
            $searchPattern = 'cooldown:' . $pattern;
            $keys = $redis->keys($searchPattern);
            
            $removed = 0;
            foreach ($keys as $key) {
                // Extraire la clé sans le préfixe 'cooldown:'
                $cleanKey = str_replace('cooldown:', '', $key);
                if ($this->cooldownService->removeCooldown($cleanKey)) {
                    $removed++;
                    $this->info("Cooldown supprimé: {$cleanKey}");
                }
            }
            
            return $removed;
        } catch (\Exception $e) {
            $this->error("Erreur lors de la recherche par pattern: {$e->getMessage()}");
            return 0;
        }
    }
}
