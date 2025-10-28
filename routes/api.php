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
use App\Http\Controllers\Api\QuietHoursController;
use App\Http\Controllers\Api\UserActivitiesController;
use App\Http\Controllers\Api\TestNotificationController;
use App\Http\Controllers\Api\FeedbackController;

// Routes d'authentification (publiques)
Route::prefix('auth')->group(function () {
    Route::post('/firebase-login', [AuthController::class, 'firebaseLogin']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Routes protégées par Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Authentification
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Profil utilisateur
    Route::get('/me', [AuthController::class, 'me']);

    // Toutes les zones de l'utilisateur
    Route::get('/my-zones', [AuthController::class, 'getMyZones']);

    // Zones de sécurité
    Route::get('/safe-zones', [SafeZonesController::class, 'index']);
    Route::post('/safe-zones', [SafeZonesController::class, 'store']);
    Route::put('/safe-zones/{safeZone}', [SafeZonesController::class, 'update']);
    Route::delete('/safe-zones/{safeZone}', [SafeZonesController::class, 'destroy']);
    // Assignation de contacts
    Route::post('/safe-zones/{safeZone}/assign', [SafeZonesController::class, 'assignContacts']);

    // Routes pour les zones de danger
    Route::get('/danger-zones', [DangerZonesController::class, 'index']);
    Route::post('/danger-zones', [DangerZonesController::class, 'store']);
    Route::get('/danger-zones/{dangerZone}', [DangerZonesController::class, 'show']);
    Route::put('/danger-zones/{dangerZone}', [DangerZonesController::class, 'update']);
    Route::delete('/danger-zones/{dangerZone}', [DangerZonesController::class, 'destroy']);
    Route::post('/danger-zones/{dangerZone}/confirm', [DangerZonesController::class, 'confirm']);
    Route::post('/danger-zones/{dangerZone}/report-abuse', [DangerZonesController::class, 'reportAbuse']);
    Route::post('/danger-zones/check-duplicates', [DangerZonesController::class, 'checkForDuplicates']);

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
    });

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
        Route::post('/fcm-token', [LocationController::class, 'updateFcmToken']);
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
});
