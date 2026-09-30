<?php
/**
 * WooCommerce > Products Quick Edit: "Move to URME Lager" for a Dropshipping product. Runs
 * WooCommerce's own Quick Edit save (nonce, fields, $product->save()), then the plugin.
 * Included after two-state.php; uses the helpers of the files before it.
 */

global $wpdb;

if ( ! class_exists( 'WC_Admin_Post_Types' ) ) {
	include_once WC_ABSPATH . 'includes/admin/class-wc-admin-post-types.php'; // Admin-only in WooCommerce; registers its Quick Edit save.
}

/**
 * The Quick Edit form as WooCommerce fills it for $pid, plus $fields (what the admin changed).
 */
function qe_save( $pid, array $fields = array() ) {
	$p        = wc_get_product( $pid );
	$_REQUEST = array_merge(
		array(
			'woocommerce_quick_edit'       => '1',
			'woocommerce_quick_edit_nonce' => wp_create_nonce( 'woocommerce_quick_edit_nonce' ),
			'_sku'                         => $p->get_sku(),
			'_regular_price'               => $p->get_regular_price(),
			'_sale_price'                  => $p->get_sale_price(),
			'_manage_stock'                => '1',
			'_stock'                       => (string) $p->get_stock_quantity(),
			'_stock_status'                => $p->get_stock_status(),
			'_backorders'                  => $p->get_backorders(),
			'_visibility'                  => $p->get_catalog_visibility(),
			'_tax_status'                  => $p->get_tax_status(),
			'_cogs_value'                  => (string) $p->get_cogs_value(),
		),
		$fields
	);
	do_action( 'woocommerce_product_bulk_and_quick_edit', $pid, get_post( $pid ) );
	$_REQUEST = array();
	URME_SS_Product_Source::flush();
}
function qe_state( $pid ) {
	$p = wc_get_product( $pid );
	return array(
		'stock'   => $p->get_stock_quantity(),
		'status'  => $p->get_stock_status(),
		'regular' => $p->get_regular_price(),
		'sale'    => $p->get_sale_price(),
		'cogs'    => (float) $p->get_cogs_value(),
	);
}
function qe_column( $pid ) {
	URME_SS_Product_Source::flush();
	ob_start();
	do_action( 'manage_product_posts_custom_column', 'urme_source', $pid );
	return ob_get_clean();
}

section( 'QE0. Quick Edit "Move to URME Lager": setup' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
settings( array( 'cost_target' => 'wc_cogs', 'rate_override' => '11.321' ) );
URME_SS_Settings::set_brand( 'BOSS', true );
$Q1 = bs_product( 'REF000450', key_of( 450 ), 0, 300 ); // Dropshipping → URME Lager with 3.
$Q2 = bs_product( 'REF000456', key_of( 456 ), 0, 300 ); // Dropshipping → URME Lager, stock left empty.
$Q3 = bs_product( 'REF000462', key_of( 462 ), 0, 300 ); // Dropshipping, box not ticked.
$Q4 = bs_product( 'REF000468', key_of( 468 ), 2, 300 ); // URME Lager (not selected).
$Q5 = bs_product( 'REF000474', key_of( 474 ), 0, 300 ); // Supplier link of a brand with sync off.
foreach ( array( 450, 456, 462, 468 ) as $i ) {
	$TS_SET[ sprintf( 'REF%06d', $i ) ] = array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' );
}
$TS_SET['REF000474'] = array( 'MANUFACTURER' => 'Oris', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' );
nf_feed( $feed_n, array_merge( $BS_SET, $CA_SET, $PA_SET, $AX_SET, $BF_SET, $TS_SET ) );
run( array( 'force_feed' => true ) );
foreach ( array( 450, 456, 462 ) as $i ) {
	admin( 'run_row_action', 'start_supplier|' . key_of( $i ) );
}
URME_SS_Settings::set_brand( 'Oris', true );
admin( 'run_row_action', 'start_supplier|' . key_of( 474 ) );
URME_SS_Settings::set_brand( 'Oris', false );
URME_SS_Product_Source::flush();
ok( 8 === qe_state( $Q1 )['stock'] && 'dropship' === URME_SS_Product_Source::product_state( $Q1 ) && 'dropship' === URME_SS_Product_Source::product_state( $Q3 ) && 'brand_off' === URME_SS_Product_Source::product_state( $Q5 ) && null === URME_SS_DB::get_link( key_of( 468 ) ), 'setup: REF000450/456/462 Dropshipping (supplier stock 8), REF000468 URME Lager, REF000474 brand sync off' );

section( 'QE1. Markup' );
$c1 = qe_column( $Q1 );
ok( false !== strpos( $c1, 'title="Dropshipping"><span aria-hidden="true">D</span>' ) && 1 === substr_count( $c1, '<span class="hidden urme-qe-dropship"></span>' ), 'Dropshipping product: its Fulfillment cell carries the Quick Edit marker' );
ok( false === strpos( qe_column( $Q4 ), 'urme-qe-dropship' ) && false === strpos( qe_column( $Q5 ), 'urme-qe-dropship' ) && false === strpos( qe_column( $KV ), 'urme-qe-dropship' ), '   no marker for URME Lager, brand sync off or a variable product' );
ob_start();
URME_SS_Product_Source::quick_edit_box( 'urme_source', 'product' );
$box = ob_get_clean();
ob_start();
URME_SS_Product_Source::quick_edit_box( 'price', 'product' );
URME_SS_Product_Source::quick_edit_box( 'urme_source', 'post' );
$none = ob_get_clean();
ok( false !== strpos( $box, 'name="urme_ss_to_lager" value="1"' ) && false !== strpos( $box, 'Move to URME Lager' ) && false !== strpos( $box, 'display:none' ) && '' === $none, 'Quick Edit box: hidden checkbox "Move to URME Lager" (shown by the script for Dropshipping only), only once' );
set_current_screen( 'edit-product' );
URME_SS_Product_Source::quick_edit_script( 'edit.php' );
$on_list = wp_script_is( 'urme-ss-quick-edit', 'enqueued' );
wp_dequeue_script( 'urme-ss-quick-edit' );
set_current_screen( 'product' );
URME_SS_Product_Source::quick_edit_script( 'post.php' );
$on_other = wp_script_is( 'urme-ss-quick-edit', 'enqueued' );
$GLOBALS['current_screen'] = null;
ok( $on_list && ! $on_other, '   the script loads only on WooCommerce > Products' );

section( 'QE2. Ticked, stock 3 typed: URME Lager with 3' );
$b1 = qe_state( $Q1 );
qe_save( $Q1, array( 'urme_ss_to_lager' => '1', '_stock' => '3' ) );
$a1 = qe_state( $Q1 );
ok( null === URME_SS_DB::get_link( key_of( 450 ) ) && 3 === $a1['stock'] && 'instock' === $a1['status'] && 'lager' === URME_SS_Product_Source::product_state( $Q1 ), 'removed from supplier sync; stock 3, in stock; badge URME Lager', $a1 );
ok( $b1['regular'] === $a1['regular'] && $b1['sale'] === $a1['sale'] && abs( $b1['cogs'] - $a1['cogs'] ) < 0.001, '   regular/sale price and COGS unchanged', array( $b1, $a1 ) );
ok( false === strpos( qe_column( $Q1 ), 'urme-qe-dropship' ) && false !== strpos( qe_column( $Q1 ), 'title="URME Lager"><span aria-hidden="true">U</span>' ), '   the re-rendered row shows URME Lager, no Quick Edit marker' );
run();
ok( 3 === qe_state( $Q1 )['stock'] && abs( qe_state( $Q1 )['cogs'] - $b1['cogs'] ) < 0.001, '   the next sync writes nothing (supplier 8 not written)' );

section( 'QE3. Ticked, stock left empty: 0, out of stock' );
qe_save( $Q2, array( 'urme_ss_to_lager' => '1', '_stock' => '' ) );
$a2 = qe_state( $Q2 );
ok( null === URME_SS_DB::get_link( key_of( 456 ) ) && 0 === (int) $a2['stock'] && 'outofstock' === $a2['status'], 'removed from supplier sync; stock 0, out of stock (enter the real stock later)', $a2 );

section( 'QE4. Not ticked / nothing to move / no permission' );
qe_save( $Q3, array( '_stock' => '8' ) );
ok( 'supplier' === URME_SS_DB::get_link( key_of( 462 ) )['stock_mode'] && 'dropship' === URME_SS_Product_Source::product_state( $Q3 ), 'box not ticked: still Dropshipping' );
qe_save( $Q4, array( 'urme_ss_to_lager' => '1', '_stock' => '2' ) );
ok( null === URME_SS_DB::get_link( key_of( 468 ) ) && 2 === qe_state( $Q4 )['stock'], 'URME Lager product (stale form): nothing to move, stock 2' );
qe_save( $Q5, array( 'urme_ss_to_lager' => '1', '_stock' => '8' ) );
ok( URME_SS_DB::get_link( key_of( 474 ) ) && 'brand_off' === URME_SS_Product_Source::product_state( $Q5 ), 'supplier link of a brand with sync off: not Dropshipping, link kept' );
wp_set_current_user( (int) username_exists( 'ax_subscriber' ) );
$_REQUEST = array( 'urme_ss_to_lager' => '1', '_stock' => '1' );
do_action( 'woocommerce_product_quick_edit_save', wc_get_product( $Q3 ) );
$_REQUEST = array();
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
ok( 'supplier' === URME_SS_DB::get_link( key_of( 462 ) )['stock_mode'], 'without manage_woocommerce: ignored, still Dropshipping' );
settings( array( 'rate_override' => '' ) );
