<?php

namespace App\Services\Routes;

use App\Models\Route;
use App\Models\User;
use App\Support\FlexiblePolyline;
use App\Support\Geo;
use Illuminate\Support\Collection;

/**
 * Cycle de vie et historique d'un trajet — CDC V4.1 §8.1 / §10.2
 */
class RouteHistoryService
{
    /**
     * Bascule de statut. `start` fige l'heure de départ, ce qui arme la
     * surveillance du §5.5 : seuls les trajets 'active' sont testés contre les
     * nouveaux incidents.
     */
    public function transition(Route $route, string $status): Route
    {
        $attributes = ['status' => $status];

        if ($status === 'active' && $route->started_at === null) {
            $attributes['started_at'] = now();
        }

        if (in_array($status, ['completed', 'cancelled'], true)) {
            $attributes['ended_at'] = now();
        }

        $route->update($attributes);

        return $route->refresh();
    }

    /**
     * Sélection d'un itinéraire alternatif — recalcule la bbox, dont dépend la
     * surveillance en trajet.
     */
    public function selectAlternative(Route $route, int $index): Route
    {
        $alternatives = $route->alternatives ?? [];

        if (!isset($alternatives[$index])) {
            abort(422, 'Cet itinéraire n\'existe pas.');
        }

        $selected = $alternatives[$index];
        $polyline = FlexiblePolyline::decode($selected['polyline']);
        $bbox = Geo::bboxOf($polyline);

        $route->update([
            'selected_index' => $index,
            'polyline'       => $selected['polyline'],
            'distance_m'     => $selected['distance_m'] ?? $route->distance_m,
            'duration_s'     => $selected['duration_s'] ?? $route->duration_s,
            'bbox_north'     => $bbox['north'],
            'bbox_south'     => $bbox['south'],
            'bbox_east'      => $bbox['east'],
            'bbox_west'      => $bbox['west'],
        ]);

        return $route->refresh();
    }

    /**
     * Historique — 24 h en tier Gratuit, 30 jours en Solo/Famille (§10.2).
     *
     * @return Collection<int, Route>
     */
    public function forUser(User $user): Collection
    {
        $hours = $user->hasPremiumAccess()
            ? (int) config('alertcontacts.routes.history_hours_paid', 720)
            : (int) config('alertcontacts.free_tier.route_history_hours', 24);

        return Route::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    /**
     * Cinq dernières destinations distinctes — §6.2.
     *
     * @return array<int, array{lat: float, lng: float, label: string|null}>
     */
    public function recentDestinations(User $user): array
    {
        $limit = (int) config('alertcontacts.routes.recent_destinations', 5);

        return Route::query()
            ->where('user_id', $user->id)
            ->whereNotNull('destination_label')
            ->orderByDesc('created_at')
            ->limit($limit * 4) // marge pour dédupliquer
            ->get(['destination_lat', 'destination_lng', 'destination_label'])
            ->unique('destination_label')
            ->take($limit)
            ->map(fn (Route $r) => [
                'lat'   => (float) $r->destination_lat,
                'lng'   => (float) $r->destination_lng,
                'label' => $r->destination_label,
            ])
            ->values()
            ->all();
    }
}
