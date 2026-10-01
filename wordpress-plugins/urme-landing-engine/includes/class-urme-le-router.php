<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class URME_LE_Router {

	const Q_CONTEXT    = 'urme_le_context';
	const Q_LANDING_ID = 'urme_le_landing_id';
	const Q_BRAND      = 'urme_le_brand';
	const Q_KIND       = 'urme_le_kind';
	const Q_CHILD      = 'urme_le_child';

	private static $term_cache = array();

	/**
	 * True only when THIS request's parse_request() regex actually matched a
	 * pretty-permalink route. Never trust get_query_var() alone for this: these
	 * query vars are registered public query vars, so WordPress populates them
	 * from $_GET automatically before parse_request runs, which would let a
	 * request like /?urme_le_context=global&urme_le_landing_id=5 forge a match
	 * on any URL. This flag is the source of truth instead.
	 */
	private static $matched = false;

	private static $needs_redirect = false;

	private static $empty_configured = false;

	private static $thin = false;

	private static $forced_404 = false;

	private static $active_scope = null;

	public static function hooks() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_request' ), 99 );
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ), 5 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'redirect_canonical' ), 99, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_canonical' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'force_status' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'guard_empty_results' ), 5 );
		add_filter( 'woocommerce_widget_get_current_page_url', array( __CLASS__, 'widget_current_page_url' ), 99 );
		add_filter( 'woodmart_shop_page_link', array( __CLASS__, 'woodmart_shop_page_link' ), 99, 3 );
		add_filter( 'loop_shop_post_in', array( __CLASS__, 'loop_shop_post_in' ), 99 );
		add_filter( 'woocommerce_get_filtered_term_product_counts_query', array( __CLASS__, 'filtered_term_product_counts_query' ), 99 );
		add_filter( 'woocommerce_price_filter_sql', array( __CLASS__, 'price_filter_sql' ), 99, 3 );
	}

	public static function query_vars( $vars ) {
		foreach ( array( self::Q_CONTEXT, self::Q_LANDING_ID, self::Q_BRAND, self::Q_KIND, self::Q_CHILD ) as $var ) {
			if ( ! in_array( $var, $vars, true ) ) {
				$vars[] = $var;
			}
		}
		return $vars;
	}

	public static function parse_request( $wp ) {
		if ( is_admin() ) {
			return;
		}

		$request = trim( (string) $wp->request, '/' );
		if ( '' === $request ) {
			return;
		}

		$map = URME_LE_Landing_CPT::get_landing_map();

		/* Global: /klockor/{slug}/page/{n}/ */
		if ( preg_match( '#^klockor/([^/]+)(?:/page/([1-9][0-9]*))?$#', $request, $m ) ) {
			$slug = sanitize_title( $m[1] );
			if ( isset( $map['global'][ $slug ] ) ) {
				$config = $map['global'][ $slug ];
				$vars = self::clean_query_vars( $wp->query_vars );
				$vars['post_type']        = 'product';
				$vars[ self::Q_CONTEXT ]  = 'global';
				$vars[ self::Q_LANDING_ID ] = (int) $config['id'];
				$vars[ self::Q_KIND ]     = 'configured';
				$vars[ self::Q_CHILD ]    = $slug;
				if ( ! empty( $m[2] ) ) {
					$vars['paged'] = max( 1, absint( $m[2] ) );
				}
				$wp->query_vars = $vars;
				self::$matched = true;
				if ( $slug !== $m[1] ) {
					self::$needs_redirect = true;
				}
				return;
			}
		}

		/* Brand: /marken/{brand}/{child}/page/{n}/ */
		if ( ! preg_match( '#^marken/([^/]+)/([^/]+)(?:/page/([1-9][0-9]*))?$#', $request, $m ) ) {
			return;
		}

		$brand_slug = sanitize_title( $m[1] );
		$child_slug = sanitize_title( $m[2] );
		$brand = self::get_term( 'product_brand', $brand_slug );
		if ( ! $brand ) {
			return;
		}

		$kind       = '';
		$landing_id = 0;
		$key        = $brand_slug . ':' . $child_slug;

		if ( isset( $map['brand'][ $key ] ) ) {
			$kind       = 'configured';
			$landing_id = (int) $map['brand'][ $key ]['id'];
		} elseif ( 'herrklockor' === $child_slug && self::gender_category_slug( 'herr' ) ) {
			$kind = 'herr';
		} elseif ( 'damklockor' === $child_slug && self::gender_category_slug( 'dam' ) ) {
			$kind = 'dam';
		} elseif ( 'rea' === $child_slug ) {
			$kind = 'rea';
		} elseif ( self::get_term( 'pa_serie', $child_slug ) ) {
			$kind = 'serie';
		} else {
			return;
		}

		$vars = self::clean_query_vars( $wp->query_vars );
		$vars['post_type']          = 'product';
		$vars[ self::Q_CONTEXT ]    = 'brand';
		$vars[ self::Q_BRAND ]      = $brand_slug;
		$vars[ self::Q_KIND ]       = $kind;
		$vars[ self::Q_CHILD ]      = $child_slug;
		if ( $landing_id ) {
			$vars[ self::Q_LANDING_ID ] = $landing_id;
		}
		if ( ! empty( $m[3] ) ) {
			$vars['paged'] = max( 1, absint( $m[3] ) );
		}
		$wp->query_vars = $vars;
		self::$matched = true;
		if ( $brand_slug !== $m[1] || $child_slug !== $m[2] ) {
			self::$needs_redirect = true;
		}
	}

	private static function clean_query_vars( $query_vars ) {
		$query_vars = is_array( $query_vars ) ? $query_vars : array();
		$remove = array(
			'error', 'name', 'pagename', 'page_id', 'attachment', 'attachment_id',
			'product', 'product_brand', 'product_cat', 'product_tag', 'pa_serie',
			'taxonomy', 'term', 'paged', self::Q_CONTEXT, self::Q_LANDING_ID,
			self::Q_BRAND, self::Q_KIND, self::Q_CHILD,
		);
		foreach ( $remove as $var ) {
			unset( $query_vars[ $var ] );
		}
		return $query_vars;
	}

	public static function is_dynamic() {
		if ( ! self::$matched || self::$forced_404 ) {
			return false;
		}
		return in_array( (string) get_query_var( self::Q_CONTEXT ), array( 'global', 'brand' ), true );
	}

	public static function context() {
		if ( ! self::is_dynamic() ) {
			return false;
		}
		return array(
			'context'    => (string) get_query_var( self::Q_CONTEXT ),
			'landing_id' => absint( get_query_var( self::Q_LANDING_ID ) ),
			'brand_slug' => sanitize_title( (string) get_query_var( self::Q_BRAND ) ),
			'kind'       => sanitize_key( (string) get_query_var( self::Q_KIND ) ),
			'child_slug' => sanitize_title( (string) get_query_var( self::Q_CHILD ) ),
			'paged'      => max( 1, absint( get_query_var( 'paged' ) ) ),
		);
	}

	/**
	 * current_config() is called ~15-20 times over the course of a single
	 * page render (title, description, canonical, breadcrumbs, hero, intro,
	 * OG image...), and each call previously rebuilt the config array from
	 * scratch (11 get_post_meta lookups plus sanitization). The underlying
	 * post meta can't change mid-request, so memoize per landing ID.
	 */
	private static $config_cache = array();

	private static $scope_sql_cache = null;

	private static $scope_sql_cache_ready = false;

	public static function current_config() {
		$ctx = self::context();
		if ( ! $ctx || empty( $ctx['landing_id'] ) ) {
			return false;
		}
		return self::config_by_id( $ctx['landing_id'] );
	}

	private static function config_by_id( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		if ( ! array_key_exists( $id, self::$config_cache ) ) {
			self::$config_cache[ $id ] = URME_LE_Landing_CPT::config_from_post( $id );
		}
		return self::$config_cache[ $id ];
	}

	public static function current_brand_term() {
		$ctx = self::context();
		if ( ! $ctx || 'brand' !== $ctx['context'] || ! $ctx['brand_slug'] ) {
			return false;
		}
		return self::get_term( 'product_brand', $ctx['brand_slug'] );
	}

	public static function current_series_term() {
		$ctx = self::context();
		if ( ! $ctx || 'serie' !== $ctx['kind'] ) {
			return false;
		}
		return self::get_term( 'pa_serie', $ctx['child_slug'] );
	}

	/**
	 * The single WP_Term that best represents the current route, if any -
	 * used to fall back to that term's own description and Rank Math term SEO
	 * fields when the landing itself (if any) leaves those fields empty.
	 *
	 * Deliberately excludes 'herr'/'dam': herrklockor/damklockor are a single
	 * product_cat term shared by every brand, so pulling its description here
	 * would print the exact same meta title/description on every brand's
	 * .../herrklockor/ and .../damklockor/ page - a duplicate-content
	 * regression, worse than the old generic per-brand template it would
	 * replace. Only 'serie' (a term that is inherently specific to this
	 * brand+series combination) and single-attribute-term "configured"
	 * landings - which is exactly how documented Brand-scope overrides like
	 * /marken/{brand}/{series}/ are set up - are safe to use here.
	 */
	public static function current_child_term() {
		$ctx = self::context();
		if ( ! $ctx ) {
			return false;
		}
		if ( 'serie' === $ctx['kind'] ) {
			return self::current_series_term();
		}
		if ( 'configured' === $ctx['kind'] ) {
			$config = self::current_config();
			$terms  = $config ? self::resolve_configured_term_slugs( $config ) : array();
			if (
				$config
				&& 'attribute' === $config['filter_type']
				&& ! empty( $config['taxonomy'] )
				&& 1 === count( $terms )
			) {
				return self::get_term( $config['taxonomy'], $terms[0] );
			}
		}
		return false;
	}

	/**
	 * The product_cat slug that holds men's ('herr') or women's ('dam') watches.
	 *
	 * The public routes are always /marken/{brand}/herrklockor/ and
	 * /marken/{brand}/damklockor/, but the store's own category slugs may differ
	 * (URME uses `herr` and `dam`). Older releases only looked for `herrklockor`
	 * and `damklockor`, so every automatic gender route returned 404. The first
	 * existing candidate wins; filter `urme_le_gender_category_slugs` to change
	 * the candidates.
	 *
	 * @param string $gender 'herr' or 'dam'.
	 * @return string Existing product_cat slug, or '' when none exists.
	 */
	public static function gender_category_slug( $gender ) {
		$candidates = array(
			'herr' => array( 'herrklockor', 'herr' ),
			'dam'  => array( 'damklockor', 'dam' ),
		);

		if ( ! isset( $candidates[ $gender ] ) ) {
			return '';
		}

		$slugs = (array) apply_filters( 'urme_le_gender_category_slugs', $candidates[ $gender ], $gender );

		foreach ( $slugs as $slug ) {
			if ( self::get_term( 'product_cat', $slug ) ) {
				return sanitize_title( $slug );
			}
		}

		return '';
	}

	public static function get_term( $taxonomy, $slug ) {
		$taxonomy = sanitize_key( $taxonomy );
		$slug = sanitize_title( $slug );
		if ( ! $taxonomy || ! $slug || ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}
		$key = $taxonomy . ':' . $slug;
		if ( array_key_exists( $key, self::$term_cache ) ) {
			return self::$term_cache[ $key ];
		}
		$term = get_term_by( 'slug', $slug, $taxonomy );
		self::$term_cache[ $key ] = ( $term && ! is_wp_error( $term ) ) ? $term : false;
		return self::$term_cache[ $key ];
	}

	public static function pre_get_posts( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! self::$matched ) {
			return;
		}

		$context = (string) $query->get( self::Q_CONTEXT );
		if ( ! in_array( $context, array( 'global', 'brand' ), true ) ) {
			return;
		}

		$scope = self::scope_from_query( $query );
		self::$active_scope          = $scope;
		self::$scope_sql_cache       = null;
		self::$scope_sql_cache_ready = false;

		if ( $scope['invalid'] ) {
			self::intersect_post_in( $query, array( 0 ) );
			return;
		}

		self::merge_tax_scope( $query, $scope['tax_clauses'] );
	}

	/**
	 * WooCommerce resets post__in from loop_shop_post_in while building the
	 * main archive query. Put sale landing IDs into that native filter so the
	 * immutable sale scope exists before WoodMart/WooCommerce callbacks inspect
	 * the product query.
	 */
	public static function loop_shop_post_in( $post_in ) {
		if ( ! self::is_dynamic() ) {
			return $post_in;
		}

		$scope = self::current_scope_descriptor();
		if ( $scope['invalid'] ) {
			return array( 0 );
		}

		if ( ! $scope['sale'] ) {
			return $post_in;
		}

		$sale_ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wp_parse_id_list( wc_get_product_ids_on_sale() ) : array();
		if ( ! $sale_ids ) {
			return array( 0 );
		}

		$post_in = wp_parse_id_list( $post_in );
		if ( ! $post_in ) {
			return $sale_ids;
		}

		$intersection = array_values( array_intersect( $post_in, $sale_ids ) );
		return $intersection ? $intersection : array( 0 );
	}

	/**
	 * Keep layered-nav term counts inside the immutable Landing Engine scope.
	 * WooCommerce intentionally removes the currently rendered taxonomy from
	 * OR-count queries. On an attribute landing (for example Automatiska), that
	 * would also remove the landing's own pa_urverkstyp restriction and expose
	 * impossible options such as Quartz. Re-applying only the landing scope at
	 * SQL level preserves normal faceting while preventing zero-result choices.
	 */
	public static function filtered_term_product_counts_query( $query ) {
		if ( ! self::is_dynamic() || ! is_array( $query ) || empty( $query['where'] ) ) {
			return $query;
		}

		$scope_sql = self::current_scope_sql();
		if ( $scope_sql ) {
			$query['where'] .= $scope_sql;
		}

		/*
		 * WooCommerce's lookup-table filterer intentionally omits the currently
		 * rendered taxonomy from OR-count queries. On Landing Engine routes that
		 * also means OR selections from OTHER facets are not always represented in
		 * the generated count SQL, so stale choices can remain visible even though
		 * clicking them would produce zero products. Detect the facet whose counts
		 * are being rendered and add the chosen terms from the other facets only.
		 * This preserves OR within a facet and AND between separate facets.
		 */
		if (
			'yes' !== get_option( 'woocommerce_attribute_lookup_enabled' ) ||
			! class_exists( 'WC_Query' ) ||
			! preg_match( "/\\.taxonomy='([^']+)'/", (string) $query['where'], $match )
		) {
			return $query;
		}

		$target_taxonomy = sanitize_key( $match[1] );
		$chosen          = WC_Query::get_layered_nav_chosen_attributes();

		if ( ! $target_taxonomy || empty( $chosen ) || ! is_array( $chosen ) ) {
			return $query;
		}

		global $wpdb;
		$lookup_table = $wpdb->prefix . 'wc_product_attributes_lookup';

		$hide_out_of_stock = apply_filters(
			'woocommerce_product_attributes_filterer_hide_out_of_stock',
			'yes' === get_option( 'woocommerce_hide_out_of_stock_items' )
		);
		$stock_sql = $hide_out_of_stock ? ' AND in_stock = 1' : '';

		foreach ( $chosen as $taxonomy => $data ) {
			$taxonomy = sanitize_key( $taxonomy );

			// The current facet stays unconstrained so its own terms remain OR-able.
			if ( ! $taxonomy || $taxonomy === $target_taxonomy || empty( $data['terms'] ) ) {
				continue;
			}

			$slugs = array_values(
				array_unique(
					array_filter( array_map( 'sanitize_title', (array) $data['terms'] ) )
				)
			);

			if ( ! $slugs || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'slug'       => $slugs,
				)
			);

			if ( is_wp_error( $terms ) || ! $terms ) {
				$query['where'] .= ' AND 1=0';
				continue;
			}

			$term_ids = array_values(
				array_unique(
					array_filter( array_map( 'absint', wp_list_pluck( $terms, 'term_id' ) ) )
				)
			);

			if ( ! $term_ids ) {
				$query['where'] .= ' AND 1=0';
				continue;
			}

			$ids_sql    = implode( ',', $term_ids );
			$tax_sql    = esc_sql( sanitize_title( $taxonomy ) );
			$query_type = isset( $data['query_type'] ) && 'and' === strtolower( (string) $data['query_type'] ) ? 'and' : 'or';

			if ( 'and' === $query_type && count( $term_ids ) > 1 ) {
				$required = count( $term_ids );
				$query['where'] .= "
					AND {$wpdb->posts}.ID IN (
						SELECT product_or_parent_id
						FROM {$lookup_table}
						WHERE taxonomy='{$tax_sql}'
						  AND term_id IN ({$ids_sql})
						  {$stock_sql}
						GROUP BY product_or_parent_id
						HAVING COUNT(DISTINCT term_id)={$required}
					)";
			} else {
				$query['where'] .= "
					AND {$wpdb->posts}.ID IN (
						SELECT product_or_parent_id
						FROM {$lookup_table}
						WHERE taxonomy='{$tax_sql}'
						  AND term_id IN ({$ids_sql})
						  {$stock_sql}
					)";
			}
		}

		return $query;
	}

	/**
	 * Scope WooCommerce's price-range lookup to the landing too. The core price
	 * query is a SELECT whose final parenthesis closes the product-ID subquery;
	 * inserting the same immutable scope immediately before that parenthesis
	 * keeps min/max calculations aligned with the products that can be shown.
	 */
	public static function price_filter_sql( $sql, $meta_query_sql = array(), $tax_query_sql = array() ) {
		if ( ! self::is_dynamic() || ! is_string( $sql ) || '' === $sql ) {
			return $sql;
		}

		$scope_sql = self::current_scope_sql();
		if ( ! $scope_sql ) {
			return $sql;
		}

		$position = strrpos( $sql, ')' );
		if ( false === $position ) {
			return $sql;
		}

		return substr( $sql, 0, $position ) . $scope_sql . substr( $sql, $position );
	}

	private static function scope_from_query( $query ) {
		return self::scope_descriptor(
			(string) $query->get( self::Q_CONTEXT ),
			sanitize_key( (string) $query->get( self::Q_KIND ) ),
			absint( $query->get( self::Q_LANDING_ID ) ),
			sanitize_title( (string) $query->get( self::Q_BRAND ) ),
			sanitize_title( (string) $query->get( self::Q_CHILD ) )
		);
	}

	private static function current_scope_descriptor() {
		if ( is_array( self::$active_scope ) ) {
			return self::$active_scope;
		}

		$ctx = self::context();
		if ( ! $ctx ) {
			return array(
				'tax_clauses' => array(),
				'sale'        => false,
				'invalid'     => true,
			);
		}

		return self::scope_descriptor(
			$ctx['context'],
			$ctx['kind'],
			$ctx['landing_id'],
			$ctx['brand_slug'],
			$ctx['child_slug']
		);
	}

	private static function scope_descriptor( $context, $kind, $landing_id, $brand_slug, $child_slug ) {
		$scope = array(
			'tax_clauses' => array(),
			'sale'        => false,
			'invalid'     => false,
		);

		if ( 'brand' === $context ) {
			$brand_clause = self::taxonomy_clause( 'product_brand', array( $brand_slug ), true );
			if ( ! $brand_clause ) {
				$scope['invalid'] = true;
				return $scope;
			}
			$scope['tax_clauses'][] = $brand_clause;
		}

		if ( 'configured' === $kind ) {
			$config = $landing_id ? self::config_by_id( $landing_id ) : false;
			if ( ! $config ) {
				$scope['invalid'] = true;
				return $scope;
			}

			$type = isset( $config['filter_type'] ) ? sanitize_key( $config['filter_type'] ) : '';
			if ( 'sale' === $type ) {
				$scope['sale'] = true;
				return $scope;
			}

			$taxonomy = isset( $config['taxonomy'] ) ? sanitize_key( $config['taxonomy'] ) : '';
			if ( 'category' === $type && ! $taxonomy ) {
				$taxonomy = 'product_cat';
			}
			$terms = self::resolve_configured_term_slugs( $config );
			$clause = ( $taxonomy && $terms ) ? self::taxonomy_clause( $taxonomy, $terms, 'product_cat' === $taxonomy ) : false;
			if ( ! $clause ) {
				$scope['invalid'] = true;
				return $scope;
			}
			$scope['tax_clauses'][] = $clause;
			return $scope;
		}

		if ( 'herr' === $kind || 'dam' === $kind ) {
			$category = self::gender_category_slug( $kind );
			$clause   = $category ? self::taxonomy_clause( 'product_cat', array( $category ), true ) : false;
		} elseif ( 'rea' === $kind ) {
			$scope['sale'] = true;
			return $scope;
		} elseif ( 'serie' === $kind ) {
			$clause = self::taxonomy_clause( 'pa_serie', array( $child_slug ), false );
		} else {
			$clause = false;
		}

		if ( ! $clause ) {
			$scope['invalid'] = true;
			return $scope;
		}

		$scope['tax_clauses'][] = $clause;
		return $scope;
	}

	/**
	 * Resolve the known Automatiska default aliases at runtime without writing
	 * to the database. This is deliberately limited to the seeded global
	 * Automatiska landing shape and never changes unrelated/custom filters.
	 *
	 * @param array $config Landing configuration.
	 * @return array
	 */
	private static function resolve_configured_term_slugs( $config ) {
		$terms = isset( $config['term_slugs'] ) && is_array( $config['term_slugs'] )
			? array_values( array_unique( array_filter( array_map( 'sanitize_title', $config['term_slugs'] ) ) ) )
			: array();

		if (
			! $terms
			|| 'global' !== ( isset( $config['scope'] ) ? $config['scope'] : '' )
			|| 'automatiska' !== ( isset( $config['route_slug'] ) ? $config['route_slug'] : '' )
			|| 'attribute' !== ( isset( $config['filter_type'] ) ? $config['filter_type'] : '' )
			|| 'pa_urverkstyp' !== ( isset( $config['taxonomy'] ) ? $config['taxonomy'] : '' )
		) {
			return $terms;
		}

		$known_aliases = array( 'automatic', 'automatisk', 'automatiskt' );
		if ( array_diff( $terms, $known_aliases ) ) {
			return $terms;
		}

		$available = array();
		foreach ( $known_aliases as $slug ) {
			if ( self::get_term( 'pa_urverkstyp', $slug ) ) {
				$available[] = $slug;
			}
		}

		return $available ? $available : $terms;
	}

	private static function merge_tax_scope( $query, $tax_clauses ) {
		$tax_clauses = array_values( array_filter( (array) $tax_clauses ) );
		if ( ! $tax_clauses ) {
			return;
		}

		$our_tax_query = array_merge( array( 'relation' => 'AND' ), $tax_clauses );
		$existing      = $query->get( 'tax_query' );

		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$query->set( 'tax_query', array( 'relation' => 'AND', $existing, $our_tax_query ) );
		} else {
			$query->set( 'tax_query', $our_tax_query );
		}
	}

	private static function current_scope_sql() {
		if ( self::$scope_sql_cache_ready ) {
			return self::$scope_sql_cache;
		}

		$scope = self::current_scope_descriptor();
		if ( $scope['invalid'] ) {
			self::$scope_sql_cache       = ' AND 1=0';
			self::$scope_sql_cache_ready = true;
			return self::$scope_sql_cache;
		}

		global $wpdb;
		$conditions = array();

		foreach ( $scope['tax_clauses'] as $clause ) {
			$term_taxonomy_ids = self::term_taxonomy_ids_for_clause( $clause );
			if ( ! $term_taxonomy_ids ) {
				$conditions[] = '1=0';
				continue;
			}

			$ids = implode( ',', array_map( 'absint', $term_taxonomy_ids ) );
			$conditions[] = "{$wpdb->posts}.ID IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$ids}) )";
		}

		if ( $scope['sale'] ) {
			$sale_ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wp_parse_id_list( wc_get_product_ids_on_sale() ) : array();
			if ( $sale_ids ) {
				$conditions[] = $wpdb->posts . '.ID IN (' . implode( ',', array_map( 'absint', $sale_ids ) ) . ')';
			} else {
				$conditions[] = '1=0';
			}
		}

		self::$scope_sql_cache       = $conditions ? ' AND ' . implode( ' AND ', $conditions ) : '';
		self::$scope_sql_cache_ready = true;
		return self::$scope_sql_cache;
	}

	private static function term_taxonomy_ids_for_clause( $clause ) {
		if ( empty( $clause['taxonomy'] ) || empty( $clause['terms'] ) ) {
			return array();
		}

		$taxonomy = sanitize_key( $clause['taxonomy'] );
		$term_ids = array();

		foreach ( (array) $clause['terms'] as $slug ) {
			$term = self::get_term( $taxonomy, $slug );
			if ( ! $term ) {
				continue;
			}
			$term_ids[] = (int) $term->term_id;

			if ( ! empty( $clause['include_children'] ) && is_taxonomy_hierarchical( $taxonomy ) ) {
				$children = get_term_children( $term->term_id, $taxonomy );
				if ( ! is_wp_error( $children ) ) {
					$term_ids = array_merge( $term_ids, array_map( 'absint', $children ) );
				}
			}
		}

		$term_ids = array_values( array_unique( array_filter( array_map( 'absint', $term_ids ) ) ) );
		if ( ! $term_ids ) {
			return array();
		}

		$term_taxonomy_ids = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$term_taxonomy_ids[] = (int) $term->term_taxonomy_id;
			}
		}

		return array_values( array_unique( array_filter( $term_taxonomy_ids ) ) );
	}

	private static function taxonomy_clause( $taxonomy, $terms, $include_children = false ) {
		$taxonomy = sanitize_key( $taxonomy );
		$terms = array_values( array_filter( array_map( 'sanitize_title', (array) $terms ) ) );
		if ( ! $taxonomy || ! $terms || ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		$terms = apply_filters( 'urme_le_resolved_term_slugs', $terms, $taxonomy );
		$terms = array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) $terms ) ) ) );

		$terms = array_values(
			array_filter(
				$terms,
				static function ( $slug ) use ( $taxonomy ) {
					$term = get_term_by( 'slug', $slug, $taxonomy );
					return $term && ! is_wp_error( $term );
				}
			)
		);

		if ( ! $terms ) {
			return false;
		}

		return array(
			'taxonomy'         => $taxonomy,
			'field'            => 'slug',
			'terms'            => $terms,
			'operator'         => 'IN',
			'include_children' => (bool) $include_children,
		);
	}

	private static function intersect_post_in( $query, $ids ) {
		$ids = wp_parse_id_list( $ids );
		$ids = $ids ? $ids : array( 0 );
		$existing = wp_parse_id_list( $query->get( 'post__in' ) );
		if ( $existing ) {
			$ids = array_values( array_intersect( $existing, $ids ) );
			$ids = $ids ? $ids : array( 0 );
		}
		$query->set( 'post__in', $ids );
	}

	public static function redirect_canonical( $redirect_url, $requested_url ) {
		return self::is_dynamic() ? false : $redirect_url;
	}

	/**
	 * WordPress's own canonical redirect is disabled for dynamic routes above,
	 * so a requested slug that only differs in case/accents from its
	 * sanitize_title() form (e.g. /marken/Seiko/ vs /marken/seiko/) would
	 * otherwise render successfully without ever redirecting to the canonical
	 * URL - two indexable URLs for identical content. Issue a real 301 here,
	 * which is a much stronger duplicate-content signal than a canonical tag.
	 */
	public static function maybe_redirect_canonical() {
		if ( ! self::$matched || ! self::$needs_redirect ) {
			return;
		}
		$target = self::canonical_url();
		if ( $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}

	/**
	 * Automatic brand routes (/marken/{brand}/{herrklockor|damklockor|rea|any
	 * pa_serie slug}/) match for any valid brand+term combination, whether or
	 * not a single product actually satisfies it. Without this guard those
	 * combinatorial pages return 200 with zero products forever - classic
	 * soft-404 / thin-content territory. Explicitly configured landings stay
	 * live (an admin published them on purpose) but get noindex while empty,
	 * e.g. a temporary "rea" page with nothing on sale right now.
	 */
	public static function guard_empty_results() {
		if ( ! self::is_dynamic() ) {
			return;
		}

		global $wp_query;
		if ( ! $wp_query ) {
			return;
		}

		if ( $wp_query->post_count > 0 ) {
			// Too few products for a page worth indexing: keep it live, noindex it.
			if ( (int) $wp_query->found_posts < URME_LE_Settings::min_products() ) {
				self::$thin = true;
			}
			return;
		}

		$ctx = self::context();

		if ( $ctx && 'configured' === $ctx['kind'] && $ctx['paged'] <= 1 ) {
			self::$empty_configured = true;
			return;
		}

		self::$forced_404 = true;
		$wp_query->set_404();
		$wp_query->is_archive           = false;
		$wp_query->is_post_type_archive = false;
		status_header( 404 );
		nocache_headers();
	}

	public static function is_empty_configured() {
		return self::$empty_configured;
	}

	/**
	 * True when the route lists fewer products than the "Minimum products to
	 * index" setting. Such pages stay reachable but are noindex and left out
	 * of the sitemap (thin content).
	 */
	public static function is_thin() {
		return self::$thin;
	}

	/**
	 * Cheap check used by the sitemap provider: a landing is listed only when
	 * it shows at least the "Minimum products to index" setting, counting
	 * only products the shop lists (not hidden from the catalog, and not out
	 * of stock when the store hides those).
	 */
	public static function config_has_products( $config ) {
		if ( ! $config ) {
			return false;
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => URME_LE_Settings::min_products(),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$tax_clauses = array( self::visibility_clause() );

		if ( 'brand' === $config['scope'] ) {
			if ( empty( $config['brand_slug'] ) || ! self::get_term( 'product_brand', $config['brand_slug'] ) ) {
				return false;
			}
			$tax_clauses[] = self::taxonomy_clause( 'product_brand', array( $config['brand_slug'] ), true );
		}

		$type = isset( $config['filter_type'] ) ? sanitize_key( $config['filter_type'] ) : '';

		if ( 'sale' === $type ) {
			$ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wp_parse_id_list( wc_get_product_ids_on_sale() ) : array();
			if ( ! $ids ) {
				return false;
			}
			$args['post__in'] = $ids;
		} else {
			$taxonomy = isset( $config['taxonomy'] ) ? sanitize_key( $config['taxonomy'] ) : '';
			if ( 'category' === $type && ! $taxonomy ) {
				$taxonomy = 'product_cat';
			}
			$terms = self::resolve_configured_term_slugs( $config );
			if ( ! $taxonomy || ! $terms ) {
				return false;
			}
			$clause = self::taxonomy_clause( $taxonomy, $terms, 'product_cat' === $taxonomy );
			if ( ! $clause ) {
				return false;
			}
			$tax_clauses[] = $clause;
		}

		$tax_clauses = array_values( array_filter( $tax_clauses ) );
		if ( $tax_clauses ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_clauses );
		}

		$query = new WP_Query( $args );
		return $query->post_count >= URME_LE_Settings::min_products();
	}

	/**
	 * tax_query clause leaving out products the shop does not list.
	 *
	 * @return array
	 */
	public static function visibility_clause() {
		$hidden = array( 'exclude-from-catalog' );
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$hidden[] = 'outofstock';
		}

		return array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => $hidden,
			'operator' => 'NOT IN',
		);
	}

	public static function force_status() {
		if ( ! self::is_dynamic() ) {
			return;
		}
		global $wp_query;
		if ( ! $wp_query ) {
			return;
		}
		status_header( 200 );
	}

	public static function widget_current_page_url( $url ) {
		return self::is_dynamic() ? self::rebase_to_dynamic_route( $url ) : $url;
	}

	/**
	 * WoodMart uses this filtered URL as the base for per-page, grid/list,
	 * price-range and ordering links. Keep the complete query string WoodMart
	 * has already assembled, but replace its generic Shop path with this
	 * Landing Engine route. The filter is inert outside matched dynamic routes.
	 */
	public static function woodmart_shop_page_link( $url, $keep_query = false, $taxonomy = '' ) {
		unset( $keep_query, $taxonomy );
		return self::is_dynamic() ? self::rebase_to_dynamic_route( $url ) : $url;
	}

	/**
	 * Canonical base for controls/filters. Deliberately excludes /page/N/ so a
	 * changed filter, page-size or view starts from page one.
	 */
	public static function base_url() {
		$ctx = self::context();
		if ( ! $ctx ) {
			return '';
		}

		if ( 'global' === $ctx['context'] ) {
			return home_url( '/klockor/' . $ctx['child_slug'] . '/' );
		}

		return home_url( '/marken/' . $ctx['brand_slug'] . '/' . $ctx['child_slug'] . '/' );
	}

	/**
	 * Build the canonical Landing Engine route for a specific result page.
	 */
	public static function url_for_page( $page ) {
		$url = self::base_url();
		if ( ! $url ) {
			return '';
		}

		$page = max( 1, absint( $page ) );
		if ( $page > 1 ) {
			$url = trailingslashit( $url ) . 'page/' . $page . '/';
		}

		return $url;
	}

	/**
	 * Replace only the route base of a generated URL while preserving its
	 * query string and fragment byte-for-byte.
	 */
	private static function rebase_to_dynamic_route( $url ) {
		$base = self::base_url();
		if ( ! $base || ! is_string( $url ) || '' === $url ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		if ( false === $parts ) {
			return $url;
		}

		if ( ! empty( $parts['query'] ) ) {
			$base .= '?' . $parts['query'];
		}
		if ( ! empty( $parts['fragment'] ) ) {
			$base .= '#' . $parts['fragment'];
		}

		return $base;
	}

	public static function canonical_url() {
		$ctx = self::context();
		if ( ! $ctx ) {
			return '';
		}

		return self::url_for_page( $ctx['paged'] );
	}
}
