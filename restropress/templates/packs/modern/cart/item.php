<?php
/**
 * Template: Cart Item
 *
 * This template displays individual items in the cart including quantity,
 * price, and available actions (edit/remove).
 *
 * @package RestroPress/Templates
 * @version 1.1.0
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

$button_style = sanitize_html_class( rpress_get_option( 'button_style', 'button' ) );
?>
<?php
/**
 * Cart line, modern layout: the clickable item body (thumbnail + title) on the
 * left, and a right-aligned column stacking the line price over a − / value / +
 * quantity stepper — the stepper tucks under the price to reuse the height the
 * tall image + title already take, keeping each line compact. Clicking anywhere
 * on the item body opens the edit modal — the whole `.rpress-cart-item-main`
 * carries the `rpress-edit-from-cart` class + data attributes the edit handler
 * reads, so there's no separate Edit link. At qty 1 the − morphs into a trash
 * (JS adds .is-one) and fires the hidden Remove link below, reusing the tested
 * remove flow. Stepper glyphs are drawn in CSS (mask) — an inline SVG's stroke
 * gets stripped by the cart HTML sanitizer, leaving the buttons blank until use.
 */
?>
<li class="rpress-cart-item" data-cart-key="{cart_item_id}">
	<div class="rpress-cart-item-list">
		<div class="rpress-cart-item-main rpress-edit-from-cart <?php echo esc_attr( $button_style ); ?>" role="button" tabindex="0"
			title="<?php esc_attr_e( 'Edit item', 'restropress' ); ?>"
			data-cart-item="{cart_item_id}" data-item-name="{item_title}"
			data-item-id="{item_id}" data-item-price="{item_amount}" data-remove-item="{edit_food_item}">
			{item_thumbnail}
			<span class="rpress-cart-item-title">{item_title}</span>
		</div>
		<div class="rpress-cart-item-aside">
			<div class="rpress-cart-item-price">
				<span class="cart-item-quantity-wrap">
					<span class="rpress-cart-item-price qty-class">{item_formated_amount}</span>
				</span>
			</div>
			<div class="rpress-qty-stepper" data-cart-key="{cart_item_id}">
				<button type="button" class="rp-qty-btn rp-qty-dec" aria-label="<?php esc_attr_e( 'Decrease quantity', 'restropress' ); ?>"></button>
				<span class="rp-qty-val rpress-cart-item-qty qty-class">{item_qty}</span>
				<button type="button" class="rp-qty-btn rp-qty-inc" aria-label="<?php esc_attr_e( 'Increase quantity', 'restropress' ); ?>"></button>
			</div>
		</div>
	</div>
	<a href="{remove_url}" data-cart-item="{cart_item_id}" data-fooditem-id="{item_id}"
		data-action="rpress_remove_from_cart" class="rpress-remove-from-cart rp-remove-hidden <?php echo esc_attr( $button_style ); ?>" aria-hidden="true" tabindex="-1">
		<?php echo esc_html(apply_filters('rpress_cart_remove', __('Remove', 'restropress'))); ?></a>
	<div class="rpress-cart-addons">{addon_items}</div>
	<span class="rpress-special-instruction">{special_instruction}</span>
</li>
