<?php
/**
 * Shortcodes
 *
 * @package RestroPress/Classes
 * @version 2.6
 */
defined( 'ABSPATH' ) || exit;
/**
 * RestroPress Shortcodes class.
 */
class RP_Shortcodes {
  /**
    * Init Shortcodes.
    */
  public static function init() {
    $shortcodes = array(
      'fooditems'             => __CLASS__ . '::fooditems',
      'fooditem_cart'         => __CLASS__ . '::fooditem_cart',
      'fooditem_checkout'     => __CLASS__ . '::fooditem_checkout',
      'rpress_receipt'        => __CLASS__ . '::rpress_receipt',
      'fooditem_history'      => __CLASS__ . '::fooditem_history',
      'order_history'         => __CLASS__ . '::order_history',
      'rpress_login'          => __CLASS__ . '::rpress_login',
      'rpress_register'       => __CLASS__ . '::rpress_register',
      'fooditem_discounts'    => __CLASS__ . '::fooditem_discounts',
      'rpress_profile_editor' => __CLASS__ . '::rpress_profile_editor',
      'customer_dashboard'    => __CLASS__ . '::customer_dashboard',
       
    );
    foreach ( $shortcodes as $shortcode => $function ) {
      add_shortcode( apply_filters( "{$shortcode}_shortcode_tag", $shortcode ), $function );
    }
  }
  /**
   * Shortcode Wrapper.
   *
   * @param string[] $function Callback function.
   * @param array    $atts     Attributes. Default to empty array.
   * @param array    $wrapper  Customer wrapper data.
   *
   * @return string
   */
  public static function shortcode_wrapper(
    $function,
    $atts = array(),
    $wrapper = array(
      'class'  => '',
      'before' => null,
      'after'  => null,
    )
  ) {
    ob_start();

        if (empty($atts) || is_array($atts)) {

            $escaped_atts = $atts; // or json_encode($atts);
        } else {
            $escaped_atts = esc_js($atts);
        }
    // @codingStandardsIgnoreStart
    echo empty( $wrapper['before'] ) ? '<div class="restropress ' . apply_filters( 'restropress_container_class', esc_attr( $wrapper['class'] ) )  . '">' : $wrapper['before'];
    call_user_func( $function, $escaped_atts ); 
    echo empty( $wrapper['after'] ) ? '<div class="clearfix"></div></div>' : $wrapper['after'];
    // @codingStandardsIgnoreEnd
    return ob_get_clean();
  }
  /**
   * FoodItems Shortcode.
   *
   * @return string
   */
  public static function fooditems( $atts ) {
        if (empty($atts) || is_array($atts)) {

            $escaped_atts = $atts; // or json_encode($atts);
        } else {
            $escaped_atts = esc_js($atts);
        }
    return self::shortcode_wrapper( array( 'RP_Shortcode_Fooditems', 'output' ), $escaped_atts );
  }
  /**
   * Item Cart Shortcode
   *
   * Show the shopping cart.
   *
   * @since 1.0
   * @param array $atts Shortcode attributes
   * @param string $content
   * @return string
   */
  public static function fooditem_cart( $atts = array(), $content = null ) {
    ob_start();
    $escaped_atts = esc_js($atts);
    rpress_shopping_cart($escaped_atts);
    
    return ob_get_clean();
  }
  /**
   * Checkout Form Shortcode
   *
   * Show the checkout form.
   *
   * @since 1.0
   * @return string
   */
  public static function fooditem_checkout() {
    return rpress_checkout_form();
  }
  /**
   * Receipt Shortcode
   *
   * Shows an order receipt.
   *
   * @since  1.0.0
   * @param array $atts Shortcode attributes
   * @param string $content
   * @return string
   */
  public static function rpress_receipt( $atts = array(), $content = null ) {
    global $rpress_receipt_args;
    $rpress_receipt_args = shortcode_atts( array(
      'error'           => __( 'Sorry, trouble retrieving payment receipt.', 'restropress' ),
      'price'           => true,
      'discount'        => true,
      'products'        => true,
      'date'            => true,
      'notes'           => true,
      'payment_key'     => false,
      'payment_method'  => true,
      'payment_id'      => true
    ), $atts, 'rpress_receipt' );
    $session = rpress_get_purchase_session();
    $payment_key = '';

    if ( ! empty( $_GET['payment_key'] ) ) {
        $payment_key = urldecode( sanitize_text_field( $_GET['payment_key'] ) );
    } elseif ( ! empty( $session['purchase_key'] ) ) {
        $payment_key = sanitize_text_field( $session['purchase_key'] );
    } elseif ( ! empty( $rpress_receipt_args['payment_key'] ) ) {
        $payment_key = sanitize_text_field( $rpress_receipt_args['payment_key'] );
    }

    // No key found
    if ( empty( $payment_key ) ) {
      return '<p class="rpress-alert rpress-alert-error">' . $rpress_receipt_args['error'] . '</p>';
    }

    $payment_id    = rpress_get_purchase_id_by_key( $payment_key );
    $user_can_view = rpress_can_view_receipt( $payment_key );
    
    // Key was provided, but user is logged out. Offer them the ability to login and view the receipt
    if ( ! $user_can_view && ! empty( $payment_key ) && ! is_user_logged_in() && ! rpress_is_guest_payment( $payment_id ) ) {
      global $rpress_login_redirect;
      $rpress_login_redirect = rpress_get_current_page_url();
      ob_start();
      echo '<p class="rpress-alert rpress-alert-warn">' . __( 'You must be logged in to view this payment receipt.', 'restropress' ) . '</p>';
      rpress_get_template_part( 'shortcode', 'login' );
      $login_form = ob_get_clean();
      return $login_form;
    }
    $user_can_view = apply_filters( 'rpress_user_can_view_receipt', $user_can_view, $rpress_receipt_args );
    // If this was a guest checkout and the purchase session is empty, output a relevant error message
    if ( empty( $session ) && ! is_user_logged_in() && ! $user_can_view ) {
      return '<p class="rpress-alert rpress-alert-error">' . apply_filters( 'rpress_receipt_guest_error_message', __( 'Receipt could not be retrieved, your purchase session has expired.', 'restropress' ) ) . '</p>';
    }
    /*
     * Check if the user has permission to view the receipt
     *
     * If user is logged in, user ID is compared to user ID of ID stored in payment meta
     *
     * Or if user is logged out and purchase was made as a guest, the purchase session is checked for
     *
     * Or if user is logged in and the user can view sensitive shop data
     *
     */
    if ( ! $user_can_view ) {
      return '<p class="rpress-alert rpress-alert-error">' . $rpress_receipt_args['error'] . '</p>';
    }
    ob_start();
    rpress_get_template_part( 'shortcode', 'receipt' );
    $display = ob_get_clean();
    return $display;
  }
  /**
   * Item History Shortcode
   *
   * Displays a user's fooditem history.
   *
   * @since 1.0
   * @return string
   */
  public static function fooditem_history() {
    if ( is_user_logged_in() ) {
      ob_start();
      if( ! rpress_user_pending_verification() ) {
        rpress_get_template_part( 'history', 'fooditems' );
      } else {
        rpress_get_template_part( 'account', 'pending' );
      }
      return ob_get_clean();
    }
  }
  /**
   * Order History Shortcode
   *
   * Displays a user's order history.
   *
   * @since 1.0
   * @return string
   */
  public static function order_history() {
    ob_start();
    if( ! rpress_user_pending_verification() ) {
      rpress_get_template_part( 'history', 'purchases' );
    } else {
      rpress_get_template_part( 'account', 'pending' );
    }
    return ob_get_clean();
  }
  /**
   * Login Shortcode
   *
   * Shows a login form allowing users to users to log in. This function simply
   * calls the rpress_login_form function to display the login form.
   *
   * @since 1.0
   * @param array $atts Shortcode attributes
   * @param string $content
   * @uses rpress_login_form()
   * @return string
   */
  public static function rpress_login( $atts, $content = null ) {
    $redirect = '';
    $escaped_atts = esc_js($atts);
    extract( shortcode_atts( array(
      'redirect' => $redirect
      ), $escaped_atts, 'rpress_login' )
    );
    if ( empty( $redirect ) ) {
      $login_redirect_page = rpress_get_option( 'login_redirect_page', '' );
      if ( ! empty( $login_redirect_page ) ) {
        $redirect = get_permalink( $login_redirect_page );
      }
    }
    if ( empty( $redirect ) ) {
      $order_history = rpress_get_option( 'order_history_page', 0 );
      if ( ! empty( $order_history ) ) {
        $redirect = get_permalink( $order_history );
      }
    }
    if ( empty( $redirect ) ) {
      $redirect = home_url();
    }
    return rpress_login_form( $redirect );
  }
  /**
   * Register Shortcode
   *
   * Shows a registration form allowing users to register for the site
   *
   * @since  1.0.0
   * @param array $atts Shortcode attributes
   * @param string $content
   * @uses rpress_register_form()
   * @return string
   */
  public static function rpress_register( $atts, $content = null ) {
    $redirect       = home_url();
    $order_history  = rpress_get_option( 'order_history_page', 0 );
    if ( ! empty( $order_history ) ) {
      $redirect = get_permalink( $order_history );
    }
    $escaped_atts = esc_js($atts);
    extract( shortcode_atts( array(
      'redirect' => $redirect
      ), $escaped_atts, 'rpress_register' )
    );
    return rpress_register_form( $redirect );
  }
  /**
   * Discounts shortcode
   *
   * Displays a list of all the active discounts. The active discounts can be configured
   * from the Discount Codes admin screen.
   *
   * @since 1.0
   * @param array $atts Shortcode attributes
   * @param string $content
   * @uses rpress_get_discounts()
   * @return string $discounts_lists List of all the active discount codes
   */
  public static function fooditem_discounts( $atts, $content = null ) {
    $escaped_atts = esc_js($atts);
    $discounts = rpress_get_discounts();
    $discounts_list = '<ul id="rpress_discounts_list">';
    if ( ! empty( $discounts ) && rpress_has_active_discounts() ) {
      foreach ( $discounts as $discount ) {
        if ( rpress_is_discount_active( $discount->ID ) ) {
          $discounts_list .= '<li class="rpress_discount">';
          $discounts_list .= '<span class="rpress_discount_name">' . rpress_get_discount_code( $discount->ID ) . '</span>';
          $discounts_list .= '<span class="rpress_discount_separator"> - </span>';
          $discounts_list .= '<span class="rpress_discount_amount">' . rpress_format_discount_rate( rpress_get_discount_type( $discount->ID ), rpress_get_discount_amount( $discount->ID ) ) . '</span>';
          $discounts_list .= '</li>';
        }
      }
    } else {
      $discounts_list .= '<li class="rpress_discount">' . __( 'No discounts found', 'restropress' ) . '</li>';
    }
    $discounts_list .= '</ul>';
    return $discounts_list;
  }
  /**
   * Profile Editor Shortcode
   *
   * Outputs the RPRESS Profile Editor to allow users to amend their details from the
   * front-end. This function uses the RPRESS templating system allowing users to
   * override the default profile editor template. The profile editor template is located
   * under templates/profile-editor.php, however, it can be altered by creating a
   * file called profile-editor.php in the rpress_template directory in your active theme's
   * folder. Please visit the RPRESS Documentation for more information on how the
   * templating system is used.
   *
   * @since  1.0.0
   *
   * @author RestroPress
   *
   * @param      $atts Shortcode attributes
   * @param null $content
   * @return string Output generated from the profile editor
   */
  public static function rpress_profile_editor( $atts, $content = null ) {
    $escaped_atts = esc_js($atts);
    ob_start();
    if( ! rpress_user_pending_verification() ) {
      rpress_get_template_part( 'shortcode', 'profile-editor',$escaped_atts);
    } else {
      rpress_get_template_part( 'account', 'pending' );
    }
    $display = ob_get_clean();
    return $display;
  }
   /**
   * Customer Dashboard Shortcode
   *
   * Show the Customer dashboard.
   *
   * @since 1.0
   * @param array $atts Shortcode attributes
   * @param string $content
   * @return string
   */
  public static function customer_dashboard( $atts = array(), $content = null ) {
    $escaped_atts = esc_js($atts);
    ob_start();
    include_once plugin_dir_path(RP_PLUGIN_FILE).'templates/rpress-user-dashboard.php';
    return ob_get_clean();
  }
   
}
add_action( 'init', array( 'RP_Shortcodes', 'init' ) );