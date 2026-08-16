<?php

namespace App\Jobs;

use App\Models\AlertReport;
use App\Services\Incidents\IncidentClusteringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Signalement → incident — CDC V4.1 §8.3
 *
 * Déclencheur : création d'un signalement.
 * Rôle : rattacher le signalement à un incident existant ou en créer un, puis
 * enchaîner la diffusion push et la surveillance des trajets en cours (§5.5).
 *
 * Le rattachement lui-même est déjà fait en synchrone par
 * ReportSubmissionService — l'API doit renvoyer `incident_id` immédiatement
 * pour que le client affiche la fiche. Ce job est donc idempotent : il ne
 * refait le clustering que si le signalement n'a pas encore d'incident, ce qui
 * n'arrive qu'en cas de rejeu après incident serveur.
 */
class IncidentClusteringJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int $reportId,
        public readonly ?bool $merged = null,
        public readonly ?bool $routingChanged = null,
    ) {
        $this->onQueue('alerts');
    }

    public function handle(IncidentClusteringService $clustering): void
    {
        $report = AlertReport::find($this->reportId);

        if ($report === null) {
            return;
        }

        $merged = $this->merged;
        $routingChanged = $this->routingChanged;

        if ($report->incident_id === null) {
            $result = $clustering->attach($report->refresh());
            $merged = $result['merged'];
            $routingChanged = $result['routing_changed'];
            $report->refresh();
        }

        $incident = $report->incident;

        if ($incident === null) {
            return;
        }

        Log::info('[IncidentClusteringJob] post-traitement du signalement', [
            'report_id'       => $report->id,
            'incident_id'     => $incident->id,
            'merged'          => $merged,
            'report_count'    => $incident->report_count,
            'affects_routing' => $incident->affects_routing,
        ]);

        // §9 — un signalement fusionné ne redéclenche pas de diffusion :
        // le quartier a déjà été prévenu.
        if ($merged !== true) {
            NotifyNearbyUsersJob::dispatch($incident->id);
        }

        // §5.5 — l'événement déclencheur de la surveillance est la création d'un
        // incident ou son franchissement du seuil de confiance, jamais un timer.
        if ($routingChanged && $incident->affects_routing) {
            CheckActiveRoutesAgainstIncidentJob::dispatch($incident->id);
        }
    }
}
