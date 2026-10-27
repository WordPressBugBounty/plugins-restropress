<?php
/**
 * Basic flat delivery fee (free tier)
 *
 * A single flat fee applied to delivery orders, configured under
 * RestroPress > Settings > General > Delivery Fee. Defers entirely to the
 * RestroPress Delivery Fee extension (zone / distance pricing) when that
 * extension is active: the extension owns the 'delivery_fee' cart fee id
 * and its own settings section, so core adds nothing.
 *
 * @package     RPRESS
 * @subpackage  Functions/DeliveryFee
 * @since       3.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Whether the core flat delivery fee is in charge.
 *
 * The Delivery Fee extension replaces this feature completely when active.
 *
 * @since 3.4
 * @return bool
 */
function rpress_flat_delivery_fee_active() {
	return apply_filters( 'rpress_flat_delivery_fee_active', ! class_exists( 'RP_Delivery_Fee' ) );
}

/**
 * The configured flat delivery fee amount.
 *
 * @since 3.4
 * @return float
 */
function rpress_get_flat_delivery_fee() {
	return (float) apply_filters( 'rpress_flat_delivery_fee_amount', rpress_get_option( 'flat_delivery_fee', 0 ) );
}

/**
 * The fee to advertise on a checkout service chip (Delivery / Pickup).
 *
 * When the given service is the one currently selected, its fees are the ones
 * on the cart, so return the applied fee total — this reflects whatever fee
 * extension is in play (delivery zones, pickup/extra fees, etc.) without the
 * core needing to know each fee id. When the service is NOT the active one its
 * fees aren't on the cart, so fall back to a best-effort value: the configured
 * flat delivery fee for delivery, or free for pickup.
 *
 * @since 3.4
 * @param string $service_type 'delivery' or 'pickup'.
 * @return float
 */
function rpress_get_checkout_service_fee_display( $service_type ) {
	$current = isset( $_COOKIE['service_type'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['service_type'] ) ) : '';

	/*
	 * Let a fee extension report its fee for THIS service, even when it isn't
	 * the currently-selected one, so both chips can show the backend-computed
	 * value (e.g. the Delivery Fee extension's zone/distance amount for delivery
	 * while pickup is selected). Return null to defer.
	 */
	$fee = apply_filters( 'rpress_checkout_service_fee', null, $service_type, $current );
	if ( null !== $fee ) {
		return max( 0, (float) $fee );
	}

	// No fee extension answered. Core itself only charges a flat delivery fee;
	// read the configured amount (don't sum all cart fees — that would count
	// tips and other unrelated fees).
	if ( 'delivery' === $service_type ) {
		return (float) rpress_get_flat_delivery_fee();
	}

	return 0.0;
}

/**
 * The fee sublabel to display on a checkout service chip (e.g. '$5.00 fee', 'Free', or '').
 *
 * If any fees related extensions are in use and the fees value is not 0,
 * "Free" should not appear below the service type label.
 *
 * @since 3.4.11
 * @param string $service_type 'delivery' or 'pickup'.
 * @return string
 */
function rpress_get_checkout_service_fee_sublabel( $service_type ) {
	$current = isset( $_COOKIE['service_type'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['service_type'] ) ) : '';

	// 1. Allow extensions to filter the sublabel directly.
	$filtered = apply_filters( 'rpress_checkout_service_fee_sublabel', null, $service_type, $current );
	if ( null !== $filtered ) {
		return (string) $filtered;
	}

	// 2. Check calculated fee amount.
	$amount = function_exists( 'rpress_get_checkout_service_fee_display' )
		? (float) rpress_get_checkout_service_fee_display( $service_type )
		: 0.0;

	if ( $amount > 0 ) {
		return sprintf( __( '%s fee', 'restropress' ), html_entity_decode( rpress_currency_filter( rpress_format_amount( $amount ) ), ENT_COMPAT, 'UTF-8' ) );
	}

	// 3. Check if any fee-related extension is active on the site with configured fees != 0.
	$has_fee_extension      = false;
	$fee_extension_non_zero = false;

	// Check Delivery Fee extension
	if ( class_exists( 'RP_Delivery_Fee' ) || function_exists( 'rpress_delivery_fee' ) ) {
		$has_fee_extension = true;
		$delivery_settings = get_option( 'rp_delivery_fee', array() );

		if ( ! empty( $delivery_settings['delivery_fee'] ) && is_array( $delivery_settings['delivery_fee'] ) ) {
			foreach ( $delivery_settings['delivery_fee'] as $tier ) {
				if ( ! empty( $tier['fee_amount'] ) && (float) $tier['fee_amount'] > 0 ) {
					$fee_extension_non_zero = true;
					break;
				}
			}
		}
		if ( ! $fee_extension_non_zero && ! empty( $delivery_settings['delivery_zones'] ) && is_array( $delivery_settings['delivery_zones'] ) ) {
			foreach ( $delivery_settings['delivery_zones'] as $zone ) {
				if ( ! empty( $zone['fee'] ) && (float) $zone['fee'] > 0 ) {
					$fee_extension_non_zero = true;
					break;
				}
			}
		}
	}

	// Check Extra Fees extension
	if ( class_exists( 'Rpress_Extra_Fees' ) || function_exists( 'rp_get_extra_fee_settings' ) || (int) rpress_get_option( 'rpress_extra_fees_active' ) === 1 ) {
		$has_fee_extension = true;
		if ( (int) rpress_get_option( 'rpress_extra_fees_active' ) === 1 ) {
			$fee_extension_non_zero = true;
		}
	}

	// General filters for custom extensions
	$has_fee_extension      = apply_filters( 'rpress_has_fee_extension', $has_fee_extension, $service_type );
	$fee_extension_non_zero = apply_filters( 'rpress_fee_extension_non_zero', $fee_extension_non_zero, $service_type );

	// 4. Handle Delivery service type
	if ( 'delivery' === $service_type ) {
		if ( class_exists( 'RP_Delivery_Fee' ) || function_exists( 'rpress_delivery_fee' ) ) {
			$delivery_settings    = get_option( 'rp_delivery_fee', array() );
			$free_delivery_amount = ! empty( $delivery_settings['free_delivery_amount'] ) ? (float) $delivery_settings['free_delivery_amount'] : 0.0;
			$cart_subtotal        = function_exists( 'rpress_get_cart_subtotal' ) ? (float) rpress_get_cart_subtotal() : 0.0;

			// If free delivery threshold is qualified, it is free.
			if ( $free_delivery_amount > 0 && $cart_subtotal >= $free_delivery_amount ) {
				return __( 'Free', 'restropress' );
			}
		}

		// If fee extension is in use and fees value is not 0, "Free" should NOT appear!
		if ( $has_fee_extension && $fee_extension_non_zero ) {
			return '';
		}

		// Core flat fee check
		$flat = (float) rpress_get_flat_delivery_fee();
		if ( $flat > 0 ) {
			return sprintf( __( '%s fee', 'restropress' ), html_entity_decode( rpress_currency_filter( rpress_format_amount( $flat ) ), ENT_COMPAT, 'UTF-8' ) );
		}

		// If no extension and flat fee is 0, delivery is free
		return __( 'Free', 'restropress' );
	}

	// 5. Handle Pickup service type
	if ( 'pickup' === $service_type ) {
		// Allow developer override for pickup
		$allow_pickup_free = apply_filters( 'rpress_pickup_show_free_label', null );
		if ( null !== $allow_pickup_free ) {
			return $allow_pickup_free ? __( 'Free', 'restropress' ) : '';
		}

		// If fee-related extensions are in use and fees value is not 0, conditionally remove "Free"
		if ( $has_fee_extension && $fee_extension_non_zero ) {
			return '';
		}

		$pickup_fee_managed = apply_filters( 'rpress_pickup_fee_managed', false );
		if ( $pickup_fee_managed ) {
			return '';
		}
		return __( 'Free', 'restropress' );
	}

	return '';
}

/**
 * Back-compat shim for the delivery-only helper introduced earlier in 3.4.
 *
 * @since 3.4
 * @deprecated Use rpress_get_checkout_service_fee_display( 'delivery' ).
 * @return float
 */
function rpress_get_checkout_delivery_fee_display() {
	return rpress_get_checkout_service_fee_display( 'delivery' );
}

/**
 * Add or remove the flat fee to match the selected service type.
 *
 * Uses the same 'delivery_fee' fee id as the Delivery Fee extension; the two
 * never run together because of rpress_flat_delivery_fee_active().
 *
 * @since 3.4
 * @return void
 */
function rpress_apply_flat_delivery_fee() {
	if ( ! rpress_flat_delivery_fee_active() ) {
		return;
	}
	$service_type = isset( $_COOKIE['service_type'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['service_type'] ) ) : '';
	$fee          = rpress_get_flat_delivery_fee();

	RPRESS()->fees->remove_fee( 'delivery_fee' );
	if ( 'delivery' === $service_type && $fee > 0 && rpress_get_cart_quantity() > 0 ) {
		RPRESS()->fees->add_fee( $fee, __( 'Delivery fee', 'restropress' ), 'delivery_fee', 'all', false );
	}
}
add_action( 'rpress_checkout_service_options', 'rpress_apply_flat_delivery_fee', 9 );
// The order summary renders before the service-options hook on the checkout
// page, so sync the fee before the cart prints as well.
add_action( 'rpress_before_checkout_cart', 'rpress_apply_flat_delivery_fee', 5 );
add_action( 'rpress_checkout_service_option_updated', 'rpress_apply_flat_delivery_fee', 9 );
add_action( 'rpress_post_remove_from_cart', 'rpress_apply_flat_delivery_fee', 9 );
add_action( 'rpress_post_add_to_cart', 'rpress_apply_flat_delivery_fee', 9 );

/**
 * Re-evaluate the fee when the customer confirms a service choice in the
 * date/time popup (the rpress_check_service_slot AJAX round-trip). The
 * request carries the just-chosen service type before the cookie updates,
 * so read it from the payload.
 *
 * @since 3.4
 * @param array|WP_Error $data Sanitized request payload.
 * @return array|WP_Error Unchanged payload.
 */
function rpress_flat_delivery_fee_check_service_slot( $data ) {
	if ( ! rpress_flat_delivery_fee_active() || is_wp_error( $data ) ) {
		return $data;
	}
	$service_type = is_array( $data ) && isset( $data['serviceType'] ) ? sanitize_key( $data['serviceType'] ) : '';
	$fee          = rpress_get_flat_delivery_fee();

	RPRESS()->fees->remove_fee( 'delivery_fee' );
	if ( 'delivery' === $service_type && $fee > 0 && rpress_get_cart_quantity() > 0 ) {
		RPRESS()->fees->add_fee( $fee, __( 'Delivery fee', 'restropress' ), 'delivery_fee', 'all', false );
	}
	return $data;
}
add_filter( 'rpress_check_service_slot', 'rpress_flat_delivery_fee_check_service_slot', 9 );

/**
 * Re-evaluate the fee the moment the storefront service toggle switches,
 * using the type from the request (the cookie updates client-side later).
 *
 * @since 3.4
 * @param string $service_type Newly selected service type.
 * @return void
 */
function rpress_flat_delivery_fee_on_service_switch( $service_type ) {
	if ( ! rpress_flat_delivery_fee_active() ) {
		return;
	}
	$fee = rpress_get_flat_delivery_fee();

	RPRESS()->fees->remove_fee( 'delivery_fee' );
	if ( 'delivery' === sanitize_key( $service_type ) && $fee > 0 && rpress_get_cart_quantity() > 0 ) {
		RPRESS()->fees->add_fee( $fee, __( 'Delivery fee', 'restropress' ), 'delivery_fee', 'all', false );
	}
}
add_action( 'rpress_service_type_switched', 'rpress_flat_delivery_fee_on_service_switch', 9 );

/**
 * Keep AJAX cart responses in sync after the fee is applied.
 *
 * @since 3.4
 * @param array $data Cart response payload.
 * @return array
 */
function rpress_flat_delivery_fee_cart_data( $data ) {
	if ( ! rpress_flat_delivery_fee_active() ) {
		return $data;
	}
	rpress_apply_flat_delivery_fee();
	$data['total'] = html_entity_decode( rpress_currency_filter( rpress_format_amount( rpress_get_cart_total() ) ), ENT_COMPAT, 'UTF-8' );
	return $data;
}
add_filter( 'rpress_cart_data', 'rpress_flat_delivery_fee_cart_data', 9 );
