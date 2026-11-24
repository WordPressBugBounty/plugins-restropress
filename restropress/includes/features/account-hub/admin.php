<?php
/**
 * Account hub: admin side. Clearer reorder setting and a one-time notice that
 * lets existing stores switch reorder on (new stores get it on by default,
 * see rpress_run_install()).
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Give the reorder checkbox a name and description customers' owners can act on.
 *
 * @param array $settings Registered settings.
 * @return array
 */
function rpress_account_hub_reorder_setting_copy( $settings ) {
	if ( empty( $settings['general'] ) || ! is_array( $settings['general'] ) ) {
		return $settings;
	}
	foreach ( $settings['general'] as $section => $fields ) {
		if ( is_array( $fields ) && isset( $fields['rp_reorder'] ) ) {
			$settings['general'][ $section ]['rp_reorder']['name'] = esc_html__( 'One-tap reorder', 'restropress' );
			$settings['general'][ $section ]['rp_reorder']['desc'] = esc_html__( 'Show a Reorder button on each order in the customer\'s order history. It puts the same items back in the cart at today\'s prices and skips anything no longer on the menu.', 'restropress' );
		}
	}
	return $settings;
}
add_filter( 'rpress_registered_settings', 'rpress_account_hub_reorder_setting_copy', 20 );

/**
 * Should the reorder notice show?
 *
 * @return bool
 */
function rpress_account_hub_show_reorder_notice() {
	return current_user_can( 'manage_shop_settings' )
		&& ! rpress_account_hub_reorder_enabled()
		&& ! get_option( 'rpress_reorder_notice_dismissed' );
}

/**
 * Suggestion in the admin notification center (3.5.1; was a banner on the
 * Settings screen).
 */
function rpress_account_hub_register_reorder_notice() {
	rpress_register_admin_notice(
		'reorder',
		array(
			'title'     => __( 'Let customers reorder past orders in one tap', 'restropress' ),
			'message'   => __( 'Regulars can repeat a previous order from their order history in one tap. Items no longer on the menu are skipped and the customer is told which ones.', 'restropress' ),
			'actions'   => array(
				array(
					'label'   => __( 'Enable reorder', 'restropress' ),
					'url'     => wp_nonce_url( admin_url( 'admin-post.php?action=rpress_reorder_notice&do=enable' ), 'rpress-reorder-notice' ),
					'primary' => true,
				),
			),
			'condition' => 'rpress_account_hub_show_reorder_notice',
			'dismiss'   => function () {
				update_option( 'rpress_reorder_notice_dismissed', time(), false );
			},
			'priority'  => 30,
		)
	);
}
add_action( 'rpress_register_admin_notices', 'rpress_account_hub_register_reorder_notice' );

/**
 * Enable / dismiss from the notice.
 */
function rpress_account_hub_handle_reorder_notice() {
	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_die( esc_html__( 'You do not have permission to change RestroPress settings.', 'restropress' ), 403 );
	}
	check_admin_referer( 'rpress-reorder-notice' );

	$do       = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
	$redirect = rpress_admin_notice_return_url( admin_url( 'admin.php?page=rpress-settings' ) );
	if ( 'enable' === $do ) {
		rpress_update_option( 'rp_reorder', '1' );
		$redirect = add_query_arg( 'rpress_reorder_enabled', '1', $redirect );
	}
	// Either way the notice has done its job.
	update_option( 'rpress_reorder_notice_dismissed', time(), false );

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_rpress_reorder_notice', 'rpress_account_hub_handle_reorder_notice' );
