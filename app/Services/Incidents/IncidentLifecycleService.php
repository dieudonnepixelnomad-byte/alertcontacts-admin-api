<?php

namespace App\Services\Incidents;

use App\Models\Incident;
use App\Models\IncidentInteraction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'un incident — CDC V4.1 §4.7
 *
 * Le V4.0 prévoyait qu'une alerte expire après un délai fixe, jamais qu'elle
 * soit résolue. Un accident dégagé en 20 minutes continuait donc à dérouter
 * des utilisateurs pendant 1 h 40. Sur une application de sécurité, la
 * confiance perdue ne revient pas.
 *
 * La durée de vie cesse d'être une constante décidée en réunion : elle devient
 * une propriété émergente de l'attention collective, sans modérateur.
 */
class IncidentLifecycleService
{
    public function __construct(
        private readonly RouteAvoidancePolicy $avoidancePolicy,
        private readonly IncidentConfidenceService $confidenceService,
    ) {
    }

    /**
     * « Je le vois aussi ». Idempotent : re-taper ne gonfle plus le compteur.
     *
     * @return array{counted: bool, incident: Incident}
     */
    public function confirm(Incident $incident, User $user): array
    {
        return $this->interact($incident, $user, 'confirm', function (Incident $incident) {
            $incident->increment('confirm_count');
        });
    }

    /**
     * « C'est terminé » — §4.7a. Symétrique du bouton de confirmation.
     *
     * @return array{counted: bool, incident: Incident, resolved: bool}
     */
    public function clear(Incident $incident, User $user): array
    {
        $result = $this->interact($incident, $user, 'clear', function (Incident $incident) {
            $incident->increment('clear_count');
        });

        $incident = $result['incident'];
        $resolved = false;

        $threshold = (int) config('incidents.resolution.clear_threshold', 2);

        if ($incident->status === 'active' && $incident->clear_count >= $threshold) {
            $this->resolve($incident);
            $resolved = true;
        }

        return ['counted' => $result['counted'], 'incident' => $incident, 'resolved' => $resolved];
    }

    /**
     * Signalement d'abus.
     *
     * @return array{counted: bool, incident: Incident}
     */
    public function reportAbuse(Incident $incident, User $user, ?string $reason = null): array
    {
        return $this->interact($incident, $user, 'report', null, $reason);
    }

    /**
     * Passe l'incident en `resolved` : il disparaît de la carte et cesse
     * immédiatement de dérouter qui que ce soit.
     */
    public function resolve(Incident $incident): void
    {
        $incident->status = 'resolved';
        $incident->resolved_at = now();
        $incident->affects_routing = false;
        $incident->save();
    }

    /**
     * Enregistre une interaction unique et applique son effet sur les compteurs.
     *
     * @param  callable(Incident): void|null  $effect
     * @return array{counted: bool, incident: Incident}
     */
    private function interact(
        Incident $incident,
        User $user,
        string $action,
        ?callable $effect = null,
        ?string $reason = null
    ): array {
        $counted = DB::transaction(function () use ($incident, $user, $action, $effect, $reason) {
            $interaction = IncidentInteraction::firstOrCreate(
                ['incident_id' => $incident->id, 'user_id' => $user->id, 'action' => $action],
                ['reason' => $reason]
            );

            // wasRecentlyCreated distingue la première interaction d'un re-tap
            if (!$interaction->wasRecentlyCreated) {
                return false;
            }

            if ($effect !== null) {
                $effect($incident);
            }

            return true;
        });

        $incident->refresh();

        if ($counted) {
            $this->confidenceService->refresh($incident);
            $this->avoidancePolicy->refresh($incident);
        }

        return ['counted' => $counted, 'incident' => $incident];
    }
}
