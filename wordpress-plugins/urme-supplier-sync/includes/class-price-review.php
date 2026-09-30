<?php
/**
 * Admin-only "Price review required" notices for products that switched automatically
 * from Local first to Supplier (dropshipping), so the selling price can be checked by hand.
 *
 * One review per transition. It is created in the same database transaction as the switch
 * (URME_SS_Inventory::handover), whose compare-and-swap update succeeds only once per
 * transition, so repeated cron runs can never add a second one. Prices are only read, never written.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Price_Review {

	const PENDING  = 'pending';
	const REVIEWED = 'reviewed';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'urme_ss_price_reviews';
	}

	/**
	 * Record a review for a transition. Called inside the handover transaction.
	 *
	 * @return bool False if the row could not be written (the caller rolls the switch back).
	 */
	public static function create( array $link, $product, $rate, $transitioned_at ) {
		global $wpdb;
		$eur = null === ( $link['purchase_price'] ?? null ) ? null : (float) $link['purchase_price'];
		return (bool) $wpdb->insert(
			self::table(),
			array(
				'link_id'           => (int) $link['id'],
				'product_id'        => (int) $link['product_id'],
				'item_key'          => (string) $link['item_key'],
				'sku'               => $product ? (string) $product->get_sku() : '',
				'transitioned_at'   => $transitioned_at,
				'local_cost'        => null === $link['local_cost'] ? null : (float) $link['local_cost'],
				'supplier_cost_eur' => $eur,
				'supplier_cost_sek' => ( null !== $eur && $rate ) ? round( $eur * $rate['rate'], 2 ) : null,
				'supplier_stock'    => null === ( $link['stock'] ?? null ) ? null : (int) $link['stock'],
				'regular_price'     => $product ? (string) $product->get_regular_price() : '',
				'sale_price'        => $product ? (string) $product->get_sale_price() : '',
				'status'            => self::PENDING,
			)
		);
	}

	public static function pending_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE status = %s', self::PENDING ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Reviews, newest first, with the live supplier data from the catalog.
	 */
	public static function rows( $status = self::PENDING, $limit = 200 ) {
		global $wpdb;
		$c     = URME_SS_DB::catalog_table();
		$where = self::PENDING === $status ? $wpdb->prepare( 'WHERE r.status = %s', self::PENDING ) : '';
		// phpcs:ignore WordPress.DB
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.*, c.stock AS live_stock, c.purchase_price AS live_eur FROM ' . self::table() . " r LEFT JOIN {$c} c ON c.item_key = r.item_key {$where} ORDER BY r.status = 'pending' DESC, r.transitioned_at DESC, r.id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public static function mark_reviewed( $id ) {
		global $wpdb;
		$done = $wpdb->update(
			self::table(),
			array(
				'status'      => self::REVIEWED,
				'reviewed_at' => current_time( 'mysql', true ),
				'reviewed_by' => get_current_user_id(),
			),
			array(
				'id'     => (int) $id,
				'status' => self::PENDING,
			)
		);
		if ( $done ) {
			URME_SS_Log::info( sprintf( 'Price review #%d marked as reviewed by user #%d.', $id, get_current_user_id() ) );
		}
		return (bool) $done;
	}

	/**
	 * Current values shown in the notice and on the Price Review page.
	 */
	public static function view( array $r ) {
		$product = wc_get_product( (int) $r['product_id'] );
		$rate    = URME_SS_Rates::current();
		$eur     = null !== $r['live_eur'] ? (float) $r['live_eur'] : ( null !== $r['supplier_cost_eur'] ? (float) $r['supplier_cost_eur'] : null );
		$edit    = get_edit_post_link( $product && $product->get_parent_id() ? $product->get_parent_id() : (int) $r['product_id'], 'raw' );
		return array(
			'name'       => $product ? $product->get_name() : sprintf( 'Product #%d (deleted)', $r['product_id'] ),
			'sku'        => $product ? $product->get_sku() : $r['sku'],
			'edit'       => $edit ? $edit : '',
			'local_cost' => null === $r['local_cost'] ? null : (float) $r['local_cost'],
			'eur'        => $eur,
			'sek'        => ( null !== $eur && $rate ) ? round( $eur * $rate['rate'], 2 ) : null,
			'price'      => $product ? $product->get_price() : null,
			'regular'    => $product ? $product->get_regular_price() : '',
			'sale'       => $product ? $product->get_sale_price() : '',
			'stock'      => null !== $r['live_stock'] ? (int) $r['live_stock'] : ( null === $r['supplier_stock'] ? null : (int) $r['supplier_stock'] ),
			'when'       => $r['transitioned_at'] ? wp_date( 'Y-m-d H:i', strtotime( $r['transitioned_at'] . ' UTC' ) ) : '—',
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin output (registered only in wp-admin)
	 * ------------------------------------------------------------------- */

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
	}

	private static function allowed() {
		return is_admin() && current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Dashboard-wide notice. Not dismissible with ×: it stays until marked as reviewed.
	 */
	public static function render_notice() {
		if ( ! self::allowed() ) {
			return;
		}
		$rows = self::rows( self::PENDING, 6 );
		if ( ! $rows ) {
			return;
		}
		$total = self::pending_count();
		echo '<div class="notice notice-warning urme-price-review-notice"><p><strong>URME Supplier Sync – price review required</strong></p><ul>';
		foreach ( array_slice( $rows, 0, 5 ) as $r ) {
			$v = self::view( $r );
			echo '<li style="margin:8px 0">';
			printf(
				'<strong>Price review required: SKU %1$s has switched to Dropshipping.</strong><br>%2$s · Switched %3$s · Supplier stock: %4$s · Supplier cost: %5$s / %6$s · Previous local cost: %7$s · Current price: %8$s<br>',
				esc_html( '' !== $v['sku'] ? $v['sku'] : '—' ),
				$v['edit'] ? '<a href="' . esc_url( $v['edit'] ) . '">' . esc_html( $v['name'] ) . '</a>' : esc_html( $v['name'] ),
				esc_html( $v['when'] ),
				esc_html( null === $v['stock'] ? '—' : (string) $v['stock'] ),
				esc_html( null === $v['eur'] ? '—' : '€' . number_format_i18n( $v['eur'], 2 ) ),
				esc_html( null === $v['sek'] ? '—' : number_format_i18n( $v['sek'], 2 ) . ' kr' ),
				esc_html( null === $v['local_cost'] ? 'not recorded' : number_format_i18n( $v['local_cost'], 2 ) . ' kr' ),
				esc_html( self::price_text( $v ) )
			);
			if ( $v['edit'] ) {
				printf( '<a class="button button-primary button-small" href="%s">Review price</a> ', esc_url( $v['edit'] ) );
			}
			echo self::reviewed_button( (int) $r['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</li>';
		}
		echo '</ul>';
		if ( $total > 5 ) {
			printf( '<p>%d more waiting.</p>', (int) ( $total - 5 ) );
		}
		printf( '<p><a href="%s">Open Price Review (%d)</a></p></div>', esc_url( URME_SS_Admin::url( array( 'tab' => 'reviews' ) ) ), (int) $total );
	}

	public static function price_text( array $v ) {
		if ( null === $v['price'] || '' === $v['price'] ) {
			return '—';
		}
		$text = number_format_i18n( (float) $v['price'], 2 ) . ' kr';
		if ( '' !== $v['sale'] ) {
			$text .= sprintf( ' (sale; regular %s kr)', number_format_i18n( (float) $v['regular'], 2 ) );
		}
		return $text;
	}

	public static function reviewed_button( $id ) {
		$back = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : admin_url();
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'
			. wp_nonce_field( 'urme_ss', '_wpnonce', false, false )
			. '<input type="hidden" name="action" value="urme_ss"><input type="hidden" name="do" value="review_done">'
			. '<input type="hidden" name="review_id" value="' . (int) $id . '">'
			. '<input type="hidden" name="_back" value="' . esc_attr( $back ) . '">'
			. '<button type="submit" class="button button-small">Mark as reviewed</button></form>';
	}
}
