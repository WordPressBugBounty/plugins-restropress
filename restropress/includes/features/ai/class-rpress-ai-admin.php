<?php
/**
 * AI admin touchpoints: settings section, food item editor helpers and the
 * bulk description review screen.
 *
 * Every AI result is shown to the merchant first. Editor helpers only fill
 * form fields (WordPress saves them when the merchant clicks Update), the
 * bulk screen saves only the rows the merchant accepts, and no handler here
 * changes a post status or a price.
 *
 * @package RPRESS\Features\AI
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'RPRESS_AI_Admin' ) ) {
	/**
	 * Admin UI and AJAX for RPRESS_AI.
	 */
	class RPRESS_AI_Admin {
		const NONCE       = 'rpress_ai';
		const REVIEW_PAGE = 'rpress-ai-descriptions';
		const BULK_ACTION = 'rpress_ai_descriptions';
		const SECTION     = 'ai_assistant';

		/**
		 * Boot hooks.
		 *
		 * @return void
		 */
		public static function init() {
			add_filter( 'rpress_settings_sections_general', array( __CLASS__, 'register_section' ) );
			add_filter( 'rpress_registered_settings', array( __CLASS__, 'register_settings' ) );
			add_action( 'rpress_ai_assistant_panel', array( __CLASS__, 'render_settings_panel' ) );
			add_filter( 'rpress_settings_general-' . self::SECTION . '_sanitize', array( __CLASS__, 'save_settings_section' ) );

			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			add_action( 'admin_menu', array( __CLASS__, 'register_review_page' ), 20 );
			add_filter( 'bulk_actions-edit-fooditem', array( __CLASS__, 'register_bulk_action' ) );
			add_filter( 'handle_bulk_actions-edit-fooditem', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );

			add_action( 'wp_ajax_rpress_ai_test', array( __CLASS__, 'ajax_test' ) );
			add_action( 'wp_ajax_rpress_ai_write', array( __CLASS__, 'ajax_write' ) );
			add_action( 'wp_ajax_rpress_ai_suggest', array( __CLASS__, 'ajax_suggest' ) );
			add_action( 'wp_ajax_rpress_ai_alt_text', array( __CLASS__, 'ajax_alt_text' ) );
			add_action( 'wp_ajax_rpress_ai_save_alt', array( __CLASS__, 'ajax_save_alt' ) );
			add_action( 'wp_ajax_rpress_ai_bulk_generate', array( __CLASS__, 'ajax_bulk_generate' ) );
			add_action( 'wp_ajax_rpress_ai_bulk_save', array( __CLASS__, 'ajax_bulk_save' ) );
		}

		/* ------------------------------------------------------------------
		 * Settings: RestroPress > Settings > General > AI Assistant
		 * ------------------------------------------------------------------ */

		/**
		 * Add the section tab.
		 *
		 * @param array $sections General sections.
		 * @return array
		 */
		public static function register_section( $sections ) {
			$sections[ self::SECTION ] = esc_html__( 'AI Assistant', 'restropress' );
			return $sections;
		}

		/**
		 * One hook field renders the panel. Values live in the
		 * `rpress_ai_settings` option (shared with the menu importer), not in
		 * `rpress_settings`, so the panel posts its own `rpress_ai[...]` fields.
		 *
		 * @param array $settings Registered settings.
		 * @return array
		 */
		public static function register_settings( $settings ) {
			$settings['general'][ self::SECTION ] = array(
				'ai_assistant_panel' => array(
					'id'   => 'ai_assistant_panel',
					'name' => '',
					'type' => 'hook',
				),
			);
			return $settings;
		}

		/**
		 * Render the settings panel.
		 *
		 * @return void
		 */
		public static function render_settings_panel() {
			$settings  = RPRESS_AI::get_settings();
			$status    = RPRESS_AI::get_status();
			$labels    = RPRESS_AI::provider_labels();
			$key_hints = array();
			foreach ( array( 'openai', 'gemini', 'claude' ) as $provider ) {
				$key_hints[ $provider ] = RPRESS_AI::key_hint( $provider );
			}
			include __DIR__ . '/views/settings-panel.php';
		}

		/**
		 * Save the panel when the settings form posts (options.php has already
		 * checked the settings nonce and the manage_shop_settings capability).
		 *
		 * @param array $input rpress_settings input, returned unchanged.
		 * @return array
		 */
		public static function save_settings_section( $input ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by options.php (settings API).
			if ( ! isset( $_POST['rpress_ai'] ) || ! is_array( $_POST['rpress_ai'] ) || ! current_user_can( 'manage_shop_settings' ) ) {
				return $input;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
			RPRESS_AI::save_settings( self::sanitize_settings_input( wp_unslash( $_POST['rpress_ai'] ) ) );
			return $input;
		}

		/**
		 * Sanitize posted AI settings.
		 *
		 * @param array $raw Raw values.
		 * @return array
		 */
		protected static function sanitize_settings_input( $raw ) {
			$raw  = is_array( $raw ) ? $raw : array();
			$data = array();
			foreach ( array( 'enabled', 'provider', 'api_key', 'model', 'generate_descriptions', 'remove_key' ) as $key ) {
				if ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ) {
					$data[ $key ] = sanitize_text_field( (string) $raw[ $key ] );
				}
			}
			if ( isset( $raw['features'] ) && is_array( $raw['features'] ) ) {
				$data['features'] = array();
				foreach ( RPRESS_AI::FEATURES as $feature ) {
					$data['features'][ $feature ] = isset( $raw['features'][ $feature ] ) ? sanitize_key( $raw['features'][ $feature ] ) : '';
				}
			}
			return $data;
		}

		/* ------------------------------------------------------------------
		 * Assets
		 * ------------------------------------------------------------------ */

		/**
		 * Which admin screen we are on, from the AI feature's point of view.
		 *
		 * @return string settings|editor|review|''
		 */
		protected static function context() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( 'rpress-settings' === $page && isset( $_GET['section'] ) && self::SECTION === sanitize_key( wp_unslash( $_GET['section'] ) ) ) {
				return 'settings';
			}
			// phpcs:enable
			if ( self::REVIEW_PAGE === $page ) {
				return 'review';
			}
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && 'post' === $screen->base && 'fooditem' === $screen->post_type ) {
				return 'editor';
			}
			return '';
		}

		/**
		 * Enqueue the AI assets where they are used.
		 *
		 * @return void
		 */
		public static function enqueue() {
			$context = self::context();
			if ( '' === $context ) {
				return;
			}
			if ( 'editor' === $context && ( ! current_user_can( 'edit_products' ) || ! self::any_editor_feature() ) ) {
				return;
			}

			$css = RP_PLUGIN_DIR . 'assets/features/ai/ai-admin.css';
			$js  = RP_PLUGIN_DIR . 'assets/features/ai/ai-admin.js';
			wp_enqueue_style( 'rpress-ai-admin', RP_PLUGIN_URL . 'assets/features/ai/ai-admin.css', array(), file_exists( $css ) ? (string) filemtime( $css ) : RP_VERSION );
			wp_enqueue_script( 'rpress-ai-admin', RP_PLUGIN_URL . 'assets/features/ai/ai-admin.js', array( 'jquery' ), file_exists( $js ) ? (string) filemtime( $js ) : RP_VERSION, true );
			wp_localize_script( 'rpress-ai-admin', 'rpressAI', self::script_data( $context ) );
		}

		/**
		 * Whether any food item editor helper is switched on.
		 *
		 * @return bool
		 */
		protected static function any_editor_feature() {
			return RPRESS_AI::feature_enabled( 'writer' ) || RPRESS_AI::feature_enabled( 'dietary' ) || RPRESS_AI::feature_enabled( 'alt_text' );
		}

		/**
		 * Data for ai-admin.js.
		 *
		 * @param string $context Screen context.
		 * @return array
		 */
		protected static function script_data( $context ) {
			$status = RPRESS_AI::get_status();
			$data   = array(
				'context'     => $context,
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::NONCE ),
				'ready'       => ! empty( $status['ready'] ),
				'settingsUrl' => RPRESS_AI::settings_url(),
				'features'    => array(
					'writer'   => RPRESS_AI::feature_enabled( 'writer' ),
					'dietary'  => RPRESS_AI::feature_enabled( 'dietary' ),
					'alt_text' => RPRESS_AI::feature_enabled( 'alt_text' ),
					'bulk'     => RPRESS_AI::feature_enabled( 'bulk' ),
				),
				'i18n'        => array(
					'write'           => __( 'Write with AI', 'restropress' ),
					'improve'         => __( 'Improve', 'restropress' ),
					'working'         => __( 'Writing...', 'restropress' ),
					'filled'          => __( 'AI draft added. Review and edit it, then click Update to save.', 'restropress' ),
					'needName'        => __( 'Add the item name first so AI knows what to write about.', 'restropress' ),
					'needText'        => __( 'There is no text to improve yet. Use Write with AI instead.', 'restropress' ),
					'suggestDietary'  => __( 'Suggest dietary labels', 'restropress' ),
					'suggestAllergen' => __( 'Suggest allergens', 'restropress' ),
					'suggesting'      => __( 'Checking...', 'restropress' ),
					'suggested'       => __( 'AI pre-selected:', 'restropress' ),
					'notInList'       => __( 'Also suggested (not in your list, add them yourself if correct):', 'restropress' ),
					'noSuggestion'    => __( 'AI found nothing it could confirm from the name and description.', 'restropress' ),
					'verify'          => __( 'Suggestions only. Check with your kitchen, then click Update to save.', 'restropress' ),
					'foodType'        => __( 'Food type set to:', 'restropress' ),
					'veg'             => __( 'Veg', 'restropress' ),
					'nonVeg'          => __( 'Non Veg', 'restropress' ),
					'altGenerate'     => __( 'Generate alt text', 'restropress' ),
					'altLabel'        => __( 'Image alt text (review before saving)', 'restropress' ),
					'altSave'         => __( 'Save alt text', 'restropress' ),
					'altSaved'        => __( 'Alt text saved.', 'restropress' ),
					'openSettings'    => __( 'Open AI settings', 'restropress' ),
					'testing'         => __( 'Testing connection...', 'restropress' ),
					'showKey'         => __( 'Show API key', 'restropress' ),
					'hideKey'         => __( 'Hide API key', 'restropress' ),
					/* translators: %s: last four characters of the saved API key */
					'savedKey'        => __( 'Saved key ending in %s. Enter a new key to replace it.', 'restropress' ),
					'pasteKey'        => __( 'Paste your provider API key', 'restropress' ),
					'error'           => __( 'The AI request failed. Please try again.', 'restropress' ),
					/* translators: 1: current item number, 2: total number of items */
					'generating'      => __( 'Generating %1$d of %2$d...', 'restropress' ),
					'generated'       => __( 'Drafts ready. Review them, then save the ones you accept.', 'restropress' ),
					'saving'          => __( 'Saving...', 'restropress' ),
					/* translators: %d: number of menu items updated (always more than one) */
					'saved'           => __( '%d items updated.', 'restropress' ),
					'savedOne'        => __( '1 item updated.', 'restropress' ),
					'nothingAccepted' => __( 'Tick Accept on at least one draft first.', 'restropress' ),
					'failedRow'       => __( 'Could not generate this one.', 'restropress' ),
				),
			);

			if ( 'settings' === $context ) {
				$settings       = RPRESS_AI::get_settings();
				$data['models'] = array();
				foreach ( array( 'openai', 'gemini', 'claude' ) as $provider ) {
					$data['models'][ $provider ] = array(
						'saved'   => isset( $settings['models'][ $provider ] ) ? (string) $settings['models'][ $provider ] : '',
						'default' => RPRESS_AI::default_model( $provider ),
						'keyHint' => RPRESS_AI::key_hint( $provider ),
					);
				}
			}

			if ( 'editor' === $context ) {
				$data['allergenTerms'] = taxonomy_exists( 'rpress_allergen' ) ? self::term_names( 'rpress_allergen' ) : array();
				$data['hasAllergens']  = taxonomy_exists( 'rpress_allergen' );
				$data['hasDietary']    = taxonomy_exists( 'dietary' );
				$post                  = get_post();
				$thumb_id              = $post ? (int) get_post_thumbnail_id( $post ) : 0;
				$data['thumbId']       = $thumb_id;
				$data['thumbAlt']      = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
			}

			return $data;
		}

		/* ------------------------------------------------------------------
		 * AJAX helpers
		 * ------------------------------------------------------------------ */

		/**
		 * Nonce + capability guard.
		 *
		 * @param string $capability Capability.
		 * @return void
		 */
		protected static function verify( $capability ) {
			if ( ! current_user_can( $capability ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'restropress' ) ), 403 );
			}
			check_ajax_referer( self::NONCE, 'nonce' );
		}

		/**
		 * Stop unless a feature is on and a provider is ready.
		 *
		 * @param string $feature Feature key.
		 * @return void
		 */
		protected static function require_feature( $feature ) {
			if ( ! RPRESS_AI::feature_enabled( $feature ) ) {
				self::send_error( new WP_Error( 'rpress_ai_feature_disabled', __( 'This AI feature is switched off. Turn it on under RestroPress > Settings > AI Assistant.', 'restropress' ) ) );
			}
			if ( ! RPRESS_AI::is_ready() ) {
				self::send_error( RPRESS_AI::not_configured_error() );
			}
		}

		/**
		 * Send a normalised AI error.
		 *
		 * @param WP_Error $error Error.
		 * @return void
		 */
		protected static function send_error( $error ) {
			$settings = RPRESS_AI::get_settings();
			wp_send_json_error( RPRESS_AI::error_details( $error, $settings['provider'], 'assistant' ), 400 );
		}

		/**
		 * Food item post the user may edit, or 0 for a new unsaved item.
		 *
		 * @param int $post_id Post ID.
		 * @return int
		 */
		protected static function editable_fooditem( $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id ) {
				return 0;
			}
			if ( 'fooditem' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this menu item.', 'restropress' ) ), 403 );
			}
			return $post_id;
		}

		/**
		 * Item details posted from the editor (unsaved form state wins over the
		 * stored post so the merchant can generate before the first save).
		 *
		 * @param int $post_id Post ID (0 for new).
		 * @return array name, categories, price, excerpt, content
		 */
		protected static function posted_context( $post_id ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in verify().
			$raw  = isset( $_POST['item'] ) && is_array( $_POST['item'] ) ? wp_unslash( $_POST['item'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			// phpcs:enable
			$item = array(
				'name'       => isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '',
				'categories' => isset( $raw['categories'] ) && is_array( $raw['categories'] ) ? array_map( 'sanitize_text_field', $raw['categories'] ) : array(),
				'price'      => isset( $raw['price'] ) ? sanitize_text_field( $raw['price'] ) : '',
				'excerpt'    => isset( $raw['excerpt'] ) ? sanitize_textarea_field( $raw['excerpt'] ) : '',
				'content'    => isset( $raw['content'] ) ? sanitize_textarea_field( wp_strip_all_tags( $raw['content'] ) ) : '',
			);
			if ( $post_id ) {
				$stored = self::stored_context( $post_id );
				foreach ( array( 'name', 'categories', 'price' ) as $key ) {
					if ( empty( $item[ $key ] ) ) {
						$item[ $key ] = $stored[ $key ];
					}
				}
			}
			return $item;
		}

		/**
		 * Item details from the database.
		 *
		 * @param int $post_id Post ID.
		 * @return array
		 */
		public static function stored_context( $post_id ) {
			$post  = get_post( $post_id );
			$terms = get_the_terms( $post_id, 'food-category' );
			$price = '';
			if ( function_exists( 'rpress_has_variable_prices' ) && rpress_has_variable_prices( $post_id ) && function_exists( 'rpress_get_lowest_price_option' ) ) {
				$price = (string) rpress_get_lowest_price_option( $post_id );
			} elseif ( function_exists( 'rpress_get_fooditem_price' ) ) {
				$price = (string) rpress_get_fooditem_price( $post_id );
			}
			return array(
				'name'       => $post ? $post->post_title : '',
				'categories' => is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array(),
				'price'      => $price,
				'excerpt'    => $post ? trim( wp_strip_all_tags( $post->post_excerpt ) ) : '',
				'content'    => $post ? trim( wp_strip_all_tags( $post->post_content ) ) : '',
			);
		}

		/**
		 * Item facts as prompt lines.
		 *
		 * @param array $item Item context.
		 * @return string
		 */
		public static function describe_item( $item ) {
			$lines = array( 'Item name: ' . $item['name'] );
			if ( ! empty( $item['categories'] ) ) {
				$lines[] = 'Menu category: ' . implode( ', ', $item['categories'] );
			}
			if ( '' !== (string) $item['price'] ) {
				$currency = function_exists( 'rpress_get_option' ) ? rpress_get_option( 'currency', 'USD' ) : 'USD';
				$lines[]  = 'Price (context only, never mention it): ' . $item['price'] . ' ' . $currency;
			}
			if ( '' !== $item['excerpt'] ) {
				$lines[] = 'Current short description: ' . $item['excerpt'];
			}
			if ( '' !== $item['content'] ) {
				$lines[] = 'Current full description: ' . $item['content'];
			}
			return implode( "\n", $lines );
		}

		/**
		 * Shared copywriting instructions.
		 *
		 * @return string
		 */
		public static function system_prompt() {
			$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
			return 'You write menu copy for a restaurant\'s online ordering site. Write in the language of the locale ' . $locale . '. '
				. 'Use only facts implied by the item details. Never mention or invent prices, discounts, allergens, health claims or certifications. '
				. 'No markdown, no emoji, no quotation marks around the answer, no em dashes. Reply with the copy only.';
		}

		/**
		 * Prompt for one field.
		 *
		 * @param string $field   description|excerpt.
		 * @param string $mode    write|improve.
		 * @param array  $item    Item context.
		 * @return string
		 */
		public static function copy_prompt( $field, $mode, $item ) {
			$facts = self::describe_item( $item );
			if ( 'excerpt' === $field ) {
				$task = 'improve' === $mode
					? 'Improve the current short description: keep its facts, fix grammar and make it more appetising. One line, at most 15 words.'
					: 'Write a short description for the menu card: one appetising line, at most 15 words.';
			} else {
				$task = 'improve' === $mode
					? 'Improve the current full description: keep its facts and approximate length, fix grammar and make it more appetising. At most 70 words.'
					: 'Write the full description shown when a customer opens the item: 2 or 3 appetising sentences, at most 60 words, mentioning likely key ingredients or preparation only when the name makes them clear.';
			}
			return $task . "\n\n" . $facts;
		}

		/**
		 * Clean model copy for a form field.
		 *
		 * @param string $text Raw text.
		 * @return string
		 */
		public static function clean_copy( $text ) {
			$text = sanitize_textarea_field( wp_strip_all_tags( (string) $text ) );
			$text = str_replace( array( "\xE2\x80\x94", "\xE2\x80\x93" ), array( ', ', '-' ), $text );
			return trim( $text, " \t\n\r\0\x0B\"'" );
		}

		/* ------------------------------------------------------------------
		 * AJAX handlers
		 * ------------------------------------------------------------------ */

		/**
		 * Save the posted provider settings, then test the connection.
		 *
		 * @return void
		 */
		public static function ajax_test() {
			self::verify( 'manage_shop_settings' );
			$raw = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
			RPRESS_AI::save_settings( self::sanitize_settings_input( $raw ) );
			$status = RPRESS_AI::get_status();
			$hint   = 'wordpress' !== $status['provider'] ? RPRESS_AI::key_hint( $status['provider'] ) : '';

			if ( ! $status['ready'] ) {
				$details             = RPRESS_AI::error_details( RPRESS_AI::not_configured_error(), $status['provider'], 'assistant' );
				$details['message']  = empty( $status['enabled'] )
					? __( 'AI features are switched off. Tick "Enable AI features" first.', 'restropress' )
					: ( 'wordpress' === $status['provider']
						? __( 'WordPress AI has no connected provider on this site. Connect one in WordPress, or choose OpenAI, Gemini or Claude and add a key.', 'restropress' )
						: __( 'No API key is saved for this provider yet. Paste your key, then test again.', 'restropress' ) );
				$details['status']   = $status;
				$details['key_hint'] = $hint;
				wp_send_json_error( $details, 400 );
			}

			$test = RPRESS_AI::test_connection( $status );
			if ( is_wp_error( $test ) ) {
				$details             = RPRESS_AI::error_details( $test, $status['provider'], 'assistant' );
				$details['status']   = $status;
				$details['key_hint'] = $hint;
				wp_send_json_error( $details, 400 );
			}

			wp_send_json_success(
				array(
					/* translators: %s: provider label */
					'message'  => sprintf( __( '%s is connected and ready.', 'restropress' ), $status['provider_label'] ),
					'status'   => $status,
					'key_hint' => $hint,
				)
			);
		}

		/**
		 * Write or improve the description / short description.
		 *
		 * @return void
		 */
		public static function ajax_write() {
			self::verify( 'edit_products' );
			self::require_feature( 'writer' );
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in verify().
			$post_id = self::editable_fooditem( isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0 );
			$field   = isset( $_POST['field'] ) && 'excerpt' === sanitize_key( $_POST['field'] ) ? 'excerpt' : 'description';
			$mode    = isset( $_POST['mode'] ) && 'improve' === sanitize_key( $_POST['mode'] ) ? 'improve' : 'write';
			// phpcs:enable
			$item = self::posted_context( $post_id );

			if ( '' === $item['name'] ) {
				wp_send_json_error( array( 'message' => __( 'Add the item name first so AI knows what to write about.', 'restropress' ) ), 400 );
			}
			$current = 'excerpt' === $field ? $item['excerpt'] : $item['content'];
			if ( 'improve' === $mode && '' === $current ) {
				wp_send_json_error( array( 'message' => __( 'There is no text to improve yet. Use Write with AI instead.', 'restropress' ) ), 400 );
			}

			$text = RPRESS_AI::generate_text(
				self::copy_prompt( $field, $mode, $item ),
				array(
					'system'     => self::system_prompt(),
					'max_tokens' => 'excerpt' === $field ? 120 : 400,
				)
			);
			if ( is_wp_error( $text ) ) {
				self::send_error( $text );
			}

			wp_send_json_success(
				array(
					'field' => $field,
					'text'  => self::clean_copy( $text ),
				)
			);
		}

		/**
		 * Term names of a taxonomy.
		 *
		 * @param string $taxonomy Taxonomy.
		 * @return string[]
		 */
		protected static function term_names( $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				return array();
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 200,
				)
			);
			return is_wp_error( $terms ) ? array() : array_values( array_unique( wp_list_pluck( $terms, 'name' ) ) );
		}

		/**
		 * JSON schema for a label suggestion.
		 *
		 * @param string   $kind  dietary|allergens.
		 * @param string[] $terms Known terms.
		 * @return array
		 */
		public static function suggestion_schema( $kind, $terms ) {
			$labels = array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			);
			$schema = array(
				'type'       => 'object',
				'properties' => array(
					'labels' => $labels,
					'other'  => $labels,
					'reason' => array( 'type' => 'string' ),
				),
				'required'   => array( 'labels', 'other', 'reason' ),
			);
			if ( $terms ) {
				$schema['properties']['labels']['items']['enum'] = array_values( $terms );
			}
			if ( 'dietary' === $kind ) {
				$schema['properties']['food_type'] = array(
					'type' => 'string',
					'enum' => array( 'veg', 'non_veg', 'unknown' ),
				);
				$schema['required'][]              = 'food_type';
			}
			return $schema;
		}

		/**
		 * Suggest dietary labels or allergens. Returns names to pre-select; the
		 * merchant confirms by saving the item.
		 *
		 * @return void
		 */
		public static function ajax_suggest() {
			self::verify( 'edit_products' );
			self::require_feature( 'dietary' );
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in verify().
			$post_id = self::editable_fooditem( isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0 );
			$kind    = isset( $_POST['kind'] ) && 'allergens' === sanitize_key( $_POST['kind'] ) ? 'allergens' : 'dietary';
			// phpcs:enable
			$taxonomy = 'allergens' === $kind ? 'rpress_allergen' : 'dietary';
			if ( ! taxonomy_exists( $taxonomy ) ) {
				wp_send_json_error( array( 'message' => __( 'This label list is not available on this site.', 'restropress' ) ), 400 );
			}
			$item = self::posted_context( $post_id );
			if ( '' === $item['name'] ) {
				wp_send_json_error( array( 'message' => __( 'Add the item name first so AI knows what to write about.', 'restropress' ) ), 400 );
			}

			$terms = self::term_names( $taxonomy );
			if ( 'allergens' === $kind ) {
				$task = 'List the allergens this menu item very likely contains, judging only from its name and descriptions. Put matches from the allowed list in "labels" and any other likely allergen in "other". Leave lists empty when unsure.';
			} else {
				$task = 'Pick the dietary labels that clearly apply to this menu item, judging only from its name and descriptions. Put matches from the allowed list in "labels" and any other clearly applicable dietary label in "other". Set "food_type" to "veg" when it contains no meat, poultry, fish or seafood, "non_veg" when it does, "unknown" when unclear. Leave lists empty when unsure.';
			}
			$prompt = $task . "\nAllowed list: " . ( $terms ? implode( ', ', $terms ) : '(none)' ) . "\nGive a one-sentence reason.\n\n" . self::describe_item( $item );

			$result = RPRESS_AI::extract_structured(
				$prompt,
				self::suggestion_schema( $kind, $terms ),
				array(
					'name'       => 'restropress_' . $kind,
					'max_tokens' => 400,
					'timeout'    => 30,
				)
			);
			if ( is_wp_error( $result ) ) {
				self::send_error( $result );
			}

			$labels = array();
			$lookup = array();
			foreach ( $terms as $term ) {
				$lookup[ strtolower( $term ) ] = $term;
			}
			foreach ( isset( $result['labels'] ) && is_array( $result['labels'] ) ? $result['labels'] : array() as $label ) {
				$key = strtolower( sanitize_text_field( (string) $label ) );
				if ( isset( $lookup[ $key ] ) ) {
					$labels[] = $lookup[ $key ];
				}
			}
			$other = array();
			foreach ( isset( $result['other'] ) && is_array( $result['other'] ) ? $result['other'] : array() as $label ) {
				$label = sanitize_text_field( (string) $label );
				if ( '' !== $label && ! isset( $lookup[ strtolower( $label ) ] ) ) {
					$other[] = $label;
				}
			}
			$food_type = isset( $result['food_type'] ) && in_array( $result['food_type'], array( 'veg', 'non_veg' ), true ) ? $result['food_type'] : '';

			wp_send_json_success(
				array(
					'kind'      => $kind,
					'labels'    => array_values( array_unique( $labels ) ),
					'other'     => array_slice( array_values( array_unique( $other ) ), 0, 8 ),
					'food_type' => 'dietary' === $kind ? $food_type : '',
					'reason'    => isset( $result['reason'] ) ? sanitize_text_field( (string) $result['reason'] ) : '',
				)
			);
		}

		/**
		 * Draft alt text for the food image (text only: from the item name and
		 * description, no image upload).
		 *
		 * @return void
		 */
		public static function ajax_alt_text() {
			self::verify( 'edit_products' );
			self::require_feature( 'alt_text' );
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in verify().
			$post_id       = self::editable_fooditem( isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0 );
			$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
			// phpcs:enable
			if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Choose a food image first.', 'restropress' ) ), 400 );
			}
			$item = self::posted_context( $post_id );
			if ( '' === $item['name'] ) {
				wp_send_json_error( array( 'message' => __( 'Add the item name first so AI knows what to write about.', 'restropress' ) ), 400 );
			}

			$text = RPRESS_AI::generate_text(
				"Write alt text for the photo of this menu item, for screen readers and search engines. Describe the dish as it would likely look, at most 125 characters, no 'image of' or 'photo of'.\n\n" . self::describe_item( $item ),
				array(
					'system'     => self::system_prompt(),
					'max_tokens' => 100,
				)
			);
			if ( is_wp_error( $text ) ) {
				self::send_error( $text );
			}
			$alt = self::clean_copy( $text );
			if ( function_exists( 'mb_substr' ) ) {
				$alt = mb_substr( $alt, 0, 150 );
			}

			wp_send_json_success(
				array(
					'alt'     => $alt,
					'current' => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				)
			);
		}

		/**
		 * Save reviewed alt text.
		 *
		 * @return void
		 */
		public static function ajax_save_alt() {
			self::verify( 'edit_products' );
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in verify().
			$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
			$alt           = isset( $_POST['alt'] ) ? sanitize_text_field( wp_unslash( $_POST['alt'] ) ) : '';
			// phpcs:enable
			if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this image.', 'restropress' ) ), 403 );
			}
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
			wp_send_json_success( array( 'alt' => $alt ) );
		}

		/* ------------------------------------------------------------------
		 * Bulk: Generate missing descriptions
		 * ------------------------------------------------------------------ */

		/**
		 * Add the bulk action to Menu Items.
		 *
		 * @param array $actions Bulk actions.
		 * @return array
		 */
		public static function register_bulk_action( $actions ) {
			if ( current_user_can( 'edit_products' ) && RPRESS_AI::feature_enabled( 'bulk' ) ) {
				$actions[ self::BULK_ACTION ] = __( 'Generate missing descriptions (AI)', 'restropress' );
			}
			return $actions;
		}

		/**
		 * Send the selected items to the review screen. WordPress has already
		 * checked the bulk-posts nonce.
		 *
		 * @param string $redirect Redirect URL.
		 * @param string $action   Action.
		 * @param int[]  $ids      Post IDs.
		 * @return string
		 */
		public static function handle_bulk_action( $redirect, $action, $ids ) {
			if ( self::BULK_ACTION !== $action ) {
				return $redirect;
			}
			$ids = array_filter( array_map( 'absint', (array) $ids ) );
			return add_query_arg(
				array(
					'post_type' => 'fooditem',
					'page'      => self::REVIEW_PAGE,
					'ids'       => implode( ',', array_slice( $ids, 0, 200 ) ),
				),
				admin_url( 'edit.php' )
			);
		}

		/**
		 * Hidden review page under Menu Items.
		 *
		 * @return void
		 */
		public static function register_review_page() {
			$title = __( 'Generate missing descriptions', 'restropress' );
			$hook  = add_submenu_page( 'edit.php?post_type=fooditem', $title, $title, 'edit_products', self::REVIEW_PAGE, array( __CLASS__, 'render_review_page' ) );
			remove_submenu_page( 'edit.php?post_type=fooditem', self::REVIEW_PAGE );
			if ( $hook ) {
				// Removing the submenu entry also drops the page title (PHP 8.1+ null title).
				add_action(
					'load-' . $hook,
					function () use ( $title ) {
						$GLOBALS['title'] = $title; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					}
				);
			}
		}

		/**
		 * Whether a food item is missing a description or short description.
		 *
		 * @param WP_Post $post Post.
		 * @return array{description:bool,excerpt:bool}
		 */
		public static function missing_fields( $post ) {
			return array(
				'description' => '' === trim( wp_strip_all_tags( (string) $post->post_content ) ),
				'excerpt'     => '' === trim( wp_strip_all_tags( (string) $post->post_excerpt ) ),
			);
		}

		/**
		 * Items for the review screen: the selected IDs, or every item missing a
		 * description when none were selected.
		 *
		 * @param int[] $ids Selected IDs.
		 * @return WP_Post[]
		 */
		public static function review_items( $ids ) {
			$args = array(
				'post_type'      => 'fooditem',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => $ids ? count( $ids ) : 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
			);
			if ( $ids ) {
				$args['post__in'] = $ids;
			}
			$items = array();
			foreach ( get_posts( $args ) as $post ) {
				$missing = self::missing_fields( $post );
				if ( ( $missing['description'] || $missing['excerpt'] ) && current_user_can( 'edit_post', $post->ID ) ) {
					$items[] = $post;
				}
				if ( count( $items ) >= 100 ) {
					break;
				}
			}
			return $items;
		}

		/**
		 * Render the review screen.
		 *
		 * @return void
		 */
		public static function render_review_page() {
			if ( ! current_user_can( 'edit_products' ) ) {
				wp_die( esc_html__( 'You do not have permission to edit menu items.', 'restropress' ) );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list of IDs; every save is nonce-checked.
			$ids      = isset( $_GET['ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['ids'] ) ) ) ) ) : array();
			$items    = self::review_items( $ids );
			$enabled  = RPRESS_AI::feature_enabled( 'bulk' );
			$ready    = RPRESS_AI::is_ready();
			$selected = ! empty( $ids );
			include __DIR__ . '/views/bulk-review.php';
		}

		/**
		 * Generate drafts for one item. Nothing is saved.
		 *
		 * @return void
		 */
		public static function ajax_bulk_generate() {
			self::verify( 'edit_products' );
			self::require_feature( 'bulk' );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in verify().
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			if ( ! $post_id ) {
				wp_send_json_error( array( 'message' => __( 'Missing menu item.', 'restropress' ) ), 400 );
			}
			$post_id = self::editable_fooditem( $post_id );
			$post    = get_post( $post_id );
			$missing = self::missing_fields( $post );
			if ( ! $missing['description'] && ! $missing['excerpt'] ) {
				wp_send_json_success(
					array(
						'id'          => $post_id,
						'description' => '',
						'excerpt'     => '',
					)
				);
			}

			$item   = self::stored_context( $post_id );
			$prompt = "Write menu copy for this item. \"short_description\": one appetising line, at most 15 words, for the menu card. \"description\": 2 or 3 appetising sentences, at most 60 words, shown when the customer opens the item.\n\n" . self::describe_item( $item );
			$result = RPRESS_AI::extract_structured(
				$prompt,
				array(
					'type'       => 'object',
					'properties' => array(
						'short_description' => array( 'type' => 'string' ),
						'description'       => array( 'type' => 'string' ),
					),
					'required'   => array( 'short_description', 'description' ),
				),
				array(
					'name'        => 'restropress_copy',
					'system'      => self::system_prompt(),
					'max_tokens'  => 600,
					'temperature' => 0.7,
					'timeout'     => 45,
				)
			);
			if ( is_wp_error( $result ) ) {
				self::send_error( $result );
			}

			wp_send_json_success(
				array(
					'id'          => $post_id,
					'description' => $missing['description'] && isset( $result['description'] ) ? self::clean_copy( $result['description'] ) : '',
					'excerpt'     => $missing['excerpt'] && isset( $result['short_description'] ) ? self::clean_copy( $result['short_description'] ) : '',
				)
			);
		}

		/**
		 * Save the drafts the merchant accepted. Only fields that are still empty
		 * are filled; status, prices and every other field stay as they are.
		 *
		 * @return void
		 */
		public static function ajax_bulk_save() {
			self::verify( 'edit_products' );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in verify(), sanitized per field.
			$items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
			$saved = self::save_drafts( $items );
			wp_send_json_success(
				array(
					'saved'   => $saved['saved'],
					'skipped' => $saved['skipped'],
					/* translators: %d: number of menu items */
					'message' => sprintf( _n( '%d item updated.', '%d items updated.', count( $saved['saved'] ), 'restropress' ), count( $saved['saved'] ) ),
				)
			);
		}

		/**
		 * Write accepted drafts.
		 *
		 * @param array $items Rows of id, description, excerpt.
		 * @return array saved IDs and skipped IDs.
		 */
		public static function save_drafts( $items ) {
			$saved   = array();
			$skipped = array();
			foreach ( array_slice( (array) $items, 0, 200 ) as $row ) {
				$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
				if ( ! $id || 'fooditem' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
					$skipped[] = $id;
					continue;
				}
				$post    = get_post( $id );
				$missing = self::missing_fields( $post );
				$update  = array( 'ID' => $id );
				$desc    = isset( $row['description'] ) ? self::clean_copy( $row['description'] ) : '';
				$excerpt = isset( $row['excerpt'] ) ? self::clean_copy( $row['excerpt'] ) : '';
				if ( '' !== $desc && $missing['description'] ) {
					$update['post_content'] = wp_kses_post( wpautop( esc_html( $desc ) ) );
				}
				if ( '' !== $excerpt && $missing['excerpt'] ) {
					$update['post_excerpt'] = $excerpt;
				}
				if ( 1 === count( $update ) ) {
					$skipped[] = $id;
					continue;
				}
				$result = wp_update_post( wp_slash( $update ), true );
				if ( is_wp_error( $result ) ) {
					$skipped[] = $id;
				} else {
					$saved[] = $id;
				}
			}
			return array(
				'saved'   => $saved,
				'skipped' => array_values( array_filter( $skipped ) ),
			);
		}
	}
}
