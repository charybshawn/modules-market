<?php

namespace Cultpantry\Market\Support;

use Illuminate\Support\Facades\DB;

/**
 * Drive time and distance from a starting point to market towns. Answers come
 * from the market_drive_times cache, and only the pairs not yet in it are asked
 * of the routing service (all in one request), so a repeat search costs nothing.
 */
class DriveTimes
{
    /** The routing service's cap on origins x destinations in one matrix request. */
    private const MAX_ROUTES = 3500;

    /** Roads change slowly; a year-old answer is asked again. */
    private const FRESH_DAYS = 365;

    public function __construct(private OpenRouteService $routing) {}

    /**
     * @param  array{key: string, lat: float, lng: float}  $origin
     * @param  array<string, array{lat: float, lng: float}>  $destinations  by place key (Places::key)
     * @return array<string, array{minutes: int, km: float}|null> null = no drivable route
     *
     * @throws RoutingUnavailable when pairs are missing and the service can't answer
     */
    public function from(array $origin, array $destinations): array
    {
        $here = str_starts_with($origin['key'], 'town:') ? substr($origin['key'], 5) : null;
        $toAsk = array_diff_key($destinations, $here === null ? [] : [$here => true]);

        $known = $this->cached($origin['key'], array_keys($toAsk));
        $missing = array_diff_key($toAsk, $known);

        if ($missing !== []) {
            $this->store($this->fetch([$origin], $missing));
            $known = $this->cached($origin['key'], array_keys($toAsk));
        }

        $result = [];
        foreach ($destinations as $key => $_) {
            $result[$key] = $key === $here
                ? ['minutes' => 0, 'km' => 0.0]
                : (isset($known[$key]) && $known[$key]->minutes !== null
                    ? ['minutes' => $this->adjusted((int) $known[$key]->minutes), 'km' => (float) $known[$key]->km]
                    : null);
        }

        return $result;
    }

    /**
     * The routing service times a drive from posted speed limits, which runs
     * long against what people actually drive (Salmon Arm to Vernon: 48 min
     * against Google's 39). The cache keeps the service's own figure, and this
     * scales it on the way out, so changing the factor needs no refetch.
     */
    private function adjusted(int $minutes): int
    {
        return (int) round($minutes * (float) config('market.drive_time_factor', 1.0));
    }

    /**
     * Fills the cache for many origins at once, in as few requests as the
     * service's cap on a matrix allows.
     *
     * @param  array<int, array{key: string, lat: float, lng: float}>  $origins
     * @param  array<string, array{lat: float, lng: float}>  $destinations
     * @return int pairs stored
     *
     * @throws RoutingUnavailable
     */
    public function warm(array $origins, array $destinations): int
    {
        $stored = 0;
        $perRequest = max(1, intdiv(self::MAX_ROUTES, max(1, count($destinations))));

        foreach (array_chunk($origins, $perRequest) as $chunk) {
            $rows = $this->fetch($chunk, $destinations);
            $this->store($rows);
            $stored += count($rows);
        }

        return $stored;
    }

    /**
     * @param  array<int, array{key: string, lat: float, lng: float}>  $origins
     * @param  array<string, array{lat: float, lng: float}>  $destinations
     * @return array<int, array<string, mixed>> rows for market_drive_times
     */
    private function fetch(array $origins, array $destinations): array
    {
        $keys = array_keys($destinations);
        $matrix = $this->routing->matrix(array_values($origins), array_values($destinations));
        $now = now();

        $rows = [];
        foreach (array_values($origins) as $i => $origin) {
            foreach ($keys as $j => $destinationKey) {
                // A market's own town is 0 minutes by definition; from() never asks.
                if ($origin['key'] === 'town:'.$destinationKey) {
                    continue;
                }

                $answer = $matrix[$i][$j] ?? null;
                $rows[] = [
                    'origin_key' => $origin['key'],
                    'destination_key' => $destinationKey,
                    'minutes' => $answer['minutes'] ?? null,
                    'km' => $answer['km'] ?? null,
                    'fetched_at' => $now,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function store(array $rows): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('market_drive_times')->upsert($chunk, ['origin_key', 'destination_key'], ['minutes', 'km', 'fetched_at']);
        }
    }

    /**
     * @param  array<int, string>  $destinationKeys
     * @return array<string, object>
     */
    private function cached(string $originKey, array $destinationKeys): array
    {
        if ($destinationKeys === []) {
            return [];
        }

        return DB::table('market_drive_times')
            ->where('origin_key', $originKey)
            ->whereIn('destination_key', $destinationKeys)
            ->where('fetched_at', '>=', now()->subDays(self::FRESH_DAYS))
            ->get()
            ->keyBy('destination_key')
            ->all();
    }
}
