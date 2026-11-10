<?php
/**
 * Mobile ordering: store card, enqueue and the status refresh endpoint.
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the mobile ordering layer is on.
 *
 * Off for stores still on the legacy menu (old_ui_ux): the layer styles the
 * redesigned .rpress-menu-v2 markup only.
 *
 * @since 3.5
 * @return bool
 */
function rpress_mobile_ordering_enabled() {
	$enabled = empty( rpress_get_option( 'old_ui_ux' ) );
	/**
	 * Filter whether the 3.5 mobile ordering layer loads on the menu page.
	 *
	 * @since 3.5
	 * @param bool $enabled Whether it loads.
	 */
	return (bool) apply_filters( 'rpress_mobile_ordering_enabled', $enabled );
}

/**
 * Legacy menu (old_ui_ux): only the item popup part of the layer loads
 * (Required / Optional labels, phone sheet), so legacy stores get the same
 * popup as the current flow without the redesigned menu chrome.
 *
 * @since 3.5
 * @return bool
 */
function rpress_mobile_ordering_popup_only() {
	$popup_only = ! rpress_mobile_ordering_enabled() && ! empty( rpress_get_option( 'old_ui_ux' ) );
	/**
	 * Filter whether legacy menus get the 3.5 item popup.
	 *
	 * @since 3.5
	 * @param bool $popup_only Whether to load the popup part.
	 */
	return (bool) apply_filters( 'rpress_mobile_ordering_popup_only', $popup_only );
}

/**
 * Whether the current request is a [fooditems] menu page.
 *
 * Same test core uses for the menu stylesheet. The block storefront has its
 * own layout and is left alone.
 *
 * @since 3.5
 * @return bool
 */
function rpress_mobile_ordering_is_menu_page() {
	if ( is_admin() ) {
		return false;
	}
	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return false;
	}
	return has_shortcode( (string) $post->post_content, 'fooditems' )
		|| ( function_exists( 'has_block' ) && has_block( 'rpress/food-menu', $post ) );
}

/**
 * The service the customer is ordering for (cookie context, else default).
 *
 * @since 3.5
 * @param string $requested Optional service from a request.
 * @return string
 */
function rpress_mobile_ordering_service( $requested = '' ) {
	$requested = sanitize_key( (string) $requested );
	if ( $requested && function_exists( 'rpress_is_service_enabled' ) && rpress_is_service_enabled( $requested ) ) {
		return $requested;
	}
	if ( function_exists( 'rpress_get_service_context' ) ) {
		$context = rpress_get_service_context();
		if ( ! empty( $context['service_type'] ) ) {
			return (string) $context['service_type'];
		}
	}
	return function_exists( 'rpress_get_default_enabled_service' ) ? (string) rpress_get_default_enabled_service() : 'delivery';
}

/**
 * Format a store time ("10:00", a timestamp) the way the store shows times.
 *
 * @since 3.5
 * @param int $timestamp Unix timestamp.
 * @return string
 */
function rpress_mobile_ordering_format_time( $timestamp ) {
	$format = function_exists( 'rpress_service_hours_time_format' ) ? rpress_service_hours_time_format() : get_option( 'time_format', 'g:i a' );
	return wp_date( $format, (int) $timestamp );
}

/**
 * When the store takes orders again for a service: "today at 10:00",
 * "tomorrow at 10:00" or "31 October at 10:00". Empty when unknown.
 *
 * @since 3.5
 * @param string $service Service slug.
 * @return string
 */
function rpress_mobile_ordering_next_open( $service ) {
	if ( ! function_exists( 'rpress_service_hours_engine_enabled' ) || ! rpress_service_hours_engine_enabled() ) {
		return '';
	}
	$engine = rpress_service_hours();
	$next   = $engine->next_available_slot( (string) $service );
	if ( ! $next ) {
		return '';
	}
	$time  = rpress_mobile_ordering_format_time( $next->getTimestamp() );
	$today = $engine->date( $engine->now() );
	$day   = $engine->date( $next );
	if ( $day == $today ) { // phpcs:ignore Universal.Operators.StrictComparisons -- DateTime value comparison.
		/* translators: %s: time */
		return sprintf( __( 'today at %s', 'restropress' ), $time );
	}
	if ( $day == $today->modify( '+1 day' ) ) { // phpcs:ignore Universal.Operators.StrictComparisons -- DateTime value comparison.
		/* translators: %s: time */
		return sprintf( __( 'tomorrow at %s', 'restropress' ), $time );
	}
	/* translators: 1: date, 2: time */
	return sprintf( __( '%1$s at %2$s', 'restropress' ), wp_date( get_option( 'date_format' ), $next->getTimestamp() ), $time );
}

/**
 * Everything the store card shows, for one service.
 *
 * @since 3.5
 * @param string $service Service slug.
 * @return array {
 *     @type string $service   Service slug.
 *     @type bool   $open      Taking orders for this service now.
 *     @type string $status    "Open until 23:00", "Closed", ...
 *     @type string $next_open "tomorrow at 10:00" when closed, else ''.
 *     @type bool   $can_schedule Whether a closed store takes orders for later.
 *     @type array  $chips     Short facts: ready time, fee, minimum.
 * }
 */
function rpress_mobile_ordering_store_state( $service ) {
	$service     = (string) $service;
	$always_open = ! empty( rpress_get_option( 'enable_always_open' ) );
	$open        = (bool) rpress_is_store_open( $service );

	$status = __( 'Closed', 'restropress' );
	if ( $open && $always_open ) {
		$status = __( 'Open now', 'restropress' );
	} elseif ( $open ) {
		$status = __( 'Open now', 'restropress' );
		$hours  = function_exists( 'rpress_get_store_hours_for_date' ) ? rpress_get_store_hours_for_date( rpress_get_wp_now()->format( 'Y-m-d' ) ) : array();
		if ( ! empty( $hours['close'] ) && empty( $hours['closed'] ) ) {
			$close = rpress_get_wp_timestamp( rpress_get_wp_now()->format( 'Y-m-d' ) . ' ' . $hours['close'] );
			if ( $close ) {
				/* translators: %s: closing time */
				$status = sprintf( __( 'Open until %s', 'restropress' ), rpress_mobile_ordering_format_time( $close ) );
			}
		}
	}

	$next_open = $open ? '' : rpress_mobile_ordering_next_open( $service );

	$chips = array();
	$prep  = (int) rpress_get_option( 'prep_time', 0 );
	if ( $prep > 0 ) {
		$chips[] = 'pickup' === $service
			/* translators: %d: preparation minutes */
			? sprintf( __( 'Ready in ~%d min', 'restropress' ), $prep )
			/* translators: %d: preparation minutes */
			: sprintf( __( 'Prepared in ~%d min', 'restropress' ), $prep );
	}
	if ( 'delivery' === $service ) {
		$flat_fee = rpress_get_option( 'flat_delivery_fee', '' );
		if ( '' !== $flat_fee && is_numeric( $flat_fee ) && (float) $flat_fee > 0 ) {
			/* translators: %s: formatted delivery fee */
			$chips[] = sprintf( __( '%s delivery fee', 'restropress' ), rpress_currency_filter( rpress_format_amount( $flat_fee ) ) );
		}
	}
	if ( function_exists( 'rpress_checkout_ux_minimum_order' ) ) {
		$minimum = rpress_checkout_ux_minimum_order( $service, 0 );
		if ( ! empty( $minimum['enabled'] ) ) {
			/* translators: %s: formatted minimum order */
			$chips[] = sprintf( __( 'Min. order %s', 'restropress' ), $minimum['minimum_formatted'] );
		}
	}

	$state = array(
		'service'      => $service,
		'open'         => $open,
		'status'       => $status,
		'next_open'    => $next_open,
		'can_schedule' => ! $open && '' !== $next_open && function_exists( 'rpress_is_preorder_enabled' ) && rpress_is_preorder_enabled( $service ),
		'chips'        => $chips,
	);

	/**
	 * Filter the store card state (status text, chips, scheduling).
	 *
	 * @since 3.5
	 * @param array  $state   Card state.
	 * @param string $service Service slug.
	 */
	return apply_filters( 'rpress_mobile_ordering_store_state', $state, $service );
}

/**
 * Store card markup.
 *
 * @since 3.5
 * @param string $service Service slug.
 * @return string
 */
function rpress_mobile_ordering_store_card_html( $service ) {
	$state = rpress_mobile_ordering_store_state( $service );
	$label = function_exists( 'rpress_service_label' ) ? (string) rpress_service_label( $state['service'] ) : $state['service'];

	ob_start();
	?>
	<section class="rp-mo-store<?php echo $state['open'] ? ' is-open' : ' is-closed'; ?>" data-service="<?php echo esc_attr( $state['service'] ); ?>" aria-label="<?php esc_attr_e( 'Store status', 'restropress' ); ?>">
		<div class="rp-mo-store-head">
			<h2 class="rp-mo-store-name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
			<span class="rp-mo-store-status"><span class="rp-mo-dot" aria-hidden="true"></span><?php echo esc_html( $state['status'] ); ?></span>
		</div>
		<?php if ( $state['chips'] ) : ?>
			<ul class="rp-mo-store-chips">
				<?php foreach ( $state['chips'] as $chip ) : ?>
					<li><?php echo esc_html( $chip ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( ! $state['open'] ) : ?>
			<div class="rp-mo-closed" role="status">
				<p>
					<?php
					if ( $state['next_open'] ) {
						/* translators: 1: service name, 2: when the store opens, for example "tomorrow at 10:00" */
						echo esc_html( sprintf( __( '%1$s is closed right now. We open %2$s.', 'restropress' ), $label, $state['next_open'] ) );
					} else {
						echo esc_html( wp_strip_all_tags( rpress_store_closed_message( $state['service'] ) ) );
					}
					?>
				</p>
				<?php if ( $state['can_schedule'] ) : ?>
					<button type="button" class="rp-mo-schedule"><?php esc_html_e( 'Schedule for later', 'restropress' ); ?></button>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Print the store card above the menu.
 *
 * @since 3.5
 * @return void
 */
function rpress_mobile_ordering_render_store_card() {
	if ( ! rpress_mobile_ordering_enabled() ) {
		return;
	}
	$pack = function_exists( 'rpress_get_active_template_pack' ) ? rpress_get_active_template_pack() : array();
	/**
	 * Whether to show the store card above the menu. Modern only by default,
	 * so the Classic pack keeps its 3.4 look.
	 *
	 * @since 3.5
	 * @param bool   $show Show the card.
	 * @param string $pack Active template pack ID.
	 */
	$pack_id = isset( $pack['id'] ) ? $pack['id'] : 'classic';
	if ( ! apply_filters( 'rpress_mobile_ordering_show_store_card', 'modern' === $pack_id, $pack_id ) ) {
		return;
	}
	echo rpress_mobile_ordering_store_card_html( rpress_mobile_ordering_service() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while built.
}
add_action( 'rpress_menu_page_top', 'rpress_mobile_ordering_render_store_card', 5 );

/**
 * The card replaces the Modern pack's store header band (same facts plus
 * the closed state), so only one header shows.
 *
 * @since 3.5
 * @return void
 */
function rpress_mobile_ordering_replace_pack_header() {
	if ( rpress_mobile_ordering_enabled() && class_exists( 'RPRESS_Template_Packs' ) ) {
		remove_action( 'rpress_menu_page_top', array( 'RPRESS_Template_Packs', 'render_modern_store_header' ) );
	}
}
add_action( 'init', 'rpress_mobile_ordering_replace_pack_header', 99 );

/**
 * AJAX: fresh store card. Page caches can serve a menu page rendered hours
 * ago, and the customer can switch service or time, so the card refreshes
 * itself. Public, read-only data: no nonce, so a cached page still works.
 *
 * @since 3.5
 * @return void
 */
function rpress_mobile_ordering_ajax_store() {
	$service = isset( $_REQUEST['service'] ) ? sanitize_key( wp_unslash( $_REQUEST['service'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only.
	wp_send_json_success( array( 'html' => rpress_mobile_ordering_store_card_html( rpress_mobile_ordering_service( $service ) ) ) );
}
add_action( 'wp_ajax_rpress_mobile_ordering_store', 'rpress_mobile_ordering_ajax_store' );
add_action( 'wp_ajax_nopriv_rpress_mobile_ordering_store', 'rpress_mobile_ordering_ajax_store' );

/**
 * Enqueue the layer on menu pages, after the menu stylesheet and the
 * frontend script so it can build on both.
 *
 * @since 3.5
 * @return void
 */
function rpress_mobile_ordering_enqueue() {
	if ( ( ! rpress_mobile_ordering_enabled() && ! rpress_mobile_ordering_popup_only() ) || ( ! rpress_mobile_ordering_is_menu_page() && ! rpress_mobile_ordering_is_popup_page() ) ) {
		return;
	}
	$dir = RP_PLUGIN_DIR . 'assets/features/mobile-ordering/';
	$url = plugin_dir_url( RP_PLUGIN_FILE ) . 'assets/features/mobile-ordering/';
	$ver = static function ( $file ) use ( $dir ) {
		return RP_VERSION . '.' . ( file_exists( $dir . $file ) ? filemtime( $dir . $file ) : '0' );
	};

	$style_deps = array();
	foreach ( array( 'rpress-menu', 'rpress-checkout-ux', 'rpress-styles' ) as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) ) {
			$style_deps[] = $handle;
		}
	}
	$script_deps = array( 'jquery' );
	foreach ( array( 'rp-frontend', 'rpress-checkout-ux' ) as $handle ) {
		if ( wp_script_is( $handle, 'registered' ) ) {
			$script_deps[] = $handle;
		}
	}

	wp_enqueue_style( 'rpress-mobile-ordering', $url . 'mobile-ordering.css', $style_deps, $ver( 'mobile-ordering.css' ) );
	wp_enqueue_script( 'rpress-mobile-ordering', $url . 'mobile-ordering.js', $script_deps, $ver( 'mobile-ordering.js' ), true );
	wp_localize_script(
		'rpress-mobile-ordering',
		'rpress_mobile_ordering',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			// Legacy menu: set up the item popup only.
			'popup_only' => rpress_mobile_ordering_enabled() ? '' : '1',
			// Same format as the time row (#deliveryDate), so phones can drop
			// "today" and say "Tomorrow" instead of a bare date.
			'today'    => function_exists( 'rpress_format_service_date' ) ? (string) rpress_format_service_date( rpress_get_wp_now()->format( 'Y-m-d' ) ) : '',
			'tomorrow' => function_exists( 'rpress_format_service_date' ) ? (string) rpress_format_service_date( rpress_get_wp_now()->modify( '+1 day' )->format( 'Y-m-d' ) ) : '',
			'i18n'    => array(
				'view_cart'   => __( 'View cart', 'restropress' ),
				/* translators: %d: number of items */
				'items_one'   => __( '%d item', 'restropress' ),
				/* translators: %d: number of items */
				'items_many'  => __( '%d items', 'restropress' ),
				'search'      => __( 'Search menu', 'restropress' ),
				'close'       => __( 'Close', 'restropress' ),
				'required'    => __( 'Required', 'restropress' ),
				'optional'    => __( 'Optional', 'restropress' ),
				/* translators: %d: how many options can be picked */
				'pick_up_to'  => __( 'up to %d', 'restropress' ),
				/* translators: %d: how many options must be picked */
				'pick_n'      => __( 'pick %d', 'restropress' ),
				'decrease'    => __( 'Remove one', 'restropress' ),
				'increase'    => __( 'Add one more', 'restropress' ),
				/* translators: %d: quantity in the cart */
				'in_cart'     => __( '%d in cart', 'restropress' ),
				'tomorrow'    => __( 'Tomorrow', 'restropress' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rpress_mobile_ordering_enqueue', 40 );

/**
 * Sheet classes on <body> from the first paint. Added by the script only, the
 * cart sheet would switch from the old side drawer to the sheet after load
 * and visibly slide down across the menu on phones.
 *
 * @since 3.5
 * @param array $classes Body classes.
 * @return array
 */
function rpress_mobile_ordering_body_class( $classes ) {
	if ( rpress_mobile_ordering_enabled() && rpress_mobile_ordering_is_menu_page() ) {
		$classes[] = 'rp-mo-sheet-cart';
		$classes[] = 'rp-mo-sheet-item';
		if ( rpress_mobile_ordering_primary_is_dark() ) {
			$classes[] = 'rp-mo-dark-primary';
		}
	} elseif ( rpress_mobile_ordering_enabled() && rpress_mobile_ordering_is_popup_page() ) {
		$classes[] = 'rp-mo-sheet-item';
	} elseif ( rpress_mobile_ordering_popup_only() && ( rpress_mobile_ordering_is_menu_page() || rpress_mobile_ordering_is_popup_page() ) ) {
		$classes[] = 'rp-mo-sheet-item';
	}
	return $classes;
}

/**
 * Whether the store's primary colour is too dark to read on the dark Modern
 * cart bar (contrast under 3:1 against the near-black bar), e.g. #121212.
 * The bar's count badge then falls back to white.
 *
 * @since 3.5
 * @return bool
 */
function rpress_mobile_ordering_primary_is_dark() {
	$default = '#ED5575';
	$primary = sanitize_hex_color( rpress_get_option( 'primary_color', $default ) );
	if ( ! $primary || strtoupper( $primary ) === $default ) {
		$pack = function_exists( 'rpress_get_active_template_pack' ) ? rpress_get_active_template_pack() : array();
		if ( ! empty( $pack['tokens']['primary'] ) && sanitize_hex_color( $pack['tokens']['primary'] ) ) {
			$primary = sanitize_hex_color( $pack['tokens']['primary'] );
		}
	}
	if ( ! $primary ) {
		return false;
	}
	$hex = ltrim( $primary, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$lum = 0;
	foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $weight ) {
		$c    = hexdec( substr( $hex, $i * 2, 2 ) ) / 255;
		$c    = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		$lum += $weight * $c;
	}
	// The bar ink (#000F37) has a luminance of about 0.006.
	return ( $lum + 0.05 ) / 0.056 < 3;
}

/**
 * Pages outside the menu that open the item popup (checkout upsell, block
 * storefront): they get the popup labels and the phone bottom sheet, nothing else.
 *
 * @since 3.5
 * @return bool
 */
function rpress_mobile_ordering_is_popup_page() {
	if ( is_admin() ) {
		return false;
	}
	if ( function_exists( 'rpress_is_checkout' ) && rpress_is_checkout()
		&& function_exists( 'rpress_checkout_upsell_is_active' ) && rpress_checkout_upsell_is_active() ) {
		return true;
	}
	// Block storefront pages open the same item popup.
	$post = get_post();
	return $post instanceof WP_Post && function_exists( 'rpress_block_storefront_in_post' ) && rpress_block_storefront_in_post( $post );
}
add_filter( 'body_class', 'rpress_mobile_ordering_body_class' );
