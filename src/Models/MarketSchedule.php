<?php

namespace Cultpantry\Market\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $market_id
 * @property string|null $label
 * @property string|null $frequency one of Market::FREQUENCIES' keys
 * @property string|null $frequency_detail
 * @property array<int, int>|null $weekdays 0 = Sunday ... 6 = Saturday
 * @property int|null $week_of_month 1-4, or -1 for the last; monthly only
 * @property string|null $start_time H:i
 * @property string|null $end_time H:i
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null $address_line1 only when held away from the market's own address
 * @property string|null $notes
 * @property int $liveness_score 0-4, see Market::LIVENESS_LABELS
 * @property \Illuminate\Support\Carbon $liveness_checked_at
 */
class MarketSchedule extends Model
{
    protected $table = 'market_schedules';

    protected $fillable = [
        'market_id',
        'label',
        'frequency',
        'frequency_detail',
        'weekdays',
        'week_of_month',
        'start_time',
        'end_time',
        'start_date',
        'end_date',
        'address_line1',
        'notes',
        'liveness_score',
        'liveness_checked_at',
    ];

    /**
     * Short keys as they appear in the XML (<weekdays>sat,sun</weekdays>),
     * indexed by Carbon's dayOfWeek so the stored ints line up with it.
     */
    public const WEEKDAYS = [
        0 => 'sun',
        1 => 'mon',
        2 => 'tue',
        3 => 'wed',
        4 => 'thu',
        5 => 'fri',
        6 => 'sat',
    ];

    public const WEEKS_OF_MONTH = [
        1 => '1st',
        2 => '2nd',
        3 => '3rd',
        4 => '4th',
        -1 => 'Last',
    ];

    protected $casts = [
        'weekdays' => 'array',
        'week_of_month' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'liveness_score' => 'integer',
        'liveness_checked_at' => 'date',
    ];

    protected static function booted(): void
    {
        // One canonical form (sorted unique ints, [] -> null) whichever path
        // wrote it -- form, modal or XML -- so snapshots compare equal.
        static::saving(function (MarketSchedule $schedule) {
            $days = collect($schedule->weekdays ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();
            $schedule->weekdays = $days === [] ? null : $days;
            if ($schedule->frequency !== 'monthly') {
                $schedule->week_of_month = null;
            }
        });
    }

    /**
     * Stored and handed out as H:i -- MySQL's TIME column reads back as
     * H:i:s, which would make an unchanged re-import look like an edit.
     */
    protected function startTime(): Attribute
    {
        return self::hourMinute();
    }

    protected function endTime(): Attribute
    {
        return self::hourMinute();
    }

    private static function hourMinute(): Attribute
    {
        $trim = fn (?string $value) => $value === null || $value === '' ? null : substr($value, 0, 5);

        return Attribute::make(get: $trim, set: $trim);
    }

    /**
     * Enough structure for the calendar to place this schedule on actual days.
     */
    public function isPlaceable(): bool
    {
        if ($this->frequency === 'one_time') {
            return $this->start_date !== null;
        }
        if (empty($this->weekdays)) {
            return false;
        }

        return $this->frequency !== 'monthly' || $this->week_of_month !== null;
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
