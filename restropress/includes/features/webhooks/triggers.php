<?php
/**
 * Webhooks: listeners on core hooks that turn store activity into events.
 *
 * Every listener ends in rpress_webhooks_dispatch(), which only writes queue rows.
 * Nothing here makes an HTTP request, so it is safe inside checkout and
 * inside order status updates.
 *
 * @package RestroPress
 * @since   3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * order.created: a new order was stored.
 *
 * @param int $payment_id Order ID.
 * @return void
 */
function rpress_webhooks_on_insert_payment( $payment_id ) {
	rpress_webhooks_dispatch( 'order.created', $payment_id );
}

/**
 * order.paid and order.refunded: the payment status changed.
 *
 * @param int    $payment_id Order ID.
 * @param string $new_status New payment status.
 * @param string $old_status Previous payment status.
 * @return void
 */
function rpress_webhooks_on_payment_status( $payment_id, $new_status, $old_status ) {
	if ( $new_status === $old_status ) {
		return;
	}
	if ( 'publish' === $new_status ) {
		rpress_webhooks_dispatch( 'order.paid', $payment_id );
	} elseif ( 'refunded' === $new_status ) {
		rpress_webhooks_dispatch( 'order.refunded', $payment_id );
	}
}

/**
 * order.status_changed and order.cancelled: the fulfilment status changed.
 *
 * Runs after core's status transaction has committed. The last seen status
 * is kept in post meta because the action does not say what the status was
 * before, and core fires it for the initial pending and sometimes without a
 * change (see rpress_webhooks_status_change_decision()).
 *
 * @param int    $payment_id Order ID.
 * @param string $new_status New order status.
 * @return void
 */
function rpress_webhooks_on_order_status( $payment_id, $new_status ) {
	$payment_id = absint( $payment_id );
	$new_status = sanitize_key( $new_status );
	if ( ! $payment_id || '' === $new_status || 'rpress_payment' !== get_post_type( $payment_id ) ) {
		return;
	}

	$stored   = (string) get_post_meta( $payment_id, '_rpress_webhooks_last_status', true );
	$decision = rpress_webhooks_status_change_decision( $stored, $new_status );
	if ( $stored !== $new_status ) {
		update_post_meta( $payment_id, '_rpress_webhooks_last_status', $new_status );
	}
	if ( ! $decision['emit'] ) {
		return;
	}

	rpress_webhooks_dispatch(
		'order.status_changed',
		$payment_id,
		array(
			'previous_status' => $decision['previous'],
			'status'          => $new_status,
		)
	);
	if ( 'cancelled' === $new_status ) {
		rpress_webhooks_dispatch( 'order.cancelled', $payment_id );
	}
}

/**
 * Remember whether the customer already exists before core "creates" it:
 * the customers table turns a create for a known email into an update.
 *
 * @param array $args Customer data.
 * @return void
 */
function rpress_webhooks_before_customer_create( $args ) {
	$email = isset( $args['email'] ) ? strtolower( (string) $args['email'] ) : '';
	if ( '' === $email || ! function_exists( 'RPRESS' ) || empty( RPRESS()->customers ) ) {
		return;
	}
	$GLOBALS['rpress_webhooks_customer_existed'][ $email ] = (bool) RPRESS()->customers->get_customer_by( 'email', $email );
}

/**
 * customer.created: a customer record was created.
 *
 * @param int|false $created Customer ID, or false when the create failed.
 * @param array     $args    Customer data.
 * @return void
 */
function rpress_webhooks_on_customer_created( $created, $args ) {
	$email   = isset( $args['email'] ) ? strtolower( (string) $args['email'] ) : '';
	$existed = ! empty( $GLOBALS['rpress_webhooks_customer_existed'][ $email ] );
	unset( $GLOBALS['rpress_webhooks_customer_existed'][ $email ] );
	if ( ! $created || $existed ) {
		return;
	}
	rpress_webhooks_dispatch( 'customer.created', $created );
}

/**
 * reservation.created: a table reservation was stored (storefront or admin).
 * The event only exists while reservations are enabled, so dispatch drops it
 * otherwise.
 *
 * @param int $reservation_id Reservation ID.
 * @return void
 */
function rpress_webhooks_on_reservation_created( $reservation_id ) {
	rpress_webhooks_dispatch( 'reservation.created', $reservation_id );
}

/**
 * Remember the availability of a menu item just before it is overwritten.
 *
 * @param int    $meta_id   Meta row ID.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key.
 * @return void
 */
function rpress_webhooks_before_stock_update( $meta_id, $object_id, $meta_key ) {
	if ( 'rp_stock_status' !== $meta_key ) {
		return;
	}
	$GLOBALS['rpress_webhooks_stock_before'][ (int) $object_id ] = (string) get_post_meta( $object_id, 'rp_stock_status', true );
}

/**
 * menu_item.sold_out and menu_item.back_in_stock: rp_stock_status was updated.
 * WordPress only fires this when the value really changed.
 *
 * @param int    $meta_id    Meta row ID.
 * @param int    $object_id  Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value New value.
 * @return void
 */
function rpress_webhooks_on_stock_updated( $meta_id, $object_id, $meta_key, $meta_value ) {
	if ( 'rp_stock_status' !== $meta_key ) {
		return;
	}
	$object_id = (int) $object_id;
	$old       = isset( $GLOBALS['rpress_webhooks_stock_before'][ $object_id ] ) ? $GLOBALS['rpress_webhooks_stock_before'][ $object_id ] : null;
	unset( $GLOBALS['rpress_webhooks_stock_before'][ $object_id ] );
	if ( null === $old ) {
		// The old value is unknown. The value changed, and there are only two states.
		$old = 'unavailable' === $meta_value ? 'available' : 'unavailable';
	}
	$event = rpress_webhooks_stock_event( 'fooditem' === get_post_type( $object_id ), (string) $meta_value, $old );
	if ( '' !== $event ) {
		rpress_webhooks_dispatch( $event, $object_id );
	}
}

/**
 * menu_item.sold_out: rp_stock_status was written for the first time. An item
 * without the meta counts as available, so a first "available" is no event.
 *
 * @param int    $meta_id    Meta row ID.
 * @param int    $object_id  Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Value.
 * @return void
 */
function rpress_webhooks_on_stock_added( $meta_id, $object_id, $meta_key, $meta_value ) {
	if ( 'rp_stock_status' !== $meta_key ) {
		return;
	}
	$event = rpress_webhooks_stock_event( 'fooditem' === get_post_type( $object_id ), (string) $meta_value, null );
	if ( '' !== $event ) {
		rpress_webhooks_dispatch( $event, (int) $object_id );
	}
}
