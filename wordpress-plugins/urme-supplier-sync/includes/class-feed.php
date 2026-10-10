<?php
/**
 * Supplier feeds: download to a temp file, then stream-parse. Relojitos sends XML (XMLReader);
 * ILA Uhren sends a WooCommerce product export (CSV). The format is detected from the file.
 *
 * Nothing here touches WooCommerce products. Any failure throws, and the
 * caller then leaves the catalog and all products untouched.
 *
 * @package URME_Supplier_Sync
 */

defined( 'ABSPATH' ) || exit;

class URME_SS_Feed {

	const STATE_OPTION = 'urme_ss_feed_state';

	/**
	 * Download state (ETag, MD5…) option of a supplier's feed.
	 */
	public static function state_option( $supplier ) {
		return URME_SS_Suppliers::MAIN === $supplier ? self::STATE_OPTION : self::STATE_OPTION . '_' . $supplier;
	}

	/**
	 * Download the feed.
	 *
	 * @param bool   $conditional Send If-None-Match / If-Modified-Since.
	 * @param string $supplier    Supplier ID.
	 * @return array{file: string|null, not_modified: bool, bytes: int, etag: string, last_modified: string}
	 * @throws Exception On any transport or HTTP problem.
	 */
	public static function download( $conditional = true, $supplier = URME_SS_Suppliers::MAIN ) {
		$url = URME_SS_Suppliers::feed_url( $supplier );
		if ( ! $url ) {
			throw new Exception( 'No feed URL configured.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = wp_tempnam( 'urme-feed' );
		if ( ! $tmp ) {
			throw new Exception( 'Could not create a temporary file for the feed.' );
		}

		$headers = array();
		$state   = get_option( self::state_option( $supplier ), array() );
		if ( $conditional ) {
			if ( ! empty( $state['etag'] ) ) {
				$headers['If-None-Match'] = $state['etag'];
			}
			if ( ! empty( $state['last_modified'] ) ) {
				$headers['If-Modified-Since'] = $state['last_modified'];
			}
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'    => 300,
				'stream'     => true,
				'filename'   => $tmp,
				'decompress' => false,
				'headers'    => $headers,
				'user-agent' => 'URME-Supplier-Sync/' . URME_SS_VERSION . '; ' . home_url(),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_delete_file( $tmp );
			throw new Exception( 'Feed download failed: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 304 === $code ) {
			wp_delete_file( $tmp );
			return array(
				'file'          => null,
				'not_modified'  => true,
				'bytes'         => 0,
				'etag'          => $state['etag'] ?? '',
				'last_modified' => $state['last_modified'] ?? '',
			);
		}
		if ( 200 !== $code ) {
			wp_delete_file( $tmp );
			throw new Exception( sprintf( 'Feed download failed: HTTP %d.', $code ) );
		}

		clearstatcache( true, $tmp );
		$bytes = (int) filesize( $tmp );
		if ( $bytes < 100 ) {
			wp_delete_file( $tmp );
			throw new Exception( sprintf( 'Feed download looks empty (%d bytes).', $bytes ) );
		}

		return array(
			'file'          => $tmp,
			'not_modified'  => false,
			'bytes'         => $bytes,
			'etag'          => (string) wp_remote_retrieve_header( $response, 'etag' ),
			'last_modified' => (string) wp_remote_retrieve_header( $response, 'last-modified' ),
		);
	}

	/**
	 * Parse a downloaded feed of any supported format (XML or CSV, plain or gzip).
	 *
	 * @param string $file       Path to the file.
	 * @param array  $categories Upper-case CATEGORY values to keep.
	 * @param string $supplier   Supplier ID (its rows get its item_key prefix).
	 * @throws Exception If the file cannot be read.
	 */
	public static function parse_file( $file, array $categories, $supplier = URME_SS_Suppliers::MAIN ) {
		$path = self::is_gzip( $file ) ? 'compress.zlib://' . $file : $file;
		$fh   = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$head = $fh ? (string) fread( $fh, 512 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $fh ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$head = ltrim( preg_replace( '/^\xEF\xBB\xBF/', '', $head ) );
		return '<' === substr( $head, 0, 1 ) ? self::parse( $file, $categories, $supplier ) : self::parse_csv( $file, $categories, $supplier );
	}

	/**
	 * Parse the feed file, keeping only the configured categories.
	 *
	 * Streams item by item, so memory stays flat no matter how large the
	 * full feed is; only the kept (watch) items are held in memory.
	 *
	 * @param string $file       Path to the XML file (plain or gzip).
	 * @param array  $categories Upper-case CATEGORY values to keep.
	 * @param string $supplier   Supplier ID.
	 * @return array{items: array<string,string>, total: int, kept: int, skipped_invalid: int, duplicates: int, categories: array}
	 * @throws Exception If the XML is malformed or has no recognisable items.
	 */
	public static function parse( $file, array $categories, $supplier = URME_SS_Suppliers::MAIN ) {
		$path = self::is_gzip( $file ) ? 'compress.zlib://' . $file : $file;

		$item_name = self::detect_item_element( $path );

		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();

		$reader = new XMLReader();
		if ( ! $reader->open( $path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) ) {
			libxml_use_internal_errors( $previous );
			throw new Exception( 'Could not open the feed XML.' );
		}

		$wanted = array_flip( $categories );
		$result = array(
			'items'           => array(),
			'total'           => 0,
			'kept'            => 0,
			'skipped_invalid' => 0,
			'duplicates'      => 0,
			'categories'      => array(),
		);

		while ( @$reader->read() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( XMLReader::ELEMENT !== $reader->nodeType || $reader->localName !== $item_name ) {
				continue;
			}
			$fields = self::read_fields( $reader );
			++$result['total'];

			$category = strtoupper( trim( $fields['CATEGORY'] ?? '' ) );
			$result['categories'][ $category ] = ( $result['categories'][ $category ] ?? 0 ) + 1;
			if ( ! isset( $wanted[ $category ] ) ) {
				continue;
			}

			$item = self::normalize( $fields, $category, $supplier );
			if ( ! $item ) {
				++$result['skipped_invalid'];
				continue;
			}
			if ( isset( $result['items'][ $item['item_key'] ] ) ) {
				++$result['duplicates'];
				continue;
			}
			$result['items'][ $item['item_key'] ] = self::pack( $item );
		}

		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$reader->close();

		// read() returning false can mean "done" or "broken"; libxml tells us which.
		foreach ( $errors as $error ) {
			if ( $error->level >= LIBXML_ERR_ERROR ) {
				throw new Exception( sprintf( 'Feed XML is malformed (line %d: %s). Nothing was changed.', $error->line, trim( $error->message ) ) );
			}
		}
		if ( 0 === $result['total'] ) {
			throw new Exception( 'Feed XML contained no products. Nothing was changed.' );
		}

		$result['kept'] = count( $result['items'] );
		arsort( $result['categories'] );
		return $result;
	}

	/**
	 * Parse a WooCommerce product export CSV (ILA Uhren): one product per line, columns such as
	 * sku, gtin, post_title, tax:brand, tax:product_cat ("Brand Watches>Guess>Men's"), sale_price
	 * (the dealer price, EUR, VAT 0%), stock, stock_status and images. "Brand Watches" is CATEGORY
	 * WATCH. Out-of-stock products get stock 0. A price or stock that cannot be read is null, never guessed.
	 *
	 * @throws Exception If the file has no header line with the needed columns or no products.
	 */
	public static function parse_csv( $file, array $categories, $supplier = URME_SS_Suppliers::MAIN ) {
		$path = self::is_gzip( $file ) ? 'compress.zlib://' . $file : $file;
		$fh   = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			throw new Exception( 'Could not open the feed file.' );
		}
		$first = (string) fgets( $fh );
		$first = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		$delim = substr_count( $first, ';' ) >= substr_count( $first, ',' ) ? ';' : ',';
		$head  = array_map( static function ( $h ) {
			return strtolower( trim( (string) $h ) );
		}, str_getcsv( $first, $delim, '"', '' ) );
		$col   = array_flip( $head );
		foreach ( array( 'sku', 'sale_price' ) as $need ) {
			if ( ! isset( $col[ $need ] ) ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				throw new Exception( sprintf( 'Feed CSV has no "%s" column. Nothing was changed.', $need ) );
			}
		}
		$get = static function ( array $row, $name ) use ( $col ) {
			return isset( $col[ $name ], $row[ $col[ $name ] ] ) ? trim( (string) $row[ $col[ $name ] ] ) : '';
		};

		$wanted = array_flip( $categories );
		$result = array(
			'items'           => array(),
			'total'           => 0,
			'kept'            => 0,
			'skipped_invalid' => 0,
			'duplicates'      => 0,
			'categories'      => array(),
		);
		while ( false !== ( $row = fgetcsv( $fh, 0, $delim, '"', '' ) ) ) {
			if ( array( null ) === $row ) {
				continue; // Blank line.
			}
			++$result['total'];
			$path_parts = array_map( 'trim', explode( '>', $get( $row, 'tax:product_cat' ) ) );
			$top        = strtoupper( $path_parts[0] );
			$category   = false !== strpos( $top, 'WATCH' ) ? 'WATCH' : ( false !== strpos( $top, 'JEWEL' ) ? 'JEWELRY' : $top );
			$result['categories'][ $category ] = ( $result['categories'][ $category ] ?? 0 ) + 1;
			if ( ! isset( $wanted[ $category ] ) ) {
				continue;
			}
			$images = preg_split( '/[|,]\s*(?=https?:)/', $get( $row, 'images' ) );
			$status = strtolower( $get( $row, 'stock_status' ) );
			$stock  = 'outofstock' === $status ? 0 : self::parse_stock( $get( $row, 'stock' ) );
			$fields = array(
				'ITEM_ID'        => $get( $row, 'gtin' ),
				'PRODUCTNO'      => $get( $row, 'sku' ),
				// Upper case, like the Relojitos feed, so a brand is one brand in filters and reports.
				'MANUFACTURER'   => mb_strtoupper( '' !== $get( $row, 'tax:brand' ) ? $get( $row, 'tax:brand' ) : ( $path_parts[1] ?? '' ), 'UTF-8' ),
				'PRODUCT_NAME'   => html_entity_decode( $get( $row, 'post_title' ), ENT_QUOTES, 'UTF-8' ),
				'SUBCATEGORY'    => count( $path_parts ) > 2 ? end( $path_parts ) : '',
				'PURCHASE_PRICE' => $get( $row, 'sale_price' ),
				// regular_price in ILA's export is the manufacturer's recommended retail price (EUR).
				'RRP'            => $get( $row, 'regular_price' ),
				'STOCK'          => null === $stock ? '' : (string) $stock,
				'IMG_URL'        => trim( (string) ( $images[0] ?? '' ) ),
			);
			$item = self::normalize( $fields, $category, $supplier );
			if ( ! $item ) {
				++$result['skipped_invalid'];
				continue;
			}
			if ( isset( $result['items'][ $item['item_key'] ] ) ) {
				++$result['duplicates'];
				continue;
			}
			$result['items'][ $item['item_key'] ] = self::pack( $item );
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( 0 === $result['total'] ) {
			throw new Exception( 'Feed CSV contained no products. Nothing was changed.' );
		}
		$result['kept'] = count( $result['items'] );
		arsort( $result['categories'] );
		return $result;
	}

	/**
	 * Find the repeating product element: the parent of the first CATEGORY element.
	 *
	 * @throws Exception If none is found.
	 */
	private static function detect_item_element( $path ) {
		$previous = libxml_use_internal_errors( true );
		$reader   = new XMLReader();
		if ( ! $reader->open( $path, null, LIBXML_NONET | LIBXML_PARSEHUGE ) ) {
			libxml_use_internal_errors( $previous );
			throw new Exception( 'Could not open the feed XML.' );
		}
		// Element name per depth along the current path.
		$path_names = array();
		$found      = null;
		while ( @$reader->read() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( XMLReader::ELEMENT !== $reader->nodeType ) {
				continue;
			}
			if ( 'CATEGORY' === strtoupper( $reader->localName ) && $reader->depth > 0 ) {
				$found = $path_names[ $reader->depth - 1 ];
				break;
			}
			$path_names[ $reader->depth ] = $reader->localName;
		}
		$reader->close();
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $found ) {
			throw new Exception( 'Could not find any CATEGORY element in the feed XML. Nothing was changed.' );
		}
		return $found;
	}

	/**
	 * Read child elements of the current item into an UPPERCASE => text map.
	 */
	private static function read_fields( XMLReader $reader ) {
		$fields = array();
		if ( $reader->isEmptyElement ) {
			return $fields;
		}
		$depth = $reader->depth;
		$name  = null;
		while ( @$reader->read() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( XMLReader::END_ELEMENT === $reader->nodeType && $reader->depth === $depth ) {
				break;
			}
			if ( XMLReader::ELEMENT === $reader->nodeType && $reader->depth === $depth + 1 ) {
				$name = strtoupper( $reader->localName );
				if ( ! isset( $fields[ $name ] ) ) {
					$fields[ $name ] = '';
				}
				if ( $reader->isEmptyElement ) {
					$name = null;
				}
			} elseif ( XMLReader::END_ELEMENT === $reader->nodeType && $reader->depth === $depth + 1 ) {
				$name = null;
			} elseif ( null !== $name && ( XMLReader::TEXT === $reader->nodeType || XMLReader::CDATA === $reader->nodeType ) ) {
				$fields[ $name ] .= $reader->value;
			}
		}
		return array_map( 'trim', $fields );
	}

	/**
	 * Turn raw feed fields into a catalog row, or null if it cannot be identified.
	 */
	public static function normalize( array $f, $category, $supplier = URME_SS_Suppliers::MAIN ) {
		$item_id    = preg_replace( '/\s+/', '', (string) ( $f['ITEM_ID'] ?? '' ) );
		$product_no = trim( (string) ( $f['PRODUCTNO'] ?? '' ) );
		$supplier   = URME_SS_Suppliers::id( $supplier );

		if ( '' === $item_id && '' === $product_no ) {
			return null;
		}

		$item = array(
			'supplier'       => $supplier,
			'item_key'       => URME_SS_Suppliers::get( $supplier )['prefix'] . ( '' !== $item_id ? $item_id : 'P:' . $product_no ),
			'item_id'        => substr( $item_id, 0, 64 ),
			'product_no'     => substr( $product_no, 0, 100 ),
			'manufacturer'   => mb_substr( trim( (string) ( $f['MANUFACTURER'] ?? '' ) ), 0, 100 ),
			'product_name'   => mb_substr( trim( (string) ( $f['PRODUCT_NAME'] ?? '' ) ), 0, 255 ),
			'category'       => substr( $category, 0, 50 ),
			'subcategory'    => mb_substr( trim( (string) ( $f['SUBCATEGORY'] ?? '' ) ), 0, 100 ),
			'purchase_price' => self::parse_price( $f['PURCHASE_PRICE'] ?? '' ),
			// Recommended retail price (EUR): RECOMMENDER_RETAIL_PRICE in the Relojitos XML, regular_price in ILA's CSV.
			'rrp'            => self::parse_price( $f['RRP'] ?? ( $f['RECOMMENDER_RETAIL_PRICE'] ?? ( $f['RECOMMENDED_RETAIL_PRICE'] ?? '' ) ) ),
			'stock'          => self::parse_stock( $f['STOCK'] ?? '' ),
			'img_url'        => substr( esc_url_raw( trim( (string) ( $f['IMG_URL'] ?? '' ) ) ), 0, 1000 ),
		);
		$item['item_key']  = substr( $item['item_key'], 0, 100 );
		$item['data_hash'] = md5( implode( '|', array( $item['item_id'], $item['product_no'], $item['manufacturer'], $item['product_name'], $item['category'], $item['subcategory'], (string) $item['purchase_price'], (string) $item['stock'], $item['img_url'], (string) $item['rrp'] ) ) );
		return $item;
	}

	/**
	 * Parse "123.45", "123,45", "1.234,56", "1,234.56", "EUR 99". Null if not a positive number.
	 */
	public static function parse_price( $raw ) {
		$s = preg_replace( '/[^0-9.,\-]/', '', (string) $raw );
		if ( '' === $s || '-' === $s[0] ) {
			return null;
		}
		$last_dot   = strrpos( $s, '.' );
		$last_comma = strrpos( $s, ',' );
		if ( false !== $last_dot && false !== $last_comma ) {
			// Whichever separator comes last is the decimal separator.
			$decimal  = $last_dot > $last_comma ? '.' : ',';
			$thousand = '.' === $decimal ? ',' : '.';
			$s        = str_replace( array( $thousand, $decimal ), array( '', '.' ), $s );
		} elseif ( false !== $last_comma ) {
			// "12,50" is a decimal comma; "1,234,567" is thousands grouping.
			$s = substr_count( $s, ',' ) > 1 ? str_replace( ',', '', $s ) : str_replace( ',', '.', $s );
		} elseif ( substr_count( $s, '.' ) > 1 ) {
			$s = str_replace( '.', '', $s );
		}
		if ( ! is_numeric( $s ) ) {
			return null;
		}
		$v = round( (float) $s, 4 );
		return $v > 0 ? $v : null;
	}

	/**
	 * Parse a stock value. Null when it is not a whole number, so stock is never guessed.
	 */
	public static function parse_stock( $raw ) {
		$s = trim( (string) $raw );
		if ( '' === $s ) {
			return null;
		}
		// Accept "5", "5.0", ">10" / "10+" style values as their number.
		if ( preg_match( '/^[<>]?\s*(-?\d+)(?:[.,]0+)?\s*\+?$/', $s, $m ) ) {
			return max( 0, (int) $m[1] );
		}
		return null;
	}

	/**
	 * Items are held as one delimited string while parsing: ~4x less memory than arrays.
	 */
	const FIELDS = array( 'data_hash', 'item_key', 'item_id', 'product_no', 'manufacturer', 'product_name', 'category', 'subcategory', 'purchase_price', 'stock', 'img_url', 'supplier', 'rrp' );

	private static function pack( array $item ) {
		$values = array();
		foreach ( self::FIELDS as $field ) {
			$values[] = null === $item[ $field ] ? "\x00" : str_replace( "\x1f", ' ', (string) $item[ $field ] );
		}
		return implode( "\x1f", $values );
	}

	public static function unpack( $packed ) {
		$item = array_combine( self::FIELDS, explode( "\x1f", $packed ) );
		foreach ( $item as $field => $value ) {
			if ( "\x00" === $value ) {
				$item[ $field ] = null;
			}
		}
		return $item;
	}

	/**
	 * Hash of a packed item without unpacking it.
	 */
	public static function packed_hash( $packed ) {
		return substr( $packed, 0, 32 );
	}

	private static function is_gzip( $file ) {
		$fh = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return false;
		}
		$magic = fread( $fh, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return "\x1f\x8b" === $magic;
	}
}
