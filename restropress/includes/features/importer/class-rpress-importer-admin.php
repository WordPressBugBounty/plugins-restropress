<?php
/**
 * Import framework: admin screen and AJAX endpoints.
 *
 * Screen: Menu Items > Switch to RestroPress (edit.php?post_type=fooditem&page=rpress-switch),
 * registered hidden like the Import Menu / Export Menu pages and also listed
 * under Tools > Import. Every AJAX step checks the nonce and edit_products;
 * store settings and customers additionally need manage_shop_settings.
 *
 * @package RestroPress/Importer
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin glue.
 */
class RPress_Importer_Admin {

	const PAGE       = 'rpress-switch';
	const NONCE      = 'rpress_importer';
	const CAPABILITY = 'edit_products';

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	protected static $hook = '';

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_wp_importer' ) );
		add_action( 'load-importer-restropress-switch', array( __CLASS__, 'redirect_from_tools' ) );

		foreach ( array( 'parse', 'start', 'step', 'undo', 'undo_step', 'summary', 'discard' ) as $action ) {
			add_action( 'wp_ajax_rpress_importer_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}
	}

	/**
	 * Hidden page under Menu Items, with its admin title set on load (the
	 * removed submenu entry would otherwise leave the title empty).
	 *
	 * @return void
	 */
	public static function register_page() {
		$title      = __( 'Switch to RestroPress', 'restropress' );
		self::$hook = add_submenu_page( 'edit.php?post_type=fooditem', $title, $title, self::CAPABILITY, self::PAGE, array( __CLASS__, 'render' ) );
		remove_submenu_page( 'edit.php?post_type=fooditem', self::PAGE );
		if ( self::$hook ) {
			add_action(
				'load-' . self::$hook,
				function () use ( $title ) {
					$GLOBALS['title'] = $title;
					RPress_Importer_Engine::cleanup_stale_previews();
				}
			);
		}
	}

	/**
	 * List the screen under Tools > Import.
	 *
	 * @return void
	 */
	public static function register_wp_importer() {
		if ( defined( 'WP_LOAD_IMPORTERS' ) && function_exists( 'register_importer' ) ) {
			register_importer(
				'restropress-switch',
				__( 'Switch to RestroPress', 'restropress' ),
				__( 'Import your menu from GloriaFood, WooCommerce ordering plugins (Orderable, WP Cafe, FoodMaster) or a spreadsheet into RestroPress.', 'restropress' ),
				array( __CLASS__, 'render_wp_importer' )
			);
		}
	}

	/**
	 * Tools > Import > Switch to RestroPress: go to the real screen.
	 *
	 * @return void
	 */
	public static function redirect_from_tools() {
		if ( current_user_can( self::CAPABILITY ) ) {
			wp_safe_redirect( rpress_importer_url() );
			exit;
		}
	}

	/**
	 * Fallback when the redirect did not happen.
	 *
	 * @return void
	 */
	public static function render_wp_importer() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Switch to RestroPress', 'restropress' ) . '</h1><p><a class="button button-primary" href="' . esc_url( rpress_importer_url() ) . '">' . esc_html__( 'Open the importer', 'restropress' ) . '</a></p></div>';
	}

	/**
	 * Whether this is the importer screen.
	 *
	 * @return bool
	 */
	protected static function is_screen() {
		return isset( $_GET['page'] ) && self::PAGE === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Assets, only on this screen.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::is_screen() ) {
			return;
		}
		$base    = RP_PLUGIN_DIR . 'assets/features/importer/';
		$url     = RP_PLUGIN_URL . 'assets/features/importer/';
		$css_ver = file_exists( $base . 'importer.css' ) ? filemtime( $base . 'importer.css' ) : RP_VERSION;
		$js_ver  = file_exists( $base . 'importer.js' ) ? filemtime( $base . 'importer.js' ) : RP_VERSION;
		wp_enqueue_style( 'rpress-importer', $url . 'importer.css', array(), $css_ver );
		wp_enqueue_script( 'rpress-importer', $url . 'importer.js', array(), $js_ver, true );
		wp_localize_script( 'rpress-importer', 'rpressImporter', self::script_data() );
	}

	/**
	 * Data for importer.js.
	 *
	 * @return array
	 */
	protected static function script_data() {
		return array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( self::NONCE ),
			'maxUpload'   => rpress_importer_max_upload_size(),
			'menuUrl'     => admin_url( 'edit.php?post_type=fooditem' ),
			'canSettings' => current_user_can( 'manage_shop_settings' ),
			'i18n'        => array(
				'chooseFile'     => __( 'Choose a file to upload.', 'restropress' ),
				'tooBig'         => __( 'This file is larger than the upload limit.', 'restropress' ),
				'reading'        => __( 'Reading your menu...', 'restropress' ),
				'failed'         => __( 'Something went wrong. Please try again.', 'restropress' ),
				'importing'      => __( 'Importing', 'restropress' ),
				'undoing'        => __( 'Undoing the import...', 'restropress' ),
				'undoConfirm'    => __( 'Delete everything this import created and restore what it changed? Items you edited after the import are deleted too.', 'restropress' ),
				'replaceConfirm' => __( 'Replace mode moves every current menu item to the trash before importing. Continue?', 'restropress' ),
				'create'         => __( 'Create', 'restropress' ),
				'skip'           => __( 'Skip (same name)', 'restropress' ),
				'update'         => __( 'Update existing', 'restropress' ),
				'draft'          => __( 'draft', 'restropress' ),
				'yes'            => __( 'Yes', 'restropress' ),
				'none'           => __( 'None', 'restropress' ),
				/* translators: %d: number of menu items to import (always more than one) */
				'importN'        => __( 'Import %d items', 'restropress' ),
				'importOne'      => __( 'Import 1 item', 'restropress' ),
				'importOnly'     => __( 'Import', 'restropress' ),
				'done'           => __( 'Import complete', 'restropress' ),
				'undone'         => __( 'Import undone', 'restropress' ),
				'undo'           => __( 'Undo this import', 'restropress' ),
				'counts'         => array(
					'categories' => __( 'Categories', 'restropress' ),
					'items'      => __( 'Items', 'restropress' ),
					'sizes'      => __( 'Sizes', 'restropress' ),
					'groups'     => __( 'Add-on groups', 'restropress' ),
					'options'    => __( 'Add-ons', 'restropress' ),
					'images'     => __( 'Images', 'restropress' ),
					'customers'  => __( 'Customers', 'restropress' ),
					'warnings'   => __( 'Warnings', 'restropress' ),
				),
				'result'         => array(
					'created'        => __( 'Items created', 'restropress' ),
					'updated'        => __( 'Items updated', 'restropress' ),
					'skipped'        => __( 'Items skipped', 'restropress' ),
					'failed'         => __( 'Items failed', 'restropress' ),
					'trashed'        => __( 'Old items trashed', 'restropress' ),
					'terms'          => __( 'Categories, add-ons and labels created', 'restropress' ),
					'images'         => __( 'Images downloaded', 'restropress' ),
					'image_failures' => __( 'Images that failed', 'restropress' ),
					'customers'      => __( 'Customers added', 'restropress' ),
					'settings'       => __( 'Settings changed', 'restropress' ),
				),
				'columns'        => array(
					'category' => __( 'Category', 'restropress' ),
					'name'     => __( 'Item', 'restropress' ),
					'price'    => __( 'Price', 'restropress' ),
					'addons'   => __( 'Add-ons', 'restropress' ),
					'dietary'  => __( 'Labels', 'restropress' ),
					'image'    => __( 'Image', 'restropress' ),
					'action'   => __( 'Action', 'restropress' ),
					'warnings' => __( 'Warnings', 'restropress' ),
				),
				'status'         => array(
					'running' => __( 'Not finished', 'restropress' ),
					'done'    => __( 'Imported', 'restropress' ),
					'undoing' => __( 'Undo not finished', 'restropress' ),
					'undone'  => __( 'Undone', 'restropress' ),
				),
			),
		);
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to import menu items.', 'restropress' ) );
		}
		$sources  = rpress_get_import_sources();
		$recent   = RPress_Importer_Engine::recent_jobs();
		$selected = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ai_url   = admin_url( 'edit.php?post_type=fooditem&page=rpress-menu-import' );
		include RPRESS_IMPORTER_DIR . 'views/page.php';
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	/**
	 * Nonce + capability gate.
	 *
	 * @return void
	 */
	protected static function verify() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page and try again.', 'restropress' ) ), 403 );
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to import menu items.', 'restropress' ) ), 403 );
		}
	}

	/**
	 * Job ID from the request, checked.
	 *
	 * @return int
	 */
	protected static function request_job() {
		$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $job_id || ! RPress_Importer_Engine::can_access( $job_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Import not found.', 'restropress' ) ), 404 );
		}
		return $job_id;
	}

	/**
	 * Step 2: read the upload (or the site) and build the dry-run preview.
	 *
	 * @return void
	 */
	public static function ajax_parse() {
		self::verify();
		$sources   = rpress_get_import_sources();
		$source_id = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		if ( ! isset( $sources[ $source_id ] ) || ! rpress_import_source_available( $sources[ $source_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'This import source is not available.', 'restropress' ) ) );
		}
		$source = $sources[ $source_id ];
		$file   = null;
		$label  = $source['label'];

		if ( ! empty( $source['accepts'] ) ) {
			$file = self::read_upload( $source );
			if ( is_wp_error( $file ) ) {
				wp_send_json_error( array( 'message' => $file->get_error_message() ) );
			}
			$label = $file['name'];
			if ( is_callable( $source['detect'] ) && ! call_user_func( $source['detect'], $file ) ) {
				$message = sprintf(
					/* translators: %s: source name */
					__( 'This file does not look like a %s export.', 'restropress' ),
					$source['label']
				);
				foreach ( $sources as $other_id => $other ) {
					if ( $other_id !== $source_id && in_array( $file['ext'], $other['accepts'], true ) && is_callable( $other['detect'] ) && call_user_func( $other['detect'], $file ) ) {
						/* translators: %s: other source name */
						$message .= ' ' . sprintf( __( 'It looks like a %s file; choose that source instead.', 'restropress' ), $other['label'] );
						break;
					}
				}
				wp_send_json_error( array( 'message' => $message ) );
			}
		}

		$model = call_user_func( $source['parse'], $file );
		if ( is_wp_error( $model ) ) {
			wp_send_json_error( array( 'message' => $model->get_error_message() ) );
		}
		$job_id = RPress_Importer_Engine::create_job( $source_id, $model, $label );
		if ( is_wp_error( $job_id ) ) {
			wp_send_json_error( array( 'message' => $job_id->get_error_message() ) );
		}
		wp_send_json_success( RPress_Importer_Engine::preview( $job_id ) );
	}

	/**
	 * Validate and read an uploaded import file. The file is read from PHP's
	 * temp upload and never stored in the uploads folder.
	 *
	 * @param array $source Source args.
	 * @return array|WP_Error { path, name, ext, contents }
	 */
	protected static function read_upload( $source ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in verify().
		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			return new WP_Error( 'rpress_import_nofile', __( 'Choose a file to upload.', 'restropress' ) );
		}
		$upload = $_FILES['file']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$error  = isset( $upload['error'] ) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'rpress_import_upload', in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ? __( 'This file is larger than the upload limit.', 'restropress' ) : __( 'The file could not be uploaded.', 'restropress' ) );
		}
		$tmp  = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';
		$name = isset( $upload['name'] ) ? sanitize_file_name( wp_unslash( $upload['name'] ) ) : '';
		if ( ! $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'rpress_import_upload', __( 'The file could not be uploaded.', 'restropress' ) );
		}
		$size = (int) filesize( $tmp );
		if ( $size <= 0 ) {
			return new WP_Error( 'rpress_import_empty', __( 'The file is empty.', 'restropress' ) );
		}
		if ( $size > rpress_importer_max_upload_size() ) {
			return new WP_Error( 'rpress_import_size', __( 'This file is larger than the upload limit.', 'restropress' ) );
		}
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $source['accepts'], true ) ) {
			return new WP_Error(
				'rpress_import_type',
				sprintf(
					/* translators: %s: allowed extensions */
					__( 'This file type is not accepted. Upload a %s file.', 'restropress' ),
					'.' . implode( ' / .', $source['accepts'] )
				)
			);
		}
		$contents = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			return new WP_Error( 'rpress_import_read', __( 'The file could not be read.', 'restropress' ) );
		}
		// Import files are text: refuse binaries (spreadsheets, images, archives).
		if ( false !== strpos( substr( $contents, 0, 8192 ), "\0" ) ) {
			return new WP_Error( 'rpress_import_binary', __( 'This is not a text file. Save spreadsheets as CSV before uploading.', 'restropress' ) );
		}
		return array(
			'path'     => $tmp,
			'name'     => $name,
			'ext'      => $ext,
			'contents' => $contents,
		);
	}

	/**
	 * Step 3: confirm and queue.
	 *
	 * @return void
	 */
	public static function ajax_start() {
		self::verify();
		$job_id  = self::request_job();
		$options = array(
			'mode'       => isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'add', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'duplicates' => isset( $_POST['duplicates'] ) ? sanitize_key( wp_unslash( $_POST['duplicates'] ) ) : 'skip', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'settings'   => ! empty( $_POST['settings'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'customers'  => ! empty( $_POST['customers'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);
		$result = RPress_Importer_Engine::start( $job_id, $options );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( RPress_Importer_Engine::run_step( $job_id ) );
	}

	/**
	 * Step 4: one batch.
	 *
	 * @return void
	 */
	public static function ajax_step() {
		self::verify();
		$job_id = self::request_job();
		$result = RPress_Importer_Engine::run_step( $job_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( ! empty( $result['finished'] ) ) {
			$result['summary'] = RPress_Importer_Engine::summary( $job_id );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Begin undo.
	 *
	 * @return void
	 */
	public static function ajax_undo() {
		self::verify();
		$job_id = self::request_job();
		if ( 'undoing' !== RPress_Importer_Engine::get_status( $job_id ) ) {
			$result = RPress_Importer_Engine::start_undo( $job_id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
		}
		self::ajax_undo_step();
	}

	/**
	 * One undo batch.
	 *
	 * @return void
	 */
	public static function ajax_undo_step() {
		self::verify();
		$job_id = self::request_job();
		$result = RPress_Importer_Engine::undo_step( $job_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( ! empty( $result['finished'] ) ) {
			$result['summary'] = RPress_Importer_Engine::summary( $job_id );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Job summary.
	 *
	 * @return void
	 */
	public static function ajax_summary() {
		self::verify();
		wp_send_json_success( RPress_Importer_Engine::summary( self::request_job() ) );
	}

	/**
	 * Throw away a preview that was not imported.
	 *
	 * @return void
	 */
	public static function ajax_discard() {
		self::verify();
		$job_id = self::request_job();
		if ( 'preview' === RPress_Importer_Engine::get_status( $job_id ) ) {
			wp_delete_post( $job_id, true );
		}
		wp_send_json_success();
	}
}
