<?php

namespace Cultpantry\Market\Actions;

use Carbon\CarbonImmutable;
use Cultpantry\Market\Models\Market;
use Cultpantry\Market\Models\MarketSchedule;
use Cultpantry\Market\Support\ScheduleOccurrences;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What was added to the directory in the window (since, until], shaped for
 * the update email:
 *  - new: markets added in the window, soonest next date first;
 *  - dates: new schedules (an event, a new season) on markets that were
 *    already on file, but only ones with a date still to come.
 * Ignored and inactive markets are left out. A schedule counts as new by its
 * created_at, which Market::replaceSchedules() keeps across re-imports for a
 * schedule whose days and times didn't change.
 */
class BuildUpdateDigest
{
    /** Markets listed before "and N more". */
    public const NEW = 10;

    /** Markets with new dates listed before "and N more". */
    public const DATES = 10;

    /** How far ahead a schedule's next date is looked for. */
    private const HORIZON_DAYS = 365;

    /**
     * @return array{markets: int, dated_markets: int, total: int,
     *   new: array<int, array{name: string, city: ?string, type: ?string, url: string, next: ?string}>, more_new: int,
     *   dates: array<int, array{name: string, city: ?string, url: string, items: array<int, string>}>, more_dates: int}
     */
    public function handle(Carbon $since, Carbon $until): array
    {
        $today = CarbonImmutable::today();
        $horizon = $today->addDays(self::HORIZON_DAYS);
        $listed = fn ($query) => $query->where('is_active', true)->notIgnored();

        $markets = $listed(Market::query())
            ->where('created_at', '>', $since)->where('created_at', '<=', $until)
            ->with('schedules')->get();

        $new = $markets
            ->map(function (Market $m) use ($today, $horizon) {
                $next = $m->schedules
                    ->map(fn (MarketSchedule $s) => $this->next($s, $today, $horizon))
                    ->filter()->sortBy('date')->first();

                return [
                    'name' => $m->name,
                    'city' => $m->city,
                    'type' => $m->market_type,
                    'url' => route('admin.market.show', $m),
                    'next' => $next ? ($next['label'] ? $next['label'].', ' : '').$next['text'] : null,
                    'sort' => $next['date'] ?? '9999-12-31',
                ];
            })
            ->sortBy(fn ($row) => [$row['sort'], $row['name']])
            ->map(fn ($row) => array_diff_key($row, ['sort' => true]))
            ->values();

        $schedules = MarketSchedule::query()
            ->where('created_at', '>', $since)->where('created_at', '<=', $until)
            ->whereHas('market', fn ($q) => $listed($q)->where('created_at', '<=', $since))
            ->with('market')->get();

        $dates = $schedules
            ->map(fn (MarketSchedule $s) => ['schedule' => $s, 'next' => $this->next($s, $today, $horizon)])
            ->filter(fn ($row) => $row['next'] !== null)
            ->groupBy(fn ($row) => $row['schedule']->market_id)
            ->map(function (Collection $rows) {
                $market = $rows->first()['schedule']->market;
                $sorted = $rows->sortBy(fn ($row) => $row['next']['date']);

                return [
                    'name' => $market->name,
                    'city' => $market->city,
                    'url' => route('admin.market.show', $market),
                    'items' => $sorted->map(fn ($row) => ($row['next']['label'] ?? 'New dates').', '.$row['next']['text'])->values()->all(),
                    'sort' => $sorted->first()['next']['date'],
                ];
            })
            ->sortBy(fn ($row) => [$row['sort'], $row['name']])
            ->map(fn ($row) => array_diff_key($row, ['sort' => true]))
            ->values();

        return [
            'markets' => $new->count(),
            'dated_markets' => $dates->count(),
            'total' => $new->count() + $dates->count(),
            'new' => $new->take(self::NEW)->all(),
            'more_new' => max(0, $new->count() - self::NEW),
            'dates' => $dates->take(self::DATES)->all(),
            'more_dates' => max(0, $dates->count() - self::DATES),
        ];
    }

    /**
     * The schedule's next date from today, as text: a one-time event's full
     * run ("Sat, Nov 28 to Sun, Nov 29"), otherwise its next market day
     * ("next Sat, Oct 17"). Null when it has no date to come.
     *
     * @return array{date: string, label: ?string, text: string}|null
     */
    private function next(MarketSchedule $schedule, CarbonImmutable $today, CarbonImmutable $horizon): ?array
    {
        if (! $schedule->isPlaceable()) {
            return null;
        }

        $date = ScheduleOccurrences::dates($schedule, $today, $horizon)[0] ?? null;
        if ($date === null) {
            return null;
        }

        if ($schedule->frequency === 'one_time') {
            $start = CarbonImmutable::instance($schedule->start_date);
            $end = $schedule->end_date ? CarbonImmutable::instance($schedule->end_date) : $start;
            $text = $end->gt($start) ? $this->day($start).' to '.$this->day($end) : $this->day($start);
        } else {
            $text = 'next '.$this->day($date);
        }

        return ['date' => $date->toDateString(), 'label' => $schedule->label, 'text' => $text];
    }

    private function day(CarbonImmutable $date): string
    {
        return $date->format('D, M j');
    }
}
