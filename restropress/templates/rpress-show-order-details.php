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
$payment_status     = rpress_get_payment_status( $payment, true );
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
$payment_type     = rpress_order_details_safe_text( rpress_get_gateway_checkout_label( rpress_get_payment_gateway( $payment->ID ) ) );
$payment_status   = rpress_order_details_safe_text( $payment_status );
$order_status_label = rpress_order_details_safe_text( $order_status_label );
?>
<div class="rp-order-details-modal-wrap">
	<header class="modal__header modal-header">
		<h2 class="modal__title modal-title">
			<?php esc_html_e( 'Order Details', 'restropress' ); ?>
			<?php if ( '' !== $order_status_label ) : ?>
				<span class="button rpress-status"><?php echo esc_html( $order_status_label ); ?></span>
			<?php endif; ?>
		</h2>
		<button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
	</header>
	<main class="modal__content modal-body">
		<div class="rpress-order-details">
			<div class="rp-order-section-md-data">
				<div class="rp-detils-content-view">
					<?php if ( '' !== $customer_name ) : ?>
						<div class="rp-detail-row">
							<span class="rp-detail-label"><?php esc_html_e( 'Name', 'restropress' ); ?></span>
							<span class="rp-detail-value"><?php echo esc_html( $customer_name ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $phone_text ) : ?>
						<div class="rp-detail-row">
							<span class="rp-detail-label"><?php esc_html_e( 'Phone Number', 'restropress' ); ?></span>
							<span class="rp-detail-value"><?php echo esc_html( $phone_text ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $address ) : ?>
						<div class="rp-detail-row">
							<span class="rp-detail-label"><?php esc_html_e( 'Delivery To', 'restropress' ); ?></span>
							<span class="rp-detail-value"><?php echo esc_html( $address ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $service_date ) : ?>
						<div class="rp-detail-row">
							<span class="rp-detail-label"><?php echo esc_html( ucfirst( $service_label_text ) ); ?> <?php esc_html_e( 'Date', 'restropress' ); ?></span>
							<span class="rp-detail-value"><?php echo esc_html( $service_date ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $service_time ) : ?>
						<div class="rp-detail-row">
							<span class="rp-detail-label"><?php echo esc_html( ucfirst( $service_label_text ) ); ?> <?php esc_html_e( 'Time', 'restropress' ); ?></span>
							<span class="rp-detail-value"><?php echo esc_html( $service_time ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $payment_type ) : ?>
						<div class="rp-detail-row">
							<span class="rp-detail-label"><?php esc_html_e( 'Payment Type', 'restropress' ); ?></span>
							<span class="rp-detail-value"><?php echo esc_html( $payment_type ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $payment_status ) : ?>
						<div class="rp-detail-row">
							<span class="rp-detail-label"><?php esc_html_e( 'Payment Status', 'restropress' ); ?></span>
							<span class="rp-detail-value"><?php echo esc_html( $payment_status ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<hr class="rp-line" style="border-style: dashed;">

			<div class="rp-order-list-main-wrap">
				<h3><?php esc_html_e( 'Your Order', 'restropress' ); ?></h3>
				<ul class="rpress-cart">
					<?php if ( $cart ) : ?>
						<?php foreach ( $cart as $key => $item ) : ?>
							<?php
							$special_instruction = isset( $item['instruction'] ) ? rpress_order_details_safe_text( $item['instruction'] ) : '';
							$item_title          = rpress_order_details_safe_text( rpress_get_cart_item_name( $item ) );
							$item_price          = isset( $item['subtotal'] ) ? floatval( $item['subtotal'] ) : 0;
							?>
							<li class="rpress-cart-item" data-cart-key="<?php echo esc_attr( $key ); ?>">
								<div class="rp-cart-line-main">
									<span class="rpress-cart-item-qty qty-class"><?php echo absint( $item['quantity'] ); ?> <?php esc_html_e( 'x', 'restropress' ); ?></span>
									<span class="rpress-cart-item-title"><?php echo esc_html( $item_title ); ?></span>
								</div>
								<span class="cart-item-quantity-wrap">
									<span class="rpress-cart-item-price qty-class">
										<?php if ( empty( $item['in_bundle'] ) ) : ?>
											<?php echo wp_kses_post( rpress_currency_filter( rpress_format_amount( $item_price ), $payment_currency ) ); ?>
										<?php endif; ?>
									</span>
								</span>

								<div class="rp-addons-ht-wrap">
									<?php
									$addon_name = array();
									$options    = isset( $item['item_number']['options'] ) && is_array( $item['item_number']['options'] ) ? $item['item_number']['options'] : array();

									foreach ( $options as $option ) {
										if ( isset( $option['addon_item_name'] ) ) {
											$addon_item = rpress_order_details_safe_text( $option['addon_item_name'] );
											if ( '' !== $addon_item ) {
												$addon_name[] = $addon_item;
											}
										}
									}

									echo esc_html( implode( ', ', array_filter( $addon_name ) ) );
									?>
								</div>

								<?php if ( '' !== $special_instruction ) : ?>
									<span class="rpress-special-instruction"><?php echo esc_html( $special_instruction ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					<?php endif; ?>

					<li class="cart_item rpress-cart-meta rpress_subtotal">
						<?php esc_html_e( 'Subtotal', 'restropress' ); ?>
						<span class="cart-subtotal"><?php echo wp_kses_post( rpress_payment_subtotal( $payment->ID ) ); ?></span>
					</li>

					<?php if ( rpress_use_taxes() ) : ?>
						<li class="cart_item rpress-cart-meta rpress_cart_tax">
							<?php echo esc_html( rpress_get_tax_name() ); ?>
							<span class="cart-tax"><?php echo wp_kses_post( rpress_payment_tax( $payment->ID ) ); ?></span>
						</li>
					<?php endif; ?>

					<?php
					$fees = rpress_get_payment_fees( $payment->ID, 'fee' );
					if ( $fees ) :
						foreach ( $fees as $fee ) :
							$fee_amount = isset( $fee['amount'] ) ? floatval( $fee['amount'] ) : 0;
							?>
							<li class="cart_item rpress-cart-meta rpress_fess">
								<?php echo esc_html( rpress_order_details_safe_text( $fee['label'] ) ); ?>
								<span class="cart-discount"><?php echo wp_kses_post( rpress_currency_filter( rpress_format_amount( $fee_amount ), $payment_currency ) ); ?></span>
							</li>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if ( isset( $user['discount'] ) && 'none' !== $user['discount'] ) : ?>
						<li class="cart_item rpress-cart-meta rpress_user_discount">
							<?php esc_html_e( 'Coupon', 'restropress' ); ?>
							<span class="cart-discount"><?php echo wp_kses_post( $discount ); ?></span>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</main>
	<footer class="modal__footer modal-footer">
		<?php esc_html_e( 'Total', 'restropress' ); ?>
		<span><?php echo wp_kses_post( rpress_payment_amount( $payment->ID ) ); ?></span>
	</footer>
</div>
