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
			// Refuse a feed whose watch count drops below this share of the previous good feed.
			'min_feed_ratio'     => 50,
			// Do not push stock/cost from a catalog older than this many hours.
			'max_feed_age_hours' => 3,
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
			// Only replace the brand list when the form actually contained it.
			'enabled_brands'     => isset( $input['brands_present'] ) ? self::sanitize_brands( (array) ( $input['enabled_brands'] ?? array() ) ) : $current['enabled_brands'],
			'auto_sync'          => empty( $input['auto_sync'] ) ? 0 : 1,
			'cost_target'        => in_array( $input['cost_target'] ?? '', array( 'auto', 'wc_cogs', 'meta', 'none' ), true ) ? $input['cost_target'] : 'auto',
			'cost_meta_key'      => self::sanitize_meta_key( (string) ( $input['cost_meta_key'] ?? '' ) ),
			'rate_override'      => ( is_numeric( $override ) && (float) $override > 0 ) ? (string) (float) $override : '',
			'manage_stock'       => empty( $input['manage_stock'] ) ? 0 : 1,
			'min_feed_ratio'     => max( 0, min( 100, (int) ( $input['min_feed_ratio'] ?? 50 ) ) ),
			'max_feed_age_hours' => max( 1, min( 72, (int) ( $input['max_feed_age_hours'] ?? 3 ) ) ),
		);

		update_option( self::OPTION, $new );
		return $new;
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
		$all['enabled_brands'] = self::sanitize_brands( $list );
		update_option( self::OPTION, $all );
	}

	public static function sanitize_meta_key( $key ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $key );
	}
}
