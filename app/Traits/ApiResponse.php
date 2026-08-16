<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Format de réponse JSON uniforme — prescrit par le CLAUDE.md backend.
 *
 * Le repo comptait deux enveloppes concurrentes avant V4.1 :
 * `{status, data}` (AlertController) et `{success, data|error}`
 * (DangerZonesController). Tout le code V4.1 utilise celle-ci.
 */
trait ApiResponse
{
    protected function success(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $data], $status);
    }

    protected function error(
        string $message,
        int $status = 400,
        array $errors = [],
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ], $extra), $status);
    }
}
