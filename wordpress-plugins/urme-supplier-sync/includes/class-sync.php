<?php
/**
 * Runs feed refreshes and product syncs, one at a time, with safety checks.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Sync {

	const STATUS_OPTION = 'urme_ss_status';
	const LOCK_OPTION   = 'urme_ss_lock';
	const LOCK_TTL      = 30 * MINUTE_IN_SECONDS;

	public static function status() {
		$s = get_option( self::STATUS_OPTION, array() );
		$s = is_array( $s ) ? $s : array();
		return array(
			'feed' => isset( $s['feed'] ) && is_array( $s['feed'] ) ? $s['feed'] : array(),
			'sync' => isset( $s['sync'] ) && is_array( $s['sync'] ) ? $s['sync'] : array(),
		);
	}

	private static function save_status( $part, array $data ) {
		$s          = self::status();
		$s[ $part ] = $data;
		update_option( self::STATUS_OPTION, $s, false );
	}

	/* ---------------------------------------------------------------------
	 * Lock: an atomic INSERT, so two runs can never overlap.
	 * ------------------------------------------------------------------- */

	private static function acquire_lock() {
		global $wpdb;
		// phpcs:disable WordPress.DB
		$sql = $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::LOCK_OPTION, (string) time() );
		if ( $wpdb->query( $sql ) ) {
			return true;
		}
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION ) );
		if ( $since && ( time() - $since ) > self::LOCK_TTL ) {
			// A previous run died without releasing the lock.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK_OPTION, (string) $since ) );
			return (bool) $wpdb->query( $sql );
		}
		// phpcs:enable
		return false;
	}

	private static function release_lock() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION ) ); // phpcs:ignore WordPress.DB
		wp_cache_delete( self::LOCK_OPTION, 'options' );
	}

	public static function is_running() {
		global $wpdb;
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION ) ); // phpcs:ignore WordPress.DB
		return $since && ( time() - $since ) <= self::LOCK_TTL;
	}

	/* ---------------------------------------------------------------------
	 * Entry points
	 * ------------------------------------------------------------------- */

	/**
	 * Hourly cron job.
	 */
	public static function cron() {
		if ( ! URME_SS_Settings::get( 'auto_sync' ) ) {
			return;
		}
		self::run( array( 'trigger' => 'cron' ) );
	}

	/**
	 * Run refresh and/or sync under the lock.
	 *
	 * @param array $args refresh_feed (bool), refresh_matches (bool), sync_products (bool), force_feed (bool), link_id (int), trigger (string).
	 * @return array{ran: bool, feed_ok: bool|null, sync: array|null, message: string}
	 */
	public static function run( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'refresh_feed'    => true,
				'refresh_matches' => null, // Defaults to refresh_feed.
				'sync_products'   => true,
				'force_feed'    => false,
				'link_id'       => 0,
				'trigger'       => 'manual',
			)
		);

		if ( ! self::acquire_lock() ) {
			return array(
				'ran'     => false,
				'feed_ok' => null,
				'sync'    => null,
				'message' => 'Another sync is already running. Try again in a minute.',
			);
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		wp_raise_memory_limit( 'admin' );

		$feed_ok = null;
		$sync    = null;
		try {
			URME_SS_Rates::maybe_refresh();
			if ( $args['refresh_feed'] ) {
				$feed_ok = self::refresh_feed( $args['force_feed'] );
			}
			if ( null === $args['refresh_matches'] ? $args['refresh_feed'] : $args['refresh_matches'] ) {
				// Store products change too (new SKUs/EANs), so re-check even when the feed did not.
				$m = URME_SS_Matcher::refresh_catalog();
				if ( $m['changed'] ) {
					URME_SS_Log::info( sprintf( 'URME match status updated for %d watches (%d exist in URME, %d not in URME, %d need review).', $m['changed'], $m['exists'], $m['none'], $m['review'] ) );
				}
			}
			if ( $args['sync_products'] ) {
				// A feed that failed in this run blocks all product writes; the cached catalog is kept as is.
				$sync = self::sync_products( (int) $args['link_id'], $args['trigger'], false === $feed_ok );
			}
		} catch ( Throwable $e ) {
			URME_SS_Log::error( 'Unexpected error: ' . $e->getMessage() );
		} finally {
			self::release_lock();
		}

		return array(
			'ran'     => true,
			'feed_ok' => $feed_ok,
			'sync'    => $sync,
			'message' => '',
		);
	}

	/* ---------------------------------------------------------------------
	 * Feed refresh
	 * ------------------------------------------------------------------- */

	/**
	 * Download + parse + validate + store the catalog. On any failure the
	 * catalog is left exactly as it was.
	 *
	 * @param bool $force Skip the conditional request and the size-drop safety check (admin "accept anyway").
	 */
	private static function refresh_feed( $force = false ) {
		$st                 = self::status()['feed'];
		$st['last_attempt'] = time();
		$started            = microtime( true );
		$state              = get_option( URME_SS_Feed::STATE_OPTION, array() );
		$categories         = URME_SS_Settings::get( 'categories' );
		$cat_sig            = implode( ',', $categories );
		$same_categories    = ( $state['categories'] ?? '' ) === $cat_sig;
		$file               = null;

		try {
			$dl = URME_SS_Feed::download( ! $force && $same_categories );

			if ( $dl['not_modified'] ) {
				$st['last_success'] = time();
				$st['last_result']  = 'Feed unchanged since last download (HTTP 304).';
				$st['last_error']   = '';
				return true;
			}

			$file = $dl['file'];
			$md5  = md5_file( $file );
			if ( ! $force && $same_categories && ! empty( $state['md5'] ) && $state['md5'] === $md5 ) {
				$st['last_success'] = time();
				$st['last_result']  = 'Feed content unchanged since last download.';
				$st['last_error']   = '';
				return true;
			}

			$parsed = URME_SS_Feed::parse( $file, $categories );

			if ( 0 === $parsed['kept'] ) {
				throw new Exception(
					sprintf(
						'Feed was read (%d products) but none are in category %s. Categories seen: %s. Nothing was changed.',
						$parsed['total'],
						$cat_sig,
						implode( ', ', array_slice( array_keys( $parsed['categories'] ), 0, 10 ) )
					)
				);
			}

			$previous = (int) ( $st['watches_found'] ?? 0 );
			$ratio    = (int) URME_SS_Settings::get( 'min_feed_ratio' );
			if ( ! $force && $same_categories && $previous >= 20 && $ratio > 0 && $parsed['kept'] < $previous * $ratio / 100 ) {
				throw new Exception(
					sprintf(
						'Feed has only %d items, down from %d last time (more than %d%% drop). Treated as a feed problem: nothing was changed. If this is expected, use "Accept current feed".',
						$parsed['kept'],
						$previous,
						100 - $ratio
					)
				);
			}

			$first_import = URME_SS_DB::catalog_is_empty();
			$applied      = URME_SS_DB::apply_feed( $parsed['items'], $force );
			if ( $first_import ) {
				// Everything in a first import is the existing supplier range, not "new".
				update_option( 'urme_ss_new_since', current_time( 'mysql', true ), false );
			}
			$kept    = $parsed['kept'];
			unset( $parsed['items'] );

			update_option(
				URME_SS_Feed::STATE_OPTION,
				array(
					'etag'          => $dl['etag'],
					'last_modified' => $dl['last_modified'],
					'md5'           => $md5,
					'categories'    => $cat_sig,
				),
				false
			);

			$st = array_merge(
				$st,
				array(
					'last_success'    => time(),
					'last_changed'    => time(),
					'last_error'      => '',
					'total_items'     => $parsed['total'],
					'watches_found'   => $kept,
					'skipped_invalid' => $parsed['skipped_invalid'],
					'duplicates'      => $parsed['duplicates'],
					'new'             => $applied['new'],
					'changed'         => $applied['changed'],
					'returned'        => $applied['returned'],
					'went_missing'    => $applied['missing'],
					'bytes'           => $dl['bytes'],
					'duration'        => round( microtime( true ) - $started, 1 ),
					'peak_memory_mb'  => round( memory_get_peak_usage( true ) / 1048576, 1 ),
					'categories'      => array_slice( $parsed['categories'], 0, 15, true ),
				)
			);
			$st['last_result'] = sprintf( '%d items kept of %d in feed: %d new, %d changed, %d back in feed, %d no longer in feed.', $kept, $parsed['total'], $applied['new'], $applied['changed'], $applied['returned'], $applied['missing'] );
			URME_SS_Log::info( 'Feed refreshed. ' . $st['last_result'] );
			return true;
		} catch ( Exception $e ) {
			$st['last_error']    = $e->getMessage();
			$st['last_error_at'] = time();
			URME_SS_Log::error( $e->getMessage() );
			return false;
		} finally {
			if ( $file && file_exists( $file ) ) {
				wp_delete_file( $file );
			}
			self::save_status( 'feed', $st );
		}
	}

	/* ---------------------------------------------------------------------
	 * Product sync
	 * ------------------------------------------------------------------- */

	/**
	 * Push supplier stock and cost to the linked, enabled products.
	 *
	 * @param int    $only_link_id Limit to one link (0 = all).
	 * @param string $trigger      What started the run.
	 * @param bool   $feed_failed  The feed refresh requested in this run failed.
	 */
	private static function sync_products( $only_link_id = 0, $trigger = 'manual', $feed_failed = false ) {
		$feed  = self::status()['feed'];
		$prev  = self::status()['sync'];
		$stats = array(
			'last_run'      => time(),
			'trigger'       => $trigger,
			'selected'      => 0,
			'linked'        => 0,
			'checked'       => 0,
			'stock_updated' => 0,
			'cost_updated'  => 0,
			'unchanged'     => 0,
			'unmatched'     => 0,
			'missing'       => 0,
			'paused'        => 0,
			'brand_off'     => 0,
			'errors'        => 0,
			'error_list'    => array(),
			'skipped'       => '',
		);

		// Safety: never push data from a catalog we could not refresh recently.
		$max_age = (int) URME_SS_Settings::get( 'max_feed_age_hours' ) * HOUR_IN_SECONDS;
		if ( $feed_failed ) {
			$stats['skipped'] = 'The supplier feed could not be downloaded or read in this run, so no products were changed. The previous catalog is kept for browsing.';
		} elseif ( empty( $feed['last_success'] ) ) {
			$stats['skipped'] = 'No successful feed download yet, so no products were changed.';
		} elseif ( time() - (int) $feed['last_success'] > $max_age ) {
			$stats['skipped'] = sprintf( 'The supplier feed has not been refreshed successfully for %s, so no products were changed.', human_time_diff( (int) $feed['last_success'] ) );
		}
		if ( $stats['skipped'] ) {
			URME_SS_Log::warning( $stats['skipped'] );
			if ( ! $only_link_id ) {
				self::save_status( 'sync', $stats );
			}
			return $stats;
		}

		$rate   = URME_SS_Rates::current();
		$target = URME_SS_Store::cost_target();
		$manage = (bool) URME_SS_Settings::get( 'manage_stock' );
		$now    = current_time( 'mysql', true );
		$notes  = array();

		if ( $only_link_id ) {
			$links = array_filter(
				URME_SS_DB::get_links()['rows'],
				static function ( $l ) use ( $only_link_id ) {
					return (int) $l['id'] === $only_link_id;
				}
			);
			$stats['selected'] = count( $links );
		} else {
			// Links of disabled brands are filtered out in SQL and never loaded; they are only counted.
			$counts             = URME_SS_DB::link_counts();
			$stats['selected']  = (int) ( $counts['selected'] ?? 0 );
			$stats['brand_off'] = (int) ( $counts['brand_off'] ?? 0 );
			$links              = URME_SS_Settings::enabled_brand_keys()
				? URME_SS_DB::get_links( array( 'enabled_brands_only' => true ) )['rows']
				: array();
			if ( $stats['selected'] && ! URME_SS_Settings::enabled_brand_keys() ) {
				URME_SS_Log::warning( 'No supplier brands are enabled for sync, so no products were updated. Enable brands under Supplier Sync > Settings.' );
			}
		}

		$categories = (array) URME_SS_Settings::get( 'categories' );
		foreach ( $links as $link ) {
			// Full runs never load these (filtered in SQL); this guards single-product syncs.
			if ( ! empty( $link['catalog_id'] ) && ! in_array( strtoupper( (string) $link['category'] ), $categories, true ) ) {
				$stats['skipped'] = sprintf( 'Category "%s" is not enabled for sync.', $link['category'] );
				continue;
			}
			if ( ! URME_SS_Settings::brand_enabled( (string) $link['manufacturer'] ) ) {
				++$stats['brand_off'];
				$stats['skipped'] = sprintf( 'Brand "%s" is not enabled for sync. Enable it in Settings to sync this watch.', $link['manufacturer'] );
				continue;
			}

			if ( ! (int) $link['sync_enabled'] ) {
				++$stats['paused'];
				continue;
			}
			if ( ! (int) $link['product_id'] ) {
				++$stats['unmatched'];
				self::set_link_status( $link, 'unlinked', 'Not linked to a product yet.' );
				continue;
			}
			++$stats['linked'];
			if ( empty( $link['catalog_id'] ) || ! (int) $link['in_feed'] ) {
				// Temporarily (or permanently) gone from the feed: leave the product alone.
				++$stats['missing'];
				self::set_link_status( $link, 'missing', 'Not in the supplier feed; product left unchanged.' );
				continue;
			}

			$product = wc_get_product( (int) $link['product_id'] );
			if ( ! $product || 'trash' === $product->get_status() ) {
				++$stats['errors'];
				$msg                   = sprintf( '%s: linked product #%d no longer exists.', $link['product_no'], $link['product_id'] );
				$stats['error_list'][] = $msg;
				self::set_link_status( $link, 'error', 'Linked product no longer exists.' );
				continue;
			}

			if ( URME_SS_Inventory::LOCAL === $link['stock_mode'] ) {
				// An old "Local first" link (normally removed on update): URME Lager, never written.
				++$stats['paused'];
				self::set_link_status( $link, 'local', 'URME Lager (link from an older version): not synced.' );
				continue;
			}

			++$stats['checked'];
			try {
				$changes    = array();
				$messages   = array();
				$cost_error = '';

				if ( null !== $link['stock'] ) {
					$change = URME_SS_Store::apply_stock( $product, (int) $link['stock'], $manage );
					if ( $change ) {
						++$stats['stock_updated'];
						$changes[] = $change;
					}
				} else {
					$messages[] = 'Supplier stock unreadable; stock not changed.';
				}

				$sek = null;
				if ( null !== $link['purchase_price'] ) {
					URME_SS_Store::apply_eur_reference( $product, (float) $link['purchase_price'] );
					if ( $rate ) {
						$sek = round( (float) $link['purchase_price'] * $rate['rate'], 2 );
					}
					if ( null === $sek ) {
						$messages[] = 'No EUR/SEK rate yet; cost not changed.';
					} elseif ( ! $target['type'] ) {
						$messages[] = 'No cost field configured; cost not changed.';
					} else {
						try {
							$change = URME_SS_Store::apply_cost( $product, $target, $sek );
							if ( $change ) {
								++$stats['cost_updated'];
								$changes[] = $change;
							}
						} catch ( Exception $e ) {
							$cost_error = $e->getMessage();
						}
					}
				} else {
					$messages[] = 'Supplier price missing; cost not changed.';
				}

				if ( $cost_error ) {
					++$stats['errors'];
					$stats['error_list'][] = sprintf( '%s (#%d): %s', $link['product_no'], $link['product_id'], $cost_error );
					array_unshift( $messages, 'Cost not synced: ' . $cost_error );
				}

				if ( ! $changes ) {
					++$stats['unchanged'];
				} else {
					$notes[] = sprintf( '#%d %s: %s', $product->get_id(), $link['product_no'], implode( ', ', $changes ) );
				}

				$data = array(
					'last_stock'    => null === $link['stock'] ? null : (int) $link['stock'],
					'last_cost_eur' => $link['purchase_price'],
					'last_cost_sek' => $sek,
					'last_rate'     => $rate ? $rate['rate'] : null,
					'last_status'   => $cost_error ? 'error' : 'ok',
					'last_message'  => substr( implode( ' ', $messages ), 0, 255 ),
				);
				if ( $changes || ! $cost_error ) {
					$data['last_synced_at'] = $now; // Checked against the supplier, also when nothing had to change.
				}
				self::update_link_if_changed( $link, $data );
			} catch ( Throwable $e ) {
				++$stats['errors'];
				$stats['error_list'][] = sprintf( '%s (#%d): %s', $link['product_no'], $link['product_id'], $e->getMessage() );
				self::set_link_status( $link, 'error', $e->getMessage() );
			}
		}

		foreach ( array_slice( $notes, 0, 50 ) as $note ) {
			URME_SS_Log::info( 'Updated ' . $note );
		}
		if ( count( $notes ) > 50 ) {
			URME_SS_Log::info( sprintf( '…and %d more products updated.', count( $notes ) - 50 ) );
		}
		foreach ( $stats['error_list'] as $err ) {
			URME_SS_Log::error( $err );
		}
		$stats['error_list'] = array_slice( $stats['error_list'], 0, 25 );

		if ( $only_link_id ) {
			// A single-product sync must not overwrite the store-wide numbers.
			return $stats;
		}
		// Quiet hours stay out of the log; the Status tab always shows the latest numbers.
		$eventful = $stats['stock_updated'] || $stats['cost_updated'] || $stats['errors'] || 'cron' !== $trigger;
		if ( $eventful ) {
			URME_SS_Log::info(
				sprintf(
					'Product sync: %d selected (%d in disabled brands, skipped), %d checked, %d stock updates, %d cost updates, %d unchanged, %d unlinked, %d missing from feed, %d errors.',
					$stats['selected'],
					$stats['brand_off'],
					$stats['checked'],
					$stats['stock_updated'],
					$stats['cost_updated'],
					$stats['unchanged'],
					$stats['unmatched'],
					$stats['missing'],
					$stats['errors']
				)
			);
		}
		self::save_status( 'sync', $stats );
		return $stats;
	}

	private static function set_link_status( array $link, $status, $message ) {
		self::update_link_if_changed(
			$link,
			array(
				'last_status'  => $status,
				'last_message' => substr( $message, 0, 255 ),
			)
		);
	}

	/**
	 * Only write the link row when a value actually changed.
	 */
	private static function update_link_if_changed( array $link, array $data ) {
		$diff = array();
		foreach ( $data as $key => $value ) {
			$old = $link[ $key ] ?? null;
			if ( null === $value || null === $old ) {
				$changed = $value !== $old;
			} elseif ( is_numeric( $value ) && is_numeric( $old ) ) {
				// DECIMAL columns come back as "123.4500".
				$changed = abs( (float) $old - (float) $value ) > 0.00001;
			} else {
				$changed = (string) $old !== (string) $value;
			}
			if ( $changed ) {
				$diff[ $key ] = $value;
			}
		}
		if ( $diff ) {
			URME_SS_DB::update_link( (int) $link['id'], $diff );
		}
	}
}
