<?php

namespace Cultpantry\Market\Http\Resources;

use Cultpantry\Market\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the market list page.
 *
 * @mixin Market
 */
class MarketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'region' => $this->region,
            'market_type' => $this->market_type,
            'sponsor' => $this->sponsor,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'liveness_score' => $this->liveness_score,
            'liveness_checked_at' => $this->liveness_checked_at?->toDateString(),
            'schedules' => $this->schedules->map(fn ($schedule) => [
                'id' => $schedule->id,
                'label' => $schedule->label,
                'frequency' => $schedule->frequency,
                'start_date' => $schedule->start_date?->toDateString(),
                'end_date' => $schedule->end_date?->toDateString(),
            ])->all(),
            // Set by FetchMarkets while a schedule filter is on: the schedules
            // that are the reason this market is in the list.
            'matched_schedule_ids' => $this->matched_schedule_ids ?? [],
        ];
    }
}
