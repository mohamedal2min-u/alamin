=== URME Landing Engine ===
Contributors: urme
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.43
License: GPLv2 or later

Lightweight dynamic WooCommerce landing pages for URME.

== Automatic route content fallback ==

For automatic brand routes (herrklockor, damklockor, and any pa_serie term)
that have no explicit landing record overriding them, the plugin now reuses
content you already maintain on the taxonomy term itself:

* SEO title: the term's Rank Math SEO title (Products > Attributes >
  [taxonomy, e.g. Serie] > edit term > Rank Math SEO > SEO title).
* Meta description: the term's Rank Math SEO description, same screen.
* Intro text shown below the products: the term's own Description field.

If a term has none of these set, the plugin's generic Swedish template text
is used instead, same as before. An explicit landing record under
WooCommerce > URME Landings always takes priority over both.

== Important before activation ==

1. Create a full backup or test on staging first.
2. Disable old Code Snippets that implement the same /marken/... or /klockor/... routing.
3. Keep Rank Math active if you want the plugin's Rank Math title/description/canonical/breadcrumb integration.
4. WooCommerce must be active.

== What it does ==

* /klockor/rea/ - all products currently on sale.
* /klockor/automatiska/ - products matching the existing automatic-movement aliases in pa_urverkstyp (automatic / automatisk / automatiskt).
* /klockor/guld/ - products matching configured gold case-colour terms.
* /klockor/silver/ - products matching configured silver case-colour terms.
* /klockor/rektangulara/ - created as a DRAFT template until pa_boettform / rektangular is available and you publish it.
* /marken/{brand}/herrklockor/
* /marken/{brand}/damklockor/
* /marken/{brand}/rea/
* /marken/{brand}/{pa_serie-term}/
* Custom global and brand landings from WooCommerce > URME Landings.

== Hero images ==

Open WooCommerce > URME Landings > edit a landing and use Featured Image.
That image becomes the header/hero for that landing.

If a landing has no Featured Image, the hero falls back to the default image
set under WooCommerce > Landing settings. Set that once (e.g. a general store
photo) so every landing always shows a hero background even before you add
per-page images.

For automatic brand routes such as /marken/seiko/5-sports/, create a Brand-scope landing override:
* Scope: Brand
* URL slug: 5-sports
* Brand slug: seiko
* Filter type: Attribute
* Taxonomy: pa_serie
* Term slugs: 5-sports
* Set Featured Image

The explicit Brand landing overrides the automatic route and lets you set a unique hero image and SEO text.

== Rektangulära klockor ==

The seeded Rektangulära landing assumes:
* Taxonomy: pa_boettform
* Term slug: rektangular

Create the WooCommerce attribute/term first, then edit/publish the landing if needed.

== SEO ==

The plugin provides:
* Clean canonical URLs.
* Rank Math title, description, canonical and breadcrumb filters.
* WordPress title fallback.
* Semantic H1 hero.
* Internal breadcrumb links.
* Pagination-aware canonical URLs.
* A Rank Math custom sitemap for PUBLISHED managed landing records.

Automatic brand routes remain usable, but a route should have an explicit Brand-scope landing record when you want a unique hero/SEO copy and inclusion in the managed landing sitemap.

== Security ==

Admin writes require manage_woocommerce, a nonce, sanitization and output escaping. No external API calls, trackers or third-party libraries are included.

== Performance ==

Frontend CSS and the small built-in interaction script load only on matching landing pages. Landing configuration and compatibility scope are cached per request. No external frontend dependencies are included.

== Upgrade Notice ==

= 1.0.40 =
Visible archive-description precedence: WooCommerce/WoodMart description first; Landing Engine intro only when the native archive description is empty.

= 1.0.39 =
Fixes the full-width landing header row on WoodMart 8.6.1 by avoiding WoodMart's `wd-grid-col` class cascade that forced the header back to one grid column.

= 1.0.38 =
Moves the Landing Engine header to a full-width row above the WoodMart shop sidebar and product area.

= 1.0.37 =
Integrates the live faceted-count and WoodMart sidebar/header-layout hotfixes directly into the Landing Engine plugin.

= 1.0.36 =
Fixes WoodMart control/PJAX URLs, WooCommerce widget filter state, Rank Math rel=next/prev pagination, and activation-time migration ordering on Landing Engine routes.

= 1.0.35 =
Improves WooCommerce/WoodMart filter compatibility on URME Landing Engine routes. Test `/klockor/automatiska/`, `/klockor/rea/` and representative filtered URLs after updating.

== Changelog ==

= 1.0.43 =
* SEO: new setting WooCommerce > Landing settings > "Minimum products to index" (default 3). Landing pages that list fewer products stay visible to shoppers but get noindex and are left out of the sitemap, so Google does not see near-empty pages (half of the automatic series routes had only 1-2 products).
* Sitemap checks count only products the shop lists (not hidden from the catalog; not out of stock when the store hides those), for landing records too.
* The sitemap is rebuilt once after each plugin update; uploading a new version used to leave Rank Math serving the old cached sitemap.

= 1.0.42 =
* SEO: the urme-landing sitemap now also lists the automatic brand routes that show products and have no landing record: /marken/{brand}/herrklockor/, /damklockor/, /rea/ and /{series}/. Only brands' visible products count (not hidden from the catalog; not out of stock when the store hides those), so no listed URL answers 404. The list is cached and rebuilt when a product, a landing or a scheduled sale changes. The sitemap index splits into pages when there are more URLs than Rank Math's per-sitemap limit.
* SEO: the BreadcrumbList schema now includes the current page. Rank Math dropped the last crumb because it had no URL (Google saw "... > SEIKO" without "Herrklockor"); it now carries the canonical URL. The visible breadcrumb is unchanged.
* The breadcrumb home label is "Hem", the same as the rest of the site (was "Startsida"). Filter: `urme_le_breadcrumb_home_label`.

= 1.0.41 =
* SEO: each landing now shows its own intro text (or the term description) below the products, and the WooCommerce Shop page text is no longer printed on landing routes. Before, every landing showed the same Shop text (duplicate content) and the landing intro was never visible. Shown on page 1 only. Return false from the `urme_le_remove_shop_description` filter to keep the 1.0.40 behaviour.
* Fix: /marken/{brand}/herrklockor/ and /marken/{brand}/damklockor/ returned 404 for every brand without its own landing record, because only the category slugs `herrklockor`/`damklockor` were accepted. The store's `herr`/`dam` categories are now used too (first existing slug wins; filter `urme_le_gender_category_slugs`).
* Speed: the hero image is rendered with width/height and srcset/sizes, so phones download a smaller file instead of the full-size original. It stays eager with fetchpriority=high.

= 1.0.40 =
* Prevents duplicate visible archive descriptions on Landing Engine routes. If WooCommerce/WoodMart already has a non-empty native archive description (including the published WooCommerce Shop page content), the Landing Engine intro is not rendered.
* Landing Engine `_urme_le_intro` and term-description fallback remain available only when the native archive description is empty.
* This changes visible description precedence only; Landing Engine SEO title/meta/canonical/robots/schema/sitemap behavior is unchanged.
* Added the `urme_le_has_native_archive_description` filter for controlled future compatibility.
* Stable tag updated to 1.0.40.

= 1.0.39 =
* Fixed the 1.0.38 full-width header regression on WoodMart 8.6.1. The `wd-grid-col` class matched WoodMart's generic `[class*=wd-grid]` rule, whose later-loaded defaults reset the header to one grid column.
* The Landing Engine header wrapper no longer uses `wd-grid-col`; its dedicated `urme-le-header-row` remains a direct WoodMart grid child and spans `grid-column: 1 / -1`, so breadcrumbs, hero, H1, badge and subtitle stay above the sidebar/product row at full content width.
* Preserves all 1.0.37 faceted-count fixes and all 1.0.36 URL, pagination, migration and SEO compatibility fixes.
* Stable tag updated to 1.0.39.

= 1.0.38 =
* Landing breadcrumbs, hero image, H1, badge and subtitle now render as one full-width grid row before the WoodMart Shop Sidebar/product area.
* Uses both `woocommerce_sidebar` and `woocommerce_before_main_content` at priority 5 with a one-render guard, so sidebar-left, sidebar-right and full-width archive layouts keep the header above the filter/product row without editing WoodMart core.
* Preserves all 1.0.37 faceted-count fixes and all 1.0.36 URL, pagination, migration and SEO compatibility fixes.
* Stable tag updated to 1.0.38.

= 1.0.37 =
* Layered-nav counts on dynamic Landing Engine routes now apply selected terms from other facets when WooCommerce uses the product-attribute lookup table. This keeps OR within a facet and AND between facets, hiding stale zero-result choices.
* Landing Engine breadcrumbs/hero now render after WoodMart opens `.site-content`, so the left Shop Sidebar stays aligned beside the landing content instead of being pushed above or around the hero/products.
* Includes the two live 1.0.36 hotfixes previously carried in Code Snippets IDs 71 and 72; those snippets must be removed after updating.
* Stable tag updated to 1.0.37.

= 1.0.36 =
* WoodMart `woodmart_shop_page_link` is rebased only on matched Landing Engine routes, preserving its existing query arguments for per-page, grid/list, per-row, price and ordering links; WoodMart PJAX therefore follows the correct landing URL without a JavaScript patch.
* WooCommerce widget current-page URLs now preserve active filter/order/price query arguments while replacing only the route base.
* Rank Math `rel="next"` / `rel="prev"` archive links now stay on the Landing Engine route instead of falling back to the generic `/klockor/` Shop archive.
* Activation now runs Landing Engine migrations before advancing the installed-version option, preventing updates performed while the plugin was inactive from skipping required migrations.
* Automatiska has a runtime-only compatibility fallback for the known `automatic` / `automatisk` / `automatiskt` seeded aliases. It is limited to the seeded global `pa_urverkstyp` landing shape and does not write to the database.
* Removed redundant manual archive-state flag rewrites while preserving status, empty-result, faceted-count and sale-price scope guards.
* Stable tag updated to 1.0.36.

= 1.0.35 =
* WooCommerce/WoodMart archive initialization now happens before WooCommerce builds its main product query, so Landing Engine routes participate in the native shop/filter lifecycle from the start.
* Sale landing restrictions now enter through WooCommerce's native `loop_shop_post_in` filter, so the sale scope exists before WoodMart/WooCommerce inspect the archive query and cannot be overwritten during initialization.
* Layered-nav term-count SQL is now constrained by the immutable Landing Engine scope. This prevents OR filters from dropping the landing's own attribute/category/brand restriction and advertising choices that immediately produce zero products.
* WooCommerce price-filter SQL is constrained by the same immutable landing scope so price bounds stay aligned with the current landing.
* Automatiska now supports the existing automatic-movement aliases (automatic / automatisk / automatiskt), filtering out aliases that do not exist on the site.
* Upgrade migration expands only the known untouched/default Automatiska configurations; custom landing filters are preserved.
* Stable tag updated to 1.0.35.

= 1.0.34 =
* WoodMart/WooCommerce filter compatibility: layered-nav and price-filter widgets now keep URME Landing Engine routes as their base URL instead of falling back to the generic shop/archive URL.
* Fixed the default Automatiska landing to use the current pa_urverkstyp slug `automatic`.
* Added a narrow upgrade migration that changes only the untouched legacy Automatiska value `automatisk,automatiskt` to `automatic` when that current term exists; custom admin values are preserved.
* Attribute queries now ignore missing configured term slugs and fail closed if no valid configured term remains, preventing invalid taxonomy clauses from masquerading as valid filters.
* Added the `urme_le_resolved_term_slugs` filter for future controlled term-alias compatibility.

= 1.0.30 =
* Improved the automatic fallback SEO title/description for brand routes (herrklockor/damklockor/rea) that have no dedicated landing page or term-level SEO set - phrasing now follows real keyword-research patterns ("{brand} Klocka Herr/Dam", "fri frakt, Klarna och 60 dagars öppet köp") instead of the plugin's generic house copy. Brands with dedicated landings/term SEO (e.g. Seiko) are unaffected since those still take priority.
* Data hygiene note (server-side, not plugin code): found and removed 6 corrupted product_brand terms containing prompt-injection-style text, unrelated to any product (0 count each).

= 1.0.29 =
* Performance: URME_LE_Router::current_config() is called ~15-20 times per page render (title, description, canonical, breadcrumbs, hero image, intro, OG image...); it now memoizes the landing config per request instead of rebuilding it (11 meta lookups + sanitization) on every call.
* Performance: the hero image URL/vertical-position/horizontal-position are now resolved with a single URME_LE_SEO::hero_image() call in the header instead of three separate calls that each recomputed the same thing.
* Accessibility: the intro "Läs mer / Visa mindre" toggle button now has aria-expanded and aria-controls, kept in sync by front.js, so screen readers announce its collapsed/expanded state correctly.
* Reviewed the full codebase (PHP, CSS, JS) after the session's changes: no functional bugs found, no dead/orphaned CSS rules, no unused hooks.

= 1.0.28 =
* Split image fit by screen size: desktop is back to object-fit: cover at the larger clamp(150-300px) height (big, fills the banner, minor cropping acceptable - steerable via the Hero image position fields), while mobile keeps object-fit: contain at 130px (no cropping, since mobile's narrow width was cutting off logo text).

= 1.0.27 =
* Reduced desktop hero banner height (clamp 150-300px -> 110-190px) since object-fit: contain (1.0.25) can leave empty side margins on very wide/short boxes with narrower source photos - a shorter box keeps that empty space less noticeable.

= 1.0.26 =
* Enlarged the trust badge icon on mobile (both the default refresh SVG and a custom text/emoji icon) from 15px to 19px so it doesn't look too small next to the badge text.

= 1.0.25 =
* Hero image switched from object-fit: cover (crops to fill, can cut content) to object-fit: contain (always shows the full image, never crops) site-wide on all landing pages. Any empty space left around the image is filled with the banner's background color. Mobile banner height reduced (185px -> 130px) so the full image reads better at that size.

= 1.0.24 =
* Added a "Hero image horizontal position" control (Left / Center / Right), alongside the existing vertical one, since the much narrower mobile banner crops left/right content that a wide desktop banner doesn't. Available per-landing and as a site-wide default.

= 1.0.23 =
* Fixed: on mobile the breadcrumb bar's negative top margin (tuned for desktop's taller header) was pulling it up far enough to overlap the shorter mobile header/menu bar. Removed the negative pull on mobile (0 instead of -22px) so it sits safely below the header instead of overlapping it.

= 1.0.22 =
* Hero banner height increased again: desktop clamp(130px,260px) -> clamp(150px,300px), mobile 160px -> 185px.

= 1.0.21 =
* Added a "Hero image position" control (Top / Center / Bottom) so a tall photo can be cropped without cutting off important content (like a logo near the top edge). Available per-landing next to the Featured Image, and as a site-wide default under WooCommerce > Landing settings.

= 1.0.20 =
* Tightened breadcrumb spacing further: top pull -20px -> -32px, bottom margin 20px -> 10px (mobile: -14px/14px -> -22px/8px), aiming for a noticeably smaller gap on both sides.

= 1.0.19 =
* Hero banner height increased: desktop clamp(110px,200px) -> clamp(130px,260px), mobile 130px -> 160px.

= 1.0.18 =
* Breadcrumb bar spacing above and below is now symmetric (20px), and the current/last breadcrumb item ("Presage" etc.) is now a solid dark pill matching the other breadcrumb links' shape instead of bare unstyled text, so it reads as visibly part of the trail.

= 1.0.17 =
* Explicitly hooks Rank Math's rank_math/opengraph/facebook/image and rank_math/opengraph/twitter/image filters to the landing's hero image, confirmed against Rank Math's official hook documentation, instead of relying on Rank Math's automatic content-image detection.

= 1.0.16 =
* SEO fix: herrklockor/damklockor automatic routes no longer pull Rank Math SEO title/description from the shared herrklockor/damklockor category term - that term is identical across every brand, so doing so was printing the exact same meta title/description on every single brand's page (a duplicate-content regression introduced in 1.0.12). Only series-specific terms and single-term configured landings use the term-content fallback now.
* SEO fix: the hero banner is now a real <img> element with alt text instead of a CSS background-image, so it can be indexed by Google Images and picked up automatically for social share (Open Graph) previews.

= 1.0.15 =
* The intro/description block below the products is now a self-contained collapsible white card with a "Läs mer / Visa mindre" toggle button, matching the site's own category-description card (colors, font and button shape read directly from the theme via DevTools). It's a lightweight built-in implementation (own CSS/JS, no Elementor dependency), since the theme's native card is an Elementor "Collapsible Content" widget whose assets are not guaranteed to load on these dynamically generated routes.

= 1.0.14 =
* Removed the plugin's own font-size/line-height from the intro/description block below the products, so headings and paragraphs inside it (including term descriptions) fully inherit the theme's own typography and colors instead of being overridden.

= 1.0.13 =
* Fixed: the term-content fallback (1.0.12) only checked automatic routes (herr/dam/serie) and missed "configured" landing records that map to a single attribute term - which is exactly how documented Brand-scope overrides like /marken/{brand}/{series}/ are set up. Configured single-term landings now resolve their underlying term too, so the term's Rank Math SEO fields and Description are picked up whenever the landing's own SEO/intro fields are left empty.

= 1.0.12 =
* Automatic routes (brand + herrklockor/damklockor/series, no explicit landing record) now fall back to that taxonomy term's own content when no landing override exists: Rank Math's per-term SEO title/description (Products > Attributes > [taxonomy] > term) for the meta title/description, and the term's own Description field for the intro text shown below the products. A configured landing record, when one exists, still takes priority over all of this.

= 1.0.11 =
* Moved the landing "Intro text" from above the product grid to below it (after the loop and pagination), matching how category descriptions are shown at the bottom of the page, with a divider line above it.

= 1.0.10 =
* Added a "Trust badge icon" field (WooCommerce > Landing settings) to type a character or emoji (e.g. ↻ ⟳ ✓ ★) shown before the badge text instead of the built-in refresh SVG icon.

= 1.0.9 =
* Added a "Trust badge text" field under WooCommerce > Landing settings (e.g. "60 dagars öppet köp"). When set, it renders with a small icon aligned on the same line as the H1 title, right-aligned next to it, replacing any theme-side badge previously placed under the title.

= 1.0.8 =
* Pulled the breadcrumb bar further up toward the theme header (-10px -> -26px) and tightened the gap between the hero image and the title below it (24px -> 10px).

= 1.0.7 =
* Tightened the vertical spacing around the breadcrumb bar (pulled closer to the theme header above, less gap before the hero image below).
* Smaller, gray H1 title under the hero image instead of large black text.

= 1.0.6 =
* Hero corner radius switched from a percentage (which stretched into an oval look on a very wide, short banner) to a fixed 8px radius for a subtle, consistent rounding at any width.

= 1.0.5 =
* The hero image and breadcrumb bar now stretch to the full width of the theme's content area instead of being capped at 1180px, matching the site's actual layout width.

= 1.0.4 =
* Smaller hero corner radius (8% -> 5%), smaller H1 title size, and a more compact breadcrumb bar (smaller font/padding).

= 1.0.3 =
* Redesigned the landing header to match a short, rounded-corner banner style: breadcrumb now sits above the image, the hero image is a plain short banner with no text on it, and the H1/subtitle render as normal left-aligned text below the image instead of white text overlaid on the picture.

= 1.0.2 =
* Added WooCommerce > Landing settings page to set a default hero image, used as a fallback on any landing without its own Featured Image.

= 1.0.1 =
* Security: dynamic-route query vars are no longer trusted from arbitrary $_GET requests. An internal match flag now gates all routing/SEO logic, so a URL like /?urme_le_context=global&urme_le_landing_id=5 can no longer force landing content onto an arbitrary page (index bloat / cache-poisoning risk).
* SEO: automatic brand routes (/marken/{brand}/{category-or-series}/) that match zero products now return a real 404 instead of a permanently empty 200 page (was a soft-404 / thin-content risk).
* SEO: explicitly configured landings that are temporarily empty (e.g. no products on sale) stay live but are now marked noindex via Rank Math instead of being indexed with no content.
* SEO: the Rank Math sitemap provider no longer lists landings that currently match zero products.
* SEO: requests that only differ from their canonical slug by case/accents (e.g. /marken/Seiko/ vs /marken/seiko/) now get a real 301 redirect instead of relying on the canonical tag alone.

= 1.0.0 =
* Initial URME release.
