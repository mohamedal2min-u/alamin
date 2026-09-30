<?php
/**
 * WooCommerce side: setup inspection, matching, and the only two writes
 * this plugin ever makes to a product (stock and cost price).
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Store {

	const INSPECT_TRANSIENT = 'urme_ss_inspection';
	const EUR_META          = '_urme_supplier_cost_eur';

	/**
	 * Cost-price meta keys used by common WooCommerce cost plugins.
	 */
	const KNOWN_COST_KEYS = array(
		'_wc_cog_cost'        => 'WooCommerce Cost of Goods (SkyVerge)',
		'_alg_wc_cog_cost'    => 'Cost of Goods for WooCommerce (WPFactory)',
		'yith_cog_cost'       => 'YITH Cost of Goods',
		'_wcj_purchase_price' => 'Booster for WooCommerce',
		'_purchase_price'     => 'Purchase price',
		'_cost_price'         => 'Cost price',
		'_op_cost_price'      => 'OpenPOS cost price',
	);

	/**
	 * Meta keys used by common GTIN/EAN plugins (WooCommerce's own field is _global_unique_id).
	 */
	const KNOWN_GTIN_KEYS = array( '_wpm_gtin_code', 'hwp_product_gtin', '_ts_gtin', '_alg_ean', '_ean', '_gtin', '_barcode' );

	/* ---------------------------------------------------------------------
	 * Setup inspection
	 * ------------------------------------------------------------------- */

	public static function cogs_enabled() {
		return method_exists( 'WC_Product', 'set_cogs_value' )
			&& class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' )
			&& \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'cost_of_goods_sold' );
	}

	/**
	 * Look at how this store keeps SKU, EAN/GTIN and cost price. Cached for 12 hours.
	 */
	public static function inspect( $refresh = false ) {
		$cached = get_transient( self::INSPECT_TRANSIENT );
		if ( ! $refresh && is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;

		$types = "'product','product_variation'";
		// phpcs:disable WordPress.DB
		$count_meta = static function ( $key ) use ( $wpdb, $types ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE p.post_type IN ({$types}) AND p.post_status <> 'trash' AND pm.meta_key = %s AND pm.meta_value <> ''",
					$key
				)
			);
		};

		$r = array(
			'time'          => time(),
			'wc_version'    => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'products'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status <> 'trash'" ),
			'variations'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_status <> 'trash'" ),
			'with_sku'      => $count_meta( '_sku' ),
			'with_gtin'     => $count_meta( '_global_unique_id' ),
			'manage_stock'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type IN ({$types}) AND p.post_status <> 'trash' AND pm.meta_key = '_manage_stock' AND pm.meta_value = 'yes'" ),
			'cogs_supported' => method_exists( 'WC_Product', 'set_cogs_value' ),
			'cogs_enabled'  => self::cogs_enabled(),
			'cogs_count'    => $count_meta( '_cogs_total_value' ),
			'cost_keys'     => array(),
			'other_keys'    => array(),
			'gtin_keys'     => array(),
			'ean_attribute' => null,
			'atum'          => null,
		);

		foreach ( self::KNOWN_COST_KEYS as $key => $label ) {
			$r['cost_keys'][ $key ] = array(
				'label' => $label,
				'count' => $count_meta( $key ),
			);
		}

		// Any other product meta that looks like a cost/purchase price, e.g. from a custom setup.
		$known = array_merge( array_keys( self::KNOWN_COST_KEYS ), array( '_cogs_total_value', self::EUR_META ) );
		$rows  = $wpdb->get_results(
			"SELECT pm.meta_key, COUNT(DISTINCT pm.post_id) AS n FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type IN ({$types}) AND pm.meta_value <> ''
			AND ( pm.meta_key LIKE '%cost%' OR pm.meta_key LIKE '%purchase%' OR pm.meta_key LIKE '%inkop%' OR pm.meta_key LIKE '%cog%' )
			GROUP BY pm.meta_key ORDER BY n DESC LIMIT 20",
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			if ( ! in_array( $row['meta_key'], $known, true ) ) {
				$r['other_keys'][ $row['meta_key'] ] = (int) $row['n'];
			}
		}

		foreach ( self::KNOWN_GTIN_KEYS as $key ) {
			$n = $count_meta( $key );
			if ( $n ) {
				$r['gtin_keys'][ $key ] = $n;
			}
		}

		if ( taxonomy_exists( 'pa_ean' ) ) {
			$r['ean_attribute'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'pa_ean'" );
		}

		$atum_table = $wpdb->prefix . 'atum_product_data';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $atum_table ) ) === $atum_table ) {
			$r['atum'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$atum_table} WHERE purchase_price IS NOT NULL AND purchase_price > 0" );
		}
		// phpcs:enable

		set_transient( self::INSPECT_TRANSIENT, $r, 12 * HOUR_IN_SECONDS );
		return $r;
	}

	/**
	 * Where cost price is written, based on the setting (auto picks the field already in use).
	 *
	 * @return array{type: string|null, key: string, label: string, reason: string}
	 */
	public static function cost_target() {
		$setting = URME_SS_Settings::get( 'cost_target' );
		$none    = array(
			'type'   => null,
			'key'    => '',
			'label'  => 'Not synced',
			'reason' => '',
		);

		if ( 'none' === $setting ) {
			$none['reason'] = 'Cost sync is turned off in the settings.';
			return $none;
		}
		if ( 'wc_cogs' === $setting ) {
			if ( ! self::cogs_enabled() ) {
				$none['reason'] = 'WooCommerce Cost of Goods Sold is selected but the feature is not enabled (WooCommerce > Settings > Advanced > Features).';
				return $none;
			}
			return array(
				'type'   => 'wc_cogs',
				'key'    => '_cogs_total_value',
				'label'  => 'WooCommerce Cost of Goods Sold',
				'reason' => 'Chosen in settings.',
			);
		}
		if ( 'meta' === $setting ) {
			$key = URME_SS_Settings::get( 'cost_meta_key' );
			if ( '' === $key ) {
				$none['reason'] = 'Custom meta key is selected but empty.';
				return $none;
			}
			return array(
				'type'   => 'meta',
				'key'    => $key,
				'label'  => isset( self::KNOWN_COST_KEYS[ $key ] ) ? self::KNOWN_COST_KEYS[ $key ] . " ({$key})" : "Product meta {$key}",
				'reason' => 'Chosen in settings.',
			);
		}

		// Auto: use whichever cost field already holds the most values.
		$inspect = self::inspect();
		$best    = null;
		$best_n  = 0;
		if ( $inspect['cogs_enabled'] ) {
			$best   = array(
				'type'  => 'wc_cogs',
				'key'   => '_cogs_total_value',
				'label' => 'WooCommerce Cost of Goods Sold',
			);
			$best_n = $inspect['cogs_count'];
		}
		foreach ( $inspect['cost_keys'] as $key => $info ) {
			if ( $info['count'] > $best_n ) {
				$best   = array(
					'type'  => 'meta',
					'key'   => $key,
					'label' => $info['label'] . " ({$key})",
				);
				$best_n = $info['count'];
			}
		}
		if ( ! $best ) {
			$none['reason'] = 'No existing cost-price field was found. Enable WooCommerce > Settings > Advanced > Features > "Cost of Goods Sold" (recommended), or pick a field in the settings.';
			return $none;
		}
		$best['reason'] = $best_n
			? sprintf( 'Detected automatically: %d products already use this field.', $best_n )
			: 'Detected automatically: WooCommerce Cost of Goods Sold is enabled.';
		return $best;
	}

	/* ---------------------------------------------------------------------
	 * Linking a selected item (matching itself lives in URME_SS_Matcher)
	 * ------------------------------------------------------------------- */

	/**
	 * Try to link a selected item automatically. Only a single, unambiguous,
	 * not-yet-linked product is accepted.
	 *
	 * @return array{product_id: int, method: string, message: string}
	 */
	public static function auto_match( array $item, $link_id = 0 ) {
		$matches = URME_SS_Matcher::match( $item )['candidates'];
		foreach ( array_keys( $matches ) as $pid ) {
			if ( URME_SS_DB::item_key_for_product( $pid, $link_id ) ) {
				unset( $matches[ $pid ] );
			}
		}
		if ( 1 === count( $matches ) ) {
			$pid = (int) key( $matches );
			return array(
				'product_id' => $pid,
				'method'     => implode( '+', current( $matches ) ),
				'message'    => '',
			);
		}
		if ( ! $matches ) {
			return array(
				'product_id' => 0,
				'method'     => '',
				'message'    => 'No product with this SKU or EAN; link manually.',
			);
		}
		return array(
			'product_id' => 0,
			'method'     => '',
			'message'    => 'Several products match (IDs ' . implode( ', ', array_keys( $matches ) ) . '); link manually.',
		);
	}

	/**
	 * Current WooCommerce stock of many products/variations, read in bulk (a constant number of
	 * queries for any number of IDs). Read only. A variation whose stock is managed by its parent
	 * reports the parent's stock (that is where WooCommerce keeps it).
	 *
	 * @param int[] $ids Exact product or variation IDs.
	 * @return array<int, array{managed: bool, qty: int|null, backorders: string, status: string, by_parent: bool, variable: bool, regular: string, sale: string}|null>
	 */
	public static function stock_info( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		_prime_post_caches( $ids, false, false );
		$parents = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post && 'product_variation' === $post->post_type && $post->post_parent ) {
				$parents[] = (int) $post->post_parent;
			}
		}
		if ( $parents ) {
			_prime_post_caches( $parents, false, false );
		}
		update_meta_cache( 'post', array_merge( $ids, $parents ) );
		update_object_term_cache( $ids, 'product' );

		$out = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || ! in_array( $post->post_type, array( 'product', 'product_variation' ), true ) || 'trash' === $post->post_status ) {
				$out[ $id ] = null;
				continue;
			}
			$stock_id  = $id;
			$by_parent = false;
			if ( 'yes' !== get_post_meta( $id, '_manage_stock', true ) && 'product_variation' === $post->post_type && $post->post_parent && 'yes' === get_post_meta( $post->post_parent, '_manage_stock', true ) ) {
				$stock_id  = (int) $post->post_parent;
				$by_parent = true;
			}
			$managed    = 'yes' === get_post_meta( $stock_id, '_manage_stock', true );
			$backorders = (string) get_post_meta( $stock_id, '_backorders', true );
			$types      = 'product' === $post->post_type ? get_the_terms( $id, 'product_type' ) : false;
			$out[ $id ] = array(
				'managed'    => $managed,
				'qty'        => $managed ? (int) wc_stock_amount( get_post_meta( $stock_id, '_stock', true ) ) : null,
				'backorders' => '' === $backorders ? 'no' : $backorders,
				'status'     => (string) get_post_meta( $id, '_stock_status', true ),
				'by_parent'  => $by_parent,
				'variable'   => is_array( $types ) && in_array( 'variable', wp_list_pluck( $types, 'slug' ), true ),
				'regular'    => (string) get_post_meta( $id, '_regular_price', true ),
				'sale'       => (string) get_post_meta( $id, '_sale_price', true ),
			);
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * The two product writes
	 * ------------------------------------------------------------------- */

	/**
	 * Set product stock to the supplier quantity. Writes only when it differs.
	 *
	 * @return string|null Change description, or null when nothing changed.
	 */
	public static function apply_stock( WC_Product $product, $qty, $enable_manage ) {
		$qty     = max( 0, (int) $qty );
		$managed = $product->get_manage_stock();

		// A variation whose stock is managed by its parent is treated as unmanaged here.
		if ( true !== $managed ) {
			if ( $enable_manage ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $qty );
				$product->save();
				return sprintf( 'stock management enabled, stock %d', $qty );
			}
			$status = $qty > 0 ? 'instock' : 'outofstock';
			if ( $product->get_stock_status() !== $status ) {
				$old = $product->get_stock_status();
				$product->set_stock_status( $status );
				$product->save();
				return sprintf( 'stock status %s → %s', $old, $status );
			}
			return null;
		}

		$current = $product->get_stock_quantity();
		if ( null !== $current && (int) $current === $qty ) {
			return null;
		}
		// Updates the quantity and lets WooCommerce set in stock / out of stock.
		wc_update_product_stock( $product, $qty, 'set' );
		return sprintf( 'stock %s → %d', null === $current ? '–' : (int) $current, $qty );
	}

	/**
	 * Keep the supplier's original EUR cost as a reference on the product (hidden meta).
	 */
	public static function apply_eur_reference( WC_Product $product, $eur ) {
		$eur_str = wc_format_decimal( $eur, 4 );
		if ( get_post_meta( $product->get_id(), self::EUR_META, true ) !== $eur_str ) {
			update_post_meta( $product->get_id(), self::EUR_META, $eur_str );
		}
	}

	public static function get_cost( WC_Product $product, array $target ) {
		if ( 'wc_cogs' === $target['type'] ) {
			return $product->get_cogs_value();
		}
		$v = get_post_meta( $product->get_id(), $target['key'], true );
		return ( '' === $v || ! is_numeric( $v ) ) ? null : (float) $v;
	}

	/**
	 * Write cost price in SEK to the chosen field. Writes only when it differs.
	 *
	 * @return string|null Change description, or null when nothing changed.
	 */
	public static function apply_cost( WC_Product $product, array $target, $sek ) {
		// WooCommerce 10.x/11.x: an "additive" variation cost is added on top of the parent's cost,
		// so writing the full supplier cost there would double count. Leave it for a human.
		if ( 'wc_cogs' === $target['type'] && method_exists( $product, 'get_cogs_value_is_additive' ) && $product->get_cogs_value_is_additive() ) {
			throw new Exception( 'This variation\'s cost is set to be added to the parent product\'s cost; turn that off on the variation to let the supplier cost be synced.' );
		}

		$current = self::get_cost( $product, $target );
		if ( null !== $current && abs( round( $current, 2 ) - round( $sek, 2 ) ) < 0.005 ) {
			return null;
		}

		if ( 'wc_cogs' === $target['type'] ) {
			$product->set_cogs_value( (float) $sek );
			$product->save();
		} else {
			update_post_meta( $product->get_id(), $target['key'], wc_format_decimal( $sek, 2 ) );
		}
		return sprintf( 'cost %s → %s SEK', null === $current ? '–' : wc_format_decimal( $current, 2 ), wc_format_decimal( $sek, 2 ) );
	}
}
