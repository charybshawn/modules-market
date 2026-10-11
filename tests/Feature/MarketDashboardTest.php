<?php

use App\Models\User;
use App\Support\AdminNav;
use Cultpantry\Market\Models\Market;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('counts markets on file and per region, leaving ignored markets out', function () {
    Market::create(['name' => 'A', 'region' => 'Shuswap', 'liveness_score' => 4]);
    Market::create(['name' => 'B', 'region' => 'Shuswap', 'liveness_score' => 1]); // deactivated
    Market::create(['name' => 'C', 'region' => 'Okanagan', 'liveness_score' => 4]);
    Market::create(['name' => 'D', 'liveness_score' => 4]); // no region
    Market::create(['name' => 'E', 'region' => 'Okanagan', 'liveness_score' => 4])->forceFill(['ignored_at' => now()])->save();

    $this->actingAs($this->admin)->get('/admin/market/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Vendor/market/Dashboard')
        ->where('counts', ['total' => 4, 'active' => 3, 'ignored' => 1])
        ->has('regions', count(Market::REGIONS))
        ->where('regions.0', ['region' => 'Shuswap', 'total' => 2, 'active' => 1])
        ->where('regions.1', ['region' => 'Okanagan', 'total' => 1, 'active' => 1])
        ->where('regions.2.total', 0)
        ->where('unassigned', ['total' => 1, 'active' => 1])
    );
});

it('counts markets per type, busiest first, leaving ignored markets out', function () {
    Market::create(['name' => 'A', 'market_type' => 'Craft fair', 'liveness_score' => 4]);
    Market::create(['name' => 'B', 'market_type' => 'Craft fair', 'liveness_score' => 1]); // deactivated
    Market::create(['name' => 'C', 'market_type' => "Farmers' market", 'liveness_score' => 4]);
    Market::create(['name' => 'D', 'liveness_score' => 4]); // no type
    Market::create(['name' => 'E', 'market_type' => "Farmers' market", 'liveness_score' => 4])->forceFill(['ignored_at' => now()])->save();

    $this->actingAs($this->admin)->get('/admin/market/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->has('types', count(Market::MARKET_TYPES))
        ->where('types.0', ['market_type' => 'Craft fair', 'total' => 2, 'active' => 1])
        ->where('types.1', ['market_type' => "Farmers' market", 'total' => 1, 'active' => 1])
        ->where('types.2.total', 0)
        ->where('untyped', ['total' => 1, 'active' => 1])
    );
});

it('shows what was added, a day at a time, newest first', function () {
    $this->travelTo(now()->subDays(3));
    $older = Market::create(['name' => 'Ross Street Market', 'city' => 'Salmon Arm', 'liveness_score' => 4]);
    $this->travelBack();

    $this->travelTo(now()->subDay());
    Market::create(['name' => 'Kelowna Farmers', 'city' => 'Kelowna', 'liveness_score' => 4]);
    Market::create(['name' => 'Quiet Market', 'city' => 'Vernon', 'liveness_score' => 4])->forceFill(['ignored_at' => now()])->save();
    $this->travelBack();

    // A new date on a market that was already on file.
    $older->schedules()->create([
        'label' => 'Christmas Market', 'frequency' => 'one_time',
        'start_date' => now()->addDays(20)->toDateString(), 'end_date' => null, 'liveness_score' => 4, 'liveness_checked_at' => now(),
    ]);

    $this->actingAs($this->admin)->get('/admin/market/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('updates', 3)
        ->where('updates.0.date', now()->toDateString())
        ->where('updates.0.markets', 0)
        ->where('updates.0.dates.0.name', 'Ross Street Market')
        ->where('updates.0.dates.0.items.0', fn ($item) => str_starts_with($item, 'Christmas Market, '))
        ->where('updates.1.markets', 1)
        ->where('updates.1.new.0.name', 'Kelowna Farmers')
        ->where('updates.1.new.0.city', 'Kelowna')
        ->where('updates.2.new.0.name', 'Ross Street Market')
        ->missing('imports')
    );
});

it('opens from the sidebar link', function () {
    $entry = collect(AdminNav::all())->firstWhere('key', 'farmers-markets');

    expect($entry['href'])->toBe('/admin/market/dashboard');
});
