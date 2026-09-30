<?php

namespace Cultpantry\Market\Support;

use Cultpantry\Market\Models\MarketSchedule;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Expands schedules into the actual market days inside a date range, for the
 * calendar. start_date/end_date bound the season (either may be open); the
 * structured weekdays / week_of_month / times say which days and hours.
 */
class ScheduleOccurrences
{
    /**
     * @param  Collection<int, MarketSchedule>  $schedules  with 'market' loaded
     * @return array<int, array<string, mixed>>
     */
    public static function between(Collection $schedules, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];

        foreach ($schedules as $schedule) {
            if (! $schedule->market?->is_active || ! $schedule->isPlaceable()) {
                continue;
            }

            // Model attribute reads are the costly part per market day, so the
            // schedule's shared fields are read once and only the date varies.
            $template = self::template($schedule);
            foreach (self::dates($schedule, $from, $to) as $date) {
                $ymd = $date->toDateString();
                $row = $template;
                $row['id'] = "{$schedule->id}-{$ymd}";
                $row['date'] = $ymd;
                $rows[] = $row;
            }
        }

        usort($rows, fn ($a, $b) => [$a['date'], $a['start_time'] ?? '99', $a['market_name']] <=> [$b['date'], $b['start_time'] ?? '99', $b['market_name']]);

        return $rows;
    }

    /**
     * Jumps straight to the matching days rather than testing every day in
     * the range: weekly steps 7 days from the first matching weekday,
     * biweekly 14 (lined up with the anchor's fortnight first), monthly
     * computes each month's Nth/last weekday directly. Sorted ascending.
     *
     * @return array<int, CarbonImmutable>
     */
    public static function dates(MarketSchedule $schedule, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $seasonStart = $schedule->start_date ? CarbonImmutable::instance($schedule->start_date)->startOfDay() : null;
        $seasonEnd = $schedule->end_date ? CarbonImmutable::instance($schedule->end_date)->startOfDay() : null;

        if ($schedule->frequency === 'one_time') {
            // A one-day event, or every day of a multi-day fair.
            $seasonEnd ??= $seasonStart;
        }

        $start = $seasonStart && $seasonStart->gt($from) ? $seasonStart : $from->startOfDay();
        $end = $seasonEnd && $seasonEnd->lt($to) ? $seasonEnd : $to->startOfDay();

        if ($start->gt($end)) {
            return [];
        }

        $dates = [];

        if ($schedule->frequency === 'one_time') {
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $dates[] = $day;
            }

            return $dates;
        }

        $weekdays = $schedule->weekdays ?? [];

        if ($schedule->frequency === 'monthly') {
            $n = (int) $schedule->week_of_month;
            for ($month = $start->startOfMonth(); $month->lte($end); $month = $month->addMonth()) {
                foreach ($weekdays as $weekday) {
                    $day = self::nthWeekdayOfMonth($month, $weekday, $n);
                    if ($day !== null && $day->gte($start) && $day->lte($end)) {
                        $dates[] = $day;
                    }
                }
            }
        } else {
            $fortnightly = $schedule->frequency === 'biweekly' && $seasonStart !== null;
            foreach ($weekdays as $weekday) {
                $day = $start->addDays(($weekday - $start->dayOfWeek + 7) % 7);
                if ($fortnightly && ! self::isOnFortnight($day, $seasonStart)) {
                    $day = $day->addWeek();
                }
                for (; $day->lte($end); $day = $day->addDays($fortnightly ? 14 : 7)) {
                    $dates[] = $day;
                }
            }
        }

        usort($dates, fn (CarbonImmutable $a, CarbonImmutable $b) => $a <=> $b);

        return $dates;
    }

    /**
     * The Nth (1-4) or last (-1) given weekday of the month starting at
     * $month, or null when there's no such day (or no valid N).
     */
    private static function nthWeekdayOfMonth(CarbonImmutable $month, int $weekday, int $n): ?CarbonImmutable
    {
        if ($n === -1) {
            $last = $month->endOfMonth()->startOfDay();

            return $last->subDays(($last->dayOfWeek - $weekday + 7) % 7);
        }
        if ($n < 1) {
            return null;
        }

        $day = $month->addDays(($weekday - $month->dayOfWeek + 7) % 7)->addWeeks($n - 1);

        return $day->month === $month->month ? $day : null;
    }

    /**
     * Counted in whole weeks from the season's first week, so every listed
     * weekday of an "on" week is included. With no start_date there's no
     * anchor, so it's shown weekly rather than guessed at.
     */
    private static function isOnFortnight(CarbonImmutable $day, ?CarbonImmutable $anchor): bool
    {
        if ($anchor === null) {
            return true;
        }

        $weeks = intdiv((int) $anchor->startOfWeek(Carbon::SUNDAY)->diffInDays($day->startOfWeek(Carbon::SUNDAY)), 7);

        return $weeks % 2 === 0;
    }

    /**
     * @return array<string, mixed>
     */
    private static function template(MarketSchedule $schedule): array
    {
        $market = $schedule->market;

        return [
            'id' => null,
            'schedule_id' => $schedule->id,
            'market_id' => $market->id,
            'market_name' => $market->name,
            'city' => $market->city,
            'region' => $market->region,
            'label' => $schedule->label,
            'date' => null,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'address' => $schedule->address_line1 ?? $market->address_line1,
            'liveness_score' => $schedule->liveness_score,
        ];
    }
}
