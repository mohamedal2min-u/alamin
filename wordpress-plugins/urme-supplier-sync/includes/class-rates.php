<?php
/**
 * EUR -> SEK exchange rate.
 *
 * Primary source: European Central Bank daily reference rate.
 * Fallback: Sveriges Riksbank (SWEA API). Fetched at most every 12 hours;
 * the last good rate is kept when both sources fail.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Rates {

	const OPTION      = 'urme_ss_rate';
	const MAX_AGE     = 12 * HOUR_IN_SECONDS;
	const ECB_URL     = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
	const RIKSBANK_URL = 'https://api.riksbank.se/swea/v1/Observations/Latest/SEKEURPMI';

	/**
	 * Stored rate state: rate, date (rate reference date), source, fetched_at, last_error, last_error_at.
	 */
	public static function state() {
		$s = get_option( self::OPTION, array() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Rate to use for conversion, or null when none is available.
	 *
	 * @return array{rate: float, source: string, date: string, overridden: bool}|null
	 */
	public static function current() {
		$override = URME_SS_Settings::get( 'rate_override' );
		if ( '' !== $override && (float) $override > 0 ) {
			return array(
				'rate'       => (float) $override,
				'source'     => 'Manual override',
				'date'       => '',
				'overridden' => true,
			);
		}
		$s = self::state();
		if ( empty( $s['rate'] ) ) {
			return null;
		}
		return array(
			'rate'       => (float) $s['rate'],
			'source'     => (string) ( $s['source'] ?? '' ),
			'date'       => (string) ( $s['date'] ?? '' ),
			'overridden' => false,
		);
	}

	/**
	 * Refresh the rate if it is older than MAX_AGE (or always when $force).
	 *
	 * @return bool True when a fresh rate was stored.
	 */
	public static function maybe_refresh( $force = false ) {
		$s = self::state();
		if ( ! $force && ! empty( $s['fetched_at'] ) && ( time() - (int) $s['fetched_at'] ) < self::MAX_AGE ) {
			return false;
		}

		$errors = array();
		foreach ( array( 'fetch_ecb', 'fetch_riksbank' ) as $method ) {
			try {
				$new = self::$method();
				self::validate( $new['rate'], $s );
				$s = array_merge(
					$s,
					$new,
					array(
						'fetched_at' => time(),
						'last_error' => '',
					)
				);
				update_option( self::OPTION, $s, false );
				URME_SS_Log::info( sprintf( 'EUR/SEK rate updated: %s (%s, %s).', $new['rate'], $new['source'], $new['date'] ) );
				return true;
			} catch ( Exception $e ) {
				$errors[] = $e->getMessage();
			}
		}

		$s['last_error']    = implode( ' | ', $errors );
		$s['last_error_at'] = time();
		update_option( self::OPTION, $s, false );
		URME_SS_Log::warning( 'EUR/SEK rate refresh failed, keeping the previous rate. ' . $s['last_error'] );
		return false;
	}

	private static function validate( $rate, array $previous ) {
		if ( $rate < 5 || $rate > 25 ) {
			throw new Exception( sprintf( 'Implausible EUR/SEK rate %s rejected.', $rate ) );
		}
		if ( ! empty( $previous['rate'] ) && abs( $rate / (float) $previous['rate'] - 1 ) > 0.15 ) {
			throw new Exception( sprintf( 'EUR/SEK rate %s differs more than 15%% from the previous %s; rejected. Use the manual override if this is real.', $rate, $previous['rate'] ) );
		}
	}

	private static function fetch_ecb() {
		$response = wp_safe_remote_get( self::ECB_URL, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			throw new Exception( 'ECB: ' . ( is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ) ) );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! preg_match( "/currency=['\"]SEK['\"]\s+rate=['\"]([0-9.]+)['\"]/", $body, $rate ) ) {
			throw new Exception( 'ECB: SEK rate not found in response.' );
		}
		preg_match( "/time=['\"](\d{4}-\d{2}-\d{2})['\"]/", $body, $date );
		return array(
			'rate'   => (float) $rate[1],
			'date'   => $date[1] ?? '',
			'source' => 'ECB reference rate',
		);
	}

	private static function fetch_riksbank() {
		$response = wp_safe_remote_get(
			self::RIKSBANK_URL,
			array(
				'timeout' => 20,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			throw new Exception( 'Riksbank: ' . ( is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ) ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['value'] ) || ! is_numeric( $data['value'] ) ) {
			throw new Exception( 'Riksbank: unexpected response.' );
		}
		return array(
			'rate'   => (float) $data['value'],
			'date'   => (string) ( $data['date'] ?? '' ),
			'source' => 'Sveriges Riksbank',
		);
	}

	/**
	 * Convert EUR to SEK, rounded to öre. Null when no rate is available.
	 */
	public static function to_sek( $eur ) {
		$current = self::current();
		if ( null === $eur || ! $current ) {
			return null;
		}
		return round( (float) $eur * $current['rate'], 2 );
	}
}
