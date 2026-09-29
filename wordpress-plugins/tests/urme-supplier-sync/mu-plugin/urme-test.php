<?php
// Test-only: allow the local feed server and fake the exchange-rate sources.
add_filter( 'http_request_host_is_external', '__return_true' );
add_filter( 'http_allowed_safe_ports', function ( $p ) { $p[] = 8090; return $p; } );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$mode = get_option( 'urme_test_rate_mode', 'ecb' );
	$resp = function ( $body ) { return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null ); };
	if ( false !== strpos( $url, 'ecb.europa.eu' ) ) {
		if ( 'ecb' === $mode ) return $resp( "<gesmes:Envelope><Cube><Cube time='2026-09-29'><Cube currency='USD' rate='1.17'/><Cube currency='SEK' rate='11.0250'/></Cube></Cube></gesmes:Envelope>" );
		if ( 'jump' === $mode ) return $resp( "<Cube time='2026-09-29'><Cube currency='SEK' rate='15.5000'/></Cube>" );
		return new WP_Error( 'x', 'ECB down (test)' );
	}
	if ( false !== strpos( $url, 'riksbank.se' ) ) {
		if ( 'none' === $mode ) return new WP_Error( 'x', 'Riksbank down (test)' );
		return $resp( '{"date":"2026-09-29","value":11.0312}' );
	}
	return $pre;
}, 10, 3 );
