<?php
/**
 * Menu schema: schema.org Restaurant > Menu > MenuSection > MenuItem JSON-LD.
 *
 * Output: on the menu page (food_items_page option) and on any singular post
 * containing the [fooditems] shortcode or the rpress/food-menu block.
 *
 * Skipped when the "Turn off menu schema" setting is on, or when an SEO plugin
 * is detected that already outputs Restaurant schema (best effort, see
 * seo_plugin_restaurant_schema()). The `rpress_output_menu_schema` filter has
 * the final say either way.
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menu schema output.
 */
class RPRESS_Menu_Schema {

	/**
	 * Option holding the cache generation; bumping it invalidates every cached copy.
	 */
	const CACHE_VERSION_OPTION = 'rpress_menu_schema_cache_ver';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 30 );

		// Cache invalidation.
		add_action( 'save_post_fooditem', array( __CLASS__, 'flush' ) );
		add_action( 'delete_post', array( __CLASS__, 'maybe_flush_post' ) );
		add_action( 'trashed_post', array( __CLASS__, 'maybe_flush_post' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'maybe_flush_post' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'maybe_flush_terms' ), 10, 6 );
		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_flush_meta' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'maybe_flush_meta' ), 10, 4 );
		foreach ( array( 'food-category', 'dietary' ) as $taxonomy ) {
			add_action( "created_{$taxonomy}", array( __CLASS__, 'flush' ) );
			add_action( "edited_{$taxonomy}", array( __CLASS__, 'flush' ) );
			add_action( "delete_{$taxonomy}", array( __CLASS__, 'flush' ) );
		}
		add_action( 'update_option_rpress_settings', array( __CLASS__, 'flush' ) );
		add_action( 'update_option_blogname', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Pending invalidation for this request.
	 *
	 * @var bool
	 */
	private static $dirty = false;

	/**
	 * Invalidate the cached schema. Bulk edits and imports fire many hooks,
	 * so the option is bumped once, on the next read or at shutdown.
	 *
	 * @return void
	 */
	public static function flush() {
		if ( ! self::$dirty ) {
			self::$dirty = true;
			add_action( 'shutdown', array( __CLASS__, 'cache_version' ) );
		}
	}

	/**
	 * Current cache generation, committing any pending invalidation.
	 *
	 * The generation is a random token, not a counter: a counter that goes
	 * back (database restore, option cleanup, lost update) lands on keys
	 * whose day-long transients still hold an old menu.
	 *
	 * @return string
	 */
	public static function cache_version() {
		$ver = (string) get_option( self::CACHE_VERSION_OPTION, '' );
		if ( self::$dirty || '' === $ver ) {
			self::$dirty = false;
			$ver         = strtolower( wp_generate_password( 12, false ) );
			update_option( self::CACHE_VERSION_OPTION, $ver, true );
		}
		return $ver;
	}

	/**
	 * Flush when a food item is deleted/trashed/restored.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function maybe_flush_post( $post_id ) {
		if ( 'fooditem' === get_post_type( $post_id ) ) {
			self::flush();
		}
	}

	/**
	 * Flush when a food item's categories or dietary labels change.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    Term taxonomy IDs.
	 * @param string $taxonomy  Taxonomy.
	 * @return void
	 */
	public static function maybe_flush_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'food-category', 'dietary' ), true ) ) {
			self::flush();
		}
	}

	/**
	 * Flush when a food item's price, image or food type meta changes outside a full save.
	 *
	 * @param int    $meta_id   Meta ID.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param mixed  $value     Value.
	 * @return void
	 */
	public static function maybe_flush_meta( $meta_id, $object_id, $meta_key, $value ) {
		if ( in_array( $meta_key, array( 'rpress_price', 'rpress_variable_prices', '_variable_pricing', '_thumbnail_id', 'rpress_food_type' ), true ) && 'fooditem' === get_post_type( $object_id ) ) {
			self::flush();
		}
	}

	/**
	 * Whether the current request is a page showing the menu.
	 *
	 * @return bool
	 */
	public static function is_menu_page() {
		if ( ! is_singular() ) {
			return false;
		}
		$post    = get_queried_object();
		$is_menu = false;
		if ( $post instanceof WP_Post ) {
			$is_menu = (int) $post->ID === absint( rpress_get_option( 'food_items_page', 0 ) )
				|| has_shortcode( $post->post_content, 'fooditems' )
				|| ( function_exists( 'has_block' ) && has_block( 'rpress/food-menu', $post ) );
		}
		/**
		 * Whether to treat the current singular page as a menu page for schema output.
		 *
		 * @since 3.5
		 * @param bool         $is_menu Detected value.
		 * @param WP_Post|null $post    Queried post.
		 */
		return (bool) apply_filters( 'rpress_is_menu_schema_page', $is_menu, $post instanceof WP_Post ? $post : null );
	}

	/**
	 * Best-effort detection of an SEO plugin that already outputs Restaurant schema.
	 *
	 * Checks the local business settings of Yoast Local SEO, Rank Math and
	 * All in One SEO, plus a Restaurant-type Rank Math schema saved on the post.
	 * Plain Yoast/Rank Math/AIOSEO without local business data output only
	 * Organization/WebPage schema, so ours is still added there.
	 *
	 * @param int $post_id Current post ID (0 for site-wide checks only).
	 * @return string Plugin name when detected, else ''.
	 */
	public static function seo_plugin_restaurant_schema( $post_id = 0 ) {
		$types = '/"?(Restaurant|FoodEstablishment|FastFoodRestaurant|CafeOrCoffeeShop|Bakery|BarOrPub|Brewery|IceCreamShop|Winery|Distillery)"?/';

		// Yoast Local SEO.
		if ( defined( 'WPSEO_LOCAL_VERSION' ) ) {
			$local = get_option( 'wpseo_local', array() );
			if ( is_array( $local ) && ! empty( $local['business_type'] ) && preg_match( $types, (string) $local['business_type'] ) ) {
				return 'Yoast Local SEO';
			}
		}

		// Rank Math: Local SEO knowledge graph type, or a per-post Restaurant schema.
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			$titles = get_option( 'rank-math-options-titles', array() );
			if ( is_array( $titles ) && isset( $titles['knowledgegraph_type'] ) && 'company' === $titles['knowledgegraph_type'] && ! empty( $titles['local_business_type'] ) && preg_match( $types, (string) $titles['local_business_type'] ) ) {
				return 'Rank Math';
			}
			if ( $post_id ) {
				foreach ( (array) get_post_meta( $post_id ) as $key => $values ) {
					if ( 0 === strpos( $key, 'rank_math_schema_' ) && preg_match( $types, (string) maybe_serialize( $values ) ) ) {
						return 'Rank Math';
					}
				}
			}
		}

		// All in One SEO local business.
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			$local = get_option( 'aioseo_options_localbusiness', '' );
			if ( is_string( $local ) && preg_match( '/"businessType"\s*:\s*"(Restaurant|FoodEstablishment|FastFoodRestaurant|CafeOrCoffeeShop|Bakery|BarOrPub|Brewery|IceCreamShop|Winery|Distillery)"/', $local ) ) {
				return 'All in One SEO';
			}
		}

		return '';
	}

	/**
	 * Whether schema should be printed on the current request.
	 *
	 * @param int $post_id Current post ID.
	 * @return bool
	 */
	public static function should_output( $post_id = 0 ) {
		$seo    = self::seo_plugin_restaurant_schema( $post_id );
		$output = ! rpress_get_option( 'disable_menu_schema', false ) && '' === $seo;
		/**
		 * Whether RestroPress prints its menu schema (JSON-LD) on this page.
		 *
		 * Defaults to true unless the setting is off or an SEO plugin already
		 * outputs Restaurant schema. Return true to force it, false to drop it.
		 *
		 * @since 3.5
		 * @param bool   $output  Whether to output.
		 * @param int    $post_id Current post ID.
		 * @param string $seo     Detected SEO plugin name, or ''.
		 */
		return (bool) apply_filters( 'rpress_output_menu_schema', $output, $post_id, $seo );
	}

	/**
	 * Settings description reflecting detection.
	 *
	 * @return string
	 */
	public static function settings_status_text() {
		$text = esc_html__( 'Check to stop RestroPress adding Restaurant and Menu schema (JSON-LD) to your menu page.', 'restropress' );
		$seo  = did_action( 'plugins_loaded' ) ? self::seo_plugin_restaurant_schema() : '';
		if ( $seo ) {
			/* translators: %s: SEO plugin name. */
			$text .= ' ' . sprintf( esc_html__( '%s already outputs Restaurant schema, so RestroPress skips its own to avoid duplicates.', 'restropress' ), esc_html( $seo ) );
		}
		return $text;
	}

	/**
	 * Print the JSON-LD block in wp_head.
	 *
	 * @return void
	 */
	public static function output() {
		if ( is_admin() || is_feed() || ! self::is_menu_page() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! self::should_output( $post_id ) ) {
			return;
		}
		$json = self::json();
		if ( '' === $json ) {
			return;
		}
		echo "\n<script type=\"application/ld+json\" class=\"rpress-menu-schema\">" . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG.
	}

	/**
	 * Encoded schema, cached in a transient.
	 *
	 * @return string JSON, or '' when there is nothing to describe.
	 */
	public static function json() {
		$lang = apply_filters( 'wpml_current_language', null );
		$key  = 'rpress_menu_schema_' . self::cache_version() . '_' . md5( ( $lang ? $lang : get_locale() ) . '|' . rpress_get_currency() );
		$json = get_transient( $key );
		if ( false === $json ) {
			$data = self::data();
			$json = $data ? (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) : '';
			set_transient( $key, $json, DAY_IN_SECONDS );
		}
		return (string) $json;
	}

	/**
	 * Build the schema array.
	 *
	 * @return array
	 */
	public static function data() {
		$name      = rpress_qr_schema_store_name();
		$menu_page = absint( rpress_get_option( 'food_items_page', 0 ) );
		$menu_url  = $menu_page ? get_permalink( $menu_page ) : '';
		$currency  = rpress_get_currency();

		$restaurant = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Restaurant',
			'@id'      => home_url( '/#restaurant' ),
			'name'     => $name,
			'url'      => home_url( '/' ),
		);
		$image = self::store_image();
		if ( $image ) {
			$restaurant['image'] = $image;
		}
		$phone = trim( (string) rpress_get_option( 'store_phone', '' ) );
		if ( '' !== $phone ) {
			$restaurant['telephone'] = $phone;
		}
		$address = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => trim( preg_replace( '/\s*\n\s*/', ', ', wp_strip_all_tags( (string) rpress_get_option( 'store_address', '' ) ) ) ),
				'addressLocality' => trim( (string) rpress_get_option( 'store_city', '' ) ),
				'addressRegion'   => trim( (string) rpress_get_option( 'base_state', '' ) ),
				'postalCode'      => trim( (string) rpress_get_option( 'store_postcode', '' ) ),
				'addressCountry'  => trim( (string) rpress_get_option( 'base_country', '' ) ),
			),
			'strlen'
		);
		if ( count( $address ) > 1 ) {
			$restaurant['address'] = $address;
		}
		$cuisine = trim( wp_strip_all_tags( (string) rpress_get_option( 'restaurant_cuisine', '' ) ) );
		if ( '' !== $cuisine ) {
			$restaurant['servesCuisine'] = array_values( array_filter( array_map( 'trim', explode( ',', $cuisine ) ), 'strlen' ) );
		}

		$menu = array(
			'@type' => 'Menu',
			'name'  => $menu_page ? wp_strip_all_tags( get_the_title( $menu_page ) ) : __( 'Menu', 'restropress' ),
		);
		if ( $menu_url ) {
			$menu['url'] = $menu_url;
		}

		/**
		 * Maximum number of menu items described in the schema.
		 *
		 * @since 3.5
		 * @param int $max Default 500.
		 */
		$max   = max( 1, (int) apply_filters( 'rpress_menu_schema_max_items', 500 ) );
		$posts = get_posts(
			array(
				'post_type'              => 'fooditem',
				'post_status'            => 'publish',
				'posts_per_page'         => $max,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_term_cache' => true,
				'update_post_meta_cache' => true,
			)
		);

		$sections   = array();
		$loose      = array();
		$categories = get_terms(
			array(
				'taxonomy'   => 'food-category',
				'hide_empty' => true,
			)
		);
		$order      = array();
		if ( ! is_wp_error( $categories ) ) {
			foreach ( $categories as $i => $term ) {
				$order[ $term->term_id ] = $i;
			}
		}

		foreach ( $posts as $post ) {
			$item = self::menu_item( $post, $currency );
			if ( ! $item ) {
				continue;
			}
			$terms = get_the_terms( $post, 'food-category' );
			if ( ! $terms || is_wp_error( $terms ) ) {
				$loose[] = $item;
				continue;
			}
			// One section per item (the first by menu order) to avoid duplicate items.
			usort(
				$terms,
				function ( $a, $b ) use ( $order ) {
					$x = isset( $order[ $a->term_id ] ) ? $order[ $a->term_id ] : PHP_INT_MAX;
					$y = isset( $order[ $b->term_id ] ) ? $order[ $b->term_id ] : PHP_INT_MAX;
					return $x - $y;
				}
			);
			$term = $terms[0];
			if ( ! isset( $sections[ $term->term_id ] ) ) {
				$section = array(
					'@type' => 'MenuSection',
					'name'  => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				);
				$desc    = trim( wp_strip_all_tags( (string) $term->description ) );
				if ( '' !== $desc ) {
					$section['description'] = $desc;
				}
				$section['hasMenuItem']       = array();
				$sections[ $term->term_id ] = $section;
			}
			$sections[ $term->term_id ]['hasMenuItem'][] = $item;
		}

		uksort(
			$sections,
			function ( $a, $b ) use ( $order ) {
				$x = isset( $order[ $a ] ) ? $order[ $a ] : PHP_INT_MAX;
				$y = isset( $order[ $b ] ) ? $order[ $b ] : PHP_INT_MAX;
				return $x - $y;
			}
		);
		if ( $sections ) {
			$menu['hasMenuSection'] = array_values( $sections );
		}
		if ( $loose ) {
			$menu['hasMenuItem'] = $loose;
		}
		if ( ! $sections && ! $loose ) {
			return array();
		}
		$restaurant['hasMenu'] = $menu;

		/**
		 * Filter the menu schema before it is encoded and cached.
		 *
		 * @since 3.5
		 * @param array $restaurant Restaurant schema array.
		 */
		return (array) apply_filters( 'rpress_menu_schema_data', $restaurant );
	}

	/**
	 * Schema for one food item.
	 *
	 * @param WP_Post $post     Food item.
	 * @param string  $currency Currency code.
	 * @return array Empty when the item cannot be described.
	 */
	public static function menu_item( $post, $currency ) {
		$name = trim( wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' === $name ) {
			return array();
		}
		$item = array(
			'@type' => 'MenuItem',
			'name'  => $name,
		);

		$desc = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		$desc = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( (string) $desc ) ) ) );
		if ( '' !== $desc ) {
			$item['description'] = wp_html_excerpt( html_entity_decode( $desc, ENT_QUOTES, 'UTF-8' ), 300, '...' );
		}

		$image = get_the_post_thumbnail_url( $post, 'large' );
		if ( $image ) {
			$item['image'] = $image;
		}

		$price = rpress_has_variable_prices( $post->ID ) ? rpress_get_lowest_price_option( $post->ID ) : rpress_get_fooditem_price( $post->ID );
		$price = (float) $price;
		if ( $price > 0 ) {
			$item['offers'] = array(
				'@type'         => 'Offer',
				'price'         => number_format( $price, 2, '.', '' ),
				'priceCurrency' => $currency,
			);
		}

		$diets = self::diets( $post );
		if ( $diets ) {
			$item['suitableForDiet'] = 1 === count( $diets ) ? $diets[0] : $diets;
		}

		/**
		 * Filter one MenuItem in the menu schema.
		 *
		 * @since 3.5
		 * @param array   $item MenuItem array.
		 * @param WP_Post $post Food item.
		 */
		return (array) apply_filters( 'rpress_menu_schema_item', $item, $post );
	}

	/**
	 * Map dietary labels (and the veg food type) to schema.org RestrictedDiet values.
	 *
	 * @param WP_Post $post Food item.
	 * @return array Diet URLs.
	 */
	public static function diets( $post ) {
		/**
		 * Dietary label slug to schema.org RestrictedDiet map.
		 *
		 * @since 3.5
		 * @param array $map Slug => diet name (without the https://schema.org/ prefix).
		 */
		$map   = apply_filters(
			'rpress_menu_schema_diet_map',
			array(
				'vegetarian'   => 'VegetarianDiet',
				'veg'          => 'VegetarianDiet',
				'vegan'        => 'VeganDiet',
				'gluten-free'  => 'GlutenFreeDiet',
				'glutenfree'   => 'GlutenFreeDiet',
				'halal'        => 'HalalDiet',
				'kosher'       => 'KosherDiet',
				'hindu'        => 'HinduDiet',
				'diabetic'     => 'DiabeticDiet',
				'lactose-free' => 'LowLactoseDiet',
				'dairy-free'   => 'LowLactoseDiet',
				'low-lactose'  => 'LowLactoseDiet',
				'low-calorie'  => 'LowCalorieDiet',
				'low-fat'      => 'LowFatDiet',
				'low-salt'     => 'LowSaltDiet',
				'low-sodium'   => 'LowSaltDiet',
			)
		);
		$found = array();
		$terms = get_the_terms( $post, 'dietary' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$slug = sanitize_title( $term->slug );
				$name = sanitize_title( $term->name );
				if ( isset( $map[ $slug ] ) ) {
					$found[] = $map[ $slug ];
				} elseif ( isset( $map[ $name ] ) ) {
					$found[] = $map[ $name ];
				}
			}
		}
		if ( 'veg' === get_post_meta( $post->ID, 'rpress_food_type', true ) ) {
			$found[] = 'VegetarianDiet';
		}
		$found = array_values( array_unique( $found ) );
		return array_map(
			function ( $diet ) {
				return 'https://schema.org/' . $diet;
			},
			$found
		);
	}

	/**
	 * Restaurant image: receipt logo, theme logo, then site icon.
	 *
	 * @return string
	 */
	private static function store_image() {
		$logo = (string) rpress_get_option( 'store_logo', '' );
		if ( '' !== $logo && preg_match( '#^https?://#i', $logo ) ) {
			return esc_url_raw( $logo );
		}
		$custom = (int) get_theme_mod( 'custom_logo' );
		if ( $custom ) {
			$url = wp_get_attachment_image_url( $custom, 'full' );
			if ( $url ) {
				return $url;
			}
		}
		return (string) get_site_icon_url( 512 );
	}
}
