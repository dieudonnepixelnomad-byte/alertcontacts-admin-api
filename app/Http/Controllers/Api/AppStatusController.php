<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

class AppStatusController extends Controller
{
    /**
     * @OA\Get(
     *      path="/app-status",
     *      operationId="getAppStatus",
     *      tags={"App"},
     *      summary="Get application status and required versions",
     *      description="Returns the minimum required versions for mobile clients and store URLs.",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="android",
     *                  type="object",
     *                  @OA\Property(property="min_version", type="string", example="1.0.0"),
     *                  @OA\Property(property="store_url", type="string", example="https://play.google.com/store/apps/details?id=com.yourapp.package")
     *              ),
     *              @OA\Property(
     *                  property="ios",
     *                  type="object",
     *                  @OA\Property(property="min_version", type="string", example="1.0.0"),
     *                  @OA\Property(property="store_url", type="string", example="https://apps.apple.com/app/your-app-name/id123456789")
     *              )
     *          )
     *      )
     * )
     */
    public function __invoke(): JsonResponse
    {
        $settings = AppSetting::all()->pluck('value', 'key');

        $response = [
            'android' => [
                'min_version' => $settings->get('android_min_version', '1.0.0'),
                'store_url' => $settings->get('android_store_url', ''),
            ],
            'ios' => [
                'min_version' => $settings->get('ios_min_version', '1.0.0'),
                'store_url' => $settings->get('ios_store_url', ''),
            ],
        ];

        return response()->json($response);
    }
}