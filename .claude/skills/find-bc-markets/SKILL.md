---
name: find-bc-markets
description: Research British Columbia artisan/farmer's markets for a specific city or region and produce a ready-to-import XML file for the cultpantry admin panel's Farmer's Markets module (modules-market). Use this whenever the user asks to find, research, look up, document, or add farmer's markets, artisan markets, or maker's markets anywhere in BC — by city, by named region (e.g. Shuswap, Okanagan, Kootenay Rockies), or generally — even if they just say something like "find markets in the Shuswap" or "add some Okanagan farmers markets" without mentioning XML, import, or this skill by name.
---

# Find BC Markets

Researches real farmer's/artisan markets in British Columbia and writes an
XML file shaped for the modules-market admin panel's Import XML feature
(`POST /admin/market/import`, handled by
`Cultpantry\Market\Actions\ImportMarketsFromXml`). This is a research aid,
not an importer — it never touches the database itself. The user reviews the
XML and imports it by hand through the admin UI, same as any hand-written
import file.

**Read `references/market-xml-schema.md` before writing any output.** It has
the exact field list, the controlled `region`/`frequency` value lists, and a
worked example. Don't reconstruct these lists from memory or guess at new
region names — they're validated server-side against
`Cultpantry\Market\Models\Market::REGIONS`/`FREQUENCIES`, so a value outside
that list either gets silently rejected (frequency falls back to `other`) or
flagged back at the user as unmatched (region). Getting it right the first
time saves a round trip.

## Why assisted, not automated

This is a human-in-the-loop research task, not a scraper. Two reasons that
matters:

- **Facebook and Instagram restrict automated access.** Both are Meta, and
  their terms don't allow bots scraping pages programmatically. Visiting a
  page through the Chrome extension, as the user's own logged-in browser
  session, is a legitimate way to read public content a person could read
  themselves — hitting their endpoints directly (via `WebFetch` or a raw
  request) is not, and will also just get blocked or return garbage in
  practice.
- **Data quality.** A market's hours, fees, and even whether it still exists
  change often and get stale on old listings. A human spot-checking a
  handful of found markets produces cleaner data than a crawler dumping
  everything a search turns up. Flag anything uncertain rather than
  resolving it by guessing — see the liveness score and flagging steps
  below.

## Workflow

1. **Confirm scope.** If the user gave a city or region, use it directly. If
   they didn't (e.g. "find some BC markets"), ask which city or region —
   researching all of BC at once produces a shallow, low-confidence result
   for a task that's much better done a region at a time.

2. **Look up what's already in the database for this scope.** This is what
   makes a repeat run (e.g. a weekly re-check) produce a diff instead of just
   re-reporting the same markets from scratch. Query the live
   `market_markets` table read-only, from the cultpantry app:

   ```
   cd /Users/shawn/Documents/code/cultpantry && php artisan tinker --execute="echo \Cultpantry\Market\Models\Market::with('schedules')->where('city', '<city>')->orWhere('region', '<region>')->get()->toJson();"
   ```

   Match on whichever of city/region the user gave in step 1 (use just one
   `where` if only one applies). Pull every column (no `get([...])` field
   list) rather than just the liveness-relevant ones — you need the full row
   on hand, not just enough to score it, because of the carry-forward rule
   below. Keep this baseline list in mind through the rest of the research —
   step 6 compares fresh findings against it, and step 9 reports the diff.
   This step only reads; nothing about it writes to the database, so it's
   safe to run every time, including the very first run for a new scope
   (where it'll just come back empty).

3. **Search broadly first.** Use `WebSearch` to find candidate markets:
   market directory pages, Destination BC / regional tourism sites,
   municipal or chamber-of-commerce listings, "best farmers markets in
   [city]" roundups, and each market's own website if it has one. This step
   often surfaces the market's Facebook page URL too, which you'll need for
   step 4.

   **Always also check Castanet's events page directly**
   (`https://www.castanet.net/events`) for the scope's town or region.
   Castanet covers most of the BC Interior (Okanagan, Shuswap, Kamloops/
   Thompson, Kootenays) and its events listings regularly carry markets,
   garlic/harvest festivals and craft fairs that a generic web search
   misses — treat it as a standing source to check, not just another search
   term, the same way step 4 treats a market's own Facebook page. The
   Events page has a region/city picker down the side (Central Okanagan,
   South Okanagan, North Okanagan, Thompson-Shuswap, Kootenays, Vancouver,
   each with its own city list) with a `CRAFTS/MARKETS` category filter —
   pick the scope's actual sub-region rather than reading whatever loads by
   default (the bare URL defaults to Kelowna/Central Okanagan). Castanet
   also runs a **separate homepage per region** (e.g.
   `castanet.net/vernon/` for North Okanagan; the bare `castanet.net/` is
   Kelowna's) carrying that area's own local news, which is worth a look
   alongside the events page for closure/relocation stories the second-pass
   step below searches for.

   **For Shuswap-area scopes (Salmon Arm, Sicamous, Sorrento, Falkland,
   Enderby and similar), also check the Salmon Arm Observer directly**
   (`https://saobserver.net/`) — it's the dedicated local paper for that
   area and regularly covers markets, fairs and festivals (openings,
   closures, relocations, road-closure approvals) that Castanet's
   North Okanagan/Thompson-Shuswap coverage doesn't reach as reliably.

   **Local radio stations are worth checking too, specifically their
   written community events calendar, not the station's on-air schedule.**
   Confirmed working example: `myshuswapnow.com/community-calendar/` (Shuswap)
   is a real, dated, text-searchable calendar of community events —
   distinct from the station's own on-air "Community Events Calendar"
   segment, which on at least one Shuswap station (CKVS 93.7) is audio-only
   and not usable for research. Other BC Interior towns often have an
   equivalently-branded local station site (a `my<town>now.com`-style
   domain) with the same kind of calendar — search for "`<town> radio
   station community events`" to find the right one rather than guessing
   the URL, since the pattern isn't confirmed for every region.

4. **Visit Facebook and Instagram pages through the browser, not
   WebFetch.** For any market whose primary online presence is a Facebook
   page or Instagram profile (common for smaller markets — a market's own
   website often links both), load the Chrome extension tools first —
   `ToolSearch("select:mcp__claude-in-chrome__tabs_context_mcp,mcp__claude-in-chrome__navigate,mcp__claude-in-chrome__computer,mcp__claude-in-chrome__get_page_text")`
   — then navigate to the page and read its actual content (About section,
   pinned posts, recent event posts) the same way a person would. Never
   simulate, guess, or reconstruct what a page probably says — only report
   what you actually read there.

   **Instagram needs the user logged in, and that's their step, not yours.**
   Logged out, Instagram covers the profile with a login dialog and the page
   text gives you only the bio and follower count — no dated posts, which is
   the one thing a liveness check needs. If you see that wall ("Log in",
   "Continue with Facebook"), stop and ask the user to log in themselves in
   that browser tab, then carry on. Don't click "Continue with Facebook" or
   any login button and never type credentials — that would be signing in on
   their behalf. If they'd rather not, record the Instagram page as
   "unverified (login wall)" in the market's `<notes>` and don't score
   anything from it.

   Once in, read what a person checking on the market would: the bio (hours
   and location often live there), the link-in-bio page (an ordinary website,
   fine to open normally), pinned posts, and the dates on the latest few
   posts. The profile page text (`get_page_text`) gives you the bio and
   counts but **not** post dates — the grid is images, and dates printed
   inside the graphics ("Saturday September 12") are the event's date, not
   the post's. To get a post's real date, open it and read its `<time>`
   element with the javascript tool:
   `[...document.querySelectorAll('time')].map(t => t.getAttribute('datetime'))`
   returns an exact ISO timestamp including the year. Pinned posts sort
   first in the grid, so open a few posts and take the newest date rather
   than assuming the top-left one is the latest. Keep it light and read-only: one profile and a handful of
   posts per market, a pause between markets, no endless scrolling. Never
   follow, like, comment, message, or open Stories — story views show up to
   the account owner as the user's own name. And this is the user's real
   account: if Instagram shows a challenge, "suspicious activity", or "try
   again later", stop immediately and tell them rather than pushing on.

5. **Extract fields per the schema.** For each market, fill in whatever
   fields the sources actually support — see
   `references/market-xml-schema.md` for the full list. Most markets won't
   have every field filled; that's expected, not a gap to paper over by
   guessing. Always record `sources` (the URL(s) the info came from) so the
   user can verify anything later.

   **Don't copy a combined listing's dates onto each market.** A tourism page
   that covers two co-located markets ("a double market experience") gives
   one date range for the pair, and it's usually just one of them. Take each
   market's dates from its *own* site or a per-market directory such as the BC
   Farmers' Market Trail, and treat two markets that end up with identical
   dates and near-identical hours as a red flag to re-check, not a result.
   (Revelstoke's Farm & Craft Market was first given the Local Food
   Initiative market's October 31 end date; its own directory listing says
   October 10.) When sources disagree on a date, say so in `<notes>` and name
   which one you used and why.

   **Name a sponsor when there is one.** When a market's name differs from the
   business or organization actually behind it — a night market hosted by a
   resort's restaurant, a Christmas market put on by a brewery — record that
   business in `<sponsor>`. If the market is just run by its own
   organization, or the sponsor is already in the name, leave it out rather
   than repeating the name.

   **Split distinct schedules out.** When a market runs more than one
   distinct thing — a summer market and a winter market, a holiday fair at a
   different venue — write each as its own `<schedule>` rather than
   squeezing them into one description. Give each a label, its days/hours,
   and start/end dates *only when the source states the year* (a site that
   says "starts March 23" with no year gets no date, not a guessed one). A
   market with one ordinary schedule just gets one `<schedule>`; a market
   where the research turned up no schedule detail at all gets none.

6. **Score liveness before deciding whether to include a market.** A market
   found on a search doesn't mean it still runs — old blog posts and
   directory listings outlive the markets they describe all the time. Score
   each market against these four checks, one point each:

   - **Recent activity, in season.** Its Facebook or Instagram page (or site) has a post
     within roughly the last 12 months, OR it's currently off-season for
     that market and its last posts look like a normal end-of-season
     wind-down rather than going quiet mid-season. Check the post's actual
     date against today's date — don't estimate.
   - **Current-year confirmation.** Something dated to *this* year
     specifically: an upcoming event, "2026 season" language, an open
     vendor application for the current year — not just "every Saturday"
     with no date attached anywhere.
   - **Live website**, if it has one. It loads and reads like the market's
     own current content — not a fetch failure, an expired/parked domain,
     or an obvious placeholder. (Skip this check, don't penalize for it, if
     the market has no website at all — plenty of small markets only exist
     on Facebook.)
   - **Corroboration.** At least two independent sources (not two pages
     that clearly just copied each other) agree it's currently operating. A
     market's own Facebook and Instagram are the same operator, so together
     they count as one source, not two.

   Don't score follower/like counts — a defunct page keeps its followers
   forever, so this tells you nothing about whether the market still runs.

   One override beats the score entirely: if anything explicitly says the
   market is closed, discontinued, or "permanently closed" (Facebook
   sometimes marks this directly on a Page), treat it as dead regardless of
   how it scores elsewhere.

   **What the score means:**

   | Score | Meaning | What to do |
   |---|---|---|
   | 4/4 | Confident it's active | Include normally, write `<liveness_score>4</liveness_score>` |
   | 2-3/4 | Probably active, some gaps | Include, write `<liveness_score>n</liveness_score>`, and add a one-line `Liveness: n/4 — <what's missing>` note in `<notes>` so the gap is visible without opening the admin UI |
   | 0-1/4, or explicit closure | Likely defunct | Run the step 7 second pass first if you haven't. If it still lands here: don't write a `<market>` entry for a market that isn't in the baseline — mention it in your summary instead ("found mentions of X Market but it looks defunct: `<why>`"), so they know you looked and why you excluded it rather than just missing it. **If it *is* in the baseline, write the entry anyway** with the low score: leaving the old, now-contradicted score on file would be actively misleading. The import then **marks that market inactive automatically** (a market scoring 1 or below is deactivated the moment it's saved), which also hides it from the admin panel's default list — it stays findable via the Status filter, not deleted |

   The score lands in the modules-market admin panel as its own field
   (`Market::liveness_score`, filterable/sortable on the Index page) — this
   is what actually makes low-confidence markets findable later for a
   re-check, rather than the score being throwaway reasoning that only
   existed for this one research pass. Leave `<liveness_checked_at>` out
   unless you're deliberately backdating it (see the schema reference) — it
   defaults to today, which is correct almost always.

   **Score each schedule too — it's required.** The market-level score
   answers "does this market still exist and run?"; each `<schedule>` needs
   its own `<liveness_score>` answering "is *this specific schedule*
   current?" A market can be very much alive while its winter schedule is
   stale or its Christmas fair hasn't run in years. Apply the same four
   checks to the schedule itself: recent activity around it, something dated
   to this year (published dates for an upcoming season count), the website
   agreeing, and a second source agreeing. A schedule whose season hasn't
   started yet can't have in-season activity to show, so 3/4 with a note
   saying so is the honest ceiling when the only evidence is published dates.
   A schedule can't sensibly score higher than the evidence for its market
   does. The import skips any schedule missing a valid score and reports it,
   so never leave one out or guess one to make the file import cleanly.

   **If this market matched one already in the baseline from step 2**, this
   is also the comparison point — classify it as you score it, since you're
   already looking at the same evidence:

   - **Unchanged.** Score comes out the same (or within 1 point — that's
     normal noise between two independent passes, not a real change) and
     the fields you found match what's on file. Still write the `<market>`
     entry — re-importing it bumps `liveness_checked_at` to today, which is
     the point of a re-check: it records that someone actually looked again
     recently, not just that it was once confirmed active. Build this entry
     from the baseline row itself (every field, carried forward as-is), not
     from scratch — see the schema reference's note on why an omitted field
     wipes the existing value on re-import rather than leaving it alone. It
     doesn't need a mention of its own in the summary beyond the roll-up
     count; the user doesn't need to re-read a market that didn't change.
   - **Changed.** Score moved by more than 1 point, went active↔defunct, or
     a concrete field differs (phone, fees, etc.) from what's on file, or a
     schedule appeared, disappeared, or moved (match schedules within a
     market by label; the baseline query includes them). Same carry-forward
     rule as Unchanged: start from the baseline row and overwrite only the
     fields that actually changed, not a fresh entry built solely from this
     pass's findings. Write the entry with the new values, and call this one
     out by name in the summary with old → new, so it doesn't get buried in
     a count.
   - **New.** Doesn't match anything in the baseline. Handle exactly as
     before — no special treatment needed beyond what step 5-6 already do.

   Match a fresh finding to a baseline row on name (case-insensitive,
   ignoring minor punctuation/whitespace differences) within the same city —
   don't try to be clever about fuzzy-matching across cities.

7. **Second pass on anything that scored 2 or below.** A low score means
   the first pass couldn't find current evidence — which can just mean it
   looked in the wrong places (a market can be alive with a neglected
   Facebook page, or dead with a website that still loads). Before deciding
   what a low-scoring market or schedule gets, deliberately look somewhere
   else. This applies to a market scoring 2 or below *and* to any single
   schedule scoring 2 or below; for a schedule, search for that specific
   season or event (e.g. "Christmas Craft Fair Salmon Arm 2025") rather than
   re-researching the whole market.

   Pick the kinds of source that fit — roughly three to six targeted
   searches per item, not an exhaustive sweep:

   - **Local news and community papers** — the market's name plus words like
     "closing", "cancelled", "final season", "new location", "returns". For
     BC Interior scopes, check Castanet directly rather than relying on a
     general web search: its region-specific homepage (`castanet.net/vernon/`,
     etc. — not the bare `castanet.net/`, which is Kelowna's) and its events
     page filtered to the scope's sub-region. For Shuswap-area markets, also
     check the Salmon Arm Observer directly (`https://saobserver.net/`) and
     that area's local radio station community calendar
     (`myshuswapnow.com/community-calendar/`, or search for the equivalent
     in another town).
   - **Municipal and venue sources** — the city's events calendar, parks and
     rec, council agendas or minutes mentioning the market, and the venue's
     own page (the park, mall, or plaza it's held at).
   - **Current-year program lists** — the BC Farmers' Market Nutrition Coupon
     Program's participating markets, BC Association of Farmers' Markets
     members, the BC Farmers' Market Trail, regional tourism sites. Check the
     date these show; tourism listings routinely lag.
   - **The vendor side** — vendors' own pages or Facebook/Instagram posts
     saying they'll be at the market this year (a vendor tagging the market
     or using its hashtag is independent of the market's own pages), or a
     vendor application with current-year dates.
   - **Maps and review sites** — Google Maps/Business Profile (a "permanently
     closed" flag, the dates of the newest reviews), Tripadvisor, Yelp.
   - **Web archive** — the date of the latest archived snapshot of the
     market's website, and whether its content ever changed. It tells you
     when the site was last actually touched, not just that it loads.
   - **Facebook or Instagram, if a page, event, or vendor post turns up** —
     through the browser only, exactly as in step 4, including the login
     handoff and the light-touch rules for Instagram.

   Only dated evidence counts. An undated listing doesn't show a market is
   operating, and a directory entry that copies an older one isn't an
   independent source. What the pass can conclude:

   - **Current evidence turned up** (dated to this year, or within the last
     12 months). Re-score with it — it feeds the same four checks, so the
     score can rise. Say what changed the score.
   - **Explicit closure turned up.** Treat as dead regardless of anything
     else, per the override in step 6.
   - **Nothing turned up anywhere.** If you searched at least three
     different *kinds* of source above and found no dated activity within
     the last 12 months in any of them, score it 1: the "currently
     operating" checks aren't met by a website that merely still loads or a
     directory entry that merely still exists. If you covered fewer than
     three kinds, or something was inconclusive, leave the score alone and
     say what's still unchecked.

   Mind the consequence: a market scored 1 or below is marked inactive
   when imported and disappears from the admin panel's default list, so a
   score of 1 from the "nothing turned up" rule isn't a soft note — hold
   that conclusion to the standard above (three kinds of source, a dated
   search, not just one thin pass). This only ever deactivates: a later
   higher score never re-activates a market on its own.

   Record the pass so a low score visibly means "checked twice", not
   "checked lazily": append `Second pass (<today's date>): searched <kinds
   of source>; <what was found, or that nothing was>.` to that market's (or
   schedule's) `<notes>`.

   Two limits. First, silence is evidence, not proof — even at 1/4 say "no
   current evidence found", never "closed", unless something actually says
   so. Second, never contact anyone: no calling the manager, no emailing
   vendors. If it's still genuinely uncertain, list the manager's phone or
   email in your summary as a manual follow-up for the user.

8. **Flag, don't resolve, other uncertainty — including baseline markets you
   couldn't reconfirm.** Beyond liveness: if two sources disagree on hours,
   fees, or location, or a market might be a duplicate of one you or the
   user already found, say so in the `<notes>` field and call it out in your
   summary. Don't silently pick one version of the truth; let the user
   decide.

   Separately: if a market from the step-2 baseline simply never came up in
   this pass's searches, that's *not* the same as evidence it closed — a
   search missing something is common and says nothing on its own. Don't
   lower its score or touch its record at all. Just list it in the summary
   as "not reconfirmed this pass" so the user knows it wasn't silently
   dropped, and knows a real defunct-check on it (visiting its actual page)
   would need a dedicated look rather than relying on this pass's absence.

9. **Write the XML and hand it off as a diff, not a flat report.** Save the
   file to `~/Documents/market-research/` (create it if it doesn't exist
   yet), named `<city-or-region>-markets-<YYYY-MM-DD>.xml`. This is
   deliberately **not** the session's scratchpad directory — the scratchpad
   is a temp path buried under a session ID, which is fine for intermediate
   working files but a bad place to leave something the user needs to find
   again in a native file picker (Finder, or the admin panel's own "Choose
   File" dialog) later. `~/Documents/market-research/` is stable and easy to
   navigate to by hand every time. Tell the user the path, and structure the
   summary around what step 2's baseline makes visible:

   - **New markets found** — same as a from-scratch run: how many, their
     liveness scores if below 4/4, and anything excluded as likely-defunct
     per steps 6-7 (say what you found and why you left it out).
   - **Changed** — each one named, with what changed (old → new).
   - **Reconfirmed, no change** — just a count; these don't need detail.
   - **Not reconfirmed this pass** — markets from the baseline this
     search didn't surface, so the user knows to spot-check them manually
     if they care.
   - **Second-pass results** — for every market or schedule that got one in
     step 7: what you searched, what turned up, and the score change
     (e.g. 2 → 1). Say plainly which markets will be **marked inactive on
     import** because they scored 1 or below. Also anything still uncertain
     that's worth a manual phone call.

   On a first run for a new scope, the baseline is empty, so this collapses
   back to a flat "found N markets" report — the diff framing costs nothing
   when there's nothing to diff against yet. **Don't import the file
   yourself** — that's a deliberate step the user takes through the admin
   panel's Import XML button, reviewing the file first.

## One-time, pop-up and past-event markets

Recurring weekly markets are easy to find; the one-off ones — holiday markets,
craft fairs, harvest and Halloween markets, street festivals — mostly live in
event listings and community groups, and they repeat annually. Capture the
past ones too (from 2023 on), because last year's Christmas market is the best
predictor of next year's.

- **Where to look.** Facebook's event search
  (`facebook.com/events/search/?q=<town> market`) and the "suggested events"
  sidebar on any event page; the *search* box of a **public** Facebook group
  about local events (`.../groups/<id>/search/?q=market`); the pages of venues
  that host markets (malls, wineries, farms, rec centres); the downtown
  association's and organizers' own sites; local news, especially council
  road-closure stories (they name exact dates); and regional roundup guides,
  which are the best source for *past* editions. Instagram is worth opening
  for an organizer's known account, not for hashtag browsing — hashtag pages
  are images with no readable text.
- **Groups are read-only, and only public ones.** Read a public group the way
  any visitor could; never join, request access, or post. Private groups can't
  be read, so just note their names in the summary for the user.
- **Never accept a date without its year.** A Facebook event page's header
  carries the year — use it. (An event that looked like it might be upcoming,
  "Dec 7-8", turned out to be 2018.) If only a day and month are given and you
  can't find the year, leave the schedule's dates out and say so.
- **Open the source; don't trust a search summary.** Summaries in this
  environment have merged two different businesses, and returned events in
  Kelowna and Armstrong for a Salmon Arm search. Confirm the town and the name
  on the actual page. Keep the import to the area the user asked for (the town
  plus its immediate neighbours) and list nearby-but-outside finds in the
  summary instead of importing them.
- **How to model them.** A special day of an existing market (Apple Fest at
  the weekly downtown market) is a `one_time` schedule *on that market* — and
  since schedules are replaced wholesale on import, re-supply its existing
  schedules unchanged. A standalone event is its own market with one
  `one_time` schedule (`start_date` = `end_date` for a single day) for the
  next or most recent edition; put earlier editions, with their years, in
  `<notes>`. Say whether an event is borderline (a home show with a
  marketplace, an agricultural fair) so the user can drop it.
- **Scoring a one-time event** uses the same four checks, aimed at "is this
  event real and does it recur?": an upcoming edition confirmed by two
  *independent* sources, or one that already ran this year, is a 4; one
  source (only the organizer's own page and event, which count as one) is a
  3; something last seen two or more years ago is a 2 with a note of what's
  still unchecked. A brand-new event has no past edition — say so; its
  repeat is the thing to watch.
- **Past markets that look dead are still written when the user has asked
  for past markets to be captured.** The default (step 6) is to leave a
  brand-new likely-defunct market out, but for a user collecting past events
  "in case they repeat", write it with its low score: a score of 1 or below
  imports it as inactive, so it's on file but hidden unless someone includes
  inactive markets. Say so in the market's notes and in the summary, so the
  user can drop it from the XML if they'd rather not have it.
- **Flag likely successors, don't merge them.** A new page at the same
  location as a silent old one (a "Community Market Events" page in the same
  postal code as a market that stopped posting in 2024) is worth a note on
  both records, but only the user, or the organizer, can say they're the same.

## A note on scale

This works best run per-city or per-region, the way a person would actually
research it — not as a single pass meant to cover all of BC. If the user
wants broad coverage, that's naturally several invocations (one per region),
each producing its own reviewable XML file, rather than one giant unverified
batch.
