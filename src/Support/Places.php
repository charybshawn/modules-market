<?php

namespace Cultpantry\Market\Support;

use Illuminate\Support\Str;

/**
 * BC place names with coordinates (resources/data/bc-places.json, from
 * GeoNames plus anything added by hand). A market's location is its city's
 * point here, and it is what drive times are measured to: the file is the whole
 * lookup, and a missing town is fixed by adding a line to it.
 */
class Places
{
    /** @var array<int, array{name: string, lat: float, lng: float, pop: int, aliases?: array<int, string>}>|null */
    private static ?array $places = null;

    /** @var array<string, array<int, array{name: string, lat: float, lng: float, pop: int}>>|null */
    private static ?array $index = null;

    /**
     * The best match for a city name, or null when the file doesn't know it.
     * When several places share a name (BC has repeats) the most populated
     * one wins.
     *
     * @return array{name: string, lat: float, lng: float, pop: int}|null
     */
    public static function find(?string $name): ?array
    {
        $key = self::normalize((string) $name);
        if ($key === '') {
            return null;
        }

        return self::index()[$key][0] ?? null;
    }

    /**
     * The place's own normalized spelling, so "Salmon Arm, BC" and "salmon arm"
     * are one key ("salmon arm"); null when the file doesn't know the place.
     */
    public static function key(?string $name): ?string
    {
        $place = self::find($name);

        return $place === null ? null : self::normalize($place['name']);
    }

    /**
     * How many places share this name, for spotting a match that might be the
     * wrong "Lakeview".
     */
    public static function matches(?string $name): int
    {
        return count(self::index()[self::normalize((string) $name)] ?? []);
    }

    /**
     * Names for the "near" picker: towns big enough to be a sensible
     * reference point, plus any place a market is actually in, however small.
     *
     * @param  iterable<int, string>  $alsoNames
     * @return array<int, string>
     */
    public static function options(iterable $alsoNames = [], int $minPopulation = 1000): array
    {
        $names = collect(self::all())
            ->filter(fn (array $place) => $place['pop'] >= $minPopulation)
            ->pluck('name');

        foreach ($alsoNames as $name) {
            $place = self::find($name);
            if ($place !== null) {
                $names->push($place['name']);
            }
        }

        return $names->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /**
     * "Salmon Arm, BC", "salmon arm" and "Kelowna (Rutland)" all reduce to the
     * same key as the file's own spelling.
     */
    public static function normalize(string $name): string
    {
        $name = Str::lower(Str::ascii($name));
        $name = preg_replace('/\(.*?\)/', ' ', $name);
        $name = preg_replace('/,\s*(bc|b\.c\.|british columbia)\s*$/', '', $name);
        $name = preg_replace('/\b(saint|st\.?)\s/', 'st ', $name);
        $name = preg_replace('/\b(mount|mt\.?)\s/', 'mt ', $name);
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name);

        return trim($name);
    }

    /**
     * @return array<int, array{name: string, lat: float, lng: float, pop: int, aliases?: array<int, string>}>
     */
    private static function all(): array
    {
        if (self::$places === null) {
            $file = dirname(__DIR__, 2).'/resources/data/bc-places.json';
            self::$places = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)['places'];
        }

        return self::$places;
    }

    /**
     * @return array<string, array<int, array{name: string, lat: float, lng: float, pop: int}>>
     */
    private static function index(): array
    {
        if (self::$index === null) {
            self::$index = [];
            foreach (self::all() as $place) {
                foreach ([$place['name'], ...($place['aliases'] ?? [])] as $name) {
                    self::$index[self::normalize($name)][] = $place;
                }
            }
            foreach (self::$index as &$candidates) {
                usort($candidates, fn (array $a, array $b) => $b['pop'] <=> $a['pop']);
            }
        }

        return self::$index;
    }
}
