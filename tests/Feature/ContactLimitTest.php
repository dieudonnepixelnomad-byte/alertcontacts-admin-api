<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Relationship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('alertcontacts.free_tier.contacts_limit', 1);
    }

    public function test_free_user_with_one_accepted_contact_reaches_the_limit(): void
    {
        $user = User::factory()->create(['tier' => 'free']);
        $contact = User::factory()->create(['tier' => 'free']);
        $this->createAcceptedRelationship($user, $contact);

        $this->assertTrue($user->fresh()->hasReachedContactsLimit());
        $this->assertTrue($contact->fresh()->hasReachedContactsLimit());
    }

    public function test_inviter_cannot_create_another_invitation_after_reaching_limit(): void
    {
        $inviter = User::factory()->create(['tier' => 'free']);
        $contact = User::factory()->create(['tier' => 'free']);
        $this->createAcceptedRelationship($inviter, $contact);

        $this->actingAs($inviter)
            ->postJson('/api/invitations', [
                'default_share_level' => 'alert_only',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'SUBSCRIPTION_LIMIT_REACHED');
    }

    public function test_invitee_cannot_accept_when_they_already_have_one_contact(): void
    {
        $inviter = User::factory()->create(['tier' => 'free']);
        $invitee = User::factory()->create(['tier' => 'free']);
        $existingContact = User::factory()->create(['tier' => 'free']);
        $this->createAcceptedRelationship($invitee, $existingContact);

        $invitation = Invitation::createInvitation([
            'inviter_id' => $inviter->id,
            'inviter_name' => $inviter->name,
            'default_share_level' => 'alert_only',
            'suggested_zones' => [],
            'expires_at' => now()->addDay(),
            'max_uses' => 1,
            'require_pin' => false,
        ]);

        $this->actingAs($invitee)
            ->postJson('/api/invitations/accept', [
                'token' => $invitation->token,
                'share_level' => 'alert_only',
                'accept_relation' => true,
                'accepted_zones' => [],
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'SUBSCRIPTION_LIMIT_REACHED');

        $this->assertFalse(
            Relationship::between($inviter->id, $invitee->id)->exists()
        );
    }

    private function createAcceptedRelationship(User $first, User $second): void
    {
        foreach ([[$first, $second], [$second, $first]] as [$user, $contact]) {
            Relationship::create([
                'user_id' => $user->id,
                'contact_id' => $contact->id,
                'status' => 'accepted',
                'share_level' => 'alert_only',
                'can_see_me' => true,
                'accepted_at' => now(),
            ]);
        }
    }
}
