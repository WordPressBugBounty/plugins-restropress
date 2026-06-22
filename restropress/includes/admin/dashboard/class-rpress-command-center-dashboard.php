<?php
/**
 * Command center dashboard data provider.
 *
 * Aggregates today's service-day orders in a single pass, exposes an AJAX
 * endpoint for live refresh, and caches the payload in a short transient
 * that is flushed whenever an order or payment status changes.
 *
 * @package RPRESS
 * @subpackage Admin/Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RPRESS_Command_Center_Dashboard' ) ) {
	class RPRESS_Command_Center_Dashboard {

		const CACHE_KEY = 'rpress_command_center_data';

		public static function init() {
			add_action( 'wp_ajax_rpress_command_center_refresh', array( __CLASS__, 'ajax_refresh' ) );

			// Keep the cached payload honest: any order/payment mutation flushes it.
			add_action( 'rpress_update_order_status', array( __CLASS__, 'flush_cache' ) );
			add_action( 'rpress_update_payment_status', array( __CLASS__, 'flush_cache' ) );
			add_action( 'save_post_rpress_payment', array( __CLASS__, 'flush_cache' ) );
		}

		public static function flush_cache() {
			delete_transient( self::CACHE_KEY );
		}

		/**
		 * Operational thresholds, overridable per restaurant.
		 *
		 * A food truck and a ghost kitchen need different alarm levels, so
		 * every constant that drives health/issue states lives here.
		 */
		public static function thresholds() {
			return apply_filters(
				'rpress_command_center_thresholds',
				array(
					'due_soon_minutes'      => 15,
					'ready_waiting_minutes' => 10,
					'asap_prep_minutes'     => 30,
					'processing_overload'   => 6,
					'late_critical'         => 3,
					'needs_action_critical' => 8,
					'needs_action_risk'     => 4,
					'active_risk'           => 10,
					'active_busy'           => 5,
					'high_value_multiplier' => 1.5,
				)
			);
		}

		/**
		 * Gateways where "unpaid" means cash due on handoff, not a payment problem.
		 * The single source of truth for cash-awareness across every screen.
		 *
		 * `cash_on_delivery` is the slug the bundled COD gateway actually stores;
		 * `cash`/`cod`/`manual` are kept as aliases for legacy/imported data and
		 * the zero-total manual gateway.
		 */
		public static function cash_gateways() {
			return apply_filters( 'rpress_command_center_cash_gateways', array( 'cash_on_delivery', 'cash', 'cod', 'manual' ) );
		}

		public static function build_data( $force = false ) {
			if ( ! $force ) {
				$cached = get_transient( self::CACHE_KEY );
				if ( is_array( $cached ) && isset( $cached['kpis'] ) ) {
					return $cached;
				}
			}

			$data = self::compute_data();
			set_transient( self::CACHE_KEY, $data, 30 );

			return $data;
		}

		private static function compute_data() {
			$today          = current_time( 'Y-m-d' );
			$now            = current_time( 'timestamp' );
			$limits         = self::thresholds();
			$service_modes  = self::get_service_modes();
			$store_status   = self::get_store_status( $service_modes, $today );
			$active_status  = array( 'accepted', 'processing', 'ready', 'transit' );
			$done_status    = array( 'completed', 'cancelled', 'refunded', 'failed', 'trash' );
			$status_counts  = array_fill_keys( array( 'pending', 'accepted', 'processing', 'ready', 'transit', 'completed', 'cancelled' ), 0 );
			$service_counts = array();
			$hourly         = array();
			$item_counts    = array();

			for ( $hour = 0; $hour < 24; $hour++ ) {
				$hourly[ $hour ] = array( 'orders' => 0, 'sales' => 0.0 );
			}

			foreach ( $service_modes as $mode => $label ) {
				$service_counts[ $mode ] = array( 'label' => $label, 'count' => 0 );
			}

			$order_ids = self::get_today_order_ids( $today );

			// One query for all post rows + one for all meta, instead of N each.
			if ( ! empty( $order_ids ) ) {
				_prime_post_caches( $order_ids, false, false );
				update_meta_cache( 'post', $order_ids );
			}

			$orders              = array();
			$paid_count          = 0;
			$gross_sales         = 0.0;
			$refund_sales        = 0.0;
			$cash_due_total      = 0.0;
			$cash_due_count      = 0;
			$active_count        = 0;
			$late_count          = 0;
			$due_soon_count      = 0;
			$unpaid_active_count = 0;
			$ready_waiting       = 0;
			$cancelled_count     = 0;
			$refunded_count      = 0;
			$failed_count        = 0;

			// Pass 1: read every order once, aggregate everything that does not
			// depend on the day's averages.
			foreach ( $order_ids as $payment_id ) {
				$total         = (float) get_post_meta( $payment_id, '_rpress_payment_total', true );
				$order_status  = sanitize_key( rpress_get_order_status( $payment_id ) );
				$service_type  = function_exists( 'rpress_get_service_type' ) ? sanitize_key( rpress_get_service_type( $payment_id ) ) : '';
				$service_label = function_exists( 'rpress_service_label' ) ? rpress_service_label( $service_type ) : ucwords( $service_type );
				$payment_state = self::get_payment_state( $payment_id );
				$promise       = self::get_service_promise( $payment_id, $limits['asap_prep_minutes'] );
				$is_done       = in_array( $order_status, $done_status, true );
				$is_cancelled  = in_array( $order_status, array( 'cancelled', 'refunded', 'failed' ), true );

				if ( 'dine-in' === $service_type ) {
					$service_type  = 'dinein';
					$service_label = __( 'Dine-in', 'restropress' );
				}

				if ( isset( $status_counts[ $order_status ] ) ) {
					$status_counts[ $order_status ]++;
				}

				if ( isset( $service_counts[ $service_type ] ) ) {
					$service_counts[ $service_type ]['count']++;
				} elseif ( ! empty( $service_type ) ) {
					$service_counts[ $service_type ] = array( 'label' => $service_label, 'count' => 1 );
				}

				// Revenue: paid online orders plus accepted cash orders, never
				// refunded/failed/cancelled. Matches the orders-table KPI logic
				// so the two screens always agree.
				$counts_for_revenue = ! $is_cancelled
					&& ! in_array( $payment_state['key'], array( 'refunded', 'failed' ), true )
					&& ( 'paid' === $payment_state['key'] || ( $payment_state['is_cash'] && 'pending' !== $order_status ) );

				if ( $counts_for_revenue ) {
					$gross_sales += $total;
					$paid_count++;
				}

				if ( 'refunded' === $payment_state['key'] || 'refunded' === $order_status ) {
					$refund_sales += $total;
					$refunded_count++;
				}

				if ( 'failed' === $payment_state['key'] || 'failed' === $order_status ) {
					$failed_count++;
				}

				if ( 'cancelled' === $order_status ) {
					$cancelled_count++;
				}

				if ( in_array( $order_status, $active_status, true ) ) {
					$active_count++;
				}

				// Cash orders awaiting handoff are expected money, not a payment
				// problem. Only unpaid *online* orders count as payment issues.
				if ( ! $is_done && 'cash_due' === $payment_state['key'] ) {
					$cash_due_count++;
					$cash_due_total += $total;
				}

				if ( ! $is_done && 'unpaid' === $payment_state['key'] && ! $payment_state['is_cash'] ) {
					$unpaid_active_count++;
				}

				if ( ! $is_done && $promise['ts'] ) {
					$delta_minutes = ( $promise['ts'] - $now ) / MINUTE_IN_SECONDS;
					if ( $delta_minutes < 0 ) {
						$late_count++;
					} elseif ( $delta_minutes <= $limits['due_soon_minutes'] ) {
						$due_soon_count++;
					}
				}

				$ready_since = get_post_modified_time( 'U', false, $payment_id );
				if ( 'ready' === $order_status && ( $now - $ready_since ) > $limits['ready_waiting_minutes'] * MINUTE_IN_SECONDS ) {
					$ready_waiting++;
				}

				// Bucket the rush chart by the hour the order is due for service,
				// falling back to creation time - same population as the KPIs.
				$bucket_ts   = $promise['ts'] ? $promise['ts'] : get_post_time( 'U', false, $payment_id );
				$bucket_hour = (int) date_i18n( 'G', $bucket_ts );
				if ( isset( $hourly[ $bucket_hour ] ) ) {
					$hourly[ $bucket_hour ]['orders']++;
					if ( $counts_for_revenue ) {
						$hourly[ $bucket_hour ]['sales'] += $total;
					}
				}

				$cart_details = get_post_meta( $payment_id, '_rpress_payment_cart_details', true );
				foreach ( (array) $cart_details as $cart_item ) {
					$item_id = isset( $cart_item['id'] ) ? absint( $cart_item['id'] ) : absint( $cart_item );
					if ( ! $item_id ) {
						continue;
					}
					if ( ! isset( $item_counts[ $item_id ] ) ) {
						$item_counts[ $item_id ] = 0;
					}
					$item_counts[ $item_id ]++;
				}

				$orders[] = array(
					'id'            => $payment_id,
					'total'         => $total,
					'order_status'  => $order_status,
					'service_type'  => $service_type,
					'service_label' => $service_label,
					'payment_state' => $payment_state,
					'promise'       => $promise,
					'is_done'       => $is_done,
					'ready_since'   => $ready_since,
				);
			}

			$avg_ticket = $paid_count > 0 ? $gross_sales / $paid_count : 0.0;

			// Pass 2 (in memory, no queries): flag issues now that the average
			// ticket is known.
			$priority_queue   = array();
			$needs_action_ids = array();

			foreach ( $orders as $order ) {
				$issue = self::get_order_issue( $order, $avg_ticket, $limits, $now );
				if ( empty( $issue ) ) {
					continue;
				}

				$needs_action_ids[ $order['id'] ] = true;
				$priority_queue[] = array(
					'id'          => $order['id'],
					'number'      => '#' . $order['id'],
					'customer'    => self::get_customer_name( $order['id'] ),
					'service_key' => $order['service_type'] ? $order['service_type'] : 'service',
					'service'     => $order['service_label'] ? $order['service_label'] : __( 'Service', 'restropress' ),
					'promise'     => self::promise_label( $order['promise'] ),
					'elapsed'     => self::promise_elapsed( $order['promise'], $now ),
					'status'      => function_exists( 'rpress_get_order_status_label' ) ? rpress_get_order_status_label( $order['order_status'] ) : ucwords( $order['order_status'] ),
					'status_key'  => $order['order_status'],
					'payment'     => $order['payment_state'],
					'total'       => $order['total'],
					'issue'       => $issue,
					'details_url' => admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details&id=' . $order['id'] ),
					'live_url'    => admin_url( 'admin.php?page=rpress-payment-history&view=live' ),
				);
			}

			usort(
				$priority_queue,
				function( $a, $b ) {
					if ( $a['issue']['priority'] === $b['issue']['priority'] ) {
						return $b['id'] <=> $a['id'];
					}
					return $a['issue']['priority'] <=> $b['issue']['priority'];
				}
			);
			$priority_queue = array_slice( $priority_queue, 0, 8 );

			$needs_action = count( $needs_action_ids );
			$health       = self::get_health( $late_count, $needs_action, $active_count, $due_soon_count, $status_counts, $unpaid_active_count, $limits );
			$menu         = self::get_menu_readiness( $item_counts );
			$insights     = self::get_insights( $late_count, $unpaid_active_count, $status_counts, $ready_waiting, $menu, $store_status, $active_count, count( $order_ids ), $limits );
			$bottlenecks  = self::get_bottlenecks( $status_counts, $ready_waiting, $late_count, $limits );

			return array(
				'today'          => $today,
				'generated_at'   => $now,
				'updated_label'  => date_i18n( get_option( 'time_format' ), $now ),
				'store_status'   => $store_status,
				'service_modes'  => $service_modes,
				'health'         => $health,
				'kpis'           => array(
					'needs_action' => $needs_action,
					'active_load'  => $active_count,
					'promise_risk' => $late_count + $due_soon_count,
					// Refunded orders never enter gross sales, so gross already
					// is net of refunds - do not subtract them a second time.
					'net_sales'    => $gross_sales,
					'avg_ticket'   => $avg_ticket,
					'recovery'     => $cancelled_count + $refunded_count + $failed_count,
				),
				'ops_counts'     => array(
					'late'          => $late_count,
					'due_soon'      => $due_soon_count,
					'unpaid_active' => $unpaid_active_count,
					'ready_waiting' => $ready_waiting,
					'cash_due'      => $cash_due_count,
				),
				'cash_due_total' => $cash_due_total,
				'refund_total'   => $refund_sales,
				'status_counts'  => $status_counts,
				'service_counts' => $service_counts,
				'priority_queue' => $priority_queue,
				'hourly'         => $hourly,
				'menu'           => $menu,
				'recovery'       => array(
					'cancelled' => $cancelled_count,
					'refunded'  => $refunded_count,
					'failed'    => $failed_count,
					'late'      => $late_count,
				),
				'insights'       => array_slice( $insights, 0, 5 ),
				'bottlenecks'    => array_slice( $bottlenecks, 0, 4 ),
			);
		}

		/**
		 * Render the dynamic dashboard panels (everything below the topline).
		 * Shared by the page template and the AJAX refresh response.
		 */
		public static function render_panels( $dashboard ) {
			include __DIR__ . '/views/command-center-panels.php';
		}

		public static function ajax_refresh() {
			// No die-on-failure: a stale nonce (page left open past the nonce
			// lifetime) must produce JSON the poller can recognise and halt on.
			if ( ! check_ajax_referer( 'rpress-command-center', 'nonce', false ) ) {
				wp_send_json_error( array( 'code' => 'expired' ), 403 );
			}

			$capability = apply_filters( 'rpress_view_customers_role', 'view_shop_reports' );
			if ( ! current_user_can( $capability ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to view this data.', 'restropress' ) ), 403 );
			}

			$dashboard = self::build_data();

			ob_start();
			self::render_panels( $dashboard );
			$html = ob_get_clean();

			wp_send_json_success(
				array(
					'html'    => $html,
					'updated' => $dashboard['updated_label'],
					'health'  => $dashboard['health']['key'],
				)
			);
		}

		public static function money( $amount ) {
			return function_exists( 'rpress_currency_filter' )
				? rpress_currency_filter( rpress_format_amount( (float) $amount ) )
				: number_format_i18n( (float) $amount, 2 );
		}

		public static function orders_url( $args = array() ) {
			return add_query_arg(
				array_merge(
					array(
						'page' => 'rpress-payment-history',
						'view' => 'list',
					),
					$args
				),
				admin_url( 'admin.php' )
			);
		}

		public static function service_icon( $service_type ) {
			$icons = array(
				'delivery' => '<svg class="rp-command-service-icon rp-command-service-icon-truck" viewBox="0 0 22 16" aria-hidden="true" focusable="false"><path d="M1.5 3.2h11.1v8.2H8.9a2.5 2.5 0 0 0-4.8 0H1.5V3.2Zm12.1 2.3h3.5l3.4 3.5v2.4h-1.6a2.5 2.5 0 0 0-4.8 0h-.5V5.5Zm1.3 1.3v2.1h3.4l-2-2.1h-1.4ZM6.5 10.7a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm10 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Z" fill="currentColor"/></svg>',
				'pickup'   => '<svg class="rp-command-service-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4.5 5.8V4.7a3.5 3.5 0 0 1 7 0v1.1h1.3l.7 8.2H2.5l.7-8.2h1.3Zm1.1 0h4.8V4.7a2.4 2.4 0 0 0-4.8 0v1.1Zm-1.4 1-.5 6.1h8.6l-.5-6.1h-1.3v1.5h-1V6.8H5.6v1.5h-1V6.8h-.4Z" fill="currentColor"/></svg>',
				'dinein'   => '<svg class="rp-command-service-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3.1 1.5h1v5.2h.8V1.5h1v5.2h.8V1.5h1v5.4c0 1.3-.8 2.3-2 2.6v5h-1.4v-5c-1.2-.3-2-1.3-2-2.6V1.5Zm8.9 0c1.3 1 2 2.7 2 4.8 0 1.8-.6 3.2-1.7 3.8v4.4h-1.4V1.5H12Z" fill="currentColor"/></svg>',
			);

			return isset( $icons[ $service_type ] ) ? $icons[ $service_type ] : '<svg class="rp-command-service-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1.5 14.5 8 8 14.5 1.5 8 8 1.5Zm0 2L3.5 8 8 12.5 12.5 8 8 3.5Z" fill="currentColor"/></svg>';
		}

		/**
		 * Today's service-day orders: delivery date is today, plus any order
		 * created today that has no delivery date meta (legacy/imported rows),
		 * so nothing silently disappears from the board.
		 */
		private static function get_today_order_ids( $today ) {
			$base_args = array(
				'post_type'              => 'rpress_payment',
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);

			$by_service_date = new WP_Query(
				array_merge(
					$base_args,
					array(
						'meta_query' => array(
							array(
								'key'   => '_rpress_delivery_date',
								'value' => $today,
							),
						),
					)
				)
			);

			$created_today_no_meta = new WP_Query(
				array_merge(
					$base_args,
					array(
						'date_query' => array(
							array(
								'year'  => (int) date_i18n( 'Y' ),
								'month' => (int) date_i18n( 'm' ),
								'day'   => (int) date_i18n( 'd' ),
							),
						),
						'meta_query' => array(
							array(
								'key'     => '_rpress_delivery_date',
								'compare' => 'NOT EXISTS',
							),
						),
					)
				)
			);

			$ids = array_unique( array_merge( array_map( 'intval', $by_service_date->posts ), array_map( 'intval', $created_today_no_meta->posts ) ) );
			rsort( $ids );

			return $ids;
		}

		/**
		 * When the customer must receive the order.
		 *
		 * ASAP / missing-time orders get a working promise of creation time plus
		 * the prep window, so they age into "late" naturally instead of sitting
		 * in "due soon" forever.
		 *
		 * @return array { ts: int, is_asap: bool }
		 */
		private static function get_service_promise( $payment_id, $asap_prep_minutes ) {
			$date    = trim( (string) get_post_meta( $payment_id, '_rpress_delivery_date', true ) );
			$time    = trim( (string) get_post_meta( $payment_id, '_rpress_delivery_time', true ) );
			$is_asap = ( '' === $time || false !== stripos( $time, 'asap' ) );

			if ( $is_asap || '' === $date ) {
				$created = get_post_time( 'U', false, $payment_id );
				return array(
					'ts'      => $created + ( $asap_prep_minutes * MINUTE_IN_SECONDS ),
					'is_asap' => true,
				);
			}

			if ( false !== strpos( $time, '-' ) || false !== strpos( $time, '–' ) ) {
				$parts = preg_split( '/\s*[\-–]\s*/', $time );
				$time  = ! empty( $parts[0] ) ? trim( $parts[0] ) : '';
			}

			$timestamp = strtotime( $date . ' ' . $time );
			if ( false === $timestamp ) {
				return array( 'ts' => 0, 'is_asap' => false );
			}

			return array( 'ts' => $timestamp, 'is_asap' => false );
		}

		private static function promise_label( $promise ) {
			if ( empty( $promise['ts'] ) ) {
				return __( 'No time', 'restropress' );
			}

			$time = date_i18n( get_option( 'time_format' ), $promise['ts'] );

			return $promise['is_asap']
				? sprintf( __( 'ASAP (by %s)', 'restropress' ), $time )
				: $time;
		}

		private static function promise_elapsed( $promise, $now ) {
			if ( empty( $promise['ts'] ) ) {
				return __( 'Unscheduled', 'restropress' );
			}

			return human_time_diff( $promise['ts'], $now ) . ( $promise['ts'] < $now ? ' ' . __( 'late', 'restropress' ) : '' );
		}

		private static function get_customer_name( $payment_id ) {
			$name        = '';
			$customer_id = function_exists( 'rpress_get_payment_customer_id' ) ? rpress_get_payment_customer_id( $payment_id ) : 0;

			if ( ! empty( $customer_id ) && class_exists( 'RPRESS_Customer' ) ) {
				$customer = new RPRESS_Customer( $customer_id );
				$name     = ! empty( $customer->name ) ? $customer->name : '';
			}

			if ( '' === $name && function_exists( 'rpress_get_payment_meta' ) ) {
				$payment_meta = rpress_get_payment_meta( $payment_id );
				$user_info    = isset( $payment_meta['user_info'] ) && is_array( $payment_meta['user_info'] ) ? $payment_meta['user_info'] : array();
				$name         = trim( ( isset( $user_info['first_name'] ) ? $user_info['first_name'] : '' ) . ' ' . ( isset( $user_info['last_name'] ) ? $user_info['last_name'] : '' ) );
			}

			return '' !== $name ? sanitize_text_field( $name ) : __( 'Guest', 'restropress' );
		}

		/**
		 * Payment state with cash-gateway awareness. Cash/COD orders awaiting
		 * settlement read as "Cash Due", never as a payment problem.
		 *
		 * @return array { key: string, label: string, is_cash: bool }
		 */
		private static function get_payment_state( $payment_id ) {
			$post_status = get_post_status( $payment_id );
			$gateway     = get_post_meta( $payment_id, '_rpress_payment_gateway', true );
			$is_cash     = in_array( $gateway, self::cash_gateways(), true );

			if ( 'publish' === $post_status ) {
				return array( 'key' => 'paid', 'label' => __( 'Paid', 'restropress' ), 'is_cash' => $is_cash );
			}

			if ( in_array( $post_status, array( 'refunded', 'failed' ), true ) ) {
				return array( 'key' => $post_status, 'label' => ucwords( $post_status ), 'is_cash' => $is_cash );
			}

			if ( $is_cash ) {
				return array( 'key' => 'cash_due', 'label' => __( 'Cash Due', 'restropress' ), 'is_cash' => true );
			}

			return array( 'key' => 'unpaid', 'label' => __( 'Unpaid', 'restropress' ), 'is_cash' => false );
		}

		private static function get_service_modes() {
			if ( function_exists( 'rpress_get_enabled_service_types' ) ) {
				$modes = rpress_get_enabled_service_types();
			} else {
				$enabled = function_exists( 'rpress_get_option' ) ? rpress_get_option( 'enable_service', 'delivery_and_pickup' ) : 'delivery_and_pickup';
				$modes   = array();
				if ( 'delivery_and_pickup' === $enabled || 'delivery' === $enabled ) {
					$modes['delivery'] = function_exists( 'rpress_service_label' ) ? rpress_service_label( 'delivery' ) : __( 'Delivery', 'restropress' );
				}
				if ( 'delivery_and_pickup' === $enabled || 'pickup' === $enabled ) {
					$modes['pickup'] = function_exists( 'rpress_service_label' ) ? rpress_service_label( 'pickup' ) : __( 'Pickup', 'restropress' );
				}
			}

			return is_array( $modes ) ? $modes : array();
		}

		private static function get_store_status( $service_modes, $today ) {
			$slots       = array();
			$last_slot   = 0;
			$is_open_any = false;

			foreach ( $service_modes as $mode => $label ) {
				if ( function_exists( 'rpress_is_store_open' ) && rpress_is_store_open( $mode, $today ) ) {
					$is_open_any = true;
				}

				if ( function_exists( 'rpress_get_available_service_slots' ) ) {
					$mode_slots = rpress_get_available_service_slots( $mode, $today, false );
					foreach ( (array) $mode_slots as $slot ) {
						$slot_label = is_array( $slot ) && isset( $slot['label'] ) ? $slot['label'] : $slot;
						$slot_ts    = strtotime( $today . ' ' . $slot_label );
						if ( $slot_ts ) {
							$slots[]   = $slot_ts;
							$last_slot = max( $last_slot, $slot_ts );
						}
					}
				}
			}

			if ( empty( $service_modes ) ) {
				return array(
					'key'    => 'not-configured',
					'label'  => __( 'Not Configured', 'restropress' ),
					'window' => __( 'Set up service modes to accept orders.', 'restropress' ),
				);
			}

			sort( $slots );
			$window = ! empty( $slots )
				? sprintf( '%s - %s', date_i18n( get_option( 'time_format' ), reset( $slots ) ), date_i18n( get_option( 'time_format' ), end( $slots ) ) )
				: __( 'Hours not configured', 'restropress' );

			if ( ! $is_open_any ) {
				return array(
					'key'    => 'closed',
					'label'  => __( 'Closed', 'restropress' ),
					'window' => $window,
				);
			}

			if ( $last_slot && ( $last_slot - current_time( 'timestamp' ) ) <= HOUR_IN_SECONDS ) {
				return array(
					'key'    => 'closing-soon',
					'label'  => __( 'Closing Soon', 'restropress' ),
					'window' => $window,
				);
			}

			return array(
				'key'    => 'open',
				'label'  => __( 'Open', 'restropress' ),
				'window' => $window,
			);
		}

		private static function get_order_issue( $order, $avg_ticket, $limits, $now ) {
			$order_status  = $order['order_status'];
			$payment_state = $order['payment_state'];
			$promise_ts    = $order['promise']['ts'];
			$is_done       = $order['is_done'];

			if ( in_array( $order_status, array( 'cancelled', 'refunded', 'failed' ), true ) || in_array( $payment_state['key'], array( 'refunded', 'failed' ), true ) ) {
				return array(
					'key'      => 'recovery',
					'label'    => __( 'Service Recovery', 'restropress' ),
					'priority' => 50,
					'detail'   => __( 'Protect the customer relationship.', 'restropress' ),
				);
			}

			if ( ! $is_done && $promise_ts && $promise_ts < $now ) {
				return array(
					'key'      => 'late',
					'label'    => __( 'Late', 'restropress' ),
					'priority' => 10,
					'detail'   => sprintf( __( '%s behind promise', 'restropress' ), human_time_diff( $promise_ts, $now ) ),
				);
			}

			if ( 'pending' === $order_status ) {
				return array(
					'key'      => 'pending',
					'label'    => __( 'Needs Acceptance', 'restropress' ),
					'priority' => 20,
					'detail'   => __( 'Waiting for the restaurant to accept.', 'restropress' ),
				);
			}

			// Cash-due orders are expected money, not a payment issue.
			if ( 'unpaid' === $payment_state['key'] && ! $payment_state['is_cash'] && ! $is_done ) {
				return array(
					'key'      => 'payment',
					'label'    => __( 'Payment Issue', 'restropress' ),
					'priority' => 30,
					'detail'   => __( 'Online payment not confirmed. Review before fulfilment.', 'restropress' ),
				);
			}

			if ( 'ready' === $order_status && ( $now - $order['ready_since'] ) > $limits['ready_waiting_minutes'] * MINUTE_IN_SECONDS ) {
				return array(
					'key'      => 'ready-waiting',
					'label'    => __( 'Ready Too Long', 'restropress' ),
					'priority' => 40,
					'detail'   => sprintf( __( 'Ready for %s.', 'restropress' ), human_time_diff( $order['ready_since'], $now ) ),
				);
			}

			if ( $avg_ticket > 0 && $order['total'] >= ( $avg_ticket * $limits['high_value_multiplier'] ) && in_array( $order_status, array( 'accepted', 'processing', 'ready', 'transit' ), true ) ) {
				return array(
					'key'      => 'high-value',
					'label'    => __( 'High-Value Active Order', 'restropress' ),
					'priority' => 60,
					'detail'   => __( 'Keep this order moving.', 'restropress' ),
				);
			}

			return array();
		}

		private static function get_health( $late_count, $needs_action, $active_count, $due_soon_count, $status_counts, $unpaid_active_count, $limits ) {
			$health_key   = 'calm';
			$health_label = __( 'Calm', 'restropress' );

			if ( $late_count >= $limits['late_critical'] || $needs_action >= $limits['needs_action_critical'] ) {
				$health_key   = 'critical';
				$health_label = __( 'Critical', 'restropress' );
			} elseif ( $late_count > 0 || $needs_action >= $limits['needs_action_risk'] || $active_count >= $limits['active_risk'] ) {
				$health_key   = 'at-risk';
				$health_label = __( 'At Risk', 'restropress' );
			} elseif ( $active_count >= $limits['active_busy'] || $due_soon_count > 0 || $needs_action > 0 ) {
				$health_key   = 'busy';
				$health_label = __( 'Busy', 'restropress' );
			}

			$reasons = array();
			if ( $late_count > 0 ) {
				$reasons[] = sprintf( _n( '%d order late', '%d orders late', $late_count, 'restropress' ), $late_count );
			}
			if ( $status_counts['pending'] > 0 ) {
				$reasons[] = sprintf( _n( '%d waiting acceptance', '%d waiting acceptance', $status_counts['pending'], 'restropress' ), $status_counts['pending'] );
			}
			if ( $unpaid_active_count > 0 ) {
				$reasons[] = sprintf( _n( '%d online payment issue', '%d online payment issues', $unpaid_active_count, 'restropress' ), $unpaid_active_count );
			}
			if ( $active_count >= $limits['active_busy'] ) {
				$reasons[] = sprintf( __( '%d active in kitchen flow', 'restropress' ), $active_count );
			}
			if ( empty( $reasons ) ) {
				$reasons[] = __( 'No exceptions right now. Kitchen load normal.', 'restropress' );
			}

			return array( 'key' => $health_key, 'label' => $health_label, 'reasons' => $reasons );
		}

		private static function get_menu_readiness( $item_counts ) {
			$published_items = wp_count_posts( 'fooditem' );
			$published_items = isset( $published_items->publish ) ? (int) $published_items->publish : 0;
			$out_of_stock = 0;

			$unavailable_meta_query = array(
				'relation' => 'OR',
				array(
					'key'   => 'rp_stock_status',
					'value' => 'unavailable',
				),
			);

			if ( class_exists( 'RP_Inventory' ) ) {
				$unavailable_meta_query[] = array(
					'key'     => 'rp_item_stock',
					'value'   => 0,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
			}

			$out_of_stock_query = new WP_Query(
				array(
					'post_type'              => 'fooditem',
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => $unavailable_meta_query,
				)
			);
			$out_of_stock = (int) $out_of_stock_query->found_posts;

			arsort( $item_counts );
			$has_today_item_sales = ! empty( $item_counts );
			$top_items = array();
			foreach ( array_slice( $item_counts, 0, 3, true ) as $item_id => $count ) {
				$top_items[] = array(
					'title' => get_the_title( $item_id ),
					'count' => (int) $count,
				);
			}

			$missing_images = 0;
			if ( $published_items > 0 ) {
				$menu_without_images = new WP_Query(
					array(
						'post_type'              => 'fooditem',
						'post_status'            => 'publish',
						'posts_per_page'         => 1,
						'fields'                 => 'ids',
						'no_found_rows'          => false,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						'meta_query'             => array(
							array(
								'key'     => '_thumbnail_id',
								'compare' => 'NOT EXISTS',
							),
						),
					)
				);
				$missing_images = (int) $menu_without_images->found_posts;
			}

			return array(
				'published'            => $published_items,
				'out_of_stock'         => $out_of_stock,
				'top_items'            => $top_items,
				'has_today_item_sales' => $has_today_item_sales,
				'missing_images'       => $missing_images,
			);
		}

		private static function get_insights( $late_count, $unpaid_active_count, $status_counts, $ready_waiting, $menu, $store_status, $active_count, $today_order_count, $limits ) {
			$insights = array();

			if ( $late_count > 0 ) {
				$insights[] = sprintf( _n( '%d order is already late. Open Live Orders and recover it first.', '%d orders are already late. Open Live Orders and recover those first.', $late_count, 'restropress' ), $late_count );
			}
			if ( $unpaid_active_count > 0 ) {
				$insights[] = sprintf( _n( '%d active order has an unconfirmed online payment. Review before dispatch.', '%d active orders have unconfirmed online payments. Review before dispatch.', $unpaid_active_count, 'restropress' ), $unpaid_active_count );
			}
			if ( $status_counts['processing'] >= $limits['processing_overload'] ) {
				$insights[] = sprintf( __( 'Preparing is loaded with %d orders. Consider slowing new order promises.', 'restropress' ), $status_counts['processing'] );
			}
			if ( $ready_waiting > 0 ) {
				$insights[] = sprintf( _n( '%1$d ready order has been waiting more than %2$d minutes.', '%1$d ready orders have been waiting more than %2$d minutes.', $ready_waiting, 'restropress' ), $ready_waiting, $limits['ready_waiting_minutes'] );
			}
			if ( ! in_array( $store_status['key'], array( 'open', 'closing-soon' ), true ) ) {
				$insights[] = __( 'Store is not open for ordering. Confirm hours before the next service window.', 'restropress' );
			}
			if ( 0 === $active_count && 0 === $today_order_count && in_array( $store_status['key'], array( 'open', 'closing-soon' ), true ) ) {
				$insights[] = __( 'No orders yet today. Use the calm window to verify menu, stock, and ordering settings.', 'restropress' );
			}
			if ( $menu['has_today_item_sales'] && ! empty( $menu['top_items'] ) ) {
				$insights[] = sprintf( __( '%s is moving fastest. Keep prep and stock ready.', 'restropress' ), $menu['top_items'][0]['title'] );
			}
			if ( $menu['missing_images'] > 0 ) {
				$insights[] = sprintf( _n( '%d menu item has no image. Add photos to improve conversion.', '%d menu items have no image. Add photos to improve conversion.', $menu['missing_images'], 'restropress' ), $menu['missing_images'] );
			}
			if ( empty( $insights ) ) {
				$insights[] = __( 'No urgent recommendations. Keep watching the rush timeline and live orders.', 'restropress' );
			}

			return $insights;
		}

		private static function get_bottlenecks( $status_counts, $ready_waiting, $late_count, $limits ) {
			$bottlenecks = array();
			if ( $status_counts['pending'] > 0 ) {
				$bottlenecks[] = sprintf( _n( '%d new order is waiting for acceptance.', '%d new orders are waiting for acceptance.', $status_counts['pending'], 'restropress' ), $status_counts['pending'] );
			}
			if ( $status_counts['processing'] >= $limits['processing_overload'] ) {
				$bottlenecks[] = sprintf( __( 'Preparing is overloaded with %d active orders.', 'restropress' ), $status_counts['processing'] );
			}
			if ( $ready_waiting > 0 ) {
				$bottlenecks[] = sprintf( _n( '%d ready order is waiting too long.', '%d ready orders are waiting too long.', $ready_waiting, 'restropress' ), $ready_waiting );
			}
			if ( $late_count > 0 ) {
				$bottlenecks[] = sprintf( _n( '%d promise is already late.', '%d promises are already late.', $late_count, 'restropress' ), $late_count );
			}
			if ( empty( $bottlenecks ) ) {
				$bottlenecks[] = __( 'No bottleneck detected. Kitchen flow is balanced.', 'restropress' );
			}

			return $bottlenecks;
		}
	}

	RPRESS_Command_Center_Dashboard::init();
}
