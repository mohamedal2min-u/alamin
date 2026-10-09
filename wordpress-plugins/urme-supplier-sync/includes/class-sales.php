<?php
/**
 * Dropshipping sales and profit (admin only, read only).
 *
 * Reads the fulfillment ledger: only order lines whose units were taken as Dropshipping count,
 * and only the units still sold (units given back by a cancellation, a restocking refund or a
 * lowered quantity are left out). Per unit:
 *
 *   Sales excl. VAT = the line's total after discounts and refunds (WooCommerce, excl. VAT)
 *   Customer paid   = sales excl. VAT + the line's VAT
 *   Supplier cost   = the line's cost of goods frozen by WooCommerce at order time (COGS);
 *                     when the order has none, the watch's last synced supplier cost (estimated)
 *   Extra cost      = the supplier's cost per order (shipping + fees, EUR, Settings > Suppliers) ×
 *                     the current rate, once per order and supplier, shared by its units
 *   Payment fee     = customer paid × the payment fee setting
 *   Profit          = sales excl. VAT − supplier cost − extra cost − payment fee
 *
 * Shipping charged to the customer and order-level fees are not part of a line and not counted.
 * Never writes anything.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Sales {

	/** Order statuses whose sales do not count. */
	const SKIP_STATUSES = array( 'cancelled', 'refunded', 'failed', 'pending', 'checkout-draft', 'trash' );

	/**
	 * Period presets: id => label.
	 */
	public static function periods() {
		return array(
			'this_month' => 'This month',
			'last_month' => 'Last month',
			'last_30'    => 'Last 30 days',
			'this_year'  => 'This year',
			'all'        => 'All time',
			'custom'     => 'Custom',
		);
	}

	/**
	 * Local date range (Y-m-d, inclusive) for a preset or custom dates.
	 *
	 * @return array{0: string, 1: string} From and to, '' for open ends.
	 */
	public static function range( $period, $from = '', $to = '' ) {
		$now = current_datetime();
		switch ( $period ) {
			case 'last_month':
				$first = $now->modify( 'first day of last month' );
				return array( $first->format( 'Y-m-d' ), $first->modify( 'last day of this month' )->format( 'Y-m-d' ) );
			case 'last_30':
				return array( $now->modify( '-29 days' )->format( 'Y-m-d' ), $now->format( 'Y-m-d' ) );
			case 'this_year':
				return array( $now->format( 'Y-01-01' ), $now->format( 'Y-m-d' ) );
			case 'all':
				return array( '', '' );
			case 'custom':
				$valid = static function ( $d ) {
					return is_string( $d ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : '';
				};
				return array( $valid( $from ), $valid( $to ) );
			default: // this_month
				return array( $now->format( 'Y-m-01' ), $now->format( 'Y-m-d' ) );
		}
	}

	/**
	 * Dropshipping lines, totals and per-brand totals for a local date range (by the date the
	 * units were taken).
	 *
	 * @param string $from Local Y-m-d or ''.
	 * @param string $to   Local Y-m-d or '' (inclusive).
	 */
	public static function report( $from, $to ) {
		global $wpdb;
		$a      = URME_SS_DB::alloc_table();
		$l      = URME_SS_DB::links_table();
		$c      = URME_SS_DB::catalog_table();
		$where  = array( "a.origin = 'sale'", 'a.src_supplier > 0' );
		$params = array();
		$tz     = wp_timezone();
		$utc    = new DateTimeZone( 'UTC' );
		if ( $from ) {
			$where[]  = 'a.created_at >= %s';
			$params[] = ( new DateTimeImmutable( $from . ' 00:00:00', $tz ) )->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		}
		if ( $to ) {
			$where[]  = 'a.created_at < %s';
			$params[] = ( new DateTimeImmutable( $to . ' 00:00:00', $tz ) )->modify( '+1 day' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		}
		$sql = "SELECT a.*, c.manufacturer, c.product_no, l.last_cost_sek
			FROM {$a} a LEFT JOIN {$l} l ON l.id = a.link_id LEFT JOIN {$c} c ON c.item_key = IF(a.source_key <> '', a.source_key, l.item_key)
			WHERE " . implode( ' AND ', $where ) . ' ORDER BY a.created_at DESC, a.order_item_id DESC';
		// phpcs:ignore WordPress.DB
		$rows = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A );

		$ctx    = URME_SS_Price_Hint::context();
		$rate   = $ctx['rate'];
		$lines  = array();
		$tot    = self::empty_totals();
		$brands = array();
		$by_sup = array();
		$orders = array();
		$ret    = 0;
		$groups = array(); // "order|supplier" => units, to share the cost per order.
		foreach ( (array) $rows as $row ) {
			$order = wc_get_order( (int) $row['order_id'] );
			if ( ! $order || in_array( $order->get_status(), self::SKIP_STATUSES, true ) ) {
				continue;
			}
			$item = $order->get_item( (int) $row['order_item_id'] );
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$qty     = (int) $item->get_quantity();
			$net_qty = $qty + (int) $order->get_qty_refunded_for_item( $item->get_id() ); // Refunded qty is negative.
			$units   = min( (int) $row['supplier_allocated'], $net_qty );
			$ret    += max( 0, (int) $row['src_supplier'] - max( 0, $units ) );
			if ( $units <= 0 || $qty <= 0 ) {
				continue;
			}
			$total    = (float) $item->get_total();
			$tax      = (float) $item->get_total_tax();
			$line_net = $total - (float) $order->get_total_refunded_for_item( $item->get_id() );
			$net      = max( 0.0, $line_net ) / $net_qty * $units;
			$paid     = $total > 0 ? $net * ( 1 + $tax / $total ) : $net;
			$cogs     = method_exists( $item, 'get_cogs_value' ) ? $item->get_cogs_value() : $item->get_meta( '_cogs_value', true );
			$estimate = ! is_numeric( $cogs ) || (float) $cogs <= 0;
			if ( $estimate ) {
				$cost = is_numeric( $row['last_cost_sek'] ) ? (float) $row['last_cost_sek'] * $units : 0.0;
			} else {
				$cost = (float) $cogs / $qty * $units;
			}
			$fee      = $paid * $ctx['fee'];
			$supplier = URME_SS_Suppliers::id( $row['supplier'] ?? '' );
			$group    = $order->get_id() . '|' . $supplier;
			$groups[ $group ] = ( $groups[ $group ] ?? 0 ) + $units;

			$product = $item->get_product();
			$brand   = '' !== (string) $row['manufacturer'] ? $row['manufacturer'] : '—';
			$line    = array(
				'date'       => $row['created_at'],
				'order_id'   => $order->get_id(),
				'order_no'   => $order->get_order_number(),
				'order_url'  => $order->get_edit_order_url(),
				'product_id' => $product ? $product->get_id() : (int) $row['product_id'],
				'name'       => $item->get_name(),
				'sku'        => $product && $product->get_sku() ? $product->get_sku() : (string) $row['product_no'],
				'brand'      => $brand,
				'units'      => $units,
				'paid'       => $paid,
				'net'        => $net,
				'cost'       => $cost,
				'extra'      => 0.0,
				'fee'        => $fee,
				'profit'     => 0.0,
				'estimate'   => $estimate,
				'supplier'   => $supplier,
				'group'      => $group,
			);
			$lines[] = $line;
		}
		foreach ( $lines as &$line ) {
			// The supplier's cost per order, shared by the units of that order bought from it.
			$per_order       = $rate ? URME_SS_Suppliers::extra_eur( $line['supplier'] ) * $rate : 0.0;
			$line['extra']   = $per_order * $line['units'] / max( 1, $groups[ $line['group'] ] );
			$line['profit']  = $line['net'] - $line['cost'] - $line['extra'] - $line['fee'];
			$orders[ $line['order_id'] ] = true;
			self::add( $tot, $line );
			foreach ( array( 'brands' => $line['brand'], 'by_sup' => $line['supplier'] ) as $var => $key ) {
				if ( ! isset( ${$var}[ $key ] ) ) {
					${$var}[ $key ] = self::empty_totals();
				}
				self::add( ${$var}[ $key ], $line );
			}
			if ( ! isset( $by_sup[ $line['supplier'] ]['order_ids'] ) ) {
				$by_sup[ $line['supplier'] ]['order_ids'] = array();
			}
			$by_sup[ $line['supplier'] ]['order_ids'][ $line['order_id'] ] = true;
		}
		unset( $line );
		$suppliers = array();
		foreach ( URME_SS_Suppliers::ids() as $id ) { // Supplier order, not profit order.
			if ( isset( $by_sup[ $id ] ) ) {
				$by_sup[ $id ]['orders'] = count( $by_sup[ $id ]['order_ids'] );
				unset( $by_sup[ $id ]['order_ids'] );
				$suppliers[ $id ] = $by_sup[ $id ];
			}
		}
		$tot['orders']   = count( $orders );
		$tot['returned'] = $ret;
		uasort(
			$brands,
			static function ( $x, $y ) {
				return $y['profit'] <=> $x['profit'];
			}
		);
		return array(
			'lines'   => $lines,
			'totals'  => $tot,
			'brands'    => $brands,
			'suppliers' => $suppliers,
			'no_rate'   => ! $rate,
		);
	}

	private static function empty_totals() {
		return array(
			'units'     => 0,
			'paid'      => 0.0,
			'net'       => 0.0,
			'cost'      => 0.0,
			'extra'     => 0.0,
			'fee'       => 0.0,
			'profit'    => 0.0,
			'estimates' => 0,
		);
	}

	private static function add( array &$t, array $line ) {
		foreach ( array( 'units', 'paid', 'net', 'cost', 'extra', 'fee', 'profit' ) as $k ) {
			$t[ $k ] += $line[ $k ];
		}
		$t['estimates'] += $line['estimate'] ? 1 : 0;
	}
}
