<?php

namespace App\Console\Commands;

use App\Jobs\SendSafeZoneExitReminders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendSafeZoneReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'safezone:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie les rappels périodiques pour les alertes de sortie de zone de sécurité';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Démarrage de l\'envoi des rappels de zone de sécurité...');
            
            // Dispatcher le job pour envoyer les rappels
            SendSafeZoneExitReminders::dispatch();
            
            $this->info('Job de rappels de zone de sécurité dispatché avec succès.');
            
            Log::info('Commande safezone:send-reminders exécutée avec succès');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Erreur lors de l\'envoi des rappels: ' . $e->getMessage());
            
            Log::error('Erreur dans la commande safezone:send-reminders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }
}