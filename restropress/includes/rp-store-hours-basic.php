<?php
/**
 * Basic per-day store hours and holidays (free tier)
 *
 * Per-day open/close hours and up to five holiday dates, configured under
 * RestroPress > Settings > General > Service & Hours. Defers to the Store
 * Timing & Delivery Cutoff extension when it is active: the extension
 * overrides rpress_is_store_open and rpress_store_timings with its own
 * per-service schedules, cutoffs, breaks, and unlimited holidays.
 *
 * @package     RPRESS
 * @subpackage  Functions/StoreHours
 * @since       3.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Whether core basic hours/holidays are in charge of the schedule UI.
 *
 * The Store Timing extension takes over completely when active.
 *
 * @since 3.4
 * @return bool
 */
function rpress_basic_store_hours_active() {
	return apply_filters( 'rpress_basic_store_hours_active', ! class_exists( 'RP_StoreTiming_Functions' ) );
}

/**
 * The configured holiday dates (Y-m-d strings, at most five).
 *
 * @since 3.4
 * @return array
 */
function rpress_get_basic_holidays() {
	$holidays = rpress_get_option( 'basic_holidays', array() );
	if ( ! is_array( $holidays ) ) {
		$holidays = array();
	}
	$holidays = array_slice( array_filter( array_map( 'sanitize_text_field', $holidays ) ), 0, 5 );
	return apply_filters( 'rpress_basic_holidays', array_values( $holidays ) );
}

/**
 * Whether a date is one of the configured basic holidays.
 *
 * @since 3.4
 * @param string $date Y-m-d date.
 * @return bool
 */
function rpress_is_basic_holiday( $date ) {
	return rpress_basic_store_hours_active() && in_array( $date, rpress_get_basic_holidays(), true );
}

/**
 * Resolve the open/close window for a given date.
 *
 * Reads the per-day store_hours setting (keyed 1=Monday .. 7=Sunday) and
 * falls back to the legacy global open_time/close_time pair for days that
 * have never been configured.
 *
 * @since 3.4
 * @param string $date          Y-m-d date. Empty means today.
 * @param bool   $skip_holidays When true, holidays are ignored and only the
 *                              weekly schedule answers (used by displays that
 *                              show recurring hours, like the Opening Hours
 *                              block, where holidays are listed separately).
 * @return array { open: string, close: string, closed: bool }
 */
function rpress_get_store_hours_for_date( $date = '', $skip_holidays = false ) {
	$legacy_open  = ! empty( rpress_get_option( 'open_time' ) ) ? rpress_get_option( 'open_time' ) : '9:00am';
	$legacy_close = ! empty( rpress_get_option( 'close_time' ) ) ? rpress_get_option( 'close_time' ) : '11:30pm';
	$hours        = array(
		'open'   => $legacy_open,
		'close'  => $legacy_close,
		'closed' => false,
	);

	if ( empty( $date ) ) {
		$date = rpress_get_wp_now()->format( 'Y-m-d' );
	}

	if ( rpress_basic_store_hours_active() ) {
		// "Always order" overrides closed days and holidays, as it always has,
		// but per-day times still shape the slot window when configured.
		$ignore_closed = ! empty( rpress_get_option( 'enable_always_open' ) );

		if ( ! $ignore_closed && ! $skip_holidays && rpress_is_basic_holiday( $date ) ) {
			$hours['closed'] = true;
		} else {
			$day_config = rpress_get_option( 'store_hours', array() );
			$timestamp  = rpress_get_wp_timestamp( $date );
			$day_number = false !== $timestamp ? (int) wp_date( 'N', $timestamp, rpress_get_wp_timezone() ) : 0;
			if ( $day_number && isset( $day_config[ $day_number ] ) && is_array( $day_config[ $day_number ] ) ) {
				$day = $day_config[ $day_number ];
				if ( ! empty( $day['closed'] ) && ! $ignore_closed ) {
					$hours['closed'] = true;
				} else {
					if ( ! empty( $day['open'] ) ) {
						$hours['open'] = $day['open'];
					}
					if ( ! empty( $day['close'] ) ) {
						$hours['close'] = $day['close'];
					}
				}
			}
		}
	}

	return apply_filters( 'rpress_store_hours_for_date', $hours, $date, $skip_holidays );
}
