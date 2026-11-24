<?php
/**
 * Storefront layout components.
 *
 * @package RestroPress
 * @since   3.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * The layout for the current menu page, or an empty array when the layout
 * components do not apply (admin, legacy menu, a page without the menu).
 *
 * @since 3.5.1
 * @return array
 */
function rpress_storefront_layout_current() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}
	$layout = array();
	if ( ! function_exists( 'rpress_get_storefront_layout' ) || ! empty( rpress_get_option( 'old_ui_ux' ) ) ) {
		return $layout;
	}
	if ( ! function_exists( 'rpress_mobile_ordering_is_menu_page' ) || ! rpress_mobile_ordering_is_menu_page() ) {
		return $layout;
	}
	$layout = rpress_get_storefront_layout();
	// Cache once the main query is known; tests and early callers recompute.
	if ( did_action( 'wp' ) && ! defined( 'RPRESS_TESTS' ) ) {
		$cached = $layout;
	}
	return $layout;
}

/**
 * Whether the current menu page uses a layout value.
 *
 * @since 3.5.1
 * @param string $key   Layout key.
 * @param mixed  $value Value to compare (string compare).
 * @return bool
 */
function rpress_storefront_layout_is( $key, $value ) {
	$layout = rpress_storefront_layout_current();
	return isset( $layout[ $key ] ) && (string) $layout[ $key ] === (string) $value;
}

/**
 * Body classes for every layout value that differs from the core default.
 * Defaults add no class, so today's storefront CSS is untouched.
 *
 * Classes: rp-sf, rp-sf-masthead-{v}, rp-sf-nav-{v}, rp-sf-media-{v},
 * rp-sf-cols-{n}, rp-sf-card-{v}, rp-sf-noimg, rp-sf-cart-{v}, rp-sf-item-{v}.
 *
 * @since 3.5.1
 * @param array $classes Body classes.
 * @return array
 */
function rpress_storefront_layout_body_class( $classes ) {
	$layout = rpress_storefront_layout_current();
	if ( ! $layout ) {
		return $classes;
	}
	$defaults = RPRESS_Template_Packs::layout_defaults();
	$prefix   = array(
		'masthead'   => 'masthead',
		'nav_style'  => 'nav',
		'nav_media'  => 'media',
		'columns'    => 'cols',
		'card_style' => 'card',
		'cart_style' => 'cart',
		'item_style' => 'item',
	);
	// "band" is the 3.5 store card / Modern band, which renders as before.
	$defaults['masthead'] = 'band' === ( isset( $layout['masthead'] ) ? $layout['masthead'] : '' ) ? 'band' : $defaults['masthead'];
	$added = array();
	foreach ( $prefix as $key => $short ) {
		if ( isset( $layout[ $key ] ) && (string) $layout[ $key ] !== (string) $defaults[ $key ] ) {
			$added[] = 'rp-sf-' . $short . '-' . sanitize_html_class( str_replace( '_', '-', (string) $layout[ $key ] ) );
		}
	}
	if ( isset( $layout['show_image'] ) && ! $layout['show_image'] ) {
		$added[] = 'rp-sf-noimg';
	}
	if ( $added ) {
		$classes[] = 'rp-sf';
		$classes   = array_merge( $classes, $added );
	}
	return $classes;
}
add_filter( 'body_class', 'rpress_storefront_layout_body_class', 20 );

/* ------------------------------------------------------------------ */
/* Masthead                                                            */
/* ------------------------------------------------------------------ */

/**
 * Masthead variants this module renders. "band" stays the 3.5 store card
 * (or the Modern band) so Modern is unchanged; "none" renders nothing new.
 *
 * @since 3.5.1
 * @return string Variant, or '' when the module does not render a masthead.
 */
function rpress_storefront_masthead_variant() {
	$layout = rpress_storefront_layout_current();
	$v      = isset( $layout['masthead'] ) ? (string) $layout['masthead'] : 'none';
	return in_array( $v, array( 'cover', 'strip', 'editorial' ), true ) ? $v : '';
}

/**
 * Cover image URL for the cover masthead (Styles setting), or ''.
 *
 * @since 3.5.1
 * @return string
 */
function rpress_storefront_cover_image() {
	$cover = rpress_get_option( 'store_cover_image', '' );
	if ( is_numeric( $cover ) ) {
		$cover = wp_get_attachment_image_url( (int) $cover, 'full' );
	}
	/**
	 * Filter the storefront cover image URL.
	 *
	 * @since 3.5.1
	 * @param string $cover Image URL or ''.
	 */
	return (string) apply_filters( 'rpress_storefront_cover_image', $cover ? esc_url_raw( $cover ) : '' );
}

/**
 * Store monogram: the site logo when there is one, else the initials.
 *
 * @since 3.5.1
 * @return string HTML.
 */
function rpress_storefront_monogram_html() {
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( ! $logo_id ) {
		$logo_id = (int) get_option( 'site_icon' );
	}
	if ( $logo_id ) {
		$img = wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'alt' => '', 'class' => 'rp-sf-monogram-img' ) );
		if ( $img ) {
			return '<span class="rp-sf-monogram has-img" aria-hidden="true">' . $img . '</span>';
		}
	}
	$name     = trim( wp_strip_all_tags( get_bloginfo( 'name' ) ) );
	$words    = preg_split( '/\s+/u', $name );
	$initials = '';
	foreach ( (array) $words as $word ) {
		if ( '' !== $word && preg_match( '/^[\p{L}\p{N}]/u', $word, $m ) ) {
			$initials .= $m[0];
		}
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $initials ) >= 2 : strlen( $initials ) >= 2 ) {
			break;
		}
	}
	$initials = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initials ) : strtoupper( $initials );
	return '<span class="rp-sf-monogram" aria-hidden="true">' . esc_html( $initials ) . '</span>';
}

/**
 * Render the masthead above the menu toolbar. It wraps the 3.5 store card
 * (status, chips, closed notice, live refresh), so the facts and their rules
 * stay in one place.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_storefront_render_masthead() {
	$variant = rpress_storefront_masthead_variant();
	if ( ! $variant ) {
		return;
	}
	$cover = 'cover' === $variant ? rpress_storefront_cover_image() : '';
	$card  = function_exists( 'rpress_mobile_ordering_store_card_html' ) && rpress_mobile_ordering_enabled()
		? rpress_mobile_ordering_store_card_html( rpress_mobile_ordering_service() )
		: '';
	$classes = array( 'rp-sf-masthead', 'rp-sf-masthead--' . $variant );
	if ( 'cover' === $variant ) {
		$classes[] = $cover ? 'has-cover' : 'no-cover';
	}
	?>
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
		<?php if ( 'cover' === $variant ) : ?>
			<div class="rp-sf-cover"<?php echo $cover ? ' style="background-image:url(' . esc_url( $cover ) . ')"' : ''; ?> aria-hidden="true"></div>
		<?php endif; ?>
		<div class="rp-sf-masthead-body">
			<?php
			if ( 'editorial' !== $variant ) {
				echo rpress_storefront_monogram_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while built.
			}
			if ( $card ) {
				echo $card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while built.
			} else {
				?>
				<h2 class="rp-sf-store-name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
				<?php
			}
			?>
		</div>
	</div>
	<?php
}
add_action( 'rpress_menu_page_top', 'rpress_storefront_render_masthead', 3 );

/**
 * A masthead variant owns the store facts: the standalone 3.5 store card and
 * the Modern band step aside so only one header shows.
 *
 * @since 3.5.1
 * @param bool $show Show the card.
 * @return bool
 */
function rpress_storefront_masthead_hides_card( $show ) {
	return rpress_storefront_masthead_variant() ? false : $show;
}
add_filter( 'rpress_mobile_ordering_show_store_card', 'rpress_storefront_masthead_hides_card', 20 );

/**
 * Drop the Modern band when a masthead variant renders (runs on wp, once the
 * query is known, so the menu page test works).
 *
 * @since 3.5.1
 * @return void
 */
function rpress_storefront_masthead_replace_band() {
	if ( rpress_storefront_masthead_variant() && class_exists( 'RPRESS_Template_Packs' ) ) {
		remove_action( 'rpress_menu_page_top', array( 'RPRESS_Template_Packs', 'render_modern_store_header' ) );
	}
}
add_action( 'wp', 'rpress_storefront_masthead_replace_band' );

/**
 * Whether core renders the store header for the current page. Packs that used
 * to print their own header check this and stay quiet.
 *
 * @since 3.5.1
 * @return bool
 */
function rpress_storefront_renders_masthead() {
	return '' !== rpress_storefront_masthead_variant();
}

/* ------------------------------------------------------------------ */
/* Category nav media (photo tiles)                                    */
/* ------------------------------------------------------------------ */

/**
 * Image for a menu category: term meta thumbnail_id, else the first dish in
 * the category that has a photo. '' when neither exists (the tile then shows
 * the initial).
 *
 * @since 3.5.1
 * @param int $term_id food-category term ID.
 * @return string Image URL or ''.
 */
function rpress_get_category_image_url( $term_id ) {
	$term_id = (int) $term_id;
	$cache   = wp_cache_get( 'rp_cat_img_' . $term_id, 'restropress' );
	if ( false !== $cache ) {
		return (string) $cache;
	}
	$url   = '';
	$thumb = (int) get_term_meta( $term_id, 'thumbnail_id', true );
	if ( $thumb ) {
		$url = (string) wp_get_attachment_image_url( $thumb, 'medium' );
	}
	if ( ! $url ) {
		$posts = get_posts(
			array(
				'post_type'      => 'fooditem',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one row, cached.
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => 'food-category',
						'field'            => 'term_id',
						'terms'            => $term_id,
						'include_children' => false,
					),
				),
			)
		);
		if ( $posts ) {
			$url = (string) get_the_post_thumbnail_url( $posts[0], 'medium' );
		}
	}
	/**
	 * Filter a menu category's tile image.
	 *
	 * @since 3.5.1
	 * @param string $url     Image URL or ''.
	 * @param int    $term_id Term ID.
	 */
	$url = (string) apply_filters( 'rpress_category_image_url', $url, $term_id );
	wp_cache_set( 'rp_cat_img_' . $term_id, $url, 'restropress', HOUR_IN_SECONDS );
	return $url;
}

/**
 * Media for one category link in the scrollable nav. Empty unless the layout
 * asks for images.
 *
 * @since 3.5.1
 * @param WP_Term $cat Category.
 * @return string HTML.
 */
function rpress_storefront_nav_media_html( $cat ) {
	if ( ! $cat instanceof WP_Term || ! rpress_storefront_layout_is( 'nav_media', 'images' ) ) {
		return '';
	}
	$url = rpress_get_category_image_url( $cat->term_id );
	if ( $url ) {
		return '<span class="rp-sf-tile"><img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async"></span>';
	}
	$initial = function_exists( 'mb_substr' ) ? mb_substr( $cat->name, 0, 1 ) : substr( $cat->name, 0, 1 );
	return '<span class="rp-sf-tile is-empty" aria-hidden="true">' . esc_html( $initial ) . '</span>';
}

/* ------------------------------------------------------------------ */
/* Rails                                                               */
/* ------------------------------------------------------------------ */

/**
 * Dish IDs for a rail. Only published dishes that can be ordered now.
 *
 * - popular:     most sold.
 * - deals:       dishes with the fooditem_tag "deals" (filter the slug).
 * - order_again: the logged-in customer's past dishes.
 *
 * @since 3.5.1
 * @param string $rail Rail key.
 * @return int[]
 */
function rpress_storefront_rail_items( $rail ) {
	/**
	 * Filter how many dishes a rail shows.
	 *
	 * @since 3.5.1
	 * @param int    $limit Dishes.
	 * @param string $rail  Rail key.
	 */
	$limit = max( 1, (int) apply_filters( 'rpress_storefront_rail_limit', 8, $rail ) );
	$ids   = array();
	$base  = array(
		'post_type'      => 'fooditem',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);

	if ( 'popular' === $rail ) {
		$ids = get_posts(
			$base + array(
				'meta_key'   => '_rpress_fooditem_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'    => 'meta_value_num',
				'order'      => 'DESC',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_rpress_fooditem_sales',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
	} elseif ( 'deals' === $rail ) {
		/**
		 * Filter the dish tag that puts a dish on the Deals rail.
		 *
		 * @since 3.5.1
		 * @param string $slug Tag slug.
		 */
		$slug = (string) apply_filters( 'rpress_storefront_deals_tag', 'deals' );
		$ids  = get_posts(
			$base + array(
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'fooditem_tag',
						'field'    => 'slug',
						'terms'    => $slug,
					),
				),
			)
		);
	} elseif ( 'order_again' === $rail && is_user_logged_in() && function_exists( 'rpress_get_users_ordered_products' ) ) {
		$posts = rpress_get_users_ordered_products( get_current_user_id(), 'complete' );
		if ( $posts ) {
			$ids = array_slice( wp_list_pluck( (array) $posts, 'ID' ), 0, $limit );
		}
	}

	$ids = array_values(
		array_filter(
			array_map( 'absint', (array) $ids ),
			function ( $id ) {
				return $id && 'publish' === get_post_status( $id ) && ( ! function_exists( 'rpress_fooditem_available' ) || rpress_fooditem_available( $id ) );
			}
		)
	);

	/**
	 * Filter the dishes on a storefront rail.
	 *
	 * @since 3.5.1
	 * @param int[]  $ids  Dish IDs.
	 * @param string $rail Rail key.
	 */
	return array_values( array_map( 'absint', (array) apply_filters( 'rpress_storefront_rail_items', $ids, $rail ) ) );
}

/**
 * Rail headings.
 *
 * @since 3.5.1
 * @param string $rail Rail key.
 * @return string
 */
function rpress_storefront_rail_title( $rail ) {
	$titles = array(
		'popular'     => __( 'Popular', 'restropress' ),
		'order_again' => __( 'Order again', 'restropress' ),
		'deals'       => __( 'Deals', 'restropress' ),
	);
	/**
	 * Filter a rail heading.
	 *
	 * @since 3.5.1
	 * @param string $title Heading.
	 * @param string $rail  Rail key.
	 */
	return (string) apply_filters( 'rpress_storefront_rail_title', isset( $titles[ $rail ] ) ? $titles[ $rail ] : '', $rail );
}

/**
 * Render the rails above the menu. Each card opens the same item popup as
 * the dish in the menu (the script forwards the tap), so options, required
 * choices and the cart flow stay core's. A rail with no dishes is skipped.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_storefront_render_rails() {
	$layout = rpress_storefront_layout_current();
	if ( empty( $layout['rails'] ) ) {
		return;
	}
	foreach ( (array) $layout['rails'] as $rail ) {
		$ids = rpress_storefront_rail_items( $rail );
		if ( ! $ids ) {
			continue;
		}
		$title    = rpress_storefront_rail_title( $rail );
		$title_id = 'rp-sf-rail-' . sanitize_html_class( $rail );
		?>
		<section class="rp-sf-rail rp-sf-rail--<?php echo esc_attr( sanitize_html_class( $rail ) ); ?>" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<div class="rp-sf-rail-head">
				<h2 class="rp-sf-rail-title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $title ); ?></h2>
				<div class="rp-sf-rail-paddles">
					<button type="button" class="rp-sf-paddle is-prev" aria-label="<?php esc_attr_e( 'Scroll back', 'restropress' ); ?>"><span aria-hidden="true">&#8249;</span></button>
					<button type="button" class="rp-sf-paddle is-next" aria-label="<?php esc_attr_e( 'Scroll forward', 'restropress' ); ?>"><span aria-hidden="true">&#8250;</span></button>
				</div>
			</div>
			<ul class="rp-sf-rail-track">
				<?php foreach ( $ids as $id ) : ?>
					<?php
					$name  = get_the_title( $id );
					$price = function_exists( 'rpress_has_variable_prices' ) && rpress_has_variable_prices( $id )
						? wp_strip_all_tags( rpress_price_range( $id ) )
						: rpress_currency_filter( rpress_format_amount( (float) get_post_meta( $id, 'rpress_price', true ) ) );
					?>
					<li class="rp-sf-rail-item">
						<button type="button" class="rp-sf-rail-card" data-fooditem-id="<?php echo esc_attr( $id ); ?>">
							<?php if ( has_post_thumbnail( $id ) ) : ?>
								<span class="rp-sf-rail-img"><?php echo get_the_post_thumbnail( $id, 'medium', array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); ?></span>
							<?php endif; ?>
							<span class="rp-sf-rail-name"><?php echo esc_html( $name ); ?></span>
							<span class="rp-sf-rail-price"><?php echo esc_html( $price ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}
}
add_action( 'rpress_menu_page_top', 'rpress_storefront_render_rails_at_top', 30 );

/* ------------------------------------------------------------------ */
/* Section order                                                       */
/* ------------------------------------------------------------------ */

/**
 * Order of the parts printed above the dishes: the rails ("rails"), the
 * category navigation ("nav") and the service row with time and search
 * ("service"). Comes from the layout's "sections" key; parts it leaves out
 * keep their default place. The store header always comes first and the
 * dishes last.
 *
 * The menu template prints the parts in this order, so keyboard and screen
 * reader order match what the customer sees.
 *
 * @since 3.5.1
 * @return string[] Some order of 'rails', 'nav', 'service'.
 */
function rpress_menu_section_order() {
	$default = array( 'rails', 'nav', 'service' );
	$layout  = rpress_storefront_layout_current();
	$order   = ! empty( $layout['sections'] ) ? array_values( array_intersect( (array) $layout['sections'], $default ) ) : array();
	foreach ( $default as $i => $part ) {
		if ( ! in_array( $part, $order, true ) ) {
			array_splice( $order, min( $i, count( $order ) ), 0, $part );
		}
	}
	/**
	 * Filter the order of the parts above the dishes.
	 *
	 * @since 3.5.1
	 * @param string[] $order Some order of 'rails', 'nav', 'service'.
	 */
	$order = (array) apply_filters( 'rpress_menu_section_order', $order );
	return array_values( array_unique( array_intersect( $order, $default ) ) );
}

/**
 * Rails print above the menu columns (full width) when they come first, and
 * inside the menu column, in their place, otherwise.
 *
 * @since 3.5.1
 * @return bool
 */
function rpress_menu_rails_first() {
	$order = rpress_menu_section_order();
	return 'rails' === reset( $order );
}

/**
 * Rails above the menu columns, when they come first.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_storefront_render_rails_at_top() {
	if ( rpress_menu_rails_first() ) {
		rpress_storefront_render_rails();
	}
}

/**
 * Rails in their place inside the menu column, when they do not come first.
 * The menu templates fire rpress_menu_section_rails where the rails belong.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_storefront_render_rails_in_place() {
	if ( ! rpress_menu_rails_first() ) {
		rpress_storefront_render_rails();
	}
}
add_action( 'rpress_menu_section_rails', 'rpress_storefront_render_rails_in_place' );

/* ------------------------------------------------------------------ */
/* Assets                                                              */
/* ------------------------------------------------------------------ */

/**
 * Enqueue the component styles and script on menu pages that use a
 * non-default layout. After the pack skins and the mobile layer, so the
 * component rules win and the packs then skin them through tokens.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_storefront_layout_enqueue() {
	$classes = rpress_storefront_layout_body_class( array() );
	if ( ! in_array( 'rp-sf', $classes, true ) && empty( rpress_storefront_layout_current()['rails'] ) ) {
		return;
	}
	$dir = RP_PLUGIN_DIR . 'assets/features/storefront-layout/';
	$url = plugin_dir_url( RP_PLUGIN_FILE ) . 'assets/features/storefront-layout/';
	$ver = static function ( $file ) use ( $dir ) {
		return RP_VERSION . '.' . ( file_exists( $dir . $file ) ? filemtime( $dir . $file ) : '0' );
	};
	$deps = array();
	foreach ( array( 'rpress-menu', 'rpress-styles', 'rpress-mobile-ordering' ) as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) ) {
			$deps[] = $handle;
		}
	}
	wp_enqueue_style( 'rpress-storefront-layout', $url . 'storefront-layout.css', $deps, $ver( 'storefront-layout.css' ) );
	$script_deps = array( 'jquery' );
	if ( wp_script_is( 'rpress-mobile-ordering', 'registered' ) ) {
		$script_deps[] = 'rpress-mobile-ordering';
	}
	wp_enqueue_script( 'rpress-storefront-layout', $url . 'storefront-layout.js', $script_deps, $ver( 'storefront-layout.js' ), true );
	wp_localize_script(
		'rpress-storefront-layout',
		'rpress_storefront_layout',
		array(
			'i18n' => array(
				'cart'       => __( 'Your order', 'restropress' ),
				'open_cart'  => __( 'View cart', 'restropress' ),
				'close'      => __( 'Close', 'restropress' ),
				/* translators: %d: number of items */
				'items_one'  => __( '%d item', 'restropress' ),
				/* translators: %d: number of items */
				'items_many' => __( '%d items', 'restropress' ),
			),
			// Plural forms per count, so languages with more than two
			// forms read right (the two strings above stay as fallbacks).
			'items_by_count' => array_map(
				function ( $n ) {
					/* translators: %d: number of items */
					return sprintf( _n( '%d item', '%d items', $n, 'restropress' ), $n );
				},
				range( 0, 50 )
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rpress_storefront_layout_enqueue', 50 );
