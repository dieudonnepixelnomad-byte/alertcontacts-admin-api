<?php

namespace App\Services\Incidents;

use App\Models\Incident;

/**
 * Qui a le droit de modifier un itinéraire — CDC V4.1 §4.9 + §4.10 + §4.11
 *
 * Toute la politique se condense dans le booléen dérivé `affects_routing`,
 * recalculé à chaque nouveau signalement. Le service de routage n'exécute
 * ensuite plus qu'un `WHERE affects_routing = TRUE AND status = 'active'`.
 * Simple à lire, simple à auditer, modifiable sans toucher au moteur.
 *
 * Position éthique (§4.11) — les signalements portant sur des personnes ne
 * modifient JAMAIS un itinéraire. Rerouter automatiquement autour d'un
 * signalement visant une personne revient à encoder du profilage dans un
 * algorithme de navigation. L'information reste visible, l'utilisateur décide.
 */
class RouteAvoidancePolicy
{
    /**
     * Cet incident est-il autorisé à modifier des trajets ?
     */
    public function shouldAffectRouting(Incident $incident): bool
    {
        // §4.10 règle 3 — seuls les incidents actifs comptent
        if ($incident->status !== 'active') {
            return false;
        }

        $config = $incident->typeConfig();
        $threshold = $config['routing_min_reports'] ?? null;

        // null = ce type n'influence jamais le routage (suspect, other — §4.11)
        if ($threshold === null) {
            return false;
        }

        // §4.10 règle 2 — un signalement isolé et jamais confirmé reste affiché,
        // jamais routé. Le seuil de confiance conditionne l'autorité.
        if ($incident->report_count < (int) $threshold) {
            return false;
        }

        // §4.8 — un fix GPS trop imprécis ne permet pas une décision d'évitement
        $displayOnlyAbove = (int) config('incidents.gps.accuracy_display_only_m', 80);
        $bestAccuracy = $incident->relationLoaded('reports')
            ? $incident->reports->min('gps_accuracy_m')
            : $incident->reports()->min('gps_accuracy_m');

        if ($bestAccuracy !== null && $bestAccuracy > $displayOnlyAbove) {
            return false;
        }

        return true;
    }

    /**
     * Ce type d'incident est-il pertinent pour ce mode de transport ? (§5.6)
     */
    public function appliesToTransportMode(Incident $incident, string $transportMode): bool
    {
        $modes = $incident->typeConfig()['transport_modes'] ?? null;

        return $modes === null || in_array($transportMode, $modes, true);
    }

    /**
     * Recalcule et persiste `affects_routing`. Retourne true si la valeur a changé,
     * ce qui sert de déclencheur à la surveillance en trajet (§5.5).
     */
    public function refresh(Incident $incident): bool
    {
        $next = $this->shouldAffectRouting($incident);

        if ($incident->affects_routing === $next) {
            return false;
        }

        $incident->affects_routing = $next;
        $incident->save();

        return true;
    }
}
