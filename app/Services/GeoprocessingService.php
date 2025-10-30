<?php

namespace App\Services;

use App\Models\DangerZone;
use App\Models\SafeZone;
use App\Models\SafeZoneEvent;
use App\Models\UserLocation;
use App\Services\NotificationService;
use App\Services\CooldownService;
use App\Services\ActivityLogService;
use App\Services\IgnoredDangerZoneService;
use Illuminate\Support\Facades\Log;

/**
 * UC-G1/G2: Service de géoprocessing pour détecter les zones
 *
 * Traite les positions GPS pour détecter les entrées/sorties
 * de zones de danger et zones de sécurité
 */
class GeoprocessingService
{
    public function __construct(
        private NotificationService $notificationService,
        private CooldownService $cooldownService,
        private ActivityLogService $activityLogService,
        private IgnoredDangerZoneService $ignoredDangerZoneService
    ) {}

    /**
     * UC-G1/G2: Traiter une position GPS
     */
    public function processLocation(UserLocation $location): void
    {
        try {
            Log::debug('Processing location', [
                'user_id' => $location->user_id,
                'location_id' => $location->id,
                'lat' => $location->latitude,
                'lng' => $location->longitude
            ]);

            // UC-G1: Détecter les zones de danger
            $this->processDangerZones($location);

            // UC-G2: Détecter les zones de sécurité
            $this->processSafeZones($location);

        } catch (\Exception $e) {
            Log::error('Location processing failed', [
                'location_id' => $location->id,
                'user_id' => $location->user_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * UC-G1: Traiter les zones de danger
     * Nouvelle logique : sélection aléatoire d'une zone proche et cooldown 12h uniquement
     */
    private function processDangerZones(UserLocation $location): void
    {
        // Récupérer les zones de danger actives dans un rayon de recherche
        $searchRadius = 1.0; // 1km de rayon de recherche

        $dangerZones = DangerZone::active()
            ->recent()
            ->withinRadius($location->latitude, $location->longitude, $searchRadius)
            ->get();

        Log::debug('Danger zones found', [
            'user_id' => $location->user_id,
            'location_id' => $location->id,
            'danger_zones_count' => $dangerZones->count()
        ]);

        // Collecter toutes les zones de danger proches (dans le rayon de la zone)
        $nearbyZones = [];

        foreach ($dangerZones as $zone) {
            $distance = $this->calculateDistance(
                $location->latitude,
                $location->longitude,
                $zone->center_lat,
                $zone->center_lng
            );

            Log::debug('Distance to danger zone', [
                'user_id' => $location->user_id,
                'location_id' => $location->id,
                'danger_zone_id' => $zone->id,
                'distance' => $distance
            ]);

            // Vérifier si l'utilisateur est proche de la zone de danger
            if ($distance <= $zone->radius_m) {
                $nearbyZones[] = [
                    'zone' => $zone,
                    'distance' => $distance
                ];
            }
        }

        // Si des zones de danger sont détectées, en sélectionner une au hasard
        if (!empty($nearbyZones)) {
            Log::debug('Nearby danger zones detected', [
                'user_id' => $location->user_id,
                'location_id' => $location->id,
                'nearby_zones_count' => count($nearbyZones)
            ]);

            // Sélection aléatoire d'une zone parmi celles détectées
            $randomIndex = array_rand($nearbyZones);
            $selectedZone = $nearbyZones[$randomIndex];

            Log::debug('Random zone selected for notification check', [
                'user_id' => $location->user_id,
                'location_id' => $location->id,
                'selected_zone_id' => $selectedZone['zone']->id,
                'distance' => $selectedZone['distance']
            ]);

            $this->handleDangerZoneEntry($location, $selectedZone['zone'], $selectedZone['distance']);
        }
    }

    /**
     * UC-G2: Traiter les zones de sécurité
     */
    private function processSafeZones(UserLocation $location): void
    {

        Log::debug('Processing safe zones', [
            'user_id' => $location->user_id,
            'location_id' => $location->id
        ]);

        // Récupérer les zones de sécurité où l'utilisateur est assigné
        $safeZones = SafeZone::whereHas('assignments', function ($query) use ($location) {
                $query->where('assigned_user_id', $location->user_id)
                      ->where('is_active', true);
            })
            ->where('is_active', true)
            ->get();

        if($safeZones->isEmpty()){
            Log::debug('No safe zones assigned to user', [
                'user_id' => $location->user_id,
                'location_id' => $location->id
            ]);
            return;
        }

        Log::debug('Safe zones found', [
            'user_id' => $location->user_id,
            'location_id' => $location->id,
            'safe_zones_count' => $safeZones->count()
        ]);

        foreach ($safeZones as $zone) {

            Log::debug('Processing Foreach safe zone', [
                'user_id' => $location->user_id,
                'location_id' => $location->id,
                'safe_zone_id' => $zone->id
            ]);

            $isInside = $this->isUserInSafeZone($location, $zone);
            $distance = null;

            // Calculer la distance si c'est une zone circulaire
            if ($zone->isCircle()) {
                $distance = $this->calculateDistance(
                    $location->latitude,
                    $location->longitude,
                    $zone->center->latitude,
                    $zone->center->longitude
                );
            }

            // Récupérer le dernier état connu pour cette zone
            $lastState = $this->getLastSafeZoneState($location->user_id, $zone->id);

            Log::debug('Last safe zone state get', [
                'user_id' => $location->user_id,
                'location_id' => $location->id,
                'safe_zone_id' => $zone->id,
                'last_state' => $lastState
            ]);

            // Détecter les changements d'état (entrée/sortie)
            if ($isInside && !$lastState) {
                Log::debug('User entered safe zone', [
                    'user_id' => $location->user_id,
                    'location_id' => $location->id,
                    'safe_zone_id' => $zone->id,
                    'distance' => $distance
                ]);
                $this->handleSafeZoneEntry($location, $zone, $distance);
                $this->recordSafeZoneEvent($location, $zone, 'enter', $distance);
            } elseif (!$isInside && $lastState) {
                Log::debug('User exited safe zone', [
                    'user_id' => $location->user_id,
                    'location_id' => $location->id,
                    'safe_zone_id' => $zone->id,
                    'distance' => $distance
                ]);
                $this->handleSafeZoneExit($location, $zone, $distance);
                $this->recordSafeZoneEvent($location, $zone, 'exit', $distance);
            }

            // Mettre à jour l'état
            $this->updateSafeZoneState($location->user_id, $zone->id, $isInside);
        }
    }

    /**
     * UC-G1: Gérer l'entrée dans une zone de danger
     * Nouvelle logique : cooldown 12h uniquement par zone/utilisateur
     */
    private function handleDangerZoneEntry(UserLocation $location, DangerZone $zone, float $distance): void
    {
        // Vérifier si l'utilisateur a ignoré cette zone de danger
        if ($this->ignoredDangerZoneService->isZoneIgnored($location->user_id, $zone->id)) {
            Log::debug('Danger zone notification skipped - zone is ignored by user', [
                'user_id' => $location->user_id,
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'distance' => $distance
            ]);
            return;
        }

        $cooldownKey = "danger_zone_{$zone->id}_user_{$location->user_id}";

        // Vérifier le cooldown (pas plus d'une notification par 12h par zone/utilisateur)
        if ($this->cooldownService->isInCooldown($cooldownKey)) {
            Log::debug('Danger zone notification skipped due to 12h cooldown', [
                'user_id' => $location->user_id,
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'distance' => $distance,
                'cooldown_key' => $cooldownKey
            ]);
            return;
        }

        Log::info('Sending danger zone notification', [
            'user_id' => $location->user_id,
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'distance' => $distance,
            'severity' => $zone->severity
        ]);

        // Enregistrer l'activité d'entrée dans la zone de danger
        $this->activityLogService->logEnterDangerZone($location->user_id, $zone->id, [
            'distance' => $distance,
            'severity' => $zone->severity,
            'zone_name' => $zone->name,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude
        ]);

        // Envoyer la notification (sans cooldown supplémentaire dans NotificationService)
        $this->notificationService->sendDangerZoneAlert($location->user_id, $zone, $distance);

        // Activer le cooldown de 12h pour cette zone/utilisateur
        $this->cooldownService->setCooldown($cooldownKey, 12 * 60 * 60, [
            'zone_id' => $zone->id,
            'user_id' => $location->user_id,
            'zone_name' => $zone->name,
            'severity' => $zone->severity
        ]);

        Log::debug('Cooldown activated for danger zone', [
            'user_id' => $location->user_id,
            'zone_id' => $zone->id,
            'cooldown_key' => $cooldownKey,
            'duration_hours' => 12
        ]);
    }

    /**
     * UC-G2: Gérer l'entrée dans une zone de sécurité
     */
    private function handleSafeZoneEntry(UserLocation $location, SafeZone $zone, float $distance): void
    {
        Log::info('User entered safe zone', [
            'user_id' => $location->user_id,
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'distance' => $distance
        ]);

        // Enregistrer l'activité d'entrée dans la zone de sécurité
        $this->activityLogService->logEnterSafeZone($location->user_id, $zone->id, [
            'distance' => $distance,
            'zone_name' => $zone->name,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude
        ]);

        // Notifier les proches assignés à cette zone
        $this->notificationService->sendSafeZoneEntryAlert($location->user_id, $zone);
    }

    /**
     * UC-G2: Gérer la sortie d'une zone de sécurité
     */
    private function handleSafeZoneExit(UserLocation $location, SafeZone $zone, float $distance): void
    {
        Log::info('User exited safe zone', [
            'user_id' => $location->user_id,
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'distance' => $distance
        ]);

        // Enregistrer l'événement de sortie de la zone de sécurité
        $safeZoneEvent = $this->recordSafeZoneEvent($location, $zone, 'exit', $distance);

        // Enregistrer l'activité de sortie de la zone de sécurité
        $this->activityLogService->logExitSafeZone($location->user_id, $zone->id, [
            'distance' => $distance,
            'zone_name' => $zone->name,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude
        ]);

        // Créer une alerte en attente pour les rappels périodiques si l'événement a été créé avec succès
        if ($safeZoneEvent) {
            $this->createPendingSafeZoneAlert($location->user_id, $zone->id, $safeZoneEvent->id);
        }

        // Notifier les proches assignés à cette zone (première alerte)
        $this->notificationService->sendSafeZoneExitAlert($location->user_id, $zone);
    }

    /**
     * Récupérer le dernier état d'une zone de sécurité pour un utilisateur
     */
    private function getLastSafeZoneState(int $userId, int $zoneId): bool
    {
        Log::debug('Checking last safe zone state', [
            'user_id' => $userId,
            'zone_id' => $zoneId
        ]);
        // Récupérer le dernier événement pour cette zone et cet utilisateur
        $lastEvent = SafeZoneEvent::where('user_id', $userId)
            ->where('safe_zone_id', $zoneId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastEvent) {
            Log::debug('No previous safe zone event found', [
                'user_id' => $userId,
                'zone_id' => $zoneId
            ]);
            // Aucun événement précédent, l'utilisateur n'était pas dans la zone
            return false;
        }

        Log::debug('Last safe zone event found', [
            'user_id' => $userId,
            'zone_id' => $zoneId,
            'event_type' => $lastEvent->event_type,
            'created_at' => $lastEvent->created_at->toISOString()
        ]);

        // Si le dernier événement est 'entry', l'utilisateur était dans la zone
        // Si le dernier événement est 'exit', l'utilisateur n'était pas dans la zone
        return $lastEvent->event_type === 'entry';
    }

    /**
     * Mettre à jour l'état d'une zone de sécurité pour un utilisateur
     */
    private function updateSafeZoneState(int $userId, int $zoneId, bool $isInside): void
    {
        Log::debug('Safe zone state updated', [
            'user_id' => $userId,
            'zone_id' => $zoneId,
            'is_inside' => $isInside
        ]);
    }

    /**
     * Calculer la distance entre deux points GPS (en mètres)
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // Rayon de la Terre en mètres

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Vérifier si un utilisateur est dans une zone de sécurité
     */
    private function isUserInSafeZone(UserLocation $location, SafeZone $zone): bool
    {
        Log::debug('Checking if user is in safe zone', [
            'user_id' => $location->user_id,
            'zone_id' => $zone->id,
            'zone_type' => $zone->isCircle() ? 'circle' : 'polygon'
        ]);

        if ($zone->isCircle()) {
            Log::debug('Safe zone is circular', [
                'zone_id' => $zone->id,
                'radius_m' => $zone->radius_m
            ]);

            // Zone circulaire
            $distance = $this->calculateDistance(
                $location->latitude,
                $location->longitude,
                $zone->center->latitude,
                $zone->center->longitude
            );
            return $distance <= $zone->radius_m;
        } elseif ($zone->isPolygon()) {
            Log::debug('Safe zone is polygon', [
                'zone_id' => $zone->id,
                'geom' => $zone->geom->toJson()
            ]);

            // Zone polygonale - utiliser la géométrie spatiale
            $point = new \MatanYadaev\EloquentSpatial\Objects\Point($location->latitude, $location->longitude);
            return $zone->geom->contains($point);
        }

        return false;
    }

    /**
     * Enregistrer un événement de zone de sécurité
     */
    private function recordSafeZoneEvent(UserLocation $location, SafeZone $zone, string $eventType, ?float $distance): ?SafeZoneEvent
    {
        try {
            return SafeZoneEvent::create([
                'user_id' => $location->user_id,
                'safe_zone_id' => $zone->id,
                'event_type' => $eventType,
                'location' => new \MatanYadaev\EloquentSpatial\Objects\Point($location->latitude, $location->longitude),
                'accuracy' => $location->accuracy,
                'distance_m' => $distance,
                'speed' => $location->speed,
                'heading' => $location->heading,
                'battery_level' => $location->battery_level,
                'source' => $location->source,
                'foreground' => $location->foreground,
                'captured_at_device' => $location->captured_at_device,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record safe zone event', [
                'user_id' => $location->user_id,
                'zone_id' => $zone->id,
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Créer une alerte en attente pour les rappels périodiques
     */
    private function createPendingSafeZoneAlert(int $userId, int $safeZoneId, int $safeZoneEventId): void
    {
        try {
            // Vérifier s'il existe déjà une alerte non confirmée pour cette zone et cet utilisateur
            $existingAlert = \App\Models\PendingSafeZoneAlert::where('user_id', $userId)
                ->where('safe_zone_id', $safeZoneId)
                ->where('confirmed', false)
                ->first();

            if ($existingAlert) {
                Log::info('Pending alert already exists for user and safe zone', [
                    'user_id' => $userId,
                    'safe_zone_id' => $safeZoneId,
                    'existing_alert_id' => $existingAlert->id
                ]);
                return;
            }

            // Créer une nouvelle alerte en attente
            \App\Models\PendingSafeZoneAlert::create([
                'user_id' => $userId,
                'safe_zone_id' => $safeZoneId,
                'safe_zone_event_id' => $safeZoneEventId,
                'first_alert_sent_at' => now(),
                'reminder_count' => 0,
                'confirmed' => false,
            ]);

            Log::info('Pending safe zone alert created', [
                'user_id' => $userId,
                'safe_zone_id' => $safeZoneId,
                'safe_zone_event_id' => $safeZoneEventId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create pending safe zone alert', [
                'user_id' => $userId,
                'safe_zone_id' => $safeZoneId,
                'safe_zone_event_id' => $safeZoneEventId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
