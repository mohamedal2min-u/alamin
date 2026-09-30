# URME Supplier Sync – tests

End-to-end tests that run the real plugin inside a WordPress + WooCommerce install
against a generated supplier feed. Not part of the plugin zip.

**Use a throwaway test site only.** The suite deletes all products and the plugin's data.

| File | What it covers |
|---|---|
| `scenarios.php` | Feed parsing and every feed-failure mode, stock and cost sync, linking, brand allowlist, "In URME" match status, locking, cron, exchange-rate fallbacks, protected fields. Includes `local-first.php`, `fulfillment-review.php`, `new-products.php`, `price-hint.php`, `brand-sync.php`, `catalog-actions.php`, `product-admin.php`, `ajax-local.php`, `bulk-fulfillment.php`, `gift-wrap.php` and `two-state.php` at the end. |
| `local-first.php` | The 1.1.0 Local first ledger on legacy links (such links are removed once in 1.5.2; the engine is kept): 1.1.0 Local first → Supplier: local stock kept, handover only in safe runs, cancellations, refunds with restock, returns after the switch (stay Dropshipping since 1.5.2), admin line edits, brand off, backorders, and interrupted bookings (exceptions, a real process kill via `crash-child.php`, missed hooks, concurrent changes). |
| `fulfillment-review.php` | 1.1.0 admin-only features: URME Lager / Dropshipping / Mixed labels (frozen at sale time, legacy orders, list column and filter; the orders list loads them in one query per page, never per row) and price reviews (one per switch, no duplicates, atomic with the switch), plus privacy checks on customer emails, My Account, thank-you page, order meta/notes, REST and Store API, structured data. |
| `new-products.php` | 1.1.0 NEW supplier product badge: 4-day window from first_seen, unchanged by refreshes, stock/cost changes, missing/return and brand toggles; filter and count; nothing NEW after a first import or an upgrade; nothing on the storefront. |
| `price-hint.php` | 1.2.0 admin-only selling price hint: the formula (VAT-0% cost + 12 EUR, 10% coupon, 25% VAT, 5% Klarna fee on the paid amount, ≥ 500 SEK profit, rounded up to 10 SEK), missing price or rate, settings changes, prices never changed, nothing customer-facing, no per-row queries. |
| `brand-sync.php` | Regression (production SKU 1513905): an enabled brand survives settings saves, also from a page opened before the brand was enabled; a linked Supplier-now watch gets stock and supplier cost whichever of the two is stale; disabled brand changes nothing; price-hint +12 EUR never in the synced cost; selling prices untouched; all last_* fields set after a successful sync; cron and "Sync now" behave the same; Paused keeps the current stock and cost (also on a cancelled/refunded local unit) and the link, and resumes with "Supplier now". |
| `catalog-actions.php` | Brand filter lists only brands enabled for sync (plus a disabled brand filtered on from a link, marked "– sync off"; "All brands" still lists every watch). Supplier catalog URME stock column (in stock / 0 / not managed / backorders, exact variation), "Dropshipping" at stock 0 (supplier stock and cost at once), stock above 0 stays URME Lager (no button, refused, never automatic when sold to 0), "URME Lager" and "Sync now" on Dropshipping rows, Pause/Resume refused, refusals (brand off, Not in URME, Needs review, backorders, stale stock, not managed), URME stock and "Ready for supplier sync" filters, prices untouched, constant queries for 20/50/100 rows. |
| `product-admin.php` | Fulfillment badge (Dropshipping / Local first (N) / Paused / URME Lager) on WooCommerce > Products and in the catalog; manual sale price editor (only the sale price changes: regular, stock, COGS, mode and link kept; clear, invalid, above regular, variation only, paused, refusals, no HTTP); cron never writes prices; no per-row queries for 20/50/100 rows. |
| `ajax-local.php` | AJAX row actions (sale price, Dropshipping, old "Use Local first", Sync now, Resume): nonce + manage_woocommerce, same server logic, re-rendered row, double clicks, no feed request; local URME stock always has priority (Dropshipping refused at stock 1, 2 or 3, no confirmation override, no automatic switch at 0); paused, brand off, ambiguous and deleted products; no per-row queries. |
| `bulk-fulfillment.php` | "Select checked for sync" per watch: stock 0 → Dropshipping, stock above 0 → not selected (stays URME Lager, stock and COGS kept), Needs review / no URME product / brand off rejected without stopping the others, prices never written, no bypass; Dropshipping → URME Lager (link removed, stock 0) and no automatic switch back; turning a brand on again removes its watches with URME stock from supplier sync (listed; no N+1). Fulfillment filter on WooCommerce > Products (URME Lager / Dropshipping): each state equals the badge buckets, combined with stock status, brand, category, search, product type and sorting; counts, brand sync, paused, variable products, no duplicates, found_posts; constant queries, no HTTP. |
| `gift-wrap.php` | 1.4.1 ThemeComplete gift wrap (Presentinslagning), without ThemeComplete installed: its `wc_epo_disable` filter and the add-to-cart validation are called as ThemeComplete and WooCommerce call them. Dropshipping → options off; Local first / URME Lager / Paused / brand sync off → unchanged; crafted Dropshipping + tmcp_* request rejected, without gift allowed; variations decided by the selected variation; Local first ↔ Supplier and brand/pause changes follow and clean only the affected product pages; a stand-in global form unchanged; no HTTP; one state query per product per request. |
| `two-state.php` | Two Fulfillment states only (URME Lager / Dropshipping): Dropshipping refused at URME stock 1 or 3 (nothing changed); sold or set to 0 → stays URME Lager until "Dropshipping" is clicked (supplier stock and cost at once); Dropshipping → URME Lager removes the watch from supplier sync with stock 0 / out of stock (supplier quantity never kept, the sync writes nothing, entering stock keeps it URME Lager); no Selected watches page; Pause/Resume/Local first refused; the one-time 1.5.2 cleanup (legacy Local first, paused and product-less links removed, stock/cost/prices untouched; Dropshipping and brand-off links kept). |
| `migration-populate.php`, `migration-verify.php`, `run-migration.sh` | Upgrade 1.0.0 → 1.1.0: catalog, links, paused state, match data and settings are kept, and nothing changes behaviour. |

Use MySQL/MariaDB for results that match production (row locks, InnoDB transactions).

## Setup

1. Test site with WooCommerce and this plugin active. Copy `mu-plugin/urme-test.php`
   to `wp-content/mu-plugins/`. It allows the local feed server and fakes the ECB and
   Riksbank rate responses.
2. Start the fake supplier feed server from this folder:

       mkdir -p feedsrv && echo ok > feedsrv/mode.txt
       python3 genfeed.py 3000 feedsrv/current.xml
       php -S 127.0.0.1:8090 feed-router.php

## Run

    wp eval-file /path/to/tests/urme-supplier-sync/scenarios.php

It ends with `RESULT: N passed, 0 failed`.

Migration test (needs a separate throwaway site with WooCommerce active and a copy of the
1.0.0 plugin, e.g. `git archive 40269ad wordpress-plugins/urme-supplier-sync | tar -x`):

    ./run-migration.sh /path/to/wordpress /path/to/plugin-1.0.0 /path/to/plugin-1.1.0

It ends with `MIGRATION RESULT: N passed, 0 failed`.
