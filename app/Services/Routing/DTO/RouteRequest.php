<?php

namespace App\Services\Routing\DTO;

/**
 * Demande d'itinéraire, indépendante du fournisseur — CDC V4.1 §5.3
 */
final class RouteRequest
{
    /**
     * @param  array<int, AvoidArea>  $avoidAreas
     */
    public function __construct(
        public readonly float $originLat,
        public readonly float $originLng,
        public readonly float $destinationLat,
        public readonly float $destinationLng,
        public readonly string $transportMode = 'car',
        public readonly int $alternatives = 2,
        public readonly array $avoidAreas = [],
        public readonly string $lang = 'fr-fr',
    ) {
    }

    /**
     * @param  array<int, AvoidArea>  $areas
     */
    public function withAvoidAreas(array $areas): self
    {
        return new self(
            $this->originLat,
            $this->originLng,
            $this->destinationLat,
            $this->destinationLng,
            $this->transportMode,
            $this->alternatives,
            $areas,
            $this->lang,
        );
    }
}
