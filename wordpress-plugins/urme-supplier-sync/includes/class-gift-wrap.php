<?php
/**
 * ThemeComplete Extra Product Options (Presentinslagning) is not offered for true Dropshipping
 * watches: they ship straight from the supplier. The decision is the Fulfillment state
 * (URME_SS_Product_Source), per exact product or variation; nothing is stored on products and
 * the ThemeComplete forms are never changed.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Gift_Wrap {

	/**
	 * ThemeComplete posts its option fields as tmcp_<element>_<n> (e.g. tmcp_checkbox_0).
	 */
	const FIELD_PREFIX = 'tmcp_';

	public static function init() {
		add_filter( 'wc_epo_disable', array( __CLASS__, 'disable_epo' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 5 );
	}

	public static function is_dropship( $product_id ) {
		return URME_SS_Product_Source::DROPSHIP === URME_SS_Product_Source::product_state( $product_id );
	}

	/**
	 * ThemeComplete's switch for the product being rendered: off for a Dropshipping product
	 * (URME has one published global form, the gift wrap). Every other state is left as it is.
	 *
	 * @param bool $disable    Current value.
	 * @param int  $product_id Product ThemeComplete renders the options for.
	 */
	public static function disable_epo( $disable, $product_id = 0 ) {
		if ( $disable || ! (int) $product_id ) {
			return $disable;
		}
		return self::is_dropship( $product_id ) ? true : $disable;
	}

	/**
	 * A crafted request can still post the option fields: refuse gift wrap for a Dropshipping
	 * item (the selected variation when there is one). A purchase without it is not affected.
	 */
	public static function validate_add_to_cart( $passed, $product_id, $quantity = 1, $variation_id = 0, $variations = array() ) {
		if ( ! $passed || ! self::has_option_fields() ) {
			return $passed;
		}
		if ( ! self::is_dropship( (int) $variation_id ? (int) $variation_id : (int) $product_id ) ) {
			return $passed;
		}
		wc_add_notice( 'Presentinslagning kan inte väljas för den här produkten.', 'error' );
		return false;
	}

	private static function has_option_fields() {
		foreach ( $_REQUEST as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only check during WooCommerce's own add-to-cart.
			if ( 0 === strpos( (string) $key, self::FIELD_PREFIX ) && ! in_array( $value, array( '', null, array() ), true ) ) {
				return true;
			}
		}
		return false;
	}
}
