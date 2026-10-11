<?php

use App\Models\User;
use Cultpantry\Market\Actions\FetchMarkets;
use Cultpantry\Market\Actions\ImportMarketsFromXml;
use Cultpantry\Market\Models\Market;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo('2026-10-11 12:00:00'));

function farmersMarketWithChristmas(string $start, string $end): Market
{
    $market = Market::create(['name' => 'Ross Street Market', 'market_type' => "Farmers' market", 'liveness_score' => 4]);
    $market->schedules()->create(['label' => 'Summer Market', 'frequency' => 'weekly', 'weekdays' => [6], 'liveness_score' => 4, 'liveness_checked_at' => now()]);
    $market->schedules()->create([
        'label' => 'Christmas Market', 'market_type' => 'Holiday market', 'frequency' => 'one_time',
        'start_date' => $start, 'end_date' => $end, 'liveness_score' => 4, 'liveness_checked_at' => now(),
    ]);

    return $market;
}

it('finds a farmers market under Holiday market while its Christmas run is still to come', function () {
    farmersMarketWithChristmas('2026-12-05', '2026-12-06');
    $count = fn (string $type) => app(FetchMarkets::class)->handle(['market_type' => [$type], 'status' => 'active,inactive'])->total();

    expect($count('Holiday market'))->toBe(1)->and($count("Farmers' market"))->toBe(1)->and($count('Craft fair'))->toBe(0);

    $this->travelTo('2026-12-07 12:00:00');

    expect($count('Holiday market'))->toBe(0)->and($count("Farmers' market"))->toBe(1);
});

it('counts the market under both types on the dashboard', function () {
    farmersMarketWithChristmas('2026-12-05', '2026-12-06');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/market/dashboard')->assertInertia(fn (Assert $p) => $p
        ->where('types', fn ($types) => collect($types)->firstWhere('market_type', 'Holiday market')['total'] === 1
            && collect($types)->firstWhere('market_type', "Farmers' market")['total'] === 1)
        ->where('counts.total', 1)
    );
});

it('types holiday-labelled schedules on non-holiday markets, and leaves winter seasons alone', function () {
    $migration = require __DIR__.'/../../database/migrations/2026_10_11_130000_add_market_type_to_market_schedules_table.php';
    $migration->down();

    $market = fn (string $name, string $type) => DB::table('market_markets')->insertGetId(['name' => $name, 'market_type' => $type, 'liveness_score' => 4, 'created_at' => now(), 'updated_at' => now()]);
    $schedule = fn (int $marketId, string $label) => DB::table('market_schedules')->insertGetId(['market_id' => $marketId, 'label' => $label, 'liveness_score' => 4, 'liveness_checked_at' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
    $farmers = $market('Farmers', "Farmers' market");
    $holiday = $market('Holiday', 'Holiday market');
    $ids = [
        $schedule($farmers, 'Annual Christmas Market'),
        $schedule($farmers, 'Winter Market'),
        $schedule($farmers, 'Holiday Craft Fair'),
        $schedule($holiday, 'Christmas Market 2026'),
    ];

    $migration->up();

    expect(DB::table('market_schedules')->whereIn('id', $ids)->orderBy('id')->pluck('market_type')->all())
        ->toBe(['Holiday market', null, 'Holiday market', null]);
});

it('imports a schedule type through the same mapping, and reports an unknown one', function () {
    $import = app(ImportMarketsFromXml::class);
    $schedule = fn (string $label, string $type) => "<schedule><label>{$label}</label><market_type>{$type}</market_type><frequency>one_time</frequency><start_date>2026-12-05</start_date><liveness_score>3</liveness_score></schedule>";

    $result = $import->handleString('<?xml version="1.0" encoding="UTF-8"?><markets><market><name>Ross Street Market</name><market_type>Farmers</market_type><schedules>'
        .$schedule('Christmas Market', 'christmas market').$schedule('Mystery Night', 'Underwater market')
        .'</schedules></market></markets>');

    expect(Market::first()->schedules()->orderBy('id')->pluck('market_type')->all())->toBe(['Holiday market', null])
        ->and($result['type_unmatched'])->toBe(['Underwater market']);
});

it('only accepts a listed type when editing a schedule', function () {
    $market = farmersMarketWithChristmas('2026-12-05', '2026-12-06');
    $schedule = $market->schedules()->where('label', 'Summer Market')->first();
    $admin = User::factory()->admin()->create();
    $save = fn (?string $type) => $this->actingAs($admin)->patch("/admin/market/{$market->id}/schedules/{$schedule->id}", [
        'label' => 'Summer Market', 'market_type' => $type, 'frequency' => 'weekly', 'weekdays' => [6], 'liveness_score' => 4,
    ]);

    $save('Underwater market')->assertSessionHasErrors('market_type');
    $save('Night market')->assertSessionHasNoErrors();

    expect($schedule->fresh()->market_type)->toBe('Night market');
});
