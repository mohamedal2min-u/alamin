<?php
/**
 * 1.4.1: no ThemeComplete gift wrap (Presentinslagning) for true Dropshipping products.
 * ThemeComplete is not installed here: its two entry points are called the way it calls them
 * (the wc_epo_disable filter with the product ID, and WooCommerce's add-to-cart validation with
 * the posted tmcp_* option fields). Included after bulk-fulfillment.php; uses its products.
 */

global $wpdb;

function gw_epo_disabled( $product_id ) {
	return (bool) apply_filters( 'wc_epo_disable', false, $product_id );
}
function gw_add( $product_id, $variation_id = 0, $gift = true ) {
	unset( $_REQUEST['tmcp_checkbox_0'], $_POST['tmcp_checkbox_0'] );
	if ( $gift ) {
		$_REQUEST['tmcp_checkbox_0'] = 'Presentinslagning_0'; // What ThemeComplete's checkbox posts.
		$_POST['tmcp_checkbox_0']    = 'Presentinslagning_0';
	}
	wc_clear_notices();
	$ok = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, 1, $variation_id, array() );
	unset( $_REQUEST['tmcp_checkbox_0'], $_POST['tmcp_checkbox_0'] );
	return $ok;
}
function gw_form_snapshot( $id ) {
	$p = get_post( $id, ARRAY_A );
	unset( $p['filter'] );
	return array( $p, get_post_meta( $id ) );
}

section( 'GW0. Gift wrap setup' );
URME_SS_Product_Source::flush();
if ( ! function_exists( 'wc_clear_notices' ) ) {
	WC()->frontend_includes();
}
if ( null === WC()->session ) {
	WC()->initialize_session();
}
// Stand-in for ThemeComplete Global Form 10375 (apply to all products, Presentinslagning 45 SEK).
$form = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tm_global_cp' AND post_title = 'Presentinslagning (test)' LIMIT 1" );
if ( ! $form ) {
	$form = wp_insert_post( array( 'post_type' => 'tm_global_cp', 'post_status' => 'publish', 'post_title' => 'Presentinslagning (test)' ) );
	update_post_meta( $form, 'tm_meta', array( 'tmfbuilder' => array( 'checkboxes_options_title' => array( array( 'Presentinslagning' ) ), 'checkboxes_options_price' => array( array( '45' ) ) ) ) );
	update_post_meta( $form, 'tm_meta_apply_to_all', 'yes' );
}
$form_before = gw_form_snapshot( $form );
$http = 0;
$hf   = static function ( $pre ) use ( &$http ) {
	++$http;
	return $pre;
};
add_filter( 'pre_http_request', $hf );
ok( false !== has_filter( 'wc_epo_disable', array( 'URME_SS_Gift_Wrap', 'disable_epo' ) ) && false !== has_filter( 'woocommerce_add_to_cart_validation', array( 'URME_SS_Gift_Wrap', 'validate_add_to_cart' ) ), 'registered on every request (not admin-only): wc_epo_disable + woocommerce_add_to_cart_validation' );
ok( 'dropship' === URME_SS_Product_Source::product_state( $K0 ) && 'local' === URME_SS_Product_Source::product_state( $K3 ) && 'paused' === URME_SS_Product_Source::product_state( $KP ) && 'brand_off' === URME_SS_Product_Source::product_state( $BR[368] ) && 'lager' === URME_SS_Product_Source::product_state( $KC ), 'product_state(): the badge states (Dropshipping / Local first / Paused / brand sync off / URME Lager)' );

section( 'GW1–5. Presentinslagning per Fulfillment state' );
ok( gw_epo_disabled( $K0 ), '1. Dropshipping (REF000294) → ThemeComplete options off: Presentinslagning absent', URME_SS_Product_Source::product_state( $K0 ) );
ok( ! gw_epo_disabled( $K3 ), '2. Local first (REF000282) → unchanged: Presentinslagning visible' );
ok( ! gw_epo_disabled( $KC ), '3. URME Lager → visible' );
ok( ! gw_epo_disabled( $KP ), '4. legacy paused link (URME Lager) → visible' );
ok( ! gw_epo_disabled( $BR[368] ), '5. Supplier – brand sync off → visible' );
ok( true === apply_filters( 'wc_epo_disable', true, $K3 ), '   an earlier "disabled" from elsewhere is never switched back on' );

section( 'GW6–9. Add to cart (server side)' );
ok( false === gw_add( $K0 ) && wc_has_notice( 'Presentinslagning kan inte väljas för den här produkten.', 'error' ), '6. crafted add-to-cart: Dropshipping + Presentinslagning → rejected with a notice' );
ok( true === gw_add( $K0, 0, false ), '7. normal Dropshipping purchase without gift wrap → allowed' );
ok( true === gw_add( $K3 ) && true === gw_add( $KC ) && true === gw_add( $KP ) && true === gw_add( $BR[368] ), '8. every URME Lager case (Local first link, not linked, legacy paused, brand sync off) + Presentinslagning → allowed (the 45 SEK price is ThemeComplete\'s own; nothing here changes it)' );
$_REQUEST['tmcp_checkbox_0'] = '';
ok( true === apply_filters( 'woocommerce_add_to_cart_validation', true, $K0, 1, 0, array() ), '   an empty (unticked) option field is not a gift-wrap request' );
unset( $_REQUEST['tmcp_checkbox_0'] );
ok( false === apply_filters( 'woocommerce_add_to_cart_validation', false, $K3, 1, 0, array() ), '   an add-to-cart already refused by another check stays refused' );

section( 'GW9. Variable product: the selected variation decides' );
ok( ! gw_epo_disabled( $KV ), '   the variable parent keeps the option although one variation is Dropshipping (no hiding for a sibling)' );
ok( false === gw_add( $KV, $KVS ) && true === gw_add( $KV, $KVL ) && true === gw_add( $KV, $KVU ), '9. + gift: Dropshipping variation → rejected; Local first variation and never-linked variation → allowed' );
ok( true === gw_add( $KV, $KVS, false ), '   Dropshipping variation without gift wrap → allowed' );

section( 'GW10–11. State changes: pages cleaned, option follows' );
$cleaned = array();
$cp      = static function ( $id ) use ( &$cleaned ) {
	$cleaned[] = (int) $id;
};
add_action( 'clean_post_cache', $cp );
$lk = URME_SS_DB::get_link( key_of( 282 ) ); // Local first (3).
$o  = lf_order( $K3, (int) $lk['local_qty'] ); // Last local units sold…
run( array( 'refresh_feed' => false ) );      // …automatic Local first → Supplier.
ok( 'supplier' === URME_SS_DB::get_link( key_of( 282 ) )['stock_mode'] && gw_epo_disabled( $K3 ) && in_array( $K3, $cleaned, true ), '10. URME Lager → Dropshipping (last unit sold): Presentinslagning removed, product page cleaned from caches', array( URME_SS_Product_Source::product_state( $K3 ), $cleaned ) );
$cleaned             = array();
$_POST['local_qty']  = '2';
$_POST['local_cost'] = '650';
$r                   = admin( 'set_mode', URME_SS_DB::get_link( key_of( 282 ) ), 'local' );
unset( $_POST['local_qty'], $_POST['local_cost'] );
ok( 'success' === $r[1] && ! gw_epo_disabled( $K3 ) && in_array( $K3, $cleaned, true ), '11. Supplier → Local first: Presentinslagning available again, product page cleaned', $r );
$cleaned = array();
URME_SS_Settings::set_brand( 'BOSS', false );
$off = gw_epo_disabled( $K0 );
URME_SS_Settings::set_brand( 'BOSS', true );
ok( ! $off && gw_epo_disabled( $K0 ) && in_array( $K0, $cleaned, true ), '   brand off / on: the option follows (visible with brand sync off, absent again as Dropshipping), pages cleaned' );
$all_products = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation')" );
ok( count( array_unique( $cleaned ) ) < $all_products / 2, sprintf( '   only the affected products are cleaned (%d of %d), no site-wide purge', count( array_unique( $cleaned ) ), $all_products ) );
$cleaned = array();
$_POST = array( 'local_qty' => '2' );
admin( 'set_mode', URME_SS_DB::get_link( key_of( 294 ) ), 'local' );
$_POST = array();
ok( ! gw_epo_disabled( $K0 ) && in_array( $K0, $cleaned, true ), '   Dropshipping → URME Lager: option back, page cleaned' );
bs_set( $K0, 0 );
admin( 'run_row_action', 'dropship|' . URME_SS_DB::get_link( key_of( 294 ) )['id'] );
run( array( 'refresh_feed' => false ) );
remove_action( 'clean_post_cache', $cp );

section( 'GW12–14. Form 10375, HTTP, queries' );
ok( gw_form_snapshot( $form ) === $form_before, '12. the global form (post and meta) is unchanged' );
$src = '';
foreach ( glob( URME_SS_DIR . 'includes/*.php' ) as $f ) {
	$src .= file_get_contents( $f ); // phpcs:ignore
}
ok( false === strpos( $src, 'tm_global_cp' ) && false === strpos( $src, '10375' ), '   the plugin never reads or writes ThemeComplete forms (no tm_global_cp, no form ID)' );
remove_filter( 'pre_http_request', $hf );
ok( 0 === $http, '13. no HTTP request (no supplier feed) from the storefront checks', $http );
URME_SS_Product_Source::flush();
$q0 = $wpdb->num_queries;
gw_epo_disabled( $K0 );
$q1 = $wpdb->num_queries - $q0;
$sql = array();
$qf  = static function ( $q ) use ( &$sql ) {
	$sql[] = substr( $q, 0, 160 ) . ' <= ' . wp_debug_backtrace_summary( null, 4 );
	return $q;
};
add_filter( 'query', $qf );
$q0 = $wpdb->num_queries;
// This plugin's callbacks only (WooCommerce's own validators, e.g. the password check, load the post themselves).
gw_epo_disabled( $K0 );
URME_SS_Gift_Wrap::validate_add_to_cart( true, $K0, 1, 0, array() );
URME_SS_Gift_Wrap::is_dropship( $K0 ); // The state check a gift-wrap request runs (its refusal notice is WooCommerce's session).
$q2 = $wpdb->num_queries - $q0;
remove_filter( 'query', $qf );
URME_SS_Product_Source::flush();
$q0 = $wpdb->num_queries;
URME_SS_Gift_Wrap::validate_add_to_cart( true, $K3, 1, 0, array() );
$q3 = $wpdb->num_queries - $q0;
ok( 1 === $q1 && 0 === $q2 && 0 === $q3, sprintf( '14. one small supplier-state query per product per request (%d), then none (%d); an add to cart without gift wrap: %d queries', $q1, $q2, $q3 ), array( $q1, $q2, $q3, $sql ) );
wc_clear_notices();
