<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionTier
{
    public function handle(Request $request, Closure $next, string ...$tiers): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->tier ?? 'free', $tiers)) {
            return response()->json([
                'status'         => 'error',
                'message'        => 'Abonnement requis',
                'required_tiers' => $tiers,
                'current_tier'   => $user?->tier ?? 'free',
                'upgrade_url'    => '/api/subscriptions',
            ], 403);
        }

        return $next($request);
    }
}
