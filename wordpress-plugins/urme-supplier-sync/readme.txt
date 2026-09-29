=== URME Supplier Sync ===
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.1
Stable tag: 1.0.0

Browse the supplier's watch catalog and keep stock and cost price in sync for the WooCommerce products you explicitly select.

== What it does ==

* Downloads the supplier XML feed in the background (hourly) and keeps a local copy of the
  WATCH category only. Browsing and searching never downloads the feed.
* WooCommerce > Supplier Sync shows every supplier watch with image (loaded from the supplier,
  nothing is added to the Media Library), brand, model (PRODUCTNO), EAN (ITEM_ID), supplier stock,
  cost in EUR and cost in SEK.
* "In URME" column: whether the watch already exists in the store, found by SKU/PRODUCTNO and
  EAN/ITEM_ID:
  - Exists in URME (one confident match, with a link to the product)
  - Not in URME
  - Needs review (several products could match)
  - Manually linked
  This is information only. Nothing is synced because a product exists.
* A product is synced only when all of these are true:
  1. its supplier CATEGORY is WATCH,
  2. its brand (MANUFACTURER) is enabled in Settings > Brands enabled for sync,
  3. you selected it and it is linked to a WooCommerce product.
  Turning a brand off keeps its selections and links; its products are just left alone.
* For those products only:
  - Stock: WooCommerce stock = supplier STOCK. 0 = out of stock, above 0 = in stock again.
  - Cost price: supplier PURCHASE_PRICE (EUR) x EUR/SEK rate, written to the store's cost field.
    The original EUR cost is kept as hidden product meta `_urme_supplier_cost_eur`.
* Never changes regular price, sale price, title, descriptions, images, categories, attributes
  or SEO fields.

== Installation ==

1. Plugins > Add New > Upload Plugin, choose urme-supplier-sync-1.0.0.zip, Install, Activate.
2. Open WooCommerce > Supplier Sync and click "Sync now" once to fill the catalog
   (after that it refreshes by itself every hour).
3. Status & log > "Store setup (detected)": check where cost price will be written.
4. Settings > "Brands enabled for sync": tick the brands you sell. (None are enabled at first.)
5. Supplier catalog: tick watches > "Select checked for sync". Watches with a unique SKU/EAN match
   are linked automatically; others are linked under "Selected watches" with the product search.

== Cost price field ==

The plugin looks at the store and uses the cost field that is already in use ("Automatic"):

* WooCommerce's built-in Cost of Goods Sold (WooCommerce 9.5+, including 11.x) when it is enabled,
* or a cost plugin's field: _wc_cog_cost (Woo Cost of Goods), _alg_wc_cog_cost (WPFactory),
  yith_cog_cost, _wcj_purchase_price, _purchase_price, _cost_price, _op_cost_price,
* whichever currently holds values for the most products.

Other product meta that looks like a cost field is listed on the Status tab and can be chosen in
Settings as a custom meta key. If no cost field exists, cost sync pauses and says so. Enable
WooCommerce > Settings > Advanced > Features > Cost of Goods Sold, or pick a field.

For a variation whose WooCommerce cost is set to "add to the parent's cost", the plugin does not
write the cost (it would be counted twice). It shows an error on that watch instead.

== Exchange rate ==

EUR/SEK comes from the European Central Bank's daily reference rate, with Sveriges Riksbank as a
fallback. It is fetched at most every 12 hours. A new rate that differs more than 15% from the
previous one is rejected. If both sources fail, the last rate is kept. A manual override is
available in Settings.

== Safety ==

* Feed download or XML error, empty feed, no WATCH items, or a feed with far fewer watches than
  last time (default: under 50%) means nothing is changed: no stock set to 0, no cost overwritten.
  After an expected big drop, use "Accept current feed" on the Status tab.
* Products are not updated from a catalog older than 3 hours (feed failing), configurable.
* A watch that disappears from the feed is flagged, and its product is left untouched until it
  comes back.
* Only one sync runs at a time (database lock; a lock older than 30 minutes is taken over).
* Values are only written when they differ from what is already stored.

== Scheduling ==

Uses WP-Cron (hook `urme_ss_hourly`). On a low-traffic site add a real cron job, e.g.
`*/15 * * * * curl -s https://urme.se/wp-cron.php?doing_wp_cron > /dev/null`
or `wp cron event run --due-now` from the server.

== Uninstall ==

Deleting the plugin removes its own tables and options. WooCommerce products are not changed.
