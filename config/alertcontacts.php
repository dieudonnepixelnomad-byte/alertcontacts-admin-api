<?php

return [

    'free_tier' => [
        'contacts_limit'      => env('FREE_CONTACTS_LIMIT', 2),
        'zones_limit'         => env('FREE_ZONES_LIMIT', 1),
        'alert_history_hours' => 24,
    ],

    'trial' => [
        'duration_days' => 7,
    ],

    'alerts' => [
        'gravity' => [
            'low'    => ['duration_minutes' => 30,  'radius_meters' => 200],
            'medium' => ['duration_minutes' => 60,  'radius_meters' => 500],
            'high'   => ['duration_minutes' => 120, 'radius_meters' => 1000],
        ],
        'confirmations_to_validate' => 2,
    ],

    'zones' => [
        'radius_min'     => 50,
        'radius_max'     => 500,
        'radius_default' => 150,
    ],

    'location' => [
        'update_interval_foreground'   => 10,   // secondes — carte ouverte
        'update_interval_background'   => 300,  // secondes — background mouvement
        'update_interval_alert_nearby' => 60,   // secondes — alerte < 1km active
        'update_interval_idle'         => 900,  // secondes — immobile > 10 min
    ],

    'invisible_mode' => [
        'duration_options' => [60, 240, 0], // minutes — 0 = jusqu'à réactivation manuelle
    ],

    'invitations' => [
        'expiry_days'    => 7,
        'resend_delay_s' => 30, // secondes avant de pouvoir renvoyer un magic link
    ],

    'prices' => [
        'solo'   => ['monthly' => 4.99, 'annual' => 34.99],
        'family' => ['monthly' => 8.99, 'annual' => 59.99],
        'family_max_members' => 6,
    ],

    'paywall' => [
        'proactive_trigger_days'    => 7,  // J7 si >= 2 proches actifs
        'proactive_min_contacts'    => 2,
    ],

];
