<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class URME_LE_Frontend {

	private static $header_rendered = false;

	public static function hooks() {
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
		add_action( 'woocommerce_sidebar', array( __CLASS__, 'render_header' ), 5 );
		add_action( 'woocommerce_before_main_content', array( __CLASS__, 'render_header' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'remove_shop_description' ), 20 );
		add_action( 'woocommerce_after_shop_loop', array( __CLASS__, 'render_intro' ), 15 );
		add_filter( 'woocommerce_show_page_title', array( __CLASS__, 'hide_default_wc_title' ), 99 );
		add_filter( 'woocommerce_breadcrumb_defaults', array( __CLASS__, 'breadcrumb_defaults' ), 99 );
	}

	public static function body_class( $classes ) {
		if ( ! URME_LE_Router::is_dynamic() ) {
			return $classes;
		}
		$classes[] = 'urme-le-dynamic-page';
		$ctx = URME_LE_Router::context();
		if ( $ctx ) {
			$classes[] = 'urme-le-context-' . sanitize_html_class( $ctx['context'] );
			$classes[] = 'urme-le-kind-' . sanitize_html_class( $ctx['kind'] );
			if ( ! empty( $ctx['brand_slug'] ) ) {
				$classes[] = 'urme-le-brand-' . sanitize_html_class( $ctx['brand_slug'] );
			}
		}
		return $classes;
	}

	public static function assets() {
		if ( ! URME_LE_Router::is_dynamic() ) {
			return;
		}
		wp_enqueue_style(
			'urme-le-front',
			URME_LE_URL . 'assets/front.css',
			array(),
			URME_LE_VERSION
		);

		wp_enqueue_script(
			'urme-le-front',
			URME_LE_URL . 'assets/front.js',
			array(),
			URME_LE_VERSION,
			true
		);
	}

	public static function hide_default_wc_title( $show ) {
		return URME_LE_Router::is_dynamic() ? false : $show;
	}

	public static function breadcrumb_defaults( $defaults ) {
		if ( URME_LE_Router::is_dynamic() ) {
			$defaults['delimiter'] = '<span class="urme-le-separator">›</span>';
		}
		return $defaults;
	}

	public static function render_header() {
		if ( ! URME_LE_Router::is_dynamic() || self::$header_rendered ) {
			return;
		}

		self::$header_rendered = true;

		$hero             = URME_LE_SEO::hero_image();
		$image            = $hero['url'];
		$image_position   = $hero['position'];
		$image_position_x = $hero['position_x'];
		$title            = URME_LE_SEO::visual_title();
		$subtitle         = URME_LE_SEO::subtitle();
		$crumbs           = URME_LE_SEO::breadcrumb_items();
		$badge_text       = URME_LE_Settings::badge_text();
		$badge_icon       = URME_LE_Settings::badge_icon();

		?>
		<div class="urme-le-header-row">
		<nav class="urme-le-breadcrumbs" aria-label="<?php echo esc_attr__( 'Breadcrumb', 'urme-landing-engine' ); ?>">
			<?php foreach ( $crumbs as $index => $crumb ) : ?>
				<?php if ( $index > 0 ) : ?><span class="urme-le-separator" aria-hidden="true">›</span><?php endif; ?>
				<?php if ( ! empty( $crumb[1] ) ) : ?>
					<a href="<?php echo esc_url( $crumb[1] ); ?>"<?php echo 0 === $index ? ' class="urme-le-back-link"' : ''; ?>><?php echo 0 === $index ? '<span aria-hidden="true">←</span> ' : ''; ?><?php echo esc_html( $crumb[0] ); ?></a>
				<?php else : ?>
					<span class="urme-le-current" aria-current="page"><?php echo esc_html( $crumb[0] ); ?></span>
				<?php endif; ?>
			<?php endforeach; ?>
		</nav>

		<?php if ( $image ) : ?>
			<div class="urme-le-hero-image">
				<?php
				/*
				 * Render through wp_get_attachment_image() so WordPress adds
				 * width/height and srcset/sizes: phones download a ~768px file
				 * instead of the full-size original. fetchpriority=high keeps it
				 * the LCP image and also makes WoodMart skip lazy loading it.
				 */
				$hero_img = wp_get_attachment_image(
					$hero['id'],
					'full',
					false,
					array(
						'alt'           => $title,
						'style'         => 'object-position: ' . $image_position_x . ' ' . $image_position . ';',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
						'sizes'         => '100vw',
					)
				);

				if ( $hero_img ) {
					echo $hero_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped image markup.
				} else {
					?>
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" style="object-position: <?php echo esc_attr( $image_position_x . ' ' . $image_position ); ?>;" loading="eager" fetchpriority="high">
					<?php
				}
				?>
			</div>
		<?php endif; ?>

		<section class="urme-le-hero" aria-labelledby="urme-le-title">
			<div class="urme-le-hero-inner">
				<div class="urme-le-hero-heading">
					<h1 id="urme-le-title" class="urme-le-title"><?php echo esc_html( $title ); ?></h1>
					<?php if ( $badge_text ) : ?>
						<span class="urme-le-badge">
							<?php if ( $badge_icon ) : ?>
								<span class="urme-le-badge-icon-text" aria-hidden="true"><?php echo esc_html( $badge_icon ); ?></span>
							<?php else : ?>
								<svg class="urme-le-badge-icon" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
									<path d="M3 12a9 9 0 1 0 3-6.7"></path>
									<path d="M3 4v5h5"></path>
								</svg>
							<?php endif; ?>
							<?php echo esc_html( $badge_text ); ?>
						</span>
					<?php endif; ?>
				</div>
				<?php if ( $subtitle ) : ?>
					<p class="urme-le-subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>
		</section>
		</div>
		<?php
	}

	/**
	 * Landing routes are product archives, so WooCommerce prints the Shop page
	 * content ("Köp klockor online hos URME ...") on every one of them. That is
	 * the same text on every landing (duplicate content), and older releases
	 * also hid the landing's own intro whenever the Shop page had content, so
	 * no landing intro was ever shown. The Shop text belongs to the Shop page
	 * only: remove it on landing routes and show the landing intro instead.
	 */
	public static function remove_shop_description() {
		if ( ! URME_LE_Router::is_dynamic() ) {
			return;
		}

		if ( ! apply_filters( 'urme_le_remove_shop_description', true ) ) {
			return;
		}

		remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
		remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description', 10 );
	}

	public static function render_intro() {
		if ( ! URME_LE_Router::is_dynamic() ) {
			return;
		}

		// Like WooCommerce archive descriptions, show the intro on page 1 only so
		// paginated pages don't repeat the same text.
		$ctx = URME_LE_Router::context();
		if ( $ctx && $ctx['paged'] > 1 ) {
			return;
		}

		// Kept for sites that opt out of removing the Shop description: never
		// print two competing archive descriptions on the same page.
		if ( ! apply_filters( 'urme_le_remove_shop_description', true ) && self::has_native_archive_description() ) {
			return;
		}

		$config = URME_LE_Router::current_config();
		$intro  = $config && ! empty( $config['intro'] ) ? $config['intro'] : '';

		if ( ! $intro ) {
			$term = URME_LE_Router::current_child_term();
			if ( $term && ! empty( $term->description ) ) {
				$intro = $term->description;
			}
		}

		if ( ! $intro ) {
			return;
		}
		?>
		<div class="urme-le-intro-collapsible" data-collapsed="true">
			<div class="urme-le-intro" id="urme-le-intro-text"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
			<button type="button" class="urme-le-intro-toggle" aria-expanded="false" aria-controls="urme-le-intro-text">
				<span class="urme-le-intro-toggle-label" data-more="Läs mer" data-less="Visa mindre">Läs mer</span>
				<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M6 9l6 6 6-6"></path>
				</svg>
			</button>
		</div>
		<?php
	}
	/**
	 * Whether WooCommerce/WoodMart already owns a visible archive description.
	 *
	 * WooCommerce uses the Shop page content as the product-archive description.
	 * Landing Engine routes intentionally participate in that archive lifecycle, so
	 * a populated Shop page would otherwise be followed by the plugin intro too.
	 * Real product-taxonomy descriptions are also treated as native when the
	 * current query is actually a product taxonomy archive.
	 *
	 * @return bool
	 */
	private static function has_native_archive_description() {
		$has_native = false;

		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Term && self::has_meaningful_content( $queried->description ) ) {
				$has_native = true;
			}
		}

		if ( ! $has_native && function_exists( 'wc_get_page_id' ) ) {
			$shop_page_id = absint( wc_get_page_id( 'shop' ) );
			if ( $shop_page_id > 0 ) {
				$shop_page = get_post( $shop_page_id );
				if ( $shop_page instanceof WP_Post && 'publish' === $shop_page->post_status && self::has_meaningful_content( $shop_page->post_content ) ) {
					$has_native = true;
				}
			}
		}

		return (bool) apply_filters( 'urme_le_has_native_archive_description', $has_native );
	}

	/**
	 * Treat visible text, block markup or shortcode content as non-empty content.
	 *
	 * @param mixed $content Candidate archive content.
	 * @return bool
	 */
	private static function has_meaningful_content( $content ) {
		if ( ! is_string( $content ) ) {
			return false;
		}

		$content = trim( $content );
		if ( '' === $content ) {
			return false;
		}

		$plain = trim( wp_strip_all_tags( strip_shortcodes( $content ) ) );
		if ( '' !== $plain ) {
			return true;
		}

		// A shortcode/block may render meaningful content even if stripping it
		// leaves no plain text, so preserve native ownership in that case.
		return false !== strpos( $content, '[' ) || false !== strpos( $content, '<!-- wp:' );
	}

}
