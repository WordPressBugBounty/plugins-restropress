<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Batch RestroPress Export Class
 *
 * This class handles fooditem products export
 *
 * @package     RPRESS
 * @subpackage  Admin/Reports
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 1.0
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;
/**
 * RPRESS_Batch_RestroPress_Export Class
 *
 * @since  1.0.0
 */
class RPRESS_Batch_RestroPress_Export extends RPRESS_Batch_Export
{
	/**
	 * Our export type. Used for export-type specific filters/actions
	 *
	 * @var string
	 * @since  1.0.0
	 */
	public $export_type = 'fooditems';
	/**
	 * Food-category term IDs to limit the export to.
	 *
	 * @var array
	 * @since 3.3
	 */
	public $categories = array();
	/**
	 * Post statuses to include.
	 *
	 * @var array
	 * @since 3.3
	 */
	public $statuses = array();
	/**
	 * Food type filter (e.g. veg / non-veg slug stored in rpress_food_type).
	 *
	 * @var string
	 * @since 3.3
	 */
	public $food_type = '';
	/**
	 * Column keys to export. Empty means every column.
	 *
	 * @var array
	 * @since 3.3
	 */
	public $columns = array();
	/**
	 * Capture the export filters posted from the export form.
	 *
	 * @since 3.3
	 * @param array $request The request array ($_REQUEST as parsed from the form).
	 * @return void
	 */
	public function set_properties( $request )
	{
		$this->categories = ! empty( $request['categories'] )
			? array_filter( array_map( 'absint', (array) $request['categories'] ) )
			: array();
		$this->statuses = ! empty( $request['statuses'] )
			? array_filter( array_map( 'sanitize_key', (array) $request['statuses'] ) )
			: array();
		$this->food_type = ! empty( $request['food_type'] )
			? sanitize_text_field( $request['food_type'] )
			: '';
		$this->columns = ! empty( $request['columns'] )
			? array_filter( array_map( 'sanitize_key', (array) $request['columns'] ) )
			: array();
	}
	/**
	 * Build the shared WP_Query args from the chosen filters.
	 *
	 * Used by both get_data() and get_percentage_complete() so the total and
	 * the rows always agree on what is being exported.
	 *
	 * @since 3.3
	 * @return array
	 */
	private function get_query_args()
	{
		$args = array(
			'post_type'   => 'fooditem',
			'post_status' => ! empty( $this->statuses ) ? $this->statuses : 'any',
			'orderby'     => 'ID',
			'order'       => 'ASC',
		);
		if ( ! empty( $this->categories ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'food-category',
					'field'    => 'term_id',
					'terms'    => $this->categories,
				),
			);
		}
		if ( '' !== $this->food_type ) {
			$args['meta_query'] = array(
				array(
					'key'   => 'rpress_food_type',
					'value' => $this->food_type,
				),
			);
		}
		return $args;
	}
	/**
	 * Set the CSV columns
	 *
	 * @since  1.0.0
	 * @return array $cols All the columns
	 */
	public function csv_cols()
	{
		// Column order follows how a restaurant owner reads a menu: the item and
		// its core selling fields first, then add-ons, then media/identifiers,
		// then the technical + read-only stats last. Labels are plain-English and
		// match the import screen's data-field values so a fresh export auto-maps
		// on re-import.
		$cols = array(
			'ID' => esc_html__('ID', 'restropress'),
			'post_title' => esc_html__('Name', 'restropress'),
			'categories' => esc_html__('Categories', 'restropress'),
			'rpress_price' => esc_html__('Price', 'restropress'),
			'rpress_variable_price_label' => esc_html__('Variable Price Label', 'restropress'),
			'post_content' => esc_html__('Description', 'restropress'),
			'post_excerpt' => esc_html__('Short Description', 'restropress'),
			'tag_mark' => esc_html__('Veg / Non-Veg', 'restropress'),
			'dietary' => esc_html__('Dietary', 'restropress'),
			'tags' => esc_html__('Tags', 'restropress'),
			'addons' => esc_html__('Add-ons', 'restropress'),
			'addon_prices' => esc_html__('Add-on Prices', 'restropress'),
			'addon_max' => esc_html__('Max Add-ons', 'restropress'),
			'addon_default' => esc_html__('Default Add-ons', 'restropress'),
			'addon_is_required' => esc_html__('Add-ons Required', 'restropress'),
			'_thumbnail_id' => esc_html__('Featured Image', 'restropress'),
			'rpress_sku' => esc_html__('SKU', 'restropress'),
			'rpress_product_notes' => esc_html__('Notes', 'restropress'),
			'post_status' => esc_html__('Status', 'restropress'),
			'post_name' => esc_html__('Slug', 'restropress'),
			'post_date' => esc_html__('Date Created', 'restropress'),
			'post_author' => esc_html__('Author', 'restropress'),
			'_rpress_fooditem_sales' => esc_html__('Order Count', 'restropress'),
			'_rpress_fooditem_earnings' => esc_html__('Total Revenue', 'restropress'),
		);
		// Limit to the chosen columns when the form requested a subset. ID is
		// always kept so each row stays identifiable.
		if ( ! empty( $this->columns ) ) {
			$selected = array_merge( array( 'ID' ), $this->columns );
			$cols     = array_intersect_key( $cols, array_flip( $selected ) );
		}
		return $cols;
	}
	/**
	 * Get the Export Data
	 *
	 * @since  1.0.0
	 * @return array $data The data for the CSV file
	 */
	public function get_data()
	{
		$data = array();
		$meta = array(
			'rpress_price',
			'_thumbnail_id',
			'rpress_sku',
			'rpress_product_notes',
			'rpress_variable_price_label',
			'_rpress_fooditem_sales',
			'_rpress_fooditem_earnings'
		);
		$args = array_merge(
			$this->get_query_args(),
			array(
				'posts_per_page' => 30,
				'paged'          => $this->step,
			)
		);
		$fooditems = new WP_Query($args);
		if ($fooditems->posts) {
			foreach ($fooditems->posts as $fooditem) {
				$row = array();
				foreach ($this->csv_cols() as $key => $value) {
					// Setup default value
					$row[$key] = '';
					if (in_array($key, $meta)) {
						switch ($key) {
							case '_thumbnail_id':
								$image_id = get_post_thumbnail_id($fooditem->ID);
								$row[$key] = wp_get_attachment_url($image_id);
								break;
							case 'rpress_price':
								if (rpress_has_variable_prices($fooditem->ID)) {
									$prices = array();
									foreach (rpress_get_variable_prices($fooditem->ID) as $price) {
										$prices[] = $price['name'] . ': ' . $price['amount'];
									}
									$row[$key] = implode(' | ', $prices);
								} else {
									$row[$key] = rpress_get_fooditem_price($fooditem->ID);
								}
								break;
							default:
								$row[$key] = get_post_meta($fooditem->ID, $key, true);
								break;
						}
					} else if (isset($fooditem->$key)) {
						switch ($key) {
							case 'post_author':
								$row[$key] = get_the_author_meta('user_login', $fooditem->post_author);
								break;
							default:
								$row[$key] = $fooditem->$key;
								break;
						}
					} else if ('tags' == $key) {
						$terms = get_the_terms($fooditem->ID, 'fooditem_tag');
						if ($terms) {
							$terms = wp_list_pluck($terms, 'name');
							$row[$key] = implode(' | ', $terms);
						}
					} else if ('dietary' == $key) {
							$dietary_terms = get_the_terms($fooditem->ID, 'dietary');
							if ($dietary_terms && ! is_wp_error($dietary_terms)) {
								$row[$key] = implode('; ', wp_list_pluck($dietary_terms, 'name'));
							}
						} else if ('tag_mark' == $key) {
						$food_type = get_post_meta($fooditem->ID, 'rpress_food_type', true);
						$row[$key] = $food_type;
					} else if ('categories' == $key) {
						$row[$key] = $this->get_terms_str($fooditem->ID, 'food-category');
					} else if ('addons' == $key) {
						$row[$key] = $this->get_terms_str($fooditem->ID, 'addon_category');

					} else if ('addon_prices' == $key) {
						$addons = get_post_meta($fooditem->ID, '_addon_items', true);
						if (is_array($addons)) {
							$row[$key] = $this->get_addon_prices_str($addons);
						}

					} else if ('addon_max' == $key) {
						$addons = get_post_meta($fooditem->ID, '_addon_items', true);
						if (is_array($addons)) {
							$row[$key] = $this->keyed_addon_str(
								$addons,
								function ($addon) {
									return isset($addon['max_addons']) ? (string) $addon['max_addons'] : '';
								}
							);
						}

					} else if ('addon_default' == $key) {
						$addons = get_post_meta($fooditem->ID, '_addon_items', true);
						if (is_array($addons)) {
							$row[$key] = $this->keyed_addon_str(
								$addons,
								function ($addon) {
									return $this->addon_default_names($addon);
								}
							);
						}

					} else if ('addon_is_required' == $key) {
						$addons = get_post_meta($fooditem->ID, '_addon_items', true);
						if (is_array($addons)) {
							$row[$key] = $this->keyed_addon_str(
								$addons,
								function ($addon) {
									return !empty($addon['is_required']) ? $addon['is_required'] : 'no';
								}
							);
						}
					}
				}
				$data[] = $row;
			}
			$data = apply_filters('rpress_export_get_data', $data);
			$data = apply_filters('rpress_export_get_data_' . $this->export_type, $data);
			return $data;
		}
		return false;
	}

	/**
	 * Return the string of terms
	 *
	 * @since  2.9.9
	 * @return string
	 */
	public function get_terms_str($foodId, $taxonomy)
	{
		if ('addon_category' === $taxonomy) {
			$addon_paths = $this->get_addon_terms_str($foodId);
			if ('' !== $addon_paths) {
				return $addon_paths;
			}
		}

		$terms = wp_get_object_terms(
			$foodId,
			$taxonomy,
			array(
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);

		if (empty($terms) || is_wp_error($terms)) {
			return '';
		}

		// Render each assigned term as a breadcrumb path from root, e.g.
		// "Mains > Burgers". Multiple categories are separated by " | ". When a
		// term is also represented as the prefix of a deeper assigned term (the
		// item sits in both the parent and the child), the shorter path is
		// dropped so we emit the most specific path only.
		$chains = array();
		foreach ($terms as $term) {
			$chain = array();
			$node  = $term;
			$guard = 0;
			while ($node && !is_wp_error($node) && $guard < 20) {
				array_unshift($chain, trim($node->name));
				if (empty($node->parent)) {
					break;
				}
				$node = get_term((int) $node->parent, $taxonomy);
				$guard++;
			}
			$chains[(int) $term->term_id] = $chain;
		}

		$paths = array();
		foreach ($chains as $tid => $chain) {
			$is_prefix = false;
			foreach ($chains as $other_tid => $other) {
				if ($tid === $other_tid) {
					continue;
				}
				if (count($chain) < count($other) && array_slice($other, 0, count($chain)) === $chain) {
					$is_prefix = true;
					break;
				}
			}
			if (!$is_prefix) {
				$paths[] = implode(' > ', $chain);
			}
		}

		$paths = array_values(array_unique(array_filter($paths, 'strlen')));

		return implode(' | ', $paths);
	}

	/**
	 * Build add-on taxonomy paths in the same order as _addon_items meta.
	 *
	 * @since 3.3
	 * @param int $foodId Food item ID.
	 * @return string
	 */
	private function get_addon_terms_str($foodId)
	{
		$addons = get_post_meta($foodId, '_addon_items', true);
		if (empty($addons) || !is_array($addons)) {
			return '';
		}

		// "Group: Item, Item | Group2: Item" - the group (parent add-on category)
		// name, a colon, then its options. Groups are separated by " | ". This
		// reads cleanly and stays visually distinct from the Categories column.
		$groups = array();
		foreach ($addons as $addon) {
			if (empty($addon['category'])) {
				continue;
			}
			$category = get_term(absint($addon['category']), 'addon_category');
			if (!$category || is_wp_error($category)) {
				continue;
			}
			$name  = trim($category->name);
			$items = array();
			if (!empty($addon['items']) && is_array($addon['items'])) {
				foreach ($addon['items'] as $item_id) {
					$item = get_term(absint($item_id), 'addon_category');
					if ($item && !is_wp_error($item)) {
						$items[] = trim($item->name);
					}
				}
			}
			if ('' === $name) {
				continue;
			}
			$groups[] = empty($items) ? $name : $name . ': ' . implode(', ', $items);
		}

		return implode(' | ', $groups);
	}

	/**
	 * Build a clean add-on prices string.
	 *
	 * @since 3.3
	 * @param array $addons Add-on meta.
	 * @return string
	 */
	private function get_addon_prices_str($addons)
	{
		// "Group: Item=price, Item=price | Group2: Item=price" - each price is
		// labelled with its option, so a human can see exactly which add-on costs
		// what instead of decoding a positional list. A price of 0 (free option)
		// is kept so it round-trips.
		$groups = array();
		foreach ($addons as $addon) {
			if (empty($addon['category']) || empty($addon['items']) || !is_array($addon['items'])) {
				continue;
			}
			$category = get_term(absint($addon['category']), 'addon_category');
			if (!$category || is_wp_error($category)) {
				continue;
			}
			$prices = isset($addon['prices']) && is_array($addon['prices']) ? $addon['prices'] : array();
			$pairs  = array();
			foreach ($addon['items'] as $item_id) {
				$item = get_term(absint($item_id), 'addon_category');
				if (!$item || is_wp_error($item)) {
					continue;
				}
				$price = isset($prices[$item_id]) ? $prices[$item_id] : '';
				if (is_array($price)) {
					// Variable-priced option: join the per-variation amounts.
					$price = $this->join_clean_values($price, ' : ');
				}
				$pairs[] = trim($item->name) . '=' . trim((string) $price);
			}
			if (!empty($pairs)) {
				$groups[] = trim($category->name) . ': ' . implode(', ', $pairs);
			}
		}

		return implode(' | ', $groups);
	}

	/**
	 * Join values after removing empty placeholder segments.
	 *
	 * @since 3.3
	 * @param array  $values Values to join.
	 * @param string $delimiter Delimiter.
	 * @return string
	 */
	private function join_clean_values($values, $delimiter = ' | ')
	{
		$values = array_map('trim', array_map('strval', (array) $values));
		$values = array_filter(
			$values,
			function ($value) {
				return '' !== $value;
			}
		);

		return implode($delimiter, $values);
	}

	/**
	 * Build a per-category add-on string keyed by category name.
	 *
	 * Produces `Category=>value | Category2=>value` so the importer can match
	 * each value to its add-on category by name rather than by column position.
	 * This survives category reordering and hand-editing in a spreadsheet.
	 * Categories whose callback yields an empty value are skipped.
	 *
	 * @since 3.3
	 * @param array    $addons   The _addon_items meta.
	 * @param callable $callback Returns the string value for one add-on group.
	 * @return string
	 */
	private function keyed_addon_str($addons, $callback)
	{
		$segments = array();
		foreach ((array) $addons as $addon) {
			if (empty($addon['category'])) {
				continue;
			}
			$category = get_term(absint($addon['category']), 'addon_category');
			if (!$category || is_wp_error($category)) {
				continue;
			}
			$name  = trim($category->name);
			$value = trim((string) call_user_func($callback, $addon));
			if ('' === $name || '' === $value) {
				continue;
			}
			$segments[] = $name . '=>' . $value;
		}

		return implode(' | ', $segments);
	}

	/**
	 * Render an add-on group's default selections as item names.
	 *
	 * The `default` meta stores add-on item term IDs (or `id|Price Name` for a
	 * variable-priced item). Exporting the bare ID is meaningless to a human, so
	 * resolve each to its term name. The importer maps the names back to IDs.
	 *
	 * @since 3.3
	 * @param array $addon One _addon_items entry.
	 * @return string Item names joined by ` : `.
	 */
	private function addon_default_names($addon)
	{
		if (empty($addon['default']) || !is_array($addon['default'])) {
			return '';
		}
		$names = array();
		foreach ($addon['default'] as $default) {
			$default = trim((string) $default);
			if ('' === $default) {
				continue;
			}
			// Variable-priced default: `itemId|Price Name`.
			$suffix = '';
			if (false !== strpos($default, '|')) {
				list($id_part, $price_part) = array_pad(explode('|', $default, 2), 2, '');
				$suffix  = '|' . $price_part;
				$default = $id_part;
			}
			$term = is_numeric($default) ? get_term(absint($default), 'addon_category') : false;
			$name = ($term && !is_wp_error($term)) ? trim($term->name) : $default;
			$names[] = $name . $suffix;
		}

		return implode(' : ', $names);
	}

	/**
	 * Return the calculated completion percentage
	 *
	 * @since  1.0.0
	 * @return int
	 */
	public function get_percentage_complete()
	{
		$args = array_merge(
			$this->get_query_args(),
			array(
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$fooditems = new WP_Query($args);
		$total = (int) $fooditems->found_posts;
		$percentage = 100;
		if ($total > 0) {
			$percentage = ((30 * $this->step) / $total) * 100;
		}
		if ($percentage > 100) {
			$percentage = 100;
		}
		return $percentage;
	}
}
