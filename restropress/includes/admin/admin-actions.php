<?php
/**
 * Admin Actions
 *
 * @package     RPRESS
 * @subpackage  Admin/Actions
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Processes all RPRESS actions sent via POST and GET by looking for the 'rpress-action'
 * request and running do_action() to call the function
 *
 * @since 1.0.0
 * @return void
 */
function rpress_process_actions() {
	if ( ! is_admin() || ! is_user_logged_in() ) {
		return;
	}

	if ( isset( $_POST['rpress-action'] ) ) {
		$action = sanitize_key( wp_unslash( $_POST['rpress-action'] ) );
		if ( ! empty( $action ) ) {
			do_action( 'rpress_' . $action, rpress_sanitize_array( $_POST ) );
		}
	}
	if ( isset( $_GET['rpress-action'] ) ) {
		$action = sanitize_key( wp_unslash( $_GET['rpress-action'] ) );
		if ( ! empty( $action ) ) {
			do_action( 'rpress_' . $action, rpress_sanitize_array( $_GET ) );
		}
	}
}
add_action( 'admin_init', 'rpress_process_actions' );
/**
 * Display notices to admins
 *
 * @since 2.6
 */
function rp_addon_activation_notice() {
  $items = get_transient( 'restropress_add_ons_feed' );
  if( ! $items ) {
    $items = rpress_fetch_items();
  }
  $statuses = array();
  if( is_array( $items ) && !empty( $items ) ) {
    foreach( $items as $key => $item ) {
      $class_name = trim( $item->class_name );
      if( class_exists( $class_name ) ) {
        if( !get_option( $item->text_domain . '_license_status' ) ) {
          array_push( $statuses, 'empty' );
        } else {
          $status = get_option( $item->text_domain . '_license_status' );
          array_push( $statuses, $status );
        }
      }
    }
  }
  if( !empty( $statuses ) && ( in_array( 'empty', $statuses) || in_array( 'invalid', $statuses) ) ) {
    $class = 'notice notice-error';
    $message = esc_html__( 'You have invalid or expired license keys for one or more addons of RestroPress. Please go to the Extensions page to update your licenses.', 'restropress' );
    printf( '<div class="%1$s"><p>' . esc_html__($message) . '</p></div>', esc_attr( $class ) );
  }
}
add_action( 'admin_notices', 'rp_addon_activation_notice' );
/**
 * Check all extensions for updates
 * @since 2.7.2
 */
add_action( 'init', 'check_extensions_update', 0 );
function check_extensions_update() {
  $doing_cron = function_exists( 'wp_doing_cron' )
    ? wp_doing_cron()
    : ( defined( 'DOING_CRON' ) && DOING_CRON );

  if ( ! is_admin() && ! $doing_cron && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
    return;
  }

  if ( ! function_exists( 'get_plugins' ) ) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
  }

  $items = get_transient( 'restropress_add_ons_feed' );
  if ( ! is_array( $items ) ) {
    $items = rpress_fetch_items();
  }
  $items = is_array( $items ) ? $items : array();
  $feed_items = array();

  foreach ( $items as $item ) {
    if ( ! is_object( $item ) || empty( $item->text_domain ) ) {
      continue;
    }

    $feed_items[ str_replace( '-', '_', sanitize_key( $item->text_domain ) ) ] = $item;
  }

  $all_plugins = get_plugins();
  $ext_data = [];
  foreach ( $all_plugins as $key => $plugin ) {
    if ( $key == plugin_basename( RP_PLUGIN_FILE ) ) {
      continue;
    }

    $text_domain = ! empty( $plugin['TextDomain'] ) ? $plugin['TextDomain'] : dirname( $key );
    if ( '.' === $text_domain || empty( $text_domain ) ) {
      $text_domain = basename( $key, '.php' );
    }

    $license_key = str_replace( '-', '_', sanitize_key( $text_domain ) );
    $author      = strtolower( wp_strip_all_tags( isset( $plugin['Author'] ) ? $plugin['Author'] : '' ) );
    $feed_item   = isset( $feed_items[ $license_key ] ) ? $feed_items[ $license_key ] : null;
    $is_rpress_extension = ! empty( $feed_item ) || in_array( $author, array( 'magnigenie', 'magnigeeks', 'restropress' ), true );

    if ( $is_rpress_extension ) {
      $item_name = ! empty( $feed_item->title )
        ? wp_strip_all_tags( $feed_item->title )
        : ( ! empty( $plugin['Name'] ) ? $plugin['Name'] : $text_domain );

      $ext_data[ $key ] = array(
        'path'        => $key,
        'version'     => ! empty( $plugin['Version'] ) ? $plugin['Version'] : '0',
        'item_name'   => $item_name,
        'license_key' => $license_key,
        'item_id'     => ! empty( $feed_item->id ) ? absint( $feed_item->id ) : 0,
      );
    }
  }
  if ( !empty( $ext_data ) ) {
    foreach ( $ext_data as $ext ) {
      new RestroPress_License(
        $ext['path'],
        $ext['item_name'],
        $ext['version'],
        'MagniGenie',
        $ext['license_key'] . '_license',
        null,
        $ext['item_id']
      );
    }
  }
}
