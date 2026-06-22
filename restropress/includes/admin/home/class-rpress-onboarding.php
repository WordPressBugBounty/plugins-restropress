<?php
/**
 * AI-first onboarding and launch hub.
 *
 * @package RPRESS\Admin\Home
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'RPress_Onboarding' ) ) {
	/**
	 * Restaurant-first onboarding controller.
	 */
	class RPress_Onboarding {
		const STATE_OPTION      = 'rpress_onboarding';
		const AI_SETTINGS       = 'rpress_ai_settings';
		const IMPORT_POST_TYPE  = 'rpress_menu_import';
		const NONCE_ACTION      = 'rpress_onboarding';
		const MAX_UPLOAD_BYTES  = 10485760;

		/**
		 * Boot hooks.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_import_post_type' ), 2 );
			add_action( 'wp_ajax_rpress_onboarding_save_step', array( __CLASS__, 'ajax_save_step' ) );
			add_action( 'wp_ajax_rpress_onboarding_upload_menu', array( __CLASS__, 'ajax_upload_menu' ) );
			add_action( 'wp_ajax_rpress_onboarding_publish_menu', array( __CLASS__, 'ajax_publish_menu' ) );
			add_action( 'wp_ajax_rpress_onboarding_publish_items', array( __CLASS__, 'ajax_publish_items' ) );
			add_action( 'wp_ajax_rpress_onboarding_delete_import', array( __CLASS__, 'ajax_delete_import' ) );
			add_action( 'wp_ajax_rpress_onboarding_test_ai', array( __CLASS__, 'ajax_test_ai' ) );
		}

		/**
		 * Register private import jobs.
		 *
		 * @return void
		 */
		public static function register_import_post_type() {
			register_post_type(
				self::IMPORT_POST_TYPE,
				array(
					'labels'              => array(
						'name'          => __( 'Menu Imports', 'restropress' ),
						'singular_name' => __( 'Menu Import', 'restropress' ),
					),
					'public'              => false,
					'show_ui'             => false,
					'query_var'           => false,
					'rewrite'             => false,
					'capability_type'     => 'post',
					'map_meta_cap'        => true,
					'supports'            => array( 'title', 'author' ),
					'can_export'          => false,
					'exclude_from_search' => true,
				)
			);
		}

		/**
		 * Render onboarding screen.
		 *
		 * @return void
		 */
		public static function render() {
			if ( ! current_user_can( 'manage_shop_settings' ) ) {
				wp_die( esc_html__( 'You do not have permission to view this page.', 'restropress' ) );
			}

			$state       = self::get_state();
			$ai_settings = self::get_ai_settings();
			$ai_status   = self::get_ai_status();
			$latest      = self::get_latest_import();
			$countries   = function_exists( 'rpress_get_country_list' ) ? rpress_get_country_list() : array();
			$currencies  = function_exists( 'rpress_get_currencies' ) ? rpress_get_currencies() : array();

			include RP_PLUGIN_DIR . 'includes/admin/home/views/onboarding.php';
		}

		/**
		 * Render the standalone Menu Items -> Import screen, reusing the same
		 * AI/spreadsheet/sample importer the onboarding wizard uses (without the
		 * wizard rail / preview / steps). Shop managers can import menus, so this
		 * is gated on edit_products rather than manage_shop_settings.
		 *
		 * @return void
		 */
		public static function render_menu_importer() {
			if ( ! current_user_can( 'edit_products' ) ) {
				wp_die( esc_html__( 'You do not have permission to import menu items.', 'restropress' ) );
			}

			$ai_settings = self::get_ai_settings();
			$ai_status   = self::get_ai_status();

			include RP_PLUGIN_DIR . 'includes/admin/home/views/menu-importer-page.php';
		}

		/**
		 * Get persisted onboarding state.
		 *
		 * @return array
		 */
		public static function get_state() {
			$defaults = array(
				'version'         => '2026.1',
				'status'          => 'in_progress',
				'current_step'    => 'welcome',
				'completed_steps' => array(),
				'completed_tasks' => array(),
				'skipped_tasks'   => array(),
				'launch_goal'     => '',
				'menu_setup_path' => 'ai_import',
				'test_order_confirmed' => false,
				'launch_confirmed_at'  => '',
				'updated_at'      => '',
			);

			$state = get_option( self::STATE_OPTION, array() );
			return wp_parse_args( is_array( $state ) ? $state : array(), $defaults );
		}

		/**
		 * Update onboarding state.
		 *
		 * @param array $changes Changes.
		 * @return array
		 */
		protected static function update_state( $changes ) {
			$state = self::get_state();
			$state = array_merge( $state, $changes );
			$state['updated_at'] = current_time( 'mysql' );
			update_option( self::STATE_OPTION, $state, false );
			return $state;
		}

		/**
		 * Get AI settings.
		 *
		 * @return array
		 */
		public static function get_ai_settings() {
			$defaults = array(
				'enabled' => 'yes',
				'provider' => 'wordpress',
				'api_key' => '',
				'model' => '',
			);

			$settings = get_option( self::AI_SETTINGS, array() );
			return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
		}

		/**
		 * Get current AI availability.
		 *
		 * @return array
		 */
		public static function get_ai_status() {
			$settings = self::get_ai_settings();
			$wp_ai    = class_exists( '\WordPress\AiClient\AiClient' );
			$wp_configured = false;
			$wp_provider_ids = array();
			if ( $wp_ai ) {
				try {
					$registry = \WordPress\AiClient\AiClient::defaultRegistry();
					$wp_provider_ids = method_exists( $registry, 'getRegisteredProviderIds' ) ? $registry->getRegisteredProviderIds() : array();
					foreach ( $wp_provider_ids as $provider_id ) {
						if ( method_exists( $registry, 'isProviderConfigured' ) && $registry->isProviderConfigured( $provider_id ) ) {
							$wp_configured = true;
							break;
						}
					}
				} catch ( Exception $e ) {
					$wp_configured = false;
				}
			}
			$direct   = 'wordpress' !== $settings['provider'] && ! empty( $settings['api_key'] );
			$enabled  = 'yes' === $settings['enabled'];

			$provider_label = __( 'Not configured', 'restropress' );
			if ( $enabled && 'wordpress' === $settings['provider'] && $wp_configured ) {
				$provider_label = __( 'WordPress AI', 'restropress' );
			} elseif ( $enabled && 'openai' === $settings['provider'] && $direct ) {
				$provider_label = __( 'OpenAI via RestroPress', 'restropress' );
			} elseif ( $enabled && 'gemini' === $settings['provider'] && $direct ) {
				$provider_label = __( 'Google Gemini via RestroPress', 'restropress' );
			}

			return array(
				'enabled'          => $enabled,
				'wp_ai_available'  => $wp_ai,
				'wp_ai_configured' => $wp_configured,
				'wp_ai_providers'  => array_values( $wp_provider_ids ),
				'direct_available' => $direct,
				'provider'         => sanitize_key( $settings['provider'] ),
				'provider_label'   => $provider_label,
				'ready'            => $enabled && ( ( 'wordpress' === $settings['provider'] && $wp_configured ) || $direct ),
				'multimodal'       => $enabled && ( ( 'wordpress' === $settings['provider'] && $wp_configured ) || 'gemini' === $settings['provider'] || 'openai' === $settings['provider'] ),
			);
		}

		/**
		 * Latest import job.
		 *
		 * @return WP_Post|null
		 */
		public static function get_latest_import() {
			$jobs = get_posts(
				array(
					'post_type'      => self::IMPORT_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);

			return $jobs ? $jobs[0] : null;
		}

		/**
		 * AJAX guard.
		 *
		 * @return void
		 */
		protected static function verify_ajax() {
			if ( ! current_user_can( 'manage_shop_settings' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to manage onboarding.', 'restropress' ) ), 403 );
			}

			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		}

		/**
		 * Save one onboarding step.
		 *
		 * @return void
		 */
		public static function ajax_save_step() {
			self::verify_ajax();

			$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
			$data = isset( $_POST['data'] ) ? self::sanitize_deep( wp_unslash( $_POST['data'] ) ) : array();
			$next_step = isset( $_POST['next_step'] ) ? sanitize_key( wp_unslash( $_POST['next_step'] ) ) : '';

			if ( ! is_array( $data ) ) {
				$data = array();
			}

			switch ( $step ) {
				case 'welcome':
					self::save_welcome( $data );
					break;
				case 'profile':
					self::save_profile( $data );
					break;
				case 'ordering':
					self::save_ordering( $data );
					break;
				case 'hours':
					self::save_hours( $data );
					break;
				case 'payments':
					self::save_payments( $data );
					break;
				case 'operations':
					self::save_operations( $data );
					break;
				case 'menu':
				case 'ai':
					self::save_ai_settings( $data );
					break;
				case 'launch':
					self::save_launch( $data );
					break;
			}

			$state = self::mark_step_complete( $step );
			if ( $next_step && in_array( $next_step, self::get_allowed_steps(), true ) ) {
				$state = self::update_state( array( 'current_step' => $next_step ) );
			}
			wp_send_json_success(
				array(
					'state'     => $state,
					'ai_status' => self::get_ai_status(),
				)
			);
		}

		/**
		 * Upload and parse a menu file.
		 *
		 * @return void
		 */
		public static function ajax_upload_menu() {
			self::verify_ajax();

			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			$consent = isset( $_POST['consent'] ) ? sanitize_text_field( wp_unslash( $_POST['consent'] ) ) : '';
			if ( 'yes' !== $consent ) {
				wp_send_json_error( array( 'message' => __( 'Please confirm AI processing consent before uploading a menu.', 'restropress' ) ), 400 );
			}

			if ( empty( $_FILES['menu_file'] ) || empty( $_FILES['menu_file']['name'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Choose a menu file to import.', 'restropress' ) ), 400 );
			}

			$file = $_FILES['menu_file'];
			if ( ! empty( $file['size'] ) && self::MAX_UPLOAD_BYTES < (int) $file['size'] ) {
				wp_send_json_error( array( 'message' => __( 'Menu files must be 10 MB or smaller.', 'restropress' ) ), 400 );
			}

			$allowed = array( 'pdf', 'jpg', 'jpeg', 'png', 'webp', 'csv', 'xls', 'xlsx' );
			$ext     = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Upload a PDF, image, CSV, XLS, or XLSX menu.', 'restropress' ) ), 400 );
			}

			$upload = self::store_upload( $file );
			if ( is_wp_error( $upload ) ) {
				wp_send_json_error( array( 'message' => $upload->get_error_message() ), 400 );
			}

			$job_id = wp_insert_post(
				array(
					'post_type'   => self::IMPORT_POST_TYPE,
					'post_status' => 'private',
					'post_title'  => sprintf(
						/* translators: %s: file name */
						__( 'Menu import: %s', 'restropress' ),
						sanitize_file_name( $file['name'] )
					),
					'post_author' => get_current_user_id(),
				),
				true
			);

			if ( is_wp_error( $job_id ) ) {
				wp_send_json_error( array( 'message' => $job_id->get_error_message() ), 400 );
			}

			update_post_meta( $job_id, '_rpress_import_status', 'parsing' );
			update_post_meta( $job_id, '_rpress_import_source', $upload );
			update_post_meta( $job_id, '_rpress_import_provider', self::get_ai_status() );

			$gen_desc = isset( $_POST['generate_descriptions'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['generate_descriptions'] ) );
			$result = self::parse_menu_file( $upload, $ext, $gen_desc );
			if ( is_wp_error( $result ) ) {
				update_post_meta( $job_id, '_rpress_import_status', 'failed' );
				update_post_meta( $job_id, '_rpress_import_error', $result->get_error_message() );
				wp_send_json_error(
					array(
						'message' => self::friendly_ai_error_message( $result ),
						'job_id'  => $job_id,
					),
					400
				);
			}

			$result = self::normalize_import_payload( $result );

			// The AI decided this file isn't a menu - tell the user what it looks
			// like instead of importing junk rows from a flyer/receipt/etc.
			if ( isset( $result['is_menu'] ) && false === $result['is_menu'] ) {
				update_post_meta( $job_id, '_rpress_import_status', 'not_menu' );
				$doc_type = ! empty( $result['document_type'] ) ? $result['document_type'] : __( 'something other than a menu', 'restropress' );
				$message  = sprintf(
					/* translators: %s: what the uploaded file looks like, e.g. a receipt or flyer */
					__( 'This doesn\'t look like a menu. It appears to be: %s. Please upload your food menu (PDF, image, CSV, or spreadsheet) and try again.', 'restropress' ),
					$doc_type
				);
				if ( ! empty( $result['document_note'] ) ) {
					$message .= ' ' . $result['document_note'];
				}
				wp_send_json_error(
					array(
						'message'  => $message,
						'job_id'   => $job_id,
						'not_menu' => true,
					),
					400
				);
			}

			$result = self::add_duplicate_warnings( $result );

			update_post_meta( $job_id, '_rpress_import_status', 'needs_review' );
			update_post_meta( $job_id, '_rpress_import_payload', $result );

			self::mark_step_complete( 'menu' );
			self::update_state( array( 'current_step' => 'review' ) );

			wp_send_json_success(
				array(
					'job_id'  => $job_id,
					'status'  => 'needs_review',
					'payload' => $result,
					'html'    => self::render_review_html( $result, $job_id ),
				)
			);
		}

		/**
		 * Publish reviewed menu items.
		 *
		 * @return void
		 */
		public static function ajax_publish_menu() {
			self::verify_ajax();

			$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
			$mode   = isset( $_POST['mode'] ) && 'draft' === sanitize_key( wp_unslash( $_POST['mode'] ) ) ? 'draft' : 'publish';
			$payload = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : array();

			if ( ! $job_id || self::IMPORT_POST_TYPE !== get_post_type( $job_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Import job not found.', 'restropress' ) ), 404 );
			}

			if ( ! is_array( $payload ) ) {
				wp_send_json_error( array( 'message' => __( 'Review data is invalid.', 'restropress' ) ), 400 );
			}

			$payload = self::sanitize_deep( $payload );
			$result  = self::publish_payload( $payload, $mode );

			update_post_meta( $job_id, '_rpress_import_status', 'published' );
			update_post_meta( $job_id, '_rpress_import_review_state', $payload );
			update_post_meta( $job_id, '_rpress_import_publish_result', $result );

			self::mark_step_complete( 'review' );
			self::mark_step_complete( 'launch' );

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: 1: created items, 2: skipped duplicates */
						__( 'Menu published. %1$d items created, %2$d duplicates skipped.', 'restropress' ),
						(int) $result['created'],
						(int) $result['skipped']
					),
					'result'  => $result,
					'state'   => self::get_state(),
				)
			);
		}

		/**
		 * Publish a client-built menu (sample or manual) with no import job.
		 *
		 * @return void
		 */
		public static function ajax_publish_items() {
			self::verify_ajax();

			$payload = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : array();
			if ( ! is_array( $payload ) || empty( $payload['categories'] ) ) {
				wp_send_json_error( array( 'message' => __( 'No menu items to publish.', 'restropress' ) ), 400 );
			}

			$payload   = self::normalize_import_payload( self::sanitize_deep( $payload ) );
			$mode      = ( isset( $_POST['mode'] ) && 'draft' === sanitize_key( wp_unslash( $_POST['mode'] ) ) ) ? 'draft' : 'publish';
			$is_sample = ! empty( $_POST['is_sample'] );
			$result    = self::publish_payload( $payload, $mode );

			// Tag sample items so they can be cleared later in one click.
			if ( $is_sample && ! empty( $result['ids'] ) ) {
				foreach ( $result['ids'] as $pid ) {
					update_post_meta( $pid, '_rpress_sample_item', '1' );
				}
			}

			self::mark_step_complete( 'menu' );
			self::mark_step_complete( 'review' );

			wp_send_json_success(
				array(
					'result'  => $result,
					'message' => sprintf(
						/* translators: %d: number of items created */
						__( 'Menu saved. %d items added.', 'restropress' ),
						(int) $result['created']
					),
				)
			);
		}

		/**
		 * Delete import job and optional source file.
		 *
		 * @return void
		 */
		public static function ajax_delete_import() {
			self::verify_ajax();

			$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
			if ( ! $job_id || self::IMPORT_POST_TYPE !== get_post_type( $job_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Import job not found.', 'restropress' ) ), 404 );
			}

			$source = get_post_meta( $job_id, '_rpress_import_source', true );
			if ( ! empty( $source['file'] ) && file_exists( $source['file'] ) ) {
				wp_delete_file( $source['file'] );
			}

			wp_delete_post( $job_id, true );
			wp_send_json_success( array( 'message' => __( 'Import data deleted.', 'restropress' ) ) );
		}

		/**
		 * Test AI settings.
		 *
		 * @return void
		 */
		public static function ajax_test_ai() {
			self::verify_ajax();

			$data = isset( $_POST['data'] ) ? self::sanitize_deep( wp_unslash( $_POST['data'] ) ) : array();
			self::save_ai_settings( is_array( $data ) ? $data : array() );
			$status = self::get_ai_status();

			if ( ! $status['ready'] ) {
				$provider = $status['provider'];
				if ( 'openai' === $provider ) {
					$message = __( 'No OpenAI key saved yet. Paste your OpenAI API key above, then test again.', 'restropress' );
				} elseif ( 'gemini' === $provider ) {
					$message = __( 'No Gemini key saved yet. Paste your Google Gemini API key above, then test again.', 'restropress' );
				} elseif ( empty( $status['wp_ai_available'] ) ) {
					$message = __( 'Your site has no built-in WordPress AI. Choose OpenAI or Gemini above and add your API key, or import a spreadsheet instead.', 'restropress' );
				} else {
					$message = __( 'WordPress AI is installed but no provider is connected yet. Connect one under Settings, or choose OpenAI / Gemini above and add a key.', 'restropress' );
				}
				wp_send_json_error(
					array(
						'message' => $message,
						'status'  => $status,
					),
					400
				);
			}

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: provider label */
						__( '%s is ready for menu parsing.', 'restropress' ),
						$status['provider_label']
					),
					'status' => $status,
				)
			);
		}

		/**
		 * Save first-run launch choices.
		 *
		 * @param array $data Step data.
		 * @return void
		 */
		protected static function save_welcome( $data ) {
			$launch_goal = isset( $data['launch_goal'] ) ? sanitize_key( $data['launch_goal'] ) : 'delivery_and_pickup';
			$menu_path   = isset( $data['menu_setup_path'] ) ? sanitize_key( $data['menu_setup_path'] ) : 'ai_import';

			if ( ! in_array( $launch_goal, array( 'pickup', 'delivery', 'delivery_and_pickup' ), true ) ) {
				$launch_goal = 'delivery_and_pickup';
			}

			if ( ! in_array( $menu_path, array( 'ai_import', 'structured_import', 'manual' ), true ) ) {
				$menu_path = 'ai_import';
			}

			rpress_update_option( 'enable_service', $launch_goal );
			rpress_update_option( 'default_service', 'delivery' === $launch_goal ? 'delivery' : 'pickup' );

			self::update_state(
				array(
					'launch_goal'     => $launch_goal,
					'menu_setup_path' => $menu_path,
				)
			);
		}

		/**
		 * Save profile settings.
		 *
		 * @param array $data Step data.
		 * @return void
		 */
		protected static function save_profile( $data ) {
			$map = array(
				'restaurant_name' => 'restaurant_name',
				'cuisine'         => 'restaurant_cuisine',
				'store_address'   => 'store_address',
				'store_city'      => 'store_city',
				'store_postcode'  => 'store_postcode',
				'store_phone'     => 'store_phone',
				'base_country'    => 'base_country',
				'base_state'      => 'base_state',
				'currency'        => 'currency',
			);

			// Keep compatibility with early onboarding builds that posted generic profile keys.
			if ( isset( $data['address'] ) && ! isset( $data['store_address'] ) ) {
				$data['store_address'] = $data['address'];
			}
			if ( isset( $data['country'] ) && ! isset( $data['base_country'] ) ) {
				$data['base_country'] = $data['country'];
			}
			if ( isset( $data['state'] ) && ! isset( $data['base_state'] ) ) {
				$data['base_state'] = $data['state'];
			}

			foreach ( $map as $key => $option ) {
				if ( isset( $data[ $key ] ) ) {
					$value = 'store_address' === $key ? sanitize_textarea_field( $data[ $key ] ) : sanitize_text_field( $data[ $key ] );
					rpress_update_option( $option, $value );
				}
			}

			// RestroPress uses the WordPress site title as the store name across
			// receipts, emails and the storefront - so mirror the name there too.
			if ( ! empty( $data['restaurant_name'] ) ) {
				$name = sanitize_text_field( $data['restaurant_name'] );
				update_option( 'blogname', $name );
				if ( ! rpress_get_option( 'from_name' ) ) {
					rpress_update_option( 'from_name', $name );
				}
			}

			// Timezone & time format are core WordPress options that RestroPress
			// reads for order times and hours - let onboarding set them inline.
			if ( ! empty( $data['timezone_string'] ) ) {
				$tz = sanitize_text_field( $data['timezone_string'] );
				if ( in_array( $tz, timezone_identifiers_list(), true ) ) {
					update_option( 'timezone_string', $tz );
					update_option( 'gmt_offset', '' );
				}
			}
			if ( ! empty( $data['time_format'] ) ) {
				// Whitelist the two presets the wizard offers; custom formats stay in WP settings.
				$tfmt = ( '24' === $data['time_format'] ) ? 'H:i' : 'g:i A';
				update_option( 'time_format', $tfmt );
				// Keep the storefront's own time format in sync - guests see
				// store_time_format, not the WP admin format.
				rpress_update_option( 'store_time_format', '24' === $data['time_format'] ? '24hrs' : '12hrs' );
			}
		}

		/**
		 * Save ordering settings.
		 *
		 * @param array $data Step data.
		 * @return void
		 */
		protected static function save_ordering( $data ) {
			// The v4 wizard sends the service slug directly; older payloads sent
			// pickup/delivery booleans. Accept both - previously only the boolean
			// form was read, so every wizard save silently stored "pickup".
			$allowed = array( 'delivery_and_pickup', 'delivery', 'pickup' );
			if ( isset( $data['enable_service'] ) && in_array( $data['enable_service'], $allowed, true ) ) {
				$service = $data['enable_service'];
			} else {
				$pickup   = ! empty( $data['pickup'] );
				$delivery = ! empty( $data['delivery'] );
				$service  = 'pickup';

				if ( $pickup && $delivery ) {
					$service = 'delivery_and_pickup';
				} elseif ( $delivery ) {
					$service = 'delivery';
				}
			}

			rpress_update_option( 'enable_service', $service );

			foreach ( array( 'default_service', 'enable_asap_option', 'prep_time', 'allow_minimum_order', 'minimum_order_price', 'minimum_order_price_pickup' ) as $key ) {
				if ( isset( $data[ $key ] ) ) {
					$value = sanitize_text_field( $data[ $key ] );

					if ( 'default_service' === $key ) {
						if ( 'delivery' === $service ) {
							$value = 'delivery';
						} elseif ( 'pickup' === $service ) {
							$value = 'pickup';
						} elseif ( ! in_array( $value, array( 'pickup', 'delivery' ), true ) ) {
							$value = 'pickup';
						}
					}

					rpress_update_option( $key, $value );
				}
			}
		}

		/**
		 * Save ordering availability settings.
		 *
		 * @param array $data Step data.
		 * @return void
		 */
		protected static function save_hours( $data ) {
			foreach ( array( 'open_time', 'close_time', 'store_time_format' ) as $key ) {
				if ( isset( $data[ $key ] ) ) {
					rpress_update_option( $key, sanitize_text_field( $data[ $key ] ) );
				}
			}
		}

		/**
		 * Save payment settings.
		 *
		 * @param array $data Step data.
		 * @return void
		 */
		protected static function save_payments( $data ) {
			// Onboarding only flips gateways on/off; their configuration lives on
			// Settings -> Payments. We deliberately do NOT touch test_mode here.
			if ( isset( $data['cash_gateway'] ) ) {
				self::update_cash_gateway( ! empty( $data['cash_gateway'] ) );
			}
			if ( isset( $data['paypal_enabled'] ) ) {
				self::toggle_gateway( 'paypal', ! empty( $data['paypal_enabled'] ) );
			}
			if ( isset( $data['stripe_enabled'] ) ) {
				self::toggle_gateway( 'stripe', ! empty( $data['stripe_enabled'] ) );
			}
		}

		/**
		 * Enable or disable a payment gateway, keeping default_gateway sane.
		 *
		 * @param string $gateway Gateway key.
		 * @param bool   $enabled Whether it should be enabled.
		 * @return void
		 */
		protected static function toggle_gateway( $gateway, $enabled ) {
			$gateways = rpress_get_option( 'gateways', array() );
			$gateways = is_array( $gateways ) ? $gateways : array();

			if ( $enabled ) {
				$gateways[ $gateway ] = 1;
				if ( ! rpress_get_option( 'default_gateway', '' ) ) {
					rpress_update_option( 'default_gateway', $gateway );
				}
			} else {
				unset( $gateways[ $gateway ] );
				if ( $gateway === rpress_get_option( 'default_gateway', '' ) ) {
					reset( $gateways );
					rpress_update_option( 'default_gateway', ! empty( $gateways ) ? key( $gateways ) : '' );
				}
			}

			rpress_update_option( 'gateways', $gateways );
		}

		/**
		 * Update the existing RestroPress cash payment gateway setting.
		 *
		 * @param bool $enabled Whether pay-by-cash should be enabled.
		 * @return void
		 */
		protected static function update_cash_gateway( $enabled ) {
			$gateways = rpress_get_option( 'gateways', array() );
			$gateways = is_array( $gateways ) ? $gateways : array();

			if ( $enabled ) {
				$gateways['cash_on_delivery'] = 1;

				if ( ! rpress_get_option( 'default_gateway', '' ) ) {
					rpress_update_option( 'default_gateway', 'cash_on_delivery' );
				}
			} else {
				unset( $gateways['cash_on_delivery'] );

				if ( 'cash_on_delivery' === rpress_get_option( 'default_gateway', '' ) ) {
					reset( $gateways );
					$next_gateway = ! empty( $gateways ) ? key( $gateways ) : '';
					rpress_update_option( 'default_gateway', $next_gateway );
				}
			}

			rpress_update_option( 'gateways', $gateways );
			rpress_update_option( 'manual_payments', '' );
		}

		/**
		 * Whether RestroPress has at least one real payment gateway enabled.
		 *
		 * @return bool
		 */
		protected static function has_payment_path() {
			$gateways = rpress_get_option( 'gateways', array() );

			if ( ! is_array( $gateways ) ) {
				return ! empty( $gateways );
			}

			foreach ( $gateways as $enabled ) {
				if ( ! empty( $enabled ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Save operation settings.
		 *
		 * @param array $data Step data.
		 * @return void
		 */
		protected static function save_operations( $data ) {
			foreach ( array( 'enable_order_notification', 'enable_printing', 'admin_notice_emails' ) as $key ) {
				if ( isset( $data[ $key ] ) ) {
					rpress_update_option( $key, sanitize_text_field( $data[ $key ] ) );
				}
			}
			// Veg / non-veg display toggle (config → Menu labelling).
			if ( isset( $data['enable_food_type'] ) ) {
				if ( ! empty( $data['enable_food_type'] ) ) {
					rpress_update_option( 'enable_food_type', '1' );
				} else {
					rpress_update_option( 'enable_food_type', '' );
				}
			}
		}

		/**
		 * Save and validate launch confirmation.
		 *
		 * @param array $data Step data.
		 * @return void
		 */
		protected static function save_launch( $data ) {
			$confirmed = ! empty( $data['confirm_test_order'] );
			$state     = self::get_state();

			if ( ! $confirmed && 'launched' !== $state['status'] ) {
				wp_send_json_error(
					array(
						'message' => __( 'Place and confirm an internal test order before finishing launch setup.', 'restropress' ),
					),
					400
				);
			}

			$state = self::update_state(
				array(
					'test_order_confirmed' => true,
					'launch_confirmed_at'  => current_time( 'mysql' ),
				)
			);

			$latest        = self::get_latest_import();
			$latest_status = $latest ? get_post_meta( $latest->ID, '_rpress_import_status', true ) : '';
			$tasks         = self::get_launch_tasks( $state, $latest, $latest_status, self::get_ai_status() );
			$missing       = array();

			foreach ( $tasks as $id => $task ) {
				if ( 'launch' === $id || empty( $task['blocking'] ) ) {
					continue;
				}

				if ( ! in_array( $task['status'], array( 'ready', 'complete' ), true ) ) {
					$missing[] = $task['label'];
				}
			}

			if ( ! empty( $missing ) ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: comma-separated task labels */
							__( 'Finish these launch requirements first: %s.', 'restropress' ),
							implode( ', ', $missing )
						),
					),
					400
				);
			}

			self::mark_setup_complete();
		}

		/**
		 * Save AI settings.
		 *
		 * @param array $data Settings.
		 * @return void
		 */
		protected static function save_ai_settings( $data ) {
			$current = self::get_ai_settings();
			$settings = array(
				'enabled'  => ! empty( $data['enabled'] ) ? 'yes' : 'no',
				'provider' => isset( $data['provider'] ) ? sanitize_key( $data['provider'] ) : $current['provider'],
				'api_key'  => isset( $data['api_key'] ) && '' !== $data['api_key'] ? sanitize_text_field( $data['api_key'] ) : $current['api_key'],
				'model'    => isset( $data['model'] ) ? sanitize_text_field( $data['model'] ) : $current['model'],
			);

			if ( ! in_array( $settings['provider'], array( 'wordpress', 'openai', 'gemini' ), true ) ) {
				$settings['provider'] = 'wordpress';
			}

			update_option( self::AI_SETTINGS, $settings, false );
		}

		/**
		 * Complete setup.
		 *
		 * @return void
		 */
		protected static function mark_setup_complete() {
			delete_option( 'rpress_show_setup_wizard' );
			self::update_state( array( 'status' => 'launched' ) );
		}

		/**
		 * Mark a step complete.
		 *
		 * @param string $step Step slug.
		 * @return array
		 */
		protected static function mark_step_complete( $step ) {
			$state = self::get_state();
			if ( $step && ! in_array( $step, $state['completed_steps'], true ) ) {
				$state['completed_steps'][] = $step;
			}
			if ( $step && ! in_array( $step, $state['completed_tasks'], true ) ) {
				$state['completed_tasks'][] = $step;
			}
			$state['current_step'] = $step;
			$state['updated_at'] = current_time( 'mysql' );
			update_option( self::STATE_OPTION, $state, false );
			return $state;
		}

		/**
		 * Allowed onboarding steps.
		 *
		 * @return array
		 */
		protected static function get_allowed_steps() {
			return array( 'welcome', 'profile', 'menu', 'review', 'ordering', 'hours', 'payments', 'operations', 'launch' );
		}

		/**
		 * Store file in protected imports folder.
		 *
		 * @param array $file Uploaded file.
		 * @return array|WP_Error
		 */
		protected static function store_upload( $file ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			$dir = self::get_import_dir();
			if ( is_wp_error( $dir ) ) {
				return $dir;
			}

			add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
			$upload = wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'mimes'     => array(
						'pdf'  => 'application/pdf',
						'jpg'  => 'image/jpeg',
						'jpeg' => 'image/jpeg',
						'png'  => 'image/png',
						'webp' => 'image/webp',
						'csv'  => 'text/csv',
						'xls'  => 'application/vnd.ms-excel',
						'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					),
				)
			);
			remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

			if ( ! empty( $upload['error'] ) ) {
				return new WP_Error( 'rpress_upload_failed', $upload['error'] );
			}

			return $upload;
		}

		/**
		 * Upload directory filter.
		 *
		 * @param array $dirs Upload dirs.
		 * @return array
		 */
		public static function filter_upload_dir( $dirs ) {
			$dirs['subdir'] = '/restropress/menu-imports';
			$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
			$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
			return $dirs;
		}

		/**
		 * Get protected import dir.
		 *
		 * @return array|WP_Error
		 */
		protected static function get_import_dir() {
			$uploads = wp_upload_dir();
			if ( ! empty( $uploads['error'] ) ) {
				return new WP_Error( 'rpress_upload_dir_failed', $uploads['error'] );
			}

			$path = trailingslashit( $uploads['basedir'] ) . 'restropress/menu-imports';
			if ( ! wp_mkdir_p( $path ) ) {
				return new WP_Error( 'rpress_upload_dir_failed', __( 'Could not create the menu import folder.', 'restropress' ) );
			}

			if ( ! file_exists( $path . '/index.html' ) ) {
				file_put_contents( $path . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
			if ( ! file_exists( $path . '/.htaccess' ) ) {
				file_put_contents( $path . '/.htaccess', "Options -Indexes\n<Files *>\nRequire all denied\n</Files>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}

			return array( 'path' => $path );
		}

		/**
		 * Parse uploaded menu.
		 *
		 * @param array  $upload Upload data.
		 * @param string $ext Extension.
		 * @return array|WP_Error
		 */
		protected static function parse_menu_file( $upload, $ext, $generate_descriptions = false ) {
			$text = '';
			if ( 'csv' === $ext ) {
				$text = self::extract_csv_text( $upload['file'] );
			} elseif ( 'xlsx' === $ext ) {
				$text = self::extract_xlsx_text( $upload['file'] );
			} elseif ( 'xls' === $ext ) {
				return new WP_Error( 'rpress_xls_unsupported', __( 'Legacy XLS files are not supported. Open the file in Excel or Google Sheets and save it as XLSX or CSV, then upload again.', 'restropress' ) );
			}

			$status = self::get_ai_status();
			if ( $status['ready'] ) {
				$ai = self::parse_with_ai( $upload, $ext, $text, $generate_descriptions );
				if ( ! is_wp_error( $ai ) ) {
					return $ai;
				}

				if ( ! in_array( $ext, array( 'csv', 'xlsx' ), true ) ) {
					return $ai;
				}
			}

			if ( in_array( $ext, array( 'csv', 'xlsx' ), true ) ) {
				return self::parse_table_text_without_ai( $text );
			}

			return new WP_Error( 'rpress_ai_required', __( 'This menu type needs an AI provider. Enable WordPress AI or connect RestroPress AI settings, then try again.', 'restropress' ) );
		}

		/**
		 * Parse with available AI provider.
		 *
		 * @param array  $upload Upload data.
		 * @param string $ext Extension.
		 * @param string $text Extracted text.
		 * @return array|WP_Error
		 */
		protected static function parse_with_ai( $upload, $ext, $text = '', $generate_descriptions = false ) {
			$settings = self::get_ai_settings();
			if ( 'wordpress' === $settings['provider'] ) {
				return self::parse_with_wordpress_ai( $upload, $ext, $text, $generate_descriptions );
			}
			if ( 'openai' === $settings['provider'] ) {
				return self::parse_with_openai( $upload, $ext, $text, $generate_descriptions );
			}
			if ( 'gemini' === $settings['provider'] ) {
				return self::parse_with_gemini( $upload, $ext, $text, $generate_descriptions );
			}

			return new WP_Error( 'rpress_ai_unavailable', __( 'No supported AI provider is configured.', 'restropress' ) );
		}

		/**
		 * Parse through WordPress AI.
		 *
		 * @param array  $upload Upload data.
		 * @param string $ext Extension.
		 * @param string $text Extracted text.
		 * @return array|WP_Error
		 */
		protected static function parse_with_wordpress_ai( $upload, $ext, $text, $generate_descriptions = false ) {
			if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
				return new WP_Error( 'rpress_wp_ai_missing', __( 'WordPress AI is not available on this site.', 'restropress' ) );
			}

			try {
				add_filter( 'http_request_timeout', array( __CLASS__, 'bump_ai_request_timeout' ), 10, 2 );

				$prompt = \WordPress\AiClient\AiClient::prompt( self::get_menu_prompt( $text, $generate_descriptions ) );
				if ( class_exists( '\WordPress\AiClient\Providers\Http\DTO\RequestOptions' ) && method_exists( $prompt, 'usingRequestOptions' ) ) {
					$request_options = new \WordPress\AiClient\Providers\Http\DTO\RequestOptions();
					$request_options->setTimeout( 60 );
					$request_options->setConnectTimeout( 15 );
					$prompt->usingRequestOptions( $request_options );
				}

				$prompt->asJsonResponse( self::get_menu_schema() );

				if ( empty( $text ) && method_exists( $prompt, 'withFile' ) ) {
					$prompt->withFile( $upload['file'], $upload['type'] );
				}

				$result = $prompt->generateTextResult();
				return self::json_to_payload( $result->toText() );
			} catch ( Exception $e ) {
				return new WP_Error( 'rpress_wp_ai_failed', $e->getMessage() );
			} finally {
				remove_filter( 'http_request_timeout', array( __CLASS__, 'bump_ai_request_timeout' ), 10 );
			}
		}

		/**
		 * Increase remote request timeout while parsing menus through AI providers.
		 *
		 * @param int    $timeout Current timeout.
		 * @param string $url Request URL.
		 * @return int
		 */
		public static function bump_ai_request_timeout( $timeout, $url ) {
			if ( false !== strpos( $url, 'generativelanguage.googleapis.com' ) || false !== strpos( $url, 'api.openai.com' ) ) {
				return 60;
			}

			return $timeout;
		}

		/**
		 * Convert provider transport errors into restaurant-owner friendly messages.
		 *
		 * @param WP_Error $error Error object.
		 * @return string
		 */
		protected static function friendly_ai_error_message( $error ) {
			$message = $error->get_error_message();
			$lower   = strtolower( $message );

			if ( false !== strpos( $lower, 'timed out' ) || false !== strpos( $lower, 'curl error 28' ) ) {
				return __( 'The AI provider took too long to read this menu image. Try again, or upload a smaller/clearer image. If it keeps happening, connect a direct RestroPress AI provider with a longer timeout.', 'restropress' );
			}

			if ( false !== strpos( $lower, 'network error' ) ) {
				return __( 'RestroPress could not reach the AI provider. Check the provider connection and try importing the menu again.', 'restropress' );
			}

			return $message;
		}

		/**
		 * Parse through OpenAI.
		 *
		 * @param array  $upload Upload data.
		 * @param string $ext Extension.
		 * @param string $text Extracted text.
		 * @return array|WP_Error
		 */
		protected static function parse_with_openai( $upload, $ext, $text, $generate_descriptions = false ) {
			$settings = self::get_ai_settings();
			$model    = $settings['model'] ? $settings['model'] : 'gpt-4o-mini';
			$content  = array(
				array(
					'type' => 'text',
					'text' => self::get_menu_prompt( $text, $generate_descriptions ),
				),
			);

			if ( empty( $text ) && in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
				$bytes = file_get_contents( $upload['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$content[] = array(
					'type'      => 'image_url',
					'image_url' => array( 'url' => 'data:' . $upload['type'] . ';base64,' . base64_encode( $bytes ) ),
				);
			} elseif ( empty( $text ) ) {
				return new WP_Error( 'rpress_openai_file_unsupported', __( 'OpenAI direct import currently supports CSV/XLSX text and image menus. Use WordPress AI or Gemini for PDF files.', 'restropress' ) );
			}

			$response = wp_remote_post(
				'https://api.openai.com/v1/chat/completions',
				array(
					'timeout' => 45,
					'headers' => array(
						'Authorization' => 'Bearer ' . $settings['api_key'],
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'           => $model,
							'response_format' => array( 'type' => 'json_object' ),
							'messages'         => array(
								array(
									'role'    => 'user',
									'content' => $content,
								),
							),
						)
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $body['choices'][0]['message']['content'] ) ) {
				return new WP_Error( 'rpress_openai_failed', __( 'OpenAI did not return a menu payload.', 'restropress' ) );
			}

			return self::json_to_payload( $body['choices'][0]['message']['content'] );
		}

		/**
		 * Parse through Gemini.
		 *
		 * @param array  $upload Upload data.
		 * @param string $ext Extension.
		 * @param string $text Extracted text.
		 * @return array|WP_Error
		 */
		protected static function parse_with_gemini( $upload, $ext, $text, $generate_descriptions = false ) {
			$settings = self::get_ai_settings();
			$model    = $settings['model'] ? $settings['model'] : 'gemini-2.0-flash';
			$parts    = array( array( 'text' => self::get_menu_prompt( $text, $generate_descriptions ) ) );

			if ( empty( $text ) ) {
				$bytes = file_get_contents( $upload['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$parts[] = array(
					'inline_data' => array(
						'mime_type' => $upload['type'],
						'data'      => base64_encode( $bytes ),
					),
				);
			}

			$response = wp_remote_post(
				'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $settings['api_key'] ),
				array(
					'timeout' => 45,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'contents'         => array( array( 'parts' => $parts ) ),
							'generationConfig' => array( 'response_mime_type' => 'application/json' ),
						)
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
				return new WP_Error( 'rpress_gemini_failed', __( 'Gemini did not return a menu payload.', 'restropress' ) );
			}

			return self::json_to_payload( $body['candidates'][0]['content']['parts'][0]['text'] );
		}

		/**
		 * Extract CSV into normalized table text.
		 *
		 * @param string $file File path.
		 * @return string
		 */
		protected static function extract_csv_text( $file ) {
			$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( ! $handle ) {
				return '';
			}

			$rows = array();
			while ( ( $row = fgetcsv( $handle ) ) !== false ) {
				$rows[] = implode( ' | ', array_map( 'trim', $row ) );
			}
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			return implode( "\n", $rows );
		}

		/**
		 * Extract first worksheet of XLSX.
		 *
		 * @param string $file File path.
		 * @return string
		 */
		protected static function extract_xlsx_text( $file ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				return '';
			}

			$zip = new ZipArchive();
			if ( true !== $zip->open( $file ) ) {
				return '';
			}

			$shared = array();
			$shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
			if ( $shared_xml ) {
				$xml = simplexml_load_string( $shared_xml );
				if ( $xml ) {
					foreach ( $xml->si as $si ) {
						$text = '';
						if ( isset( $si->t ) ) {
							$text = (string) $si->t;
						} elseif ( isset( $si->r ) ) {
							foreach ( $si->r as $run ) {
								$text .= (string) $run->t;
							}
						}
						$shared[] = $text;
					}
				}
			}

			$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
			$zip->close();
			if ( ! $sheet_xml ) {
				return '';
			}

			$xml = simplexml_load_string( $sheet_xml );
			if ( ! $xml ) {
				return '';
			}

			$rows = array();
			foreach ( $xml->sheetData->row as $row ) {
				$cells = array();
				foreach ( $row->c as $cell ) {
					$value = isset( $cell->v ) ? (string) $cell->v : '';
					$type  = isset( $cell['t'] ) ? (string) $cell['t'] : '';
					if ( 's' === $type && isset( $shared[ (int) $value ] ) ) {
						$value = $shared[ (int) $value ];
					}
					$cells[] = trim( $value );
				}
				$rows[] = implode( ' | ', $cells );
			}

			return implode( "\n", $rows );
		}

		/**
		 * Build deterministic payload from table text.
		 *
		 * @param string $text Table text.
		 * @return array|WP_Error
		 */
		protected static function parse_table_text_without_ai( $text ) {
			$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ) ) );
			if ( empty( $lines ) ) {
				return new WP_Error( 'rpress_empty_menu', __( 'No menu rows were found in this file.', 'restropress' ) );
			}

			$headers = array_map( 'sanitize_key', array_map( 'trim', explode( '|', array_shift( $lines ) ) ) );
			$by_cat  = array();
			$count   = 0;
			foreach ( $lines as $line ) {
				$cols = array_map( 'trim', explode( '|', $line ) );
				$row  = array();
				foreach ( $cols as $index => $value ) {
					$key = isset( $headers[ $index ] ) && $headers[ $index ] ? $headers[ $index ] : 'column_' . $index;
					$row[ $key ] = $value;
				}

				$name = self::first_value( $row, array( 'name', 'item', 'item_name', 'title', 'menu_item', 'column_0' ) );
				if ( ! $name ) {
					continue;
				}

				// Optional category column groups items; otherwise everything lands in one menu.
				$cat = self::first_value( $row, array( 'category', 'cat', 'section', 'menu_category' ) );
				if ( '' === $cat ) {
					$cat = __( 'Imported Menu', 'restropress' );
				}

				// Dietary labels: accept comma / semicolon / pipe separated values.
				$diet_raw = self::first_value( $row, array( 'dietary', 'diet', 'dietary_labels', 'labels' ) );
				$dietary  = $diet_raw ? array_values( array_filter( array_map( 'trim', preg_split( '/[,;\/]+/', $diet_raw ) ) ) ) : array();

				// Veg / non-veg marker (maps to rpress_food_type on publish).
				$ftype_raw = strtolower( self::first_value( $row, array( 'food_type', 'veg', 'veg_non_veg', 'type' ) ) );
				$food_type = '';
				if ( in_array( $ftype_raw, array( 'veg', 'vegetarian', 'veg.' ), true ) ) {
					$food_type = 'veg';
				} elseif ( in_array( $ftype_raw, array( 'non_veg', 'non-veg', 'nonveg', 'non veg', 'nonvegetarian' ), true ) ) {
					$food_type = 'non_veg';
				}

				// Sizes / variants: "Small:3.50; Large:4.50".
				$variants_raw = self::first_value( $row, array( 'variants', 'sizes', 'variant', 'size' ) );
				$variants     = self::parse_variants_cell( $variants_raw );

				// Add-ons / modifiers: "Group [single|multiple]: Opt:price, Opt:price; Group2 ...".
				$mods_raw  = self::first_value( $row, array( 'addons', 'add_ons', 'modifiers', 'extras', 'options' ) );
				$modifiers = self::parse_modifiers_cell( $mods_raw );

				$by_cat[ $cat ][] = array(
					'name'        => $name,
					'description' => self::first_value( $row, array( 'description', 'desc', 'column_2' ) ),
					'price'       => self::clean_price( self::first_value( $row, array( 'price', 'amount', 'column_1' ) ) ),
					'variants'    => $variants,
					'modifiers'   => $modifiers,
					'dietary'     => $dietary,
					'food_type'   => $food_type,
					'confidence'  => 0.9,
					'warnings'    => array(),
				);
				$count++;
			}

			if ( 0 === $count ) {
				return new WP_Error( 'rpress_empty_menu', __( 'No menu items were found in this file.', 'restropress' ) );
			}

			$categories = array();
			foreach ( $by_cat as $cat_name => $cat_items ) {
				$categories[] = array( 'name' => $cat_name, 'items' => $cat_items );
			}

			return array(
				'categories' => $categories,
				'warnings'   => array( __( 'Imported using structured rows because AI is not configured. Review every item before publishing.', 'restropress' ) ),
			);
		}

		/**
		 * Prompt for AI menu parsing.
		 *
		 * @param string $text Extracted text.
		 * @return string
		 */
		protected static function get_menu_prompt( $text = '', $generate_descriptions = false ) {
			$currency = rpress_get_option( 'currency', 'USD' );
			$desc_rule = $generate_descriptions
				? 'For every item, if the menu has no description, WRITE a short, appetising one-line description (max ~15 words) based on the item name; keep any descriptions the menu already provides. '
				: 'Only use descriptions present in the menu; leave description empty when none is given. ';
			return 'You are parsing an uploaded file for RestroPress online ordering. FIRST decide whether the file actually is a restaurant food or drink menu (a list of dishes or drinks, usually with prices and/or sections). Return only valid JSON using this shape: {"is_menu":true,"document_type":"menu","document_note":"","categories":[{"name":"Category","items":[{"name":"Item","description":"","price":"0.00","variants":[{"name":"Small","price":"0.00"}],"modifiers":[{"name":"Choose sauce","type":"single","options":[{"name":"Hot","price":"0.00"}]}],"dietary":["veg"],"confidence":0.94,"warnings":[]}]}],"warnings":[]}. If the file is NOT a restaurant menu, set "is_menu" to false, set "document_type" to a short lowercase label for what it actually looks like (for example "receipt", "invoice", "flyer", "leaflet", "letter", "article", "photo", or "unknown document"), put a one-sentence explanation in "document_note", and return "categories":[]. Use currency ' . $currency . '. Preserve real categories. Treat sizes as variants. Treat toppings, add-ons, spice levels, and required choices as modifiers. ' . $desc_rule . 'If unsure, include a warning and lower confidence. Never invent prices. File content: ' . $text;
		}

		/**
		 * JSON schema for AI client.
		 *
		 * @return array
		 */
		protected static function get_menu_schema() {
			return array(
				'type'       => 'object',
				'properties' => array(
					'is_menu'       => array( 'type' => 'boolean' ),
					'document_type' => array( 'type' => 'string' ),
					'document_note' => array( 'type' => 'string' ),
					'categories' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'name'  => array( 'type' => 'string' ),
								'items' => array(
									'type'  => 'array',
									'items' => array(
										'type'       => 'object',
										'properties' => array(
											'name'        => array( 'type' => 'string' ),
											'description' => array( 'type' => 'string' ),
											'price'       => array( 'type' => 'string' ),
											'variants'    => array(
												'type'  => 'array',
												'items' => array(
													'type'       => 'object',
													'properties' => array(
														'name'  => array( 'type' => 'string' ),
														'price' => array( 'type' => 'string' ),
													),
													'required'   => array( 'name' ),
												),
											),
											'modifiers'   => array(
												'type'  => 'array',
												'items' => array(
													'type'       => 'object',
													'properties' => array(
														'name'    => array( 'type' => 'string' ),
														'type'    => array( 'type' => 'string' ),
														'options' => array(
															'type'  => 'array',
															'items' => array(
																'type'       => 'object',
																'properties' => array(
																	'name'  => array( 'type' => 'string' ),
																	'price' => array( 'type' => 'string' ),
																),
																'required'   => array( 'name' ),
															),
														),
													),
													'required'   => array( 'name', 'options' ),
												),
											),
											'dietary'     => array(
												'type'  => 'array',
												'items' => array( 'type' => 'string' ),
											),
											'confidence'  => array( 'type' => 'number' ),
											'warnings'    => array(
												'type'  => 'array',
												'items' => array( 'type' => 'string' ),
											),
										),
										'required'   => array( 'name' ),
									),
								),
							),
							'required'   => array( 'name', 'items' ),
						),
					),
					'warnings'   => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
				'required'   => array( 'categories' ),
			);
		}

		/**
		 * Decode JSON payload.
		 *
		 * @param string $json JSON.
		 * @return array|WP_Error
		 */
		protected static function json_to_payload( $json ) {
			$json = trim( preg_replace( '/^```(?:json)?|```$/m', '', $json ) );
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				return new WP_Error( 'rpress_ai_bad_json', __( 'AI returned an invalid menu format.', 'restropress' ) );
			}
			return $data;
		}

		/**
		 * Normalize AI or deterministic payload.
		 *
		 * @param array $payload Payload.
		 * @return array
		 */
		protected static function normalize_import_payload( $payload ) {
			$normalized = array(
				'categories' => array(),
				'warnings'   => isset( $payload['warnings'] ) && is_array( $payload['warnings'] ) ? array_values( $payload['warnings'] ) : array(),
				// Document classification from the AI. Absent on the deterministic
				// CSV/XLSX path, so default is_menu to true there.
				'is_menu'       => array_key_exists( 'is_menu', $payload ) ? (bool) $payload['is_menu'] : true,
				'document_type' => isset( $payload['document_type'] ) ? sanitize_text_field( $payload['document_type'] ) : '',
				'document_note' => isset( $payload['document_note'] ) ? sanitize_text_field( $payload['document_note'] ) : '',
			);

			if ( empty( $payload['categories'] ) || ! is_array( $payload['categories'] ) ) {
				$payload['categories'] = array(
					array(
						'name'  => __( 'Imported Menu', 'restropress' ),
						'items' => isset( $payload['items'] ) ? $payload['items'] : array(),
					),
				);
			}

			foreach ( $payload['categories'] as $category ) {
				if ( empty( $category['items'] ) || ! is_array( $category['items'] ) ) {
					continue;
				}

				$cat = array(
					'name'  => ! empty( $category['name'] ) ? sanitize_text_field( $category['name'] ) : __( 'Imported Menu', 'restropress' ),
					'items' => array(),
				);

				foreach ( $category['items'] as $item ) {
					if ( empty( $item['name'] ) ) {
						continue;
					}

					$warnings = isset( $item['warnings'] ) && is_array( $item['warnings'] ) ? array_values( $item['warnings'] ) : array();
					$price    = isset( $item['price'] ) ? self::clean_price( $item['price'] ) : '';
					if ( '' === $price && empty( $item['variants'] ) ) {
						$warnings[] = __( 'Missing price', 'restropress' );
					}

					$cat['items'][] = array(
						'name'        => sanitize_text_field( $item['name'] ),
						'description' => isset( $item['description'] ) ? sanitize_textarea_field( $item['description'] ) : '',
						'price'       => $price,
						'variants'    => self::normalize_variants( isset( $item['variants'] ) ? $item['variants'] : array() ),
						'modifiers'   => self::normalize_modifiers( isset( $item['modifiers'] ) ? $item['modifiers'] : array() ),
						'dietary'     => isset( $item['dietary'] ) && is_array( $item['dietary'] ) ? array_values( array_map( 'sanitize_text_field', $item['dietary'] ) ) : array(),
						'food_type'   => ( isset( $item['food_type'] ) && in_array( $item['food_type'], array( 'veg', 'non_veg' ), true ) ) ? $item['food_type'] : '',
						'image_id'    => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
						'confidence'  => isset( $item['confidence'] ) ? min( 1, max( 0, (float) $item['confidence'] ) ) : 0.7,
						'warnings'    => array_values( array_unique( $warnings ) ),
					);
				}

				if ( $cat['items'] ) {
					$normalized['categories'][] = $cat;
				}
			}

			return $normalized;
		}

		/**
		 * Normalize variants.
		 *
		 * @param array $variants Variants.
		 * @return array
		 */
		protected static function normalize_variants( $variants ) {
			if ( ! is_array( $variants ) ) {
				return array();
			}

			$out = array();
			foreach ( $variants as $variant ) {
				if ( empty( $variant['name'] ) ) {
					continue;
				}
				$out[] = array(
					'name'  => sanitize_text_field( $variant['name'] ),
					'price' => self::clean_price( isset( $variant['price'] ) ? $variant['price'] : '' ),
				);
			}
			return $out;
		}

		/**
		 * Normalize modifiers.
		 *
		 * @param array $modifiers Modifiers.
		 * @return array
		 */
		protected static function normalize_modifiers( $modifiers ) {
			if ( ! is_array( $modifiers ) ) {
				return array();
			}

			$out = array();
			foreach ( $modifiers as $modifier ) {
				if ( empty( $modifier['name'] ) ) {
					continue;
				}
				$options = array();
				if ( ! empty( $modifier['options'] ) && is_array( $modifier['options'] ) ) {
					foreach ( $modifier['options'] as $option ) {
						if ( empty( $option['name'] ) ) {
							continue;
						}
						$options[] = array(
							'name'  => sanitize_text_field( $option['name'] ),
							'price' => self::clean_price( isset( $option['price'] ) ? $option['price'] : '0' ),
						);
					}
				}
				$out[] = array(
					'name'    => sanitize_text_field( $modifier['name'] ),
					'type'    => ! empty( $modifier['type'] ) && 'single' === $modifier['type'] ? 'single' : 'multiple',
					'options' => $options,
				);
			}
			return $out;
		}

		/**
		 * Add duplicate warnings.
		 *
		 * @param array $payload Payload.
		 * @return array
		 */
		protected static function add_duplicate_warnings( $payload ) {
			foreach ( $payload['categories'] as $cat_index => $category ) {
				foreach ( $category['items'] as $item_index => $item ) {
					if ( self::find_duplicate_fooditem( $item['name'], $category['name'], $item['price'] ) ) {
						$payload['categories'][ $cat_index ]['items'][ $item_index ]['warnings'][] = __( 'Possible duplicate already exists', 'restropress' );
					}
					if ( $item['confidence'] < 0.75 ) {
						$payload['categories'][ $cat_index ]['items'][ $item_index ]['warnings'][] = __( 'Low AI confidence', 'restropress' );
					}
					$payload['categories'][ $cat_index ]['items'][ $item_index ]['warnings'] = array_values( array_unique( $payload['categories'][ $cat_index ]['items'][ $item_index ]['warnings'] ) );
				}
			}
			return $payload;
		}

		/**
		 * Publish payload into RestroPress menu structures.
		 *
		 * @param array  $payload Payload.
		 * @param string $mode Post status.
		 * @return array
		 */
		protected static function publish_payload( $payload, $mode ) {
			$result = array(
				'created' => 0,
				'skipped' => 0,
				'ids'     => array(),
			);

			if ( empty( $payload['categories'] ) || ! is_array( $payload['categories'] ) ) {
				return $result;
			}

			foreach ( $payload['categories'] as $category ) {
				$category_name = ! empty( $category['name'] ) ? sanitize_text_field( $category['name'] ) : __( 'Imported Menu', 'restropress' );
				$term_id       = self::ensure_term( $category_name, 'food-category' );

				if ( empty( $category['items'] ) || ! is_array( $category['items'] ) ) {
					continue;
				}

				foreach ( $category['items'] as $item ) {
					if ( empty( $item['name'] ) ) {
						continue;
					}

					$price = isset( $item['price'] ) ? self::clean_price( $item['price'] ) : '';
					if ( self::find_duplicate_fooditem( $item['name'], $category_name, $price ) ) {
						$result['skipped']++;
						continue;
					}

					$post_id = wp_insert_post(
						array(
							'post_type'    => 'fooditem',
							'post_status'  => $mode,
							'post_title'   => sanitize_text_field( $item['name'] ),
							'post_content' => isset( $item['description'] ) ? sanitize_textarea_field( $item['description'] ) : '',
							'post_excerpt' => isset( $item['description'] ) ? sanitize_textarea_field( $item['description'] ) : '',
							'post_author'  => get_current_user_id(),
						),
						true
					);

					if ( is_wp_error( $post_id ) ) {
						continue;
					}

					if ( $term_id ) {
						wp_set_post_terms( $post_id, array( $term_id ), 'food-category' );
					}

					// Per-item image chosen in review (attachment already in the media library).
					if ( ! empty( $item['image_id'] ) ) {
						$image_id = absint( $item['image_id'] );
						if ( $image_id && wp_attachment_is_image( $image_id ) ) {
							set_post_thumbnail( $post_id, $image_id );
						}
					}

					self::save_fooditem_prices( $post_id, $item );
					self::save_fooditem_modifiers( $post_id, isset( $item['modifiers'] ) ? $item['modifiers'] : array() );
					self::save_fooditem_tags( $post_id, isset( $item['dietary'] ) ? $item['dietary'] : array() );
					// Veg / non-veg marker (from CSV/AI/sample) → rpress_food_type.
					if ( ! empty( $item['food_type'] ) && in_array( $item['food_type'], array( 'veg', 'non_veg' ), true ) ) {
						update_post_meta( $post_id, 'rpress_food_type', sanitize_text_field( $item['food_type'] ) );
					}
					update_post_meta( $post_id, '_rpress_ai_imported', '1' );
					update_post_meta( $post_id, '_rpress_ai_confidence', isset( $item['confidence'] ) ? (float) $item['confidence'] : 0.7 );

					$result['created']++;
					$result['ids'][] = $post_id;
				}
			}

			return $result;
		}

		/**
		 * Save prices.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $item Item.
		 * @return void
		 */
		protected static function save_fooditem_prices( $post_id, $item ) {
			$variants = isset( $item['variants'] ) && is_array( $item['variants'] ) ? $item['variants'] : array();
			if ( $variants ) {
				$prices = array();
				foreach ( $variants as $index => $variant ) {
					$prices[ $index ] = array(
						'name'   => sanitize_text_field( $variant['name'] ),
						'amount' => self::clean_price( isset( $variant['price'] ) ? $variant['price'] : '' ),
					);
				}
				update_post_meta( $post_id, '_variable_pricing', 1 );
				update_post_meta( $post_id, '_rpress_price_options_mode', 'on' );
				update_post_meta( $post_id, 'rpress_variable_prices', $prices );
				update_post_meta( $post_id, 'rpress_price', self::lowest_price( $prices ) );
			} else {
				$price = self::clean_price( isset( $item['price'] ) ? $item['price'] : '' );
				update_post_meta( $post_id, 'rpress_price', $price );
				delete_post_meta( $post_id, '_variable_pricing' );
				delete_post_meta( $post_id, 'rpress_variable_prices' );
			}
		}

		/**
		 * Save add-ons as taxonomy terms.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $modifiers Modifiers.
		 * @return void
		 */
		protected static function save_fooditem_modifiers( $post_id, $modifiers ) {
			if ( empty( $modifiers ) || ! is_array( $modifiers ) ) {
				return;
			}

			$addon_terms = array();
			$addon_items = array();
			foreach ( $modifiers as $modifier ) {
				if ( empty( $modifier['name'] ) ) {
					continue;
				}

				$parent_id = self::ensure_term( $modifier['name'], 'addon_category' );
				if ( ! $parent_id ) {
					continue;
				}

				update_term_meta( $parent_id, '_type', isset( $modifier['type'] ) && 'single' === $modifier['type'] ? 'single' : 'multiple' );
				$addon_terms[] = $parent_id;
				$addon_items[ $parent_id ] = array(
					'category' => $parent_id,
					'items'    => array(),
				);

				if ( empty( $modifier['options'] ) || ! is_array( $modifier['options'] ) ) {
					continue;
				}

				foreach ( $modifier['options'] as $option ) {
					if ( empty( $option['name'] ) ) {
						continue;
					}
					$child_id = self::ensure_term( $option['name'], 'addon_category', $parent_id );
					if ( ! $child_id ) {
						continue;
					}
					update_term_meta( $child_id, '_price', self::clean_price( isset( $option['price'] ) ? $option['price'] : '0' ) );
					$addon_terms[] = $child_id;
					$addon_items[ $parent_id ]['items'][] = $child_id;
				}
			}

			if ( $addon_terms ) {
				wp_set_post_terms( $post_id, array_values( array_unique( $addon_terms ) ), 'addon_category', true );
				update_post_meta( $post_id, '_addon_items', $addon_items );
			}
		}

		/**
		 * Save AI dietary notes as RestroPress product tags.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $tags Tags.
		 * @return void
		 */
		protected static function save_fooditem_tags( $post_id, $tags ) {
			if ( empty( $tags ) || ! is_array( $tags ) ) {
				return;
			}

			$tag_names = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $tags ) ) ) );
			if ( empty( $tag_names ) ) {
				return;
			}

			// Dietary labels are a first-class taxonomy in 3.3; fall back to
			// product tags only if the dietary taxonomy isn't registered.
			$taxonomy = taxonomy_exists( 'dietary' ) ? 'dietary' : 'fooditem_tag';
			wp_set_post_terms( $post_id, $tag_names, $taxonomy, false );
		}

		/**
		 * Ensure a taxonomy term.
		 *
		 * @param string $name Term name.
		 * @param string $taxonomy Taxonomy.
		 * @param int    $parent Parent.
		 * @return int
		 */
		protected static function ensure_term( $name, $taxonomy, $parent = 0 ) {
			$existing = term_exists( $name, $taxonomy, $parent );
			if ( is_array( $existing ) && ! empty( $existing['term_id'] ) ) {
				return (int) $existing['term_id'];
			}
			if ( is_int( $existing ) ) {
				return $existing;
			}

			$term = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );
			if ( is_wp_error( $term ) ) {
				return 0;
			}
			return (int) $term['term_id'];
		}

		/**
		 * Find duplicate by name/category/price.
		 *
		 * @param string $name Name.
		 * @param string $category Category.
		 * @param string $price Price.
		 * @return int
		 */
		protected static function find_duplicate_fooditem( $name, $category, $price ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'fooditem',
					'post_status'    => array( 'publish', 'draft', 'pending' ),
					'title'          => sanitize_text_field( $name ),
					'posts_per_page' => 10,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			foreach ( $query->posts as $post_id ) {
				$item_price = self::clean_price( get_post_meta( $post_id, 'rpress_price', true ) );
				if ( $price && $item_price && $price !== $item_price ) {
					continue;
				}
				if ( has_term( $category, 'food-category', $post_id ) ) {
					return (int) $post_id;
				}
			}

			return 0;
		}

		/**
		 * Render import review.
		 *
		 * @param array $payload Payload.
		 * @param int   $job_id Job ID.
		 * @return string
		 */
		public static function render_review_html( $payload, $job_id ) {
			ob_start();
			?>
			<div class="rpress-ai-review" data-job-id="<?php echo esc_attr( $job_id ); ?>">
				<?php if ( ! empty( $payload['warnings'] ) ) : ?>
					<div class="rpress-ai-notice warning">
						<strong><?php esc_html_e( 'Review notes', 'restropress' ); ?></strong>
						<?php foreach ( $payload['warnings'] as $warning ) : ?>
							<p><?php echo esc_html( $warning ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php foreach ( $payload['categories'] as $cat_index => $category ) : ?>
					<section class="rpress-review-category" data-category-index="<?php echo esc_attr( $cat_index ); ?>">
						<div class="rpress-review-category-header">
							<label>
								<span><?php esc_html_e( 'Menu category', 'restropress' ); ?></span>
								<input class="rpress-category-name" type="text" value="<?php echo esc_attr( $category['name'] ); ?>" aria-label="<?php esc_attr_e( 'Category name', 'restropress' ); ?>">
							</label>
							<span class="rpress-review-count">
								<?php
								printf(
									/* translators: %d: item count */
									esc_html( _n( '%d product', '%d products', count( $category['items'] ), 'restropress' ) ),
									absint( count( $category['items'] ) )
								);
								?>
							</span>
						</div>
						<div class="rpress-review-items">
							<?php foreach ( $category['items'] as $item_index => $item ) : ?>
								<article class="rpress-review-item" data-item-index="<?php echo esc_attr( $item_index ); ?>">
									<header class="rpress-review-item-header">
										<span class="rpress-review-thumb" aria-hidden="true">
											<span class="dashicons dashicons-food"></span>
										</span>
										<div class="rpress-review-product-copy">
											<label>
												<span><?php esc_html_e( 'Product title', 'restropress' ); ?></span>
												<input class="rpress-item-name" type="text" value="<?php echo esc_attr( $item['name'] ); ?>" aria-label="<?php esc_attr_e( 'Product title', 'restropress' ); ?>">
											</label>
											<label>
												<span><?php esc_html_e( 'Product description', 'restropress' ); ?></span>
												<textarea class="rpress-item-description" aria-label="<?php esc_attr_e( 'Product description', 'restropress' ); ?>"><?php echo esc_textarea( $item['description'] ); ?></textarea>
											</label>
										</div>
										<div class="rpress-review-product-price">
											<span class="rpress-confidence"><?php echo esc_html( round( (float) $item['confidence'] * 100 ) ); ?>%</span>
											<label>
											<span><?php esc_html_e( 'Product price', 'restropress' ); ?></span>
											<input class="rpress-item-price" type="text" value="<?php echo esc_attr( $item['price'] ); ?>" aria-label="<?php esc_attr_e( 'Product price', 'restropress' ); ?>">
											<small><?php echo ! empty( $item['variants'] ) ? esc_html__( 'Base/fallback price', 'restropress' ) : esc_html__( 'Fixed price', 'restropress' ); ?></small>
											</label>
										</div>
									</header>

									<?php if ( ! empty( $item['variants'] ) || ! empty( $item['modifiers'] ) ) : ?>
										<div class="rpress-review-detail-grid">
											<?php if ( ! empty( $item['variants'] ) ) : ?>
												<section class="rpress-review-detail">
													<header>
														<strong><?php esc_html_e( 'Variable prices', 'restropress' ); ?></strong>
														<span><?php esc_html_e( 'Review sizes/options', 'restropress' ); ?></span>
													</header>
												<div class="rpress-review-table">
													<div class="rpress-review-table-head">
														<span><?php esc_html_e( 'Variant name', 'restropress' ); ?></span>
														<span><?php esc_html_e( 'Price', 'restropress' ); ?></span>
													</div>
													<?php foreach ( $item['variants'] as $variant ) : ?>
														<div class="rpress-review-variant">
															<input class="rpress-variant-name" type="text" value="<?php echo esc_attr( isset( $variant['name'] ) ? $variant['name'] : '' ); ?>" aria-label="<?php esc_attr_e( 'Variant name', 'restropress' ); ?>">
															<input class="rpress-variant-price" type="text" value="<?php echo esc_attr( isset( $variant['price'] ) ? $variant['price'] : '' ); ?>" aria-label="<?php esc_attr_e( 'Variant price', 'restropress' ); ?>">
														</div>
													<?php endforeach; ?>
												</div>
												</section>
											<?php endif; ?>

											<?php if ( ! empty( $item['modifiers'] ) ) : ?>
												<section class="rpress-review-detail">
													<header>
														<strong><?php esc_html_e( 'Add-ons', 'restropress' ); ?></strong>
														<span><?php esc_html_e( 'Review option groups', 'restropress' ); ?></span>
													</header>
												<?php foreach ( $item['modifiers'] as $modifier ) : ?>
													<div class="rpress-review-modifier">
														<div class="rpress-modifier-heading">
															<input class="rpress-modifier-name" type="text" value="<?php echo esc_attr( isset( $modifier['name'] ) ? $modifier['name'] : '' ); ?>" aria-label="<?php esc_attr_e( 'Add-on group name', 'restropress' ); ?>">
															<select class="rpress-modifier-type" aria-label="<?php esc_attr_e( 'Add-on selection type', 'restropress' ); ?>">
																<option value="multiple" <?php selected( isset( $modifier['type'] ) ? $modifier['type'] : '', 'multiple' ); ?>><?php esc_html_e( 'Multiple choice', 'restropress' ); ?></option>
																<option value="single" <?php selected( isset( $modifier['type'] ) ? $modifier['type'] : '', 'single' ); ?>><?php esc_html_e( 'Single choice', 'restropress' ); ?></option>
															</select>
														</div>
														<?php if ( ! empty( $modifier['options'] ) && is_array( $modifier['options'] ) ) : ?>
															<div class="rpress-review-table">
																<div class="rpress-review-table-head">
																	<span><?php esc_html_e( 'Option', 'restropress' ); ?></span>
																	<span><?php esc_html_e( 'Extra price', 'restropress' ); ?></span>
																</div>
																<?php foreach ( $modifier['options'] as $option ) : ?>
																	<div class="rpress-review-modifier-option">
																		<input class="rpress-modifier-option-name" type="text" value="<?php echo esc_attr( isset( $option['name'] ) ? $option['name'] : '' ); ?>" aria-label="<?php esc_attr_e( 'Add-on option name', 'restropress' ); ?>">
																		<input class="rpress-modifier-option-price" type="text" value="<?php echo esc_attr( isset( $option['price'] ) ? $option['price'] : '0' ); ?>" aria-label="<?php esc_attr_e( 'Add-on option price', 'restropress' ); ?>">
																	</div>
																<?php endforeach; ?>
															</div>
														<?php endif; ?>
													</div>
												<?php endforeach; ?>
												</section>
											<?php endif; ?>
										</div>
									<?php endif; ?>

									<label class="rpress-review-tags">
										<span><?php esc_html_e( 'Tags', 'restropress' ); ?></span>
										<input class="rpress-item-dietary" type="text" value="<?php echo esc_attr( ! empty( $item['dietary'] ) && is_array( $item['dietary'] ) ? implode( ', ', $item['dietary'] ) : '' ); ?>" placeholder="<?php esc_attr_e( 'veg, vegan, spicy, gluten-free', 'restropress' ); ?>">
										<small><?php esc_html_e( 'Comma-separated values will be published as RestroPress product tags, not menu categories.', 'restropress' ); ?></small>
									</label>

									<?php if ( ! empty( $item['warnings'] ) ) : ?>
										<ul class="rpress-item-warnings">
											<?php foreach ( $item['warnings'] as $warning ) : ?>
												<li><?php echo esc_html( $warning ); ?></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Launch checklist.
		 *
		 * @return array
		 */
		public static function get_launch_checklist() {
			$state       = self::get_state();
			$items_count = wp_count_posts( 'fooditem' );
			$published   = isset( $items_count->publish ) ? (int) $items_count->publish : 0;
			$service     = rpress_get_option( 'enable_service', '' );
			$currency    = rpress_get_option( 'currency', '' );
			$email       = rpress_get_option( 'enable_order_notification', '' );
			$payment     = self::has_payment_path();
			$tested      = ! empty( $state['test_order_confirmed'] ) || 'launched' === $state['status'];

			return array(
				array( 'id' => 'profile', 'label' => __( 'Restaurant profile saved', 'restropress' ), 'done' => ! empty( $currency ) ),
				array( 'id' => 'service', 'label' => __( 'Ordering services configured', 'restropress' ), 'done' => ! empty( $service ) ),
				array( 'id' => 'menu', 'label' => __( 'Menu has publishable items', 'restropress' ), 'done' => $published > 0 ),
				array( 'id' => 'payments', 'label' => __( 'Payment path selected', 'restropress' ), 'done' => ! empty( $payment ) ),
				array( 'id' => 'operations', 'label' => __( 'Order notifications configured', 'restropress' ), 'done' => ! empty( $email ) ),
				array( 'id' => 'test_order', 'label' => __( 'Internal test order confirmed', 'restropress' ), 'done' => $tested ),
			);
		}

		/**
		 * Launch Hub tasks for the free onboarding experience.
		 *
		 * @param array        $state Onboarding state.
		 * @param WP_Post|null $latest Latest import job.
		 * @param string       $latest_status Latest import status.
		 * @param array        $ai_status AI provider status.
		 * @return array
		 */
		public static function get_launch_tasks( $state, $latest = null, $latest_status = '', $ai_status = array() ) {
			$items_count = wp_count_posts( 'fooditem' );
			$published   = isset( $items_count->publish ) ? (int) $items_count->publish : 0;
			$service     = rpress_get_option( 'enable_service', '' );
			$currency    = rpress_get_option( 'currency', '' );
			$country     = rpress_get_option( 'base_country', '' );
			$address     = rpress_get_option( 'store_address', '' );
			$open_time   = rpress_get_option( 'open_time', '' );
			$close_time  = rpress_get_option( 'close_time', '' );
			$email       = rpress_get_option( 'admin_notice_emails', get_option( 'admin_email' ) );
			$alerts      = rpress_get_option( 'enable_order_notification', '' );
			$payment     = self::has_payment_path();
			$completed   = isset( $state['completed_tasks'] ) && is_array( $state['completed_tasks'] ) ? $state['completed_tasks'] : array();
			$launch_goal = isset( $state['launch_goal'] ) ? sanitize_key( $state['launch_goal'] ) : '';
			$menu_path   = isset( $state['menu_setup_path'] ) ? sanitize_key( $state['menu_setup_path'] ) : 'ai_import';
			$ai_ready    = ! empty( $ai_status['ready'] );
			$has_import  = $latest instanceof WP_Post && 'needs_review' === $latest_status;
			$has_import_attempt = $latest instanceof WP_Post;
			$test_order_confirmed = ! empty( $state['test_order_confirmed'] ) || 'launched' === $state['status'];
			$welcome_ready = 'launched' === $state['status'] || ! empty( $launch_goal ) || in_array( 'welcome', $completed, true );
			$profile_ready = ! empty( $currency ) && ! empty( $country ) && ! empty( $address );
			$menu_ready    = $published > 0 || $has_import;
			$review_ready  = $published > 0;
			$ordering_ready = ! empty( $service ) && '' !== rpress_get_option( 'prep_time', '' );
			$hours_ready   = ! empty( $open_time ) && ! empty( $close_time );
			$payments_ready = ! empty( $payment );
			$operations_ready = ! empty( $alerts ) && is_email( $email );
			$launch_ready = $welcome_ready && $profile_ready && $menu_ready && $review_ready && $ordering_ready && $hours_ready && $payments_ready && $operations_ready && $test_order_confirmed;

			$tasks = array(
				'welcome'    => array(
					'id'       => 'welcome',
					'label'    => __( 'Start setup', 'restropress' ),
					'kicker'   => __( 'Launch goal', 'restropress' ),
					'summary'  => __( 'Choose the first ordering model and menu setup path so RestroPress can guide the restaurant correctly.', 'restropress' ),
					'step'     => 'welcome',
					'ready'    => $welcome_ready,
					'started'  => ! empty( $launch_goal ),
					'blocking' => true,
				),
				'profile'    => array(
					'id'       => 'profile',
					'label'    => __( 'Restaurant profile', 'restropress' ),
					'kicker'   => __( 'Foundation', 'restropress' ),
					'summary'  => __( 'Name, cuisine, address, country, state, currency, and WordPress timezone.', 'restropress' ),
					'step'     => 'profile',
					'ready'    => $profile_ready,
					'started'  => ! empty( $currency ) || ! empty( $country ) || ! empty( $address ),
					'blocking' => true,
				),
				'menu'       => array(
					'id'       => 'menu',
					'label'    => __( 'Create menu', 'restropress' ),
					'kicker'   => __( 'Menu', 'restropress' ),
					'summary'  => __( 'Import an existing menu with AI or use structured CSV/XLSX as the free fallback.', 'restropress' ),
					'step'     => 'menu',
					'ready'    => $menu_ready,
					'started'  => $has_import_attempt || $published > 0,
					'attention' => 'ai_import' === $menu_path && ! $ai_ready && 0 === $published && ! $has_import,
					'blocking' => true,
				),
				'review'     => array(
					'id'       => 'review',
					'label'    => __( 'Review menu', 'restropress' ),
					'kicker'   => __( 'Human approval', 'restropress' ),
					'summary'  => __( 'Approve categories, item names, prices, variants, add-ons, tags, and warnings before publishing.', 'restropress' ),
					'step'     => 'review',
					'ready'    => $review_ready,
					'started'  => $has_import,
					'blocking' => true,
				),
				'ordering'   => array(
					'id'       => 'ordering',
					'label'    => __( 'Ordering rules', 'restropress' ),
					'kicker'   => __( 'Service model', 'restropress' ),
					'summary'  => __( 'Pickup, delivery, ASAP/preorder behavior, prep time, and minimum order rules.', 'restropress' ),
					'step'     => 'ordering',
					'ready'    => $ordering_ready,
					'started'  => ! empty( $service ),
					'blocking' => true,
				),
				'hours'      => array(
					'id'       => 'hours',
					'label'    => __( 'Ordering hours', 'restropress' ),
					'kicker'   => __( 'Availability', 'restropress' ),
					'summary'  => __( 'Set when the restaurant accepts online orders. Holiday and daypart controls can grow from here.', 'restropress' ),
					'step'     => 'hours',
					'ready'    => $hours_ready,
					'started'  => ! empty( $open_time ) || ! empty( $close_time ),
					'blocking' => true,
				),
				'payments'   => array(
					'id'       => 'payments',
					'label'    => __( 'Payment path', 'restropress' ),
					'kicker'   => __( 'Checkout', 'restropress' ),
					'summary'  => __( 'Choose test/live behavior and keep pay-by-cash available for a solid free launch.', 'restropress' ),
					'step'     => 'payments',
					'ready'    => $payments_ready,
					'started'  => $payments_ready || '' !== rpress_get_option( 'test_mode', '' ),
					'attention' => ! $payments_ready,
					'blocking' => true,
				),
				'operations' => array(
					'id'       => 'operations',
					'label'    => __( 'Order operations', 'restropress' ),
					'kicker'   => __( 'Kitchen readiness', 'restropress' ),
					'summary'  => __( 'Order alerts, admin recipient email, and basic printing readiness.', 'restropress' ),
					'step'     => 'operations',
					'ready'    => $operations_ready,
					'started'  => ! empty( $alerts ) || ! empty( $email ),
					'attention' => ! empty( $email ) && ! is_email( $email ),
					'blocking' => true,
				),
				'launch'     => array(
					'id'       => 'launch',
					'label'    => __( 'Test and launch', 'restropress' ),
					'kicker'   => __( 'Go live', 'restropress' ),
					'summary'  => __( 'Preview the menu, place a test order, confirm alerts, and mark the setup ready.', 'restropress' ),
					'step'     => 'launch',
					'ready'    => $launch_ready,
					'complete' => 'launched' === $state['status'] && $launch_ready,
					'started'  => 'launch' === $state['current_step'] || $test_order_confirmed,
					'attention' => ! $launch_ready,
					'blocking' => false,
				),
			);

			foreach ( $tasks as $id => $task ) {
				$tasks[ $id ]['status'] = self::get_launch_task_status( $task, $completed, $state );
			}

			return $tasks;
		}

		/**
		 * Resolve a task status.
		 *
		 * @param array $task Task.
		 * @param array $completed Completed tasks.
		 * @param array $state Onboarding state.
		 * @return string
		 */
		protected static function get_launch_task_status( $task, $completed, $state ) {
			$ready = ! empty( $task['ready'] );
			if ( ! empty( $task['complete'] ) || ( $ready && ( in_array( $task['id'], $completed, true ) || 'launched' === $state['status'] ) ) ) {
				return 'complete';
			}

			if ( $ready ) {
				return 'ready';
			}

			if ( ! empty( $task['attention'] ) ) {
				return 'needs_attention';
			}

			if ( ! empty( $task['started'] ) || $task['step'] === $state['current_step'] ) {
				return 'in_progress';
			}

			return 'not_started';
		}

		/**
		 * Clean a price string.
		 *
		 * @param mixed $price Raw price.
		 * @return string
		 */
		protected static function clean_price( $price ) {
			$price = preg_replace( '/[^0-9.,-]/', '', (string) $price );
			$price = str_replace( ',', '', $price );
			if ( '' === $price ) {
				return '';
			}
			return number_format( (float) $price, 2, '.', '' );
		}

		/**
		 * Parse a spreadsheet "variants" cell into variant rows.
		 *
		 * Format: "Small:3.50; Large:4.50" - name:price pairs separated by ";".
		 * (Pipe "|" is reserved as the column delimiter, so it cannot appear here.)
		 *
		 * @param string $raw Cell value.
		 * @return array
		 */
		protected static function parse_variants_cell( $raw ) {
			$out = array();
			if ( '' === trim( (string) $raw ) ) {
				return $out;
			}
			foreach ( preg_split( '/;/', $raw ) as $pair ) {
				$pair = trim( $pair );
				if ( '' === $pair ) {
					continue;
				}
				$bits  = explode( ':', $pair );
				$price = count( $bits ) > 1 ? array_pop( $bits ) : '';
				$name  = trim( implode( ':', $bits ) );
				if ( '' === $name ) {
					continue;
				}
				$out[] = array(
					'name'  => sanitize_text_field( $name ),
					'price' => self::clean_price( $price ),
				);
			}
			return $out;
		}

		/**
		 * Parse a spreadsheet "addons" cell into modifier groups.
		 *
		 * Format: "Group [single|multiple]: Opt:price, Opt:price; Group2 [..]: ..."
		 *  - Groups separated by ";".
		 *  - Each group: "Name [type]: option:price, option:price".
		 *  - Options separated by ",". Type defaults to "multiple".
		 *
		 * @param string $raw Cell value.
		 * @return array
		 */
		protected static function parse_modifiers_cell( $raw ) {
			$out = array();
			if ( '' === trim( (string) $raw ) ) {
				return $out;
			}
			foreach ( preg_split( '/;/', $raw ) as $group ) {
				$group = trim( $group );
				if ( '' === $group ) {
					continue;
				}
				// Split header (name + optional [type]) from the options list.
				$parts    = explode( ':', $group, 2 );
				$head     = trim( $parts[0] );
				$opts_str = isset( $parts[1] ) ? trim( $parts[1] ) : '';
				$type     = 'multiple';
				if ( preg_match( '/\[(single|multiple)\]/i', $head, $m ) ) {
					$type = strtolower( $m[1] );
					$head = trim( preg_replace( '/\[(single|multiple)\]/i', '', $head ) );
				}
				if ( '' === $head ) {
					continue;
				}
				$options = array();
				foreach ( preg_split( '/,/', $opts_str ) as $opt ) {
					$opt = trim( $opt );
					if ( '' === $opt ) {
						continue;
					}
					$bits  = explode( ':', $opt );
					$price = count( $bits ) > 1 ? array_pop( $bits ) : '';
					$oname = trim( implode( ':', $bits ) );
					if ( '' === $oname ) {
						continue;
					}
					$options[] = array(
						'name'  => sanitize_text_field( $oname ),
						'price' => self::clean_price( $price ),
					);
				}
				$out[] = array(
					'name'    => sanitize_text_field( $head ),
					'type'    => $type,
					'options' => $options,
				);
			}
			return $out;
		}

		/**
		 * Get first available row value.
		 *
		 * @param array $row Row.
		 * @param array $keys Keys.
		 * @return string
		 */
		protected static function first_value( $row, $keys ) {
			foreach ( $keys as $key ) {
				if ( isset( $row[ $key ] ) && '' !== $row[ $key ] ) {
					return sanitize_text_field( $row[ $key ] );
				}
			}
			return '';
		}

		/**
		 * Lowest variable price.
		 *
		 * @param array $prices Prices.
		 * @return string
		 */
		protected static function lowest_price( $prices ) {
			$amounts = array();
			foreach ( $prices as $price ) {
				if ( isset( $price['amount'] ) && '' !== $price['amount'] ) {
					$amounts[] = (float) $price['amount'];
				}
			}
			return $amounts ? number_format( min( $amounts ), 2, '.', '' ) : '';
		}

		/**
		 * Recursive sanitize.
		 *
		 * @param mixed $value Value.
		 * @return mixed
		 */
		protected static function sanitize_deep( $value ) {
			if ( is_array( $value ) ) {
				return array_map( array( __CLASS__, 'sanitize_deep' ), $value );
			}
			return is_scalar( $value ) ? sanitize_text_field( $value ) : '';
		}
	}

	RPress_Onboarding::init();
}
