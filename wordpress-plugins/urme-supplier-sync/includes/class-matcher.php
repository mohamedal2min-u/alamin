<?php
/**
 * Matches supplier items to WooCommerce products by SKU (PRODUCTNO) and EAN (ITEM_ID).
 *
 * The store's SKUs and EANs are loaded into memory once (a few small queries),
 * so the whole catalog can be matched without one query per watch.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Matcher {

	const EXISTS = 'exists';
	const NONE   = 'none';
	const REVIEW = 'review';

	/**
	 * @var array{sku: array, ean: array, time: int}|null
	 */
	private static $index = null;

	public static function reset() {
		self::$index = null;
	}

	/**
	 * "SUR 311-P1" == "sur311p1".
	 */
	public static function normalize_sku( $sku ) {
		return strtoupper( str_replace( array( ' ', '-', '.', '/' ), '', trim( (string) $sku ) ) );
	}

	/**
	 * EAN-13, UPC-12 and GTIN-14 spellings share the same digits without leading zeros.
	 */
	public static function normalize_ean( $ean ) {
		$digits = preg_replace( '/\D/', '', (string) $ean );
		return strlen( $digits ) >= 8 ? ltrim( $digits, '0' ) : '';
	}

	/**
	 * SKU and EAN lookup tables for all live products and variations.
	 */
	private static function index() {
		// Rebuilt at most every 30 seconds within one long request.
		if ( null !== self::$index && time() - self::$index['time'] < 30 ) {
			return self::$index;
		}
		global $wpdb;
		$alive = "p.post_type IN ('product','product_variation') AND p.post_status NOT IN ('trash','auto-draft')";
		$index = array(
			'sku'  => array(),
			'ean'  => array(),
			'time' => time(),
		);

		// phpcs:disable WordPress.DB
		$rows = $wpdb->get_results( "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE {$alive} AND pm.meta_key = '_sku' AND pm.meta_value <> ''", ARRAY_N );
		foreach ( $rows as $r ) {
			$index['sku'][ self::normalize_sku( $r[1] ) ][ (int) $r[0] ] = true;
		}

		$keys = array_merge( array( '_global_unique_id' ), URME_SS_Store::KNOWN_GTIN_KEYS );
		$in   = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE {$alive} AND pm.meta_key IN ({$in}) AND pm.meta_value <> ''", $keys ), ARRAY_N );
		if ( taxonomy_exists( 'pa_ean' ) ) {
			$rows = array_merge(
				$rows,
				$wpdb->get_results(
					"SELECT tr.object_id, t.name FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
					INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
					WHERE tt.taxonomy = 'pa_ean' AND {$alive}",
					ARRAY_N
				)
			);
		}
		// phpcs:enable
		foreach ( $rows as $r ) {
			$ean = self::normalize_ean( $r[1] );
			if ( '' !== $ean ) {
				$index['ean'][ $ean ][ (int) $r[0] ] = true;
			}
		}

		self::$index = $index;
		return $index;
	}

	/**
	 * Match one supplier item.
	 *
	 * @param array $item Needs product_no and item_id.
	 * @return array{status: string, product_id: int, method: string, candidates: array<int, string[]>}
	 */
	public static function match( array $item ) {
		$index = self::index();
		$found = array();

		$sku = self::normalize_sku( $item['product_no'] ?? '' );
		if ( '' !== $sku && isset( $index['sku'][ $sku ] ) ) {
			foreach ( array_keys( $index['sku'][ $sku ] ) as $id ) {
				$found[ $id ][] = 'sku';
			}
		}
		$ean = self::normalize_ean( $item['item_id'] ?? '' );
		if ( '' !== $ean && isset( $index['ean'][ $ean ] ) ) {
			foreach ( array_keys( $index['ean'][ $ean ] ) as $id ) {
				$found[ $id ][] = 'ean';
			}
		}

		if ( 1 === count( $found ) ) {
			return array(
				'status'     => self::EXISTS,
				'product_id' => (int) key( $found ),
				'method'     => implode( '+', current( $found ) ),
				'candidates' => $found,
			);
		}
		return array(
			'status'     => $found ? self::REVIEW : self::NONE,
			'product_id' => 0,
			'method'     => '',
			'candidates' => $found,
		);
	}

	/**
	 * Re-check every catalog watch against the store and store the result.
	 * Only rows whose result changed are written.
	 *
	 * @return array Counts per status, plus "changed".
	 */
	public static function refresh_catalog() {
		global $wpdb;
		self::reset();
		$table = URME_SS_DB::catalog_table();
		$stats = array(
			self::EXISTS => 0,
			self::NONE   => 0,
			self::REVIEW => 0,
			'changed'    => 0,
		);

		// Grouped writes: rows moving to "none" or "review" share values, so they are updated in bulk.
		$bulk = array();
		$last = 0;
		do {
			// phpcs:ignore WordPress.DB
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, product_no, item_id, match_status, match_product_id, match_method, match_candidates FROM {$table} WHERE id > %d ORDER BY id LIMIT 5000", $last ), ARRAY_A );
			foreach ( $rows as $row ) {
				$last = (int) $row['id'];
				$m    = self::match( $row );
				++$stats[ $m['status'] ];

				$candidates = self::REVIEW === $m['status'] ? substr( implode( ',', array_keys( $m['candidates'] ) ), 0, 255 ) : '';
				if ( $row['match_status'] === $m['status'] && (int) $row['match_product_id'] === $m['product_id'] && $row['match_method'] === $m['method'] && $row['match_candidates'] === $candidates ) {
					continue;
				}
				++$stats['changed'];
				if ( self::EXISTS === $m['status'] || '' !== $candidates ) {
					$wpdb->update(
						$table,
						array(
							'match_status'     => $m['status'],
							'match_product_id' => $m['product_id'],
							'match_method'     => $m['method'],
							'match_candidates' => $candidates,
						),
						array( 'id' => $last )
					);
				} else {
					$bulk[] = $last;
				}
			}
		} while ( count( $rows ) === 5000 );

		foreach ( array_chunk( $bulk, 500 ) as $ids ) {
			// phpcs:ignore WordPress.DB
			$wpdb->query( "UPDATE {$table} SET match_status = 'none', match_product_id = 0, match_method = '', match_candidates = '' WHERE id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' );
		}

		update_option( 'urme_ss_match_checked', time(), false );
		return $stats;
	}
}
