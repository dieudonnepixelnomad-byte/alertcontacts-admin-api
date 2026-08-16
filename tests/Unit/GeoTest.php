<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

/**
 * CDC V4.1 §17 — le point de vérité géométrique du module Incidents/Trajets.
 */
class GeoTest extends TestCase
{
    public function test_haversine_matches_known_distance(): void
    {
        // Notre-Dame → Arc de Triomphe : ~4,63 km à vol d'oiseau
        $distance = Geo::haversine(48.8530, 2.3499, 48.8738, 2.2950);

        $this->assertEqualsWithDelta(4634, $distance, 50);
    }

    public function test_haversine_is_zero_for_identical_points(): void
    {
        $this->assertSame(0.0, Geo::haversine(48.8566, 2.3522, 48.8566, 2.3522));
    }

    public function test_haversine_is_symmetric(): void
    {
        $ab = Geo::haversine(48.85, 2.35, 48.87, 2.29);
        $ba = Geo::haversine(48.87, 2.29, 48.85, 2.35);

        $this->assertEqualsWithDelta($ab, $ba, 0.001);
    }

    /**
     * §17.A — erreur d'aire avec N = 12 : environ 3,4 % par défaut.
     * Elle doit rester conservatrice, c'est-à-dire SOUS-estimer la zone évitée.
     */
    public function test_circle_to_polygon_area_error_stays_under_four_percent(): void
    {
        $radius = 80.0;
        $polygon = Geo::circleToPolygon(48.8566, 2.3522, $radius, 12);

        $this->assertCount(12, $polygon);

        $circleArea = M_PI * $radius ** 2;
        // Aire d'un polygone régulier à N côtés inscrit dans le cercle
        $polygonArea = 0.5 * 12 * $radius ** 2 * sin(2 * M_PI / 12);
        $error = ($circleArea - $polygonArea) / $circleArea;

        $this->assertGreaterThan(0, $error, "L'approximation doit sous-estimer l'aire.");
        $this->assertLessThan(0.04, $error);
    }

    public function test_circle_to_polygon_vertices_sit_on_the_circle(): void
    {
        $radius = 100.0;
        $polygon = Geo::circleToPolygon(48.8566, 2.3522, $radius, 12);

        foreach ($polygon as $vertex) {
            $distance = Geo::haversine(48.8566, 2.3522, $vertex[0], $vertex[1]);
            $this->assertEqualsWithDelta($radius, $distance, 2);
        }
    }

    public function test_bbox_of_applies_margin_in_both_axes(): void
    {
        $bbox = Geo::bboxOf([[48.85, 2.34], [48.87, 2.36]], 1000);

        $this->assertLessThan(48.85, $bbox['south']);
        $this->assertGreaterThan(48.87, $bbox['north']);
        $this->assertLessThan(2.34, $bbox['west']);
        $this->assertGreaterThan(2.36, $bbox['east']);
    }

    public function test_distance_point_to_polyline_projects_onto_the_segment(): void
    {
        // Segment est-ouest, point décalé au nord de son milieu
        $polyline = [[48.8566, 2.3400], [48.8566, 2.3600]];

        $distance = Geo::distancePointToPolyline(48.8575, 2.3500, $polyline);

        // 0,0009° de latitude ≈ 100 m
        $this->assertEqualsWithDelta(100, $distance, 10);
    }

    public function test_point_inside_polygon_has_zero_distance(): void
    {
        $polygon = Geo::circleToPolygon(48.8566, 2.3522, 200, 12);

        $this->assertTrue(Geo::pointInPolygon(48.8566, 2.3522, $polygon));
        $this->assertSame(0.0, Geo::distancePointToPolygon(48.8566, 2.3522, $polygon));
    }

    public function test_point_outside_polygon_has_positive_distance(): void
    {
        $polygon = Geo::circleToPolygon(48.8566, 2.3522, 100, 12);

        $this->assertFalse(Geo::pointInPolygon(48.8700, 2.3522, $polygon));
        $this->assertGreaterThan(1000, Geo::distancePointToPolygon(48.8700, 2.3522, $polygon));
    }

    /**
     * §4.5 — à partir de 4 signalements, l'enveloppe convexe donne l'étendue réelle.
     */
    public function test_convex_hull_drops_interior_points(): void
    {
        $hull = Geo::convexHull([
            [48.850, 2.340],
            [48.860, 2.340],
            [48.860, 2.350],
            [48.850, 2.350],
            [48.855, 2.345], // au centre — doit disparaître
        ]);

        $this->assertCount(4, $hull);
        $this->assertNotContains([48.855, 2.345], $hull);
    }

    public function test_sample_polyline_keeps_endpoints(): void
    {
        $polyline = [];
        for ($i = 0; $i <= 100; $i++) {
            $polyline[] = [48.8566 + $i * 0.0001, 2.3522];
        }

        $sampled = Geo::samplePolyline($polyline, 50);

        $this->assertSame($polyline[0], $sampled[0]);
        $this->assertSame(end($polyline), end($sampled));
        $this->assertLessThan(count($polyline), count($sampled));
    }

    public function test_bbox_intersects_detects_overlap_and_separation(): void
    {
        $a = ['north' => 48.87, 'south' => 48.85, 'east' => 2.36, 'west' => 2.34];
        $overlapping = ['north' => 48.86, 'south' => 48.84, 'east' => 2.35, 'west' => 2.33];
        $disjoint = ['north' => 48.90, 'south' => 48.89, 'east' => 2.40, 'west' => 2.39];

        $this->assertTrue(Geo::bboxIntersects($a, $overlapping));
        $this->assertFalse(Geo::bboxIntersects($a, $disjoint));
    }
}
