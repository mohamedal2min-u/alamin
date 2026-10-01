<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class URME_LE_Landing_CPT {

	const POST_TYPE = 'urme_landing';

	private static $meta_keys = array(
		'_urme_le_scope',
		'_urme_le_route_slug',
		'_urme_le_brand_slug',
		'_urme_le_filter_type',
		'_urme_le_taxonomy',
		'_urme_le_term_slugs',
		'_urme_le_h1',
		'_urme_le_subtitle',
		'_urme_le_seo_title',
		'_urme_le_meta_description',
		'_urme_le_intro',
		'_urme_le_image_position',
		'_urme_le_image_position_x',
	);

	const IMAGE_POSITIONS   = array( 'top', 'center', 'bottom' );
	const IMAGE_POSITIONS_X = array( 'left', 'center', 'right' );

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'URME Landings', 'urme-landing-engine' ),
			'singular_name'      => __( 'Landing', 'urme-landing-engine' ),
			'add_new'            => __( 'Add landing', 'urme-landing-engine' ),
			'add_new_item'       => __( 'Add landing', 'urme-landing-engine' ),
			'edit_item'          => __( 'Edit landing', 'urme-landing-engine' ),
			'new_item'           => __( 'New landing', 'urme-landing-engine' ),
			'view_item'          => __( 'View landing', 'urme-landing-engine' ),
			'search_items'       => __( 'Search landings', 'urme-landing-engine' ),
			'not_found'          => __( 'No landings found.', 'urme-landing-engine' ),
			'not_found_in_trash' => __( 'No landings found in Trash.', 'urme-landing-engine' ),
			'menu_name'          => __( 'URME Landings', 'urme-landing-engine' ),
		);

		$cap = 'manage_woocommerce';
		$capabilities = array(
			'edit_post'              => $cap,
			'read_post'              => $cap,
			'delete_post'            => $cap,
			'edit_posts'             => $cap,
			'edit_others_posts'      => $cap,
			'publish_posts'          => $cap,
			'read_private_posts'     => $cap,
			'delete_posts'           => $cap,
			'delete_private_posts'   => $cap,
			'delete_published_posts' => $cap,
			'delete_others_posts'    => $cap,
			'edit_private_posts'     => $cap,
			'edit_published_posts'   => $cap,
			'create_posts'           => $cap,
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'woocommerce',
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-layout',
				'supports'            => array( 'title', 'thumbnail' ),
				'capabilities'        => $capabilities,
				'map_meta_cap'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}

	public static function register_meta() {
		foreach ( self::$meta_keys as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => array( __CLASS__, 'sanitize_meta_value' ),
					'auth_callback'     => function() {
						return current_user_can( 'manage_woocommerce' );
					},
				)
			);
		}
	}

	public static function sanitize_meta_value( $value, $meta_key = '' ) {
		if ( '_urme_le_intro' === $meta_key ) {
			return wp_kses_post( $value );
		}

		if ( '_urme_le_meta_description' === $meta_key ) {
			return sanitize_textarea_field( $value );
		}

		if ( '_urme_le_image_position' === $meta_key ) {
			$value = sanitize_key( $value );
			return in_array( $value, self::IMAGE_POSITIONS, true ) ? $value : 'center';
		}

		if ( '_urme_le_image_position_x' === $meta_key ) {
			$value = sanitize_key( $value );
			return in_array( $value, self::IMAGE_POSITIONS_X, true ) ? $value : 'center';
		}

		return sanitize_text_field( $value );
	}

	public static function admin_hooks() {
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'urme-le-settings',
			__( 'Landing settings', 'urme-landing-engine' ),
			array( __CLASS__, 'render_settings_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'urme-le-seo',
			__( 'SEO & content', 'urme-landing-engine' ),
			array( __CLASS__, 'render_seo_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	private static function field( $post_id, $key, $default = '' ) {
		$value = get_post_meta( $post_id, $key, true );
		return '' !== $value ? $value : $default;
	}

	public static function render_settings_box( $post ) {
		wp_nonce_field( 'urme_le_save_landing', 'urme_le_nonce' );

		$scope       = self::field( $post->ID, '_urme_le_scope', 'global' );
		$route_slug  = self::field( $post->ID, '_urme_le_route_slug' );
		$brand_slug  = self::field( $post->ID, '_urme_le_brand_slug' );
		$filter_type = self::field( $post->ID, '_urme_le_filter_type', 'attribute' );
		$taxonomy    = self::field( $post->ID, '_urme_le_taxonomy' );
		$term_slugs  = self::field( $post->ID, '_urme_le_term_slugs' );
		$image_pos   = self::field( $post->ID, '_urme_le_image_position', 'center' );
		$image_pos_x = self::field( $post->ID, '_urme_le_image_position_x', 'center' );
		?>
		<table class="form-table urme-le-table" role="presentation">
			<tr>
				<th scope="row"><label for="urme_le_scope"><?php esc_html_e( 'Scope', 'urme-landing-engine' ); ?></label></th>
				<td>
					<select name="urme_le_scope" id="urme_le_scope">
						<option value="global" <?php selected( $scope, 'global' ); ?>><?php esc_html_e( 'Global: /klockor/{slug}/', 'urme-landing-engine' ); ?></option>
						<option value="brand" <?php selected( $scope, 'brand' ); ?>><?php esc_html_e( 'Brand: /marken/{brand}/{slug}/', 'urme-landing-engine' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="urme_le_route_slug"><?php esc_html_e( 'URL slug', 'urme-landing-engine' ); ?></label></th>
				<td><input class="regular-text" type="text" name="urme_le_route_slug" id="urme_le_route_slug" value="<?php echo esc_attr( $route_slug ); ?>" placeholder="automatiska"></td>
			</tr>
			<tr class="urme-le-brand-row">
				<th scope="row"><label for="urme_le_brand_slug"><?php esc_html_e( 'Brand slug', 'urme-landing-engine' ); ?></label></th>
				<td>
					<input class="regular-text" type="text" name="urme_le_brand_slug" id="urme_le_brand_slug" value="<?php echo esc_attr( $brand_slug ); ?>" placeholder="seiko">
					<p class="description"><?php esc_html_e( 'Required only for Brand scope. Example: seiko, tissot.', 'urme-landing-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="urme_le_filter_type"><?php esc_html_e( 'Filter type', 'urme-landing-engine' ); ?></label></th>
				<td>
					<select name="urme_le_filter_type" id="urme_le_filter_type">
						<option value="sale" <?php selected( $filter_type, 'sale' ); ?>><?php esc_html_e( 'WooCommerce sale', 'urme-landing-engine' ); ?></option>
						<option value="category" <?php selected( $filter_type, 'category' ); ?>><?php esc_html_e( 'Product category', 'urme-landing-engine' ); ?></option>
						<option value="attribute" <?php selected( $filter_type, 'attribute' ); ?>><?php esc_html_e( 'Product attribute / taxonomy', 'urme-landing-engine' ); ?></option>
					</select>
				</td>
			</tr>
			<tr class="urme-le-tax-row">
				<th scope="row"><label for="urme_le_taxonomy"><?php esc_html_e( 'Taxonomy', 'urme-landing-engine' ); ?></label></th>
				<td>
					<input class="regular-text" type="text" name="urme_le_taxonomy" id="urme_le_taxonomy" value="<?php echo esc_attr( $taxonomy ); ?>" placeholder="pa_urverkstyp">
					<p class="description"><?php esc_html_e( 'For category use product_cat. For attributes use the full taxonomy, e.g. pa_urverkstyp or pa_boettfarg.', 'urme-landing-engine' ); ?></p>
				</td>
			</tr>
			<tr class="urme-le-tax-row">
				<th scope="row"><label for="urme_le_term_slugs"><?php esc_html_e( 'Term slugs', 'urme-landing-engine' ); ?></label></th>
				<td>
					<input class="large-text" type="text" name="urme_le_term_slugs" id="urme_le_term_slugs" value="<?php echo esc_attr( $term_slugs ); ?>" placeholder="automatisk, automatiskt">
					<p class="description"><?php esc_html_e( 'Comma-separated. Matching is OR inside this filter, then AND with the selected brand when scope is Brand.', 'urme-landing-engine' ); ?></p>
				</td>
			</tr>
		</table>
		<p><strong><?php esc_html_e( 'Hero image:', 'urme-landing-engine' ); ?></strong> <?php esc_html_e( 'Use the Featured Image box. This image is used as the landing header image.', 'urme-landing-engine' ); ?></p>
		<table class="form-table urme-le-table" role="presentation">
			<tr>
				<th scope="row"><label for="urme_le_image_position"><?php esc_html_e( 'Hero image vertical position', 'urme-landing-engine' ); ?></label></th>
				<td>
					<select name="urme_le_image_position" id="urme_le_image_position">
						<option value="top" <?php selected( $image_pos, 'top' ); ?>><?php esc_html_e( 'Top', 'urme-landing-engine' ); ?></option>
						<option value="center" <?php selected( $image_pos, 'center' ); ?>><?php esc_html_e( 'Center', 'urme-landing-engine' ); ?></option>
						<option value="bottom" <?php selected( $image_pos, 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'urme-landing-engine' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'The hero banner is short and wide, so tall photos get cropped top/bottom. Use this to choose which part stays visible.', 'urme-landing-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="urme_le_image_position_x"><?php esc_html_e( 'Hero image horizontal position', 'urme-landing-engine' ); ?></label></th>
				<td>
					<select name="urme_le_image_position_x" id="urme_le_image_position_x">
						<option value="left" <?php selected( $image_pos_x, 'left' ); ?>><?php esc_html_e( 'Left', 'urme-landing-engine' ); ?></option>
						<option value="center" <?php selected( $image_pos_x, 'center' ); ?>><?php esc_html_e( 'Center', 'urme-landing-engine' ); ?></option>
						<option value="right" <?php selected( $image_pos_x, 'right' ); ?>><?php esc_html_e( 'Right', 'urme-landing-engine' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'On narrow (mobile) screens the banner is much narrower than on desktop, so a wide photo gets cropped left/right too. If important content (like a logo) sits on one side of the photo, set that side here so it never gets cropped off.', 'urme-landing-engine' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function render_seo_box( $post ) {
		$h1          = self::field( $post->ID, '_urme_le_h1' );
		$subtitle    = self::field( $post->ID, '_urme_le_subtitle' );
		$seo_title   = self::field( $post->ID, '_urme_le_seo_title' );
		$description = self::field( $post->ID, '_urme_le_meta_description' );
		$intro       = self::field( $post->ID, '_urme_le_intro' );
		?>
		<table class="form-table urme-le-table" role="presentation">
			<tr>
				<th scope="row"><label for="urme_le_h1"><?php esc_html_e( 'H1 / Hero title', 'urme-landing-engine' ); ?></label></th>
				<td><input class="large-text" type="text" name="urme_le_h1" id="urme_le_h1" value="<?php echo esc_attr( $h1 ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="urme_le_subtitle"><?php esc_html_e( 'Hero subtitle', 'urme-landing-engine' ); ?></label></th>
				<td><input class="large-text" type="text" name="urme_le_subtitle" id="urme_le_subtitle" value="<?php echo esc_attr( $subtitle ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="urme_le_seo_title"><?php esc_html_e( 'SEO title', 'urme-landing-engine' ); ?></label></th>
				<td><input class="large-text" type="text" name="urme_le_seo_title" id="urme_le_seo_title" value="<?php echo esc_attr( $seo_title ); ?>" placeholder="Automatiska klockor | Köp online hos URME"></td>
			</tr>
			<tr>
				<th scope="row"><label for="urme_le_meta_description"><?php esc_html_e( 'Meta description', 'urme-landing-engine' ); ?></label></th>
				<td><textarea class="large-text" rows="3" name="urme_le_meta_description" id="urme_le_meta_description"><?php echo esc_textarea( $description ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="urme_le_intro"><?php esc_html_e( 'Intro text', 'urme-landing-engine' ); ?></label></th>
				<td><textarea class="large-text" rows="6" name="urme_le_intro" id="urme_le_intro"><?php echo esc_textarea( $intro ); ?></textarea><p class="description"><?php esc_html_e( 'Basic safe HTML is allowed.', 'urme-landing-engine' ); ?></p></td>
			</tr>
		</table>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! isset( $_POST['urme_le_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['urme_le_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'urme_le_save_landing' ) ) {
			return;
		}

		$scope = isset( $_POST['urme_le_scope'] ) ? sanitize_key( wp_unslash( $_POST['urme_le_scope'] ) ) : 'global';
		$scope = in_array( $scope, array( 'global', 'brand' ), true ) ? $scope : 'global';

		$filter_type = isset( $_POST['urme_le_filter_type'] ) ? sanitize_key( wp_unslash( $_POST['urme_le_filter_type'] ) ) : 'attribute';
		$filter_type = in_array( $filter_type, array( 'sale', 'category', 'attribute' ), true ) ? $filter_type : 'attribute';

		$image_position = isset( $_POST['urme_le_image_position'] ) ? sanitize_key( wp_unslash( $_POST['urme_le_image_position'] ) ) : 'center';
		$image_position = in_array( $image_position, self::IMAGE_POSITIONS, true ) ? $image_position : 'center';

		$image_position_x = isset( $_POST['urme_le_image_position_x'] ) ? sanitize_key( wp_unslash( $_POST['urme_le_image_position_x'] ) ) : 'center';
		$image_position_x = in_array( $image_position_x, self::IMAGE_POSITIONS_X, true ) ? $image_position_x : 'center';

		$values = array(
			'_urme_le_scope'            => $scope,
			'_urme_le_route_slug'       => isset( $_POST['urme_le_route_slug'] ) ? sanitize_title( wp_unslash( $_POST['urme_le_route_slug'] ) ) : '',
			'_urme_le_brand_slug'       => isset( $_POST['urme_le_brand_slug'] ) ? sanitize_title( wp_unslash( $_POST['urme_le_brand_slug'] ) ) : '',
			'_urme_le_filter_type'      => $filter_type,
			'_urme_le_taxonomy'         => isset( $_POST['urme_le_taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['urme_le_taxonomy'] ) ) : '',
			'_urme_le_term_slugs'       => isset( $_POST['urme_le_term_slugs'] ) ? self::sanitize_term_list( wp_unslash( $_POST['urme_le_term_slugs'] ) ) : '',
			'_urme_le_h1'               => isset( $_POST['urme_le_h1'] ) ? sanitize_text_field( wp_unslash( $_POST['urme_le_h1'] ) ) : '',
			'_urme_le_subtitle'         => isset( $_POST['urme_le_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['urme_le_subtitle'] ) ) : '',
			'_urme_le_seo_title'        => isset( $_POST['urme_le_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['urme_le_seo_title'] ) ) : '',
			'_urme_le_meta_description' => isset( $_POST['urme_le_meta_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['urme_le_meta_description'] ) ) : '',
			'_urme_le_intro'            => isset( $_POST['urme_le_intro'] ) ? wp_kses_post( wp_unslash( $_POST['urme_le_intro'] ) ) : '',
			'_urme_le_image_position'   => $image_position,
			'_urme_le_image_position_x' => $image_position_x,
		);

		foreach ( $values as $key => $value ) {
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		delete_transient( 'urme_le_landing_map' );
	}

	private static function sanitize_term_list( $value ) {
		$parts = preg_split( '/\s*,\s*/', (string) $value );
		$parts = array_filter( array_map( 'sanitize_title', $parts ) );
		$parts = array_values( array_unique( $parts ) );
		return implode( ',', $parts );
	}

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['urme_route'] = __( 'Route', 'urme-landing-engine' );
				$new['urme_filter'] = __( 'Filter', 'urme-landing-engine' );
			}
		}
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		if ( 'urme_route' === $column ) {
			$scope      = get_post_meta( $post_id, '_urme_le_scope', true );
			$route_slug = get_post_meta( $post_id, '_urme_le_route_slug', true );
			$brand_slug = get_post_meta( $post_id, '_urme_le_brand_slug', true );
			if ( 'brand' === $scope ) {
				echo '<code>/marken/' . esc_html( $brand_slug ?: '{brand}' ) . '/' . esc_html( $route_slug ) . '/</code>';
			} else {
				echo '<code>/klockor/' . esc_html( $route_slug ) . '/</code>';
			}
		}

		if ( 'urme_filter' === $column ) {
			$type     = get_post_meta( $post_id, '_urme_le_filter_type', true );
			$taxonomy = get_post_meta( $post_id, '_urme_le_taxonomy', true );
			$terms    = get_post_meta( $post_id, '_urme_le_term_slugs', true );
			echo esc_html( $type );
			if ( $taxonomy ) {
				echo '<br><code>' . esc_html( $taxonomy ) . '</code>';
			}
			if ( $terms ) {
				echo '<br>' . esc_html( $terms );
			}
		}
	}

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'urme-le-admin',
			URME_LE_URL . 'assets/admin.js',
			array(),
			URME_LE_VERSION,
			true
		);
	}

	public static function get_landing_map() {
		$cached = get_transient( 'urme_le_landing_map' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$map = array(
			'global' => array(),
			'brand'  => array(),
		);

		$posts = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $posts as $post ) {
			$config = self::config_from_post( $post );
			if ( ! $config || empty( $config['route_slug'] ) ) {
				continue;
			}

			if ( 'brand' === $config['scope'] ) {
				if ( empty( $config['brand_slug'] ) ) {
					continue;
				}
				$key = $config['brand_slug'] . ':' . $config['route_slug'];
				$map['brand'][ $key ] = $config;
			} else {
				$map['global'][ $config['route_slug'] ] = $config;
			}
		}

		set_transient( 'urme_le_landing_map', $map, HOUR_IN_SECONDS );
		return $map;
	}

	public static function config_from_post( $post ) {
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$term_string = (string) get_post_meta( $post->ID, '_urme_le_term_slugs', true );
		$terms = array_filter( array_map( 'sanitize_title', explode( ',', $term_string ) ) );

		return array(
			'id'               => (int) $post->ID,
			'scope'            => get_post_meta( $post->ID, '_urme_le_scope', true ) ?: 'global',
			'route_slug'       => sanitize_title( get_post_meta( $post->ID, '_urme_le_route_slug', true ) ),
			'brand_slug'       => sanitize_title( get_post_meta( $post->ID, '_urme_le_brand_slug', true ) ),
			'filter_type'      => sanitize_key( get_post_meta( $post->ID, '_urme_le_filter_type', true ) ),
			'taxonomy'         => sanitize_key( get_post_meta( $post->ID, '_urme_le_taxonomy', true ) ),
			'term_slugs'       => array_values( array_unique( $terms ) ),
			'h1'               => (string) get_post_meta( $post->ID, '_urme_le_h1', true ),
			'subtitle'         => (string) get_post_meta( $post->ID, '_urme_le_subtitle', true ),
			'seo_title'        => (string) get_post_meta( $post->ID, '_urme_le_seo_title', true ),
			'meta_description' => (string) get_post_meta( $post->ID, '_urme_le_meta_description', true ),
			'intro'            => (string) get_post_meta( $post->ID, '_urme_le_intro', true ),
			'image_id'         => (int) get_post_thumbnail_id( $post->ID ),
			'image_position'   => self::sanitize_meta_value( get_post_meta( $post->ID, '_urme_le_image_position', true ), '_urme_le_image_position' ),
			'image_position_x' => self::sanitize_meta_value( get_post_meta( $post->ID, '_urme_le_image_position_x', true ), '_urme_le_image_position_x' ),
		);
	}

	public static function maybe_upgrade() {
		$installed_version = (string) get_option( 'urme_le_version', '1.0.30' );

		if ( version_compare( $installed_version, '1.0.34', '<' ) ) {
			self::migrate_1_0_34();
		}

		if ( version_compare( $installed_version, '1.0.35', '<' ) ) {
			self::migrate_1_0_35();
		}

		if ( URME_LE_VERSION !== $installed_version ) {
			update_option( 'urme_le_version', URME_LE_VERSION, false );
		}
	}

	private static function migrate_1_0_34() {
		if ( ! taxonomy_exists( 'pa_urverkstyp' ) || ! get_term_by( 'slug', 'automatic', 'pa_urverkstyp' ) ) {
			return;
		}

		$landing_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_urme_le_route_slug',
				'meta_value'     => 'automatiska',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$legacy_slugs = array( 'automatisk', 'automatiskt' );

		foreach ( $landing_ids as $landing_id ) {
			if ( 'global' !== ( get_post_meta( $landing_id, '_urme_le_scope', true ) ?: 'global' ) ) {
				continue;
			}
			if ( 'attribute' !== get_post_meta( $landing_id, '_urme_le_filter_type', true ) ) {
				continue;
			}
			if ( 'pa_urverkstyp' !== get_post_meta( $landing_id, '_urme_le_taxonomy', true ) ) {
				continue;
			}

			$stored = array_values( array_filter( array_map( 'sanitize_title', explode( ',', (string) get_post_meta( $landing_id, '_urme_le_term_slugs', true ) ) ) ) );
			$custom = array_diff( $stored, $legacy_slugs );

			if ( $stored && empty( $custom ) ) {
				update_post_meta( $landing_id, '_urme_le_term_slugs', 'automatic' );
			}
		}

		delete_transient( 'urme_le_landing_map' );
	}


	/**
	 * Restore the complete known automatic-movement alias set after 1.0.34.
	 * 1.0.34 narrowed the seeded Automatiska landing to `automatic`; the live
	 * catalogue also contains the established Swedish `automatisk` term. Only
	 * untouched/default alias-only configurations are expanded here. Any custom
	 * taxonomy or unrelated term configuration is preserved exactly.
	 */
	private static function migrate_1_0_35() {
		if ( ! taxonomy_exists( 'pa_urverkstyp' ) ) {
			return;
		}

		$candidates = array( 'automatic', 'automatisk', 'automatiskt' );
		$available  = array_values(
			array_filter(
				$candidates,
				static function ( $slug ) {
					$term = get_term_by( 'slug', $slug, 'pa_urverkstyp' );
					return $term && ! is_wp_error( $term );
				}
			)
		);

		if ( ! $available ) {
			return;
		}

		$landing_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_urme_le_route_slug',
				'meta_value'     => 'automatiska',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $landing_ids as $landing_id ) {
			if ( 'global' !== ( get_post_meta( $landing_id, '_urme_le_scope', true ) ?: 'global' ) ) {
				continue;
			}
			if ( 'attribute' !== get_post_meta( $landing_id, '_urme_le_filter_type', true ) ) {
				continue;
			}
			if ( 'pa_urverkstyp' !== get_post_meta( $landing_id, '_urme_le_taxonomy', true ) ) {
				continue;
			}

			$stored = array_values( array_filter( array_map( 'sanitize_title', explode( ',', (string) get_post_meta( $landing_id, '_urme_le_term_slugs', true ) ) ) ) );
			$stored_sorted = $stored;
			sort( $stored_sorted );

			$legacy_default = array( 'automatisk', 'automatiskt' );
			sort( $legacy_default );

			$is_1_0_34_default = array( 'automatic' ) === $stored_sorted;
			$is_legacy_default = $legacy_default === $stored_sorted;

			if ( ! $is_1_0_34_default && ! $is_legacy_default ) {
				continue;
			}

			update_post_meta( $landing_id, '_urme_le_term_slugs', implode( ',', $available ) );
		}

		delete_transient( 'urme_le_landing_map' );
	}

	public static function seed_defaults() {
		$defaults = array(
			array(
				'title'       => 'Rea',
				'route_slug'  => 'rea',
				'filter_type' => 'sale',
				'h1'          => 'Klockor på rea',
				'subtitle'    => 'Utvalda klockor till nedsatta priser',
				'seo_title'   => 'Klockor på rea | Köp online hos URME',
				'description' => 'Upptäck klockor på rea hos URME. Handla herrklockor och damklockor från populära märken till nedsatta priser online.',
			),
			array(
				'title'       => 'Automatiska klockor',
				'route_slug'  => 'automatiska',
				'filter_type' => 'attribute',
				'taxonomy'    => 'pa_urverkstyp',
				'terms'       => 'automatic,automatisk,automatiskt',
				'h1'          => 'Automatiska klockor',
				'subtitle'    => 'Mekaniska klockor som drivs av din rörelse',
				'seo_title'   => 'Automatiska klockor | Köp online hos URME',
				'description' => 'Upptäck automatiska klockor hos URME. Jämför modeller, märken, storlekar och design och hitta rätt automatisk klocka för dig.',
			),
			array(
				'title'       => 'Guldklockor',
				'route_slug'  => 'guld',
				'filter_type' => 'attribute',
				'taxonomy'    => 'pa_boettfarg',
				'terms'       => 'guld,guldtonad,gulguld,gulguldtonad',
				'h1'          => 'Guldklockor',
				'subtitle'    => 'Klockor i eleganta guldtoner',
				'seo_title'   => 'Guldklockor | Köp online hos URME',
				'description' => 'Upptäck guldklockor hos URME. Jämför modeller i guld och guldtoner från populära varumärken och hitta din favorit online.',
			),
			array(
				'title'       => 'Silverklockor',
				'route_slug'  => 'silver',
				'filter_type' => 'attribute',
				'taxonomy'    => 'pa_boettfarg',
				'terms'       => 'silver',
				'h1'          => 'Silverklockor',
				'subtitle'    => 'Tidlösa klockor i silverton',
				'seo_title'   => 'Silverklockor | Köp online hos URME',
				'description' => 'Upptäck silverklockor hos URME. Jämför modeller i silverton från populära varumärken och hitta rätt klocka för din stil.',
			),
			array(
				'title'       => 'Rektangulära klockor',
				'route_slug'  => 'rektangulara',
				'filter_type' => 'attribute',
				'taxonomy'    => 'pa_boettform',
				'terms'       => 'rektangular',
				'h1'          => 'Rektangulära klockor',
				'subtitle'    => 'Distinkt design med rektangulär boett',
				'seo_title'   => 'Rektangulära klockor | Köp online hos URME',
				'description' => 'Upptäck rektangulära klockor hos URME. Jämför eleganta modeller med rektangulär boett och hitta en klocka som passar din stil.',
				'status'      => 'draft',
			),
		);

		foreach ( $defaults as $item ) {
			$existing = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'meta_key'       => '_urme_le_route_slug',
					'meta_value'     => $item['route_slug'],
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			if ( $existing ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => isset( $item['status'] ) ? $item['status'] : 'publish',
					'post_title'  => $item['title'],
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, '_urme_le_scope', 'global' );
			update_post_meta( $post_id, '_urme_le_route_slug', $item['route_slug'] );
			update_post_meta( $post_id, '_urme_le_filter_type', $item['filter_type'] );
			update_post_meta( $post_id, '_urme_le_taxonomy', isset( $item['taxonomy'] ) ? $item['taxonomy'] : '' );
			update_post_meta( $post_id, '_urme_le_term_slugs', isset( $item['terms'] ) ? $item['terms'] : '' );
			update_post_meta( $post_id, '_urme_le_h1', $item['h1'] );
			update_post_meta( $post_id, '_urme_le_subtitle', $item['subtitle'] );
			update_post_meta( $post_id, '_urme_le_seo_title', $item['seo_title'] );
			update_post_meta( $post_id, '_urme_le_meta_description', $item['description'] );
		}

		delete_transient( 'urme_le_landing_map' );
	}
}
