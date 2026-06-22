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
 * Render the Orders & Risk report tab.
 *
 * @since 3.3
 * @return void
 */
function rpress_reports_tab_orders_risk() {
	if ( ! current_user_can( 'view_shop_reports' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this report', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}

	$filters  = RPRESS_Reports_Intelligence::get_filters();
	$data     = RPRESS_Reports_Intelligence::build_overview( $filters );
	$current  = $data['current'];
	$previous = $data['previous'];
	$risk_total = $current['late_orders'] + $current['cancelled'] + $current['refunded'] + $current['failed_unpaid'];
	$previous_risk_total = $previous['late_orders'] + $previous['cancelled'] + $previous['refunded'] + $previous['failed_unpaid'];
	$promise_risk = $current['orders'] > 0 ? ( $risk_total / $current['orders'] ) * 100 : 0.0;
	$previous_promise_risk = $previous['orders'] > 0 ? ( $previous_risk_total / $previous['orders'] ) * 100 : 0.0;
	?>
	<section class="rp-grid rp-reports-intelligence">
		<?php rpress_reports_intelligence_filters( 'orders-risk', $filters ); ?>

		<div class="rp-grid rp-reports-kpi-grid rp-reports-kpi-grid-seven">
			<?php
			rpress_reports_metric_card( esc_html__( 'Total Orders', 'restropress' ), number_format_i18n( $current['orders'] ), RPRESS_Reports_Intelligence::delta( $current['orders'], $previous['orders'] ), 'cart', 'blue' );
			rpress_reports_metric_card( esc_html__( 'Completed', 'restropress' ), number_format_i18n( $current['completed_orders'] ), RPRESS_Reports_Intelligence::delta( $current['completed_orders'], $previous['completed_orders'] ), 'yes-alt', 'green' );
			rpress_reports_metric_card( esc_html__( 'Cancelled', 'restropress' ), number_format_i18n( $current['cancelled'] ), RPRESS_Reports_Intelligence::delta( $current['cancelled'], $previous['cancelled'] ), 'dismiss', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Refunded', 'restropress' ), number_format_i18n( $current['refunded'] ), RPRESS_Reports_Intelligence::delta( $current['refunded'], $previous['refunded'] ), 'undo', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Failed / Unpaid', 'restropress' ), number_format_i18n( $current['failed_unpaid'] ), RPRESS_Reports_Intelligence::delta( $current['failed_unpaid'], $previous['failed_unpaid'] ), 'warning', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Late Orders', 'restropress' ), number_format_i18n( $current['late_orders'] ), RPRESS_Reports_Intelligence::delta( $current['late_orders'], $previous['late_orders'] ), 'clock', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Promise Risk', 'restropress' ), number_format_i18n( $promise_risk, 1 ) . '%', RPRESS_Reports_Intelligence::delta( $promise_risk, $previous_promise_risk, '%' ), 'chart-area', 'purple' );
			?>
		</div>

		<div class="rp-reports-orders-risk-grid">
			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Orders by Status Over Time', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Completed, cancelled, refunded, failed, and late counts by day.', 'restropress' ); ?></p>
				<?php rpress_reports_status_timeline( $current['trend'] ); ?>
			</div>

			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Risk Trend Over Time', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Daily late, cancelled, refunded, failed, and unpaid order pressure.', 'restropress' ); ?></p>
				<div class="rp-reports-chart" role="img" aria-label="<?php esc_attr_e( 'Risk trend chart', 'restropress' ); ?>">
					<?php rpress_reports_single_trend_svg( wp_list_pluck( $current['trend'], 'risk' ) ); ?>
				</div>
			</div>

			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Rush Heatmap', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Weekday by hour order concentration. Darker = busier.', 'restropress' ); ?></p>
				<?php rpress_reports_rush_heatmap( $current['ids'] ); ?>
			</div>

			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Service Type Trend', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Order mix for the selected period.', 'restropress' ); ?></p>
				<?php rpress_reports_count_bar_list( rpress_reports_service_rows( $current['service_totals'] ), max( 1, $current['orders'] ) ); ?>
			</div>
		</div>

		<div class="rp-card rp-reports-panel">
			<h2><?php esc_html_e( 'Risk Table', 'restropress' ); ?></h2>
			<div class="rp-table-scroll">
				<table class="widefat striped rp-reports-table rp-reports-risk-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Period', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Orders', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Late %', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Cancel %', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Refund %', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Failed %', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Main Risk Driver', 'restropress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( rpress_reports_risk_period_rows( $current['trend'] ) as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['period'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['orders'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['late_rate'], 1 ) ); ?>%</td>
								<td><?php echo esc_html( number_format_i18n( $row['cancel_rate'], 1 ) ); ?>%</td>
								<td><?php echo esc_html( number_format_i18n( $row['refund_rate'], 1 ) ); ?>%</td>
								<td><?php echo esc_html( number_format_i18n( $row['failed_rate'], 1 ) ); ?>%</td>
								<td><?php echo esc_html( $row['driver'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
	<?php
}
add_action( 'rpress_reports_tab_orders-risk', 'rpress_reports_tab_orders_risk' );
