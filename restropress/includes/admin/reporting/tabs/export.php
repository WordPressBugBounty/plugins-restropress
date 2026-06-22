<?php
/**
 * Reports Export tab.
 *
 * @package RPRESS
 * @since 3.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Render the Export report tab.
 *
 * @since 3.3
 * @return void
 */
function rpress_reports_tab_export() {
	if ( ! current_user_can( 'view_shop_reports' ) ) {
		return;
	}

	$filters = RPRESS_Reports_Intelligence::get_filters();
	$cards   = rpress_reports_export_cards();
	?>
	<section class="rp-grid rp-reports-intelligence rp-reports-export">
		<?php rpress_reports_intelligence_filters( 'export', $filters ); ?>

		<div class="rp-card rp-reports-panel rp-reports-export-intro">
			<h2><?php esc_html_e( 'Export Center', 'restropress' ); ?></h2>
			<p><?php esc_html_e( 'Download restaurant-ready CSVs for accounting, operations, menu analysis, customers, recovery, and taxes.', 'restropress' ); ?></p>
		</div>

		<div class="rp-grid rp-grid-3 rp-reports-export-grid">
			<?php foreach ( $cards as $card_id => $card ) : ?>
				<?php if ( ! empty( $card['secondary'] ) ) : ?>
					</div>
					<div class="rp-card rp-reports-panel rp-reports-export-intro rp-reports-export-secondary-head">
						<h2><?php esc_html_e( 'Store Data', 'restropress' ); ?></h2>
						<p><?php esc_html_e( 'Use these exports for menu setup, migration, and catalog review. They are not selected-period performance reports.', 'restropress' ); ?></p>
					</div>
					<div class="rp-grid rp-grid-3 rp-reports-export-grid">
				<?php endif; ?>
				<?php rpress_reports_render_export_card( $card_id, $card, $filters ); ?>
			<?php endforeach; ?>
		</div>

		<?php do_action( 'rpress_reports_tab_export_content_bottom' ); ?>
	</section>
	<?php
}
add_action( 'rpress_reports_tab_export', 'rpress_reports_tab_export' );

/**
 * Get report export cards.
 *
 * @since 3.3
 * @return array
 */
function rpress_reports_export_cards() {
	$cards = array(
		'orders'            => array(
			'title'       => __( 'Orders Export', 'restropress' ),
			'description' => __( 'Detailed order-level data for operations, audits, and support review.', 'restropress' ),
			'icon'        => 'cart',
			'class'       => 'RPRESS_Batch_Report_Intelligence_Export',
			'type'        => 'orders',
			'format'      => __( 'CSV', 'restropress' ),
		),
		'sales-summary'     => array(
			'title'       => __( 'Sales Summary Export', 'restropress' ),
			'description' => __( 'Daily sales, refunds, net sales, AOV, tax, and service split.', 'restropress' ),
			'icon'        => 'chart-line',
			'class'       => 'RPRESS_Batch_Report_Intelligence_Export',
			'type'        => 'sales-summary',
			'format'      => __( 'CSV', 'restropress' ),
		),
		'menu-performance'  => array(
			'title'       => __( 'Menu Performance Export', 'restropress' ),
			'description' => __( 'Selected-period item sales, quantity, revenue share, risk, and availability.', 'restropress' ),
			'icon'        => 'food',
			'class'       => 'RPRESS_Batch_Report_Intelligence_Export',
			'type'        => 'menu-performance',
			'format'      => __( 'CSV', 'restropress' ),
		),
		'customers'         => array(
			'title'       => __( 'Customers Export', 'restropress' ),
			'description' => __( 'Customer orders, spend, average order, last order, and service preference.', 'restropress' ),
			'icon'        => 'groups',
			'class'       => 'RPRESS_Batch_Report_Intelligence_Export',
			'type'        => 'customers',
			'format'      => __( 'CSV', 'restropress' ),
		),
		'payments-recovery' => array(
			'title'       => __( 'Payments & Recovery Export', 'restropress' ),
			'description' => __( 'Failed, unpaid, refunded, pending, and cancelled orders for follow-up.', 'restropress' ),
			'icon'        => 'undo',
			'class'       => 'RPRESS_Batch_Report_Intelligence_Export',
			'type'        => 'payments-recovery',
			'format'      => __( 'CSV', 'restropress' ),
		),
		'tax-report'        => array(
			'title'       => __( 'Tax Report Export', 'restropress' ),
			'description' => __( 'Taxable sales, non-taxable sales, collected tax, orders, and refund tax adjustments.', 'restropress' ),
			'icon'        => 'clipboard',
			'class'       => 'RPRESS_Batch_Report_Intelligence_Export',
			'type'        => 'tax-report',
			'format'      => __( 'CSV', 'restropress' ),
		),
	);

	return apply_filters( 'rpress_reports_export_cards', $cards );
}

/**
 * Render one export card.
 *
 * @since 3.3
 * @param string $card_id Card ID.
 * @param array  $card Card config.
 * @param array  $filters Current filters.
 * @return void
 */
function rpress_reports_render_export_card( $card_id, $card, $filters ) {
	$is_report_export = 'RPRESS_Batch_Report_Intelligence_Export' === $card['class'];
	?>
	<div class="rp-card rp-reports-export-card">
		<div class="rp-reports-export-card-head">
			<span class="rp-metric-icon tone-blue"><span class="dashicons dashicons-<?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span></span>
			<div>
				<h2><?php echo esc_html( $card['title'] ); ?></h2>
				<p><?php echo esc_html( $card['description'] ); ?></p>
			</div>
		</div>

		<dl class="rp-reports-export-meta">
			<div>
				<dt><?php esc_html_e( 'Period', 'restropress' ); ?></dt>
				<dd><?php echo esc_html( rpress_reports_export_period_label( $filters ) ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Filters', 'restropress' ); ?></dt>
				<dd><?php echo esc_html( $is_report_export ? rpress_reports_export_filter_label( $filters ) : __( 'All catalog items', 'restropress' ) ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Format', 'restropress' ); ?></dt>
				<dd><?php echo esc_html( $card['format'] ); ?></dd>
			</div>
		</dl>

		<form id="rpress-export-<?php echo esc_attr( $card_id ); ?>" class="rpress-export-form rpress-import-export-form rp-reports-export-form" method="post">
			<?php wp_nonce_field( 'rpress_ajax_export', 'rpress_ajax_export' ); ?>
			<input type="hidden" name="rpress-export-class" value="<?php echo esc_attr( $card['class'] ); ?>" />
			<?php if ( $is_report_export ) : ?>
				<input type="hidden" name="report_export_type" value="<?php echo esc_attr( $card['type'] ); ?>" />
				<input type="hidden" name="range" value="<?php echo esc_attr( $filters['range'] ); ?>" />
				<input type="hidden" name="start_date" value="<?php echo esc_attr( $filters['start_date'] ); ?>" />
				<input type="hidden" name="end_date" value="<?php echo esc_attr( $filters['end_date'] ); ?>" />
				<input type="hidden" name="service_type" value="<?php echo esc_attr( $filters['service_type'] ); ?>" />
				<input type="hidden" name="payment_status" value="<?php echo esc_attr( $filters['payment_status'] ); ?>" />
				<input type="hidden" name="order_status" value="<?php echo esc_attr( $filters['order_status'] ); ?>" />
			<?php endif; ?>
			<input type="submit" value="<?php esc_attr_e( 'Export CSV', 'restropress' ); ?>" class="button button-secondary rp-btn rp-btn-secondary" />
		</form>
	</div>
	<?php
}

/**
 * Get period label for export cards.
 *
 * @since 3.3
 * @param array $filters Current filters.
 * @return string
 */
function rpress_reports_export_period_label( $filters ) {
	$ranges = RPRESS_Reports_Intelligence::get_range_options();
	$range_label = isset( $ranges[ $filters['range'] ] ) ? $ranges[ $filters['range'] ] : __( 'Selected Period', 'restropress' );
	$date_label = date_i18n( get_option( 'date_format' ), strtotime( $filters['start'] ) ) . ' - ' . date_i18n( get_option( 'date_format' ), strtotime( $filters['end'] ) );

	return $range_label . ' | ' . $date_label;
}

/**
 * Get filter label for export cards.
 *
 * @since 3.3
 * @param array $filters Current filters.
 * @return string
 */
function rpress_reports_export_filter_label( $filters ) {
	$labels = array();
	$service_options = function_exists( 'rpress_get_service_types' ) ? rpress_get_service_types() : array();
	$payment_options = function_exists( 'rpress_get_payment_statuses' ) ? rpress_get_payment_statuses() : array();
	$order_options = function_exists( 'rpress_get_order_statuses' ) ? rpress_get_order_statuses() : array();

	$labels[] = ! empty( $filters['service_type'] ) && isset( $service_options[ $filters['service_type'] ] ) ? $service_options[ $filters['service_type'] ] : __( 'All services', 'restropress' );
	$labels[] = ! empty( $filters['payment_status'] ) && isset( $payment_options[ $filters['payment_status'] ] ) ? $payment_options[ $filters['payment_status'] ] : __( 'All payments', 'restropress' );
	$labels[] = ! empty( $filters['order_status'] ) && isset( $order_options[ $filters['order_status'] ] ) ? $order_options[ $filters['order_status'] ] : __( 'All statuses', 'restropress' );

	return implode( ' | ', $labels );
}
