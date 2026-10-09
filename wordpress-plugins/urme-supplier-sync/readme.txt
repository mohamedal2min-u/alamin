=== URME Supplier Sync ===
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.1
Stable tag: 1.9.3

Browse the supplier's watch catalog and keep stock and cost price in sync for the WooCommerce products you explicitly select.

== What it does ==

* Downloads the supplier XML feed in the background (hourly) and keeps a local copy of the
  WATCH category only. Browsing and searching never downloads the feed.
* WooCommerce > Supplier Sync shows every supplier watch with image (loaded from the supplier,
  nothing is added to the Media Library), brand, model (PRODUCTNO), EAN (ITEM_ID), supplier stock,
  cost in EUR and cost in SEK.
* Watches with URME stock (URME Lager, stock above 0, not selected) are hidden from the catalog:
  there is nothing to do on them. When their stock reaches 0 they come back, ready for
  "Dropshipping". A Model / EAN / Text search or "URME stock: In stock" still shows them.
* The catalog lists the watches in your store first (linked, or a unique URME match), then Needs
  review, then Not in URME; inside each group the largest supplier stock first (out of stock
  last), then brand and model. Compact columns: brand above
  the product name, model (PRODUCTNO) above the EAN, EUR cost above SEK cost.
* The catalog opens with the brands enabled for sync only (Settings > Brands enabled for sync) when
  no filter is used; any search (Model, EAN, Text), a Brand choice or a dashboard link looks in all
  brands, so a watch you look for is always found. "Brand sync" can also be set to Enabled brands
  only, Disabled brands or All brands. The Brand filter lists
  only enabled brands (a brand filtered on from an old link is shown with "– sync off").
* "In URME" column: whether the watch already exists in the store, found by SKU/PRODUCTNO and
  EAN/ITEM_ID:
  - Exists in URME (one confident match, with a link to the product)
  - Not in URME
  - Needs review (several products could match)
  - Manually linked
  This is information only. Nothing is synced because a product exists.
* "URME stock" column: the current WooCommerce stock of the linked product, or of the unique
  confirmed URME match (the exact product or variation): green quantity, red "0 / Out of stock",
  "Not managed", and a note when backorders are allowed. Filters: URME stock (In stock / Out of
  stock / Not managed) and "Ready for supplier sync" (brand on, unique match, URME stock 0, not
  selected, still in the feed).
* "Dropshipping sales" tab: sales and profit of the watches sold as Dropshipping, for a chosen
  period, by brand and per order line (see the 1.8.0 changelog for what is counted).
* Two Fulfillment states only: **URME Lager** and **Dropshipping**. WooCommerce's current stock is
  the URME count (no order history decides anything).
  - URME Lager = a normal WooCommerce product with URME's own stock. It is not in supplier sync
    at all (no selection, no link); the plugin never writes its stock or cost.
  - Dropshipping = selected in the Supplier catalog; stock and cost follow the supplier.
  - URME Lager -> Dropshipping only at URME stock 0, only by hand ("Dropshipping" in the catalog
    or "Select checked for sync"); at 1 or more it is refused with a clear error and nothing
    changes ("Local URME stock exists (N units). Dropshipping can only start when the URME Lager
    stock is 0."). No confirmation overrides it. A watch sold down to 0 is not switched
    automatically.
  - Dropshipping -> URME Lager ("URME Lager" button on the watch's catalog row): the watch leaves
    supplier sync (its selection and link are removed) and its WooCommerce stock becomes 0 (out of
    stock); the supplier quantity is never kept. Enter the real stock in WooCommerce (product page
    or quick edit) when you have it. Cost and prices are not changed.
  - Pause / Resume and the Selected watches page no longer exist. On the update to 1.5.2, older
    URME Lager selections (Local first, Paused) and selections without a linked product are removed once
    from supplier sync; their stock, cost and prices are not changed.
* Per-product controls in the Sync column, only for an enabled brand, a unique confirmed match and
  a watch still in the feed (never for Not in URME / Needs review):
  - URME stock 0, not selected: "Dropshipping" selects and links the watch and syncs its supplier
    stock and cost at once, with the normal safety rules.
  - URME stock above 0: "URME Lager (N in stock) – Dropshipping is possible at stock 0", no button.
  - Dropshipping: "Sync now" and "URME Lager".
  Supplier sync never changes prices. Every condition is checked again when the button is clicked.
  These row actions and the sale price Save run without reloading the page; the row is updated in
  place with the result (bulk "Select checked for sync" is a normal form).
* "Select checked for sync" handles each checked watch on its own: WooCommerce stock 0 ->
  Dropshipping; stock above 0 -> not selected (stays URME Lager, stock and COGS kept). Needs review
  (several URME products), no URME product with this SKU or EAN (create it in WooCommerce first),
  brand sync off or not in the feed -> not selected and listed in the notice; the others are still
  processed. Turning a brand on again removes its Dropshipping watches that have URME stock from
  supplier sync (URME Lager, stock and cost kept; listed in the notice).
* Manual sale price: for every linked or uniquely matched product, the catalog shows the
  regular price (read-only) and an editable sale price with Save. Only the
  sale price of that exact product or variation is saved (WooCommerce product API); an empty field
  removes the sale. It must be a number in SEK, not above the regular price. Nothing else changes
  and no sync is started.
* Quick Edit on WooCommerce > Products: a Dropshipping product shows "Move to URME Lager (stop
  Dropshipping)" above Stock qty. Ticking it empties Stock qty so you type URME's own count; on
  Update the watch leaves supplier sync and its stock becomes that count (empty = 0, out of stock).
  Prices and cost are not changed by the plugin. Not shown for URME Lager or variable products.
* WooCommerce > Products gets a narrow "Fulfillment" column from the Supplier Sync link (never
  from the stock quantity): a blue "D" = Dropshipping, a green "U" = URME Lager (full name on
  hover and for screen readers). The Supplier catalog and orders use the same colours in full
  words: blue = Dropshipping, green = URME Lager.
* A "Fulfillment" filter in WooCommerce's product filter row: All / Dropshipping / URME Lager,
  each with its current count (read live from the links, one aggregate query). Same states as the
  badge: Dropshipping = supplier-linked, sync on, brand sync on; URME Lager = everything else.
  Counts are products (list rows); a variable product with a Dropshipping variation is listed
  under Dropshipping. It combines with the stock status, category,
  product type, brand and search filters, sorting and paging.
* Gift wrap (ThemeComplete Extra Product Options, "Presentinslagning") is not offered for a true
  Dropshipping product or variation: ThemeComplete's options are switched off for it with
  ThemeComplete's `wc_epo_disable` filter, and an add-to-cart request that still posts its option
  fields (tmcp_*) for a Dropshipping item is refused. URME Lager keeps it. A variable product keeps it; the selected variation decides at add to cart.
  When a product's Fulfillment state changes, its page is cleaned from caches (clean_post_cache).
  ThemeComplete forms are never changed.
* A product is synced only when all of these are true:
  1. its supplier CATEGORY is WATCH,
  2. its brand (MANUFACTURER) is enabled in Settings > Brands enabled for sync,
  3. you selected it (Dropshipping) and it is linked to a WooCommerce product with the same SKU or EAN.
  Turning a brand off keeps its selections and links; its products are just left alone.
* For those products only:
  - Stock: WooCommerce stock = supplier STOCK. 0 = out of stock, above 0 = in stock again.
  - Cost price: supplier PURCHASE_PRICE (EUR) x EUR/SEK rate, written to the store's cost field.
    The original EUR cost is kept as hidden product meta `_urme_supplier_cost_eur`.
* Never changes regular price, sale price, title, descriptions, images, categories, attributes
  or SEO fields.

== Installation ==

1. Plugins > Add New > Upload Plugin, choose urme-supplier-sync-1.8.0.zip, Install, Activate.
2. Open WooCommerce > Supplier Sync and click "Sync now" once to fill the catalog
   (after that it refreshes by itself every hour).
3. Status & log > "Store setup (detected)": check where cost price will be written.
4. Settings > "Brands enabled for sync": tick the brands you sell. (None are enabled at first.)
5. Supplier catalog: tick watches > "Select checked for sync" (or "Dropshipping" on a row). A watch
   is linked to the WooCommerce product with the same SKU or EAN; create the product first if it
   does not exist. Watches with URME stock stay URME Lager and are not selected.

== Selling price hint (admin only) ==

Supplier catalog has a "Price hint" column with a
suggested selling price for each supplier watch:

  Cost SEK        = (PURCHASE_PRICE EUR + 12 EUR) x EUR/SEK   (PURCHASE_PRICE is VAT 0%)
  Customer pays   = price x 0.90                               (10% coupon allowance)
  Excl. VAT       = customer pays / 1.25                       (25% Swedish VAT)
  Klarna fee      = customer pays x 0.05
  Profit          = excl. VAT - Klarna fee - cost SEK
  Target profit   = 20% of the cost SEK (setting)
  Suggested price = lowest price with profit >= target, i.e. cost SEK x 1.20 / 0.675,
                    rounded UP to the next 10 SEK (never down)

Example: 176 EUR at 11.321 -> cost 2,128 kr -> target 426 kr -> suggested 3,790 kr (customer
pays 3,411 kr, Klarna 171 kr, estimated profit 430 kr = 20% of the cost).

Under the suggestion, a watch that exists in URME also shows the profit at its current selling
price (sale price, else regular price) with the same formula: "Your price 4,490 kr: profit 902 kr
(42% of cost)", green when it reaches the target percentage, red when it is below.

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

== URME Lager and Dropshipping ==

* Dropshipping: stock and cost follow the supplier (hourly, or "Sync now").
* URME Lager: not in supplier sync; WooCommerce stock and cost are yours. "Dropshipping" in the
  Supplier catalog is offered when its stock is 0; it never happens automatically.
* "URME Lager" on a Dropshipping row: removed from supplier sync, stock 0 / out of stock until you
  enter the real stock.

Every order line is booked in a small ledger table (urme_ss_alloc) when WooCommerce takes its
stock: Dropshipping if the watch is Dropshipping at that moment, otherwise URME Lager, so orders
can be labeled (see below). A returned unit does not change the watch's state; use "URME Lager"
if a Dropshipping watch should be sold from URME's own stock.

== Fulfillment source (admin only) ==

Each order line shows, on the order edit screen only: URME Lager (own stock) or Dropshipping
(supplier), with the unit counts; an order with both is Mixed. The order shows
a summary (URME stock only / Dropshipping required / Mixed fulfillment), and WooCommerce > Orders
gets a Fulfillment column and filter. The source is frozen when stock is taken for the order;
later supplier changes never alter it, and returns are shown next to it. Orders placed before
1.1.0 show "Unknown / Legacy order"; lines of watches that were not in supplier sync before 1.6.0
show "Not tracked", as does an order whose stock has not been taken yet (unpaid).

Nothing is stored on the order (no order or line meta, no order notes), so it cannot appear in
customer pages, My Account, emails, invoices, packing slips, the REST/Store API or structured data.

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

== Changelog ==

= 1.9.3 =
* Supplier catalog: Profit column (profit per watch at the current price, coloured by the loss
  protection rule); the URME column shows the supplier flag (or a store icon for URME Lager)
  instead of the word Dropshipping.

= 1.9.2 =
* Supplier catalog: the same watch at the other supplier shows line by line under the row's own
  values (flag under Model, its stock under Stock, its price under Cost), ✓ on the cheapest.

= 1.9.1 =
* Supplier catalog: watches with supplier stock 0 are hidden (a search or Selected only still
  finds them); new "Both suppliers" filter; each row shows the same watch at the other supplier
  and marks the cheapest after shipping + fees.
* Compact catalog: short column titles, one-line suggested price and profit (details on hover).

= 1.9.0 =
* Two suppliers: Relojitos (Spain, XML feed) and ILA Uhren (Germany, WooCommerce CSV feed).
  Settings > Suppliers: feed URL, shipping + fees per order (EUR) and delivery days for each.
  A supplier without a feed URL is not used.
* Each Dropshipping watch is bought where it is cheapest now (cost price + that supplier's
  shipping + fees) with stock, matched by EAN. Stock and cost follow the chosen supplier; a
  change is logged ("supplier ES → DE"). A supplier whose feed is stale is not used.
* Delivery days per supplier: one WoodMart estimated-delivery rule per supplier (kept by the
  plugin), active only for watches bought from that supplier now. URME Lager is unchanged.
* Orders (admin only): a small country flag with the supplier and its reference on each line,
  the order and the orders list. Private order note "URME: Dropshipping – Relojitos (ES) – SKU × n".
* Dropshipping sales: profit per supplier with the total below; shipping + fees are counted once
  per order and supplier.
* Catalog: supplier flag on each row and a Supplier filter. Price hint and Auto price use the
  row's supplier costs. New public product meta `urme_supplier` (relo / ila) for feeds.

= 1.8.0 =
* Supplier catalog order: in the store first, then Needs review, then Not in URME, and inside each
  group the largest supplier stock first (out of stock last). Watches at URME stock 0 are no
  longer pinned above the others; they follow the same order.
* New "Dropshipping sales" tab (admin only, read only): orders, watches, sales incl. and excl.
  VAT, supplier cost, extra cost, payment fee and profit for This month / Last month / Last 30
  days / This year / All time / custom dates, by brand and per order line. Only units sold as
  Dropshipping count (from the fulfillment ledger); URME Lager units, cancelled, refunded and
  failed orders and units given back are left out. Supplier cost is the cost WooCommerce froze on
  the order (COGS); without it the watch's last synced cost is used and marked "estimated". Extra
  cost and payment fee come from Settings > Selling price hint. Nothing is written.

= 1.7.0 =
* New public product meta `urme_fulfillment` (dropship / local / lager / paused / brand_off) so
  product feeds such as CTX Feed Pro can filter or label products by fulfillment source.
  Updated whenever a product is linked or unlinked, its stock mode changes (also from Quick
  Edit), a brand is turned on or off, and at the end of every product sync; written only when
  the value changes. Filled once for all products on update; Status > "Rebuild fulfillment
  labels" rebuilds it. Removed on uninstall. Prices, stock and everything else unchanged.

= 1.6.13 =
* Private order note when a watch is sold: "URME: Dropshipping – SKU × 1" or "URME: URME Lager –
  SKU × 1", so the source shows in the WooCommerce mobile app (Settings > Order note, on by
  default). Private notes are never shown to the customer.

= 1.6.12 =
* A Dropshipping watch that disappears from the supplier feed is set out of stock at the next
  sync (it was left unchanged, so it could stay in stock on the site). It is synced again when
  it returns. The feed safety checks (failed or half-empty feed) still stop all changes.

= 1.6.11 =
* WooCommerce > Products: the estimated profit under the D badge is removed again (the profit
  stays in the Supplier catalog).

= 1.6.10 =
* "Auto …98 kr" equal to the regular price changes nothing ("already sells at …") and removes a
  sale price equal to the regular price, instead of saving a sale price equal to the regular price.

= 1.6.9 =
* "Auto …98 kr" button: when the price is above the regular price it now raises the regular
  price to it (and removes a sale price) instead of refusing. At or below the regular price it
  still sets the sale price.

= 1.6.8 =
* Loss protection uses a minimum profit (Settings, default 500 kr): a Dropshipping watch whose
  estimated profit at its current price is below it goes out of stock; at or above it the watch
  stays in stock, also below the 20% hint target.
* The estimated profit is shown everywhere: under the D badge on WooCommerce > Products (green /
  red), in the Supplier catalog ("OK: at least 500 kr" / "Below 500 kr minimum"), and in the sync
  log and link message ("profit 430 kr, below the 500 kr minimum").
* Profit colours: green at the 20% target or above, yellow below the target, red only for a real
  loss (profit below 0).

= 1.6.7 =
* Loss protection (Settings, on by default): a Dropshipping watch whose current price (sale price
  included) is below its cost is set out of stock, by every sync and as soon as such a price is
  saved. Same profit calculation as the price hint. Prices are never changed; stock comes back at
  the next sync once the price covers the cost.

= 1.6.6 =
* "Select checked for sync" now syncs the selected watches right away (supplier stock and cost,
  from the cached catalog, no feed download) instead of waiting for the next hourly sync. When a
  sync cannot run (another sync running, feed too old), the message says so and the next sync
  updates them.

= 1.6.5 =
* WooCommerce > Products: the Fulfillment counts (All / Dropshipping / URME Lager) follow the
  other active filters (stock status, brand, category, product type, search, post status),
  counted from the list's own query. Example: Boss + In stock shows URME Lager (16). Without
  the list query the overall counts are shown as before.

= 1.6.4 =
* Supplier catalog: the "Auto … kr" button sits on its own line under the sale price field.
  Enter in the sale price field saves only that price (it never clicks "Auto").

= 1.6.3 =
* Supplier catalog: an "Auto … kr" button next to the sale price. One click saves the suggested
  price raised to the next price ending in 98 (2 910 → 2 998, 4 680 → 4 698). Same checks as Save
  (not above the regular price, linked or uniquely matched product only); works with or without
  JavaScript.

= 1.6.2 =
* Supplier catalog: watches with URME stock are hidden until their stock is 0; watches at URME
  stock 0 (not selected yet, ready for Dropshipping) are listed first, then Dropshipping. A search
  or "URME stock: In stock" still shows the hidden ones.

= 1.6.1 =
* Selling price hint: the target profit is a percentage of the cost (default 20%, Settings >
  Selling price hint) instead of a fixed 500 SEK. The estimated profit also shows its % of cost.
* Supplier catalog: the profit of each watch at its current selling price is shown under the
  suggestion (green at or above the target, red below). Prices are never changed.

= 1.6.0 =
* The old "Local first" engine is removed (unused since 1.5.2): local stock counting, automatic
  switches, crash recovery, transactions and the sweep before each sync. About 900 lines less.
* Order labels are kept and simpler: every order line is booked when its stock is taken,
  Dropshipping if the watch is Dropshipping at that moment, otherwise URME Lager. Orders of your
  own-stock watches now show "URME Lager" instead of "Not tracked".
* Price Review (tab, notice and menu badge) is removed; it came only from the old automatic
  switch. Its table is kept and removed on uninstall.
* No database change; prices, supplier sync, catalog, Quick Edit and gift wrap are unchanged.

= 1.5.7 =
* Review fixes: a search for a watch of a disabled brand finds it again (the enabled-brands view
  applies only when the catalog is opened without a filter); after "Disable brand sync" the
  brand's list is not empty; dashboard links list every brand.
* The red "!" (not in supplier feed) on the Products list is red again; screen readers read
  "Dropshipping" / "URME Lager" instead of "D" / "U".
* One colour system: blue = Dropshipping, green = URME Lager (catalog, Products list, orders).
* Lighter: no plugin database query on store pages (the version check is autoloaded); a shop or
  category page loads the gift-wrap state of all its products in one query; admin pages skip the
  price-review query when nothing is pending, and the empty Price Review tab is hidden.
* Uninstall also removes the 1.5.2 cleanup flag.

= 1.5.6 =
* WooCommerce > Products: the Fulfillment column shows a blue "D" (Dropshipping) or a green "U"
  (URME Lager) instead of the full words (full name on hover); the column is narrower.
* Supplier catalog: opens with the brands enabled for sync only; "Brand sync: All brands" shows
  every watch.

= 1.5.5 =
* Quick Edit (WooCommerce > Products): "Move to URME Lager" above Stock qty for Dropshipping
  products. The watch leaves supplier sync and keeps the stock you type (empty = 0, out of stock),
  in one save. Checked again on the server; prices and cost untouched.

= 1.5.4 =
* Supplier catalog: watches in your store are listed first (linked or a unique URME match), then
  Needs review, then Not in URME.
* Narrower table (13 → 10 columns): brand shown above the product name, model and EAN stacked in
  one "Model / EAN" column, EUR and SEK cost stacked in one "Cost" column, smaller images.

= 1.5.3 =
* Supplier catalog: the Brand filter lists only brands enabled for sync ("All brands" unchanged).
* Tidier catalog table: rows aligned to the top, a wider Sync column with the state, its buttons
  on one line and a compact Regular / Sale price editor; the price-hint breakdown is smaller; a
  row's success message is shown in a small box and clears itself after a few seconds.

= 1.5.2 =
* The Selected watches page is removed (also manual linking and automatch). A watch is linked to
  the WooCommerce product with the same SKU or EAN; create the product first if it is missing.
* URME Lager watches are no longer in supplier sync: a watch with URME stock is not selected, and
  Dropshipping starts only by hand at stock 0 ("Dropshipping" in the catalog or "Select checked
  for sync"). No automatic switch at stock 0 and no switch back when a unit is returned.
* New "URME Lager" button on a Dropshipping catalog row: the watch leaves supplier sync and its
  stock becomes 0 (out of stock) until you enter the real stock. Cost and prices are not changed.
* Turning a brand on again removes its watches with URME stock from supplier sync (stock and cost
  kept, listed in the notice).
* On update, older URME Lager selections (Local first, Paused) and selections without a product
  are removed once from supplier sync; stock, cost and prices are not changed. No database change.

= 1.5.1 =
* Dropshipping -> URME Lager now sets the WooCommerce stock to 0 (out of stock) and stops supplier
  sync; the supplier quantity is never kept. The watch waits like that (it is not switched back to
  Dropshipping at 0) until you enter the real stock in WooCommerce; after that stock is sold it
  becomes Dropshipping again. The quantity field in the mode switcher is gone.

= 1.5.0 =
* Two Fulfillment states only: URME Lager and Dropshipping. Local first, Paused and supplier links
  with brand sync off are shown as URME Lager; Pause / Resume are removed.
* WooCommerce stock is the URME count (no order history decides the state). URME Lager ->
  Dropshipping only at stock 0 (refused with a clear error otherwise, nothing changed).
* A URME Lager watch whose stock reaches 0 becomes Dropshipping in the same request, with supplier
  stock and cost from the stored catalog and a "Price review required" notice for the selling price.
* Dropshipping -> URME Lager takes the typed quantity (at least 1), never the supplier quantity, and
  stops supplier stock and cost sync at once.
* Products filter, badges, catalog and Selected watches show only the two states.

= 1.4.1 =
* Gift wrap (ThemeComplete "Presentinslagning") is no longer offered for true Dropshipping products:
  ThemeComplete's options are switched off for them (wc_epo_disable), and an add-to-cart request
  that still posts the option fields for a Dropshipping item (the selected variation) is refused.
  Local first, URME Lager, Paused and brand sync off keep it. A product page is cleaned from
  caches when its Fulfillment state changes. ThemeComplete forms are never changed.

= 1.4.0 =
* Supplier catalog row actions (Save sale price, Start supplier sync, Use Local first, Sync now,
  Resume) run without reloading the page; the row is updated in place.
* Local URME stock always has priority over Dropshipping on every manual path: Start supplier sync,
  "Select checked for sync" (stock above 0 starts as Local first with current stock and COGS kept,
  stock 0 as Supplier now; Needs review, brand off and invalid watches are rejected one by one),
  manual linking and automatch, Local first -> Supplier now (no override), Resume, and turning a
  brand on again (watches with local units stay paused and are listed).
* WooCommerce > Products: Fulfillment filter (Dropshipping / URME Lager / Local first / Paused /
  Supplier – brand sync off) with live counts, the same states as the badge; works with the other
  product filters, sorting and paging.

= 1.3.0 =
* Supplier catalog: "URME stock" column (current WooCommerce stock of the linked or uniquely matched
  product/variation) and filters "URME stock" and "Ready for supplier sync".
* Per-product "Start supplier sync" (URME stock 0) and "Use Local first" (URME stock above 0, current
  stock and cost kept), "Sync now" and "Resume", only for an enabled brand and a unique match.
* Fulfillment badges (Dropshipping / Local first (N) / Paused / URME Lager) on WooCommerce > Products
  and in the catalog.
* Manual sale price editor in the catalog and Selected watches. Supplier sync still never changes
  regular or sale prices.
* Faster admin pages: the catalog needs 28 plugin queries at any page size (was 45); the price-review
  notice loads its products in bulk.

= 1.2.1 =
* Fix: saving the Settings page could remove brands that were enabled after the page was opened
  (e.g. with "Enable brand sync" in the catalog); their watches were then skipped. Only the
  changes made on the page are applied now, and every change to the enabled brands is logged.
* Fix: "Last synced" is recorded after every successful sync, also when nothing had to change.
* Fix: a Paused watch is never written to, also when an order with one of its local units is
  cancelled or refunded.

== Uninstall ==

Deleting the plugin removes its own tables and options. WooCommerce products are not changed.
