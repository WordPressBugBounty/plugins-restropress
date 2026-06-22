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
 * Render the Menu report tab.
 *
 * @since 3.3
 * @return void
 */
function rpress_reports_tab_menu() {
	if ( ! current_user_can( 'view_shop_reports' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this report', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}

	$filters  = RPRESS_Reports_Intelligence::get_filters();
	$data     = RPRESS_Reports_Intelligence::build_menu( $filters );
	$current  = $data['current'];
	$previous = $data['previous'];
	$top_item = ! empty( $current['top_quantity'][0]['name'] ) && $current['top_quantity'][0]['quantity'] > 0 ? $current['top_quantity'][0]['name'] : esc_html__( 'No item sales', 'restropress' );
	?>
	<section class="rp-grid rp-reports-intelligence">
		<?php rpress_reports_intelligence_filters( 'menu', $filters ); ?>

		<div class="rp-grid rp-reports-kpi-grid rp-reports-kpi-grid-six">
			<?php
			rpress_reports_metric_card( esc_html__( 'Items Sold', 'restropress' ), number_format_i18n( $current['total_quantity'] ), RPRESS_Reports_Intelligence::delta( $current['total_quantity'], $previous['total_quantity'] ), 'food', 'blue' );
			rpress_reports_metric_card( esc_html__( 'Menu Net Sales', 'restropress' ), RPRESS_Reports_Intelligence::money( $current['total_net_sales'] ), RPRESS_Reports_Intelligence::delta( $current['total_net_sales'], $previous['total_net_sales'] ), 'money-alt', 'green' );
			rpress_reports_metric_card( esc_html__( 'Low Sellers', 'restropress' ), number_format_i18n( $current['zero_sellers'] ), RPRESS_Reports_Intelligence::delta( $current['zero_sellers'], $previous['zero_sellers'] ), 'visibility', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Refund / Cancel Items', 'restropress' ), number_format_i18n( $current['risk_count'] ), RPRESS_Reports_Intelligence::delta( $current['risk_count'], $previous['risk_count'] ), 'warning', 'amber' );
			rpress_reports_metric_card( esc_html__( 'Published Items', 'restropress' ), number_format_i18n( $data['readiness']['published'] ), array( 'label' => esc_html__( 'Current menu', 'restropress' ), 'tone' => 'flat' ), 'list-view', 'purple' );
			rpress_reports_metric_card( esc_html__( 'Unavailable Items', 'restropress' ), number_format_i18n( $data['readiness']['unavailable'] ), array( 'label' => esc_html__( 'Current availability', 'restropress' ), 'tone' => 'flat' ), 'hidden', 'amber' );
			?>
		</div>

		<div class="rp-grid rp-reports-menu-grid">
			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Top Items by Quantity', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Unit movement during the selected period only.', 'restropress' ); ?></p>
				<?php rpress_reports_count_bar_list( rpress_reports_menu_rows( $current['top_quantity'], 'quantity' ), max( 1, $current['total_quantity'] ) ); ?>
			</div>

			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Top Items by Net Sales', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Revenue concentration, net of cancelled and refunded orders.', 'restropress' ); ?></p>
				<?php rpress_reports_bar_list( rpress_reports_menu_rows( $current['top_sales'], 'net_sales' ), $current['total_net_sales'] ); ?>
			</div>

			<div class="rp-card rp-reports-panel rp-reports-menu-hero">
				<h2><?php esc_html_e( 'Menu Signal', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Quick read on what moved and what needs attention.', 'restropress' ); ?></p>
				<strong><?php echo esc_html( $top_item ); ?></strong>
				<span><?php esc_html_e( 'Best mover this period', 'restropress' ); ?></span>
				<ul class="rp-reports-mini-list">
					<li><span><?php esc_html_e( 'Zero-sale items', 'restropress' ); ?></span><strong><?php echo esc_html( number_format_i18n( $current['zero_sellers'] ) ); ?></strong></li>
					<li><span><?php esc_html_e( 'Category sales groups', 'restropress' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( $current['category_mix'] ) ) ); ?></strong></li>
					<li><span><?php esc_html_e( 'Add-ons sold', 'restropress' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( $current['addons'] ) ) ); ?></strong></li>
				</ul>
			</div>
		</div>

		<div class="rp-grid rp-reports-menu-grid-secondary">
			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Category Mix', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Primary category sales for the selected period.', 'restropress' ); ?></p>
				<?php rpress_reports_bar_list( rpress_reports_named_amount_rows( $current['category_mix'] ), $current['total_net_sales'] ); ?>
			</div>

			<div class="rp-card rp-reports-panel">
				<h2><?php esc_html_e( 'Add-on Performance', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Add-ons found in selected-period paid order items.', 'restropress' ); ?></p>
				<?php rpress_reports_bar_list( rpress_reports_named_amount_rows( $current['addons'] ), rpress_reports_sum_key( $current['addons'], 'net_sales' ) ); ?>
			</div>
		</div>

		<div class="rp-card rp-reports-panel">
			<h2><?php esc_html_e( 'Menu Item Detail', 'restropress' ); ?></h2>
			<p><?php esc_html_e( 'Every value is scoped to the selected period, except availability which reflects the current menu state.', 'restropress' ); ?></p>
			<div class="rp-table-scroll">
				<table class="widefat striped rp-reports-table rp-reports-menu-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Item', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Quantity Sold', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Net Sales', 'restropress' ); ?></th>
							<th><?php esc_html_e( '% of Total', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Refund / Cancel Count', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Availability', 'restropress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $current['all_items'] as $item ) : ?>
							<?php $share = $current['total_net_sales'] > 0 ? ( (float) $item['net_sales'] / (float) $current['total_net_sales'] ) * 100 : 0; ?>
							<tr>
								<td><strong><?php echo esc_html( $item['name'] ); ?></strong></td>
								<td><?php echo esc_html( number_format_i18n( $item['quantity'] ) ); ?></td>
								<td><?php echo esc_html( RPRESS_Reports_Intelligence::money( $item['net_sales'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $share, 1 ) ); ?>%</td>
								<td><?php echo esc_html( number_format_i18n( $item['risk_count'] ) ); ?></td>
								<td><span class="rp-reports-status-pill <?php echo 'unavailable' === $item['availability_status'] ? 'is-warning' : 'is-success'; ?>"><?php echo esc_html( $item['availability'] ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
	<?php
}
add_action( 'rpress_reports_tab_menu', 'rpress_reports_tab_menu' );
