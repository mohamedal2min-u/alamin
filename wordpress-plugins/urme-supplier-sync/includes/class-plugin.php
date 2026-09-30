<?php
/**
 * Bootstrap: activation, cron scheduling, compatibility declarations.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Plugin {

	const CRON_HOOK = 'urme_ss_hourly';

	public static function init() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_compatibility' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'loaded' ) );
		add_action( self::CRON_HOOK, array( 'URME_SS_Sync', 'cron' ) );
		// Order stock movements (storefront, REST and admin) feed the local-inventory ledger.
		URME_SS_Inventory::init();
	}

	public static function loaded() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>URME Supplier Sync needs WooCommerce to be active.</p></div>';
				}
			);
			return;
		}
		URME_SS_DB::maybe_upgrade();
		if ( get_option( 'urme_ss_schema_error' ) ) {
			add_action(
				'admin_notices',
				static function () {
					printf( '<div class="notice notice-error"><p>URME Supplier Sync: the database upgrade to 1.1.0 is incomplete (missing %s). Local first is unavailable; the upgrade is retried automatically. Check WooCommerce > Status > Logs (urme-supplier-sync).</p></div>', esc_html( get_option( 'urme_ss_schema_error' ) ) );
				}
			);
		}
		if ( is_admin() ) {
			URME_SS_Admin::init();
			// Admin-only "URME Lager / Dropshipping" labels on orders; never registered on the front end or REST.
			URME_SS_Fulfillment::init();
			// Admin-only "Price review required" notices.
			URME_SS_Price_Review::init();
			// Admin-only Fulfillment column (URME Lager / Dropshipping / Local first / Paused) on WooCommerce > Products.
			URME_SS_Product_Source::init();
		}
		// Self-heal the schedule if it was lost (e.g. after a migration).
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule();
		}
	}

	public static function declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			// We never touch orders, and we use the core COGS API when it is enabled.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', URME_SS_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cost_of_goods_sold', URME_SS_FILE, true );
		}
	}

	public static function schedule() {
		// First run a few minutes after activation, then hourly.
		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
	}

	public static function activate() {
		URME_SS_DB::install();
		if ( false === get_option( URME_SS_Settings::OPTION ) ) {
			add_option( URME_SS_Settings::OPTION, URME_SS_Settings::defaults() );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule();
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
