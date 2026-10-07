<?php

use Illuminate\Support\Facades\Route;

/*
 * Every action must be auditable. Each route this module registers that
 * changes data dispatches MarketRecordSaved / MarketRecordDeleted, which the host writes to its Event log. A new write route fails here until it does and
 * is added below.
 */
it('audits every route in this module that changes data', function () {
    $audited = [
        'DELETE admin/market/{market}',
        'PATCH admin/market/{market}/field',
        'PATCH admin/market/{market}/ignore',
        'PATCH admin/market/{market}/schedules/{schedule}',
        'POST admin/market',
        'POST admin/market/import',
        'PUT admin/market/{market}',
    ];

    $writeRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getActionName(), 'Cultpantry\\Market\\'))
        ->flatMap(fn ($r) => collect($r->methods())
            ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
            ->map(fn ($m) => "{$m} {$r->uri()}"))
        ->unique()->sort()->values();

    expect($writeRoutes->diff($audited)->values()->all())->toBe([])
        ->and(collect($audited)->diff($writeRoutes)->values()->all())->toBe([]);
});
