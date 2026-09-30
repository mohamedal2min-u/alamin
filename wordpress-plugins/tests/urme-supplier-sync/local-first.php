<?php
/**
 * Local first → Supplier automatically (1.1.0). Included at the end of scenarios.php
 * and uses its helpers (ok, section, feed, mode, run, p, cat, link_of, key_of, settings, sync_stats, cogs).
 */

global $wpdb;

/* ---------------------------------------------------------------- helpers */

// Feed values used by these tests (supplier stock 8, EUR 200 for the SKU 1513905 case).
$GLOBALS['LF_SET'] = array(
	'REF000000' => array( 'STOCK' => '3' ),
	'REF000036' => array( 'STOCK' => '8', 'PURCHASE_PRICE' => '200.00' ),
	'REF000042' => array( 'STOCK' => '6', 'PURCHASE_PRICE' => '100.00' ),
	'REF000048' => array( 'STOCK' => '5', 'PURCHASE_PRICE' => '100.00' ),
	'REF000054' => array( 'STOCK' => '7', 'PURCHASE_PRICE' => '100.00' ),
	'REF000060' => array( 'STOCK' => '9', 'PURCHASE_PRICE' => '100.00' ),
	'REF000066' => array( 'STOCK' => '4', 'PURCHASE_PRICE' => '100.00' ),
	'REF000072' => array( 'STOCK' => '4', 'PURCHASE_PRICE' => '100.00' ),
	'REF000037' => array( 'STOCK' => '5', 'PURCHASE_PRICE' => '100.00' ),
);
function lf_feed( array $extra = array() ) {
	feed( array( 'set' => array_merge( $GLOBALS['LF_SET'], $extra ) ) );
}
function lf_product( $name, $sku, $qty, $cogs = null, $backorders = 'no' ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_sku( $sku );
	$p->set_regular_price( '4990' );
	$p->set_sale_price( '4490' );
	$p->set_description( 'Local description' );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( $qty );
	$p->set_backorders( $backorders );
	if ( null !== $cogs ) {
		$p->set_cogs_value( (float) $cogs );
	}
	return $p->save();
}
function lf_select( $i ) {
	URME_SS_Matcher::reset();
	admin( 'select_items', array( key_of( $i ) ) );
	return link_of( $i );
}
/**
 * A Supplier-now link as earlier versions made it (bulk selection linked any matched product as
 * Supplier now, whatever its stock). Since 1.4 selection starts a product with stock as Local
 * first; tests about the sync of an existing Supplier-now link over stock use this.
 */
function lf_legacy_select( $i ) {
	URME_SS_Matcher::reset();
	$match = URME_SS_Store::auto_match( URME_SS_DB::get_item( key_of( $i ) ) );
	URME_SS_DB::insert_link( key_of( $i ), $match['product_id'], $match['method'] );
	return link_of( $i );
}
function lf_link( $i ) {
	return link_of( $i );
}
function lf_order( $pid, $qty, $status = 'processing' ) {
	$o = wc_create_order();
	$o->add_product( wc_get_product( $pid ), $qty );
	$o->calculate_totals();
	$o->save();
	$o->update_status( $status );
	return wc_get_order( $o->get_id() );
}
function lf_line( $order ) {
	$items = wc_get_order( $order->get_id() )->get_items();
	return reset( $items );
}
function lf_alloc( $item_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . URME_SS_DB::alloc_table() . ' WHERE order_item_id = %d', $item_id ), ARRAY_A );
}
function lf_refund( $order, $qty ) {
	$item = lf_line( $order );
	return wc_create_refund(
		array(
			'order_id'       => $order->get_id(),
			'amount'         => 0,
			'line_items'     => array(
				$item->get_id() => array(
					'qty'          => $qty,
					'refund_total' => 0,
				),
			),
			'restock_items'  => true,
		)
	);
}
function lf_log_count( $needle ) {
	URME_SS_Log::flush();
	$n = 0;
	foreach ( URME_SS_Log::entries( 150 ) as $e ) {
		$n += false !== strpos( $e[2], $needle ) ? 1 : 0;
	}
	return $n;
}
function lf_stock( $pid ) {
	return p( $pid )->get_stock_quantity();
}
function lf_cogs( $pid ) {
	return p( $pid )->get_cogs_value();
}
function lf_private_meta( $item_id ) {
	$keys = wp_list_pluck( WC_Order_Factory::get_order_item( $item_id )->get_meta_data(), 'key' );
	return array_values( array_filter( $keys, static function ( $k ) { return 0 === strpos( $k, '_urme' ); } ) );
}
function lf_rate() {
	return URME_SS_Rates::current()['rate'];
}

/* ------------------------------------------------------------------ setup */
section( 'LF0. Local first setup' );
cogs( true );
settings( array( 'cost_target' => 'wc_cogs', 'enabled_brands' => array( 'Seiko', 'Tissot', 'Guess' ), 'rate_override' => '' ) );
mode( 'ok' );
lf_feed();
run( array( 'force_feed' => true ) );
ok( 8 === (int) cat( 36 )['stock'] && 200.0 === (float) cat( 36 )['purchase_price'], 'supplier feed: REF000036 stock 8, EUR 200' );

$L1 = lf_product( 'SKU 1513905 (local 1)', 'REF000036', 1, 900 );
$l  = lf_select( 36 );
ok( (int) $l['product_id'] === $L1 && 'local_first' === $l['stock_mode'] && 1 === (int) $l['local_qty'] && 900.0 === (float) $l['local_cost'], 'selected with URME stock 1: starts as Local first (1 unit, cost 900), never Supplier now', $l );
ok( true === URME_SS_Inventory::enable_local( (int) $l['id'], 1, 900 ), 'Local first enabled (1 local unit, cost 900)' );
$l = lf_link( 36 );
ok( 'local_first' === $l['stock_mode'] && 1 === (int) $l['local_qty'] && 900.0 === (float) $l['local_cost'] && 0 === (int) $l['needs_stock_apply'], 'link state: local_first, 1 unit, cost 900' );

/* ------------------------------------------------------------------ 1 + 3 */
section( 'LF1/LF3. Local 1, supplier 8: sync keeps Woo stock 1 and local COGS' );
run();
ok( 1 === lf_stock( $L1 ), 'Woo stock stays 1 (supplier has 8)', lf_stock( $L1 ) );
ok( abs( lf_cogs( $L1 ) - 900 ) < 0.001, 'COGS stays local 900 (not 200 x rate)', lf_cogs( $L1 ) );
$l = lf_link( 36 );
ok( 'local' === $l['last_status'] && 8 === (int) $l['last_stock'] && abs( (float) $l['last_cost_sek'] - round( 200 * lf_rate(), 2 ) ) < 0.01, 'supplier stock/cost still read and shown', $l );
ok( false !== strpos( $l['last_message'], 'URME Lager: 1 in stock; switches to Dropshipping at 0.' ), 'status: URME Lager, 1 in stock, switches at 0', $l['last_message'] );
ok( 1 === sync_stats()['local_waiting'] && 0 === sync_stats()['handovers'], 'stats: 1 waiting, 0 switched', sync_stats() );
ok( '4990' === p( $L1 )->get_regular_price() && '4490' === p( $L1 )->get_sale_price(), 'prices untouched' );

/* ------------------------------------------------------------------ 2 + 4 */
section( 'LF2/LF4. Local unit sells → Supplier on next safe sync, supplier cost starts' );
$o1 = lf_order( $L1, 1 );
$i1 = lf_line( $o1 );
$a  = lf_alloc( $i1->get_id() );
ok( $a && 1 === (int) $a['local_allocated'] && 0 === (int) $a['supplier_allocated'] && 1 === (int) $a['last_reduced_stock'], 'ledger: 1 local unit sold', $a );
ok( ! lf_private_meta( $i1->get_id() ), 'nothing stored on the order line (ledger only)' );
$l = lf_link( 36 );
ok( 0 === (int) $l['local_qty'] && 'local_first' === $l['stock_mode'], 'local 0, still Local first until a safe sync' );
ok( 0 === lf_stock( $L1 ) && 'outofstock' === p( $L1 )->get_stock_status(), 'Woo stock 0 / out of stock meanwhile' );
$notes = wc_get_order_notes( array( 'order_id' => $o1->get_id() ) );
ok( ! array_filter( $notes, static function ( $n ) { return false !== stripos( $n->content, 'URME' ); } ), 'no order note added (source stays admin-only)' );
run();
$l = lf_link( 36 );
ok( 'supplier' === $l['stock_mode'] && 1 === sync_stats()['handovers'], 'switched to Supplier now in the safe sync', sync_stats() );
ok( 8 === lf_stock( $L1 ) && 'instock' === p( $L1 )->get_stock_status(), 'Woo stock = supplier 8' );
ok( abs( lf_cogs( $L1 ) - round( 200 * lf_rate(), 2 ) ) < 0.001, 'COGS = supplier 200 EUR x rate', lf_cogs( $L1 ) );
ok( 1 === lf_log_count( 'Transition Local first → Supplier for product #' . $L1 ), 'transition logged once' );

/* ------------------------------------------------------------------ 8 */
section( 'LF8. Cron twice / re-saves: no double transition or double booking' );
run();
do_action( URME_SS_Plugin::CRON_HOOK );
URME_SS_Log::flush();
ok( 0 === sync_stats()['handovers'] && 'supplier' === lf_link( 36 )['stock_mode'], 'no second switch' );
ok( 1 === lf_log_count( 'Transition Local first → Supplier for product #' . $L1 ), 'still exactly one transition in the log' );
$item = lf_line( $o1 );
$item->save();
$item->save();
URME_SS_Inventory::reconcile_item( $item->get_id() );
ok( lf_alloc( $item->get_id() ) === $a, 'ledger unchanged after repeated saves/reconciles' );
ok( 8 === lf_stock( $L1 ) && 0 === (int) lf_link( 36 )['local_qty'], 'stock and local count unchanged' );

/* ------------------------------------------------------------------ 7 */
section( 'LF7. Local unit returned after the switch → back to Local first, not mixed' );
lf_refund( $o1, 1 );
$a = lf_alloc( $i1->get_id() );
$l = lf_link( 36 );
ok( 0 === (int) $a['local_allocated'] && 0 === (int) $a['last_reduced_stock'], 'ledger: local unit returned', $a );
ok( 'local_first' === $l['stock_mode'] && 1 === (int) $l['local_qty'] && 0 === (int) $l['needs_stock_apply'], 'Supplier → Local first, 1 local unit', $l );
ok( 1 === lf_stock( $L1 ), 'Woo stock = 1 local unit (not supplier 8 + 1)', lf_stock( $L1 ) );
ok( abs( lf_cogs( $L1 ) - 900 ) < 0.001, 'COGS restored to local 900', lf_cogs( $L1 ) );
ok( 1 === lf_log_count( 'Transition Supplier → Local first for product #' . $L1 ), 'return transition logged' );
run();
ok( 1 === lf_stock( $L1 ) && 'local_first' === lf_link( 36 )['stock_mode'], 'sync keeps it local (1 unit) while supplier has 8' );
$o1b = lf_order( $L1, 1 );
run();
ok( 'supplier' === lf_link( 36 )['stock_mode'] && 8 === lf_stock( $L1 ), 'returned unit sold again → Supplier, stock 8' );

/* ------------------------------------------------------------------ 5 */
section( 'LF5. Feed fails when local reaches 0 → no switch, no writes' );
$L5 = lf_product( 'Local feed-fail', 'REF000042', 1, 500 );
$l5 = lf_select( 42 );
URME_SS_Inventory::enable_local( (int) $l5['id'], 1, 500 );
lf_order( $L5, 1 );
ok( 0 === (int) lf_link( 42 )['local_qty'] && 0 === lf_stock( $L5 ), 'local sold out, Woo stock 0' );
mode( 'http500' );
$r = run();
ok( false === $r['feed_ok'] && 'local_first' === lf_link( 42 )['stock_mode'], 'feed failed: still Local first' );
ok( 0 === lf_stock( $L5 ) && abs( lf_cogs( $L5 ) - 500 ) < 0.001, 'no stock or cost written', array( lf_stock( $L5 ), lf_cogs( $L5 ) ) );
$st                         = get_option( URME_SS_Sync::STATUS_OPTION );
$st['feed']['last_success'] = time() - 5 * HOUR_IN_SECONDS;
update_option( URME_SS_Sync::STATUS_OPTION, $st, false );
run( array( 'refresh_feed' => false ) );
ok( 'local_first' === lf_link( 42 )['stock_mode'] && 0 === lf_stock( $L5 ), 'stale catalog (product-only sync): no switch either' );
mode( 'ok' );
run( array( 'force_feed' => true ) );
ok( 'supplier' === lf_link( 42 )['stock_mode'] && 6 === lf_stock( $L5 ), 'next safe sync: switched, supplier stock 6' );

/* ------------------------------------------------------------------ 6 */
section( 'LF6. Local sale cancelled → local stock restored' );
$L6 = lf_product( 'Local cancel', 'REF000048', 2, 700 );
$l6 = lf_select( 48 );
URME_SS_Inventory::enable_local( (int) $l6['id'], 2, 700 );
$o6 = lf_order( $L6, 1 );
ok( 1 === (int) lf_link( 48 )['local_qty'] && 1 === lf_stock( $L6 ), 'after sale: local 1, Woo 1' );
$o6->update_status( 'cancelled' );
$a6 = lf_alloc( lf_line( $o6 )->get_id() );
ok( 2 === (int) lf_link( 48 )['local_qty'] && 2 === lf_stock( $L6 ), 'after cancel: local 2, Woo 2' );
ok( 0 === (int) $a6['local_allocated'] && 0 === (int) $a6['last_reduced_stock'], 'ledger cleared', $a6 );
ok( 'local_first' === lf_link( 48 )['stock_mode'], 'still Local first' );

/* ------------------------------------------------------------------ mixed */
section( 'LF-M. Mixed line (1 local + 1 supplier): restock returns local first' );
$LM = lf_product( 'Local mixed', 'REF000054', 1, 650 );
$lm = lf_select( 54 );
URME_SS_Inventory::enable_local( (int) $lm['id'], 1, 650 );
$om = lf_order( $LM, 2 ); // Programmatic order: WooCommerce does not check stock here.
$am = lf_alloc( lf_line( $om )->get_id() );
ok( 1 === (int) $am['local_allocated'] && 1 === (int) $am['supplier_allocated'], 'ledger: 1 local + 1 supplier', $am );
ok( 0 === (int) lf_link( 54 )['local_qty'] && 0 === lf_stock( $LM ), 'local never negative; Woo stock re-aligned to 0 (not -1)', lf_stock( $LM ) );
lf_refund( $om, 1 );
$am = lf_alloc( lf_line( $om )->get_id() );
ok( 0 === (int) $am['local_allocated'] && 1 === (int) $am['supplier_allocated'], 'returned unit booked back to local first', $am );
ok( 1 === (int) lf_link( 54 )['local_qty'] && 1 === lf_stock( $LM ) && 'local_first' === lf_link( 54 )['stock_mode'], 'local 1, Woo 1, Local first' );

/* ------------------------------------------------------------------ admin edit */
section( 'LF-E. Admin changes line quantity / removes line' );
require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php'; // Same function the order screen and REST API use.
$LE = lf_product( 'Local admin edit', 'REF000060', 2, 800 );
$le = lf_select( 60 );
URME_SS_Inventory::enable_local( (int) $le['id'], 2, 800 );
$oe = lf_order( $LE, 1 );
$ie = lf_line( $oe );
$ie->set_quantity( 2 );
wc_maybe_adjust_line_item_product_stock( $ie, 2 );
ok( 0 === (int) lf_link( 60 )['local_qty'] && 2 === (int) lf_alloc( $ie->get_id() )['local_allocated'], 'qty 1→2: second local unit booked' );
wc_maybe_adjust_line_item_product_stock( lf_line( $oe ), 0 );
ok( 2 === (int) lf_link( 60 )['local_qty'] && 2 === lf_stock( $LE ) && 0 === (int) lf_alloc( $ie->get_id() )['local_allocated'], 'line removed: both local units back' );

/* ------------------------------------------------------------------ 9 */
section( 'LF9. Brand disabled → Local first state preserved' );
$L9 = lf_product( 'Local Tissot', 'REF000037', 1, 400 );
$l9 = lf_select( 37 );
URME_SS_Inventory::enable_local( (int) $l9['id'], 1, 400 );
URME_SS_Settings::set_brand( 'Tissot', false );
run();
$l = lf_link( 37 );
ok( 'local_first' === $l['stock_mode'] && 1 === (int) $l['local_qty'] && 400.0 === (float) $l['local_cost'], 'brand off: local state kept' );
lf_order( $L9, 1 );
ok( 0 === (int) lf_link( 37 )['local_qty'], 'sales still tracked while brand is off' );
run();
ok( 'local_first' === lf_link( 37 )['stock_mode'] && 0 === lf_stock( $L9 ), 'brand off: no switch, no writes' );
URME_SS_Settings::set_brand( 'Tissot', true );
run();
ok( 'supplier' === lf_link( 37 )['stock_mode'] && 5 === lf_stock( $L9 ), 'brand on again: switched, supplier stock 5' );

/* ------------------------------------------------------------------ 10 */
section( 'LF10. Supplier now products behave exactly as before' );
$l = link_of( 1 ); // P2 from the main suite (Tissot, Supplier now).
ok( 'supplier' === $l['stock_mode'] && 0 === (int) $l['local_qty'], 'existing link is Supplier now' );
$before = lf_stock( $P2 );
$os     = lf_order( $P2, 1 );
$as     = lf_alloc( lf_line( $os )->get_id() );
ok( 0 === (int) $as['local_allocated'] && 1 === (int) $as['supplier_allocated'], 'sale booked as supplier unit' );
ok( 'supplier' === link_of( 1 )['stock_mode'] && 0 === (int) link_of( 1 )['local_qty'], 'no local count, mode unchanged' );
run();
ok( lf_stock( $P2 ) === (int) cat( 1 )['stock'], 'stock re-synced to supplier as before', array( lf_stock( $P2 ), cat( 1 )['stock'] ) );
$os->update_status( 'cancelled' );
ok( 'supplier' === link_of( 1 )['stock_mode'], 'cancel of a supplier sale does not switch to Local first' );

/* ------------------------------------------------------------------ backorders */
section( 'LF-B. Backorders cannot oversell local inventory' );
$LB = lf_product( 'Backorders on', 'REF000066', 1, 300, 'notify' );
$lb = lf_select( 66 );
ok( null === $lb && 1 === lf_stock( $LB ), 'selection refused: 1 local unit, but backorders are allowed (neither Local first nor Supplier now)' );
$lb = lf_legacy_select( 66 ); // An existing Supplier-now link (earlier version).
$r  = URME_SS_Inventory::enable_local( (int) $lb['id'], 1, 300 );
ok( is_string( $r ) && false !== stripos( $r, 'backorders' ), 'Local first refused while backorders are allowed', $r );
ok( 'supplier' === lf_link( 66 )['stock_mode'] && 'notify' === p( $LB )->get_backorders(), 'link unchanged; backorders setting not touched' );
$pb = p( $LB );
$pb->set_backorders( 'no' );
$pb->save();
ok( true === URME_SS_Inventory::enable_local( (int) $lb['id'], 1, 300 ), 'allowed once backorders are off' );
ok( ! p( $LB )->has_enough_stock( 2 ) && p( $LB )->has_enough_stock( 1 ), 'WooCommerce refuses 2 units with 1 local unit' );
WC()->frontend_includes();
WC()->initialize_session();
WC()->initialize_cart();
WC()->cart->empty_cart();
ok( false === WC()->cart->add_to_cart( $LB, 2 ), 'cart: adding 2 is refused' );
ok( false !== WC()->cart->add_to_cart( $LB, 1 ), 'cart: adding 1 works' );
WC()->cart->empty_cart();
$pb = p( $LB );
$pb->set_backorders( 'yes' ); // Turned on later by someone.
$pb->save();
run();
ok( 1 === sync_stats()['local_blocked'] && 'error' === lf_link( 66 )['last_status'], 'sync puts it on hold and flags it', sync_stats() );
ok( 1 === lf_stock( $LB ) && 'local_first' === lf_link( 66 )['stock_mode'], 'no writes while on hold' );
$ob = lf_order( $LB, 3 );
$ab = lf_alloc( lf_line( $ob )->get_id() );
ok( 1 === (int) $ab['local_allocated'] && 2 === (int) $ab['supplier_allocated'] && 0 === (int) lf_link( 66 )['local_qty'], 'only 1 local unit booked; local never below 0', $ab );
run();
ok( 'local_first' === lf_link( 66 )['stock_mode'], 'no switch to supplier while backorders are on' );
$pb = p( $LB );
$pb->set_backorders( 'no' );
$pb->save();
run();
ok( 'supplier' === lf_link( 66 )['stock_mode'] && 4 === lf_stock( $LB ), 'backorders off: switched, supplier stock 4' );

/* ------------------------------------------------------------------ crash */
section( 'LF-C. Interrupted booking: a local unit is never deducted twice' );
$LC = lf_product( 'Local crash', 'REF000072', 2, 350 );
$lc = lf_select( 72 );
URME_SS_Inventory::enable_local( (int) $lc['id'], 2, 350 );
// C1: fail right before COMMIT (both writes done inside the transaction).
$boom = static function ( $stage ) {
	if ( 'before_commit' === $stage ) {
		throw new RuntimeException( 'simulated crash' );
	}
};
add_action( 'urme_ss_inventory_checkpoint', $boom );
$oc = lf_order( $LC, 1 );
$ic = lf_line( $oc )->get_id();
ok( null === lf_alloc( $ic ) && 2 === (int) lf_link( 72 )['local_qty'], 'rolled back: no ledger row, local still 2', array( lf_alloc( $ic ), lf_link( 72 )['local_qty'] ) );
ok( 1 === lf_stock( $LC ), 'WooCommerce itself reduced its stock (1)' );
ok( lf_log_count( 'could not be booked' ) >= 1, 'error logged, nothing guessed' );
run( array( 'refresh_feed' => false ) );
ok( 'error' === lf_link( 72 )['last_status'] && 'local_first' === lf_link( 72 )['stock_mode'] && 1 === lf_stock( $LC ), 'sync while still failing: held, no writes' );
remove_action( 'urme_ss_inventory_checkpoint', $boom );
// C2: fail after the link row was updated inside the transaction.
$boom2 = static function ( $stage ) {
	if ( 'link_updated' === $stage ) {
		throw new RuntimeException( 'simulated crash after first write' );
	}
};
add_action( 'urme_ss_inventory_checkpoint', $boom2 );
URME_SS_Inventory::reconcile_item( $ic );
ok( 2 === (int) lf_link( 72 )['local_qty'] && null === lf_alloc( $ic ), 'first write rolled back too' );
remove_action( 'urme_ss_inventory_checkpoint', $boom2 );
// Recovery by the next safe sync, exactly once.
run( array( 'refresh_feed' => false ) );
ok( 1 === (int) lf_link( 72 )['local_qty'] && 1 === (int) lf_alloc( $ic )['local_allocated'], 'sweep booked it once: local 1' );
run( array( 'refresh_feed' => false ) );
URME_SS_Inventory::reconcile_item( $ic );
ok( 1 === (int) lf_link( 72 )['local_qty'] && 1 === (int) lf_alloc( $ic )['local_allocated'], 'repeat runs: still local 1 (no double deduction)' );
// C3: the ledger is the only record; re-saving the line books nothing again.
lf_line( $oc )->save();
URME_SS_Inventory::reconcile_item( $ic );
ok( ! lf_private_meta( $ic ) && 1 === (int) lf_link( 72 )['local_qty'], 'no order-line meta; re-save causes no second deduction' );
// C4: our hook never ran for a sale (e.g. PHP died right after WooCommerce saved the line).
remove_action( 'woocommerce_update_order_item', array( 'URME_SS_Inventory', 'on_item_saved' ), 20 );
remove_action( 'woocommerce_new_order_item', array( 'URME_SS_Inventory', 'on_item_saved' ), 20 );
$oc4 = lf_order( $LC, 1 );
URME_SS_Inventory::init();
$ic4 = lf_line( $oc4 )->get_id();
ok( null === lf_alloc( $ic4 ) && 1 === (int) lf_link( 72 )['local_qty'], 'hook missed: nothing booked yet' );
run( array( 'refresh_feed' => false ) );
ok( 1 === (int) lf_alloc( $ic4 )['local_allocated'] && 0 === (int) lf_link( 72 )['local_qty'], 'sweep booked the missed sale once' );
ok( 'supplier' === lf_link( 72 )['stock_mode'] && 4 === lf_stock( $LC ), 'then switched in the same safe run (supplier 4)' );
// C5: concurrent change between read and write → compare-and-swap retry, booked once.
$LC5 = lf_product( 'Local CAS', 'REF000078', 3, 350 );
$GLOBALS['LF_SET']['REF000078'] = array( 'STOCK' => '4', 'PURCHASE_PRICE' => '100.00' );
lf_feed();
run( array( 'force_feed' => true ) );
$lc5 = lf_select( 78 );
URME_SS_Inventory::enable_local( (int) $lc5['id'], 3, 350 );
$fired = 0;
$race  = static function ( $stage ) use ( &$fired, $lc5 ) {
	global $wpdb;
	if ( 'planned' === $stage && 0 === $fired++ ) {
		// Another request changes the row between our read and our write.
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . URME_SS_DB::links_table() . ' SET local_qty = 5 WHERE id = %d', $lc5['id'] ) );
	}
};
add_action( 'urme_ss_inventory_checkpoint', $race );
$o5 = lf_order( $LC5, 1 );
remove_action( 'urme_ss_inventory_checkpoint', $race );
$a5 = lf_alloc( lf_line( $o5 )->get_id() );
ok( $fired >= 2 && 1 === (int) $a5['local_allocated'], 'conflict detected and retried; 1 unit booked', array( $fired, $a5 ) );
ok( 2 === (int) lf_link( 78 )['local_qty'], 'local 3 → 2 exactly once (the racing write was rolled back with the failed attempt)', lf_link( 78 )['local_qty'] );

/* ------------------------------------------------------------------ admin guards */
section( 'LF-G. Admin mode switching and guards' );
$lg = lf_link( 78 );
$_POST = array( 'link_id' => $lg['id'] );
$res = ( new ReflectionMethod( 'URME_SS_Admin', 'set_mode' ) );
$res->setAccessible( true );
$out = $res->invoke( null, $lg, 'supplier' );
ok( 'error' === $out[1] && 'local_first' === lf_link( 78 )['stock_mode'], 'Supplier now refused while local units remain', $out );
$_POST['confirm_drop'] = '1';
$out = $res->invoke( null, $lg, 'supplier' );
ok( 'error' === $out[1] && 'local_first' === lf_link( 78 )['stock_mode'] && (int) $lg['local_qty'] === (int) lf_link( 78 )['local_qty'], '   no confirmation can override it: local units kept', $out );
// The local units are gone (count 0, WooCommerce stock 0): now the manual switch is allowed.
$wpdb->update( URME_SS_DB::links_table(), array( 'local_qty' => 0 ), array( 'id' => $lg['id'] ) );
wc_update_product_stock( wc_get_product( $LC5 ), 0, 'set' );
$out = $res->invoke( null, lf_link( 78 ), 'supplier' );
ok( 'success' === $out[1] && 'supplier' === lf_link( 78 )['stock_mode'] && 0 === (int) lf_link( 78 )['local_qty'], '0 local units and stock 0: Supplier now allowed', $out );
ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . URME_SS_Price_Review::table() . ' WHERE link_id = %d', $lg['id'] ) ), 'manual switch to Supplier now creates no price review' );
run();
ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . URME_SS_Price_Review::table() . ' WHERE link_id = %d', $lg['id'] ) ), '...and the next sync does not create one either' );
$out = $res->invoke( null, lf_link( 78 ), 'local' );
ok( 'success' === $out[1] && 'local_first' === lf_link( 78 )['stock_mode'] && ! (int) lf_link( 78 )['sync_enabled'] && 0 === lf_stock( $LC5 ), '(1.5.1) back to URME Lager via admin: stock 0, waiting for the real stock', $out );
wc_update_product_stock( wc_get_product( $LC5 ), 2, 'set' ); // The admin enters 2 in WooCommerce.
ok( 1 === (int) lf_link( 78 )['sync_enabled'] && 2 === (int) lf_link( 78 )['local_qty'] && 2 === lf_stock( $LC5 ), '   stock 2 entered: active URME Lager with 2' );
$out = $res->invoke( null, lf_link( 78 ), 'paused' );
ok( 'error' === $out[1] && '1' === (string) lf_link( 78 )['sync_enabled'] && 'local_first' === lf_link( 78 )['stock_mode'] && 2 === lf_stock( $LC5 ), '(1.5) Pause no longer exists: refused, URME Lager unchanged', $out );
$_POST = array();
// 1.5: no order history, so relinking is allowed; a product with stock is linked as URME Lager.
$msg = admin( 'link_product', lf_link( 78 ), $P8 );
ok( (int) lf_link( 78 )['product_id'] === $P8 && 'local_first' === lf_link( 78 )['stock_mode'] && 2 === lf_stock( $LC5 ), '(1.5) relinking a URME Lager watch to a product with stock: linked as URME Lager, the old product keeps its stock', $msg );
$msg = admin( 'link_product', lf_link( 78 ), $LC5 ); // Back to its own product for the tests below.
ok( (int) lf_link( 78 )['product_id'] === $LC5 && 'local_first' === lf_link( 78 )['stock_mode'], '   linked back', $msg );

/* ------------------------------------------------------------------ hard crash */
section( 'LF-K. Process killed mid-transaction: database discards it, booked once later' );
$LK = lf_product( 'Local hard crash', 'REF000084', 2, 350 );
$GLOBALS['LF_SET']['REF000084'] = array( 'STOCK' => '4', 'PURCHASE_PRICE' => '100.00' );
lf_feed();
run( array( 'force_feed' => true ) );
$lk = lf_select( 84 );
URME_SS_Inventory::enable_local( (int) $lk['id'], 2, 350 );
putenv( 'URME_CRASH_PRODUCT=' . $LK );
$child = WP_CLI::runcommand( 'eval-file ' . __DIR__ . '/crash-child.php', array( 'launch' => true, 'exit_error' => false, 'return' => 'all' ) );
wp_cache_flush();
$ok_id = (int) get_option( 'urme_crash_order' );
$ik    = $ok_id ? lf_line( wc_get_order( $ok_id ) )->get_id() : 0;
ok( 3 === (int) $child->return_code && false !== strpos( $child->stdout, 'dying before COMMIT' ), 'child process died inside the transaction', array( $child->return_code, $child->stdout ) );
ok( $ik && null === lf_alloc( $ik ) && 2 === (int) lf_link( 84 )['local_qty'], 'database discarded the half-done booking: no ledger row, local still 2', array( $ik, lf_alloc( $ik ), lf_link( 84 )['local_qty'] ) );
ok( 1 === lf_stock( $LK ) && 1 === (int) wc_get_order_item_meta( $ik, '_reduced_stock', true ), 'WooCommerce stock change made before our transaction is kept' );
run( array( 'refresh_feed' => false ) );
ok( 1 === (int) lf_alloc( $ik )['local_allocated'] && 1 === (int) lf_link( 84 )['local_qty'], 'next sync booked it exactly once: local 1' );
run( array( 'refresh_feed' => false ) );
ok( 1 === (int) lf_link( 84 )['local_qty'] && 1 === lf_stock( $LK ), 'repeat sync: still local 1, Woo 1' );
