<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\Feedback;

class StoreFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(Feedback::TYPES))],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'device_info' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Le type de feedback est requis.',
            'type.in' => 'Le type de feedback sélectionné n\'est pas valide.',
            'message.required' => 'Le message est requis.',
            'message.min' => 'Le message doit contenir au moins 10 caractères.',
            'message.max' => 'Le message ne peut pas dépasser 2000 caractères.',
            'rating.integer' => 'La note doit être un nombre entier.',
            'rating.min' => 'La note doit être comprise entre 1 et 5.',
            'rating.max' => 'La note doit être comprise entre 1 et 5.',
            'subject.max' => 'Le sujet ne peut pas dépasser 255 caractères.',
            'app_version.max' => 'La version de l\'application ne peut pas dépasser 50 caractères.',
            'device_info.max' => 'Les informations de l\'appareil ne peuvent pas dépasser 255 caractères.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'type' => 'type de feedback',
            'subject' => 'sujet',
            'message' => 'message',
            'rating' => 'note',
            'app_version' => 'version de l\'application',
            'device_info' => 'informations de l\'appareil',
        ];
    }
}
