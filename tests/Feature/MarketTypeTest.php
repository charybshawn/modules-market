<?php

use App\Models\User;
use Cultpantry\Market\Actions\ImportMarketsFromXml;
use Cultpantry\Market\Models\Market;
use Illuminate\Support\Facades\DB;

function typedMarketsXml(array $types): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><markets>'
        .collect($types)->map(fn ($type, $name) => "<market><name>{$name}</name><city>Vernon</city><market_type>{$type}</market_type></market>")->implode('')
        .'</markets>';
}

it('maps old free-text types onto the eight, ignoring case', function () {
    $import = app(ImportMarketsFromXml::class);

    $result = $import->handleString(typedMarketsXml([
        'A' => 'farmers',
        'B' => 'CHRISTMAS MARKET',
        'C' => 'Garlic festival',
        'D' => 'Night market',
    ]));

    expect(Market::orderBy('name')->pluck('market_type')->all())->toBe(["Farmers' market", 'Holiday market', 'Festival market', 'Night market'])
        ->and($result['type_unmatched'])->toBe([]);
});

it('imports an unknown type as blank and names it in the summary', function () {
    $import = app(ImportMarketsFromXml::class);

    $result = $import->handleString(typedMarketsXml(['A' => 'Underwater market']));

    expect(Market::first()->market_type)->toBeNull()
        ->and($result['type_unmatched'])->toBe(['Underwater market'])
        ->and($import->summarize($result))->toContain('Not a market type on the list: Underwater market');
});

it('moves stored types onto the eight and clears ones that match nothing', function () {
    $id = fn (string $type) => DB::table('market_markets')->insertGetId(['name' => $type, 'market_type' => $type, 'liveness_score' => 4, 'created_at' => now(), 'updated_at' => now()]);
    $ids = ['Farmers & Craft' => $id('Farmers & Craft'), 'Seed swap' => $id('Seed swap'), 'Craft fair' => $id('Craft fair'), 'Mystery' => $id('Mystery')];

    (require __DIR__.'/../../database/migrations/2026_10_11_120000_merge_market_types.php')->up();

    expect(DB::table('market_markets')->whereIn('id', $ids)->orderBy('id')->pluck('market_type')->all())
        ->toBe(["Farmers' market", 'Seed & plant sale', 'Craft fair', null]);
});

it('only accepts a listed type when editing a market', function () {
    $market = Market::create(['name' => 'Ross Street Market', 'liveness_score' => 4]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch("/admin/market/{$market->id}/field", ['field' => 'market_type', 'value' => 'Underwater market'])
        ->assertSessionHasErrors('value');
    $this->actingAs($admin)->patch("/admin/market/{$market->id}/field", ['field' => 'market_type', 'value' => 'Craft fair'])
        ->assertSessionHasNoErrors();

    expect($market->fresh()->market_type)->toBe('Craft fair');
});
