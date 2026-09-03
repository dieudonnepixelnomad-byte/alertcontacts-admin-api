<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostHogServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.posthog.project_api_key', 'test-posthog-key');
        config()->set('services.posthog.host', 'https://us.i.posthog.com');
    }

    public function test_capture_uses_firebase_uid_as_distinct_id(): void
    {
        Http::fake();
        $user = User::factory()->create([
            'firebase_uid' => 'firebase-user-123',
            'analytics_consent' => true,
        ]);

        app(PostHogService::class)->capture($user, 'zone_created', [
            'radius_bucket' => '100-200m',
        ]);
        app()->terminate();

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://us.i.posthog.com/capture/'
            && $request['api_key'] === 'test-posthog-key'
            && $request['event'] === 'zone_created'
            && $request['distinct_id'] === 'firebase-user-123'
            && $request['properties']['radius_bucket'] === '100-200m'
            && $request['properties']['source'] === 'laravel'
        );
    }

    public function test_capture_respects_refused_analytics_consent(): void
    {
        Http::fake();
        $user = User::factory()->create([
            'firebase_uid' => 'firebase-user-456',
            'analytics_consent' => false,
        ]);

        app(PostHogService::class)->capture($user, 'zone_created');
        app()->terminate();

        Http::assertNothingSent();
    }

    public function test_set_person_properties_uses_posthog_set_payload(): void
    {
        Http::fake();
        $user = User::factory()->create([
            'firebase_uid' => 'firebase-user-789',
            'analytics_consent' => true,
        ]);

        app(PostHogService::class)->setPersonProperties($user, [
            'subscription_tier' => 'premium',
            'has_active_contact' => true,
        ]);
        app()->terminate();

        Http::assertSent(fn ($request) =>
            $request['event'] === 'backend_person_properties_updated'
            && $request['distinct_id'] === 'firebase-user-789'
            && $request['properties']['$set']['subscription_tier'] === 'premium'
            && $request['properties']['$set']['has_active_contact'] === true
            && $request['properties']['$update_person_last_seen_at'] === false
        );
    }
}
