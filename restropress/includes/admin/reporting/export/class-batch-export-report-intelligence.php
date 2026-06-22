<?php
/**
 * Reports Intelligence batch export class.
 *
 * @package RPRESS
 * @subpackage Admin/Reports
 * @since 3.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RPRESS_Batch_Report_Intelligence_Export Class.
 *
 * @since 3.3
 */
class RPRESS_Batch_Report_Intelligence_Export extends RPRESS_Batch_Export {

	/**
	 * Export type.
	 *
	 * @since 3.3
	 * @var string
	 */
	public $export_type = 'report_intelligence';

	/**
	 * Report export type.
	 *
	 * @since 3.3
	 * @var string
	 */
	protected $report_type = 'orders';

	/**
	 * Report filters.
	 *
	 * @since 3.3
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Set CSV columns.
	 *
	 * @since 3.3
	 * @return array
	 */
	public function csv_cols() {
		$columns = array(
			'orders'            => array(
				'order_number'   => esc_html__( 'Order Number', 'restropress' ),
				'payment_id'     => esc_html__( 'Payment ID', 'restropress' ),
				'date'           => esc_html__( 'Date', 'restropress' ),
				'customer'       => esc_html__( 'Customer', 'restropress' ),
				'email'          => esc_html__( 'Email', 'restropress' ),
				'service_type'   => esc_html__( 'Service Type', 'restropress' ),
				'service_time'   => esc_html__( 'Service Time', 'restropress' ),
				'order_status'   => esc_html__( 'Order Status', 'restropress' ),
				'payment_status' => esc_html__( 'Payment Status', 'restropress' ),
				'payment_method' => esc_html__( 'Payment Method', 'restropress' ),
				'gross_total'    => esc_html__( 'Gross Total', 'restropress' ),
				'tax'            => esc_html__( 'Tax', 'restropress' ),
				'tip'            => esc_html__( 'Tip', 'restropress' ),
				'net_total'      => esc_html__( 'Net Total', 'restropress' ),
				'items'          => esc_html__( 'Items', 'restropress' ),
			),
			'sales-summary'     => array(
				'date'        => esc_html__( 'Date', 'restropress' ),
				'orders'      => esc_html__( 'Orders', 'restropress' ),
				'gross_sales' => esc_html__( 'Gross Sales', 'restropress' ),
				'refunds'     => esc_html__( 'Refunds', 'restropress' ),
				'net_sales'   => esc_html__( 'Net Sales', 'restropress' ),
				'aov'         => esc_html__( 'Average Order Value', 'restropress' ),
				'tax'         => esc_html__( 'Tax', 'restropress' ),
				'delivery'    => esc_html__( 'Delivery Orders', 'restropress' ),
				'pickup'      => esc_html__( 'Pickup Orders', 'restropress' ),
				'dinein'      => esc_html__( 'Dine-in Orders', 'restropress' ),
			),
			'menu-performance'  => array(
				'item_id'       => esc_html__( 'Item ID', 'restropress' ),
				'item'          => esc_html__( 'Menu Item', 'restropress' ),
				'quantity_sold' => esc_html__( 'Quantity Sold', 'restropress' ),
				'net_sales'     => esc_html__( 'Net Sales', 'restropress' ),
				'percent_total' => esc_html__( 'Percent of Total Sales', 'restropress' ),
				'risk_count'    => esc_html__( 'Refund / Cancel Count', 'restropress' ),
				'availability'  => esc_html__( 'Availability', 'restropress' ),
			),
			'customers'         => array(
				'customer_id'        => esc_html__( 'Customer ID', 'restropress' ),
				'customer'           => esc_html__( 'Customer', 'restropress' ),
				'email'              => esc_html__( 'Email', 'restropress' ),
				'orders'             => esc_html__( 'Orders', 'restropress' ),
				'paid_orders'        => esc_html__( 'Paid Orders', 'restropress' ),
				'total_spend'        => esc_html__( 'Total Spend', 'restropress' ),
				'average_order'      => esc_html__( 'Average Order', 'restropress' ),
				'last_order'         => esc_html__( 'Last Order', 'restropress' ),
				'service_preference' => esc_html__( 'Service Preference', 'restropress' ),
				'customer_type'      => esc_html__( 'Customer Type', 'restropress' ),
			),
			'payments-recovery' => array(
				'order_number'   => esc_html__( 'Order Number', 'restropress' ),
				'date'           => esc_html__( 'Date', 'restropress' ),
				'customer'       => esc_html__( 'Customer', 'restropress' ),
				'issue'          => esc_html__( 'Issue', 'restropress' ),
				'amount'         => esc_html__( 'Amount', 'restropress' ),
				'service_type'   => esc_html__( 'Service Type', 'restropress' ),
				'payment_method' => esc_html__( 'Payment Method', 'restropress' ),
				'payment_status' => esc_html__( 'Payment Status', 'restropress' ),
				'order_status'   => esc_html__( 'Order Status', 'restropress' ),
			),
			'tax-report'        => array(
				'date'                  => esc_html__( 'Date', 'restropress' ),
				'taxable_sales'         => esc_html__( 'Taxable Sales', 'restropress' ),
				'non_taxable_sales'     => esc_html__( 'Non-taxable Sales', 'restropress' ),
				'tax_collected'         => esc_html__( 'Tax Collected', 'restropress' ),
				'taxable_orders'        => esc_html__( 'Taxable Orders', 'restropress' ),
				'orders'                => esc_html__( 'Orders', 'restropress' ),
				'refund_tax_adjustment' => esc_html__( 'Refund Tax Adjustment', 'restropress' ),
			),
		);

		return isset( $columns[ $this->report_type ] ) ? $columns[ $this->report_type ] : $columns['orders'];
	}

	/**
	 * Get export rows.
	 *
	 * @since 3.3
	 * @return array|false
	 */
	public function get_data() {
		switch ( $this->report_type ) {
			case 'sales-summary':
				return 1 === (int) $this->step ? $this->get_sales_summary_rows() : false;
			case 'menu-performance':
				return 1 === (int) $this->step ? $this->get_menu_rows() : false;
			case 'customers':
				return 1 === (int) $this->step ? $this->get_customer_rows() : false;
			case 'payments-recovery':
				return $this->get_recovery_rows();
			case 'tax-report':
				return 1 === (int) $this->step ? $this->get_tax_rows() : false;
			case 'orders':
			default:
				return $this->get_order_rows();
		}
	}

	/**
	 * Set properties from request.
	 *
	 * @since 3.3
	 * @param array $request Request data.
	 * @return void
	 */
	public function set_properties( $request ) {
		$allowed = array( 'orders', 'sales-summary', 'menu-performance', 'customers', 'payments-recovery', 'tax-report' );
		$type    = isset( $request['report_export_type'] ) ? sanitize_key( $request['report_export_type'] ) : 'orders';

		$this->report_type = in_array( $type, $allowed, true ) ? $type : 'orders';
		$this->filters     = $this->sanitize_filters( $request );
		$this->start       = $this->filters['start'];
		$this->end         = $this->filters['end'];
		$this->status      = $this->filters['order_status'];

		$upload_dir     = wp_upload_dir();
		$this->filename = sanitize_file_name( 'rpress-' . $this->report_type . '-' . $this->start . '-to-' . $this->end . $this->filetype );
		$this->file     = trailingslashit( $upload_dir['basedir'] ) . $this->filename;
	}

	/**
	 * Export the generated file.
	 *
	 * @since 3.3
	 * @return void
	 */
	public function export() {
		$this->set_properties( $_REQUEST );
		parent::export();
	}

	/**
	 * Sanitize report filters.
	 *
	 * @since 3.3
	 * @param array $request Request data.
	 * @return array
	 */
	protected function sanitize_filters( $request ) {
		$range        = isset( $request['range'] ) ? sanitize_key( $request['range'] ) : 'last_30';
		$start_date   = isset( $request['start_date'] ) ? sanitize_text_field( $request['start_date'] ) : '';
		$end_date     = isset( $request['end_date'] ) ? sanitize_text_field( $request['end_date'] ) : '';
		$service      = isset( $request['service_type'] ) ? sanitize_key( $request['service_type'] ) : '';
		$payment      = isset( $request['payment_status'] ) ? sanitize_key( $request['payment_status'] ) : '';
		$order_status = isset( $request['order_status'] ) ? sanitize_key( $request['order_status'] ) : '';
		$ranges       = class_exists( 'RPRESS_Reports_Intelligence' ) ? RPRESS_Reports_Intelligence::get_range_options() : array();

		if ( ! isset( $ranges[ $range ] ) ) {
			$range = 'last_30';
		}

		if ( function_exists( 'rpress_get_payment_statuses' ) && ! array_key_exists( $payment, array( '' => '' ) + rpress_get_payment_statuses() ) ) {
			$payment = '';
		}

		if ( function_exists( 'rpress_get_order_statuses' ) && ! array_key_exists( $order_status, array( '' => '' ) + rpress_get_order_statuses() ) ) {
			$order_status = '';
		}

		$period = $this->get_period_for_range( $range, $start_date, $end_date );

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
	 * Get a period for a preset.
	 *
	 * @since 3.3
	 * @param string $range Range key.
	 * @param string $custom_start Custom start.
	 * @param string $custom_end Custom end.
	 * @return array
	 */
	protected function get_period_for_range( $range, $custom_start = '', $custom_end = '' ) {
		$today = current_time( 'Y-m-d' );
		$start = $today;
		$end = $today;

		switch ( $range ) {
			case 'yesterday':
				$start = date_i18n( 'Y-m-d', strtotime( '-1 day', current_time( 'timestamp' ) ) );
				$end = $start;
				break;
			case 'this_week':
				$week_start = (int) get_option( 'start_of_week', 1 );
				$today_ts = current_time( 'timestamp' );
				$weekday = (int) gmdate( 'w', $today_ts );
				$offset = ( $weekday - $week_start + 7 ) % 7;
				$start = date_i18n( 'Y-m-d', strtotime( '-' . $offset . ' days', $today_ts ) );
				break;
			case 'last_7':
				$start = date_i18n( 'Y-m-d', strtotime( '-6 days', current_time( 'timestamp' ) ) );
				break;
			case 'this_month':
				$start = date_i18n( 'Y-m-01', current_time( 'timestamp' ) );
				break;
			case 'last_month':
				$start = date_i18n( 'Y-m-01', strtotime( 'first day of previous month', current_time( 'timestamp' ) ) );
				$end = date_i18n( 'Y-m-t', strtotime( 'last day of previous month', current_time( 'timestamp' ) ) );
				break;
			case 'custom':
				$parsed_start = $this->parse_report_date( $custom_start );
				$parsed_end = $this->parse_report_date( $custom_end );
				if ( $parsed_start && $parsed_end ) {
					$start = $parsed_start;
					$end = $parsed_end;
				}
				break;
			case 'last_30':
				$start = date_i18n( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) );
				break;
			case 'today':
			default:
				break;
		}

		if ( strtotime( $start ) > strtotime( $end ) ) {
			$temp = $start;
			$start = $end;
			$end = $temp;
		}

		$days = max( 1, (int) floor( ( strtotime( $end ) - strtotime( $start ) ) / DAY_IN_SECONDS ) + 1 );
		$previous_end = date_i18n( 'Y-m-d', strtotime( '-1 day', strtotime( $start ) ) );
		$previous_start = date_i18n( 'Y-m-d', strtotime( '-' . $days . ' days', strtotime( $start ) ) );

		return array(
			'start'          => $start,
			'end'            => $end,
			'previous_start' => $previous_start,
			'previous_end'   => $previous_end,
		);
	}

	/**
	 * Parse a report date.
	 *
	 * @since 3.3
	 * @param string $date Date.
	 * @return string
	 */
	protected function parse_report_date( $date ) {
		$date = trim( (string) $date );
		if ( empty( $date ) ) {
			return '';
		}

		$timestamp = strtotime( $date );
		return $timestamp ? date_i18n( 'Y-m-d', $timestamp ) : '';
	}

	/**
	 * Query payment IDs.
	 *
	 * @since 3.3
	 * @param int  $limit Limit.
	 * @param bool $recovery_only Only recovery rows.
	 * @return array
	 */
	protected function get_payment_ids( $limit = -1, $recovery_only = false ) {
		$meta_query = array();

		if ( ! empty( $this->filters['service_type'] ) ) {
			$meta_query[] = array(
				'key'   => '_rpress_delivery_type',
				'value' => $this->filters['service_type'],
			);
		}

		if ( ! empty( $this->filters['order_status'] ) ) {
			$meta_query[] = array(
				'key'   => '_order_status',
				'value' => $this->filters['order_status'],
			);
		}

		if ( $recovery_only ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_order_status',
					'value'   => array( 'refunded', 'cancelled' ),
					'compare' => 'IN',
				),
				array(
					'key'     => '_order_status',
					'value'   => array( 'refunded', 'cancelled' ),
					'compare' => 'NOT IN',
				),
			);
		}

		$args = array(
			'post_type'              => 'rpress_payment',
			'post_status'            => ! empty( $this->filters['payment_status'] ) ? $this->filters['payment_status'] : 'any',
			'posts_per_page'         => $limit,
			'paged'                  => max( 1, (int) $this->step ),
			'fields'                 => 'ids',
			'date_query'             => array(
				array(
					'after'     => $this->filters['start'] . ' 00:00:00',
					'before'    => $this->filters['end'] . ' 23:59:59',
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
		return array_map( 'absint', $query->posts );
	}

	/**
	 * Get order rows.
	 *
	 * @since 3.3
	 * @return array|false
	 */
	protected function get_order_rows() {
		$rows = array();
		foreach ( $this->get_payment_ids( 30 ) as $payment_id ) {
			$rows[] = $this->build_order_row( $payment_id );
		}

		return ! empty( $rows ) ? $rows : false;
	}

	/**
	 * Get sales summary rows.
	 *
	 * @since 3.3
	 * @return array|false
	 */
	protected function get_sales_summary_rows() {
		$overview = RPRESS_Reports_Intelligence::build_overview( $this->filters );
		$rows = array();

		foreach ( $overview['current']['trend'] as $day => $row ) {
			$rows[] = array(
				'date'        => date_i18n( get_option( 'date_format' ), strtotime( $day ) ),
				'orders'      => (int) $row['orders'],
				'gross_sales' => rpress_format_amount( $row['gross'] ),
				'refunds'     => rpress_format_amount( $row['refunds'] ),
				'net_sales'   => rpress_format_amount( $row['sales'] ),
				'aov'         => rpress_format_amount( $row['aov'] ),
				'tax'         => rpress_format_amount( $row['tax'] ),
				'delivery'    => (int) $row['delivery'],
				'pickup'      => (int) $row['pickup'],
				'dinein'      => (int) $row['dinein'],
			);
		}

		return ! empty( $rows ) ? $rows : false;
	}

	/**
	 * Get menu performance rows.
	 *
	 * @since 3.3
	 * @return array|false
	 */
	protected function get_menu_rows() {
		$menu = RPRESS_Reports_Intelligence::build_menu( $this->filters );
		$current = $menu['current'];
		$total = max( 0.01, (float) $current['total_net_sales'] );
		$rows = array();

		foreach ( $current['all_items'] as $item ) {
			$rows[] = array(
				'item_id'       => absint( $item['id'] ),
				'item'          => $item['name'],
				'quantity_sold' => (int) $item['quantity'],
				'net_sales'     => rpress_format_amount( $item['net_sales'] ),
				'percent_total' => number_format_i18n( ( (float) $item['net_sales'] / $total ) * 100, 1 ) . '%',
				'risk_count'    => (int) $item['risk_count'],
				'availability'  => $item['availability'],
			);
		}

		return ! empty( $rows ) ? $rows : false;
	}

	/**
	 * Get customer rows.
	 *
	 * @since 3.3
	 * @return array|false
	 */
	protected function get_customer_rows() {
		$customers = RPRESS_Reports_Intelligence::build_customers( $this->filters, 0 );
		$rows = array();

		foreach ( $customers['current']['rows'] as $customer ) {
			$rows[] = array(
				'customer_id'        => $customer['customer_id'],
				'customer'           => $customer['name'],
				'email'              => $customer['email'],
				'orders'             => (int) $customer['orders'],
				'paid_orders'        => (int) $customer['paid_orders'],
				'total_spend'        => rpress_format_amount( $customer['total_spend'] ),
				'average_order'      => rpress_format_amount( $customer['avg_order'] ),
				'last_order'         => $customer['last_order_label'],
				'service_preference' => $customer['service_preference'],
				'customer_type'      => ! empty( $customer['is_new'] ) ? __( 'New', 'restropress' ) : __( 'Returning', 'restropress' ),
			);
		}

		return ! empty( $rows ) ? $rows : false;
	}

	/**
	 * Get recovery rows.
	 *
	 * @since 3.3
	 * @return array|false
	 */
	protected function get_recovery_rows() {
		$rows = array();
		$ids = array_slice( $this->get_recovery_payment_ids(), 30 * ( max( 1, (int) $this->step ) - 1 ), 30 );

		foreach ( $ids as $payment_id ) {
			$post_status  = get_post_status( $payment_id );
			$order_status = function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment_id ) ) : '';
			$order = $this->build_order_row( $payment_id );
			$rows[] = array(
				'order_number'   => $order['order_number'],
				'date'           => $order['date'],
				'customer'       => $order['customer'],
				'issue'          => $this->get_recovery_issue( $post_status, $order_status ),
				'amount'         => $order['gross_total'],
				'service_type'   => $order['service_type'],
				'payment_method' => $order['payment_method'],
				'payment_status' => $order['payment_status'],
				'order_status'   => $order['order_status'],
			);
		}

		return ! empty( $rows ) ? $rows : false;
	}

	/**
	 * Get recovery payment IDs.
	 *
	 * @since 3.3
	 * @return array
	 */
	protected function get_recovery_payment_ids() {
		$ids = array();

		foreach ( $this->get_payment_ids( -1 ) as $payment_id ) {
			$post_status  = get_post_status( $payment_id );
			$order_status = function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment_id ) ) : '';

			if ( in_array( $post_status, array( 'pending', 'processing', 'failed', 'abandoned', 'refunded' ), true ) || in_array( $order_status, array( 'refunded', 'cancelled' ), true ) ) {
				$ids[] = $payment_id;
			}
		}

		return $ids;
	}

	/**
	 * Get tax rows.
	 *
	 * @since 3.3
	 * @return array|false
	 */
	protected function get_tax_rows() {
		$taxes = RPRESS_Reports_Intelligence::build_taxes( $this->filters );
		$rows = array();

		foreach ( $taxes['current']['rows'] as $row ) {
			$rows[] = array(
				'date'                  => $row['date'],
				'taxable_sales'         => rpress_format_amount( $row['taxable_sales'] ),
				'non_taxable_sales'     => rpress_format_amount( $row['non_taxable_sales'] ),
				'tax_collected'         => rpress_format_amount( $row['tax_collected'] ),
				'taxable_orders'        => (int) $row['taxable_orders'],
				'orders'                => (int) $row['orders'],
				'refund_tax_adjustment' => rpress_format_amount( $row['refund_tax_adjustment'] ),
			);
		}

		return ! empty( $rows ) ? $rows : false;
	}

	/**
	 * Build one order row.
	 *
	 * @since 3.3
	 * @param int $payment_id Payment ID.
	 * @return array
	 */
	protected function build_order_row( $payment_id ) {
		$payment = new RPRESS_Payment( $payment_id );
		$user_info = function_exists( 'rpress_get_payment_meta_user_info' ) ? rpress_get_payment_meta_user_info( $payment_id ) : array();
		$email = function_exists( 'rpress_get_payment_user_email' ) ? rpress_get_payment_user_email( $payment_id ) : $payment->email;
		$name = trim( ( isset( $user_info['first_name'] ) ? $user_info['first_name'] : $payment->first_name ) . ' ' . ( isset( $user_info['last_name'] ) ? $user_info['last_name'] : $payment->last_name ) );
		$total = (float) get_post_meta( $payment_id, '_rpress_payment_total', true );
		$tax = function_exists( 'rpress_get_payment_tax' ) ? (float) rpress_get_payment_tax( $payment_id ) : 0.0;
		$tip = $this->get_tip_amount( $payment_id );
		$service_type = function_exists( 'rpress_get_service_type' ) ? sanitize_key( rpress_get_service_type( $payment_id ) ) : '';
		$order_status = function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment_id ) ) : '';
		$payment_status = get_post_status( $payment_id );
		$gateway = function_exists( 'rpress_get_payment_gateway' ) ? sanitize_key( rpress_get_payment_gateway( $payment_id ) ) : '';

		return array(
			'order_number'   => function_exists( 'rpress_get_payment_number' ) ? rpress_get_payment_number( $payment_id ) : $payment_id,
			'payment_id'     => $payment_id,
			'date'           => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) get_post_time( 'U', false, $payment_id ) ),
			'customer'       => $name ? $name : __( 'Guest', 'restropress' ),
			'email'          => $email,
			'service_type'   => $this->get_service_label( $service_type ),
			'service_time'   => get_post_meta( $payment_id, '_rpress_delivery_time', true ),
			'order_status'   => function_exists( 'rpress_get_order_status_label' ) ? rpress_get_order_status_label( $order_status ) : $order_status,
			'payment_status' => function_exists( 'rpress_get_payment_status_label' ) ? rpress_get_payment_status_label( $payment_status ) : $payment_status,
			'payment_method' => $this->get_gateway_label( $gateway ),
			'gross_total'    => rpress_format_amount( $total ),
			'tax'            => rpress_format_amount( $tax ),
			'tip'            => rpress_format_amount( $tip ),
			'net_total'      => rpress_format_amount( max( 0, $total - $tax ) ),
			'items'          => $this->get_items_label( $payment_id ),
		);
	}

	/**
	 * Get item labels for an order.
	 *
	 * @since 3.3
	 * @param int $payment_id Payment ID.
	 * @return string
	 */
	protected function get_items_label( $payment_id ) {
		$items = function_exists( 'rpress_get_payment_meta_cart_details' ) ? rpress_get_payment_meta_cart_details( $payment_id ) : array();
		$labels = array();

		foreach ( (array) $items as $item ) {
			$name = ! empty( $item['name'] ) ? $item['name'] : ( ! empty( $item['id'] ) ? get_the_title( $item['id'] ) : __( 'Item', 'restropress' ) );
			$qty = isset( $item['quantity'] ) ? max( 1, absint( $item['quantity'] ) ) : 1;
			$labels[] = $name . ' x ' . $qty;
		}

		return implode( ' | ', $labels );
	}

	/**
	 * Get tip amount.
	 *
	 * @since 3.3
	 * @param int $payment_id Payment ID.
	 * @return float
	 */
	protected function get_tip_amount( $payment_id ) {
		$meta = function_exists( 'rpress_get_payment_meta' ) ? rpress_get_payment_meta( $payment_id ) : array();
		if ( isset( $meta['fees']['tip']['amount'] ) ) {
			return (float) $meta['fees']['tip']['amount'];
		}

		return 0.0;
	}

	/**
	 * Get recovery issue label.
	 *
	 * @since 3.3
	 * @param string $post_status Payment status.
	 * @param string $order_status Order status.
	 * @return string
	 */
	protected function get_recovery_issue( $post_status, $order_status ) {
		if ( 'abandoned' === $post_status ) {
			return __( 'Abandoned checkout', 'restropress' );
		}
		if ( 'failed' === $post_status ) {
			return __( 'Payment failed', 'restropress' );
		}
		if ( in_array( $post_status, array( 'pending', 'processing' ), true ) ) {
			return __( 'Pending / unpaid', 'restropress' );
		}
		if ( 'refunded' === $post_status || 'refunded' === $order_status ) {
			return __( 'Refund issued', 'restropress' );
		}
		if ( 'cancelled' === $order_status ) {
			return __( 'Cancelled order', 'restropress' );
		}

		return __( 'Needs review', 'restropress' );
	}

	/**
	 * Get service label.
	 *
	 * @since 3.3
	 * @param string $service_type Service type.
	 * @return string
	 */
	protected function get_service_label( $service_type ) {
		if ( 'dine-in' === $service_type ) {
			$service_type = 'dinein';
		}
		if ( function_exists( 'rpress_service_label' ) && $service_type ) {
			return rpress_service_label( $service_type );
		}

		return $service_type ? ucwords( str_replace( array( '-', '_' ), ' ', $service_type ) ) : __( 'Not captured', 'restropress' );
	}

	/**
	 * Get gateway label.
	 *
	 * @since 3.3
	 * @param string $gateway Gateway key.
	 * @return string
	 */
	protected function get_gateway_label( $gateway ) {
		if ( function_exists( 'rpress_get_gateway_admin_label' ) && $gateway ) {
			return rpress_get_gateway_admin_label( $gateway );
		}

		return $gateway ? ucwords( str_replace( array( '-', '_' ), ' ', $gateway ) ) : __( 'Not captured', 'restropress' );
	}

	/**
	 * Return calculated completion percentage.
	 *
	 * @since 3.3
	 * @return int
	 */
	public function get_percentage_complete() {
		if ( in_array( $this->report_type, array( 'sales-summary', 'menu-performance', 'customers', 'tax-report' ), true ) ) {
			return 100;
		}

		$total = 'payments-recovery' === $this->report_type ? count( $this->get_recovery_payment_ids() ) : count( $this->get_payment_ids( -1 ) );

		return min( 100, (int) ( ( 30 * max( 1, (int) $this->step ) / max( 1, $total ) ) * 100 ) );
	}
}
