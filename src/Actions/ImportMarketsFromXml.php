<?php

namespace Cultpantry\Market\Actions;

use Cultpantry\Market\Events\MarketRecordSaved;
use Cultpantry\Market\Models\Market;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use RuntimeException;
use SimpleXMLElement;

/**
 * Imports a <markets><market>...</market></markets> XML file -- the format
 * produced by the manual/browser-assisted data-gathering workflow this
 * module supports (Facebook pages, individual market websites), not a live
 * scraper. Every field except <name> is optional: that gathering workflow
 * won't always turn up every field for every market, and one missing phone
 * number shouldn't fail the whole row.
 *
 * Matched on (name, city) -- two different markets can share a name in
 * different towns, but not within the same one. Not updateOrCreate(): kept
 * as find-or-new + fill + save so a future caller has the pre-save state
 * available (e.g. for an audit/event hook), matching the shape
 * ImportKitchenRentalsFromCsv in cultpantry/costing already uses for the
 * same reason.
 */
class ImportMarketsFromXml
{
    /**
     * @return array{created: int, updated: int, unchanged: int, skipped: int, region_unmatched: int, schedules: int, schedules_skipped: int, deactivated: int}
     */
    public function handle(UploadedFile $file): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) file_get_contents($file->getRealPath()));

        if ($xml === false) {
            $errors = collect(libxml_get_errors())->pluck('message')->map(trim(...))->implode('; ');
            libxml_clear_errors();
            throw new RuntimeException($errors !== '' ? "Invalid XML: {$errors}" : 'Invalid XML: could not be parsed.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $unchanged = 0;
        $regionUnmatched = 0;
        $scheduleCount = 0;
        $schedulesSkipped = 0;
        $deactivated = 0;

        foreach ($xml->market as $node) {
            $name = $this->text($node, 'name');
            if ($name === null) {
                $skipped++;
                continue;
            }

            $matchOn = [
                'name' => $name,
                'city' => $this->text($node, 'city'),
            ];

            $market = Market::where($matchOn)->first() ?? new Market($matchOn);
            $isNew = ! $market->exists;
            $before = $isNew ? [] : $market->getAttributes();
            $schedulesBefore = $isNew ? [] : $market->scheduleSnapshot();

            $region = $this->region($node);
            if ($region === false) {
                $regionUnmatched++;
                $region = null;
            }

            $livenessScore = $this->livenessScore($node);

            $market->fill([
                ...$matchOn,
                'region' => $region,
                'market_type' => $this->text($node, 'market_type'),
                'sponsor' => $this->text($node, 'sponsor'),
                'address_line1' => $this->text($node, 'address_line1'),
                'address_line2' => $this->text($node, 'address_line2'),
                'province' => $this->text($node, 'province'),
                'postal_code' => $this->text($node, 'postal_code'),
                'vendor_fees' => $this->text($node, 'vendor_fees'),
                'phone' => $this->text($node, 'phone'),
                'manager' => $this->text($node, 'manager'),
                'manager_phone' => $this->text($node, 'manager_phone'),
                'manager_email' => $this->text($node, 'manager_email'),
                'facebook_page' => $this->text($node, 'facebook_page'),
                'instagram_page' => $this->text($node, 'instagram_page'),
                'website' => $this->text($node, 'website'),
                'description' => $this->text($node, 'description'),
                'notes' => $this->text($node, 'notes'),
                'sources' => $this->text($node, 'sources'),
                'liveness_score' => $livenessScore,
                'liveness_checked_at' => $this->livenessCheckedAt($node, $livenessScore),
            ]);

            // A new market counts as active going in: is_active is unset
            // (the column's own default applies) until something sets it.
            $wasActive = $market->exists ? $market->is_active : true;

            $market->save();

            if ($wasActive && $market->is_active === false) {
                $deactivated++;
            }

            $schedules = $this->schedules($node, $schedulesSkipped);
            if ($schedules !== [] && ! $this->schedulesMatch($schedulesBefore, $schedules)) {
                // Replace-all, but only when the entry actually carried
                // valid schedules -- a market re-imported without any
                // shouldn't wipe schedules that were entered by hand -- and
                // only when they actually differ from what's already there,
                // so re-importing the same file repeatedly doesn't churn the
                // schedules table (new row ids, a firehose of "updated"
                // reports) for data that hasn't changed.
                $market->schedules()->delete();
                $market->schedules()->createMany($schedules);
                $scheduleCount += count($schedules);
            }

            $event = $isNew
                ? MarketRecordSaved::forCreated($market, auth()->id(), ['source' => 'xml_import'])
                : MarketRecordSaved::forUpdated($market, $before, $schedulesBefore, auth()->id(), ['source' => 'xml_import']);

            if ($isNew) {
                event($event);
                $created++;
            } elseif ($event->changes !== []) {
                // $event->changes is Eloquent's own post-save getChanges()
                // (plus the schedules diff above) -- the same comparison
                // that decides whether to fire the audit event doubles as
                // the "did this row actually change" check, so a re-import
                // of identical data is recognized and counted separately
                // rather than reported as an update every time.
                event($event);
                $updated++;
            } else {
                $unchanged++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'skipped' => $skipped,
            'region_unmatched' => $regionUnmatched,
            'schedules' => $scheduleCount,
            'schedules_skipped' => $schedulesSkipped,
            'deactivated' => $deactivated,
        ];
    }

    /**
     * Order-insensitive, type-normalized comparison between a market's
     * current schedule snapshot and a freshly-parsed candidate set, so an
     * identical re-import is recognized as a no-op rather than always
     * replacing the rows. liveness_checked_at needs normalizing to a plain
     * date string on both sides -- the snapshot already stores it that way,
     * but a freshly-parsed candidate schedule still carries a Carbon
     * instance at this point (see livenessCheckedAt()).
     *
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $incoming
     */
    private function schedulesMatch(array $before, array $incoming): bool
    {
        $key = function (array $row): string {
            if (($row['liveness_checked_at'] ?? null) instanceof Carbon) {
                $row['liveness_checked_at'] = $row['liveness_checked_at']->toDateString();
            }
            ksort($row);

            return json_encode($row);
        };

        $normalize = fn (array $rows) => collect($rows)->map($key)->sort()->values()->all();

        return $normalize($before) === $normalize($incoming);
    }

    /**
     * Parses <schedules><schedule>...</schedule></schedules>. A schedule
     * without a valid 0-4 <liveness_score> is skipped (and counted), not
     * imported with a guessed one -- the score is required per schedule so
     * "is this specific schedule current?" is always an actual answer.
     *
     * @return array<int, array<string, mixed>>
     */
    private function schedules(SimpleXMLElement $node, int &$skipped): array
    {
        $schedules = [];

        foreach ($node->schedules->schedule ?? [] as $schedule) {
            $score = $this->livenessScore($schedule);
            if ($score === null) {
                $skipped++;
                continue;
            }

            $schedules[] = [
                'label' => $this->text($schedule, 'label'),
                'frequency' => $this->frequency($schedule),
                'frequency_detail' => $this->text($schedule, 'frequency_detail'),
                'start_date' => $this->date($schedule, 'start_date'),
                'end_date' => $this->date($schedule, 'end_date'),
                'address_line1' => $this->text($schedule, 'address_line1'),
                'notes' => $this->text($schedule, 'notes'),
                'liveness_score' => $score,
                'liveness_checked_at' => $this->livenessCheckedAt($schedule, $score),
            ];
        }

        return $schedules;
    }

    /**
     * An unparseable date is treated as absent rather than failing the row,
     * same leniency as liveness_score above.
     */
    private function date(SimpleXMLElement $node, string $child): ?string
    {
        $value = $this->text($node, $child);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Trims whitespace and maps a blank/missing child element to null
     * rather than an empty string, so a market's optional fields land in
     * the database the same way whether they were omitted from the XML
     * entirely or present-but-empty.
     */
    private function text(SimpleXMLElement $node, string $child): ?string
    {
        if (! isset($node->{$child})) {
            return null;
        }

        $value = trim((string) $node->{$child});

        return $value === '' ? null : $value;
    }

    /**
     * Falls back to 'other' for a value outside Market::FREQUENCIES rather
     * than dropping it silently -- the schedule still imports, and
     * frequency_detail (free text) still carries whatever the source XML
     * actually said either way.
     */
    private function frequency(SimpleXMLElement $node): ?string
    {
        $value = $this->text($node, 'frequency');
        if ($value === null) {
            return null;
        }

        return array_key_exists($value, Market::FREQUENCIES) ? $value : 'other';
    }

    /**
     * Region is a controlled list (Market::REGIONS), not free text -- unlike
     * frequency there's no "other" bucket to fall back to (no matching
     * detail field to carry the original text alongside it either), so a
     * value that doesn't match is left unset rather than guessed at, and
     * reported back as region_unmatched so it's visible and someone can
     * pick the right one by hand on that market's Edit page. Matched
     * case-insensitively (and normalized to REGIONS' own casing) since a
     * manually-gathered XML source is more likely to have "okanagan" or
     * "OKANAGAN" than to always match the canonical casing exactly.
     *
     * @return string|null|false string on a match, null if no region was
     *   given at all, false if one was given but didn't match anything.
     */
    private function region(SimpleXMLElement $node): string|null|false
    {
        $value = $this->text($node, 'region');
        if ($value === null) {
            return null;
        }

        foreach (Market::REGIONS as $region) {
            if (strcasecmp($region, $value) === 0) {
                return $region;
            }
        }

        return false;
    }

    /**
     * 0-4 per Market::LIVENESS_LABELS -- an out-of-range or non-numeric
     * value is treated the same as no value at all (null) rather than
     * clamped or rejecting the whole row, since this field is advisory,
     * not something an import should fail over.
     */
    private function livenessScore(SimpleXMLElement $node): ?int
    {
        $value = $this->text($node, 'liveness_score');
        if ($value === null || ! ctype_digit($value)) {
            return null;
        }

        $score = (int) $value;

        return $score >= 0 && $score <= 4 ? $score : null;
    }

    /**
     * Defaults to today when a liveness_score was given but no explicit
     * date -- the import itself is effectively the moment that score was
     * determined, in the normal case of a fresh find-bc-markets research
     * pass being imported right away. An explicit <liveness_checked_at>
     * (e.g. a re-import of older research) always wins over that default.
     * No score at all means no default either -- both stay null together.
     */
    private function livenessCheckedAt(SimpleXMLElement $node, ?int $livenessScore): ?Carbon
    {
        $value = $this->text($node, 'liveness_checked_at');
        if ($value !== null) {
            try {
                return Carbon::parse($value);
            } catch (\Exception) {
                // Falls through to the default-or-null handling below.
            }
        }

        return $livenessScore !== null ? Carbon::today() : null;
    }
}
