# URME Supplier Sync – tests

End-to-end tests that run the real plugin inside a WordPress + WooCommerce install
against a generated supplier feed. Not part of the plugin zip.

**Use a throwaway test site only.** The suite deletes all products and the plugin's data.

| File | What it covers |
|---|---|
| `scenarios.php` | Feed parsing and every feed-failure mode, stock and cost sync, linking, brand allowlist, "In URME" match status, locking, cron, exchange-rate fallbacks, protected fields. Includes `local-first.php` at the end. |
| `local-first.php` | 1.1.0 Local first → Supplier: local stock kept, handover only in safe runs, cancellations, refunds with restock, returns after the switch, admin line edits, brand off, backorders, and interrupted bookings (exceptions, a real process kill via `crash-child.php`, missed hooks, concurrent changes). |
| `fulfillment-review.php` | 1.1.0 admin-only features: URME Lager / Dropshipping / Mixed labels (frozen at sale time, legacy orders, list column and filter) and price reviews (one per switch, no duplicates, atomic with the switch), plus privacy checks on customer emails, My Account, thank-you page, order meta/notes, REST and Store API, structured data. |
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
