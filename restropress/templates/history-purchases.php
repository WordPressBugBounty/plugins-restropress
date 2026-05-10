<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $_GET['rpress-verify-success'] ) ) :
	?>
	<p class="rpress-account-verified rpress_success">
		<?php esc_html_e( 'Your account has been successfully verified!', 'restropress' ); ?>
	</p>
	<?php
endif;

/**
 * This template is used to display the order history of the current user.
 */
if ( is_user_logged_in() ) :
	$payments = rpress_get_users_orders( get_current_user_id(), 10, true, 'any' );
	if ( $payments ) :
		$rpress_settings = get_option( 'rpress_settings', true );
		$reorder_enabled = ! empty( $rpress_settings['rp_reorder'] );
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
						/* translators: %d: number of orders shown. */
						esc_html( _n( '%d order', '%d orders', count( $payments ), 'restropress' ) ),
						absint( count( $payments ) )
					);
					?>
				</span>
			</div>

			<div class="repress-history-inner rpress-order-history-grid">
				<?php foreach ( $payments as $payment_post ) : ?>
					<?php
					$payment = new RPRESS_Payment( $payment_post->ID );

					$address_info = get_post_meta( $payment->ID, '_rpress_delivery_address', true );
					$address_bits = array();
					if ( ! empty( $address_info['address'] ) ) {
						$address_bits[] = $address_info['address'];
					}
					if ( ! empty( $address_info['flat'] ) ) {
						$address_bits[] = $address_info['flat'];
					}
					if ( ! empty( $address_info['city'] ) ) {
						$address_bits[] = $address_info['city'];
					}
					if ( ! empty( $address_info['postcode'] ) ) {
						$address_bits[] = $address_info['postcode'];
					}
					$address = implode( ', ', array_filter( $address_bits ) );

					$service_type        = get_post_meta( $payment->ID, '_rpress_delivery_type', true );
					$service_type_label  = ! empty( $service_type ) ? ucwords( str_replace( array( '-', '_' ), ' ', $service_type ) ) : __( 'Order', 'restropress' );
					$order_status        = get_post_meta( $payment->ID, '_order_status', true );
					$order_status_key    = sanitize_key( $order_status );
					$order_status_label  = rpress_get_order_status_label( $order_status );
					$order_status_label  = ! empty( $order_status_label ) ? $order_status_label : $order_status;
					$order_items         = array();
					foreach ( $payment->fooditems as $cart_item ) {
						$fooditem = new RPRESS_Fooditem( $cart_item['id'] );
						$name     = $fooditem->get_name();
						if ( $fooditem->has_variable_prices() && isset( $cart_item['options']['price_id'] ) ) {
							$variation_name = rpress_get_price_option_name( $fooditem->ID, $cart_item['options']['price_id'] );
							if ( ! empty( $variation_name ) ) {
								$name .= ' - ' . $variation_name;
							}
						}
						$order_items[] = $name . ' &times; ' . $cart_item['quantity'];
					}
					$items_purchased = implode( ', ', $order_items );

					do_action( 'rpress_order_history_row_start', $payment->ID, $payment->payment_meta );
					?>
					<article class="rpress_purchase_row rpress-history-card rpress-order-status-<?php echo esc_attr( $order_status_key ); ?>">
						<header class="rpress-history-card-header">
							<div>
								<span class="rpress-history-card-label"><?php esc_html_e( 'Order', 'restropress' ); ?></span>
								<h3>#<?php echo esc_html( $payment->number ); ?></h3>
							</div>
							<span class="button rpress-status rpress-status-badge status-<?php echo esc_attr( $order_status_key ); ?>">
								<?php echo esc_html( $order_status_label ); ?>
							</span>
						</header>

						<div class="rpress-history-meta-grid">
							<div class="rpress-history-meta-item">
								<span><?php esc_html_e( 'Placed on', 'restropress' ); ?></span>
								<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $payment->date ) ) ); ?></strong>
							</div>
							<div class="rpress-history-meta-item">
								<span><?php esc_html_e( 'Order type', 'restropress' ); ?></span>
								<strong><?php echo esc_html( $service_type_label ); ?></strong>
							</div>
							<?php if ( $address ) : ?>
								<div class="rpress-history-meta-item rpress-history-meta-address">
									<span><?php esc_html_e( 'Address', 'restropress' ); ?></span>
									<strong><?php echo esc_html( $address ); ?></strong>
								</div>
							<?php endif; ?>
						</div>

						<div class="rpress-history-items">
							<span><?php esc_html_e( 'Items', 'restropress' ); ?></span>
							<p><?php echo wp_kses_post( $items_purchased ); ?></p>
						</div>

						<footer class="rpress-history-card-footer">
							<div class="rpress-history-total">
								<span><?php esc_html_e( 'Total paid', 'restropress' ); ?></span>
								<strong><?php echo esc_html( rpress_currency_filter( rpress_format_amount( $payment->total ), rpress_get_payment_currency_code( $payment->ID ) ) ); ?></strong>
							</div>
							<div class="rpess-view-details rpress-history-actions">
								<a href="#" class="rpress-view-order-btn" data-order-id="<?php echo esc_attr( $payment->ID ); ?>">
									<span class="rp-ajax-toggle-text"><?php esc_html_e( 'View Details', 'restropress' ); ?></span>
								</a>
								<?php do_action( 'rpress_order_after_view_details', $payment->ID, $payment->payment_meta ); ?>
								<?php if ( $reorder_enabled ) : ?>
									<a href="#" class="rpress-reorder-btn" data-order-id="<?php echo esc_attr( $payment->ID ); ?>">
										<span class="rp-ajax-toggle-text"><?php esc_html_e( 'Reorder', 'restropress' ); ?></span>
									</a>
								<?php endif; ?>
							</div>
						</footer>
					</article>
				<?php endforeach; ?>
			</div>

			<div class="rp-infinite-load-main">
				<div class="rp-infinite-load" id="rp-order-history-infi-load-container"></div>
			</div>
		</div>
		<?php do_action( 'rpress_after_order_history', $payments ); ?>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<p class="rpress-no-purchases"><?php esc_html_e( 'You have not made any orders', 'restropress' ); ?></p>
	<?php endif; ?>
<?php endif; ?>
