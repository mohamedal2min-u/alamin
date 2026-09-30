=== URME Supplier Sync ===
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.1
Stable tag: 1.2.0

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

1. Plugins > Add New > Upload Plugin, choose urme-supplier-sync-1.2.0.zip, Install, Activate.
2. Open WooCommerce > Supplier Sync and click "Sync now" once to fill the catalog
   (after that it refreshes by itself every hour).
3. Status & log > "Store setup (detected)": check where cost price will be written.
4. Settings > "Brands enabled for sync": tick the brands you sell. (None are enabled at first.)
5. Supplier catalog: tick watches > "Select checked for sync". Watches with a unique SKU/EAN match
   are linked automatically; others are linked under "Selected watches" with the product search.

== Selling price hint (admin only) ==

Supplier catalog has a "Price hint" column (and Selected watches a short version) with a
suggested selling price for each supplier watch:

  Cost SEK        = (PURCHASE_PRICE EUR + 12 EUR) x EUR/SEK   (PURCHASE_PRICE is VAT 0%)
  Customer pays   = price x 0.90                               (10% coupon allowance)
  Excl. VAT       = customer pays / 1.25                       (25% Swedish VAT)
  Klarna fee      = customer pays x 0.05
  Profit          = excl. VAT - Klarna fee - cost SEK
  Suggested price = lowest price with profit >= 500 SEK, i.e. (cost SEK + 500) / 0.675,
                    rounded UP to the next 10 SEK (never down)

Example: 176 EUR at 11.321 -> cost 2,128 kr -> suggested 3,900 kr (customer pays 3,510 kr,
Klarna 176 kr, estimated profit 504 kr).

Extra supplier cost, coupon, fee, VAT, target profit and rounding step are in Settings >
Selling price hint and apply on the next page load. It uses the rate already in use (automatic
or manual override) and never makes a request. Without a PURCHASE_PRICE or a rate it shows
"Price hint unavailable". It is a hint only: selling prices, sale prices, coupons and products
are never changed, and nothing is shown to customers.

== NEW supplier products (admin only) ==

Watches the supplier adds to the feed get a NEW badge in Supplier Sync > Supplier catalog for
4 full days after URME first saw them ("Added today", "Added 1 day ago", ...), plus a
"New products (N)" filter and a count on the Supplier watches card. Based on the catalog's
first_seen date, which refreshes, stock/cost changes, brand toggles and a temporary absence
from the feed never reset. The first import and the upgrade to 1.1.0 mark nothing as NEW.
Nothing is selected, linked or changed in WooCommerce because a watch is new.

== Local first → Supplier automatically ==

Per selected watch, under Selected watches > Mode:

* Supplier now (default): stock and cost follow the supplier.
* Local first: you own N units. They are sold first; supplier stock and cost are shown
  but never written while local units remain. When the last local unit is sold, the next
  safe sync (fresh, successful feed) switches the watch to Supplier now and syncs supplier
  stock and EUR -> SEK cost. Until then the product is out of stock.
* Paused: nothing is synced; Local first state is kept.

Every order line of a linked product is booked in a ledger table (urme_ss_alloc) as local or
supplier units, in one database transaction together with the local stock count, so a crash
can never count a unit twice. Cancellations, refunds with restock and admin quantity edits
return units to local first. A returned local unit on a watch that is already on Supplier now
switches it back to Local first (WooCommerce stock = local units, cost = saved local cost).
Anything that cannot be booked is logged and retried by the next sync, and that watch is not
switched until it is booked.

Local first cannot be enabled while backorders are allowed on the product (WooCommerce could
otherwise sell supplier units before the local ones). If backorders are turned on later, the
watch is put on hold and flagged until they are turned off again.

== Fulfillment source (admin only) ==

Each order line of a supplier-linked watch shows, on the order edit screen only:
URME Lager (own stock), Dropshipping (supplier) or Mixed, with the unit counts. The order shows
a summary (URME stock only / Dropshipping required / Mixed fulfillment), and WooCommerce > Orders
gets a Fulfillment column and filter. The source is frozen when stock is taken for the order;
later supplier changes never alter it, and returns are shown next to it. Orders placed before
1.1.0 show "Unknown / Legacy order".

Nothing is stored on the order (no order or line meta, no order notes), so it cannot appear in
customer pages, My Account, emails, invoices, packing slips, the REST/Store API or structured data.

== Price review (admin only) ==

When a watch switches automatically from Local first to Supplier (its last local unit was sold),
an admin notice "Price review required: SKU … has switched to Dropshipping." appears with the
product, SKU, previous local cost, supplier cost EUR/SEK, supplier stock, current selling price,
the switch time, a "Review price" button and "Mark as reviewed". It stays until marked as
reviewed. Supplier Sync > Price Review lists them (Needs review / Reviewed) with a count badge.
One switch = one review (created in the same transaction as the switch). Selling prices are
never changed by the plugin.

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
