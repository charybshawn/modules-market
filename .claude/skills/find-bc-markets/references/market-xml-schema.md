# Market XML schema

Source of truth for everything on this page: `Cultpantry\Market\Models\Market` and
`Cultpantry\Market\Actions\ImportMarketsFromXml` in
`/Users/shawn/Documents/code/modules-market/src/`. If the Laravel side ever
changes these lists, this file needs to change with it — check that repo if
something here looks stale.

## Controlled `region` values

Case-insensitive on import (normalized to this exact casing), but never invent
a value outside this list. If a market's locality doesn't clearly match one of
these, leave `<region>` out entirely rather than guessing — the import reports
unmatched regions back so a human picks the right one later, but it's cleaner
to just not claim a fit you're not sure of.

```
Boundary
Cariboo Chilcotin Coast
Fraser Valley
Kootenay Rockies
Metro Vancouver
Nechako
North Coast
Northern Rockies
Okanagan
Sea to Sky
Shuswap
Similkameen
Sunshine Coast
Thompson
Thompson Okanagan
Vancouver Island
```

These are a blend of Destination BC's 6 official tourism regions and
well-known named sub-areas (Okanagan, Shuswap, Similkameen, Thompson) split
out separately, since BC markets get referred to by the narrower local name
far more often than the broad region they technically sit inside. "Shuswap"
and "Thompson Okanagan" are both valid, distinct values — don't collapse one
into the other.

## Controlled `frequency` values

Used on each `<schedule>` (not on `<market>` itself).

```
one_time
weekly
biweekly
monthly
seasonal
other
```

Pick the closest bucket, then put the actual human-readable detail — days,
hours — in the schedule's `<frequency_detail>` regardless of which bucket you
picked. `frequency_detail` is where the real information lives; `frequency`
is just a coarse filter.

## Field reference

Every field except `<name>` is optional. Omit an element entirely rather than
leaving it empty — the import treats a missing element and an empty one the
same way (both become `null`), but omitting is cleaner and makes it obvious
at a glance what you actually found versus didn't.

| Element | Notes |
|---|---|
| `name` | Required. |
| `city` | Free text. |
| `region` | One of the controlled list above. |
| `market_type` | Free text — "Farmers", "Artisan", "Makers", etc. |
| `sponsor` | The business or organization behind the market, when that isn't already in its name — e.g. a night market at a resort, run by the resort's restaurant (`Finz Restaurant`). Leave it out when the market is simply run by its own organization. |
| `address_line1` | Street address. |
| `address_line2` | Unit/suite, if any. |
| `province` | Almost always `BC`. |
| `postal_code` | Canadian format, e.g. `V1E 4N2`. |
| `vendor_fees` | Free text — fee structures vary too much to model. |
| `phone` | The market's own general/public line. |
| `manager` | Manager's name. |
| `manager_phone` | Manager's direct line, if different from the general phone. |
| `manager_email` | |
| `facebook_page` | Full URL. |
| `instagram_page` | Full URL. |
| `website` | Full URL. |
| `description` | Public-facing blurb — what the market is. |
| `notes` | Internal notes — anything uncertain, conflicting, or worth a human's attention. |
| `sources` | The URL(s) this record was built from, one per line. Always fill this in — it's how someone later checks your work. |
| `liveness_score` | `0`-`4`, from the liveness scoring step below. This is the score for the *market as a whole* (does it still exist and run?); each schedule carries its own too. A brand-new market scoring 0-1 doesn't get an XML entry at all, but one already in the database does, so its old score gets overwritten (see SKILL.md step 7). **The import marks any market saved with a score of 0 or 1 inactive** (there's no `<is_active>` element to write — the score drives it), and inactive markets are hidden from the admin panel's default list. |
| `schedules` | Container for one or more `<schedule>` blocks — see below. Optional; omit it entirely when research didn't turn up any schedule detail (an existing market's hand-entered schedules are left alone when a re-import has none). |
| `liveness_checked_at` | `YYYY-MM-DD`. Optional — if omitted, the import defaults it to the import date itself, which is correct for a fresh research pass. Only set it explicitly when re-importing older research and you want the *original* check date preserved instead. |

## Schedules

A market can run several distinct schedules — a summer market and a winter
market, a Christmas fair at a different venue. Write one `<schedule>` per
distinct run inside `<schedules>`; don't cram them into one text field. Most
markets will have just one.

| Element | Notes |
|---|---|
| `label` | Optional. "Summer Market", "Winter Market", "Christmas Craft Fair". |
| `frequency` | One of the controlled list above. |
| `frequency_detail` | Free text — days and hours. |
| `start_date` / `end_date` | `YYYY-MM-DD`. Only when the source actually states the year — leave them out rather than guessing one (e.g. a site saying "starts March 23" with no year). |
| `address_line1` | Only when this schedule is held somewhere other than the market's own address. |
| `notes` | Anything uncertain about this schedule specifically. |
| `liveness_score` | **Required.** `0`-`4`, scored per schedule (see SKILL.md step 6). A schedule without a valid one is skipped on import and reported, not imported with a guess. |
| `liveness_checked_at` | Same rules as on `<market>`: defaults to the import date; leave it out normally. |

## Example

```xml
<markets>
  <market>
    <name>Salmon Arm Farmers Market</name>
    <city>Salmon Arm</city>
    <region>Shuswap</region>
    <market_type>Farmers</market_type>
    <address_line1>100 Ross St</address_line1>
    <province>BC</province>
    <postal_code>V1E 4N2</postal_code>
    <phone>250-555-0100</phone>
    <facebook_page>https://facebook.com/salmonarmfarmersmarket</facebook_page>
    <sources>https://facebook.com/salmonarmfarmersmarket</sources>
    <liveness_score>4</liveness_score>
    <schedules>
      <schedule>
        <label>Summer Market</label>
        <frequency>weekly</frequency>
        <frequency_detail>Saturdays 8:30am-12:30pm</frequency_detail>
        <start_date>2026-05-02</start_date>
        <end_date>2026-10-31</end_date>
        <liveness_score>4</liveness_score>
      </schedule>
    </schedules>
  </market>
  <market>
    <name>Vernon Farmers Market</name>
    <city>Vernon</city>
    <region>Okanagan</region>
    <market_type>Farmers</market_type>
    <website>https://vernonfarmersmarket.ca</website>
    <sources>https://vernonfarmersmarket.ca</sources>
  </market>
</markets>
```

Note the second entry: several fields are simply absent (no address, no
phone, no schedules) because the research didn't turn them up — that's expected and fine,
not an error to fix by guessing.
