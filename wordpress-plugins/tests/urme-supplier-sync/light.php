<?php
/**
 * 1.5.7 review: what the plugin costs on store pages and admin pages. Included last from
 * scenarios.php; uses the helpers of the files before it.
 */

global $wpdb;

section( 'LT1. Store pages: no plugin query for the database version' );
delete_option( 'urme_ss_db_version' );
add_option( 'urme_ss_db_version', URME_SS_DB_VERSION, '', 'no' ); // As versions before 1.5.7 stored it.
wp_cache_delete( 'alloptions', 'options' );
ok( ! isset( wp_load_alloptions()['urme_ss_db_version'] ), '   (setup: version stored without autoload, as before 1.5.7)' );
URME_SS_DB::maybe_upgrade(); // First load after the update switches it once.
wp_cache_delete( 'alloptions', 'options' );
wp_cache_delete( 'urme_ss_db_version', 'options' );
ok( URME_SS_DB_VERSION === ( wp_load_alloptions()['urme_ss_db_version'] ?? null ), 'the version is autoloaded after the first load' );
$q0 = $wpdb->num_queries;
URME_SS_DB::maybe_upgrade();
ok( 0 === $wpdb->num_queries - $q0, 'later requests: the version check costs 0 queries', $wpdb->num_queries - $q0 );
ok( false !== strpos( (string) file_get_contents( URME_SS_DIR . 'uninstall.php' ), "'urme_ss_lager_links_removed'" ), 'uninstall removes every plugin option (incl. urme_ss_lager_links_removed)' );

section( 'LT2. Shop / category page: gift-wrap state for all products in one query' );
$saved_query = $GLOBALS['wp_query'];
$ids         = wc_get_products( array( 'limit' => 20, 'return' => 'ids', 'type' => 'simple', 'orderby' => 'ID', 'order' => 'ASC' ) );
$GLOBALS['wp_query'] = new WP_Query( array( 'post_type' => 'product', 'post__in' => $ids, 'posts_per_page' => 20, 'orderby' => 'post__in' ) );
URME_SS_Product_Source::flush();
$q0    = $wpdb->num_queries;
$first = URME_SS_Product_Source::product_state( $ids[0] );
$q1    = $wpdb->num_queries;
$same  = true;
foreach ( $ids as $id ) {
	$same = $same && in_array( URME_SS_Product_Source::product_state( $id ), array( 'dropship', 'lager', 'local', 'paused', 'brand_off' ), true );
}
$q2 = $wpdb->num_queries;
$GLOBALS['wp_query'] = $saved_query;
ok( 20 === count( $ids ) && 1 === $q1 - $q0 && 0 === $q2 - $q1 && $same, sprintf( 'first product asked: 1 query loads all %d products of the page; the other %d cost 0 queries', count( $ids ), count( $ids ) - 1 ), array( $q1 - $q0, $q2 - $q1 ) );
$ok_states = true;
URME_SS_Product_Source::flush();
foreach ( $ids as $id ) {
	$ok_states = $ok_states && URME_SS_Product_Source::product_state( $id ) === URME_SS_Product_Source::state( URME_SS_DB::link_for_product( $id ) ? array_merge( URME_SS_DB::link_for_product( $id ), array( 'manufacturer' => (string) ( URME_SS_DB::get_item( URME_SS_DB::link_for_product( $id )['item_key'] )['manufacturer'] ?? '' ) ) ) : null );
}
ok( $ok_states, '   the states are the same as when asked one by one' );

section( 'LT3. Admin pages: no Price Review (removed in 1.6.0)' );
$page = ff_admin_html( array( 'URME_SS_Admin', 'render' ) );
$notices = ff_admin_html( static function () { do_action( 'admin_notices' ); } );
ok( false === strpos( $page, 'Price Review' ) && false === stripos( $notices, 'price review' ) && false !== strpos( $page, '>Supplier catalog<' ), 'no Price Review tab, notice or menu badge' );
