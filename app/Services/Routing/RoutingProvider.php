<?php

namespace App\Services\Routing;

use App\Services\Routing\DTO\RouteRequest;
use App\Services\Routing\DTO\RouteResult;

/**
 * Fournisseur de routage — CDC V4.1 §5.3
 *
 * Cette abstraction est à écrire dès la V1. Elle rend la migration vers
 * Valhalla indolore le jour où la facture HERE dépassera le coût d'un poste
 * d'exploitation — c'est un arbitrage de volume, pas de capacité technique.
 *
 * Règle non négociable : Flutter n'appelle JAMAIS le moteur directement.
 *   1. une clé API embarquée dans un APK est extractible en quelques minutes
 *   2. le volume d'appels, donc la facture, doit rester sous contrôle serveur
 *   3. un changement de fournisseur ne doit pas exiger de publication sur les stores
 */
interface RoutingProvider
{
    /**
     * @throws \App\Services\Routing\RoutingException
     */
    public function route(RouteRequest $request): RouteResult;

    /**
     * Identifiant du fournisseur, pour les logs et l'analytics de coût (§13.1).
     */
    public function name(): string;
}
