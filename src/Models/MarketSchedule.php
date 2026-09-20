<?php

namespace Cultpantry\Market\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $market_id
 * @property string|null $label
 * @property string|null $frequency one of Market::FREQUENCIES' keys
 * @property string|null $frequency_detail
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
        'start_date',
        'end_date',
        'address_line1',
        'notes',
        'liveness_score',
        'liveness_checked_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'liveness_score' => 'integer',
        'liveness_checked_at' => 'date',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
