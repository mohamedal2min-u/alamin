<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class URME_LE_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		require_once URME_LE_PATH . 'includes/class-urme-le-landing-cpt.php';
		URME_LE_Landing_CPT::register_post_type();
		URME_LE_Landing_CPT::seed_defaults();
		/*
		 * Run migrations before the installed-version option is advanced. Older
		 * releases stamped the current version here first, which meant an update
		 * performed while the plugin was inactive could permanently skip the
		 * migrations registered later on init.
		 */
		URME_LE_Landing_CPT::maybe_upgrade();
		flush_rewrite_rules( false );
	}

	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	private function load_dependencies() {
		require_once URME_LE_PATH . 'includes/class-urme-le-landing-cpt.php';
		require_once URME_LE_PATH . 'includes/class-urme-le-router.php';
		require_once URME_LE_PATH . 'includes/class-urme-le-seo.php';
		require_once URME_LE_PATH . 'includes/class-urme-le-frontend.php';
		require_once URME_LE_PATH . 'includes/class-urme-le-sitemap.php';
		require_once URME_LE_PATH . 'includes/class-urme-le-settings.php';
	}

	private function register_hooks() {
		add_action( 'init', array( 'URME_LE_Landing_CPT', 'register_post_type' ) );
		add_action( 'init', array( 'URME_LE_Landing_CPT', 'register_meta' ) );
		add_action( 'init', array( 'URME_LE_Landing_CPT', 'maybe_upgrade' ), 30 );

		if ( is_admin() ) {
			URME_LE_Landing_CPT::admin_hooks();
			URME_LE_Settings::hooks();
		}

		URME_LE_Router::hooks();
		URME_LE_SEO::hooks();
		URME_LE_Frontend::hooks();
		URME_LE_Sitemap::hooks();

		add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
	}

	public function woocommerce_notice() {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'URME Landing Engine requires WooCommerce to be active.', 'urme-landing-engine' ) . '</p></div>';
	}
}
