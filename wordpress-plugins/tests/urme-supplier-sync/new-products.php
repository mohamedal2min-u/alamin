<?php
/**
 * 1.1.0: "NEW supplier product" indicator (admin catalog only). Included after
 * fulfillment-review.php; uses the helpers of the files before it.
 */

global $wpdb;

function nf_feed( $n, array $extra = array(), array $drop = array() ) {
	shell_exec( 'python3 ' . TS . '/genfeed.py ' . (int) $n . ' ' . TS . '/feedsrv/current.xml ' . escapeshellarg( wp_json_encode( array( 'set' => array_merge( $GLOBALS['LF_SET'], $extra ), 'drop' => $drop ) ) ) );
}
function nf_row( $i ) {
	return URME_SS_DB::get_item( key_of( $i ) );
}
function nf_is_new( $i ) {
	$row = nf_row( $i );
	return $row && null !== URME_SS_DB::new_age( $row );
}
function nf_set_first_seen( $i, $seconds_ago ) {
	global $wpdb;
	$wpdb->update( URME_SS_DB::catalog_table(), array( 'first_seen' => gmdate( 'Y-m-d H:i:s', time() - $seconds_ago ) ), array( 'item_key' => key_of( $i ) ) );
}
function nf_new_keys() {
	return wp_list_pluck( URME_SS_DB::search_catalog( array( 'new_only' => 1, 'per_page' => 200 ) )['rows'], 'item_key' );
}
function nf_catalog_html( array $get ) {
	$m = new ReflectionMethod( 'URME_SS_Admin', 'render_catalog' );
	$m->setAccessible( true );
	$_GET = $get;
	$html = ff_admin_html( static function () use ( $m ) { $m->invoke( null ); } );
	$_GET = array();
	return $html;
}

/* ------------------------------------------------------------------ */
section( 'NEW-0. Existing catalog is not NEW' );
$feed_n = 3000;
nf_feed( $feed_n );
run();
ok( 0 === URME_SS_DB::new_count() && array() === nf_new_keys(), 'after the first import and all earlier refreshes: 0 NEW', URME_SS_DB::new_count() );
// Simulate a store that has been running for weeks: catalog and baseline in the past.
$wpdb->query( $wpdb->prepare( 'UPDATE ' . URME_SS_DB::catalog_table() . ' SET first_seen = %s', gmdate( 'Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS ) ) );
update_option( 'urme_ss_new_since', gmdate( 'Y-m-d H:i:s', time() - 19 * DAY_IN_SECONDS ), false );
ok( 0 === URME_SS_DB::new_count(), '   (store running for weeks: still 0 NEW)' );
$links_before = URME_SS_DB::link_counts()['selected'];
$products     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product'" );

section( 'NEW-1/7. A genuinely new supplier watch is NEW' );
$feed_n = 3012; // Adds REF003000..REF003011; watches are 3000-3002 and 3006-3008.
nf_feed( $feed_n );
run();
ok( 6 === URME_SS_DB::new_count(), '1. six new watches from the feed are NEW', URME_SS_DB::new_count() );
ok( nf_is_new( 3000 ) && 0 === URME_SS_DB::new_age( nf_row( 3000 ) ), '   REF003000: NEW, "Added today"' );
ok( ! nf_row( 3003 ), '   new non-WATCH items (JEWELRY etc.) are still not stored' );
ok( abs( strtotime( nf_row( 3000 )['first_seen'] . ' UTC' ) - time() ) < 120, '7. first_seen = the moment URME first saw it', nf_row( 3000 )['first_seen'] );
ok( strtotime( nf_row( 3000 )['first_seen'] . ' UTC' ) > strtotime( get_option( 'urme_ss_new_since' ) . ' UTC' ), '   and later than the catalog baseline' );
ok( URME_SS_DB::link_counts()['selected'] === $links_before, '   not selected or linked automatically' );
ok( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product'" ) === $products, '   no WooCommerce product created' );

section( 'NEW-2/3. NEW for less than 4 full days, gone after' );
nf_set_first_seen( 3000, 4 * DAY_IN_SECONDS - HOUR_IN_SECONDS );
ok( nf_is_new( 3000 ) && 3 === URME_SS_DB::new_age( nf_row( 3000 ) ), '2. 3 days 23 h: still NEW, "Added 3 days ago"' );
nf_set_first_seen( 3001, DAY_IN_SECONDS + 60 );
ok( 1 === URME_SS_DB::new_age( nf_row( 3001 ) ), '   1 day: "Added 1 day ago"' );
nf_set_first_seen( 3001, 4 * DAY_IN_SECONDS + 60 );
ok( ! nf_is_new( 3001 ) && ! in_array( key_of( 3001 ), nf_new_keys(), true ), '3. 4 days + 1 min: no longer NEW (badge and filter)' );
nf_set_first_seen( 3001, 4 * DAY_IN_SECONDS );
ok( ! nf_is_new( 3001 ), '   exactly 4 full days: no longer NEW' );

section( 'NEW-4/5. Refreshes and stock/cost changes keep first_seen' );
$fs = nf_row( 3002 )['first_seen'];
nf_set_first_seen( 3006, 2 * DAY_IN_SECONDS );
$fs6 = nf_row( 3006 )['first_seen'];
run();
run( array( 'force_feed' => true ) ); // Also rewrites every row.
ok( nf_row( 3002 )['first_seen'] === $fs && nf_row( 3006 )['first_seen'] === $fs6, '4. normal and forced refreshes: first_seen unchanged' );
nf_feed( $feed_n, array( 'REF003002' => array( 'STOCK' => '0', 'PURCHASE_PRICE' => '444.00' ), 'REF003006' => array( 'STOCK' => '9', 'PURCHASE_PRICE' => '12.00' ) ) );
run();
ok( 444.0 === (float) nf_row( 3002 )['purchase_price'] && 0 === (int) nf_row( 3002 )['stock'], '   (stock/cost change stored)' );
ok( nf_row( 3002 )['first_seen'] === $fs && nf_row( 3006 )['first_seen'] === $fs6 && 2 === URME_SS_DB::new_age( nf_row( 3006 ) ), '5. stock/cost changes: first_seen unchanged, age unchanged' );

section( 'NEW-6. Missing from the feed and back: not NEW again' );
$fs30 = nf_row( 30 )['first_seen'];
nf_feed( $feed_n, array(), array( 'REF000030', 'REF003006' ) );
run();
ok( '0' === nf_row( 30 )['in_feed'] && '0' === nf_row( 3006 )['in_feed'], 'both flagged as missing' );
ok( ! in_array( key_of( 3006 ), nf_new_keys(), true ), '   missing items are not listed as NEW' );
nf_feed( $feed_n );
run();
ok( '1' === nf_row( 30 )['in_feed'] && nf_row( 30 )['first_seen'] === $fs30 && ! nf_is_new( 30 ), 'old watch returns: same first_seen, not NEW' );
ok( nf_row( 3006 )['first_seen'] === $fs6 && 2 === URME_SS_DB::new_age( nf_row( 3006 ) ), 'new watch returns: same first_seen, NEW clock not restarted' );

section( 'NEW-9. "New products" filter and admin badge' );
$keys = nf_new_keys();
sort( $keys );
$expect = array( key_of( 3000 ), key_of( 3002 ), key_of( 3006 ), key_of( 3007 ), key_of( 3008 ) );
sort( $expect );
ok( $keys === $expect && count( $keys ) === URME_SS_DB::new_count(), '9. filter returns exactly the 5 NEW watches; count matches', $keys );
ok( 5 === URME_SS_DB::search_catalog( array( 'new_only' => 1, 'brand' => '' ) )['total'], '   total in the filtered list = 5' );
ok( 0 === URME_SS_DB::search_catalog( array( 'new_only' => 1, 'productno' => 'REF003001' ) )['total'], '   expired one not in the filter' );
$html = nf_catalog_html( array( 'tab' => 'catalog', 'new_only' => '1' ) );
ok( 5 === substr_count( $html, 'class="urme-new"' ) && false !== strpos( ff_text( $html ), 'Added 3 days ago' ) && false !== strpos( ff_text( $html ), 'Added today' ), 'admin catalog: 5 NEW badges with "Added …" text' );
ok( false !== strpos( ff_text( $html ), 'New products (5)' ), 'admin catalog: filter shows "New products (5)"' );
URME_SS_Settings::set_brand( 'Guess', false );
ok( nf_row( 3002 )['first_seen'] === $fs && 5 === URME_SS_DB::new_count(), 'brand disabled: first_seen and NEW unchanged' );
URME_SS_Settings::set_brand( 'Guess', true );

section( 'NEW-10. Nothing on the storefront' );
$pn = lf_product( 'NEW storefront check', 'REF003007', 2 ); // Same SKU as a NEW supplier watch (not linked).
wp_set_current_user( 0 );
$GLOBALS['current_screen'] = null;
$front  = do_shortcode( '[product_page id="' . $pn . '"]' );
$front .= do_shortcode( '[products skus="REF003007"]' );
ok( false !== strpos( $front, 'NEW storefront check' ), 'storefront product page and shop loop rendered' );
ok( false === stripos( $front, 'urme-new' ) && false === stripos( $front, 'Added today' ) && false === strpos( $front, '>NEW<' ), 'no NEW badge or "Added …" text on the storefront' );
ok( ! array_filter( wp_list_pluck( wc_get_product( $pn )->get_meta_data(), 'key' ), static function ( $k ) { return false !== stripos( $k, 'urme' ); } ), 'no plugin metadata on the WooCommerce product' );
ok( '4990' === p( $pn )->get_regular_price() && 2 === lf_stock( $pn ), 'product price and stock untouched' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );

section( 'NEW-8. A first import (fresh install) marks nothing as NEW' );
$wpdb->query( 'DELETE FROM ' . URME_SS_DB::catalog_table() );
run( array( 'force_feed' => true ) );
ok( URME_SS_DB::catalog_counts()['in_feed'] > 1500 && 0 === URME_SS_DB::new_count(), 'catalog re-imported from scratch: 0 NEW', URME_SS_DB::new_count() );
sleep( 1 ); // Hourly in real life; rows written in the same second as a first import are not NEW.
nf_feed( 3018 );
run();
ok( 3 === URME_SS_DB::new_count(), 'the next genuinely new watches are NEW again (3)', URME_SS_DB::new_count() );
