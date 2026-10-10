<?php
/**
 * Custom tables: the cached supplier catalog, the product links and the
 * local-inventory allocation ledger.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_DB {

	/** Supplier catalog: watches cheaper than this (EUR) are not listed. */
	const MIN_EUR = 10;

	public static function catalog_table() {
		global $wpdb;
		return $wpdb->prefix . 'urme_ss_catalog';
	}

	public static function links_table() {
		global $wpdb;
		return $wpdb->prefix . 'urme_ss_links';
	}

	/**
	 * One row per WooCommerce order line of a linked product: how many of its
	 * reduced units came from local (URME-owned) stock and how many from the supplier.
	 */
	public static function alloc_table() {
		global $wpdb;
		return $wpdb->prefix . 'urme_ss_alloc';
	}

	/**
	 * Price reviews of versions 1.1.0–1.5.1 (the feature was removed in 1.6.0; the table is kept).
	 */
	public static function reviews_table() {
		global $wpdb;
		return $wpdb->prefix . 'urme_ss_price_reviews';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$catalog = self::catalog_table();
		$links   = self::links_table();
		$alloc   = self::alloc_table();

		// item_key = ITEM_ID (EAN) when present, otherwise "P:" . PRODUCTNO; prefixed for suppliers
		// other than the main one ("DE:<EAN>"), so a watch can be in the catalog once per supplier.
		dbDelta(
			"CREATE TABLE {$catalog} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  item_key varchar(100) NOT NULL,
  item_id varchar(64) NOT NULL DEFAULT '',
  product_no varchar(100) NOT NULL DEFAULT '',
  manufacturer varchar(100) NOT NULL DEFAULT '',
  product_name varchar(255) NOT NULL DEFAULT '',
  category varchar(50) NOT NULL DEFAULT '',
  subcategory varchar(100) NOT NULL DEFAULT '',
  purchase_price decimal(12,4) NULL,
  rrp decimal(12,4) NULL,
  stock int(11) NULL,
  img_url varchar(1000) NOT NULL DEFAULT '',
  data_hash char(32) NOT NULL DEFAULT '',
  in_feed tinyint(1) NOT NULL DEFAULT 1,
  first_seen datetime NOT NULL,
  updated_at datetime NOT NULL,
  missing_since datetime NULL,
  match_status varchar(10) NOT NULL DEFAULT '',
  match_product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  match_method varchar(20) NOT NULL DEFAULT '',
  match_candidates varchar(255) NOT NULL DEFAULT '',
  supplier varchar(10) NOT NULL DEFAULT 'relo',
  PRIMARY KEY  (id),
  UNIQUE KEY item_key (item_key),
  KEY item_id (item_id),
  KEY supplier (supplier,in_feed),
  KEY product_no (product_no),
  KEY manufacturer (manufacturer),
  KEY category (category,in_feed),
  KEY first_seen (first_seen),
  KEY match_status (match_status)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$links} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  item_key varchar(100) NOT NULL,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sync_enabled tinyint(1) NOT NULL DEFAULT 1,
  match_method varchar(20) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  last_synced_at datetime NULL,
  last_stock int(11) NULL,
  last_cost_eur decimal(12,4) NULL,
  last_cost_sek decimal(12,2) NULL,
  last_rate decimal(10,6) NULL,
  last_status varchar(20) NOT NULL DEFAULT '',
  last_message varchar(255) NOT NULL DEFAULT '',
  stock_mode varchar(20) NOT NULL DEFAULT 'supplier',
  local_qty int(11) NOT NULL DEFAULT 0,
  local_cost decimal(12,2) NULL,
  needs_stock_apply tinyint(1) NOT NULL DEFAULT 0,
  mode_changed_at datetime NULL,
  mode_note varchar(255) NOT NULL DEFAULT '',
  supplier varchar(10) NOT NULL DEFAULT '',
  source_key varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY item_key (item_key),
  KEY product_id (product_id)
) {$charset};"
		);

		// Authoritative local/supplier split per order line (see URME_SS_Inventory).
		dbDelta(
			"CREATE TABLE {$alloc} (
  order_item_id bigint(20) unsigned NOT NULL,
  order_id bigint(20) unsigned NOT NULL DEFAULT 0,
  link_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  last_reduced_stock int(11) NOT NULL DEFAULT 0,
  local_allocated int(11) NOT NULL DEFAULT 0,
  supplier_allocated int(11) NOT NULL DEFAULT 0,
  src_local int(11) NOT NULL DEFAULT 0,
  src_supplier int(11) NOT NULL DEFAULT 0,
  ret_local int(11) NOT NULL DEFAULT 0,
  ret_supplier int(11) NOT NULL DEFAULT 0,
  origin varchar(10) NOT NULL DEFAULT 'sale',
  supplier varchar(10) NOT NULL DEFAULT '',
  source_key varchar(100) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (order_item_id),
  KEY link_id (link_id),
  KEY order_id (order_id)
) {$charset};"
		);

		// Price reviews of versions 1.1.0–1.5.1 (feature removed in 1.6.0; the table is kept).
		$reviews = self::reviews_table();
		dbDelta(
			"CREATE TABLE {$reviews} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  link_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  item_key varchar(100) NOT NULL DEFAULT '',
  sku varchar(100) NOT NULL DEFAULT '',
  transitioned_at datetime NOT NULL,
  local_cost decimal(12,2) NULL,
  supplier_cost_eur decimal(12,4) NULL,
  supplier_cost_sek decimal(12,2) NULL,
  supplier_stock int(11) NULL,
  regular_price varchar(30) NOT NULL DEFAULT '',
  sale_price varchar(30) NOT NULL DEFAULT '',
  status varchar(10) NOT NULL DEFAULT 'pending',
  reviewed_at datetime NULL,
  reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY link_id (link_id),
  KEY status (status)
) {$charset};"
		);

		self::ensure_transactional( array( $links, $alloc, $reviews ) );

		// Only record the new version when every column really exists; otherwise retry next load.
		$missing = self::missing_columns();
		if ( $missing ) {
			update_option( 'urme_ss_schema_error', implode( ', ', $missing ), false );
			URME_SS_Log::error( 'Database upgrade incomplete, missing: ' . implode( ', ', $missing ) . '. Order fulfillment labels are unavailable until this is fixed; the upgrade is retried on every load.' );
			return;
		}
		delete_option( 'urme_ss_schema_error' );
		// Orders whose stock was taken before the ledger existed have no known fulfilment source.
		add_option( 'urme_ss_ledger_since', time(), '', false );
		// Catalog rows that already exist (e.g. from 1.0.0) are never shown as NEW.
		add_option( 'urme_ss_new_since', current_time( 'mysql', true ), '', false );
		update_option( 'urme_ss_db_version', URME_SS_DB_VERSION, true ); // Autoloaded: read on every request.
	}

	/**
	 * Columns added in schema v3 that are missing from the database.
	 *
	 * @return string[]
	 */
	public static function missing_columns() {
		global $wpdb;
		$expected = array(
			self::catalog_table() => array( 'supplier', 'rrp' ),
			self::links_table() => array( 'stock_mode', 'local_qty', 'local_cost', 'needs_stock_apply', 'mode_changed_at', 'mode_note', 'supplier', 'source_key' ),
			self::alloc_table() => array( 'order_item_id', 'order_id', 'link_id', 'product_id', 'last_reduced_stock', 'local_allocated', 'supplier_allocated', 'src_local', 'src_supplier', 'ret_local', 'ret_supplier', 'origin', 'supplier', 'source_key', 'created_at', 'updated_at' ),
			self::reviews_table() => array( 'id', 'link_id', 'product_id', 'item_key', 'sku', 'transitioned_at', 'local_cost', 'supplier_cost_eur', 'supplier_cost_sek', 'supplier_stock', 'regular_price', 'sale_price', 'status', 'reviewed_at', 'reviewed_by' ),
		);
		$missing  = array();
		foreach ( $expected as $table => $columns ) {
			$have = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore WordPress.DB
			foreach ( $columns as $column ) {
				if ( ! in_array( $column, (array) $have, true ) ) {
					$missing[] = $table . '.' . $column;
				}
			}
		}
		return $missing;
	}

	/**
	 * The ledger needs transactions: make sure our own tables use InnoDB on MySQL/MariaDB.
	 */
	private static function ensure_transactional( array $tables ) {
		global $wpdb;
		if ( self::is_sqlite() ) {
			return;
		}
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB
			$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
			if ( $engine && 'innodb' !== strtolower( $engine ) ) {
				$wpdb->query( "ALTER TABLE {$table} ENGINE=InnoDB" ); // phpcs:ignore WordPress.DB
				URME_SS_Log::warning( sprintf( 'Converted %s from %s to InnoDB so inventory updates can use transactions.', $table, $engine ) );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Database engine
	 * ------------------------------------------------------------------- */

	public static function is_sqlite() {
		global $wpdb;
		return ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) || ( class_exists( 'WP_SQLite_DB' ) && $wpdb instanceof WP_SQLite_DB );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'urme_ss_db_version' ) !== URME_SS_DB_VERSION ) {
			self::install();
		}
		// 1.7.0: one-time backfill of the public `urme_fulfillment` product meta.
		if ( '1' !== get_option( 'urme_ss_fulfillment_meta' ) && URME_SS_DB_VERSION === get_option( 'urme_ss_db_version' ) ) {
			URME_SS_Product_Source::rebuild_meta();
			update_option( 'urme_ss_fulfillment_meta', '1', true ); // Autoloaded: no extra query per request.
		}
		// Versions before 1.5.7 stored the version without autoload: one query on every page. Switch once.
		$all = wp_load_alloptions();
		if ( ! isset( $all['urme_ss_db_version'] ) && URME_SS_DB_VERSION === get_option( 'urme_ss_db_version' ) ) {
			if ( function_exists( 'wp_set_option_autoload' ) ) {
				wp_set_option_autoload( 'urme_ss_db_version', true );
			} else {
				delete_option( 'urme_ss_db_version' );
				add_option( 'urme_ss_db_version', URME_SS_DB_VERSION, '', true );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Catalog
	 * ------------------------------------------------------------------- */

	/**
	 * Write a fully parsed and validated feed into the catalog.
	 *
	 * Only new or changed rows are written. Rows missing from the feed are
	 * flagged (never deleted) so selections and links survive a temporary gap.
	 *
	 * @param array  $items       item_key => packed item (see URME_SS_Feed::pack()).
	 * @param bool   $rewrite_all Write every row even if unchanged (manual forced refresh).
	 * @param string $supplier    Supplier of this feed: only its rows are compared and flagged missing.
	 * @return array Counts.
	 */
	public static function apply_feed( array $items, $rewrite_all = false, $supplier = URME_SS_Suppliers::MAIN ) {
		global $wpdb;
		$table = self::catalog_table();
		$now   = current_time( 'mysql', true );

		$existing = array();
		// Keyed by item_key: "hash|in_feed". Small even for tens of thousands of rows.
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT item_key, data_hash, in_feed FROM {$table} WHERE supplier = %s", $supplier ), ARRAY_N ) as $row ) { // phpcs:ignore WordPress.DB
			$existing[ $row[0] ] = $row[1] . '|' . $row[2];
		}

		$stats = array(
			'new'       => 0,
			'changed'   => 0,
			'unchanged' => 0,
			'missing'   => 0,
			'returned'  => 0,
		);
		$write = array();

		foreach ( $items as $key => $packed ) {
			$key = (string) $key;
			if ( ! isset( $existing[ $key ] ) ) {
				++$stats['new'];
				$write[] = URME_SS_Feed::unpack( $packed );
			} elseif ( $existing[ $key ] !== URME_SS_Feed::packed_hash( $packed ) . '|1' ) {
				if ( substr( $existing[ $key ], -2 ) === '|0' ) {
					++$stats['returned'];
				} else {
					++$stats['changed'];
				}
				$write[] = URME_SS_Feed::unpack( $packed );
			} else {
				++$stats['unchanged'];
				if ( $rewrite_all ) {
					$write[] = URME_SS_Feed::unpack( $packed );
				}
			}
			unset( $existing[ $key ] );

			if ( count( $write ) >= 200 ) {
				self::upsert( $write, $now );
				$write = array();
			}
		}
		if ( $write ) {
			self::upsert( $write, $now );
		}

		// Whatever is left in $existing was not in this feed.
		$gone = array();
		foreach ( $existing as $key => $state ) {
			if ( substr( $state, -2 ) === '|1' ) {
				$gone[] = (string) $key;
			}
		}
		foreach ( array_chunk( $gone, 200 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			// phpcs:ignore WordPress.DB
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET in_feed = 0, missing_since = %s WHERE item_key IN ({$placeholders})", array_merge( array( $now ), $chunk ) ) );
		}
		$stats['missing'] = count( $gone );

		return $stats;
	}

	private static function upsert( array $rows, $now ) {
		global $wpdb;
		$table  = self::catalog_table();
		$values = array();
		$params = array();

		foreach ( $rows as $r ) {
			$rrp      = isset( $r['rrp'] ) && null !== $r['rrp'] && '' !== $r['rrp'] ? $r['rrp'] : null;
			$values[] = '(%s,%s,%s,%s,%s,%s,%s,' . ( null === $r['purchase_price'] ? 'NULL' : '%f' ) . ',' . ( null === $r['stock'] ? 'NULL' : '%d' ) . ',%s,%s,1,%s,%s,NULL,%s,' . ( null === $rrp ? 'NULL' : '%f' ) . ')';
			array_push( $params, $r['item_key'], $r['item_id'], $r['product_no'], $r['manufacturer'], $r['product_name'], $r['category'], $r['subcategory'] );
			if ( null !== $r['purchase_price'] ) {
				$params[] = $r['purchase_price'];
			}
			if ( null !== $r['stock'] ) {
				$params[] = $r['stock'];
			}
			array_push( $params, $r['img_url'], $r['data_hash'], $now, $now, URME_SS_Suppliers::id( $r['supplier'] ?? '' ) );
			if ( null !== $rrp ) {
				$params[] = $rrp;
			}
		}

		$sql = "INSERT INTO {$table}
			(item_key, item_id, product_no, manufacturer, product_name, category, subcategory, purchase_price, stock, img_url, data_hash, in_feed, first_seen, updated_at, missing_since, supplier, rrp)
			VALUES " . implode( ',', $values ) . '
			ON DUPLICATE KEY UPDATE
				item_id = VALUES(item_id), product_no = VALUES(product_no), manufacturer = VALUES(manufacturer),
				product_name = VALUES(product_name), category = VALUES(category), subcategory = VALUES(subcategory),
				purchase_price = VALUES(purchase_price), stock = VALUES(stock), img_url = VALUES(img_url),
				data_hash = VALUES(data_hash), in_feed = 1, updated_at = VALUES(updated_at), missing_since = NULL, supplier = VALUES(supplier), rrp = VALUES(rrp)';

		$wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * SQL condition for "brand is enabled" (or disabled) on catalog alias c.
	 *
	 * @param bool  $enabled True for enabled brands, false for disabled ones.
	 * @param array $params  Prepared-statement parameters, appended to.
	 */
	private static function brand_condition( $enabled, array &$params ) {
		$keys = URME_SS_Settings::enabled_brand_keys();
		if ( ! $keys ) {
			return $enabled ? '1=0' : '1=1';
		}
		$params = array_merge( $params, $keys );
		return 'UPPER(c.manufacturer) ' . ( $enabled ? '' : 'NOT ' ) . 'IN (' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ')';
	}

	/**
	 * Search the cached catalog.
	 *
	 * @param array $args brand, productno, ean, q, in_stock, selected, show_missing, category, page, per_page.
	 * @return array{rows: array, total: int}
	 */
	public static function search_catalog( array $args ) {
		global $wpdb;
		$c = self::catalog_table();
		$l = self::links_table();

		$where  = array( '1=1' );
		$params = array();

		if ( empty( $args['show_missing'] ) ) {
			$where[] = 'c.in_feed = 1';
		}
		if ( ! empty( $args['category'] ) ) {
			$where[]  = 'c.category = %s';
			$params[] = $args['category'];
		} else {
			$cats = URME_SS_Settings::get( 'categories' );
			if ( $cats ) {
				$where[] = 'c.category IN (' . implode( ',', array_fill( 0, count( $cats ), '%s' ) ) . ')';
				$params  = array_merge( $params, $cats );
			}
		}
		if ( '' !== ( $args['item_key'] ?? '' ) ) {
			$where[]  = 'c.item_key = %s';
			$params[] = $args['item_key'];
		}
		if ( '' !== ( $args['brand'] ?? '' ) ) {
			$where[]  = 'c.manufacturer = %s';
			$params[] = $args['brand'];
		}
		if ( 'both' === ( $args['supplier'] ?? '' ) ) {
			// One row per watch sold by several suppliers: the main supplier's row (the others show on it).
			$keys = self::common_keys();
			if ( $keys ) {
				$where[] = 'c.item_key IN (' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ')';
				$params  = array_merge( $params, $keys );
			} else {
				$where[] = '1 = 0';
			}
		} elseif ( '' !== ( $args['supplier'] ?? '' ) ) {
			$where[]  = 'c.supplier = %s';
			$params[] = URME_SS_Suppliers::id( $args['supplier'] );
		}
		if ( '' !== ( $args['productno'] ?? '' ) ) {
			$where[]  = 'c.product_no LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['productno'] ) . '%';
		}
		if ( '' !== ( $args['ean'] ?? '' ) ) {
			$where[]  = 'c.item_id LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['ean'] ) . '%';
		}
		if ( '' !== ( $args['q'] ?? '' ) ) {
			$like     = '%' . $wpdb->esc_like( $args['q'] ) . '%';
			$where[]  = '(c.product_name LIKE %s OR c.manufacturer LIKE %s OR c.product_no LIKE %s OR c.item_id LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['new_only'] ) ) {
			$where[] = self::new_condition( $params );
		}
		// Watches under MIN_EUR cost (straps, cheap fashion items) are not shown; linked ones always are.
		$where[]  = '(c.purchase_price IS NULL OR c.purchase_price >= %f OR l.id IS NOT NULL)';
		$params[] = self::MIN_EUR;
		if ( ! empty( $args['in_stock'] ) ) {
			$where[] = 'c.stock > 0';
		}
		$manual = "(l.product_id > 0 AND l.match_method = 'manual')";
		switch ( $args['match'] ?? '' ) {
			case 'manual':
				$where[] = $manual;
				break;
			case URME_SS_Matcher::EXISTS:
			case URME_SS_Matcher::NONE:
			case URME_SS_Matcher::REVIEW:
				$where[]  = "c.match_status = %s AND (l.id IS NULL OR NOT {$manual})";
				$params[] = $args['match'];
				break;
		}
		if ( 'on' === ( $args['brand_sync'] ?? '' ) ) {
			$where[] = self::brand_condition( true, $params );
		} elseif ( 'off' === ( $args['brand_sync'] ?? '' ) ) {
			$where[] = self::brand_condition( false, $params );
		}
		if ( 'yes' === ( $args['selected'] ?? '' ) ) {
			$where[] = 'l.id IS NOT NULL';
		} elseif ( 'no' === ( $args['selected'] ?? '' ) ) {
			$where[] = 'l.id IS NULL';
		}

		// URME stock of the linked product, or of the unique confirmed match (joined only when filtering on it).
		$joins = '';
		$urme  = in_array( $args['urme_stock'] ?? '', array( 'in', 'out', 'unmanaged' ), true ) ? $args['urme_stock'] : '';
		$ready = ! empty( $args['ready'] );
		$hide  = ! empty( $args['hide_stocked'] );
		// 1.8.0: in the store first (linked or a unique URME match), then Needs review, then Not in URME;
		// inside each group the largest supplier stock first (out of stock last).
		$order = "CASE WHEN l.product_id > 0 OR c.match_status = 'exists' THEN 0 WHEN c.match_status = 'review' THEN 1 ELSE 2 END, c.stock DESC";
		if ( $urme || $ready || $hide ) {
			$pm      = $wpdb->postmeta;
			$joins   = " LEFT JOIN {$wpdb->posts} up ON up.ID = (CASE WHEN l.product_id > 0 THEN l.product_id WHEN c.match_status = 'exists' THEN c.match_product_id ELSE 0 END) AND up.post_status <> 'trash'"
				. " LEFT JOIN {$pm} ums ON ums.post_id = up.ID AND ums.meta_key = '_manage_stock'"
				. " LEFT JOIN {$pm} ust ON ust.post_id = up.ID AND ust.meta_key = '_stock'"
				. " LEFT JOIN {$pm} pms ON pms.post_id = up.post_parent AND up.post_parent > 0 AND pms.meta_key = '_manage_stock'"
				. " LEFT JOIN {$pm} pst ON pst.post_id = up.post_parent AND up.post_parent > 0 AND pst.meta_key = '_stock'";
			$own     = "COALESCE(ums.meta_value, '') = 'yes'";
			$managed = "({$own} OR (up.post_type = 'product_variation' AND COALESCE(pms.meta_value, '') = 'yes'))";
			$qty     = "(CASE WHEN {$own} THEN COALESCE(ust.meta_value, '0') ELSE COALESCE(pst.meta_value, '0') END + 0)";
			if ( 'in' === $urme ) {
				$where[] = "up.ID IS NOT NULL AND {$managed} AND {$qty} > 0";
			} elseif ( 'out' === $urme ) {
				$where[] = "up.ID IS NOT NULL AND {$managed} AND {$qty} <= 0";
			} elseif ( 'unmanaged' === $urme ) {
				$where[] = "up.ID IS NOT NULL AND NOT {$managed}";
			}
			if ( $ready ) {
				// Ready for supplier sync: brand on, unique match, not selected, still in the feed, URME stock 0.
				$where[] = self::brand_condition( true, $params );
				$where[] = "c.in_feed = 1 AND c.match_status = 'exists' AND c.match_product_id > 0 AND l.id IS NULL AND up.ID IS NOT NULL AND {$managed} AND {$qty} <= 0";
				$where[] = "NOT EXISTS (SELECT 1 FROM {$l} lx WHERE lx.product_id = c.match_product_id)";
			}
			if ( $hide ) {
				// Watches with URME stock need nothing here: hidden until their stock is 0 (never a selected one).
				$where[] = "NOT ( l.id IS NULL AND up.ID IS NOT NULL AND {$managed} AND {$qty} > 0 )";
			}
		}

		$per_page = max( 10, min( 200, (int) ( $args['per_page'] ?? 50 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$from     = "FROM {$c} c LEFT JOIN {$l} l ON l.item_key = c.item_key{$joins} WHERE " . implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) {$from}";
		$rows_sql  = "SELECT c.*, l.id AS link_id, l.product_id, l.sync_enabled, l.match_method AS link_method, l.stock_mode, l.local_qty, l.last_status AS link_status, l.last_message AS link_message, l.supplier AS link_supplier {$from} ORDER BY {$order}, c.manufacturer ASC, c.product_no ASC, c.item_key ASC LIMIT %d OFFSET %d";

		// phpcs:disable WordPress.DB
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );
		$rows  = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );
		// phpcs:enable

		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Brands in the catalog with watch and selection counts.
	 *
	 * @return array[] manufacturer, n (watches in feed), selected (links).
	 */
	public static function brands() {
		global $wpdb;
		$c    = self::catalog_table();
		$l    = self::links_table();
		$cats = URME_SS_Settings::get( 'categories' );
		$sql  = "SELECT c.manufacturer, SUM(c.in_feed = 1) AS n, COUNT(l.id) AS selected
			FROM {$c} c LEFT JOIN {$l} l ON l.item_key = c.item_key
			WHERE c.manufacturer <> '' AND (c.in_feed = 1 OR l.id IS NOT NULL)";
		if ( $cats ) {
			$sql = $wpdb->prepare( $sql . ' AND c.category IN (' . implode( ',', array_fill( 0, count( $cats ), '%s' ) ) . ')', $cats ); // phpcs:ignore WordPress.DB
		}
		return $wpdb->get_results( $sql . ' GROUP BY c.manufacturer ORDER BY c.manufacturer', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public static function match_counts() {
		global $wpdb;
		$c    = self::catalog_table();
		$l    = self::links_table();
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			"SELECT CASE WHEN l.product_id > 0 AND l.match_method = 'manual' THEN 'manual' ELSE c.match_status END AS s, COUNT(*) AS n
			FROM {$c} c LEFT JOIN {$l} l ON l.item_key = c.item_key WHERE c.in_feed = 1 GROUP BY s",
			ARRAY_A
		);
		return wp_list_pluck( $rows, 'n', 's' );
	}

	public static function get_item( $item_key ) {
		global $wpdb;
		$c = self::catalog_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$c} WHERE item_key = %s", $item_key ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/* ---------------------------------------------------------------------
	 * NEW supplier products (admin only)
	 * ------------------------------------------------------------------- */

	const NEW_DAYS = 4;

	/**
	 * A watch is NEW for 4 full days after it first appeared in the feed, but only if it
	 * appeared after the catalog baseline (first import / upgrade), so an initial import
	 * or an upgrade never marks the whole catalog as new. Uses first_seen, which is only
	 * set when a catalog row is first inserted.
	 */
	private static function new_condition( array &$params ) {
		$params[] = (string) get_option( 'urme_ss_new_since', '9999-12-31 00:00:00' );
		$params[] = gmdate( 'Y-m-d H:i:s', time() - self::NEW_DAYS * DAY_IN_SECONDS );
		return '(c.first_seen > %s AND c.first_seen > %s)';
	}

	/**
	 * Whole days since first seen (0 = within 24 h) if the row is NEW, else null.
	 */
	public static function new_age( array $row ) {
		$first = strtotime( $row['first_seen'] . ' UTC' );
		$since = strtotime( get_option( 'urme_ss_new_since', '9999-12-31 00:00:00' ) . ' UTC' );
		$age   = time() - $first;
		if ( ! $first || $first <= $since || $age >= self::NEW_DAYS * DAY_IN_SECONDS ) {
			return null;
		}
		return (int) floor( max( 0, $age ) / DAY_IN_SECONDS );
	}

	public static function new_count() {
		global $wpdb;
		$params = array();
		$cond   = self::new_condition( $params );
		$cats   = (array) URME_SS_Settings::get( 'categories' );
		$params = array_merge( $params, $cats );
		// phpcs:ignore WordPress.DB
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::catalog_table() . " c WHERE c.in_feed = 1 AND {$cond} AND c.category IN (" . implode( ',', array_fill( 0, count( $cats ), '%s' ) ) . ')', $params ) );
	}

	/**
	 * Called before a feed is written: if the catalog is empty this is a first import,
	 * and none of its rows should count as NEW.
	 */
	public static function catalog_is_empty( $supplier = null ) {
		global $wpdb;
		if ( null !== $supplier ) {
			return ! $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::catalog_table() . ' WHERE supplier = %s LIMIT 1', $supplier ) ); // phpcs:ignore WordPress.DB
		}
		return ! $wpdb->get_var( 'SELECT 1 FROM ' . self::catalog_table() . ' LIMIT 1' ); // phpcs:ignore WordPress.DB
	}

	/**
	 * A supplier's first import is its existing range, not "new": its rows get the NEW baseline as first seen.
	 */
	public static function mark_not_new( $supplier ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::catalog_table() . ' SET first_seen = %s WHERE supplier = %s', (string) get_option( 'urme_ss_new_since', current_time( 'mysql', true ) ), $supplier ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Main-supplier rows (in the feed) whose watch, by EAN, is also in another supplier's feed.
	 *
	 * @return string[] item_keys.
	 */
	public static function common_keys() {
		static $keys = null;
		if ( null !== $keys ) {
			return $keys;
		}
		global $wpdb;
		$main  = array();
		$other = array();
		foreach ( (array) $wpdb->get_results( 'SELECT item_key, item_id, supplier FROM ' . self::catalog_table() . " WHERE in_feed = 1 AND item_id <> ''", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$ean = URME_SS_Matcher::normalize_ean( $r['item_id'] );
			if ( '' === $ean ) {
				continue;
			}
			if ( URME_SS_Suppliers::MAIN === URME_SS_Suppliers::id( $r['supplier'] ) ) {
				$main[ $ean ] = $r['item_key'];
			} else {
				$other[ $ean ] = true;
			}
		}
		$keys = array_values( array_intersect_key( $main, $other ) );
		return $keys;
	}

	/**
	 * In-feed rows per supplier.
	 *
	 * @return array<string, int>
	 */
	public static function supplier_counts() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT supplier, SUM(in_feed = 1) AS n FROM ' . self::catalog_table() . ' GROUP BY supplier', ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_map( 'intval', wp_list_pluck( (array) $rows, 'n', 'supplier' ) );
	}

	/**
	 * The same watches at the other suppliers (same EAN), for many catalog rows at once.
	 *
	 * @param array[] $rows Rows with item_key and item_id (EAN).
	 * @return array<string, array[]> item_key => rows of the same watch at other suppliers.
	 */
	public static function other_sources( array $rows ) {
		global $wpdb;
		$by_ean = array();
		foreach ( $rows as $r ) {
			$ean = URME_SS_Matcher::normalize_ean( $r['item_id'] ?? '' );
			if ( '' !== $ean && ! empty( $r['item_key'] ) ) {
				$by_ean[ $ean ][] = (string) $r['item_key'];
			}
		}
		$out = array();
		if ( ! $by_ean ) {
			return $out;
		}
		// EAN-13 / UPC-12 / GTIN-14 spellings differ only by leading zeros.
		$variants = array();
		foreach ( array_keys( $by_ean ) as $ean ) {
			for ( $z = 0; $z <= 3 && strlen( $ean ) + $z <= 14; $z++ ) {
				$variants[] = str_repeat( '0', $z ) . $ean;
			}
		}
		foreach ( array_chunk( array_values( array_unique( $variants ) ), 500 ) as $chunk ) {
			// phpcs:ignore WordPress.DB
			$found = $wpdb->get_results( $wpdb->prepare( 'SELECT item_key, item_id, product_no, manufacturer, supplier, purchase_price, rrp, stock, in_feed FROM ' . self::catalog_table() . ' WHERE item_id IN (' . implode( ',', array_fill( 0, count( $chunk ), '%s' ) ) . ')', $chunk ), ARRAY_A );
			foreach ( (array) $found as $f ) {
				$ean = URME_SS_Matcher::normalize_ean( $f['item_id'] );
				foreach ( $by_ean[ $ean ] ?? array() as $key ) {
					if ( $key !== $f['item_key'] && URME_SS_Suppliers::of_key( $key ) !== URME_SS_Suppliers::id( $f['supplier'] ) ) {
						$out[ $key ][] = $f;
					}
				}
			}
		}
		return $out;
	}

	public static function catalog_counts() {
		global $wpdb;
		$c    = self::catalog_table();
		$row  = $wpdb->get_row( "SELECT SUM(in_feed = 1) AS in_feed, SUM(in_feed = 0) AS missing, COUNT(*) AS total FROM {$c}", ARRAY_A ); // phpcs:ignore WordPress.DB
		return array(
			'in_feed' => (int) ( $row['in_feed'] ?? 0 ),
			'missing' => (int) ( $row['missing'] ?? 0 ),
			'total'   => (int) ( $row['total'] ?? 0 ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Links (selected watches)
	 * ------------------------------------------------------------------- */

	public static function get_link( $item_key ) {
		global $wpdb;
		$l = self::links_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$l} WHERE item_key = %s", $item_key ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public static function get_link_by_id( $id ) {
		global $wpdb;
		$l = self::links_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$l} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Return the item_key already linked to a product, if any.
	 */
	public static function item_key_for_product( $product_id, $exclude_link_id = 0 ) {
		global $wpdb;
		$l = self::links_table();
		return $wpdb->get_var( $wpdb->prepare( "SELECT item_key FROM {$l} WHERE product_id = %d AND id <> %d LIMIT 1", $product_id, $exclude_link_id ) ); // phpcs:ignore WordPress.DB
	}

	public static function insert_link( $item_key, $product_id, $method ) {
		global $wpdb;
		URME_SS_Inventory::flush_cache();
		$wpdb->insert(
			self::links_table(),
			array(
				'item_key'     => $item_key,
				'product_id'   => (int) $product_id,
				'match_method' => $method,
				'sync_enabled' => 1,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%d', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		URME_SS_Product_Source::changed( array( $product_id ) );
		return $id;
	}

	public static function update_link( $id, array $data ) {
		global $wpdb;
		URME_SS_Inventory::flush_cache();
		// Only a change of product, sync on/off or mode can change a product's Fulfillment state.
		$state  = array_intersect_key( $data, array_flip( array( 'product_id', 'sync_enabled', 'stock_mode' ) ) );
		$before = $state ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ' . self::links_table() . ' WHERE id = %d', $id ) ) : 0; // phpcs:ignore WordPress.DB
		$result = $wpdb->update( self::links_table(), $data, array( 'id' => (int) $id ) );
		if ( $state ) {
			URME_SS_Product_Source::changed( array( $before, $data['product_id'] ?? 0 ) );
		}
		return $result;
	}

	public static function delete_link( $id ) {
		global $wpdb;
		URME_SS_Inventory::flush_cache();
		$product_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ' . self::links_table() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB
		$result     = $wpdb->delete( self::links_table(), array( 'id' => (int) $id ), array( '%d' ) );
		URME_SS_Product_Source::changed( array( $product_id ) );
		return $result;
	}

	/**
	 * Links joined with their catalog row.
	 *
	 * @param array $args status (all|linked|unlinked|missing|paused), q, page, per_page. per_page 0 = all.
	 */
	public static function get_links( array $args = array() ) {
		global $wpdb;
		$c = self::catalog_table();
		$l = self::links_table();

		$where  = array( '1=1' );
		$params = array();
		switch ( $args['status'] ?? 'all' ) {
			case 'linked':
				$where[] = 'l.product_id > 0';
				break;
			case 'unlinked':
				$where[] = 'l.product_id = 0';
				break;
			case 'missing':
				$where[] = '(c.id IS NULL OR c.in_feed = 0)';
				break;
			case 'paused':
				$where[] = 'l.sync_enabled = 0';
				break;
			case 'errors':
				$where[] = "l.last_status = 'error'";
				break;
			case 'brand_off':
				$where[] = self::brand_condition( false, $params );
				break;
			case 'local':
				$where[] = "l.stock_mode = 'local_first'";
				break;
			case 'dropship':
				$where[] = "l.product_id > 0 AND l.sync_enabled = 1 AND l.stock_mode <> 'local_first' AND " . self::brand_condition( true, $params );
				break;
			case 'lager':
				$where[] = "l.product_id > 0 AND (l.sync_enabled = 0 OR l.stock_mode = 'local_first' OR COALESCE((" . self::brand_condition( false, $params ) . '), 1) = 1)';
				break;
		}
		if ( ! empty( $args['enabled_brands_only'] ) ) {
			// Sync scope: enabled brand AND an enabled supplier category (WATCH in version 1).
			$where[] = self::brand_condition( true, $params );
			$cats    = (array) URME_SS_Settings::get( 'categories' );
			$where[] = 'c.category IN (' . implode( ',', array_fill( 0, count( $cats ), '%s' ) ) . ')';
			$params  = array_merge( $params, $cats );
		}
		if ( '' !== ( $args['q'] ?? '' ) ) {
			$like     = '%' . $wpdb->esc_like( $args['q'] ) . '%';
			$where[]  = '(c.product_name LIKE %s OR c.manufacturer LIKE %s OR c.product_no LIKE %s OR l.item_key LIKE %s)';
			$params   = array_merge( $params, array( $like, $like, $like, $like ) );
		}

		$from = "FROM {$l} l LEFT JOIN {$c} c ON c.item_key = l.item_key WHERE " . implode( ' AND ', $where );
		$sql  = "SELECT l.*, c.item_id, c.product_no, c.manufacturer, c.category, c.product_name, c.purchase_price, c.stock, c.img_url, c.in_feed, c.missing_since, c.id AS catalog_id, c.supplier AS item_supplier {$from} ORDER BY c.manufacturer ASC, c.product_no ASC";

		// phpcs:disable WordPress.DB
		$per_page = (int) ( $args['per_page'] ?? 0 );
		if ( $per_page > 0 ) {
			$count_sql = "SELECT COUNT(*) {$from}";
			$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );
			$page      = max( 1, (int) ( $args['page'] ?? 1 ) );
			$rows      = $wpdb->get_results( $wpdb->prepare( $sql . ' LIMIT %d OFFSET %d', array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );
		} else {
			$rows  = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A );
			$total = count( $rows );
		}
		// phpcs:enable

		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * The link for a WooCommerce product or variation, if any.
	 */
	public static function link_for_product( $product_id ) {
		global $wpdb;
		$l = self::links_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$l} WHERE product_id = %d AND product_id > 0 LIMIT 1", $product_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public static function link_counts() {
		global $wpdb;
		$c      = self::catalog_table();
		$l      = self::links_table();
		$p_on   = array();
		$p_off  = array();
		$on     = self::brand_condition( true, $p_on );
		$off    = self::brand_condition( false, $p_off );
		$params = array_merge( $p_on, $p_off );
		$sql    = "SELECT COUNT(*) AS selected,
				SUM(l.product_id > 0 AND l.sync_enabled = 1 AND l.stock_mode <> 'local_first' AND {$on}) AS dropship,
				SUM(l.product_id > 0) AS linked,
				SUM(l.product_id = 0) AS unlinked,
				SUM(l.sync_enabled = 0) AS paused,
				SUM(c.id IS NULL OR c.in_feed = 0) AS missing,
				SUM(l.last_status = 'error') AS errors,
				SUM(l.stock_mode = 'local_first') AS local_first,
				SUM({$off}) AS brand_off
			FROM {$l} l LEFT JOIN {$c} c ON c.item_key = l.item_key";
		$row    = array_map( 'intval', (array) $wpdb->get_row( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB
		// Every linked watch is Dropshipping or URME Lager.
		$row['lager'] = max( 0, ( $row['linked'] ?? 0 ) - ( $row['dropship'] ?? 0 ) );
		return $row;
	}
}
