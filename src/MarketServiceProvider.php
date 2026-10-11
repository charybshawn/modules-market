<?php

namespace Cultpantry\Market;

use App\Support\AdminNav;
use Cultpantry\Market\Console\ImportPendingCommand;
use Cultpantry\Market\Console\NotifyUpdatesCommand;
use Cultpantry\Market\Console\WarmDriveTimes;
use Cultpantry\Market\Models\Market;
use Cultpantry\Market\Policies\MarketPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MarketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Settings for the routing service (the key is read from ORS_API_KEY).
        $this->mergeConfigFrom(__DIR__.'/../config/market.php', 'market');
    }

    public function boot(): void
    {
        // 1. Admin routes -- additive merge into the existing admin route
        //    group, since prefix/name/middleware are identical.
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');

        // 2. Migrations for this module's own table (market_markets).
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 2b. Blade views (currently just the PDF export template),
        //     namespaced 'market::' so `market::pdf.markets` resolves
        //     without publishing -- unlike the Vue pages below, a
        //     server-rendered Blade view doesn't need to live inside the
        //     host app's own resource tree to be found.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'market');

        // 2c. `php artisan market:warm-drive-times`, and
        //     `market:import-pending`, which loads the research files committed
        //     to the host app's database/markets/ folder that aren't in the
        //     ledger yet; it runs from the deploy script and hourly as a
        //     safety net. `market:notify-updates` then emails users with
        //     Farmer's Markets access a digest of what's new since its last run
        //     (first run only sets a baseline).
        if ($this->app->runningInConsole()) {
            $this->commands([WarmDriveTimes::class, ImportPendingCommand::class, NotifyUpdatesCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('market:import-pending')->hourly()->withoutOverlapping();
            $schedule->command('market:notify-updates')->hourlyAt(10)->withoutOverlapping();
        });

        // 3. Nav entry. It opens the dashboard; 'match' covers every
        //    market/* sub-page so the entry stays highlighted across them.
        AdminNav::register([
            'name' => "Farmer's Markets",
            'href' => '/admin/market/dashboard',
            'icon' => 'market',
            'match' => '/admin/market',
            'module' => 'cultpantry/market',
        ]);

        // 4. Policy -- explicit registration. Laravel's naming-convention
        //    auto-discovery only scans App\Models -> App\Policies, not
        //    module namespaces.
        Gate::policy(Market::class, MarketPolicy::class);

        // 5. Publish the module's raw Vue source into
        //    resources/js/Pages/Vendor/market/ -- inside the SAME root the
        //    core Inertia glob and Inertia's testing view-finder already
        //    scan. Run via:
        //    php artisan vendor:publish --tag=market-pages
        $this->publishes([
            __DIR__.'/../resources/js/Pages' => resource_path('js/Pages/Vendor/market'),
        ], 'market-pages');
    }
}
