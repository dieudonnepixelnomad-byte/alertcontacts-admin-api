<?php

namespace App\Console\Commands;

use App\Models\SafeZone;
use App\Models\SafeZoneAssignment;
use App\Models\User;
use App\Models\UserLocation;
use App\Services\GeoprocessingService;
use Illuminate\Console\Command;
use MatanYadaev\EloquentSpatial\Objects\Point;

class TestZoneExit extends Command
{
    protected $signature = 'test:zone-exit';
    protected $description = 'Crée une SafeZone fictive et simule une sortie de zone pour user#2 → notif vers user#1';

    public function handle(GeoprocessingService $geo): int
    {
        $user1 = User::find(1);
        $user2 = User::find(2);

        if (!$user1 || !$user2) {
            $this->error('User#1 ou User#2 introuvable.');
            return self::FAILURE;
        }

        $this->info("User1 [{$user1->id}]: {$user1->email} | FCM: " . ($user1->fcm_token ? '✓ OK' : '⚠ MISSING'));
        $this->info("User2 [{$user2->id}]: {$user2->email} | FCM: " . ($user2->fcm_token ? '✓ OK' : '⚠ MISSING'));
        $this->newLine();

        // ── Zone fictive centrée sur la dernière position connue de user#2 ──
        $zoneLat    = 4.0615151;
        $zoneLng    = 9.7604228;
        $zoneRadius = 200; // mètres

        SafeZone::where('owner_id', 1)->where('name', 'Zone Test Sortie')->delete();

        $zone = SafeZone::create([
            'owner_id'  => 1,
            'name'      => 'Zone Test Sortie',
            'icon'      => 'home',
            'center'    => new Point($zoneLat, $zoneLng),
            'radius_m'  => $zoneRadius,
            'is_active' => true,
        ]);

        $this->info("✓ SafeZone créée  → ID={$zone->id}, centre=({$zoneLat},{$zoneLng}), rayon={$zoneRadius}m");

        // ── Assignation user#2 → zone (notify_exit activé) ─────────────────
        // Aucun SafeZoneEvent précédent → système suppose user#2 était DEDANS
        SafeZoneAssignment::where('safe_zone_id', $zone->id)->delete();

        $assignment = SafeZoneAssignment::create([
            'safe_zone_id'        => $zone->id,
            'assigned_user_id'    => 2,
            'assigned_by_user_id' => 1,
            'is_active'           => true,
            'notify_entry'        => true,
            'notify_exit'         => true,
            'assigned_at'         => now(),
            'accepted_at'         => now(),
        ]);

        $this->info("✓ Assignment créé → ID={$assignment->id}, notify_exit=true");

        // ── Nouvelle position de user#2 HORS zone (~1.2 km au nord) ─────────
        $outsideLat = $zoneLat + 0.011;
        $outsideLng = $zoneLng;
        $approxDist = (int) round(abs($outsideLat - $zoneLat) * 111000);

        $location = UserLocation::create([
            'user_id'            => 2,
            'latitude'           => $outsideLat,
            'longitude'          => $outsideLng,
            'accuracy'           => 5.0,
            'speed'              => null,
            'heading'            => null,
            'captured_at_device' => now(),
            'source'             => 'gps',
            'foreground'         => true,
            'battery_level'      => 75,
        ]);

        $this->info("✓ UserLocation créée → ID={$location->id}, lat={$outsideLat} (~{$approxDist}m du centre → OUTSIDE)");
        $this->newLine();

        // ── Processing synchrone ─────────────────────────────────────────────
        $this->comment('▶ GeoprocessingService::processLocation() ...');
        $geo->processLocation($location);

        $this->newLine();
        $this->info('✓ Processing terminé.');
        $this->line('→ Vérifier laravel.log + notification FCM reçue sur le device user#1');

        return self::SUCCESS;
    }
}
