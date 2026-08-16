<?php

namespace App\Services\Routing;

use RuntimeException;

/**
 * Échec du moteur de routage.
 *
 * §5.6 — cette exception ne doit JAMAIS remonter telle quelle jusqu'à
 * l'utilisateur. Aucun écran vide, aucun spinner infini, aucun message
 * d'erreur technique : le controller la traduit en message compréhensible.
 */
class RoutingException extends RuntimeException
{
    public static function unavailable(string $provider, string $detail = ''): self
    {
        return new self("Fournisseur de routage « {$provider} » indisponible. {$detail}");
    }

    public static function noRoute(): self
    {
        return new self('Aucun itinéraire trouvé entre ces deux points.');
    }
}
