<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Route\PreviewRouteRequest;
use App\Http\Resources\RouteResource;
use App\Models\Route;
use App\Services\Routes\AvoidanceQuotaService;
use App\Services\Routes\RouteHistoryService;
use App\Services\Routes\RoutePlanningService;
use App\Services\Routes\RoutePresenter;
use App\Services\Routing\RoutingException;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Module Trajets — CDC V4.1 §8.1
 */
class RouteController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RoutePlanningService $planning,
        private readonly RoutePresenter $presenter,
        private readonly AvoidanceQuotaService $quota,
        private readonly RouteHistoryService $history,
    ) {
    }

    /**
     * POST /api/v1/routes/preview — 1 appel au moteur de routage
     */
    public function preview(PreviewRouteRequest $request): JsonResponse
    {
        try {
            $preview = $this->planning->preview($request->user(), $request->validated());
        } catch (RoutingException $e) {
            // §5.6 — jamais d'erreur technique brute côté utilisateur
            return $this->error("Impossible de calculer cet itinéraire pour l'instant.", 503);
        }

        return $this->success($this->presenter->preview(
            $preview['route'],
            $preview['hits'],
            $preview['destination_inside']
        ), 201);
    }

    /**
     * POST /api/v1/routes/{route}/avoid — second appel, conditionnel (§5.4 étape 5)
     */
    public function avoid(Request $request, Route $route): JsonResponse
    {
        $this->authorizeRoute($request, $route);

        $validated = $request->validate([
            'incident_ids'   => 'required|array|min:1|max:20',
            'incident_ids.*' => 'integer|exists:incidents,id',
        ]);

        $baseline = (int) $route->duration_s;

        try {
            $avoidance = $this->planning->avoid($route, $validated['incident_ids']);
        } catch (RoutingException $e) {
            return $this->error("Aucun itinéraire ne contourne cette zone pour l'instant.", 503);
        }

        return $this->success(
            $this->presenter->avoidance($route, $avoidance['evaluations'], $baseline)
        );
    }

    /**
     * POST /api/v1/routes/{route}/select
     */
    public function select(Request $request, Route $route): JsonResponse
    {
        $this->authorizeRoute($request, $route);

        $validated = $request->validate(['route_index' => 'required|integer|min:0']);

        return $this->success(new RouteResource(
            $this->history->selectAlternative($route, $validated['route_index'])
        ));
    }

    /**
     * POST /api/v1/routes/{route}/start|end|cancel
     */
    public function start(Request $request, Route $route): JsonResponse
    {
        $this->authorizeRoute($request, $route);

        return $this->success(new RouteResource($this->history->transition($route, 'active')));
    }

    public function end(Request $request, Route $route): JsonResponse
    {
        $this->authorizeRoute($request, $route);

        return $this->success(new RouteResource($this->history->transition($route, 'completed')));
    }

    public function cancel(Request $request, Route $route): JsonResponse
    {
        $this->authorizeRoute($request, $route);

        return $this->success(new RouteResource($this->history->transition($route, 'cancelled')));
    }

    /**
     * GET /api/v1/routes/history — 24 h en Gratuit, 30 j en Solo/Famille (§10.2)
     */
    public function history(Request $request): JsonResponse
    {
        return $this->success(RouteResource::collection($this->history->forUser($request->user())));
    }

    /**
     * GET /api/v1/routes/recent-destinations
     */
    public function recentDestinations(Request $request): JsonResponse
    {
        return $this->success($this->history->recentDestinations($request->user()));
    }

    /**
     * GET /api/v1/routes/avoidance-quota — état du quota, pour le paywall (§10.4)
     */
    public function avoidanceQuota(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'used'  => $this->quota->usedThisMonth($user),
            'limit' => (int) config('alertcontacts.free_tier.avoidances_per_month', 3),
            'tier'  => $user->tier ?? 'free',
        ]);
    }

    private function authorizeRoute(Request $request, Route $route): void
    {
        abort_unless($route->user_id === $request->user()->id, 403, 'Ce trajet ne t\'appartient pas.');
    }
}
