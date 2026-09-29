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

	const SLUG = 'urme-supplier-sync';
	const CAP  = 'manage_woocommerce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'admin_post_urme_ss', array( __CLASS__, 'handle' ) );
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

		switch ( $do ) {
			case 'select':
				$keys = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['item_keys'] ?? array() ) );
				self::notice( self::select_items( $keys ) );
				break;

			case 'unselect':
				$link = URME_SS_DB::get_link_by_id( absint( $_POST['link_id'] ?? 0 ) );
				if ( $link && URME_SS_Inventory::has_local_units( $link ) ) {
					self::notice( sprintf( 'Not removed: this watch still has %d local unit(s) in Local first. Switch it to "Supplier now" first if you really want to stop tracking them.', $link['local_qty'] ), 'error' );
				} elseif ( $link ) {
					URME_SS_DB::delete_link( $link['id'] );
					self::notice( 'Removed from sync. The WooCommerce product was not changed.' );
				}
				break;

			case 'link':
				$link = URME_SS_DB::get_link_by_id( absint( $_POST['link_id'] ?? 0 ) );
				$pid  = absint( $_POST['product_id'] ?? 0 );
				self::notice( self::link_product( $link, $pid ) );
				break;

			case 'unlink':
				$link = URME_SS_DB::get_link_by_id( absint( $_POST['link_id'] ?? 0 ) );
				if ( $link && URME_SS_Inventory::has_local_units( $link ) ) {
					self::notice( sprintf( 'Not unlinked: this watch still has %d local unit(s) in Local first. Switch it to "Supplier now" first.', $link['local_qty'] ), 'error' );
				} elseif ( $link ) {
					URME_SS_DB::update_link(
						$link['id'],
						array(
							'product_id'   => 0,
							'match_method' => '',
							'last_status'  => 'unlinked',
							'last_message' => 'Unlinked manually.',
						)
					);
					self::notice( 'Product unlinked. The WooCommerce product was not changed.' );
				}
				break;

			case 'automatch':
				self::notice( self::automatch( absint( $_POST['link_id'] ?? 0 ) ) );
				break;

			case 'review_done':
				$ok = URME_SS_Price_Review::mark_reviewed( absint( $_POST['review_id'] ?? 0 ) );
				self::notice( $ok ? 'Marked as reviewed. The selling price was not changed by the plugin.' : 'Already reviewed.' );
				break;

			case 'set_mode':
				self::notice_result( self::set_mode( URME_SS_DB::get_link_by_id( absint( $_POST['link_id'] ?? 0 ) ), sanitize_key( $_POST['mode'] ?? '' ) ) );
				break;

			case 'toggle':
				$link = URME_SS_DB::get_link_by_id( absint( $_POST['link_id'] ?? 0 ) );
				if ( $link ) {
					$enabled = (int) $link['sync_enabled'] ? 0 : 1;
					URME_SS_DB::update_link( $link['id'], array( 'sync_enabled' => $enabled ) );
					self::notice( $enabled ? 'Sync resumed for this watch.' : 'Sync paused for this watch.' );
				}
				break;

			case 'sync_one':
				$result = URME_SS_Sync::run(
					array(
						'refresh_feed' => false,
						'link_id'      => absint( $_POST['link_id'] ?? 0 ),
					)
				);
				if ( ! $result['ran'] ) {
					self::notice( $result['message'], 'warning' );
				} elseif ( ! empty( $result['sync']['skipped'] ) ) {
					self::notice( 'Not synced: ' . $result['sync']['skipped'], 'warning' );
				} else {
					self::notice( 'Synced this product. See its status below.' );
				}
				break;

			case 'toggle_brand':
				$brand  = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
				$enable = ! empty( $_POST['enable'] );
				if ( '' !== $brand ) {
					URME_SS_Settings::set_brand( $brand, $enable );
					self::notice(
						$enable
							? sprintf( 'Sync enabled for %s. Selected %s watches are updated on the next sync.', $brand, $brand )
							: sprintf( 'Sync disabled for %s. Its selections and links are kept; its products are no longer updated.', $brand )
					);
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
				$old = URME_SS_Settings::all();
				$new = URME_SS_Settings::save( wp_unslash( (array) ( $_POST['settings'] ?? array() ) ) );
				if ( $old['categories'] !== $new['categories'] || $old['feed_url'] !== $new['feed_url'] ) {
					self::notice( 'Settings saved. Click "Sync now" to reload the catalog with the new feed settings.' );
				} else {
					self::notice( 'Settings saved.' );
				}
				break;
		}
		// phpcs:enable

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Select supplier items for sync and try to link each automatically.
	 */
	private static function select_items( array $keys ) {
		$added      = 0;
		$matched    = 0;
		$off_brands = array();
		foreach ( array_unique( array_filter( $keys ) ) as $key ) {
			$item = URME_SS_DB::get_item( $key );
			if ( ! $item || URME_SS_DB::get_link( $key ) ) {
				continue;
			}
			if ( ! URME_SS_Settings::brand_enabled( $item['manufacturer'] ) ) {
				$off_brands[] = $item['manufacturer'];
			}
			$match   = URME_SS_Store::auto_match( $item );
			$link_id = URME_SS_DB::insert_link( $key, $match['product_id'], $match['method'] );
			if ( ! $link_id ) {
				continue;
			}
			++$added;
			if ( $match['product_id'] ) {
				++$matched;
			} else {
				URME_SS_DB::update_link(
					$link_id,
					array(
						'last_status'  => 'unlinked',
						'last_message' => $match['message'],
					)
				);
			}
		}
		if ( ! $added ) {
			return 'Nothing new was selected.';
		}
		$msg = sprintf(
			'%d watch(es) selected; %d linked automatically by SKU/EAN%s. Products are updated on the next sync (or click "Sync now").',
			$added,
			$matched,
			$added > $matched ? sprintf( ', %d need manual linking under "Selected watches"', $added - $matched ) : ''
		);
		if ( $off_brands ) {
			$msg .= ' Note: sync is disabled for ' . implode( ', ', array_unique( $off_brands ) ) . ' – enable the brand to sync these.';
		}
		return $msg;
	}

	/**
	 * Local first / Supplier now / Paused for one selected watch.
	 *
	 * @return array{0: string, 1: string} Message and notice type.
	 */
	private static function set_mode( $link, $mode ) {
		// phpcs:disable WordPress.Security.NonceVerification -- verified in handle().
		if ( ! $link ) {
			return array( 'Selection not found.', 'error' );
		}
		switch ( $mode ) {
			case 'paused':
				URME_SS_DB::update_link( $link['id'], array( 'sync_enabled' => 0 ) );
				return array( 'Paused. Nothing is synced for this watch; its Local first state (if any) is kept.', 'success' );

			case 'local':
				$qty  = absint( $_POST['local_qty'] ?? 0 );
				$raw  = str_replace( array( ' ', ',' ), array( '', '.' ), sanitize_text_field( wp_unslash( $_POST['local_cost'] ?? '' ) ) );
				$cost = ( '' !== $raw && is_numeric( $raw ) && (float) $raw >= 0 ) ? (float) $raw : null;
				$ok   = URME_SS_Inventory::enable_local( (int) $link['id'], $qty, $cost );
				return true === $ok
					? array( sprintf( 'Local first: %d local unit(s) will be sold before supplier stock. WooCommerce stock was set to %d%s.', $qty, $qty, null === $cost ? '' : ' and cost to ' . wc_format_decimal( $cost, 2 ) . ' SEK' ), 'success' )
					: array( $ok, 'error' );

			case 'supplier':
				if ( URME_SS_Inventory::has_local_units( $link ) && empty( $_POST['confirm_drop'] ) ) {
					return array( sprintf( 'Not changed: %d local unit(s) are still tracked. Confirm the switch to drop them.', $link['local_qty'] ), 'error' );
				}
				URME_SS_Inventory::enable_supplier( (int) $link['id'] );
				return array( 'Supplier now: supplier stock and cost are synced on the next sync.', 'success' );
		}
		return array( 'Unknown mode.', 'error' );
		// phpcs:enable
	}

	private static function notice_result( array $result ) {
		self::notice( $result[0], $result[1] );
	}

	private static function link_product( $link, $pid ) {
		if ( ! $link ) {
			return 'Selection not found.';
		}
		if ( URME_SS_Inventory::has_local_units( $link ) && (int) $link['product_id'] !== (int) $pid ) {
			return sprintf( 'Not changed: this watch still has %d local unit(s) in Local first on its current product. Switch it to "Supplier now" first.', $link['local_qty'] );
		}
		$product = $pid ? wc_get_product( $pid ) : null;
		if ( ! $product ) {
			return 'Choose a WooCommerce product first.';
		}
		$other = URME_SS_DB::item_key_for_product( $pid, $link['id'] );
		if ( $other ) {
			return sprintf( 'Not linked: "%s" is already linked to supplier item %s.', $product->get_name(), $other );
		}
		URME_SS_DB::update_link(
			$link['id'],
			array(
				'product_id'   => $pid,
				'match_method' => 'manual',
				'last_status'  => '',
				'last_message' => '',
			)
		);
		// Push stock and cost for this one product right away (with the usual safety checks).
		$result = URME_SS_Sync::run(
			array(
				'refresh_feed' => false,
				'link_id'      => (int) $link['id'],
			)
		);
		if ( ! empty( $result['sync']['skipped'] ) ) {
			return sprintf( 'Linked to "%s". Not synced yet: %s', $product->get_name(), $result['sync']['skipped'] );
		}
		return sprintf( 'Linked to "%s" and synced.', $product->get_name() );
	}

	private static function automatch( $link_id ) {
		$links = $link_id
			? array_filter( array( URME_SS_DB::get_link_by_id( $link_id ) ) )
			: URME_SS_DB::get_links( array( 'status' => 'unlinked' ) )['rows'];
		$ok    = 0;
		$fail  = array();
		foreach ( $links as $link ) {
			if ( (int) $link['product_id'] ) {
				continue;
			}
			$item = URME_SS_DB::get_item( $link['item_key'] );
			if ( ! $item ) {
				continue;
			}
			$match = URME_SS_Store::auto_match( $item, (int) $link['id'] );
			if ( $match['product_id'] ) {
				URME_SS_DB::update_link(
					$link['id'],
					array(
						'product_id'   => $match['product_id'],
						'match_method' => $match['method'],
						'last_status'  => '',
						'last_message' => '',
					)
				);
				++$ok;
			} else {
				URME_SS_DB::update_link(
					$link['id'],
					array(
						'last_status'  => 'unlinked',
						'last_message' => $match['message'],
					)
				);
				$fail[] = $item['product_no'];
			}
		}
		$msg = sprintf( '%d linked automatically.', $ok );
		if ( $fail ) {
			$msg .= ' No unique match for: ' . implode( ', ', array_slice( $fail, 0, 15 ) ) . ( count( $fail ) > 15 ? '…' : '' );
		}
		return $msg;
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
			'selected' => 'Selected watches',
			'reviews'  => 'Price Review',
			'status'   => 'Status & log',
			'settings' => 'Settings',
		);
		$pending = URME_SS_Price_Review::pending_count();
		if ( $pending ) {
			$tabs['reviews'] .= sprintf( ' (%d)', $pending );
		}
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'catalog';
		}

		echo '<div class="wrap urme-ss"><h1>Supplier Sync</h1>';
		self::print_notice();
		self::render_summary();

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $id => $label ) {
			printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( self::url( array( 'tab' => $id ) ) ), $id === $tab ? ' nav-tab-active' : '', esc_html( $label ) );
		}
		echo '</nav><div class="urme-tab">';
		call_user_func( array( __CLASS__, 'render_' . $tab ) );
		echo '</div></div>';
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

		self::card( 'Supplier watches', number_format_i18n( $counts['in_feed'] ), $counts['missing'] ? number_format_i18n( $counts['missing'] ) . ' no longer in feed' : 'in local catalog' );

		self::card(
			'Selected for sync',
			number_format_i18n( $links['selected'] ?? 0 ),
			sprintf( '%d linked · %d not linked · %d missing', $links['linked'] ?? 0, $links['unlinked'] ?? 0, $links['missing'] ?? 0 )
				. ( ! empty( $links['local_first'] ) ? sprintf( '<br>%d in Local first', $links['local_first'] ) : '' )
				. ( ! empty( $links['brand_off'] ) ? sprintf( '<br>%d in disabled brands (not synced)', $links['brand_off'] ) : '' ),
			! empty( $links['unlinked'] ) || ! empty( $links['errors'] ) || ! empty( $links['brand_off'] )
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
			printf( '<div class="notice notice-warning inline"><p><strong>No brands are enabled for sync yet.</strong> Selected watches are only synced when their brand is enabled. <a href="%s">Choose brands in Settings</a>.</p></div>', esc_url( self::url( array( 'tab' => 'settings' ) ) ) );
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
			'brand_sync'   => in_array( $_GET['brand_sync'] ?? '', array( 'on', 'off' ), true ) ? sanitize_key( $_GET['brand_sync'] ) : '',
			'show_missing' => empty( $_GET['show_missing'] ) ? '' : '1',
			'category'     => sanitize_text_field( wp_unslash( $_GET['category'] ?? '' ) ),
		);
		$page = max( 1, absint( $_GET['paged'] ?? 1 ) );
		// phpcs:enable
		$per_page = 50;

		$result  = URME_SS_DB::search_catalog( array_merge( $f, array( 'page' => $page, 'per_page' => $per_page ) ) );
		$brands  = URME_SS_DB::brands();
		$rate    = URME_SS_Rates::current();
		$cats    = URME_SS_Settings::get( 'categories' );
		$mcounts = URME_SS_DB::match_counts();

		$product_ids = array();
		foreach ( $result['rows'] as $row ) {
			$product_ids[] = (int) $row['product_id'];
			$product_ids[] = (int) $row['match_product_id'];
			foreach ( explode( ',', (string) $row['match_candidates'] ) as $cid ) {
				$product_ids[] = (int) $cid;
			}
		}
		_prime_post_caches( array_filter( array_unique( $product_ids ) ), false, false );

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
					<option value="">All brands</option>
					<?php foreach ( $brands as $b ) : ?>
						<option value="<?php echo esc_attr( $b['manufacturer'] ); ?>" <?php selected( $f['brand'], $b['manufacturer'] ); ?>><?php echo esc_html( $b['manufacturer'] . ' (' . (int) $b['n'] . ')' . ( URME_SS_Settings::brand_enabled( $b['manufacturer'] ) ? ' – sync on' : '' ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Brand sync
				<select name="brand_sync">
					<option value="">Any</option>
					<option value="on" <?php selected( $f['brand_sync'], 'on' ); ?>>Enabled brands</option>
					<option value="off" <?php selected( $f['brand_sync'], 'off' ); ?>>Disabled brands</option>
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
			<label class="urme-check"><input type="checkbox" name="in_stock" value="1" <?php checked( $f['in_stock'], '1' ); ?>> In stock only</label>
			<label class="urme-check"><input type="checkbox" name="show_missing" value="1" <?php checked( $f['show_missing'], '1' ); ?>> Include items no longer in feed</label>
			<button class="button">Search</button>
			<a class="button-link" href="<?php echo esc_url( self::url() ); ?>">Reset</a>
		</form>

		<div class="urme-catalog-bar">
			<span class="urme-count"><?php echo esc_html( sprintf( '%s watches found', number_format_i18n( $result['total'] ) ) ); ?>
				<?php if ( $rate ) : ?>· SEK at <?php echo esc_html( number_format_i18n( $rate['rate'], 4 ) ); ?><?php endif; ?></span>
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
				<span class="description">Selecting is always manual. A watch that exists in URME is not synced until you select it.</span></div>
			<table class="widefat striped urme-table">
				<thead><tr>
					<td class="check-column"><input type="checkbox" class="urme-check-all" aria-label="Select all"></td>
					<th class="urme-img-col">Image</th>
					<th>Brand</th>
					<th>Product</th>
					<th>Model / SKU<br><small>PRODUCTNO</small></th>
					<th>EAN<br><small>ITEM_ID</small></th>
					<th class="num">Supplier stock</th>
					<th class="num">Cost EUR</th>
					<th class="num">Cost SEK</th>
					<th class="urme-match-col">In URME</th>
					<th>Sync</th>
				</tr></thead>
				<tbody>
				<?php if ( ! $result['rows'] ) : ?>
					<tr><td colspan="11">
						<?php
						echo URME_SS_DB::catalog_counts()['total']
							? 'No watches match your search.'
							: 'The catalog is empty. Click "Sync now" to download the supplier feed (this also runs automatically every hour).';
						?>
					</td></tr>
				<?php endif; ?>
				<?php foreach ( $result['rows'] as $row ) : ?>
					<tr class="<?php echo (int) $row['in_feed'] ? '' : 'urme-missing'; ?>">
						<th class="check-column">
							<?php if ( ! $row['link_id'] ) : ?>
								<input type="checkbox" name="item_keys[]" value="<?php echo esc_attr( $row['item_key'] ); ?>" aria-label="<?php echo esc_attr( 'Select ' . $row['product_no'] ); ?>">
							<?php endif; ?>
						</th>
						<td class="urme-img-col"><?php echo self::img( $row['img_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td><?php echo esc_html( $row['manufacturer'] ); ?></td>
						<td><?php echo esc_html( $row['product_name'] ); ?>
							<?php if ( $row['subcategory'] ) : ?><br><small><?php echo esc_html( $row['subcategory'] ); ?></small><?php endif; ?>
							<?php if ( ! (int) $row['in_feed'] ) : ?><br><span class="urme-bad">Not in feed since <?php echo esc_html( self::mysql_datetime( $row['missing_since'] ) ); ?></span><?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $row['product_no'] ); ?></code></td>
						<td><code><?php echo esc_html( $row['item_id'] ); ?></code></td>
						<td class="num"><?php echo null === $row['stock'] ? '—' : '<span class="' . ( (int) $row['stock'] > 0 ? 'urme-good' : 'urme-bad' ) . '">' . esc_html( $row['stock'] ) . '</span>'; ?></td>
						<td class="num"><?php echo esc_html( self::eur( $row['purchase_price'] ) ); ?></td>
						<td class="num"><?php echo esc_html( self::sek( null === $row['purchase_price'] ? null : URME_SS_Rates::to_sek( $row['purchase_price'] ) ) ); ?></td>
						<td class="urme-match-col"><?php echo self::match_cell( $row ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td>
							<?php
							$brand_on = URME_SS_Settings::brand_enabled( $row['manufacturer'] );
							if ( ! $row['link_id'] ) {
								echo '<span class="urme-muted">Not selected</span>';
							} elseif ( ! (int) $row['product_id'] ) {
								printf( '<span class="urme-bad">Selected, not linked</span><br><a href="%s">Link product</a>', esc_url( self::url( array( 'tab' => 'selected', 'status' => 'unlinked' ) ) ) );
							} elseif ( ! $brand_on ) {
								echo '<span class="urme-muted">Selected – brand sync off</span>';
							} elseif ( ! (int) $row['sync_enabled'] ) {
								echo '<span class="urme-muted">Selected – paused</span>';
							} else {
								echo '<span class="urme-good">Syncing</span>';
							}
							if ( $row['link_id'] && (int) $row['product_id'] && ( 'manual' !== $row['link_method'] ) && (int) $row['product_id'] !== (int) $row['match_product_id'] ) {
								echo '<br><small>→ ' . self::product_link( (int) $row['product_id'] ) . '</small>'; // phpcs:ignore WordPress.Security.EscapeOutput
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</form>
		<?php
		self::pagination( $result['total'], $per_page, $page, array_merge( array( 'page' => self::SLUG, 'tab' => 'catalog' ), array_filter( $f ) ) );
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

	private static function render_selected() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = sanitize_key( $_GET['status'] ?? 'all' );
		$q      = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		$page   = max( 1, absint( $_GET['paged'] ?? 1 ) );
		// phpcs:enable
		$per_page = 50;
		$counts   = URME_SS_DB::link_counts();
		$target   = URME_SS_Store::cost_target();

		$filters = array(
			'all'      => array( 'All', $counts['selected'] ?? 0 ),
			'linked'   => array( 'Linked', $counts['linked'] ?? 0 ),
			'unlinked' => array( 'Not linked', $counts['unlinked'] ?? 0 ),
			'missing'  => array( 'Missing from feed', $counts['missing'] ?? 0 ),
			'paused'   => array( 'Paused', $counts['paused'] ?? 0 ),
			'brand_off' => array( 'Brand sync off', $counts['brand_off'] ?? 0 ),
			'local'    => array( 'Local first', $counts['local_first'] ?? 0 ),
			'errors'   => array( 'Errors', $counts['errors'] ?? 0 ),
		);
		if ( ! isset( $filters[ $status ] ) ) {
			$status = 'all';
		}

		echo '<ul class="subsubsub">';
		$parts = array();
		foreach ( $filters as $id => $info ) {
			$parts[] = sprintf(
				'<li><a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( self::url( array( 'tab' => 'selected', 'status' => $id ) ) ),
				$id === $status ? 'current' : '',
				esc_html( $info[0] ),
				(int) $info[1]
			);
		}
		echo implode( ' | </li>', $parts ) . '</li></ul>'; // phpcs:ignore WordPress.Security.EscapeOutput

		?>
		<form method="get" class="urme-filters urme-filters-right">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
			<input type="hidden" name="tab" value="selected">
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
			<input type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="Brand, model, EAN…">
			<button class="button">Search</button>
			<?php
			if ( ! empty( $counts['unlinked'] ) ) {
				echo self::action_button( 'automatch', 'Auto-link all unlinked by SKU/EAN' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</form>
		<br class="clear">
		<?php

		$result = URME_SS_DB::get_links(
			array(
				'status'   => $status,
				'q'        => $q,
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
		?>
		<table class="widefat striped urme-table urme-selected">
			<thead><tr>
				<th class="urme-img-col">Image</th>
				<th>Supplier watch</th>
				<th class="num">Supplier stock</th>
				<th class="num">Supplier cost</th>
				<th>WooCommerce product</th>
				<th class="urme-mode-col">Mode</th>
				<th>Last sync</th>
				<th>Actions</th>
			</tr></thead>
			<tbody>
			<?php if ( ! $result['rows'] ) : ?>
				<tr><td colspan="8">Nothing here. Select watches in the <a href="<?php echo esc_url( self::url() ); ?>">Supplier catalog</a>.</td></tr>
			<?php endif; ?>
			<?php
			foreach ( $result['rows'] as $row ) {
				self::render_selected_row( $row, $target );
			}
			?>
			</tbody>
		</table>
		<?php
		self::pagination( $result['total'], $per_page, $page, array_filter( array( 'page' => self::SLUG, 'tab' => 'selected', 'status' => $status, 'q' => $q ) ) );
	}

	private static function render_selected_row( array $row, array $target ) {
		$pid     = (int) $row['product_id'];
		$product = $pid ? wc_get_product( $pid ) : null;
		$in_feed = ! empty( $row['catalog_id'] ) && (int) $row['in_feed'];
		?>
		<tr class="<?php echo $in_feed ? '' : 'urme-missing'; ?>">
			<td class="urme-img-col"><?php echo self::img( $row['img_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			<td>
				<strong><?php echo esc_html( $row['manufacturer'] ); ?></strong> <?php echo esc_html( $row['product_name'] ); ?><br>
				Model <code><?php echo esc_html( $row['product_no'] ); ?></code> · EAN <code><?php echo esc_html( $row['item_id'] ); ?></code>
				<?php if ( ! $in_feed ) : ?>
					<br><span class="urme-bad">Not in supplier feed<?php echo $row['missing_since'] ? ' since ' . esc_html( self::mysql_datetime( $row['missing_since'] ) ) : ''; ?> – product left unchanged</span>
				<?php endif; ?>
			</td>
			<td class="num"><?php echo null === $row['stock'] ? '—' : esc_html( $row['stock'] ); ?></td>
			<td class="num"><?php echo esc_html( self::eur( $row['purchase_price'] ) ); ?><br><small><?php echo esc_html( self::sek( null === $row['purchase_price'] ? null : URME_SS_Rates::to_sek( $row['purchase_price'] ) ) ); ?></small></td>
			<td class="urme-product-col">
				<?php if ( $product ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( self::edit_id( $pid ) ) ?? '' ); ?>"><strong><?php echo esc_html( $product->get_name() ); ?></strong></a>
					<br>SKU <code><?php echo esc_html( $product->get_sku() ?: '—' ); ?></code>
					<?php if ( $row['match_method'] ) : ?><span class="urme-muted">(<?php echo esc_html( 'manual' === $row['match_method'] ? 'linked manually' : 'matched by ' . strtoupper( str_replace( '+', ' + ', $row['match_method'] ) ) ); ?>)</span><?php endif; ?>
					<br>Stock: <?php echo esc_html( true === $product->get_manage_stock() ? (string) $product->get_stock_quantity() : 'not managed' ); ?> (<?php echo esc_html( wc_get_product_stock_status_options()[ $product->get_stock_status() ] ?? $product->get_stock_status() ); ?>)
					<?php if ( $target['type'] ) : ?>
						· Cost: <?php echo esc_html( self::sek( URME_SS_Store::get_cost( $product, $target ) ) ); ?>
					<?php endif; ?>
				<?php elseif ( $pid ) : ?>
					<span class="urme-bad">Linked product #<?php echo (int) $pid; ?> no longer exists.</span>
				<?php else : ?>
					<span class="urme-bad">Not linked</span>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="urme-link-form">
					<?php echo self::hidden_fields( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<input type="hidden" name="link_id" value="<?php echo (int) $row['id']; ?>">
					<select class="wc-product-search" name="product_id" data-placeholder="<?php echo esc_attr( $pid ? 'Change linked product…' : 'Search product by name or SKU…' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true" style="width: 100%;"></select>
					<button type="submit" class="button button-small">Link</button>
				</form>
			</td>
			<td class="urme-mode-col"><?php self::render_mode_cell( $row, $product, $target ); ?></td>
			<td>
				<?php
				$labels = array(
					'local'    => '<span class="urme-muted">Local first</span>',
					'ok'       => '<span class="urme-good">OK</span>',
					'error'    => '<span class="urme-bad">Error</span>',
					'missing'  => '<span class="urme-bad">Missing from feed</span>',
					'unlinked' => '<span class="urme-bad">Not linked</span>',
				);
				if ( ! URME_SS_Settings::brand_enabled( (string) $row['manufacturer'] ) ) {
					echo self::status_badge( 'off', 'Brand sync off' ) . '<br><small>' . esc_html( sprintf( 'Enable %s in Settings to sync. Link kept.', $row['manufacturer'] ) ) . '</small><br>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo wp_kses_post( $labels[ $row['last_status'] ] ?? '<span class="urme-muted">Waiting for next sync</span>' );
				if ( ! (int) $row['sync_enabled'] ) {
					echo ' <span class="urme-muted">(paused)</span>';
				}
				if ( $row['last_message'] ) {
					echo '<br><small>' . esc_html( $row['last_message'] ) . '</small>';
				}
				if ( $row['last_synced_at'] ) {
					echo '<br><small>Last change ' . esc_html( self::mysql_datetime( $row['last_synced_at'] ) ) . '</small>';
				}
				if ( null !== $row['last_cost_sek'] ) {
					echo '<br><small>' . esc_html( sprintf( '%s × %s = %s', self::eur( $row['last_cost_eur'] ), number_format_i18n( (float) $row['last_rate'], 4 ), self::sek( $row['last_cost_sek'] ) ) ) . '</small>';
				}
				?>
			</td>
			<td class="urme-actions">
				<?php
				// phpcs:disable WordPress.Security.EscapeOutput
				$locked = URME_SS_Inventory::has_local_units( $row );
				if ( $pid ) {
					echo self::action_button( 'sync_one', 'Sync now', array( 'link_id' => $row['id'] ), 'button button-small' );
					if ( ! $locked ) {
						echo self::action_button( 'unlink', 'Unlink', array( 'link_id' => $row['id'] ), 'button-link' );
					}
				} else {
					echo self::action_button( 'automatch', 'Auto-link', array( 'link_id' => $row['id'] ), 'button button-small' );
				}
				if ( ! $locked ) {
					echo self::action_button( 'unselect', 'Remove', array( 'link_id' => $row['id'] ), 'button-link urme-danger', 'Stop syncing this watch? The WooCommerce product itself is not changed.' );
				} else {
					echo '<small class="urme-muted">Unlink/Remove are disabled while local units are tracked.</small>';
				}
				// phpcs:enable
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Mode column: Local first / Supplier now / Paused, with details and the switcher.
	 */
	private static function render_mode_cell( array $row, $product, array $target ) {
		$local  = URME_SS_Inventory::LOCAL === $row['stock_mode'];
		$paused = ! (int) $row['sync_enabled'];
		$sek    = null === $row['purchase_price'] ? null : URME_SS_Rates::to_sek( $row['purchase_price'] );

		if ( $paused ) {
			echo self::status_badge( 'off', 'Paused' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<br><small>' . esc_html( $local ? sprintf( 'Local first state kept (%d local unit(s)).', $row['local_qty'] ) : 'Nothing is synced.' ) . '</small>';
		} elseif ( $local ) {
			echo self::status_badge( 'local', 'Local first' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<ul class="urme-mode-facts">';
			printf( '<li>Local stock remaining: <strong>%d</strong></li>', (int) $row['local_qty'] );
			printf( '<li>Supplier stock: %s</li>', null === $row['stock'] ? '—' : (int) $row['stock'] );
			printf( '<li>Supplier cost: %s · %s</li>', esc_html( self::eur( $row['purchase_price'] ) ), esc_html( self::sek( $sek ) ) );
			if ( null !== $row['local_cost'] ) {
				printf( '<li>Local cost: %s</li>', esc_html( self::sek( $row['local_cost'] ) ) );
			}
			echo '</ul><small>';
			if ( $product && 'no' !== $product->get_backorders() ) {
				echo '<span class="urme-bad">On hold: backorders are allowed on this product.</span>';
			} elseif ( (int) $row['local_qty'] > 0 ) {
				echo esc_html( 'Waiting for local stock to sell.' );
			} else {
				echo esc_html( 'Local stock sold – switching to supplier stock on the next safe sync.' );
			}
			if ( $product && true === $product->get_manage_stock() && (int) $product->get_stock_quantity() !== (int) $row['local_qty'] ) {
				printf( '<br><span class="urme-bad">WooCommerce stock is %d, local stock is %d. Correct it by applying Local first again with the right quantity.</span>', (int) $product->get_stock_quantity(), (int) $row['local_qty'] );
			}
			echo '</small>';
		} else {
			echo self::status_badge( 'exists', 'Supplier now' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<ul class="urme-mode-facts"><li>Supplier stock sync active</li><li>' . esc_html( $target['type'] ? 'Supplier cost sync active' : 'Supplier cost sync: no cost field' ) . '</li></ul>';
		}
		if ( $row['mode_note'] ) {
			echo '<br><small class="urme-muted">' . esc_html( $row['mode_note'] . ( $row['mode_changed_at'] ? ' (' . self::mysql_datetime( $row['mode_changed_at'] ) . ')' : '' ) ) . '</small>';
		}
		if ( ! (int) $row['product_id'] ) {
			return;
		}

		$current   = $paused ? 'paused' : ( $local ? 'local' : 'supplier' );
		$def_qty   = $local ? max( 1, (int) $row['local_qty'] ) : max( 1, (int) ( $product ? $product->get_stock_quantity() : 1 ) );
		$def_cost  = null !== $row['local_cost'] ? $row['local_cost'] : ( ( $product && $target['type'] ) ? URME_SS_Store::get_cost( $product, $target ) : null );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="urme-mode-form" data-local-units="<?php echo (int) ( $local ? $row['local_qty'] : 0 ); ?>">
			<?php echo self::hidden_fields( 'set_mode' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<input type="hidden" name="link_id" value="<?php echo (int) $row['id']; ?>">
			<input type="hidden" name="confirm_drop" value="">
			<select name="mode" aria-label="Sync mode">
				<option value="local" <?php selected( $current, 'local' ); ?>>Local first</option>
				<option value="supplier" <?php selected( $current, 'supplier' ); ?>>Supplier now</option>
				<option value="paused" <?php selected( $current, 'paused' ); ?>>Paused</option>
			</select>
			<span class="urme-local-fields">
				<label>Local units <input type="number" name="local_qty" min="1" step="1" value="<?php echo (int) $def_qty; ?>" class="small-text"></label>
				<label>Local cost SEK <input type="text" name="local_cost" value="<?php echo esc_attr( null === $def_cost ? '' : wc_format_decimal( $def_cost, 2 ) ); ?>" class="small-text" inputmode="decimal"></label>
			</span>
			<button type="submit" class="button button-small">Apply</button>
		</form>
		<?php
	}

	/* --- Price Review tab ----------------------------------------------- */

	private static function render_reviews() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$show = 'all' === sanitize_key( $_GET['show'] ?? '' ) ? 'all' : URME_SS_Price_Review::PENDING;
		$rows = URME_SS_Price_Review::rows( $show );
		printf(
			'<p>Watches that switched automatically from Local first to Dropshipping. Check the selling price; the plugin never changes it. <a href="%s">%s</a></p>',
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
				<tr><th>Paused</th><td><?php echo esc_html( $sync['paused'] ?? 0 ); ?></td></tr>
				<tr><th>In disabled brands (not processed)</th><td><?php echo esc_html( $sync['brand_off'] ?? 0 ); ?></td></tr>
				<tr><th>Local first: waiting for local stock to sell</th><td><?php echo esc_html( $sync['local_waiting'] ?? 0 ); ?></td></tr>
				<tr><th>Local first → Supplier switches</th><td><?php echo esc_html( $sync['handovers'] ?? 0 ); ?></td></tr>
				<tr class="<?php echo empty( $sync['local_blocked'] ) ? '' : 'urme-error-row'; ?>"><th>Local first on hold (backorders allowed)</th><td><?php echo esc_html( $sync['local_blocked'] ?? 0 ); ?></td></tr>
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
			<?php submit_button( 'Save settings' ); ?>
		</form>
		<?php
	}
}
