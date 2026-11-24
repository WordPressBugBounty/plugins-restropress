<?php
/**
 * Webhooks: queue, delivery worker, retries, resend, send test and pruning.
 *
 * The request that triggers an event only inserts queue rows and schedules
 * the WP-Cron hook rpress_webhooks_process_queue. All HTTP happens in
 * rpress_webhooks_send(), called by the worker and by the send test button.
 * Rows of every target type (core endpoints and types registered by
 * extensions) share the same queue, signature, retries, log and resend.
 *
 * Delivery is at least once and unordered: receivers dedupe on the envelope id.
 *
 * @package RestroPress
 * @since   3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current UTC time as a MySQL datetime.
 *
 * @param int $offset Seconds to add.
 * @return string
 */
function rpress_webhooks_now( $offset = 0 ) {
	return gmdate( 'Y-m-d H:i:s', time() + (int) $offset );
}

/**
 * Queue an event for every target (endpoint or extension target) that listens to it.
 *
 * Does no HTTP. Returns straight away when nothing listens, so an
 * unconfigured site never gets a queue row or a cron event.
 *
 * @param string $event     Event key from rpress_webhooks_events().
 * @param int    $object_id Order, customer, reservation or fooditem ID.
 * @param array  $extra     Extra event data frozen at trigger time (previous_status, status).
 * @return int Number of rows queued.
 */
function rpress_webhooks_dispatch( $event, $object_id, $extra = array() ) {
	global $wpdb;

	$object_id = absint( $object_id );
	if ( ! $object_id ) {
		return 0;
	}
	if ( ! isset( rpress_webhooks_events()[ $event ] ) ) {
		return 0;
	}
	$targets = rpress_webhooks_targets_for( $event );
	if ( ! $targets ) {
		return 0;
	}
	rpress_webhooks_maybe_install();

	$event_id = rpress_webhooks_generate_event_id();
	$now      = rpress_webhooks_now();
	$queued   = 0;
	foreach ( $targets as $target ) {
		$inserted = $wpdb->insert(
			rpress_webhooks_table(),
			array(
				'event_id'        => $event_id,
				'event'           => $event,
				'object_id'       => $object_id,
				'target_type'     => $target['type'],
				'target_id'       => $target['id'],
				'target_label'    => $target['label'],
				'url'             => $target['url'],
				'extra'           => $extra ? wp_json_encode( $extra ) : null,
				'status'          => 'pending',
				'event_time'      => $now,
				'created_at'      => $now,
				'updated_at'      => $now,
				'next_attempt_at' => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$queued  += $inserted ? 1 : 0;
	}

	if ( $queued ) {
		rpress_webhooks_schedule_worker( time() );
	}
	return $queued;
}

/**
 * Make sure the worker runs at (or before) a given time.
 *
 * @param int $timestamp Unix seconds.
 * @return void
 */
function rpress_webhooks_schedule_worker( $timestamp ) {
	$timestamp = max( time(), (int) $timestamp );
	$next      = wp_next_scheduled( 'rpress_webhooks_process_queue' );
	if ( $next && $next <= $timestamp ) {
		return;
	}
	if ( $next ) {
		wp_unschedule_event( $next, 'rpress_webhooks_process_queue' );
	}
	wp_schedule_single_event( $timestamp, 'rpress_webhooks_process_queue' );
}

/**
 * The target a delivery row points at, through its type's resolve callback.
 *
 * @param object $row Delivery row.
 * @return array|false { secret, active } or false when the target was deleted
 *                     or its type is no longer registered.
 */
function rpress_webhooks_row_target( $row ) {
	return rpress_webhooks_resolve_target( (string) $row->target_type, (string) $row->target_id );
}

/**
 * Body of a delivery, built once and then frozen.
 *
 * Built at the first attempt instead of at trigger time so order meta saved
 * later in the triggering request is included. Rows of the same event share
 * one body.
 *
 * @param object $row Delivery row.
 * @return string|WP_Error Raw JSON.
 */
function rpress_webhooks_row_body( $row ) {
	global $wpdb;

	if ( null !== $row->body && '' !== $row->body ) {
		return (string) $row->body;
	}
	$table = rpress_webhooks_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$body = $wpdb->get_var( $wpdb->prepare( "SELECT body FROM {$table} WHERE event_id = %s AND body IS NOT NULL AND body <> '' ORDER BY id ASC LIMIT 1", $row->event_id ) );
	if ( ! $body ) {
		$extra               = $row->extra ? json_decode( $row->extra, true ) : array();
		$extra               = is_array( $extra ) ? $extra : array();
		$extra['id']         = $row->event_id;
		$extra['created_at'] = strtotime( $row->event_time . ' UTC' );
		$payload             = rpress_webhooks_build_payload( $row->event, (int) $row->object_id, $extra );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		$body = wp_json_encode( $payload );
		if ( ! is_string( $body ) || '' === $body ) {
			return new WP_Error( 'rpress_webhooks_encode', __( 'The payload could not be encoded as JSON.', 'restropress' ) );
		}
	}
	$wpdb->update( $table, array( 'body' => $body ), array( 'id' => $row->id ), array( '%s' ), array( '%d' ) );
	return (string) $body;
}

/**
 * POST one signed delivery. The only place this plugin makes an HTTP request.
 *
 * @param string $url         Target URL.
 * @param string $body        Raw JSON body.
 * @param string $secret      Signing secret.
 * @param string $event       Event key.
 * @param string $event_id    Envelope id.
 * @param int    $delivery_id Delivery row id.
 * @return array { ok: bool, code: int, body: string } body is the response body or the error message.
 */
function rpress_webhooks_send( $url, $body, $secret, $event, $event_id, $delivery_id ) {
	$timestamp = time();
	$response  = wp_safe_remote_post(
		$url,
		array(
			/**
			 * Seconds to wait for a webhook receiver.
			 *
			 * @param int    $timeout Timeout, default 10.
			 * @param string $url     Target URL.
			 */
			'timeout'     => max( 1, (int) apply_filters( 'rpress_webhooks_request_timeout', 10, $url ) ),
			'redirection' => 0,
			'blocking'    => true,
			'user-agent'  => rpress_webhooks_user_agent(),
			'headers'     => array(
				'Content-Type'           => 'application/json',
				'User-Agent'             => rpress_webhooks_user_agent(),
				'X-RP-Webhook-Event'     => $event,
				'X-RP-Webhook-Id'        => $event_id,
				'X-RP-Webhook-Delivery'  => (string) $delivery_id,
				'X-RP-Webhook-Timestamp' => (string) $timestamp,
				'X-RP-Webhook-Signature' => 'sha256=' . rpress_webhooks_sign( $secret, $timestamp, $body ),
			),
			'body'        => $body,
		)
	);

	if ( is_wp_error( $response ) ) {
		$message = $response->get_error_message();
		if ( ! wp_http_validate_url( $url ) ) {
			// WordPress answers "A valid URL was not provided." here, which hides the real reason.
			$message = __( 'WordPress blocked the request: the host does not resolve, or it is a private or local address.', 'restropress' );
		}
		return array(
			'ok'   => false,
			'code' => 0,
			'body' => $message,
		);
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	return array(
		'ok'   => $code >= 200 && $code < 300,
		'code' => $code,
		'body' => (string) wp_remote_retrieve_body( $response ),
	);
}

/**
 * First 1000 characters of a response body, safe to store and show.
 *
 * @param string $body Response body or error message.
 * @return string
 */
function rpress_webhooks_trim_response( $body ) {
	$body = wp_check_invalid_utf8( (string) $body );
	return function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 1000 ) : substr( $body, 0, 1000 );
}

/**
 * Mark a delivery finished or schedule its next attempt.
 *
 * @param int    $id       Row id.
 * @param string $status   success, failed or retrying.
 * @param int    $attempts Attempts made so far.
 * @param int    $code     Last HTTP code (0 for none).
 * @param string $response Response body or error message.
 * @param int    $delay    Seconds until the next attempt (retrying only).
 * @return void
 */
function rpress_webhooks_finish_row( $id, $status, $attempts, $code, $response, $delay = 0 ) {
	global $wpdb;
	$wpdb->update(
		rpress_webhooks_table(),
		array(
			'status'          => $status,
			'attempts'        => (int) $attempts,
			'response_code'   => (int) $code,
			'response_body'   => rpress_webhooks_trim_response( $response ),
			'updated_at'      => rpress_webhooks_now(),
			'next_attempt_at' => rpress_webhooks_now( $delay ),
		),
		array( 'id' => (int) $id ),
		array( '%s', '%d', '%d', '%s', '%s', '%s' ),
		array( '%d' )
	);
}

/**
 * Attempt one claimed delivery row.
 *
 * @param object $row Delivery row, already claimed (status sending).
 * @return string Resulting status.
 */
function rpress_webhooks_attempt_row( $row ) {
	$attempts = (int) $row->attempts;
	$target   = rpress_webhooks_row_target( $row );
	if ( ! $target ) {
		rpress_webhooks_finish_row( $row->id, 'failed', $attempts, 0, __( 'Not sent: the target was deleted or the extension that owns it is inactive.', 'restropress' ) );
		return 'failed';
	}
	if ( ! $target['active'] ) {
		rpress_webhooks_finish_row( $row->id, 'failed', $attempts, 0, __( 'Not sent: the target is paused.', 'restropress' ) );
		return 'failed';
	}
	$body = rpress_webhooks_row_body( $row );
	if ( is_wp_error( $body ) ) {
		rpress_webhooks_finish_row( $row->id, 'failed', $attempts, 0, $body->get_error_message() );
		return 'failed';
	}

	$result = rpress_webhooks_send( $row->url, $body, $target['secret'], $row->event, $row->event_id, (int) $row->id );
	++$attempts;

	if ( $result['ok'] ) {
		rpress_webhooks_finish_row( $row->id, 'success', $attempts, $result['code'], $result['body'] );
		return 'success';
	}
	$types = rpress_webhooks_get_target_types();
	if ( 410 === $result['code'] && ! empty( $types[ $row->target_type ]['gone'] ) ) {
		// REST Hooks: 410 Gone means the receiver dropped the hook. The owning type forgets the target.
		call_user_func( $types[ $row->target_type ]['gone'], (string) $row->target_id );
		rpress_webhooks_finish_row( $row->id, 'failed', $attempts, 410, $result['body'] );
		return 'failed';
	}
	$delay = rpress_webhooks_retry_delay( $attempts );
	if ( $attempts >= rpress_webhooks_max_attempts() || $delay <= 0 ) {
		rpress_webhooks_finish_row( $row->id, 'failed', $attempts, $result['code'], $result['body'] );
		return 'failed';
	}
	rpress_webhooks_finish_row( $row->id, 'retrying', $attempts, $result['code'], $result['body'], $delay );
	return 'retrying';
}

/**
 * Worker: send every due delivery. Hooked to the cron event of the same name.
 *
 * Each row is claimed with a conditional UPDATE before it is sent, so two
 * overlapping workers never send the same row.
 *
 * @return int Number of rows attempted.
 */
function rpress_webhooks_process_queue() {
	global $wpdb;

	$table = rpress_webhooks_table();
	rpress_webhooks_maybe_install();

	// A worker that died mid-send leaves rows in "sending": give them back after 5 minutes.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'retrying', updated_at = %s WHERE status = 'sending' AND updated_at < %s", rpress_webhooks_now(), rpress_webhooks_now( -300 ) ) );

	/**
	 * Rows one worker run may send.
	 *
	 * @param int $batch Default 25.
	 */
	$batch     = max( 1, (int) apply_filters( 'rpress_webhooks_batch_size', 25 ) );
	$deadline  = time() + 40;
	$attempted = 0;

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE status IN ('pending','retrying') AND next_attempt_at <= %s ORDER BY next_attempt_at ASC, id ASC LIMIT %d", rpress_webhooks_now(), $batch ) );
	foreach ( $ids as $id ) {
		if ( time() >= $deadline ) {
			break;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'sending', updated_at = %s WHERE id = %d AND status IN ('pending','retrying')", rpress_webhooks_now(), $id ) );
		if ( 1 !== $claimed ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		if ( ! $row ) {
			continue;
		}
		rpress_webhooks_attempt_row( $row );
		++$attempted;
	}

	// Schedule the next run for whatever is still waiting (retries, or rows beyond this batch).
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$next = $wpdb->get_var( "SELECT MIN(next_attempt_at) FROM {$table} WHERE status IN ('pending','retrying')" );
	if ( $next ) {
		rpress_webhooks_schedule_worker( strtotime( $next . ' UTC' ) );
	}
	return $attempted;
}

/**
 * Send a signed ping to an admin endpoint right now and log it.
 *
 * Synchronous by design (the admin is waiting for the answer) and never
 * retried.
 *
 * @param string $endpoint_id Endpoint id.
 * @return array|WP_Error { success: bool, code: int, message: string, delivery_id: int }
 */
function rpress_webhooks_send_test( $endpoint_id ) {
	global $wpdb;

	$endpoints = rpress_webhooks_get_endpoints();
	if ( ! isset( $endpoints[ (string) $endpoint_id ] ) ) {
		return new WP_Error( 'rpress_webhooks_not_found', __( 'That endpoint no longer exists.', 'restropress' ) );
	}
	$endpoint = $endpoints[ (string) $endpoint_id ];
	rpress_webhooks_maybe_install();
	$event_id = rpress_webhooks_generate_event_id();
	$now      = rpress_webhooks_now();
	$body     = wp_json_encode(
		rpress_webhooks_build_payload(
			'ping',
			0,
			array(
				'id'         => $event_id,
				'created_at' => time(),
			)
		)
	);

	$wpdb->insert(
		rpress_webhooks_table(),
		array(
			'event_id'        => $event_id,
			'event'           => 'ping',
			'object_id'       => 0,
			'target_type'     => 'endpoint',
			'target_id'       => (string) $endpoint['id'],
			'target_label'    => (string) $endpoint['label'],
			'url'             => (string) $endpoint['url'],
			'body'            => $body,
			'status'          => 'sending',
			'event_time'      => $now,
			'created_at'      => $now,
			'updated_at'      => $now,
			'next_attempt_at' => $now,
		),
		array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	$delivery_id = (int) $wpdb->insert_id;

	$result = rpress_webhooks_send( $endpoint['url'], $body, $endpoint['secret'], 'ping', $event_id, $delivery_id );
	if ( $delivery_id ) {
		rpress_webhooks_finish_row( $delivery_id, $result['ok'] ? 'success' : 'failed', 1, $result['code'], $result['body'] );
	}

	if ( $result['ok'] ) {
		/* translators: %d: HTTP status code */
		$message = sprintf( __( 'Test delivered. The endpoint answered with HTTP %d.', 'restropress' ), $result['code'] );
	} elseif ( $result['code'] ) {
		/* translators: %d: HTTP status code */
		$message = sprintf( __( 'Test failed. The endpoint answered with HTTP %d.', 'restropress' ), $result['code'] );
	} else {
		/* translators: %s: connection error message */
		$message = sprintf( __( 'Test failed. Could not connect: %s', 'restropress' ), rpress_webhooks_trim_response( $result['body'] ) );
	}
	return array(
		'success'     => $result['ok'],
		'code'        => $result['code'],
		'message'     => $message,
		'delivery_id' => $delivery_id,
	);
}

/**
 * Queue a copy of a logged delivery: same envelope id, same body.
 *
 * @param int $delivery_id Row to resend.
 * @return int|WP_Error New row id.
 */
function rpress_webhooks_resend( $delivery_id ) {
	global $wpdb;

	$table = rpress_webhooks_table();
	rpress_webhooks_maybe_install();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $delivery_id ) ) );
	if ( ! $row ) {
		return new WP_Error( 'rpress_webhooks_not_found', __( 'That delivery is no longer in the log.', 'restropress' ) );
	}
	if ( ! rpress_webhooks_row_target( $row ) ) {
		return new WP_Error( 'rpress_webhooks_target_gone', __( 'The target of this delivery was deleted or the extension that owns it is inactive.', 'restropress' ) );
	}

	$now = rpress_webhooks_now();
	$wpdb->insert(
		$table,
		array(
			'event_id'        => $row->event_id,
			'event'           => $row->event,
			'object_id'       => (int) $row->object_id,
			'target_type'     => $row->target_type,
			'target_id'       => $row->target_id,
			'target_label'    => $row->target_label,
			'url'             => $row->url,
			'extra'           => $row->extra,
			'body'            => $row->body,
			'status'          => 'pending',
			'event_time'      => $row->event_time,
			'created_at'      => $now,
			'updated_at'      => $now,
			'next_attempt_at' => $now,
		),
		array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	$new_id = (int) $wpdb->insert_id;
	if ( ! $new_id ) {
		return new WP_Error( 'rpress_webhooks_db', __( 'The delivery could not be queued.', 'restropress' ) );
	}
	rpress_webhooks_schedule_worker( time() );
	return $new_id;
}

/**
 * Delete log rows older than the retention period (30 days). Request bodies
 * contain customer personal data, so they are not kept forever.
 *
 * @return int Rows deleted.
 */
function rpress_webhooks_prune_log() {
	global $wpdb;

	if ( ! get_option( 'rpress_webhooks_db_version' ) ) {
		return 0;
	}
	/**
	 * Days a delivery stays in the log.
	 *
	 * @param int $days Default 30.
	 */
	$days  = max( 1, (int) apply_filters( 'rpress_webhooks_log_retention_days', 30 ) );
	$table = rpress_webhooks_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", rpress_webhooks_now( -1 * $days * DAY_IN_SECONDS ) ) );
}
