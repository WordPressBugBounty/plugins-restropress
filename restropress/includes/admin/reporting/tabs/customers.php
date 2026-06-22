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
 * Render the Customers report tab.
 *
 * @since 3.3
 * @return void
 */
function rpress_reports_tab_customers() {
	if ( ! current_user_can( 'view_shop_reports' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this report', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}

	$filters  = RPRESS_Reports_Intelligence::get_filters();
	$data     = RPRESS_Reports_Intelligence::build_customers( $filters );
	$current  = $data['current'];
	$previous = $data['previous'];
	?>
	<section class="rp-grid rp-reports-intelligence">
		<?php rpress_reports_intelligence_filters( 'customers', $filters ); ?>

		<div class="rp-grid rp-reports-kpi-grid rp-reports-kpi-grid-five">
			<?php
			rpress_reports_metric_card( esc_html__( 'Total Customers', 'restropress' ), number_format_i18n( $current['total_customers'] ), RPRESS_Reports_Intelligence::delta( $current['total_customers'], $previous['total_customers'] ), 'groups', 'blue' );
			rpress_reports_metric_card( esc_html__( 'New Customers', 'restropress' ), number_format_i18n( $current['new_customers'] ), RPRESS_Reports_Intelligence::delta( $current['new_customers'], $previous['new_customers'] ), 'welcome-add-page', 'green' );
			rpress_reports_metric_card( esc_html__( 'Returning Customers', 'restropress' ), number_format_i18n( $current['returning_customers'] ), RPRESS_Reports_Intelligence::delta( $current['returning_customers'], $previous['returning_customers'] ), 'admin-users', 'purple' );
			rpress_reports_metric_card( esc_html__( 'Repeat Order Rate', 'restropress' ), number_format_i18n( $current['repeat_rate'], 1 ) . '%', RPRESS_Reports_Intelligence::delta( $current['repeat_rate'], $previous['repeat_rate'], '%' ), 'update', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Avg Spend / Customer', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['avg_spend'] ), RPRESS_Reports_Intelligence::delta( $current['avg_spend'], $previous['avg_spend'] ), 'money-alt', 'green' );
			?>
		</div>

		<div class="rp-grid rp-reports-customers-grid">
			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'New vs Returning Trend', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Daily customer mix for the selected period.', 'restropress' ); ?></p>
				<div class="rp-reports-chart" role="img" aria-label="<?php esc_attr_e( 'New and returning customer trend chart', 'restropress' ); ?>">
					<?php rpress_reports_trend_svg( $current['trend'], 'new', 'returning' ); ?>
				</div>
				<div class="rp-reports-legend">
					<span><i class="rp-reports-dot is-blue"></i><?php esc_html_e( 'New', 'restropress' ); ?></span>
					<span><i class="rp-reports-dot is-green"></i><?php esc_html_e( 'Returning', 'restropress' ); ?></span>
				</div>
			</div>

			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Repeat Rate Trend', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Customers with more than one order on the same day.', 'restropress' ); ?></p>
				<div class="rp-reports-chart" role="img" aria-label="<?php esc_attr_e( 'Repeat customer rate trend chart', 'restropress' ); ?>">
					<?php rpress_reports_single_trend_svg( wp_list_pluck( $current['trend'], 'repeat_rate' ) ); ?>
				</div>
				<div class="rp-reports-legend">
					<span><i class="rp-reports-dot is-blue"></i><?php esc_html_e( 'Repeat rate', 'restropress' ); ?></span>
				</div>
			</div>
		</div>

		<div class="rp-card rp-reports-panel">
			<h2><?php esc_html_e( 'Customer Detail', 'restropress' ); ?></h2>
			<p><?php esc_html_e( 'Selected-period customers, ranked by paid spend.', 'restropress' ); ?></p>
			<div class="rp-table-scroll">
				<table class="widefat striped rp-reports-table rp-reports-customers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Customer', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Orders', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Total Spend', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Avg Order', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Last Order', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Service Preference', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Type', 'restropress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $current['rows'] ) ) : ?>
							<tr>
								<td colspan="7"><?php esc_html_e( 'No customers found for the selected filters.', 'restropress' ); ?></td>
							</tr>
						<?php endif; ?>
						<?php foreach ( $current['rows'] as $customer ) : ?>
							<tr>
								<td>
									<strong>
										<?php if ( ! empty( $customer['customer_id'] ) ) : ?>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . absint( $customer['customer_id'] ) ) ); ?>"><?php echo esc_html( $customer['name'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $customer['name'] ); ?>
										<?php endif; ?>
									</strong>
									<?php if ( ! empty( $customer['email'] ) ) : ?>
										<small class="rp-reports-customer-email"><?php echo esc_html( $customer['email'] ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( number_format_i18n( $customer['orders'] ) ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $customer['total_spend'] ) ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $customer['avg_order'] ) ); ?></td>
								<td><?php echo esc_html( $customer['last_order_label'] ); ?></td>
								<td><?php echo esc_html( $customer['service_preference'] ); ?></td>
								<td><span class="rp-reports-status-pill <?php echo ! empty( $customer['is_new'] ) ? 'is-success' : 'is-info'; ?>"><?php echo ! empty( $customer['is_new'] ) ? esc_html__( 'New', 'restropress' ) : esc_html__( 'Returning', 'restropress' ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
	<?php
}
add_action( 'rpress_reports_tab_customers', 'rpress_reports_tab_customers' );
