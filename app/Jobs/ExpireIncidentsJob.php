<?php

namespace App\Jobs;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Expiration des incidents — CDC V4.1 §8.3
 *
 * Planifié toutes les minutes. Passe en 'expired' les incidents dont
 * expires_at est dépassé, et en 'rejected' ceux qui n'ont jamais été
 * reconfirmés (§4.10 règle 5) — ce qui pénalise légèrement la réputation
 * implicite de leur auteur via IncidentConfidenceService.
 */
class ExpireIncidentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('alerts');
    }

    public function handle(): void
    {
        $rejectLonely = (bool) config('incidents.abuse.reject_if_lonely', true);

        $expired = 0;
        $rejected = 0;

        Incident::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->chunkById(200, function ($incidents) use ($rejectLonely, &$expired, &$rejected) {
                foreach ($incidents as $incident) {
                    $isLonely = $rejectLonely
                        && $incident->report_count <= 1
                        && $incident->confirm_count === 0;

                    $incident->status = $isLonely ? 'rejected' : 'expired';
                    $incident->affects_routing = false;
                    $incident->save();

                    $isLonely ? $rejected++ : $expired++;
                }
            });

        if ($expired + $rejected > 0) {
            Log::info('[ExpireIncidentsJob] incidents clôturés', [
                'expired'  => $expired,
                'rejected' => $rejected,
            ]);
        }
    }
}
