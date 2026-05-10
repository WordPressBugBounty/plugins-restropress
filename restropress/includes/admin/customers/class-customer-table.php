<?php
/**
 * Customer Reports Table Class
 *
 * @package     RPRESS
 * @subpackage  Admin/Reports
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
/**
 * RPRESS_Customer_Reports_Table Class
 *
 * Renders the Customer Reports table
 *
 * @since 1.0.0
 */
class RPRESS_Customer_Reports_Table extends WP_List_Table {
	/**
	 * Number of items per page
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public $per_page = 30;
	/**
	 * Number of customers found
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public $count = 0;
	/**
	 * Total customers
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public $total = 0;
	/**
	 * The arguments for the data set
	 *
	 * @var array
	 * @since  1.0.0
	 */
	public $args = array();
	/**
	 * Current customer status view.
	 *
	 * @var string
	 * @since 3.2.8.6.3
	 */
	public $status = 'active';
	/**
	 * Get things started
	 *
	 * @since 1.0.0
	 * @see WP_List_Table::__construct()
	 */
	public function __construct() {
		global $status, $page;
		// Set parent defaults
		parent::__construct( array(
			'singular' => esc_html__( 'Customer', 'restropress' ),
			'plural'   => esc_html__( 'Customers', 'restropress' ),
			'ajax'     => false,
		) );
	}
	/**
	 * Show the search field
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Label for the search box
	 * @param string $input_id ID of the search box
	 *
	 * @return void
	 */
	public function search_box( $text, $input_id ) {
		$input_id = $input_id . '-search-input';
		if ( ! empty( $_REQUEST['orderby'] ) )
			echo '<input type="hidden" name="orderby" value="' . esc_attr( sanitize_text_field( $_REQUEST['orderby'] ) ) . '" />';
		if ( ! empty( $_REQUEST['order'] ) )
			echo '<input type="hidden" name="order" value="' . esc_attr( sanitize_text_field( $_REQUEST['order'] ) ) . '" />';
		if ( ! empty( $_REQUEST['status'] ) ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_text_field( $_REQUEST['status'] ) ) . '" />';
		}
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, 'button', false, false, array('ID' => 'search-submit') ); ?>
		</p>
		<?php
	}
	/**
	 * Gets the name of the primary column.
	 *
	 * @since 1.0.0
	 * @access protected
	 *
	 * @return string Name of the primary column.
	 */
	protected function get_primary_column_name() {
		return 'name';
	}
	/**
	 * This function renders most of the columns in the list table.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Contains all the data of the customers
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'num_purchases' :
				$value = '<a href="' .
					admin_url( 'admin.php?page=rpress-payment-history&user=' . urlencode( $item['email'] )
				) . '">' . esc_html( $item['num_purchases'] ) . '</a>';
				break;
			case 'amount_spent' :
				$value = rpress_currency_filter( rpress_format_amount( $item[ $column_name ] ) );
				break;
			case 'date_created' :
				$value = date_i18n( get_option( 'date_format' ), strtotime( $item['date_created'] ) );
				break;
			default:
				$value = isset( $item[ $column_name ] ) ? $item[ $column_name ] : null;
				break;
		}
		return apply_filters( 'rpress_customers_column_' . $column_name, $value, $item['id'] );
	}
	public function column_name( $item ) {
		$name        = '#' . $item['id'] . ' ';
		$name       .= ! empty( $item['name'] ) ? $item['name'] : '<em>' . esc_html__( 'Unnamed Customer','restropress' ) . '</em>';
		$view_url    = admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . $item['id'] );
		$actions     = array();

		if ( 'trashed' === $this->status ) {
			$restore_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'          => 'rpress-customers',
						'status'        => 'trash',
						'rpress_action' => 'restore-customer',
						'customer_id'   => $item['id'],
					),
					admin_url( 'admin.php' )
				),
				'restore-customer'
			);
			$delete_url  = wp_nonce_url(
				add_query_arg(
					array(
						'page'                           => 'rpress-customers',
						'status'                         => 'trash',
						'rpress_action'                  => 'delete-customer-permanently',
						'customer_id'                    => $item['id'],
						'rpress-customer-delete-confirm' => 1,
					),
					admin_url( 'admin.php' )
				),
				'delete-customer-permanently'
			);
			$delete_confirm = esc_js( __( 'Permanently delete this customer? This cannot be undone.', 'restropress' ) );
			$actions = array(
				'restore' => '<a href="' . esc_url( $restore_url ) . '">' . esc_html__( 'Restore', 'restropress' ) . '</a>',
				'delete'  => '<a href="' . esc_url( $delete_url ) . '" class="submitdelete" onclick="return confirm(\'' . $delete_confirm . '\');">' . esc_html__( 'Delete Permanently', 'restropress' ) . '</a>',
			);
		} else {
			$delete_url  = wp_nonce_url(
				add_query_arg(
					array(
						'page'                           => 'rpress-customers',
						'rpress_action'                  => 'delete-customer',
						'customer_id'                    => $item['id'],
						'rpress-customer-delete-confirm' => 1,
					),
					admin_url( 'admin.php' )
				),
				'delete-customer'
			);
			$delete_confirm = esc_js( __( 'Are you sure you want to move this customer to trash?', 'restropress' ) );
			$actions = array(
				'view'   => '<a href="' . $view_url . '">' . esc_html__( 'View', 'restropress' ) . '</a>',
				'delete' => '<a href="' . esc_url( $delete_url ) . '" class="submitdelete" onclick="return confirm(\'' . $delete_confirm . '\');">' . esc_html__( 'Delete', 'restropress' ) . '</a>',
			);
		}

		$customer = new RPRESS_Customer( $item['id'] );
		$pending  = rpress_user_pending_verification( $customer->user_id ) ? ' <em>' . esc_html__( '(Pending Verification)', 'restropress' ) . '</em>' : '';
		return '<a href="' . esc_url( $view_url ) . '">' . $name . '</a>' . $pending . $this->row_actions( $actions );
	}
	/**
	 * Retrieve the current status for the customer list.
	 *
	 * @since 3.2.8.6.3
	 * @return string
	 */
	public function get_status() {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		if ( 'trash' === $status ) {
			return 'trashed';
		}
		return 'active';
	}
	/**
	 * Get metadata query for the current customer status.
	 *
	 * @since 3.2.8.6.3
	 * @param string $status active|trashed.
	 * @return array
	 */
	public function get_status_meta_query( $status = 'active' ) {
		if ( 'trashed' === $status ) {
			return array(
				array(
					'key'     => '_rpress_customer_trashed',
					'value'   => '1',
					'compare' => '=',
				),
			);
		}
		return array(
			array(
				'key'     => '_rpress_customer_trashed',
				'compare' => 'NOT EXISTS',
			),
		);
	}
	/**
	 * Retrieve the table views.
	 *
	 * @since 3.2.8.6.3
	 * @return array
	 */
	public function get_views() {
		$base_url    = admin_url( 'admin.php?page=rpress-customers' );
		$current     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		$active_count = rpress_count_total_customers(
			array(
				'meta_query' => $this->get_status_meta_query( 'active' ),
			)
		);
		$trash_count = rpress_count_total_customers(
			array(
				'meta_query' => $this->get_status_meta_query( 'trashed' ),
			)
		);

		$views = array(
			'all'   => sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( remove_query_arg( 'status', $base_url ) ),
				'all' === $current ? ' class="current"' : '',
				sprintf(
					/* translators: %d: customer count */
					esc_html__( 'All (%d)', 'restropress' ),
					absint( $active_count )
				)
			),
			'trash' => sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( add_query_arg( 'status', 'trash', $base_url ) ),
				'trash' === $current ? ' class="current"' : '',
				sprintf(
					/* translators: %d: customer count */
					esc_html__( 'Trash (%d)', 'restropress' ),
					absint( $trash_count )
				)
			),
		);

		return $views;
	}
	/**
	 * Retrieve the table columns
	 *
	 * @since 1.0
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		$columns = array(
			'cb'            => '<input type="checkbox" />',
			'name'          => esc_html__( 'Name', 'restropress' ),
			'email'         => esc_html__( 'Primary Email', 'restropress' ),
			'num_purchases' => esc_html__( 'Numbers Of Order', 'restropress' ),
			'amount_spent'  => esc_html__( 'Total Order Price', 'restropress' ),
			'date_created'  => esc_html__( 'Date Created', 'restropress' ),
		);
		return apply_filters( 'rpress_report_customer_columns', $columns );
	}
	/**
	 * Render the checkbox column.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Contains all the data for the customer.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'customer',
			absint( $item['id'] )
		);
	}
	/**
	 * Retrieve the bulk actions.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_bulk_actions() {
		if ( 'trashed' === $this->status ) {
			return array(
				'restore'            => esc_html__( 'Restore', 'restropress' ),
				'delete_permanently' => esc_html__( 'Delete Permanently', 'restropress' ),
			);
		}

		return array(
			'delete' => esc_html__( 'Delete', 'restropress' ),
		);
	}
	/**
	 * Get the sortable columns
	 *
	 * @since  1.0.0
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns() {
		return array(
			'date_created'  => array( 'date_created', true ),
			'name'          => array( 'name', true ),
			'num_purchases' => array( 'purchase_count', false ),
			'amount_spent'  => array( 'purchase_value', false ),
		);
	}
	/**
	 * Outputs the reporting views
	 *
	 * @since 1.0
	 * @return void
	 */
	public function bulk_actions( $which = '' ) {
		parent::bulk_actions( $which );
	}
	/**
	 * Retrieve the current page number
	 *
	 * @since 1.0
	 * @return int Current page number
	 */
	public function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}
	/**
	 * Retrieves the search query string
	 *
	 * @since  1.0.0
	 * @return mixed string If search is present, false otherwise
	 */
	public function get_search() {
		return ! empty( $_GET['s'] ) ? urldecode( trim( sanitize_text_field( $_GET['s'] ) ) ) : false;
	}
	/**
	 * Build all the reports data
	 *
	 * @since 1.0
	  * @global object $wpdb Used to query the database using the WordPress
	 *   Database API
	 * @return array $reports_data All the data for customer reports
	 */
	public function reports_data() {
		global $wpdb;
		$data    = array();
		$paged   = $this->get_paged();
		$offset  = $this->per_page * ( $paged - 1 );
		$search  = $this->get_search();
		$status  = $this->get_status();
		$order   = isset( $_GET['order'] )   ? sanitize_text_field( $_GET['order'] )   : 'DESC';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'id';
		$args    = array(
			'number'  => $this->per_page,
			'offset'  => $offset,
			'order'   => $order,
			'orderby' => $orderby,
			'meta_query' => $this->get_status_meta_query( $status ),
		);
		if( is_email( $search ) ) {
			$args['email'] = $search;
		} elseif( is_numeric( $search ) ) {
			$args['id']    = $search;
		} elseif( strpos( $search, 'user:' ) !== false ) {
			$args['user_id'] = trim( str_replace( 'user:', '', $search ) );
		} else {
			$args['name']  = $search;
		}
		$this->args = $args;
		$this->status = $status;
		$customers  = RPRESS()->customers->get_customers( $args );
		if ( $customers ) {
			foreach ( $customers as $customer ) {
				$user_id = ! empty( $customer->user_id ) ? intval( $customer->user_id ) : 0;
				$data[] = array(
					'id'            => $customer->id,
					'user_id'       => $user_id,
					'name'          => $customer->name,
					'email'         => $customer->email,
					'num_purchases' => $customer->purchase_count,
					'amount_spent'  => $customer->purchase_value,
					'date_created'  => $customer->date_created,
				);
			}
		}
		return $data;
	}
	/**
	 * Setup the final data for the table
	 *
	 * @since 1.0
	 * @uses RPRESS_Customer_Reports_Table::get_columns()
	 * @uses WP_List_Table::get_sortable_columns()
	 * @uses RPRESS_Customer_Reports_Table::get_pagenum()
	 * @uses RPRESS_Customer_Reports_Table::get_total_customers()
	 * @return void
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array(); // No hidden columns
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items = $this->reports_data();
		$count_args = $this->args;
		unset( $count_args['number'], $count_args['offset'] );
		$this->total = rpress_count_total_customers( $count_args );
		// Add condition to be sure we don't divide by zero.
		// If $this->per_page is 0, then set total pages to 1.
		$total_pages = $this->per_page ? ceil( (int) $this->total / (int) $this->per_page ) : 1;
		$this->set_pagination_args( array(
			'total_items' => $this->total,
			'per_page'    => $this->per_page,
			'total_pages' => $total_pages,
		) );
	}
}
