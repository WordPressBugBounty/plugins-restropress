<?php
/**
 * Admin Reports Page
 *
 * @package     RPRESS
 * @subpackage  Admin/Reports
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

require_once dirname( __FILE__ ) . '/class-rpress-reports-intelligence.php';
require_once dirname( __FILE__ ) . '/report-helpers.php';

$rpress_report_tab_files = apply_filters(
	'rpress_reports_tab_files',
	array(
		'overview'          => dirname( __FILE__ ) . '/tabs/overview.php',
		'sales'             => dirname( __FILE__ ) . '/tabs/sales.php',
		'orders-risk'       => dirname( __FILE__ ) . '/tabs/orders-risk.php',
		'menu'              => dirname( __FILE__ ) . '/tabs/menu.php',
		'customers'         => dirname( __FILE__ ) . '/tabs/customers.php',
		'payments-recovery' => dirname( __FILE__ ) . '/tabs/payments-recovery.php',
		'taxes'             => dirname( __FILE__ ) . '/tabs/taxes.php',
		'export'            => dirname( __FILE__ ) . '/tabs/export.php',
	)
);

foreach ( $rpress_report_tab_files as $rpress_report_tab_file ) {
	if ( is_readable( $rpress_report_tab_file ) ) {
		require_once $rpress_report_tab_file;
	}
}

/**
 * Reports Page
 *
 * Renders the reports page contents.
 *
 * @since 1.0
 * @return void
*/
function rpress_reports_page() {
	$current_page = admin_url( 'admin.php?page=rpress-reports' );
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
	$tabs       = RPRESS_Reports_Intelligence::get_tabs();
	$filters    = RPRESS_Reports_Intelligence::get_filters();
	$range_options = RPRESS_Reports_Intelligence::get_range_options();
	$period_label  = isset( $range_options[ $filters['range'] ] ) ? $range_options[ $filters['range'] ] : esc_html__( 'Selected Period', 'restropress' );
	if ( ! isset( $tabs[ $active_tab ] ) ) {
		$active_tab = 'overview';
	}
	?>
	<div class="wrap rp-admin-scope rp-reports-page">
		<div class="rp-page-header rp-reports-header">
			<div class="rp-page-header-titles">
				<p class="rp-page-eyebrow"><?php esc_html_e( 'Reports', 'restropress' ); ?></p>
				<h1 class="wp-heading-inline rp-page-title"><?php esc_html_e( 'RestroPress Reports', 'restropress' ); ?></h1>
				<p class="rp-page-subtitle"><?php esc_html_e( 'Restaurant performance, risk trends, menu movement, customers, payments, and exports.', 'restropress' ); ?></p>
			</div>
			<div class="rp-reports-period-card rp-card">
				<span><?php esc_html_e( 'Selected period', 'restropress' ); ?></span>
				<div class="rp-reports-period-heading">
					<strong><?php echo esc_html( $period_label ); ?></strong>
					<button
						type="button"
						class="rp-icon-action rp-date-range-edit rp-reports-period-edit"
						aria-label="<?php esc_attr_e( 'Edit selected period', 'restropress' ); ?>"
					>
						<span class="dashicons dashicons-edit" aria-hidden="true"></span>
					</button>
				</div>
				<small><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $filters['start'] ) ) . ' - ' . date_i18n( get_option( 'date_format' ), strtotime( $filters['end'] ) ) ); ?></small>
			</div>
		</div>
		<hr class="wp-header-end">
		<nav class="nav-tab-wrapper rp-tabs" aria-label="<?php esc_attr_e( 'RestroPress reports tabs', 'restropress' ); ?>">
			<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'tab' => $tab_id, 'settings-updated' => false ), $current_page ) ); ?>" class="nav-tab rp-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tab_label ); ?></a>
			<?php endforeach; ?>
			<?php do_action( 'rpress_reports_tabs' ); ?>
		</nav>
		<?php
		do_action( 'rpress_reports_page_top' );
		do_action( 'rpress_reports_tab_' . $active_tab );
		do_action( 'rpress_reports_page_bottom' );
		?>
	</div><!-- .wrap -->
	<?php
}

/**
 * Render shared intelligence reports filters.
 *
 * @since 3.3
 *
 * @param string $active_tab Active report tab.
 * @param array  $filters Current filters.
 * @return void
 */
function rpress_reports_intelligence_filters( $active_tab, $filters ) {
	$service_options = function_exists( 'rpress_get_service_types' ) ? rpress_get_service_types() : array();
	$payment_options = rpress_get_payment_statuses();
	$order_options   = rpress_get_order_statuses();
	$range_options   = RPRESS_Reports_Intelligence::get_range_options();
	?>
	<form class="rp-grid rp-grid-filter rp-filter-bar rp-reports-intelligence-filter" method="get">
		<input type="hidden" name="page" value="rpress-reports" />
		<input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab ); ?>" />
		<input type="hidden" name="range" value="<?php echo esc_attr( $filters['range'] ); ?>" />
		<input type="hidden" name="start_date" value="<?php echo esc_attr( $filters['start_date'] ); ?>" />
		<input type="hidden" name="end_date" value="<?php echo esc_attr( $filters['end_date'] ); ?>" />
		<div class="rp-filter-field">
			<label for="rp-report-range"><?php esc_html_e( 'Date range', 'restropress' ); ?></label>
			<select
				id="rp-report-range"
				class="rp-select rp-date-range-preset"
				aria-label="<?php esc_attr_e( 'Select report date range', 'restropress' ); ?>"
			>
				<?php foreach ( $range_options as $range_id => $range_label ) : ?>
					<option value="<?php echo esc_attr( $range_id ); ?>" <?php selected( $filters['range'], $range_id ); ?>><?php echo esc_html( $range_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php if ( 'export' !== $active_tab ) : ?>
			<div class="rp-filter-field">
				<label for="rp-report-compare"><?php esc_html_e( 'Compare', 'restropress' ); ?></label>
				<span id="rp-report-compare" class="rp-filter-value" role="text"><?php esc_html_e( 'Previous Period', 'restropress' ); ?></span>
			</div>
		<?php endif; ?>
		<div class="rp-filter-field">
			<label for="rp-report-service"><?php esc_html_e( 'Service Type', 'restropress' ); ?></label>
			<select id="rp-report-service" name="service_type" class="rp-select">
				<option value=""><?php esc_html_e( 'All services', 'restropress' ); ?></option>
				<?php foreach ( $service_options as $service_id => $service_label ) : ?>
					<option value="<?php echo esc_attr( $service_id ); ?>" <?php selected( $filters['service_type'], $service_id ); ?>><?php echo esc_html( $service_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="rp-filter-field">
			<label for="rp-report-payment"><?php esc_html_e( 'Payment', 'restropress' ); ?></label>
			<select id="rp-report-payment" name="payment_status" class="rp-select">
				<option value=""><?php esc_html_e( 'All payments', 'restropress' ); ?></option>
				<?php foreach ( $payment_options as $payment_id => $payment_label ) : ?>
					<option value="<?php echo esc_attr( $payment_id ); ?>" <?php selected( $filters['payment_status'], $payment_id ); ?>><?php echo esc_html( $payment_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="rp-filter-field">
			<label for="rp-report-order-status"><?php esc_html_e( 'Order Status', 'restropress' ); ?></label>
			<select id="rp-report-order-status" name="order_status" class="rp-select">
				<option value=""><?php esc_html_e( 'All statuses', 'restropress' ); ?></option>
				<?php foreach ( $order_options as $status_id => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_id ); ?>" <?php selected( $filters['order_status'], $status_id ); ?>><?php echo esc_html( $status_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</form>
	<?php
}

/**
 * Placeholder for report tabs that are intentionally phased.
 *
 * @since 3.3
 *
 * @param string $tab Tab key.
 * @return void
 */
function rpress_reports_tab_coming_next( $tab ) {
	$filters = RPRESS_Reports_Intelligence::get_filters();
	$tabs = RPRESS_Reports_Intelligence::get_tabs();
	?>
	<section class="rp-grid rp-reports-intelligence">
		<?php rpress_reports_intelligence_filters( $tab, $filters ); ?>
		<div class="rp-card rp-reports-panel rp-reports-coming-next">
			<h2><?php echo esc_html( isset( $tabs[ $tab ] ) ? $tabs[ $tab ] : __( 'Report', 'restropress' ) ); ?></h2>
			<p><?php esc_html_e( 'This section is next in the phased Reports Intelligence rollout. Overview is live first so the shared data model can be verified before expanding the remaining tabs.', 'restropress' ); ?></p>
		</div>
	</section>
	<?php
}

/**
 * Default Report Views
 *
 * @since  1.0.0
 * @return array $views Report Views
 */
function rpress_reports_default_views() {
	$views = array(
		'earnings'   => esc_html__( 'Earnings', 'restropress' ),
		'categories' => esc_html__( 'Earnings by Category', 'restropress' ),
		'addons' 	 => esc_html__( 'Earnings by Addon', 'restropress' ),
		'fooditems'  => rpress_get_label_plural(),
		'gateways'   => esc_html__( 'Payment Methods', 'restropress' ),
		'taxes'      => esc_html__( 'Taxes', 'restropress' ),
	);
	$views = apply_filters( 'rpress_report_views', $views );
	return $views;
}
/**
 * Default Report Views
 *
 * Checks the $_GET['view'] parameter to ensure it exists within the default allowed views.
 *
 * @param string $default Default view to use.
 *
 * @since  1.0.0.6
 * @return string $view Report View
 *
 */
function rpress_get_reporting_view( $default = 'earnings' ) {
	if ( ! isset( $_GET['view'] ) || ! in_array( sanitize_text_field( $_GET['view'] ), array_keys( rpress_reports_default_views() ) ) ) {
		$view = $default;
	} else {
		$view = sanitize_text_field( $_GET['view'] );
	}
	return apply_filters( 'rpress_get_reporting_view', $view );
}
/**
 * Renders the Reports page
 *
 * @since 1.0
 * @return void
 */
function rpress_reports_tab_reports() {
	if( ! current_user_can( 'view_shop_reports' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this report', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}
	$current_view = 'earnings';
	$views        = rpress_reports_default_views();
	if ( isset( $_GET['view'] ) && array_key_exists( sanitize_text_field( $_GET['view'] ), $views ) )
		$current_view = sanitize_text_field( $_GET['view'] );
	do_action( 'rpress_reports_view_' . $current_view );
}
add_action( 'rpress_reports_tab_reports', 'rpress_reports_tab_reports' );
/**
 * Renders the Reports Page Views Drop Downs
 *
 * @since 1.0
 * @return void
 */
function rpress_report_views() {
	if( ! current_user_can( 'view_shop_reports' ) ) {
		return;
	}
	$views        = rpress_reports_default_views();
	$current_view = isset( $_GET['view'] )  ? sanitize_text_field( $_GET['view'] ) : 'earnings';
	?>
	<form id="rpress-reports-filter" class="rp-filter-bar rp-reports-filter" method="get">
		<select id="rpress-reports-view" name="view" class="rp-select rp-field-md">
			<option value="-1"><?php esc_html_e( 'Report Type', 'restropress' ); ?></option>
			<?php foreach ( $views as $view_id => $label ) : ?>
				<option value="<?php echo esc_attr( $view_id ); ?>" <?php selected( $view_id, $current_view ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php do_action( 'rpress_report_view_actions' ); ?>
		<input type="hidden" name="page" value="rpress-reports"/>
		<?php submit_button( esc_html__( 'Show', 'restropress' ), 'secondary rp-btn rp-btn-secondary', 'submit', false ); ?>
	</form>
	<?php
	do_action( 'rpress_report_view_actions_after' );
}
/**
 * Renders the Reports RestroPress Table
 *
 * @since 1.0
 * @uses RPRESS_Fooditem_Reports_Table::prepare_items()
 * @uses RPRESS_Fooditem_Reports_Table::display()
 * @return void
 */
function rpress_reports_fooditems_table() {
	if( ! current_user_can( 'view_shop_reports' ) ) {
		return;
	}
	if( isset( $_GET['fooditem-id'] ) )
		return;
	include( dirname( __FILE__ ) . '/class-fooditem-reports-table.php' );
	$fooditems_table = new RPRESS_Fooditem_Reports_Table();
	$fooditems_table->prepare_items();
	$fooditems_table->display();
}
add_action( 'rpress_reports_view_fooditems', 'rpress_reports_fooditems_table' );
/**
 * Renders the detailed report for a specific product
 *
 * @since  1.0.0
 * @return void
 */
function rpress_reports_fooditem_details() {
	if( ! current_user_can( 'view_shop_reports' ) ) {
		return;
	}
	if( ! isset( $_GET['fooditem-id'] ) )
		return;
?>
	<div class="tablenav top">
		<div class="actions bulkactions">
			<div class="alignleft">
				<?php rpress_report_views(); ?>
			</div>&nbsp;
			<button onclick="history.go(-1);" class="button button-secondary rp-btn rp-btn-secondary"><?php esc_html_e( 'Go Back', 'restropress' ); ?></button>
		</div>
	</div>
<?php
	rpress_reports_graph_of_fooditem( absint( $_GET['fooditem-id'] ) );
}
add_action( 'rpress_reports_view_fooditems', 'rpress_reports_fooditem_details' );
/**
 * Renders the Gateways Table
 *
 * @since 1.0
 * @uses RPRESS_Gateawy_Reports_Table::prepare_items()
 * @uses RPRESS_Gateawy_Reports_Table::display()
 * @return void
 */
function rpress_reports_gateways_table() {
	if( ! current_user_can( 'view_shop_reports' ) ) {
		return;
	}
	include( dirname( __FILE__ ) . '/class-gateways-reports-table.php' );
	$fooditems_table = new RPRESS_Gateawy_Reports_Table();
	$fooditems_table->prepare_items();
	$fooditems_table->display();
}
add_action( 'rpress_reports_view_gateways', 'rpress_reports_gateways_table' );
/**
 * Renders the Reports Earnings Graphs
 *
 * @since 1.0
 * @return void
 */
function rpress_reports_earnings() {
	if( ! current_user_can( 'view_shop_reports' ) ) {
		return;
	}
	?>
	<div class="tablenav top">
		<div class="alignleft actions"><?php rpress_report_views(); ?></div>
	</div>
	<?php
	rpress_reports_graph();
}
add_action( 'rpress_reports_view_earnings', 'rpress_reports_earnings' );
/**
 * Renders the Reports Earnings By Category Table & Graphs
 *
 * @since 1.0
 */
function rpress_reports_categories() {
	if( ! current_user_can( 'view_shop_reports' ) ) {
		return;
	}
	include( dirname( __FILE__ ) . '/class-categories-reports-table.php' );
	?>
			<div class="inside">
				<?php
				$categories_table = new RPRESS_Categories_Reports_Table();
				$categories_table->prepare_items();
				$categories_table->display();
				?>
				<?php
				$allowed_html = array(
					'script' => array(
						'type' => true,
					),
				);
				
				echo wp_kses( $categories_table->load_scripts(), $allowed_html );
				?>
				<div class="rpress-mix-totals">
					<div class="rpress-mix-chart">
						<strong><?php esc_html_e( 'Category Sales Mix: ', 'restropress' ); ?></strong>
						<?php $categories_table->output_sales_graph(); ?>
					</div>
					<div class="rpress-mix-chart">
						<strong><?php esc_html_e( 'Category Earnings Mix: ', 'restropress' ); ?></strong>
						<?php $categories_table->output_earnings_graph(); ?>
					</div>
				</div>
				<?php do_action( 'rpress_reports_graph_additional_stats' ); ?>
				<p class="rpress-graph-notes">
					<span>
						<em><sup>&dagger;</sup> <?php esc_html_e( 'All Parent categories include sales and earnings stats from child categories.', 'restropress' ); ?></em>
					</span>
					<span>
						<em><?php esc_html_e( 'Stats include all sales and earnings for the lifetime of the store.', 'restropress' ); ?></em>
					</span>
				</p>
			</div>
	<?php
}
add_action( 'rpress_reports_view_categories', 'rpress_reports_categories' );
/**
 * Renders the Reports Earnings By Addon Table & Graphs
 *
 * @since 1.0
 */
function rpress_reports_addons() {
	if( ! current_user_can( 'view_shop_reports' ) ) {
		return;
	}
	include( dirname( __FILE__ ) . '/class-addons-reports-table.php' );
	?>
			<div class="inside">
				<?php
				$categories_table = new RPRESS_Addons_Reports_Table();
				$categories_table->prepare_items();
				$categories_table->display();
				?>
				<?php
				$allowed_html = array(
					'script' => array(
						'type' => true,
					),
				);
				
				echo wp_kses( $categories_table->load_scripts(), $allowed_html );
				?>
				<div class="rpress-mix-totals">
					<div class="rpress-mix-chart">
						<strong><?php esc_html_e( 'Category Sales Mix: ', 'restropress' ); ?></strong>
						<?php $categories_table->output_sales_graph(); ?>
					</div>
					<div class="rpress-mix-chart">
						<strong><?php esc_html_e( 'Category Earnings Mix: ', 'restropress' ); ?></strong>
						<?php $categories_table->output_earnings_graph(); ?>
					</div>
				</div>
				<?php do_action( 'rpress_reports_graph_additional_stats' ); ?>
				<p class="rpress-graph-notes">
					<span>
						<em><sup>&dagger;</sup> <?php esc_html_e( 'All Parent categories include sales and earnings stats from child categories.', 'restropress' ); ?></em>
					</span>
					<span>
						<em><?php esc_html_e( 'Stats include all sales and earnings for the lifetime of the store.', 'restropress' ); ?></em>
					</span>
				</p>
			</div>
	<?php
}
add_action( 'rpress_reports_view_addons', 'rpress_reports_addons' );
/**
 * Renders the Tax Reports
 *
 * @since 1.0.0
 * @return void
 */
function rpress_reports_taxes() {
	if( ! current_user_can( 'view_shop_reports' ) ) {
		return;
	}
	$year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : gmdate( 'Y' );
	?>
	<div class="tablenav top">
		<div class="alignleft actions"><?php rpress_report_views(); ?></div>
	</div>
	<div class="metabox-holder" style="padding-top: 0;">
		<div class="postbox">
			<h3><span><?php esc_html_e('Tax Report','restropress' ); ?></span></h3>
			<div class="inside">
				<p><?php esc_html_e( 'This report shows the total amount collected in sales tax for the given year.', 'restropress' ); ?></p>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<span><?php echo esc_html( $year ); ?></span>: <strong><?php rpress_sales_tax_for_year( $year ); ?></strong>&nbsp;&mdash;&nbsp;
					<select name="year">
						<?php for ( $i = 2009; $i <= gmdate( 'Y' ); $i++ ) : ?>
						<option value="<?php echo esc_html( $i ); ?>"<?php selected( $year, $i ); ?>><?php echo esc_html( $i ); ?></option>
						<?php endfor; ?>
					</select>
					<input type="hidden" name="page" value="rpress-reports" />
					<input type="hidden" name="view" value="taxes" />
			<?php submit_button( esc_html__( 'Submit', 'restropress' ), 'secondary rp-btn rp-btn-secondary', 'submit', false ); ?>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->
	</div><!-- .metabox-holder -->
	<?php
}
add_action( 'rpress_reports_view_taxes', 'rpress_reports_taxes' );
/**
 * Retrieves estimated monthly earnings and sales
 *
 * @since 1.0
 *
 * @param bool  $include_taxes If the estimated earnings should include taxes
 * @return array
 */
function rpress_estimated_monthly_stats( $include_taxes = true ) {
	$estimated = get_transient( 'rpress_estimated_monthly_stats' . $include_taxes );
	if ( false === $estimated ) {
		$estimated = array(
			'earnings' => 0,
			'sales'    => 0
		);
		$stats = new RPRESS_Payment_Stats;
		$to_date_earnings = $stats->get_earnings( 0, 'this_month', null, $include_taxes );
		$to_date_sales    = $stats->get_sales( 0, 'this_month' );
		$current_day      = gmdate( 'd', current_time( 'timestamp' ) );
		$current_month    = gmdate( 'n', current_time( 'timestamp' ) );
		$current_year     = gmdate( 'Y', current_time( 'timestamp' ) );
		$days_in_month    = cal_days_in_month( CAL_GREGORIAN, $current_month, $current_year );
		$estimated['earnings'] = ( $to_date_earnings / $current_day ) * $days_in_month;
		$estimated['sales']    = ( $to_date_sales / $current_day ) * $days_in_month;
		// Cache for one day
		set_transient( 'rpress_estimated_monthly_stats' . $include_taxes, $estimated, 86400 );
	}
	return maybe_unserialize( $estimated );
}
