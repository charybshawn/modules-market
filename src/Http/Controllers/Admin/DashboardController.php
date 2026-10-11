<?php

namespace Cultpantry\Market\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Market\Actions\BuildUpdateDigest;
use Cultpantry\Market\Models\Market;
use Cultpantry\Market\Models\MarketSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page for the module (the sidebar link opens it): how many markets
 * are on file, how they spread across regions and market types, and a day-by-day feed of what
 * was recently added. Ignored markets are left out of the counts, the same
 * way the list hides them unless asked.
 */
class DashboardController extends Controller implements HasMiddleware
{
    /** How many days with additions the feed shows. */
    public const FEED_DAYS = 10;

    /** How far back the feed looks for them. */
    public const FEED_LOOKBACK_DAYS = 90;

    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless($request->user()?->canAccessAdminPanel(), 403, 'Admin access required.');
                return $next($request);
            }),
            new Middleware(function ($request, $next) {
                abort_unless(app(GetSiteSetting::class)->handle('modules.cultpantry/market.enabled', true), 404);
                return $next($request);
            }),
        ];
    }

    public function index(BuildUpdateDigest $digest): Response
    {
        $this->authorize('viewAny', Market::class);

        $onFile = Market::query()->notIgnored();

        [$regions, $unassigned] = $this->countBy($onFile, 'region', Market::REGIONS);
        [, $untyped] = $this->countBy($onFile, 'market_type', Market::MARKET_TYPES);

        // By type, the way the list's type filter matches: a market's own type
        // or an upcoming schedule's, so one market can count under two types.
        $types = collect(Market::MARKET_TYPES)
            ->map(fn (string $type) => [
                'market_type' => $type,
                'total' => (clone $onFile)->ofType([$type])->count(),
                'active' => (clone $onFile)->ofType([$type])->where('is_active', true)->count(),
            ])
            ->sortBy([['total', 'desc'], ['market_type', 'asc']])
            ->values();

        return Inertia::render('Vendor/market/Dashboard', [
            'counts' => [
                'total' => (clone $onFile)->count(),
                'active' => (clone $onFile)->where('is_active', true)->count(),
                'ignored' => Market::whereNotNull('ignored_at')->count(),
            ],
            'regions' => $regions,
            'unassigned' => $unassigned,
            'types' => $types,
            'untyped' => $untyped,
            'updates' => $this->updates($digest),
            // This IS the module root, so a single, non-linked crumb.
            'breadcrumbs' => [['label' => "Farmer's Markets"]],
        ]);
    }

    /**
     * Every value in $list with its market count, busiest first, keyed by
     * $column ('region' => 'Shuswap'), plus the markets with no value or one
     * outside the list.
     *
     * @param  array<int, string>  $list
     * @return array{0: Collection<int, array<string, mixed>>, 1: array{total: int, active: int}}
     */
    private function countBy(Builder $markets, string $column, array $list): array
    {
        $counts = (clone $markets)
            ->selectRaw("{$column}, count(*) as total, sum(case when is_active then 1 else 0 end) as active")
            ->groupBy($column)
            ->get()
            ->keyBy(fn ($row) => $row->{$column} ?? '');

        $rows = collect($list)
            ->map(fn (string $value) => [
                $column => $value,
                'total' => (int) ($counts[$value]->total ?? 0),
                'active' => (int) ($counts[$value]->active ?? 0),
            ])
            ->sortBy([['total', 'desc'], [$column, 'asc']])
            ->values();

        $other = $counts->reject(fn ($row, $key) => in_array($key, $list, true));

        return [$rows, ['total' => (int) $other->sum('total'), 'active' => (int) $other->sum('active')]];
    }

    /**
     * What was added on each of the most recent days that had additions,
     * newest first, in the update email's words: new markets with their next
     * date, and new dates on markets already on file. Days whose additions
     * were all inactive or ignored markets, or dates already past, drop out.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function updates(BuildUpdateDigest $digest): Collection
    {
        $from = now()->subDays(self::FEED_LOOKBACK_DAYS);
        $day = fn ($at) => Carbon::parse($at)->toDateString();

        // Lazy, so the digest is only built for days until the feed is full.
        return Market::where('created_at', '>', $from)->pluck('created_at')->map($day)
            ->merge(MarketSchedule::where('created_at', '>', $from)->pluck('created_at')->map($day))
            ->unique()
            ->sortDesc()
            ->lazy()
            ->map(fn (string $date) => ['date' => $date] + $digest->handle(
                Carbon::parse($date)->startOfDay()->subSecond(),
                Carbon::parse($date)->endOfDay(),
            ))
            ->filter(fn (array $update) => $update['total'] > 0)
            ->take(self::FEED_DAYS)
            ->values()
            ->collect();
    }
}
