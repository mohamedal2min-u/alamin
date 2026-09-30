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

	/**
	 * Fulfillment filter values on WooCommerce > Products.
	 */
	const FILTERS = array(
		self::DROPSHIP  => 'Dropshipping',
		self::LAGER     => 'URME Lager',
		self::LOCAL     => 'Local first',
		self::PAUSED    => 'Paused',
		self::BRAND_OFF => 'Supplier – brand sync off',
	);

	/**
	 * Supplier-linked manufacturers whose brand sync is on (one query per request).
	 *
	 * @var string[]|null
	 */
	private static $enabled_makers = null;

	public static function init() {
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'the_posts', array( __CLASS__, 'prime_list' ), 10, 2 );
		add_filter( 'woocommerce_products_admin_list_table_filters', array( __CLASS__, 'add_filter_dropdown' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'read_filter' ) );
		add_filter( 'posts_where', array( __CLASS__, 'filter_where' ), 10, 2 );
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
		self::$map            = array();
		self::$enabled_makers = null;
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

	/* --- Fulfillment in SQL (same rules as state()) -------------------------- */

	/**
	 * SQL conditions on a link row "l" (joined with its catalog row "c") for each state, exactly
	 * as state() decides it: Paused = sync off; Local first = sync on + Local first mode;
	 * Dropshipping = sync on + Supplier now mode + brand sync on; brand sync off = sync on +
	 * Supplier now mode + brand sync off. Every link is in exactly one. Never from stock.
	 *
	 * @return array<string, string>
	 */
	private static function state_sql() {
		global $wpdb;
		if ( null === self::$enabled_makers ) {
			// The brand check is URME_SS_Settings::brand_enabled() itself, on the few distinct linked brands.
			$makers               = (array) $wpdb->get_col( 'SELECT DISTINCT c.manufacturer FROM ' . URME_SS_DB::links_table() . ' l INNER JOIN ' . URME_SS_DB::catalog_table() . ' c ON c.item_key = l.item_key WHERE l.product_id > 0' ); // phpcs:ignore WordPress.DB
			self::$enabled_makers = array_values( array_filter( $makers, array( 'URME_SS_Settings', 'brand_enabled' ) ) );
		}
		$local = esc_sql( URME_SS_Inventory::LOCAL );
		$brand = self::$enabled_makers
			? 'COALESCE(c.manufacturer, \'\') IN (' . $wpdb->prepare( implode( ',', array_fill( 0, count( self::$enabled_makers ), '%s' ) ), self::$enabled_makers ) . ')' // phpcs:ignore WordPress.DB
			: '1 = 0';
		return array(
			self::PAUSED   => 'l.sync_enabled = 0',
			self::LOCAL    => "l.sync_enabled <> 0 AND l.stock_mode = '{$local}'",
			self::DROPSHIP  => "l.sync_enabled <> 0 AND l.stock_mode <> '{$local}' AND {$brand}",
			self::BRAND_OFF => "l.sync_enabled <> 0 AND l.stock_mode <> '{$local}' AND NOT ({$brand})",
		);
	}

	/**
	 * Supplier links with the product each belongs to in the products list: a variation's link
	 * belongs to its parent product. Links to deleted products are left out.
	 */
	private static function owner_sql() {
		global $wpdb;
		return 'FROM ' . URME_SS_DB::links_table() . " l
			INNER JOIN {$wpdb->posts} lp ON lp.ID = l.product_id
			LEFT JOIN " . URME_SS_DB::catalog_table() . ' c ON c.item_key = l.item_key
			WHERE l.product_id > 0';
	}

	private static function owner_col() {
		return "CASE WHEN lp.post_type = 'product_variation' THEN lp.post_parent ELSE lp.ID END";
	}

	/**
	 * WHERE fragment keeping only the products in one fulfillment state. A product is in a
	 * supplier state (Dropshipping, Local first, Paused, brand sync off) when it, or one of its
	 * variations, is; URME Lager when neither it nor any of its variations is supplier-linked.
	 * A variable product whose variations are in different states is in each of those states
	 * (still one row per filter).
	 *
	 * @param string $state One of the FILTERS keys.
	 */
	public static function where_sql( $state ) {
		global $wpdb;
		$sql = self::state_sql();
		if ( self::LAGER === $state ) {
			return " AND {$wpdb->posts}.ID NOT IN (SELECT " . self::owner_col() . ' ' . self::owner_sql() . ' AND ((' . implode( ') OR (', $sql ) . ')))';
		}
		if ( ! isset( $sql[ $state ] ) ) {
			return '';
		}
		return " AND {$wpdb->posts}.ID IN (SELECT " . self::owner_col() . ' ' . self::owner_sql() . " AND ({$sql[ $state ]}))";
	}

	/**
	 * Products (list rows) per fulfillment state, from the current links (not cached): one
	 * aggregate query, plus WordPress's own post counts. URME Lager = all products minus the
	 * supplier-linked ones. A product is counted once per state; a variable product whose
	 * variations are in different states is counted in each of them.
	 *
	 * @param string[] $statuses Post statuses counted (default: those of the "All" view).
	 * @return array<string, int>
	 */
	public static function counts( array $statuses = array() ) {
		global $wpdb;
		if ( ! $statuses ) {
			$statuses = get_post_stati( array( 'show_in_admin_all_list' => true ) );
		}
		$in    = $wpdb->prepare( implode( ',', array_fill( 0, count( $statuses ), '%s' ) ), array_values( $statuses ) ); // phpcs:ignore WordPress.DB
		$sql   = self::state_sql();
		$owner = self::owner_col();
		$cols  = array();
		foreach ( $sql as $state => $cond ) {
			$cols[] = "COUNT(DISTINCT CASE WHEN {$cond} THEN {$owner} END) AS n_{$state}";
		}
		$cols[] = 'COUNT(DISTINCT CASE WHEN (' . implode( ') OR (', $sql ) . ") THEN {$owner} END) AS n_active";
		$row    = $wpdb->get_row( // phpcs:ignore WordPress.DB
			'SELECT ' . implode( ', ', $cols ) . ' ' . self::owner_sql() . "
			AND EXISTS (SELECT 1 FROM {$wpdb->posts} o WHERE o.ID = {$owner} AND o.post_type = 'product' AND o.post_status IN ({$in}))",
			ARRAY_A
		);
		$all = 0;
		foreach ( (array) wp_count_posts( 'product' ) as $status => $n ) {
			if ( in_array( $status, $statuses, true ) ) {
				$all += (int) $n;
			}
		}
		return array(
			'all'           => $all,
			self::DROPSHIP  => (int) ( $row[ 'n_' . self::DROPSHIP ] ?? 0 ),
			self::LAGER     => max( 0, $all - (int) ( $row['n_active'] ?? 0 ) ),
			self::LOCAL     => (int) ( $row[ 'n_' . self::LOCAL ] ?? 0 ),
			self::PAUSED    => (int) ( $row[ 'n_' . self::PAUSED ] ?? 0 ),
			self::BRAND_OFF => (int) ( $row[ 'n_' . self::BRAND_OFF ] ?? 0 ),
		);
	}

	/* --- WooCommerce > Products ------------------------------------------ */

	private static function current_filter() {
		$value = isset( $_GET['urme_fulfillment'] ) ? sanitize_key( wp_unslash( $_GET['urme_fulfillment'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		return isset( self::FILTERS[ $value ] ) ? $value : '';
	}

	/**
	 * The Fulfillment dropdown, in WooCommerce's own filter row (after the stock status filter).
	 */
	public static function add_filter_dropdown( $filters ) {
		if ( self::allowed() ) {
			$filters['urme_fulfillment'] = array( __CLASS__, 'render_filter_dropdown' );
		}
		return $filters;
	}

	public static function render_filter_dropdown() {
		$status   = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$counts   = self::counts( ( '' !== $status && 'all' !== $status && get_post_status_object( $status ) ) ? array( $status ) : array() );
		$current  = self::current_filter();
		$out      = '<label for="urme-fulfillment-filter" class="screen-reader-text">Filter by fulfillment</label>';
		$out     .= '<select name="urme_fulfillment" id="urme-fulfillment-filter" title="Counts are products. A variable product whose variations are fulfilled differently is counted under each of those states."><option value="">' . esc_html( sprintf( 'Fulfillment: All (%d)', $counts['all'] ) ) . '</option>';
		foreach ( self::FILTERS as $value => $label ) {
			$out .= '<option value="' . esc_attr( $value ) . '"' . selected( $value, $current, false ) . '>' . esc_html( sprintf( '%s (%d)', $label, $counts[ $value ] ) ) . '</option>';
		}
		echo $out . '</select>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
	}

	/**
	 * The products list's own query gets the filter as a query var (so paging, sorting, the item
	 * count and WooCommerce's other filters all apply to the same query).
	 */
	public static function read_filter( $query ) {
		global $pagenow;
		if ( is_admin() && 'edit.php' === $pagenow && $query instanceof WP_Query && $query->is_main_query() && 'product' === $query->get( 'post_type' ) && self::allowed() ) {
			$state = self::current_filter();
			if ( '' !== $state ) {
				$query->set( 'urme_fulfillment', $state );
			}
		}
	}

	public static function filter_where( $where, $query ) {
		$state = $query instanceof WP_Query ? (string) $query->get( 'urme_fulfillment' ) : '';
		return ( '' !== $state && isset( self::FILTERS[ $state ] ) ) ? $where . self::where_sql( $state ) : $where;
	}


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
