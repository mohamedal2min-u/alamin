<?php
/**
 * 1.7.0: public product meta `urme_fulfillment` for product feeds (CTX Feed). Included after
 * loss-guard.php; uses its linked product $lg (REF000140) and the helpers of the files before it.
 */

section( 'FM. urme_fulfillment product meta' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
settings( array( 'loss_out_of_stock' => 0 ) );
$fm_meta = static function ( $id ) {
	wp_cache_delete( $id, 'post_meta' );
	return get_post_meta( $id, URME_SS_Product_Source::META, true );
};
$fm_writes = 0;
$fm_count  = static function ( $check, $object_id, $meta_key ) use ( &$fm_writes ) {
	if ( URME_SS_Product_Source::META === $meta_key ) {
		++$fm_writes;
	}
	return $check;
};
$fm_link = static function () {
	return URME_SS_DB::get_link( key_of( 140 ) );
};

ok( 'urme_fulfillment' === URME_SS_Product_Source::META, 'meta key urme_fulfillment (no leading underscore, listed by feed plugins)' );
ok( 'dropship' === $fm_meta( $lg->get_id() ), 'linked Dropshipping watch: dropship', $fm_meta( $lg->get_id() ) );

$plain = mk( 'FM plain', 'FM-1' );
URME_SS_Product_Source::rebuild_meta();
ok( 'lager' === $fm_meta( $plain->get_id() ), 'unlinked product after the backfill: lager' );

// Pause / resume and unlink / link change the meta at once.
URME_SS_DB::update_link( (int) $fm_link()['id'], array( 'sync_enabled' => 0 ) );
ok( 'paused' === $fm_meta( $lg->get_id() ), 'sync paused: paused' );
URME_SS_DB::update_link( (int) $fm_link()['id'], array( 'sync_enabled' => 1 ) );
ok( 'dropship' === $fm_meta( $lg->get_id() ), 'resumed: dropship' );
URME_SS_DB::delete_link( (int) $fm_link()['id'] );
ok( 'lager' === $fm_meta( $lg->get_id() ), 'unlinked: lager' );
URME_SS_DB::insert_link( key_of( 140 ), $lg->get_id(), 'manual' );
ok( 'dropship' === $fm_meta( $lg->get_id() ), 'linked again: dropship' );

// The sync writes a missing or wrong value, and nothing when it is already right.
delete_post_meta( $lg->get_id(), URME_SS_Product_Source::META );
run();
ok( 'dropship' === $fm_meta( $lg->get_id() ), 'sync run restores the meta of a linked product' );
add_filter( 'update_post_metadata', $fm_count, 10, 3 );
add_filter( 'add_post_metadata', $fm_count, 10, 3 );
run();
ok( 0 === $fm_writes, '   a second sync writes nothing (value unchanged)', $fm_writes );
$fm_writes = 0;
ok( 0 === URME_SS_Product_Source::rebuild_meta() && 0 === $fm_writes, '   the backfill writes nothing when every value is right', $fm_writes );
remove_filter( 'update_post_metadata', $fm_count, 10 );
remove_filter( 'add_post_metadata', $fm_count, 10 );
update_post_meta( $plain->get_id(), URME_SS_Product_Source::META, 'dropship' );
ok( 1 === URME_SS_Product_Source::rebuild_meta() && 'lager' === $fm_meta( $plain->get_id() ), '"Rebuild fulfillment labels" fixes a wrong value' );

// Prices and stock untouched by the label.
$before = array( $lg->get_regular_price(), (int) p( $lg->get_id() )->get_stock_quantity() );
URME_SS_Product_Source::rebuild_meta();
ok( array( p( $lg->get_id() )->get_regular_price(), (int) p( $lg->get_id() )->get_stock_quantity() ) === $before, 'prices and stock untouched' );
ok( false !== strpos( (string) file_get_contents( URME_SS_DIR . 'uninstall.php' ), "delete_post_meta_by_key( 'urme_fulfillment' )" ), 'uninstall removes the meta' );
ok( '1' === get_option( 'urme_ss_fulfillment_meta' ), 'one-time backfill recorded on update' );
settings( array( 'loss_out_of_stock' => 1 ) );
