# Market XML schema

Source of truth for everything on this page: `Cultpantry\Market\Models\Market` and
`Cultpantry\Market\Actions\ImportMarketsFromXml` in this repo's `src/`
(the `modules-market` root, two levels above this skill's folder). If the Laravel side ever
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
Northern BC
Okanagan
Sea to Sky
Shuswap
Similkameen
Sunshine Coast
Thompson
Thompson Okanagan
Vancouver Island
```

"Northern BC" is deliberately one value for the whole north (Prince George,
the Peace, the northwest and the north coast): it's a huge, thinly populated
area with few markets, so it isn't split further. The older Nechako, North
Coast and Northern Rockies values are retired; an import of an old file still
accepts them and files them under Northern BC.

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

Pick the closest bucket, then put the actual days and hours in the schedule's
`<weekdays>`, `<start_time>` and `<end_time>`; `frequency` is just a coarse
filter. Everything else the source says about the schedule (entry fee, vendor
count, closures) goes in the schedule's `<notes>`. There is no separate
"days and hours" text field.

## Field reference

Every field except `<name>` is optional. Omit an element entirely rather than
leaving it empty — the import treats a missing element and an empty one the
same way (both become `null`), but omitting is cleaner and makes it obvious
at a glance what you actually found versus didn't.

**That "omit what you didn't find" rule is for a brand-new market only.**
The import does a full `fill()` on every `<market>` node it matches by
(name, city) — there's no partial-patch mode. For a market that's *already
in the database*, an omitted element doesn't leave the existing value
alone; it overwrites it with `null`, same as an empty one. So when writing
an entry for a market you pulled from a baseline query (a re-check,
`find-bc-markets` step 6's "Unchanged"/"Changed" case, or the
`refresh-bc-markets` skill), start from that baseline row's own field
values and carry every one of them forward into the XML unchanged, then
overwrite only the specific fields you actually found new information for.
Never regenerate an existing market's entry from scratch using only what
this pass happened to turn up — that silently blanks every field the pass
didn't touch. Schedules follow the same logic one level down: a re-import
replaces a market's schedules wholesale *only when the entry includes a
`<schedules>` block at all* (an entry with none leaves existing schedules
alone) — but the moment you do include one, every `<schedule>` inside it
needs to be complete, since there's no per-schedule carry-forward once
you've opted in to replacing the set.

| Element | Notes |
|---|---|
| `name` | Required. |
| `city` | The BC town the market is in, spelled as the town is normally written ("Salmon Arm", "West Kelowna"). It is free text, but it is also what a drive-time search locates the market by: a city missing here shows a "No city" badge, and one that isn't in `resources/data/bc-places.json` shows "Town not found" and can't be found by drive time. The import reports any such towns. |
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
| `ignored_at` | `YYYY-MM-DD`. **Export-only, never write it yourself.** Present when an admin marked the market ignored ("not relevant to us"). The import ignores it, so carrying it forward is harmless, but adding, changing or removing it does nothing. Ignoring is set only on the market's page in the admin panel. |
| `ignored_reason` | Export-only, same as `ignored_at`: the admin's note on why. |

## Schedules

A market can run several distinct schedules — a summer market and a winter
market, a Christmas fair at a different venue. Write one `<schedule>` per
distinct run inside `<schedules>`; don't cram them into one text field. Most
markets will have just one.

| Element | Notes |
|---|---|
| `label` | Optional. "Summer Market", "Winter Market", "Christmas Craft Fair". |
| `frequency` | One of the controlled list above. |
| `weekdays` | Comma-separated day keys: `sun,mon,tue,wed,thu,fri,sat`. The day(s) the market runs. **This is what puts a market on the admin calendar** — see "Deep-parsing schedules" below. Omit for `one_time`. |
| `week_of_month` | `1`-`4`, or `last`. Only for `frequency` = `monthly` ("2nd Saturday" → `2`, "last Sunday" → `last`). Ignored for any other frequency. |
| `start_time` / `end_time` | 24-hour `HH:MM` ("8:30am" → `08:30`, "12:30pm" → `12:30`, "5pm" → `17:00`). One pair per schedule. |
| `start_date` / `end_date` | `YYYY-MM-DD`. Only when the source actually states the year — leave them out rather than guessing one (e.g. a site saying "starts March 23" with no year). |
| `address_line1` | Only when this schedule is held somewhere other than the market's own address. |
| `notes` | Wording that isn't a day or an hour: entry fee, vendor count, "closed Thanksgiving weekend", "until sold out". Also anything uncertain about this schedule specifically. |
| `liveness_score` | **Required.** `0`-`4`, scored per schedule (see SKILL.md step 6). A schedule without a valid one is skipped on import and reported, not imported with a guess. |
| `liveness_checked_at` | Same rules as on `<market>`: defaults to the import date; leave it out normally. |

## Deep-parsing schedules

The admin calendar places a market on actual days using `weekdays`,
`week_of_month`, `start_time` and `end_time`. A schedule with no
`<weekdays>` (or, for `monthly`, no `<week_of_month>`) is imported fine but
lands in the calendar's "Not on the calendar yet" list. So for every
schedule, parse the source's days and hours all the way down to these fields,
and put whatever wording is left over in `<notes>`.

A legacy `<frequency_detail>` element in an older file is still accepted on
import: its days and hours fill any of these fields the file left empty, and
the remaining wording is appended to the notes. Don't write it in new files.

Rules:

- **Only encode what the source states.** "Saturday mornings" gives
  `weekdays` = `sat` but no times. Never infer hours from a typical market
  or from a different year's listing — leave the element out and say so in
  the schedule's `<notes>`.
- **One schedule = one set of weekdays + one opening time + one closing
  time.** A schedule holds one time pair. If different days have
  different hours, split them into separate `<schedule>`s with distinct
  labels, e.g. "Saturday Market" and "Wednesday Evening Market". Days
  that share the same hours stay together (`<weekdays>sat,sun</weekdays>`).
- **Monthly with several weeks** ("2nd and 4th Saturday") can't be one
  schedule, since `week_of_month` holds a single value. Write one `monthly`
  schedule per week, labelled apart ("Market — 2nd Saturday", "Market —
  4th Saturday"). "Every other week" is `biweekly`, not two monthlies.
- **`biweekly` needs an anchor.** The fortnight is counted from
  `start_date`. If the source gives no dated first market, still write
  `weekdays` and put "biweekly anchor date unknown" in `<notes>`. The
  calendar then shows it every week until someone fixes it, so flag it
  in the summary.
- **`one_time` uses dates, not weekdays.** `start_date` (and `end_date` for a
  multi-day fair) with `start_time`/`end_time`. Omit `<weekdays>`.
- **`seasonal` / `other` still get weekdays.** "Saturdays, May to October"
  is a `seasonal` (or `weekly`) schedule with `weekdays` = `sat` and the
  season in `start_date`/`end_date` when the year is stated.
- **Time words:** "noon" → `12:00`, "midnight" → `00:00`. Fuzzy ends ("until
  dusk", "until sold out", "till 2ish") get no `end_time`; the wording
  goes in `<notes>`.
- **Exceptions stay in text.** Skipped dates, holiday closures and "weather
  permitting" go in `<notes>`. The structured fields describe the normal
  pattern.

Worked examples:

| Source says | frequency | weekdays | week_of_month | start_time | end_time | dates |
|---|---|---|---|---|---|---|
| "Saturdays 8:30am–12:30pm, May 2 – Oct 31, 2026" | `weekly` | `sat` | | `08:30` | `12:30` | `2026-05-02` / `2026-10-31` |
| "Every Wed 4–7pm and Sat 9–1" | `weekly` ×2 | `wed` / `sat` | | `16:00` / `09:00` | `19:00` / `13:00` | split into two schedules |
| "Sat & Sun 10am–3pm" | `weekly` | `sat,sun` | | `10:00` | `15:00` | |
| "First Friday of the month, 5–9pm" | `monthly` | `fri` | `1` | `17:00` | `21:00` | |
| "Last Sunday monthly, 10 till 2" | `monthly` | `sun` | `last` | `10:00` | `14:00` | |
| "Every other Thursday from June 4, 2026, 3pm to dusk" | `biweekly` | `thu` | | `15:00` | | `2026-06-04` / — |
| "Christmas Craft Fair, Dec 5–6 2026, 10–4" | `one_time` | | | `10:00` | `16:00` | `2026-12-05` / `2026-12-06` |
| "Saturday mornings in summer" | `seasonal` | `sat` | | | | none (no year); note "hours not stated" |

## Always start with an XML declaration

Always write `<?xml version="1.0" encoding="UTF-8"?>` as the file's first
line. `SimpleXMLElement` and `DOMDocument` parse a file fine without it, so
it's easy to skip — but Laravel's upload validation (`mimes:xml`) MIME-sniffs
the raw file content, and a file that starts straight with `<markets>` gets
detected as `text/plain` instead of `text/xml`/`application/xml`, which fails
that rule with "The file field must be a file of type: xml" even though the
XML itself is perfectly well-formed. Check with `file --mime-type <path>`
before handing a file off — it should report `text/xml`, not `text/plain`.

## Example

```xml
<?xml version="1.0" encoding="UTF-8"?>
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
        <weekdays>sat</weekdays>
        <start_time>08:30</start_time>
        <end_time>12:30</end_time>
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
