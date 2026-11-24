<?php
/**
 * Admin Notices Class
 *
 * @package     RPRESS
 * @subpackage  Admin/Notices
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * RPRESS_Notices Class
 *
 * @since 1.0.0
 */
class RPRESS_Notices {
	/**
	 * Get things started
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show_notices' ) );
		add_action( 'rpress_dismiss_notices', array( $this, 'dismiss_notices' ) );
	}
	/**
	 * Show relevant notices
	 *
	 * @since 1.0.0
	 */
	public function show_notices() {
		// 3.5.1: the missing menu and checkout page and test mode notices are
		// problems in the notification center (one banner, on the screens where
		// they matter), and the ?rpress-message= results are toasts. See
		// includes/features/admin-notices/. The Recount Earnings notice is gone.

		// Settings API results registered under rpress-notices (core settings
		// save, extensions): "Settings updated." is a toast, errors stay a banner
		// so they are not missed.
		foreach ( (array) get_settings_errors( 'rpress-notices' ) as $result ) {
			if ( empty( $result['message'] ) ) {
				continue;
			}
			if ( 'error' === $result['type'] ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $result['message'] ) );
			} elseif ( function_exists( 'rpress_admin_toast' ) ) {
				rpress_admin_toast( $result['message'], 'success' );
			} else {
				printf( '<div class="notice notice-success"><p>%s</p></div>', wp_kses_post( $result['message'] ) );
			}
		}
	}
	/**
	 * Dismiss admin notices when Dismiss links are clicked
	 *
	 * @since 1.0.0
	 * @return void
	 */
	function dismiss_notices() {
		if( ! isset( $_GET['rpress_notice'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_shop_settings' ) && ! current_user_can( 'edit_pages' ) && ! current_user_can( 'view_shop_reports' ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'rpress-dismiss-notice' ) ) {
			return;
		}

		$notice_key = sanitize_text_field( wp_unslash( $_GET['rpress_notice'] ) );
		update_user_meta( get_current_user_id(), '_rpress_' . $notice_key . '_dismissed', 1 );
		wp_safe_redirect( remove_query_arg( array( 'rpress_action', 'rpress_notice', '_wpnonce' ) ) );
		exit;
	}
}
new RPRESS_Notices;
