<?php

use Cultpantry\Market\Http\Controllers\Admin\MarketController;
use Illuminate\Support\Facades\Route;

// 'web' is REQUIRED here and is not optional. Core routes/web.php gets the
// 'web' middleware group automatically via bootstrap/app.php's
// withRouting(web: ...); routes loaded via loadRoutesFrom() from a
// provider do NOT get it automatically. Omitting it means no session is
// started, so 'auth' silently treats every request as a guest and
// redirects to /login -- even for a logged-in admin. Do not drop 'web'
// from this array.
Route::prefix('admin')->name('admin.')->middleware(['web', 'auth', 'admin'])->group(function () {
    Route::prefix('market')->name('market.')->group(function () {
        Route::get('/', [MarketController::class, 'index'])->name('index');
        Route::get('create', [MarketController::class, 'create'])->name('create');
        Route::post('/', [MarketController::class, 'store'])->name('store');
        Route::post('import', [MarketController::class, 'import'])->name('import');
        Route::get('{market}/edit', [MarketController::class, 'edit'])->name('edit');
        Route::get('{market}', [MarketController::class, 'show'])->name('show');
        Route::put('{market}', [MarketController::class, 'update'])->name('update');
        Route::delete('{market}', [MarketController::class, 'destroy'])->name('destroy');
    });
});
