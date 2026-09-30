<?php
/**
 * Regression: enabled brand lost by a settings save, and a linked Supplier-now watch
 * (production case SKU 1513905, BOSS) never getting stock and supplier cost.
 * Included after price-hint.php; uses the helpers of the files before it.
 */

global $wpdb;

/**
 * What the browser posts for the settings form in $html (checked boxes only, like a real submit).
 */
function bs_form_post( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	$post = array();
	$xp   = new DOMXPath( $doc );
	foreach ( $xp->query( "//input[starts-with(@name,'settings[')] | //select[starts-with(@name,'settings[')]" ) as $el ) {
		if ( ! preg_match( '/^settings\[([^\]]+)\](\[\])?$/', $el->getAttribute( 'name' ), $m ) ) {
			continue;
		}
		if ( 'select' === $el->nodeName ) {
			$value = '';
			foreach ( $el->getElementsByTagName( 'option' ) as $i => $opt ) {
				if ( 0 === $i || $opt->hasAttribute( 'selected' ) ) {
					$value = $opt->getAttribute( 'value' );
				}
			}
		} else {
			if ( 'checkbox' === $el->getAttribute( 'type' ) && ! $el->hasAttribute( 'checked' ) ) {
				continue;
			}
			$value = $el->getAttribute( 'value' );
		}
		if ( ! empty( $m[2] ) ) {
			$post[ $m[1] ][] = $value;
		} else {
			$post[ $m[1] ] = $value;
		}
	}
	return $post;
}
function bs_settings_form() {
	return ff_admin_html( static function () { admin( 'render_settings' ); } );
}
function bs_brands() {
	$b = URME_SS_Settings::enabled_brand_keys();
	sort( $b );
	return $b;
}
function bs_product( $sku, $gtin, $qty, $cogs ) {
	$p = new WC_Product_Simple();
	$p->set_name( 'BOSS ' . $sku );
	$p->set_sku( $sku );
	$p->set_global_unique_id( $gtin );
	$p->set_regular_price( '4990' );
	$p->set_sale_price( '4490' );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( $qty );
	$p->set_cogs_value( (float) $cogs );
	return $p->save();
}
function bs_set( $pid, $qty = null, $cogs = null ) {
	$p = wc_get_product( $pid );
	if ( null !== $qty ) {
		wc_update_product_stock( $p, $qty, 'set' ); // A manual stock correction.
	}
	if ( null !== $cogs ) {
		$p = wc_get_product( $pid );
		$p->set_cogs_value( (float) $cogs );
		$p->save();
	}
}
function bs_state( $pid ) {
	$p = p( $pid );
	return array( 'stock' => $p->get_stock_quantity(), 'status' => $p->get_stock_status(), 'cogs' => (float) $p->get_cogs_value(), 'regular' => $p->get_regular_price(), 'sale' => $p->get_sale_price() );
}

/* ------------------------------------------------------------------ setup */
section( 'BS0. Production case: BOSS watch 176 EUR, supplier stock 8' );
wp_set_current_user( $GLOBALS['FF_ADMIN'] );
cogs( true );
settings( array( 'cost_target' => 'wc_cogs', 'rate_override' => '11.321', 'enabled_brands' => array( 'Seiko', 'Tissot', 'Guess' ), 'hint_extra_eur' => 12 ) );
$BS_SET = array(
	'REF000132' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '8', 'PURCHASE_PRICE' => '176.00' ),
	'REF000138' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '3', 'PURCHASE_PRICE' => '150.00' ),
);
nf_feed( $feed_n, $BS_SET );
run( array( 'force_feed' => true ) );
$B1 = bs_product( 'REF000132', key_of( 132 ), 2, 480 ); // Like 1513905: old stock, old COGS 480 SEK.
$B2 = bs_product( 'REF000138', key_of( 138 ), 1, 300 );
$l1 = lf_legacy_select( 132 ); // Linked as Supplier now before 1.4, like 1513905 in production.
lf_legacy_select( 138 );
ok( $l1 && (int) $l1['product_id'] === $B1 && 'sku+ean' === $l1['match_method'] && 'supplier' === $l1['stock_mode'] && 1 === (int) $l1['sync_enabled'] && 0 === (int) $l1['local_qty'], 'linked by SKU + EAN, Supplier now, sync enabled, local_qty 0', $l1 );
ok( 8 === (int) cat( 132 )['stock'] && 176.0 === (float) cat( 132 )['purchase_price'] && '1' === (string) cat( 132 )['in_feed'] && 'BOSS' === cat( 132 )['manufacturer'], 'catalog: BOSS, stock 8, 176 EUR, in feed' );
$sek = round( 176 * 11.321, 2 ); // 1,992.50 SEK: supplier cost only, never the price-hint +12 EUR.

section( 'BS-A. Enabled brand lost by a settings save (reproduction)' );
$stale = bs_form_post( bs_settings_form() ); // Settings page opened before BOSS was enabled…
URME_SS_Settings::set_brand( 'BOSS', true ); // …BOSS enabled with "Enable brand sync" in the catalog…
ok( in_array( 'BOSS', bs_brands(), true ), '   BOSS enabled', bs_brands() );
ok( 1 === lf_log_count( 'Brands enabled for sync changed (brand button): enabled BOSS' ), '   the change is logged with its source' );
$stale['hint_profit_sek'] = '550'; // …then the open settings page is saved for something unrelated.
$logged = lf_log_count( 'Brands enabled for sync changed (settings page)' );
URME_SS_Settings::save( $stale );
ok( in_array( 'BOSS', bs_brands(), true ), 'A1. saving a settings page opened before BOSS was enabled keeps BOSS', bs_brands() );
ok( 550.0 === (float) URME_SS_Settings::get( 'hint_profit_sek' ), '   the unrelated change itself is saved' );
ok( lf_log_count( 'Brands enabled for sync changed (settings page)' ) === $logged, '   no brand change logged for that save' );
settings( array( 'hint_profit_sek' => 500 ) );
URME_SS_Settings::set_brand( 'BOSS', true );

section( 'BS-A2. Enabled brands persist across settings saves' );
$before = bs_brands();
for ( $k = 0; $k < 3; $k++ ) {
	URME_SS_Settings::save( bs_form_post( bs_settings_form() ) ); // Save unchanged, three times.
}
ok( bs_brands() === $before && in_array( 'BOSS', $before, true ), 'A2. saving the settings form as is (3×) keeps every enabled brand', bs_brands() );
$post                  = bs_form_post( bs_settings_form() );
$post['rate_override'] = '11.321';
$post['hint_fee_pct']  = '5';
$post['min_feed_ratio'] = '40';
URME_SS_Settings::save( $post );
ok( bs_brands() === $before && 40 === (int) URME_SS_Settings::get( 'min_feed_ratio' ), '   unrelated settings changed, brands unchanged', bs_brands() );
settings( array( 'min_feed_ratio' => 50 ) );
$post = bs_form_post( bs_settings_form() );
$post['enabled_brands'] = array_values( array_diff( $post['enabled_brands'], array( 'Guess' ) ) );
URME_SS_Settings::save( $post );
ok( ! in_array( 'GUESS', bs_brands(), true ) && in_array( 'BOSS', bs_brands(), true ), '   unticking a brand in the form still disables it (only that brand)', bs_brands() );
$stale = bs_form_post( bs_settings_form() ); // Guess off when the page was opened…
URME_SS_Settings::set_brand( 'Guess', true ); // …enabled elsewhere meanwhile…
$stale['enabled_brands'] = array_values( array_diff( $stale['enabled_brands'], array( 'Tissot' ) ) ); // …and Tissot unticked on the stale page.
URME_SS_Settings::save( $stale );
ok( in_array( 'GUESS', bs_brands(), true ) && ! in_array( 'TISSOT', bs_brands(), true ) && in_array( 'BOSS', bs_brands(), true ), '   stale page: its own change applied (Tissot off), later change kept (Guess on)', bs_brands() );
URME_SS_Settings::set_brand( 'Tissot', true );
ok( URME_SS_Settings::save( array( 'auto_sync' => 1 ) ) && bs_brands() === $before, '   a save without the brand list (no brands_present) never touches brands', bs_brands() );
settings( array( 'auto_sync' => 1, 'cost_target' => 'wc_cogs', 'rate_override' => '11.321', 'categories' => array( 'WATCH' ), 'feed_url' => 'http://127.0.0.1:8090/feed.xml', 'manage_stock' => 1, 'min_feed_ratio' => 50, 'max_feed_age_hours' => 3 ) );

section( 'BS-B0. Disabled brand: neither stock nor cost' );
URME_SS_Settings::set_brand( 'BOSS', false );
$st = run();
$s  = sync_stats();
ok( 2 === $s['brand_off'], 'BOSS off: its 2 links counted as brand_off, not loaded', $s );
$b  = bs_state( $B1 );
ok( 2 === $b['stock'] && abs( $b['cogs'] - 480 ) < 0.001, 'disabled brand → stock 2 and COGS 480 untouched', $b );
ok( null === URME_SS_DB::get_link( key_of( 132 ) )['last_stock'], '   link not processed' );

section( 'BS-B1. Brand enabled: stock unchanged (manual 8) but cost stale → cost updates' );
URME_SS_Settings::set_brand( 'BOSS', true );
bs_set( $B1, 8 ); // Manual stock correction to the supplier value.
$writes = array();
$watch  = static function ( $check, $object_id, $meta_key ) use ( &$writes ) {
	if ( in_array( $meta_key, array( '_price', '_regular_price', '_sale_price' ), true ) ) {
		$writes[] = $meta_key;
	}
	return $check;
};
add_filter( 'update_post_metadata', $watch, 10, 3 );
add_filter( 'add_post_metadata', $watch, 10, 3 );
run();
$s = sync_stats();
$b = bs_state( $B1 );
$l = URME_SS_DB::get_link( key_of( 132 ) );
ok( 0 === $s['brand_off'] && $s['linked'] >= 2 && $s['checked'] >= 2, 'linked Supplier-now BOSS links are included (linked/checked count them)', $s );
ok( 8 === $b['stock'] && 'instock' === $b['status'], '   stock stays 8, in stock', $b );
ok( abs( $b['cogs'] - $sek ) < 0.001, 'B1. COGS 480 → ' . $sek . ' SEK although stock was already equal', $b );
ok( abs( $b['cogs'] - round( 188 * 11.321, 2 ) ) > 1, '   the price-hint +12 EUR is NOT in the COGS', $b );
ok( 8 === (int) $l['last_stock'] && abs( (float) $l['last_cost_eur'] - 176 ) < 0.0001 && abs( (float) $l['last_cost_sek'] - $sek ) < 0.001 && abs( (float) $l['last_rate'] - 11.321 ) < 0.00001 && 'ok' === $l['last_status'] && ! empty( $l['last_synced_at'] ), '   last_stock, last_cost_eur, last_cost_sek, last_rate, last_status, last_synced_at all set', $l );

section( 'BS-B2. Cost unchanged but stock stale → stock updates' );
bs_set( $B1, 0 ); // Out of stock by hand, COGS already right.
run();
$b = bs_state( $B1 );
ok( 8 === $b['stock'] && 'instock' === $b['status'] && abs( $b['cogs'] - $sek ) < 0.001, 'B2. stock 0 → 8, back in stock; COGS unchanged', $b );

section( 'BS-B3. Both stale → both update' );
bs_set( $B1, 5, 480 );
run();
$b = bs_state( $B1 );
ok( 8 === $b['stock'] && abs( $b['cogs'] - $sek ) < 0.001, 'B3. stock 5 → 8 and COGS 480 → ' . $sek, $b );

section( 'BS-B4. Nothing stale → still recorded as synced' );
$wpdb->update( URME_SS_DB::links_table(), array( 'last_synced_at' => null, 'last_stock' => null, 'last_cost_eur' => null, 'last_cost_sek' => null, 'last_rate' => null, 'last_status' => '' ), array( 'item_key' => key_of( 132 ) ) );
run();
$l = URME_SS_DB::get_link( key_of( 132 ) );
ok( 8 === (int) $l['last_stock'] && abs( (float) $l['last_cost_sek'] - $sek ) < 0.001 && 'ok' === $l['last_status'] && ! empty( $l['last_synced_at'] ), 'B4. product already matching the supplier: last_* and last_synced_at still set', $l );

section( 'BS-B5. Price hint settings never reach the synced cost' );
settings( array( 'hint_extra_eur' => 50 ) );
bs_set( $B1, null, 480 );
run();
ok( abs( bs_state( $B1 )['cogs'] - $sek ) < 0.001, 'B5. extra cost 50 EUR in the price hint: COGS still 176 × rate', bs_state( $B1 ) );
settings( array( 'hint_extra_eur' => 12 ) );

section( 'BS-B6. Selling prices never touched' );
remove_filter( 'update_post_metadata', $watch, 10 );
remove_filter( 'add_post_metadata', $watch, 10 );
$b = bs_state( $B1 );
ok( '4990' === $b['regular'] && '4490' === $b['sale'] && '4490' === p( $B1 )->get_price(), 'B6. regular 4,990 and sale 4,490 unchanged after all syncs', $b );
ok( array() === $writes, '   no write to _regular_price, _sale_price or _price', $writes );

section( 'BS-C. Hourly cron and manual "Sync now" use the same sync' );
bs_set( $B1, 1, 480 );
do_action( URME_SS_Plugin::CRON_HOOK );
URME_SS_Log::flush();
$b = bs_state( $B1 );
ok( 8 === $b['stock'] && abs( $b['cogs'] - $sek ) < 0.001 && 'cron' === sync_stats()['trigger'], 'hourly cron: stock and cost synced', $b );
bs_set( $B1, 1, 480 );
run( array( 'trigger' => 'manual' ) ); // What "Sync now" calls.
$b = bs_state( $B1 );
ok( 8 === $b['stock'] && abs( $b['cogs'] - $sek ) < 0.001 && 'manual' === sync_stats()['trigger'], 'manual Sync now: same result', $b );

section( 'BS-P1. Paused: supplier stock and cost stop, current values kept, link kept' );
$lb1 = URME_SS_DB::get_link( key_of( 132 ) );
bs_set( $B1, 6, 777 ); // The admin's own values at the moment of pausing.
admin( 'set_mode', URME_SS_DB::get_link_by_id( (int) $lb1['id'] ), 'paused' );
nf_feed( $feed_n, array_merge( $BS_SET, array( 'REF000132' => array( 'MANUFACTURER' => 'BOSS', 'STOCK' => '11', 'PURCHASE_PRICE' => '200.00' ) ) ) );
run();
do_action( URME_SS_Plugin::CRON_HOOK );
URME_SS_Log::flush();
$b = bs_state( $B1 );
$l = URME_SS_DB::get_link( key_of( 132 ) );
ok( 6 === $b['stock'] && abs( $b['cogs'] - 777 ) < 0.001, 'P1. paused: supplier stock 11 and cost 200 EUR not applied; stock 6 and COGS 777 kept as they were', $b );
ok( 2 !== $b['stock'] && abs( $b['cogs'] - 480 ) > 1, '   no old/original values restored (not 2 / 480)', $b );
ok( $l && (int) $l['product_id'] === $B1 && 0 === (int) $l['sync_enabled'] && 'supplier' === $l['stock_mode'], '   link kept (same product), sync off', $l );
ok( '4990' === $b['regular'] && '4490' === $b['sale'], '   selling prices unchanged' );
admin( 'set_mode', URME_SS_DB::get_link_by_id( (int) $lb1['id'] ), 'supplier' );
run();
$b = bs_state( $B1 );
ok( 11 === $b['stock'] && abs( $b['cogs'] - round( 200 * 11.321, 2 ) ) < 0.001, '   resumed with "Supplier now": supplier stock 11 and 200 EUR × rate synced again', $b );

section( 'BS-P2. Paused: a returned local unit does not overwrite stock or cost' );
$lb2 = URME_SS_DB::get_link( key_of( 138 ) );
URME_SS_Inventory::enable_local( (int) $lb2['id'], 1, 900 ); // One own unit, local cost 900.
$o = lf_order( $B2, 1 );                                      // Sold from local stock…
run();                                                        // …then switched to supplier in a safe sync.
ok( 'supplier' === URME_SS_DB::get_link( key_of( 138 ) )['stock_mode'] && 3 === bs_state( $B2 )['stock'], '   (setup: local unit sold, watch switched to supplier, stock 3)', bs_state( $B2 ) );
admin( 'set_mode', URME_SS_DB::get_link_by_id( (int) $lb2['id'] ), 'paused' );
bs_set( $B2, 7, 555 );                                        // Admin's own values while paused.
wc_get_order( $o->get_id() )->update_status( 'cancelled' );   // The local unit comes back; WooCommerce restocks it (+1).
$b = bs_state( $B2 );
ok( 8 === $b['stock'] && abs( $b['cogs'] - 555 ) < 0.001, 'P2. paused: only WooCommerce\'s own restock (7 → 8); the plugin does not set stock to the local count (1) or cost to 900', $b );
run();
$b = bs_state( $B2 );
ok( 8 === $b['stock'] && abs( $b['cogs'] - 555 ) < 0.001, '   still unchanged after the next sync', $b );
$_POST['confirm_drop'] = '1'; // The old override field no longer does anything.
$res = admin( 'set_mode', URME_SS_DB::get_link_by_id( (int) $lb2['id'] ), 'supplier' );
unset( $_POST['confirm_drop'] );
run();
$b = bs_state( $B2 );
$l = URME_SS_DB::get_link( key_of( 138 ) );
ok( 'error' === $res[1] && 'local_first' === $l['stock_mode'] && 1 === (int) $l['local_qty'] && 8 === $b['stock'] && abs( $b['cogs'] - 555 ) < 0.001, '   returned local unit: Supplier now refused (no override), 1 local unit kept, stock 8 / COGS 555 unchanged', array( $res, $l['stock_mode'], $l['local_qty'], $b ) );
settings( array( 'rate_override' => '' ) );
