<?php
/**
 * Gutenberg blocks (3.4)
 *
 * Server-rendered blocks wrapping the existing shortcodes, so storefront
 * behavior is identical whether a page uses blocks or shortcodes. No build
 * toolchain: the editor script is plain JS and block metadata lives in
 * blocks/<name>/block.json.
 *
 * @package     RPRESS
 * @subpackage  Blocks
 * @since       3.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class RPRESS_Blocks {

	/**
	 * Block folder names under blocks/, mapped to their render callbacks.
	 *
	 * @var array
	 */
	private static $blocks = array(
		'food-menu'     => 'render_food_menu',
		'checkout'      => 'render_checkout',
		'order-history' => 'render_order_history',
		'receipt'       => 'render_receipt',
		'food-search'   => 'render_food_search',
		'opening-hours' => 'render_opening_hours',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'block_category' ) );
	}

	/**
	 * Register the shared editor script, block styles, and all blocks.
	 *
	 * @return void
	 */
	public static function register() {
		$js_path  = RP_PLUGIN_DIR . 'assets/js/admin/rpress-blocks.js';
		$css_path = RP_PLUGIN_DIR . 'assets/css/rpress-blocks.css';
		wp_register_script(
			'rpress-blocks-editor',
			RP_PLUGIN_URL . 'assets/js/admin/rpress-blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n', 'wp-data', 'wp-core-data' ),
			file_exists( $js_path ) ? RP_VERSION . '.' . filemtime( $js_path ) : RP_VERSION,
			true
		);
		wp_register_style(
			'rpress-blocks',
			RP_PLUGIN_URL . 'assets/css/rpress-blocks.css',
			array(),
			file_exists( $css_path ) ? RP_VERSION . '.' . filemtime( $css_path ) : RP_VERSION
		);
		// Storefront stylesheet for editor previews, so ServerSideRender output
		// looks like the real menu (the frontend 'rpress-styles' handle only
		// exists on the frontend).
		wp_register_style(
			'rpress-storefront-editor',
			RP_PLUGIN_URL . 'assets/css/rpress.css',
			array(),
			RP_VERSION
		);

		foreach ( self::$blocks as $folder => $callback ) {
			register_block_type(
				RP_PLUGIN_DIR . 'blocks/' . $folder,
				array( 'render_callback' => array( __CLASS__, $callback ) )
			);
		}
	}

	/**
	 * Add the RestroPress block category, first in the list.
	 *
	 * @param array $categories Registered categories.
	 * @return array
	 */
	public static function block_category( $categories ) {
		return array_merge(
			array(
				array(
					'slug'  => 'restropress',
					'title' => __( 'RestroPress', 'restropress' ),
				),
			),
			$categories
		);
	}

	/**
	 * Food Menu block: wraps [fooditems].
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_food_menu( $attributes ) {
		$layout = isset( $attributes['layout'] ) ? sanitize_key( $attributes['layout'] ) : '';

		$shortcode = '[fooditems';
		if ( ! empty( $attributes['category'] ) ) {
			$shortcode .= ' category="' . esc_attr( $attributes['category'] ) . '"';
		}
		if ( ! empty( $attributes['categoryExclude'] ) ) {
			$shortcode .= ' category_exclude="' . esc_attr( $attributes['categoryExclude'] ) . '"';
		}
		if ( ! empty( $attributes['orderby'] ) ) {
			$shortcode .= ' fooditem_orderby="' . esc_attr( $attributes['orderby'] ) . '"';
		}
		if ( ! empty( $attributes['order'] ) ) {
			$shortcode .= ' fooditem_order="' . esc_attr( $attributes['order'] ) . '"';
		}
		$shortcode .= ']';

		$override = null;
		if ( in_array( $layout, array( 'list', 'grid' ), true ) ) {
			$override = function () use ( $layout ) {
				return $layout;
			};
			add_filter( 'rpress_get_option_template', $override );
		}

		$html = do_shortcode( $shortcode );

		if ( $override ) {
			remove_filter( 'rpress_get_option_template', $override );
		}

		// Layout toggles: each disabled region adds a class the block styles
		// hide, so merchants can e.g. drop the cart sidebar for a full-width
		// menu. First taste of the composable layout system planned for 3.5.
		$classes = array();
		if ( isset( $attributes['showCategories'] ) && ! $attributes['showCategories'] ) {
			$classes[] = 'rpress-menu-hide-categories';
		}
		if ( isset( $attributes['showServiceBar'] ) && ! $attributes['showServiceBar'] ) {
			$classes[] = 'rpress-menu-hide-servicebar';
		}
		if ( isset( $attributes['showSearch'] ) && ! $attributes['showSearch'] ) {
			$classes[] = 'rpress-menu-hide-search';
		}
		if ( isset( $attributes['showCart'] ) && ! $attributes['showCart'] ) {
			$classes[] = 'rpress-menu-hide-cart';
		}

		// In the editor's live preview, show a clean menu: the service toggle
		// and cart sidebar are session widgets that make no sense in a canvas.
		if ( self::is_editor_preview() ) {
			$classes[] = 'rpress-block-editor-preview';
		}

		$wrapper = function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( $classes ? array( 'class' => implode( ' ', $classes ) ) : array() )
			: ( $classes ? 'class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '' );
		return '<div ' . $wrapper . '>' . $html . '</div>';
	}

	/**
	 * Whether this render is the block editor's ServerSideRender preview.
	 *
	 * @since 3.4
	 * @return bool
	 */
	private static function is_editor_preview() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST
			&& isset( $_GET['context'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['context'] ) );
	}

	/**
	 * Cart & Checkout block: wraps [fooditem_checkout] or [fooditem_cart].
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_checkout( $attributes ) {
		$mode = isset( $attributes['mode'] ) && 'cart' === $attributes['mode'] ? 'cart' : 'checkout';
		return self::wrap( do_shortcode( 'cart' === $mode ? '[fooditem_cart]' : '[fooditem_checkout]' ) );
	}

	/**
	 * Order History block: wraps [order_history] or [fooditem_history].
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_order_history( $attributes ) {
		$type = isset( $attributes['historyType'] ) && 'items' === $attributes['historyType'] ? 'items' : 'orders';
		return self::wrap( do_shortcode( 'items' === $type ? '[fooditem_history]' : '[order_history]' ) );
	}

	/**
	 * Order Receipt block: wraps [rpress_receipt] with visibility toggles.
	 * The receipt template reads these through FILTER_VALIDATE_BOOLEAN, so
	 * "0" and "1" strings map cleanly.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_receipt( $attributes ) {
		$map = array(
			'showPrice'         => 'price',
			'showDiscount'      => 'discount',
			'showProducts'      => 'products',
			'showDate'          => 'date',
			'showNotes'         => 'notes',
			'showPaymentMethod' => 'payment_method',
			'showPaymentId'     => 'payment_id',
		);
		$shortcode = '[rpress_receipt';
		foreach ( $map as $attr => $att_name ) {
			$enabled    = ! isset( $attributes[ $attr ] ) || ! empty( $attributes[ $attr ] );
			$shortcode .= ' ' . $att_name . '="' . ( $enabled ? '1' : '0' ) . '"';
		}
		$shortcode .= ']';
		return self::wrap( do_shortcode( $shortcode ) );
	}

	/**
	 * Food Search block: wraps [foodsearch].
	 *
	 * @return string
	 */
	public static function render_food_search() {
		return self::wrap( do_shortcode( '[foodsearch]' ) );
	}

	/**
	 * Opening Hours block: renders the weekly basic store hours.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_opening_hours( $attributes ) {
		$highlight_today = ! isset( $attributes['highlightToday'] ) || ! empty( $attributes['highlightToday'] );
		$show_closed     = ! isset( $attributes['showClosedDays'] ) || ! empty( $attributes['showClosedDays'] );
		$show_holidays   = ! empty( $attributes['showHolidays'] );

		$timezone     = rpress_get_wp_timezone();
		$now          = rpress_get_wp_now();
		$today_number = (int) $now->format( 'N' );
		$time_format  = get_option( 'time_format' );
		if ( '24hrs' === rpress_get_option( 'store_time_format' ) ) {
			$time_format = 'H:i';
		} elseif ( '12hrs' === rpress_get_option( 'store_time_format' ) ) {
			$time_format = 'g:i a';
		}

		$rows = '';
		for ( $day_number = 1; $day_number <= 7; $day_number++ ) {
			// The next date (today included) that falls on this weekday, so
			// per-day hours resolve through the same helper the storefront uses.
			$offset = ( $day_number - $today_number + 7 ) % 7;
			$date   = $now->modify( '+' . $offset . ' day' );
			// Skip holidays here: these rows show the recurring weekly schedule
			// and holidays get their own line below.
			$hours  = rpress_get_store_hours_for_date( $date->format( 'Y-m-d' ), true );

			if ( ! empty( $hours['closed'] ) && ! $show_closed ) {
				continue;
			}

			$label = wp_date( 'l', $date->getTimestamp(), $timezone );
			if ( ! empty( $hours['closed'] ) ) {
				$times = '<span class="rpress-hours-closed-label">' . esc_html__( 'Closed', 'restropress' ) . '</span>';
			} else {
				$open_ts  = rpress_get_wp_timestamp( $date->format( 'Y-m-d' ) . ' ' . $hours['open'] );
				$close_ts = rpress_get_wp_timestamp( $date->format( 'Y-m-d' ) . ' ' . $hours['close'] );
				if ( false === $open_ts || false === $close_ts ) {
					$times = esc_html( $hours['open'] . ' – ' . $hours['close'] );
				} else {
					$times = esc_html( wp_date( $time_format, $open_ts, $timezone ) . ' – ' . wp_date( $time_format, $close_ts, $timezone ) );
				}
			}

			$row_class = 'rpress-opening-hours-row';
			if ( $highlight_today && $day_number === $today_number ) {
				$row_class .= ' is-today';
			}
			$rows .= '<div class="' . esc_attr( $row_class ) . '"><span class="rpress-opening-hours-day">' . esc_html( $label ) . '</span><span class="rpress-opening-hours-times">' . $times . '</span></div>';
		}

		$holidays_html = '';
		// With "always order" on, holidays never close the store, so listing
		// them as closure dates would mislead customers.
		if ( ! empty( rpress_get_option( 'enable_always_open' ) ) ) {
			$show_holidays = false;
		}
		if ( $show_holidays && function_exists( 'rpress_get_basic_holidays' ) ) {
			$upcoming = array();
			$today    = $now->format( 'Y-m-d' );
			foreach ( rpress_get_basic_holidays() as $holiday ) {
				if ( $holiday >= $today ) {
					$ts = rpress_get_wp_timestamp( $holiday );
					if ( false !== $ts ) {
						$upcoming[] = wp_date( get_option( 'date_format' ), $ts, $timezone );
					}
				}
			}
			if ( $upcoming ) {
				$holidays_html = '<div class="rpress-opening-hours-holidays">'
					. esc_html__( 'Closed on:', 'restropress' ) . ' '
					. esc_html( implode( ', ', $upcoming ) )
					. '</div>';
			}
		}

		$wrapper = function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( array( 'class' => 'rpress-opening-hours' ) )
			: 'class="rpress-opening-hours"';

		return '<div ' . $wrapper . '>' . $rows . $holidays_html . '</div>';
	}

	/**
	 * Standard block wrapper so alignment and spacing supports apply.
	 *
	 * @param string $html Inner markup.
	 * @return string
	 */
	private static function wrap( $html ) {
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';
		return '<div ' . $wrapper . '>' . $html . '</div>';
	}
}
RPRESS_Blocks::init();
