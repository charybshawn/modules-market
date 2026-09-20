<?php

namespace Cultpantry\Market\Contracts;

use Cultpantry\Market\Models\Market;

/**
 * Where the module reads a market's change history from. The module fires
 * MarketRecordSaved / MarketRecordDeleted and knows nothing about who stores
 * them, so it can't query a host's audit log itself -- the host binds this to
 * whatever it recorded them into (cultpantry's Event log, via
 * App\Market\EventLogMarketHistory). If nothing is bound, the market page just
 * has no History section.
 */
interface MarketHistory
{
    /**
     * Newest first, one page at a time.
     *
     * @param  array{kind?: string|null, source?: string|null, field?: string|null}  $filters
     *     kind: 'created' | 'updated'. source: 'admin' | 'xml_import'.
     *     field: only edits that changed this attribute (or 'schedules').
     * @return array{data: array<int, array{id: int, kind: string, severity: string, actor: ?string, created_at: \DateTimeInterface, metadata: array<string, mixed>}>, has_more: bool}
     *     Each row's metadata is exactly what was dispatched: 'changes' (see
     *     MarketRecordSaved) plus any context such as 'source'.
     */
    public function forMarket(Market $market, array $filters, int $page, int $perPage): array;
}
