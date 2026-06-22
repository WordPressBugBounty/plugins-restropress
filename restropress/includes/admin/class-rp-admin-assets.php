<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Load assets
 *
 * @package RestroPress/Admin
 * @since 2.5
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
if ( ! class_exists( 'RP_Admin_Assets', false ) ) :
  /**
   * RP_Admin_Assets Class.
   */
  class RP_Admin_Assets {
    /**
     * Admin UI design-system stylesheet handles in dependency order.
     *
     * @var array
     */
    protected $admin_ui_style_handles = array(
      'rpress-admin-ui-tokens',
      'rpress-admin-ui-base',
      'rpress-admin-ui-layout',
      'rpress-admin-ui-components',
      'rpress-admin-ui-utilities',
      'rpress-admin-ui-screens',
    );

    /**
     * Hook in tabs.
     */
    public function __construct() {
      add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'register_styles' ), 100 );
      add_action( 'admin_head', array( $this, 'admin_icons_buttons' ) );
      add_action( 'wp_ajax_selected_filter', array( $this, 'selected_filter' ) );
      add_action( 'wp_ajax_rpress_do_ajax_export', array($this, 'rpress_do_ajax_export' ) );
      add_action( 'wp_ajax_order_graph_filter', array( $this, 'order_graph_filter' ) );
      add_action( 'wp_ajax_revenue_graph_filter', array( $this, 'revenue_graph_filter' ) );
      add_action( 'wp_ajax_customers_data_filter', array( $this, 'customers_data_filter') );
    }

    /**
     * Convert a WordPress PHP date format into a jQuery UI datepicker format.
     *
     * @since 3.3
     *
     * @param string $format WordPress date format.
     * @return string
     */
    protected static function wp_date_format_to_jquery_ui( $format ) {
      $replacements = array(
        'd' => 'dd',
        'j' => 'd',
        'm' => 'mm',
        'n' => 'm',
        'F' => 'MM',
        'M' => 'M',
        'Y' => 'yy',
        'y' => 'y',
      );

      return strtr( $format, $replacements );
    }

    /**
     * Build a readable placeholder from the WordPress date format.
     *
     * @since 3.3
     *
     * @param string $format WordPress date format.
     * @return string
     */
    protected static function wp_date_format_placeholder( $format ) {
      $replacements = array(
        'd' => 'dd',
        'j' => 'd',
        'm' => 'mm',
        'n' => 'm',
        'F' => 'Month',
        'M' => 'Mon',
        'Y' => 'yyyy',
        'y' => 'yy',
      );

      return strtr( $format, $replacements );
    }

    /**
     * Determine whether the current admin screen belongs to RestroPress.
     *
     * @since 3.3
     *
     * @param string $screen_id Current screen ID.
     * @return bool
     */
    protected function is_rpress_admin_screen( $screen_id = '' ) {
      $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

      if ( '' !== $page && ( 0 === strpos( $page, 'rpress' ) || 'restropress' === $page ) ) {
        return true;
      }

      if ( in_array( $screen_id, array( 'fooditem', 'edit-fooditem' ), true ) ) {
        return true;
      }

      return (
        0 === strpos( $screen_id, 'restropress_page_' )
        || 0 === strpos( $screen_id, 'fooditem_page_' )
      );
    }

    /**
     * Register the reusable RestroPress admin UI system assets.
     *
     * @since 3.3
     *
     * @return void
     */
    protected function register_admin_ui_assets() {
      $css_dir = RP_PLUGIN_URL . 'assets/admin-ui/css/';
      $js_dir  = RP_PLUGIN_URL . 'assets/admin-ui/js/';
      $css_path = RP_PLUGIN_DIR . 'assets/admin-ui/css/';
      $js_path  = RP_PLUGIN_DIR . 'assets/admin-ui/js/';

      $styles = array(
        'rpress-admin-ui-tokens'     => array( 'file' => 'tokens.css', 'deps' => array() ),
        'rpress-admin-ui-base'       => array( 'file' => 'base.css', 'deps' => array( 'rpress-admin-ui-tokens' ) ),
        'rpress-admin-ui-layout'     => array( 'file' => 'layout.css', 'deps' => array( 'rpress-admin-ui-base' ) ),
        'rpress-admin-ui-components' => array( 'file' => 'components.css', 'deps' => array( 'rpress-admin-ui-layout' ) ),
        'rpress-admin-ui-utilities'  => array( 'file' => 'utilities.css', 'deps' => array( 'rpress-admin-ui-components' ) ),
        'rpress-admin-ui-screens'    => array( 'file' => 'screens.css', 'deps' => array( 'rpress-admin-ui-utilities' ) ),
      );

      foreach ( $styles as $handle => $style ) {
        $style_file = $css_path . $style['file'];
        $style_version = file_exists( $style_file ) ? filemtime( $style_file ) : RP_VERSION;

        wp_register_style( $handle, $css_dir . $style['file'], $style['deps'], $style_version );
      }

      $script_file = $js_path . 'admin-ui.js';
      $script_version = file_exists( $script_file ) ? filemtime( $script_file ) : RP_VERSION;

      wp_register_script( 'rpress-admin-ui', $js_dir . 'admin-ui.js', array( 'jquery', 'jquery-ui-datepicker' ), $script_version, true );
    }

    /**
     * Enqueue the reusable RestroPress admin UI system assets.
     *
     * @since 3.3
     *
     * @return void
     */
    protected function enqueue_admin_ui_assets() {
      $this->register_admin_ui_assets();

      foreach ( $this->admin_ui_style_handles as $handle ) {
        wp_enqueue_style( $handle );
      }

      wp_enqueue_script( 'rpress-admin-ui' );
      wp_localize_script(
        'rpress-admin-ui',
        'rpressAdminUI',
        array(
          'dateRange' => array(
            'format' => 'YYYY-MM-DD',
            'displayFormat' => self::wp_date_format_to_jquery_ui( get_option( 'date_format' ) ),
            'datePlaceholder' => self::wp_date_format_placeholder( get_option( 'date_format' ) ),
            'separator' => ' - ',
            'firstDay' => (int) get_option( 'start_of_week', 1 ),
            'startLabel' => esc_html__( 'Start', 'restropress' ),
            'endLabel' => esc_html__( 'End', 'restropress' ),
            'applyLabel' => esc_html__( 'Apply', 'restropress' ),
            'cancelLabel' => esc_html__( 'Cancel', 'restropress' ),
            'customRangeLabel' => esc_html__( 'Custom Range', 'restropress' ),
            'ranges' => array(
              'today'      => esc_html__( 'Today', 'restropress' ),
              'yesterday'  => esc_html__( 'Yesterday', 'restropress' ),
              'this_week'  => esc_html__( 'This Week', 'restropress' ),
              'last_7'     => esc_html__( 'Last 7 Days', 'restropress' ),
              'last_30'    => esc_html__( 'Last 30 Days', 'restropress' ),
              'this_month' => esc_html__( 'This Month', 'restropress' ),
              'last_month' => esc_html__( 'Last Month', 'restropress' ),
            ),
          ),
        )
      );
    }

    /**
     * Enqueue styles.
     */
    public function admin_styles() {
      global $wp_scripts;
      $screen    = get_current_screen();
      $screen_id = $screen ? $screen->id : '';
      $suffix       = '';
      $admin_css_file = RP_PLUGIN_DIR . 'assets/css/admin.css';
      $admin_css_version = file_exists( $admin_css_file ) ? filemtime( $admin_css_file ) : RP_VERSION;
      // Register admin styles.
      wp_register_style( 'rpress_admin_icon_styles', RP_PLUGIN_URL . '/assets/css/admin-icons.css', array(), RP_VERSION );
      wp_deregister_style( 'rpress_admin_styles' );
      wp_register_style( 'rpress_admin_styles', RP_PLUGIN_URL . 'assets/css/admin.css', array('select2'), $admin_css_version );
      wp_register_style( 'select2', RP_PLUGIN_URL . 'assets/css/select2.min.css', array(), RP_VERSION );
      wp_register_style( 'toast', RP_PLUGIN_URL . '/assets/css/jquery.toast.css', array(), RP_VERSION );
      wp_register_style( 'timepicker', RP_PLUGIN_URL . 'assets/css/jquery.timepicker.css', array(), RP_VERSION );
      wp_register_style( 'jquery-chosen', RP_PLUGIN_URL .'assets/css/chosen.min.css', array(), RP_VERSION );
      wp_register_style( 'backbone-modal', RP_PLUGIN_URL .'assets/css/rpress-backbone-modal.css', array(), RP_VERSION );
      $this->register_admin_ui_assets();
      $ui_style = ( 'classic' == get_user_option( 'admin_color' ) ) ? 'classic' : 'fresh';
      wp_register_style( 'jquery-ui-css', RP_PLUGIN_URL . 'assets/css/jquery-ui-'. $ui_style . '.min.css' );
      wp_enqueue_style( 'jquery-ui-css' );
      wp_enqueue_style( 'timepicker' );
      wp_enqueue_style( 'rpress_admin_styles' );
      wp_enqueue_style( 'jquery-chosen' );
      wp_enqueue_style( 'wp-color-picker' );
      wp_enqueue_style( 'toast' );
      wp_enqueue_style( 'thickbox' );
      wp_enqueue_style( 'backbone-modal' );
      // Sitewide Admin Icons.
      wp_enqueue_style( 'rpress_admin_icon_styles' );

      if ( $this->is_rpress_admin_screen( $screen_id ) ) {
        $this->enqueue_admin_ui_assets();
      }

      // The onboarding wizard AND the standalone Menu Items -> Import screen
      // share the same view + JS, so both load the home assets.
      $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
      if ( in_array( $current_page, array( 'rpress-setup', 'rpress-menu-import' ), true ) ) {
        $home_css_file = RP_PLUGIN_DIR . 'assets/css/admin-home.css';
        $home_js_file  = RP_PLUGIN_DIR . 'assets/js/admin-home.js';
        $home_css_version = file_exists( $home_css_file ) ? filemtime( $home_css_file ) : RP_VERSION;
        $home_js_version  = file_exists( $home_js_file ) ? filemtime( $home_js_file ) : RP_VERSION;
        wp_register_style( 'rpress-admin-home', RP_PLUGIN_URL . 'assets/css/admin-home.css', array(), $home_css_version );
        wp_enqueue_style( 'rpress-admin-home' );
        wp_enqueue_media(); // Per-item photo picker on the menu review screen.
        wp_register_script( 'rpress-home', RP_PLUGIN_URL . 'assets/js/admin-home.js', array( 'jquery' ), $home_js_version, true );
        wp_enqueue_script( 'rpress-home' );
        wp_localize_script(
          'rpress-home',
          'rpressOnboarding',
          array(
            'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'rpress_onboarding' ),
            'getStatesNonce'    => wp_create_nonce( 'rpress_get_states_nonce' ),
            'savedText'         => esc_html__( 'Saved. Moving to the next setup area.', 'restropress' ),
            'importedText'      => esc_html__( 'Menu parsed. Review every item before publishing.', 'restropress' ),
            'missingImportText' => esc_html__( 'Upload and review a menu before publishing.', 'restropress' ),
            'errorText'         => esc_html__( 'Something went wrong. Please try again.', 'restropress' ),
            'nextText'          => esc_html__( 'Save and continue', 'restropress' ),
            'finishText'        => esc_html__( 'Finish & go live', 'restropress' ),
            'launchedText'      => esc_html__( 'Setup complete', 'restropress' ),
            'publishedText'     => esc_html__( 'menu items published.', 'restropress' ),
            'csvReadyText'      => esc_html__( 'Ready to import — click “Upload & map columns”.', 'restropress' ),
          )
        );
      }
    }
    /**
     * Enqueue scripts.
     */
    public function admin_scripts() {
      global $wp_query, $post;
      $screen       = get_current_screen();
      $screen_id    = $screen ? $screen->id : '';
      $rp_screen_id = sanitize_title( esc_html__( 'RestroPress', 'restropress' ) );
      $suffix       = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
      $admin_deps   = array( 'jquery', 'jquery-tata-toast', 'timepicker', 'jquery-form',  'jquery-ui-tooltip' );
      $this->register_admin_ui_assets();
      if ( $this->is_rpress_admin_screen( $screen_id ) ) {
        $admin_deps[] = 'rpress-admin-ui';
      }
      wp_register_script( 'jquery-tiptip', RP_PLUGIN_URL . 'assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js', array( 'jquery' ), RP_VERSION, true );
      wp_register_script( 'select2', RP_PLUGIN_URL . 'assets/js/select2/select2' . $suffix . '.js', array( 'jquery' ), RP_VERSION, true );
      wp_register_script( 'jquery-blockui', RP_PLUGIN_URL . 'assets/js/jquery-blockui/jquery.blockUI' . $suffix . '.js', array( 'jquery' ), RP_VERSION, true );
      wp_register_script( 'rp-backbone-modal', RP_PLUGIN_URL . 'assets/js/admin/backbone-modal.js', array( 'underscore', 'backbone', 'wp-util' ), RP_VERSION );
      wp_register_script( 'timepicker', RP_PLUGIN_URL . 'assets/js/timepicker/jquery.timepicker.js', array( 'jquery' ), RP_VERSION );
      wp_register_script( 'rp-admin-meta-boxes', RP_PLUGIN_URL . 'assets/js/admin/meta-boxes.js', array( 'jquery', 'jquery-ui-datepicker', 'jquery-ui-sortable', 'select2', 'jquery-tiptip', 'jquery-blockui' ), RP_VERSION );
      wp_register_script( 'rpress-orders', RP_PLUGIN_URL . 'assets/js/admin/rp-orders.js', array( 'jquery', 'rp-backbone-modal' ), RP_VERSION, true );
      wp_register_script( 'jquery-tata-toast', RP_PLUGIN_URL . 'assets/js/rp-tata.js', array( 'jquery' ), RP_VERSION );
      wp_register_script( 'rp-admin', RP_PLUGIN_URL . 'assets/js/admin/rp-admin.js', $admin_deps, RP_VERSION );
      wp_register_script( 'jquery-chosen', RP_PLUGIN_URL . 'assets/js/jquery-chosen/chosen.jquery' . $suffix . '.js', array( 'jquery' ), RP_VERSION );
      wp_register_script( 'admin-dashboard', RP_PLUGIN_URL . 'assets/js/admin/admin-dashboard.js', array( 'jquery','rp-backbone-modal' ), RP_VERSION, true );
      wp_register_script( 'admin-dashboard-chart', 'https://cdn.canvasjs.com/canvasjs.min.js', array( 'jquery' ), RP_VERSION, true );
      wp_enqueue_script( 'jquery-chosen' );
      wp_enqueue_script( 'jquery-form' );
      wp_enqueue_script( 'jquery-ui-datepicker' );
      wp_enqueue_script( 'jquery-ui-dialog' );
      wp_enqueue_script( 'jquery-ui-tooltip' );
      wp_enqueue_script( 'select2' );
      wp_enqueue_script( 'media-upload' );
      wp_enqueue_script( 'thickbox' );
      wp_enqueue_script( 'admin-dashboard' );
      wp_enqueue_script( 'admin-dashboard-chart' );
       
      $is_custom_cordinates_enabled = !empty( rpress_get_option( 'use_custom_latlng' ) ) ? 'yes' : 'no';
      $wp_date_format = get_option( 'date_format' );
      $admin_params = array(
        'ajaxurl'                     => rpress_get_ajax_url(),
        'please_wait'                 => esc_html__( 'Please Wait...', 'restropress' ),
        'success'                     => esc_html__( 'Success', 'restropress' ),
        'error'                       => esc_html__( 'Error', 'restropress' ),
        'information'                 => esc_html__( 'Information', 'restropress' ),
        'license_success'             => esc_html__( 'Congrats, your license successfully activated!', 'restropress' ),
        'license_error'               => esc_html__( 'Invalid License Key', 'restropress' ),
        'license_activate'            => esc_html__( 'Activate License', 'restropress' ),
        'license_deactivated'         => esc_html__( 'Your license has been deactivated', 'restropress' ),
        'deactivate_license'          => esc_html__( 'Deactivate', 'restropress' ),
        'empty_license'               => esc_html__( 'Please enter valid license key', 'restropress' ),
        'update_order_nonce'          => wp_create_nonce( 'update-order' ),
        'use_custom_cordinates'       => $is_custom_cordinates_enabled,
        'post_id'                     => isset( $post->ID ) ? $post->ID : null,
        'rpress_version'              => RP_VERSION,
        'add_new_fooditem'            => esc_html__( 'Add New Menu Item', 'restropress' ),
        'use_this_file'               => esc_html__( 'Use This File', 'restropress' ),
        'quick_edit_warning'          => esc_html__( 'Sorry, not available for variable priced products.', 'restropress' ),
        'delete_payment'              => esc_html__( 'Are you sure you wish to delete this payment?', 'restropress' ),
        'delete_payment_note'         => esc_html__( 'Are you sure you wish to delete this note?', 'restropress' ),
        'delete_tax_rate'             => esc_html__( 'Are you sure you wish to delete this tax rate?', 'restropress' ),
        'resend_receipt'              => esc_html__( 'Are you sure you wish to resend the purchase receipt?', 'restropress' ),
        'disconnect_customer'         => esc_html__( 'Are you sure you wish to disconnect the WordPress user from this customer record?', 'restropress' ),
        'copy_fooditem_link_text'     => esc_html__( 'Copy these links to your clipboard and give them to your customer', 'restropress' ),
        /* translators: %s: singular payment */
        'delete_payment_fooditem'     => sprintf( esc_html__( 'Are you sure you wish to delete this %s?', 'restropress' ), rp_get_label_singular() ), /* translators: %s: singular payment */
        'one_price_min'               => esc_html__( 'You must have at least one price', 'restropress' ),
        'one_field_min'               => esc_html__( 'You must have at least one field', 'restropress' ),
        'one_fooditem_min'            => esc_html__( 'Payments must contain at least one item', 'restropress' ),
        /* translators: %s: singular payment */
        'one_option'                  => sprintf( esc_html__( 'Choose a %s', 'restropress' ), rp_get_label_singular() ), /* translators: %s: singular label */
        'one_or_more_option'          => sprintf( esc_html__( 'Choose one or more %s', 'restropress' ), rp_get_label_plural() ), /* translators: %s: singular label */ 
        'numeric_item_price'          => esc_html__( 'Item price must be numeric', 'restropress' ),
        'numeric_item_tax'            => esc_html__( 'Item tax must be numeric', 'restropress' ),
        'numeric_quantity'            => esc_html__( 'Quantity must be numeric', 'restropress' ),
        'currency'                    => rpress_get_currency(),
        'currency_sign'               => rpress_currency_filter( '' ),
        'currency_pos'                => rpress_get_option( 'currency_position', 'before' ),
        'currency_value_type'         => rpress_get_currency_value_type(),
        'currency_decimals'           => rpress_currency_decimal_filter(),
        'decimal_separator'           => esc_attr( rpress_get_option( 'decimal_separator', '.' )),
        'thousands_separator'         => esc_attr( rpress_get_option( 'thousands_separator', ',' )),
        'date_format'                 => self::wp_date_format_to_jquery_ui( $wp_date_format ),
        'date_placeholder'            => self::wp_date_format_placeholder( $wp_date_format ),
        'new_media_ui'                => apply_filters( 'rpress_use_35_media_ui', 1 ),
        'remove_text'                 => esc_html__( 'Remove', 'restropress' ),
        'type_to_search'              => esc_html__( 'Type to search', 'restropress' ),
        'quantities_enabled'          => rpress_item_quantities_enabled(),
        'batch_export_no_class'       => esc_html__( 'You must choose a method.', 'restropress' ),
        'batch_export_no_reqs'        => esc_html__( 'Required fields not completed.', 'restropress' ),
        'reset_stats_warn'            => esc_html__( 'Are you sure you want to reset your store? This process is <strong><em>not reversible</em></strong>. Please be sure you have a recent backup.', 'restropress' ),
        'unsupported_browser'         => esc_html__( 'We are sorry but your browser is not compatible with this kind of file upload. Please upgrade your browser.', 'restropress' ),
        'show_advanced_settings'      => esc_html__( 'Show advanced settings', 'restropress' ),
        'hide_advanced_settings'      => esc_html__( 'Hide advanced settings', 'restropress' ),
        'is_admin'                    => is_admin(),
        'notification_duration'       => esc_attr( rpress_get_option( 'notification_duration' )) ,
        'enable_order_notification'   => esc_attr( rpress_get_option( 'enable_order_notification' )),
        'loopsound'                   => esc_attr( rpress_get_option( 'notification_sound_loop' )),
        'load_admin_addon_nonce'      => wp_create_nonce( 'load-admin-addon' ),
        'preview_nonce'               => wp_create_nonce( 'rpress-preview-order' ),
        'order_nonce'                 => wp_create_nonce( 'rpress-order' ),
        'reports_nonce'               => wp_create_nonce( 'rpress-admin-reports' ),
        'payment_note_nonce'          => wp_create_nonce( 'rpress-payment-note' ),
        'check_new_orders_nonce'      => wp_create_nonce( 'rpress-check-new-orders' ),
        'activate_license'            => wp_create_nonce( 'activate-license' ),
        'deactivate_license'          => wp_create_nonce( 'deactivate-license' ),
        'selected_filter_nonce'       => wp_create_nonce( 'selected-filter' ),
        'bulk_edit_nonce'             => wp_create_nonce( 'rpress-bulk-edit' ),
        'get_states_nonce'            => wp_create_nonce( 'rpress_get_states_nonce' ),
      );
      wp_localize_script( 'rp-admin', 'rpress_vars',
        $admin_params
      );
      wp_register_script( 'rpress-admin-scripts-compatibility', RP_PLUGIN_URL . '/assets/js/admin/admin-backwards-compatibility' . $suffix . '.js', array( 'jquery', 'rp-admin' ), RP_VERSION );
      wp_localize_script( 'rpress-admin-scripts-compatibility', 'rpress_backcompat_vars', array(
          'purchase_limit_settings'     => esc_html__( 'Purchase Limit Settings', 'restropress' ),
          'simple_shipping_settings'    => esc_html__( 'Simple Shipping Settings', 'restropress' ),
          'software_licensing_settings' => esc_html__( 'Software Licensing Settings', 'restropress' ),
          'recurring_payments_settings' => esc_html__( 'Recurring Payments Settings', 'restropress' ),
      ) );
      wp_enqueue_script( 'wp-color-picker' );
      //call for media manager
      wp_enqueue_media();
      wp_register_script( 'jquery-flot', RP_PLUGIN_URL . '/assets/js/jquery-flot/jquery.flot' . $suffix . '.js' );
      wp_enqueue_script( 'jquery-flot' );
      // Meta boxes.
      if ( in_array( $screen_id, array( 'fooditem', 'edit-fooditem' ) ) ){
        wp_register_script( 'rp-admin-fooditem-meta-boxes', RP_PLUGIN_URL . 'assets/js/admin/meta-boxes-fooditem.js', array( 'rp-admin-meta-boxes' ), RP_VERSION );
        wp_enqueue_script( 'rp-admin-fooditem-meta-boxes' );
        $params = array(
          'post_id'               => isset( $post->ID ) ? $post->ID : '',
          'ajax_url'              => admin_url( 'admin-ajax.php' ),
          'add_price_nonce'       => wp_create_nonce( 'add-price' ),
          'add_category_nonce'    => wp_create_nonce( 'add-category' ),
          'add_addon_nonce'       => wp_create_nonce( 'add-addon' ),
          'load_addon_nonce'      => wp_create_nonce( 'load-addon' ),
          'delete_pricing'        => esc_js( __( 'Are you sure you want to remove this?', 'restropress' ) ),
          'delete_new_category'   => esc_js( __( 'Are you sure to delete this category?', 'restropress' ) ),
          'select_addon_category' => esc_js( __( 'Please select an add-on group first.', 'restropress' ) ),
          'addon_category_already_selected' => esc_js( __( 'Addon category already selected.', 'restropress' ) ),
          'set_image'    => esc_js( __( 'Set Food Image', 'restropress' ) ),
          'change_image' => esc_js( __( 'Change Image', 'restropress' ) ),
          'image_title'  => esc_js( __( 'Select Food Image', 'restropress' ) ),
          'image_button' => esc_js( __( 'Use as food image', 'restropress' ) ),
          'option_single' => esc_js( __( '1 option', 'restropress' ) ),
          'option_plural' => esc_js( __( 'options', 'restropress' ) ),
          'group_single'    => esc_js( __( '1 group', 'restropress' ) ),
          'group_plural'    => esc_js( __( 'groups', 'restropress' ) ),
          'variable_empty'  => esc_js( __( 'No options yet - click "+ Add Option" to add sizes or variants.', 'restropress' ) ),
        );
        wp_localize_script( 'rp-admin-fooditem-meta-boxes', 'fooditem_meta_boxes', $params );
      }
      $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
      if ( $screen_id == 'restropress_page_rpress-payment-history' || $screen_id == 'restropress_page_rpress-dashboard' || in_array( $current_page, array( 'rpress-payment-history', 'rpress-dashboard' ), true ) ) {
        wp_enqueue_script( 'rpress-admin-ui' );
        wp_enqueue_script( 'rpress-orders' );
        wp_localize_script(
          'rpress-orders',
          'rp_orders_params',
          array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'preview_nonce' => wp_create_nonce( 'rpress-preview-order' ),
            'order_nonce'   => wp_create_nonce( 'rpress-order' ),
          )
        );

        // Shared orders stylesheet + filter / quick-edit JS (covers Phase A-C
        // status pills, filter chips, etc. - previously unenqueued).
        wp_enqueue_style(
          'rpress-admin-payments',
          RP_PLUGIN_URL . 'assets/css/admin-payments.css',
          array( 'rpress_admin_styles', 'rpress-admin-ui-screens' ),
          RP_VERSION
        );
        wp_enqueue_script( 'jquery-chosen' );
        wp_enqueue_script(
          'rpress-admin-payments',
          RP_PLUGIN_URL . 'assets/js/admin-payments.js',
          array( 'jquery', 'jquery-chosen' ),
          RP_VERSION,
          true
        );

        // Command Center live refresh - dashboard page only.
        if ( 'rpress-dashboard' === $current_page || 'restropress_page_rpress-dashboard' === $screen_id ) {
          wp_enqueue_script(
            'rpress-command-center',
            RP_PLUGIN_URL . 'assets/js/admin/rp-command-center.js',
            array( 'jquery' ),
            RP_VERSION,
            true
          );
          wp_localize_script(
            'rpress-command-center',
            'rpCommandCenter',
            array(
              'ajax_url' => admin_url( 'admin-ajax.php' ),
              'nonce'    => wp_create_nonce( 'rpress-command-center' ),
              'interval' => (int) apply_filters( 'rpress_command_center_refresh_interval', 30 ) * 1000,
              'i18n'     => array(
                'expired'     => __( 'Session expired. Click Refresh to reload the page.', 'restropress' ),
                'unreachable' => __( 'Live updates paused after repeated errors. Click Refresh to reload the page.', 'restropress' ),
              ),
            )
          );
        }

        // Live Orders kanban - on ?view=live, or when Orders defaults to Live.
        $current_view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : '';
        $is_live_orders_view = ( 'live' === $current_view || ( '' === $current_view && rpress_get_option( 'live_orders_default_view' ) ) );
        if ( $is_live_orders_view && $screen_id === 'restropress_page_rpress-payment-history' ) {
          wp_enqueue_style(
            'rpress-admin-live-orders',
            RP_PLUGIN_URL . 'assets/css/admin-live-orders.css',
            array( 'rpress-admin-payments' ),
            RP_VERSION
          );
          wp_enqueue_script(
            'rpress-admin-live-orders',
            RP_PLUGIN_URL . 'assets/js/admin/rp-live-orders.js',
            array( 'jquery', 'jquery-ui-sortable', 'rpress-orders' ),
            RP_VERSION,
            true
          );
        }
      }
      
      wp_enqueue_script( 'rp-admin' );
    }
    /**
    * RestroPress Admin dashboard  revenue graph filter
    *
    *  return revenue data according filter .
    *
    * @since 1.0
    * @return void
    */
    public function revenue_graph_filter(){
      if ( ! current_user_can( apply_filters( 'rpress_dashboard_stats_cap', 'view_shop_reports' ) ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to access this resource.', 'restropress' ) ), 403 );
      }

      check_ajax_referer( 'rpress-admin-reports', 'nonce' );

      $filter_type = isset( $_POST['select_filter'] ) ? $_POST['select_filter'] : '';
      $SalesByDate = [];
      if( $filter_type == 'yearly') {
        $SalesByDate = $this->get_revenue_report( $filter_type );
      }
      elseif( $filter_type === 'monthly') {
         $SalesByDate = $this->get_revenue_report( $filter_type );
      }
      elseif( $filter_type == 'weekly') {
        $SalesByDate = $this->get_revenue_report( $filter_type );
      }
      wp_send_json( $SalesByDate ); 
    }
    
    private function get_payment_report_by_date_bucket( $start_date, $end_date, $bucket, $sum_total = false, $status = '' ) {
      global $wpdb;

      $date_format = ( 'm' === $bucket ) ? '%m' : '%d';
      $start_datetime = $start_date . ' 00:00:00';
      $end_datetime   = gmdate( 'Y-m-d 00:00:00', strtotime( $end_date . ' +1 day' ) );
      $select_value   = $sum_total ? 'COALESCE(SUM(CAST(pm_total.meta_value AS DECIMAL(18,2))), 0)' : 'COUNT(DISTINCT p.ID)';
      $total_join     = $sum_total ? "LEFT JOIN {$wpdb->postmeta} pm_total ON pm_total.post_id = p.ID AND pm_total.meta_key = '_rpress_payment_total'" : '';
      $status_sql     = '';
      $query_args     = array( $date_format, $start_datetime, $end_datetime );

      if ( '' !== $status ) {
        $status_sql   = 'AND p.post_status = %s';
        $query_args[] = $status;
      }

      $sql = "SELECT DATE_FORMAT(pm_delivery.meta_value, %s) AS date_key, {$select_value} AS total
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_delivery
          ON pm_delivery.post_id = p.ID
          AND pm_delivery.meta_key = '_rpress_delivery_date'
        {$total_join}
        WHERE p.post_type = 'rpress_payment'
          AND p.post_date >= %s
          AND p.post_date < %s
          {$status_sql}
        GROUP BY date_key";

      $rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ) );
      $data = array();

      foreach ( $rows as $row ) {
        if ( '' === $row->date_key || null === $row->date_key ) {
          continue;
        }
        $data[ $row->date_key ] = $sum_total ? (float) $row->total : (int) $row->total;
      }

      return $data;
    }

    public function get_revenue_report( $filter_type ) {
      $SalesByDate          = [];
      $key                  = "";
      $currentMonth         ='';
      $currentYear          = '';
      $first_day_for_filter = '';
      $last_day_for_filter  = '';
      if( $filter_type == 'monthly' ) {
        $key                  = 'd';
        $currentMonth         = gmdate('m');
        $currentYear          = gmdate('Y');
        $first_day_for_filter = gmdate( 'Y-m-01', strtotime( "$currentYear-$currentMonth-01" ) );
        $last_day_for_filter  = gmdate( 'Y-m-t', strtotime( "$currentYear-$currentMonth-01" ) );
      }
      elseif( $filter_type == 'weekly'  ) {
        $key                  = 'd';
        $first_day_for_filter      = gmdate( 'Y-m-d', strtotime( 'this week monday' ) );
        $last_day_for_filter        = gmdate( 'Y-m-d', strtotime( 'this week sunday' ) );
      }
      elseif( $filter_type == 'yearly' ) {
        $key                    = 'm';
        $currentMonth           = gmdate( 'm' );
        $currentYear            = gmdate('Y');
        $first_day_for_filter   = gmdate( 'Y-01-01', strtotime( "$currentYear-01-01" ) );
        $last_day_for_filter    = gmdate( 'Y-12-31', strtotime( "$currentYear-12-31" ) );
      }
     
      
      return $this->get_payment_report_by_date_bucket( $first_day_for_filter, $last_day_for_filter, $key, true, 'publish' );
  
    }
    /**
     * Register Required admin style
     * Taken from scripts.php from RP 2.5
     *
     * @since 1.0
     * @global $post
     * @param string $hook Page hook
     * @return void
     */
    public function register_styles() {
      global $post;
      $screen    = get_current_screen();
      $screen_id = $screen ? $screen->id : '';
      $js_dir  = RP_PLUGIN_URL . 'assets/js/';
      $css_dir = RP_PLUGIN_URL . 'assets/css/';
      // Use minified libraries if SCRIPT_DEBUG is turned off
      $suffix = '';
      $this->register_admin_ui_assets();
      $admin_deps = $this->is_rpress_admin_screen( $screen_id ) ? array( 'rpress-admin-ui-screens' ) : array();
      $admin_style_file = RP_PLUGIN_DIR . 'assets/css/rpress-admin' . $suffix . '.css';
      $admin_style_version = file_exists( $admin_style_file ) ? filemtime( $admin_style_file ) : RP_VERSION;
      wp_deregister_style( 'rpress-admin' );
      wp_register_style( 'rpress-admin', $css_dir . 'rpress-admin' . $suffix . '.css', $admin_deps, $admin_style_version);
      wp_enqueue_style('rpress-admin');
      if ( $this->is_rpress_admin_screen( $screen_id ) ) {
        wp_enqueue_script( 'rpress-admin-ui' );
      }
      if ( isset( $_GET['section'] ) && $_GET['section'] == 'order_notifications' ) {
        wp_add_inline_style( 'rpress-admin', 'input#submit { visibility: hidden; }' );
      }
      if ( isset( $_GET['section'] )
        && $_GET['section'] == 'order_notifications'
        && isset( $_GET['rpress_order_status'] )
        && !empty( $_GET['rpress_order_status'] )
      ) {
        $css = 'input#submit { visibility: visible; }';
        $css .= 'table.rpress_emails.widefat { display: none; }';
        $css .= 'p.order_notification_desc{ display: none; }';
        wp_add_inline_style( 'rpress-admin', $css );
      }
      wp_register_style('admin-icons', $css_dir . 'admin-icons' . $suffix . '.css', array(), RP_VERSION);
      wp_enqueue_style('admin-icons');
    }
    /**
    * RestroPress Admin Food Items Icons
    *
    * Echoes the CSS for the fooditems post type icon.
    *
    * @since 1.0
    * @return void
    */
    public function admin_icons_buttons() {
      $svg_images_url = esc_url( RP_PLUGIN_URL . 'assets/svg/restropress-icon.svg' );
      $screen = get_current_screen();
    
    // The Menu Items list Import/Export buttons are now rendered by
    // rpress_fooditem_list_header_buttons() (dashboard-columns.php), pointing
    // at the dedicated Menu Items import/export pages. The previous injection
    // here linked to the old Tools/Reports locations and built the href with
    // esc_url() inside a JS string, so the encoded "&#038;" was treated as a
    // literal fragment and the links landed on the wrong page.
    ?>
      <style type="text/css" media="screen">
        #dashboard_right_now .fooditem-count:before {
          background-image: url(<?php echo esc_url( $svg_images_url ); ?>);
          content: '';
          width: 20px;
          height: 20px;
          background-repeat: no-repeat;
          filter: grayscale(1);
          background-size: 80%;
          -webkit-background-size: 80%;
          -moz-background-size: 80%;
        }
        #icon-edit.icon32-posts-fooditem {
          background-image: url(<?php echo esc_url( $svg_images_url ); ?>);
          content: '';
          width: 20px;
          height: 20px;
          background-repeat: no-repeat;
          filter: grayscale(1);
          background-size: 80%;
          -webkit-background-size: 80%;
          -moz-background-size: 80%;
        }
        @media
        only screen and (-webkit-min-device-pixel-ratio: 1.5),
        only screen and (   min--moz-device-pixel-ratio: 1.5),
        only screen and (     -o-min-device-pixel-ratio: 3/2),
        only screen and (        min-device-pixel-ratio: 1.5),
        only screen and (            min-resolution: 1.5dppx) {
          #icon-edit.icon32-posts-fooditem {
            background-image: url(<?php echo esc_url( $svg_images_url ); ?>);
            content: '';
            width: 20px;
            height: 20px;
            background-repeat: no-repeat;
            filter: grayscale(1);
            background-size: 80%;
            -webkit-background-size: 80%;
            -moz-background-size: 80%;
          }
        }
      </style>
    <?php }
    private function get_selected_filter_ranges( $filter, $custom_start = '', $custom_end = '' ) {
      $today = gmdate( 'Y-m-d' );
      $ranges = array(
        'current_start'  => $today,
        'current_end'    => $today,
        'previous_start' => '',
        'previous_end'   => '',
      );

      switch ( $filter ) {
        case 'this_year':
          $ranges['current_start']  = gmdate( 'Y-01-01' );
          $ranges['current_end']    = gmdate( 'Y-12-31' );
          $ranges['previous_start'] = gmdate( 'Y-01-01', strtotime( '-1 year' ) );
          $ranges['previous_end']   = gmdate( 'Y-12-31', strtotime( '-1 year' ) );
          break;
        case 'yesterday':
          $ranges['current_start']  = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
          $ranges['current_end']    = $ranges['current_start'];
          $ranges['previous_start'] = gmdate( 'Y-m-d', strtotime( '-2 day' ) );
          $ranges['previous_end']   = $ranges['previous_start'];
          break;
        case 'last_week':
          $ranges['current_start']  = gmdate( 'Y-m-d', strtotime( 'last week monday' ) );
          $ranges['current_end']    = gmdate( 'Y-m-d', strtotime( 'last week sunday' ) );
          $ranges['previous_start'] = gmdate( 'Y-m-d', strtotime( 'monday -2 weeks' ) );
          $ranges['previous_end']   = gmdate( 'Y-m-d', strtotime( 'sunday -2 weeks' ) );
          break;
        case 'last_month':
          $ranges['current_start']  = gmdate( 'Y-m-01', strtotime( 'last month' ) );
          $ranges['current_end']    = gmdate( 'Y-m-t', strtotime( 'last month' ) );
          $ranges['previous_start'] = gmdate( 'Y-m-01', strtotime( '-2 months' ) );
          $ranges['previous_end']   = gmdate( 'Y-m-t', strtotime( '-2 months' ) );
          break;
        case 'last_year':
          $ranges['current_start']  = gmdate( 'Y-01-01', strtotime( '-1 year' ) );
          $ranges['current_end']    = gmdate( 'Y-12-31', strtotime( '-1 year' ) );
          $ranges['previous_start'] = gmdate( 'Y-01-01', strtotime( '-2 years' ) );
          $ranges['previous_end']   = gmdate( 'Y-12-31', strtotime( '-2 years' ) );
          break;
        case 'custom':
          $ranges['current_start'] = $this->sanitize_dashboard_date( $custom_start, $today );
          $ranges['current_end']   = $this->sanitize_dashboard_date( $custom_end, $ranges['current_start'] );
          if ( strtotime( $ranges['current_end'] ) < strtotime( $ranges['current_start'] ) ) {
            $temp                    = $ranges['current_start'];
            $ranges['current_start'] = $ranges['current_end'];
            $ranges['current_end']   = $temp;
          }
          break;
        case 'today':
        default:
          $ranges['current_start']  = $today;
          $ranges['current_end']    = $today;
          $ranges['previous_start'] = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
          $ranges['previous_end']   = $ranges['previous_start'];
          break;
      }

      return $ranges;
    }

    private function sanitize_dashboard_date( $date, $fallback ) {
      $date = sanitize_text_field( wp_unslash( (string) $date ) );
      $time = strtotime( $date );
      if ( false === $time ) {
        return $fallback;
      }

      return gmdate( 'Y-m-d', $time );
    }

    private function calculate_dashboard_percentage( $current, $previous ) {
      $previous = (float) $previous;
      if ( 0.0 === $previous ) {
        return 0.0;
      }

      return ( ( (float) $current - $previous ) / $previous ) * 100;
    }

    private function get_delivery_order_count_by_range( $start_date, $end_date ) {
      global $wpdb;

      $query = $wpdb->prepare(
        "SELECT COUNT(DISTINCT pm.post_id)
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = '_rpress_delivery_date'
          AND pm.meta_value BETWEEN %s AND %s
          AND p.post_type = 'rpress_payment'",
        $start_date,
        $end_date
      );

      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      return (int) $wpdb->get_var( $query );
    }

    private function get_customer_count_by_range( $start_date, $end_date ) {
      global $wpdb;
      $table_name = esc_sql( $wpdb->prefix . 'rpress_customers' );

      $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
      if ( $table_exists !== $table_name ) {
        return 0;
      }

      $start_datetime = $start_date . ' 00:00:00';
      $end_datetime   = gmdate( 'Y-m-d 00:00:00', strtotime( $end_date . ' +1 day' ) );

      $query = $wpdb->prepare(
        "SELECT COUNT(*)
        FROM {$table_name}
        WHERE date_created >= %s
          AND date_created < %s",
        $start_datetime,
        $end_datetime
      );

      return (int) $wpdb->get_var( $query );
    }

    private function get_payment_total_by_range_and_status( $start_date, $end_date, $status ) {
      global $wpdb;

      $query = $wpdb->prepare(
        "SELECT COALESCE(SUM(CAST(pm_total.meta_value AS DECIMAL(18,2))), 0)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_date
          ON pm_date.post_id = p.ID
          AND pm_date.meta_key = '_rpress_delivery_date'
        INNER JOIN {$wpdb->postmeta} pm_total
          ON pm_total.post_id = p.ID
          AND pm_total.meta_key = '_rpress_payment_total'
        WHERE p.post_type = 'rpress_payment'
          AND p.post_status = %s
          AND pm_date.meta_value BETWEEN %s AND %s",
        $status,
        $start_date,
        $end_date
      );

      return (float) $wpdb->get_var( $query );
    }

    public function get_dashboard_summary_by_range( $current_start, $current_end, $previous_start = '', $previous_end = '' ) {
      $order_count    = $this->get_delivery_order_count_by_range( $current_start, $current_end );
      $customer_count = $this->get_customer_count_by_range( $current_start, $current_end );
      $total_refund   = $this->get_payment_total_by_range_and_status( $current_start, $current_end, 'refunded' );
      $total_sales    = $this->get_payment_total_by_range_and_status( $current_start, $current_end, 'publish' );

      $order_percentage    = 0.0;
      $customer_percentage = 0.0;
      $refund_percentage   = 0.0;
      $sales_percentage    = 0.0;

      if ( ! empty( $previous_start ) && ! empty( $previous_end ) ) {
        $previous_order_count    = $this->get_delivery_order_count_by_range( $previous_start, $previous_end );
        $previous_customer_count = $this->get_customer_count_by_range( $previous_start, $previous_end );
        $previous_total_refund   = $this->get_payment_total_by_range_and_status( $previous_start, $previous_end, 'refunded' );
        $previous_total_sales    = $this->get_payment_total_by_range_and_status( $previous_start, $previous_end, 'publish' );

        $order_percentage    = $this->calculate_dashboard_percentage( $order_count, $previous_order_count );
        $customer_percentage = $this->calculate_dashboard_percentage( $customer_count, $previous_customer_count );
        $refund_percentage   = $this->calculate_dashboard_percentage( $total_refund, $previous_total_refund );
        $sales_percentage    = $this->calculate_dashboard_percentage( $total_sales, $previous_total_sales );
      }

      return array(
        'order_count'          => $order_count,
        'customer_count'       => $customer_count,
        'total_refund'         => $total_refund,
        'total_sales'          => $total_sales,
        'order_percentage'     => $order_percentage,
        'customer_percentage'  => $customer_percentage,
        'refund_percentage'    => $refund_percentage,
        'sales_percentage'     => $sales_percentage,
      );
    }

    public function selected_filter() {
      if ( ! current_user_can( apply_filters( 'rpress_dashboard_stats_cap', 'view_shop_reports' ) ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to access this resource.', 'restropress' ) ), 403 );
      }

      check_ajax_referer( 'selected-filter', 'nonce' );

      $pdate      = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : 'today';
      $start_date = isset( $_POST['startDate'] ) ? sanitize_text_field( wp_unslash( $_POST['startDate'] ) ) : '';
      $end_date   = isset( $_POST['endDate'] ) ? sanitize_text_field( wp_unslash( $_POST['endDate'] ) ) : '';
      $ranges     = $this->get_selected_filter_ranges( $pdate, $start_date, $end_date );
      $summary    = $this->get_dashboard_summary_by_range(
        $ranges['current_start'],
        $ranges['current_end'],
        $ranges['previous_start'],
        $ranges['previous_end']
      );

      $data = array(
        'order_count'         => (int) $summary['order_count'],
        'customer_count'      => (int) $summary['customer_count'],
        'total_refund'        => number_format( (float) $summary['total_refund'], 2, '.', '' ),
        'total_sales'         => number_format( (float) $summary['total_sales'], 2, '.', '' ),
        'order_percentage'    => number_format( (float) $summary['order_percentage'], 2, '.', '' ),
        'customer_percentage' => number_format( (float) $summary['customer_percentage'], 2, '.', '' ),
        'refund_percentage'   => number_format( (float) $summary['refund_percentage'], 2, '.', '' ),
        'sales_percentage'    => number_format( (float) $summary['sales_percentage'], 2, '.', '' ),
      );

      wp_send_json( $data );
    }
    public function get_today_order_count( $date ) {
      global $wpdb;
      $query = $wpdb->prepare( "SELECT count(*) as count
          FROM {$wpdb->postmeta}
          WHERE `meta_key` = '_rpress_delivery_date'
          AND `meta_value` = %s
          GROUP BY meta_value", $date );
  
      $total_order_count = $wpdb->get_var( $query );
      return $total_order_count ? $total_order_count : 0;
    }
    
    public function get_this_year_order_count( $start_date, $end_date ) {
      global $wpdb;
      $query = $wpdb->prepare( "SELECT count(*) as count
          FROM {$wpdb->postmeta}
          WHERE `meta_key` = '_rpress_delivery_date'
          AND `meta_value` BETWEEN %s AND %s", $start_date, $end_date );
  
      $total_order_count = $wpdb->get_var( $query );
      return $total_order_count ? $total_order_count : 0;
    }
    public function get_yesterday_order_count( $date ) {
      global $wpdb;
      $query = $wpdb->prepare( "SELECT count(*) as count
          FROM {$wpdb->postmeta}
          WHERE `meta_key` = '_rpress_delivery_date'
          AND `meta_value` = %s
          GROUP BY meta_value", $date );
  
      $total_order_count = $wpdb->get_var( $query );
      return $total_order_count ? $total_order_count : 0;
    }
    public function get_last_week_order_count( $start_date, $end_date ) {
      global $wpdb;
      $query = $wpdb->prepare( "SELECT count(*) as count
          FROM {$wpdb->postmeta}
          WHERE `meta_key` = '_rpress_delivery_date'
          AND `meta_value` BETWEEN %s AND %s",
         $start_date, $end_date );
  
      $total_order_count = $wpdb->get_var( $query );
      return $total_order_count ? $total_order_count : 0;
    }
    public function get_last_month_order_count( $start_date, $end_date ) {
      global $wpdb;
      $query = $wpdb->prepare( "SELECT count(*) as count
          FROM {$wpdb->postmeta}
          WHERE `meta_key` = '_rpress_delivery_date'
          AND `meta_value` BETWEEN %s AND %s", $start_date, $end_date );
  
      $total_order_count = $wpdb->get_var($query);
      return $total_order_count ? $total_order_count : 0;
    }
    public function get_last_year_order_count( $start_date, $end_date ) {
      global $wpdb;
      $query = $wpdb->prepare( "SELECT count(*) as count
          FROM {$wpdb->postmeta}
          WHERE `meta_key` = '_rpress_delivery_date'
          AND `meta_value` BETWEEN %s AND %s", $start_date, $end_date );
  
      $total_order_count = $wpdb->get_var( $query );
      return $total_order_count ? $total_order_count : 0;
      
    }
    public function get_custom_order_count( $range_of_start_date, $range_of_end_date ) {
      global $wpdb;
      $query = $wpdb->prepare( "SELECT count(*) as count
          FROM {$wpdb->postmeta}
          WHERE `meta_key` = '_rpress_delivery_date'
          AND `meta_value` BETWEEN %s AND %s",
         $range_of_start_date, $range_of_end_date );
  
      $total_order_count = $wpdb->get_var( $query );
      return $total_order_count ? $total_order_count : 0;
    }
    public function get_today_customer_counts( $table_name, $today_date, $yesterday_date ) {
      global $wpdb;
      $table_name = esc_sql( $table_name );
  
       
      $query_today = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) LIKE %s",
          $today_date . '%'
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count = $wpdb->get_var( $query_today );
  
     
      $query_yesterday = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) LIKE %s",
          $yesterday_date . '%'
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count_yesterday = $wpdb->get_var( $query_yesterday );
      $percentage_change_customer = 0;
      if ( $customer_count_yesterday != 0 ) {
        $percentage_change_customer = (  ( $customer_count - $customer_count_yesterday )  / $customer_count_yesterday ) * 100;
      } 
  
      return array(
          'customer_count'              => $customer_count,
          'customer_count_yesterday'    => $customer_count_yesterday,
          'percentage_change_customer'  => number_format( $percentage_change_customer, 2 )
      );
    }
    public function get_yesterday_customer_counts( $table_name, $yesterday_date, $two_day_previous_date ) {
      global $wpdb;
      $table_name = esc_sql( $table_name );
  
      $query_yesterday = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) LIKE %s",
          $yesterday_date . '%'
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count = $wpdb->get_var( $query_yesterday );
  
      $query_two_days_ago = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) LIKE %s",
          $two_day_previous_date . '%'
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count_two_days_ago = $wpdb->get_var( $query_two_days_ago );
  
      
      $percentage_change_customer = 0;
      if ( $customer_count_two_days_ago != 0 ) {
        $percentage_change_customer = ( ( $customer_count - $customer_count_two_days_ago )  / $customer_count_two_days_ago ) * 100;
      }
      return array(
          'customer_count'              => $customer_count,
          'customer_count_two_days_ago' => $customer_count_two_days_ago,
          'percentage_change_customer'  => number_format( $percentage_change_customer, 2 )
      );
    }
    public function get_last_week_customer_counts( $table_name, $last_week_start, $last_week_end, $two_weeks_ago_start, $two_weeks_ago_end ) {
      global $wpdb;
      $table_name = esc_sql( $table_name );
      $query_last_week = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) BETWEEN %s AND %s",
          $last_week_start,
          $last_week_end
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count = $wpdb->get_var( $query_last_week );
      $query_two_weeks_ago = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) BETWEEN %s AND %s",
          $two_weeks_ago_start,
          $two_weeks_ago_end
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count_two_weeks_ago = $wpdb->get_var( $query_two_weeks_ago );
  
      
      $percentage_change_customer = 0;
      if ( $customer_count_two_weeks_ago != 0 ) {
        $percentage_change_customer = ( ( $customer_count - $customer_count_two_weeks_ago )  / $customer_count_two_weeks_ago ) * 100;
          
      }
  
      return array(
          'customer_count'                => $customer_count,
          'customer_count_two_weeks_ago'  => $customer_count_two_weeks_ago,
          'percentage_change_customer'    => number_format( $percentage_change_customer, 2 )
      );
    }
    public function get_last_month_customer_counts( $table_name, $last_month_start, $last_month_end, $two_month_ago_start, $two_month_ago_end ) {
      global $wpdb;
      $table_name = esc_sql( $table_name );
      $query_last_week = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) BETWEEN %s AND %s",
          $last_month_start,
          $last_month_end
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count = $wpdb->get_var( $query_last_week );
      $query_two_month_ago = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) BETWEEN %s AND %s",
          $two_month_ago_start,
          $two_month_ago_end
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count_two_months_ago = $wpdb->get_var( $query_two_month_ago );
  
      
      $percentage_change_customer = 0;
      if ( $customer_count_two_months_ago != 0 ) {
        $percentage_change_customer = ( ( $customer_count - $customer_count_two_months_ago ) / $customer_count_two_months_ago ) * 100;
      } 
  
      return array(
          'customer_count'                => $customer_count,
          'customer_count_two_months_ago' => $customer_count_two_months_ago,
          'percentage_change_customer'    => number_format( $percentage_change_customer, 2 )
      );
    }
    public function get_last_year_customer_counts( $table_name, $last_year_start, $last_year_end, $two_year_ago_start, $two_year_ago_end ) {
      global $wpdb;
      $table_name = esc_sql( $table_name );
      $query_last_week = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) BETWEEN %s AND %s",
          $last_year_start,
          $last_year_end
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count = $wpdb->get_var( $query_last_week );
      $query_two_weeks_ago = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) BETWEEN %s AND %s",
          $two_year_ago_start,
          $two_year_ago_end
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count_two_year_ago = $wpdb->get_var( $query_two_weeks_ago );
  
      
      $percentage_change_customer = 0;
      if ( $customer_count_two_year_ago != 0 ) {
        $percentage_change_customer = ( ( $customer_count - $customer_count_two_year_ago ) / $customer_count_two_year_ago ) * 100;   
      }
  
      return array(
          'customer_count'              => $customer_count,
          'customer_count_two_year_ago' => $customer_count_two_year_ago,
          'percentage_change_customer'  => number_format( $percentage_change_customer, 2 )
      );
    }
    public function get_this_year_customer_counts( $table_name, $this_year_start, $this_year_end, $start_of_last_year, $end_of_last_year ) {
      global $wpdb;
      $table_name = esc_sql( $table_name );
      $query_last_week = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) BETWEEN %s AND %s",
          $this_year_start,
          $this_year_end
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count = $wpdb->get_var( $query_last_week );
      $query_two_weeks_ago = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) BETWEEN %s AND %s",
          $start_of_last_year,
          $end_of_last_year
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count_last_year = $wpdb->get_var( $query_two_weeks_ago );
  
      
      $percentage_change_customer = 0;
      if ( $customer_count_last_year != 0 ) {
        $percentage_change_customer = ( ( $customer_count - $customer_count_last_year ) / $customer_count_last_year ) * 100;
      } 
  
      return array(
          'customer_count'              => $customer_count,
          'customer_count_last_year'    => $customer_count_last_year,
          'percentage_change_customer'  => number_format( $percentage_change_customer, 2 )
      );
    }
    public function get_custom_customer_counts( $table_name, $range_of_start_date, $range_of_end_date ) {
      global $wpdb;
      $table_name = esc_sql( $table_name );
      $query_custom_range = $wpdb->prepare(
          "SELECT COUNT(*)
          FROM {$table_name}
          WHERE DATE(date_created) BETWEEN %s AND %s",
          $range_of_start_date,
          $range_of_end_date
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count = $wpdb->get_var( $query_custom_range );
      $percentage_change_customer = 0;
       
      return array(
          'customer_count'                => $customer_count,
          'percentage_change_customer'    => number_format( $percentage_change_customer, 2 )
      );
    }
    public function calculate_today_refund( $today_date, $yesterday_date ) {
  
      $args_today = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'refunded',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => $today_date,
                'compare' => '=' // Exact match for today's date
            )
        )
      );
    
      $args_yesterday = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'refunded',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => $yesterday_date,
                  'compare' => '=' // Exact match for yesterday's date
              )
          )
      );
      $query_today      = new WP_Query( $args_today );
      $query_yesterday  = new WP_Query( $args_yesterday );
      $total_refund = 0;
      $total_refund_yesterday = 0;
      if ( $query_today->have_posts() ) {
          while ( $query_today->have_posts() ) {
              $query_today->the_post();
              $post_id        = get_the_ID();
              $payment        = new RPRESS_Payment($post_id);
              $amount         = $payment->total;
              $total_refund  += $amount;
          }
          wp_reset_postdata();
      }
      
      if ( $query_yesterday->have_posts() ) {
          while ( $query_yesterday->have_posts() ) {
              $query_yesterday->the_post();
              $post_id          = get_the_ID();
              $payment          = new RPRESS_Payment( $post_id );
              $amount           = $payment->total;
              $total_refund_yesterday += $amount;
          }
          wp_reset_postdata();
      }
      
      $total_refund_percentage = 0;
      if ( $total_refund_yesterday != 0 ) {
          $total_refund_percentage = ( ( $total_refund - $total_refund_yesterday ) / $total_refund_yesterday ) * 100;
      }
      return array(
          'total_refund'            => $total_refund,
          'total_refund_yesterday'  => $total_refund_yesterday,
          'total_refund_percentage' => number_format( $total_refund_percentage, 2 )
      );
    }
    public function calculate_yesterday_refund( $yesterday_date, $two_days_ago_date ) {
  
      $args_yesterday = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'refunded',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => $yesterday_date,
                'compare' => '=' // Exact match for today's date
            )
        )
      );
    
      $args_two_days_ago = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'refunded',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => $two_days_ago_date,
                  'compare' => '=' // Exact match for yesterday's date
              )
          )
      );
      $query_yesterday      = new WP_Query( $args_yesterday );
      $query_two_days_ago  = new WP_Query( $args_two_days_ago );
      $total_refund = 0;
      $total_refund_two_days_ago = 0;
      if ( $query_yesterday->have_posts() ) {
          while ( $query_yesterday->have_posts() ) {
              $query_yesterday->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment( $post_id );
              $amount = $payment->total;
              $total_refund += $amount;
          }
          wp_reset_postdata();
      }
      
      if ( $query_two_days_ago->have_posts() ) {
          while ( $query_two_days_ago->have_posts() ) {
              $query_two_days_ago->the_post();
              $post_id          = get_the_ID();
              $payment          = new RPRESS_Payment( $post_id );
              $amount           = $payment->total;
              $total_refund_two_days_ago += $amount;
          }
          wp_reset_postdata();
      }
      
      $total_refund_percentage = 0;
      if ( $total_refund_two_days_ago != 0 ) {
          $total_refund_percentage = ( ( $total_refund - $total_refund_two_days_ago ) / $total_refund_two_days_ago ) * 100;
      }
      return array(
          'total_refund' => $total_refund,
          'total_refund_two_days_ago' => $total_refund_two_days_ago,
          'total_refund_percentage' => number_format( $total_refund_percentage, 2 )
      );
    }
    public function calculate_last_weekly_refunds( $start_date_last_week, $end_date_last_week, $previous_date_week_start, $previous_date_week_end ) {
      $args_last_week = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'refunded',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => array( $start_date_last_week, $end_date_last_week ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
      );
      $query_last_week = new WP_Query( $args_last_week );
      $total_refund = 0;
      if ( $query_last_week->have_posts()) {
          while ( $query_last_week->have_posts() ) {
              $query_last_week->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment( $post_id );
              $amount = $payment->total;
              $total_refund += $amount;
          }
          wp_reset_postdata();
      }
      
      $args_previous_week = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'refunded',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => array( $previous_date_week_start, $previous_date_week_end ),
                  'compare' => 'BETWEEN',
                  'type'    => 'DATE'
              )
          )
      );
      $query_previous_week = new WP_Query( $args_previous_week );
      $total_refund_previous_week = 0;
      if ( $query_previous_week->have_posts() ) {
          while ( $query_previous_week->have_posts() ) {
              $query_previous_week->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment($post_id);
              $amount = $payment->total;
              $total_refund_previous_week += $amount;
          }
          wp_reset_postdata();
      }
       
      $total_refund_percentage = 0;
      if ( $total_refund_previous_week != 0 ) {
          $total_refund_percentage = ( ( $total_refund - $total_refund_previous_week ) / $total_refund_previous_week) * 100;
      }
      
      $data = array(
          'total_refund'                => $total_refund,
          'total_refund_previous_week'  => $total_refund_previous_week,
          'total_refund_percentage'     => number_format( $total_refund_percentage, 2 )
      );
      return $data;
    }
    public function calculate_last_month_refunds( $start_date_last_month, $end_date_last_month, $previous_date_month_start, $previous_date_month_end ) {
      $args_last_month = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'refunded',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => array( $start_date_last_month, $end_date_last_month ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
      );
      $query_last_month = new WP_Query( $args_last_month );
      $total_refund = 0;
      if ( $query_last_month->have_posts()) {
          while ( $query_last_month->have_posts() ) {
              $query_last_month->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment( $post_id );
              $amount = $payment->total;
              $total_refund += $amount;
          }
          wp_reset_postdata();
      }
      
      $args_previous_month = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'refunded',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => array( $previous_date_month_start, $previous_date_month_end ),
                  'compare' => 'BETWEEN',
                  'type'    => 'DATE'
              )
          )
      );
      $query_previous_month = new WP_Query( $args_previous_month );
      $total_refund_previous_month = 0;
      if ( $query_previous_month->have_posts() ) {
          while ( $query_previous_month->have_posts() ) {
              $query_previous_month->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment($post_id);
              $amount = $payment->total;
              $total_refund_previous_month += $amount;
          }
          wp_reset_postdata();
      }
       
      $total_refund_percentage = 0;
      if ( $total_refund_previous_month != 0 ) {
          $total_refund_percentage = ( ( $total_refund - $total_refund_previous_month ) / $total_refund_previous_month) * 100;
      }
      
      $data = array(
          'total_refund'                  => $total_refund,
          'total_refund_previous_month'   => $total_refund_previous_month,
          'total_refund_percentage'       => number_format( $total_refund_percentage, 2 )
      );
      return $data;
    }
    public function calculate_last_year_refunds( $start_date_last_year, $end_date_last_year, $previous_date_year_start, $previous_date_year_end ) {
      $args_last_year = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'refunded',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => array( $start_date_last_year, $end_date_last_year ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
      );
      $query_last_year = new WP_Query( $args_last_year );
      $total_refund = 0;
      if ( $query_last_year->have_posts()) {
          while ( $query_last_year->have_posts() ) {
              $query_last_year->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment( $post_id );
              $amount = $payment->total;
              $total_refund += $amount;
          }
          wp_reset_postdata();
      }
      
      $args_previous_year = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'refunded',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => array( $previous_date_year_start, $previous_date_year_end ),
                  'compare' => 'BETWEEN',
                  'type'    => 'DATE'
              )
          )
      );
      $query_previous_month = new WP_Query( $args_previous_year );
      $total_refund_previous_year = 0;
      if ( $query_previous_month->have_posts() ) {
          while ( $query_previous_month->have_posts() ) {
              $query_previous_month->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment($post_id);
              $amount = $payment->total;
              $total_refund_previous_year += $amount;
          }
          wp_reset_postdata();
      }
       
      $total_refund_percentage = 0;
      if ( $total_refund_previous_year != 0 ) {
          $total_refund_percentage = ( ( $total_refund - $total_refund_previous_year ) / $total_refund_previous_year) * 100;
      }
      
      $data = array(
          'total_refund'                  => $total_refund,
          'total_refund_previous_year'    => $total_refund_previous_year,
          'total_refund_percentage'       => number_format( $total_refund_percentage, 2 )
      );
      return $data;
    }
    public function calculate_this_year_refunds( $start_date_this_year, $end_date_this_year, $last_year_date_start, $last_year_date_end ) {
      $args_this_year = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'refunded',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => array( $start_date_this_year, $end_date_this_year ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
      );
      $query_this_year = new WP_Query( $args_this_year );
      $total_refund = 0;
      if ( $query_this_year->have_posts()) {
          while ( $query_this_year->have_posts() ) {
              $query_this_year->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment( $post_id );
              $amount = $payment->total;
              $total_refund += $amount;
          }
          wp_reset_postdata();
      }
      
      $args_previous_year = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'refunded',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => array( $last_year_date_start, $last_year_date_end ),
                  'compare' => 'BETWEEN',
                  'type'    => 'DATE'
              )
          )
      );
      $query_previous_year = new WP_Query( $args_previous_year );
      $total_refund_previous_year = 0;
      if ( $query_previous_year->have_posts() ) {
          while ( $query_previous_year->have_posts() ) {
              $query_previous_year->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment($post_id);
              $amount = $payment->total;
              $total_refund_previous_year += $amount;
          }
          wp_reset_postdata();
      }
       
      $total_refund_percentage = 0;
      if ( $total_refund_previous_year != 0 ) {
          $total_refund_percentage = ( ( $total_refund - $total_refund_previous_year ) / $total_refund_previous_year) * 100;
      }
      
      $data = array(
          'total_refund'                  => $total_refund,
          'total_refund_previous_year'    => $total_refund_previous_year,
          'total_refund_percentage'       => number_format( $total_refund_percentage, 2 )
      );
      return $data;
    }
    public function calculate_custom_refunds( $range_of_start_date, $range_of_end_date ) {
      $args_custom = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'refunded',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => array( $range_of_start_date, $range_of_end_date ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
      );
      $query_custom_range = new WP_Query( $args_custom );
      $total_refund = 0;
      $total_refund_percentage =0;
      if ( $query_custom_range->have_posts()) {
          while ( $query_custom_range->have_posts() ) {
              $query_custom_range->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment( $post_id );
              $amount = $payment->total;
              $total_refund += $amount;
          }
          wp_reset_postdata();
      }
       
      $data = array(
          'total_refund'                  => $total_refund,
          'total_refund_percentage'       => number_format( $total_refund_percentage, 2 )
      );
      return $data;
    }
    public function calculate_today_sales( $today_date, $yesterday_date ) {
  
      $args_today = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => $today_date,
                'compare' => '=' // Exact match for today's date
            )
        )
      );
    
      $args_yesterday = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'publish',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => $yesterday_date,
                  'compare' => '=' // Exact match for yesterday's date
              )
          )
      );
      $query_today      = new WP_Query( $args_today );
      $query_yesterday  = new WP_Query( $args_yesterday );
      $total_today_sales = 0;
      $total_sales_yesterday = 0;
      if ( $query_today->have_posts() ) {
          while ( $query_today->have_posts() ) {
              $query_today->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment( $post_id );
              $amount = $payment->total;
              $total_today_sales += $amount;
          }
          wp_reset_postdata();
      }
      
      if ( $query_yesterday->have_posts() ) {
          while ( $query_yesterday->have_posts() ) {
              $query_yesterday->the_post();
              $post_id          = get_the_ID();
              $payment          = new RPRESS_Payment( $post_id );
              $amount           = $payment->total;
              $total_sales_yesterday += $amount;
          }
          wp_reset_postdata();
      }
      
      $total_sales_percentage = 0;
      if ( $total_sales_yesterday != 0 ) {
          $total_sales_percentage = ( ( $total_today_sales - $total_sales_yesterday ) / $total_sales_yesterday ) * 100;
      }
      return array(
          'total_sales'             => $total_today_sales,
          'total_sales_yesterday'   => $total_sales_yesterday,
          'total_sales_percentage'  => number_format( $total_sales_percentage, 2 )
      );
    }
    public function calculate_yesterday_sales( $yesterday_date, $two_days_ago_date ) {
  
      $args_yesterday = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => $yesterday_date,
                'compare' => '=' // Exact match for today's date
            )
        )
      );
    
      $args_two_days_ago = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'publish',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => $two_days_ago_date,
                  'compare' => '=' // Exact match for yesterday's date
              )
          )
      );
      $query_yesterday      = new WP_Query( $args_yesterday );
      $query_two_days_ago  = new WP_Query( $args_two_days_ago );
      $total_yesterday_sales = 0;
      $total_sales_two_days_ago = 0;
      if ( $query_yesterday->have_posts() ) {
          while ( $query_yesterday->have_posts() ) {
              $query_yesterday->the_post();
              $post_id  = get_the_ID();
              $payment  = new RPRESS_Payment( $post_id );
              $amount   = $payment->total;
              $total_yesterday_sales += $amount;
          }
          wp_reset_postdata();
      }
      
      if ( $query_two_days_ago->have_posts() ) {
          while ( $query_two_days_ago->have_posts() ) {
              $query_two_days_ago->the_post();
              $post_id          = get_the_ID();
              $payment          = new RPRESS_Payment( $post_id );
              $amount           = $payment->total;
              $total_sales_two_days_ago += $amount;
          }
          wp_reset_postdata();
      }
      
      $total_sales_percentage = 0;
      if ( $total_sales_two_days_ago != 0 ) {
          $total_sales_percentage = ( ( $total_yesterday_sales - $total_sales_two_days_ago ) / $total_sales_two_days_ago ) * 100;
      }
      return array(
          'total_sales'               => $total_yesterday_sales,
          'total_sales_two_days_ago'  => $total_sales_two_days_ago,
          'total_sales_percentage'    => number_format( $total_sales_percentage, 2 )
      );
    }
    public function calculate_last_weekly_sales( $start_date_last_week, $end_date_last_week, $previous_date_week_start, $previous_date_week_end ) {
      $args_last_week = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => array( $start_date_last_week, $end_date_last_week ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
      );
      $query_last_week = new WP_Query( $args_last_week );
      $total_sales_last_week = 0;
      if ( $query_last_week->have_posts()) {
          while ( $query_last_week->have_posts() ) {
              $query_last_week->the_post();
              $post_id          = get_the_ID();
              $payment          = new RPRESS_Payment( $post_id );
              $amount           = $payment->total;
              $total_sales_last_week += $amount;
          }
          wp_reset_postdata();
      }
      
      $args_previous_week = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'publish',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => array( $previous_date_week_start, $previous_date_week_end ),
                  'compare' => 'BETWEEN',
                  'type'    => 'DATE'
              )
          )
      );
      $query_previous_week = new WP_Query( $args_previous_week );
      $total_sales_previous_week = 0;
      if ( $query_previous_week->have_posts() ) {
          while ( $query_previous_week->have_posts() ) {
              $query_previous_week->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment($post_id);
              $amount = $payment->total;
              $total_sales_previous_week += $amount;
          }
          wp_reset_postdata();
      }
       
      $total_sales_percentage = 0;
      if ( $total_sales_previous_week != 0 ) {
          $total_sales_percentage = ( ( $total_sales_last_week - $total_sales_previous_week ) / $total_sales_previous_week) * 100;
      }
      
      $data = array(
          'total_sales'                 => $total_sales_last_week,
          'total_sales_previous_week'   => $total_sales_previous_week,
          'total_sales_percentage'      => number_format( $total_sales_percentage, 2 )
      );
      return $data;
    }
    public function calculate_last_month_sales( $start_date_last_month, $end_date_last_month, $previous_date_month_start, $previous_date_month_end ) {
      $args_last_month = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => array( $start_date_last_month, $end_date_last_month ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
      );
      $query_last_month = new WP_Query( $args_last_month );
      $total_last_month_sales = 0;
      if ( $query_last_month->have_posts()) {
          while ( $query_last_month->have_posts() ) {
              $query_last_month->the_post();
              $post_id            = get_the_ID();
              $payment            = new RPRESS_Payment( $post_id );
              $amount             = $payment->total;
              $total_last_month_sales += $amount;
          }
          wp_reset_postdata();
      }
      
      $args_previous_month = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'publish',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => array( $previous_date_month_start, $previous_date_month_end ),
                  'compare' => 'BETWEEN',
                  'type'    => 'DATE'
              )
          )
      );
      $query_previous_month = new WP_Query( $args_previous_month );
      $total_sales_previous_month = 0;
      if ( $query_previous_month->have_posts() ) {
          while ( $query_previous_month->have_posts() ) {
              $query_previous_month->the_post();
              $post_id        = get_the_ID();
              $payment        = new RPRESS_Payment($post_id);
              $amount         = $payment->total;
              $total_sales_previous_month += $amount;
          }
          wp_reset_postdata();
      }
       
      $total_sales_percentage = 0;
      if ( $total_sales_previous_month != 0 ) {
          $total_sales_percentage = ( ( $total_last_month_sales - $total_sales_previous_month ) / $total_sales_previous_month) * 100;
      }
      
      $data = array(
          'total_sales'                   => $total_last_month_sales,
          'total_sales_previous_month'    => $total_sales_previous_month,
          'total_sales_percentage'        => number_format( $total_sales_percentage, 2 )
      );
      return $data;
    }
    public function calculate_last_year_sales( $start_date_last_year, $end_date_last_year, $previous_date_year_start, $previous_date_year_end ) {
      $args_last_year = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => array( $start_date_last_year, $end_date_last_year ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
      );
      $query_last_year = new WP_Query( $args_last_year );
      $total_last_year_sales = 0;
      if ( $query_last_year->have_posts()) {
          while ( $query_last_year->have_posts() ) {
              $query_last_year->the_post();
              $post_id        = get_the_ID();
              $payment        = new RPRESS_Payment( $post_id );
              $amount         = $payment->total;
              $total_last_year_sales += $amount;
          }
          wp_reset_postdata();
      }
      
      $args_previous_year = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'publish',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => array( $previous_date_year_start, $previous_date_year_end ),
                  'compare' => 'BETWEEN',
                  'type'    => 'DATE'
              )
          )
      );
      $query_previous_month = new WP_Query( $args_previous_year );
      $total_sales_previous_year = 0;
      if ( $query_previous_month->have_posts() ) {
          while ( $query_previous_month->have_posts() ) {
              $query_previous_month->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment($post_id);
              $amount = $payment->total;
              $total_sales_previous_year += $amount;
          }
          wp_reset_postdata();
      }
       
      $total_sales_percentage = 0;
      if ( $total_sales_previous_year != 0 ) {
          $total_sales_percentage = ( ( $total_last_year_sales - $total_sales_previous_year ) / $total_sales_previous_year) * 100;
      }
      
      $data = array(
          'total_sales'                   => $total_last_year_sales,
          'total_sales_previous_year'     => $total_sales_previous_year,
          'total_sales_percentage'        => number_format( $total_sales_percentage, 2 )
      );
      return $data;
    }
    public function calculate_this_year_sales( $start_date_this_year, $end_date_this_year, $last_year_date_start, $last_year_date_end ) {
      $args_this_year = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => array( $start_date_this_year, $end_date_this_year ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
      );
      $query_this_year = new WP_Query( $args_this_year );
      $total_this_year_sales = 0;
      if ( $query_this_year->have_posts()) {
          while ( $query_this_year->have_posts() ) {
              $query_this_year->the_post();
              $post_id                = get_the_ID();
              $payment                = new RPRESS_Payment( $post_id );
              $amount                 = $payment->total;
              $total_this_year_sales += $amount;
          }
          wp_reset_postdata();
      }
      
      $args_previous_year = array(
          'post_type'      => 'rpress_payment',
          'post_status'    => 'publish',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_rpress_delivery_date',
                  'value'   => array( $last_year_date_start, $last_year_date_end ),
                  'compare' => 'BETWEEN',
                  'type'    => 'DATE'
              )
          )
      );
      $query_previous_year = new WP_Query( $args_previous_year );
      $total_sales_previous_year = 0;
      if ( $query_previous_year->have_posts() ) {
          while ( $query_previous_year->have_posts() ) {
              $query_previous_year->the_post();
              $post_id = get_the_ID();
              $payment = new RPRESS_Payment($post_id);
              $amount = $payment->total;
              $total_sales_previous_year += $amount;
          }
          wp_reset_postdata();
      }
       
      $total_sales_percentage = 0;
      if ( $total_sales_previous_year != 0 ) {
          $total_sales_percentage = ( ( $total_this_year_sales - $total_sales_previous_year ) / $total_sales_previous_year) * 100;
      }
      
      $data = array(
          'total_sales'                   => $total_this_year_sales,
          'total_sales_previous_year'     => $total_sales_previous_year,
          'total_sales_percentage'        => number_format( $total_sales_percentage, 2 )
      );
      return $data;
    }
    public function calculate_custom_sales( $range_of_start_date, $range_of_end_date ) {
      $args_custom = array(
        'post_type'      => 'rpress_payment',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_rpress_delivery_date',
                'value'   => array( $range_of_start_date, $range_of_end_date ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
      );
      $query_custom_range = new WP_Query( $args_custom );
      $total_range_sales = 0;
      $total_sales_percentage =0;
      if ( $query_custom_range->have_posts()) {
          while( $query_custom_range->have_posts() ) {
              $query_custom_range->the_post();
              $post_id                = get_the_ID();
              $payment                = new RPRESS_Payment( $post_id );
              $amount                 = $payment->total;
              $total_range_sales     += $amount;
          }
          wp_reset_postdata();
      }
      
      $data = array(
          'total_sales'                   => $total_range_sales,
          'total_sales_percentage'        => number_format( $total_sales_percentage, 2 )
      );
      return $data;
    }
    public function rpress_do_ajax_export() {
      require_once RP_PLUGIN_DIR . 'includes/admin/reporting/export/class-batch-export.php';
    
      parse_str( $_POST['form'], $form );
    
      $_REQUEST = $form = (array) $form;
    
    
      if( ! wp_verify_nonce( $_REQUEST['rpress_ajax_export'], 'rpress_ajax_export' ) ) {
        die( '-2' );
      }
    
      do_action( 'rpress_batch_export_class_include', $form['rpress-export-class'] );
    
      $step     = absint( $_POST['step'] );
      $class    = sanitize_text_field( $form['rpress-export-class'] );
      // Validate the class (exists, is a real exporter, user is capable)
      // before instantiating - never call `new $class` on a raw request value.
      if( ! function_exists( 'rpress_is_allowed_export_class' ) || ! rpress_is_allowed_export_class( $class ) ) {
        die( '-1' );
      }
      $export   = new $class( $step );

      if( ! $export->can_export() ) {
        die( '-1' );
      }
    
      if ( ! $export->is_writable ) {
        echo wp_json_encode( array( 'error' => true, 'message' => esc_html__( 'Export location or file not writable', 'restropress' ) ) ); exit;
      }
    
      $export->set_properties( $_REQUEST );
      
      // Added in 2.5 to allow a bulk processor to pre-fetch some data to speed up the remaining steps and cache data
      $export->pre_fetch();
    
      $ret = $export->process_step( $step );
    
      $percentage = $export->get_percentage_complete();
    
      if( $ret ) {
    
        $step += 1;
        echo wp_json_encode( array( 'step' => $step, 'percentage' => $percentage ) ); exit;
    
      } elseif ( true === $export->is_empty ) {
    
        echo wp_json_encode( array( 'error' => true, 'message' => esc_html__( 'No data found for export parameters', 'restropress' ) ) ); exit;
    
      } elseif ( true === $export->done && true === $export->is_void ) {
    
        $message = ! empty( $export->message ) ? $export->message : esc_html__( 'Batch Processing Complete', 'restropress' );
        echo wp_json_encode( array( 'success' => true, 'message' => $message ) ); exit;
    
      } else {
    
        $args = array_merge( $form, array(
          'step'       => $step,
          'class'      => $class,
          'nonce'      => wp_create_nonce( 'rpress-batch-export' ),
          'rpress_action' => 'fooditem_batch_export',
        ) );
    
        $fooditem_url = add_query_arg( $args, admin_url() );
    
        echo wp_json_encode( array( 'step' => 'done', 'url' => $fooditem_url ) ); exit;
    
      }
    }
    public function order_graph_filter() {
      if ( ! current_user_can( apply_filters( 'rpress_dashboard_stats_cap', 'view_shop_reports' ) ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to access this resource.', 'restropress' ) ), 403 );
      }

      check_ajax_referer( 'rpress-admin-reports', 'nonce' );

      $filter_type = isset( $_POST[ 'select_filter' ] ) ? $_POST[ 'select_filter' ] : '';
      $SalesByDate = [];
      if (  $filter_type === 'monthly' || $filter_type === 'weekly' || $filter_type === 'yearly'  ) {
        $SalesByDate  = $this->get_order_report( $filter_type  );
    }
      wp_send_json(  $SalesByDate );
    }
    public function get_order_report( $filter_type ) {
        $SalesByDate            = [];
        $key                    = '';
        $currentMonth           = '';
        $first_day_for_filter   = '';
        $last_day_for_filter    = '';
        if ( $filter_type == 'monthly' ) {
          $key                = 'd';
          $first_day_of_month = gmdate( 'Y-m-01' );
          $last_day_of_month  = gmdate( 'Y-m-t' );
        } elseif ( $filter_type == 'weekly' ) {
          $key                  = 'd';
          $currentDate          = gmdate( 'Y-m-d' );
          $previousSixDays      = gmdate( 'Y-m-d', strtotime( '-7 days', strtotime( $currentDate ) ) );
          $first_day_for_filter = $previousSixDays;
          $last_day_for_filter  = $currentDate;
        } elseif ( $filter_type == 'yearly' ) {
          $key            = 'm';
          $currentYear    = gmdate('Y');
          $first_day_for_filter   = gmdate("$currentYear-01-01");
          $last_day_for_filter    = gmdate("$currentYear-12-t");
        }
        return $this->get_payment_report_by_date_bucket( $first_day_for_filter, $last_day_for_filter, $key );
    }
    public function customers_data_filter(){
      if ( ! current_user_can( apply_filters( 'rpress_dashboard_stats_cap', 'view_shop_reports' ) ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to access this resource.', 'restropress' ) ), 403 );
      }

      check_ajax_referer( 'rpress-admin-reports', 'nonce' );

      $customer_filter = isset( $_POST['selected_option'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_option'] ) ) : 'monthly';
      $today           = gmdate( 'Y-m-d' );

      $current_start  = gmdate( 'Y-m-01' );
      $current_end    = $today;
      $previous_start = gmdate( 'Y-m-01', strtotime( '-1 month' ) );
      $previous_end   = gmdate( 'Y-m-d', strtotime( $today . ' -1 month' ) );

      if ( 'yearly' === $customer_filter ) {
        $current_start  = gmdate( 'Y-01-01' );
        $current_end    = $today;
        $previous_start = gmdate( 'Y-01-01', strtotime( '-1 year' ) );
        $previous_end   = gmdate( 'Y-m-d', strtotime( $today . ' -1 year' ) );
      } elseif ( 'weekly' === $customer_filter ) {
        $current_start  = gmdate( 'Y-m-d', strtotime( '-6 days' ) );
        $current_end    = $today;
        $previous_start = gmdate( 'Y-m-d', strtotime( '-13 days' ) );
        $previous_end   = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
      }

      $customer_count      = $this->get_customer_count_by_range( $current_start, $current_end );
      $customer_count_last = $this->get_customer_count_by_range( $previous_start, $previous_end );
      $customer_change     = $this->calculate_dashboard_percentage( $customer_count, $customer_count_last );

      $data = array(
        'customer_count'      => $customer_count,
        'customer_percentage' => number_format( $customer_change, 2, '.', '' ),
        'customer_count_last' => $customer_count_last,
      );

      wp_send_json( $data );
    }
    public function get_this_year_customers_data( $table_name, $this_year_start, $this_year_end, $start_of_last_year, $end_of_last_year ) {
      global $wpdb;
      $query_last_week = $wpdb->prepare("
          SELECT COUNT(*) 
          FROM $table_name 
          WHERE DATE(date_created) BETWEEN %s AND %s", 
          array( $this_year_start, $this_year_end )
      );
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count = $wpdb->get_var( $query_last_week );
      $query_two_weeks_ago = $wpdb->prepare("
          SELECT COUNT(*) 
          FROM $table_name 
          WHERE DATE(date_created) BETWEEN %s AND %s", 
          array( $start_of_last_year, $end_of_last_year )
      );
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count_last_year = $wpdb->get_var( $query_two_weeks_ago );
      
      $percentage_change_customer = 0;
      if ( $customer_count_last_year != 0 ) {
        $percentage_change_customer = ( ( $customer_count - $customer_count_last_year ) / $customer_count_last_year ) * 100;
      } 
      
      return array(
        'customer_count'              => $customer_count,
        'customer_count_last'    => $customer_count_last_year,
        'percentage_change_customer'  => number_format( $percentage_change_customer, 2 )
      );
    }
    public function get_this_month_customer_counts( $table_name, $start_of_this_month, $end_of_this_month, $last_month_start, $last_month_end ) {
      global $wpdb;
      $query_this_week = $wpdb->prepare("
          SELECT COUNT(*) 
          FROM $table_name 
          WHERE DATE(date_created) BETWEEN %s AND %s", 
          array( $start_of_this_month, $end_of_this_month )
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count = $wpdb->get_var( $query_this_week );
      $query_last_month_ago = $wpdb->prepare("
          SELECT COUNT(*) 
          FROM $table_name 
          WHERE DATE(date_created) BETWEEN %s AND %s", 
          array( $last_month_start, $last_month_end )
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count_last_months_ago = $wpdb->get_var( $query_last_month_ago );
  
      
      $percentage_change_customer = 0;
      if ( $customer_count_last_months_ago != 0 ) {
        $percentage_change_customer = ( ( $customer_count - $customer_count_last_months_ago ) / $customer_count_last_months_ago ) * 100;
      } 
  
      return array(
          'customer_count'                  => $customer_count,
          'customer_count_last'  => $customer_count_last_months_ago,
          'percentage_change_customer'      => number_format( $percentage_change_customer, 2 )
      );
    }
    public function get_this_week_customer_counts( $table_name, $start_of_this_week, $end_of_this_week, $last_week_start, $last_week_end ) {
      global $wpdb;
      $query_this_week = $wpdb->prepare("
          SELECT COUNT(*) 
          FROM $table_name 
          WHERE DATE(date_created) BETWEEN %s AND %s", 
          array( $start_of_this_week, $end_of_this_week )
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count = $wpdb->get_var( $query_this_week );
      $query_last_weeks_ago = $wpdb->prepare("
          SELECT COUNT(*) 
          FROM $table_name 
          WHERE DATE(date_created) BETWEEN %s AND %s", 
          array( $last_week_start, $last_week_end )
      );
  
      // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query values are prepared above.
      $customer_count_last_weeks_ago = $wpdb->get_var( $query_last_weeks_ago );
  
      
      $percentage_change_customer = 0;
      if ( $customer_count_last_weeks_ago != 0 ) {
        $percentage_change_customer = ( ( $customer_count - $customer_count_last_weeks_ago )  / $customer_count_last_weeks_ago ) * 100;
          
      }
  
      return array(
          'customer_count'                => $customer_count,
          'customer_count_last'  => $customer_count_last_weeks_ago,
          'percentage_change_customer'  => number_format( $percentage_change_customer, 2 ) . '%'
);
    }
    
    
  }
endif;
if ( ! function_exists( 'rpress_admin_assets' ) ) {
  /**
   * Admin assets singleton accessor.
   *
   * @return RP_Admin_Assets
   */
  function rpress_admin_assets() {
    static $instance = null;

    if ( null === $instance ) {
      $instance = new RP_Admin_Assets();
    }

    return $instance;
  }
}

return rpress_admin_assets();
