<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $_GET['rpress-verify-success'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification -- display only.
	?>
	<p class="rpress-account-verified rpress_success">
		<?php esc_html_e( 'Your account has been successfully verified!', 'restropress' ); ?>
	</p>
	<?php
endif;

/**
 * The current user's orders as cards ([order_history] and its block).
 * The card itself is templates/account/order-card.php.
 */
if ( is_user_logged_in() ) :
	$rpress_orders = rpress_account_orders_query( get_current_user_id(), 1 );
	if ( $rpress_orders->posts ) :
		// Kept for code hooked here before 3.5.1: the first page of payments.
		$payments = $rpress_orders->posts;
		do_action( 'rpress_before_order_history', $payments );
		?>
		<div id="rpress_user_history" class="rpress-order-history">
			<div class="rpress-order-history-heading">
				<div>
					<span class="rpress-order-history-eyebrow"><?php esc_html_e( 'Order history', 'restropress' ); ?></span>
					<h2><?php esc_html_e( 'Your recent orders', 'restropress' ); ?></h2>
				</div>
				<span class="rpress-order-history-count">
					<?php
					printf(
						/* translators: %d: number of orders. */
						esc_html( _n( '%d order', '%d orders', (int) $rpress_orders->found_posts, 'restropress' ) ),
						absint( $rpress_orders->found_posts )
					);
					?>
				</span>
			</div>

			<div class="rpress-order-cards" data-context="history">
				<?php $rpress_page = rpress_account_order_cards( 1, 'history' ); ?>
			</div>
			<?php
			if ( $rpress_page['has_more'] ) {
				rpress_account_orders_load_more_button( 'history' );
			}
			?>
		</div>
		<?php do_action( 'rpress_after_order_history', $payments ); ?>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<p class="rpress-no-purchases"><?php esc_html_e( 'You have not made any orders', 'restropress' ); ?></p>
	<?php endif; ?>
<?php endif; ?>
