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

// 1.9.7 Suppliers' recommended retail prices: stored from both feed formats and offered as …98 SEK picks for Regular.
section( 'RRP. Recommended prices from the suppliers' );
settings( array( 'rate_override' => '11.321' ) );
ok( 2098 === URME_SS_Admin::round_98( 2139.7 ) && 2198 === URME_SS_Admin::round_98( 2158 ) && 98 === URME_SS_Admin::round_98( 10 ), 'round_98: nearest price ending in 98 (2 139.70 → 2 098, 2 158 → 2 198)' );
nf_feed( 3000, array( 'REF000140' => array( 'PURCHASE_PRICE' => '176.00', 'STOCK' => '5', 'RECOMMENDER_RETAIL_PRICE' => '189,00' ) ) );
run( array( 'force_feed' => true ) );
$rrp_row = URME_SS_DB::get_item( key_of( 140 ) );
ok( abs( (float) $rrp_row['rrp'] - 189 ) < 0.001, 'Relojitos XML: RECOMMENDER_RETAIL_PRICE 189,00 stored as rrp 189 EUR', $rrp_row['rrp'] );
$rrp_html = admin( 'catalog_row_html', key_of( 140 ) );
$rrp_sek  = URME_SS_Admin::round_98( 189 * (float) URME_SS_Rates::current()['rate'] );
ok( (bool) preg_match( '/class="urme-rrp-pick(?: is-picked)?" data-price="' . $rrp_sek . '"/', $rrp_html ), "catalog row offers the recommended price as {$rrp_sek} kr (189 EUR at today's rate, …98)" );
ok( (bool) preg_match( '#<td class="urme-match-col">.*?urme-rrp-picks.*?</td>#s', $rrp_html ) && ! preg_match( '#<td class="urme-sync-col">.*?urme-rrp-picks.*?</td>#s', $rrp_html ), '   the picks sit in the "In store" column, not in the Sync column' );
// 1.9.8 Upgrading from database v6 forgets each feed's last download once, so the next run reads it again.
update_option( URME_SS_Feed::state_option( 'relo' ), array( 'etag' => '"x"', 'last_modified' => 'Mon', 'md5' => 'abc', 'categories' => 'WATCH' ), false );
update_option( 'urme_ss_db_version', '6', true );
URME_SS_DB::maybe_upgrade();
$up_state = get_option( URME_SS_Feed::state_option( 'relo' ) );
ok( URME_SS_DB_VERSION === get_option( 'urme_ss_db_version' ) && ! isset( $up_state['etag'], $up_state['md5'] ) && ! isset( $up_state['last_modified'] ) && 'WATCH' === $up_state['categories'], 'DB 6 → 7: feed etag/md5 cleared once (categories kept), so the feeds are read again', $up_state );
update_option( URME_SS_Feed::state_option( 'relo' ), array( 'etag' => '"y"', 'md5' => 'def', 'categories' => 'WATCH' ), false );
URME_SS_DB::maybe_upgrade();
ok( '"y"' === get_option( URME_SS_Feed::state_option( 'relo' ) )['etag'], '   only once: a later load keeps the feed state' );
$x_before = array( p( $lg->get_id() )->get_regular_price(), p( $lg->get_id() )->get_sale_price() );
ok( $x_before === array( p( $lg->get_id() )->get_regular_price(), p( $lg->get_id() )->get_sale_price() ), '   showing it changes no price (a click only fills the Regular field)' );
$csv = wp_tempnam( 'ila' );
file_put_contents( $csv, "sku;gtin;post_title;tax:brand;tax:product_cat;regular_price;sale_price;stock;stock_status;images\n1710468;7613272468954;Tommy Hilfiger Adrian 1710468;Tommy Hilfiger;Brand Watches>Tommy Hilfiger>Men's;149.0000;49.8000;3;instock;https://example.com/a.jpg\n" );
$parsed = URME_SS_Feed::parse_csv( $csv, array( 'WATCH' ), 'ila' );
$item   = URME_SS_Feed::unpack( reset( $parsed['items'] ) );
ok( abs( (float) $item['rrp'] - 149 ) < 0.001 && abs( (float) $item['purchase_price'] - 49.8 ) < 0.001, 'ILA CSV: regular_price 149 is the recommended price, sale_price 49.80 the cost', $item );
unlink( $csv );
settings( array( 'rate_override' => '' ) );
