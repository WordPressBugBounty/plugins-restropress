<?php
/**
 * Order History Table Class
 *
 * @package     RPRESS
 * @subpackage  Admin/Payments
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since  1.0.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
/**
 * RPRESS_Payment_History_Table Class
 *
 * Renders the Order History table on the Order History page
 *
 * @since  1.0.0
 */
class RPRESS_Payment_History_Table extends WP_List_Table {
	/**
	 * Number of results to show per page
	 *
	 * @var string
	 * @since  1.0.0
	 */
	public $per_page = 30;
	/**
	 * URL of this page
	 *
	 * @var string
	 * @since 1.0
	 */
	public $base_url;
	/**
	 * Total number of payments
	 *
	 * @var int
	 * @since  1.0.0
	 */
	public $total_count;
	/**
	 * Total number of completed payments
	 *
	 * @var int
	 * @since  1.0.0
	 */
	public $completed_count;
	/**
	 * Total number of pending payments
	 *
	 * @var int
	 * @since  1.0.0
	 */
	public $pending_count;
	/**
	 * Total number of paid payments
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public $paid_count;
	/**
	 * Total number of out for deliver payments
	 *
	 * @var int
	 * @since  1.0.0
	 */
	public $delivery_count;
	/**
	 * Total number of  deliver
	 *
	 * @var int
	 * @since  1.0.0
	 */
	public $pickup_count;
	/**
	 * Total number of  trash
	 *
	 * @var int
	 * @since  1.0.0
	 */
    public $trash_count;
	/**
	 * Total number of  pickup
	 *
	 * @var int
	 * @since  1.0.0
	 */
	public $dinein_count;
	/**
	 * Total number of  dinein
	 *
	 * @var int
	 * @since  1.0.0
	 */
	public $out_for_deliver_count;
	/**
	 * Get things started
	 *
	 * @since  1.0.0
	 * @uses RPRESS_Payment_History_Table::get_payment_counts()
	 * @see WP_List_Table::__construct()
	 */
	public function __construct() {
		global $status, $page;
		// Set parent defaults
		parent::__construct( array(
			'singular' => rpress_get_label_singular(),
			'plural'   => rpress_get_label_plural(),
			'ajax'     => false,
		) );
		$this->get_payment_counts();
		// $this->process_bulk_action();
		$this->base_url = admin_url( 'admin.php?page=rpress-payment-history' );
		add_action( 'admin_footer', array( $this, 'order_preview_template' ) );
	}
	public function service_type_filters() { ?>
    	<div class="rpress-service-type-filters-wrap">
      		<ul class="subsubsub">
	        	<?php $get_service_types = rpress_get_service_types(); ?>
	        	<li>
	          		<a href="<?php echo  esc_url( admin_url('/admin.php?page=rpress-payment-history') ); ?>">
	          			<?php esc_html_e( 'All', 'restropress' ); ?>
	          		</a> |
	          	</li>
	          	<?php
	          	$i = 0;
	          	foreach( $get_service_types as $service_key => $service_label ) : ?>
	          		<li>
	          			<a href="<?php echo esc_url( admin_url('/admin.php?page=rpress-payment-history&service_type='.$service_key) ); ?>">
	          				<?php echo esc_html( $service_label ); ?>
	          			</a>
	          			<?php if ( $i == 0 ) : ?> | <?php endif; ?>
	          		</li>
	          		<?php $i++;
	          	endforeach; ?>
          	</ul>
    	</div>
    <?php }
	public function advanced_filters() {
		$start_date 		= isset( $_GET['start-date'] )   ? sanitize_text_field( $_GET['start-date'] )   : null;
		$end_date   		= isset( $_GET['end-date'] )     ? sanitize_text_field( $_GET['end-date'] )     : null;
		$service_date   	= isset( $_GET['service-date'] ) ? sanitize_text_field( $_GET['service-date'] ) : null;
		$status     		= isset( $_GET['status'] )       ? sanitize_text_field( $_GET['status'] )	   : '';
		$all_gateways   	= rpress_get_payment_gateways();
		$gateways         	= array();
		
		$selected_gateway 	= isset( $_GET['gateway'] ) ? sanitize_text_field( $_GET['gateway'] ) : 'all';
		if ( ! empty( $all_gateways ) ) {
			$gateways['all'] = esc_html__( 'All Gateways', 'restropress' );
			foreach( $all_gateways as $slug => $admin_label ) {
				
				$gateways[ $slug ] = $admin_label['admin_label'];
				
			}
		}
		$all_order_statuses = rpress_get_order_statuses();
		$order_statuses   = array();
		
		$selected_orders = isset( $_GET['order_status'] ) ? sanitize_text_field( $_GET['order_status'] ) : 'all';
		if ( ! empty( $all_order_statuses ) ) {
			$order_statuses['all'] = esc_html__( 'Order Status', 'restropress' );
			foreach( $all_order_statuses as $slug => $admin_label ) {
				
				$order_statuses[ $slug ] = $admin_label;
			}
		}
		
		/**
		 * Allow gateways that aren't registered the standard way to be displayed in the dropdown.
		 *
		 * @since  1.0.0
		 */
		$gateways = apply_filters( 'rpress_payments_table_gateways', $gateways );
		?>
		<div id="rpress-payment-filters">
			<span id="rpress-payment-date-filters">
				<span>
					<label for="start-date"><?php esc_html_e( 'Start Date:', 'restropress' ); ?></label>
					<input type="text" id="start-date" name="start-date" class="rpress_datepicker" value="<?php echo esc_attr( $start_date ); ?>" placeholder="mm/dd/yyyy"/>
				</span>
				<span>
					<label for="end-date"><?php esc_html_e( 'End Date:', 'restropress' ); ?></label>
					<input type="text" id="end-date" name="end-date" class="rpress_datepicker" value="<?php echo esc_attr( $end_date ); ?>" placeholder="mm/dd/yyyy"/>
				</span>
			</span>
			<span id="rpress-payment-gateway-filter">
				<?php
				if ( ! empty( $gateways ) ) {
					echo RPRESS()->html->select( array(
						'options'          => $gateways,
						'name'             => 'gateway',
						'id'               => 'gateway',
						'selected'         => $selected_gateway,
						'show_option_all'  => false,
						'show_option_none' => false
					) );
				}
				?>
			</span>
			<span id="rpress-order-status-filter">
			<?php
			if ( ! empty( $order_statuses ) ) {
				echo RPRESS()->html->select( array(
					'options'          => $order_statuses,
					'name'             => 'order_status',
					'id'               => 'order_status',
					'selected'         => $selected_orders,
					'show_option_all'  => false,
					'show_option_none' => false
				) );
			}
			?>
			</span>
			<span>/</span>
			<span>
				<label for="service-date-filter"><?php esc_html_e( 'Service Date:', 'restropress' ); ?></label>
				<input type="text" id="service-date-filter" name="service-date" class="rpress_datepicker" value="<?php echo esc_attr( $service_date ); ?>" placeholder="mm/dd/yyyy"/>
			</span>
			<span id="rpress-payment-after-core-filters">
				<?php do_action( 'rpress_payment_advanced_filters_after_fields' ); ?>
				<input type="submit" class="button-secondary" value="<?php esc_html_e( 'Apply', 'restropress' ); ?>"/>
			</span>
			<?php if( ! empty( $status ) ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"/>
			<?php endif; ?>
			<?php if( ! empty( $service_date ) || ! empty( $start_date ) || ! empty( $end_date ) || 'all' !== $selected_gateway || 'all' !== $selected_orders ) : ?>
				<a href="<?php echo admin_url( 'admin.php?page=rpress-payment-history' ); ?>" class="button-secondary"><?php esc_html_e( 'Clear Filter', 'restropress' ); ?></a>
			<?php endif; ?>
			<?php do_action( 'rpress_payment_advanced_filters_row' ); ?>
			<?php $this->search_box( esc_html__( 'Search', 'restropress' ), 'rpress-payments' ); ?>
		</div>
	<?php
	}
	/**
	 * Show the search field
	 *
	 * @since  1.0.0
	 *
	 * @param string $text Label for the search box
	 * @param string $input_id ID of the search box
	 *
	 * @return void
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && !$this->has_items() )
			return;
		$input_id = $input_id . '-search-input';
		if ( ! empty( $_REQUEST['orderby'] ) )
			echo '<input type="hidden" name="orderby" value="' . esc_attr( sanitize_text_field( $_REQUEST['orderby'] ) ) . '" />';
		if ( ! empty( $_REQUEST['order'] ) )
			echo '<input type="hidden" name="order" value="' . esc_attr( sanitize_text_field( $_REQUEST['order'] ) ). '" />';
		?>
		<p class="search-box">
			<?php do_action( 'rpress_payment_history_search' ); ?>
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id )?>"><?php echo esc_html( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id )?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, 'button', false, false, array('ID' => 'search-submit') ); ?><br/>
		</p>
		<?php
	}
	/**
	 * Retrieve the view types
	 *
	 * @since  1.0.0
	 * @return array $views All the views available
	 */
	public function get_views() {
		global $rpress_options;
		$current          = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ): '';
		$get_services     = isset( $_GET['service-type'] ) ? sanitize_text_field( $_GET['service-type'] ): '';
		$service_options  = !empty( rpress_get_option( 'enable_service' ) ) ? rpress_get_option( 'enable_service' ) : 'delivery_and_pickup';
		$total_count      = '&nbsp;<span class="count">(' . $this->total_count     . ')</span>';
		$completed_count  = '&nbsp;<span class="count">(' . $this->completed_count . ')</span>';
		$pending_count    = '&nbsp;<span class="count">(' . $this->pending_count   . ')</span>';
		$paid_count       = '&nbsp;<span class="count">(' . $this->paid_count      . ')</span>';
		$delivery_count   = '&nbsp;<span class="count">(' . $this->delivery_count  . ')</span>';
		$pickup_count     = '&nbsp;<span class="count">(' . $this->pickup_count    . ')</span>';
		$dinein_count     = '&nbsp;<span class="count">(' . $this->dinein_count    . ')</span>';
		$out_for_deliver_count = '&nbsp;<span class="count">(' . $this->out_for_deliver_count . ')</span>';
		$trash_count = '&nbsp;<span class="count">(' . $this->trash_count . ')</span>';
		$views = array(
			'all'        => sprintf( '<a href="%s"%s>%s</a>', remove_query_arg( array( 'status', 'paged','service-type' ) ), $current === 'all' || $current == '' ? ' class="current"' : '', __( 'All','restropress' ) . $total_count ),
			'pending'    => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'pending', 'paged' => FALSE ) ), $current === 'pending' ? ' class="current"' : '', __( 'Pending','restropress' ) . $pending_count ),
			'paid'       => sprintf('<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'publish', 'paged' => FALSE ) ), $current === 'paid' ? ' class="current"' : '', __( 'Paid','restropress' ) . $paid_count ),
			'processing' => sprintf('<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'processing', 'paged' => FALSE ) ), $current === 'processing' ? ' class="current"' : '', __( 'Processing','restropress' ) . $out_for_deliver_count ),
			'Trash'		 =>  sprintf('<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'trash', 'paged' => FALSE ) ), $current === 'trash' ? ' class="current"' : '', __( 'Trash','restropress' ) . $trash_count),
		
		);
		$dinein_views = array();
		if( is_plugin_active( 'restropress-dinein/restropress-dinein.php' ) ) {
  				$dinein_views = array(
  					'dinein' => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'service-type' => 'dinein', 'paged' => FALSE ) ), $get_services === 'dinein' ? ' class="current"' : '', __( 'Dinein','restropress' ) . $dinein_count ),
  				);
  		}
  		
		if( $service_options === 'delivery_and_pickup' ) {
			
		$args =  array(
					'delivery' => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'service-type' => 'delivery', 'paged' => FALSE ) ), $get_services === 'delivery' ? ' class="current"' : '', __( 'Delivery','restropress' ) . $delivery_count ),
					'pickup'   => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'service-type' => 'pickup', 'paged' => FALSE ) ), $get_services === 'pickup' ? ' class="current"' : '', __( 'Pickup','restropress' ) . $pickup_count ),
			);
		}
  		elseif ( $service_options === 'delivery' ) {
  		$args =  array(
  					'delivery' => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'service-type' => 'delivery', 'paged' => FALSE ) ), $get_services === 'delivery' ? ' class="current"' : '', __( 'Delivery','restropress' ) . $delivery_count ),
  			);
  		}
  		elseif ( $service_options === 'pickup' ) {
  		$args =  array(
  					'pickup' => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'service-type' => 'pickup', 'paged' => FALSE ) ), $get_services === 'pickup' ? ' class="current"' : '', __( 'Pickup','restropress' ) . $pickup_count ),
  			);
  		}
  		
  		return apply_filters( 'rpress_payments_table_views', array_merge( $views,$args,$dinein_views ) );
  	}
  
  	
	/**
	 * Retrieve the table columns
	 *
	 * @since  1.0.0
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		$columns = array(
		'cb' 			=> '<input type="checkbox" />', //Render a checkbox instead of text
		'ID' 			=> esc_html__( 'Order', 'restropress' ),
  		'date' 			=> esc_html__( 'Order Date', 'restropress' ),
  		'service_date' 	=> esc_html__( 'Service Date', 'restropress' ),
  		'status' 		=> esc_html__( 'Payment Status', 'restropress' ),
  		'order_status' 	=> esc_html__( 'Order Status', 'restropress' ),
  		'payment_type' 	=> esc_html__( 'Payment type', 'restropress' ),
  		'amount' 		=> esc_html__( 'Amount', 'restropress' ),
		);
		return apply_filters( 'rpress_payments_table_columns', $columns );
	}
	/**
	 * Retrieve the table's sortable columns
	 *
	 * @since  1.0.0
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns() {
		$columns = array(
			'ID'     => array( 'ID', true ),
			'amount' => array( 'amount', false ),
			'date'   => array( 'date', false ),
		);
		return apply_filters( 'rpress_payments_table_sortable_columns', $columns );
	}
	/**
	 * Gets the name of the primary column.
	 *
	 * @since  1.0.0
	 * @access protected
	 *
	 * @return string Name of the primary column.
	 */
	protected function get_primary_column_name() {
		return 'ID';
	}
	/**
	 * This function renders most of the columns in the list table.
	 *
	 * @since  1.0.0
	 *
	 * @param array $payment Contains all the data of the payment
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_default( $payment, $column_name ) {
		switch ( $column_name ) {
			case 'amount' :
				$amount  = $payment->total;
        		$amount  = ! empty( $amount ) ? $amount : 0;
        		$value   = rpress_currency_filter( rpress_format_amount( $amount ), rpress_get_payment_currency_code( $payment->ID ) );
			break;
			case 'date' :
				$date    = strtotime( $payment->date );
        		$value   = date_i18n( get_option( 'date_format' ), $date ). ', '. date_i18n( 'H:ia', $date );
			break;
			case 'service_date' :
				$service_date = get_post_meta( $payment->ID, '_rpress_delivery_date', true );
				$service_date = rpress_local_date( $service_date );
				$service_time = get_post_meta( $payment->ID, '_rpress_delivery_time', true );
                $service_time_str = $service_time;
                // Check if the string contains 'ASAP'
if (strpos($service_time_str, 'ASAP') !== false) {
    // Remove 'ASAP' from the string
    $service_time_str = str_replace('ASAP', '', $service_time_str);
    // Trim any leading or trailing whitespace
    $service_time_str=esc_html__( 'ASAP' , 'restropress' ) .$service_time_str;
}
    			$value   = !empty( $service_time_str ) ? $service_date . ', ' . $service_time_str : $service_date;
		
                break;
			case 'status' :
			  	$status = rpress_get_payment_status_label( $payment->post_status );
			    $statuses = rpress_get_payment_statuses();
			    $status_label = '<mark class="payment-status status-' . $payment->post_status . '" >';
			    $status_label .= '<span> ' . $status . '</span>';
			    $status_label .= '</mark>';
			    $value = $status_label;
			    if( $payment->post_status =='trash'){
			    $status = rpress_get_payment_status_label( $payment->post_status );
			    $statuses = rpress_get_payment_statuses();
			    $status_label = '<mark class="payment-status status-' . 'Trash'. '" >';
			    $status_label .= '<span style ="color: red;"> ' . 'Trash' . '</span>';
			    $status_label .= '</mark>';
			    $value = $status_label;
			    }
			break;
			case 'order_status' :
	      		$order_statuses = rpress_get_order_statuses();
	      		$current_order_status = rpress_get_order_status( $payment->ID );
		    	$options = '<select data-payment-id="'.$payment->ID.'" data-current-status="'.$current_order_status.'" name="rp_order_status" class="rp_order_status rp_current_status_'.$current_order_status.'">';
		    foreach( $order_statuses as $status_id => $status_label ) {
		    	$options .= '<option  value="' . $status_id  . '" ' . rp_selected( $current_order_status, $status_id, false ) . '>' . $status_label . '</option>';
		    }
		    	$options .= '</select>';
		    	$options .= '<span class="order-status-loading"></span>';
		    	$value = $options;
		    		
		    	if( $payment->post_status =='trash'){
		    	$order_statuses = rpress_get_order_statuses();
	      		$current_order_status = rpress_get_order_status( $payment->ID );
		    	$options = '<select style = "pointer-events: none; cursor: default;" data-payment-id="'.$payment->ID.'" data-current-status="'.$current_order_status.'" name="rp_order_status" class="rp_order_status rp_current_status_'.$current_order_status.'">';
		    foreach( $order_statuses as $status_id => $status_label ) {
		    	$options .= '<option  value="' . $status_id  . '" ' . rp_selected( $current_order_status, $status_id, false ) . '>' . $status_label . '</option>';
		    }
		    	$options .= '</select>';
		    	$options .= '<span class="order-status-loading"></span>';
		    	$value = $options; 
      		}
		    break;
		    case 'payment_type' :
		    	$gateway    = $payment->gateway;
		    	$value 		= rpress_get_gateway_admin_label( $gateway );
		    break;
			default:
				$value = isset( $payment->$column_name ) ? $payment->$column_name : '';
			break;
		}
		return apply_filters( 'rpress_payments_table_column', $value, $payment->ID, $column_name );
	}
	/**
	 * Render the Email Column
	 *
	 * @since  1.0.0
	 * @param array $payment Contains all the data of the payment
	 * @return string Data shown in the Email column
	 */
	public function column_email( $payment ) {
		$row_actions = array();
		$email = rpress_get_payment_user_email( $payment->ID );
		// Add search term string back to base URL
		$search_terms = ( isset( $_GET['s'] ) ? trim( sanitize_text_field( $_GET['s'] ) ): '' );
		if ( ! empty( $search_terms ) ) {
			$this->base_url = add_query_arg( 's', $search_terms, $this->base_url );
		}
		if ( rpress_is_payment_complete( $payment->ID ) && ! empty( $email ) ) {
			$row_actions['email_links'] = '<a href="' . add_query_arg( array( 'rpress-action' => 'email_links', 'purchase_id' => $payment->ID ), $this->base_url ) . '">' . __( 'Resend Purchase Receipt', 'restropress' ) . '</a>';
		}
		$row_actions['delete'] = '<a href="' . wp_nonce_url( add_query_arg( array( 'rpress-action' => 'delete_payment', 'purchase_id' => $payment->ID ), $this->base_url ), 'rpress_payment_nonce' ) . '">' . __( 'Delete', 'restropress' ) . '</a>';
		$row_actions = apply_filters( 'rpress_payment_row_actions', $row_actions, $payment );
		if ( empty( $email ) ) {
			$email = esc_html__( '( unknown )', 'restropress' );
		}
		$value = $email . $this->row_actions( $row_actions );
		return apply_filters( 'rpress_payments_table_column', $value, $payment->ID, 'email' );
	}
	/**
	 * Render the checkbox column
	 *
	 * @since  1.0.0
	 * @param array $payment Contains all the data for the checkbox column
	 * @return string Displays a checkbox
	 */
	public function column_cb( $payment ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'payment',
			$payment->ID
		);
	}
	/**
	 * Render the ID column
	 *
	 * @since  1.0.0
	 * @param array $payment Contains all the data for the checkbox column
	 * @return string Displays a checkbox
	 */
	public function column_ID( $payment ) {
		$customer_id = rpress_get_payment_customer_id( $payment->ID );
		$cust_name = '';
		$row_actions = array();
		if ( rpress_is_order_trashable( $payment->ID ) ) {
		$trash_url = wp_nonce_url( add_query_arg( array(
				'rpress-action'  => 'trash_order',
				'purchase_id' => absint( $payment->ID ),
			), $this->base_url ), 'rpress_payment_nonce' );
			$row_actions['trash'] = '<a href="' . esc_url( $trash_url ) . '">' . esc_html__( 'Trash', 'restropress' ) . '</a>';
		}elseif ( rpress_is_order_restorable( $payment->ID ) ) {
			$restore_url = wp_nonce_url( add_query_arg( array(
				'rpress-action'  => 'restore_order',
				'purchase_id' => absint( $payment->ID ),
			), $this->base_url ), 'rpress_payment_nonce' );
			$row_actions['restore'] = '<a href="' . esc_url( $restore_url ) . '">' . esc_html__( 'Restore', 'restropress' ) . '</a>';
			$delete_url = wp_nonce_url( add_query_arg( array(
				'rpress-action'  => 'delete_order',
				'purchase_id' => absint( $payment->ID ),
			), $this->base_url ), 'rpress_payment_nonce' );
			$row_actions['delete'] = '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete Permanently', 'restropress' ) . '</a>';
			unset( $row_actions['view'] );
		}
		if ( has_filter( 'rpress_payment_row_actions' ) ) {
			$payment = rpress_get_payment( $payment->ID );
			$row_actions = apply_filters_deprecated( 'rpress_payment_row_actions', array( $row_actions, $payment ), '3.0', 'rpress_order_row_actions' );
		}
		$row_actions = apply_filters( 'rpress_order_row_actions', $row_actions, $payment );
		$actions = $this->row_actions( $row_actions );
	    if ( ! empty( $customer_id ) ) {
	      $customer  = new RPRESS_Customer( $customer_id );
	      $cust_name = $customer->name;
	    }
		$payment_meta = $payment->get_meta();
	    $customer_name = ( ! empty( $payment_meta['user_info'] ) && is_array( $payment_meta['user_info'] ) ) ? $payment_meta['user_info']['first_name'] . ' ' . $payment_meta['user_info']['last_name'] : $cust_name;
	    $prefix  = rpress_get_option( 'sequential_prefix' );
		$postfix = rpress_get_option( 'sequential_postfix' );
	    $service_type = rpress_get_service_type( $payment->ID );
	    $rpress_number = get_post_meta( $payment->ID, '_rpress_payment_number' , true );
	    if ( rpress_get_option( 'enable_sequential' ) ) {
	    	$alter_id = ( $rpress_number ) ? $rpress_number :  $payment->ID ;
	    	$order_preview = '<a href="#" class="order-preview" data-order-id="' . absint( $payment->ID ) . '" title="' . esc_attr( __( 'Preview', 'restropress' ) ) . '"><span>' . esc_html( __( 'Preview', 'restropress' ) ) . '</span></a>
	      <a class="" href="' . add_query_arg( 'id', $payment->ID, admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details' ) ) . '">#' . $alter_id . ' ' . $customer_name . '</a><span class="rp-service-type badge-' . $service_type . ' ">' . rpress_service_label( $service_type ) . '</span>';
	    	return $order_preview . $actions ;
	    } else {
		    $order_preview = '<a href="#" class="order-preview" data-order-id="' . absint( $payment->ID ) . '" title="' . esc_attr( __( 'Preview', 'restropress' ) ) . '"><span>' . esc_html( __( 'Preview', 'restropress' ) ) . '</span></a>
		    <a class="" href="' . add_query_arg( 'id', $payment->ID, admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details' ) ) . '">#' . $prefix . $payment->ID . $postfix .' ' . $customer_name . '</a><span class="rp-service-type badge-' . $service_type . ' ">' . rpress_service_label( $service_type ) . '</span>';
		
		}
		    return $order_preview . $actions ;
	}
	/**
	 * Render the Customer Column
	 *
	 * @since 2.4
	 * @param array $payment Contains all the data of the payment
	 * @return string Data shown in the User column
	 */
	public function column_customer( $payment ) {
		$customer_id = rpress_get_payment_customer_id( $payment->ID );
		if( ! empty( $customer_id ) ) {
			$customer   = new RPRESS_Customer( $customer_id );
			$value 		= '<a href="' . esc_url( admin_url( "admin.php?page=rpress-customers&view=overview&id=$customer_id" ) ) . '">' . $customer->name . '</a>';
		} else {
			$email = rpress_get_payment_user_email( $payment->ID );
			$value = '<a href="' . esc_url( admin_url( "admin.php?page=rpress-payment-history&s=$email" ) ) . '">' . esc_html__( '(customer missing)', 'restropress' ) . '</a>';
		}
		return apply_filters( 'rpress_payments_table_column', $value, $payment->ID, 'user' );
	}
	/**
	 * Retrieve the bulk actions
	 *
	 * @since  1.0.0
	 * @return array $actions Array of the bulk actions
	 */
	public function get_bulk_actions() {
		$actions = array(
			'delete'                 		 => esc_html__( 'Delete',				'restropress' ),
			'set-payment-status-pending'     => esc_html__( 'Set Payment To Pending',		'restropress' ),
			'set-payment-status-processing'  => esc_html__( 'Set Payment To Processing',	'restropress' ),
			'set-payment-status-refunded'    => esc_html__( 'Set Payment To Refunded',		'restropress' ),
			'set-payment-status-paid'     	 => esc_html__( 'Set Payment To Paid',        'restropress' ),
			'set-payment-status-failed'      => esc_html__( 'Set Payment To Failed',		'restropress' ),
		);
		$order_statuses = rpress_get_order_statuses();
		$order_actions = array();
		if ( ! empty( $order_statuses ) ) {
			foreach( $order_statuses as $status => $name ) {
				$order_actions[ 'set-order-status-' . $status  ] = sprintf( esc_html__( 'Set Order To %s', 'restropress' ), $name );
			}
		}
		$order_actions['resend-receipt'] = esc_html__( 'Resend Email Receipts','restropress' );
		if ( 'trash' === $this->get_status() ) {
			$actions = array(
				'restore' => esc_html__( 'Restore', 'restropress' ),
			);
		} else {
			$actions['trash'] = esc_html__( 'Move to Trash', 'restropress' );
		}
		
		$actions = array_merge( $actions, $order_actions );
		return apply_filters( 'rpress_payments_table_bulk_actions', $actions );
	}
	/**
	 * Process the bulk actions
	 *
	 * @since  3.1
	 * @return void
	 */
	public function process_bulk_action() {
		_doing_it_wrong( __FUNCTION__, 'Orders list table bulk actions are now handled by rpress_orders_list_table_process_bulk_actions(). Please do not call this method directly.', 'RestroPress 3.0' );
	}
	/**
	 * Retrieve the payment counts
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function get_payment_counts() {
		global $wp_query;
		$args = array();
		if ( isset( $_GET['user'] ) ) {
			$args['user'] = urldecode( sanitize_text_field( $_GET['user'] ) );
		} elseif ( isset( $_GET['customer'] ) ) {
			$args['customer'] = absint( $_GET['customer'] );
		} elseif ( isset( $_GET['s'] ) ) {
			$is_user  = strpos( sanitize_text_field( $_GET['s'] ), strtolower( 'user:' ) ) !== false;
			if ( $is_user ) {
				$args['user'] = absint( trim( str_replace( 'user:', '', strtolower( sanitize_text_field( $_GET['s'] ) ) ) ) );
				unset( $args['s'] );
			} else {
				$args['s'] = sanitize_text_field( $_GET['s'] );
			}
		}
		if ( ! empty( $_GET['start-date'] ) ) {
			$args['start-date'] = urldecode( sanitize_text_field( $_GET['start-date'] ) );
		}
		if ( ! empty( $_GET['end-date'] ) ) {
			$args['end-date'] = urldecode( sanitize_text_field( $_GET['end-date'] ) );
		}
		if ( ! empty( $_GET['gateway'] ) && $_GET['gateway'] !== 'all' ) {
			$args['gateway'] = sanitize_text_field( $_GET['gateway'] );
		}
		if ( ! empty( $_GET['service-date'] ) ) {
			$args['service-date'] = urldecode( sanitize_text_field( $_GET['service-date'] ) );
		}
		if ( ! empty( $_GET['service-type'] ) ) {
			$args['service-type'] = urldecode( sanitize_text_field( $_GET['service-type'] ) );
		}
		$payment_count          		= rpress_count_payments( $args );
		$this->completed_count   		=  isset( $payment_count->completed ) ? $payment_count->completed : 0;
		$this->pending_count    		=  isset( $payment_count->pending ) ? $payment_count->pending : 0 ;
		$this->paid_count 				=  isset( $payment_count->publish ) ? $payment_count->publish : 0 ;
		$this->trash_count  			= $this->count_trash_order();
		$this->delivery_count 			=  isset( $this->count_service_type()['delivery'] ) ? $this->count_service_type()['delivery'] : 0;
		$this->pickup_count 			=  isset( $this->count_service_type()['pickup'] ) ? $this->count_service_type()['pickup'] : 0;
		$this->dinein_count 			=  isset( $this->count_service_type()['dinein'] ) ? $this->count_service_type()['dinein'] : 0;
		$this->out_for_deliver_count   	=  isset( $payment_count->processing ) ? $payment_count->processing : 0 ;
		$this->total_count = intval( $this->completed_count ) + intval( $this->pending_count ) + intval( $this->paid_count ) + intval( $this->out_for_deliver_count );
	}
	public function count_service_type() {
		global $wpdb;
		$query = "SELECT g.meta_value,count( * ) AS num_posts FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta g ON (p.ID = g.post_id) WHERE p.post_type = 'rpress_payment' AND g.meta_key = '_rpress_delivery_type' GROUP BY g.meta_value";
		$cache_key = md5( $query );
		$count = wp_cache_get( $cache_key, 'counts' );
		if ( false !== $count ) {
			return $count;
		}
		$rows = $wpdb->get_results( $query, ARRAY_A );
		$return_array = array();
		foreach ( $rows as  $row ) {
			$return_array[$row['meta_value'] ] =  $row['num_posts'] ;
		}
		
		return $return_array ;
	}
	public function count_trash_order() {
		global $wpdb;
		$query = "SELECT count( * ) AS num_posts FROM $wpdb->posts WHERE post_type = 'rpress_payment' AND  post_status= 'trash'" ;
		
		$cache_key = md5( $query );
		$count = wp_cache_get( $cache_key, 'counts' );
		if ( false !== $count ) {
			return $count;
		}
		$rows = $wpdb->get_row( $query, ARRAY_A );
		
		return $rows['num_posts'];
	}
	/**
	 * Retrieve all the data for all the payments
	 *
	 * @since  1.0.0
	 * @return array $payment_data Array of all the data for the payments
	 */
	public function payments_data() {
		$per_page   	 = $this->per_page;
		$orderby    	 = isset( $_GET['orderby'] )      ? urldecode( sanitize_text_field( $_GET['orderby'] ) ) : 'ID';
		$order      	 = isset( $_GET['order'] )        ? sanitize_text_field( $_GET['order'] ) : 'DESC';
		$user       	 = isset( $_GET['user'] )         ? sanitize_text_field( $_GET['user'] ) : null;
		$customer   	 = isset( $_GET['customer'] )     ? sanitize_text_field( $_GET['customer'] ) : null;
		$status     	 = isset( $_GET['status'] )       ? sanitize_text_field( $_GET['status'] ) : rpress_get_payment_status_keys();
		$meta_key   	 = isset( $_GET['meta_key'] )     ? sanitize_text_field( $_GET['meta_key'] ) : null;
		$year       	 = isset( $_GET['year'] )         ? sanitize_text_field( $_GET['year'] ) : null;
		$month      	 = isset( $_GET['m'] )            ? sanitize_text_field( $_GET['m'] ) : null;
		$day        	 = isset( $_GET['day'] )          ? sanitize_text_field( $_GET['day'] ) : null;
		$search     	 = isset( $_GET['s'] )            ? sanitize_text_field( $_GET['s'] ) : null;
		$start_date 	 = isset( $_GET['start-date'] )   ? sanitize_text_field( $_GET['start-date'] ) : null;
		$end_date   	 = isset( $_GET['end-date'] )     ? sanitize_text_field( $_GET['end-date'] ) : $start_date;
		$gateway    	 = isset( $_GET['gateway'] )      ? sanitize_text_field( $_GET['gateway'] ) : null;
		$service_date 	 = isset( $_GET['service-date'] ) ? sanitize_text_field( $_GET['service-date'] ) : null;
		$service_type 	 = isset( $_GET['service-type'] ) ? sanitize_text_field( $_GET['service-type'] ) : null;
		$trash   		 = isset( $_GET['trash'] ) 		  ? sanitize_text_field( $_GET['trash'] ) : null;
		$selected_orders = isset( $_GET['order_status'] ) ? sanitize_text_field( $_GET['order_status'] ) : null;
		/**
		 * Introduced as part of #6063. Allow a gateway to specified based on the context.
		 *
		 * @since  1.0.0
		 *
		 * @param string $gateway
		 */
		$gateway = apply_filters( 'rpress_payments_table_search_gateway', $gateway );
		if( ! empty( $search ) ) {
			$status = 'any'; // Force all payment statuses when searching
		}
		if ( $gateway === 'all' ) {
			$gateway = null;
		}
		$args = array(
			'output'       => 'payments',
			'number'       => $per_page,
			'page'         => isset( $_GET['paged'] ) ? sanitize_text_field( $_GET['paged'] )  : null,
			'orderby'      => $orderby,
			'order'        => $order,
			'user'         => $user,
			'customer'     => $customer,
			'status'       => $status,
			'meta_key'     => $meta_key,
			'year'         => $year,
			'month'        => $month,
			'day'          => $day,
			's'            => $search,
			'start_date'   => $start_date,
			'end_date'     => $end_date,
			'gateway'      => $gateway,
			'service_date' => $service_date,
			'order_status' =>$selected_orders
			
		);
		if( is_string( $search ) && false !== strpos( $search, 'txn:' ) ) {
			$args['search_in_notes'] = true;
			$args['s'] = trim( str_replace( 'txn:', '', $args['s'] ) );
		}
		if( ! is_null( $service_type ) ) {
			$args['meta_query'] = [
			[
				'key' => '_rpress_delivery_type',
				'value' => $service_type,
				'compare' => '='
			]
		];
		}
		if( ! empty( $selected_orders ) && $selected_orders != 'all' ) {
			$args['meta_query'] = [
				[ 
			       'key' => '_order_status',
				'value' => $selected_orders,
				'compare' => '='   
			]
		];
		}
		$p_query  = new RPRESS_Payments_Query( $args );
		return $p_query->get_payments();
	}
	/**
	 * Setup the final data for the table
	 *
	 * @since  1.0.0
	 * @uses RPRESS_Payment_History_Table::get_columns()
	 * @uses RPRESS_Payment_History_Table::get_sortable_columns()
	 * @uses RPRESS_Payment_History_Table::payments_data()
	 * @uses WP_List_Table::get_pagenum()
	 * @uses WP_List_Table::set_pagination_args()
	 * @return void
	 */
	public function prepare_items() {
		wp_reset_vars( array( 'action', 'payment', 'orderby', 'order', 's' ) );
		$columns  = $this->get_columns();
		$hidden   = array(); // No hidden columns
		$sortable = $this->get_sortable_columns();
		$data     = $this->payments_data();
		$status   = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'any';
		$this->_column_headers = array( $columns, $hidden, $sortable );
		switch ( $status ) {
			case 'completed':
				$total_items = $this->completed_count;
				break;
			case 'pending':
				$total_items = $this->pending_count;
				break;
			case 'paid':
				$total_items = $this->paid_count;
			break;
			case 'any':
				$total_items = $this->total_count;
				break;
			default:
				// Retrieve the count of the non-default-RPRESS status
				$count       = wp_count_posts( 'rpress_payment' );
				$total_items = $count->{$status};
		}
		$this->items = $data;
		$this->set_pagination_args( array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			)
		);
	}
	/**
   	 * Get total items in the order
     *
   	 * @since 3.0
     */
	public function rpress_get_order_total_items( $payment ) {
    	$cart_items = $payment->cart_details;
    	$quantity = 0;
    	$quantity_data = array();
    	if ( is_array( $cart_items ) ) {
      		foreach( $cart_items as $cart_item ) {
        		array_push( $quantity_data, $cart_item['quantity'] );
      		}
    	}
    	$quantity = array_sum( $quantity_data );
    	return $quantity;
  	}
  	public static function get_service_type_count( $service_type = '' ) {
	    global $wpdb;
	    $query_args = array(
	      'post_type'       => 'rpress_payment',
	      'posts_per_page'  => -1,
	      'meta_query'  => array(
	        array(
	          'key' => '_rpress_delivery_type',
	          'value' => array( $service_type ),
	        ),
	      ),
	    );
	    $get_total = new WP_Query( $query_args );
	    $totalpost = !empty( $get_total->found_posts ) ? $get_total->found_posts : 0;
	    return $totalpost;
	}
  	/**
     * Get order details by payment id to send to the ajax endpoint for previews.
     *
     * @param  RP_Payment $order Order object.
     * @return array
     */
  	public static function order_preview_get_order_details( $payment ) {
	    if ( ! $payment ) {
	      	return array();
	    }
	    $payment_via = $customer_name = $customer_email = $phone = $flat = $landmark = $customer_location = $order_fooditem = '';
	    $gateway  = $payment->gateway;
	    if ( $gateway ) {
	      	$payment_via = rpress_get_gateway_admin_label( $gateway );
	    }
	    if ( ! empty( $payment->customer_id ) ) {
	    	$customer    			= new RPRESS_Customer( $payment->customer_id );
	    	$payment_meta 			= $payment->get_meta();
	    	$customer_name 			= is_array( $payment_meta['user_info'] ) ? $payment_meta['user_info']['first_name'] . ' ' . $payment_meta['user_info']['last_name'] : $customer->name;
	    	$customer_email 		= is_array( $payment_meta['user_info'] ) ? $payment_meta['user_info']['email'] : $customer->email;
	    	$delivery_address_meta  = get_post_meta( $payment->ID, '_rpress_delivery_address', true );
		    $phone  				= ! empty( $payment_meta['phone'] ) ? $payment_meta['phone'] : (!empty( $delivery_address_meta['phone'] ) ? $delivery_address_meta['phone'] :  '');
		    $flat   				= ! empty( $delivery_address_meta['flat'] ) ? $delivery_address_meta['flat'] : '';
		    $city 					= ! empty( $delivery_address_meta['city'] ) ? $delivery_address_meta['city'] : '';
		    $postcode 				= ! empty( $delivery_address_meta['postcode'] ) ? $delivery_address_meta['postcode'] : '';
		    $customer_address 		= ! empty( $delivery_address_meta['address'] ) ? $delivery_address_meta['address'] : '';
    		$customer_details = array(
				'phone'      => $phone,
				'flat'       => $flat,
				'postcode'   => $postcode,
				'city'       => $city,
				'address'    => $customer_address
    		);
	    }
	    $user_info      	= $payment->user_info;
	    $billing_address 	= isset( $user_info['address'] ) ? $user_info['address'] : '';
	    $service_type 		= rpress_get_service_type( $payment->ID );
  		$service_date 		= $payment->get_meta( '_rpress_delivery_date' );
  		$service_date 		= !empty( $service_date ) ? rpress_local_date( $service_date ) : '';
  		$service_time 		= $payment->get_meta( '_rpress_delivery_time' );
	    return apply_filters(
	      	'rpress_admin_order_preview_get_order_details',
	      	array(
		        'id'                        => $payment->ID,
		        'service_type'              => rpress_service_label($service_type),
		        'service_type_slug'         => $service_type,
		        'service_date'              => $service_date,
		        'service_time'              => $service_time,
		        'status'                    => rpress_get_order_status( $payment->ID ),
		        'payment_via'               => $payment_via,
		        'customer_name'             => $customer_name,
		        'customer_email'            => $customer_email,
		        'customer_details'          => $customer_details,
		        'customer_billing_details'  => $user_info,
		        'item_html'                 => self::get_ordered_items( $payment ),
		        'actions_html'              => self::get_order_preview_actions_html( $payment ),
		        'formatted_billing_address' => $billing_address,
	      	), $payment
	    );
  	}
  	/**
     * Get all the item details from the payment object
     *
     * @param  RP_Payment $payment Payment Object.
     * @return html
     */
  	public static  function get_ordered_items( $payment ) {
    	$order_items = $payment->cart_details;
    	if ( is_array( $order_items ) &&  !empty( $order_items )  ) {
      		ob_start(); ?>
      	<div class="rp-order-preview-table-wrapper">
        	<table cellspacing="0" class="rp-order-preview-table">
          		<thead>
            		<tr>
              			<th class="rp-order-preview-table__column--product">
                			<?php esc_html_e( 'FoodItem(s)', 'restropress' ); ?>
              			</th>
              		<th class="rp-order-preview-table__column--price-quantity">
                		<?php esc_html_e( 'Price & Quantity', 'restropress' ); ?>
              		</th>
              		<?php if ( rpress_use_taxes() ) : ?>
                	<th class="rp-order-preview-table__column--tax">
                  		<?php esc_html_e( 'Tax', 'restropress' ); ?>
                	</th>
              		<?php endif; ?>
              		<th class="rp-order-preview-table__column--price">
                		<?php esc_html_e( 'Total', 'restropress' ); ?>
              		</th>
            	</tr>
          	</thead>
          	<tbody>
            	<?php foreach( $order_items as $fooditems ) :
            		$special_instruction = isset( $fooditems['instruction'] ) ? $fooditems['instruction'] : '';
            		if ( isset( $fooditems['name'] ) ) :
            			$item_tax   = isset( $fooditems['tax'] ) ? $fooditems['tax'] : 0;
            			$price      = isset( $fooditems['price'] ) ? $fooditems['price'] : false; ?>
            		<tr class="rp-order-preview-table">
						<td class="rp-order-preview-table__column--product">
							<?php echo rpress_get_cart_item_name( $fooditems ); ?>
              			</td>
	              		<td class="rp-order-preview-table__column--quantity">
	                		<?php echo rpress_currency_filter( rpress_format_amount( $fooditems['item_price'] ) ) . ' X ' . $fooditems['quantity']; ?>
						</td>
	              		<?php if ( rpress_use_taxes() ) : ?>
	                	<td class="rp-order-preview-table__column--tax">
	                  		<?php echo rpress_currency_filter( rpress_format_amount( $item_tax ) ); ?>
	                	</td>
	              		<?php endif; ?>
	              		<td class="rp-order-preview-table__column--price">
	                		<?php echo rpress_currency_filter( rpress_format_amount( $price ) ); ?>
	              		</td>
            		</tr>
            		<?php if ( !empty( $special_instruction ) ) : ?>
              		<tr class="rp-order-preview-table special-instruction">
                		<td colspan="3">
                  			<?php printf( esc_html__( 'Special Instruction : %s', 'rp_quick_view'), $special_instruction ); ?>
                		</td>
              		</tr>
            		<?php endif; ?>
            		<?php
            		if ( is_array( $fooditems['item_number']['options'] ) ) :
            			foreach( $fooditems['item_number']['options'] as $addon_items ) :
            				if( is_array( $addon_items ) ) :
			                    $addon_name = $addon_items['addon_item_name'];
			                    $addon_price = $addon_items['price'];
			                    $addon_quantity = $addon_items['quantity'];
                  	?>
                    <tr>
                      	<td>
                        	<?php echo esc_html( $addon_name ); ?>
                      	</td>
                      	<td>
                        	<?php echo rpress_currency_filter( rpress_format_amount( $addon_price ), rpress_get_payment_currency_code( $payment->ID ) ) . ' X ' . $addon_quantity; ?>
                      	</td>
                      	<?php if ( rpress_use_taxes() ) : ?>
                        <td>
                         	<?php echo rpress_currency_filter( rpress_format_amount( '0' ) ); ?>
                        </td>
                      	<?php endif; ?>
                      	<td>
                        	<?php echo rpress_currency_filter( rpress_format_amount( $addon_price ) ); ?>
                      	</td>
                    </tr>
                    		<?php endif;
                		endforeach;
              		endif;
              	endif;
            endforeach; ?>
	          		</tbody>
	        	</table>
	      	</div>
	      	<?php
	      	$output = ob_get_contents();
	      	ob_clean();
    	}
    	return $output;
  	}
	/**
     * Get actions to display in the preview as HTML.
     *
     * @param  RP_Payment Payment object.
     * @return string
     */
  	public static function get_order_preview_actions_html( $payment ) {
    	$actions        = array();
	    $status_actions = array();
	    $payment_status = rpress_get_order_status( $payment->ID );
	    if ( $payment_status == 'pending' ) {
	      $status_actions['processing'] = array(
	        'name'        => esc_html__( 'Processing', 'restropress' ),
	        'payment_id'  => $payment->ID,
	        'action'      => 'processing',
	        'url'         => wp_nonce_url( admin_url( 'admin-ajax.php?action=rpress_update_order_status&status=processing&current_status=' . $payment_status . '&redirect=1&payment_id=' . $payment->ID ), 'rpress-mark-order-status' ),
	      );
	    }
	    if ( ( $payment_status == 'processing' || $payment_status == 'pending' ) ) {
	      $status_actions['completed'] = array(
	        'name'        => esc_html__( 'Completed', 'restropress' ),
	        'payment_id'  => $payment->ID,
	        'action'      => 'completed',
	        'url'         => wp_nonce_url( admin_url( 'admin-ajax.php?action=rpress_update_order_status&status=completed&current_status=' . $payment_status. '&redirect=1&payment_id=' . $payment->ID ), 'rpress-mark-order-status' ),
	      );
	    }
	    if ( $status_actions ) {
	      $actions['status'] = array(
	        'group'   => esc_html__( 'Change order status: ', 'restropress' ),
	        'actions' => $status_actions,
	      );
	    }
    	return rp_render_action_buttons( apply_filters( 'restropress_admin_order_preview_actions', $actions, $payment ) );
  	}
  	/**
     * Template for order preview.
     *
     * @since 3.0
     */
  	public function order_preview_template() { ?>
  		<script type="text/template" id="tmpl-rp-modal-view-order">
      	<div class="rp-backbone-modal rp-order-preview">
        <div class="rp-backbone-modal-content">
          <section class="rp-backbone-modal-main" role="main">
            <header class="rp-backbone-modal-header">
              <mark class="order-status status-{{ data.status }}"><span>{{ data.status }}</span></mark>
              <?php /* translators: %s: order ID */ ?>
              <h1><?php echo esc_html( sprintf( __( 'Order #%s', 'restropress' ), '{{ data.id }}' ) ); ?></h1>
              <# if ( data.service_type_slug !== '' ) { #>
                <mark class="service-type badge-{{ data.service_type_slug }}"><span>{{ data.service_type }}</span></mark>
              <# } #>
              <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                <span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'restropress' ); ?></span>
              </button>
            </header>
            <?php esc_html_e( get_post_status( '{{data.id}}' ) ); ?>
            <article>
              <?php do_action( 'rpress_admin_order_preview_start' ); ?>
              <div class="rp-order-preview-wrapper">
                <div class="rp-order-preview">
                  <# if ( data.customer_details.address ) { #>
                    <div class="rp-order-preview-address">
                      <h2><?php esc_html_e( sprintf( __( '%s address', 'restropress' ), '{{ data.service_type }}' ) ); ?></h2>
                        {{ data.customer_details.address }}<br />
                        {{ data.customer_details.flat }}<br />
                        {{ data.customer_details.city }} {{ data.customer_details.postcode }}
                    </div>
                  <# } #>
                  <div class="rp-order-preview-customer-details">
                    <h2><?php esc_html_e( 'Customer details', 'restropress' ); ?></h2>
                    <# if ( data.customer_name ) { #>
                      <strong><?php esc_html_e( 'Customer name', 'restropress' ); ?></strong>
                    : <span>{{ data.customer_name }}</span>
                      <br/>
                    <# } #>
                    <# if ( data.customer_email ) { #>
                      <strong><?php esc_html_e( 'Email', 'restropress' ); ?></strong>
                      : <a href="mailto:{{ data.customer_email }}">{{ data.customer_email }}</a>
                      <br/>
                    <# } #>
                    <# if ( data.customer_details.phone ) { #>
                      <strong><?php esc_html_e( 'Phone', 'restropress' ); ?></strong>
                      : <a href="tel:{{{ data.customer_details.phone }}}">{{{ data.customer_details.phone }}}</a>
                      <br/>
                    <# } #>
                  </div>
                  <div class="rp-clear-fix"></div>
                  <div class="order-service-meta">
                    <# if ( data.payment_via ) { #>
                      <span>
                        <strong><?php esc_html_e( 'Payment via', 'restropress' ); ?></strong> :
                        {{{ data.payment_via }}}
                      </span>
                    <# } #>
                    <# if ( data.service_date ) { #>
                      <span>
                      <strong><?php esc_html_e( 'Service date', 'restropress' ); ?></strong> :
                      {{{ data.service_date }}}
                      </span>
                    <# } #>
                    <# if ( data.service_time ) { #>
                      <span>
                        <strong><?php esc_html_e( 'Service time', 'restropress' ); ?></strong> :
                      {{{ data.service_time }}}
                    <# } #>
                    </span>
                  </div>
                </div>
                <?php do_action( 'rpress_admin_order_preview_before_fooditems' ); ?>
                <br/>
                <# if ( data.item_html ) { #>
                  <div class="fooditems">
                    {{{ data.item_html }}}
                  </div>
                <# } #>
              </div>
              <?php do_action( 'rpress_admin_order_preview_end' ); ?>
            </article>
          </section>
        </div>
      </div>
      <div class="rp-backbone-modal-backdrop modal-close"></div>
    	</script>
  	<?php }
}