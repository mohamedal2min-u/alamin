<?php
/**
 * Delivery days per supplier, shown by WoodMart's "Estimated delivery".
 *
 * The plugin keeps one WoodMart delivery rule per supplier (post type wd_woo_est_del), with the
 * days from Settings > Suppliers and a high priority. Each rule is active only for Dropshipping
 * watches bought from its supplier now; every other watch falls through to the shop's own rules
 * (e.g. URME Lager 2–4 days). Customers only see the days, never the supplier.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Delivery {

	const OPTION    = 'urme_ss_delivery_rules'; // supplier => rule post ID.
	const STATE     = 'urme_ss_delivery_state'; // What the rules were last written with.
	const POST_TYPE = 'wd_woo_est_del';
	const PRIORITY  = 10; // WoodMart: the highest priority wins; the shop's own rules use 1–2.

	public static function init() {
		add_filter( 'woodmart_check_estimate_delivery_condition', array( __CLASS__, 'condition' ), 20, 3 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_sync' ) );
	}

	/**
	 * WoodMart asks whether a rule applies to a product: ours only to its supplier's watches.
	 */
	public static function condition( $active, $rule, $product ) {
		$rule_id = (int) ( $rule['key'] ?? 0 );
		$ours    = array_search( $rule_id, self::rules(), true );
		if ( false === $ours || ! $active ) {
			return $active;
		}
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		return $ours === URME_SS_Product_Source::supplier_of( $id );
	}

	/**
	 * @return array<string,int> Supplier => rule post ID.
	 */
	public static function rules() {
		return array_map( 'intval', (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Admin: write the rules when the days changed (or a rule was deleted). Cheap when nothing changed.
	 */
	public static function maybe_sync() {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return; // WoodMart is not active.
		}
		$want = self::wanted();
		if ( get_option( self::STATE ) === $want && self::rules_exist() ) {
			return;
		}
		self::sync( $want );
	}

	/**
	 * Plugin deactivated: without the filter above, the rules would apply to every watch, so they
	 * are set to draft. maybe_sync() publishes them again after reactivation.
	 */
	public static function unpublish() {
		foreach ( self::rules() as $rule_id ) {
			if ( $rule_id && self::POST_TYPE === get_post_type( $rule_id ) ) {
				wp_update_post(
					array(
						'ID'          => $rule_id,
						'post_status' => 'draft',
					)
				);
			}
		}
		delete_transient( 'wd_transient_est_del_ids' );
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
	}

	private static function wanted() {
		$out = array();
		foreach ( URME_SS_Suppliers::ids() as $id ) {
			$out[ $id ] = URME_SS_Suppliers::days( $id );
		}
		return $out;
	}

	private static function rules_exist() {
		$rules = self::rules();
		foreach ( URME_SS_Suppliers::ids() as $id ) {
			$post = empty( $rules[ $id ] ) ? null : get_post( $rules[ $id ] );
			if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
				return false;
			}
		}
		return true;
	}

	private static function sync( array $want ) {
		$rules = self::rules();
		foreach ( $want as $id => $days ) {
			$s     = URME_SS_Suppliers::get( $id );
			$title = sprintf( 'URME Supplier Sync – %s (%s) %d–%d', $s['name'], $s['country'], $days[0], $days[1] );
			$post  = empty( $rules[ $id ] ) ? null : get_post( $rules[ $id ] );
			if ( ! $post || self::POST_TYPE !== $post->post_type ) {
				$post_id = wp_insert_post(
					array(
						'post_type'   => self::POST_TYPE,
						'post_status' => 'publish',
						'post_title'  => $title,
					),
					true
				);
				if ( is_wp_error( $post_id ) ) {
					URME_SS_Log::error( 'Delivery days: the WoodMart rule for ' . $s['name'] . ' could not be created: ' . $post_id->get_error_message() );
					continue;
				}
			} else {
				$post_id = (int) $post->ID;
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'publish',
						'post_title'  => $title,
					)
				);
			}
			update_post_meta( $post_id, 'est_del_day_min', (string) $days[0] );
			update_post_meta( $post_id, 'est_del_day_max', (string) $days[1] );
			update_post_meta( $post_id, 'est_del_priority', (string) self::PRIORITY );
			update_post_meta( $post_id, 'est_del_condition', array( array( 'comparison' => 'include', 'type' => 'all' ) ) );
			add_post_meta( $post_id, 'est_del_daily_deadline', '23:59', true ); // The admin may change these in WoodMart.
			add_post_meta( $post_id, 'est_del_skipped_date', array( '' ), true );
			add_post_meta( $post_id, 'est_del_shipping_method', array( '' ), true );
			add_post_meta( $post_id, 'est_del_exclusion_dates', array(), true );
			delete_transient( 'wd_transient_est_del_rule_' . $post_id );
			$rules[ $id ] = (int) $post_id;
		}
		delete_transient( 'wd_transient_est_del_ids' );
		update_option( self::OPTION, $rules, true );
		update_option( self::STATE, $want, false );
		URME_SS_Log::info(
			'Delivery days: ' . implode(
				', ',
				array_map(
					static function ( $id ) use ( $want ) {
						return sprintf( '%s %d–%d', URME_SS_Suppliers::name( $id ), $want[ $id ][0], $want[ $id ][1] );
					},
					array_keys( $want )
				)
			) . '.'
		);
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain(); // Product pages show the days.
		}
	}
}
