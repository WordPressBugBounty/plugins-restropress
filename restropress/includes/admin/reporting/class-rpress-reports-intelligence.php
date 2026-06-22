<?php
/**
 * Reports Intelligence partial.
 *
 * @package RPRESS
 * @since   3.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RPRESS_Reports_Intelligence' ) ) {
	/**
	 * Period-based data provider for the redesigned reports experience.
	 *
	 * @since 3.3
	 */
	class RPRESS_Reports_Intelligence {

		/**
		 * Get the supported report tabs.
		 *
		 * @since 3.3
		 *
		 * @return array
		 */
		public static function get_tabs() {
			$tabs = array(
				'overview'          => esc_html__( 'Overview', 'restropress' ),
				'sales'             => esc_html__( 'Sales', 'restropress' ),
				'orders-risk'       => esc_html__( 'Orders & Risk', 'restropress' ),
				'menu'              => esc_html__( 'Menu', 'restropress' ),
				'customers'         => esc_html__( 'Customers', 'restropress' ),
				'payments-recovery' => esc_html__( 'Payments & Recovery', 'restropress' ),
				'taxes'             => esc_html__( 'Taxes', 'restropress' ),
			);

			if ( current_user_can( 'export_shop_reports' ) ) {
				$tabs['export'] = esc_html__( 'Export', 'restropress' );
			}

			return apply_filters( 'rpress_reports_intelligence_tabs', $tabs );
		}

		/**
		 * Get sanitized filters.
		 *
		 * @since 3.3
		 *
		 * @return array
		 */
		public static function get_filters() {
			$range = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'last_30';
			$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
			$end_date = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
			$service = isset( $_GET['service_type'] ) ? sanitize_key( wp_unslash( $_GET['service_type'] ) ) : '';
			$payment = isset( $_GET['payment_status'] ) ? sanitize_key( wp_unslash( $_GET['payment_status'] ) ) : '';
			$order_status = isset( $_GET['order_status'] ) ? sanitize_key( wp_unslash( $_GET['order_status'] ) ) : '';

			$ranges = self::get_range_options();
			if ( ! isset( $ranges[ $range ] ) ) {
				$range = 'last_30';
			}

			if ( ! array_key_exists( $payment, array( '' => '' ) + rpress_get_payment_statuses() ) ) {
				$payment = '';
			}

			if ( ! array_key_exists( $order_status, array( '' => '' ) + rpress_get_order_statuses() ) ) {
				$order_status = '';
			}

			$period = self::get_period_for_range( $range, $start_date, $end_date );

			return array(
				'range'          => $range,
				'start'          => $period['start'],
				'end'            => $period['end'],
				'previous_start' => $period['previous_start'],
				'previous_end'   => $period['previous_end'],
				'start_date'     => $period['start'],
				'end_date'       => $period['end'],
				'service_type'   => $service,
				'payment_status' => $payment,
				'order_status'   => $order_status,
			);
		}

		/**
		 * Available date range options.
		 *
		 * @since 3.3
		 *
		 * @return array
		 */
		public static function get_range_options() {
			return array(
				'today'      => esc_html__( 'Today', 'restropress' ),
				'yesterday'  => esc_html__( 'Yesterday', 'restropress' ),
				'this_week'  => esc_html__( 'This Week', 'restropress' ),
				'last_7'     => esc_html__( 'Last 7 Days', 'restropress' ),
				'last_30'    => esc_html__( 'Last 30 Days', 'restropress' ),
				'this_month' => esc_html__( 'This Month', 'restropress' ),
				'last_month' => esc_html__( 'Last Month', 'restropress' ),
				'custom'     => esc_html__( 'Custom Range', 'restropress' ),
			);
		}

		/**
		 * Build overview data.
		 *
		 * @since 3.3
		 *
		 * @param array $filters Current report filters.
		 * @return array
		 */
		/**
		 * Short-lived cache wrapper for the per-tab report builds. Reports are
		 * historical analytics, not a live operations view, so a brief cache is
		 * safe and removes the repeated full scans when staff reopen a tab or
		 * flip between tabs on the same period. TTL is short enough that new
		 * orders surface quickly; the key includes the filters so each view is
		 * cached independently.
		 *
		 * @since 3.3
		 * @param string   $key_suffix Stable identifier for the build + filters.
		 * @param callable $builder    Produces the data on a cache miss.
		 * @return mixed
		 */
		protected static function cached( $key_suffix, $builder ) {
			$ttl = (int) apply_filters( 'rpress_reports_cache_ttl', 120 );
			if ( $ttl <= 0 ) {
				return call_user_func( $builder );
			}
			$key    = 'rpress_rep_' . md5( $key_suffix );
			$cached = get_transient( $key );
			if ( false !== $cached ) {
				return $cached;
			}
			$data = call_user_func( $builder );
			set_transient( $key, $data, $ttl );
			return $data;
		}

		public static function build_overview( $filters ) {
			return self::cached( 'overview|' . wp_json_encode( $filters ), function () use ( $filters ) {
				$current  = self::summarize_period( $filters['start'], $filters['end'], $filters );
				$previous = self::summarize_period( $filters['previous_start'], $filters['previous_end'], $filters );
				$insights = self::build_insights( $current, $previous );

				return array(
					'current'  => $current,
					'previous' => $previous,
					'insights' => $insights,
					'health'   => self::build_risk_state( $current, $previous ),
				);
			} );
		}

		/**
		 * Build selected-period menu performance data.
		 *
		 * @since 3.3
		 *
		 * @param array $filters Current report filters.
		 * @return array
		 */
		public static function build_menu( $filters ) {
			return self::cached( 'menu|' . wp_json_encode( $filters ), function () use ( $filters ) {
				$current_ids  = self::get_payment_ids( $filters['start'], $filters['end'], $filters );
				$previous_ids = self::get_payment_ids( $filters['previous_start'], $filters['previous_end'], $filters );
				$current      = self::summarize_menu_period( $current_ids );
				$previous     = self::summarize_menu_period( $previous_ids );

				return array(
					'current'   => $current,
					'previous'  => $previous,
					'readiness' => self::get_menu_readiness(),
				);
			} );
		}

		/**
		 * Build selected-period customer behavior data.
		 *
		 * @since 3.3
		 *
		 * @param array $filters Current report filters.
		 * @return array
		 */
		public static function build_customers( $filters, $limit = 50 ) {
			return self::cached( 'customers|' . $limit . '|' . wp_json_encode( $filters ), function () use ( $filters, $limit ) {
				$current_ids  = self::get_payment_ids( $filters['start'], $filters['end'], $filters );
				$previous_ids = self::get_payment_ids( $filters['previous_start'], $filters['previous_end'], $filters );

				return array(
					'current'  => self::summarize_customers_period( $current_ids, $filters['start'], $filters['end'], $limit ),
					'previous' => self::summarize_customers_period( $previous_ids, $filters['previous_start'], $filters['previous_end'], $limit ),
				);
			} );
		}

		/**
		 * Build selected-period payment and recovery data.
		 *
		 * @since 3.3
		 *
		 * @param array $filters Current report filters.
		 * @return array
		 */
		public static function build_payments_recovery( $filters ) {
			return self::cached( 'recovery|' . wp_json_encode( $filters ), function () use ( $filters ) {
				$current_ids  = self::get_payment_ids( $filters['start'], $filters['end'], $filters );
				$previous_ids = self::get_payment_ids( $filters['previous_start'], $filters['previous_end'], $filters );

				return array(
					'current'  => self::summarize_payments_recovery_period( $current_ids, $filters['start'], $filters['end'] ),
					'previous' => self::summarize_payments_recovery_period( $previous_ids, $filters['previous_start'], $filters['previous_end'] ),
				);
			} );
		}

		/**
		 * Build selected-period tax report data.
		 *
		 * @since 3.3
		 *
		 * @param array $filters Current report filters.
		 * @return array
		 */
		public static function build_taxes( $filters ) {
			return self::cached( 'taxes|' . wp_json_encode( $filters ), function () use ( $filters ) {
				$current_ids  = self::get_payment_ids( $filters['start'], $filters['end'], $filters );
				$previous_ids = self::get_payment_ids( $filters['previous_start'], $filters['previous_end'], $filters );

				return array(
					'current'  => self::summarize_taxes_period( $current_ids, $filters['start'], $filters['end'] ),
					'previous' => self::summarize_taxes_period( $previous_ids, $filters['previous_start'], $filters['previous_end'] ),
				);
			} );
		}

		/**
		 * Format money using RestroPress settings.
		 *
		 * @since 3.3
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		public static function money( $amount ) {
			return function_exists( 'rpress_currency_filter' )
				? rpress_currency_filter( rpress_format_amount( (float) $amount ) )
				: number_format_i18n( (float) $amount, 2 );
		}

		/**
		 * Format percentage delta.
		 *
		 * @since 3.3
		 *
		 * @param float $current Current value.
		 * @param float $previous Previous value.
		 * @param string $suffix Suffix.
		 * @return array
		 */
		public static function delta( $current, $previous, $suffix = '%' ) {
			if ( 0.0 === (float) $previous ) {
				return array(
					'label' => 0.0 === (float) $current ? esc_html__( 'No change', 'restropress' ) : esc_html__( 'No previous data', 'restropress' ),
					'tone'  => 'flat',
				);
			}

			$change = ( ( (float) $current - (float) $previous ) / (float) $previous ) * 100;
			return array(
				'label' => sprintf( '%s%s%s', $change > 0 ? '+' : '', number_format_i18n( $change, 1 ), $suffix ),
				'tone'  => $change >= 0 ? 'up' : 'down',
			);
		}

		/**
		 * Get period dates for a range key.
		 *
		 * @since 3.3
		 *
		 * @param string $range Range key.
		 * @return array
		 */
		protected static function get_period_for_range( $range, $custom_start = '', $custom_end = '' ) {
			$today_ts = current_time( 'timestamp' );
			$today = date_i18n( 'Y-m-d', $today_ts );

			if ( 'custom' === $range ) {
				$custom_start_ts = self::parse_report_date( $custom_start );
				$custom_end_ts = self::parse_report_date( $custom_end );

				if ( ! $custom_start_ts || ! $custom_end_ts ) {
					$custom_end_ts = $today_ts;
					$custom_start_ts = strtotime( '-29 days', $today_ts );
				}

				if ( $custom_start_ts > $custom_end_ts ) {
					$tmp = $custom_start_ts;
					$custom_start_ts = $custom_end_ts;
					$custom_end_ts = $tmp;
				}

				$start = date_i18n( 'Y-m-d', $custom_start_ts );
				$today = date_i18n( 'Y-m-d', $custom_end_ts );
				$days = max( 1, (int) floor( ( strtotime( $today ) - strtotime( $start ) ) / DAY_IN_SECONDS ) + 1 );
			} elseif ( 'today' === $range ) {
				$start = $today;
				$days = 1;
			} elseif ( 'yesterday' === $range ) {
				$start = date_i18n( 'Y-m-d', strtotime( '-1 day', $today_ts ) );
				$today = $start;
				$days = 1;
			} elseif ( 'this_week' === $range ) {
				$week_start = (int) get_option( 'start_of_week', 1 );
				$current_day = (int) date_i18n( 'w', $today_ts );
				$offset = ( $current_day - $week_start + 7 ) % 7;
				$start = date_i18n( 'Y-m-d', strtotime( '-' . $offset . ' days', $today_ts ) );
				$days = max( 1, $offset + 1 );
			} elseif ( 'last_week' === $range ) {
				$week_start = (int) get_option( 'start_of_week', 1 );
				$current_day = (int) date_i18n( 'w', $today_ts );
				$offset = ( $current_day - $week_start + 7 ) % 7;
				$this_week_start_ts = strtotime( '-' . $offset . ' days', $today_ts );
				$start_ts = strtotime( '-7 days', $this_week_start_ts );
				$end_ts = strtotime( '+6 days', $start_ts );
				$start = date_i18n( 'Y-m-d', $start_ts );
				$today = date_i18n( 'Y-m-d', $end_ts );
				$days = 7;
			} elseif ( 'last_7' === $range ) {
				$start = date_i18n( 'Y-m-d', strtotime( '-6 days', $today_ts ) );
				$days = 7;
			} elseif ( 'this_month' === $range ) {
				$start = date_i18n( 'Y-m-01', $today_ts );
				$days = max( 1, (int) date_i18n( 'j', $today_ts ) );
			} elseif ( 'last_month' === $range ) {
				$start_ts = strtotime( 'first day of previous month', $today_ts );
				$end_ts = strtotime( 'last day of previous month', $today_ts );
				$start = date_i18n( 'Y-m-d', $start_ts );
				$today = date_i18n( 'Y-m-d', $end_ts );
				$days = (int) date_i18n( 't', $start_ts );
			} else {
				$start = date_i18n( 'Y-m-d', strtotime( '-29 days', $today_ts ) );
				$days = 30;
			}

			$previous_end_ts = strtotime( $start . ' -1 day' );
			$previous_start_ts = strtotime( '-' . ( $days - 1 ) . ' days', $previous_end_ts );

			return array(
				'start'          => $start,
				'end'            => $today,
				'previous_start' => date_i18n( 'Y-m-d', $previous_start_ts ),
				'previous_end'   => date_i18n( 'Y-m-d', $previous_end_ts ),
			);
		}

		/**
		 * Parse a YYYY-MM-DD reports date.
		 *
		 * @since 3.3
		 *
		 * @param string $date Date.
		 * @return int
		 */
		protected static function parse_report_date( $date ) {
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				return 0;
			}

			$timestamp = strtotime( $date . ' 00:00:00' );
			return false === $timestamp ? 0 : $timestamp;
		}

		/**
		 * Summarize a date period.
		 *
		 * @since 3.3
		 *
		 * @param string $start Start date.
		 * @param string $end End date.
		 * @param array  $filters Filters.
		 * @return array
		 */
		protected static function summarize_period( $start, $end, $filters ) {
			$ids = self::get_payment_ids( $start, $end, $filters );
			$days = self::get_days_between( $start, $end );
			$trend = array();
			$customers = array();
			$repeat_customers = array();
			$service_totals = array(
				'delivery' => 0,
				'pickup'   => 0,
				'dinein'   => 0,
			);
			$service_sales = array(
				'delivery' => 0.0,
				'pickup'   => 0.0,
				'dinein'   => 0.0,
			);
			$payment_totals = array();

			foreach ( $days as $day ) {
				$trend[ $day ] = array(
					'gross'     => 0.0,
					'refunds'   => 0.0,
					'sales'     => 0.0,
					'tax'       => 0.0,
					'orders'    => 0,
					'completed' => 0,
					'cancelled' => 0,
					'refunded'  => 0,
					'failed'    => 0,
					'late'      => 0,
					'risk'      => 0,
					'delivery'  => 0,
					'pickup'    => 0,
					'dinein'    => 0,
				);
			}

			$summary = array(
				'ids'               => $ids,
				'orders'            => count( $ids ),
				'completed_orders'  => 0,
				'gross_sales'       => 0.0,
				'refund_amount'     => 0.0,
				'tax_collected'     => 0.0,
				'net_sales'         => 0.0,
				'aov'               => 0.0,
				'cancelled'         => 0,
				'refunded'          => 0,
				'failed_unpaid'     => 0,
				'late_orders'       => 0,
				'new_customers'     => 0,
				'repeat_customers'  => 0,
				'customer_count'    => 0,
				'trend'             => $trend,
				'service_totals'    => $service_totals,
				'service_sales'     => $service_sales,
				'payment_totals'    => $payment_totals,
			);

			foreach ( $ids as $payment_id ) {
				$post_status = get_post_status( $payment_id );
				$order_status = function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment_id ) ) : '';
				$total = (float) get_post_meta( $payment_id, '_rpress_payment_total', true );
				$tax = function_exists( 'rpress_get_payment_tax' ) ? (float) rpress_get_payment_tax( $payment_id ) : 0.0;
				$day = get_post_time( 'Y-m-d', false, $payment_id );
				$gateway = function_exists( 'rpress_get_payment_gateway' ) ? sanitize_key( rpress_get_payment_gateway( $payment_id ) ) : '';
				$is_refunded = 'refunded' === $post_status || 'refunded' === $order_status;
				$is_cancelled = 'cancelled' === $order_status;
				$is_paid = self::is_paid_order( $payment_id, $post_status, $order_status, $gateway );
				// "Failed / unpaid" is a payment-failure signal: genuinely
				// failed/abandoned orders, or an online order whose payment is
				// still pending. Cash orders are due on handoff, so a pending
				// cash order (new or accepted) is never a failure here -
				// otherwise normal COD inflates the risk rate.
				$is_cash = in_array( $gateway, self::cash_gateways(), true );
				$is_failed = in_array( $post_status, array( 'failed', 'abandoned' ), true )
					|| ( 'pending' === $post_status && ! $is_cash );
				$customer_key = self::get_customer_key( $payment_id );
				$service_type = function_exists( 'rpress_get_service_type' ) ? sanitize_key( rpress_get_service_type( $payment_id ) ) : '';

				if ( 'dine-in' === $service_type ) {
					$service_type = 'dinein';
				}

				if ( isset( $summary['service_totals'][ $service_type ] ) ) {
					$summary['service_totals'][ $service_type ]++;
				}

				if ( ! empty( $customer_key ) ) {
					if ( isset( $customers[ $customer_key ] ) ) {
						$repeat_customers[ $customer_key ] = true;
					}
					$customers[ $customer_key ] = true;
				}

				if ( isset( $summary['trend'][ $day ] ) ) {
					$summary['trend'][ $day ]['orders']++;
					if ( isset( $summary['trend'][ $day ][ $service_type ] ) ) {
						$summary['trend'][ $day ][ $service_type ]++;
					}
				}

				if ( $is_refunded ) {
					$summary['refunded']++;
					$summary['refund_amount'] += $total;
					if ( isset( $summary['trend'][ $day ] ) ) {
						$summary['trend'][ $day ]['refunds'] += $total;
						$summary['trend'][ $day ]['refunded']++;
					}
				}

				if ( $is_failed ) {
					$summary['failed_unpaid']++;
					if ( isset( $summary['trend'][ $day ] ) ) {
						$summary['trend'][ $day ]['failed']++;
					}
				}

				if ( $is_cancelled ) {
					$summary['cancelled']++;
					if ( isset( $summary['trend'][ $day ] ) ) {
						$summary['trend'][ $day ]['cancelled']++;
					}
				}

				if ( self::is_late_order( $payment_id ) ) {
					$summary['late_orders']++;
					if ( isset( $summary['trend'][ $day ] ) ) {
						$summary['trend'][ $day ]['late']++;
					}
				}

				if ( $is_refunded || $is_failed || $is_cancelled || self::is_late_order( $payment_id ) ) {
					if ( isset( $summary['trend'][ $day ] ) ) {
						$summary['trend'][ $day ]['risk']++;
					}
				}

				if ( $is_paid && ! $is_cancelled && ! $is_refunded ) {
					$summary['gross_sales'] += $total;
					$summary['tax_collected'] += $tax;
					$summary['completed_orders']++;

					if ( isset( $summary['trend'][ $day ] ) ) {
						$summary['trend'][ $day ]['gross'] += $total;
						$summary['trend'][ $day ]['sales'] += $total;
						$summary['trend'][ $day ]['tax'] += $tax;
						$summary['trend'][ $day ]['completed']++;
					}

					if ( isset( $summary['service_sales'][ $service_type ] ) ) {
						$summary['service_sales'][ $service_type ] += $total;
					}

					if ( ! empty( $gateway ) ) {
						if ( ! isset( $summary['payment_totals'][ $gateway ] ) ) {
							$summary['payment_totals'][ $gateway ] = 0.0;
						}
						$summary['payment_totals'][ $gateway ] += $total;
					}
				}
			}

			$summary['net_sales'] = max( 0, $summary['gross_sales'] - $summary['refund_amount'] );
			$summary['aov'] = $summary['completed_orders'] > 0 ? $summary['net_sales'] / $summary['completed_orders'] : 0.0;
			foreach ( $summary['trend'] as $day => $row ) {
				$summary['trend'][ $day ]['sales'] = max( 0, $row['gross'] - $row['refunds'] );
				$summary['trend'][ $day ]['aov'] = $summary['trend'][ $day ]['orders'] > 0 ? $summary['trend'][ $day ]['sales'] / $summary['trend'][ $day ]['orders'] : 0.0;
			}
			arsort( $summary['payment_totals'] );
			$summary['customer_count'] = count( $customers );
			$summary['repeat_customers'] = count( $repeat_customers );
			$summary['new_customers'] = max( 0, $summary['customer_count'] - $summary['repeat_customers'] );
			$summary['cancellation_rate'] = $summary['orders'] > 0 ? ( $summary['cancelled'] / $summary['orders'] ) * 100 : 0.0;

			return $summary;
		}

		/**
		 * Query payment IDs for a period.
		 *
		 * @since 3.3
		 *
		 * @param string $start Start date.
		 * @param string $end End date.
		 * @param array  $filters Filters.
		 * @return array
		 */
		protected static function get_payment_ids( $start, $end, $filters ) {
			$meta_query = array();

			if ( ! empty( $filters['service_type'] ) ) {
				$meta_query[] = array(
					'key'   => '_rpress_delivery_type',
					'value' => $filters['service_type'],
				);
			}

			if ( ! empty( $filters['order_status'] ) ) {
				$meta_query[] = array(
					'key'   => '_order_status',
					'value' => $filters['order_status'],
				);
			}

			$args = array(
				'post_type'              => 'rpress_payment',
				'post_status'            => ! empty( $filters['payment_status'] ) ? $filters['payment_status'] : 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'date_query'             => array(
					array(
						'after'     => $start . ' 00:00:00',
						'before'    => $end . ' 23:59:59',
						'inclusive' => true,
					),
				),
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			);

			if ( ! empty( $meta_query ) ) {
				$args['meta_query'] = $meta_query;
			}

			$query = new WP_Query( $args );
			$ids   = array_map( 'intval', $query->posts );

			// The query returns bare IDs, so WordPress primes nothing. Every
			// summarize_* loop then reads post status + meta per order, which
			// is an N+1 (~2.5 queries/order -> ~1,100 queries for a few hundred
			// orders, timing out on large all-time ranges). Warm the post and
			// meta caches once here so those per-order reads are served from
			// cache - the single biggest lever on reports page load.
			if ( ! empty( $ids ) ) {
				_prime_post_caches( $ids, false, true );
			}

			return $ids;
		}

		/**
		 * Build a risk state from summary data.
		 *
		 * @since 3.3
		 *
		 * @param array $current Current period.
		 * @param array $previous Previous period.
		 * @return array
		 */
		protected static function build_risk_state( $current, $previous ) {
			$risk_count = $current['late_orders'] + $current['cancelled'] + $current['refunded'] + $current['failed_unpaid'];
			$risk_rate = $current['orders'] > 0 ? ( $risk_count / $current['orders'] ) * 100 : 0;
			$previous_risk_count = $previous['late_orders'] + $previous['cancelled'] + $previous['refunded'] + $previous['failed_unpaid'];
			$previous_risk_rate = $previous['orders'] > 0 ? ( $previous_risk_count / $previous['orders'] ) * 100 : 0;
			$label = esc_html__( 'Calm', 'restropress' );
			$tone = 'green';

			if ( $risk_rate >= 10 || $current['failed_unpaid'] >= 10 ) {
				$label = esc_html__( 'Critical', 'restropress' );
				$tone = 'red';
			} elseif ( $risk_rate >= 5 || $risk_rate > $previous_risk_rate ) {
				$label = esc_html__( 'Watch', 'restropress' );
				$tone = 'amber';
			}

			return array(
				'label' => $label,
				'tone'  => $tone,
				'text'  => sprintf(
					/* translators: 1: risk count, 2: risk rate. */
					esc_html__( '%1$d orders need review in this period. Risk rate is %2$s%%.', 'restropress' ),
					$risk_count,
					number_format_i18n( $risk_rate, 1 )
				),
			);
		}

		/**
		 * Build ranked insights.
		 *
		 * @since 3.3
		 *
		 * @param array $current Current period.
		 * @param array $previous Previous period.
		 * @return array
		 */
		protected static function build_insights( $current, $previous ) {
			$insights = array();

			if ( $current['failed_unpaid'] > 0 ) {
				$insights[] = sprintf( _n( '%d failed or unpaid order needs recovery review.', '%d failed or unpaid orders need recovery review.', $current['failed_unpaid'], 'restropress' ), $current['failed_unpaid'] );
			}

			if ( $current['refund_amount'] > $previous['refund_amount'] && $current['refund_amount'] > 0 ) {
				$insights[] = esc_html__( 'Refund amount increased versus the previous period.', 'restropress' );
			}

			if ( $current['late_orders'] > 0 ) {
				$insights[] = sprintf( _n( '%d late order is affecting service quality.', '%d late orders are affecting service quality.', $current['late_orders'], 'restropress' ), $current['late_orders'] );
			}

			if ( isset( $current['service_totals']['pickup'], $current['service_totals']['delivery'] ) && $current['service_totals']['pickup'] > $current['service_totals']['delivery'] ) {
				$insights[] = esc_html__( 'Pickup demand is ahead of delivery in the selected period.', 'restropress' );
			}

			if ( empty( $insights ) ) {
				$insights[] = esc_html__( 'No major risk movement detected for this period.', 'restropress' );
			}

			return array_slice( $insights, 0, 4 );
		}

		/**
		 * Summarize menu item, category, and add-on movement for payment IDs.
		 *
		 * @since 3.3
		 *
		 * @param array $payment_ids Payment IDs.
		 * @return array
		 */
		protected static function summarize_menu_period( $payment_ids ) {
			$items           = array();
			$categories      = array();
			$addons          = array();
			$total_quantity  = 0;
			$total_net_sales = 0.0;
			$risk_count      = 0;

			foreach ( (array) $payment_ids as $payment_id ) {
				$post_status  = get_post_status( $payment_id );
				$order_status = function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment_id ) ) : '';
				$is_refunded  = 'refunded' === $post_status || 'refunded' === $order_status;
				$is_cancelled = 'cancelled' === $order_status;
				$is_paid      = self::is_paid_order( $payment_id, $post_status, $order_status );
				$cart_details = function_exists( 'rpress_get_payment_meta_cart_details' ) ? rpress_get_payment_meta_cart_details( $payment_id ) : array();

				if ( empty( $cart_details ) || ! is_array( $cart_details ) ) {
					continue;
				}

				foreach ( $cart_details as $cart_item ) {
					$item_id = isset( $cart_item['id'] ) ? absint( $cart_item['id'] ) : 0;
					if ( ! $item_id ) {
						continue;
					}

					$quantity = isset( $cart_item['quantity'] ) ? max( 1, absint( $cart_item['quantity'] ) ) : 1;
					$line_net = isset( $cart_item['price'] ) ? (float) $cart_item['price'] : 0.0;

					if ( ! isset( $items[ $item_id ] ) ) {
						$items[ $item_id ] = array(
							'id'          => $item_id,
							'name'        => get_the_title( $item_id ),
							'quantity'    => 0,
							'net_sales'   => 0.0,
							'risk_count'  => 0,
							'availability_status' => self::get_fooditem_availability_status( $item_id ),
							'availability' => self::get_fooditem_availability_label( $item_id ),
						);
					}

					if ( $is_paid ) {
						$items[ $item_id ]['quantity'] += $quantity;
						$items[ $item_id ]['net_sales'] += $line_net;
						$total_quantity += $quantity;
						$total_net_sales += $line_net;
						self::add_item_category_sales( $categories, $item_id, $line_net );

						if ( ! empty( $cart_item['item_number']['options']['addon_items'] ) ) {
							self::collect_addon_sales( $cart_item['item_number']['options']['addon_items'], $addons, $quantity );
						}
					}

					if ( $is_refunded || $is_cancelled ) {
						$items[ $item_id ]['risk_count']++;
						$risk_count++;
					}
				}
			}

			$published_items = get_posts(
				array(
					'post_type'              => 'fooditem',
					'post_status'            => 'publish',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => true,
				)
			);

			foreach ( $published_items as $item_id ) {
				$item_id = absint( $item_id );
				if ( isset( $items[ $item_id ] ) ) {
					continue;
				}
				$items[ $item_id ] = array(
					'id'          => $item_id,
					'name'        => get_the_title( $item_id ),
					'quantity'    => 0,
					'net_sales'   => 0.0,
					'risk_count'  => 0,
					'availability_status' => self::get_fooditem_availability_status( $item_id ),
					'availability' => self::get_fooditem_availability_label( $item_id ),
				);
			}

			$top_quantity = array_values( $items );
			usort(
				$top_quantity,
				function( $a, $b ) {
					if ( $a['quantity'] === $b['quantity'] ) {
						return $b['net_sales'] <=> $a['net_sales'];
					}
					return $b['quantity'] <=> $a['quantity'];
				}
			);

			$top_sales = array_values( $items );
			usort(
				$top_sales,
				function( $a, $b ) {
					if ( $a['net_sales'] === $b['net_sales'] ) {
						return $b['quantity'] <=> $a['quantity'];
					}
					return $b['net_sales'] <=> $a['net_sales'];
				}
			);

			$low_sellers = array_values( $items );
			usort(
				$low_sellers,
				function( $a, $b ) {
					if ( $a['quantity'] === $b['quantity'] ) {
						return strcmp( $a['name'], $b['name'] );
					}
					return $a['quantity'] <=> $b['quantity'];
				}
			);

			usort(
				$categories,
				function( $a, $b ) {
					return $b['net_sales'] <=> $a['net_sales'];
				}
			);

			usort(
				$addons,
				function( $a, $b ) {
					return $b['net_sales'] <=> $a['net_sales'];
				}
			);

			return array(
				'total_quantity'  => $total_quantity,
				'total_net_sales' => $total_net_sales,
				'risk_count'      => $risk_count,
				'item_count'      => count( $published_items ),
				'zero_sellers'    => count( array_filter( $items, function( $item ) { return 0 === (int) $item['quantity']; } ) ),
				'top_quantity'    => array_slice( $top_quantity, 0, 5 ),
				'top_sales'       => array_slice( $top_sales, 0, 5 ),
				'low_sellers'     => array_slice( $low_sellers, 0, 6 ),
				'category_mix'    => array_slice( $categories, 0, 6 ),
				'addons'          => array_slice( $addons, 0, 6 ),
				'all_items'       => $top_sales,
			);
		}

		/**
		 * Get current menu readiness counts.
		 *
		 * @since 3.3
		 *
		 * @return array
		 */
		protected static function get_menu_readiness() {
			$published = get_posts(
				array(
					'post_type'      => 'fooditem',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$unavailable = get_posts(
				array(
					'post_type'      => 'fooditem',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => 'rp_stock_status',
					'meta_value'     => 'unavailable',
					'no_found_rows'  => true,
				)
			);

			return array(
				'published'   => count( $published ),
				'unavailable' => count( $unavailable ),
			);
		}

		/**
		 * Add selected-period item sales to the item's primary category.
		 *
		 * @since 3.3
		 *
		 * @param array $categories Category rows by ID.
		 * @param int   $item_id Item ID.
		 * @param float $line_net Line net sales.
		 * @return void
		 */
		protected static function add_item_category_sales( &$categories, $item_id, $line_net ) {
			$terms = get_the_terms( $item_id, 'food-category' );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				$term_id = 0;
				$name = __( 'Uncategorized', 'restropress' );
			} else {
				$term = reset( $terms );
				$term_id = absint( $term->term_id );
				$name = $term->name;
			}

			if ( ! isset( $categories[ $term_id ] ) ) {
				$categories[ $term_id ] = array(
					'name'      => $name,
					'net_sales' => 0.0,
				);
			}

			$categories[ $term_id ]['net_sales'] += (float) $line_net;
		}

		/**
		 * Collect add-on sales from stored cart options.
		 *
		 * @since 3.3
		 *
		 * @param array $addon_items Add-on data.
		 * @param array $addons Add-on rows by label.
		 * @param int   $quantity Parent item quantity.
		 * @return void
		 */
		protected static function collect_addon_sales( $addon_items, &$addons, $quantity ) {
			foreach ( (array) $addon_items as $addon ) {
				if ( ! is_array( $addon ) ) {
					continue;
				}

				$name = '';
				foreach ( array( 'name', 'label', 'addon_name', 'item_name', 'title' ) as $name_key ) {
					if ( ! empty( $addon[ $name_key ] ) && ! is_array( $addon[ $name_key ] ) ) {
						$name = sanitize_text_field( $addon[ $name_key ] );
						break;
					}
				}

				$amount = 0.0;
				foreach ( array( 'price', 'addon_price', 'amount' ) as $price_key ) {
					if ( isset( $addon[ $price_key ] ) && ! is_array( $addon[ $price_key ] ) ) {
						$amount = (float) $addon[ $price_key ];
						break;
					}
				}

				if ( '' !== $name ) {
					$key = sanitize_key( $name );
					if ( ! isset( $addons[ $key ] ) ) {
						$addons[ $key ] = array(
							'name'      => $name,
							'quantity'  => 0,
							'net_sales' => 0.0,
						);
					}
					$addons[ $key ]['quantity'] += max( 1, (int) $quantity );
					$addons[ $key ]['net_sales'] += $amount * max( 1, (int) $quantity );
				}

				self::collect_addon_sales( $addon, $addons, $quantity );
			}
		}

		/**
		 * Get a plain availability label for a food item.
		 *
		 * @since 3.3
		 *
		 * @param int $item_id Item ID.
		 * @return string
		 */
		protected static function get_fooditem_availability_label( $item_id ) {
			if ( 'unavailable' === self::get_fooditem_availability_status( $item_id ) ) {
				return __( 'Unavailable', 'restropress' );
			}

			return __( 'Available', 'restropress' );
		}

		/**
		 * Get a normalized availability status for a food item.
		 *
		 * @since 3.3
		 *
		 * @param int $item_id Item ID.
		 * @return string
		 */
		protected static function get_fooditem_availability_status( $item_id ) {
			if ( function_exists( 'rpress_fooditem_is_unavailable' ) && rpress_fooditem_is_unavailable( $item_id ) ) {
				return 'unavailable';
			}

			return 'available';
		}

		/**
		 * Summarize customer behavior for a selected period.
		 *
		 * @since 3.3
		 *
		 * @param array  $payment_ids Payment IDs.
		 * @param string $start Start date.
		 * @param string $end End date.
		 * @return array
		 */
		protected static function summarize_customers_period( $payment_ids, $start, $end, $limit = 50 ) {
			$customers      = array();
			$first_order_ts = array();
			$days           = self::get_days_between( $start, $end );
			$trend_sets     = array();

			foreach ( $days as $day ) {
				$trend_sets[ $day ] = array(
					'new'       => array(),
					'returning' => array(),
					'orders'    => array(),
				);
			}

			foreach ( (array) $payment_ids as $payment_id ) {
				$key = self::get_customer_key( $payment_id );
				if ( empty( $key ) ) {
					$key = 'order:' . absint( $payment_id );
				}

				if ( ! isset( $first_order_ts[ $key ] ) ) {
					$first_order_ts[ $key ] = self::get_customer_first_order_timestamp( $payment_id, $key );
				}

				if ( ! isset( $customers[ $key ] ) ) {
					$profile = self::get_customer_profile( $payment_id );
					$customers[ $key ] = array(
						'key'              => $key,
						'customer_id'      => $profile['customer_id'],
						'name'             => $profile['name'],
						'email'            => $profile['email'],
						'orders'           => 0,
						'paid_orders'      => 0,
						'total_spend'      => 0.0,
						'avg_order'        => 0.0,
						'last_order_ts'    => 0,
						'last_order_label' => '',
						'service_counts'   => array(),
						'service_preference' => __( 'Not captured', 'restropress' ),
						'is_new'           => false,
					);
				}

				$post_status  = get_post_status( $payment_id );
				$order_status = function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment_id ) ) : '';
				$is_refunded  = 'refunded' === $post_status || 'refunded' === $order_status;
				$is_cancelled = 'cancelled' === $order_status;
				$is_paid      = self::is_paid_order( $payment_id, $post_status, $order_status );
				$order_ts     = (int) get_post_time( 'U', false, $payment_id );
				$day          = get_post_time( 'Y-m-d', false, $payment_id );
				$total        = (float) get_post_meta( $payment_id, '_rpress_payment_total', true );
				$service_type = function_exists( 'rpress_get_service_type' ) ? sanitize_key( rpress_get_service_type( $payment_id ) ) : '';

				if ( 'dine-in' === $service_type ) {
					$service_type = 'dinein';
				}

				$customers[ $key ]['orders']++;
				if ( $is_paid ) {
					$customers[ $key ]['paid_orders']++;
					$customers[ $key ]['total_spend'] += $total;
				}

				if ( ! empty( $service_type ) ) {
					if ( ! isset( $customers[ $key ]['service_counts'][ $service_type ] ) ) {
						$customers[ $key ]['service_counts'][ $service_type ] = 0;
					}
					$customers[ $key ]['service_counts'][ $service_type ]++;
				}

				if ( $order_ts > $customers[ $key ]['last_order_ts'] ) {
					$customers[ $key ]['last_order_ts'] = $order_ts;
					$customers[ $key ]['last_order_label'] = date_i18n( get_option( 'date_format' ), $order_ts );
				}

				if ( isset( $trend_sets[ $day ] ) ) {
					$trend_sets[ $day ]['orders'][ $key ] = isset( $trend_sets[ $day ]['orders'][ $key ] ) ? $trend_sets[ $day ]['orders'][ $key ] + 1 : 1;
					$day_start = strtotime( $day . ' 00:00:00' );
					if ( $first_order_ts[ $key ] >= $day_start ) {
						$trend_sets[ $day ]['new'][ $key ] = true;
					} else {
						$trend_sets[ $day ]['returning'][ $key ] = true;
					}
				}
			}

			$start_ts = strtotime( $start . ' 00:00:00' );
			$total_spend = 0.0;
			$new_customers = 0;
			$returning_customers = 0;
			$repeat_customers = 0;

			foreach ( $customers as $key => $customer ) {
				$customers[ $key ]['is_new'] = ! empty( $first_order_ts[ $key ] ) && $first_order_ts[ $key ] >= $start_ts;
				if ( $customers[ $key ]['is_new'] ) {
					$new_customers++;
				} else {
					$returning_customers++;
				}

				if ( $customer['orders'] >= 2 ) {
					$repeat_customers++;
				}

				$customers[ $key ]['avg_order'] = $customer['paid_orders'] > 0 ? $customer['total_spend'] / $customer['paid_orders'] : 0.0;
				$total_spend += $customer['total_spend'];

				if ( ! empty( $customer['service_counts'] ) ) {
					arsort( $customers[ $key ]['service_counts'] );
					$service_key = key( $customers[ $key ]['service_counts'] );
					$customers[ $key ]['service_preference'] = self::get_service_label( $service_key );
				}
			}

			$trend = array();
			foreach ( $trend_sets as $day => $sets ) {
				$daily_customers = count( $sets['orders'] );
				$daily_repeat = count(
					array_filter(
						$sets['orders'],
						function( $count ) {
							return (int) $count >= 2;
						}
					)
				);
				$trend[ $day ] = array(
					'new'         => count( $sets['new'] ),
					'returning'   => count( $sets['returning'] ),
					'repeat_rate' => $daily_customers > 0 ? ( $daily_repeat / $daily_customers ) * 100 : 0.0,
				);
			}

			$rows = array_values( $customers );
			usort(
				$rows,
				function( $a, $b ) {
					if ( $a['total_spend'] === $b['total_spend'] ) {
						return $b['orders'] <=> $a['orders'];
					}
					return $b['total_spend'] <=> $a['total_spend'];
				}
			);

			$total_customers = count( $customers );

			return array(
				'total_customers'    => $total_customers,
				'new_customers'      => $new_customers,
				'returning_customers' => $returning_customers,
				'repeat_customers'   => $repeat_customers,
				'repeat_rate'        => $total_customers > 0 ? ( $repeat_customers / $total_customers ) * 100 : 0.0,
				'avg_spend'          => $total_customers > 0 ? $total_spend / $total_customers : 0.0,
				'total_spend'        => $total_spend,
				'trend'              => $trend,
				'rows'               => $limit > 0 ? array_slice( $rows, 0, $limit ) : $rows,
			);
		}

		/**
		 * Build a displayable customer profile from an order.
		 *
		 * @since 3.3
		 *
		 * @param int $payment_id Payment ID.
		 * @return array
		 */
		protected static function get_customer_profile( $payment_id ) {
			$customer_id = function_exists( 'rpress_get_payment_customer_id' ) ? absint( rpress_get_payment_customer_id( $payment_id ) ) : 0;
			$email       = function_exists( 'rpress_get_payment_user_email' ) ? sanitize_email( rpress_get_payment_user_email( $payment_id ) ) : '';
			$user_info   = function_exists( 'rpress_get_payment_meta_user_info' ) ? rpress_get_payment_meta_user_info( $payment_id ) : array();
			$name        = '';

			if ( $customer_id && class_exists( 'RPRESS_Customer' ) ) {
				$customer = new RPRESS_Customer( $customer_id );
				if ( ! empty( $customer->name ) ) {
					$name = $customer->name;
				}
				if ( empty( $email ) && ! empty( $customer->email ) ) {
					$email = sanitize_email( $customer->email );
				}
			}

			if ( empty( $name ) && is_array( $user_info ) ) {
				$name = trim( ( isset( $user_info['first_name'] ) ? $user_info['first_name'] : '' ) . ' ' . ( isset( $user_info['last_name'] ) ? $user_info['last_name'] : '' ) );
				if ( empty( $email ) && ! empty( $user_info['email'] ) ) {
					$email = sanitize_email( $user_info['email'] );
				}
			}

			if ( empty( $name ) ) {
				$name = ! empty( $email ) ? $email : sprintf( __( 'Guest order #%d', 'restropress' ), absint( $payment_id ) );
			}

			return array(
				'customer_id' => $customer_id,
				'name'        => $name,
				'email'       => $email,
			);
		}

		/**
		 * Get first known order timestamp for a customer key.
		 *
		 * @since 3.3
		 *
		 * @param int    $payment_id Payment ID.
		 * @param string $key Customer key.
		 * @return int
		 */
		protected static function get_customer_first_order_timestamp( $payment_id, $key ) {
			$customer_id = function_exists( 'rpress_get_payment_customer_id' ) ? absint( rpress_get_payment_customer_id( $payment_id ) ) : 0;
			$email       = function_exists( 'rpress_get_payment_user_email' ) ? sanitize_email( rpress_get_payment_user_email( $payment_id ) ) : '';
			$payment_ids = array();

			if ( $customer_id && class_exists( 'RPRESS_Customer' ) ) {
				$customer = new RPRESS_Customer( $customer_id );
				if ( ! empty( $customer->payment_ids ) ) {
					$payment_ids = array_filter( array_map( 'absint', explode( ',', (string) $customer->payment_ids ) ) );
				}
			}

			if ( empty( $payment_ids ) && ! empty( $email ) ) {
				$query = new WP_Query(
					array(
						'post_type'              => 'rpress_payment',
						'post_status'            => 'any',
						'posts_per_page'         => 1,
						'fields'                 => 'ids',
						'meta_key'               => '_rpress_payment_user_email',
						'meta_value'             => $email,
						'orderby'                => 'date',
						'order'                  => 'ASC',
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					)
				);
				$payment_ids = array_map( 'absint', $query->posts );
			}

			if ( empty( $payment_ids ) ) {
				return (int) get_post_time( 'U', false, $payment_id );
			}

			$first_ts = 0;
			foreach ( $payment_ids as $known_payment_id ) {
				$known_ts = (int) get_post_time( 'U', false, $known_payment_id );
				if ( $known_ts && ( 0 === $first_ts || $known_ts < $first_ts ) ) {
					$first_ts = $known_ts;
				}
			}

			return $first_ts ? $first_ts : (int) get_post_time( 'U', false, $payment_id );
		}

		/**
		 * Get a service type label.
		 *
		 * @since 3.3
		 *
		 * @param string $service_key Service key.
		 * @return string
		 */
		protected static function get_service_label( $service_key ) {
			$labels = array(
				'delivery' => function_exists( 'rpress_service_label' ) ? rpress_service_label( 'delivery' ) : __( 'Delivery', 'restropress' ),
				'pickup'   => function_exists( 'rpress_service_label' ) ? rpress_service_label( 'pickup' ) : __( 'Pickup', 'restropress' ),
				'dinein'   => __( 'Dine-in', 'restropress' ),
			);

			return isset( $labels[ $service_key ] ) ? $labels[ $service_key ] : ucwords( str_replace( array( '-', '_' ), ' ', $service_key ) );
		}

		/**
		 * Summarize payment health and recovery rows for a selected period.
		 *
		 * @since 3.3
		 *
		 * @param array  $payment_ids Payment IDs.
		 * @param string $start Start date.
		 * @param string $end End date.
		 * @return array
		 */
		protected static function summarize_payments_recovery_period( $payment_ids, $start, $end ) {
			$days = self::get_days_between( $start, $end );
			$trend = array();

			foreach ( $days as $day ) {
				$trend[ $day ] = array(
					'refunds'  => 0,
					'failures' => 0,
				);
			}

			$summary = array(
				'paid_orders'     => 0,
				'pending_unpaid'  => 0,
				'failed_orders'   => 0,
				'refunded_orders' => 0,
				'refund_amount'   => 0.0,
				'refund_rate'     => 0.0,
				'payment_totals'  => array(),
				'trend'           => $trend,
				'recovery_rows'   => array(),
			);

			foreach ( (array) $payment_ids as $payment_id ) {
				$post_status  = get_post_status( $payment_id );
				$order_status = function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment_id ) ) : '';
				$total        = (float) get_post_meta( $payment_id, '_rpress_payment_total', true );
				$day          = get_post_time( 'Y-m-d', false, $payment_id );
				$gateway      = function_exists( 'rpress_get_payment_gateway' ) ? sanitize_key( rpress_get_payment_gateway( $payment_id ) ) : '';
				$is_refunded  = 'refunded' === $post_status || 'refunded' === $order_status;
				$is_paid      = self::is_paid_order( $payment_id, $post_status, $order_status, $gateway );
				// Cash orders are due on handoff, so an unpaid cash order is not
				// a payment-recovery case (new or accepted). Only unpaid online
				// orders surface here.
				$is_cash      = in_array( $gateway, self::cash_gateways(), true );
				$is_pending   = ! $is_paid && ! $is_refunded && in_array( $post_status, array( 'pending', 'processing' ), true ) && ! $is_cash;
				$is_failed    = in_array( $post_status, array( 'failed', 'abandoned' ), true );

				if ( $is_paid ) {
					$summary['paid_orders']++;
					if ( ! empty( $gateway ) ) {
						if ( ! isset( $summary['payment_totals'][ $gateway ] ) ) {
							$summary['payment_totals'][ $gateway ] = 0.0;
						}
						$summary['payment_totals'][ $gateway ] += $total;
					}
				}

				if ( $is_pending ) {
					$summary['pending_unpaid']++;
				}

				if ( $is_failed ) {
					$summary['failed_orders']++;
					if ( isset( $summary['trend'][ $day ] ) ) {
						$summary['trend'][ $day ]['failures']++;
					}
				}

				if ( $is_refunded ) {
					$summary['refunded_orders']++;
					$summary['refund_amount'] += $total;
					if ( isset( $summary['trend'][ $day ] ) ) {
						$summary['trend'][ $day ]['refunds']++;
					}
				}

				if ( $is_pending || $is_failed || $is_refunded ) {
					$summary['recovery_rows'][] = self::build_recovery_row( $payment_id, $post_status, $order_status, $total, $gateway );
				}
			}

			$total_considered = $summary['paid_orders'] + $summary['pending_unpaid'] + $summary['failed_orders'] + $summary['refunded_orders'];
			$summary['refund_rate'] = $total_considered > 0 ? ( $summary['refunded_orders'] / $total_considered ) * 100 : 0.0;
			arsort( $summary['payment_totals'] );
			usort(
				$summary['recovery_rows'],
				function( $a, $b ) {
					if ( $a['priority'] === $b['priority'] ) {
						return $b['timestamp'] <=> $a['timestamp'];
					}

					return $b['priority'] <=> $a['priority'];
				}
			);
			$summary['recovery_rows'] = array_slice( $summary['recovery_rows'], 0, 25 );

			return $summary;
		}

		/**
		 * Build one recovery table row.
		 *
		 * @since 3.3
		 *
		 * @param int    $payment_id Payment ID.
		 * @param string $post_status Payment status.
		 * @param string $order_status Order status.
		 * @param float  $total Payment total.
		 * @param string $gateway Gateway key.
		 * @return array
		 */
		protected static function build_recovery_row( $payment_id, $post_status, $order_status, $total, $gateway ) {
			$profile      = self::get_customer_profile( $payment_id );
			$is_refunded  = 'refunded' === $post_status || 'refunded' === $order_status;
			$is_failed    = in_array( $post_status, array( 'failed', 'abandoned' ), true );
			$is_pending   = in_array( $post_status, array( 'pending', 'processing' ), true );
			$service_type = function_exists( 'rpress_get_service_type' ) ? sanitize_key( rpress_get_service_type( $payment_id ) ) : '';
			$issue        = __( 'Needs review', 'restropress' );
			$priority     = 1;

			if ( $is_failed ) {
				$issue = 'abandoned' === $post_status ? __( 'Abandoned checkout', 'restropress' ) : __( 'Payment failed', 'restropress' );
				$priority = 3;
			} elseif ( $is_pending ) {
				$issue = 'processing' === $post_status ? __( 'Payment processing', 'restropress' ) : __( 'Pending / unpaid', 'restropress' );
				$priority = 2;
			} elseif ( $is_refunded ) {
				$issue = __( 'Refund issued', 'restropress' );
			}

			if ( 'dine-in' === $service_type ) {
				$service_type = 'dinein';
			}

			$timestamp = (int) get_post_time( 'U', false, $payment_id );

			return array(
				'order'          => function_exists( 'rpress_get_payment_number' ) ? rpress_get_payment_number( $payment_id ) : $payment_id,
				'date'           => date_i18n( get_option( 'date_format' ), $timestamp ),
				'timestamp'      => $timestamp,
				'customer'       => $profile['name'],
				'issue'          => $issue,
				'amount'         => $total,
				'service'        => self::get_service_label( $service_type ),
				'payment_method' => self::get_gateway_label( $gateway ),
				'action_url'     => admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details&id=' . absint( $payment_id ) ),
				'priority'       => $priority,
			);
		}

		/**
		 * Get a payment gateway label.
		 *
		 * @since 3.3
		 *
		 * @param string $gateway Gateway key.
		 * @return string
		 */
		protected static function get_gateway_label( $gateway ) {
			$gateways = function_exists( 'rpress_get_payment_gateways' ) ? rpress_get_payment_gateways() : array();
			if ( isset( $gateways[ $gateway ]['admin_label'] ) ) {
				return $gateways[ $gateway ]['admin_label'];
			}
			if ( isset( $gateways[ $gateway ]['checkout_label'] ) ) {
				return $gateways[ $gateway ]['checkout_label'];
			}

			return ! empty( $gateway ) ? ucwords( str_replace( array( '-', '_' ), ' ', $gateway ) ) : __( 'Not captured', 'restropress' );
		}

		/**
		 * Summarize tax values for a selected period.
		 *
		 * @since 3.3
		 *
		 * @param array  $payment_ids Payment IDs.
		 * @param string $start Start date.
		 * @param string $end End date.
		 * @return array
		 */
		protected static function summarize_taxes_period( $payment_ids, $start, $end ) {
			$days = self::get_days_between( $start, $end );
			$rows = array();

			foreach ( $days as $day ) {
				$rows[ $day ] = array(
					'date'                  => date_i18n( get_option( 'date_format' ), strtotime( $day ) ),
					'taxable_sales'         => 0.0,
					'non_taxable_sales'     => 0.0,
					'tax_collected'         => 0.0,
					'taxable_orders'        => 0,
					'orders'                => 0,
					'refund_tax_adjustment' => 0.0,
				);
			}

			$summary = array(
				'tax_collected'     => 0.0,
				'taxable_sales'     => 0.0,
				'non_taxable_sales' => 0.0,
				'net_sales'         => 0.0,
				'orders'            => 0,
				'taxable_orders'    => 0,
				'rows'              => $rows,
			);

			foreach ( (array) $payment_ids as $payment_id ) {
				$post_status  = get_post_status( $payment_id );
				$order_status = function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment_id ) ) : '';
				$total        = (float) get_post_meta( $payment_id, '_rpress_payment_total', true );
				$tax          = function_exists( 'rpress_get_payment_tax' ) ? (float) rpress_get_payment_tax( $payment_id ) : 0.0;
				$day          = get_post_time( 'Y-m-d', false, $payment_id );
				$is_refunded  = 'refunded' === $post_status || 'refunded' === $order_status;
				$is_cancelled = 'cancelled' === $order_status;
				$is_paid      = 'publish' === $post_status && ! $is_refunded && ! $is_cancelled;

				if ( $is_refunded && isset( $summary['rows'][ $day ] ) ) {
					$summary['rows'][ $day ]['refund_tax_adjustment'] += $tax;
					continue;
				}

				if ( ! $is_paid ) {
					continue;
				}

				$sales_ex_tax = max( 0.0, $total - $tax );
				$summary['orders']++;
				$summary['tax_collected'] += $tax;
				$summary['net_sales'] += $sales_ex_tax;

				if ( $tax > 0 ) {
					$summary['taxable_sales'] += $sales_ex_tax;
					$summary['taxable_orders']++;
				} else {
					$summary['non_taxable_sales'] += $sales_ex_tax;
				}

				if ( isset( $summary['rows'][ $day ] ) ) {
					$summary['rows'][ $day ]['orders']++;
					$summary['rows'][ $day ]['tax_collected'] += $tax;
					if ( $tax > 0 ) {
						$summary['rows'][ $day ]['taxable_sales'] += $sales_ex_tax;
						$summary['rows'][ $day ]['taxable_orders']++;
					} else {
						$summary['rows'][ $day ]['non_taxable_sales'] += $sales_ex_tax;
					}
				}
			}

			$summary['rows'] = array_reverse( $summary['rows'], true );

			return $summary;
		}

		/**
		 * Return all day keys in a period.
		 *
		 * @since 3.3
		 *
		 * @param string $start Start date.
		 * @param string $end End date.
		 * @return array
		 */
		protected static function get_days_between( $start, $end ) {
			$days = array();
			$cursor = strtotime( $start . ' 00:00:00' );
			$end_ts = strtotime( $end . ' 00:00:00' );

			while ( $cursor <= $end_ts ) {
				$days[] = date_i18n( 'Y-m-d', $cursor );
				$cursor = strtotime( '+1 day', $cursor );
			}

			return $days;
		}

		/**
		 * Get customer identity key.
		 *
		 * @since 3.3
		 *
		 * @param int $payment_id Payment ID.
		 * @return string
		 */
		protected static function get_customer_key( $payment_id ) {
			$customer_id = function_exists( 'rpress_get_payment_customer_id' ) ? absint( rpress_get_payment_customer_id( $payment_id ) ) : 0;
			if ( $customer_id ) {
				return 'customer:' . $customer_id;
			}

			$email = function_exists( 'rpress_get_payment_user_email' ) ? rpress_get_payment_user_email( $payment_id ) : '';
			return $email ? 'email:' . strtolower( sanitize_email( $email ) ) : '';
		}

		/**
		 * Gateways where an unpaid payment status means "cash due on handoff",
		 * not a payment problem. Shares the command center's filter so every
		 * screen agrees on which gateways are cash-like.
		 *
		 * @since 3.3
		 * @return array
		 */
		protected static function cash_gateways() {
			if ( class_exists( 'RPRESS_Command_Center_Dashboard' ) && method_exists( 'RPRESS_Command_Center_Dashboard', 'cash_gateways' ) ) {
				return RPRESS_Command_Center_Dashboard::cash_gateways();
			}
			return apply_filters( 'rpress_command_center_cash_gateways', array( 'cash_on_delivery', 'cash', 'cod', 'manual' ) );
		}

		/**
		 * Cash-aware "counts as revenue" test, matching the orders table and
		 * dashboard: a paid online order, or an accepted cash/COD order that is
		 * no longer awaiting action. Cancelled/refunded/failed never count.
		 *
		 * @since 3.3
		 * @param int    $payment_id   Payment ID.
		 * @param string $post_status  Payment post status.
		 * @param string $order_status Fulfilment status.
		 * @param string $gateway      Payment gateway slug.
		 * @return bool
		 */
		protected static function is_paid_order( $payment_id, $post_status = null, $order_status = null, $gateway = null ) {
			$post_status  = null === $post_status ? get_post_status( $payment_id ) : $post_status;
			$order_status = null === $order_status ? ( function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment_id ) ) : '' ) : $order_status;
			if ( in_array( $order_status, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
				return false;
			}
			if ( in_array( $post_status, array( 'refunded', 'failed' ), true ) ) {
				return false;
			}
			if ( 'publish' === $post_status ) {
				return true;
			}
			$gateway = null === $gateway ? ( function_exists( 'rpress_get_payment_gateway' ) ? sanitize_key( rpress_get_payment_gateway( $payment_id ) ) : '' ) : $gateway;
			return in_array( $gateway, self::cash_gateways(), true ) && 'pending' !== $order_status;
		}

		/**
		 * Basic late-order detection for reports.
		 *
		 * @since 3.3
		 *
		 * @param int $payment_id Payment ID.
		 * @return bool
		 */
		protected static function is_late_order( $payment_id ) {
			$order_status = function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment_id ) ) : '';
			if ( ! in_array( $order_status, array( 'completed', 'cancelled', 'refunded', 'failed' ), true ) ) {
				return false;
			}

			$date = trim( (string) get_post_meta( $payment_id, '_rpress_delivery_date', true ) );
			$time = trim( (string) get_post_meta( $payment_id, '_rpress_delivery_time', true ) );
			if ( empty( $date ) ) {
				return false;
			}

			// ASAP / missing-time orders carry a working promise of creation
			// time plus the prep window - the same rule the dashboard, live
			// board, and orders table use - rather than being skipped, so
			// ASAP-heavy restaurants don't read as having zero late orders.
			if ( '' === $time || false !== stripos( $time, 'asap' ) ) {
				$prep_minutes = 30;
				if ( class_exists( 'RPRESS_Command_Center_Dashboard' ) && method_exists( 'RPRESS_Command_Center_Dashboard', 'thresholds' ) ) {
					$thresholds   = RPRESS_Command_Center_Dashboard::thresholds();
					$prep_minutes = isset( $thresholds['asap_prep_minutes'] ) ? (int) $thresholds['asap_prep_minutes'] : 30;
				}
				$promise_ts = (int) get_post_time( 'U', false, $payment_id ) + ( $prep_minutes * MINUTE_IN_SECONDS );
			} else {
				if ( false !== strpos( $time, '-' ) || false !== strpos( $time, '–' ) ) {
					$parts = preg_split( '/\s*[\-–]\s*/', $time );
					$time  = ! empty( $parts[1] ) ? trim( $parts[1] ) : trim( $parts[0] );
				}
				$promise_ts = strtotime( $date . ' ' . $time );
			}

			$done_ts = get_post_modified_time( 'U', false, $payment_id );

			return $promise_ts && $done_ts && $done_ts > ( $promise_ts + ( 10 * MINUTE_IN_SECONDS ) );
		}
	}
}
