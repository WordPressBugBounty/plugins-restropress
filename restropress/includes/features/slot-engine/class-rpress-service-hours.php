<?php
/**
 * Service hours / slot engine
 *
 * One service that answers every "when can this store take an order"
 * question: is the store open at a moment, when does it open next, which
 * slots does a date offer, and which dates can be booked.
 *
 * Rules that keep it timezone and DST safe:
 * - Every moment is a DateTimeImmutable in the site timezone (wp_timezone()).
 * - Times are built from local wall-clock values (date + setTime), never by
 *   adding seconds across days, so a 23 or 25 hour day cannot shift slots.
 * - Wall-clock times that do not exist (the skipped hour on a spring-forward
 *   day) are dropped; repeated ones (fall-back) are generated once.
 *
 * Slots belong to the calendar date they fall on. For an overnight window
 * (for example 6:00pm to 2:00am), the slots after midnight are listed under
 * the next date, so "service date + slot time" always names the real moment.
 *
 * @package     RPRESS
 * @subpackage  Features/SlotEngine
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class RPRESS_Service_Hours {

	/**
	 * Site timezone.
	 *
	 * @var DateTimeZone
	 */
	protected $timezone;

	/**
	 * Fixed "now" (tests), or null for the real current time.
	 *
	 * @var DateTimeImmutable|null
	 */
	protected $now;

	/**
	 * Per-instance cache of resolved windows, keyed by service and date.
	 *
	 * @var array
	 */
	protected $window_cache = array();

	/**
	 * @param DateTimeZone|null      $timezone Site timezone (defaults to wp_timezone()).
	 * @param DateTimeImmutable|null $now      Fixed current time (defaults to the real time).
	 */
	public function __construct( $timezone = null, $now = null ) {
		if ( ! $timezone instanceof DateTimeZone ) {
			$timezone = function_exists( 'rpress_get_wp_timezone' ) ? rpress_get_wp_timezone() : wp_timezone();
		}
		$this->timezone = $timezone;
		$this->now      = $now instanceof DateTimeImmutable ? $now->setTimezone( $timezone ) : null;
	}

	/**
	 * The site timezone used by this instance.
	 *
	 * @return DateTimeZone
	 */
	public function get_timezone() {
		return $this->timezone;
	}

	/**
	 * Current time in the site timezone.
	 *
	 * @return DateTimeImmutable
	 */
	public function now() {
		if ( $this->now instanceof DateTimeImmutable ) {
			return $this->now;
		}
		if ( function_exists( 'rpress_get_wp_now' ) ) {
			return rpress_get_wp_now()->setTimezone( $this->timezone );
		}
		return new DateTimeImmutable( 'now', $this->timezone );
	}

	/**
	 * Parse a stored time of day into hour and minute.
	 *
	 * Accepts 12-hour ("7:30pm", "07:30 PM", "7pm", "7.30 p.m.") and 24-hour
	 * ("19:30", "19:30:00", "7.30") values.
	 *
	 * @param string $value Stored time.
	 * @return array|null array( hour, minute ) or null when not a valid time.
	 */
	public static function parse_time( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/^(\d{1,2})(?:[:.](\d{2}))?(?::\d{2})?\s*([ap])\.?\s*m?\.?$/', $value, $m ) ) {
			$hour   = (int) $m[1];
			$minute = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
			if ( $hour < 1 || $hour > 12 || $minute > 59 ) {
				return null;
			}
			if ( 'p' === $m[3] && $hour < 12 ) {
				$hour += 12;
			} elseif ( 'a' === $m[3] && 12 === $hour ) {
				$hour = 0;
			}
			return array( $hour, $minute );
		}

		if ( preg_match( '/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', $value, $m ) ) {
			$hour   = (int) $m[1];
			$minute = (int) $m[2];
			if ( $hour > 24 || $minute > 59 || ( 24 === $hour && $minute > 0 ) ) {
				return null;
			}
			return array( $hour, $minute );
		}

		return null;
	}

	/**
	 * Midnight (start of day) of a Y-m-d date in the site timezone.
	 *
	 * @param string|DateTimeInterface $date Date.
	 * @return DateTimeImmutable|null
	 */
	public function date( $date ) {
		if ( $date instanceof DateTimeInterface ) {
			$date = ( new DateTimeImmutable( '@' . $date->getTimestamp() ) )->setTimezone( $this->timezone )->format( 'Y-m-d' );
		}
		$date = trim( (string) $date );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$parsed = function_exists( 'rpress_parse_wp_datetime' ) ? rpress_parse_wp_datetime( $date ) : false;
			if ( ! $parsed instanceof DateTimeImmutable ) {
				return null;
			}
			$date = $parsed->setTimezone( $this->timezone )->format( 'Y-m-d' );
		}
		$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $this->timezone );
		if ( ! $day instanceof DateTimeImmutable || $day->format( 'Y-m-d' ) !== $date ) {
			return null;
		}
		// Midnight can be missing on DST days in a few zones; pin to 00:00 wall time.
		return $day->setTime( 0, 0 );
	}

	/**
	 * A local wall-clock moment: $day (midnight) plus $day_offset days at $hour:$minute.
	 *
	 * @param DateTimeImmutable $day        Day start.
	 * @param int               $day_offset Whole days to move (calendar days, DST safe).
	 * @param int               $hour       Hour 0..24.
	 * @param int               $minute     Minute 0..59.
	 * @return DateTimeImmutable
	 */
	protected function wall_clock( DateTimeImmutable $day, $day_offset, $hour, $minute ) {
		if ( 24 === (int) $hour ) {
			$hour = 0;
			$day_offset++;
		}
		if ( 0 !== (int) $day_offset ) {
			$day = $day->modify( sprintf( '%+d day', (int) $day_offset ) );
		}
		return $day->setTime( (int) $hour, (int) $minute );
	}

	/**
	 * Resolved settings for a service.
	 *
	 * Prep time is read through rpress_get_option() so the
	 * rpress_get_option_prep_time filter (busy mode) applies.
	 *
	 * @param string $service Service type.
	 * @return array
	 */
	public function get_settings( $service ) {
		$interval = rpress_get_option( 'order_slot_interval', '30' );
		$interval = is_numeric( $interval ) && (int) $interval > 0 ? (string) (int) $interval : '30';
		$interval = (int) apply_filters( 'rp_store_time_interval', $interval, $service );
		if ( $interval <= 0 ) {
			$interval = 30;
		}

		// Matches the legacy slot list: an empty prep time means 30 minutes.
		$prep = rpress_get_option( 'prep_time' );
		$prep = ! empty( $prep ) ? (int) $prep : 30;

		$preorder = function_exists( 'rpress_is_preorder_enabled' )
			? rpress_is_preorder_enabled( $service )
			: ( '1' === (string) rpress_get_option( 'enable_preorder', 0 ) );

		$settings = array(
			'interval'      => $interval,
			'prep_time'     => max( 0, $prep ),
			'always_open'   => ! empty( rpress_get_option( 'enable_always_open' ) ),
			'preorder'      => (bool) $preorder,
			'preorder_days' => max( 0, (int) rpress_get_option( 'po_days', 0 ) ),
		);

		return apply_filters( 'rpress_service_hours_settings', $settings, $service );
	}

	/**
	 * The service window that starts on a date.
	 *
	 * @param string $date    Y-m-d date.
	 * @param string $service Service type.
	 * A close of 11:59pm is the end of the business day, not a hard close: the
	 * window stays open until midnight, so a 12:00am-11:59pm day joins the next
	 * day without a gap. Slots still stop at the stored close time.
	 *
	 * @return array|null array( 'open' => DateTimeImmutable, 'close' => DateTimeImmutable,
	 *                    'open_minutes' => int, 'close_minutes' => int ) or null when closed.
	 *                    'close' is the moment open checks use; 'close_minutes'
	 *                    is where slots stop.
	 */
	public function get_window( $date, $service = '' ) {
		$day = $this->date( $date );
		if ( ! $day ) {
			return null;
		}
		$key = $service . '|' . $day->format( 'Y-m-d' );
		if ( array_key_exists( $key, $this->window_cache ) ) {
			return $this->window_cache[ $key ];
		}

		$hours  = rpress_get_store_hours_for_date( $day->format( 'Y-m-d' ) );
		$window = null;
		if ( is_array( $hours ) && empty( $hours['closed'] ) && isset( $hours['open'], $hours['close'] ) ) {
			$open  = self::parse_time( $hours['open'] );
			$close = self::parse_time( $hours['close'] );
			if ( $open && $close ) {
				$open_minutes  = $open[0] * 60 + $open[1];
				$close_minutes = $close[0] * 60 + $close[1];
				if ( $close_minutes < $open_minutes ) {
					// Overnight: closes on the next calendar day.
					$close_minutes += 1440;
				}
				// Same open and close is treated as closed (as before).
				if ( $close_minutes > $open_minutes ) {
					// 11:59pm marks the end of the business day: open until midnight.
					$open_until = 1439 === $close_minutes % 1440 ? $close_minutes + 1 : $close_minutes;
					$window     = array(
						'open'           => $this->wall_clock( $day, 0, $open[0], $open[1] ),
						'close'          => $this->wall_clock( $day, intdiv( $open_until, 1440 ), intdiv( $open_until % 1440, 60 ), $open_until % 60 ),
						'open_minutes'   => $open_minutes,
						'close_minutes'  => $close_minutes,
						'until_midnight' => $open_until !== $close_minutes,
					);
				}
			}
		}

		$window = apply_filters( 'rpress_service_hours_window', $window, $day->format( 'Y-m-d' ), $service );
		$this->window_cache[ $key ] = $window;
		return $window;
	}

	/**
	 * Whether the store takes orders for a service at a moment.
	 *
	 * @param DateTimeInterface $dt      Moment.
	 * @param string            $service Service type.
	 * @return bool
	 */
	public function is_open_at( DateTimeInterface $dt, $service = '' ) {
		$settings = $this->get_settings( $service );
		if ( $settings['always_open'] ) {
			return true;
		}
		$ts  = $dt->getTimestamp();
		$day = $this->date( $dt );
		foreach ( array( $day->modify( '-1 day' ), $day ) as $start_day ) {
			$window = $this->get_window( $start_day->format( 'Y-m-d' ), $service );
			if ( ! $window || $ts < $window['open']->getTimestamp() ) {
				continue;
			}
			$close = $window['close']->getTimestamp();
			// A stored close is inclusive; the 11:59pm end-of-day boundary runs up to (not including) midnight.
			if ( $ts < $close || ( $ts === $close && empty( $window['until_midnight'] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The first moment at or after $dt when the store is open.
	 *
	 * @param DateTimeInterface $dt       Moment to start from.
	 * @param string            $service  Service type.
	 * @param int               $max_days How far ahead to look.
	 * @return DateTimeImmutable|null Null when nothing opens within $max_days.
	 */
	public function next_open_at( DateTimeInterface $dt, $service = '', $max_days = 14 ) {
		$from = ( new DateTimeImmutable( '@' . $dt->getTimestamp() ) )->setTimezone( $this->timezone );
		if ( $this->is_open_at( $from, $service ) ) {
			return $from;
		}
		$day = $this->date( $from );
		for ( $offset = -1; $offset <= (int) $max_days; $offset++ ) {
			$window = $this->get_window( $day->modify( sprintf( '%+d day', $offset ) )->format( 'Y-m-d' ), $service );
			if ( ! $window || $window['close'] < $from ) {
				continue;
			}
			return $window['open'] > $from ? $window['open'] : $from;
		}
		return null;
	}

	/**
	 * Whether a date is inside the booking horizon (today, or a pre-order day).
	 *
	 * @param string $date    Y-m-d date.
	 * @param string $service Service type.
	 * @return bool
	 */
	public function is_date_bookable( $date, $service = '' ) {
		$day   = $this->date( $date );
		$today = $this->date( $this->now() );
		if ( ! $day || $day < $today ) {
			return false;
		}
		if ( $day == $today ) {
			return true;
		}
		if ( $this->get_rollover_date( $service ) === $day->format( 'Y-m-d' ) ) {
			return true;
		}
		$settings = $this->get_settings( $service );
		if ( ! $settings['preorder'] ) {
			return false;
		}
		if ( $settings['preorder_days'] > 0 ) {
			$last = $today->modify( '+' . $settings['preorder_days'] . ' day' );
			return $day <= $last;
		}
		return true;
	}

	/**
	 * Bookable slots on a calendar date, in order.
	 *
	 * @param string $date    Y-m-d date.
	 * @param string $service Service type.
	 * @param array  $args {
	 *     @type bool $hide_past       Drop slots before now + prep time. Default true.
	 *     @type bool $respect_horizon Return nothing for dates outside today/pre-order days. Default true.
	 * }
	 * @return DateTimeImmutable[]
	 */
	public function get_slots( $date, $service = '', $args = array() ) {
		$args = array_merge(
			array(
				'hide_past'       => true,
				'respect_horizon' => true,
			),
			(array) $args
		);

		$day = $this->date( $date );
		if ( ! $day ) {
			return array();
		}
		if ( $args['respect_horizon'] && ! $this->is_date_bookable( $day->format( 'Y-m-d' ), $service ) ) {
			return array();
		}

		$settings = $this->get_settings( $service );
		$interval = $settings['interval'];
		$date_key = $day->format( 'Y-m-d' );
		$slots    = array();

		// The previous day's overnight window spills into this date.
		foreach ( array( -1, 0 ) as $start_offset ) {
			$start_day = 0 === $start_offset ? $day : $day->modify( '-1 day' );
			$window    = $this->get_window( $start_day->format( 'Y-m-d' ), $service );
			if ( ! $window ) {
				continue;
			}
			for ( $m = $window['open_minutes']; $m <= $window['close_minutes']; $m += $interval ) {
				$day_offset = intdiv( $m, 1440 );
				$hour       = intdiv( $m % 1440, 60 );
				$minute     = $m % 60;
				// Only the part of the window that falls on this calendar date.
				if ( $start_offset + $day_offset !== 0 ) {
					continue;
				}
				$slot = $this->wall_clock( $start_day, $day_offset, $hour, $minute );
				// Skip wall-clock times that do not exist (spring-forward gap).
				if ( $slot->format( 'Y-m-d H:i' ) !== sprintf( '%s %02d:%02d', $date_key, $hour, $minute ) ) {
					continue;
				}
				$slots[ $slot->getTimestamp() ] = $slot;
			}
		}

		if ( $args['hide_past'] ) {
			$earliest = $this->now()->getTimestamp() + $settings['prep_time'] * 60;
			foreach ( array_keys( $slots ) as $ts ) {
				if ( $ts <= $earliest ) {
					unset( $slots[ $ts ] );
				}
			}
		}

		ksort( $slots );
		return apply_filters( 'rpress_service_hours_slots', array_values( $slots ), $date_key, $service, $args );
	}

	/**
	 * The date that takes over from today when ordering carries on past midnight.
	 *
	 * Today's slots can run out while the store is still taking orders, for
	 * example at 23:48 with a 30 minute prep time. When the store stays open
	 * across midnight (always open, or hours that run into the next day), the
	 * service date rolls to tomorrow so the next slots and ASAP stay available
	 * even without pre-ordering. A store that closes at midnight does not roll:
	 * it is closed for new orders until it opens again.
	 *
	 * @param string $service Service type.
	 * @return string|null Y-m-d of tomorrow, or null when today still applies.
	 */
	public function get_rollover_date( $service = '' ) {
		$now   = $this->now();
		$today = $this->date( $now );
		$args  = array(
			'hide_past'       => true,
			'respect_horizon' => false,
		);
		if ( $this->get_slots( $today->format( 'Y-m-d' ), $service, $args ) ) {
			return null;
		}
		$tomorrow = $today->modify( '+1 day' )->format( 'Y-m-d' );
		$next     = $this->get_slots( $tomorrow, $service, $args );
		if ( ! $next ) {
			return null;
		}
		$settings = $this->get_settings( $service );
		$earliest = $now->getTimestamp() + $settings['prep_time'] * 60;
		$rolls    = $settings['always_open'];
		if ( ! $rolls && $this->is_open_at( $now, $service ) ) {
			// Open now and still open once the order is ready, with the next
			// slot following straight on (no gap until the next opening).
			$ready = ( new DateTimeImmutable( '@' . $earliest ) )->setTimezone( $this->timezone );
			$rolls = $this->is_open_at( $ready, $service ) && $next[0]->getTimestamp() - $earliest <= $settings['interval'] * 60;
		}
		return apply_filters( 'rpress_service_hours_rollover_date', $rolls ? $tomorrow : null, $service, $now );
	}

	/**
	 * The first slot a new order can get, today or later.
	 *
	 * @param string $service  Service type.
	 * @param int    $max_days How far ahead to look.
	 * @return DateTimeImmutable|null
	 */
	public function next_available_slot( $service = '', $max_days = 14 ) {
		$today = $this->date( $this->now() );
		for ( $offset = 0; $offset <= (int) $max_days; $offset++ ) {
			$slots = $this->get_slots(
				$today->modify( '+' . $offset . ' day' )->format( 'Y-m-d' ),
				$service,
				array(
					'hide_past'       => true,
					'respect_horizon' => false,
				)
			);
			if ( $slots ) {
				return $slots[0];
			}
		}
		return null;
	}

	/**
	 * Dates (Y-m-d) that have at least one bookable slot.
	 *
	 * Without pre-ordering this is at most today. With pre-ordering it covers
	 * today plus the following days, capped by the pre-order day limit.
	 *
	 * @param string   $service Service type.
	 * @param int|null $days    Number of days to inspect, today included.
	 *                          Defaults to the pre-order limit, or 7.
	 * @return string[]
	 */
	public function get_available_dates( $service = '', $days = null ) {
		$settings = $this->get_settings( $service );
		if ( null === $days ) {
			$days = $settings['preorder_days'] > 0 ? $settings['preorder_days'] + 1 : 7;
		}
		$days  = $settings['preorder'] ? max( 1, (int) $days ) : 1;
		$today = $this->date( $this->now() );
		$dates = array();
		for ( $offset = 0; $offset < $days; $offset++ ) {
			$candidate = $today->modify( '+' . $offset . ' day' )->format( 'Y-m-d' );
			if ( $this->get_slots( $candidate, $service ) ) {
				$dates[] = $candidate;
			}
		}
		$rollover = $this->get_rollover_date( $service );
		if ( $rollover && ! in_array( $rollover, $dates, true ) && ! in_array( $today->format( 'Y-m-d' ), $dates, true ) ) {
			array_unshift( $dates, $rollover );
			$dates = array_slice( $dates, 0, max( 1, $days ) );
		}
		return apply_filters( 'rpress_service_hours_available_dates', $dates, $service, $days );
	}
}
