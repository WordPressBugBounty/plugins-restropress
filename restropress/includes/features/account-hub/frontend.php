<?php
/**
 * Account hub: storefront output (checkout address picker, tracking links,
 * dashboard hooks) and assets.
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL of an order's receipt, which shows the live order status.
 *
 * @param int $payment_id Payment ID.
 * @return string
 */
function rpress_account_hub_order_receipt_url( $payment_id ) {
	$key = rpress_get_payment_key( $payment_id );
	if ( empty( $key ) ) {
		return '';
	}
	$page = absint( rpress_get_option( 'success_page', 0 ) );
	$url  = $page ? get_permalink( $page ) : '';
	if ( ! $url ) {
		return '';
	}
	return (string) apply_filters( 'rpress_account_hub_order_receipt_url', add_query_arg( 'payment_key', rawurlencode( $key ), $url ), $payment_id );
}

/**
 * Is this order still on its way (worth tracking live)?
 *
 * @param int $payment_id Payment ID.
 * @return bool
 */
function rpress_account_hub_order_is_live( $payment_id ) {
	$status = sanitize_key( (string) get_post_meta( $payment_id, '_order_status', true ) );
	$model  = function_exists( 'rpress_order_status_model' ) ? rpress_order_status_model() : array();
	$phase  = isset( $model[ $status ]['phase'] ) ? $model[ $status ]['phase'] : 'pending';
	if ( in_array( get_post_status( $payment_id ), array( 'failed', 'refunded', 'revoked', 'abandoned' ), true ) ) {
		return false;
	}
	return ! in_array( $phase, array( 'delivered', 'cancelled' ), true );
}

/**
 * "Track order" (live orders) or "Receipt" link for an order card.
 *
 * @param int $payment_id Payment ID.
 */
function rpress_account_hub_tracking_link( $payment_id ) {
	$url = rpress_account_hub_order_receipt_url( $payment_id );
	if ( ! $url ) {
		return;
	}
	$live = rpress_account_hub_order_is_live( $payment_id );
	printf(
		'<a href="%1$s" class="rpress-track-order-btn%2$s">%3$s</a>',
		esc_url( $url ),
		$live ? ' is-live' : '',
		$live ? esc_html__( 'Track order', 'restropress' ) : esc_html__( 'Receipt', 'restropress' )
	);
}
add_action( 'rpress_order_after_view_details', 'rpress_account_hub_tracking_link', 10, 1 );
add_action( 'rpress_order_details_modal_actions', 'rpress_account_hub_tracking_link', 10, 1 );

/**
 * Order actions in the customer dashboard's Orders tab: tracking link and,
 * when enabled, Reorder.
 *
 * @param int $payment_id Payment ID.
 */
function rpress_account_hub_dashboard_order_actions( $payment_id ) {
	rpress_account_hub_tracking_link( $payment_id );
	if ( rpress_account_hub_reorder_enabled() ) {
		printf(
			'<a href="#" class="rpress-reorder-btn" data-order-id="%1$s"><span class="rp-ajax-toggle-text">%2$s</span></a>',
			esc_attr( $payment_id ),
			esc_html__( 'Reorder', 'restropress' )
		);
	}
}
add_action( 'rpress_user_dashboard_order_actions', 'rpress_account_hub_dashboard_order_actions' );

/**
 * Nonce for the dashboard's address and profile forms.
 */
function rpress_account_hub_form_nonce() {
	wp_nonce_field( 'rpress-account-hub', 'rpress_account_hub_nonce', false );
}
add_action( 'rpress_user_dashboard_address_form_fields', 'rpress_account_hub_form_nonce' );
add_action( 'rpress_user_dashboard_profile_form_fields', 'rpress_account_hub_form_nonce' );

/**
 * Result message after a dashboard form redirect.
 */
function rpress_account_hub_dashboard_notice() {
	$code = isset( $_GET['rpress_account'] ) ? sanitize_key( wp_unslash( $_GET['rpress_account'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
	if ( '' === $code ) {
		return;
	}
	$messages = array(
		'address_saved'             => array( 'success', __( 'Address saved.', 'restropress' ) ),
		'profile_saved'             => array( 'success', __( 'Profile updated.', 'restropress' ) ),
		'email_in_use'              => array( 'error', __( 'That email address belongs to another account.', 'restropress' ) ),
		'profile_error'             => array( 'error', __( 'Your profile could not be updated. Please try again.', 'restropress' ) ),
		'rpress_address_incomplete' => array( 'error', __( 'Please enter the street address and city.', 'restropress' ) ),
		'rpress_address_limit'      => array( 'error', __( 'You have reached the maximum number of saved addresses. Delete one to add another.', 'restropress' ) ),
		'rpress_address_missing'    => array( 'error', __( 'That address no longer exists.', 'restropress' ) ),
	);
	if ( ! isset( $messages[ $code ] ) ) {
		return;
	}
	printf(
		'<div class="rpress-account-notice rpress-account-notice-%1$s" role="status">%2$s</div>',
		esc_attr( $messages[ $code ][0] ),
		esc_html( $messages[ $code ][1] )
	);
}
add_action( 'rpress_user_dashboard_before', 'rpress_account_hub_dashboard_notice' );

/**
 * Checkout: saved address picker above the delivery address fields.
 */
function rpress_account_hub_checkout_address_picker() {
	if ( ! is_user_logged_in() || 'delivery' !== sanitize_key( (string) rpress_selected_service() ) ) {
		return;
	}
	$addresses = rpress_account_hub_get_addresses( get_current_user_id() );
	if ( ! $addresses ) {
		return;
	}

	// Preselect the saved address that matches what checkout prefilled, else
	// the default when the fields are empty.
	$fields  = function_exists( 'rp_get_checkout_fields' ) ? rp_get_checkout_fields() : array();
	$current = array(
		'address'   => isset( $fields['rpress_street_address']['value'] ) ? $fields['rpress_street_address']['value'] : '',
		'apt_suite' => isset( $fields['rpress_apt_suite']['value'] ) ? $fields['rpress_apt_suite']['value'] : '',
		'city'      => isset( $fields['rpress_city']['value'] ) ? $fields['rpress_city']['value'] : '',
		'pincode'   => isset( $fields['rpress_postcode']['value'] ) ? $fields['rpress_postcode']['value'] : '',
	);
	$selected = '';
	if ( '' === implode( '', $current ) ) {
		$default  = rpress_account_hub_get_default_index( get_current_user_id() );
		$selected = null === $default ? '' : (string) $default;
	} else {
		$match    = rpress_account_hub_find_address( get_current_user_id(), $current );
		$selected = null === $match ? '' : (string) $match;
	}
	?>
	<div class="rp-col-sm-12 rpress-saved-addresses">
		<label for="rpress-saved-address"><?php esc_html_e( 'Deliver to', 'restropress' ); ?></label>
		<select id="rpress-saved-address" class="rpress-input rpress-saved-address-select">
			<?php foreach ( $addresses as $index => $address ) : ?>
				<option value="<?php echo esc_attr( $index ); ?>"
					data-address="<?php echo esc_attr( $address['address'] ); ?>"
					data-flat="<?php echo esc_attr( $address['apt_suite'] ); ?>"
					data-city="<?php echo esc_attr( $address['city'] ); ?>"
					data-postcode="<?php echo esc_attr( $address['pincode'] ); ?>"
					<?php selected( $selected, (string) $index ); ?>>
					<?php
					echo esc_html( $address['address_type'] . ': ' . rpress_account_hub_format_address( $address ) );
					if ( $address['is_default'] ) {
						echo ' ' . esc_html__( '(default)', 'restropress' );
					}
					?>
				</option>
			<?php endforeach; ?>
			<option value="" <?php selected( $selected, '' ); ?>><?php esc_html_e( 'Use a new address', 'restropress' ); ?></option>
		</select>
	</div>
	<?php
}
add_action( 'rpress_purchase_form_before_order_details', 'rpress_account_hub_checkout_address_picker' );

/**
 * Checkout: "Save this address" option below the delivery fields.
 */
function rpress_account_hub_checkout_save_option() {
	if ( ! is_user_logged_in() || 'delivery' !== sanitize_key( (string) rpress_selected_service() ) ) {
		return;
	}
	$has_saved = (bool) rpress_account_hub_get_addresses( get_current_user_id() );
	$labels    = apply_filters(
		'rpress_account_hub_address_labels',
		array(
			__( 'Home', 'restropress' ),
			__( 'Work', 'restropress' ),
			__( 'Other', 'restropress' ),
		)
	);
	?>
	<div class="rp-col-sm-12 rpress-save-address-wrap">
		<label class="rpress-save-address-toggle" for="rpress-save-address">
			<input type="checkbox" name="rpress_save_address" id="rpress-save-address" value="1" <?php checked( ! $has_saved ); ?> />
			<span><?php esc_html_e( 'Save this address for next time', 'restropress' ); ?></span>
		</label>
		<label class="screen-reader-text" for="rpress-save-address-label"><?php esc_html_e( 'Address label', 'restropress' ); ?></label>
		<select name="rpress_save_address_label" id="rpress-save-address-label" class="rpress-input rpress-save-address-label">
			<?php foreach ( (array) $labels as $label ) : ?>
				<option value="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
}
add_action( 'rpress_purchase_form_order_details_fields', 'rpress_account_hub_checkout_save_option' );

/**
 * Does the current page show the customer dashboard or order history?
 *
 * @return string '' | 'dashboard' | 'history'
 */
function rpress_account_hub_page_type() {
	$post = get_post();
	if ( ! $post || ! is_singular() ) {
		return '';
	}
	if ( has_shortcode( $post->post_content, 'customer_dashboard' ) ) {
		return 'dashboard';
	}
	if ( has_shortcode( $post->post_content, 'order_history' ) || has_block( 'restropress/order-history', $post ) ) {
		return 'history';
	}
	return '';
}

/**
 * Enqueue the account hub assets only where they are used.
 */
function rpress_account_hub_enqueue_assets() {
	$on_checkout = function_exists( 'rpress_is_checkout' ) && rpress_is_checkout() && is_user_logged_in();
	$page_type   = rpress_account_hub_page_type();
	if ( ! $on_checkout && '' === $page_type ) {
		return;
	}
	$css = 'assets/features/account-hub/account-hub.css';
	$js  = 'assets/features/account-hub/account-hub.js';
	wp_enqueue_style( 'rpress-account-hub', RP_PLUGIN_URL . $css, array(), RP_VERSION . '.' . (int) @filemtime( RP_PLUGIN_DIR . $css ) );
	if ( $on_checkout ) {
		wp_enqueue_script( 'rpress-account-hub', RP_PLUGIN_URL . $js, array( 'jquery' ), RP_VERSION . '.' . (int) @filemtime( RP_PLUGIN_DIR . $js ), true );
	}
	if ( 'dashboard' === $page_type && rpress_account_hub_reorder_enabled() && wp_script_is( 'rp-reorder', 'registered' ) ) {
		wp_enqueue_script( 'rp-reorder' );
	}
}
add_action( 'wp_enqueue_scripts', 'rpress_account_hub_enqueue_assets', 20 );
