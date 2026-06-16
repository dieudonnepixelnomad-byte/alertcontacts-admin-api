<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\SafeZone;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendZoneAssignmentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected User $assignedUser,
        protected User $owner,
        protected SafeZone $safeZone,
    ) {}

    public function handle(FirebaseNotificationService $firebaseService): void
    {
        $success = $firebaseService->sendZoneAssignmentNotification(
            $this->assignedUser,
            $this->owner,
            $this->safeZone,
        );

        if (!$success) {
            Log::warning('Échec notification assignation zone', [
                'assigned_user_id' => $this->assignedUser->id,
                'owner_id'         => $this->owner->id,
                'safe_zone_id'     => $this->safeZone->id,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job SendZoneAssignmentNotificationJob échoué', [
            'assigned_user_id' => $this->assignedUser->id,
            'safe_zone_id'     => $this->safeZone->id,
            'error'            => $exception->getMessage(),
        ]);
    }
}
