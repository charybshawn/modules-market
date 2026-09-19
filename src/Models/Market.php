<?php

namespace Cultpantry\Market\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $city
 * @property string|null $region
 * @property string|null $market_type
 * @property string|null $address
 * @property string|null $frequency 'one_time'|'weekly'|'biweekly'|'monthly'|'seasonal'|'other'
 * @property string|null $frequency_detail
 * @property string|null $vendor_fees
 * @property string|null $phone
 * @property string|null $manager
 * @property string|null $manager_email
 * @property string|null $facebook_page
 * @property string|null $instagram_page
 * @property string|null $website
 * @property string|null $description
 * @property string|null $notes
 * @property string|null $sources
 * @property bool $is_active
 */
class Market extends Model
{
    protected $table = 'market_markets';

    /**
     * Bucket labels for the frequency select -- shared between the
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

    protected $fillable = [
        'name',
        'city',
        'region',
        'market_type',
        'address',
        'frequency',
        'frequency_detail',
        'vendor_fees',
        'phone',
        'manager',
        'manager_email',
        'facebook_page',
        'instagram_page',
        'website',
        'description',
        'notes',
        'sources',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
