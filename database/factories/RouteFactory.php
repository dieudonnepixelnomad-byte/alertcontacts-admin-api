<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\FlexiblePolyline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Route>
 */
class RouteFactory extends Factory
{
    protected $model = \App\Models\Route::class;

    public function definition(): array
    {
        $originLat = 48.8566;
        $originLng = 2.3522;
        $destinationLat = 48.8738;
        $destinationLng = 2.2950;

        $polyline = FlexiblePolyline::encode([
            [$originLat, $originLng],
            [($originLat + $destinationLat) / 2, ($originLng + $destinationLng) / 2],
            [$destinationLat, $destinationLng],
        ]);

        return [
            'user_id'         => User::factory(),
            'origin_lat'      => $originLat,
            'origin_lng'      => $originLng,
            'destination_lat' => $destinationLat,
            'destination_lng' => $destinationLng,
            'transport_mode'  => 'car',
            'polyline'        => $polyline,
            'distance_m'      => 4600,
            'duration_s'      => 900,
            'bbox_south'      => min($originLat, $destinationLat),
            'bbox_north'      => max($originLat, $destinationLat),
            'bbox_west'       => min($originLng, $destinationLng),
            'bbox_east'       => max($originLng, $destinationLng),
            'status'          => 'planned',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active', 'started_at' => now()]);
    }
}
