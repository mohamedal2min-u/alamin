<?php
/**
 * "Local first → Supplier automatically": tracks URME-owned units separately
 * from supplier stock.
 *
 * WooCommerce keeps, per order line, how many units it has taken from stock
 * (`_reduced_stock`). Every stock movement (payment, cancellation, refund with
 * restock, admin quantity edit, line removal) ends with that line being saved.
 * On each save we compare `_reduced_stock` with our ledger row for the line and
 * book the difference:
 *
 *  - more units taken  → from local stock first, the rest from the supplier;
 *  - units given back  → to local stock first, the rest to the supplier.
 *
 * The ledger also freezes the fulfilment source at sale time (src_local / src_supplier)
 * for the admin-only "URME Lager / Dropshipping" labels. Nothing is written to the order
 * or its lines, so none of this can reach customers, emails, invoices or the REST API.
 *
 * The ledger row (urme_ss_alloc) and the link's local_qty are changed in ONE
 * database transaction with compare-and-swap guards, so a crash can never book
 * a unit twice. Anything that fails is logged and picked up again by sweep(),
 * which every safe sync run calls before it may switch a product to supplier.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Inventory {

	const LOCAL    = 'local_first';
	const SUPPLIER = 'supplier';

	/**
	 * Re-entry guard: our own writes must not trigger another reconcile.
	 *
	 * @var bool
	 */
	private static $busy = false;

	/**
	 * Product IDs that have a link (loaded once per request).
	 *
	 * @var array<int,bool>|null
	 */
	private static $linked = null;

	/**
	 * Per request: order line ID => `_reduced_stock` value already booked (or irrelevant).
	 * WooCommerce saves a line many times per checkout; unchanged saves need no database work.
	 *
	 * @var array<int,int>
	 */
	private static $seen = array();

	public static function flush_cache() {
		self::$linked = null;
	}

	private static function in_ledger( $item_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . URME_SS_DB::alloc_table() . ' WHERE order_item_id = %d', $item_id ) ); // phpcs:ignore WordPress.DB
	}

	private static function is_linked_product( $product_id ) {
		global $wpdb;
		if ( null === self::$linked ) {
			// phpcs:ignore WordPress.DB
			self::$linked = array_fill_keys( array_map( 'intval', $wpdb->get_col( 'SELECT product_id FROM ' . URME_SS_DB::links_table() . ' WHERE product_id > 0' ) ), true );
		}
		return isset( self::$linked[ (int) $product_id ] );
	}

	public static function init() {
		add_action( 'woocommerce_new_order_item', array( __CLASS__, 'on_item_saved' ), 20, 3 );
		add_action( 'woocommerce_update_order_item', array( __CLASS__, 'on_item_saved' ), 20, 3 );
	}

	/**
	 * Hook: any order line was saved.
	 */
	public static function on_item_saved( $item_id, $item = null, $order_id = 0 ) {
		if ( self::$busy || ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		// Saved again with the stock count this request already booked: nothing can have changed.
		$item_id = (int) $item_id;
		$reduced = (int) wc_stock_amount( $item->get_meta( '_reduced_stock', true ) );
		if ( isset( self::$seen[ $item_id ] ) && self::$seen[ $item_id ] === $reduced ) {
			return;
		}
		// Cheap filter: only lines of linked products, or lines already in the ledger.
		$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
		if ( ! self::is_linked_product( $product_id ) && ! self::in_ledger( $item_id ) ) {
			self::$seen[ $item_id ] = $reduced;
			return;
		}
		// A failed booking (null) is not remembered, so the next save retries it.
		if ( null !== self::reconcile_item( $item_id ) ) {
			self::$seen[ $item_id ] = $reduced;
		}
	}

	/* ---------------------------------------------------------------------
	 * Reconcile one order line
	 * ------------------------------------------------------------------- */

	/**
	 * Bring the ledger for one order line in line with WooCommerce's `_reduced_stock`.
	 *
	 * @return bool|null True if something was booked, false if nothing to do, null on error (left for later).
	 */
	public static function reconcile_item( $item_id ) {
		global $wpdb;
		$facts = self::item_facts( $item_id );
		if ( ! $facts ) {
			return false;
		}

		$alloc_t = URME_SS_DB::alloc_table();
		$links_t = URME_SS_DB::links_table();
		$lock    = URME_SS_DB::lock_clause();

		// Which link does this line belong to? The one it was first booked against, else the product's current link.
		$link_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT link_id FROM {$alloc_t} WHERE order_item_id = %d", $item_id ) ); // phpcs:ignore WordPress.DB
		if ( ! $link_id ) {
			if ( 0 === $facts['reduced'] ) {
				return false; // Nothing taken from stock yet (e.g. unpaid order): nothing to book.
			}
			$link = URME_SS_DB::link_for_product( $facts['product_id'] );
			if ( ! $link ) {
				return false; // Not a linked product.
			}
			$link_id = (int) $link['id'];
		}

		$last_error = '';
		for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
			$tx = URME_SS_DB::begin();
			if ( ! $tx ) {
				$last_error = 'could not start a database transaction';
				break;
			}
			self::$busy = true;
			try {
				$now = current_time( 'mysql', true );
				// phpcs:disable WordPress.DB
				$alloc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$alloc_t} WHERE order_item_id = %d{$lock}", $item_id ), ARRAY_A );
				if ( ! $alloc ) {
					$ok = $wpdb->insert(
						$alloc_t,
						array(
							'order_item_id'      => $item_id,
							'order_id'           => $facts['order_id'],
							'link_id'            => $link_id,
							'product_id'         => $facts['product_id'],
							'last_reduced_stock' => 0,
							'local_allocated'    => 0,
							'supplier_allocated' => 0,
							'origin'             => self::origin_for_order( $facts['order_id'] ),
							'created_at'         => $now,
							'updated_at'         => $now,
						)
					);
					if ( ! $ok ) {
						throw new URME_SS_Conflict( 'ledger row was created concurrently' );
					}
					$alloc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$alloc_t} WHERE order_item_id = %d{$lock}", $item_id ), ARRAY_A );
				}
				$link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$links_t} WHERE id = %d{$lock}", $alloc['link_id'] ), ARRAY_A );
				// phpcs:enable

				$delta = $facts['reduced'] - (int) $alloc['last_reduced_stock'];
				if ( 0 === $delta ) {
					URME_SS_DB::commit( $tx );
					self::$busy = false;
					return false;
				}

				$plan = self::plan( $delta, $alloc, $link );
				self::checkpoint( 'planned', $item_id );

				$set = array();
				foreach ( $plan['link_changes'] as $col => $val ) {
					if ( ! $link || (string) $link[ $col ] !== (string) $val ) {
						$set[ $col ] = $val;
					}
				}
				if ( $link && $set ) {
					// Compare-and-swap: only if nobody changed the link since we read it.
					$where  = array(
						'id'         => (int) $link['id'],
						'local_qty'  => (int) $link['local_qty'],
						'stock_mode' => $link['stock_mode'],
					);
					$result = $wpdb->update( $links_t, $set, $where );
					if ( 1 !== $result ) {
						throw new URME_SS_Conflict( 'product link changed while booking' );
					}
				}
				self::checkpoint( 'link_updated', $item_id );

				$result = $wpdb->update(
					$alloc_t,
					array(
						'last_reduced_stock' => $facts['reduced'],
						'local_allocated'    => $plan['local_allocated'],
						'supplier_allocated' => $plan['supplier_allocated'],
						'src_local'          => $plan['src_local'],
						'src_supplier'       => $plan['src_supplier'],
						'ret_local'          => $plan['ret_local'],
						'ret_supplier'       => $plan['ret_supplier'],
						'updated_at'         => $now,
					),
					array(
						'order_item_id'      => $item_id,
						'last_reduced_stock' => (int) $alloc['last_reduced_stock'],
					)
				);
				if ( 1 !== $result ) {
					throw new URME_SS_Conflict( 'order line was booked concurrently' );
				}
				self::checkpoint( 'before_commit', $item_id );

				if ( ! URME_SS_DB::commit( $tx ) ) {
					throw new Exception( 'commit failed' );
				}
				self::$busy = false;
			} catch ( URME_SS_Conflict $e ) {
				URME_SS_DB::rollback( $tx );
				self::$busy = false;
				$last_error = $e->getMessage();
				continue;
			} catch ( Throwable $e ) {
				URME_SS_DB::rollback( $tx );
				self::$busy = false;
				$last_error = $e->getMessage();
				break;
			}

			// Committed. Everything below is derived from the ledger and safe to repeat.
			self::after_commit( $item_id, $facts, $link, $plan );
			return true;
		}

		URME_SS_Log::error( sprintf( 'Local inventory: order line #%d (order #%d) could not be booked (%s). Nothing was guessed; it will be retried on the next sync.', $item_id, $facts['order_id'], $last_error ) );
		return null;
	}

	/**
	 * Decide how a change of `_reduced_stock` is split between local and supplier.
	 */
	private static function plan( $delta, array $alloc, $link ) {
		$local    = (int) $alloc['local_allocated'];
		$supplier = (int) $alloc['supplier_allocated'];
		$plan     = array(
			'delta'              => $delta,
			'took_local'         => 0,
			'took_supplier'      => 0,
			'back_local'         => 0,
			'back_supplier'      => 0,
			'link_changes'       => array(),
			'warnings'           => array(),
		);
		$mode      = $link ? $link['stock_mode'] : self::SUPPLIER;
		$local_qty = $link ? max( 0, (int) $link['local_qty'] ) : 0;

		if ( $delta > 0 ) {
			// Units sold: local stock first, only while in Local first.
			$take                  = self::LOCAL === $mode ? min( $delta, $local_qty ) : 0;
			$plan['took_local']    = $take;
			$plan['took_supplier'] = $delta - $take;
			$local                += $take;
			$supplier             += $delta - $take;
			if ( $take ) {
				$plan['link_changes']['local_qty'] = $local_qty - $take;
			}
			if ( self::LOCAL === $mode && $plan['took_supplier'] && $link ) {
				$plan['warnings'][] = sprintf( '%d unit(s) were sold beyond the local stock while in Local first (check backorders/stock); booked as supplier units.', $plan['took_supplier'] );
				// WooCommerce stock went below the local count: bring it back to the local units.
				$plan['link_changes']['needs_stock_apply'] = 1;
			}
		} else {
			// Units given back (cancel, refund with restock, qty lowered): to local first.
			$back                  = -$delta;
			$plan['back_local']    = min( $back, $local );
			$plan['back_supplier'] = $back - $plan['back_local'];
			$local                -= $plan['back_local'];
			$supplier             -= $plan['back_supplier'];
			if ( $supplier < 0 ) {
				$plan['warnings'][] = 'More units were given back than this line had booked; the excess was ignored.';
				$supplier           = 0;
			}
			if ( $plan['back_local'] && $link ) {
				// A returned unit of a Dropshipping watch does not change its mode (no order history
				// decides the fulfillment); the admin moves it back to URME Lager if wanted.
				$plan['link_changes']['local_qty'] = self::SUPPLIER === $mode ? 0 : $local_qty + $plan['back_local'];
			}
			if ( $plan['back_supplier'] && self::LOCAL === $mode && $link ) {
				// WooCommerce just added supplier units to a local-only stock level: take them out again.
				$plan['link_changes']['needs_stock_apply'] = 1;
			}
		}
		if ( ! $link && ( $plan['took_local'] || $plan['back_local'] ) ) {
			$plan['warnings'][] = 'The supplier link for this line no longer exists; local stock could not be adjusted.';
		}

		$plan['local_allocated']    = $local;
		$plan['supplier_allocated'] = $supplier;

		// Fulfilment source, frozen at sale time: set when units are taken, never recomputed later.
		$plan['src_local']    = (int) ( $alloc['src_local'] ?? 0 );
		$plan['src_supplier'] = (int) ( $alloc['src_supplier'] ?? 0 );
		$plan['ret_local']    = (int) ( $alloc['ret_local'] ?? 0 );
		$plan['ret_supplier'] = (int) ( $alloc['ret_supplier'] ?? 0 );
		if ( $delta > 0 ) {
			if ( 0 === (int) $alloc['local_allocated'] + (int) $alloc['supplier_allocated'] ) {
				// A new sale of this line (first sale, or sold again after a full cancellation).
				$plan['src_local']    = $plan['took_local'];
				$plan['src_supplier'] = $plan['took_supplier'];
				$plan['ret_local']    = 0;
				$plan['ret_supplier'] = 0;
			} else {
				$plan['src_local']    += $plan['took_local'];
				$plan['src_supplier'] += $plan['took_supplier'];
			}
		} else {
			$plan['ret_local']    += $plan['back_local'];
			$plan['ret_supplier'] += $plan['back_supplier'];
		}
		return $plan;
	}

	private static function after_commit( $item_id, array $facts, $link, array $plan ) {
		$ref = sprintf( 'order #%d, line #%d', $facts['order_id'], $item_id );

		$local_now = (int) ( $plan['link_changes']['local_qty'] ?? ( $link ? $link['local_qty'] : 0 ) );
		$note      = array();
		if ( $plan['took_local'] ) {
			$note[] = sprintf( '%d unit(s) sold from URME local stock (local left: %d)', $plan['took_local'], $local_now );
		}
		if ( $plan['back_local'] && $link ) {
			$note[] = sprintf( '%d local unit(s) returned to URME local stock (local now: %d)', $plan['back_local'], $local_now );
		}
		if ( $note ) {
			// Admin log only; nothing is added to the order (no notes, no line meta).
			URME_SS_Log::info( sprintf( 'Local inventory (%s, product #%d): %s.', $ref, $facts['product_id'], implode( '; ', $note ) ) );
		}
		if ( $plan['took_local'] && 0 === $local_now ) {
			URME_SS_Log::info( sprintf( 'Local stock for product #%d is sold out; it switches to supplier stock on the next safe sync.', $facts['product_id'] ) );
		}
		foreach ( $plan['warnings'] as $warning ) {
			URME_SS_Log::warning( sprintf( 'Local inventory (%s, product #%d): %s', $ref, $facts['product_id'], $warning ) );
		}
		if ( $link && ! empty( $plan['link_changes']['needs_stock_apply'] ) ) {
			self::apply_pending( (int) $link['id'] );
		}
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
	 * Test hook to simulate a crash at a given step.
	 */
	private static function checkpoint( $stage, $item_id ) {
		do_action( 'urme_ss_inventory_checkpoint', $stage, $item_id );
	}

	/* ---------------------------------------------------------------------
	 * Applying local state to the WooCommerce product
	 * ------------------------------------------------------------------- */

	/**
	 * Write local stock (and local cost) to the product when the ledger asked for it.
	 * Idempotent; the flag is cleared only after the write succeeded.
	 */
	public static function apply_pending( $link_id ) {
		global $wpdb;
		$link = URME_SS_DB::get_link_by_id( $link_id );
		if ( ! $link || ! (int) $link['needs_stock_apply'] ) {
			return true;
		}
		if ( ! (int) $link['sync_enabled'] ) {
			return true; // Paused: the product's stock and cost are left exactly as they are; the request stays queued.
		}
		if ( self::LOCAL === $link['stock_mode'] ) {
			$product = wc_get_product( (int) $link['product_id'] );
			if ( ! $product ) {
				URME_SS_Log::error( sprintf( 'Local inventory: product #%d not found; local stock could not be applied.', $link['product_id'] ) );
				return false;
			}
			$qty = max( 0, (int) $link['local_qty'] );
			if ( true !== $product->get_manage_stock() ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $qty );
				$product->save();
			} elseif ( (int) $product->get_stock_quantity() !== $qty ) {
				wc_update_product_stock( $product, $qty, 'set' );
			}
			if ( null !== $link['local_cost'] ) {
				$target = URME_SS_Store::cost_target();
				if ( $target['type'] ) {
					try {
						URME_SS_Store::apply_cost( wc_get_product( (int) $link['product_id'] ), $target, (float) $link['local_cost'] );
					} catch ( Exception $e ) {
						URME_SS_Log::warning( sprintf( 'Local cost for product #%d not restored: %s', $link['product_id'], $e->getMessage() ) );
					}
				}
			}
		}
		// Clear only if nothing changed meanwhile (otherwise the newer request stays pending).
		$wpdb->update(
			URME_SS_DB::links_table(),
			array( 'needs_stock_apply' => 0 ),
			array(
				'id'                => $link_id,
				'needs_stock_apply' => 1,
				'local_qty'         => (int) $link['local_qty'],
				'stock_mode'        => $link['stock_mode'],
			)
		);
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Sweep: crash recovery, run by every safe sync before any transition
	 * ------------------------------------------------------------------- */

	/**
	 * Re-book order lines whose ledger differs from WooCommerce, and re-apply pending local stock.
	 *
	 * @return int[] Link IDs that could not be reconciled (they must not be switched this run).
	 */
	public static function sweep() {
		global $wpdb;
		$links_t = URME_SS_DB::links_table();
		$alloc_t = URME_SS_DB::alloc_table();
		$items   = $wpdb->prefix . 'woocommerce_order_items';
		$meta    = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$failed  = array();

		// phpcs:disable WordPress.DB
		$local_links = $wpdb->get_results( "SELECT id, product_id FROM {$links_t} WHERE stock_mode = 'local_first' AND product_id > 0", ARRAY_A );
		$candidates  = array();
		if ( $local_links ) {
			$pids = array_map( 'intval', wp_list_pluck( $local_links, 'product_id' ) );
			$in   = implode( ',', $pids );
			// Every line of a Local-first product whose WooCommerce count differs from the ledger.
			$rows = $wpdb->get_results(
				"SELECT oi.order_item_id, rs.meta_value AS reduced, a.last_reduced_stock AS booked
				FROM {$meta} pm
				INNER JOIN {$items} oi ON oi.order_item_id = pm.order_item_id AND oi.order_item_type = 'line_item'
				LEFT JOIN {$meta} rs ON rs.order_item_id = pm.order_item_id AND rs.meta_key = '_reduced_stock'
				LEFT JOIN {$alloc_t} a ON a.order_item_id = pm.order_item_id
				WHERE pm.meta_key IN ('_product_id','_variation_id') AND pm.meta_value IN ({$in})",
				ARRAY_A
			);
			foreach ( $rows as $r ) {
				if ( (int) wc_stock_amount( $r['reduced'] ?? 0 ) !== (int) ( $r['booked'] ?? 0 ) ) {
					$candidates[ (int) $r['order_item_id'] ] = true;
				}
			}
		}
		// Lines that sold local units (returns of those matter whatever the current mode).
		$rows = $wpdb->get_results(
			"SELECT a.order_item_id, rs.meta_value AS reduced, a.last_reduced_stock AS booked
			FROM {$alloc_t} a LEFT JOIN {$meta} rs ON rs.order_item_id = a.order_item_id AND rs.meta_key = '_reduced_stock'
			WHERE a.local_allocated > 0",
			ARRAY_A
		);
		// phpcs:enable
		foreach ( $rows as $r ) {
			if ( (int) wc_stock_amount( $r['reduced'] ?? 0 ) !== (int) $r['booked'] ) {
				$candidates[ (int) $r['order_item_id'] ] = true;
			}
		}

		foreach ( array_keys( $candidates ) as $item_id ) {
			if ( null === self::reconcile_item( $item_id ) ) {
				$link_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT link_id FROM {$alloc_t} WHERE order_item_id = %d", $item_id ) ); // phpcs:ignore WordPress.DB
				$failed[ $link_id ] = true;
				// A line with no ledger row yet: block its product's link instead.
				if ( ! $link_id ) {
					$facts = self::item_facts( $item_id );
					$link  = $facts ? URME_SS_DB::link_for_product( $facts['product_id'] ) : null;
					if ( $link ) {
						$failed[ (int) $link['id'] ] = true;
					}
				}
			}
		}

		// phpcs:ignore WordPress.DB
		foreach ( $wpdb->get_col( "SELECT id FROM {$links_t} WHERE needs_stock_apply = 1" ) as $link_id ) {
			self::apply_pending( (int) $link_id );
		}
		return array_keys( $failed );
	}

	/* ---------------------------------------------------------------------
	 * Mode changes
	 * ------------------------------------------------------------------- */

	/**
	 * Local first → Supplier once the local units are sold. Only called by a safe sync run.
	 *
	 * The switch and its price-review record are written in one transaction: either both
	 * happen or neither does. The compare-and-swap update makes a second run a no-op.
	 *
	 * @param array           $link    Link joined with its catalog row.
	 * @param WC_Product|null $product The linked product (prices are only read).
	 * @param array|null      $rate    Current EUR/SEK rate.
	 * @return bool True if this call made the switch.
	 */
	public static function handover( array $link, $product = null, $rate = null ) {
		global $wpdb;
		$tx = URME_SS_DB::begin();
		if ( ! $tx ) {
			URME_SS_Log::error( sprintf( 'Local first → Supplier for product #%d postponed: could not start a transaction.', $link['product_id'] ) );
			return false;
		}
		try {
			$now    = current_time( 'mysql', true );
			$result = $wpdb->query( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					'UPDATE ' . URME_SS_DB::links_table() . " SET stock_mode = 'supplier', local_qty = 0, mode_changed_at = %s, mode_note = %s
					WHERE id = %d AND stock_mode = 'local_first' AND needs_stock_apply = 0",
					$now,
					'URME Lager stock reached 0; switched to Dropshipping.',
					$link['id']
				)
			);
			if ( 1 !== (int) $result ) {
				URME_SS_DB::rollback( $tx );
				return false; // Already switched by another run, or the state changed.
			}
			self::checkpoint( 'handover_switched', (int) $link['id'] );
			if ( ! URME_SS_Price_Review::create( $link, $product, $rate, $now ) ) {
				throw new Exception( 'the price-review record could not be written (' . $wpdb->last_error . ')' );
			}
			if ( ! URME_SS_DB::commit( $tx ) ) {
				throw new Exception( 'commit failed' );
			}
		} catch ( Throwable $e ) {
			URME_SS_DB::rollback( $tx );
			URME_SS_Log::error( sprintf( 'Local first → Supplier for product #%d postponed: %s. Nothing changed; retried on the next sync.', $link['product_id'], $e->getMessage() ) );
			return false;
		}
		URME_SS_Product_Source::changed( array( $link['product_id'] ) ); // Now Dropshipping: its product page must not stay cached.
		$sku = $product ? $product->get_sku() : ( $link['product_no'] ?? $link['item_key'] );
		URME_SS_Log::info( sprintf( 'Transition Local first → Supplier for product #%d (%s): local stock sold out, supplier stock and cost now synced.', $link['product_id'], $sku ) );
		URME_SS_Log::info( sprintf( 'Price review required: SKU %s (product #%d) has switched to Dropshipping. Selling price was not changed.', $sku, $link['product_id'] ) );
		return true;
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
	 * Admin: start (or correct) Local first with the given local quantity and cost.
	 *
	 * @return true|string True or an error message.
	 */
	public static function enable_local( $link_id, $qty, $cost ) {
		global $wpdb;
		if ( get_option( 'urme_ss_schema_error' ) ) {
			return 'Local first is unavailable: the database upgrade is incomplete (see the notice at the top of the page).';
		}
		$link = URME_SS_DB::get_link_by_id( $link_id );
		if ( ! $link || ! (int) $link['product_id'] ) {
			return 'Link a WooCommerce product first.';
		}
		$product = wc_get_product( (int) $link['product_id'] );
		if ( ! $product ) {
			return 'The linked product no longer exists.';
		}
		if ( $product->is_type( 'variable' ) ) {
			return 'Local first works on a simple product or a single variation, not on a variable parent product.';
		}
		if ( 'no' !== $product->get_backorders() ) {
			return sprintf( 'Local first was not enabled: backorders are allowed on "%s". With backorders WooCommerce could sell more than your local units before supplier stock is checked. Set Backorders to "Do not allow" on the product (Inventory tab) and try again.', $product->get_name() );
		}
		$qty = (int) $qty;
		if ( $qty < 1 ) {
			return 'Enter how many units you own (at least 1).';
		}

		// Book every earlier sale of this product first, so old orders never count as local sales.
		self::baseline( $link );

		$result = $wpdb->update(
			URME_SS_DB::links_table(),
			array(
				'stock_mode'        => self::LOCAL,
				'local_qty'         => $qty,
				'local_cost'        => null === $cost ? null : round( (float) $cost, 2 ),
				'needs_stock_apply' => 1,
				'mode_changed_at'   => current_time( 'mysql', true ),
				'mode_note'         => sprintf( 'Local first enabled with %d local unit(s).', $qty ),
				'sync_enabled'      => 1,
			),
			array( 'id' => $link_id )
		);
		if ( false === $result ) {
			return 'Database error while enabling Local first.';
		}
		URME_SS_Product_Source::changed( array( $link['product_id'] ) );
		self::apply_pending( $link_id );
		URME_SS_Log::info( sprintf( 'Local first enabled for product #%d (%s): %d local unit(s)%s.', $link['product_id'], $link['item_key'], $qty, null === $cost ? '' : ', local cost ' . wc_format_decimal( $cost, 2 ) . ' SEK' ) );
		return true;
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
	 * Resuming a paused Supplier-now link. Units in WooCommerce above what supplier sync last set
	 * were added by hand while paused, so they are local URME stock; when supplier sync never
	 * set a stock, every unit is. A paused Local first link resumes as Local first (0 here).
	 *
	 * @param array           $link    Supplier link.
	 * @param WC_Product|null $product Its product.
	 */
	public static function local_units_on_resume( array $link, $product ) {
		if ( self::LOCAL === ( $link['stock_mode'] ?? '' ) ) {
			return 0;
		}
		return self::extra_units( $link, self::local_units_before_supplier( null, $product ) );
	}

	/**
	 * The resume rule on a WooCommerce stock already read: local units when the stock is above
	 * what supplier sync last set (or it never set one), else 0.
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

	/**
	 * Record all existing sales of a product as already booked (supplier), so enabling
	 * Local first never counts an earlier sale against the new local quantity.
	 */
	private static function baseline( array $link ) {
		global $wpdb;
		$items   = $wpdb->prefix . 'woocommerce_order_items';
		$meta    = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$alloc_t = URME_SS_DB::alloc_table();
		$now     = current_time( 'mysql', true );
		// phpcs:disable WordPress.DB
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.order_item_id, oi.order_id, rs.meta_value AS reduced, a.order_item_id AS booked
				FROM {$meta} pm
				INNER JOIN {$items} oi ON oi.order_item_id = pm.order_item_id AND oi.order_item_type = 'line_item'
				LEFT JOIN {$meta} rs ON rs.order_item_id = pm.order_item_id AND rs.meta_key = '_reduced_stock'
				LEFT JOIN {$alloc_t} a ON a.order_item_id = pm.order_item_id
				WHERE pm.meta_key IN ('_product_id','_variation_id') AND pm.meta_value = %d",
				$link['product_id']
			),
			ARRAY_A
		);
		foreach ( $rows as $r ) {
			if ( $r['booked'] ) {
				// Already in the ledger: settle any pending difference first (as supplier in the current mode).
				self::reconcile_item( (int) $r['order_item_id'] );
				continue;
			}
			$reduced = (int) wc_stock_amount( $r['reduced'] ?? 0 );
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$alloc_t} (order_item_id, order_id, link_id, product_id, last_reduced_stock, local_allocated, supplier_allocated, origin, created_at, updated_at) VALUES (%d, %d, %d, %d, %d, 0, %d, 'legacy', %s, %s)",
					$r['order_item_id'],
					$r['order_id'],
					$link['id'],
					$link['product_id'],
					$reduced,
					$reduced,
					$now,
					$now
				)
			);
		}
		// phpcs:enable
	}

	/**
	 * Local units still owned (for guards in the admin).
	 */
	public static function has_local_units( array $link ) {
		return self::LOCAL === ( $link['stock_mode'] ?? '' ) && (int) ( $link['local_qty'] ?? 0 ) > 0;
	}
}

/**
 * A concurrent change was detected; the transaction is retried.
 */
class URME_SS_Conflict extends Exception {}
