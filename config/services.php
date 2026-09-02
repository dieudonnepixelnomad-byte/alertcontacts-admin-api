<?php

return [

    'trackers' => [
        'ingest_secret' => env('TRACKER_INGEST_SECRET'),
    ],

    'posthog' => [
        'project_api_key' => env('POSTHOG_PROJECT_API_KEY'),
        'host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'database_url' => env('FIREBASE_DATABASE_URL'),
    ],

    'revenuecat' => [
        'webhook_secret' => env('REVENUECAT_WEBHOOK_SECRET'),
        'secret_api_key' => env('REVENUECAT_SECRET_API_KEY'),
        'entitlement_id' => env('REVENUECAT_ENTITLEMENT_ID', 'premium'),
        'products' => [
            env('REVENUECAT_PREMIUM_MONTHLY_PRODUCT_ID', 'premium_monthly'),
            env('REVENUECAT_PREMIUM_ANNUAL_PRODUCT_ID', 'premium_annual'),
        ],
    ],

    /*
    | HERE Routing API v8 — CDC V4.1 §5.2
    |
    | La clé ne doit JAMAIS être embarquée dans l'APK : Flutter n'appelle
    | jamais HERE directement, tout passe par Laravel (§5.3).
    | Sans clé configurée, RoutingServiceProvider retombe automatiquement sur
    | FakeRoutingProvider — le module Trajets reste développable et testable.
    */
    'here' => [
        'api_key'  => env('HERE_API_KEY'),
        'base_url' => env('HERE_ROUTING_URL', 'https://router.hereapi.com/v8'),
    ],

];
