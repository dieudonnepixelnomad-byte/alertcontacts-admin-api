<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMinimumAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $platform = strtolower((string) $request->header('X-App-Platform'));
        if (!in_array($platform, ['android', 'ios'], true)) {
            return $next($request);
        }

        $minimumBuild = (int) config("alertcontacts.app_updates.minimum_{$platform}_build", 0);
        $build = filter_var($request->header('X-App-Build'), FILTER_VALIDATE_INT);

        if ($minimumBuild <= 0 || ($build !== false && $build >= $minimumBuild)) {
            return $next($request);
        }

        $storeUrl = (string) config("alertcontacts.app_updates.{$platform}_store_url", '');

        return response()->json([
            'code' => 'UPDATE_REQUIRED',
            'message' => 'Cette version de l’application n’est plus prise en charge.',
            'minimum_build' => $minimumBuild,
            'store_url' => $storeUrl,
        ], 426)->header('X-Update-Store-Url', $storeUrl);
    }
}
