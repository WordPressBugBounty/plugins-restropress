<?php
/**
 * Import framework: the intermediate model.
 *
 * Every source adapter returns this shape from its parse() callback. The
 * engine never trusts adapter output: normalize() sanitizes every field and
 * collects per-item warnings before anything is previewed or saved.
 *
 * Model shape (all keys optional except categories):
 *
 *   array(
 *     'currency'   => 'USD',
 *     'categories' => array(
 *       array(
 *         'name'        => 'Pizza',
 *         'description' => '',
 *         'items'       => array(
 *           array(
 *             'name'            => 'Margherita',
 *             'description'     => 'Tomato, mozzarella',
 *             'price'           => '8.00',          // Base price ('' when sizes carry the price).
 *             'sizes'           => array( array( 'name' => 'Small', 'price' => '8.00' ) ), // Absolute prices.
 *             'addon_groups'    => array(
 *               array(
 *                 'name'     => 'Extra toppings',
 *                 'type'     => 'multiple',       // single|multiple.
 *                 'required' => false,
 *                 'min'      => 0,
 *                 'max'      => 3,                // 0 = no limit.
 *                 'options'  => array(
 *                   array(
 *                     'name'    => 'Olives',
 *                     'price'   => '1.00',         // Price for every size.
 *                     'prices'  => array( 'Large' => '1.50' ), // Optional per-size overrides.
 *                     'default' => false,
 *                   ),
 *                 ),
 *               ),
 *             ),
 *             'image'           => 'https://example.com/margherita.jpg', // Sideloaded.
 *             'image_id'        => 0,             // Existing attachment on this site (WooCommerce).
 *             'dietary'         => array( 'Vegetarian' ),
 *             'allergens'       => array( 'Milk' ),
 *             'food_type'       => 'veg',         // veg|non_veg|''.
 *             'status'          => 'publish',     // publish|draft.
 *             'also_categories' => array(),       // Extra category names.
 *             'source_ref'      => '58424',       // Id in the source system, for the log.
 *             'warnings'        => array(),
 *           ),
 *         ),
 *       ),
 *     ),
 *     'settings'   => array(
 *       'store_hours'          => array( 1 => array( 'open' => '9:00am', 'close' => '10:00pm', 'closed' => false ) ), // 1 = Monday.
 *       'holidays'             => array( '2026-12-25' ),
 *       'delivery_fee'         => '2.50',
 *       'delivery_zones'       => array( array( 'name' => 'Zone A', 'fee' => '2.50', 'min_order' => '10.00' ) ),
 *       'minimum_order'        => '10.00',
 *       'minimum_order_pickup' => '',
 *       'tax_rate'             => '8.5',
 *     ),
 *     'customers'  => array( array( 'email' => '', 'first_name' => '', 'last_name' => '', 'phone' => '' ) ),
 *     'warnings'   => array(),
 *   )
 *
 * @package RestroPress/Importer
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Intermediate model helpers.
 */
class RPress_Importer_Model {

	/**
	 * Known dietary labels, keyed by a normalized spelling. Values match the
	 * default terms RestroPress seeds in the dietary taxonomy.
	 *
	 * @var array
	 */
	protected static $dietary_aliases = array(
		'vegetarian'  => 'Vegetarian',
		'veg'         => 'Vegetarian',
		'vegan'       => 'Vegan',
		'glutenfree'  => 'Gluten-free',
		'gf'          => 'Gluten-free',
		'dairyfree'   => 'Dairy-free',
		'lactosefree' => 'Dairy-free',
		'nutfree'     => 'Nut-free',
		'halal'       => 'Halal',
		'kosher'      => 'Kosher',
		'spicy'       => 'Spicy',
		'hot'         => 'Spicy',
		'raw'         => 'Raw',
	);

	/**
	 * An empty model.
	 *
	 * @return array
	 */
	public static function empty_model() {
		return array(
			'currency'   => '',
			'categories' => array(),
			'settings'   => array(),
			'customers'  => array(),
			'warnings'   => array(),
		);
	}

	/**
	 * Normalize a price the same way the AI menu importer does
	 * (RPress_Onboarding::clean_price), so imported prices match.
	 *
	 * @param mixed $price Raw price.
	 * @return string Price with two decimals, or '' when empty.
	 */
	public static function clean_price( $price ) {
		if ( is_float( $price ) || is_int( $price ) ) {
			return number_format( (float) $price, 2, '.', '' );
		}
		$price = preg_replace( '/[^0-9.,-]/', '', (string) $price );
		if ( '' === $price || '-' === $price ) {
			return '';
		}

		$comma = strrpos( $price, ',' );
		$dot   = strrpos( $price, '.' );
		if ( false !== $comma && false !== $dot ) {
			$decimal = $comma > $dot ? ',' : '.';
			$price   = str_replace( ',' === $decimal ? '.' : ',', '', $price );
			$price   = str_replace( $decimal, '.', $price );
		} elseif ( false !== $comma ) {
			$digits_after = strlen( $price ) - $comma - 1;
			$price        = ( $digits_after > 0 && $digits_after <= 2 ) ? str_replace( ',', '.', $price ) : str_replace( ',', '', $price );
		} elseif ( substr_count( $price, '.' ) > 1 ) {
			$price = str_replace( '.', '', $price );
		}

		return number_format( max( 0, (float) $price ), 2, '.', '' );
	}

	/**
	 * Validate an image URL: http(s) only, no credentials.
	 *
	 * @param string $url Raw URL.
	 * @return string Clean URL or ''.
	 */
	public static function clean_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Map free-form tags onto dietary labels. Unknown tags are kept as typed
	 * (title-cased) so nothing is lost.
	 *
	 * @param array $tags Raw tags.
	 * @return array { dietary: string[], food_type: string }
	 */
	public static function map_dietary( $tags ) {
		$out       = array();
		$food_type = '';
		foreach ( (array) $tags as $tag ) {
			$tag = trim( sanitize_text_field( (string) $tag ) );
			if ( '' === $tag ) {
				continue;
			}
			$key = preg_replace( '/[^a-z]/', '', strtolower( $tag ) );
			if ( in_array( $key, array( 'nonveg', 'nonvegetarian' ), true ) ) {
				$food_type = 'non_veg';
				continue;
			}
			$label = isset( self::$dietary_aliases[ $key ] ) ? self::$dietary_aliases[ $key ] : ucwords( strtolower( str_replace( '_', ' ', $tag ) ) );
			if ( in_array( $label, array( 'Vegetarian', 'Vegan' ), true ) && '' === $food_type ) {
				$food_type = 'veg';
			}
			$out[ strtolower( $label ) ] = $label;
		}
		return array(
			'dietary'   => array_values( $out ),
			'food_type' => $food_type,
		);
	}

	/**
	 * Sanitize a raw model from an adapter.
	 *
	 * @param array $raw Adapter output.
	 * @return array Clean model.
	 */
	public static function normalize( $raw ) {
		$model = self::empty_model();
		if ( ! is_array( $raw ) ) {
			return $model;
		}

		$model['currency'] = isset( $raw['currency'] ) ? strtoupper( substr( sanitize_key( $raw['currency'] ), 0, 3 ) ) : '';
		$model['warnings'] = self::clean_strings( isset( $raw['warnings'] ) ? $raw['warnings'] : array() );

		$categories = isset( $raw['categories'] ) && is_array( $raw['categories'] ) ? $raw['categories'] : array();
		$by_name    = array();
		foreach ( $categories as $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}
			$name = isset( $category['name'] ) ? trim( sanitize_text_field( (string) $category['name'] ) ) : '';
			if ( '' === $name ) {
				$name = __( 'Imported Menu', 'restropress' );
			}
			$key = strtolower( $name );
			if ( ! isset( $by_name[ $key ] ) ) {
				$by_name[ $key ] = count( $model['categories'] );
				$model['categories'][] = array(
					'name'        => $name,
					'description' => isset( $category['description'] ) ? sanitize_textarea_field( (string) $category['description'] ) : '',
					'items'       => array(),
				);
			}
			$index = $by_name[ $key ];
			$items = isset( $category['items'] ) && is_array( $category['items'] ) ? $category['items'] : array();
			foreach ( $items as $item ) {
				$clean = self::normalize_item( $item );
				if ( $clean ) {
					$model['categories'][ $index ]['items'][] = $clean;
				}
			}
		}

		// Drop empty categories so the preview only shows real work.
		$model['categories'] = array_values(
			array_filter(
				$model['categories'],
				function ( $category ) {
					return ! empty( $category['items'] );
				}
			)
		);

		$model['settings']  = self::normalize_settings( isset( $raw['settings'] ) ? $raw['settings'] : array(), $model['warnings'] );
		$model['customers'] = self::normalize_customers( isset( $raw['customers'] ) ? $raw['customers'] : array() );

		return $model;
	}

	/**
	 * Sanitize one item.
	 *
	 * @param mixed $item Raw item.
	 * @return array|null
	 */
	protected static function normalize_item( $item ) {
		if ( ! is_array( $item ) ) {
			return null;
		}
		$name = isset( $item['name'] ) ? trim( sanitize_text_field( (string) $item['name'] ) ) : '';
		if ( '' === $name ) {
			return null;
		}

		$warnings = self::clean_strings( isset( $item['warnings'] ) ? $item['warnings'] : array() );
		$price    = isset( $item['price'] ) ? self::clean_price( $item['price'] ) : '';

		$sizes = array();
		if ( ! empty( $item['sizes'] ) && is_array( $item['sizes'] ) ) {
			foreach ( $item['sizes'] as $size ) {
				if ( ! is_array( $size ) ) {
					continue;
				}
				$size_name = isset( $size['name'] ) ? trim( sanitize_text_field( (string) $size['name'] ) ) : '';
				if ( '' === $size_name ) {
					continue;
				}
				$size_price = isset( $size['price'] ) ? self::clean_price( $size['price'] ) : '';
				$size_key   = strtolower( $size_name );
				if ( isset( $sizes[ $size_key ] ) ) {
					/* translators: %s: size name */
					$warnings[] = sprintf( __( 'Size "%s" is listed twice; the first price is kept.', 'restropress' ), $size_name );
					continue;
				}
				if ( '' === $size_price ) {
					/* translators: %s: size name */
					$warnings[] = sprintf( __( 'Size "%s" has no price; it is imported at 0.00.', 'restropress' ), $size_name );
					$size_price = '0.00';
				}
				$sizes[ $size_key ] = array(
					'name'  => $size_name,
					'price' => $size_price,
				);
			}
		}
		$sizes      = array_values( $sizes );
		$size_names = wp_list_pluck( $sizes, 'name' );

		if ( '' === $price && empty( $sizes ) ) {
			$warnings[] = __( 'Missing price; the item is imported at 0.00.', 'restropress' );
			$price      = '0.00';
		}
		if ( ! empty( $sizes ) ) {
			$amounts = array_map( 'floatval', wp_list_pluck( $sizes, 'price' ) );
			$price   = number_format( min( $amounts ), 2, '.', '' );
		}

		$groups = array();
		if ( ! empty( $item['addon_groups'] ) && is_array( $item['addon_groups'] ) ) {
			foreach ( $item['addon_groups'] as $group ) {
				$clean = self::normalize_group( $group, $size_names, $warnings );
				if ( ! $clean ) {
					continue;
				}
				$key = strtolower( $clean['name'] );
				if ( isset( $groups[ $key ] ) ) {
					// Same group listed twice (for example per size): merge options.
					$groups[ $key ] = self::merge_groups( $groups[ $key ], $clean );
				} else {
					$groups[ $key ] = $clean;
				}
			}
		}

		$image = isset( $item['image'] ) ? self::clean_url( $item['image'] ) : '';
		if ( ! empty( $item['image'] ) && '' === $image ) {
			$warnings[] = __( 'Image URL is not a valid http(s) address; skipped.', 'restropress' );
		}

		$dietary   = self::map_dietary( isset( $item['dietary'] ) ? (array) $item['dietary'] : array() );
		$food_type = isset( $item['food_type'] ) && in_array( $item['food_type'], array( 'veg', 'non_veg' ), true ) ? $item['food_type'] : $dietary['food_type'];

		return array(
			'name'            => $name,
			'description'     => isset( $item['description'] ) ? sanitize_textarea_field( wp_strip_all_tags( (string) $item['description'] ) ) : '',
			'price'           => $price,
			'sizes'           => $sizes,
			'addon_groups'    => array_values( $groups ),
			'image'           => $image,
			'image_id'        => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
			'dietary'         => $dietary['dietary'],
			'allergens'       => self::clean_strings( isset( $item['allergens'] ) ? $item['allergens'] : array() ),
			'food_type'       => $food_type,
			'status'          => isset( $item['status'] ) && 'draft' === $item['status'] ? 'draft' : 'publish',
			'also_categories' => self::clean_strings( isset( $item['also_categories'] ) ? $item['also_categories'] : array() ),
			'source_ref'      => isset( $item['source_ref'] ) ? sanitize_text_field( (string) $item['source_ref'] ) : '',
			'warnings'        => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * Sanitize one add-on group.
	 *
	 * @param mixed $group      Raw group.
	 * @param array $size_names Item size names.
	 * @param array $warnings   Item warnings (by reference).
	 * @return array|null
	 */
	protected static function normalize_group( $group, $size_names, &$warnings ) {
		if ( ! is_array( $group ) ) {
			return null;
		}
		$name = isset( $group['name'] ) ? trim( sanitize_text_field( (string) $group['name'] ) ) : '';
		if ( '' === $name ) {
			return null;
		}
		$min      = isset( $group['min'] ) ? absint( $group['min'] ) : 0;
		$max      = isset( $group['max'] ) ? absint( $group['max'] ) : 0;
		$required = ! empty( $group['required'] ) && ! in_array( strtolower( (string) $group['required'] ), array( 'no', 'false', '0', 'n' ), true );
		if ( $max && $min > $max ) {
			/* translators: %s: add-on group name */
			$warnings[] = sprintf( __( 'Add-on group "%s" has a minimum above its maximum; the minimum was lowered.', 'restropress' ), $name );
			$min = $max;
		}
		if ( $required && $min < 1 ) {
			$min = 1;
		}
		$type = isset( $group['type'] ) && in_array( $group['type'], array( 'single', 'multiple' ), true ) ? $group['type'] : ( 1 === $max ? 'single' : 'multiple' );

		$size_lookup = array();
		foreach ( $size_names as $size_name ) {
			$size_lookup[ strtolower( $size_name ) ] = $size_name;
		}

		$options = array();
		if ( ! empty( $group['options'] ) && is_array( $group['options'] ) ) {
			foreach ( $group['options'] as $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}
				$option_name = isset( $option['name'] ) ? trim( sanitize_text_field( (string) $option['name'] ) ) : '';
				if ( '' === $option_name ) {
					continue;
				}
				$option_price = isset( $option['price'] ) ? self::clean_price( $option['price'] ) : '';
				$prices       = array();
				if ( ! empty( $option['prices'] ) && is_array( $option['prices'] ) ) {
					foreach ( $option['prices'] as $size_name => $size_price ) {
						$size_key = strtolower( trim( sanitize_text_field( (string) $size_name ) ) );
						if ( ! isset( $size_lookup[ $size_key ] ) ) {
							/* translators: 1: add-on name, 2: size name */
							$warnings[] = sprintf( __( 'Add-on "%1$s" has a price for an unknown size "%2$s"; ignored.', 'restropress' ), $option_name, sanitize_text_field( (string) $size_name ) );
							continue;
						}
						$size_price = self::clean_price( $size_price );
						if ( '' !== $size_price ) {
							$prices[ $size_lookup[ $size_key ] ] = $size_price;
						}
					}
				}
				if ( '' === $option_price ) {
					$option_price = $prices ? number_format( min( array_map( 'floatval', $prices ) ), 2, '.', '' ) : '0.00';
				}
				$option_key = strtolower( $option_name );
				if ( isset( $options[ $option_key ] ) ) {
					$options[ $option_key ]['prices'] = array_merge( $options[ $option_key ]['prices'], $prices );
					continue;
				}
				$options[ $option_key ] = array(
					'name'    => $option_name,
					'price'   => $option_price,
					'prices'  => $prices,
					'default' => ! empty( $option['default'] ),
				);
			}
		}

		if ( empty( $options ) ) {
			/* translators: %s: add-on group name */
			$warnings[] = sprintf( __( 'Add-on group "%s" has no options; skipped.', 'restropress' ), $name );
			return null;
		}

		return array(
			'name'     => $name,
			'type'     => $type,
			'required' => $required,
			'min'      => $min,
			'max'      => $max,
			'options'  => array_values( $options ),
		);
	}

	/**
	 * Merge two groups with the same name.
	 *
	 * @param array $a First group.
	 * @param array $b Second group.
	 * @return array
	 */
	protected static function merge_groups( $a, $b ) {
		$by_name = array();
		foreach ( $a['options'] as $i => $option ) {
			$by_name[ strtolower( $option['name'] ) ] = $i;
		}
		foreach ( $b['options'] as $option ) {
			$key = strtolower( $option['name'] );
			if ( isset( $by_name[ $key ] ) ) {
				$a['options'][ $by_name[ $key ] ]['prices'] = array_merge( $a['options'][ $by_name[ $key ] ]['prices'], $option['prices'] );
			} else {
				$a['options'][] = $option;
			}
		}
		$a['required'] = $a['required'] || $b['required'];
		$a['min']      = max( $a['min'], $b['min'] );
		$a['max']      = $a['max'] && $b['max'] ? max( $a['max'], $b['max'] ) : 0;
		return $a;
	}

	/**
	 * Sanitize store settings.
	 *
	 * @param mixed $raw      Raw settings.
	 * @param array $warnings Model warnings (by reference).
	 * @return array
	 */
	protected static function normalize_settings( $raw, &$warnings ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}

		if ( ! empty( $raw['store_hours'] ) && is_array( $raw['store_hours'] ) ) {
			$hours = array();
			foreach ( $raw['store_hours'] as $day => $config ) {
				$day = absint( $day );
				if ( $day < 1 || $day > 7 || ! is_array( $config ) ) {
					continue;
				}
				$closed = ! empty( $config['closed'] );
				$open   = self::clean_time( isset( $config['open'] ) ? $config['open'] : '' );
				$close  = self::clean_time( isset( $config['close'] ) ? $config['close'] : '' );
				if ( ! $closed && ( '' === $open || '' === $close ) ) {
					/* translators: %d: ISO day number */
					$warnings[] = sprintf( __( 'Store hours for day %d could not be read; that day was skipped.', 'restropress' ), $day );
					continue;
				}
				$hours[ $day ] = array(
					'open'   => $open,
					'close'  => $close,
					'closed' => $closed ? '1' : '',
				);
			}
			if ( $hours ) {
				ksort( $hours );
				$out['store_hours'] = $hours;
			}
		}

		if ( ! empty( $raw['holidays'] ) && is_array( $raw['holidays'] ) ) {
			$dates = array();
			foreach ( $raw['holidays'] as $date ) {
				$date = sanitize_text_field( (string) $date );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
					$dates[] = $date;
				}
			}
			$dates = array_values( array_unique( $dates ) );
			sort( $dates );
			if ( $dates ) {
				$out['holidays'] = $dates;
			}
		}

		foreach ( array( 'delivery_fee', 'minimum_order', 'minimum_order_pickup' ) as $key ) {
			if ( isset( $raw[ $key ] ) && '' !== (string) $raw[ $key ] ) {
				$out[ $key ] = self::clean_price( $raw[ $key ] );
			}
		}
		if ( isset( $raw['tax_rate'] ) && '' !== (string) $raw['tax_rate'] ) {
			$rate = (float) preg_replace( '/[^0-9.]/', '', (string) $raw['tax_rate'] );
			if ( $rate >= 0 && $rate < 100 ) {
				$out['tax_rate'] = (string) round( $rate, 4 );
			}
		}

		if ( ! empty( $raw['delivery_zones'] ) && is_array( $raw['delivery_zones'] ) ) {
			$zones = array();
			foreach ( $raw['delivery_zones'] as $zone ) {
				if ( ! is_array( $zone ) ) {
					continue;
				}
				$zones[] = array(
					'name'      => isset( $zone['name'] ) ? sanitize_text_field( (string) $zone['name'] ) : '',
					'fee'       => isset( $zone['fee'] ) ? self::clean_price( $zone['fee'] ) : '',
					'min_order' => isset( $zone['min_order'] ) ? self::clean_price( $zone['min_order'] ) : '',
				);
			}
			if ( $zones ) {
				$out['delivery_zones'] = $zones;
			}
		}

		return $out;
	}

	/**
	 * Convert a time to RestroPress's "9:00am" format.
	 *
	 * @param string $time Raw time.
	 * @return string
	 */
	public static function clean_time( $time ) {
		$time = trim( strtolower( sanitize_text_field( (string) $time ) ) );
		if ( '' === $time ) {
			return '';
		}
		if ( ! preg_match( '/^(\d{1,2})(?::(\d{2}))?(?::\d{2})?\s*(am|pm)?$/', $time, $m ) ) {
			return '';
		}
		$hour   = (int) $m[1];
		$minute = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
		if ( ! empty( $m[3] ) ) {
			if ( $hour < 1 || $hour > 12 ) {
				return '';
			}
			$hour = ( 12 === $hour ? 0 : $hour ) + ( 'pm' === $m[3] ? 12 : 0 );
		}
		if ( 24 === $hour && 0 === $minute ) {
			$hour = 23;
			$minute = 59;
		}
		if ( $hour > 23 || $minute > 59 ) {
			return '';
		}
		$suffix  = $hour >= 12 ? 'pm' : 'am';
		$display = $hour % 12;
		$display = 0 === $display ? 12 : $display;
		return sprintf( '%d:%02d%s', $display, $minute, $suffix );
	}

	/**
	 * Sanitize customers: valid, unique emails only.
	 *
	 * @param mixed $raw Raw customers.
	 * @return array
	 */
	protected static function normalize_customers( $raw ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $customer ) {
			if ( ! is_array( $customer ) || empty( $customer['email'] ) ) {
				continue;
			}
			$email = sanitize_email( (string) $customer['email'] );
			if ( ! is_email( $email ) ) {
				continue;
			}
			$key = strtolower( $email );
			if ( isset( $out[ $key ] ) ) {
				continue;
			}
			$out[ $key ] = array(
				'email'      => $email,
				'first_name' => isset( $customer['first_name'] ) ? sanitize_text_field( (string) $customer['first_name'] ) : '',
				'last_name'  => isset( $customer['last_name'] ) ? sanitize_text_field( (string) $customer['last_name'] ) : '',
				'phone'      => isset( $customer['phone'] ) ? preg_replace( '/[^0-9+()\-\s.]/', '', (string) $customer['phone'] ) : '',
			);
		}
		return array_values( $out );
	}

	/**
	 * Sanitize a list of strings, dropping empties and duplicates.
	 *
	 * @param mixed $list Raw list.
	 * @return array
	 */
	public static function clean_strings( $list ) {
		$out = array();
		foreach ( (array) $list as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$value = trim( sanitize_text_field( (string) $value ) );
			if ( '' !== $value ) {
				$out[] = $value;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Count what a model holds.
	 *
	 * @param array $model Clean model.
	 * @return array
	 */
	public static function counts( $model ) {
		$counts = array(
			'categories' => count( $model['categories'] ),
			'items'      => 0,
			'sizes'      => 0,
			'groups'     => 0,
			'options'    => 0,
			'images'     => 0,
			'warnings'   => count( $model['warnings'] ),
			'customers'  => count( $model['customers'] ),
			'settings'   => count( $model['settings'] ),
		);
		$group_names = array();
		foreach ( $model['categories'] as $category ) {
			foreach ( $category['items'] as $item ) {
				$counts['items']++;
				$counts['sizes']    += count( $item['sizes'] );
				$counts['warnings'] += count( $item['warnings'] );
				if ( $item['image'] || $item['image_id'] ) {
					$counts['images']++;
				}
				foreach ( $item['addon_groups'] as $group ) {
					$group_names[ strtolower( $group['name'] ) ] = true;
					$counts['options'] += count( $group['options'] );
				}
			}
		}
		$counts['groups'] = count( $group_names );
		return $counts;
	}
}
