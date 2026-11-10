<?php
/**
 * Reservations: settings, slot engine, validation, storage and status flow.
 *
 * @package     RPRESS
 * @subpackage  Features/Reservations
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Integer reservation setting with a default.
 *
 * Reads the stored value directly because rpress_get_option() treats "0" as
 * empty, and 0 is a meaningful value for lead time.
 *
 * @since 3.5
 * @param string $key     Setting ID.
 * @param int    $default Default value.
 * @param int    $min     Lowest allowed value.
 * @return int
 */
function rpress_reservation_int_setting( $key, $default, $min = 0 ) {
	global $rpress_options;
	$value = $default;
	if ( isset( $rpress_options[ $key ] ) && '' !== $rpress_options[ $key ] && is_numeric( $rpress_options[ $key ] ) ) {
		$value = (int) $rpress_options[ $key ];
	}
	$value = (int) apply_filters( 'rpress_reservation_setting_' . $key, $value );
	return max( $min, $value );
}

/**
 * All reservation settings resolved with defaults.
 *
 * @since 3.5
 * @return array
 */
function rpress_reservation_settings() {
	return apply_filters(
		'rpress_reservation_settings',
		array(
			'interval'       => rpress_reservation_int_setting( 'reservation_slot_interval', 30, 5 ),
			'capacity'       => rpress_reservation_int_setting( 'reservation_slot_capacity', 20, 1 ),
			'max_party'      => rpress_reservation_int_setting( 'reservation_max_party', 10, 1 ),
			'lead_time'      => rpress_reservation_int_setting( 'reservation_lead_time', 60, 0 ),
			'max_days'       => rpress_reservation_int_setting( 'reservation_max_days', 30, 0 ),
			'reminder_hours' => rpress_reservation_int_setting( 'reservation_reminder_hours', 3, 1 ),
			'reminders'      => ! rpress_get_option( 'reservation_disable_reminder', false ),
			'auto_confirm'   => (bool) rpress_get_option( 'reservation_auto_confirm', false ),
			'paused'         => (bool) rpress_get_option( 'reservation_paused', false ),
		)
	);
}

/**
 * Reservation statuses (slug => label).
 *
 * @since 3.5
 * @return array
 */
function rpress_reservation_statuses() {
	return apply_filters(
		'rpress_reservation_statuses',
		array(
			'pending'   => __( 'Pending', 'restropress' ),
			'confirmed' => __( 'Confirmed', 'restropress' ),
			'declined'  => __( 'Declined', 'restropress' ),
			'cancelled' => __( 'Cancelled', 'restropress' ),
			'completed' => __( 'Completed', 'restropress' ),
		)
	);
}

/**
 * Statuses that hold seats (count against slot capacity).
 *
 * @since 3.5
 * @return array
 */
function rpress_reservation_active_statuses() {
	return apply_filters( 'rpress_reservation_active_statuses', array( 'pending', 'confirmed' ) );
}

/**
 * Whether the Table Booking extension is active (hides its upsell).
 *
 * @since 3.5
 * @return bool
 */
function rpress_reservation_has_table_booking() {
	return (bool) apply_filters( 'rpress_reservation_has_table_booking', defined( 'RP_TB_VERSION' ) || class_exists( 'RP_Tablebooking' ) );
}

/**
 * Store page of the Table Booking extension.
 *
 * @since 3.5
 * @return string
 */
function rpress_reservation_table_booking_url() {
	return 'https://www.restropress.com/downloads/restropress-table-booking/';
}

/**
 * Whether a string is a real Y-m-d date.
 *
 * @since 3.5
 * @param string $date Date string.
 * @return bool
 */
function rpress_reservation_is_valid_date( $date ) {
	if ( ! is_string( $date ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
		return false;
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/**
 * Today and the last bookable date (Y-m-d, store timezone).
 *
 * @since 3.5
 * @return array { today: string, last: string }
 */
function rpress_reservation_date_window() {
	$settings = rpress_reservation_settings();
	$now      = rpress_get_wp_now();
	return array(
		'today' => $now->format( 'Y-m-d' ),
		'last'  => $now->modify( '+' . (int) $settings['max_days'] . ' day' )->format( 'Y-m-d' ),
	);
}

/**
 * Format a slot timestamp as a time, honouring the store time format.
 *
 * @since 3.5
 * @param int $ts Unix timestamp.
 * @return string
 */
function rpress_reservation_format_time( $ts ) {
	$format = get_option( 'time_format' );
	$store  = rpress_get_option( 'store_time_format' );
	if ( '24hrs' === $store ) {
		$format = 'H:i';
	} elseif ( '12hrs' === $store ) {
		$format = 'g:i a';
	}
	return wp_date( $format, (int) $ts, rpress_get_wp_timezone() );
}

/**
 * Format a slot timestamp as a date.
 *
 * @since 3.5
 * @param int $ts Unix timestamp.
 * @return string
 */
function rpress_reservation_format_date( $ts ) {
	return wp_date( get_option( 'date_format' ), (int) $ts, rpress_get_wp_timezone() );
}

/**
 * Covers already held per slot timestamp within a range.
 *
 * @since 3.5
 * @param int $from_ts    Range start (inclusive).
 * @param int $to_ts      Range end (inclusive).
 * @param int $exclude_id Reservation to leave out (when editing one).
 * @return array slot timestamp => covers
 */
function rpress_reservation_covers_by_slot( $from_ts, $to_ts, $exclude_id = 0 ) {
	global $wpdb;

	$statuses     = array_map( 'sanitize_key', rpress_reservation_active_statuses() );
	$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	$params       = array_merge( array( (int) $from_ts, (int) $to_ts, (int) $exclude_id ), $statuses );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ts.meta_value AS slot, SUM( CAST( party.meta_value AS UNSIGNED ) ) AS covers
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} ts ON ts.post_id = p.ID AND ts.meta_key = '_rpress_res_ts'
		INNER JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_rpress_res_status'
		INNER JOIN {$wpdb->postmeta} party ON party.post_id = p.ID AND party.meta_key = '_rpress_res_party'
		WHERE p.post_type = 'rpress_reservation' AND p.post_status = 'publish'
		AND CAST( ts.meta_value AS UNSIGNED ) BETWEEN %d AND %d
		AND p.ID != %d
		AND st.meta_value IN ( $placeholders )
		GROUP BY ts.meta_value",
		$params
	) );

	$covers = array();
	foreach ( (array) $rows as $row ) {
		$covers[ (int) $row->slot ] = (int) $row->covers;
	}
	return $covers;
}

/**
 * Bookable time slots for a date.
 *
 * Slots run from opening time every `interval` minutes, the last one
 * starting one interval before closing. Closed days and holidays (through
 * rpress_get_store_hours_for_date), slots inside the lead time and dates
 * outside the booking window return nothing.
 *
 * @since 3.5
 * @param string $date       Y-m-d date.
 * @param int    $party      Party size, used to flag slots without room.
 * @param int    $exclude_id Reservation to leave out of capacity counts.
 * @return array List of { ts, time, label, remaining, available }.
 */
function rpress_reservation_get_slots( $date, $party = 1, $exclude_id = 0 ) {
	if ( ! rpress_reservation_is_valid_date( $date ) ) {
		return array();
	}

	$settings = rpress_reservation_settings();
	$window   = rpress_reservation_date_window();
	if ( $date < $window['today'] || $date > $window['last'] ) {
		return array();
	}

	$hours = rpress_get_store_hours_for_date( $date );
	if ( ! empty( $hours['closed'] ) || empty( $hours['open'] ) || empty( $hours['close'] ) ) {
		return array();
	}

	$open_ts  = rpress_get_wp_timestamp( $date . ' ' . $hours['open'] );
	$close_ts = rpress_get_wp_timestamp( $date . ' ' . $hours['close'] );
	if ( false === $open_ts || false === $close_ts ) {
		return array();
	}
	// Past-midnight closing (e.g. 6pm to 1am) belongs to the same opening.
	if ( $close_ts <= $open_ts ) {
		$close_ts += DAY_IN_SECONDS;
	}

	$step       = (int) $settings['interval'] * MINUTE_IN_SECONDS;
	$last_start = $close_ts - (int) apply_filters( 'rpress_reservation_last_seating_offset', $step, $date );
	$earliest   = time() + (int) $settings['lead_time'] * MINUTE_IN_SECONDS;
	$party      = max( 1, (int) $party );
	$covers     = rpress_reservation_covers_by_slot( $open_ts, $close_ts, $exclude_id );

	$slots = array();
	for ( $ts = $open_ts; $ts <= $last_start; $ts += $step ) {
		if ( $ts < $earliest ) {
			continue;
		}
		$capacity  = (int) apply_filters( 'rpress_reservation_slot_capacity', $settings['capacity'], $ts, $date );
		$remaining = max( 0, $capacity - ( isset( $covers[ $ts ] ) ? $covers[ $ts ] : 0 ) );
		$slots[]   = array(
			'ts'        => $ts,
			'time'      => wp_date( 'H:i', $ts, rpress_get_wp_timezone() ),
			'label'     => rpress_reservation_format_time( $ts ),
			'remaining' => $remaining,
			'available' => $remaining >= $party,
		);
	}

	return apply_filters( 'rpress_reservation_slots', $slots, $date, $party );
}

/**
 * First date (from today) that has at least one bookable slot.
 *
 * @since 3.5
 * @param int $party Party size.
 * @return string Y-m-d, or today when nothing is open in the window.
 */
function rpress_reservation_first_open_date( $party = 1 ) {
	$window = rpress_reservation_date_window();
	$party  = max( 1, absint( $party ) );
	// Short cache: every page with the form asks, and each day scanned builds
	// its slots. Keyed by today, party and settings so edits apply at once.
	$cache_key = 'rpress_res_first_' . md5( $window['today'] . '|' . $party . '|' . wp_json_encode( rpress_reservation_settings() ) );
	$cached    = get_transient( $cache_key );
	if ( is_string( $cached ) && rpress_reservation_is_valid_date( $cached ) && $cached >= $window['today'] ) {
		return $cached;
	}

	$found    = $window['today'];
	$max_days = max( 1, (int) apply_filters( 'rpress_reservation_first_open_scan_days', 14 ) );
	$date     = rpress_get_wp_now();
	for ( $i = 0; $i < $max_days; $i++ ) {
		$day = $date->modify( '+' . $i . ' day' )->format( 'Y-m-d' );
		if ( $day > $window['last'] ) {
			break;
		}
		foreach ( rpress_reservation_get_slots( $day, $party ) as $slot ) {
			if ( $slot['available'] ) {
				$found = $day;
				break 2;
			}
		}
	}
	set_transient( $cache_key, $found, 5 * MINUTE_IN_SECONDS );
	return $found;
}

/**
 * Client IP for the submission limit: REMOTE_ADDR only, because forwarded
 * headers are set by the client and would let a bot pick a fresh key per
 * request. Sites behind a trusted proxy can filter in the real address.
 *
 * @since 3.5
 * @return string
 */
function rpress_reservation_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip = (string) apply_filters( 'rpress_reservation_client_ip', $ip );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Take the short booking lock for a date, so the capacity check and the
 * insert of one request cannot interleave with another's. The lock is an
 * options row created with INSERT IGNORE: option_name is unique, so only one
 * request can create it. (add_option() is not usable as a mutex: it inserts
 * with ON DUPLICATE KEY UPDATE, so a racing second call also "succeeds".)
 *
 * @since 3.5
 * @param string $key Lock key (the booking date).
 * @return string|false Option name to release, false when still busy.
 */
function rpress_reservation_acquire_lock( $key ) {
	global $wpdb;
	$name       = 'rpress_resv_lock_' . md5( (string) $key );
	$stale_done = false;
	for ( $attempt = 0; $attempt < 15; $attempt++ ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- atomic lock row, never cached.
		$created = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) time() ) );
		if ( $created ) {
			return $name;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read the lock row past any cache.
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		if ( ! $stale_done && ( ! $since || time() - $since > 10 ) ) {
			// Left behind by a request that died: clear it and retry once.
			$stale_done = true;
			rpress_reservation_release_lock( $name );
			continue;
		}
		usleep( 200000 );
	}
	return false;
}

/**
 * Release a lock taken with rpress_reservation_acquire_lock().
 *
 * @since 3.5
 * @param string|false $name Option name.
 * @return void
 */
function rpress_reservation_release_lock( $name ) {
	global $wpdb;
	if ( $name ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- lock row, never cached.
		$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
	}
}

/**
 * Validate and normalise reservation input.
 *
 * @since 3.5
 * @param array $input      Raw input: date, slot (timestamp), party, name, phone, email, note.
 * @param int   $exclude_id Reservation being edited, left out of capacity checks.
 * @return array|WP_Error Clean data, or errors keyed by field.
 */
function rpress_reservation_validate( $input, $exclude_id = 0 ) {
	$settings = rpress_reservation_settings();
	$errors   = new WP_Error();

	$data = array(
		'date'  => isset( $input['date'] ) ? sanitize_text_field( (string) $input['date'] ) : '',
		'slot'  => isset( $input['slot'] ) ? absint( $input['slot'] ) : 0,
		'party' => isset( $input['party'] ) ? absint( $input['party'] ) : 0,
		'name'  => isset( $input['name'] ) ? sanitize_text_field( (string) $input['name'] ) : '',
		'phone' => isset( $input['phone'] ) ? sanitize_text_field( (string) $input['phone'] ) : '',
		'email' => isset( $input['email'] ) ? sanitize_email( (string) $input['email'] ) : '',
		'note'  => isset( $input['note'] ) ? sanitize_textarea_field( (string) $input['note'] ) : '',
	);
	$data['name'] = mb_substr( $data['name'], 0, 100 );
	$data['note'] = mb_substr( $data['note'], 0, 500 );

	if ( '' === $data['name'] ) {
		$errors->add( 'name', __( 'Please enter your name.', 'restropress' ) );
	}
	if ( '' === $data['email'] || ! is_email( $data['email'] ) ) {
		$errors->add( 'email', __( 'Please enter a valid email address.', 'restropress' ) );
	}
	if ( ! preg_match( '/^[0-9+()\-.\s]{5,30}$/', $data['phone'] ) || strlen( preg_replace( '/\D/', '', $data['phone'] ) ) < 5 ) {
		$errors->add( 'phone', __( 'Please enter a valid phone number.', 'restropress' ) );
	}
	if ( $data['party'] < 1 ) {
		$errors->add( 'party', __( 'Please choose the number of guests.', 'restropress' ) );
	} elseif ( $data['party'] > $settings['max_party'] ) {
		$errors->add(
			'party',
			sprintf(
				/* translators: %d: maximum party size */
				__( 'Online reservations are limited to %d guests. Please call us for larger groups.', 'restropress' ),
				$settings['max_party']
			)
		);
	}

	$window = rpress_reservation_date_window();
	if ( ! rpress_reservation_is_valid_date( $data['date'] ) ) {
		$errors->add( 'date', __( 'Please choose a date.', 'restropress' ) );
	} elseif ( $data['date'] < $window['today'] ) {
		$errors->add( 'date', __( 'That date has already passed.', 'restropress' ) );
	} elseif ( $data['date'] > $window['last'] ) {
		$errors->add(
			'date',
			sprintf(
				/* translators: %d: number of days */
				_n( 'Reservations can be made up to %d day ahead.', 'Reservations can be made up to %d days ahead.', $settings['max_days'], 'restropress' ),
				$settings['max_days']
			)
		);
	} else {
		$slots = rpress_reservation_get_slots( $data['date'], max( 1, $data['party'] ), $exclude_id );
		if ( empty( $slots ) ) {
			$errors->add( 'date', __( 'We are not taking reservations on that date. Please choose another day.', 'restropress' ) );
		} elseif ( ! $data['slot'] ) {
			$errors->add( 'slot', __( 'Please choose a time.', 'restropress' ) );
		} else {
			$match = null;
			foreach ( $slots as $slot ) {
				if ( (int) $slot['ts'] === $data['slot'] ) {
					$match = $slot;
					break;
				}
			}
			if ( null === $match ) {
				$errors->add( 'slot', __( 'That time is not available. Please choose another time.', 'restropress' ) );
			} elseif ( $data['party'] >= 1 && $match['remaining'] < $data['party'] ) {
				$errors->add( 'slot', __( 'Sorry, that time is fully booked for your party size. Please choose another time.', 'restropress' ) );
			}
		}
	}

	$errors = apply_filters( 'rpress_reservation_validation_errors', $errors, $data, $input );
	if ( $errors->has_errors() ) {
		return $errors;
	}
	return $data;
}

/**
 * Create a reservation.
 *
 * @since 3.5
 * @param array $input Raw input (see rpress_reservation_validate()).
 * @param array $args  { validate: bool (default true), status: string, source: string }.
 * @return int|WP_Error Reservation ID.
 */
function rpress_insert_reservation( $input, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'validate' => true,
			'status'   => '',
			'source'   => 'web',
		)
	);

	// Capacity is counted per date (seatings can overlap), so lock the date.
	$lock_date = isset( $input['date'] ) ? sanitize_text_field( (string) $input['date'] ) : '';
	if ( '' === $lock_date && ! empty( $input['slot'] ) ) {
		$lock_date = wp_date( 'Y-m-d', absint( $input['slot'] ), rpress_get_wp_timezone() );
	}
	$lock = rpress_reservation_acquire_lock( $lock_date );
	if ( false === $lock ) {
		return new WP_Error( 'slot', __( 'We are busy taking another booking for that day. Please try again.', 'restropress' ) );
	}
	try {
		$id = rpress_reservation_insert_locked( $input, $args );
	} finally {
		rpress_reservation_release_lock( $lock );
	}
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	/**
	 * Fires after a reservation is stored. Emails hook in here.
	 *
	 * @param int    $id     Reservation ID.
	 * @param string $status Initial status.
	 * @param array  $args   Insert arguments.
	 */
	do_action( 'rpress_reservation_created', $id, rpress_get_reservation_status( $id ), $args );

	return $id;
}

/**
 * Capacity check and insert, run while the date lock is held.
 *
 * @since 3.5
 * @param array $input Raw input.
 * @param array $args  Parsed insert arguments.
 * @return int|WP_Error Reservation ID.
 */
function rpress_reservation_insert_locked( $input, $args ) {
	if ( $args['validate'] ) {
		$data = rpress_reservation_validate( $input );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
	} else {
		$data = $input;
	}

	$settings = rpress_reservation_settings();
	$status   = $args['status'];
	if ( ! isset( rpress_reservation_statuses()[ $status ] ) ) {
		$status = $settings['auto_confirm'] ? 'confirmed' : 'pending';
	}
	/**
	 * Initial status, for example a registered "awaiting payment" status that
	 * holds the seats until a deposit is paid.
	 *
	 * @since 3.5
	 * @param string $status Status slug.
	 * @param array  $data   Validated core fields.
	 * @param array  $input  Raw input, including extension fields.
	 * @param array  $args   Insert arguments.
	 */
	$filtered = apply_filters( 'rpress_reservation_initial_status', $status, $data, $input, $args );
	if ( isset( rpress_reservation_statuses()[ $filtered ] ) ) {
		$status = $filtered;
	}

	$id = wp_insert_post(
		array(
			'post_type'   => 'rpress_reservation',
			'post_status' => 'publish',
			'post_title'  => $data['name'],
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	$slot = (int) $data['slot'];
	update_post_meta( $id, '_rpress_res_ts', $slot );
	update_post_meta( $id, '_rpress_res_date', wp_date( 'Y-m-d', $slot, rpress_get_wp_timezone() ) );
	update_post_meta( $id, '_rpress_res_party', (int) $data['party'] );
	update_post_meta( $id, '_rpress_res_name', $data['name'] );
	update_post_meta( $id, '_rpress_res_phone', $data['phone'] );
	update_post_meta( $id, '_rpress_res_email', $data['email'] );
	update_post_meta( $id, '_rpress_res_note', $data['note'] );
	update_post_meta( $id, '_rpress_res_status', $status );
	update_post_meta( $id, '_rpress_res_source', sanitize_key( $args['source'] ) );
	if ( 'web' === $args['source'] ) {
		update_post_meta( $id, '_rpress_res_ip', rpress_reservation_client_ip() );
		// Lists it in the customer's account even if they book with another email.
		if ( get_current_user_id() ) {
			update_post_meta( $id, '_rpress_res_user', get_current_user_id() );
		}
	}

	/**
	 * Save extension data with the reservation. Runs while the date lock is
	 * held, so table assignment here cannot race another booking.
	 *
	 * @since 3.5
	 * @param int   $id    Reservation ID.
	 * @param array $data  Validated core fields.
	 * @param array $input Raw input, including extension fields.
	 * @param array $args  Insert arguments.
	 */
	do_action( 'rpress_reservation_insert_meta', $id, $data, $input, $args );

	return $id;
}

/**
 * Read a reservation.
 *
 * @since 3.5
 * @param int $id Reservation ID.
 * @return array|false
 */
function rpress_get_reservation( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'rpress_reservation' !== $post->post_type ) {
		return false;
	}
	$ts = (int) get_post_meta( $id, '_rpress_res_ts', true );
	return array(
		'id'     => (int) $post->ID,
		'ts'     => $ts,
		'date'   => (string) get_post_meta( $id, '_rpress_res_date', true ),
		'party'  => (int) get_post_meta( $id, '_rpress_res_party', true ),
		'name'   => (string) get_post_meta( $id, '_rpress_res_name', true ),
		'phone'  => (string) get_post_meta( $id, '_rpress_res_phone', true ),
		'email'  => (string) get_post_meta( $id, '_rpress_res_email', true ),
		'note'   => (string) get_post_meta( $id, '_rpress_res_note', true ),
		'status' => rpress_get_reservation_status( $id ),
	);
}

/**
 * Current status of a reservation.
 *
 * @since 3.5
 * @param int $id Reservation ID.
 * @return string
 */
function rpress_get_reservation_status( $id ) {
	$status = (string) get_post_meta( $id, '_rpress_res_status', true );
	return isset( rpress_reservation_statuses()[ $status ] ) ? $status : 'pending';
}

/**
 * Change a reservation's status.
 *
 * @since 3.5
 * @param int    $id     Reservation ID.
 * @param string $status New status.
 * @param bool   $notify Send the customer email for this change.
 * @return bool True when the status changed.
 */
function rpress_set_reservation_status( $id, $status, $notify = true ) {
	$status = sanitize_key( $status );
	if ( ! isset( rpress_reservation_statuses()[ $status ] ) || ! rpress_get_reservation( $id ) ) {
		return false;
	}
	$old = rpress_get_reservation_status( $id );
	if ( $old === $status ) {
		return false;
	}
	update_post_meta( $id, '_rpress_res_status', $status );

	/**
	 * Fires after a reservation status changes.
	 *
	 * @param int    $id     Reservation ID.
	 * @param string $status New status.
	 * @param string $old    Previous status.
	 * @param bool   $notify Whether customer emails should go out.
	 */
	do_action( 'rpress_reservation_status_changed', $id, $status, $old, $notify );
	return true;
}

/**
 * Schedule (or clear) the reminder for a reservation.
 *
 * @since 3.5
 * @param int $id Reservation ID.
 * @return int|false Timestamp the reminder runs at, false when none.
 */
function rpress_reservation_schedule_reminder( $id ) {
	rpress_reservation_clear_reminder( $id );

	$settings = rpress_reservation_settings();
	$res      = rpress_get_reservation( $id );
	if ( ! $res || 'confirmed' !== $res['status'] || ! $settings['reminders'] || get_post_meta( $id, '_rpress_res_reminded', true ) ) {
		return false;
	}
	$when = $res['ts'] - (int) $settings['reminder_hours'] * HOUR_IN_SECONDS;
	if ( $when <= time() ) {
		return false;
	}
	wp_schedule_single_event( $when, 'rpress_reservation_reminder', array( (int) $id ) );
	return $when;
}

/**
 * Remove a pending reminder event.
 *
 * @since 3.5
 * @param int $id Reservation ID.
 * @return void
 */
function rpress_reservation_clear_reminder( $id ) {
	wp_clear_scheduled_hook( 'rpress_reservation_reminder', array( (int) $id ) );
}

/**
 * Keep the reminder in step with the status.
 *
 * @since 3.5
 * @param int    $id     Reservation ID.
 * @param string $status New status.
 * @return void
 */
function rpress_reservation_sync_reminder( $id, $status ) {
	if ( 'confirmed' === $status ) {
		rpress_reservation_schedule_reminder( $id );
	} else {
		rpress_reservation_clear_reminder( $id );
	}
}
add_action( 'rpress_reservation_created', 'rpress_reservation_sync_reminder', 20, 2 );
add_action( 'rpress_reservation_status_changed', 'rpress_reservation_sync_reminder', 20, 2 );

/**
 * Clear the reminder when a reservation is deleted.
 *
 * @since 3.5
 * @param int $post_id Post ID.
 * @return void
 */
function rpress_reservation_before_delete( $post_id ) {
	if ( 'rpress_reservation' === get_post_type( $post_id ) ) {
		rpress_reservation_clear_reminder( $post_id );
	}
}
add_action( 'before_delete_post', 'rpress_reservation_before_delete' );
add_action( 'wp_trash_post', 'rpress_reservation_before_delete' );

/**
 * Count reservations for today (store timezone).
 *
 * @since 3.5
 * @param bool $upcoming_only Only those still ahead of now.
 * @return int
 */
function rpress_reservation_count_today( $upcoming_only = false ) {
	$today = rpress_get_wp_now()->format( 'Y-m-d' );
	$meta  = array(
		array( 'key' => '_rpress_res_date', 'value' => $today ),
		array( 'key' => '_rpress_res_status', 'value' => rpress_reservation_active_statuses(), 'compare' => 'IN' ),
	);
	if ( $upcoming_only ) {
		$meta[] = array( 'key' => '_rpress_res_ts', 'value' => time(), 'compare' => '>=', 'type' => 'NUMERIC' );
	}
	$query = new WP_Query(
		array(
			'post_type'      => 'rpress_reservation',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);
	return (int) $query->found_posts;
}

/**
 * Upcoming reservations still waiting for a Confirm or Decline
 * ("Needs action" in the list, and the count next to the menu item).
 *
 * @since 3.5
 * @return int
 */
function rpress_reservation_count_pending() {
	$query = new WP_Query(
		array(
			'post_type'      => 'rpress_reservation',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_rpress_res_status', 'value' => 'pending' ),
				array( 'key' => '_rpress_res_ts', 'value' => time(), 'compare' => '>=', 'type' => 'NUMERIC' ),
			),
		)
	);
	return (int) $query->found_posts;
}
