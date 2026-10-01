<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( '\\RankMath\\Sitemap\\Providers\\Provider' ) ) {
	return;
}

final class URME_LE_RankMath_Sitemap_Provider implements \RankMath\Sitemap\Providers\Provider {

	public function handles_type( $type ) {
		return 'urme-landing' === $type;
	}

	public function get_index_links( $max_entries ) {
		$latest = get_posts(
			array(
				'post_type'      => URME_LE_Landing_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$lastmod = '';
		if ( $latest ) {
			$lastmod = mysql2date( DATE_W3C, $latest[0]->post_modified_gmt ?: $latest[0]->post_modified, false );
		}

		return array(
			array(
				'loc'     => \RankMath\Sitemap\Router::get_base_url( 'urme-landing-sitemap.xml' ),
				'lastmod' => $lastmod,
			),
		);
	}

	public function get_sitemap_links( $type, $max_entries, $current_page ) {
		$max_entries = max( 1, absint( $max_entries ) );
		$current_page = max( 1, absint( $current_page ) );

		$posts = get_posts(
			array(
				'post_type'      => URME_LE_Landing_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $max_entries,
				'paged'          => $current_page,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$links = array();
		foreach ( $posts as $post ) {
			$config = URME_LE_Landing_CPT::config_from_post( $post );
			if ( ! $config || empty( $config['route_slug'] ) ) {
				continue;
			}

			if ( ! URME_LE_Router::config_has_products( $config ) ) {
				continue;
			}

			if ( 'brand' === $config['scope'] ) {
				if ( empty( $config['brand_slug'] ) ) {
					continue;
				}
				$loc = home_url( '/marken/' . $config['brand_slug'] . '/' . $config['route_slug'] . '/' );
			} else {
				$loc = home_url( '/klockor/' . $config['route_slug'] . '/' );
			}

			$item = array(
				'loc' => $loc,
				'mod' => mysql2date( DATE_W3C, $post->post_modified_gmt ?: $post->post_modified, false ),
			);

			if ( ! empty( $config['image_id'] ) ) {
				$src = wp_get_attachment_image_url( $config['image_id'], 'full' );
				if ( $src ) {
					$item['images'] = array(
						array(
							'src'   => $src,
							'title' => $config['h1'] ?: get_the_title( $post ),
						),
					);
				}
			}

			$links[] = $item;
		}

		return $links;
	}
}
