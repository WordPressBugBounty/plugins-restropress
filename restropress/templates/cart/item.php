<?php
/**
 * Template: Cart Item
 *
 * This template displays individual items in the cart including quantity,
 * price, and available actions (edit/remove).
 *
 * @package RestroPress/Templates
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
?>
<li class="rpress-cart-item" data-cart-key="{cart_item_id}">
	<div class="rpress-cart-item-list">
		<span class="rpress-cart-item-title">{item_title}</span>
		<div class="rpress-cart-item-price">
			<div>
				<span class="separator">x</span>
				<span class="rpress-cart-item-qty qty-class">{item_qty}</span>
			</div>
			<span class="cart-item-quantity-wrap">
				<span class="rpress-cart-item-price qty-class">{item_formated_amount}</span>
			</span>
		</div>
	</div>
	<span class="rpress-special-instruction">{special_instruction}</span>
	<div>
		<span class="cart-action-wrap">
			<a class="rpress-addon-text-cart">
				<span>Addons</span>
			</a>
			<a class="rpress-edit-from-cart" data-cart-item="{cart_item_id}" data-item-name="{item_title}"
				data-item-id="{item_id}" data-item-price="{item_amount}" data-remove-item="{edit_food_item}">
				<svg width="11" height="10" viewBox="0 0 11 10" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M7.73179 0.0159254C7.51285 0.0726175 7.59691 -0.00753307 4.04294 3.55034C0.821308 6.77393 0.647324 6.95182 0.596497 7.06911C0.54958 7.17859 0.526122 7.31934 0.406874 8.22249C0.31695 8.89106 0.271988 9.29181 0.277852 9.36218C0.305221 9.68865 0.588678 9.9721 0.913187 9.99947C0.987472 10.0053 1.37649 9.96233 2.0607 9.87045C3.0831 9.73556 3.09678 9.73165 3.23362 9.66519C3.36265 9.60068 3.55618 9.41105 6.76413 6.1992C9.99945 2.95997 10.1597 2.79576 10.2145 2.67456C10.2634 2.56313 10.2712 2.52403 10.2712 2.36178C10.2712 2.1917 10.2653 2.16433 10.2067 2.03922C10.1461 1.91215 10.0816 1.84178 9.25855 1.01878C8.44337 0.203594 8.36517 0.131264 8.24201 0.0745716C8.09735 0.0061512 7.8745 -0.0192623 7.73179 0.0159254ZM8.57043 1.71667L9.21554 2.36178L8.71705 2.86027L8.21856 3.35876L7.56954 2.70974L6.91856 2.05877L7.41119 1.56614C7.68292 1.29441 7.90969 1.07156 7.91555 1.07156C7.92142 1.07156 8.2166 1.36088 8.57043 1.71667ZM6.88924 3.39786L7.53435 4.04297L5.15918 6.41619L2.784 8.79136L2.0998 8.87933C1.21228 8.99271 1.2983 8.98685 1.30221 8.93993C1.30416 8.91843 1.34717 8.58414 1.39604 8.19708L1.48792 7.49332L3.85527 5.12206C5.15918 3.82011 6.22849 2.75275 6.23436 2.75275C6.24022 2.75275 6.53541 3.04403 6.88924 3.39786Z" fill="black"/>
				</svg>
				<span class="rp-ajax-toggle-text">
					<?php echo esc_html(apply_filters('rpress_cart_edit', __('Edit', 'restropress'))); ?></span>
			</a>
			<a href="{remove_url}" data-cart-item="{cart_item_id}" data-fooditem-id="{item_id}"
				data-action="rpress_remove_from_cart" class="rpress-remove-from-cart">				
				<svg width="9" height="9" viewBox="0 0 9 9" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M0.526503 0.025013C0.100779 0.141119 -0.114847 0.594487 0.062077 0.995331C0.111837 1.10867 0.39381 1.40447 1.67651 2.68717L3.23012 4.24355L1.67651 5.79716C-0.0208561 7.49729 0.00125933 7.47241 0.00125933 7.78479C0.00125933 8.17458 0.308112 8.48143 0.700662 8.48143C1.01028 8.48143 0.988164 8.50078 2.68553 6.80618L4.23914 5.25257L5.79552 6.80618C7.49289 8.50355 7.46801 8.48143 7.78039 8.48143C8.17018 8.48143 8.47703 8.17458 8.47703 7.78203C8.47703 7.47241 8.49638 7.49453 6.80178 5.79716L5.24816 4.24355L6.80178 2.68717C8.49638 0.992567 8.47703 1.01468 8.47703 0.705065C8.47703 0.312514 8.17018 0.00566196 7.78039 0.00566196C7.46801 0.00566196 7.49289 -0.0164537 5.79552 1.68091L4.23914 3.23453L2.68553 1.68091C1.05727 0.0554218 1.04345 0.0415993 0.769773 0.00289726C0.708956 -0.00539589 0.598378 0.00566196 0.526503 0.025013Z" fill="#28303F"/>
				</svg>

				<?php echo esc_html(apply_filters('rpress_cart_remove', __('Remove', 'restropress'))); ?></a>
		</span>
	</div>
	<div>{addon_items}</div>
</li>