<?php
/**
 * RestroPress AI service.
 *
 * One provider-agnostic client for every AI touchpoint in the admin: menu
 * import, food item copy, dietary suggestions, alt text and bulk descriptions.
 * Supports WordPress AI (6.8+ / 7.0 client), OpenAI, Google Gemini and
 * Anthropic Claude with the merchant's own key, stored in the
 * `rpress_ai_settings` option (shared with the menu import screen).
 *
 * This file only defines the class. Hooks live in loader.php and
 * class-rpress-ai-admin.php so the service can be loaded on its own (tests,
 * RPress_Onboarding).
 *
 * @package RPRESS\Features\AI
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'RPRESS_AI' ) ) {
	/**
	 * Provider-agnostic AI client.
	 */
	class RPRESS_AI {
		const OPTION    = 'rpress_ai_settings';
		const PROVIDERS = array( 'wordpress', 'openai', 'gemini', 'claude' );
		const FEATURES  = array( 'writer', 'dietary', 'alt_text', 'bulk' );

		/**
		 * Default model per direct provider.
		 *
		 * @param string $provider Provider slug.
		 * @return string
		 */
		public static function default_model( $provider ) {
			$defaults = array(
				'openai' => 'gpt-4o-mini',
				'gemini' => 'gemini-3.5-flash-lite',
				'claude' => 'claude-haiku-4-5',
			);
			return isset( $defaults[ $provider ] ) ? $defaults[ $provider ] : '';
		}

		/**
		 * Human labels for providers.
		 *
		 * @return array
		 */
		public static function provider_labels() {
			return array(
				'wordpress' => 'WordPress AI',
				'openai'    => 'OpenAI',
				'gemini'    => 'Google Gemini',
				'claude'    => 'Anthropic Claude',
			);
		}

		/**
		 * Stored settings, normalised. Single source of truth for the import
		 * screen and the AI Assistant settings section.
		 *
		 * @return array
		 */
		public static function get_settings() {
			$defaults = array(
				'enabled'               => 'yes',
				'provider'              => 'wordpress',
				'api_key'               => '',
				'api_keys'              => array(),
				'model'                 => '',
				'models'                => array(),
				'features'              => array(),
				'generate_descriptions' => 'yes',
			);

			$settings = get_option( self::OPTION, array() );
			$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
			if ( ! in_array( $settings['provider'], self::PROVIDERS, true ) ) {
				$settings['provider'] = 'wordpress';
			}

			$settings['api_keys'] = is_array( $settings['api_keys'] ) ? $settings['api_keys'] : array();
			if ( ! empty( $settings['api_key'] ) && 'wordpress' !== $settings['provider'] && empty( $settings['api_keys'][ $settings['provider'] ] ) ) {
				$settings['api_keys'][ $settings['provider'] ] = $settings['api_key'];
			}
			$settings['api_key'] = isset( $settings['api_keys'][ $settings['provider'] ] ) ? $settings['api_keys'][ $settings['provider'] ] : '';

			// Models are stored per provider. Older installs kept one shared value.
			$settings['models'] = is_array( $settings['models'] ) ? $settings['models'] : array();
			if ( '' !== (string) $settings['model'] && ! isset( $settings['models'][ $settings['provider'] ] ) ) {
				$settings['models'][ $settings['provider'] ] = $settings['model'];
			}
			$settings['model'] = isset( $settings['models'][ $settings['provider'] ] ) ? (string) $settings['models'][ $settings['provider'] ] : '';

			$features = is_array( $settings['features'] ) ? $settings['features'] : array();
			foreach ( self::FEATURES as $feature ) {
				$features[ $feature ] = isset( $features[ $feature ] ) && 'no' === $features[ $feature ] ? 'no' : 'yes';
			}
			$settings['features']              = $features;
			$settings['generate_descriptions'] = 'no' === $settings['generate_descriptions'] ? 'no' : 'yes';

			return $settings;
		}

		/**
		 * Save settings. Keys that are not passed keep their stored value, so the
		 * import screen (provider + key only) never wipes the per-feature toggles
		 * or default model chosen on the settings screen.
		 *
		 * Accepted keys: enabled, provider, api_key, remove_key, model, features,
		 * generate_descriptions.
		 *
		 * @param array $data Raw values.
		 * @return array Saved settings.
		 */
		public static function save_settings( $data ) {
			$data     = is_array( $data ) ? $data : array();
			$current  = self::get_settings();
			$provider = isset( $data['provider'] ) ? sanitize_key( $data['provider'] ) : $current['provider'];
			if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
				$provider = 'wordpress';
			}

			$api_keys = $current['api_keys'];
			if ( isset( $data['api_key'] ) && '' !== trim( (string) $data['api_key'] ) && 'wordpress' !== $provider ) {
				$api_keys[ $provider ] = sanitize_text_field( trim( (string) $data['api_key'] ) );
			} elseif ( ! empty( $data['remove_key'] ) && 'wordpress' !== $provider ) {
				unset( $api_keys[ $provider ] );
			}

			$models = $current['models'];
			if ( isset( $data['model'] ) && 'wordpress' !== $provider ) {
				$models[ $provider ] = sanitize_text_field( (string) $data['model'] );
			}

			$features = $current['features'];
			if ( isset( $data['features'] ) && is_array( $data['features'] ) ) {
				foreach ( self::FEATURES as $feature ) {
					$features[ $feature ] = self::truthy( isset( $data['features'][ $feature ] ) ? $data['features'][ $feature ] : '' ) ? 'yes' : 'no';
				}
			}

			$settings = array(
				'enabled'               => isset( $data['enabled'] ) ? ( self::truthy( $data['enabled'] ) ? 'yes' : 'no' ) : $current['enabled'],
				'provider'              => $provider,
				'api_key'               => isset( $api_keys[ $provider ] ) ? $api_keys[ $provider ] : '',
				'api_keys'              => $api_keys,
				'model'                 => isset( $models[ $provider ] ) ? $models[ $provider ] : '',
				'models'                => $models,
				'features'              => $features,
				'generate_descriptions' => isset( $data['generate_descriptions'] ) ? ( self::truthy( $data['generate_descriptions'] ) ? 'yes' : 'no' ) : $current['generate_descriptions'],
			);

			update_option( self::OPTION, $settings, false );
			self::flush_wp_status();
			return $settings;
		}

		/**
		 * Loose boolean for form values.
		 *
		 * @param mixed $value Value.
		 * @return bool
		 */
		protected static function truthy( $value ) {
			return in_array( $value, array( true, 1, '1', 'yes', 'on', 'true' ), true );
		}

		/**
		 * Last characters of a saved key, for "Saved key ending in ..." hints.
		 * The full key is never sent back to the browser.
		 *
		 * @param string $provider Provider slug.
		 * @return string Empty when no key is saved.
		 */
		public static function key_hint( $provider ) {
			$settings = self::get_settings();
			$key      = isset( $settings['api_keys'][ $provider ] ) ? (string) $settings['api_keys'][ $provider ] : '';
			if ( '' === $key ) {
				return '';
			}
			return strlen( $key ) > 8 ? substr( $key, -4 ) : '****';
		}

		/**
		 * Current availability.
		 *
		 * @return array
		 */
		public static function get_status() {
			$settings        = self::get_settings();
			$wp_ai           = class_exists( '\WordPress\AiClient\AiClient' );
			$wp_configured   = false;
			$wp_provider_ids = array();
			if ( $wp_ai ) {
				try {
					$registry        = \WordPress\AiClient\AiClient::defaultRegistry();
					$wp_provider_ids = method_exists( $registry, 'getRegisteredProviderIds' ) ? $registry->getRegisteredProviderIds() : array();
					$wp_configured   = self::wp_providers_configured( $registry, $wp_provider_ids );
				} catch ( Throwable $e ) {
					$wp_configured = false;
				}
			}
			$direct  = 'wordpress' !== $settings['provider'] && ! empty( $settings['api_key'] );
			$enabled = 'yes' === $settings['enabled'];

			$provider_label = __( 'Not configured', 'restropress' );
			if ( $enabled && 'wordpress' === $settings['provider'] && $wp_configured ) {
				$provider_label = __( 'WordPress AI', 'restropress' );
			} elseif ( $enabled && 'openai' === $settings['provider'] && $direct ) {
				$provider_label = __( 'OpenAI via RestroPress', 'restropress' );
			} elseif ( $enabled && 'gemini' === $settings['provider'] && $direct ) {
				$provider_label = __( 'Google Gemini via RestroPress', 'restropress' );
			} elseif ( $enabled && 'claude' === $settings['provider'] && $direct ) {
				$provider_label = __( 'Anthropic Claude via RestroPress', 'restropress' );
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
				'multimodal'       => $enabled && ( ( 'wordpress' === $settings['provider'] && $wp_configured ) || in_array( $settings['provider'], array( 'gemini', 'openai', 'claude' ), true ) ),
			);
		}

		/**
		 * Cached WordPress AI provider check for this request.
		 *
		 * @var array|null { sig: string, ok: bool }
		 */
		private static $wp_status_memo = null;

		/**
		 * Whether any WordPress AI provider is configured.
		 *
		 * Provider plugins can check this over the network (listing models), so
		 * the answer is kept for the request and for 10 minutes; saving the AI
		 * settings clears it.
		 *
		 * @param object $registry     AI client provider registry.
		 * @param array  $provider_ids Registered provider IDs.
		 * @return bool
		 */
		private static function wp_providers_configured( $registry, $provider_ids ) {
			$sig = md5( implode( ',', array_map( 'strval', (array) $provider_ids ) ) );
			if ( null !== self::$wp_status_memo && $sig === self::$wp_status_memo['sig'] ) {
				return self::$wp_status_memo['ok'];
			}
			$cached = function_exists( 'get_transient' ) ? get_transient( 'rpress_ai_wp_configured' ) : false;
			if ( is_array( $cached ) && isset( $cached['sig'], $cached['ok'] ) && $sig === $cached['sig'] ) {
				self::$wp_status_memo = array( 'sig' => $sig, 'ok' => (bool) $cached['ok'] );
				return self::$wp_status_memo['ok'];
			}
			$configured = false;
			foreach ( (array) $provider_ids as $provider_id ) {
				if ( method_exists( $registry, 'isProviderConfigured' ) && $registry->isProviderConfigured( $provider_id ) ) {
					$configured = true;
					break;
				}
			}
			self::$wp_status_memo = array( 'sig' => $sig, 'ok' => $configured );
			if ( function_exists( 'set_transient' ) ) {
				set_transient( 'rpress_ai_wp_configured', self::$wp_status_memo, 600 );
			}
			return $configured;
		}

		/**
		 * Forget the cached WordPress AI provider check.
		 *
		 * @return void
		 */
		public static function flush_wp_status() {
			self::$wp_status_memo = null;
			if ( function_exists( 'delete_transient' ) ) {
				delete_transient( 'rpress_ai_wp_configured' );
			}
		}

		/**
		 * Whether AI is configured and usable.
		 *
		 * @return bool
		 */
		public static function is_ready() {
			$status = self::get_status();
			return ! empty( $status['ready'] );
		}

		/**
		 * Whether one admin touchpoint is switched on.
		 *
		 * @param string $feature One of self::FEATURES.
		 * @return bool
		 */
		public static function feature_enabled( $feature ) {
			$settings = self::get_settings();
			return 'yes' === $settings['enabled'] && ( ! isset( $settings['features'][ $feature ] ) || 'yes' === $settings['features'][ $feature ] );
		}

		/**
		 * Link to the AI Assistant settings section.
		 *
		 * @return string
		 */
		public static function settings_url() {
			$path = 'admin.php?page=rpress-settings&tab=general&section=ai_assistant';
			return function_exists( 'admin_url' ) ? admin_url( $path ) : $path;
		}

		/**
		 * Error returned when no provider is usable.
		 *
		 * @return WP_Error
		 */
		public static function not_configured_error() {
			return new WP_Error(
				'rpress_ai_not_configured',
				__( 'No AI provider is set up yet. Choose a provider and add your API key under RestroPress > Settings > AI Assistant.', 'restropress' ),
				array( 'settings_url' => self::settings_url() )
			);
		}

		/**
		 * Model to use for a provider.
		 *
		 * @param string $provider Provider slug.
		 * @param array  $settings Settings.
		 * @param string $override Model passed by the caller.
		 * @return string
		 */
		public static function model_for( $provider, $settings = null, $override = '' ) {
			$settings   = null === $settings ? self::get_settings() : $settings;
			$configured = '' !== (string) $override ? (string) $override : ( isset( $settings['models'][ $provider ] ) ? (string) $settings['models'][ $provider ] : '' );
			if ( '' === $configured && $provider === $settings['provider'] ) {
				$configured = (string) $settings['model'];
			}
			if ( 'gemini' === $provider ) {
				return self::gemini_model( $configured );
			}
			return '' !== $configured ? sanitize_text_field( $configured ) : self::default_model( $provider );
		}

		/**
		 * Resolve Gemini's extraction-optimized default and migrate retired models.
		 *
		 * @param string $configured Configured model.
		 * @return string
		 */
		public static function gemini_model( $configured = '' ) {
			if ( ! $configured || in_array( $configured, array( 'gemini-2.0-flash', 'gemini-2.0-flash-001' ), true ) ) {
				return 'gemini-3.5-flash-lite';
			}
			return sanitize_text_field( $configured );
		}

		/**
		 * Merge caller options with defaults.
		 *
		 * @param array $opts     Options.
		 * @param array $defaults Defaults.
		 * @return array
		 */
		protected static function options( $opts, $defaults ) {
			$opts             = wp_parse_args( is_array( $opts ) ? $opts : array(), $defaults );
			$opts['messages'] = wp_parse_args(
				isset( $opts['messages'] ) && is_array( $opts['messages'] ) ? $opts['messages'] : array(),
				array(
					/* translators: %s: provider name */
					'empty'       => __( '%s returned an empty response.', 'restropress' ),
					'file_read'   => __( 'The uploaded file could not be read.', 'restropress' ),
					/* translators: %s: provider name */
					'unsupported' => __( '%s could not read this file type. Upload a PDF or image.', 'restropress' ),
					'bad_json'    => __( 'AI returned an invalid response format.', 'restropress' ),
				)
			);
			return $opts;
		}

		/**
		 * Resolve which provider a call runs on. Without an explicit provider the
		 * stored one is used and must be ready.
		 *
		 * @param array $opts Options.
		 * @return string|WP_Error
		 */
		protected static function resolve_provider( $opts ) {
			if ( ! empty( $opts['provider'] ) ) {
				$provider = sanitize_key( $opts['provider'] );
				return in_array( $provider, self::PROVIDERS, true ) ? $provider : new WP_Error( 'rpress_ai_unavailable', __( 'No supported AI provider is configured.', 'restropress' ) );
			}
			$status = self::get_status();
			if ( empty( $status['ready'] ) ) {
				return self::not_configured_error();
			}
			return $status['provider'];
		}

		/**
		 * Generate free-form text.
		 *
		 * Options: provider, model, system, max_tokens (default 600),
		 * temperature (default 0.7), timeout (default 30).
		 *
		 * @param string $prompt Prompt.
		 * @param array  $opts   Options.
		 * @return string|WP_Error Trimmed text.
		 */
		public static function generate_text( $prompt, $opts = array() ) {
			$opts     = self::options(
				$opts,
				array(
					'provider'    => '',
					'model'       => '',
					'system'      => '',
					'max_tokens'  => 600,
					'temperature' => 0.7,
					'timeout'     => 30,
				)
			);
			$provider = self::resolve_provider( $opts );
			if ( is_wp_error( $provider ) ) {
				return $provider;
			}
			$prompt   = (string) $prompt;
			$settings = self::get_settings();

			if ( 'wordpress' === $provider ) {
				$full = '' !== $opts['system'] ? $opts['system'] . "\n\n" . $prompt : $prompt;
				$text = self::wordpress_request( $full, null, array(), (int) $opts['timeout'] );
			} else {
				$model = self::model_for( $provider, $settings, $opts['model'] );
				$max   = max( 16, (int) $opts['max_tokens'] );
				if ( 'openai' === $provider ) {
					$body = array(
						'model'             => $model,
						'input'             => $prompt,
						'max_output_tokens' => $max,
						'store'             => false,
					);
					if ( '' !== $opts['system'] ) {
						$body['instructions'] = $opts['system'];
					}
				} elseif ( 'gemini' === $provider ) {
					$config = array(
						'maxOutputTokens' => $max,
						'temperature'     => (float) $opts['temperature'],
					);
					if ( 0 === strpos( $model, 'gemini-3' ) ) {
						$config['thinkingConfig'] = array( 'thinkingLevel' => 'minimal' );
					}
					$body = array(
						'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
						'generationConfig' => $config,
					);
					if ( '' !== $opts['system'] ) {
						$body['systemInstruction'] = array( 'parts' => array( array( 'text' => $opts['system'] ) ) );
					}
				} else {
					$body = array(
						'model'       => $model,
						'max_tokens'  => $max,
						'temperature' => (float) $opts['temperature'],
						'messages'    => array( array( 'role' => 'user', 'content' => $prompt ) ),
					);
					if ( '' !== $opts['system'] ) {
						$body['system'] = $opts['system'];
					}
				}
				$text = self::provider_text( $provider, $body, $model, (int) $opts['timeout'], $settings );
			}

			if ( is_wp_error( $text ) ) {
				return $text;
			}
			$text = trim( (string) $text );
			if ( '' === $text ) {
				$labels = self::provider_labels();
				return new WP_Error( 'rpress_' . $provider . '_failed', sprintf( $opts['messages']['empty'], $labels[ $provider ] ) );
			}
			return $text;
		}

		/**
		 * Extract structured data matching a JSON schema.
		 *
		 * $input is a prompt string, or an array with:
		 *  - text: prompt (instructions plus any source text)
		 *  - file: path to a PDF or image to attach (optional)
		 *  - mime: file MIME type
		 *  - ext:  file extension (optional; derived from the MIME type)
		 *
		 * Options: provider, model, system, name (schema name for OpenAI),
		 * max_tokens (0 = provider default, 16384 for Gemini and Claude, unset for
		 * OpenAI), temperature (default 0.1), timeout (default 60), schema_hint
		 * (append the schema to the prompt for Gemini, default true), messages.
		 *
		 * Claude receives the schema with `additionalProperties: false` on every
		 * object (its structured output rejects open objects).
		 *
		 * @param string|array $input  Prompt or prompt + file.
		 * @param array        $schema JSON schema.
		 * @param array        $opts   Options.
		 * @return array|WP_Error Decoded JSON.
		 */
		public static function extract_structured( $input, $schema, $opts = array() ) {
			$opts     = self::options(
				$opts,
				array(
					'provider'    => '',
					'model'       => '',
					'system'      => '',
					'name'        => 'restropress_data',
					'max_tokens'  => 0,
					'temperature' => 0.1,
					'timeout'     => 60,
					'schema_hint' => true,
				)
			);
			$provider = self::resolve_provider( $opts );
			if ( is_wp_error( $provider ) ) {
				return $provider;
			}

			$input  = is_array( $input ) ? $input : array( 'text' => (string) $input );
			$prompt = isset( $input['text'] ) ? (string) $input['text'] : '';
			$file   = ! empty( $input['file'] ) ? (string) $input['file'] : '';
			$mime   = isset( $input['mime'] ) ? (string) $input['mime'] : '';
			$ext    = isset( $input['ext'] ) ? strtolower( (string) $input['ext'] ) : '';
			if ( '' === $ext && '' !== $mime ) {
				$ext = 'application/pdf' === $mime ? 'pdf' : ( 0 === strpos( $mime, 'image/' ) ? substr( $mime, 6 ) : '' );
			}
			$labels   = self::provider_labels();
			$settings = self::get_settings();

			if ( 'wordpress' === $provider ) {
				$text = self::wordpress_request( $prompt, $schema, '' !== $file ? array( 'file' => $file, 'type' => $mime ) : array(), (int) $opts['timeout'] );
				return is_wp_error( $text ) ? $text : self::decode_json( $text, $opts['messages']['bad_json'] );
			}

			$bytes = null;
			if ( '' !== $file ) {
				$bytes = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( false === $bytes ) {
					return new WP_Error( 'rpress_file_read_failed', $opts['messages']['file_read'] );
				}
			}
			$is_image = in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true );
			$is_pdf   = 'pdf' === $ext;
			$model    = self::model_for( $provider, $settings, $opts['model'] );
			$max      = (int) $opts['max_tokens'];

			if ( 'openai' === $provider ) {
				$content = array(
					array(
						'type' => 'input_text',
						'text' => $prompt,
					),
				);
				if ( null !== $bytes && $is_image ) {
					$content[] = array(
						'type'      => 'input_image',
						'image_url' => 'data:' . $mime . ';base64,' . base64_encode( $bytes ),
					);
				} elseif ( null !== $bytes && $is_pdf ) {
					$content[] = array(
						'type'      => 'input_file',
						'filename'  => basename( $file ),
						'file_data' => 'data:application/pdf;base64,' . base64_encode( $bytes ),
					);
				} elseif ( null !== $bytes ) {
					return new WP_Error( 'rpress_openai_file_unsupported', sprintf( $opts['messages']['unsupported'], $labels['openai'] ) );
				}
				$body = array(
					'model' => $model,
					'store' => false,
					'text'  => array(
						'format' => array(
							'type'   => 'json_schema',
							'name'   => sanitize_key( $opts['name'] ),
							'schema' => $schema,
							'strict' => false,
						),
					),
					'input' => array(
						array(
							'role'    => 'user',
							'content' => $content,
						),
					),
				);
				if ( $max > 0 ) {
					$body['max_output_tokens'] = max( 16, $max );
				}
				if ( '' !== $opts['system'] ) {
					$body['instructions'] = $opts['system'];
				}
			} elseif ( 'gemini' === $provider ) {
				if ( $opts['schema_hint'] ) {
					$prompt .= "\n\nReturn only JSON that matches this JSON schema: " . wp_json_encode( $schema );
				}
				$parts  = array( array( 'text' => $prompt ) );
				$config = array(
					'response_mime_type' => 'application/json',
					'maxOutputTokens'    => $max > 0 ? $max : 16384,
					'temperature'        => (float) $opts['temperature'],
				);
				if ( 0 === strpos( $model, 'gemini-3' ) ) {
					// Extraction, not open-ended reasoning. Gemini 3.x defaults to more
					// thinking, which can exceed common proxy timeouts.
					$config['thinkingConfig'] = array( 'thinkingLevel' => 'minimal' );
				}
				if ( null !== $bytes ) {
					$parts[] = array(
						'inline_data' => array(
							'mime_type' => $mime,
							'data'      => base64_encode( $bytes ),
						),
					);
				}
				$body = array(
					'contents'         => array( array( 'parts' => $parts ) ),
					'generationConfig' => $config,
				);
				if ( '' !== $opts['system'] ) {
					$body['systemInstruction'] = array( 'parts' => array( array( 'text' => $opts['system'] ) ) );
				}
			} else {
				$content = array();
				if ( null !== $bytes && $is_image ) {
					$content[] = array(
						'type'   => 'image',
						'source' => array(
							'type'       => 'base64',
							'media_type' => $mime,
							'data'       => base64_encode( $bytes ),
						),
					);
				} elseif ( null !== $bytes && $is_pdf ) {
					$content[] = array(
						'type'   => 'document',
						'source' => array(
							'type'       => 'base64',
							'media_type' => 'application/pdf',
							'data'       => base64_encode( $bytes ),
						),
					);
				} elseif ( null !== $bytes ) {
					return new WP_Error( 'rpress_claude_file_unsupported', sprintf( $opts['messages']['unsupported'], $labels['claude'] ) );
				}
				$content[] = array(
					'type' => 'text',
					'text' => $prompt,
				);
				$body = array(
					'model'         => $model,
					'max_tokens'    => $max > 0 ? $max : 16384,
					'temperature'   => (float) $opts['temperature'],
					'messages'      => array( array( 'role' => 'user', 'content' => $content ) ),
					'output_config' => array(
						'format' => array(
							'type'   => 'json_schema',
							'schema' => self::close_schema_objects( $schema ),
						),
					),
				);
				if ( '' !== $opts['system'] ) {
					$body['system'] = $opts['system'];
				}
			}

			$text = self::provider_text( $provider, $body, $model, (int) $opts['timeout'], $settings );
			if ( is_wp_error( $text ) ) {
				return $text;
			}
			if ( '' === trim( (string) $text ) ) {
				return new WP_Error( 'rpress_' . $provider . '_failed', sprintf( $opts['messages']['empty'], $labels[ $provider ] ) );
			}
			return self::decode_json( $text, $opts['messages']['bad_json'] );
		}

		/**
		 * Verify that the configured provider answers.
		 *
		 * @param array $status Status from get_status(); only `provider` is used.
		 * @return true|WP_Error
		 */
		public static function test_connection( $status = null ) {
			$status   = null === $status ? self::get_status() : $status;
			$provider = isset( $status['provider'] ) ? $status['provider'] : 'wordpress';

			if ( 'wordpress' === $provider ) {
				$result = self::wordpress_request( 'Reply with the single word OK.', null, array(), 20 );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return '' !== trim( (string) $result ) ? true : new WP_Error( 'rpress_wp_ai_empty_test', __( 'WordPress AI connected but returned an empty response.', 'restropress' ) );
			}

			$settings = self::get_settings();
			$model    = self::model_for( $provider, $settings );
			if ( 'openai' === $provider ) {
				$body = array(
					'model'             => $model,
					'input'             => 'Reply with OK.',
					'max_output_tokens' => 16,
					'store'             => false,
				);
			} elseif ( 'gemini' === $provider ) {
				$config = array( 'maxOutputTokens' => 8 );
				if ( 0 === strpos( $model, 'gemini-3' ) ) {
					$config['thinkingConfig'] = array( 'thinkingLevel' => 'minimal' );
				}
				$body = array(
					'contents'         => array( array( 'parts' => array( array( 'text' => 'Reply with OK.' ) ) ) ),
					'generationConfig' => $config,
				);
			} else {
				$body = array(
					'model'      => $model,
					'max_tokens' => 8,
					'messages'   => array( array( 'role' => 'user', 'content' => 'Reply with OK.' ) ),
				);
			}

			$response = self::provider_post( $provider, $body, $model, 20, $settings );
			return is_wp_error( $response ) ? $response : true;
		}

		/**
		 * POST a JSON body to a direct provider and return the decoded body.
		 *
		 * @param string $provider Provider slug.
		 * @param array  $body     Request body.
		 * @param string $model    Model (used in the Gemini URL).
		 * @param int    $timeout  Seconds.
		 * @param array  $settings Settings.
		 * @return array|WP_Error
		 */
		protected static function provider_post( $provider, $body, $model, $timeout, $settings ) {
			$key = isset( $settings['api_keys'][ $provider ] ) ? (string) $settings['api_keys'][ $provider ] : '';
			if ( '' === $key && $provider === $settings['provider'] ) {
				$key = (string) $settings['api_key'];
			}

			if ( 'openai' === $provider ) {
				$url     = 'https://api.openai.com/v1/responses';
				$headers = array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				);
			} elseif ( 'gemini' === $provider ) {
				$url     = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';
				$headers = array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $key,
				);
			} elseif ( 'claude' === $provider ) {
				$url     = 'https://api.anthropic.com/v1/messages';
				$headers = array(
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				);
			} else {
				return new WP_Error( 'rpress_ai_unavailable', __( 'No supported AI provider is configured.', 'restropress' ) );
			}

			$response = wp_remote_post(
				$url,
				array(
					'timeout' => $timeout,
					'headers' => $headers,
					'body'    => wp_json_encode( $body ),
				)
			);

			$error = self::remote_response_error( $response, $provider );
			if ( is_wp_error( $error ) ) {
				return $error;
			}

			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			return is_array( $decoded ) ? $decoded : array();
		}

		/**
		 * POST to a provider and pull the generated text out of its response.
		 *
		 * @param string $provider Provider slug.
		 * @param array  $body     Request body.
		 * @param string $model    Model.
		 * @param int    $timeout  Seconds.
		 * @param array  $settings Settings.
		 * @return string|WP_Error
		 */
		protected static function provider_text( $provider, $body, $model, $timeout, $settings ) {
			$response = self::provider_post( $provider, $body, $model, $timeout, $settings );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			return self::response_text( $provider, $response );
		}

		/**
		 * Text from a decoded provider response.
		 *
		 * @param string $provider Provider slug.
		 * @param array  $body     Decoded response.
		 * @return string
		 */
		public static function response_text( $provider, $body ) {
			$text = '';
			if ( 'openai' === $provider && ! empty( $body['output'] ) && is_array( $body['output'] ) ) {
				foreach ( $body['output'] as $output ) {
					if ( empty( $output['content'] ) || ! is_array( $output['content'] ) ) {
						continue;
					}
					foreach ( $output['content'] as $part ) {
						if ( isset( $part['text'] ) ) {
							$text .= $part['text'];
						}
					}
				}
			} elseif ( 'gemini' === $provider && ! empty( $body['candidates'][0]['content']['parts'] ) && is_array( $body['candidates'][0]['content']['parts'] ) ) {
				foreach ( $body['candidates'][0]['content']['parts'] as $part ) {
					// Skip thought summaries; only the answer parts carry output.
					if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
						$text .= $part['text'];
					}
				}
			} elseif ( 'claude' === $provider && ! empty( $body['content'] ) && is_array( $body['content'] ) ) {
				foreach ( $body['content'] as $part ) {
					if ( isset( $part['text'] ) ) {
						$text .= $part['text'];
					}
				}
			}
			return $text;
		}

		/**
		 * Run a prompt through WordPress AI.
		 *
		 * Uses the 7.0 `wp_ai_client_prompt()` wrapper when present, otherwise the
		 * AI client library (`\WordPress\AiClient\AiClient`).
		 *
		 * @param string     $prompt  Prompt.
		 * @param array|null $schema  JSON schema for a JSON response, or null for text.
		 * @param array      $upload  Optional array( 'file' => path, 'type' => mime ).
		 * @param int        $timeout Seconds.
		 * @return string|WP_Error
		 */
		public static function wordpress_request( $prompt, $schema = null, $upload = array(), $timeout = 20 ) {
			if ( function_exists( 'wp_ai_client_prompt' ) ) {
				try {
					$builder = wp_ai_client_prompt( $prompt );
					if ( class_exists( '\WordPress\AiClient\Providers\Http\DTO\RequestOptions' ) ) {
						$options = new \WordPress\AiClient\Providers\Http\DTO\RequestOptions();
						$options->setTimeout( $timeout );
						$options->setConnectTimeout( 10 );
						$builder->using_request_options( $options );
					}
					if ( null !== $schema ) {
						$builder->as_json_response( $schema );
					}
					if ( ! empty( $upload['file'] ) ) {
						$builder->with_file( $upload['file'], $upload['type'] );
					}
					$result = $builder->generate_text();
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					return is_string( $result ) ? $result : new WP_Error( 'rpress_wp_ai_failed', __( 'WordPress AI returned an unreadable response. Check the connected provider and try again.', 'restropress' ) );
				} catch ( Throwable $e ) {
					return new WP_Error( 'rpress_wp_ai_failed', $e->getMessage() );
				}
			}

			if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
				return new WP_Error( 'rpress_wp_ai_missing', __( 'WordPress AI is not available on this site.', 'restropress' ) );
			}

			$long = $timeout > 20;
			try {
				if ( $long ) {
					add_filter( 'http_request_timeout', array( __CLASS__, 'bump_request_timeout' ), 10, 2 );
				}
				$builder = \WordPress\AiClient\AiClient::prompt( $prompt );
				if ( class_exists( '\WordPress\AiClient\Providers\Http\DTO\RequestOptions' ) && method_exists( $builder, 'usingRequestOptions' ) ) {
					$request_options = new \WordPress\AiClient\Providers\Http\DTO\RequestOptions();
					$request_options->setTimeout( $timeout );
					$request_options->setConnectTimeout( $long ? 15 : 10 );
					$builder->usingRequestOptions( $request_options );
				}
				if ( null !== $schema ) {
					$builder->asJsonResponse( $schema );
				}
				if ( ! empty( $upload['file'] ) ) {
					if ( ! method_exists( $builder, 'withFile' ) ) {
						return new WP_Error( 'rpress_wp_ai_file_unsupported', __( 'The installed WordPress AI client cannot send files. Update it or choose OpenAI, Gemini, or Claude.', 'restropress' ) );
					}
					$builder->withFile( $upload['file'], $upload['type'] );
				}
				$result = $builder->generateTextResult();
				return (string) $result->toText();
			} catch ( Throwable $e ) {
				return new WP_Error( 'rpress_wp_ai_failed', $e->getMessage() );
			} finally {
				if ( $long ) {
					remove_filter( 'http_request_timeout', array( __CLASS__, 'bump_request_timeout' ), 10 );
				}
			}
		}

		/**
		 * Raise the HTTP timeout for AI provider hosts during long requests.
		 *
		 * @param int    $timeout Current timeout.
		 * @param string $url     Request URL.
		 * @return int
		 */
		public static function bump_request_timeout( $timeout, $url ) {
			if ( false !== strpos( $url, 'generativelanguage.googleapis.com' ) || false !== strpos( $url, 'api.openai.com' ) || false !== strpos( $url, 'api.anthropic.com' ) ) {
				return 60;
			}
			return $timeout;
		}

		/**
		 * Claude's structured output rejects open objects, so every object in the
		 * schema gets `additionalProperties: false`.
		 *
		 * @param array $schema JSON schema.
		 * @return array
		 */
		public static function close_schema_objects( $schema ) {
			if ( isset( $schema['type'] ) && 'object' === $schema['type'] ) {
				$schema['additionalProperties'] = false;
			}
			foreach ( $schema as $key => $value ) {
				if ( is_array( $value ) ) {
					$schema[ $key ] = self::close_schema_objects( $value );
				}
			}
			return $schema;
		}

		/**
		 * Decode JSON from a model reply, tolerating code fences and chatter.
		 *
		 * @param string $json    Reply text.
		 * @param string $message Error message when nothing decodes.
		 * @return array|WP_Error
		 */
		public static function decode_json( $json, $message = '' ) {
			$json = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', (string) $json ) );
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				$start = strpos( $json, '{' );
				$end   = strrpos( $json, '}' );
				if ( false !== $start && false !== $end && $end > $start ) {
					$data = json_decode( substr( $json, $start, $end - $start + 1 ), true );
				}
			}
			if ( ! is_array( $data ) ) {
				return new WP_Error( 'rpress_ai_bad_json', $message ? $message : __( 'AI returned an invalid response format.', 'restropress' ) );
			}
			return $data;
		}

		/**
		 * Validate a provider HTTP response and keep its useful error message.
		 *
		 * @param array|WP_Error $response HTTP response.
		 * @param string         $provider Provider name.
		 * @return true|WP_Error
		 */
		public static function remote_response_error( $response, $provider ) {
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				return true;
			}

			$body       = json_decode( wp_remote_retrieve_body( $response ), true );
			$message    = isset( $body['error']['message'] ) ? sanitize_text_field( $body['error']['message'] ) : '';
			$error_code = isset( $body['error']['code'] ) ? sanitize_key( $body['error']['code'] ) : '';
			$error_type = isset( $body['error']['type'] ) ? sanitize_key( $body['error']['type'] ) : '';
			if ( ! $error_type && isset( $body['error']['status'] ) ) {
				$error_type = sanitize_key( $body['error']['status'] );
			}
			if ( ! $error_type && isset( $body['error']['details'][0]['reason'] ) ) {
				$error_type = sanitize_key( $body['error']['details'][0]['reason'] );
			}
			if ( ! $message ) {
				$message = sprintf(
					/* translators: 1: provider name, 2: HTTP status code */
					__( '%1$s returned HTTP %2$d.', 'restropress' ),
					ucfirst( $provider ),
					$code
				);
			}

			return new WP_Error(
				'rpress_' . sanitize_key( $provider ) . '_http_error',
				$message,
				array(
					'status'              => $code,
					'provider_error_code' => $error_code,
					'provider_error_type' => $error_type,
				)
			);
		}

		/**
		 * Convert a provider error into a short explanation plus recovery steps.
		 *
		 * @param WP_Error $error    Error object.
		 * @param string   $provider Provider slug.
		 * @param string   $context  'import' (menu importer wording) or 'assistant'.
		 * @return array
		 */
		public static function error_details( $error, $provider = '', $context = 'import' ) {
			$message  = sanitize_text_field( $error->get_error_message() );
			$code     = method_exists( $error, 'get_error_code' ) ? (string) $error->get_error_code() : '';
			$data     = method_exists( $error, 'get_error_data' ) ? $error->get_error_data() : array();
			$data     = is_array( $data ) ? $data : array();
			$provider = $provider ? sanitize_key( $provider ) : self::get_settings()['provider'];
			$labels   = self::provider_labels();
			$label    = isset( $labels[ $provider ] ) ? $labels[ $provider ] : __( 'AI provider', 'restropress' );
			$lower    = strtolower( $message . ' ' . $code . ' ' . implode( ' ', array_filter( array_map( 'strval', array_filter( $data, 'is_scalar' ) ) ) ) );
			$details  = array(
				'title'          => 'import' === $context ? __( 'AI import could not continue', 'restropress' ) : __( 'AI request could not continue', 'restropress' ),
				'message'        => $message ? $message : __( 'The AI provider returned an unknown error.', 'restropress' ),
				'steps'          => array( 'import' === $context ? __( 'Click Save & test connection, then try the import again.', 'restropress' ) : __( 'Open the AI Assistant settings, click Save & test connection, then try again.', 'restropress' ) ),
				'error_code'     => 'provider',
				'provider_error' => true,
				'status_message' => __( 'AI connection failed.', 'restropress' ),
			);
			if ( 'import' !== $context ) {
				$details['settings_url'] = self::settings_url();
			}

			$billing_error = false !== strpos( $lower, 'insufficient_quota' ) || false !== strpos( $lower, 'billing' ) || false !== strpos( $lower, 'credit balance' ) || false !== strpos( $lower, 'hard limit' );
			$rate_error    = false !== strpos( $lower, 'rate limit' ) || false !== strpos( $lower, 'resource_exhausted' ) || false !== strpos( $lower, 'quota exceeded' ) || false !== strpos( $lower, ' 429' );

			if ( in_array( $code, array( 'rpress_ai_not_configured', 'rpress_ai_feature_disabled' ), true ) ) {
				$details['title']          = 'rpress_ai_feature_disabled' === $code ? __( 'This AI feature is switched off', 'restropress' ) : __( 'AI provider is not configured', 'restropress' );
				$details['message']        = $message;
				$details['steps']          = array( __( 'Open RestroPress > Settings > AI Assistant, choose a provider, add its API key and click Save & test connection.', 'restropress' ) );
				$details['error_code']     = 'configuration';
				$details['status_message'] = __( 'AI provider setup is incomplete.', 'restropress' );
				$details['action_url']     = self::settings_url();
				$details['action_label']   = __( 'Open AI settings', 'restropress' );
				unset( $details['technical'] );
				return $details;
			}

			if ( $billing_error ) {
				/* translators: %s: provider name */
				$details['title'] = sprintf( __( '%s billing or credits need attention', 'restropress' ), $label );
				/* translators: %s: provider name */
				$details['message'] = sprintf( __( '%s rejected the request because this API account has no available quota.', 'restropress' ), $label );
				$details['steps']   = array(
					/* translators: %s: provider name */
					sprintf( __( 'Open %s billing and add credits or raise the project spending limit.', 'restropress' ), $label ),
					__( 'Wait a few minutes for the provider to apply the change.', 'restropress' ),
					'import' === $context ? __( 'Return here, click Save & test connection, then upload the menu again.', 'restropress' ) : __( 'Return here and try again.', 'restropress' ),
				);
				$details['error_code'] = 'billing';
				/* translators: %s: provider name */
				$details['status_message'] = sprintf( __( '%s has no usable API quota.', 'restropress' ), $label );
				if ( 'openai' === $provider ) {
					$details['action_url']   = 'https://platform.openai.com/settings/organization/billing/overview';
					$details['action_label'] = __( 'Open OpenAI billing', 'restropress' );
				} elseif ( 'gemini' === $provider ) {
					$details['action_url']   = 'https://aistudio.google.com/usage';
					$details['action_label'] = __( 'Open Gemini usage', 'restropress' );
				} elseif ( 'claude' === $provider ) {
					$details['action_url']   = 'https://console.anthropic.com/settings/billing';
					$details['action_label'] = __( 'Open Claude billing', 'restropress' );
				}
			} elseif ( $rate_error ) {
				/* translators: %s: provider name */
				$details['title']          = sprintf( __( '%s usage limit was reached', 'restropress' ), $label );
				$details['message']        = __( 'The provider is temporarily refusing more requests because an API rate or usage limit was reached.', 'restropress' );
				$details['steps']          = array(
					__( 'Wait one minute and try the connection test again.', 'restropress' ),
					__( 'If it continues, check the provider project usage limits or upload fewer pages at once.', 'restropress' ),
				);
				$details['error_code']     = 'rate_limit';
				$details['status_message'] = __( 'AI provider usage limit reached.', 'restropress' );
			} elseif ( false !== strpos( $lower, 'api key' ) || false !== strpos( $lower, 'api-key' ) || false !== strpos( $lower, 'unauthenticated' ) || false !== strpos( $lower, 'authentication_error' ) || false !== strpos( $lower, 'permission_denied' ) || false !== strpos( $lower, 'incorrect_api_key' ) || false !== strpos( $lower, 'invalid_api_key' ) || false !== strpos( $lower, 'api_key_invalid' ) || false !== strpos( $lower, 'invalid authentication' ) || false !== strpos( $lower, ' 401' ) || false !== strpos( $lower, ' 403' ) ) {
				/* translators: %s: provider name */
				$details['title']   = sprintf( __( '%s rejected the API key', 'restropress' ), $label );
				$details['message'] = __( 'The saved key is missing, invalid, revoked, or belongs to the wrong provider.', 'restropress' );
				$details['steps']   = array(
					/* translators: %s: provider name */
					sprintf( __( 'Create or copy an active key from your %s account.', 'restropress' ), $label ),
					__( 'Paste it in the API key field above and click Save & test connection.', 'restropress' ),
				);
				$details['error_code']     = 'authentication';
				$details['status_message'] = __( 'API key rejected.', 'restropress' );
				if ( 'openai' === $provider ) {
					$details['action_url']   = 'https://platform.openai.com/api-keys';
					$details['action_label'] = __( 'Open OpenAI API keys', 'restropress' );
				} elseif ( 'gemini' === $provider ) {
					$details['action_url']   = 'https://aistudio.google.com/app/apikey';
					$details['action_label'] = __( 'Open Gemini API keys', 'restropress' );
				} elseif ( 'claude' === $provider ) {
					$details['action_url']   = 'https://console.anthropic.com/settings/keys';
					$details['action_label'] = __( 'Open Claude API keys', 'restropress' );
				}
			} elseif ( false !== strpos( $lower, 'timed out' ) || false !== strpos( $lower, 'curl error 28' ) ) {
				$details['title']          = __( 'The AI provider took too long to respond', 'restropress' );
				$details['message']        = 'import' === $context ? __( 'The request timed out before the menu could be read.', 'restropress' ) : __( 'The request timed out before the AI provider answered.', 'restropress' );
				$details['steps']          = array(
					'import' === $context ? __( 'Try again with a smaller file or fewer menu pages.', 'restropress' ) : __( 'Try again in a moment.', 'restropress' ),
					__( 'If it keeps happening, check the site connection to the AI provider.', 'restropress' ),
				);
				$details['error_code']     = 'timeout';
				$details['status_message'] = __( 'AI provider timed out.', 'restropress' );
			} elseif ( false !== strpos( $lower, 'network error' ) || false !== strpos( $lower, 'could not resolve host' ) || false !== strpos( $lower, 'connection refused' ) ) {
				$details['title']          = __( 'Could not reach the AI provider', 'restropress' );
				$details['message']        = __( 'The site could not connect to the selected AI service.', 'restropress' );
				$details['steps']          = array( __( 'Check the site internet connection or firewall, then click Save & test connection again.', 'restropress' ) );
				$details['error_code']     = 'network';
				$details['status_message'] = __( 'AI provider is unreachable.', 'restropress' );
			} elseif ( false !== strpos( $lower, 'model' ) && ( false !== strpos( $lower, 'not found' ) || false !== strpos( $lower, 'does not exist' ) || false !== strpos( $lower, 'unsupported' ) ) ) {
				$details['title']          = __( 'The configured AI model is unavailable', 'restropress' );
				$details['message']        = __( 'The provider no longer offers the selected model or this API project cannot use it.', 'restropress' );
				$details['steps']          = array( __( 'Update RestroPress or choose another AI provider, then test the connection again.', 'restropress' ) );
				$details['error_code']     = 'model';
				$details['status_message'] = __( 'AI model unavailable.', 'restropress' );
			}

			if ( $message && $message !== $details['message'] ) {
				$details['technical'] = $message;
			}

			return $details;
		}
	}
}
