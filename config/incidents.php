<?php

/*
|--------------------------------------------------------------------------
| Incidents — CDC V4.1 §4.9
|--------------------------------------------------------------------------
|
| Table de référence type d'incident → comportement. Elle vit ici et non en
| dur dans le code : elle évoluera avec l'usage réel (§4.9).
|
| Rappel du découplage §4.1 — gravité, étendue et durée sont indépendantes :
|   severity_default          → couleur, priorité, tri. RIEN d'autre.
|   geometry_type + buffer    → ce qui est EXCLU du calcul d'itinéraire
|   notify_radius_m           → qui reçoit le PUSH
|   display_radius_m          → le halo DESSINÉ sur la carte (§4.4)
|   ttl_minutes               → fonction du TYPE, pas de la gravité
|   routing_min_reports       → null = n'influence JAMAIS un itinéraire (§4.11)
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Types d'incident — §4.9
    |----------------------------------------------------------------------
    */
    'types' => [

        'accident' => [
            'label'                    => 'Accident',
            'emoji'                    => '🚗',
            'severity_default'         => 'medium',
            'geometry_type'            => 'corridor',
            'danger_buffer_m'          => 20,
            'polygon_fallback_radius_m' => 80,
            'ttl_minutes'              => 45,
            'extendable'               => true,
            'notify_radius_m'          => 500,
            'display_radius_m'         => 250,
            'routing_min_reports'      => 1,
            'transport_modes'          => ['car', 'scooter'],
        ],

        'fire' => [
            'label'                    => 'Incendie',
            'emoji'                    => '🔥',
            'severity_default'         => 'high',
            'geometry_type'            => 'corridor',
            'danger_buffer_m'          => 25,
            'polygon_fallback_radius_m' => 80,
            'ttl_minutes'              => 120,
            'extendable'               => true,
            'notify_radius_m'          => 1000,
            'display_radius_m'         => 300,
            'routing_min_reports'      => 1,
            'transport_modes'          => ['car', 'scooter', 'pedestrian'],
        ],

        'aggression' => [
            'label'                    => 'Agression',
            'emoji'                    => '⚠️',
            'severity_default'         => 'high',
            'geometry_type'            => 'corridor',
            'danger_buffer_m'          => 20,
            'polygon_fallback_radius_m' => 80,
            'ttl_minutes'              => 60,
            'extendable'               => false,
            'notify_radius_m'          => 800,
            'display_radius_m'         => 250,
            // §4.11 — n'influence le routage qu'à partir de 3 signalements indépendants
            'routing_min_reports'      => 3,
            'transport_modes'          => ['car', 'scooter', 'pedestrian'],
        ],

        'suspect' => [
            'label'                    => 'Individu suspect',
            'emoji'                    => '👤',
            'severity_default'         => 'medium',
            'geometry_type'            => 'polygon',
            'danger_buffer_m'          => null,
            'polygon_fallback_radius_m' => 100,
            'ttl_minutes'              => 45,
            'extendable'               => false,
            'notify_radius_m'          => 500,
            'display_radius_m'         => 200,
            // §4.11 — signalement visant une personne : affiché, JAMAIS routé
            'routing_min_reports'      => null,
            'transport_modes'          => ['car', 'scooter', 'pedestrian'],
        ],

        'suspicious_package' => [
            'label'                    => 'Colis suspect',
            'emoji'                    => '📦',
            'severity_default'         => 'medium',
            'geometry_type'            => 'polygon',
            'danger_buffer_m'          => null,
            'polygon_fallback_radius_m' => 150,
            'ttl_minutes'              => 60,
            'extendable'               => true,
            'notify_radius_m'          => 500,
            'display_radius_m'         => 250,
            'routing_min_reports'      => 2,
            'transport_modes'          => ['car', 'scooter', 'pedestrian'],
        ],

        'roadworks' => [
            'label'                    => 'Travaux',
            'emoji'                    => '🚧',
            'severity_default'         => 'low',
            'geometry_type'            => 'corridor',
            'danger_buffer_m'          => 20,
            'polygon_fallback_radius_m' => 80,
            'ttl_minutes'              => 10080, // 7 jours — un chantier dure des semaines (§2.2)
            'extendable'               => true,
            'notify_radius_m'          => 300,
            'display_radius_m'         => 150,
            'routing_min_reports'      => 2,
            'transport_modes'          => ['car', 'scooter'],
        ],

        'traffic_jam' => [
            'label'                    => 'Embouteillage',
            'emoji'                    => '🚦',
            'severity_default'         => 'low',
            'geometry_type'            => 'corridor',
            'danger_buffer_m'          => 20,
            'polygon_fallback_radius_m' => 80,
            'ttl_minutes'              => 30,
            'extendable'               => true,
            'notify_radius_m'          => 400,
            'display_radius_m'         => 200,
            'routing_min_reports'      => 2,
            'transport_modes'          => ['car', 'scooter'],
        ],

        'flood' => [
            'label'                    => 'Inondation',
            'emoji'                    => '🌊',
            'severity_default'         => 'high',
            'geometry_type'            => 'polygon',
            'danger_buffer_m'          => null,
            'polygon_fallback_radius_m' => 150,
            'ttl_minutes'              => 360,
            'extendable'               => true,
            'notify_radius_m'          => 1000,
            'display_radius_m'         => 400,
            'routing_min_reports'      => 2,
            'transport_modes'          => ['car', 'scooter', 'pedestrian'],
        ],

        'protest' => [
            'label'                    => 'Manifestation',
            'emoji'                    => '📢',
            'severity_default'         => 'medium',
            'geometry_type'            => 'polygon',
            'danger_buffer_m'          => null,
            'polygon_fallback_radius_m' => 150,
            'ttl_minutes'              => 240,
            'extendable'               => true,
            'notify_radius_m'          => 800,
            'display_radius_m'         => 400,
            'routing_min_reports'      => 3,
            'transport_modes'          => ['car', 'scooter', 'pedestrian'],
        ],

        'other' => [
            'label'                    => 'Autre',
            'emoji'                    => '🔔',
            'severity_default'         => 'medium',
            'geometry_type'            => 'polygon',
            'danger_buffer_m'          => null,
            'polygon_fallback_radius_m' => 100,
            'ttl_minutes'              => 60,
            'extendable'               => false,
            'notify_radius_m'          => 400,
            'display_radius_m'         => 200,
            'routing_min_reports'      => null,
            'transport_modes'          => ['car', 'scooter', 'pedestrian'],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Clustering signalement → incident — §4.5
    |----------------------------------------------------------------------
    | Seuils configurables, à ajuster à l'usage réel.
    */
    'clustering' => [
        'max_distance_m'  => 150,
        'max_age_minutes' => 10,

        // Types considérés comme témoignages du même événement.
        // Relation symétrique — appliquée dans les deux sens par le service.
        'compatible_types' => [
            'accident'   => ['traffic_jam'],
            'fire'       => [],
            'aggression' => [],
            'roadworks'  => ['traffic_jam'],
            'traffic_jam' => ['accident', 'roadworks'],
            'flood'      => [],
            'protest'    => [],
        ],

        // §4.5 — à partir de N signalements, l'enveloppe convexe donne l'étendue réelle
        'convex_hull_min_reports' => 4,
    ],

    /*
    |----------------------------------------------------------------------
    | Construction de la géométrie — §4.6
    |----------------------------------------------------------------------
    */
    'geometry' => [
        // Longueur du corridor repris depuis la trace GPS du signaleur
        'corridor_length_m'      => 120,
        'corridor_min_points'    => 2,
        // Repli quand aucune trace exploitable : polygone serré (régime précis HERE)
        'polygon_vertices'       => 12,
        // Bornes du buffer d'évitement (§4.1)
        'danger_buffer_min_m'    => 15,
        'danger_buffer_max_m'    => 60,
        // Bornes du rayon de notification (§4.1)
        'notify_radius_min_m'    => 200,
        'notify_radius_max_m'    => 2000,
    ],

    /*
    |----------------------------------------------------------------------
    | Confiance et précision — §4.8
    |----------------------------------------------------------------------
    */
    'gps' => [
        // > 40 m → buffer élargi
        'accuracy_widen_m'        => 40,
        'accuracy_widen_factor'   => 1.5,
        // > 80 m → affichage seul, jamais de routage
        'accuracy_display_only_m' => 80,
    ],

    'confidence' => [
        // Compte de moins de 24 h → pondération réduite
        'new_account_hours'   => 24,
        'new_account_weight'  => 0.5,
        'stationary_bonus'    => 0.1,
        'report_count_weight' => 0.25, // facteur dominant
    ],

    /*
    |----------------------------------------------------------------------
    | Cycle de vie — §4.7
    |----------------------------------------------------------------------
    */
    'resolution' => [
        // Nombre de « C'est terminé » faisant passer l'incident en resolved (§4.7a)
        'clear_threshold' => 2,
        // Résolution passive (§4.7b) — traversées sans signalement
        'passive' => [
            'enabled'            => true,
            'min_crossings'      => 3,
            'lookback_minutes'   => 15,
            'min_incident_age_minutes' => 10,
        ],
        // Prolongation automatique (§4.7c) — plafond de sécurité
        'max_extension_hours' => 12,
    ],

    /*
    |----------------------------------------------------------------------
    | Anti-abus — §4.10
    |----------------------------------------------------------------------
    */
    'rate_limit' => [
        'reports_per_hour' => 10,
    ],

    'abuse' => [
        // §4.10 règle 5 — incident jamais reconfirmé → rejected à l'expiration
        'reject_if_lonely' => true,
    ],

    /*
    |----------------------------------------------------------------------
    | Routage — §5.6
    |----------------------------------------------------------------------
    */
    'routing' => [
        'max_avoid_areas'          => 20,
        'polyline_sample_step_m'   => 50,
        'polyline_sample_step_long_m' => 200,
        'long_route_threshold_m'   => 100000,
        'long_route_corridor_m'    => 2000,
        'bbox_margin_m'            => 2000,
    ],
];
