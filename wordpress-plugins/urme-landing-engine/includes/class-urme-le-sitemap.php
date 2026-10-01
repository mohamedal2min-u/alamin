<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class URME_LE_Sitemap {

	const AUTO_ROUTES_TRANSIENT = 'urme_le_auto_routes';

	public static function hooks() {
		add_filter( 'rank_math/sitemap/providers', array( __CLASS__, 'register_provider' ) );
		add_action( 'save_post_' . URME_LE_Landing_CPT::POST_TYPE, array( __CLASS__, 'invalidate' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'maybe_invalidate_deleted' ) );

		// Automatic routes depend on products: brand, category, series and sale.
		add_action( 'woocommerce_update_product', array( __CLASS__, 'invalidate' ) );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'invalidate' ) );
		add_action( 'woocommerce_delete_product', array( __CLASS__, 'invalidate' ) );
		add_action( 'woocommerce_trash_product', array( __CLASS__, 'invalidate' ) );
		add_action( 'woocommerce_scheduled_sales', array( __CLASS__, 'invalidate' ) );
	}

	public static function register_provider( $providers ) {
		if ( ! interface_exists( '\\RankMath\\Sitemap\\Providers\\Provider' ) ) {
			return $providers;
		}

		if ( ! class_exists( 'URME_LE_RankMath_Sitemap_Provider' ) ) {
			require_once URME_LE_PATH . 'includes/class-urme-le-rankmath-sitemap-provider.php';
		}

		if ( class_exists( 'URME_LE_RankMath_Sitemap_Provider' ) ) {
			$providers['urme-landing'] = new URME_LE_RankMath_Sitemap_Provider();
		}

		return $providers;
	}

	public static function invalidate() {
		delete_transient( 'urme_le_landing_map' );
		delete_transient( self::AUTO_ROUTES_TRANSIENT );
		if ( class_exists( '\\RankMath\\Sitemap\\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage( 'urme-landing' );
		}
	}

	public static function maybe_invalidate_deleted( $post_id ) {
		if ( URME_LE_Landing_CPT::POST_TYPE === get_post_type( $post_id ) ) {
			self::invalidate();
		}
	}

	/**
	 * Automatic brand routes that currently list products, for the sitemap:
	 * /marken/{brand}/herrklockor/, /damklockor/, /rea/ and /{pa_serie term}/.
	 *
	 * Routes owned by a published landing record are skipped (the record is
	 * listed on its own). Built from one ID query per brand, then cached until
	 * a product or landing changes, so sitemap requests stay cheap.
	 *
	 * @return array[] Each item: brand_slug, child_slug, lastmod (GMT mysql date).
	 */
	public static function automatic_routes() {
		$cached = get_transient( self::AUTO_ROUTES_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$routes = array();

		if ( ! taxonomy_exists( 'product_brand' ) ) {
			set_transient( self::AUTO_ROUTES_TRANSIENT, $routes, 12 * HOUR_IN_SECONDS );
			return $routes;
		}

		$map    = URME_LE_Landing_CPT::get_landing_map();
		$brands = get_terms(
			array(
				'taxonomy'   => 'product_brand',
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $brands ) || ! $brands ) {
			set_transient( self::AUTO_ROUTES_TRANSIENT, $routes, 12 * HOUR_IN_SECONDS );
			return $routes;
		}

		$sale_ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wp_parse_id_list( wc_get_product_ids_on_sale() ) : array();

		$gender_terms = array();
		foreach ( array( 'herr' => 'herrklockor', 'dam' => 'damklockor' ) as $gender => $child ) {
			$slug = URME_LE_Router::gender_category_slug( $gender );
			$term = $slug ? URME_LE_Router::get_term( 'product_cat', $slug ) : false;
			if ( $term ) {
				$ids = get_term_children( $term->term_id, 'product_cat' );
				$ids = is_wp_error( $ids ) ? array() : array_map( 'absint', $ids );
				$ids[] = (int) $term->term_id;
				$gender_terms[ $child ] = $ids;
			}
		}

		foreach ( $brands as $brand ) {
			$product_ids = self::visible_product_ids( $brand->slug );
			if ( ! $product_ids ) {
				continue;
			}

			$lastmod = self::latest_modified( $product_ids );
			$children = array();

			// Gender routes: any product of the brand in that category (or a child).
			$cat_ids = wp_get_object_terms( $product_ids, 'product_cat', array( 'fields' => 'ids' ) );
			$cat_ids = is_wp_error( $cat_ids ) ? array() : array_map( 'absint', $cat_ids );
			foreach ( $gender_terms as $child => $ids ) {
				if ( array_intersect( $cat_ids, $ids ) ) {
					$children[] = $child;
				}
			}

			// Sale route.
			if ( $sale_ids && array_intersect( $product_ids, $sale_ids ) ) {
				$children[] = 'rea';
			}

			// Series routes: every pa_serie term used by this brand's products.
			if ( taxonomy_exists( 'pa_serie' ) ) {
				$series = wp_get_object_terms( $product_ids, 'pa_serie', array( 'fields' => 'slugs' ) );
				if ( ! is_wp_error( $series ) ) {
					foreach ( $series as $series_slug ) {
						// Herr/dam/rea already map to fixed routes; never list a series twice.
						if ( ! in_array( $series_slug, array( 'herrklockor', 'damklockor', 'rea' ), true ) ) {
							$children[] = sanitize_title( $series_slug );
						}
					}
				}
			}

			foreach ( array_unique( $children ) as $child ) {
				if ( isset( $map['brand'][ $brand->slug . ':' . $child ] ) ) {
					continue; // Listed through its landing record.
				}
				$routes[] = array(
					'brand_slug' => $brand->slug,
					'child_slug' => $child,
					'lastmod'    => $lastmod,
				);
			}
		}

		set_transient( self::AUTO_ROUTES_TRANSIENT, $routes, 12 * HOUR_IN_SECONDS );
		return $routes;
	}

	/**
	 * Published products of a brand that the shop actually lists: not hidden
	 * from the catalog, and not out of stock when the store hides those.
	 * Mirrors what the route itself would show, so the sitemap never lists a
	 * URL that answers 404.
	 *
	 * @param string $brand_slug product_brand slug.
	 * @return int[]
	 */
	private static function visible_product_ids( $brand_slug ) {
		$tax_query = array(
			'relation' => 'AND',
			array(
				'taxonomy'         => 'product_brand',
				'field'            => 'slug',
				'terms'            => array( $brand_slug ),
				'include_children' => true,
			),
		);

		$hidden = array( 'exclude-from-catalog' );
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$hidden[] = 'outofstock';
		}
		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => $hidden,
			'operator' => 'NOT IN',
		);

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);

		return array_map( 'absint', $query->posts );
	}

	private static function latest_modified( $product_ids ) {
		global $wpdb;

		$ids = implode( ',', array_map( 'absint', $product_ids ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer list built above.
		return (string) $wpdb->get_var( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE ID IN ({$ids})" );
	}
}
