<?php

namespace App\Providers;

use App\Services\Routing\FakeRoutingProvider;
use App\Services\Routing\HereRoutingProvider;
use App\Services\Routing\RoutingProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Choix du fournisseur de routage — CDC V4.1 §5.3
 *
 * Sans clé HERE configurée, on retombe sur le fournisseur factice : tout le
 * module Trajets reste développable et testable, sans clé ni facture.
 *
 * Le jour de la bascule vers Valhalla (§5.2), c'est le seul fichier à toucher.
 */
class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RoutingProvider::class, function () {
            $apiKey = config('services.here.api_key');

            if (empty($apiKey)) {
                return new FakeRoutingProvider();
            }

            return new HereRoutingProvider(
                $apiKey,
                rtrim((string) config('services.here.base_url'), '/'),
            );
        });
    }
}
