<?php
/**
 * Admin-only fulfilment source: "URME Lager" (own stock), "Dropshipping" (supplier) or "Mixed".
 *
 * Read from the allocation ledger, where the source is frozen when stock is taken for the
 * order. Nothing is stored on the order, so nothing can reach the customer: no order or
 * line meta, no notes. Output only happens through admin-screen hooks, for users who can
 * edit orders.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Fulfillment {

	const LOCAL    = 'local';
	const DROPSHIP = 'dropship';
	const MIXED    = 'mixed';
	const LEGACY   = 'legacy';
	const PENDING  = 'pending';
	const UNTRACK  = 'untracked';

	const FILTER = 'urme_fulfillment';

	/**
	 * Orders list: order ID => array( local, supplier, legacy_rows ), loaded in bulk for the visible page.
	 *
	 * @var array<int,int[]>
	 */
	private static $map = array();

	/**
	 * Query args of WooCommerce's HPOS orders list while it runs its query, so its results can be primed.
	 *
	 * @var array|null
	 */
	private static $listing = null;

	/* ---------------------------------------------------------------------
	 * Classification (from the ledger only)
	 * ------------------------------------------------------------------- */

	/**
	 * Source of one order line.
	 *
	 * @return array{status: string, local: int, supplier: int, ret_local: int, ret_supplier: int}
	 */
	public static function line( $item_id, $row = false ) {
		global $wpdb;
		if ( false === $row ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . URME_SS_DB::alloc_table() . ' WHERE order_item_id = %d', $item_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		}
		$out = array(
			'status'       => self::UNTRACK,
			'local'        => 0,
			'supplier'     => 0,
			'ret_local'    => 0,
			'ret_supplier' => 0,
			'from'         => '',
			'source_key'   => '',
		);
		if ( ! $row ) {
			return $out;
		}
		if ( 'legacy' === $row['origin'] ) {
			$out['status'] = self::LEGACY;
			return $out;
		}
		$out['local']        = (int) $row['src_local'];
		$out['supplier']     = (int) $row['src_supplier'];
		$out['ret_local']    = (int) $row['ret_local'];
		$out['ret_supplier'] = (int) $row['ret_supplier'];
		$out['from']         = $out['supplier'] ? URME_SS_Suppliers::id( $row['supplier'] ?? '' ) : '';
		$out['source_key']   = (string) ( $row['source_key'] ?? '' );
		$out['status']       = self::classify( $out['local'], $out['supplier'] );
		return $out;
	}

	private static function classify( $local, $supplier ) {
		if ( $local && $supplier ) {
			return self::MIXED;
		}
		if ( $local ) {
			return self::LOCAL;
		}
		return $supplier ? self::DROPSHIP : self::PENDING;
	}

	/**
	 * Source of a whole order.
	 *
	 * @return array{status: string, local: int, supplier: int, lines: array, untracked: int}
	 */
	public static function order( $order ) {
		global $wpdb;
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		$out   = array(
			'status'    => self::UNTRACK,
			'local'     => 0,
			'supplier'  => 0,
			'lines'     => array(),
			'untracked' => 0,
			'from'      => array(),
		);
		if ( ! $order instanceof WC_Order ) { // Not found, or a refund.
			return $out;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . URME_SS_DB::alloc_table() . ' WHERE order_id = %d', $order->get_id() ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$rows = array_column( $rows, null, 'order_item_id' );

		$legacy = 0;
		foreach ( $order->get_items() as $item_id => $item ) {
			$line                     = self::line( $item_id, $rows[ $item_id ] ?? null );
			$out['lines'][ $item_id ] = $line;
			$out['local']            += $line['local'];
			$out['supplier']         += $line['supplier'];
			$legacy                  += self::LEGACY === $line['status'] ? 1 : 0;
			$out['untracked']        += self::UNTRACK === $line['status'] ? 1 : 0;
			if ( $line['from'] ) {
				$out['from'][ $line['from'] ] = true;
			}
		}
		$out['from'] = array_keys( $out['from'] );
		if ( $out['local'] || $out['supplier'] ) {
			$out['status'] = self::classify( $out['local'], $out['supplier'] );
		} elseif ( $legacy || self::before_ledger( $order ) ) {
			$out['status'] = self::LEGACY;
		} elseif ( count( $out['lines'] ) > $out['untracked'] ) {
			$out['status'] = self::PENDING;
		}
		return $out;
	}

	private static function before_ledger( WC_Order $order ) {
		$date = $order->get_date_created();
		return self::ts_before_ledger( $date ? $date->getTimestamp() : 0 );
	}

	private static function ts_before_ledger( $ts ) {
		$since = (int) get_option( 'urme_ss_ledger_since', 0 );
		return $since && $ts && $ts < $since;
	}

	/**
	 * Load the ledger totals for many orders with ONE query (orders list).
	 *
	 * @param int[] $order_ids Orders on the current list page.
	 */
	public static function prime( array $order_ids ) {
		global $wpdb;
		$ids = array_values( array_diff( array_unique( array_filter( array_map( 'intval', $order_ids ) ) ), array_keys( self::$map ) ) );
		if ( ! $ids ) {
			return;
		}
		foreach ( $ids as $id ) {
			self::$map[ $id ] = array( 0, 0, 0, array() );
		}
		// Only lines still on the order count (same as order()); the join is on the items table's primary key.
		// phpcs:ignore WordPress.DB
		$rows = $wpdb->get_results(
			'SELECT a.order_id,
				SUM(CASE WHEN a.origin = \'sale\' THEN a.src_local ELSE 0 END) AS l,
				SUM(CASE WHEN a.origin = \'sale\' THEN a.src_supplier ELSE 0 END) AS s,
				SUM(CASE WHEN a.origin = \'legacy\' THEN 1 ELSE 0 END) AS g,
				GROUP_CONCAT(DISTINCT CASE WHEN a.origin = \'sale\' AND a.src_supplier > 0 THEN a.supplier END) AS f
			FROM ' . URME_SS_DB::alloc_table() . ' a
			INNER JOIN ' . $wpdb->prefix . 'woocommerce_order_items oi ON oi.order_item_id = a.order_item_id
			WHERE a.order_id IN (' . implode( ',', $ids ) . ') GROUP BY a.order_id',
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			self::$map[ (int) $r['order_id'] ] = array( (int) $r['l'], (int) $r['s'], (int) $r['g'], self::supplier_list( (string) $r['f'] ) );
		}
	}

	/**
	 * "relo,ila" (or '' for rows booked before 1.9.0: the main supplier) → known supplier IDs.
	 *
	 * @return string[]
	 */
	private static function supplier_list( $csv ) {
		$out = array();
		foreach ( explode( ',', $csv ) as $id ) {
			$out[ URME_SS_Suppliers::id( trim( $id ) ) ] = true;
		}
		return array_keys( $out );
	}

	/**
	 * Suppliers of an order on the list page (after list_status() primed it).
	 *
	 * @return string[]
	 */
	public static function list_suppliers( $order_id ) {
		return self::$map[ $order_id ][3] ?? array();
	}

	public static function flush_map() {
		self::$map = array();
	}

	/**
	 * Orders-list status from the primed map (no per-row queries).
	 *
	 * @param int $order_id   Order ID.
	 * @param int $created_ts Order creation time (already loaded by the list).
	 */
	public static function list_status( $order_id, $created_ts ) {
		if ( ! isset( self::$map[ $order_id ] ) ) {
			self::prime( array( $order_id ) ); // Not on a primed page: one query for this order.
		}
		list( $local, $supplier, $legacy ) = self::$map[ $order_id ];
		if ( $local || $supplier ) {
			return self::classify( $local, $supplier );
		}
		return ( $legacy || self::ts_before_ledger( $created_ts ) ) ? self::LEGACY : self::UNTRACK;
	}

	/* --- Priming hooks for the orders list ------------------------------- */

	public static function hpos_list_starts( $args ) {
		self::$listing = is_array( $args ) ? $args : null;
		return $args;
	}

	/**
	 * HPOS: WooCommerce passes the list's query results through this filter. Other order queries
	 * that run inside it (e.g. refunds primed for the page's orders) have a different type or limit.
	 */
	public static function hpos_prime( $results, $args = array() ) {
		$list = self::$listing;
		if ( ! $list || ! is_array( $args ) || empty( $args['paginate'] )
			|| ( $list['type'] ?? 'shop_order' ) !== ( $args['type'] ?? '' )
			|| (int) ( $list['limit'] ?? 0 ) !== (int) ( $args['limit'] ?? -1 ) ) {
			return $results;
		}
		self::$listing = null;
		$orders = is_object( $results ) && isset( $results->orders ) ? $results->orders : (array) $results;
		$ids    = array();
		foreach ( $orders as $o ) {
			$ids[] = $o instanceof WC_Abstract_Order ? $o->get_id() : (int) $o;
		}
		self::prime( $ids );
		return $results;
	}

	/**
	 * Legacy storage: the orders list is the main query on edit.php?post_type=shop_order.
	 */
	public static function legacy_prime( $posts, $query ) {
		if ( $posts && $query instanceof WP_Query && $query->is_main_query() && 'shop_order' === $query->get( 'post_type' ) && self::allowed() ) {
			self::prime( wp_list_pluck( $posts, 'ID' ) );
		}
		return $posts;
	}

	/**
	 * Order IDs with a given source, for the order list filter.
	 *
	 * @return int[]
	 */
	public static function order_ids( $status ) {
		global $wpdb;
		$where = array(
			self::LOCAL    => 'l > 0 AND s = 0',
			self::DROPSHIP => 's > 0 AND l = 0',
			self::MIXED    => 'l > 0 AND s > 0',
		);
		if ( ! isset( $where[ $status ] ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB
		return array_map( 'intval', $wpdb->get_col( 'SELECT order_id FROM (SELECT order_id, SUM(src_local) AS l, SUM(src_supplier) AS s FROM ' . URME_SS_DB::alloc_table() . " WHERE origin = 'sale' GROUP BY order_id) t WHERE " . $where[ $status ] ) );
	}

	/* ---------------------------------------------------------------------
	 * Admin output
	 * ------------------------------------------------------------------- */

	public static function init() {
		// Order edit screen (HPOS and legacy): per line and per order.
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'render_line' ), 10, 3 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_order' ) );
		// Order list, HPOS.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( __CLASS__, 'render_filter' ) );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( __CLASS__, 'filter_hpos' ) );
		// Bulk-load the fulfilment data of all visible orders (no per-row queries).
		// The type-specific filter is the last step before WooCommerce runs the list query.
		add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', array( __CLASS__, 'hpos_list_starts' ), PHP_INT_MAX );
		add_filter( 'woocommerce_order_query', array( __CLASS__, 'hpos_prime' ), 10, 2 );
		add_filter( 'the_posts', array( __CLASS__, 'legacy_prime' ), 10, 2 );
		// Order list, legacy posts storage.
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_filter_legacy' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_legacy' ) );
	}

	/**
	 * Only in wp-admin, only for people who can edit orders.
	 */
	private static function allowed() {
		return is_admin() && current_user_can( 'edit_shop_orders' );
	}

	private static function labels() {
		return array(
			self::LOCAL    => array( 'URME Lager', 'URME stock only', 'dashicons-store' ),
			self::DROPSHIP => array( 'Dropshipping', 'Dropshipping required', 'dashicons-car' ),
			self::MIXED    => array( 'Mixed', 'Mixed fulfillment', 'dashicons-randomize' ),
			self::LEGACY   => array( 'Unknown / Legacy order', 'Unknown / Legacy order', 'dashicons-backup' ),
			self::PENDING  => array( 'Not allocated yet', 'Not allocated yet (stock not taken)', 'dashicons-clock' ),
			self::UNTRACK  => array( 'Not tracked', 'Not tracked (no stock taken yet, or placed before 1.6.0)', 'dashicons-minus' ),
		);
	}

	private static function badge( $status, $long = false ) {
		$l = self::labels()[ $status ];
		return sprintf(
			'<span class="urme-ff urme-ff-%1$s"><span class="dashicons %2$s" aria-hidden="true"></span>%3$s</span>',
			esc_attr( $status ),
			esc_attr( $l[2] ),
			esc_html( $long ? $l[1] : $l[0] )
		);
	}

	public static function render_line( $item_id, $item, $product = null ) {
		if ( ! self::allowed() || ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$line = self::line( $item_id );
		if ( self::UNTRACK === $line['status'] ) {
			return; // Not a supplier-linked watch: nothing to say.
		}
		$parts = array();
		if ( $line['local'] ) {
			$parts[] = sprintf( 'URME Lager: %d', $line['local'] );
		}
		if ( $line['supplier'] ) {
			$parts[] = sprintf( 'Dropshipping: %d', $line['supplier'] );
		}
		$from = '';
		if ( $line['from'] ) {
			$ref  = self::supplier_ref( $line['source_key'] );
			$from = ' ' . URME_SS_Suppliers::flag( $line['from'], true ) . ( '' !== $ref ? ' <small>' . esc_html( 'Ref: ' . $ref ) . '</small>' : '' );
		}
		$returned = $line['ret_local'] + $line['ret_supplier'];
		printf(
			'<div class="urme-ff-line"><small>Fulfillment (internal):</small> %s%s%s%s</div>',
			self::badge( $line['status'] ), // phpcs:ignore WordPress.Security.EscapeOutput
			$from, // phpcs:ignore WordPress.Security.EscapeOutput -- flag() escapes.
			$parts ? ' <small>' . esc_html( implode( ' · ', $parts ) ) . '</small>' : '',
			$returned ? ' <small class="urme-ff-ret">' . esc_html( sprintf( '(%d returned/restocked: %d URME Lager, %d Dropshipping)', $returned, $line['ret_local'], $line['ret_supplier'] ) ) . '</small>' : ''
		);
		self::styles();
	}

	/**
	 * The supplier's own reference (product number) of the catalog row a line was bought from.
	 */
	private static function supplier_ref( $item_key ) {
		global $wpdb;
		if ( '' === (string) $item_key ) {
			return '';
		}
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT product_no FROM ' . URME_SS_DB::catalog_table() . ' WHERE item_key = %s', $item_key ) ); // phpcs:ignore WordPress.DB
	}

	private static function flags( array $ids, $name = false ) {
		return implode( ' ', array_map( static function ( $id ) use ( $name ) {
			return URME_SS_Suppliers::flag( $id, $name );
		}, $ids ) );
	}

	public static function render_order( $order ) {
		if ( ! self::allowed() ) {
			return;
		}
		$o = self::order( $order );
		echo '<p class="form-field form-field-wide urme-ff-order"><strong>Fulfillment (internal):</strong><br>' . self::badge( $o['status'], true ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $o['from'] ) {
			echo '<br>' . self::flags( $o['from'], true ); // phpcs:ignore WordPress.Security.EscapeOutput -- flag() escapes.
		}
		if ( $o['local'] || $o['supplier'] ) {
			echo ' <small>' . esc_html( sprintf( 'URME Lager: %d · Dropshipping: %d', $o['local'], $o['supplier'] ) ) . '</small>';
		}
		echo '</p>';
		self::styles();
	}

	public static function add_column( $columns ) {
		if ( ! self::allowed() ) {
			return $columns;
		}
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['urme_fulfillment'] = 'Fulfillment';
			}
		}
		if ( ! isset( $new['urme_fulfillment'] ) ) {
			$new['urme_fulfillment'] = 'Fulfillment';
		}
		return $new;
	}

	public static function render_column( $column, $order ) {
		if ( 'urme_fulfillment' !== $column || ! self::allowed() ) {
			return;
		}
		// HPOS passes the order object, legacy storage passes the post ID (post already loaded by the list).
		if ( $order instanceof WC_Abstract_Order ) {
			$id   = $order->get_id();
			$date = $order->get_date_created();
			$ts   = $date ? $date->getTimestamp() : 0;
		} else {
			$id = (int) $order;
			$ts = (int) strtotime( (string) get_post_field( 'post_date_gmt', $id ) . ' UTC' );
		}
		$status = self::list_status( $id, $ts );
		echo in_array( $status, array( self::LOCAL, self::DROPSHIP, self::MIXED, self::LEGACY ), true ) ? self::badge( $status ) : '<span class="urme-ff urme-ff-none">–</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( in_array( $status, array( self::DROPSHIP, self::MIXED ), true ) ) {
			echo ' ' . self::flags( self::list_suppliers( $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- flag() escapes.
		}
		self::styles();
	}

	private static function filter_value() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$v = sanitize_key( wp_unslash( $_GET[ self::FILTER ] ?? '' ) );
		return in_array( $v, array( self::LOCAL, self::DROPSHIP, self::MIXED ), true ) ? $v : '';
	}

	public static function render_filter( $order_type = 'shop_order' ) {
		if ( 'shop_order' !== $order_type || ! self::allowed() ) {
			return;
		}
		$current = self::filter_value();
		echo '<select name="' . esc_attr( self::FILTER ) . '" aria-label="Fulfillment">';
		foreach ( array( '' => 'All fulfillment', self::LOCAL => 'URME Lager', self::DROPSHIP => 'Dropshipping', self::MIXED => 'Mixed' ) as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	public static function render_filter_legacy( $post_type ) {
		if ( 'shop_order' === $post_type ) {
			self::render_filter( 'shop_order' );
		}
	}

	public static function filter_hpos( $args ) {
		$v = self::filter_value();
		if ( $v && self::allowed() ) {
			$ids              = self::order_ids( $v );
			$args['post__in'] = $ids ? $ids : array( 0 );
		}
		return $args;
	}

	public static function filter_legacy( $query ) {
		$v = self::filter_value();
		if ( ! $v || ! self::allowed() || ! $query->is_main_query() || 'shop_order' !== $query->get( 'post_type' ) ) {
			return;
		}
		$ids = self::order_ids( $v );
		$query->set( 'post__in', $ids ? $ids : array( 0 ) );
	}

	private static function styles() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		echo '<style>.urme-ff{display:inline-flex;align-items:center;gap:2px;font-weight:600;white-space:nowrap}.urme-ff .dashicons{font-size:16px;width:16px;height:16px}.urme-ff-local{color:#00701a}.urme-ff-dropship{color:#2271b1}.urme-ff-mixed{color:#8a6100}.urme-ff-legacy,.urme-ff-pending,.urme-ff-untracked,.urme-ff-none{color:#646970;font-weight:400}.urme-ff-line{margin-top:4px}.urme-ff-order .urme-flag{margin-right:6px}' . URME_SS_Suppliers::flag_css() . '</style>';
	}
}
