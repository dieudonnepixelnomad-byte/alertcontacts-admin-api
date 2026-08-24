<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\StoreReportRequest;
use App\Http\Resources\MyAlertReportResource;
use App\Models\AlertReport;
use App\Services\Incidents\ReportDeletionService;
use App\Services\Incidents\ReportSubmissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signalements communautaires — CDC V4.1 §8.2
 */
class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReportSubmissionService $reports,
        private readonly ReportDeletionService $deletion,
    ) {
    }

    /**
     * POST /api/v1/reports
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $result = $this->reports->submit($request->user(), $request->validated(), $request->severity());

        return $this->success($result, 201);
    }

    /**
     * GET /api/v1/reports/mine
     *
     * Historique privé des signalements créés par le compte. Il ne dépend ni
     * de la proximité GPS ni du statut actuel de l'incident : un utilisateur
     * doit pouvoir retrouver ce qu'il a signalé après expiration ou résolution.
     */
    public function mine(Request $request): JsonResponse
    {
        $reports = AlertReport::query()
            ->where('user_id', $request->user()->id)
            ->with('incident')
            ->latest()
            ->limit(100)
            ->get();

        return $this->success(MyAlertReportResource::collection($reports));
    }

    /**
     * DELETE /api/v1/reports/{report}
     */
    public function destroy(Request $request, AlertReport $report): JsonResponse
    {
        if ((int) $report->user_id !== (int) $request->user()->id) {
            return $this->error('Signalement introuvable.', 404);
        }

        $this->deletion->withdraw($report);

        return $this->success();
    }

    /**
     * GET /api/v1/reports/duplicate-check — §6.6
     *
     * Transforme un doublon potentiel en confirmation, ce qui renforce la
     * confiance de l'incident au lieu de polluer la carte.
     */
    public function duplicateCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'lat'  => 'required|numeric|between:-90,90',
            'lng'  => 'required|numeric|between:-180,180',
        ]);

        return $this->success($this->reports->findDuplicate($validated));
    }
}
