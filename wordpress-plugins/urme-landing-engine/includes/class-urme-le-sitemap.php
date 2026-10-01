<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class URME_LE_Sitemap {

	public static function hooks() {
		add_filter( 'rank_math/sitemap/providers', array( __CLASS__, 'register_provider' ) );
		add_action( 'save_post_' . URME_LE_Landing_CPT::POST_TYPE, array( __CLASS__, 'invalidate' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'maybe_invalidate_deleted' ) );
	}

	public static function register_provider( $providers ) {
		if ( ! interface_exists( '\\RankMath\\Sitemap\\Providers\\Provider' ) ) {
			return $providers;
		}

		if ( ! class_exists( 'URME_LE_RankMath_Sitemap_Provider' ) ) {
			require_once URME_LE_PATH . 'includes/class-urme-le-rankmath-sitemap-provider.php';
		}

		if ( class_exists( 'URME_LE_RankMath_Sitemap_Provider' ) ) {
			$providers['urme-landing'] = new URME_LE_RankMath_Sitemap_Provider();
		}

		return $providers;
	}

	public static function invalidate() {
		delete_transient( 'urme_le_landing_map' );
		if ( class_exists( '\\RankMath\\Sitemap\\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage( 'urme-landing' );
		}
	}

	public static function maybe_invalidate_deleted( $post_id ) {
		if ( URME_LE_Landing_CPT::POST_TYPE === get_post_type( $post_id ) ) {
			self::invalidate();
		}
	}
}
