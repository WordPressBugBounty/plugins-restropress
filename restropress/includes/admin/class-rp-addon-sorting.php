<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

/**
 * Addon Category Sorting
 */
class RP_Addon_Sorting
{

    /**
     * Initializes the object instance
     */
    public function __construct()
    {
        add_action('init', array($this, 'front_end_order_terms'), 20);
        add_action('admin_head', array($this, 'admin_sort_categories'));
        add_action('wp_ajax_rp_update_addon_category_order', array($this, 'update_category_order'));
        add_action('wp_ajax_rp_get_addon_category_order', array($this, 'rp_get_category_order'));
        add_filter("get_terms", [$this, "rp_sort_addon_on_edit_texonomy_page"], 10, 3);
    }

    /**
     * Functionalities and Includes to enable Category Sorting
     *
     * @since 1.0
     * @return void
     */
    public function admin_sort_categories()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : '';

        if (empty($screen)) {
            return;
        }

        // Load on post screen, term edit screen, and taxonomy list screen
        $allowed_screens = array('post', 'edit-tags', 'term');

        if (in_array($screen->base, $allowed_screens, true)) {

            if (taxonomy_exists('addon_category')) {

                // Load CSS + JS on all addon_category screens
                $this->enqueue_assets();

                // Ensure order meta exists
                $this->set_default_term_order('addon_category');

                // Apply sorting
                add_filter('terms_clauses', array($this, 'set_tax_order'), 10, 3);
            }
        }
    }



    /**
     * Enqueueing assets for drag and drop sorting
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_assets()
    {
        wp_enqueue_style('rp-addon-sorting', RP_PLUGIN_URL . "assets/css/admin-rp-addon-sorting.css", array(), '', 'all');
        wp_enqueue_script('rp-addon-sorting', RP_PLUGIN_URL . "assets/js/admin/admin-rp-addon-sorting.js", array('jquery-ui-core', 'jquery-ui-sortable'), time(), true);
        $food_item_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;

        wp_localize_script(
            'rp-addon-sorting',
            'rp_addon_sorting_data',
            array(
                'preloader_url' => esc_url(admin_url('images/wpspin_light.gif')),
                'term_order_nonce' => wp_create_nonce('term_order_nonce'),
                'paged' => isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 0,
                'per_page_id' => "edit_addon_category_per_page",
                'fooditem_id' => $food_item_id,
                'is_variable_fooditem' => rpress_has_variable_prices($food_item_id)
            )
        );
    }

    /**
     * Setting a default term order if the drag and drop
     * is not done yet
     *
     * @since 1.0.0
     * @return void
     */
    public function set_default_term_order($tax_slug)
    {
        $terms = get_terms(array(
            'taxonomy' => $tax_slug,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms)) {
            return;
        }

        $order = $this->get_max_taxonomy_order($tax_slug);
        foreach ($terms as $term) {
            if (!get_term_meta($term->term_id, 'addon_position', true)) {
                update_term_meta($term->term_id, 'addon_position', $order);
                $order++;
            }
        }
    }

    /**
     * Get category order via AJAX
     *
     * @return void
     */
    function rp_get_category_order()
    {
        if (!check_ajax_referer('term_order_nonce', 'term_order_nonce', false)) {
            wp_send_json_error();
        }
        $fooditem_id = filter_var(wp_unslash($_POST['fooditem_id']), FILTER_SANITIZE_NUMBER_INT);
        $addon_cat_terms = get_terms(array(
            'taxonomy' => 'addon_category',
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => false,
        ));

        if (is_wp_error($addon_cat_terms)) {
            wp_send_json_error();
        }

        $return_array = [];
        foreach ($addon_cat_terms as $term_key => $addon_cat_term) {
            $return_array[$addon_cat_term->term_id] = (int) get_term_meta($addon_cat_term->term_id, 'addon_position', true);
        }

        wp_send_json_success($return_array);
        wp_die();
    }

    /**
     * Get the maximum tax_position for categories
     *
     * @since 1.0.0
     */
    private function get_max_taxonomy_order($tax_slug)
    {
        global $wpdb;
        $max_term_order = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT MAX( CAST( tm.meta_value AS UNSIGNED ) )
                FROM $wpdb->terms t
                JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id AND tt.taxonomy = '%s'
                JOIN $wpdb->termmeta tm ON tm.term_id = t.term_id WHERE tm.meta_key = 'tax_position'",
                $tax_slug
            )
        );
        $max_term_order = is_array($max_term_order) ? current($max_term_order) : 0;
        return (int) $max_term_order === 0 || empty($max_term_order) ? 1 : (int) $max_term_order + 1;
    }

    /**
     * Create custom help tab
     *
     * @since 1.0.0
     * @return void
     */
    public function custom_help_tab()
    {
        $screen = get_current_screen();
        $screen->add_help_tab(
            array(
                'id' => 'rp_addon_sorting_help_tab',
                'title' => __('Addon Category Ordering', 'restropress'),
                'content' => '<p>' . __('To reposition an addon category in the list, simply drag & drop it into the desired position. Each time you reposition a category, the data will update in the database and on the front end of your site.', 'restropress') . '</p>',
            )
        );
    }

    /**
     * Re-Order the taxonomies based on the tax_position value
     *
     * @param array $pieces     Array of SQL query clauses
     * @param array $taxonomies Array of taxonomy names
     * @param array $args       Array of term query args
     */
    public function set_tax_order($pieces, $taxonomies, $args)
    {
        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy === 'addon_category') {
                global $wpdb;
                $join_statement = " LEFT JOIN $wpdb->termmeta AS term_meta ON t.term_id = term_meta.term_id AND term_meta.meta_key = 'tax_position'";
                if (!$this->does_substring_exist($pieces['join'], $join_statement)) {
                    $pieces['join'] .= $join_statement;
                }
                $pieces['orderby'] = 'ORDER BY CAST( term_meta.meta_value AS UNSIGNED )';
            }
        }
        return $pieces;
    }

    /**
     * Check if a substring exists inside a string
     *
     * @param string $string    The main string (haystack) we're searching in
     * @param string $substring The substring we're searching for
     *
     * @return bool True if substring exists, else false
     */
    protected function does_substring_exist($string, $substring)
    {
        return strstr($string, $substring) !== false;
    }

    /**
     * Ajax callback function to update new sorting order
     *
     * @since 1.0.0
     */
    public function update_category_order()
    {
        if (!check_ajax_referer('term_order_nonce', 'term_order_nonce', false)) {
            wp_send_json_error();
        }

        $taxonomy_ordering_data = filter_var_array(wp_unslash($_POST['taxonomy_ordering_data']), FILTER_SANITIZE_NUMBER_INT);
        $base_index = filter_var(wp_unslash($_POST['base_index']), FILTER_SANITIZE_NUMBER_INT);

        foreach ($taxonomy_ordering_data as $order_data) {
            // Due to the way WordPress shows parent categories on multiple pages, we need to check if the parent category's position should be updated
            if ($base_index > 0) {
                $current_position = get_term_meta($order_data['term_id'], 'addon_position', true);
                if ((int) $current_position < (int) $base_index) {
                    continue;
                }
            }
            update_term_meta($order_data['term_id'], 'addon_position', ((int) $order_data['order'] + (int) $base_index));
        }

        wp_send_json_success();
    }

    /**
     * Sort categories on Frontend as per new sorting order
     *
     * @since 1.0.0
     */
    public function front_end_order_terms()
    {
        if (!is_admin()) {
            add_filter('terms_clauses', array($this, 'set_tax_order'), 10, 3);
        }
    }
    public function rp_sort_addon_on_edit_texonomy_page($terms, $taxonomies, $args)
    {

        // Only target addon_category
        if (!in_array('addon_category', (array) $taxonomies, true)) {
            return $terms;
        }

        usort($terms, function ($a, $b) {
            if(is_object($a) && is_object($b)) {
                $pos_a = (int) get_term_meta($a->term_id, 'addon_position', true);
                $pos_b = (int) get_term_meta($b->term_id, 'addon_position', true);
                return $pos_a <=> $pos_b;
            }
        });

        return $terms;
    }
}

// Initialize the class
new RP_Addon_Sorting();