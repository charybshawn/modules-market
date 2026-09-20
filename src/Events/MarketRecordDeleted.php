<?php

namespace Cultpantry\Market\Events;

use Cultpantry\Market\Models\Market;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a market is deleted. See MarketRecordSaved for the decoupling
 * rationale -- same properties here.
 */
final class MarketRecordDeleted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $attributes  Full snapshot of the deleted
     *     row -- captured before ->delete(), not derived from this event.
     * @param  array<string, mixed>  $context  Includes the market's 'schedules'
     *     snapshot, since deleting the market cascades to them.
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly int|string $modelId,
        public readonly string $label,
        public readonly array $attributes,
        public readonly ?int $actorId,
        public readonly array $context = [],
    ) {}

    /**
     * Call *before* $market->delete().
     */
    public static function forModel(Market $market, ?int $actorId, array $context = []): self
    {
        return new self(
            modelClass: $market::class,
            modelId: $market->getKey(),
            label: $market->name,
            attributes: $market->getAttributes(),
            actorId: $actorId,
            context: ['schedules' => $market->scheduleSnapshot(), ...$context],
        );
    }
}
