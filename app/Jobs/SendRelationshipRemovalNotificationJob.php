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

class SendRelationshipRemovalNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected User $recipient,
        protected User $removedBy,
    ) {}

    public function handle(FirebaseNotificationService $firebaseService): void
    {
        $success = $firebaseService->sendRelationshipRemovalNotification(
            $this->recipient,
            $this->removedBy,
        );

        if (!$success) {
            Log::warning('Échec notification de suppression de proche', [
                'recipient_id' => $this->recipient->id,
                'removed_by_id' => $this->removedBy->id,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job de notification de suppression de proche échoué', [
            'recipient_id' => $this->recipient->id,
            'removed_by_id' => $this->removedBy->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
