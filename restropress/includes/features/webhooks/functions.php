<?php
/**
 * Webhooks: event registry, signing, retry schedule, secrets, the URL rule,
 * stored endpoints, the delivery target type registry and the small pure
 * decisions the triggers use.
 *
 * Nothing in this file talks to the network.
 *
 * Public API for extensions (stable from RPRESS_WEBHOOKS_API_VERSION 1.0):
 * rpress_webhooks_events(), rpress_webhooks_event_resource(),
 * rpress_webhooks_sign(), rpress_webhooks_clean_url(),
 * rpress_webhooks_generate_secret(), rpress_webhooks_iso(),
 * rpress_webhooks_register_target_type(), rpress_webhooks_get_target_types(),
 * rpress_webhooks_retry_delay(), rpress_webhooks_max_attempts().
 *
 * @package RestroPress
 * @since   3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* Events                                                              */
/* ------------------------------------------------------------------ */

/**
 * Events an endpoint or a REST Hook subscription can listen to.
 *
 * @return array event key => label
 */
function rpress_webhooks_events() {
	$events = array(
		'order.created'           => __( 'Order created', 'restropress' ),
		'order.paid'              => __( 'Order paid', 'restropress' ),
		'order.status_changed'    => __( 'Order status changed', 'restropress' ),
		'order.refunded'          => __( 'Order refunded', 'restropress' ),
		'order.cancelled'         => __( 'Order cancelled', 'restropress' ),
		'customer.created'        => __( 'Customer created', 'restropress' ),
		'menu_item.sold_out'      => __( 'Menu item sold out', 'restropress' ),
		'menu_item.back_in_stock' => __( 'Menu item back in stock', 'restropress' ),
	);
	if ( function_exists( 'rpress_reservations_enabled' ) && rpress_reservations_enabled() ) {
		$events['reservation.created'] = __( 'Reservation created', 'restropress' );
	}
	return $events;
}

/**
 * Resource type carried in the data object of an event.
 *
 * @param string $event Event key.
 * @return string order, customer, reservation, menu_item or '' for unknown.
 */
function rpress_webhooks_event_resource( $event ) {
	$prefix = strtok( (string) $event, '.' );
	return in_array( $prefix, array( 'order', 'customer', 'reservation', 'menu_item' ), true ) ? $prefix : '';
}

/**
 * Keep only known event keys, in registry order.
 *
 * @param mixed $events Posted events.
 * @return array
 */
function rpress_webhooks_sanitize_events( $events ) {
	$events = array_map( 'strval', (array) $events );
	return array_values( array_intersect( array_keys( rpress_webhooks_events() ), $events ) );
}

/* ------------------------------------------------------------------ */
/* Signing and retry schedule                                          */
/* ------------------------------------------------------------------ */

/**
 * Signature of a delivery: HMAC SHA-256 of "<timestamp>.<raw body>".
 *
 * @param string $secret    Endpoint or subscription signing secret.
 * @param int    $timestamp Unix seconds of the attempt.
 * @param string $body      Raw request body.
 * @return string Lower-case hex.
 */
function rpress_webhooks_sign( $secret, $timestamp, $body ) {
	return hash_hmac( 'sha256', $timestamp . '.' . $body, (string) $secret );
}

/**
 * How many times a delivery is tried before it is marked failed.
 *
 * @return int
 */
function rpress_webhooks_max_attempts() {
	return 5;
}

/**
 * Seconds to wait after a failed attempt.
 *
 * @param int $attempt Number of the attempt that just failed (1 based).
 * @return int Seconds, 0 when no further attempt follows.
 */
function rpress_webhooks_retry_delay( $attempt ) {
	$delays  = array(
		1 => 60,
		2 => 300,
		3 => 1800,
		4 => 7200,
	);
	$attempt = (int) $attempt;
	return isset( $delays[ $attempt ] ) ? $delays[ $attempt ] : 0;
}

/**
 * Random hex string.
 *
 * @param int $bytes Number of random bytes (the result is twice as long).
 * @return string
 */
function rpress_webhooks_random_hex( $bytes ) {
	return bin2hex( random_bytes( max( 1, (int) $bytes ) ) );
}

/**
 * New signing secret (40 characters).
 *
 * @return string
 */
function rpress_webhooks_generate_secret() {
	return 'whsec_' . rpress_webhooks_random_hex( 17 );
}

/**
 * New envelope id.
 *
 * @return string
 */
function rpress_webhooks_generate_event_id() {
	return 'evt_' . rpress_webhooks_random_hex( 12 );
}

/**
 * Validate a target URL: http or https with a host. The host is not resolved
 * here; private and loopback addresses are refused when the request is sent
 * (WordPress's safe HTTP API, see rpress_webhooks_send()).
 *
 * @param mixed $url Raw URL.
 * @return string Clean URL, or '' when it is not acceptable.
 */
function rpress_webhooks_clean_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || strlen( $url ) > 2000 || preg_match( '/[\s<>"\']/', $url ) ) {
		return '';
	}
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return '';
	}
	if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return '';
	}
	if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return '';
	}
	$clean = esc_url_raw( $url, array( 'http', 'https' ) );
	// Never store a URL other than the one that was entered: if escaping would change it, refuse it.
	return is_string( $clean ) && $clean === $url ? $clean : '';
}

/* ------------------------------------------------------------------ */
/* Stored lists (options, autoload off)                                */
/* ------------------------------------------------------------------ */

/**
 * Read one of the stored lists.
 *
 * @param string $name endpoints, subscriptions or api_keys.
 * @return array id => record
 */
function rpress_webhooks_get_list( $name ) {
	$list = get_option( 'rpress_webhooks_' . $name, array() );
	return is_array( $list ) ? $list : array();
}

/**
 * Save one of the stored lists.
 *
 * @param string $name endpoints, subscriptions or api_keys.
 * @param array  $list id => record.
 * @return void
 */
function rpress_webhooks_save_list( $name, $list ) {
	update_option( 'rpress_webhooks_' . $name, (array) $list, false );
}

/**
 * Admin endpoints.
 *
 * @return array id => array( id, label, url, events, secret, active, created )
 */
function rpress_webhooks_get_endpoints() {
	return rpress_webhooks_get_list( 'endpoints' );
}

/**
 * Create or update an admin endpoint.
 *
 * @param array  $data { label, url, events, active }.
 * @param string $id   Existing endpoint id, or '' to create one.
 * @return array|WP_Error The stored endpoint.
 */
function rpress_webhooks_save_endpoint( $data, $id = '' ) {
	$endpoints = rpress_webhooks_get_endpoints();
	$id        = (string) $id;
	if ( '' !== $id && ! isset( $endpoints[ $id ] ) ) {
		return new WP_Error( 'rpress_webhooks_not_found', __( 'That endpoint no longer exists.', 'restropress' ) );
	}

	$url = rpress_webhooks_clean_url( isset( $data['url'] ) ? $data['url'] : '' );
	if ( '' === $url ) {
		return new WP_Error( 'rpress_webhooks_bad_url', __( 'Enter a valid URL that starts with http:// or https://.', 'restropress' ) );
	}
	$events = rpress_webhooks_sanitize_events( isset( $data['events'] ) ? $data['events'] : array() );
	if ( ! $events ) {
		return new WP_Error( 'rpress_webhooks_no_events', __( 'Choose at least one event.', 'restropress' ) );
	}
	$label = isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '';
	if ( '' === $label ) {
		$label = (string) wp_parse_url( $url, PHP_URL_HOST );
	}

	if ( '' === $id ) {
		$id       = 'ep_' . rpress_webhooks_random_hex( 6 );
		$endpoint = array(
			'id'      => $id,
			'secret'  => rpress_webhooks_generate_secret(),
			'created' => time(),
			'active'  => true,
		);
	} else {
		$endpoint = $endpoints[ $id ];
	}
	$endpoint['label']  = function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 100 ) : substr( $label, 0, 100 );
	$endpoint['url']    = $url;
	$endpoint['events'] = $events;
	if ( isset( $data['active'] ) ) {
		$endpoint['active'] = (bool) $data['active'];
	}

	$endpoints[ $id ] = $endpoint;
	rpress_webhooks_save_list( 'endpoints', $endpoints );
	return $endpoint;
}

/**
 * Delete an admin endpoint.
 *
 * @param string $id Endpoint id.
 * @return bool
 */
function rpress_webhooks_delete_endpoint( $id ) {
	$endpoints = rpress_webhooks_get_endpoints();
	if ( ! isset( $endpoints[ (string) $id ] ) ) {
		return false;
	}
	unset( $endpoints[ (string) $id ] );
	rpress_webhooks_save_list( 'endpoints', $endpoints );
	return true;
}

/**
 * REST Hook subscriptions.
/* ------------------------------------------------------------------ */
/* Delivery target types                                               */
/* ------------------------------------------------------------------ */

/**
 * Targets of core's own endpoints for an event (the "endpoint" type).
 *
 * @param string $event Event key.
 * @return array List of array( id, url, label ).
 */
function rpress_webhooks_endpoint_targets( $event ) {
	$targets = array();
	foreach ( rpress_webhooks_get_endpoints() as $endpoint ) {
		if ( empty( $endpoint['active'] ) || empty( $endpoint['url'] ) || ! in_array( $event, (array) $endpoint['events'], true ) ) {
			continue;
		}
		$targets[] = array(
			'id'    => (string) $endpoint['id'],
			'url'   => (string) $endpoint['url'],
			'label' => (string) $endpoint['label'],
		);
	}
	return $targets;
}

/**
 * Secret and state of one of core's endpoints.
 *
 * @param string $id Endpoint id.
 * @return array|false { secret, active } or false when it was deleted.
 */
function rpress_webhooks_endpoint_resolve( $id ) {
	$endpoints = rpress_webhooks_get_endpoints();
	if ( ! isset( $endpoints[ (string) $id ] ) ) {
		return false;
	}
	return array(
		'secret' => (string) $endpoints[ (string) $id ]['secret'],
		'active' => ! empty( $endpoints[ (string) $id ]['active'] ),
	);
}

/**
 * Let an extension deliver events through core's queue, signature, retries,
 * log and resend (public API).
 *
 * The callbacks are called at dispatch, at the worker and at resend time and
 * never cached across requests, so a target deleted by the extension, or an
 * extension that is deactivated, simply stops receiving deliveries.
 *
 * @param string $type Type key: /^[a-z][a-z0-9_]{1,19}$/, not "endpoint".
 * @param array  $args {
 *     @type string   $label   Shown in the delivery log. Required.
 *     @type callable $targets ( string $event ): array of array( 'id' => string (max 32), 'url' => string, 'label' => string ). Required.
 *     @type callable $resolve ( string $id ): array( 'secret' => string, 'active' => bool ) or false. Required.
 *     @type callable $gone    ( string $id ): void. Optional. When set, an HTTP 410 answer calls it and the delivery is not retried.
 * }
 * @return true|WP_Error
 */
function rpress_webhooks_register_target_type( $type, $args ) {
	$type = is_string( $type ) ? $type : '';
	if ( ! preg_match( '/^[a-z][a-z0-9_]{1,19}$/', $type ) ) {
		return new WP_Error( 'rpress_webhooks_invalid_type', __( 'A webhook target type must be 2 to 20 lower-case letters, digits or underscores, starting with a letter.', 'restropress' ) );
	}
	$registry = isset( $GLOBALS['rpress_webhooks_target_types'] ) && is_array( $GLOBALS['rpress_webhooks_target_types'] ) ? $GLOBALS['rpress_webhooks_target_types'] : array();
	if ( 'endpoint' === $type || isset( $registry[ $type ] ) ) {
		return new WP_Error( 'rpress_webhooks_type_exists', __( 'That webhook target type is already registered.', 'restropress' ) );
	}
	$args = is_array( $args ) ? $args : array();
	if ( empty( $args['label'] ) || ! is_string( $args['label'] ) ) {
		return new WP_Error( 'rpress_webhooks_invalid_type_args', __( 'A webhook target type needs a label.', 'restropress' ) );
	}
	if ( empty( $args['targets'] ) || ! is_callable( $args['targets'] ) || empty( $args['resolve'] ) || ! is_callable( $args['resolve'] ) ) {
		return new WP_Error( 'rpress_webhooks_invalid_type_args', __( 'A webhook target type needs targets and resolve callbacks.', 'restropress' ) );
	}
	if ( isset( $args['gone'] ) && null !== $args['gone'] && ! is_callable( $args['gone'] ) ) {
		return new WP_Error( 'rpress_webhooks_invalid_type_args', __( 'The gone callback of a webhook target type must be callable.', 'restropress' ) );
	}

	$registry[ $type ] = array(
		'label'   => $args['label'],
		'targets' => $args['targets'],
		'resolve' => $args['resolve'],
		'gone'    => isset( $args['gone'] ) && is_callable( $args['gone'] ) ? $args['gone'] : null,
	);
	$GLOBALS['rpress_webhooks_target_types'] = $registry;
	return true;
}

/**
 * Every delivery target type, core's "endpoint" first (public API).
 *
 * @return array type => array( label, targets, resolve, gone )
 */
function rpress_webhooks_get_target_types() {
	$registry = isset( $GLOBALS['rpress_webhooks_target_types'] ) && is_array( $GLOBALS['rpress_webhooks_target_types'] ) ? $GLOBALS['rpress_webhooks_target_types'] : array();
	return array_merge(
		array(
			'endpoint' => array(
				'label'   => __( 'Endpoint', 'restropress' ),
				'targets' => 'rpress_webhooks_endpoint_targets',
				'resolve' => 'rpress_webhooks_endpoint_resolve',
				'gone'    => null,
			),
		),
		$registry
	);
}

/**
 * Secret and state of a target through its type's resolve callback.
 *
 * @param string $type Target type.
 * @param string $id   Target id.
 * @return array|false { secret, active } or false when the target or its type is gone.
 */
function rpress_webhooks_resolve_target( $type, $id ) {
	$types = rpress_webhooks_get_target_types();
	if ( ! isset( $types[ $type ] ) ) {
		return false;
	}
	$target = call_user_func( $types[ $type ]['resolve'], (string) $id );
	if ( ! is_array( $target ) || ! isset( $target['secret'] ) || '' === (string) $target['secret'] ) {
		return false;
	}
	return array(
		'secret' => (string) $target['secret'],
		'active' => ! isset( $target['active'] ) || (bool) $target['active'],
	);
}

/**
 * Everything that should receive an event right now, over all target types.
 *
 * Targets with an id longer than 32 characters or a URL that fails the URL
 * rule are skipped.
 *
 * @param string $event Event key.
 * @return array List of array( type, id, label, url ).
 */
function rpress_webhooks_targets_for( $event ) {
	$targets = array();
	foreach ( rpress_webhooks_get_target_types() as $type => $args ) {
		$list = call_user_func( $args['targets'], (string) $event );
		foreach ( is_array( $list ) ? $list : array() as $target ) {
			if ( ! is_array( $target ) || empty( $target['id'] ) || ! is_scalar( $target['id'] ) || strlen( (string) $target['id'] ) > 32 ) {
				continue;
			}
			$url = isset( $target['url'] ) ? rpress_webhooks_clean_url( $target['url'] ) : '';
			if ( '' === $url ) {
				continue;
			}
			$targets[] = array(
				'type'  => $type,
				'id'    => (string) $target['id'],
				'label' => isset( $target['label'] ) && is_scalar( $target['label'] ) ? substr( (string) $target['label'], 0, 190 ) : '',
				'url'   => $url,
			);
		}
	}
	return $targets;
}

/**
 * User-Agent of every delivery.
 *
 * @return string
 */
function rpress_webhooks_user_agent() {
	return 'RestroPress-Webhooks/' . ( defined( 'RP_VERSION' ) ? RP_VERSION : '' );
}

/* ------------------------------------------------------------------ */
/* Trigger decisions                                                   */
/* ------------------------------------------------------------------ */

/**
 * Whether an order status notification is a real change.
 *
 * Core's rpress_update_order_status action carries no previous status, fires
 * for the initial pending right after an order is inserted, and some core
 * REST paths fire it without a change. The extension therefore keeps its own
 * last seen status per order.
 *
 * @param string $stored Last seen status ('' when none was stored yet).
 * @param string $new    Status reported by the action.
 * @return array { emit: bool, previous: string|null }
 */
function rpress_webhooks_status_change_decision( $stored, $new ) {
	$stored = (string) $stored;
	$new    = (string) $new;
	if ( '' === $new || $stored === $new ) {
		return array(
			'emit'     => false,
			'previous' => '' === $stored ? null : $stored,
		);
	}
	if ( '' === $stored ) {
		// First sighting: pending is the baseline of a new order, anything else is a change we did not see the start of.
		return array(
			'emit'     => 'pending' !== $new,
			'previous' => null,
		);
	}
	return array(
		'emit'     => true,
		'previous' => $stored,
	);
}

/**
 * Event to send for a write of the rp_stock_status meta.
 *
 * An item without the meta counts as available, like in core.
 *
 * @param bool   $is_fooditem Whether the post is a fooditem.
 * @param string $new         New meta value.
 * @param mixed  $old         Previous meta value, null when the meta did not exist.
 * @return string Event key, or '' for no event.
 */
function rpress_webhooks_stock_event( $is_fooditem, $new, $old ) {
	if ( ! $is_fooditem ) {
		return '';
	}
	$was_out = 'unavailable' === $old;
	$is_out  = 'unavailable' === $new;
	if ( $was_out === $is_out ) {
		return '';
	}
	return $is_out ? 'menu_item.sold_out' : 'menu_item.back_in_stock';
}

/**
 * ISO 8601 UTC string for a unix timestamp.
 *
 * @param int $timestamp Unix seconds.
 * @return string|null Null for an empty timestamp.
 */
function rpress_webhooks_iso( $timestamp ) {
	$timestamp = (int) $timestamp;
	return $timestamp > 0 ? gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ) : null;
}

/**
 * ISO 8601 UTC string for a MySQL datetime.
 *
 * @param string $mysql    Datetime (Y-m-d H:i:s).
 * @param bool   $is_local True when the value is in the site timezone, false when it is already UTC.
 * @return string|null Null for an empty or zero date.
 */
function rpress_webhooks_iso_from_mysql( $mysql, $is_local = false ) {
	$mysql = (string) $mysql;
	if ( '' === $mysql || 0 === strpos( $mysql, '0000-00-00' ) ) {
		return null;
	}
	if ( $is_local ) {
		$mysql = get_gmt_from_date( $mysql );
	}
	$timestamp = strtotime( $mysql . ' UTC' );
	return $timestamp ? rpress_webhooks_iso( $timestamp ) : null;
}
