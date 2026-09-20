<?php

namespace Cultpantry\Market\Events;

use Cultpantry\Market\Models\Market;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a market is created or updated -- this module's only "hook" for
 * audit logging, mirroring cultpantry/costing's CostingRecordSaved. Dispatched
 * with the plain event() helper, so it costs nothing when nothing listens; a
 * host app opts in by registering a listener, and the module never needs to
 * know one exists.
 *
 * A market's schedules are edited inside the market's own form and replaced
 * wholesale on save, so they travel in the same event under a 'schedules' key
 * rather than as separate per-schedule events.
 */
final class MarketRecordSaved
{
    use Dispatchable;

    /**
     * @param  string  $action  'created' | 'updated'
     * @param  array<string, mixed>  $changes  created: full attribute snapshot
     *     (plus 'schedules' when it has any). updated: [attribute => ['old' =>
     *     mixed, 'new' => mixed]] for changed attributes only, plus
     *     'schedules' => ['old' => [...], 'new' => [...]] if they changed.
     * @param  array<string, mixed>  $context  Free-form tagging, e.g.
     *     ['source' => 'xml_import'].
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly int|string $modelId,
        public readonly string $action,
        public readonly string $label,
        public readonly array $changes,
        public readonly ?int $actorId,
        public readonly array $context = [],
    ) {}

    /**
     * Call after the market (and its schedules) are saved.
     */
    public static function forCreated(Market $market, ?int $actorId, array $context = []): self
    {
        // Re-read so column defaults the insert didn't set (is_active) are in
        // the snapshot rather than missing.
        $changes = ($market->fresh() ?? $market)->getAttributes();

        $schedules = $market->scheduleSnapshot();
        if ($schedules !== []) {
            $changes['schedules'] = $schedules;
        }

        return new self(
            modelClass: $market::class,
            modelId: $market->getKey(),
            action: 'created',
            label: $market->name,
            changes: $changes,
            actorId: $actorId,
            context: $context,
        );
    }

    /**
     * Call *after* saving, passing what the market and its schedules looked
     * like *before* the edit ($market->getAttributes() and
     * $market->scheduleSnapshot(), captured before fill()). The diff comes from
     * getChanges() rather than the pre-save dirty set, so a change made inside
     * the model's own saving hook -- a liveness score of 1 or below switching
     * is_active off -- is recorded too.
     *
     * @param  array<string, mixed>  $before
     * @param  array<int, array<string, mixed>>  $schedulesBefore
     */
    public static function forUpdated(Market $market, array $before, array $schedulesBefore, ?int $actorId, array $context = []): self
    {
        $changes = [];
        foreach ($market->getChanges() as $key => $new) {
            if ($key === 'updated_at') {
                continue;
            }
            $old = $before[$key] ?? null;
            // The raw original is 1/0 from the database while the new value
            // may already be a real bool (e.g. set by the saving hook); show
            // booleans as booleans on both sides.
            if ($market->hasCast($key, ['bool', 'boolean'])) {
                $old = $old === null ? null : (bool) $old;
                $new = (bool) $new;
            }
            $changes[$key] = ['old' => $old, 'new' => $new];
        }

        $schedulesAfter = $market->scheduleSnapshot();
        if ($schedulesBefore !== $schedulesAfter) {
            $changes['schedules'] = ['old' => $schedulesBefore, 'new' => $schedulesAfter];
        }

        return new self(
            modelClass: $market::class,
            modelId: $market->getKey(),
            action: 'updated',
            label: $market->name,
            changes: $changes,
            actorId: $actorId,
            context: $context,
        );
    }
}
