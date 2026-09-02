<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostHogService
{
    private const BLOCKED_KEY_FRAGMENTS = [
        'email',
        'phone',
        'name',
        'nom',
        'prenom',
        'lat',
        'lng',
        'longitude',
        'latitude',
        'address',
        'adresse',
        'imei',
        'serial',
        'external_identifier',
        'identifier',
        'token',
        'secret',
        'password',
        'payload',
    ];

    public function capture(User|string|null $user, string $event, array $properties = []): void
    {
        $apiKey = (string) config('services.posthog.project_api_key', '');
        if ($apiKey === '') {
            return;
        }

        $distinctId = $user instanceof User ? (string) $user->id : (string) ($user ?: 'server');

        $payload = [
            'api_key' => $apiKey,
            'event' => $event,
            'distinct_id' => $distinctId,
            'properties' => $this->sanitize($properties + [
                'source' => 'laravel',
                'environment' => app()->environment(),
            ]),
        ];

        app()->terminating(function () use ($event, $payload): void {
            $this->send($event, $payload);
        });
    }

    private function send(string $event, array $payload): void
    {
        try {
            Http::baseUrl($this->host())
                ->acceptJson()
                ->timeout(2)
                ->post('/capture/', $payload);
        } catch (\Throwable $e) {
            Log::debug('PostHog capture failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function host(): string
    {
        $host = rtrim((string) config('services.posthog.host', ''), '/');
        return $host !== '' ? $host : 'https://us.i.posthog.com';
    }

    private function sanitize(array $properties): array
    {
        $clean = [];
        foreach ($properties as $key => $value) {
            if (! is_string($key) || $this->isSensitiveKey($key)) {
                continue;
            }

            if (is_string($value) && $this->looksSensitive($value)) {
                continue;
            }

            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        if (str_starts_with($normalized, 'has_')) {
            return false;
        }

        foreach (self::BLOCKED_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function looksSensitive(string $value): bool
    {
        return str_contains($value, '@')
            || str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://');
    }
}
