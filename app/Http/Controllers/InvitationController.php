<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * Affiche la page de redirection pour les invitations AlertContact
     *
     * @param Request $request
     * @return View
     */
    public function show(Request $request): View
    {
        // Récupérer le token d'invitation depuis les paramètres de requête
        $token = $request->query('t');

        // Valider que le token est présent
        if (empty($token)) {
            abort(400, 'Token d\'invitation manquant');
        }

        // Optionnel : Valider le token en base de données
        // $invitation = Invitation::where('token', $token)->first();
        // if (!$invitation || $invitation->isExpired()) {
        //     abort(404, 'Invitation invalide ou expirée');
        // }

        return view('invitation.show', [
            'token' => $token,
            'appUrl' => "alertcontact://invitations/accept?t={$token}",
            'playStoreUrl' => 'https://play.google.com/store/apps/details?id=com.alertcontacts.alertcontacts',
            'appStoreUrl' => 'https://apps.apple.com/app/alertcontacts/id123456789'
        ]);
    }
}
