<?php

namespace Cultpantry\Market\Actions;

use Barryvdh\DomPDF\Facade\Pdf;
use Cultpantry\Market\Models\Market;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Renders a curated, print-ready PDF for a set of markets -- deliberately
 * not a dump of every admin field. Deciding what to leave out is most of
 * the design here: liveness score, checked-on date, notes, sources,
 * manager contact and vendor fees are all internal research/admin
 * metadata with no audience beyond this module's own users, so none of it
 * appears. What's left (name, type, sponsor, description, schedule,
 * public-facing contact) is what an actual market-goer or vendor would
 * want on a printed page.
 */
class ExportMarketsToPdf
{
    /**
     * @param  Collection<int, Market>  $markets  expected to already have
     *   `schedules` eager-loaded, same contract as ExportMarketsToXml.
     */
    public function handle(Collection $markets): string
    {
        // Laravel's multi-column sortBy([[col, dir], ...]) array syntax only
        // accepts column-name strings, not callables -- passing a closure
        // there silently sorts by nothing. A single closure returning a
        // composite "region|name" string is the supported way to sort on a
        // derived value (the null-region fallback) plus a real column.
        $regionLabel = fn (Market $market) => $market->region ?? 'Other';

        $marketsByRegion = $markets
            ->sortBy(fn (Market $market) => $regionLabel($market).'|'.$market->name)
            ->groupBy($regionLabel);

        $pdf = Pdf::loadView('market::pdf.markets', [
            'marketsByRegion' => $marketsByRegion,
            'markets' => $markets,
            'generatedAt' => Carbon::now(),
            'frequencyLabels' => Market::FREQUENCIES,
        ])->setPaper('letter');

        return $pdf->output();
    }
}
