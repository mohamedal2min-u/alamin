<?php
/**
 * Migration test, step 2 (run after swapping in plugin 1.1.0): the first load upgrades
 * DB v2 -> v3. Nothing that existed may be lost or change behaviour.
 */
global $wpdb;
$pass = 0;
$fail = 0;
$ok   = static function ( $cond, $label, $extra = null ) use ( &$pass, &$fail ) {
	if ( $cond ) {
		++$pass;
		echo "  PASS  $label\n";
	} else {
		++$fail;
		echo "  FAIL  $label" . ( null === $extra ? '' : '  => ' . wp_json_encode( $extra ) ) . "\n";
	}
};
$snap = json_decode( file_get_contents( getenv( 'URME_MIGRATION_SNAPSHOT' ) ), true );

echo "== Migration 1.0.0 -> " . URME_SS_VERSION . ' on WooCommerce ' . WC_VERSION . "\n";
$ok( '1.1.0' === URME_SS_VERSION, 'plugin version is 1.1.0' );
$ok( '3' === get_option( 'urme_ss_db_version' ), 'database upgraded to v3', get_option( 'urme_ss_db_version' ) );
$ok( URME_SS_DB::alloc_table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', URME_SS_DB::alloc_table() ) ), 'ledger table created' );
$ok( 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . URME_SS_DB::alloc_table() ), 'ledger starts empty' );

$catalog = $wpdb->get_results( 'SELECT * FROM ' . URME_SS_DB::catalog_table() . ' ORDER BY id', ARRAY_A );
$ok( $catalog === $snap['catalog'], sprintf( 'supplier catalog identical (%d rows, incl. match status)', count( $catalog ) ) );

$links   = $wpdb->get_results( 'SELECT * FROM ' . URME_SS_DB::links_table() . ' ORDER BY id', ARRAY_A );
$ok( count( $links ) === count( $snap['links'] ), sprintf( 'all %d links kept', count( $snap['links'] ) ) );
$same    = true;
$default = true;
foreach ( $snap['links'] as $i => $old ) {
	$new = $links[ $i ] ?? array();
	foreach ( $old as $col => $val ) {
		$same = $same && array_key_exists( $col, $new ) && $new[ $col ] === $val;
	}
	$default = $default && 'supplier' === $new['stock_mode'] && '0' === $new['local_qty'] && null === $new['local_cost'] && '0' === $new['needs_stock_apply'] && null === $new['mode_changed_at'] && '' === $new['mode_note'];
}
$ok( $same, 'every existing link column unchanged (product, match method, sync_enabled, last sync data)' );
$ok( $default, 'new columns default to Supplier now (no local stock, nothing pending)' );
$paused = array_values( array_filter( $links, static function ( $l ) { return '0' === $l['sync_enabled']; } ) );
$ok( 1 === count( $paused ) && '4900000000018' === $paused[0]['item_key'], 'paused link still paused' );

$ok( get_option( 'urme_ss_settings' ) === $snap['options']['settings'], 'settings unchanged (brands, cost field, safety values)' );
$ok( get_option( 'urme_ss_feed_state' ) === $snap['options']['feed_state'], 'feed state (ETag/hash) unchanged' );
$ok( get_option( 'urme_ss_rate' ) === $snap['options']['rate'], 'exchange rate unchanged' );
$ok( get_option( 'urme_ss_status' )['feed'] === $snap['options']['feed'], 'feed status unchanged' );

// Behaviour: the next syncs change nothing (same feed, all Supplier now).
URME_SS_Sync::run( array( 'refresh_feed' => false, 'trigger' => 'migration' ) );
URME_SS_Sync::run( array( 'trigger' => 'migration' ) );
$same = true;
foreach ( $snap['products'] as $id => $old ) {
	clean_post_cache( $id );
	$p    = wc_get_product( $id );
	$same = $same && array( $p->get_stock_quantity(), $p->get_stock_status(), $p->get_cogs_value(), $p->get_regular_price() ) === $old;
}
$ok( $same, 'products unchanged by syncs after the upgrade (no behaviour change)' );
$sync = get_option( 'urme_ss_status' )['sync'];
$ok( 0 === $sync['stock_updated'] && 0 === $sync['cost_updated'] && 0 === $sync['local_waiting'] && 0 === $sync['handovers'] && 0 === $sync['errors'], 'sync after upgrade: no updates, no local first, no errors', $sync );
$ok( ! in_array( 'local_first', $wpdb->get_col( 'SELECT stock_mode FROM ' . URME_SS_DB::links_table() ), true ), 'no link switched to Local first by itself' );
$ok( array() === URME_SS_DB::missing_columns(), 'all new columns present (links, ledger incl. frozen source, price reviews)', URME_SS_DB::missing_columns() );
$ok( 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . URME_SS_Price_Review::table() ) && 0 === URME_SS_Price_Review::pending_count(), 'no price reviews created by the upgrade or the syncs after it' );
$ok( (int) get_option( 'urme_ss_ledger_since' ) > 0, 'ledger start time recorded (older orders show Unknown / Legacy order)' );
$old_orders = wc_get_orders( array( 'limit' => 5, 'return' => 'ids' ) );
$legacy_ok  = true;
foreach ( $old_orders as $oid ) {
	$legacy_ok = $legacy_ok && in_array( URME_SS_Fulfillment::order( $oid )['status'], array( 'legacy', 'untracked' ), true );
}
$ok( $legacy_ok, sprintf( 'existing orders (%d checked) show a neutral status, nothing backdated', count( $old_orders ) ) );

echo "\nMIGRATION RESULT: $pass passed, $fail failed\n";
