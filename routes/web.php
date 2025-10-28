<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvitationController;

Route::get('/', function () {
    return view('welcome');
});

// Route pour gérer les invitations AlertContact
Route::get('/invite', [InvitationController::class, 'show'])->name('invitation.show');
