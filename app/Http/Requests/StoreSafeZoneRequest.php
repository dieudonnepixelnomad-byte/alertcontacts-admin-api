<?php
// app/Http/Requests/StoreSafeZoneRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSafeZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name'        => ['required','string','max:64'],
            'icon'        => ['nullable','string','max:32'],

            // Mode CERCLE
            'center'              => ['nullable','array'],
            'center.lat'          => ['required_with:center','numeric','between:-90,90'],
            'center.lng'          => ['required_with:center','numeric','between:-180,180'],
            'radius_m'            => ['nullable','integer','min:50','max:5000'],

            // Mode POLYGONE (GeoJSON-like minimal)
            'geom'                        => ['nullable','array'],
            'geom.type'                   => ['required_with:geom','in:Polygon'],
            'geom.coordinates'            => ['required_with:geom','array','min:1'],   // un seul LinearRing extérieur
            'geom.coordinates.0'          => ['required_with:geom','array','min:4'],   // au moins 4 points
            'geom.coordinates.0.*'        => ['array','size:2'],                       // [lng, lat]
            'geom.coordinates.0.0'        => ['required_with:geom'],                   // 1er point
            'geom.coordinates.0.*.0'      => ['numeric','between:-180,180'],           // lng
            'geom.coordinates.0.*.1'      => ['numeric','between:-90,90'],             // lat
            // fermeture du ring (le contrôleur vérifiera que 1er == dernier, sinon on fermera automatiquement)

            // Fenêtres horaires (optionnel)
            'active_hours' => ['nullable','array'],

            // Assignations (optionnel)
            'contact_ids'  => ['nullable','array'],
            'contact_ids.*'=> ['integer','exists:users,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $hasCircle  = $this->filled('center.lat') && $this->filled('center.lng') && $this->filled('radius_m');
            $hasPolygon = $this->filled('geom');

            if (!$hasCircle && !$hasPolygon) {
                $v->errors()->add('shape', 'Vous devez fournir un cercle (center + radius_m) ou un polygone (geom).');
            }

            if ($hasCircle && $hasPolygon) {
                $v->errors()->add('shape', 'Ne fournissez pas cercle et polygone simultanément.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la zone est requis.',
            'center.lat.required_with' => 'Latitude du centre requise.',
            'center.lng.required_with' => 'Longitude du centre requise.',
            'radius_m.min'  => 'Le rayon minimal est 50 mètres.',
            'geom.type.in'  => 'Le type de géométrie doit être "Polygon".',
            'geom.coordinates.required_with' => 'Les coordonnées du polygone sont requises.',
        ];
    }
}
