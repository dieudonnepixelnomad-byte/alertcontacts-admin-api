<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserOnboardingController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'age_range' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:50'],
            'primary_goal' => ['nullable', 'string', 'max:100'],
            'experience_level' => ['nullable', 'string', 'max:100'],
            'payload' => ['nullable', 'array'],
        ]);

        $onboarding = $data['payload'] ?? [
            'first_name' => $data['first_name'] ?? null,
            'age_range' => $data['age_range'] ?? null,
            'gender' => $data['gender'] ?? null,
            'primary_goal' => $data['primary_goal'] ?? null,
            'experience_level' => $data['experience_level'] ?? null,
        ];

        $user->onboarding_completed = true;
        $user->onboarding_data = $onboarding;
        if (!empty($data['first_name'])) {
            $user->name = $data['first_name'];
        }
        $user->save();

        return response()->json([
            'status' => 'ok',
            'onboarding_completed' => true,
            'onboarding_data' => $user->onboarding_data,
        ]);
    }
}