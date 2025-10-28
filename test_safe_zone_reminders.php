<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\User;
use App\Models\SafeZone;
use App\Models\SafeZoneEvent;
use App\Models\PendingSafeZoneAlert;
use App\Jobs\SendSafeZoneExitReminders;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\LineString;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Test du système de rappels de zone de sécurité ===\n\n";

try {
    // 1. Vérifier qu'il y a au moins un utilisateur
    echo "1. Vérification des utilisateurs existants...\n";
    $userCount = User::count();
    echo "   Nombre d'utilisateurs: {$userCount}\n";
    
    if ($userCount === 0) {
        echo "   Création d'un utilisateur de test...\n";
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test_' . time() . '@example.com',
            'password' => bcrypt('password'),
            'fcm_token' => 'test_fcm_token_' . time(),
            'email_verified_at' => now(),
        ]);
        echo "   Utilisateur créé: {$user->name} (ID: {$user->id})\n";
    } else {
        $user = User::first();
        echo "   Utilisation de l'utilisateur existant: {$user->name} (ID: {$user->id})\n";
    }
    echo "\n";

    // 2. Créer une zone de sécurité de test
    echo "2. Création d'une zone de sécurité de test...\n";
    
    // Valeur par défaut pour geom (requis car NOT NULL)
    $defaultPolygon = new Polygon([
        new LineString([
            new Point(0, 0, 4326),
            new Point(0, 0.001, 4326),
            new Point(0.001, 0.001, 4326),
            new Point(0.001, 0, 4326),
            new Point(0, 0, 4326), // Fermer le ring
        ], 4326)
    ], 4326);
    
    $safeZone = SafeZone::create([
        'owner_id' => $user->id,
        'name' => 'Zone Test Rappels ' . time(),
        'center' => new Point(48.8566, 2.3522, 4326),
        'radius_m' => 100,
        'geom' => $defaultPolygon,
        'is_active' => true,
    ]);
    echo "   Zone de sécurité créée: {$safeZone->name} (ID: {$safeZone->id})\n\n";

    // 3. Créer un événement de sortie de zone
    echo "3. Création d'un événement de sortie de zone...\n";
    $safeZoneEvent = SafeZoneEvent::create([
        'user_id' => $user->id,
        'safe_zone_id' => $safeZone->id,
        'event_type' => 'exit',
        'location' => new Point(48.8600, 2.3600, 4326), // Position en dehors de la zone
        'created_at' => now(),
    ]);
    echo "   Événement de sortie créé (ID: {$safeZoneEvent->id})\n\n";

    // 4. Créer une alerte en attente
    echo "4. Création d'une alerte en attente...\n";
    $pendingAlert = PendingSafeZoneAlert::create([
        'user_id' => $user->id,
        'safe_zone_id' => $safeZone->id,
        'safe_zone_event_id' => $safeZoneEvent->id,
        'first_alert_sent_at' => now(),
        'last_reminder_sent_at' => null,
        'reminder_count' => 0,
        'confirmed' => false,
        'metadata' => ['test' => true],
    ]);
    echo "   Alerte en attente créée (ID: {$pendingAlert->id})\n\n";

    // 5. Tester la récupération des alertes en attente
    echo "5. Test de récupération des alertes en attente...\n";
    $alertsNeedingReminder = PendingSafeZoneAlert::needingReminder()->get();
    echo "   Nombre d'alertes nécessitant un rappel: {$alertsNeedingReminder->count()}\n";
    
    if ($alertsNeedingReminder->count() > 0) {
        foreach ($alertsNeedingReminder as $alert) {
            echo "   - Alerte ID: {$alert->id}, Zone: {$alert->safeZone->name}, Utilisateur: {$alert->user->name}\n";
        }
    }
    echo "\n";

    // 6. Tester la méthode canReceiveReminder
    echo "6. Test de la méthode canReceiveReminder...\n";
    $canReceive = $pendingAlert->canReceiveReminder();
    echo "   L'alerte peut recevoir un rappel: " . ($canReceive ? 'OUI' : 'NON') . "\n\n";

    // 7. Tester l'enregistrement d'un rappel
    echo "7. Test d'enregistrement d'un rappel...\n";
    $pendingAlert->recordReminderSent();
    $pendingAlert->refresh();
    echo "   Rappel enregistré. Nombre de rappels: {$pendingAlert->reminder_count}\n";
    echo "   Dernier rappel à: {$pendingAlert->last_reminder_at}\n\n";

    // 8. Tester la confirmation d'alerte
    echo "8. Test de confirmation d'alerte...\n";
    $pendingAlert->markAsConfirmed($user->id);
    $pendingAlert->refresh();
    echo "   Alerte confirmée: " . ($pendingAlert->confirmed ? 'OUI' : 'NON') . "\n";
    echo "   Confirmée par l'utilisateur ID: {$pendingAlert->confirmed_by}\n";
    echo "   Confirmée à: {$pendingAlert->confirmed_at}\n\n";

    // 9. Tester le job de rappels
    echo "9. Test du job SendSafeZoneExitReminders...\n";
    
    // Créer une nouvelle alerte non confirmée pour le test
    $testAlert = PendingSafeZoneAlert::create([
        'user_id' => $user->id,
        'safe_zone_id' => $safeZone->id,
        'safe_zone_event_id' => $safeZoneEvent->id,
        'first_alert_sent_at' => now()->subMinutes(10), // Il y a 10 minutes
        'last_reminder_sent_at' => null,
        'reminder_count' => 0,
        'confirmed' => false,
        'metadata' => ['test_job' => true],
    ]);
    
    echo "   Alerte de test créée pour le job (ID: {$testAlert->id})\n";
    
    // Dispatcher le job
    SendSafeZoneExitReminders::dispatch();
    echo "   Job SendSafeZoneExitReminders dispatché\n\n";

    // 10. Tester les API de confirmation
    echo "10. Test des modèles pour l'API de confirmation...\n";
    $pendingAlerts = PendingSafeZoneAlert::where('confirmed', false)
        ->whereHas('safeZone', function($query) use ($user) {
            $query->where('owner_id', $user->id);
        })
        ->with(['safeZone', 'user', 'safeZoneEvent'])
        ->get();
    
    echo "   Alertes en attente pour l'utilisateur: {$pendingAlerts->count()}\n";
    foreach ($pendingAlerts as $alert) {
        echo "   - Alerte ID: {$alert->id}, Zone: {$alert->safeZone->name}\n";
    }
    echo "\n";

    echo "=== Test terminé avec succès ! ===\n";
    echo "\nRésumé des données créées:\n";
    echo "- Utilisateur: {$user->name} (ID: {$user->id})\n";
    echo "- Zone de sécurité: {$safeZone->name} (ID: {$safeZone->id})\n";
    echo "- Événement de sortie: ID {$safeZoneEvent->id}\n";
    echo "- Alertes en attente: 2 créées\n";
    echo "\nPour nettoyer les données de test, vous pouvez exécuter:\n";
    echo "php artisan tinker\n";
    echo "PendingSafeZoneAlert::where('metadata->test', true)->delete();\n";
    echo "SafeZoneEvent::where('id', {$safeZoneEvent->id})->delete();\n";
    echo "SafeZone::where('id', {$safeZone->id})->delete();\n";

} catch (\Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . " ligne " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}