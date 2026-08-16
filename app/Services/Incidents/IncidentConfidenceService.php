<?php

namespace App\Services\Incidents;

use App\Models\AlertReport;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;

/**
 * Confiance et précision de position — CDC V4.1 §4.8
 *
 * Le signalement spontané indépendant a plus de valeur probante que la
 * confirmation par bouton : confirmer suppose d'avoir vu l'alerte puis d'avoir
 * tapé ; signaler spontanément est un acte non sollicité, bien plus difficile
 * à falsifier en volume (§4.5).
 *
 * Le nombre de signalements indépendants est donc le facteur dominant.
 */
class IncidentConfidenceService
{
    /**
     * Score de confiance de l'incident, entre 0.00 et 1.00.
     */
    public function compute(Incident $incident): float
    {
        $reports = $incident->relationLoaded('reports')
            ? $incident->reports
            : $incident->reports()->with('user')->get();

        // Facteur dominant — nombre de signalements indépendants
        $weight = (float) config('incidents.confidence.report_count_weight', 0.25);
        $score = min(1.0, $weight * max(1, $incident->report_count));

        // Confirmations « je le vois aussi » — apport plus faible
        $score += min(0.2, 0.05 * $incident->confirm_count);

        // Résolutions « c'est terminé » — érodent la confiance
        $score -= 0.15 * $incident->clear_count;

        if ($reports->isNotEmpty()) {
            $score += $this->reportsModifier($reports);
        }

        return round(max(0.0, min(1.0, $score)), 2);
    }

    /**
     * Recalcule et persiste le score.
     */
    public function refresh(Incident $incident): float
    {
        $score = $this->compute($incident);

        if ((float) $incident->confidence_score !== $score) {
            $incident->confidence_score = $score;
            $incident->save();
        }

        return $score;
    }

    /**
     * Modificateurs issus de la qualité des signalements eux-mêmes.
     *
     * @param  \Illuminate\Support\Collection<int, AlertReport>  $reports
     */
    private function reportsModifier($reports): float
    {
        $modifier = 0.0;

        // Précision du meilleur fix GPS
        $bestAccuracy = $reports->min('gps_accuracy_m');
        $widenAbove = (int) config('incidents.gps.accuracy_widen_m', 40);
        $displayOnly = (int) config('incidents.gps.accuracy_display_only_m', 80);

        if ($bestAccuracy !== null) {
            if ($bestAccuracy > $displayOnly) {
                $modifier -= 0.20;
            } elseif ($bestAccuracy > $widenAbove) {
                $modifier -= 0.10;
            }
        }

        // À l'arrêt = position plus fiable
        if ($reports->contains(fn (AlertReport $r) => !$r->was_moving)) {
            $modifier += (float) config('incidents.confidence.stationary_bonus', 0.1);
        }

        // Compte récent → pondération réduite. Réputation implicite du signaleur,
        // sans score public.
        $newAccountHours = (int) config('incidents.confidence.new_account_hours', 24);
        $newAccountWeight = (float) config('incidents.confidence.new_account_weight', 0.5);

        $allNew = $reports->every(function (AlertReport $r) use ($newAccountHours) {
            $createdAt = $r->user?->created_at;

            return $createdAt !== null && $createdAt->diffInHours(now()) < $newAccountHours;
        });

        if ($allNew) {
            $modifier -= (1 - $newAccountWeight) * 0.4;
        }

        $modifier += $this->reporterReputation($reports->pluck('user_id')->unique()->all());

        return $modifier;
    }

    /**
     * Réputation implicite : taux de signalements résolus vs rejetés.
     *
     * @param  array<int, int>  $userIds
     */
    private function reporterReputation(array $userIds): float
    {
        if ($userIds === []) {
            return 0.0;
        }

        $stats = DB::table('alert_reports')
            ->join('incidents', 'incidents.id', '=', 'alert_reports.incident_id')
            ->whereIn('alert_reports.user_id', $userIds)
            ->whereIn('incidents.status', ['resolved', 'expired', 'rejected'])
            ->selectRaw("
                SUM(CASE WHEN incidents.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                COUNT(*) AS total
            ")
            ->first();

        if (!$stats || (int) $stats->total < 3) {
            return 0.0; // pas assez d'historique pour juger
        }

        $rejectionRate = (int) $stats->rejected / (int) $stats->total;

        return $rejectionRate > 0.5 ? -0.15 : 0.05;
    }
}
