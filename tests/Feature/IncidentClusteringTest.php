<?php

namespace Tests\Feature;

use App\Models\AlertReport;
use App\Models\Incident;
use App\Models\User;
use App\Services\Incidents\IncidentClusteringService;
use App\Services\Incidents\IncidentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clustering et cycle de vie des incidents — CDC V4.1 §4.5, §4.7, §4.9, §4.11
 *
 * Couvre UC-10 (signalements fusionnés) et UC-11 (résolution communautaire).
 */
class IncidentClusteringTest extends TestCase
{
    use RefreshDatabase;

    private IncidentClusteringService $clustering;
    private IncidentLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clustering = app(IncidentClusteringService::class);
        $this->lifecycle = app(IncidentLifecycleService::class);
    }

    /**
     * UC-10 — Marie, Ahmed et Léa signalent le même incendie à quelques dizaines
     * de mètres et quelques minutes d'écart. Le V4.0 produisait trois cercles
     * superposés avec une confiance diluée à 1 chacun.
     */
    public function test_three_nearby_reports_merge_into_one_incident(): void
    {
        $this->report(User::factory()->create(), 'fire', 48.86980, 2.33250);
        $this->report(User::factory()->create(), 'fire', 48.86985, 2.33322); // ~55 m
        $result = $this->report(User::factory()->create(), 'fire', 48.86988, 2.33375); // ~90 m

        $this->assertSame(1, Incident::count());
        $this->assertTrue($result['merged']);
        $this->assertSame(3, $result['incident']->report_count);
    }

    public function test_distant_report_creates_a_separate_incident(): void
    {
        $this->report(User::factory()->create(), 'fire', 48.86980, 2.33250);
        $result = $this->report(User::factory()->create(), 'fire', 48.87600, 2.35000); // ~1,5 km

        $this->assertSame(2, Incident::count());
        $this->assertFalse($result['merged']);
    }

    public function test_merged_reports_refine_the_centroid(): void
    {
        $this->report(User::factory()->create(), 'fire', 48.86980, 2.33250);
        $result = $this->report(User::factory()->create(), 'fire', 48.87000, 2.33350);

        $incident = $result['incident'];

        // Le centroïde se place entre les deux signalements, pas sur l'un d'eux
        $this->assertGreaterThan(48.86980, $incident->centroid_lat);
        $this->assertLessThan(48.87000, $incident->centroid_lat);
    }

    /**
     * §4.11 — rerouter autour d'un signalement visant une personne revient à
     * encoder du profilage dans un algorithme de navigation. Quel que soit le
     * nombre de témoins, ce type reste affiché et jamais routé.
     */
    public function test_suspect_person_never_affects_routing_whatever_the_count(): void
    {
        $result = null;

        for ($i = 0; $i < 10; $i++) {
            $result = $this->report(User::factory()->create(), 'suspect', 48.85000, 2.30001);
        }

        $this->assertSame(10, $result['incident']->report_count);
        $this->assertFalse((bool) $result['incident']->affects_routing);
    }

    /**
     * §4.9 — l'agression n'influence le routage qu'à partir de trois
     * signalements indépendants.
     */
    public function test_aggression_affects_routing_only_from_three_reports(): void
    {
        $first = $this->report(User::factory()->create(), 'aggression', 48.84000, 2.31000);
        $this->assertFalse((bool) $first['incident']->affects_routing);

        $second = $this->report(User::factory()->create(), 'aggression', 48.84003, 2.31002);
        $this->assertFalse((bool) $second['incident']->affects_routing);

        $third = $this->report(User::factory()->create(), 'aggression', 48.84005, 2.31004);
        $this->assertTrue((bool) $third['incident']->affects_routing);
        $this->assertTrue($third['routing_changed'], 'La bascule doit être signalée pour armer le §5.5.');
    }

    /**
     * §4.9 — l'incendie fait autorité dès le premier signalement.
     */
    public function test_fire_affects_routing_from_the_first_report(): void
    {
        $result = $this->report(User::factory()->create(), 'fire', 48.86980, 2.33250);

        $this->assertTrue((bool) $result['incident']->affects_routing);
    }

    /**
     * Corrige le défaut de AlertController@confirm, qui incrémentait sans
     * condition : re-confirmer gonflait le compteur sans nouveau témoin.
     */
    public function test_confirming_twice_counts_once(): void
    {
        $incident = $this->report(User::factory()->create(), 'fire', 48.86980, 2.33250)['incident'];
        $witness = User::factory()->create();

        $first = $this->lifecycle->confirm($incident, $witness);
        $second = $this->lifecycle->confirm($incident->fresh(), $witness);

        $this->assertTrue($first['counted']);
        $this->assertFalse($second['counted']);
        $this->assertSame(1, $incident->fresh()->confirm_count);
    }

    /**
     * UC-11 — sans ce mécanisme, un accident dégagé continuerait à dérouter
     * des utilisateurs pendant tout le reste de son TTL.
     */
    public function test_two_clears_resolve_the_incident(): void
    {
        $incident = $this->report(User::factory()->create(), 'accident', 48.86980, 2.33250)['incident'];

        $first = $this->lifecycle->clear($incident, User::factory()->create());
        $this->assertFalse($first['resolved']);

        $second = $this->lifecycle->clear($incident->fresh(), User::factory()->create());
        $this->assertTrue($second['resolved']);

        $incident->refresh();
        $this->assertSame('resolved', $incident->status);
        $this->assertFalse((bool) $incident->affects_routing);
        $this->assertNotNull($incident->resolved_at);
    }

    /**
     * §4.7c — un incendie qui dure trois heures reste actif trois heures, sans
     * que quiconque ait eu à choisir une durée.
     */
    public function test_new_report_extends_an_extendable_incident(): void
    {
        $first = $this->report(User::factory()->create(), 'fire', 48.86980, 2.33250);
        $initialExpiry = $first['incident']->expires_at->copy();

        $this->travel(30)->minutes();

        $second = $this->report(User::factory()->create(), 'fire', 48.86985, 2.33270);

        $this->assertTrue($second['incident']->expires_at->greaterThan($initialExpiry));
    }

    /**
     * §4.6 cas 1 — les derniers mètres de la trace du signaleur SONT la
     * géométrie de la voie. Zéro appel externe, géométrie exacte.
     */
    public function test_gps_trace_produces_a_corridor(): void
    {
        $trace = [];
        for ($i = 0; $i < 12; $i++) {
            $trace[] = ['lat' => 48.86980 + $i * 0.0001, 'lng' => 2.33250];
        }

        $result = $this->report(User::factory()->create(), 'accident', 48.86980, 2.33250, [
            'gps_trace'  => $trace,
            'was_moving' => true,
            'speed_kmh'  => 40,
        ]);

        $this->assertSame('corridor', $result['incident']->geometry_type);
        $this->assertGreaterThanOrEqual(2, count($result['incident']->geometryPoints()));
    }

    /**
     * §4.6 cas 2 — sans trace exploitable, repli sur un polygone serré de 80 m,
     * à la frontière du régime d'évitement précis de HERE.
     */
    public function test_without_trace_geometry_falls_back_to_a_tight_polygon(): void
    {
        $result = $this->report(User::factory()->create(), 'accident', 48.86980, 2.33250);

        $this->assertSame('polygon', $result['incident']->geometry_type);
        $this->assertCount(12, $result['incident']->geometryPoints());
    }

    /**
     * §4.8 — un fix GPS trop imprécis autorise l'affichage, jamais le routage.
     */
    public function test_very_poor_gps_accuracy_blocks_routing(): void
    {
        $result = $this->report(User::factory()->create(), 'fire', 48.86980, 2.33250, [
            'gps_accuracy_m' => 150,
        ]);

        $this->assertFalse((bool) $result['incident']->affects_routing);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{incident: Incident, merged: bool, routing_changed: bool}
     */
    private function report(User $user, string $type, float $lat, float $lng, array $extra = []): array
    {
        $report = AlertReport::create(array_merge([
            'user_id'        => $user->id,
            'type'           => $type,
            'severity'       => config("incidents.types.{$type}.severity_default"),
            'lat'            => $lat,
            'lng'            => $lng,
            'gps_accuracy_m' => 10,
            'was_moving'     => false,
            'visibility'     => 'public',
        ], $extra));

        return $this->clustering->attach($report);
    }
}
