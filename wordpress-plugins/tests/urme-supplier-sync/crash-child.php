<?php
/**
 * Child process for the hard-crash test (LF-K): dies with exit() in the middle of the
 * ledger transaction, after both rows were written but before COMMIT. Nothing of our
 * own code runs a rollback; the database must discard the half-done booking itself.
 */
$product_id = (int) getenv( 'URME_CRASH_PRODUCT' );
add_action(
	'urme_ss_inventory_checkpoint',
	static function ( $stage ) {
		if ( 'before_commit' === $stage ) {
			fwrite( STDOUT, "child: dying before COMMIT\n" );
			exit( 3 );
		}
	}
);
$o = wc_create_order();
$o->add_product( wc_get_product( $product_id ), 1 );
$o->calculate_totals();
$o->save();
update_option( 'urme_crash_order', $o->get_id(), false );
$o->update_status( 'processing' );
fwrite( STDOUT, "child: finished without crashing (unexpected)\n" );
