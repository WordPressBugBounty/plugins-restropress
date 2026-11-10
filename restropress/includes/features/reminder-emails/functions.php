<?php
/**
 * Reminder emails: scheduling, due check and sending.
 *
 * @package RestroPress
 * @since   3.5
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Cron hook that sends (or re-schedules) one order's reminder. */
define( 'RPRESS_REMINDER_CRON_HOOK', 'rpress_send_order_reminder' );
/** Order meta set once the reminder went out (value: Unix time it was sent). */
define( 'RPRESS_REMINDER_SENT_META', '_rpress_reminder_sent' );
/** Settings key: rpress_settings['order_reminder']. */
define( 'RPRESS_REMINDER_OPTION', 'order_reminder' );

/**
 * Default copy for the reminder email.
 *
 * @return array subject, heading, content.
 */
function rpress_reminder_default_copy() {
	return array(
		'subject' => '{reminder_message}',
		'heading' => __( 'Your order is coming up', 'restropress' ),
		'content' => __( 'Hi {name},', 'restropress' ) . "\n\n"
			. '{reminder_message}.' . "\n\n"
			. '{fooditem_list}' . "\n\n"
			. __( 'Thank you for ordering from {sitename}.', 'restropress' ),
	);
}

/**
 * Whether the reminder is on before the owner has saved its settings.
 *
 * ON for new installs, OFF for stores that already existed when the feature
 * first loaded, so an update never starts emailing customers unannounced.
 * Decided once and stored, so the default never flips later.
 *
 * @return bool
 */
function rpress_reminder_default_enabled() {
	$stored = get_option( 'rpress_reminder_default', '' );
	if ( 'yes' !== $stored && 'no' !== $stored ) {
		$existing = (bool) get_option( 'rpress_version_upgraded_from' )
			|| (bool) get_posts(
				array(
					'post_type'        => 'rpress_payment',
					'post_status'      => 'any',
					'posts_per_page'   => 1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
				)
			);
		$stored = $existing ? 'no' : 'yes';
		add_option( 'rpress_reminder_default', $stored, '', 'yes' );
	}
	return (bool) apply_filters( 'rpress_reminder_default_enabled', 'yes' === $stored );
}

/**
 * Reminder settings with defaults applied.
 *
 * Until the owner saves the email, rpress_reminder_default_enabled() decides
 * whether it is on.
 *
 * @return array enabled (bool), minutes (int), subject, heading, content.
 */
function rpress_reminder_settings() {
	$saved    = rpress_get_option( RPRESS_REMINDER_OPTION, null );
	$defaults = rpress_reminder_default_copy();
	$settings = array(
		'enabled' => is_array( $saved ) ? ! empty( $saved['enable_notification'] ) : rpress_reminder_default_enabled(),
		'minutes' => isset( $saved['minutes_before'] ) ? rpress_reminder_clamp_minutes( $saved['minutes_before'] ) : 30,
	);
	foreach ( $defaults as $key => $default ) {
		$settings[ $key ] = ( is_array( $saved ) && ! empty( $saved[ $key ] ) ) ? stripslashes( $saved[ $key ] ) : $default;
	}
	return apply_filters( 'rpress_reminder_settings', $settings );
}

/**
 * Keep the lead time between 5 minutes and one day.
 *
 * @param mixed $minutes Raw value.
 * @return int
 */
function rpress_reminder_clamp_minutes( $minutes ) {
	return max( 5, min( 1440, absint( $minutes ) ) );
}

/**
 * Current Unix time. Filterable so tests can move the clock.
 *
 * @return int
 */
function rpress_reminder_now() {
	return (int) apply_filters( 'rpress_reminder_current_time', time() );
}

/**
 * Scheduled service time of an order as a Unix timestamp (site timezone).
 *
 * @param int $payment_id Order ID.
 * @return int|false False for ASAP orders or orders without a usable date/time.
 */
function rpress_reminder_service_timestamp( $payment_id ) {
	$time = trim( (string) get_post_meta( $payment_id, '_rpress_delivery_time', true ) );
	$date = trim( (string) get_post_meta( $payment_id, '_rpress_delivery_date', true ) );
	if ( '' === $time || '' === $date || false !== stripos( $time, 'asap' ) || ! preg_match( '/\d/', $time ) ) {
		return false;
	}
	// A slot stored as a range ("7:30 PM - 8:00 PM") counts from its start.
	if ( preg_match( '/^(.+?)\s+-\s+.+$/', $time, $range ) ) {
		$time = $range[1];
	}
	return rpress_get_wp_timestamp( $date . ' ' . $time );
}

/**
 * Work out what to do with an order's reminder at a given moment.
 *
 * @param int      $payment_id Order ID.
 * @param int|null $now        Unix time, defaults to rpress_reminder_now().
 * @return array {
 *     @type string $state       send | wait | sent | skip | disabled
 *     @type string $reason      Why it was skipped (skip only).
 *     @type int    $reminder_at Unix time the reminder is due (when known).
 *     @type int    $service_at  Unix time of the service (when known).
 * }
 */
function rpress_reminder_check_order( $payment_id, $now = null ) {
	$payment_id = absint( $payment_id );
	$now        = null === $now ? rpress_reminder_now() : (int) $now;
	$result     = array( 'state' => 'skip', 'reason' => '', 'reminder_at' => 0, 'service_at' => 0 );
	$settings   = rpress_reminder_settings();

	if ( ! $payment_id || 'rpress_payment' !== get_post_type( $payment_id ) ) {
		$result['reason'] = 'not_an_order';
		return $result;
	}
	if ( get_post_meta( $payment_id, RPRESS_REMINDER_SENT_META, true ) ) {
		$result['state'] = 'sent';
		return $result;
	}
	if ( empty( $settings['enabled'] ) ) {
		$result['state'] = 'disabled';
		return $result;
	}

	$service_at = rpress_reminder_service_timestamp( $payment_id );
	if ( false === $service_at ) {
		$result['reason'] = 'asap_or_no_time';
		return $result;
	}
	$result['service_at']  = $service_at;
	$result['reminder_at'] = $service_at - ( $settings['minutes'] * MINUTE_IN_SECONDS );

	// Unpaid, failed, refunded or abandoned payments never get a reminder.
	$payment_statuses = apply_filters( 'rpress_reminder_payment_statuses', array( 'publish', 'processing', 'complete' ) );
	if ( ! in_array( get_post_status( $payment_id ), $payment_statuses, true ) ) {
		$result['reason'] = 'payment_status';
		return $result;
	}
	$skip_order_statuses = apply_filters( 'rpress_reminder_skip_order_statuses', array( 'cancelled', 'completed', 'refunded', 'failed' ) );
	if ( in_array( rpress_get_order_status( $payment_id ), $skip_order_statuses, true ) ) {
		$result['reason'] = 'order_status';
		return $result;
	}
	// Placed for a time already inside the reminder window: the receipt covers it.
	$placed_at = (int) get_post_time( 'U', true, $payment_id );
	if ( $placed_at >= $result['reminder_at'] ) {
		$result['reason'] = 'placed_inside_window';
		return $result;
	}
	if ( $now > $service_at ) {
		$result['reason'] = 'time_passed';
		return $result;
	}
	$result['state'] = $now >= $result['reminder_at'] ? 'send' : 'wait';
	return apply_filters( 'rpress_reminder_check_order', $result, $payment_id, $now );
}

/**
 * Schedule (or re-schedule) the single cron event for an order's reminder.
 *
 * @param int $payment_id Order ID.
 * @return void
 */
function rpress_reminder_schedule( $payment_id ) {
	$payment_id = absint( $payment_id );
	if ( ! $payment_id ) {
		return;
	}
	wp_clear_scheduled_hook( RPRESS_REMINDER_CRON_HOOK, array( $payment_id ) );
	$check = rpress_reminder_check_order( $payment_id );
	if ( 'wait' === $check['state'] ) {
		wp_schedule_single_event( $check['reminder_at'], RPRESS_REMINDER_CRON_HOOK, array( $payment_id ) );
	} elseif ( 'send' === $check['state'] ) {
		// Due now (the time was moved): let cron send it instead of the current request.
		wp_schedule_single_event( time(), RPRESS_REMINDER_CRON_HOOK, array( $payment_id ) );
	}
}

/**
 * Cron callback: send when due, re-schedule when the lead time grew shorter.
 *
 * @param int $payment_id Order ID.
 * @return void
 */
function rpress_reminder_cron_callback( $payment_id ) {
	$check = rpress_reminder_check_order( $payment_id );
	if ( 'send' === $check['state'] ) {
		rpress_reminder_send( $payment_id );
	} elseif ( 'wait' === $check['state'] ) {
		wp_schedule_single_event( $check['reminder_at'], RPRESS_REMINDER_CRON_HOOK, array( absint( $payment_id ) ) );
	}
}
add_action( RPRESS_REMINDER_CRON_HOOK, 'rpress_reminder_cron_callback' );

/**
 * Send one order's reminder if it is due. Safe to call repeatedly.
 *
 * @param int      $payment_id Order ID.
 * @param int|null $now        Unix time, defaults to rpress_reminder_now().
 * @return bool True when the email was sent by this call.
 */
function rpress_reminder_send( $payment_id, $now = null ) {
	$payment_id = absint( $payment_id );
	$now        = null === $now ? rpress_reminder_now() : (int) $now;
	$check      = rpress_reminder_check_order( $payment_id, $now );
	if ( 'send' !== $check['state'] ) {
		return false;
	}
	// Claim the order first so cron and the init fallback cannot both send.
	if ( ! add_post_meta( $payment_id, RPRESS_REMINDER_SENT_META, $now, true ) ) {
		return false;
	}
	$sent = rpress_reminder_send_email( $payment_id, $check['service_at'] );
	if ( ! $sent ) {
		// Let a later check retry while the order is still inside its window.
		delete_post_meta( $payment_id, RPRESS_REMINDER_SENT_META );
		return false;
	}
	wp_clear_scheduled_hook( RPRESS_REMINDER_CRON_HOOK, array( $payment_id ) );
	if ( function_exists( 'rpress_insert_payment_note' ) ) {
		rpress_insert_payment_note( $payment_id, __( 'Order reminder email sent to the customer.', 'restropress' ) );
	}
	do_action( 'rpress_reminder_sent', $payment_id );
	return true;
}

/**
 * Send all reminders that are due now. Used by the init fallback and tests.
 *
 * @param int|null $now Unix time, defaults to rpress_reminder_now().
 * @return int[] IDs of the orders reminded.
 */
function rpress_reminder_process_due( $now = null ) {
	$now      = null === $now ? rpress_reminder_now() : (int) $now;
	$settings = rpress_reminder_settings();
	if ( empty( $settings['enabled'] ) ) {
		return array();
	}
	$timezone = rpress_get_wp_timezone();
	// Due orders are today's, plus tomorrow's just after midnight.
	$dates = array_unique(
		array(
			wp_date( 'Y-m-d', $now, $timezone ),
			wp_date( 'Y-m-d', $now + ( $settings['minutes'] * MINUTE_IN_SECONDS ), $timezone ),
		)
	);
	$batch     = max( 1, (int) apply_filters( 'rpress_reminder_batch_size', 100 ) );
	$max_pages = max( 1, (int) apply_filters( 'rpress_reminder_max_pages', 10 ) );
	$sent      = array();
	$skipped   = 0;
	// ASAP orders are excluded in the query, so they can never fill a batch and
	// starve scheduled orders. Unsent orders that are not due yet stay matched,
	// so page past them (capped per run). Sent orders drop out of the match,
	// which is why the offset counts only the ones left behind.
	for ( $page = 1; $page <= $max_pages; $page++ ) {
		$ids = get_posts(
			array(
				'post_type'        => 'rpress_payment',
				'post_status'      => apply_filters( 'rpress_reminder_payment_statuses', array( 'publish', 'processing', 'complete' ) ),
				'posts_per_page'   => $batch,
				'offset'           => $skipped,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => '_rpress_delivery_date',
						'value'   => array_values( $dates ),
						'compare' => 'IN',
					),
					array(
						'key'     => '_rpress_delivery_time',
						'value'   => 'asap',
						'compare' => 'NOT LIKE',
					),
					array(
						'key'     => RPRESS_REMINDER_SENT_META,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		foreach ( $ids as $payment_id ) {
			if ( rpress_reminder_send( $payment_id, $now ) ) {
				$sent[] = (int) $payment_id;
			} else {
				++$skipped;
			}
		}
		if ( count( $ids ) < $batch ) {
			break;
		}
	}
	return $sent;
}

/**
 * Fallback for sites with WP-Cron disabled: check for due reminders on init,
 * at most once every five minutes.
 *
 * @return void
 */
function rpress_reminder_init_fallback() {
	if ( wp_installing() || ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) ) {
		return;
	}
	// Disabled: nothing to send, and no option write on every request.
	$settings = rpress_reminder_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}
	$interval = (int) apply_filters( 'rpress_reminder_fallback_interval', 5 * MINUTE_IN_SECONDS );
	$now      = time();
	$last     = (int) get_option( 'rpress_reminder_last_check', 0 );
	if ( $now - $last < $interval ) {
		return;
	}
	update_option( 'rpress_reminder_last_check', $now, true );
	rpress_reminder_process_due();
}
add_action( 'init', 'rpress_reminder_init_fallback', 99 );

/**
 * Re-schedule an order whenever something that affects its reminder changes.
 * Collected per request and handled on shutdown, once the order is fully saved.
 *
 * @param int|null $payment_id Order ID to queue, null to read the queue.
 * @return int[]
 */
function rpress_reminder_queue( $payment_id = null ) {
	static $queue = array();
	if ( null !== $payment_id ) {
		$queue[ absint( $payment_id ) ] = true;
	}
	return array_keys( $queue );
}

/**
 * Queue an order on status changes and completed payments.
 *
 * @param int $payment_id Order ID.
 * @return void
 */
function rpress_reminder_queue_order( $payment_id ) {
	if ( $payment_id && ! is_object( $payment_id ) ) {
		rpress_reminder_queue( $payment_id );
	}
}
add_action( 'rpress_update_order_status', 'rpress_reminder_queue_order', 20 );
add_action( 'rpress_complete_purchase', 'rpress_reminder_queue_order', 20 );
add_action( 'rpress_update_payment_status', 'rpress_reminder_queue_order', 20 );

/**
 * Queue an order when its service date or time is saved or edited.
 *
 * @param int    $meta_id   Meta ID.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key.
 * @return void
 */
function rpress_reminder_queue_on_meta( $meta_id, $object_id, $meta_key ) {
	if ( in_array( $meta_key, array( '_rpress_delivery_date', '_rpress_delivery_time' ), true ) ) {
		rpress_reminder_queue( $object_id );
	}
}
add_action( 'added_post_meta', 'rpress_reminder_queue_on_meta', 10, 3 );
add_action( 'updated_post_meta', 'rpress_reminder_queue_on_meta', 10, 3 );

/**
 * Schedule every queued order at the end of the request.
 *
 * @return void
 */
function rpress_reminder_flush_queue() {
	foreach ( rpress_reminder_queue() as $payment_id ) {
		if ( 'rpress_payment' === get_post_type( $payment_id ) ) {
			rpress_reminder_schedule( $payment_id );
		}
	}
}
add_action( 'shutdown', 'rpress_reminder_flush_queue' );

/**
 * Drop an order's pending event when the order is deleted.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function rpress_reminder_unschedule_deleted( $post_id ) {
	if ( 'rpress_payment' === get_post_type( $post_id ) ) {
		wp_clear_scheduled_hook( RPRESS_REMINDER_CRON_HOOK, array( absint( $post_id ) ) );
	}
}
add_action( 'before_delete_post', 'rpress_reminder_unschedule_deleted' );

/**
 * The reminder sentence for an order, worded for pickup or delivery.
 *
 * @param int $payment_id Order ID.
 * @param int $service_at Unix time of the service.
 * @return string
 */
function rpress_reminder_message( $payment_id, $service_at ) {
	$number = function_exists( 'rpress_get_payment_number' ) ? rpress_get_payment_number( $payment_id ) : $payment_id;
	$time   = wp_date( get_option( 'time_format' ), $service_at, rpress_get_wp_timezone() );
	if ( 'delivery' === rpress_get_service_type( $payment_id ) ) {
		/* translators: 1: order number, 2: scheduled time */
		$message = sprintf( __( 'Your order #%1$s is scheduled for delivery at %2$s', 'restropress' ), $number, $time );
	} else {
		/* translators: 1: order number, 2: scheduled time */
		$message = sprintf( __( 'Your order #%1$s will be ready at %2$s', 'restropress' ), $number, $time );
	}
	return apply_filters( 'rpress_reminder_message', $message, $payment_id, $service_at );
}

/**
 * Build and send the reminder email with the store's email template.
 *
 * @param int $payment_id Order ID.
 * @param int $service_at Unix time of the service.
 * @return bool
 */
function rpress_reminder_send_email( $payment_id, $service_at ) {
	$payment = rpress_get_payment( $payment_id );
	if ( ! $payment || ! is_email( $payment->email ) ) {
		return false;
	}
	// Send in the language the order was placed in (WPML); restored after send.
	if ( function_exists( 'rpress_wpml_switch_language_for_order' ) ) {
		rpress_wpml_switch_language_for_order( $payment_id );
	}

	$settings     = rpress_reminder_settings();
	$payment_data = $payment->get_meta( '_rpress_payment_meta', true );
	$timezone     = rpress_get_wp_timezone();
	// Feature tags, replaced before the standard {tags}. {service_time} is
	// formatted here because the stored value is the raw slot.
	$tags = array(
		'{reminder_message}' => rpress_reminder_message( $payment_id, $service_at ),
		'{service_time}'     => wp_date( get_option( 'time_format' ), $service_at, $timezone ),
		'{service_date}'     => wp_date( get_option( 'date_format' ), $service_at, $timezone ),
	);
	$apply = function ( $text ) use ( $tags, $payment_id ) {
		return rpress_do_email_tags( strtr( (string) $text, $tags ), $payment_id );
	};

	$subject = wp_specialchars_decode( wp_strip_all_tags( $apply( $settings['subject'] ) ) );
	$heading = $apply( $settings['heading'] );
	$message = $apply( apply_filters( 'rpress_email_template_wpautop', true ) ? wpautop( $settings['content'] ) : $settings['content'] );

	$from_name  = rpress_get_option( 'from_name', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	$from_name  = apply_filters( 'rpress_purchase_from_name', $from_name, $payment_id, $payment_data );
	$from_email = rpress_get_option( 'from_email', get_bloginfo( 'admin_email' ) );
	$from_email = apply_filters( 'rpress_purchase_from_address', $from_email, $payment_id, $payment_data );

	// The mailer is shared: keep the caller's heading and headers and put them back.
	$emails       = RPRESS()->emails;
	$prev_heading = $emails->heading;
	$prev_headers = $emails->headers;

	$emails->__set( 'from_name', $from_name );
	$emails->__set( 'from_email', $from_email );
	$emails->__set( 'heading', $heading );
	$emails->__set( 'headers', '' );
	$emails->__set( 'headers', apply_filters( 'rpress_reminder_headers', $emails->get_headers(), $payment_id ) );

	$sent = $emails->send( $payment->email, $subject, apply_filters( 'rpress_reminder_email_message', $message, $payment_id ), array() );

	$emails->__set( 'heading', $prev_heading );
	$emails->__set( 'headers', $prev_headers );

	return (bool) $sent;
}
