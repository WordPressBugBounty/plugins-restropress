<?php
/**
 * Import source: GloriaFood.
 *
 * Reads the menu JSON returned by GloriaFood's "Fetch Menu" integration
 * (GET https://pos.globalfoodsoft.com/pos/menu, Glf-Api-Version: 2), as
 * documented in the public repository GlobalFood/integration_docs,
 * fetch_menu/README.md and the sample fetch_menu/payload/menu.json. The
 * merchant saves that response as a .json file and uploads it; RestroPress
 * never calls GloriaFood itself.
 *
 * Schema implemented (unknown or deprecated keys are ignored):
 *
 *   menu: { id, restaurant_id, active, currency, categories[] }
 *     (a wrapper object { "menu": { ... } } is also accepted)
 *   category: { id, name, active, groups[], items[] }
 *     category.groups are option groups that apply to every item in it.
 *   item: { id, name, description, price, active, tags[]|null,
 *           sizes[], groups[], extras{} }
 *     tags: HOT, VEGETARIAN, VEGAN, GLUTEN_FREE, HALAL, NUT_FREE,
 *           DAIRY_FREE, RAW
 *     extras.menu_item_allergens_values[]: { id, name }
 *     extras.menu_item_order_types: [ "pickup", "delivery", ... ] or null
 *   size: { id, name, price, default, groups[] }
 *     size.price is ADDED to item.price (the sample prices a 7.00 pizza with
 *     sizes Small 0 and Large 2), so a size costs item.price + size.price.
 *     size.groups are option groups for that size only. GloriaFood
 *     merchants usually name them "Extra Toppings (Small)" and
 *     "Extra Toppings (Large)"; the "(size)" suffix is removed and the
 *     groups are merged into one RestroPress group with per-size prices.
 *   group: { id, name, required, force_min, force_max, allow_quantity, options[] }
 *     force_max 1 becomes a single-choice group; force_min only applies
 *     when the group is required.
 *   option: { id, name, price, default }
 *
 * The fetch-menu payload has no image files (only internal picture ids), no
 * store hours and no delivery zones, so those are not imported from it.
 *
 * A client list exported from GloriaFood as CSV is also accepted (best
 * effort: any CSV with an email column; name, first/last name and phone
 * columns are read when present). Customers are only created when the
 * merchant ticks the consent box on the preview screen.
 *
 * @package RestroPress/Importer
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * GloriaFood adapter.
 */
class RPress_Import_Source_GloriaFood {

	/**
	 * Source args.
	 *
	 * @return array
	 */
	public static function args() {
		return array(
			'label'       => __( 'GloriaFood', 'restropress' ),
			'description' => __( 'Your GloriaFood menu (Fetch Menu .json) and, optionally, your client list (.csv).', 'restropress' ),
			'accepts'     => array( 'json', 'csv' ),
			'detect'      => array( __CLASS__, 'detect' ),
			'parse'       => array( __CLASS__, 'parse' ),
			'field_map'   => array( __CLASS__, 'field_map' ),
			'help'        => self::help(),
			'priority'    => 10,
		);
	}

	/**
	 * Upload notes.
	 *
	 * @return string
	 */
	public static function help() {
		return '<p>' . esc_html__( 'Menu: in your GloriaFood admin open Others > 3rd party integrations, enable the "Fetch Menu" integration and copy its key. Request the menu with that key (for example: curl -H "Authorization: YOUR_KEY" -H "Accept: application/json" -H "Glf-Api-Version: 2" https://pos.globalfoodsoft.com/pos/menu > menu.json) and upload the saved menu.json here.', 'restropress' ) . '</p>'
			. '<p>' . esc_html__( 'Clients: export your client list from GloriaFood as CSV and upload it as a separate import. Only import people who agreed to hear from you; RestroPress never emails them.', 'restropress' ) . '</p>'
			. '<p>' . esc_html__( 'GloriaFood exports do not include photos, store hours or delivery zones. Add those in RestroPress after the import.', 'restropress' ) . '</p>';
	}

	/**
	 * Field map for the preview.
	 *
	 * @return array
	 */
	public static function field_map() {
		return array(
			'categories[].name'                    => __( 'Menu category', 'restropress' ),
			'items[].name / description'           => __( 'Menu item name and description', 'restropress' ),
			'items[].price'                        => __( 'Price (base price when the item has sizes)', 'restropress' ),
			'items[].sizes[]'                      => __( 'Price options: item price + size price', 'restropress' ),
			'groups[] (category, item and size)'   => __( 'Add-on groups with required, min (force_min) and max (force_max)', 'restropress' ),
			'groups[].options[]'                   => __( 'Add-on items with prices (per size for size groups)', 'restropress' ),
			'items[].tags'                         => __( 'Dietary labels (HOT becomes Spicy)', 'restropress' ),
			'extras.menu_item_allergens_values'    => __( 'Tags such as "Contains milk"', 'restropress' ),
			'active: false'                        => __( 'Imported as a draft', 'restropress' ),
			'clients CSV: email, name, phone'      => __( 'Customers (only with consent)', 'restropress' ),
		);
	}

	/**
	 * Decode the menu JSON.
	 *
	 * @param string $contents Raw JSON.
	 * @return array|null
	 */
	protected static function decode( $contents ) {
		$data = json_decode( (string) $contents, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( isset( $data['menu'] ) && is_array( $data['menu'] ) ) {
			$data = $data['menu'];
		}
		return isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data : null;
	}

	/**
	 * Whether the file is a GloriaFood menu or client list.
	 *
	 * @param array $file File.
	 * @return bool
	 */
	public static function detect( $file ) {
		if ( 'csv' === $file['ext'] ) {
			$data = RPress_Import_Source_CSV::read( $file['contents'], 1 );
			return (bool) self::email_column( $data['headers'] ) && ! in_array( 'item', $data['headers'], true );
		}
		$menu = self::decode( $file['contents'] );
		if ( ! $menu ) {
			return false;
		}
		if ( isset( $menu['restaurant_id'] ) ) {
			return true;
		}
		foreach ( $menu['categories'] as $category ) {
			if ( ! empty( $category['items'][0] ) && is_array( $category['items'][0] ) ) {
				$item = $category['items'][0];
				return array_key_exists( 'sizes', $item ) || array_key_exists( 'groups', $item ) || array_key_exists( 'menu_category_id', $item );
			}
		}
		return false;
	}

	/**
	 * Parse into the model.
	 *
	 * @param array $file File.
	 * @return array|WP_Error
	 */
	public static function parse( $file ) {
		if ( 'csv' === $file['ext'] ) {
			return self::parse_clients( $file );
		}
		$menu = self::decode( $file['contents'] );
		if ( ! $menu ) {
			return new WP_Error( 'rpress_import_gloriafood_json', __( 'This is not a GloriaFood menu file: no "categories" list was found.', 'restropress' ) );
		}

		$model = array(
			'currency'   => isset( $menu['currency'] ) ? (string) $menu['currency'] : '',
			'categories' => array(),
			'warnings'   => array(),
		);
		if ( isset( $menu['active'] ) && false === $menu['active'] ) {
			$model['warnings'][] = __( 'This GloriaFood menu is marked inactive.', 'restropress' );
		}

		foreach ( $menu['categories'] as $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}
			$category_active = ! isset( $category['active'] ) || false !== $category['active'];
			$category_groups = isset( $category['groups'] ) && is_array( $category['groups'] ) ? $category['groups'] : array();
			$out             = array(
				'name'  => isset( $category['name'] ) ? (string) $category['name'] : '',
				'items' => array(),
			);
			$items = isset( $category['items'] ) && is_array( $category['items'] ) ? $category['items'] : array();
			foreach ( $items as $item ) {
				if ( is_array( $item ) ) {
					$out['items'][] = self::parse_item( $item, $category_groups, $category_active );
				}
			}
			$model['categories'][] = $out;
		}

		return $model;
	}

	/**
	 * One GloriaFood item.
	 *
	 * @param array $item            Item.
	 * @param array $category_groups Category-level groups.
	 * @param bool  $category_active Whether the category is active.
	 * @return array
	 */
	protected static function parse_item( $item, $category_groups, $category_active ) {
		$warnings = array();
		$base     = isset( $item['price'] ) ? (float) $item['price'] : 0.0;
		$extras   = isset( $item['extras'] ) && is_array( $item['extras'] ) ? $item['extras'] : array();

		$groups = array();
		foreach ( array_merge( $category_groups, isset( $item['groups'] ) && is_array( $item['groups'] ) ? $item['groups'] : array() ) as $group ) {
			$clean = self::parse_group( $group, '', $warnings );
			if ( $clean ) {
				$groups[] = $clean;
			}
		}

		$sizes = array();
		if ( ! empty( $item['sizes'] ) && is_array( $item['sizes'] ) ) {
			$size_groups = array(); // base name => group with per-size prices.
			$seen_in     = array(); // base name => size names.
			$size_names  = array();
			foreach ( $item['sizes'] as $size ) {
				if ( ! is_array( $size ) || empty( $size['name'] ) ) {
					continue;
				}
				$size_name    = (string) $size['name'];
				$size_names[] = $size_name;
				$sizes[]      = array(
					'name'  => $size_name,
					'price' => $base + ( isset( $size['price'] ) ? (float) $size['price'] : 0.0 ),
				);
				foreach ( isset( $size['groups'] ) && is_array( $size['groups'] ) ? $size['groups'] : array() as $group ) {
					$clean = self::parse_group( $group, $size_name, $warnings );
					if ( ! $clean ) {
						continue;
					}
					$key                = strtolower( $clean['name'] );
					$seen_in[ $key ][] = $size_name;
					if ( ! isset( $size_groups[ $key ] ) ) {
						$size_groups[ $key ] = $clean;
						continue;
					}
					$by_name = array();
					foreach ( $size_groups[ $key ]['options'] as $i => $option ) {
						$by_name[ strtolower( $option['name'] ) ] = $i;
					}
					foreach ( $clean['options'] as $option ) {
						$option_key = strtolower( $option['name'] );
						if ( isset( $by_name[ $option_key ] ) ) {
							$size_groups[ $key ]['options'][ $by_name[ $option_key ] ]['prices'] += $option['prices'];
						} else {
							$size_groups[ $key ]['options'][] = $option;
						}
					}
				}
			}
			foreach ( $size_groups as $key => $group ) {
				$missing = array_diff( $size_names, $seen_in[ $key ] );
				if ( $missing ) {
					/* translators: 1: group name, 2: size names */
					$warnings[] = sprintf( __( 'Add-on group "%1$s" is not offered for %2$s in GloriaFood; in RestroPress it shows for every size.', 'restropress' ), $group['name'], implode( ', ', $missing ) );
				}
				$groups[] = $group;
			}
		}

		$active = $category_active && ( ! isset( $item['active'] ) || false !== $item['active'] );
		if ( ! $active ) {
			$warnings[] = __( 'Inactive in GloriaFood; imported as a draft.', 'restropress' );
		}

		if ( ! empty( $extras['menu_item_order_types'] ) && is_array( $extras['menu_item_order_types'] ) && 1 === count( $extras['menu_item_order_types'] ) ) {
			/* translators: %s: order type */
			$warnings[] = sprintf( __( 'Only offered for %s in GloriaFood; RestroPress offers it for every order type.', 'restropress' ), sanitize_text_field( (string) $extras['menu_item_order_types'][0] ) );
		}

		$allergens = array();
		if ( ! empty( $extras['menu_item_allergens_values'] ) && is_array( $extras['menu_item_allergens_values'] ) ) {
			foreach ( $extras['menu_item_allergens_values'] as $allergen ) {
				if ( is_array( $allergen ) && ! empty( $allergen['name'] ) ) {
					$allergens[] = (string) $allergen['name'];
				}
			}
		}

		// Not in the documented payload; read if a merchant's export carries one.
		$image = '';
		foreach ( array( 'image_url', 'picture_url', 'image', 'picture' ) as $key ) {
			if ( ! empty( $item[ $key ] ) && is_string( $item[ $key ] ) ) {
				$image = $item[ $key ];
				break;
			}
		}

		return array(
			'name'         => isset( $item['name'] ) ? (string) $item['name'] : '',
			'description'  => isset( $item['description'] ) ? (string) $item['description'] : '',
			'price'        => $base,
			'sizes'        => $sizes,
			'addon_groups' => $groups,
			'dietary'      => isset( $item['tags'] ) && is_array( $item['tags'] ) ? $item['tags'] : array(),
			'allergens'    => $allergens,
			'image'        => $image,
			'status'       => $active ? 'publish' : 'draft',
			'source_ref'   => isset( $item['id'] ) ? (string) $item['id'] : '',
			'warnings'     => $warnings,
		);
	}

	/**
	 * One option group. For a size group, option prices are stored for that
	 * size and a trailing "(Size)" is removed from the group name.
	 *
	 * @param mixed  $group     Group.
	 * @param string $size_name Size the group belongs to ('' for all sizes).
	 * @param array  $warnings  Item warnings (by reference).
	 * @return array|null
	 */
	protected static function parse_group( $group, $size_name, &$warnings ) {
		if ( ! is_array( $group ) || empty( $group['name'] ) ) {
			return null;
		}
		$name = trim( (string) $group['name'] );
		if ( '' !== $size_name ) {
			$suffix = '(' . strtolower( $size_name ) . ')';
			if ( strtolower( substr( $name, -strlen( $suffix ) ) ) === $suffix ) {
				$name = trim( substr( $name, 0, -strlen( $suffix ) ) );
			}
		}
		$required = ! empty( $group['required'] );
		$max      = isset( $group['force_max'] ) ? absint( $group['force_max'] ) : 0;
		$min      = $required && isset( $group['force_min'] ) ? absint( $group['force_min'] ) : 0;
		if ( ! empty( $group['allow_quantity'] ) ) {
			/* translators: %s: group name */
			$warnings[] = sprintf( __( 'Add-on group "%s" lets customers pick an option more than once in GloriaFood; check the quantity setting in RestroPress.', 'restropress' ), $name );
		}

		$options = array();
		foreach ( isset( $group['options'] ) && is_array( $group['options'] ) ? $group['options'] : array() as $option ) {
			if ( ! is_array( $option ) || empty( $option['name'] ) ) {
				continue;
			}
			$price     = isset( $option['price'] ) ? (float) $option['price'] : 0.0;
			$options[] = array(
				'name'    => (string) $option['name'],
				'price'   => '' === $size_name ? $price : '',
				'prices'  => '' === $size_name ? array() : array( $size_name => $price ),
				'default' => ! empty( $option['default'] ),
			);
		}

		return array(
			'name'     => $name,
			'type'     => 1 === $max ? 'single' : 'multiple',
			'required' => $required,
			'min'      => $min,
			'max'      => $max,
			'options'  => $options,
		);
	}

	/**
	 * Header that holds the email address.
	 *
	 * @param array $headers Normalized headers.
	 * @return string
	 */
	protected static function email_column( $headers ) {
		foreach ( array( 'email', 'e_mail', 'email_address', 'client_email', 'customer_email' ) as $candidate ) {
			if ( in_array( $candidate, $headers, true ) ) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * Client list CSV.
	 *
	 * @param array $file File.
	 * @return array|WP_Error
	 */
	protected static function parse_clients( $file ) {
		$data   = RPress_Import_Source_CSV::read( $file['contents'] );
		$column = self::email_column( $data['headers'] );
		if ( ! $column ) {
			return new WP_Error( 'rpress_import_gloriafood_csv', __( 'This CSV has no email column, so it is not a GloriaFood client list.', 'restropress' ) );
		}
		$pick = function ( $row, $keys ) {
			foreach ( $keys as $key ) {
				if ( isset( $row[ $key ] ) && '' !== $row[ $key ] ) {
					return $row[ $key ];
				}
			}
			return '';
		};
		$customers = array();
		foreach ( $data['rows'] as $row ) {
			$first = $pick( $row, array( 'first_name', 'firstname', 'given_name' ) );
			$last  = $pick( $row, array( 'last_name', 'lastname', 'surname', 'family_name' ) );
			if ( '' === $first && '' === $last ) {
				$parts = explode( ' ', trim( $pick( $row, array( 'name', 'client_name', 'full_name', 'customer_name' ) ) ), 2 );
				$first = $parts[0];
				$last  = isset( $parts[1] ) ? $parts[1] : '';
			}
			$customers[] = array(
				'email'      => $row[ $column ],
				'first_name' => $first,
				'last_name'  => $last,
				'phone'      => $pick( $row, array( 'phone', 'phone_number', 'telephone', 'mobile', 'client_phone' ) ),
			);
		}
		return array(
			'categories' => array(),
			'customers'  => $customers,
			'warnings'   => array( __( 'Only import customers who agreed to receive messages from you. RestroPress does not email imported customers.', 'restropress' ) ),
		);
	}
}
