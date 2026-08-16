<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Services\QuietHoursService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Diffusion push d'un incident aux utilisateurs proches — CDC V4.1 §4.1
 *
 * Remplace la closure `dispatch(fn)` de AlertController@store et le
 * FirebaseNotificationService::sendCommunityAlert() du V4.0, qui :
 *   - utilisait le rayon unique `radius_m` au lieu de `notify_radius_m`
 *   - ignorait la visibilité contacts_only
 *   - ignorait les zones mises en sourdine par l'utilisateur
 *   - ignorait les heures calmes
 *
 * Le rayon de notification reste généreux : tout le quartier est prévenu de
 * l'incendie. C'est la géométrie d'ÉVITEMENT qui est chirurgicale (§4.1).
 */
class NotifyNearbyUsersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly int $incidentId)
    {
        $this->onQueue('alerts');
    }

    public function handle(
        FirebaseNotificationService $fcm,
        QuietHoursService $quietHours
    ): void {
        $incident = Incident::with('reports')->find($this->incidentId);

        if ($incident === null || $incident->status !== 'active') {
            return;
        }

        $authorIds = $incident->reports->pluck('user_id')->filter()->unique()->all();
        $recipients = $this->findRecipients($incident, $authorIds);

        $sent = 0;

        foreach ($recipients as $row) {
            $user = User::find($row->id);

            if ($user === null) {
                continue;
            }

            // §9.4 — silence pendant les heures calmes, sauf gravité Élevé
            if ($incident->severity !== 'high' && $quietHours->isQuietTime($user)) {
                continue;
            }

            $distanceM = (int) $row->distance_m;

            $ok = $fcm->sendNotification(
                $user->fcm_token,
                $this->title($incident, $distanceM),
                $this->body($incident, $distanceM),
                [
                    'type'            => 'community_incident',
                    'incident_id'     => $incident->id,
                    'incident_type'   => $incident->type,
                    'gravity'         => $incident->severity,
                    'distance_meters' => $distanceM,
                    'lat'             => $incident->centroid_lat,
                    'lng'             => $incident->centroid_lng,
                    'report_count'    => $incident->report_count,
                ],
                $incident->severity === 'high' ? 'high' : 'normal'
            );

            if ($ok) {
                $sent++;
            }
        }

        Log::info('[NotifyNearbyUsersJob] diffusion terminée', [
            'incident_id' => $incident->id,
            'type'        => $incident->type,
            'sent'        => $sent,
            'candidates'  => $recipients->count(),
        ]);
    }

    /**
     * Utilisateurs dont la dernière position connue est dans notify_radius_m.
     *
     * @param  array<int, int>  $authorIds
     */
    private function findRecipients(Incident $incident, array $authorIds)
    {
        $lat = (float) $incident->centroid_lat;
        $lng = (float) $incident->centroid_lng;
        $radius = (int) $incident->notify_radius_m;

        $haversine = '(6371000 * acos(LEAST(1.0,
            cos(radians(?)) * cos(radians(ul.latitude)) *
            cos(radians(ul.longitude) - radians(?)) +
            sin(radians(?)) * sin(radians(ul.latitude))
        )))';

        $query = DB::table('user_locations as ul')
            ->join('users as u', 'u.id', '=', 'ul.user_id')
            ->whereNotNull('u.fcm_token')
            ->where('ul.captured_at_device', '>=', now()->subHours(24))
            ->whereRaw('ul.id = (SELECT id FROM user_locations WHERE user_id = ul.user_id ORDER BY captured_at_device DESC LIMIT 1)')
            ->whereRaw("{$haversine} <= ?", [$lat, $lng, $lat, $radius])
            ->select('u.id', 'u.fcm_token')
            ->selectRaw("ROUND({$haversine}) as distance_m", [$lat, $lng, $lat]);

        // §4.10 règle 1 — on ne notifie jamais les auteurs du signalement
        if ($authorIds !== []) {
            $query->whereNotIn('u.id', $authorIds);
        }

        // Zones mises en sourdine par l'utilisateur : un incident dans une zone
        // ignorée ne doit pas le relancer.
        $query->whereNotExists(function ($sub) use ($lat, $lng) {
            $sub->select(DB::raw(1))
                ->from('ignored_danger_zones as idz')
                ->join('danger_zones as dz', 'dz.id', '=', 'idz.danger_zone_id')
                ->whereColumn('idz.user_id', 'u.id')
                ->where(function ($q) {
                    $q->whereNull('idz.expires_at')->orWhere('idz.expires_at', '>', now());
                })
                ->whereRaw('
                    (6371000 * acos(LEAST(1.0,
                        cos(radians(?)) * cos(radians(dz.center_lat)) *
                        cos(radians(dz.center_lng) - radians(?)) +
                        sin(radians(?)) * sin(radians(dz.center_lat))
                    ))) <= dz.radius_m
                ', [$lat, $lng, $lat]);
        });

        $recipients = $query->get();

        // Visibilité restreinte au cercle de proches de l'auteur
        if ($this->isCircleOnly($incident) && $authorIds !== []) {
            $allowed = DB::table('relationships')
                ->whereIn('user_id', $authorIds)
                ->where('status', 'accepted')
                ->pluck('contact_id')
                ->all();

            $recipients = $recipients->whereIn('id', $allowed)->values();
        }

        return $recipients;
    }

    private function isCircleOnly(Incident $incident): bool
    {
        return $incident->reports->isNotEmpty()
            && $incident->reports->every(fn ($r) => $r->visibility === 'circle');
    }

    private function title(Incident $incident, int $distanceM): string
    {
        $emoji = match ($incident->severity) {
            'high'   => '🔴',
            'medium' => '🟠',
            default  => '🟡',
        };

        // §6.7 — « signalé », jamais « danger »
        return "{$emoji} {$incident->label()} signalé à " . $this->formatDistance($distanceM);
    }

    private function body(Incident $incident, int $distanceM): string
    {
        if ($incident->report_count > 1) {
            return "Signalé par {$incident->report_count} personnes";
        }

        return 'Alerte signalée près de toi';
    }

    private function formatDistance(int $meters): string
    {
        return $meters < 1000
            ? "{$meters} m"
            : round($meters / 1000, 1) . ' km';
    }
}
