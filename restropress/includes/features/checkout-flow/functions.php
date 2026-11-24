<?php
/**
 * Checkout flow and order details helpers.
 *
 * @package RestroPress
 * @since   3.5.1
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------- */
/* Checkout: fulfilment first                                                  */
/* -------------------------------------------------------------------------- */

/**
 * Whether checkout shows delivery or pickup details before contact.
 *
 * @since 3.5.1
 * @return bool
 */
function rpress_checkout_fulfilment_first() {
	/**
	 * Filter whether the checkout shows delivery or pickup details first.
	 *
	 * @since 3.5.1
	 * @param bool $first True to show fulfilment before contact.
	 */
	return (bool) apply_filters( 'rpress_checkout_fulfilment_first', true );
}

/**
 * Move the order details card (service, time, address) above contact.
 *
 * Both cards print on rpress_purchase_form_after_user_info (contact at 10,
 * order details at 11). Order details moves to 9. The register form path
 * prints contact on rpress_register_fields_before and order details on
 * rpress_register_fields_after, so order details moves to the start of
 * rpress_register_fields_before there. Runs on init so the AJAX-rendered
 * checkout (gateway switch) gets the same order.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_checkout_flow_reorder_cards() {
	if ( ! function_exists( 'rpress_order_details_fields' ) || ! rpress_checkout_fulfilment_first() ) {
		return;
	}
	if ( 11 === has_action( 'rpress_purchase_form_after_user_info', 'rpress_order_details_fields' ) ) {
		remove_action( 'rpress_purchase_form_after_user_info', 'rpress_order_details_fields', 11 );
		add_action( 'rpress_purchase_form_after_user_info', 'rpress_order_details_fields', 9 );
	}
	if ( false !== has_action( 'rpress_register_fields_after', 'rpress_order_details_fields' ) ) {
		remove_action( 'rpress_register_fields_after', 'rpress_order_details_fields' );
		add_action( 'rpress_register_fields_before', 'rpress_order_details_fields', 9 );
	}
}
add_action( 'init', 'rpress_checkout_flow_reorder_cards', 20 );

/* -------------------------------------------------------------------------- */
/* Checkout: tip slot                                                          */
/* -------------------------------------------------------------------------- */

/**
 * Print the tip slot at the top of the payment step when something fills it.
 *
 * Extensions add their tip picker on rpress_checkout_tip_slot. The slot sits
 * inside the Payment card, above the payment methods, so the tip is chosen
 * next to the money it changes.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_checkout_tip_slot() {
	if ( ! has_action( 'rpress_checkout_tip_slot' ) ) {
		return;
	}
	echo '<div class="rpress-checkout-tip-slot">';
	/**
	 * Print a tip picker in the payment step.
	 *
	 * @since 3.5.1
	 */
	do_action( 'rpress_checkout_tip_slot' );
	echo '</div>';
}
add_action( 'rpress_payment_mode_before_gateways_wrap', 'rpress_checkout_tip_slot', 5 );

/**
 * Move the RestroPress Tips picker into the tip slot.
 *
 * Tips 1.x prints after the contact card (rpress_purchase_form_after_user_info
 * and rpress_purchase_form, priority 12). Its callback is found by method
 * name so core does not need the extension's object.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_checkout_flow_adopt_tips() {
	global $wp_filter;
	$moved = null;
	foreach ( array( 'rpress_purchase_form_after_user_info', 'rpress_purchase_form' ) as $hook ) {
		if ( empty( $wp_filter[ $hook ] ) || empty( $wp_filter[ $hook ]->callbacks[12] ) ) {
			continue;
		}
		foreach ( $wp_filter[ $hook ]->callbacks[12] as $cb ) {
			$fn = $cb['function'];
			if ( is_array( $fn ) && isset( $fn[1] ) && 'rpress_tips_html_checkout' === $fn[1] ) {
				remove_action( $hook, $fn, 12 );
				$moved = $fn;
			}
		}
	}
	if ( $moved && apply_filters( 'rpress_checkout_tips_in_payment_step', true ) ) {
		add_action( 'rpress_checkout_tip_slot', $moved );
	} elseif ( $moved ) {
		add_action( 'rpress_purchase_form_after_user_info', $moved, 12 );
	}
}
add_action( 'init', 'rpress_checkout_flow_adopt_tips', 30 );

/* -------------------------------------------------------------------------- */
/* Assets                                                                      */
/* -------------------------------------------------------------------------- */

/**
 * Whether this request shows an order details (receipt) page.
 *
 * @since 3.5.1
 * @return bool
 */
function rpress_is_order_details_page() {
	if ( is_admin() ) {
		return false;
	}
	if ( function_exists( 'rpress_is_success_page' ) && rpress_is_success_page() ) {
		return true;
	}
	$post = get_post();
	return $post && has_shortcode( (string) $post->post_content, 'rpress_receipt' );
}

/**
 * Enqueue the checkout flow assets on checkout and order details pages.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_checkout_flow_enqueue() {
	if ( is_admin() ) {
		return;
	}
	$on_checkout = function_exists( 'rpress_is_checkout' ) && rpress_is_checkout();
	$on_order    = rpress_is_order_details_page();
	if ( ! $on_checkout && ! $on_order ) {
		return;
	}

	$dir = RP_PLUGIN_DIR . 'assets/features/checkout-flow/';
	$url = plugin_dir_url( RP_PLUGIN_FILE ) . 'assets/features/checkout-flow/';
	$ver = function ( $file ) use ( $dir ) {
		return RP_VERSION . '.' . ( file_exists( $dir . $file ) ? filemtime( $dir . $file ) : '0' );
	};

	$style_deps = array();
	foreach ( array( 'rpress-checkout-ux', 'rpress-checkout', 'rpress-styles' ) as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) ) {
			$style_deps[] = $handle;
			break;
		}
	}
	wp_enqueue_style( 'rpress-checkout-flow', $url . 'checkout-flow.css', $style_deps, $ver( 'checkout-flow.css' ) );

	if ( ! $on_checkout ) {
		return;
	}
	$script_deps = array( 'jquery' );
	foreach ( array( 'rp-checkout-ui', 'rpress-checkout-ux' ) as $handle ) {
		if ( wp_script_is( $handle, 'registered' ) ) {
			$script_deps[] = $handle;
		}
	}
	wp_enqueue_script( 'rpress-checkout-flow', $url . 'checkout-flow.js', $script_deps, $ver( 'checkout-flow.js' ), true );
	wp_localize_script(
		'rpress-checkout-flow',
		'rpress_checkout_flow',
		array(
			'summary_cards' => (bool) apply_filters( 'rpress_checkout_summary_cards', true ),
			'today'         => wp_date( 'Y-m-d', time(), rpress_get_wp_timezone() ),
			'i18n'          => array(
				'change'        => __( 'Change', 'restropress' ),
				'done'          => __( 'Done', 'restropress' ),
				'when'          => __( 'When', 'restropress' ),
				'address'       => __( 'Delivery address', 'restropress' ),
				'today'         => __( 'Today', 'restropress' ),
				'place_order'   => __( 'Place order', 'restropress' ),
				'change_when'    => __( 'Change service and time', 'restropress' ),
				'change_address' => __( 'Change delivery address', 'restropress' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rpress_checkout_flow_enqueue', 40 );

/* -------------------------------------------------------------------------- */
/* Order details                                                               */
/* -------------------------------------------------------------------------- */

/**
 * Body class on order details pages, used to hide the theme's page title so
 * the page has one heading ("Order #1234 placed").
 *
 * @since 3.5.1
 * @param string[] $classes Body classes.
 * @return string[]
 */
function rpress_order_details_body_class( $classes ) {
	/**
	 * Filter whether order details pages hide the theme's page title.
	 *
	 * @since 3.5.1
	 * @param bool $hide True to hide it.
	 */
	if ( rpress_is_order_details_page() && apply_filters( 'rpress_order_details_hide_page_title', true ) ) {
		$classes[] = 'rp-order-details-page';
	}
	return $classes;
}
add_filter( 'body_class', 'rpress_order_details_body_class' );

/**
 * Time format the store uses for service times.
 *
 * @since 3.5.1
 * @return string PHP date format.
 */
function rpress_store_time_format() {
	return '24hrs' === rpress_get_option( 'store_time_format' ) ? 'H:i' : 'g:i a';
}

/**
 * When an order is expected: delivery arrival or pickup ready time.
 *
 * ASAP orders: placed time plus the longest wait in the stored ASAP text
 * ("ASAP (ready in 15-20 min)" gives 20), else the store's preparation time.
 * Scheduled orders: the requested date and time.
 *
 * @since 3.5.1
 * @param int $payment_id Payment ID.
 * @return array|null { timestamp, label, time, date, asap } or null when unknown.
 */
function rpress_get_order_eta( $payment_id ) {
	$payment_id = absint( $payment_id );
	$time       = trim( (string) rpress_get_payment_meta( $payment_id, '_rpress_delivery_time', true ) );
	$date       = trim( (string) rpress_get_payment_meta( $payment_id, '_rpress_delivery_date', true ) );
	$service    = (string) rpress_get_payment_meta( $payment_id, '_rpress_delivery_type', true );
	$is_asap    = '' === $time || false !== stripos( $time, 'ASAP' );
	$timestamp  = 0;

	if ( $is_asap ) {
		$placed = get_post_field( 'post_date_gmt', $payment_id );
		$placed = $placed && '0000-00-00 00:00:00' !== $placed ? strtotime( $placed . ' UTC' ) : 0;
		if ( $placed ) {
			$minutes = 0;
			if ( preg_match( '/(\d+)\s*(?:-|–|to)\s*(\d+)\s*min/i', $time, $m ) ) {
				$minutes = (int) $m[2];
			} elseif ( preg_match( '/(\d+)\s*min/i', $time, $m ) ) {
				$minutes = (int) $m[1];
			}
			if ( $minutes <= 0 ) {
				$minutes = absint( rpress_get_option( 'prep_time', 0 ) );
			}
			/**
			 * Filter the minutes from order to arrival (delivery) or ready (pickup) for ASAP orders.
			 *
			 * @since 3.5.1
			 * @param int    $minutes    Minutes.
			 * @param int    $payment_id Payment ID.
			 * @param string $service    Service type.
			 */
			$minutes = (int) apply_filters( 'rpress_order_eta_minutes', $minutes, $payment_id, $service );
			if ( $minutes > 0 ) {
				$timestamp = $placed + $minutes * MINUTE_IN_SECONDS;
			}
		}
	} elseif ( '' !== $date ) {
		$timestamp = (int) rpress_get_wp_timestamp( $date . ' ' . $time );
	}

	if ( $timestamp <= 0 ) {
		return null;
	}

	$tz       = rpress_get_wp_timezone();
	$time_str = wp_date( rpress_store_time_format(), $timestamp, $tz );
	$is_today = wp_date( 'Y-m-d', $timestamp, $tz ) === wp_date( 'Y-m-d', time(), $tz );
	$date_str = $is_today ? '' : wp_date( _x( 'D j M', 'order arrival date format', 'restropress' ), $timestamp, $tz );
	$when     = $is_today ? $time_str : sprintf( /* translators: 1: date, 2: time */ _x( '%1$s, %2$s', 'order arrival date and time', 'restropress' ), $date_str, $time_str );

	$label = 'delivery' === $service
		/* translators: %s: time, e.g. 7:55 pm */
		? sprintf( __( 'Arrives around %s', 'restropress' ), $when )
		/* translators: %s: time, e.g. 7:55 pm */
		: sprintf( __( 'Ready around %s', 'restropress' ), $when );

	return apply_filters(
		'rpress_order_eta',
		array(
			'timestamp' => $timestamp,
			'label'     => $label,
			'time'      => $when,
			'date'      => $date_str,
			'asap'      => $is_asap,
		),
		$payment_id
	);
}

/**
 * Whether the order details page should tell a guest the link was emailed.
 *
 * @since 3.5.1
 * @param int $payment_id Payment ID.
 * @return bool
 */
function rpress_order_details_show_guest_note( $payment_id ) {
	$user_id = (int) rpress_get_payment_user_id( $payment_id );
	$show    = $user_id <= 0 && ! is_user_logged_in() && '' !== (string) rpress_get_payment_user_email( $payment_id );
	return (bool) apply_filters( 'rpress_order_details_show_guest_note', $show, $payment_id );
}
