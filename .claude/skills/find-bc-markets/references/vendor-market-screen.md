# The independent-vendor-market screen

Shared by `find-bc-markets`, `refresh-bc-markets`, and `scan-bc-market-socials`
-- this is one rule, written once, not three slightly different copies of it.
If it ever changes, it changes here and all three skills pick it up.

## Why this exists

A "market" name gets used loosely online. Some listings are genuinely what
this module exists for -- an outdoor or indoor event where independent
vendors set up their own stalls or tables. Others borrow the word for
something categorically different: a single retail business's storefront,
sale, or pop-up shop. Screen every candidate against this before writing it
into an XML entry, independent of its liveness score -- a listing can be
perfectly "live" and still not belong in this module.

This applies whenever you're deciding whether something is (or still is) an
independent-vendor market: a brand-new find, a special one-time event, or an
existing market you're re-checking that reads like it's changed shape.

## Signals it's a genuine independent-vendor market

The presumption to confirm:

- Vendor language -- "vendors wanted", vendor applications, vendor fees,
  "40+ local makers/artisans", vendor booths, tables, or stalls.
- Multiple distinct business or maker names named as participants, not one
  brand throughout.
- A venue description implying multiple stalls (a parking lot, hall, park,
  or field) rather than a single shop floor.

## Red flags it's not

Don't presume past these -- go verify:

- "Storefront", "our shop", "brick-and-mortar location", "retail location",
  "in-store only", "shop our collection".
- Only one business or brand named throughout, with no vendor list and no
  vendor application mentioned anywhere.
- "Warehouse sale", "flagship store", or a "grand opening" for a single
  retailer.
- A shopping mall's general seasonal décor or promotion with no named
  outside vendors.
- A single maker's "pop-up shop" of their own product only -- not a market
  hosting other vendors.

## A storefront host doesn't disqualify the event

A market genuinely hosted at or by a brick-and-mortar business is still a
market -- screen the event, not the host. A farm stand that also runs a
Saturday vendor market in its yard, or a brewery hosting a monthly craft
market in its parking lot, is a real market with a storefront business as
its venue. Record that business in `<sponsor>` per the existing schema rule
(when its name differs from the market's own name) and include the entry
normally.

## Decision, once you've looked

- **Confirmed genuine, or reasonably so** (red flags absent, or clearly
  explained by the "hosted at" case above) -- proceed normally: score it and
  write the entry, no special note needed.
- **Red flags present but not conclusive** -- one ambiguous post, a market
  that also happens to have a retail wing alongside real vendor booths, no
  clear vendor list either way. Still score and write the entry, but prepend
  a note: `Vendor-market check: UNCERTAIN -- <what the red flag was>. Verify
  before treating as a normal independent-vendor market.` Call it out by
  name in your summary under a "Flagged as possible non-vendor-market"
  heading (or equivalent) so the user reviews it before importing, rather
  than it blending in with confirmed finds.
- **Certain it's not** -- explicit confirmation (an "about" page that says
  it's the shop's own in-store sale, not open to other vendors; no vendor
  language anywhere despite clear "market" branding; a single retailer's
  seasonal promotion with nothing else pointing to a hosted market). Don't
  write an entry at all, and don't score it. Name it in the summary under an
  "Excluded -- not an independent-vendor market" heading with the one-line
  reason -- the same pattern used elsewhere in these skills for excluding a
  confirmed-defunct market instead of writing a doomed entry for it.

Run this screen before liveness scoring, not after -- a candidate that fails
the "certain it's not" bar never gets a liveness score at all, since scoring
implies it's a market this module should track.

## Re-checking a market already on file

`refresh-bc-markets` runs into a different shape of this problem: a market
that was a genuine vendor market when first added but now reads like it's
drifted toward storefront-only (vendor booths dropped, page now only posts
about in-store retail). Don't silently deactivate or remove it on that basis
alone -- a liveness score change and a format change are different findings.
Flag it in the market's `<notes>` with the same `Vendor-market check:` note
format above, and call it out in the run's summary so the user makes the
call on whether it still belongs in the module at all.
