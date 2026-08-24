<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirebaseAccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_firebase_login_reports_when_it_creates_the_backend_account(): void
    {
        $payload = [
            'idToken' => 'test-token',
            'userData' => [
                'uid' => 'firebase-reset-user',
                'email' => 'reset@example.test',
                'name' => 'Compte recréé',
                'email_verified' => true,
            ],
        ];

        $this->postJson('/api/auth/firebase-login', $payload)
            ->assertOk()
            ->assertJsonPath('data.account_created', true);

        $this->postJson('/api/auth/firebase-login', $payload)
            ->assertOk()
            ->assertJsonPath('data.account_created', false);

        $this->assertSame(1, User::where('firebase_uid', 'firebase-reset-user')->count());
    }
}
