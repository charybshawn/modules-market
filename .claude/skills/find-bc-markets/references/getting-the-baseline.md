# Getting the baseline

`find-bc-markets`, `refresh-bc-markets`, and `scan-bc-market-socials` all
start from the markets already on file: the *baseline*. It's what makes a run produce a diff
instead of re-reporting everything, and it's what every carried-forward
entry is copied from (see the "full overwrite on re-import" note in
`market-xml-schema.md`). Get it one of two ways, in this order.

Neither way writes anything. The baseline is read-only.

## 1. The admin panel's Export XML file (preferred)

Ask the user for it:

> Please download the current market list from the admin panel:
> **Farmer's Markets → Export XML** (it saves as
> `markets-export-YYYY-MM-DD.xml`), and tell me where it saved.

Use this by default, because:

- **It's the real data.** Downloaded from production
  (`https://cultpantry.ca/admin/market`), it's exactly what the import will
  be applied to. A local database can be empty or weeks stale.
- **It works on any machine.** It needs no local cultpantry checkout, no
  database, and no particular folder layout.
- **It's already in the import's format.** It's produced by
  `ExportMarketsToXml`, the mirror image of `ImportMarketsFromXml`, so an
  entry can be carried forward by copying its `<market>` element and
  editing only what changed. `<weekdays>` are already names (`sat,sun`),
  `<week_of_month>` is already `1`-`4` or `last`, and a null field is simply
  absent.

If the user already gave a path to an export (or one is in the conversation),
use it without asking again. If it's more than a day or two old, say so,
since someone may have edited markets since.

Scope it yourself after reading it. The export always contains every market.

- `find-bc-markets` and `scan-bc-market-socials`: keep the `<market>`
  entries whose `<city>` or `<region>` matches the requested scope.
- `refresh-bc-markets`: keep them all, sorted by `<liveness_checked_at>`
  with missing dates first, then oldest first.

**One field the export doesn't carry: `is_active`.** The import derives it
from the score, and so should you: treat a market whose `<liveness_score>`
is `0` or `1` (`Market::DEACTIVATE_AT_OR_BELOW`) as inactive. A market an
admin switched off by hand despite a higher score will look active in the
export. That's a known gap in `ExportMarketsToXml`, noted in its docblock.
Mention it if the user says a market "should be off" but it scores 2+.

**Ignored markets.** A market an admin marked "not relevant to us" (only
possible from its page in the admin panel) carries an `<ignored_at>` date,
plus an `<ignored_reason>` when one was given. The local fallback below has
the same as `ignored_at`/`ignored_reason` (set = ignored). Both are
read-only: the import never reads them, so an ignored market that ends up in
your XML is harmless (its details update and it stays ignored), and you
can't ignore or un-ignore a market by editing them.

## 2. A local cultpantry checkout (fallback)

Use this only if the user can't get an export, or explicitly asks for
their local data. Say plainly that it reads **whatever database that
checkout points at**, which is usually a local dev copy, not production.

Find the checkout. Don't assume a path:

1. The directory next to this repo: `../cultpantry` relative to the
   `modules-market` root.
2. Otherwise, ask the user where it is.

Confirm it's the right app (it has an `artisan` file and
`vendor/cultpantry/market`), then run tinker from it:

```bash
cd <cultpantry> && php artisan tinker --execute="echo \Cultpantry\Market\Models\Market::with('schedules')->orderBy('liveness_checked_at')->get()->toJson();"
```

Add a `->where('city', '<city>')->orWhere('region', '<region>')` before
`->get()` to scope it for `find-bc-markets`.

The JSON differs from the XML in three ways. Convert when you write the
entry back:

| JSON | XML |
|---|---|
| `weekdays`: integers, `0` = Sunday … `6` = Saturday | `<weekdays>sun,sat</weekdays>` |
| `week_of_month`: `1`-`4`, or `-1` for last | `<week_of_month>1</week_of_month>`, or `last` |
| `is_active`: present | not written. The score drives it on import |
| `ignored_at`: a timestamp | `<ignored_at>` is a `YYYY-MM-DD` date. Read-only either way |

Times are `HH:MM` in both.
