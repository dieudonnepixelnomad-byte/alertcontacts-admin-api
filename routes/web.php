<?php

use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvitationController;

Route::get('/', function () {
    return view('welcome');
});

// Route pour gérer les invitations AlertContact
Route::get('/invite', [InvitationController::class, 'show'])->name('invitation.show');

// Route pour les invitations partagées (URL générée par Flutter)
Route::get('/invitations/accept', [InvitationController::class, 'show'])->name('invitation.accept');

// Pages légales
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
