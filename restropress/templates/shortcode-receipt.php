<?php
/**
 * Order confirmation → live order tracking template ([rpress_receipt]).
 *
 * Rebuilt for the 3.4 tracking redesign: a live-status hero with a 5-step
 * progress tracker, plus two consolidated cards (Your order + Delivery &
 * payment). One order status drives the hero, tracker, ETA and payment badge.
 * Live updates reuse the rpress_receipt_order_status AJAX endpoint.
 *
 * @package RPRESS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
$order_status    = rpress_get_order_status( $payment->ID );
$prefix          = rpress_get_option( 'sequential_prefix' );
$postfix         = rpress_get_option( 'sequential_postfix' );
$payment_id      = rpress_get_payment_number( $payment->ID );
$service_type    = rpress_get_payment_meta( $payment->ID, '_rpress_delivery_type' );
$is_delivery     = ( 'delivery' === $service_type );
$phone           = ! empty( $meta['phone'] ) ? $meta['phone'] : ( ! empty( $user['phone'] ) ? $user['phone'] : '' );
$firstname       = isset( $user['first_name'] ) ? $user['first_name'] : '';
$lastname        = isset( $user['last_name'] ) ? $user['last_name'] : '';
$address_info    = get_post_meta( $payment->ID, '_rpress_delivery_address', true );
$address         = ! empty( $address_info['address'] ) ? $address_info['address'] . ', ' : '';
$address        .= ! empty( $address_info['flat'] ) ? $address_info['flat'] . ', ' : '';
$address        .= ! empty( $address_info['city'] ) ? $address_info['city'] . ', ' : '';
$address        .= ! empty( $address_info['postcode'] ) ? $address_info['postcode'] : '';
$address         = trim( $address, ', ' );

$payment_method  = rpress_get_gateway_checkout_label( rpress_get_payment_gateway( $payment->ID ) );
$gateway         = rpress_get_payment_gateway( $payment->ID );
$customer_name   = trim( $firstname . ' ' . $lastname );
$order_number    = rpress_get_option( 'enable_sequential' ) ? $payment_id : $prefix . $payment_id . $postfix;
$store_name      = get_bloginfo( 'name' );
$store_location  = rpress_get_option( 'store_address' );
$store_phone     = rpress_get_option( 'store_phone' );
$payment_key     = rpress_get_payment_key( $payment->ID );
$order_status_key = sanitize_key( $order_status );
$status_poll_nonce = wp_create_nonce( 'rpress-receipt-status-' . $payment->ID );

$order_total     = rpress_payment_amount( $payment->ID );
$order_subtotal  = rpress_payment_subtotal( $payment->ID );
$support_email   = sanitize_email( (string) get_option( 'admin_email' ) );

// Requested time label ("Today · DD/MM/YYYY · HH:MM").
$service_date_label = ! empty( $service_date ) ? rpress_local_date( $service_date ) : '';
$requested_time     = trim( $service_date_label . ( ! empty( $service_time ) ? ' · ' . $service_time : '' ), ' ·' );

// --- Status model ------------------------------------------------------------
// All sequencing/phase/step logic comes from the canonical model in
// includes/payments/functions.php so admin + tracker never drift apart.
$service_type_key = $is_delivery ? 'delivery' : 'pickup';

// Terminal-bad payment states override the fulfilment status for display: a
// refunded/failed order shows "cancelled" instead of a stale happy tracker.
if ( in_array( $payment->post_status, array( 'refunded', 'cancelled', 'failed', 'abandoned', 'revoked' ), true ) ) {
	$order_status_key = 'cancelled';
}

$status_model  = function_exists( 'rpress_order_status_model' ) ? rpress_order_status_model() : array();
$tracker_steps = function_exists( 'rpress_order_tracker_steps' ) ? rpress_order_tracker_steps( $service_type_key ) : array();
$step_names    = wp_list_pluck( $tracker_steps, 'label' );

$phase_map = array();
$step_map  = array();
foreach ( $status_model as $skey => $sinfo ) {
	$phase_map[ $skey ] = $sinfo['phase'];
	$step_map[ $skey ]  = rpress_order_status_step_index( $skey, $service_type_key );
}

$phase        = isset( $phase_map[ $order_status_key ] ) ? $phase_map[ $order_status_key ] : 'pending';
$current_step = isset( $step_map[ $order_status_key ] ) ? $step_map[ $order_status_key ] : 0;

// Headline + message per phase (service-aware wording).
$phase_copy   = array(
	'pending'   => array( __( 'Waiting for confirmation', 'restropress' ), sprintf( /* translators: %s store name */ __( "We've sent your order to %s — it usually takes a minute or two to confirm.", 'restropress' ), $store_name ) ),
	'accepted'  => array( __( 'Order confirmed', 'restropress' ), sprintf( /* translators: %s store name */ __( '%s has accepted your order and will start preparing it shortly.', 'restropress' ), $store_name ) ),
	'preparing' => array( __( 'In the kitchen', 'restropress' ), __( 'Your food is being prepared right now.', 'restropress' ) ),
	'ready'     => $is_delivery
		? array( __( 'Ready to go', 'restropress' ), __( 'Your order is packed and waiting for a rider to pick it up.', 'restropress' ) )
		: array( __( 'Ready for pickup', 'restropress' ), __( 'Your order is ready — come collect it when you can.', 'restropress' ) ),
	'transit'   => array( __( 'Out for delivery', 'restropress' ), __( 'Your rider has picked up the order and is on the way.', 'restropress' ) ),
	'delivered' => array( $is_delivery ? __( 'Delivered', 'restropress' ) : __( 'Picked up', 'restropress' ), $is_delivery ? __( 'Your order has arrived. Enjoy your meal!', 'restropress' ) : __( 'Order collected. Enjoy your meal!', 'restropress' ) ),
	'cancelled' => array( __( 'Order cancelled', 'restropress' ), __( 'This order was cancelled.', 'restropress' ) ),
);
$headline = $phase_copy[ $phase ][0];
$message  = $phase_copy[ $phase ][1];

// ETA: show the requested service time until the order is delivered/cancelled.
$eta_hidden = in_array( $phase, array( 'delivered', 'cancelled' ), true ) || empty( $service_time );
$eta_value  = 'transit' === $phase ? __( 'Any minute now', 'restropress' ) : $service_time;

// Payment badge/note. Trim a leading "Pay by/via/with" from the gateway label
// so notes read "paid by cash" rather than "paid by Pay by cash".
$method_noun = trim( preg_replace( '/^pay\s+(by|via|with|using)\s+/i', '', (string) $payment_method ) );
if ( '' === $method_noun ) {
	$method_noun = $payment_method;
}
$is_paid   = in_array( $payment->post_status, array( 'publish', 'complete' ), true ) || 'delivered' === $phase;
$pay_label = $is_paid ? __( 'Paid', 'restropress' ) : __( 'Pay on delivery', 'restropress' );
$pay_note  = $is_paid
	? sprintf( /* translators: 1: amount 2: method */ __( '%1$s paid by %2$s', 'restropress' ), $order_total, $method_noun )
	: sprintf( /* translators: 1: amount 2: method */ __( 'Pay %1$s by %2$s when your order arrives', 'restropress' ), $order_total, $method_noun );

// Inline status icons (Lucide-style). Shared between PHP render and JS updates.
$phase_icons = array(
	'pending'   => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
	'accepted'  => '<path d="M20 6 9 17l-5-5"></path>',
	'preparing' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path>',
	'ready'     => '<path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path>',
	'transit'   => '<path d="M5 18H3c-.6 0-1-.4-1-1V7c0-.6.4-1 1-1h10c.6 0 1 .4 1 1v11"></path><path d="M14 9h4l4 4v4c0 .6-.4 1-1 1h-2"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle>',
	'delivered' => '<path d="M20 6 9 17l-5-5"></path>',
	'cancelled' => '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>',
);
$hero_icon_svg = '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $phase_icons[ $phase ] . '</svg>';

// Visible item count.
$visible_items = 0;
if ( ! empty( $cart ) ) {
	foreach ( $cart as $ci ) {
		if ( apply_filters( 'rpress_user_can_view_receipt_item', true, $ci ) && empty( $ci['in_bundle'] ) ) {
			$visible_items++;
		}
	}
}

// Small SVG helper for the delivery/payment icon rows.
$svg_pin   = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>';
$svg_clock = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>';
$svg_card  = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2.5"></circle><path d="M6 12h.01M18 12h.01"></path></svg>';
$svg_store = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>';

do_action( 'rpress_before_payment_receipt', $payment, $rpress_receipt_args );
?>
<div class="rp-track rp-track-v1<?php echo ( 'cancelled' === $phase ) ? ' is-cancelled' : ''; ?>" data-phase="<?php echo esc_attr( $phase ); ?>">
	<div class="rp-track-inner">

		<!-- Header -->
		<div class="rp-track-topbar">
			<div class="rp-track-brand">
				<span class="rp-track-order"><?php /* translators: %s order number */ echo esc_html( sprintf( __( 'Order #%s', 'restropress' ), $order_number ) ); ?></span>
			</div>
			<a href="#" class="rp-track-print" onclick="window.print();return false;">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
				<?php esc_html_e( 'Print receipt', 'restropress' ); ?>
			</a>
		</div>

		<!-- Live status hero -->
		<div class="rp-track-hero" data-phase="<?php echo esc_attr( $phase ); ?>">
			<div class="rp-track-hero-top">
				<span class="rp-track-hero-icon" id="rp-track-icon"><?php echo $hero_icon_svg; // phpcs:ignore ?></span>
				<div class="rp-track-hero-body">
					<div class="rp-track-eyebrow"><?php esc_html_e( 'Live order status', 'restropress' ); ?></div>
					<div class="rp-track-headline" id="rp-track-headline"><?php echo esc_html( $headline ); ?></div>
					<div class="rp-track-msg"><span id="rp-track-msg"><?php echo esc_html( $message ); ?></span> <span class="rp-track-updated" id="rp-track-updated">· <?php esc_html_e( 'Updated just now', 'restropress' ); ?></span></div>
				</div>
				<div class="rp-track-hero-side">
					<div class="rp-track-eta" id="rp-track-eta"<?php echo $eta_hidden ? ' hidden' : ''; ?>>
						<div class="rp-track-eta-label"><?php echo esc_html( $is_delivery ? __( 'Estimated delivery', 'restropress' ) : __( 'Ready by', 'restropress' ) ); ?></div>
						<div class="rp-track-eta-time" id="rp-track-eta-time"><?php echo esc_html( $eta_value ); ?></div>
					</div>
					<button type="button" class="rp-track-alerts is-on" id="rp-track-alerts" aria-pressed="true">
						<span class="rp-track-alerts-dot"></span>
						<span class="rp-track-alerts-label"><?php esc_html_e( 'Live updates on', 'restropress' ); ?></span>
					</button>
				</div>
			</div>

			<!-- Progress tracker -->
			<div class="rp-track-steps" id="rp-track-steps"<?php echo ( 'cancelled' === $phase ) ? ' hidden' : ''; ?>>
				<?php
				$last_index = count( $step_names ) - 1;
				foreach ( $step_names as $i => $name ) :
					$done     = ( $current_step > $i ) || ( 'delivered' === $phase );
					$active   = ( $current_step === $i ) && ( 'delivered' !== $phase );
					$state    = $done ? 'is-done' : ( $active ? 'is-active' : 'is-upcoming' );
					$mark     = $done ? '&#10003;' : ( $i + 1 );
					$l_fill   = ( 0 !== $i ) && ( $done || $active );
					$r_fill   = ( $last_index !== $i ) && $done;
					$classes  = 'rp-track-step ' . $state;
					$classes .= ( 0 === $i ) ? ' rp-track-first' : '';
					$classes .= ( $last_index === $i ) ? ' rp-track-last' : '';
					$classes .= $l_fill ? ' rp-lfill' : '';
					$classes .= $r_fill ? ' rp-rfill' : '';
					?>
					<div class="<?php echo esc_attr( $classes ); ?>">
						<span class="rp-track-bar rp-track-bar-left"></span>
						<span class="rp-track-bar rp-track-bar-right"></span>
						<span class="rp-track-dot"><?php echo wp_kses_post( $done ? $mark : ( $active ? ( $i + 1 ) : ( $i + 1 ) ) ); ?></span>
						<span class="rp-track-step-label"><?php echo esc_html( $name ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Cancelled banner -->
			<div class="rp-track-cancel" id="rp-track-cancel"<?php echo ( 'cancelled' === $phase ) ? '' : ' hidden'; ?>>
				<span><?php esc_html_e( 'This order was cancelled. Any online payment will be refunded to your original payment method.', 'restropress' ); ?></span>
				<a class="rp-track-btn" href="<?php echo esc_url( home_url( '/order-online' ) ); ?>"><?php esc_html_e( 'Order again', 'restropress' ); ?></a>
			</div>
		</div>

		<!-- Two cards -->
		<div class="rp-track-grid">

			<!-- Your order -->
			<div class="rp-track-card rp-track-order">
				<button type="button" class="rp-track-order-head" id="rp-track-receipt-toggle" aria-expanded="true">
					<span class="rp-track-card-title"><?php esc_html_e( 'Your order', 'restropress' ); ?> <span class="rp-track-muted">· <?php echo esc_html( sprintf( _n( '%d item', '%d items', $visible_items, 'restropress' ), $visible_items ) ); ?></span></span>
					<span class="rp-track-receipt-to rp-track-receipt-to--head"><?php echo esc_html( sprintf( /* translators: %s email */ __( 'Receipt sent to %s', 'restropress' ), $email ) ); ?></span>
					<span class="rp-track-order-total"><?php echo esc_html( $order_total ); ?></span>
					<svg class="rp-track-chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
				</button>
				<div class="rp-track-order-body">
					<div class="rp-track-items">
						<?php
						if ( $cart ) :
							foreach ( $cart as $item ) :
								if ( ! apply_filters( 'rpress_user_can_view_receipt_item', true, $item ) || ! empty( $item['in_bundle'] ) ) {
									continue;
								}
								$item_options = ( isset( $item['item_number']['options'] ) && is_array( $item['item_number']['options'] ) ) ? $item['item_number']['options'] : array();
								$mods         = array();
								foreach ( $item_options as $option ) {
									if ( ! empty( $option['quantity'] ) && ! empty( $option['addon_id'] ) && ! empty( $option['addon_item_name'] ) ) {
										$mods[] = $option['addon_item_name'];
									}
								}
								$instruction = ! empty( $item['instruction'] ) ? $item['instruction'] : '';
								?>
								<div class="rp-track-item">
									<div class="rp-track-item-line">
										<span><?php echo wp_kses_post( rpress_get_cart_item_name( $item ) ); ?> <span class="rp-track-muted">&times; <?php echo esc_html( $item['quantity'] ); ?></span></span>
										<span><?php echo esc_html( rpress_currency_filter( rpress_format_amount( $item['item_price'] ) ) ); ?></span>
									</div>
									<?php if ( ! empty( $mods ) ) : ?>
										<div class="rp-track-item-mods"><?php echo esc_html( implode( ' · ', $mods ) ); ?></div>
									<?php endif; ?>
									<?php if ( ! empty( $instruction ) ) : ?>
										<div class="rp-track-item-note">&ldquo;<?php echo esc_html( $instruction ); ?>&rdquo;</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<div class="rp-track-totals">
						<div class="rp-track-total-row"><span><?php esc_html_e( 'Subtotal', 'restropress' ); ?></span><span><?php echo esc_html( $order_subtotal ); ?></span></div>
						<?php
						$fees = rpress_get_payment_fees( $payment->ID, 'fee' );
						if ( $fees ) :
							foreach ( $fees as $fee ) :
								?>
								<div class="rp-track-total-row"><span><?php echo esc_html( $fee['label'] ); ?></span><span><?php echo esc_html( rpress_currency_filter( rpress_format_amount( $fee['amount'] ) ) ); ?></span></div>
							<?php endforeach; ?>
						<?php endif; ?>
						<?php if ( filter_var( $rpress_receipt_args['discount'], FILTER_VALIDATE_BOOLEAN ) && isset( $user['discount'] ) && 'none' !== $user['discount'] ) : ?>
							<div class="rp-track-total-row"><span><?php esc_html_e( 'Coupon', 'restropress' ); ?></span><span><?php echo wp_kses_post( $discount ); ?></span></div>
						<?php endif; ?>
						<?php if ( rpress_use_taxes() ) : ?>
							<div class="rp-track-total-row"><span><?php echo esc_html( rpress_get_tax_name() ); ?></span><span><?php echo esc_html( rpress_payment_tax( $payment->ID ) ); ?></span></div>
						<?php endif; ?>
						<div class="rp-track-grand">
							<span><?php esc_html_e( 'Total', 'restropress' ); ?></span>
							<span class="rp-track-grand-amount"><?php echo esc_html( $order_total ); ?></span>
						</div>
					</div>
					<div class="rp-track-receipt-to rp-track-receipt-to--foot"><?php echo esc_html( sprintf( /* translators: %s email */ __( 'Receipt sent to %s', 'restropress' ), $email ) ); ?></div>
				</div>
			</div>

			<!-- Delivery & payment -->
			<div class="rp-track-card rp-track-dp">
				<div class="rp-track-card-title rp-track-dp-title"><?php echo esc_html( ( $is_delivery ? __( 'Delivery', 'restropress' ) : __( 'Pickup', 'restropress' ) ) . ' ' . __( '& payment', 'restropress' ) ); ?></div>

				<?php if ( $is_delivery && ! empty( $address ) ) : ?>
					<div class="rp-track-dp-row">
						<span class="rp-track-dp-icon"><?php echo $svg_pin; // phpcs:ignore ?></span>
						<div class="rp-track-dp-text">
							<div class="rp-track-dp-label"><?php esc_html_e( 'Delivering to', 'restropress' ); ?></div>
							<div class="rp-track-dp-detail"><?php echo esc_html( trim( $customer_name . ( $phone ? ' · ' . $phone : '' ) ) ); ?><br><?php echo esc_html( $address ); ?></div>
						</div>
					</div>
				<?php else : ?>
					<div class="rp-track-dp-row">
						<span class="rp-track-dp-icon"><?php echo $svg_pin; // phpcs:ignore ?></span>
						<div class="rp-track-dp-text">
							<div class="rp-track-dp-label"><?php esc_html_e( 'Pickup by', 'restropress' ); ?></div>
							<div class="rp-track-dp-detail"><?php echo esc_html( trim( $customer_name . ( $phone ? ' · ' . $phone : '' ) ) ); ?></div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $requested_time ) ) : ?>
					<div class="rp-track-dp-row">
						<span class="rp-track-dp-icon"><?php echo $svg_clock; // phpcs:ignore ?></span>
						<div class="rp-track-dp-text">
							<div class="rp-track-dp-label"><?php esc_html_e( 'Requested time', 'restropress' ); ?></div>
							<div class="rp-track-dp-detail"><?php echo esc_html( $requested_time ); ?></div>
						</div>
					</div>
				<?php endif; ?>

				<div class="rp-track-dp-row">
					<span class="rp-track-dp-icon"><?php echo $svg_card; // phpcs:ignore ?></span>
					<div class="rp-track-dp-text">
						<div class="rp-track-dp-payhead">
							<span class="rp-track-dp-label"><?php esc_html_e( 'Payment', 'restropress' ); ?></span>
							<span class="rp-track-pay-badge<?php echo $is_paid ? ' is-paid' : ''; ?>" id="rp-track-pay-badge"><?php echo esc_html( $pay_label ); ?></span>
						</div>
						<div class="rp-track-dp-detail" id="rp-track-pay-note"><?php echo esc_html( $pay_note ); ?></div>
					</div>
				</div>

				<?php if ( ! empty( $store_name ) || ! empty( $store_location ) ) : ?>
					<div class="rp-track-dp-row rp-track-dp-store">
						<span class="rp-track-dp-icon rp-track-dp-icon-neutral"><?php echo $svg_store; // phpcs:ignore ?></span>
						<div class="rp-track-dp-text">
							<div class="rp-track-dp-label"><?php echo esc_html( $store_name ); ?></div>
							<?php if ( ! empty( $store_location ) ) : ?>
								<div class="rp-track-dp-detail"><?php echo esc_html( $store_location ); ?></div>
							<?php endif; ?>
							<div class="rp-track-dp-links">
								<?php if ( ! empty( $store_phone ) ) : ?>
									<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $store_phone ) ); ?>"><?php esc_html_e( 'Call store', 'restropress' ); ?></a>
								<?php endif; ?>
								<?php if ( ! empty( $support_email ) ) : ?>
									<a href="mailto:<?php echo esc_attr( antispambot( $support_email ) ); ?>?subject=<?php echo rawurlencode( sprintf( __( 'Help with order #%s', 'restropress' ), $order_number ) ); ?>"><?php esc_html_e( 'Get help with this order', 'restropress' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php do_action( 'rpress_after_payment_receipt', $payment, $rpress_receipt_args ); ?>
	</div>
</div>
<script>
(function () {
	var CFG = {
		ajaxUrl: <?php echo wp_json_encode( rpress_get_ajax_url() ); ?>,
		paymentId: <?php echo (int) $payment->ID; ?>,
		paymentKey: <?php echo wp_json_encode( $payment_key ); ?>,
		security: <?php echo wp_json_encode( $status_poll_nonce ); ?>,
		status: <?php echo wp_json_encode( $order_status_key ); ?>,
		isDelivery: <?php echo $is_delivery ? 'true' : 'false'; ?>,
		orderNumber: <?php echo wp_json_encode( (string) $order_number ); ?>,
		pollInterval: 8000,
		icons: <?php echo wp_json_encode( $phase_icons ); ?>,
		phaseMap: <?php echo wp_json_encode( $phase_map ); ?>,
		stepMap: <?php echo wp_json_encode( $step_map ); ?>,
		stepNames: <?php echo wp_json_encode( array_values( $step_names ) ); ?>,
		copy: <?php echo wp_json_encode( $phase_copy ); ?>,
		terminal: { completed: 1, cancelled: 1, failed: 1, refunded: 1 },
		i18n: {
			updated: <?php echo wp_json_encode( __( 'Updated', 'restropress' ) ); ?>,
			justNow: <?php echo wp_json_encode( __( 'just now', 'restropress' ) ); ?>,
			liveOn: <?php echo wp_json_encode( __( 'Live updates on', 'restropress' ) ); ?>,
			liveOff: <?php echo wp_json_encode( __( 'Enable live updates', 'restropress' ) ); ?>,
			anyMinute: <?php echo wp_json_encode( __( 'Any minute now', 'restropress' ) ); ?>,
			paid: <?php echo wp_json_encode( __( 'Paid', 'restropress' ) ); ?>,
			paidNote: <?php echo wp_json_encode( sprintf( /* translators: 1: amount 2: method */ __( '%1$s paid by %2$s', 'restropress' ), $order_total, $method_noun ) ); ?>
		}
	};

	var root = document.querySelector('.rp-track-v1');
	if (!root) { return; }

	var hero = root.querySelector('.rp-track-hero');
	var elIcon = document.getElementById('rp-track-icon');
	var elHeadline = document.getElementById('rp-track-headline');
	var elMsg = document.getElementById('rp-track-msg');
	var elUpdated = document.getElementById('rp-track-updated');
	var elSteps = document.getElementById('rp-track-steps');
	var elCancel = document.getElementById('rp-track-cancel');
	var elEta = document.getElementById('rp-track-eta');
	var elEtaTime = document.getElementById('rp-track-eta-time');
	var elPayBadge = document.getElementById('rp-track-pay-badge');
	var elPayNote = document.getElementById('rp-track-pay-note');
	var alertsBtn = document.getElementById('rp-track-alerts');

	var current = CFG.status;
	var alertsOn = true;
	var pollTimer = null;
	var inFlight = false;

	function timeNow() {
		try { return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
		catch (e) { return CFG.i18n.justNow; }
	}

	function iconSvg(phase) {
		return '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (CFG.icons[phase] || CFG.icons.pending) + '</svg>';
	}

	function renderSteps(phase, step) {
		if (!elSteps) { return; }
		var names = CFG.stepNames, last = names.length - 1, html = '';
		for (var i = 0; i < names.length; i++) {
			var done = (step > i) || (phase === 'delivered');
			var active = (step === i) && (phase !== 'delivered');
			var state = done ? 'is-done' : (active ? 'is-active' : 'is-upcoming');
			var cls = 'rp-track-step ' + state + (i === 0 ? ' rp-track-first' : '') + (i === last ? ' rp-track-last' : '');
			cls += (i !== 0 && (done || active)) ? ' rp-lfill' : '';
			cls += (i !== last && done) ? ' rp-rfill' : '';
			var mark = done ? '✓' : (i + 1);
			html += '<div class="' + cls + '"><span class="rp-track-bar rp-track-bar-left"></span><span class="rp-track-bar rp-track-bar-right"></span><span class="rp-track-dot">' + mark + '</span><span class="rp-track-step-label">' + names[i] + '</span></div>';
		}
		elSteps.innerHTML = html;
	}

	function render(statusKey, label, updatedText) {
		var phase = CFG.phaseMap[statusKey] || 'pending';
		var step = (statusKey in CFG.stepMap) ? CFG.stepMap[statusKey] : 0;
		var copy = CFG.copy[phase] || CFG.copy.pending;

		root.setAttribute('data-phase', phase);
		hero.setAttribute('data-phase', phase);
		root.classList.toggle('is-cancelled', phase === 'cancelled');

		if (elIcon) { elIcon.innerHTML = iconSvg(phase); }
		if (elHeadline) { elHeadline.textContent = copy[0]; }
		if (elMsg) { elMsg.textContent = copy[1]; }
		if (elUpdated) { elUpdated.textContent = '· ' + CFG.i18n.updated + ' ' + (updatedText || timeNow()); }

		if (phase === 'cancelled') {
			if (elSteps) { elSteps.hidden = true; }
			if (elCancel) { elCancel.hidden = false; }
		} else {
			if (elCancel) { elCancel.hidden = true; }
			if (elSteps) { elSteps.hidden = false; renderSteps(phase, step); }
		}

		// ETA
		var etaHide = (phase === 'delivered' || phase === 'cancelled');
		if (elEta) {
			if (etaHide) { elEta.hidden = true; }
			else if (elEtaTime && elEtaTime.textContent.trim()) {
				elEta.hidden = false;
				if (phase === 'transit') { elEtaTime.textContent = CFG.i18n.anyMinute; }
			}
		}

		// Payment badge + note → Paid (green) when delivered
		if (phase === 'delivered' && elPayBadge && !elPayBadge.classList.contains('is-paid')) {
			elPayBadge.classList.add('is-paid');
			elPayBadge.textContent = CFG.i18n.paid;
			if (elPayNote && CFG.i18n.paidNote) { elPayNote.textContent = CFG.i18n.paidNote; }
		}
	}

	function formatUpdated(unix) {
		if (!unix) { return timeNow(); }
		try { return new Date(unix * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
		catch (e) { return timeNow(); }
	}

	function poll() {
		if (inFlight || !alertsOn) { return; }
		inFlight = true;
		var body = new URLSearchParams();
		body.set('action', 'rpress_receipt_order_status');
		body.set('payment_id', String(CFG.paymentId));
		body.set('payment_key', CFG.paymentKey);
		body.set('security', CFG.security);
		fetch(CFG.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success || !res.data || !res.data.status) { return; }
				var s = res.data.status;
				if (s !== current) {
					current = s;
					render(s, res.data.status_label || '', formatUpdated(res.data.updated_at_unix));
				}
				if (CFG.terminal[s]) { stop(); }
			})
			.catch(function () {})
			.then(function () { inFlight = false; });
	}

	function start() {
		if (pollTimer || CFG.terminal[current]) { return; }
		poll(); // check straight away so a status that advanced around page load is caught fast
		pollTimer = setInterval(poll, CFG.pollInterval);
	}
	function stop() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

	if (alertsBtn) {
		alertsBtn.addEventListener('click', function () {
			alertsOn = !alertsOn;
			alertsBtn.classList.toggle('is-on', alertsOn);
			alertsBtn.setAttribute('aria-pressed', alertsOn ? 'true' : 'false');
			alertsBtn.querySelector('.rp-track-alerts-label').textContent = alertsOn ? CFG.i18n.liveOn : CFG.i18n.liveOff;
			if (alertsOn) { start(); } else { stop(); }
		});
	}

	// Mobile collapsible receipt.
	var toggle = document.getElementById('rp-track-receipt-toggle');
	var orderCard = root.querySelector('.rp-track-order');
	if (toggle && orderCard) {
		toggle.addEventListener('click', function () {
			var open = orderCard.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	if (!CFG.terminal[current]) { start(); }
})();
</script>
