<?php

namespace App\Services\Routes;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Quota de contournement — CDC V4.1 §10.2
 *
 * Position de principe (§10.1) : facturer à quelqu'un le droit d'éviter un
 * incendie est un risque asymétrique — gain marginal en conversion, exposition
 * majeure en réputation, sur une application dont le positionnement est la
 * sécurité familiale.
 *
 *   > On monétise le confort et la veille continue. Jamais la survie.
 *
 * Conséquence directe : le contournement d'un danger de gravité Élevé est
 * gratuit, illimité, sans condition, pour tout le monde, à vie. Seules les
 * gravités Faible et Moyen sont plafonnées à 3 par mois en tier Gratuit.
 */
class AvoidanceQuotaService
{
    /**
     * L'utilisateur peut-il contourner ces incidents ?
     *
     * @param  \Illuminate\Support\Collection<int, Incident>|array<int, Incident>  $incidents
     * @return array{allowed: bool, used: int, limit: int, reason: string|null}
     */
    public function check(User $user, $incidents): array
    {
        $limit = (int) config('alertcontacts.free_tier.avoidances_per_month', 3);

        // Tiers payants : illimité
        if ($user->hasPremiumAccess()) {
            return ['allowed' => true, 'used' => 0, 'limit' => 0, 'reason' => null];
        }

        // §10.3b — au moins un danger vital dans le lot → gratuit et illimité
        $freeSeverities = (array) config('alertcontacts.routes.free_avoidance_severities', ['high']);

        foreach ($incidents as $incident) {
            if (in_array($incident->severity, $freeSeverities, true)) {
                return ['allowed' => true, 'used' => $this->usedThisMonth($user), 'limit' => $limit, 'reason' => null];
            }
        }

        $used = $this->usedThisMonth($user);

        if ($used < $limit) {
            return ['allowed' => true, 'used' => $used, 'limit' => $limit, 'reason' => null];
        }

        return [
            'allowed' => false,
            'used'    => $used,
            'limit'   => $limit,
            // §10.4 — le paywall apparaît au pic de motivation
            'reason'  => 'avoidance_quota',
        ];
    }

    /**
     * Contournements effectifs du mois calendaire en cours.
     *
     * On compte les contournements réellement obtenus (`user_action` = avoided),
     * jamais les tentatives : un contournement impossible faute d'alternative
     * (§5.6) ne consomme pas de quota.
     */
    public function usedThisMonth(User $user): int
    {
        return (int) DB::table('route_incident_hits as h')
            ->join('routes as r', 'r.id', '=', 'h.route_id')
            ->join('incidents as i', 'i.id', '=', 'h.incident_id')
            ->where('r.user_id', $user->id)
            ->where('h.user_action', 'avoided')
            ->whereNotIn('i.severity', (array) config('alertcontacts.routes.free_avoidance_severities', ['high']))
            ->where('h.acted_at', '>=', now()->startOfMonth())
            ->count();
    }
}
