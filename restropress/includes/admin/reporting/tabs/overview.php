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
 * Render the new Reports Overview tab.
 *
 * @since 3.3
 * @return void
 */
function rpress_reports_tab_overview() {
	if ( ! current_user_can( 'view_shop_reports' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this report', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}

	$filters = RPRESS_Reports_Intelligence::get_filters();
	$data    = RPRESS_Reports_Intelligence::build_overview( $filters );
	$current = $data['current'];
	$previous = $data['previous'];
	?>
	<section class="rp-grid rp-reports-intelligence">
		<?php rpress_reports_intelligence_filters( 'overview', $filters ); ?>

		<div class="rp-grid rp-grid-4 rp-reports-kpi-grid">
			<?php
			rpress_reports_metric_card( esc_html__( 'Net Sales', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['net_sales'] ), RPRESS_Reports_Intelligence::delta( $current['net_sales'], $previous['net_sales'] ), 'money-alt', 'green' );
			rpress_reports_metric_card( esc_html__( 'Orders', 'restropress' ), number_format_i18n( $current['orders'] ), RPRESS_Reports_Intelligence::delta( $current['orders'], $previous['orders'] ), 'cart', 'blue' );
			rpress_reports_metric_card( esc_html__( 'Average Order Value', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['aov'] ), RPRESS_Reports_Intelligence::delta( $current['aov'], $previous['aov'] ), 'chart-bar', 'purple' );
			rpress_reports_metric_card( esc_html__( 'Refund Amount', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['refund_amount'] ), RPRESS_Reports_Intelligence::delta( $current['refund_amount'], $previous['refund_amount'] ), 'undo', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Cancellation Rate', 'restropress' ), number_format_i18n( $current['cancellation_rate'], 1 ) . '%', RPRESS_Reports_Intelligence::delta( $current['cancellation_rate'], $previous['cancellation_rate'], '%' ), 'dismiss', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Failed / Unpaid Orders', 'restropress' ), number_format_i18n( $current['failed_unpaid'] ), RPRESS_Reports_Intelligence::delta( $current['failed_unpaid'], $previous['failed_unpaid'] ), 'warning', 'amber' );
			rpress_reports_metric_card( esc_html__( 'New Customers', 'restropress' ), number_format_i18n( $current['new_customers'] ), RPRESS_Reports_Intelligence::delta( $current['new_customers'], $previous['new_customers'] ), 'groups', 'green' );
			rpress_reports_metric_card( esc_html__( 'Repeat Customers', 'restropress' ), number_format_i18n( $current['repeat_customers'] ), RPRESS_Reports_Intelligence::delta( $current['repeat_customers'], $previous['repeat_customers'] ), 'admin-users', 'blue' );
			?>
		</div>

		<div class="rp-grid rp-grid-main-sidebar rp-reports-overview-grid">
			<div class="rp-card rp-reports-panel">
				<div class="rp-reports-panel-head">
					<div>
						<h2><?php esc_html_e( 'Sales + Orders Trend', 'restropress' ); ?></h2>
						<p><?php esc_html_e( 'Net sales and order volume for the selected period.', 'restropress' ); ?></p>
					</div>
				</div>
				<div class="rp-reports-chart" role="img" aria-label="<?php esc_attr_e( 'Sales and orders trend chart', 'restropress' ); ?>">
					<?php rpress_reports_trend_svg( $current['trend'], 'sales', 'orders' ); ?>
				</div>
				<div class="rp-reports-legend">
					<span><i class="rp-reports-dot is-blue"></i><?php esc_html_e( 'Net Sales', 'restropress' ); ?></span>
					<span><i class="rp-reports-dot is-green"></i><?php esc_html_e( 'Orders', 'restropress' ); ?></span>
				</div>
			</div>

			<div class="rp-card rp-reports-risk-panel tone-<?php echo esc_attr( $data['health']['tone'] ); ?>">
				<h2><?php esc_html_e( 'Risk Score', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Late, cancelled, refunded, failed, and unpaid order trend.', 'restropress' ); ?></p>
				<strong><?php echo esc_html( $data['health']['label'] ); ?></strong>
				<p><?php echo esc_html( $data['health']['text'] ); ?></p>
				<ol class="rp-reports-insights">
					<?php foreach ( $data['insights'] as $insight ) : ?>
						<li><?php echo esc_html( $insight ); ?></li>
					<?php endforeach; ?>
				</ol>
			</div>
		</div>
	</section>
	<?php
}
add_action( 'rpress_reports_tab_overview', 'rpress_reports_tab_overview' );
