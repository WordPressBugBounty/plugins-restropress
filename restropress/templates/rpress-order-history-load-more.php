<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$paged = ( isset( $_GET['paged'] ) && ! empty( $_GET['paged'] ) ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
$args  = array(
	'post_type'      => 'rpress_payment',
	'post_status'    => 'any',
	'posts_per_page' => 10,
	'paged'          => $paged,
	'order'          => 'DESC',
	'author'         => get_current_user_id(),
);

$loop             = new WP_Query( $args );
$rpress_settings  = get_option( 'rpress_settings', true );
$reorder_enabled  = ! empty( $rpress_settings['rp_reorder'] );
$found_post       = 0;

if ( $loop->have_posts() ) :
	while ( $loop->have_posts() ) :
		$loop->the_post();
		$payment = new RPRESS_Payment( get_the_ID() );

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
		<?php
	endwhile;
endif;

$found_post = count( $loop->posts );
wp_reset_postdata();
