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
		);
		if ( ! $order ) {
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
		}
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
		$since = (int) get_option( 'urme_ss_ledger_since', 0 );
		$date  = $order->get_date_created();
		return $since && $date && $date->getTimestamp() < $since;
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
			self::UNTRACK  => array( 'Not tracked', 'Not tracked (no supplier-linked watches)', 'dashicons-minus' ),
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
		$returned = $line['ret_local'] + $line['ret_supplier'];
		printf(
			'<div class="urme-ff-line"><small>Fulfillment (internal):</small> %s%s%s</div>',
			self::badge( $line['status'] ), // phpcs:ignore WordPress.Security.EscapeOutput
			$parts ? ' <small>' . esc_html( implode( ' · ', $parts ) ) . '</small>' : '',
			$returned ? ' <small class="urme-ff-ret">' . esc_html( sprintf( '(%d returned/restocked: %d URME Lager, %d Dropshipping)', $returned, $line['ret_local'], $line['ret_supplier'] ) ) . '</small>' : ''
		);
		self::styles();
	}

	public static function render_order( $order ) {
		if ( ! self::allowed() ) {
			return;
		}
		$o = self::order( $order );
		echo '<p class="form-field form-field-wide urme-ff-order"><strong>Fulfillment (internal):</strong><br>' . self::badge( $o['status'], true ); // phpcs:ignore WordPress.Security.EscapeOutput
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
		$o = self::order( $order );
		echo in_array( $o['status'], array( self::LOCAL, self::DROPSHIP, self::MIXED, self::LEGACY ), true ) ? self::badge( $o['status'] ) : '<span class="urme-ff urme-ff-none">–</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
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
		echo '<style>.urme-ff{display:inline-flex;align-items:center;gap:2px;font-weight:600;white-space:nowrap}.urme-ff .dashicons{font-size:16px;width:16px;height:16px}.urme-ff-local{color:#7a4ec2}.urme-ff-dropship{color:#2271b1}.urme-ff-mixed{color:#8a6100}.urme-ff-legacy,.urme-ff-pending,.urme-ff-untracked,.urme-ff-none{color:#646970;font-weight:400}.urme-ff-line{margin-top:4px}</style>';
	}
}
