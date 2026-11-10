<?php
/**
 * Apple Pay / Google Pay through Stripe's Payment Request Button.
 *
 * Flow: the checkout shows the wallet button when Stripe is the selected
 * gateway and the browser has a wallet. After the customer approves in the
 * wallet sheet, the JS validates the checkout form (the normal AJAX check),
 * then posts the form with the wallet PaymentMethod ID. The Stripe gateway
 * hook below creates the order and a PaymentIntent confirmed server side and
 * answers with JSON. 3D Secure, when asked for, is finished in the browser,
 * then the return URL and the payment_intent.succeeded webhook mark it paid.
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe publishable key for the current mode.
 *
 * @since 3.5
 * @return string
 */
function rpress_payments_stripe_publishable_key() {
	$key = rpress_is_test_mode() ? rpress_get_option( 'test_publishable_key', '' ) : rpress_get_option( 'live_publishable_key', '' );
	return trim( (string) apply_filters( 'rpress_core_stripe_publishable_key', $key ) );
}

/**
 * Whether the wallet button should be offered.
 *
 * @since 3.5
 * @return bool
 */
function rpress_payments_stripe_wallets_enabled() {
	$enabled = function_exists( 'rpress_core_stripe_is_available' )
		&& rpress_core_stripe_is_available()
		&& rpress_is_gateway_active( 'stripe' )
		&& 'hide' !== rpress_get_option( 'stripe_wallets', 'show' )
		&& '' !== rpress_payments_stripe_publishable_key()
		&& '' !== rpress_core_stripe_get_secret_key();

	return (bool) apply_filters( 'rpress_payments_stripe_wallets_enabled', $enabled );
}

/**
 * Cart total for the wallet sheet.
 *
 * @since 3.5
 * @return array
 */
function rpress_payments_stripe_wallet_total() {
	$label = get_bloginfo( 'name' );
	return array(
		'amount'   => rpress_core_stripe_get_amount( rpress_get_cart_total() ),
		'currency' => strtolower( rpress_get_currency() ),
		'label'    => '' !== $label ? html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) : __( 'Order total', 'restropress' ),
	);
}

add_action( 'wp_enqueue_scripts', 'rpress_payments_stripe_wallet_scripts', 20 );
/**
 * Enqueue Stripe.js and the wallet script on checkout.
 *
 * @since 3.5
 * @return void
 */
function rpress_payments_stripe_wallet_scripts() {
	if ( ! function_exists( 'rpress_is_checkout' ) || ! rpress_is_checkout() || ! rpress_payments_stripe_wallets_enabled() ) {
		return;
	}

	// Stripe.js must be loaded from js.stripe.com (PCI); it is not bundled.
	wp_register_script( 'rpress-stripe-js', 'https://js.stripe.com/v3/', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_enqueue_script( 'rpress-stripe-wallet', RP_PLUGIN_URL . 'assets/features/payments/stripe-wallets.js', array( 'jquery', 'rpress-stripe-js' ), RP_VERSION, true );
	wp_enqueue_style( 'rpress-stripe-wallet', RP_PLUGIN_URL . 'assets/features/payments/stripe-wallets.css', array(), RP_VERSION );

	wp_localize_script(
		'rpress-stripe-wallet',
		'rpress_stripe_wallet',
		array(
			'key'     => rpress_payments_stripe_publishable_key(),
			'country' => strtoupper( (string) rpress_get_shop_country() ),
			'ajaxurl' => rpress_get_ajax_url(),
			'nonce'   => wp_create_nonce( 'rpress-stripe-wallet' ),
			'total'   => rpress_payments_stripe_wallet_total(),
			'i18n'    => array(
				'generic' => __( 'The payment could not be completed. Please try again or choose another payment method.', 'restropress' ),
				'changed' => __( 'Your order total changed. Please check it and try again.', 'restropress' ),
			),
		)
	);
}

add_action( 'rpress_purchase_form_before_submit', 'rpress_payments_stripe_wallet_markup' );
/**
 * Wallet button container (hidden until Stripe.js confirms a wallet).
 *
 * @since 3.5
 * @return void
 */
function rpress_payments_stripe_wallet_markup() {
	if ( ! rpress_payments_stripe_wallets_enabled() ) {
		return;
	}
	?>
	<div id="rpress-stripe-wallet" class="rpress-stripe-wallet" hidden>
		<div id="rpress-stripe-wallet-button" class="rpress-stripe-wallet-button"></div>
		<p class="rpress-stripe-wallet-divider"><span><?php esc_html_e( 'or pay by card', 'restropress' ); ?></span></p>
	</div>
	<?php
}

add_action( 'wp_ajax_rpress_stripe_wallet_total', 'rpress_payments_stripe_wallet_total_ajax' );
add_action( 'wp_ajax_nopriv_rpress_stripe_wallet_total', 'rpress_payments_stripe_wallet_total_ajax' );
/**
 * AJAX: current cart total in Stripe minor units.
 *
 * @since 3.5
 * @return void
 */
function rpress_payments_stripe_wallet_total_ajax() {
	if ( ! check_ajax_referer( 'rpress-stripe-wallet', 'security', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page.', 'restropress' ) ), 403 );
	}
	if ( ! rpress_payments_stripe_wallets_enabled() ) {
		wp_send_json_error( array( 'message' => 'disabled' ), 400 );
	}
	wp_send_json_success( rpress_payments_stripe_wallet_total() );
}

add_action( 'rpress_gateway_stripe', 'rpress_payments_stripe_wallet_process', 5 );
/**
 * Stripe gateway, wallet branch. Runs before the hosted checkout handler and
 * exits with JSON when the checkout carries a wallet PaymentMethod.
 *
 * @since 3.5
 * @param array $purchase_data Purchase data.
 * @return void
 */
function rpress_payments_stripe_wallet_process( $purchase_data ) {
	$post_data = isset( $purchase_data['post_data'] ) && is_array( $purchase_data['post_data'] ) ? $purchase_data['post_data'] : array();
	$pm_id     = isset( $post_data['rpress_stripe_wallet_pm'] ) ? sanitize_text_field( $post_data['rpress_stripe_wallet_pm'] ) : '';

	if ( '' === $pm_id ) {
		return; // Normal hosted checkout.
	}

	$fail = function ( $message, $payment_id = 0 ) {
		if ( $payment_id ) {
			rpress_update_payment_status( $payment_id, 'failed' );
			rpress_insert_payment_note( $payment_id, sprintf( /* translators: %s: error message */ __( 'Wallet payment failed: %s', 'restropress' ), $message ) );
		}
		wp_send_json( array( 'result' => 'error', 'message' => $message ) );
	};

	if ( empty( $purchase_data['gateway_nonce'] ) || ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'rpress-gateway' ) ) {
		$fail( __( 'Security check failed. Please refresh and try again.', 'restropress' ) );
	}
	if ( ! preg_match( '/^pm_[A-Za-z0-9_]+$/', $pm_id ) || ! rpress_payments_stripe_wallets_enabled() ) {
		$fail( __( 'Apple Pay / Google Pay is not available right now. Please choose another payment method.', 'restropress' ) );
	}

	$amount = rpress_core_stripe_get_amount( $purchase_data['price'] );
	$shown  = isset( $post_data['rpress_stripe_wallet_amount'] ) ? absint( $post_data['rpress_stripe_wallet_amount'] ) : -1;
	if ( $amount <= 0 || $shown !== $amount ) {
		wp_send_json(
			array(
				'result'  => 'total_changed',
				'message' => __( 'Your order total changed. Please check it and try again.', 'restropress' ),
				'total'   => rpress_payments_stripe_wallet_total(),
			)
		);
	}

	$payment_id = rpress_insert_payment(
		array(
			'price'        => $purchase_data['price'],
			'date'         => $purchase_data['date'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => rpress_get_currency(),
			'fooditems'    => $purchase_data['fooditems'],
			'user_info'    => $purchase_data['user_info'],
			'cart_details' => $purchase_data['cart_details'],
			'gateway'      => 'stripe',
			// Hidden from the order lists until Stripe confirms, like hosted checkout.
			'status'       => 'private',
		)
	);
	if ( ! $payment_id ) {
		$fail( __( 'Could not create the order. Please try again.', 'restropress' ) );
	}
	update_post_meta( $payment_id, '_rpress_stripe_wallet', 1 );

	$success_url = add_query_arg(
		array(
			'payment-confirmation' => 'stripe',
			'payment-id'           => $payment_id,
		),
		get_permalink( rpress_get_option( 'success_page', false ) )
	);

	$args = array(
		'amount'               => $amount,
		'currency'             => strtolower( rpress_get_currency() ),
		'payment_method'       => $pm_id,
		'payment_method_types' => array( 'card' ),
		'confirm'              => 'true',
		'description'          => rpress_core_stripe_get_payment_description( $purchase_data['cart_details'] ),
		'metadata'             => array(
			'rpress_payment_id' => $payment_id,
			'rpress_site'       => rpress_payments_stripe_site_key(),
		),
	);
	if ( ! empty( $purchase_data['user_email'] ) ) {
		$args['receipt_email'] = sanitize_email( $purchase_data['user_email'] );
	}

	try {
		$intent = rpress_core_stripe_api_request( 'POST', 'payment_intents', $args, array( 'Idempotency-Key' => 'rpress-wallet-' . rpress_payments_stripe_site_key() . '-' . $payment_id ) );
	} catch ( Exception $e ) {
		rpress_record_gateway_error( __( 'Stripe Error', 'restropress' ), $e->getMessage(), $payment_id );
		$fail( $e->getMessage(), $payment_id );
	}

	$intent_id = ! empty( $intent['id'] ) ? sanitize_text_field( $intent['id'] ) : '';
	$status    = ! empty( $intent['status'] ) ? sanitize_key( $intent['status'] ) : '';
	if ( '' === $intent_id ) {
		$fail( __( 'Stripe did not return a payment. Please try again.', 'restropress' ), $payment_id );
	}

	rpress_set_payment_transaction_id( $payment_id, $intent_id );
	update_post_meta( $payment_id, '_rpress_stripe_wallet_intent', $intent_id );
	rpress_insert_payment_note( $payment_id, sprintf( /* translators: %s: PaymentIntent ID */ __( 'Apple Pay / Google Pay PaymentIntent: %s', 'restropress' ), $intent_id ) );

	if ( 'succeeded' === $status ) {
		rpress_payments_stripe_mark_paid( $payment_id, $intent, __( 'wallet checkout', 'restropress' ) );
		wp_send_json( array( 'result' => 'success', 'redirect' => esc_url_raw( $success_url ) ) );
	}

	// Orders stay hidden (private) until Stripe confirms them, as with hosted checkout.
	if ( in_array( $status, array( 'requires_action', 'requires_source_action' ), true ) && ! empty( $intent['client_secret'] ) ) {
		wp_send_json(
			array(
				'result'        => 'requires_action',
				'client_secret' => $intent['client_secret'],
				'redirect'      => esc_url_raw( $success_url ),
			)
		);
	}

	if ( 'processing' === $status ) {
		// The payment_intent.succeeded webhook finishes it.
		wp_send_json( array( 'result' => 'success', 'redirect' => esc_url_raw( $success_url ) ) );
	}

	$message = ! empty( $intent['last_payment_error']['message'] ) ? sanitize_text_field( $intent['last_payment_error']['message'] ) : __( 'The payment was declined.', 'restropress' );
	$fail( $message, $payment_id );
}

add_action( 'template_redirect', 'rpress_payments_stripe_wallet_return', 9 );
/**
 * Success page return after 3D Secure: ask Stripe for the PaymentIntent and
 * mark the order paid if it succeeded. Safe for any visitor: it only reads
 * Stripe and only ever moves an order to paid on a succeeded intent.
 *
 * @since 3.5
 * @return void
 */
function rpress_payments_stripe_wallet_return() {
	if ( empty( $_GET['payment-confirmation'] ) || 'stripe' !== sanitize_key( wp_unslash( $_GET['payment-confirmation'] ) ) || empty( $_GET['payment-id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! function_exists( 'rpress_is_success_page' ) || ! rpress_is_success_page() || ! function_exists( 'rpress_core_stripe_is_available' ) || ! rpress_core_stripe_is_available() ) {
		return;
	}

	$payment_id = absint( wp_unslash( $_GET['payment-id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$intent_id  = (string) get_post_meta( $payment_id, '_rpress_stripe_wallet_intent', true );
	if ( '' === $intent_id || 'stripe' !== rpress_get_payment_gateway( $payment_id ) ) {
		return;
	}

	$status = get_post_status( $payment_id );
	if ( in_array( $status, array( 'publish', 'complete', 'refunded' ), true ) ) {
		return;
	}

	try {
		$intent = rpress_core_stripe_api_request( 'GET', 'payment_intents/' . rawurlencode( $intent_id ) );
	} catch ( Exception $e ) {
		rpress_record_gateway_error( __( 'Stripe Error', 'restropress' ), $e->getMessage(), $payment_id );
		return;
	}

	$intent_status = ! empty( $intent['status'] ) ? $intent['status'] : '';
	if ( 'succeeded' === $intent_status ) {
		rpress_payments_stripe_mark_paid( $payment_id, $intent, __( 'return from authentication', 'restropress' ) );
	} elseif ( 'requires_payment_method' === $intent_status ) {
		rpress_update_payment_status( $payment_id, 'failed' );
		rpress_insert_payment_note( $payment_id, __( 'Wallet payment authentication failed or was cancelled.', 'restropress' ) );
	}
}
