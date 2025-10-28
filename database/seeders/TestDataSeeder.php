<?php

namespace Database\Seeders;

use App\Models\DangerZone;
use App\Models\Invitation;
use App\Models\Relationship;
use App\Models\SafeZone;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Créer quelques utilisateurs de test
        $users = User::factory(10)->create();

        // Créer des zones de danger
        DangerZone::create([
            'title' => 'Zone dangereuse Centre-ville',
            'description' => 'Zone signalée pour plusieurs agressions',
            'center' => DB::raw("ST_GeomFromText('POINT(2.3522 48.8566)', 4326)"),
            'radius_m' => 500,
            'severity' => 'high',
            'confirmations' => 5,
            'last_report_at' => now()->subDays(2),
            'reported_by' => $users->first()->id,
            'is_active' => true,
        ]);

        DangerZone::create([
            'title' => 'Zone de vol Gare',
            'description' => 'Pickpockets fréquents',
            'center' => DB::raw("ST_GeomFromText('POINT(2.3740 48.8448)', 4326)"),
            'radius_m' => 300,
            'severity' => 'med',
            'confirmations' => 3,
            'last_report_at' => now()->subDays(5),
            'reported_by' => $users->skip(1)->first()->id,
            'is_active' => true,
        ]);

        DangerZone::create([
            'title' => 'Accident fréquent',
            'description' => 'Carrefour accidentogène',
            'center' => DB::raw("ST_GeomFromText('POINT(2.3376 48.8606)', 4326)"),
            'radius_m' => 200,
            'severity' => 'low',
            'confirmations' => 1,
            'last_report_at' => now()->subDays(10),
            'reported_by' => $users->skip(2)->first()->id,
            'is_active' => true,
        ]);

        // Créer des zones de sécurité
        SafeZone::create([
            'owner_id' => $users->first()->id,
            'name' => 'Maison',
            'icon' => 'home',
            'center' => DB::raw("ST_GeomFromText('POINT(2.3522 48.8566)', 4326)"),
            'radius_m' => 100,
            'geom' => DB::raw("ST_GeomFromText('POLYGON((2.3512 48.8556, 2.3532 48.8556, 2.3532 48.8576, 2.3512 48.8576, 2.3512 48.8556))', 4326)"),
            'is_active' => true,
        ]);

        SafeZone::create([
            'owner_id' => $users->skip(1)->first()->id,
            'name' => 'École',
            'icon' => 'school',
            'center' => DB::raw("ST_GeomFromText('POINT(2.3376 48.8606)', 4326)"),
            'radius_m' => 200,
            'geom' => DB::raw("ST_GeomFromText('POLYGON((2.3366 48.8596, 2.3386 48.8596, 2.3386 48.8616, 2.3366 48.8616, 2.3366 48.8596))', 4326)"),
            'is_active' => true,
        ]);

        // Créer des relations
        for ($i = 0; $i < 5; $i++) {
            $user = $users->skip($i)->first();
            $contact = $users->skip($i + 1)->first();
            
            if ($user && $contact) {
                Relationship::create([
                    'user_id' => $user->id,
                    'contact_id' => $contact->id,
                    'status' => ['pending', 'accepted', 'refused'][rand(0, 2)],
                    'share_level' => ['realtime', 'alert_only', 'none'][rand(0, 2)],
                    'can_see_me' => rand(0, 1),
                    'accepted_at' => rand(0, 1) ? now()->subDays(rand(1, 30)) : null,
                ]);
            }
        }

        // Créer des invitations
        for ($i = 0; $i < 3; $i++) {
            $inviter = $users->skip($i)->first();
            
            if ($inviter) {
                Invitation::create([
                    'inviter_id' => $inviter->id,
                    'token' => Str::random(32),
                    'pin' => sprintf('%04d', rand(1000, 9999)),
                    'status' => ['pending', 'accepted', 'refused'][rand(0, 2)],
                    'default_share_level' => ['realtime', 'alert_only', 'none'][rand(0, 2)],
                    'expires_at' => now()->addDays(7),
                    'max_uses' => 1,
                    'used_count' => 0,
                    'inviter_name' => $inviter->name,
                    'message' => 'Rejoignez-moi sur AlertContact pour rester en sécurité !',
                ]);
            }
        }
    }
}