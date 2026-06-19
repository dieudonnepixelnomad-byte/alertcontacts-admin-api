<?php

use App\Models\User;
use App\Models\SafeZone;
use App\Models\SafeZoneAssignment;
use App\Models\UserLocation;
use App\Services\GeoprocessingService;
use MatanYadaev\EloquentSpatial\Objects\Point;

// ── 1. Vérification des users ──────────────────────────────────────────────
$user1 = User::find(1);
$user2 = User::find(2);

if (!$user1 || !$user2) {
    echo "ERREUR: user1 ou user2 introuvable\n";
    return;
}

echo "User1 [{$user1->id}]: {$user1->email} | FCM: " . ($user1->fcm_token ? 'OK ✓' : '⚠ MISSING') . "\n";
echo "User2 [{$user2->id}]: {$user2->email} | FCM: " . ($user2->fcm_token ? 'OK ✓' : '⚠ MISSING') . "\n\n";

// ── 2. Création de la SafeZone fictive (user#1 = owner) ───────────────────
// Centre : position actuelle de user#2 (d'après les logs)
// User#2 sera HORS zone → position injectée sera ~1.2km au nord
$zoneLat = 4.0615151;
$zoneLng = 9.7604228;
$zoneRadius = 200; // 200m

// Nettoyer les éventuelles zones de test précédentes
SafeZone::where('owner_id', 1)->where('name', 'Zone Test Sortie')->delete();

$zone = SafeZone::create([
    'owner_id'  => 1,
    'name'      => 'Zone Test Sortie',
    'icon'      => 'home',
    'center'    => new Point($zoneLat, $zoneLng),
    'radius_m'  => $zoneRadius,
    'is_active' => true,
]);

echo "✓ SafeZone créée:\n";
echo "  ID        : {$zone->id}\n";
echo "  Centre    : lat={$zoneLat}, lng={$zoneLng}\n";
echo "  Rayon     : {$zoneRadius}m\n\n";

// ── 3. Assignation de user#2 à la zone (notify_exit=true) ─────────────────
// Pas de SafeZoneEvent précédent → le système suppose que user#2 était DEDANS
SafeZoneAssignment::where('safe_zone_id', $zone->id)->delete();

$assignment = SafeZoneAssignment::create([
    'safe_zone_id'        => $zone->id,
    'assigned_user_id'    => 2,
    'assigned_by_user_id' => 1,
    'is_active'           => true,
    'notify_entry'        => true,
    'notify_exit'         => true,
    'assigned_at'         => now(),
    'accepted_at'         => now(), // auto-accepté pour le test
]);

echo "✓ Assignment créé:\n";
echo "  ID            : {$assignment->id}\n";
echo "  assigned_user : user#2\n";
echo "  notify_exit   : true\n\n";

// ── 4. Position de user#2 HORS zone (~1.2km au nord du centre) ────────────
$outsideLat = $zoneLat + 0.011; // ≈ +1.22km en latitude
$outsideLng = $zoneLng;

$location = UserLocation::create([
    'user_id'           => 2,
    'latitude'          => $outsideLat,
    'longitude'         => $outsideLng,
    'accuracy'          => 5.0,
    'speed'             => null,
    'heading'           => null,
    'captured_at_device'=> now(),
    'source'            => 'gps',
    'foreground'        => true,
    'battery_level'     => 75,
]);

$approxDist = round(abs($outsideLat - $zoneLat) * 111000);
echo "✓ UserLocation créée:\n";
echo "  ID       : {$location->id}\n";
echo "  Position : lat={$outsideLat}, lng={$outsideLng}\n";
echo "  Distance zone : ~{$approxDist}m (rayon={$zoneRadius}m) → OUTSIDE ✓\n\n";

// ── 5. Traitement synchrone → devrait déclencher l'alerte de sortie ───────
echo "▶ Lancement de GeoprocessingService::processLocation()...\n\n";

$service = app(GeoprocessingService::class);
$service->processLocation($location);

echo "\n✓ Processing terminé.\n";
echo "→ Vérifier les logs Laravel + la notification FCM reçue sur user#1\n";
