<?php
/**
 * AJAX row actions in the Supplier catalog, and "local URME stock always has priority over
 * Dropshipping". Included after product-admin.php; uses the helpers of the files before it.
 */

global $wpdb;

function ax( $action, array $extra = array(), $nonce = null ) {
	$GLOBALS['AX_IN'] = true; // HTTP requests are recorded only while an AJAX action runs.
	$post             = array_merge(
		array(
			'nonce'      => null === $nonce ? wp_create_nonce( 'urme_ss_row' ) : $nonce,
			'row_action' => $action,
		),
		$extra
	);
	$res              = admin( 'row_ajax', $post );
	$GLOBALS['AX_IN'] = false;
	return $res;
}
function ax_key( $op, $i, array $extra = array(), $nonce = null ) {
	return ax( $op . '|' . key_of( $i ), $extra, $nonce );
}
function ax_state( $pid, $i ) {
	$p = p( $pid );
	return array(
		'name'        => $p->get_name(),
		'sku'         => $p->get_sku(),
		'description' => $p->get_description(),
		'status'      => $p->get_status(),
		'regular'     => $p->get_regular_price(),
		'sale'        => $p->get_sale_price(),
		'stock'       => $p->get_stock_quantity(),
		'stock_st'    => $p->get_stock_status(),
		'manage'      => $p->get_manage_stock(),
		'backorders'  => $p->get_backorders(),
		'cogs'        => (float) $p->get_cogs_value(),
		'gtin'        => $p->get_global_unique_id(),
		'link'        => URME_SS_DB::get_link( key_of( $i ) ),
	);
}
function ax_badge( $html ) {
	return implode( ' | ', pa_badges( $html ) );
}

/* ------------------------------------------------------------------ setup */
section( 'AX0. AJAX row actions and local stock priority: setup' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
settings( array( 'cost_target' => 'wc_cogs', 'rate_override' => '11.321' ) );
URME_SS_Settings::set_brand( 'BOSS', true );
$A3 = bs_product( 'REF000246', key_of( 246 ), 3, 650 );  // URME stock 3.
$A1 = bs_product( 'REF000252', key_of( 252 ), 1, 610 );  // URME stock 1.
$A0 = bs_product( 'REF000258', key_of( 258 ), 0, 480 );  // URME stock 0.
$AB = bs_product( 'REF000264', key_of( 264 ), 0, 300 );  // Stock 0, for brand-off / double-click.
$AD = bs_product( 'REF000270', key_of( 270 ), 0, 300 );  // Linked, then deleted.
$AX_SET = array(
	'REF000246' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' ),
	'REF000252' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' ),
	'REF000258' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' ),
	'REF000264' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '4', 'PURCHASE_PRICE' => '90.00' ),
	'REF000270' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '4', 'PURCHASE_PRICE' => '90.00' ),
);
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, $PA_SET, $AX_SET ) );
run( array( 'force_feed' => true ) );
$sek  = round( 176 * 11.321, 2 );
$http       = 0;
$http_urls  = array();
$count_http = static function ( $pre, $args, $url ) use ( &$http, &$http_urls ) {
	if ( ! empty( $GLOBALS['AX_IN'] ) ) {
		++$http;
		$http_urls[] = $url;
	}
	return $pre;
};
add_filter( 'pre_http_request', $count_http, 10, 3 );
URME_SS_Admin::init();
ok( false !== has_action( 'wp_ajax_urme_ss_row', array( 'URME_SS_Admin', 'ajax_row' ) ), 'admin-ajax action "urme_ss_row" registered' );

section( 'AX11–12. URME stock > 0: Start supplier sync is rejected' );
$before = ax_state( $A3, 246 );
$r      = ax_key( 'start_supplier', 246 );
$after  = ax_state( $A3, 246 );
ok( false === $r['success'] && 'error' === $r['data']['type'] && 'Local URME stock exists (3 units). Dropshipping can only start when the URME Lager stock is 0.' === $r['data']['message'] && 'start_local' === $r['data']['suggest'], '11. URME stock 3: direct Dropshipping rejected with the local-stock message, suggests URME Lager', $r['data'] );
ok( $before === $after && null === $after['link'], '   stock 3, COGS, prices and every other field unchanged; not selected or linked', $after );
ok( false !== strpos( $r['data']['row_html'], 'value="start_local|' . key_of( 246 ) . '"' ) && false === strpos( $r['data']['row_html'], 'start_supplier|' ), '   the returned row offers "URME Lager" (no Dropshipping button)' );
$b1 = ax_state( $A1, 252 );
$r  = ax_key( 'start_supplier', 252 );
ok( false === $r['success'] && 'Local URME stock exists (1 unit). Dropshipping can only start when the URME Lager stock is 0.' === $r['data']['message'] && $b1 === ax_state( $A1, 252 ), '12. URME stock 1: same rejection, nothing changed', $r['data'] );

section( 'AX13. URME stock 0: Supplier now allowed (AJAX)' );
$r = ax_key( 'start_supplier', 258 );
$s = ax_state( $A0, 258 );
ok( true === $r['success'] && 'success' === $r['data']['type'] && 8 === $s['stock'] && 'instock' === $s['stock_st'] && abs( $s['cogs'] - $sek ) < 0.001, '13. stock 0 → 8, COGS 480 → ' . $sek . ': ' . $r['data']['message'], $r['data'] );
ok( '4990' === $s['regular'] && '4490' === $s['sale'] && 'supplier' === $s['link']['stock_mode'], '   prices unchanged; Supplier now' );
$row = $r['data']['row_html'];
ok( 0 === strpos( $row, '<tr' ) && false !== strpos( $row, 'data-urme-key="' . key_of( 258 ) . '"' ) && '8' === ca_stock_cell( $row ) && 'Dropshipping' === ax_badge( $row ) && false === strpos( ff_text( $row ), 'Supplier now' ) && false !== strpos( $row, 'value="sync|' ), '   returned row: URME stock 8, badge Dropshipping, Sync now button', ff_text( $row ) );

section( 'AX14. URME stock 3: Use Local first (AJAX)' );
$r = ax_key( 'start_local', 246 );
$s = ax_state( $A3, 246 );
ok( true === $r['success'] && 3 === $s['stock'] && abs( $s['cogs'] - 650 ) < 0.001 && 'local_first' === $s['link']['stock_mode'] && 3 === (int) $s['link']['local_qty'] && abs( (float) $s['link']['local_cost'] - 650 ) < 0.001, '14. Local first: local_qty 3, local COGS 650 kept, Woo stock stays 3', $r['data'] );
ok( 'URME Lager' === ax_badge( $r['data']['row_html'] ) && '4990' === $s['regular'] && '4490' === $s['sale'], '   row badge URME Lager; prices unchanged', ax_badge( $r['data']['row_html'] ) );

section( 'AX15–17. Local first → Supplier now needs 0 local units' );
lf_order( $A3, 1 ); // 3 → 2 local units.
$l = URME_SS_DB::get_link( key_of( 246 ) );
$r = admin( 'set_mode', $l, 'supplier' );
ok( 'error' === $r[1] && 'local_first' === URME_SS_DB::get_link( key_of( 246 ) )['stock_mode'] && 2 === (int) URME_SS_DB::get_link( key_of( 246 ) )['local_qty'], '15. 2 local units left: manual Supplier now rejected', $r );
$_POST['confirm_drop'] = '1';
$r = admin( 'set_mode', URME_SS_DB::get_link( key_of( 246 ) ), 'supplier' );
unset( $_POST['confirm_drop'] );
ok( 'error' === $r[1] && 2 === (int) URME_SS_DB::get_link( key_of( 246 ) )['local_qty'] && 2 === ax_state( $A3, 246 )['stock'], '17. no confirmation bypass: still 2 local units, stock 2', $r );
lf_order( $A3, 2 ); // Last local units sold.
run();
$s = ax_state( $A3, 246 );
ok( 'supplier' === $s['link']['stock_mode'] && 0 === (int) $s['link']['local_qty'] && 8 === $s['stock'] && abs( $s['cogs'] - $sek ) < 0.001, '16. 0 local units left: the automatic Local first → Supplier switch happens (stock 8, supplier COGS)', $s );

section( 'AX1–10. Sale price over AJAX' );
$feed_url = (string) URME_SS_Settings::get( 'feed_url' );
ok( ! array_filter( $http_urls, static function ( $u ) use ( $feed_url ) { return 0 === strpos( $u, $feed_url ); } ), '   no supplier feed request from any AJAX action so far (Start supplier sync, Use Local first)', $http_urls );
$http_urls = array();
$http   = 0;
$before = ax_state( $A0, 258 );
$writes = array();
// Real database changes only (WordPress skips an update whose value is unchanged).
$watch = static function ( $meta_id, $object_id, $meta_key ) use ( &$writes ) {
	if ( in_array( $meta_key, array( '_price', '_regular_price', '_sale_price', '_stock', '_stock_status', '_cogs_total_value' ), true ) ) {
		$writes[] = $object_id . ':' . $meta_key;
	}
};
add_action( 'updated_post_meta', $watch, 10, 3 );
add_action( 'added_post_meta', $watch, 10, 3 );
add_action( 'deleted_post_meta', $watch, 10, 3 );
$r     = ax_key( 'sale', 258, array( 'sale_price' => '4290' ) );
$after = ax_state( $A0, 258 );
ok( true === $r['success'] && 'success' === $r['data']['type'] && '4290' === $after['sale'], '1. sale price 4490 → 4290 over AJAX: ' . $r['data']['message'], $r['data'] );
ok( $before['regular'] === $after['regular'], '2. regular price unchanged (' . $after['regular'] . ')' );
ok( $before['stock'] === $after['stock'] && $before['stock_st'] === $after['stock_st'], '3. stock and stock status unchanged' );
ok( abs( $before['cogs'] - $after['cogs'] ) < 0.0001, '4. COGS unchanged' );
ok( $before['link'] === $after['link'], '5. supplier link and mode unchanged (whole row)' );
$diff = array_keys( array_diff_assoc( array_diff_key( $before, array( 'link' => 1 ) ), array_diff_key( $after, array( 'link' => 1 ) ) ) );
ok( array( 'sale' ) === $diff, '24. no other product field changed (only sale)', $diff );
ok( false !== strpos( $r['data']['row_html'], 'value="4290"' ) && '8' === ca_stock_cell( $r['data']['row_html'] ), '   returned row shows sale 4290, stock 8' );
ok( array( $A0 . ':_sale_price', $A0 . ':_price' ) === array_values( array_unique( $writes ) ), '   database changes: only _sale_price and _price (no stock, stock status or COGS write)', $writes );
$n_sale = count( array_keys( $writes, $A0 . ':_sale_price', true ) );
$r2     = ax_key( 'sale', 258, array( 'sale_price' => '4290' ) );
ok( 'info' === $r2['data']['type'] && count( array_keys( $writes, $A0 . ':_sale_price', true ) ) === $n_sale, '23. the same save again (double click): no second write', $r2['data'] );
remove_action( 'updated_post_meta', $watch, 10 );
remove_action( 'added_post_meta', $watch, 10 );
remove_action( 'deleted_post_meta', $watch, 10 );
$r = ax_key( 'sale', 258, array( 'sale_price' => '' ) );
ok( true === $r['success'] && '' === p( $A0 )->get_sale_price() && '4990' === p( $A0 )->get_price() && ! p( $A0 )->is_on_sale(), '6. cleared: sale removed, regular price 4,990 applies', $r['data'] );
foreach ( array( 'abc', '-1', '10.555', '1e3', '42 kr' ) as $bad ) {
	$r = ax_key( 'sale', 258, array( 'sale_price' => $bad ) );
	ok( false === $r['success'] && 'error' === $r['data']['type'] && '' === p( $A0 )->get_sale_price(), '7. invalid "' . $bad . '" rejected: ' . $r['data']['message'] );
}
$r = ax_key( 'sale', 258, array( 'sale_price' => '4991' ) );
ok( false === $r['success'] && '' === p( $A0 )->get_sale_price(), '8. 4,991 above regular 4,990 rejected', $r['data'] );
$r = ax_key( 'sale', 258, array( 'sale_price' => '4490' ) );
ok( true === $r['success'] && '4490' === p( $A0 )->get_sale_price(), '   back to 4490' );
$v2_before = p( $ZV2 )->get_sale_price();
$parent    = array( p( $VP )->get_regular_price(), p( $VP )->get_sale_price() );
$r         = ax_key( 'sale', 210, array( 'sale_price' => '3190' ) );
ok( true === $r['success'] && '3190' === p( $ZV )->get_sale_price() && $v2_before === p( $ZV2 )->get_sale_price() && $parent === array( p( $VP )->get_regular_price(), p( $VP )->get_sale_price() ), '9. variation: only the matched variation (3190); other variation and parent unchanged', $r['data'] );
ok( 0 === $http, '10. no supplier feed HTTP request for any sale price save', $http );

section( 'AX18–21. Paused, brand off, ambiguous, deleted' );
$la = URME_SS_DB::get_link( key_of( 258 ) );
$wpdb->update( URME_SS_DB::links_table(), array( 'sync_enabled' => 0 ), array( 'id' => (int) $la['id'] ) ); // Paused by an earlier version.
bs_set( $A0, 5, 700 );
$r = ax( 'sync|' . $la['id'] );
ok( false === $r['success'] && false !== strpos( $r['data']['message'], 'URME Lager' ) && 5 === p( $A0 )->get_stock_quantity() && abs( (float) p( $A0 )->get_cogs_value() - 700 ) < 0.001, '18. legacy paused link = URME Lager: Sync now refused, stock 5 / COGS 700 untouched', $r['data'] );
ok( 'URME Lager' === ax_badge( $r['data']['row_html'] ) && false !== strpos( $r['data']['row_html'], 'value="dropship|' . $la['id'] . '"' ) && false === strpos( $r['data']['row_html'], 'resume|' ), '   row: URME Lager badge + "Dropshipping" button (no Resume)' );
$r1 = ax( 'resume|' . $la['id'] );
$r2 = ax( 'dropship|' . $la['id'] );
ok( false === $r1['success'] && false === $r2['success'] && 5 === p( $A0 )->get_stock_quantity() && ! (int) URME_SS_DB::get_link( key_of( 258 ) )['sync_enabled'], '   Resume no longer exists; Dropshipping at stock 5 refused; nothing changed', array( $r1['data']['message'], $r2['data']['message'] ) );
bs_set( $A0, 0 );
$r = ax( 'dropship|' . $la['id'] );
ok( true === $r['success'] && 'Dropshipping' === ax_badge( $r['data']['row_html'] ), '   at stock 0: Dropshipping (AJAX)', $r['data'] );
URME_SS_Settings::set_brand( 'BOSS', false );
$r = ax_key( 'start_supplier', 264 );
ok( false === $r['success'] && null === URME_SS_DB::get_link( key_of( 264 ) ) && 0 === p( $AB )->get_stock_quantity(), '19. brand sync off: supplier sync refused, nothing linked or written', $r['data'] );
URME_SS_Settings::set_brand( 'BOSS', true );
$r = ax_key( 'start_supplier', 198 );
ok( false === $r['success'] && null === URME_SS_DB::get_link( key_of( 198 ) ), '20. Needs review / ambiguous: refused', $r['data'] );
$r = ax_key( 'start_supplier', 240 );
ok( false === $r['success'] && null === URME_SS_DB::get_link( key_of( 240 ) ), '21. trashed product: refused', $r['data'] );
ok( true === ax_key( 'start_supplier', 270 )['success'], '   (REF000270 linked, Supplier now)' );
$ld = URME_SS_DB::get_link( key_of( 270 ) );
wp_trash_post( $AD );
$r = ax( 'sync|' . $ld['id'] );
ok( false === $r['success'] && 'error' === URME_SS_DB::get_link( key_of( 270 ) )['last_status'], '   linked product deleted: Sync now reports an error, nothing written', $r['data'] );

section( 'AX22–23. Security and double clicks' );
$b = ax_state( $A0, 258 );
$r = ax_key( 'sale', 258, array( 'sale_price' => '100' ), 'bad-nonce' );
ok( false === $r['success'] && 403 === $r['status'] && $b === ax_state( $A0, 258 ), '22. wrong nonce: HTTP 403, nothing changed', $r );
$sub = username_exists( 'ax_subscriber' );
if ( ! $sub ) {
	$sub = wp_insert_user( array( 'user_login' => 'ax_subscriber', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
}
wp_set_current_user( (int) $sub );
ok( is_int( $sub ) || ctype_digit( (string) $sub ), '   (a real subscriber account is used)', $sub );
ok( ! current_user_can( 'manage_woocommerce' ), '   (it has no manage_woocommerce)' );
$r = ax_key( 'start_supplier', 264 );
ok( false === $r['success'] && 403 === $r['status'] && null === URME_SS_DB::get_link( key_of( 264 ) ), '   without manage_woocommerce: HTTP 403, nothing changed', $r );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
$links = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . URME_SS_DB::links_table() );
$r1    = ax_key( 'start_supplier', 264 );
$st1   = p( $AB )->get_stock_quantity();
$r2    = ax_key( 'start_supplier', 264 );
ok( true === $r1['success'] && false === $r2['success'] && 'warning' === $r2['data']['type'] && (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . URME_SS_DB::links_table() ) === $links + 1 && 4 === $st1 && 4 === p( $AB )->get_stock_quantity(), '23. double click on Start supplier sync: one link, one sync; the second call is refused', array( $r2['data']['message'] ) );
ok( 'Dropshipping' === ax_badge( $r2['data']['row_html'] ), '   the second response still returns the current row (Dropshipping)' );

ok( ! array_filter( $http_urls, static function ( $u ) use ( $feed_url ) { return 0 === strpos( $u, $feed_url ); } ), '   no supplier feed request from any AJAX action in this file (Sync now, Resume, Start supplier sync included)', $http_urls );

section( 'AX25. No per-row queries' );
remove_filter( 'pre_http_request', $count_http );
$counts = array();
foreach ( array( 20, 50, 100 ) as $n ) {
	ph_catalog( array( 'per_page' => $n ) );
	$q0           = $wpdb->num_queries;
	ph_catalog( array( 'per_page' => $n ) );
	$counts[ $n ] = $wpdb->num_queries - $q0;
}
ok( $counts[20] === $counts[50] && $counts[50] === $counts[100], sprintf( '25. catalog 20 / 50 / 100 rows: %d / %d / %d queries (same)', $counts[20], $counts[50], $counts[100] ), $counts );
$q0 = $wpdb->num_queries;
ax_key( 'sale', 258, array( 'sale_price' => '4490' ) );
$q_sale = $wpdb->num_queries - $q0;
ok( $q_sale < 40, sprintf( '   one AJAX sale save incl. the re-rendered row: %d queries (one row, independent of the catalog size)', $q_sale ), $q_sale );
settings( array( 'rate_override' => '' ) );
