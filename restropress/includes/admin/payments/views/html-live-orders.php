<?php
/**
 * Live Orders page template.
 *
 * @package RPRESS
 * @subpackage Admin/Payments/Views
 * @since 3.3
 */

defined( 'ABSPATH' ) || exit;

$snapshot       = RP_Live_Orders::build_snapshot();
$poll_interval  = RP_Live_Orders::get_poll_interval();
$sound_url      = (string) rpress_get_option( 'notification_sound', '' );
$sound_loop     = (bool) rpress_get_option( 'notification_sound_loop', false );
$sound_duration = max( 1, (int) rpress_get_option( 'notification_duration', 5 ) );
$nonce          = wp_create_nonce( 'rp_live_orders_refresh' );
$ajax_url       = admin_url( 'admin-ajax.php' );
// Land on the all-time list so currently-active orders (which can be from
// earlier days) are visible. The list's own default range stays "Today" for
// direct visits via the menu - only this Live -> List jump widens the range
// so the two screens never disagree about a live order.
$list_url       = admin_url( 'admin.php?page=rpress-payment-history&view=list&date-range=all' );
$service_filters = RP_Live_Orders::get_service_filters();
$today_label      = date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) );

// Inline localised config - surfaces to JS without an additional wp_localize_script call.
$js_config = array(
	'ajaxUrl'       => $ajax_url,
	'nonce'         => $nonce,
	'pollInterval'  => $poll_interval,
	'soundUrl'      => $sound_url,
	'soundLoop'     => $sound_loop,
	'soundDuration' => $sound_duration,
	'initialIds'    => $snapshot['all_order_ids'],
	'strings'       => array(
		'soundOn'         => __( 'Sound alerts on', 'restropress' ),
		'soundOff'        => __( 'Sound alerts off', 'restropress' ),
		'enableSound'     => __( 'Click anywhere to enable sound', 'restropress' ),
		'updatedJustNow'  => __( 'Updated just now', 'restropress' ),
		'updatedSecAgo'   => __( 'Updated %ds ago', 'restropress' ),
		'updatedMinAgo'   => __( 'Updated %dm ago', 'restropress' ),
		'newOrder'        => __( 'New order', 'restropress' ),
		'empty'           => __( 'No orders here yet.', 'restropress' ),
		'justNow'         => __( 'just now', 'restropress' ),
		'minAgo'          => __( '%dm ago', 'restropress' ),
		'hourAgo'         => __( '%dh ago', 'restropress' ),
		'paused'          => __( 'Live updates paused', 'restropress' ),
		'pause'           => __( 'Pause updates', 'restropress' ),
		'resume'          => __( 'Resume', 'restropress' ),
		'visibleEmpty'    => __( 'No orders match these filters.', 'restropress' ),
		'sessionExpired'  => __( 'Session expired - reload the page to resume live updates.', 'restropress' ),
	),
);
?>
<div class="wrap rp-admin-scope rp-live-orders-wrap">
	<div class="rp-page-header rp-orders-pageheader rp-live-pageheader">
		<div class="rp-page-header-titles rp-orders-pageheader-titles">
			<h1 class="wp-heading-inline rp-page-title rp-orders-title"><?php esc_html_e( 'Live Orders', 'restropress' ); ?></h1>
			<p class="rp-page-subtitle rp-orders-subtitle">
				<?php esc_html_e( 'Monitor today’s active orders, update statuses, and keep fulfilment moving.', 'restropress' ); ?>
			</p>
		</div>
		<div class="rp-page-actions rp-orders-pageheader-actions">
			<a href="<?php echo esc_url( $list_url ); ?>" class="button button-secondary rp-btn rp-btn-secondary rp-orders-action rp-orders-action-list">
				<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
				<?php esc_html_e( 'All Orders', 'restropress' ); ?>
			</a>
		</div>
	</div>
	<hr class="wp-header-end">

	<div class="rp-live-orders" data-config="<?php echo esc_attr( wp_json_encode( $js_config ) ); ?>">

		<div class="rp-live-toolbar">
			<div class="rp-live-toolbar-left">
				<span class="rp-live-status">
					<span class="rp-live-status-dot" aria-hidden="true"></span>
					<span class="rp-live-status-main">
						<?php
						printf(
							/* translators: %d: seconds */
							esc_html__( 'Live · Updates every %ds', 'restropress' ),
							(int) $poll_interval
						);
						?>
					</span>
				</span>
				<span class="rp-live-status-text"><?php esc_html_e( 'Updated just now', 'restropress' ); ?></span>
			</div>
			<div class="rp-live-toolbar-right">
				<button type="button" class="button rp-live-refresh">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Refresh', 'restropress' ); ?>
				</button>
				<button type="button" class="button rp-live-pause" aria-pressed="false">
					<span class="dashicons dashicons-controls-pause" aria-hidden="true"></span>
					<span class="rp-live-pause-label"><?php esc_html_e( 'Pause updates', 'restropress' ); ?></span>
				</button>
				<button type="button" class="button rp-live-sound-toggle" aria-pressed="true">
					<span class="dashicons dashicons-controls-volumeon" aria-hidden="true"></span>
					<span class="rp-live-sound-label"><?php esc_html_e( 'Sound alerts on', 'restropress' ); ?></span>
				</button>
			</div>
		</div>

		<div class="rp-grid rp-grid-6 rp-live-kpis" aria-label="<?php esc_attr_e( 'Live order summary', 'restropress' ); ?>">
			<?php
			$kpi_cards = array(
				'new'       => array( 'label' => __( 'New', 'restropress' ), 'icon' => 'dashicons-products' ),
				'accepted'  => array( 'label' => __( 'Accepted', 'restropress' ), 'icon' => 'dashicons-clock' ),
				'preparing' => array( 'label' => __( 'Preparing', 'restropress' ), 'icon' => 'dashicons-food' ),
				'ready'     => array( 'label' => __( 'Ready', 'restropress' ), 'icon' => 'dashicons-yes-alt' ),
				'out'       => array( 'label' => __( 'Out for Delivery', 'restropress' ), 'icon' => 'dashicons-location-alt' ),
				'late'      => array( 'label' => __( 'Late', 'restropress' ), 'icon' => 'dashicons-warning' ),
			);
			foreach ( $kpi_cards as $key => $card ) :
				$count = isset( $snapshot['kpis'][ $key ] ) ? (int) $snapshot['kpis'][ $key ] : 0;
				?>
				<div class="rp-live-kpi rp-live-kpi-<?php echo esc_attr( $key ); ?>" data-kpi="<?php echo esc_attr( $key ); ?>">
					<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
					<span class="rp-live-kpi-label"><?php echo esc_html( $card['label'] ); ?></span>
					<strong class="rp-live-kpi-count"><?php echo (int) $count; ?></strong>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="rp-live-filters" aria-label="<?php esc_attr_e( 'Live order filters', 'restropress' ); ?>">
			<div class="rp-live-service-filter" role="group" aria-label="<?php esc_attr_e( 'Service type', 'restropress' ); ?>">
				<?php foreach ( $service_filters as $filter_key => $filter_label ) : ?>
					<button type="button"
					        class="rp-live-filter-chip<?php echo 'all' === $filter_key ? ' is-active' : ''; ?>"
					        data-service-filter="<?php echo esc_attr( $filter_key ); ?>"
					        aria-pressed="<?php echo 'all' === $filter_key ? 'true' : 'false'; ?>">
						<?php if ( 'all' !== $filter_key ) : ?>
							<?php echo RP_Live_Orders::get_service_type_icon( $filter_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
						<?php echo esc_html( $filter_label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<label class="rp-live-search">
				<span class="screen-reader-text"><?php esc_html_e( 'Search live orders', 'restropress' ); ?></span>
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<input type="search" class="rp-live-search-input" placeholder="<?php esc_attr_e( 'Search order, customer, phone', 'restropress' ); ?>">
			</label>
		</div>

		<div class="rp-grid rp-grid-5 rp-live-kanban">
			<?php foreach ( $snapshot['columns'] as $column_key => $col ) : ?>
				<section class="rp-live-column rp-live-column-tone-<?php echo esc_attr( $col['tone'] ); ?>"
				         data-column="<?php echo esc_attr( $column_key ); ?>"
				         data-default-status="<?php echo esc_attr( $col['default_status'] ); ?>"
				         data-statuses="<?php echo esc_attr( implode( ',', $col['statuses'] ) ); ?>">
					<header class="rp-live-column-header">
						<h2 class="rp-live-column-title"><?php echo esc_html( $col['label'] ); ?></h2>
						<span class="rp-live-column-count"><?php echo (int) $col['count']; ?></span>
					</header>
					<div class="rp-live-column-body">
						<?php
						// `html` is built from safe escape paths inside render_card();
						// rendered as-is to preserve markup like the status pill, icons, etc.
						echo $col['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
						<div class="rp-live-column-empty"<?php echo $col['count'] > 0 ? ' hidden' : ''; ?>>
							<?php esc_html_e( 'No orders here yet.', 'restropress' ); ?>
						</div>
					</div>
				</section>
			<?php endforeach; ?>
		</div>

		<!-- Browser-audio overlay: click anywhere to unlock the audio context on first visit. -->
		<div class="rp-live-sound-overlay" hidden>
			<div class="rp-live-sound-overlay-inner">
				<span class="dashicons dashicons-controls-volumeon" aria-hidden="true"></span>
				<?php esc_html_e( 'Click anywhere to enable sound notifications for new orders.', 'restropress' ); ?>
			</div>
		</div>

		<?php if ( $sound_url ) : ?>
			<audio id="rp-live-orders-audio" preload="auto" src="<?php echo esc_url( $sound_url ); ?>"></audio>
		<?php endif; ?>
	</div>
</div>
