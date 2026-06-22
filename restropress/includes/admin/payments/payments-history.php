<?php
/**
 * Admin Payment History
 *
 * @package     RPRESS
 * @subpackage  Admin/Payments
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
*/
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Payment History Page
 *
 * Renders the payment history page contents.
 *
 * @access      private
 * @since       1.0
 * @return      void
*/
function rpress_payment_history_page() {

	$rpress_payment = get_post_type_object( 'rpress_payment' );
	$view           = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : '';

	// Single-order detail page.
	if ( 'view-order-details' === $view ) {
		require_once RP_PLUGIN_DIR . 'includes/admin/payments/view-order-details.php';
		return;
	}

	// Live Orders kanban sub-page.
	if ( 'live' === $view ) {
		require_once RP_PLUGIN_DIR . 'includes/admin/payments/class-live-orders.php';
		require_once RP_PLUGIN_DIR . 'includes/admin/payments/class-payments-table.php';
		new RPRESS_Payment_History_Table();
		RP_Live_Orders::render();
		return;
	}

	// If the admin opted to default Orders to the Live Orders view, render it
	// directly. This callback can run after admin output has started, so a
	// redirect here can trigger "headers already sent" warnings.
	if ( $view === '' && rpress_get_option( 'live_orders_default_view' ) ) {
		require_once RP_PLUGIN_DIR . 'includes/admin/payments/class-live-orders.php';
		require_once RP_PLUGIN_DIR . 'includes/admin/payments/class-payments-table.php';
		new RPRESS_Payment_History_Table();
		RP_Live_Orders::render();
		return;
	}
	// `view=list` is just a marker that bypasses the default-view redirect; reset for the list render path.
	if ( 'list' === $view ) {
		$view = '';
	}

	// Default: list view.
	require_once RP_PLUGIN_DIR . 'includes/admin/payments/class-payments-table.php';
	$payments_table = new RPRESS_Payment_History_Table();
	$payments_table->prepare_items();

	$is_past      = RPRESS_Payment_History_Table::is_past_mode();
	$is_all_time  = RPRESS_Payment_History_Table::is_all_time_mode();
	$is_search    = isset( $_GET['s'] ) && '' !== sanitize_text_field( wp_unslash( $_GET['s'] ) );
	$has_scope_filter = $is_search || $is_all_time;
	$scope_filter_keys = array( 'order_status', 'service-type', 'status', 'gateway', 'service-date', 'urgency' );
	foreach ( $scope_filter_keys as $scope_filter_key ) {
		if ( ! isset( $_GET[ $scope_filter_key ] ) ) {
			continue;
		}
		$scope_filter_value = sanitize_text_field( wp_unslash( $_GET[ $scope_filter_key ] ) );
		if ( '' !== $scope_filter_value && 'all' !== $scope_filter_value && 'any' !== $scope_filter_value ) {
			$has_scope_filter = true;
			break;
		}
	}
	$kpi          = RPRESS_Payment_History_Table::get_kpi_data();
	$export_url   = admin_url( 'admin.php?page=rpress-reports&tab=export' );
	$live_url     = admin_url( 'admin.php?page=rpress-payment-history&view=live' );

	$subtitle = $is_past
		? __( 'View, filter, and manage your restaurant order history.', 'restropress' )
		: __( 'Manage incoming restaurant orders, update statuses, and process fulfilment.', 'restropress' );
	?>
	<div class="wrap rp-admin-scope rp-orders-list-wrap<?php echo $is_past ? ' is-past-mode' : ''; ?>">

		<div class="rp-page-header rp-orders-pageheader">
			<div class="rp-page-header-titles rp-orders-pageheader-titles">
				<h1 class="wp-heading-inline rp-page-title rp-orders-title"><?php echo esc_html( $rpress_payment->labels->menu_name ); ?></h1>
				<p class="rp-page-subtitle rp-orders-subtitle"><?php echo esc_html( $subtitle ); ?></p>
			</div>
			<div class="rp-page-actions rp-orders-pageheader-actions">
				<a href="<?php echo esc_url( $live_url ); ?>" class="button button-primary rp-btn rp-btn-danger rp-orders-action rp-orders-action-live">
					<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
					<?php esc_html_e( 'Live Orders', 'restropress' ); ?>
				</a>
				<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary rp-btn rp-btn-secondary rp-orders-action">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'Export', 'restropress' ); ?>
				</a>
			</div>
		</div>
		<hr class="wp-header-end">

		<?php if ( $is_past ) : ?>
			<div class="rp-notice rp-orders-pastnotice" role="status">
				<span class="dashicons dashicons-info" aria-hidden="true"></span>
				<span class="rp-orders-pastnotice-text">
					<?php esc_html_e( 'You are viewing past orders. Results are based on the selected date range and filters.', 'restropress' ); ?>
				</span>
				<button type="button"
					class="rp-orders-pastnotice-close"
					aria-label="<?php esc_attr_e( 'Dismiss this notice', 'restropress' ); ?>">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				</button>
			</div>
		<?php endif; ?>

		<?php do_action( 'rpress_payments_page_top' ); ?>
		<form id="rpress-payments-filter" method="get" action="<?php echo esc_url( admin_url( 'admin.php?page=rpress-payment-history' ) ); ?>">
			<input type="hidden" name="page" value="rpress-payment-history" />
			<input type="hidden" name="view" value="list" />

			<?php $payments_table->date_row(); ?>

		<?php
		/* ── KPI cards - composition depends on mode. ───────────────── */
		if ( $has_scope_filter ) {
			$scope_kpi = $payments_table->get_current_scope_kpi_data();
			$is_all_time_only = $is_all_time && ! $is_search && empty( $_GET['order_status'] ) && empty( $_GET['service-type'] ) && empty( $_GET['status'] ) && ( empty( $_GET['gateway'] ) || 'all' === sanitize_text_field( wp_unslash( $_GET['gateway'] ) ) ) && empty( $_GET['service-date'] ) && empty( $_GET['urgency'] );
			if ( $is_all_time_only ) {
				$kpi_cards = array(
					array(
						'key'        => 'total',
						'label'      => __( 'Total Orders', 'restropress' ),
						'subtitle'   => __( 'All time', 'restropress' ),
						'icon'       => 'dashicons-cart',
						'tone'       => 'tone-blue',
						'value_text' => number_format_i18n( $scope_kpi['total'] ),
						'delta'      => null,
						'tooltip'    => '',
					),
					array(
						'key'        => 'completed',
						'label'      => __( 'Completed Orders', 'restropress' ),
						'subtitle'   => sprintf(
							/* translators: %s: percentage of total */
							esc_html__( '%s%% of total', 'restropress' ),
							number_format_i18n( $scope_kpi['completed_pct'], 1 )
						),
						'icon'       => 'dashicons-yes-alt',
						'tone'       => 'tone-green',
						'value_text' => number_format_i18n( $scope_kpi['completed'] ),
						'delta'      => null,
						'tooltip'    => '',
					),
					array(
						'key'        => 'revenue',
						'label'      => __( 'Total Revenue', 'restropress' ),
						'subtitle'   => __( 'Excl. refunds', 'restropress' ),
						'icon'       => 'dashicons-money-alt',
						'tone'       => 'tone-purple',
						'value_text' => rpress_currency_filter( rpress_format_amount( $scope_kpi['revenue'] ) ),
						'delta'      => null,
						'tooltip'    => '',
					),
					array(
						'key'        => 'refunds',
						'label'      => __( 'Refunds', 'restropress' ),
						'subtitle'   => sprintf(
							/* translators: %s: number of refunded orders */
							esc_html( _n( '%s order', '%s orders', $scope_kpi['refunds_count'], 'restropress' ) ),
							number_format_i18n( $scope_kpi['refunds_count'] )
						),
						'icon'       => 'dashicons-undo',
						'tone'       => 'tone-amber',
						'value_text' => rpress_currency_filter( rpress_format_amount( $scope_kpi['refunds_sum'] ) ),
						'delta'      => null,
						'tooltip'    => '',
					),
					array(
						'key'        => 'aov',
						'label'      => __( 'Average Order Value', 'restropress' ),
						'subtitle'   => __( 'All time', 'restropress' ),
						'icon'       => 'dashicons-chart-bar',
						'tone'       => 'tone-blue',
						'value_text' => rpress_currency_filter( rpress_format_amount( $scope_kpi['aov'] ) ),
						'delta'      => null,
						'tooltip'    => '',
					),
				);
			} else {
				$kpi_cards = array(
					array(
						'key'        => 'total',
						'label'      => __( 'Matching Orders', 'restropress' ),
						'subtitle'   => __( 'Current filters', 'restropress' ),
						'icon'       => 'dashicons-search',
						'tone'       => 'tone-blue',
						'value_text' => number_format_i18n( $scope_kpi['total'] ),
						'delta'      => null,
						'tooltip'    => '',
					),
					array(
						'key'        => 'needs_attention',
						'label'      => __( 'Needs Attention', 'restropress' ),
						'subtitle'   => __( 'New, late or payment pending', 'restropress' ),
						'icon'       => 'dashicons-warning',
						'tone'       => 'tone-amber',
						'value_text' => number_format_i18n( $scope_kpi['needs_attention'] ),
						'delta'      => null,
						'tooltip'    => '',
					),
					array(
						'key'        => 'in_progress',
						'label'      => __( 'In Progress', 'restropress' ),
						'subtitle'   => __( 'Preparing, ready or out', 'restropress' ),
						'icon'       => 'dashicons-clock',
						'tone'       => 'tone-green',
						'value_text' => number_format_i18n( $scope_kpi['in_progress'] ),
						'delta'      => null,
						'tooltip'    => '',
					),
					array(
						'key'        => 'revenue',
						'label'      => __( 'Matching Revenue', 'restropress' ),
						'subtitle'   => __( 'Paid &amp; cash orders, excl. refunds', 'restropress' ),
						'icon'       => 'dashicons-money-alt',
						'tone'       => 'tone-purple',
						'value_text' => rpress_currency_filter( rpress_format_amount( $scope_kpi['revenue'] ) ),
						'delta'      => null,
						'tooltip'    => '',
					),
				);
			}
		} elseif ( $is_past ) {
			$start_raw = isset( $_GET['start-date'] ) ? sanitize_text_field( wp_unslash( $_GET['start-date'] ) ) : '';
			$end_raw   = isset( $_GET['end-date'] )   ? sanitize_text_field( wp_unslash( $_GET['end-date'] ) )   : '';
			$start_ymd = function_exists( 'rpress_parse_payment_filter_date' ) ? rpress_parse_payment_filter_date( $start_raw ) : '';
			$end_ymd   = function_exists( 'rpress_parse_payment_filter_date' ) ? rpress_parse_payment_filter_date( $end_raw ) : '';
			$start_ts  = $start_ymd ? strtotime( $start_ymd ) : strtotime( '-1 day' );
			$end_ts    = $end_ymd   ? strtotime( $end_ymd )   : strtotime( '-1 day' );
			$past_kpi  = RPRESS_Payment_History_Table::get_kpi_data_for_range(
				date_i18n( 'Y-m-d', $start_ts ),
				date_i18n( 'Y-m-d', $end_ts )
			);

			$kpi_cards = array(
				array(
					'key'        => 'total',
					'label'      => __( 'Total Orders', 'restropress' ),
					'subtitle'   => __( 'In selected period', 'restropress' ),
					'icon'       => 'dashicons-cart',
					'tone'       => 'tone-blue',
					'value_text' => number_format_i18n( $past_kpi['total'] ),
					'delta'      => null,
					'tooltip'    => '',
				),
				array(
					'key'        => 'completed',
					'label'      => __( 'Completed Orders', 'restropress' ),
					'subtitle'   => sprintf(
						/* translators: %s: percentage of total */
						esc_html__( '%s%% of total', 'restropress' ),
						number_format_i18n( $past_kpi['completed_pct'], 1 )
					),
					'icon'       => 'dashicons-yes-alt',
					'tone'       => 'tone-green',
					'value_text' => number_format_i18n( $past_kpi['completed'] ),
					'delta'      => null,
					'tooltip'    => '',
				),
				array(
					'key'        => 'revenue',
					'label'      => __( 'Total Revenue', 'restropress' ),
					'subtitle'   => __( 'Excl. refunds', 'restropress' ),
					'icon'       => 'dashicons-money-alt',
					'tone'       => 'tone-purple',
					'value_text' => rpress_currency_filter( rpress_format_amount( $past_kpi['revenue'] ) ),
					'delta'      => null,
					'tooltip'    => '',
				),
				array(
					'key'        => 'refunds',
					'label'      => __( 'Refunds', 'restropress' ),
					'subtitle'   => sprintf(
						/* translators: %s: number of refunded orders */
						esc_html( _n( '%s order', '%s orders', $past_kpi['refunds_count'], 'restropress' ) ),
						number_format_i18n( $past_kpi['refunds_count'] )
					),
					'icon'       => 'dashicons-undo',
					'tone'       => 'tone-amber',
					'value_text' => rpress_currency_filter( rpress_format_amount( $past_kpi['refunds_sum'] ) ),
					'delta'      => null,
					'tooltip'    => '',
				),
				array(
					'key'        => 'aov',
					'label'      => __( 'Average Order Value', 'restropress' ),
					'subtitle'   => __( 'In selected period', 'restropress' ),
					'icon'       => 'dashicons-chart-bar',
					'tone'       => 'tone-blue',
					'value_text' => rpress_currency_filter( rpress_format_amount( $past_kpi['aov'] ) ),
					'delta'      => null,
					'tooltip'    => '',
				),
			);
		} else {
			$kpi_cards = array(
				array(
					'key'        => 'total',
					'label'      => __( "Today's Orders", 'restropress' ),
					'subtitle'   => __( 'All order types', 'restropress' ),
					'icon'       => 'dashicons-cart',
					'tone'       => 'tone-blue',
					'value_text' => number_format_i18n( $kpi['total']['value'] ),
					'delta'      => $kpi['total']['delta'],
					'tooltip'    => '',
				),
				array(
					'key'        => 'needs_attention',
					'label'      => __( 'Needs Attention', 'restropress' ),
					'subtitle'   => __( 'New, late or payment pending', 'restropress' ),
					'icon'       => 'dashicons-warning',
					'tone'       => 'tone-amber',
					'value_text' => number_format_i18n( $kpi['needs_attention']['value'] ),
					'delta'      => $kpi['needs_attention']['delta'],
					'tooltip'    => '',
				),
				array(
					'key'        => 'in_progress',
					'label'      => __( 'In Progress', 'restropress' ),
					'subtitle'   => __( 'Preparing, ready or out', 'restropress' ),
					'icon'       => 'dashicons-clock',
					'tone'       => 'tone-green',
					'value_text' => number_format_i18n( $kpi['in_progress']['value'] ),
					'delta'      => $kpi['in_progress']['delta'],
					'tooltip'    => '',
				),
				array(
					'key'        => 'revenue',
					'label'      => __( 'Revenue Today', 'restropress' ),
					'subtitle'   => __( 'Paid &amp; cash orders, excl. refunds', 'restropress' ),
					'icon'       => 'dashicons-money-alt',
					'tone'       => 'tone-purple',
					'value_text' => rpress_currency_filter( rpress_format_amount( $kpi['revenue']['value'] ) ),
					'delta'      => $kpi['revenue']['delta'],
					'tooltip'    => __( 'Includes paid online orders and accepted cash orders. Cancelled and refunded orders are excluded.', 'restropress' ),
				),
			);
		}
		?>
		<div class="rp-grid rp-grid-auto rp-orders-kpi-row">
			<?php foreach ( $kpi_cards as $card ) :
				$delta_class = '';
				$delta_text  = '';
				$delta_icon  = '';
				if ( null !== $card['delta'] ) {
					$rounded = (int) round( $card['delta'] );
					if ( $rounded > 0 ) {
						$delta_class = 'is-up';
						$delta_icon  = 'dashicons-arrow-up-alt';
						$delta_text  = $rounded . '%';
					} elseif ( $rounded < 0 ) {
						$delta_class = 'is-down';
						$delta_icon  = 'dashicons-arrow-down-alt';
						// Arrow already conveys direction - strip the minus sign.
						$delta_text  = abs( $rounded ) . '%';
					} else {
						$delta_class = 'is-flat';
						$delta_icon  = 'dashicons-minus';
						$delta_text  = '0%';
					}
				}
			?>
				<div class="rp-card rp-metric-card rp-orders-kpi-card <?php echo esc_attr( $card['tone'] ); ?>">
					<div class="rp-metric-icon rp-orders-kpi-icon">
						<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
					</div>
					<div class="rp-orders-kpi-body">
						<div class="rp-orders-kpi-label">
							<?php echo esc_html( $card['label'] ); ?>
							<?php if ( ! empty( $card['tooltip'] ) ) : ?>
								<span class="rp-orders-kpi-info" title="<?php echo esc_attr( $card['tooltip'] ); ?>" tabindex="0" aria-label="<?php echo esc_attr( $card['tooltip'] ); ?>">
									<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
								</span>
							<?php endif; ?>
						</div>
						<div class="rp-orders-kpi-value"><?php echo wp_kses( $card['value_text'], array( 'span' => array( 'class' => array() ) ) ); ?></div>
						<?php if ( ! empty( $card['subtitle'] ) ) : ?>
							<div class="rp-orders-kpi-subtitle"><?php echo wp_kses( $card['subtitle'], array() ); ?></div>
						<?php endif; ?>
						<?php if ( $delta_text ) : ?>
							<div class="rp-orders-kpi-footer">
								<span class="rp-orders-kpi-delta <?php echo esc_attr( $delta_class ); ?>">
									<span class="dashicons <?php echo esc_attr( $delta_icon ); ?>" aria-hidden="true"></span>
									<?php echo esc_html( $delta_text ); ?>
								</span>
								<span class="rp-orders-kpi-period"><?php esc_html_e( 'vs yesterday', 'restropress' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

			<?php $payments_table->views() ?>
			<?php $payments_table->advanced_filters(); ?>
			<?php $payments_table->display() ?>
		</form>
		<?php do_action( 'rpress_payments_page_bottom' ); ?>
	</div>
	<?php
}
/**
 * Payment History admin titles
 *
 * @since  1.0.0
 *
 * @param $admin_title
 * @param $title
 * @return string
 */
function rpress_view_order_details_title( $admin_title, $title ) {
	if ( 'restropress_page_rpress-payment-history' != get_current_screen()->base )
		return $admin_title;
	if( ! isset( $_GET['rpress-action'] ) )
		return $admin_title;
	switch( sanitize_title( $_GET['rpress-action'] ) ) :
		case 'view-order-details' :
			$title = esc_html__( 'View Order Details', 'restropress' ) . ' - ' . $admin_title;
			break;
		case 'edit-payment' :
			$title = esc_html__( 'Edit Payment', 'restropress' ) . ' - ' . $admin_title;
			break;
		default:
			$title = $admin_title;
			break;
	endswitch;
	return $title;
}
add_filter( 'admin_title', 'rpress_view_order_details_title', 10, 2 );
/**
 * Intercept default Edit post links for RPRESS payments and rewrite them to the View Order Details screen
 *
 * @since 1.0.4
 *
 * @param $url
 * @param $post_id
 * @param $context
 * @return string
 */
function rpress_override_edit_post_for_payment_link( $url, $post_id = 0, $context = null ) {
	$post = get_post( $post_id );
	if( ! $post )
		return $url;
	if( 'rpress_payment' != $post->post_type )
		return $url;
	$url = admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details&id=' . $post_id );
	return $url;
}
add_filter( 'get_edit_post_link', 'rpress_override_edit_post_for_payment_link', 10, 3 );
