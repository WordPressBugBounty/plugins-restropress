<?php
/**
 * Live Orders kanban view for the orders / payment history admin page.
 *
 * URL: /wp-admin/admin.php?page=rpress-payment-history&view=live
 *
 * Renders a kanban with columns mapped to order-status slugs. Polls the
 * server every N seconds (configurable) for new and changed orders, plays
 * the configured notification sound for genuinely new orders, and supports
 * status changes via the same `rpress_update_order_status` AJAX as the
 * list view (no page reload).
 *
 * @package RPRESS
 * @subpackage Admin/Payments
 * @since 3.3
 */

defined( 'ABSPATH' ) || exit;

class RP_Live_Orders {

	/**
	 * Bootstrap - registers the AJAX action. Called once from class-rpress-ajax.php's
	 * registration block or directly when this file is required.
	 */
	public static function init() {
		add_action( 'wp_ajax_rpress_live_orders_refresh', array( __CLASS__, 'ajax_refresh' ) );
	}

	/**
	 * Default kanban column definitions. Filterable.
	 *
	 * @return array
	 */
	public static function get_columns() {
		$columns = array(
			'new' => array(
				'label'          => __( 'New', 'restropress' ),
				'statuses'       => array( 'pending' ),
				'default_status' => 'pending',
				'tone'           => 'warning',
			),
			'accepted' => array(
				'label'          => __( 'Accepted', 'restropress' ),
				'statuses'       => array( 'accepted' ),
				'default_status' => 'accepted',
				'tone'           => 'brand',
			),
			'preparing' => array(
				'label'          => __( 'Preparing', 'restropress' ),
				'statuses'       => array( 'processing' ),
				'default_status' => 'processing',
				'tone'           => 'info',
			),
			'ready' => array(
				'label'          => __( 'Ready', 'restropress' ),
				'statuses'       => array( 'ready' ),
				'default_status' => 'ready',
				'tone'           => 'success',
			),
			'out' => array(
				'label'          => __( 'Out for Delivery', 'restropress' ),
				'statuses'       => array( 'transit' ),
				'default_status' => 'transit',
				'tone'           => 'muted',
			),
		);

		/**
		 * Filter the Live Orders kanban column definitions.
		 *
		 * @since 3.3
		 * @param array $columns Default column definitions.
		 */
		return apply_filters( 'rpress_live_orders_columns', $columns );
	}

	/**
	 * Time window (hours) for cards visible in the kanban. Older completed orders fall off.
	 *
	 * @return int
	 */
	public static function get_window_hours() {
		/**
		 * Filter the rolling window (in hours) used to populate the Live Orders view.
		 *
		 * @since 3.3
		 * @param int $hours Default 12.
		 */
		return (int) apply_filters( 'rpress_live_orders_window_hours', 12 );
	}

	/**
	 * Poll interval seconds. Pulls from settings (`live_orders_poll_interval`), clamped.
	 *
	 * @return int
	 */
	public static function get_poll_interval() {
		$value = (int) rpress_get_option( 'live_orders_poll_interval', 10 );
		if ( $value < 10 )  { $value = 10; }
		if ( $value > 300 ) { $value = 300; }
		return $value;
	}

	/**
	 * Map an order's current status to a column key (or empty string if it doesn't belong).
	 *
	 * @param string $status
	 * @return string
	 */
	public static function status_to_column( $status ) {
		foreach ( self::get_columns() as $key => $col ) {
			if ( in_array( $status, $col['statuses'], true ) ) {
				return $key;
			}
		}
		return '';
	}

	/**
	 * Query orders for the kanban. Returns today's active service orders.
	 *
	 * The live board is an operations view, so date scope follows the order's
	 * service date instead of post_date. That keeps pre-orders for today visible
	 * even when they were placed yesterday.
	 * Status filtering is done in PHP after the query because the `_order_status`
	 * meta key is not guaranteed to exist on freshly-created orders (the helper
	 * `rpress_get_order_status()` falls back to 'pending' when missing).
	 *
	 * @return WP_Post[]
	 */
	public static function get_orders() {
		$columns      = self::get_columns();
		$all_statuses = array();
		foreach ( $columns as $col ) {
			$all_statuses = array_merge( $all_statuses, $col['statuses'] );
		}
		$all_statuses = array_unique( $all_statuses );

		// Day boundaries in the WordPress timezone. Using the DateTime helper keeps
		// "yesterday" correct across DST changes, where subtracting a fixed 86400s
		// from a wall-clock timestamp can land on the wrong calendar day.
		$now       = rpress_get_wp_now();
		$today     = $now->format( 'Y-m-d' );
		$yesterday = $now->modify( '-1 day' )->format( 'Y-m-d' );

		// Yesterday's service date is included so a still-active order from
		// last night (e.g. never accepted or never completed) stays on the
		// board instead of silently vanishing at midnight - the status
		// filter below already keeps finished orders off the board.
		$args = array(
			'post_type'        => 'rpress_payment',
			'post_status'      => array( 'publish', 'pending', 'processing' ),
			'posts_per_page'   => (int) apply_filters( 'rpress_live_orders_max_cards', 500 ),
			'meta_query'       => array(
				array(
					'key'     => '_rpress_delivery_date',
					'value'   => array( $yesterday, $today ),
					'compare' => 'IN',
				),
			),
			'orderby'          => 'ID',
			'order'            => 'DESC',
			'suppress_filters' => false,
		);

		/**
		 * Filter the WP_Query args used to fetch Live Orders.
		 *
		 * @since 3.3
		 * @param array $args
		 */
		$args = apply_filters( 'rpress_live_orders_query_args', $args );

		$query = new WP_Query( $args );
		$posts = $query->posts;

		// Orders created today without delivery-date meta (legacy/imported)
		// must not fall off the board either.
		$no_meta = new WP_Query(
			array(
				'post_type'        => 'rpress_payment',
				'post_status'      => array( 'publish', 'pending', 'processing' ),
				'posts_per_page'   => (int) apply_filters( 'rpress_live_orders_max_cards', 500 ),
				'date_query'       => array(
					array(
						'year'  => (int) date_i18n( 'Y' ),
						'month' => (int) date_i18n( 'm' ),
						'day'   => (int) date_i18n( 'd' ),
					),
				),
				'meta_query'       => array(
					array(
						'key'     => '_rpress_delivery_date',
						'compare' => 'NOT EXISTS',
					),
				),
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);
		if ( $no_meta->posts ) {
			$seen = wp_list_pluck( $posts, 'ID' );
			foreach ( $no_meta->posts as $post ) {
				if ( ! in_array( $post->ID, $seen, true ) ) {
					$posts[] = $post;
				}
			}
		}

		$matched = array();
		foreach ( $posts as $post ) {
			$status = rpress_get_order_status( $post->ID );
			if ( in_array( $status, $all_statuses, true ) ) {
				$matched[] = $post;
			}
		}
		return $matched;
	}

	/**
	 * Active statuses allowed from the live board.
	 *
	 * @return array
	 */
	protected static function get_live_statuses() {
		$statuses = array();
		foreach ( self::get_columns() as $col ) {
			$statuses = array_merge( $statuses, $col['statuses'] );
		}
		return array_unique( $statuses );
	}

	/**
	 * Build the service-type filters shown above the board.
	 *
	 * @return array
	 */
	public static function get_service_filters() {
		$filters = array( 'all' => __( 'All', 'restropress' ) );
		$services = function_exists( 'rpress_get_enabled_service_types' )
			? rpress_get_enabled_service_types()
			: array(
				'delivery' => rpress_service_label( 'delivery' ),
				'pickup'   => rpress_service_label( 'pickup' ),
			);

		foreach ( (array) $services as $slug => $label ) {
			$slug = self::normalize_service_type( $slug );
			if ( empty( $slug ) || isset( $filters[ $slug ] ) ) {
				continue;
			}
			$filters[ $slug ] = $label;
		}

		return $filters;
	}

	/**
	 * Normalize service slugs for filtering/rendering.
	 *
	 * @param string $service_type Raw service type slug.
	 * @return string
	 */
	protected static function normalize_service_type( $service_type ) {
		$service_type = sanitize_key( (string) $service_type );
		if ( 'dine-in' === $service_type || 'dine_in' === $service_type ) {
			return 'dinein';
		}
		return $service_type;
	}

	/**
	 * Build service-time labels for a live card.
	 *
	 * @param int    $payment_id Order ID.
	 * @param string $order_status Current order status.
	 * @return array
	 */
	protected static function get_service_time_summary( $payment_id, $order_status ) {
		$date_str = get_post_meta( $payment_id, '_rpress_delivery_date', true );
		$time_str = get_post_meta( $payment_id, '_rpress_delivery_time', true );

		if ( empty( $date_str ) ) {
			return array(
				'label' => __( 'No service time', 'restropress' ),
				'meta'  => '',
				'tone'  => 'muted',
			);
		}

		$is_asap = is_string( $time_str ) && stripos( $time_str, 'ASAP' ) !== false;
		if ( $is_asap ) {
			$time_str = trim( str_ireplace( 'ASAP', '', $time_str ) );
		}

		$time_display = trim( (string) $time_str );
		if ( $is_asap ) {
			$time_display = trim( __( 'ASAP', 'restropress' ) . ( $time_display ? ' ' . $time_display : '' ) );
		}

		$service_ts = self::parse_service_timestamp( $payment_id, $date_str, $time_str, $is_asap );
		$now        = current_time( 'timestamp' );
		$delta_min  = $service_ts ? ( $service_ts - $now ) / 60 : null;
		$meta       = '';
		$tone       = 'muted';

		if ( 'transit' === $order_status ) {
			$meta = __( 'Out for delivery', 'restropress' );
			$tone = 'info';
		} elseif ( 'ready' === $order_status ) {
			$meta = __( 'Ready for handoff', 'restropress' );
			$tone = 'success';
		} elseif ( null !== $delta_min && $delta_min < 0 ) {
			$late_min = max( 1, absint( $delta_min ) );
			if ( $late_min >= 1440 ) {
				$meta = sprintf( _n( 'Late by %d day', 'Late by %d days', (int) floor( $late_min / 1440 ), 'restropress' ), (int) floor( $late_min / 1440 ) );
			} elseif ( $late_min >= 60 ) {
				$meta = sprintf( _n( 'Late by %d hr', 'Late by %d hrs', (int) floor( $late_min / 60 ), 'restropress' ), (int) floor( $late_min / 60 ) );
			} else {
				$meta = sprintf(
					/* translators: %d: minutes late. */
					__( 'Late by %d min', 'restropress' ),
					$late_min
				);
			}
			$tone = 'late';
		} elseif ( null !== $delta_min && $delta_min <= 30 ) {
			$meta = sprintf(
				/* translators: %d: minutes until due. */
				__( 'Due in %d min', 'restropress' ),
				max( 1, (int) ceil( $delta_min ) )
			);
			$tone = 'urgent';
		}

		return array(
			'label' => $time_display ? $time_display : rpress_local_date( $date_str ),
			'meta'  => $meta,
			'tone'  => $tone,
		);
	}

	/**
	 * Best-effort parse of service date/time.
	 *
	 * ASAP (or missing-time) orders get a working promise of creation time
	 * plus the prep window - the same rule as the orders table and the
	 * command center - so cards age into "late" instead of reading "due
	 * now" forever, and the Late KPI agrees across all three screens.
	 *
	 * @param int    $payment_id Order ID.
	 * @param string $date_str Service date.
	 * @param string $time_str Service time.
	 * @param bool   $is_asap Whether the service time is ASAP.
	 * @return int|null
	 */
	protected static function parse_service_timestamp( $payment_id, $date_str, $time_str, $is_asap ) {
		if ( ! $is_asap ) {
			$time_parts = preg_split( '/\s*[\-–]\s*/', (string) $time_str );
			$time       = trim( $time_parts[0] ?? '' );
			if ( '' !== $time ) {
				$ts = strtotime( trim( $date_str ) . ' ' . $time );
				return $ts ? (int) $ts : null;
			}
		}

		return get_post_time( 'U', false, $payment_id ) + self::get_asap_prep_minutes() * MINUTE_IN_SECONDS;
	}

	/**
	 * Prep window for ASAP promises, shared with the command center thresholds.
	 *
	 * @return int
	 */
	protected static function get_asap_prep_minutes() {
		if ( class_exists( 'RPRESS_Command_Center_Dashboard' ) ) {
			$thresholds = RPRESS_Command_Center_Dashboard::thresholds();
			if ( isset( $thresholds['asap_prep_minutes'] ) ) {
				return max( 1, (int) $thresholds['asap_prep_minutes'] );
			}
		}
		return 30;
	}

	/**
	 * Return a concise list of cart item names for the card.
	 *
	 * @param array $cart_details Payment cart details.
	 * @return string
	 */
	protected static function get_item_summary( $cart_details ) {
		if ( empty( $cart_details ) || ! is_array( $cart_details ) ) {
			return '';
		}

		$names = array();
		foreach ( $cart_details as $row ) {
			if ( empty( $row['name'] ) ) {
				continue;
			}
			$names[] = wp_strip_all_tags( $row['name'] );
			if ( count( $names ) >= 3 ) {
				break;
			}
		}

		return implode( ', ', $names );
	}

	/**
	 * Choose the primary next action for a live card.
	 *
	 * @param string $status Current order status.
	 * @param string $service_type Service type.
	 * @return array|null
	 */
	protected static function get_next_action( $status, $service_type ) {
		$actions = array(
			'pending' => array(
				'status'     => 'accepted',
				'label'      => __( 'Accept', 'restropress' ),
				'aria_label' => __( 'Accept order', 'restropress' ),
				'tone'       => 'primary',
			),
			'accepted' => array(
				'status'     => 'processing',
				'label'      => __( 'Start', 'restropress' ),
				'aria_label' => __( 'Start preparing order', 'restropress' ),
				'tone'       => 'primary',
			),
			'processing' => array(
				'status'     => 'ready',
				'label'      => __( 'Ready', 'restropress' ),
				'aria_label' => __( 'Mark order ready', 'restropress' ),
				'tone'       => 'primary',
			),
			'transit' => array(
				'status'     => 'completed',
				'label'      => __( 'Complete', 'restropress' ),
				'aria_label' => __( 'Complete delivery', 'restropress' ),
				'tone'       => 'success',
			),
		);

		if ( 'ready' === $status ) {
			if ( 'delivery' === $service_type ) {
				return array(
					'status'     => 'transit',
					'label'      => __( 'Dispatch', 'restropress' ),
					'aria_label' => __( 'Mark order out for delivery', 'restropress' ),
					'tone'       => 'primary',
				);
			}

			return array(
				'status'     => 'completed',
				'label'      => __( 'Complete', 'restropress' ),
				'aria_label' => __( 'Complete order', 'restropress' ),
				'tone'       => 'success',
			);
		}

		return isset( $actions[ $status ] ) ? $actions[ $status ] : null;
	}

	/**
	 * Inline service icons match the list-table badges without depending on
	 * the list table class being loaded.
	 *
	 * @param string $service_type Service type slug.
	 * @return string SVG markup.
	 */
	public static function get_service_type_icon( $service_type ) {
		$icons = array(
			'delivery' => '<svg class="rp-type-icon rp-type-icon-truck" viewBox="0 0 22 16" aria-hidden="true" focusable="false"><path d="M1.5 3.2h11.1v8.2H8.9a2.5 2.5 0 0 0-4.8 0H1.5V3.2Zm12.1 2.3h3.5l3.4 3.5v2.4h-1.6a2.5 2.5 0 0 0-4.8 0h-.5V5.5Zm1.3 1.3v2.1h3.4l-2-2.1h-1.4ZM6.5 10.7a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm10 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Z" fill="currentColor"/></svg>',
			'pickup'   => '<svg class="rp-type-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4.5 5.8V4.7a3.5 3.5 0 0 1 7 0v1.1h1.3l.7 8.2H2.5l.7-8.2h1.3Zm1.1 0h4.8V4.7a2.4 2.4 0 0 0-4.8 0v1.1Zm-1.4 1-.5 6.1h8.6l-.5-6.1h-1.3v1.5h-1V6.8H5.6v1.5h-1V6.8h-.4Z" fill="currentColor"/></svg>',
			'dinein'   => '<svg class="rp-type-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3.1 1.5h1v5.2h.8V1.5h1v5.2h.8V1.5h1v5.4c0 1.3-.8 2.3-2 2.6v5h-1.4v-5c-1.2-.3-2-1.3-2-2.6V1.5Zm8.9 0c1.3 1 2 2.7 2 4.8 0 1.8-.6 3.2-1.7 3.8v4.4h-1.4V1.5H12Z" fill="currentColor"/></svg>',
		);

		return isset( $icons[ $service_type ] ) ? $icons[ $service_type ] : '';
	}

	/**
	 * Render a single kanban card. Returns the HTML string.
	 *
	 * @param WP_Post $payment
	 * @return string
	 */
	public static function render_card( $payment ) {
		$payment_id   = (int) $payment->ID;
		$order_status = rpress_get_order_status( $payment_id );

		// Customer name.
		$payment_obj   = rpress_get_payment( $payment_id );
		$user_info     = rpress_get_payment_meta_user_info( $payment_id );
		$customer_id   = rpress_get_payment_customer_id( $payment_id );
		$customer_name = '';
		if ( $customer_id ) {
			$customer      = new RPRESS_Customer( $customer_id );
			$customer_name = $customer->name;
		}
		if ( ! $customer_name && $payment_obj ) {
			$meta = $payment_obj->get_meta();
			if ( ! empty( $meta['user_info']['first_name'] ) || ! empty( $meta['user_info']['last_name'] ) ) {
				$customer_name = trim( ( $meta['user_info']['first_name'] ?? '' ) . ' ' . ( $meta['user_info']['last_name'] ?? '' ) );
			}
		}
		if ( ! $customer_name ) {
			$customer_name = __( 'Guest', 'restropress' );
		}
		// Phone is stored at the top level of the payment meta on real orders
		// (`phone`); fall back to the delivery address and the legacy
		// user_info keys. The previous code only read user_info['rpress_phone'],
		// which is empty on checkout-created orders, so the card never showed a
		// number.
		$payment_meta   = $payment_obj ? $payment_obj->get_meta() : array();
		$address_info   = get_post_meta( $payment_id, '_rpress_delivery_address', true );
		$customer_phone = '';
		if ( ! empty( $payment_meta['phone'] ) ) {
			$customer_phone = $payment_meta['phone'];
		} elseif ( is_array( $address_info ) && ! empty( $address_info['phone'] ) ) {
			$customer_phone = $address_info['phone'];
		} elseif ( is_array( $user_info ) && ! empty( $user_info['phone'] ) ) {
			$customer_phone = $user_info['phone'];
		} elseif ( is_array( $user_info ) && ! empty( $user_info['rpress_phone'] ) ) {
			$customer_phone = $user_info['rpress_phone'];
		}
		$customer_phone = sanitize_text_field( $customer_phone );

		// Order number with optional sequential prefix/postfix.
		$prefix  = rpress_get_option( 'sequential_prefix' );
		$postfix = rpress_get_option( 'sequential_postfix' );
		$seq     = get_post_meta( $payment_id, '_rpress_payment_number', true );
		if ( rpress_get_option( 'enable_sequential' ) && $seq ) {
			$order_number = '#' . $seq;
		} else {
			$order_number = '#' . $prefix . $payment_id . $postfix;
		}

		// Service type.
		$service_type  = rpress_get_service_type( $payment_id );
		$service_label = rpress_service_label( $service_type );
		$service_filter_type = self::normalize_service_type( $service_type );
		$enabled_services    = self::get_service_filters();
		if ( isset( $enabled_services[ $service_filter_type ] ) ) {
			$service_label = $enabled_services[ $service_filter_type ];
		}

		// Item count + total.
		$item_count = 0;
		$item_summary = '';
		if ( $payment_obj && ! empty( $payment_obj->cart_details ) && is_array( $payment_obj->cart_details ) ) {
			foreach ( $payment_obj->cart_details as $row ) {
				$item_count += isset( $row['quantity'] ) ? (int) $row['quantity'] : 1;
			}
			$item_summary = self::get_item_summary( $payment_obj->cart_details );
		}
		$total = $payment_obj ? rpress_currency_filter( rpress_format_amount( $payment_obj->total ), rpress_get_payment_currency_code( $payment_id ) ) : '';
		$payment_status = $payment_obj ? $payment_obj->status : get_post_status( $payment_id );
		$payment_label  = rpress_get_payment_status_label( $payment_status );
		$payment_label  = $payment_label ? $payment_label : ucfirst( (string) $payment_status );
		$gateway        = $payment_obj ? rpress_get_payment_gateway( $payment_id ) : '';
		$gateway_label  = $gateway ? rpress_get_gateway_admin_label( $gateway ) : '';
		$next_action    = self::get_next_action( $order_status, $service_filter_type );

		// Timestamps for time-since. Guard against the zeroed post_date_gmt
		// WordPress leaves on pending posts.
		$post_date_ts = ( $payment->post_date_gmt && '0000-00-00 00:00:00' !== $payment->post_date_gmt )
			? strtotime( $payment->post_date_gmt . ' UTC' )
			: (int) get_post_time( 'U', true, $payment );
		$service_time = self::get_service_time_summary( $payment_id, $order_status );

		$details_url = admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details&id=' . $payment_id );
		$search_text = strtolower( trim( $order_number . ' ' . $customer_name . ' ' . $customer_phone . ' ' . $item_summary ) );

		ob_start();
		?>
		<article class="rp-live-card" data-order-id="<?php echo $payment_id; ?>" data-status="<?php echo esc_attr( $order_status ); ?>" data-service-type="<?php echo esc_attr( $service_filter_type ); ?>" data-search="<?php echo esc_attr( $search_text ); ?>" data-created-at="<?php echo (int) $post_date_ts; ?>">
			<header class="rp-live-card-header">
				<div class="rp-live-card-title">
					<a href="<?php echo esc_url( $details_url ); ?>" class="rp-live-card-number"><?php echo esc_html( $order_number ); ?></a>
					<a href="#"
					   class="rp-live-card-quickview order-preview"
					   data-order-id="<?php echo $payment_id; ?>"
					   title="<?php esc_attr_e( 'Quick view order', 'restropress' ); ?>">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Quick view order', 'restropress' ); ?></span>
					</a>
					<span class="rp-type-cell rp-order-service-badge badge-<?php echo esc_attr( $service_filter_type ); ?>">
						<?php echo self::get_service_type_icon( $service_filter_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="rp-type-cell-label"><?php echo esc_html( $service_label ); ?></span>
					</span>
				</div>
				<time class="rp-live-card-time" datetime="<?php echo esc_attr( gmdate( 'c', $post_date_ts ) ); ?>"></time>
			</header>
			<div class="rp-live-card-customer"><?php echo esc_html( $customer_name ); ?></div>
			<?php if ( $customer_phone ) : ?>
				<div class="rp-live-card-phone"><?php echo esc_html( $customer_phone ); ?></div>
			<?php endif; ?>
			<div class="rp-live-card-service-time rp-live-card-service-time--<?php echo esc_attr( $service_time['tone'] ); ?>">
				<span class="dashicons dashicons-clock" aria-hidden="true"></span>
				<span class="rp-live-card-service-label"><?php echo esc_html( $service_time['label'] ); ?></span>
				<?php if ( ! empty( $service_time['meta'] ) ) : ?>
					<span class="rp-live-card-service-meta"><?php echo esc_html( $service_time['meta'] ); ?></span>
				<?php endif; ?>
			</div>
			<div class="rp-live-card-meta">
				<strong><?php echo esc_html( sprintf( _n( '%d item', '%d items', $item_count, 'restropress' ), (int) $item_count ) ); ?></strong>
				<?php if ( $item_summary ) : ?>
					<span><?php echo esc_html( $item_summary ); ?></span>
				<?php endif; ?>
			</div>
			<div class="rp-live-card-payment">
				<span><?php echo esc_html( $payment_label ); ?><?php echo $gateway_label ? esc_html( ' · ' . $gateway_label ) : ''; ?></span>
				<strong><?php echo wp_kses( $total, array( 'span' => array( 'class' => array() ) ) ); ?></strong>
			</div>
			<footer class="rp-live-card-footer">
				<?php if ( $next_action ) : ?>
					<button type="button"
					        class="button rp-live-card-action rp-live-card-action-<?php echo esc_attr( $next_action['tone'] ); ?>"
					        data-payment-id="<?php echo $payment_id; ?>"
					        data-next-status="<?php echo esc_attr( $next_action['status'] ); ?>"
					        aria-label="<?php echo esc_attr( $next_action['aria_label'] ); ?>"
					        title="<?php echo esc_attr( $next_action['aria_label'] ); ?>">
						<?php echo esc_html( $next_action['label'] ); ?>
					</button>
				<?php endif; ?>
			</footer>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build the full kanban snapshot used for both initial render and polling.
	 *
	 * @return array { columns: [key => { html, count, order_ids }], all_order_ids: [...] }
	 */
	public static function build_snapshot() {
		$columns = self::get_columns();
		$orders  = self::get_orders();
		$kpis    = array(
			'new'      => 0,
			'accepted' => 0,
			'preparing'=> 0,
			'ready'    => 0,
			'out'      => 0,
			'late'     => 0,
		);

		// Bucket orders into columns by their current order_status.
		$buckets = array();
		foreach ( $columns as $key => $col ) {
			$buckets[ $key ] = array(
				'orders'    => array(),
				'order_ids' => array(),
			);
		}
		$all_order_ids = array();

		foreach ( $orders as $payment ) {
			$status     = rpress_get_order_status( $payment->ID );
			$column_key = self::status_to_column( $status );
			if ( $column_key === '' ) {
				continue;
			}
			$buckets[ $column_key ]['orders'][]    = $payment;
			$buckets[ $column_key ]['order_ids'][] = (int) $payment->ID;
			$all_order_ids[]                       = (int) $payment->ID;
			if ( isset( $kpis[ $column_key ] ) ) {
				$kpis[ $column_key ]++;
			}
			$service_time = self::get_service_time_summary( $payment->ID, $status );
			if ( isset( $service_time['tone'] ) && 'late' === $service_time['tone'] ) {
				$kpis['late']++;
			}
		}

		// Render each column's HTML.
		$column_data = array();
		foreach ( $columns as $key => $col ) {
			$html = '';
			foreach ( $buckets[ $key ]['orders'] as $payment ) {
				$html .= self::render_card( $payment );
			}
			$column_data[ $key ] = array(
				'label'          => $col['label'],
				'tone'           => isset( $col['tone'] ) ? $col['tone'] : 'muted',
				'default_status' => $col['default_status'],
				'statuses'       => $col['statuses'],
				'count'          => count( $buckets[ $key ]['order_ids'] ),
				'order_ids'      => $buckets[ $key ]['order_ids'],
				'html'           => $html,
			);
		}

		return array(
			'columns'       => $column_data,
			'all_order_ids' => $all_order_ids,
			'kpis'          => $kpis,
		);
	}

	/**
	 * Render the Live Orders page.
	 */
	public static function render() {
		require RP_PLUGIN_DIR . 'includes/admin/payments/views/html-live-orders.php';
	}

	/**
	 * AJAX handler - returns the current snapshot for the kanban.
	 * Also exposes new_order_ids (orders not seen in the client's last poll).
	 */
	public static function ajax_refresh() {
		check_ajax_referer( 'rp_live_orders_refresh', 'security' );
		if ( ! current_user_can( 'edit_shop_payments' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'restropress' ) ), 403 );
		}

		$known_ids = array();
		if ( isset( $_POST['known_order_ids'] ) ) {
			$raw = wp_unslash( $_POST['known_order_ids'] );
			if ( is_array( $raw ) ) {
				$known_ids = array_map( 'absint', $raw );
			} elseif ( is_string( $raw ) && $raw !== '' ) {
				$known_ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );
			}
		}

		$snapshot = self::build_snapshot();
		$new_ids  = array_values( array_diff( $snapshot['all_order_ids'], $known_ids ) );

		wp_send_json_success( array(
			'polled_at'     => current_time( 'mysql' ),
			'columns'       => $snapshot['columns'],
			'all_order_ids' => $snapshot['all_order_ids'],
			'kpis'          => $snapshot['kpis'],
			'new_order_ids' => $new_ids,
		) );
	}
}

RP_Live_Orders::init();
