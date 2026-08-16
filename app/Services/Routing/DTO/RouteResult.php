<?php

namespace App\Services\Routing\DTO;

/**
 * Réponse d'un fournisseur de routage — CDC V4.1 §5.3
 */
final class RouteResult
{
    /**
     * @param  array<int, RouteAlternative>  $alternatives
     */
    public function __construct(
        public readonly array $alternatives,
        public readonly string $provider,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->alternatives === [];
    }

    public function primary(): ?RouteAlternative
    {
        return $this->alternatives[0] ?? null;
    }

    /**
     * Le plus rapide des itinéraires proposés, quel que soit son rang.
     */
    public function fastest(): ?RouteAlternative
    {
        $sorted = $this->alternatives;

        usort($sorted, static fn (RouteAlternative $a, RouteAlternative $b) => $a->durationS <=> $b->durationS);

        return $sorted[0] ?? null;
    }
}
