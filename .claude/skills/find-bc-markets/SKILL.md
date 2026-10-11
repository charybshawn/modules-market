---
name: find-bc-markets
description: Research British Columbia artisan/farmer's markets for a specific city or region and produce a ready-to-import XML file for the cultpantry admin panel's Farmer's Markets module (modules-market). Use this whenever the user asks to find, research, look up, document, or add farmer's markets, artisan markets, or maker's markets anywhere in BC — by city, by named region (e.g. Shuswap, Okanagan, Kootenay Rockies), or generally — even if they just say something like "find markets in the Shuswap" or "add some Okanagan farmers markets" without mentioning XML, import, or this skill by name.
---

# Find BC Markets

Researches real farmer's/artisan markets in British Columbia and writes an
XML file for the modules-market import
(`Cultpantry\Market\Actions\ImportMarketsFromXml`), saved to the app repo's
`database/markets/` folder and loaded with `php artisan market:import-pending`.

**Read `references/market-xml-schema.md` before writing any output.** It has
the field list, the controlled `region`/`frequency` values (validated
server-side, so never invent one), the schedule-parsing rules, and "Saving
and importing".

This is human-in-the-loop research, not a crawler: a market's hours, fees and
even its existence go stale on old listings, so flag anything uncertain
rather than resolving it by guessing.

## Workflow

1. **Confirm scope.** Use the city or region the user gave. If they gave
   none, ask: researching all of BC at once gives a shallow, low-confidence
   result. Broad coverage means several runs, one per region, each with its
   own reviewable file.

2. **Get the baseline: what's already on file for this scope.** Follow
   `references/getting-the-baseline.md` (the admin panel's Export XML file,
   or a local checkout as fallback). Keep the entries whose city or region
   matches, with **every field**, since step 6's carry-forward rule needs
   the full entry. This is what turns a repeat run into a diff (step 9). It
   only reads, so it's safe on a first run too, where it comes back empty.

   The baseline can include **ignored** markets (see that reference). Never
   report one as a new find. Writing its entry is harmless (it stays
   ignored); don't suggest un-ignoring it, and mention them only as "N
   ignored markets in this area were left as they are".

3. **Search broadly first.** `WebSearch` for directory pages, Destination BC
   and regional tourism sites, municipal and chamber listings, "best farmers
   markets in [city]" roundups, and each market's own site. Note every
   Facebook/Instagram URL you find for step 4. Then check these **standing
   sources** directly, every run (step 7 reuses them):

   - **Castanet events** (`https://www.castanet.net/events`, `CRAFTS/MARKETS`
     category) for BC Interior scopes. It carries markets, harvest festivals
     and craft fairs that web search misses. Pick the scope's town in the
     region/city picker rather than reading the default (Kelowna). The
     picker sets `?loc=<city id>`, and towns don't always sit where you'd
     expect: Salmon Arm (`9`), Sicamous (`31`), Enderby (`19`) and Falkland
     (`28`) are under **North Okanagan**, while Thompson-Shuswap holds only
     Kamloops, Sun Peaks, Merritt and Chase. Listings show **one day at a
     time** (`/events/categories/crafts-markets/YYYY-MM-DD?loc=9`), so check
     the upcoming weekends, not just today. On 2026-10-11 the Salmon Arm
     filter returned "no events near you" for every date tried, so an empty
     result there is weak evidence, not a finding. Castanet also runs a
     **homepage per region** (`castanet.net/vernon/` for North Okanagan; the
     bare `castanet.net/` is Kelowna's) with local news worth a look for
     closure and relocation stories.
   - **Salmon Arm Observer** (`https://saobserver.net/`) for Shuswap-area
     scopes (Salmon Arm, Sicamous, Sorrento, Falkland, Enderby and similar).
     It covers openings, closures, relocations and road-closure approvals
     that Castanet misses.
   - **The local radio station's written community calendar**, not its
     on-air events segment (CKVS 93.7's is audio-only). Confirmed:
     `myshuswapnow.com/community-calendar/`. Other towns often have a
     `my<town>now.com`-style equivalent; search "`<town> radio station
     community events`" rather than guessing the URL.

   Most of these block `WebFetch` (403). Read them through the
   `browser-scrape` skill: `browserless_smartscraper` for a single page, or
   add them to the step 4 session.

4. **Read Facebook and Instagram through Browserless**, never `WebFetch` or
   the Chrome extension. **Load the `browser-scrape` skill and follow it**,
   including its Meta section (logged out, ≤40 page views, long waits, no
   saved `profile` unless the user picks a throwaway account, never their
   own). Specific to this task:

   - **One session, one page per call.** Read every URL from step 3 in a
     single `browserless_agent` session (`keepSessionAlive: true`,
     residential `ca` proxy). Each call's batch is one page's `goto`, wait
     and `evaluate`: a batch returns only its last command's result, so two
     pages in one batch loses the first. Put `reportOutcome` + `close` on
     the last page's batch, before writing any XML.
   - **Read only what's there.** Return the page text (About/intro, pinned
     and recent posts, each post's date) and report only what you read;
     never reconstruct what a page probably says. Read: the bio (hours and
     location often live there), the link-in-bio page (an ordinary site),
     pinned posts, and the latest few posts' dates. Stay read-only: no
     scrolling whole timelines, and never follow, like, comment, message,
     or open Stories.
   - **Dating posts.** A date printed in a post's graphic ("Saturday
     September 12") is the event's date, not the post's. Logged in, a
     post's `<time datetime>` gives the exact ISO date. Logged out (verified
     2026-10-11), Facebook renders no `<time>` elements and shows only one
     or two posts, dated absolutely ("May 6, 2025") or relatively ("a day
     ago", "1d"). Convert relative dates against today and say so in
     `<notes>`. Pinned posts sort first, so take the newest date you see.
   - **Login walls are a stop, not a puzzle.** Logged out, Instagram shows
     only the bio and follower count, and Facebook cuts a Page off after a
     post or two. So an old newest-visible post is the wall, not proof the
     market went quiet: don't lower a score on it alone. Record what you
     couldn't see as "unverified (login wall)" in `<notes>`. Never click a
     login button or type credentials. On a checkpoint, "suspicious
     activity" or "You're temporarily blocked", close the session at once
     and tell the user; don't switch proxies or personas to push past it.

5. **Extract fields per the schema.** Fill only what the sources support;
   missing fields are expected, not gaps to guess at. Always fill
   `<sources>`.

   - **Don't copy a combined listing's dates onto each market.** A tourism
     page covering two co-located markets gives one date range that usually
     belongs to just one of them. Take each market's dates from its own site
     or a per-market directory (e.g. the BC Farmers' Market Trail). Two
     markets with identical dates and near-identical hours are a red flag to
     re-check. (Revelstoke's Farm & Craft Market was first given the Local
     Food Initiative market's Oct 31 end date; its own listing says Oct 10.)
     When sources disagree, say so in `<notes>` and which one you used.
   - **Name a sponsor** in `<sponsor>` when the business behind the market
     differs from its name (a resort restaurant's night market, a brewery's
     Christmas market). Leave it out when the market runs itself or the
     sponsor is already in the name.
   - **One `<schedule>` per distinct run** (summer vs. winter market, a
     holiday fair at another venue), each **deep-parsed** into `<weekdays>`,
     `<week_of_month>`, `<start_time>`, `<end_time>` and, only when the
     source states the year, `<start_date>`/`<end_date>`. These fields are
     what put a market on the admin calendar; leftover wording goes in the
     schedule's `<notes>`. Follow the rules and examples in the schema's
     "Deep-parsing schedules". A schedule you couldn't fully structure is
     still written, with what's missing in its `<notes>`. No schedule detail
     at all means no `<schedules>`.

6. **Screen it, then score liveness.** Two separate questions, in order:

   **Is it an independent-vendor market at all?** Run the decision rule in
   `references/vendor-market-screen.md` on every candidate. A single
   business's storefront, sale or pop-up shop is excluded (or flagged, if
   ambiguous), however active it is.

   **Does it still run?** Old blog posts and directory listings outlive the
   markets they describe. Score each market that passes the screen, one
   point per check:

   - **Recent activity, in season.** A post on its Facebook/Instagram page
     or site within roughly the last 12 months, or, if it's off-season, last
     posts that look like a normal end-of-season wind-down rather than going
     quiet mid-season. Check the actual date against today.
   - **Current-year confirmation.** Something dated to *this* year: an
     upcoming event, "2026 season" language, a current vendor application.
     "Every Saturday" with no date anywhere doesn't count.
   - **Live website**, if it has one: loads and reads like the market's own
     current content, not a parked domain or placeholder. No website at all
     means skip this check, not fail it.
   - **Corroboration.** Two independent sources agree it's operating (not
     copies of each other). A market's own Facebook and Instagram together
     count as one source.

   Don't count followers or likes: a defunct page keeps them forever. An
   explicit "closed", "discontinued" or "permanently closed" overrides the
   score: treat it as dead.

   | Score | Meaning | What to do |
   |---|---|---|
   | 4/4 | Confident it's active | Include, `<liveness_score>4</liveness_score>` |
   | 2-3/4 | Probably active, some gaps | Include, and add a one-line `Liveness: n/4 — <what's missing>` to `<notes>` |
   | 0-1/4, or explicit closure | Likely defunct | Run step 7 first. If it still lands here: a market **not** in the baseline gets no entry, just a summary line ("found mentions of X but it looks defunct: `<why>`"). A baseline market **is** written with the low score, since leaving the old score on file would mislead. **The import marks any market scoring 1 or below inactive**, which hides it from the admin panel's default list (it stays findable via the Status filter). A later higher score never re-activates it |

   The score is a real field (`Market::liveness_score`, filterable on the
   Index page): it's how low-confidence markets get found for a re-check.
   Leave `<liveness_checked_at>` out; it defaults to the import date.

   **Score each schedule too; it's required.** The market score asks "does
   this market still run?", and each `<schedule>`'s `<liveness_score>` asks
   "is *this* schedule current?" (a live market can have a Christmas fair
   that stopped years ago). Apply the same four checks to the schedule. A
   season that hasn't started can't show in-season activity, so 3/4 with a
   note is the honest ceiling on published dates alone. A schedule can't
   score higher than its market's evidence. The import skips any schedule
   without a valid score, so never omit or guess one.

   **Compare against the baseline.** Match a finding to a baseline row by
   name (case-insensitive, ignoring minor punctuation) within the same city;
   don't fuzzy-match across cities. For any match, **build the entry from
   the baseline row, every field carried forward, and overwrite only what
   you found new**: an omitted field wipes the stored value on re-import
   (see the schema reference). Then classify it:

   - **Unchanged.** Score within 1 point (normal noise between passes) and
     no field differs. Still write it: re-importing bumps
     `liveness_checked_at`, recording that someone looked again. Counted in
     the summary, not named.
   - **Changed.** Score moved more than 1 point, went active↔defunct, a
     field differs, or a schedule appeared, disappeared or moved (match
     schedules by label). Named in the summary with old → new.
   - **New.** No baseline match; steps 5-6 as normal.

7. **Second pass on anything that scored 2 or below**, market or single
   schedule. A low score can just mean the first pass looked in the wrong
   places. For a schedule, search for that specific event (e.g. "Christmas
   Craft Fair Salmon Arm 2025"), not the whole market. Roughly three to six
   targeted searches per item, across the kinds of source that fit:

   - **Local news and community papers.** The market's name plus "closing",
     "cancelled", "final season", "new location", "returns". For Interior
     scopes, the step 3 standing sources, read directly rather than via
     web search.
   - **Municipal and venue sources.** The city's events calendar, parks and
     rec, council agendas or minutes, the venue's own page.
   - **Current-year program lists.** BC Farmers' Market Nutrition Coupon
     Program participants, BC Association of Farmers' Markets members, the
     BC Farmers' Market Trail, regional tourism sites. Check their dates;
     tourism listings lag.
   - **The vendor side.** Vendors' own pages or posts saying they'll be at
     the market this year, or a vendor application with current dates.
   - **Maps and review sites.** Google Business Profile ("permanently
     closed", newest review dates), Tripadvisor, Yelp.
   - **Web archive.** When the market's site was last actually changed, not
     just whether it loads.
   - **Facebook or Instagram**, if a page, event or vendor post turns up,
     exactly as in step 4, added to the one session.

   Only dated evidence counts; an undated listing or a copied directory
   entry proves nothing. Outcomes:

   - **Current evidence** (this year, or within 12 months): re-score with
     it and say what changed the score.
   - **Explicit closure**: dead, per step 6's override.
   - **Nothing anywhere**: if you covered at least three different *kinds*
     of source and found no dated activity in the last 12 months, score it
     1. That deactivates it on import, so hold to that bar. Fewer than three
     kinds, or anything inconclusive: leave the score and say what's
     unchecked.

   Append `Second pass (<today's date>): searched <kinds of source>; <what
   was found, or that nothing was>.` to the market's (or schedule's)
   `<notes>`, so a low score visibly means "checked twice". Silence is
   evidence, not proof: write "no current evidence found", never "closed",
   unless something says so. Never contact anyone; if it's still uncertain,
   list the manager's phone or email in the summary for the user to follow
   up.

8. **Flag, don't resolve, other uncertainty.** Sources disagreeing on
   hours, fees or location, or a possible duplicate of a known market: say
   so in `<notes>` and the summary, and let the user decide. A baseline
   market this pass simply didn't surface is *not* evidence it closed:
   don't touch its record or score; list it as "not reconfirmed this pass".

9. **Write, import, and report a diff.** Follow "Saving and importing" in
   the schema reference: save as `database/markets/YYYY-MM-DD-NN-<scope>.xml`,
   dry run `php artisan market:import-pending --dry-run` and fix what it
   reports, import, and commit. Give the user the path, the import summary,
   and:

   - **New markets found**: how many, scores below 4/4, and anything
     excluded as defunct or not a vendor market, with why.
   - **Changed**: each named, old → new.
   - **Reconfirmed, no change**: a count.
   - **Schedules not fully structured**: each schedule missing
     `<weekdays>` or times, or a `biweekly` with no anchor date, and why.
     These don't show on the calendar until filled in.
   - **Not reconfirmed this pass**: baseline markets this search didn't
     surface.
   - **Second-pass results**: what was searched, what turned up, the score
     change (e.g. 2 → 1), which markets will be **marked inactive on
     import**, and anything worth a manual phone call.

   On a first run the baseline is empty, so this is just "found N markets".

## One-time, pop-up and past-event markets

(For the place-less "see what's new on social media" version, with no city
or region named, use `scan-bc-market-socials`.)

Holiday markets, craft fairs, harvest and Halloween markets and street
festivals mostly live in event listings and community groups, and repeat
annually. Capture past ones too (from 2023 on): last year's Christmas market
is the best predictor of next year's.

- **Where to look.** Facebook event search
  (`facebook.com/events/search/?q=<town> market`) and the "suggested events"
  sidebar on any event page; the search box of a **public** local-events
  group (`.../groups/<id>/search/?q=market`); venues that host markets
  (malls, wineries, farms, rec centres); the downtown association's and
  organizers' sites; local news, especially council road-closure stories
  (exact dates); and regional roundup guides, the best source for *past*
  editions. Open Instagram for an organizer's known account, not hashtags
  (images, no readable text). All of it goes through Browserless as in
  step 4; when event or group search hits a login wall, note it and use the
  other sources rather than retrying.
- **Groups: public only, read-only.** Never join, request access or post.
  Note private groups' names in the summary.
- **Never accept a date without its year.** A Facebook event page's header
  carries it. ("Dec 7-8" that looked upcoming turned out to be 2018.) No
  year found: leave the dates out and say so.
- **Open the source; don't trust a search summary.** Summaries here have
  merged two businesses and returned Kelowna and Armstrong events for a
  Salmon Arm search. Confirm town and name on the page itself. Import only
  the requested area (the town plus immediate neighbours); list
  nearby-but-outside finds in the summary.
- **How to model them.** A special day of an existing market (Apple Fest at
  the weekly downtown market) is a `one_time` schedule *on that market*;
  schedules are replaced wholesale on import, so re-supply its existing ones
  unchanged. A standalone event is its own market with one `one_time`
  schedule (`start_date` = `end_date` for one day) for the next or most
  recent edition, with earlier editions and their years in `<notes>`. Say
  when an event is borderline (a home show with a marketplace, an
  agricultural fair) so the user can drop it.
- **Scoring a one-time event** asks "is it real and does it recur?": an
  upcoming edition confirmed by two independent sources, or one that already
  ran this year, is 4; one source (the organizer's page and event count as
  one) is 3; last seen two or more years ago is 2, noting what's unchecked.
  A brand-new event has no past edition; say so.
- **Past markets that look dead are still written when the user asked for
  past markets.** Unlike step 6's default, write it with its low score: at 1
  or below it imports inactive, on file but hidden. Say so in its notes and
  the summary so the user can drop it.
- **Flag likely successors, don't merge them.** A new page at the same
  location as a silent old one (a "Community Market Events" page in the same
  postal code as a market quiet since 2024) gets a note on both records;
  only the user or the organizer can say they're the same.
