<?php

namespace App\Http\Middleware;

use App\Services\PostHogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionTier
{
    public function handle(Request $request, Closure $next, string ...$tiers): Response
    {
        $user = $request->user();

        if (!$user || (!$user->hasPremiumAccess() && !in_array($user->tier ?? 'free', $tiers))) {
            app(PostHogService::class)->capture($user, 'premium_action_denied', [
                'feature' => $request->route()?->getName() ?? $request->path(),
                'reason' => 'subscription_tier_required',
                'current_tier' => $user?->tier ?? 'free',
            ]);

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
