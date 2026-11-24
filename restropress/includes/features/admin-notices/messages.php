<?php
/**
 * Admin confirmations as toasts (3.5.1), and the W3 Total Cache problem.
 *
 * The `?rpress-message=` results that used to be banners at the top of the
 * screen are now a short toast on the same screen.
 *
 * @package RestroPress
 * @since   3.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Results of admin actions, keyed by the `rpress-message` value:
 * capability, type, text.
 *
 * @since 3.5.1
 * @return array
 */
function rpress_admin_action_messages() {
	$messages = array(
		// Discounts.
		'discount_added'             => array( 'manage_shop_discounts', 'success', __( 'Discount code added.', 'restropress' ) ),
		'discount_add_failed'        => array( 'manage_shop_discounts', 'error', __( 'There was a problem adding your discount code, please try again.', 'restropress' ) ),
		'discount_exists'            => array( 'manage_shop_discounts', 'error', __( 'A discount with that code already exists, please use a different code.', 'restropress' ) ),
		'discount_updated'           => array( 'manage_shop_discounts', 'success', __( 'Discount code updated.', 'restropress' ) ),
		'discount_update_failed'     => array( 'manage_shop_discounts', 'error', __( 'There was a problem updating your discount code, please try again.', 'restropress' ) ),
		'discount_validation_failed' => array( 'manage_shop_discounts', 'error', __( 'The discount code could not be added because one or more of the required fields was empty, please try again.', 'restropress' ) ),
		'discount_invalid_code'      => array( 'manage_shop_discounts', 'error', __( 'The discount code entered is invalid; only alphanumeric characters are allowed, please try again.', 'restropress' ) ),
		// Orders and reports.
		'payment_deleted'            => array( 'view_shop_reports', 'success', __( 'The payment has been deleted.', 'restropress' ) ),
		'email_sent'                 => array( 'view_shop_reports', 'success', __( 'The order receipt has been resent.', 'restropress' ) ),
		'refreshed-reports'          => array( 'view_shop_reports', 'success', __( 'The reports have been refreshed.', 'restropress' ) ),
		'payment-note-deleted'       => array( 'view_shop_reports', 'success', __( 'The payment note has been deleted.', 'restropress' ) ),
		'pending_count_refreshed'    => array( 'view_shop_reports', 'success', __( 'Pending order count has been refreshed and recalculated.', 'restropress' ) ),
		'note-added'                 => array( 'edit_shop_payments', 'success', __( 'The payment note has been added successfully.', 'restropress' ) ),
		'payment-updated'            => array( 'edit_shop_payments', 'success', __( 'The order has been successfully updated.', 'restropress' ) ),
		// Settings and tools.
		'settings-imported'          => array( 'manage_shop_settings', 'success', __( 'The settings have been imported.', 'restropress' ) ),
		'api-key-generated'          => array( 'manage_shop_settings', 'success', __( 'API keys successfully generated.', 'restropress' ) ),
		'api-key-exists'             => array( 'manage_shop_settings', 'error', __( 'The specified user already has API keys.', 'restropress' ) ),
		'api-key-regenerated'        => array( 'manage_shop_settings', 'success', __( 'API keys successfully regenerated.', 'restropress' ) ),
		'api-key-revoked'            => array( 'manage_shop_settings', 'success', __( 'API keys successfully revoked.', 'restropress' ) ),
		// Customers.
		'customer-trashed'           => array( 'edit_shop_payments', 'success', __( 'Customer moved to trash', 'restropress' ) ),
		'customer-restored'          => array( 'edit_shop_payments', 'success', __( 'Customer restored from trash', 'restropress' ) ),
		'customer-deleted'           => array( 'edit_shop_payments', 'success', __( 'Customer successfully deleted', 'restropress' ) ),
		'user-verified'              => array( 'edit_shop_payments', 'success', __( 'User successfully verified', 'restropress' ) ),
		'email-added'                => array( 'edit_shop_payments', 'success', __( 'Customer email added', 'restropress' ) ),
		'email-removed'              => array( 'edit_shop_payments', 'success', __( 'Customer email removed', 'restropress' ) ),
		'email-remove-failed'        => array( 'edit_shop_payments', 'error', __( 'Failed to remove customer email', 'restropress' ) ),
		'primary-email-updated'      => array( 'edit_shop_payments', 'success', __( 'Primary email updated for customer', 'restropress' ) ),
		'primary-email-failed'       => array( 'edit_shop_payments', 'error', __( 'Failed to set primary email', 'restropress' ) ),
	);
	return apply_filters( 'rpress_admin_action_messages', $messages );
}

/**
 * Turn the current request's result parameters into toasts.
 */
function rpress_admin_toasts_from_request() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
	if ( isset( $_GET['rpress-message'] ) ) {
		$key      = sanitize_text_field( wp_unslash( $_GET['rpress-message'] ) );
		$messages = rpress_admin_action_messages();
		if ( isset( $messages[ $key ] ) && current_user_can( $messages[ $key ][0] ) ) {
			rpress_admin_toast( $messages[ $key ][2], $messages[ $key ][1] );
		}
	}
	if ( isset( $_GET['rpress_reorder_enabled'] ) ) {
		rpress_admin_toast( __( 'Reorder is on. Customers will see a Reorder button in their order history.', 'restropress' ) );
	}
	if ( isset( $_GET['rpress_order_alerts_enabled'] ) ) {
		rpress_admin_toast( __( 'Unattended-order email is on. New orders not accepted in time will be emailed to the admin notification recipients.', 'restropress' ) );
	}
	if ( ! empty( $_GET['rpress_res_updated'] ) && function_exists( 'rpress_reservation_statuses' ) ) {
		$status = sanitize_key( wp_unslash( $_GET['rpress_res_updated'] ) );
		$count  = isset( $_GET['rpress_res_count'] ) ? absint( $_GET['rpress_res_count'] ) : 0;
		$labels = rpress_reservation_statuses();
		if ( ! $count || ! isset( $labels[ $status ] ) ) {
			rpress_admin_toast( __( 'No reservations were changed.', 'restropress' ), 'info' );
		} else {
			rpress_admin_toast(
				sprintf(
					/* translators: 1: count, 2: status label */
					_n( '%1$d reservation marked %2$s.', '%1$d reservations marked %2$s.', $count, 'restropress' ),
					$count,
					strtolower( $labels[ $status ] )
				)
			);
		}
	}
	// phpcs:enable
}
add_action( 'admin_head', 'rpress_admin_toasts_from_request' );

/**
 * Back to the screen the notification was acted on from (the panel can be
 * opened anywhere), else the given fallback.
 *
 * @since 3.5.1
 * @param string $fallback Fallback URL.
 * @return string
 */
function rpress_admin_notice_return_url( $fallback ) {
	$referer = wp_get_referer();
	if ( $referer && 0 === strpos( $referer, admin_url() ) ) {
		return remove_query_arg( array( 'rpress-message', 'rpress_reorder_enabled', 'rpress_order_alerts_enabled' ), $referer );
	}
	return $fallback;
}

/**
 * W3 Total Cache database caching needs `_wp_session_` ignored.
 *
 * @return bool
 */
function rpress_w3tc_session_problem() {
	if ( ! function_exists( 'w3tc_pgcache_flush' ) || ! function_exists( 'w3_instance' ) ) {
		return false;
	}
	$config = w3_instance( 'W3_Config' );
	if ( ! $config || ! method_exists( $config, 'get_integer' ) ) {
		return false;
	}
	return (bool) $config->get_integer( 'dbcache.enabled' ) && ! in_array( '_wp_session_', (array) $config->get_array( 'dbcache.reject.sql' ), true );
}

add_action(
	'rpress_register_admin_notices',
	function () {
		rpress_register_admin_notice(
			'w3tc_session',
			array(
				'kind'      => 'problem',
				'title'     => __( 'W3 Total Cache setting needed', 'restropress' ),
				'message'   => __( 'For database caching to work with RestroPress, add _wp_session_ to the "Ignored query stems" option in W3 Total Cache.', 'restropress' ),
				'actions'   => array( array( 'label' => __( 'Open W3 Total Cache settings', 'restropress' ), 'url' => admin_url( 'admin.php?page=w3tc_dbcache' ), 'primary' => true ) ),
				'condition' => 'rpress_w3tc_session_problem',
				'screens'   => array( 'rpress-settings', 'rpress-dashboard' ),
				'priority'  => 8,
			)
		);
	}
);

/**
 * Drop the result parameters from the address bar once shown (WordPress does
 * this for its own), so a refresh does not repeat the toast.
 *
 * @param array $args Removable query args.
 * @return array
 */
function rpress_admin_removable_query_args( $args ) {
	return array_merge( $args, array( 'rpress-message', 'rpress_reorder_enabled', 'rpress_order_alerts_enabled', 'rpress_res_updated', 'rpress_res_count' ) );
}
add_filter( 'removable_query_args', 'rpress_admin_removable_query_args' );
