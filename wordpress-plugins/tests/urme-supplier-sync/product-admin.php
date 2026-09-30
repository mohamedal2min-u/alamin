<?php
/**
 * Fulfillment badge (WooCommerce > Products and Supplier catalog) and the manual sale price
 * editor. Included after catalog-actions.php; uses the helpers of the files before it.
 */

global $wpdb;

function pa_badges( $html ) {
	preg_match_all( '#<span class="urme-src urme-src-[a-z_]+">([^<]+)</span>#', $html, $m );
	return $m[1];
}
function pa_column( $pid ) {
	URME_SS_Product_Source::flush();
	set_current_screen( 'edit-product' );
	ob_start();
	do_action( 'manage_product_posts_custom_column', 'urme_source', $pid );
	$html = ob_get_clean();
	$GLOBALS['current_screen'] = null;
	return $html;
}
function pa_badge( $pid ) {
	$b = pa_badges( pa_column( $pid ) );
	return implode( ' | ', $b );
}
function pa_sale( $i, $value ) {
	return admin( 'save_sale_price', key_of( $i ), $value );
}
function pa_snapshot( $pid, $i ) {
	$p = p( $pid );
	return array(
		'regular' => $p->get_regular_price(),
		'sale'    => $p->get_sale_price(),
		'price'   => $p->get_price(),
		'stock'   => $p->get_stock_quantity(),
		'status'  => $p->get_stock_status(),
		'cogs'    => (float) $p->get_cogs_value(),
		'link'    => URME_SS_DB::get_link( key_of( $i ) ),
	);
}

/* ------------------------------------------------------------------ setup */
section( 'PA0. Fulfillment badge and sale price editor setup' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
settings( array( 'cost_target' => 'wc_cogs', 'rate_override' => '11.321' ) );
URME_SS_Product_Source::init();
$ZL = bs_product( 'REF000228', key_of( 228 ), 3, 700 );  // → URME Lager (3).
$ZP = bs_product( 'REF000234', key_of( 234 ), 0, 500 );  // → Dropshipping, then paused as an earlier version could.
$ZO = bs_product( 'OWN-0001', '', 0, 900 );              // URME's own watch, no supplier link, stock 0.
$v2 = new WC_Product_Variation();                        // A second variation of the variable product.
$v2->set_parent_id( $VP );
$v2->set_attributes( array( 'size' => 'L' ) );
$v2->set_sku( 'VAR-L-210' );
$v2->set_regular_price( '3990' );
$v2->set_sale_price( '3590' );
$v2->set_manage_stock( true );
$v2->set_stock_quantity( 1 );
$ZV2   = $v2->save();
$trash = bs_product( 'REF000240', key_of( 240 ), 0, 100 ); // Matched, then trashed below.
$PA_SET = array(
	'REF000180' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '12', 'PURCHASE_PRICE' => '200.00' ),
	'REF000228' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '5', 'PURCHASE_PRICE' => '100.00' ),
	'REF000234' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '6', 'PURCHASE_PRICE' => '110.00' ),
	'REF000240' => array( 'MANUFACTURER' => 'BOSS' ),
);
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, $PA_SET ) );
run( array( 'force_feed' => true ) );
ok( 'success' === ca_act( 'start_local', 228 )[1] && 'success' === ca_act( 'start_supplier', 234 )[1], '   (REF000228 Local first with 3 units, REF000234 Supplier now)' );
$lp = URME_SS_DB::get_link( key_of( 234 ) );
$wpdb->update( URME_SS_DB::links_table(), array( 'sync_enabled' => 0 ), array( 'id' => (int) $lp['id'] ) ); // Legacy paused link (Pause no longer exists).
wp_trash_post( $trash );
$http = 0;
$count_http = static function ( $pre ) use ( &$http ) {
	++$http;
	return $pre;
};
add_filter( 'pre_http_request', $count_http );

section( 'PA1–4. Fulfillment badge (WooCommerce > Products)' );
$cols = apply_filters( 'manage_edit-product_columns', array( 'cb' => '', 'name' => 'Name', 'sku' => 'SKU', 'is_in_stock' => 'Stock', 'price' => 'Price', 'date' => 'Date' ) );
ok( array( 'cb', 'name', 'sku', 'is_in_stock', 'urme_source', 'price', 'date' ) === array_keys( $cols ) && 'Fulfillment' === $cols['urme_source'], '"Fulfillment" column after Stock', array_keys( $cols ) );
ok( 'Dropshipping' === pa_badge( $Z0 ), '1. Supplier now, brand on → Dropshipping', pa_badge( $Z0 ) );
ok( 'URME Lager' === pa_badge( $ZL ), '2. URME Lager link with 3 units → URME Lager', pa_badge( $ZL ) );
ok( 'URME Lager' === pa_badge( $ZP ), '3. legacy paused link → URME Lager', pa_badge( $ZP ) );
ok( 'URME Lager' === pa_badge( $ZO ) && 'URME Lager' === pa_badge( $ZB ), '4. no Supplier Sync link → URME Lager (also at stock 0, and for a matched but not selected product)', array( pa_badge( $ZO ), pa_badge( $ZB ) ) );
ok( false !== strpos( pa_column( $VP ), 'Dropshipping</span> <small class="urme-src-note">1 variation</small>' ), '   variable product: shows its supplier-linked variation (Dropshipping, 1 variation)', ff_text( pa_column( $VP ) ) );
URME_SS_Settings::set_brand( 'BOSS', false );
ok( 'URME Lager' === pa_badge( $Z0 ), '   supplier link with brand sync off → URME Lager (not Dropshipping)', pa_badge( $Z0 ) );
URME_SS_Settings::set_brand( 'BOSS', true );
$html = ph_catalog( array( 'brand' => 'BOSS' ) );
ok( array( 'Dropshipping' ) === pa_badges( ca_row( $html, 'REF000180' ) ) && array( 'URME Lager' ) === pa_badges( ca_row( $html, 'REF000228' ) ) && array( 'URME Lager' ) === pa_badges( ca_row( $html, 'REF000234' ) ) && array( 'URME Lager' ) === pa_badges( ca_row( $html, 'REF000216' ) ), 'Supplier catalog shows the same badge for matched products' );
ok( array() === pa_badges( ca_row( $html, 'REF000192' ) ) && array() === pa_badges( ca_row( $html, 'REF000198' ) ), '   no badge for Not in URME / Needs review' );

section( 'PA5–10. Manual sale price: 4490 → 4290' );
$r = ca_row( $html, 'REF000180' );
ok( false !== strpos( ff_text( $r ), 'Regular: 4,990 kr' ) && false !== strpos( $r, 'name="sale_price[' . key_of( 180 ) . ']" value="4490"' ) && false !== strpos( $r, 'value="sale|' . key_of( 180 ) . '"' ), 'catalog row: Regular 4,990 kr (read-only), Sale price [4490] [Save]' );
ok( false === strpos( ca_row( $html, 'REF000192' ), 'sale_price[' ) && false === strpos( ca_row( $html, 'REF000198' ), 'sale_price[' ), '   no editor for Not in URME / Needs review' );
ok( false !== strpos( ca_row( $html, 'REF000234' ), 'sale_price[' . key_of( 234 ) . ']' ), '   editor also for a Paused product' );
$before = pa_snapshot( $Z0, 180 );
$res    = pa_sale( 180, '4290' );
$after  = pa_snapshot( $Z0, 180 );
ok( 'success' === $res[1] && '4290' === $after['sale'] && '4290' === $after['price'], '5. sale price 4490 → 4290: ' . $res[0], $res );
ok( '4990' === $after['regular'], '6. regular price unchanged (4990)' );
ok( $before['stock'] === $after['stock'] && $before['status'] === $after['status'], '7. stock unchanged (' . $after['stock'] . ')' );
ok( abs( $before['cogs'] - $after['cogs'] ) < 0.0001, '8. COGS unchanged (' . $after['cogs'] . ')' );
ok( $before['link']['stock_mode'] === $after['link']['stock_mode'] && $before['link']['sync_enabled'] === $after['link']['sync_enabled'], '9. Supplier Sync mode unchanged' );
ok( $before['link'] === $after['link'], '10. supplier link row unchanged (all fields)' );

section( 'PA11–13. Clear, invalid, above regular' );
$res = pa_sale( 180, '' );
$s   = pa_snapshot( $Z0, 180 );
ok( 'success' === $res[1] && '' === $s['sale'] && '4990' === $s['price'] && ! p( $Z0 )->is_on_sale() && '4990' === $s['regular'], '11. cleared: no sale price, sells at regular 4,990', $res );
foreach ( array( 'abc', '-5', '12.345', '1e3', '4290kr' ) as $bad ) {
	$res = pa_sale( 180, $bad );
	ok( 'error' === $res[1] && '' === p( $Z0 )->get_sale_price(), '12. invalid "' . $bad . '" rejected, nothing changed', $res );
}
$res = pa_sale( 180, '5000' );
ok( 'error' === $res[1] && '' === p( $Z0 )->get_sale_price(), '13. sale 5,000 above regular 4,990 rejected', $res );
$res = pa_sale( 180, '4 290,50' );
ok( 'success' === $res[1] && '4290.50' === p( $Z0 )->get_sale_price(), '   "4 290,50" accepted as 4290.50', p( $Z0 )->get_sale_price() );
pa_sale( 180, '4490' );
ok( '4490' === p( $Z0 )->get_sale_price(), '   back to 4490' );

section( 'PA14. Variation: only the matched variation' );
$parent_before = array( p( $VP )->get_regular_price(), p( $VP )->get_sale_price() );
$res           = pa_sale( 210, '3290' );
ok( 'success' === $res[1] && '3290' === p( $ZV )->get_sale_price() && '3990' === p( $ZV )->get_regular_price(), '14. matched variation: sale 3490 → 3290, regular 3990 kept', $res );
ok( '3590' === p( $ZV2 )->get_sale_price() && $parent_before === array( p( $VP )->get_regular_price(), p( $VP )->get_sale_price() ), '   the other variation (3590) and the parent product not changed' );

section( 'PA15. Refused edits, paused product, no requests' );
ok( 'error' === pa_sale( 192, '100' )[1] && 'error' === pa_sale( 198, '100' )[1], 'Not in URME / Needs review: refused' );
$pp = pa_snapshot( $ZP, 234 );
$res = pa_sale( 234, '4190' );
$pa  = pa_snapshot( $ZP, 234 );
ok( 'success' === $res[1] && '4190' === $pa['sale'] && 0 === (int) $pa['link']['sync_enabled'] && $pp['stock'] === $pa['stock'] && abs( $pp['cogs'] - $pa['cogs'] ) < 0.0001, 'legacy paused product: sale price saved; still not synced, stock and COGS unchanged', $res );
ok( 'error' === pa_sale( 240, '100' )[1], 'deleted (trashed) product: refused' );
ok( 0 === $http, '15. no HTTP (supplier feed) request for any sale price save', $http );
remove_filter( 'pre_http_request', $count_http );
$sel = ph_text( ph_selected( 'REF000180' ) );
ok( false !== strpos( $sel, 'Regular: 4,990 kr Sale price' ) && false !== strpos( ph_selected( 'REF000180' ), 'name="do" value="save_sale"' ), 'Selected watches: the same sale price editor as its own small form', $sel );

section( 'PA16. Cron / supplier sync never writes regular or sale price' );
$writes = array();
$watch  = static function ( $check, $object_id, $meta_key ) use ( &$writes ) {
	if ( in_array( $meta_key, array( '_price', '_regular_price', '_sale_price' ), true ) ) {
		$writes[] = $object_id . ':' . $meta_key;
	}
	return $check;
};
add_filter( 'update_post_metadata', $watch, 10, 3 );
add_filter( 'add_post_metadata', $watch, 10, 3 );
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, $PA_SET, array( 'REF000180' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '9', 'PURCHASE_PRICE' => '210.00' ) ) ) );
do_action( URME_SS_Plugin::CRON_HOOK );
URME_SS_Log::flush();
remove_filter( 'update_post_metadata', $watch, 10 );
remove_filter( 'add_post_metadata', $watch, 10 );
ok( 9 === p( $Z0 )->get_stock_quantity() && array() === $writes && '4490' === p( $Z0 )->get_sale_price() && '4990' === p( $Z0 )->get_regular_price() && '3290' === p( $ZV )->get_sale_price(), '16. cron synced stock 9 but wrote no price field; manual sale prices kept', $writes );

section( 'PA17. No per-row queries (Products list and Supplier catalog)' );
for ( $k = 0; $k < 100; $k++ ) { // Enough products for a 100-row list.
	$x = new WC_Product_Simple();
	$x->set_name( 'List filler ' . $k );
	$x->set_regular_price( '100' );
	$x->save();
}
$res = array();
foreach ( array( 20, 50, 100 ) as $n ) {
	URME_SS_Product_Source::flush();
	set_current_screen( 'edit-product' );
	$saved                   = $GLOBALS['wp_the_query'];
	$q                       = new WP_Query();
	$GLOBALS['wp_the_query'] = $q;
	$q0                      = $wpdb->num_queries;
	$posts                   = $q->query( array( 'post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => $n, 'orderby' => 'ID', 'order' => 'ASC' ) );
	$q1                      = $wpdb->num_queries;
	$GLOBALS['wp_the_query'] = $saved;
	ob_start();
	foreach ( $posts as $post ) {
		do_action( 'manage_product_posts_custom_column', 'urme_source', $post->ID );
	}
	$h                       = ob_get_clean();
	$GLOBALS['current_screen'] = null;
	$res[ $n ]               = array( count( $posts ), $wpdb->num_queries - $q1, count( pa_badges( $h ) ) );
	URME_SS_Product_Source::flush();
	$p0 = $wpdb->num_queries;
	URME_SS_Product_Source::prime( wp_list_pluck( $posts, 'ID' ) );
	$res[ $n ][] = $wpdb->num_queries - $p0;
}
ok( 20 === $res[20][0] && 50 === $res[50][0] && 100 === $res[100][0] && 0 === $res[20][1] + $res[50][1] + $res[100][1], sprintf( '17. Products list 20 / 50 / 100 rows: %d / %d / %d queries while rendering the Fulfillment column', $res[20][1], $res[50][1], $res[100][1] ), $res );
ok( 100 === $res[100][2] && 1 === $res[20][3] && 1 === $res[50][3] && 1 === $res[100][3], '   every row has a badge; bulk load = 1 query for 20, 50 and 100 products', $res );
$counts = array();
foreach ( array( 20, 50, 100 ) as $n ) {
	ph_catalog( array( 'per_page' => $n ) );
	$q0           = $wpdb->num_queries;
	$h            = ph_catalog( array( 'per_page' => $n ) );
	$counts[ $n ] = array( $wpdb->num_queries - $q0, substr_count( $h, 'name="sale_price[' ) );
}
ok( $counts[20][0] === $counts[50][0] && $counts[50][0] === $counts[100][0], sprintf( '   Supplier catalog with badges and sale editors, 20 / 50 / 100 rows: %d / %d / %d queries (same)', $counts[20][0], $counts[50][0], $counts[100][0] ), $counts );
settings( array( 'rate_override' => '' ) );
