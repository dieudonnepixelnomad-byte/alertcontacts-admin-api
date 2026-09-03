<?php

namespace App\Services;

use App\Models\User;
use DateTimeInterface;
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

    public function capture(
        User|string|null $user,
        string $event,
        array $properties = [],
        ?DateTimeInterface $timestamp = null,
        ?string $eventUuid = null,
    ): void {
        $apiKey = (string) config('services.posthog.project_api_key', '');
        if ($apiKey === '' || ! $this->hasAnalyticsConsent($user)) {
            return;
        }

        $payload = [
            'api_key' => $apiKey,
            'event' => $event,
            'distinct_id' => $this->distinctId($user),
            'properties' => $this->sanitize($properties + [
                'source' => 'laravel',
                'environment' => app()->environment(),
            ]),
        ];

        if ($timestamp !== null) {
            $payload['timestamp'] = $timestamp->format(DATE_ATOM);
        }
        if ($eventUuid !== null) {
            $payload['uuid'] = $eventUuid;
        }

        app()->terminating(function () use ($event, $payload): void {
            $this->send($event, $payload);
        });
    }

    public function setPersonProperties(User|string|null $user, array $properties): void
    {
        $apiKey = (string) config('services.posthog.project_api_key', '');
        if ($apiKey === '' || ! $this->hasAnalyticsConsent($user)) {
            return;
        }

        $personProperties = $this->sanitize($properties);
        if ($personProperties === []) {
            return;
        }

        $payload = [
            'api_key' => $apiKey,
            'event' => 'backend_person_properties_updated',
            'distinct_id' => $this->distinctId($user),
            'properties' => [
                '$set' => $personProperties,
                '$update_person_last_seen_at' => false,
                'source' => 'laravel',
                'environment' => app()->environment(),
            ],
        ];

        app()->terminating(function () use ($payload): void {
            $this->send('backend_person_properties_updated', $payload);
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

    private function distinctId(User|string|null $user): string
    {
        if ($user instanceof User) {
            return (string) ($user->firebase_uid ?: $user->id);
        }

        return (string) ($user ?: 'server');
    }

    private function hasAnalyticsConsent(User|string|null $user): bool
    {
        if (! $user instanceof User) {
            return true;
        }

        return $user->analytics_consent !== false;
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
