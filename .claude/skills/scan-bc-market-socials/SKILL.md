---
name: scan-bc-market-socials
description: Sweeps Facebook (public groups and event search), Instagram, and Threads broadly for British Columbia market chatter when the request has no specific city or region attached -- "see what's new on social media", "check Facebook for any new markets lately", "scan socials for market announcements" -- and produces an import-ready XML file for the cultpantry admin panel's Farmer's Markets module (modules-market). If the user names a specific city or region, use find-bc-markets instead -- it already covers Facebook/Instagram sourcing and one-off/pop-up-event discovery for a named place, including its own "One-time, pop-up and past-event markets" section; this skill exists only for the open-ended, place-less version of that ask. Not for re-checking markets already on file (refresh-bc-markets).
---

# Scan BC Market Socials

For the specific shape of request that neither other market skill fits: no
city or region named, just "is anything new showing up on social media."
`find-bc-markets` already does the Facebook/Instagram/event-search legwork
well -- including one-off and pop-up markets, in its own "One-time, pop-up
and past-event markets" section -- but it expects a place to start from. This
skill exists only to handle the version of the same ask where there isn't
one yet.

**If the user already named a place, don't use this skill -- use
`find-bc-markets`.** Route back to it and follow its "One-time, pop-up and
past-event markets" section for the social-sourcing half of that request.

Same ground rules as both other market skills: this is a research aid, not
an importer (never touches the database), and Facebook/Instagram are read
through the user's own logged-in browser session via the Chrome extension
tools, never `WebFetch` or a raw request. **Read
`../find-bc-markets/references/market-xml-schema.md` before writing any
output** -- same schema, same controlled `region`/`frequency` lists, same
carry-forward rule for anything that matches a baseline market.

## Workflow

1. **Pick a scope to actually sweep.** "All of BC" isn't a pass, it's a
   promise to do this forever -- same reasoning as `find-bc-markets` step 1.
   Ask the user to pick a region (or offer to go region by region), or, if
   they'd rather you just start somewhere, default to the region(s) with the
   oldest `liveness_checked_at` dates in the baseline (see step 2) -- the
   ones least recently looked at either way.

2. **Get the baseline for that scope.** Follow
   `../find-bc-markets/references/getting-the-baseline.md`, same as the
   other two skills, keeping the entries matching the chosen scope with
   every field intact. This is what tells you whether something you find is
   genuinely new or a special event belonging to a market already on file,
   and gives you existing markets' own Facebook/Instagram links as a
   starting point for step 3.

3. **Sweep the social channels for that scope**, following
   `find-bc-markets`' "One-time, pop-up and past-event markets" section for
   *where* to look (Facebook event search, public groups' own search boxes,
   venue and organizer pages, light-touch Instagram) and its rules for how
   (public groups only, read-only, never join/request/post; never accept a
   date without its year; open the source rather than trusting a search
   summary). Run that same sweep across the whole scope's worth of towns and
   groups, not just one market's own channels -- that breadth is this
   skill's actual job. Use `WebSearch` for corroboration once something
   surfaces, not as the primary discovery method.

   **Check the user's own joined-groups list before searching Facebook's
   public group search.** Facebook's own group search ranks and surfaces
   groups the user hasn't joined far more reliably than ones they already
   belong to -- a group the user is an active member of can simply not
   appear for a query that names it almost exactly. Visit
   `facebook.com/groups/joins/` first (their "Groups you've joined" list)
   and scan it for anything scope-relevant before falling back to a public
   search. This is often the single highest-yield step in the whole sweep:
   a user who's been running this market list for a while is usually
   already a member of the exact regional buy-and-sell and market groups
   that matter, and public search alone will miss them. See "Known groups
   worth checking every run" below for ones already confirmed this way.

4. **Decide new market vs. special event of an existing one**, per
   `find-bc-markets`' "How to model them" bullet in that same section: a
   special day of a market already in the baseline is a `one_time` schedule
   *on that market* (re-supply its existing schedules unchanged alongside
   it); a standalone market with no baseline match is its own entry with one
   `one_time` schedule for the next or most recent edition.

5. **Run the vendor-market screen on every candidate.** Read
   `../find-bc-markets/references/vendor-market-screen.md` and follow its
   decision rule before scoring anything -- some "market"-branded listings
   turn out to be a single business's storefront or retail event, and those
   get excluded (or flagged, if genuinely ambiguous) regardless of how
   active they look.

6. **Score liveness** with `find-bc-markets` step 6's rubric and its
   "Scoring a one-time event" variant in the pop-up section for anything
   without a recurring schedule. Don't reinvent the rubric here.

7. **Write the XML and report as a diff.** Save to
   `~/Documents/market-research/` (create it if needed), named
   `social-scan-<scope>-<YYYY-MM-DD>.xml`. Structure the summary the same
   way `find-bc-markets` step 9 does, plus:

   - **Flagged as possible non-vendor-market** -- added to the XML anyway
     per the screen, with the specific red flag named.
   - **Excluded -- not an independent-vendor market** -- named in the
     summary only, not written to the XML, with the one-line reason.
   - **Groups found but private** -- named only, so the user can request
     access themselves if they want.

   Same rule as both other skills: **don't import the file yourself.**

## Known groups worth checking every run

Groups confirmed (by finding them in the user's own joined-groups list --
see step 3) to be worth a standing check for the matching scope, rather than
rediscovering them each time via public search:

- **Okanagan and Shuswap (either scope):** "Markets / Flea Markets /
  Farmers Markets - Okanagan and Shuswap BC" --
  `facebook.com/groups/2011732815631813/`. A public group, 1.5K members,
  spanning both regions. Its own Events tab under-reports what's actually
  posted -- also run the group's own search box for `market` and `vendor`,
  since most vendor-call posts aren't tagged as formal Events.

Add a region to this list the same way this one was found: check
`facebook.com/groups/joins/` during a run for that region, and if a
scope-relevant group turns up, record its name and URL here so the next run
starts from it directly instead of re-searching. Note anything about a
group's own quirks (an under-used Events tab, a particularly active
search-worthy topic) the way the Okanagan/Shuswap entry above does, since
that's what makes the entry actually useful next time rather than just a
bookmark.

## A note on scale and pacing

Like `refresh-bc-markets`, this leans on the user's real, logged-in
Facebook/Instagram sessions across several groups and pages per scope, which
adds up fast. Take a region in batches if it has many active community
groups rather than trying to exhaust all of them in one pass, and check in
with the user between batches the same way `refresh-bc-markets` step 2 does.
