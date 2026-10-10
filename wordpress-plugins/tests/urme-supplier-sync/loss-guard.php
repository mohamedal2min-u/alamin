<?php
/**
 * 1.6.7: never sell a Dropshipping watch at a loss. Included last; uses the helpers of the files before it.
 * Cost model = the price hint: (EUR + 12) × rate, 10% coupon, 25% VAT, 5% Klarna on the paid amount.
 */

section( 'LG. Loss protection' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
settings( array( 'rate_override' => '11.321', 'loss_out_of_stock' => 1 ) );
ok( 1 === URME_SS_Settings::defaults()['loss_out_of_stock'] && 500 == URME_SS_Settings::defaults()['min_profit_sek'], 'on by default, minimum profit 500 kr (1.6.8)' );

// 176 EUR → cost (176 + 12) × 11.321 = 2128 SEK; break-even price ≈ 2128 / (0.9 × (0.8 − 0.05)) ≈ 3153 SEK.
nf_feed( 3000, array( 'REF000140' => array( 'PURCHASE_PRICE' => '176.00', 'STOCK' => '5' ) ) );
run( array( 'force_feed' => true ) );
$lg = p( mk( 'Loss guard', 'LG-1', '', true, 0 ) ); // mk() returns the product ID.
$lg->set_regular_price( '4990' );
$lg->set_sale_price( '' );
$lg->save();
URME_SS_DB::insert_link( key_of( 140 ), $lg->get_id(), 'manual' );
run( array( 'only_link_id' => (int) URME_SS_DB::get_link( key_of( 140 ) )['id'] ) );
ok( 5 === (int) p( $lg->get_id() )->get_stock_quantity(), 'profitable price 4 990 kr: supplier stock 5 synced' );

// Saving a price below cost takes the watch off sale at once.
$x = p( $lg->get_id() );
$x->set_regular_price( '2990' );
$x->save();
$x = p( $lg->get_id() );
ok( 0 === (int) $x->get_stock_quantity() && 'outofstock' === $x->get_stock_status(), 'price saved at 2 990 kr (loss): out of stock immediately' );
ok( '2990' === $x->get_regular_price(), '   the price itself is never changed' );

// The sync keeps it at 0 while the price is too low, even with supplier stock.
run( array( 'only_link_id' => (int) URME_SS_DB::get_link( key_of( 140 ) )['id'] ) );
ok( 0 === (int) p( $lg->get_id() )->get_stock_quantity(), 'sync keeps it out of stock while selling at a loss' );
ok( false !== strpos( URME_SS_DB::get_link( key_of( 140 ) )['last_message'], 'below the 500 kr minimum' ), '   link message explains the loss' );

// A sale price below cost counts too (the active price is what the customer pays).
$x = p( $lg->get_id() );
$x->set_regular_price( '4990' );
$x->save();
run( array( 'only_link_id' => (int) URME_SS_DB::get_link( key_of( 140 ) )['id'] ) );
ok( 5 === (int) p( $lg->get_id() )->get_stock_quantity(), 'repriced to 4 990 kr: stock back at the next sync' );
$x = p( $lg->get_id() );
$x->set_sale_price( '2490' );
$x->save();
ok( 0 === (int) p( $lg->get_id() )->get_stock_quantity(), 'sale price 2 490 kr (loss): out of stock' );

// 1.6.8 minimum profit 500 kr: 3 790 kr gives about 430 kr profit (above cost, below 500) → out of stock; 3 990 kr ≈ 565 kr → in stock.
$x = p( $lg->get_id() );
$x->set_sale_price( '' );
$x->set_regular_price( '3790' );
$x->save();
run( array( 'only_link_id' => (int) URME_SS_DB::get_link( key_of( 140 ) )['id'] ) );
ok( 0 === (int) p( $lg->get_id() )->get_stock_quantity(), '1.6.8: profit ≈ 430 kr (< 500 kr minimum): out of stock' );
$x = p( $lg->get_id() );
$x->set_regular_price( '3990' );
$x->save();
run( array( 'only_link_id' => (int) URME_SS_DB::get_link( key_of( 140 ) )['id'] ) );
ok( 5 === (int) p( $lg->get_id() )->get_stock_quantity(), '1.6.8: profit ≈ 565 kr (>= 500 kr): stays in stock' );

// 1.9.6 "Auto …98" sets the sale (discount) price only; the regular (recommended) price is never changed by it.
$x = p( $lg->get_id() );
$x->set_regular_price( '2990' );
$x->set_sale_price( '2490' );
$x->save();
$a98 = URME_SS_Price_Hint::price_98( 176, URME_SS_Price_Hint::context() );
$r   = admin( 'run_row_action', 'auto98|' . key_of( 140 ) );
$x   = p( $lg->get_id() );
ok( 'error' === $r[1] && '2990' === $x->get_regular_price() && '2490' === $x->get_sale_price(), "1.9.6: Auto {$a98} kr above regular 2 990 kr → refused (raise Regular first), prices unchanged", $r );
$x->set_regular_price( '9990' );
$x->save();
$r = admin( 'run_row_action', 'auto98|' . key_of( 140 ) );
$x = p( $lg->get_id() );
ok( '9990' === $x->get_regular_price() && (string) $a98 === $x->get_sale_price(), '   Auto below the regular price 9 990 kr → sale price, regular kept', $r );
$x->set_regular_price( (string) $a98 );
$x->set_sale_price( '' );
$x->save();
$r = admin( 'run_row_action', 'auto98|' . key_of( 140 ) );
$x = p( $lg->get_id() );
ok( 'error' === $r[1] && (string) $a98 === $x->get_regular_price() && '' === $x->get_sale_price(), '   Auto equal to the regular price → refused (a sale must be below Regular), prices unchanged', $r );
$html = admin( 'catalog_row_html', key_of( 140 ) );
ok( false !== strpos( $html, 'class="button button-small button-primary urme-auto98" data-price="' . $a98 . '"' ), '   Auto button carries its price for the browser (fills the Sale price field)' );

// Turned off: the supplier stock is synced whatever the price.
settings( array( 'loss_out_of_stock' => 0 ) );
run( array( 'only_link_id' => (int) URME_SS_DB::get_link( key_of( 140 ) )['id'] ) );
ok( 5 === (int) p( $lg->get_id() )->get_stock_quantity(), 'setting off: supplier stock synced at any price' );
settings( array( 'loss_out_of_stock' => 1, 'rate_override' => '' ) );
