<?php

namespace Tests\Feature;

use App\Jobs\CheckActiveRoutesAgainstIncidentJob;
use App\Models\AlertReport;
use App\Models\Incident;
use App\Models\Route;
use App\Models\RouteIncidentHit;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Services\Incidents\IncidentClusteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Surveillance pendant le trajet — CDC V4.1 §5.5, §9
 *
 * UC-09 : un incident créé devant un utilisateur en trajet déclenche un push,
 * sans polling, sans batterie supplémentaire et sans appel au moteur de routage.
 */
class RouteMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = [48.8566, 2.3522];
    private const DESTINATION = [48.8738, 2.2950];

    /**
     * UC-09 étapes 4 à 6 — incident devant l'utilisateur → une notification.
     */
    public function test_incident_ahead_notifies_the_traveller_once(): void
    {
        $driver = $this->traveller();
        $route = $this->activeRoute($driver);
        $this->positionAt($driver, self::ORIGIN[0], self::ORIGIN[1]);

        $incident = $this->incidentAt($this->midpoint());

        $fcm = $this->expectNotifications(1);
        (new CheckActiveRoutesAgainstIncidentJob($incident->id))->handle($fcm, app(\App\Services\QuietHoursService::class));

        $hit = RouteIncidentHit::where('route_id', $route->id)->first();
        $this->assertNotNull($hit);
        $this->assertSame('en_route', $hit->detected_phase);
        $this->assertTrue((bool) $hit->notified);
    }

    /**
     * §9.2 — une seule notification par incident et par trajet. Pas de rappel,
     * même si le job est rejoué.
     */
    public function test_replaying_the_job_does_not_notify_twice(): void
    {
        $driver = $this->traveller();
        $this->activeRoute($driver);
        $this->positionAt($driver, self::ORIGIN[0], self::ORIGIN[1]);

        $incident = $this->incidentAt($this->midpoint());
        $quietHours = app(\App\Services\QuietHoursService::class);

        (new CheckActiveRoutesAgainstIncidentJob($incident->id))->handle($this->expectNotifications(1), $quietHours);
        (new CheckActiveRoutesAgainstIncidentJob($incident->id))->handle($this->expectNotifications(0), $quietHours);

        $this->assertSame(1, RouteIncidentHit::count());
    }

    /**
     * §9.1 — jamais de push si l'incident est derrière l'utilisateur. Seule la
     * portion non parcourue est testée.
     */
    public function test_incident_behind_the_traveller_is_not_notified(): void
    {
        $driver = $this->traveller();
        $this->activeRoute($driver);

        // L'utilisateur est déjà arrivé aux trois quarts du trajet
        $this->positionAt(
            $driver,
            self::ORIGIN[0] + (self::DESTINATION[0] - self::ORIGIN[0]) * 0.9,
            self::ORIGIN[1] + (self::DESTINATION[1] - self::ORIGIN[1]) * 0.9,
        );

        // L'incident apparaît juste après le départ, donc derrière lui
        $incident = $this->incidentAt([
            self::ORIGIN[0] + (self::DESTINATION[0] - self::ORIGIN[0]) * 0.1,
            self::ORIGIN[1] + (self::DESTINATION[1] - self::ORIGIN[1]) * 0.1,
        ]);

        (new CheckActiveRoutesAgainstIncidentJob($incident->id))
            ->handle($this->expectNotifications(0), app(\App\Services\QuietHoursService::class));

        $this->assertSame(0, RouteIncidentHit::count());
    }

    /**
     * §10.2 — la surveillance pendant le trajet est une feature Solo/Famille.
     */
    public function test_free_tier_is_not_monitored(): void
    {
        $driver = $this->traveller('free');
        $this->activeRoute($driver);
        $this->positionAt($driver, self::ORIGIN[0], self::ORIGIN[1]);

        $incident = $this->incidentAt($this->midpoint());

        (new CheckActiveRoutesAgainstIncidentJob($incident->id))
            ->handle($this->expectNotifications(0), app(\App\Services\QuietHoursService::class));

        $this->assertSame(0, RouteIncidentHit::count());
    }

    /**
     * §4.10 règle 1 — on ne prévient jamais quelqu'un de son propre signalement.
     */
    public function test_author_of_the_report_is_not_notified(): void
    {
        $driver = $this->traveller();
        $this->activeRoute($driver);
        $this->positionAt($driver, self::ORIGIN[0], self::ORIGIN[1]);

        $incident = $this->incidentAt($this->midpoint(), $driver);

        (new CheckActiveRoutesAgainstIncidentJob($incident->id))
            ->handle($this->expectNotifications(0), app(\App\Services\QuietHoursService::class));

        $this->assertSame(0, RouteIncidentHit::count());
    }

    public function test_planned_route_is_not_monitored(): void
    {
        $driver = $this->traveller();
        Route::factory()->create(['user_id' => $driver->id, 'status' => 'planned']);
        $this->positionAt($driver, self::ORIGIN[0], self::ORIGIN[1]);

        $incident = $this->incidentAt($this->midpoint());

        (new CheckActiveRoutesAgainstIncidentJob($incident->id))
            ->handle($this->expectNotifications(0), app(\App\Services\QuietHoursService::class));

        $this->assertSame(0, RouteIncidentHit::count());
    }

    // --- Helpers ---

    private function traveller(string $tier = 'solo'): User
    {
        return User::factory()->create(['tier' => $tier, 'fcm_token' => 'token-test']);
    }

    private function activeRoute(User $user): Route
    {
        return Route::factory()->active()->create(['user_id' => $user->id]);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function midpoint(): array
    {
        return [
            (self::ORIGIN[0] + self::DESTINATION[0]) / 2,
            (self::ORIGIN[1] + self::DESTINATION[1]) / 2,
        ];
    }

    /**
     * @param  array{0: float, 1: float}  $at
     */
    private function incidentAt(array $at, ?User $author = null): Incident
    {
        $report = AlertReport::create([
            'user_id'        => ($author ?? User::factory()->create())->id,
            'type'           => 'fire',
            'severity'       => 'high',
            'lat'            => $at[0],
            'lng'            => $at[1],
            'gps_accuracy_m' => 10,
            'was_moving'     => false,
            'visibility'     => 'public',
        ]);

        return app(IncidentClusteringService::class)->attach($report)['incident'];
    }

    private function positionAt(User $user, float $lat, float $lng): void
    {
        DB::table('user_locations')->insert([
            'user_id'            => $user->id,
            'latitude'           => $lat,
            'longitude'          => $lng,
            'captured_at_device' => now(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function expectNotifications(int $times): FirebaseNotificationService
    {
        $mock = Mockery::mock(FirebaseNotificationService::class);
        $mock->shouldReceive('sendNotification')->times($times)->andReturn(true);

        return $mock;
    }
}
