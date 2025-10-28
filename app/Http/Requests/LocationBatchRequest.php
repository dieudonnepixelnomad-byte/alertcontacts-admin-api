<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UC-A1: Validation des données de position GPS en batch
 */
class LocationBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // L'authentification est gérée par le middleware auth:sanctum
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'locations' => 'required|array|min:1|max:100', // Max 100 positions par batch
            'locations.*.latitude' => 'required|numeric|between:-90,90',
            'locations.*.longitude' => 'required|numeric|between:-180,180',
            'locations.*.accuracy' => 'nullable|numeric|min:0|max:10000', // Précision en mètres
            'locations.*.speed' => 'nullable|numeric|min:0|max:200', // Vitesse en m/s (max ~720 km/h)
            'locations.*.heading' => 'nullable|numeric|min:0|max:360', // Direction en degrés
            'locations.*.captured_at_device' => 'required|date_format:Y-m-d\TH:i:s.v\Z', // ISO 8601 avec millisecondes
            'locations.*.source' => 'nullable|string|in:gps,network,passive,fused',
            'locations.*.foreground' => 'nullable|boolean',
            'locations.*.battery_level' => 'nullable|integer|min:0|max:100', // Pourcentage batterie
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'locations.required' => 'Au moins une position est requise',
            'locations.max' => 'Maximum 100 positions par batch',
            'locations.*.latitude.required' => 'La latitude est requise',
            'locations.*.latitude.between' => 'La latitude doit être entre -90 et 90',
            'locations.*.longitude.required' => 'La longitude est requise',
            'locations.*.longitude.between' => 'La longitude doit être entre -180 et 180',
            'locations.*.captured_at_device.required' => 'Le timestamp de capture est requis',
            'locations.*.captured_at_device.date_format' => 'Le timestamp doit être au format ISO 8601',
            'locations.*.accuracy.max' => 'La précision ne peut pas dépasser 10000 mètres',
            'locations.*.speed.max' => 'La vitesse ne peut pas dépasser 200 m/s',
            'locations.*.heading.between' => 'La direction doit être entre 0 et 360 degrés',
            'locations.*.battery_level.between' => 'Le niveau de batterie doit être entre 0 et 100',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Vérifier que les timestamps sont dans un ordre chronologique acceptable
            $locations = $this->validated()['locations'] ?? [];
            if (count($locations) > 1) {
                $timestamps = array_column($locations, 'captured_at_device');
                $sortedTimestamps = $timestamps;
                sort($sortedTimestamps);
                
                // Permettre un léger désordre (max 5 minutes d'écart)
                $maxGap = 5 * 60; // 5 minutes en secondes
                for ($i = 1; $i < count($sortedTimestamps); $i++) {
                    $prev = strtotime($sortedTimestamps[$i-1]);
                    $curr = strtotime($sortedTimestamps[$i]);
                    if (($curr - $prev) > $maxGap * 60) { // Convertir en secondes
                        $validator->errors()->add('locations', 'Les timestamps des positions sont trop désordonnés');
                        break;
                    }
                }
            }
        });
    }
}