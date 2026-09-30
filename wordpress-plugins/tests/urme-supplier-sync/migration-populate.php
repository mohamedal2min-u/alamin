<?php
/**
 * Migration test, step 1 (run with plugin 1.0.0 active): build a realistic 1.0.0 state and
 * snapshot it to the JSON file in URME_MIGRATION_SNAPSHOT.
 */
global $wpdb;
if ( '1.0.0' !== URME_SS_VERSION || '2' !== get_option( 'urme_ss_db_version' ) ) {
	fwrite( STDERR, 'Expected plugin 1.0.0 / DB 2, got ' . URME_SS_VERSION . ' / ' . get_option( 'urme_ss_db_version' ) . "\n" );
	exit( 1 );
}
require_once URME_SS_DIR . 'includes/class-admin.php';
update_option( 'woocommerce_feature_cost_of_goods_sold_enabled', 'yes' );
update_option(
	URME_SS_Settings::OPTION,
	array_merge(
		URME_SS_Settings::defaults(),
		array(
			'feed_url'       => 'http://127.0.0.1:8090/feed.xml',
			'enabled_brands' => array( 'Seiko', 'Tissot' ),
			'cost_target'    => 'auto',
			'min_feed_ratio' => 40,
		)
	)
);
$mk = static function ( $sku, $qty ) {
	$p = new WC_Product_Simple();
	$p->set_name( 'Migration ' . $sku );
	$p->set_sku( $sku );
	$p->set_regular_price( '1999' );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( $qty );
	return $p->save();
};
$a = $mk( 'REF000000', 1 );  // Auto-linked by SKU.
$b = $mk( 'OTHER-B', 1 );    // Linked manually.
$d = $mk( 'REF000018', 1 );  // Auto-linked, then paused.
$m = new ReflectionMethod( 'URME_SS_Admin', 'select_items' );
$m->setAccessible( true );
URME_SS_Sync::run( array( 'force_feed' => true, 'trigger' => 'migration' ) );
$m->invoke( null, array( '4900000000000', '4900000000006', '4900000000012', '4900000000018', '4900000000001' ) );
$link = URME_SS_DB::get_link( '4900000000006' );
URME_SS_DB::update_link( $link['id'], array( 'product_id' => $b, 'match_method' => 'manual' ) );
$link = URME_SS_DB::get_link( '4900000000018' );
URME_SS_DB::update_link( $link['id'], array( 'sync_enabled' => 0 ) );
// A 1.0.0-era order of a linked product (before the last 1.0.0 sync, as in real life).
$order = wc_create_order();
$order->add_product( wc_get_product( $a ), 1 );
$order->calculate_totals();
$order->save();
$order->update_status( 'processing' );
URME_SS_Sync::run( array( 'trigger' => 'migration' ) );
URME_SS_Log::flush();

$products = array();
foreach ( array( $a, $b, $d ) as $id ) {
	clean_post_cache( $id );
	$p               = wc_get_product( $id );
	$products[ $id ] = array( $p->get_stock_quantity(), $p->get_stock_status(), $p->get_cogs_value(), $p->get_regular_price() );
}
$snapshot = array(
	'catalog'  => $wpdb->get_results( 'SELECT * FROM ' . URME_SS_DB::catalog_table() . ' ORDER BY id', ARRAY_A ),
	'links'    => $wpdb->get_results( 'SELECT * FROM ' . URME_SS_DB::links_table() . ' ORDER BY id', ARRAY_A ),
	'options'  => array(
		'settings'   => get_option( 'urme_ss_settings' ),
		'feed_state' => get_option( 'urme_ss_feed_state' ),
		'rate'       => get_option( 'urme_ss_rate' ),
		'feed'       => get_option( 'urme_ss_status' )['feed'],
		'match'      => get_option( 'urme_ss_match_checked' ),
	),
	'products' => $products,
);
file_put_contents( getenv( 'URME_MIGRATION_SNAPSHOT' ), wp_json_encode( $snapshot ) );
printf( "1.0.0 state: %d catalog rows, %d links (%d paused, %d manual, %d unlinked), %d match statuses stored\n", count( $snapshot['catalog'] ), count( $snapshot['links'] ), count( array_filter( $snapshot['links'], static function ( $l ) { return '0' === $l['sync_enabled']; } ) ), count( array_filter( $snapshot['links'], static function ( $l ) { return 'manual' === $l['match_method']; } ) ), count( array_filter( $snapshot['links'], static function ( $l ) { return '0' === $l['product_id']; } ) ), count( array_filter( $snapshot['catalog'], static function ( $c ) { return '' !== $c['match_status']; } ) ) );
