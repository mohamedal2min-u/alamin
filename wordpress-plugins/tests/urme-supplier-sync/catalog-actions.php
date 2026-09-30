<?php
/**
 * Supplier catalog: "URME stock" column, per-product Start supplier sync / Use Local first /
 * Resume / Sync now, and the URME stock + "Ready for supplier sync" filters.
 * Included after brand-sync.php; uses the helpers of the files before it.
 */

global $wpdb;

function ca_row( $html, $product_no ) {
	foreach ( explode( '<tr', $html ) as $chunk ) {
		if ( false !== strpos( $chunk, '<code>' . $product_no . '</code>' ) ) {
			return $chunk;
		}
	}
	return '';
}
function ca_stock_cell( $row_html ) {
	// Stock text only; the Fulfillment badge after it is checked in product-admin.php.
	return preg_match( '#<td class="num urme-stock-col">(.*?)</td>#s', $row_html, $m ) ? ff_text( str_replace( '<br>', ' ', preg_replace( '#(<br>)?<span class="urme-src.*$#s', '', $m[1] ) ) ) : null;
}
function ca_act( $action, $i ) {
	return admin( 'run_row_action', $action . '|' . key_of( $i ) );
}
function ca_keys( array $get ) {
	$rows = URME_SS_DB::search_catalog( $get + array( 'per_page' => 200 ) )['rows'];
	$keys = wp_list_pluck( $rows, 'product_no' );
	sort( $keys );
	return $keys;
}
function ca_prices_ok( $pid, $regular, $sale ) {
	$p = p( $pid );
	return $regular === $p->get_regular_price() && $sale === $p->get_sale_price();
}

/* ------------------------------------------------------------------ setup */
section( 'CA0. Catalog actions setup' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
cogs( true );
settings( array( 'cost_target' => 'wc_cogs', 'rate_override' => '11.321', 'manage_stock' => 1 ) );
URME_SS_Settings::set_brand( 'BOSS', true );
URME_SS_Settings::set_brand( 'Casio', false );
$rate = 11.321;

$Z0 = bs_product( 'REF000180', key_of( 180 ), 0, 480 );  // URME stock 0 → Start supplier sync.
$Z3 = bs_product( 'REF000186', key_of( 186 ), 3, 650 );  // URME stock 3 → Use Local first.
$R1 = bs_product( 'REF000198', '', 1, 100 );              // Needs review: one product by SKU…
$R2 = bs_product( 'OTHER-198', key_of( 198 ), 1, 100 );   // …another by EAN.
$ZC = bs_product( 'REF000204', key_of( 204 ), 0, 300 );  // Brand Casio, sync disabled.
$ZB = bs_product( 'REF000216', key_of( 216 ), 2, 400 );  // Backorders allowed.
$b  = wc_get_product( $ZB );
$b->set_backorders( 'yes' );
$b->save();
$ZU = bs_product( 'REF000222', key_of( 222 ), 0, 200 );  // Stock not managed.
$u  = wc_get_product( $ZU );
$u->set_manage_stock( false );
$u->set_stock_status( 'instock' );
$u->save();
// Variable product: the match is the exact variation (stock 0), not its parent.
$vp  = new WC_Product_Variable();
$vp->set_name( 'BOSS variable parent' );
$vp->set_sku( 'VARP-210' );
$att = new WC_Product_Attribute();
$att->set_name( 'Size' );
$att->set_options( array( 'M', 'L' ) );
$att->set_visible( true );
$att->set_variation( true );
$vp->set_attributes( array( $att ) );
$VP = $vp->save();
$v  = new WC_Product_Variation();
$v->set_parent_id( $VP );
$v->set_attributes( array( 'size' => 'M' ) );
$v->set_sku( 'REF000210' );
$v->set_global_unique_id( key_of( 210 ) );
$v->set_regular_price( '3990' );
$v->set_sale_price( '3490' );
$v->set_manage_stock( true );
$v->set_stock_quantity( 0 );
$v->set_cogs_value( 350 );
$ZV = $v->save();

$CA_SET = array(
	'REF000180' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' ),
	'REF000186' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '5', 'PURCHASE_PRICE' => '100.00' ),
	'REF000192' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '4', 'PURCHASE_PRICE' => '90.00' ),
	'REF000198' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '4', 'PURCHASE_PRICE' => '90.00' ),
	'REF000204' => array( 'MANUFACTURER' => 'Casio', 'STOCK' => '6', 'PURCHASE_PRICE' => '80.00' ),
	'REF000210' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '7', 'PURCHASE_PRICE' => '120.00' ),
	'REF000216' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '5', 'PURCHASE_PRICE' => '60.00' ),
	'REF000222' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '5', 'PURCHASE_PRICE' => '60.00' ),
);
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET ) );
run( array( 'force_feed' => true ) );
ok( 'exists' === cat( 180 )['match_status'] && $Z0 === (int) cat( 180 )['match_product_id'] && $ZV === (int) cat( 210 )['match_product_id'] && 'none' === cat( 192 )['match_status'] && 'review' === cat( 198 )['match_status'], 'matches: REF000180 → product, REF000210 → the variation, REF000192 Not in URME, REF000198 Needs review' );
$writes = array();
$watch  = static function ( $check, $object_id, $meta_key ) use ( &$writes ) {
	if ( in_array( $meta_key, array( '_price', '_regular_price', '_sale_price' ), true ) ) {
		$writes[] = $object_id . ':' . $meta_key;
	}
	return $check;
};
add_filter( 'update_post_metadata', $watch, 10, 3 );
add_filter( 'add_post_metadata', $watch, 10, 3 );
$http = 0;
$count_http = static function ( $pre ) use ( &$http ) {
	++$http;
	return $pre;
};
add_filter( 'pre_http_request', $count_http );

section( 'CA1. URME stock column and per-row controls' );
$html = ph_catalog( array( 'brand' => 'BOSS' ) );
$http_render = $http;
ok( 1 === preg_match( '#<th class="num">Supplier stock</th>\s*<th class="num urme-stock-col">URME stock</th>#', $html ), '"URME stock" column right after "Supplier stock"' );
$r = ca_row( $html, 'REF000180' );
ok( '0 / Out of stock' === ca_stock_cell( $r ) && false !== strpos( $r, 'urme-bad">0 / Out of stock' ), 'stock 0: red "0 / Out of stock"', ca_stock_cell( $r ) );
ok( false !== strpos( $r, 'value="start_supplier|' . key_of( 180 ) . '"' ) && false === strpos( $r, 'start_local|' ), '   → "Start supplier sync" only' );
$r = ca_row( $html, 'REF000186' );
ok( '3' === ca_stock_cell( $r ) && false !== strpos( $r, 'urme-good">3<' ), 'stock 3: green 3', ca_stock_cell( $r ) );
ok( false === strpos( $r, 'start_local|' ) && false === strpos( $r, 'start_supplier|' ) && false !== strpos( $r, 'URME Lager (3 in stock) – Dropshipping is possible at stock 0' ), '   → no button (stays URME Lager), note shown' );
$r = ca_row( $html, 'REF000210' );
ok( '0 / Out of stock' === ca_stock_cell( $r ) && false !== strpos( $r, 'start_supplier|' . key_of( 210 ) ), 'variation: its own stock (0) shown, Start supplier sync offered', ca_stock_cell( $r ) );
$r = ca_row( $html, 'REF000216' );
ok( '2 Backorders allowed' === ca_stock_cell( $r ) && false === strpos( $r, 'start_local|' ) && false === strpos( $r, 'start_supplier|' ) && false !== strpos( $r, 'URME Lager (2 in stock)' ), 'backorders: stock 2 + "Backorders allowed"; no button, note shown', ca_stock_cell( $r ) );
$r = ca_row( $html, 'REF000222' );
ok( 0 === strpos( (string) ca_stock_cell( $r ), 'Not managed' ) && false === strpos( $r, 'start_' ), 'stock not managed: "Not managed", no button', ca_stock_cell( $r ) );
$r = ca_row( $html, 'REF000192' );
ok( '—' === ca_stock_cell( $r ) && false === strpos( $r, 'start_' ), 'Not in URME: no stock, no button' );
$r = ca_row( $html, 'REF000198' );
ok( '—' === ca_stock_cell( $r ) && false === strpos( $r, 'start_' ) && false !== strpos( $r, 'Needs review' ), 'Needs review: no stock, no button' );
$r = ca_row( $html, 'REF000132' );
ok( false !== strpos( ff_text( $r ), 'Dropshipping' ) && false !== strpos( $r, 'value="sync|' ) && false !== strpos( $r, 'value="lager|' ), 'already Dropshipping: "Dropshipping" + "Sync now" + "URME Lager"', ff_text( $r ) );
$rc = ca_row( ph_catalog( array( 'brand' => 'Casio' ) ), 'REF000204' );
ok( '0 / Out of stock' === ca_stock_cell( $rc ) && false === strpos( $rc, 'start_' ), 'brand sync disabled (Casio): stock shown, no sync button', ca_stock_cell( $rc ) );

section( 'CA2. Filters' );
ok( array( 'REF000180', 'REF000210' ) === ca_keys( array( 'brand' => 'BOSS', 'urme_stock' => 'out' ) ), 'URME stock: Out of stock', ca_keys( array( 'brand' => 'BOSS', 'urme_stock' => 'out' ) ) );
ok( array( 'REF000132', 'REF000138', 'REF000186', 'REF000216' ) === ca_keys( array( 'brand' => 'BOSS', 'urme_stock' => 'in' ) ), 'URME stock: In stock', ca_keys( array( 'brand' => 'BOSS', 'urme_stock' => 'in' ) ) );
ok( array( 'REF000222' ) === ca_keys( array( 'brand' => 'BOSS', 'urme_stock' => 'unmanaged' ) ), 'URME stock: Not managed' );
$ready = ca_keys( array( 'ready' => 1, 'brand' => 'BOSS' ) );
ok( array( 'REF000180', 'REF000210' ) === $ready, 'Ready for supplier sync: unique match + stock 0 + not selected + in feed (not review/none, not stock > 0, not selected, not unmanaged)', $ready );
ok( array() === ca_keys( array( 'ready' => 1, 'brand' => 'Casio' ) ) && array( 'REF000204' ) === ca_keys( array( 'urme_stock' => 'out', 'brand' => 'Casio' ) ), '   brand sync disabled (Casio, stock 0) is not "ready"' );
ok( $http_render === 0, 'no HTTP request to render the catalog', $http_render );

section( 'CA3. URME stock 0 → Start supplier sync' );
$res = ca_act( 'start_supplier', 180 );
$st  = bs_state( $Z0 );
$l   = URME_SS_DB::get_link( key_of( 180 ) );
ok( 'success' === $res[1], 'started: ' . $res[0], $res );
ok( 8 === $st['stock'] && 'instock' === $st['status'], 'WooCommerce stock 0 → 8, in stock', $st );
ok( abs( $st['cogs'] - round( 176 * $rate, 2 ) ) < 0.001, 'COGS 480 → ' . round( 176 * $rate, 2 ) . ' (176 EUR × rate, no +12 EUR)', $st );
ok( '4990' === $st['regular'] && '4490' === $st['sale'], 'regular and sale price unchanged' );
ok( $l && $Z0 === (int) $l['product_id'] && 'supplier' === $l['stock_mode'] && 1 === (int) $l['sync_enabled'] && 'ok' === $l['last_status'] && ! empty( $l['last_synced_at'] ), 'selected + linked to the matched product, Supplier now, synced', $l );
ok( 'warning' === ca_act( 'start_supplier', 180 )[1] && 8 === bs_state( $Z0 )['stock'], 'clicking again: "already selected", nothing changed' );

section( 'CA4. URME stock 3 → stays URME Lager (1.5.2: never selected)' );
$res = ca_act( 'start_local', 186 );
$st  = bs_state( $Z3 );
ok( 'success' !== $res[1] && null === URME_SS_DB::get_link( key_of( 186 ) ), 'old "Use Local first": nothing to start, not selected', $res );
ok( 3 === $st['stock'] && abs( $st['cogs'] - 650 ) < 0.001, 'WooCommerce stock stays 3 and COGS stays 650 (not supplier 5 / 100 EUR)', $st );
$res = ca_act( 'start_supplier', 186 );
run();
$st = bs_state( $Z3 );
ok( 'error' === $res[1] && null === URME_SS_DB::get_link( key_of( 186 ) ) && 3 === $st['stock'] && abs( $st['cogs'] - 650 ) < 0.001, '   Dropshipping at stock 3 refused; next sync: still 3 / 650', array( $res, $st ) );

section( 'CA5. Two states only: URME Lager / Dropshipping' );
$r  = ca_row( ph_catalog( array( 'brand' => 'BOSS' ) ), 'REF000186' );
$lz = URME_SS_DB::get_link( key_of( 180 ) );
ok( false !== strpos( ff_text( $r ), 'URME Lager' ) && false === strpos( $r, 'value="dropship|' ) && false === strpos( $r, 'value="sync|' ) && false === strpos( $r, 'resume|' ) && false === strpos( $r, 'lager|' ) && false === strpos( ff_text( $r ), 'Local first' ), 'URME Lager row: "URME Lager", no Dropshipping/Sync now/Resume/"Local first" button', ff_text( $r ) );
$res  = admin( 'set_mode', $lz, 'paused' );
$res2 = admin( 'run_row_action', 'resume|' . $lz['id'] );
ok( 'error' === $res[1] && 'error' === $res2[1] && 1 === (int) URME_SS_DB::get_link( key_of( 180 ) )['sync_enabled'] && 'supplier' === URME_SS_DB::get_link( key_of( 180 ) )['stock_mode'], 'Pause and Resume no longer exist; nothing changed', array( $res[0], $res2[0] ) );
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, array( 'REF000180' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '12', 'PURCHASE_PRICE' => '200.00' ) ) ) );
run();
ok( 12 === bs_state( $Z0 )['stock'] && abs( bs_state( $Z0 )['cogs'] - round( 200 * $rate, 2 ) ) < 0.001 && 'supplier' === URME_SS_DB::get_link( key_of( 180 ) )['stock_mode'], 'Dropshipping: supplier 12 / 200 EUR × rate synced', bs_state( $Z0 ) );
$res = admin( 'run_row_action', 'sync|' . $lz['id'] );
ok( 'success' === $res[1], '"Sync now" on the row runs the single-product sync', $res );

section( 'CA6. URME Lager sold to 0 → stays URME Lager until "Dropshipping" is clicked' );
lf_order( $Z3, 2 ); // URME Lager stock 3 → 1…
lf_order( $Z3, 1 ); // …→ 0.
run();
$st = bs_state( $Z3 );
ok( null === URME_SS_DB::get_link( key_of( 186 ) ) && 0 === $st['stock'] && abs( $st['cogs'] - 650 ) < 0.001, 'sold out: not selected, stock 0, COGS 650 (no automatic switch)', $st );
ok( false !== strpos( ca_row( ph_catalog( array( 'brand' => 'BOSS' ) ), 'REF000186' ), 'value="start_supplier|' . key_of( 186 ) . '"' ), '   the catalog row now offers "Dropshipping"' );
$res = ca_act( 'start_supplier', 186 );
$st  = bs_state( $Z3 );
ok( 'success' === $res[1] && 'supplier' === URME_SS_DB::get_link( key_of( 186 ) )['stock_mode'] && 5 === $st['stock'] && abs( $st['cogs'] - round( 100 * $rate, 2 ) ) < 0.001, '   clicked: Dropshipping, supplier stock 5, COGS 100 EUR × rate', array( $res, $st ) );

section( 'CA7. Variation: read and written on the matched variation' );
$res = ca_act( 'start_supplier', 210 );
$vv  = p( $ZV );
$vpp = p( $VP );
ok( 'success' === $res[1] && 7 === $vv->get_stock_quantity() && abs( (float) $vv->get_cogs_value() - round( 120 * $rate, 2 ) ) < 0.001, 'variation stock 0 → 7, COGS 120 EUR × rate', array( $res, $vv->get_stock_quantity(), $vv->get_cogs_value() ) );
ok( true !== $vpp->get_manage_stock() && null === $vpp->get_stock_quantity() && '3990' === $vv->get_regular_price() && '3490' === $vv->get_sale_price(), '   parent product not touched; variation prices unchanged' );

section( 'CA8. Refused, nothing written' );
$links_before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . URME_SS_DB::links_table() );
$res1 = ca_act( 'start_supplier', 204 ); // Brand disabled.
$res2 = ca_act( 'start_supplier', 192 ); // Not in URME.
$res3 = ca_act( 'start_supplier', 198 ); // Needs review.
$res4 = ca_act( 'start_local', 216 );    // Backorders allowed.
$res5 = ca_act( 'start_supplier', 216 ); // Stock 2, not 0 (stale page).
$res6 = ca_act( 'start_supplier', 222 ); // Stock not managed.
ok( 'error' === $res1[1] && 0 === bs_state( $ZC )['stock'] && abs( bs_state( $ZC )['cogs'] - 300 ) < 0.001, 'brand disabled: refused, stock 0 / COGS 300 untouched', $res1 );
ok( 'error' === $res2[1] && 'error' === $res3[1], 'Not in URME / Needs review: refused', array( $res2, $res3 ) );
ok( 'success' !== $res4[1] && 2 === bs_state( $ZB )['stock'] && abs( bs_state( $ZB )['cogs'] - 400 ) < 0.001, 'old "Use Local first" (backorders allowed): nothing started, nothing changed', $res4 );
ok( 'error' === $res5[1] && false !== strpos( $res5[0], 'Local URME stock exists (2 units)' ) && 2 === bs_state( $ZB )['stock'], 'local stock 2: Start supplier sync refused (local stock has priority)', $res5 );
ok( 'error' === $res6[1] && 'instock' === p( $ZU )->get_stock_status() && true !== p( $ZU )->get_manage_stock(), 'stock not managed: refused, product unchanged', $res6 );
ok( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . URME_SS_DB::links_table() ) === $links_before, '   no selection created by any refused action' );

section( 'CA9. Prices never change; no per-row queries' );
remove_filter( 'update_post_metadata', $watch, 10 );
remove_filter( 'add_post_metadata', $watch, 10 );
ok( array() === $writes, 'no write to _regular_price, _sale_price or _price by any catalog action or sync', $writes );
ok( ca_prices_ok( $Z0, '4990', '4490' ) && ca_prices_ok( $Z3, '4990', '4490' ) && ca_prices_ok( $ZB, '4990', '4490' ) && ca_prices_ok( $ZC, '4990', '4490' ), '   regular/sale prices of all test products unchanged' );
$counts = array();
foreach ( array( 20, 50, 100 ) as $n ) {
	$get = array( 'per_page' => $n );
	ph_catalog( $get ); // Warm, like any page after the first.
	$q0            = $wpdb->num_queries;
	$h             = ph_catalog( $get );
	$counts[ $n ]  = array( $wpdb->num_queries - $q0, substr_count( $h, '<td class="num urme-stock-col">' ) );
}
ok( 20 === $counts[20][1] && 50 === $counts[50][1] && 100 === $counts[100][1] && $counts[20][0] === $counts[50][0] && $counts[50][0] === $counts[100][0], sprintf( 'catalog with 20 / 50 / 100 rows: %d / %d / %d queries (same)', $counts[20][0], $counts[50][0], $counts[100][0] ), $counts );
$ids  = array( $Z0, $Z3, $ZV, $ZB, $ZU, $ZC ); // Includes a variation (its parent is read too).
$more = array_merge( $ids, wc_get_products( array( 'limit' => 60, 'return' => 'ids', 'type' => 'simple' ) ) );
$cold = static function ( array $list ) use ( $wpdb ) {
	wp_cache_flush();
	get_option( 'siteurl' ); // Reload the options flushed above, so only stock_info is counted.
	$q0  = $wpdb->num_queries;
	$res = URME_SS_Store::stock_info( $list );
	return array( $wpdb->num_queries - $q0, $res );
};
list( $q_few, $info ) = $cold( $ids );
list( $q_many )       = $cold( $more );
ok( $q_few === $q_many && 12 === $info[ $Z0 ]['qty'] && $info[ $ZV ]['managed'] && ! $info[ $ZU ]['managed'] && 'yes' === $info[ $ZB ]['backorders'], sprintf( '   stock read in bulk, cold cache: %d products %d queries, %d products %d queries (same)', count( $ids ), $q_few, count( array_unique( $more ) ), $q_many ), $info );
remove_filter( 'pre_http_request', $count_http );
settings( array( 'rate_override' => '' ) );
