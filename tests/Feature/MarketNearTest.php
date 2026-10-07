<?php

use App\Models\User;
use Cultpantry\Market\Actions\ImportMarketsFromXml;
use Cultpantry\Market\Models\Market;
use Cultpantry\Market\Support\Places;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Drive minutes from Salmon Arm, as the routing service would answer; every
// other origin gets these too, which is all these tests need.
const DRIVES = ['Salmon Arm' => 8, 'Sorrento' => 30, 'Vernon' => 49, 'Kelowna' => 99, 'Sun Peaks' => 126];

function fakeRouting(bool $postalCodeFound = true): void
{
    Http::fake([
        'api.openrouteservice.org/v2/matrix/*' => function (Request $request) {
            $locations = $request['locations'];
            $byPoint = [];
            foreach (array_keys(DRIVES) as $town) {
                $byPoint[json_encode([Places::find($town)['lng'], Places::find($town)['lat']])] = $town;
            }
            $durations = $distances = [];
            foreach ($request['sources'] as $s) {
                foreach ($request['destinations'] as $d) {
                    $minutes = DRIVES[$byPoint[json_encode($locations[$d])] ?? 'Kelowna'] ?? 60;
                    $durations[$s][] = $minutes * 60;
                    $distances[$s][] = $minutes * 1.1;
                }
            }

            return Http::response(['durations' => array_values($durations), 'distances' => array_values($distances)]);
        },
        'api.openrouteservice.org/geocode/*' => Http::response(['features' => $postalCodeFound
            ? [['properties' => ['layer' => 'postalcode', 'label' => 'V1E 4N2, Salmon Arm, BC, Canada'], 'geometry' => ['coordinates' => [-119.3209, 50.6534]]]]
            : []]),
    ]);
}

function matrixCalls(): int
{
    return Http::recorded(fn (Request $r) => str_contains($r->url(), '/v2/matrix/'))->count();
}

beforeEach(function () {
    config(['market.openrouteservice.key' => 'test-key', 'market.drive_time_factor' => 1.0]);
    $this->admin = User::factory()->admin()->create();
    foreach (array_keys(DRIVES) as $town) {
        Market::create(['name' => "{$town} Market", 'city' => $town, 'liveness_score' => 4]);
    }
    Market::create(['name' => 'Lost Market', 'city' => 'Nowheresville', 'liveness_score' => 4]);
    Market::create(['name' => 'Nameless Market', 'city' => null, 'liveness_score' => 4]);
});

it('finds markets within a drive time of a town, quickest first, with minutes and km', function () {
    fakeRouting();
    $this->actingAs($this->admin)->get('/admin/market?near=Salmon+Arm&radius=60&sort=drive_minutes')
        ->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('markets.meta.total', 3)
            ->where('markets.data.0.name', 'Salmon Arm Market')->where('markets.data.0.drive_minutes', 0)->where('markets.data.0.drive_km', 0)
            ->where('markets.data.1.name', 'Sorrento Market')->where('markets.data.1.drive_minutes', 30)
            ->where('markets.data.2.name', 'Vernon Market')->where('markets.data.2.drive_minutes', 49)
            ->where('routingProblem', null));
});

it('asks the routing service once and then answers from the cache', function () {
    fakeRouting();
    $url = '/admin/market?near=Salmon+Arm&radius=60';
    $this->actingAs($this->admin)->get($url)->assertOk();
    expect(matrixCalls())->toBe(1);

    $this->actingAs($this->admin)->get($url.'&page=1')->assertOk();
    $this->actingAs($this->admin)->get('/admin/market?near=Salmon+Arm&radius=120')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 4)); // Kelowna (99) now inside
    expect(matrixCalls())->toBe(1);
    expect(DB::table('market_drive_times')->where('origin_key', 'town:salmon arm')->count())->toBeGreaterThan(3);
});

it('finds a full postal code once and remembers it', function () {
    fakeRouting();
    $this->actingAs($this->admin)->get('/admin/market?near=v1e4n2&radius=60')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('routingProblem', null)->where('markets.meta.total', 3));
    $this->actingAs($this->admin)->get('/admin/market?near=V1E+4N2&radius=60')->assertOk();

    $geocodes = Http::recorded(fn (Request $r) => str_contains($r->url(), '/geocode/'))->count();
    expect($geocodes)->toBe(1)->and(matrixCalls())->toBe(1);
    expect(DB::table('market_origins')->where('origin_key', 'postal:V1E4N2')->exists())->toBeTrue();
});

it('says so, and shows the whole list, when it cannot find the starting place', function () {
    fakeRouting(postalCodeFound: false);

    foreach (['Xyzzyville', 'V1E'] as $place) {
        $this->actingAs($this->admin)->get("/admin/market?near={$place}&radius=60")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 7)
                ->where('routingProblem', fn ($m) => str_contains($m, "Couldn't find \"{$place}\"")));
    }
    expect(matrixCalls())->toBe(0);

    // A well-formed postal code the service has no point for.
    $this->actingAs($this->admin)->get('/admin/market?near=V9Z+9Z9&radius=60')
        ->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 7)->where('routingProblem', fn ($m) => str_contains($m, 'V9Z 9Z9')));
});

it('falls back to the unfiltered list when the routing service is unavailable', function () {
    Http::fake(['api.openrouteservice.org/*' => Http::response('boom', 500)]);
    $this->actingAs($this->admin)->get('/admin/market?near=Sorrento&radius=30')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 7)
            ->where('routingProblem', fn ($m) => str_contains($m, "aren't available right now")));

    config(['market.openrouteservice.key' => null]);
    $this->actingAs($this->admin)->get('/admin/market?near=Sorrento&radius=30')
        ->assertInertia(fn (Assert $p) => $p->where('routingProblem', fn ($m) => str_contains($m, "aren't available right now")));
});

it('counts the markets it cannot place and flags them', function () {
    fakeRouting();
    $this->actingAs($this->admin)->get('/admin/market?near=Kelowna&radius=1440')
        ->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 5)->where('unlocated', 2));

    $this->actingAs($this->admin)->get('/admin/market')->assertInertia(fn (Assert $p) => $p
        ->where('unlocated', null)
        ->where('markets.data', function ($rows) {
            $by = collect($rows)->keyBy('name');

            return $by['Kelowna Market']['location_status'] === 'ok'
                && $by['Lost Market']['location_status'] === 'unknown_town'
                && $by['Nameless Market']['location_status'] === 'no_city';
        }));

    $this->actingAs($this->admin)->get('/admin/market/'.Market::where('name', 'Lost Market')->value('id'))
        ->assertInertia(fn (Assert $p) => $p->where('locationStatus', 'unknown_town'));
});

it('follows a change of city for the badge', function () {
    $market = Market::where('name', 'Lost Market')->first();
    expect($market->locationStatus())->toBe('unknown_town');
    $market->update(['city' => 'Vernon']);
    expect($market->fresh()->locationStatus())->toBe('ok');
    $market->update(['city' => null]);
    expect($market->fresh()->locationStatus())->toBe('no_city');
});

it('does not ask for a drive within a market\'s own town', function () {
    fakeRouting();
    $this->actingAs($this->admin)->get('/admin/market?near=Sorrento&radius=5')
        ->assertInertia(fn (Assert $p) => $p->where('markets.meta.total', 1)->where('markets.data.0.name', 'Sorrento Market')->where('markets.data.0.drive_minutes', 0));
    expect(DB::table('market_drive_times')->where('origin_key', 'town:sorrento')->where('destination_key', 'sorrento')->exists())->toBeFalse();
});

it('reports towns missing from the places list after an import', function () {
    $xml = '<?xml version="1.0"?><markets><market><name>Somewhere Market</name><city>Nowheresville</city></market>'
        .'<market><name>Fine Market</name><city>Vernon</city></market></markets>';
    $result = app(ImportMarketsFromXml::class)->handle(UploadedFile::fake()->createWithContent('m.xml', $xml));

    expect($result['created'])->toBe(2)->and($result['unknown_towns'])->toBe(['Nowheresville']);
});

it('builds the PDF from a drive-time search too', function () {
    fakeRouting();
    $this->actingAs($this->admin)->get('/admin/market/export-pdf?status=all&near=Salmon+Arm&radius=60')
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('warms the cache for every pickable town, in few requests, and only where there are gaps', function () {
    fakeRouting();
    $this->artisan('market:warm-drive-times')->assertSuccessful()->expectsOutputToContain('Cached');
    $calls = matrixCalls();
    expect($calls)->toBeGreaterThanOrEqual(1)->and($calls)->toBeLessThan(10);
    expect(DB::table('market_drive_times')->where('origin_key', 'town:sorrento')->count())->toBe(4);

    $this->artisan('market:warm-drive-times')->assertSuccessful()->expectsOutputToContain('already has current');
    expect(matrixCalls())->toBe($calls);
});

it('scales the routing service\'s minutes by the drive-time factor without touching the cache', function () {
    fakeRouting();
    config(['market.drive_time_factor' => 0.8]);

    $this->actingAs($this->admin)->get('/admin/market?near=Salmon+Arm&radius=120&sort=drive_minutes')
        ->assertInertia(fn (Assert $p) => $p->where('markets.data.2.drive_minutes', 39)); // Vernon: 49 x 0.8

    expect((int) DB::table('market_drive_times')->where('destination_key', 'vernon')->value('minutes'))->toBe(49);
});
