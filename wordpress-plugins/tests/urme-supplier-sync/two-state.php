<?php
/**
 * Two Fulfillment states only: URME Lager and Dropshipping. A watch with URME stock is never in
 * supplier sync; "Dropshipping" in the catalog only at stock 0 (never automatically); "URME Lager"
 * removes a Dropshipping watch from supplier sync with stock 0 / out of stock. 1.5.2 cleanup.
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
$sel = admin( 'select_items', array( key_of( 396 ), key_of( 402 ), key_of( 408 ), key_of( 414 ) ) );
run( array( 'refresh_feed' => false ) );
ok( null === ts_link( 396 ) && null === ts_link( 402 ) && null === ts_link( 414 ) && 'supplier' === ts_link( 408 )['stock_mode'] && 8 === p( $T0 )->get_stock_quantity(), 'REF000396 / 402 / 414 stay URME Lager (1 / 3 / 1 units, not selected), REF000408 Dropshipping (supplier 8)', $sel );
ok( 'URME Lager' === pa_badge( $T1 ) && 'URME Lager' === pa_badge( $T3 ) && 'Dropshipping' === pa_badge( $T0 ), 'badges: only "URME Lager" / "Dropshipping"' );
$http = 0;
$hf   = static function ( $pre ) use ( &$http ) {
	++$http;
	return $pre;
};
add_filter( 'pre_http_request', $hf );

section( 'TS1. URME Lager → Dropshipping only at stock 0' );
$b3 = bs_state( $T3 );
$r1 = admin( 'run_row_action', 'start_supplier|' . key_of( 402 ) );
$r2 = ax_key( 'start_supplier', 402 );
$b1 = bs_state( $T1 );
$r3 = admin( 'run_row_action', 'start_supplier|' . key_of( 396 ) );
ok( 'error' === $r1[1] && 'Local URME stock exists (3 units). Dropshipping can only start when the URME Lager stock is 0.' === $r1[0] && false === $r2['success'] && $b3 === bs_state( $T3 ) && null === ts_link( 402 ), 'stock 3: Dropshipping refused with a clear error; not selected, stock, COGS and prices unchanged', $r1 );
ok( 'error' === $r3[1] && false !== strpos( $r3[0], '(1 unit)' ) && $b1 === bs_state( $T1 ), 'stock 1: refused too, nothing changed', $r3 );

section( 'TS2. The last unit is sold: stays URME Lager until "Dropshipping" is clicked' );
$o  = lf_order( $T1, 1 ); // WooCommerce: stock 1 → 0.
run( array( 'refresh_feed' => false ) );
$s1 = bs_state( $T1 );
ok( null === ts_link( 396 ) && 0 === $s1['stock'] && 'outofstock' === $s1['status'] && abs( $s1['cogs'] - 900 ) < 0.001 && 'URME Lager' === pa_badge( $T1 ), 'stock 0: no automatic switch; out of stock, COGS 900 kept, badge URME Lager', $s1 );
$row = ca_row( ph_catalog( array( 'q' => 'REF000396' ) ), 'REF000396' );
ok( false !== strpos( $row, 'value="start_supplier|' . key_of( 396 ) . '"' ), '   the catalog row now offers "Dropshipping"' );
$r  = admin( 'run_row_action', 'start_supplier|' . key_of( 396 ) );
$s1 = bs_state( $T1 );
ok( 'success' === $r[1] && 'supplier' === ts_link( 396 )['stock_mode'] && 8 === $s1['stock'] && abs( $s1['cogs'] - $sek ) < 0.001 && 'Dropshipping' === pa_badge( $T1 ), '   clicked: Dropshipping, supplier stock 8 and cost 176 EUR × rate written', array( $r, $s1 ) );
ok( '4990' === $s1['regular'] && '4490' === $s1['sale'], '   selling prices unchanged' );
lf_order( $T3, 1 ); // 3 → 2.
ok( null === ts_link( 402 ) && 2 === p( $T3 )->get_stock_quantity(), 'a sale 3 → 2: nothing changes, stock 2' );
bs_set( $T3, 0 ); // Stock set to 0 by hand in WooCommerce.
run( array( 'refresh_feed' => false ) );
ok( null === ts_link( 402 ) && 0 === p( $T3 )->get_stock_quantity(), 'stock set to 0 by hand: stays URME Lager as well' );

remove_filter( 'pre_http_request', $hf );

section( 'TS3. Dropshipping → URME Lager: removed from supplier sync, stock 0 / out of stock' );
$c0 = (float) p( $T0 )->get_cogs_value();
$l  = ts_link( 408 );
$r  = admin( 'run_row_action', 'lager|' . $l['id'] );
ok( 'success' === $r[1] && null === ts_link( 408 ) && 0 === p( $T0 )->get_stock_quantity() && 'outofstock' === p( $T0 )->get_stock_status() && abs( (float) p( $T0 )->get_cogs_value() - $c0 ) < 0.001 && 'URME Lager' === pa_badge( $T0 ), 'Dropshipping (stock 8) → URME Lager: link removed, stock 0, out of stock (the supplier 8 is not kept), COGS and prices untouched', array( $r, bs_state( $T0 ) ) );
ok( '4990' === p( $T0 )->get_regular_price() && '4490' === p( $T0 )->get_sale_price(), '   prices unchanged' );
$row = ca_row( ph_catalog( array( 'q' => 'REF000408' ) ), 'REF000408' );
ok( false !== strpos( $row, 'value="start_supplier|' . key_of( 408 ) . '"' ) && false === strpos( $row, 'lager|' ) && false === strpos( $row, 'value="sync|' ), '   its catalog row: not selected, "Dropshipping" offered (stock 0)', ff_text( $row ) );
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, $PA_SET, $AX_SET, $BF_SET, $TS_SET, array( 'REF000408' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '9', 'PURCHASE_PRICE' => '190.00' ) ) ) );
run();
ok( null === ts_link( 408 ) && 0 === p( $T0 )->get_stock_quantity() && 'outofstock' === p( $T0 )->get_stock_status() && abs( (float) p( $T0 )->get_cogs_value() - $c0 ) < 0.001, 'at 0: not switched back to Dropshipping; the next sync (supplier 9 / 190 EUR) writes nothing', bs_state( $T0 ) );
$r = admin( 'run_row_action', 'lager|' . $l['id'] );
ok( 'error' === $r[1] && 0 === p( $T0 )->get_stock_quantity(), '   "URME Lager" again (old link): nothing changed', $r );
bs_set( $T0, 3 ); // The admin enters the real stock in WooCommerce.
run( array( 'refresh_feed' => false ) );
ok( null === ts_link( 408 ) && 3 === p( $T0 )->get_stock_quantity() && 'instock' === p( $T0 )->get_stock_status() && 'URME Lager' === pa_badge( $T0 ), 'stock 3 entered in WooCommerce: normal URME Lager product, in stock, kept by the sync' );
lf_order( $T0, 3 );
run( array( 'refresh_feed' => false ) );
ok( null === ts_link( 408 ) && 0 === p( $T0 )->get_stock_quantity(), '   that stock sold to 0: still URME Lager (no automatic switch)' );
$r = admin( 'run_row_action', 'start_supplier|' . key_of( 408 ) );
ok( 'success' === $r[1] && 'supplier' === ts_link( 408 )['stock_mode'] && 9 === p( $T0 )->get_stock_quantity() && abs( (float) p( $T0 )->get_cogs_value() - round( 190 * 11.321, 2 ) ) < 0.001, '   "Dropshipping" clicked: supplier stock 9, cost 190 EUR × rate', array( $r, bs_state( $T0 ) ) );

section( 'TS4. No Selected watches page; only URME Lager / Dropshipping' );
foreach ( array( 'selected', '' ) as $t ) {
	$_GET['tab'] = $t;
	$page        = ff_admin_html( array( 'URME_SS_Admin', 'render' ) );
	unset( $_GET['tab'] );
	ok( false === strpos( $page, 'Selected watches' ) && false === strpos( $page, 'tab=selected' ) && 1 === preg_match( '#nav-tab nav-tab-active">Supplier catalog<#', $page ), sprintf( '   page (tab "%s"): no "Selected watches" tab or link; the Supplier catalog is shown', $t ) );
}
foreach ( array( 'render_selected', 'render_mode_cell', 'link_product', 'automatch' ) as $gone ) {
	ok( ! method_exists( 'URME_SS_Admin', $gone ), '   removed: URME_SS_Admin::' . $gone );
}
$counts = URME_SS_DB::link_counts();
ok( $counts['linked'] === $counts['lager'] + $counts['dropship'], sprintf( 'counts: Dropshipping %d + URME Lager %d = linked %d', $counts['dropship'], $counts['lager'], $counts['linked'] ), $counts );
$r1 = admin( 'set_mode', ts_link( 408 ), 'paused' );
$r2 = admin( 'run_row_action', 'resume|' . ts_link( 408 )['id'] );
$r3 = admin( 'run_row_action', 'start_local|' . key_of( 402 ) );
ok( 'error' === $r1[1] && 'error' === $r2[1] && 'success' !== $r3[1] && 1 === (int) ts_link( 408 )['sync_enabled'] && null === ts_link( 402 ), 'Pause / Resume / "Local first" requests: refused, nothing changed', array( $r1[0], $r2[0], $r3[0] ) );

section( 'TS5. 1.5.2 upgrade: URME Lager links leave supplier sync once' );
ok( '1' === get_option( 'urme_ss_lager_links_removed' ), 'cleanup done once on load (option set)' );
$TL = bs_product( 'REF000420', key_of( 420 ), 4, 610 ); // Legacy Local first link.
$TP = bs_product( 'REF000426', key_of( 426 ), 2, 620 ); // Legacy paused link.
$TD = bs_product( 'REF000432', key_of( 432 ), 0, 630 ); // Dropshipping: kept.
$TF = bs_product( 'REF000438', key_of( 438 ), 0, 640 ); // Supplier link of a brand with sync off: kept.
foreach ( array( 420, 426, 432 ) as $i ) {
	$TS_SET[ sprintf( 'REF%06d', $i ) ] = array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' );
}
$TS_SET['REF000438'] = array( 'MANUFACTURER' => 'Rado', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' );
$TS_SET['REF000444'] = array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' );
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, $PA_SET, $AX_SET, $BF_SET, $TS_SET ) );
run( array( 'force_feed' => true ) );
URME_SS_Settings::set_brand( 'Rado', true );
lf_select( 420 );
lf_legacy_select( 426 );
lf_legacy_select( 432 );
lf_legacy_select( 438 );
URME_SS_DB::insert_link( key_of( 444 ), 0, '' ); // Legacy selection without a product.
$wpdb->update( URME_SS_DB::links_table(), array( 'sync_enabled' => 0 ), array( 'item_key' => key_of( 426 ) ) );
URME_SS_Settings::set_brand( 'Rado', false );
ok( 'local_first' === ts_link( 420 )['stock_mode'] && ! (int) ts_link( 426 )['sync_enabled'] && 0 === (int) ts_link( 444 )['product_id'] && ts_link( 432 ) && ts_link( 438 ), '   (setup: legacy Local first, paused and product-less links; a Dropshipping and a brand-off link)' );
$before = array( bs_state( $TL ), bs_state( $TP ), bs_state( $TD ), bs_state( $TF ) );
$n      = URME_SS_Inventory::remove_lager_links();
ok( $n >= 3 && null === ts_link( 420 ) && null === ts_link( 426 ) && null === ts_link( 444 ), 'Local first, paused and product-less links removed', $n );
ok( 'supplier' === ts_link( 432 )['stock_mode'] && 'supplier' === ts_link( 438 )['stock_mode'], 'Dropshipping link and the brand-off supplier link kept' );
ok( array( bs_state( $TL ), bs_state( $TP ), bs_state( $TD ), bs_state( $TF ) ) === $before, 'stock, COGS and prices of all four products unchanged', $before );
ok( 'URME Lager' === pa_badge( $TL ) && 'URME Lager' === pa_badge( $TP ) && 'Dropshipping' === pa_badge( $TD ), '   badges: URME Lager / URME Lager / Dropshipping' );
ok( 0 === URME_SS_Inventory::remove_lager_links(), '   running it again removes nothing' );
settings( array( 'rate_override' => '' ) );
