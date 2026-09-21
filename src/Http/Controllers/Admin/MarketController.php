<?php

namespace Cultpantry\Market\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Market\Actions\ExportMarketsToPdf;
use Cultpantry\Market\Actions\ExportMarketsToXml;
use Cultpantry\Market\Actions\ImportMarketsFromXml;
use Cultpantry\Market\Contracts\MarketHistory;
use Cultpantry\Market\Events\MarketRecordDeleted;
use Cultpantry\Market\Events\MarketRecordSaved;
use Cultpantry\Market\Models\Market;
use Cultpantry\Market\Models\MarketSchedule;
use Cultpantry\Market\Support\MarketEventPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
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

        $markets = Market::with('schedules')->orderBy('name')->get();

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

        $validated = $this->validated($request);

        $market = DB::transaction(function () use ($validated) {
            $market = Market::create(collect($validated)->except('schedules')->all());
            $this->syncSchedules($market, $validated['schedules'] ?? []);

            return $market;
        });

        event(MarketRecordSaved::forCreated($market, auth()->id()));

        return redirect()
            ->route('admin.market.index')
            ->with('success', "Market '{$market->name}' created.");
    }

    public function show(Market $market): Response
    {
        $this->authorize('view', $market);

        return Inertia::render('Vendor/market/Show', [
            'market' => $market->load('schedules'),
            'cities' => $this->knownValues('city'),
            'regions' => Market::REGIONS,
            'marketTypes' => $this->knownValues('market_type'),
            'frequencies' => Market::FREQUENCIES,
            'livenessLabels' => Market::LIVENESS_LABELS,
            // No History section unless the host has said where a market's
            // history lives (see Contracts\MarketHistory).
            'history' => [
                'enabled' => app()->bound(MarketHistory::class),
                'url' => route('admin.market.events', $market),
                'kinds' => ['created' => 'Created', 'updated' => 'Edited'],
                'sources' => ['admin' => 'Admin form', 'xml_import' => 'XML import'],
                'fields' => MarketEventPresenter::FIELDS,
            ],
        ]);
    }

    /**
     * One page of a market's change history as JSON, for the History section
     * on the view page (and its mobile drawer). Filterable by kind, source and
     * the field that changed; infinite-scrolled with ?page=.
     */
    public function events(Request $request, Market $market): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $market);
        abort_unless(app()->bound(MarketHistory::class), 404);

        $filters = $request->validate([
            'kind' => ['nullable', Rule::in(['created', 'updated'])],
            'source' => ['nullable', Rule::in(['admin', 'xml_import'])],
            'field' => ['nullable', Rule::in(array_keys(MarketEventPresenter::FIELDS))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = app(MarketHistory::class)->forMarket(
            $market,
            collect($filters)->only(['kind', 'source', 'field'])->filter()->all(),
            (int) ($filters['page'] ?? 1),
            15,
        );

        return response()->json([
            'data' => array_map(MarketEventPresenter::present(...), $result['data']),
            'has_more' => $result['has_more'],
        ]);
    }

    public function edit(Market $market): Response
    {
        $this->authorize('update', $market);

        return Inertia::render('Vendor/market/Edit', [
            'market' => $market->load('schedules'),
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

        $validated = $this->validated($request);
        $before = $market->getAttributes();
        $schedulesBefore = $market->scheduleSnapshot();

        DB::transaction(function () use ($market, $validated) {
            $market->update(collect($validated)->except('schedules')->all());
            $this->syncSchedules($market, $validated['schedules'] ?? []);
        });

        // Saving with nothing changed isn't worth an audit row.
        $event = MarketRecordSaved::forUpdated($market, $before, $schedulesBefore, auth()->id());
        if ($event->changes !== []) {
            event($event);
        }

        return redirect()
            ->route('admin.market.show', $market)
            ->with('success', "Market '{$market->name}' updated.");
    }

    /**
     * Saves one field from the market's Show page, in place -- the inline
     * editor there never touches schedules (a repeatable, nested resource
     * that doesn't fit a "click one value, save it" pattern), so this never
     * calls syncSchedules() the way update() does. Fires the same audit
     * event as a full-form save, so History can't tell the two apart except
     * by which fields changed.
     */
    public function updateField(Request $request, Market $market): RedirectResponse
    {
        $this->authorize('update', $market);

        $rules = $this->fieldRules();
        $field = $request->input('field');
        abort_unless(is_string($field) && array_key_exists($field, $rules), 422, 'Not an editable field.');

        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in(array_keys($rules))],
            'value' => $rules[$field],
        ], [], [
            // Otherwise every error reads "The value field is required."
            // regardless of which field was actually being edited.
            'value' => MarketEventPresenter::FIELDS[$field] ?? $field,
        ]);

        $before = $market->getAttributes();
        $schedulesBefore = $market->scheduleSnapshot();

        $market->update([$field => $validated['value']]);

        $event = MarketRecordSaved::forUpdated($market, $before, $schedulesBefore, auth()->id());
        if ($event->changes !== []) {
            event($event);
        }

        return redirect()->route('admin.market.show', $market);
    }

    /**
     * Saves one schedule in place -- the click-to-edit counterpart to
     * updateField() above, for the market Show page's schedule rows (a
     * modal on desktop, a full-screen takeover on mobile, both driven by
     * the same endpoint). Unlike syncSchedules()'s replace-all, this only
     * ever touches the one row: no add/remove here, since those still only
     * make sense on the full Edit form where the whole list is in view at
     * once.
     */
    public function updateSchedule(Request $request, Market $market, MarketSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $market);
        abort_unless($schedule->market_id === $market->id, 404);

        $validated = $request->validate($this->scheduleFieldRules(), [], [
            'liveness_score' => 'liveness score',
            'liveness_checked_at' => 'checked-on date',
        ]);

        $before = $market->getAttributes();
        $schedulesBefore = $market->scheduleSnapshot();

        $schedule->update($validated);

        // Same as update()/updateField(): nothing changed is nothing worth
        // an audit row, even though the schedule row itself was "saved".
        $event = MarketRecordSaved::forUpdated($market, $before, $schedulesBefore, auth()->id());
        if ($event->changes !== []) {
            event($event);
        }

        return redirect()->route('admin.market.show', $market)->with('success', 'Schedule updated.');
    }

    public function destroy(Market $market): RedirectResponse
    {
        $this->authorize('delete', $market);

        $name = $market->name;
        $deletedEvent = MarketRecordDeleted::forModel($market, auth()->id());
        $market->delete();
        event($deletedEvent);

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
        if ($result['unchanged'] > 0) {
            $message .= " {$result['unchanged']} existing market".($result['unchanged'] === 1 ? '' : 's').' matched with no changes -- left alone.';
        }
        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']} row".($result['skipped'] === 1 ? '' : 's')." missing a name.";
        }
        if ($result['schedules'] > 0) {
            $message .= " Imported {$result['schedules']} schedule".($result['schedules'] === 1 ? '' : 's').'.';
        }
        if ($result['schedules_skipped'] > 0) {
            $message .= " Skipped {$result['schedules_skipped']} schedule".($result['schedules_skipped'] === 1 ? '' : 's')." missing a valid liveness score (0-4).";
        }
        if ($result['deactivated'] > 0) {
            $message .= " Marked {$result['deactivated']} market".($result['deactivated'] === 1 ? '' : 's').' inactive (liveness score '.Market::DEACTIVATE_AT_OR_BELOW.' or below).';
        }
        if ($result['region_unmatched'] > 0) {
            $message .= " {$result['region_unmatched']} row".($result['region_unmatched'] === 1 ? '' : 's')." had a region that didn't match the controlled list -- imported without one, set it by hand on that market's Edit page.";
        }

        return redirect()->route('admin.market.index')->with('success', $message);
    }

    /**
     * Downloads every market (active and inactive alike -- this is for
     * migrating the whole table to another server, not a filtered report)
     * as the same XML shape ImportMarketsFromXml reads, so the file this
     * produces can be dropped straight onto another server's Import XML
     * form. A plain GET returning a raw file response rather than an
     * Inertia page, same pattern as InventoryController::generateReport()
     * in the main app. Must be linked with a plain <a href>, not Inertia's
     * <Link> -- confirmed by hand that <Link> intercepts the click, gets a
     * response with no X-Inertia header, and shows a blank in-page overlay
     * instead of letting the browser download the file.
     */
    public function export(ExportMarketsToXml $exportMarketsToXml): \Illuminate\Http\Response
    {
        $this->authorize('export', Market::class);

        $markets = Market::with('schedules')->orderBy('name')->get();
        $xml = $exportMarketsToXml->handle($markets);
        $filename = 'markets-export-'.Carbon::today()->toDateString().'.xml';

        return response($xml)
            ->header('Content-Type', 'text/xml; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Downloads a curated, print-ready PDF of whatever set of markets the
     * caller names -- normally the Index page's *currently filtered* rows
     * (search, status, city/region/type chips, and the market-specific
     * schedule/liveness filters all happen client-side, so the browser is
     * the only place that knows the true current result set; it sends that
     * set's ids here rather than the server trying to re-derive it). Same
     * plain-<a>-not-<Link> requirement as export() above, for the same
     * reason.
     */
    public function exportPdf(Request $request, ExportMarketsToPdf $exportMarketsToPdf): \Illuminate\Http\Response
    {
        $this->authorize('export', Market::class);

        $validated = $request->validate([
            'ids' => ['nullable', 'string'],
        ]);

        $query = Market::with('schedules')->orderBy('name');

        // No ids at all (rather than an empty string) means "export
        // everything" -- e.g. a direct link with no filter state to report,
        // same fallback as export()'s own full-table XML dump.
        if (array_key_exists('ids', $validated) && $validated['ids'] !== null) {
            $ids = array_filter(array_map('trim', explode(',', $validated['ids'])), fn ($id) => ctype_digit($id));
            $query->whereIn('id', $ids);
        }

        $pdf = $exportMarketsToPdf->handle($query->get());
        $filename = 'markets-'.Carbon::today()->toDateString().'.pdf';

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * One rule set, shared by the full-form save (validated(), below) and
     * updateField()'s single-field save -- so a rule can't drift between
     * the two entry points.
     */
    private function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', Rule::in(Market::REGIONS)],
            'market_type' => ['nullable', 'string', 'max:255'],
            'sponsor' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
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
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $scheduleRules = collect($this->scheduleFieldRules())
            ->mapWithKeys(fn (array $rules, string $field) => ["schedules.*.{$field}" => $rules])
            ->all();

        return $request->validate([
            ...$this->fieldRules(),
            'schedules' => ['nullable', 'array'],
            ...$scheduleRules,
        ], [], [
            'schedules.*.liveness_score' => 'liveness score',
            'schedules.*.liveness_checked_at' => 'checked-on date',
        ]);
    }

    /**
     * One rule set per schedule field, shared by the full-form save
     * (validated(), above, prefixed to schedules.*.<field>) and
     * updateSchedule()'s single-row save -- same reasoning as fieldRules()
     * for the market's own fields.
     */
    private function scheduleFieldRules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'frequency' => ['nullable', Rule::in(array_keys(Market::FREQUENCIES))],
            'frequency_detail' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'liveness_score' => ['required', 'integer', 'min:0', 'max:4'],
            'liveness_checked_at' => ['nullable', 'date'],
        ];
    }

    /**
     * The schedules list is submitted whole with the market's own form (no
     * per-schedule endpoints), so replace-all is the simplest correct sync:
     * whatever rows came in are the market's schedules now.
     *
     * @param  array<int, array<string, mixed>>  $schedules
     */
    private function syncSchedules(Market $market, array $schedules): void
    {
        $market->schedules()->delete();

        foreach ($schedules as $schedule) {
            $market->schedules()->create([
                ...$schedule,
                'liveness_checked_at' => $schedule['liveness_checked_at'] ?? Carbon::today(),
            ]);
        }
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
