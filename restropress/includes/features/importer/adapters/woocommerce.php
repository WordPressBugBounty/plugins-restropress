<?php
/**
 * Import source: WooCommerce-based ordering plugins on this site
 * (Orderable, WP Cafe, FoodMaster and plain WooCommerce menus).
 *
 * Reads published products straight from this WordPress site while
 * WooCommerce is active. WooCommerce data is only read, never changed.
 *
 *   product_cat            -> menu category (first category; others are added too)
 *   simple product         -> item with its price
 *   variable product       -> item with one size per variation; the size name
 *                             is the variation's attribute values ("Large, Thin")
 *                             and the price is the variation price
 *   product image          -> featured image (same attachment, no download)
 *   short description, or description -> item description (tags stripped)
 *   product tags such as "Vegan" or "Gluten free" -> dietary labels
 *   WooCommerce Product Add-Ons (_product_addons meta, per product):
 *     multiple_choice -> single-choice group, checkbox -> multi-choice group,
 *     required and option prices are kept. Other field types (text, file
 *     upload, quantity) and global add-on groups are not mapped.
 *   Orderable and WP Cafe add-ons live in their own storage formats that are
 *     not mapped yet; the preview says so when those plugins are found.
 *   Store settings: the first standard tax rate, and the cost of the first
 *     flat-rate shipping method as the flat delivery fee (best effort).
 *
 * @package RestroPress/Importer
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce adapter.
 */
class RPress_Import_Source_WooCommerce {

	/**
	 * Source args.
	 *
	 * @return array
	 */
	public static function args() {
		return array(
			'label'       => __( 'WooCommerce, Orderable, WP Cafe or FoodMaster', 'restropress' ),
			'description' => __( 'Copy the food products already on this site into RestroPress. Your WooCommerce products are not changed.', 'restropress' ),
			'accepts'     => array(),
			'available'   => array( __CLASS__, 'available' ),
			'parse'       => array( __CLASS__, 'parse' ),
			'field_map'   => array( __CLASS__, 'field_map' ),
			'help'        => '<p>' . esc_html__( 'Reads published WooCommerce products, categories, variations, images and WooCommerce Product Add-Ons from this site. Nothing in WooCommerce is changed, so you can compare both menus before you switch.', 'restropress' ) . '</p>',
			'priority'    => 20,
		);
	}

	/**
	 * WooCommerce must be active.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' );
	}

	/**
	 * Products found, for the start screen.
	 *
	 * @return int
	 */
	public static function product_count() {
		if ( ! post_type_exists( 'product' ) ) {
			return 0;
		}
		$counts = wp_count_posts( 'product' );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * Field map for the preview.
	 *
	 * @return array
	 */
	public static function field_map() {
		return array(
			'product_cat'                   => __( 'Menu category', 'restropress' ),
			'Product name'                  => __( 'Menu item name', 'restropress' ),
			'Short description'             => __( 'Item description', 'restropress' ),
			'Regular / sale price'          => __( 'Price (current price)', 'restropress' ),
			'Variations (attribute values)' => __( 'Price options with the variation price', 'restropress' ),
			'Product image'                 => __( 'Featured image (same media file)', 'restropress' ),
			'Product tags'                  => __( 'Dietary labels when they match (Vegan, Halal, ...)', 'restropress' ),
			'Product Add-Ons'               => __( 'Add-on groups (choice and checkbox fields only)', 'restropress' ),
			'Tax rate / flat rate shipping' => __( 'Tax rate and flat delivery fee (optional)', 'restropress' ),
		);
	}

	/**
	 * Read the products into the model.
	 *
	 * @param mixed $file Unused (this source reads the site).
	 * @return array|WP_Error
	 */
	public static function parse( $file = null ) {
		if ( ! self::available() ) {
			return new WP_Error( 'rpress_import_woo_inactive', __( 'WooCommerce is not active on this site.', 'restropress' ) );
		}

		$model = array(
			'currency'   => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'categories' => array(),
			'settings'   => self::settings(),
			'warnings'   => array(),
		);

		if ( post_type_exists( 'orderable_addons' ) || class_exists( 'Orderable_Addons_Pro' ) ) {
			$model['warnings'][] = __( 'Orderable product add-ons are not imported. Recreate them as add-on groups in RestroPress.', 'restropress' );
		}
		if ( class_exists( 'WpCafe' ) || defined( 'WPCAFE_VERSION' ) ) {
			$model['warnings'][] = __( 'WP Cafe extra options are not imported. Recreate them as add-on groups in RestroPress.', 'restropress' );
		}
		if ( post_type_exists( 'global_product_addon' ) ) {
			$model['warnings'][] = __( 'Global WooCommerce add-on groups are not imported; add-ons set on each product are.', 'restropress' );
		}

		$categories = array();
		$page       = 1;
		do {
			$products = wc_get_products(
				array(
					'status'  => 'publish',
					'limit'   => 100,
					'page'    => $page,
					'orderby' => 'menu_order',
					'order'   => 'ASC',
				)
			);
			foreach ( $products as $product ) {
				$item = self::parse_product( $product );
				if ( ! $item ) {
					continue;
				}
				$names    = self::category_names( $product->get_id() );
				$category = array_shift( $names );
				$item['also_categories'] = $names;
				if ( ! isset( $categories[ $category ] ) ) {
					$categories[ $category ] = array(
						'name'  => $category,
						'items' => array(),
					);
				}
				$categories[ $category ]['items'][] = $item;
			}
			$page++;
		} while ( count( $products ) === 100 );

		$model['categories'] = array_values( $categories );
		return $model;
	}

	/**
	 * Category names for a product (first is the primary one).
	 *
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	protected static function category_names( $product_id ) {
		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'orderby' => 'term_order' ) );
		$names = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$names[] = wp_specialchars_decode( $term->name, ENT_QUOTES );
			}
		}
		return $names ? $names : array( __( 'Uncategorized', 'restropress' ) );
	}

	/**
	 * One product.
	 *
	 * @param WC_Product $product Product.
	 * @return array|null
	 */
	protected static function parse_product( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_type' ) ) {
			return null;
		}
		$type     = $product->get_type();
		$warnings = array();
		if ( ! in_array( $type, array( 'simple', 'variable' ), true ) ) {
			return null;
		}

		$description = $product->get_short_description();
		if ( '' === trim( wp_strip_all_tags( $description ) ) ) {
			$description = $product->get_description();
		}

		$sizes = array();
		if ( 'variable' === $type ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation || ! $variation->exists() || 'publish' !== $variation->get_status() ) {
					continue;
				}
				$name = function_exists( 'wc_get_formatted_variation' ) ? wc_get_formatted_variation( $variation, true, false, false ) : '';
				$name = trim( wp_strip_all_tags( (string) $name ) );
				if ( '' === $name ) {
					/* translators: %d: variation id */
					$name = sprintf( __( 'Option %d', 'restropress' ), $variation_id );
				}
				if ( '' === (string) $variation->get_price() ) {
					/* translators: %s: variation name */
					$warnings[] = sprintf( __( 'Variation "%s" has no price and was skipped.', 'restropress' ), $name );
					continue;
				}
				$sizes[] = array(
					'name'  => $name,
					'price' => (float) $variation->get_price(),
				);
			}
			if ( empty( $sizes ) ) {
				$warnings[] = __( 'Variable product without priced variations.', 'restropress' );
			}
		}

		$tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );

		return array(
			'name'         => wp_specialchars_decode( $product->get_name(), ENT_QUOTES ),
			'description'  => wp_strip_all_tags( $description ),
			'price'        => 'variable' === $type ? '' : $product->get_price(),
			'sizes'        => $sizes,
			'addon_groups' => self::addons( $product->get_id(), $warnings ),
			'image_id'     => (int) $product->get_image_id(),
			'dietary'      => is_wp_error( $tags ) ? array() : self::dietary_tags( $tags ),
			'status'       => 'publish',
			'source_ref'   => (string) $product->get_id(),
			'warnings'     => $warnings,
		);
	}

	/**
	 * Keep only product tags that are dietary labels.
	 *
	 * @param string[] $tags Tag names.
	 * @return string[]
	 */
	protected static function dietary_tags( $tags ) {
		$known = array( 'vegetarian', 'vegan', 'glutenfree', 'dairyfree', 'nutfree', 'halal', 'kosher', 'spicy', 'hot', 'veg', 'nonveg' );
		$out   = array();
		foreach ( (array) $tags as $tag ) {
			if ( in_array( preg_replace( '/[^a-z]/', '', strtolower( $tag ) ), $known, true ) ) {
				$out[] = $tag;
			}
		}
		return $out;
	}

	/**
	 * WooCommerce Product Add-Ons stored on the product.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $warnings   Item warnings (by reference).
	 * @return array
	 */
	protected static function addons( $product_id, &$warnings ) {
		$raw = get_post_meta( $product_id, '_product_addons', true );
		if ( empty( $raw ) || ! is_array( $raw ) ) {
			return array();
		}
		$groups = array();
		foreach ( $raw as $addon ) {
			if ( ! is_array( $addon ) || empty( $addon['name'] ) ) {
				continue;
			}
			$type = isset( $addon['type'] ) ? $addon['type'] : '';
			if ( ! in_array( $type, array( 'multiple_choice', 'checkbox' ), true ) ) {
				/* translators: 1: add-on name, 2: add-on type */
				$warnings[] = sprintf( __( 'Add-on "%1$s" (%2$s field) is not supported and was skipped.', 'restropress' ), $addon['name'], $type );
				continue;
			}
			$options = array();
			foreach ( isset( $addon['options'] ) && is_array( $addon['options'] ) ? $addon['options'] : array() as $option ) {
				if ( ! is_array( $option ) || ! isset( $option['label'] ) || '' === trim( (string) $option['label'] ) ) {
					continue;
				}
				if ( isset( $option['price_type'] ) && 'percentage_based' === $option['price_type'] ) {
					/* translators: %s: option label */
					$warnings[] = sprintf( __( 'Add-on "%s" has a percentage price; imported at 0.00.', 'restropress' ), $option['label'] );
					$option['price'] = 0;
				}
				$options[] = array(
					'name'  => $option['label'],
					'price' => isset( $option['price'] ) ? $option['price'] : 0,
				);
			}
			$single   = 'multiple_choice' === $type;
			$required = ! empty( $addon['required'] );
			$groups[] = array(
				'name'     => $addon['name'],
				'type'     => $single ? 'single' : 'multiple',
				'required' => $required,
				'min'      => $required ? 1 : 0,
				'max'      => $single ? 1 : 0,
				'options'  => $options,
			);
		}
		return $groups;
	}

	/**
	 * Tax rate and flat delivery fee from WooCommerce settings.
	 *
	 * @return array
	 */
	protected static function settings() {
		global $wpdb;
		$settings = array();

		if ( 'yes' === get_option( 'woocommerce_calc_taxes' ) ) {
			$table = $wpdb->prefix . 'woocommerce_tax_rates';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				$rate = $wpdb->get_var( "SELECT tax_rate FROM {$table} WHERE tax_rate_class = '' ORDER BY tax_rate_order ASC, tax_rate_id ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( null !== $rate && '' !== $rate ) {
					$settings['tax_rate'] = $rate;
				}
			}
		}

		$table = $wpdb->prefix . 'woocommerce_shipping_zone_methods';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$instance = $wpdb->get_var( "SELECT instance_id FROM {$table} WHERE method_id = 'flat_rate' AND is_enabled = 1 ORDER BY zone_id ASC, method_order ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $instance ) {
				$options = get_option( 'woocommerce_flat_rate_' . absint( $instance ) . '_settings' );
				if ( is_array( $options ) && isset( $options['cost'] ) && is_numeric( $options['cost'] ) ) {
					$settings['delivery_fee'] = $options['cost'];
				}
			}
		}

		return $settings;
	}
}
