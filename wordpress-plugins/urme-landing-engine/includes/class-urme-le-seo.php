<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class URME_LE_SEO {

	public static function hooks() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 99 );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'archive_title' ), 99 );
		add_filter( 'woocommerce_page_title', array( __CLASS__, 'woocommerce_title' ), 99 );

		add_filter( 'rank_math/frontend/title', array( __CLASS__, 'rank_math_title' ), 99 );
		add_filter( 'rank_math/frontend/description', array( __CLASS__, 'rank_math_description' ), 99 );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'rank_math_canonical' ), 99 );
		add_filter( 'rank_math/frontend/next_rel_link', array( __CLASS__, 'rank_math_next_rel_link' ), 99 );
		add_filter( 'rank_math/frontend/prev_rel_link', array( __CLASS__, 'rank_math_prev_rel_link' ), 99 );
		add_filter( 'rank_math/frontend/breadcrumb/items', array( __CLASS__, 'rank_math_breadcrumbs' ), 99, 2 );
		add_filter( 'woocommerce_get_breadcrumb', array( __CLASS__, 'woocommerce_breadcrumbs' ), 99, 2 );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'rank_math_robots' ), 99 );
		add_filter( 'rank_math/opengraph/facebook/image', array( __CLASS__, 'rank_math_og_image' ), 99 );
		add_filter( 'rank_math/opengraph/twitter/image', array( __CLASS__, 'rank_math_og_image' ), 99 );
	}

	public static function rank_math_robots( $robots ) {
		if ( URME_LE_Router::is_dynamic() && URME_LE_Router::is_empty_configured() ) {
			$robots['index'] = 'noindex';
		}
		return $robots;
	}

	/**
	 * The hero image (and its crop position) resolved the same way the
	 * frontend header renders it: the landing's own Featured Image and its
	 * own position setting, else the site-wide default image and position
	 * set under WooCommerce > Landing settings.
	 */
	public static function hero_image() {
		$config     = URME_LE_Router::current_config();
		$image_id   = $config && ! empty( $config['image_id'] ) ? absint( $config['image_id'] ) : 0;
		$position   = $config && ! empty( $config['image_position'] ) ? $config['image_position'] : '';
		$position_x = $config && ! empty( $config['image_position_x'] ) ? $config['image_position_x'] : '';

		if ( ! $image_id ) {
			$image_id   = URME_LE_Settings::default_image_id();
			$position   = URME_LE_Settings::default_image_position();
			$position_x = URME_LE_Settings::default_image_position_x();
		}

		return array(
			'id'         => $image_id,
			'url'        => $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '',
			'position'   => $position ? $position : 'center',
			'position_x' => $position_x ? $position_x : 'center',
		);
	}

	/**
	 * Shared by both the visible <img> and the Open Graph/Twitter
	 * share-image filters below.
	 */
	public static function hero_image_url() {
		$image = self::hero_image();
		return $image['url'];
	}

	public static function hero_image_position() {
		$image = self::hero_image();
		return $image['position'];
	}

	public static function hero_image_position_x() {
		$image = self::hero_image();
		return $image['position_x'];
	}

	public static function rank_math_og_image( $attachment_url ) {
		if ( ! URME_LE_Router::is_dynamic() ) {
			return $attachment_url;
		}
		$url = self::hero_image_url();
		return $url ? $url : $attachment_url;
	}

	public static function h1() {
		$config = URME_LE_Router::current_config();
		if ( $config && ! empty( $config['h1'] ) ) {
			return $config['h1'];
		}

		$ctx = URME_LE_Router::context();
		if ( ! $ctx ) {
			return '';
		}

		if ( 'global' === $ctx['context'] ) {
			return ucwords( str_replace( '-', ' ', $ctx['child_slug'] ) );
		}

		$brand = URME_LE_Router::current_brand_term();
		if ( ! $brand ) {
			return '';
		}

		if ( 'herr' === $ctx['kind'] ) {
			return $brand->name . ' herrklockor';
		}
		if ( 'dam' === $ctx['kind'] ) {
			return $brand->name . ' damklockor';
		}
		if ( 'rea' === $ctx['kind'] ) {
			return $brand->name . ' klockor på rea';
		}
		if ( 'serie' === $ctx['kind'] ) {
			$series = URME_LE_Router::current_series_term();
			return $series ? $brand->name . ' ' . $series->name . ' klockor' : $brand->name;
		}

		return $brand->name;
	}

	public static function visual_title() {
		$config = URME_LE_Router::current_config();
		if ( $config && ! empty( $config['h1'] ) ) {
			return $config['h1'];
		}

		$ctx = URME_LE_Router::context();
		if ( ! $ctx ) {
			return '';
		}

		if ( 'global' === $ctx['context'] ) {
			return self::h1();
		}

		$brand = URME_LE_Router::current_brand_term();
		if ( ! $brand ) {
			return self::h1();
		}

		$brand_name = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $brand->name, 'UTF-8' ) : strtoupper( $brand->name );
		$child = self::child_label();
		return $child ? $brand_name . ' – ' . $child : $brand_name;
	}

	public static function child_label() {
		$config = URME_LE_Router::current_config();
		$ctx = URME_LE_Router::context();
		if ( ! $ctx ) {
			return '';
		}

		if ( 'global' === $ctx['context'] ) {
			return $config && ! empty( $config['h1'] ) ? $config['h1'] : self::h1();
		}

		if ( 'herr' === $ctx['kind'] ) {
			return 'Herrklockor';
		}
		if ( 'dam' === $ctx['kind'] ) {
			return 'Damklockor';
		}
		if ( 'rea' === $ctx['kind'] ) {
			return 'Rea';
		}
		if ( 'serie' === $ctx['kind'] ) {
			$series = URME_LE_Router::current_series_term();
			return $series ? $series->name : '';
		}
		if ( 'configured' === $ctx['kind'] && $config ) {
			return ! empty( $config['h1'] ) ? $config['h1'] : ucwords( str_replace( '-', ' ', $ctx['child_slug'] ) );
		}
		return '';
	}

	public static function subtitle() {
		$config = URME_LE_Router::current_config();
		return $config && ! empty( $config['subtitle'] ) ? $config['subtitle'] : '';
	}

	/**
	 * Reads a Rank Math SEO term meta value (e.g. rank_math_title,
	 * rank_math_description) for the taxonomy term backing the current
	 * automatic route, so content already written on Products > Attributes >
	 * [taxonomy] > [term] doesn't have to be duplicated into a landing record.
	 */
	private static function term_seo_meta( $term, $key ) {
		if ( ! $term || empty( $term->term_id ) ) {
			return '';
		}
		return (string) get_term_meta( $term->term_id, $key, true );
	}

	/**
	 * Keyword-aligned fallback title for automatic brand routes that have no
	 * "configured" landing and no term-level Rank Math title - i.e. every
	 * brand that only has the standard herrklockor/damklockor/rea/serie
	 * routes (anything besides the brands that got dedicated landing pages,
	 * like Seiko). Phrasing follows real search terms from keyword research
	 * (e.g. "{brand} klocka herr", "{brand} klocka dam") rather than the
	 * plugin's own house style, so it actually matches what people type.
	 */
	private static function generic_brand_title( $ctx ) {
		$brand = URME_LE_Router::current_brand_term();
		if ( ! $brand ) {
			return self::h1() . ' | Köp online hos URME';
		}

		if ( 'herr' === $ctx['kind'] ) {
			return sprintf( '%s Klocka Herr – Köp Online | URME', $brand->name );
		}
		if ( 'dam' === $ctx['kind'] ) {
			return sprintf( '%s Klocka Dam – Köp Online | URME', $brand->name );
		}
		if ( 'rea' === $ctx['kind'] ) {
			return sprintf( '%s Klockor Rea – Nedsatta Priser | URME', $brand->name );
		}
		if ( 'serie' === $ctx['kind'] ) {
			$series = URME_LE_Router::current_series_term();
			if ( $series ) {
				return sprintf( '%s %s Klockor – Köp Online | URME', $brand->name, $series->name );
			}
		}

		return self::h1() . ' | Köp online hos URME';
	}

	public static function seo_title() {
		$config = URME_LE_Router::current_config();
		if ( $config && ! empty( $config['seo_title'] ) ) {
			$title = $config['seo_title'];
		} else {
			$term       = URME_LE_Router::current_child_term();
			$term_title = self::term_seo_meta( $term, 'rank_math_title' );
			if ( $term_title ) {
				$title = $term_title;
			} else {
				$ctx   = URME_LE_Router::context();
				$title = ( $ctx && 'brand' === $ctx['context'] ) ? self::generic_brand_title( $ctx ) : self::h1() . ' | Köp online hos URME';
			}
		}
		$paged = max( 1, absint( get_query_var( 'paged' ) ) );
		if ( $paged > 1 ) {
			$title = preg_replace( '/\s*\|\s*/', ' – Sida ' . $paged . ' | ', $title, 1 );
		}
		return trim( $title );
	}

	public static function meta_description() {
		$config = URME_LE_Router::current_config();
		if ( $config && ! empty( $config['meta_description'] ) ) {
			return $config['meta_description'];
		}

		$term             = URME_LE_Router::current_child_term();
		$term_description = self::term_seo_meta( $term, 'rank_math_description' );
		if ( $term_description ) {
			return $term_description;
		}

		$ctx = URME_LE_Router::context();
		if ( ! $ctx ) {
			return '';
		}

		if ( 'brand' === $ctx['context'] ) {
			$brand = URME_LE_Router::current_brand_term();
			if ( $brand ) {
				if ( 'herr' === $ctx['kind'] ) {
					return sprintf( 'Upptäck %s klocka herr hos URME. Jämför herrklockor i olika modeller, urverk och design. Fri frakt, Klarna och 60 dagars öppet köp.', $brand->name );
				}
				if ( 'dam' === $ctx['kind'] ) {
					return sprintf( 'Upptäck %s klocka dam hos URME. Jämför damklockor i olika modeller, färger och design. Fri frakt, Klarna och 60 dagars öppet köp.', $brand->name );
				}
				if ( 'rea' === $ctx['kind'] ) {
					return sprintf( 'Handla %s klockor på rea hos URME. Utvalda modeller till nedsatta priser med fri frakt och 60 dagars öppet köp.', $brand->name );
				}
				if ( 'serie' === $ctx['kind'] ) {
					$series = URME_LE_Router::current_series_term();
					if ( $series ) {
						return sprintf( 'Upptäck %s %s klockor hos URME. Jämför modeller, priser och specifikationer och hitta rätt klocka för dig.', $brand->name, $series->name );
					}
				}
			}
		}

		return '';
	}

	public static function document_title( $title ) {
		return URME_LE_Router::is_dynamic() ? self::seo_title() : $title;
	}

	public static function archive_title( $title ) {
		return URME_LE_Router::is_dynamic() ? self::h1() : $title;
	}

	public static function woocommerce_title( $title ) {
		return URME_LE_Router::is_dynamic() ? self::visual_title() : $title;
	}

	public static function rank_math_title( $title ) {
		return URME_LE_Router::is_dynamic() ? self::seo_title() : $title;
	}

	public static function rank_math_description( $description ) {
		if ( ! URME_LE_Router::is_dynamic() ) {
			return $description;
		}
		$custom = self::meta_description();
		return $custom ? $custom : $description;
	}

	public static function rank_math_canonical( $canonical ) {
		if ( ! URME_LE_Router::is_dynamic() ) {
			return $canonical;
		}
		$url = URME_LE_Router::canonical_url();
		return $url ? $url : $canonical;
	}

	public static function rank_math_next_rel_link( $link ) {
		return self::rank_math_adjacent_rel_link( $link, 'next' );
	}

	public static function rank_math_prev_rel_link( $link ) {
		return self::rank_math_adjacent_rel_link( $link, 'prev' );
	}

	/**
	 * Rank Math builds archive rel=next/prev from the WooCommerce Shop archive
	 * canonical before its per-link filter runs. Rebuild only those two link
	 * tags for matched Landing Engine routes so SEO pagination stays on the
	 * plugin-owned route.
	 */
	private static function rank_math_adjacent_rel_link( $link, $rel ) {
		if ( ! URME_LE_Router::is_dynamic() || ! in_array( $rel, array( 'next', 'prev' ), true ) ) {
			return $link;
		}

		$ctx = URME_LE_Router::context();
		if ( ! $ctx ) {
			return $link;
		}

		$page = 'next' === $rel ? $ctx['paged'] + 1 : max( 1, $ctx['paged'] - 1 );
		$url  = URME_LE_Router::url_for_page( $page );
		if ( ! $url ) {
			return $link;
		}

		return '<link rel="' . esc_attr( $rel ) . '" href="' . esc_url( $url ) . '" />' . "\n";
	}

	public static function breadcrumb_items() {
		$ctx = URME_LE_Router::context();
		if ( ! $ctx ) {
			return array();
		}

		// Same home label as the rest of the site's Rank Math breadcrumbs ("Hem").
		$items = array(
			array( apply_filters( 'urme_le_breadcrumb_home_label', 'Hem' ), home_url( '/' ) ),
		);

		if ( 'global' === $ctx['context'] ) {
			$items[] = array( 'Klockor', home_url( '/klockor/' ) );
			$items[] = array( self::child_label(), '' );
			return $items;
		}

		$items[] = array( 'Märken', home_url( '/marken/' ) );
		$brand = URME_LE_Router::current_brand_term();
		if ( $brand ) {
			$url = get_term_link( $brand, 'product_brand' );
			$items[] = array( $brand->name, is_wp_error( $url ) ? home_url( '/marken/' . $brand->slug . '/' ) : $url );
		}
		$items[] = array( self::child_label(), '' );
		return $items;
	}

	public static function woocommerce_breadcrumbs( $crumbs, $breadcrumb ) {
		return URME_LE_Router::is_dynamic() ? self::breadcrumb_items() : $crumbs;
	}

	public static function rank_math_breadcrumbs( $crumbs, $class ) {
		if ( ! URME_LE_Router::is_dynamic() ) {
			return $crumbs;
		}
		$items = self::breadcrumb_items();
		$last  = count( $items ) - 1;
		$out   = array();
		foreach ( $items as $index => $item ) {
			$url = $item[1];
			/*
			 * The current page has no link in the visible trail, but Rank Math
			 * leaves crumbs without a URL out of the BreadcrumbList schema, so
			 * Google never saw the page itself (e.g. "... > SEIKO" without
			 * "Herrklockor"). Give the last crumb the page's canonical URL.
			 */
			if ( '' === $url && $index === $last ) {
				$url = URME_LE_Router::canonical_url();
			}
			$out[] = array(
				$item[0],
				$url,
				'hide_in_schema' => false,
			);
		}
		return $out;
	}
}
