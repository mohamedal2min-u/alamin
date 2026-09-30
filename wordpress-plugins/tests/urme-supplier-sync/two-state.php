<?php
/**
 * Two Fulfillment states only: URME Lager and Dropshipping (no order history decides anything).
 * URME Lager → Dropshipping only at stock 0, automatically in the same request when the stock
 * reaches 0 (with the price-review notice); Dropshipping → URME Lager with a typed quantity.
 * Included after gift-wrap.php; uses the helpers of the files before it.
 */

global $wpdb;

function ts_link( $i ) {
	return URME_SS_DB::get_link( key_of( $i ) );
}

section( 'TS0. Two states: setup' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
settings( array( 'cost_target' => 'wc_cogs', 'rate_override' => '11.321' ) );
URME_SS_Settings::set_brand( 'BOSS', true );
$sek = round( 176 * 11.321, 2 );
$T1  = bs_product( 'REF000396', key_of( 396 ), 1, 900 ); // URME Lager, 1 unit.
$T3  = bs_product( 'REF000402', key_of( 402 ), 3, 800 ); // URME Lager, 3 units.
$T0  = bs_product( 'REF000408', key_of( 408 ), 0, 300 ); // Dropshipping.
$TB  = bs_product( 'REF000414', key_of( 414 ), 1, 500 ); // URME Lager, brand switched off below.
$TS_SET = array();
foreach ( array( 396, 402, 408, 414 ) as $i ) {
	$TS_SET[ sprintf( 'REF%06d', $i ) ] = array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' );
}
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, $PA_SET, $AX_SET, $BF_SET, $TS_SET ) );
run( array( 'force_feed' => true ) );
admin( 'select_items', array( key_of( 396 ), key_of( 402 ), key_of( 408 ), key_of( 414 ) ) );
run( array( 'refresh_feed' => false ) );
ok( 'local_first' === ts_link( 396 )['stock_mode'] && 'local_first' === ts_link( 402 )['stock_mode'] && 'supplier' === ts_link( 408 )['stock_mode'] && 8 === p( $T0 )->get_stock_quantity(), 'REF000396 / 402 URME Lager (1 / 3 units), REF000408 Dropshipping (supplier 8)' );
ok( 'URME Lager' === pa_badge( $T1 ) && 'URME Lager' === pa_badge( $T3 ) && 'Dropshipping' === pa_badge( $T0 ), 'badges: only "URME Lager" / "Dropshipping"' );
$http = 0;
$hf   = static function ( $pre ) use ( &$http ) {
	++$http;
	return $pre;
};
add_filter( 'pre_http_request', $hf );

section( 'TS1. URME Lager → Dropshipping only at stock 0' );
$b3 = array( bs_state( $T3 ), ts_link( 402 ) );
$r1 = admin( 'set_mode', ts_link( 402 ), 'supplier' );
$r2 = admin( 'run_row_action', 'dropship|' . ts_link( 402 )['id'] );
$b1 = array( bs_state( $T1 ), ts_link( 396 ) );
$r3 = admin( 'set_mode', ts_link( 396 ), 'supplier' );
ok( 'error' === $r1[1] && 'Not changed: Local URME stock exists (3 units). Dropshipping can only start when the URME Lager stock is 0.' === $r1[0] && 'error' === $r2[1] && $b3 === array( bs_state( $T3 ), ts_link( 402 ) ), 'stock 3: Dropshipping refused with a clear error; link, stock, COGS and prices unchanged', $r1 );
ok( 'error' === $r3[1] && false !== strpos( $r3[0], '(1 unit)' ) && $b1 === array( bs_state( $T1 ), ts_link( 396 ) ), 'stock 1: refused too, nothing changed', $r3 );

section( 'TS2. The last unit is sold: Dropshipping in the same request' );
$rev0 = count( pr_rows( (int) ts_link( 396 )['id'] ) );
$o    = lf_order( $T1, 1 );             // WooCommerce: stock 1 → 0.
ok( 'local_first' === ts_link( 396 )['stock_mode'], '   (the switch waits until the order line is booked)' );
URME_SS_Inventory::switch_sold_out();   // What runs at the end of that request (shutdown).
$l1 = ts_link( 396 );
$s1 = bs_state( $T1 );
ok( 'supplier' === $l1['stock_mode'] && 1 === (int) $l1['sync_enabled'] && 8 === $s1['stock'] && abs( $s1['cogs'] - $sek ) < 0.001 && 'Dropshipping' === pa_badge( $T1 ), 'stock 0 → Dropshipping at once: supplier stock 8 and cost 176 EUR × rate written, badge Dropshipping', $s1 );
ok( '4990' === $s1['regular'] && '4490' === $s1['sale'], '   selling prices unchanged' );
$revs = pr_rows( (int) $l1['id'] );
ok( count( $revs ) === $rev0 + 1 && 'pending' === end( $revs )['status'], '   one "Price review required" notice (dashboard) to adjust the selling price', $revs );
ok( false !== strpos( ff_text( ff_line_html( $o ) ), 'URME Lager: 1' ), '   the order line that sold the last unit is still labeled URME Lager', ff_text( ff_line_html( $o ) ) );
$o3 = lf_order( $T3, 1 );                // 3 → 2.
URME_SS_Inventory::switch_sold_out();
ok( 'local_first' === ts_link( 402 )['stock_mode'] && 2 === p( $T3 )->get_stock_quantity(), 'a sale 3 → 2: no switch, stock 2' );
bs_set( $T3, 0 );                        // Stock set to 0 by hand in WooCommerce.
URME_SS_Inventory::switch_sold_out();
ok( 'supplier' === ts_link( 402 )['stock_mode'] && 8 === p( $T3 )->get_stock_quantity(), 'stock set to 0 by hand: Dropshipping at once as well' );
URME_SS_Settings::set_brand( 'BOSS', false );
bs_set( $TB, 0 );
URME_SS_Inventory::switch_sold_out();
ok( 'local_first' === ts_link( 414 )['stock_mode'] && 0 === p( $TB )->get_stock_quantity(), 'brand sync off: stays URME Lager at 0 (Dropshipping needs the brand)' );
URME_SS_Settings::set_brand( 'BOSS', true );

remove_filter( 'pre_http_request', $hf );
ok( 0 === $http, 'no HTTP request (no feed download) for the refusals and the automatic switches', $http );

section( 'TS3. Dropshipping → URME Lager: supplier sync stops, the typed quantity is kept' );
$_POST = array( 'local_qty' => '0' );
$r0    = admin( 'set_mode', ts_link( 408 ), 'local' );
$_POST = array( 'local_qty' => '2', 'local_cost' => '650' );
$r     = admin( 'set_mode', ts_link( 408 ), 'local' );
$_POST = array();
ok( 'error' === $r0[1] && 'success' === $r[1] && 'local_first' === ts_link( 408 )['stock_mode'] && 2 === p( $T0 )->get_stock_quantity() && abs( (float) p( $T0 )->get_cogs_value() - 650 ) < 0.001, 'qty 0 refused; qty 2: URME Lager with stock 2 (not the supplier 8) and cost 650', array( $r0, $r ) );
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, $PA_SET, $AX_SET, $BF_SET, $TS_SET, array( 'REF000408' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '9', 'PURCHASE_PRICE' => '190.00' ) ) ) );
run();
ok( 2 === p( $T0 )->get_stock_quantity() && abs( (float) p( $T0 )->get_cogs_value() - 650 ) < 0.001 && 'URME Lager' === pa_badge( $T0 ), 'next sync (supplier now 9 / 190 EUR): nothing written, stock 2 / cost 650' );

section( 'TS4. Controls show only URME Lager / Dropshipping' );
$counts = URME_SS_DB::link_counts();
$lager  = URME_SS_DB::get_links( array( 'status' => 'lager' ) )['total'];
$drop   = URME_SS_DB::get_links( array( 'status' => 'dropship' ) )['total'];
ok( $counts['lager'] === $lager && $counts['dropship'] === $drop && $counts['linked'] === $lager + $drop, sprintf( 'Selected watches: Dropshipping %d + URME Lager %d = linked %d', $drop, $lager, $counts['linked'] ), $counts );
$m = new ReflectionMethod( 'URME_SS_Admin', 'render_mode_cell' );
$m->setAccessible( true );
$row = array_merge( URME_SS_DB::get_links( array( 'q' => 'REF000402' ) )['rows'][0], array() );
ob_start();
$m->invoke( null, $row, p( $T3 ), URME_SS_Store::cost_target() );
$cell = ob_get_clean();
ok( 2 === substr_count( $cell, '<option' ) && false !== strpos( $cell, '>URME Lager</option>' ) && false !== strpos( $cell, '>Dropshipping</option>' ) && false === strpos( $cell, 'Paused' ) && false === strpos( $cell, 'Local first' ) && false !== strpos( $cell, 'name="local_qty" min="1" step="1" value=""' ), 'mode switcher: URME Lager / Dropshipping only; a Dropshipping watch offers no prefilled quantity (never the supplier stock)', ff_text( $cell ) );
$r1 = admin( 'set_mode', ts_link( 402 ), 'paused' );
$r2 = admin( 'run_row_action', 'resume|' . ts_link( 402 )['id'] );
ok( 'error' === $r1[1] && 'error' === $r2[1] && 1 === (int) ts_link( 402 )['sync_enabled'], 'Pause / Resume requests: refused, nothing changed', array( $r1[0], $r2[0] ) );
settings( array( 'rate_override' => '' ) );
