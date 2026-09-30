<?php
/**
 * Shared helpers for the order and link tests (orders, refunds, ledger rows, legacy links).
 * Included from scenarios.php; uses its helpers (ok, section, feed, mode, run, p, cat, link_of,
 * key_of, settings, sync_stats, cogs). The old "Local first" engine was removed in 1.6.0.
 */

global $wpdb;
require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php'; // Line edits as the order screen and REST API make them.

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
/**
 * A selection as versions before 1.5.2 made it: stock 0 → Dropshipping link, stock above 0 →
 * a "Local first" link with that stock and cost. Such links are removed once on update (1.5.2)
 * and never created since; tests about old rows create them directly in the database.
 */
function lf_select( $i ) {
	global $wpdb;
	URME_SS_Matcher::reset();
	$item = URME_SS_DB::get_item( key_of( $i ) );
	if ( ! $item || ! URME_SS_Settings::brand_enabled( $item['manufacturer'] ) ) {
		return null;
	}
	$match = URME_SS_Store::auto_match( $item );
	if ( ! $match['product_id'] ) {
		return null;
	}
	$product = wc_get_product( $match['product_id'] );
	$units   = URME_SS_Inventory::local_units_before_supplier( null, $product );
	if ( $units > 0 && ( $product->is_type( 'variable' ) || 'no' !== $product->get_backorders() ) ) {
		return null;
	}
	$id = URME_SS_DB::insert_link( key_of( $i ), $match['product_id'], $match['method'] );
	if ( $units > 0 ) {
		$target = URME_SS_Store::cost_target();
		$cost   = $target['type'] ? URME_SS_Store::get_cost( $product, $target ) : null;
		$wpdb->update( URME_SS_DB::links_table(), array( 'stock_mode' => 'local_first', 'local_qty' => $units, 'local_cost' => $cost ), array( 'id' => (int) $id ) );
		URME_SS_Product_Source::flush();
	}
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

