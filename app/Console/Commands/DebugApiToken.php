<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DebugApiToken extends Command
{
    protected $signature = 'debug:api-token {userId=1}';
    protected $description = 'Teste le token Sanctum + appel HTTP réel GET /api/my-zones';

    public function handle(): int
    {
        $userId = (int) $this->argument('userId');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User #{$userId} introuvable.");
            return self::FAILURE;
        }

        $this->info("=== User #{$userId}: {$user->email} ===");
        $this->newLine();

        // ── Tokens Sanctum ──────────────────────────────────────────────────
        $tokens = DB::table('personal_access_tokens')
            ->where('tokenable_id', $userId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $this->info("Tokens Sanctum ({$tokens->count()}) :");
        foreach ($tokens as $t) {
            $lastUsed = $t->last_used_at ?? 'jamais';
            $this->line("  ID={$t->id} name={$t->name} last_used={$lastUsed} created={$t->created_at}");
        }

        if ($tokens->isEmpty()) {
            $this->warn("⚠ Aucun token Sanctum pour user#{$userId} → Flutter ne peut pas s'authentifier");
            return self::SUCCESS;
        }

        // ── Test HTTP réel ──────────────────────────────────────────────────
        $this->newLine();
        $this->info("=== Test HTTP GET /api/my-zones ===");

        // Créer un nouveau token de test
        $plainToken = $user->createToken('debug-test')->plainTextToken;
        $this->line("Token de test créé: " . substr($plainToken, 0, 20) . "...");

        $baseUrl = config('app.url');
        $url = "{$baseUrl}/api/my-zones";
        $this->line("URL: {$url}");
        $this->newLine();

        try {
            $response = Http::withToken($plainToken)
                ->timeout(10)
                ->get($url);

            $this->line("Status HTTP: " . $response->status());
            $body = $response->json();
            $this->line("Réponse:");
            $this->line(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($response->successful()) {
                $zones = $body['data']['zones'] ?? [];
                $this->newLine();
                $this->info("✓ " . count($zones) . " zone(s) retournée(s)");
            } else {
                $this->error("✗ Erreur HTTP " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("✗ Exception HTTP: " . $e->getMessage());
        }

        // Nettoyer le token de test
        DB::table('personal_access_tokens')
            ->where('name', 'debug-test')
            ->where('tokenable_id', $userId)
            ->delete();

        $this->newLine();
        $this->line("Token de test nettoyé.");

        return self::SUCCESS;
    }
}
