<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Processes a custom edit
 *
 * @since  1.0.0
 * @param  array $args The $_POST array being passeed
 * @return array $output Response messages
 */
function rpress_edit_customer( $args ) {
	$customer_edit_role = apply_filters( 'rpress_edit_customers_role', 'edit_shop_payments' );
	if ( ! is_admin() || ! current_user_can( $customer_edit_role ) ) {
		wp_die( esc_html__( 'You do not have permission to edit this customer.', 'restropress' ) );
	}
	if ( empty( $args ) ) {
		return;
	}
	$customer_info = $args['customerinfo'];
	$customer_id   = (int)$args['customerinfo']['id'];
	$nonce         = $args['_wpnonce'];
	if ( ! wp_verify_nonce( $nonce, 'edit-customer' ) ) {
		wp_die( esc_html__( 'Cheatin\' eh?!', 'restropress' ) );
	}
	$customer = new RPRESS_Customer( $customer_id );
	if ( empty( $customer->id ) ) {
		return false;
	}
	$defaults = array(
		'name'    => '',
		'email'   => '',
		'user_id' => 0
	);
	$customer_info = wp_parse_args( $customer_info, $defaults );
	if ( ! is_email( $customer_info['email'] ) ) {
		rpress_set_error( 'rpress-invalid-email', esc_html__( 'Please enter a valid email address.', 'restropress' ) );
	}
	if ( (int) $customer_info['user_id'] != (int) $customer->user_id ) {
		// Make sure we don't already have this user attached to a customer
		if ( ! empty( $customer_info['user_id'] ) && false !== RPRESS()->customers->get_customer_by( 'user_id', $customer_info['user_id'] ) ) {
			rpress_set_error( 'rpress-invalid-customer-user_id', /* translators: 1: text, 2: admin url */
				sprintf( esc_html__( 'The User ID %d is already associated with a different customer.', 'restropress' ), $customer_info['user_id'] ) );
		}
		// Make sure it's actually a user
		$user = get_user_by( 'id', $customer_info['user_id'] );
		if ( ! empty( $customer_info['user_id'] ) && false === $user ) {
			rpress_set_error( 'rpress-invalid-user_id', /* translators: 1: text, 2: admin url */
				sprintf( esc_html__( 'The User ID %d does not exist. Please assign an existing user.', 'restropress' ), $customer_info['user_id'] ) );
		}
	}
	// Record this for later
	$previous_user_id  = $customer->user_id;
	if ( rpress_get_errors() ) {
		return;
	}
	$user_id = intval( $customer_info['user_id'] );
	if ( empty( $user_id ) && ! empty( $customer_info['user_login'] ) ) {
		// See if they gave an email, otherwise we'll assume login
		$user_by_field = 'login';
		if ( is_email( $customer_info['user_login'] ) ) {
			$user_by_field = 'email';
		}
		$user = get_user_by( $user_by_field, $customer_info['user_login'] );
		if ( $user ) {
			$user_id = $user->ID;
		} else {
			rpress_set_error( 'rpress-invalid-user-string', /* translators: 1: text, 2: admin url */
				sprintf( esc_html__( 'Failed to attach user. The login or email address %s was not found.', 'restropress' ), $customer_info['user_login'] ) );
		}
	}
	// Setup the customer address, if present
	$address = array();
	if ( ! empty( $user_id ) ) {
		$current_address = get_user_meta( $customer_info['user_id'], '_rpress_user_address', true );
		if ( empty( $current_address ) ) {
			$address['line1']   = isset( $customer_info['line1'] )   ? $customer_info['line1']   : '';
			$address['line2']   = isset( $customer_info['line2'] )   ? $customer_info['line2']   : '';
			$address['city']    = isset( $customer_info['city'] )    ? $customer_info['city']    : '';
			$address['country'] = isset( $customer_info['country'] ) ? $customer_info['country'] : '';
			$address['zip']     = isset( $customer_info['zip'] )     ? $customer_info['zip']     : '';
			$address['state']   = isset( $customer_info['state'] )   ? $customer_info['state']   : '';
		} else {
			$current_address    = wp_parse_args( $current_address, array( 'line1', 'line2', 'city', 'zip', 'state', 'country' ) );
			$address['line1']   = isset( $customer_info['line1'] )   ? $customer_info['line1']   : $current_address['line1']  ;
			$address['line2']   = isset( $customer_info['line2'] )   ? $customer_info['line2']   : $current_address['line2']  ;
			$address['city']    = isset( $customer_info['city'] )    ? $customer_info['city']    : $current_address['city']   ;
			$address['country'] = isset( $customer_info['country'] ) ? $customer_info['country'] : $current_address['country'];
			$address['zip']     = isset( $customer_info['zip'] )     ? $customer_info['zip']     : $current_address['zip']    ;
			$address['state']   = isset( $customer_info['state'] )   ? $customer_info['state']   : $current_address['state']  ;
		}
	}
	// Sanitize the inputs
	$customer_data            = array();
	$customer_data['name']    = strip_tags( stripslashes( $customer_info['name'] ) );
	$customer_data['email']   = sanitize_email($customer_info['email']);
	$customer_data['user_id'] = $user_id;
	$customer_data = apply_filters( 'rpress_edit_customer_info', $customer_data, $customer_id );
	$address       = apply_filters( 'rpress_edit_customer_address', $address, $customer_id );
	$customer_data = array_map( 'sanitize_text_field', $customer_data );
	$address       = array_map( 'sanitize_text_field', $address );
	do_action( 'rpress_pre_edit_customer', $customer_id, $customer_data, $address );
	$output         = array();
	$previous_email = $customer->email;
	if ( $customer->update( $customer_data ) ) {
		if ( ! empty( $customer->user_id ) && $customer->user_id > 0 ) {
			update_user_meta( $customer->user_id, '_rpress_user_address', $address );
		}
		// Update some payment meta if we need to
		$payments_array = explode( ',', $customer->payment_ids );
		if ( $customer->email != $previous_email ) {
			foreach ( $payments_array as $payment_id ) {
				rpress_update_payment_meta( $payment_id, 'email', $customer->email );
			}
		}
		if ( $customer->user_id != $previous_user_id ) {
			foreach ( $payments_array as $payment_id ) {
				rpress_update_payment_meta( $payment_id, '_rpress_payment_user_id', $customer->user_id );
			}
		}
		$output['success']       = true;
		$customer_data           = array_merge( $customer_data, $address );
		$output['customer_info'] = $customer_data;
	} else {
		$output['success'] = false;
	}
	do_action( 'rpress_post_edit_customer', $customer_id, $customer_data );
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		header( 'Content-Type: application/json' );
		echo json_encode( $output );
		wp_die();
	}
	return $output;
}
add_action( 'rpress_edit-customer', 'rpress_edit_customer', 10, 1 );
/**
 * Add an email address to the customer from within the admin and log a customer note
 *
 * @since  1.0.0
 * @param  array $args  Array of arguments: nonce, customer id, and email address
 * @return mixed        If DOING_AJAX echos out JSON, otherwise returns array of success (bool) and message (string)
 */
function rpress_add_customer_email( $args ) {
	$customer_edit_role = apply_filters( 'rpress_edit_customers_role', 'edit_shop_payments' );
	if ( ! is_admin() || ! current_user_can( $customer_edit_role ) ) {
		wp_die( esc_html__( 'You do not have permission to edit this customer.', 'restropress' ) );
	}
	$output = array();
	if ( empty( $args ) || empty( $args['email'] ) || empty( $args['customer_id'] ) ) {
		$output['success'] = false;
		if ( empty( $args['email'] ) ) {
			$output['message'] = esc_html__( 'Email address is required.', 'restropress' );
		} else if ( empty( $args['customer_id'] ) ) {
			$output['message'] = esc_html__( 'Customer ID is required.', 'restropress' );
		} else {
			$output['message'] = esc_html__( 'An error has occured. Please try again.', 'restropress' );
		}
	} else if ( ! wp_verify_nonce( $args['_wpnonce'], 'rpress-add-customer-email' ) ) {
		$output = array(
			'success' => false,
			'message' => esc_html__( 'Nonce verification failed.', 'restropress' ),
		);
	} else if ( ! is_email( $args['email'] ) ) {
		$output = array(
			'success' => false,
			'message' => esc_html__( 'Invalid email address.', 'restropress' ),
		);
	} else {
		$email       = sanitize_email( $args['email'] );
		$customer_id = (int) $args['customer_id'];
		$primary     = 'true' === $args['primary'] ? true : false;
		$customer    = new RPRESS_Customer( $customer_id );
		if ( false === $customer->add_email( $email, $primary ) ) {
			if ( in_array( $email, $customer->emails ) ) {
				$output = array(
					'success'  => false,
					'message'  => esc_html__( 'Email already associated with this customer.', 'restropress' ),
				);
			} else {
				$output = array(
					'success' => false,
					'message' => esc_html__( 'Email address is already associated with another customer.', 'restropress' ),
				);
			}
		} else {
			$redirect = admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . $customer_id . '&rpress-message=email-added' );
			$output = array(
				'success'  => true,
				'message'  => esc_html__( 'Email successfully added to customer.', 'restropress' ),
				'redirect' => $redirect,
			);
			$user          = wp_get_current_user();
			$user_login    = ! empty( $user->user_login ) ? $user->user_login : 'RPRESSBot';
			/* translators: 1: text, 2: admin url */
			$customer_note = sprintf( esc_html__( 'Email address %1$s added by %2$s', 'restropress' ), $email, $user_login );
			$customer->add_note( $customer_note );
			if ( $primary ) {
				/* translators: 1: text, 2: admin url */
				$customer_note =  sprintf( esc_html__( 'Email address %1$s set as primary by %2$s', 'restropress' ), $email, $user_login );
				$customer->add_note( $customer_note );
			}
		}
	}
	do_action( 'rpress_post_add_customer_email', $customer_id, $args );
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		header( 'Content-Type: application/json' );
		echo json_encode( $output );
		wp_die();
	}
	return $output;
}
add_action( 'rpress_customer-add-email', 'rpress_add_customer_email', 10, 1 );
/**
 * Remove an email address to the customer from within the admin and log a customer note
 * and redirect back to the customer interface for feedback
 *
 * @since  1.0.0
 * @return void
 */
function rpress_remove_customer_email() {
	if ( empty( $_GET['id'] ) || ! is_numeric( $_GET['id'] ) ) {
		return false;
	}
	if ( empty( $_GET['email'] ) || ! is_email( $_GET['email'] ) ) {
		return false;
	}
	if ( empty( $_GET['_wpnonce'] ) ) {
		return false;
	}
	$nonce = sanitize_text_field( $_GET['_wpnonce'] );
	if ( ! wp_verify_nonce( $nonce, 'rpress-remove-customer-email' ) ) {
		wp_die( esc_html__( 'Nonce verification failed', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}
	$customer = new RPRESS_Customer( absint( $_GET['id'] ) );
	if ( $customer->remove_email( sanitize_email( $_GET['email'] ) ) ) {
		$url = add_query_arg( 'rpress-message', 'email-removed', admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . $customer->id ) );
		$user          = wp_get_current_user();
		$user_login    = ! empty( $user->user_login ) ? $user->user_login : 'RPRESSBot';
		$customer_note = sprintf( 
		 /* translators: 1: text, 2: admin url */
			esc_html__( 'Email address %1$s removed by %2$s', 'restropress' ), sanitize_email( $_GET['email'] ), $user_login );
		$customer->add_note( $customer_note );
	} else {
		$url = add_query_arg( 'rpress-message', 'email-remove-failed', admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . $customer->id ) );
	}
	wp_safe_redirect( $url );
	exit;
}
add_action( 'rpress_customer-remove-email', 'rpress_remove_customer_email', 10 );
/**
 * Set an email address as the primary for a customer from within the admin and log a customer note
 * and redirect back to the customer interface for feedback
 *
 * @since  1.0.0
 * @return void
 */
function rpress_set_customer_primary_email() {
	if ( empty( $_GET['id'] ) || ! is_numeric( $_GET['id'] ) ) {
		return false;
	}
	if ( empty( $_GET['email'] ) || ! is_email( $_GET['email'] ) ) {
		return false;
	}
	if ( empty( $_GET['_wpnonce'] ) ) {
		return false;
	}
	$nonce = sanitize_text_field( $_GET['_wpnonce'] );
	if ( ! wp_verify_nonce( $nonce, 'rpress-set-customer-primary-email' ) ) {
		wp_die( esc_html__( 'Nonce verification failed', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}
	$customer = new RPRESS_Customer( absint( $_GET['id'] ) );
	if ( $customer->set_primary_email( sanitize_email( $_GET['email'] ) ) ){
		$url = add_query_arg( 'rpress-message', 'primary-email-updated', admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . $customer->id ) );
			/* translators: 1: text, 2: admin url */
		$user          = wp_get_current_user();
		$user_login    = ! empty( $user->user_login ) ? $user->user_login : 'RPRESSBot';
		$customer_note = sprintf
		 /* translators: 1: text, 2: admin url */
		( esc_html__( 'Email address %1$s set as primary by %2$s', 'restropress' ), sanitize_email( $_GET['email'] ), $user_login );
		$customer->add_note( $customer_note );
	} else {
		$url = add_query_arg( 'rpress-message', 'primary-email-failed', admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . $customer->id ) );
	}
	wp_safe_redirect( $url );
	exit;
}
add_action( 'rpress_customer-primary-email', 'rpress_set_customer_primary_email', 10 );
/**
 * Save a customer note being added
 *
 * @since  1.0.0
 * @param  array $args The $_POST array being passeed
 * @return int         The Note ID that was saved, or 0 if nothing was saved
 */
function rpress_customer_save_note( $args ) {
	$customer_view_role = apply_filters( 'rpress_view_customers_role', 'view_shop_reports' );
	if ( ! is_admin() || ! current_user_can( $customer_view_role ) ) {
		wp_die( esc_html__( 'You do not have permission to edit this customer.', 'restropress' ) );
	}
	if ( empty( $args ) ) {
		return;
	}
	$customer_note = trim( sanitize_text_field( $args['customer_note'] ) );
	$customer_id   = (int)$args['customer_id'];
	$nonce         = $args['add_customer_note_nonce'];
	if ( ! wp_verify_nonce( $nonce, 'add-customer-note' ) ) {
		wp_die( esc_html__( 'Cheatin\' eh?!', 'restropress' ) );
	}
	if ( empty( $customer_note ) ) {
		rpress_set_error( 'empty-customer-note', esc_html__( 'A note is required', 'restropress' ) );
	}
	if ( rpress_get_errors() ) {
		return;
	}
	$customer = new RPRESS_Customer( $customer_id );
	$new_note = $customer->add_note( $customer_note );
	do_action( 'rpress_pre_insert_customer_note', $customer_id, $new_note );
	if ( ! empty( $new_note ) && ! empty( $customer->id ) ) {
		ob_start();
		?>
		<div class="customer-note-wrapper dashboard-comment-wrap comment-item">
			<span class="note-content-wrap">
				<?php echo stripslashes( $new_note ); ?>
			</span>
		</div>
		<?php
		$output = ob_get_contents();
		ob_end_clean();
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			echo wp_kses_data( $output );
			exit;
		}
		return $new_note;
	}
	return false;
}
add_action( 'rpress_add-customer-note', 'rpress_customer_save_note', 10, 1 );
/**
 * Delete a customer
 *
 * @since  1.0.0
 * @param  array $args The $_POST array being passeed
 * @return int         Wether it was a successful deletion
 */
function rpress_customer_delete( $args ) {
	$customer_edit_role = apply_filters( 'rpress_edit_customers_role', 'edit_shop_payments' );
	if ( ! is_admin() || ! current_user_can( $customer_edit_role ) ) {
		wp_die( esc_html__( 'You do not have permission to delete this customer.', 'restropress' ) );
	}
	if ( empty( $args ) ) {
		return;
	}
	$customer_id   = (int)$args['customer_id'];
	$confirm       = ! empty( $args['rpress-customer-delete-confirm'] ) ? true : false;
	$remove_data   = ! empty( $args['rpress-customer-delete-records'] ) ? true : false;
	$nonce         = $args['_wpnonce'];
	if ( ! wp_verify_nonce( $nonce, 'delete-customer' ) ) {
		wp_die( esc_html__( 'Cheatin\' eh?!', 'restropress' ) );
	}
	if ( ! $confirm ) {
		rpress_set_error( 'customer-delete-no-confirm', esc_html__( 'Please confirm you want to delete this customer', 'restropress' ) );
	}
	if ( rpress_get_errors() ) {
		wp_redirect( admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . $customer_id ) );
		exit;
	}
	$customer = new RPRESS_Customer( $customer_id );
	do_action( 'rpress_pre_delete_customer', $customer_id, $confirm, $remove_data );
	$success = false;
	if ( $customer->id > 0 ) {
		$payments_array = explode( ',', $customer->payment_ids );
		$success        = RPRESS()->customers->delete( $customer->id );
		if ( $success ) {
			if ( $remove_data ) {
				// Remove all payments, logs, etc
				foreach ( $payments_array as $payment_id ) {
					rpress_delete_purchase( $payment_id, false, true );
				}
			} else {
				// Just set the payments to customer_id of 0
				foreach ( $payments_array as $payment_id ) {
					rpress_update_payment_meta( $payment_id, '_rpress_payment_customer_id', 0 );
				}
			}
			$redirect = admin_url( 'admin.php?page=rpress-customers&rpress-message=customer-deleted' );
		} else {
			rpress_set_error( 'rpress-customer-delete-failed', esc_html__( 'Error deleting customer', 'restropress' ) );
			$redirect = admin_url( 'admin.php?page=rpress-customers&view=delete&id=' . $customer_id );
		}
	} else {
		rpress_set_error( 'rpress-customer-delete-invalid-id', esc_html__( 'Invalid Customer ID', 'restropress' ) );
		$redirect = admin_url( 'admin.php?page=rpress-customers' );
	}
	wp_redirect( $redirect );
	exit;
}
add_action( 'rpress_delete-customer', 'rpress_customer_delete', 10, 1 );
/**
 * Disconnect a user ID from a customer
 *
 * @since  1.0.0
 * @param  array $args Array of arguments
 * @return bool        If the disconnect was sucessful
 */
function rpress_disconnect_customer_user_id( $args ) {
	$customer_edit_role = apply_filters( 'rpress_edit_customers_role', 'edit_shop_payments' );
	if ( ! is_admin() || ! current_user_can( $customer_edit_role ) ) {
		wp_die( esc_html__( 'You do not have permission to edit this customer.', 'restropress' ) );
	}
	if ( empty( $args ) ) {
		return;
	}
	$customer_id   = ( int )$args['customer_id'];
	$nonce         = $args['_wpnonce'];
	if ( ! wp_verify_nonce( $nonce, 'edit-customer' ) ) {
		wp_die( esc_html__( 'Cheatin\' eh?!', 'restropress' ) );
	}
	$customer = new RPRESS_Customer( $customer_id );
	if ( empty( $customer->id ) ) {
		return false;
	}
	do_action( 'rpress_pre_customer_disconnect_user_id', $customer_id, $customer->user_id );
	$customer_args = array( 'user_id' => 0 );
	if ( $customer->update( $customer_args ) ) {
		global $wpdb;
		if ( ! empty( $customer->payment_ids ) ) {
			$wpdb->query( "UPDATE $wpdb->postmeta SET meta_value = 0 WHERE meta_key = '_rpress_payment_user_id' AND post_id IN ( $customer->payment_ids )" );
		}
		$output['success'] = true;
	} else {
		$output['success'] = false;
		rpress_set_error( 'rpress-disconnect-user-fail', esc_html__( 'Failed to disconnect user from customer', 'restropress' ) );
	}
	do_action( 'rpress_post_customer_disconnect_user_id', $customer_id );
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		header( 'Content-Type: application/json' );
		echo json_encode( $output );
		wp_die();
	}
	return $output;
}
add_action( 'rpress_disconnect-userid', 'rpress_disconnect_customer_user_id', 10, 1 );
/**
 * Process manual verification of customer account by admin
 *
 * @since  1.0.0
 * @return void
 */
function rpress_process_admin_user_verification() {
	if ( empty( $_GET['id'] ) || ! is_numeric( $_GET['id'] ) ) {
		return false;
	}
	if ( empty( $_GET['_wpnonce'] ) ) {
		return false;
	}
	$nonce = sanitize_text_field( $_GET['_wpnonce'] );
	if ( ! wp_verify_nonce( $nonce, 'rpress-verify-user' ) ) {
		wp_die( esc_html__( 'Nonce verification failed', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}
	$customer = new RPRESS_Customer( absint( $_GET['id'] ) );
	rpress_set_user_to_verified( $customer->user_id );
	$url = add_query_arg( 'rpress-message', 'user-verified', admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . $customer->id ) );
	wp_safe_redirect( esc_url( $url ) );
	exit;
}
add_action( 'rpress_verify_user_admin', 'rpress_process_admin_user_verification' );
/**
 * Register the reset single customer stats batch processor
 * @since  1.0.0
 */
function rpress_register_batch_single_customer_recount_tool() {
	add_action( 'rpress_batch_export_class_include', 'rpress_include_single_customer_recount_tool_batch_processer', 10, 1 );
}
add_action( 'rpress_register_batch_exporter', 'rpress_register_batch_single_customer_recount_tool', 10 );
/**
 * Loads the tools batch processing class for recounding stats for a single customer
 *
 * @since  1.0.0
 * @param  string $class The class being requested to run for the batch export
 * @return void
 */
function rpress_include_single_customer_recount_tool_batch_processer( $class ) {
	if ( 'RPRESS_Tools_Recount_Single_Customer_Stats' === $class ) {
		require_once RP_PLUGIN_DIR . 'includes/admin/tools/class-rpress-tools-recount-single-customer-stats.php';
	}
}
/**
 * Sets up additional action calls for the set_last_changed method in the RPRESS_DB_Customers class.
 *
 * @since  1.0.0
 * @param  void
 * @return void
 */
function rpress_customer_action_calls() {
	add_action( 'added_customer_meta', array( RPRESS()->customers, 'set_last_changed' ) );
	add_action( 'updated_customer_meta', array( RPRESS()->customers, 'set_last_changed' ) );
	add_action( 'deleted_customer_meta', array( RPRESS()->customers, 'set_last_changed' ) );
}
add_action( 'init', 'rpress_customer_action_calls' );
