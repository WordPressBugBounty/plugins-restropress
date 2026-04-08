<?php
/**
 * Realtime order status broadcaster (websocket provider bridge).
 *
 * @package RestroPress
 * @since 3.2.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * RPRESS_Realtime class.
 */
class RPRESS_Realtime {

	/**
	 * Setup hooks.
	 *
	 * @since 3.2.6
	 * @return void
	 */
	public static function init() {
		add_action( 'rpress_update_order_status', array( __CLASS__, 'broadcast_order_status' ), 20, 2 );
	}

	/**
	 * Build client-safe realtime config.
	 *
	 * @since 3.2.6
	 * @param int $payment_id Payment ID.
	 * @return array
	 */
	public static function get_client_config_for_payment( $payment_id = 0 ) {
		$payment_id = absint( $payment_id );
		$config     = self::get_provider_config();

		if ( ! $config['enabled'] || empty( $payment_id ) ) {
			return array(
				'enabled' => false,
			);
		}

		$payment_key = rpress_get_payment_key( $payment_id );
		$channel     = self::build_channel_name( $payment_id, $payment_key );
		$client      = array(
			'enabled'    => true,
			'provider'   => $config['provider'],
			'key'        => $config['key'],
			'cluster'    => $config['cluster'],
			'forceTLS'   => ! empty( $config['use_tls'] ),
			'channel'    => $channel,
			'event'      => 'order-status-updated',
			'script_url' => $config['client_script_url'],
		);

		if ( ! empty( $config['client_ws_host'] ) ) {
			$client['wsHost'] = $config['client_ws_host'];
		}

		if ( ! empty( $config['client_ws_port'] ) ) {
			$client['wsPort'] = (int) $config['client_ws_port'];
		}

		if ( ! empty( $config['client_wss_port'] ) ) {
			$client['wssPort'] = (int) $config['client_wss_port'];
		}

		return apply_filters( 'rpress_realtime_client_config', $client, $payment_id );
	}

	/**
	 * Broadcast order status through websocket provider API.
	 *
	 * @since 3.2.6
	 * @param int    $payment_id Payment ID.
	 * @param string $new_status New status.
	 * @return void
	 */
	public static function broadcast_order_status( $payment_id = 0, $new_status = '' ) {
		$payment_id = absint( $payment_id );
		if ( empty( $payment_id ) ) {
			return;
		}

		$config = self::get_provider_config();
		if ( ! $config['enabled'] ) {
			return;
		}

		$payment_key = rpress_get_payment_key( $payment_id );
		if ( empty( $payment_key ) ) {
			return;
		}

		$channel = self::build_channel_name( $payment_id, $payment_key );
		$payload = self::build_status_payload( $payment_id, $new_status );
		$result  = self::pusher_trigger( $config, $channel, 'order-status-updated', $payload );

		if ( is_wp_error( $result ) ) {
			/**
			 * Fires when realtime status publish fails.
			 *
			 * @since 3.2.6
			 * @param WP_Error $result     Error object.
			 * @param int      $payment_id Payment ID.
			 * @param array    $payload    Event payload.
			 */
			do_action( 'rpress_realtime_publish_error', $result, $payment_id, $payload );
		}
	}

	/**
	 * Prepare status event payload.
	 *
	 * @since 3.2.6
	 * @param int    $payment_id Payment ID.
	 * @param string $new_status New status key.
	 * @return array
	 */
	private static function build_status_payload( $payment_id, $new_status ) {
		$status         = sanitize_key( ! empty( $new_status ) ? $new_status : rpress_get_order_status( $payment_id ) );
		$status_label   = rpress_get_order_status_label( $status );
		$status_colors  = function_exists( 'rpress_get_order_status_colors' ) ? rpress_get_order_status_colors() : array();
		$status_color   = isset( $status_colors[ $status ] ) ? sanitize_hex_color( $status_colors[ $status ] ) : '';
		$payment_number = rpress_get_payment_number( $payment_id );

		return array(
			'payment_id'      => $payment_id,
			'order_number'    => rpress_format_payment_number( $payment_number ),
			'status'          => $status,
			'status_label'    => wp_strip_all_tags( (string) $status_label ),
			'status_color'    => $status_color,
			'updated_at_unix' => (int) current_time( 'timestamp', true ),
		);
	}

	/**
	 * Build channel name using payment ID + signed key fragment.
	 *
	 * @since 3.2.6
	 * @param int    $payment_id  Payment ID.
	 * @param string $payment_key Payment key.
	 * @return string
	 */
	private static function build_channel_name( $payment_id, $payment_key ) {
		$payment_id  = absint( $payment_id );
		$payment_key = (string) $payment_key;
		$hash        = substr( hash_hmac( 'sha256', $payment_key, wp_salt( 'nonce' ) ), 0, 16 );
		$channel     = sprintf( 'rpress-order-%1$d-%2$s', $payment_id, $hash );

		return (string) apply_filters( 'rpress_realtime_channel_name', $channel, $payment_id, $payment_key );
	}

	/**
	 * Send event to Pusher-compatible HTTP publish API.
	 *
	 * @since 3.2.6
	 * @param array  $config     Provider config.
	 * @param string $channel    Event channel.
	 * @param string $event_name Event name.
	 * @param array  $payload    Payload body.
	 * @return true|WP_Error
	 */
	private static function pusher_trigger( $config, $channel, $event_name, $payload ) {
		$body = array(
			'name'     => (string) $event_name,
			'channels' => array( (string) $channel ),
			'data'     => wp_json_encode( $payload ),
		);

		$body_json = wp_json_encode( $body );
		if ( false === $body_json || empty( $body_json ) ) {
			return new WP_Error( 'rpress_realtime_encode_failed', __( 'Unable to encode realtime payload.', 'restropress' ) );
		}

		$path   = '/apps/' . rawurlencode( (string) $config['app_id'] ) . '/events';
		$params = array(
			'auth_key'       => (string) $config['key'],
			'auth_timestamp' => (string) time(),
			'auth_version'   => '1.0',
			'body_md5'       => md5( $body_json ),
		);

		ksort( $params );
		$query_string = http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		$string       = "POST\n{$path}\n{$query_string}";
		$signature    = hash_hmac( 'sha256', $string, (string) $config['secret'] );

		$params['auth_signature'] = $signature;

		$scheme      = ! empty( $config['scheme'] ) ? $config['scheme'] : 'https';
		$host        = (string) $config['host'];
		$port        = (int) $config['port'];
		$default_tls = ( 'https' === $scheme && 443 === $port );
		$default_tcp = ( 'http' === $scheme && 80 === $port );
		$host_part   = ( ! empty( $port ) && ! $default_tls && ! $default_tcp ) ? $host . ':' . $port : $host;
		$url         = $scheme . '://' . $host_part . $path . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => $body_json,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'rpress_realtime_http_error',
				__( 'Realtime provider rejected event publish request.', 'restropress' ),
				array(
					'code' => $code,
					'body' => wp_remote_retrieve_body( $response ),
				)
			);
		}

		return true;
	}

	/**
	 * Get provider config from constants + filter.
	 *
	 * @since 3.2.6
	 * @return array
	 */
	public static function get_provider_config() {
		$enabled_value = self::get_setting_or_constant( 'realtime_enabled', 'RPRESS_REALTIME_ENABLED', 'yes' );
		$use_tls_value = self::get_setting_or_constant( 'realtime_use_tls', 'RPRESS_REALTIME_USE_TLS', 'yes' );

		$defaults = array(
			'provider'          => self::get_setting_or_constant( 'realtime_provider', 'RPRESS_REALTIME_PROVIDER', 'pusher' ),
			'app_id'            => self::get_setting_or_constant( 'realtime_app_id', 'RPRESS_REALTIME_APP_ID', '' ),
			'key'               => self::get_setting_or_constant( 'realtime_key', 'RPRESS_REALTIME_KEY', '' ),
			'secret'            => self::get_setting_or_constant( 'realtime_secret', 'RPRESS_REALTIME_SECRET', '' ),
			'cluster'           => self::get_setting_or_constant( 'realtime_cluster', 'RPRESS_REALTIME_CLUSTER', 'mt1' ),
			'host'              => self::get_setting_or_constant( 'realtime_host', 'RPRESS_REALTIME_HOST', '' ),
			'port'              => self::get_setting_or_constant( 'realtime_port', 'RPRESS_REALTIME_PORT', 443 ),
			'scheme'            => self::get_setting_or_constant( 'realtime_scheme', 'RPRESS_REALTIME_SCHEME', 'https' ),
			'use_tls'           => self::parse_bool_value( $use_tls_value, true ),
			'client_script_url' => self::get_setting_or_constant( 'realtime_client_script_url', 'RPRESS_REALTIME_CLIENT_SCRIPT_URL', 'https://js.pusher.com/8.4.0/pusher.min.js' ),
			'client_ws_host'    => self::get_setting_or_constant( 'realtime_client_ws_host', 'RPRESS_REALTIME_CLIENT_WS_HOST', '' ),
			'client_ws_port'    => self::get_setting_or_constant( 'realtime_client_ws_port', 'RPRESS_REALTIME_CLIENT_WS_PORT', 0 ),
			'client_wss_port'   => self::get_setting_or_constant( 'realtime_client_wss_port', 'RPRESS_REALTIME_CLIENT_WSS_PORT', 0 ),
			'enabled_toggle'    => self::parse_bool_value( $enabled_value, true ),
		);

		$config = apply_filters( 'rpress_realtime_config', $defaults );
		$config = wp_parse_args( $config, $defaults );

		$config['provider'] = strtolower( sanitize_key( $config['provider'] ) );
		$config['app_id']   = sanitize_text_field( (string) $config['app_id'] );
		$config['key']      = sanitize_text_field( (string) $config['key'] );
		$config['secret']   = sanitize_text_field( (string) $config['secret'] );
		$config['cluster']  = sanitize_key( $config['cluster'] );
		$config['scheme']   = in_array( strtolower( (string) $config['scheme'] ), array( 'http', 'https' ), true ) ? strtolower( (string) $config['scheme'] ) : 'https';
		$config['port']     = absint( $config['port'] );
		$config['use_tls']  = self::parse_bool_value( $config['use_tls'], true );

		if ( empty( $config['host'] ) ) {
			$config['host'] = 'api-' . $config['cluster'] . '.pusher.com';
		}

		$config['host']            = sanitize_text_field( $config['host'] );
		$config['client_ws_host']  = sanitize_text_field( $config['client_ws_host'] );
		$config['client_script_url'] = esc_url_raw( $config['client_script_url'] );
		$config['enabled_toggle']  = self::parse_bool_value( $config['enabled_toggle'], true );
		$config['enabled']         = (
			$config['enabled_toggle'] &&
			'pusher' === $config['provider'] &&
			! empty( $config['app_id'] ) &&
			! empty( $config['key'] ) &&
			! empty( $config['secret'] ) &&
			! empty( $config['host'] ) &&
			! empty( $config['port'] )
		);

		unset( $config['enabled_toggle'] );

		return $config;
	}

	/**
	 * Get setting value when explicitly saved, otherwise fallback to constant/default.
	 *
	 * @since 3.2.6
	 * @param string $option_key    RPRESS option key.
	 * @param string $constant_name Constant name fallback.
	 * @param mixed  $default       Default value.
	 * @return mixed
	 */
	private static function get_setting_or_constant( $option_key, $constant_name, $default = '' ) {
		$settings = function_exists( 'rpress_get_settings' ) ? rpress_get_settings() : array();

		if ( is_array( $settings ) && array_key_exists( $option_key, $settings ) ) {
			return $settings[ $option_key ];
		}

		if ( ! empty( $constant_name ) && defined( $constant_name ) ) {
			return constant( $constant_name );
		}

		return $default;
	}

	/**
	 * Normalize yes/no, true/false and 1/0 values to bool.
	 *
	 * @since 3.2.6
	 * @param mixed $value   Value to normalize.
	 * @param bool  $default Default fallback.
	 * @return bool
	 */
	private static function parse_bool_value( $value, $default = false ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (bool) absint( $value );
		}

		$normalized = strtolower( trim( (string) $value ) );
		if ( in_array( $normalized, array( 'yes', 'true', '1', 'on' ), true ) ) {
			return true;
		}

		if ( in_array( $normalized, array( 'no', 'false', '0', 'off' ), true ) ) {
			return false;
		}

		return (bool) $default;
	}
}

RPRESS_Realtime::init();
