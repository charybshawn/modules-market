<?php

namespace Cultpantry\Market\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The two things asked of openrouteservice.org (a free key; routing data is
 * OpenStreetMap's): where a postal code is, and how long the drive between
 * places takes. Every answer is cached by the callers, so this is only reached
 * the first time a pair is asked about.
 */
class OpenRouteService
{
    /**
     * @return array{lat: float, lng: float, label: string}|null null when nothing matches
     *
     * @throws RoutingUnavailable
     */
    public function postalCode(string $postalCode): ?array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('/geocode/search', [
            'text' => $postalCode,
            'boundary.country' => 'CA',
            'size' => 3,
        ]));

        foreach ($response->json('features') ?? [] as $feature) {
            if (($feature['properties']['layer'] ?? null) === 'postalcode') {
                return [
                    'lat' => (float) $feature['geometry']['coordinates'][1],
                    'lng' => (float) $feature['geometry']['coordinates'][0],
                    'label' => (string) ($feature['properties']['label'] ?? $postalCode),
                ];
            }
        }

        return null;
    }

    /**
     * Drive minutes and kilometres from every source to every destination.
     *
     * @param  array<int, array{lat: float, lng: float}>  $sources
     * @param  array<int, array{lat: float, lng: float}>  $destinations
     * @return array<int, array<int, array{minutes: int, km: float}|null>> [source][destination]; null = no route
     *
     * @throws RoutingUnavailable
     */
    public function matrix(array $sources, array $destinations): array
    {
        $locations = array_map(fn (array $p) => [$p['lng'], $p['lat']], [...$sources, ...$destinations]);
        $sourceCount = count($sources);

        $response = $this->send(fn (PendingRequest $http) => $http->post('/v2/matrix/driving-car', [
            'locations' => $locations,
            'sources' => range(0, $sourceCount - 1),
            'destinations' => range($sourceCount, $sourceCount + count($destinations) - 1),
            'metrics' => ['distance', 'duration'],
            'units' => 'km',
        ]));

        $durations = $response->json('durations');
        $distances = $response->json('distances');
        if (! is_array($durations) || ! is_array($distances)) {
            throw new RoutingUnavailable('The routing service returned an answer this app could not read.');
        }

        $result = [];
        foreach ($durations as $i => $row) {
            foreach ($row as $j => $seconds) {
                $km = $distances[$i][$j] ?? null;
                $result[$i][$j] = $seconds === null || $km === null
                    ? null
                    : ['minutes' => (int) round($seconds / 60), 'km' => round((float) $km, 1)];
            }
        }

        return $result;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        $key = config('market.openrouteservice.key');
        if (blank($key)) {
            throw new RoutingUnavailable('No openrouteservice key is set (ORS_API_KEY).');
        }

        try {
            $response = $call(
                Http::withHeaders(['Authorization' => $key, 'Accept' => 'application/json'])
                    ->baseUrl(config('market.openrouteservice.base_url'))
                    ->timeout(30)
            );
        } catch (ConnectionException $e) {
            Log::warning('openrouteservice unreachable: '.$e->getMessage());
            throw new RoutingUnavailable('The routing service could not be reached.', 0, $e);
        }

        if ($response->failed()) {
            Log::warning('openrouteservice refused a request', ['status' => $response->status(), 'body' => substr($response->body(), 0, 200)]);
            throw new RoutingUnavailable("The routing service answered {$response->status()}.");
        }

        return $response;
    }
}
