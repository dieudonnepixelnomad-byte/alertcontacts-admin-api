<?php

namespace App\Console\Commands;

use App\Models\SafeZone;
use App\Models\SafeZoneAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use MatanYadaev\EloquentSpatial\Objects\Point;

class CreateSafeZoneForUser extends Command
{
    protected $signature = 'zones:create-for-user {userId} {--contact=} {--name=Maison}';
    protected $description = 'Crée une SafeZone de test pour un user donné, avec contact optionnel assigné';

    public function handle(): int
    {
        $userId = (int) $this->argument('userId');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User #{$userId} introuvable.");
            return self::FAILURE;
        }

        $this->info("Création safe zone pour user#{$userId} ({$user->email})");

        $zone = SafeZone::create([
            'owner_id'  => $userId,
            'name'      => $this->option('name'),
            'icon'      => 'home',
            'center'    => new Point(4.0615151, 9.7604228),
            'radius_m'  => 150,
            'is_active' => true,
        ]);

        $this->info("✓ SafeZone créée → ID={$zone->id} name=\"{$zone->name}\"");

        $contactId = $this->option('contact');
        if ($contactId) {
            $contact = User::find((int) $contactId);
            if ($contact) {
                SafeZoneAssignment::create([
                    'safe_zone_id'        => $zone->id,
                    'assigned_user_id'    => $contact->id,
                    'assigned_by_user_id' => $userId,
                    'is_active'           => true,
                    'notify_entry'        => true,
                    'notify_exit'         => true,
                    'assigned_at'         => now(),
                    'accepted_at'         => now(),
                ]);
                $this->info("✓ Assignation → user#{$contact->id} ({$contact->email})");
            } else {
                $this->warn("Contact #{$contactId} introuvable, zone créée sans assignation.");
            }
        }

        $this->newLine();
        $this->line("→ Appelle GET /api/my-zones avec le token user#{$userId} pour vérifier.");

        return self::SUCCESS;
    }
}
