<?php
/**
 * Plugin Name:       URME Supplier Sync
 * Description:       Browse the supplier's watch catalog and keep stock and cost price in sync for the WooCommerce products you explicitly link.
 * Version:           1.5.0
 * Author:            URME.se
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to:  11.1
 * Text Domain:       urme-supplier-sync
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

define( 'URME_SS_VERSION', '1.5.0' );
define( 'URME_SS_DB_VERSION', '3' );
define( 'URME_SS_FILE', __FILE__ );
define( 'URME_SS_DIR', plugin_dir_path( __FILE__ ) );
define( 'URME_SS_URL', plugin_dir_url( __FILE__ ) );

require_once URME_SS_DIR . 'includes/class-settings.php';
require_once URME_SS_DIR . 'includes/class-log.php';
require_once URME_SS_DIR . 'includes/class-db.php';
require_once URME_SS_DIR . 'includes/class-feed.php';
require_once URME_SS_DIR . 'includes/class-rates.php';
require_once URME_SS_DIR . 'includes/class-price-hint.php';
require_once URME_SS_DIR . 'includes/class-store.php';
require_once URME_SS_DIR . 'includes/class-matcher.php';
require_once URME_SS_DIR . 'includes/class-inventory.php';
require_once URME_SS_DIR . 'includes/class-fulfillment.php';
require_once URME_SS_DIR . 'includes/class-product-source.php';
require_once URME_SS_DIR . 'includes/class-gift-wrap.php';
require_once URME_SS_DIR . 'includes/class-price-review.php';
require_once URME_SS_DIR . 'includes/class-sync.php';
require_once URME_SS_DIR . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once URME_SS_DIR . 'includes/class-admin.php';
}

register_activation_hook( __FILE__, array( 'URME_SS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'URME_SS_Plugin', 'deactivate' ) );

URME_SS_Plugin::init();
