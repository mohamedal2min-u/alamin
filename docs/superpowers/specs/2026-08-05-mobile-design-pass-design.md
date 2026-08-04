# Mobile design pass across the farm app

## Purpose

The inventory page (`frontend/src/app/(farm)/inventory/page.tsx`) recently got a
round of phone-layout fixes: the page content had zero horizontal padding
(touching the screen edges), a KPI grid left an orphaned card, and two tables
(movements, low-stock alerts) forced horizontal scroll or clipped content on
narrow screens instead of falling back to a card layout.

The user asked for the same treatment across the rest of the app — improving
how the app *looks and fits* on a phone screen. This is explicitly scoped to
layout/design, not the Capacitor networking model (the app intentionally loads
the live site in-app) and not backend/query performance work.

## Scope

**In scope** — regular-user pages rendered through the mobile bottom-nav
layout branch (`(farm)/layout.tsx`, non-`super_admin` role):

1. **Shared layout padding fix.** `(farm)/layout.tsx:83` wraps all page content
   in `<div className="mx-auto max-w-2xl">` with no horizontal padding, while
   the header and bottom nav both carry `px-2`/`px-2 sm:px-4`. Fix once at this
   shared layer (`px-3 sm:px-4`, matching the inventory precedent) so every
   regular-user page gets consistent edge spacing in one change. Remove the
   now-redundant `px-3 sm:px-4` added directly to `inventory/page.tsx` so it
   isn't double-padded.

2. **Sales page** (`sales/page.tsx:115`) — wide table with `overflow-x-auto`
   and no mobile fallback. Add a card list for `<sm` and keep the table for
   `sm:` and up, following the exact pattern already used in
   `my-wallet/page.tsx` (card list below `sm`, table `hidden sm:block` above).

3. **Partners page** (`partners/page.tsx:167`) — same issue, same fix pattern.

4. **Spot-check pass**: `dashboard/page.tsx`, `expenses/page.tsx`,
   `flocks/page.tsx` (+ `flocks/[id]/page.tsx`), `workers/page.tsx`. Look for
   the same class of concrete problems found in inventory — grids that leave
   an orphaned card on a 2-column mobile layout, touch targets under ~40px,
   tables/wide content without a card fallback, content that assumes a wider
   viewport than a phone provides. Fix only what's actually broken; no
   speculative rewrites or redesigns of pages that already work on mobile
   (e.g. `my-wallet` is already correct and untouched).

**Out of scope:**
- `admin/*` pages (`admin/farms`, `admin/registration-requests`) — these
  render through the separate desktop-sidebar layout branch for
  `super_admin` only, not the mobile bottom-nav shell.
- Capacitor/network loading behavior, backend pagination, `next/image`
  adoption, and query-efficiency work — these were surfaced during recon as
  separate, independent initiatives and are explicitly deferred.
- Any visual redesign beyond what's needed for phone-width correctness (no
  new color themes, no new components beyond the card/table pattern already
  established).

## Approach

Reuse the pattern already validated on the inventory page and already present
in `my-wallet/page.tsx`:

- A `<div className="hidden sm:block">` (or `md:block`, matched to where the
  table's `min-w` actually breaks) wrapping the existing table, unchanged.
- A sibling `<div className="... sm:hidden">` (or `md:hidden`) rendering a
  compact card per row: primary identifier + status/direction badge on top,
  key numeric fields below, secondary metadata (tags/notes) at the bottom —
  same visual language (rounded-2xl, `--shadow-card`, slate/emerald palette)
  already used throughout the app.
- No new shared components are introduced; each page keeps its card markup
  inline, consistent with how the codebase currently handles per-page UI
  (no shared `<Table>` abstraction exists to hook into).

## Validation

- `npx tsc --noEmit` after each file's edits (already the standard for this
  codebase, no linked test suite for these pages).
- No live/browser verification is planned (same constraint as the inventory
  work — would require running the Laravel backend + seeded farm data); this
  will be called out explicitly when reporting completion.
