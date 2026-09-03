<?php

namespace App\Services\Incidents;

use App\Http\Resources\IncidentResource;
use App\Jobs\IncidentClusteringJob;
use App\Models\AlertReport;
use App\Models\User;
use App\Services\PostHogService;

/**
 * Soumission d'un signalement — CDC V4.1 §8.2
 *
 * Le parcours utilisateur reste en trois taps (§4.6). Tout le travail de
 * géométrie, de clustering et de confiance se fait en asynchrone.
 */
class ReportSubmissionService
{
    public function __construct(
        private readonly IncidentClusteringService $clustering,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{report_id: int, incident_id: int|null, was_merged: bool}
     */
    public function submit(User $user, array $data, string $severity): array
    {
        $report = AlertReport::create([
            'user_id'        => $user->id,
            'type'           => $data['type'],
            'severity'       => $severity,
            'lat'            => $data['lat'],
            'lng'            => $data['lng'],
            'gps_accuracy_m' => $data['gps_accuracy_m'] ?? null,
            'gps_trace'      => $data['gps_trace'] ?? null,
            'was_moving'     => $data['was_moving'] ?? false,
            'speed_kmh'      => $data['speed_kmh'] ?? null,
            'comment'        => $data['comment'] ?? null,
            'photo_url'      => $data['photo_url'] ?? null,
            'visibility'     => $data['visibility'] ?? 'public',
        ]);

        // Le clustering est fait en synchrone pour pouvoir répondre l'incident_id
        // au client, qui doit afficher la fiche immédiatement. Le reste
        // (diffusion push, surveillance des trajets) part en file.
        $result = $this->clustering->attach($report);
        $incident = $result['incident'];

        IncidentClusteringJob::dispatch($report->id, $result['merged'], $result['routing_changed'])
            ->afterCommit();

        app(PostHogService::class)->capture($user, 'community_alert_created', [
            'gravity' => $severity,
            'type' => $report->type,
            'visibility' => $report->visibility ?? 'public',
            'was_merged' => (bool) $result['merged'],
            'routing_changed' => (bool) $result['routing_changed'],
            'was_moving' => (bool) $report->was_moving,
            'gps_accuracy_bucket' => $this->accuracyBucket($report->gps_accuracy_m),
        ]);

        return [
            'report_id'   => $report->id,
            'incident_id' => $incident->id,
            'was_merged'  => $result['merged'],
            'incident'    => (new IncidentResource($incident))->resolve(request()),
        ];
    }

    /**
     * Un incident compatible existe-t-il déjà ici ? — §6.6
     *
     * @param  array<string, mixed>  $data
     * @return array{found: bool, incident: array|null}
     */
    public function findDuplicate(array $data): array
    {
        $incident = $this->clustering->findCompatibleIncident(
            $data['type'],
            (float) $data['lat'],
            (float) $data['lng']
        );

        return [
            'found'    => $incident !== null,
            'incident' => $incident !== null
                ? (new IncidentResource($incident))->resolve(request())
                : null,
        ];
    }

    private function accuracyBucket(int|float|null $accuracy): string
    {
        if ($accuracy === null) {
            return 'unknown';
        }
        if ($accuracy <= 10) {
            return '<=10m';
        }
        if ($accuracy <= 30) {
            return '11-30m';
        }
        if ($accuracy <= 100) {
            return '31-100m';
        }

        return '>100m';
    }
}
