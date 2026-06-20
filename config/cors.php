<?php

return [

    /*
     * Flutter (app native) : pas de CORS — navigateur n'est pas impliqué.
     * Ce fichier protège les routes web (Filament admin panel).
     *
     * En production : remplacer l'URL admin dans allowed_origins.
     */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('APP_URL', 'http://localhost'),
        env('ADMIN_URL', ''),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'X-App-Version',
    ],

    'exposed_headers' => [],

    'max_age' => 3600,

    /*
     * false pour l'API mobile — true si le panel admin partage des cookies Sanctum.
     */
    'supports_credentials' => false,

];
