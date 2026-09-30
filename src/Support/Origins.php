<?php

namespace Cultpantry\Market\Support;

use Illuminate\Support\Facades\DB;

/**
 * Where a "near" search starts: a town from the places list, or a full postal
 * code (located once by openrouteservice and remembered).
 */
class Origins
{
    public function __construct(private OpenRouteService $routing) {}

    /**
     * @return array{key: string, label: string, lat: float, lng: float}|null null when it is neither a known town nor a postal code that could be located
     *
     * @throws RoutingUnavailable when a postal code has to be looked up and the service can't answer
     */
    public function resolve(string $near): ?array
    {
        $near = trim($near);

        if ($town = Places::find($near)) {
            return ['key' => 'town:'.Places::normalize($town['name']), 'label' => $town['name'], 'lat' => $town['lat'], 'lng' => $town['lng']];
        }

        $postal = self::postalCode($near);

        return $postal === null ? null : $this->postal($postal);
    }

    /**
     * "v1e4n2", "V1E-4N2" and "V1E 4N2" all become "V1E 4N2"; null for anything
     * that isn't a full six-character Canadian postal code (the first three
     * characters alone can't be located).
     */
    public static function postalCode(string $text): ?string
    {
        if (! preg_match('/^\s*([A-Za-z]\d[A-Za-z])[\s-]*(\d[A-Za-z]\d)\s*$/', $text, $m)) {
            return null;
        }

        return strtoupper($m[1].' '.$m[2]);
    }

    /**
     * @return array{key: string, label: string, lat: float, lng: float}|null
     */
    private function postal(string $postalCode): ?array
    {
        $key = 'postal:'.str_replace(' ', '', $postalCode);

        $row = DB::table('market_origins')->where('origin_key', $key)->first();
        if ($row === null) {
            $found = $this->routing->postalCode($postalCode);
            if ($found === null) {
                return null;
            }

            DB::table('market_origins')->upsert(
                [['origin_key' => $key, 'label' => $found['label'], 'latitude' => $found['lat'], 'longitude' => $found['lng'], 'fetched_at' => now()]],
                ['origin_key'],
                ['label', 'latitude', 'longitude', 'fetched_at'],
            );

            return ['key' => $key, 'label' => $postalCode, 'lat' => $found['lat'], 'lng' => $found['lng']];
        }

        return ['key' => $key, 'label' => $postalCode, 'lat' => (float) $row->latitude, 'lng' => (float) $row->longitude];
    }
}
