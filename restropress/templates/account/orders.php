<?php
/**
 * Account: Orders section.
 *
 * Override in your theme: restropress/account/orders.php
 *
 * @package RestroPress
 * @since   3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="box-bg pd-2 rpress-account-orders">
	<div class="rpress-account-section-head">
		<h4 class="box-title"><?php esc_html_e( 'Orders', 'restropress' ); ?></h4>
	</div>
	<div class="rpress-order-cards" data-context="account">
		<?php $rpress_page = rpress_account_order_cards( 1, 'account' ); ?>
	</div>
	<?php if ( ! $rpress_page['count'] ) : ?>
		<div class="rpress-orders-empty">
			<p><?php esc_html_e( 'You have not placed any orders yet.', 'restropress' ); ?></p>
			<?php $rpress_menu = rpress_get_option( 'food_items_page', 0 ) ? get_permalink( rpress_get_option( 'food_items_page', 0 ) ) : ''; ?>
			<?php if ( $rpress_menu ) : ?>
				<a class="rpress-orders-empty-cta" href="<?php echo esc_url( $rpress_menu ); ?>"><?php esc_html_e( 'Browse the menu', 'restropress' ); ?></a>
			<?php endif; ?>
		</div>
	<?php elseif ( $rpress_page['has_more'] ) : ?>
		<?php rpress_account_orders_load_more_button( 'account' ); ?>
	<?php endif; ?>
</div>
