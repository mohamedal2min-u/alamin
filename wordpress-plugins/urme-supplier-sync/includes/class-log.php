<?php
/**
 * Small activity log shown in the admin, mirrored to WooCommerce > Status > Logs.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Log {

	const OPTION = 'urme_ss_log';
	const MAX    = 150;

	/**
	 * Entries added during this request; written once on shutdown.
	 *
	 * @var array
	 */
	private static $pending = array();

	public static function info( $message ) {
		self::add( 'info', $message );
	}

	public static function warning( $message ) {
		self::add( 'warning', $message );
	}

	public static function error( $message ) {
		self::add( 'error', $message );
	}

	private static function add( $level, $message ) {
		if ( ! self::$pending ) {
			add_action( 'shutdown', array( __CLASS__, 'flush' ) );
		}
		self::$pending[] = array( time(), $level, (string) $message );

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => 'urme-supplier-sync' ) );
		}
	}

	public static function flush() {
		if ( ! self::$pending ) {
			return;
		}
		$log = get_option( self::OPTION, array() );
		// Stored newest first.
		$log = array_merge( array_reverse( self::$pending ), is_array( $log ) ? $log : array() );
		update_option( self::OPTION, array_slice( $log, 0, self::MAX ), false );
		self::$pending = array();
	}

	public static function entries( $limit = 50 ) {
		self::flush();
		$log = get_option( self::OPTION, array() );
		return array_slice( is_array( $log ) ? $log : array(), 0, $limit );
	}
}
