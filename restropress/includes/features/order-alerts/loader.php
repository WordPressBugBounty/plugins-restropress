<?php
/**
 * Missed-order protection.
 *
 * - Persistent new-order alert on the Live Orders board: the sound repeats
 *   until the order is accepted (or rejected) or staff click Acknowledge, the
 *   card pulses and the tab title flashes.
 * - Opt-in desktop notifications (Notification API) while the board is open.
 * - Unattended-order escalation: one email per order to the admin notification
 *   recipients when a new order stays unaccepted for M minutes. Checked by a
 *   5-minute WP-Cron event; board polls only queue an extra single cron run,
 *   they never send mail themselves. On by default for new stores only;
 *   existing stores get a one-time notice on the Settings screen. Only orders
 *   placed after the email was switched on, and due within the Live Orders
 *   lookahead, are escalated.
 * - Print status per order (`_rpress_print_status`), a printer badge on each
 *   live card, optional auto-print of new orders from the board and a visible
 *   "Print failed, Retry" action.
 *
 * Desktop notifications, the repeating sound and auto-print run in the browser,
 * so they only work while a Live Orders tab is open. Real background push is
 * out of scope; the escalation email covers the "nobody is watching" case.
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/functions.php';

/**
 * Missed-order protection feature.
 */
class RPRESS_Order_Alerts {

	/**
	 * Nonce action for the feature's admin AJAX endpoint.
	 */
	const NONCE = 'rpress_order_alerts';

	/**
	 * WP-Cron hook for the unattended-order check.
	 */
	const CRON_HOOK = 'rpress_order_alerts_escalation_check';

	/**
	 * Cron schedule key (every 5 minutes).
	 */
	const CRON_SCHEDULE = 'rpress_every_five_minutes';

	/**
	 * Order meta set once the unattended-order email went out (value: timestamp).
	 */
	const META_ESCALATED = '_rpress_unattended_alert_sent';

	/**
	 * Order meta used to claim an auto-print so two open boards do not both print.
	 */
	const META_PRINT_CLAIM = '_rpress_print_claim';

	/**
	 * Transient that throttles the escalation check triggered by board polls.
	 */
	const THROTTLE_KEY = 'rpress_order_alerts_last_check';

	/**
	 * Single WP-Cron event queued by board polls (kept apart from CRON_HOOK so
	 * the duplicate-event guard of the recurring event does not swallow it).
	 */
	const POLL_CRON_HOOK = 'rpress_order_alerts_poll_check';

	/**
	 * Option: 'new' or 'existing', recorded the first time the feature loads.
	 */
	const OPTION_INSTALL = 'rpress_order_alerts_install';

	/**
	 * Option: Unix time the unattended-order email was switched on. Only
	 * orders created after it are escalated.
	 */
	const OPTION_ENABLED_AT = 'rpress_order_alerts_enabled_at';

	/**
	 * Option: set once the "turn on the email" notice was used or dismissed.
	 */
	const OPTION_NOTICE = 'rpress_order_alerts_notice_dismissed';

	/**
	 * Register hooks.
	 */
	public static function init() {
		self::maybe_record_install();

		add_filter( 'rpress_registered_settings', array( __CLASS__, 'register_settings' ), 20 );
		add_action( 'update_option_rpress_settings', array( __CLASS__, 'settings_updated' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'render_enable_notice' ) );
		add_action( 'admin_post_rpress_order_alerts_notice', array( __CLASS__, 'handle_enable_notice' ) );

		// Escalation: WP-Cron every 5 minutes plus every board poll (throttled).
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedule' ) );
		add_action( 'init', array( __CLASS__, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_check' ) );
		add_action( self::POLL_CRON_HOOK, array( __CLASS__, 'cron_check' ) );
		add_action( 'wp_ajax_rpress_live_orders_refresh', array( __CLASS__, 'maybe_check_on_poll' ), 1 );
		if ( defined( 'RP_PLUGIN_FILE' ) ) {
			register_deactivation_hook( RP_PLUGIN_FILE, array( __CLASS__, 'unschedule_cron' ) );
		}

		// Live Orders board.
		add_action( 'rpress_live_orders_card_before_footer', array( __CLASS__, 'render_card_row' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ), 20 );
		add_action( 'wp_ajax_rpress_order_alerts_print', array( __CLASS__, 'ajax_print' ) );
	}

	/* --------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * Add the feature settings to General > Live orders.
	 *
	 * @param array $settings Registered settings.
	 * @return array
	 */
	public static function register_settings( $settings ) {
		if ( ! isset( $settings['general'] ) || ! is_array( $settings['general'] ) ) {
			return $settings;
		}
		if ( ! isset( $settings['general']['live_orders'] ) || ! is_array( $settings['general']['live_orders'] ) ) {
			$settings['general']['live_orders'] = array();
		}

		$settings['general']['live_orders'] = array_merge(
			$settings['general']['live_orders'],
			array(
				'order_alerts_header'             => array(
					'id'   => 'order_alerts_header',
					'type' => 'header',
					'name' => '<h3>' . esc_html__( 'Missed-order protection', 'restropress' ) . '</h3>',
				),
				'order_alerts_repeat_interval'    => array(
					'id'   => 'order_alerts_repeat_interval',
					'name' => esc_html__( 'Repeat new-order sound every', 'restropress' ),
					'desc' => esc_html__( 'Seconds between repeats of the new-order sound on the Live Orders screen. The sound keeps repeating until the order is accepted or rejected, or someone clicks Acknowledge. Allowed range: 5 to 300.', 'restropress' ),
					'type' => 'number',
					'size' => 'small',
					'min'  => 5,
					'max'  => 300,
					'std'  => 10,
				),
				'order_alerts_escalation_email'   => array(
					'id'      => 'order_alerts_escalation_email',
					'name'    => esc_html__( 'Unattended-order email', 'restropress' ),
					'desc'    => esc_html__( 'Email the admin notification recipients once when a new order has not been accepted in time. Works even when no Live Orders screen is open.', 'restropress' ),
					'type'    => 'select',
					'options' => array(
						'on'  => esc_html__( 'On', 'restropress' ),
						'off' => esc_html__( 'Off', 'restropress' ),
					),
					'std'     => self::escalation_default(),
				),
				'order_alerts_escalation_minutes' => array(
					'id'   => 'order_alerts_escalation_minutes',
					'name' => esc_html__( 'Send the email after', 'restropress' ),
					'desc' => esc_html__( 'Minutes a new order can wait unaccepted before the unattended-order email is sent. Allowed range: 1 to 240.', 'restropress' ),
					'type' => 'number',
					'size' => 'small',
					'min'  => 1,
					'max'  => 240,
					'std'  => 5,
				),
				'order_alerts_auto_print'         => array(
					'id'   => 'order_alerts_auto_print',
					'name' => esc_html__( 'Auto-print new orders', 'restropress' ),
					'desc' => esc_html__( 'Print the receipt automatically when a new order arrives while the Live Orders screen is open (needs printing enabled under Printing). Each card shows whether its receipt was printed.', 'restropress' ),
					'type' => 'checkbox',
				),
			)
		);

		return $settings;
	}

	/**
	 * Seconds between repeats of the new-order sound.
	 *
	 * @return int
	 */
	public static function repeat_interval() {
		$value = (int) rpress_get_option( 'order_alerts_repeat_interval', 10 );
		$value = $value > 0 ? $value : 10;
		return (int) apply_filters( 'rpress_order_alerts_repeat_interval', max( 5, min( 300, $value ) ) );
	}

	/**
	 * Minutes an order may wait unaccepted before escalation.
	 *
	 * @return int
	 */
	public static function escalation_minutes() {
		$value = (int) rpress_get_option( 'order_alerts_escalation_minutes', 5 );
		$value = $value > 0 ? $value : 5;
		return (int) apply_filters( 'rpress_order_alerts_escalation_minutes', max( 1, min( 240, $value ) ) );
	}

	/**
	 * Whether the unattended-order email is enabled.
	 *
	 * @return bool
	 */
	public static function escalation_enabled() {
		$enabled = 'on' === (string) rpress_get_option( 'order_alerts_escalation_email', self::escalation_default() );
		return (bool) apply_filters( 'rpress_order_alerts_escalation_enabled', $enabled );
	}

	/**
	 * Default for the unattended-order email: on for stores installed with
	 * this feature, off for stores that existed before it (they get a notice).
	 *
	 * @return string 'on' or 'off'.
	 */
	public static function escalation_default() {
		return 'new' === get_option( self::OPTION_INSTALL ) ? 'on' : 'off';
	}

	/**
	 * Record once whether this is a new install. Runs when the feature file
	 * loads, which on a fresh install is before the activation routine writes
	 * `rpress_version`, so a missing version means a brand new store.
	 */
	public static function maybe_record_install() {
		if ( false !== get_option( self::OPTION_INSTALL, false ) ) {
			return;
		}
		$is_new = false === get_option( 'rpress_version', false );
		add_option( self::OPTION_INSTALL, $is_new ? 'new' : 'existing' );
		if ( $is_new ) {
			update_option( self::OPTION_ENABLED_AT, time(), false );
		}
	}

	/**
	 * Unix time the unattended-order email was switched on (0 when unknown).
	 *
	 * @return int
	 */
	public static function enabled_at() {
		return (int) get_option( self::OPTION_ENABLED_AT, 0 );
	}

	/**
	 * Stamp the enable time when the email setting goes from off to on.
	 *
	 * @param mixed $old_value Previous rpress_settings.
	 * @param mixed $new_value New rpress_settings.
	 */
	public static function settings_updated( $old_value, $new_value ) {
		$key = 'order_alerts_escalation_email';
		$def = self::escalation_default();
		$was = is_array( $old_value ) && isset( $old_value[ $key ] ) ? (string) $old_value[ $key ] : $def;
		$is  = is_array( $new_value ) && isset( $new_value[ $key ] ) ? (string) $new_value[ $key ] : $def;
		if ( 'on' === $is && 'on' !== $was ) {
			update_option( self::OPTION_ENABLED_AT, time(), false );
		}
	}

	/**
	 * Whether the one-time "turn on the email" notice should show.
	 *
	 * @return bool
	 */
	public static function show_enable_notice() {
		return current_user_can( 'manage_shop_settings' )
			&& 'existing' === get_option( self::OPTION_INSTALL )
			&& ! self::escalation_enabled()
			&& ! get_option( self::OPTION_NOTICE );
	}

	/**
	 * Notice on the RestroPress Settings screen offering the unattended-order email.
	 */
	public static function render_enable_notice() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
		if ( 'rpress-settings' !== $page ) {
			return;
		}
		if ( isset( $_GET['rpress_order_alerts_enabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- display only.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Unattended-order email is on. New orders not accepted in time will be emailed to the admin notification recipients.', 'restropress' ) . '</p></div>';
			return;
		}
		if ( ! self::show_enable_notice() ) {
			return;
		}
		$enable_url  = wp_nonce_url( admin_url( 'admin-post.php?action=rpress_order_alerts_notice&do=enable' ), 'rpress-order-alerts-notice' );
		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=rpress_order_alerts_notice&do=dismiss' ), 'rpress-order-alerts-notice' );
		?>
		<div class="notice notice-info rpress-order-alerts-notice">
			<p><strong><?php esc_html_e( 'Get an email when a new order is not accepted in time', 'restropress' ); ?></strong></p>
			<p><?php esc_html_e( 'If a new order waits unaccepted for a few minutes, RestroPress can email the admin notification recipients once, even when no Live Orders screen is open. Only orders placed after you switch it on are checked.', 'restropress' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $enable_url ); ?>"><?php esc_html_e( 'Enable unattended-order email', 'restropress' ); ?></a>
				<a class="button-link" style="margin-left:12px" href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'No thanks', 'restropress' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Enable / dismiss from the notice.
	 */
	public static function handle_enable_notice() {
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to change RestroPress settings.', 'restropress' ), 403 );
		}
		check_admin_referer( 'rpress-order-alerts-notice' );

		$do       = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$redirect = admin_url( 'admin.php?page=rpress-settings' );
		if ( 'enable' === $do ) {
			rpress_update_option( 'order_alerts_escalation_email', 'on' );
			update_option( self::OPTION_ENABLED_AT, time(), false );
			$redirect = add_query_arg( 'rpress_order_alerts_enabled', '1', $redirect );
		}
		// Either way the notice has done its job.
		update_option( self::OPTION_NOTICE, time(), false );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Whether the order's service date and time is further away than the Live
	 * Orders lookahead (same filter and default as RP_Live_Orders::get_orders).
	 * ASAP orders and orders without a parsable service time count as due now.
	 *
	 * @param int $order_id Order ID.
	 * @param int $now      Unix time of the check.
	 * @return bool
	 */
	public static function is_service_too_far( $order_id, $now ) {
		$date = trim( (string) get_post_meta( $order_id, '_rpress_delivery_date', true ) );
		$time = (string) get_post_meta( $order_id, '_rpress_delivery_time', true );
		if ( '' === $date || false !== stripos( $time, 'ASAP' ) ) {
			return false;
		}
		$parts = preg_split( '/\s*[\-\x{2013}]\s*/u', $time );
		$start = trim( isset( $parts[0] ) ? $parts[0] : '' );
		if ( '' === $start ) {
			return false;
		}
		$due = date_create( $date . ' ' . $start, wp_timezone() );
		if ( ! $due ) {
			return false;
		}
		$lookahead = (int) apply_filters( 'rpress_live_orders_lookahead', 3 * HOUR_IN_SECONDS );
		return $due->getTimestamp() > (int) $now + $lookahead;
	}

	/**
	 * Whether auto-print from the board is enabled (and printing is on).
	 *
	 * @return bool
	 */
	public static function auto_print_enabled() {
		return self::printing_enabled() && (bool) rpress_get_option( 'order_alerts_auto_print', false );
	}

	/**
	 * Whether receipt printing is enabled at all.
	 *
	 * @return bool
	 */
	public static function printing_enabled() {
		return class_exists( 'RPRESS_Print_Receipts' ) && RPRESS_Print_Receipts::is_printing_enabled();
	}

	/**
	 * Current Unix time. Filterable so tests can move the clock.
	 *
	 * @return int
	 */
	public static function now() {
		return (int) apply_filters( 'rpress_order_alerts_now', time() );
	}

	/**
	 * Capability for the Live Orders screen and the feature AJAX.
	 *
	 * @return string
	 */
	public static function capability() {
		return (string) apply_filters( 'rpress_order_alerts_capability', 'edit_shop_payments' );
	}

	/**
	 * Payment (post) statuses that count as real orders for alerts. Unpaid
	 * online checkouts (`pending`) are left out so abandoned payments do not
	 * ring the kitchen or trigger emails; cash orders are `processing`.
	 *
	 * @return array
	 */
	public static function alert_payment_statuses() {
		return (array) apply_filters( 'rpress_order_alerts_payment_statuses', array( 'publish', 'processing' ) );
	}

	/**
	 * Whether an order should raise new-order alerts right now.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public static function is_alertable( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id || 'rpress_payment' !== get_post_type( $order_id ) ) {
			return false;
		}
		if ( 'pending' !== rpress_get_order_status( $order_id ) ) {
			return false;
		}
		return in_array( get_post_status( $order_id ), self::alert_payment_statuses(), true );
	}

	/* --------------------------------------------------------------------
	 * Escalation
	 * ------------------------------------------------------------------ */

	/**
	 * Add the 5-minute cron schedule.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function add_cron_schedule( $schedules ) {
		if ( ! isset( $schedules[ self::CRON_SCHEDULE ] ) ) {
			$schedules[ self::CRON_SCHEDULE ] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 minutes', 'restropress' ),
			);
		}
		return $schedules;
	}

	/**
	 * Schedule the cron check once.
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * Remove the cron event (plugin deactivation).
	 */
	public static function unschedule_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::POLL_CRON_HOOK );
	}

	/**
	 * Cron callback.
	 */
	public static function cron_check() {
		self::run_escalation_check();
	}

	/**
	 * Queue an escalation check from a board poll, at most once a minute.
	 * Mail is never sent inside the poll request: a single cron event is
	 * scheduled instead (deduped). Runs before the core refresh handler
	 * (which exits); never sends output.
	 *
	 * @return bool Whether a check was queued.
	 */
	public static function maybe_check_on_poll() {
		if ( ! check_ajax_referer( 'rp_live_orders_refresh', 'security', false ) || ! current_user_can( self::capability() ) ) {
			return false;
		}
		if ( ! self::escalation_enabled() || get_transient( self::THROTTLE_KEY ) ) {
			return false;
		}
		set_transient( self::THROTTLE_KEY, 1, MINUTE_IN_SECONDS );
		if ( wp_next_scheduled( self::POLL_CRON_HOOK ) ) {
			return false;
		}
		return true === wp_schedule_single_event( time(), self::POLL_CRON_HOOK );
	}

	/**
	 * Find new orders waiting longer than the escalation window and email the
	 * admin recipients once per order.
	 *
	 * @param int|null $now Unix time to evaluate against (defaults to self::now()).
	 * @return int[] Order IDs that were escalated in this run.
	 */
	public static function run_escalation_check( $now = null ) {
		if ( ! self::escalation_enabled() ) {
			return array();
		}

		$now      = null === $now ? self::now() : (int) $now;
		$minutes  = self::escalation_minutes();
		$lookback = max( 1, (int) apply_filters( 'rpress_order_alerts_escalation_lookback_hours', 24 ) );
		$cutoff   = $now - $minutes * MINUTE_IN_SECONDS;

		// Never escalate orders placed before the email was switched on. A store
		// enabled without a recorded time (e.g. through a filter) starts now.
		$enabled_at = self::enabled_at();
		if ( ! $enabled_at ) {
			$enabled_at = $now;
			update_option( self::OPTION_ENABLED_AT, $enabled_at, false );
		}
		$after = max( $now - $lookback * HOUR_IN_SECONDS, $enabled_at );
		if ( $after > $cutoff ) {
			return array();
		}

		// post_date (site time) rather than post_date_gmt: WordPress leaves the
		// GMT date zeroed on posts saved with the `pending` status.
		$args = array(
			'post_type'              => 'rpress_payment',
			'post_status'            => self::alert_payment_statuses(),
			'posts_per_page'         => (int) apply_filters( 'rpress_order_alerts_escalation_batch', 50 ),
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
			'date_query'             => array(
				array(
					'column'    => 'post_date',
					'after'     => wp_date( 'Y-m-d H:i:s', $after ),
					'before'    => wp_date( 'Y-m-d H:i:s', $cutoff ),
					'inclusive' => true,
				),
			),
			'meta_query'             => array(
				array(
					'key'     => self::META_ESCALATED,
					'compare' => 'NOT EXISTS',
				),
			),
		);

		/**
		 * Filter the query that finds orders to escalate.
		 *
		 * @since 3.5
		 * @param array $args WP_Query args.
		 * @param int   $now  Unix time of the check.
		 */
		$args = apply_filters( 'rpress_order_alerts_escalation_query_args', $args, $now );

		// Orders skipped for now (e.g. pre-orders due later) stay unflagged, so
		// page past them instead of letting them fill every batch.
		$batch = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 50;
		$base  = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
		$ids   = array();
		for ( $page = 0; $page < 10; $page++ ) {
			if ( $ids ) {
				$args['post__not_in'] = array_merge( $base, $ids );
			}
			$query = new WP_Query( $args );
			$fresh = array_diff( array_map( 'intval', $query->posts ), $ids );
			if ( ! $fresh ) {
				break;
			}
			$ids = array_merge( $ids, $fresh );
			if ( $batch < 1 || count( $query->posts ) < $batch ) {
				break;
			}
		}

		$sent = array();
		foreach ( $ids as $order_id ) {
			$order_id = (int) $order_id;
			if ( ! self::is_alertable( $order_id ) ) {
				continue;
			}
			$created = get_post_datetime( $order_id );
			if ( ! $created || $created->getTimestamp() > $cutoff || $created->getTimestamp() < $enabled_at ) {
				continue;
			}
			if ( self::is_service_too_far( $order_id, $now ) ) {
				continue;
			}
			// Claim first so a concurrent cron run and board poll cannot both send.
			if ( ! add_post_meta( $order_id, self::META_ESCALATED, $now, true ) ) {
				continue;
			}
			$ok = self::send_escalation_email( $order_id, $minutes );
			rpress_insert_payment_note(
				$order_id,
				$ok
					? sprintf(
						/* translators: %d: minutes. */
						__( 'Order not accepted within %d minutes: unattended-order email sent to the admin recipients.', 'restropress' ),
						$minutes
					)
					: __( 'Order not accepted in time: the unattended-order email could not be sent.', 'restropress' )
			);
			/**
			 * Fires after an unattended order was escalated.
			 *
			 * @since 3.5
			 * @param int  $order_id Order ID.
			 * @param bool $ok       Whether the email was sent.
			 * @param int  $minutes  Escalation window in minutes.
			 */
			do_action( 'rpress_order_alerts_escalated', $order_id, $ok, $minutes );
			$sent[] = $order_id;
		}

		return $sent;
	}

	/**
	 * Formatted order number (matches the Live Orders card).
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function order_number( $order_id ) {
		$seq = get_post_meta( $order_id, '_rpress_payment_number', true );
		if ( rpress_get_option( 'enable_sequential' ) && $seq ) {
			return '#' . $seq;
		}
		return '#' . rpress_get_option( 'sequential_prefix' ) . $order_id . rpress_get_option( 'sequential_postfix' );
	}

	/**
	 * Send the unattended-order email through the RestroPress email system.
	 *
	 * @param int $order_id Order ID.
	 * @param int $minutes  Escalation window in minutes.
	 * @return bool
	 */
	public static function send_escalation_email( $order_id, $minutes ) {
		$payment = rpress_get_payment( $order_id );
		if ( ! $payment ) {
			return false;
		}

		$number   = self::order_number( $order_id );
		$service  = rpress_service_label( rpress_get_service_type( $order_id ) );
		$items    = 0;
		$lines    = array();
		foreach ( (array) $payment->cart_details as $row ) {
			$qty    = isset( $row['quantity'] ) ? (int) $row['quantity'] : 1;
			$items += $qty;
			if ( ! empty( $row['name'] ) ) {
				$lines[] = $qty . ' x ' . wp_strip_all_tags( $row['name'] );
			}
		}
		$total    = html_entity_decode( wp_strip_all_tags( rpress_currency_filter( rpress_format_amount( $payment->total ), rpress_get_payment_currency_code( $order_id ) ) ), ENT_QUOTES, 'UTF-8' );
		$customer = trim( $payment->first_name . ' ' . $payment->last_name );
		$customer = '' !== $customer ? $customer : __( 'Guest', 'restropress' );
		$board    = admin_url( 'admin.php?page=rpress-payment-history&view=live' );
		$details  = admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details&id=' . $order_id );

		$subject = sprintf(
			/* translators: 1: order number, 2: minutes. */
			__( 'Order %1$s has not been accepted for %2$d minutes', 'restropress' ),
			$number,
			$minutes
		);
		$subject = apply_filters( 'rpress_order_alerts_escalation_subject', $subject, $order_id, $minutes );

		$message  = '<p>' . esc_html(
			sprintf(
				/* translators: 1: order number, 2: minutes. */
				__( 'New order %1$s is still waiting to be accepted after %2$d minutes. Please check the Live Orders screen.', 'restropress' ),
				$number,
				$minutes
			)
		) . '</p>';
		$message .= '<p>';
		$message .= '<strong>' . esc_html__( 'Customer:', 'restropress' ) . '</strong> ' . esc_html( $customer ) . '<br>';
		$message .= '<strong>' . esc_html__( 'Service:', 'restropress' ) . '</strong> ' . esc_html( $service ) . '<br>';
		/* translators: %d: number of items. */
		$message .= '<strong>' . esc_html__( 'Items:', 'restropress' ) . '</strong> ' . esc_html( sprintf( _n( '%d item', '%d items', $items, 'restropress' ), $items ) ) . '<br>';
		$message .= '<strong>' . esc_html__( 'Total:', 'restropress' ) . '</strong> ' . esc_html( $total );
		$message .= '</p>';
		if ( $lines ) {
			$message .= '<p>' . implode( '<br>', array_map( 'esc_html', array_slice( $lines, 0, 20 ) ) ) . '</p>';
		}
		$message .= '<p><a href="' . esc_url( $board ) . '">' . esc_html__( 'Open Live Orders', 'restropress' ) . '</a> | <a href="' . esc_url( $details ) . '">' . esc_html__( 'View order', 'restropress' ) . '</a></p>';
		$message  = apply_filters( 'rpress_order_alerts_escalation_message', $message, $order_id, $minutes );

		$to = apply_filters( 'rpress_order_alerts_escalation_recipients', rpress_get_admin_notice_emails(), $order_id );
		$to = array_filter( array_map( 'sanitize_email', (array) $to ) );
		if ( ! $to ) {
			return false;
		}

		$emails = RPRESS()->emails;
		$emails->__set( 'heading', __( 'Order waiting to be accepted', 'restropress' ) );
		$emails->__set( 'headers', $emails->get_headers() );
		return (bool) $emails->send( $to, $subject, $message );
	}

	/* --------------------------------------------------------------------
	 * Live Orders board
	 * ------------------------------------------------------------------ */

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
	 * Asset version from file mtime.
	 *
	 * @param string $path File path.
	 * @return string
	 */
	protected static function asset_version( $path ) {
		return file_exists( $path ) ? RP_VERSION . '.' . filemtime( $path ) : RP_VERSION;
	}

	/**
	 * Enqueue the board assets on the Live Orders screen only.
	 */
	public static function enqueue_admin() {
		if ( ! self::is_live_orders_screen() || ! current_user_can( self::capability() ) ) {
			return;
		}
		$base = RP_PLUGIN_DIR . 'assets/features/order-alerts/';
		$url  = RP_PLUGIN_URL . 'assets/features/order-alerts/';
		wp_enqueue_style( 'rpress-order-alerts', $url . 'order-alerts.css', array( 'rpress-admin-live-orders' ), self::asset_version( $base . 'order-alerts.css' ) );
		wp_enqueue_script( 'rpress-order-alerts', $url . 'order-alerts.js', array( 'jquery', 'rpress-admin-live-orders' ), self::asset_version( $base . 'order-alerts.js' ), true );
		wp_localize_script(
			'rpress-order-alerts',
			'rpressOrderAlerts',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( self::NONCE ),
				'printNonce'     => wp_create_nonce( 'rpress-order' ),
				'repeatInterval' => self::repeat_interval(),
				'autoPrint'      => self::auto_print_enabled(),
				'icon'           => esc_url_raw( (string) rpress_get_option( 'notification_icon', '' ) ),
				'strings'        => array(
					/* translators: %d: number of new orders. */
					'titleOne'          => __( '(%d) New order', 'restropress' ),
					/* translators: %d: number of new orders. */
					'titleMany'         => __( '(%d) New orders', 'restropress' ),
					/* translators: %s: order number. */
					'notifyTitle'       => __( 'New order %s', 'restropress' ),
					'notifyEnable'      => __( 'Enable desktop notifications', 'restropress' ),
					'notifyOn'          => __( 'Desktop notifications on', 'restropress' ),
					'notifyOff'         => __( 'Desktop notifications off', 'restropress' ),
					'notifyBlocked'     => __( 'Notifications blocked', 'restropress' ),
					'notifyBlockedHelp' => __( 'Notifications are blocked for this site. Allow them in your browser site settings, then reload this page.', 'restropress' ),
					'notifyHelp'        => __( 'Shows a desktop notification for new orders while this Live Orders tab is open in the background.', 'restropress' ),
					'printFailed'       => __( 'Print failed', 'restropress' ),
					'printing'          => __( 'Printing...', 'restropress' ),
					'printEmpty'        => __( 'The receipt was empty.', 'restropress' ),
					'printLoad'         => __( 'The receipt could not be loaded.', 'restropress' ),
					'printError'        => __( 'The browser could not open the print dialog.', 'restropress' ),
				),
			)
		);
	}

	/**
	 * Card row on each live card: printer badge and, for new orders, the
	 * Acknowledge button. Echoed inside RP_Live_Orders::render_card().
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $order_status Order status.
	 */
	public static function render_card_row( $order_id, $order_status ) {
		$order_id  = absint( $order_id );
		$alertable = 'pending' === $order_status && self::is_alertable( $order_id );
		$printing  = self::printing_enabled();
		if ( ! $alertable && ! $printing ) {
			return;
		}
		$print     = rpress_get_order_print_status( $order_id );
		$auto      = $printing && self::auto_print_enabled() && 'not_attempted' === $print['status'];
		?>
		<div class="rp-oa-row" data-order-id="<?php echo (int) $order_id; ?>" data-oa-alert="<?php echo $alertable ? '1' : '0'; ?>" data-oa-autoprint="<?php echo $auto ? '1' : '0'; ?>">
			<?php
			if ( $printing ) {
				echo self::get_print_badge_html( $order_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			}
			?>
			<?php if ( $alertable ) : ?>
				<button type="button" class="button rp-oa-ack" data-order-id="<?php echo (int) $order_id; ?>" title="<?php esc_attr_e( 'Stop the alert sound for this order on this screen', 'restropress' ); ?>">
					<span class="dashicons dashicons-bell" aria-hidden="true"></span>
					<?php esc_html_e( 'Acknowledge', 'restropress' ); ?>
				</button>
				<span class="rp-oa-acked" hidden><?php esc_html_e( 'Acknowledged', 'restropress' ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Printer badge markup for a card.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_print_badge_html( $order_id ) {
		$print = rpress_get_order_print_status( $order_id );
		$time  = $print['time'] ? wp_date( get_option( 'time_format', 'g:i a' ), $print['time'] ) : '';

		switch ( $print['status'] ) {
			case 'printed':
				/* translators: %s: time. */
				$label  = $time ? sprintf( __( 'Printed %s', 'restropress' ), $time ) : __( 'Printed', 'restropress' );
				$action = __( 'Reprint', 'restropress' );
				break;
			case 'failed':
				$label  = __( 'Print failed', 'restropress' );
				$action = __( 'Retry', 'restropress' );
				break;
			default:
				$label  = __( 'Not printed', 'restropress' );
				$action = __( 'Print', 'restropress' );
				break;
		}

		$title = $label;
		if ( 'printed' === $print['status'] ) {
			$title = __( 'Sent to the printer from the browser print dialog. The browser cannot confirm that paper came out.', 'restropress' );
		} elseif ( 'failed' === $print['status'] && '' !== $print['message'] ) {
			$title = $print['message'];
		}

		ob_start();
		?>
		<span class="rp-oa-print rp-oa-print--<?php echo esc_attr( $print['status'] ); ?>" data-order-id="<?php echo (int) $order_id; ?>" data-print-status="<?php echo esc_attr( $print['status'] ); ?>">
			<span class="rp-oa-print-label" title="<?php echo esc_attr( $title ); ?>">
				<span class="dashicons dashicons-printer" aria-hidden="true"></span>
				<?php echo esc_html( $label ); ?>
			</span>
			<button type="button" class="rp-oa-print-action" data-order-id="<?php echo (int) $order_id; ?>"><?php echo esc_html( $action ); ?></button>
		</span>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * AJAX: claim an auto-print or record a print result.
	 *
	 * POST: security, order_id, op (claim|status), status (printed|failed), message.
	 */
	public static function ajax_print() {
		check_ajax_referer( self::NONCE, 'security' );
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		if ( ! $order_id || ! current_user_can( self::capability(), $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'restropress' ) ), 403 );
		}
		if ( 'rpress_payment' !== get_post_type( $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'restropress' ) ), 404 );
		}
		if ( ! self::printing_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Printing is disabled.', 'restropress' ) ), 400 );
		}

		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

		if ( 'claim' === $op ) {
			wp_send_json_success( array( 'claimed' => rpress_claim_order_auto_print( $order_id ) ) );
		}

		if ( 'status' === $op ) {
			$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
			$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
			if ( ! in_array( $status, array( 'printed', 'failed' ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid print status.', 'restropress' ) ), 400 );
			}
			rpress_set_order_print_status( $order_id, $status, $message, 'board' );
			wp_send_json_success(
				array(
					'status' => $status,
					'html'   => self::get_print_badge_html( $order_id ),
				)
			);
		}

		wp_send_json_error( array( 'message' => __( 'Unknown request.', 'restropress' ) ), 400 );
	}
}

RPRESS_Order_Alerts::init();
