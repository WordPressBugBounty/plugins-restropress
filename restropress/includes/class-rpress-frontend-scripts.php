<?php
/**
 * Handle frontend scripts
 *
 * @package RestroPress/Classes
 * @version 3.0
 */
if (!defined('ABSPATH')) {
  exit;
}
/**
 * Frontend scripts class.
 */
class RP_Frontend_Scripts
{
  /**
   * Contains an array of script handles registered by RP.
   *
   * @var array
   */
  private static $scripts = array();
  /**
   * Contains an array of script handles registered by RP.
   *
   * @var array
   */
  private static $styles = array();
  /**
   * Contains an array of script handles localized by RP.
   *
   * @var array
   */
  private static $wp_localize_scripts = array();
  /**
   * Hook in methods.
   */
  public static function init()
  {
    add_action('wp_enqueue_scripts', array(__CLASS__, 'load_scripts'));
    add_action('wp_enqueue_scripts', array(__CLASS__, 'register_styles'));
    add_action('wp_head', array(__CLASS__, 'rp_head_styles'));
    add_action('wp_head', array(__CLASS__, 'rp_head_colors'));
  }
  /**
   * Return asset URL.
   *
   * @param string $path Assets path.
   * @return string
   */
  private static function get_asset_url($path)
  {
    return apply_filters('rpress_get_asset_url', plugins_url($path, RP_PLUGIN_FILE), $path);
  }
  /**
   * Register a script for use.
   *
   * @uses   wp_register_script()
   * @param  string   $handle    Name of the script. Should be unique.
   * @param  string   $path      Full URL of the script, or path of the script relative to the WordPress root directory.
   * @param  string[] $deps      An array of registered script handles this script depends on.
   * @param  string   $version   String specifying script version number, if it has one, which is added to the URL as a query string for cache busting purposes. If version is set to false, a version number is automatically added equal to current installed WordPress version. If set to null, no version is added.
   * @param  boolean  $in_footer Whether to enqueue the script before </body> instead of in the <head>. Default 'false'.
   */
  private static function register_script($handle, $path, $deps = array('jquery'), $version = RP_VERSION, $in_footer = true)
  {
    self::$scripts[] = $handle;
    wp_register_script($handle, $path, $deps, $version, $in_footer);
  }
  /**
   * Register and enqueue a script for use.
   *
   * @uses   wp_enqueue_script()
   * @param  string   $handle    Name of the script. Should be unique.
   * @param  string   $path      Full URL of the script, or path of the script relative to the WordPress root directory.
   * @param  string[] $deps      An array of registered script handles this script depends on.
   * @param  string   $version   String specifying script version number, if it has one, which is added to the URL as a query string for cache busting purposes. If version is set to false, a version number is automatically added equal to current installed WordPress version. If set to null, no version is added.
   * @param  boolean  $in_footer Whether to enqueue the script before </body> instead of in the <head>. Default 'false'.
   */
  private static function enqueue_script($handle, $path = '', $deps = array('jquery'), $version = RP_VERSION, $in_footer = true)
  {
    if (!in_array($handle, self::$scripts, true) && $path) {
      self::register_script($handle, $path, $deps, $version, $in_footer);
    }
    wp_enqueue_script($handle);
  }
  /**
   * Register a style for use.
   *
   * @uses   wp_register_style()
   * @param  string   $handle  Name of the stylesheet. Should be unique.
   * @param  string   $path    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
   * @param  string[] $deps    An array of registered stylesheet handles this stylesheet depends on.
   * @param  string   $version String specifying stylesheet version number, if it has one, which is added to the URL as a query string for cache busting purposes. If version is set to false, a version number is automatically added equal to current installed WordPress version. If set to null, no version is added.
   * @param  string   $media   The media for which this stylesheet has been defined. Accepts media types like 'all', 'print' and 'screen', or media queries like '(orientation: portrait)' and '(max-width: 640px)'.
   * @param  boolean  $has_rtl If has RTL version to load too.
   */
  private static function register_style($handle, $path, $deps = array(), $version = RP_VERSION, $media = 'all', $has_rtl = false)
  {
    self::$styles[] = $handle;
    wp_register_style($handle, $path, $deps, $version, $media);
    if ($has_rtl) {
      wp_style_add_data($handle, 'rtl', 'replace');
    }
  }
  /**
   * Register and enqueue a styles for use.
   *
   * @uses   wp_enqueue_style()
   * @param  string   $handle  Name of the stylesheet. Should be unique.
   * @param  string   $path    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
   * @param  string[] $deps    An array of registered stylesheet handles this stylesheet depends on.
   * @param  string   $version String specifying stylesheet version number, if it has one, which is added to the URL as a query string for cache busting purposes. If version is set to false, a version number is automatically added equal to current installed WordPress version. If set to null, no version is added.
   * @param  string   $media   The media for which this stylesheet has been defined. Accepts media types like 'all', 'print' and 'screen', or media queries like '(orientation: portrait)' and '(max-width: 640px)'.
   * @param  boolean  $has_rtl If has RTL version to load too.
   */
  private static function enqueue_style($handle, $path = '', $deps = array(), $version = RP_VERSION, $media = 'all', $has_rtl = false)
  {
    if (!in_array($handle, self::$styles, true) && $path) {
      self::register_style($handle, $path, $deps, $version, $media, $has_rtl);
    }
    wp_enqueue_style($handle);
  }
  /**
   * Register all RP scripts.
   */
  private static function register_scripts()
  {
    $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
    $frontend_script_version = RP_VERSION;
    $frontend_script_path = trailingslashit(RP_PLUGIN_DIR) . 'assets/js/frontend/rp-frontend.js';
    if (file_exists($frontend_script_path)) {
      $frontend_script_version = RP_VERSION . '.' . filemtime($frontend_script_path);
    }

    $register_scripts = array(
      'jquery-cookies' => array(
        'src' => self::get_asset_url('assets/js/jquery.cookies.min.js'),
        'deps' => array('jquery'),
        'version' => RP_VERSION,
      ),
      'sticky-sidebar' => array(
        'src' => self::get_asset_url('assets/js/sticky-sidebar/rpress-sticky-sidebar.js'),
        'deps' => array('jquery'),
        'version' => '1.7.0',
      ),
      'timepicker' => array(
        'src' => self::get_asset_url('assets/js/timepicker/jquery.timepicker' . $suffix . '.js'),
        'deps' => array('jquery'),
        'version' => '1.11.14',
      ),
      'rp-fancybox' => array(
        'src' => self::get_asset_url('assets/js/jquery.fancybox.js'),
        'deps' => array('jquery'),
        'version' => RP_VERSION,
      ),
      'rp-checkout' => array(
        'src' => self::get_asset_url('assets/js/frontend/rp-checkout' . $suffix . '.js'),
        'deps' => array('jquery'),
        'version' => RP_VERSION,
      ),
      'jquery-payment' => array(
        'src' => self::get_asset_url('assets/js/jquery.payment' . $suffix . '.js'),
        'deps' => array('jquery'),
        'version' => '3.0.0',
      ),
      'jquery-creditcard-validator' => array(
        'src' => self::get_asset_url('assets/js/jquery.creditCardValidator' . $suffix . '.js'),
        'deps' => array('jquery'),
        'version' => '1.3.3',
      ),
      'jquery-chosen' => array(
        'src' => self::get_asset_url('assets/js/jquery-chosen/chosen.jquery' . $suffix . '.js'),
        'deps' => array('jquery'),
        'version' => '1.8.2',
      ),
      'jquery-flot' => array(
        'src' => self::get_asset_url('assets/js/jquery-flot/jquery-flot' . $suffix . '.js'),
        'deps' => array('jquery'),
        'version' => '0.7',
      ),
      'rp-frontend' => array(
        'src' => self::get_asset_url('assets/js/frontend/rp-frontend.js'),
        'deps' => array('jquery'),
        'version' => $frontend_script_version,
      ),
      'rp-ajax' => array(
        'src' => self::get_asset_url('assets/js/frontend/rp-ajax.js'),
        'deps' => array('jquery'),
        'version' => RP_VERSION,
      ),
      'rp-tabs' => array(
        'src' => self::get_asset_url('assets/js/frontend/rp-tabs.js'),
        'deps' => array('jquery'),
        'version' => RP_VERSION,
      ),
      'rp-modal' => array(
        'src' => self::get_asset_url('assets/js/frontend/rp-modal.js'),
        'deps' => array('jquery'),
        'version' => RP_VERSION,
      ),
      'rp-toast' => array(
        'src' => self::get_asset_url('assets/js/rp-tata.js'),
        'deps' => array('jquery'),
        'version' => RP_VERSION,
      ),
      'rp-reorder' => array(
        'src' => self::get_asset_url('assets/js/frontend/rp-reorder.js'),
        'deps' => array('jquery'),
        'version' => RP_VERSION,
      )
    );
    foreach ($register_scripts as $name => $props) {
      self::register_script($name, $props['src'], $props['deps'], $props['version']);
    }
  }
  /**
   * Register/queue frontend scripts.
   */
  public static function load_scripts()
  {
    global $post;
    self::register_scripts();
    self::enqueue_script('jquery-cookies');
    self::enqueue_script('rp-toast');
    self::enqueue_script('swl-js');
    $user_params = array(
      'ajaxurl' => rpress_get_ajax_url(),
      'address_nonce' => wp_create_nonce( 'rpress-user-address' ),
      'order_details_nonce' => wp_create_nonce( 'show-order-details' ),
      'delete_confirm_text' => esc_html__( 'Are you sure you want to delete this address?', 'restropress' ),
      'delete_success_text' => esc_html__( 'Address Deleted', 'restropress' ),
      'delete_failed_text' => esc_html__( 'Unable to delete the address. Please try again.', 'restropress' ),
      'delete_error_text' => esc_html__( 'Something went wrong while deleting the address. Please try again.', 'restropress' ),
      'edit_address_title_text' => esc_html__( 'Edit Delivery Address', 'restropress' ),
      'add_address_title_text' => esc_html__( 'Add Delivery Address', 'restropress' ),
      'save_changes_text' => esc_html__( 'Save Changes', 'restropress' ),
      'save_address_text' => esc_html__( 'Save Address', 'restropress' ),
    );
    $user_dashboard_script_version = RP_VERSION;
    $user_dashboard_script_path = trailingslashit(RP_PLUGIN_DIR) . 'assets/js/user-dashboard.js';
    if (file_exists($user_dashboard_script_path)) {
      $user_dashboard_script_version = RP_VERSION . '.' . filemtime($user_dashboard_script_path);
    }

    wp_register_script('user-dashboard-scripts', plugin_dir_url(RP_PLUGIN_FILE) . 'assets/js/user-dashboard.js', array('jquery'), $user_dashboard_script_version, true);
    wp_localize_script('user-dashboard-scripts', 'users', $user_params);
    wp_enqueue_script('user-dashboard-scripts');
    if (is_restropress_page()) {
      self::enqueue_script('sticky-sidebar');
      self::enqueue_script('rp-fancybox');
      self::enqueue_script('timepicker');
      self::enqueue_script('jquery-chosen');
      self::enqueue_script('rp-modal');
      self::enqueue_script('rp-frontend');
      self::enqueue_script('rp-tabs');
      self::enqueue_script('rp-reorder');
    }

    self::enqueue_script('rp-modal');
    self::enqueue_script('rp-frontend');
    if (rpress_is_checkout()) {
      self::enqueue_script('rp-checkout');
      if (rpress_is_cc_verify_enabled()) {
        self::enqueue_script('jquery-creditcard-validator');
        self::enqueue_script('jquery-payment');
      }
    }
    if (!rpress_is_ajax_disabled()) {
      self::enqueue_script('rp-ajax');
    }
    $add_to_cart = apply_filters('rp_add_to_cart', __('Add To Cart', 'restropress'));
    $update_cart = apply_filters('rp_update_cart', __('Update Cart', 'restropress'));
    $added_to_cart = apply_filters('rp_added_to_cart', __(' is added to cart', 'restropress'));
    $please_wait_text = esc_html__('Please Wait...', 'restropress');
    $color = rpress_get_option('primary_color', '#ED5575');
    $service_options = !empty(rpress_get_option('enable_service')) ? rpress_get_option('enable_service') : 'delivery_and_pickup';
    $default_service = rpress_get_default_enabled_service();
    $minimum_order_error_title = !empty(rpress_get_option('minimum_order_error_title')) ? rpress_get_option('minimum_order_error_title') : __('Minimum Order Error', 'restropress');
    $expire_cookie_time = !empty(rpress_get_option('expire_service_cookie')) ? rpress_get_option('expire_service_cookie') : 90;
    $cart_quantity = rpress_get_cart_quantity();
    $closed_message = rpress_get_option('store_closed_msg', __('Sorry, we are closed for ordering now.', 'restropress'));
    $params = array(
      'estimated_tax' => rpress_get_tax_name(),
      'total_text' => esc_html__('Subtotal', 'restropress'),
      'ajaxurl' => rpress_get_ajax_url(),
      'show_products_nonce' => wp_create_nonce('show-products'),
      'add_to_cart' => $add_to_cart,
      'update_cart' => $update_cart,
      'added_to_cart' => $added_to_cart,
      'please_wait' => $please_wait_text,
      'at' => esc_html__('at', 'restropress'),
      'color' => $color,
      'checkout_page' => rpress_get_checkout_uri(),
      'add_to_cart_nonce' => wp_create_nonce('add-to-cart'),
      'service_type_nonce' => wp_create_nonce('service-type'),
      'order_details_nonce' => wp_create_nonce('show-order-details'),
      'service_options' => $service_options,
      'default_service' => $default_service,
      'minimum_order_title' => $minimum_order_error_title,
      'edit_cart_fooditem_nonce' => wp_create_nonce('edit-cart-fooditem'),
      'update_cart_item_nonce' => wp_create_nonce('update-cart-item'),
      'clear_cart_nonce' => wp_create_nonce('clear-cart'),
      'update_service_nonce' => wp_create_nonce('update-service'),
      'proceed_checkout_nonce' => wp_create_nonce('proceed-checkout'),
      'error' => esc_html__('Error', 'restropress'),
      'change_txt' => esc_html__('Change?', 'restropress'),
      'asap_txt' => esc_html__('ASAP', 'restropress'),
      'currency' => rpress_get_currency(),
      'currency_sign' => rpress_currency_filter(),
      'currency_pos' => rpress_get_option('currency_position', 'before'),
      'currency_value_type' => rpress_get_currency_value_type(),
      'currency_decimals' => rpress_currency_decimal_filter(),
      'expire_cookie_time' => $expire_cookie_time,
      'confirm_empty_cart' => esc_html__('Are you sure! You want to clear the cart?', 'restropress'),
      'success' => esc_html__('Success', 'restropress'),
      'success_empty_cart' => esc_html__('Cart cleared', 'restropress'),
      'decimal_separator' => rpress_get_option('decimal_separator', '.'),
      'thousands_separator' => rpress_get_option('thousands_separator', ','),
      'cart_quantity' => $cart_quantity,
      'items' => esc_html__('Items', 'restropress'),
      'no_image' => RP_PLUGIN_URL . 'assets/images/no-image.png',
      'always_open' => !empty(rpress_get_option('enable_always_open')) ? '1' : '0',
      'open_hours' => (rpress_get_option('enable_always_open')) ? '12:00am' : rpress_get_option('open_time'),
      'close_hours' => (rpress_get_option('enable_always_open')) ? '11:59pm' : rpress_get_option('close_time'),
      'closed_message' => $closed_message,
      'old_ui_ux' => !empty(rpress_get_option('old_ui_ux')) ? '1' : '0',
    );
    $cookie_service = isset($_COOKIE['service_type']) ? sanitize_text_field(wp_unslash($_COOKIE['service_type'])) : $default_service;
    $cookie_service = apply_filters('rpress_current_service_type', $cookie_service);

    $params = apply_filters('rpress_frontend_script_vars', $params, $cookie_service);

    wp_localize_script('rp-frontend', 'rp_scripts', $params);
    $co_params = array(
      'ajaxurl' => rpress_get_ajax_url(),
      'checkout_nonce' => wp_create_nonce('rpress_checkout_nonce'),
      'checkout_error_anchor' => '#rpress_purchase_submit',
      'currency_sign' => rpress_currency_filter(''),
      'currency_pos' => rpress_get_option('currency_position', 'before'),
      'currency_value_type' => rpress_get_currency_value_type(),
      'currency_decimals' => rpress_currency_decimal_filter(),
      'decimal_separator' => rpress_get_option('decimal_separator', '.'),
      'thousands_separator' => rpress_get_option('thousands_separator', ','),
      'no_gateway' => esc_html__('Please select a payment method', 'restropress'),
      'no_discount' => esc_html__('Please enter a discount code', 'restropress'), // Blank discount code message
      'enter_discount' => esc_html__('Enter coupon code', 'restropress'),
      'discount_applied' => esc_html__('Discount Applied', 'restropress'), // Discount verified message
      'no_email' => esc_html__('Please enter an email address before applying a discount code', 'restropress'),
      'no_username' => esc_html__('Please enter a username before applying a discount code', 'restropress'),
      'purchase_loading' => esc_html__('Please Wait...', 'restropress'),
      'complete_purchase' => rpress_get_checkout_button_purchase_label(),
      'taxes_enabled' => rpress_use_taxes() ? '1' : '0',
      'rpress_version' => RP_VERSION
    );
    wp_localize_script('rp-checkout', 'rpress_global_vars', apply_filters('rpress_global_checkout_script_vars', $co_params));
    if (isset($post->ID))
      $position = rpress_get_item_position_in_cart($post->ID);
    $has_purchase_links = false;
    if ((!empty($post->post_content) && (has_shortcode($post->post_content, 'purchase_link') || has_shortcode($post->post_content, 'fooditems'))) || is_post_type_archive('fooditem'))
      $has_purchase_links = true;
    $pickup_time_enabled = rpress_is_service_enabled('pickup');
    $delivery_time_enabled = rpress_is_service_enabled('delivery');

    $ajax_params = array(
      'ajaxurl' => rpress_get_ajax_url(),
      'checkout_nonce' => wp_create_nonce('rpress_checkout_nonce'),
      'load_gateway_nonce' => wp_create_nonce('rpress_load_gateway'),
      'post_id' => isset($post->ID) ? $post->ID : '',
      'position_in_cart' => isset($position) ? $position : -1,
      'has_purchase_links' => $has_purchase_links,
      'already_in_cart_message' => esc_html__('You have already added this item to your cart', 'restropress'), // Item already in the cart message
      'empty_cart_message' => esc_html__('Your cart is empty', 'restropress'), // Item already in the cart message
      'loading' => esc_html__('Loading', 'restropress'), // General loading message
      'select_option' => esc_html__('Please select an option', 'restropress'), // Variable pricing error with multi-purchase option enabled
      'is_checkout' => rpress_is_checkout() ? '1' : '0',
      'default_gateway' => rpress_get_default_gateway(),
      'redirect_to_checkout' => (rpress_straight_to_checkout() || rpress_is_checkout()) ? '1' : '0',
      'checkout_page' => rpress_get_checkout_uri(),
      'permalinks' => get_option('permalink_structure') ? '1' : '0',
      'quantities_enabled' => rpress_item_quantities_enabled(),
      'taxes_enabled' => rpress_use_taxes() ? '1' : '0', // Adding here for widget, but leaving in checkout vars for backcompat
      'open_hours' => (rpress_get_option('enable_always_open')) ? '12:00am' : rpress_get_option('open_time'),
      'close_hours' => (rpress_get_option('enable_always_open')) ? '11:59pm' : rpress_get_option('close_time'),
      'please_wait' => esc_html__('Please Wait', 'restropress'),
      'add_to_cart' => esc_html__('Add To Cart', 'restropress'),
      'update_cart' => esc_html__('Update Cart', 'restropress'),
      'button_color' => $color,
      'color' => $color,
      'delivery_time_enabled' => $delivery_time_enabled,
      'pickup_time_enabled' => $pickup_time_enabled,
      'display_date' => rp_current_date(),
      'current_date' => rpress_get_wp_now()->format('Y-m-d'),
      'update' => esc_html__('Update', 'restropress'),
      'subtotal' => esc_html__('SubTotal', 'restropress'),
      'change_txt' => esc_html__('Change?', 'restropress'),
      'fee' => esc_html__('Fee', 'restropress'),
      'close' => esc_html__('Close', 'restropress'),
      'menu' => esc_html__('Menu', 'restropress'),
      'items' => esc_html__('Items', 'restropress'),
      'select_time_error' => esc_html__('Please select time for ', 'restropress'),
      'blurtxt' => esc_html__('Notice: Please do not click back or refresh this page, until the transaction is not completed.', 'restropress'),

    );
    $ajax_params = apply_filters('rpress_ajax_script_vars', $ajax_params);

    wp_localize_script('rp-ajax', 'rpress_scripts', $ajax_params);
    // CSS Styles.
    $enqueue_styles = self::get_styles();
    if ($enqueue_styles && is_restropress_page()) {
      foreach ($enqueue_styles as $handle => $args) {
        if (!isset($args['has_rtl'])) {
          $args['has_rtl'] = false;
        }
        self::enqueue_style($handle, $args['src'], $args['deps'], $args['version'], $args['media'], $args['has_rtl']);
      }
    }
  }
  /**
   * Register Style
   * Code taken from scripts.php present in RP2.5
   *
   */
  public static function register_styles()
  {
    if (rpress_get_option('disable_styles', false)) {
      return;
    }
    $user_dashboard_style_version = RP_VERSION;
    $user_dashboard_style_path = trailingslashit(RP_PLUGIN_DIR) . 'assets/css/user-dashboard.css';
    if (file_exists($user_dashboard_style_path)) {
      $user_dashboard_style_version = RP_VERSION . '.' . filemtime($user_dashboard_style_path);
    }

    wp_register_style('user-dashboard-styles', plugin_dir_url(RP_PLUGIN_FILE) . '/assets/css/user-dashboard.css', array(), $user_dashboard_style_version, 'all');
    wp_enqueue_style('user-dashboard-styles');
    if (!is_restropress_page()) {
      return;
    }
    // Use minified libraries if SCRIPT_DEBUG is turned off
    $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
    $file = 'rpress' . $suffix . '.css';
    $templates_dir = rpress_get_theme_template_dir_name();
    $child_theme_style_sheet = trailingslashit(get_stylesheet_directory()) . $templates_dir . $file;
    $child_theme_style_sheet_2 = trailingslashit(get_stylesheet_directory()) . $templates_dir . 'rpress.css';
    $parent_theme_style_sheet = trailingslashit(get_template_directory()) . $templates_dir . $file;
    $parent_theme_style_sheet_2 = trailingslashit(get_template_directory()) . $templates_dir . 'rpress.css';
    $rpress_plugin_style_sheet = trailingslashit(rpress_get_templates_dir()) . $file;
    // Look in the child theme directory first, followed by the parent theme, followed by the RPRESS core templates directory
    // Also look for the min version first, followed by non minified version, even if SCRIPT_DEBUG is not enabled.
    // This allows users to copy just rpress.css to their theme
    if (file_exists($child_theme_style_sheet) || (!empty($suffix) && ($nonmin = file_exists($child_theme_style_sheet_2)))) {
      if (!empty($nonmin)) {
        $url = trailingslashit(get_stylesheet_directory_uri()) . $templates_dir . 'rpress.css';
      } else {
        $url = trailingslashit(get_stylesheet_directory_uri()) . $templates_dir . $file;
      }
    } elseif (file_exists($parent_theme_style_sheet) || (!empty($suffix) && ($nonmin = file_exists($parent_theme_style_sheet_2)))) {
      if (!empty($nonmin)) {
        $url = trailingslashit(get_template_directory_uri()) . $templates_dir . 'rpress.css';
      } else {
        $url = trailingslashit(get_template_directory_uri()) . $templates_dir . $file;
      }
    } elseif (file_exists($rpress_plugin_style_sheet) || file_exists($rpress_plugin_style_sheet)) {
      $url = trailingslashit(rpress_get_templates_url()) . $file;
    }
    wp_register_style('rpress-styles', $url, array(), RP_VERSION, 'all');
    wp_enqueue_style('rpress-styles');
  }
  /**
   * Load head styles
   *
   * Ensures fooditem styling is still shown correctly if a theme is using the CSS template file
   *
   * @since  1.0.0
   * @global $post
   * @return void
   */
  public static function rp_head_styles()
  {
    global $post;
    if (rpress_get_option('disable_styles', false) || !is_object($post)) {
      return;
    }
    // Use minified libraries if SCRIPT_DEBUG is turned off
    $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
    $file = 'rpress' . $suffix . '.css';
    $templates_dir = rpress_get_theme_template_dir_name();
    $child_theme_style_sheet = trailingslashit(get_stylesheet_directory()) . $templates_dir . $file;
    $child_theme_style_sheet_2 = trailingslashit(get_stylesheet_directory()) . $templates_dir . 'rpress.css';
    $parent_theme_style_sheet = trailingslashit(get_template_directory()) . $templates_dir . $file;
    $parent_theme_style_sheet_2 = trailingslashit(get_template_directory()) . $templates_dir . 'rpress.css';
    $has_css_template = false;
    if (has_shortcode($post->post_content, 'fooditems') && file_exists($child_theme_style_sheet) || file_exists($child_theme_style_sheet_2) || file_exists($parent_theme_style_sheet) || file_exists($parent_theme_style_sheet_2)) {
      $has_css_template = apply_filters('rpress_load_head_styles', true);
    }
    if (!$has_css_template) {
      return;
    }
    ?>
    <style>
      .rpress_fooditem {
        float: left;
      }

      .rpress_fooditem_columns_1 .rpress_fooditem {
        width: 100%;
      }

      .rpress_fooditem_columns_2 .rpress_fooditem {
        width: 50%;
      }

      .rpress_fooditem_columns_0 .rpress_fooditem,
      .rpress_fooditem_columns_3 .rpress_fooditem {
        width: 33%;
      }

      .rpress_fooditem_columns_4 .rpress_fooditem {
        width: 25%;
      }

      .rpress_fooditem_columns_5 .rpress_fooditem {
        width: 20%;
      }

      .rpress_fooditem_columns_6 .rpress_fooditem {
        width: 16.6%;
      }
    </style>
    <?php
  }

  /**
   * Mix a hex color with white/black for dynamic UI tones.
   *
   * @param string $hex Hex color.
   * @param string $target Target hex color.
   * @param int    $weight Target mix percentage.
   * @return string
   */
  private static function mix_hex_color($hex, $target, $weight)
  {
    $hex = sanitize_hex_color($hex);
    $target = sanitize_hex_color($target);

    if (!$hex || !$target) {
      return '#ED5575';
    }

    $hex = ltrim($hex, '#');
    $target = ltrim($target, '#');

    if (3 === strlen($hex)) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if (3 === strlen($target)) {
      $target = $target[0] . $target[0] . $target[1] . $target[1] . $target[2] . $target[2];
    }

    $weight = max(0, min(100, absint($weight))) / 100;

    $base = sscanf('#' . $hex, '#%02x%02x%02x');
    $mix = sscanf('#' . $target, '#%02x%02x%02x');

    if (!is_array($base) || !is_array($mix) || count($base) !== 3 || count($mix) !== 3) {
      return '#ED5575';
    }

    $r = round($base[0] * (1 - $weight) + $mix[0] * $weight);
    $g = round($base[1] * (1 - $weight) + $mix[1] * $weight);
    $b = round($base[2] * (1 - $weight) + $mix[2] * $weight);

    return sprintf('#%02x%02x%02x', $r, $g, $b);
  }

  /**
   * Load head styles for Primary & Secondary colors
   *
   * @since  2.7
   * @return void
   */
  public static function rp_head_colors()
  {
    global $post;
    if (rpress_get_option('disable_styles', false) || !is_object($post)) {
      return;
    }
    if (!function_exists('is_restropress_page') || !is_restropress_page()) {
      return;
    }
    $primary_color = sanitize_hex_color(rpress_get_option('primary_color', '#ED5575'));
    if (!$primary_color) {
      $primary_color = '#ED5575';
    }
    $add_button_bg_color = sanitize_hex_color(rpress_get_option('add_button_background_color', '#FEE2E8'));
    if (!$add_button_bg_color) {
      $add_button_bg_color = '#FEE2E8';
    }
    $add_button_text_color = sanitize_hex_color(rpress_get_option('add_button_text_color', '#000000'));
    if (!$add_button_text_color) {
      $add_button_text_color = '#000000';
    }
    $button_style = sanitize_key((string) rpress_get_option('button_style', 'th-rounded'));
    $default_control_radius = '100px';
    if ('th-border-radius' === $button_style) {
      $default_control_radius = '8px';
    } elseif ('th-rectangle' === $button_style || 'th-plain' === $button_style) {
      $default_control_radius = '0px';
    }
    $rgb = sscanf($primary_color, "#%02x%02x%02x");
    $theme_contrast = '#ffffff';
    $theme_label = self::mix_hex_color($primary_color, '#000000', 28);
    $theme_dark = self::mix_hex_color($primary_color, '#000000', 22);
    $theme_light = self::mix_hex_color($primary_color, '#ffffff', 30);

    // Validate and escape
    if (is_array($rgb) && count($rgb) === 3) {
      $r = absint($rgb[0]);
      $g = absint($rgb[1]);
      $b = absint($rgb[2]);
      $rgba = esc_attr("{$r},{$g},{$b}");
      $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
      $theme_contrast = $brightness > 155 ? '#111827' : '#ffffff';

      if ($brightness > 190) {
        $theme_label = self::mix_hex_color($primary_color, '#000000', 48);
      } elseif ($brightness < 70) {
        $theme_label = self::mix_hex_color($primary_color, '#ffffff', 28);
      }
    } else {
      // fallback if invalid
      $rgba = "0,0,0";
    }
    ?>
    <style type="text/css">
      .rpress-wrap,
      .rpress-section,
      .rpress-sidebar-cart-wrap,
      .rpress-mobile-cart-icons,
      .cart_item.rpress_checkout,
      body.rpress-checkout #rpress_checkout_wrap,
      body.rpress-checkout #rpress_purchase_form,
      .rp-thankyou-page,
      #rpress_user_history.rpress-order-history,
      .user-dashboard-wrapper,
      form.rpress_form,
      .custom-reset-password,
      #rpressModal,
      #rpressModal.show-service-options,
      #rpressModal.show-order-details.rpress-order-details-context,
      #rpressDateTime.rpress-edit-address-popup {
        --rpress-theme-primary:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
        --rpress-theme-primary-rgb:
          <?php echo esc_attr($rgba); ?>
        ;
        --rpress-theme-primary-dark:
          <?php echo sanitize_hex_color($theme_dark); ?>
        ;
        --rpress-theme-primary-light:
          <?php echo sanitize_hex_color($theme_light); ?>
        ;
        --rpress-theme-primary-contrast:
          <?php echo sanitize_hex_color($theme_contrast); ?>
        ;
        --rpress-theme-label:
          <?php echo sanitize_hex_color($theme_label); ?>
        ;
        --rpress-theme-link:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
        --rpress-theme-section-bg: rgba(<?php echo esc_attr($rgba); ?>, 0.055);
        --rpress-theme-section-bg-strong: rgba(<?php echo esc_attr($rgba); ?>, 0.12);
        --rpress-theme-section-bg-soft: rgba(<?php echo esc_attr($rgba); ?>, 0.035);
        --rpress-theme-border-soft: rgba(<?php echo esc_attr($rgba); ?>, 0.18);
        --rpress-theme-border: rgba(<?php echo esc_attr($rgba); ?>, 0.32);
        --rpress-theme-focus: rgba(<?php echo esc_attr($rgba); ?>, 0.16);
        --rpress-default-control-radius:
          <?php echo esc_attr($default_control_radius); ?>
        ;
        --rp-old-ui-modal-rect-radius:
          <?php echo esc_attr($default_control_radius); ?>
        ;
      }

      body.rp-old-ui-ux-enabled #rpressModal.show-service-options,
      body.rp-old-ui-ux-enabled #rpressDateTime.rpress-edit-address-popup {
        --rp-old-ui-modal-rect-radius: var(--rpress-default-control-radius);
      }

      /* Keep frontend surfaces, labels, and links synced with Theme Color. */
      .rpress-wrap,
      .rpress-section,
      body.rpress-checkout #rpress_checkout_wrap,
      .rp-thankyou-page,
      #rpress_user_history.rpress-order-history,
      .user-dashboard-wrapper,
      form.rpress_form,
      .custom-reset-password,
      #rpressModal,
      #rpressDateTime {
        --rp-checkout-accent: var(--rpress-theme-primary);
        --rp-checkout-accent-rgb: var(--rpress-theme-primary-rgb);
        --rp-checkout-brand: var(--rpress-theme-primary);
        --rp-checkout-brand-rgb: var(--rpress-theme-primary-rgb);
        --rp-checkout-card-soft: var(--rpress-theme-section-bg-soft);
        --rp-checkout-border: var(--rpress-theme-border-soft);
        --rp-thankyou-accent: var(--rpress-theme-primary);
        --rp-thankyou-accent-ink: var(--rpress-theme-primary-dark);
        --rp-thankyou-surface: var(--rpress-theme-section-bg);
        --rp-thankyou-border: var(--rpress-theme-border-soft);
      }

      .rpress-wrap a:not(.button):not(.btn):not(.rpress-submit):not(.rpress-add-to-cart),
      body.rpress-checkout #rpress_checkout_wrap a:not(.button):not(.btn):not(.rpress-submit):not(.rpress-add-to-cart),
      form.rpress_form a,
      .custom-reset-password a,
      .user-dashboard-wrapper a,
      #rpress_user_history.rpress-order-history a:not(.rpress-view-order-btn):not(.rpress-reorder-btn),
      .rp-thankyou-page a {
        color: var(--rpress-theme-link) !important;
      }

      .rpress-wrap label,
      .rpress-wrap .rpress-label,
      .rpress-checkout #rpress_checkout_wrap label,
      .rpress-checkout #rpress_checkout_wrap .rpress-label,
      .rpress-checkout #rpress_checkout_wrap .delivery-time-text,
      .rpress-checkout #rpress_checkout_wrap .pickup-time-text,
      form.rpress_form p label,
      .custom-reset-password form label,
      .user-dashboard-wrapper .form-label,
      .user-dashboard-wrapper .box-title-description,
      .user-dashboard-wrapper table#user-orders tbody td::before,
      #rpress_user_history.rpress-order-history .rpress-history-card-label,
      #rpress_user_history.rpress-order-history .rpress-history-meta-item span,
      #rpress_user_history.rpress-order-history .rpress-history-items span,
      #rpress_user_history.rpress-order-history .rpress-history-total span,
      .rp-thankyou-meta-item span,
      .rp-thankyou-list span,
      #rpressModal.show-order-details.rpress-order-details-context .rp-detils-content-view .rp-detail-label {
        color: var(--rpress-theme-label) !important;
      }

      body.rpress-checkout #rpress_checkout_wrap,
      .user-dashboard-wrapper {
        background: linear-gradient(180deg, var(--rpress-theme-section-bg-soft) 0%, #ffffff 100%) !important;
      }

      body.rpress-checkout #rpress_checkout_wrap .rp-checkout-service-option,
      body.rpress-checkout #rpress_checkout_wrap #rpress_discount_code,
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_rewards,
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_login_register,
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_user_info,
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_order_details,
      body.rpress-checkout #rpress_checkout_wrap #rpress_payment_mode_select_wrap,
      body.rpress-checkout #rpress_checkout_wrap #rpress_payment_icons,
      body.rpress-checkout #rpress_checkout_wrap #rpress_cc_fields,
      body.rpress-checkout #rpress_checkout_wrap #rpress_cc_address,
      body.rpress-checkout #rpress_checkout_wrap #rpress_purchase_submit,
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_cart_wrap table,
      #rpress_user_history.rpress-order-history .rpress-history-card,
      .user-dashboard-wrapper .box-bg,
      .user-dashboard-wrapper .light-gray-bg,
      .user-dashboard-wrapper .address-wrap,
      .user-dashboard-wrapper table#user-orders,
      .user-dashboard-wrapper table#user-orders tbody tr,
      #rpressModal.show-order-details.rpress-order-details-context .modal__container,
      #rpressModal.show-order-details.rpress-order-details-context .rp-order-section-md-data,
      #rpressModal.show-order-details.rpress-order-details-context .rp-order-list-main-wrap ul.rpress-cart {
        background: linear-gradient(180deg, #ffffff 0%, var(--rpress-theme-section-bg-soft) 100%) !important;
        border-color: var(--rpress-theme-border-soft) !important;
      }

      .rp-thankyou-hero {
        background: linear-gradient(140deg, #ffffff 0%, var(--rpress-theme-section-bg) 48%, #ffffff 100%) !important;
        border-color: var(--rpress-theme-border-soft) !important;
      }

      .rp-thankyou-hero::before {
        background: radial-gradient(circle at center, rgba(<?php echo esc_attr($rgba); ?>, 0.2), rgba(<?php echo esc_attr($rgba); ?>, 0)) !important;
      }

      .rp-thankyou-check {
        background: linear-gradient(135deg, var(--rpress-theme-primary), var(--rpress-theme-primary-light)) !important;
        box-shadow: 0 10px 20px rgba(<?php echo esc_attr($rgba); ?>, 0.28) !important;
      }

      .rp-thankyou-live-status,
      .rp-thankyou-meta-item,
      .rp-thankyou-card,
      .rp-thankyou-next-item,
      .rp-thankyou-table-wrap {
        background: rgba(255, 255, 255, 0.92) !important;
        border-color: var(--rpress-theme-border-soft) !important;
      }

      .rp-thankyou-page table#rp-order-summary thead th,
      .rp-thankyou-page table#rp-order-summary tfoot td,
      #rpress_user_history.rpress-order-history .rpress-order-history-count,
      #rpress_user_history.rpress-order-history .rpress-history-meta-item,
      .user-dashboard-wrapper table#user-orders thead th,
      .user-dashboard-wrapper .viewbg,
      .user-dashboard-wrapper div#user-orders_filter input[type="search"],
      .user-dashboard-wrapper .rp-order-dropdown,
      #rpressModal.show-order-details.rpress-order-details-context .modal__close {
        background: var(--rpress-theme-section-bg) !important;
        border-color: var(--rpress-theme-border-soft) !important;
      }

      .user-dashboard-wrapper ul.sidebar-menu li.active,
      .user-dashboard-wrapper ul.sidebar-menu li:hover,
      body.rpress-checkout #rpress_checkout_wrap #rpress_payment_mode_select .rpress-gateway-option-selected,
      body.rpress-checkout #rpress_checkout_wrap .rp-checkout-service-option #rpressdeliveryTab .single-service-selected.active,
      body.rpress-checkout #rpress_checkout_wrap .rp-checkout-service-option #rpressdeliveryTab .single-service-selected[aria-selected="true"],
      .rpress-section ul.rpress-category-lists .rpress-category-item.current,
      .rpress-section ul.rpress-category-lists .rpress-category-item:hover,
      .rpress-section .cd-dropdown-content li a.mnuactive {
        background: var(--rpress-theme-section-bg-strong) !important;
        border-color: var(--rpress-theme-primary) !important;
        color: var(--rpress-theme-primary) !important;
      }

      .user-dashboard-wrapper ul.sidebar-menu li.active {
        border-left-color: var(--rpress-theme-primary) !important;
      }

      .user-dashboard-wrapper ul.sidebar-menu li.active span,
      .user-dashboard-wrapper ul.sidebar-menu li:hover span,
      .user-dashboard-wrapper .address-wrap.default .type-of-address,
      .user-dashboard-wrapper .rp-order-dropdown .dropdown__items li a:hover,
      .user-dashboard-wrapper form.rpress_form .rpress-login-remember a,
      .user-dashboard-wrapper form.rpress_form p.register-link-wrap .reglink,
      .rpress-section .rpress-categories-menu ul li a:hover,
      .rpress-section .rpress-categories-menu ul li a.active,
      .rpress-section .rpress-price-holder span.price,
      .rpress-section .special-inst span,
      .rpress-section .special-margin span,
      .rpress-section .delivery-change,
      .rpress-section .rpress-clear-cart,
      .rpress-section .cart-action-wrap a,
      .rpress-section .rpress-show-terms a {
        color: var(--rpress-theme-primary) !important;
      }

      .user-dashboard-wrapper .rp-order-dropdown .dropdown__items li a:hover svg path {
        fill: var(--rpress-theme-primary) !important;
      }

      .user-dashboard-wrapper .input-wrap .form-control:focus,
      .user-dashboard-wrapper input.search__input:focus,
      .user-dashboard-wrapper div#user-orders_filter input[type="search"]:focus,
      form.rpress_form p input:focus,
      .custom-reset-password form input:focus,
      body.rpress-checkout #rpress_checkout_wrap .rpress-input:focus,
      body.rpress-checkout #rpress_checkout_wrap select.rpress-select:focus,
      body.rpress-checkout #rpress_checkout_wrap select.rp-form-control:focus,
      body.rpress-checkout #rpress_checkout_wrap select.rpress-hrs:focus,
      body.rpress-checkout #rpress_checkout_wrap textarea.rpress-input:focus {
        border-color: var(--rpress-theme-primary) !important;
        box-shadow: 0 0 0 3px var(--rpress-theme-focus) !important;
      }

      .user-dashboard-wrapper .input-wrap .form-control:focus~.form-label {
        color: var(--rpress-theme-primary) !important;
      }

      .user-dashboard-wrapper.user-profile .box-body button.btn.btn-primary,
      .user-dashboard-wrapper .save-address,
      .user-dashboard-wrapper form.profile-form-wrap input[type="submit"],
      .user-dashboard-wrapper .address-wrap.default button.btn.btn-primary,
      .user-dashboard-wrapper .address-wrap button.btn.btn-primary:hover,
      form.rpress_form p input[type="submit"],
      .custom-reset-password form input[type="submit"],
      body.rpress-checkout #rpress_checkout_wrap #rpress_purchase_submit #rpress-purchase-button,
      body.rpress-checkout #rpress_checkout_wrap #rpress_purchase_submit input[type="submit"],
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_rewards .rpress-apply-discount.rpress-submit,
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_rewards .rpress-apply-redemp.rpress-submit,
      body.rpress-checkout #rpress_checkout_wrap a.btn.btn-primary.btn-block.rpress-delivery-opt-update,
      #rpress_user_history.rpress-order-history .rpress-history-actions a,
      .rp-live-status-toast {
        background: var(--rpress-theme-primary) !important;
        border-color: var(--rpress-theme-primary) !important;
        color: var(--rpress-theme-primary-contrast) !important;
      }

      .user-dashboard-wrapper .address-wrap button.btn.btn-primary,
      .user-dashboard-wrapper button.btn.btn-primary.add-new-address-btn,
      .rp-thankyou-notify-btn,
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_form_wrap .rpress-cart-adjustment .rpress-apply-discount.rpress-submit {
        background: transparent !important;
        border-color: var(--rpress-theme-primary) !important;
        color: var(--rpress-theme-primary) !important;
      }

      .rp-thankyou-notify-btn:hover,
      .rp-thankyou-notify-btn:focus,
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_form_wrap .rpress-cart-adjustment .rpress-apply-discount.rpress-submit:hover {
        background: var(--rpress-theme-primary) !important;
        color: var(--rpress-theme-primary-contrast) !important;
      }

      #rpress_checkout_wrap #rpress_discount_code_wrap .rpress-discount-code-field-wrap {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        width: 100% !important;
        max-width: 100% !important;
      }

      #rpress_checkout_wrap #rpress_discount_code_wrap input#rpress-discount {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        width: 100% !important;
        margin: 0 !important;
      }

      #rpress_checkout_wrap #rpress_discount_code_wrap .rpress-apply-discount.rpress-submit {
        flex: 0 0 auto !important;
        margin-left: 0 !important;
      }

      @media only screen and (max-width: 480px) {
        #rpress_checkout_wrap #rpress_discount_code_wrap .rpress-discount-code-field-wrap {
          align-items: stretch !important;
          flex-direction: column !important;
        }

        #rpress_checkout_wrap #rpress_discount_code_wrap .rpress-apply-discount.rpress-submit:not(.th-plain) {
          width: 100% !important;
        }
      }

      .user-dashboard-wrapper .radio-custom:checked + .radio-custom-label,
      .user-dashboard-wrapper .radio-custom:checked + .radio-custom-label svg path {
        color: var(--rpress-theme-primary) !important;
        fill: var(--rpress-theme-primary) !important;
      }

      .user-dashboard-wrapper .radio-custom:checked + .radio-custom-label:before,
      .user-dashboard-wrapper .default-address-checkbox input[type=checkbox]:checked + label {
        background-color: var(--rpress-theme-primary) !important;
        border-color: var(--rpress-theme-primary) !important;
      }

      form.rpress_form .rpress-login-remember input[type=checkbox]:checked {
        accent-color: var(--rpress-theme-primary) !important;
      }

      /* Sync frontend button/select shapes with "Default Button Style". */
      body.rpress-checkout #rpress_checkout_wrap a.rpress-submit:not(.rpress-not-available),
      body.rpress-checkout #rpress_checkout_wrap button.rpress-submit:not(.rpress-not-available),
      body.rpress-checkout #rpress_checkout_wrap input[type="submit"].rpress-submit:not(.rpress-not-available),
      body.rpress-checkout #rpress_checkout_wrap input[type="button"].rpress-submit:not(.rpress-not-available),
      .rpress-section .rpress-tabs-wrapper.rpress-delivery-options,
      .rpress-section .rpress-delivery-options ul#rpressdeliveryTab.order-online-servicetabs,
      .rpress-section .rpress-delivery-options ul#rpressdeliveryTab.order-online-servicetabs > li.nav-item,
      .rpress-section .rpress-delivery-options ul#rpressdeliveryTab.order-online-servicetabs > li.nav-item > a.nav-link,
      .rpress-section .rpress_checkout a,
      body.rpress-checkout #rpress_checkout_wrap a.rpress-checkout-cart.rpress-submit,
      #rpress_checkout_wrap #rpress_purchase_submit .rpress-submit,
      #rpress_checkout_wrap .rpress-checkout-button-actions a.rpress-submit.button,
      #rpress_checkout_form_wrap .rpress-cart-adjustment .rpress-apply-discount.rpress-submit,
      #rpress_checkout_wrap .rpress-delivery-options ul#rpressdeliveryTab.nav,
      #rpress_checkout_wrap .rpress-delivery-options ul#rpressdeliveryTab.nav>li,
      #rpress_checkout_wrap .rpress-delivery-options ul#rpressdeliveryTab.nav>li.nav-item,
      #rpress_checkout_wrap .rpress-delivery-options ul#rpressdeliveryTab.nav>li>a,
      #rpress_checkout_wrap a.btn.btn-primary.btn-block.rpress-delivery-opt-update,
      #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-cancel-btn,
      #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-submit-btn,
      #rpressDateTime.rpress-edit-address-popup .rpress-delivery-options ul#rpressdeliveryTab,
      #rpressDateTime.rpress-edit-address-popup .rpress-delivery-options ul#rpressdeliveryTab>li.nav-item,
      #rpressDateTime.rpress-edit-address-popup .rpress-delivery-options ul#rpressdeliveryTab>li.nav-item>a.nav-link,
      #rpressModal.show-service-options .rpress-delivery-options ul#rpressdeliveryTab,
      #rpressModal.show-service-options .rpress-delivery-options ul#rpressdeliveryTab>li.nav-item,
      #rpressModal.show-service-options .rpress-delivery-options ul#rpressdeliveryTab>li.nav-item>a.nav-link,
      #rpressModal.show-service-options a.btn.btn-primary.btn-block.rpress-delivery-opt-update,
      #rpressModal .rpress-popup-actions .submit-fooditem-button,
      #rpress_purchase_form #rpress-purchase-button,
      #rpress_purchase_form #rpress-user-login-submit input,
      #rpress_login_submit,
      #rpress_register_form input[type="submit"].rpress-submit,
      #rpress_profile_editor_submit,
      .rpress-order-history a.rpress-view-order-btn,
      .rpress-order-history a.rpress-reorder-btn,
      #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn,
      #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart {
        border-radius: var(--rpress-default-control-radius) !important;
      }

      .rpress-section .rpress-delivery-options select,
      #rpress_checkout_wrap select,
      #rpressDateTime.rpress-edit-address-popup select,
      #rpressModal.show-service-options select {
        border-radius: var(--rpress-default-control-radius) !important;
      }

      /* Keep old UI modal labels aligned with normal modal wording/formatting. */
      body.rp-old-ui-ux-enabled #rpressModal.show-service-options .delivery-time-text,
      body.rp-old-ui-ux-enabled #rpressModal.show-service-options .pickup-time-text,
      body.rp-old-ui-ux-enabled #rpressDateTime.rpress-edit-address-popup .delivery-time-text,
      body.rp-old-ui-ux-enabled #rpressDateTime.rpress-edit-address-popup .pickup-time-text {
        text-transform: none;
        letter-spacing: normal;
      }

      .rp-loading:after {
        content: " ";
        display: block;
        width: 20px;
        height: 20px;
        line-height: 20px;
        margin: 2px auto;
        border-radius: 50%;
        border: 3px solid #fff;
        border-color: #fff transparent #fff transparent;
        animation: lds-dual-ring 1.2s linear infinite;
        position: absolute;
        left: 0;
        right: 0;
        top: 4px;
        bottom: 0;
      }

      .rpress-add-to-cart.rp-loading:after {
        content: " ";
        display: block;
        width: 20px;
        height: 20px;
        line-height: 20px;
        margin: 2px auto;
        border-radius: 50%;
        border: 3px solid
          <?php echo sanitize_hex_color($add_button_bg_color) ?>
        ;
        border-color:
          <?php echo sanitize_hex_color($add_button_bg_color) ?>
          transparent
          <?php echo sanitize_hex_color($add_button_bg_color) ?>
          transparent;
        animation: lds-dual-ring 1.2s linear infinite;
        position: absolute;
        left: 0;
        right: 0;
        top: 4px;
        bottom: 0;
      }

      .rpress_purchase_submit_wrapper a.rpress-add-to-cart.rpress-submit {
        color: #fff;
        background:
          <?php echo sanitize_hex_color($add_button_bg_color) ?>
        ;
        border: 1px solid
          <?php echo sanitize_hex_color($add_button_bg_color) ?>
        ;
        padding: 4px 16px 3px;
        width: auto;
        height: 32px;
        margin-top: 0px;
        border-radius: 18px;
        text-transform: uppercase;
        text-decoration: none;
      }

      .rpress_purchase_submit_wrapper a.rpress-add-to-cart.rpress-submit .rpress-add-to-cart-label {
        color:
          <?php echo sanitize_hex_color($add_button_text_color) ?>
        ;
      }

      .rpress_purchase_submit_wrapper a.rpress-add-to-cart.rpress-submit span.add-icon svg {
        fill:
          <?php echo sanitize_hex_color($add_button_text_color) ?>
        ;
      }

      .rpress-categories-menu ul li a:hover,
      .rpress-categories-menu ul li a.active,
      .rpress-price-holder span.price,
      #rpressModal .qtyplus-wrap input[type="button"],
      #rpressModal .qtyminus-wrap input[type="button"],
      #rpressModal div.rpress-popup-actions .btn-count input[type="button"],
      #rpressModal .qtyplus-wrap input[type="button"]:hover,
      #rpressModal .qtyminus-wrap input[type="button"]:hover,
      #rpressModal .qtyplus-wrap input[type="button"]:focus,
      #rpressModal .qtyminus-wrap input[type="button"]:focus,
      #rpressModal div.rpress-popup-actions .btn-count input[type="button"]:hover,
      #rpressModal div.rpress-popup-actions .btn-count input[type="button"]:focus,
      .rpress_purchase_submit_wrapper a.rpress-add-to-cart.rpress-submit:hover .rpress-add-to-cart-label {
        color:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
      }

      div.rpress-search-wrap input#rpress-food-search,
      .rpress_fooditem_tags span.fooditem_tag,
      #rpressModal .qtyplus-wrap input[type="button"],
      #rpressModal .qtyminus-wrap input[type="button"],
      #rpressModal div.rpress-popup-actions .btn-count input[type="button"],
      #rpressModal .qtyplus-wrap input[type="button"]:hover,
      #rpressModal .qtyminus-wrap input[type="button"]:hover,
      #rpressModal .qtyplus-wrap input[type="button"]:focus,
      #rpressModal .qtyminus-wrap input[type="button"]:focus,
      #rpressModal div.rpress-popup-actions .btn-count input[type="button"]:hover,
      #rpressModal div.rpress-popup-actions .btn-count input[type="button"]:focus {
        border-color:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
      }

      ul.rpress-category-lists .rpress-category-item.current {
        border-left: 4px solid
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
      }

      .button.rpress-submit,
      #rpressModal.show-service-options .btn.btn-block.btn-primary,
      #rpressDateTime.rpress-edit-address-popup .btn.btn-block.btn-primary,
      .rpress-mobile-cart-icons .rp-cart-right-wrap,
      .button.rpress-status,
      #rpress_login_submit,
      #rpress_register_form input[type="submit"].rpress-submit,
      #rpress_profile_editor_submit,
      .rpress-order-history a.rpress-view-order-btn,
      .rpress-order-history a.rpress-reorder-btn,
      #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn,
      #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart {
        background:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
        color: #fff;
        border: 1px solid
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
      }

      #rpressModal .rpress-popup-actions .submit-fooditem-button {
        background:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
        border: 1px solid
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
        color: #fff;
      }

      .cart_item.rpress_checkout a {
        background:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
        border: 1px solid
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
      }

      .rpress_purchase_submit_wrapper a.rpress-add-to-cart.rpress-submit:hover span.add-icon svg path,
      div.rpress_fooditems_grid .rpress_fooditem.rpress-list .rpress_purchase_submit_wrapper a.rpress-add-to-cart.rpress-submit:hover span.add-icon svg {
        fill:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
      }

      .button.rpress-submit:active,
      .button.rpress-submit:focus,
      .button.rpress-submit:hover,
      #rpressModal.show-service-options .btn.btn-block.btn-primary:hover,
      #rpressDateTime.rpress-edit-address-popup .btn.btn-block.btn-primary:hover,
      .cart_item.rpress_checkout a:hover,
      #rpressModal .rpress-popup-actions .submit-fooditem-button:hover,
      #rpress_login_submit:hover,
      #rpress_register_form input[type="submit"].rpress-submit:hover,
      #rpress_profile_editor_submit:hover,
      .rpress-order-history a.rpress-view-order-btn:hover,
      .rpress-order-history a.rpress-reorder-btn:hover,
      #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn:hover,
      #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart:hover
      {
      border: 1px solid
        <?php echo sanitize_hex_color($primary_color); ?>
      ;
      }

      .rpress-section .delivery-change,
      .rpress-section .special-inst span,
      .rpress-section .special-margin span,
      .rpress-clear-cart,
      .rpress-section .cart-action-wrap a,
      .rpress-sidebar-cart-wrap .cart-action-wrap a,
      #rpress_checkout_wrap .cart-action-wrap a,
      #rpressModal .cart-action-wrap a,
      .rpress_fooditems_list h5.rpress-cat,
      ul.rpress-cart span.cart-total,
      .rpress-show-terms a,
      .rpress-view-order-btn {
        color:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
      }

      .rpress-clear-cart:hover,
      .rpress-section .delivery-change:hover,
      .rpress-section .cart-action-wrap a:hover,
      .rpress-sidebar-cart-wrap .cart-action-wrap a:hover,
      #rpress_checkout_wrap .cart-action-wrap a:hover,
      #rpressModal .cart-action-wrap a:hover,
      a.rpress_cart_remove_item_btn:hover,
      .rpress-show-terms a:hover {
        color:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
        opacity: 0.8;
      }

      .nav#rpressdeliveryTab>li>a {
        text-decoration: none;
        color:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
      }

      .nav#rpressdeliveryTab>li>a:hover,
      .nav#rpressdeliveryTab>li>a:focus {
        background-color: #eee;
      }

      .nav#rpressdeliveryTab>li.active>a,
      .nav#rpressdeliveryTab>li.active>a:hover,
      .nav#rpressdeliveryTab>li.active>a:focus,
      .rpress-sidebar-cart-wrap .close-cart-ic,
      .rpress-mobile-cart-icons .close-cart-ic,
      #rpress_checkout_wrap .nav#rpressdeliveryTab>li.active>a,
      #rpress_checkout_wrap .nav#rpressdeliveryTab>li.active>a:hover,
      #rpress_checkout_wrap .nav#rpressdeliveryTab>li.active>a:focus,
      [type=submit].rpress-submit {
        background-color:
          <?php echo sanitize_hex_color($primary_color); ?>
        ;
        color: #fff;
      }

      .rpress-clear-cart.rp-loading:after,
      .rpress-section .delivery-wrap .rp-loading:after,
      #rpress_checkout_wrap .delivery-wrap .rp-loading:after,
      #rpressModal .delivery-wrap .rp-loading:after,
      .rpress_checkout.rp-loading:after,
      .rpress-edit-from-cart.rp-loading:after,
      a.rpress-view-order-btn.rp-loading:after,
      #rpress_purchase_submit .rp-loading:after,
      #rpress-user-login-submit .rp-loading:after,
      #rpress-new-account-wrap a.rpress_checkout_register_login.rp-loading:after,
      #rpress-login-account-wrap a.rpress_checkout_register_login.rp-loading:after,
      body.rpress-dinein-menuitem a.rpress-add-to-cart.button.rpress-submit.rp-loading:after {
        border-color:
          <?php echo sanitize_hex_color($primary_color); ?>
          transparent
          <?php echo sanitize_hex_color($primary_color); ?>
          transparent;
      }

      body a.rpress-add-to-cart.button.rpress-submit.rp-loading:after {
        border-top-color:
          <?php echo sanitize_hex_color($primary_color) ?>
        ;
        border-bottom-color:
          <?php echo sanitize_hex_color($primary_color) ?>
        ;
      }

      #rpress_purchase_submit.rp-submit-loading {
        position: relative;
      }

      #rpress_purchase_submit .rp-purchase-loading {
        position: absolute;
        left: 50%;
        top: 50%;
        width: 24px;
        height: 24px;
        margin: -12px 0 0 -12px;
        pointer-events: none;
        z-index: 2;
      }

      #rpress_purchase_submit .rp-purchase-loading:after {
        border-color: #fff transparent #fff transparent;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        margin: 0 auto;
      }

      #rpress_purchase_submit .rp-purchase-button-loading {
        color: transparent !important;
        text-shadow: none !important;
      }

      .rpress-order-history a.rpress-view-order-btn,
      .rpress-order-history a.rpress-reorder-btn,
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn,
      body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart {
        background:
          <?php echo sanitize_hex_color($primary_color); ?>
          !important;
        border-color:
          <?php echo sanitize_hex_color($primary_color); ?>
          !important;
        color: #fff !important;
      }

      .rpress-cart .cart-action-wrap a.rpress-remove-from-cart,
      ul.rpress-category-lists .rpress-category-item:hover {
        background-color: rgba(<?php echo esc_attr($rgba); ?>, 0.1);
      }

      .rpress-edit-address-popup button.rpress-editaddress-submit-btn {
        background:
          <?php echo sanitize_hex_color($primary_color) ?>
        ;
        border: 1px solid
          <?php echo sanitize_hex_color($primary_color) ?>
        ;
      }

      .rpress-section .cd-dropdown-wrapper .cd-dropdown-content li a.mnuactive {
        color: #000;
        background-color: rgba(<?php echo esc_attr($rgba); ?>, 0.1);
        border-left: 4px solid
          <?php echo sanitize_hex_color($primary_color) ?>
        ;
        border-bottom: 0;
        border-right: 0;
      }

      .rpress_purchase_submit_wrapper a.rpress-add-to-cart.rpress-submit:hover {
        color:
          <?php echo sanitize_hex_color($primary_color) ?>
        ;
        background: #fff;
        border: 1px solid
          <?php echo sanitize_hex_color($primary_color) ?>
        ;
      }

      .rpress_purchase_submit_wrapper a.rpress-add-to-cart.rpress-submit.plain:hover {
        color:
          <?php echo sanitize_hex_color($primary_color) ?>
        ;
      }

      .rpress-section .pn-ProductNav_Indicator {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        width: 100px;
        background-color:
          <?php echo sanitize_hex_color($primary_color) ?>
          !important;
        transform-origin: 0 0;
        transition: transform 0.2s ease-in-out, background-color 0.2s ease-in-out;
      }

      .rpress-section .pn-ProductNav_Link[aria-selected=true],
      .rpress-section .pn-ProductNav_Link:hover {
        color:
          <?php echo sanitize_hex_color($primary_color) ?>
        ;
        outline: 0;
      }

       .rpress-section .container-actionmenu #actionburger {
        background-color:
          <?php echo sanitize_hex_color($primary_color) ?>
       }

      <?php if ('th-plain' === $button_style) : ?>
        .rpress-submit:not(.rpress-not-available),
        a.rpress-submit:not(.rpress-not-available),
        button.rpress-submit:not(.rpress-not-available),
        input[type="submit"].rpress-submit:not(.rpress-not-available),
        input[type="button"].rpress-submit:not(.rpress-not-available),
        body.rpress-checkout #rpress_checkout_wrap a.rpress-submit:not(.rpress-not-available),
        body.rpress-checkout #rpress_checkout_wrap button.rpress-submit:not(.rpress-not-available),
        body.rpress-checkout #rpress_checkout_wrap input[type="submit"].rpress-submit:not(.rpress-not-available),
        body.rpress-checkout #rpress_checkout_wrap input[type="button"].rpress-submit:not(.rpress-not-available),
        .button.rpress-submit:not(.rpress-not-available),
        [type=submit].rpress-submit:not(.rpress-not-available),
        #rpressModal.show-service-options .btn.btn-block.btn-primary,
        #rpressDateTime.rpress-edit-address-popup .btn.btn-block.btn-primary,
        .rpress-section .rpress_checkout a,
        .rpress-section .cart-action-wrap a,
        body.rpress-checkout #rpress_checkout_wrap a.rpress-checkout-cart.rpress-submit,
        body.rpress-checkout #rpress_checkout_wrap #rpress_purchase_submit #rpress-purchase-button,
        body.rpress-checkout #rpress_checkout_wrap #rpress_purchase_submit input[type="submit"].rpress-submit,
        #rpress_checkout_wrap #rpress_purchase_submit .rpress-submit,
        #rpress_checkout_wrap .rpress-checkout-button-actions a.rpress-submit.button,
        #rpress_checkout_form_wrap .rpress-cart-adjustment .rpress-apply-discount.rpress-submit,
        #rpress_checkout_wrap a.btn.btn-primary.btn-block.rpress-delivery-opt-update,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-cancel-btn,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-submit-btn,
        #rpressModal.show-service-options a.btn.btn-primary.btn-block.rpress-delivery-opt-update,
        #rpressModal .rpress-popup-actions .submit-fooditem-button,
        #rpress_purchase_form #rpress-purchase-button,
        #rpress_purchase_form #rpress-user-login-submit input,
        #rpress_login_submit,
        #rpress_register_form input[type="submit"].rpress-submit,
        #rpress_profile_editor_submit,
        .rpress-order-history a.rpress-view-order-btn,
        .rpress-order-history a.rpress-reorder-btn,
        body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn,
        body.rpress-checkout #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart {
          background: transparent !important;
          background-color: transparent !important;
          border: 0 !important;
          border-radius: 0 !important;
          box-shadow: none !important;
          color:
            <?php echo sanitize_hex_color($primary_color); ?>
            !important;
          height: auto !important;
          line-height: inherit !important;
          min-height: 0 !important;
          min-width: 0 !important;
          padding: 0 !important;
          text-decoration: underline !important;
          text-underline-offset: 2px !important;
          text-transform: none !important;
          width: auto !important;
        }

        body.rpress-checkout #rpress_checkout_form_wrap #rpress-discount-code-wrap .rpress-apply-discount.rpress-submit {
          display: inline-flex !important;
          align-items: center !important;
          justify-content: center !important;
          min-width: 92px !important;
          min-height: 44px !important;
          padding: 0 8px !important;
          font-size: 16px !important;
          line-height: 1.2 !important;
        }

        #rpressModal .rpress-popup-actions .rpress-popup-submit-wrap {
          display: flex !important;
          align-items: center !important;
          justify-content: flex-end !important;
        }

        #rpressModal .rpress-popup-actions .submit-fooditem-button {
          display: inline-flex !important;
          align-items: center !important;
          justify-content: center !important;
          gap: 6px !important;
          width: auto !important;
          max-width: 100% !important;
          margin-left: 0 !important;
          padding: 0 !important;
          min-height: 0 !important;
          line-height: 1.35 !important;
          letter-spacing: 0 !important;
          text-align: center !important;
          text-transform: none !important;
          white-space: nowrap !important;
        }

        #rpressModal .rpress-popup-actions .submit-fooditem-button span.cart-item-price {
          position: static !important;
          right: auto !important;
          margin-left: 4px !important;
          font-size: inherit !important;
          line-height: inherit !important;
          white-space: nowrap !important;
        }

        .rpress-submit:not(.rpress-not-available):hover,
        .rpress-submit:not(.rpress-not-available):focus,
        a.rpress-submit:not(.rpress-not-available):hover,
        a.rpress-submit:not(.rpress-not-available):focus,
        button.rpress-submit:not(.rpress-not-available):hover,
        button.rpress-submit:not(.rpress-not-available):focus,
        input[type="submit"].rpress-submit:not(.rpress-not-available):hover,
        input[type="submit"].rpress-submit:not(.rpress-not-available):focus,
        input[type="button"].rpress-submit:not(.rpress-not-available):hover,
        input[type="button"].rpress-submit:not(.rpress-not-available):focus,
        body.rpress-checkout #rpress_checkout_wrap a.rpress-submit:not(.rpress-not-available):hover,
        body.rpress-checkout #rpress_checkout_wrap a.rpress-submit:not(.rpress-not-available):focus,
        body.rpress-checkout #rpress_checkout_wrap button.rpress-submit:not(.rpress-not-available):hover,
        body.rpress-checkout #rpress_checkout_wrap button.rpress-submit:not(.rpress-not-available):focus,
        body.rpress-checkout #rpress_checkout_wrap input[type="submit"].rpress-submit:not(.rpress-not-available):hover,
        body.rpress-checkout #rpress_checkout_wrap input[type="submit"].rpress-submit:not(.rpress-not-available):focus,
        body.rpress-checkout #rpress_checkout_wrap input[type="button"].rpress-submit:not(.rpress-not-available):hover,
        body.rpress-checkout #rpress_checkout_wrap input[type="button"].rpress-submit:not(.rpress-not-available):focus,
        .button.rpress-submit:not(.rpress-not-available):hover,
        .button.rpress-submit:not(.rpress-not-available):focus,
        [type=submit].rpress-submit:not(.rpress-not-available):hover,
        [type=submit].rpress-submit:not(.rpress-not-available):focus,
        #rpressModal.show-service-options .btn.btn-block.btn-primary:hover,
        #rpressModal.show-service-options .btn.btn-block.btn-primary:focus,
        #rpressDateTime.rpress-edit-address-popup .btn.btn-block.btn-primary:hover,
        #rpressDateTime.rpress-edit-address-popup .btn.btn-block.btn-primary:focus,
        .rpress-section .rpress_checkout a:hover,
        .rpress-section .rpress_checkout a:focus,
        body.rpress-checkout #rpress_checkout_wrap a.rpress-checkout-cart.rpress-submit:hover,
        body.rpress-checkout #rpress_checkout_wrap a.rpress-checkout-cart.rpress-submit:focus,
        #rpress_checkout_wrap #rpress_purchase_submit .rpress-submit:hover,
        #rpress_checkout_wrap #rpress_purchase_submit .rpress-submit:focus,
        #rpress_checkout_wrap .rpress-checkout-button-actions a.rpress-submit.button:hover,
        #rpress_checkout_wrap .rpress-checkout-button-actions a.rpress-submit.button:focus,
        #rpress_checkout_form_wrap .rpress-cart-adjustment .rpress-apply-discount.rpress-submit:hover,
        #rpress_checkout_form_wrap .rpress-cart-adjustment .rpress-apply-discount.rpress-submit:focus,
        #rpress_checkout_wrap a.btn.btn-primary.btn-block.rpress-delivery-opt-update:hover,
        #rpress_checkout_wrap a.btn.btn-primary.btn-block.rpress-delivery-opt-update:focus,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-cancel-btn:hover,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-cancel-btn:focus,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-submit-btn:hover,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-submit-btn:focus,
        #rpressModal.show-service-options a.btn.btn-primary.btn-block.rpress-delivery-opt-update:hover,
        #rpressModal.show-service-options a.btn.btn-primary.btn-block.rpress-delivery-opt-update:focus,
        #rpressModal .rpress-popup-actions .submit-fooditem-button:hover,
        #rpressModal .rpress-popup-actions .submit-fooditem-button:focus,
        #rpress_purchase_form #rpress-purchase-button:hover,
        #rpress_purchase_form #rpress-purchase-button:focus,
        #rpress_purchase_form #rpress-user-login-submit input:hover,
        #rpress_purchase_form #rpress-user-login-submit input:focus,
        #rpress_login_submit:hover,
        #rpress_login_submit:focus,
        #rpress_register_form input[type="submit"].rpress-submit:hover,
        #rpress_register_form input[type="submit"].rpress-submit:focus,
        #rpress_profile_editor_submit:hover,
        #rpress_profile_editor_submit:focus,
        .rpress-order-history a.rpress-view-order-btn:hover,
        .rpress-order-history a.rpress-view-order-btn:focus,
        .rpress-order-history a.rpress-reorder-btn:hover,
        .rpress-order-history a.rpress-reorder-btn:focus,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn:hover,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn:focus,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart:hover,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart:focus {
          background: transparent !important;
          background-color: transparent !important;
          border-color: transparent !important;
          box-shadow: none !important;
          color:
            <?php echo sanitize_hex_color($theme_dark); ?>
            !important;
          text-decoration: underline !important;
        }

        .rpress-submit.rp-loading:after,
        a.rpress-submit.rp-loading:after,
        button.rpress-submit.rp-loading:after,
        input[type="submit"].rpress-submit.rp-loading:after,
        input[type="button"].rpress-submit.rp-loading:after,
        #rpressModal .submit-fooditem-button.rp-loading:after,
        #rpressModal.show-service-options .btn.rp-loading:after,
        #rpressDateTime.rpress-edit-address-popup .btn.rp-loading:after,
        .rpress_checkout.rp-loading:after,
        .rpress-edit-from-cart.rp-loading:after,
        .rpress-clear-cart.rp-loading:after,
        .rpress-cart-adjustment .rp-loading:after,
        .rpress-discount-code-field-wrap .rp-loading:after,
        a.rpress-view-order-btn.rp-loading:after,
        a.rpress-reorder-btn.rp-loading:after,
        #rpress_purchase_submit .rp-loading:after,
        #rpress_purchase_submit .rp-purchase-loading:after {
          border-color:
            <?php echo sanitize_hex_color($primary_color); ?>
            transparent
            <?php echo sanitize_hex_color($primary_color); ?>
            transparent;
        }
      <?php else : ?>
        .rpress-submit:not(.rpress-not-available),
        a.rpress-submit:not(.rpress-not-available),
        button.rpress-submit:not(.rpress-not-available),
        input[type="submit"].rpress-submit:not(.rpress-not-available),
        input[type="button"].rpress-submit:not(.rpress-not-available),
        body.rpress-checkout #rpress_checkout_wrap a.rpress-submit:not(.rpress-not-available),
        body.rpress-checkout #rpress_checkout_wrap button.rpress-submit:not(.rpress-not-available),
        body.rpress-checkout #rpress_checkout_wrap input[type="submit"].rpress-submit:not(.rpress-not-available),
        body.rpress-checkout #rpress_checkout_wrap input[type="button"].rpress-submit:not(.rpress-not-available),
        .button.rpress-submit:not(.rpress-not-available),
        [type=submit].rpress-submit:not(.rpress-not-available),
        #rpressModal.show-service-options .btn.btn-block.btn-primary,
        #rpressDateTime.rpress-edit-address-popup .btn.btn-block.btn-primary,
        .rpress-section .rpress_checkout a,
        body.rpress-checkout #rpress_checkout_wrap a.rpress-checkout-cart.rpress-submit,
        #rpress_checkout_wrap #rpress_purchase_submit .rpress-submit,
        #rpress_checkout_wrap .rpress-checkout-button-actions a.rpress-submit.button,
        #rpress_checkout_form_wrap .rpress-cart-adjustment .rpress-apply-discount.rpress-submit,
        #rpress_checkout_wrap a.btn.btn-primary.btn-block.rpress-delivery-opt-update,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-cancel-btn,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-submit-btn,
        #rpressModal.show-service-options a.btn.btn-primary.btn-block.rpress-delivery-opt-update,
        #rpressModal .rpress-popup-actions .submit-fooditem-button,
        #rpress_purchase_form #rpress-purchase-button,
        #rpress_purchase_form #rpress-user-login-submit input,
        #rpress_login_submit,
        #rpress_register_form input[type="submit"].rpress-submit,
        #rpress_profile_editor_submit,
        .rpress-order-history a.rpress-view-order-btn,
        .rpress-order-history a.rpress-reorder-btn,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart {
          color: #fff !important;
          text-decoration: none !important;
        }

        .rpress-submit:not(.rpress-not-available):hover,
        .rpress-submit:not(.rpress-not-available):focus,
        a.rpress-submit:not(.rpress-not-available):hover,
        a.rpress-submit:not(.rpress-not-available):focus,
        button.rpress-submit:not(.rpress-not-available):hover,
        button.rpress-submit:not(.rpress-not-available):focus,
        input[type="submit"].rpress-submit:not(.rpress-not-available):hover,
        input[type="submit"].rpress-submit:not(.rpress-not-available):focus,
        input[type="button"].rpress-submit:not(.rpress-not-available):hover,
        input[type="button"].rpress-submit:not(.rpress-not-available):focus,
        body.rpress-checkout #rpress_checkout_wrap a.rpress-submit:not(.rpress-not-available):hover,
        body.rpress-checkout #rpress_checkout_wrap a.rpress-submit:not(.rpress-not-available):focus,
        body.rpress-checkout #rpress_checkout_wrap button.rpress-submit:not(.rpress-not-available):hover,
        body.rpress-checkout #rpress_checkout_wrap button.rpress-submit:not(.rpress-not-available):focus,
        body.rpress-checkout #rpress_checkout_wrap input[type="submit"].rpress-submit:not(.rpress-not-available):hover,
        body.rpress-checkout #rpress_checkout_wrap input[type="submit"].rpress-submit:not(.rpress-not-available):focus,
        body.rpress-checkout #rpress_checkout_wrap input[type="button"].rpress-submit:not(.rpress-not-available):hover,
        body.rpress-checkout #rpress_checkout_wrap input[type="button"].rpress-submit:not(.rpress-not-available):focus,
        .button.rpress-submit:not(.rpress-not-available):hover,
        .button.rpress-submit:not(.rpress-not-available):focus,
        [type=submit].rpress-submit:not(.rpress-not-available):hover,
        [type=submit].rpress-submit:not(.rpress-not-available):focus,
        #rpressModal.show-service-options .btn.btn-block.btn-primary:hover,
        #rpressModal.show-service-options .btn.btn-block.btn-primary:focus,
        #rpressDateTime.rpress-edit-address-popup .btn.btn-block.btn-primary:hover,
        #rpressDateTime.rpress-edit-address-popup .btn.btn-block.btn-primary:focus,
        .rpress-section .rpress_checkout a:hover,
        .rpress-section .rpress_checkout a:focus,
        body.rpress-checkout #rpress_checkout_wrap a.rpress-checkout-cart.rpress-submit:hover,
        body.rpress-checkout #rpress_checkout_wrap a.rpress-checkout-cart.rpress-submit:focus,
        #rpress_checkout_wrap #rpress_purchase_submit .rpress-submit:hover,
        #rpress_checkout_wrap #rpress_purchase_submit .rpress-submit:focus,
        #rpress_checkout_wrap .rpress-checkout-button-actions a.rpress-submit.button:hover,
        #rpress_checkout_wrap .rpress-checkout-button-actions a.rpress-submit.button:focus,
        #rpress_checkout_form_wrap .rpress-cart-adjustment .rpress-apply-discount.rpress-submit:hover,
        #rpress_checkout_form_wrap .rpress-cart-adjustment .rpress-apply-discount.rpress-submit:focus,
        #rpress_checkout_wrap a.btn.btn-primary.btn-block.rpress-delivery-opt-update:hover,
        #rpress_checkout_wrap a.btn.btn-primary.btn-block.rpress-delivery-opt-update:focus,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-cancel-btn:hover,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-cancel-btn:focus,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-submit-btn:hover,
        #rpressDateTime.rpress-edit-address-popup .rpress-editaddress-submit-btn:focus,
        #rpressModal.show-service-options a.btn.btn-primary.btn-block.rpress-delivery-opt-update:hover,
        #rpressModal.show-service-options a.btn.btn-primary.btn-block.rpress-delivery-opt-update:focus,
        #rpressModal .rpress-popup-actions .submit-fooditem-button:hover,
        #rpressModal .rpress-popup-actions .submit-fooditem-button:focus,
        #rpress_purchase_form #rpress-purchase-button:hover,
        #rpress_purchase_form #rpress-purchase-button:focus,
        #rpress_purchase_form #rpress-user-login-submit input:hover,
        #rpress_purchase_form #rpress-user-login-submit input:focus,
        #rpress_login_submit:hover,
        #rpress_login_submit:focus,
        #rpress_register_form input[type="submit"].rpress-submit:hover,
        #rpress_register_form input[type="submit"].rpress-submit:focus,
        #rpress_profile_editor_submit:hover,
        #rpress_profile_editor_submit:focus,
        .rpress-order-history a.rpress-view-order-btn:hover,
        .rpress-order-history a.rpress-view-order-btn:focus,
        .rpress-order-history a.rpress-reorder-btn:hover,
        .rpress-order-history a.rpress-reorder-btn:focus,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn:hover,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress_cart_remove_item_btn:focus,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart:hover,
        #rpress_checkout_wrap #rpress_checkout_cart_wrap .rpress-checkout-item-actions .rpress-remove-from-cart:focus {
          background:
            <?php echo sanitize_hex_color($theme_dark); ?>
            !important;
          border-color:
            <?php echo sanitize_hex_color($theme_dark); ?>
            !important;
          color:
            <?php echo sanitize_hex_color($theme_contrast); ?>
            !important;
          text-decoration: none !important;
        }

        .rpress-submit.rp-loading:after,
        a.rpress-submit.rp-loading:after,
        button.rpress-submit.rp-loading:after,
        input[type="submit"].rpress-submit.rp-loading:after,
        input[type="button"].rpress-submit.rp-loading:after,
        #rpressModal .submit-fooditem-button.rp-loading:after,
        #rpressModal.show-service-options .btn.rp-loading:after,
        #rpressDateTime.rpress-edit-address-popup .btn.rp-loading:after,
        .rpress_checkout.rp-loading:after,
        .rpress-edit-from-cart.rp-loading:after,
        .rpress-clear-cart.rp-loading:after,
        .rpress-cart-adjustment .rp-loading:after,
        .rpress-discount-code-field-wrap .rp-loading:after,
        a.rpress-view-order-btn.rp-loading:after,
        a.rpress-reorder-btn.rp-loading:after,
        #rpress_purchase_submit .rp-loading:after,
        #rpress_purchase_submit .rp-purchase-loading:after {
          border-color: #fff transparent #fff transparent !important;
        }
      <?php endif; ?>

      @media only screen and (max-width: 768px) {
        #rpress_checkout_wrap #rpress_purchase_submit #rpress-purchase-button:not(.th-plain),
        #rpress_checkout_wrap #rpress_checkout_form_wrap .rpress-checkout-button-actions a.rpress-submit.button:not(.th-plain) {
          padding: 12px 16px;
        }
      }
    </style>
    <?php
  }
  /**
   * Get styles for the frontend.
   *
   * @return array
   */
  public static function get_styles()
  {
    $frontend_style_version = RP_VERSION;
    $frontend_style_path = trailingslashit(RP_PLUGIN_DIR) . 'assets/css/rpress.css';
    if (file_exists($frontend_style_path)) {
      $frontend_style_version = RP_VERSION . '.' . filemtime($frontend_style_path);
    }

    return apply_filters(
      'rpress_enqueue_styles',
      array(
        'font-awesome' => array(
          'src' => 'https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
          'deps' => '',
          'version' => RP_VERSION,
          'media' => 'all',
          'has_rtl' => false,
        ),
        'rpress-frontend-icons' => array(
          'src' => self::get_asset_url('assets/css/frontend-icons.css'),
          'deps' => '',
          'version' => RP_VERSION,
          'media' => 'all',
          'has_rtl' => false,
        ),
        'rp-fancybox' => array(
          'src' => self::get_asset_url('assets/css/jquery.fancybox.css'),
          'deps' => array(),
          'version' => RP_VERSION,
          'media' => 'all',
          'has_rtl' => false,
        ),
        'jquery-chosen' => array(
          'src' => self::get_asset_url('assets/css/chosen.css'),
          'deps' => array(),
          'version' => RP_VERSION,
          'media' => 'all',
          'has_rtl' => false,
        ),
        'rp-frontend-styles' => array(
          'src' => self::get_asset_url('assets/css/rpress.css'),
          'deps' => array(),
          'version' => $frontend_style_version,
          'media' => 'all',
          'has_rtl' => false,
        ),
      )
    );
  }
}
RP_Frontend_Scripts::init();
