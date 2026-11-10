<?php
/**
 * Checkout UX helpers: minimum-order state and the cart snapshot the
 * checkout and cart scripts render from.
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the service type the minimum applies to, the same way
 * rpress_pre_validate_order() does: an explicit request value, then the
 * service_type cookie, then the store default. Unknown or disabled services
 * fall back to the default.
 *
 * @since 3.5
 * @param string $requested Optional service slug sent by the browser.
 * @return string Service slug, or '' when no service is enabled.
 */
function rpress_checkout_ux_resolve_service( $requested = '' ) {
	$service = sanitize_key( (string) $requested );
	if ( '' === $service && ! empty( $_COOKIE['service_type'] ) ) {
		$service = sanitize_key( wp_unslash( $_COOKIE['service_type'] ) );
	}

	$enabled = function_exists( 'rpress_get_enabled_services' ) ? (array) rpress_get_enabled_services() : array( 'delivery', 'pickup' );
	if ( '' === $service || ! in_array( $service, $enabled, true ) ) {
		$service = function_exists( 'rpress_get_default_enabled_service' ) ? sanitize_key( (string) rpress_get_default_enabled_service() ) : '';
	}

	return $service;
}

/**
 * Format an amount as plain text (entities decoded) for use in JS .text().
 *
 * @since 3.5
 * @param float $amount Amount.
 * @return string
 */
function rpress_checkout_ux_format_amount( $amount ) {
	return html_entity_decode( rpress_currency_filter( rpress_format_amount( (float) $amount ) ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Minimum-order state for a service and cart subtotal.
 *
 * Uses the same settings and comparison as rpress_pre_validate_order():
 * allow_minimum_order toggles it, minimum_order_price applies to delivery,
 * minimum_order_price_pickup to pickup, and the cart subtotal must not be
 * below the minimum. Other services have no minimum.
 *
 * @since 3.5
 * @param string     $service  Service slug (delivery|pickup|...).
 * @param float|null $subtotal Cart subtotal; null reads the live cart.
 * @return array {
 *     @type bool   $enabled             Whether a minimum applies to this service.
 *     @type string $service             Service slug.
 *     @type float  $minimum             Minimum amount (0 when none).
 *     @type float  $subtotal            Subtotal compared.
 *     @type float  $shortfall           How much more is needed (0 when met).
 *     @type bool   $below               True while the cart is below the minimum.
 *     @type string $minimum_formatted   Minimum as currency text.
 *     @type string $shortfall_formatted Shortfall as currency text.
 *     @type string $message             "Add ₹40.00 more for pickup", or ''.
 *     @type string $detail              "Minimum order for pickup is ₹100.00", or ''.
 * }
 */
function rpress_checkout_ux_minimum_order( $service, $subtotal = null ) {
	$service  = sanitize_key( (string) $service );
	$subtotal = null === $subtotal ? (float) rpress_get_cart_subtotal() : (float) $subtotal;

	$minimum = 0.0;
	$enabled = ! empty( rpress_get_option( 'allow_minimum_order' ) );
	if ( $enabled ) {
		if ( 'delivery' === $service ) {
			$minimum = (float) rpress_get_option( 'minimum_order_price' );
		} elseif ( 'pickup' === $service ) {
			$minimum = (float) rpress_get_option( 'minimum_order_price_pickup' );
		}
	}
	$enabled = $enabled && $minimum > 0;

	// Same strict comparison as rpress_pre_validate_order(); round to cents so
	// float noise never flags a cart that exactly meets the minimum.
	$below     = $enabled && round( $subtotal, 2 ) < round( $minimum, 2 );
	$shortfall = $below ? round( $minimum - $subtotal, 2 ) : 0.0;

	$label = function_exists( 'rpress_service_label' ) ? (string) rpress_service_label( $service ) : $service;
	$label = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label, 'UTF-8' ) : strtolower( $label );

	$state = array(
		'enabled'             => $enabled,
		'service'             => $service,
		'minimum'             => $minimum,
		'subtotal'            => $subtotal,
		'shortfall'           => $shortfall,
		'below'               => $below,
		'minimum_formatted'   => $enabled ? rpress_checkout_ux_format_amount( $minimum ) : '',
		'shortfall_formatted' => $below ? rpress_checkout_ux_format_amount( $shortfall ) : '',
		'message'             => '',
		'detail'              => '',
	);

	if ( $below ) {
		/* translators: 1: amount still needed, 2: service name (delivery/pickup) */
		$state['message'] = sprintf( __( 'Add %1$s more for %2$s', 'restropress' ), $state['shortfall_formatted'], $label );
		/* translators: 1: service name (delivery/pickup), 2: minimum order amount */
		$state['detail'] = sprintf( __( 'Minimum order for %1$s is %2$s', 'restropress' ), $label, $state['minimum_formatted'] );
	}

	/**
	 * Filter the minimum-order hint state shown at checkout and in the cart.
	 *
	 * @since 3.5
	 * @param array  $state    State array (see rpress_checkout_ux_minimum_order()).
	 * @param string $service  Service slug.
	 * @param float  $subtotal Cart subtotal.
	 */
	return apply_filters( 'rpress_checkout_ux_minimum_order', $state, $service, $subtotal );
}

/**
 * Snapshot of the live cart for the checkout UX scripts.
 *
 * @since 3.5
 * @param string $service Optional requested service slug.
 * @return array
 */
function rpress_checkout_ux_state( $service = '' ) {
	$service = rpress_checkout_ux_resolve_service( $service );
	$total   = (float) rpress_get_cart_total();

	return array(
		'service'         => $service,
		'service_label'   => function_exists( 'rpress_service_label' ) ? (string) rpress_service_label( $service ) : $service,
		'quantity'        => (int) rpress_get_cart_quantity(),
		'subtotal'        => (float) rpress_get_cart_subtotal(),
		'total'           => $total,
		'total_formatted' => rpress_checkout_ux_format_amount( $total ),
		'minimum'         => rpress_checkout_ux_minimum_order( $service ),
	);
}

/**
 * Names of the delivery address inputs the checkout marks as required
 * (rp_get_checkout_fields(), visible and is_required), with their messages.
 * The inputs carry no required attribute, so the inline validator needs this
 * list; the messages match rpress_purchase_form_required_fields().
 *
 * @since 3.5
 * @return array<string,string> Field name => error message.
 */
function rpress_checkout_ux_required_address_fields() {
	if ( ! function_exists( 'rp_get_checkout_fields' ) ) {
		return array();
	}
	$messages = array(
		'rpress_street_address' => __( 'Please enter your street address', 'restropress' ),
		'rpress_postcode'       => __( 'Please enter your zip / postal code', 'restropress' ),
		'rpress_city'           => __( 'Please select your town / city', 'restropress' ),
	);
	$out = array();
	foreach ( (array) rp_get_checkout_fields() as $field ) {
		if ( empty( $field['name'] ) || empty( $field['is_required'] ) || ! empty( $field['is_hidden'] ) ) {
			continue;
		}
		$name         = sanitize_key( $field['name'] );
		$out[ $name ] = isset( $messages[ $name ] ) ? $messages[ $name ] : __( 'This field is required.', 'restropress' );
	}
	return $out;
}
