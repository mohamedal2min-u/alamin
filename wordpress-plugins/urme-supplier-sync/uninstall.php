<?php
/**
 * Remove the plugin's own tables and options. WooCommerce products are not touched.
 *
 * @package URME_Supplier_Sync
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}urme_ss_catalog" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}urme_ss_links" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}urme_ss_alloc" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}urme_ss_price_reviews" ); // phpcs:ignore WordPress.DB

foreach ( array( 'urme_ss_settings', 'urme_ss_status', 'urme_ss_log', 'urme_ss_rate', 'urme_ss_feed_state', 'urme_ss_db_version', 'urme_ss_lock', 'urme_ss_match_checked', 'urme_ss_schema_error', 'urme_ss_ledger_since', 'urme_ss_new_since', 'urme_ss_lager_links_removed', 'urme_ss_fulfillment_meta', 'urme_ss_feed_state_ila', 'urme_ss_delivery_state' ) as $option ) {
	delete_option( $option );
}
delete_transient( 'urme_ss_inspection' );
delete_post_meta_by_key( 'urme_fulfillment' );
delete_post_meta_by_key( 'urme_supplier' );
// The plugin's WoodMart delivery rules (one per supplier).
foreach ( (array) get_option( 'urme_ss_delivery_rules', array() ) as $rule_id ) {
	if ( $rule_id && 'wd_woo_est_del' === get_post_type( (int) $rule_id ) ) {
		wp_delete_post( (int) $rule_id, true );
	}
}
delete_option( 'urme_ss_delivery_rules' );
delete_transient( 'wd_transient_est_del_ids' );
wp_clear_scheduled_hook( 'urme_ss_hourly' );
