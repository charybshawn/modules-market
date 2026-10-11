<?php

namespace Cultpantry\Market\Models;

use Cultpantry\Market\Support\Places;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $city
 * @property string|null $region one of Market::REGIONS
 * @property string|null $market_type one of Market::MARKET_TYPES
 * @property string|null $sponsor business or organization behind the market, when different from its name
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $province
 * @property string|null $postal_code
 * @property string|null $vendor_fees
 * @property string|null $phone
 * @property string|null $manager
 * @property string|null $manager_phone
 * @property string|null $manager_email
 * @property string|null $facebook_page
 * @property string|null $instagram_page
 * @property string|null $website
 * @property string|null $description
 * @property string|null $notes
 * @property string|null $sources
 * @property int|null $liveness_score 0-4, see Market::LIVENESS_LABELS
 * @property \Illuminate\Support\Carbon|null $liveness_checked_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $ignored_at set = ignored; see isIgnored()
 * @property string|null $ignored_reason
 */
class Market extends Model
{
    protected $table = 'market_markets';

    /**
     * Bucket labels for a schedule's frequency select -- shared between the
     * controller's validation Rule::in and the Vue form's options list so
     * the two never drift apart (the controller exposes this array to the
     * frontend as a prop rather than the Vue side hardcoding its own copy).
     */
    public const FREQUENCIES = [
        'one_time' => 'One-time',
        'weekly' => 'Weekly',
        'biweekly' => 'Biweekly',
        'monthly' => 'Monthly',
        'seasonal' => 'Seasonal',
        'other' => 'Other',
    ];

    /**
     * Controlled list, not free text -- a blend of Destination BC's 6
     * official tourism regions and well-known named sub-areas (Okanagan,
     * Shuswap, Similkameen, Thompson) split out where markets actually
     * cluster, since those narrower names are how BC markets get referred
     * to locally far more often than the broad "Thompson Okanagan" region
     * they technically sit inside. Shared with the Vue form's <select> and
     * the filter dropdown the same way FREQUENCIES is.
     *
     * The north is a single "Northern BC": it spans a huge area with few
     * people and few markets, so splitting it into Nechako, North Coast and
     * Northern Rockies left each nearly empty and put Prince George in none
     * of them (see RETIRED_REGIONS).
     */
    public const REGIONS = [
        'Boundary',
        'Cariboo Chilcotin Coast',
        'Fraser Valley',
        'Kootenay Rockies',
        'Metro Vancouver',
        'Northern BC',
        'Okanagan',
        'Sea to Sky',
        'Shuswap',
        'Similkameen',
        'Sunshine Coast',
        'Thompson',
        'Thompson Okanagan',
        'Vancouver Island',
    ];

    /**
     * Regions that used to be in REGIONS and are now part of another one, so
     * an old XML file or a stored record still lands in the right place.
     */
    public const RETIRED_REGIONS = [
        'Nechako' => 'Northern BC',
        'North Coast' => 'Northern BC',
        'Northern Rockies' => 'Northern BC',
    ];

    /**
     * What kind of market it is. A short controlled list, so the filter and
     * the dashboard group like with like; the nuance ("indoor winter",
     * "charity") belongs in the description.
     */
    public const MARKET_TYPES = [
        "Farmers' market",
        'Artisan market',
        'Craft fair',
        'Holiday market',
        'Night market',
        'Festival market',
        'Vintage & flea market',
        'Seed & plant sale',
    ];

    /**
     * Free-text types used before MARKET_TYPES existed, and the type each now
     * counts as, so an old XML file or a stored record still lands in the
     * right place. Matched case-insensitively.
     */
    public const RETIRED_MARKET_TYPES = [
        'Farmers' => "Farmers' market",
        'Farmers market' => "Farmers' market",
        'Farmers & Craft' => "Farmers' market",
        'Farmers (indoor winter)' => "Farmers' market",
        'Farmers / artisan market' => "Farmers' market",
        'Farmers, Artisan' => "Farmers' market",
        'Farm and craft' => "Farmers' market",
        'Community' => "Farmers' market",
        'Community market' => "Farmers' market",
        'Artisan' => 'Artisan market',
        'Artisan and vendor market' => 'Artisan market',
        'Artisan market (venue-based)' => 'Artisan market',
        'Craft / artisan market' => 'Artisan market',
        'Art / alternative craft market' => 'Artisan market',
        'Artisan / oddities' => 'Artisan market',
        'Pop-up vendor market' => 'Artisan market',
        'Boutique seasonal market' => 'Artisan market',
        'Winery artisan market' => 'Artisan market',
        'Artisan shop' => 'Artisan market',
        'Specialty grocery / artisan market (permanent store)' => 'Artisan market',
        'Seasonal market' => 'Artisan market',
        'Harvest market' => 'Artisan market',
        'Harvest / fundraiser market' => 'Artisan market',
        'Craft & gift fair' => 'Craft fair',
        'Charity craft fair' => 'Craft fair',
        'Hospital fundraiser craft fair' => 'Craft fair',
        'Craft festival' => 'Craft fair',
        'Art & artisan bazaar' => 'Craft fair',
        'Artisan / home show marketplace' => 'Craft fair',
        'Christmas market' => 'Holiday market',
        'Holiday craft fair' => 'Holiday market',
        'Christmas market / craft fair' => 'Holiday market',
        'Christmas market / fundraiser' => 'Holiday market',
        'Christmas market / night market' => 'Holiday market',
        'Holiday market / artisan market' => 'Holiday market',
        'Artisan night market' => 'Night market',
        'Evening vendor market' => 'Night market',
        'Garlic festival' => 'Festival market',
        'Festival' => 'Festival market',
        'Festival market (beverage festival)' => 'Festival market',
        'Street festival market' => 'Festival market',
        'Community festival (pollinator/biodiversity)' => 'Festival market',
        'Fair marketplace' => 'Festival market',
        'Spring market / farm festival' => 'Festival market',
        'Vintage market' => 'Vintage & flea market',
        'Flea' => 'Vintage & flea market',
        'Flea market' => 'Vintage & flea market',
        'Collector show' => 'Vintage & flea market',
        'Vintage & collectibles show' => 'Vintage & flea market',
        'Farm artisan / vintage market' => 'Vintage & flea market',
        'Seed swap' => 'Seed & plant sale',
        'Seed and plant sale' => 'Seed & plant sale',
    ];

    /**
     * The MARKET_TYPES entry a typed-in value means: itself, or what a
     * retired value now counts as, ignoring case. Null for no value, false for
     * one that matches nothing.
     */
    public static function canonicalMarketType(?string $value): string|null|false
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (self::MARKET_TYPES as $type) {
            if (strcasecmp($type, $value) === 0) {
                return $type;
            }
        }
        foreach (self::RETIRED_MARKET_TYPES as $old => $type) {
            if (strcasecmp($old, $value) === 0) {
                return $type;
            }
        }

        return false;
    }

    /**
     * How confident a liveness check is that a market is still actually
     * running -- see the find-bc-markets skill's scoring rubric
     * (.claude/skills/find-bc-markets/SKILL.md in this repo) for how
     * this gets computed during research. A plain 0-4 integer in the
     * database (not this labeled form) so it stays easy to sort/filter on;
     * these labels are for display only.
     */
    public const LIVENESS_LABELS = [
        0 => 'Likely defunct',
        1 => 'Likely defunct',
        2 => 'Probably active',
        3 => 'Probably active',
        4 => 'Confirmed active',
    ];

    /**
     * A liveness score at or below this marks the market inactive the moment
     * it's saved -- "likely defunct" shouldn't keep showing as an active
     * listing until someone remembers to untick a box. One-directional on
     * purpose: a higher score later never re-activates it (a person, or an
     * import, shouldn't silently overrule an earlier deactivation).
     */
    public const DEACTIVATE_AT_OR_BELOW = 1;

    protected static function booted(): void
    {
        // Only when the score itself is being changed, so an admin who
        // deliberately re-activates a market can save it again later
        // without the unchanged score flipping it back off.
        static::saving(function (Market $market) {
            if ($market->isDirty('liveness_score')
                && $market->liveness_score !== null
                && $market->liveness_score <= self::DEACTIVATE_AT_OR_BELOW) {
                $market->is_active = false;
            }
        });
    }

    protected $fillable = [
        'name',
        'city',
        'region',
        'market_type',
        'sponsor',
        'address_line1',
        'address_line2',
        'province',
        'postal_code',
        'vendor_fees',
        'phone',
        'manager',
        'manager_phone',
        'manager_email',
        'facebook_page',
        'instagram_page',
        'website',
        'description',
        'notes',
        'sources',
        'liveness_score',
        'liveness_checked_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'ignored_at' => 'datetime',
        'liveness_score' => 'integer',
        'liveness_checked_at' => 'date',
    ];

    /**
     * Ordered by start_date (undated schedules last) so seasonal ones read
     * chronologically wherever they're listed.
     */
    /**
     * A plain, comparable picture of this market's schedules (dates as
     * Y-m-d), for the audit events' before/after diff.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scheduleSnapshot(): array
    {
        return $this->schedules()->get()->map(fn (MarketSchedule $s) => [
            'label' => $s->label,
            'market_type' => $s->market_type,
            'frequency' => $s->frequency,
            'weekdays' => $s->weekdays,
            'week_of_month' => $s->week_of_month,
            'start_time' => $s->start_time,
            'end_time' => $s->end_time,
            'start_date' => $s->start_date?->toDateString(),
            'end_date' => $s->end_date?->toDateString(),
            'address_line1' => $s->address_line1,
            'notes' => $s->notes,
            'liveness_score' => $s->liveness_score,
            'liveness_checked_at' => $s->liveness_checked_at?->toDateString(),
        ])->all();
    }

    /**
     * Why this market can't be found by drive time, or 'ok' when it can:
     * 'no_city' (nothing to locate) or 'unknown_town' (a city the places file
     * doesn't have, so there is no point to route to).
     */
    public function locationStatus(): string
    {
        return match (true) {
            blank($this->city) => 'no_city',
            Places::find($this->city) === null => 'unknown_town',
            default => 'ok',
        };
    }

    /**
     * Ignored is the admin's own "not relevant to us", independent of
     * is_active: an ignored market is hidden from the list's default view
     * and the calendar, but imports still update it. ignored_at and
     * ignored_reason are deliberately not fillable -- only the market
     * page's Ignore action (MarketController::ignore) sets or clears them,
     * so neither the Edit form nor an XML import can undo the decision.
     */
    public function isIgnored(): bool
    {
        return $this->ignored_at !== null;
    }

    /**
     * Markets of any of $types: their own type, or the type of a schedule that
     * isn't over yet -- a farmers' market with an upcoming Christmas market
     * counts as a Holiday market until that run ends.
     *
     * @param  array<int, string>  $types
     */
    public function scopeOfType(Builder $query, array $types): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereIn('market_type', $types)
            ->orWhereHas('schedules', fn (Builder $s) => $s->whereIn('market_type', $types)->notOver()));
    }

    public function scopeNotIgnored(Builder $query): void
    {
        $query->whereNull('ignored_at');
    }

    /**
     * Replaces every schedule with $rows. A row that runs on the same days and
     * times as one it replaces keeps that one's created_at, so created_at says
     * when a schedule first appeared -- what the update email reports as new --
     * rather than when it was last re-saved.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function replaceSchedules(array $rows): void
    {
        $firstSeen = $this->schedules()->get()
            ->mapWithKeys(fn (MarketSchedule $s) => [$s->occurrenceKey() => $s->created_at])
            ->all();

        $this->schedules()->delete();
        $this->schedules()->createMany($rows);

        foreach ($this->schedules()->get() as $schedule) {
            if (isset($firstSeen[$schedule->occurrenceKey()])) {
                MarketSchedule::whereKey($schedule->id)->update(['created_at' => $firstSeen[$schedule->occurrenceKey()]]);
            }
        }
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MarketSchedule::class)
            ->orderByRaw('start_date is null')
            ->orderBy('start_date')
            ->orderBy('id');
    }
}
