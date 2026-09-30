<?php
/**
 * Selling price hint (admin only). Pure calculation: never writes anything,
 * never makes a request, and reads only the settings option and the cached rate.
 *
 * Listed price P: the customer pays P × (1 − coupon); revenue excl. VAT is that / (1 + VAT);
 * the payment fee is a share of what the customer pays. The hint is the lowest P,
 * rounded up to the rounding step, whose estimated profit is at least the target.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Price_Hint {

	/**
	 * Settings and EUR/SEK rate for one page render (read once, not per row).
	 *
	 * @return array{extra_eur: float, coupon: float, fee: float, vat: float, profit: float, step: int, rate: float|null}
	 */
	public static function context() {
		$s    = URME_SS_Settings::all();
		$rate = URME_SS_Rates::current();
		return array(
			'extra_eur' => (float) $s['hint_extra_eur'],
			'coupon'    => (float) $s['hint_coupon_pct'] / 100,
			'fee'       => (float) $s['hint_fee_pct'] / 100,
			'vat'       => (float) $s['hint_vat_pct'] / 100,
			'profit'    => (float) $s['hint_profit_sek'],
			'step'      => max( 1, (int) $s['hint_round_sek'] ),
			'rate'      => $rate ? (float) $rate['rate'] : null,
		);
	}

	/**
	 * Price hint for one supplier watch, or null when a value it needs is missing.
	 *
	 * @param mixed $purchase_eur Supplier PURCHASE_PRICE (EUR, VAT 0%), null when missing.
	 * @param array $ctx          From context().
	 * @return array{price: int, paid: float, net: float, fee: float, cost: float, profit: float}|null
	 */
	public static function calculate( $purchase_eur, array $ctx ) {
		if ( null === $purchase_eur || '' === $purchase_eur || ! is_numeric( $purchase_eur ) || (float) $purchase_eur <= 0 || empty( $ctx['rate'] ) || $ctx['rate'] <= 0 ) {
			return null; // Never guess a missing cost or rate.
		}
		$pay_share = 1 - $ctx['coupon'];
		// Profit per SEK of listed price: paid share excl. VAT, minus the fee on the paid amount.
		$margin = $pay_share * ( 1 / ( 1 + $ctx['vat'] ) - $ctx['fee'] );
		if ( $margin <= 0 ) {
			return null; // These settings leave nothing to cover the cost.
		}
		$cost  = ( (float) $purchase_eur + $ctx['extra_eur'] ) * $ctx['rate']; // PURCHASE_PRICE is VAT 0%: no VAT added.
		$step  = max( 1, (int) $ctx['step'] );
		$min   = ( $cost + $ctx['profit'] ) / $margin;
		$price = (int) ( ceil( round( $min, 6 ) / $step ) * $step ); // Always up, never down.
		$out   = self::breakdown( $price, $cost, $ctx );
		while ( $out['profit'] < $ctx['profit'] - 0.000001 ) { // Guard against float rounding.
			$price += $step;
			$out    = self::breakdown( $price, $cost, $ctx );
		}
		return $out;
	}

	private static function breakdown( $price, $cost, array $ctx ) {
		$paid = $price * ( 1 - $ctx['coupon'] );
		$net  = $paid / ( 1 + $ctx['vat'] );
		$fee  = $paid * $ctx['fee'];
		return array(
			'price'  => $price,
			'paid'   => $paid,
			'net'    => $net,
			'fee'    => $fee,
			'cost'   => $cost,
			'profit' => $net - $fee - $cost,
		);
	}

	private static function kr( $v ) {
		return number_format_i18n( round( $v ) ) . ' kr';
	}

	private static function pct( $share ) {
		return rtrim( rtrim( number_format( $share * 100, 2, '.', '' ), '0' ), '.' ) . '%';
	}

	/**
	 * Full hint (Supplier catalog column).
	 */
	public static function html( $purchase_eur, array $ctx ) {
		$h = self::calculate( $purchase_eur, $ctx );
		if ( ! $h ) {
			return '<span class="urme-muted urme-hint-na">Price hint unavailable</span>';
		}
		$extra = rtrim( rtrim( number_format( $ctx['extra_eur'], 2, '.', '' ), '0' ), '.' );
		return sprintf(
			'<div class="urme-hint"><strong>Suggested price: %s</strong><br><span class="urme-hint-detail">After %s coupon: %s<br>Klarna %s: %s<br>Cost incl. +%s EUR: %s<br>Estimated profit: %s</span></div>',
			esc_html( self::kr( $h['price'] ) ),
			esc_html( self::pct( $ctx['coupon'] ) ),
			esc_html( self::kr( $h['paid'] ) ),
			esc_html( self::pct( $ctx['fee'] ) ),
			esc_html( self::kr( $h['fee'] ) ),
			esc_html( $extra ),
			esc_html( self::kr( $h['cost'] ) ),
			esc_html( self::kr( $h['profit'] ) )
		);
	}

	/**
	 * Compact hint (Selected watches).
	 */
	public static function short_html( $purchase_eur, array $ctx ) {
		$h = self::calculate( $purchase_eur, $ctx );
		if ( ! $h ) {
			return '<small class="urme-muted urme-hint-na">Price hint unavailable</small>';
		}
		return sprintf(
			'<small class="urme-hint">Suggested price: <strong>%s</strong><br>Est. profit %s</small>',
			esc_html( self::kr( $h['price'] ) ),
			esc_html( self::kr( $h['profit'] ) )
		);
	}
}
