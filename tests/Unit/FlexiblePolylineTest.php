<?php

namespace Tests\Unit;

use App\Support\FlexiblePolyline;
use PHPUnit\Framework\TestCase;

/**
 * CDC V4.1 §14.1 point 4 — décodeur Flexible Polyline côté PHP.
 *
 * Vecteurs issus de l'implémentation de référence heremaps/flexible-polyline.
 */
class FlexiblePolylineTest extends TestCase
{
    public function test_decodes_reference_vector(): void
    {
        // BFoz5xJ67i1B1U1O... : vecteur du README heremaps/flexible-polyline
        $points = FlexiblePolyline::decode('BFoz5xJ67i1B1B7PzIhaxL7Y');

        $this->assertSame([
            [50.10228, 8.69821],
            [50.10201, 8.69567],
            [50.10063, 8.69150],
            [50.09878, 8.68752],
        ], $points);
    }

    public function test_round_trip_preserves_coordinates(): void
    {
        $original = [
            [48.8566, 2.3522],
            [48.8600, 2.3480],
            [48.8738, 2.2950],
        ];

        $decoded = FlexiblePolyline::decode(FlexiblePolyline::encode($original));

        $this->assertCount(3, $decoded);

        foreach ($original as $i => $point) {
            $this->assertEqualsWithDelta($point[0], $decoded[$i][0], 0.0000001);
            $this->assertEqualsWithDelta($point[1], $decoded[$i][1], 0.0000001);
        }
    }

    public function test_round_trip_handles_negative_coordinates(): void
    {
        $original = [[-33.8688, 151.2093], [-34.0000, -58.3816]];

        $decoded = FlexiblePolyline::decode(FlexiblePolyline::encode($original));

        $this->assertEqualsWithDelta(-33.8688, $decoded[0][0], 0.0000001);
        $this->assertEqualsWithDelta(-58.3816, $decoded[1][1], 0.0000001);
    }

    public function test_empty_input_produces_empty_output(): void
    {
        $this->assertSame([], FlexiblePolyline::decode(''));
        $this->assertSame('', FlexiblePolyline::encode([]));
    }

    public function test_invalid_character_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FlexiblePolyline::decode('BFoz5xJ67i1B1B7P!!!');
    }
}
