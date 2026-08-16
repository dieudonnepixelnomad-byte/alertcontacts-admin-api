<?php

namespace App\Services\Routing;

use App\Services\Routing\DTO\AvoidArea;
use App\Services\Routing\DTO\RouteAlternative;
use App\Services\Routing\DTO\RouteRequest;
use App\Services\Routing\DTO\RouteResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HERE Routing API v8 — CDC V4.1 §5.2
 *
 * Seul fournisseur combinant évitement de zones polygonales arbitraires,
 * signalement explicite des violations (`violatedBlockedRoad`) et SDK Flutter
 * officiel. Google Routes ne propose aucune exclusion géographique arbitraire ;
 * l'exclusion Mapbox est en BETA, best-effort et limitée à 50 points.
 */
class HereRoutingProvider implements RoutingProvider
{
    /**
     * Au-delà, on bascule en POST. §5.4 étape 5 : « prévoir le POST dès la
     * V1 » — anticiper évite un bug tardif et difficile à diagnostiquer le
     * jour où un trajet croise cinq incidents.
     */
    private const MAX_URL_LENGTH = 4000;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {
    }

    public function name(): string
    {
        return 'here';
    }

    public function route(RouteRequest $request): RouteResult
    {
        $params = $this->buildParams($request);
        $url = $this->baseUrl . '/routes';

        $client = Http::timeout(10)->retry(2, 200, throw: false);

        // L'URL complète dépasse-t-elle la limite ? Alors POST avec le corps.
        $query = http_build_query($params);
        $response = strlen($url . '?' . $query) > self::MAX_URL_LENGTH
            ? $client->asForm()->post($url, $params)
            : $client->get($url, $params);

        if ($response->failed()) {
            Log::warning('[HereRoutingProvider] appel en échec', [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
            ]);

            throw RoutingException::unavailable('here', "HTTP {$response->status()}");
        }

        return $this->parse($response->json() ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParams(RouteRequest $request): array
    {
        $params = [
            'origin'        => $request->originLat . ',' . $request->originLng,
            'destination'   => $request->destinationLat . ',' . $request->destinationLng,
            'transportMode' => $request->transportMode,
            // routeLabels génère « via A86 » automatiquement : les libellés du
            // sélecteur d'itinéraires sont fournis par l'API, sans code à écrire.
            'return'        => 'polyline,summary,routeLabels',
            'alternatives'  => $request->alternatives,
            'lang'          => $request->lang,
            'apiKey'        => $this->apiKey,
        ];

        if ($request->avoidAreas !== []) {
            $params['avoid[areas]'] = $this->encodeAvoidAreas($request->avoidAreas);
        }

        return $params;
    }

    /**
     * Sérialise les zones au format HERE, concaténées par « | ».
     * Limite documentée : 250 zones, jamais atteinte en pratique (1 à 5).
     *
     * @param  array<int, AvoidArea>  $areas
     */
    private function encodeAvoidAreas(array $areas): string
    {
        $cap = (int) config('incidents.routing.max_avoid_areas', 20);
        $encoded = [];

        foreach (array_slice($areas, 0, $cap) as $area) {
            $coords = implode(';', array_map(
                static fn (array $p) => $p[0] . ',' . $p[1],
                $area->points
            ));

            $encoded[] = $area->type === 'corridor'
                ? "corridor:{$coords};r={$area->radiusM}"
                : "polygon:{$coords}";
        }

        return implode('|', $encoded);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function parse(array $payload): RouteResult
    {
        $routes = $payload['routes'] ?? [];

        if ($routes === []) {
            throw RoutingException::noRoute();
        }

        $alternatives = [];

        foreach ($routes as $route) {
            $sections = $route['sections'] ?? [];

            if ($sections === []) {
                continue;
            }

            // Un trajet voiture n'a qu'une section ; on concatène par sécurité.
            $polyline = $sections[0]['polyline'] ?? '';
            $distance = 0;
            $duration = 0;

            foreach ($sections as $section) {
                $distance += (int) ($section['summary']['length'] ?? 0);
                $duration += (int) ($section['summary']['duration'] ?? 0);
            }

            $alternatives[] = new RouteAlternative(
                polyline: $polyline,
                distanceM: $distance,
                durationS: $duration,
                labels: $this->extractLabels($sections),
                notices: $this->extractNotices($sections),
            );
        }

        if ($alternatives === []) {
            throw RoutingException::noRoute();
        }

        return new RouteResult($alternatives, $this->name());
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, string>
     */
    private function extractLabels(array $sections): array
    {
        $labels = [];

        foreach ($sections as $section) {
            foreach ($section['routeLabels'] ?? [] as $label) {
                $name = $label['name'] ?? null;

                if ($name !== null && !in_array($name, $labels, true)) {
                    $labels[] = $name;
                }
            }
        }

        // HERE en fournit deux au maximum par itinéraire
        return array_slice($labels, 0, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array{code: string, title: string, severity: string}>
     */
    private function extractNotices(array $sections): array
    {
        $notices = [];

        foreach ($sections as $section) {
            foreach ($section['notices'] ?? [] as $notice) {
                $notices[] = [
                    'code'     => (string) ($notice['code'] ?? ''),
                    'title'    => (string) ($notice['title'] ?? ''),
                    'severity' => (string) ($notice['severity'] ?? 'info'),
                ];
            }
        }

        return $notices;
    }
}
