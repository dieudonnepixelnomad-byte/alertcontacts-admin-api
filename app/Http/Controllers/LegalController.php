<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalController extends Controller
{
    /**
     * Affiche les politique de confidentialité
     */
    public function privacy(): View
    {
        return view('legal.privacy');
    }

    /**
     * Affiche les conditions d'utilisation
     */
    public function terms(): View
    {
        return view('legal.terms');
    }
}
