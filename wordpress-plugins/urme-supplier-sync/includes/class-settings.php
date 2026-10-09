<?php
/**
 * Plugin settings (one autoloaded option).
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Settings {

	const OPTION = 'urme_ss_settings';

	/**
	 * Watches paused by the last brand enable because they have local URME stock.
	 *
	 * @var string[]
	 */
	private static $held = array();

	/**
	 * Default values for every setting.
	 */
	public static function defaults() {
		return array(
			'feed_url'           => 'https://files.channable.com/qZgX9h5JcLw9-XZjGB5Myw==.xml',
			// Supplier CATEGORY values to keep. Add e.g. ACCESSORIES later.
			'categories'         => array( 'WATCH' ),
			// Supplier MANUFACTURER values whose selected products are synced. Empty = none.
			'enabled_brands'     => array(),
			'auto_sync'          => 1,
			// Cost target: auto | wc_cogs | meta | none.
			'cost_target'        => 'auto',
			'cost_meta_key'      => '',
			'rate_override'      => '',
			'manage_stock'       => 1,
			'loss_out_of_stock'  => 1,  // Never sell a watch below the minimum profit: out of stock until repriced.
			'min_profit_sek'     => 500, // Minimum estimated profit (SEK) to stay in stock.
			'order_note'         => 1,   // Private order note with the fulfillment source (mobile app).
			// Refuse a feed whose watch count drops below this share of the previous good feed.
			'min_feed_ratio'     => 50,
			// Do not push stock/cost from a catalog older than this many hours.
			'max_feed_age_hours' => 3,
			// Selling price hint (admin only, never written to products).
			'hint_extra_eur'     => 12,  // Fixed supplier cost per watch, EUR, on top of PURCHASE_PRICE.
			'hint_coupon_pct'    => 10,  // Discount a customer may use.
			'hint_fee_pct'       => 5,   // Klarna/payment fee, % of what the customer pays.
			'hint_vat_pct'       => 25,  // Swedish VAT included in the listed price.
			'hint_profit_pct'    => 20,  // Target profit per watch, % of its cost (cost incl. the extra supplier cost).
			'hint_round_sek'     => 10,  // Round the suggested price up to this step.
			// Second supplier (ILA Uhren, Germany). Inactive while the feed URL is empty.
			'ila_feed_url'       => '',
			'ila_extra_eur'      => 17.90, // Shipping + fees per order, EUR (like hint_extra_eur for Relojitos).
			// Delivery days to the customer per supplier (WoodMart "Leverans" on Dropshipping watches).
			'relo_days'          => '3-6',
			'ila_days'           => '3-6',
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitize and store posted settings.
	 *
	 * @param array $input Raw input.
	 */
	public static function save( array $input ) {
		$current = self::all();

		$categories = array_filter( array_map( 'trim', explode( ',', strtoupper( (string) ( $input['categories'] ?? '' ) ) ) ) );
		$override   = str_replace( ',', '.', trim( (string) ( $input['rate_override'] ?? '' ) ) );

		$new = array(
			'feed_url'           => esc_url_raw( trim( (string) ( $input['feed_url'] ?? $current['feed_url'] ) ) ),
			'categories'         => $categories ? array_values( array_unique( $categories ) ) : array( 'WATCH' ),
			// Only change the brand list when the form actually contained it, and then only by what was changed on that page.
			'enabled_brands'     => isset( $input['brands_present'] ) ? self::brands_from_form( $input, (array) $current['enabled_brands'] ) : $current['enabled_brands'],
			'auto_sync'          => empty( $input['auto_sync'] ) ? 0 : 1,
			'cost_target'        => in_array( $input['cost_target'] ?? '', array( 'auto', 'wc_cogs', 'meta', 'none' ), true ) ? $input['cost_target'] : 'auto',
			'cost_meta_key'      => self::sanitize_meta_key( (string) ( $input['cost_meta_key'] ?? '' ) ),
			'rate_override'      => ( is_numeric( $override ) && (float) $override > 0 ) ? (string) (float) $override : '',
			'manage_stock'       => empty( $input['manage_stock'] ) ? 0 : 1,
			'loss_out_of_stock'  => empty( $input['loss_out_of_stock'] ) ? 0 : 1,
			'min_profit_sek'     => (int) self::number( $input, $current, 'min_profit_sek', 0, 100000 ),
			'order_note'         => empty( $input['order_note'] ) ? 0 : 1,
			'min_feed_ratio'     => max( 0, min( 100, (int) ( $input['min_feed_ratio'] ?? 50 ) ) ),
			'max_feed_age_hours' => max( 1, min( 72, (int) ( $input['max_feed_age_hours'] ?? 3 ) ) ),
			'hint_extra_eur'     => self::number( $input, $current, 'hint_extra_eur', 0, 1000 ),
			'hint_coupon_pct'    => self::number( $input, $current, 'hint_coupon_pct', 0, 90 ),
			'hint_fee_pct'       => self::number( $input, $current, 'hint_fee_pct', 0, 50 ),
			'hint_vat_pct'       => self::number( $input, $current, 'hint_vat_pct', 0, 100 ),
			'hint_profit_pct'    => self::number( $input, $current, 'hint_profit_pct', 0, 1000 ),
			'hint_round_sek'     => (int) self::number( $input, $current, 'hint_round_sek', 1, 1000 ),
			'ila_feed_url'       => esc_url_raw( trim( (string) ( $input['ila_feed_url'] ?? $current['ila_feed_url'] ) ) ),
			'ila_extra_eur'      => self::number( $input, $current, 'ila_extra_eur', 0, 1000 ),
			'relo_days'          => self::days( $input, $current, 'relo_days' ),
			'ila_days'           => self::days( $input, $current, 'ila_days' ),
		);

		self::guard_enabled_brands( (array) $current['enabled_brands'], $new['enabled_brands'] );
		update_option( self::OPTION, $new );
		self::log_brand_change( (array) $current['enabled_brands'], $new['enabled_brands'], 'settings page' );
		self::brand_pages_changed( (array) $current['enabled_brands'], $new['enabled_brands'] );
		return $new;
	}

	/**
	 * Local URME stock has priority also when a brand is turned on again: its Supplier-now links
	 * are checked (and paused where local stock is found) before the brand is saved, so no sync
	 * can write supplier stock or cost to them in between.
	 */
	private static function guard_enabled_brands( array $old, array $new ) {
		$added      = array_values( array_diff( array_map( array( __CLASS__, 'brand_key' ), $new ), array_map( array( __CLASS__, 'brand_key' ), $old ) ) );
		self::$held = $added ? URME_SS_Inventory::hold_on_brand_enable( $added ) : array();
	}

	/**
	 * Watches paused by the last brand enable (product number and local units), for the notice.
	 *
	 * @return string[]
	 */
	public static function held_on_enable() {
		return self::$held;
	}

	/**
	 * Enabled brands after a settings-page save. The page posts the brands that were ticked when it
	 * was opened (brands_before); only the difference is applied, so a brand enabled or disabled
	 * elsewhere after the page was opened (brand button, another tab) is not overwritten.
	 */
	private static function brands_from_form( array $input, array $current ) {
		$posted = self::sanitize_brands( (array) ( $input['enabled_brands'] ?? array() ) );
		if ( ! isset( $input['brands_before'] ) ) {
			return $posted; // A form without the snapshot: the list as posted.
		}
		$before = json_decode( (string) $input['brands_before'], true );
		$before = array_flip( array_map( array( __CLASS__, 'brand_key' ), is_array( $before ) ? $before : array() ) );
		$ticked = array();
		foreach ( $posted as $b ) {
			$ticked[ self::brand_key( $b ) ] = $b;
		}
		$list = array();
		foreach ( $current as $b ) {
			$list[ self::brand_key( $b ) ] = $b;
		}
		foreach ( $ticked as $key => $b ) {
			if ( ! isset( $before[ $key ] ) ) {
				$list[ $key ] = $b; // Ticked on this page.
			}
		}
		foreach ( array_keys( $before ) as $key ) {
			if ( ! isset( $ticked[ $key ] ) ) {
				unset( $list[ $key ] ); // Unticked on this page.
			}
		}
		return self::sanitize_brands( $list );
	}

	private static function log_brand_change( array $old, array $new, $source ) {
		$old_keys = array_map( array( __CLASS__, 'brand_key' ), $old );
		$new_keys = array_map( array( __CLASS__, 'brand_key' ), $new );
		$added    = array_diff( $new_keys, $old_keys );
		$removed  = array_diff( $old_keys, $new_keys );
		if ( $added || $removed ) {
			URME_SS_Log::info( sprintf( 'Brands enabled for sync changed (%s): %s%s. Now: %s.', $source, $added ? 'enabled ' . implode( ', ', $added ) : '', $removed ? ( $added ? '; ' : '' ) . 'disabled ' . implode( ', ', $removed ) : '', $new_keys ? implode( ', ', $new_keys ) : 'none' ) );
		}
	}

	/**
	 * A number from the form (comma or dot decimals), clamped; the current value when absent or invalid.
	 */
	private static function number( array $input, array $current, $key, $min, $max ) {
		$raw = str_replace( array( ',', ' ' ), array( '.', '' ), trim( (string) ( $input[ $key ] ?? '' ) ) );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return $current[ $key ];
		}
		return max( $min, min( $max, round( (float) $raw, 2 ) ) );
	}

	/**
	 * Delivery days as "min-max" (or one number); the current value when absent or unreadable.
	 */
	private static function days( array $input, array $current, $key ) {
		$raw = trim( (string) ( $input[ $key ] ?? '' ) );
		if ( ! preg_match( '/^\d{1,2}(\s*[-–—]\s*\d{1,2})?$/u', $raw ) ) {
			return $current[ $key ];
		}
		list( $min, $max ) = URME_SS_Suppliers::parse_days( $raw );
		return $min === $max ? (string) $min : $min . '-' . $max;
	}

	public static function sanitize_brands( array $brands ) {
		$out = array();
		foreach ( $brands as $brand ) {
			$brand = trim( sanitize_text_field( (string) $brand ) );
			if ( '' !== $brand ) {
				$out[ self::brand_key( $brand ) ] = $brand;
			}
		}
		ksort( $out );
		return array_values( $out );
	}

	/**
	 * Brands are compared case-insensitively ("SEIKO" == "Seiko").
	 */
	public static function brand_key( $brand ) {
		return mb_strtoupper( trim( (string) $brand ) );
	}

	/**
	 * Upper-cased enabled brands.
	 *
	 * @return string[]
	 */
	public static function enabled_brand_keys() {
		return array_map( array( __CLASS__, 'brand_key' ), (array) self::get( 'enabled_brands' ) );
	}

	public static function brand_enabled( $brand ) {
		return in_array( self::brand_key( $brand ), self::enabled_brand_keys(), true );
	}

	/**
	 * Enable or disable one brand. Links are never touched.
	 */
	public static function set_brand( $brand, $enabled ) {
		$all  = self::all();
		$list = array();
		foreach ( (array) $all['enabled_brands'] as $b ) {
			$list[ self::brand_key( $b ) ] = $b;
		}
		if ( $enabled ) {
			$list[ self::brand_key( $brand ) ] = trim( $brand );
		} else {
			unset( $list[ self::brand_key( $brand ) ] );
		}
		$old                   = (array) $all['enabled_brands'];
		$all['enabled_brands'] = self::sanitize_brands( $list );
		self::guard_enabled_brands( $old, $all['enabled_brands'] );
		update_option( self::OPTION, $all );
		self::log_brand_change( $old, $all['enabled_brands'], 'brand button' );
		self::brand_pages_changed( $old, $all['enabled_brands'] );
	}

	/**
	 * After a brand is turned on or off, its Supplier-now products switch between Dropshipping and
	 * "brand sync off": their product pages are cleaned from caches.
	 */
	private static function brand_pages_changed( array $old, array $new ) {
		$old_keys = array_map( array( __CLASS__, 'brand_key' ), $old );
		$new_keys = array_map( array( __CLASS__, 'brand_key' ), $new );
		$changed  = array_merge( array_diff( $new_keys, $old_keys ), array_diff( $old_keys, $new_keys ) );
		if ( $changed ) {
			URME_SS_Product_Source::brands_changed( array_values( $changed ) );
		}
	}

	public static function sanitize_meta_key( $key ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $key );
	}
}
