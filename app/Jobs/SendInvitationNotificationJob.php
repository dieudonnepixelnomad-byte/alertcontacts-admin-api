<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendInvitationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected User $invitee,
        protected User $inviter,
    ) {}

    public function handle(FirebaseNotificationService $firebaseService): void
    {
        $success = $firebaseService->sendInvitationNotification(
            $this->invitee,
            $this->inviter,
            'contact',
        );

        if (!$success) {
            Log::warning('Échec notification invitation', [
                'invitee_id' => $this->invitee->id,
                'inviter_id' => $this->inviter->id,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job SendInvitationNotificationJob échoué', [
            'invitee_id' => $this->invitee->id,
            'inviter_id' => $this->inviter->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
