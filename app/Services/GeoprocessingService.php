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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UC-G1/G2: Service de géoprocessing pour détecter les zones
 *
 * Traite les positions GPS pour détecter les entrées/sorties
 * de zones de danger et zones de sécurité
 */
class GeoprocessingService
{
    private const SAFE_ZONE_MIN_BOUNDARY_MARGIN_METERS = 30.0;
    private const SAFE_ZONE_MAX_BOUNDARY_MARGIN_METERS = 200.0;
    private const SAFE_ZONE_TRANSITION_DEBOUNCE_SECONDS = 120;
    private const SAFE_ZONE_NOTIFICATION_COOLDOWN_SECONDS = 300;
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
        Log::info('Processing location', [
            'user_id' => $location->user_id,
            'location_id' => $location->id,
            'lat' => $location->latitude,
            'lng' => $location->longitude
        ]);
        try {
            Log::debug('Processing location try & catch', [
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
        Log::info('Processing danger zones', [
            'user_id' => $location->user_id,
            'location_id' => $location->id,
            'lat' => $location->latitude,
            'lng' => $location->longitude
        ]);

        // Récupérer les zones de danger actives dans un rayon de recherche
        $searchRadius = 1.0; // 1km de rayon de recherche

        $dangerZones = DangerZone::active()
            ->recent()
            ->withinRadius($location->latitude, $location->longitude, $searchRadius)
            ->get();

        Log::info('Danger zones found', [
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

            Log::info('Distance to danger zone', [
                'user_id' => $location->user_id,
                'location_id' => $location->id,
                'danger_zone_id' => $zone->id,
                'distance' => $distance
            ]);

            // Vérifier si l'utilisateur est proche de la zone de danger
            if ($distance <= $zone->radius_m) {
                Log::info('User is within danger zone radius', [
                    'user_id' => $location->user_id,
                    'location_id' => $location->id,
                    'danger_zone_id' => $zone->id,
                    'distance' => $distance
                ]);

                $nearbyZones[] = [
                    'zone' => $zone,
                    'distance' => $distance
                ];
            }
        }

        // Si des zones de danger sont détectées, en sélectionner une au hasard
        if (!empty($nearbyZones)) {
            Log::info('Nearby danger zones detected', [
                'user_id' => $location->user_id,
                'location_id' => $location->id,
                'nearby_zones_count' => count($nearbyZones)
            ]);

            // Sélection aléatoire d'une zone parmi celles détectées
            $randomIndex = array_rand($nearbyZones);
            $selectedZone = $nearbyZones[$randomIndex];

            Log::info('Checking if notification should be sent for danger zone', [
                'user_id' => $location->user_id,
                'location_id' => $location->id,
                'danger_zone_name' => $selectedZone['zone']->title,
                'distance' => $selectedZone['distance']
            ]);

            Log::info('Random zone selected for notification check', [
                'user_id' => $location->user_id,
                'location_id' => $location->id,
                'selected_zone_id' => $selectedZone['zone']->id,
                'distance' => $selectedZone['distance']
            ]);

            $this->handleDangerZoneEntry($location, $selectedZone['zone'], $selectedZone['distance']);
        }else{
            Log::info('No nearby danger zones detected', [
                'user_id' => $location->user_id,
                'location_id' => $location->id
            ]);
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

            $distance = null;

            if ($zone->isCircle()) {
                $distance = $this->calculateDistance(
                    $location->latitude,
                    $location->longitude,
                    $zone->center->latitude,
                    $zone->center->longitude
                );
            }

            // Machine à états : une transition doit être stable dans le temps et
            // dépasser une marge liée à la précision GPS, afin d'éviter le ping-pong
            // sur la frontière d'une zone.
            $lastEvent = SafeZoneEvent::where('user_id', $location->user_id)
                ->where('safe_zone_id', $zone->id)
                ->latest('captured_at_device')
                ->first();

            // Pas d'événement précédent : le premier point vraiment loin de la zone
            // reste une sortie utile à signaler (ex. app lancée après le déplacement).
            $wasInside = $lastEvent ? ($lastEvent->event_type === 'entry') : true;

            $isInside = $this->isInsideForState($location, $zone, $distance, $wasInside);

            if (!$isInside && $wasInside) {
                if ($this->isTransitionDebounced($lastEvent, $location)) {
                    $this->logDebouncedTransition($location, $zone, $lastEvent);
                    continue;
                }
                // Transition dedans → dehors = SORTIE
                Log::info('Safe zone transition: entry → exit', [
                    'user_id' => $location->user_id,
                    'safe_zone_id' => $zone->id,
                    'distance' => $distance,
                ]);
                $this->handleSafeZoneExit($location, $zone, $distance);

            } elseif ($isInside && !$wasInside) {
                if ($this->isTransitionDebounced($lastEvent, $location)) {
                    $this->logDebouncedTransition($location, $zone, $lastEvent);
                    continue;
                }
                // Transition dehors → dedans = ENTRÉE
                Log::info('Safe zone transition: exit → entry', [
                    'user_id' => $location->user_id,
                    'safe_zone_id' => $zone->id,
                    'distance' => $distance,
                ]);
                $this->handleSafeZoneEntry($location, $zone, $distance);

            } else {
                Log::debug('Safe zone state unchanged - no action', [
                    'user_id' => $location->user_id,
                    'safe_zone_id' => $zone->id,
                    'is_inside' => $isInside,
                ]);
            }
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
            Log::info('Danger zone notification skipped - zone is ignored by user', [
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
            Log::info('Danger zone notification skipped due to 12h cooldown', [
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

        Log::info('Cooldown activated for danger zone', [
            'user_id' => $location->user_id,
            'zone_id' => $zone->id,
            'cooldown_key' => $cooldownKey,
            'duration_hours' => 12
        ]);
    }

    /**
     * UC-G2: Gérer la sortie d'une zone de sécurité
     * Nouvelle logique : envoi systématique de notification à chaque détection hors zone
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
        if (!$safeZoneEvent) {
            return;
        }

        // Enregistrer l'activité de sortie de la zone de sécurité
        $this->activityLogService->logExitSafeZone($location->user_id, $zone->id, [
            'distance' => $distance,
            'zone_name' => $zone->name,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude
        ]);

        // Vérifier si les notifications de sortie sont activées pour cet utilisateur
        $assignment = $zone->assignments()
            ->where('assigned_user_id', $location->user_id)
            ->where('is_active', true)
            ->first();

        if ($assignment && $assignment->notify_exit) {
            // Créer une alerte en attente pour les rappels périodiques si l'événement a été créé avec succès
            $this->createPendingSafeZoneAlert($location->user_id, $zone->id, $safeZoneEvent->id);

            if ($this->isNotificationInCooldown($location->user_id, $zone->id, 'exit', $safeZoneEvent)) {
                Log::info('Safe zone exit notification skipped due to cooldown', [
                    'user_id' => $location->user_id,
                    'zone_id' => $zone->id,
                    'event_id' => $safeZoneEvent->id,
                ]);
                return;
            }

            // Notifier les proches assignés à cette zone - SYSTÉMATIQUEMENT à chaque détection
            $sent = $this->notificationService->sendSafeZoneExitAlert($location->user_id, $zone, $safeZoneEvent);
            if ($sent) {
                $safeZoneEvent->markNotificationSent();
            }

            Log::info('Safe zone exit notification sent (no cooldown)', [
                'user_id' => $location->user_id,
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'distance' => $distance
            ]);
        } else {
            Log::info('Safe zone exit notification skipped - notify_exit disabled', [
                'user_id' => $location->user_id,
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'distance' => $distance
            ]);
        }
    }



    /**
     * UC-G2: Gérer l'entrée dans une zone de sécurité
     */
    private function handleSafeZoneEntry(UserLocation $location, SafeZone $zone, ?float $distance): void
    {
        Log::info('User entered safe zone', [
            'user_id' => $location->user_id,
            'zone_id'  => $zone->id,
            'zone_name' => $zone->name,
            'distance' => $distance,
        ]);

        // Enregistrer l'événement d'entrée
        $safeZoneEvent = $this->recordSafeZoneEvent($location, $zone, 'entry', $distance);
        if (!$safeZoneEvent) {
            return;
        }

        // Auto-confirmer l'alerte en attente si l'utilisateur revient dans la zone
        \App\Models\PendingSafeZoneAlert::where('user_id', $location->user_id)
            ->where('safe_zone_id', $zone->id)
            ->where('confirmed', false)
            ->update([
                'confirmed'    => true,
                'confirmed_at' => now(),
            ]);

        // Enregistrer l'activité
        $this->activityLogService->logEnterSafeZone($location->user_id, $zone->id, [
            'distance'  => $distance,
            'zone_name' => $zone->name,
            'latitude'  => $location->latitude,
            'longitude' => $location->longitude,
        ]);

        // Notifier le créateur de la zone si les notifications d'entrée sont activées
        $assignment = $zone->assignments()
            ->where('assigned_user_id', $location->user_id)
            ->where('is_active', true)
            ->first();

        if ($assignment && $assignment->notify_entry) {
            if ($this->isNotificationInCooldown($location->user_id, $zone->id, 'entry', $safeZoneEvent)) {
                Log::info('Safe zone entry notification skipped due to cooldown', [
                    'user_id' => $location->user_id,
                    'zone_id' => $zone->id,
                    'event_id' => $safeZoneEvent->id,
                ]);
                return;
            }
            if ($this->notificationService->sendSafeZoneEntryAlert($location->user_id, $zone, $safeZoneEvent)) {
                $safeZoneEvent->markNotificationSent();
            }
        }
    }

    private function isInsideForState(UserLocation $location, SafeZone $zone, ?float $distance, bool $wasInside): bool
    {
        if (!$zone->isCircle()) {
            return $this->isUserInSafeZone($location, $zone);
        }

        $margin = min(
            self::SAFE_ZONE_MAX_BOUNDARY_MARGIN_METERS,
            max(self::SAFE_ZONE_MIN_BOUNDARY_MARGIN_METERS, (float) ($location->accuracy ?? 0)),
        );

        return $wasInside
            ? $distance <= $zone->radius_m + $margin
            : $distance < max(0, $zone->radius_m - $margin);
    }

    private function isTransitionDebounced(?SafeZoneEvent $lastEvent, UserLocation $location): bool
    {
        if (!$lastEvent) {
            return false;
        }

        $lastAt = $lastEvent->captured_at_device ?? $lastEvent->created_at;
        $currentAt = $location->captured_at_device ?? now();
        return abs($currentAt->diffInSeconds($lastAt, false)) < self::SAFE_ZONE_TRANSITION_DEBOUNCE_SECONDS;
    }

    private function logDebouncedTransition(UserLocation $location, SafeZone $zone, ?SafeZoneEvent $lastEvent): void
    {
        Log::info('Safe zone transition ignored during debounce window', [
            'user_id' => $location->user_id,
            'safe_zone_id' => $zone->id,
            'last_event' => $lastEvent?->event_type,
        ]);
    }

    private function isNotificationInCooldown(int $userId, int $zoneId, string $eventType, SafeZoneEvent $event): bool
    {
        $previous = SafeZoneEvent::where('user_id', $userId)
            ->where('safe_zone_id', $zoneId)
            ->where('event_type', $eventType)
            ->where('id', '!=', $event->id)
            ->where('notification_sent', true)
            ->latest('captured_at_device')
            ->first();

        if (!$previous) {
            return false;
        }

        $previousAt = $previous->captured_at_device ?? $previous->created_at;
        $eventAt = $event->captured_at_device ?? $event->created_at;
        return abs($eventAt->diffInSeconds($previousAt, false)) < self::SAFE_ZONE_NOTIFICATION_COOLDOWN_SECONDS;
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

            $isInside = $distance <= $zone->radius_m;

            Log::debug('Safe zone distance calculation', [
                'zone_id' => $zone->id,
                'user_lat' => $location->latitude,
                'user_lng' => $location->longitude,
                'zone_center_lat' => $zone->center->latitude,
                'zone_center_lng' => $zone->center->longitude,
                'distance_m' => round($distance, 2),
                'radius_m' => $zone->radius_m,
                'is_inside' => $isInside
            ]);

            return $isInside;
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
                'speed_kmh' => $location->speed,
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
            $alert = DB::transaction(function () use ($userId, $safeZoneId, $safeZoneEventId) {
                // Serialize creation per zone. This prevents two concurrent location
                // jobs from creating two active reminder chains for the same person.
                SafeZone::whereKey($safeZoneId)->lockForUpdate()->firstOrFail();

                $existing = \App\Models\PendingSafeZoneAlert::where('user_id', $userId)
                    ->where('safe_zone_id', $safeZoneId)
                    ->where('confirmed', false)
                    ->first();

                return $existing ?? \App\Models\PendingSafeZoneAlert::create([
                    'user_id' => $userId,
                    'safe_zone_id' => $safeZoneId,
                    'safe_zone_event_id' => $safeZoneEventId,
                    'first_alert_sent_at' => now(),
                    'reminder_count' => 0,
                    'confirmed' => false,
                ]);
            }, 3);

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
