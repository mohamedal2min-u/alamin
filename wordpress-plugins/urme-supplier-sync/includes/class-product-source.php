<?php
/**
 * Fulfillment of a WooCommerce product, from the Supplier Sync link. Two states are shown:
 * Dropshipping (supplier stock synced) or URME Lager (everything else).
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
	 * Public product meta with the Fulfillment state (dropship / local / lager / paused /
	 * brand_off), for product feeds such as CTX Feed. No leading underscore so feed plugins list it.
	 */
	const META = 'urme_fulfillment';

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
		self::DROPSHIP => 'Dropshipping',
		self::LAGER    => 'URME Lager',
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
		// Quick Edit: "Move to URME Lager" above the stock quantity, for Dropshipping products.
		add_action( 'quick_edit_custom_box', array( __CLASS__, 'quick_edit_box' ), 10, 2 );
		add_action( 'woocommerce_product_quick_edit_save', array( __CLASS__, 'quick_edit_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'quick_edit_script' ) );
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
	 * Fulfillment state of this exact product or variation (its own link only, not its
	 * variations'): one small query per request, then served from the same map as the badges.
	 */
	public static function product_state( $product_id ) {
		$product_id = (int) $product_id;
		if ( ! $product_id ) {
			return self::LAGER;
		}
		if ( ! isset( self::$map[ $product_id ] ) ) {
			// Asked for one product of a shop / category page (gift wrap): load the whole page at once.
			self::prime( array_merge( array( $product_id ), self::page_products() ) );
		}
		return self::state( self::$map[ $product_id ]['own'] );
	}

	/**
	 * Store front: the products of the main query (shop, category, search) not loaded yet.
	 * Nothing in the admin, where the lists prime themselves.
	 *
	 * @return int[]
	 */
	private static function page_products() {
		if ( is_admin() || empty( $GLOBALS['wp_query'] ) || ! $GLOBALS['wp_query'] instanceof WP_Query ) {
			return array();
		}
		$ids = array();
		foreach ( (array) $GLOBALS['wp_query']->posts as $post ) {
			if ( $post instanceof WP_Post && 'product' === $post->post_type && ! isset( self::$map[ (int) $post->ID ] ) ) {
				$ids[] = (int) $post->ID;
			}
		}
		return $ids;
	}

	/**
	 * The Fulfillment state of these products or variations may have changed: their product pages
	 * are cleaned from caches through WordPress's own clean_post_cache (which page caches listen
	 * to), a variation's parent too. Nothing site-wide is purged.
	 *
	 * @param int[] $ids Product or variation IDs.
	 */
	public static function changed( array $ids ) {
		self::flush();
		foreach ( array_unique( array_filter( array_map( 'intval', $ids ) ) ) as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			clean_post_cache( $id );
			if ( 'product_variation' === $post->post_type && $post->post_parent ) {
				clean_post_cache( (int) $post->post_parent );
			}
		}
		self::write_meta( $ids );
	}

	/**
	 * Store the current Fulfillment state of these products or variations in the public
	 * `urme_fulfillment` meta. Written only when the value changes; links are loaded in bulk.
	 *
	 * @param int[] $ids Product or variation IDs.
	 * @return int Number of products whose meta changed.
	 */
	public static function write_meta( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$written = 0;
		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			self::prime( $chunk );
			update_meta_cache( 'post', $chunk );
			foreach ( $chunk as $id ) {
				$type = get_post_type( $id );
				if ( 'product' !== $type && 'product_variation' !== $type ) {
					continue;
				}
				$state = self::state( self::$map[ $id ]['own'] ?? null );
				if ( get_post_meta( $id, self::META, true ) !== $state ) {
					update_post_meta( $id, self::META, $state );
					++$written;
				}
			}
		}
		return $written;
	}

	/**
	 * Backfill: the `urme_fulfillment` meta for every product and variation (not in the trash).
	 *
	 * @return int Number of products whose meta changed.
	 */
	public static function rebuild_meta() {
		global $wpdb;
		self::flush();
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') AND post_status NOT IN ('trash','auto-draft')" ); // phpcs:ignore WordPress.DB
		return self::write_meta( array_map( 'intval', (array) $ids ) );
	}

	/**
	 * Brands turned on or off: the pages of their active Supplier-now products change state
	 * (Dropshipping / brand sync off); Paused and Local first do not depend on the brand.
	 *
	 * @param string[] $brand_keys Upper-cased brands.
	 */
	public static function brands_changed( array $brand_keys ) {
		global $wpdb;
		if ( ! $brand_keys ) {
			return;
		}
		$ids = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT l.product_id, c.manufacturer FROM ' . URME_SS_DB::links_table() . ' l INNER JOIN ' . URME_SS_DB::catalog_table() . ' c ON c.item_key = l.item_key WHERE l.product_id > 0 AND l.sync_enabled = 1 AND l.stock_mode <> %s', URME_SS_Inventory::LOCAL ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			if ( in_array( URME_SS_Settings::brand_key( $r['manufacturer'] ), $brand_keys, true ) ) {
				$ids[] = (int) $r['product_id'];
			}
		}
		if ( $ids ) {
			_prime_post_caches( $ids, false, false );
		}
		self::changed( $ids );
	}

	/**
	 * Badge HTML for a product or variation.
	 */
	public static function html( $product_id, $short = false ) {
		$product_id = (int) $product_id;
		if ( ! isset( self::$map[ $product_id ] ) ) {
			self::prime( array( $product_id ) ); // Not on a primed page: one query for this product.
		}
		$entry = self::$map[ $product_id ];
		if ( $entry['own'] || ! $entry['variations'] ) {
			return self::badge( $entry['own'], $short ) . self::styles();
		}
		// Variable product: the sources of its supplier-linked variations.
		$groups = array();
		foreach ( $entry['variations'] as $link ) {
			$html            = self::badge( $link, $short );
			$groups[ $html ] = ( $groups[ $html ] ?? 0 ) + 1;
		}
		$out = array();
		foreach ( $groups as $html => $n ) {
			$label = sprintf( '%d variation%s', $n, 1 === $n ? '' : 's' );
			$out[] = $html . ( $short
				? ' <small class="urme-src-note" title="' . esc_attr( $label ) . '">×' . (int) $n . '</small>'
				: ' <small class="urme-src-note">' . esc_html( $label ) . '</small>' );
		}
		return implode( '<br>', $out ) . self::styles();
	}

	/**
	 * Only two Fulfillment states are shown: Dropshipping, or URME Lager for everything else
	 * (not linked, Local first, Paused, supplier-linked with brand sync off).
	 */
	private static function badge( $link, $short = false ) {
		$drop = self::DROPSHIP === self::state( $link );
		$key  = $drop ? self::DROPSHIP : self::LAGER;
		$name = self::FILTERS[ $key ];
		// Products list: one letter (D / U); the full name on hover and for screen readers.
		$out = $short
			? sprintf( '<span class="urme-src urme-src-%1$s urme-src-short" title="%2$s"><span aria-hidden="true">%3$s</span><span class="screen-reader-text">%2$s</span></span>', $key, esc_attr( $name ), $drop ? 'D' : 'U' )
			: sprintf( '<span class="urme-src urme-src-%1$s">%2$s</span>', $key, esc_html( $name ) );
		if ( $drop && isset( $link['in_feed'] ) && ! (int) $link['in_feed'] ) {
			$out .= $short
				? ' <small class="urme-src-note urme-bad-note" title="not in supplier feed"><span aria-hidden="true">!</span><span class="screen-reader-text">not in supplier feed</span></small>'
				: ' <small class="urme-src-note">not in supplier feed</small>';
		}
		return $out;
	}

	private static function styles() {
		if ( self::$styled ) {
			return '';
		}
		self::$styled = true;
		// Base rules first, the compact / warning variants after them (same specificity: order decides).
		return '<style>.wp-list-table .column-urme_source{width:6em}.urme-src{display:inline-block;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:600;line-height:18px;white-space:nowrap}'
			. '.urme-src-dropship{background:#e5f0fa;color:#135e96}.urme-src-lager{background:#edfaef;color:#00701a}.urme-src-note{color:#646970}'
			. '.urme-src-short{min-width:12px;padding:1px 6px;text-align:center;cursor:help}.urme-src-note.urme-bad-note{color:#b32d2e;font-weight:700}</style>';
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
	 * WHERE fragment keeping only the products in one of the two fulfillment states. Dropshipping
	 * when it, or one of its variations, is; URME Lager when it is not Dropshipping, or has a
	 * linked variation that is not (a variable product with both is in both, one row each).
	 *
	 * @param string $state One of the FILTERS keys.
	 */
	public static function where_sql( $state ) {
		global $wpdb;
		$sql  = self::state_sql();
		$drop = $sql[ self::DROPSHIP ];
		unset( $sql[ self::DROPSHIP ] );
		$sel = 'SELECT ' . self::owner_col() . ' ' . self::owner_sql();
		if ( self::DROPSHIP === $state ) {
			return " AND {$wpdb->posts}.ID IN ({$sel} AND ({$drop}))";
		}
		if ( self::LAGER === $state ) {
			// Not Dropshipping, or with a variation that is not (a mixed variable product is in both).
			return " AND ({$wpdb->posts}.ID NOT IN ({$sel} AND ({$drop})) OR {$wpdb->posts}.ID IN ({$sel} AND ((" . implode( ') OR (', $sql ) . '))))';
		}
		return '';
	}

	/**
	 * Products (list rows) per fulfillment state, from the current links (not cached): one
	 * aggregate query, plus WordPress's own post counts. URME Lager = all products minus those
	 * that are only Dropshipping; a variable product with both kinds of variation counts in both.
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
		$drop  = $sql[ self::DROPSHIP ];
		unset( $sql[ self::DROPSHIP ] );
		$owner = self::owner_col();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB
			"SELECT SUM(d) AS n_dropship, SUM(CASE WHEN d = 1 AND o = 0 THEN 1 ELSE 0 END) AS n_dropship_only FROM (
				SELECT {$owner} AS owner, MAX(CASE WHEN {$drop} THEN 1 ELSE 0 END) AS d, MAX(CASE WHEN (" . implode( ') OR (', $sql ) . ') THEN 1 ELSE 0 END) AS o ' . self::owner_sql() . "
				AND EXISTS (SELECT 1 FROM {$wpdb->posts} p2 WHERE p2.ID = {$owner} AND p2.post_type = 'product' AND p2.post_status IN ({$in}))
				GROUP BY {$owner}
			) t",
			ARRAY_A
		);
		$all = 0;
		foreach ( (array) wp_count_posts( 'product' ) as $status => $n ) {
			if ( in_array( $status, $statuses, true ) ) {
				$all += (int) $n;
			}
		}
		return array(
			'all'          => $all,
			self::DROPSHIP => (int) ( $row['n_dropship'] ?? 0 ),
			self::LAGER    => max( 0, $all - (int) ( $row['n_dropship_only'] ?? 0 ) ),
		);
	}

	/* --- WooCommerce > Products ------------------------------------------ */

	private static function current_filter() {
		$value = isset( $_GET['urme_fulfillment'] ) ? sanitize_key( wp_unslash( $_GET['urme_fulfillment'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		return isset( self::FILTERS[ $value ] ) ? $value : '';
	}

	/**
	 * Counts for the dropdown from the products list's own query, so they follow every other
	 * active filter (stock status, brand, category, product type, search, post status): the list
	 * SQL without its paging, ordering and Fulfillment condition, counted once per state.
	 * Null when the list query is not available (the dropdown then shows the overall counts).
	 *
	 * @return array<string, int>|null
	 */
	private static function list_counts() {
		global $wp_query, $wpdb;
		if ( ! ( $wp_query instanceof WP_Query ) || 'product' !== $wp_query->get( 'post_type' ) || ! is_string( $wp_query->request ) || '' === $wp_query->request ) {
			return null;
		}
		$sql   = $wp_query->request;
		$state = (string) $wp_query->get( 'urme_fulfillment' );
		if ( '' !== $state && isset( self::FILTERS[ $state ] ) ) {
			$own = self::where_sql( $state );
			if ( false === strpos( $sql, $own ) ) {
				return null;
			}
			$sql = str_replace( $own, '', $sql );
		}
		$sql = str_replace( 'SQL_CALC_FOUND_ROWS', '', $sql );
		$sql = preg_replace( '/\s+LIMIT\s+\d+(\s*,\s*\d+)?\s*$/i', '', $sql );
		$pos = strripos( $sql, ' ORDER BY ' );
		if ( false !== $pos && false === strpos( substr( $sql, $pos ), ')' ) ) {
			$sql = substr( $sql, 0, $pos );
		}
		$base = "SELECT COUNT(DISTINCT t.ID) FROM ({$sql}) t";
		$quiet = $wpdb->suppress_errors( true );
		$out   = array( 'all' => $wpdb->get_var( $base ) ); // phpcs:ignore WordPress.DB
		foreach ( array_keys( self::FILTERS ) as $value ) {
			$cond          = preg_replace( '/^\s*AND\s+/i', '', str_replace( "{$wpdb->posts}.ID", 't.ID', self::where_sql( $value ) ) );
			$out[ $value ] = $wpdb->get_var( "{$base} WHERE {$cond}" ); // phpcs:ignore WordPress.DB
		}
		$wpdb->suppress_errors( $quiet );
		foreach ( $out as $n ) {
			if ( null === $n ) {
				return null; // A query failed: fall back to the overall counts.
			}
		}
		return array_map( 'intval', $out );
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
		$counts = self::list_counts();
		if ( null === $counts ) {
			$status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$counts = self::counts( ( '' !== $status && 'all' !== $status && get_post_status_object( $status ) ) ? array( $status ) : array() );
		}
		$current  = self::current_filter();
		$out      = '<label for="urme-fulfillment-filter" class="screen-reader-text">Filter by fulfillment</label>';
		$out     .= '<select name="urme_fulfillment" id="urme-fulfillment-filter" title="Counts are products and follow the other filters (stock status, brand, category, search…). A variable product with both Dropshipping and URME Lager variations is counted under both."><option value="">' . esc_html( sprintf( 'Fulfillment: All (%d)', $counts['all'] ) ) . '</option>';
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
			echo self::html( (int) $post_id, true ); // phpcs:ignore WordPress.Security.EscapeOutput
			$own = self::$map[ (int) $post_id ]['own'] ?? null;
			if ( $own && self::DROPSHIP === self::state( $own ) ) {
				// Read by assets/quick-edit.js: this product may be moved to URME Lager in Quick Edit.
				echo '<span class="hidden urme-qe-dropship"></span>';
			}
		}
	}

	/**
	 * Quick Edit field (moved above "Stock qty" by assets/quick-edit.js, shown only for a
	 * Dropshipping product). Ticking it empties the stock field for URME's own count.
	 */
	public static function quick_edit_box( $column, $post_type ) {
		if ( 'urme_source' !== $column || 'product' !== $post_type || ! self::allowed() ) {
			return;
		}
		echo '<div class="urme-qe-lager" style="display:none;clear:both;width:100%;padding:6px 0 8px">'
			. '<label style="display:inline-flex;align-items:center;gap:6px"><input type="checkbox" name="urme_ss_to_lager" value="1">'
			. '<span><strong>Move to URME Lager</strong> (stop Dropshipping)</span></label>'
			. '<div class="description" style="margin:2px 0 0 24px;font-size:12px">Enter your own stock in Stock qty below (empty = 0, out of stock).</div>'
			. '</div>';
	}

	/**
	 * Quick Edit save (after WooCommerce saved its own fields and verified its nonce): a
	 * Dropshipping product leaves supplier sync; its stock becomes the typed Stock qty.
	 */
	public static function quick_edit_save( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- WooCommerce verified woocommerce_quick_edit_nonce before this action.
		if ( empty( $_REQUEST['urme_ss_to_lager'] ) || ! $product instanceof WC_Product || ! self::allowed() || $product->is_type( 'variable' ) ) {
			return;
		}
		$qty = isset( $_REQUEST['_stock'] ) && is_numeric( wp_unslash( $_REQUEST['_stock'] ) ) ? (int) wc_stock_amount( wp_unslash( $_REQUEST['_stock'] ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		// phpcs:enable
		unset( self::$map[ $product->get_id() ] ); // Re-read: act only on the current state, never on a stale form.
		$link = URME_SS_DB::link_for_product( $product->get_id() );
		if ( ! $link || self::DROPSHIP !== self::product_state( $product->get_id() ) ) {
			return;
		}
		$ok = URME_SS_Inventory::return_to_lager( (int) $link['id'], $qty );
		if ( true === $ok ) {
			URME_SS_Log::info( sprintf( 'Quick Edit: product #%d moved to URME Lager with stock %d.', $product->get_id(), max( 0, $qty ) ) );
		}
	}

	public static function quick_edit_script( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( 'edit.php' !== $hook || ! $screen || 'product' !== $screen->post_type || ! self::allowed() ) {
			return;
		}
		wp_enqueue_script( 'urme-ss-quick-edit', URME_SS_URL . 'assets/quick-edit.js', array( 'jquery', 'inline-edit-post' ), URME_SS_VERSION, true );
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
