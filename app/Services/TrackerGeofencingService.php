<?php

namespace App\Services;

use App\Models\TrackerLocation;
use App\Models\TrackerSafeZoneEvent;
use App\Support\Geo;

class TrackerGeofencingService
{
    public function process(TrackerLocation $location): void
    {
        $tracker = $location->tracker()->with(['zoneAssignments.safeZone', 'owner'])->firstOrFail();

        foreach ($tracker->zoneAssignments->where('is_active', true) as $assignment) {
            $zone = $assignment->safeZone;
            if (!$zone?->isCircle())
                continue; // Les polygones seront activés avec le même évaluateur commun.
            $distance = Geo::haversine($location->latitude, $location->longitude, $zone->center->latitude, $zone->center->longitude);
            $inside = $distance <= $zone->radius_m;
            $last = TrackerSafeZoneEvent::where('tracker_id', $tracker->id)->where('safe_zone_id', $zone->id)->latest('occurred_at')->first();
            $wasInside = $last ? $last->event_type === 'entry' : true;
            if ($inside === $wasInside)
                continue;
            $event = TrackerSafeZoneEvent::create(['tracker_id' => $tracker->id, 'safe_zone_id' => $zone->id, 'tracker_location_id' => $location->id, 'event_type' => $inside ? 'entry' : 'exit', 'distance_m' => $distance, 'occurred_at' => $location->captured_at_device]);
            $allowed = $inside ? $assignment->notify_entry : $assignment->notify_exit;
            if ($allowed && $tracker->owner?->fcm_token) {
                $sent = app(FirebaseNotificationService::class)->sendNotification($tracker->owner->fcm_token, $inside ? "{$tracker->name} est entré dans {$zone->name}" : "{$tracker->name} a quitté {$zone->name}", $inside ? 'Entrée dans une zone de sécurité.' : 'Sortie d’une zone de sécurité.', ['type' => $inside ? 'safe_zone_entry' : 'safe_zone_exit', 'tracker_id' => $tracker->id, 'safe_zone_id' => $zone->id], 'high');
                if ($sent)
                    $event->update(['notification_sent' => true]);
            }
        }
    }
}
