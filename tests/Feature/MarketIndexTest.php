<?php

use App\Models\User;
use Cultpantry\Market\Models\Market;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    foreach (range(1, 30) as $i) {
        $market = Market::create([
            'name' => sprintf('Market %02d', $i),
            'city' => $i % 2 ? 'Kelowna' : 'Salmon Arm',
            'region' => $i % 2 ? 'Okanagan' : 'Shuswap',
            'liveness_score' => $i % 5,
        ]);
        $market->schedules()->create([
            'label' => 'S', 'frequency' => $i % 3 ? 'weekly' : 'one_time',
            'start_date' => $i % 3 ? null : '2026-10-1'.($i % 9), 'end_date' => null, 'liveness_score' => 3, 'liveness_checked_at' => now(),
        ]);
    }
});

it('pages the market list, defaults to Active, and echoes the filters', function () {
    foreach (range(31, 60) as $i) {
        Market::create(['name' => "Market {$i}", 'liveness_score' => 4]);
    }
    $active = Market::where('is_active', true)->count();
    $inactive = Market::where('is_active', false)->count();
    expect($inactive)->toBeGreaterThan(0)->and($active + $inactive)->toBe(60);

    $this->actingAs($this->admin)->get('/admin/market')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Vendor/market/Index')
        ->has('markets.data', 25)
        ->where('markets.meta.total', $active)
        ->where('markets.meta.last_page', 2)
        ->where('counts.total', 60)->where('counts.active', $active)
        ->has('markets.data.0', fn (Assert $m) => $m->hasAll(['id', 'name', 'schedules', 'matched_schedule_ids', 'is_active'])->etc()));

    $this->actingAs($this->admin)->get('/admin/market?page=2')->assertInertia(fn (Assert $p) => $p
        ->has('markets.data', $active - 25)->where('markets.meta.current_page', 2));

    $this->actingAs($this->admin)->get('/admin/market?status=all')->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 60));
    $this->actingAs($this->admin)->get('/admin/market?status=inactive')->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', $inactive));
});

it('applies the chips, search, sort, schedule and liveness filters on the server', function () {
    $get = fn (string $qs) => $this->actingAs($this->admin)->get('/admin/market?status=all&'.$qs);

    $get('region[]=Shuswap')->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 15));
    $get('city[]=Kelowna&city[]=Salmon+Arm')->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 30));
    $get('search=Market+07')->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 1)->where('markets.data.0.name', 'Market 07'));
    $get('sort=name&direction=desc')->assertInertia(fn (Assert $p) => $p->where('markets.data.0.name', 'Market 30'));
    $get('freq_include[]=one_time')->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 10)->has('markets.data.0.matched_schedule_ids', 1));
    $get('freq_exclude[]=weekly')->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 10)->where('markets.data.0.matched_schedule_ids', []));
    $get('months[]=10')->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 10));
    $get('liveness_min=3&liveness_max=4')->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 12));
    $get('sort=bogus')->assertSessionHasErrors('sort');
});

it('builds the market PDF from the same filters as the list', function () {
    $this->actingAs($this->admin)->get('/admin/market/export-pdf?status=all&region[]=Shuswap')
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->admin)->get('/admin/market/export-pdf')->assertOk();
});
