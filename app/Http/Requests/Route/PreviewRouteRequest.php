<?php

namespace App\Http\Requests\Route;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Aperçu d'itinéraire — CDC V4.1 §8.1
 */
class PreviewRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'origin.lat'         => ['required', 'numeric', 'between:-90,90'],
            'origin.lng'         => ['required', 'numeric', 'between:-180,180'],
            'origin.label'       => ['nullable', 'string', 'max:255'],
            'destination.lat'    => ['required', 'numeric', 'between:-90,90'],
            'destination.lng'    => ['required', 'numeric', 'between:-180,180'],
            'destination.label'  => ['nullable', 'string', 'max:255'],
            'transport_mode'     => ['nullable', Rule::in(['car', 'pedestrian', 'scooter'])],
        ];
    }
}
