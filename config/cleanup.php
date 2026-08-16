<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration du Nettoyage Automatique des Données
    |--------------------------------------------------------------------------
    |
    | Ce fichier configure les paramètres de rétention des données pour
    | maintenir les performances de l'application AlertContact.
    |
    */

    'enabled' => env('CLEANUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Paramètres de Rétention par Table
    |--------------------------------------------------------------------------
    |
    | Définit combien de temps conserver les données pour chaque table critique.
    | Les valeurs sont en jours.
    |
    */
    'retention' => [
        // 📍 Positions GPS des utilisateurs (très critique)
        'user_locations' => [
            'days' => env('CLEANUP_USER_LOCATIONS_DAYS', 30),
            'batch_size' => 1000,
            'description' => 'Positions GPS des utilisateurs'
        ],

        // 🔍 Logs de debug Telescope (très critique)
        'telescope_entries' => [
            'days' => env('CLEANUP_TELESCOPE_DAYS', 7),
            'batch_size' => 500,
            'description' => 'Logs de debug Telescope'
        ],

        // 👤 Activités utilisateurs (critique)
        'user_activities' => [
            'days' => env('CLEANUP_USER_ACTIVITIES_DAYS', 90),
            'batch_size' => 1000,
            'description' => 'Historique des activités utilisateurs'
        ],

        // 🛡️ Événements zones sécurisées (critique)
        'safe_zone_events' => [
            'days' => env('CLEANUP_SAFE_ZONE_EVENTS_DAYS', 180),
            'batch_size' => 500,
            'description' => 'Événements d\'entrée/sortie des zones sécurisées'
        ],

        // ⚙️ Jobs en queue (critique)
        'jobs' => [
            'days' => env('CLEANUP_JOBS_DAYS', 7),
            'batch_size' => 1000,
            'description' => 'Jobs en queue traités'
        ],

        // ❌ Jobs échoués (critique)
        'failed_jobs' => [
            'days' => env('CLEANUP_FAILED_JOBS_DAYS', 30),
            'batch_size' => 100,
            'description' => 'Jobs échoués'
        ],

        // 📦 Lots de jobs (critique)
        'job_batches' => [
            'days' => env('CLEANUP_JOB_BATCHES_DAYS', 30),
            'batch_size' => 100,
            'description' => 'Lots de jobs'
        ],

        // 🚨 Incidents communautaires terminés — V4.1 §7.2
        // Seuls les incidents non actifs sont purgés. Les alert_reports
        // rattachés partent avec eux (ON DELETE SET NULL puis purge orpheline).
        'incidents' => [
            'days' => env('CLEANUP_INCIDENTS_DAYS', 90),
            'batch_size' => 500,
            'description' => 'Incidents communautaires résolus/expirés/rejetés'
        ],

        // 📝 Signalements bruts — V4.1 §7.1
        'alert_reports' => [
            'days' => env('CLEANUP_ALERT_REPORTS_DAYS', 90),
            'batch_size' => 500,
            'description' => 'Signalements communautaires bruts'
        ],

        // 🗺️ Trajets terminés — V4.1 §7.3
        'routes' => [
            'days' => env('CLEANUP_ROUTES_DAYS', 90),
            'batch_size' => 500,
            'description' => 'Trajets terminés ou annulés'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paramètres d'Exécution
    |--------------------------------------------------------------------------
    |
    | Configuration pour l'exécution du job de nettoyage.
    |
    */
    'execution' => [
        // Taille maximale des lots pour éviter les timeouts
        'max_batch_size' => 2000,
        
        // Délai entre les lots (en millisecondes)
        'batch_delay_ms' => 100,
        
        // Nombre maximum de lots par table par exécution
        'max_batches_per_table' => 50,
        
        // Timeout maximum pour le job (en secondes)
        'timeout_seconds' => 7200, // 2 heures
    ],

    /*
    |--------------------------------------------------------------------------
    | Optimisation des Tables
    |--------------------------------------------------------------------------
    |
    | Configuration pour l'optimisation automatique des tables après nettoyage.
    |
    */
    'optimization' => [
        'enabled' => env('CLEANUP_OPTIMIZE_TABLES', true),
        
        // Tables à optimiser après nettoyage
        'tables' => [
            'user_locations',
            'telescope_entries',
            'user_activities',
            'safe_zone_events',
            'incidents',
            'alert_reports',
            'routes',
        ],
        
        // Seuil minimum de suppression pour déclencher l'optimisation
        'min_deleted_threshold' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications et Logs
    |--------------------------------------------------------------------------
    |
    | Configuration des notifications et logs pour le nettoyage.
    |
    */
    'notifications' => [
        // Email d'administration pour les erreurs
        'admin_email' => env('CLEANUP_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS')),
        
        // Seuil d'alerte pour les suppressions massives
        'mass_deletion_threshold' => 10000,
        
        // Activer les logs détaillés
        'detailed_logging' => env('CLEANUP_DETAILED_LOGS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mode Maintenance
    |--------------------------------------------------------------------------
    |
    | Paramètres pour le mode maintenance pendant le nettoyage.
    |
    */
    'maintenance' => [
        // Activer le mode maintenance pendant le nettoyage lourd
        'enable_during_cleanup' => env('CLEANUP_MAINTENANCE_MODE', false),
        
        // Message affiché pendant la maintenance
        'message' => 'Maintenance en cours - Optimisation de la base de données',
        
        // Seuil de suppressions pour activer la maintenance
        'threshold' => 50000,
    ],
];