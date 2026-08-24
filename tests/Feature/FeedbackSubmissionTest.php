<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_submit_feedback(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/feedback', [
                'type' => 'bug',
                'subject' => 'La carte ne se charge pas',
                'message' => 'La carte reste vide après plusieurs tentatives.',
                'rating' => 2,
                'app_version' => '4.1.1+49',
                'device_info' => 'Android 15',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'bug');

        $this->assertDatabaseHas('feedback', [
            'user_id' => $user->id,
            'type' => 'bug',
            'subject' => 'La carte ne se charge pas',
            'rating' => 2,
            'app_version' => '4.1.1+49',
            'device_info' => 'Android 15',
            'status' => 'pending',
        ]);
    }

    public function test_feedback_requires_the_mobile_contract_type_field(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/feedback', [
                'category' => 'bug',
                'message' => 'Un message suffisamment détaillé pour la validation.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }
}
