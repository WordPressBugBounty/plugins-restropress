<?php
/**
 * Checkout upsell (3.5, free).
 *
 * One compact "Add to your order" row on the checkout page, above the Payment
 * card: up to 4 food items the merchant picked, or the best sellers of one
 * category. Items already in the cart are hidden. Simple items add straight
 * through the normal rpress_add_to_cart AJAX; items with variable prices or
 * required add-ons open the normal item popup, so every add-on rule still
 * applies. Rules-based upsells, several rows and discounted order bumps stay Pro.
 *
 * Settings live in General > Checkout.
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Maximum number of items shown (and selectable) in the row. */
if ( ! defined( 'RPRESS_CHECKOUT_UPSELL_MAX' ) ) {
	define( 'RPRESS_CHECKOUT_UPSELL_MAX', 4 );
}

/**
 * Normalized upsell settings.
 *
 * @since 3.5
 *
 * @param array|null $raw Optional raw option values (for tests); defaults to the saved settings.
 * @return array {enabled: bool, heading: string, source: 'items'|'category', items: int[], category: int}
 */
function rpress_checkout_upsell_settings( $raw = null ) {
	if ( null === $raw ) {
		$raw = array(
			'checkout_upsell_enable'   => rpress_get_option( 'checkout_upsell_enable', false ),
			'checkout_upsell_heading'  => rpress_get_option( 'checkout_upsell_heading', '' ),
			'checkout_upsell_source'   => rpress_get_option( 'checkout_upsell_source', 'items' ),
			'checkout_upsell_items'    => rpress_get_option( 'checkout_upsell_items', array() ),
			'checkout_upsell_category' => rpress_get_option( 'checkout_upsell_category', 0 ),
		);
	}
	$raw = (array) $raw;

	$enabled = ! empty( $raw['checkout_upsell_enable'] ) && '-1' !== (string) $raw['checkout_upsell_enable'];
	$heading = isset( $raw['checkout_upsell_heading'] ) ? trim( wp_strip_all_tags( (string) $raw['checkout_upsell_heading'] ) ) : '';
	if ( '' === $heading ) {
		$heading = __( 'Add to your order', 'restropress' );
	}
	$source = isset( $raw['checkout_upsell_source'] ) && 'category' === $raw['checkout_upsell_source'] ? 'category' : 'items';

	return apply_filters(
		'rpress_checkout_upsell_settings',
		array(
			'enabled'  => $enabled,
			'heading'  => $heading,
			'source'   => $source,
			'items'    => rpress_checkout_upsell_sanitize_items( isset( $raw['checkout_upsell_items'] ) ? $raw['checkout_upsell_items'] : array() ),
			'category' => isset( $raw['checkout_upsell_category'] ) ? absint( $raw['checkout_upsell_category'] ) : 0,
		)
	);
}

/**
 * Clean a list of picked item IDs: positive ints, unique, order kept, at most 4.
 *
 * @since 3.5
 *
 * @param mixed $items Array or comma separated string.
 * @return int[]
 */
function rpress_checkout_upsell_sanitize_items( $items ) {
	if ( is_string( $items ) ) {
		$items = explode( ',', $items );
	}
	$clean = array();
	foreach ( (array) $items as $item ) {
		$id = absint( $item );
		if ( $id && ! in_array( $id, $clean, true ) ) {
			$clean[] = $id;
		}
		if ( count( $clean ) >= RPRESS_CHECKOUT_UPSELL_MAX ) {
			break;
		}
	}
	return $clean;
}

/**
 * Food item IDs currently in the cart.
 *
 * @since 3.5
 * @return int[]
 */
function rpress_checkout_upsell_cart_ids() {
	$ids = array();
	foreach ( (array) rpress_get_cart_contents() as $line ) {
		if ( ! empty( $line['id'] ) ) {
			$ids[] = absint( $line['id'] );
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Whether a food item can be offered: published, a fooditem, orderable.
 *
 * @since 3.5
 *
 * @param int $fooditem_id Food item ID.
 * @return bool
 */
function rpress_checkout_upsell_item_is_offerable( $fooditem_id ) {
	$post = get_post( $fooditem_id );
	if ( ! $post || 'fooditem' !== $post->post_type || 'publish' !== $post->post_status ) {
		return false;
	}
	if ( function_exists( 'rpress_fooditem_available' ) && ! rpress_fooditem_available( $fooditem_id ) ) {
		return false;
	}
	return true;
}

/**
 * Items of a category, best sellers first (when there are sales), then menu order.
 *
 * @since 3.5
 *
 * @param int $term_id food-category term ID.
 * @return int[]
 */
function rpress_checkout_upsell_category_items( $term_id ) {
	$term_id = absint( $term_id );
	if ( ! $term_id ) {
		return array();
	}
	$ids = get_posts(
		array(
			'post_type'        => 'fooditem',
			'post_status'      => 'publish',
			'posts_per_page'   => 50,
			'fields'           => 'ids',
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'tax_query'        => array(
				array(
					'taxonomy' => 'food-category',
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		)
	);
	$ids = array_map( 'absint', (array) $ids );
	if ( $ids ) {
		// One query each for the posts and their meta, not one per item below.
		_prime_post_caches( $ids, false, false );
		update_meta_cache( 'post', $ids );
	}

	// Stable sort by sales, so items with no sales keep menu order.
	$rows = array();
	foreach ( $ids as $position => $id ) {
		$rows[] = array(
			'id'       => $id,
			'sales'    => (int) get_post_meta( $id, '_rpress_fooditem_sales', true ),
			'position' => $position,
		);
	}
	usort(
		$rows,
		function ( $a, $b ) {
			if ( $a['sales'] === $b['sales'] ) {
				return $a['position'] - $b['position'];
			}
			return $b['sales'] - $a['sales'];
		}
	);
	return wp_list_pluck( $rows, 'id' );
}

/**
 * The item IDs to show: from the chosen source, minus items in the cart, max 4.
 *
 * @since 3.5
 *
 * @param array|null $settings Normalized settings (rpress_checkout_upsell_settings()).
 * @param int[]|null $cart_ids Food item IDs in the cart; defaults to the live cart.
 * @return int[]
 */
function rpress_checkout_upsell_candidate_ids( $settings = null, $cart_ids = null ) {
	if ( null === $settings ) {
		$settings = rpress_checkout_upsell_settings();
	}
	if ( null === $cart_ids ) {
		$cart_ids = rpress_checkout_upsell_cart_ids();
	}
	$cart_ids = array_map( 'absint', (array) $cart_ids );

	$pool = 'category' === $settings['source']
		? rpress_checkout_upsell_category_items( $settings['category'] )
		: (array) $settings['items'];

	$ids = array();
	foreach ( $pool as $id ) {
		$id = absint( $id );
		if ( ! $id || in_array( $id, $cart_ids, true ) || in_array( $id, $ids, true ) ) {
			continue;
		}
		if ( ! rpress_checkout_upsell_item_is_offerable( $id ) ) {
			continue;
		}
		$ids[] = $id;
		if ( count( $ids ) >= RPRESS_CHECKOUT_UPSELL_MAX ) {
			break;
		}
	}
	$ids = (array) apply_filters( 'rpress_checkout_upsell_items', $ids, $settings, $cart_ids );
	return array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, RPRESS_CHECKOUT_UPSELL_MAX );
}

/**
 * Whether adding the item needs the item popup (choices to make) instead of a direct add.
 *
 * True for variable prices, a required add-on group (or a minimum), or
 * pre-selected default add-ons (a direct add would skip them).
 *
 * @since 3.5
 *
 * @param int $fooditem_id Food item ID.
 * @return bool
 */
function rpress_checkout_upsell_needs_popup( $fooditem_id ) {
	$needs = false;
	if ( rpress_has_variable_prices( $fooditem_id ) ) {
		$needs = true;
	} else {
		$addons = get_post_meta( $fooditem_id, '_addon_items', true );
		foreach ( (array) $addons as $addon ) {
			if ( ! is_array( $addon ) ) {
				continue;
			}
			if ( ( isset( $addon['is_required'] ) && 'yes' === $addon['is_required'] )
				|| ( ! empty( $addon['min_addons'] ) && absint( $addon['min_addons'] ) > 0 )
				|| ! empty( $addon['default'] ) ) {
				$needs = true;
				break;
			}
		}
	}
	return (bool) apply_filters( 'rpress_checkout_upsell_needs_popup', $needs, $fooditem_id );
}

/**
 * A price as the menu shows it: the same prices_include_tax / tax_item rules
 * as the menu templates and rpress_price(), at the item's tax class rate.
 *
 * @since 3.5
 *
 * @param int   $fooditem_id Food item ID.
 * @param float $amount      Price as entered on the item.
 * @return float
 */
function rpress_checkout_upsell_display_amount( $fooditem_id, $amount ) {
	$amount      = (float) $amount;
	$rate        = function_exists( 'rpress_get_fooditem_tax_percent' ) ? (float) rpress_get_fooditem_tax_percent( $fooditem_id ) : (float) rpress_get_option( 'tax_rate', 0 );
	$include_tax = rpress_get_option( 'prices_include_tax', true );
	$tax_item    = rpress_get_option( 'tax_item', true );
	if ( 'yes' === $include_tax && 'exc_tax' === $tax_item ) {
		$amount = $amount / ( ( $rate / 100 ) + 1 );
	} elseif ( 'no' === $include_tax && 'inc_tax' === $tax_item ) {
		$amount = $amount + ( $amount * ( $rate / 100 ) );
	}
	return (float) apply_filters( 'rpress_checkout_upsell_display_amount', $amount, $fooditem_id );
}

/**
 * Display price for a card ("From" the lowest option for variable prices).
 *
 * @since 3.5
 *
 * @param int $fooditem_id Food item ID.
 * @return array {amount: float, label: string} amount is the item's own price, label as the menu shows it.
 */
function rpress_checkout_upsell_price( $fooditem_id ) {
	if ( rpress_has_variable_prices( $fooditem_id ) ) {
		$amount = (float) rpress_get_lowest_price_option( $fooditem_id );
		/* translators: %s: lowest price */
		$label = sprintf( __( 'From %s', 'restropress' ), rpress_currency_filter( rpress_format_amount( rpress_checkout_upsell_display_amount( $fooditem_id, $amount ) ) ) );
	} else {
		$amount = (float) rpress_get_fooditem_price( $fooditem_id );
		$label  = $amount > 0 ? rpress_currency_filter( rpress_format_amount( rpress_checkout_upsell_display_amount( $fooditem_id, $amount ) ) ) : __( 'Free', 'restropress' );
	}
	return array(
		'amount' => $amount,
		'label'  => $label,
	);
}

/**
 * Whether the row should render on this request.
 *
 * @since 3.5
 * @return bool
 */
function rpress_checkout_upsell_is_active() {
	$settings = rpress_checkout_upsell_settings();
	$active   = $settings['enabled'] && ! rpress_is_ajax_disabled();
	return (bool) apply_filters( 'rpress_checkout_upsell_is_active', $active, $settings );
}

/**
 * Render the row (hooked above the Payment card).
 *
 * @since 3.5
 * @return void
 */
function rpress_checkout_upsell_render() {
	if ( ! rpress_checkout_upsell_is_active() ) {
		return;
	}
	$settings = rpress_checkout_upsell_settings();
	$ids      = rpress_checkout_upsell_candidate_ids( $settings );
	if ( empty( $ids ) ) {
		return;
	}
	$add_label = __( 'Add', 'restropress' );
	?>
	<section id="rpress-checkout-upsell" class="rpress-upsell" aria-labelledby="rpress-upsell-title">
		<h3 id="rpress-upsell-title" class="rpress-upsell-title"><?php echo esc_html( $settings['heading'] ); ?></h3>
		<ul class="rpress-upsell-list">
			<?php
			foreach ( $ids as $id ) :
				$title     = get_the_title( $id );
				$price     = rpress_checkout_upsell_price( $id );
				$popup     = rpress_checkout_upsell_needs_popup( $id );
				$thumb_id  = get_post_thumbnail_id( $id );
				$image_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';
				$classes   = 'rpress-upsell-add' . ( $popup ? ' rpress-add-to-cart' : '' );
				/* translators: %s: food item name */
				$aria = sprintf( __( 'Add %s to your order', 'restropress' ), $title );
				?>
				<li class="rpress-upsell-item" data-fooditem-id="<?php echo esc_attr( $id ); ?>">
					<span class="rpress-upsell-media<?php echo $image_url ? '' : ' is-empty'; ?>" aria-hidden="true">
						<?php if ( $image_url ) : ?>
							<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy" width="56" height="56" />
						<?php else : ?>
							<span class="rpress-upsell-initial"><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 ) ); ?></span>
						<?php endif; ?>
					</span>
					<span class="rpress-upsell-info">
						<span class="rpress-upsell-name"><?php echo esc_html( $title ); ?></span>
						<span class="rpress-upsell-price"><?php echo esc_html( $price['label'] ); ?></span>
					</span>
					<button type="button"
						class="<?php echo esc_attr( $classes ); ?>"
						data-fooditem-id="<?php echo esc_attr( $id ); ?>"
						data-title="<?php echo esc_attr( $title ); ?>"
						data-price="<?php echo esc_attr( $price['amount'] ); ?>"
						data-variable-price="<?php echo rpress_has_variable_prices( $id ) ? 'yes' : 'no'; ?>"
						data-upsell-mode="<?php echo $popup ? 'popup' : 'direct'; ?>"
						aria-label="<?php echo esc_attr( $aria ); ?>">
						<span class="rp-ajax-toggle-text"><?php echo esc_html( $add_label ); ?></span>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
}
// Priority 5: right before the Payment card (rpress_payment_mode_select runs at 10).
add_action( 'rpress_payment_mode_select', 'rpress_checkout_upsell_render', 5 );

/**
 * Enqueue the row's CSS and JS on the checkout page only.
 *
 * @since 3.5
 * @return void
 */
function rpress_checkout_upsell_enqueue() {
	if ( ! function_exists( 'rpress_is_checkout' ) || ! rpress_is_checkout() || ! rpress_checkout_upsell_is_active() ) {
		return;
	}
	$base = plugin_dir_url( RP_PLUGIN_FILE ) . 'assets/features/checkout-upsell/';
	$dir  = RP_PLUGIN_DIR . 'assets/features/checkout-upsell/';
	$ver  = function ( $file ) use ( $dir ) {
		return file_exists( $dir . $file ) ? RP_VERSION . '.' . filemtime( $dir . $file ) : RP_VERSION;
	};
	$style_deps = wp_style_is( 'rpress-checkout', 'registered' ) ? array( 'rpress-checkout' ) : array();
	wp_enqueue_style( 'rpress-checkout-upsell', $base . 'checkout-upsell.css', $style_deps, $ver( 'checkout-upsell.css' ) );
	wp_enqueue_script( 'rpress-checkout-upsell', $base . 'checkout-upsell.js', array( 'jquery', 'rp-frontend' ), $ver( 'checkout-upsell.js' ), true );
	wp_localize_script(
		'rpress-checkout-upsell',
		'rpress_checkout_upsell',
		array(
			'ajaxurl' => rpress_get_ajax_url(),
			'nonce'   => wp_create_nonce( 'add-to-cart' ),
			'added'   => __( 'Added', 'restropress' ),
			'error'   => __( 'Could not add this item. Please try again.', 'restropress' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rpress_checkout_upsell_enqueue', 20 );

/**
 * Settings: General > Checkout.
 *
 * @since 3.5
 *
 * @param array $settings Registered settings.
 * @return array
 */
function rpress_checkout_upsell_register_settings( $settings ) {
	if ( ! isset( $settings['general'] ) || ! is_array( $settings['general'] ) ) {
		return $settings;
	}
	$section = isset( $settings['general']['checkout'] ) ? 'checkout' : 'checkout_options';
	if ( ! isset( $settings['general'][ $section ] ) || ! is_array( $settings['general'][ $section ] ) ) {
		$settings['general'][ $section ] = array();
	}
	// The item and category lists are only needed to draw the settings page.
	// Saving never checks them, so the saved values stay intact elsewhere.
	$build_options = rpress_checkout_upsell_is_settings_screen();

	$settings['general'][ $section ] += array(
		'checkout_upsell_header'   => array(
			'id'   => 'checkout_upsell_header',
			'name' => '<h3>' . esc_html__( 'Checkout upsell', 'restropress' ) . '</h3>',
			'desc' => '',
			'type' => 'header',
		),
		'checkout_upsell_enable'   => array(
			'id'   => 'checkout_upsell_enable',
			'name' => esc_html__( 'Show "Add to your order" row', 'restropress' ),
			'desc' => esc_html__( 'Show up to 4 extra items on the checkout page, above Payment. Items already in the cart are hidden.', 'restropress' ),
			'type' => 'checkbox',
		),
		'checkout_upsell_heading'  => array(
			'id'          => 'checkout_upsell_heading',
			'name'        => esc_html__( 'Row heading', 'restropress' ),
			'desc'        => '',
			'type'        => 'text',
			'std'         => __( 'Add to your order', 'restropress' ),
			'placeholder' => __( 'Add to your order', 'restropress' ),
		),
		'checkout_upsell_source'   => array(
			'id'      => 'checkout_upsell_source',
			'name'    => esc_html__( 'Items to show', 'restropress' ),
			'desc'    => '',
			'type'    => 'select',
			'std'     => 'items',
			'options' => array(
				'items'    => esc_html__( 'Selected items', 'restropress' ),
				'category' => esc_html__( 'Items from a category', 'restropress' ),
			),
		),
		// Hidden empty value posted before the multi-select, so clearing every
		// pick saves an empty list instead of keeping the old one.
		'checkout_upsell_items_marker' => array(
			'id'   => 'checkout_upsell_items_marker',
			'name' => '',
			'desc' => '',
			'type' => 'hook',
		),
		'checkout_upsell_items'    => array(
			'id'          => 'checkout_upsell_items',
			'name'        => esc_html__( 'Selected items', 'restropress' ),
			'desc'        => esc_html__( 'Pick up to 4 items. Extra picks beyond 4 are ignored.', 'restropress' ),
			'type'        => 'select',
			'multiple'    => true,
			'chosen'      => true,
			'placeholder' => esc_html__( 'Choose items', 'restropress' ),
			'options'     => $build_options ? rpress_checkout_upsell_fooditem_options() : array(),
		),
		'checkout_upsell_category' => array(
			'id'      => 'checkout_upsell_category',
			'name'    => esc_html__( 'Category', 'restropress' ),
			'desc'    => esc_html__( 'Shows up to 4 items from this category: best sellers first, then menu order.', 'restropress' ),
			'type'    => 'select',
			'options' => $build_options ? rpress_checkout_upsell_category_options() : array(),
		),
	);
	return $settings;
}
add_filter( 'rpress_registered_settings', 'rpress_checkout_upsell_register_settings' );

/**
 * Whether this request draws the RestroPress settings page (not AJAX).
 *
 * @since 3.5
 * @return bool
 */
function rpress_checkout_upsell_is_settings_screen() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only screen check.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	return (bool) apply_filters( 'rpress_checkout_upsell_build_setting_options', is_admin() && ! wp_doing_ajax() && 'rpress-settings' === $page );
}

/**
 * Hidden empty value for the item picker (see the marker setting).
 *
 * @since 3.5
 * @return void
 */
function rpress_checkout_upsell_items_marker() {
	echo '<input type="hidden" name="rpress_settings[checkout_upsell_items]" value="" />';
}
add_action( 'rpress_checkout_upsell_items_marker', 'rpress_checkout_upsell_items_marker' );

/**
 * Food item options for the settings select.
 *
 * @since 3.5
 * @return array id => title
 */
function rpress_checkout_upsell_fooditem_options() {
	static $options = null;
	if ( null !== $options ) {
		return $options;
	}
	$options = array();
	$posts   = get_posts(
		array(
			'post_type'      => 'fooditem',
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	foreach ( $posts as $post ) {
		$options[ $post->ID ] = $post->post_title;
	}
	return $options;
}

/**
 * Category options for the settings select.
 *
 * @since 3.5
 * @return array term_id => name
 */
function rpress_checkout_upsell_category_options() {
	static $options = null;
	if ( null !== $options ) {
		return $options;
	}
	$options = array( '' => esc_html__( 'Choose a category', 'restropress' ) );
	$terms   = get_terms(
		array(
			'taxonomy'   => 'food-category',
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$options[ $term->term_id ] = $term->name;
		}
	}
	return $options;
}

/**
 * Sanitize the upsell settings on save.
 *
 * @since 3.5
 *
 * @param mixed  $value Value.
 * @param string $key   Setting key.
 * @return mixed
 */
function rpress_checkout_upsell_sanitize_setting( $value, $key = '' ) {
	switch ( $key ) {
		case 'checkout_upsell_items':
			return rpress_checkout_upsell_sanitize_items( $value );
		case 'checkout_upsell_category':
			return absint( $value );
		case 'checkout_upsell_source':
			return 'category' === $value ? 'category' : 'items';
		case 'checkout_upsell_heading':
			return sanitize_text_field( $value );
	}
	return $value;
}
add_filter( 'rpress_settings_sanitize', 'rpress_checkout_upsell_sanitize_setting', 10, 2 );
