<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\StoreReportRequest;
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

    public function __construct(private readonly ReportSubmissionService $reports)
    {
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
