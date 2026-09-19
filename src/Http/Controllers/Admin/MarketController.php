<?php

namespace Cultpantry\Market\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Market\Actions\ImportMarketsFromXml;
use Cultpantry\Market\Models\Market;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarketController extends Controller implements HasMiddleware
{
    /**
     * Three layers of protection, plus a fourth applied here to every
     * action (not just index): the admin Settings -> Modules enable
     * toggle. Putting it in middleware() rather than repeating
     * abort_unless(...) in each method means a disabled module is
     * actually blocked on every route, not just the index page.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless($request->user()?->isAdmin(), 403, 'Admin access required.');
                return $next($request);
            }),
            new Middleware(function ($request, $next) {
                abort_unless(app(GetSiteSetting::class)->handle('modules.cultpantry/market.enabled', true), 404);
                return $next($request);
            }),
        ];
    }

    public function index(): Response
    {
        $this->authorize('viewAny', Market::class);

        $markets = Market::orderBy('name')->get();

        return Inertia::render('Vendor/market/Index', [
            'markets' => $markets,
            'cities' => $this->knownValues('city'),
            'regions' => Market::REGIONS,
            'marketTypes' => $this->knownValues('market_type'),
            'frequencies' => Market::FREQUENCIES,
            'livenessLabels' => Market::LIVENESS_LABELS,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Market::class);

        return Inertia::render('Vendor/market/Create', [
            'cities' => $this->knownValues('city'),
            'regions' => Market::REGIONS,
            'marketTypes' => $this->knownValues('market_type'),
            'frequencies' => Market::FREQUENCIES,
            'livenessLabels' => Market::LIVENESS_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Market::class);

        $market = Market::create($this->validated($request));

        return redirect()
            ->route('admin.market.index')
            ->with('success', "Market '{$market->name}' created.");
    }

    public function edit(Market $market): Response
    {
        $this->authorize('update', $market);

        return Inertia::render('Vendor/market/Edit', [
            'market' => $market,
            'cities' => $this->knownValues('city'),
            'regions' => Market::REGIONS,
            'marketTypes' => $this->knownValues('market_type'),
            'frequencies' => Market::FREQUENCIES,
            'livenessLabels' => Market::LIVENESS_LABELS,
        ]);
    }

    public function update(Request $request, Market $market): RedirectResponse
    {
        $this->authorize('update', $market);

        $market->update($this->validated($request));

        return redirect()
            ->route('admin.market.index')
            ->with('success', "Market '{$market->name}' updated.");
    }

    public function destroy(Market $market): RedirectResponse
    {
        $this->authorize('delete', $market);

        $name = $market->name;
        $market->delete();

        return redirect()
            ->route('admin.market.index')
            ->with('success', "Market '{$name}' deleted.");
    }

    public function import(Request $request, ImportMarketsFromXml $importMarketsFromXml): RedirectResponse
    {
        $this->authorize('import', Market::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xml', 'max:5120'],
        ]);

        try {
            $result = $importMarketsFromXml->handle($validated['file']);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.market.index')
                ->with('error', $e->getMessage());
        }

        $message = "Imported {$result['created']} new and updated {$result['updated']} existing markets.";
        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']} row".($result['skipped'] === 1 ? '' : 's')." missing a name.";
        }
        if ($result['region_unmatched'] > 0) {
            $message .= " {$result['region_unmatched']} row".($result['region_unmatched'] === 1 ? '' : 's')." had a region that didn't match the controlled list -- imported without one, set it by hand on that market's Edit page.";
        }

        return redirect()->route('admin.market.index')->with('success', $message);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', Rule::in(Market::REGIONS)],
            'market_type' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'frequency' => ['nullable', Rule::in(array_keys(Market::FREQUENCIES))],
            'frequency_detail' => ['nullable', 'string'],
            'vendor_fees' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'manager' => ['nullable', 'string', 'max:255'],
            'manager_phone' => ['nullable', 'string', 'max:50'],
            'manager_email' => ['nullable', 'email', 'max:255'],
            'facebook_page' => ['nullable', 'string', 'max:255'],
            'instagram_page' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'sources' => ['nullable', 'string'],
            'liveness_score' => ['nullable', 'integer', 'min:0', 'max:4'],
            'liveness_checked_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * Distinct values already in use for a free-text-with-suggestions
     * column -- offered as autocomplete options rather than a fixed list,
     * matching cultpantry/costing's IngredientController::knownCategories().
     */
    private function knownValues(string $column): Collection
    {
        return Market::whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }
}
