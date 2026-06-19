<?php

namespace App\Console\Commands;

use App\Models\SafeZone;
use App\Models\SafeZoneAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugZones extends Command
{
    protected $signature = 'debug:zones';
    protected $description = 'Debug: affiche les zones en DB + simule la réponse API /my-zones';

    public function handle(): int
    {
        // ── Raw DB ──────────────────────────────────────────────────────────
        $this->info('=== safe_zones table ===');
        $rows = DB::select('SELECT id, owner_id, name, is_active, radius_m, icon FROM safe_zones');
        if (empty($rows)) {
            $this->warn('Aucune zone en base.');
        } else {
            foreach ($rows as $r) {
                $this->line("  ID={$r->id} owner={$r->owner_id} name={$r->name} active={$r->is_active} radius={$r->radius_m} icon={$r->icon}");
            }
        }

        $this->newLine();
        $this->info('=== safe_zone_assignments table ===');
        $assigns = DB::select('SELECT id, safe_zone_id, assigned_user_id, is_active, notify_exit FROM safe_zone_assignments');
        if (empty($assigns)) {
            $this->warn('Aucune assignation.');
        } else {
            foreach ($assigns as $a) {
                $this->line("  ID={$a->id} zone={$a->safe_zone_id} user={$a->assigned_user_id} active={$a->is_active} notify_exit={$a->notify_exit}");
            }
        }

        // ── Simulation réponse API pour user#1 ──────────────────────────────
        $this->newLine();
        $this->info('=== Simulation GET /my-zones pour user#1 ===');

        $zones = SafeZone::where('owner_id', 1)
            ->with(['assignments' => fn($q) => $q->where('is_active', true)])
            ->get();

        $this->line("  Zones trouvées: " . $zones->count());

        foreach ($zones as $zone) {
            $this->line("  Zone ID={$zone->id} name={$zone->name}");
            $this->line("    center: lat=" . ($zone->center?->latitude ?? 'NULL') . " lng=" . ($zone->center?->longitude ?? 'NULL'));
            $this->line("    radius_m={$zone->radius_m}");
            $this->line("    is_active={$zone->is_active}");
            $this->line("    isCircle=" . ($zone->isCircle() ? 'true' : 'false'));
            $this->line("    assignments count=" . $zone->assignments->count());

            // Ce que l'API renverrait
            $memberIds = $zone->assignments->pluck('assigned_user_id')->map(fn($id) => (string) $id)->values()->all();
            $this->line("    member_ids=" . json_encode($memberIds));

            $apiPayload = [
                'id'           => $zone->id,
                'type'         => 'safe',
                'name'         => $zone->name,
                'center'       => $zone->center ? ['lat' => $zone->center->latitude, 'lng' => $zone->center->longitude] : null,
                'radius_meters'=> $zone->radius_m,
                'icon_key'     => $zone->icon,
                'member_ids'   => $memberIds,
                'created_at'   => $zone->created_at->toISOString(),
                'updated_at'   => $zone->updated_at->toISOString(),
            ];
            $this->line("    JSON payload: " . json_encode($apiPayload));
        }

        return self::SUCCESS;
    }
}
