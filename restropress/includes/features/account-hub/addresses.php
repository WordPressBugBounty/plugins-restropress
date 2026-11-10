<?php
/**
 * Account hub: saved delivery addresses.
 *
 * Storage is the address book the customer dashboard already uses, so old and
 * new screens share one list:
 * - user meta `user_addresses`: array of addresses keyed by a stable index
 *   (first_name, last_name, phone, address, apt_suite, city, pincode,
 *   address_type (the label: Home, Work...), default_address).
 * - user meta `user_default_address_index`: index of the default address.
 * - user meta `_rpress_user_delivery_address`: the address checkout prefills
 *   (core); kept in sync with the default address.
 *
 * Every request handler works on the current user only: no handler accepts a
 * user ID, so a customer can never read or change another customer's book.
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Most addresses one customer can keep.
 *
 * @return int
 */
function rpress_account_hub_max_addresses() {
	return (int) apply_filters( 'rpress_account_hub_max_addresses', 20 );
}

/**
 * Normalize and sanitize one address.
 *
 * @param array $data Raw address data. Accepts the stored keys plus the
 *                    checkout/order keys (flat, postcode, label).
 * @return array
 */
function rpress_account_hub_sanitize_address( $data ) {
	$data = is_array( $data ) ? $data : array();

	// Order meta and checkout use flat/postcode; the book uses apt_suite/pincode.
	$aliases = array(
		'flat'     => 'apt_suite',
		'postcode' => 'pincode',
		'label'    => 'address_type',
	);
	foreach ( $aliases as $from => $to ) {
		if ( ! isset( $data[ $to ] ) && isset( $data[ $from ] ) ) {
			$data[ $to ] = $data[ $from ];
		}
	}

	$address = array();
	foreach ( array( 'first_name', 'last_name', 'phone', 'address', 'apt_suite', 'city', 'pincode', 'address_type' ) as $key ) {
		$value           = isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? (string) $data[ $key ] : '';
		$address[ $key ] = trim( sanitize_text_field( $value ) );
	}
	$address['address_type']    = '' !== $address['address_type'] ? substr( $address['address_type'], 0, 40 ) : __( 'Home', 'restropress' );
	$address['default_address'] = empty( $data['default_address'] ) ? 0 : 1;

	return $address;
}

/**
 * A customer's saved addresses, keyed by index, with an `is_default` flag.
 *
 * @param int $user_id User ID.
 * @return array
 */
function rpress_account_hub_get_addresses( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return array();
	}
	$stored = get_user_meta( $user_id, 'user_addresses', true );
	if ( ! is_array( $stored ) ) {
		return array();
	}
	$default   = rpress_account_hub_get_default_index( $user_id );
	$addresses = array();
	foreach ( $stored as $index => $address ) {
		if ( ! is_array( $address ) || ! is_numeric( $index ) ) {
			continue;
		}
		$address               = rpress_account_hub_sanitize_address( $address );
		$address['is_default'] = ( null !== $default && (int) $index === $default );
		$addresses[ (int) $index ] = $address;
	}
	return $addresses;
}

/**
 * Index of the default address, or null when there is none.
 *
 * @param int $user_id User ID.
 * @return int|null
 */
function rpress_account_hub_get_default_index( $user_id ) {
	$index  = get_user_meta( absint( $user_id ), 'user_default_address_index', true );
	$stored = get_user_meta( absint( $user_id ), 'user_addresses', true );
	if ( '' === $index || ! is_numeric( $index ) || ! is_array( $stored ) || ! isset( $stored[ (int) $index ] ) ) {
		return null;
	}
	return (int) $index;
}

/**
 * Key used to spot the same address saved twice.
 *
 * @param array $address Sanitized address.
 * @return string
 */
function rpress_account_hub_address_fingerprint( $address ) {
	$parts = array();
	foreach ( array( 'address', 'apt_suite', 'city', 'pincode' ) as $key ) {
		$value   = isset( $address[ $key ] ) ? (string) $address[ $key ] : '';
		$parts[] = preg_replace( '/\s+/', ' ', strtolower( trim( $value ) ) );
	}
	return implode( '|', $parts );
}

/**
 * Index of a saved address equal to $data, or null.
 *
 * @param int   $user_id User ID.
 * @param array $data    Address data.
 * @return int|null
 */
function rpress_account_hub_find_address( $user_id, $data ) {
	$needle = rpress_account_hub_address_fingerprint( rpress_account_hub_sanitize_address( $data ) );
	foreach ( rpress_account_hub_get_addresses( $user_id ) as $index => $address ) {
		if ( rpress_account_hub_address_fingerprint( $address ) === $needle ) {
			return (int) $index;
		}
	}
	return null;
}

/**
 * Write the raw address book (strips the computed is_default flag).
 *
 * @param int   $user_id   User ID.
 * @param array $addresses Addresses keyed by index.
 * @return void
 */
function rpress_account_hub_store_addresses( $user_id, $addresses ) {
	foreach ( $addresses as $index => $address ) {
		unset( $addresses[ $index ]['is_default'] );
	}
	update_user_meta( $user_id, 'user_addresses', $addresses );
}

/**
 * Add or update an address.
 *
 * Low level: no permission check. Request handlers call it with the current
 * user's ID only.
 *
 * @param int      $user_id User ID.
 * @param array    $data    Address data (street `address`, `city` and
 *                          `pincode` or `postcode`, optional `address_type`
 *                          or `label`, optional `default_address`).
 * @param int|null $index   Index to update, or null to add.
 * @return int|WP_Error Index of the saved address.
 */
function rpress_account_hub_save_address( $user_id, $data, $index = null ) {
	$user_id = absint( $user_id );
	if ( ! $user_id || ! get_userdata( $user_id ) ) {
		return new WP_Error( 'rpress_address_user', __( 'Please log in to save addresses.', 'restropress' ) );
	}
	$address = rpress_account_hub_sanitize_address( $data );
	if ( '' === $address['address'] || '' === $address['city'] ) {
		return new WP_Error( 'rpress_address_incomplete', __( 'Please enter the street address and city.', 'restropress' ) );
	}

	$addresses = rpress_account_hub_get_addresses( $user_id );

	if ( null === $index ) {
		$existing = rpress_account_hub_find_address( $user_id, $address );
		if ( null !== $existing ) {
			// Same place saved again: refresh its details instead of duplicating it.
			$index = $existing;
		} elseif ( count( $addresses ) >= rpress_account_hub_max_addresses() ) {
			return new WP_Error( 'rpress_address_limit', __( 'You have reached the maximum number of saved addresses. Delete one to add another.', 'restropress' ) );
		} else {
			$index = $addresses ? max( array_keys( $addresses ) ) + 1 : 0;
		}
	} else {
		$index = (int) $index;
		if ( ! isset( $addresses[ $index ] ) ) {
			return new WP_Error( 'rpress_address_missing', __( 'That address no longer exists.', 'restropress' ) );
		}
		// Keep the contact details already on file when an update leaves them out.
		foreach ( array( 'first_name', 'last_name', 'phone' ) as $key ) {
			if ( '' === $address[ $key ] && ! array_key_exists( $key, (array) $data ) ) {
				$address[ $key ] = $addresses[ $index ][ $key ];
			}
		}
	}

	// New first address, an explicit request, or the current default being edited.
	$make_default = ! empty( $address['default_address'] ) || ! $addresses || ! empty( $addresses[ $index ]['is_default'] );
	$address['default_address'] = $make_default ? 1 : 0;
	$addresses[ $index ]        = $address;
	rpress_account_hub_store_addresses( $user_id, $addresses );

	if ( $make_default ) {
		rpress_account_hub_set_default_address( $user_id, $index );
	}

	do_action( 'rpress_account_hub_address_saved', $user_id, $index, $address );
	return $index;
}

/**
 * Make an address the default (and the one checkout prefills).
 *
 * @param int $user_id User ID.
 * @param int $index   Address index.
 * @return bool
 */
function rpress_account_hub_set_default_address( $user_id, $index ) {
	$user_id   = absint( $user_id );
	$index     = (int) $index;
	$addresses = rpress_account_hub_get_addresses( $user_id );
	if ( ! isset( $addresses[ $index ] ) ) {
		return false;
	}
	foreach ( $addresses as $i => $address ) {
		$addresses[ $i ]['default_address'] = ( $i === $index ) ? 1 : 0;
	}
	rpress_account_hub_store_addresses( $user_id, $addresses );
	update_user_meta( $user_id, 'user_default_address_index', $index );

	$default = $addresses[ $index ];
	update_user_meta(
		$user_id,
		'_rpress_user_delivery_address',
		array(
			'address'  => $default['address'],
			'flat'     => $default['apt_suite'],
			'city'     => $default['city'],
			'postcode' => $default['pincode'],
		)
	);
	// Contact details travel with the address only when it has them.
	if ( '' !== $default['first_name'] ) {
		update_user_meta( $user_id, 'first_name', $default['first_name'] );
	}
	if ( '' !== $default['last_name'] ) {
		update_user_meta( $user_id, 'last_name', $default['last_name'] );
	}
	if ( '' !== $default['phone'] ) {
		update_user_meta( $user_id, '_rpress_phone', $default['phone'] );
	}
	return true;
}

/**
 * Delete an address. When it was the default, the first remaining address
 * becomes the default.
 *
 * @param int $user_id User ID.
 * @param int $index   Address index.
 * @return bool
 */
function rpress_account_hub_delete_address( $user_id, $index ) {
	$user_id   = absint( $user_id );
	$index     = (int) $index;
	$addresses = rpress_account_hub_get_addresses( $user_id );
	if ( ! isset( $addresses[ $index ] ) ) {
		return false;
	}
	$was_default = ! empty( $addresses[ $index ]['is_default'] );
	unset( $addresses[ $index ] );
	rpress_account_hub_store_addresses( $user_id, $addresses );

	if ( $was_default ) {
		delete_user_meta( $user_id, 'user_default_address_index' );
		if ( $addresses ) {
			$keys = array_keys( $addresses );
			rpress_account_hub_set_default_address( $user_id, reset( $keys ) );
		}
	}
	do_action( 'rpress_account_hub_address_deleted', $user_id, $index );
	return true;
}

/**
 * One-line summary of an address for lists and selects.
 *
 * @param array $address Address.
 * @return string
 */
function rpress_account_hub_format_address( $address ) {
	$street = trim( $address['address'] . ( '' !== $address['apt_suite'] ? ', ' . $address['apt_suite'] : '' ) );
	$place  = trim( $address['city'] . ' ' . $address['pincode'] );
	return implode( ', ', array_filter( array( $street, $place ) ) );
}

/**
 * Handle an address request for the CURRENT user.
 *
 * @param string $action  save | delete | set_default.
 * @param array  $request Request data (already unslashed or raw $_POST).
 * @return int|true|WP_Error
 */
function rpress_account_hub_handle_address_request( $action, $request ) {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return new WP_Error( 'rpress_address_login', __( 'Please log in to manage addresses.', 'restropress' ) );
	}
	$index = isset( $request['index'] ) && '' !== $request['index'] && is_numeric( $request['index'] ) ? (int) $request['index'] : null;

	switch ( $action ) {
		case 'save':
			return rpress_account_hub_save_address( $user_id, $request, $index );
		case 'delete':
			if ( null === $index || ! rpress_account_hub_delete_address( $user_id, $index ) ) {
				return new WP_Error( 'rpress_address_missing', __( 'That address no longer exists.', 'restropress' ) );
			}
			return true;
		case 'set_default':
			if ( null === $index || ! rpress_account_hub_set_default_address( $user_id, $index ) ) {
				return new WP_Error( 'rpress_address_missing', __( 'That address no longer exists.', 'restropress' ) );
			}
			return true;
	}
	return new WP_Error( 'rpress_address_action', __( 'Unknown address action.', 'restropress' ) );
}

/**
 * Dashboard AJAX: delete / set default. Replaces the core handlers (same
 * action names, nonce and response shape) so deleting the default address
 * promotes another one instead of leaving a stale default behind.
 */
function rpress_account_hub_ajax_delete_address() {
	check_ajax_referer( 'rpress-user-address', 'security' );
	$result = rpress_account_hub_handle_address_request( 'delete', array( 'index' => isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : '' ) );
	wp_send_json( array( 'success' => ! is_wp_error( $result ) ), is_user_logged_in() ? 200 : 403 );
}
add_action( 'wp_ajax_rpress_delete_user_address', 'rpress_account_hub_ajax_delete_address', 5 );

function rpress_account_hub_ajax_default_address() {
	check_ajax_referer( 'rpress-user-address', 'security' );
	$result = rpress_account_hub_handle_address_request( 'set_default', array( 'index' => isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : '' ) );
	wp_send_json( array( 'success' => ! is_wp_error( $result ) ), is_user_logged_in() ? 200 : 403 );
}
add_action( 'wp_ajax_rpress_default_user_address', 'rpress_account_hub_ajax_default_address', 5 );

/**
 * Dashboard forms (add/edit address, profile) post back to the page. Handle
 * them before the page renders, with a nonce, then redirect (post/redirect/get).
 * A post without a valid nonce is dropped so the template never acts on it.
 */
function rpress_account_hub_handle_dashboard_posts() {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
		return;
	}
	$is_address = isset( $_POST['submit_user_address'] );
	$is_profile = isset( $_POST['submit_profile_form'] );
	if ( ! $is_address && ! $is_profile ) {
		return;
	}
	$nonce = isset( $_POST['rpress_account_hub_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rpress_account_hub_nonce'] ) ) : '';
	if ( ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'rpress-account-hub' ) ) {
		unset( $_POST['submit_user_address'], $_POST['submit_profile_form'], $_REQUEST['submit_user_address'], $_REQUEST['submit_profile_form'] );
		return;
	}

	$notice = 'saved';
	if ( $is_address ) {
		$request          = wp_unslash( $_POST ); // Sanitized per field in rpress_account_hub_sanitize_address().
		$request['index'] = isset( $request['edit_user_address_index'] ) ? $request['edit_user_address_index'] : '';
		$result           = rpress_account_hub_handle_address_request( 'save', $request );
		$notice           = is_wp_error( $result ) ? $result->get_error_code() : 'address_saved';
	} else {
		$notice = rpress_account_hub_update_profile( get_current_user_id(), wp_unslash( $_POST ) );
	}

	// The dashboard forms post to their own page; wp_get_referer() is false
	// when the referer is the current URL, which sent customers home.
	$redirect = wp_validate_redirect( wp_get_raw_referer(), home_url( '/' ) );
	wp_safe_redirect( add_query_arg( 'rpress_account', rawurlencode( $notice ), remove_query_arg( 'rpress_account', $redirect ) ) );
	exit;
}
add_action( 'template_redirect', 'rpress_account_hub_handle_dashboard_posts', 5 );

/**
 * Update the current customer's profile from the dashboard form.
 *
 * @param int   $user_id User ID (always the current user).
 * @param array $data    Unslashed form data.
 * @return string Notice code.
 */
function rpress_account_hub_update_profile( $user_id, $data ) {
	$update = array( 'ID' => $user_id );
	$first  = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '';
	$last   = isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '';
	$email  = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
	$phone  = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
	if ( '' !== $first ) {
		$update['first_name'] = $first;
	}
	if ( '' !== $last ) {
		$update['last_name'] = $last;
	}
	$code = 'profile_saved';
	if ( '' !== $email && is_email( $email ) ) {
		$owner = email_exists( $email );
		if ( $owner && (int) $owner !== (int) $user_id ) {
			$code = 'email_in_use';
		} else {
			$update['user_email'] = $email;
		}
	}
	if ( count( $update ) > 1 ) {
		$result = wp_update_user( $update );
		if ( is_wp_error( $result ) ) {
			$code = 'profile_error';
		}
	}
	if ( '' !== $phone ) {
		update_user_meta( $user_id, '_rpress_phone', $phone );
	}
	return $code;
}

/**
 * Remember, on the order, that the customer asked to save its address.
 * Runs while the checkout request is being processed.
 *
 * @param int   $payment_id   Payment ID.
 * @param array $payment_data Payment data.
 */
function rpress_account_hub_flag_address_on_insert( $payment_id, $payment_data ) {
	if ( empty( $_POST['rpress_save_address'] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification -- checkout nonce is verified by core.
		return;
	}
	$order_user = isset( $payment_data['user_info']['id'] ) ? absint( $payment_data['user_info']['id'] ) : 0;
	if ( $order_user !== get_current_user_id() || 'delivery' !== rpress_selected_service() ) {
		return;
	}
	$label = isset( $_POST['rpress_save_address_label'] ) ? sanitize_text_field( wp_unslash( $_POST['rpress_save_address_label'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	update_post_meta( $payment_id, '_rpress_save_address', '' !== $label ? $label : __( 'Home', 'restropress' ) );

	// Free orders and gateways that complete instantly never change status again.
	if ( in_array( get_post_status( $payment_id ), rpress_account_hub_address_save_statuses(), true ) ) {
		rpress_account_hub_save_address_from_payment( $payment_id );
	}
}
add_action( 'rpress_insert_payment', 'rpress_account_hub_flag_address_on_insert', 20, 2 );

/**
 * Payment statuses that count as a placed order: paid, or accepted for
 * offline payment (cash on delivery moves to processing).
 *
 * @return array
 */
function rpress_account_hub_address_save_statuses() {
	return (array) apply_filters( 'rpress_account_hub_address_save_statuses', array( 'publish', 'complete', 'processing' ) );
}

/**
 * Save the flagged address once the order went through.
 *
 * @param int    $payment_id Payment ID.
 * @param string $new_status New status.
 */
function rpress_account_hub_save_address_on_status( $payment_id, $new_status ) {
	if ( in_array( $new_status, rpress_account_hub_address_save_statuses(), true ) ) {
		rpress_account_hub_save_address_from_payment( $payment_id );
	}
}
add_action( 'rpress_update_payment_status', 'rpress_account_hub_save_address_on_status', 20, 2 );

/**
 * Copy an order's delivery address into its customer's address book when the
 * order was flagged at checkout. Runs once per order.
 *
 * @param int $payment_id Payment ID.
 * @return int|false Saved index, or false.
 */
function rpress_account_hub_save_address_from_payment( $payment_id ) {
	$label = get_post_meta( $payment_id, '_rpress_save_address', true );
	if ( '' === $label || false === $label ) {
		return false;
	}
	delete_post_meta( $payment_id, '_rpress_save_address' );

	$user_id  = absint( rpress_get_payment_user_id( $payment_id ) );
	$delivery = get_post_meta( $payment_id, '_rpress_delivery_address', true );
	if ( ! $user_id || ! is_array( $delivery ) || empty( $delivery['address'] ) ) {
		return false;
	}
	$user_info = rpress_get_payment_meta_user_info( $payment_id );
	$data      = array(
		'address'    => isset( $delivery['address'] ) ? $delivery['address'] : '',
		'flat'       => isset( $delivery['flat'] ) ? $delivery['flat'] : '',
		'city'       => isset( $delivery['city'] ) ? $delivery['city'] : '',
		'postcode'   => isset( $delivery['postcode'] ) ? $delivery['postcode'] : '',
		'label'      => $label,
		'first_name' => isset( $user_info['first_name'] ) ? $user_info['first_name'] : '',
		'last_name'  => isset( $user_info['last_name'] ) ? $user_info['last_name'] : '',
		'phone'      => (string) get_user_meta( $user_id, '_rpress_phone', true ),
	);
	$index = rpress_account_hub_save_address( $user_id, $data );
	return is_wp_error( $index ) ? false : $index;
}
