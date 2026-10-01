<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class URME_LE_Settings {

	const OPTION_DEFAULT_IMAGE            = 'urme_le_default_hero_image_id';
	const OPTION_DEFAULT_IMAGE_POSITION   = 'urme_le_default_hero_image_position';
	const OPTION_DEFAULT_IMAGE_POSITION_X = 'urme_le_default_hero_image_position_x';
	const OPTION_BADGE_TEXT               = 'urme_le_trust_badge_text';
	const OPTION_BADGE_ICON               = 'urme_le_trust_badge_icon';
	const OPTION_MIN_PRODUCTS             = 'urme_le_min_products';
	const DEFAULT_MIN_PRODUCTS            = 3;
	const PAGE_SLUG                       = 'urme-le-settings';

	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'URME Landing settings', 'urme-landing-engine' ),
			__( 'Landing settings', 'urme-landing-engine' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function assets( $hook ) {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'urme-le-settings',
			URME_LE_URL . 'assets/settings.js',
			array( 'jquery' ),
			URME_LE_VERSION,
			true
		);

		wp_localize_script(
			'urme-le-settings',
			'urmeLeSettings',
			array(
				'chooseTitle'  => __( 'Select default hero image', 'urme-landing-engine' ),
				'chooseButton' => __( 'Use this image', 'urme-landing-engine' ),
			)
		);
	}

	public static function default_image_id() {
		return absint( get_option( self::OPTION_DEFAULT_IMAGE, 0 ) );
	}

	public static function default_image_position() {
		$value = sanitize_key( get_option( self::OPTION_DEFAULT_IMAGE_POSITION, 'center' ) );
		return in_array( $value, URME_LE_Landing_CPT::IMAGE_POSITIONS, true ) ? $value : 'center';
	}

	public static function default_image_position_x() {
		$value = sanitize_key( get_option( self::OPTION_DEFAULT_IMAGE_POSITION_X, 'center' ) );
		return in_array( $value, URME_LE_Landing_CPT::IMAGE_POSITIONS_X, true ) ? $value : 'center';
	}

	/**
	 * Fewest products a landing must show to be indexed and listed in the
	 * sitemap. Pages below it stay reachable but get noindex (thin content).
	 */
	public static function min_products() {
		$value = get_option( self::OPTION_MIN_PRODUCTS, self::DEFAULT_MIN_PRODUCTS );
		return max( 1, absint( $value ) );
	}

	public static function badge_text() {
		return (string) get_option( self::OPTION_BADGE_TEXT, '' );
	}

	public static function badge_icon() {
		return (string) get_option( self::OPTION_BADGE_ICON, '' );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_POST['urme_le_settings_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['urme_le_settings_nonce'] ) );
			if ( wp_verify_nonce( $nonce, 'urme_le_save_settings' ) ) {
				$image_id = isset( $_POST['urme_le_default_image_id'] ) ? absint( $_POST['urme_le_default_image_id'] ) : 0;
				update_option( self::OPTION_DEFAULT_IMAGE, $image_id );

				$image_position = isset( $_POST['urme_le_default_image_position'] ) ? sanitize_key( wp_unslash( $_POST['urme_le_default_image_position'] ) ) : 'center';
				$image_position = in_array( $image_position, URME_LE_Landing_CPT::IMAGE_POSITIONS, true ) ? $image_position : 'center';
				update_option( self::OPTION_DEFAULT_IMAGE_POSITION, $image_position );

				$image_position_x = isset( $_POST['urme_le_default_image_position_x'] ) ? sanitize_key( wp_unslash( $_POST['urme_le_default_image_position_x'] ) ) : 'center';
				$image_position_x = in_array( $image_position_x, URME_LE_Landing_CPT::IMAGE_POSITIONS_X, true ) ? $image_position_x : 'center';
				update_option( self::OPTION_DEFAULT_IMAGE_POSITION_X, $image_position_x );

				$badge_text = isset( $_POST['urme_le_trust_badge_text'] ) ? sanitize_text_field( wp_unslash( $_POST['urme_le_trust_badge_text'] ) ) : '';
				update_option( self::OPTION_BADGE_TEXT, $badge_text );

				$badge_icon = isset( $_POST['urme_le_trust_badge_icon'] ) ? sanitize_text_field( wp_unslash( $_POST['urme_le_trust_badge_icon'] ) ) : '';
				update_option( self::OPTION_BADGE_ICON, $badge_icon );

				$min_products = isset( $_POST['urme_le_min_products'] ) ? max( 1, absint( $_POST['urme_le_min_products'] ) ) : self::DEFAULT_MIN_PRODUCTS;
				update_option( self::OPTION_MIN_PRODUCTS, $min_products );

				// The minimum decides which routes the sitemap lists.
				URME_LE_Sitemap::invalidate();

				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'urme-landing-engine' ) . '</p></div>';
			}
		}

		$image_id       = self::default_image_id();
		$image_url      = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		$image_position   = self::default_image_position();
		$image_position_x = self::default_image_position_x();
		$badge_text       = self::badge_text();
		$badge_icon       = self::badge_icon();
		$min_products     = self::min_products();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'URME Landing settings', 'urme-landing-engine' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'urme_le_save_settings', 'urme_le_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Default hero image', 'urme-landing-engine' ); ?></th>
						<td>
							<div class="urme-le-default-image-preview" style="margin-bottom:10px;<?php echo $image_url ? '' : 'display:none;'; ?>">
								<img src="<?php echo esc_url( $image_url ); ?>" style="max-width:300px;height:auto;display:block;">
							</div>
							<input type="hidden" name="urme_le_default_image_id" id="urme_le_default_image_id" value="<?php echo esc_attr( $image_id ); ?>">
							<button type="button" class="button" id="urme_le_choose_default_image"><?php esc_html_e( 'Choose image', 'urme-landing-engine' ); ?></button>
							<button type="button" class="button" id="urme_le_remove_default_image" <?php echo $image_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove image', 'urme-landing-engine' ); ?></button>
							<p class="description"><?php esc_html_e( 'Used as the header/hero background on any landing page that has no Featured Image of its own.', 'urme-landing-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="urme_le_default_image_position"><?php esc_html_e( 'Default image position', 'urme-landing-engine' ); ?></label></th>
						<td>
							<select name="urme_le_default_image_position" id="urme_le_default_image_position">
								<option value="top" <?php selected( $image_position, 'top' ); ?>><?php esc_html_e( 'Top', 'urme-landing-engine' ); ?></option>
								<option value="center" <?php selected( $image_position, 'center' ); ?>><?php esc_html_e( 'Center', 'urme-landing-engine' ); ?></option>
								<option value="bottom" <?php selected( $image_position, 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'urme-landing-engine' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Which part of the default image stays visible when cropped to fit the short, wide hero banner. Each landing can override this under its own Featured Image setting.', 'urme-landing-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="urme_le_default_image_position_x"><?php esc_html_e( 'Default image horizontal position', 'urme-landing-engine' ); ?></label></th>
						<td>
							<select name="urme_le_default_image_position_x" id="urme_le_default_image_position_x">
								<option value="left" <?php selected( $image_position_x, 'left' ); ?>><?php esc_html_e( 'Left', 'urme-landing-engine' ); ?></option>
								<option value="center" <?php selected( $image_position_x, 'center' ); ?>><?php esc_html_e( 'Center', 'urme-landing-engine' ); ?></option>
								<option value="right" <?php selected( $image_position_x, 'right' ); ?>><?php esc_html_e( 'Right', 'urme-landing-engine' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'On narrow (mobile) screens the banner is much narrower, so a wide photo gets cropped left/right too. Set this if important content sits on one side of the photo.', 'urme-landing-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="urme_le_trust_badge_text"><?php esc_html_e( 'Trust badge text', 'urme-landing-engine' ); ?></label></th>
						<td>
							<input class="regular-text" type="text" name="urme_le_trust_badge_text" id="urme_le_trust_badge_text" value="<?php echo esc_attr( $badge_text ); ?>" placeholder="60 dagars öppet köp">
							<p class="description"><?php esc_html_e( 'Shown next to the H1 title on every landing page. Leave empty to hide it.', 'urme-landing-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="urme_le_trust_badge_icon"><?php esc_html_e( 'Trust badge icon', 'urme-landing-engine' ); ?></label></th>
						<td>
							<input type="text" name="urme_le_trust_badge_icon" id="urme_le_trust_badge_icon" value="<?php echo esc_attr( $badge_icon ); ?>" placeholder="↻" style="width:80px;font-size:18px;text-align:center;">
							<p class="description"><?php esc_html_e( 'A single character or emoji placed before the badge text (e.g. ↻ ⟳ ✓ ★). Leave empty to use the default refresh icon.', 'urme-landing-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="urme_le_min_products"><?php esc_html_e( 'Minimum products to index', 'urme-landing-engine' ); ?></label></th>
						<td>
							<input type="number" min="1" step="1" name="urme_le_min_products" id="urme_le_min_products" value="<?php echo esc_attr( $min_products ); ?>" style="width:80px;">
							<p class="description"><?php esc_html_e( 'Landing pages with fewer products stay visible to shoppers but get noindex and are left out of the sitemap, so Google does not see near-empty pages.', 'urme-landing-engine' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
