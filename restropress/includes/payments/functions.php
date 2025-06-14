<?php
/**
 * Payment Functions
 *
 * @package     RPRESS
 * @subpackage  Payments
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;
/**
 * Retrieves an instance of RPRESS_Payment for a specified ID.
 *
 * @since 1.0
 *
 * @param mixed int|RPRESS_Payment|WP_Post $payment Payment ID, RPRESS_Payment object or WP_Post object.
 * @param bool                          $by_txn  Is the ID supplied as the first parameter
 * @return RPRESS_Payment|false false|object RPRESS_Payment if a valid payment ID, false otherwise.
 */
function rpress_get_payment( $payment_or_txn_id = null, $by_txn = false ) {
	global $wpdb;
	if ( $payment_or_txn_id instanceof WP_Post || $payment_or_txn_id instanceof RPRESS_Payment ) {
		$payment_id = $payment_or_txn_id->ID;
	} elseif ( $by_txn ) {
		if ( empty( $payment_or_txn_id ) ) {
			return false;
		}
		$query      = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_rpress_payment_transaction_id' AND meta_value = '%s'", $payment_or_txn_id );
		$payment_id = $wpdb->get_var( $query );
		if ( empty( $payment_id ) ) {
			return false;
		}
	} else {
		$payment_id = $payment_or_txn_id;
	}
	if ( empty( $payment_id ) ) {
		return false;
	}
	$cache_key = md5( 'rpress_payment' . $payment_id );
	$payment   = wp_cache_get( $cache_key, 'payments' );
	if ( false === $payment ) {
		$payment = new RPRESS_Payment( $payment_id );
		if ( empty( $payment->ID ) || ( ! $by_txn && (int) $payment->ID !== (int) $payment_id ) ) {
			return false;
		} else {
			wp_cache_set( $cache_key, $payment, 'payments' );
		}
	}
	return $payment;
}
/**
 * Get Payments
 *
 * Retrieve payments from the database.
 *
 * Since 1.2, this function takes an array of arguments, instead of individual
 * parameters. All of the original parameters remain, but can be passed in any
 * order via the array.
 *
 * $offset = 0, $number = 20, $mode = 'live', $orderby = 'ID', $order = 'DESC',
 * $user = null, $status = 'any', $meta_key = null
 *
 * As of RPRESS 1.8 this simply wraps RPRESS_Payments_Query
 *
 * @since 1.0
 * @param array $args Arguments passed to get payments
 * @return RPRESS_Payment[] $payments Payments retrieved from the database
 */
function rpress_get_payments( $args = array() ) {
	// Fallback to post objects to ensure backwards compatibility
	if( ! isset( $args['output'] ) ) {
		$args['output'] = 'posts';
	}
	$args     = apply_filters( 'rpress_get_payments_args', $args );
	$payments = new RPRESS_Payments_Query( $args );
	return $payments->get_payments();
}
/**
 * Retrieve payment by a given field
 *
 * @since       2.0
 * @param       string $field The field to retrieve the payment with
 * @param       mixed $value The value for $field
 * @return      mixed
 */
function rpress_get_payment_by( $field = '', $value = '' ) {
	$payment = false;
	if( ! empty( $field ) && ! empty( $value ) ) {
		switch( strtolower( $field ) ) {
			case 'id':
				$payment = rpress_get_payment( $value );
				if( ! $payment->ID > 0 ) {
					$payment = false;
				}
				break;
			case 'key':
			case 'payment_number':
				global $wpdb;
				$meta_key   = ( 'key' == $field ) ? '_rpress_payment_purchase_key' : '_rpress_payment_number';
				$payment_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT post_ID FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value=%s",
					$meta_key, $value
				) );
				if ( $payment_id ) {
					$payment = rpress_get_payment( $payment_id );
					if( ! $payment->ID > 0 ) {
						$payment = false;
					}
				}
				break;
		}
	}
	return $payment;
}
/**
 * Insert Payment
 *
 * @since 1.0
 * @param array $payment_data Payment data to process
 * @return int|bool Payment ID if payment is inserted, false otherwise
 */
function rpress_insert_payment( $payment_data = array() ) {
	if ( empty( $payment_data ) ) {
		return false;
	}
	$resume_payment   = false;
	$existing_payment = RPRESS()->session->get( 'rpress_resume_payment' );
	if ( ! empty( $existing_payment ) ) {
		$payment = new RPRESS_Payment( $existing_payment );
		$resume_payment = $payment->is_recoverable();
	}
	if ( $resume_payment ) {
		$payment->date = gmdate( 'Y-m-d G:i:s', current_time( 'timestamp' ) );
		$payment->add_note( __( 'Payment recovery processed', 'restropress' ) );
		// Since things could have been added/removed since we first crated this...rebuild the cart details.
		foreach ( $payment->fees as $fee_index => $fee ) {
			$payment->remove_fee_by( 'index', $fee_index, true );
		}
		foreach ( $payment->fooditems as $cart_index => $fooditem ) {
			$item_args = array(
				'quantity'   => isset( $fooditem['quantity'] ) ? $fooditem['quantity'] : 1,
				'cart_index' => $cart_index,
			);
			$payment->remove_fooditem( $fooditem['id'], $item_args );
		}
		if ( strtolower( $payment->email ) !== strtolower( $payment_data['user_info']['email'] ) ) {
			// Remove the payment from the previous customer.
			$previous_customer = new RPRESS_Customer( $payment->customer_id );
			$previous_customer->remove_payment( $payment->ID, false );
			// Redefine the email frst and last names.
			$payment->email                 = $payment_data['user_info']['email'];
			$payment->first_name            = $payment_data['user_info']['first_name'];
			$payment->last_name             = $payment_data['user_info']['last_name'];
		}
		// Remove any remainders of possible fees from items.
		$payment->save();
	} else {
		$payment = new RPRESS_Payment();
	}
	if( is_array( $payment_data['cart_details'] ) && ! empty( $payment_data['cart_details'] ) ) {
		foreach ( $payment_data['cart_details'] as $item ) {
			$args = array(
				'quantity'   => $item['quantity'],
				'instruction'=> isset($item['item_number']['instruction']) ? $item['item_number']['instruction'] : null,
				'price_id'   => isset( $item['item_number']['price_id'] ) ? $item['item_number']['price_id'] : null,
				'tax'        => $item['tax'],
				'item_price' => isset( $item['item_price'] ) ? $item['item_price'] : $item['price'],
				'fees'       => isset( $item['fees'] ) ? $item['fees'] : array(),
				'discount'   => isset( $item['discount'] ) ? $item['discount'] : 0,
			);
			$options = isset( $item['item_number']['addon_items'] ) ? $item['item_number']['addon_items'] : array();
			$payment->add_fooditem( $item['id'], $args, $options );
		}
	}
	$gateway = ! empty( $payment_data['gateway'] ) ? $payment_data['gateway'] : '';
	$gateway = empty( $gateway ) && isset( $_POST['rpress-gateway'] ) ? sanitize_text_field( $_POST['rpress-gateway'] ) : $gateway;
	$country = ! empty( $payment_data['user_info']['address']['country'] ) ? $payment_data['user_info']['address']['country'] : false;
	$state   = ! empty( $payment_data['user_info']['address']['state'] )   ? $payment_data['user_info']['address']['state']   : false;
	$zip     = ! empty( $payment_data['user_info']['address']['zip'] )     ? $payment_data['user_info']['address']['zip']     : false;
	$payment->status         = ! empty( $payment_data['status'] ) ? $payment_data['status'] : 'pending';
	$payment->currency       = ! empty( $payment_data['currency'] ) ? $payment_data['currency'] : rpress_get_currency();
	$payment->user_info      = $payment_data['user_info'];
	$payment->gateway        = $gateway;
	$payment->user_id        = $payment_data['user_info']['id'];
	$payment->first_name     = $payment_data['user_info']['first_name'];
	$payment->last_name      = $payment_data['user_info']['last_name'];
	$payment->email          = $payment_data['user_info']['email'];
	$payment->ip             = rpress_get_ip();
	$payment->key            = $payment_data['purchase_key'];
	$payment->mode           = rpress_is_test_mode() ? 'test' : 'live';
	$payment->parent_payment = ! empty( $payment_data['parent'] ) ? absint( $payment_data['parent'] ) : '';
	$payment->discounts      = ! empty( $payment_data['user_info']['discount'] ) ? $payment_data['user_info']['discount'] : array();
	$payment->tax_rate       = rpress_get_cart_tax_rate( $country, $state, $zip );
	$payment->delivery_type      = isset($_COOKIE['service_type']) ? sanitize_text_field( $_COOKIE['service_type'] ) : ''  ;
	$payment->delivery_time      = isset($_COOKIE['service_time']) ? sanitize_text_field( $_COOKIE['service_time'] ) : ''  ;
	$payment->delivery_date     = isset($_COOKIE['delivery_date']) ? sanitize_text_field( $_COOKIE['delivery_date'] ) : ''  ;
	if ( isset( $payment_data['post_date'] ) ) {
		$payment->date = $payment_data['post_date'];
	}
	// Clear the user's purchased cache
	delete_transient( 'rpress_user_' . $payment_data['user_info']['id'] . '_purchases' );
	$payment->total = isset($payment_data['price']) ? $payment_data['price'] : '';
	$payment->save();
	if ( rpress_get_option( 'show_agree_to_terms', false ) && ! empty( $_POST['rpress_agree_to_terms'] ) ) {
		$payment_data['agree_to_terms_time'] = current_time( 'timestamp' );
	}
	if ( rpress_get_option( 'show_agree_to_privacy_policy', false ) && ! empty( $_POST['rpress_agree_to_privacy_policy'] ) ) {
		$payment_data['agree_to_privacy_time'] = current_time( 'timestamp' );
	}
	do_action( 'rpress_insert_payment', $payment->ID, $payment_data );
	rpress_update_order_status( $payment->ID, 'pending' );
	if ( ! empty( $payment->ID ) ) {
		return $payment->ID;
	}
	// Return false if no payment was inserted
	return false;
}
/**
 * Updates a payment status.
 *
 * @since  1.0
 * @param  int    $payment_id Payment ID
 * @param  string $new_status New Payment Status (default: publish)
 * @return bool               If the payment was successfully updated
 */
function rpress_update_payment_status( $payment_id = 0, $new_status = 'publish' ) {
	$updated = false;
	$payment = new RPRESS_Payment( $payment_id );
	if( $payment && $payment->ID > 0 ) {
		$payment->status = $new_status;
		$updated = $payment->save();
	}
	return $updated;
}
/**
 * Deletes a Purchase
 *
 * @since 1.0
 * @global $rpress_logs
 *
 * @uses RPRESS_Logging::delete_logs()
 *
 * @param int $payment_id Payment ID (default: 0)
 * @param bool $update_customer If we should update the customer stats (default:true)
 * @param bool $delete_fooditem_logs If we should remove all file fooditem logs associated with the payment (default:false)
 *
 * @return void
 */
function rpress_delete_purchase( $payment_id = 0, $update_customer = true, $delete_fooditem_logs = false ) {
	global $rpress_logs;
	$payment   = new RPRESS_Payment( $payment_id );
	// Update sale counts and earnings for all purchased products
	rpress_undo_purchase( false, $payment_id );
	$amount      = rpress_get_payment_amount( $payment_id );
	$status      = $payment->post_status;
	$customer_id = rpress_get_payment_customer_id( $payment_id );
	$customer = new RPRESS_Customer( $customer_id );
	if( $status == 'revoked' || $status == 'publish' ) {
		// Only decrease earnings if they haven't already been decreased (or were never increased for this payment)
		rpress_decrease_total_earnings( $amount );
		// Clear the This Month earnings (this_monththis_month is NOT a typo)
		delete_transient( md5( 'rpress_earnings_this_monththis_month' ) );
		if( $customer->id && $update_customer ) {
			// Decrement the stats for the customer
			$customer->decrease_purchase_count();
			$customer->decrease_value( $amount );
		}
	}
	do_action( 'rpress_payment_delete', $payment_id );
	if( $customer->id && $update_customer ) {
		// Remove the payment ID from the customer
		$customer->remove_payment( $payment_id );
	}
	// Remove the payment
	wp_delete_post( $payment_id, true );
	// Remove related sale log entries
	$rpress_logs->delete_logs(
		null,
		'sale',
		array(
			array(
				'key'   => '_rpress_log_payment_id',
				'value' => $payment_id
			)
		)
	);
	if ( $delete_fooditem_logs ) {
		$rpress_logs->delete_logs(
			null,
			'file_fooditem',
			array(
				array(
					'key'   => '_rpress_log_payment_id',
					'value' => $payment_id
				)
			)
		);
	}
	do_action( 'rpress_payment_deleted', $payment_id );
}
/**
 * Undos a purchase, including the decrease of sale and earning stats. Used for
 * when refunding or deleting a purchase
 *
 * @since 1.0.0.1
 * @param int $fooditem_id (Post) ID
 * @param int $payment_id Payment ID
 * @return void
 */
function rpress_undo_purchase( $fooditem_id = false, $payment_id = null ) {
	/**
	 * In 2.5.7, a bug was found that $fooditem_id was an incorrect usage. Passing it in
	 * now does nothing, but we're holding it in place for legacy support of the argument order.
	 */
	if ( ! empty( $fooditem_id ) ) {
		$fooditem_id = false;
		_rpress_deprected_argument( 'fooditem_id', 'rpress_undo_purchase', '2.5.7' );
	}
	$payment = new RPRESS_Payment( $payment_id );
	$cart_details = $payment->cart_details;
	$user_info    = $payment->user_info;
	if ( is_array( $cart_details ) ) {
		foreach ( $cart_details as $item ) {
			// get the item's price
			$amount = isset( $item['price'] ) ? $item['price'] : false;
			// Decrease earnings/sales and fire action once per quantity number
			for( $i = 0; $i < $item['quantity']; $i++ ) {
				// variable priced fooditems
				if ( false === $amount && rpress_has_variable_prices( $item['id'] ) ) {
					$price_id = isset( $item['item_number']['options']['price_id'] ) ? $item['item_number']['options']['price_id'] : null;
					$amount   = ! isset( $item['price'] ) && 0 !== $item['price'] ? rpress_get_price_option_amount( $item['id'], $price_id ) : $item['price'];
				}
				if ( ! $amount ) {
					// This function is only used on payments with near 1.0 cart data structure
					$amount = rpress_get_fooditem_final_price( $item['id'], $user_info, $amount );
				}
			}
			if ( ! empty( $item['fees'] ) ) {
				foreach ( $item['fees'] as $fee ) {
					// Only let negative fees affect the earnings
					if ( $fee['amount'] > 0 ) {
						continue;
					}
					$amount += $fee['amount'];
				}
			}
			$maybe_decrease_earnings = apply_filters( 'rpress_decrease_earnings_on_undo', true, $payment, $item['id'] );
			if ( true === $maybe_decrease_earnings ) {
				// decrease earnings
				rpress_decrease_earnings( $item['id'], $amount );
			}
			$maybe_decrease_sales = apply_filters( 'rpress_decrease_sales_on_undo', true, $payment, $item['id'] );
			if ( true === $maybe_decrease_sales ) {
				// decrease purchase count
				rpress_decrease_purchase_count( $item['id'], $item['quantity'] );
			}
		}
	}
}
/**
 * Count Payments
 *
 * Returns the total number of payments recorded.
 *
 * @since 1.0
 * @param array $args List of arguments to base the payments count on
 * @return array $count Number of payments sorted by payment status
 */
function rpress_count_payments( $args = array() ) {
	global $wpdb;
	$defaults = array(
		'user'       => null,
		'customer'   => null,
		's'          => null,
		'start-date' => null,
		'end-date'   => null,
		'fooditem'   => null,
		'gateway'    => null,
	'service-type'   =>null
	);
	$args = wp_parse_args( $args, $defaults );
	$select = "SELECT p.post_status,count( * ) AS num_posts";
	$join = '';
	$where = "WHERE p.post_type = 'rpress_payment'";
	// Count payments for a specific user
	if( ! empty( $args['user'] ) ) {
		if( is_email( $args['user'] ) )
			$field = 'email';
		elseif( is_numeric( $args['user'] ) )
			$field = 'id';
		else
			$field = '';
		$join = "LEFT JOIN $wpdb->postmeta m ON (p.ID = m.post_id)";
		if ( ! empty( $field ) ) {
			$where .= "
				AND m.meta_key = '_rpress_payment_user_{$field}'
				AND m.meta_value = '{$args['user']}'";
		}
	} elseif ( ! empty( $args['customer'] ) ) {
		$join = "LEFT JOIN $wpdb->postmeta m ON (p.ID = m.post_id)";
		$where .= "
			AND m.meta_key = '_rpress_payment_customer_id'
			AND m.meta_value = '{$args['customer']}'";
	// Count payments for a search
	} elseif( ! empty( $args['s'] ) ) {
		if ( is_email( $args['s'] ) || strlen( $args['s'] ) == 32 ) {
			if( is_email( $args['s'] ) )
				$field = '_rpress_payment_user_email';
			else
				$field = '_rpress_payment_purchase_key';
			$join = "LEFT JOIN $wpdb->postmeta m ON (p.ID = m.post_id)";
			$where .= $wpdb->prepare( "
				AND m.meta_key = %s
				AND m.meta_value = %s",
				$field,
				$args['s']
			);
		} elseif ( '#' == substr( $args['s'], 0, 1 ) ) {
			$search = str_replace( '#:', '', $args['s'] );
			$search = str_replace( '#', '', $search );
			$select = "SELECT p2.post_status,count( * ) AS num_posts ";
			$join   = "LEFT JOIN $wpdb->postmeta m ON m.meta_key = '_rpress_log_payment_id' AND m.post_id = p.ID ";
			$join  .= "INNER JOIN $wpdb->posts p2 ON m.meta_value = p2.ID ";
			$where  = "WHERE p.post_type = 'rpress_log' ";
			$where .= $wpdb->prepare( "AND p.post_parent = %d ", $search );
		} elseif ( is_numeric( $args['s'] ) ) {
			$join = "LEFT JOIN $wpdb->postmeta m ON (p.ID = m.post_id)";
			$where .= $wpdb->prepare( "
				AND m.meta_key = '_rpress_payment_user_id'
				AND m.meta_value = %d",
				$args['s']
			);
		} elseif ( 0 === strpos( $args['s'], 'discount:' ) ) {
			$search = str_replace( 'discount:', '', $args['s'] );
			$search = 'discount.*' . $search;
			$join   = "LEFT JOIN $wpdb->postmeta m ON (p.ID = m.post_id)";
			$where .= $wpdb->prepare( "
				AND m.meta_key = '_rpress_payment_meta'
				AND m.meta_value REGEXP %s",
				$search
			);
		} else {
			$search = $wpdb->esc_like( $args['s'] );
			$search = '%' . $search . '%';
			$where .= $wpdb->prepare( "AND ((p.post_title LIKE %s) OR (p.post_content LIKE %s))", $search, $search );
		}
	}
	if ( ! empty( $args['fooditem'] ) && is_numeric( $args['fooditem'] ) ) {
		$where .= $wpdb->prepare( " AND p.post_parent = %d", $args['fooditem'] );
	}
	// Limit payments count by gateway
	if ( ! empty( $args['gateway'] ) ) {
		$join .= "LEFT JOIN $wpdb->postmeta g ON (p.ID = g.post_id)";
		$where .= $wpdb->prepare( "
			AND g.meta_key = '_rpress_payment_gateway'
			AND g.meta_value = %s",
			$args['gateway']
		);
	}
	
	// Limit payments count by date
	if ( ! empty( $args['start-date'] ) && false !== strpos( $args['start-date'], '/' ) ) {
		$date_parts = explode( '/', $args['start-date'] );
		$month      = ! empty( $date_parts[0] ) && is_numeric( $date_parts[0] ) ? $date_parts[0] : 0;
		$day        = ! empty( $date_parts[1] ) && is_numeric( $date_parts[1] ) ? $date_parts[1] : 0;
		$year       = ! empty( $date_parts[2] ) && is_numeric( $date_parts[2] ) ? $date_parts[2] : 0;
		$is_date    = checkdate( $month, $day, $year );
		if ( false !== $is_date ) {
			$date   = new DateTime( $args['start-date'] );
			$where .= $wpdb->prepare( " AND p.post_date >= '%s'", $date->format( 'Y-m-d' ) );
		}
		// Fixes an issue with the payments list table counts when no end date is specified (partly with stats class)
		if ( empty( $args['end-date'] ) ) {
			$args['end-date'] = $args['start-date'];
		}
	}
	if ( ! empty ( $args['end-date'] ) && false !== strpos( $args['end-date'], '/' ) ) {
		$date_parts = explode( '/', $args['end-date'] );
		$month      = ! empty( $date_parts[0] ) ? $date_parts[0] : 0;
		$day        = ! empty( $date_parts[1] ) ? $date_parts[1] : 0;
		$year       = ! empty( $date_parts[2] ) ? $date_parts[2] : 0;
		$is_date    = checkdate( $month, $day, $year );
		if ( false !== $is_date ) {
			$date = gmdate( 'Y-m-d', strtotime( '+1 day', mktime( 0, 0, 0, $month, $day, $year ) ) );
			$where .= $wpdb->prepare( " AND p.post_date < '%s'", $date );
		}
	}
	$where = apply_filters( 'rpress_count_payments_where', $where );
	$join  = apply_filters( 'rpress_count_payments_join', $join );
	$query = "$select
		FROM $wpdb->posts p
		$join
		$where
		GROUP BY p.post_status
	";
	$cache_key = md5( $query );
	$count = wp_cache_get( $cache_key, 'counts');
	if ( false !== $count ) {
		return $count;
	}
	$count = $wpdb->get_results( $query, ARRAY_A );
	$stats    = array();
	$statuses = get_post_stati();
	if( isset( $statuses['private'] ) && empty( $args['s'] ) ) {
		unset( $statuses['private'] );
	}
	foreach ( $statuses as $state ) {
		$stats[$state] = 0;
	}
	foreach ( (array) $count as $row ) {
		if( 'private' == $row['post_status'] && empty( $args['s'] ) ) {
			continue;
		}
		$stats[$row['post_status']] = $row['num_posts'];
	}
	$stats = (object) $stats;
	wp_cache_set( $cache_key, $stats, 'counts' );
	return $stats;
}
/**
 * Check For Existing Payment
 *
 * @since 1.0
 * @param int $payment_id Payment ID
 * @return bool true if payment exists, false otherwise
 */
function rpress_check_for_existing_payment( $payment_id ) {
	$exists  = false;
	$payment = new RPRESS_Payment( $payment_id );
	if ( $payment_id === $payment->ID && 'publish' === $payment->status ) {
		$exists = true;
	}
	return $exists;
}
/**
 * Get Payment Status
 *
 * @since 1.0
 *
 * @param mixed  WP_Post|RPRESS_Payment|Payment ID $payment Payment post object, RPRESS_Payment object, or payment/post ID
 * @param bool   $return_label Whether to return the payment status or not
 *
 * @return bool|mixed if payment status exists, false otherwise
 */
function rpress_get_payment_status( $payment, $return_label = false ) {
	if( is_numeric( $payment ) ) {
		$payment = new RPRESS_Payment( $payment );
		if( ! $payment->ID > 0 ) {
			return false;
		}
	}
	if ( ! is_object( $payment ) || ! isset( $payment->post_status ) ) {
		return false;
	}
	if ( true === $return_label ) {
		return rpress_get_payment_status_label( $payment->post_status );
	} else {
		$statuses = rpress_get_payment_statuses();
		// Account that our 'publish' status is labeled 'Paid'
		$post_status = 'publish' == $payment->status ? 'Paid' : $payment->post_status;
		// Make sure we're matching cases, since they matter
		return array_search( strtolower( $payment->post_status ), array_map( 'strtolower', $statuses ) );
	}
	return ! empty( $status ) ? $status : false;
}
/**
 * Given a payment status string, return the label for that string.
 *
 * @since 2.9.2
 * @param string $status
 *
 * @return bool|mixed
 */
function rpress_get_payment_status_label( $status = '' ) {
	$statuses = rpress_get_payment_statuses();
	if ( ! is_array( $statuses ) || empty( $statuses ) ) {
		return false;
	}
	if ( array_key_exists( $status, $statuses ) ) {
		return $statuses[ $status ];
	}
	return false;
}
/**
 * Retrieves all available statuses for payments.
 *
 * @since 1.0.0.1
 * @return array $payment_status All the available payment statuses
 */
function rpress_get_payment_statuses() {
	$payment_statuses = array(
		'pending' 		=> __( 'Pending', 'restropress' ),
		'publish' 		=> __( 'Paid', 'restropress' ),
		'refunded' 		=> __( 'Refunded', 'restropress' ),
		'failed' 		=> __( 'Failed', 'restropress' ),
    	'processing'	=> __( 'Processing', 'restropress' ),
		'abandoned'		=> __( 'Abandoned', 'restropress' ),
	);
	return apply_filters( 'rpress_payment_statuses', $payment_statuses );
}
/**
 * Retrieves all available payments status colors.
 *
 * @since 1.0.0.1
 * @return array $payment_status_colors Status Colors
 */
function rpress_get_payment_status_colors() {
	$payment_status_colors = array(
		'pending' 			=> '#fcbdbd',
		'pending_text' 		=> '#333333',
		'publish' 			=> '#e0f0d7',
		'publish_text' 		=> '#3a773a',
		'refunded' 			=> '#e5e5e5',
		'refunded_text' 	=> '#777777',
		'failed' 			=> '#e76450',
		'failed_text' 		=> '#ffffff',
    	'processing'		=> '#f7ae18',
    	'processing_text'	=> '#ffffff',
	);
	return apply_filters( 'rpress_payment_status_colors', $payment_status_colors );
}
/**
 * Given a order status string, return the label for that string.
 *
 * @since 2.7
 * @param string $status
 *
 * @return bool|mixed
 */
function rpress_get_order_status_label( $status = '' ) {
	$statuses = rpress_get_order_statuses();
	if ( ! is_array( $statuses ) || empty( $statuses ) ) {
		return false;
	}
	if ( array_key_exists( $status, $statuses ) ) {
		return $statuses[ $status ];
	}
	return false;
}
/**
  * Retrieves all available statuses for Orders.
  *
  * @since 2.2
  * @return array $order_status All the available order statuses
  */
  function rpress_get_order_statuses() {
    $order_statuses = array(
      'pending'     => __( 'Pending', 'restropress' ),
      'accepted'    => __( 'Accepted', 'restropress' ),
      'processing'  => __( 'Processing', 'restropress' ),
      'ready' 		=> __( 'Ready', 'restropress' ),
      'transit' 	=> __( 'In Transit', 'restropress' ),
      'cancelled'   => __( 'Cancelled', 'restropress' ),
      'completed'   => __( 'Completed', 'restropress' ),
    );
    return apply_filters( 'rpress_order_statuses', $order_statuses );
  }
  /**
  * Retrieves all available colors for Order Statuses
  *
  * @since 2.2
  * @return array $order_status_colors Colors for available statuses
  */
  function rpress_get_order_status_colors() {
    $order_status_colors = array(
      'pending'     	=> '#fcbdbd',
      'pending_text' 	=> '#333333',
      'accepted'    	=> '#ffcd85',
      'accepted_text' 	=> '#92531b',
      'processing'  	=> '#f7ae18',
      'processing_text' => '#ffffff',
      'ready' 			=> '#75A84C',
      'ready_text' 		=> '#ffffff',
      'transit' 		=> '#cac300',
      'transit_text' 	=> '#464343',
      'cancelled'   	=> '#eba3a3',
      'cancelled_text' 	=> '#761919',
      'completed' 		=> '#e0f0d7',
      'completed_text'	=> '#3a773a',
    );
    return apply_filters( 'rpress_order_status_colors', $order_status_colors );
  }
/**
 * Retrieves keys for all available statuses for payments
 *
 * @since 1.0
 * @return array $payment_status All the available payment statuses
 */
function rpress_get_payment_status_keys() {
	$statuses = array_keys( rpress_get_payment_statuses() );
	asort( $statuses );
	return array_values( $statuses );
}
/**
 * Checks whether a payment has been marked as complete.
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID to check against
 * @return bool true if complete, false otherwise
 */
function rpress_is_payment_complete( $payment_id = 0 ) {
	$payment = new RPRESS_Payment( $payment_id );
	$ret = false;
	if( $payment->ID > 0 ) {
		if ( (int) $payment_id === (int) $payment->ID && 'publish' == $payment->status ) {
			$ret = true;
		}
	}
	return apply_filters( 'rpress_is_payment_complete', $ret, $payment_id, $payment->post_status );
}
/**
 * Get Total Sales
 *
 * @since  1.0.0
 * @return int $count Total sales
 */
function rpress_get_total_sales() {
	$payments = rpress_count_payments();
	return $payments->revoked + $payments->publish;
}
/**
 * Get Total Earnings
 *
 * @since 1.0.0
 * @return float $total Total earnings
 */
function rpress_get_total_earnings() {
	$total = get_option( 'rpress_earnings_total', false );
	// If no total stored in DB, use old method of calculating total earnings
	if( false === $total ) {
		global $wpdb;
		$total = get_transient( 'rpress_earnings_total' );
		if( false === $total ) {
			$total = (float) 0;
			$args = apply_filters( 'rpress_get_total_earnings_args', array(
				'offset' => 0,
				'number' => -1,
				'status' => array( 'publish', 'revoked' ),
				'fields' => 'ids'
			) );
			$payments = rpress_get_payments( $args );
			if ( $payments ) {
				/*
				 * If performing a purchase, we need to skip the very last payment in the database, since it calls
				 * rpress_increase_total_earnings() on completion, which results in duplicated earnings for the very
				 * first purchase
				 */
				if( did_action( 'rpress_update_payment_status' ) ) {
					array_pop( $payments );
				}
				if( ! empty( $payments ) ) {
					$payments = implode( ',', $payments );
					$total += $wpdb->get_var( "SELECT SUM(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_rpress_payment_total' AND post_id IN({$payments})" );
				}
			}
			// Cache results for 1 day. This cache is cleared automatically when a payment is made
			set_transient( 'rpress_earnings_total', $total, 86400 );
			// Store the total for the first time
			update_option( 'rpress_earnings_total', $total );
		}
	}
	if( $total < 0 ) {
		$total = 0; // Don't ever show negative earnings
	}
	return apply_filters( 'rpress_total_earnings', round( $total, rpress_currency_decimal_filter() ) );
}
/**
 * Increase the Total Earnings
 *
 * @since 1.0
 * @param $amount int The amount you would like to increase the total earnings by.
 * @return float $total Total earnings
 */
function rpress_increase_total_earnings( $amount = 0 ) {
	$total = floatval( rpress_get_total_earnings() );
	$total += floatval( $amount );
	update_option( 'rpress_earnings_total', $total );
	return $total;
}
/**
 * Decrease the Total Earnings
 *
 * @since 1.0
 * @param $amount int The amount you would like to decrease the total earnings by.
 * @return float $total Total earnings
 */
function rpress_decrease_total_earnings( $amount = 0 ) {
	$total = rpress_get_total_earnings();
	$total -= $amount;
	if( $total < 0 ) {
		$total = 0;
	}
	update_option( 'rpress_earnings_total', $total );
	return $total;
}
/**
 * Get Payment Meta for a specific Payment
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID
 * @param string $meta_key The meta key to pull
 * @param bool $single Pull single meta entry or as an object
 * @return mixed $meta Payment Meta
 */
function rpress_get_payment_meta( $payment_id = 0, $meta_key = '_rpress_payment_meta', $single = true ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->get_meta( $meta_key, $single );
}
/**
 * Update the meta for a payment
 * @param  integer $payment_id Payment ID
 * @param  string  $meta_key   Meta key to update
 * @param  string  $meta_value Value to update to
 * @param  string  $prev_value Previous value
 * @return mixed               Meta ID if successful, false if unsuccessful
 */
function rpress_update_payment_meta( $payment_id = 0, $meta_key = '', $meta_value = '', $prev_value = '' ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->update_meta( $meta_key, $meta_value, $prev_value );
}
/**
 * Get the user_info Key from Payment Meta
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID
 * @return array $user_info User Info Meta Values
 */
function rpress_get_payment_meta_user_info( $payment_id ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->user_info;
}
/**
 * Get the fooditems Key from Payment Meta
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID
 * @return array $fooditems RestroPress Meta Values
 */
function rpress_get_payment_meta_fooditems( $payment_id ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->fooditems;
}
/**
 * Get the cart_details Key from Payment Meta
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID
 * @param bool $include_bundle_files Whether to retrieve product IDs associated with a bundled product and return them in the array
 * @return array $cart_details Cart Details Meta Values
 */
function rpress_get_payment_meta_cart_details( $payment_id, $include_bundle_files = false ) {
	$payment      = new RPRESS_Payment( $payment_id );
	$cart_details = $payment->cart_details;
	$payment_currency = $payment->currency;
	if ( ! empty( $cart_details ) && is_array( $cart_details ) ) {
		foreach ( $cart_details as $key => $cart_item ) {
			$cart_details[ $key ]['currency'] = $payment_currency;
			// Ensure subtotal is set, for pre-1.9 orders
			if ( ! isset( $cart_item['subtotal'] ) ) {
				$cart_details[ $key ]['subtotal'] = $cart_item['price'];
			}
			if ( $include_bundle_files ) {
				if( 'bundle' != rpress_get_fooditem_type( $cart_item['id'] ) )
					continue;
				$price_id = rpress_get_cart_item_price_id( $cart_item );
				$products = rpress_get_bundled_products( $cart_item['id'], $price_id );
				if ( empty( $products ) )
					continue;
				foreach ( $products as $product_id ) {
					$cart_details[]   = array(
						'id'          => $product_id,
						'name'        => get_the_title( $product_id ),
						'item_number' => array(
							'id'      => $product_id,
							'options' => array(),
						),
						'price'       => 0,
						'subtotal'    => 0,
						'quantity'    => 1,
						'tax'         => 0,
						'in_bundle'   => 1,
						'parent'      => array(
							'id'      => $cart_item['id'],
							'options' => isset( $cart_item['item_number']['options'] ) ? $cart_item['item_number']['options'] : array()
						)
					);
				}
			}
		}
	}
	return apply_filters( 'rpress_payment_meta_cart_details', $cart_details, $payment_id );
}
/**
 * Get the user email associated with a payment
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID
 * @return string $email User Email
 */
function rpress_get_payment_user_email( $payment_id ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->email;
}
/**
 * Is the payment provided associated with a user account
 *
 * @since  1.0.0
 * @param  int $payment_id The payment ID
 * @return bool            If the payment is associated with a user (false) or not (true)
 */
function rpress_is_guest_payment( $payment_id ) {
	$payment_user_id  = rpress_get_payment_user_id( $payment_id );
	$is_guest_payment = ! empty( $payment_user_id ) && $payment_user_id > 0 ? false : true;
	return (bool) apply_filters( 'rpress_is_guest_payment', $is_guest_payment, $payment_id );
}
/**
 * Get the user ID associated with a payment
 *
 * @since 1.0.1
 * @param int $payment_id Payment ID
 * @return string $user_id User ID
 */
function rpress_get_payment_user_id( $payment_id ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->user_id;
}
/**
 * Get the customer ID associated with a payment
 *
 * @since  1.0.0
 * @param int $payment_id Payment ID
 * @return string $customer_id Customer ID
 */
function rpress_get_payment_customer_id( $payment_id ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->customer_id;
}
/**
 * Get the status of the unlimited fooditems flag
 *
 * @since  1.0.0
 * @param int $payment_id Payment ID
 * @return bool $unlimited
 */
function rpress_payment_has_unlimited_fooditems( $payment_id ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->has_unlimited_fooditems;
}
/**
 * Get the IP address used to make a purchase
 *
 * @since  1.0.0
 * @param int $payment_id Payment ID
 * @return string $ip User IP
 */
function rpress_get_payment_user_ip( $payment_id ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->ip;
}
/**
 * Get the date a payment was completed
 *
 * @since  1.0.0
 * @param int $payment_id Payment ID
 * @return string $date The date the payment was completed
 */
function rpress_get_payment_completed_date( $payment_id = 0 ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->completed_date;
}
/**
 * Get the gateway associated with a payment
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID
 * @return string $gateway Gateway
 */
function rpress_get_payment_gateway( $payment_id ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->gateway;
}
/**
 * Get the currency code a payment was made in
 *
 * @since  1.0.0
 * @param int $payment_id Payment ID
 * @return string $currency The currency code
 */
function rpress_get_payment_currency_code( $payment_id = 0 ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->currency;
}
/**
 * Get the currency name a payment was made in
 *
 * @since  1.0.0
 * @param int $payment_id Payment ID
 * @return string $currency The currency name
 */
function rpress_get_payment_currency( $payment_id = 0 ) {
	$currency = rpress_get_payment_currency_code( $payment_id );
	return apply_filters( 'rpress_payment_currency', rpress_get_currency_name( $currency ), $payment_id );
}
/**
 * Get the purchase key for a purchase
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID
 * @return string $key Purchase key
 */
function rpress_get_payment_key( $payment_id = 0 ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->key;
}
/**
 * Get the payment order number
 *
 * This will return the payment ID if sequential order numbers are not enabled or the order number does not exist
 *
 * @since  1.0.0
 * @param int $payment_id Payment ID
 * @return string $number Payment order number
 */
function rpress_get_payment_number( $payment_id = 0 ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->number;
}
/**
 * Formats the payment number with the prefix and postfix
 *
 * @since 1.0
 * @param  int $number The payment number to format
 * @return string      The formatted payment number
 */
function rpress_format_payment_number( $number ) {
	
	if( ! rpress_get_option( 'enable_sequential' ) ) {
		return $number;
	}
	if ( ! is_numeric( $number ) ) {
		return $number;
	}
	$prefix  = rpress_get_option( 'sequential_prefix' );
	$number  = absint( $number );
	$postfix = rpress_get_option( 'sequential_postfix' );
	$formatted_number = $prefix . $number . $postfix;
	return apply_filters( 'rpress_format_payment_number', $formatted_number, $prefix, $number, $postfix );
}
/**
 * Gets the next available order number
 *
 * This is used when inserting a new payment
 *
 * @since  1.0.0
 * @return string $number The next available payment number
 */
function rpress_get_next_payment_number() {
	if( ! rpress_get_option( 'enable_sequential' ) ) {
		return false;
	}
	$number           = get_option( 'rpress_last_payment_number' );
	$start            = rpress_get_option( 'enable_sequential' );
   
	$increment_number = true;
	 if ( false !== $number ) {
		if ( empty( $number ) ) {
			$number = $start;
			$increment_number = false;
		}
	} else {
		// This case handles the first addition of the new option, as well as if it get's deleted for any reason
		$payments     = new RPRESS_Payments_Query( array( 'number' => 1, 'order' => 'DESC', 'orderby' => 'ID', 'output' => 'posts', 'fields' => 'ids' ) );
		$last_payment = $payments->get_payments();
		if ( ! empty( $last_payment ) ) {
			$number = rpress_get_payment_number( $last_payment[0] );
		}
		if( ! empty( $number ) && $number !== (int) $last_payment[0] ) {
			$number = rpress_remove_payment_prefix_postfix( $number );
		} else {
			$number = $start;
			$increment_number = false;
		}
	}
	
	$increment_number = apply_filters( 'rpress_increment_payment_number', $increment_number, $number );
	if ( $increment_number ) {
		$number++;
	}
	
	return apply_filters( 'rpress_get_next_payment_number', $number );
}
/**
 * Given a given a number, remove the pre/postfix
 *
 * @since 1.0
 * @param  string $number  The formatted Current Number to increment
 * @return string          The new Payment number without prefix and postfix
 */
function rpress_remove_payment_prefix_postfix( $number ) {
	$prefix  = rpress_get_option( 'sequential_prefix' );
	$postfix = rpress_get_option( 'sequential_postfix' );
	// Remove prefix
	$number = preg_replace( '/' . $prefix . '/', '', $number, 1 );
	// Remove the postfix
	$length      = strlen( $number );
	$postfix_pos = strrpos( $number, $postfix );
	if ( false !== $postfix_pos ) {
		$number      = substr_replace( $number, '', $postfix_pos, $length );
	}
	// Ensure it's a whole number
	$number = intval( $number );
	return apply_filters( 'rpress_remove_payment_prefix_postfix', $number, $prefix, $postfix );
}
/**
 * Get the fully formatted payment amount. The payment amount is retrieved using
 * rpress_get_payment_amount() and is then sent through rpress_currency_filter() and
 * rpress_format_amount() to format the amount correctly.
 *
 * @since  1.0.0
 * @param int $payment_id Payment ID
 * @return string $amount Fully formatted payment amount
 */
function rpress_payment_amount( $payment_id = 0 ) {
	$amount = rpress_get_payment_amount( $payment_id );
	return rpress_currency_filter( rpress_format_amount( $amount ), rpress_get_payment_currency_code( $payment_id ) );
}
/**
 * Get the amount associated with a payment
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID
 * @return float Payment amount
 */
function rpress_get_payment_amount( $payment_id ) {
	$payment = new RPRESS_Payment( $payment_id );
	return apply_filters( 'rpress_payment_amount', floatval( $payment->total ), $payment_id );
}
/**
 * Retrieves subtotal for payment (this is the amount before taxes) and then
 * returns a full formatted amount. This function essentially calls
 * rpress_get_payment_subtotal()
 *
 * @since 1.0.0
 *
 * @param int $payment_id Payment ID
 *
 * @see rpress_get_payment_subtotal()
 *
 * @return array Fully formatted payment subtotal
 */
function rpress_payment_subtotal( $payment_id = 0 ) {
	$subtotal = rpress_get_payment_subtotal( $payment_id );
	return rpress_currency_filter( rpress_format_amount( $subtotal ), rpress_get_payment_currency_code( $payment_id ) );
}
/**
 * Retrieves subtotal for payment (this is the amount before taxes) and then
 * returns a non formatted amount.
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID
 * @return float $subtotal Subtotal for payment (non formatted)
 */
function rpress_get_payment_subtotal( $payment_id = 0 ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->subtotal;
}
/**
 * Retrieves taxed amount for payment and then returns a full formatted amount
 * This function essentially calls rpress_get_payment_tax()
 *
 * @since 1.0.0
 * @see rpress_get_payment_tax()
 * @param int $payment_id Payment ID
 * @param bool $payment_meta Payment Meta provided? (default: false)
 * @return string $subtotal Fully formatted payment subtotal
 */
function rpress_payment_tax( $payment_id = 0, $payment_meta = false ) {
	$tax = rpress_get_payment_tax( $payment_id, $payment_meta );
	return rpress_currency_filter( rpress_format_amount( $tax ), rpress_get_payment_currency_code( $payment_id ) );
}
/**
 * Retrieves taxed amount for payment and then returns a non formatted amount
 *
 * @since 1.0.0
 * @param int $payment_id Payment ID
 * @param bool $payment_meta Get payment meta?
 * @return float $tax Tax for payment (non formatted)
 */
function rpress_get_payment_tax( $payment_id = 0, $payment_meta = false ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->tax;
}
/**
 * Retrieve the tax for a cart item by the cart key
 *
 * @since  1.0.0
 * @param  integer $payment_id The Payment ID
 * @param  int     $cart_key   The cart key
 * @return float               The item tax amount
 */
function rpress_get_payment_item_tax( $payment_id = 0, $cart_key = false ) {
	$payment = new RPRESS_Payment( $payment_id );
	$item_tax = 0;
	$cart_details = $payment->cart_details;
	if ( false !== $cart_key && ! empty( $cart_details ) && array_key_exists( $cart_key, $cart_details ) ) {
		$item_tax = ! empty( $cart_details[ $cart_key ]['tax'] ) ? $cart_details[ $cart_key ]['tax'] : 0;
	}
	return $item_tax;
}
/**
 * Retrieves arbitrary fees for the payment
 *
 * @since 1.0
 * @param int $payment_id Payment ID
 * @param string $type Fee type
 * @return mixed array if payment fees found, false otherwise
 */
function rpress_get_payment_fees( $payment_id = 0, $type = 'all' ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->get_fees( $type );
}
/**
 * Retrieves the transaction ID for the given payment
 *
 * @since  2.1
 * @param int $payment_id Payment ID
 * @return string The Transaction ID
 */
function rpress_get_payment_transaction_id( $payment_id = 0 ) {
	$payment = new RPRESS_Payment( $payment_id );
	return $payment->transaction_id;
}
/**
 * Sets a Transaction ID in post meta for the given Payment ID
 *
 * @since  2.1
 * @param int $payment_id Payment ID
 * @param string $transaction_id The transaction ID from the gateway
 * @return mixed Meta ID if successful, false if unsuccessful
 */
function rpress_set_payment_transaction_id( $payment_id = 0, $transaction_id = '' ) {
	if ( empty( $payment_id ) || empty( $transaction_id ) ) {
		return false;
	}
	$transaction_id = apply_filters( 'rpress_set_payment_transaction_id', $transaction_id, $payment_id );
	return rpress_update_payment_meta( $payment_id, '_rpress_payment_transaction_id', $transaction_id );
}
/**
 * Retrieve the purchase ID based on the purchase key
 *
 * @since 1.0
 * @global object $wpdb Used to query the database using the WordPress
 *   Database API
 * @param string $key the purchase key to search for
 * @return int $purchase Purchase ID
 */
function rpress_get_purchase_id_by_key( $key ) {
	global $wpdb;
	$global_key_string = 'rpress_purchase_id_by_key' . $key;
	global $global_key_string;
	if ( null !== $global_key_string ) {
		return $global_key_string;
	}
	$purchase = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_rpress_payment_purchase_key' AND meta_value = %s LIMIT 1", $key ) );
	if ( $purchase != NULL ) {
		$global_key_string = $purchase;
		return $global_key_string;
	}
	return 0;
}
/**
 * Retrieve the purchase ID based on the transaction ID
 *
 * @since 2.4
 * @global object $wpdb Used to query the database using the WordPress
 *   Database API
 * @param string $key the transaction ID to search for
 * @return int $purchase Purchase ID
 */
function rpress_get_purchase_id_by_transaction_id( $key ) {
	global $wpdb;
	$purchase = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_rpress_payment_transaction_id' AND meta_value = %s LIMIT 1", $key ) );
	if ( $purchase != NULL )
		return $purchase;
	return 0;
}
/**
 * Retrieve all notes attached to a purchase
 *
 * @since  1.0.0
 * @param int $payment_id The payment ID to retrieve notes for
 * @param string $search Search for notes that contain a search term
 * @return array $notes Payment Notes
 */
function rpress_get_payment_notes( $payment_id = 0, $search = '' ) {
	if ( empty( $payment_id ) && empty( $search ) ) {
		return false;
	}
	remove_action( 'pre_get_comments', 'rpress_hide_payment_notes', 10 );
	remove_filter( 'comments_clauses', 'rpress_hide_payment_notes_pre_41', 10 );
	$notes = get_comments( array( 'post_id' => $payment_id, 'order' => 'ASC', 'search' => $search ) );
	add_action( 'pre_get_comments', 'rpress_hide_payment_notes', 10 );
	add_filter( 'comments_clauses', 'rpress_hide_payment_notes_pre_41', 10, 2 );
	return $notes;
}
/**
 * Add a note to a payment
 *
 * @since  1.0.0
 * @param int $payment_id The payment ID to store a note for
 * @param string $note The note to store
 * @return int The new note ID
 */
function rpress_insert_payment_note( $payment_id = 0, $note = '' ) {
	if ( empty( $payment_id ) )
		return false;
	do_action( 'rpress_pre_insert_payment_note', $payment_id, $note );
	$note_id = wp_insert_comment( wp_filter_comment( array(
		'comment_post_ID'      => $payment_id,
		'comment_content'      => $note,
		'user_id'              => is_admin() ? get_current_user_id() : 0,
		'comment_date'         => current_time( 'mysql' ),
		'comment_date_gmt'     => current_time( 'mysql', 1 ),
		'comment_approved'     => 1,
		'comment_parent'       => 0,
		'comment_author'       => '',
		'comment_author_IP'    => '',
		'comment_author_url'   => '',
		'comment_author_email' => '',
		'comment_type'         => 'rpress_payment_note'
	) ) );
	do_action( 'rpress_insert_payment_note', $note_id, $payment_id, $note );
	return $note_id;
}
/**
 * Deletes a payment note
 *
 * @since  1.0.0
 * @param int $comment_id The comment ID to delete
 * @param int $payment_id The payment ID the note is connected to
 * @return bool True on success, false otherwise
 */
function rpress_delete_payment_note( $comment_id = 0, $payment_id = 0 ) {
	if( empty( $comment_id ) )
		return false;
	do_action( 'rpress_pre_delete_payment_note', $comment_id, $payment_id );
	$ret = wp_delete_comment( $comment_id, true );
	do_action( 'rpress_post_delete_payment_note', $comment_id, $payment_id );
	return $ret;
}
/**
 * Gets the payment note HTML
 *
 * @since  1.0.0
 * @param object|int $note The comment object or ID
 * @param int $payment_id The payment ID the note is connected to
 * @return string
 */
function rpress_get_payment_note_html( $note, $payment_id = 0 ) {
	if( is_numeric( $note ) ) {
		$note = get_comment( $note );
	}
	if ( ! empty( $note->user_id ) ) {
		$user = get_userdata( $note->user_id );
		$user = $user->display_name;
	} else {
		$user = __( 'RPRESS Bot', 'restropress' );
	}
	$date_format = get_option( 'date_format' ) . ', ' . get_option( 'time_format' );
	$delete_note_url = wp_nonce_url( add_query_arg( array(
		'rpress-action' => 'delete_payment_note',
		'note_id'    => $note->comment_ID,
		'payment_id' => $payment_id
	) ), 'rpress_delete_payment_note_' . $note->comment_ID );
	$note_html = '<div class="rpress-payment-note" id="rpress-payment-note-' . $note->comment_ID . '">';
		$note_html .='<p>';
			$note_html .= '<strong>' . $user . '</strong>&nbsp;&ndash;&nbsp;' . date_i18n( $date_format, strtotime( $note->comment_date ) ) . '<br/>';
			$note_html .= make_clickable( wp_kses_post( $note->comment_content ) );
			$note_html .= '&nbsp;&ndash;&nbsp;<a href="' . esc_url( $delete_note_url ) . '" class="rpress-delete-payment-note" data-note-id="' . absint( $note->comment_ID ) . '" data-payment-id="' . absint( $payment_id ) . '">' . __( 'Delete', 'restropress' ) . '</a>';
		$note_html .= '</p>';
	$note_html .= '</div>';
	return $note_html;
}
/**
 * Exclude notes (comments) on rpress_payment post type from showing in Recent
 * Comments widgets
 *
 * @since 1.0
 * @param obj $query WordPress Comment Query Object
 * @return void
 */
function rpress_hide_payment_notes( $query ) {
	global $wp_version;
	if( version_compare( floatval( $wp_version ), '4.1', '>=' ) ) {
		$types = isset( $query->query_vars['type__not_in'] ) ? $query->query_vars['type__not_in'] : array();
		if( ! is_array( $types ) ) {
			$types = array( $types );
		}
		$types[] = 'rpress_payment_note';
		$query->query_vars['type__not_in'] = $types;
	}
}
add_action( 'pre_get_comments', 'rpress_hide_payment_notes', 10 );
/**
 * Exclude notes (comments) on rpress_payment post type from showing in Recent
 * Comments widgets
 *
 * @since  1.0.0
 * @param array $clauses Comment clauses for comment query
 * @param obj $wp_comment_query WordPress Comment Query Object
 * @return array $clauses Updated comment clauses
 */
function rpress_hide_payment_notes_pre_41( $clauses, $wp_comment_query ) {
	global $wpdb, $wp_version;
	if( version_compare( floatval( $wp_version ), '4.1', '<' ) ) {
		$clauses['where'] .= ' AND comment_type != "rpress_payment_note"';
	}
	return $clauses;
}
add_filter( 'comments_clauses', 'rpress_hide_payment_notes_pre_41', 10, 2 );
/**
 * Exclude notes (comments) on rpress_payment post type from showing in comment feeds
 *
 * @since 1.0.1
 * @param array $where
 * @param obj $wp_comment_query WordPress Comment Query Object
 * @return array $where
 */
function rpress_hide_payment_notes_from_feeds( $where, $wp_comment_query ) {
    global $wpdb;
	$where .= $wpdb->prepare( " AND comment_type != %s", 'rpress_payment_note' );
	return $where;
}
add_filter( 'comment_feed_where', 'rpress_hide_payment_notes_from_feeds', 10, 2 );
/**
 * Remove RPRESS Comments from the wp_count_comments function
 *
 * @since 1.0
 * @param array $stats (empty from core filter)
 * @param int $post_id Post ID
 * @return array Array of comment counts
*/
function rpress_remove_payment_notes_in_comment_counts( $stats, $post_id ) {
	global $wpdb, $pagenow;
	$array_excluded_pages = array( 'index.php', 'edit-comments.php' );
	if( ! in_array( $pagenow, $array_excluded_pages )  ) {
		return $stats;
	}
	$post_id = (int) $post_id;
	if ( apply_filters( 'rpress_count_payment_notes_in_comments', false ) )
		return $stats;
	$stats = wp_cache_get( "comments-{$post_id}", 'counts' );
	if ( false !== $stats )
		return $stats;
	$stats_arr = array();
	$where = 'WHERE comment_type != "rpress_payment_note"';
	if ( $post_id > 0 )
		$where .= $wpdb->prepare( " AND comment_post_ID = %d", $post_id );
	$count = $wpdb->get_results( "SELECT comment_approved, COUNT( * ) AS num_comments FROM {$wpdb->comments} {$where} GROUP BY comment_approved", ARRAY_A );
	$total = 0;
	$approved = array( '0' => 'moderated', '1' => 'approved', 'spam' => 'spam', 'trash' => 'trash', 'post-trashed' => 'post-trashed' );
	foreach ( (array) $count as $row ) {
		// Don't count post-trashed toward totals
		if ( 'post-trashed' != $row['comment_approved'] && 'trash' != $row['comment_approved'] )
			$total += $row['num_comments'];
		if ( isset( $approved[$row['comment_approved']] ) ) {
			$temp = $approved[$row['comment_approved']];
			$stats_arr[$temp] = $row['num_comments'];
		}
	}
	$stats_arr['total_comments'] = $total;
	foreach ( $approved as $key ) {
		if ( empty($stats_arr[$key]) )
			$stats_arr[$key] = 0;
	}
	$stats_arr = (object) $stats_arr;
	wp_cache_set( "comments-{$post_id}", $stats_arr, 'counts' );
	return $stats_arr;
}
add_filter( 'wp_count_comments', 'rpress_remove_payment_notes_in_comment_counts', 10, 2 );
/**
 * Filter where older than one week
 *
 * @since  1.0.0
 * @param string $where Where clause
 * @return string $where Modified where clause
*/
function rpress_filter_where_older_than_week( $where = '' ) {
	// Payments older than one week
	$start = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
	$where .= " AND post_date <= '{$start}'";
	return $where;
}
/**
 * get discount by payment_id
 *
 * @since  2.5.6
 * @param int $payment_id
 * @return string discount value
*/
function rpress_get_discount_price_by_payment_id( $payment_id = 0 ) {
    if( empty( $payment_id ) )
        return false;
    $cart_contents  = rpress_get_payment_meta_cart_details( $payment_id, true );
    $user_info  	= rpress_get_payment_meta_user_info( $payment_id, true );
    $discount_code = isset( $user_info['discount'] ) ? $user_info['discount'] : '';
    $discount = 0;
    $discount_data  = rpress_get_discount_by_code( $discount_code );
    if( !$discount_data ) return false;
    $discount_type 	= rpress_get_discount_type( $discount_data->ID );
    if( $discount_type == 'flat' ){
	    if ( is_array( $cart_contents ) && !empty( $cart_contents ) ) {
	        // foreach( $cart_contents as $key => $cart_content ) {
	        //     $discount = isset( $cart_content['discount'] ) ? floatval($cart_content['discount']) : 0;
	        // }
            $discount =$discount_data->get_amount();
	    }
    }
    if($discount_type == 'percent' ){
	    if ( is_array( $cart_contents ) && !empty( $cart_contents ) ) {
            foreach( $cart_contents as $key => $cart_content ) {
	            $discount += isset( $cart_content['discount'] ) ? floatval($cart_content['discount']) : 0;
	        }
	    }
    }
    $discount_value = apply_filters( 'rpress_discount_price_by_payment', $discount );
    return rpress_currency_filter( rpress_format_amount( $discount_value ) );
}
