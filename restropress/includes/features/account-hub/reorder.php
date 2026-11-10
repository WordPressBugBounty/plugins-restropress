<?php
/**
 * Account hub: one-tap reorder that skips items that can no longer be bought.
 *
 * Replaces the core `rpress_reorder` AJAX handler (same action, nonce and
 * response shape, so assets/js/frontend/rp-reorder.js keeps working) with one
 * that re-checks every line against the current menu and current prices.
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is reorder switched on for this store?
 *
 * @return bool
 */
function rpress_account_hub_reorder_enabled() {
	return (bool) rpress_get_option( 'rp_reorder', false );
}

/**
 * Can the current visitor act on this order? Logged-in customers: only their
 * own orders. Guests: only guest orders, and only with the order's key.
 *
 * @param int    $order_id    Payment ID.
 * @param string $payment_key Payment key sent by a guest.
 * @return bool
 */
function rpress_account_hub_can_access_order( $order_id, $payment_key = '' ) {
	$order_id = absint( $order_id );
	if ( ! $order_id || 'rpress_payment' !== get_post_type( $order_id ) ) {
		return false;
	}
	$owner = absint( rpress_get_payment_user_id( $order_id ) );
	if ( is_user_logged_in() ) {
		return $owner > 0 && get_current_user_id() === $owner;
	}
	if ( $owner > 0 || '' === $payment_key ) {
		return false;
	}
	return absint( rpress_get_purchase_id_by_key( $payment_key ) ) === $order_id;
}

/**
 * Why a past order line cannot be bought again, or '' when it can.
 *
 * @param array $line Cart line from rpress_get_payment_meta_cart_details().
 * @return string Reason code: missing | unavailable | option_gone | ''.
 */
function rpress_account_hub_reorder_line_problem( $line ) {
	$fooditem_id = isset( $line['id'] ) ? absint( $line['id'] ) : 0;
	if ( ! $fooditem_id || 'fooditem' !== get_post_type( $fooditem_id ) ) {
		return 'missing';
	}
	$fooditem = new RPRESS_Fooditem( $fooditem_id );
	if ( empty( $fooditem->ID ) || ! $fooditem->can_purchase() || ! rpress_fooditem_available( $fooditem_id ) ) {
		return 'unavailable';
	}
	if ( rpress_has_variable_prices( $fooditem_id ) ) {
		$price_id = rpress_account_hub_line_price_id( $line );
		$prices   = rpress_get_variable_prices( $fooditem_id );
		if ( null === $price_id || ! is_array( $prices ) || ! isset( $prices[ $price_id ] ) ) {
			return 'option_gone';
		}
	}
	return (string) apply_filters( 'rpress_account_hub_reorder_line_problem', '', $line );
}

/**
 * The size/variation a past line was ordered with.
 *
 * @param array $line Cart line.
 * @return int|null
 */
function rpress_account_hub_line_price_id( $line ) {
	if ( isset( $line['item_number']['options']['price_id'] ) && '' !== $line['item_number']['options']['price_id'] ) {
		return (int) $line['item_number']['options']['price_id'];
	}
	if ( isset( $line['item_number']['price_id'] ) && '' !== $line['item_number']['price_id'] ) {
		return (int) $line['item_number']['price_id'];
	}
	return null;
}

/**
 * Display name of a past order line.
 *
 * @param array $line Cart line.
 * @return string
 */
function rpress_account_hub_line_name( $line ) {
	if ( ! empty( $line['name'] ) ) {
		return (string) $line['name'];
	}
	$title = isset( $line['id'] ) ? get_the_title( absint( $line['id'] ) ) : '';
	return '' !== $title ? $title : __( 'An item', 'restropress' );
}

/**
 * Add an order's items back to the cart, at today's prices, skipping lines
 * that can no longer be bought. Add-ons that were removed from the menu are
 * dropped from their line.
 *
 * @param int $order_id Payment ID (access must be checked by the caller).
 * @return array {
 *     @type array $added   Names of the lines added.
 *     @type array $skipped Names of the lines skipped.
 *     @type array $changed Names of the lines added without some add-ons.
 * }
 */
function rpress_account_hub_reorder( $order_id ) {
	$result = array(
		'added'   => array(),
		'skipped' => array(),
		'changed' => array(),
		'keys'    => array(),
	);
	$cart = rpress_get_payment_meta_cart_details( absint( $order_id ), true );
	if ( empty( $cart ) || ! is_array( $cart ) ) {
		return $result;
	}

	foreach ( $cart as $line ) {
		$name = rpress_account_hub_line_name( $line );
		if ( '' !== rpress_account_hub_reorder_line_problem( $line ) ) {
			$result['skipped'][] = $name;
			continue;
		}

		$fooditem_id = absint( $line['id'] );
		$options     = array(
			'id'          => $fooditem_id,
			'quantity'    => isset( $line['quantity'] ) ? max( 1, absint( $line['quantity'] ) ) : 1,
			'instruction' => isset( $line['instruction'] ) ? sanitize_textarea_field( $line['instruction'] ) : '',
		);
		$price_id = null;
		if ( rpress_has_variable_prices( $fooditem_id ) ) {
			$price_id            = rpress_account_hub_line_price_id( $line );
			$options['price_id'] = $price_id;
			$options['price']    = rpress_get_price_option_amount( $fooditem_id, $price_id );
		} else {
			$options['price'] = rpress_get_fooditem_price( $fooditem_id );
		}

		$dropped_addon = false;
		$addon_items   = isset( $line['addon_items'] ) && is_array( $line['addon_items'] ) ? $line['addon_items'] : array();
		foreach ( $addon_items as $key => $addon ) {
			if ( ! is_array( $addon ) || empty( $addon['addon_id'] ) ) {
				continue;
			}
			$term = get_term( absint( $addon['addon_id'] ), 'addon_category' );
			if ( ! $term || is_wp_error( $term ) ) {
				$dropped_addon = true;
				continue;
			}
			// Today's add-on price, resolved server-side like a fresh add to cart.
			$price = rpress_dynamic_addon_price( $fooditem_id, absint( $addon['addon_id'] ), null, $price_id );
			$options['addon_items'][ $key ] = array(
				'addon_item_name' => $term->name,
				'addon_id'        => absint( $addon['addon_id'] ),
				'price'           => ! empty( $price ) ? floatval( $price ) : 0,
				'quantity'        => isset( $addon['quantity'] ) ? max( 1, absint( $addon['quantity'] ) ) : 1,
			);
		}

		$key = rpress_add_to_cart( $fooditem_id, $options );
		if ( null === $key || false === $key ) {
			$result['skipped'][] = $name;
			continue;
		}
		$result['added'][] = $name;
		$result['keys'][]  = $key;
		if ( $dropped_addon ) {
			$result['changed'][] = $name;
		}
	}

	return apply_filters( 'rpress_account_hub_reorder_result', $result, $order_id );
}

/**
 * Modal HTML telling the customer what was added and what was skipped.
 *
 * @param int   $order_id Payment ID.
 * @param array $result   rpress_account_hub_reorder() result.
 * @return string
 */
function rpress_account_hub_reorder_modal_html( $order_id, $result ) {
	$menu_page = rpress_get_option( 'food_items_page', 0 );
	$menu_url  = $menu_page ? get_permalink( $menu_page ) : home_url( '/' );
	$number    = rpress_get_payment_number( $order_id );

	if ( $result['added'] && ! $result['skipped'] ) {
		/* translators: %s: order number. */
		$message = sprintf( __( 'All items from order #%s are in your cart.', 'restropress' ), $number );
	} elseif ( $result['added'] ) {
		/* translators: %s: order number. */
		$message = sprintf( __( 'We added what is still on the menu from order #%s.', 'restropress' ), $number );
	} else {
		/* translators: %s: order number. */
		$message = sprintf( __( 'None of the items from order #%s can be ordered right now.', 'restropress' ), $number );
	}

	ob_start();
	?>
	<div>
		<header class="modal__header modal-header">
			<h2 id="rpressModal-title" class="modal__title modal-title"><?php esc_html_e( 'Reorder', 'restropress' ); ?></h2>
			<button class="modal__close" aria-label="<?php esc_attr_e( 'Close modal', 'restropress' ); ?>" data-micromodal-close></button>
		</header>
		<main class="modal__content modal-body">
			<div class="rpress-order-details">
				<div class="rp-order-section-md-data">
					<div class="rp-detils-content-view rpress-reorder-result">
						<p class="rpress-reorder-message"><?php echo esc_html( $message ); ?></p>
						<?php if ( $result['skipped'] ) : ?>
							<div class="rpress-reorder-skipped" role="status">
								<strong><?php esc_html_e( 'Not added (no longer available):', 'restropress' ); ?></strong>
								<ul>
									<?php foreach ( $result['skipped'] as $name ) : ?>
										<li><?php echo esc_html( $name ); ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
						<?php if ( $result['changed'] ) : ?>
							<p class="rpress-reorder-changed">
								<?php
								/* translators: %s: comma separated item names. */
								echo esc_html( sprintf( __( 'Some add-ons are no longer offered and were left off: %s', 'restropress' ), implode( ', ', $result['changed'] ) ) );
								?>
							</p>
						<?php endif; ?>
						<?php if ( $result['added'] ) : ?>
							<p class="rpress-reorder-price-note"><?php esc_html_e( 'Prices are today\'s menu prices.', 'restropress' ); ?></p>
						<?php endif; ?>
						<a class="btn btn-primary btn-block" href="<?php echo esc_url( $menu_url ); ?>"><?php esc_html_e( 'Add more items', 'restropress' ); ?></a>
						<?php if ( $result['added'] ) : ?>
							<a class="btn btn-primary btn-block" href="<?php echo esc_url( rpress_get_checkout_uri() ); ?>"><?php esc_html_e( 'Checkout Now', 'restropress' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</main>
		<footer class="modal__footer modal-footer"></footer>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * AJAX: reorder. Runs before the core handler and ends the request.
 */
function rpress_account_hub_ajax_reorder() {
	check_ajax_referer( 'show-order-details', 'security' );

	$order_id    = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
	$payment_key = isset( $_REQUEST['payment_key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['payment_key'] ) ) : '';
	if ( ! rpress_account_hub_can_access_order( $order_id, $payment_key ) ) {
		wp_send_json_error(
			array( 'message' => esc_html__( 'You do not have permission to reorder this order.', 'restropress' ) ),
			403
		);
	}

	$result = rpress_account_hub_reorder( $order_id );
	wp_send_json_success(
		array(
			'html'          => rpress_account_hub_reorder_modal_html( $order_id, $result ),
			'added'         => array_values( $result['added'] ),
			'skipped'       => array_values( $result['skipped'] ),
			'cart_quantity' => html_entity_decode( rpress_get_cart_quantity() ),
		)
	);
}
add_action( 'wp_ajax_rpress_reorder', 'rpress_account_hub_ajax_reorder', 5 );
add_action( 'wp_ajax_nopriv_rpress_reorder', 'rpress_account_hub_ajax_reorder', 5 );
add_action( 'rp_ajax_reorder', 'rpress_account_hub_ajax_reorder', 5 );
