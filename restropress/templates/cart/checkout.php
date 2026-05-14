<?php
/**
 * Template: Cart Checkout
 *
 * This template handles the checkout section of the cart including subtotal, tax,
 * total calculations and the checkout button.
 *
 * @package RestroPress/Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$cart_quantity = rpress_get_cart_quantity();
$display       = $cart_quantity > 0 ? '' : ' style="display:none;"';
$button_style = rpress_get_option('button_style', 'button');
$old_ui_ux_enabled = ! empty( rpress_get_option( 'old_ui_ux' ) );
?>
<div class="rpress-cart-total-wrap">
	<ul class="rpress-cart-summary-list">
	<li class="cart_item rpress-cart-meta rpress_subtotal"><?php esc_html_e( 'Subtotal', 'restropress' ); ?> <span class='cart-subtotal'><?php echo esc_html( rpress_currency_filter( rpress_format_amount( rpress_get_cart_subtotal() ) ) ); ?></span></li>
	<?php
	$cart_discounts = rpress_get_cart_discounts();
	$discount_total = (float) rpress_get_cart_discounted_amount();
	if ( rpress_cart_has_discounts() && $discount_total > 0 ) :
		$discount_label = esc_html__( 'Discount', 'restropress' );
		if ( is_array( $cart_discounts ) && ! empty( $cart_discounts ) ) {
			$discount_label .= ' (' . implode( ', ', array_map( 'sanitize_text_field', $cart_discounts ) ) . ')';
		}
		?>
		<li class="cart_item rpress-cart-meta rpress_user_discount">
			<?php echo esc_html( $discount_label ); ?>
			<span class="cart-discount">-<?php echo esc_html( rpress_currency_filter( rpress_format_amount( $discount_total ) ) ); ?></span>
		</li>
	<?php endif; ?>
	<?php if ( rpress_use_taxes() && !empty( ceil( rpress_get_cart_tax() ) ) ) : ?>
	<li class="cart_item rpress-cart-meta rpress_cart_tax"><?php echo esc_html( rpress_get_tax_name() ); ?> <span class="cart-tax"><?php echo esc_html( rpress_currency_filter( rpress_format_amount( rpress_get_cart_tax() ) ) ); ?></span></li>
	<?php endif; ?>
	<?php if ( rpress_cart_has_fees() ) : ?>
		<?php foreach ( rpress_get_cart_fees() as $fee_id => $fee ) : ?>
			<?php
			$fee_label = isset( $fee['label'] ) ? $fee['label'] : esc_html__( 'Fee', 'restropress' );
			$fee_amount = isset( $fee['amount'] ) ? (float) $fee['amount'] : 0;
			if ( ! apply_filters( 'rpress_cart_sidebar_render_fee', true, $fee_id, $fee ) ) {
				continue;
			}
			$fee_classes = array( 'cart_item', 'rpress-cart-meta', 'rpress_cart_fee' );
			if ( false !== stripos( (string) $fee_label, 'delivery' ) ) {
				$fee_classes[] = 'rpress-delivery-fee';
			}
			?>
			<li class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $fee_classes ) ) ); ?>" id="rpress_cart_fee_<?php echo esc_attr( $fee_id ); ?>">
				<?php echo esc_html( $fee_label ); ?>
				<span class="cart-delivery-fee"><?php echo esc_html( rpress_currency_filter( rpress_format_amount( $fee_amount ) ) ); ?></span>
			</li>
		<?php endforeach; ?>
	<?php endif; ?>
	<?php do_action( 'rpress_cart_line_item' ); ?>
	<li class="cart_item rpress-cart-meta rpress_total">
		<span class="rpress-total-label"><?php esc_html_e( 'Total (', 'restropress' ); ?><span class="rpress-cart-quantity" <?php echo wp_kses_post( $display ); ?> ><?php echo esc_html( $cart_quantity ); ?></span><?php esc_html_e( ' Items)', 'restropress' ); ?></span>
		<span class="cart-total"><?php echo esc_html( rpress_currency_filter( rpress_format_amount( rpress_get_cart_total() ) ) ); ?></span>
	</li>
	</ul>
</div>
<!-- Service Type and Service Time -->
<?php if ( ( isset( $_COOKIE['service_type'] ) && !empty( $_COOKIE['service_type'] ) ) || ( isset( $_COOKIE['service_time'] ) && !empty( $_COOKIE['service_time'] ) ) ) : ?>

<?php endif; ?>
<?php if ( $old_ui_ux_enabled && function_exists( 'get_delivery_options' ) ) : ?>
<div class="delivery-items-options"<?php echo wp_kses_post( $display ); ?>>
	<?php echo wp_kses_post( get_delivery_options( true ) ); ?>
</div>
<?php endif; ?>
<?php if( apply_filters( 'rpress_show_checkout_button', true ) ) : ?>
<ul class="rpress-cart-summary-list rpress-cart-checkout-list">
	<li class="cart_item rpress_checkout">
	  	<a class="rpress-checkout-cart rpress-submit <?php echo esc_attr($button_style) ?>" data-url="<?php echo esc_url( rpress_get_checkout_uri() ); ?>" href="#">
	  		<span class="rpress-checkout-label rp-ajax-toggle-text">
	  			<?php
	    		$confirm_order_text = apply_filters( 'rp_confirm_order_text', esc_html__( 'Checkout', 'restropress' ) );
	    		echo esc_html( $confirm_order_text ); ?>
	    	</span> 
		</a>
	</li>
</ul>
<?php endif; ?>
<?php do_action( 'rpress_after_checkout_button' ); ?>
