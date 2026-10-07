<?php

use Cultpantry\Market\Actions\ImportMarketsFromXml;
use Cultpantry\Market\Models\Market;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('offers one Northern BC in place of the three northern sub-areas', function () {
    expect(Market::REGIONS)->toContain('Northern BC')
        ->not->toContain('Nechako')
        ->not->toContain('North Coast')
        ->not->toContain('Northern Rockies');
});

it('files an XML import of a retired northern region under Northern BC', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?><markets>'
        .'<market><name>Vanderhoof Market</name><city>Vanderhoof</city><region>Nechako</region></market>'
        .'<market><name>Terrace Market</name><city>Terrace</city><region>north coast</region></market>'
        .'<market><name>Fort Nelson Market</name><city>Fort Nelson</city><region>Northern Rockies</region></market>'
        .'<market><name>Prince George Market</name><city>Prince George</city><region>Northern BC</region></market>'
        .'</markets>';
    $path = tempnam(sys_get_temp_dir(), 'mk');
    file_put_contents($path, $xml);

    $result = app(ImportMarketsFromXml::class)->handle(new UploadedFile($path, 'm.xml', 'text/xml', null, true));

    expect($result['region_unmatched'])->toBe(0)
        ->and(Market::pluck('region')->unique()->all())->toBe(['Northern BC']);
});

it('moves stored markets from the retired regions to Northern BC', function () {
    foreach (['Nechako', 'North Coast', 'Northern Rockies', 'Okanagan'] as $i => $region) {
        Market::create(['name' => "Market {$i}", 'region' => $region]);
    }

    (require base_path('vendor/cultpantry/market/database/migrations/2026_09_30_120000_merge_northern_regions_into_northern_bc.php'))->up();

    expect(DB::table('market_markets')->where('region', 'Northern BC')->count())->toBe(3)
        ->and(DB::table('market_markets')->where('region', 'Okanagan')->count())->toBe(1);
});
