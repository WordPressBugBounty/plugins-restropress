<?php
/**
 * Import source: RestroPress CSV template (any POS or spreadsheet).
 *
 * Columns (header row required, order free, names case-insensitive):
 *   category, item, description, size, price, addon_group, addon,
 *   addon_price, required, min, max, image_url, dietary
 *
 * One row per item / size / add-on combination. Rows with the same category
 * and item build one menu item:
 *   - size + price adds a size (variable price). With no size, price is the
 *     item's base price.
 *   - addon_group + addon + addon_price adds an add-on option. When the row
 *     also has a size, addon_price is that size's price for the option;
 *     without a size it applies to every size.
 *   - required (yes/no), min and max describe the add-on group (first value wins).
 *   - description, image_url and dietary (comma separated) are read from the
 *     first row that has them.
 *
 * @package RestroPress/Importer
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * CSV template adapter.
 */
class RPress_Import_Source_CSV {

	/**
	 * Template columns.
	 *
	 * @var string[]
	 */
	public static $columns = array( 'category', 'item', 'description', 'size', 'price', 'addon_group', 'addon', 'addon_price', 'required', 'min', 'max', 'image_url', 'dietary' );

	/**
	 * Header aliases => template column.
	 *
	 * @var array
	 */
	protected static $aliases = array(
		'category_name' => 'category',
		'section'       => 'category',
		'name'          => 'item',
		'item_name'     => 'item',
		'product'       => 'item',
		'title'         => 'item',
		'variant'       => 'size',
		'variation'     => 'size',
		'option'        => 'size',
		'group'         => 'addon_group',
		'modifier'      => 'addon',
		'addon_name'    => 'addon',
		'modifier_price' => 'addon_price',
		'image'         => 'image_url',
		'photo'         => 'image_url',
		'tags'          => 'dietary',
		'labels'        => 'dietary',
	);

	/**
	 * Source args.
	 *
	 * @return array
	 */
	public static function args() {
		return array(
			'label'       => __( 'Spreadsheet (CSV template)', 'restropress' ),
			'description' => __( 'Any POS or spreadsheet: fill in our CSV template and upload it.', 'restropress' ),
			'accepts'     => array( 'csv', 'txt' ),
			'detect'      => array( __CLASS__, 'detect' ),
			'parse'       => array( __CLASS__, 'parse' ),
			'field_map'   => array( __CLASS__, 'field_map' ),
			'help'        => self::help(),
			'priority'    => 30,
		);
	}

	/**
	 * Format notes shown on the upload step.
	 *
	 * @return string
	 */
	public static function help() {
		$url = RP_PLUGIN_URL . 'assets/features/importer/restropress-menu-template.csv';
		return '<p>' . sprintf(
			/* translators: %s: template download link */
			__( 'Download the %s, fill it in with Excel, Google Sheets or Numbers, and save it as CSV.', 'restropress' ),
			'<a href="' . esc_url( $url ) . '" download>' . esc_html__( 'CSV template', 'restropress' ) . '</a>'
		) . '</p><ul>'
			. '<li>' . esc_html__( 'One row per item, size and add-on combination. Rows with the same category and item make one menu item.', 'restropress' ) . '</li>'
			. '<li>' . esc_html__( 'size + price: adds a size with its price. Leave size empty for a single price.', 'restropress' ) . '</li>'
			. '<li>' . esc_html__( 'addon_group + addon + addon_price: adds an add-on. On a row with a size, addon_price is the price for that size.', 'restropress' ) . '</li>'
			. '<li>' . esc_html__( 'required (yes/no), min and max: rules for the add-on group. max 1 makes it a single choice.', 'restropress' ) . '</li>'
			. '<li>' . esc_html__( 'image_url: a public .jpg, .png, .gif or .webp link. dietary: comma separated, for example "Vegetarian, Gluten-free".', 'restropress' ) . '</li>'
			. '</ul>';
	}

	/**
	 * Field map for the preview.
	 *
	 * @return array
	 */
	public static function field_map() {
		return array(
			'category'    => __( 'Menu category', 'restropress' ),
			'item'        => __( 'Menu item name', 'restropress' ),
			'description' => __( 'Item description', 'restropress' ),
			'size'        => __( 'Price option (variable price) name', 'restropress' ),
			'price'       => __( 'Price, or the price of the size', 'restropress' ),
			'addon_group' => __( 'Add-on group (parent add-on category)', 'restropress' ),
			'addon'       => __( 'Add-on item (child add-on category)', 'restropress' ),
			'addon_price' => __( 'Add-on price for the item (per size when a size is set)', 'restropress' ),
			'required'    => __( 'Add-on group required', 'restropress' ),
			'min'         => __( 'Minimum add-ons', 'restropress' ),
			'max'         => __( 'Maximum add-ons', 'restropress' ),
			'image_url'   => __( 'Featured image (downloaded)', 'restropress' ),
			'dietary'     => __( 'Dietary labels', 'restropress' ),
		);
	}

	/**
	 * Read CSV text into header-keyed rows.
	 *
	 * @param string $contents Raw file contents.
	 * @param int    $max_rows Row limit.
	 * @return array { headers: string[], rows: array[] }
	 */
	public static function read( $contents, $max_rows = 20000 ) {
		$contents = (string) $contents;
		if ( 0 === strpos( $contents, "\xEF\xBB\xBF" ) ) {
			$contents = substr( $contents, 3 );
		}
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $contents, 'UTF-8' ) && function_exists( 'mb_convert_encoding' ) ) {
			$contents = mb_convert_encoding( $contents, 'UTF-8', 'Windows-1252' );
		}

		$first_line = strtok( $contents, "\n" );
		$delimiter  = ',';
		foreach ( array( ';', "\t" ) as $candidate ) {
			if ( substr_count( (string) $first_line, $candidate ) > substr_count( (string) $first_line, $delimiter ) ) {
				$delimiter = $candidate;
			}
		}

		$handle = fopen( 'php://temp', 'r+' );
		fwrite( $handle, $contents );
		rewind( $handle );

		$headers = array();
		$rows    = array();
		while ( false !== ( $line = fgetcsv( $handle, 0, $delimiter, '"', '\\' ) ) ) {
			if ( array( null ) === $line ) {
				continue;
			}
			if ( ! $headers ) {
				foreach ( $line as $cell ) {
					$key       = trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( trim( (string) $cell ) ) ), '_' );
					$headers[] = $key;
				}
				continue;
			}
			$row = array();
			foreach ( $headers as $i => $key ) {
				if ( '' !== $key ) {
					$row[ $key ] = isset( $line[ $i ] ) ? trim( (string) $line[ $i ] ) : '';
				}
			}
			if ( '' === implode( '', $row ) ) {
				continue;
			}
			$rows[] = $row;
			if ( count( $rows ) >= $max_rows ) {
				break;
			}
		}
		fclose( $handle );

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Map aliased headers onto template columns.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	protected static function canonical( $row ) {
		$out = array_fill_keys( self::$columns, '' );
		foreach ( $row as $key => $value ) {
			if ( isset( self::$aliases[ $key ] ) ) {
				$key = self::$aliases[ $key ];
			}
			if ( array_key_exists( $key, $out ) && '' === $out[ $key ] ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Whether a file is in the template format.
	 *
	 * @param array $file File.
	 * @return bool
	 */
	public static function detect( $file ) {
		$data    = self::read( $file['contents'], 1 );
		$headers = array();
		foreach ( $data['headers'] as $header ) {
			$headers[] = isset( self::$aliases[ $header ] ) ? self::$aliases[ $header ] : $header;
		}
		return in_array( 'item', $headers, true ) && in_array( 'price', $headers, true );
	}

	/**
	 * Parse the template into the model.
	 *
	 * @param array $file File.
	 * @return array|WP_Error
	 */
	public static function parse( $file ) {
		$data = self::read( $file['contents'] );
		if ( empty( $data['rows'] ) ) {
			return new WP_Error( 'rpress_import_csv_empty', __( 'The CSV file has no rows.', 'restropress' ) );
		}
		if ( ! self::detect( $file ) ) {
			return new WP_Error( 'rpress_import_csv_columns', __( 'The CSV file needs at least the "item" and "price" columns. Download the template to see the format.', 'restropress' ) );
		}

		$model = array(
			'categories' => array(),
			'warnings'   => array(),
		);
		$known   = array_merge( self::$columns, array_keys( self::$aliases ) );
		$unknown = array_diff( array_filter( $data['headers'] ), $known );
		if ( $unknown ) {
			/* translators: %s: column names */
			$model['warnings'][] = sprintf( __( 'Columns not used: %s.', 'restropress' ), implode( ', ', $unknown ) );
		}

		$items = array(); // key => item being built.
		$order = array(); // key => category name.
		foreach ( $data['rows'] as $line => $raw ) {
			$row = self::canonical( $raw );
			if ( '' === $row['item'] ) {
				/* translators: %d: CSV line number */
				$model['warnings'][] = sprintf( __( 'Line %d has no item name and was skipped.', 'restropress' ), $line + 2 );
				continue;
			}
			$category = '' !== $row['category'] ? $row['category'] : __( 'Imported Menu', 'restropress' );
			$key      = strtolower( $category . '|' . $row['item'] );
			if ( ! isset( $items[ $key ] ) ) {
				$items[ $key ] = array(
					'name'         => $row['item'],
					'description'  => '',
					'price'        => '',
					'sizes'        => array(),
					'addon_groups' => array(),
					'image'        => '',
					'dietary'      => array(),
					'source_ref'   => (string) ( $line + 2 ),
					'warnings'     => array(),
				);
				$order[ $key ] = $category;
			}
			$item = &$items[ $key ];

			if ( '' === $item['description'] && '' !== $row['description'] ) {
				$item['description'] = $row['description'];
			}
			if ( '' === $item['image'] && '' !== $row['image_url'] ) {
				$item['image'] = $row['image_url'];
			}
			if ( empty( $item['dietary'] ) && '' !== $row['dietary'] ) {
				$item['dietary'] = array_map( 'trim', preg_split( '/[,;\/|]+/', $row['dietary'] ) );
			}

			$size = $row['size'];
			if ( '' !== $size ) {
				$size_key = strtolower( $size );
				if ( '' !== $row['price'] ) {
					if ( isset( $item['sizes'][ $size_key ] ) && RPress_Importer_Model::clean_price( $item['sizes'][ $size_key ]['price'] ) !== RPress_Importer_Model::clean_price( $row['price'] ) ) {
						/* translators: 1: size, 2: line */
						$item['warnings'][] = sprintf( __( 'Size "%1$s" has a different price on line %2$d; the first price is kept.', 'restropress' ), $size, $line + 2 );
					} elseif ( ! isset( $item['sizes'][ $size_key ] ) ) {
						$item['sizes'][ $size_key ] = array(
							'name'  => $size,
							'price' => $row['price'],
						);
					}
				} elseif ( ! isset( $item['sizes'][ $size_key ] ) ) {
					$item['sizes'][ $size_key ] = array(
						'name'  => $size,
						'price' => '',
					);
				}
			} elseif ( '' !== $row['price'] && '' === $item['price'] ) {
				$item['price'] = $row['price'];
			}

			if ( '' !== $row['addon_group'] && '' !== $row['addon'] ) {
				$group_key = strtolower( $row['addon_group'] );
				if ( ! isset( $item['addon_groups'][ $group_key ] ) ) {
					$item['addon_groups'][ $group_key ] = array(
						'name'     => $row['addon_group'],
						'required' => '',
						'min'      => '',
						'max'      => '',
						'options'  => array(),
					);
				}
				$group = &$item['addon_groups'][ $group_key ];
				foreach ( array( 'required', 'min', 'max' ) as $rule ) {
					if ( '' === $group[ $rule ] && '' !== $row[ $rule ] ) {
						$group[ $rule ] = $row[ $rule ];
					}
				}
				$option_key = strtolower( $row['addon'] );
				if ( ! isset( $group['options'][ $option_key ] ) ) {
					$group['options'][ $option_key ] = array(
						'name'   => $row['addon'],
						'price'  => '',
						'prices' => array(),
					);
				}
				if ( '' !== $size ) {
					$group['options'][ $option_key ]['prices'][ $size ] = $row['addon_price'];
				} elseif ( '' === $group['options'][ $option_key ]['price'] ) {
					$group['options'][ $option_key ]['price'] = $row['addon_price'];
				}
				unset( $group );
			} elseif ( '' !== $row['addon_group'] || '' !== $row['addon'] ) {
				/* translators: %d: CSV line */
				$item['warnings'][] = sprintf( __( 'Line %d has an add-on group or add-on but not both; the add-on was skipped.', 'restropress' ), $line + 2 );
			}
			unset( $item );
		}

		$categories = array();
		foreach ( $items as $key => $item ) {
			$category = $order[ $key ];
			if ( ! isset( $categories[ $category ] ) ) {
				$categories[ $category ] = array(
					'name'  => $category,
					'items' => array(),
				);
			}
			foreach ( $item['addon_groups'] as &$group ) {
				$group['required'] = in_array( strtolower( $group['required'] ), array( 'yes', 'y', '1', 'true', 'required' ), true );
				$group['min']      = '' === $group['min'] ? 0 : absint( $group['min'] );
				$group['max']      = '' === $group['max'] ? 0 : absint( $group['max'] );
				$group['options']  = array_values( $group['options'] );
			}
			unset( $group );
			$item['sizes']        = array_values( $item['sizes'] );
			$item['addon_groups'] = array_values( $item['addon_groups'] );
			$categories[ $category ]['items'][] = $item;
		}
		$model['categories'] = array_values( $categories );
		return $model;
	}
}
