<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SafeZonesController;
use App\Http\Controllers\Api\DangerZonesController;
use App\Http\Controllers\Api\IgnoredDangerZoneController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\RelationshipController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\QuietHoursController;
use App\Http\Controllers\Api\UserActivitiesController;
use App\Http\Controllers\Api\TestNotificationController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\UserOnboardingController;
use App\Http\Controllers\Api\AppStatusController;
use App\Http\Controllers\Api\ZoneController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\RevenueCatWebhookController;
use App\Http\Controllers\Api\GpsTrackerController;
use App\Http\Controllers\Api\InternalTrackerTelemetryController;
use App\Http\Controllers\Api\Admin\AppSettingsController as AdminAppSettingsController;
// API v1 — CDC V4.1 (incidents communautaires & trajets)
use App\Http\Controllers\Api\V1\IncidentController as V1IncidentController;
use App\Http\Controllers\Api\V1\ReportController as V1ReportController;
use App\Http\Controllers\Api\V1\RouteController as V1RouteController;

// Route publique pour l'état de l'application
Route::get('/app-status', AppStatusController::class);

// Webhook RevenueCat — public, pas de Sanctum (appelé par RevenueCat serveurs)
Route::post('/webhooks/revenuecat', [RevenueCatWebhookController::class, 'handle']);
Route::post('/internal/tracker-telemetry', [InternalTrackerTelemetryController::class, 'store']);

// Routes d'authentification (publiques)
Route::prefix('auth')->group(function () {
    Route::post('/firebase-login', [AuthController::class, 'firebaseLogin']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Route publique pour la mise à jour du token FCM (sans authentification Sanctum)
Route::post('/users/fcm_token', [UserController::class, 'updateFcmToken']);

// Routes protégées par Sanctum
Route::middleware(['auth:sanctum', 'minimum-app-version'])->group(function () {
    // Authentification
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Profil utilisateur
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/export-data', [UserController::class, 'exportData']);

    // Toutes les zones de l'utilisateur
    Route::get('/my-zones', [AuthController::class, 'getMyZones']);

    // Zones de sécurité
    Route::get('/safe-zones', [SafeZonesController::class, 'index']);
    Route::post('/safe-zones', [SafeZonesController::class, 'store']);
    Route::put('/safe-zones/{safeZone}', [SafeZonesController::class, 'update']);
    Route::delete('/safe-zones/{safeZone}', [SafeZonesController::class, 'destroy']);
    // Assignations
    Route::get('/safe-zones/my-assignments', [SafeZonesController::class, 'getMyAssignments']);
    Route::get('/safe-zones/{safeZone}/contacts', [SafeZonesController::class, 'getContacts']);
    Route::put('/safe-zones/{safeZone}/contacts', [SafeZonesController::class, 'syncContacts']);
    // Paramètres de notification
    Route::put('/safe-zones/{safeZone}/notification-settings', [SafeZonesController::class, 'updateNotificationSettings']);

    // Routes pour les zones de danger
    // IMPORTANT : les routes statiques DOIVENT précéder /{dangerZone} pour éviter les conflits
    Route::get('/danger-zones', [DangerZonesController::class, 'index']);
    Route::post('/danger-zones', [DangerZonesController::class, 'store']);
    Route::get('/danger-zones/viewport', [DangerZonesController::class, 'viewport']);
    Route::post('/danger-zones/check-duplicates', [DangerZonesController::class, 'checkForDuplicates']);
    Route::get('/danger-zones/{dangerZone}', [DangerZonesController::class, 'show']);
    Route::put('/danger-zones/{dangerZone}', [DangerZonesController::class, 'update']);
    Route::delete('/danger-zones/{dangerZone}', [DangerZonesController::class, 'destroy']);
    Route::post('/danger-zones/{dangerZone}/confirm', [DangerZonesController::class, 'confirm']);
    Route::post('/danger-zones/{dangerZone}/report-abuse', [DangerZonesController::class, 'reportAbuse']);

    // Routes pour les zones de danger ignorées
    Route::prefix('ignored-danger-zones')->group(function () {
        Route::get('/', [IgnoredDangerZoneController::class, 'index']);
        Route::post('/ignore', [IgnoredDangerZoneController::class, 'ignore']);
        Route::delete('/{dangerZoneId}/reactivate', [IgnoredDangerZoneController::class, 'reactivate']);
        Route::patch('/{dangerZoneId}/extend', [IgnoredDangerZoneController::class, 'extend']);
        Route::get('/{dangerZoneId}/check', [IgnoredDangerZoneController::class, 'check']);
    });

    // Invitations
    Route::prefix('invitations')->group(function () {
        Route::get('/', [InvitationController::class, 'index']);
        Route::post('/', [InvitationController::class, 'store']);
        Route::post('/check', [InvitationController::class, 'check']);
        Route::post('/accept', [InvitationController::class, 'accept']);
        Route::delete('/{invitation}', [InvitationController::class, 'destroy']);
    });

    // Relations/Proches
    Route::prefix('relationships')->group(function () {
        Route::get('/', [RelationshipController::class, 'index']);
        Route::get('/stats', [RelationshipController::class, 'stats']);
        Route::get('/search-users', [RelationshipController::class, 'searchUsers']);
        Route::get('/{relationship}', [RelationshipController::class, 'show']);
        Route::put('/{relationship}/share-level', [RelationshipController::class, 'updateShareLevel']);
        Route::delete('/{relationship}', [RelationshipController::class, 'destroy']);
        Route::get('/contact/{contactId}/locations', [RelationshipController::class, 'getContactLocations']);
    });

    // V4 — Contacts permissions
    Route::put('/contacts/{relationship}/permissions', [RelationshipController::class, 'updatePermissions']);

    // V4 — Zones
    Route::apiResource('zones', ZoneController::class);
    Route::get('/zones/{zone}/status', [ZoneController::class, 'status']);

    // V4 — Abonnements
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);

    Route::prefix('gps-trackers')->middleware('tier:premium')->group(function () {
        Route::get('/', [GpsTrackerController::class, 'index']);
        Route::post('/', [GpsTrackerController::class, 'store']);
        Route::get('/{tracker}/locations', [GpsTrackerController::class, 'locations']);
        Route::get('/{tracker}/zones', [GpsTrackerController::class, 'zones']);
        Route::put('/{tracker}/zones', [GpsTrackerController::class, 'syncZones']);
        Route::post('/{tracker}/activate', [GpsTrackerController::class, 'activate']);
        Route::post('/{tracker}/deactivate', [GpsTrackerController::class, 'deactivate']);
        Route::put('/{tracker}', [GpsTrackerController::class, 'update']);
        Route::delete('/{tracker}', [GpsTrackerController::class, 'destroy']);
    });

    // V4 — Mode invisible (CDC §10.1 — réservé aux tiers payants)
    Route::post('/location/pause', [LocationController::class, 'pause'])
        ->middleware('tier:premium');
    // `resume` reste volontairement ouvert à tous : un utilisateur dont
    // l'abonnement expire pendant une pause doit toujours pouvoir redevenir
    // visible. Le gating porte sur l'activation, jamais sur la sortie.
    Route::post('/location/resume', [LocationController::class, 'resume']);

    // V4 — Alertes communautaires
    Route::get('/alerts/nearby', [AlertController::class, 'nearby']);
    Route::post('/alerts/{alert}/confirm', [AlertController::class, 'confirm']);
    Route::post('/alerts/{alert}/deny', [AlertController::class, 'deny']);
    Route::post('/alerts/{alert}/report', [AlertController::class, 'report']);
    Route::post('/alerts', [AlertController::class, 'store']);

    // Gestion des zones assignées aux proches
    Route::prefix('proches')->group(function () {
        Route::get('/{contact_id}/zones', [RelationshipController::class, 'getAssignableZones']);
        Route::post('/{contact_id}/zones/{zone_id}', [RelationshipController::class, 'assignZone']);
        Route::delete('/{contact_id}/zones/{zone_id}', [RelationshipController::class, 'unassignZone']);
        Route::patch('/{contact_id}/zones/{zone_id}', [RelationshipController::class, 'toggleZoneAssignment']);
    });

    // UC-A1/R1: API d'ingestion des positions GPS
    Route::prefix('locations')->group(function () {
        Route::post('/batch', [LocationController::class, 'batch']);
        Route::get('/recent', [LocationController::class, 'recent']);
    });

    // UC-Q1: Gestion des heures calmes
    Route::prefix('quiet-hours')->group(function () {
        Route::get('/', [QuietHoursController::class, 'show']);
        Route::put('/', [QuietHoursController::class, 'update']);
        Route::get('/next-allowed-time', [QuietHoursController::class, 'nextAllowedTime']);
        Route::get('/timezones', [QuietHoursController::class, 'timezones']);
    });

    // Activités utilisateur
    Route::prefix('activities')->group(function () {
        Route::get('/', [UserActivitiesController::class, 'index']);
        Route::get('/stats', [UserActivitiesController::class, 'stats']);
    });

    // Confirmation d'alertes
    Route::prefix('alerts')->group(function () {
        Route::get('/pending', [App\Http\Controllers\Api\AlertConfirmationController::class, 'getPendingAlerts']);
        Route::post('/confirm', [App\Http\Controllers\Api\AlertConfirmationController::class, 'confirmSafeZoneAlert']);
        Route::post('/stop', [App\Http\Controllers\Api\AlertConfirmationController::class, 'stopNotifications']);
    });

    // Feedback et suggestions
    Route::prefix('feedback')->group(function () {
        Route::get('/', [FeedbackController::class, 'index']);
        Route::post('/', [FeedbackController::class, 'store']);
        Route::get('/types', [FeedbackController::class, 'types']);
        Route::get('/stats', [FeedbackController::class, 'stats']);
        Route::get('/{feedback}', [FeedbackController::class, 'show']);
    });

    // Tests de notifications (uniquement en développement)
    if (app()->environment(['local', 'testing'])) {
        Route::prefix('test-notifications')->group(function () {
            Route::get('/users-with-fcm', [App\Http\Controllers\TestNotificationController::class, 'listUsersWithFcm']);
            Route::post('/send-to-user', [App\Http\Controllers\TestNotificationController::class, 'sendTestNotification']);
            Route::post('/broadcast', [App\Http\Controllers\TestNotificationController::class, 'sendBroadcastTest']);

            // Tests spécifiques aux notifications d'invitation
            Route::post('/invitation-accept', [TestNotificationController::class, 'testAcceptNotification']);
            Route::post('/invitation-refuse', [TestNotificationController::class, 'testRefuseNotification']);
        });
    }

    // Suppression de compte utilisateur (RGPD)
    Route::delete('/user/account', [AuthController::class, 'deleteAccount']);

    // Ancienne route pour compatibilité
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Onboarding utilisateur (enregistrement des données dans users)
    Route::post('/user/onboarding', [UserOnboardingController::class, 'complete']);
    Route::post('/auth/onboarding/complete', [UserOnboardingController::class, 'complete']); // V4 alias

    // Routes d'administration
    Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
        Route::get('settings', [AdminAppSettingsController::class, 'index']);
        Route::put('settings', [AdminAppSettingsController::class, 'update']);
    });
});

/*
|--------------------------------------------------------------------------
| API v1 — Incidents communautaires & Trajets (CDC V4.1)
|--------------------------------------------------------------------------
|
| Nouveau socle §8. Les routes /api/* ci-dessus restent inchangées : l'app en
| production les utilise, la bascule côté client se fait écran par écran.
|
| Modèle : signalement (alert_reports) → clustering → incident (incidents).
| Le rayon unique dérivé de la gravité est remplacé par trois valeurs
| découplées — notification, affichage, évitement (§4.1).
*/
Route::prefix('v1')->middleware(['auth:sanctum', 'minimum-app-version'])->group(function () {

    // --- Signalements (§8.2) ---
    // Pas de middleware 'tier' : §10.3a fait passer la création en tier Gratuit.
    // Le module a un besoin critique de contributeurs pour exister.
    Route::post('/reports', [V1ReportController::class, 'store'])->middleware('throttle:reports');
    Route::get('/reports/mine', [V1ReportController::class, 'mine']);
    Route::get('/reports/duplicate-check', [V1ReportController::class, 'duplicateCheck']);
    Route::delete('/reports/{report}', [V1ReportController::class, 'destroy']);

    // --- Incidents (§8.2) ---
    Route::get('/incidents', [V1IncidentController::class, 'index']);
    Route::get('/incidents/{incident}', [V1IncidentController::class, 'show']);
    Route::post('/incidents/{incident}/confirm', [V1IncidentController::class, 'confirm']);
    Route::post('/incidents/{incident}/clear', [V1IncidentController::class, 'clear']);
    Route::post('/incidents/{incident}/report-abuse', [V1IncidentController::class, 'reportAbuse']);

    // --- Trajets (§8.1) ---
    Route::prefix('routes')->group(function () {
        Route::get('/history', [V1RouteController::class, 'history']);
        Route::get('/recent-destinations', [V1RouteController::class, 'recentDestinations']);
        Route::get('/avoidance-quota', [V1RouteController::class, 'avoidanceQuota']);

        // 1 appel au moteur de routage
        Route::post('/preview', [V1RouteController::class, 'preview'])->middleware('throttle:routing');

        // Second appel, conditionnel — seul point de gating monétisation (§10.2)
        Route::post('/{route}/avoid', [V1RouteController::class, 'avoid'])
            ->middleware(['throttle:routing', 'avoidance.quota']);

        Route::post('/{route}/select', [V1RouteController::class, 'select']);
        Route::post('/{route}/start', [V1RouteController::class, 'start']);
        Route::post('/{route}/end', [V1RouteController::class, 'end']);
        Route::post('/{route}/cancel', [V1RouteController::class, 'cancel']);
    });
});
