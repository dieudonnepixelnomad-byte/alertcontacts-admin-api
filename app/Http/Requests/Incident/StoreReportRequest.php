<?php

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un signalement — CDC V4.1 §8.2
 *
 * Aucune contrainte de tier : §10.3a fait passer la création d'alertes
 * communautaires en tier Gratuit. Le module a un besoin critique de
 * contributeurs pour atteindre une densité utile ; restreindre la
 * contribution ralentit le seul mécanisme capable de remplir le module.
 *
 * L'utilisateur ne fournit JAMAIS de géométrie ni de rayon (§4.6) : le
 * système les déduit du type et de la trace GPS.
 */
class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type'           => ['required', 'string', Rule::in(array_keys(config('incidents.types', [])))],
            'severity'       => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'lat'            => ['required', 'numeric', 'between:-90,90'],
            'lng'            => ['required', 'numeric', 'between:-180,180'],

            // §4.8 — fourni gratuitement par le SDK de géolocalisation
            'gps_accuracy_m' => ['nullable', 'integer', 'min:0', 'max:10000'],

            // §4.6 cas 1 — les ~100 derniers mètres de la trace du signaleur
            'gps_trace'      => ['nullable', 'array', 'max:200'],
            'gps_trace.*.lat' => ['required_with:gps_trace', 'numeric', 'between:-90,90'],
            'gps_trace.*.lng' => ['required_with:gps_trace', 'numeric', 'between:-180,180'],

            'was_moving'     => ['nullable', 'boolean'],
            'speed_kmh'      => ['nullable', 'integer', 'min:0', 'max:400'],

            'comment'        => ['nullable', 'string', 'max:200'],
            'photo_url'      => ['nullable', 'url', 'max:255'],
            'visibility'     => ['nullable', Rule::in(['public', 'circle'])],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => "Ce type d'incident n'existe pas.",
        ];
    }

    /**
     * Gravité effective : celle fournie, sinon la valeur par défaut du type (§4.9).
     * Rappel — la gravité ne détermine que couleur, priorité et tri.
     */
    public function severity(): string
    {
        $provided = $this->validated()['severity'] ?? null;

        if ($provided !== null) {
            return $provided;
        }

        $types = config('incidents.types', []);

        return $types[$this->validated()['type']]['severity_default'] ?? 'medium';
    }
}
