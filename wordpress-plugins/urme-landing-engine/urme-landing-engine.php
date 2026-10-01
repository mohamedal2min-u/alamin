<?php
/**
 * Plugin Name: URME Landing Engine
 * Plugin URI: https://www.urme.se/
 * Description: Lightweight dynamic WooCommerce landing pages for URME: brand, gender, sale, series, attribute pages, hero images, breadcrumbs and SEO integration.
 * Version: 1.0.40
 * Author: URME
 * License: GPL-2.0-or-later
 * Text Domain: urme-landing-engine
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'URME_LE_VERSION', '1.0.40' );
define( 'URME_LE_FILE', __FILE__ );
define( 'URME_LE_PATH', plugin_dir_path( __FILE__ ) );
define( 'URME_LE_URL', plugin_dir_url( __FILE__ ) );

require_once URME_LE_PATH . 'includes/class-urme-le-plugin.php';

register_activation_hook( __FILE__, array( 'URME_LE_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'URME_LE_Plugin', 'instance' ), 20 );
