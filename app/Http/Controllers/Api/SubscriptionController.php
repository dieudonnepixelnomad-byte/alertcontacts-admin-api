<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /**
     * Lecture seule : les abonnements sont créés et modifiés exclusivement par
     * les webhooks RevenueCat vérifiés côté serveur.
     */
    public function index(): JsonResponse
    {
        $sub = DB::table('subscriptions')
            ->where('user_id', Auth::id())
            ->whereIn('status', ['active', 'trialing', 'cancelled'])
            ->orderByDesc('updated_at')
            ->first();

        return response()->json([
            'status' => 'ok',
            'tier' => Auth::user()->tier ?? 'free',
            'data' => $sub ? [
                'tier' => $sub->tier,
                'billing_cycle' => $sub->billing_cycle,
                'status' => $sub->status,
                'trial_active' => (bool) $sub->trial_active,
                'trial_ends_at' => $sub->trial_ends_at,
                'current_period_ends_at' => $sub->current_period_ends_at,
            ] : null,
        ]);
    }
}
