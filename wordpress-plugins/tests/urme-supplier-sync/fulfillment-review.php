<?php
/**
 * Admin-only fulfilment source per order line and order (URME Lager / Dropshipping / Mixed),
 * frozen at sale time. 1.6.0: every product line is booked (Dropshipping if the watch is
 * Dropshipping when its stock is taken, else URME Lager). Included after helpers-orders.php.
 */

global $wpdb;

/* ---------------------------------------------------------------- helpers */

$GLOBALS['FF_ADMIN'] = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];

/**
 * Render a callback as an order admin would see it (wp-admin screen, admin user).
 */
function ff_admin_html( callable $cb ) {
	wp_set_current_user( $GLOBALS['FF_ADMIN'] );
	set_current_screen( 'shop_order' );
	ob_start();
	$cb();
	$html = ob_get_clean();
	$GLOBALS['current_screen'] = null;
	return $html;
}
function ff_line_html( $order ) {
	$item = lf_line( $order );
	return ff_admin_html(
		static function () use ( $item ) {
			do_action( 'woocommerce_after_order_itemmeta', $item->get_id(), $item, $item->get_product() );
		}
	);
}
function ff_order_html( $order ) {
	return ff_admin_html(
		static function () use ( $order ) {
			do_action( 'woocommerce_admin_order_data_after_order_details', wc_get_order( $order->get_id() ) );
		}
	);
}
function ff_column_html( $order ) {
	URME_SS_Fulfillment::flush_map(); // Each call stands for a fresh page load.
	return ff_admin_html(
		static function () use ( $order ) {
			do_action( 'manage_woocommerce_page_wc-orders_custom_column', 'urme_fulfillment', wc_get_order( $order->get_id() ) );
		}
	);
}
function ff_text( $html ) {
	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );
}
// Admin hooks are only registered in wp-admin; WP-CLI is not wp-admin, so register them once here.
URME_SS_Fulfillment::init();
cogs( true );
settings( array( 'cost_target' => 'wc_cogs', 'enabled_brands' => array( 'Seiko', 'Tissot', 'Guess' ), 'rate_override' => '' ) );
mode( 'ok' );

/* ------------------------------------------------------------ fulfilment */
section( 'FF1–3. Admin shows URME Lager / Dropshipping / Mixed per line and per order' );
$GLOBALS['LF_SET']['REF000090'] = array( 'STOCK' => '6', 'PURCHASE_PRICE' => '150.00' );
$GLOBALS['LF_SET']['REF000096'] = array( 'STOCK' => '6', 'PURCHASE_PRICE' => '150.00' );
lf_feed();
run( array( 'force_feed' => true ) );
$FL = lf_product( 'FF local', 'REF000090', 3, 800 );  // URME Lager: not in supplier sync.
$FD = lf_product( 'FF dropship', 'REF000096', 6, null );
lf_legacy_select( 96 ); // Dropshipping (supplier stock 6).
ok( null === lf_link( 90 ) && 'supplier' === lf_link( 96 )['stock_mode'], '   (setup: REF000090 URME Lager with 3, REF000096 Dropshipping)' );

$o_local = lf_order( $FL, 1 );
$t       = ff_text( ff_line_html( $o_local ) );
ok( false !== strpos( $t, 'URME Lager' ) && false !== strpos( $t, 'URME Lager: 1' ) && false === strpos( $t, 'Dropshipping' ), '1. sale of a URME Lager watch: line shows URME Lager: 1', $t );
ok( false !== strpos( ff_text( ff_order_html( $o_local ) ), 'URME stock only' ), '   order summary: URME stock only', ff_text( ff_order_html( $o_local ) ) );
ok( 'URME Lager' === ff_text( ff_column_html( $o_local ) ), '   orders list column: URME Lager', ff_text( ff_column_html( $o_local ) ) );

$o_drop = lf_order( $FD, 2 );
$t      = ff_text( ff_line_html( $o_drop ) );
ok( false !== strpos( $t, 'Dropshipping: 2' ) && false === strpos( $t, 'URME Lager' ), '2. sale of a Dropshipping watch: line shows Dropshipping: 2', $t );
ok( false !== strpos( ff_text( ff_order_html( $o_drop ) ), 'Dropshipping required' ) && 'Dropshipping' === ff_text( ff_column_html( $o_drop ) ), '   order summary + column: Dropshipping', ff_text( ff_order_html( $o_drop ) ) );

// Mixed order: one URME Lager line and one Dropshipping line.
$o_mixed = wc_create_order();
$o_mixed->add_product( wc_get_product( $FL ), 2 );
$o_mixed->add_product( wc_get_product( $FD ), 1 );
$o_mixed->calculate_totals();
$o_mixed->save();
$o_mixed->update_status( 'processing' );
$o_mixed = wc_get_order( $o_mixed->get_id() );
$t       = ff_text( ff_line_html( $o_mixed ) );
ok( false !== strpos( $t, 'URME Lager: 2' ) && false !== strpos( ff_text( ff_order_html( $o_mixed ) ), 'Mixed fulfillment' ) && 'Mixed' === ff_text( ff_column_html( $o_mixed ) ), '3. order with a URME Lager and a Dropshipping line: Mixed', ff_text( ff_order_html( $o_mixed ) ) );

// Orders list filter.
$ids = URME_SS_Fulfillment::order_ids( 'local' );
ok( in_array( $o_local->get_id(), $ids, true ) && ! in_array( $o_drop->get_id(), $ids, true ) && ! in_array( $o_mixed->get_id(), $ids, true ), '   filter URME Lager: only the local order' );
ok( in_array( $o_drop->get_id(), URME_SS_Fulfillment::order_ids( 'dropship' ), true ) && in_array( $o_mixed->get_id(), URME_SS_Fulfillment::order_ids( 'mixed' ), true ), '   filters Dropshipping / Mixed' );
$_GET['urme_fulfillment'] = 'mixed';
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
set_current_screen( 'shop_order' );
$args = apply_filters( 'woocommerce_order_list_table_prepare_items_query_args', array() );
$GLOBALS['current_screen'] = null;
unset( $_GET['urme_fulfillment'] );
ok( in_array( $o_mixed->get_id(), $args['post__in'] ?? array(), true ) && ! in_array( $o_local->get_id(), $args['post__in'], true ), '   HPOS list query restricted to Mixed orders', $args );

section( 'FF4. Source frozen: supplier changes later do not change old orders' );
$before = array( ff_text( ff_line_html( $o_local ) ), ff_text( ff_line_html( $o_drop ) ), ff_text( ff_line_html( $o_mixed ) ) );
$GLOBALS['LF_SET']['REF000090'] = array( 'STOCK' => '0', 'PURCHASE_PRICE' => '999.00' );
$GLOBALS['LF_SET']['REF000096'] = array( 'STOCK' => '50', 'PURCHASE_PRICE' => '10.00' );
lf_feed();
run();
run();
ok( array( ff_text( ff_line_html( $o_local ) ), ff_text( ff_line_html( $o_drop ) ), ff_text( ff_line_html( $o_mixed ) ) ) === $before, 'labels unchanged after supplier stock/price changes and syncs', $before );

section( 'FF5. Source stays correct after cancellation / refund / restock' );
$o_local->update_status( 'cancelled' );
$t = ff_text( ff_line_html( $o_local ) );
ok( false !== strpos( $t, 'URME Lager: 1' ) && false !== strpos( $t, '1 returned/restocked: 1 URME Lager, 0 Dropshipping' ), 'cancelled local sale: still URME Lager, 1 returned', $t );
ok( 'URME Lager' === ff_text( ff_column_html( $o_local ) ), '   column unchanged' );
lf_refund( $o_mixed, 2 );
$t = ff_text( ff_line_html( $o_mixed ) );
ok( false !== strpos( $t, 'URME Lager: 2' ) && false !== strpos( $t, '2 returned/restocked: 2 URME Lager, 0 Dropshipping' ) && 'Mixed' === ff_text( ff_column_html( $o_mixed ) ), 'refund with restock of the URME Lager line of a Mixed order: source kept, returns shown, order still Mixed', $t );
lf_refund( $o_drop, 1 );
ok( false !== strpos( ff_text( ff_line_html( $o_drop ) ), 'Dropshipping: 2' ) && false !== strpos( ff_text( ff_line_html( $o_drop ) ), '1 returned/restocked: 0 URME Lager, 1 Dropshipping' ), 'refund of dropship unit: still Dropshipping: 2' );

section( 'FF7. Orders without allocation data: Unknown / Legacy order, no errors' );
$o_old = wc_create_order();
$o_old->add_product( wc_get_product( $FD ), 1 );
$o_old->set_date_created( '2024-01-15 10:00:00' );
$o_old->calculate_totals();
$o_old->save();
$o_old->update_status( 'processing' );
ok( false !== strpos( ff_text( ff_line_html( $o_old ) ), 'Unknown / Legacy order' ), 'order placed before 1.1.0: line shows Unknown / Legacy order', ff_text( ff_line_html( $o_old ) ) );
ok( 'Unknown / Legacy order' === ff_text( ff_column_html( $o_old ) ), '   column: Unknown / Legacy order' );
ok( ! in_array( $o_old->get_id(), array_merge( URME_SS_Fulfillment::order_ids( 'local' ), URME_SS_Fulfillment::order_ids( 'dropship' ), URME_SS_Fulfillment::order_ids( 'mixed' ) ), true ), '   not counted in any fulfilment filter' );
$o_plain = wc_create_order();
$o_plain->add_product( wc_get_product( $P8 ), 1 ); // P8 is not linked to the supplier.
$o_plain->set_date_created( '2023-05-01 09:00:00' );
$o_plain->save();
$o_plain->update_status( 'processing' );
ok( 'Unknown / Legacy order' === ff_text( ff_column_html( $o_plain ) ), 'legacy order of a URME Lager watch: Unknown / Legacy order (never guessed)' );
$o_new_plain = lf_order( $P8, 1 );
ok( 'URME Lager' === ff_text( ff_column_html( $o_new_plain ) ), '(1.6.0) new order of a watch not in supplier sync: URME Lager (was "Not tracked")' );
$o_unpaid = lf_order( $P8, 1, 'pending' );
ok( '–' === ff_text( ff_column_html( $o_unpaid ) ) && ! lf_alloc( lf_line( $o_unpaid )->get_id() ), '   unpaid order (no stock taken yet): no label and no ledger row' );

/* ------------------------------------------------ orders list performance */
section( 'FF-P. Orders list: fulfilment data loaded in bulk, zero queries per row' );
// Enough orders of every kind: URME Lager, Dropshipping, Mixed, legacy and untracked.
// Listed newest ID first so the backdated legacy orders are mixed into every page.
$FP = lf_product( 'FF perf local', 'REF000114', 200, 500 );
$GLOBALS['LF_SET']['REF000114'] = array( 'STOCK' => '9', 'PURCHASE_PRICE' => '80.00' );
lf_feed();
run( array( 'force_feed' => true ) );
for ( $k = 0; $k < 110; $k++ ) {
	$kind = $k % 5;
	if ( 4 === $kind ) {
		$o = wc_create_order();
		$o->add_product( wc_get_product( $FD ), 1 );
		$o->set_date_created( '2024-02-0' . ( 1 + $k % 9 ) . ' 10:00:00' ); // Legacy.
		$o->calculate_totals();
		$o->save();
		$o->update_status( 'processing' );
	} elseif ( 3 === $kind ) {
		$o = wc_create_order();
		$o->add_product( wc_get_product( $FP ), 1 );
		$o->add_product( wc_get_product( $FD ), 1 ); // Mixed order.
		$o->calculate_totals();
		$o->save();
		$o->update_status( 'processing' );
	} else {
		$o = lf_order( array( $FP, $FD, $P8 )[ $kind ], 1, 2 === $kind ? 'pending' : 'processing' ); // URME Lager / Dropshipping / untracked (unpaid).
		if ( 106 === $k ) {
			// A refunded order on the first page: HPOS then runs a nested refund query inside the list query.
			wc_create_refund( array( 'order_id' => $o->get_id(), 'amount' => 1 ) );
		}
	}
}
$hpos = Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
$http = 0;
$count_http = static function ( $pre ) use ( &$http ) {
	++$http;
	return $pre;
};
add_filter( 'pre_http_request', $count_http );
foreach ( array( 20, 50, 100 ) as $n ) {
	URME_SS_Fulfillment::flush_map();
	wp_set_current_user( $GLOBALS['FF_ADMIN'] );
	set_current_screen( $hpos ? 'woocommerce_page_wc-orders' : 'edit-shop_order' );
	$q0 = $wpdb->num_queries;
	if ( $hpos ) {
		// Exactly what WooCommerce's HPOS orders list does.
		$args   = apply_filters( 'woocommerce_order_list_table_prepare_items_query_args', array( 'limit' => $n, 'page' => 1, 'paginate' => true, 'type' => 'shop_order', 'orderby' => 'id', 'order' => 'DESC' ) );
		$args   = apply_filters( 'woocommerce_shop_order_list_table_prepare_items_query_args', $args );
		$result = wc_get_orders( $args );
		$rows   = $result->orders;
	} else {
		// Legacy storage: the list is the main WP_Query on edit.php?post_type=shop_order.
		$saved                   = $GLOBALS['wp_the_query'];
		$q                       = new WP_Query();
		$GLOBALS['wp_the_query'] = $q;
		$posts                   = $q->query( array( 'post_type' => 'shop_order', 'post_status' => 'any', 'posts_per_page' => $n, 'orderby' => 'ID', 'order' => 'DESC' ) );
		$GLOBALS['wp_the_query'] = $saved;
		$rows                    = wp_list_pluck( $posts, 'ID' );
	}
	$q1 = $wpdb->num_queries;
	ob_start();
	foreach ( $rows as $row ) {
		URME_SS_Fulfillment::render_column( 'urme_fulfillment', $row );
	}
	$html = ob_get_clean();
	$q2   = $wpdb->num_queries;
	$GLOBALS['current_screen'] = null;
	ok( count( $rows ) === $n && 0 === $q2 - $q1, sprintf( '%d orders (%s list): rendering the Fulfillment column ran 0 queries', $n, $hpos ? 'HPOS' : 'legacy' ), array( count( $rows ), $q2 - $q1 ) );
	// Correct values: compare with the full per-order calculation.
	$bad = 0;
	foreach ( $rows as $row ) {
		$full = URME_SS_Fulfillment::order( $row )['status'];
		$full = in_array( $full, array( 'local', 'dropship', 'mixed', 'legacy' ), true ) ? $full : 'none';
		$bad += false === strpos( $html, 'urme-ff-' . $full ) ? 1 : 0;
	}
	$kinds = array();
	foreach ( array( 'local', 'dropship', 'mixed', 'legacy', 'none' ) as $kk ) {
		$kinds[ $kk ] = substr_count( $html, 'urme-ff-' . $kk . '"' );
	}
	ok( 0 === $bad && $kinds['local'] && $kinds['dropship'] && $kinds['mixed'] && $kinds['legacy'] && $kinds['none'], sprintf( '   values correct for all %d rows (all kinds present)', $n ), $kinds );
	$pq = $wpdb->num_queries;
	URME_SS_Fulfillment::flush_map();
	URME_SS_Fulfillment::prime( $hpos ? array_map( static function ( $o ) { return $o->get_id(); }, $rows ) : $rows );
	ok( 1 === $wpdb->num_queries - $pq, sprintf( '   bulk load for %d orders = 1 query', $n ), $wpdb->num_queries - $pq );
}
// A line removed from an order afterwards keeps its ledger row; the list must still match the order screen.
$o = wc_create_order();
$o->add_product( wc_get_product( $FP ), 1 );
$o->add_product( wc_get_product( $FD ), 1 );
$o->calculate_totals();
$o->save();
$o->update_status( 'processing' );
$o = wc_get_order( $o->get_id() );
foreach ( $o->get_items() as $item_id => $item ) {
	if ( (int) $item->get_product_id() === (int) $FP ) {
		wc_maybe_adjust_line_item_product_stock( $item, 0 ); // What WooCommerce does when an admin deletes a line.
		wc_delete_order_item( $item_id );
	}
}
$o = wc_get_order( $o->get_id() );
URME_SS_Fulfillment::flush_map();
$full = URME_SS_Fulfillment::order( $o )['status'];
ok( 'dropship' === $full && URME_SS_Fulfillment::list_status( $o->get_id(), $o->get_date_created()->getTimestamp() ) === $full, 'line removed from a Mixed order: list shows the same as the order screen (Dropshipping)', $full );
remove_filter( 'pre_http_request', $count_http );
ok( 0 === $http, 'no HTTP (feed) request while rendering the orders list', $http );

section( 'FF9. 1.6.0 ledger: frozen at sale time, booked once' );
$FS = lf_product( 'FF switch later', 'REF000102', 2, 700 ); // URME Lager with 2…
$GLOBALS['LF_SET']['REF000102'] = array( 'STOCK' => '7', 'PURCHASE_PRICE' => '210.00' );
lf_feed();
run( array( 'force_feed' => true ) );
$o_before = lf_order( $FS, 2 );                            // …both sold as URME Lager…
ok( 0 === lf_stock( $FS ) && 'success' === admin( 'run_row_action', 'start_supplier|' . key_of( 102 ) )[1], '   (setup: 2 URME Lager units sold, then Dropshipping started at stock 0)' );
$o_after = lf_order( $FS, 1 );                             // …then one sold as Dropshipping.
ok( 'URME Lager' === ff_text( ff_column_html( $o_before ) ) && 'Dropshipping' === ff_text( ff_column_html( $o_after ) ), 'the old order stays URME Lager; the new one is Dropshipping' );
$line = lf_line( $o_after );
$row0 = lf_alloc( $line->get_id() );
$line->save();
$line->save();
URME_SS_Inventory::reconcile_item( $line->get_id() );
ok( lf_alloc( $line->get_id() ) === $row0 && 1 === (int) $row0['src_supplier'] && 0 === (int) $row0['src_local'], 'repeated saves and reconciles book nothing twice', $row0 );
$o_before = wc_get_order( $o_before->get_id() );
$item     = lf_line( $o_before );
$item->set_quantity( 1 ); // Admin lowers the quantity 2 → 1.
wc_maybe_adjust_line_item_product_stock( $item, 1 );
$item->save();
$ra = lf_alloc( $item->get_id() );
ok( 1 === (int) $ra['ret_local'] && 2 === (int) $ra['src_local'] && 'URME Lager' === ff_text( ff_column_html( $o_before ) ), 'admin lowers the quantity 2 → 1: 1 URME Lager unit returned, label kept', $ra );
$q0 = $wpdb->num_queries;
$o_q = lf_order( $P8, 1 );
ok( lf_alloc( lf_line( $o_q )->get_id() ) && 'URME Lager' === ff_text( ff_column_html( $o_q ) ), sprintf( '   a checkout with one line: booked (%d queries for the whole order incl. WooCommerce)', $wpdb->num_queries - $q0 ) );

section( 'FF8. Selling price and sale price never changed' );
$all_prices = array();
foreach ( array( $FL, $FD, $FP ) as $pid ) {
	$all_prices[ $pid ] = array( p( $pid )->get_regular_price(), p( $pid )->get_sale_price() );
}
ok( ! array_filter( $all_prices, static function ( $pp ) { return array( '4990', '4490' ) !== $pp; } ), 'regular/sale prices of every test product unchanged by sales, returns and syncs', $all_prices );
ok( ! class_exists( 'URME_SS_Price_Review' ) && ! is_file( URME_SS_DIR . 'includes/class-price-review.php' ), '(1.6.0) Price Review removed (no class, no file)' );

/* --------------------------------------------------------------- privacy */
section( 'FF6. Nothing reaches customers' );
$customer = wp_insert_user(
	array(
		'user_login' => 'ffcust' . wp_rand(),
		'user_pass'  => 'x',
		'user_email' => 'ffcust' . wp_rand() . '@example.com',
		'role'       => 'customer',
	)
);
$o_c = wc_create_order( array( 'customer_id' => $customer ) );
$o_c->add_product( wc_get_product( $FD ), 1 );
$o_c->set_billing_email( 'buyer@example.com' );
$o_c->set_billing_first_name( 'Buyer' );
$o_c->calculate_totals();
$o_c->save();
$mails = array();
add_filter(
	'pre_wp_mail',
	static function ( $null, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	},
	10,
	2
);
$o_c->update_status( 'processing' );
$o_c->update_status( 'completed' );
WC()->mailer()->emails['WC_Email_Customer_Invoice']->trigger( $o_c->get_id(), wc_get_order( $o_c->get_id() ) );
lf_refund( wc_get_order( $o_c->get_id() ), 1 );
ok( in_array( $o_c->get_id(), URME_SS_Fulfillment::order_ids( 'dropship' ), true ), 'customer order has a Dropshipping source internally' );

$markers = array( 'URME Lager', 'Dropshipping', 'Mixed fulfillment', 'Fulfillment (internal)', 'urme_ss', '_urme', 'Price review', 'Review price', 'local stock', 'URME stock only' );
$leaks   = static function ( $html ) use ( $markers ) {
	$found = array();
	foreach ( $markers as $m ) {
		if ( false !== stripos( $html, $m ) ) {
			$found[] = $m;
		}
	}
	return $found;
};

// Customer emails (HTML and plain text), captured instead of sent.
$customer_mails = array_filter( $mails, static function ( $m ) { return false !== strpos( is_array( $m['to'] ) ? implode( ',', $m['to'] ) : $m['to'], 'buyer@example.com' ); } );
ok( count( $customer_mails ) >= 3, sprintf( 'captured %d customer emails (processing, completed, invoice, refund)', count( $customer_mails ) ) );
ok( ! $leaks( implode( "\n", wp_list_pluck( $customer_mails, 'message' ) ) . implode( "\n", wp_list_pluck( $customer_mails, 'subject' ) ) ), 'customer emails: no fulfilment source or price review' );
$plain = WC()->mailer()->emails['WC_Email_Customer_Completed_Order'];
$plain->object = wc_get_order( $o_c->get_id() );
ok( ! $leaks( $plain->get_content_plain() ), 'plain-text customer email: clean' );

// Front end as the customer: My Account order view, thank-you page, order details.
wp_set_current_user( $customer );
$GLOBALS['current_screen'] = null;
ok( ! is_admin(), 'rendering as a customer on the front end' );
WC()->frontend_includes();
ob_start();
wc_get_template( 'myaccount/view-order.php', array( 'order' => wc_get_order( $o_c->get_id() ), 'order_id' => $o_c->get_id() ) );
do_action( 'woocommerce_view_order', $o_c->get_id() );
wc_get_template( 'checkout/thankyou.php', array( 'order' => wc_get_order( $o_c->get_id() ) ) );
do_action( 'woocommerce_thankyou', $o_c->get_id() );
$front = ob_get_clean();
ok( false !== strpos( $front, 'FF dropship' ) && ! $leaks( $front ), 'My Account + thank-you page: product shown, no internal source', $leaks( $front ) );
ob_start();
URME_SS_Fulfillment::render_line( lf_line( $o_c )->get_id(), lf_line( $o_c ) );
URME_SS_Fulfillment::render_order( wc_get_order( $o_c->get_id() ) );
URME_SS_Fulfillment::render_column( 'urme_fulfillment', wc_get_order( $o_c->get_id() ) );
URME_SS_Fulfillment::render_filter( 'shop_order' );
$hooks = ob_get_clean();
ok( '' === $hooks, 'plugin notice/labels/column/filter print nothing outside wp-admin' );

// Order data a PDF invoice / packing slip plugin or the REST API would read.
$item = lf_line( $o_c );
ok( ! lf_private_meta( $item->get_id() ) && ! $leaks( wp_json_encode( $item->get_formatted_meta_data( '', true ) ) ), 'order line meta (used by invoices/packing slips): nothing stored' );
ok( ! $leaks( wp_json_encode( wc_get_order( $o_c->get_id() )->get_meta_data() ) ), 'order meta: nothing stored' );
ok( ! array_filter( wc_get_order_notes( array( 'order_id' => $o_c->get_id() ) ), static function ( $n ) use ( $leaks ) { return (bool) $leaks( $n->content ); } ), 'order notes: nothing added' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/wc/v3/orders/' . $o_c->get_id() ) );
ok( 200 === $rest->get_status() && ! $leaks( wp_json_encode( $rest->get_data() ) ), 'REST API order response: no internal data', $rest->get_status() );
wp_set_current_user( $customer );
$store = new WP_REST_Request( 'GET', '/wc/store/v1/order/' . $o_c->get_id() );
$store->set_query_params( array( 'key' => wc_get_order( $o_c->get_id() )->get_order_key(), 'billing_email' => 'buyer@example.com' ) );
$store = rest_do_request( $store );
ok( ! $leaks( wp_json_encode( $store->get_data() ) ), 'Store API (customer) order response: no internal data', $store->get_status() );
$sd = new WC_Structured_Data();
$sd->generate_order_data( wc_get_order( $o_c->get_id() ), false, false );
ok( ! $leaks( wp_json_encode( $sd->get_data() ) ), 'order structured data (emails): no internal data' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
