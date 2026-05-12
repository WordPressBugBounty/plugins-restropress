<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Stripe hosted checkout gateway.
 *
 * @package     RPRESS
 * @subpackage  Gateways
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.2.8.7
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check whether the separate RestroPress Stripe extension is active.
 *
 * Core Stripe must stay out of the way whenever the extension is active.
 *
 * @since 3.2.8.7
 * @return bool
 */
function rpress_core_stripe_extension_installed() {
	if ( defined( 'RPRESS_STRIPE_PLUGIN_FILE' ) || class_exists( 'RPRESS_Stripe' ) || function_exists( 'run_rpress_stripe' ) || function_exists( 'rpresss_settings_section' ) ) {
		return true;
	}

	$plugin_paths = apply_filters(
		'rpress_core_stripe_extension_plugin_paths',
		array(
			'restropress-stripe/restropress-stripe.php',
			'rpress-stripe/rpress-stripe.php',
		)
	);

	if ( function_exists( 'get_option' ) ) {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		foreach ( $active_plugins as $plugin_file ) {
			if ( rpress_core_stripe_is_extension_plugin_file( $plugin_file, $plugin_paths ) ) {
				return true;
			}
		}
	}

	if ( is_multisite() && function_exists( 'get_site_option' ) ) {
		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );

		foreach ( array_keys( $network_plugins ) as $plugin_file ) {
			if ( rpress_core_stripe_is_extension_plugin_file( $plugin_file, $plugin_paths ) ) {
				return true;
			}
		}
	}

	if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( function_exists( 'is_plugin_active' ) ) {
		foreach ( $plugin_paths as $plugin_path ) {
			if ( is_plugin_active( $plugin_path ) || ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin_path ) ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Check whether an active plugin file belongs to the Stripe extension.
 *
 * @since 3.2.8.7
 * @param string $plugin_file  Active plugin file.
 * @param array  $plugin_paths Known Stripe extension paths.
 * @return bool
 */
function rpress_core_stripe_is_extension_plugin_file( $plugin_file, $plugin_paths ) {
	$plugin_file = strtolower( str_replace( '\\', '/', (string) $plugin_file ) );

	foreach ( $plugin_paths as $plugin_path ) {
		if ( $plugin_file === strtolower( str_replace( '\\', '/', (string) $plugin_path ) ) ) {
			return true;
		}
	}

	return false !== strpos( $plugin_file, 'restropress-stripe/' ) || false !== strpos( $plugin_file, 'rpress-stripe/' );
}

/**
 * Check whether core Stripe is available.
 *
 * @since 3.2.8.7
 * @return bool
 */
function rpress_core_stripe_is_available() {
	return ! rpress_core_stripe_extension_installed();
}

/**
 * Default core Stripe hosted checkout on for new installs.
 *
 * @since 3.2.8.7
 * @param mixed  $value   Option value.
 * @param string $key     Option key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function rpress_core_stripe_default_hosted_checkout( $value, $key, $default ) {
	if ( ! rpress_core_stripe_is_available() ) {
		return $value;
	}

	if ( false === $value || null === $value || '' === $value ) {
		return '1';
	}

	return $value;
}
add_filter( 'rpress_get_option_stripe_self_hosted', 'rpress_core_stripe_default_hosted_checkout', 10, 3 );

/**
 * Core Stripe does not render a card form.
 *
 * @since 3.2.8.7
 * @return false
 */
function rpress_core_stripe_remove_cc_form() {
	return false;
}

/**
 * Register core Stripe runtime hooks after all plugins have loaded.
 *
 * @since 3.2.8.7
 * @return void
 */
function rpress_core_stripe_register_runtime_hooks() {
	if ( ! rpress_core_stripe_is_available() ) {
		return;
	}

	add_action( 'rpress_stripe_cc_form', 'rpress_core_stripe_remove_cc_form' );
	add_action( 'rpress_gateway_stripe', 'rpress_core_stripe_process_purchase' );
	add_filter( 'allowed_redirect_hosts', 'rpress_core_stripe_allowed_redirect_hosts' );
	add_action( 'template_redirect', 'rpress_core_stripe_process_return' );
	add_filter( 'rpress_payment_confirm_stripe', 'rpress_core_stripe_success_page_content' );
	add_action( 'init', 'rpress_core_stripe_event_listener' );
	add_filter( 'rpress_get_payment_transaction_id-stripe', 'rpress_core_stripe_get_payment_transaction_id', 10, 1 );
	add_filter( 'rpress_payment_details_transaction_id-stripe', 'rpress_core_stripe_link_transaction_id', 10, 2 );
}
add_action( 'plugins_loaded', 'rpress_core_stripe_register_runtime_hooks', 20 );

/**
 * Register Stripe gateway when the Stripe extension is not installed.
 *
 * @since 3.2.8.7
 * @param array $gateways Payment gateways.
 * @return array
 */
function rpress_core_stripe_register_gateway( $gateways ) {
	if ( ! rpress_core_stripe_is_available() ) {
		return $gateways;
	}

	$stripe_label = did_action( 'init' ) ? __( 'Stripe', 'restropress' ) : 'Stripe';

	$gateways['stripe'] = array(
		'admin_label'    => $stripe_label,
		'checkout_label' => $stripe_label,
		'supports'       => array( 'buy_now' ),
	);

	return $gateways;
}
add_filter( 'rpress_payment_gateways', 'rpress_core_stripe_register_gateway', 5, 1 );

/**
 * Register Stripe settings section.
 *
 * @since 3.2.8.7
 * @param array $sections Gateway settings sections.
 * @return array
 */
function rpress_core_stripe_register_settings_section( $sections ) {
	if ( ! rpress_core_stripe_is_available() ) {
		return $sections;
	}

	$sections['stripe'] = __( 'Stripe', 'restropress' );

	return $sections;
}
add_filter( 'rpress_settings_sections_gateways', 'rpress_core_stripe_register_settings_section', 5, 1 );

/**
 * Register core Stripe settings.
 *
 * @since 3.2.8.7
 * @param array $gateway_settings Gateway settings.
 * @return array
 */
function rpress_core_stripe_register_gateway_settings( $gateway_settings ) {
	if ( ! rpress_core_stripe_is_available() ) {
		return $gateway_settings;
	}

	$stripe_settings = array(
		'stripe_settings'             => array(
			'id'   => 'stripe_settings',
			'name' => '<strong>' . __( 'Stripe Settings', 'restropress' ) . '</strong>',
			'type' => 'header',
		),
		'stripe_self_hosted'          => array(
			'id'   => 'stripe_self_hosted',
			'name' => __( 'Stripe Self Hosted Checkout', 'restropress' ),
			'desc' => __( 'It will redirect to stripe.com for checkout.', 'restropress' ),
			'type' => 'checkbox',
			'std'  => '1',
		),
		'test_secret_key'             => array(
			'id'    => 'test_secret_key',
			'name'  => __( 'Test Secret Key', 'restropress' ),
			'desc'  => __( 'Enter your test secret key, found in your Stripe Account Settings', 'restropress' ),
			'type'  => 'text',
			'size'  => 'regular',
			'class' => 'rpress-stripe-api-key-row',
		),
		'test_publishable_key'        => array(
			'id'    => 'test_publishable_key',
			'name'  => __( 'Test Publishable Key', 'restropress' ),
			'desc'  => __( 'Enter your test publishable key, found in your Stripe Account Settings', 'restropress' ),
			'type'  => 'text',
			'size'  => 'regular',
			'class' => 'rpress-stripe-api-key-row',
		),
		'live_secret_key'             => array(
			'id'    => 'live_secret_key',
			'name'  => __( 'Live Secret Key', 'restropress' ),
			'desc'  => __( 'Enter your live secret key, found in your Stripe Account Settings', 'restropress' ),
			'type'  => 'text',
			'size'  => 'regular',
			'class' => 'rpress-stripe-api-key-row',
		),
		'live_publishable_key'        => array(
			'id'    => 'live_publishable_key',
			'name'  => __( 'Live Publishable Key', 'restropress' ),
			'desc'  => __( 'Enter your live publishable key, found in your Stripe Account Settings', 'restropress' ),
			'type'  => 'text',
			'size'  => 'regular',
			'class' => 'rpress-stripe-api-key-row',
		),
		'stripe_webhook_secret'       => array(
			'id'    => 'stripe_webhook_secret',
			'name'  => __( 'Webhook Signing Secret', 'restropress' ),
			'desc'  => __( 'Enter your Stripe webhook signing secret (starts with whsec_).', 'restropress' ),
			'type'  => 'text',
			'size'  => 'regular',
			'class' => 'rpress-stripe-api-key-row',
		),
		'stripe_webhook_description'  => array(
			'id'   => 'stripe_webhook_description',
			'name' => __( 'Webhooks', 'restropress' ),
			'desc' => sprintf(
				/* translators: 1: Stripe dashboard URL, 2: webhook endpoint URL. */
				__( 'In order for Stripe to function completely, you must configure your Stripe webhooks. Visit your <a href="%1$s" target="_blank" rel="noopener noreferrer">account dashboard</a> to configure them. Please add a webhook endpoint for the URL below.<br><strong>Webhook URL: %2$s</strong>', 'restropress' ),
				esc_url( 'https://dashboard.stripe.com/account/webhooks' ),
				esc_url( home_url( 'index.php?rpress-listener=stripe' ) )
			),
			'type' => 'descriptive_text',
		),
	);

	$gateway_settings['stripe'] = apply_filters( 'rpress_core_stripe_settings', $stripe_settings );

	return $gateway_settings;
}
add_filter( 'rpress_settings_gateways', 'rpress_core_stripe_register_gateway_settings', 5, 1 );

/**
 * Get the current Stripe secret key.
 *
 * @since 3.2.8.7
 * @return string
 */
function rpress_core_stripe_get_secret_key() {
	$key = rpress_is_test_mode() ? rpress_get_option( 'test_secret_key', '' ) : rpress_get_option( 'live_secret_key', '' );

	return trim( (string) apply_filters( 'rpress_core_stripe_secret_key', $key ) );
}

/**
 * Get the Stripe webhook signing secret.
 *
 * @since 3.2.8.7
 * @return string
 */
function rpress_core_stripe_get_webhook_secret() {
	return trim( (string) rpress_get_option( 'stripe_webhook_secret', '' ) );
}

/**
 * Check whether hosted checkout is enabled.
 *
 * @since 3.2.8.7
 * @return bool
 */
function rpress_core_stripe_hosted_checkout_enabled() {
	return '1' === (string) rpress_get_option( 'stripe_self_hosted', '1' );
}

/**
 * Process Stripe checkout.
 *
 * @since 3.2.8.7
 * @param array $purchase_data Purchase data.
 * @return void
 */
function rpress_core_stripe_process_purchase( $purchase_data ) {
	if ( ! rpress_core_stripe_is_available() ) {
		rpress_set_error( 'stripe_extension_active', __( 'Core Stripe is disabled because the RestroPress Stripe extension is active.', 'restropress' ) );
		rpress_send_back_to_checkout( '?payment-mode=stripe' );
	}

	if ( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'rpress-gateway' ) ) {
		wp_die( esc_html__( 'Nonce verification has failed', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}

	if ( ! rpress_core_stripe_hosted_checkout_enabled() ) {
		rpress_set_error( 'stripe_hosted_checkout_required', __( 'Core Stripe only supports Stripe hosted checkout.', 'restropress' ) );
		rpress_send_back_to_checkout( '?payment-mode=stripe' );
	}

	if ( '' === rpress_core_stripe_get_secret_key() ) {
		rpress_set_error( 'stripe_missing_secret_key', __( 'Please add your Stripe secret key before placing an order.', 'restropress' ) );
		rpress_send_back_to_checkout( '?payment-mode=stripe' );
	}

	$payment_data = array(
		'price'        => $purchase_data['price'],
		'date'         => $purchase_data['date'],
		'user_email'   => $purchase_data['user_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency'     => rpress_get_currency(),
		'fooditems'    => $purchase_data['fooditems'],
		'user_info'    => $purchase_data['user_info'],
		'cart_details' => $purchase_data['cart_details'],
		'gateway'      => 'stripe',
		'status'       => 'private',
	);

	$payment_id = rpress_insert_payment( $payment_data );

	if ( ! $payment_id ) {
		rpress_record_gateway_error( __( 'Payment Error', 'restropress' ), sprintf( __( 'Payment creation failed before sending the customer to Stripe. Payment data: %s', 'restropress' ), wp_json_encode( $payment_data ) ), 0 );
		rpress_send_back_to_checkout( '?payment-mode=stripe' );
	}

	RPRESS()->session->set( 'rpress_resume_payment', $payment_id );

	$success_url = add_query_arg(
		array(
			'payment-confirmation' => 'stripe',
			'payment-id'           => $payment_id,
		),
		get_permalink( rpress_get_option( 'success_page', false ) )
	);

	$cancel_url = rpress_get_checkout_uri( array( 'payment-mode' => 'stripe' ) );

	$session_args = array(
		'client_reference_id' => $payment_id,
		'mode'                => 'payment',
		'success_url'         => $success_url,
		'cancel_url'          => $cancel_url,
		'payment_method_types' => array( 'card' ),
		'line_items'          => array(
			array(
				'price_data' => array(
					'currency'     => strtolower( rpress_get_currency() ),
					'unit_amount'  => rpress_core_stripe_get_amount( $purchase_data['price'] ),
					'product_data' => array(
						'name' => sprintf( __( 'Total Order #%d', 'restropress' ), $payment_id ),
					),
				),
				'quantity'   => 1,
			),
		),
		'payment_intent_data' => array(
			'description' => rpress_core_stripe_get_payment_description( $purchase_data['cart_details'] ),
			'metadata'    => array(
				'rpress_payment_id' => $payment_id,
			),
		),
		'metadata'            => array(
			'rpress_payment_id' => $payment_id,
		),
	);

	if ( ! empty( $purchase_data['user_email'] ) ) {
		$session_args['customer_email'] = sanitize_email( $purchase_data['user_email'] );
	}

	try {
		$checkout_session = rpress_core_stripe_api_request( 'POST', 'checkout/sessions', $session_args );
	} catch ( Exception $e ) {
		rpress_record_gateway_error( __( 'Stripe Error', 'restropress' ), $e->getMessage(), $payment_id );
		rpress_update_payment_status( $payment_id, 'failed' );
		rpress_set_error( 'stripe_checkout_error', $e->getMessage() );
		rpress_send_back_to_checkout( '?payment-mode=stripe' );
	}

	if ( empty( $checkout_session['id'] ) || empty( $checkout_session['url'] ) ) {
		rpress_update_payment_status( $payment_id, 'failed' );
		rpress_set_error( 'stripe_checkout_error', __( 'Unable to create a Stripe checkout session.', 'restropress' ) );
		rpress_send_back_to_checkout( '?payment-mode=stripe' );
	}

	update_post_meta( $payment_id, '_stripe_checkout_sessions_key', sanitize_text_field( $checkout_session['id'] ) );
	rpress_insert_payment_note( $payment_id, sprintf( __( 'Stripe Checkout Session ID: %s', 'restropress' ), sanitize_text_field( $checkout_session['id'] ) ) );

	wp_safe_redirect( esc_url_raw( $checkout_session['url'] ) );
	exit;
}
/**
 * Allow safe redirects to Stripe Checkout.
 *
 * @since 3.2.8.7
 * @param array $hosts Allowed redirect hosts.
 * @return array
 */
function rpress_core_stripe_allowed_redirect_hosts( $hosts ) {
	$hosts[] = 'checkout.stripe.com';
	$hosts[] = 'stripe.com';

	return array_unique( $hosts );
}

/**
 * Run a Stripe API request.
 *
 * @since 3.2.8.7
 * @param string $method   HTTP method.
 * @param string $endpoint Stripe endpoint.
 * @param array  $params   Request params.
 * @return array
 * @throws Exception When the request fails.
 */
function rpress_core_stripe_api_request( $method, $endpoint, $params = array() ) {
	$secret_key = rpress_core_stripe_get_secret_key();

	if ( '' === $secret_key ) {
		throw new Exception( __( 'Missing Stripe secret key.', 'restropress' ) );
	}

	$method = strtoupper( $method );
	$url    = 'https://api.stripe.com/v1/' . ltrim( $endpoint, '/' );
	$args   = array(
		'method'  => $method,
		'timeout' => 70,
		'headers' => array(
			'Authorization' => 'Bearer ' . $secret_key,
		),
	);

	if ( 'GET' === $method && ! empty( $params ) ) {
		$url = add_query_arg( $params, $url );
	} elseif ( ! empty( $params ) ) {
		$args['body'] = $params;
	}

	$response = wp_remote_request( $url, $args );

	if ( is_wp_error( $response ) ) {
		throw new Exception( $response->get_error_message() );
	}

	$body = wp_remote_retrieve_body( $response );
	$code = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( $body, true );

	if ( ! is_array( $data ) ) {
		throw new Exception( __( 'Stripe returned an invalid response.', 'restropress' ) );
	}

	if ( $code < 200 || $code >= 300 ) {
		$message = ! empty( $data['error']['message'] ) ? $data['error']['message'] : __( 'Stripe request failed.', 'restropress' );
		throw new Exception( $message );
	}

	return $data;
}

/**
 * Return amount in Stripe minor units.
 *
 * @since 3.2.8.7
 * @param float $amount Amount.
 * @return int
 */
function rpress_core_stripe_get_amount( $amount ) {
	$amount = (float) $amount;

	if ( rpress_core_stripe_is_zero_decimal_currency() ) {
		return absint( round( $amount ) );
	}

	return absint( round( $amount * 100 ) );
}

/**
 * Check if current currency is zero decimal in Stripe.
 *
 * @since 3.2.8.7
 * @return bool
 */
function rpress_core_stripe_is_zero_decimal_currency() {
	$zero_decimal_currencies = array(
		'BIF',
		'CLP',
		'DJF',
		'GNF',
		'JPY',
		'KMF',
		'KRW',
		'MGA',
		'PYG',
		'RWF',
		'VND',
		'VUV',
		'XAF',
		'XOF',
		'XPF',
	);

	return in_array( strtoupper( rpress_get_currency() ), $zero_decimal_currencies, true );
}

/**
 * Build a short Stripe payment description.
 *
 * @since 3.2.8.7
 * @param array $cart_details Cart details.
 * @return string
 */
function rpress_core_stripe_get_payment_description( $cart_details ) {
	$description = '';

	if ( is_array( $cart_details ) ) {
		foreach ( $cart_details as $cart_item ) {
			if ( ! empty( $cart_item['name'] ) ) {
				$description .= $cart_item['name'] . ', ';
			}
		}
	}

	$description = trim( $description, ', ' );

	if ( '' === $description ) {
		$description = __( 'RestroPress order', 'restropress' );
	}

	if ( strlen( $description ) > 250 ) {
		$description = substr( $description, 0, 247 ) . '...';
	}

	return html_entity_decode( $description, ENT_COMPAT, 'UTF-8' );
}

/**
 * Try to complete Stripe payment on return from Checkout.
 *
 * @since 3.2.8.7
 * @return void
 */
function rpress_core_stripe_process_return() {
	if ( ! rpress_core_stripe_is_available() || ! rpress_is_success_page() || empty( $_GET['payment-confirmation'] ) || 'stripe' !== sanitize_text_field( wp_unslash( $_GET['payment-confirmation'] ) ) ) {
		return;
	}

	$payment_id = ! empty( $_GET['payment-id'] ) ? absint( wp_unslash( $_GET['payment-id'] ) ) : 0;

	if ( empty( $payment_id ) ) {
		return;
	}

	$session_id = get_post_meta( $payment_id, '_stripe_checkout_sessions_key', true );

	if ( empty( $session_id ) ) {
		return;
	}

	rpress_core_stripe_update_payment_from_session( $session_id, $payment_id );
}
/**
 * Show a processing template if Stripe is still pending.
 *
 * @since 3.2.8.7
 * @param string $content Success page content.
 * @return string
 */
function rpress_core_stripe_success_page_content( $content ) {
	if ( ! isset( $_REQUEST['payment-id'] ) && ! rpress_get_purchase_session() ) {
		return $content;
	}

	rpress_empty_cart();

	$payment_id = isset( $_REQUEST['payment-id'] ) ? absint( wp_unslash( $_REQUEST['payment-id'] ) ) : false;

	if ( ! $payment_id ) {
		$session    = rpress_get_purchase_session();
		$payment_id = ! empty( $session['purchase_key'] ) ? rpress_get_purchase_id_by_key( $session['purchase_key'] ) : 0;
	}

	$payment = new RPRESS_Payment( $payment_id );

	if ( $payment->ID > 0 && in_array( $payment->status, array( 'pending', 'private' ), true ) ) {
		ob_start();
		rpress_get_template_part( 'payment', 'processing' );
		$content = ob_get_clean();
	}

	return $content;
}

/**
 * Listen for Stripe webhooks.
 *
 * @since 3.2.8.7
 * @return void
 */
function rpress_core_stripe_event_listener() {
	if ( ! rpress_core_stripe_is_available() || empty( $_GET['rpress-listener'] ) || 'stripe' !== sanitize_text_field( wp_unslash( $_GET['rpress-listener'] ) ) ) {
		return;
	}

	$payload          = file_get_contents( 'php://input' );
	$signature       = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';
	$webhook_secret  = rpress_core_stripe_get_webhook_secret();

	if ( '' === $webhook_secret || ! rpress_core_stripe_verify_webhook_signature( $payload, $signature, $webhook_secret ) ) {
		status_header( 400 );
		echo 'Invalid signature';
		exit;
	}

	$event = json_decode( $payload, true );

	if ( ! is_array( $event ) || empty( $event['type'] ) ) {
		status_header( 400 );
		echo 'Invalid payload';
		exit;
	}

	$object = isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ? $event['data']['object'] : array();

	switch ( $event['type'] ) {
		case 'checkout.session.completed':
		case 'checkout.session.async_payment_succeeded':
			if ( ! empty( $object['id'] ) ) {
				$payment_id = rpress_core_stripe_get_payment_id_by_checkout_session( $object['id'] );

				if ( $payment_id ) {
					rpress_core_stripe_update_payment_from_session( $object['id'], $payment_id, $object );
				}
			}
			break;

		case 'checkout.session.async_payment_failed':
			if ( ! empty( $object['id'] ) ) {
				$payment_id = rpress_core_stripe_get_payment_id_by_checkout_session( $object['id'] );

				if ( $payment_id ) {
					rpress_update_payment_status( $payment_id, 'failed' );
					rpress_insert_payment_note( $payment_id, __( 'Stripe checkout payment failed.', 'restropress' ) );
				}
			}
			break;

		case 'checkout.session.expired':
			if ( ! empty( $object['id'] ) ) {
				$payment_id = rpress_core_stripe_get_payment_id_by_checkout_session( $object['id'] );

				if ( $payment_id ) {
					rpress_insert_payment_note( $payment_id, __( 'Stripe checkout session expired.', 'restropress' ) );
					delete_post_meta( $payment_id, '_stripe_checkout_sessions_key' );
				}
			}
			break;
	}

	status_header( 200 );
	echo 'OK';
	exit;
}

/**
 * Verify Stripe webhook signature.
 *
 * @since 3.2.8.7
 * @param string $payload Payload.
 * @param string $header  Stripe signature header.
 * @param string $secret  Webhook signing secret.
 * @return bool
 */
function rpress_core_stripe_verify_webhook_signature( $payload, $header, $secret ) {
	$timestamp  = '';
	$signatures = array();
	$parts      = explode( ',', $header );

	foreach ( $parts as $part ) {
		$key_value = explode( '=', $part, 2 );

		if ( 2 !== count( $key_value ) ) {
			continue;
		}

		if ( 't' === $key_value[0] ) {
			$timestamp = $key_value[1];
		}

		if ( 'v1' === $key_value[0] ) {
			$signatures[] = $key_value[1];
		}
	}

	if ( empty( $timestamp ) || empty( $signatures ) ) {
		return false;
	}

	$tolerance = (int) apply_filters( 'rpress_core_stripe_webhook_tolerance', 300 );

	if ( $tolerance > 0 && abs( time() - (int) $timestamp ) > $tolerance ) {
		return false;
	}

	$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

	foreach ( $signatures as $signature ) {
		if ( rpress_core_stripe_hash_equals( $expected, $signature ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Timing-safe string compare with PHP 5.5 fallback.
 *
 * @since 3.2.8.7
 * @param string $known_string Known string.
 * @param string $user_string  User string.
 * @return bool
 */
function rpress_core_stripe_hash_equals( $known_string, $user_string ) {
	if ( function_exists( 'hash_equals' ) ) {
		return hash_equals( $known_string, $user_string );
	}

	if ( strlen( $known_string ) !== strlen( $user_string ) ) {
		return false;
	}

	$result = 0;
	$length = strlen( $known_string );

	for ( $i = 0; $i < $length; $i++ ) {
		$result |= ord( $known_string[ $i ] ) ^ ord( $user_string[ $i ] );
	}

	return 0 === $result;
}

/**
 * Get payment ID by stored Stripe Checkout Session ID.
 *
 * @since 3.2.8.7
 * @param string $session_id Checkout session ID.
 * @return int
 */
function rpress_core_stripe_get_payment_id_by_checkout_session( $session_id ) {
	global $wpdb;

	return absint(
		$wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_stripe_checkout_sessions_key' AND meta_value = %s LIMIT 1",
				$session_id
			)
		)
	);
}

/**
 * Update a payment using a Stripe Checkout Session.
 *
 * @since 3.2.8.7
 * @param string $session_id      Checkout session ID.
 * @param int    $payment_id      Payment ID.
 * @param array  $checkout_session Optional checkout session object.
 * @return void
 */
function rpress_core_stripe_update_payment_from_session( $session_id, $payment_id, $checkout_session = array() ) {
	$payment_id = absint( $payment_id );

	if ( empty( $payment_id ) ) {
		return;
	}

	$payment = new RPRESS_Payment( $payment_id );

	if ( empty( $payment->ID ) || 'stripe' !== $payment->gateway || in_array( $payment->status, array( 'publish', 'complete' ), true ) ) {
		return;
	}

	if ( empty( $checkout_session ) ) {
		try {
			$checkout_session = rpress_core_stripe_api_request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ) );
		} catch ( Exception $e ) {
			rpress_record_gateway_error( __( 'Stripe Error', 'restropress' ), $e->getMessage(), $payment_id );
			return;
		}
	}

	$paid_status = ! empty( $checkout_session['payment_status'] ) ? sanitize_key( $checkout_session['payment_status'] ) : '';

	if ( 'paid' !== $paid_status ) {
		return;
	}

	$stripe_total = isset( $checkout_session['amount_total'] ) ? rpress_core_stripe_amount_from_minor_units( $checkout_session['amount_total'] ) : (float) $payment->total;

	if ( round( (float) $stripe_total, 2 ) < round( (float) $payment->total, 2 ) ) {
		rpress_update_payment_status( $payment_id, 'failed' );
		rpress_insert_payment_note( $payment_id, __( 'Stripe payment failed due to an invalid amount.', 'restropress' ) );
		return;
	}

	$payment_intent = ! empty( $checkout_session['payment_intent'] ) ? sanitize_text_field( $checkout_session['payment_intent'] ) : '';

	if ( '' !== $payment_intent ) {
		rpress_insert_payment_note( $payment_id, sprintf( __( 'Stripe PaymentIntent ID: %s', 'restropress' ), $payment_intent ) );
		rpress_set_payment_transaction_id( $payment_id, $payment_intent );
	}

	if ( ! empty( $checkout_session['customer'] ) ) {
		update_post_meta( $payment_id, '_rpress_stripe_customer_id', sanitize_text_field( $checkout_session['customer'] ) );
	}

	rpress_update_payment_status( $payment_id, 'publish' );
	delete_post_meta( $payment_id, '_stripe_checkout_sessions_key' );
}

/**
 * Convert Stripe minor units to a RestroPress amount.
 *
 * @since 3.2.8.7
 * @param int $amount Amount in Stripe minor units.
 * @return float
 */
function rpress_core_stripe_amount_from_minor_units( $amount ) {
	$amount = (float) $amount;

	if ( rpress_core_stripe_is_zero_decimal_currency() ) {
		return $amount;
	}

	return $amount / 100;
}

/**
 * Get Stripe transaction ID when order details render.
 *
 * @since 3.2.8.7
 * @param int $payment_id Payment ID.
 * @return string
 */
function rpress_core_stripe_get_payment_transaction_id( $payment_id ) {
	return get_post_meta( $payment_id, '_rpress_payment_transaction_id', true );
}

/**
 * Link Stripe transaction IDs in the admin order details.
 *
 * @since 3.2.8.7
 * @param string $transaction_id Transaction ID.
 * @param int    $payment_id     Payment ID.
 * @return string
 */
function rpress_core_stripe_link_transaction_id( $transaction_id, $payment_id ) {
	if ( empty( $transaction_id ) ) {
		return '';
	}

	$payment = new RPRESS_Payment( $payment_id );
	$mode    = ( ! empty( $payment->mode ) && 'test' === $payment->mode ) ? 'test/' : '';
	$url     = 'https://dashboard.stripe.com/' . $mode . 'payments/' . rawurlencode( $transaction_id );

	return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $transaction_id ) . '</a>';
}
