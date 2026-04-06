<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This template is used to display the purchase summary with [rpress_receipt]
 */
global $rpress_receipt_args;
$payment = get_post( $rpress_receipt_args['id'] );
if ( empty( $payment ) ) : ?>
	<div class="rpress_errors rpress-alert rpress-alert-error">
		<?php esc_html_e( 'The specified receipt ID appears to be invalid', 'restropress' ); ?>
	</div>
	<?php
	return;
endif;

$meta           = rpress_get_payment_meta( $payment->ID );
$service_time   = rpress_get_payment_meta( $payment->ID, '_rpress_delivery_time' );
$service_date   = rpress_get_payment_meta( $payment->ID, '_rpress_delivery_date', true );
$cart           = rpress_get_payment_meta_cart_details( $payment->ID, true );
$discount       = rpress_get_discount_price_by_payment_id( $payment->ID );
$user           = rpress_get_payment_meta_user_info( $payment->ID );
$email          = rpress_get_payment_user_email( $payment->ID );
$payment_status = rpress_get_payment_status( $payment, true );
$order_status   = rpress_get_order_status( $payment->ID );
$order_note     = rpress_get_payment_meta( $payment->ID, '_rpress_order_note', true );
$prefix         = rpress_get_option( 'sequential_prefix' );
$postfix        = rpress_get_option( 'sequential_postfix' );
$payment_id     = rpress_get_payment_number( $payment->ID );
$service_type   = rpress_get_payment_meta( $payment->ID, '_rpress_delivery_type' );
$service_label  = rpress_service_label( $service_type );
$phone          = ! empty( $meta['phone'] ) ? $meta['phone'] : ( ! empty( $user['phone'] ) ? $user['phone'] : '' );
$firstname      = isset( $user['first_name'] ) ? $user['first_name'] : '';
$lastname       = isset( $user['last_name'] ) ? $user['last_name'] : '';
$address_info   = get_post_meta( $payment->ID, '_rpress_delivery_address', true );
$address        = !empty( $address_info['address'] ) ? $address_info['address'] . ', ' : '';
$address       .= ! empty( $address_info['flat'] ) ? $address_info['flat'] . ', ' : '';
$address       .= ! empty( $address_info['city'] ) ? $address_info['city'] . ', ' : '';
$address       .= ! empty( $address_info['postcode'] ) ? $address_info['postcode'] : '';

$service_label_text = ucfirst( (string) $service_label );
$payment_method     = rpress_get_gateway_checkout_label( rpress_get_payment_gateway( $payment->ID ) );
$order_status_text  = rpress_get_order_status_label( $order_status );
$order_date_value   = ! empty( $meta['date'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $meta['date'] ) ) : '';
$customer_name      = trim( $firstname . ' ' . $lastname );
$order_number       = rpress_get_option( 'enable_sequential' ) ? $payment_id : $prefix . $payment_id . $postfix;
$store_location     = rpress_get_option( 'store_address' );
$payment_key        = rpress_get_payment_key( $payment->ID );
$visible_items      = 0;
$order_status_key   = sanitize_key( $order_status );
$status_media_map   = array(
	'pending'    => array(
		'src'         => trailingslashit( RP_PLUGIN_URL ) . 'templates/images/status/pending.gif',
		'description' => __( 'Your order is waiting for confirmation.', 'restropress' ),
	),
	'accepted'   => array(
		'src'         => trailingslashit( RP_PLUGIN_URL ) . 'templates/images/status/accepted.gif',
		'description' => __( 'Your order has been accepted by the restaurant.', 'restropress' ),
	),
	'processing' => array(
		'src'         => trailingslashit( RP_PLUGIN_URL ) . 'templates/images/status/processing.gif',
		'description' => __( 'Your order is being prepared in the kitchen.', 'restropress' ),
	),
	'ready'      => array(
		'src'         => trailingslashit( RP_PLUGIN_URL ) . 'templates/images/status/ready.gif',
		'description' => __( 'Your order is ready for pickup or handoff.', 'restropress' ),
	),
	'transit'    => array(
		'src'         => trailingslashit( RP_PLUGIN_URL ) . 'templates/images/status/transit.gif',
		'description' => __( 'Your order is on the way.', 'restropress' ),
	),
	'completed'  => array(
		'src'         => trailingslashit( RP_PLUGIN_URL ) . 'templates/images/status/completed.gif',
		'description' => __( 'Your order is completed. Enjoy your meal!', 'restropress' ),
	),
	'cancelled'  => array(
		'src'         => trailingslashit( RP_PLUGIN_URL ) . 'templates/images/status/cancelled.gif',
		'description' => __( 'This order has been cancelled.', 'restropress' ),
	),
	'failed'     => array(
		'src'         => trailingslashit( RP_PLUGIN_URL ) . 'templates/images/status/failed.gif',
		'description' => __( 'There was an issue with this order.', 'restropress' ),
	),
);
$default_status_media = array(
	'src'         => trailingslashit( RP_PLUGIN_URL ) . 'templates/images/status/pending.gif',
	'description' => __( 'Your order status is being updated.', 'restropress' ),
);
$current_status_media = isset( $status_media_map[ $order_status_key ] ) ? $status_media_map[ $order_status_key ] : $default_status_media;
$current_status_src   = apply_filters(
	'rpress_receipt_status_media_src',
	$current_status_media['src'],
	$order_status_key,
	$payment->ID
);
$current_status_desc  = isset( $current_status_media['description'] ) ? $current_status_media['description'] : $default_status_media['description'];
$status_poll_nonce    = wp_create_nonce( 'rpress-receipt-status-' . $payment->ID );
$realtime_client      = class_exists( 'RPRESS_Realtime' ) ? RPRESS_Realtime::get_client_config_for_payment( $payment->ID ) : array( 'enabled' => false );

foreach ( $status_media_map as $status_key => $status_media ) {
	$status_media_map[ $status_key ]['src'] = apply_filters(
		'rpress_receipt_status_media_src',
		$status_media['src'],
		$status_key,
		$payment->ID
	);
}

if ( ! empty( $cart ) ) {
	foreach ( $cart as $cart_item_for_count ) {
		if ( apply_filters( 'rpress_user_can_view_receipt_item', true, $cart_item_for_count ) ) {
			$visible_items++;
		}
	}
}

do_action( 'rpress_before_payment_receipt', $payment, $rpress_receipt_args );
?>
<div class="rp-thankyou-page">
	<div class="rp-thankyou-hero rp-reveal rp-reveal-1">
		<div class="rp-thankyou-hero-main">
			<span class="rp-thankyou-check" aria-hidden="true">
				<svg viewBox="0 0 24 24" role="img" focusable="false" aria-hidden="true">
					<path d="M9.55 17.62 4.7 12.77l1.41-1.42 3.44 3.44 8.35-8.35 1.41 1.42z"></path>
				</svg>
			</span>
			<div>
				<p class="rp-thankyou-kicker"><?php esc_html_e( 'Order Confirmation', 'restropress' ); ?></p>
				<h2><?php esc_html_e( "We've received your order", 'restropress' ); ?></h2>
				<p class="rp-thankyou-message">
					<?php esc_html_e( 'A copy of your receipt has been sent to', 'restropress' ); ?>
					<strong><?php echo esc_html( $email ); ?></strong>
				</p>
			</div>
		</div>
		<div class="rp-thankyou-live-status">
			<div class="rp-thankyou-live-status-media">
				<img
					id="rp-live-status-gif"
					src="<?php echo esc_url( $current_status_src ); ?>"
					alt="<?php echo esc_attr( sprintf( __( 'Order status animation: %s', 'restropress' ), $order_status_text ) ); ?>"
				/>
			</div>
			<div class="rp-thankyou-live-status-content">
				<p class="rp-live-status-kicker"><?php esc_html_e( 'Live Order Status', 'restropress' ); ?></p>
				<h4 id="rp-live-order-status-panel"><?php echo esc_html( $order_status_text ); ?></h4>
				<p id="rp-live-order-status-desc"><?php echo esc_html( $current_status_desc ); ?></p>
				<p id="rp-live-status-updated"><?php esc_html_e( 'Updated just now', 'restropress' ); ?></p>
			</div>
			<div class="rp-thankyou-live-status-actions">
				<button type="button" class="rp-thankyou-notify-btn" id="rp-enable-order-alerts">
					<?php esc_html_e( 'Enable Browser Alerts', 'restropress' ); ?>
				</button>
			</div>
		</div>
		<div class="rp-thankyou-meta">
			<div class="rp-thankyou-meta-item">
				<span><?php esc_html_e( 'Order', 'restropress' ); ?></span>
				<strong>#<?php echo esc_html( $order_number ); ?></strong>
			</div>
			<div class="rp-thankyou-meta-item">
				<span><?php esc_html_e( 'Total', 'restropress' ); ?></span>
				<strong><?php echo esc_html( rpress_payment_amount( $payment->ID ) ); ?></strong>
			</div>
			<div class="rp-thankyou-meta-item">
				<span><?php esc_html_e( 'Status', 'restropress' ); ?></span>
				<strong id="rp-live-order-status"><?php echo esc_html( $order_status_text ); ?></strong>
			</div>
			<div class="rp-thankyou-meta-item">
				<span><?php esc_html_e( 'Items', 'restropress' ); ?></span>
				<strong><?php echo esc_html( (string) $visible_items ); ?></strong>
			</div>
		</div>
	</div>
	<div id="rp-order-details" class="rp-thankyou-details-grid rp-reveal rp-reveal-2">
		<div class="rp-thankyou-card rp-thankyou-details-card">
			<h3>
				<?php
				/* translators: %s: Service type name */
				echo esc_html( sprintf( __( '%s details', 'restropress' ), $service_label_text ) );
				?>
			</h3>
			<ul class="rp-thankyou-list">
				<li>
					<span><?php esc_html_e( 'Name', 'restropress' ); ?></span>
					<strong><?php echo esc_html( $customer_name ); ?></strong>
				</li>
				<li>
					<span><?php esc_html_e( 'Phone Number', 'restropress' ); ?></span>
					<strong><?php echo esc_html( $phone ); ?></strong>
				</li>
				<li>
					<span>
						<?php
						/* translators: %s: Service type name */
						echo esc_html( sprintf( __( '%s Date', 'restropress' ), $service_label_text ) );
						?>
					</span>
					<strong><?php echo esc_html( rpress_local_date( $service_date ) ); ?></strong>
				</li>
				<?php if ( ! empty( $service_time ) ) : ?>
					<li>
						<span>
							<?php
							/* translators: %s: Service type name */
							echo esc_html( sprintf( __( '%s Time', 'restropress' ), $service_label_text ) );
							?>
						</span>
						<strong><?php echo esc_html( $service_time ); ?></strong>
					</li>
				<?php endif; ?>
			</ul>
		</div>

		<div class="rp-thankyou-card rp-thankyou-details-card">
			<?php if ( filter_var( $rpress_receipt_args['date'], FILTER_VALIDATE_BOOLEAN ) ) : ?>
				<h3><?php esc_html_e( 'Order details', 'restropress' ); ?></h3>
				<ul class="rp-thankyou-list">
					<li>
						<span><?php esc_html_e( 'Order Status', 'restropress' ); ?></span>
						<strong id="rp-live-order-status-details"><?php echo esc_html( $order_status_text ); ?></strong>
					</li>
					<li>
						<span><?php esc_html_e( 'Order Date', 'restropress' ); ?></span>
						<strong><?php echo esc_html( $order_date_value ); ?></strong>
					</li>
				</ul>
			<?php endif; ?>

			<h3 class="rp-thankyou-subhead"><?php esc_html_e( 'Payment Details', 'restropress' ); ?></h3>
			<ul class="rp-thankyou-list">
				<li>
					<span><?php esc_html_e( 'Payment Method', 'restropress' ); ?></span>
					<strong><?php echo esc_html( $payment_method ); ?></strong>
				</li>
				<li>
					<span><?php esc_html_e( 'Payment Status', 'restropress' ); ?></span>
					<strong><?php echo esc_html( $payment_status ); ?></strong>
				</li>
			</ul>
		</div>

		<?php if ( 'delivery' === $service_type ) : ?>
			<div class="rp-thankyou-card rp-thankyou-card-wide">
				<h3><?php esc_html_e( 'Delivery Address', 'restropress' ); ?></h3>
				<p class="rp-thankyou-address">
					<?php echo esc_html( apply_filters( 'rpress_receipt_delivery_address', $address, $address_info ) ); ?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $store_location ) ) : ?>
			<div class="rp-thankyou-card rp-thankyou-card-wide">
				<h3><?php esc_html_e( 'Store Address', 'restropress' ); ?></h3>
				<p class="rp-thankyou-address"><?php echo esc_html( $store_location ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<?php do_action( 'rpress_after_order_details', $payment, $rpress_receipt_args ); ?>

	<div class="rp-thankyou-card rp-thankyou-summary rp-reveal rp-reveal-3">
		<div class="rp-thankyou-summary-header">
			<h3><?php esc_html_e( 'Order summary', 'restropress' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: Order total amount */
					esc_html__( 'Grand total: %s', 'restropress' ),
					esc_html( rpress_payment_amount( $payment->ID ) )
				);
				?>
			</p>
		</div>
		<div class="rp-thankyou-table-wrap">
			<table id="rp-order-summary" class="rp-thankyou-table" width="100%">
				<thead>
					<tr>
						<th class="rp-tb-left"><?php esc_html_e( 'Item', 'restropress' ); ?></th>
						<th class="rp-center"><?php esc_html_e( 'Quantity', 'restropress' ); ?></th>
						<th class="rp-tb-right"><?php esc_html_e( 'Amount', 'restropress' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				if ( $cart ) :
					foreach ( $cart as $item ) :
						if ( ! apply_filters( 'rpress_user_can_view_receipt_item', true, $item ) ) :
							continue;
						endif;

						if ( ! empty( $item['in_bundle'] ) ) :
							continue;
						endif;

						$special_instruction = isset( $item['instruction'] ) ? $item['instruction'] : '';
						$item_options        = array();

						if ( isset( $item['item_number']['options'] ) && is_array( $item['item_number']['options'] ) ) {
							$item_options = $item['item_number']['options'];
						}
						?>
						<tr>
							<td>
								<div class="rpress_purchase_receipt_product_name">
									<span class="rpress-main-item-name"><?php echo wp_kses_post( rpress_get_cart_item_name( $item ) ); ?></span>
									<?php
									foreach ( $item_options as $option ) {
										if ( empty( $option['quantity'] ) ) {
											continue;
										}

										$addon_id = ! empty( $option['addon_id'] ) ? $option['addon_id'] : '';
										if ( empty( $addon_id ) ) {
											continue;
										}

										if ( ! empty( $option['addon_item_name'] ) ) {
											echo '<br><small class="rpress-receipt-addon-item">' . wp_kses_post( $option['addon_item_name'] ) . '</small>';
										}
									}
									?>
									<?php if ( ! empty( $special_instruction ) ) : ?>
										<span><?php esc_html_e( 'Special Instructions', 'restropress' ); ?>:</span>
										<small><?php echo esc_html( $special_instruction ); ?></small>
									<?php endif; ?>
								</div>
							</td>
							<td class="rp-center">
								<?php echo wp_kses_post( $item['quantity'] ); ?><br>
								<?php
								foreach ( $item_options as $option ) {
									if ( empty( $option['quantity'] ) ) {
										continue;
									}

									$addon_id = ! empty( $option['addon_id'] ) ? $option['addon_id'] : '';
									if ( empty( $addon_id ) ) {
										continue;
									}

									$addon_item_quantity = isset( $option['quantity'] ) ? $option['quantity'] : 0;
									echo '<small>' . esc_html( $addon_item_quantity ) . '</small><br>';
								}

								do_action( 'rpress_payment_receipt_table', $payment, $item );
								?>
							</td>
							<td class="rp-tb-right">
								<?php
								echo esc_html( rpress_currency_filter( rpress_format_amount( $item['item_price'] ) ) ) . '<br>';
								foreach ( $item_options as $option ) {
									if ( empty( $option['quantity'] ) ) {
										continue;
									}

									$addon_id = ! empty( $option['addon_id'] ) ? $option['addon_id'] : '';
									if ( empty( $addon_id ) ) {
										continue;
									}

									$cart_instance     = new RPRESS_Cart();
									$addon_item_price  = isset( $option['price'] ) ? $option['price'] : 0;
									$addon_price_value = $cart_instance->get_addon_price( $addon_id, $item, $addon_item_price );
									echo esc_html( rpress_currency_filter( rpress_format_amount( $addon_price_value ) ) ) . '<br>';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
				<tfoot>
					<tr class="rpress_cart_footer_row rpress_cart_subtotal_row">
						<td colspan="2" class="rp-tb-right"><?php esc_html_e( 'Subtotal', 'restropress' ); ?>:</td>
						<td class="rp-tb-right rp-amount-right"><?php echo esc_html( rpress_payment_subtotal( $payment->ID ) ); ?></td>
					</tr>

					<?php
					$fees = rpress_get_payment_fees( $payment->ID, 'fee' );
					if ( $fees ) :
						foreach ( $fees as $fee ) :
							?>
							<tr class="rpress_cart_footer_row rpress_cart_delivery_row">
								<td colspan="2" class="rp-tb-right"><?php echo esc_html( $fee['label'] ); ?>:</td>
								<td class="rp-tb-right rp-amount-right"><?php echo esc_html( rpress_currency_filter( rpress_format_amount( $fee['amount'] ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if ( filter_var( $rpress_receipt_args['discount'], FILTER_VALIDATE_BOOLEAN ) && isset( $user['discount'] ) && 'none' !== $user['discount'] ) : ?>
						<tr class="rpress_cart_footer_row rpress_cart_discount_row">
							<td colspan="2" class="rp-tb-right"><?php esc_html_e( 'Coupon', 'restropress' ); ?>:</td>
							<td class="rp-tb-right rp-amount-right"><?php echo wp_kses_post( $discount ); ?></td>
						</tr>
					<?php endif; ?>

					<?php if ( rpress_use_taxes() ) : ?>
						<tr class="rpress_cart_footer_row kk rpress_cart_tax_row">
							<td colspan="2" class="rp-tb-right"><?php echo esc_html( rpress_get_tax_name() ); ?>:</td>
							<td class="rp-tb-right rp-amount-right"><?php echo esc_html( rpress_payment_tax( $payment->ID ) ); ?></td>
						</tr>
						<?php do_action( 'rpress_payment_receipt_after_tax_table', $payment, $rpress_receipt_args ); ?>
					<?php endif; ?>

					<?php if ( filter_var( $rpress_receipt_args['price'], FILTER_VALIDATE_BOOLEAN ) ) : ?>
						<tr class="rpress_cart_footer_row rpress_cart_total_row">
							<td colspan="2" class="rp-tb-right rp-bold"><?php esc_html_e( 'Total', 'restropress' ); ?>:</td>
							<td class="rp-tb-right rp-amount-right rp-bold"><?php echo esc_html( rpress_payment_amount( $payment->ID ) ); ?></td>
						</tr>
					<?php endif; ?>
				</tfoot>
			</table>
		</div>
		<?php do_action( 'rpress_payment_receipt_after_table', $payment, $rpress_receipt_args ); ?>
	</div>

	<div class="rp-thankyou-next rp-reveal rp-reveal-4">
		<div class="rp-thankyou-next-item">
			<h4><?php esc_html_e( 'Order Received', 'restropress' ); ?></h4>
			<p><?php esc_html_e( 'Your order has been captured successfully.', 'restropress' ); ?></p>
		</div>
		<div class="rp-thankyou-next-item">
			<h4><?php esc_html_e( 'Kitchen Preparation', 'restropress' ); ?></h4>
			<p><?php esc_html_e( 'We are preparing your items and updating status in real-time.', 'restropress' ); ?></p>
		</div>
		<div class="rp-thankyou-next-item">
			<h4><?php esc_html_e( 'Ready for Service', 'restropress' ); ?></h4>
			<p><?php esc_html_e( 'You will receive updates once your order is ready.', 'restropress' ); ?></p>
		</div>
	</div>
	<div class="rp-live-status-toast" id="rp-live-status-toast" aria-live="polite"></div>
</div>
<?php if ( ! empty( $realtime_client['enabled'] ) && ! empty( $realtime_client['script_url'] ) ) : ?>
<script src="<?php echo esc_url( $realtime_client['script_url'] ); ?>"></script>
<?php endif; ?>
<script>
	(function () {
		var receiptConfig = {
			ajaxUrl: <?php echo wp_json_encode( rpress_get_ajax_url() ); ?>,
			paymentId: <?php echo (int) $payment->ID; ?>,
			paymentKey: <?php echo wp_json_encode( $payment_key ); ?>,
			security: <?php echo wp_json_encode( $status_poll_nonce ); ?>,
			currentStatus: <?php echo wp_json_encode( $order_status_key ); ?>,
			currentStatusLabel: <?php echo wp_json_encode( wp_strip_all_tags( (string) $order_status_text ) ); ?>,
			orderNumber: <?php echo wp_json_encode( (string) $order_number ); ?>,
			pollInterval: 10000,
			trackerStorageKey: 'rpress_live_order_tracker_v1',
			trackerTtlMs: 21600000,
			statusMediaMap: <?php echo wp_json_encode( $status_media_map ); ?>,
			realtime: <?php echo wp_json_encode( $realtime_client ); ?>,
			strings: {
				updatedJustNow: <?php echo wp_json_encode( __( 'Updated just now', 'restropress' ) ); ?>,
				updatedAt: <?php echo wp_json_encode( __( 'Updated at', 'restropress' ) ); ?>,
				alertsEnabled: <?php echo wp_json_encode( __( 'Alerts Enabled', 'restropress' ) ); ?>,
				alertButton: <?php echo wp_json_encode( __( 'Enable Browser Alerts', 'restropress' ) ); ?>,
				alertDenied: <?php echo wp_json_encode( __( 'Browser alerts blocked for this site.', 'restropress' ) ); ?>,
				alertUnsupported: <?php echo wp_json_encode( __( 'Browser alerts are not supported.', 'restropress' ) ); ?>,
				realtimeConnected: <?php echo wp_json_encode( __( 'Live status connected.', 'restropress' ) ); ?>,
				realtimeConnecting: <?php echo wp_json_encode( __( 'Connecting to live status updates...', 'restropress' ) ); ?>,
				realtimeUnavailable: <?php echo wp_json_encode( __( 'Live updates are unavailable, showing latest status.', 'restropress' ) ); ?>,
				alertsEnabledForOrder: <?php echo wp_json_encode( __( 'Browser alerts enabled for Order', 'restropress' ) ); ?>,
				statusAnimationPrefix: <?php echo wp_json_encode( __( 'Order status animation', 'restropress' ) ); ?>,
				orderPrefix: <?php echo wp_json_encode( __( 'Order', 'restropress' ) ); ?>,
				isNow: <?php echo wp_json_encode( __( 'is now', 'restropress' ) ); ?>,
				notificationTitlePrefix: <?php echo wp_json_encode( __( 'Order update', 'restropress' ) ); ?>,
				notificationBodyPrefix: <?php echo wp_json_encode( __( 'Status', 'restropress' ) ); ?>
			}
		};

		var statusPrimary = document.getElementById('rp-live-order-status');
		var statusDetails = document.getElementById('rp-live-order-status-details');
		var statusPanel = document.getElementById('rp-live-order-status-panel');
		var statusDescription = document.getElementById('rp-live-order-status-desc');
		var statusUpdated = document.getElementById('rp-live-status-updated');
		var statusGif = document.getElementById('rp-live-status-gif');
		var toast = document.getElementById('rp-live-status-toast');
		var notifyButton = document.getElementById('rp-enable-order-alerts');

		if (!statusPrimary || !statusPanel) {
			return;
		}

		var currentStatus = receiptConfig.currentStatus || '';
		var statusMediaMap = receiptConfig.statusMediaMap || {};
		var realtimeConfig = receiptConfig.realtime || {};
		var TERMINAL_STATUSES = {
			completed: true,
			cancelled: true,
			failed: true
		};
		var pusherClient = null;
		var pollTimer = null;
		var pollRequestInFlight = false;

		function getStatusMedia(statusKey) {
			if (statusMediaMap && statusMediaMap[statusKey]) {
				return statusMediaMap[statusKey];
			}

			return {
				src: '',
				description: ''
			};
		}

		function formatUpdatedText(unixTime) {
			if (!unixTime || isNaN(unixTime)) {
				return receiptConfig.strings.updatedJustNow;
			}

			var d = new Date(unixTime * 1000);
			return receiptConfig.strings.updatedAt + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		}

		function setStatusText(statusLabel, statusKey, updatedAtUnix, statusColor) {
			var media = getStatusMedia(statusKey);

			statusPrimary.textContent = statusLabel;
			statusPanel.textContent = statusLabel;
			if (statusDetails) {
				statusDetails.textContent = statusLabel;
			}

			[statusPrimary, statusDetails, statusPanel].forEach(function (el) {
				if (!el) {
					return;
				}

				if (statusColor) {
					el.style.color = statusColor;
				} else {
					el.style.removeProperty('color');
				}
			});

			if (statusDescription) {
				statusDescription.textContent = media.description || '';
			}

			if (statusUpdated) {
				statusUpdated.textContent = formatUpdatedText(updatedAtUnix);
			}

			if (statusGif && media.src) {
				statusGif.src = media.src;
				statusGif.alt = receiptConfig.strings.statusAnimationPrefix + ': ' + statusLabel;
			}
		}

		function showToast(message) {
			if (!toast || !message) {
				return;
			}

			toast.textContent = message;
			toast.classList.add('is-visible');
			window.setTimeout(function () {
				toast.classList.remove('is-visible');
			}, 3500);
		}

		function maybeSendBrowserNotification(orderNumber, statusLabel) {
			if (!('Notification' in window)) {
				return;
			}

			if (Notification.permission !== 'granted') {
				return;
			}

			var title = receiptConfig.strings.notificationTitlePrefix + ' #' + orderNumber;
			var body = receiptConfig.strings.notificationBodyPrefix + ': ' + statusLabel;

			if ('serviceWorker' in navigator) {
				navigator.serviceWorker.getRegistration().then(function (registration) {
					if (registration && typeof registration.showNotification === 'function') {
						registration.showNotification(title, {
							body: body,
							tag: 'rpress-order-' + orderNumber
						});
						return;
					}

					try {
						new Notification(title, { body: body });
					} catch (e) {
						// Ignore browser-level notification errors.
					}
				}).catch(function () {
					try {
						new Notification(title, { body: body });
					} catch (e) {
						// Ignore browser-level notification errors.
					}
				});
				return;
			}

			try {
				new Notification(title, { body: body });
			} catch (e) {
				// Ignore browser-level notification errors.
			}
		}

		function saveOrderTracker(statusKey, statusLabel, updatedAtUnix) {
			if (!window.localStorage || !receiptConfig.paymentId || !receiptConfig.paymentKey) {
				return;
			}

			var tracker = {
				payment_id: receiptConfig.paymentId,
				payment_key: receiptConfig.paymentKey,
				security: receiptConfig.security,
				order_number: receiptConfig.orderNumber,
				status: statusKey || '',
				status_label: statusLabel || '',
				updated_at_unix: updatedAtUnix || 0,
				realtime: realtimeConfig || {},
				expires_at: Date.now() + receiptConfig.trackerTtlMs
			};

			try {
				window.localStorage.setItem(receiptConfig.trackerStorageKey, JSON.stringify(tracker));
			} catch (e) {
				// Ignore storage failures.
			}
		}

		function clearOrderTracker() {
			if (!window.localStorage) {
				return;
			}

			try {
				window.localStorage.removeItem(receiptConfig.trackerStorageKey);
			} catch (e) {
				// Ignore storage failures.
			}
		}

		function stopLiveWatchers() {
			if (pusherClient && typeof pusherClient.disconnect === 'function') {
				pusherClient.disconnect();
				pusherClient = null;
			}

			if (pollTimer) {
				window.clearInterval(pollTimer);
				pollTimer = null;
			}
		}

		function handleIncomingStatus(statusPayload) {
			if (!statusPayload) {
				return;
			}

			var incomingStatus = statusPayload.status || '';
			var incomingLabel = statusPayload.status_label || incomingStatus;
			var incomingOrderNumber = statusPayload.order_number || receiptConfig.orderNumber;

			if (!incomingStatus) {
				return;
			}

			setStatusText(
				incomingLabel,
				incomingStatus,
				statusPayload.updated_at_unix || 0,
				statusPayload.status_color || ''
			);

			if (incomingStatus !== currentStatus) {
				currentStatus = incomingStatus;
				showToast(
					receiptConfig.strings.orderPrefix + ' #' + incomingOrderNumber + ' ' +
					receiptConfig.strings.isNow + ' ' + incomingLabel + '.'
				);
				maybeSendBrowserNotification(incomingOrderNumber, incomingLabel);
			}

			if (TERMINAL_STATUSES[incomingStatus]) {
				clearOrderTracker();
				stopLiveWatchers();
				return;
			}

			saveOrderTracker(incomingStatus, incomingLabel, statusPayload.updated_at_unix || 0);
		}

		function fetchOrderStatusOnce() {
			if (pollRequestInFlight) {
				return;
			}

			var body = new URLSearchParams();
			body.set('action', 'rpress_receipt_order_status');
			body.set('payment_id', String(receiptConfig.paymentId));
			body.set('payment_key', receiptConfig.paymentKey);
			body.set('security', receiptConfig.security);

			pollRequestInFlight = true;
			fetch(receiptConfig.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (result) {
					if (!result || !result.success || !result.data) {
						return;
					}

					handleIncomingStatus(result.data);
				})
				.catch(function () {
					// Skip transient sync failures.
				})
				.then(function () {
					pollRequestInFlight = false;
				});
		}

		function startFallbackPolling() {
			if (pollTimer) {
				return;
			}

			fetchOrderStatusOnce();
			pollTimer = window.setInterval(fetchOrderStatusOnce, receiptConfig.pollInterval);
		}

		function connectRealtimeUpdates() {
			if (!realtimeConfig || !realtimeConfig.enabled) {
				return false;
			}

			if (typeof window.Pusher === 'undefined' || !realtimeConfig.key || !realtimeConfig.channel || !realtimeConfig.event) {
				showToast(receiptConfig.strings.realtimeUnavailable);
				return false;
			}

			var pusherOptions = {
				forceTLS: !!realtimeConfig.forceTLS
			};

			if (realtimeConfig.cluster) {
				pusherOptions.cluster = realtimeConfig.cluster;
			}

			if (realtimeConfig.wsHost) {
				pusherOptions.wsHost = realtimeConfig.wsHost;
			}

			if (realtimeConfig.wsPort) {
				pusherOptions.wsPort = parseInt(realtimeConfig.wsPort, 10);
			}

			if (realtimeConfig.wssPort) {
				pusherOptions.wssPort = parseInt(realtimeConfig.wssPort, 10);
			}

			showToast(receiptConfig.strings.realtimeConnecting);

			try {
				pusherClient = new window.Pusher(realtimeConfig.key, pusherOptions);
			} catch (e) {
				showToast(receiptConfig.strings.realtimeUnavailable);
				return false;
			}

			var channel = pusherClient.subscribe(realtimeConfig.channel);

			channel.bind(realtimeConfig.event, function (payload) {
				handleIncomingStatus(payload || {});
			});

			channel.bind('pusher:subscription_succeeded', function () {
				showToast(receiptConfig.strings.realtimeConnected);
			});

			channel.bind('pusher:subscription_error', function () {
				showToast(receiptConfig.strings.realtimeUnavailable);
				startFallbackPolling();
			});

			if (pusherClient.connection && typeof pusherClient.connection.bind === 'function') {
				pusherClient.connection.bind('error', function () {
					startFallbackPolling();
				});
			}

			return true;
		}

		if (notifyButton) {
			if (!('Notification' in window)) {
				notifyButton.textContent = receiptConfig.strings.alertUnsupported;
				notifyButton.disabled = true;
			} else if (Notification.permission === 'granted') {
				notifyButton.textContent = receiptConfig.strings.alertsEnabled;
				notifyButton.disabled = true;
			} else if (Notification.permission === 'denied') {
				notifyButton.textContent = receiptConfig.strings.alertDenied;
				notifyButton.disabled = true;
			} else {
				notifyButton.addEventListener('click', function () {
					Notification.requestPermission().then(function (permission) {
						if (permission === 'granted') {
							notifyButton.textContent = receiptConfig.strings.alertsEnabled;
							notifyButton.disabled = true;
							showToast(receiptConfig.strings.alertsEnabledForOrder + ' #' + receiptConfig.orderNumber + '.');
							maybeSendBrowserNotification(receiptConfig.orderNumber, receiptConfig.currentStatusLabel);
						} else if (permission === 'denied') {
							notifyButton.textContent = receiptConfig.strings.alertDenied;
							notifyButton.disabled = true;
						}
					});
				});
			}
		}

		setStatusText(receiptConfig.currentStatusLabel, receiptConfig.currentStatus, 0, '');
		if (TERMINAL_STATUSES[receiptConfig.currentStatus]) {
			clearOrderTracker();
			return;
		}

		saveOrderTracker(receiptConfig.currentStatus, receiptConfig.currentStatusLabel, 0);
		var realtimeConnected = connectRealtimeUpdates();
		if (realtimeConnected) {
			window.setTimeout(fetchOrderStatusOnce, 1200);
		} else {
			startFallbackPolling();
		}

		window.addEventListener('beforeunload', function () {
			stopLiveWatchers();
		});
	})();
</script>
<?php do_action( 'rpress_after_payment_receipt', $payment, $rpress_receipt_args ); ?>
