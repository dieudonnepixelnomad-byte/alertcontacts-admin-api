<?php

namespace App\Services\Routes;

use App\Http\Resources\IncidentResource;
use App\Http\Resources\RouteResource;
use App\Models\Incident;
use App\Models\Route;
use App\Services\Routing\DTO\RouteAlternative;
use Illuminate\Support\Collection;

/**
 * Mise en forme des réponses du module Trajets — CDC V4.1 §6.7
 *
 * Les règles de rédaction du §6.7 sont contraignantes, pas des suggestions.
 * Le vocabulaire est donc centralisé ici plutôt que dispersé dans le client :
 *
 *   ❌ « itinéraire sécurisé »   ✅ « contourne la zone signalée »
 *   ❌ « zone évitée à 100 % »   ✅ « contourne la zone signalée »
 *   ❌ « danger »                ✅ « alerte signalée »
 *   ❌ « N personnes confirment »✅ « signalé par N personnes »
 *   ❌ « trajet dangereux »      ✅ « alerte sur ton trajet »
 *
 * Le §1.1 pose que l'application vend de la tranquillité d'esprit : un
 * vocabulaire anxiogène travaille contre le positionnement.
 */
class RoutePresenter
{
    /**
     * @param  Collection<int, array{incident: Incident, min_distance_m: int, distance_from_origin_m: float}>  $hits
     * @return array<string, mixed>
     */
    public function preview(Route $route, Collection $hits, bool $destinationInside): array
    {
        return [
            'route'               => new RouteResource($route),
            'incidents_on_route'  => $hits->map(fn (array $hit) => [
                'incident'               => new IncidentResource($hit['incident']),
                'min_distance_m'         => $hit['min_distance_m'],
                'distance_from_origin_m' => (int) round($hit['distance_from_origin_m']),
                'headline'               => $this->headline($hit['incident']),
                'detail'                 => $this->detail($hit['incident']),
            ])->values(),
            'destination_inside'  => $destinationInside,
            // §5.6 — la destination est dans la zone : on ne propose pas de
            // contournement, on prévient.
            'can_avoid'           => !$destinationInside && $hits->isNotEmpty(),
            'message'             => $this->previewMessage($hits, $destinationInside),
        ];
    }

    /**
     * @param  array<int, array{alternative: RouteAlternative, partial: bool, still_crossed: array<int, int>}>  $evaluations
     * @return array<string, mixed>
     */
    public function avoidance(Route $route, array $evaluations, int $baselineDurationS): array
    {
        $options = [];

        foreach ($evaluations as $index => $evaluation) {
            $alternative = $evaluation['alternative'];
            $delta = $alternative->durationS - $baselineDurationS;

            $options[] = [
                'index'         => $index,
                'label'         => $alternative->label(),
                'polyline'      => $alternative->polyline,
                'distance_m'    => $alternative->distanceM,
                'duration_s'    => $alternative->durationS,
                'delta_s'       => $delta,
                // ✅ contourne · ⚠️ contournement partiel (§6.4)
                'safety_badge'  => $evaluation['partial'] ? 'partial' : 'avoids',
                'safety_label'  => $evaluation['partial']
                    ? 'contournement partiel'
                    : 'contourne la zone signalée',
                'still_crossed' => $evaluation['still_crossed'],
                // §5.6 — au-delà de +50 %, on propose sans imposer
                'detour_excessive' => $this->isDetourExcessive($baselineDurationS, $alternative->durationS),
            ];
        }

        $best = $evaluations[0] ?? null;
        $noAlternative = $best !== null && $best['partial'] && $best['still_crossed'] !== [];

        return [
            'route'                   => new RouteResource($route->refresh()),
            'options'                 => $options,
            'avoidance_partial'       => $best['partial'] ?? false,
            'incidents_still_crossed' => $best['still_crossed'] ?? [],
            'message'                 => $noAlternative
                // §5.6 / UC-08 — jamais d'écran vide ni d'erreur technique
                ? "Aucun itinéraire ne contourne cette zone. Voici le trajet le plus court — reste vigilant."
                : 'Itinéraire mis à jour.',
        ];
    }

    /**
     * « 🔴 Agression signalée sur ton trajet » — §5.4 étape 4
     */
    public function headline(Incident $incident): string
    {
        $emoji = match ($incident->severity) {
            'high'   => '🔴',
            'medium' => '🟠',
            default  => '🟡',
        };

        return "{$emoji} {$incident->label()} signalé sur ton trajet";
    }

    /**
     * « Signalé par 3 personnes » — nuance entre témoignage et vérification.
     */
    public function detail(Incident $incident): string
    {
        $age = $incident->created_at?->diffForHumans(['short' => true]) ?? '';

        return $incident->report_count > 1
            ? "Signalé par {$incident->report_count} personnes · {$age}"
            : "Signalé une fois · {$age}";
    }

    /**
     * @param  Collection<int, array{incident: Incident}>  $hits
     */
    private function previewMessage(Collection $hits, bool $destinationInside): ?string
    {
        if ($destinationInside) {
            return 'Ta destination est dans la zone signalée. Sois prudent à l\'arrivée.';
        }

        if ($hits->isEmpty()) {
            return null; // cas majoritaire : rien à dire, on affiche l'itinéraire
        }

        return $this->headline($hits->first()['incident']);
    }

    private function isDetourExcessive(int $baselineS, int $candidateS): bool
    {
        if ($baselineS <= 0) {
            return false;
        }

        $ratio = (float) config('alertcontacts.routes.detour_warning_ratio', 1.5);

        return $candidateS > $baselineS * $ratio;
    }
}
