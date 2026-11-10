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
 * Notice on the RestroPress Settings screen.
 */
function rpress_account_hub_reorder_notice() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
	if ( 'rpress-settings' !== $page ) {
		return;
	}
	if ( isset( $_GET['rpress_reorder_enabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- display only.
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Reorder is on. Customers will see a Reorder button in their order history.', 'restropress' ) . '</p></div>';
		return;
	}
	if ( ! rpress_account_hub_show_reorder_notice() ) {
		return;
	}
	$enable_url  = wp_nonce_url( admin_url( 'admin-post.php?action=rpress_reorder_notice&do=enable' ), 'rpress-reorder-notice' );
	$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=rpress_reorder_notice&do=dismiss' ), 'rpress-reorder-notice' );
	?>
	<div class="notice notice-info rpress-reorder-notice">
		<p><strong><?php esc_html_e( 'Let customers reorder past orders in one tap', 'restropress' ); ?></strong></p>
		<p><?php esc_html_e( 'Regulars can repeat a previous order from their order history in one tap. Items no longer on the menu are skipped and the customer is told which ones.', 'restropress' ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $enable_url ); ?>"><?php esc_html_e( 'Enable reorder', 'restropress' ); ?></a>
			<a class="button-link" style="margin-left:12px" href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'No thanks', 'restropress' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'rpress_account_hub_reorder_notice' );

/**
 * Enable / dismiss from the notice.
 */
function rpress_account_hub_handle_reorder_notice() {
	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_die( esc_html__( 'You do not have permission to change RestroPress settings.', 'restropress' ), 403 );
	}
	check_admin_referer( 'rpress-reorder-notice' );

	$do       = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
	$redirect = admin_url( 'admin.php?page=rpress-settings' );
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
