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
 * Render the Taxes report tab.
 *
 * @since 3.3
 * @return void
 */
function rpress_reports_tab_taxes_intelligence() {
	if ( ! current_user_can( 'view_shop_reports' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this report', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}

	$filters  = RPRESS_Reports_Intelligence::get_filters();
	$data     = RPRESS_Reports_Intelligence::build_taxes( $filters );
	$current  = $data['current'];
	$previous = $data['previous'];
	?>
	<section class="rp-grid rp-reports-intelligence">
		<?php rpress_reports_intelligence_filters( 'taxes', $filters ); ?>

		<div class="rp-grid rp-reports-kpi-grid rp-reports-kpi-grid-five">
			<?php
			rpress_reports_metric_card( esc_html__( 'Tax Collected', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['tax_collected'] ), RPRESS_Reports_Intelligence::delta( $current['tax_collected'], $previous['tax_collected'] ), 'clipboard', 'blue' );
			rpress_reports_metric_card( esc_html__( 'Taxable Sales', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['taxable_sales'] ), RPRESS_Reports_Intelligence::delta( $current['taxable_sales'], $previous['taxable_sales'] ), 'money-alt', 'green' );
			rpress_reports_metric_card( esc_html__( 'Non-taxable Sales', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['non_taxable_sales'] ), RPRESS_Reports_Intelligence::delta( $current['non_taxable_sales'], $previous['non_taxable_sales'] ), 'hidden', 'purple' );
			rpress_reports_metric_card( esc_html__( 'Net Sales', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['net_sales'] ), RPRESS_Reports_Intelligence::delta( $current['net_sales'], $previous['net_sales'] ), 'chart-line', 'green' );
			rpress_reports_metric_card( esc_html__( 'Taxable Orders', 'restropress' ), number_format_i18n( $current['taxable_orders'] ), RPRESS_Reports_Intelligence::delta( $current['taxable_orders'], $previous['taxable_orders'] ), 'cart', 'amber' );
			?>
		</div>

		<div class="rp-card rp-reports-panel">
			<h2><?php esc_html_e( 'Tax Detail', 'restropress' ); ?></h2>
			<p><?php esc_html_e( 'Paid orders only. Refunded orders are shown as tax adjustments for review.', 'restropress' ); ?></p>
			<div class="rp-table-scroll">
				<table class="widefat striped rp-reports-table rp-reports-taxes-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Taxable Sales', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Tax Collected', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Orders', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Refund Tax Adjustment', 'restropress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $current['rows'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['date'] ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $row['taxable_sales'] ) ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $row['tax_collected'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['orders'] ) ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $row['refund_tax_adjustment'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
	<?php
}
add_action( 'rpress_reports_tab_taxes', 'rpress_reports_tab_taxes_intelligence' );
