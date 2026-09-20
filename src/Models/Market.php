<?php

namespace Cultpantry\Market\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $city
 * @property string|null $region one of Market::REGIONS
 * @property string|null $market_type
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
     */
    public const REGIONS = [
        'Boundary',
        'Cariboo Chilcotin Coast',
        'Fraser Valley',
        'Kootenay Rockies',
        'Metro Vancouver',
        'Nechako',
        'North Coast',
        'Northern Rockies',
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
        'liveness_score' => 'integer',
        'liveness_checked_at' => 'date',
    ];

    /**
     * Ordered by start_date (undated schedules last) so seasonal ones read
     * chronologically wherever they're listed.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(MarketSchedule::class)
            ->orderByRaw('start_date is null')
            ->orderBy('start_date')
            ->orderBy('id');
    }
}
