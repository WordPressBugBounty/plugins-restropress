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
 * Render the Sales report tab.
 *
 * @since 3.3
 * @return void
 */
function rpress_reports_tab_sales() {
	if ( ! current_user_can( 'view_shop_reports' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this report', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}

	$filters  = RPRESS_Reports_Intelligence::get_filters();
	$data     = RPRESS_Reports_Intelligence::build_overview( $filters );
	$current  = $data['current'];
	$previous = $data['previous'];
	?>
	<section class="rp-grid rp-reports-intelligence">
		<?php rpress_reports_intelligence_filters( 'sales', $filters ); ?>

		<div class="rp-grid rp-reports-kpi-grid rp-reports-kpi-grid-six">
			<?php
			rpress_reports_metric_card( esc_html__( 'Gross Sales', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['gross_sales'] ), RPRESS_Reports_Intelligence::delta( $current['gross_sales'], $previous['gross_sales'] ), 'money-alt', 'green' );
			rpress_reports_metric_card( esc_html__( 'Refunds', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['refund_amount'] ), RPRESS_Reports_Intelligence::delta( $current['refund_amount'], $previous['refund_amount'] ), 'undo', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Net Sales', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['net_sales'] ), RPRESS_Reports_Intelligence::delta( $current['net_sales'], $previous['net_sales'] ), 'chart-line', 'green' );
			rpress_reports_metric_card( esc_html__( 'Taxes', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['tax_collected'] ), RPRESS_Reports_Intelligence::delta( $current['tax_collected'], $previous['tax_collected'] ), 'clipboard', 'blue' );
			rpress_reports_metric_card( esc_html__( 'AOV', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['aov'] ), RPRESS_Reports_Intelligence::delta( $current['aov'], $previous['aov'] ), 'chart-bar', 'purple' );
			rpress_reports_metric_card( esc_html__( 'Orders', 'restropress' ), number_format_i18n( $current['orders'] ), RPRESS_Reports_Intelligence::delta( $current['orders'], $previous['orders'] ), 'cart', 'blue' );
			?>
		</div>

		<div class="rp-reports-sales-grid">
			<div class="rp-card rp-reports-panel rp-reports-sales-trend">
				<h2><?php esc_html_e( 'Net Sales Trend', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Selected period, net of refunds.', 'restropress' ); ?></p>
				<div class="rp-reports-chart" role="img" aria-label="<?php esc_attr_e( 'Net sales trend chart', 'restropress' ); ?>">
					<?php rpress_reports_single_trend_svg( wp_list_pluck( $current['trend'], 'sales' ) ); ?>
				</div>
			</div>

			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Service Split', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Net sales by service type.', 'restropress' ); ?></p>
				<?php rpress_reports_bar_list( rpress_reports_service_rows( $current['service_sales'] ), $current['net_sales'] ); ?>
			</div>

			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Payment Split', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Net sales by payment method.', 'restropress' ); ?></p>
				<?php rpress_reports_bar_list( rpress_reports_payment_rows( $current['payment_totals'] ), $current['net_sales'] ); ?>
			</div>
		</div>

		<div class="rp-card rp-reports-panel">
			<h2><?php esc_html_e( 'Hour-of-Day Revenue Heatmap', 'restropress' ); ?></h2>
			<p><?php esc_html_e( 'Coming from order timestamps in this phase. Darker = more revenue concentration.', 'restropress' ); ?></p>
			<?php rpress_reports_sales_heatmap( $current['ids'] ); ?>
		</div>

		<div class="rp-card rp-reports-panel">
			<h2><?php esc_html_e( 'Sales Detail', 'restropress' ); ?></h2>
			<div class="rp-table-scroll">
				<table class="widefat striped rp-reports-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Orders', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Gross Sales', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Refunds', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Net Sales', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'AOV', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Tax', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Risk', 'restropress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_reverse( $current['trend'], true ) as $day => $row ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $day ) ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['orders'] ) ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $row['gross'] ) ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $row['refunds'] ) ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $row['sales'] ) ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $row['aov'] ) ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $row['tax'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['risk'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
	<?php
}
add_action( 'rpress_reports_tab_sales', 'rpress_reports_tab_sales' );
