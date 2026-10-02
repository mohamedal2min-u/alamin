<?php
/**
 * Order ledger for the admin-only "URME Lager / Dropshipping" labels, and the few stock rules
 * the Supplier catalog uses.
 *
 * WooCommerce keeps, per order line, how many units it has taken from stock (`_reduced_stock`).
 * Every stock movement (payment, cancellation, refund with restock, admin quantity edit) ends
 * with that line being saved. On each save the difference with the ledger row is booked:
 *
 *  - units taken    → Dropshipping if the watch is Dropshipping at that moment, else URME Lager;
 *  - units returned → counted as returns (URME Lager first); the label of the sale stays.
 *
 * The source is frozen at sale time (src_local / src_supplier). Nothing is written to the order
 * or its lines, so none of this can reach customers, emails, invoices or the REST API.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Inventory {

	const LOCAL    = 'local_first'; // Links of versions before 1.5.2 only (removed once on update).
	const SUPPLIER = 'supplier';

	/**
	 * Per request: order line ID => `_reduced_stock` value already booked (or irrelevant).
	 * WooCommerce saves a line many times per checkout; unchanged saves need no database work.
	 *
	 * @var array<int,int>
	 */
	private static $seen = array();

	public static function flush_cache() {
		self::$seen = array();
	}

	public static function init() {
		add_action( 'woocommerce_new_order_item', array( __CLASS__, 'on_item_saved' ), 20, 3 );
		add_action( 'woocommerce_update_order_item', array( __CLASS__, 'on_item_saved' ), 20, 3 );
	}

	/**
	 * Hook: any order line was saved.
	 */
	public static function on_item_saved( $item_id, $item = null, $order_id = 0 ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$item_id = (int) $item_id;
		$reduced = (int) wc_stock_amount( $item->get_meta( '_reduced_stock', true ) );
		if ( isset( self::$seen[ $item_id ] ) && self::$seen[ $item_id ] === $reduced ) {
			return; // Saved again with the count this request already booked.
		}
		// Nothing taken from stock and never booked (e.g. an unpaid order): nothing to do.
		if ( 0 === $reduced && ! self::in_ledger( $item_id ) ) {
			self::$seen[ $item_id ] = 0;
			return;
		}
		// A failed booking (null) is not remembered, so the next save retries it.
		if ( null !== self::reconcile_item( $item_id ) ) {
			self::$seen[ $item_id ] = $reduced;
		}
	}

	private static function in_ledger( $item_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . URME_SS_DB::alloc_table() . ' WHERE order_item_id = %d', $item_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Bring the ledger row of one order line in line with WooCommerce's `_reduced_stock`.
	 * One guarded write (compare-and-swap on the booked count), so a repeated or concurrent save
	 * never books a unit twice.
	 *
	 * @return bool|null True if something was booked, false if nothing to do, null on error.
	 */
	public static function reconcile_item( $item_id ) {
		global $wpdb;
		$facts = self::item_facts( $item_id );
		if ( ! $facts ) {
			return false;
		}
		$table = URME_SS_DB::alloc_table();
		for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
			$alloc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_item_id = %d", $item_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
			$delta = $facts['reduced'] - ( $alloc ? (int) $alloc['last_reduced_stock'] : 0 );
			if ( 0 === $delta || ( ! $alloc && $delta < 0 ) ) {
				return false;
			}
			$now  = current_time( 'mysql', true );
			$book = self::book( $delta, $alloc, $facts['product_id'] );
			if ( ! $alloc ) {
				$ok = $wpdb->query( // phpcs:ignore WordPress.DB
					$wpdb->prepare(
						"INSERT IGNORE INTO {$table} (order_item_id, order_id, link_id, product_id, last_reduced_stock, local_allocated, supplier_allocated, src_local, src_supplier, ret_local, ret_supplier, origin, created_at, updated_at) VALUES (%d, %d, %d, %d, %d, %d, %d, %d, %d, 0, 0, %s, %s, %s)",
						$item_id,
						$facts['order_id'],
						$book['link_id'],
						$facts['product_id'],
						$facts['reduced'],
						$book['local_allocated'],
						$book['supplier_allocated'],
						$book['src_local'],
						$book['src_supplier'],
						self::origin_for_order( $facts['order_id'] ),
						$now,
						$now
					)
				);
			} else {
				$ok = $wpdb->update(
					$table,
					array(
						'last_reduced_stock' => $facts['reduced'],
						'local_allocated'    => $book['local_allocated'],
						'supplier_allocated' => $book['supplier_allocated'],
						'src_local'          => $book['src_local'],
						'src_supplier'       => $book['src_supplier'],
						'ret_local'          => $book['ret_local'],
						'ret_supplier'       => $book['ret_supplier'],
						'updated_at'         => $now,
					),
					array(
						'order_item_id'      => (int) $item_id,
						'last_reduced_stock' => (int) $alloc['last_reduced_stock'],
					)
				);
			}
			if ( 1 === (int) $ok ) {
				if ( $delta > 0 ) {
					self::note_sale( $facts, $book, $delta );
				}
				return true;
			}
			// Booked by a concurrent save meanwhile (or a database error): read again once.
		}
		URME_SS_Log::error( sprintf( 'Order line #%d (order #%d): its fulfillment could not be recorded; it is retried on the next save of the line.', $item_id, $facts['order_id'] ) );
		return null;
	}

	/**
	 * Private order note "URME: Dropshipping – SKU × 1" when units are taken, so the source shows in
	 * the WooCommerce mobile app. Private: never on the customer's emails, My Account or invoices.
	 */
	private static function note_sale( array $facts, array $book, $delta ) {
		if ( ! URME_SS_Settings::get( 'order_note' ) ) {
			return;
		}
		$order = wc_get_order( $facts['order_id'] );
		if ( ! $order ) {
			return;
		}
		$product = wc_get_product( $facts['product_id'] );
		$sku     = $product ? ( $product->get_sku() ? $product->get_sku() : $product->get_name() ) : '#' . $facts['product_id'];
		$source  = $book['src_supplier'] > 0 && $book['supplier_allocated'] >= $delta ? 'Dropshipping' : 'URME Lager';
		$order->add_order_note( sprintf( 'URME: %s – %s × %d', $source, $sku, $delta ), 0, false );
	}

	/**
	 * The new ledger values for a change of `_reduced_stock`.
	 *
	 * @param int        $delta      Units taken (> 0) or given back (< 0).
	 * @param array|null $alloc      Current ledger row.
	 * @param int        $product_id Product or variation of the line.
	 */
	private static function book( $delta, $alloc, $product_id ) {
		$b = array(
			'link_id'            => (int) ( $alloc['link_id'] ?? 0 ),
			'local_allocated'    => (int) ( $alloc['local_allocated'] ?? 0 ),
			'supplier_allocated' => (int) ( $alloc['supplier_allocated'] ?? 0 ),
			'src_local'          => (int) ( $alloc['src_local'] ?? 0 ),
			'src_supplier'       => (int) ( $alloc['src_supplier'] ?? 0 ),
			'ret_local'          => (int) ( $alloc['ret_local'] ?? 0 ),
			'ret_supplier'       => (int) ( $alloc['ret_supplier'] ?? 0 ),
		);
		if ( $delta > 0 ) {
			// Units taken: the watch's state now decides (Dropshipping, else URME Lager).
			$drop = URME_SS_Product_Source::DROPSHIP === URME_SS_Product_Source::product_state( $product_id );
			if ( $drop && ! $b['link_id'] ) {
				$link         = URME_SS_DB::link_for_product( $product_id );
				$b['link_id'] = $link ? (int) $link['id'] : 0;
			}
			if ( 0 === $b['local_allocated'] + $b['supplier_allocated'] ) {
				// A new sale of this line (first sale, or sold again after a full cancellation).
				$b['src_local']    = 0;
				$b['src_supplier'] = 0;
				$b['ret_local']    = 0;
				$b['ret_supplier'] = 0;
			}
			$b[ $drop ? 'supplier_allocated' : 'local_allocated' ] += $delta;
			$b[ $drop ? 'src_supplier' : 'src_local' ]             += $delta;
			return $b;
		}
		// Units given back (cancel, refund with restock, quantity lowered): URME Lager first.
		$back                     = -$delta;
		$back_local               = min( $back, $b['local_allocated'] );
		$back_supplier            = min( $back - $back_local, $b['supplier_allocated'] );
		$b['local_allocated']    -= $back_local;
		$b['supplier_allocated'] -= $back_supplier;
		$b['ret_local']          += $back_local;
		$b['ret_supplier']       += $back_supplier;
		return $b;
	}

	/**
	 * Current facts for an order line, read straight from the database.
	 *
	 * @return array{order_id: int, product_id: int, reduced: int}|null
	 */
	private static function item_facts( $item_id ) {
		global $wpdb;
		$items = $wpdb->prefix . 'woocommerce_order_items';
		$meta  = $wpdb->prefix . 'woocommerce_order_itemmeta';
		// phpcs:disable WordPress.DB
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT order_id, order_item_type FROM {$items} WHERE order_item_id = %d", $item_id ), ARRAY_A );
		if ( ! $row || 'line_item' !== $row['order_item_type'] ) {
			return null;
		}
		$values = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$meta} WHERE order_item_id = %d AND meta_key IN ('_product_id','_variation_id','_reduced_stock')", $item_id ), ARRAY_A );
		// phpcs:enable
		$m = wp_list_pluck( $values, 'meta_value', 'meta_key' );
		return array(
			'order_id'   => (int) $row['order_id'],
			'product_id' => (int) ( ! empty( $m['_variation_id'] ) ? $m['_variation_id'] : ( $m['_product_id'] ?? 0 ) ),
			'reduced'    => max( 0, (int) wc_stock_amount( $m['_reduced_stock'] ?? 0 ) ),
		);
	}

	/**
	 * 'legacy' for orders placed before the ledger existed: their source is unknown, never guessed.
	 */
	private static function origin_for_order( $order_id ) {
		$since = (int) get_option( 'urme_ss_ledger_since', 0 );
		$order = $since ? wc_get_order( $order_id ) : null;
		$date  = $order ? $order->get_date_created() : null;
		return ( $date && $date->getTimestamp() < $since ) ? 'legacy' : 'sale';
	}

	/**
	 * Admin: back to URME Lager. The watch leaves supplier sync (its link is removed) and its
	 * WooCommerce stock becomes $qty (default 0, out of stock until the admin enters the real
	 * stock); the supplier quantity is never kept. Prices and cost are not touched.
	 *
	 * @param int $link_id Link ID.
	 * @param int $qty     URME's own stock (Quick Edit passes the typed count).
	 * @return true|string True or an error message.
	 */
	public static function return_to_lager( $link_id, $qty = 0 ) {
		$qty  = max( 0, (int) $qty );
		$link = URME_SS_DB::get_link_by_id( $link_id );
		if ( ! $link ) {
			return 'Selection not found.';
		}
		$product = (int) $link['product_id'] ? wc_get_product( (int) $link['product_id'] ) : null;
		if ( $product && $product->is_type( 'variable' ) ) {
			return 'A variable parent product has no stock of its own; nothing was changed.';
		}
		URME_SS_DB::delete_link( (int) $link['id'] );
		if ( $product ) {
			if ( true !== $product->get_manage_stock() ) {
				$product->set_manage_stock( true );
				$product->save();
			}
			wc_update_product_stock( $product, $qty, 'set' );
		}
		URME_SS_Log::info(
			$qty
				? sprintf( 'Back to URME Lager: product #%d (%s) removed from supplier sync; URME stock %d.', $link['product_id'], $link['item_key'], $qty )
				: sprintf( 'Back to URME Lager: product #%d (%s) removed from supplier sync; stock 0 (out of stock) until the real stock is entered.', $link['product_id'], $link['item_key'] )
		);
		return true;
	}

	/**
	 * Once (1.5.2): watches that are URME Lager (Local first, paused, or without a product) are
	 * removed from supplier sync. Their WooCommerce stock, cost and prices stay as they are.
	 */
	public static function remove_lager_links() {
		global $wpdb;
		$ids = array_map( 'intval', (array) $wpdb->get_col( 'SELECT id FROM ' . URME_SS_DB::links_table() . " WHERE product_id = 0 OR stock_mode = 'local_first' OR sync_enabled = 0" ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			URME_SS_DB::delete_link( $id );
		}
		if ( $ids ) {
			URME_SS_Log::info( sprintf( '%d URME Lager watch(es) removed from supplier sync (1.5.2); their stock, cost and prices were not changed.', count( $ids ) ) );
		}
		return count( $ids );
	}

	/**
	 * URME Lager stock always has priority over Dropshipping: the product's current WooCommerce
	 * stock (for a stock managed by the parent product, the parent's stock). No order history is
	 * used: WooCommerce's stock is the local count.
	 *
	 * @param array|null      $link    Supplier link (unused; kept for callers).
	 * @param WC_Product|null $product The WooCommerce product or variation.
	 */
	public static function local_units_before_supplier( $link, $product ) {
		$woo = 0;
		if ( $product instanceof WC_Product ) {
			$by    = (int) $product->get_stock_managed_by_id();
			$stock = ( $by && $by !== $product->get_id() ) ? wc_get_product( $by ) : $product;
			if ( $stock && true === $stock->get_manage_stock() ) {
				$woo = max( 0, (int) $stock->get_stock_quantity() );
			}
		}
		return $woo;
	}

	/**
	 * URME units on a Dropshipping link: the stock above what supplier sync last set (or any
	 * stock when it never set one), else 0.
	 *
	 * @param array $link Supplier link.
	 * @param int   $woo  Current WooCommerce stock (0 when not managed).
	 */
	private static function extra_units( array $link, $woo ) {
		if ( self::LOCAL === ( $link['stock_mode'] ?? '' ) || (int) $woo < 1 ) {
			return 0;
		}
		return ( null === $link['last_stock'] || (int) $woo > (int) $link['last_stock'] ) ? (int) $woo : 0;
	}

	/**
	 * A brand is turned on again: its Supplier-now links would start writing supplier stock and
	 * cost at the next sync. Each one is checked before the brand is saved; a link whose product
	 * has local URME stock (stock above what supplier sync last set) is URME Lager and is removed
	 * from supplier sync, so nothing is written to it. Stock is read in bulk (constant queries for any number of links).
	 *
	 * @param string[] $brand_keys Upper-cased brands being enabled.
	 * @return string[] The watches removed from supplier sync, for the admin notice.
	 */
	public static function hold_on_brand_enable( array $brand_keys ) {
		global $wpdb;
		$rows = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT l.id, l.item_key, l.product_id, l.stock_mode, l.last_stock, c.manufacturer, c.product_no FROM ' . URME_SS_DB::links_table() . ' l INNER JOIN ' . URME_SS_DB::catalog_table() . ' c ON c.item_key = l.item_key WHERE l.product_id > 0 AND l.sync_enabled = 1 AND l.stock_mode <> %s', self::LOCAL ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			if ( in_array( URME_SS_Settings::brand_key( $r['manufacturer'] ), $brand_keys, true ) ) {
				$rows[] = $r;
			}
		}
		if ( ! $rows ) {
			return array();
		}
		$info = URME_SS_Store::stock_info( wp_list_pluck( $rows, 'product_id' ) );
		$held = array();
		foreach ( $rows as $r ) {
			$stock = $info[ (int) $r['product_id'] ] ?? null;
			$units = self::extra_units( $r, ( $stock && $stock['managed'] ) ? max( 0, (int) $stock['qty'] ) : 0 );
			if ( $units < 1 ) {
				continue;
			}
			URME_SS_DB::delete_link( (int) $r['id'] ); // URME Lager: out of supplier sync, stock and cost kept.
			URME_SS_Log::info( sprintf( 'Brand %s enabled: product #%d (%s) has %d URME unit(s); removed from supplier sync (URME Lager), supplier stock and cost not written.', $r['manufacturer'], $r['product_id'], $r['product_no'], $units ) );
			$held[] = sprintf( '%s (%d unit%s)', $r['product_no'], $units, 1 === $units ? '' : 's' );
		}
		return $held;
	}

	public static function local_priority_message( $units ) {
		return sprintf( 'Local URME stock exists (%d unit%s). Dropshipping can only start when the URME Lager stock is 0.', $units, 1 === (int) $units ? '' : 's' );
	}

	/**
	 * Admin: switch to Supplier now. Callers must check local_units_before_supplier() first.
	 */
	public static function enable_supplier( $link_id ) {
		global $wpdb;
		$link = URME_SS_DB::get_link_by_id( $link_id );
		if ( ! $link ) {
			return 'Selection not found.';
		}
		$wpdb->update(
			URME_SS_DB::links_table(),
			array(
				'stock_mode'        => self::SUPPLIER,
				'local_qty'         => 0,
				'needs_stock_apply' => 0,
				'sync_enabled'      => 1,
				'mode_changed_at'   => current_time( 'mysql', true ),
				'mode_note'         => (int) $link['local_qty'] ? sprintf( 'Switched to Supplier now manually; %d local unit(s) no longer tracked.', $link['local_qty'] ) : 'Switched to Supplier now manually.',
			),
			array( 'id' => $link_id )
		);
		URME_SS_Product_Source::changed( array( $link['product_id'] ) );
		URME_SS_Log::info( sprintf( 'Transition %s → Supplier (manual) for product #%d%s.', self::LOCAL === $link['stock_mode'] ? 'Local first' : 'Supplier', $link['product_id'], (int) $link['local_qty'] ? sprintf( '; %d local unit(s) dropped', $link['local_qty'] ) : '' ) );
		return true;
	}
}
