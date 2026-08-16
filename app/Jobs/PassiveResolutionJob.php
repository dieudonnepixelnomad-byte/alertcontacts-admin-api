<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Services\Incidents\IncidentConfidenceService;
use App\Services\Incidents\IncidentLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Résolution passive — CDC V4.1 §4.7b
 *
 * Si N utilisateurs traversent la zone sans rien signaler, l'incident est
 * probablement terminé. Les positions sont déjà remontées par la stack
 * géoloc existante (§6.3) : coût nul, zéro appel externe, zéro batterie.
 *
 * La confiance est décrémentée automatiquement, et l'incident finit par être
 * résolu si personne ne le reconfirme.
 */
class PassiveResolutionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('alerts');
    }

    public function handle(
        IncidentConfidenceService $confidence,
        IncidentLifecycleService $lifecycle
    ): void {
        if (!config('incidents.resolution.passive.enabled', true)) {
            return;
        }

        $minCrossings = (int) config('incidents.resolution.passive.min_crossings', 3);
        $lookback = (int) config('incidents.resolution.passive.lookback_minutes', 15);
        $minAge = (int) config('incidents.resolution.passive.min_incident_age_minutes', 10);

        $resolved = 0;
        $decayed = 0;

        Incident::query()
            ->where('status', 'active')
            ->where('created_at', '<=', now()->subMinutes($minAge))
            ->chunkById(100, function ($incidents) use (
                $minCrossings, $lookback, $confidence, $lifecycle, &$resolved, &$decayed
            ) {
                foreach ($incidents as $incident) {
                    $crossings = $this->countSilentCrossings($incident, $lookback);

                    if ($crossings < $minCrossings) {
                        continue;
                    }

                    // Autant de traversées silencieuses vaut un « c'est terminé »
                    $incident->increment('clear_count');
                    $incident->refresh();
                    $confidence->refresh($incident);
                    $decayed++;

                    $threshold = (int) config('incidents.resolution.clear_threshold', 2);

                    if ($incident->clear_count >= $threshold) {
                        $lifecycle->resolve($incident);
                        $resolved++;
                    }
                }
            });

        if ($decayed > 0) {
            Log::info('[PassiveResolutionJob] résolution passive', [
                'decayed'  => $decayed,
                'resolved' => $resolved,
            ]);
        }
    }

    /**
     * Nombre d'utilisateurs distincts ayant traversé la zone récemment sans
     * avoir signalé ni confirmé l'incident.
     */
    private function countSilentCrossings(Incident $incident, int $lookbackMinutes): int
    {
        $lat = (float) $incident->centroid_lat;
        $lng = (float) $incident->centroid_lng;
        // On mesure sur le halo d'affichage : traverser à 300 m d'un incendie
        // ne prouve rien, passer dedans si.
        $radius = (int) $incident->display_radius_m;

        return (int) DB::table('user_locations as ul')
            ->where('ul.captured_at_device', '>=', now()->subMinutes($lookbackMinutes))
            ->whereRaw('
                (6371000 * acos(LEAST(1.0,
                    cos(radians(?)) * cos(radians(ul.latitude)) *
                    cos(radians(ul.longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(ul.latitude))
                ))) <= ?
            ', [$lat, $lng, $lat, $radius])
            ->whereNotIn('ul.user_id', function ($sub) use ($incident) {
                $sub->select('user_id')->from('alert_reports')->where('incident_id', $incident->id);
            })
            ->whereNotIn('ul.user_id', function ($sub) use ($incident) {
                $sub->select('user_id')
                    ->from('incident_interactions')
                    ->where('incident_id', $incident->id)
                    ->where('action', 'confirm');
            })
            ->distinct()
            ->count('ul.user_id');
    }
}
