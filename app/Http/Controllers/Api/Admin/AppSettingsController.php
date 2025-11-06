<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AppSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(AppSetting::all());
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|exists:app_settings,key',
            'settings.*.value' => 'nullable|string',
        ]);

        foreach ($validated['settings'] as $setting) {
            AppSetting::where('key', $setting['key'])->update(['value' => $setting['value']]);
        }

        return response()->json(['message' => 'Settings updated successfully.']);
    }
}