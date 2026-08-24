<?php

namespace App\Services\Incidents;

use App\Models\AlertReport;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;

/**
 * Retrait volontaire d'un signalement par son auteur.
 *
 * L'incident est une agrégation : supprimer un témoignage ne doit donc ni
 * effacer ceux des autres utilisateurs, ni conserver ses compteurs et sa
 * géométrie devenus inexacts.
 */
class ReportDeletionService
{
    public function __construct(
        private readonly IncidentGeometryBuilder $geometryBuilder,
        private readonly IncidentConfidenceService $confidenceService,
        private readonly RouteAvoidancePolicy $avoidancePolicy,
    ) {
    }

    public function withdraw(AlertReport $report): void
    {
        DB::transaction(function () use ($report) {
            $lockedReport = AlertReport::query()->lockForUpdate()->findOrFail($report->id);
            $incident = $lockedReport->incident_id === null
                ? null
                : Incident::query()->lockForUpdate()->find($lockedReport->incident_id);

            $lockedReport->delete();

            if ($incident === null) {
                return;
            }

            $reports = $incident->reports()->with('user')->get();
            if ($reports->isEmpty()) {
                $incident->report_count = 0;
                $incident->confidence_score = 0;
                $incident->affects_routing = false;
                if ($incident->status === 'active') {
                    $incident->status = 'rejected';
                    $incident->resolved_at = now();
                }
                $incident->save();

                return;
            }

            $incident->report_count = $reports->count();
            $incident->severity = $this->maxSeverity($reports->pluck('severity')->all());
            $incident->fill($this->geometryBuilder->rebuildFromReports($incident, $reports));
            $incident->save();
            $incident->setRelation('reports', $reports);

            $this->confidenceService->refresh($incident);
            $this->avoidancePolicy->refresh($incident);
        });
    }

    /**
     * @param array<int, string> $severities
     */
    private function maxSeverity(array $severities): string
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
        $best = 'low';

        foreach ($severities as $severity) {
            if (($rank[$severity] ?? 0) > ($rank[$best] ?? 0)) {
                $best = $severity;
            }
        }

        return $best;
    }
}
