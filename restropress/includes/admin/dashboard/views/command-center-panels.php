<?php
/**
 * Command center dynamic panels.
 *
 * Rendered by RPRESS_Command_Center_Dashboard::render_panels() with a
 * $dashboard payload in scope. Shared by the page template and the AJAX
 * live-refresh response, so everything here must be self-contained.
 *
 * @package RPRESS
 * @subpackage Admin/Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$live_orders_url = admin_url( 'admin.php?page=rpress-payment-history&view=live' );
$orders_url      = RPRESS_Command_Center_Dashboard::orders_url();
$settings_url    = admin_url( 'admin.php?page=rpress-settings&tab=general&section=service_hours#rpress_settings%5Benable_service%5D%5Bdelivery_and_pickup%5D' );
$hours_url       = admin_url( 'admin.php?page=rpress-settings&tab=general&section=service_hours#rpress_settings%5Bopen_time%5D' );
$menu_url        = admin_url( 'edit.php?post_type=fooditem' );
$add_item_url    = admin_url( 'post-new.php?post_type=fooditem' );
$max_hour_orders = max( 1, max( wp_list_pluck( $dashboard['hourly'], 'orders' ) ) );
?>

<div class="rp-grid rp-grid-command-strip rp-now-strip rp-health-<?php echo esc_attr( $dashboard['health']['key'] ); ?>">
	<div class="rp-shift-health">
		<span><?php esc_html_e( 'Shift Health', 'restropress' ); ?></span>
		<strong><?php echo esc_html( $dashboard['health']['label'] ); ?></strong>
		<small><?php echo esc_html( implode( ', ', $dashboard['health']['reasons'] ) ); ?></small>
	</div>
	<div class="rp-store-status rp-store-<?php echo esc_attr( $dashboard['store_status']['key'] ); ?>">
		<span><?php esc_html_e( 'Store', 'restropress' ); ?></span>
		<strong><?php echo esc_html( $dashboard['store_status']['label'] ); ?></strong>
	</div>
	<div>
		<span><?php esc_html_e( 'Service Modes', 'restropress' ); ?></span>
		<strong><?php echo esc_html( ! empty( $dashboard['service_modes'] ) ? implode( ' / ', array_map( 'wp_strip_all_tags', $dashboard['service_modes'] ) ) : __( 'Not configured', 'restropress' ) ); ?></strong>
	</div>
	<div>
		<span><?php esc_html_e( 'Today Window', 'restropress' ); ?></span>
		<strong><?php echo esc_html( $dashboard['store_status']['window'] ); ?></strong>
	</div>
	<div class="rp-now-metric">
		<span><?php esc_html_e( 'Live Load', 'restropress' ); ?></span>
		<strong><?php echo esc_html( number_format_i18n( $dashboard['kpis']['active_load'] ) ); ?></strong>
	</div>
	<div class="rp-now-metric is-danger">
		<span><?php esc_html_e( 'Late', 'restropress' ); ?></span>
		<strong><?php echo esc_html( number_format_i18n( $dashboard['ops_counts']['late'] ) ); ?></strong>
	</div>
	<div class="rp-now-metric is-warning">
		<span><?php esc_html_e( 'Payment Issues', 'restropress' ); ?></span>
		<strong><?php echo esc_html( number_format_i18n( $dashboard['ops_counts']['unpaid_active'] ) ); ?></strong>
	</div>
	<div class="rp-store-actions">
		<a class="button button-primary" href="<?php echo esc_url( $live_orders_url ); ?>"><?php esc_html_e( 'Open Live Orders', 'restropress' ); ?></a>
		<a class="button" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Configure Ordering', 'restropress' ); ?></a>
		<a class="button" href="<?php echo esc_url( $hours_url ); ?>"><?php esc_html_e( 'Manage Hours', 'restropress' ); ?></a>
	</div>
</div>

<div class="rp-grid rp-grid-auto rp-operator-kpis">
	<div class="rp-kpi-card tone-danger">
		<span><?php esc_html_e( 'Needs Action', 'restropress' ); ?></span>
		<strong><?php echo esc_html( number_format_i18n( $dashboard['kpis']['needs_action'] ) ); ?></strong>
		<small><?php esc_html_e( 'Late, pending, payment issues or recovery items', 'restropress' ); ?></small>
	</div>
	<div class="rp-kpi-card tone-blue">
		<span><?php esc_html_e( 'Active Load', 'restropress' ); ?></span>
		<strong><?php echo esc_html( number_format_i18n( $dashboard['kpis']['active_load'] ) ); ?></strong>
		<small><?php esc_html_e( 'Accepted, preparing, ready and transit', 'restropress' ); ?></small>
	</div>
	<div class="rp-kpi-card tone-amber">
		<span><?php esc_html_e( 'Promise Risk', 'restropress' ); ?></span>
		<strong><?php echo esc_html( number_format_i18n( $dashboard['kpis']['promise_risk'] ) ); ?></strong>
		<small><?php esc_html_e( 'Due soon or already late', 'restropress' ); ?></small>
	</div>
	<?php if ( $dashboard['kpis']['recovery'] > 0 ) : ?>
		<div class="rp-kpi-card tone-red">
			<span><?php esc_html_e( 'Recovery Count', 'restropress' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $dashboard['kpis']['recovery'] ) ); ?></strong>
			<small><?php esc_html_e( 'Cancelled, refunded or failed today', 'restropress' ); ?></small>
		</div>
	<?php endif; ?>
</div>

<div class="rp-grid rp-grid-sidebar-360 rp-command-grid">
	<section class="rp-command-panel rp-priority-panel">
		<div class="rp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Priority Queue', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Only orders that can hurt service quality appear here.', 'restropress' ); ?></p>
			</div>
			<a href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'View all orders', 'restropress' ); ?></a>
		</div>

		<?php if ( empty( $dashboard['priority_queue'] ) ) : ?>
			<div class="rp-empty-state">
				<strong><?php esc_html_e( 'No exceptions right now.', 'restropress' ); ?></strong>
				<p><?php esc_html_e( 'Kitchen flow is clean. Keep watching live orders as the rush changes.', 'restropress' ); ?></p>
			</div>
		<?php else : ?>
			<div class="rp-priority-table-wrap">
				<table class="widefat fixed striped rp-priority-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Issue', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Service', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Promise', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Status', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Total', 'restropress' ); ?></th>
							<th><?php esc_html_e( 'Action', 'restropress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $dashboard['priority_queue'] as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['number'] ); ?></strong></td>
								<td>
									<span class="rp-issue-badge issue-<?php echo esc_attr( $row['issue']['key'] ); ?>"><?php echo esc_html( $row['issue']['label'] ); ?></span>
									<small><?php echo esc_html( $row['issue']['detail'] ); ?></small>
								</td>
								<td><?php echo esc_html( $row['customer'] ); ?></td>
								<td>
									<span class="rp-command-service-badge badge-<?php echo esc_attr( sanitize_html_class( $row['service_key'] ) ); ?>">
										<?php echo RPRESS_Command_Center_Dashboard::service_icon( $row['service_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<span><?php echo esc_html( $row['service'] ); ?></span>
									</span>
								</td>
								<td>
									<?php echo esc_html( $row['promise'] ); ?><br>
									<small><?php echo esc_html( $row['elapsed'] ); ?></small>
								</td>
								<td>
									<span class="rp-status-chip status-<?php echo esc_attr( $row['status_key'] ); ?>"><?php echo esc_html( $row['status'] ); ?></span>
									<span class="rp-payment-chip payment-<?php echo esc_attr( sanitize_html_class( $row['payment']['key'] ) ); ?>"><?php echo esc_html( $row['payment']['label'] ); ?></span>
								</td>
								<td><?php echo esc_html( RPRESS_Command_Center_Dashboard::money( $row['total'] ) ); ?></td>
								<td><a class="button button-small" href="<?php echo esc_url( $row['details_url'] ); ?>"><?php esc_html_e( 'Review', 'restropress' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>

	<aside class="rp-command-side">
		<section class="rp-command-panel">
			<div class="rp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Insights & Actions', 'restropress' ); ?></h2>
					<p><?php esc_html_e( 'Ranked prompts from today’s operating data.', 'restropress' ); ?></p>
				</div>
			</div>
			<ol class="rp-insight-list">
				<?php foreach ( $dashboard['insights'] as $insight ) : ?>
					<li><?php echo esc_html( $insight ); ?></li>
				<?php endforeach; ?>
			</ol>
		</section>
	</aside>
</div>

<div class="rp-grid rp-grid-2 rp-command-grid rp-command-grid-secondary">
	<section class="rp-command-panel">
		<div class="rp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Kitchen Flow', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Bottleneck view for the current shift.', 'restropress' ); ?></p>
			</div>
			<a href="<?php echo esc_url( $live_orders_url ); ?>"><?php esc_html_e( 'Open Live Orders', 'restropress' ); ?></a>
		</div>
		<div class="rp-grid rp-grid-6 rp-flow-lanes">
			<?php
			$flow_lanes = array(
				'pending'    => __( 'New', 'restropress' ),
				'accepted'   => __( 'Accepted', 'restropress' ),
				'processing' => __( 'Preparing', 'restropress' ),
				'ready'      => __( 'Ready', 'restropress' ),
				'transit'    => __( 'Out for Delivery', 'restropress' ),
				'completed'  => __( 'Completed', 'restropress' ),
			);
			foreach ( $flow_lanes as $key => $label ) :
				$count = isset( $dashboard['status_counts'][ $key ] ) ? (int) $dashboard['status_counts'][ $key ] : 0;
				?>
				<div class="rp-flow-lane lane-<?php echo esc_attr( $key ); ?> <?php echo $count > 0 ? 'is-active' : 'is-empty'; ?>">
					<span><?php echo esc_html( $label ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong>
					<small><?php echo esc_html( _n( 'order', 'orders', $count, 'restropress' ) ); ?></small>
				</div>
			<?php endforeach; ?>
		</div>
		<h3 class="rp-panel-subheading"><?php esc_html_e( 'Service Mix', 'restropress' ); ?></h3>
		<div class="rp-service-split">
			<?php foreach ( $dashboard['service_counts'] as $service_key => $service ) : ?>
				<span class="rp-command-service-badge badge-<?php echo esc_attr( sanitize_html_class( $service_key ) ); ?>">
					<?php echo RPRESS_Command_Center_Dashboard::service_icon( $service_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php echo esc_html( $service['label'] ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $service['count'] ) ); ?></strong>
				</span>
			<?php endforeach; ?>
		</div>
		<h3 class="rp-panel-subheading"><?php esc_html_e( 'Bottlenecks', 'restropress' ); ?></h3>
		<ul class="rp-bottleneck-list">
			<?php foreach ( $dashboard['bottlenecks'] as $bottleneck ) : ?>
				<li><?php echo esc_html( $bottleneck ); ?></li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="rp-command-panel">
		<div class="rp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Rush Timeline', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Orders by service hour today. The current hour is highlighted.', 'restropress' ); ?></p>
			</div>
		</div>
		<div class="rp-grid rp-grid-24 rp-rush-chart" aria-label="<?php esc_attr_e( 'Orders by service hour today', 'restropress' ); ?>">
			<?php foreach ( $dashboard['hourly'] as $hour => $bucket ) : ?>
				<?php
				$height = max( 6, round( ( (int) $bucket['orders'] / $max_hour_orders ) * 100 ) );
				$is_now = (int) current_time( 'G' ) === (int) $hour;
				?>
				<div class="rp-rush-bar<?php echo $is_now ? ' is-current' : ''; ?>" title="<?php echo esc_attr( sprintf( __( '%1$s: %2$d orders', 'restropress' ), date_i18n( 'ga', strtotime( $hour . ':00' ) ), $bucket['orders'] ) ); ?>">
					<span style="height: <?php echo esc_attr( $height ); ?>%"></span>
					<small><?php echo esc_html( 0 === $hour % 3 ? date_i18n( 'ga', strtotime( $hour . ':00' ) ) : '' ); ?></small>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
</div>

<div class="rp-grid rp-grid-3 rp-command-grid rp-command-grid-thirds">
	<section class="rp-command-panel">
		<div class="rp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Sales Snapshot', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Paid online orders plus accepted cash orders for today’s service.', 'restropress' ); ?></p>
			</div>
		</div>
		<div class="rp-grid rp-grid-2 rp-mini-stats">
			<div><span><?php esc_html_e( 'Today Net Sales', 'restropress' ); ?></span><strong><?php echo esc_html( RPRESS_Command_Center_Dashboard::money( $dashboard['kpis']['net_sales'] ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Average Ticket', 'restropress' ); ?></span><strong><?php echo esc_html( RPRESS_Command_Center_Dashboard::money( $dashboard['kpis']['avg_ticket'] ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Cash to Collect', 'restropress' ); ?></span><strong><?php echo esc_html( RPRESS_Command_Center_Dashboard::money( $dashboard['cash_due_total'] ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Refunded Today', 'restropress' ); ?></span><strong><?php echo esc_html( RPRESS_Command_Center_Dashboard::money( $dashboard['refund_total'] ) ); ?></strong></div>
		</div>
	</section>

	<section class="rp-command-panel">
		<div class="rp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Menu Readiness', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Stock and menu signals before the next rush.', 'restropress' ); ?></p>
			</div>
			<a href="<?php echo esc_url( $menu_url ); ?>"><?php esc_html_e( 'Manage Menu', 'restropress' ); ?></a>
		</div>
		<div class="rp-grid rp-grid-2 rp-readiness-grid">
			<div class="rp-readiness-card is-ready">
				<span><?php esc_html_e( 'Published Items', 'restropress' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $dashboard['menu']['published'] ) ); ?></strong>
				<small><?php esc_html_e( 'Available to sell', 'restropress' ); ?></small>
			</div>
			<div class="rp-readiness-card <?php echo $dashboard['menu']['out_of_stock'] > 0 ? 'is-warning' : 'is-ready'; ?>">
				<span><?php esc_html_e( 'Unavailable', 'restropress' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $dashboard['menu']['out_of_stock'] ) ); ?></strong>
				<small><?php echo $dashboard['menu']['out_of_stock'] > 0 ? esc_html__( 'Needs review', 'restropress' ) : esc_html__( 'No stock flags', 'restropress' ); ?></small>
			</div>
		</div>
		<?php if ( ! empty( $dashboard['menu']['top_items'] ) ) : ?>
			<h3 class="rp-panel-subheading"><?php esc_html_e( 'Top Today', 'restropress' ); ?></h3>
			<ul class="rp-top-items">
				<?php foreach ( $dashboard['menu']['top_items'] as $item ) : ?>
					<li><span><?php echo esc_html( $item['title'] ); ?></span><strong><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></strong></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<div class="rp-menu-empty">
				<strong><?php esc_html_e( 'No item sales yet today.', 'restropress' ); ?></strong>
				<p><?php esc_html_e( 'Top items will appear here once orders come in.', 'restropress' ); ?></p>
			</div>
		<?php endif; ?>
		<div class="rp-panel-actions">
			<a class="button button-secondary" href="<?php echo esc_url( $menu_url ); ?>"><?php esc_html_e( 'Manage Menu', 'restropress' ); ?></a>
			<a class="button button-secondary" href="<?php echo esc_url( $add_item_url ); ?>"><?php esc_html_e( 'Add Item', 'restropress' ); ?></a>
		</div>
	</section>

	<section class="rp-command-panel">
		<div class="rp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Service Recovery', 'restropress' ); ?></h2>
				<p><?php esc_html_e( 'Protect ratings, refunds, and repeat customers.', 'restropress' ); ?></p>
			</div>
		</div>
		<div class="rp-grid rp-grid-2 rp-recovery-grid">
			<a class="tone-red" href="<?php echo esc_url( add_query_arg( 'order_status', 'cancelled', $orders_url ) ); ?>"><span><?php esc_html_e( 'Cancelled', 'restropress' ); ?></span><strong><?php echo esc_html( number_format_i18n( $dashboard['recovery']['cancelled'] ) ); ?></strong><small><?php esc_html_e( 'Review cancellations', 'restropress' ); ?></small></a>
			<a class="tone-amber" href="<?php echo esc_url( add_query_arg( 'status', 'refunded', $orders_url ) ); ?>"><span><?php esc_html_e( 'Refunded', 'restropress' ); ?></span><strong><?php echo esc_html( number_format_i18n( $dashboard['recovery']['refunded'] ) ); ?></strong><small><?php esc_html_e( 'Check refund impact', 'restropress' ); ?></small></a>
			<a class="tone-red" href="<?php echo esc_url( add_query_arg( 'status', 'failed', $orders_url ) ); ?>"><span><?php esc_html_e( 'Failed / Unpaid', 'restropress' ); ?></span><strong><?php echo esc_html( number_format_i18n( $dashboard['recovery']['failed'] ) ); ?></strong><small><?php esc_html_e( 'Recover payment', 'restropress' ); ?></small></a>
			<a class="tone-blue" href="<?php echo esc_url( add_query_arg( 'urgency', 'late', $orders_url ) ); ?>"><span><?php esc_html_e( 'Late Active', 'restropress' ); ?></span><strong><?php echo esc_html( number_format_i18n( $dashboard['recovery']['late'] ) ); ?></strong><small><?php esc_html_e( 'Protect promise time', 'restropress' ); ?></small></a>
		</div>
	</section>
</div>
