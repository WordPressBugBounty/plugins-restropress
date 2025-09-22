<?php
/**
 * Template: Cart Widget
 *
 * This template is used to display the RestroPress cart widget in the sidebar.
 *
 * @package RestroPress/Templates
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
$cart_items = rpress_get_cart_contents();
$cart_quantity = rpress_get_cart_quantity();
$display = $cart_quantity > 0 ? '' : 'style="display:none;"';
?>
<?php do_action('rpress_before_cart'); ?>
<div class="rp-col-lg-4 rp-col-md-4 rp-col-sm-12 rp-col-xs-12 pull-right rpress-sidebar-cart item-cart sticky-sidebar">
	<div class="rpress-mobile-cart-icons" <?php echo empty(rpress_get_cart_quantity()) ? 'style="display:none"' : '' ?>>
		<div class="rp-cart-left-wrap">
			<!-- <span class="rp-cart-mb-icon">
				<i class='fa fa-shopping-cart' aria-hidden='true'></i>
			</span> -->
			<div class='rpress-cart-badge rpress-cart-quantity'>
				<div class="rpress-total-price-wrap">
					<span>Total</span><span class="rp-mb-price"><?php echo esc_html(rpress_currency_filter(rpress_format_amount(rpress_get_cart_total()))); ?></span>
				</div>
				<div class='rpress-cart-total-item-list'>
					<span class="rp-mb-quantity"><?php echo esc_html(rpress_get_cart_quantity()); ?></span>
					<span><?php esc_html_e('items added to cart', 'restropress'); ?></span>
				</div>
			</div>
			<!-- <span class="rp-separation">&nbsp;|&nbsp;</span> -->
		</div>
<?php
$button_style = rpress_get_option('button_style', 'button');
?>
		<div class="rp-cart-right-wrap <?php echo esc_attr($button_style); ?>">
			<span class="rp-cart-mb-txt"><?php esc_html_e('Checkout', 'restropress'); ?></span>
			<!-- <span class="rp-cart-mb-icon"><i class="fa fa-caret-right" aria-hidden="true"></i></span> -->
			<svg width="8" height="13" viewBox="0 0 8 13" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path
					d="M0.145329 12.5589C0.284698 12.8212 0.526545 12.977 0.825778 12.9975C0.956949 13.0057 1.13731 12.9934 1.22339 12.9647C1.35456 12.9237 1.91614 12.3826 4.12144 10.1814C5.95374 8.35324 6.88423 7.39815 6.93752 7.28338C7.04 7.07842 7.04409 6.7382 6.94982 6.53734C6.90882 6.43897 5.90865 5.4101 4.21572 3.70897C2.17028 1.65943 1.49803 1.01178 1.34636 0.933893C0.608526 0.564976 -0.162102 1.31101 0.178122 2.06114C0.239608 2.20051 0.809381 2.79898 2.58429 4.57388L4.90847 6.89806L2.5433 9.26734C0.903661 10.9029 0.157627 11.6776 0.112536 11.7842C0.0182576 12.0137 0.0346541 12.3417 0.145329 12.5589Z"
					fill="white" />
			</svg>
		</div>
	</div>
	<div class="rpress-sidebar-main-wrap">
		<div class="rpress-sidebar-cart-wrap <?php echo empty($cart_items) ? 'empty-cart' : ''; ?>">
			<?php if ($cart_items): ?>
				<div class="rpress item-order">
					<h4><?php echo esc_html(apply_filters('rpress_cart_title', __('Your Order', 'restropress'))); ?>
					</h4>
					<span>
						<?php echo esc_html(rpress_get_cart_quantity()); ?>
						<?php esc_html_e('items', 'restropress'); ?>
					</span>
				</div>
			<?php endif; ?>
			<ul class="rpress-cart">
				<?php if ($cart_items): ?>
					<?php foreach ($cart_items as $key => $item): ?>
						<?php
						$allowed_html = array_merge(
							wp_kses_allowed_html('post'),
							[
								'svg' => [
									'xmlns' => true,
									'width' => true,
									'height' => true,
									'viewBox' => true,
									'fill' => true,
								],
								'path' => [
									'd' => true,
									'fill' => true,
								],
							]
						);
						echo wp_kses(rpress_get_cart_item_template($key, $item, false, $data_key = ''), $allowed_html);
						?>
					<?php endforeach; ?>
					<?php rpress_get_template_part('cart/checkout'); ?>
				<?php else: ?>
					<?php rpress_get_template_part('cart/empty'); ?>
				<?php endif; ?>
			</ul>
		</div>
	</div>
</div>
<?php do_action('rpress_after_cart'); ?>