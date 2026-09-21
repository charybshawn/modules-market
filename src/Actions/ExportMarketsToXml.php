<?php

namespace Cultpantry\Market\Actions;

use Cultpantry\Market\Models\Market;
use Cultpantry\Market\Models\MarketSchedule;
use Illuminate\Support\Collection;
use SimpleXMLElement;

/**
 * Builds the `<markets><market>...</market></markets>` XML that
 * ImportMarketsFromXml reads back in -- the export side of that action, so
 * a full market list can be pulled off one server and dropped onto
 * another's Import XML form. Every field the import understands is
 * written back out; a null field is omitted entirely rather than written
 * empty, matching the "omit what you didn't find" convention the
 * hand-authored research XML already follows (see
 * references/market-xml-schema.md in the find-bc-markets skill).
 *
 * Known gap: `is_active` has no element in this schema (the import derives
 * it purely from `liveness_score` <= Market::DEACTIVATE_AT_OR_BELOW), so a
 * market that was deactivated by hand despite a higher score round-trips
 * back to active on the target server. Not fixed here since it would mean
 * changing ImportMarketsFromXml's contract too, not just this export.
 */
class ExportMarketsToXml
{
    /**
     * @param  Collection<int, Market>  $markets  expected to already have
     *   `schedules` eager-loaded; not loaded here so a caller exporting a
     *   filtered subset isn't forced to also accept this class's own
     *   default query.
     */
    public function handle(Collection $markets): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><markets></markets>');

        foreach ($markets as $market) {
            $this->appendMarket($xml, $market);
        }

        return $this->prettyPrint($xml);
    }

    private function appendMarket(SimpleXMLElement $xml, Market $market): void
    {
        $node = $xml->addChild('market');

        $this->addChild($node, 'name', $market->name);
        $this->addChild($node, 'city', $market->city);
        $this->addChild($node, 'region', $market->region);
        $this->addChild($node, 'market_type', $market->market_type);
        $this->addChild($node, 'sponsor', $market->sponsor);
        $this->addChild($node, 'address_line1', $market->address_line1);
        $this->addChild($node, 'address_line2', $market->address_line2);
        $this->addChild($node, 'province', $market->province);
        $this->addChild($node, 'postal_code', $market->postal_code);
        $this->addChild($node, 'vendor_fees', $market->vendor_fees);
        $this->addChild($node, 'phone', $market->phone);
        $this->addChild($node, 'manager', $market->manager);
        $this->addChild($node, 'manager_phone', $market->manager_phone);
        $this->addChild($node, 'manager_email', $market->manager_email);
        $this->addChild($node, 'facebook_page', $market->facebook_page);
        $this->addChild($node, 'instagram_page', $market->instagram_page);
        $this->addChild($node, 'website', $market->website);
        $this->addChild($node, 'description', $market->description);
        $this->addChild($node, 'notes', $market->notes);
        $this->addChild($node, 'sources', $market->sources);
        $this->addChild($node, 'liveness_score', $market->liveness_score);
        $this->addChild($node, 'liveness_checked_at', $market->liveness_checked_at?->toDateString());

        if ($market->schedules->isNotEmpty()) {
            $schedulesNode = $node->addChild('schedules');
            foreach ($market->schedules as $schedule) {
                $this->appendSchedule($schedulesNode, $schedule);
            }
        }
    }

    private function appendSchedule(SimpleXMLElement $schedulesNode, MarketSchedule $schedule): void
    {
        $node = $schedulesNode->addChild('schedule');

        $this->addChild($node, 'label', $schedule->label);
        $this->addChild($node, 'frequency', $schedule->frequency);
        $this->addChild($node, 'frequency_detail', $schedule->frequency_detail);
        $this->addChild($node, 'start_date', $schedule->start_date?->toDateString());
        $this->addChild($node, 'end_date', $schedule->end_date?->toDateString());
        $this->addChild($node, 'address_line1', $schedule->address_line1);
        $this->addChild($node, 'notes', $schedule->notes);
        // Required by the import (a schedule without one is skipped), but
        // every row in the database already has one -- the column itself
        // is NOT NULL -- so there's nothing to omit here.
        $this->addChild($node, 'liveness_score', $schedule->liveness_score);
        $this->addChild($node, 'liveness_checked_at', $schedule->liveness_checked_at?->toDateString());
    }

    /**
     * Skips the element entirely for a null value -- addChild() would
     * otherwise write an empty tag, and the import treats a missing element
     * and an empty one the same way, but omitting matches the hand-authored
     * XML convention and keeps a re-exported file's diff-against-research
     * comparisons clean. addChild() itself XML-escapes the value (&, <, >),
     * so a name or note containing "&" round-trips safely without any
     * manual htmlspecialchars() call here.
     */
    private function addChild(SimpleXMLElement $node, string $name, string|int|null $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $node->addChild($name, htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
    }

    /**
     * SimpleXMLElement::asXML() writes everything on one line; reformatted
     * through DOMDocument so a downloaded file is actually readable if
     * someone opens it in a text editor before importing it elsewhere,
     * matching the indentation style of this module's hand-authored
     * research XML.
     */
    private function prettyPrint(SimpleXMLElement $xml): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML((string) $xml->asXML());

        return (string) $dom->saveXML();
    }
}
