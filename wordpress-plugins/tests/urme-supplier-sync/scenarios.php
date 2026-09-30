<?php
/**
 * Scenario tests for URME Supplier Sync. See README.md; run with: wp eval-file scenarios.php
 */
require_once URME_SS_DIR . 'includes/class-admin.php';

define( 'TS', __DIR__ );
$GLOBALS['FAIL'] = 0;
$GLOBALS['PASS'] = 0;

function ok( $cond, $label, $extra = '' ) {
	if ( $cond ) {
		++$GLOBALS['PASS'];
		echo "  PASS  $label\n";
	} else {
		++$GLOBALS['FAIL'];
		echo "  FAIL  $label" . ( '' !== $extra ? "  => " . ( is_string( $extra ) ? $extra : json_encode( $extra ) ) : '' ) . "\n";
	}
}
function section( $t ) {
	echo "\n== $t\n";
}
function feed( array $o = array() ) {
	shell_exec( 'python3 ' . TS . '/genfeed.py 3000 ' . TS . '/feedsrv/current.xml '  . escapeshellarg( json_encode( $o ? $o : new stdClass() ) ) );
}
function mode( $m ) {
	file_put_contents( TS . '/feedsrv/mode.txt', $m );
}
function run( array $a = array() ) {
	$r = URME_SS_Sync::run( $a + array( 'trigger' => 'test' ) );
	URME_SS_Log::flush();
	return $r;
}
function p( $id ) {
	clean_post_cache( $id );
	wp_cache_flush();
	return wc_get_product( $id );
}
function admin( $method, ...$args ) {
	$m = new ReflectionMethod( 'URME_SS_Admin', $method );
	$m->setAccessible( true );
	return $m->invoke( null, ...$args );
}
function key_of( $i ) {
	return (string) ( 4900000000000 + $i );
}
function cat( $i ) {
	return URME_SS_DB::get_item( key_of( $i ) );
}
function link_of( $i ) {
	$l = URME_SS_DB::get_links()['rows'];
	foreach ( $l as $row ) {
		if ( $row['item_key'] === key_of( $i ) ) {
			return $row;
		}
	}
	return null;
}
function settings( array $s ) {
	update_option( URME_SS_Settings::OPTION, array_merge( URME_SS_Settings::all(), $s ) );
}
function sync_stats() {
	return URME_SS_Sync::status()['sync'];
}
function feed_status() {
	return URME_SS_Sync::status()['feed'];
}
function cogs( $on ) {
	update_option( 'woocommerce_feature_cost_of_goods_sold_enabled', $on ? 'yes' : 'no' );
	wp_cache_flush();
	// FeaturesController caches enabled state per request; reset it.
	$fc = wc_get_container()->get( \Automattic\WooCommerce\Internal\Features\FeaturesController::class );
	foreach ( array( 'features', 'enabled_features_cache' ) as $prop ) {
		if ( property_exists( $fc, $prop ) ) {
			$r = new ReflectionProperty( $fc, $prop );
			$r->setAccessible( true );
			if ( 'features' !== $prop ) {
				$r->setValue( $fc, null );
			}
		}
	}
	delete_transient( URME_SS_Store::INSPECT_TRANSIENT );
}
function feed_price( $i ) {
	return (float) cat( $i )['purchase_price'];
}

global $wpdb;
echo 'WordPress ' . get_bloginfo( 'version' ) . ', WooCommerce ' . WC_VERSION . ', PHP ' . PHP_VERSION . "\n";

/* ------------------------------------------------------------------ reset */
$wpdb->query( 'DELETE FROM ' . URME_SS_DB::catalog_table() );
$wpdb->query( 'DELETE FROM ' . URME_SS_DB::links_table() );
$wpdb->query( 'DELETE FROM ' . URME_SS_DB::alloc_table() );
$wpdb->query( 'DELETE FROM ' . URME_SS_Price_Review::table() );
foreach ( array( 'urme_ss_status', 'urme_ss_log', 'urme_ss_rate', 'urme_ss_feed_state', 'urme_ss_lock' ) as $o ) {
	delete_option( $o );
}
foreach ( wc_get_products( array( 'limit' => -1, 'status' => array( 'publish', 'trash', 'draft' ), 'return' => 'ids', 'type' => array( 'simple', 'variable' ) ) ) as $old ) {
	$op = wc_get_product( $old );
	if ( $op ) {
		$op->delete( true );
	}
}
update_option(
	URME_SS_Settings::OPTION,
	array_merge(
		URME_SS_Settings::defaults(),
		array(
			'feed_url'       => 'http://127.0.0.1:8090/feed.xml',
			'enabled_brands' => array( 'Seiko', 'TISSOT', 'Guess' ), // Case-insensitive on purpose.
		)
	)
);
update_option( 'urme_test_rate_mode', 'ecb' );
cogs( false );
mode( 'ok' );
feed();

function mk( $name, $sku, $gtin = '', $manage = false, $qty = null ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_regular_price( '2999' );
	$p->set_sale_price( '2499' );
	$p->set_description( 'Original description' );
	$p->set_short_description( 'Short' );
	if ( $sku ) {
		$p->set_sku( $sku );
	}
	if ( $gtin ) {
		$p->set_global_unique_id( $gtin );
	}
	$p->set_manage_stock( $manage );
	if ( $manage ) {
		$p->set_stock_quantity( $qty );
	}
	$p->set_stock_status( 'instock' );
	return $p->save();
}
$P1  = mk( 'P1 exact sku', 'REF000000' );
$P2  = mk( 'P2 normalized sku', 'ref-000001', '', true, 0 ); // URME stock 0: may start as Supplier now.
$P3  = mk( 'P3 ean only', '', '04900000000002' );
$P4  = mk( 'P4 manual', 'OTHER1', '', true, 0 );
$P5a = mk( 'P5a conflict sku', 'REF000012' );
$P5b = mk( 'P5b conflict ean', 'XYZ', '4900000000012' );
$P7  = mk( 'P7 will be trashed', 'REF000024', '', true, 0 );
$P8  = mk( 'P8 unrelated', 'UNRELATED', '', true, 4 );

$parent = new WC_Product_Variable();
$parent->set_name( 'P6 variable' );
$parent_id = $parent->save();
$var       = new WC_Product_Variation();
$var->set_parent_id( $parent_id );
$var->set_sku( 'REF000018' );
$var->set_regular_price( '1999' );
$var->set_manage_stock( true );
$var->set_stock_quantity( 0 );
$V6 = $var->save();

$snapshot = function ( $id ) {
	$p = p( $id );
	return array( $p->get_name(), $p->get_regular_price(), $p->get_sale_price(), $p->get_description(), $p->get_short_description(), $p->get_sku(), $p->get_image_id(), $p->get_category_ids(), $p->get_attributes() );
};
$before = array();
foreach ( array( $P1, $P2, $P3, $P4, $P7, $V6 ) as $id ) {
	$before[ $id ] = $snapshot( $id );
}

/* ------------------------------------------------------------------ A */
section( 'A. First feed download (catalog + rate)' );
$r = run();
ok( true === $r['feed_ok'], 'feed refresh ok' );
$c = URME_SS_DB::catalog_counts();
ok( 1500 === $c['in_feed'], 'only WATCH kept (1500 of 3000)', $c );
ok( 3000 === feed_status()['total_items'], 'total items counted' );
$rate = URME_SS_Rates::current();
ok( $rate && abs( $rate['rate'] - 11.025 ) < 0.0001 && 'ECB reference rate' === $rate['source'], 'ECB rate stored', $rate );
ok( null === URME_SS_DB::get_item( key_of( 3 ) ), 'JEWELRY item not stored' );

/* ------------------------------------------------------------------ B */
section( 'B. Cost field detection (nothing configured)' );
$t = URME_SS_Store::cost_target();
ok( null === $t['type'] && false !== strpos( $t['reason'], 'Cost of Goods Sold' ), 'auto: no cost field -> cost sync paused with guidance', $t );
$insp = URME_SS_Store::inspect( true );
ok( ! $insp['cogs_enabled'] && $insp['with_gtin'] >= 2, 'inspection sees GTIN values, COGS disabled', array( $insp['cogs_enabled'], $insp['with_gtin'] ) );

/* ------------------------------------------------------------------ C */
section( 'C. Select + automatic matching' );
$res = admin( 'select_items', array( key_of( 0 ), key_of( 1 ), key_of( 2 ), key_of( 6 ), key_of( 12 ), key_of( 18 ), key_of( 24 ) ) );
echo "  ({$res[0]})\n";
ok( 'warning' === $res[1] && false !== strpos( $res[0], 'REF000012 – several URME products match (Needs review' ) && null === link_of( 12 ) && false !== strpos( $res[0], 'REF000006 – no URME product with this SKU or EAN' ) && null === link_of( 6 ), 'bulk: Needs review and "not in URME" rejected, the others selected', $res );
// Links made by earlier versions (a manual link and an unlinked conflict); the rest of the scenario uses them.
URME_SS_DB::insert_link( key_of( 6 ), $P4, 'manual' );
$l12 = URME_SS_DB::insert_link( key_of( 12 ), 0, '' );
URME_SS_DB::update_link( $l12, array( 'last_status' => 'unlinked', 'last_message' => URME_SS_Store::auto_match( cat( 12 ) )['message'] ) );
ok( (int) link_of( 0 )['product_id'] === $P1 && 'sku' === link_of( 0 )['match_method'], 'exact SKU match' );
ok( (int) link_of( 1 )['product_id'] === $P2, 'normalized SKU match (ref-000001)' );
ok( (int) link_of( 2 )['product_id'] === $P3 && 'ean' === link_of( 2 )['match_method'], 'EAN match via GTIN-14 variant' );
ok( 0 === (int) link_of( 12 )['product_id'] && false !== strpos( link_of( 12 )['last_message'], 'Several' ), 'SKU/EAN conflict -> not linked', link_of( 12 )['last_message'] );
ok( (int) link_of( 18 )['product_id'] === $V6, 'variation matched by SKU' );
ok( array( 'Nothing new was selected.', 'info' ) === admin( 'select_items', array( key_of( 0 ) ) ), 'selecting twice is a no-op' );

/* ------------------------------------------------------------------ D */
section( 'D. Manual link from an earlier version' );
run( array( 'refresh_feed' => false, 'link_id' => (int) link_of( 6 )['id'] ) );
ok( (int) link_of( 6 )['product_id'] === $P4 && 'manual' === link_of( 6 )['match_method'] && p( $P4 )->get_stock_quantity() === (int) cat( 6 )['stock'], 'manual link kept and synced', array( p( $P4 )->get_stock_quantity(), cat( 6 )['stock'] ) );

/* ------------------------------------------------------------------ Z */
section( 'Z. URME match status for every catalog watch' );
URME_SS_Matcher::refresh_catalog();
ok( 'exists' === cat( 0 )['match_status'] && (int) cat( 0 )['match_product_id'] === $P1 && 'sku' === cat( 0 )['match_method'], 'REF000000 exists (SKU)', cat( 0 ) );
ok( 'exists' === cat( 2 )['match_status'] && (int) cat( 2 )['match_product_id'] === $P3 && 'ean' === cat( 2 )['match_method'], 'REF000002 exists (EAN)' );
ok( 'review' === cat( 12 )['match_status'] && count( explode( ',', cat( 12 )['match_candidates'] ) ) === 2, 'REF000012 needs review with 2 candidates', cat( 12 )['match_candidates'] );
ok( 'none' === cat( 30 )['match_status'], 'REF000030 not in URME' );
$mc = URME_SS_DB::match_counts();
ok( 1 === (int) $mc['manual'] && 1 === (int) $mc['review'], 'counts: 1 manual, 1 review', $mc );
$man = URME_SS_DB::search_catalog( array( 'match' => 'manual' ) );
ok( 1 === $man['total'] && key_of( 6 ) === $man['rows'][0]['item_key'], 'filter: manually linked' );
ok( 0 === URME_SS_DB::search_catalog( array( 'match' => 'none', 'productno' => 'REF000006' ) )['total'], 'manual link not listed as "Not in URME"' );
ok( (int) $mc['exists'] === URME_SS_DB::search_catalog( array( 'match' => 'exists' ) )['total'], 'filter: exists matches count' );
ok( 1 === URME_SS_DB::search_catalog( array( 'match' => 'review' ) )['total'], 'filter: needs review' );
$links_before = URME_SS_DB::link_counts()['selected'];
$P9 = mk( 'P9 new product', 'REF000030' );
$m  = URME_SS_Matcher::refresh_catalog();
ok( 'exists' === cat( 30 )['match_status'] && 1 === $m['changed'], 'new store product detected; only 1 row rewritten', $m );
ok( URME_SS_DB::link_counts()['selected'] === $links_before && null === link_of( 30 ), 'existing in URME does NOT select it for sync' );
ok( 0 === URME_SS_Matcher::refresh_catalog()['changed'], 'no writes when nothing changed' );

/* ------------------------------------------------------------------ E */
section( 'E. Stock sync (cost field not configured)' );
$r = run( array( 'refresh_feed' => false ) );
$s = sync_stats();
ok( true === p( $P1 )->get_manage_stock() && p( $P1 )->get_stock_quantity() === (int) cat( 0 )['stock'], 'P1: manage stock enabled + qty set', array( p( $P1 )->get_manage_stock(), p( $P1 )->get_stock_quantity() ) );
ok( p( $P2 )->get_stock_quantity() === (int) cat( 1 )['stock'], 'P2 qty = supplier stock' );
ok( p( $V6 )->get_stock_quantity() === (int) cat( 18 )['stock'], 'variation qty = supplier stock' );
ok( 0 === $s['cost_updated'] && 1 === $s['unmatched'], 'no cost writes, 1 unmatched', $s );
ok( wc_format_decimal( cat( 0 )['purchase_price'], 4 ) === get_post_meta( $P1, '_urme_supplier_cost_eur', true ), 'EUR reference stored' );
ok( false !== strpos( link_of( 0 )['last_message'], 'No cost field' ), 'link explains cost not synced' );

/* ------------------------------------------------------------------ F */
section( 'F. Cost sync with WooCommerce COGS enabled (auto-detected)' );
cogs( true );
$t = URME_SS_Store::cost_target();
ok( 'wc_cogs' === $t['type'], 'auto -> WooCommerce COGS', $t );
run( array( 'refresh_feed' => false ) );
$s   = sync_stats();
$exp = round( feed_price( 0 ) * 11.025, 2 );
ok( abs( p( $P1 )->get_cogs_value() - $exp ) < 0.001, 'P1 COGS = EUR x rate', array( p( $P1 )->get_cogs_value(), $exp ) );
ok( 6 === $s['cost_updated'], '6 cost updates', $s );
ok( abs( (float) link_of( 0 )['last_cost_sek'] - $exp ) < 0.001, 'link stores SEK/EUR/rate reference' );

/* ------------------------------------------------------------------ G */
section( 'G. Idempotent: second sync writes nothing' );
$q0 = $wpdb->num_queries;
run( array( 'refresh_feed' => false ) );
$s = sync_stats();
ok( 0 === $s['stock_updated'] && 0 === $s['cost_updated'] && 6 === $s['unchanged'], 'no updates on unchanged data', $s );

/* ------------------------------------------------------------------ Y */
section( 'Y. Brand allowlist' );
URME_SS_Settings::set_brand( 'Tissot', false );
ok( ! URME_SS_Settings::brand_enabled( 'TISSOT' ) && URME_SS_Settings::brand_enabled( 'seiko' ), 'brand toggle, case-insensitive' );
$q2   = p( $P2 )->get_stock_quantity();
$row2 = link_of( 1 );
$row2_raw = URME_SS_DB::get_link_by_id( $row2['id'] );
feed( array( 'set' => array( 'REF000001' => array( 'STOCK' => '77' ) ) ) );
run();
ok( 77 === (int) cat( 1 )['stock'], 'catalog still updated for browsing' );
ok( p( $P2 )->get_stock_quantity() === $q2, 'disabled brand: product not updated' );
ok( 1 === sync_stats()['brand_off'] && 5 === sync_stats()['checked'], 'counted as brand_off, not processed', sync_stats() );
ok( URME_SS_DB::get_link_by_id( $row2['id'] ) === $row2_raw, 'link row untouched (no writes for disabled brand)' );
ok( ! in_array( key_of( 1 ), wp_list_pluck( URME_SS_DB::get_links( array( 'enabled_brands_only' => true ) )['rows'], 'item_key' ), true ), 'disabled brand filtered in SQL' );
$r = run( array( 'refresh_feed' => false, 'link_id' => (int) link_of( 1 )['id'] ) );
ok( false !== strpos( $r['sync']['skipped'], 'not enabled' ) && p( $P2 )->get_stock_quantity() === $q2, 'single-product sync refuses disabled brand', $r['sync']['skipped'] );
URME_SS_Settings::set_brand( 'Tissot', true );
run( array( 'refresh_feed' => false ) );
ok( 77 === p( $P2 )->get_stock_quantity(), 're-enabled brand syncs again; link preserved' );
$saved = URME_SS_Settings::get( 'enabled_brands' );
settings( array( 'enabled_brands' => array() ) );
run( array( 'refresh_feed' => false ) );
ok( 0 === sync_stats()['checked'] && 7 === sync_stats()['brand_off'], 'no brands enabled -> nothing processed', sync_stats() );
settings( array( 'enabled_brands' => $saved ) );
$wpdb->update( URME_SS_DB::catalog_table(), array( 'category' => 'JEWELRY', 'stock' => 55 ), array( 'item_key' => key_of( 0 ) ) );
run( array( 'refresh_feed' => false ) );
ok( 55 !== p( $P1 )->get_stock_quantity() && 5 === sync_stats()['checked'], 'non-WATCH catalog row never synced' );
$r = run( array( 'refresh_feed' => false, 'link_id' => (int) link_of( 0 )['id'] ) );
ok( false !== strpos( (string) $r['sync']['skipped'], 'Category' ), 'single sync refuses non-WATCH too' );
run( array( 'force_feed' => true ) );
ok( 'WATCH' === cat( 0 )['category'], 'category restored from feed' );
URME_SS_Settings::save( array( 'feed_url' => 'http://127.0.0.1:8090/feed.xml', 'categories' => 'WATCH', 'auto_sync' => 1, 'cost_target' => 'auto', 'manage_stock' => 1, 'min_feed_ratio' => 50, 'max_feed_age_hours' => 3 ) );
ok( URME_SS_Settings::get( 'enabled_brands' ) === $saved, 'saving a form without the brand list keeps brands' );
URME_SS_Settings::save( array( 'brands_present' => 1, 'enabled_brands' => array( 'Guess', ' Seiko ', 'Tissot', 'seiko' ), 'categories' => 'WATCH', 'auto_sync' => 1, 'manage_stock' => 1, 'min_feed_ratio' => 50, 'max_feed_age_hours' => 3, 'feed_url' => 'http://127.0.0.1:8090/feed.xml' ) );
ok( 3 === count( URME_SS_Settings::get( 'enabled_brands' ) ), 'brand list saved and de-duplicated', URME_SS_Settings::get( 'enabled_brands' ) );
settings( array( 'cost_target' => 'auto' ) );

/* ------------------------------------------------------------------ H */
section( 'H. Out of stock and back in stock' );
feed( array( 'set' => array( 'REF000000' => array( 'STOCK' => '0' ) ) ) );
run();
ok( 0 === p( $P1 )->get_stock_quantity() && 'outofstock' === p( $P1 )->get_stock_status(), 'stock 0 -> out of stock', p( $P1 )->get_stock_status() );
feed( array( 'set' => array( 'REF000000' => array( 'STOCK' => '3' ) ) ) );
run();
ok( 3 === p( $P1 )->get_stock_quantity() && 'instock' === p( $P1 )->get_stock_status(), 'stock 3 -> in stock', p( $P1 )->get_stock_status() );

/* ------------------------------------------------------------------ I */
section( 'I. Unreadable stock value' );
$q2 = p( $P2 )->get_stock_quantity();
feed( array( 'set' => array( 'REF000000' => array( 'STOCK' => '3' ), 'REF000001' => array( 'STOCK' => 'n/a' ) ) ) );
run();
ok( p( $P2 )->get_stock_quantity() === $q2, 'stock untouched when supplier value is unreadable' );
ok( false !== strpos( link_of( 1 )['last_message'], 'unreadable' ), 'link explains it' );

/* ------------------------------------------------------------------ J */
section( 'J. Product temporarily missing from feed' );
$before_p2 = array( p( $P2 )->get_stock_quantity(), p( $P2 )->get_cogs_value(), p( $P2 )->get_stock_status() );
feed( array( 'drop' => array( 'REF000001' ), 'set' => array( 'REF000000' => array( 'STOCK' => '3' ) ) ) );
run();
ok( '0' === cat( 1 )['in_feed'] && null !== cat( 1 )['missing_since'], 'catalog row flagged, not deleted' );
ok( array( p( $P2 )->get_stock_quantity(), p( $P2 )->get_cogs_value(), p( $P2 )->get_stock_status() ) === $before_p2, 'P2 untouched while missing' );
ok( 1 === sync_stats()['missing'] && 'missing' === link_of( 1 )['last_status'], 'reported as missing', sync_stats() );
ok( null !== link_of( 1 ), 'selection/link kept' );
feed( array( 'set' => array( 'REF000000' => array( 'STOCK' => '3' ) ) ) );
run();
ok( 1 === feed_status()['returned'] && '1' === cat( 1 )['in_feed'], 'item back in feed', feed_status()['last_result'] );
ok( p( $P2 )->get_stock_quantity() === (int) cat( 1 )['stock'], 'P2 synced again after return' );

/* ------------------------------------------------------------------ K */
section( 'K. Feed download fails (HTTP 500)' );
$last_success = feed_status()['last_success'];
$state        = array( p( $P1 )->get_stock_quantity(), p( $P1 )->get_cogs_value() );
mode( 'http500' );
$r = run();
ok( false === $r['feed_ok'] && false !== strpos( feed_status()['last_error'], 'HTTP 500' ), 'feed failure recorded', feed_status()['last_error'] );
ok( feed_status()['last_success'] === $last_success, 'last successful update unchanged' );
ok( array( p( $P1 )->get_stock_quantity(), p( $P1 )->get_cogs_value() ) === $state, 'products unchanged' );
ok( 1500 === URME_SS_DB::catalog_counts()['in_feed'], 'catalog intact' );
mode( 'ok' );

/* ------------------------------------------------------------------ K2 */
section( 'K2. Regression: failed download must not push the fresh cached catalog' );
// 1. A successful, fresh feed whose values differ from the products (refreshed, not yet synced).
feed( array( 'set' => array( 'REF000000' => array( 'STOCK' => '8', 'PURCHASE_PRICE' => '500.00' ), 'REF000001' => array( 'STOCK' => '0' ) ) ) );
$r = run( array( 'sync_products' => false ) );
ok( true === $r['feed_ok'] && 8 === (int) cat( 0 )['stock'] && 0 === (int) cat( 1 )['stock'], 'fresh cached catalog differs from products' );
ok( time() - feed_status()['last_success'] < 60, 'cached catalog is fresh (well within max age)' );
$guard = array();
foreach ( array( $P1, $P2, $P3, $P4, $V6 ) as $id ) {
	$gp            = p( $id );
	$guard[ $id ] = array( $gp->get_stock_quantity(), $gp->get_stock_status(), $gp->get_manage_stock(), $gp->get_cogs_value(), get_post_meta( $id, '_urme_supplier_cost_eur', true ) );
}
$links_before = URME_SS_DB::get_links()['rows'];
$cat_before   = $wpdb->get_results( 'SELECT * FROM ' . URME_SS_DB::catalog_table() . ' ORDER BY id', ARRAY_A );
// 2. The next download fails (full run, as the hourly cron and "Sync now" do).
mode( 'http500' );
$r = run();
ok( false === $r['feed_ok'], 'feed download failed' );
// 3. Nothing in WooCommerce changed.
foreach ( array( $P1, $P2, $P3, $P4, $V6 ) as $id ) {
	$gp  = p( $id );
	$now = array( $gp->get_stock_quantity(), $gp->get_stock_status(), $gp->get_manage_stock(), $gp->get_cogs_value(), get_post_meta( $id, '_urme_supplier_cost_eur', true ) );
	ok( $now === $guard[ $id ], "product #$id stock/status/cost/EUR ref unchanged", array( $now, $guard[ $id ] ) );
}
$s = $r['sync'];
ok( 0 === $s['stock_updated'] && 0 === $s['cost_updated'] && 0 === $s['checked'], 'no products processed', $s );
ok( false !== strpos( $s['skipped'], 'could not be downloaded' ) && $s['skipped'] === sync_stats()['skipped'], 'reason recorded on Status', $s['skipped'] );
ok( URME_SS_DB::get_links()['rows'] === $links_before, 'link rows untouched' );
ok( $wpdb->get_results( 'SELECT * FROM ' . URME_SS_DB::catalog_table() . ' ORDER BY id', ARRAY_A ) === $cat_before, 'cached catalog not deleted or altered' );
ok( 1 === URME_SS_DB::search_catalog( array( 'productno' => 'REF000000' ) )['total'], 'previous catalog still browsable' );
// Same for the forced "Accept current feed" path.
$r = run( array( 'force_feed' => true ) );
ok( false === $r['feed_ok'] && 8 !== p( $P1 )->get_stock_quantity(), 'forced refresh that fails also blocks writes' );
// A deliberate product-only sync may still use the fresh cached catalog.
mode( 'ok' );
$r = run( array( 'refresh_feed' => false ) );
ok( 8 === p( $P1 )->get_stock_quantity() && abs( p( $P1 )->get_cogs_value() - round( 500 * URME_SS_Rates::current()['rate'], 2 ) ) < 0.001, 'product-only sync uses fresh cached catalog' );
ok( 0 === p( $P2 )->get_stock_quantity() && 'outofstock' === p( $P2 )->get_stock_status(), 'including out-of-stock from cache' );
feed( array( 'set' => array( 'REF000000' => array( 'STOCK' => '3' ) ) ) );
run();
ok( 3 === p( $P1 )->get_stock_quantity(), 'back to normal after a good download' );

/* ------------------------------------------------------------------ L */
section( 'L. Truncated / malformed XML' );
feed( array( 'truncate' => 1, 'set' => array( 'REF000000' => array( 'STOCK' => '9' ) ) ) );
$r = run();
ok( false === $r['feed_ok'] && false !== strpos( feed_status()['last_error'], 'malformed' ), 'parse error detected', feed_status()['last_error'] );
ok( 3 === (int) cat( 0 )['stock'] && 3 === p( $P1 )->get_stock_quantity(), 'no partial data applied' );
ok( 1500 === URME_SS_DB::catalog_counts()['in_feed'], 'nothing marked missing' );

/* ------------------------------------------------------------------ M */
section( 'M. Suspiciously small feed' );
feed( array( 'keep_watches' => 100, 'set' => array( 'REF000000' => array( 'STOCK' => '3' ) ) ) );
$r = run();
ok( false === $r['feed_ok'] && false !== strpos( feed_status()['last_error'], 'Accept current feed' ), 'size drop refused', feed_status()['last_error'] );
ok( 1500 === URME_SS_DB::catalog_counts()['in_feed'], 'catalog intact' );
$r = run( array( 'force_feed' => true, 'sync_products' => false ) );
ok( true === $r['feed_ok'] && 100 === URME_SS_DB::catalog_counts()['in_feed'], '"Accept current feed" works', URME_SS_DB::catalog_counts() );
feed( array( 'set' => array( 'REF000000' => array( 'STOCK' => '3' ) ) ) );
run();
ok( 1500 === URME_SS_DB::catalog_counts()['in_feed'], 'full feed restored' );

/* ------------------------------------------------------------------ N */
section( 'N. Feed without any WATCH items' );
feed( array( 'catmap' => array( 'WATCH' => 'WATCHES' ) ) );
$r = run();
ok( false === $r['feed_ok'] && false !== strpos( feed_status()['last_error'], 'none are in category' ), 'refused', feed_status()['last_error'] );
ok( 1500 === URME_SS_DB::catalog_counts()['in_feed'], 'catalog intact' );
feed( array( 'set' => array( 'REF000000' => array( 'STOCK' => '3' ) ) ) );
run();

/* ------------------------------------------------------------------ O */
section( 'O. Stale catalog protection' );
$st                         = get_option( URME_SS_Sync::STATUS_OPTION );
$st['feed']['last_success'] = time() - 5 * HOUR_IN_SECONDS;
update_option( URME_SS_Sync::STATUS_OPTION, $st, false );
$wpdb->update( URME_SS_DB::catalog_table(), array( 'stock' => 42 ), array( 'item_key' => key_of( 0 ) ) );
mode( 'http500' );
run();
ok( false !== strpos( sync_stats()['skipped'], 'could not be downloaded' ), 'full run with failed feed: skipped', sync_stats()['skipped'] );
ok( 3 === p( $P1 )->get_stock_quantity(), 'stale data not pushed (full run)' );
run( array( 'refresh_feed' => false ) );
ok( false !== strpos( sync_stats()['skipped'], 'not been refreshed' ), 'product-only sync: max-age rule skips stale catalog', sync_stats()['skipped'] );
ok( 3 === p( $P1 )->get_stock_quantity(), 'stale data not pushed (product-only)' );
mode( 'ok' );
run( array( 'force_feed' => true ) );
ok( 3 === p( $P1 )->get_stock_quantity() && '' === sync_stats()['skipped'], 'fresh feed -> sync resumes' );

/* ------------------------------------------------------------------ P */
section( 'P. Locking' );
$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES ('urme_ss_lock', %s, 'off')", (string) time() ) );
$r = run();
ok( false === $r['ran'], 'second run refused while locked' );
ok( URME_SS_Sync::is_running(), 'is_running reports lock' );
$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = 'urme_ss_lock'", (string) ( time() - 3600 ) ) );
$r = run( array( 'refresh_feed' => false ) );
ok( true === $r['ran'], 'stale lock (1 h) is taken over' );
ok( null === $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'urme_ss_lock'" ), 'lock released after run' );

/* ------------------------------------------------------------------ Q */
section( 'Q. Conditional download (ETag / 304) and unchanged content' );
mode( 'etag' );
run( array( 'force_feed' => true ) );
$r = run();
ok( false !== strpos( feed_status()['last_result'], '304' ), 'HTTP 304 handled', feed_status()['last_result'] );
mode( 'ok' );
run();
$r = run();
ok( false !== strpos( feed_status()['last_result'], 'unchanged' ), 'identical content skipped', feed_status()['last_result'] );

/* ------------------------------------------------------------------ R */
section( 'R. Exchange rate sources and safety' );
delete_option( URME_SS_Rates::OPTION );
update_option( 'urme_test_rate_mode', 'riksbank' );
URME_SS_Rates::maybe_refresh( true );
ok( 'Sveriges Riksbank' === URME_SS_Rates::current()['source'], 'Riksbank fallback when ECB fails', URME_SS_Rates::current() );
update_option( 'urme_test_rate_mode', 'none' );
ok( false === URME_SS_Rates::maybe_refresh( true ) && abs( URME_SS_Rates::current()['rate'] - 11.0312 ) < 0.0001, 'both down -> previous rate kept' );
ok( '' !== URME_SS_Rates::state()['last_error'], 'rate error recorded' );
update_option( 'urme_test_rate_mode', 'jump' );
URME_SS_Rates::maybe_refresh( true );
ok( abs( URME_SS_Rates::current()['rate'] - 11.0312 ) < 0.0001, '>15% jump from ECB rejected (fallback used)', URME_SS_Rates::current() );
ok( false === URME_SS_Rates::maybe_refresh(), 'not refetched within 12 h' );
update_option( 'urme_test_rate_mode', 'ecb' );
settings( array( 'rate_override' => '12' ) );
run( array( 'refresh_feed' => false ) );
ok( abs( p( $P1 )->get_cogs_value() - round( feed_price( 0 ) * 12, 2 ) ) < 0.001, 'manual override used for cost', p( $P1 )->get_cogs_value() );
settings( array( 'rate_override' => '' ) );
URME_SS_Rates::maybe_refresh( true );

/* ------------------------------------------------------------------ S */
section( 'S. Other cost fields (dynamic detection)' );
settings( array( 'cost_target' => 'meta', 'cost_meta_key' => '_my_cost' ) );
run( array( 'refresh_feed' => false ) );
ok( get_post_meta( $P1, '_my_cost', true ) === wc_format_decimal( round( feed_price( 0 ) * 11.025, 2 ), 2 ), 'custom meta key written', get_post_meta( $P1, '_my_cost', true ) );
cogs( false );
update_post_meta( $P5a, '_alg_wc_cog_cost', '100' );
update_post_meta( $P5b, '_alg_wc_cog_cost', '200' );
settings( array( 'cost_target' => 'auto', 'cost_meta_key' => '' ) );
delete_transient( URME_SS_Store::INSPECT_TRANSIENT );
$t = URME_SS_Store::cost_target();
ok( 'meta' === $t['type'] && '_alg_wc_cog_cost' === $t['key'], 'auto detects existing cost plugin field', $t );
run( array( 'refresh_feed' => false ) );
ok( get_post_meta( $P1, '_alg_wc_cog_cost', true ) === wc_format_decimal( round( feed_price( 0 ) * 11.025, 2 ), 2 ), 'written to detected field' );
ok( '100' === get_post_meta( $P5a, '_alg_wc_cog_cost', true ), 'unlinked product cost untouched' );
update_post_meta( $P5a, '_some_inkop_cost', '5' );
delete_transient( URME_SS_Store::INSPECT_TRANSIENT );
ok( isset( URME_SS_Store::inspect()['other_keys']['_some_inkop_cost'] ), 'unknown cost-like meta listed for review' );
cogs( true );
delete_transient( URME_SS_Store::INSPECT_TRANSIENT );
$t = URME_SS_Store::cost_target();
ok( 'meta' === $t['type'], 'auto keeps the plugin field while it holds the most values', $t );
foreach ( array( $P1, $P2, $P3, $P4, $P7, $V6 ) as $id ) {
	delete_post_meta( $id, '_alg_wc_cog_cost' );
}
delete_transient( URME_SS_Store::INSPECT_TRANSIENT );
$t = URME_SS_Store::cost_target();
ok( 'wc_cogs' === $t['type'], 'auto switches to COGS once it holds the most values', $t );
delete_post_meta( $P5a, '_alg_wc_cog_cost' );
delete_post_meta( $P5b, '_alg_wc_cog_cost' );
delete_transient( URME_SS_Store::INSPECT_TRANSIENT );
settings( array( 'cost_target' => 'wc_cogs' ) );
run( array( 'refresh_feed' => false ) );

/* ------------------------------------------------------------------ T */
section( 'T. Additive variation cost (WooCommerce 10+/11)' );
$v = p( $V6 );
if ( method_exists( $v, 'set_cogs_value_is_additive' ) ) {
	$v->set_cogs_value_is_additive( true );
	$v->save();
	$cost_before = p( $V6 )->get_cogs_value();
	feed( array( 'set' => array( 'REF000000' => array( 'STOCK' => '3' ), 'REF000018' => array( 'STOCK' => '4', 'PURCHASE_PRICE' => '123.45' ) ) ) );
	run();
	ok( p( $V6 )->get_cogs_value() === $cost_before, 'additive variation cost not overwritten', array( p( $V6 )->get_cogs_value(), $cost_before ) );
	ok( 4 === p( $V6 )->get_stock_quantity(), 'its stock still synced' );
	ok( 'error' === link_of( 18 )['last_status'] && false !== strpos( link_of( 18 )['last_message'], 'added to the parent' ), 'explained on the link', link_of( 18 )['last_message'] );
	$v = p( $V6 );
	$v->set_cogs_value_is_additive( false );
	$v->save();
	run( array( 'refresh_feed' => false ) );
	ok( abs( p( $V6 )->get_cogs_value() - round( 123.45 * 11.025, 2 ) ) < 0.001 && 'ok' === link_of( 18 )['last_status'], 'non-additive variation cost synced' );
} else {
	echo "  (skipped: WooCommerce has no additive COGS)\n";
}

/* ------------------------------------------------------------------ U */
section( 'U. Linked product deleted, pause, unlink' );
wp_trash_post( $P7 );
run( array( 'refresh_feed' => false ) );
ok( sync_stats()['errors'] >= 1 && 'error' === link_of( 24 )['last_status'], 'trashed product reported as error', sync_stats()['error_list'] );
URME_SS_DB::update_link( link_of( 2 )['id'], array( 'sync_enabled' => 0 ) );
$q3 = p( $P3 )->get_stock_status();
feed( array( 'set' => array( 'REF000000' => array( 'STOCK' => '3' ), 'REF000002' => array( 'STOCK' => '0' ) ) ) );
run();
ok( p( $P3 )->get_stock_status() === $q3 && 1 === sync_stats()['paused'], 'paused link not synced' );

/* ------------------------------------------------------------------ V */
section( 'V. Scheduled (cron) run' );
settings( array( 'auto_sync' => 1 ) );
ok( (bool) wp_next_scheduled( URME_SS_Plugin::CRON_HOOK ), 'hourly event scheduled' );
$before_run = sync_stats()['last_run'];
sleep( 1 );
do_action( URME_SS_Plugin::CRON_HOOK );
URME_SS_Log::flush();
ok( sync_stats()['last_run'] > $before_run && 'cron' === sync_stats()['trigger'], 'cron runs refresh + sync' );
settings( array( 'auto_sync' => 0 ) );
$before_run = sync_stats()['last_run'];
sleep( 1 );
do_action( URME_SS_Plugin::CRON_HOOK );
ok( sync_stats()['last_run'] === $before_run, 'auto sync off -> cron does nothing' );
settings( array( 'auto_sync' => 1 ) );

/* ------------------------------------------------------------------ W */
section( 'W. Protected fields never changed' );
foreach ( array( $P1, $P2, $P3, $P4, $V6 ) as $id ) {
	ok( $snapshot( $id ) === $before[ $id ], "product #$id: title/prices/descriptions/SKU/image/categories/attributes unchanged" );
}
ok( 4 === p( $P8 )->get_stock_quantity() && '' === get_post_meta( $P8, '_urme_supplier_cost_eur', true ), 'unlinked product never touched' );
ok( '2999' === p( $P1 )->get_regular_price() && '2499' === p( $P1 )->get_sale_price(), 'selling prices intact' );

/* ------------------------------------------------------------------ X */
section( 'X. Search' );
$r = URME_SS_DB::search_catalog( array( 'brand' => 'Seiko', 'page' => 1, 'per_page' => 50 ) );
ok( 500 === $r['total'] && 50 === count( $r['rows'] ), 'brand filter + paging', $r['total'] );
ok( 1 === URME_SS_DB::search_catalog( array( 'productno' => 'REF000006' ) )['total'], 'PRODUCTNO search' );
ok( 1 === URME_SS_DB::search_catalog( array( 'ean' => '4900000000018' ) )['total'], 'EAN search' );
ok( 7 === URME_SS_DB::search_catalog( array( 'selected' => 'yes' ) )['total'], 'selected filter' );

// Local first → Supplier automatically (1.1.0).
require __DIR__ . '/local-first.php';
// Admin-only fulfilment source and price reviews (1.1.0).
require __DIR__ . '/fulfillment-review.php';
// Admin-only NEW supplier product indicator (1.1.0).
require __DIR__ . '/new-products.php';
// Admin-only selling price hint (1.2.0).
require __DIR__ . '/price-hint.php';
// Regression: brand kept on settings save; linked Supplier-now watch gets stock and cost (production SKU 1513905).
require __DIR__ . '/brand-sync.php';
// Supplier catalog: URME stock column, per-product start/resume/sync controls, filters.
require __DIR__ . '/catalog-actions.php';
// Fulfillment badge (Products list + catalog) and manual sale price editor.
require __DIR__ . '/product-admin.php';
// AJAX row actions; local URME stock always has priority over Dropshipping.
require __DIR__ . '/ajax-local.php';
// Bulk selection follows local stock priority; Fulfillment filter and counts on WooCommerce > Products.
require __DIR__ . '/bulk-fulfillment.php';
// 1.4.1: no ThemeComplete gift wrap (Presentinslagning) for true Dropshipping products.
require __DIR__ . '/gift-wrap.php';
// 1.5: two Fulfillment states only (URME Lager / Dropshipping).
require __DIR__ . '/two-state.php';
// 1.5.5: "Move to URME Lager" in WooCommerce Quick Edit.
require __DIR__ . '/quick-edit.php';

echo "\nRESULT: " . $GLOBALS["PASS"] . " passed, " . $GLOBALS["FAIL"] . " failed\n";
