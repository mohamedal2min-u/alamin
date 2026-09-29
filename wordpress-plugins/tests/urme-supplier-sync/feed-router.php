<?php
$dir  = __DIR__ . '/feedsrv';
$mode = trim( (string) @file_get_contents( "$dir/mode.txt" ) ) ?: 'ok';
if ( 'http500' === $mode ) { http_response_code( 500 ); echo 'error'; return true; }
$file = "$dir/current.xml";
$etag = '"' . md5_file( $file ) . '"';
if ( 'etag' === $mode && ( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) === $etag ) { http_response_code( 304 ); return true; }
header( 'Content-Type: application/xml' );
if ( 'etag' === $mode ) header( "ETag: $etag" );
readfile( $file );
return true;
