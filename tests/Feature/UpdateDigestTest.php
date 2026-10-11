<?php

use App\Models\User;
use Cultpantry\Market\Actions\BuildUpdateDigest;
use Cultpantry\Market\Actions\ImportMarketsFromXml;
use Cultpantry\Market\Models\Market;
use Cultpantry\Market\Models\UpdateDigest;
use Cultpantry\Market\Notifications\MarketUpdateNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

function digestImport(string $markets): void
{
    app(ImportMarketsFromXml::class)->handleString('<?xml version="1.0" encoding="UTF-8"?><markets>'.$markets.'</markets>');
}

function digestMarket(string $name, string $schedules = '', string $extra = ''): string
{
    return "<market><name>{$name}</name><city>Vernon</city><market_type>Artisan market</market_type><liveness_score>4</liveness_score>{$extra}"
        .($schedules !== '' ? "<schedules>{$schedules}</schedules>" : '').'</market>';
}

function digestOneTime(string $label, string $start, ?string $end = null, string $checked = '2026-10-11'): string
{
    return "<schedule><label>{$label}</label><frequency>one_time</frequency><start_date>{$start}</start_date><end_date>".($end ?? $start)
        ."</end_date><liveness_score>3</liveness_score><liveness_checked_at>{$checked}</liveness_checked_at></schedule>";
}

function digestSaturdays(string $checked = '2026-10-11'): string
{
    return "<schedule><label>Saturday Market</label><frequency>weekly</frequency><weekdays>sat</weekdays><start_time>09:00</start_time><end_time>13:00</end_time><liveness_score>4</liveness_score><liveness_checked_at>{$checked}</liveness_checked_at></schedule>";
}

beforeEach(fn () => $this->travelTo('2026-10-11 12:00:00'));

describe('Update digest', function () {
    $digest = fn ($since) => app(BuildUpdateDigest::class)->handle($since, now()->addMinute());

    it('lists new markets soonest first, with their next date', function () use ($digest) {
        $since = now()->subMinute();
        digestImport(
            digestMarket('Undated Market')
            .digestMarket('Weekly Market', digestSaturdays())
            .digestMarket('Holiday Market', digestOneTime('Holiday Market 2026', '2026-11-28', '2026-11-29'))
        );

        $d = $digest($since);

        expect($d['markets'])->toBe(3)
            ->and(collect($d['new'])->pluck('name')->all())->toBe(['Weekly Market', 'Holiday Market', 'Undated Market'])
            ->and($d['new'][0]['next'])->toBe('Saturday Market, next Sat, Oct 17')
            ->and($d['new'][1]['next'])->toBe('Holiday Market 2026, Sat, Nov 28 to Sun, Nov 29')
            ->and($d['new'][2]['next'])->toBeNull()
            ->and($d['new'][0]['url'])->toBe(route('admin.market.show', Market::where('name', 'Weekly Market')->first()));
    });

    it('reports a new event on a market already on file, but not its unchanged schedules re-imported', function () use ($digest) {
        digestImport(digestMarket('Polson Market', digestSaturdays('2026-09-28')));
        $this->travel(1)->days();
        $since = now()->subMinute();

        // A re-check: the Saturday market is unchanged (only its checked date moves), plus a new event and a past one.
        digestImport(digestMarket('Polson Market', digestSaturdays('2026-10-12')
            .digestOneTime('Sleigh All Day 2026', '2026-11-28', '2026-11-29', '2026-10-12')
            .digestOneTime('Already Happened', '2026-09-12', null, '2026-10-12')));

        $d = $digest($since);

        expect($d['markets'])->toBe(0)
            ->and($d['dates'])->toHaveCount(1)
            ->and($d['dates'][0]['name'])->toBe('Polson Market')
            ->and($d['dates'][0]['items'])->toBe(['Sleigh All Day 2026, Sat, Nov 28 to Sun, Nov 29']);
    });

    it('leaves out ignored and inactive markets', function () use ($digest) {
        $since = now()->subMinute();
        digestImport(digestMarket('Gone Market', digestSaturdays(), '').digestMarket('Ignored Market', digestSaturdays()));
        Market::where('name', 'Gone Market')->first()->forceFill(['liveness_score' => 1])->save();
        Market::where('name', 'Ignored Market')->first()->forceFill(['ignored_at' => now()])->save();

        expect($digest($since)['total'])->toBe(0);
    });
});

describe('market:notify-updates', function () {
    $user = fn (array $attributes) => User::factory()->create(['is_active' => true, 'email_verified_at' => now(), ...$attributes]);

    it('records a baseline on its first run, then emails section users one digest of what is new', function () use ($user) {
        Notification::fake();
        $admin = $user(['role' => 'admin', 'admin_permissions' => null]);
        $granted = $user(['role' => 'customer', 'admin_permissions' => ['farmers-markets']]);
        $elsewhere = $user(['role' => 'customer', 'admin_permissions' => ['elections']]);

        $this->artisan('market:notify-updates')->expectsOutputToContain('First run')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(5)->minutes();
        digestImport(digestMarket('Strange Bazaar', digestOneTime('Season of the Witch 2026', '2026-10-17')));
        $this->travel(5)->minutes();

        $this->artisan('market:notify-updates')->assertSuccessful();

        Notification::assertSentTo([$admin, $granted], MarketUpdateNotification::class, function (MarketUpdateNotification $n, array $channels, object $notifiable) {
            $mail = $n->toMail($notifiable);

            return $mail->subject === "Farmer's Markets: 1 new market"
                && str_contains(implode("\n", $mail->introLines), 'Season of the Witch 2026, Sat, Oct 17');
        });
        Notification::assertNotSentTo($elsewhere, MarketUpdateNotification::class);
        expect(UpdateDigest::count())->toBe(2)
            ->and(UpdateDigest::latest('id')->first()->counts)->toBe(['markets' => 1, 'dated_markets' => 0]);

        $this->travel(5)->minutes();
        $this->artisan('market:notify-updates')->expectsOutputToContain('Nothing new.')->assertSuccessful();
    });

    it('sends and records nothing on a dry run', function () use ($user) {
        Notification::fake();
        $user(['role' => 'admin', 'admin_permissions' => null]);
        UpdateDigest::create(['covers_through' => now()->subHour(), 'recipients' => 0]);
        digestImport(digestMarket('Strange Bazaar', digestOneTime('Season of the Witch 2026', '2026-10-17')));

        $this->artisan('market:notify-updates', ['--dry-run' => true])->expectsOutputToContain('would email')->assertSuccessful();

        Notification::assertNothingSent();
        expect(UpdateDigest::count())->toBe(1);
    });

    it('is scheduled hourly, after the import', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'market:notify-updates'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('10 * * * *');
    });
});
