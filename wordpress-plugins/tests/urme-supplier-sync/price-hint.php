<?php
/**
 * 1.2.0: admin-only "Selling price hint". Included after new-products.php; uses the helpers of the files before it.
 */

global $wpdb;

function ph_ctx( array $over = array() ) {
	return array_merge( URME_SS_Price_Hint::context(), $over );
}
function ph_catalog( array $get ) {
	return nf_catalog_html( $get + array( 'tab' => 'catalog' ) );
}
function ph_selected( $q = '' ) {
	$m = new ReflectionMethod( 'URME_SS_Admin', 'render_selected' );
	$m->setAccessible( true );
	$_GET = array( 'tab' => 'selected', 'q' => $q );
	$html = ff_admin_html( static function () use ( $m ) { $m->invoke( null ); } );
	$_GET = array();
	return $html;
}
function ph_save( array $s ) {
	// Like the settings form: all current values posted as strings, plus the changed ones.
	$in               = URME_SS_Settings::all();
	$in['categories'] = implode( ', ', $in['categories'] );
	return URME_SS_Settings::save( array_merge( $in, $s ) );
}
function ph_text( $html ) {
	return ff_text( str_replace( '<br>', ' ', $html ) );
}
function ph_near( $a, $b, $eps = 0.0001 ) {
	return abs( $a - $b ) < $eps;
}

/* ------------------------------------------------------------------ setup */
section( 'PH0. Selling price hint setup' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
$rate_before = get_option( URME_SS_Rates::OPTION );
settings( array( 'rate_override' => '11.321' ) );
// REF000120: 176 EUR (the worked example); REF000126: PURCHASE_PRICE missing in the feed.
nf_feed( $feed_n, array( 'REF000120' => array( 'PURCHASE_PRICE' => '176.00' ), 'REF000126' => array( 'PURCHASE_PRICE' => '' ) ) );
run( array( 'force_feed' => true ) );
ok( 176.0 === (float) cat( 120 )['purchase_price'] && null === cat( 126 )['purchase_price'], 'feed: REF000120 costs 176.00 EUR, REF000126 has no PURCHASE_PRICE' );
$d = URME_SS_Settings::defaults();
ok( 12 == $d['hint_extra_eur'] && 10 == $d['hint_coupon_pct'] && 5 == $d['hint_fee_pct'] && 25 == $d['hint_vat_pct'] && 500 == $d['hint_profit_sek'] && 10 == $d['hint_round_sek'], 'defaults: +12 EUR, 10% coupon, 5% Klarna, 25% VAT, 500 SEK profit, round up to 10 SEK' );
$ctx = ph_ctx();
ok( ph_near( 11.321, $ctx['rate'] ), '   uses the plugin\'s own EUR/SEK rate (' . $ctx['rate'] . ')' );

section( 'PH worked example: 176 EUR at 11.321' );
$h = URME_SS_Price_Hint::calculate( '176.00', $ctx );
ok( 3900 === $h['price'], 'suggested price 3,900 kr', $h );
ok( ph_near( 3510, $h['paid'] ) && ph_near( 2808, $h['net'] ) && ph_near( 175.5, $h['fee'] ) && ph_near( 2128.348, $h['cost'] ) && ph_near( 504.152, $h['profit'] ), '   paid 3,510 · excl. VAT 2,808 · Klarna 175.50 · cost 2,128.35 · profit 504.15', $h );

section( 'PH1. PURCHASE_PRICE is VAT 0%' );
ok( ph_near( ( 176 + 12 ) * 11.321, $h['cost'] ), '1. cost = (PURCHASE_PRICE + 12) × rate, no VAT added', $h['cost'] );
ok( ! ph_near( ( 176 + 12 ) * 1.25 * 11.321, $h['cost'], 1 ), '   (not × 1.25)' );

section( 'PH2. +12 EUR on every supplier watch' );
$bad = 0;
foreach ( array( '20.00', '99.99', '176.00', '455.50', '899.00' ) as $pp ) {
	$c    = URME_SS_Price_Hint::calculate( $pp, $ctx )['cost'];
	$c0   = URME_SS_Price_Hint::calculate( $pp, ph_ctx( array( 'extra_eur' => 0.0 ) ) )['cost'];
	$bad += ph_near( $c - $c0, 12 * 11.321 ) && ph_near( $c, ( (float) $pp + 12 ) * 11.321 ) ? 0 : 1;
}
ok( 0 === $bad, '2. every price: cost − cost without extra = 12 EUR × rate' );

section( 'PH3–6. Coupon, VAT, Klarna fee, profit' );
$bad = array( 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0 );
mt_srand( 12 );
for ( $k = 0; $k < 500; $k++ ) {
	$pp = mt_rand( 1000, 150000 ) / 100; // 10.00 .. 1,500.00 EUR
	$r  = URME_SS_Price_Hint::calculate( $pp, $ctx );
	$bad[3] += ph_near( $r['paid'], $r['price'] * 0.90 ) ? 0 : 1;
	$bad[4] += ph_near( $r['net'], $r['paid'] / 1.25 ) ? 0 : 1;
	$bad[5] += ph_near( $r['fee'], $r['paid'] * 0.05 ) && ! ph_near( $r['fee'], $r['price'] * 0.05 ) ? 0 : 1;
	$bad[6] += $r['profit'] >= 500 - 0.000001 && ph_near( $r['profit'], $r['net'] - $r['fee'] - ( $pp + 12 ) * 11.321 ) ? 0 : 1;
	// The next lower 10 SEK step would miss the target: this is the minimum rounded price.
	$lower   = ( $r['price'] - 10 ) * 0.9 / 1.25 - ( $r['price'] - 10 ) * 0.9 * 0.05 - $r['cost'];
	$bad[7] += 0 === $r['price'] % 10 && $lower < 500 ? 0 : 1;
}
ok( 0 === $bad[3], '3. customer pays listed price × 0.90 (500 random costs)' );
ok( 0 === $bad[4], '4. revenue excl. VAT = paid / 1.25' );
ok( 0 === $bad[5], '5. Klarna fee = 5% of the amount paid after the coupon (not of the listed price)' );
ok( 0 === $bad[6], '6. estimated profit ≥ 500 SEK every time, = net − fee − cost' );

section( 'PH7. Always rounded UP to the next 10 SEK' );
ok( 0 === $bad[7], '7. multiple of 10 and the lowest such price reaching the target (500 random costs)' );
// 188 EUR at 11.00: cost 2,200, (2,200 + 500) / 0.675 = exactly 4,000 → stays 4,000 (float noise is not rounded up).
ok( 4000 === URME_SS_Price_Hint::calculate( 188, ph_ctx( array( 'rate' => 11.0 ) ) )['price'], '   exact 4,000.00 stays 4,000' );
// 188.01 EUR: minimum 4,000.16 → 4,010, never 4,000.
ok( 4010 === URME_SS_Price_Hint::calculate( 188.01, ph_ctx( array( 'rate' => 11.0 ) ) )['price'], '   4,000.16 → 4,010 (never down)' );

section( 'PH8/9. Missing values: "Price hint unavailable"' );
ok( null === URME_SS_Price_Hint::calculate( null, $ctx ) && null === URME_SS_Price_Hint::calculate( '', $ctx ) && null === URME_SS_Price_Hint::calculate( 0, $ctx ), '8. no PURCHASE_PRICE → unavailable (nothing guessed)' );
$html = ph_catalog( array( 'productno' => 'REF000126' ) );
ok( false !== strpos( ff_text( $html ), 'Price hint unavailable' ) && false === strpos( $html, 'Suggested price' ), '   catalog row without PURCHASE_PRICE shows "Price hint unavailable"' );
settings( array( 'rate_override' => '' ) );
delete_option( URME_SS_Rates::OPTION );
$http = 0;
$count_http = static function ( $pre ) use ( &$http ) {
	++$http;
	return $pre;
};
add_filter( 'pre_http_request', $count_http );
ok( null === ph_ctx()['rate'] && null === URME_SS_Price_Hint::calculate( '176.00', ph_ctx() ), '9. no EUR/SEK rate → unavailable' );
$html = ph_catalog( array( 'productno' => 'REF000120' ) );
ok( false !== strpos( ff_text( $html ), 'Price hint unavailable' ) && false === strpos( $html, 'Suggested price' ), '   catalog shows "Price hint unavailable"' );
ok( 0 === $http, '   no request made to get a rate for the hint', $http );
update_option( URME_SS_Rates::OPTION, $rate_before, false );
settings( array( 'rate_override' => '11.321' ) );

section( 'PH catalog column' );
$html = ph_catalog( array( 'productno' => 'REF000120' ) );
$t    = ph_text( $html );
ok( false !== strpos( $html, '<th class="urme-hint-col">Price hint</th>' ), 'Supplier catalog has a "Price hint" column' );
ok( false !== strpos( $t, 'Suggested price: 3,900 kr After 10% coupon: 3,510 kr Klarna 5%: 176 kr Cost incl. +12 EUR: 2,128 kr Estimated profit: 504 kr' ), '   shows suggested price, after coupon, Klarna, cost incl. +12 EUR, estimated profit', $t );
ok( 0 === $http, '   no external request while rendering', $http );

section( 'PH10. Settings changes apply immediately' );
ph_save( array( 'hint_coupon_pct' => '15', 'hint_profit_sek' => '700', 'hint_extra_eur' => '20', 'hint_fee_pct' => '3,5', 'hint_vat_pct' => '25', 'hint_round_sek' => '50' ) );
$c2 = ph_ctx();
$h2 = URME_SS_Price_Hint::calculate( '176.00', $c2 );
$m2 = 0.85 * ( 1 / 1.25 - 0.035 ); // 0.65025
$ex = (int) ( ceil( ( ( 176 + 20 ) * 11.321 + 700 ) / $m2 / 50 ) * 50 );
ok( ph_near( 0.15, $c2['coupon'] ) && ph_near( 0.035, $c2['fee'] ) && 700.0 === (float) $c2['profit'] && 20.0 === (float) $c2['extra_eur'] && 50 === $c2['step'], '10. saved settings are used (15% coupon, 3.5% fee, 700 SEK, +20 EUR, round to 50)', $c2 );
ok( $ex === $h2['price'] && 0 === $h2['price'] % 50 && $h2['profit'] >= 700, '   new suggestion ' . $h2['price'] . ' kr = recalculated with the new settings', $h2 );
$t = ph_text( ph_catalog( array( 'productno' => 'REF000120' ) ) );
ok( false !== strpos( $t, 'Suggested price: ' . number_format_i18n( $ex ) . ' kr After 15% coupon' ) && false !== strpos( $t, 'Klarna 3.5%' ) && false !== strpos( $t, 'Cost incl. +20 EUR' ), '   catalog shows the new values on the next page load', $t );
ph_save( array( 'hint_vat_pct' => 'abc', 'hint_coupon_pct' => '500' ) );
ok( 25.0 === (float) URME_SS_Settings::get( 'hint_vat_pct' ) && 90.0 === (float) URME_SS_Settings::get( 'hint_coupon_pct' ), '   invalid input keeps the old value, out-of-range is clamped' );
ph_save( array( 'hint_vat_pct' => '100', 'hint_fee_pct' => '50' ) ); // 0.9 × (1/2 − 0.5) = 0.
ok( null === URME_SS_Price_Hint::calculate( '176.00', ph_ctx() ), '   settings that leave no margin → unavailable instead of a nonsense price' );
ph_save( array( 'hint_extra_eur' => '12', 'hint_coupon_pct' => '10', 'hint_fee_pct' => '5', 'hint_vat_pct' => '25', 'hint_profit_sek' => '500', 'hint_round_sek' => '10' ) );
ok( 3900 === URME_SS_Price_Hint::calculate( '176.00', ph_ctx() )['price'], '   back to defaults: 3,900 kr again' );

section( 'PH11. Selling prices are never changed' );
$ph = lf_product( 'Price hint watch', 'REF000120', 3 ); // Regular 4,990, sale 4,490.
lf_select( 120 );
URME_SS_Settings::set_brand( cat( 120 )['manufacturer'], true );
$coupons = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_coupon'" );
$writes  = array();
$watch   = static function ( $check, $object_id, $meta_key ) use ( &$writes ) {
	if ( in_array( $meta_key, array( '_price', '_regular_price', '_sale_price' ), true ) ) {
		$writes[] = $meta_key;
	}
	return $check;
};
add_filter( 'update_post_metadata', $watch, 10, 3 );
add_filter( 'add_post_metadata', $watch, 10, 3 );
$sel = ph_selected( 'REF000120' );
ph_catalog( array() );
ph_save( array( 'hint_profit_sek' => '900' ) );
ph_catalog( array( 'productno' => 'REF000120' ) );
run();
ph_save( array( 'hint_profit_sek' => '500' ) );
remove_filter( 'update_post_metadata', $watch, 10 );
remove_filter( 'add_post_metadata', $watch, 10 );
ok( '4990' === p( $ph )->get_regular_price() && '4490' === p( $ph )->get_sale_price() && '4490' === p( $ph )->get_price(), '11. regular 4,990 and sale 4,490 unchanged after catalog, Selected watches, settings change and sync' );
ok( array() === $writes, '   no write to _regular_price, _sale_price or _price', $writes );
ok( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_coupon'" ) === $coupons, '   no coupon created or changed' );
ok( false !== strpos( ph_text( $sel ), 'Suggested price: 3,900 kr Est. profit 504 kr' ), '   Selected watches shows the hint next to the supplier cost' );

section( 'PH12. Nothing customer-facing' );
wp_set_current_user( 0 );
$GLOBALS['current_screen'] = null;
$front  = do_shortcode( '[product_page id="' . $ph . '"]' );
$front .= do_shortcode( '[products skus="REF000120"]' );
$req    = new WP_REST_Request( 'GET', '/wc/store/v1/products/' . $ph );
$front .= wp_json_encode( rest_do_request( $req )->get_data() );
ok( false !== strpos( $front, 'Price hint watch' ), 'storefront product page, shop loop and Store API rendered' );
ok( false === stripos( $front, 'Suggested price' ) && false === stripos( $front, 'urme-hint' ) && false === stripos( $front, 'Price hint unavailable' ) && false === stripos( $front, 'Estimated profit' ), '12. no price hint anywhere customer-facing' );
$hooked = 0;
foreach ( $GLOBALS['wp_filter'] as $hook ) {
	foreach ( $hook->callbacks as $cbs ) {
		foreach ( $cbs as $cb ) {
			$hooked += is_array( $cb['function'] ) && 'URME_SS_Price_Hint' === ( is_object( $cb['function'][0] ) ? get_class( $cb['function'][0] ) : $cb['function'][0] ) ? 1 : 0;
		}
	}
}
ok( 0 === $hooked, '   the hint registers no hooks (only called by the admin screens)' );
ok( ! array_filter( wp_list_pluck( wc_get_product( $ph )->get_meta_data(), 'key' ), static function ( $k ) { return false !== stripos( $k, 'hint' ); } ), '   nothing stored on the product' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );

section( 'PH13. No per-row queries in the Supplier catalog' );
$http = 0; // The sync in PH11 downloads the feed; count only from here on.
$measure = static function ( array $get ) use ( $wpdb ) {
	ph_catalog( $get ); // Warm, like any page after the first.
	$q0   = $wpdb->num_queries;
	$html = ph_catalog( $get );
	return array( $wpdb->num_queries - $q0, substr_count( $html, '<td class="urme-hint-col">' ) ); // Hint or "unavailable" (REF000126).
};
list( $q1, $n1 )   = $measure( array( 'productno' => 'REF000120' ) );
list( $q50, $n50 ) = $measure( array( 'brand' => 'Seiko' ) );
ok( 1 === $n1 && 50 === $n50 && $q1 === $q50, sprintf( '13. catalog with 1 row: %d queries, with 50 rows: %d queries (same)', $q1, $q50 ), array( $n1, $n50 ) );
$rows = URME_SS_DB::search_catalog( array( 'brand' => 'Seiko', 'per_page' => 50 ) )['rows'];
$c    = ph_ctx();
$q0   = $wpdb->num_queries;
foreach ( $rows as $row ) {
	URME_SS_Price_Hint::html( $row['purchase_price'], $c );
}
ok( 0 === $wpdb->num_queries - $q0, '   hints for 50 rows: 0 queries (settings and rate read once per page)' );
remove_filter( 'pre_http_request', $count_http );
ok( 0 === $http, '   no HTTP request for any price hint in this section', $http );
settings( array( 'rate_override' => '' ) );
