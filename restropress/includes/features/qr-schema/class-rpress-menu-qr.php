<?php
/**
 * Menu QR code: settings section, generator UI and SVG/PNG download endpoint.
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menu QR code admin tool.
 */
class RPRESS_Menu_QR {

	/**
	 * Settings section slug under the General tab.
	 */
	const SECTION = 'qr_schema';

	/**
	 * admin-post.php action and nonce action.
	 */
	const ACTION = 'rpress_menu_qr';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rpress_settings_sections_general', array( __CLASS__, 'add_section' ) );
		add_filter( 'rpress_registered_settings', array( __CLASS__, 'add_settings' ) );
		add_action( 'rpress_menu_qr_generator', array( __CLASS__, 'render_generator' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'serve' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Add the "QR Code & SEO" section, before Developer API.
	 *
	 * @param array $sections General sections.
	 * @return array
	 */
	public static function add_section( $sections ) {
		$label = esc_html__( 'QR Code & SEO', 'restropress' );
		$out   = array();
		foreach ( (array) $sections as $key => $name ) {
			if ( 'developer_api' === $key ) {
				$out[ self::SECTION ] = $label;
			}
			$out[ $key ] = $name;
		}
		if ( ! isset( $out[ self::SECTION ] ) ) {
			$out[ self::SECTION ] = $label;
		}
		return $out;
	}

	/**
	 * Register the section fields.
	 *
	 * @param array $settings All settings.
	 * @return array
	 */
	public static function add_settings( $settings ) {
		if ( ! isset( $settings['general'] ) || ! is_array( $settings['general'] ) ) {
			return $settings;
		}
		$settings['general'][ self::SECTION ] = array(
			'menu_qr_header'      => array(
				'id'            => 'menu_qr_header',
				'name'          => '<h3>' . esc_html__( 'Menu QR code', 'restropress' ) . '</h3>',
				'desc'          => '',
				'type'          => 'header',
				'tooltip_title' => esc_html__( 'Menu QR code', 'restropress' ),
				'tooltip_desc'  => esc_html__( 'Print this code on table tents, flyers or the shop window. Guests scan it with their phone camera to open your online menu. The code is generated on your own server.', 'restropress' ),
			),
			'menu_qr_generator'   => array(
				'id'   => 'menu_qr_generator',
				'name' => esc_html__( 'QR code', 'restropress' ),
				'type' => 'hook',
			),
			'menu_schema_header'  => array(
				'id'            => 'menu_schema_header',
				'name'          => '<h3>' . esc_html__( 'Google rich results', 'restropress' ) . '</h3>',
				'desc'          => '',
				'type'          => 'header',
				'tooltip_title' => esc_html__( 'Google rich results', 'restropress' ),
				'tooltip_desc'  => esc_html__( 'RestroPress adds schema.org Restaurant and Menu data (JSON-LD) to your menu page so search engines can understand your dishes, prices and dietary labels.', 'restropress' ),
			),
			'disable_menu_schema' => array(
				'id'   => 'disable_menu_schema',
				'name' => esc_html__( 'Turn off menu schema', 'restropress' ),
				'desc' => RPRESS_Menu_Schema::settings_status_text(),
				'type' => 'checkbox',
			),
		);
		return $settings;
	}

	/**
	 * Whether the current admin screen is our settings section.
	 *
	 * @return bool
	 */
	private static function is_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		return 'rpress-settings' === $page && self::SECTION === $section;
	}

	/**
	 * Enqueue the generator script and style on our section only.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::is_screen() ) {
			return;
		}
		$base = RP_PLUGIN_URL . 'assets/features/qr-schema/';
		$dir  = RP_PLUGIN_DIR . 'assets/features/qr-schema/';
		wp_enqueue_style( 'rpress-menu-qr', $base . 'menu-qr.css', array(), RP_VERSION . '.' . (int) @filemtime( $dir . 'menu-qr.css' ) );
		wp_enqueue_script( 'rpress-menu-qr', $base . 'menu-qr.js', array(), RP_VERSION . '.' . (int) @filemtime( $dir . 'menu-qr.js' ), true );
	}

	/**
	 * Whether PNG output is available (GD).
	 *
	 * @return bool
	 */
	public static function png_supported() {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagepng' );
	}

	/**
	 * Build the link the QR code points to.
	 *
	 * @param int    $page_id Page ID (0 to use $url).
	 * @param string $url     Custom URL, used when $page_id is 0.
	 * @param string $table   Optional table label, added as a query arg.
	 * @return string Empty when no valid target.
	 */
	public static function target_url( $page_id, $url = '', $table = '' ) {
		$page_id = absint( $page_id );
		if ( $page_id ) {
			$target = 'page' === get_post_type( $page_id ) && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : '';
		} else {
			$target = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
		}
		if ( ! $target ) {
			return '';
		}
		$table = self::clean_table( $table );
		if ( '' !== $table ) {
			/**
			 * Query arg used for the table label in menu QR links.
			 *
			 * @since 3.5
			 * @param string $arg Default 'table'.
			 */
			$arg    = sanitize_key( apply_filters( 'rpress_menu_qr_table_arg', 'table' ) );
			$target = add_query_arg( $arg ? $arg : 'table', rawurlencode( $table ), $target );
		}
		/**
		 * Filter the URL encoded in a menu QR code.
		 *
		 * @since 3.5
		 * @param string $target  URL.
		 * @param int    $page_id Page ID or 0.
		 * @param string $table   Table label.
		 */
		return (string) apply_filters( 'rpress_menu_qr_url', $target, $page_id, $table );
	}

	/**
	 * Sanitize a table label.
	 *
	 * @param string $table Raw label.
	 * @return string
	 */
	public static function clean_table( $table ) {
		$table = trim( sanitize_text_field( (string) $table ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $table, 0, 40 ) : substr( $table, 0, 40 );
	}

	/**
	 * Render a QR code for a URL.
	 *
	 * @param string $url    URL to encode.
	 * @param string $format 'svg' or 'png'.
	 * @param array  $args   Caption args: 'label' (store name), 'sub' (table line).
	 * @return string|null Image bytes, or null when the format is unavailable.
	 */
	public static function render( $url, $format = 'svg', $args = array() ) {
		$qr = RPRESS_QR_Encoder::encode( $url, 'M' );
		if ( 'png' === $format ) {
			return RPRESS_QR_Encoder::to_png( $qr, array_merge( array( 'module' => 12 ), $args ) );
		}
		return RPRESS_QR_Encoder::to_svg( $qr, array_merge( array( 'title' => $url ), $args ) );
	}

	/**
	 * Generator UI (settings field of type "hook").
	 *
	 * @return void
	 */
	public static function render_generator() {
		$menu_page = absint( rpress_get_option( 'food_items_page', 0 ) );
		$pages     = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'post_title',
			)
		);
		$png       = self::png_supported();
		$default   = self::endpoint_url( $menu_page, '', '', true, 'svg', false );
		?>
		<div class="rpress-menu-qr" data-endpoint="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( self::ACTION ) ); ?>">
			<div class="rpress-menu-qr__controls">
				<p>
					<label for="rpress-menu-qr-page"><strong><?php esc_html_e( 'Link to', 'restropress' ); ?></strong></label><br>
					<select id="rpress-menu-qr-page">
						<?php foreach ( $pages as $page ) : ?>
							<option value="<?php echo esc_attr( $page->ID ); ?>" data-url="<?php echo esc_url( get_permalink( $page ) ); ?>" <?php selected( $menu_page, $page->ID ); ?>>
								<?php
								echo esc_html( $page->post_title ? $page->post_title : __( '(no title)', 'restropress' ) );
								if ( $menu_page === (int) $page->ID ) {
									echo ' ' . esc_html__( '(menu page)', 'restropress' );
								}
								?>
							</option>
						<?php endforeach; ?>
						<option value="custom" <?php selected( ! $menu_page ); ?>><?php esc_html_e( 'Custom URL...', 'restropress' ); ?></option>
					</select>
				</p>
				<p class="rpress-menu-qr__custom" <?php echo $menu_page ? 'hidden' : ''; ?>>
					<label for="rpress-menu-qr-url"><strong><?php esc_html_e( 'Custom URL', 'restropress' ); ?></strong></label><br>
					<input type="url" id="rpress-menu-qr-url" class="regular-text" placeholder="https://" value="">
				</p>
				<p>
					<label for="rpress-menu-qr-table"><strong><?php esc_html_e( 'Table label (optional)', 'restropress' ); ?></strong></label><br>
					<input type="text" id="rpress-menu-qr-table" class="regular-text" maxlength="40" placeholder="<?php esc_attr_e( 'e.g. 5', 'restropress' ); ?>" value="">
					<span class="description"><?php esc_html_e( 'Adds ?table=5 to the link and prints "Table 5" under the code. Make one code per table.', 'restropress' ); ?></span>
				</p>
				<p>
					<label><input type="checkbox" id="rpress-menu-qr-name" checked> <?php esc_html_e( 'Print the store name under the code', 'restropress' ); ?></label>
				</p>
				<p class="rpress-menu-qr__link"><?php esc_html_e( 'Opens:', 'restropress' ); ?> <code id="rpress-menu-qr-target"><?php echo esc_html( self::target_url( $menu_page ) ); ?></code></p>
				<p class="rpress-menu-qr__actions">
					<a class="button button-primary" id="rpress-menu-qr-svg" href="<?php echo esc_url( self::endpoint_url( $menu_page, '', '', true, 'svg', true ) ); ?>"><?php esc_html_e( 'Download SVG', 'restropress' ); ?></a>
					<?php if ( $png ) : ?>
						<a class="button" id="rpress-menu-qr-png" href="<?php echo esc_url( self::endpoint_url( $menu_page, '', '', true, 'png', true ) ); ?>"><?php esc_html_e( 'Download PNG', 'restropress' ); ?></a>
					<?php else : ?>
						<span class="description"><?php esc_html_e( 'PNG download needs the PHP GD extension; SVG works everywhere and prints sharper.', 'restropress' ); ?></span>
					<?php endif; ?>
				</p>
			</div>
			<div class="rpress-menu-qr__preview">
				<img id="rpress-menu-qr-preview" src="<?php echo esc_url( $default ); ?>" alt="<?php esc_attr_e( 'QR code preview', 'restropress' ); ?>" width="240">
			</div>
		</div>
		<?php
	}

	/**
	 * Endpoint URL for a QR image (used for the no-JS defaults).
	 *
	 * @param int    $page_id  Page ID.
	 * @param string $url      Custom URL.
	 * @param string $table    Table label.
	 * @param bool   $name     Print store name.
	 * @param string $format   svg|png.
	 * @param bool   $download Attachment disposition.
	 * @return string
	 */
	private static function endpoint_url( $page_id, $url, $table, $name, $format, $download ) {
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'page_id'  => $page_id ? absint( $page_id ) : 'custom',
				'url'      => rawurlencode( $url ),
				'table'    => rawurlencode( $table ),
				'name'     => $name ? 1 : 0,
				'format'   => $format,
				'download' => $download ? 1 : 0,
				'_wpnonce' => wp_create_nonce( self::ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * admin-post.php handler: stream the QR image.
	 *
	 * @return void
	 */
	public static function serve() {
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'restropress' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		$page_id  = isset( $_GET['page_id'] ) ? absint( wp_unslash( $_GET['page_id'] ) ) : 0;
		$url      = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ), array( 'http', 'https' ) ) : '';
		$table    = isset( $_GET['table'] ) ? self::clean_table( wp_unslash( $_GET['table'] ) ) : '';
		$name     = ! empty( $_GET['name'] );
		$format   = isset( $_GET['format'] ) && 'png' === $_GET['format'] ? 'png' : 'svg';
		$download = ! empty( $_GET['download'] );

		$target = self::target_url( $page_id, $url, $table );
		if ( '' === $target ) {
			wp_die( esc_html__( 'Choose a published page or enter a valid http(s) URL.', 'restropress' ), '', array( 'response' => 400 ) );
		}
		$args = array(
			'label' => $name ? rpress_qr_schema_store_name() : '',
			/* translators: %s: table label. */
			'sub'   => '' !== $table ? sprintf( __( 'Table %s', 'restropress' ), $table ) : '',
		);
		try {
			$data = self::render( $target, $format, $args );
		} catch ( InvalidArgumentException $e ) {
			$data = null;
		}
		if ( null === $data ) {
			wp_die( esc_html__( 'The QR code could not be generated.', 'restropress' ), '', array( 'response' => 500 ) );
		}

		$file = 'menu-qr' . ( '' !== $table ? '-table-' . sanitize_title( $table ) : '' ) . '.' . $format;
		nocache_headers();
		header( 'Content-Type: ' . ( 'png' === $format ? 'image/png' : 'image/svg+xml; charset=utf-8' ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="' . $file . '"' );
		header( 'Content-Length: ' . strlen( $data ) );
		echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary/SVG image built from escaped parts.
		exit;
	}
}
