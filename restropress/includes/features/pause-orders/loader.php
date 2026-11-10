<?php
/**
 * Pause orders / busy mode.
 *
 * Lets staff pause new orders for a while (or until resumed) and add extra
 * prep time from the Live Orders screen. State lives in one option with
 * expiry timestamps and is checked on every read, so no cron is needed.
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pause orders / busy mode feature.
 */
class RPRESS_Pause_Orders {

	/**
	 * Option holding the pause / busy state.
	 */
	const OPTION = 'rpress_pause_orders';

	/**
	 * Nonce action for the admin AJAX endpoint.
	 */
	const NONCE = 'rpress_pause_orders';

	/**
	 * Error code used for blocked add-to-cart / checkout requests.
	 */
	const ERROR_CODE = 'orders_paused';

	/**
	 * Whether the storefront banner has been printed on this request.
	 *
	 * @var bool
	 */
	protected static $banner_printed = false;

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Extra prep time shifts the ASAP estimate and earliest slot.
		add_filter( 'rpress_get_option_prep_time', array( __CLASS__, 'filter_prep_time' ), 10, 3 );
		// While paused with pre-orders on, hide slots that fall inside the pause.
		add_filter( 'rpress_store_timings', array( __CLASS__, 'filter_store_timings' ), 20, 3 );

		// Block the service picker, item popup and add to cart while paused.
		foreach ( array( 'show_delivery_options', 'show_products', 'add_to_cart' ) as $event ) {
			add_action( 'wp_ajax_rpress_' . $event, array( __CLASS__, 'guard_cart_ajax' ), 1 );
			add_action( 'wp_ajax_nopriv_rpress_' . $event, array( __CLASS__, 'guard_cart_ajax' ), 1 );
			add_action( 'rp_ajax_' . $event, array( __CLASS__, 'guard_cart_ajax' ), 1 );
		}

		// Block checkout server side.
		add_filter( 'rpress_proceed_checkout', array( __CLASS__, 'filter_proceed_checkout' ), 20 );
		add_action( 'rpress_checkout_error_checks', array( __CLASS__, 'checkout_error_checks' ), 10, 2 );
		// Same rule for orders created through the REST API.
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'guard_rest_order_create' ), 10, 3 );

		// Storefront banner on the menu and checkout.
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'filter_shortcode_output' ), 10, 2 );
		add_filter( 'render_block', array( __CLASS__, 'filter_block_output' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ), 99 );

		// Admin controls on the Live Orders screen.
		add_action( 'rpress_live_orders_before_toolbar', array( __CLASS__, 'render_admin_panel' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		add_action( 'wp_ajax_rpress_pause_orders', array( __CLASS__, 'ajax_update' ) );
	}

	/* --------------------------------------------------------------------
	 * State
	 * ------------------------------------------------------------------ */

	/**
	 * Current state with expired parts dropped.
	 *
	 * @return array { paused: bool, until: int (0 = until resumed), extra_prep: int, extra_until: int }
	 */
	public static function get_state() {
		$raw = get_option( self::OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();
		$now = time();

		$state = array(
			'paused'      => ! empty( $raw['paused'] ),
			'until'       => isset( $raw['until'] ) ? absint( $raw['until'] ) : 0,
			'extra_prep'  => isset( $raw['extra_prep'] ) ? absint( $raw['extra_prep'] ) : 0,
			'extra_until' => isset( $raw['extra_until'] ) ? absint( $raw['extra_until'] ) : 0,
		);

		$expired = false;
		if ( $state['paused'] && $state['until'] && $state['until'] <= $now ) {
			$state['paused'] = false;
			$state['until']  = 0;
			$expired         = true;
		}
		if ( ! $state['paused'] ) {
			$state['until'] = 0;
		}
		if ( $state['extra_prep'] && $state['extra_until'] && $state['extra_until'] <= $now ) {
			$state['extra_prep']  = 0;
			$state['extra_until'] = 0;
			$expired              = true;
		}

		// Auto-resume: persist the cleaned state the first time it is read after expiry.
		if ( $expired ) {
			self::save_state( $state );
		}

		return $state;
	}

	/**
	 * Persist the state.
	 *
	 * @param array $state State array.
	 */
	protected static function save_state( $state ) {
		$state = array(
			'paused'      => ! empty( $state['paused'] ),
			'until'       => absint( $state['until'] ),
			'extra_prep'  => absint( $state['extra_prep'] ),
			'extra_until' => absint( $state['extra_until'] ),
		);
		if ( ! $state['paused'] && ! $state['extra_prep'] ) {
			delete_option( self::OPTION );
		} else {
			update_option( self::OPTION, $state, true );
		}
		do_action( 'rpress_pause_orders_changed', $state );
	}

	/**
	 * Allowed pause durations in minutes (0 = until resumed).
	 *
	 * @return int[]
	 */
	public static function pause_durations() {
		return array_map( 'absint', (array) apply_filters( 'rpress_pause_orders_durations', array( 15, 30, 60, 0 ) ) );
	}

	/**
	 * Allowed extra prep steps in minutes.
	 *
	 * @return int[]
	 */
	public static function busy_steps() {
		return array_map( 'absint', (array) apply_filters( 'rpress_pause_orders_busy_steps', array( 10, 20, 30 ) ) );
	}

	/**
	 * Pause new orders.
	 *
	 * @param int $minutes Minutes to pause for, 0 for until resumed.
	 * @return array New state.
	 */
	public static function pause( $minutes ) {
		$minutes          = absint( $minutes );
		$state            = self::get_state();
		$state['paused']  = true;
		$state['until']   = $minutes > 0 ? time() + $minutes * MINUTE_IN_SECONDS : 0;
		self::save_state( $state );
		return self::get_state();
	}

	/**
	 * Resume taking orders.
	 *
	 * @return array New state.
	 */
	public static function resume() {
		$state           = self::get_state();
		$state['paused'] = false;
		$state['until']  = 0;
		self::save_state( $state );
		return self::get_state();
	}

	/**
	 * Set the extra prep time. Busy mode clears itself at the end of the
	 * store's day so the next service starts with the normal prep time.
	 *
	 * @param int $minutes Extra minutes, 0 to clear.
	 * @return array New state.
	 */
	public static function set_extra_prep( $minutes ) {
		$minutes              = absint( $minutes );
		$state                = self::get_state();
		$state['extra_prep']  = $minutes;
		$state['extra_until'] = 0;
		if ( $minutes > 0 ) {
			$midnight             = new DateTime( 'tomorrow', rpress_get_wp_timezone() );
			$state['extra_until'] = (int) apply_filters( 'rpress_pause_orders_busy_until', $midnight->getTimestamp(), $minutes );
		}
		self::save_state( $state );
		return self::get_state();
	}

	/**
	 * Whether new orders are paused right now.
	 *
	 * @return bool
	 */
	public static function is_paused() {
		$state = self::get_state();
		return $state['paused'];
	}

	/**
	 * Extra prep minutes in effect right now.
	 *
	 * @return int
	 */
	public static function get_extra_prep() {
		$state = self::get_state();
		return $state['extra_prep'];
	}

	/* --------------------------------------------------------------------
	 * Prep time and slots
	 * ------------------------------------------------------------------ */

	/**
	 * Whether this request is a storefront one (prep / slot filters apply).
	 * The settings screen reads prep_time too and must keep the saved value.
	 *
	 * @return bool
	 */
	protected static function is_storefront_context() {
		return ! is_admin() || wp_doing_ajax();
	}

	/**
	 * Add the busy-mode minutes to the prep time setting on read.
	 *
	 * @param mixed  $value   Option value.
	 * @param string $key     Option key.
	 * @param mixed  $default Default passed to rpress_get_option().
	 * @return mixed
	 */
	public static function filter_prep_time( $value, $key = 'prep_time', $default = false ) {
		if ( ! self::is_storefront_context() ) {
			return $value;
		}
		$extra = self::get_extra_prep();
		if ( $extra <= 0 ) {
			return $value;
		}
		return absint( $value ) + $extra;
	}

	/**
	 * Saved prep time in minutes, without the busy-mode minutes.
	 *
	 * @return int
	 */
	public static function base_prep_time() {
		remove_filter( 'rpress_get_option_prep_time', array( __CLASS__, 'filter_prep_time' ), 10 );
		$minutes = absint( rpress_get_option( 'prep_time', 0 ) );
		add_filter( 'rpress_get_option_prep_time', array( __CLASS__, 'filter_prep_time' ), 10, 3 );
		return $minutes;
	}

	/**
	 * Slot date + label to timestamp.
	 *
	 * @param mixed  $slot         Slot label or timestamp.
	 * @param string $service_date Y-m-d.
	 * @return int|false
	 */
	protected static function slot_timestamp( $slot, $service_date ) {
		if ( is_int( $slot ) || ( is_string( $slot ) && ctype_digit( $slot ) && strlen( $slot ) > 6 ) ) {
			return (int) $slot;
		}
		if ( ! is_scalar( $slot ) ) {
			return false;
		}
		$label = trim( str_ireplace( 'ASAP', '', (string) $slot ) );
		if ( '' === $label ) {
			return false;
		}
		$date = ! empty( $service_date ) ? $service_date : rpress_get_wp_now()->format( 'Y-m-d' );
		return rpress_get_wp_timestamp( $date . ' ' . $label );
	}

	/**
	 * While paused with pre-orders on, only offer slots after the pause.
	 * Without pre-orders ordering is blocked outright, so slots stay as they are.
	 *
	 * @param array  $store_times  Slots.
	 * @param string $service_type Service type.
	 * @param string $service_date Y-m-d.
	 * @return array
	 */
	public static function filter_store_timings( $store_times, $service_type = '', $service_date = '' ) {
		if ( ! is_array( $store_times ) || empty( $store_times ) || ! self::is_storefront_context() ) {
			return $store_times;
		}
		$state = self::get_state();
		if ( ! $state['paused'] || ! rpress_is_preorder_enabled( $service_type ) ) {
			return $store_times;
		}
		$today = rpress_get_wp_now()->format( 'Y-m-d' );
		$date  = ! empty( $service_date ) ? $service_date : $today;
		if ( ! $state['until'] ) {
			// Paused until resumed: nothing today, later days stay open for pre-orders.
			return $date > $today ? $store_times : array();
		}
		$kept = array();
		foreach ( $store_times as $slot ) {
			// No ASAP while paused, only scheduled times after the pause.
			if ( is_string( $slot ) && false !== stripos( $slot, 'ASAP' ) ) {
				continue;
			}
			$ts = self::slot_timestamp( $slot, $date );
			if ( false === $ts || $ts >= $state['until'] ) {
				$kept[] = $slot;
			}
		}
		return $kept;
	}

	/* --------------------------------------------------------------------
	 * Ordering gate
	 * ------------------------------------------------------------------ */

	/**
	 * Service type chosen by the customer (POST first, then cookie).
	 *
	 * @return string
	 */
	protected static function selected_service_type() {
		// phpcs:disable WordPress.Security.NonceVerification -- read only, the core handlers verify nonces.
		$type = '';
		if ( ! empty( $_POST['rpress_service_type'] ) ) {
			$type = sanitize_key( wp_unslash( $_POST['rpress_service_type'] ) );
		} elseif ( ! empty( $_POST['service_type'] ) ) {
			$type = sanitize_key( wp_unslash( $_POST['service_type'] ) );
		} elseif ( ! empty( $_COOKIE['service_type'] ) ) {
			$type = sanitize_key( wp_unslash( $_COOKIE['service_type'] ) );
		}
		// phpcs:enable
		if ( '' === $type && function_exists( 'rpress_get_default_enabled_service' ) ) {
			$type = (string) rpress_get_default_enabled_service();
		}
		return $type;
	}

	/**
	 * Service date and time the customer picked (POST first, then cookie).
	 *
	 * @return array { date: string, time: string }
	 */
	protected static function selected_service_choice() {
		// phpcs:disable WordPress.Security.NonceVerification -- read only, the core handlers verify nonces.
		$date = '';
		if ( ! empty( $_POST['rpress_service_date'] ) ) {
			$date = sanitize_text_field( wp_unslash( $_POST['rpress_service_date'] ) );
		} elseif ( ! empty( $_COOKIE['service_date'] ) ) {
			$date = sanitize_text_field( wp_unslash( $_COOKIE['service_date'] ) );
		}
		$time = '';
		if ( ! empty( $_POST['rpress_service_time'] ) ) {
			$time = sanitize_text_field( wp_unslash( $_POST['rpress_service_time'] ) );
		} elseif ( ! empty( $_COOKIE['service_time'] ) ) {
			$time = sanitize_text_field( wp_unslash( $_COOKIE['service_time'] ) );
		}
		// phpcs:enable
		return array(
			'date' => $date,
			'time' => $time,
		);
	}

	/**
	 * Whether a service date + time means "as soon as possible": ASAP or no
	 * time, for today (or no date). A later date without a time is not ASAP.
	 *
	 * @param string $date Y-m-d or empty.
	 * @param string $time Slot label, ASAP or empty.
	 * @return bool
	 */
	public static function is_asap_choice( $date, $time ) {
		$label = trim( str_ireplace( 'ASAP', '', (string) $time ) );
		if ( '' !== $label && false === stripos( (string) $time, 'ASAP' ) ) {
			return false;
		}
		return '' === (string) $date || $date <= rpress_get_wp_now()->format( 'Y-m-d' );
	}

	/**
	 * Whether the customer picked ASAP (or no time yet) for today.
	 *
	 * @return bool
	 */
	public static function selected_is_asap() {
		$choice = self::selected_service_choice();
		return self::is_asap_choice( $choice['date'], $choice['time'] );
	}

	/**
	 * Timestamp of the service time the customer picked (ASAP = now + prep).
	 *
	 * @return int
	 */
	public static function selected_service_timestamp() {
		$choice = self::selected_service_choice();
		$date   = $choice['date'];
		$time   = $choice['time'];

		$now = time();
		$asap = $now + absint( rpress_get_option( 'prep_time', 0 ) ) * MINUTE_IN_SECONDS;
		if ( '' === $date ) {
			$date = rpress_get_wp_now()->format( 'Y-m-d' );
		}
		$label = trim( str_ireplace( 'ASAP', '', $time ) );
		if ( '' === $label || false !== stripos( $time, 'ASAP' ) ) {
			// ASAP (or no time yet): today means right now, a later date means its first slot.
			$date_ts = rpress_get_wp_timestamp( $date . ' 00:00' );
			return ( false !== $date_ts && $date_ts > $asap ) ? (int) $date_ts : $asap;
		}
		$ts = rpress_get_wp_timestamp( $date . ' ' . $label );
		return false === $ts ? $asap : (int) $ts;
	}

	/**
	 * Formatted clock time using the store's time format.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	public static function format_time( $timestamp ) {
		$format       = get_option( 'time_format', 'g:i a' );
		$store_format = rpress_get_option( 'store_time_format' );
		if ( '24hrs' === $store_format ) {
			$format = 'H:i';
		} elseif ( '12hrs' === $store_format ) {
			$format = 'g:i A';
		}
		return wp_date( $format, (int) $timestamp, rpress_get_wp_timezone() );
	}

	/**
	 * Error for an add-to-cart style request (no service time known yet).
	 * With pre-orders on, customers may keep building a cart for later.
	 *
	 * @return string Empty when allowed.
	 */
	public static function cart_block_message() {
		$state = self::get_state();
		if ( ! $state['paused'] || rpress_is_preorder_enabled( self::selected_service_type() ) ) {
			return '';
		}
		return self::paused_message( $state );
	}

	/**
	 * Error for a checkout request at the given service time.
	 *
	 * @param int|null $service_ts Service timestamp, null to read the customer's choice.
	 * @return string Empty when allowed.
	 */
	public static function checkout_block_message( $service_ts = null ) {
		if ( null === $service_ts ) {
			return self::service_block_message( self::selected_service_type(), self::selected_service_timestamp(), self::selected_is_asap() );
		}
		return self::service_block_message( self::selected_service_type(), (int) $service_ts, false );
	}

	/**
	 * Error for an order with the given service, empty when allowed.
	 *
	 * While paused, ASAP is never allowed: it would count as now + prep time and
	 * slip through whenever the prep time is longer than the pause. Only an
	 * explicitly scheduled time at or after the pause end (pre-orders) passes.
	 *
	 * @param string $service_type Service type.
	 * @param int    $service_ts   Service timestamp.
	 * @param bool   $is_asap      Whether the customer asked for ASAP.
	 * @return string
	 */
	public static function service_block_message( $service_type, $service_ts, $is_asap ) {
		$state = self::get_state();
		if ( ! $state['paused'] ) {
			return '';
		}
		if ( ! rpress_is_preorder_enabled( $service_type ) ) {
			return self::paused_message( $state );
		}
		if ( $is_asap ) {
			if ( $state['until'] ) {
				/* translators: %s: time ordering resumes, e.g. 7:30 PM */
				$message = sprintf( __( 'We are not taking ASAP orders right now. Please choose a time after %s.', 'restropress' ), self::format_time( $state['until'] ) );
			} else {
				$message = __( 'We are not taking ASAP orders right now. Please choose a later date.', 'restropress' );
			}
			return (string) apply_filters( 'rpress_pause_orders_error_message', $message, $state );
		}
		$service_ts = (int) $service_ts;
		if ( $state['until'] ) {
			if ( $service_ts >= $state['until'] ) {
				return '';
			}
			/* translators: %s: time ordering resumes, e.g. 7:30 PM */
			return sprintf( __( 'We are not taking orders for this time. Please choose a time after %s.', 'restropress' ), self::format_time( $state['until'] ) );
		}
		// Paused until resumed: later days only.
		if ( wp_date( 'Y-m-d', $service_ts, rpress_get_wp_timezone() ) > rpress_get_wp_now()->format( 'Y-m-d' ) ) {
			return '';
		}
		return __( 'We are not taking orders for today. Please choose a later date.', 'restropress' );
	}

	/**
	 * Generic "paused" error text.
	 *
	 * @param array $state State.
	 * @return string
	 */
	protected static function paused_message( $state ) {
		if ( $state['until'] ) {
			/* translators: %s: time ordering resumes, e.g. 7:30 PM */
			$message = sprintf( __( 'We are not taking new orders right now. Please try again after %s.', 'restropress' ), self::format_time( $state['until'] ) );
		} else {
			$message = __( 'We are not taking new orders right now. Please check back soon.', 'restropress' );
		}
		return (string) apply_filters( 'rpress_pause_orders_error_message', $message, $state );
	}

	/**
	 * JSON error payload for a blocked cart request, or null when allowed.
	 *
	 * @return array|null
	 */
	public static function cart_ajax_error() {
		$message = self::cart_block_message();
		if ( '' === $message ) {
			return null;
		}
		return array(
			'status'     => 'error',
			'error_type' => self::ERROR_CODE,
			'error_msg'  => $message,
		);
	}

	/**
	 * Runs before the core show_products / add_to_cart handlers.
	 */
	public static function guard_cart_ajax() {
		$error = self::cart_ajax_error();
		if ( null !== $error ) {
			wp_send_json_error( $error );
		}
	}

	/**
	 * Block the "Checkout" button in the cart while paused.
	 *
	 * @param mixed $response Pre-validation response.
	 * @return mixed
	 */
	public static function filter_proceed_checkout( $response ) {
		if ( is_array( $response ) && isset( $response['status'] ) && 'error' === $response['status'] ) {
			return $response;
		}
		$message = self::checkout_block_message();
		if ( '' === $message ) {
			return $response;
		}
		return array(
			'status'     => 'error',
			'error_type' => self::ERROR_CODE,
			'error_msg'  => $message,
		);
	}

	/**
	 * Block the purchase form submission while paused.
	 *
	 * @param mixed $valid_data Validated data.
	 * @param array $data       Posted data.
	 */
	public static function checkout_error_checks( $valid_data = null, $data = array() ) {
		$message = self::checkout_block_message();
		if ( '' !== $message ) {
			rpress_set_error( self::ERROR_CODE, $message );
		}
	}

	/**
	 * Apply the pause to REST order creation (POST to the orders endpoint).
	 *
	 * @param mixed           $response Response so far (null to run the callback).
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  Request.
	 * @return mixed WP_Error when the order is blocked.
	 */
	public static function guard_rest_order_create( $response, $handler, $request ) {
		if ( null !== $response || ! class_exists( 'RP_REST_Orders_V1_Controller' ) || empty( $handler['callback'] ) || ! is_array( $handler['callback'] ) ) {
			return $response;
		}
		list( $controller, $method ) = array_pad( $handler['callback'], 2, '' );
		if ( 'create_item' !== $method || ! ( $controller instanceof RP_REST_Orders_V1_Controller ) || ! self::is_paused() ) {
			return $response;
		}
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : $request->get_params();
		$type   = isset( $params['service_type'] ) ? sanitize_key( (string) $params['service_type'] ) : '';
		$date   = isset( $params['service_date'] ) ? sanitize_text_field( (string) $params['service_date'] ) : '';
		$time   = isset( $params['service_time'] ) ? sanitize_text_field( (string) $params['service_time'] ) : '';
		if ( '' === $type && function_exists( 'rpress_get_default_enabled_service' ) ) {
			$type = (string) rpress_get_default_enabled_service();
		}
		$is_asap = self::is_asap_choice( $date, $time );
		$ts      = time();
		if ( ! $is_asap ) {
			// A slot range ("7:30 PM - 8:00 PM") counts from its start.
			$start = preg_replace( '/\s+-\s+.*$/', '', $time );
			$ts    = self::slot_timestamp( $start, $date );
			if ( false === $ts ) {
				$ts = rpress_get_wp_timestamp( ( '' !== $date ? $date : rpress_get_wp_now()->format( 'Y-m-d' ) ) . ' 00:00' );
			}
		}
		$message = self::service_block_message( $type, (int) $ts, $is_asap );
		if ( '' === $message ) {
			return $response;
		}
		return new WP_Error( self::ERROR_CODE, $message, array( 'status' => 409 ) );
	}

	/* --------------------------------------------------------------------
	 * Storefront banner
	 * ------------------------------------------------------------------ */

	/**
	 * Banner text for the current state, empty when nothing to say.
	 *
	 * @return string
	 */
	public static function banner_message() {
		$state = self::get_state();
		$parts = array();
		if ( $state['paused'] ) {
			if ( $state['until'] ) {
				/* translators: %s: time ordering resumes, e.g. 7:30 PM */
				$parts[] = sprintf( __( 'We are not taking orders right now. Back at %s.', 'restropress' ), self::format_time( $state['until'] ) );
			} else {
				$parts[] = __( 'We are not taking orders right now. Please check back soon.', 'restropress' );
			}
			if ( rpress_is_preorder_enabled( self::selected_service_type() ) ) {
				$parts[] = __( 'You can still schedule an order for later.', 'restropress' );
			}
		} elseif ( $state['extra_prep'] > 0 ) {
			$minutes = absint( rpress_get_option( 'prep_time', 0 ) );
			/* translators: %d: minutes an order currently takes */
			$parts[] = sprintf( _n( 'Busy: orders take about %d minute.', 'Busy: orders take about %d minutes.', $minutes, 'restropress' ), $minutes );
		}
		return (string) apply_filters( 'rpress_pause_orders_banner_message', implode( ' ', $parts ), $state );
	}

	/**
	 * Banner markup (printed once per page).
	 *
	 * @return string
	 */
	public static function get_banner_html() {
		if ( self::$banner_printed ) {
			return '';
		}
		$message = self::banner_message();
		if ( '' === $message ) {
			return '';
		}
		self::$banner_printed = true;
		$state = self::get_state();
		$class = $state['paused'] ? 'rp-pause-banner is-paused' : 'rp-pause-banner is-busy';
		return '<div class="' . esc_attr( $class ) . '" role="status"><span class="rp-pause-banner-icon" aria-hidden="true"></span><span class="rp-pause-banner-text">' . esc_html( $message ) . '</span></div>';
	}

	/**
	 * Prepend the banner to the menu and checkout shortcodes.
	 *
	 * @param string $output Shortcode output.
	 * @param string $tag    Shortcode tag.
	 * @return string
	 */
	public static function filter_shortcode_output( $output, $tag ) {
		$tags = array(
			apply_filters( 'fooditems_shortcode_tag', 'fooditems' ),
			apply_filters( 'fooditem_checkout_shortcode_tag', 'fooditem_checkout' ),
		);
		if ( is_admin() || ! in_array( $tag, $tags, true ) ) {
			return $output;
		}
		return self::get_banner_html() . $output;
	}

	/**
	 * Prepend the banner to the menu and checkout blocks.
	 *
	 * @param string $content Block output.
	 * @param array  $block   Block data.
	 * @return string
	 */
	public static function filter_block_output( $content, $block ) {
		if ( is_admin() || empty( $block['blockName'] ) || ! in_array( $block['blockName'], array( 'rpress/food-menu', 'rpress/checkout' ), true ) ) {
			return $content;
		}
		return self::get_banner_html() . $content;
	}

	/**
	 * Storefront assets. The banner CSS loads only while paused or busy; the
	 * small add-to-cart guard rides along with the core frontend script so a
	 * pause that starts after the page loaded is still reported properly.
	 */
	public static function enqueue_frontend() {
		$base  = RP_PLUGIN_DIR . 'assets/features/pause-orders/';
		$url   = RP_PLUGIN_URL . 'assets/features/pause-orders/';
		$state = self::get_state();
		if ( $state['paused'] || $state['extra_prep'] ) {
			wp_enqueue_style( 'rpress-pause-orders', $url . 'pause-orders.css', array(), self::asset_version( $base . 'pause-orders.css' ) );
		}
		if ( wp_script_is( 'rp-frontend', 'enqueued' ) ) {
			wp_enqueue_script( 'rpress-pause-orders', $url . 'pause-orders.js', array( 'jquery', 'rp-frontend' ), self::asset_version( $base . 'pause-orders.js' ), true );
			wp_localize_script(
				'rpress-pause-orders',
				'rpressPauseOrders',
				array(
					'errorTitle' => __( 'Error', 'restropress' ),
				)
			);
		}
	}

	/**
	 * Cache-busting version for an asset.
	 *
	 * @param string $path Absolute file path.
	 * @return string
	 */
	protected static function asset_version( $path ) {
		return file_exists( $path ) ? RP_VERSION . '.' . filemtime( $path ) : RP_VERSION;
	}

	/* --------------------------------------------------------------------
	 * Admin (Live Orders)
	 * ------------------------------------------------------------------ */

	/**
	 * Capability needed to pause orders (same as the Live Orders screen).
	 *
	 * @return string
	 */
	public static function capability() {
		return (string) apply_filters( 'rpress_pause_orders_capability', 'edit_shop_payments' );
	}

	/**
	 * Whether the current admin screen is Live Orders (mirrors RP_Admin_Assets).
	 *
	 * @return bool
	 */
	protected static function is_live_orders_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'restropress_page_rpress-payment-history' !== $screen->id ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen routing only.
		$view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : '';
		return 'live' === $view || ( '' === $view && rpress_get_option( 'live_orders_default_view' ) );
	}

	/**
	 * Admin assets on the Live Orders screen.
	 */
	public static function enqueue_admin() {
		if ( ! self::is_live_orders_screen() || ! current_user_can( self::capability() ) ) {
			return;
		}
		$base = RP_PLUGIN_DIR . 'assets/features/pause-orders/';
		$url  = RP_PLUGIN_URL . 'assets/features/pause-orders/';
		wp_enqueue_style( 'rpress-pause-orders-admin', $url . 'pause-orders-admin.css', array(), self::asset_version( $base . 'pause-orders-admin.css' ) );
		wp_enqueue_script( 'rpress-pause-orders-admin', $url . 'pause-orders-admin.js', array( 'jquery' ), self::asset_version( $base . 'pause-orders-admin.js' ), true );
		wp_localize_script(
			'rpress-pause-orders-admin',
			'rpressPauseOrdersAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'strings' => array(
					'confirmIndefinite' => __( 'Pause new orders until you resume them?', 'restropress' ),
					'error'             => __( 'Could not update. Please reload the page and try again.', 'restropress' ),
				),
			)
		);
	}

	/**
	 * Print the panel on the Live Orders screen.
	 */
	public static function render_admin_panel() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		echo self::get_admin_panel_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
	}

	/**
	 * Panel markup, also returned by the AJAX endpoint.
	 *
	 * @return string
	 */
	public static function get_admin_panel_html() {
		$state = self::get_state();
		ob_start();
		require __DIR__ . '/views/admin-panel.php';
		return (string) ob_get_clean();
	}

	/**
	 * Admin AJAX: pause, resume, busy, get.
	 */
	public static function ajax_update() {
		check_ajax_referer( self::NONCE, 'security' );
		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'restropress' ) ), 403 );
		}

		$op      = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : 'get';
		$minutes = isset( $_POST['minutes'] ) ? absint( wp_unslash( $_POST['minutes'] ) ) : 0;

		switch ( $op ) {
			case 'pause':
				if ( ! in_array( $minutes, self::pause_durations(), true ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid pause length.', 'restropress' ) ), 400 );
				}
				self::pause( $minutes );
				break;
			case 'resume':
				self::resume();
				break;
			case 'busy':
				if ( 0 !== $minutes && ! in_array( $minutes, self::busy_steps(), true ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid prep time.', 'restropress' ) ), 400 );
				}
				self::set_extra_prep( $minutes );
				break;
			case 'get':
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'restropress' ) ), 400 );
		}

		$state = self::get_state();
		wp_send_json_success(
			array(
				'state' => $state,
				'now'   => time(),
				'html'  => self::get_admin_panel_html(),
			)
		);
	}
}

RPRESS_Pause_Orders::init();
