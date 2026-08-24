<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueCatWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.revenuecat.webhook_secret', 'test-revenuecat-secret');
        config()->set('services.revenuecat.products', ['premium_monthly', 'premium_annual']);
    }

    public function test_initial_purchase_activates_premium_and_is_idempotent(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'firebase-user-1', 'tier' => 'free']);
        $payload = ['event' => [
            'id' => 'event-1',
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => 'firebase-user-1',
            'product_id' => 'premium_monthly',
            'original_transaction_id' => 'transaction-1',
            'expiration_at_ms' => now()->addMonth()->getTimestamp() * 1000,
        ]];

        $this->withHeader('Authorization', 'test-revenuecat-secret')
            ->postJson('/api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertSame('premium', $user->fresh()->tier);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'tier' => 'premium',
            'payment_provider' => 'revenuecat',
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'test-revenuecat-secret')
            ->postJson('/api/webhooks/revenuecat', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('revenuecat_events', 1);
    }

    public function test_expiration_returns_user_to_free(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'firebase-user-2', 'tier' => 'premium']);

        $this->withHeader('Authorization', 'test-revenuecat-secret')
            ->postJson('/api/webhooks/revenuecat', ['event' => [
                'id' => 'event-2',
                'type' => 'EXPIRATION',
                'app_user_id' => 'firebase-user-2',
                'product_id' => 'premium_annual',
            ]])
            ->assertOk();

        $this->assertSame('free', $user->fresh()->tier);
    }

    public function test_webhook_rejects_an_invalid_secret(): void
    {
        $this->postJson('/api/webhooks/revenuecat', ['event' => []])
            ->assertUnauthorized();
    }
}
