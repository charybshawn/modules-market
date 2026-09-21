<?php

namespace Cultpantry\Market;

use App\Support\AdminNav;
use Cultpantry\Market\Models\Market;
use Cultpantry\Market\Policies\MarketPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MarketServiceProvider extends ServiceProvider
{
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

        // 3. Nav entry. 'match' covers every market/* sub-page so the
        //    sidebar entry stays highlighted across Index/Create/Edit.
        AdminNav::register([
            'name' => "Farmer's Markets",
            'href' => '/admin/market',
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
