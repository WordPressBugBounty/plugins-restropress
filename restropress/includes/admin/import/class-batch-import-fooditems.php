<?php
/**
 * RestroPress import class
 *
 * This class handles importing fooditems with the batch processing API
 *
 * @package     RPRESS
 * @subpackage  Admin/Import
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since  1.0.0
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;
/**
 * RPRESS_Batch_FoodItems_Import Class
 *
 * @since 1.0.0
 */
class RPRESS_Batch_FoodItems_Import extends RPRESS_Batch_Import
{

	//php 8.2 compatible
	public $done = false;

	/**
	 * Set up our import config.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init()
	{
		// Set up default field map values
		$this->field_mapping = array(
			'id' => '',
			'post_title' => '',
			'post_name' => '',
			'post_status' => 'draft',
			'post_author' => '',
			'post_date' => '',
			'post_content' => '',
			'post_excerpt' => '',
			'price' => '',
			'categories' => '',
			'addons' => '',
			'tags' => '',
			'dietary' => '',
			'tag_mark' => '',
			'addon_prices' => '',
			'addon_max' => '',
			'addon_is_required' => '',
			'sku' => '',
			'earnings' => '',
			'sales' => '',
			'featured_image' => '',
			'notes' => '',
			'variable_price_label' => '',
			'default_addons' => ''
		);
	}
	/**
	 * Process a step
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function process_step()
	{
		$more = false;
		if (!$this->can_import()) {
			wp_die(esc_html__('You do not have permission to import data.', 'restropress'), esc_html__('Error', 'restropress'), array('response' => 403));
		}
		$i = 1;
		$offset = $this->step > 1 ? ($this->per_step * ($this->step - 1)) : 0;
		if ($offset > $this->total) {
			$this->done = true;
		}
		if (!$this->done && $this->csv->data) {
			$more = true;
			foreach ($this->csv->data as $key => $row) {
				// Skip all rows until we pass our offset
				if ($key + 1 <= $offset) {
					continue;
				}
				// Done with this batch
				if ($i > $this->per_step) {
					break;
				}
				// Import RestroPress
				$args = array(
					'post_type' => 'fooditem',
					'post_title' => '',
					'post_name' => '',
					'post_status' => '',
					'post_author' => '',
					'post_date' => '',
					'post_content' => '',
					'post_excerpt' => ''
				);
				foreach ($args as $key => $field) {
					if (!empty($this->field_mapping[$key]) && !empty($row[$this->field_mapping[$key]])) {
						$args[$key] = $row[$this->field_mapping[$key]];
					}
				}
				if (empty($args['post_author'])) {
					$user = wp_get_current_user();
					$args['post_author'] = $user->ID;
				} else {
					// Check all forms of possible user inputs, email, ID, login.
					if (is_email($args['post_author'])) {
						$user = get_user_by('email', $args['post_author']);
					} elseif (is_numeric($args['post_author'])) {
						$user = get_user_by('ID', $args['post_author']);
					} else {
						$user = get_user_by('login', $args['post_author']);
					}
					// If we don't find one, resort to the logged in user.
					if (false === $user) {
						$user = wp_get_current_user();
					}
					$args['post_author'] = $user->ID;
				}
				// Format the date properly
				if (!empty($args['post_date'])) {
					$timestamp = strtotime($args['post_date'], current_time('timestamp'));
					if ($timestamp == false) {
						$date = date_i18n('Y-m-d H:i:s');
					} else {
						$date = gmdate('Y-m-d H:i:s', $timestamp);
					}
					// If the date provided results in a date string, use it, or just default to today so it imports
					if (!empty($date)) {
						$args['post_date'] = $date;
					} else {
						$date = '';
					}
				}
				// Detect any status that could map to `publish`
				if (!empty($args['post_status'])) {
					$published_statuses = array(
						'live',
						'published',
					);
					$current_status = strtolower($args['post_status']);
					if (in_array($current_status, $published_statuses)) {
						$args['post_status'] = 'publish';
					}
				}
				// Resolve an existing item to UPDATE rather than insert a new one.
				// Match first by the mapped ID column, then fall back to SKU. This
				// makes export -> edit -> re-import a true round-trip (no dupes).
				$existing_id = $this->find_existing_fooditem($row);
				if ($existing_id > 0) {
					// Only update the post fields the CSV actually carried; drop
					// empty/unmapped ones so re-importing a partial sheet never
					// blanks an existing title, description, status, etc.
					foreach (array('post_title', 'post_name', 'post_status', 'post_date', 'post_content', 'post_excerpt') as $maybe_empty) {
						if (empty($args[$maybe_empty])) {
							unset($args[$maybe_empty]);
						}
					}
					// Keep the existing author unless the CSV explicitly carried one.
					if (empty($this->field_mapping['post_author']) || empty($row[$this->field_mapping['post_author']])) {
						unset($args['post_author']);
					}
					$args['ID'] = $existing_id;
					// Rebuild add-ons from scratch on update so the keyed/positional
					// add-on columns below don't merge on top of stale data.
					delete_post_meta($existing_id, '_addon_items');
				}
				$fooditem_id = wp_insert_post($args);
				// setup categories
				if (!empty($this->field_mapping['categories']) && !empty($row[$this->field_mapping['categories']])) {
					$strCategories = $row[$this->field_mapping['categories']];
					if (substr($strCategories, 0, 3) === " | ") {
						// Legacy nested format: " | Parent , Child".
						$this->set_taxonomy_terms_cp($fooditem_id, $this->str_to_array($strCategories), 'food-category');
					} elseif (false !== strpos($strCategories, '>')) {
						// New breadcrumb format: "Mains > Burgers | Specials".
						$this->import_category_paths($fooditem_id, $strCategories, 'food-category');
					} else {
						// Flat list ("Pizzas" or "Pizzas | Specials").
						$this->set_taxonomy_terms($fooditem_id, $this->str_to_array($strCategories), 'food-category', false);
					}

				}
				// setup addons
				if (!empty($this->field_mapping['addons']) && !empty($row[$this->field_mapping['addons']])) {
					$strAddons = $row[$this->field_mapping['addons']];
					if (substr($strAddons, 0, 3) === " | ") {
						// Legacy nested format: " | Group , Item , Item".
						$this->set_taxonomy_terms_cp($fooditem_id, $this->str_to_array($strAddons), 'addon_category');
					} elseif (false !== strpos($strAddons, ':')) {
						// New grouped format: "Group: Item, Item | Group2: Item".
						$this->import_addons_grouped($fooditem_id, $strAddons);
					} else {
						$addons = $this->str_to_array($strAddons);
						$this->set_taxonomy_terms($fooditem_id, $addons, 'addon_category', isset($addons[0]) ? $addons[0] : false);
					}

				}
				// setup tags
				if (!empty($this->field_mapping['tags']) && !empty($row[$this->field_mapping['tags']])) {
					$tags = $this->str_to_array($row[$this->field_mapping['tags']]);
					$this->set_taxonomy_terms($fooditem_id, $tags, 'fooditem_tag', false);
				}
				// setup dietary labels (Vegetarian, Vegan, Gluten-free, Halal, ...)
				// Standardised on ; / , separators so the same value works in the
				// onboarding spreadsheet importer too (which reserves | for columns).
				if (!empty($this->field_mapping['dietary']) && !empty($row[$this->field_mapping['dietary']]) && taxonomy_exists('dietary')) {
					$dietary = array_values(array_filter(array_map('trim', preg_split('/[;,]+/', $row[$this->field_mapping['dietary']]))));
					if (!empty($dietary)) {
						$this->set_taxonomy_terms($fooditem_id, $dietary, 'dietary', false);
					}
				}
				// setup tag mark
				if (!empty($this->field_mapping['tag_mark']) && !empty($row[$this->field_mapping['tag_mark']])) {
					update_post_meta($fooditem_id, 'rpress_food_type', sanitize_text_field($row[$this->field_mapping['tag_mark']]));
				}
				// setup price(s)
				if (!empty($this->field_mapping['price']) && !empty($row[$this->field_mapping['price']])) {
					$price = $row[$this->field_mapping['price']];
					$this->set_price($fooditem_id, $price);
				}
				// Product Image
				if (!empty($this->field_mapping['featured_image']) && !empty($row[$this->field_mapping['featured_image']])) {
					$image = sanitize_text_field($row[$this->field_mapping['featured_image']]);
					$this->set_image($fooditem_id, $image, $args['post_author']);
				}
				// Sale count
				if (!empty($this->field_mapping['sales']) && !empty($row[$this->field_mapping['sales']])) {
					update_post_meta($fooditem_id, '_rpress_fooditem_sales', absint($row[$this->field_mapping['sales']]));
				}
				// Earnings
				if (!empty($this->field_mapping['earnings']) && !empty($row[$this->field_mapping['earnings']])) {
					update_post_meta($fooditem_id, '_rpress_fooditem_earnings', rpress_sanitize_amount($row[$this->field_mapping['earnings']]));
				}
				// Notes
				if (!empty($this->field_mapping['notes']) && !empty($row[$this->field_mapping['notes']])) {
					update_post_meta($fooditem_id, 'rpress_product_notes', sanitize_text_field($row[$this->field_mapping['notes']]));
				}
				// Variation Price Label
				if (!empty($this->field_mapping['variable_price_label']) && !empty($row[$this->field_mapping['variable_price_label']])) {
					update_post_meta($fooditem_id, 'rpress_variable_price_label', sanitize_text_field($row[$this->field_mapping['variable_price_label']]));
				}
				// SKU
				if (!empty($this->field_mapping['sku']) && !empty($row[$this->field_mapping['sku']])) {
					update_post_meta($fooditem_id, 'rpress_sku', sanitize_text_field($row[$this->field_mapping['sku']]));
				}
				if (!empty($this->field_mapping['addon_prices']) && !empty($row[$this->field_mapping['addon_prices']])) {
					$price = $row[$this->field_mapping['addon_prices']];
					$this->set_addon_prices($fooditem_id, $price);
				}
				if (!empty($this->field_mapping['addon_max']) && !empty($row[$this->field_mapping['addon_max']])) {
					$price = $row[$this->field_mapping['addon_max']];
					$this->set_addon_max($fooditem_id, $price);
				}
				if (!empty($this->field_mapping['addon_is_required']) && !empty($row[$this->field_mapping['addon_is_required']])) {
					$price = $row[$this->field_mapping['addon_is_required']];
					$this->set_addon_required($fooditem_id, $price);
				}
				if (!empty($this->field_mapping['default_addons']) && !empty($row[$this->field_mapping['default_addons']])) {
					$price = $row[$this->field_mapping['default_addons']];
					$this->set_addon_default($fooditem_id, $price);
				}
				// Custom fields
				// Code goes here
				$i++;
			}
		}
		return $more;
	}
	/**
	 * Return the calculated completion percentage
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function get_percentage_complete()
	{
		if ($this->total > 0) {
			$percentage = ($this->step * $this->per_step / $this->total) * 100;
		}
		if ($percentage > 100) {
			$percentage = 100;
		}
		return $percentage;
	}
	/**
	 * Find or create a term by name under a specific parent.
	 *
	 * Unlike a bare name lookup, this disambiguates children that share a name
	 * across groups (e.g. "Regular" under both "Size" and "Crust").
	 *
	 * @since 3.3
	 * @param string $name     Term name.
	 * @param string $taxonomy Taxonomy.
	 * @param int    $parent   Parent term ID.
	 * @return int Term ID, or 0 on failure.
	 */
	private function ensure_term($name, $taxonomy, $parent = 0)
	{
		$name = trim($name);
		if ('' === $name) {
			return 0;
		}
		$existing = get_terms(array(
			'taxonomy'   => $taxonomy,
			'name'       => $name,
			'parent'     => $parent,
			'hide_empty' => false,
			'number'     => 1,
		));
		if (!is_wp_error($existing) && !empty($existing)) {
			return (int) $existing[0]->term_id;
		}
		$created = wp_insert_term($name, $taxonomy, array('parent' => (int) $parent));
		if (is_wp_error($created)) {
			// Slug collision with a differently-parented term: fall back to a name match.
			$fallback = get_term_by('name', $name, $taxonomy);
			return $fallback ? (int) $fallback->term_id : 0;
		}
		return (int) $created['term_id'];
	}
	/**
	 * Import breadcrumb category paths ("Mains > Burgers | Specials").
	 *
	 * Each path's full chain (every ancestor plus the leaf) is created as needed
	 * and assigned to the item, matching how the menu shows items under both the
	 * parent and child filters.
	 *
	 * @since 3.3
	 * @param int    $fooditem_id Food item ID.
	 * @param string $raw         Raw column value.
	 * @param string $taxonomy    Taxonomy.
	 * @return void
	 */
	private function import_category_paths($fooditem_id, $raw, $taxonomy)
	{
		$assign = array();
		foreach (array_filter(array_map('trim', explode('|', $raw)), 'strlen') as $path) {
			$parent = 0;
			foreach (array_filter(array_map('trim', explode('>', $path)), 'strlen') as $level) {
				$term_id = $this->ensure_term($level, $taxonomy, $parent);
				if (!$term_id) {
					continue 2;
				}
				$assign[] = $term_id;
				$parent   = $term_id;
			}
		}
		if (!empty($assign)) {
			wp_set_object_terms($fooditem_id, array_values(array_unique(array_map('absint', $assign))), $taxonomy);
		}
	}
	/**
	 * Import grouped add-ons ("Group: Item, Item | Group2: Item").
	 *
	 * Rebuilds the _addon_items meta (each parent category plus its child items)
	 * and assigns every term. Prices, max, default and required are applied
	 * afterwards by the per-column setters, matched by group name.
	 *
	 * @since 3.3
	 * @param int    $fooditem_id Food item ID.
	 * @param string $raw         Raw column value.
	 * @return void
	 */
	private function import_addons_grouped($fooditem_id, $raw)
	{
		$addon_items = array();
		$all_terms   = array();
		foreach (array_filter(array_map('trim', explode('|', $raw)), 'strlen') as $group) {
			$parts    = explode(':', $group, 2);
			$cat_name = trim($parts[0]);
			if ('' === $cat_name) {
				continue;
			}
			$cat_id = $this->ensure_term($cat_name, 'addon_category', 0);
			if (!$cat_id) {
				continue;
			}
			$all_terms[] = $cat_id;
			$entry = array('category' => (string) $cat_id, 'items' => array());
			$items_str = isset($parts[1]) ? trim($parts[1]) : '';
			if ('' !== $items_str) {
				foreach (array_filter(array_map('trim', explode(',', $items_str)), 'strlen') as $item_name) {
					$item_id = $this->ensure_term($item_name, 'addon_category', $cat_id);
					if ($item_id) {
						$entry['items'][] = (string) $item_id;
						$all_terms[]      = $item_id;
					}
				}
			}
			$addon_items[$cat_id] = $entry;
		}
		if (!empty($all_terms)) {
			wp_set_object_terms($fooditem_id, array_values(array_unique(array_map('absint', $all_terms))), 'addon_category');
		}
		if (!empty($addon_items)) {
			update_post_meta($fooditem_id, '_addon_items', $addon_items);
		}
	}
	/**
	 * Locate an existing fooditem this row should update.
	 *
	 * Matches by the mapped ID column first, then by SKU. Returns 0 when the
	 * row describes a brand new item.
	 *
	 * @since 3.3
	 * @param array $row The CSV row.
	 * @return int Existing fooditem ID, or 0.
	 */
	private function find_existing_fooditem($row)
	{
		// 1) Explicit ID column.
		if (!empty($this->field_mapping['id']) && !empty($row[$this->field_mapping['id']])) {
			$maybe_id = absint($row[$this->field_mapping['id']]);
			if ($maybe_id > 0 && 'fooditem' === get_post_type($maybe_id)) {
				return $maybe_id;
			}
		}
		// 2) SKU column.
		if (!empty($this->field_mapping['sku']) && !empty($row[$this->field_mapping['sku']])) {
			$sku = sanitize_text_field($row[$this->field_mapping['sku']]);
			if ('' !== $sku) {
				$matches = get_posts(array(
					'post_type'      => 'fooditem',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => 'rpress_sku',
							'value' => $sku,
						),
					),
				));
				if (!empty($matches)) {
					return (int) $matches[0];
				}
			}
		}
		return 0;
	}
	/**
	 * Parse a per-category add-on column.
	 *
	 * New exports key each value by category name (`Category=>value | ...`) so
	 * values match by name regardless of order. Returns a name=>value map, or
	 * false when the column uses the legacy positional format.
	 *
	 * @since 3.3
	 * @param string $raw The raw column value.
	 * @return array|false
	 */
	private function parse_keyed_addon_value($raw)
	{
		$segments = array_map('trim', explode(' | ', (string) $raw));
		$map      = array();
		$keyed    = false;
		foreach ($segments as $segment) {
			if (false !== strpos($segment, '=>')) {
				$keyed = true;
				$parts = array_map('trim', explode('=>', $segment, 2));
				if ('' !== $parts[0]) {
					$map[$parts[0]] = isset($parts[1]) ? $parts[1] : '';
				}
			}
		}
		return $keyed ? $map : false;
	}
	/**
	 * Resolve an add-on group's category name from its meta.
	 *
	 * @since 3.3
	 * @param array $food_addon One _addon_items entry.
	 * @return string
	 */
	private function addon_category_name($food_addon)
	{
		if (empty($food_addon['category'])) {
			return '';
		}
		$term = get_term(absint($food_addon['category']), 'addon_category');
		return ($term && !is_wp_error($term)) ? trim($term->name) : '';
	}
	/**
	 * Convert exported default add-on names back to item term IDs.
	 *
	 * The export writes default selections as item names (` : `-joined, with an
	 * optional `Name|Price Label` for variable-priced items). The stored meta
	 * needs the item term ID (or `itemId|Price Label`), so map each name to its
	 * term within this add-on group. Unmatched names are kept as-is.
	 *
	 * @since 3.3
	 * @param array  $food_addon The _addon_items entry (provides items[]).
	 * @param string $value      The ` : `-joined default names.
	 * @return array
	 */
	private function default_names_to_ids($food_addon, $value)
	{
		$name_to_id = array();
		if (!empty($food_addon['items']) && is_array($food_addon['items'])) {
			foreach ($food_addon['items'] as $item_id) {
				$term = get_term(absint($item_id), 'addon_category');
				if ($term && !is_wp_error($term)) {
					$name_to_id[trim($term->name)] = (string) $item_id;
				}
			}
		}
		$ids = array();
		foreach (array_filter(array_map('trim', explode(' : ', (string) $value)), 'strlen') as $token) {
			$suffix = '';
			if (false !== strpos($token, '|')) {
				list($name_part, $price_part) = array_pad(explode('|', $token, 2), 2, '');
				$suffix = '|' . $price_part;
				$token  = trim($name_part);
			}
			$ids[] = (isset($name_to_id[$token]) ? $name_to_id[$token] : $token) . $suffix;
		}
		return $ids;
	}
	/**
	 * Set up and store the addon max option for the fooditem's addon category
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_addon_max($fooditem_id = 0, $price = '')
	{
		$food_addons = get_post_meta($fooditem_id, '_addon_items', true);
		if (!is_array($food_addons)) {
			return;
		}
		$keyed = $this->parse_keyed_addon_value($price);
		if (false !== $keyed) {
			foreach ($food_addons as $addon_id => $food_addon) {
				$name = $this->addon_category_name($food_addon);
				if ('' !== $name && isset($keyed[$name]) && $keyed[$name] !== 'Not Define') {
					$food_addon['max_addons'] = $keyed[$name];
				}
				$food_addons[$addon_id] = $food_addon;
			}
			update_post_meta($fooditem_id, '_addon_items', $food_addons);
			return;
		}
		// Legacy positional format.
		$prices = (array) explode(' | ', $price);
		$i = 0;
		foreach ($food_addons as $addon_id => $food_addon) {
			// Check if the element at index $i exists and is not equal to 'Not Define'
			if (isset($prices[$i]) && $prices[$i] !== 'Not Define') {
				$food_addon['max_addons'] = $prices[$i];
			}
			$food_addons[$addon_id] = $food_addon;
			$i++;
		}

		update_post_meta($fooditem_id, '_addon_items', $food_addons);

	}
	/**
	 * Set up and store the addon required option for the fooditem's addon category
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_addon_required($fooditem_id = 0, $price = '')
	{
		$food_addons = get_post_meta($fooditem_id, '_addon_items', true);
		if (!is_array($food_addons)) {
			return;
		}
		$keyed = $this->parse_keyed_addon_value($price);
		if (false !== $keyed) {
			foreach ($food_addons as $addon_id => $food_addon) {
				$name = $this->addon_category_name($food_addon);
				if ('' !== $name && isset($keyed[$name])) {
					$food_addon['is_required'] = ('yes' === strtolower($keyed[$name])) ? 'yes' : 'no';
				}
				$food_addons[$addon_id] = $food_addon;
			}
			update_post_meta($fooditem_id, '_addon_items', $food_addons);
			return;
		}
		// Legacy positional format.
		$prices = (array) explode(' | ', $price);
		$i = 0;
		foreach ($food_addons as $addon_id => $food_addon) {
			if (isset($prices[$i]) && $prices[$i] == 'yes') {
				$food_addon['is_required'] = $prices[$i];
			}
			$food_addons[$addon_id] = $food_addon;
			$i++;
		}

		update_post_meta($fooditem_id, '_addon_items', $food_addons);

	}
	/**
	 * Set up and store the addon default option for the fooditem's addon category
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_addon_default($fooditem_id = 0, $price = '')
	{
		$food_addons = get_post_meta($fooditem_id, '_addon_items', true);
		if (!is_array($food_addons)) {
			return;
		}
		$keyed = $this->parse_keyed_addon_value($price);
		if (false !== $keyed) {
			foreach ($food_addons as $addon_id => $food_addon) {
				$name = $this->addon_category_name($food_addon);
				if ('' !== $name && isset($keyed[$name])) {
					$food_addon['default'] = $this->default_names_to_ids($food_addon, $keyed[$name]);
				}
				$food_addons[$addon_id] = $food_addon;
			}
			update_post_meta($fooditem_id, '_addon_items', $food_addons);
			return;
		}
		// Legacy positional format.
		$default_per_category = (array) explode(' | ', trim($price));

		$i = 0;
		foreach ($food_addons as $addon_id => $food_addon) {
			if (!isset($food_addon['default'])) {
				$food_addon['default'] = array();
			}
			if (isset($default_per_category[$i])) {
				$dafault_per_addon = (array) explode(' : ', $default_per_category[$i]);

				if (!empty($dafault_per_addon)) {
					$food_addon['default'] = array_merge($food_addon['default'], $dafault_per_addon);
				}
			}
			$food_addons[$addon_id] = $food_addon;
			$i++;
		}


		update_post_meta($fooditem_id, '_addon_items', $food_addons);

	}
	/**
	 * Set up and store the price for the fooditem
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_addon_prices($fooditem_id = 0, $price = '')
	{
		$variable_prices = get_post_meta($fooditem_id, 'rpress_variable_prices');
		if (is_array($variable_prices) && count($variable_prices) == 1) {
			$variable_prices = $variable_prices[0];
		}
		$variable_prices = is_array($variable_prices) ? $variable_prices : array();
		$food_addons = get_post_meta($fooditem_id, '_addon_items', true);
		$food_addons = is_array($food_addons) ? $food_addons : array();

		// New self-describing format: "Group: Item=price, Item=price | Group2: ...".
		// The `=` never appears in the legacy positional format, so it is a safe
		// discriminator. Prices are matched to items by group + option name.
		if (false !== strpos($price, '=')) {
			$by_group = array();
			foreach (array_filter(array_map('trim', explode('|', $price)), 'strlen') as $group) {
				$parts = explode(':', $group, 2);
				if (count($parts) < 2) {
					continue;
				}
				$cat_name = trim($parts[0]);
				$item_map = array();
				foreach (array_filter(array_map('trim', explode(',', $parts[1])), 'strlen') as $pair) {
					$kv = explode('=', $pair, 2);
					$item_name = trim($kv[0]);
					if ('' !== $item_name) {
						$item_map[$item_name] = isset($kv[1]) ? trim($kv[1]) : '';
					}
				}
				if ('' !== $cat_name) {
					$by_group[$cat_name] = $item_map;
				}
			}
			foreach ($food_addons as $addon_id => $food_addon) {
				$cat_name = $this->addon_category_name($food_addon);
				if ('' === $cat_name || !isset($by_group[$cat_name]) || empty($food_addon['items'])) {
					continue;
				}
				$item_map   = $by_group[$cat_name];
				$price_data = array();
				foreach ($food_addon['items'] as $item_id) {
					$term      = get_term(absint($item_id), 'addon_category');
					$item_name = ($term && !is_wp_error($term)) ? trim($term->name) : '';
					if ('' === $item_name || !isset($item_map[$item_name])) {
						continue;
					}
					$val = $item_map[$item_name];
					if (!empty($variable_prices) && false !== strpos($val, ' : ')) {
						// Variable-priced option: map sub-amounts to the variation names.
						$var_prices = array_map('trim', explode(' : ', $val));
						$var_data   = array();
						$i = 0;
						foreach ($variable_prices as $food_variable) {
							if (isset($var_prices[$i], $food_variable['name'])) {
								$var_data[str_replace(' ', '', $food_variable['name'])] = '' . $var_prices[$i];
							}
							$i++;
						}
						$price_data[$item_id] = $var_data;
					} else {
						$price_data[$item_id] = $val;
					}
				}
				$food_addon['prices']   = $price_data;
				$food_addons[$addon_id] = $food_addon;
			}
			update_post_meta($fooditem_id, '_addon_items', $food_addons);
			return;
		}

		// Legacy positional format.
		$prices = (array) explode(' | ', $price);
		$price_index = 0;

		foreach ($food_addons as $addon_id => $food_addon) {
			$price = array();
			if (isset($food_addon['items']) && is_array($food_addon['items'])) {
				foreach ($food_addon['items'] as $key => $addonId) {
					if (!empty($variable_prices) && isset($prices[$price_index])) {
						$var_prices = array_map('trim', (array) explode(' : ', $prices[$price_index]));
						$priceVarData = array();
						$i = 0;
						foreach ($variable_prices as $foodVarKey => $food_variable) {
							if (isset($var_prices[$i], $food_variable['name'])) {
								$priceVarData[str_replace(' ', '', $food_variable['name'])] = '' . $var_prices[$i];
							}

							$i++;
						}
						$price[$addonId] = $priceVarData;
					} else {
						if (isset($prices[$price_index])) {
							$price[$addonId] = $prices[$price_index];
						}
					}
					$price_index++;
				}
			}
			$food_addon['prices'] = $price;
			$food_addons[$addon_id] = $food_addon;
		}

		update_post_meta($fooditem_id, '_addon_items', $food_addons);

	}
	/**
	 * Set up and store the price for the fooditem
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_price($fooditem_id = 0, $price = '')
	{
		if (is_numeric($price)) {
			update_post_meta($fooditem_id, 'rpress_price', rpress_sanitize_amount($price));
		} else {
			$prices = $this->str_to_array($price);
			if (!empty($prices)) {
				$variable_prices = array();
				$price_id = 1;
				foreach ($prices as $price) {
					// See if this matches the RPRESS RestroPress export for variable prices
					if (false !== strpos($price, ':')) {
						$price = array_map('trim', explode(':', $price, 2));
						if (2 === count($price)) {
							$variable_prices[$price_id] = array('name' => $price[0], 'amount' => $price[1]);
							$price_id++;
						}
					}
				}
				update_post_meta($fooditem_id, '_variable_pricing', 1);
				update_post_meta($fooditem_id, 'rpress_variable_prices', $variable_prices);
			}
		}
	}
	/**
	 * Set up and store the file fooditems
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_files($fooditem_id = 0, $files = array())
	{
		if (!empty($files)) {
			$fooditem_files = array();
			$file_id = 1;
			foreach ($files as $file) {
				$condition = '';
				if (false !== strpos($file, ';')) {
					$split_on = strpos($file, ';');
					$file_url = substr($file, 0, $split_on);
					$condition = substr($file, $split_on + 1);
				} else {
					$file_url = $file;
				}
				$fooditem_file_args = array(
					'file' => $file_url,
					'name' => basename($file_url),
				);
				if (!empty($condition)) {
					$fooditem_file_args['condition'] = $condition;
				}
				$fooditem_files[$file_id] = $fooditem_file_args;
				$file_id++;
			}
			update_post_meta($fooditem_id, 'rpress_fooditem_files', $fooditem_files);
		}
	}
	/**
	 * Set up and store the Featured Image
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_image($fooditem_id = 0, $image = '', $post_author = 0)
	{
		$is_url = false !== filter_var($image, FILTER_VALIDATE_URL);
		$is_local = $is_url && false !== strpos(site_url(), $image);
		$ext = rpress_get_file_extension($image);
		if ($is_url && $is_local) {
			// Image given by URL, see if we have an attachment already
			$attachment_id = attachment_url_to_postid($image);
		} elseif ($is_url) {
			if (!function_exists('media_sideload_image')) {
				require_once(ABSPATH . 'wp-admin/includes/file.php');
			}
			// Image given by external URL
			$url = media_sideload_image($image, $fooditem_id, '', 'src');
			if (!is_wp_error($url)) {
				$attachment_id = attachment_url_to_postid($url);
			}
		} elseif (false === strpos($image, '/') && rpress_get_file_extension($image)) {
			// Image given by name only
			$upload_dir = wp_upload_dir();
			if (file_exists(trailingslashit($upload_dir['path']) . $image)) {
				// Look in current upload directory first
				$file = trailingslashit($upload_dir['path']) . $image;
			} else {
				// Now look through year/month sub folders of upload directory for files with our image's same extension
				$files = glob($upload_dir['basedir'] . '/*/*/*{' . $ext . '}', GLOB_BRACE);
				foreach ($files as $file) {
					if (basename($file) == $image) {
						// Found our file
						break;
					}
					// Make sure $file is unset so our empty check below does not return a false positive
					unset($file);
				}
			}
			if (!empty($file)) {
				// We found the file, let's see if it already exists in the media library
				$guid = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file);
				$attachment_id = attachment_url_to_postid($guid);
				if (empty($attachment_id)) {
					// Doesn't exist in the media library, let's add it
					$filetype = wp_check_filetype(basename($file), null);
					// Prepare an array of post data for the attachment.
					$attachment = array(
						'guid' => $guid,
						'post_mime_type' => $filetype['type'],
						'post_title' => preg_replace('/\.[^.]+$/', '', $image),
						'post_content' => '',
						'post_status' => 'inherit',
						'post_author' => $post_author
					);
					// Insert the attachment.
					$attachment_id = wp_insert_attachment($attachment, $file, $fooditem_id);
					// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
					require_once(ABSPATH . 'wp-admin/includes/image.php');
					// Generate the metadata for the attachment, and update the database record.
					$attach_data = wp_generate_attachment_metadata($attachment_id, $file);
					wp_update_attachment_metadata($attachment_id, $attach_data);
				}
			}
		}
		if (!empty($attachment_id)) {
			return set_post_thumbnail($fooditem_id, $attachment_id);
		}
		return false;
	}
	/**
	 * Set up and taxonomy terms with child
	 *
	 * @since 2.9.9
	 * @return void
	 */
	private function set_taxonomy_terms_cp($fooditem_id = 0, $terms = array(), $taxonomy = 'addon_category')
	{
		if (!empty($terms)) {
			if ('addon_category' == $taxonomy) {
				$addons = get_post_meta($fooditem_id, '_addon_items');
			} else {
				$addons = array();
			}
			if (!is_array($addons) || empty($addons)) {
				$addons = array();
			}
			foreach ($terms as $input) {
				if (empty($input)) {
					continue;
				}
				$terms_array = explode(' , ', $input);
				// Trim whitespaces from each term
				$terms_array = array_values(array_filter(array_map('trim', $terms_array), 'strlen'));
				if (empty($terms_array) || !is_array($terms_array)) {
					// Insufficient terms to create
					continue; // Skip
				}
				// First term is the parent
				$parent_term_name = rtrim(reset($terms_array), ' ,');
				$parent_term_slug = sanitize_title($parent_term_name);
				if (empty($parent_term_slug)) {
					// Insufficient terms to create
					continue; // Skip
				}
				$terms_ids = wp_set_object_terms($fooditem_id, $parent_term_name, $taxonomy);
				if (empty($terms_ids)) {
					continue;
				}
				$parent_term_id = $terms_ids[0];
				if (isset($parent_term_id) && 'addon_category' == $taxonomy) {
					if (!isset($addons['' . $parent_term_id])) {
						$addons['' . $parent_term_id] = array(
							'category' => '' . $parent_term_id,
						);
					}
				}
				// Create child terms and associate them with the parent
				array_splice($terms_array, 0, 1);
				foreach ($terms_array as $child_term_name) {
					$child_term_slug = sanitize_title(rtrim($child_term_name, ' ,'));
					if (empty($child_term_slug)) {
						// Insufficient terms to create
						continue; // Skip
					}
					// Check if the child term already exists
					$child_term_exists = term_exists($child_term_slug, $taxonomy);
					if ($child_term_exists) {
						$child_term_id = $child_term_exists['term_id'];
					} else {
						// Create the child term
						$child_term_args = array(
							'parent' => $parent_term_id,
							'taxonomy' => $taxonomy,
							'description' => '', // You can set a description if needed
							'slug' => $child_term_slug,
						);
						$child_term = wp_insert_term($child_term_name, $taxonomy, $child_term_args);
						if (is_wp_error($child_term)) {
							// Handle the error if any
							error_log('Error creating child term: ' . $child_term->get_error_message());
							continue; // Skip to the next iteration
						}
						$child_term_id = $child_term['term_id'];
					}
					// Set the terms for the specified object

					if ('addon_category' == $taxonomy) {
						if (!isset($addons['' . $parent_term_id]['items'])) {
							$addons['' . $parent_term_id]['items'] = array();
						}
						$addons['' . $parent_term_id]['items'][] = '' . $child_term_id;
					}
				}
				if (!empty($addons)) {
					unset($addons['0']);
					update_post_meta($fooditem_id, '_addon_items', $addons);
				}
				if (!empty($terms_array)) {
					array_unshift($terms_array, $parent_term_slug);
					wp_set_object_terms($fooditem_id, $terms_array, $taxonomy);
				}
			}
			return $addons;
		}
	}
	/**
	 * Set up and taxonomy terms
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_taxonomy_terms($fooditem_id = 0, $terms = array(), $taxonomy = 'food-category', $parent_addon = false)
	{
		$terms = $this->maybe_create_terms($terms, $taxonomy, $parent_addon);
		if (!empty($terms)) {
			wp_set_object_terms($fooditem_id, $terms, $taxonomy);
		}
		if (!empty($terms) && 'addon_category' == $taxonomy) {
			$all_addons = get_terms(array(
				'taxonomy' => 'addon_category',
				'include' => $terms
			));
			$addon_items = array();
			if (!is_wp_error($all_addons)) {
				foreach ($all_addons as $addon) {
					if ($addon->parent != 0)
						continue;
					$addon_items[$addon->term_id] = array(
						'category' => $addon->term_id,
						'items' => []
					);
				}
				foreach ($all_addons as $addon) {
					if ($addon->parent == 0)
						continue;
					$addon_items[$addon->parent]['items'][] = $addon->term_id;
				}
			}
			if (!empty($addon_items)) {
				update_post_meta($fooditem_id, '_addon_items', $addon_items);
			}
		}
	}
	/**
	 * Locate term IDs or create terms if none are found
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function maybe_create_terms($terms = array(), $taxonomy = 'food-category', $parent_addon = false)
	{
		// Return of term IDs
		$term_ids = array();
		foreach ($terms as $term) {
			$parent_id = 0;
			// Get parent Addon ID
			$parent = get_term_by('slug', $parent_addon, $taxonomy);
			if (!empty($parent)) {
				$parent_id = $parent->term_id;
			}
			if (is_numeric($term) && 0 === (int) $term) {
				$t = get_term($term, $taxonomy);
			} else {
				$t = get_term_by('name', $term, $taxonomy);
				if (!$t) {
					$t = get_term_by('slug', $term, $taxonomy);
				}
			}
			if (!empty($t)) {
				$term_ids[] = $t->term_id;
			} else {
				$term_data = wp_insert_term($term, $taxonomy, array('slug' => sanitize_title($term), 'parent' => $parent_id));
				if (!is_wp_error($term_data)) {
					$term_ids[] = $term_data['term_id'];
				}
			}
		}
		return array_map('absint', $term_ids);
	}
	/**
	 * Retrieve URL to RestroPress list table
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_list_table_url()
	{
		return admin_url('edit.php?post_type=fooditem');
	}
	/**
	 * Retrieve RestroPress label
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function get_import_type_label()
	{
		return rpress_get_label_plural(true);
	}
}
