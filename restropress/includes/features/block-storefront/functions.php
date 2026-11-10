<?php
/**
 * Block storefront data helpers.
 *
 * Every number the storefront blocks show comes from the same server logic
 * the classic [fooditems] storefront uses: menu prices go through the tax
 * class aware display rules, cart lines and totals through RPRESS_Cart, and
 * the open/closed state through the slot engine (rpress_is_store_open()).
 *
 * @package     RPRESS
 * @subpackage  Features/BlockStorefront
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Interactivity API store namespace shared by every storefront block.
 */
if ( ! defined( 'RPRESS_STOREFRONT_NAMESPACE' ) ) {
	define( 'RPRESS_STOREFRONT_NAMESPACE', 'restropress' );
}

/**
 * Block names of the composable storefront.
 *
 * @since 3.5
 * @return string[] Folder name under blocks/ => block name.
 */
function rpress_block_storefront_blocks() {
	return array(
		'category-nav' => 'rpress/category-nav',
		'item-grid'    => 'rpress/item-grid',
		'cart-panel'   => 'rpress/cart-panel',
		'service-bar'  => 'rpress/service-bar',
		'menu-search'  => 'rpress/menu-search',
	);
}

/**
 * Whether the Interactivity API is available (WordPress 6.5+).
 *
 * Without it the blocks fall back to the classic shortcode storefront.
 *
 * @since 3.5
 * @return bool
 */
function rpress_block_storefront_supported() {
	return (bool) apply_filters(
		'rpress_block_storefront_supported',
		function_exists( 'wp_interactivity_state' ) && function_exists( 'wp_register_script_module' )
	);
}

/**
 * Whether a post renders the block storefront (any storefront block, the
 * storefront pattern, or the storefront page template).
 *
 * @since 3.5
 * @param WP_Post|int|null $post Post.
 * @return bool
 */
function rpress_block_storefront_in_post( $post = null ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return false;
	}
	$content = (string) $post->post_content;
	if ( false !== strpos( $content, 'restropress/storefront' ) ) {
		return true;
	}
	if ( function_exists( 'has_block' ) ) {
		foreach ( rpress_block_storefront_blocks() as $block_name ) {
			if ( has_block( $block_name, $post ) ) {
				return true;
			}
		}
	}
	if ( 'page' === $post->post_type && 'rpress-storefront' === get_page_template_slug( $post ) ) {
		return true;
	}
	return false;
}

/**
 * Lowercase, accent-free text used by the live menu search. The view module
 * normalizes the query the same way.
 *
 * @since 3.5
 * @param string $text Text.
 * @return string
 */
function rpress_block_storefront_normalize( $text ) {
	$text = remove_accents( wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) );
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	$text = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );
	return trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
}

/**
 * A food item's menu price as plain text, honoring the tax display settings
 * and the item's tax class (same rules as templates/list/content-title.php).
 *
 * @since 3.5
 * @param int $fooditem_id Food item ID.
 * @return string Formatted price, or a "low - high" range for variable prices.
 */
function rpress_block_storefront_price_text( $fooditem_id ) {
	$fooditem_id = absint( $fooditem_id );
	if ( rpress_has_variable_prices( $fooditem_id ) ) {
		$range = rpress_price_range( $fooditem_id );
		$range = str_replace( array( '&nbsp;', '&ndash;' ), array( ' ', '-' ), (string) $range );
		return trim( html_entity_decode( wp_strip_all_tags( $range ), ENT_QUOTES, 'UTF-8' ) );
	}

	$price       = (float) get_post_meta( $fooditem_id, 'rpress_price', true );
	$rate        = (float) rpress_get_fooditem_tax_percent( $fooditem_id );
	$include_tax = rpress_get_option( 'prices_include_tax', true );
	$tax_display = rpress_get_option( 'tax_item', true );

	if ( 'yes' === $include_tax && 'exc_tax' === $tax_display ) {
		$price = $price - ( $price - ( $price / ( ( $rate / 100 ) + 1 ) ) );
	} elseif ( 'no' === $include_tax && 'inc_tax' === $tax_display ) {
		$price = $price + ( $price * ( $rate / 100 ) );
	}

	$text = rpress_currency_filter( rpress_format_amount( $price ) );
	return (string) apply_filters( 'rpress_block_storefront_price_text', html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ), $fooditem_id, $price );
}

/**
 * Format an amount like the cart does, decoded for use as plain text.
 *
 * @since 3.5
 * @param float $amount Amount.
 * @return string
 */
function rpress_block_storefront_money( $amount ) {
	return html_entity_decode( (string) rpress_currency_filter( rpress_format_amount( (float) $amount ) ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Store open/closed state for the current service, from the slot engine.
 *
 * @since 3.5
 * @return array { open: bool, message: string, serviceType: string }
 */
function rpress_block_storefront_store_state() {
	$service_type = function_exists( 'rpress_get_service_context' ) ? '' : rpress_get_default_enabled_service();
	if ( function_exists( 'rpress_get_service_context' ) ) {
		$context      = rpress_get_service_context();
		$service_type = (string) $context['service_type'];
	}
	$open    = function_exists( 'rpress_is_store_open' ) ? (bool) rpress_is_store_open( $service_type ) : true;
	$message = '';
	if ( ! $open ) {
		$message = (string) rpress_store_closed_message( $service_type );
		if ( function_exists( 'rpress_service_hours_closed_message' ) ) {
			$message = (string) rpress_service_hours_closed_message( $message, $service_type );
		}
	}
	return array(
		'open'        => $open,
		'message'     => wp_strip_all_tags( $message ),
		'serviceType' => $service_type,
	);
}

/**
 * Cart state for the Interactivity store: lines, totals and labels.
 *
 * Built from RPRESS_Cart so the panel always matches the classic sidebar
 * cart and checkout. Also returned by the rpress_storefront_cart AJAX action
 * after any cart change.
 *
 * @since 3.5
 * @return array
 */
function rpress_block_storefront_cart_state() {
	$contents = rpress_get_cart_contents();
	$items    = array();

	if ( is_array( $contents ) ) {
		foreach ( $contents as $cart_key => $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}
			$fooditem_id = absint( $item['id'] );
			$quantity    = max( 1, absint( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) );
			$price_id    = rpress_get_cart_item_price_id( $item );
			$unit        = (float) rpress_get_cart_item_price( $fooditem_id, $item, array(), $price_id, false, isset( $item['price'] ) ? $item['price'] : '' );
			$name        = wp_strip_all_tags( html_entity_decode( (string) rpress_get_cart_item_name( $item ), ENT_QUOTES, 'UTF-8' ) );

			$addons      = array();
			$addon_total = 0.0;
			if ( ! empty( $item['addon_items'] ) && is_array( $item['addon_items'] ) ) {
				foreach ( $item['addon_items'] as $addon ) {
					if ( ! is_array( $addon ) || empty( $addon['quantity'] ) || empty( $addon['addon_id'] ) ) {
						continue;
					}
					$term = get_term_by( 'id', absint( $addon['addon_id'] ), 'addon_category' );
					if ( $term && ! is_wp_error( $term ) ) {
						$addons[] = $term->name;
					}
					$addon_total += (float) RPRESS()->cart->get_addon_price( $addon['addon_id'], $item, isset( $addon['price'] ) ? $addon['price'] : 0 );
				}
			}
			if ( ! empty( $item['instruction'] ) ) {
				$addons[] = sanitize_text_field( $item['instruction'] );
			}

			$key     = (string) $cart_key;
			$items[] = array(
				'key'           => $key,
				'id'            => $fooditem_id,
				'name'          => $name,
				'options'       => implode( ', ', $addons ),
				'hasOptions'    => ! empty( $addons ),
				'quantity'      => $quantity,
				'lineTotal'     => rpress_block_storefront_money( ( $unit + $addon_total ) * $quantity ),
				/* translators: %s: food item name */
				'decreaseLabel' => sprintf( __( 'Decrease quantity of %s', 'restropress' ), $name ),
				/* translators: %s: food item name */
				'increaseLabel' => sprintf( __( 'Increase quantity of %s', 'restropress' ), $name ),
				/* translators: 1: quantity, 2: food item name */
				'quantityLabel' => sprintf( __( 'Quantity %1$d, %2$s', 'restropress' ), $quantity, $name ),
			);
		}
	}

	// Count units, not lines: two wraps read as "2 items" on the cart badge.
	$count    = (int) array_sum( wp_list_pluck( $items, 'quantity' ) );
	$show_tax = rpress_use_taxes() && (float) rpress_get_cart_tax() > 0;

	// Fees (pickup, delivery, extras) sit between subtotal and total, as in the sidebar cart.
	$fees = array();
	if ( $count > 0 && rpress_cart_has_fees() ) {
		foreach ( rpress_get_cart_fees() as $fee_id => $fee ) {
			if ( ! apply_filters( 'rpress_cart_sidebar_render_fee', true, $fee_id, $fee ) ) {
				continue;
			}
			$fees[] = array(
				'key'    => (string) $fee_id,
				'label'  => ! empty( $fee['label'] ) ? wp_strip_all_tags( (string) $fee['label'] ) : __( 'Fee', 'restropress' ),
				'amount' => rpress_block_storefront_money( isset( $fee['amount'] ) ? (float) $fee['amount'] : 0 ),
			);
		}
	}

	$state = array(
		'items'      => $items,
		'count'      => $count,
		'hasItems'   => $count > 0,
		/* translators: %d: number of items in the cart */
		'countLabel' => sprintf( _n( '%d item', '%d items', $count, 'restropress' ), $count ),
		'subtotal'   => rpress_block_storefront_money( rpress_get_cart_subtotal() ),
		'fees'       => $fees,
		'tax'        => $show_tax ? rpress_block_storefront_money( rpress_get_cart_tax() ) : '',
		'showTax'    => $show_tax,
		'taxLabel'   => wp_strip_all_tags( (string) rpress_get_tax_name() ),
		'total'      => rpress_block_storefront_money( rpress_get_cart_total() ),
	);

	return apply_filters( 'rpress_block_storefront_cart_state', $state );
}

/**
 * Seed the shared Interactivity store once per request.
 *
 * Called by every block render; wp_interactivity_state() merges, and the
 * values are identical for each call, so the order blocks render in does
 * not matter.
 *
 * @since 3.5
 * @return void
 */
function rpress_block_storefront_seed_state() {
	static $seeded = false;
	if ( $seeded || ! function_exists( 'wp_interactivity_state' ) ) {
		return;
	}
	$seeded = true;

	wp_interactivity_state(
		RPRESS_STOREFRONT_NAMESPACE,
		array(
			'query'          => '',
			'activeCategory' => '',
			'filterCategory' => '',
			'announcement'   => '',
			'busy'           => false,
			'error'          => '',
			'cart'           => rpress_block_storefront_cart_state(),
			'store'          => rpress_block_storefront_store_state(),
			'ajaxUrl'        => rpress_get_ajax_url(),
			'checkoutUrl'    => rpress_get_checkout_uri(),
			'nonces'         => array(
				'quantity' => wp_create_nonce( 'update-cart-item' ),
				'checkout' => wp_create_nonce( 'proceed-checkout' ),
			),
			'i18n'           => array(
				/* translators: 1: number of items, 2: cart total */
				'cartUpdated' => __( 'Cart updated: %1$s, total %2$s.', 'restropress' ),
				'cartError'   => __( 'Could not update the cart. Please try again.', 'restropress' ),
				/* translators: %s: search term */
				'noResults'   => __( 'No menu items match "%s".', 'restropress' ),
			),
		)
	);

	// Server-side derived state, so the first paint already marks the active
	// category ("All" in filter mode). Only from WordPress 6.9, which tells the
	// client to keep its own getters for closures (derivedStateClosures);
	// earlier versions would serialize the closure over the client getter.
	if ( function_exists( 'wp_interactivity_get_context' ) && version_compare( get_bloginfo( 'version' ), '6.9', '>=' ) ) {
		$is_active = function () {
			$context = wp_interactivity_get_context( RPRESS_STOREFRONT_NAMESPACE );
			return isset( $context['cat'] ) && '' === (string) $context['cat'] && ! empty( $context['filter'] );
		};
		wp_interactivity_state(
			RPRESS_STOREFRONT_NAMESPACE,
			array(
				'isCategoryActive' => $is_active,
				'categoryCurrent'  => function () use ( $is_active ) {
					// null drops the attribute; false prints aria-current="false".
					return $is_active() ? 'true' : null;
				},
			)
		);
	}
}

/**
 * Food categories for a block, honoring include/exclude attributes the way
 * the [fooditems] shortcode does (ids or slugs, comma separated).
 *
 * @since 3.5
 * @param string $include Comma separated ids or slugs. Empty for all.
 * @param string $exclude Comma separated ids or slugs.
 * @return WP_Term[]
 */
function rpress_block_storefront_categories( $include = '', $exclude = '' ) {
	$ids = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', (string) $include ) ) ) as $token ) {
		$term = is_numeric( $token )
			? get_term_by( 'id', (int) $token, 'food-category' )
			: get_term_by( 'slug', $token, 'food-category' );
		if ( $term && ! is_wp_error( $term ) ) {
			$ids[] = (int) $term->term_id;
		}
	}
	$terms = rpress_get_categories(
		array(
			'ids'               => $ids,
			'excluded_category' => (string) $exclude,
		)
	);
	if ( ! is_array( $terms ) ) {
		return array();
	}
	// Same hook the classic menu runs its category list through.
	$slugs = apply_filters( 'time_based_menu_rpress_foods', wp_list_pluck( $terms, 'slug' ) );
	$slugs = is_array( $slugs ) ? $slugs : array();
	return array_values(
		array_filter(
			$terms,
			function ( $term ) use ( $slugs ) {
				return in_array( $term->slug, $slugs, true );
			}
		)
	);
}

/**
 * Published food items of one category, through the same query filter the
 * classic menu uses (rpress_fooditems_query).
 *
 * @since 3.5
 * @param WP_Term $term    Category.
 * @param string  $orderby Order by.
 * @param string  $order   ASC|DESC.
 * @return WP_Post[]
 */
function rpress_block_storefront_category_items( $term, $orderby = 'title', $order = 'ASC' ) {
	$atts  = array(
		'category'         => '',
		'category_menu'    => '',
		'fooditem_orderby' => sanitize_key( $orderby ) ? sanitize_key( $orderby ) : 'title',
		'fooditem_order'   => 'DESC' === strtoupper( (string) $order ) ? 'DESC' : 'ASC',
		'relation'         => 'OR',
		'cat_orderby'      => 'include',
		'cat_order'        => 'ASC',
		'category_exclude' => '',
	);
	$query = array(
		'post_type'      => 'fooditem',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => $atts['fooditem_orderby'],
		'order'          => $atts['fooditem_order'],
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy'         => 'food-category',
				'field'            => 'slug',
				'terms'            => array( $term->slug ),
				'include_children' => false,
			),
		),
	);
	$query = apply_filters( 'rpress_fooditems_query', $query, $atts );
	$posts = get_posts( $query + array( 'suppress_filters' => false ) );
	return is_array( $posts ) ? $posts : array();
}

/**
 * The existing Add button for a food item.
 *
 * Reuses rpress_get_purchase_link(), so the click opens the classic item
 * popup (variations, add-ons, special instructions, quantity) through the
 * existing rp-frontend.js handler and AJAX. Only adds an accessible name.
 *
 * @since 3.5
 * @param int $fooditem_id Food item ID.
 * @return string HTML.
 */
function rpress_block_storefront_add_button( $fooditem_id ) {
	$html = rpress_get_purchase_link( array( 'fooditem_id' => absint( $fooditem_id ) ) );
	if ( ! is_string( $html ) || '' === $html ) {
		return '';
	}
	$title = get_the_title( $fooditem_id );
	/* translators: %s: food item name */
	$label = sprintf( __( 'Add %s to cart', 'restropress' ), $title );
	$html  = preg_replace(
		'/<a href="#"(\s+data-title=)/',
		'<a href="#" role="button" aria-label="' . esc_attr( $label ) . '"$1',
		$html,
		1
	);
	return (string) $html;
}

/**
 * Wrapper attributes for a storefront block (alignment, spacing, color
 * supports plus our classes).
 *
 * @since 3.5
 * @param string $class Extra class.
 * @param array  $extra Extra attributes.
 * @return string
 */
function rpress_block_storefront_wrapper( $class, $extra = array() ) {
	$extra['class'] = trim( 'rpress-sf ' . $class . ( isset( $extra['class'] ) ? ' ' . $extra['class'] : '' ) );
	if ( function_exists( 'get_block_wrapper_attributes' ) ) {
		return get_block_wrapper_attributes( $extra );
	}
	$out = '';
	foreach ( $extra as $name => $value ) {
		$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
	}
	return trim( $out );
}

/**
 * Fallback for WordPress without the Interactivity API: the item grid
 * renders the classic storefront (menu, categories, service bar, search and
 * cart in one), the other blocks render nothing so nothing is duplicated.
 *
 * @since 3.5
 * @param string $block Block folder name.
 * @param array  $attributes Block attributes.
 * @return string
 */
function rpress_block_storefront_fallback( $block, $attributes = array() ) {
	if ( 'item-grid' !== $block ) {
		return '';
	}
	$shortcode = '[fooditems';
	if ( ! empty( $attributes['categories'] ) ) {
		$shortcode .= ' category="' . esc_attr( $attributes['categories'] ) . '"';
	}
	if ( ! empty( $attributes['categoryExclude'] ) ) {
		$shortcode .= ' category_exclude="' . esc_attr( $attributes['categoryExclude'] ) . '"';
	}
	$shortcode .= ']';
	return '<div ' . rpress_block_storefront_wrapper( 'rpress-sf-fallback' ) . '>' . do_shortcode( $shortcode ) . '</div>';
}

/**
 * AJAX: current cart state for the storefront blocks (read only).
 *
 * @since 3.5
 * @return void
 */
function rpress_block_storefront_ajax_cart() {
	wp_send_json_success(
		array(
			'cart'  => rpress_block_storefront_cart_state(),
			'store' => rpress_block_storefront_store_state(),
		)
	);
}
