<?php
/**
 * Cart Panel block (rpress/cart-panel).
 *
 * Mini cart from the shared store: lines with quantity steppers (existing
 * rpress_cart_item_quantity AJAX), totals from RPRESS_Cart, the store's
 * open state from the slot engine, and a checkout button that runs the
 * existing pre-checkout validation (rpress_proceed_checkout). A polite live
 * region announces every change.
 *
 * @package RPRESS
 * @since   3.5
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

if ( ! rpress_block_storefront_supported() ) {
	echo rpress_block_storefront_fallback( 'cart-panel', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

rpress_block_storefront_seed_state();

$rpress_sf_title    = ! empty( $attributes['title'] ) ? $attributes['title'] : apply_filters( 'rpress_cart_title', __( 'Your Order', 'restropress' ) );
$rpress_sf_sticky   = ! isset( $attributes['sticky'] ) || ! empty( $attributes['sticky'] );
$rpress_sf_title_id = wp_unique_id( 'rpress-sf-cart-title-' );
$rpress_sf_checkout = rpress_get_option( 'checkout_button_text', __( 'Checkout', 'restropress' ) );

$rpress_sf_wrapper = rpress_block_storefront_wrapper(
	'rpress-sf-cart' . ( $rpress_sf_sticky ? ' is-sticky' : '' ),
	array(
		'data-wp-interactive' => RPRESS_STOREFRONT_NAMESPACE,
		'data-wp-class--is-busy' => 'state.busy',
		'aria-labelledby'     => $rpress_sf_title_id,
	)
);
?>
<aside <?php echo $rpress_sf_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="rpress-sf-cart-head">
		<h2 class="rpress-sf-cart-title" id="<?php echo esc_attr( $rpress_sf_title_id ); ?>"><?php echo esc_html( $rpress_sf_title ); ?></h2>
		<span class="rpress-sf-cart-count" data-wp-text="state.cart.countLabel"></span>
	</div>

	<p class="rpress-sf-cart-closed" hidden data-wp-bind--hidden="state.store.open" data-wp-text="state.store.message"></p>

	<p class="rpress-sf-cart-empty" data-wp-bind--hidden="state.cart.hasItems">
		<?php echo esc_html( apply_filters( 'rpress_block_storefront_empty_cart_text', __( 'Your cart is empty. Add items from the menu to get started.', 'restropress' ) ) ); ?>
	</p>

	<ul class="rpress-sf-cart-lines" data-wp-bind--hidden="!state.cart.hasItems">
		<template data-wp-each--line="state.cart.items" data-wp-each-key="context.line.key">
			<li class="rpress-sf-cart-line">
				<div class="rpress-sf-cart-line-info">
					<span class="rpress-sf-cart-line-name" data-wp-text="context.line.name"></span>
					<span class="rpress-sf-cart-line-options" data-wp-bind--hidden="!context.line.hasOptions" data-wp-text="context.line.options"></span>
				</div>
				<div class="rpress-sf-cart-line-qty" role="group" data-wp-bind--aria-label="context.line.quantityLabel">
					<button type="button" class="rpress-sf-qty-btn" data-wp-on--click="actions.decrement" data-wp-bind--aria-label="context.line.decreaseLabel" data-wp-bind--disabled="state.busy">
						<span aria-hidden="true">&minus;</span>
					</button>
					<span class="rpress-sf-qty-value" data-wp-text="context.line.quantity"></span>
					<button type="button" class="rpress-sf-qty-btn" data-wp-on--click="actions.increment" data-wp-bind--aria-label="context.line.increaseLabel" data-wp-bind--disabled="state.busy">
						<span aria-hidden="true">+</span>
					</button>
				</div>
				<span class="rpress-sf-cart-line-total" data-wp-text="context.line.lineTotal"></span>
			</li>
		</template>
	</ul>

	<dl class="rpress-sf-cart-totals" data-wp-bind--hidden="!state.cart.hasItems">
		<div class="rpress-sf-cart-row">
			<dt><?php esc_html_e( 'Subtotal', 'restropress' ); ?></dt>
			<dd data-wp-text="state.cart.subtotal"></dd>
		</div>
		<template data-wp-each--fee="state.cart.fees" data-wp-each-key="context.fee.key">
			<div class="rpress-sf-cart-row">
				<dt data-wp-text="context.fee.label"></dt>
				<dd data-wp-text="context.fee.amount"></dd>
			</div>
		</template>
		<div class="rpress-sf-cart-row" data-wp-bind--hidden="!state.cart.showTax">
			<dt data-wp-text="state.cart.taxLabel"></dt>
			<dd data-wp-text="state.cart.tax"></dd>
		</div>
		<div class="rpress-sf-cart-row is-total">
			<dt><?php esc_html_e( 'Total', 'restropress' ); ?></dt>
			<dd data-wp-text="state.cart.total"></dd>
		</div>
	</dl>

	<p class="rpress-sf-cart-error" role="alert" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>

	<a class="rpress-sf-checkout" data-wp-bind--href="state.checkoutUrl" href="<?php echo esc_url( rpress_get_checkout_uri() ); ?>" data-wp-on--click="actions.checkout" data-wp-bind--hidden="!state.cart.hasItems" data-wp-bind--aria-disabled="state.checkoutDisabled">
		<span class="rpress-sf-checkout-label"><?php echo esc_html( $rpress_sf_checkout ); ?></span>
		<span class="rpress-sf-checkout-total" data-wp-text="state.cart.total"></span>
	</a>

	<div class="screen-reader-text rpress-sf-live" aria-live="polite" aria-atomic="true" data-wp-text="state.announcement"></div>
</aside>
