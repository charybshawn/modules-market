<?php

namespace Cultpantry\Market\Actions;

use Cultpantry\Market\Models\Market;
use Illuminate\Http\UploadedFile;
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
     * @return array{created: int, updated: int, skipped: int}
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

            $market->fill([
                ...$matchOn,
                'region' => $this->text($node, 'region'),
                'market_type' => $this->text($node, 'market_type'),
                'address' => $this->text($node, 'address'),
                'frequency' => $this->frequency($node),
                'frequency_detail' => $this->text($node, 'frequency_detail'),
                'vendor_fees' => $this->text($node, 'vendor_fees'),
                'phone' => $this->text($node, 'phone'),
                'manager' => $this->text($node, 'manager'),
                'manager_email' => $this->text($node, 'manager_email'),
                'facebook_page' => $this->text($node, 'facebook_page'),
                'instagram_page' => $this->text($node, 'instagram_page'),
                'website' => $this->text($node, 'website'),
                'description' => $this->text($node, 'description'),
                'notes' => $this->text($node, 'notes'),
                'sources' => $this->text($node, 'sources'),
            ]);

            $market->save();

            $isNew ? $created++ : $updated++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
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
     * than dropping it silently -- the row still imports, and
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
}
