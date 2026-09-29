# URME Supplier Sync – scenario tests

End-to-end tests that run the real plugin inside a WordPress + WooCommerce install
against a generated supplier feed. Not part of the plugin zip.

They cover feed parsing and every feed-failure mode, stock and cost sync, linking,
brand allowlist, the "In URME" match status, locking, cron, exchange-rate fallbacks,
and that protected product fields never change.

**Use a throwaway test site only.** The suite deletes all products and the plugin's data.

## Run

1. Test site with WooCommerce and this plugin active. Copy `mu-plugin/urme-test.php`
   to `wp-content/mu-plugins/`. It allows the local feed server and fakes the ECB and
   Riksbank rate responses.
2. Start the fake supplier feed server from this folder:

       mkdir -p feedsrv && echo ok > feedsrv/mode.txt
       python3 genfeed.py 3000 feedsrv/current.xml
       php -S 127.0.0.1:8090 feed-router.php

3. Run the suite from the WordPress root:

       wp eval-file /path/to/tests/urme-supplier-sync/scenarios.php

   It ends with `RESULT: N passed, 0 failed`.
