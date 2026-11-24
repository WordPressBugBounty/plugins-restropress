<?php
/**
 * Customer orders as cards: shared by the account Orders section, the
 * [order_history] shortcode/block and their "Load more".
 *
 * @package RestroPress
 * @since   3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orders shown per page (first page and each "Load more").
 *
 * @since 3.5.1
 * @return int
 */
function rpress_account_orders_per_page() {
	return max( 1, (int) apply_filters( 'rpress_account_orders_per_page', 10 ) );
}

/**
 * A customer's orders, newest first: every order linked to the customer
 * record (including ones placed as a guest with the same email), or the
 * orders they authored when there is no customer record.
 *
 * @since 3.5.1
 * @param int $user_id User ID.
 * @param int $paged   Page number.
 * @return WP_Query
 */
function rpress_account_orders_query( $user_id, $paged = 1 ) {
	$args = array(
		'post_type'      => 'rpress_payment',
		'post_status'    => 'any',
		'posts_per_page' => rpress_account_orders_per_page(),
		'paged'          => max( 1, (int) $paged ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'author'         => (int) $user_id,
	);
	if ( $user_id && class_exists( 'RPRESS_Customer' ) ) {
		$customer = new RPRESS_Customer( $user_id, true );
		if ( ! empty( $customer->payment_ids ) ) {
			unset( $args['author'] );
			$args['post__in'] = array_map( 'absint', explode( ',', $customer->payment_ids ) );
		}
	}
	/**
	 * Filter the customer order list query.
	 *
	 * @since 3.5.1
	 * @param array $args    WP_Query arguments.
	 * @param int   $user_id User ID.
	 */
	return new WP_Query( apply_filters( 'rpress_account_orders_query_args', $args, $user_id ) );
}

/**
 * Short item summary: "2× Chicken Biryani, 1× Butter Naan" and how many
 * more lines there are. Uses the name stored on the order, so items deleted
 * from the menu still show.
 *
 * @since 3.5.1
 * @param int $payment_id Payment ID.
 * @param int $max        Lines to name.
 * @return array { @type string[] $lines, @type int $more }
 */
function rpress_account_order_summary( $payment_id, $max = 2 ) {
	$cart  = rpress_get_payment_meta_cart_details( $payment_id, true );
	$lines = array();
	foreach ( (array) $cart as $line ) {
		if ( ! is_array( $line ) ) {
			continue;
		}
		$name = function_exists( 'rpress_account_hub_line_name' ) ? rpress_account_hub_line_name( $line ) : ( isset( $line['name'] ) ? $line['name'] : '' );
		$qty  = isset( $line['quantity'] ) ? max( 1, absint( $line['quantity'] ) ) : 1;
		/* translators: 1: quantity, 2: item name. */
		$lines[] = sprintf( __( '%1$s× %2$s', 'restropress' ), number_format_i18n( $qty ), wp_strip_all_tags( $name ) );
	}
	return array(
		'lines' => array_slice( $lines, 0, $max ),
		'more'  => max( 0, count( $lines ) - $max ),
	);
}

/**
 * Print one order card.
 *
 * @since 3.5.1
 * @param int    $payment_id Payment ID.
 * @param string $context    'account' (dashboard Orders) or 'history' ([order_history]).
 */
function rpress_account_order_card( $payment_id, $context = 'account' ) {
	$payment = new RPRESS_Payment( $payment_id );
	if ( ! $payment->ID ) {
		return;
	}
	$status       = sanitize_key( (string) get_post_meta( $payment->ID, '_order_status', true ) );
	$status       = '' === $status ? 'pending' : $status;
	$model        = rpress_order_status_model();
	$phase        = isset( $model[ $status ]['phase'] ) ? $model[ $status ]['phase'] : 'pending';
	$label        = rpress_get_order_status_label( $status );
	$service      = sanitize_key( (string) get_post_meta( $payment->ID, '_rpress_delivery_type', true ) );
	$is_live      = function_exists( 'rpress_account_hub_order_is_live' ) ? rpress_account_hub_order_is_live( $payment->ID ) : ! in_array( $phase, array( 'delivered', 'cancelled' ), true );
	$track_url    = ( $is_live && function_exists( 'rpress_account_hub_order_receipt_url' ) ) ? rpress_account_hub_order_receipt_url( $payment->ID ) : '';
	$can_reorder  = ! $is_live && function_exists( 'rpress_account_hub_reorder_enabled' ) && rpress_account_hub_reorder_enabled();

	rpress_account_template(
		'order-card',
		array(
			'payment'      => $payment,
			'context'      => $context,
			'status_key'   => $status,
			'status_phase' => $phase,
			'status_label' => $label ? $label : ucwords( str_replace( array( '_', '-' ), ' ', $status ) ),
			'service_type' => $service,
			'service_label' => rpress_account_service_label( $service ),
			'summary'      => rpress_account_order_summary( $payment->ID ),
			'is_live'      => $is_live,
			'track_url'    => $track_url,
			'can_reorder'  => $can_reorder,
		)
	);
}

/**
 * Customer-facing service name.
 *
 * @param string $service Service type key.
 * @return string
 */
function rpress_account_service_label( $service ) {
	if ( '' === $service ) {
		return '';
	}
	if ( function_exists( 'rpress_service_label' ) ) {
		$label = rpress_service_label( $service );
		if ( is_string( $label ) && '' !== $label ) {
			return $label;
		}
	}
	return ucwords( str_replace( array( '-', '_' ), ' ', $service ) );
}

/**
 * Print a page of order cards for the current user.
 *
 * @since 3.5.1
 * @param int    $paged   Page number.
 * @param string $context 'account' or 'history'.
 * @return array { @type int $count Cards printed, @type bool $has_more More pages. }
 */
function rpress_account_order_cards( $paged, $context ) {
	$query = rpress_account_orders_query( get_current_user_id(), $paged );
	foreach ( $query->posts as $post ) {
		if ( 'history' === $context ) {
			$payment = new RPRESS_Payment( $post->ID );
			do_action( 'rpress_order_history_row_start', $payment->ID, $payment->payment_meta );
		}
		rpress_account_order_card( $post->ID, $context );
	}
	return array(
		'count'    => count( $query->posts ),
		'total'    => (int) $query->found_posts,
		'has_more' => $paged < (int) $query->max_num_pages,
	);
}

/**
 * "Load more" button under a card list.
 *
 * @since 3.5.1
 * @param string $context 'account' or 'history'.
 */
function rpress_account_orders_load_more_button( $context ) {
	printf(
		'<div class="rpress-orders-more"><button type="button" class="rpress-orders-load-more" data-page="1" data-context="%1$s"><span class="rp-ajax-toggle-text">%2$s</span></button></div>',
		esc_attr( $context ),
		esc_html__( 'Load more orders', 'restropress' )
	);
}

/**
 * The account-hub actions now live on the card itself (one main button by
 * status); keep the hooks for other code but drop the hub's own links there.
 */
function rpress_account_unhook_hub_card_actions() {
	remove_action( 'rpress_order_after_view_details', 'rpress_account_hub_tracking_link', 10 );
	remove_action( 'rpress_user_dashboard_order_actions', 'rpress_account_hub_dashboard_order_actions' );
}
add_action( 'init', 'rpress_account_unhook_hub_card_actions' );
