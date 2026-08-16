<?php

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Incidents actifs dans une boîte englobante — CDC V4.1 §8.2
 *
 * Format bbox : "south,west,north,east" (degrés décimaux).
 */
class NearbyIncidentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'bbox' => ['required', 'string', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'bbox.regex' => 'bbox attendu au format "south,west,north,east".',
        ];
    }

    /**
     * @return array{north: float, south: float, east: float, west: float}
     */
    public function bbox(): array
    {
        [$south, $west, $north, $east] = array_map('floatval', explode(',', $this->validated()['bbox']));

        return [
            'south' => min($south, $north),
            'north' => max($south, $north),
            'west'  => min($west, $east),
            'east'  => max($west, $east),
        ];
    }
}
