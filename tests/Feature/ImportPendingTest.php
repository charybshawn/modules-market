<?php

use Cultpantry\Market\Models\ImportRecord;
use Cultpantry\Market\Models\Market;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;

function marketResearchDir(array $files): string
{
    $dir = sys_get_temp_dir().'/market-research-'.uniqid();
    File::ensureDirectoryExists($dir.'/archive');
    foreach ($files as $name => $xml) {
        file_put_contents("{$dir}/{$name}", $xml);
    }
    config(['market.import_path' => $dir]);

    return $dir;
}

function marketXml(string $name, string $type = 'Farmers'): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><markets>'
        ."<market><name>{$name}</name><city>Salmon Arm</city><market_type>{$type}</market_type><liveness_score>4</liveness_score></market>"
        .'</markets>';
}

describe('market:import-pending', function () {
    it('imports pending files in name order, records them, and skips them next time', function () {
        $dir = marketResearchDir([
            '2026-10-11-02-update.xml' => marketXml('Ross Street Market', 'Artisan'),
            '2026-10-11-01-new.xml' => marketXml('Ross Street Market'),
        ]);
        file_put_contents($dir.'/archive/old.xml', marketXml('Archived Market'));

        $this->artisan('market:import-pending')->assertSuccessful();

        expect(Market::where('name', 'Ross Street Market')->value('market_type'))->toBe('Artisan')
            ->and(Market::where('name', 'Archived Market')->exists())->toBeFalse()
            ->and(ImportRecord::orderBy('id')->pluck('filename')->all())->toBe(['2026-10-11-01-new.xml', '2026-10-11-02-update.xml']);

        $this->artisan('market:import-pending')->expectsOutputToContain('No pending research files.')->assertSuccessful();
        expect(ImportRecord::count())->toBe(2);
    });

    it('imports an edited file again', function () {
        $dir = marketResearchDir(['2026-10-11-01-new.xml' => marketXml('Ross Street Market')]);
        $this->artisan('market:import-pending')->assertSuccessful();

        file_put_contents($dir.'/2026-10-11-01-new.xml', marketXml('Ross Street Market', 'Artisan'));
        $this->artisan('market:import-pending')->assertSuccessful();

        expect(Market::where('name', 'Ross Street Market')->value('market_type'))->toBe('Artisan')
            ->and(ImportRecord::count())->toBe(2);
    });

    it('stops at the first failure, rolling that file back and leaving later files pending', function () {
        marketResearchDir([
            '2026-10-11-01-new.xml' => marketXml('Ross Street Market'),
            '2026-10-11-02-broken.xml' => '<not-markets/>',
            '2026-10-11-03-later.xml' => marketXml('Later Market'),
        ]);

        $this->artisan('market:import-pending')
            ->expectsOutputToContain('Stopped: later files were not imported.')
            ->assertFailed();

        expect(ImportRecord::pluck('filename')->all())->toBe(['2026-10-11-01-new.xml'])
            ->and(Market::where('name', 'Later Market')->exists())->toBeFalse();
    });

    it('dry-runs every pending file together, then rolls it all back', function () {
        marketResearchDir([
            '2026-10-11-01-new.xml' => marketXml('Ross Street Market'),
            '2026-10-11-02-update.xml' => marketXml('Ross Street Market', 'Artisan'),
        ]);

        $this->artisan('market:import-pending', ['--dry-run' => true])
            ->expectsOutputToContain('Imported 0 new and updated 1 existing markets.')
            ->assertSuccessful();

        expect(Market::count())->toBe(0)->and(ImportRecord::count())->toBe(0);
    });

    it('marks files as imported without importing them', function () {
        marketResearchDir(['0000-snapshot.xml' => marketXml('Ross Street Market')]);

        $this->artisan('market:import-pending', ['--mark-only' => true])->assertSuccessful();

        expect(Market::count())->toBe(0)
            ->and(ImportRecord::first()->marked_only)->toBeTrue();
        $this->artisan('market:import-pending')->expectsOutputToContain('No pending research files.');
    });

    it('does nothing without a research folder', function () {
        config(['market.import_path' => sys_get_temp_dir().'/no-such-market-dir']);

        $this->artisan('market:import-pending')->assertSuccessful();
    });

    it('is scheduled hourly', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'market:import-pending'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('0 * * * *');
    });
});
