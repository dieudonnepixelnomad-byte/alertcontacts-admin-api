<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $sub = DB::table('subscriptions')
            ->where('user_id', Auth::id())
            ->whereIn('status', ['active', 'trialing'])
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'status' => 'ok',
            'data'   => $sub ? $this->formatSub((array) $sub) : null,
            'tier'   => Auth::user()->tier ?? 'free',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'tier'             => 'required|in:solo,famille',
            'billing_cycle'    => 'required|in:monthly,annual',
            'payment_provider' => 'required|in:stripe,paydunya',
            'trial'            => 'nullable|boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $data    = $v->validated();
        $isTrial = ($data['trial'] ?? false) && $data['billing_cycle'] === 'annual';
        $now     = now();

        $periodEnd = $data['billing_cycle'] === 'annual'
            ? $now->copy()->addYear()
            : $now->copy()->addMonth();

        $id = DB::table('subscriptions')->insertGetId([
            'user_id'                 => Auth::id(),
            'tier'                    => $data['tier'],
            'billing_cycle'           => $data['billing_cycle'],
            'payment_provider'        => $data['payment_provider'],
            'trial_active'            => $isTrial,
            'trial_ends_at'           => $isTrial ? $now->copy()->addDays(7) : null,
            'current_period_ends_at'  => $periodEnd,
            'status'                  => $isTrial ? 'trialing' : 'active',
            'created_at'              => $now,
            'updated_at'              => $now,
        ]);

        // Mettre à jour le tier sur l'utilisateur
        Auth::user()->update(['tier' => $data['tier']]);

        $sub = DB::table('subscriptions')->find($id);

        return response()->json(['status' => 'ok', 'data' => $this->formatSub((array) $sub)], 201);
    }

    public function familyInvite(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        return response()->json(['status' => 'ok', 'message' => 'Invitation famille envoyée']);
    }

    public function familyRemove(int $member): JsonResponse
    {
        // Vérifier que l'auth est le owner famille — logique complète en V4.1
        return response()->json(['status' => 'ok']);
    }

    private function formatSub(array $sub): array
    {
        return [
            'id'                     => $sub['id'],
            'tier'                   => $sub['tier'],
            'billing_cycle'          => $sub['billing_cycle'],
            'payment_provider'       => $sub['payment_provider'],
            'status'                 => $sub['status'],
            'trial_active'           => (bool) $sub['trial_active'],
            'trial_ends_at'          => $sub['trial_ends_at'],
            'current_period_ends_at' => $sub['current_period_ends_at'],
        ];
    }
}
