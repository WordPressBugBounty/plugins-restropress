<?php
/**
 * Reports Intelligence partial.
 *
 * @package RPRESS
 * @since   3.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Render the Payments & Recovery report tab.
 *
 * @since 3.3
 * @return void
 */
function rpress_reports_tab_payments_recovery() {
	if ( ! current_user_can( 'view_shop_reports' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this report', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}

	$filters  = RPRESS_Reports_Intelligence::get_filters();
	$data     = RPRESS_Reports_Intelligence::build_payments_recovery( $filters );
	$current  = $data['current'];
	$previous = $data['previous'];
	$payment_total = array_sum( array_map( 'floatval', (array) $current['payment_totals'] ) );
	?>
	<section class="rp-grid rp-reports-intelligence">
		<?php rpress_reports_intelligence_filters( 'payments-recovery', $filters ); ?>

		<div class="rp-grid rp-reports-kpi-grid rp-reports-kpi-grid-six">
			<?php
			rpress_reports_metric_card( esc_html__( 'Paid Orders', 'restropress' ), number_format_i18n( $current['paid_orders'] ), RPRESS_Reports_Intelligence::delta( $current['paid_orders'], $previous['paid_orders'] ), 'yes-alt', 'green' );
			rpress_reports_metric_card( esc_html__( 'Pending / Unpaid', 'restropress' ), number_format_i18n( $current['pending_unpaid'] ), RPRESS_Reports_Intelligence::delta( $current['pending_unpaid'], $previous['pending_unpaid'] ), 'clock', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Failed Orders', 'restropress' ), number_format_i18n( $current['failed_orders'] ), RPRESS_Reports_Intelligence::delta( $current['failed_orders'], $previous['failed_orders'] ), 'warning', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Refunded Orders', 'restropress' ), number_format_i18n( $current['refunded_orders'] ), RPRESS_Reports_Intelligence::delta( $current['refunded_orders'], $previous['refunded_orders'] ), 'undo', 'purple' );
			rpress_reports_metric_card( esc_html__( 'Refund Amount', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['refund_amount'] ), RPRESS_Reports_Intelligence::delta( $current['refund_amount'], $previous['refund_amount'] ), 'money-alt', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Refund Rate', 'restropress' ), number_format_i18n( $current['refund_rate'], 1 ) . '%', RPRESS_Reports_Intelligence::delta( $current['refund_rate'], $previous['refund_rate'], '%' ), 'chart-line', 'blue' );
			?>
		</div>

		<div class="rp-grid rp-reports-payments-grid">
			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Payment Method Split', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Paid order value by payment method for the selected period.', 'restropress' ); ?></p>
				<?php rpress_reports_bar_list( rpress_reports_payment_rows( $current['payment_totals'] ), $payment_total ); ?>
			</div>

			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Refund / Failure Trend', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Daily refunded and failed/abandoned payment pressure.', 'restropress' ); ?></p>
				<div class="rp-reports-chart" role="img" aria-label="<?php esc_attr_e( 'Refund and failure trend chart', 'restropress' ); ?>">
					<?php rpress_reports_trend_svg( $current['trend'], 'refunds', 'failures' ); ?>
				</div>
				<div class="rp-reports-legend">
					<span><i class="rp-reports-dot is-blue"></i><?php esc_html_e( 'Refunded', 'restropress' ); ?></span>
					<span><i class="rp-reports-dot is-green"></i><?php esc_html_e( 'Failed / abandoned', 'restropress' ); ?></span>
				</div>
			</div>
		</div>

		<div class="rp-card rp-reports-panel">
			<h2><?php esc_html_e( 'Recovery Table', 'restropress' ); ?></h2>
			<p><?php esc_html_e( 'Orders that need payment review, refund follow-up, or customer recovery.', 'restropress' ); ?></p>
			<div class="rp-table-scroll">
				<table class="widefat striped rp-reports-table rp-reports-recovery-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Date', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Issue', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Service', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Payment Method', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Action', 'restropress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $current['recovery_rows'] ) ) : ?>
							<tr>
								<td colspan="8"><?php esc_html_e( 'No payment recovery issues found for the selected filters.', 'restropress' ); ?></td>
							</tr>
						<?php endif; ?>
						<?php foreach ( $current['recovery_rows'] as $row ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( $row['order'] ); ?></strong></td>
								<td><?php echo esc_html( $row['date'] ); ?></td>
								<td><?php echo esc_html( $row['customer'] ); ?></td>
								<td><span class="rp-reports-status-pill <?php echo $row['priority'] >= 3 ? 'is-warning' : 'is-info'; ?>"><?php echo esc_html( $row['issue'] ); ?></span></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $row['amount'] ) ); ?></td>
								<td><?php echo esc_html( $row['service'] ); ?></td>
								<td><?php echo esc_html( $row['payment_method'] ); ?></td>
								<td><a class="button button-secondary rp-btn rp-btn-secondary" href="<?php echo esc_url( $row['action_url'] ); ?>"><?php esc_html_e( 'Review', 'restropress' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
	<?php
}
add_action( 'rpress_reports_tab_payments-recovery', 'rpress_reports_tab_payments_recovery' );
