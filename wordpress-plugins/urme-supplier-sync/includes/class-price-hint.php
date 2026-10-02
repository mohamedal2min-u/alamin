<?php
/**
 * Selling price hint (admin only). Pure calculation: never writes anything,
 * never makes a request, and reads only the settings option and the cached rate.
 *
 * Listed price P: the customer pays P × (1 − coupon); revenue excl. VAT is that / (1 + VAT);
 * the payment fee is a share of what the customer pays. The hint is the lowest P,
 * rounded up to the rounding step, whose estimated profit is at least the target: a
 * percentage of the watch's cost (PURCHASE_PRICE + extra cost, in SEK).
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Price_Hint {

	/**
	 * Settings and EUR/SEK rate for one page render (read once, not per row).
	 *
	 * @return array{extra_eur: float, coupon: float, fee: float, vat: float, profit_pct: float, step: int, rate: float|null}
	 */
	public static function context() {
		$s    = URME_SS_Settings::all();
		$rate = URME_SS_Rates::current();
		return array(
			'extra_eur' => (float) $s['hint_extra_eur'],
			'coupon'    => (float) $s['hint_coupon_pct'] / 100,
			'fee'       => (float) $s['hint_fee_pct'] / 100,
			'vat'       => (float) $s['hint_vat_pct'] / 100,
			'profit_pct' => (float) $s['hint_profit_pct'] / 100,
			'step'      => max( 1, (int) $s['hint_round_sek'] ),
			'rate'      => $rate ? (float) $rate['rate'] : null,
		);
	}

	/**
	 * Price hint for one supplier watch, or null when a value it needs is missing.
	 *
	 * @param mixed $purchase_eur Supplier PURCHASE_PRICE (EUR, VAT 0%), null when missing.
	 * @param array $ctx          From context().
	 * @return array{price: int, paid: float, net: float, fee: float, cost: float, profit: float, target: float}|null
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
		$target = $cost * max( 0.0, (float) $ctx['profit_pct'] ); // Profit: a percentage of the cost.
		$min    = ( $cost + $target ) / $margin;
		$price  = (int) ( ceil( round( $min, 6 ) / $step ) * $step ); // Always up, never down.
		$out    = self::breakdown( $price, $cost, $ctx );
		while ( $out['profit'] < $target - 0.000001 ) { // Guard against float rounding.
			$price += $step;
			$out    = self::breakdown( $price, $cost, $ctx );
		}
		$out['target'] = $target;
		return $out;
	}

	/**
	 * The suggested price raised to the nearest price ending in 98 at or above it
	 * (2 910 → 2 998, 4 680 → 4 698), or null when there is no suggested price.
	 *
	 * @param mixed $purchase_eur Supplier PURCHASE_PRICE (EUR, VAT 0%).
	 */
	public static function price_98( $purchase_eur, array $ctx ) {
		$h = self::calculate( $purchase_eur, $ctx );
		if ( ! $h ) {
			return null;
		}
		return (int) ( max( 0, ceil( ( $h['price'] - 98 ) / 100 ) ) * 100 + 98 );
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

	/**
	 * A profit share as a whole percentage (e.g. 0.4239 → "42%").
	 */
	private static function whole_pct( $share ) {
		return number_format_i18n( round( $share * 100 ) ) . '%';
	}

	private static function pct( $share ) {
		return rtrim( rtrim( number_format( $share * 100, 2, '.', '' ), '0' ), '.' ) . '%';
	}

	/**
	 * Estimated profit at the price the watch sells for now (same model: coupon allowance, VAT,
	 * payment fee, cost incl. the extra cost), or null when the cost, rate or price is missing.
	 *
	 * @param mixed $price        Current selling price in SEK (VAT included).
	 * @param mixed $purchase_eur Supplier PURCHASE_PRICE (EUR, VAT 0%).
	 * @return array{price: float, paid: float, net: float, fee: float, cost: float, profit: float}|null
	 */
	public static function profit_at( $price, $purchase_eur, array $ctx ) {
		if ( ! is_numeric( $price ) || (float) $price <= 0 || ! is_numeric( $purchase_eur ) || (float) $purchase_eur <= 0 || empty( $ctx['rate'] ) || $ctx['rate'] <= 0 ) {
			return null;
		}
		$cost = ( (float) $purchase_eur + $ctx['extra_eur'] ) * $ctx['rate'];
		return self::breakdown( (float) $price, $cost, $ctx );
	}

	/**
	 * Full hint (Supplier catalog column), plus the profit at the watch's current price when known.
	 *
	 * @param mixed $current_price The URME product's current selling price (SEK), or null.
	 */
	public static function html( $purchase_eur, array $ctx, $current_price = null ) {
		$h = self::calculate( $purchase_eur, $ctx );
		if ( ! $h ) {
			return '<span class="urme-muted urme-hint-na">Price hint unavailable</span>';
		}
		$now = self::profit_at( $current_price, $purchase_eur, $ctx );
		$own = '';
		if ( $now ) {
			$ok  = $now['profit'] >= $h['target'] - 0.000001;
			$own = sprintf(
				'<div class="urme-hint-now %1$s">Your price %2$s: profit <strong>%3$s</strong> (%4$s of cost)</div>',
				$ok ? 'urme-good' : 'urme-bad',
				esc_html( self::kr( $now['price'] ) ),
				esc_html( self::kr( $now['profit'] ) ),
				esc_html( self::whole_pct( $now['profit'] / $now['cost'] ) )
			);
		}
		$extra = rtrim( rtrim( number_format( $ctx['extra_eur'], 2, '.', '' ), '0' ), '.' );
		return sprintf(
			'<div class="urme-hint"><strong>Suggested price: %s</strong><br><span class="urme-hint-detail">After %s coupon: %s<br>Klarna %s: %s<br>Cost incl. +%s EUR: %s<br>Estimated profit: %s (%s of cost)</span></div>',
			esc_html( self::kr( $h['price'] ) ),
			esc_html( self::pct( $ctx['coupon'] ) ),
			esc_html( self::kr( $h['paid'] ) ),
			esc_html( self::pct( $ctx['fee'] ) ),
			esc_html( self::kr( $h['fee'] ) ),
			esc_html( $extra ),
			esc_html( self::kr( $h['cost'] ) ),
			esc_html( self::kr( $h['profit'] ) ),
			esc_html( self::whole_pct( $h['profit'] / $h['cost'] ) )
		) . $own;
	}
}
