<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RestartWorkersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:restart-workers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Redémarre tous les workers de file d\'attente';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Démarrage du redémarrage des workers...');
        
        try {
            // Utiliser le script existant restart_jobs.sh
            $scriptPath = base_path('restart_jobs.sh');
            
            if (!file_exists($scriptPath)) {
                $this->error('Le script restart_jobs.sh n\'existe pas');
                Log::error('Script restart_jobs.sh introuvable');
                return Command::FAILURE;
            }
            
            // Rendre le script exécutable si nécessaire
            if (!is_executable($scriptPath)) {
                chmod($scriptPath, 0755);
            }
            
            $this->info('Exécution du script restart_jobs.sh...');
            
            // Exécuter le script
            $result = Process::run("bash {$scriptPath}");
            
            if ($result->successful()) {
                $this->info('Workers redémarrés avec succès');
                Log::info('Workers redémarrés via commande artisan', [
                    'output' => $result->output(),
                    'exit_code' => $result->exitCode()
                ]);
                return Command::SUCCESS;
            } else {
                $this->error('Échec du redémarrage des workers');
                $this->error('Erreur: ' . $result->errorOutput());
                
                Log::error('Échec du redémarrage des workers', [
                    'output' => $result->output(),
                    'error' => $result->errorOutput(),
                    'exit_code' => $result->exitCode()
                ]);
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error('Exception lors du redémarrage: ' . $e->getMessage());
            Log::error('Exception lors du redémarrage des workers', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}