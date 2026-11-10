<?php
/**
 * Slot engine bridge: the legacy core functions delegate here.
 *
 * rpress_is_store_open() and rp_get_store_timings() keep their signatures,
 * return formats and filters; when the engine is enabled they call these
 * functions instead of their own legacy bodies.
 *
 * @package     RPRESS
 * @subpackage  Features/SlotEngine
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Whether the core open/slot functions route through RPRESS_Service_Hours.
 *
 * On by default for the core basic hours. When the Store Timing extension
 * owns the schedule (rpress_basic_store_hours_active() is false) the legacy
 * code path runs, because that extension filters the legacy output shapes.
 * Return false from the filter to switch the engine off.
 *
 * @since 3.5
 * @return bool
 */
function rpress_service_hours_engine_enabled() {
	$default = function_exists( 'rpress_basic_store_hours_active' ) ? rpress_basic_store_hours_active() : true;
	return (bool) apply_filters( 'rpress_use_service_hours_engine', $default );
}

/**
 * A service hours instance for the site timezone.
 *
 * @since 3.5
 * @return RPRESS_Service_Hours
 */
function rpress_service_hours() {
	return apply_filters( 'rpress_service_hours_instance', new RPRESS_Service_Hours() );
}

/**
 * Time format used for slot labels (same rules as the legacy list).
 *
 * @since 3.5
 * @return string
 */
function rpress_service_hours_time_format() {
	$store_time_format = rpress_get_option( 'store_time_format' );
	if ( '24hrs' === $store_time_format ) {
		return 'H:i';
	}
	if ( '12hrs' === $store_time_format ) {
		return 'h:i A';
	}
	return get_option( 'time_format' );
}

/**
 * Engine version of rp_get_store_timings().
 *
 * Same return shape: formatted time strings, or raw timestamps when
 * rpress_timing_for_today is false.
 *
 * @since 3.5
 * @param bool        $hide_past_time Drop slots before now + prep time.
 * @param string|null $service_type   Service type.
 * @param string      $service_date   Y-m-d date. Empty means today.
 * @return array
 */
function rpress_service_hours_store_timings( $hide_past_time = true, $service_type = null, $service_date = '' ) {
	$engine    = rpress_service_hours();
	$slot_date = ! empty( $service_date ) ? $service_date : $engine->now()->format( 'Y-m-d' );
	$service   = (string) $service_type;

	$time_today = apply_filters( 'rpress_timing_for_today', true );
	$slots      = $engine->get_slots(
		$slot_date,
		$service,
		array(
			// Future dates also drop slots before now + prep: that only
			// trims the early slots of a date that took over at midnight.
			'hide_past'       => $time_today && ( $hide_past_time || $slot_date > $engine->now()->format( 'Y-m-d' ) ),
			'respect_horizon' => false,
		)
	);

	if ( ! $time_today ) {
		return array_map(
			function ( $slot ) {
				return $slot->getTimestamp();
			},
			$slots
		);
	}

	$format   = rpress_service_hours_time_format();
	$timezone = $engine->get_timezone();
	$timings  = array();
	foreach ( $slots as $slot ) {
		$timings[] = wp_date( $format, $slot->getTimestamp(), $timezone );
	}
	return $timings;
}

/**
 * Engine version of rpress_is_store_open().
 *
 * Same contract and the same rpress_is_store_open filter arguments.
 *
 * @since 3.5
 * @param string $service_type  Service type.
 * @param string $selected_date Date being asked about. Empty means today.
 * @return bool
 */
function rpress_service_hours_is_store_open( $service_type, $selected_date = '' ) {
	$engine        = rpress_service_hours();
	$now           = $engine->now();
	$current_time  = $now->getTimestamp();
	$current_day   = $now->format( 'Y-m-d' );
	$selected_date = ! empty( $selected_date ) ? rp_row_date( $selected_date, $service_type ) : $current_day;
	$selected_day  = $engine->date( $selected_date );
	$selected_key  = $selected_day ? $selected_day->format( 'Y-m-d' ) : $selected_date;
	$settings      = $engine->get_settings( (string) $service_type );
	$window        = $selected_day ? $engine->get_window( $selected_key, (string) $service_type ) : null;

	if ( $settings['always_open'] ) {
		$day        = $engine->date( $current_day );
		$open_time  = $day->getTimestamp();
		$close_time = $day->setTime( 23, 59 )->getTimestamp();
		$has_hours  = true;
	} else {
		$open_time  = $window ? $window['open']->getTimestamp() : false;
		$close_time = $window ? $window['close']->getTimestamp() : false;
		$has_hours  = (bool) $window;
	}

	if ( ! $selected_day || $selected_key < $current_day ) {
		$is_open = false;
	} elseif ( $selected_key > $current_day && $selected_key === $engine->get_rollover_date( (string) $service_type ) ) {
		// Ordering carries on past midnight: tomorrow is today's service date.
		$is_open = $engine->is_open_at( $now, (string) $service_type );
	} elseif ( $selected_key > $current_day ) {
		$is_open = rpress_is_preorder_enabled( $service_type ) ? $has_hours : false;
	} else {
		$is_open = $engine->is_open_at( $now, (string) $service_type );
	}

	return apply_filters( 'rpress_is_store_open', $is_open, $service_type, $selected_date, $current_time, $open_time, $close_time );
}

/**
 * Engine version of rpress_get_selected_service_date().
 *
 * Same rules as before (past dates move to today, future dates need
 * pre-ordering), plus the midnight rollover: when today has no slots left but
 * the store keeps taking orders across midnight, tomorrow is the service date.
 *
 * @since 3.5
 * @param string $service_type   Service type.
 * @param string $requested_date Requested date.
 * @param bool   $allow_future   Whether future dates from cookies are kept.
 * @return string Y-m-d date.
 */
function rpress_service_hours_selected_service_date( $service_type, $requested_date = '', $allow_future = true ) {
	$service_type = sanitize_key( (string) $service_type );
	$engine       = rpress_service_hours();
	$today        = $engine->date( $engine->now() );
	$seed         = $engine->date( rp_row_date( $requested_date, $service_type ) );
	$rollover     = $engine->get_rollover_date( $service_type );

	if ( ! $seed || $seed < $today ) {
		$seed = $today;
	}
	if ( $seed > $today && ! ( $allow_future && rpress_is_preorder_enabled( $service_type ) ) && $seed->format( 'Y-m-d' ) !== $rollover ) {
		$seed = $today;
	}
	if ( $seed == $today && $rollover ) {
		return $rollover;
	}
	return $seed->format( 'Y-m-d' );
}

/**
 * Add the next available time to the "closed" message.
 *
 * A store that is closed for new orders says when it takes orders again
 * instead of a bare "closed". Return false from
 * rpress_service_hours_closed_message_next_open to keep the plain message.
 *
 * @since 3.5
 * @param string $message      Closed message.
 * @param string $service_type Service type.
 * @return string
 */
function rpress_service_hours_closed_message( $message, $service_type = '' ) {
	if ( ! rpress_service_hours_engine_enabled() || ! apply_filters( 'rpress_service_hours_closed_message_next_open', true, $service_type ) ) {
		return $message;
	}
	$engine = rpress_service_hours();
	$next   = $engine->next_available_slot( (string) $service_type );
	if ( ! $next ) {
		return $message;
	}
	$timezone = $engine->get_timezone();
	$time     = wp_date( rpress_service_hours_time_format(), $next->getTimestamp(), $timezone );
	$today    = $engine->date( $engine->now() );
	$day      = $engine->date( $next );
	if ( $day == $today ) {
		/* translators: %s: time */
		$when = sprintf( __( 'today at %s', 'restropress' ), $time );
	} elseif ( $day == $today->modify( '+1 day' ) ) {
		/* translators: %s: time */
		$when = sprintf( __( 'tomorrow at %s', 'restropress' ), $time );
	} else {
		/* translators: 1: date, 2: time */
		$when = sprintf( __( '%1$s at %2$s', 'restropress' ), wp_date( get_option( 'date_format' ), $next->getTimestamp(), $timezone ), $time );
	}
	/* translators: %s: when the store takes orders again, for example "tomorrow at 09:00" */
	return trim( $message ) . ' ' . sprintf( __( 'Next available: %s.', 'restropress' ), $when );
}
add_filter( 'rpress_store_closed_message', 'rpress_service_hours_closed_message', 5, 2 );
