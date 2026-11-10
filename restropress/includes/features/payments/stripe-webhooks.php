<?php
/**
 * Stripe webhook handling: signature check, stale timestamp rejection,
 * idempotency and order updates.
 *
 * The HTTP listener (rpress_core_stripe_event_listener in the core gateway)
 * reads the raw body and header and hands them to
 * rpress_payments_stripe_handle_webhook(), which never touches the network and
 * returns a status code, so it can be driven from a CLI test.
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option holding processed Stripe event IDs (event ID => unix time).
 */
if ( ! defined( 'RPRESS_STRIPE_EVENTS_OPTION' ) ) {
	define( 'RPRESS_STRIPE_EVENTS_OPTION', 'rpress_stripe_processed_events' );
}

/**
 * Short key identifying this site in Stripe metadata, so a Stripe account that
 * sends webhooks to several sites never updates another site's order.
 *
 * @since 3.5
 * @return string
 */
function rpress_payments_stripe_site_key() {
	return substr( md5( untrailingslashit( home_url() ) ), 0, 12 );
}

/**
 * The webhook endpoint URL to register in the Stripe dashboard.
 *
 * @since 3.5
 * @return string
 */
function rpress_payments_stripe_webhook_url() {
	return home_url( 'index.php?rpress-listener=stripe' );
}

/**
 * Events the endpoint acts on (shown in settings).
 *
 * @since 3.5
 * @return array
 */
function rpress_payments_stripe_webhook_events() {
	return array(
		'checkout.session.completed',
		'checkout.session.async_payment_succeeded',
		'checkout.session.async_payment_failed',
		'checkout.session.expired',
		'payment_intent.succeeded',
		'payment_intent.payment_failed',
		'charge.refunded',
	);
}

/**
 * Verify a Stripe-Signature header.
 *
 * @since 3.5
 * @param string $payload   Raw request body.
 * @param string $header    Stripe-Signature header value.
 * @param string $secret    Webhook signing secret (whsec_...).
 * @param int    $tolerance Allowed clock difference in seconds.
 * @param int    $now       Current unix time (for tests), default time().
 * @return true|WP_Error
 */
function rpress_payments_stripe_verify_signature( $payload, $header, $secret, $tolerance = 300, $now = null ) {
	$now        = null === $now ? time() : (int) $now;
	$timestamp  = '';
	$signatures = array();

	foreach ( explode( ',', (string) $header ) as $part ) {
		$pair = explode( '=', trim( $part ), 2 );
		if ( 2 !== count( $pair ) ) {
			continue;
		}
		if ( 't' === $pair[0] ) {
			$timestamp = $pair[1];
		} elseif ( 'v1' === $pair[0] ) {
			$signatures[] = $pair[1];
		}
	}

	if ( '' === $timestamp || ! ctype_digit( $timestamp ) || empty( $signatures ) ) {
		return new WP_Error( 'missing_signature', 'Missing or malformed Stripe-Signature header' );
	}

	$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
	$matched  = false;
	foreach ( $signatures as $signature ) {
		if ( hash_equals( $expected, $signature ) ) {
			$matched = true;
			break;
		}
	}
	if ( ! $matched ) {
		return new WP_Error( 'bad_signature', 'Invalid signature' );
	}

	// Checked after the HMAC so the timestamp itself is authenticated.
	if ( $tolerance > 0 && abs( $now - (int) $timestamp ) > $tolerance ) {
		return new WP_Error( 'stale_timestamp', 'Timestamp outside the tolerance zone' );
	}

	return true;
}

/**
 * Whether an event ID was already processed.
 *
 * @since 3.5
 * @param string $event_id Stripe event ID.
 * @return bool
 */
function rpress_payments_stripe_event_processed( $event_id ) {
	$events = get_option( RPRESS_STRIPE_EVENTS_OPTION, array() );
	return is_array( $events ) && isset( $events[ $event_id ] );
}

/**
 * Remember a processed event ID. Keeps the last 500 events and drops entries
 * older than 7 days (Stripe stops retrying after 3 days).
 *
 * @since 3.5
 * @param string $event_id Stripe event ID.
 * @return void
 */
function rpress_payments_stripe_remember_event( $event_id ) {
	$events = get_option( RPRESS_STRIPE_EVENTS_OPTION, array() );
	if ( ! is_array( $events ) ) {
		$events = array();
	}
	$cutoff = time() - 7 * DAY_IN_SECONDS;
	foreach ( $events as $id => $seen ) {
		if ( (int) $seen < $cutoff ) {
			unset( $events[ $id ] );
		}
	}
	$events[ $event_id ] = time();
	if ( count( $events ) > 500 ) {
		asort( $events );
		$events = array_slice( $events, -500, null, true );
	}
	update_option( RPRESS_STRIPE_EVENTS_OPTION, $events, false );
}

/**
 * Handle one webhook delivery.
 *
 * @since 3.5
 * @param string      $payload          Raw request body.
 * @param string      $signature_header Stripe-Signature header value.
 * @param string|null $secret           Signing secret; defaults to the saved setting.
 * @return array { code: int, message: string, action: string }
 */
function rpress_payments_stripe_handle_webhook( $payload, $signature_header, $secret = null ) {
	if ( null === $secret ) {
		$secret = function_exists( 'rpress_core_stripe_get_webhook_secret' ) ? rpress_core_stripe_get_webhook_secret() : '';
	}
	$secret = trim( (string) $secret );

	if ( '' === $secret ) {
		return array( 'code' => 400, 'message' => 'Webhook signing secret is not configured', 'action' => 'rejected' );
	}

	$tolerance = (int) apply_filters( 'rpress_core_stripe_webhook_tolerance', 300 );
	$verified  = rpress_payments_stripe_verify_signature( $payload, $signature_header, $secret, $tolerance );
	if ( is_wp_error( $verified ) ) {
		return array( 'code' => 400, 'message' => $verified->get_error_message(), 'action' => $verified->get_error_code() );
	}

	$event = json_decode( $payload, true );
	if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
		return array( 'code' => 400, 'message' => 'Invalid payload', 'action' => 'invalid_payload' );
	}

	$event_id = sanitize_text_field( $event['id'] );
	if ( rpress_payments_stripe_event_processed( $event_id ) ) {
		return array( 'code' => 200, 'message' => 'Duplicate event ignored', 'action' => 'duplicate' );
	}

	$action = rpress_payments_stripe_dispatch_event( $event );

	rpress_payments_stripe_remember_event( $event_id );

	return array( 'code' => 200, 'message' => 'OK', 'action' => $action );
}

/**
 * Route a verified event to its handler.
 *
 * @since 3.5
 * @param array $event Decoded Stripe event.
 * @return string What happened (for logs and tests).
 */
function rpress_payments_stripe_dispatch_event( $event ) {
	$type   = sanitize_text_field( $event['type'] );
	$object = isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ? $event['data']['object'] : array();
	$live   = ! empty( $event['livemode'] );

	switch ( $type ) {
		case 'checkout.session.completed':
		case 'checkout.session.async_payment_succeeded':
			if ( ! empty( $object['id'] ) && function_exists( 'rpress_core_stripe_get_payment_id_by_checkout_session' ) ) {
				$payment_id = rpress_core_stripe_get_payment_id_by_checkout_session( $object['id'] );
				if ( $payment_id ) {
					rpress_core_stripe_update_payment_from_session( $object['id'], $payment_id, $object );
					return 'session_completed';
				}
			}
			return 'ignored';

		case 'checkout.session.async_payment_failed':
			if ( ! empty( $object['id'] ) && function_exists( 'rpress_core_stripe_get_payment_id_by_checkout_session' ) ) {
				$payment_id = rpress_core_stripe_get_payment_id_by_checkout_session( $object['id'] );
				if ( $payment_id ) {
					rpress_update_payment_status( $payment_id, 'failed' );
					rpress_insert_payment_note( $payment_id, __( 'Stripe checkout payment failed.', 'restropress' ) );
					return 'session_failed';
				}
			}
			return 'ignored';

		case 'checkout.session.expired':
			if ( ! empty( $object['id'] ) && function_exists( 'rpress_core_stripe_get_payment_id_by_checkout_session' ) ) {
				$payment_id = rpress_core_stripe_get_payment_id_by_checkout_session( $object['id'] );
				if ( $payment_id ) {
					rpress_insert_payment_note( $payment_id, __( 'Stripe checkout session expired.', 'restropress' ) );
					delete_post_meta( $payment_id, '_stripe_checkout_sessions_key' );
					return 'session_expired';
				}
			}
			return 'ignored';

		case 'payment_intent.succeeded':
			return rpress_payments_stripe_on_intent_succeeded( $object, $live );

		case 'payment_intent.payment_failed':
			return rpress_payments_stripe_on_intent_failed( $object, $live );

		case 'charge.refunded':
			return rpress_payments_stripe_on_charge_refunded( $object, $live );
	}

	return (string) apply_filters( 'rpress_payments_stripe_webhook_unhandled', 'ignored', $event );
}

/**
 * Find the RestroPress payment a Stripe object belongs to.
 *
 * Looks at metadata.rpress_payment_id first, then the stored transaction ID
 * (the PaymentIntent ID). Objects tagged for another site, payments that are
 * not Stripe payments and test/live mismatches are ignored.
 *
 * @since 3.5
 * @param array  $object    Stripe PaymentIntent or Charge.
 * @param string $intent_id PaymentIntent ID.
 * @param bool   $live      Event livemode.
 * @return RPRESS_Payment|null
 */
function rpress_payments_stripe_find_payment( $object, $intent_id, $live ) {
	$metadata = isset( $object['metadata'] ) && is_array( $object['metadata'] ) ? $object['metadata'] : array();

	if ( ! empty( $metadata['rpress_site'] ) && rpress_payments_stripe_site_key() !== $metadata['rpress_site'] ) {
		return null;
	}

	$payment_id = ! empty( $metadata['rpress_payment_id'] ) ? absint( $metadata['rpress_payment_id'] ) : 0;

	if ( ! $payment_id && '' !== $intent_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$payment_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_rpress_payment_transaction_id' AND meta_value = %s LIMIT 1", $intent_id ) ) );
	}

	if ( ! $payment_id ) {
		return null;
	}

	$payment = new RPRESS_Payment( $payment_id );
	if ( empty( $payment->ID ) || 'stripe' !== $payment->gateway ) {
		return null;
	}

	// A stored PaymentIntent that differs means the metadata points elsewhere.
	$stored = (string) get_post_meta( $payment->ID, '_rpress_payment_transaction_id', true );
	if ( '' !== $stored && '' !== $intent_id && 0 === strpos( $stored, 'pi_' ) && $stored !== $intent_id ) {
		return null;
	}

	if ( ! empty( $payment->mode ) && ( $live ? 'live' : 'test' ) !== $payment->mode ) {
		return null;
	}

	return $payment;
}

/**
 * payment_intent.succeeded: mark the order paid.
 *
 * @since 3.5
 * @param array $intent PaymentIntent.
 * @param bool  $live   Event livemode.
 * @return string
 */
function rpress_payments_stripe_on_intent_succeeded( $intent, $live ) {
	$intent_id = ! empty( $intent['id'] ) ? sanitize_text_field( $intent['id'] ) : '';
	$payment   = rpress_payments_stripe_find_payment( $intent, $intent_id, $live );
	if ( ! $payment ) {
		return 'no_order';
	}

	return rpress_payments_stripe_mark_paid( $payment->ID, $intent, __( 'Stripe webhook', 'restropress' ) );
}

/**
 * Mark a payment paid from a succeeded PaymentIntent (webhook, wallet
 * confirmation or return URL). Safe to call more than once.
 *
 * @since 3.5
 * @param int    $payment_id Payment ID.
 * @param array  $intent     PaymentIntent.
 * @param string $source     Who confirmed it (for the order note).
 * @return string
 */
function rpress_payments_stripe_mark_paid( $payment_id, $intent, $source ) {
	$payment = new RPRESS_Payment( absint( $payment_id ) );
	if ( empty( $payment->ID ) ) {
		return 'no_order';
	}
	if ( in_array( $payment->status, array( 'publish', 'complete', 'refunded' ), true ) ) {
		return 'already_' . $payment->status;
	}
	if ( empty( $intent['status'] ) || 'succeeded' !== $intent['status'] ) {
		return 'not_succeeded';
	}

	$intent_id = ! empty( $intent['id'] ) ? sanitize_text_field( $intent['id'] ) : '';
	$received  = isset( $intent['amount_received'] ) ? (int) $intent['amount_received'] : ( isset( $intent['amount'] ) ? (int) $intent['amount'] : 0 );
	$expected  = rpress_core_stripe_get_amount( $payment->total );
	$currency  = ! empty( $intent['currency'] ) ? strtoupper( $intent['currency'] ) : '';
	$order_cur = strtoupper( (string) ( $payment->currency ? $payment->currency : rpress_get_currency() ) );

	if ( $received < $expected || ( '' !== $currency && $currency !== $order_cur ) ) {
		// Money was taken, so do not fail the order; flag it for a person instead.
		rpress_insert_payment_note(
			$payment->ID,
			sprintf(
				/* translators: 1: PaymentIntent ID, 2: amount received in minor units, 3: currency */
				__( 'Stripe PaymentIntent %1$s succeeded for %2$d (%3$s), which does not match the order total. Please review before accepting.', 'restropress' ),
				$intent_id,
				$received,
				$currency
			)
		);
		return 'amount_mismatch';
	}

	if ( '' !== $intent_id ) {
		rpress_set_payment_transaction_id( $payment->ID, $intent_id );
	}
	rpress_update_payment_status( $payment->ID, 'publish' );
	rpress_insert_payment_note(
		$payment->ID,
		sprintf(
			/* translators: 1: PaymentIntent ID, 2: source such as "Stripe webhook" */
			__( 'Stripe payment %1$s confirmed (%2$s).', 'restropress' ),
			$intent_id,
			$source
		)
	);
	delete_post_meta( $payment->ID, '_stripe_checkout_sessions_key' );

	return 'paid';
}

/**
 * payment_intent.payment_failed: mark the order failed unless it is paid.
 *
 * @since 3.5
 * @param array $intent PaymentIntent.
 * @param bool  $live   Event livemode.
 * @return string
 */
function rpress_payments_stripe_on_intent_failed( $intent, $live ) {
	$intent_id = ! empty( $intent['id'] ) ? sanitize_text_field( $intent['id'] ) : '';
	$payment   = rpress_payments_stripe_find_payment( $intent, $intent_id, $live );
	if ( ! $payment ) {
		return 'no_order';
	}
	if ( in_array( $payment->status, array( 'publish', 'complete', 'refunded', 'failed' ), true ) ) {
		return 'already_' . $payment->status;
	}

	$reason = ! empty( $intent['last_payment_error']['message'] ) ? sanitize_text_field( $intent['last_payment_error']['message'] ) : __( 'No reason given.', 'restropress' );

	rpress_update_payment_status( $payment->ID, 'failed' );
	rpress_insert_payment_note(
		$payment->ID,
		sprintf(
			/* translators: 1: PaymentIntent ID, 2: decline reason */
			__( 'Stripe payment %1$s failed: %2$s', 'restropress' ),
			$intent_id,
			$reason
		)
	);

	return 'failed';
}

/**
 * charge.refunded: full refunds mark the order refunded (which cancels the
 * order through rpress_sync_order_status_on_payment_change); partial refunds
 * add a note.
 *
 * @since 3.5
 * @param array $charge Charge.
 * @param bool  $live   Event livemode.
 * @return string
 */
function rpress_payments_stripe_on_charge_refunded( $charge, $live ) {
	$intent_id = ! empty( $charge['payment_intent'] ) && is_string( $charge['payment_intent'] ) ? sanitize_text_field( $charge['payment_intent'] ) : '';
	$payment   = rpress_payments_stripe_find_payment( $charge, $intent_id, $live );
	if ( ! $payment ) {
		return 'no_order';
	}
	if ( 'refunded' === $payment->status ) {
		return 'already_refunded';
	}

	$amount   = isset( $charge['amount'] ) ? (int) $charge['amount'] : 0;
	$refunded = isset( $charge['amount_refunded'] ) ? (int) $charge['amount_refunded'] : 0;
	$full     = ! empty( $charge['refunded'] ) || ( $amount > 0 && $refunded >= $amount );
	$display  = rpress_currency_filter( rpress_format_amount( rpress_core_stripe_amount_from_minor_units( $refunded ) ), $payment->currency );

	update_post_meta( $payment->ID, '_rpress_stripe_amount_refunded', $refunded );

	if ( ! $full ) {
		rpress_insert_payment_note(
			$payment->ID,
			sprintf(
				/* translators: %s: refunded amount */
				__( 'Stripe partial refund: %s refunded so far.', 'restropress' ),
				$display
			)
		);
		return 'partial_refund';
	}

	rpress_update_payment_status( $payment->ID, 'refunded' );
	rpress_insert_payment_note(
		$payment->ID,
		sprintf(
			/* translators: %s: refunded amount */
			__( 'Stripe refund: %s refunded in full.', 'restropress' ),
			$display
		)
	);

	return 'refunded';
}
