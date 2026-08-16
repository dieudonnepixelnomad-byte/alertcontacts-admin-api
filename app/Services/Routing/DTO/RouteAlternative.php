<?php

namespace App\Services\Routing\DTO;

/**
 * Un itinéraire proposé — CDC V4.1 §5.4 étape 2
 */
final class RouteAlternative
{
    /**
     * @param  array<int, string>  $labels        libellés « via A86 », fournis par routeLabels
     * @param  array<int, array{code: string, title: string, severity: string}>  $notices
     */
    public function __construct(
        public readonly string $polyline,
        public readonly int $distanceM,
        public readonly int $durationS,
        public readonly array $labels = [],
        public readonly array $notices = [],
    ) {
    }

    /**
     * Le moteur signale-t-il avoir traversé une zone à éviter ?
     *
     * HERE peut traverser malgré tout : aucun itinéraire alternatif n'existe
     * (pont unique, impasse), ou l'origine/destination est à l'intérieur de la
     * zone. La différence décisive est qu'il le signale de façon explicite et
     * machine-lisible (§5.2).
     */
    public function hasViolatedBlockedRoad(): bool
    {
        foreach ($this->notices as $notice) {
            if (($notice['code'] ?? '') === 'violatedBlockedRoad') {
                return true;
            }
        }

        return false;
    }

    /**
     * Libellé humain de l'itinéraire — « via A86 ».
     */
    public function label(): string
    {
        return $this->labels === [] ? 'Itinéraire' : 'via ' . implode(' · ', $this->labels);
    }
}
