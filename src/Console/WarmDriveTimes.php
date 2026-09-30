<?php

namespace Cultpantry\Market\Console;

use Cultpantry\Market\Models\Market;
use Cultpantry\Market\Support\DriveTimes;
use Cultpantry\Market\Support\Places;
use Cultpantry\Market\Support\RoutingUnavailable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills the drive-time cache for every town someone could search from, so a
 * search never has to wait on the routing service. Optional: searches fill the
 * cache themselves as they go. Handy after adding a market in a new town (that
 * town is a new destination, so every starting town needs one more answer).
 */
class WarmDriveTimes extends Command
{
    protected $signature = 'market:warm-drive-times {--all : Ask again for every town, not only the ones with gaps}';

    protected $description = 'Cache drive times from every pickable town to the towns your markets are in';

    public function handle(DriveTimes $driveTimes): int
    {
        $marketCities = Market::query()->whereNotNull('city')->where('city', '!=', '')->distinct()->pluck('city');

        $destinations = [];
        foreach ($marketCities as $city) {
            if ($place = Places::find($city)) {
                $destinations[Places::normalize($place['name'])] = ['lat' => $place['lat'], 'lng' => $place['lng']];
            }
        }
        if ($destinations === []) {
            $this->warn('No market is in a town the places list knows, so there is nothing to compute.');

            return self::SUCCESS;
        }

        $fresh = DB::table('market_drive_times')
            ->where('fetched_at', '>=', now()->subDays(365))
            ->select('origin_key', DB::raw('count(*) as pairs'))
            ->groupBy('origin_key')
            ->pluck('pairs', 'origin_key');

        $origins = [];
        foreach (Places::options($marketCities) as $name) {
            $place = Places::find($name);
            $key = 'town:'.Places::normalize($place['name']);
            $needed = count($destinations) - (isset($destinations[substr($key, 5)]) ? 1 : 0);
            if ($this->option('all') || ($fresh[$key] ?? 0) < $needed) {
                $origins[] = ['key' => $key, 'lat' => $place['lat'], 'lng' => $place['lng']];
            }
        }

        if ($origins === []) {
            $this->info('Every starting town already has current drive times to all '.count($destinations).' market towns.');

            return self::SUCCESS;
        }

        $this->line(count($origins).' starting towns x '.count($destinations).' market towns...');

        try {
            $stored = $driveTimes->warm($origins, $destinations);
        } catch (RoutingUnavailable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Cached {$stored} drive times.");

        return self::SUCCESS;
    }
}
