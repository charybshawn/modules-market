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

            foreach (self::dates($schedule, $from, $to) as $date) {
                $rows[] = self::row($schedule, $date);
            }
        }

        usort($rows, fn ($a, $b) => [$a['date'], $a['start_time'] ?? '99', $a['market_name']] <=> [$b['date'], $b['start_time'] ?? '99', $b['market_name']]);

        return $rows;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    public static function dates(MarketSchedule $schedule, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $seasonStart = $schedule->start_date ? CarbonImmutable::instance($schedule->start_date)->startOfDay() : null;
        $seasonEnd = $schedule->end_date ? CarbonImmutable::instance($schedule->end_date)->startOfDay() : null;

        if ($schedule->frequency === 'one_time') {
            // A one-day event, or every day of a multi-day fair.
            $seasonEnd ??= $seasonStart;
            $weekdays = null;
        } else {
            $weekdays = $schedule->weekdays;
        }

        $start = $seasonStart && $seasonStart->gt($from) ? $seasonStart : $from->startOfDay();
        $end = $seasonEnd && $seasonEnd->lt($to) ? $seasonEnd : $to->startOfDay();

        $dates = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            if ($weekdays !== null && ! in_array($day->dayOfWeek, $weekdays, true)) {
                continue;
            }
            if ($schedule->frequency === 'monthly' && ! self::isNthWeekday($day, (int) $schedule->week_of_month)) {
                continue;
            }
            if ($schedule->frequency === 'biweekly' && ! self::isOnFortnight($day, $seasonStart)) {
                continue;
            }
            $dates[] = $day;
        }

        return $dates;
    }

    private static function isNthWeekday(CarbonImmutable $day, int $n): bool
    {
        if ($n === -1) {
            return $day->addWeek()->month !== $day->month;
        }

        return intdiv($day->day - 1, 7) + 1 === $n;
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
    private static function row(MarketSchedule $schedule, CarbonImmutable $date): array
    {
        $market = $schedule->market;

        return [
            'id' => "{$schedule->id}-{$date->toDateString()}",
            'schedule_id' => $schedule->id,
            'market_id' => $market->id,
            'market_name' => $market->name,
            'city' => $market->city,
            'region' => $market->region,
            'label' => $schedule->label,
            'date' => $date->toDateString(),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'frequency_detail' => $schedule->frequency_detail,
            'address' => $schedule->address_line1 ?? $market->address_line1,
            'liveness_score' => $schedule->liveness_score,
        ];
    }
}
