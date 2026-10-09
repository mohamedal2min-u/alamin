<?php
/**
 * The suppliers: Relojitos (Spain, the original feed) and ILA Uhren (Germany).
 *
 * Each supplier has its own feed, its own cost per order (shipping + fees, EUR) and its own
 * delivery days. Catalog rows of a supplier other than the first have their item_key prefixed
 * ("DE:<EAN>"), so the same watch can be in the catalog once per supplier.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Suppliers {

	const MAIN = 'relo';

	/**
	 * Supplier ID => name, country, catalog key prefix, settings keys.
	 */
	public static function all() {
		return array(
			'relo' => array(
				'name'    => 'Relojitos',
				'country' => 'ES',
				'prefix'  => '',
				'url'     => 'feed_url',
				'extra'   => 'hint_extra_eur',
				'days'    => 'relo_days',
			),
			'ila'  => array(
				'name'    => 'ILA Uhren',
				'country' => 'DE',
				'prefix'  => 'DE:',
				'url'     => 'ila_feed_url',
				'extra'   => 'ila_extra_eur',
				'days'    => 'ila_days',
			),
		);
	}

	public static function ids() {
		return array_keys( self::all() );
	}

	/**
	 * A known supplier ID, else the main supplier (old rows and links have none).
	 */
	public static function id( $id ) {
		return isset( self::all()[ (string) $id ] ) ? (string) $id : self::MAIN;
	}

	public static function get( $id ) {
		return self::all()[ self::id( $id ) ];
	}

	public static function name( $id ) {
		return self::get( $id )['name'];
	}

	/**
	 * The supplier of a catalog item_key, from its prefix.
	 */
	public static function of_key( $item_key ) {
		foreach ( self::all() as $id => $s ) {
			if ( '' !== $s['prefix'] && 0 === strpos( (string) $item_key, $s['prefix'] ) ) {
				return $id;
			}
		}
		return self::MAIN;
	}

	public static function feed_url( $id ) {
		return (string) URME_SS_Settings::get( self::get( $id )['url'] );
	}

	/**
	 * Suppliers with a feed URL, in order (the main supplier first).
	 *
	 * @return string[]
	 */
	public static function active() {
		return array_values(
			array_filter(
				self::ids(),
				static function ( $id ) {
					return '' !== self::feed_url( $id );
				}
			)
		);
	}

	/**
	 * Shipping + fees per order (EUR).
	 */
	public static function extra_eur( $id ) {
		return max( 0.0, (float) URME_SS_Settings::get( self::get( $id )['extra'] ) );
	}

	/**
	 * Delivery days to the customer, e.g. array( 3, 6 ).
	 *
	 * @return int[]
	 */
	public static function days( $id ) {
		return self::parse_days( (string) URME_SS_Settings::get( self::get( $id )['days'] ) );
	}

	/**
	 * "3-6", "3–6", "3 - 6" or "4" → array( min, max ); "3-6" when unreadable.
	 *
	 * @return int[]
	 */
	public static function parse_days( $raw ) {
		if ( preg_match( '/^\s*(\d{1,2})\s*(?:[-–—]\s*(\d{1,2}))?\s*$/u', (string) $raw, $m ) ) {
			$min = (int) $m[1];
			$max = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : $min;
			return array( min( $min, $max ), max( $min, $max ) );
		}
		return array( 3, 6 );
	}

	/**
	 * Price hint settings with this supplier's extra cost.
	 */
	public static function hint( array $ctx, $id ) {
		$ctx['extra_eur'] = self::extra_eur( $id );
		return $ctx;
	}

	/**
	 * Small country flag (inline SVG, so it also shows on Windows, which has no flag emoji).
	 *
	 * @param bool $name Also print the supplier name.
	 */
	public static function flag( $id, $name = false ) {
		$id = self::id( $id );
		$s  = self::get( $id );
		$svg = 'DE' === $s['country']
			? '<svg viewBox="0 0 5 3" width="18" height="12" aria-hidden="true"><rect width="5" height="3" fill="#ffce00"/><rect width="5" height="2" fill="#dd0000"/><rect width="5" height="1" fill="#000"/></svg>'
			: '<svg viewBox="0 0 3 2" width="18" height="12" aria-hidden="true"><rect width="3" height="2" fill="#c60b1e"/><rect y=".5" width="3" height="1" fill="#ffc400"/></svg>';
		return sprintf(
			'<span class="urme-flag" title="%1$s (%2$s)">%3$s%4$s</span>',
			esc_attr( $s['name'] ),
			esc_attr( $s['country'] ),
			$svg,
			$name ? ' <span class="urme-flag-name">' . esc_html( $s['name'] ) . '</span>' : '<span class="screen-reader-text">' . esc_html( $s['name'] ) . '</span>'
		);
	}

	public static function flag_css() {
		return '.urme-flag{display:inline-flex;align-items:center;gap:4px;white-space:nowrap;vertical-align:middle}.urme-flag svg{display:block;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.15)}';
	}

	/**
	 * The cheapest place to buy one watch now: among the catalog rows of the same watch (one per
	 * supplier), in the feed of a supplier whose feed is fresh, with stock, cost price + the
	 * supplier's cost per order is the lowest. Without stock anywhere: the linked row itself if it
	 * is still in a fresh feed, else any row still in a fresh feed (the product goes out of stock).
	 *
	 * @param array    $own   The linked catalog row (item_key, supplier, purchase_price, stock, in_feed).
	 * @param array[]  $other Rows of the same watch at the other suppliers.
	 * @param string[] $fresh Suppliers whose feed may be used now.
	 * @return array|null The chosen row, or null when no row is in a usable feed.
	 */
	public static function choose( array $own, array $other, array $fresh ) {
		$rows   = array_merge( array( $own ), $other );
		$usable = array();
		foreach ( $rows as $r ) {
			if ( ! empty( $r['item_key'] ) && (int) ( $r['in_feed'] ?? 0 ) && in_array( self::of_key( $r['item_key'] ), $fresh, true ) ) {
				$usable[] = $r;
			}
		}
		if ( ! $usable ) {
			return null;
		}
		$best = null;
		foreach ( $usable as $r ) {
			if ( null === $r['stock'] || (int) $r['stock'] < 1 || null === $r['purchase_price'] || (float) $r['purchase_price'] <= 0 ) {
				continue;
			}
			$total = (float) $r['purchase_price'] + self::extra_eur( self::of_key( $r['item_key'] ) );
			if ( null === $best || $total < $best[0] - 0.00001 ) {
				$best = array( $total, $r );
			}
		}
		if ( $best ) {
			return $best[1];
		}
		foreach ( $usable as $r ) {
			if ( $r['item_key'] === $own['item_key'] ) {
				return $r;
			}
		}
		return $usable[0];
	}
}
