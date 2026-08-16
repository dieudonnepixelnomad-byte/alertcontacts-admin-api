<?php

namespace App\Http\Middleware;

use App\Models\Incident;
use App\Services\Routes\AvoidanceQuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gating du contournement — CDC V4.1 §10.2 / §10.4
 *
 * Ne s'applique qu'à POST /routes/{route}/avoid. Tout le reste du module
 * Trajets — calcul, alternatives, incidents affichés, avertissement avant le
 * départ — est gratuit pour tous les tiers.
 *
 * Le contournement d'un danger de gravité Élevé n'est jamais bloqué.
 */
class CheckAvoidanceQuota
{
    public function __construct(private readonly AvoidanceQuotaService $quota)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $incidentIds = (array) $request->input('incident_ids', []);

        if ($incidentIds === []) {
            return $next($request);
        }

        $incidents = Incident::whereIn('id', $incidentIds)->get(['id', 'severity']);
        $check = $this->quota->check($request->user(), $incidents);

        if ($check['allowed']) {
            return $next($request);
        }

        // §10.4 — le mur apparaît au pic de motivation : l'utilisateur voit un
        // incident de gravité Faible ou Moyen sur son trajet et a épuisé ses
        // trois contournements du mois.
        return response()->json([
            'status'  => 'error',
            'message' => 'Tu as utilisé tes ' . $check['limit'] . ' contournements de ce mois.',
            'reason'  => $check['reason'],
            'used'    => $check['used'],
            'limit'   => $check['limit'],
            'upgrade_url' => '/api/subscriptions',
        ], 403);
    }
}
