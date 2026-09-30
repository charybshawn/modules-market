<?php

namespace Cultpantry\Market\Actions;

use Cultpantry\Market\Models\Market;
use Cultpantry\Market\Models\MarketSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The admin market list: search, the chip filters, the schedule and liveness
 * filters, and sorting, applied on the server so the page can infinite-scroll
 * instead of loading every market.
 *
 * The schedule filters (frequency include/exclude, months, match all/any) are
 * applied in PHP on the loaded schedules rather than in SQL, because "which
 * months does this date range touch, ignoring the year" has no tidy SQL form.
 * That path loads every market matching the cheaper filters, which is fine at
 * this list's size and only happens while a schedule filter is switched on.
 */
class FetchMarkets
{
    public const PER_PAGE = 25;

    public const SORTABLE = ['name', 'city', 'region', 'sponsor', 'liveness_score'];

    private const SEARCHED = ['name', 'city', 'region', 'market_type', 'sponsor', 'phone'];

    /**
     * @param  array<string, mixed>  $filters  the validated query string; anything absent takes its default
     */
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $f = $this->normalize($filters);

        if (! $this->scheduleFilterActive($f)) {
            $paginator = $this->query($f)->paginate(self::PER_PAGE)->withQueryString();
            $paginator->getCollection()->each(fn (Market $market) => $market->setAttribute('matched_schedule_ids', []));

            return $paginator;
        }

        $matches = $this->matching($f);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return (new LengthAwarePaginator(
            $matches->forPage($page, self::PER_PAGE)->values(),
            $matches->count(),
            self::PER_PAGE,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))->withQueryString();
    }

    /**
     * Every market the filters match, unpaginated -- what the PDF prints.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Market>
     */
    public function all(array $filters = []): Collection
    {
        return $this->matching($this->normalize($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalize(array $filters): array
    {
        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            // Inactive markets stay hidden unless asked for, so a request
            // that says nothing about status is the "Active" view.
            'status' => $filters['status'] ?? 'active',
            'city' => array_values($filters['city'] ?? []),
            'region' => array_values($filters['region'] ?? []),
            'market_type' => array_values($filters['market_type'] ?? []),
            'sort' => in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'name',
            'direction' => ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            'freq_include' => array_values($filters['freq_include'] ?? []),
            'freq_exclude' => array_values($filters['freq_exclude'] ?? []),
            'months' => array_map('intval', array_values($filters['months'] ?? [])),
            'match' => ($filters['match'] ?? 'all') === 'any' ? 'any' : 'all',
            'liveness_min' => (int) ($filters['liveness_min'] ?? 0),
            'liveness_max' => (int) ($filters['liveness_max'] ?? 4),
            'liveness_unchecked' => (bool) ($filters['liveness_unchecked'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $f  normalized
     * @return Collection<int, Market>
     */
    private function matching(array $f): Collection
    {
        $markets = $this->query($f)->get();

        if (! $this->scheduleFilterActive($f)) {
            return $markets->each(fn (Market $market) => $market->setAttribute('matched_schedule_ids', []));
        }

        $needsAMatch = $f['freq_include'] !== [] || $f['months'] !== [];

        return $markets->flatMap(function (Market $market) use ($f, $needsAMatch) {
            $matched = $market->schedules->filter(fn (MarketSchedule $schedule) => $this->scheduleMatches($schedule, $f));
            // Matched schedules are highlighted only when they are the reason
            // the market is listed; with exclusions alone the list stays compact.
            $market->setAttribute('matched_schedule_ids', $needsAMatch ? $matched->pluck('id')->values()->all() : []);

            if ($needsAMatch) {
                return $matched->isNotEmpty() ? [$market] : [];
            }

            // Only exclusions: hide a market once all of its schedules are
            // excluded ("not weekly" drops a weekly-only market), but keep one
            // that has no schedules at all.
            return $market->schedules->isNotEmpty() && $matched->isEmpty() ? [] : [$market];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $f  normalized
     */
    private function query(array $f): Builder
    {
        $query = Market::query()->with('schedules');

        if ($f['status'] !== 'all') {
            $query->where('is_active', $f['status'] === 'active');
        }

        foreach (['city', 'region', 'market_type'] as $column) {
            if ($f[$column] !== []) {
                $query->whereIn($column, $f[$column]);
            }
        }

        if ($f['search'] !== '') {
            $like = '%'.addcslashes($f['search'], '\\%_').'%';
            $query->where(function (Builder $search) use ($like) {
                foreach (self::SEARCHED as $column) {
                    $search->orWhere($column, 'like', $like);
                }
            });
        }

        if ($f['liveness_min'] > 0 || $f['liveness_max'] < 4) {
            $query->where(function (Builder $range) use ($f) {
                $range->whereBetween('liveness_score', [$f['liveness_min'], $f['liveness_max']]);
                if ($f['liveness_unchecked']) {
                    $range->orWhereNull('liveness_score');
                }
            });
        }

        $query->orderBy($f['sort'], $f['direction']);
        if ($f['sort'] !== 'name') {
            $query->orderBy('name');
        }

        return $query->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $f  normalized
     */
    private function scheduleFilterActive(array $f): bool
    {
        return $f['freq_include'] !== [] || $f['freq_exclude'] !== [] || $f['months'] !== [];
    }

    /**
     * An excluded frequency can never match; otherwise a schedule must satisfy
     * whichever include groups are active, all of them or any of them.
     */
    /**
     * @param  array<string, mixed>  $f  normalized
     */
    private function scheduleMatches(MarketSchedule $schedule, array $f): bool
    {
        if ($schedule->frequency && in_array($schedule->frequency, $f['freq_exclude'], true)) {
            return false;
        }

        $checks = [];
        if ($f['freq_include'] !== []) {
            $checks[] = (bool) $schedule->frequency && in_array($schedule->frequency, $f['freq_include'], true);
        }
        if ($f['months'] !== []) {
            $checks[] = array_intersect($f['months'], $this->monthsCovered($schedule)) !== [];
        }

        if ($checks === []) {
            return true;
        }

        return $f['match'] === 'all' ? ! in_array(false, $checks, true) : in_array(true, $checks, true);
    }

    /**
     * Every calendar month a schedule touches, ignoring the year: a 2023
     * edition in November still says "this runs in November". A schedule with
     * no dates covers nothing (unknown, not "always"); one date covers just
     * its month.
     *
     * @return array<int, int>
     */
    private function monthsCovered(MarketSchedule $schedule): array
    {
        $start = $schedule->start_date;
        $end = $schedule->end_date;

        if (! $start && ! $end) {
            return [];
        }
        if (! $start || ! $end) {
            return [($start ?? $end)->month];
        }

        $covered = [];
        $year = $start->year;
        $month = $start->month;
        $last = $end->year * 12 + $end->month;
        for ($i = 0; $i < 12 && $year * 12 + $month <= $last; $i++) {
            $covered[] = $month;
            if (++$month > 12) {
                $month = 1;
                $year++;
            }
        }

        return $covered;
    }
}
