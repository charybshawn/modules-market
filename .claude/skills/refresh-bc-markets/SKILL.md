---
name: refresh-bc-markets
description: Re-checks markets ALREADY in the cultpantry Farmer's Markets admin module (modules-market) against current online sources -- web search, Facebook (including public groups), Instagram, and Threads -- to catch closures, schedule changes, and stale liveness scores, and produces an update-ready XML file. Use this whenever the user asks to refresh, re-check, audit, verify, update, or "check for updates on" the markets already in the system -- as opposed to find-bc-markets, which discovers NEW markets in a city or region. Trigger on phrasing like "check the markets for updates", "see if anything's changed", "audit the market list", "are these markets still active", or a request that starts from the existing list rather than a place.
---

# Refresh BC Markets

Pulls the full list of markets already on file and
re-verifies each one against current sources, producing an XML file for the
Import XML feature that updates what changed and leaves everything else
alone. This is the maintenance counterpart to `find-bc-markets`: that skill
discovers new markets for a place the user names; this one starts from the
current market list and asks "is what's on file still true?"

Like `find-bc-markets`, this is a research aid, not an importer -- it never
touches the database. The user reviews the XML and imports it by hand.

**Read `../find-bc-markets/references/market-xml-schema.md` before writing
any output**, especially the "full overwrite on re-import" note -- it's the
single most important thing to get right in this skill specifically, more so
than in `find-bc-markets`, because *every* market this skill touches is
already in the database. Getting it wrong means silently blanking fields on
real, currently-accurate records.

## The one rule that matters most here

**Every entry you write starts from that market's own current baseline entry,
with every field carried forward unchanged, and you overwrite only the
specific fields you found new information for.** Never rebuild an entry
from scratch using only what this pass happened to turn up. A market you
only fully reconfirmed via its Facebook page still needs its `vendor_fees`,
`description`, `manager` and every other field written back exactly as they
already are -- you didn't re-derive them this pass, and that's fine; they're
not what you're checking. Same for `<schedules>`: a market whose schedule
didn't change should still get its existing schedule(s) written back
verbatim (dates, labels, frequency, all of it) inside a `<schedules>` block,
not an empty one and not an omitted one -- omitting `<schedules>` is the one
case that safely leaves existing schedules alone, but including the block at
all replaces the whole set, so a partial or reconstructed-from-memory
schedule list would quietly drop real data.

From the Export XML baseline, carrying an entry forward is literally
copying its `<market>` element and editing only what changed. That
includes each schedule's `<weekdays>`, `<week_of_month>`, `<start_time>`
and `<end_time>`. Dropping them would take the market off the calendar.
(If the baseline came from the tinker fallback instead, convert its JSON as
the table in `getting-the-baseline.md` shows.)

## Backfilling schedule structure

Some schedules still lack the structured day/time fields (a wording the
one-time conversion of the old free-text field couldn't parse ends up whole in
the schedule's notes). With null `weekdays`/`start_time`/`end_time` they
don't appear on the admin calendar. **On every market you touch,
deep-parse any schedule that's missing that structure.** Use the rules and
worked examples in `find-bc-markets/references/market-xml-schema.md` →
"Deep-parsing schedules". Parse from the current source you just checked
when there is one, or from the baseline `<notes>` when the schedule is
reconfirmed unchanged.

Split a schedule when the rules call for it. For example, a
"Wed 4-7, Sat 9-1" row becomes two schedules. Splitting replaces the
original row, so keep its label on one of the halves where it still fits.
Report backfills as their own group in the summary ("Structure added: N
schedules"), separate from real schedule changes. Name anything you could
only partly structure (no hours stated, biweekly with no anchor date) so
the user can fill it in by hand.

## Workflow

1. **Get the full baseline.** Every market, every field, every schedule --
   this is the source of truth you'll carry forward and diff against. **Follow
   `../find-bc-markets/references/getting-the-baseline.md`**: ask for the
   admin panel's Export XML file (preferred: it's production's data and
   works on any machine), or fall back to a local cultpantry checkout.

   Sort it by `liveness_checked_at`, missing dates first, then oldest
   first. That ordering is what makes step 2's prioritization
   straightforward -- the markets most overdue for a look come first.

2. **Scope the run.** Checking every market across four platforms in one
   pass doesn't hold up the same way `find-bc-markets`' per-region searches
   do -- each market needs its own handful of browser visits, and burning
   through all of them at once means less attention per market and a long
   stretch of the user's real Facebook/Instagram session in active use. If
   the user named a scope (a region, a city, "just the ones scoring low",
   "just Shuswap"), use it directly. If they asked for a full refresh with
   no scope, or just said "check for updates" generally, take it in batches:

   - **Default batch: 10-12 markets**, prioritized by the step-1 ordering
     (oldest/never-checked first), then by liveness score (lowest first)
     among ties. This is a default, not a hard cap -- a small enough scope
     (a single region already down to 8 markets) just runs in full.
   - After a batch, report progress plainly (`Checked 12 of 39 -- continue
     with the next batch?`) rather than silently stopping or silently
     continuing through all of them. Let the user decide whether to keep
     going now or pick it up later.
   - Inactive markets (liveness 0-1; the export has no `is_active`, see
     `getting-the-baseline.md`) are
     included by default but check them last and more lightly -- a couple of
     searches, not a full multi-platform pass -- since a market already
     marked defunct re-activating is a real but rare event, not something
     worth spending equal effort on relative to markets still presumed
     live. If the user explicitly wants inactive markets checked
     thoroughly (e.g. "did any of the closed ones come back"), do the full
     pass on those instead.

3. **Check each market across the relevant platforms.** Not every platform
   for every market -- use what the baseline row already points to, and
   fall back to search for the rest:

   - **The market's own channels first.** If `facebook_page`,
     `instagram_page`, or `website` is already on file, visit it directly --
     load the Chrome extension tools
     (`ToolSearch("select:mcp__claude-in-chrome__tabs_context_mcp,mcp__claude-in-chrome__navigate,mcp__claude-in-chrome__computer,mcp__claude-in-chrome__get_page_text,mcp__claude-in-chrome__javascript_tool")`)
     and read it the way `find-bc-markets` step 4 does: About section,
     recent posts, actual post dates via the `<time datetime>` trick for
     Instagram. Same login-wall handling for Instagram -- stop and hand it
     to the user rather than clicking through it yourself, and same
     light-touch rules (no follows, likes, comments, Stories).
   - **`WebSearch`** for anything not already covered by an on-file link, or
     to catch news the market's own channels wouldn't mention about
     themselves (closure announcements, a venue change reported by local
     media). A quick search on the market's name plus its city and the
     current year is usually enough to tell you whether anything's changed;
     escalate to `find-bc-markets` step 7's second-pass source list (local
     news, municipal/venue pages, current-year program lists, the vendor
     side, maps/reviews, web archive) only for a market that comes back with
     nothing, same threshold as that step. For a market in the BC Interior,
     also check Castanet directly rather than relying on a general web
     search: its region-specific homepage (`castanet.net/vernon/`, etc. —
     not the bare `castanet.net/`, which is Kelowna's) and its events page
     filtered to the market's sub-region and the `CRAFTS/MARKETS` category.
     For a Shuswap-area market (Salmon Arm, Sicamous, Sorrento, Falkland,
     Enderby and similar), also check the Salmon Arm Observer directly
     (`https://saobserver.net/`) and that area's local radio station
     community calendar (`myshuswapnow.com/community-calendar/`, or search
     for the equivalent in another town — a written events calendar, not
     the station's on-air schedule).
   - **Facebook groups, read-only, public only.** When a market has no
     `facebook_page` on file, or its page looks abandoned but the market
     might still run informally, a regional community-marketplace or
     buy-and-sell group is often where small/seasonal markets actually get
     announced. Search Facebook's own search for groups matching the
     market's city or region (`facebook.com/search/groups/?q=<area> market>`
     or similar), then a public group's own search box
     (`.../groups/<id>/search/?q=<market name>`) for mentions. Never join,
     request access, or post -- read exactly what a visitor could, and note
     a private group's name in the summary rather than trying to get into
     it. This mirrors `find-bc-markets`' one-time-events section, which has
     the same rule for the same reason.
   - **Threads.** Treat as a light, best-effort check, not a platform to
     dig for. Meta links a Threads handle from its owner's Instagram profile
     when one exists, so if you're already on the market's Instagram page,
     glance for a Threads link rather than searching separately. If there's
     no Instagram on file and no obvious Threads presence turns up in the
     general `WebSearch` pass, don't spend a dedicated search cycle chasing
     one down -- small BC markets very rarely have a Threads presence
     distinct from their other channels, and this skill doesn't have a
     database column for it (fold anything genuinely found into `<notes>`
     or `<sources>`, not a field that doesn't exist).

4. **Screen for a vendor-market drift, then score liveness with the same
   rubric as `find-bc-markets`.** A market already on file already passed
   the independent-vendor-market bar once, so this isn't the "brand-new
   candidate" case -- but a market's own format can change over the years
   you're not looking. If what you read this pass suggests it's drifted
   toward storefront-only (vendor booths dropped, posts now only about
   in-store retail), run `../find-bc-markets/references/vendor-market-screen.md`'s
   decision rule and follow its "Re-checking a market already on file"
   section rather than silently rescoring it as if nothing changed. Then
   apply `find-bc-markets`' step 6 four-point check (recent in-season
   activity, current-year confirmation, live website, corroboration) and its
   step 7 second-pass escalation for anything landing at 2 or below -- don't
   reinvent or loosen the rubric here; read that skill's SKILL.md for the
   full version if it's not already in context. The one addition specific to
   a refresh pass:
   since every market here already has a *previous* score and
   `liveness_checked_at` date on file, the comparison in step 6's
   "Unchanged / Changed" classification is the whole point of this skill,
   not an incidental case -- there is no "New" case here at all, since
   everything you're checking started out in the baseline.

5. **Classify and write each entry**, starting from that market's baseline
   row per the carry-forward rule above:

   - **Reconfirmed, no change.** Score holds (within 1 point) and every
     field you checked matches what's on file. Write the entry anyway
     (baseline fields carried forward verbatim) so `liveness_checked_at`
     advances to today -- that's what makes the next refresh's staleness
     ordering meaningful. Roll-up count only in the summary.
   - **Changed.** A field genuinely differs, or the score moved by more
     than 1 point, or a schedule changed. (Only adding day/time structure
     to a schedule whose schedule itself didn't change counts as "Structure
     added", not "Changed". See "Backfilling schedule structure" above.) Carry the baseline forward and
     overwrite just what changed. Name it in the summary, old → new.
   - **Newly defunct.** Score drops to 1 or below, or something explicitly
     says closed. Still write the entry (carrying every other field
     forward) with the new score -- the import marks it inactive
     automatically. Call this out clearly in the summary; it's the single
     most actionable finding a refresh pass can produce.
   - **Came back.** An already-inactive (liveness 0-1) market shows real current
     activity. Write it with the higher score and say so plainly -- the
     import doesn't auto-reactivate anything below `DEACTIVATE_AT_OR_BELOW`
     on its own the other direction, but a clear positive score change is
     exactly what should prompt the user to flip it back on by hand.
   - **Not reached this pass.** Any market outside this run's batch (step
     2) -- list them, don't imply they were checked.
   - **Possibly no longer a vendor market.** Flagged by step 4's screen.
     Write the entry as usual (carry-forward rule applies same as any other
     case) with the `Vendor-market check:` note appended to `<notes>`, and
     call it out by name in the summary so the user decides whether it still
     belongs in the module -- don't deactivate or remove it yourself.

6. **Write the XML and report as a diff**, exactly following
   `find-bc-markets` step 9's format and file location
   (`~/Documents/market-research/`, named
   `refresh-<scope-or-date>-<YYYY-MM-DD>.xml`) and its emphasis on flagging
   uncertainty rather than resolving it. Structure the summary around the
   classifications in step 5 above rather than step 9's "New markets found"
   framing, since nothing here is new. Include a "Structure added"
   group: the count of schedules backfilled with days/times, plus a named
   list of any that are only partly structured. **Don't import the file yourself.**

## A note on scale and pacing

This is inherently heavier per-market than `find-bc-markets`, since every
market gets checked (not just found), and several checks go through the
user's real, logged-in Facebook and Instagram sessions. Batch it, pace it,
and check in with the user rather than trying to silently push through the
full list in one long unattended run -- see step 2.
