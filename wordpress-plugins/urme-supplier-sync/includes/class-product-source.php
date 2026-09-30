<?php
/**
 * Fulfillment / stock source of a WooCommerce product (admin only), from the Supplier Sync link:
 * Dropshipping (Supplier now), Local first (N), Paused, or URME Lager (not supplier-linked).
 * Never guessed from the stock quantity. Links are loaded in bulk for the visible products.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Product_Source {

	const DROPSHIP  = 'dropship';
	const LOCAL     = 'local';
	const PAUSED    = 'paused';
	const LAGER     = 'lager';
	const BRAND_OFF = 'brand_off';

	/**
	 * Product ID => array( 'own' => link row|null, 'variations' => link rows of its variations ).
	 *
	 * @var array<int, array>
	 */
	private static $map = array();

	private static $styled = false;

	public static function init() {
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'the_posts', array( __CLASS__, 'prime_list' ), 10, 2 );
	}

	/**
	 * Load the Supplier Sync links of many products (and of their variations) with one query.
	 *
	 * @param int[] $ids Product or variation IDs.
	 */
	public static function prime( array $ids ) {
		global $wpdb;
		$ids = array_values( array_diff( array_unique( array_filter( array_map( 'intval', $ids ) ) ), array_keys( self::$map ) ) );
		if ( ! $ids ) {
			return;
		}
		foreach ( $ids as $id ) {
			self::$map[ $id ] = array(
				'own'        => null,
				'variations' => array(),
			);
		}
		$in   = implode( ',', $ids );
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			'SELECT l.product_id, p.post_parent, l.stock_mode, l.sync_enabled, l.local_qty, c.manufacturer, c.in_feed
			FROM ' . URME_SS_DB::links_table() . " l
			LEFT JOIN {$wpdb->posts} p ON p.ID = l.product_id
			LEFT JOIN " . URME_SS_DB::catalog_table() . " c ON c.item_key = l.item_key
			WHERE l.product_id > 0 AND (l.product_id IN ({$in}) OR p.post_parent IN ({$in}))",
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			$pid    = (int) $r['product_id'];
			$parent = (int) $r['post_parent'];
			if ( isset( self::$map[ $pid ] ) ) {
				self::$map[ $pid ]['own'] = $r;
			}
			if ( $parent && isset( self::$map[ $parent ] ) && $parent !== $pid ) {
				self::$map[ $parent ]['variations'][] = $r;
			}
		}
	}

	/**
	 * Whether this exact product/variation is linked to a supplier item (from the same bulk load).
	 */
	public static function is_linked( $product_id ) {
		$product_id = (int) $product_id;
		if ( ! $product_id ) {
			return false;
		}
		if ( ! isset( self::$map[ $product_id ] ) ) {
			self::prime( array( $product_id ) );
		}
		return null !== self::$map[ $product_id ]['own'];
	}

	public static function flush() {
		self::$map = array();
	}

	/**
	 * State of one link row (null = not supplier-linked).
	 */
	public static function state( $link ) {
		if ( ! $link ) {
			return self::LAGER;
		}
		if ( ! (int) $link['sync_enabled'] ) {
			return self::PAUSED;
		}
		if ( URME_SS_Inventory::LOCAL === $link['stock_mode'] ) {
			return self::LOCAL;
		}
		return URME_SS_Settings::brand_enabled( (string) $link['manufacturer'] ) ? self::DROPSHIP : self::BRAND_OFF;
	}

	/**
	 * Badge HTML for a product or variation.
	 */
	public static function html( $product_id ) {
		$product_id = (int) $product_id;
		if ( ! isset( self::$map[ $product_id ] ) ) {
			self::prime( array( $product_id ) ); // Not on a primed page: one query for this product.
		}
		$entry = self::$map[ $product_id ];
		if ( $entry['own'] || ! $entry['variations'] ) {
			return self::badge( $entry['own'] ) . self::styles();
		}
		// Variable product: the sources of its supplier-linked variations.
		$groups = array();
		foreach ( $entry['variations'] as $link ) {
			$html            = self::badge( $link );
			$groups[ $html ] = ( $groups[ $html ] ?? 0 ) + 1;
		}
		$out = array();
		foreach ( $groups as $html => $n ) {
			$out[] = $html . ' <small class="urme-src-note">' . esc_html( sprintf( '%d variation%s', $n, 1 === $n ? '' : 's' ) ) . '</small>';
		}
		return implode( '<br>', $out ) . self::styles();
	}

	private static function badge( $link ) {
		$state = self::state( $link );
		switch ( $state ) {
			case self::DROPSHIP:
				$label = 'Dropshipping';
				break;
			case self::LOCAL:
				$label = sprintf( 'Local first (%d)', (int) $link['local_qty'] );
				break;
			case self::PAUSED:
				$label = 'Paused';
				break;
			case self::BRAND_OFF:
				$label = 'Supplier – brand sync off';
				break;
			default:
				$label = 'URME Lager';
		}
		$out = '<span class="urme-src urme-src-' . esc_attr( $state ) . '">' . esc_html( $label ) . '</span>';
		if ( $link && self::LOCAL === $state && ! URME_SS_Settings::brand_enabled( (string) $link['manufacturer'] ) ) {
			$out .= ' <small class="urme-src-note">brand sync off</small>';
		}
		if ( $link && self::PAUSED !== $state && isset( $link['in_feed'] ) && ! (int) $link['in_feed'] ) {
			$out .= ' <small class="urme-src-note">not in supplier feed</small>';
		}
		return $out;
	}

	private static function styles() {
		if ( self::$styled ) {
			return '';
		}
		self::$styled = true;
		return '<style>.wp-list-table .column-urme_source{width:9em}.urme-src{display:inline-block;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:600;line-height:18px;white-space:nowrap}'
			. '.urme-src-dropship{background:#e5f0fa;color:#135e96}.urme-src-local{background:#edfaef;color:#00701a}'
			. '.urme-src-paused{background:#f0f0f1;color:#50575e}.urme-src-lager{background:#fcf0e3;color:#8a4b00}'
			. '.urme-src-brand_off{background:#f0f0f1;color:#8c1f1f}.urme-src-note{color:#646970}</style>';
	}

	/* --- WooCommerce > Products ------------------------------------------ */

	private static function allowed() {
		return current_user_can( 'manage_woocommerce' );
	}

	public static function add_column( $columns ) {
		if ( ! self::allowed() ) {
			return $columns;
		}
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'is_in_stock' === $key ) {
				$out['urme_source'] = 'Fulfillment';
			}
		}
		if ( ! isset( $out['urme_source'] ) ) {
			$out['urme_source'] = 'Fulfillment';
		}
		return $out;
	}

	public static function render_column( $column, $post_id ) {
		if ( 'urme_source' === $column && self::allowed() ) {
			echo self::html( (int) $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/**
	 * The products list is the main query on edit.php?post_type=product: prime all its rows at once.
	 */
	public static function prime_list( $posts, $query ) {
		if ( $posts && $query instanceof WP_Query && $query->is_main_query() && 'product' === $query->get( 'post_type' ) && self::allowed() ) {
			self::prime( wp_list_pluck( $posts, 'ID' ) );
		}
		return $posts;
	}
}
