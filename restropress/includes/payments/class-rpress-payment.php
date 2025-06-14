<?php
/**
 * Payments
 *
 * This class is for working with payments in RPRESS.
 *
 * @package     RPRESS
 * @subpackage  Classes/Payment
 * @copyright   Copyright (c) 2014, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 1.0
 */
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;
/**
 * RPRESS_Payment Class
 *
 * @since  1.0.0
 */
class RPRESS_Payment {
	/**
	 * The Payment we are working with
	 *
	 * @var int
	 * @access private
	 * @since  1.0.0
	 */
	/**
	 * The Payment ID
	 *
	 * @since  1.0.0
	 * @var    integer
	 */
	public    $ID  = 0;
	protected $_ID = 0;
	/**
	 * Identify if the payment is a new one or existing
	 *
	 * @since  1.0.0
	 * @var boolean
	 */
	protected $new = false;
	/**
	 * The Payment number (for use with sequential payments)
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $number = '';
	/**
	 * The Gateway mode the payment was made in
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $mode = 'live';
	/**
	 * The Unique Payment Key
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $key = '';
	/**
	 * The total amount the payment is for
	 * Includes items, taxes, fees, and discounts
	 *
	 * @since  1.0.0
	 * @var float
	 */
	protected $total = 0.00;
	/**
	 * The Subtotal fo the payment before taxes
	 *
	 * @since  1.0.0
	 * @var float
	 */
	protected $subtotal = 0;
	/**
	 * The amount of tax for this payment
	 *
	 * @since  1.0.0
	 * @var float
	 */
	protected $tax = 0;
	/**
	 * The amount the payment has been discounted through discount codes
	 *
	 * @since 1.0
	 * @var int
	 */
	protected $discounted_amount = 0;
	/**
	 * The tax rate charged on this payment
	 *
	 * @since 1.0
	 * @var float
	 */
	protected $tax_rate = '';
	/**
	 * Array of global fees for this payment
	 *
	 * @since  1.0.0
	 * @var array
	 */
	protected $fees = array();
	/**
	 * The sum of the fee amounts
	 *
	 * @since  1.0.0
	 * @var float
	 */
	protected $fees_total = 0;
	/**
	 * Any discounts applied to the payment
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $discounts = 'none';
	/**
	 * The date the payment was created
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $date = '';
	/**
	 * The date the payment was marked as 'complete'
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $completed_date = '';
	/**
	 * The status of the payment
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $status      = 'pending';
	protected $post_status = 'pending'; // Same as $status but here for backwards compat
	/**
	 * When updating, the old status prior to the change
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $old_status = '';
	/**
	 * The display name of the current payment status
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $status_nicename = '';
	/**
	 * The customer ID that made the payment
	 *
	 * @since  1.0.0
	 * @var integer
	 */
	protected $customer_id = null;
	/**
	 * The User ID (if logged in) that made the payment
	 *
	 * @since  1.0.0
	 * @var integer
	 */
	protected $user_id = 0;
	/**
	 * The first name of the payee
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $first_name = '';
	/**
	 * The last name of the payee
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $last_name = '';
	/**
	 * The email used for the payment
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $email = '';
	/**
	 * Legacy (not to be accessed) array of user information
	 *
	 * @since  1.0.0
	 * @var array
	 */
	private   $user_info = array();
	/**
	 * Legacy (not to be accessed) payment meta array
	 *
	 * @since  1.0.0
	 * @var array
	 */
	private   $payment_meta = array();
	/**
	 * Customer delivery address
	 *
	 * @since  1.0.0
	 * @var array
	 */
	protected $delivery_address = array();
	/**
	 * Order Notes
	 *
	 * @since  1.0.0
	 * @var array
	 */
	protected $order_note = '';
	/**
	 * The physical address used for the payment if provided
	 *
	 * @since  1.0.0
	 * @var array
	 */
	protected $address = array();
	/**
	 * The transaction ID returned by the gateway
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $transaction_id = '';
	/**
	 * Array of fooditems for this payment
	 *
	 * @since  1.0.0
	 * @var array
	 */
	protected $fooditems = array();
	/**
	 * Delivery type for this payment
	 *
	 * @since  1.0
	 * @var array
	 */
	protected $delivery_type = '';
	/**
	 * Delivery time for this payment
	 *
	 * @since  1.0
	 * @var array
	 */
	protected $delivery_time = '';
	/**
	 * Delivery Fee for this payment
	 *
	 * @since  1.0
	 * @var array
	 */
	protected $delivery_fee = '';
	/**
	 * Delivery Location
	 *
	 * @since  2.0.1
	 * @var array
	 */
	protected $delivery_location = '';
	/**
	 * Delivery Location Pos
	 *
	 * @since  2.0.1
	 * @var array
	 */
	protected $delivery_location_pos = '';
  /**
   * Delivery Date
   *
   * @since  2.0.5
   * @var string
   */
  protected $delivery_date = '';
	/**
	 * IP Address payment was made from
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $ip = '';
	/**
	 * The gateway used to process the payment
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $gateway = '';
	/**
	 * The the payment was made with
	 *
	 * @since  1.0.0
	 * @var string
	 */
	protected $currency = '';
	/**
	 * The cart details array
	 *
	 * @since  1.0.0
	 * @var array
	 */
	protected $cart_details = array();
	/**
	 * Allows the files for this payment to be fooditemed unlimited times (when fooditem limits are enabled)
	 *
	 * @since  1.0.0
	 * @var boolean
	 */
	protected $has_unlimited_fooditems = false;
	/**
	 * Array of items that have changed since the last save() was run
	 * This is for internal use, to allow fewer update_payment_meta calls to be run
	 *
	 * @since  1.0.0
	 * @var array
	 */
	private $pending;
	/**
	 * The parent payment (if applicable)
	 *
	 * @since  1.0.0
	 * @var integer
	 */
	protected $parent_payment = 0;
	/**
	 * Setup the RPRESS Payments class
	 *
	 * @since  1.0.0
	 * @param int $payment_id A given payment
	 * @return mixed void|false
	 */
	public function __construct( $payment_or_txn_id = false, $by_txn = false ) {
		global $wpdb;
		if( empty( $payment_or_txn_id ) ) {
			return false;
		}
		if ( $by_txn ) {
			$query      = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_rpress_payment_transaction_id' AND meta_value = '%s'", $payment_or_txn_id );
			$payment_id = $wpdb->get_var( $query );
			if ( empty( $payment_id ) ) {
				return false;
			}
		} else {
			$payment_id = absint( $payment_or_txn_id );
		}
		$this->setup_payment( $payment_id );
	}
	/**
	 * Magic GET function
	 *
	 * @since  1.0.0
	 * @param  string $key  The property
	 * @return mixed        The value
	 */
	public function __get( $key ) {
		if ( method_exists( $this, 'get_' . $key ) ) {
			$value = call_user_func( array( $this, 'get_' . $key ) );
		} else {
			$value = $this->$key;
		}
		return $value;
	}
	/**
	 * Magic SET function
	 *
	 * Sets up the pending array for the save method
	 *
	 * @since  1.0.0
	 * @param string $key   The property name
	 * @param mixed $value  The value of the property
	 */
	public function __set( $key, $value ) {
		$ignore = array( 'fooditems', 'cart_details', 'fees', '_ID' );
		if ( $key === 'status' ) {
			$this->old_status = $this->status;
		}
		if ( ! in_array( $key, $ignore ) ) {
			$this->pending[ $key ] = $value;
		}
		if( '_ID' !== $key ) {
			$this->$key = $value;
		}
	}
	/**
	 * Magic ISSET function, which allows empty checks on protected elements
	 *
	 * @since  1.0.0
	 * @param  string  $name The attribute to get
	 * @return boolean       If the item is set or not
	 */
	public function __isset( $name ) {
		if ( property_exists( $this, $name) ) {
			return false === empty( $this->$name );
		} else {
			return null;
		}
	}
	/**
	 * Setup payment properties
	 *
	 * @since  1.0.0
	 * @param  int $payment_id The payment ID
	 * @return bool            If the setup was successful or not
	 */
	private function setup_payment( $payment_id ) {
		$this->pending = array();
		if ( empty( $payment_id ) ) {
			return false;
		}
		$payment = get_post( $payment_id );
		if( ! $payment || is_wp_error( $payment ) ) {
			return false;
		}
		if( 'rpress_payment' !== $payment->post_type ) {
			return false;
		}
		// Allow extensions to perform actions before the payment is loaded
		do_action( 'rpress_pre_setup_payment', $this, $payment_id );
		// Primary Identifier
		$this->ID              = absint( $payment_id );
		// Protected ID that can never be changed
		$this->_ID             = absint( $payment_id );
		// We have a payment, get the generic payment_meta item to reduce calls to it
		$this->payment_meta    = $this->get_meta();
		// Status and Dates
		$this->date            = $payment->post_date;
		$this->completed_date  = $this->setup_completed_date();
		$this->status          = $payment->post_status;
		$this->post_status     = $this->status;
		$this->mode            = $this->setup_mode();
		$this->parent_payment  = $payment->post_parent;
		$all_payment_statuses  = rpress_get_payment_statuses();
		$this->status_nicename = array_key_exists( $this->status, $all_payment_statuses ) ? $all_payment_statuses[ $this->status ] : ucfirst( $this->status );
		// Items
		$this->fees            = $this->setup_fees();
		$this->cart_details    = $this->setup_cart_details();
		$this->fooditems       = $this->setup_fooditems();
		// Currency Based
		$this->total           = $this->setup_total();
		//$this->total           = '';
		$this->tax             = $this->setup_tax();
		$this->tax_rate        = $this->setup_tax_rate();
		$this->fees_total      = $this->setup_fees_total();
		$this->subtotal        = $this->setup_subtotal();
		$this->currency        = $this->setup_currency();
		// Gateway based
		$this->gateway         = $this->setup_gateway();
		$this->transaction_id  = $this->setup_transaction_id();
		// User based
		$this->ip              = $this->setup_ip();
		$this->customer_id     = $this->setup_customer_id();
		$this->user_id         = $this->setup_user_id();
		$this->email           = $this->setup_email();
		$this->user_info       = $this->setup_user_info();
		$this->address         = $this->setup_address();
		$this->discounts       = $this->user_info['discount'];
		$this->first_name      = $this->user_info['first_name'];
		$this->last_name       = $this->user_info['last_name'];
		// Other Identifiers
		$this->key             = $this->setup_payment_key();
		$this->number          = $this->setup_payment_number();
		// Additional Attributes
		$this->has_unlimited_fooditems = $this->setup_has_unlimited();
		// Allow extensions to add items to this object via hook
		do_action( 'rpress_setup_payment', $this, $payment_id );
		return true;
	}
	/**
	 * Create the base of a payment.
	 *
	 * @since  1.0.0
	 * @return int|bool False on failure, the payment ID on success.
	 */
	private function insert_payment() {
		// Construct the payment title
		$payment_title = '';
		if ( ! empty( $this->first_name ) && ! empty( $this->last_name ) ) {
			$payment_title = $this->first_name . ' ' . $this->last_name;
		} else if ( ! empty( $this->first_name ) && empty( $this->last_name ) ) {
			$payment_title = $this->first_name;
		} else if ( ! empty( $this->email ) && is_email( $this->email ) ) {
			$payment_title = $this->email;
		}
		if ( empty( $this->key ) ) {
			$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
			$this->key = strtolower( md5( $this->email . gmdate( 'Y-m-d H:i:s' ) . $auth_key . uniqid( 'rpress', true ) ) );  // Unique key
			$this->pending['key'] = $this->key;
		}
		if ( empty( $this->ip ) ) {
			$this->ip = rpress_get_ip();
			$this->pending['ip'] = $this->ip;
		}
		$payment_data = array(
			'price'        => $this->total,
			'date'         => $this->date,
			'user_email'   => $this->email,
			'purchase_key' => $this->key,
			'currency'     => $this->currency,
			'fooditems'    => $this->fooditems,
			'user_info' => array(
				'id'         => $this->user_id,
				'email'      => $this->email,
				'first_name' => $this->first_name,
				'last_name'  => $this->last_name,
				'discount'   => $this->discounts,
				'address'    => $this->address,
			),
			'cart_details' => $this->cart_details,
			'status'       => $this->status,
			'fees'         => $this->fees,
		);
		$args = apply_filters( 'rpress_insert_payment_args', array(
			'post_title'    => $payment_title,
			'post_status'   => $this->status,
			'post_type'     => 'rpress_payment',
			'post_date'     => ! empty( $this->date ) ? $this->date : null,
			'post_date_gmt' => ! empty( $this->date ) ? get_gmt_from_date( $this->date ) : null,
			'post_parent'   => $this->parent_payment,
		), $payment_data );
		// Create a blank payment
		$payment_id = wp_insert_post( $args );
		if ( ! empty( $payment_id ) ) {
			$this->ID  = $payment_id;
			$this->_ID = $payment_id;
			$customer = $this->maybe_create_customer();
			$this->customer_id            = $customer->id;
			$this->pending['customer_id'] = $this->customer_id;
			$customer->attach_payment( $this->ID, false );
			/**
			 * This run of the rpress_payment_meta filter is for backwards compatibility purposes. The filter will also run in the RPRESS_Payment::save
			 * method. By keeping this here, it retains compatbility of adding payment meta prior to the payment being inserted, as was previously supported
			 * by rpress_insert_payment().
			 *
			 */
			$this->payment_meta = apply_filters( 'rpress_payment_meta', $this->payment_meta, $payment_data );
			$this->delivery_address = apply_filters( 'rpress_delivery_address_meta', $this->delivery_address );
			$this->order_note = apply_filters( 'rpress_order_note_meta', $this->order_note );
			if ( ! empty( $this->payment_meta['fees'] ) ) {
				$this->fees = array_merge( $this->payment_meta['fees'], $this->fees );
				foreach( $this->fees as $fee ) {
					$this->increase_fees( $fee['amount'] );
				}
			}
			if (rpress_get_option( 'enable_sequential' ) ) {
				echo $number       = rpress_get_next_payment_number();
				$this->number = rpress_format_payment_number( $number );
				$this->update_meta( '_rpress_payment_number', $this->number );
				update_option( 'rpress_last_payment_number', $number );
			}
			$this->update_meta( '_rpress_payment_meta', $this->payment_meta );
			$this->update_meta( '_rpress_delivery_address', $this->delivery_address );
			$this->update_meta( '_rpress_order_note', $this->order_note );
			$this->new    = true;
		}
		return $this->ID;
	}
	/**
	 * One items have been set, an update is needed to save them to the database.
	 *
	 * @return bool  True of the save occurred, false if it failed or wasn't needed
	 */
	public function save() {
		global $rpress_logs;
		$saved = false;
		if ( empty( $this->ID ) ) {
			$payment_id = $this->insert_payment();
			if ( false === $payment_id ) {
				$saved = false;
			} else {
				$this->ID = $payment_id;
			}
		}
		if( $this->ID !== $this->_ID ) {
			$this->ID = $this->_ID;
		}
		$customer = $this->maybe_create_customer();
		if ( $this->customer_id != $customer->id ) {
			$this->customer_id            = $customer->id;
			$this->pending['customer_id'] = $this->customer_id;
		}
		// If we have something pending, let's save it
		if ( ! empty( $this->pending ) ) {
			$total_increase = 0;
			$total_decrease = 0;
			foreach ( $this->pending as $key => $value ) {
				switch( $key ) {
					case 'fooditems':
						// Update totals for pending fooditems
						foreach ( $this->pending[ $key ] as $item ) {
							switch( $item['action'] ) {
								case 'add':
									$price = $item['price'];
									$taxes = $item['tax'];
									if ( 'publish' === $this->status || 'complete' === $this->status || 'revoked' === $this->status ) {
										// Add sales logs
										$log_date =  date_i18n( 'Y-m-d G:i:s', current_time( 'timestamp' ) );
										$price_id = isset( $item['item_number']['options']['price_id'] ) ? $item['item_number']['options']['price_id'] : 0;
										$y = 0;
										while ( $y < $item['quantity'] ) {
											rpress_record_sale_in_log( $item['id'], $this->ID, $price_id, $log_date );
											$y++;
										}
										$increase_earnings = $price;
										if ( ! empty( $item['fees'] ) ) {
											foreach ( $item['fees'] as $fee ) {
												// Only let negative fees affect the earnings
												if ( $fee['amount'] > 0 ) {
													continue;
												}
												$increase_earnings += (float) $fee['amount'];
											}
										}
										$fooditem = new RPRESS_Fooditem( $item['id'] );
										$fooditem->increase_sales( $item['quantity'] );
										$fooditem->increase_earnings( $increase_earnings );
										$total_increase += $price;
									}
									break;
								case 'remove':
									$meta_query = array();
									$meta_query[] = array(
										'key'     => '_rpress_log_payment_id',
										'value'   => $this->ID,
										'compare' => '=',
									);
									if( ! empty( $item['price_id'] ) || 0 === (int) $item['price_id'] ) {
										$meta_query[] = array(
											'key'     => '_rpress_log_price_id',
											'value'   => (int) $item['price_id'],
											'compare' => '='
										);
									}
									$log_args = array(
										'post_parent'    => $item['id'],
										'posts_per_page' => $item['quantity'],
										'meta_query'     => $meta_query,
										'log_type'       => 'sale'
									);
									$found_logs = $rpress_logs->get_connected_logs( $log_args );
									if( $found_logs ) {
										foreach ( $found_logs as $log ) {
											wp_delete_post( $log->ID, true );
										}
									}
									if ( 'publish' === $this->status || 'complete' === $this->status || 'revoked' === $this->status ) {
										$fooditem = new RPRESS_Fooditem( $item['id'] );
										$fooditem->decrease_sales( $item['quantity'] );
										$decrease_amount = $item['amount'];
										if ( ! empty( $item['fees'] ) ) {
											foreach( $item['fees'] as $fee ) {
												// Only let negative fees affect the earnings
												if ( $fee['amount'] > 0 ) {
													continue;
												}
												$decrease_amount += $fee['amount'];
											}
										}
										$fooditem->decrease_earnings( $decrease_amount );
										$total_decrease += $item['amount'];
									}
									break;
								case 'modify':
									if ( 'publish' === $this->status || 'complete' === $this->status || 'revoked' === $this->status ) {
										$log_count_change = 0;
										if ( $item['previous_data']['quantity'] != $item['quantity'] ) {
											$log_count_change = $item['previous_data']['quantity'] - $item['quantity'];
											// Find existing logs.
											$meta_query   = array();
											$meta_query[] = array(
												'key'     => '_rpress_log_payment_id',
												'value'   => $this->ID,
												'compare' => '=',
											);
											if ( isset( $item['price_id'] ) ) {
												if ( ! empty( $item[ 'price_id' ] ) || 0 === (int) $item[ 'price_id' ] ) {
													$meta_query[] = array(
														'key'     => '_rpress_log_price_id',
														'value'   => (int) $item[ 'price_id' ],
														'compare' => '='
													);
												}
											}
											$log_args = array(
												'post_parent'    => $item[ 'id' ],
												'meta_query'     => $meta_query,
												'log_type'       => 'sale'
											);
											$existing_logs = $rpress_logs->get_connected_logs( $log_args );
											if ( count( $existing_logs ) > $item['quantity'] ) {
												// We have to remove some logs, since quantity has been reduced.
												$number_of_logs = count( $existing_logs ) - $item['quantity'];
												$logs_to_remove = array_slice( $existing_logs, 0, $number_of_logs );
												foreach ( $logs_to_remove as $log ) {
													wp_delete_post( $log->ID );
												}
											} elseif ( count( $existing_logs ) < $item['quantity'] ) {
												// We have to add some logs, since quantity has been increased.
												$log_date = date_i18n( 'Y-m-d G:i:s', strtotime( $this->completed_date ) );
												$price_id = isset( $item['item_number']['options']['price_id'] ) ? $item['item_number']['options']['price_id'] : 0;
												$number_of_logs = $item['quantity'] - count( $existing_logs );
												$y = 0;
												while ( $y < $number_of_logs ) {
													rpress_record_sale_in_log( $item['id'], $this->ID, $price_id, $log_date );
													$y ++;
												}
											}
										}
										$fooditem = new RPRESS_Fooditem( $item['id'] );
										// Change the number of sales for the fooditem.
										if ( $log_count_change > 0 ) {
											$fooditem->decrease_sales( $log_count_change );
										} elseif ( $log_count_change < 0 ) {
											$log_count_change = absint( $log_count_change );
											$fooditem->increase_sales( $log_count_change );
										}
										// Change the earnings for the product.
										$price_change = $item['previous_data']['price'] - $item['price'];
										if ( $price_change > 0 ) {
											$fooditem->decrease_earnings( $price_change );
											$total_increase -= $price_change;
										} elseif ( $price_change < 0 ) {
											$price_change = -( $price_change );
											$fooditem->increase_earnings( $price_change );
											$total_decrease += $price_change;
										}
									}
									break;
							}
						}
						break;
					case 'fees':
						if ( 'publish' !== $this->status && 'complete' !== $this->status && 'revoked' !== $this->status && ! $this->is_recoverable() ) {
							break;
						}
						if ( empty( $this->pending[ $key ] ) ) {
							break;
						}
						foreach ( $this->pending[ $key ] as $fee ) {
							switch( $fee['action'] ) {
								case 'add':
									$total_increase += $fee['amount'];
									break;
								case 'remove':
									$total_decrease += $fee['amount'];
									break;
							}
						}
						break;
					case 'status':
						$this->update_status( $this->status );
						break;
					case 'gateway':
						$this->update_meta( '_rpress_payment_gateway', $this->gateway );
						break;
					case 'mode':
						$this->update_meta( '_rpress_payment_mode', $this->mode );
						break;
					case 'transaction_id':
						$this->update_meta( '_rpress_payment_transaction_id', $this->transaction_id );
						break;
					case 'ip':
						$this->update_meta( '_rpress_payment_user_ip', $this->ip );
						break;
					case 'customer_id':
						$this->update_meta( '_rpress_payment_customer_id', $this->customer_id );
						$customer = new RPRESS_Customer( $this->customer_id );
						$customer->attach_payment( $this->ID, false );
						break;
					case 'user_id':
						$this->update_meta( '_rpress_payment_user_id', $this->user_id );
						$this->user_info['id'] = $this->user_id;
						break;
					case 'first_name':
						$this->user_info['first_name'] = $this->first_name;
						break;
					case 'last_name':
						$this->user_info['last_name'] = $this->last_name;
						break;
					case 'delivery_type':
						$service_type = rpress_selected_service();
						$this->update_meta( '_rpress_delivery_type', $service_type );
						break;
					case 'delivery_time':
						$delivery_time = isset($_COOKIE['service_time']) ? sanitize_text_field( $_COOKIE['service_time'] )  : '';
						$this->update_meta( '_rpress_delivery_time', $delivery_time );
						break;
  					case 'delivery_date':
    					$service_date = isset( $_COOKIE['service_date'] ) ? sanitize_text_field( $_COOKIE['service_date'] ): '';
      					$this->update_meta( '_rpress_delivery_date', $service_date );
      					break;
					case 'discounts':
						if ( ! is_array( $this->discounts ) ) {
							$this->discounts = explode( ',', $this->discounts );
						}
						$this->user_info['discount'] = implode( ',', $this->discounts );
						break;
					case 'address':
						$this->user_info['address'] = $this->address;
						break;
					case 'email':
						$this->payment_meta['email'] = $this->email;
						$this->user_info['email']    = $this->email;
						$this->update_meta( '_rpress_payment_user_email', $this->email );
						break;
					case 'key':
						$this->update_meta( '_rpress_payment_purchase_key', $this->key );
						break;
					case 'tax_rate':
						$this->update_meta( '_rpress_payment_tax_rate', $this->tax_rate );
						break;
					case 'number':
						$this->update_meta( '_rpress_payment_number', $this->number );
						break;
					case 'date':
						$args = array(
							'ID'        => $this->ID,
							'post_date' => $this->date,
							'edit_date' => true,
						);
						wp_update_post( $args );
						break;
					case 'completed_date':
						$this->update_meta( '_rpress_completed_date', $this->completed_date );
						break;
					case 'has_unlimited_fooditems':
						$this->update_meta( '_rpress_payment_unlimited_fooditems', $this->has_unlimited_fooditems );
						break;
					case 'parent_payment':
						$args = array(
							'ID'          => $this->ID,
							'post_parent' => $this->parent_payment,
						);
						wp_update_post( $args );
						break;
					default:
						/**
						 * Used to save non-standard data. Developers can hook here if they want to save
						 * specific payment data when $payment->save() is run and their item is in the $pending array
						 */
						do_action( 'rpress_payment_save', $this, $key );
						break;
				}
			}
			if ( ! $this->in_process() ) {
				$customer = new RPRESS_Customer( $this->customer_id );
				$total_change = $total_increase - $total_decrease;
				if ( $total_change < 0 ) {
					$total_change = -( $total_change );
					// Decrease the customer's purchase stats
					$customer->decrease_value( $total_change );
					rpress_decrease_total_earnings( $total_change );
				} else if (  $total_change > 0 ) {
					// Increase the customer's purchase stats
					$customer->increase_value( $total_change );
					rpress_increase_total_earnings( $total_change );
				}
			}
			$this->update_meta( '_rpress_payment_total', $this->total );
			$this->update_meta( '_rpress_payment_tax', $this->tax );
			$subtotal = (float) $this->total - (float) $this->tax;
			$this->update_meta( '_rpress_payment_subtotal', $subtotal );
			$this->fooditems    = array_values( $this->fooditems );
			$new_meta = array(
				'fooditems'     => $this->fooditems,
				'cart_details'  => $this->cart_details,
				'fees'          => $this->fees,
				'currency'      => $this->currency,
				'user_info'     => is_array( $this->user_info ) ? $this->user_info : array(),
				'date'          => $this->date,
				'email'         => $this->email,
			);
			// Do some merging of user_info before we merge it all, to honor the rpress_payment_meta filter
			if ( ! empty( $this->payment_meta['user_info'] ) ) {
				$stored_discount = ! empty( $new_meta['user_info']['discount'] ) ? $new_meta['user_info']['discount'] : '';
				$new_meta[ 'user_info' ] = array_replace_recursive( (array) $this->payment_meta[ 'user_info' ], $new_meta[ 'user_info' ] );
				if ( 'none' !== $stored_discount ) {
					$new_meta['user_info']['discount'] = $stored_discount;
				}
			}
			$meta        = $this->get_meta();
			$merged_meta = array_merge( $meta, $new_meta );
			$payment_data = array(
				'price'        => $this->total,
				'date'         => $this->date,
				'user_email'   => $this->email,
				'purchase_key' => $this->key,
				'currency'     => $this->currency,
				'fooditems'    => $this->fooditems,
				'user_info' => array(
					'id'         => $this->user_id,
					'email'      => $this->email,
					'first_name' => $this->first_name,
					'last_name'  => $this->last_name,
					'discount'   => $this->discounts,
					'address'    => $this->address,
				),
				'cart_details' => $this->cart_details,
				'status'       => $this->status,
				'fees'         => $this->fees,
			);
			$merged_meta = apply_filters( 'rpress_payment_meta', $merged_meta, $payment_data );
			// Only save the payment meta if it's changed
			if ( md5( serialize( $meta ) ) !== md5( serialize( $merged_meta) ) ) {
				$updated     = $this->update_meta( '_rpress_payment_meta', $merged_meta );
				if ( false !== $updated ) {
					$saved = true;
				}
			}
			$this->pending = array();
			$saved         = true;
		}
		if ( true === $saved ) {
			$this->setup_payment( $this->ID );
			/**
			 * This action fires anytime that $payment->save() is run, allowing developers to run actions
			 * when a payment is updated
			 */
			do_action( 'rpress_payment_saved', $this->ID, $this );
		}
		/**
		 * Update the payment in the object cache
		 */
		$cache_key = md5( 'rpress_payment' . $this->ID );
		wp_cache_set( $cache_key, $this, 'payments' );
		return $saved;
	}
	/**
	 * Add a fooditem to a given payment
	 *
	 * @since  1.0.0
	 * @param int   $fooditem_id The fooditem to add
	 * @param array $args Other arguments to pass to the function
	 * @param array $options List of fooditem options
	 * @return bool True when successful, false otherwise
	 */
	public function add_fooditem( $fooditem_id = 0, $args = array(), $options = array() ) {
		$fooditem = new RPRESS_Fooditem( $fooditem_id );
		// Bail if this post isn't a fooditem
		if( ! $fooditem || $fooditem->post_type !== 'fooditem' ) {
			return false;
		}
		// Set some defaults
		$defaults = array(
			'quantity'    => 1,
			'price_id'    => false,
			'item_price'  => false,
			'discount'    => 0,
			'tax'         => 0.00,
			'fees'        => array(),
		);
		$args = wp_parse_args( apply_filters( 'rpress_payment_add_fooditem_args', $args, $fooditem->ID ), $defaults );
		// Allow overriding the price
		if( false !== $args['item_price'] ) {
			$item_price = $args['item_price'];
		} else {
			// Deal with variable pricing
			if( rpress_has_variable_prices( $fooditem->ID ) ) {
				$prices = get_post_meta( $fooditem->ID, 'rpress_variable_prices', true );
				if( $args['price_id'] && array_key_exists( $args['price_id'], (array) $prices ) ) {
					$item_price = $prices[$args['price_id']]['amount'];
				} else {
					$item_price       = rpress_get_lowest_price_option( $fooditem->ID );
					$args['price_id'] = rpress_get_lowest_price_id( $fooditem->ID );
				}
			} else {
				$item_price = rpress_get_fooditem_price( $fooditem->ID );
			}
		}
		// Sanitizing the price here so we don't have a dozen calls later
		$item_price = rpress_sanitize_amount( $item_price );
		$quantity   = absint( $args['quantity'] );
		$amount     = round( $item_price * $quantity, rpress_currency_decimal_filter() );
		// Calculating addon prices
		// $addon_prices = 0;
        $rpress_cart = new RPRESS_Cart();
  		if( !empty($options) ) {
            foreach ( $options as &$addon_item ) { // Notice the '&' here
                $addon_price = (float) $rpress_cart->get_addon_price( $addon_item['addon_id'], $options, $addon_item['price'] );
               $addon_price= round($addon_price,rpress_currency_decimal_filter());
                $amount += $addon_price;
                $addon_item['price_without_tax'] = $addon_price; // Now this will be reflected in the original $options array
            }
            // Unset the reference to avoid unintended modifications
            unset($addon_item);
    		// $addon_prices = wp_list_pluck($options, 'price');
    		// $addon_prices = array_sum($addon_prices);
    		// $addon_prices = floatval($addon_prices) * $quantity;
    	}
		// Setup the fooditems meta item
		$new_fooditem = array(
			'id'       => $fooditem->ID,
			'quantity' => $quantity,
		);
		$default_options = array(
			'quantity' => $quantity,
		);
		if ( false !== $args['price_id'] ) {
			$default_options['price_id'] = (int) $args['price_id'];
		}
		$options                 = wp_parse_args( $options, $default_options );
		$new_fooditem['options'] = $options;
		$this->fooditems[] = $new_fooditem;
		$discount   = $args['discount'];
		$subtotal   = $amount;
		$tax        = $args['tax'];
		// if ( rpress_prices_include_tax() ) {
		// 	$subtotal -= round( $tax, rpress_currency_decimal_filter() );
		// }
		$total = $subtotal - $discount + $tax;
		// Do not allow totals to go negative
		if( $total < 0 ) {
			$total = 0;
		}
		// Silly item_number array
		$item_number = array(
			'id'        => $fooditem->ID,
			'quantity'  => $quantity,
			'options'   => $options,
		);
        $variation_name = rpress_get_price_option_name( $fooditem->ID, (int) $args['price_id'] );
		$this->cart_details[] = array(
			'name'        => $fooditem->post_title,
			'id'          => $fooditem->ID,
            'variation_name' => $variation_name,
            'sku' => $fooditem->get_sku(),
			'instruction' => isset( $args['instruction'] ) ? $args['instruction'] : '' ,
			'item_number' => $item_number,
			'item_price'  => round( $item_price, rpress_currency_decimal_filter() ),
			'quantity'    => $quantity,
			'discount'    => $discount,
			'subtotal'    => round( $subtotal, rpress_currency_decimal_filter() ),
			'tax'         => round( $tax, rpress_currency_decimal_filter() ),
			'fees'        => $args['fees'],
			'price'       => round( $total, rpress_currency_decimal_filter() ),
			'addon_items' => $options,
		);
		$added_fooditem = end( $this->cart_details );
		$added_fooditem['action']  = 'add';
		$this->pending['fooditems'][] = $added_fooditem;
		reset( $this->cart_details );
		$this->increase_subtotal( $subtotal - $discount );
		$this->increase_tax( $tax );
		return true;
	}
	/**
	 * Remove a fooditem from the payment
	 *
	 * @since  1.0.0
	 * @param  int   $fooditem_id The fooditem ID to remove
	 * @param  array $args        Arguments to pass to identify (quantity, amount, price_id)
	 * @return bool               If the item was removed or not
	 */
	public function remove_fooditem( $fooditem_id, $args = array() ) {
		// Set some defaults
		$defaults = array(
			'quantity'   => 1,
			'item_price' => false,
			'price_id'   => false,
			'cart_index' => false,
		);
		$args = wp_parse_args( $args, $defaults );
		$fooditem = new RPRESS_Fooditem( $fooditem_id );
		/**
		 * Bail if this post isn't a fooditem post type.
		 *
		 * We need to allow this to process though for a missing post ID, in case it's a fooditem that was deleted.
		 */
		if( ! empty( $fooditem->ID ) && $fooditem->post_type !== 'fooditem' ) {
			return false;
		}
		foreach ( $this->fooditems as $key => $item ) {
			if ( (int) $fooditem_id !== (int) $item['id'] ) {
				continue;
			}
			if ( false !== $args['price_id'] ) {
				if ( isset( $item['options']['price_id'] ) && (int) $args['price_id'] !== (int) $item['options']['price_id'] ) {
					continue;
				}
			} elseif ( false !== $args['cart_index'] ) {
				$cart_index = absint( $args['cart_index'] );
				$cart_item  = ! empty( $this->cart_details[ $cart_index ] ) ? $this->cart_details[ $cart_index ] : false;
				if ( ! empty( $cart_item ) ) {
					// If the cart index item isn't the same fooditem ID, don't remove it
					if ( $cart_item['id'] != $item['id'] ) {
						continue;
					}
					// If this item has a price ID, make sure it matches the cart indexed item's price ID before removing
					if ( ( isset( $item['options']['price_id'] ) && isset( $cart_item['item_number']['options']['price_id'] ) )
					     && (int) $item['options']['price_id'] !== (int) $cart_item['item_number']['options']['price_id'] ) {
						continue;
					}
				}
			}
			$item_quantity = $this->fooditems[ $key ]['quantity'];
			if ( $item_quantity > $args['quantity'] ) {
				$this->fooditems[ $key ]['quantity'] -= $args['quantity'];
				break;
			} else {
				unset( $this->fooditems[ $key ] );
				break;
			}
		}
		$found_cart_key = false;
		if ( false === $args['cart_index'] ) {
			foreach ( $this->cart_details as $cart_key => $item ) {
				if ( $fooditem_id != $item['id'] ) {
					continue;
				}
				if ( false !== $args['price_id'] ) {
					if ( isset( $item['item_number']['options']['price_id'] ) && (int) $args['price_id'] !== (int) $item['item_number']['options']['price_id'] ) {
						continue;
					}
				}
				if ( false !== $args['item_price'] ) {
					if ( isset( $item['item_price'] ) && (float) $args['item_price'] != (float) $item['item_price'] ) {
						continue;
					}
				}
				$found_cart_key = $cart_key;
				break;
			}
		} else {
			$cart_index = absint( $args['cart_index'] );
			if ( ! array_key_exists( $cart_index, $this->cart_details ) ) {
				return false; // Invalid cart index passed.
			}
			if ( (int) $this->cart_details[ $cart_index ]['id'] !== (int) $fooditem_id ) {
				return false; // We still need the proper Item ID to be sure.
			}
			$found_cart_key = $cart_index;
		}
		$orig_quantity = $this->cart_details[ $found_cart_key ]['quantity'];
		if ( $orig_quantity > $args['quantity'] ) {
			$this->cart_details[ $found_cart_key ]['quantity'] -= $args['quantity'];
			$item_price   = $this->cart_details[ $found_cart_key ]['item_price'];
			$tax          = $this->cart_details[ $found_cart_key ]['tax'];
			$discount     = ! empty( $this->cart_details[ $found_cart_key ]['discount'] ) ? $this->cart_details[ $found_cart_key ]['discount'] : 0;
			// The total reduction equals the number removed * the item_price
			$total_reduced = round( $item_price * $args['quantity'], rpress_currency_decimal_filter() );
			$tax_reduced   = round( ( $tax / $orig_quantity ) * $args['quantity'], rpress_currency_decimal_filter() );
			$new_quantity = $this->cart_details[ $found_cart_key ]['quantity'];
			$new_tax      = $this->cart_details[ $found_cart_key ]['tax'] - $tax_reduced;
			$new_subtotal = $new_quantity * $item_price;
			$new_discount = 0;
			$new_total    = 0;
			$this->cart_details[ $found_cart_key ]['subtotal'] = $new_subtotal;
			$this->cart_details[ $found_cart_key ]['discount'] = $new_discount;
			$this->cart_details[ $found_cart_key ]['tax']      = $new_tax;
			$this->cart_details[ $found_cart_key ]['price']    = $new_subtotal - $new_discount + $new_tax;
		} else {
			$total_reduced = $this->cart_details[ $found_cart_key ]['item_price'];
			$tax_reduced   = $this->cart_details[ $found_cart_key ]['tax'];
			$found_fees = array();
			if ( ! empty( $this->cart_details[ $found_cart_key ]['fees'] ) ) {
				$found_fees = $this->cart_details[ $found_cart_key ]['fees'];
				foreach ( $found_fees as $key => $fee ) {
					$this->remove_fee( $key );
				}
			}
			unset( $this->cart_details[ $found_cart_key ] );
		}
		$pending_args             = $args;
		$pending_args['id']       = $fooditem_id;
		$pending_args['amount']   = $total_reduced;
		$pending_args['price_id'] = false !== $args['price_id'] ? $args['price_id'] : false;
		$pending_args['quantity'] = $args['quantity'];
		$pending_args['action']   = 'remove';
		$pending_args['fees']     = isset( $found_fees ) ? $found_fees : array();
		$this->pending['fooditems'][] = $pending_args;
		$this->decrease_subtotal( $total_reduced );
		$this->decrease_tax( $tax_reduced );
		return true;
	}
	/**
	 * Alter a limited set of properties of a cart item
	 *
	 * @since 1.0
	 * @param bool  $cart_index
	 * @param array $args
	 *
	 * @return bool
	 */
	public function modify_cart_item( $cart_index = false, $args = array(), $addon_data = array() ) {
		if ( false === $cart_index ) {
			return false;
		}
		if ( ! array_key_exists( $cart_index, $this->cart_details ) ) {
			return false;
		}
		$current_args  = $this->cart_details[ $cart_index ];
		if( isset( $current_args['addon_items'] ) && is_array( $current_args['addon_items'] ) ) {
			foreach($current_args['addon_items'] as $key => $addon_set_data ) {
				if( is_array($addon_set_data) && !empty($addon_set_data) ) {
					unset($current_args['addon_items'][$key]);
				}
			}
		}
		$current_args['addon_items'] = isset( $addon_data[$current_args['id']] ) ? $addon_data[$current_args['id']] : '';
		$allowed_items = apply_filters( 'rpress_allowed_cart_item_modifications', array(
			'item_price', 'tax', 'discount', 'quantity'
		) );
		// Remove any items we don't want to modify.
		foreach ( $args as $key => $arg ) {
			if ( ! in_array( $key, $allowed_items ) ) {
				unset( $args[ $key ] );
			}
		}
		$merged_item = array_merge( $current_args, $args );
		// Sort the current and new args, and checksum them. If no changes. No need to fire a modification.
		ksort( $current_args );
		ksort( $merged_item );
		// Format the item_price correctly now
		$merged_item['item_price'] = rpress_sanitize_amount( $merged_item['item_price'] );
		$new_subtotal                       = floatval( $merged_item['item_price'] ) * $merged_item['quantity'];
		$merged_item['price']               = $new_subtotal + $merged_item['tax'];
		$this->cart_details[ $cart_index ]  = $merged_item;
		$modified_fooditem                  = $merged_item;
		$modified_fooditem['action']        = 'modify';
		$modified_fooditem['previous_data'] = $current_args;
		$this->pending['fooditems'][] = $modified_fooditem;
		if ( $new_subtotal > $current_args['subtotal'] ) {
			$this->increase_subtotal( ( $new_subtotal - $modified_fooditem['discount'] ) - $current_args['subtotal'] );
		} else {
			$this->decrease_subtotal( $current_args['subtotal'] - ( $new_subtotal - $modified_fooditem['discount'] ) );
		}
		if ( $modified_fooditem['tax'] > $current_args['tax'] ) {
			$this->increase_tax( $modified_fooditem['tax'] - $current_args['tax'] );
		} else {
			$this->increase_tax( (float) $current_args['tax'] - (float) $modified_fooditem['tax'] );
		}
		return true;
	}
	/**
	 * Add a fee to a given payment
	 *
	 * @since  1.0.0
	 * @param  array $args Array of arguments for the fee to add
	 * @param bool $global
	 * @return bool If the fee was added
	 */
	public function add_fee( $args, $global = true ) {
		$default_args = array(
			'label'       => '',
			'amount'      => 0,
			'type'        => 'fee',
			'id'          => '',
			'no_tax'      => false,
			'fooditem_id' => 0,
		);
		$fee = wp_parse_args( $args, $default_args );
		$this->fees[] = $fee;
		$added_fee               = $fee;
		$added_fee['action']     = 'add';
		$this->pending['fees'][] = $added_fee;
		reset( $this->fees );
		$this->increase_fees( $fee['amount'] );
		return true;
	}
	/**
	 * Remove a fee from the payment
	 *
	 * @since  1.0.0
	 * @param  int $key The array key index to remove
	 * @return bool     If the fee was removed successfully
	 */
	public function remove_fee( $key ) {
		$removed = $this->remove_fee_by( 'index', $key );
		return $removed;
	}
	/**
	 * Remove a fee by the defined attributed
	 *
	 * @since  1.0.0
	 * @param  string      $key    The key to remove by
	 * @param  int|string  $value  The value to search for
	 * @param  boolean $global     False - removes the first value it finds, True - removes all matches
	 * @return boolean             If the item is removed
	 */
	public function remove_fee_by( $key, $value, $global = false ) {
		$allowed_fee_keys = apply_filters( 'rpress_payment_fee_keys', array(
			'index', 'label', 'amount', 'type',
		) );
		if ( ! in_array( $key, $allowed_fee_keys ) ) {
			return false;
		}
		$removed = false;
		if ( 'index' === $key && array_key_exists( $value, $this->fees ) ) {
			$removed_fee             = $this->fees[ $value ];
			$removed_fee['action']   = 'remove';
			$this->pending['fees'][] = $removed_fee;
			$this->decrease_fees( $removed_fee['amount'] );
			unset( $this->fees[ $value ] );
			$removed = true;
		} else if ( 'index' !== $key ) {
			foreach ( $this->fees as $index => $fee ) {
				if ( isset( $fee[ $key ] ) && $fee[ $key ] == $value ) {
					$removed_fee             = $fee;
					$removed_fee['action']   = 'remove';
					$this->pending['fees'][] = $removed_fee;
					$this->decrease_fees( $removed_fee['amount'] );
					unset( $this->fees[ $index ] );
					$removed = true;
					if ( false === $global ) {
						break;
					}
				}
			}
		}
		if ( true === $removed ) {
			$this->fees = array_values( $this->fees );
		}
		return $removed;
	}
	/**
	 * Get the fees, filterable by type
	 *
	 * @since  1.0.0
	 * @param  string $type All, item, fee
	 * @return array        The Fees for the type specified
	 */
	public function get_fees( $type = 'all' ) {
		$fees    = array();
		if ( ! empty( $this->fees ) && is_array( $this->fees ) ) {
			foreach ( $this->fees as $fee_id => $fee ) {
				if( 'all' != $type && ! empty( $fee['type'] ) && $type != $fee['type'] ) {
					continue;
				}
				$fee['id'] = $fee_id;
				$fees[]    = $fee;
			}
		}
		return apply_filters( 'rpress_get_payment_fees', $fees, $this->ID, $this );
	}
	/**
	 * Add a note to a payment
	 *
	 * @since  1.0.0
	 * @param string $note The note to add
	 * @return void
	 */
	public function add_note( $note = false ) {
		// Bail if no note specified
		if( ! $note ) {
			return false;
		}
		rpress_insert_payment_note( $this->ID, esc_html( $note ) );
	}
	/**
	 * Increase the payment's subtotal
	 *
	 * @since  1.0.0
	 * @param  float  $amount The amount to increase the payment subtotal by
	 * @return void
	 */
	private function increase_subtotal( $amount = 0.00 ) {
		$amount          = (float) $amount;
		$this->subtotal += $amount;
		$this->recalculate_total();
	}
	/**
	 * Decrease the payment's subtotal
	 *
	 * @since  1.0.0
	 * @param  float  $amount The amount to decrease the payment subtotal by
	 * @return void
	 */
	private function decrease_subtotal( $amount = 0.00 ) {
		$amount          = (float) $amount;
		$this->subtotal -= $amount;
		if ( $this->subtotal < 0 ) {
			$this->subtotal = 0;
		}
		$this->recalculate_total();
	}
	/**
	 * Increase the payment's subtotal
	 *
	 * @since  1.0.0
	 * @param  float  $amount The amount to increase the payment subtotal by
	 * @return void
	 */
	private function increase_fees( $amount = 0.00 ) {
		$amount            = (float) $amount;
		$this->fees_total += $amount;
	}
	/**
	 * Decrease the payment's subtotal
	 *
	 * @since  1.0.0
	 * @param  float  $amount The amount to decrease the payment subtotal by
	 * @return void
	 */
	private function decrease_fees( $amount = 0.00 ) {
		$amount            = (float) $amount;
		$this->fees_total -= $amount;
		if ( $this->fees_total < 0 ) {
			$this->fees_total = 0;
		}
		$this->recalculate_total();
	}
	/**
	 * Set or update the total for a payment
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function recalculate_total() {
		$this->total = $this->subtotal + $this->tax + $this->fees_total;
	}
	/**
	 * Increase the payment's tax by the provided amount
	 *
	 * @since  1.0.0
	 * @param  float  $amount The amount to increase the payment tax by
	 * @return void
	 */
	public function increase_tax( $amount = 0.00 ) {
		$amount       = (float) $amount;
		$this->tax   += $amount;
		$this->recalculate_total();
	}
	/**
	 * Decrease the payment's tax by the provided amount
	 *
	 * @since  1.0.0
	 * @param  float  $amount The amount to reduce the payment tax by
	 * @return void
	 */
	public function decrease_tax( $amount = 0.00 ) {
		$amount     = (float) $amount;
		$this->tax -= $amount;
		if ( $this->tax < 0 ) {
			$this->tax = 0;
		}
		$this->recalculate_total();
	}
	/**
	 * Set the payment status and run any status specific changes necessary
	 *
	 * @since  1.0.0
	 *
	 * @param  string $status The status to set the payment to
	 * @return bool Returns if the status was successfully updated
	 */
	public function update_status( $status = false ) {
		if ( $status == 'completed' || $status == 'complete' ) {
			$status = 'publish';
		}
		$old_status = ! empty( $this->old_status ) ? $this->old_status : false;
		if ( $old_status === $status ) {
			return false; // Don't permit status changes that aren't changes
		}
		$do_change = apply_filters( 'rpress_should_update_payment_status', true, $this->ID, $status, $old_status );
		$updated = false;
		if ( $do_change ) {
			do_action( 'rpress_before_payment_status_change', $this->ID, $status, $old_status );
			$update_fields = array( 'ID' => $this->ID, 'post_status' => $status, 'edit_date' => current_time( 'mysql' ) );
			$updated = wp_update_post( apply_filters( 'rpress_update_payment_status_fields', $update_fields ) );
			$this->status = $status;
			$this->post_status = $status;
			$all_payment_statuses  = rpress_get_payment_statuses();
			$this->status_nicename = array_key_exists( $status, $all_payment_statuses ) ? $all_payment_statuses[ $status ] : ucfirst( $status );
			// Process any specific status functions
			switch( $status ) {
				case 'refunded':
					$this->process_refund();
					break;
				case 'failed':
					$this->process_failure();
					break;
				case 'pending' || 'processing':
					$this->process_pending();
					break;
			}
			do_action( 'rpress_update_payment_status', $this->ID, $status, $old_status );
		}
		return $updated;
	}
	/**
	 * Change the status of the payment to refunded, and run the necessary changes
	 *
	 * @since  1.0.0.7
	 * @return void
	 */
	public function refund() {
		$this->old_status        = $this->status;
		$this->status            = 'refunded';
		$this->pending['status'] = $this->status;
		$this->save();
	}
	/**
	 * Get a post meta item for the payment
	 *
	 * @since  1.0.0
	 * @param  string  $meta_key The Meta Key
	 * @param  boolean $single   Return single item or array
	 * @return mixed             The value from the post meta
	 */
	public function get_meta( $meta_key = '_rpress_payment_meta', $single = true ) {
		$meta = get_post_meta( $this->ID, $meta_key, $single );
		if ( $meta_key === '_rpress_payment_meta' ) {
			if ( empty( $meta ) ) {
				$meta = array();
			}
			// #5228 Fix possible data issue introduced in 2.6.12
			if ( is_array( $meta ) && isset( $meta[0] ) ) {
				$bad_meta = $meta[0];
				unset( $meta[0] );
				if ( is_array( $bad_meta ) ) {
					$meta = array_merge( $meta, $bad_meta );
				}
				update_post_meta( $this->ID, '_rpress_payment_meta', $meta );
			}
			// Payment meta was simplified in RPRESS v1.5, so these are here for backwards compatibility
			if ( empty( $meta['key'] ) ) {
				$meta['key'] = $this->setup_payment_key();
			}
			if ( empty( $meta['email'] ) ) {
				$meta['email'] = $this->setup_email();
			}
			if ( empty( $meta['date'] ) ) {
				$meta['date'] = get_post_field( 'post_date', $this->ID );
			}
		}
		
		$meta = apply_filters( 'rpress_get_payment_meta_' . $meta_key, $meta, $this->ID );
		if ( is_serialized( $meta ) ) {
			preg_match( '/[oO]\s*:\s*\d+\s*:\s*"\s*(?!(?i)(stdClass))/', $meta, $matches );
			if ( ! empty( $matches ) ) {
				$meta = array();
			}
		}
		return apply_filters( 'rpress_get_payment_meta', $meta, $this->ID, $meta_key );
	}
	/**
	 * Update the post meta
	 *
	 * @since  1.0.0
	 * @param  string $meta_key   The meta key to update
	 * @param  string $meta_value The meta value
	 * @param  string $prev_value Previous meta value
	 * @return int|bool           Meta ID if the key didn't exist, true on successful update, false on failure
	 */
	public function update_meta( $meta_key = '', $meta_value = '', $prev_value = '' ) {
		if ( empty( $meta_key ) ) {
			return false;
		}
		if( '_rpress_payment_purchase_key' == $meta_key ) {
			$current_meta = $this->get_meta();
			$current_meta[ 'key' ] = $meta_value;
			update_post_meta( $this->ID, '_rpress_payment_meta', $current_meta );
		} else if ( $meta_key == 'key' || $meta_key == 'date' ) {
			$current_meta = $this->get_meta();
			$current_meta[ $meta_key ] = $meta_value;
			$meta_key     = '_rpress_payment_meta';
			$meta_value   = $current_meta;
		} else if ( $meta_key == 'email' || $meta_key == '_rpress_payment_user_email' ) {
			$meta_value = apply_filters( 'rpress_rpress_update_payment_meta_' . $meta_key, $meta_value, $this->ID );
			update_post_meta( $this->ID, '_rpress_payment_user_email', $meta_value );
			$current_meta = $this->get_meta( '_rpress_payment_meta' );
			if ( is_array( $current_meta ) ){
				$current_meta['user_info']['email']  = $meta_value;
			}
			$meta_key     = '_rpress_payment_meta';
			$meta_value   = $current_meta;
		}
		$meta_value = apply_filters( 'rpress_update_payment_meta_' . $meta_key, $meta_value, $this->ID );
		return update_post_meta( $this->ID, $meta_key, $meta_value, $prev_value );
	}
	/**
	 * Add an item to the payment meta
	 *
	 * @since 1.0.0
	 * @param string $meta_key
	 * @param string $meta_value
	 * @param bool   $unique
	 *
	 * @return bool|false|int
	 */
	public function add_meta( $meta_key = '', $meta_value = '', $unique = false ) {
		if ( empty( $meta_key ) ) {
			return false;
		}
		return add_post_meta( $this->ID, $meta_key, $meta_value, $unique );
	}
	/**
	 * Delete an item from payment meta
	 *
	 * @since 1.0.0
	 * @param string $meta_key
	 * @param string $meta_value
	 *
	 * @return bool
	 */
	public function delete_meta( $meta_key = '', $meta_value = '' ) {
		if ( empty( $meta_key ) ) {
			return false;
		}
		return delete_post_meta( $this->ID, $meta_key, $meta_value );
	}
	/**
	 * Determines if this payment is able to be resumed by the user.
	 *
	 * @since 1.0
	 * @return bool
	 */
	public function is_recoverable() {
		$recoverable = false;
		$recoverable_statuses = apply_filters( 'rpress_recoverable_payment_statuses', array( 'pending', 'abandoned', 'failed' ) );
		if ( in_array( $this->status, $recoverable_statuses ) && empty( $this->transaction_id ) ) {
			$recoverable = true;
		}
		return $recoverable;
	}
	/**
	 * Returns the URL that a customer can use to resume a payment, or false if it's not recoverable.
	 *
	 * @since 1.0
	 * @return bool|string
	 */
	public function get_recovery_url() {
		if ( ! $this->is_recoverable() ) {
			return false;
		}
		$recovery_url = add_query_arg( array( 'rpress_action' => 'recover_payment', 'payment_id' => $this->ID ), rpress_get_checkout_uri() );
		return apply_filters( 'rpress_payment_recovery_url', $recovery_url, $this );
	}
	/**
	 * When a payment is set to a status of 'refunded' process the necessary actions to reduce stats
	 *
	 * @since  1.0.0.7
	 * @access private
	 * @return void
	 */
	private function process_refund() {
		$process_refund = true;
		// If the payment was not in publish or revoked status, don't decrement stats as they were never incremented
		if ( ( 'publish' != $this->old_status && 'revoked' != $this->old_status ) || 'refunded' != $this->status ) {
			$process_refund = false;
		}
		// Allow extensions to filter for their own payment types, Example: Recurring Payments
		$process_refund = apply_filters( 'rpress_should_process_refund', $process_refund, $this );
		if ( false === $process_refund ) {
			return;
		}
		do_action( 'rpress_pre_refund_payment', $this );
		$decrease_store_earnings = apply_filters( 'rpress_decrease_store_earnings_on_refund', true, $this );
		$decrease_customer_value = apply_filters( 'rpress_decrease_customer_value_on_refund', true, $this );
		$decrease_purchase_count = apply_filters( 'rpress_decrease_customer_purchase_count_on_refund', true, $this );
		$this->maybe_alter_stats( $decrease_store_earnings, $decrease_customer_value, $decrease_purchase_count );
		$this->delete_sales_logs();
		// Clear the This Month earnings (this_monththis_month is NOT a typo)
		delete_transient( md5( 'rpress_earnings_this_monththis_month' ) );
		do_action( 'rpress_post_refund_payment', $this );
	}
	/**
	 * Process when a payment is set to failed, decrement discount usages and other stats
	 *
	 * @since  1.0.0.7
	 * @return void
	 */
	private function process_failure() {
		$discounts = $this->discounts;
		if ( 'none' === $discounts || empty( $discounts ) ) {
			return;
		}
		if ( ! is_array( $discounts ) ) {
			$discounts = array_map( 'trim', explode( ',', $discounts ) );
		}
		foreach ( $discounts as $discount ) {
			rpress_decrease_discount_usage( $discount );
		}
	}
	/**
	 * Process when a payment moves to pending
	 *
	 * @since 1.00
	 * @return void
	 */
	private function process_pending() {
		$process_pending = true;
		// If the payment was not in publish or revoked status, don't decrement stats as they were never incremented
		if ( ( 'publish' != $this->old_status && 'revoked' != $this->old_status ) || ! $this->in_process() ) {
			$process_pending = false;
		}
		// Allow extensions to filter for their own payment types, Example: Recurring Payments
		$process_pending = apply_filters( 'rpress_should_process_pending', $process_pending, $this );
		if ( false === $process_pending ) {
			return;
		}
		$decrease_store_earnings = apply_filters( 'rpress_decrease_store_earnings_on_pending', true, $this );
		$decrease_customer_value = apply_filters( 'rpress_decrease_customer_value_on_pending', true, $this );
		$decrease_purchase_count = apply_filters( 'rpress_decrease_customer_purchase_count_on_pending', true, $this );
		$this->maybe_alter_stats( $decrease_store_earnings, $decrease_customer_value, $decrease_purchase_count );
		$this->delete_sales_logs();
		$this->completed_date = false;
		$this->update_meta( '_rpress_completed_date', '' );
		// Clear the This Month earnings (this_monththis_month is NOT a typo)
		delete_transient( md5( 'rpress_earnings_this_monththis_month' ) );
	}
	/**
	 * Used during the process of moving to refunded or pending, to decrement stats
	 *
	 * @since 1.00
	 * @param  bool   $alter_store_earnings          If the method should alter the store earnings
	 * @param  bool   $alter_customer_value          If the method should reduce the customer value
	 * @param  bool   $alter_customer_purchase_count If the method should reduce the customer's purchase count
	 * @return void
	 */
	private function maybe_alter_stats( $alter_store_earnings, $alter_customer_value, $alter_customer_purchase_count ) {
		rpress_undo_purchase( false, $this->ID );
		// Decrease store earnings
		if ( true === $alter_store_earnings ) {
			rpress_decrease_total_earnings( $this->total );
		}
		// Decrement the stats for the customer
		if ( ! empty( $this->customer_id ) ) {
			$customer = new RPRESS_Customer( $this->customer_id );
			if ( true === $alter_customer_value ) {
				$customer->decrease_value( $this->total );
			}
			if ( true === $alter_customer_purchase_count ) {
				$customer->decrease_purchase_count();
			}
		}
	}
	/**
	 * Delete sales logs for this purchase
	 *
	 * @since 1.00
	 * @return void
	 */
	private function delete_sales_logs() {
		global $rpress_logs;
		// Remove related sale log entries
		$rpress_logs->delete_logs(
			null,
			'sale',
			array(
				array(
					'key'   => '_rpress_log_payment_id',
					'value' => $this->ID,
				),
			)
		);
	}
	/**
	 * Setup functions only, these are not to be used by developers.
	 * These functions exist only to allow the setup routine to be backwards compatible with our old
	 * helper functions.
	 *
	 * These will run whenever setup_payment is called, which should only be called once.
	 * To update an attribute, update it directly instead of re-running the setup routine
	 */
	/**
	 * Setup the payment completed date
	 *
	 * @since  1.0.0
	 * @return string The date the payment was completed
	 */
	private function setup_completed_date() {
		$payment = get_post( $this->ID );
		if( 'pending' == $payment->post_status || 'preapproved' == $payment->post_status || 'processing' == $payment->post_status ) {
			return false; // This payment was never completed
		}
		$date = ( $date = $this->get_meta( '_rpress_completed_date', true ) ) ? $date : $payment->date;
		return $date;
	}
	/**
	 * Setup the payment mode
	 *
	 * @since  1.0.0
	 * @return string The payment mode
	 */
	private function setup_mode() {
		return $this->get_meta( '_rpress_payment_mode' );
	}
	/**
	 * Setup the payment total
	 *
	 * @since  1.0.0
	 * @return float The payment total
	 */
	private function setup_total() {
		$amount = $this->get_meta( '_rpress_payment_total', true );
		if ( empty( $amount ) && '0.00' != $amount ) {
			$meta   = $this->get_meta( '_rpress_payment_meta', true );
			$meta   = maybe_unserialize( $meta );
			if ( isset( $meta['amount'] ) ) {
				$amount = $meta['amount'];
			}
		}
		return $amount;
	}
	/**
	 * Setup the payment tax
	 *
	 * @since  1.0.0
	 * @return float The tax for the payment
	 */
	private function setup_tax() {
		$tax = $this->get_meta( '_rpress_payment_tax', true );
		// We don't have tax as it's own meta and no meta was passed
		if ( '' === $tax ) {
			$tax = isset( $this->payment_meta['tax'] ) ? $this->payment_meta['tax'] : 0;
		}
		$subtotal 		= $this->setup_subtotal();
		$discount_code  = $this->setup_discounts();
		$discount_id  	= !empty( $discount_code ) ? rpress_get_discount_id_by_code( $discount_code ) : '';
		$amount      	= !empty( $discount_id ) ? rpress_get_discount_amount( $discount_id ) : '';
		if ( !empty( $discount_id ) && 'flat' === rpress_get_discount_type( $discount_id ) ) {
			$tax = rpress_calculate_tax( $subtotal - $amount,false,false,true );
		}
		return $tax;
	}
	/**
	 * Setup the payment tax rate
	 *
	 * @since 1.0
	 * @return float The tax rate for the payment
	 */
	private function setup_tax_rate() {
		return $this->get_meta( '_rpress_payment_tax_rate', true );
	}
	/**
	 * Setup the payment fees
	 *
	 * @since 1.00
	 * @return float The fees total for the payment
	 */
	private function setup_fees_total() {
		$fees_total = (float) 0.00;
		$payment_fees = isset( $this->payment_meta['fees'] ) ? $this->payment_meta['fees'] : array();
		if ( ! empty( $payment_fees ) ) {
			foreach ( $payment_fees as $fee ) {
				$fees_total += (float) $fee['amount'];
			}
		}
		return $fees_total;
	}
	/**
	 * Setup the payment subtotal
	 *
	 * @since  1.0.0
	 * @return float The subtotal of the payment
	 */
	private function setup_subtotal() {
		$cart_details = $this->cart_details;
		if ( is_array( $cart_details ) ) {
			$cart = new RPRESS_Cart();
			$subtotal     = 0;
			foreach ( $cart_details as $item ) {
				if ( isset( $item['subtotal'] ) ) {
					$fooditem_id 		= $item['id'];
					$quantity   		= max( 1, $item['quantity'] ); // Force quantity to 1
					$options 			= isset( $item['item_number']['options'] ) ? $item['item_number']['options'] : array();
					$price_id 			= isset( $item['item_number']['options']['price_id'] ) ? $item['item_number']['options']['price_id'] : null;
					$item_price 		= $cart->get_item_price( $fooditem_id, $item, $options, $price_id, false, $item['price'] );
		        	$item_price = round($item_price,rpress_currency_decimal_filter());
                    $addon_tax_price = $item_addon_prices = 0;
		        	if ( !empty( $item['addon_items'] ) ) {
			    		foreach ( $item['addon_items'] as  $addon_item ) {
			    			$addon_id = !empty( $addon_item['addon_id'] ) ? $addon_item['addon_id'] : '';
			    			if ( empty( $addon_id ) ) continue;
			    			$addon_tax_price += !empty( $addon_item['price'] ) ? floatval( $addon_item['price'] ) : 0.00;
			    			$addon_price = !empty( $addon_item['price'] ) ? floatval( $addon_item['price'] ) : 0.00;
			    			$addon_price = $cart->get_addon_price( $addon_id, $item, $addon_price );
			    			$addon_price= round($addon_price,rpress_currency_decimal_filter());
                            $item_addon_prices += $addon_price;
			    		}
		        	}
	        		//Check if Product is variable
	        	  	if ( rpress_has_variable_prices( $fooditem_id ) && !is_null( $price_id ) ) {
	        	    	$item_tax_price = rpress_get_price_option_amount( $fooditem_id, $price_id );
	        	    } else {
	        	  		$item_tax_price = rpress_get_fooditem_price( $fooditem_id );
	        	  	}
	        		$item_subtotal = ( $item_tax_price + $addon_tax_price ) * $quantity;
	        		if ( rpress_prices_include_tax() ) {
	        			$item_subtotal = ( $item_price + $item_addon_prices ) * $quantity;
	        		}
				}
	        	$subtotal += $item_subtotal;
			}
		} else {
			$subtotal  = $this->total;
			$tax       = rpress_use_taxes() ? $this->tax : 0;
			$subtotal -= $tax;
		}
		return $subtotal;
	}
	/**
	 * Setup the payments discount codes
	 *
	 * @since  1.0.0
	 * @return array               Array of discount codes on this payment
	 */
	private function setup_discounts() {
		$discounts = ! empty( $this->payment_meta['user_info']['discount'] ) ? $this->payment_meta['user_info']['discount'] : array();
		return $discounts;
	}
	/**
	 * Setup the currency code
	 *
	 * @since  1.0.0
	 * @return string              The currency for the payment
	 */
	private function setup_currency() {
		$currency = isset( $this->payment_meta['currency'] ) ? $this->payment_meta['currency'] : apply_filters( 'rpress_payment_currency_default', rpress_get_currency(), $this );
		return $currency;
	}
	/**
	 * Setup any fees associated with the payment
	 *
	 * @since  1.0.0
	 * @return array               The Fees
	 */
	private function setup_fees() {
		$payment_fees = isset( $this->payment_meta['fees'] ) ? $this->payment_meta['fees'] : array();
		return $payment_fees;
	}
	/**
	 * Setup the gateway used for the payment
	 *
	 * @since  1.0.0
	 * @return string The gateway
	 */
	private function setup_gateway() {
		$gateway = $this->get_meta( '_rpress_payment_gateway', true );
		return $gateway;
	}
	/**
	 * Setup the transaction ID
	 *
	 * @since  1.0.0
	 * @return string The transaction ID for the payment
	 */
	private function setup_transaction_id() {
		$transaction_id = $this->get_meta( '_rpress_payment_transaction_id', true );
		if ( empty( $transaction_id ) || (int) $transaction_id === (int) $this->ID ) {
			$gateway        = $this->gateway;
			$transaction_id = apply_filters( 'rpress_get_payment_transaction_id-' . $gateway, $this->ID );
		}
		return $transaction_id;
	}
	/**
	 * Setup the IP Address for the payment
	 *
	 * @since  1.0.0
	 * @return string The IP address for the payment
	 */
	private function setup_ip() {
		$ip = $this->get_meta( '_rpress_payment_user_ip', true );
		return $ip;
	}
	/**
	 * Setup the customer ID
	 *
	 * @since  1.0.0
	 * @return int The Customer ID
	 */
	private function setup_customer_id() {
		$customer_id = $this->get_meta( '_rpress_payment_customer_id', true );
		return $customer_id;
	}
	/**
	 * Setup the User ID associated with the purchase
	 *
	 * @since  1.0.0
	 * @return int The User ID
	 */
	private function setup_user_id() {
		$user_id  = $this->get_meta( '_rpress_payment_user_id', true );
		$customer = new RPRESS_Customer( $this->customer_id );
		// Make sure it exists, and that it matches that of the associated customer record
		if( !empty( $customer->user_id ) && ( empty( $user_id ) || (int) $user_id !== (int) $customer->user_id ) ) {
			$user_id = $customer->user_id;
			// Backfill the user ID, or reset it to be correct in the event of data corruption
			$this->update_meta( '_rpress_payment_user_id', $user_id );
		}
		return $user_id;
	}
	/**
	 * Setup the email address for the purchase
	 *
	 * @since  1.0.0
	 * @return string The email address for the payment
	 */
	private function setup_email() {
		$email = $this->get_meta( '_rpress_payment_user_email', true );
		if( empty( $email ) ) {
			$email = RPRESS()->customers->get_column( 'email', $this->customer_id );
		}
		return $email;
	}
	/**
	 * Setup the user info
	 *
	 * @since  1.0.0
	 * @return array               The user info associated with the payment
	 */
	private function setup_user_info() {
		$defaults = array(
			'first_name' => $this->first_name,
			'last_name'  => $this->last_name,
			'discount'   => $this->discounts,
		);
		$user_info    = isset( $this->payment_meta['user_info'] ) ? $this->payment_meta['user_info'] : array();
		if ( is_serialized( $user_info ) ) {
			preg_match( '/[oO]\s*:\s*\d+\s*:\s*"\s*(?!(?i)(stdClass))/', $user_info, $matches );
			if ( ! empty( $matches ) ) {
				$user_info = array();
			}
		}
		// As per Github issue #4248, we need to run maybe_unserialize here still.
		$user_info    = wp_parse_args( maybe_unserialize( $user_info ), $defaults );
		// Ensure email index is in the old user info array
		if( empty( $user_info['email'] ) ) {
			$user_info['email'] = $this->email;
		}
		if ( empty( $user_info ) ) {
			// Get the customer, but only if it's been created
			$customer = new RPRESS_Customer( $this->customer_id );
			if ( $customer->id > 0 ) {
				$name = explode( ' ', $customer->name, 2 );
				$user_info = array(
					'first_name' => $name[0],
					'last_name'  => $name[1],
					'email'      => $customer->email,
					'discount'   => 'none',
				);
			}
		} else {
			// Get the customer, but only if it's been created
			$customer = new RPRESS_Customer( $this->customer_id );
			if ( $customer->id > 0 ) {
				foreach ( $user_info as $key => $value ) {
					if ( ! empty( $value ) ) {
						continue;
					}
					switch( $key ) {
						case 'first_name':
							$name = explode( ' ', $customer->name, 2 );
							$user_info[ $key ] = $name[0];
							break;
						case 'last_name':
							$name      = explode( ' ', $customer->name, 2 );
							$last_name = ! empty( $name[1] ) ? $name[1] : '';
							$user_info[ $key ] = $last_name;
							break;
						case 'email':
							$user_info[ $key ] = $customer->email;
							break;
					}
				}
			}
		}
		return $user_info;
	}
	/**
	 * Setup the Address for the payment
	 *
	 * @since  1.0.0
	 * @return array               The Address information for the payment
	 */
	private function setup_address() {
		$address  = ! empty( $this->payment_meta['user_info']['address'] ) ? $this->payment_meta['user_info']['address'] : array();
		$defaults = array( 'line1' => '', 'line2' => '', 'city' => '', 'country' => '', 'state' => '', 'zip' => '' );
		$address = wp_parse_args( $address, $defaults );
		return $address;
	}
	/**
	 * Setup the payment key
	 *
	 * @since  1.0.0
	 * @return string The Payment Key
	 */
	private function setup_payment_key() {
		$key = $this->get_meta( '_rpress_payment_purchase_key', true );
		return $key;
	}
	/**
	 * Setup the payment number
	 *
	 * @since  1.0.0
	 * @return int|string Integer by default, or string if sequential order numbers is enabled
	 */
	private function setup_payment_number() {
		$number = $this->ID;
		if ( rpress_get_option( 'enable_sequential' ) ) {
			$number = $this->get_meta( '_rpress_payment_number', true );
			if ( ! $number ) {
				$number = $this->ID;
			}
		}
		return $number;
	}
	/**
	 * Setup the cart details
	 *
	 * @since  1.0.0
	 * @return array               The cart details
	 */
	private function setup_cart_details() {
		$cart_details = isset( $this->payment_meta['cart_details'] ) ? maybe_unserialize( $this->payment_meta['cart_details'] ) : array();
		return $cart_details;
	}
	/**
	 * Setup the fooditems array
	 *
	 * @since  1.0.0
	 * @return array               RestroPress associated with this payment
	 */
	private function setup_fooditems() {
		$fooditems = isset( $this->payment_meta['fooditems'] ) ? maybe_unserialize( $this->payment_meta['fooditems'] ) : array();
		return $fooditems;
	}
	/**
	 * Setup the Unlimited fooditems setting
	 *
	 * @since  1.0.0
	 * @return bool If this payment has unlimited fooditems
	 */
	private function setup_has_unlimited() {
		$unlimited = (bool) $this->get_meta( '_rpress_payment_unlimited_fooditems', true );
		return $unlimited;
	}
	/**
	 * Converts this ojbect into an array for special cases
	 *
	 * @return array The payment object as an array
	 */
	public function array_convert() {
		return get_object_vars( $this );
	}
	/**
	 * Retrieve payment cart details
	 *
	 * @since 1.0
	 * @return array Cart details array
	 */
	private function get_cart_details() {
		return apply_filters( 'rpress_payment_cart_details', $this->cart_details, $this->ID, $this );
	}
	/**
	 * Retrieve payment completion date
	 *
	 * @since 1.0
	 * @return string Date payment was completed
	 */
	private function get_completed_date() {
		return apply_filters( 'rpress_payment_completed_date', $this->completed_date, $this->ID, $this );
	}
	/**
	 * Retrieve payment tax
	 *
	 * @since 1.0
	 * @return float Payment tax
	 */
	private function get_tax() {
		return apply_filters( 'rpress_get_payment_tax', $this->tax, $this->ID, $this );
	}
	/**
	 * Retrieve payment subtotal
	 *
	 * @since 1.0
	 * @return float Payment subtotal
	 */
	private function get_subtotal() {
		return apply_filters( 'rpress_get_payment_subtotal', $this->subtotal, $this->ID, $this );
	}
	/**
	 * Retrieve payment discounts
	 *
	 * @since 1.0
	 * @return array Discount codes on payment
	 */
	private function get_discounts() {
		return apply_filters( 'rpress_payment_discounts', $this->discounts, $this->ID, $this );
	}
	/**
	 * Return the discounted amount of the payment.
	 *
	 * @since 1.0
	 * @return float
	 */
	private function get_discounted_amount() {
		$total = $this->total;
		$fees  = $this->fees_total;
		$tax   = $this->tax;
		return floatval( apply_filters( 'rpress_payment_discounted_amount', $total - ( $fees + $tax ), $this ) );
	}
	/**
	 * Retrieve payment currency
	 *
	 * @since 1.0
	 * @return string Payment currency code
	 */
	private function get_currency() {
		return apply_filters( 'rpress_payment_currency_code', $this->currency, $this->ID, $this );
	}
	/**
	 * Retrieve payment gateway
	 *
	 * @since 1.0
	 * @return string Gateway used
	 */
	private function get_gateway() {
		return apply_filters( 'rpress_payment_gateway', $this->gateway, $this->ID, $this );
	}
	/**
	 * Retrieve payment transaction ID
	 *
	 * @since 1.0
	 * @return string Transaction ID from merchant processor
	 */
	private function get_transaction_id() {
		return apply_filters( 'rpress_get_payment_transaction_id', $this->transaction_id, $this->ID, $this );
	}
	/**
	 * Retrieve payment IP
	 *
	 * @since 1.0
	 * @return string Payment IP address
	 */
	private function get_ip() {
		return apply_filters( 'rpress_payment_user_ip', $this->ip, $this->ID, $this );
	}
	/**
	 * Retrieve payment customer ID
	 *
	 * @since 1.0
	 * @return int Payment customer ID
	 */
	private function get_customer_id() {
		return apply_filters( 'rpress_payment_customer_id', $this->customer_id, $this->ID, $this );
	}
	/**
	 * Retrieve payment user ID
	 *
	 * @since 1.0
	 * @return int Payment user ID
	 */
	private function get_user_id() {
		return apply_filters( 'rpress_payment_user_id', $this->user_id, $this->ID, $this );
	}
	/**
	 * Retrieve payment email
	 *
	 * @since 1.0
	 * @return string Payment customer email
	 */
	private function get_email() {
		return apply_filters( 'rpress_payment_user_email', $this->email, $this->ID, $this );
	}
	/**
	 * Retrieve payment user info
	 *
	 * @since 1.0
	 * @return array Payment user info
	 */
	private function get_user_info() {
		return apply_filters( 'rpress_payment_meta_user_info', $this->user_info, $this->ID, $this );
	}
	/**
	 * Retrieve payment billing address
	 *
	 * @since 1.0
	 * @return array Payment billing address
	 */
	private function get_address() {
		return apply_filters( 'rpress_payment_address', $this->address, $this->ID, $this );
	}
	/**
	 * Retrieve payment key
	 *
	 * @since 1.0
	 * @return string Payment key
	 */
	private function get_key() {
		return apply_filters( 'rpress_payment_key', $this->key, $this->ID, $this );
	}
	/**
	 * Retrieve payment number
	 *
	 * @since 1.0
	 * @return int|string Payment number
	 */
	private function get_number() {
		return apply_filters( 'rpress_payment_number', $this->number, $this->ID, $this );
	}
	/**
	 * Retrieve fooditems on payment
	 *
	 * @since 1.0
	 * @return array Payment fooditems
	 */
	private function get_fooditems() {
		return apply_filters( 'rpress_payment_meta_fooditems', $this->fooditems, $this->ID, $this );
	}
	/**
	 * Retrieve unlimited file fooditems status
	 *
	 * @since 1.0
	 * @return bool Is unlimited
	 */
	private function get_unlimited() {
		return apply_filters( 'rpress_payment_unlimited_fooditems', $this->unlimited, $this->ID, $this );
	}
	/**
	 * Easily determine if the payment is in a status of pending some action. Processing is specifically used for eChecks.
	 *
	 * @since 1.0
	 * @return bool
	 */
	private function in_process() {
		$in_process_statuses = array( 'pending', 'processing' );
		return in_array( $this->status, $in_process_statuses );
	}
	/**
	 * Determines if a customer needs to be created given the current payment details.
	 *
	 * @since 1.0
	 *
	 * @return RPRESS_Customer The customer object of the existing customer or new customer.
	 */
	private function maybe_create_customer() {
		$customer = new stdClass;
		if ( did_action( 'rpress_pre_process_purchase' ) && is_user_logged_in() ) {
			$customer = new RPRESS_customer( get_current_user_id(), true );
			// Customer is logged in but used a different email to purchase with so assign to their customer record
			if( ! empty( $customer->id ) && $this->email != $customer->email ) {
				$customer->add_email( $this->email );
			}
		}
		if ( empty( $customer->id ) ) {
			$customer = new RPRESS_Customer( $this->email );
		}
		if ( empty( $customer->id ) ) {
			if( empty( $this->first_name ) && empty( $this->last_name ) ) {
				$name = $this->email;
			} else {
				$name = $this->first_name . ' ' . $this->last_name;
			}
			$customer_data = array(
				'name'        => $name,
				'email'       => $this->email,
				'user_id'     => $this->user_id,
			);
			$customer->create( $customer_data );
		}
		return $customer;
	}
}
