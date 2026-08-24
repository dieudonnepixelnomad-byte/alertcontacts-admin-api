<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class RevenueCatWebhookController extends Controller
{
    private const PURCHASE_EVENTS = ['INITIAL_PURCHASE', 'RENEWAL', 'TRIAL_STARTED', 'TRIAL_CONVERTED', 'UNCANCELLATION'];
    private const EXPIRY_EVENTS   = ['EXPIRATION', 'TRIAL_CANCELLED'];
    private const CANCEL_EVENTS   = ['CANCELLATION'];

    public function handle(Request $request): JsonResponse
    {
        if (! $this->isAuthorized($request)) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $payload = $request->json()->all();
        $event   = $payload['event'] ?? null;

        if (! is_array($event) || ! isset($event['id'], $event['type'], $event['app_user_id'])) {
            return response()->json(['status' => 'invalid_payload'], 422);
        }

        try {
            DB::transaction(function () use ($event) {
                // L'unicité empêche les retries RevenueCat de réactiver ou
                // d'expirer plusieurs fois un même abonnement.
                DB::table('revenuecat_events')->insert([
                    'event_id' => $event['id'],
                    'event_type' => $event['type'],
                    'payload_hash' => hash('sha256', json_encode($event, JSON_THROW_ON_ERROR)),
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->processEvent($event);
            });
        } catch (QueryException $e) {
            // Duplicate event: webhook delivery is at-least-once, so this is OK.
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                return response()->json(['status' => 'ok', 'duplicate' => true]);
            }
            throw $e;
        } catch (\Throwable $e) {
            Log::error('RevenueCat webhook error', ['error' => $e->getMessage(), 'event' => $event['type']]);
            return response()->json(['status' => 'error'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    private function processEvent(array $event): void
    {
        $firebaseUid = $event['app_user_id'];
        $user = User::where('firebase_uid', $firebaseUid)->first();

        if (! $user) {
            Log::warning('RevenueCat webhook: unknown user', ['firebase_uid' => $firebaseUid]);
            return;
        }

        $type      = $event['type'];
        $productId = $event['product_id'] ?? '';

        if (in_array($type, self::PURCHASE_EVENTS)) {
            $this->activateSubscription($user, $event, $productId);
        } elseif (in_array($type, self::EXPIRY_EVENTS)) {
            $this->expireSubscription($user);
        } elseif (in_array($type, self::CANCEL_EVENTS)) {
            $this->cancelSubscription($user, $event);
        } elseif ($type === 'PRODUCT_CHANGE') {
            $this->activateSubscription($user, $event, $event['new_product_id'] ?? $productId);
        }
    }

    private function activateSubscription(User $user, array $event, string $productId): void
    {
        $tier = $this->tierFromProductId($productId);
        if (! $tier) {
            Log::warning('RevenueCat webhook: unsupported product', [
                'product_id' => $productId,
                'event_type' => $event['type'],
            ]);
            return;
        }

        $isTrialing      = $event['type'] === 'TRIAL_STARTED';
        $expirationMs    = $event['expiration_at_ms'] ?? null;
        // RevenueCat transmet les timestamps Unix en millisecondes, alors que
        // DateTime::setTimestamp() attend des secondes. `setTimestampMs()`
        // n'existe pas dans la version Carbon actuellement installée.
        $periodEnd       = $expirationMs
            ? now()->setTimestamp((int) floor(((int) $expirationMs) / 1000))
            : now()->addMonth();
        $annualProductId = config('services.revenuecat.products.1');
        $billingCycle    = $productId === $annualProductId ? 'annual' : 'monthly';
        $rcSubscriptionId = $event['original_transaction_id'] ?? $event['transaction_id'] ?? null;

        DB::table('subscriptions')->updateOrInsert(
            ['user_id' => $user->id, 'payment_provider' => 'revenuecat'],
            [
                'tier'                    => $tier,
                'billing_cycle'           => $billingCycle,
                'external_subscription_id'=> $rcSubscriptionId,
                'trial_active'            => $isTrialing,
                'trial_ends_at'           => $isTrialing ? $periodEnd : null,
                'current_period_ends_at'  => $periodEnd,
                'status'                  => $isTrialing ? 'trialing' : 'active',
                'updated_at'              => now(),
                'created_at'              => now(),
            ]
        );

        $user->update(['tier' => $tier]);
    }

    private function cancelSubscription(User $user, array $event): void
    {
        // Cancelled but still active until period end — keep tier, mark cancelled
        $expirationMs = $event['expiration_at_ms'] ?? null;
        $periodEnd    = $expirationMs
            ? now()->setTimestamp((int) floor(((int) $expirationMs) / 1000))
            : null;

        DB::table('subscriptions')
            ->where('user_id', $user->id)
            ->where('payment_provider', 'revenuecat')
            ->whereIn('status', ['active', 'trialing'])
            ->update(array_filter([
                'status'                 => 'cancelled',
                'current_period_ends_at' => $periodEnd?->toDateTimeString(),
                'updated_at'             => now(),
            ]));
        // Tier stays until expiration event fires
    }

    private function expireSubscription(User $user): void
    {
        DB::table('subscriptions')
            ->where('user_id', $user->id)
            ->where('payment_provider', 'revenuecat')
            ->whereIn('status', ['active', 'trialing', 'cancelled'])
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $user->update(['tier' => 'free']);
    }

    private function tierFromProductId(string $productId): ?string
    {
        $products = config('services.revenuecat.products', []);
        return in_array($productId, $products, true) ? 'premium' : null;
    }

    private function isAuthorized(Request $request): bool
    {
        $secret = config('services.revenuecat.webhook_secret');
        if (! $secret) return ! app()->environment('production');

        return hash_equals($secret, (string) $request->header('Authorization'));
    }
}
