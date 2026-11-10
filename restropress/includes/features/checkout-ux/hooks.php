<?php
/**
 * Checkout UX wiring: assets, the cart state AJAX endpoint, and the extra
 * data added to the delivery/pickup switch response.
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the checkout UX assets should load on this request.
 *
 * Always on checkout. Elsewhere only when a minimum order is configured,
 * because the cart sidebar and mobile cart bar then need the live hint.
 *
 * @since 3.5
 * @return bool
 */
function rpress_checkout_ux_should_load() {
	if ( is_admin() ) {
		return false;
	}
	$on_checkout = function_exists( 'rpress_is_checkout' ) && rpress_is_checkout();
	$load        = $on_checkout || ( ! empty( rpress_get_option( 'allow_minimum_order' ) ) && wp_script_is( 'rp-frontend', 'enqueued' ) );

	/**
	 * Filter whether the checkout UX scripts load on the current request.
	 *
	 * @since 3.5
	 * @param bool $load        Whether to load.
	 * @param bool $on_checkout Whether this is the checkout page.
	 */
	return (bool) apply_filters( 'rpress_checkout_ux_should_load', $load, $on_checkout );
}

/**
 * Enqueue the checkout UX script and stylesheet.
 *
 * Runs after the core frontend scripts (priority 10) so the dependencies
 * exist and the redesigned checkout stylesheet is already queued; our CSS
 * depends on it so it always loads after it.
 *
 * @since 3.5
 * @return void
 */
function rpress_checkout_ux_enqueue() {
	if ( ! rpress_checkout_ux_should_load() ) {
		return;
	}

	$base_dir = RP_PLUGIN_DIR . 'assets/features/checkout-ux/';
	$base_url = plugin_dir_url( RP_PLUGIN_FILE ) . 'assets/features/checkout-ux/';
	$js_ver   = RP_VERSION . '.' . ( file_exists( $base_dir . 'checkout-ux.js' ) ? filemtime( $base_dir . 'checkout-ux.js' ) : '0' );
	$css_ver  = RP_VERSION . '.' . ( file_exists( $base_dir . 'checkout-ux.css' ) ? filemtime( $base_dir . 'checkout-ux.css' ) : '0' );

	$on_checkout = function_exists( 'rpress_is_checkout' ) && rpress_is_checkout();
	$style_deps  = wp_style_is( 'rpress-checkout', 'registered' ) ? array( 'rpress-checkout' ) : ( wp_style_is( 'rpress-styles', 'registered' ) ? array( 'rpress-styles' ) : array() );
	$script_deps = array( 'jquery' );
	if ( wp_script_is( 'rp-frontend', 'registered' ) ) {
		$script_deps[] = 'rp-frontend';
	}
	if ( $on_checkout && wp_script_is( 'rp-checkout-ui', 'registered' ) ) {
		$script_deps[] = 'rp-checkout-ui';
	}

	wp_enqueue_style( 'rpress-checkout-ux', $base_url . 'checkout-ux.css', $style_deps, $css_ver );
	wp_enqueue_script( 'rpress-checkout-ux', $base_url . 'checkout-ux.js', $script_deps, $js_ver, true );

	$decimals = function_exists( 'rpress_currency_decimal_filter' ) ? (int) rpress_currency_decimal_filter() : 2;
	// "₹%s" style template built from the store's own formatter so the
	// symbol position and spacing match every other price on the page.
	$zero     = rpress_format_amount( 0 );
	$template = str_replace( $zero, '%s', rpress_checkout_ux_format_amount( 0 ) );
	if ( false === strpos( $template, '%s' ) ) {
		$template = '%s';
	}

	wp_localize_script(
		'rpress-checkout-ux',
		'rpress_checkout_ux',
		apply_filters(
			'rpress_checkout_ux_vars',
			array(
				'ajaxurl'         => rpress_get_ajax_url(),
				'nonce'           => wp_create_nonce( 'rpress-checkout-ux' ),
				'is_checkout'     => $on_checkout ? '1' : '0',
				// Only checkout (never page-cached) gets the cart state in the page.
				// Elsewhere it is null and the script fetches it on load, so a page
				// cache never bakes one visitor's cart into the HTML.
				'state'           => $on_checkout ? rpress_checkout_ux_state() : null,
				'address_fields'  => $on_checkout ? rpress_checkout_ux_required_address_fields() : array(),
				'currency'        => array(
					'template'  => $template,
					'decimals'  => $decimals,
					'thousands' => rpress_get_option( 'thousands_separator', ',' ),
					'decimal'   => rpress_get_option( 'decimal_separator', '.' ),
				),
				'i18n'            => array(
					'err_email'        => __( 'Please enter your email address', 'restropress' ),
					'err_email_format' => __( 'That email doesn\'t look right, check for typos', 'restropress' ),
					'err_phone'        => __( 'Please enter your phone number', 'restropress' ),
					'err_phone_format' => __( 'Please enter a valid phone number (8 to 15 characters)', 'restropress' ),
					'err_first'        => __( 'Please enter your first name', 'restropress' ),
					'err_last'         => __( 'Please enter your last name', 'restropress' ),
					'err_required'     => __( 'This field is required.', 'restropress' ),
					/* translators: %d: number of fields with errors */
					'err_banner'       => __( 'Please fix the %d highlighted field(s) below to place your order.', 'restropress' ),
					/* translators: 1: service name, 2: new order total, 3: amount the total went up by */
					'switch_more'      => __( '%1$s selected. Your total is now %2$s (%3$s more).', 'restropress' ),
					/* translators: 1: service name, 2: new order total, 3: amount the total went down by */
					'switch_less'      => __( '%1$s selected. Your total is now %2$s (%3$s less).', 'restropress' ),
					/* translators: 1: service name, 2: order total */
					'switch_same'      => __( '%1$s selected. Your total is still %2$s.', 'restropress' ),
				),
			)
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rpress_checkout_ux_enqueue', 30 );

/**
 * AJAX: current cart state (totals + minimum-order hint) for a service.
 *
 * Read-only; guarded by a nonce so it cannot be used as an open endpoint.
 *
 * @since 3.5
 * @return void
 */
function rpress_checkout_ux_ajax_state() {
	if ( ! check_ajax_referer( 'rpress-checkout-ux', 'security', false ) ) {
		wp_send_json_error( array( 'message' => 'invalid_nonce' ), 403 );
	}
	$service = isset( $_POST['service'] ) ? sanitize_key( wp_unslash( $_POST['service'] ) ) : '';
	wp_send_json_success( rpress_checkout_ux_state( $service ) );
}
add_action( 'wp_ajax_rpress_checkout_ux_state', 'rpress_checkout_ux_ajax_state' );
add_action( 'wp_ajax_nopriv_rpress_checkout_ux_state', 'rpress_checkout_ux_ajax_state' );

/**
 * Add the cart state to the delivery/pickup switch response so the
 * checkout can update the minimum hint and announce the total change
 * without a second request.
 *
 * @since 3.5
 * @param array $data Response data from RP_AJAX::checkout_update_service_option().
 * @return array
 */
function rpress_checkout_ux_service_switch_response( $data ) {
	if ( is_array( $data ) ) {
		$data['checkout_ux'] = rpress_checkout_ux_state();
	}
	return $data;
}
add_filter( 'rpress_checkout_update_service_option_response', 'rpress_checkout_ux_service_switch_response' );
