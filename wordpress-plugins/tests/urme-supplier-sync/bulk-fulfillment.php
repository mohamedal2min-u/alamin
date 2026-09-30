<?php
/**
 * "Select checked for sync" follows local URME stock priority per watch (a watch with URME stock
 * is never selected); the Fulfillment filter and counts on WooCommerce > Products.
 * Included after ajax-local.php; uses the helpers of the files before it.
 */

global $wpdb;

function bf_variation( $parent, $sku, $gtin, $qty, $cogs ) {
	$v = new WC_Product_Variation();
	$v->set_parent_id( $parent );
	$v->set_sku( $sku );
	if ( $gtin ) {
		$v->set_global_unique_id( $gtin );
	}
	$v->set_regular_price( '3990' );
	$v->set_sale_price( '3490' );
	$v->set_manage_stock( true );
	$v->set_stock_quantity( $qty );
	$v->set_cogs_value( (float) $cogs );
	return $v->save();
}
function bf_state( $pid, $i ) {
	$s         = bs_state( $pid );
	$l         = URME_SS_DB::get_link( key_of( $i ) );
	$s['mode'] = $l ? $l['stock_mode'] : null;
	$s['qty']  = $l ? (int) $l['local_qty'] : null;
	$s['lc']   = ( $l && null !== $l['local_cost'] ) ? (float) $l['local_cost'] : null;
	return $s;
}
/**
 * Product IDs in one fulfillment state, through a normal WP_Query (as the products list runs it).
 */
function bf_ids( $state, array $args = array() ) {
	$q = new WP_Query(
		array_merge(
			array(
				'post_type'        => 'product',
				'post_status'      => get_post_stati( array( 'show_in_admin_all_list' => true ) ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'urme_fulfillment' => $state,
			),
			$args
		)
	);
	return array_map( 'intval', $q->posts );
}
/**
 * The expected buckets, computed in PHP from each link with URME_SS_Product_Source::state() (the
 * Fulfillment badge's own rule), independently of the filter's SQL. Two states: Dropshipping, and
 * URME Lager for everything else; a product with both kinds of variation is in both.
 */
function bf_expected() {
	global $wpdb;
	$all  = array_map( 'intval', get_posts( array( 'post_type' => 'product', 'post_status' => get_post_stati( array( 'show_in_admin_all_list' => true ) ), 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
	$rows = $wpdb->get_results( 'SELECT l.product_id, l.sync_enabled, l.stock_mode, l.local_qty, c.manufacturer FROM ' . URME_SS_DB::links_table() . ' l LEFT JOIN ' . URME_SS_DB::catalog_table() . ' c ON c.item_key = l.item_key WHERE l.product_id > 0', ARRAY_A );
	$drop = array();
	$non  = array();
	foreach ( $rows as $r ) {
		$post = get_post( (int) $r['product_id'] );
		if ( ! $post ) {
			continue;
		}
		$owner = 'product_variation' === $post->post_type ? (int) $post->post_parent : (int) $post->ID;
		if ( URME_SS_Product_Source::DROPSHIP === URME_SS_Product_Source::state( $r ) ) {
			$drop[ $owner ] = $owner;
		} else {
			$non[ $owner ] = $owner;
		}
	}
	sort( $all );
	$out = array(
		'dropship' => array_values( array_intersect( $all, $drop ) ),
		'lager'    => array_values( array_filter( $all, static function ( $id ) use ( $drop, $non ) { return ! isset( $drop[ $id ] ) || isset( $non[ $id ] ); } ) ),
		'all'      => $all,
	);
	return $out;
}

/* ------------------------------------------------------------------ setup */
section( 'BF0. Bulk selection and Fulfillment filter: setup' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
settings( array( 'cost_target' => 'wc_cogs', 'rate_override' => '11.321' ) );
URME_SS_Settings::set_brand( 'BOSS', true );
$sek = round( 176 * 11.321, 2 );
$K3  = bs_product( 'REF000282', key_of( 282 ), 3, 650 ); // URME stock 3.
$K1  = bs_product( 'REF000288', key_of( 288 ), 1, 610 ); // URME stock 1.
$K0  = bs_product( 'REF000294', key_of( 294 ), 0, 480 ); // URME stock 0.
$KRa = bs_product( 'REF000300', '', 0, 300 );            // Two URME products match REF000300 (Needs review).
$KRb = bs_product( 'OTHER300', key_of( 300 ), 0, 300 );
$KC  = bs_product( 'REF000306', key_of( 306 ), 0, 300 ); // Casio: brand sync off.
$KB  = bs_product( 'REF000318', key_of( 318 ), 2, 300 ); // Backorders allowed.
$kb  = wc_get_product( $KB );
$kb->set_backorders( 'notify' );
$kb->save();
$KP  = bs_product( 'REF000336', key_of( 336 ), 0, 300 ); // Paused later.
$KT  = bs_product( 'REF000343', key_of( 343 ), 0, 300 ); // Tissot: brand switched off later.
$vp  = new WC_Product_Variable();
$vp->set_name( 'BOSS variable mixed' );
$vp->set_manage_stock( false );
$KV  = $vp->save();
$KVL = bf_variation( $KV, 'REF000324', key_of( 324 ), 2, 520 ); // Variation with URME stock 2.
$KVS = bf_variation( $KV, 'REF000330', key_of( 330 ), 0, 520 ); // Variation with URME stock 0.
$KVU = bf_variation( $KV, 'VAR-UNLINKED', '', 5, 520 );          // Never linked.
$BF_SET = array();
foreach ( array( 282, 288, 294, 300, 312, 318, 324, 330, 336, 348, 354 ) as $i ) {
	$BF_SET[ sprintf( 'REF%06d', $i ) ] = array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' );
}
$BF_SET['REF000306'] = array( 'MANUFACTURER' => 'Casio', 'STOCK' => '6', 'PURCHASE_PRICE' => '176.00' );
$BF_SET['REF000343'] = array( 'MANUFACTURER' => 'Tissot', 'STOCK' => '6', 'PURCHASE_PRICE' => '176.00' );
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, $PA_SET, $AX_SET, $BF_SET ) );
run( array( 'force_feed' => true ) );
$before = array();
foreach ( array( 282 => $K3, 288 => $K1, 294 => $K0, 300 => $KRa, 306 => $KC, 318 => $KB, 324 => $KVL, 330 => $KVS ) as $i => $pid ) {
	$before[ $i ] = bf_state( $pid, $i );
}
$before_b = bs_state( $KRb );
ok( 3 === $before[282]['stock'] && 1 === $before[288]['stock'] && 0 === $before[294]['stock'] && null === $before[282]['mode'], 'BOSS REF000282 / 288 / 294 have URME stock 3 / 1 / 0, supplier stock 8, not selected' );

/* ------------------------------------------------------------------ bulk */
section( 'BF1–10. "Select checked for sync": each checked watch on its own' );
$writes    = array();
$watch     = static function ( $mid, $object_id, $key ) use ( &$writes ) {
	$writes[] = $object_id . ':' . $key;
};
add_action( 'updated_post_meta', $watch, 10, 3 );
add_action( 'added_post_meta', $watch, 10, 3 );
add_action( 'deleted_post_meta', $watch, 10, 3 );
$http = 0;
$hf   = static function ( $pre ) use ( &$http ) {
	++$http;
	return $pre;
};
add_filter( 'pre_http_request', $hf );
$res = admin( 'select_items', array( key_of( 282 ), key_of( 288 ), key_of( 294 ), key_of( 300 ), key_of( 306 ), key_of( 312 ), key_of( 318 ), key_of( 324 ), key_of( 330 ), key_of( 282 ), '' ) );
remove_filter( 'pre_http_request', $hf );
remove_action( 'updated_post_meta', $watch, 10 );
remove_action( 'added_post_meta', $watch, 10 );
remove_action( 'deleted_post_meta', $watch, 10 );
echo "  ({$res[1]}: {$res[0]})\n";
$a3 = bf_state( $K3, 282 );
$a1 = bf_state( $K1, 288 );
$a0 = bf_state( $K0, 294 );
ok( null === $a3['mode'] && 3 === $a3['stock'] && abs( $a3['cogs'] - 650 ) < 0.001, '1. bulk, URME stock 3 → not selected, stays URME Lager: stock 3 and COGS 650 kept', $a3 );
ok( null === $a1['mode'] && 1 === $a1['stock'] && abs( $a1['cogs'] - 610 ) < 0.001, '2. bulk, URME stock 1 → not selected, stock 1 and cost 610 kept', $a1 );
ok( 'supplier' === $a0['mode'] && 0 === $a0['stock'], '3. bulk, URME stock 0 → Dropshipping (updated on the next sync, as before)', $a0 );
ok( false !== strpos( $res[0], 'Not selected: URME stock exists, they stay URME Lager (4): REF000282 (3), REF000288 (1), REF000318 (2), REF000324 (2).' ) && false !== strpos( $res[0], 'Dropshipping – URME stock 0, updated on the next sync or with "Sync now" (2): REF000294, REF000330.' ), '4. mixed selection: each watch handled on its own and reported', $res[0] );
ok( null === URME_SS_DB::get_link( key_of( 300 ) ) && false !== strpos( $res[0], 'REF000300 – several URME products match (Needs review: IDs ' ) && bs_state( $KRa ) === array_intersect_key( $before[300], bs_state( $KRa ) ) && bs_state( $KRb ) === $before_b, '5. Needs review rejected (no link, both products untouched), the other watches still processed', $res[0] );
ok( null === URME_SS_DB::get_link( key_of( 306 ) ) && false !== strpos( $res[0], 'REF000306 – sync is disabled for Casio' ) && bs_state( $KC ) === array_intersect_key( $before[306], bs_state( $KC ) ), '6. brand sync off (Casio) rejected safely, nothing changed' );
ok( null === URME_SS_DB::get_link( key_of( 318 ) ) && 2 === p( $KB )->get_stock_quantity(), '   local stock 2 with backorders allowed: not selected (never Dropshipping), stock 2 kept' );
ok( null === URME_SS_DB::get_link( key_of( 312 ) ) && false !== strpos( $res[0], 'REF000312 – no URME product with this SKU or EAN (create it in WooCommerce first)' ), '   no URME product yet: not selected (no selection without a product)' );
ok( 'warning' === $res[1] && false !== strpos( $res[0], 'Not selected, nothing changed (3):' ), '   one notice: started, kept and rejected watches listed; type warning' );
$vl = bf_state( $KVL, 324 );
$vs = bf_state( $KVS, 330 );
ok( null === $vl['mode'] && 2 === $vl['stock'] && 'supplier' === $vs['mode'], '   variable product: its variation with stock 2 stays URME Lager, its variation with stock 0 → Dropshipping', array( $vl, $vs ) );
ok( 1 === substr_count( $res[0], 'REF000282' ) && 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . URME_SS_DB::links_table() . ' WHERE item_key = %s', key_of( 282 ) ) ), '   a key checked twice is handled once' );
$price_writes = array_filter( $writes, static function ( $w ) { return preg_match( '/:(_regular_price|_sale_price|_price|_stock|_cogs_total_value|_cogs_value)$/', $w ); } );
ok( ! $price_writes, '9. bulk selection wrote no price, stock or COGS meta at all', array_values( $writes ) );
ok( 0 === $http, '   no HTTP request (no feed download) during bulk selection', $http );

run( array( 'refresh_feed' => false ) );
$s3 = bf_state( $K3, 282 );
$s1 = bf_state( $K1, 288 );
$s0 = bf_state( $K0, 294 );
ok( 3 === $s3['stock'] && abs( $s3['cogs'] - 650 ) < 0.001 && 1 === $s1['stock'] && abs( $s1['cogs'] - 610 ) < 0.001 && 2 === p( $KVL )->get_stock_quantity(), '7–8. after a full sync: URME stock (3, 1, 2) and COGS (650, 610) not overwritten by supplier stock 8 / cost', array( $s3, $s1 ) );
ok( 8 === $s0['stock'] && abs( $s0['cogs'] - $sek ) < 0.001 && 8 === p( $KVS )->get_stock_quantity(), '   Dropshipping (URME stock 0): supplier stock 8 and cost 176 EUR × rate synced', $s0 );
$prices_ok = true;
foreach ( array( 282 => $K3, 288 => $K1, 294 => $K0, 306 => $KC, 318 => $KB, 324 => $KVL, 330 => $KVS ) as $i => $pid ) {
	$now       = bs_state( $pid );
	$prices_ok = $prices_ok && $now['regular'] === $before[ $i ]['regular'] && $now['sale'] === $before[ $i ]['sale'];
}
ok( $prices_ok, '9. Regular and Sale prices unchanged on every checked product (started, rejected and synced)' );

$again = admin( 'select_items', array( key_of( 282 ), key_of( 288 ) ) );
$row   = admin( 'run_row_action', 'start_supplier|' . key_of( 282 ) );
ok( 'error' === $again[1] && 'error' === $row[1] && null === URME_SS_DB::get_link( key_of( 282 ) ) && 3 === p( $K3 )->get_stock_quantity(), '10. no bypass to Dropshipping with URME stock: re-select and row start refused, stock 3 kept', array( $again, $row ) );

section( 'BF10b. Not in URME yet; Dropshipping → URME Lager; no automatic switch back' );
$sel = admin( 'select_items', array( key_of( 348 ), key_of( 354 ) ) ); // Not in URME yet.
ok( 'error' === $sel[1] && null === URME_SS_DB::get_link( key_of( 348 ) ) && null === URME_SS_DB::get_link( key_of( 354 ) ), 'no URME product yet: nothing selected (no selection without a product)', $sel );
$KA  = bs_product( 'REF000348', '', 2, 710 );
$KZ  = bs_product( 'REF000354', '', 0, 710 );
$sel = admin( 'select_items', array( key_of( 348 ), key_of( 354 ) ) );
ok( null === URME_SS_DB::get_link( key_of( 348 ) ) && 2 === p( $KA )->get_stock_quantity() && (int) URME_SS_DB::get_link( key_of( 354 ) )['product_id'] === $KZ && 'supplier' === URME_SS_DB::get_link( key_of( 354 ) )['stock_mode'], 'after creating them in WooCommerce: stock 2 stays URME Lager, stock 0 → Dropshipping', $sel );

$l0 = URME_SS_DB::get_link( key_of( 294 ) ); // Dropshipping, synced to 8.
$c0 = (float) p( $K0 )->get_cogs_value();
$r  = admin( 'run_row_action', 'lager|' . $l0['id'] );
ok( 'success' === $r[1] && null === URME_SS_DB::get_link( key_of( 294 ) ) && 0 === p( $K0 )->get_stock_quantity() && 'outofstock' === p( $K0 )->get_stock_status() && abs( (float) p( $K0 )->get_cogs_value() - $c0 ) < 0.001, 'Dropshipping → URME Lager: removed from supplier sync, stock 0 / out of stock (never the supplier 8), cost unchanged', $r );
bs_set( $K0, 3 ); // The admin enters 3 in WooCommerce.
$r1 = admin( 'run_row_action', 'resume|' . $l0['id'] );
$r2 = admin( 'run_row_action', 'start_supplier|' . key_of( 294 ) );
$r3 = ax_key( 'start_supplier', 294 );
$r4 = admin( 'run_row_action', 'dropship|' . $l0['id'] );
ok( 'error' === $r1[1] && 0 === strpos( $r2[0], 'Local URME stock exists (3 units).' ) && false === $r3['success'] && 'error' === $r4[1] && null === URME_SS_DB::get_link( key_of( 294 ) ) && 3 === p( $K0 )->get_stock_quantity(), 'URME Lager stock 3: Dropshipping refused (row action, AJAX, old link id); Resume gone; nothing changed', array( $r1[0], $r2[0], $r3['data']['message'], $r4[0] ) );
run( array( 'refresh_feed' => false ) );
ok( 3 === p( $K0 )->get_stock_quantity(), '   and the sync leaves URME Lager stock alone' );
bs_set( $K0, 0 ); // The last URME unit is gone.
run( array( 'refresh_feed' => false ) );
ok( null === URME_SS_DB::get_link( key_of( 294 ) ) && 0 === p( $K0 )->get_stock_quantity(), '   URME Lager stock 0: stays URME Lager (no automatic Dropshipping)', p( $K0 )->get_stock_quantity() );
$r = admin( 'run_row_action', 'start_supplier|' . key_of( 294 ) );
ok( 'success' === $r[1] && 'supplier' === URME_SS_DB::get_link( key_of( 294 ) )['stock_mode'] && 8 === p( $K0 )->get_stock_quantity(), '   "Dropshipping" in the catalog: supplier stock 8', $r );

/* ------------------------------------------------------------------ brand on */
section( 'BR. Turning a brand on again respects local URME stock' );
// Rado and Oris: brands used only here. Five Supplier-now watches, synced to supplier stock 6.
$BR = array();
foreach ( array( 362, 368, 374, 380, 386 ) as $i ) {
	$BF_SET[ sprintf( 'REF%06d', $i ) ] = array( 'MANUFACTURER' => 'Rado', 'STOCK' => '6', 'PURCHASE_PRICE' => '176.00' );
	$BR[ $i ]                           = bs_product( sprintf( 'REF%06d', $i ), key_of( $i ), 0, 400 );
}
$BF_SET['REF000392'] = array( 'MANUFACTURER' => 'Oris', 'STOCK' => '6', 'PURCHASE_PRICE' => '176.00' );
$BRO                 = bs_product( 'REF000392', key_of( 392 ), 0, 400 );
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, $PA_SET, $AX_SET, $BF_SET ) );
run( array( 'force_feed' => true ) );
URME_SS_Settings::set_brand( 'Rado', true );
URME_SS_Settings::set_brand( 'Oris', true );
admin( 'select_items', array_merge( array_map( 'key_of', array_keys( $BR ) ), array( key_of( 392 ) ) ) );
run( array( 'refresh_feed' => false ) );
$synced = true;
foreach ( $BR as $pid ) {
	$synced = $synced && 6 === p( $pid )->get_stock_quantity() && abs( (float) p( $pid )->get_cogs_value() - $sek ) < 0.001;
}
ok( $synced && 6 === p( $BRO )->get_stock_quantity(), 'setup: 5 Rado watches and 1 Oris watch in Supplier now, synced to stock 6 / 176 EUR × rate' );
URME_SS_Settings::set_brand( 'Rado', false );
URME_SS_Settings::set_brand( 'Oris', false );
bs_set( $BR[362], 9, 900 ); // 3 local units put in by hand while Rado was off, with their own cost.
bs_set( $BR[368], 2, 700 ); // A local watch counted as 2 (below the synced 6): sold-down supplier stock, not local.
bs_set( $BR[374], 4 );      // 2 dropship units sold while off.
bs_set( $BR[380], 11 );     // 5 local units added.
$before_br = array();
foreach ( $BR as $i => $pid ) {
	$before_br[ $i ] = bs_state( $pid );
}
$res = admin( 'toggle_brand', 'Rado', true );
echo "  ({$res[1]}: {$res[0]})\n";
$l = static function ( $i ) {
	return URME_SS_DB::get_link( key_of( $i ) );
};
ok( null === $l( 362 ) && null === $l( 380 ), 'brand ON: the watches with local units added while off (stock 9 > 6, 11 > 6) leave supplier sync (URME Lager)' );
ok( (int) $l( 368 )['sync_enabled'] && (int) $l( 374 )['sync_enabled'] && (int) $l( 386 )['sync_enabled'], 'the others of the same brand (stock 2, 4, unchanged 6) resume normally, each decided on its own' );
ok( 'warning' === $res[1] && false !== strpos( $res[0], 'Sync enabled for Rado.' ) && false !== strpos( $res[0], 'Moved to URME Lager (removed from supplier sync) because URME stock exists (2): REF000362 (9 units), REF000380 (11 units). Their stock and cost were not changed.' ), 'clear admin notice listing the watches moved to URME Lager', $res[0] );
run( array( 'refresh_feed' => false ) );
ok( bs_state( $BR[362] ) === $before_br[362] && bs_state( $BR[380] ) === $before_br[380], 'after the next sync: stock 9 / COGS 900 and stock 11 unchanged (no supplier stock or cost written)', array( bs_state( $BR[362] ), bs_state( $BR[380] ) ) );
ok( 6 === p( $BR[368] )->get_stock_quantity() && 6 === p( $BR[374] )->get_stock_quantity() && 6 === p( $BR[386] )->get_stock_quantity() && abs( (float) p( $BR[368] )->get_cogs_value() - $sek ) < 0.001, 'no local stock: supplier sync resumed normally (stock 6, cost 176 EUR × rate)' );
ok( 'URME Lager' === pa_badge( $BR[362] ), 'badge: URME Lager' );
$r = admin( 'run_row_action', 'start_supplier|' . key_of( 362 ) );
ok( 'error' === $r[1] && null === $l( 362 ), 'Dropshipping later is refused while stock is above 0', $r );

// Settings page save path, and no N+1: the check reads all of a brand's watches in bulk.
URME_SS_Settings::set_brand( 'Oris', false );
bs_set( $BRO, 8 );
$saved = URME_SS_Settings::get( 'enabled_brands' );
URME_SS_Settings::save( array_merge( URME_SS_Settings::all(), array( 'brands_present' => 1, 'enabled_brands' => array_merge( $saved, array( 'Oris' ) ), 'categories' => 'WATCH' ) ) );
ok( array( 'REF000392 (8 units)' ) === URME_SS_Settings::held_on_enable() && null === $l( 392 ) && 8 === p( $BRO )->get_stock_quantity(), 'Settings page: enabling Oris with 8 local units moves its watch to URME Lager and reports it', URME_SS_Settings::held_on_enable() );
bs_set( $BRO, 0 ); // No URME units left…
$r = admin( 'run_row_action', 'start_supplier|' . key_of( 392 ) ); // …so it may become Dropshipping.
URME_SS_Settings::set_brand( 'Oris', false );
// Warm-up cycle each, so WooCommerce's own one-off term-count transient refresh is not measured.
foreach ( array( 'Rado', 'Oris' ) as $brand ) {
	URME_SS_Settings::set_brand( $brand, false );
	URME_SS_Settings::set_brand( $brand, true );
}
URME_SS_Settings::set_brand( 'Oris', false );
URME_SS_Settings::set_brand( 'Rado', false );
wp_cache_flush();
$q0 = $wpdb->num_queries;
URME_SS_Settings::set_brand( 'Rado', true ); // 3 active Rado watches, nothing to hold.
$q_rado3 = $wpdb->num_queries - $q0;
wp_cache_flush();
$q0 = $wpdb->num_queries;
URME_SS_Settings::set_brand( 'Oris', true ); // 1 active Oris watch, nothing to hold.
$q_oris1 = $wpdb->num_queries - $q0;
ok( $q_rado3 === $q_oris1 && array() === URME_SS_Settings::held_on_enable(), sprintf( 'no N+1: turning on a brand with 3 or with 1 active watch costs the same %d / %d queries', $q_rado3, $q_oris1 ), array( $q_rado3, $q_oris1 ) );
ok( 'success' === $r[1] && (int) $l( 392 )['sync_enabled'] && array() === URME_SS_Settings::held_on_enable(), 'brand OFF, no local stock added, brand ON: normal resume' );
run( array( 'refresh_feed' => false ) );
ok( 6 === p( $BRO )->get_stock_quantity() && abs( (float) p( $BRO )->get_cogs_value() - $sek ) < 0.001, '   and supplier stock / cost are synced as before' );
// Rado and Oris stay off: all 6 watches are URME Lager now (used by the filter below).
URME_SS_Settings::set_brand( 'Rado', false );
URME_SS_Settings::set_brand( 'Oris', false );

/* ------------------------------------------------------------------ filter */
section( 'BF24–40. Fulfillment filter on WooCommerce > Products: URME Lager / Dropshipping only' );
admin( 'select_items', array( key_of( 336 ), key_of( 343 ) ) );
$wpdb->update( URME_SS_DB::links_table(), array( 'sync_enabled' => 0 ), array( 'item_key' => key_of( 336 ) ) ); // Paused by an earlier version.
run( array( 'refresh_feed' => false ) );
URME_SS_Product_Source::flush();
$exp = bf_expected();
$got = array(
	'dropship' => bf_ids( 'dropship' ),
	'lager'    => bf_ids( 'lager' ),
	'all'      => bf_ids( '' ),
);
ok( $got['dropship'] === $exp['dropship'] && in_array( $K0, $got['dropship'], true ) && in_array( $KT, $got['dropship'], true ) && ! in_array( $K3, $got['dropship'], true ), '24. Dropshipping filter = the products whose badge says Dropshipping', array( count( $got['dropship'] ), count( $exp['dropship'] ) ) );
ok( $got['lager'] === $exp['lager'] && in_array( $KRa, $got['lager'], true ) && in_array( $KC, $got['lager'], true ) && in_array( $K3, $got['lager'], true ) && ! in_array( $K0, $got['lager'], true ), '25. URME Lager filter = every product that is not Dropshipping (not linked, or linked as URME Lager)', array( count( $got['lager'] ), count( $exp['lager'] ) ) );
$legacy = array( $K3, $K1, $KP, $BR[368], $BR[362] ); // Not selected (URME stock), legacy paused, brand off, removed at brand enable.
$ok26   = true;
foreach ( $legacy as $id ) {
	$ok26 = $ok26 && in_array( $id, $got['lager'], true ) && ! in_array( $id, $got['dropship'], true ) && 'URME Lager' === pa_badge( $id );
}
ok( $ok26, '26. not selected, legacy Paused and brand-sync-off links: URME Lager (filter and badge), never Dropshipping' );
ok( $got['all'] === $exp['all'] && ! array_diff( $got['all'], array_merge( $got['dropship'], $got['lager'] ) ), '28. All (no filter): every product, each in URME Lager or Dropshipping', count( $got['all'] ) );
$in     = bf_ids( 'lager', array( 'meta_query' => array( array( 'key' => '_stock_status', 'value' => 'instock' ) ) ) );
$exp_in = array_values( array_filter( $exp['lager'], static function ( $id ) { return 'instock' === get_post_meta( $id, '_stock_status', true ); } ) );
ok( $in === $exp_in && in_array( $K3, $in, true ) && ! in_array( $K0, $in, true ), '29. URME Lager + In stock', count( $in ) );
$out   = bf_ids( 'dropship', array( 'meta_query' => array( array( 'key' => '_stock_status', 'value' => 'outofstock' ) ) ) );
$exp_o = array_values( array_filter( $exp['dropship'], static function ( $id ) { return 'outofstock' === get_post_meta( $id, '_stock_status', true ); } ) );
ok( $out === $exp_o, '30. Dropshipping + Out of stock', count( $out ) );
if ( taxonomy_exists( 'product_brand' ) ) {
	wp_set_object_terms( $K3, 'BOSS', 'product_brand' );
	wp_set_object_terms( $K0, 'BOSS', 'product_brand' );
	wp_set_object_terms( $KC, 'Casio', 'product_brand' );
	$boss_lager = bf_ids( 'lager', array( 'tax_query' => array( array( 'taxonomy' => 'product_brand', 'field' => 'name', 'terms' => 'BOSS' ) ) ) );
	$boss_drop  = bf_ids( 'dropship', array( 'tax_query' => array( array( 'taxonomy' => 'product_brand', 'field' => 'name', 'terms' => 'BOSS' ) ) ) );
	ok( array( $K3 ) === $boss_lager && array( $K0 ) === $boss_drop, '31. Fulfillment + Brand (product_brand BOSS): URME Lager → REF000282, Dropshipping → REF000294', array( $boss_lager, $boss_drop ) );
} else {
	ok( false, '31. product_brand taxonomy missing' );
}
$cat = term_exists( 'BF watches', 'product_cat' ) ? term_exists( 'BF watches', 'product_cat' ) : wp_insert_term( 'BF watches', 'product_cat' );
wp_set_object_terms( $K1, (int) $cat['term_id'], 'product_cat' );
wp_set_object_terms( $KP, (int) $cat['term_id'], 'product_cat' );
wp_set_object_terms( $KC, (int) $cat['term_id'], 'product_cat' );
$tq       = array( 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => (int) $cat['term_id'] ) ) );
$cat_want = array( $K1, $KC, $KP );
sort( $cat_want );
ok( $cat_want === bf_ids( 'lager', $tq ) && array() === bf_ids( 'dropship', $tq ), '32. Fulfillment + Category', bf_ids( 'lager', $tq ) );
ok( array( $K3 ) === bf_ids( 'lager', array( 's' => 'REF000282' ) ) && array() === bf_ids( 'dropship', array( 's' => 'REF000282' ) ), '33. Fulfillment + product search' );
$tv = array( 'tax_query' => array( array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'variable' ) ) );
ok( in_array( $KV, bf_ids( 'dropship', $tv ), true ) && bf_ids( 'lager', $tv ) === array_values( array_intersect( $exp['lager'], bf_ids( '', $tv ) ) ), '   Fulfillment + Product type: the variable product with a Dropshipping variation is listed under Dropshipping (its other variations are not selected)' );
$asc  = bf_ids( 'lager', array( 'orderby' => 'title', 'order' => 'ASC' ) );
$desc = bf_ids( 'lager', array( 'orderby' => 'title', 'order' => 'DESC' ) );
ok( count( $asc ) === count( $desc ) && ! array_diff( $asc, $desc ), '   Fulfillment + sorting: same products in either order', array( count( $asc ), count( $desc ) ) );

$c = URME_SS_Product_Source::counts();
ok( array( 'all', 'dropship', 'lager' ) === array_keys( $c ) && $c['dropship'] === count( $exp['dropship'] ) && $c['lager'] === count( $exp['lager'] ) && $c['all'] === count( $exp['all'] ), sprintf( '34. counts = the badge buckets: Dropshipping %d, URME Lager %d (All %d)', $c['dropship'], $c['lager'], $c['all'] ), $c );
URME_SS_Settings::set_brand( 'Tissot', false );
URME_SS_Product_Source::flush();
$c2 = URME_SS_Product_Source::counts();
ok( ! in_array( $KT, bf_ids( 'dropship' ), true ) && in_array( $KT, bf_ids( 'lager' ), true ) && $c2['dropship'] < $c['dropship'] && $c2['lager'] > $c['lager'] && 'URME Lager' === pa_badge( $KT ), '35. Dropshipping needs brand sync: Tissot off → its watch is URME Lager, counts updated at once', array( $c, $c2 ) );
URME_SS_Settings::set_brand( 'Tissot', true );
URME_SS_Product_Source::flush();
ok( in_array( $KT, bf_ids( 'dropship' ), true ) && 'Dropshipping' === pa_badge( $KT ), '   Tissot on again → Dropshipping' );
ok( in_array( $KV, $got['dropship'], true ) && ! in_array( $KVL, $got['all'], true ) && ! in_array( $KVS, $got['all'], true ), '38. variable product: listed as the parent; variations never listed as rows' );
$dup = true;
foreach ( $got as $ids ) {
	$dup = $dup && count( $ids ) === count( array_unique( $ids ) );
}
ok( $dup, '39. no duplicate product rows in any filter' );
$q = new WP_Query( array( 'post_type' => 'product', 'post_status' => get_post_stati( array( 'show_in_admin_all_list' => true ) ), 'posts_per_page' => 2, 'urme_fulfillment' => 'dropship' ) );
ok( count( $exp['dropship'] ) === (int) $q->found_posts && 2 === count( $q->posts ) && (int) ceil( count( $exp['dropship'] ) / 2 ) === (int) $q->max_num_pages, '40. paged query: found_posts (the "N items" count) and pages = the filtered total', array( $q->found_posts, count( $exp['dropship'] ) ) );

section( 'BF-UI. Dropdown in the WooCommerce filter row' );
$filters = apply_filters( 'woocommerce_products_admin_list_table_filters', array( 'stock_status' => '__return_null' ) );
ok( array( 'stock_status', 'urme_fulfillment' ) === array_keys( $filters ), 'added to WooCommerce\'s product filters, after the stock status filter' );
$_GET['urme_fulfillment'] = 'lager';
ob_start();
URME_SS_Product_Source::render_filter_dropdown();
$html = ob_get_clean();
unset( $_GET['urme_fulfillment'] );
ok( 3 === substr_count( $html, '<option' ) && false !== strpos( $html, sprintf( '>Fulfillment: All (%d)<', $c['all'] ) ) && false !== strpos( $html, sprintf( '>Dropshipping (%d)<', $c['dropship'] ) ) && false !== strpos( $html, sprintf( 'value="lager" selected=\'selected\'>URME Lager (%d)<', $c['lager'] ) ) && false === strpos( $html, 'Local first' ) && false === strpos( $html, 'Paused' ) && false === strpos( $html, 'brand sync off' ), 'options: All / Dropshipping / URME Lager with counts, nothing else; the current one selected', $html );
ok( bf_ids( 'local' ) === $got['all'] && bf_ids( 'paused' ) === $got['all'] && bf_ids( 'brand_off' ) === $got['all'], 'old filter values (local, paused, brand_off) filter nothing' );
wp_set_current_user( (int) username_exists( 'ax_subscriber' ) );
ok( array( 'stock_status' ) === array_keys( apply_filters( 'woocommerce_products_admin_list_table_filters', array( 'stock_status' => '__return_null' ) ) ), 'no dropdown without manage_woocommerce' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );

section( 'BF-PERF. Constant queries, no HTTP' );
$http = 0;
add_filter( 'pre_http_request', $hf );
URME_SS_Product_Source::flush();
wp_cache_delete( _count_posts_cache_key( 'product', '' ), 'counts' );
$q0 = $wpdb->num_queries;
URME_SS_Product_Source::counts();
$qc = $wpdb->num_queries - $q0;
ok( $qc <= 3, sprintf( 'dropdown counts (5 states): %d queries (linked brands, one aggregate, WordPress post counts), independent of the number of products', $qc ), $qc );
$per = array();
foreach ( array( 20, 50, 100 ) as $n ) {
	$args      = array( 'post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => $n, 'urme_fulfillment' => 'lager' );
	new WP_Query( $args ); // Warm WordPress's post caches for these rows.
	URME_SS_Product_Source::flush();
	$q0        = $wpdb->num_queries;
	new WP_Query( $args );
	$per[ $n ] = $wpdb->num_queries - $q0;
}
ok( $per[20] === $per[50] && $per[50] === $per[100], sprintf( 'filtered list query 20 / 50 / 100 rows: %d / %d / %d queries (same)', $per[20], $per[50], $per[100] ), $per );
remove_filter( 'pre_http_request', $hf );
ok( 0 === $http, 'no HTTP request while counting or filtering', $http );
settings( array( 'rate_override' => '' ) );
