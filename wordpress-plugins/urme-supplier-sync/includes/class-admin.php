<?php
/**
 * Admin screen: WooCommerce > Supplier Sync.
 *
 * Every page reads only the local catalog tables; the supplier feed is never
 * downloaded while browsing.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Admin {

	const NO_PAUSE = 'Pause is no longer available: a watch is either URME Lager or Dropshipping. Nothing was changed.';

	const SLUG = 'urme-supplier-sync';
	const CAP  = 'manage_woocommerce';

	/**
	 * Set while one admin page is being rendered.
	 *
	 * @var bool
	 */
	private static $in_render = false;

	/**
	 * NEW count for the page being rendered.
	 *
	 * @var int|null
	 */
	private static $new_count = null;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'admin_post_urme_ss', array( __CLASS__, 'handle' ) );
		// Supplier catalog row actions without a page reload.
		add_action( 'wp_ajax_urme_ss_row', array( __CLASS__, 'ajax_row' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( URME_SS_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		$pending = URME_SS_Price_Review::pending_count();
		$badge   = $pending ? sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $pending ) : '';
		add_submenu_page( 'woocommerce', 'Supplier Sync', 'Supplier Sync' . $badge, self::CAP, self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Open</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		if ( 'woocommerce_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'urme-ss-admin', URME_SS_URL . 'assets/admin.css', array(), URME_SS_VERSION );
		wp_enqueue_script( 'urme-ss-admin', URME_SS_URL . 'assets/admin.js', array( 'jquery' ), URME_SS_VERSION, true );
		wp_localize_script(
			'urme-ss-admin',
			'urmeSS',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'urme_ss_row' ),
			)
		);
	}

	/**
	 * AJAX: one Supplier catalog row action. The work is done by the same server functions as
	 * the non-JavaScript buttons; the response carries the row re-rendered from current data.
	 */
	public static function ajax_row() {
		$res = self::row_ajax( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in row_ajax().
		wp_send_json(
			array(
				'success' => $res['success'],
				'data'    => $res['data'],
			),
			$res['status']
		);
	}

	/**
	 * @param array $post Unslashed request: nonce, row_action ("sale|<item_key>", "start_supplier|<item_key>",
	 *                    "dropship|<link_id>", "lager|<link_id>", "sync|<link_id>"), sale_price.
	 * @return array{success: bool, status: int, data: array}
	 */
	private static function row_ajax( array $post ) {
		$fail = static function ( $status, $message ) {
			return array(
				'success' => false,
				'status'  => $status,
				'data'    => array(
					'type'    => 'error',
					'message' => $message,
				),
			);
		};
		if ( ! current_user_can( self::CAP ) ) {
			return $fail( 403, 'You are not allowed to do this.' );
		}
		if ( ! wp_verify_nonce( (string) ( $post['nonce'] ?? '' ), 'urme_ss_row' ) ) {
			return $fail( 403, 'Security check failed. Reload the page and try again.' );
		}
		$action = sanitize_text_field( (string) ( $post['row_action'] ?? '' ) );
		$parts  = explode( '|', $action, 2 );
		$op     = $parts[0];
		$arg    = $parts[1] ?? '';
		if ( 'sale' === $op ) {
			$key    = $arg;
			$raw    = ( isset( $post['sale_price'] ) && is_scalar( $post['sale_price'] ) ) ? sanitize_text_field( (string) $post['sale_price'] ) : null;
			$result = self::save_sale_price( $key, $raw );
		} elseif ( in_array( $op, array( 'start_supplier', 'start_local', 'dropship', 'lager', 'resume', 'sync' ), true ) ) {
			$key    = in_array( $op, array( 'dropship', 'lager', 'resume', 'sync' ), true ) ? (string) ( URME_SS_DB::get_link_by_id( absint( $arg ) )['item_key'] ?? '' ) : $arg;
			$result = self::run_row_action( $action );
		} else {
			return $fail( 400, 'Unknown action.' );
		}
		return array(
			'success' => in_array( $result[1], array( 'success', 'info' ), true ),
			'status'  => 200,
			'data'    => array(
				'type'     => $result[1],
				'message'  => $result[0],
				'key'      => $key,
				'row_html' => '' !== $key ? self::catalog_row_html( $key ) : '',
			),
		);
	}

	/**
	 * One catalog row as HTML, from the current data (no feed request).
	 */
	private static function catalog_row_html( $item_key ) {
		$rows = URME_SS_DB::search_catalog(
			array(
				'item_key'     => $item_key,
				'show_missing' => 1,
				'per_page'     => 10,
			)
		)['rows'];
		if ( ! $rows ) {
			return '';
		}
		$row   = $rows[0];
		$pid   = self::urme_product_id( $row );
		$stock = URME_SS_Store::stock_info( array( $pid ) );
		URME_SS_Product_Source::flush();
		URME_SS_Product_Source::prime( array( $pid ) );
		ob_start();
		self::render_catalog_row( $row, $stock, URME_SS_Price_Hint::context() );
		return trim( (string) ob_get_clean() );
	}

	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/* ---------------------------------------------------------------------
	 * Actions (POST to admin-post.php)
	 * ------------------------------------------------------------------- */

	public static function handle() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'urme_ss' );

		$do       = sanitize_key( $_POST['do'] ?? '' );
		$redirect = wp_validate_redirect( wp_unslash( $_POST['_back'] ?? '' ), self::url() );
		// phpcs:disable WordPress.Security.NonceVerification -- verified above.

		// A per-row button in the Supplier catalog (inside the bulk-select form) names its own action.
		$row_action = sanitize_text_field( wp_unslash( $_POST['row_action'] ?? '' ) );
		if ( 0 === strpos( $row_action, 'sale|' ) ) {
			// Manual sale price of one row: only that row's input is used.
			$key        = substr( $row_action, 5 );
			$raw        = wp_unslash( $_POST['sale_price'][ $key ] ?? null );
			$row_action = '';
			$do         = '';
			self::notice_result( self::save_sale_price( $key, null === $raw ? null : sanitize_text_field( (string) $raw ) ) );
		}
		if ( '' !== $row_action ) {
			self::notice_result( self::run_row_action( $row_action ) );
			$do = '';
		}

		switch ( $do ) {
			case 'select':
				$keys = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['item_keys'] ?? array() ) );
				self::notice_result( self::select_items( $keys ) );
				break;

			case 'review_done':
				$ok = URME_SS_Price_Review::mark_reviewed( absint( $_POST['review_id'] ?? 0 ) );
				self::notice( $ok ? 'Marked as reviewed. The selling price was not changed by the plugin.' : 'Already reviewed.' );
				break;

			case 'toggle_brand':
				$brand = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
				if ( '' !== $brand ) {
					self::notice_result( self::toggle_brand( $brand, ! empty( $_POST['enable'] ) ) );
				}
				break;

			case 'rematch':
				$result = URME_SS_Sync::run(
					array(
						'refresh_feed'    => false,
						'refresh_matches' => true,
						'sync_products'   => false,
					)
				);
				self::notice( $result['ran'] ? 'URME match status re-checked for all supplier watches.' : $result['message'], $result['ran'] ? 'success' : 'warning' );
				break;

			case 'sync_now':
			case 'sync_products':
			case 'accept_feed':
				$result = URME_SS_Sync::run(
					array(
						'refresh_feed' => 'sync_products' !== $do,
						'force_feed'   => 'accept_feed' === $do,
					)
				);
				if ( ! $result['ran'] ) {
					self::notice( $result['message'], 'warning' );
				} elseif ( false === $result['feed_ok'] ) {
					self::notice( 'The supplier feed could not be refreshed; no products were changed from it. See Status for details.', 'error' );
				} else {
					self::notice( 'Sync finished. See Status for the numbers.' );
				}
				break;

			case 'refresh_rate':
				$ok = URME_SS_Rates::maybe_refresh( true );
				self::notice( $ok ? 'Exchange rate updated.' : 'Could not fetch a new exchange rate; the previous rate is kept.', $ok ? 'success' : 'warning' );
				break;

			case 'inspect':
				URME_SS_Store::inspect( true );
				self::notice( 'Store setup re-checked.' );
				break;

			case 'save_settings':
				$old  = URME_SS_Settings::all();
				$new  = URME_SS_Settings::save( wp_unslash( (array) ( $_POST['settings'] ?? array() ) ) );
				$msg  = ( $old['categories'] !== $new['categories'] || $old['feed_url'] !== $new['feed_url'] ) ? 'Settings saved. Click "Sync now" to reload the catalog with the new feed settings.' : 'Settings saved.';
				$held = self::held_message();
				self::notice( $msg . $held, '' === $held ? 'success' : 'warning' );
				break;
		}
		// phpcs:enable

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Brand button in the catalog. Turning a brand on checks its Supplier-now watches first (see
	 * URME_SS_Settings::set_brand()); the ones with local URME stock stay paused and are listed.
	 *
	 * @return array{0: string, 1: string} Message and notice type.
	 */
	private static function toggle_brand( $brand, $enable ) {
		URME_SS_Settings::set_brand( $brand, $enable );
		if ( ! $enable ) {
			return array( sprintf( 'Sync disabled for %s. Its selections and links are kept; its products are no longer updated.', $brand ), 'success' );
		}
		$held = self::held_message();
		return array( sprintf( 'Sync enabled for %s. Selected %s watches are updated on the next sync.', $brand, $brand ) . $held, '' === $held ? 'success' : 'warning' );
	}

	/**
	 * Notice text for the watches the last brand enable kept paused ('' when none).
	 */
	private static function held_message() {
		$held = URME_SS_Settings::held_on_enable();
		return $held ? sprintf( ' Moved to URME Lager (removed from supplier sync) because URME stock exists (%d): %s. Their stock and cost were not changed.', count( $held ), implode( ', ', $held ) ) : '';
	}

	/**
	 * "Select checked for sync": each checked watch is handled on its own. Only a watch whose
	 * unique URME product has stock 0 is selected (Dropshipping); a product with URME stock stays
	 * URME Lager and is not selected. Ambiguous, disabled-brand, not-in-URME or invalid watches
	 * are rejected without affecting the others.
	 *
	 * @return array{0: string, 1: string} Message and notice type.
	 */
	private static function select_items( array $keys ) {
		$supplier = array();
		$local    = array();
		$rejected = array();
		$skipped  = array();
		URME_SS_Matcher::reset();
		foreach ( array_unique( array_filter( array_map( 'strval', $keys ) ) ) as $key ) {
			$item = URME_SS_DB::get_item( $key );
			if ( ! $item ) {
				$rejected[] = $key . ' – not in the supplier catalog';
				continue;
			}
			$no = $item['product_no'];
			if ( URME_SS_DB::get_link( $key ) ) {
				$skipped[] = $no;
				continue;
			}
			if ( ! (int) $item['in_feed'] ) {
				$rejected[] = $no . ' – no longer in the supplier feed';
				continue;
			}
			if ( ! URME_SS_Settings::brand_enabled( $item['manufacturer'] ) ) {
				$rejected[] = sprintf( '%s – sync is disabled for %s', $no, $item['manufacturer'] );
				continue;
			}
			$found = URME_SS_Matcher::match( $item )['candidates'];
			if ( count( $found ) > 1 ) {
				$rejected[] = sprintf( '%s – several URME products match (Needs review: IDs %s)', $no, implode( ', ', array_keys( $found ) ) );
				continue;
			}
			if ( ! $found ) {
				$rejected[] = $no . ' – no URME product with this SKU or EAN (create it in WooCommerce first)';
				continue;
			}
			$pid = (int) key( $found );
			if ( URME_SS_DB::item_key_for_product( $pid ) ) {
				$rejected[] = sprintf( '%s – product #%d is already linked to another supplier watch', $no, $pid );
				continue;
			}
			$product = wc_get_product( $pid );
			if ( ! $product || 'trash' === $product->get_status() ) {
				$rejected[] = sprintf( '%s – product #%d no longer exists', $no, $pid );
				continue;
			}
			$units = URME_SS_Inventory::local_units_before_supplier( null, $product );
			if ( $units > 0 ) {
				$local[] = sprintf( '%s (%d)', $no, $units ); // URME stock: stays URME Lager, not selected.
				continue;
			}
			if ( ! URME_SS_DB::insert_link( $key, $pid, implode( '+', current( $found ) ) ) ) {
				$rejected[] = $no . ' – the selection could not be saved';
				continue;
			}
			$supplier[] = $no;
		}

		if ( ! $supplier && ! $local && ! $rejected ) {
			return array( 'Nothing new was selected.', 'info' );
		}
		$parts = array();
		if ( $supplier ) {
			$parts[] = sprintf( 'Dropshipping – URME stock 0, updated on the next sync or with "Sync now" (%d): %s.', count( $supplier ), implode( ', ', $supplier ) );
		}
		if ( $local ) {
			$parts[] = sprintf( 'Not selected: URME stock exists, they stay URME Lager (%d): %s.', count( $local ), implode( ', ', $local ) );
		}
		if ( $rejected ) {
			$parts[] = sprintf( 'Not selected, nothing changed (%d): %s.', count( $rejected ), implode( '; ', $rejected ) );
		}
		if ( $skipped ) {
			$parts[] = sprintf( 'Already selected: %s.', implode( ', ', $skipped ) );
		}
		$started = count( $supplier );
		return array( implode( ' ', $parts ), ( $rejected || $local ) ? ( $started ? 'warning' : 'error' ) : 'success' );
	}

	/**
	 * Dropshipping ('supplier', only at URME stock 0) or back to URME Lager ('local': the watch
	 * leaves supplier sync and its stock becomes 0 until the real stock is entered).
	 *
	 * @return array{0: string, 1: string} Message and notice type.
	 */
	private static function set_mode( $link, $mode ) {
		// phpcs:disable WordPress.Security.NonceVerification -- verified in handle().
		if ( ! $link ) {
			return array( 'Selection not found.', 'error' );
		}
		switch ( $mode ) {
			case 'local':
				$ok = URME_SS_Inventory::return_to_lager( (int) $link['id'] );
				return true === $ok
					? array( 'URME Lager: removed from supplier sync; stock set to 0 (out of stock). Enter the real stock in WooCommerce when you have it.', 'success' )
					: array( $ok, 'error' );

			case 'supplier':
				$product = (int) $link['product_id'] ? wc_get_product( (int) $link['product_id'] ) : null;
				if ( ! $product ) {
					return array( 'Link a WooCommerce product first. Nothing was changed.', 'error' );
				}
				$item = URME_SS_DB::get_item( $link['item_key'] );
				if ( ! $item || ! URME_SS_Settings::brand_enabled( $item['manufacturer'] ) ) {
					return array( sprintf( 'Not changed: enable brand sync for %s first.', $item ? $item['manufacturer'] : 'this brand' ), 'error' );
				}
				// URME Lager stock always has priority: Dropshipping only at stock 0. No override.
				$units = URME_SS_Inventory::local_units_before_supplier( null, $product );
				if ( $units > 0 ) {
					return array( 'Not changed: ' . URME_SS_Inventory::local_priority_message( $units ), 'error' );
				}
				URME_SS_Inventory::enable_supplier( (int) $link['id'] );
				return array( 'Dropshipping: supplier stock and cost are synced on the next sync (or click "Sync now").', 'success' );

			case 'paused':
				return array( self::NO_PAUSE, 'error' );
		}
		return array( 'Unknown mode.', 'error' );
		// phpcs:enable
	}

	/**
	 * Supplier catalog row actions: "start_supplier|<item_key>", "dropship|<link_id>",
	 * "lager|<link_id>", "sync|<link_id>" (and refusals for the old "start_local" / "resume").
	 *
	 * @return array{0: string, 1: string} Message and notice type.
	 */
	private static function run_row_action( $value ) {
		$parts = explode( '|', (string) $value, 2 );
		$arg   = $parts[1] ?? '';
		switch ( $parts[0] ) {
			case 'start_supplier':
				return self::start_from_catalog( $arg );

			case 'start_local':
				return array( 'A watch with URME stock simply stays URME Lager; there is nothing to start.', 'info' );

			case 'dropship':
				return self::set_mode( URME_SS_DB::get_link_by_id( absint( $arg ) ), 'supplier' );

			case 'lager':
				return self::set_mode( URME_SS_DB::get_link_by_id( absint( $arg ) ), 'local' );

			case 'resume':
				return array( self::NO_PAUSE, 'error' );

			case 'sync':
				$link = URME_SS_DB::get_link_by_id( absint( $arg ) );
				if ( ! $link ) {
					return array( 'Selection not found.', 'error' );
				}
				if ( ! (int) $link['sync_enabled'] ) {
					return array( 'URME Lager: supplier stock and cost are not synced for this watch.', 'error' );
				}
				$result = URME_SS_Sync::run(
					array(
						'refresh_feed' => false,
						'link_id'      => (int) $link['id'],
					)
				);
				if ( ! $result['ran'] ) {
					return array( $result['message'], 'warning' );
				}
				if ( ! empty( $result['sync']['skipped'] ) ) {
					return array( 'Not synced: ' . $result['sync']['skipped'], 'warning' );
				}
				$link = URME_SS_DB::get_link_by_id( (int) $link['id'] );
				if ( 'error' === $link['last_status'] ) {
					return array( 'Not synced: ' . $link['last_message'], 'error' );
				}
				if ( 'local' === $link['last_status'] ) {
					return array( $link['last_message'], 'success' ); // URME Lager: waiting for stock 0.
				}
				return array( sprintf( 'Synced: stock %s, cost %s.', null === $link['last_stock'] ? '—' : (int) $link['last_stock'], null === $link['last_cost_sek'] ? 'not synced' : wc_format_decimal( $link['last_cost_sek'], 2 ) . ' SEK' ), 'success' );
		}
		return array( 'Unknown action.', 'error' );
	}

	/**
	 * Start sync for one supplier watch from the catalog, only for a unique, confirmed URME match.
	 * Every condition shown on the page is checked again here, against the current data.
	 *
	 * Dropshipping: URME stock is 0, supplier stock and cost are synced at once.
	 *
	 * @param string $item_key Supplier item.
	 * @return array{0: string, 1: string} Message and notice type.
	 */
	private static function start_from_catalog( $item_key ) {
		$item = URME_SS_DB::get_item( $item_key );
		if ( ! $item ) {
			return array( 'Supplier watch not found.', 'error' );
		}
		if ( URME_SS_DB::get_link( $item_key ) ) {
			return array( sprintf( '%s is already selected.', $item['product_no'] ), 'warning' );
		}
		if ( ! (int) $item['in_feed'] ) {
			return array( sprintf( '%s is no longer in the supplier feed; nothing was changed.', $item['product_no'] ), 'error' );
		}
		if ( ! URME_SS_Settings::brand_enabled( $item['manufacturer'] ) ) {
			return array( sprintf( 'Sync is disabled for %s; nothing was changed.', $item['manufacturer'] ), 'error' );
		}
		URME_SS_Matcher::reset();
		$match = URME_SS_Matcher::match( $item );
		if ( URME_SS_Matcher::EXISTS !== $match['status'] || $match['product_id'] !== (int) $item['match_product_id'] ) {
			return array( sprintf( '%s has no unique, confirmed URME product (the match may have changed); nothing was changed. Click "Re-check now" (the SKU or EAN in WooCommerce must match).', $item['product_no'] ), 'error' );
		}
		$pid = $match['product_id'];
		if ( URME_SS_DB::item_key_for_product( $pid ) ) {
			return array( sprintf( 'Product #%d is already linked to another supplier watch; nothing was changed.', $pid ), 'error' );
		}
		$product = wc_get_product( $pid );
		$info    = URME_SS_Store::stock_info( array( $pid ) )[ $pid ] ?? null;
		if ( ! $product || ! $info ) {
			return array( sprintf( 'Product #%d no longer exists; nothing was changed.', $pid ), 'error' );
		}
		if ( ! $info['managed'] ) {
			return array( sprintf( '"%s" does not manage stock in WooCommerce; use "Select checked for sync" instead.', $product->get_name() ), 'error' );
		}
		if ( $info['by_parent'] ) {
			return array( sprintf( 'The stock of "%s" is managed by its parent product; use "Select checked for sync" instead. Nothing was changed.', $product->get_name() ), 'error' );
		}

		$units = URME_SS_Inventory::local_units_before_supplier( null, $product );
		if ( $units > 0 ) {
			// Local stock always has priority: never overwrite it with supplier stock.
			return array( URME_SS_Inventory::local_priority_message( $units ), 'error' );
		}
		$link_id = URME_SS_DB::insert_link( $item_key, $pid, $match['method'] );
		if ( ! $link_id ) {
			return array( 'Could not save the selection.', 'error' );
		}
		URME_SS_Log::info( sprintf( 'Dropshipping started from the catalog for product #%d (%s).', $pid, $item['product_no'] ) );
		$result = URME_SS_Sync::run(
			array(
				'refresh_feed' => false,
				'link_id'      => $link_id,
			)
		);
		$link = URME_SS_DB::get_link_by_id( $link_id );
		if ( ! $result['ran'] ) {
			return array( sprintf( '%s is linked to "%s" (Dropshipping), but %s It is synced on the next run.', $item['product_no'], $product->get_name(), lcfirst( $result['message'] ) ), 'warning' );
		}
		if ( ! empty( $result['sync']['skipped'] ) || 'ok' !== ( $link['last_status'] ?? '' ) ) {
			return array( sprintf( '%s is linked to "%s" (Dropshipping), but it was not synced yet: %s', $item['product_no'], $product->get_name(), $result['sync']['skipped'] ? $result['sync']['skipped'] : ( $link['last_message'] ?? '' ) ), 'warning' );
		}
		return array( sprintf( 'Dropshipping started for "%s": stock %s, cost %s.', $product->get_name(), null === $link['last_stock'] ? '—' : (int) $link['last_stock'], null === $link['last_cost_sek'] ? 'not synced' : wc_format_decimal( $link['last_cost_sek'], 2 ) . ' SEK' ), 'success' );
	}

	/**
	 * The WooCommerce product a supplier item is confirmed to be: its linked product, or its
	 * unique URME match (checked again against the current store). 0 when there is none.
	 */
	private static function confirmed_product_id( $item_key ) {
		$link = URME_SS_DB::get_link( $item_key );
		if ( $link && (int) $link['product_id'] ) {
			return (int) $link['product_id'];
		}
		$item = URME_SS_DB::get_item( $item_key );
		if ( ! $item || URME_SS_Matcher::EXISTS !== $item['match_status'] || ! (int) $item['match_product_id'] ) {
			return 0;
		}
		URME_SS_Matcher::reset();
		$match = URME_SS_Matcher::match( $item );
		return ( URME_SS_Matcher::EXISTS === $match['status'] && $match['product_id'] === (int) $item['match_product_id'] ) ? $match['product_id'] : 0;
	}

	/**
	 * Manual sale price edit (never done by supplier sync). Only the sale price of the exact
	 * product or variation is changed, through the WooCommerce product API. Empty = remove the sale.
	 *
	 * @param string      $item_key Supplier item whose confirmed product is edited.
	 * @param string|null $raw      Posted value.
	 * @return array{0: string, 1: string} Message and notice type.
	 */
	private static function save_sale_price( $item_key, $raw ) {
		if ( null === $raw ) {
			return array( 'No sale price was sent; nothing was changed.', 'error' );
		}
		$pid = self::confirmed_product_id( (string) $item_key );
		if ( ! $pid ) {
			return array( 'This supplier watch has no linked or uniquely matched URME product; no price was changed.', 'error' );
		}
		$product = wc_get_product( $pid );
		if ( ! $product || 'trash' === $product->get_status() ) {
			return array( sprintf( 'Product #%d no longer exists; no price was changed.', $pid ), 'error' );
		}
		if ( $product->is_type( array( 'variable', 'grouped' ) ) ) {
			return array( sprintf( '"%s" has no price of its own (it is a %s product); edit a single variation instead.', $product->get_name(), $product->get_type() ), 'error' );
		}
		$name    = $product->get_name();
		$regular = (string) $product->get_regular_price();
		$old     = (string) $product->get_sale_price();
		$value   = str_replace( array( ' ', "\xc2\xa0", ',' ), array( '', '', '.' ), trim( $raw ) );

		if ( '' === $value ) {
			if ( '' === $old ) {
				return array( sprintf( '"%s" has no sale price; nothing was changed.', $name ), 'info' );
			}
			$product->set_sale_price( '' );
			$product->save();
			URME_SS_Log::info( sprintf( 'Sale price of product #%d removed manually (was %s SEK); regular price %s SEK.', $pid, $old, '' === $regular ? '—' : $regular ) );
			return array( sprintf( 'Sale price removed: "%s" now sells at its regular price%s.', $name, '' === $regular ? '' : ' of ' . wc_format_decimal( $regular, 2 ) . ' kr' ), 'success' );
		}
		if ( ! preg_match( '/^\d+(\.\d{1,2})?$/', $value ) ) {
			return array( sprintf( 'Sale price "%s" is not valid: enter a number in SEK, e.g. 4290 or 4290.50. Nothing was changed.', $raw ), 'error' );
		}
		if ( '' !== $regular && (float) $value > (float) $regular ) {
			return array( sprintf( 'Sale price %s kr is above the regular price %s kr of "%s"; nothing was changed.', $value, wc_format_decimal( $regular, 2 ), $name ), 'error' );
		}
		if ( '' !== $old && abs( (float) $old - (float) $value ) < 0.001 ) {
			return array( sprintf( 'Sale price of "%s" is already %s kr; nothing was changed.', $name, wc_format_decimal( $old, 2 ) ), 'info' );
		}
		$product->set_sale_price( wc_format_decimal( $value ) );
		$product->save();
		URME_SS_Log::info( sprintf( 'Sale price of product #%d changed manually: %s → %s SEK (regular %s SEK).', $pid, '' === $old ? '—' : $old, wc_format_decimal( $value ), '' === $regular ? '—' : $regular ) );
		return array( sprintf( 'Sale price of "%s" saved: %s kr%s.', $name, wc_format_decimal( $value, 2 ), '' === $regular ? '' : ' (regular ' . wc_format_decimal( $regular, 2 ) . ' kr)' ), 'success' );
	}

	private static function notice_result( array $result ) {
		self::notice( $result[0], $result[1] );
	}

	private static function notice( $message, $type = 'success' ) {
		set_transient(
			'urme_ss_notice_' . get_current_user_id(),
			array(
				'message' => $message,
				'type'    => $type,
			),
			60
		);
	}

	private static function print_notice() {
		$key    = 'urme_ss_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice ) {
			delete_transient( $key );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice['type'] ), esc_html( $notice['message'] ) );
		}
	}

	/**
	 * Hidden fields + submit button for a one-click action.
	 */
	private static function action_button( $do, $label, array $fields = array(), $class = 'button', $confirm = '' ) {
		$out = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="urme-inline"' . ( $confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>';
		$out .= self::hidden_fields( $do );
		foreach ( $fields as $name => $value ) {
			$out .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		$out .= '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
		return $out;
	}

	private static function hidden_fields( $do ) {
		$back = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : self::url();
		return wp_nonce_field( 'urme_ss', '_wpnonce', false, false )
			. '<input type="hidden" name="action" value="urme_ss">'
			. '<input type="hidden" name="do" value="' . esc_attr( $do ) . '">'
			. '<input type="hidden" name="_back" value="' . esc_attr( $back ) . '">';
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------- */

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = sanitize_key( $_GET['tab'] ?? 'catalog' );
		$tabs = array(
			'catalog'  => 'Supplier catalog',
			'reviews'  => 'Price Review',
			'status'   => 'Status & log',
			'settings' => 'Settings',
		);
		$pending = URME_SS_Price_Review::pending_count();
		if ( $pending ) {
			$tabs['reviews'] .= sprintf( ' (%d)', $pending );
		} elseif ( ! URME_SS_Price_Review::has_any() ) {
			unset( $tabs['reviews'] ); // Reviews come only from the old automatic switch (before 1.5.2).
		}
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'catalog';
		}

		echo '<div class="wrap urme-ss"><h1>Supplier Sync</h1>';
		self::print_notice();
		self::$in_render = true; // Values read once for the whole page (see page_new_count()).
		self::$new_count = null;
		self::render_summary();

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $id => $label ) {
			printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( self::url( array( 'tab' => $id ) ) ), $id === $tab ? ' nav-tab-active' : '', esc_html( $label ) );
		}
		echo '</nav><div class="urme-tab">';
		call_user_func( array( __CLASS__, 'render_' . $tab ) );
		echo '</div></div>';
		self::$in_render = false;
		self::$new_count = null;
	}

	private static function render_summary() {
		$status  = URME_SS_Sync::status();
		$feed    = $status['feed'];
		$sync    = $status['sync'];
		$rate    = URME_SS_Rates::current();
		$rstate  = URME_SS_Rates::state();
		$links   = URME_SS_DB::link_counts();
		$counts  = URME_SS_DB::catalog_counts();
		$running = URME_SS_Sync::is_running();

		echo '<div class="urme-cards">';

		self::card(
			'EUR / SEK',
			$rate ? number_format_i18n( $rate['rate'], 4 ) : '—',
			$rate
				? ( $rate['overridden'] ? 'Manual override' : esc_html( $rate['source'] ) . ( $rate['date'] ? ', rate date ' . esc_html( $rate['date'] ) : '' ) . '<br>Fetched ' . self::ago( $rstate['fetched_at'] ?? 0 ) )
				: 'No rate yet'
		);

		$feed_sub = 'Last attempt ' . self::ago( $feed['last_attempt'] ?? 0 );
		if ( ! empty( $feed['last_error'] ) ) {
			$feed_sub .= '<br><span class="urme-bad">Last attempt failed</span>';
		}
		self::card( 'Last successful feed update', self::ago( $feed['last_success'] ?? 0 ), $feed_sub, ! empty( $feed['last_error'] ) );

		$new = self::page_new_count();
		self::card(
			'Supplier watches',
			number_format_i18n( $counts['in_feed'] ),
			( $counts['missing'] ? number_format_i18n( $counts['missing'] ) . ' no longer in feed' : 'in local catalog' )
				. ( $new ? sprintf( '<br><a href="%s">%d new in the last %d days</a>', esc_url( self::url( array( 'new_only' => 1 ) ) ), $new, URME_SS_DB::NEW_DAYS ) : '' )
		);

		self::card(
			'Dropshipping',
			number_format_i18n( $links['dropship'] ?? 0 ),
			sprintf( '<a href="%s">Show in the catalog</a>', esc_url( self::url( array( 'selected' => 'yes' ) ) ) )
				. ( ! empty( $links['missing'] ) ? sprintf( '<br>%d no longer in the feed', $links['missing'] ) : '' )
				. ( ! empty( $links['errors'] ) ? sprintf( '<br>%d with a sync error', $links['errors'] ) : '' ),
			! empty( $links['errors'] ) || ! empty( $links['missing'] )
		);

		self::card(
			'Last product sync',
			self::ago( $sync['last_run'] ?? 0 ),
			! empty( $sync['skipped'] )
				? '<span class="urme-bad">Skipped (see Status)</span>'
				: sprintf( '%d stock · %d cost updates · %d errors', $sync['stock_updated'] ?? 0, $sync['cost_updated'] ?? 0, $sync['errors'] ?? 0 ),
			! empty( $sync['skipped'] ) || ! empty( $sync['errors'] )
		);

		echo '<div class="urme-card urme-card-actions">';
		if ( $running ) {
			echo '<p><strong>A sync is running…</strong></p>';
		}
		echo self::action_button( 'sync_now', 'Sync now', array(), 'button button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="description">Downloads the feed, then updates selected products.</p>';
		echo self::action_button( 'sync_products', 'Sync products only' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="description">Uses the cached catalog (fast).</p>';
		echo '</div></div>';

		if ( ! URME_SS_Settings::enabled_brand_keys() ) {
			printf( '<div class="notice notice-warning inline"><p><strong>No brands are enabled for sync yet.</strong> Watches are only synced when their brand is enabled. <a href="%s">Choose brands in Settings</a>.</p></div>', esc_url( self::url( array( 'tab' => 'settings' ) ) ) );
		}
		$target = URME_SS_Store::cost_target();
		if ( ! $target['type'] ) {
			printf( '<div class="notice notice-warning inline"><p><strong>Cost price is not being synced.</strong> %s</p></div>', esc_html( $target['reason'] ) );
		}
		if ( ! $rate ) {
			echo '<div class="notice notice-warning inline"><p>No EUR/SEK rate is available yet, so cost price cannot be converted. It is fetched automatically; you can also set a manual override in Settings.</p></div>';
		}
	}

	private static function card( $title, $value, $sub = '', $warn = false ) {
		printf(
			'<div class="urme-card%s"><div class="urme-card-title">%s</div><div class="urme-card-value">%s</div><div class="urme-card-sub">%s</div></div>',
			$warn ? ' urme-warn' : '',
			esc_html( $title ),
			esc_html( $value ),
			wp_kses_post( $sub )
		);
	}

	private static function ago( $ts ) {
		$ts = (int) $ts;
		if ( ! $ts ) {
			return 'never';
		}
		return sprintf( '%s ago', human_time_diff( $ts ) );
	}

	private static function datetime( $ts ) {
		return $ts ? wp_date( 'Y-m-d H:i', (int) $ts ) : '—';
	}

	private static function mysql_datetime( $gmt ) {
		return $gmt ? wp_date( 'Y-m-d H:i', strtotime( $gmt . ' UTC' ) ) : '—';
	}

	private static function eur( $v ) {
		return null === $v || '' === $v ? '—' : '€' . number_format_i18n( (float) $v, 2 );
	}

	private static function sek( $v ) {
		return null === $v || '' === $v ? '—' : number_format_i18n( (float) $v, 2 ) . ' kr';
	}

	private static function img( $url ) {
		if ( ! $url ) {
			return '<span class="urme-noimg"></span>';
		}
		return sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer"><img src="%1$s" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" width="56" height="56"></a>', esc_url( $url ) );
	}

	private static function pagination( $total, $per_page, $page, array $args ) {
		$pages = (int) ceil( $total / $per_page );
		if ( $pages <= 1 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg( array_merge( $args, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ),
					'format'    => '',
					'current'   => $page,
					'total'     => $pages,
					'prev_text' => '‹',
					'next_text' => '›',
				)
			)
		);
		echo '</div></div>';
	}

	/* --- Catalog tab ---------------------------------------------------- */

	private static function render_catalog() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$f = array(
			'brand'        => sanitize_text_field( wp_unslash( $_GET['brand'] ?? '' ) ),
			'productno'    => sanitize_text_field( wp_unslash( $_GET['productno'] ?? '' ) ),
			'ean'          => preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_GET['ean'] ?? '' ) ) ),
			'q'            => sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ),
			'in_stock'     => empty( $_GET['in_stock'] ) ? '' : '1',
			'selected'     => in_array( $_GET['selected'] ?? '', array( 'yes', 'no' ), true ) ? sanitize_key( $_GET['selected'] ) : '',
			'match'        => in_array( $_GET['match'] ?? '', array( 'exists', 'none', 'review', 'manual' ), true ) ? sanitize_key( $_GET['match'] ) : '',
			'brand_sync'   => in_array( $_GET['brand_sync'] ?? '', array( 'on', 'off', 'any' ), true ) ? sanitize_key( $_GET['brand_sync'] ) : '',
			'show_missing' => empty( $_GET['show_missing'] ) ? '' : '1',
			'new_only'     => empty( $_GET['new_only'] ) ? '' : '1',
			'category'     => sanitize_text_field( wp_unslash( $_GET['category'] ?? '' ) ),
			'urme_stock'   => in_array( $_GET['urme_stock'] ?? '', array( 'in', 'out', 'unmanaged' ), true ) ? sanitize_key( $_GET['urme_stock'] ) : '',
			'ready'        => empty( $_GET['ready'] ) ? '' : '1',
			'per_page'     => in_array( (int) ( $_GET['per_page'] ?? 0 ), array( 20, 100 ), true ) ? (string) absint( $_GET['per_page'] ) : '',
		);
		$page = max( 1, absint( $_GET['paged'] ?? 1 ) );
		// phpcs:enable
		// Default view (opened or Reset, no filter at all): only the brands enabled for sync. Any
		// search, brand or dashboard link looks in all brands, so a specific watch is always found.
		$narrowed     = (bool) array_filter( array_diff_key( $f, array_flip( array( 'brand_sync', 'per_page' ) ) ) );
		$default_view = '' === $f['brand_sync'] && ! $narrowed && URME_SS_Settings::enabled_brand_keys();
		$per_page     = $f['per_page'] ? (int) $f['per_page'] : 50;

		$result  = URME_SS_DB::search_catalog( array_merge( $f, array( 'brand_sync' => $default_view ? 'on' : $f['brand_sync'], 'page' => $page, 'per_page' => $per_page ) ) );
		// Brand filter: only brands enabled for sync, plus the one filtered on now (e.g. from an old link).
		$brands  = array_filter(
			URME_SS_DB::brands(),
			static function ( $b ) use ( $f ) {
				return $b['manufacturer'] === $f['brand'] || URME_SS_Settings::brand_enabled( $b['manufacturer'] );
			}
		);
		$rate    = URME_SS_Rates::current();
		$cats    = URME_SS_Settings::get( 'categories' );
		$mcounts = URME_SS_DB::match_counts();
		$hint    = URME_SS_Price_Hint::context(); // Once per page; each row is pure arithmetic.

		$product_ids = array();
		foreach ( $result['rows'] as $row ) {
			$product_ids[] = (int) $row['product_id'];
			$product_ids[] = (int) $row['match_product_id'];
			foreach ( explode( ',', (string) $row['match_candidates'] ) as $cid ) {
				$product_ids[] = (int) $cid;
			}
		}
		_prime_post_caches( array_filter( array_unique( $product_ids ) ), false, false );

		// URME stock of the exact linked product, or of the unique confirmed match: read in bulk, never per row.
		$stock_ids = array();
		foreach ( $result['rows'] as $row ) {
			$stock_ids[] = self::urme_product_id( $row );
		}
		$stock = URME_SS_Store::stock_info( $stock_ids );
		URME_SS_Product_Source::flush(); // Always current for this page.
		URME_SS_Product_Source::prime( $stock_ids ); // Fulfillment badges and "linked elsewhere", one query.

		$match_filters = array(
			''       => 'Any',
			'exists' => 'Exists in URME',
			'none'   => 'Not in URME',
			'review' => 'Needs review',
			'manual' => 'Manually linked',
		);
		?>
		<form method="get" class="urme-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
			<input type="hidden" name="tab" value="catalog">
			<label>Brand
				<select name="brand">
					<option value="">All</option>
					<?php foreach ( $brands as $b ) : ?>
						<option value="<?php echo esc_attr( $b['manufacturer'] ); ?>" <?php selected( $f['brand'], $b['manufacturer'] ); ?>><?php echo esc_html( $b['manufacturer'] . ' (' . (int) $b['n'] . ')' . ( URME_SS_Settings::brand_enabled( $b['manufacturer'] ) ? '' : ' – sync off' ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Brand sync
				<select name="brand_sync">
					<option value="">Default (enabled brands)</option>
					<option value="on" <?php selected( $f['brand_sync'], 'on' ); ?>>Enabled brands only</option>
					<option value="off" <?php selected( $f['brand_sync'], 'off' ); ?>>Disabled brands</option>
					<option value="any" <?php selected( $f['brand_sync'], 'any' ); ?>>All brands</option>
				</select>
			</label>
			<label>In URME
				<select name="match">
					<?php foreach ( $match_filters as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $f['match'], $value ); ?>><?php echo esc_html( $label . ( '' !== $value ? ' (' . (int) ( $mcounts[ $value ] ?? 0 ) . ')' : '' ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Model / PRODUCTNO <input type="search" name="productno" value="<?php echo esc_attr( $f['productno'] ); ?>" placeholder="e.g. SRPE51K1"></label>
			<label>EAN / ITEM_ID <input type="search" name="ean" value="<?php echo esc_attr( $f['ean'] ); ?>" placeholder="e.g. 4954628..."></label>
			<label>Text <input type="search" name="q" value="<?php echo esc_attr( $f['q'] ); ?>" placeholder="Name, brand, model…"></label>
			<?php if ( count( $cats ) > 1 ) : ?>
				<label>Category
					<select name="category">
						<option value="">All</option>
						<?php foreach ( $cats as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $f['category'], $cat ); ?>><?php echo esc_html( $cat ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
			<label>Selection
				<select name="selected">
					<option value="">All watches</option>
					<option value="yes" <?php selected( $f['selected'], 'yes' ); ?>>Selected only</option>
					<option value="no" <?php selected( $f['selected'], 'no' ); ?>>Not selected</option>
				</select>
			</label>
			<label class="urme-check"><input type="checkbox" name="new_only" value="1" <?php checked( $f['new_only'], '1' ); ?>> <?php echo esc_html( sprintf( 'New products (%d)', self::page_new_count() ) ); ?></label>
			<label>URME stock
				<select name="urme_stock">
					<option value="">Any</option>
					<option value="in" <?php selected( $f['urme_stock'], 'in' ); ?>>In stock</option>
					<option value="out" <?php selected( $f['urme_stock'], 'out' ); ?>>Out of stock</option>
					<option value="unmanaged" <?php selected( $f['urme_stock'], 'unmanaged' ); ?>>Not managed</option>
				</select>
			</label>
			<label class="urme-check" title="Brand sync on, unique URME match, URME stock 0, not selected yet, still in the supplier feed"><input type="checkbox" name="ready" value="1" <?php checked( $f['ready'], '1' ); ?>> Ready for supplier sync</label>
			<label class="urme-check"><input type="checkbox" name="in_stock" value="1" <?php checked( $f['in_stock'], '1' ); ?>> In stock only</label>
			<label>Per page
				<select name="per_page">
					<?php foreach ( array( 20, 50, 100 ) as $n ) : ?>
						<option value="<?php echo esc_attr( 50 === $n ? '' : (string) $n ); ?>" <?php selected( $per_page, $n ); ?>><?php echo esc_html( (string) $n ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="urme-check"><input type="checkbox" name="show_missing" value="1" <?php checked( $f['show_missing'], '1' ); ?>> Include items no longer in feed</label>
			<button class="button">Search</button>
			<a class="button-link" href="<?php echo esc_url( self::url() ); ?>">Reset</a>
		</form>

		<div class="urme-catalog-bar">
			<span class="urme-count"><?php echo esc_html( sprintf( '%s watches found', number_format_i18n( $result['total'] ) ) ); ?>
				<?php if ( $rate ) : ?>· SEK at <?php echo esc_html( number_format_i18n( $rate['rate'], 4 ) ); ?><?php endif; ?></span>
			<?php if ( $default_view ) : ?>
				<span class="urme-muted urme-default-view">Brands enabled for sync only (a search looks in all brands) · <a href="<?php echo esc_url( self::url( array( 'brand_sync' => 'any' ) ) ); ?>">Show all brands</a></span>
			<?php endif; ?>
			<?php
			if ( '' !== $f['brand'] ) {
				$on = URME_SS_Settings::brand_enabled( $f['brand'] );
				echo '<span class="urme-brand-toggle">' . self::status_badge( $on ? 'exists' : 'off', sprintf( 'Sync %s for %s', $on ? 'enabled' : 'disabled', $f['brand'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo self::action_button( 'toggle_brand', $on ? 'Disable brand sync' : 'Enable brand sync', array( 'brand' => $f['brand'], 'enable' => $on ? '' : '1' ), 'button button-small' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			$checked = (int) get_option( 'urme_ss_match_checked', 0 );
			echo '<span class="urme-muted">URME match checked ' . esc_html( self::ago( $checked ) ) . '</span> ';
			echo self::action_button( 'rematch', 'Re-check now', array(), 'button-link' ); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="urme-select-form">
			<?php echo self::hidden_fields( 'select' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="tablenav top"><button type="submit" class="button button-primary urme-bulk" disabled>Select checked for sync</button>
				<span class="description">Selecting is always manual. Only watches with URME stock 0 can be selected (Dropshipping); watches with URME stock stay URME Lager. Watches in your store are listed first.</span></div>
			<table class="widefat striped urme-table">
				<thead><tr>
					<td class="check-column"><input type="checkbox" class="urme-check-all" aria-label="Select all"></td>
					<th class="urme-img-col">Image</th>
					<th class="urme-product-col">Product</th>
					<th class="urme-code-col">Model / EAN</th>
					<th class="num">Supplier stock</th>
					<th class="num urme-stock-col">URME stock</th>
					<th class="num">Cost</th>
					<th class="urme-hint-col">Price hint</th>
					<th class="urme-match-col">In URME</th>
					<th class="urme-sync-col">Sync</th>
				</tr></thead>
				<tbody>
				<?php if ( ! $result['rows'] ) : ?>
					<tr><td colspan="10">
						<?php
						echo URME_SS_DB::catalog_counts()['total']
							? 'No watches match your search.'
							: 'The catalog is empty. Click "Sync now" to download the supplier feed (this also runs automatically every hour).';
						?>
					</td></tr>
				<?php endif; ?>
				<?php
				foreach ( $result['rows'] as $row ) {
					self::render_catalog_row( $row, $stock, $hint );
				}
				?>
				</tbody>
			</table>
		</form>
		<?php
		self::pagination( $result['total'], $per_page, $page, array_merge( array( 'page' => self::SLUG, 'tab' => 'catalog' ), array_filter( $f ) ) );
	}

	/**
	 * One Supplier catalog row. Used by the page and by the AJAX row actions (which return
	 * the same row, re-rendered from the current data).
	 *
	 * @param array $row   Row from URME_SS_DB::search_catalog().
	 * @param array $stock URME_SS_Store::stock_info() for the row's product.
	 * @param array $hint  URME_SS_Price_Hint::context().
	 */
	private static function render_catalog_row( array $row, array $stock, array $hint ) {
		?>
		<tr class="<?php echo (int) $row['in_feed'] ? '' : 'urme-missing'; ?>" data-urme-key="<?php echo esc_attr( $row['item_key'] ); ?>">
			<th class="check-column">
				<?php if ( ! $row['link_id'] ) : ?>
					<input type="checkbox" name="item_keys[]" value="<?php echo esc_attr( $row['item_key'] ); ?>" aria-label="<?php echo esc_attr( 'Select ' . $row['product_no'] ); ?>">
				<?php endif; ?>
			</th>
			<td class="urme-img-col"><?php echo self::img( $row['img_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			<td class="urme-product-col">
				<span class="urme-brand"><?php echo esc_html( $row['manufacturer'] ); ?></span><br>
				<?php
				$age = URME_SS_DB::new_age( $row );
				if ( null !== $age ) {
					printf( '<span class="urme-new">NEW</span> <small class="urme-new-age">%s</small><br>', esc_html( 0 === $age ? 'Added today' : sprintf( 'Added %d day%s ago', $age, 1 === $age ? '' : 's' ) ) );
				}
				echo esc_html( $row['product_name'] );
				?>
				<?php if ( $row['subcategory'] ) : ?><br><small class="urme-muted"><?php echo esc_html( $row['subcategory'] ); ?></small><?php endif; ?>
				<?php if ( ! (int) $row['in_feed'] ) : ?><br><span class="urme-bad">Not in feed since <?php echo esc_html( self::mysql_datetime( $row['missing_since'] ) ); ?></span><?php endif; ?>
			</td>
			<td class="urme-code-col"><code><?php echo esc_html( $row['product_no'] ); ?></code><br><code class="urme-ean" title="EAN / ITEM_ID"><?php echo esc_html( $row['item_id'] ); ?></code></td>
			<td class="num"><?php echo null === $row['stock'] ? '—' : '<span class="' . ( (int) $row['stock'] > 0 ? 'urme-good' : 'urme-bad' ) . '">' . esc_html( $row['stock'] ) . '</span>'; ?></td>
			<td class="num urme-stock-col"><?php echo self::urme_stock_cell( $stock[ self::urme_product_id( $row ) ] ?? null ) . ( isset( $stock[ self::urme_product_id( $row ) ] ) ? '<br>' . URME_SS_Product_Source::html( self::urme_product_id( $row ) ) : '' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			<td class="num urme-cost-col"><?php echo esc_html( self::eur( $row['purchase_price'] ) ); ?><br><span class="urme-muted"><?php echo esc_html( self::sek( null === $row['purchase_price'] ? null : URME_SS_Rates::to_sek( $row['purchase_price'] ) ) ); ?></span></td>
			<td class="urme-hint-col"><?php echo URME_SS_Price_Hint::html( $row['purchase_price'], $hint ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			<td class="urme-match-col"><?php echo self::match_cell( $row ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			<td class="urme-sync-col">
				<?php
				// State on the first line, its buttons on one row below, then the sale price editor.
				$brand_on = URME_SS_Settings::brand_enabled( $row['manufacturer'] );
				$buttons  = array();
				$notes    = array();
				if ( ! $row['link_id'] ) {
					$state = '<span class="urme-muted">Not selected</span>';
					$start = self::start_control( $row, $stock[ self::urme_product_id( $row ) ] ?? null, $brand_on, URME_SS_Product_Source::is_linked( self::urme_product_id( $row ) ) );
					if ( 0 === strpos( $start, '<button' ) ) {
						$buttons[] = $start;
					} elseif ( '' !== $start ) {
						$notes[] = $start;
					}
				} elseif ( ! (int) $row['product_id'] ) {
					$state = '<span class="urme-bad">Selected, not linked</span>';
				} elseif ( ! $brand_on || ! (int) $row['sync_enabled'] || URME_SS_Inventory::LOCAL === $row['stock_mode'] ) {
					$state = '<span class="urme-muted">URME Lager</span>';
					if ( $brand_on ) {
						$buttons[] = self::row_button( 'dropship|' . (int) $row['link_id'], 'Dropshipping', 'button button-small', 'Switch ' . $row['product_no'] . ' to Dropshipping? Only allowed when its URME Lager stock is 0.' );
					}
				} else {
					$state = '<span class="urme-drop">Dropshipping</span>';
					if ( (int) $row['in_feed'] ) {
						$buttons[] = self::row_button( 'sync|' . (int) $row['link_id'], 'Sync now', 'button button-small' );
					}
					$buttons[] = self::row_button( 'lager|' . (int) $row['link_id'], 'URME Lager', 'button button-small', 'Move ' . $row['product_no'] . ' back to URME Lager? It leaves supplier sync and its stock becomes 0 (out of stock) until you enter the real stock in WooCommerce.' );
					if ( 'error' === $row['link_status'] ) {
						$notes[] = '<span class="urme-bad">Last sync: error</span>' . ( '' !== (string) $row['link_message'] ? '<br><small class="urme-bad">' . esc_html( $row['link_message'] ) . '</small>' : '' );
					}
				}
				if ( $row['link_id'] && (int) $row['product_id'] && ( 'manual' !== $row['link_method'] ) && (int) $row['product_id'] !== (int) $row['match_product_id'] ) {
					$notes[] = '<small>→ ' . self::product_link( (int) $row['product_id'] ) . '</small>';
				}
				echo '<div class="urme-sync-state">' . $state . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				if ( $buttons ) {
					echo '<div class="urme-sync-actions">' . implode( '', $buttons ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				foreach ( $notes as $note ) {
					echo '<div class="urme-sync-note">' . $note . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo self::sale_editor( $row, $stock[ self::urme_product_id( $row ) ] ?? null ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
				<div class="urme-row-msg" aria-live="polite"></div>
			</td>
		</tr>
		<?php
	}

	/**
	 * NEW supplier watches count: shown on the summary card and in the catalog filter, read once
	 * per page. Outside a page render (direct calls) it is always read fresh.
	 */
	private static function page_new_count() {
		if ( ! self::$in_render ) {
			return URME_SS_DB::new_count();
		}
		if ( null === self::$new_count ) {
			self::$new_count = URME_SS_DB::new_count();
		}
		return self::$new_count;
	}

	/**
	 * The WooCommerce product whose stock the catalog row shows: the linked product,
	 * else the unique confirmed match, else none.
	 */
	private static function urme_product_id( array $row ) {
		if ( (int) $row['product_id'] ) {
			return (int) $row['product_id'];
		}
		return URME_SS_Matcher::EXISTS === $row['match_status'] ? (int) $row['match_product_id'] : 0;
	}

	private static function urme_stock_cell( $info ) {
		if ( ! $info ) {
			return '<span class="urme-muted">—</span>';
		}
		if ( ! $info['managed'] ) {
			$status = array( 'instock' => 'in stock', 'outofstock' => 'out of stock', 'onbackorder' => 'on backorder' );
			$out    = '<span class="urme-muted">Not managed</span>' . ( isset( $status[ $info['status'] ] ) ? '<br><small>' . esc_html( $status[ $info['status'] ] ) . '</small>' : '' );
		} elseif ( $info['qty'] > 0 ) {
			$out = '<span class="urme-good">' . esc_html( (string) $info['qty'] ) . '</span>';
		} else {
			$out = '<span class="urme-bad">' . esc_html( (string) $info['qty'] ) . ' / Out of stock</span>';
		}
		if ( 'no' !== $info['backorders'] ) {
			$out .= '<br><small class="urme-warn">Backorders ' . esc_html( 'notify' === $info['backorders'] ? 'allowed (notify)' : 'allowed' ) . '</small>';
		}
		if ( $info['by_parent'] ) {
			$out .= '<br><small class="urme-muted">managed by parent</small>';
		}
		return $out;
	}

	/**
	 * A per-row button inside the catalog's bulk-select form; the handler reads row_action.
	 */
	private static function row_button( $value, $label, $class = 'button button-small', $confirm = '' ) {
		return sprintf(
			'<button type="submit" name="row_action" value="%s" class="%s"%s>%s</button>',
			esc_attr( $value ),
			esc_attr( $class ),
			$confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : '',
			esc_html( $label )
		);
	}

	/**
	 * Manual sale price editor for the row's confirmed product (inside the catalog form; the
	 * input is keyed by supplier item so each row's Save only sends its own value).
	 */
	private static function sale_editor( array $row, $info ) {
		if ( ! $info || $info['variable'] || ! self::urme_product_id( $row ) ) {
			return '';
		}
		$key = (string) $row['item_key'];
		return sprintf(
			'<div class="urme-price-edit"><small class="urme-muted">Regular: %s</small><div class="urme-price-sale"><label>Sale price <input type="text" name="sale_price[%s]" value="%s" size="7" inputmode="decimal" autocomplete="off" aria-label="Sale price (SEK)" placeholder="—"></label> %s</div></div>',
			esc_html( '' === $info['regular'] ? '—' : self::kr( $info['regular'] ) ),
			esc_attr( $key ),
			esc_attr( $info['sale'] ),
			self::row_button( 'sale|' . $key, 'Save', 'button button-small' )
		);
	}

	private static function kr( $value ) {
		$v = (float) $value;
		return number_format_i18n( $v, floor( $v ) === $v ? 0 : 2 ) . ' kr';
	}

	/**
	 * Start control for a not-selected watch: only for an enabled brand, a unique confirmed
	 * URME match that is not linked elsewhere, and an item still in the feed.
	 */
	private static function start_control( array $row, $info, $brand_on, $linked_elsewhere ) {
		if ( ! $brand_on || ! (int) $row['in_feed'] || URME_SS_Matcher::EXISTS !== $row['match_status'] || ! (int) $row['match_product_id'] || ! $info ) {
			return '';
		}
		if ( $linked_elsewhere ) {
			return '<small class="urme-muted">Product already linked to another supplier watch</small>';
		}
		if ( ! $info['managed'] || $info['by_parent'] ) {
			return '<small class="urme-muted">' . ( $info['by_parent'] ? 'Stock managed by the parent product' : 'Stock not managed in WooCommerce' ) . ' – use "Select checked for sync"</small>';
		}
		$key = (string) $row['item_key'];
		if ( $info['qty'] <= 0 ) {
			return self::row_button(
				'start_supplier|' . $key,
				'Dropshipping',
				'button button-small button-primary',
				sprintf( 'Start Dropshipping for %s? WooCommerce stock is set to the supplier stock (%s) and the cost to the supplier cost. Prices are not changed.', $row['product_no'], null === $row['stock'] ? '—' : (int) $row['stock'] )
			);
		}
		return '<small class="urme-muted">' . esc_html( sprintf( 'URME Lager (%d in stock) – Dropshipping is possible at stock 0', $info['qty'] ) ) . '</small>';
	}

	/**
	 * Icon + text status badge; colour is never the only signal.
	 *
	 * @param string $type exists|none|review|manual|pending|off.
	 */
	private static function status_badge( $type, $text ) {
		$icons = array(
			'exists'  => 'dashicons-yes-alt',
			'none'    => 'dashicons-dismiss',
			'review'  => 'dashicons-warning',
			'manual'  => 'dashicons-admin-links',
			'local'   => 'dashicons-store',
			'pending' => 'dashicons-clock',
			'off'     => 'dashicons-controls-pause',
		);
		return sprintf(
			'<span class="urme-badge urme-badge-%1$s"><span class="dashicons %2$s" aria-hidden="true"></span>%3$s</span>',
			esc_attr( $type ),
			esc_attr( $icons[ $type ] ?? 'dashicons-marker' ),
			esc_html( $text )
		);
	}

	/**
	 * Product name linking to its edit screen, plus a "View" link.
	 */
	private static function product_link( $product_id ) {
		$title = get_the_title( $product_id );
		if ( '' === $title && ! get_post( $product_id ) ) {
			return esc_html( '#' . $product_id . ' (deleted)' );
		}
		$view = get_permalink( self::edit_id( $product_id ) );
		return sprintf(
			'<a href="%s">%s</a>%s',
			esc_url( get_edit_post_link( self::edit_id( $product_id ) ) ?? '' ),
			esc_html( '' !== $title ? $title : '#' . $product_id ),
			$view ? sprintf( ' · <a href="%s" target="_blank" rel="noopener">View</a>', esc_url( $view ) ) : ''
		);
	}

	/**
	 * The "In URME" column: whether this supplier watch already exists in WooCommerce.
	 * Purely informational; it never selects or syncs anything.
	 */
	private static function match_cell( array $row ) {
		if ( (int) $row['product_id'] && 'manual' === $row['link_method'] ) {
			return self::status_badge( 'manual', 'Manually linked' ) . '<br><small>' . self::product_link( (int) $row['product_id'] ) . '</small>';
		}
		switch ( $row['match_status'] ) {
			case URME_SS_Matcher::EXISTS:
				$by = str_replace( array( 'sku', 'ean', '+' ), array( 'SKU', 'EAN', ' + ' ), $row['match_method'] );
				return self::status_badge( 'exists', 'Exists in URME' ) . '<br><small>' . self::product_link( (int) $row['match_product_id'] ) . ' <span class="urme-muted">(' . esc_html( $by ) . ')</span></small>';
			case URME_SS_Matcher::NONE:
				return self::status_badge( 'none', 'Not in URME' );
			case URME_SS_Matcher::REVIEW:
				$links = array();
				foreach ( array_filter( array_map( 'intval', explode( ',', (string) $row['match_candidates'] ) ) ) as $cid ) {
					$links[] = self::product_link( $cid );
				}
				return self::status_badge( 'review', 'Needs review' ) . '<br><small>Several possible matches:<br>' . implode( '<br>', $links ) . '</small>';
			default:
				return self::status_badge( 'pending', 'Not checked yet' );
		}
	}

	/**
	 * Variations are edited on their parent product's screen.
	 */
	private static function edit_id( $product_id ) {
		$parent = wp_get_post_parent_id( $product_id );
		return $parent ? $parent : $product_id;
	}

	/* --- Selected tab --------------------------------------------------- */

	/* --- Price Review tab ----------------------------------------------- */

	private static function render_reviews() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$show = 'all' === sanitize_key( $_GET['show'] ?? '' ) ? 'all' : URME_SS_Price_Review::PENDING;
		$rows = URME_SS_Price_Review::rows( $show );
		URME_SS_Store::prime_products( wp_list_pluck( $rows, 'product_id' ) ); // One load for all rows, not one per row.
		printf(
			'<p>Watches that switched automatically from URME Lager to Dropshipping. Check the selling price; the plugin never changes it. <a href="%s">%s</a></p>',
			esc_url( self::url( array( 'tab' => 'reviews', 'show' => 'all' === $show ? '' : 'all' ) ) ),
			'all' === $show ? 'Show only those needing review' : 'Show reviewed too'
		);
		?>
		<table class="widefat striped urme-table">
			<thead><tr>
				<th>Product</th><th>SKU</th><th>Transition date</th><th class="num">Supplier stock</th>
				<th class="num">Supplier cost EUR</th><th class="num">Supplier cost SEK</th><th class="num">Previous local cost</th>
				<th class="num">Current selling price</th><th>Status</th><th></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="10"><?php echo esc_html( 'all' === $show ? 'No price reviews yet.' : 'Nothing waiting for price review.' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) : ?>
				<?php $v = URME_SS_Price_Review::view( $r ); ?>
				<tr>
					<td><?php echo $v['edit'] ? '<a href="' . esc_url( $v['edit'] ) . '">' . esc_html( $v['name'] ) . '</a>' : esc_html( $v['name'] ); ?></td>
					<td><code><?php echo esc_html( '' !== $v['sku'] ? $v['sku'] : '—' ); ?></code></td>
					<td><?php echo esc_html( $v['when'] ); ?></td>
					<td class="num"><?php echo esc_html( null === $v['stock'] ? '—' : (string) $v['stock'] ); ?></td>
					<td class="num"><?php echo esc_html( self::eur( $v['eur'] ) ); ?></td>
					<td class="num"><?php echo esc_html( self::sek( $v['sek'] ) ); ?></td>
					<td class="num"><?php echo esc_html( self::sek( $v['local_cost'] ) ); ?></td>
					<td class="num"><?php echo esc_html( URME_SS_Price_Review::price_text( $v ) ); ?></td>
					<td>
						<?php
						echo URME_SS_Price_Review::PENDING === $r['status'] // phpcs:ignore WordPress.Security.EscapeOutput
							? self::status_badge( 'review', 'Needs review' )
							: self::status_badge( 'exists', 'Reviewed' ) . '<br><small>' . esc_html( self::mysql_datetime( $r['reviewed_at'] ) ) . '</small>';
						?>
					</td>
					<td>
						<?php if ( $v['edit'] ) : ?><a class="button button-primary button-small" href="<?php echo esc_url( $v['edit'] ); ?>">Review price</a><?php endif; ?>
						<?php
						if ( URME_SS_Price_Review::PENDING === $r['status'] ) {
							echo URME_SS_Price_Review::reviewed_button( (int) $r['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/* --- Status tab ----------------------------------------------------- */

	private static function render_status() {
		$status = URME_SS_Sync::status();
		$feed   = $status['feed'];
		$sync   = $status['sync'];
		$rstate = URME_SS_Rates::state();
		$rate   = URME_SS_Rates::current();
		$target = URME_SS_Store::cost_target();
		$next   = wp_next_scheduled( URME_SS_Plugin::CRON_HOOK );
		?>
		<div class="urme-columns">
		<div>
			<h2>Supplier feed</h2>
			<table class="widefat striped urme-kv">
				<tr><th>Last successful update</th><td><?php echo esc_html( self::datetime( $feed['last_success'] ?? 0 ) ); ?></td></tr>
				<tr><th>Last catalog change</th><td><?php echo esc_html( self::datetime( $feed['last_changed'] ?? 0 ) ); ?></td></tr>
				<tr><th>Last attempt</th><td><?php echo esc_html( self::datetime( $feed['last_attempt'] ?? 0 ) ); ?></td></tr>
				<tr><th>Result</th><td><?php echo esc_html( $feed['last_result'] ?? '—' ); ?></td></tr>
				<?php if ( ! empty( $feed['last_error'] ) ) : ?>
					<tr class="urme-error-row"><th>Last error</th><td><?php echo esc_html( $feed['last_error'] ); ?><br><small><?php echo esc_html( self::datetime( $feed['last_error_at'] ?? 0 ) ); ?></small>
						<?php if ( false !== strpos( $feed['last_error'], 'Accept current feed' ) ) : ?>
							<br><?php echo self::action_button( 'accept_feed', 'Accept current feed', array(), 'button', 'Accept the feed even though it is much smaller than before? Watches not in it will be flagged as missing (products are never changed for missing watches).' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endif; ?>
					</td></tr>
				<?php endif; ?>
				<tr><th>Products in feed (all categories)</th><td><?php echo esc_html( number_format_i18n( $feed['total_items'] ?? 0 ) ); ?></td></tr>
				<tr><th>Supplier watches found</th><td><?php echo esc_html( number_format_i18n( $feed['watches_found'] ?? 0 ) ); ?></td></tr>
				<tr><th>Skipped (no ITEM_ID/PRODUCTNO) / duplicates</th><td><?php echo esc_html( ( $feed['skipped_invalid'] ?? 0 ) . ' / ' . ( $feed['duplicates'] ?? 0 ) ); ?></td></tr>
				<tr><th>Download size / time / peak memory</th><td><?php echo esc_html( size_format( $feed['bytes'] ?? 0 ) . ' / ' . ( $feed['duration'] ?? '—' ) . ' s / ' . ( $feed['peak_memory_mb'] ?? '—' ) . ' MB' ); ?></td></tr>
				<?php if ( ! empty( $feed['categories'] ) ) : ?>
					<tr><th>Categories in feed</th><td><?php echo esc_html( implode( ', ', array_map( static function ( $k, $v ) { return ( '' === $k ? '(none)' : $k ) . ': ' . $v; }, array_keys( $feed['categories'] ), $feed['categories'] ) ) ); ?></td></tr>
				<?php endif; ?>
				<tr><th>Brands enabled for sync</th><td><?php echo esc_html( URME_SS_Settings::get( 'enabled_brands' ) ? implode( ', ', URME_SS_Settings::get( 'enabled_brands' ) ) : 'none' ); ?></td></tr>
				<tr><th>Next automatic run</th><td><?php echo esc_html( URME_SS_Settings::get( 'auto_sync' ) ? ( $next ? self::datetime( $next ) : 'not scheduled' ) : 'automatic sync is off' ); ?></td></tr>
			</table>

			<h2>Last product sync</h2>
			<table class="widefat striped urme-kv">
				<tr><th>Time</th><td><?php echo esc_html( self::datetime( $sync['last_run'] ?? 0 ) . ( ! empty( $sync['trigger'] ) ? ' (' . $sync['trigger'] . ')' : '' ) ); ?></td></tr>
				<?php if ( ! empty( $sync['skipped'] ) ) : ?>
					<tr class="urme-error-row"><th>Skipped</th><td><?php echo esc_html( $sync['skipped'] ); ?></td></tr>
				<?php endif; ?>
				<tr><th>Selected for sync</th><td><?php echo esc_html( $sync['selected'] ?? 0 ); ?></td></tr>
				<tr><th>Checked</th><td><?php echo esc_html( $sync['checked'] ?? 0 ); ?></td></tr>
				<tr><th>Stock updated</th><td><?php echo esc_html( $sync['stock_updated'] ?? 0 ); ?></td></tr>
				<tr><th>Cost updated</th><td><?php echo esc_html( $sync['cost_updated'] ?? 0 ); ?></td></tr>
				<tr><th>Already up to date</th><td><?php echo esc_html( $sync['unchanged'] ?? 0 ); ?></td></tr>
				<tr><th>Unmatched (not linked)</th><td><?php echo esc_html( $sync['unmatched'] ?? 0 ); ?></td></tr>
				<tr><th>Missing from supplier feed</th><td><?php echo esc_html( $sync['missing'] ?? 0 ); ?></td></tr>
				<tr><th>In disabled brands (not processed)</th><td><?php echo esc_html( $sync['brand_off'] ?? 0 ); ?></td></tr>
				<tr><th>URME Lager (stock above 0, not synced)</th><td><?php echo esc_html( $sync['local_waiting'] ?? 0 ); ?></td></tr>
				<tr><th>URME Lager → Dropshipping switches</th><td><?php echo esc_html( $sync['handovers'] ?? 0 ); ?></td></tr>
				<tr class="<?php echo empty( $sync['local_blocked'] ) ? '' : 'urme-error-row'; ?>"><th>URME Lager on hold (backorders allowed)</th><td><?php echo esc_html( $sync['local_blocked'] ?? 0 ); ?></td></tr>
				<tr class="<?php echo empty( $sync['errors'] ) ? '' : 'urme-error-row'; ?>"><th>Errors</th><td><?php echo esc_html( $sync['errors'] ?? 0 ); ?>
					<?php foreach ( (array) ( $sync['error_list'] ?? array() ) as $err ) : ?>
						<br><small><?php echo esc_html( $err ); ?></small>
					<?php endforeach; ?>
				</td></tr>
			</table>
		</div>
		<div>
			<h2>EUR / SEK exchange rate</h2>
			<table class="widefat striped urme-kv">
				<tr><th>Rate in use</th><td><?php echo $rate ? esc_html( number_format_i18n( $rate['rate'], 4 ) . ( $rate['overridden'] ? ' (manual override)' : '' ) ) : '—'; ?></td></tr>
				<tr><th>Latest fetched rate</th><td><?php echo esc_html( empty( $rstate['rate'] ) ? '—' : number_format_i18n( (float) $rstate['rate'], 4 ) . ' · ' . ( $rstate['source'] ?? '' ) ); ?></td></tr>
				<tr><th>Rate date</th><td><?php echo esc_html( $rstate['date'] ?? '—' ); ?></td></tr>
				<tr><th>Last update</th><td><?php echo esc_html( self::datetime( $rstate['fetched_at'] ?? 0 ) ); ?> <span class="urme-muted">(refreshed automatically every 12 h)</span></td></tr>
				<?php if ( ! empty( $rstate['last_error'] ) ) : ?>
					<tr class="urme-error-row"><th>Last error</th><td><?php echo esc_html( $rstate['last_error'] ); ?></td></tr>
				<?php endif; ?>
			</table>
			<p><?php echo self::action_button( 'refresh_rate', 'Fetch rate now' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>

			<?php self::render_inspection( $target ); ?>
		</div>
		</div>

		<h2>Activity log</h2>
		<table class="widefat striped urme-log">
			<tbody>
			<?php foreach ( URME_SS_Log::entries( 100 ) as $e ) : ?>
				<tr class="urme-log-<?php echo esc_attr( $e[1] ); ?>"><td class="urme-log-time"><?php echo esc_html( self::datetime( $e[0] ) ); ?></td><td><?php echo esc_html( $e[2] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">Full logs: WooCommerce &gt; Status &gt; Logs, source "urme-supplier-sync".</p>
		<?php
	}

	private static function render_inspection( array $target ) {
		$i = URME_SS_Store::inspect();
		?>
		<h2>Store setup (detected)</h2>
		<table class="widefat striped urme-kv">
			<tr><th>WooCommerce</th><td><?php echo esc_html( $i['wc_version'] ); ?> · <?php echo esc_html( $i['products'] . ' products, ' . $i['variations'] . ' variations' ); ?></td></tr>
			<tr><th>SKU (_sku)</th><td><?php echo esc_html( $i['with_sku'] ); ?> products/variations have a SKU</td></tr>
			<tr><th>EAN / GTIN</th><td>
				WooCommerce GTIN field (_global_unique_id): <?php echo esc_html( $i['with_gtin'] ); ?>
				<?php if ( null !== $i['ean_attribute'] ) : ?><br>EAN attribute (pa_ean): <?php echo esc_html( $i['ean_attribute'] ); ?><?php endif; ?>
				<?php foreach ( $i['gtin_keys'] as $k => $n ) : ?><br><?php echo esc_html( "{$k}: {$n}" ); ?><?php endforeach; ?>
				<br><span class="urme-muted">All of these are used for EAN matching.</span>
			</td></tr>
			<tr><th>Stock management</th><td><?php echo esc_html( $i['manage_stock'] ); ?> products/variations manage stock</td></tr>
			<tr><th>WooCommerce Cost of Goods Sold</th><td>
				<?php
				if ( ! $i['cogs_supported'] ) {
					echo 'Not available in this WooCommerce version';
				} else {
					echo esc_html( ( $i['cogs_enabled'] ? 'Enabled' : 'Available but not enabled' ) . ' · ' . $i['cogs_count'] . ' products have a value' );
				}
				?>
			</td></tr>
			<tr><th>Cost plugin fields</th><td>
				<?php
				$any = false;
				foreach ( $i['cost_keys'] as $k => $info ) {
					if ( $info['count'] ) {
						$any = true;
						echo esc_html( "{$info['label']} ({$k}): {$info['count']}" ) . '<br>';
					}
				}
				foreach ( $i['other_keys'] as $k => $n ) {
					$any = true;
					echo esc_html( "Other meta {$k}: {$n}" ) . ' <span class="urme-muted">(can be chosen as custom key)</span><br>';
				}
				if ( null !== $i['atum'] ) {
					$any = true;
					echo esc_html( "ATUM purchase price: {$i['atum']} products" ) . ' <span class="urme-muted">(ATUM is not written to in this version)</span><br>';
				}
				if ( ! $any ) {
					echo 'None found';
				}
				?>
			</td></tr>
			<tr class="<?php echo $target['type'] ? '' : 'urme-error-row'; ?>"><th>Cost price is written to</th><td><strong><?php echo esc_html( $target['label'] ); ?></strong><br><small><?php echo esc_html( $target['reason'] ); ?></small></td></tr>
			<tr><th>Checked</th><td><?php echo esc_html( self::datetime( $i['time'] ) ); ?></td></tr>
		</table>
		<p><?php echo self::action_button( 'inspect', 'Re-check store setup' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
		<?php
	}

	/* --- Settings tab --------------------------------------------------- */

	/**
	 * Brand allowlist, built from the MANUFACTURER values in the feed.
	 */
	private static function render_brand_checklist( array $s ) {
		$enabled = array_flip( URME_SS_Settings::enabled_brand_keys() );
		$brands  = array();
		foreach ( URME_SS_DB::brands() as $b ) {
			$brands[ URME_SS_Settings::brand_key( $b['manufacturer'] ) ] = array( $b['manufacturer'], (int) $b['n'], (int) $b['selected'] );
		}
		// Keep enabled brands visible even if they are not in the current feed.
		foreach ( (array) $s['enabled_brands'] as $name ) {
			if ( ! isset( $brands[ URME_SS_Settings::brand_key( $name ) ] ) ) {
				$brands[ URME_SS_Settings::brand_key( $name ) ] = array( $name, 0, 0 );
			}
		}
		ksort( $brands );
		echo '<input type="hidden" name="settings[brands_present]" value="1">';
		// Brands ticked as shown: on save only the changes made on this page are applied.
		echo '<input type="hidden" name="settings[brands_before]" value="' . esc_attr( wp_json_encode( array_values( (array) $s['enabled_brands'] ) ) ) . '">';
		if ( ! $brands ) {
			echo '<p class="description">Brands appear here after the first feed download.</p>';
			return;
		}
		echo '<p><button type="button" class="button-link urme-brands-all">Select all</button> · <button type="button" class="button-link urme-brands-none">Select none</button></p>';
		echo '<div class="urme-brand-list">';
		foreach ( $brands as $key => $b ) {
			printf(
				'<label><input type="checkbox" name="settings[enabled_brands][]" value="%s" %s> %s <span class="urme-muted">%s</span></label>',
				esc_attr( $b[0] ),
				checked( isset( $enabled[ $key ] ), true, false ),
				esc_html( $b[0] ),
				esc_html( sprintf( '%d watches%s', $b[1], $b[2] ? sprintf( ', %d selected', $b[2] ) : '' ) . ( $b[1] ? '' : ' – not in current feed' ) )
			);
		}
		echo '</div>';
		echo '<p class="description">Only selected watches of checked brands are synced (and only CATEGORY ' . esc_html( implode( ', ', (array) $s['categories'] ) ) . '). Unchecking a brand keeps its selections and links; its products are simply left alone until you enable it again.</p>';
	}

	private static function render_settings() {
		$s       = URME_SS_Settings::all();
		$inspect = URME_SS_Store::inspect();
		$rstate  = URME_SS_Rates::state();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo self::hidden_fields( 'save_settings' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<table class="form-table">
				<tr><th><label for="urme-feed">Supplier feed URL</label></th>
					<td><input type="url" id="urme-feed" name="settings[feed_url]" class="large-text" value="<?php echo esc_attr( $s['feed_url'] ); ?>"></td></tr>
				<tr><th><label for="urme-cats">Supplier categories</label></th>
					<td><input type="text" id="urme-cats" name="settings[categories]" class="regular-text" value="<?php echo esc_attr( implode( ', ', $s['categories'] ) ); ?>">
					<p class="description">CATEGORY values to keep from the feed, comma separated. Version 1: <code>WATCH</code>.</p></td></tr>
				<tr><th>Brands enabled for sync</th>
					<td>
						<?php self::render_brand_checklist( $s ); ?>
					</td></tr>
				<tr><th>Automatic sync</th>
					<td><label><input type="checkbox" name="settings[auto_sync]" value="1" <?php checked( $s['auto_sync'] ); ?>> Refresh the feed and sync selected products every hour</label>
					<p class="description">Uses WP-Cron. For reliable hourly runs on a quiet site, call <code>wp-cron.php</code> from a real server cron job.</p></td></tr>
				<tr><th>Cost price field</th>
					<td>
						<select name="settings[cost_target]" id="urme-cost-target">
							<option value="auto" <?php selected( $s['cost_target'], 'auto' ); ?>>Automatic – use the field this store already uses</option>
							<option value="wc_cogs" <?php selected( $s['cost_target'], 'wc_cogs' ); ?>>WooCommerce Cost of Goods Sold (built in)<?php echo $inspect['cogs_enabled'] ? '' : ' – not enabled'; ?></option>
							<option value="meta" <?php selected( $s['cost_target'], 'meta' ); ?>>Product meta key (cost plugin)</option>
							<option value="none" <?php selected( $s['cost_target'], 'none' ); ?>>Do not sync cost</option>
						</select>
						<p>
							<input type="text" name="settings[cost_meta_key]" list="urme-cost-keys" value="<?php echo esc_attr( $s['cost_meta_key'] ); ?>" placeholder="e.g. _wc_cog_cost" class="regular-text">
							<datalist id="urme-cost-keys">
								<?php foreach ( URME_SS_Store::KNOWN_COST_KEYS as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
								<?php foreach ( array_keys( $inspect['other_keys'] ) as $k ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>">
								<?php endforeach; ?>
							</datalist>
						</p>
						<p class="description">Currently: <strong><?php echo esc_html( URME_SS_Store::cost_target()['label'] ); ?></strong>. The selling price (regular/sale price) is never changed. See Status for what was detected in this store.</p>
					</td></tr>
				<tr><th><label for="urme-rate">EUR/SEK manual override</label></th>
					<td><input type="text" id="urme-rate" name="settings[rate_override]" value="<?php echo esc_attr( $s['rate_override'] ); ?>" placeholder="<?php echo esc_attr( empty( $rstate['rate'] ) ? 'e.g. 11.05' : (string) $rstate['rate'] ); ?>" class="small-text" inputmode="decimal">
					<p class="description">Leave empty to use the automatic daily rate (ECB, Riksbank as fallback).</p></td></tr>
				<tr><th>Stock management</th>
					<td><label><input type="checkbox" name="settings[manage_stock]" value="1" <?php checked( $s['manage_stock'] ); ?>> Turn on "Manage stock" for linked products that don't have it, so the supplier quantity can be stored</label>
					<p class="description">If off, such products only get "In stock" / "Out of stock".</p></td></tr>
				<tr><th><label for="urme-ratio">Feed safety check</label></th>
					<td>Refuse a feed with fewer than <input type="number" id="urme-ratio" name="settings[min_feed_ratio]" min="0" max="100" value="<?php echo esc_attr( $s['min_feed_ratio'] ); ?>" class="small-text"> % of the previous feed's watches
					<p class="description">Protects against a half-empty feed. 0 disables the check.</p></td></tr>
				<tr><th><label for="urme-age">Stale feed protection</label></th>
					<td>Stop updating products when the feed has not been refreshed for <input type="number" id="urme-age" name="settings[max_feed_age_hours]" min="1" max="72" value="<?php echo esc_attr( $s['max_feed_age_hours'] ); ?>" class="small-text"> hours</td></tr>
			</table>
			<h2>Selling price hint</h2>
			<p class="description">A suggested selling price shown in the Supplier catalog, for you only. It never changes any price, coupon or product.
				Suggested price = the lowest price, rounded up, where (price after coupon ÷ (1 + VAT)) − payment fee − cost ≥ target profit. Cost = (PURCHASE_PRICE + extra cost) × EUR/SEK; PURCHASE_PRICE is VAT 0%.</p>
			<table class="form-table">
				<tr><th><label for="urme-h-extra">Extra supplier cost</label></th>
					<td><input type="number" id="urme-h-extra" name="settings[hint_extra_eur]" min="0" max="1000" step="0.01" value="<?php echo esc_attr( $s['hint_extra_eur'] ); ?>" class="small-text"> EUR per watch, added to PURCHASE_PRICE</td></tr>
				<tr><th><label for="urme-h-coupon">Coupon allowance</label></th>
					<td><input type="number" id="urme-h-coupon" name="settings[hint_coupon_pct]" min="0" max="90" step="0.01" value="<?php echo esc_attr( $s['hint_coupon_pct'] ); ?>" class="small-text"> % discount the customer may use</td></tr>
				<tr><th><label for="urme-h-fee">Klarna/payment fee</label></th>
					<td><input type="number" id="urme-h-fee" name="settings[hint_fee_pct]" min="0" max="50" step="0.01" value="<?php echo esc_attr( $s['hint_fee_pct'] ); ?>" class="small-text"> % of the amount the customer pays after the coupon</td></tr>
				<tr><th><label for="urme-h-vat">VAT</label></th>
					<td><input type="number" id="urme-h-vat" name="settings[hint_vat_pct]" min="0" max="100" step="0.01" value="<?php echo esc_attr( $s['hint_vat_pct'] ); ?>" class="small-text"> % included in the selling price</td></tr>
				<tr><th><label for="urme-h-profit">Target profit</label></th>
					<td><input type="number" id="urme-h-profit" name="settings[hint_profit_sek]" min="0" step="1" value="<?php echo esc_attr( $s['hint_profit_sek'] ); ?>" class="small-text"> SEK per watch</td></tr>
				<tr><th><label for="urme-h-round">Rounding</label></th>
					<td>Round up to the next <input type="number" id="urme-h-round" name="settings[hint_round_sek]" min="1" max="1000" step="1" value="<?php echo esc_attr( $s['hint_round_sek'] ); ?>" class="small-text"> SEK (never down)</td></tr>
			</table>
			<?php submit_button( 'Save settings' ); ?>
		</form>
		<?php
	}
}
