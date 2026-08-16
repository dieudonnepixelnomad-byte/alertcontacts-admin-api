<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\NearbyIncidentsRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Services\Incidents\IncidentLifecycleService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Incidents communautaires — CDC V4.1 §8.2
 */
class IncidentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly IncidentLifecycleService $lifecycle)
    {
    }

    /**
     * GET /api/v1/incidents?bbox=south,west,north,east
     */
    public function index(NearbyIncidentsRequest $request): JsonResponse
    {
        $incidents = Incident::active()
            ->inBbox($request->bbox())
            ->orderByDesc('severity')
            ->limit(200)
            ->get();

        return $this->success(IncidentResource::collection($incidents));
    }

    /**
     * GET /api/v1/incidents/{incident}
     */
    public function show(Incident $incident): JsonResponse
    {
        $incident->load('reports');

        return $this->success(new IncidentResource($incident));
    }

    /**
     * POST /api/v1/incidents/{incident}/confirm — « je le vois aussi »
     */
    public function confirm(Request $request, Incident $incident): JsonResponse
    {
        if ($incident->status !== 'active') {
            return $this->error("Cette alerte n'est plus active.", 404);
        }

        $result = $this->lifecycle->confirm($incident, $request->user());

        return $this->success([
            'counted'  => $result['counted'],
            'incident' => new IncidentResource($result['incident']),
        ]);
    }

    /**
     * POST /api/v1/incidents/{incident}/clear — « c'est terminé » (§4.7a)
     */
    public function clear(Request $request, Incident $incident): JsonResponse
    {
        if ($incident->status !== 'active') {
            return $this->error("Cette alerte n'est plus active.", 404);
        }

        $result = $this->lifecycle->clear($incident, $request->user());

        return $this->success([
            'counted'  => $result['counted'],
            'resolved' => $result['resolved'],
            'incident' => new IncidentResource($result['incident']),
        ]);
    }

    /**
     * POST /api/v1/incidents/{incident}/report-abuse
     */
    public function reportAbuse(Request $request, Incident $incident): JsonResponse
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:200']);

        $result = $this->lifecycle->reportAbuse($incident, $request->user(), $validated['reason'] ?? null);

        return $this->success(['counted' => $result['counted']]);
    }
}
