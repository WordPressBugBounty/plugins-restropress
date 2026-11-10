<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rpress_order_details_safe_text' ) ) {
	/**
	 * Normalize scalar/array values to a safe printable string for modal output.
	 *
	 * @param mixed $value Value to normalize.
	 * @return string
	 */
	function rpress_order_details_safe_text( $value ) {
		if ( is_array( $value ) ) {
			$flattened = array();
			array_walk_recursive(
				$value,
				static function ( $item ) use ( &$flattened ) {
					if ( is_scalar( $item ) ) {
						$text = trim( (string) $item );
						if ( '' !== $text ) {
							$flattened[] = $text;
						}
					}
				}
			);

			return implode( ', ', $flattened );
		}

		if ( is_scalar( $value ) ) {
			return trim( (string) $value );
		}

		return '';
	}
}

$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : '';
$payment  = get_post( $order_id );

if ( empty( $payment ) ) {
	return;
}

$meta               = rpress_get_payment_meta( $payment->ID );
$service_time       = rpress_get_payment_meta( $payment->ID, '_rpress_delivery_time' );
$service_date       = rpress_get_payment_meta( $payment->ID, '_rpress_delivery_date', true );
$cart               = rpress_get_payment_meta_cart_details( $payment->ID, true );
$discount           = rpress_get_discount_price_by_payment_id( $payment->ID );
$user               = rpress_get_payment_meta_user_info( $payment->ID );
$payment_status     = function_exists( 'rpress_get_payment_paid_label' ) ? rpress_get_payment_paid_label( $payment ) : rpress_get_payment_status( $payment, true );
$order_status       = rpress_get_order_status( $payment->ID );
$order_status_label = rpress_get_order_status_label( $order_status );
$service_type       = rpress_get_payment_meta( $payment->ID, '_rpress_delivery_type' );
$service_label      = rpress_service_label( $service_type );
$payment_currency   = rpress_get_payment_currency_code( $payment->ID );

$phone       = ! empty( $meta['phone'] ) ? $meta['phone'] : ( ! empty( $user['phone'] ) ? $user['phone'] : '' );
$firstname   = isset( $user['first_name'] ) ? $user['first_name'] : '';
$lastname    = isset( $user['last_name'] ) ? $user['last_name'] : '';
$address_info = get_post_meta( $payment->ID, '_rpress_delivery_address', true );

$address_parts = array();
if ( is_array( $address_info ) ) {
	$address_fields = array( 'address', 'flat', 'city', 'postcode' );
	foreach ( $address_fields as $address_field ) {
		if ( isset( $address_info[ $address_field ] ) ) {
			$part = rpress_order_details_safe_text( $address_info[ $address_field ] );
			if ( '' !== $part ) {
				$address_parts[] = $part;
			}
		}
	}
}
$address = implode( ', ', $address_parts );

$service_label_text = rpress_order_details_safe_text( $service_label );
if ( '' === $service_label_text ) {
	$service_label_text = rpress_order_details_safe_text( $service_type );
}

$customer_name    = trim( rpress_order_details_safe_text( $firstname . ' ' . $lastname ) );
$phone_text       = rpress_order_details_safe_text( $phone );
$service_date     = ( '' !== $service_date && 'undefined' !== $service_date ) ? rpress_local_date( $service_date ) : '';
$service_date     = rpress_order_details_safe_text( $service_date );
$service_time     = rpress_order_details_safe_text( $service_time );
$service_time     = function_exists( 'rpress_format_service_time' ) ? rpress_format_service_time( $service_time ) : $service_time;
$payment_type     = rpress_order_details_safe_text( rpress_get_gateway_checkout_label( rpress_get_payment_gateway( $payment->ID ) ) );
$payment_status   = rpress_order_details_safe_text( $payment_status );
$order_status_label = rpress_order_details_safe_text( $order_status_label );

$order_number = rpress_order_details_safe_text( rpress_get_payment_number( $payment->ID ) );
$placed_on    = rpress_order_details_safe_text( date_i18n( get_option( 'date_format' ), strtotime( $payment->post_date ) ) );
$status_model = function_exists( 'rpress_order_status_model' ) ? rpress_order_status_model() : array();
$status_phase = isset( $status_model[ $order_status ]['phase'] ) ? $status_model[ $order_status ]['phase'] : 'pending';
$when         = implode( ' · ', array_filter( array( $service_date, $service_time ) ) );
$payment_line = implode( ' · ', array_filter( array( $payment_type, $payment_status ) ) );
$contact_line = implode( ' · ', array_filter( array( $customer_name, $phone_text ) ) );
/* translators: %s: service type, e.g. Delivery or Pickup */
$when_label   = '' !== $service_label_text ? sprintf( __( '%s time', 'restropress' ), ucfirst( $service_label_text ) ) : __( 'Time', 'restropress' );
?>
<div class="rp-order-details-modal-wrap rp-od">
	<header class="rp-od-head">
		<div class="rp-od-head-text">
			<h2 id="rpressModal-title" class="rp-od-title">
				<?php
				/* translators: %s: order number */
				echo esc_html( sprintf( __( 'Order #%s', 'restropress' ), $order_number ) );
				?>
			</h2>
			<p class="rp-od-sub">
				<?php
				/* translators: %s: date the order was placed */
				echo esc_html( sprintf( __( 'Placed %s', 'restropress' ), $placed_on ) );
				if ( '' !== $service_label_text ) {
					echo ' · ' . esc_html( ucfirst( $service_label_text ) );
				}
				?>
			</p>
		</div>
		<?php if ( '' !== $order_status_label ) : ?>
			<span class="rp-od-status is-<?php echo esc_attr( $status_phase ); ?>"><?php echo esc_html( $order_status_label ); ?></span>
		<?php endif; ?>
		<button type="button" class="rp-od-close" aria-label="<?php esc_attr_e( 'Close modal', 'restropress' ); ?>" data-micromodal-close></button>
	</header>

	<div class="rp-od-body">
		<dl class="rp-od-facts">
			<?php if ( '' !== $when ) : ?>
				<div><dt><?php echo esc_html( $when_label ); ?></dt><dd><?php echo esc_html( $when ); ?></dd></div>
			<?php endif; ?>
			<?php if ( '' !== $address ) : ?>
				<div><dt><?php esc_html_e( 'Delivery To', 'restropress' ); ?></dt><dd><?php echo esc_html( $address ); ?></dd></div>
			<?php endif; ?>
			<?php if ( '' !== $payment_line ) : ?>
				<div><dt><?php esc_html_e( 'Payment', 'restropress' ); ?></dt><dd><?php echo esc_html( $payment_line ); ?></dd></div>
			<?php endif; ?>
			<?php if ( '' !== $contact_line ) : ?>
				<div><dt><?php esc_html_e( 'Contact', 'restropress' ); ?></dt><dd><?php echo esc_html( $contact_line ); ?></dd></div>
			<?php endif; ?>
		</dl>

		<h3 class="rp-od-h"><?php esc_html_e( 'Your Order', 'restropress' ); ?></h3>
		<ul class="rp-od-items">
			<?php foreach ( (array) $cart as $key => $item ) : ?>
				<?php
				$special_instruction = isset( $item['instruction'] ) ? rpress_order_details_safe_text( $item['instruction'] ) : '';
				$item_title          = rpress_order_details_safe_text( rpress_get_cart_item_name( $item ) );
				$item_price          = isset( $item['subtotal'] ) ? floatval( $item['subtotal'] ) : 0;
				$addon_name          = array();
				$options             = isset( $item['item_number']['options'] ) && is_array( $item['item_number']['options'] ) ? $item['item_number']['options'] : array();
				foreach ( $options as $option ) {
					if ( isset( $option['addon_item_name'] ) ) {
						$addon_item = rpress_order_details_safe_text( $option['addon_item_name'] );
						if ( '' !== $addon_item ) {
							$addon_name[] = $addon_item;
						}
					}
				}
				?>
				<li class="rp-od-item" data-cart-key="<?php echo esc_attr( $key ); ?>">
					<span class="rp-od-qty"><?php echo absint( $item['quantity'] ); ?>&times;</span>
					<div class="rp-od-item-main">
						<span class="rp-od-item-name"><?php echo esc_html( $item_title ); ?></span>
						<?php if ( $addon_name ) : ?>
							<span class="rp-od-item-opts"><?php echo esc_html( implode( ' · ', $addon_name ) ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $special_instruction ) : ?>
							<span class="rp-od-item-note"><?php echo esc_html( $special_instruction ); ?></span>
						<?php endif; ?>
					</div>
					<span class="rp-od-item-price">
						<?php if ( empty( $item['in_bundle'] ) ) : ?>
							<?php echo wp_kses_post( rpress_currency_filter( rpress_format_amount( $item_price ), $payment_currency ) ); ?>
						<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>

		<dl class="rp-od-totals">
			<?php
			// Sum of the lines above, as stored on the order. The payment's
			// subtotal is rebuilt from current menu prices, so it drifts for
			// old orders once dishes are repriced or removed.
			$lines_subtotal = 0;
			foreach ( (array) $cart as $line ) {
				if ( empty( $line['in_bundle'] ) && isset( $line['subtotal'] ) ) {
					$lines_subtotal += (float) $line['subtotal'];
				}
			}
			$subtotal_html = $lines_subtotal > 0
				? rpress_currency_filter( rpress_format_amount( $lines_subtotal ), $payment_currency )
				: rpress_payment_subtotal( $payment->ID );
			?>
			<div><dt><?php esc_html_e( 'Subtotal', 'restropress' ); ?></dt><dd><?php echo wp_kses_post( $subtotal_html ); ?></dd></div>
			<?php if ( rpress_use_taxes() ) : ?>
				<div><dt><?php echo esc_html( rpress_get_tax_name() ); ?></dt><dd><?php echo wp_kses_post( rpress_payment_tax( $payment->ID ) ); ?></dd></div>
			<?php endif; ?>
			<?php foreach ( (array) rpress_get_payment_fees( $payment->ID, 'fee' ) as $fee ) : ?>
				<?php $fee_amount = isset( $fee['amount'] ) ? floatval( $fee['amount'] ) : 0; ?>
				<div><dt><?php echo esc_html( rpress_order_details_safe_text( isset( $fee['label'] ) ? $fee['label'] : '' ) ); ?></dt><dd><?php echo wp_kses_post( rpress_currency_filter( rpress_format_amount( $fee_amount ), $payment_currency ) ); ?></dd></div>
			<?php endforeach; ?>
			<?php if ( isset( $user['discount'] ) && 'none' !== $user['discount'] ) : ?>
				<div><dt><?php esc_html_e( 'Coupon', 'restropress' ); ?></dt><dd><?php echo wp_kses_post( $discount ); ?></dd></div>
			<?php endif; ?>
			<div class="rp-od-total"><dt><?php esc_html_e( 'Total', 'restropress' ); ?></dt><dd><?php echo wp_kses_post( rpress_payment_amount( $payment->ID ) ); ?></dd></div>
		</dl>
	</div>

	<?php
	ob_start();
	/**
	 * Buttons at the bottom of the order details popup (Track order / Receipt
	 * from the account hub).
	 *
	 * @since 3.5
	 * @param int $payment_id Order ID.
	 */
	do_action( 'rpress_order_details_modal_actions', $payment->ID );
	$actions = trim( ob_get_clean() );
	?>
	<?php if ( '' !== $actions ) : ?>
		<footer class="rp-od-actions"><?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the callbacks. ?></footer>
	<?php endif; ?>
</div>
