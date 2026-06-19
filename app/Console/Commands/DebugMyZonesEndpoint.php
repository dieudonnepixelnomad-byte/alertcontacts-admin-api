<?php

namespace App\Console\Commands;

use App\Models\DangerZone;
use App\Models\SafeZone;
use Illuminate\Console\Command;

class DebugMyZonesEndpoint extends Command
{
    protected $signature = 'debug:my-zones-endpoint';
    protected $description = 'Simule exactement la réponse de GET /my-zones pour user#1 et identifie les erreurs';

    public function handle(): int
    {
        $this->info('=== Simulation exacte de AuthController::getMyZones pour user#1 ===');
        $this->newLine();

        try {
            // Safe zones
            $safeZones = SafeZone::where('owner_id', 1)
                ->with(['assignments' => fn($q) => $q->where('is_active', true)])
                ->get()
                ->map(function ($zone) {
                    $data = [
                        'id' => $zone->id,
                        'type' => 'safe',
                        'name' => $zone->name,
                        'description' => null,
                        'center' => [
                            'lat' => $zone->center->latitude,
                            'lng' => $zone->center->longitude,
                        ],
                        'radius_meters' => $zone->radius_m,
                        'icon_key' => $zone->icon,
                        'address' => null,
                        'member_ids' => $zone->assignments
                            ->pluck('assigned_user_id')
                            ->map(fn($id) => (string) $id)
                            ->values()
                            ->all(),
                        'created_at' => $zone->created_at->toISOString(),
                        'updated_at' => $zone->updated_at->toISOString(),
                    ];
                    return $data;
                });

            $this->info("Safe zones pour user#1: " . $safeZones->count());

            // Danger zones
            $this->newLine();
            $this->info("DangerZones reported_by=1:");
            $dangerCount = DangerZone::where('reported_by', 1)->count();
            $this->line("  Count: $dangerCount");

            if ($dangerCount > 0) {
                DangerZone::where('reported_by', 1)->get()->each(function ($zone) {
                    $this->line("  DangerZone ID={$zone->id} last_report_at=" . ($zone->last_report_at ? $zone->last_report_at->toISOString() : 'NULL'));
                });
            }

            // Danger zones mapping (peut throw si last_report_at est null)
            $this->newLine();
            $this->info("Mapping des DangerZones...");
            try {
                $dangerZones = DangerZone::where('reported_by', 1)->get()->map(function ($zone) {
                    return [
                        'id' => $zone->id,
                        'type' => 'danger',
                        'title' => $zone->title,
                        'description' => $zone->description,
                        'center' => [
                            'lat' => $zone->center_lat,
                            'lng' => $zone->center_lng,
                        ],
                        'radius_meters' => $zone->radius_m,
                        'severity' => $zone->severity,
                        'confirmations' => $zone->confirmations,
                        'last_report_at' => $zone->last_report_at->toISOString(),
                        'created_at' => $zone->created_at->toISOString(),
                        'updated_at' => $zone->updated_at->toISOString(),
                    ];
                });
                $this->info("✓ DangerZones mappées sans erreur: " . $dangerZones->count());
            } catch (\Throwable $e) {
                $this->error("✗ ERREUR dans le mapping DangerZones: " . $e->getMessage());
                $this->line("  → L'endpoint /my-zones retournerait un 500!");
                return self::FAILURE;
            }

            $allZones = $safeZones->concat($dangerZones)->sortByDesc('created_at')->values();

            $this->newLine();
            $this->info("=== Réponse JSON finale (/my-zones) ===");
            $json = json_encode([
                'success' => true,
                'data' => [
                    'zones' => $allZones,
                    'stats' => [
                        'total' => $allZones->count(),
                        'safe_zones' => $safeZones->count(),
                        'danger_zones' => $dangerZones->count(),
                    ]
                ]
            ], JSON_PRETTY_PRINT);
            $this->line($json);

        } catch (\Throwable $e) {
            $this->error("ERREUR GÉNÉRALE: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
