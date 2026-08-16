<?php

namespace Tests\Feature;

use App\Models\AlertReport;
use App\Models\Incident;
use App\Models\Route;
use App\Models\RouteIncidentHit;
use App\Models\User;
use App\Services\Incidents\IncidentClusteringService;
use App\Services\Routes\AvoidanceQuotaService;
use App\Services\Routes\RoutePlanningService;
use App\Support\FlexiblePolyline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module Trajets — CDC V4.1 §5.4, §5.6, §10.2
 *
 * Couvre UC-06 (trajet sans incident), UC-07 (contournement accepté) et le
 * gating du §10.2. Le fournisseur de routage utilisé est FakeRoutingProvider :
 * aucune clé HERE n'est nécessaire pour faire tourner ces tests.
 */
class RoutePlanningTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = ['lat' => 48.8566, 'lng' => 2.3522];
    private const DESTINATION = ['lat' => 48.8738, 'lng' => 2.2950];

    private RoutePlanningService $planning;
    private IncidentClusteringService $clustering;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.here.api_key' => null]); // → FakeRoutingProvider

        $this->planning = app(RoutePlanningService::class);
        $this->clustering = app(IncidentClusteringService::class);
    }

    /**
     * UC-06 — le cas majoritaire ne coûte qu'un seul appel au moteur.
     */
    public function test_preview_without_incident_returns_no_hit(): void
    {
        $preview = $this->preview(User::factory()->create());

        $this->assertCount(0, $preview['hits']);
        $this->assertSame(1, Route::count());
        $this->assertGreaterThan(2, count(FlexiblePolyline::decode($preview['route']->polyline)));
    }

    /**
     * UC-07 — un incendie sur le trajet est détecté sans aucun appel externe.
     */
    public function test_incident_on_route_is_detected(): void
    {
        $this->incidentOnRoute();

        $preview = $this->preview(User::factory()->create());

        $this->assertGreaterThanOrEqual(1, $preview['hits']->count());
        $this->assertSame(1, RouteIncidentHit::where('detected_phase', 'pre_departure')->count());
    }

    /**
     * §4.10 règle 1 — une alerte créée par un utilisateur ne modifie jamais son
     * propre itinéraire. Protection anti-auto-manipulation.
     */
    public function test_own_report_never_affects_own_route(): void
    {
        $author = User::factory()->create();
        $this->incidentOnRoute($author);

        $preview = $this->preview($author);

        $this->assertCount(0, $preview['hits']);
    }

    public function test_incident_far_from_route_is_ignored(): void
    {
        $this->reportAt(User::factory()->create(), 'fire', 48.9500, 2.5000);

        $preview = $this->preview(User::factory()->create());

        $this->assertCount(0, $preview['hits']);
    }

    /**
     * §5.4 étape 5 — le second appel n'a lieu que sur action explicite.
     */
    public function test_avoid_updates_the_route_and_records_the_action(): void
    {
        $incident = $this->incidentOnRoute();
        $user = User::factory()->create();
        $preview = $this->preview($user);

        $this->planning->avoid($preview['route'], [$incident->id]);

        $route = $preview['route']->fresh();
        $this->assertTrue((bool) $route->avoidance_applied);
        $this->assertContains($incident->id, $route->avoided_incident_ids);

        $hit = RouteIncidentHit::where('route_id', $route->id)
            ->where('incident_id', $incident->id)
            ->first();

        $this->assertContains($hit->user_action, ['avoided', 'no_alternative']);
        $this->assertNotNull($hit->acted_at);
    }

    /**
     * §10.3b — le contournement d'un danger vital est gratuit, illimité, sans
     * condition. On monétise le confort, jamais la survie.
     */
    public function test_high_severity_avoidance_is_always_free(): void
    {
        $user = User::factory()->create(['tier' => 'free']);
        $incident = $this->incidentOnRoute(); // incendie → gravité high

        $this->exhaustQuota($user);

        $check = app(AvoidanceQuotaService::class)->check($user->fresh(), collect([$incident]));

        $this->assertTrue($check['allowed']);
    }

    /**
     * §10.2 / §10.4 — trois contournements de gravité Faible ou Moyen par mois
     * en tier Gratuit, puis paywall au pic de motivation.
     */
    public function test_low_severity_avoidance_is_capped_for_free_tier(): void
    {
        $user = User::factory()->create(['tier' => 'free']);
        $incident = $this->lowSeverityIncident();

        $this->exhaustQuota($user, $incident);

        $check = app(AvoidanceQuotaService::class)->check($user->fresh(), collect([$incident]));

        $this->assertFalse($check['allowed']);
        $this->assertSame('avoidance_quota', $check['reason']);
        $this->assertSame(3, $check['used']);
    }

    public function test_paid_tier_has_unlimited_avoidance(): void
    {
        $user = User::factory()->create(['tier' => 'solo']);
        $incident = $this->lowSeverityIncident();

        $this->exhaustQuota($user, $incident);

        $this->assertTrue(app(AvoidanceQuotaService::class)->check($user->fresh(), collect([$incident]))['allowed']);
    }

    /**
     * §5.6 — un contournement impossible faute d'alternative ne doit pas
     * consommer de quota.
     */
    public function test_no_alternative_does_not_consume_quota(): void
    {
        $user = User::factory()->create(['tier' => 'free']);
        $incident = $this->lowSeverityIncident();

        $route = Route::factory()->create(['user_id' => $user->id]);
        RouteIncidentHit::create([
            'route_id'       => $route->id,
            'incident_id'    => $incident->id,
            'detected_phase' => 'pre_departure',
            'user_action'    => 'no_alternative',
            'detected_at'    => now(),
            'acted_at'       => now(),
        ]);

        $this->assertSame(0, app(AvoidanceQuotaService::class)->usedThisMonth($user));
    }

    // --- Helpers ---

    /**
     * @return array{route: Route, hits: \Illuminate\Support\Collection, destination_inside: bool}
     */
    private function preview(User $user): array
    {
        return $this->planning->preview($user, [
            'origin'         => self::ORIGIN,
            'destination'    => self::DESTINATION,
            'transport_mode' => 'car',
        ]);
    }

    /**
     * Incendie placé au milieu du segment origine → destination.
     */
    private function incidentOnRoute(?User $author = null): Incident
    {
        return $this->reportAt(
            $author ?? User::factory()->create(),
            'fire',
            (self::ORIGIN['lat'] + self::DESTINATION['lat']) / 2,
            (self::ORIGIN['lng'] + self::DESTINATION['lng']) / 2,
        );
    }

    private function reportAt(User $user, string $type, float $lat, float $lng): Incident
    {
        $report = AlertReport::create([
            'user_id'        => $user->id,
            'type'           => $type,
            'severity'       => config("incidents.types.{$type}.severity_default"),
            'lat'            => $lat,
            'lng'            => $lng,
            'gps_accuracy_m' => 10,
            'was_moving'     => false,
            'visibility'     => 'public',
        ]);

        return $this->clustering->attach($report)['incident'];
    }

    private function lowSeverityIncident(): Incident
    {
        $user = User::factory()->create();

        // traffic_jam exige 2 signalements pour faire autorité (§4.9)
        $this->reportAt($user, 'traffic_jam', 48.8000, 2.3000);

        return $this->reportAt(User::factory()->create(), 'traffic_jam', 48.80002, 2.30002);
    }

    private function exhaustQuota(User $user, ?Incident $incident = null): void
    {
        $incident = $incident ?? $this->lowSeverityIncident();
        $limit = (int) config('alertcontacts.free_tier.avoidances_per_month', 3);

        for ($i = 0; $i < $limit; $i++) {
            $route = Route::factory()->create(['user_id' => $user->id]);

            RouteIncidentHit::create([
                'route_id'       => $route->id,
                'incident_id'    => $incident->id,
                'detected_phase' => 'pre_departure',
                'user_action'    => 'avoided',
                'detected_at'    => now(),
                'acted_at'       => now(),
            ]);
        }
    }
}
