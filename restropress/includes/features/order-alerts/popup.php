<?php
/**
 * New-order popup on every admin screen (3.5.1).
 *
 * Lists every order that is waiting to be accepted, newest first, with Open
 * and Acknowledge per order and a Dismiss for the whole popup. The chime only
 * rings while the popup shows unacknowledged orders, so a ring always has a
 * visible reason. Replaces the old admin-wide notifier in rp-admin.js, which
 * tracked one site-wide "last order" and could miss orders.
 *
 * - Shown to users with the Live Orders capability (edit_shop_payments).
 * - Switched on and off by the "New-order popup and sound" setting
 *   (enable_order_notification). The sound follows the per-browser Sound
 *   switch shared with Live Orders.
 * - Not shown on Live Orders, which has the full board.
 * - Acknowledge is stored on the order, so it counts on every device and on
 *   the Live Orders board.
 * - Orders older than 24 hours (filter rpress_order_alerts_max_age) do not ring.
 *
 * @package RestroPress
 * @since   3.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * New-order popup.
 */
class RPRESS_Order_Popup {

	/**
	 * Order meta set when someone acknowledges the alert: array( user, time ).
	 */
	const META_ACK = '_rpress_order_alert_ack';

	/**
	 * Nonce action for the popup AJAX.
	 */
	const NONCE = 'rpress_order_popup';

	/**
	 * Most orders listed in the popup (the total is still reported).
	 */
	const LIST_LIMIT = 20;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 30 );
		add_action( 'wp_ajax_rpress_order_popup_poll', array( __CLASS__, 'ajax_poll' ) );
		add_action( 'wp_ajax_rpress_order_popup_ack', array( __CLASS__, 'ajax_ack' ) );
	}

	/**
	 * Whether the popup is switched on for the store.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) apply_filters( 'rpress_order_popup_enabled', (bool) rpress_get_option( 'enable_order_notification', false ) );
	}

	/**
	 * Orders older than this many seconds no longer ring or show in the popup.
	 *
	 * @return int
	 */
	public static function max_age() {
		return max( HOUR_IN_SECONDS, (int) apply_filters( 'rpress_order_alerts_max_age', DAY_IN_SECONDS ) );
	}

	/**
	 * Unix time the order was placed.
	 *
	 * @param int $order_id Order ID.
	 * @return int
	 */
	public static function created_at( $order_id ) {
		return (int) get_post_time( 'U', true, $order_id );
	}

	/**
	 * Whether the order is too old to ring.
	 *
	 * @param int      $order_id Order ID.
	 * @param int|null $now      Unix time of the check.
	 * @return bool
	 */
	public static function is_stale( $order_id, $now = null ) {
		$now = null === $now ? RPRESS_Order_Alerts::now() : (int) $now;
		return self::created_at( $order_id ) < $now - self::max_age();
	}

	/**
	 * Whether someone acknowledged the order's alert.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public static function is_acknowledged( $order_id ) {
		return (bool) get_post_meta( absint( $order_id ), self::META_ACK, true );
	}

	/**
	 * Acknowledge an order's alert for everyone.
	 *
	 * @param int $order_id Order ID.
	 * @param int $user_id  User who acknowledged.
	 * @return bool False for an unknown order.
	 */
	public static function acknowledge( $order_id, $user_id = 0 ) {
		$order_id = absint( $order_id );
		if ( ! $order_id || 'rpress_payment' !== get_post_type( $order_id ) ) {
			return false;
		}
		if ( ! self::is_acknowledged( $order_id ) ) {
			update_post_meta(
				$order_id,
				self::META_ACK,
				array(
					'user' => absint( $user_id ),
					'time' => RPRESS_Order_Alerts::now(),
				)
			);
			/**
			 * Fires after a new-order alert was acknowledged.
			 *
			 * @since 3.5.1
			 * @param int $order_id Order ID.
			 * @param int $user_id  User ID.
			 */
			do_action( 'rpress_order_alert_acknowledged', $order_id, absint( $user_id ) );
		}
		return true;
	}

	/**
	 * Whether the order should ring and be listed: new (not accepted yet),
	 * paid or cash, due within the Live Orders lookahead, placed within the
	 * last 24 hours and not acknowledged.
	 *
	 * @param int      $order_id Order ID.
	 * @param int|null $now      Unix time of the check.
	 * @return bool
	 */
	public static function needs_attention( $order_id, $now = null ) {
		$now = null === $now ? RPRESS_Order_Alerts::now() : (int) $now;
		return RPRESS_Order_Alerts::is_alertable( $order_id )
			&& ! self::is_stale( $order_id, $now )
			&& ! RPRESS_Order_Alerts::is_service_too_far( $order_id, $now )
			&& ! self::is_acknowledged( $order_id );
	}

	/**
	 * IDs of the orders that need attention, newest first.
	 *
	 * @param int|null $now Unix time of the check.
	 * @return int[]
	 */
	public static function get_order_ids( $now = null ) {
		$now  = null === $now ? RPRESS_Order_Alerts::now() : (int) $now;
		$args = array(
			'post_type'              => 'rpress_payment',
			'post_status'            => RPRESS_Order_Alerts::alert_payment_statuses(),
			'posts_per_page'         => (int) apply_filters( 'rpress_order_popup_query_limit', 200 ),
			'fields'                 => 'ids',
			'orderby'                => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'date_query'             => array(
				array(
					'column'    => 'post_date_gmt',
					'after'     => gmdate( 'Y-m-d H:i:s', $now - self::max_age() ),
					'inclusive' => true,
				),
			),
		);
		/**
		 * Filter the query that finds orders for the new-order popup.
		 *
		 * @since 3.5.1
		 * @param array $args WP_Query args.
		 * @param int   $now  Unix time of the check.
		 */
		$args = apply_filters( 'rpress_order_popup_query_args', $args, $now );
		$ids  = array();
		foreach ( (array) get_posts( $args ) as $id ) {
			if ( self::needs_attention( (int) $id, $now ) ) {
				$ids[] = (int) $id;
			}
		}
		return $ids;
	}

	/**
	 * What the popup shows for one order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function order_payload( $order_id ) {
		$payment = rpress_get_payment( $order_id );
		$items   = 0;
		$total   = '';
		if ( $payment ) {
			foreach ( (array) $payment->cart_details as $row ) {
				$items += isset( $row['quantity'] ) ? (int) $row['quantity'] : 1;
			}
			$total = html_entity_decode( wp_strip_all_tags( rpress_currency_filter( rpress_format_amount( $payment->total ), rpress_get_payment_currency_code( $order_id ) ) ), ENT_QUOTES, 'UTF-8' );
		}
		$service = rpress_get_service_type( $order_id );
		$time    = trim( (string) get_post_meta( $order_id, '_rpress_delivery_time', true ) );
		$number  = RPRESS_Order_Alerts::order_number( $order_id );
		$label   = $service ? rpress_service_label( $service ) : '';

		/* translators: %d: number of items. */
		$items_label = sprintf( _n( '%d item', '%d items', $items, 'restropress' ), $items );

		return array(
			'id'        => (int) $order_id,
			'number'    => $number,
			'service'   => (string) $label,
			'time'      => $time,
			'items'     => $items_label,
			'total'     => $total,
			'createdAt' => self::created_at( $order_id ),
			'url'       => admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details&id=' . $order_id ),
			'note'      => self::desktop_note( $order_id, $number, $label, $items_label, $total ),
		);
	}

	/**
	 * Desktop notification text, from the notification title and description
	 * settings (with their placeholders) or a plain default.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $number   Order number.
	 * @param string $service  Service label.
	 * @param string $items    Items label.
	 * @param string $total    Total.
	 * @return array { title, body }
	 */
	protected static function desktop_note( $order_id, $number, $service, $items, $total ) {
		$title = trim( (string) rpress_get_option( 'notification_title', '' ) );
		/* translators: %s: order number. */
		$title = '' !== $title ? $title : sprintf( __( 'New order %s', 'restropress' ), $number );

		$body = trim( (string) rpress_get_option( 'notification_body', '' ) );
		if ( '' !== $body ) {
			$date   = (string) get_post_meta( $order_id, '_rpress_delivery_date', true );
			$status = get_post_status( $order_id );
			$body   = str_replace(
				array( '{order_id}', '{service_type}', '{payment_status}', '{service_date}' ),
				array( $order_id, $service, 'publish' === $status ? __( 'Paid', 'restropress' ) : ucfirst( (string) $status ), $date ? rpress_local_date( $date ) : '' ),
				$body
			);
		} else {
			$body = implode( ', ', array_filter( array( $service, $items, $total ) ) );
		}
		return array(
			'title' => wp_strip_all_tags( $title ),
			'body'  => wp_strip_all_tags( $body ),
		);
	}

	/**
	 * Poll reply: the orders to list and how many there are in all.
	 *
	 * @return array
	 */
	public static function poll_payload() {
		$now = RPRESS_Order_Alerts::now();
		$ids = self::get_order_ids( $now );
		return array(
			'orders' => array_map( array( __CLASS__, 'order_payload' ), array_slice( $ids, 0, self::LIST_LIMIT ) ),
			'total'  => count( $ids ),
			'now'    => $now,
		);
	}

	/**
	 * AJAX: orders that need attention.
	 */
	public static function ajax_poll() {
		check_ajax_referer( self::NONCE, 'security' );
		if ( ! current_user_can( RPRESS_Order_Alerts::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'restropress' ) ), 403 );
		}
		wp_send_json_success( self::poll_payload() );
	}

	/**
	 * AJAX: acknowledge one or more orders. POST: security, order_ids[].
	 */
	public static function ajax_ack() {
		check_ajax_referer( self::NONCE, 'security' );
		if ( ! current_user_can( RPRESS_Order_Alerts::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'restropress' ) ), 403 );
		}
		$ids = isset( $_POST['order_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['order_ids'] ) ) : array();
		foreach ( array_slice( array_filter( $ids ), 0, 200 ) as $id ) {
			if ( current_user_can( RPRESS_Order_Alerts::capability(), $id ) ) {
				self::acknowledge( $id, get_current_user_id() );
			}
		}
		wp_send_json_success( self::poll_payload() );
	}

	/**
	 * Load the popup on every admin screen except Live Orders.
	 */
	public static function enqueue() {
		if ( ! self::enabled() || ! current_user_can( RPRESS_Order_Alerts::capability() ) ) {
			return;
		}
		if ( wp_script_is( 'rpress-admin-live-orders', 'enqueued' ) ) {
			return;
		}
		$base = RP_PLUGIN_DIR . 'assets/features/order-alerts/';
		$url  = RP_PLUGIN_URL . 'assets/features/order-alerts/';
		$ver  = function ( $file ) use ( $base ) {
			return file_exists( $base . $file ) ? RP_VERSION . '.' . filemtime( $base . $file ) : RP_VERSION;
		};
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'rpress-order-popup', $url . 'order-popup.css', array(), $ver( 'order-popup.css' ) );
		wp_enqueue_script( 'rpress-order-popup', $url . 'order-popup.js', array( 'jquery' ), $ver( 'order-popup.js' ), true );

		$poll = class_exists( 'RP_Live_Orders' ) && method_exists( 'RP_Live_Orders', 'get_poll_interval' ) ? (int) RP_Live_Orders::get_poll_interval() : 15;
		wp_localize_script(
			'rpress-order-popup',
			'rpressOrderPopup',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( self::NONCE ),
				'pollInterval'   => max( 10, min( 300, $poll ) ),
				'repeatInterval' => RPRESS_Order_Alerts::repeat_interval(),
				'soundUrl'       => esc_url_raw( (string) rpress_get_option( 'notification_sound', '' ) ),
				'soundDuration'  => (int) rpress_get_option( 'notification_duration', 0 ) > 0 ? (int) rpress_get_option( 'notification_duration', 0 ) : 5,
				'icon'           => esc_url_raw( (string) rpress_get_option( 'notification_icon', '' ) ),
				'liveUrl'        => admin_url( 'admin.php?page=rpress-payment-history&view=live' ),
				'strings'        => array(
					'region'      => __( 'New orders', 'restropress' ),
					/* translators: %d: number of orders. */
					'titleOne'    => __( '%d new order', 'restropress' ),
					/* translators: %d: number of orders. */
					'titleMany'   => __( '%d new orders', 'restropress' ),
					'open'        => __( 'Open', 'restropress' ),
					'ack'         => __( 'Acknowledge', 'restropress' ),
					'ackHelp'     => __( 'Stop the alert for this order on every screen and device. The order stays on Live Orders.', 'restropress' ),
					'ackAll'      => __( 'Acknowledge all', 'restropress' ),
					'live'        => __( 'Open Live Orders', 'restropress' ),
					'dismiss'     => __( 'Dismiss until the next new order', 'restropress' ),
					'soundOn'     => __( 'Sound on. Click to mute.', 'restropress' ),
					'soundOff'    => __( 'Sound off. Click to turn on.', 'restropress' ),
					'blocked'     => __( 'Your browser blocked the sound. Click anywhere on this page to allow it.', 'restropress' ),
					/* translators: %d: number of orders not listed. */
					'more'        => __( '+ %d more on Live Orders', 'restropress' ),
					'justNow'     => __( 'just now', 'restropress' ),
					/* translators: %d: minutes. */
					'minAgo'      => __( '%d min ago', 'restropress' ),
					/* translators: %d: hours. */
					'hourAgo'     => __( '%d h ago', 'restropress' ),
					/* translators: %s: time. */
					'due'         => __( 'for %s', 'restropress' ),
				),
			)
		);
	}
}

RPRESS_Order_Popup::init();
