<?php

namespace App\Services;

use App\Models\SubscriptionAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RevenueCatSubscriptionSyncService
{
    public function synchronize(User $user): array
    {
        try {
        $apiKey = config('services.revenuecat.secret_api_key');
        if (! $apiKey) throw new RuntimeException('REVENUECAT_SECRET_API_KEY n’est pas configurée.');

        $response = Http::baseUrl('https://api.revenuecat.com/v1')->withToken($apiKey)->acceptJson()->timeout(10)
            ->get('/subscribers/' . rawurlencode($user->firebase_uid));
        if (! $response->successful()) throw new RuntimeException('RevenueCat a répondu HTTP ' . $response->status() . '.');

        $entitlement = data_get($response->json(), 'subscriber.entitlements.' . config('services.revenuecat.entitlement_id', 'premium'));
        $expires = data_get($entitlement, 'expires_date') ? Carbon::parse(data_get($entitlement, 'expires_date')) : null;
        $active = is_array($entitlement) && ($expires === null || $expires->isFuture());
        $previousTier = $user->tier ?? 'free';
        $productId = data_get($entitlement, 'product_identifier');

        DB::transaction(function () use ($user, $active, $expires, $productId, $previousTier, $entitlement, $response) {
            if ($active) {
                DB::table('subscriptions')->updateOrInsert(['user_id' => $user->id, 'payment_provider' => 'revenuecat'], [
                    'tier' => 'premium', 'billing_cycle' => str_contains((string) $productId, 'annual') ? 'annual' : 'monthly',
                    'external_subscription_id' => data_get($entitlement, 'original_purchase_date') ?? $productId,
                    'trial_active' => false, 'trial_ends_at' => null, 'current_period_ends_at' => $expires ?? now()->addMonth(),
                    'status' => 'active', 'updated_at' => now(), 'created_at' => now(),
                ]);
                $user->update(['tier' => 'premium']);
            } else {
                DB::table('subscriptions')->where('user_id', $user->id)->where('payment_provider', 'revenuecat')->update(['status' => 'expired', 'updated_at' => now()]);
                $user->update(['tier' => 'free']);
            }

            SubscriptionAuditLog::create([
                'user_id' => $user->id, 'event_type' => 'MANUAL_RECONCILIATION', 'outcome' => 'processed', 'product_id' => $productId,
                'previous_tier' => $previousTier, 'resulting_tier' => $active ? 'premium' : 'free',
                'details' => ['entitlement_id' => config('services.revenuecat.entitlement_id'), 'entitlement_active' => $active, 'revenuecat_status' => $response->status()],
                'occurred_at' => now(),
            ]);
        });

        return ['active' => $active, 'tier' => $active ? 'premium' : 'free'];
        } catch (\Throwable $e) {
            SubscriptionAuditLog::create([
                'user_id' => $user->id, 'event_type' => 'MANUAL_RECONCILIATION', 'outcome' => 'error',
                'previous_tier' => $user->tier ?? 'free', 'resulting_tier' => $user->tier ?? 'free',
                'details' => ['error' => $e->getMessage()], 'occurred_at' => now(),
            ]);
            throw $e;
        }
    }
}
