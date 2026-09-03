<?php

namespace App\Console\Commands;

use App\Models\AlertReport;
use App\Models\DangerZone;
use App\Models\Invitation;
use App\Models\Relationship;
use App\Models\Route;
use App\Models\SafeZone;
use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PostHogBackfillCommand extends Command
{
    protected $signature = 'analytics:posthog-backfill
                            {--days=180 : Number of days of historical data to backfill}
                            {--limit= : Maximum users to enrich}
                            {--dry-run : Show counts without sending anything}
                            {--force : Send without confirmation}';

    protected $description = 'Backfill server-side AlertContacts analytics into PostHog without requiring a mobile app update';

    public function handle(PostHogService $posthog): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $since = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $counts = [
            'person_properties' => $this->usersQuery($limit)->count(),
            'contact_invited' => Invitation::where('created_at', '>=', $since)->count(),
            'contact_invitation_accepted' => Relationship::accepted()->where('accepted_at', '>=', $since)->count(),
            'aha_1_contact_accepted' => Relationship::accepted()->where('accepted_at', '>=', $since)->count(),
            'zone_created' => SafeZone::where('created_at', '>=', $since)->count(),
            'community_alert_created_v1' => AlertReport::where('created_at', '>=', $since)->count(),
            'community_alert_created_legacy' => DangerZone::whereNotNull('reported_by')->where('created_at', '>=', $since)->count(),
            'route_previewed' => Route::where('created_at', '>=', $since)->count(),
            'route_started' => Route::whereNotNull('started_at')->where('started_at', '>=', $since)->count(),
            'route_avoidance_requested' => Route::where('avoidance_applied', true)->where('updated_at', '>=', $since)->count(),
            'subscription_events' => DB::table('subscription_audit_logs')->where('occurred_at', '>=', $since)->count(),
        ];

        $this->table(['Item', 'Count'], collect($counts)->map(fn ($count, $name) => [$name, $count])->all());

        if ($dryRun) {
            $this->info('Dry run complete. No PostHog events were sent.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Send these historical analytics events to PostHog?')) {
            $this->warn('Backfill cancelled.');
            return self::FAILURE;
        }

        $this->backfillPersonProperties($posthog, $limit);
        $this->backfillInvitations($posthog, $since);
        $this->backfillRelationships($posthog, $since);
        $this->backfillSafeZones($posthog, $since);
        $this->backfillCommunityAlerts($posthog, $since);
        $this->backfillRoutes($posthog, $since);
        $this->backfillSubscriptions($posthog, $since);

        $this->info('PostHog backfill queued. Laravel will send events during command termination.');

        return self::SUCCESS;
    }

    private function backfillPersonProperties(PostHogService $posthog, ?int $limit): void
    {
        $this->usersQuery($limit)->chunkById(100, function ($users) use ($posthog): void {
            foreach ($users as $user) {
                $contactsCount = $user->myContacts()->count();
                $posthog->setPersonProperties($user, [
                    'subscription_tier' => $user->tier ?? 'free',
                    'has_active_contact' => $contactsCount > 0,
                    'contacts_count_bucket' => $this->countBucket($contactsCount),
                    'safe_zones_count_bucket' => $this->countBucket(
                        SafeZone::where('owner_id', $user->id)->where('is_active', true)->count()
                    ),
                    'danger_zones_count_bucket' => $this->countBucket(
                        DangerZone::where('reported_by', $user->id)->count()
                    ),
                    'is_paying' => $user->isPaidTier(),
                    'has_premium_access' => $user->hasPremiumAccess(),
                    'auth_provider' => $user->provider ?? 'firebase',
                ]);
            }
        });
    }

    private function backfillInvitations(PostHogService $posthog, $since): void
    {
        Invitation::with('inviter')
            ->where('created_at', '>=', $since)
            ->chunkById(100, function ($invitations) use ($posthog): void {
                foreach ($invitations as $invitation) {
                    if (! $invitation->inviter) {
                        continue;
                    }

                    $posthog->capture($invitation->inviter, 'contact_invited', [
                        'share_level' => $invitation->default_share_level ?? 'alert_only',
                        'has_suggested_zones' => ! empty($invitation->suggested_zones),
                        'requires_pin' => $invitation->pin !== null,
                        'status' => $invitation->status ?? 'pending',
                    ], $invitation->created_at, $this->eventUuid('invitation', $invitation->id, 'contact_invited'));
                }
            });
    }

    private function backfillRelationships(PostHogService $posthog, $since): void
    {
        Relationship::with('user')
            ->accepted()
            ->where('accepted_at', '>=', $since)
            ->chunkById(100, function ($relationships) use ($posthog): void {
                foreach ($relationships as $relationship) {
                    if (! $relationship->user) {
                        continue;
                    }

                    $timestamp = $relationship->accepted_at ?? $relationship->created_at;
                    $posthog->capture($relationship->user, 'contact_invitation_accepted', [
                        'role' => 'relationship_owner',
                        'share_level' => $relationship->share_level ?? 'alert_only',
                        'can_see_me' => (bool) $relationship->can_see_me,
                    ], $timestamp, $this->eventUuid('relationship', $relationship->id, 'contact_invitation_accepted'));
                    $posthog->capture($relationship->user, 'aha_1_contact_accepted', [
                        'role' => 'relationship_owner',
                    ], $timestamp, $this->eventUuid('relationship', $relationship->id, 'aha_1_contact_accepted'));
                }
            });
    }

    private function backfillSafeZones(PostHogService $posthog, $since): void
    {
        SafeZone::with('owner')
            ->where('created_at', '>=', $since)
            ->chunkById(100, function ($zones) use ($posthog): void {
                foreach ($zones as $zone) {
                    if (! $zone->owner) {
                        continue;
                    }

                    $assignedCount = $zone->assignments()->where('is_active', true)->count();
                    $posthog->capture($zone->owner, 'zone_created', [
                        'zone_type' => $zone->isCircle() ? 'circle' : 'polygon',
                        'icon' => $zone->icon ?? 'unknown',
                        'radius_bucket' => $this->radiusBucket((int) ($zone->radius_m ?? 0)),
                        'has_contacts' => $assignedCount > 0,
                        'assigned_contacts_count_bucket' => $this->countBucket($assignedCount),
                    ], $zone->created_at, $this->eventUuid('safe_zone', $zone->id, 'zone_created'));
                }
            });
    }

    private function backfillCommunityAlerts(PostHogService $posthog, $since): void
    {
        AlertReport::with('user')
            ->where('created_at', '>=', $since)
            ->chunkById(100, function ($reports) use ($posthog): void {
                foreach ($reports as $report) {
                    if (! $report->user) {
                        continue;
                    }

                    $posthog->capture($report->user, 'community_alert_created', [
                        'gravity' => $report->severity,
                        'type' => $report->type,
                        'visibility' => $report->visibility ?? 'public',
                        'was_moving' => (bool) $report->was_moving,
                        'gps_accuracy_bucket' => $this->accuracyBucket($report->gps_accuracy_m),
                    ], $report->created_at, $this->eventUuid('alert_report', $report->id, 'community_alert_created'));
                }
            });

        DangerZone::with('reporter')
            ->whereNotNull('reported_by')
            ->where('created_at', '>=', $since)
            ->chunkById(100, function ($zones) use ($posthog): void {
                foreach ($zones as $zone) {
                    if (! $zone->reporter) {
                        continue;
                    }

                    $posthog->capture($zone->reporter, 'community_alert_created', [
                        'gravity' => $zone->severity,
                        'type' => $zone->danger_type,
                        'visibility' => $zone->visibility ?? 'public',
                        'is_anonymous' => (bool) ($zone->is_anonymous ?? false),
                        'radius_bucket' => $this->radiusBucket((int) $zone->radius_m),
                    ], $zone->created_at, $this->eventUuid('danger_zone', $zone->id, 'community_alert_created'));
                }
            });
    }

    private function backfillRoutes(PostHogService $posthog, $since): void
    {
        Route::with('user')
            ->where('created_at', '>=', $since)
            ->chunkById(100, function ($routes) use ($posthog): void {
                foreach ($routes as $route) {
                    if (! $route->user) {
                        continue;
                    }

                    $posthog->capture($route->user, 'route_previewed', [
                        'transport_mode' => $route->transport_mode,
                        'incident_count_bucket' => $this->countBucket($route->hits()->count()),
                        'distance_bucket' => $this->distanceBucket((int) $route->distance_m),
                        'duration_bucket' => $this->durationBucket((int) $route->duration_s),
                    ], $route->created_at, $this->eventUuid('route', $route->id, 'route_previewed'));

                    if ($route->started_at !== null) {
                        $posthog->capture($route->user, 'route_started', [
                            'transport_mode' => $route->transport_mode,
                            'avoidance_applied' => (bool) $route->avoidance_applied,
                            'avoidance_partial' => (bool) $route->avoidance_partial,
                        ], $route->started_at, $this->eventUuid('route', $route->id, 'route_started'));
                    }

                    if ($route->avoidance_applied) {
                        $posthog->capture($route->user, 'route_avoidance_requested', [
                            'transport_mode' => $route->transport_mode,
                            'avoidance_partial' => (bool) $route->avoidance_partial,
                        ], $route->updated_at, $this->eventUuid('route', $route->id, 'route_avoidance_requested'));
                    }
                }
            });
    }

    private function backfillSubscriptions(PostHogService $posthog, $since): void
    {
        DB::table('subscription_audit_logs')
            ->where('occurred_at', '>=', $since)
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunk(100, function ($logs) use ($posthog): void {
                foreach ($logs as $log) {
                    $user = User::find($log->user_id);
                    if (! $user) {
                        continue;
                    }

                    $eventName = $this->subscriptionEventName((string) $log->event_type);
                    if ($eventName === null) {
                        continue;
                    }

                    $billing = str_contains((string) $log->product_id, 'annual') ? 'annual' : 'monthly';
                    $posthog->capture($user, $eventName, [
                        'tier' => $log->resulting_tier ?? $user->tier ?? 'free',
                        'billing' => $billing,
                        'revenuecat_event_type' => (string) $log->event_type,
                    ], new \DateTimeImmutable((string) $log->occurred_at), $this->eventUuid('subscription_audit_log', $log->id, $eventName));
                }
            });
    }

    private function usersQuery(?int $limit)
    {
        $query = User::query()->whereNotNull('firebase_uid')->orderBy('id');

        return $limit !== null ? $query->limit($limit) : $query;
    }

    private function subscriptionEventName(string $type): ?string
    {
        return match ($type) {
            'TRIAL_STARTED' => 'subscription_trial_started',
            'INITIAL_PURCHASE', 'TRIAL_CONVERTED', 'PRODUCT_CHANGE', 'UNCANCELLATION' => 'subscription_purchased',
            'RENEWAL' => 'subscription_renewed',
            'CANCELLATION' => 'subscription_cancelled',
            'EXPIRATION', 'TRIAL_CANCELLED' => 'subscription_expired',
            default => null,
        };
    }

    private function eventUuid(string $source, int|string $id, string $event): string
    {
        $hash = md5("alertcontacts:posthog:{$source}:{$id}:{$event}");

        return substr($hash, 0, 8) . '-'
            . substr($hash, 8, 4) . '-'
            . substr($hash, 12, 4) . '-'
            . substr($hash, 16, 4) . '-'
            . substr($hash, 20, 12);
    }

    private function countBucket(int $count): string
    {
        if ($count <= 1) {
            return (string) $count;
        }
        if ($count <= 3) {
            return '2-3';
        }

        return '4+';
    }

    private function radiusBucket(int $radius): string
    {
        if ($radius < 100) {
            return '<100m';
        }
        if ($radius <= 200) {
            return '100-200m';
        }
        if ($radius <= 500) {
            return '201-500m';
        }

        return '>500m';
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

    private function distanceBucket(int $meters): string
    {
        if ($meters < 1000) {
            return '<1km';
        }
        if ($meters < 5000) {
            return '1-5km';
        }
        if ($meters < 15000) {
            return '5-15km';
        }

        return '>15km';
    }

    private function durationBucket(int $seconds): string
    {
        if ($seconds < 300) {
            return '<5min';
        }
        if ($seconds < 900) {
            return '5-15min';
        }
        if ($seconds < 1800) {
            return '15-30min';
        }

        return '>30min';
    }
}
