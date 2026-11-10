<?php
/**
 * Paid / unpaid state of an order, shared by Live Orders, the admin order
 * screen, the customer order details and the receipt.
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalise a payment ID, WP_Post or RPRESS_Payment to an RPRESS_Payment.
 *
 * @since 3.5
 * @param mixed $payment Payment ID, WP_Post or RPRESS_Payment.
 * @return RPRESS_Payment|null
 */
function rpress_payments_to_payment_object( $payment ) {
	if ( $payment instanceof RPRESS_Payment ) {
		return $payment;
	}
	if ( $payment instanceof WP_Post ) {
		$payment = $payment->ID;
	}
	if ( is_numeric( $payment ) && (int) $payment > 0 ) {
		return new RPRESS_Payment( absint( $payment ) );
	}
	return null;
}

/**
 * Money state of an order.
 *
 * @since 3.5
 * @param int|WP_Post|RPRESS_Payment $payment Payment ID or object.
 * @return string paid | unpaid | refunded | failed | abandoned
 */
function rpress_get_payment_paid_state( $payment ) {
	$payment = rpress_payments_to_payment_object( $payment );
	$status  = $payment ? sanitize_key( $payment->status ) : '';

	if ( in_array( $status, array( 'publish', 'complete', 'completed' ), true ) ) {
		$state = 'paid';
	} elseif ( in_array( $status, array( 'refunded', 'partially_refunded' ), true ) ) {
		$state = 'refunded';
	} elseif ( in_array( $status, array( 'failed', 'revoked' ), true ) ) {
		$state = 'failed';
	} elseif ( 'abandoned' === $status ) {
		$state = 'abandoned';
	} else {
		$state = 'unpaid';
		// Cash is collected at hand-over: a completed offline order counts as
		// paid (the receipt already treats "delivered" this way).
		if ( $payment && function_exists( 'rpress_is_offline_gateway' ) && rpress_is_offline_gateway( $payment->gateway )
			&& function_exists( 'rpress_get_order_status' ) && 'completed' === rpress_get_order_status( $payment->ID ) ) {
			$state = 'paid';
		}
	}

	return apply_filters( 'rpress_payment_paid_state', $state, $payment );
}

/**
 * Human label for an order's money state.
 *
 * Cash (offline gateways) reads "Not paid yet" until it is collected; an online
 * order that has not been confirmed by the gateway reads "Awaiting payment".
 *
 * @since 3.5
 * @param int|WP_Post|RPRESS_Payment $payment Payment ID or object.
 * @return string
 */
function rpress_get_payment_paid_label( $payment ) {
	$payment = rpress_payments_to_payment_object( $payment );
	$state   = rpress_get_payment_paid_state( $payment );
	$gateway = $payment && ! empty( $payment->gateway ) ? $payment->gateway : '';

	switch ( $state ) {
		case 'paid':
			$label = __( 'Paid', 'restropress' );
			break;
		case 'refunded':
			$label = __( 'Refunded', 'restropress' );
			break;
		case 'failed':
			$label = __( 'Payment failed', 'restropress' );
			break;
		case 'abandoned':
			$label = __( 'Abandoned', 'restropress' );
			break;
		default:
			$offline = '' === $gateway || ( function_exists( 'rpress_is_offline_gateway' ) && rpress_is_offline_gateway( $gateway ) );
			$label   = $offline ? __( 'Not paid yet', 'restropress' ) : __( 'Awaiting payment', 'restropress' );
			break;
	}

	return apply_filters( 'rpress_payment_paid_label', $label, $state, $payment );
}
