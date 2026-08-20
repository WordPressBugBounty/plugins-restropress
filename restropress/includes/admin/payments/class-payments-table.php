<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		$this->base_url = admin_url( 'admin.php?page=rpress-payment-history' );
		add_action( 'admin_footer', array( $this, 'order_preview_template' ) );
	}

	/**
	 * Snapshot metrics for the KPI cards above the orders list.
	 *
	 * Returns today's totals + yesterday's same totals for delta computation,
	 * cached for 60 seconds because the same payload can be requested several
	 * times in a single page render.
	 *
	 * @since 3.3
	 * @return array
	 */
	public static function get_kpi_data() {
		$cache_key = 'rpress_orders_kpi_' . date_i18n( 'Y_m_d_H' ); // refresh hourly
		$cached    = wp_cache_get( $cache_key, 'rpress' );
		if ( false !== $cached ) {
			return $cached;
		}

		$today_metrics     = self::compute_day_metrics( 'today' );
		$yesterday_metrics = self::compute_day_metrics( 'yesterday' );

		$delta = function ( $today, $yesterday ) {
			$yesterday = (float) $yesterday;
			if ( $yesterday <= 0 ) {
				return null;
			}
			return ( ( (float) $today - $yesterday ) / $yesterday ) * 100;
		};

		$kpi = array(
			'total'           => array(
				'value' => $today_metrics['total'],
				'delta' => $delta( $today_metrics['total'], $yesterday_metrics['total'] ),
			),
			'needs_attention' => array(
				'value' => $today_metrics['needs_attention'],
				'delta' => $delta( $today_metrics['needs_attention'], $yesterday_metrics['needs_attention'] ),
			),
			'in_progress'     => array(
				'value' => $today_metrics['in_progress'],
				'delta' => $delta( $today_metrics['in_progress'], $yesterday_metrics['in_progress'] ),
			),
			'revenue'         => array(
				'value' => $today_metrics['revenue'],
				'delta' => $delta( $today_metrics['revenue'], $yesterday_metrics['revenue'] ),
			),
		);

		wp_cache_set( $cache_key, $kpi, 'rpress', 300 );
		return $kpi;
	}

	/**
	 * Range-scoped KPI data for the past-mode cards.
	 *
	 *   total          - every order in range (excluding trash)
	 *   completed      - orders with order_status completed or transit
	 *   completed_pct  - completed / total * 100
	 *   revenue        - sum of paid online orders plus accepted cash orders,
	 *                    excluding refunded + failed
	 *   refunds_sum    - sum of refunded order totals
	 *   refunds_count  - number of refunded orders
	 *   aov            - average order value over non-refunded orders
	 *
	 * @since 3.3
	 * @param string $start_ymd 'Y-m-d'
	 * @param string $end_ymd   'Y-m-d'
	 * @return array
	 */
	public static function get_kpi_data_for_range( $start_ymd, $end_ymd ) {
		$cache_key = 'rpress_orders_kpi_range_v2_' . md5( $start_ymd . '_' . $end_ymd );
		$cached    = wp_cache_get( $cache_key, 'rpress' );
		if ( false !== $cached ) {
			return $cached;
		}

		$args = array(
			'post_type'              => 'rpress_payment',
			'post_status'            => array( 'publish', 'pending', 'processing', 'refunded', 'failed' ),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'date_query'             => array(
				array(
					'after'     => $start_ymd . ' 00:00:00',
					'before'    => $end_ymd   . ' 23:59:59',
					'inclusive' => true,
				),
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);
		$q   = new WP_Query( $args );
		$ids = $q->posts;

		$total         = 0;
		$completed     = 0;
		$revenue       = 0.0;
		$refunds_sum   = 0.0;
		$refunds_count = 0;

		foreach ( $ids as $id ) {
			$total++;
			$amt          = (float) get_post_meta( $id, '_rpress_payment_total', true );
			$post_status  = get_post_status( $id );
			$order_status = rpress_get_order_status( $id );

			if ( $post_status === 'refunded' ) {
				$refunds_sum += $amt;
				$refunds_count++;
			} elseif ( $post_status !== 'failed' ) {
				$gateway = get_post_meta( $id, '_rpress_payment_gateway', true );
				$is_cash = self::is_cash_gateway( $gateway );
				if ( 'publish' === $post_status || ( $is_cash && 'pending' !== $order_status ) ) {
					$revenue += $amt;
				}
				if ( in_array( $order_status, array( 'completed', 'transit' ), true ) ) {
					$completed++;
				}
			}
		}

		$non_refunded   = max( 0, $total - $refunds_count );
		$aov            = $non_refunded > 0 ? $revenue / $non_refunded : 0;
		$completed_pct  = $total > 0 ? ( $completed / $total ) * 100 : 0;

		$data = array(
			'total'         => $total,
			'completed'     => $completed,
			'completed_pct' => $completed_pct,
			'revenue'       => $revenue,
			'refunds_sum'   => $refunds_sum,
			'refunds_count' => $refunds_count,
			'aov'           => $aov,
		);

		wp_cache_set( $cache_key, $data, 'rpress', 300 );
		return $data;
	}

	/**
	 * Compute the day-scoped metrics that feed the KPI cards.
	 *
	 *   total           - every order created on the day (any payment status,
	 *                     including refunded - gross order count).
	 *   needs_attention - orders requiring staff action: pending order_status
	 *                     OR service time already passed without being done
	 *                     OR payment status pending.
	 *   in_progress     - accepted, processing, ready, transit.
	 *   revenue         - sum of order totals for paid (publish) orders today
	 *                     plus cash orders that have been accepted (i.e. not
	 *                     still pending payment). Excludes refunded/failed.
	 *
	 * @since 3.3
	 * @param string $day 'today' | 'yesterday'
	 * @return array
	 */
	protected static function compute_day_metrics( $day ) {
		$ts = ( $day === 'yesterday' ) ? strtotime( '-1 day' ) : current_time( 'timestamp' );
		$now = current_time( 'timestamp' );

		$args = array(
			'post_type'              => 'rpress_payment',
			'post_status'            => array( 'publish', 'pending', 'processing', 'refunded', 'failed' ),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'date_query'             => array(
				array(
					'year'  => (int) date_i18n( 'Y', $ts ),
					'month' => (int) date_i18n( 'm', $ts ),
					'day'   => (int) date_i18n( 'd', $ts ),
				),
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);

		$q   = new WP_Query( $args );
		$ids = $q->posts;

		$total           = 0;
		$needs_attention = 0;
		$in_progress     = 0;
		$revenue         = 0.0;
		$done_statuses   = array( 'completed', 'transit', 'cancelled', 'refunded', 'failed' );
		$progress_set    = array( 'accepted', 'processing', 'ready', 'transit' );

		foreach ( $ids as $id ) {
			$total++;
			$order_status = rpress_get_order_status( $id );
			$post_status  = get_post_status( $id );

			// In Progress bucket.
			if ( in_array( $order_status, $progress_set, true ) ) {
				$in_progress++;
			}

			// Needs Attention: pending order_status, OR pending payment status,
			// OR service time has passed without being done.
			$is_pending_order   = ( $order_status === 'pending' );
			$is_pending_payment = ( $post_status === 'pending' );
			$is_late            = false;
			if ( ! in_array( $order_status, $done_statuses, true ) ) {
				$svc_ts = self::parse_service_timestamp( $id );
				if ( $svc_ts && $svc_ts <= $now ) {
					$is_late = true;
				}
			}
			if ( $is_pending_order || $is_pending_payment || $is_late ) {
				$needs_attention++;
			}

			// Revenue: paid online (publish) OR accepted cash, both excluding
			// refunded/failed. We use the rough proxy: include if the order is
			// not refunded/failed and either has post_status publish or has a
			// cash-like gateway and order_status is not pending.
			if ( ! in_array( $post_status, array( 'refunded', 'failed' ), true ) ) {
				$gateway = get_post_meta( $id, '_rpress_payment_gateway', true );
				$is_cash = self::is_cash_gateway( $gateway );
				$counts_for_revenue = ( $post_status === 'publish' )
					|| ( $is_cash && $order_status !== 'pending' );
				if ( $counts_for_revenue ) {
					$revenue += (float) get_post_meta( $id, '_rpress_payment_total', true );
				}
			}
		}

		return array(
			'total'           => $total,
			'needs_attention' => $needs_attention,
			'in_progress'     => $in_progress,
			'revenue'         => $revenue,
		);
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
	/**
	 * True when the screen should render in "Past Orders" mode rather than
	 * the operational Today view. The trigger is purely the selected date
	 * range: Today (or empty / implicit-today) keeps the operational mode;
	 * any other range - Yesterday, Last 7 days, This month, Last month, a
	 * Custom range - switches to historical mode.
	 *
	 * @since 3.3
	 * @return bool
	 */
	public static function is_past_mode() {
		if ( self::is_all_time_mode() ) {
			return true;
		}

		$start = isset( $_GET['start-date'] ) ? sanitize_text_field( wp_unslash( $_GET['start-date'] ) ) : '';
		$end   = isset( $_GET['end-date'] )   ? sanitize_text_field( wp_unslash( $_GET['end-date'] ) )   : '';
		if ( $start === '' && $end === '' ) {
			return false; // implicit today
		}
		$today = date_i18n( 'Y-m-d' );
		if ( self::normalize_filter_date_for_query( $start ) === $today && self::normalize_filter_date_for_query( $end ) === $today ) {
			return false; // explicit today
		}
		return true;
	}

	/**
	 * True when the date filter should intentionally show every order.
	 *
	 * Empty start/end dates still mean "Today" on the default operations page,
	 * so all-time needs an explicit flag.
	 *
	 * @since 3.3
	 * @return bool
	 */
	public static function is_all_time_mode() {
		$date_range = isset( $_GET['date-range'] ) ? sanitize_key( wp_unslash( $_GET['date-range'] ) ) : '';
		return 'all' === $date_range;
	}

	/**
	 * Format a timestamp for payment date filters using the WordPress date format.
	 *
	 * @since 3.3
	 * @param int|null $timestamp Timestamp. Defaults to current site time.
	 * @return string
	 */
	protected static function format_filter_date( $timestamp = null ) {
		$timestamp = $timestamp ? $timestamp : current_time( 'timestamp' );
		return date_i18n( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Normalize a displayed payment filter date into Y-m-d for internal queries.
	 *
	 * @since 3.3
	 * @param string $date Date from request.
	 * @return string
	 */
	protected static function normalize_filter_date_for_query( $date ) {
		return function_exists( 'rpress_parse_payment_filter_date' ) ? rpress_parse_payment_filter_date( $date ) : '';
	}

	/**
	 * Human-readable placeholder for the site's WordPress date format.
	 *
	 * @since 3.3
	 * @return string
	 */
	protected static function get_filter_date_placeholder() {
		$placeholder = get_option( 'date_format' );
		$replacements = array(
			'Y' => 'yyyy',
			'y' => 'yy',
			'm' => 'mm',
			'n' => 'm',
			'd' => 'dd',
			'j' => 'd',
			'F' => 'Month',
			'M' => 'Mon',
		);

		return strtr( $placeholder, $replacements );
	}

	/**
	 * Date row - chip-based date shortcuts (Today / Yesterday / Last 7 days /
	 * This month / Custom).
	 *
	 * Sits ABOVE the status tabs in the page flow so the chosen day frames
	 * everything that follows.
	 *
	 * @since 3.3
	 */
	public function date_row() {
		$start_date = isset( $_GET['start-date'] ) ? sanitize_text_field( wp_unslash( $_GET['start-date'] ) ) : '';
		$end_date   = isset( $_GET['end-date'] )   ? sanitize_text_field( wp_unslash( $_GET['end-date'] ) )   : '';
		$is_all_time = self::is_all_time_mode();

		$today_str     = self::format_filter_date();
		$yesterday_str = self::format_filter_date( strtotime( '-1 day' ) );
		$seven_days    = self::format_filter_date( strtotime( '-6 days' ) );
		$month_start   = self::format_filter_date( strtotime( 'first day of this month' ) );
		$date_placeholder = self::get_filter_date_placeholder();

		$shortcuts = array(
			'today'      => array( 'label' => __( 'Today',       'restropress' ), 'start' => $today_str,    'end' => $today_str ),
			'yesterday'  => array( 'label' => __( 'Yesterday',   'restropress' ), 'start' => $yesterday_str, 'end' => $yesterday_str ),
			'last7days'  => array( 'label' => __( 'Last 7 days', 'restropress' ), 'start' => $seven_days,    'end' => $today_str ),
			'thismonth'  => array( 'label' => __( 'This month',  'restropress' ), 'start' => $month_start,   'end' => $today_str ),
		);

		$has_search = isset( $_GET['s'] ) && '' !== sanitize_text_field( wp_unslash( $_GET['s'] ) );

		// Today is the default selected state when no custom range/search is set.
		$active = '';
		foreach ( $shortcuts as $key => $s ) {
			if (
				self::normalize_filter_date_for_query( $start_date ) === self::normalize_filter_date_for_query( $s['start'] )
				&& self::normalize_filter_date_for_query( $end_date ) === self::normalize_filter_date_for_query( $s['end'] )
			) {
				$active = $key;
				break;
			}
		}
		if ( $is_all_time ) {
			$active = 'alltime';
		}

		$is_custom = ( $active === '' && ! $is_all_time && ( $start_date !== '' || $end_date !== '' ) );
		if ( $active === '' && ! $is_custom && ! $has_search ) {
			// No date filter applied - Today is the implicit default for the page.
			$active = 'today';
		}
		?>
		<div class="rp-orders-daterow">
			<span class="rp-orders-daterow-label"><?php esc_html_e( 'Date', 'restropress' ); ?></span>
			<div class="rp-orders-datechips" role="group" aria-label="<?php esc_attr_e( 'Date shortcuts', 'restropress' ); ?>">
				<?php foreach ( $shortcuts as $key => $s ) :
					$chip_url = $this->build_filter_url( array(
						'start-date' => $s['start'],
						'end-date'   => $s['end'],
						'date-range' => false,
						'paged'      => false,
					) );
				?>
					<a href="<?php echo esc_url( $chip_url ); ?>"
					   class="rp-filter-chip rp-datechip<?php echo $active === $key ? ' is-active' : ''; ?>">
						<?php echo esc_html( $s['label'] ); ?>
					</a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( $this->build_filter_url( array( 'date-range' => 'all', 'start-date' => false, 'end-date' => false, 'paged' => false ) ) ); ?>"
				   class="rp-filter-chip rp-datechip<?php echo $active === 'alltime' ? ' is-active' : ''; ?>">
					<?php esc_html_e( 'All time', 'restropress' ); ?>
				</a>
				<button type="button"
					class="rp-filter-chip rp-datechip rp-datechip-custom<?php echo $is_custom ? ' is-active' : ''; ?>"
					aria-controls="rpress-payment-custom-date-panel"
					aria-expanded="<?php echo $is_custom ? 'true' : 'false'; ?>"
					data-current-custom="<?php echo $is_custom ? '1' : '0'; ?>"
					title="<?php esc_attr_e( 'Pick a custom date range', 'restropress' ); ?>">
					<?php esc_html_e( 'Custom', 'restropress' ); ?>
				</button>
			</div>
		</div>
		<div id="rpress-payment-custom-date-panel" class="rp-date-custom-panel"<?php echo $is_custom ? '' : ' hidden'; ?>>
			<div class="rp-grid rp-grid-2 rp-date-custom-fields">
				<span class="rp-filter-field">
					<label for="start-date"><?php esc_html_e( 'Start date', 'restropress' ); ?></label>
					<input type="text" id="start-date" name="start-date" class="rp-input rpress_datepicker" value="<?php echo esc_attr( $start_date ); ?>" placeholder="<?php echo esc_attr( $date_placeholder ); ?>" autocomplete="off" />
				</span>
				<span class="rp-filter-field">
					<label for="end-date"><?php esc_html_e( 'End date', 'restropress' ); ?></label>
					<input type="text" id="end-date" name="end-date" class="rp-input rpress_datepicker" value="<?php echo esc_attr( $end_date ); ?>" placeholder="<?php echo esc_attr( $date_placeholder ); ?>" autocomplete="off" />
				</span>
			</div>
			<div class="rp-date-custom-actions">
				<input type="submit" class="button button-primary rp-btn rp-btn-primary" value="<?php esc_attr_e( 'Apply date', 'restropress' ); ?>" />
				<button type="button" class="button button-secondary rp-btn rp-btn-secondary rp-date-custom-cancel">
					<?php esc_html_e( 'Cancel', 'restropress' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	public function advanced_filters() {
		$start_date       = isset( $_GET['start-date'] )   ? sanitize_text_field( wp_unslash( $_GET['start-date'] ) )   : '';
		$end_date         = isset( $_GET['end-date'] )     ? sanitize_text_field( wp_unslash( $_GET['end-date'] ) )     : '';
		$is_all_time      = self::is_all_time_mode();
		$service_date     = isset( $_GET['service-date'] ) ? sanitize_text_field( wp_unslash( $_GET['service-date'] ) ) : '';
		$payment_status   = isset( $_GET['status'] )       ? sanitize_text_field( wp_unslash( $_GET['status'] ) )       : '';
		$selected_gateway = isset( $_GET['gateway'] )      ? sanitize_text_field( wp_unslash( $_GET['gateway'] ) )      : 'all';
		$selected_type    = isset( $_GET['service-type'] ) ? sanitize_text_field( wp_unslash( $_GET['service-type'] ) ) : 'all';
		$search_term      = isset( $_GET['s'] )            ? sanitize_text_field( wp_unslash( $_GET['s'] ) )            : '';

		$all_gateways         = rpress_get_payment_gateways();
		$all_payment_statuses = rpress_get_payment_statuses();
		$service_types        = $this->get_available_service_types();

		$gateways = array( 'all' => esc_html__( 'All Gateways', 'restropress' ) );
		if ( ! empty( $all_gateways ) ) {
			foreach ( $all_gateways as $slug => $admin_label ) {
				$gateways[ esc_attr( $slug ) ] = esc_html( $admin_label['admin_label'] );
			}
		}
		$gateways = apply_filters( 'rpress_payments_table_gateways', $gateways );

		$payment_statuses = array( 'all' => esc_html__( 'Any payment status', 'restropress' ) );
		if ( ! empty( $all_payment_statuses ) ) {
			foreach ( $all_payment_statuses as $slug => $label ) {
				if ( $slug === 'trash' ) { continue; }
				$payment_statuses[ esc_attr( $slug ) ] = esc_html( $label );
			}
		}

		// Date shortcut chips - Today is the default visual state when no
		// custom range is set. Each chip writes start-date and end-date into
		// the form and submits.
		$today_str     = self::format_filter_date();
		$yesterday_str = self::format_filter_date( strtotime( '-1 day' ) );
		$seven_days    = self::format_filter_date( strtotime( '-6 days' ) );
		$month_start   = self::format_filter_date( strtotime( 'first day of this month' ) );

		$date_shortcuts = array(
			'today'      => array( 'label' => __( 'Today',       'restropress' ), 'start' => $today_str,    'end' => $today_str ),
			'yesterday'  => array( 'label' => __( 'Yesterday',   'restropress' ), 'start' => $yesterday_str, 'end' => $yesterday_str ),
			'last7days'  => array( 'label' => __( 'Last 7 days', 'restropress' ), 'start' => $seven_days,    'end' => $today_str ),
			'thismonth'  => array( 'label' => __( 'This month',  'restropress' ), 'start' => $month_start,   'end' => $today_str ),
		);

		// Determine the active shortcut (or "custom" if dates don't match any preset).
		$active_preset = '';
		foreach ( $date_shortcuts as $key => $shortcut ) {
			if (
				self::normalize_filter_date_for_query( $start_date ) === self::normalize_filter_date_for_query( $shortcut['start'] )
				&& self::normalize_filter_date_for_query( $end_date ) === self::normalize_filter_date_for_query( $shortcut['end'] )
			) {
				$active_preset = $key;
				break;
			}
		}
		if ( $active_preset === '' && ( $start_date !== '' || $end_date !== '' ) ) {
			$active_preset = 'custom';
		}

		// Active filter chips (above the table) - one chip per applied filter.
		$active_chips = array();
		if ( ! $is_all_time && ( $start_date !== '' || $end_date !== '' ) ) {
			$active_chips[] = array(
				'label'  => sprintf(
					/* translators: 1: start date, 2: end date */
					esc_html__( 'Date: %1$s – %2$s', 'restropress' ),
					$start_date !== '' ? $start_date : '…',
					$end_date !== ''   ? $end_date   : '…'
				),
				'remove' => array( 'start-date', 'end-date' ),
			);
		}
		if ( $service_date !== '' ) {
			$active_chips[] = array(
				'label'  => sprintf( esc_html__( 'Service date: %s', 'restropress' ), $service_date ),
				'remove' => array( 'service-date' ),
			);
		}
		if ( $selected_gateway !== 'all' && $selected_gateway !== '' ) {
			$gateway_label = isset( $gateways[ $selected_gateway ] ) ? $gateways[ $selected_gateway ] : $selected_gateway;
			$active_chips[] = array(
				'label'  => sprintf( esc_html__( 'Gateway: %s', 'restropress' ), $gateway_label ),
				'remove' => array( 'gateway' ),
			);
		}
		if ( $selected_type !== 'all' && $selected_type !== '' ) {
			$type_label = isset( $service_types[ $selected_type ] ) ? $service_types[ $selected_type ] : $selected_type;
			$active_chips[] = array(
				'label'  => sprintf( esc_html__( 'Type: %s', 'restropress' ), $type_label ),
				'remove' => array( 'service-type' ),
			);
		}
		if ( $payment_status !== '' && $payment_status !== 'trash' ) {
			$ps_label = isset( $payment_statuses[ $payment_status ] ) ? $payment_statuses[ $payment_status ] : $payment_status;
			$active_chips[] = array(
				'label'  => sprintf( esc_html__( 'Payment: %s', 'restropress' ), $ps_label ),
				'remove' => array( 'status' ),
			);
		}
		$current_order_s = isset( $_GET['order_status'] ) ? sanitize_text_field( wp_unslash( $_GET['order_status'] ) ) : '';
		$all_order_stat  = rpress_get_order_statuses();
		if ( $current_order_s !== '' ) {
			$order_status_label = 'needs_attention' === $current_order_s
				? __( 'Needs Attention', 'restropress' )
				: ( isset( $all_order_stat[ $current_order_s ] ) ? $all_order_stat[ $current_order_s ] : $current_order_s );
			$active_chips[] = array(
				'label'  => sprintf( esc_html__( 'Order status: %s', 'restropress' ), $order_status_label ),
				'remove' => array( 'order_status' ),
			);
		}
		if ( $search_term !== '' ) {
			$active_chips[] = array(
				'label'  => sprintf( esc_html__( 'Search: %s', 'restropress' ), $search_term ),
				'remove' => array( 's' ),
			);
		}
		$current_urgency = isset( $_GET['urgency'] ) ? sanitize_text_field( wp_unslash( $_GET['urgency'] ) ) : '';
		if ( $current_urgency === 'late' ) {
			$active_chips[] = array(
				'label'  => esc_html__( 'Urgency: Late', 'restropress' ),
				'remove' => array( 'urgency' ),
			);
		} elseif ( $current_urgency === 'due_soon' ) {
			$active_chips[] = array(
				'label'  => esc_html__( 'Urgency: Due Soon', 'restropress' ),
				'remove' => array( 'urgency' ),
			);
		}

		// Expand the More filters panel if anything inside it is active.
		$expand_advanced = (
			$service_date !== ''
			|| $selected_gateway !== 'all'
			|| ( $payment_status !== '' && $payment_status !== 'trash' )
		);

		// Urgency chips - appear only when there are matching orders.
		$urgent          = self::get_urgent_order_ids();
		$due_soon_count  = count( $urgent['due_soon'] );
		$late_count      = count( $urgent['late'] );
		$active_urgency  = isset( $_GET['urgency'] ) ? sanitize_text_field( wp_unslash( $_GET['urgency'] ) ) : '';
		$date_placeholder = self::get_filter_date_placeholder();
		?>
		<div id="rpress-payment-filters" class="rp-filter-bar rp-filters">

			<!-- Top bar: Status (past only) / Type / Urgency / More filters + Search. -->
			<div class="rp-filters-topbar">
				<div class="rp-filters-controls">
					<?php
					$is_past_mode    = self::is_past_mode();
					?>
					<?php if ( $is_past_mode ) : ?>
						<label class="rp-filter-dropdown rp-filter-dropdown-status">
							<span class="rp-filter-dropdown-label"><?php esc_html_e( 'Status', 'restropress' ); ?></span>
							<select name="order_status" class="rp-select rp-filter-dropdown-select rp-filter-autosubmit">
								<option value="" <?php selected( $current_order_s, '' ); ?>><?php esc_html_e( 'All statuses', 'restropress' ); ?></option>
								<option value="needs_attention" <?php selected( $current_order_s, 'needs_attention' ); ?>><?php esc_html_e( 'Needs Attention', 'restropress' ); ?></option>
								<?php foreach ( $all_order_stat as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_order_s, $slug ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endif; ?>

					<?php if ( count( $service_types ) > 1 ) : ?>
						<div class="rp-filter-segment" role="group" aria-label="<?php esc_attr_e( 'Filter by order type', 'restropress' ); ?>">
							<span class="rp-filter-segment-label"><?php esc_html_e( 'Type', 'restropress' ); ?></span>
							<a href="<?php echo esc_url( $this->build_filter_url( array( 'service-type' => false, 'paged' => false ) ) ); ?>"
							   class="rp-filter-segment-option<?php echo ( $selected_type === 'all' || $selected_type === '' ) ? ' is-active' : ''; ?>">
								<?php esc_html_e( 'All', 'restropress' ); ?>
							</a>
							<?php foreach ( $service_types as $key => $label ) : ?>
								<a href="<?php echo esc_url( $this->build_filter_url( array( 'service-type' => $key, 'paged' => false ) ) ); ?>"
								   class="rp-filter-segment-option<?php echo $selected_type === $key ? ' is-active' : ''; ?>">
									<?php echo esc_html( $label ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( ! $is_past_mode ) : ?>
						<div class="rp-filter-segment rp-filter-segment-urgency" role="group" aria-label="<?php esc_attr_e( 'Filter by order urgency', 'restropress' ); ?>">
							<span class="rp-filter-segment-label"><?php esc_html_e( 'Urgency', 'restropress' ); ?></span>
							<a href="<?php echo esc_url( $this->build_filter_url( array( 'urgency' => false, 'paged' => false ) ) ); ?>"
							   class="rp-filter-segment-option<?php echo $active_urgency === '' ? ' is-active' : ''; ?>">
								<?php esc_html_e( 'Any', 'restropress' ); ?>
							</a>
							<a href="<?php echo esc_url( $this->build_filter_url( array( 'urgency' => 'due_soon', 'paged' => false ) ) ); ?>"
							   class="rp-filter-segment-option is-due-soon<?php echo $active_urgency === 'due_soon' ? ' is-active' : ''; ?>">
								<?php
								if ( $due_soon_count > 0 ) {
									printf( esc_html__( 'Due Soon %d', 'restropress' ), (int) $due_soon_count );
								} else {
									esc_html_e( 'Due Soon', 'restropress' );
								}
								?>
							</a>
							<a href="<?php echo esc_url( $this->build_filter_url( array( 'urgency' => 'late', 'paged' => false ) ) ); ?>"
							   class="rp-filter-segment-option is-late<?php echo $active_urgency === 'late' ? ' is-active' : ''; ?>">
								<?php
								if ( $late_count > 0 ) {
									printf( esc_html__( 'Late %d', 'restropress' ), (int) $late_count );
								} else {
									esc_html_e( 'Late', 'restropress' );
								}
								?>
							</a>
						</div>
					<?php endif; ?>

					<button type="button"
						class="rp-filter-chip rp-filters-more-toggle<?php echo $expand_advanced ? ' is-open' : ''; ?>"
						aria-controls="rpress-payment-filters-advanced"
						aria-expanded="<?php echo $expand_advanced ? 'true' : 'false'; ?>">
						<span class="dashicons dashicons-filter" aria-hidden="true"></span>
						<?php esc_html_e( 'More filters', 'restropress' ); ?>
					</button>
				</div>

				<div class="rp-filters-search">
					<label for="rpress-payments-search-input" class="screen-reader-text"><?php esc_html_e( 'Search orders', 'restropress' ); ?></label>
					<span class="dashicons dashicons-search rp-filters-search-icon" aria-hidden="true"></span>
					<input type="search"
						id="rpress-payments-search-input"
						name="s"
						class="rp-input"
						value="<?php echo esc_attr( $search_term ); ?>"
						placeholder="<?php esc_attr_e( 'Search order ID, customer, phone, email or item…', 'restropress' ); ?>" />
				</div>
			</div>

			<!-- Active filter chips (only when any filter is active) -->
			<?php if ( ! empty( $active_chips ) ) : ?>
				<div class="rp-filters-active">
					<span class="rp-filters-active-label"><?php esc_html_e( 'Filters:', 'restropress' ); ?></span>
					<?php foreach ( $active_chips as $chip ) : ?>
						<a href="<?php echo esc_url( $this->build_filter_url( array(), $chip['remove'] ) ); ?>"
						   class="rp-filter-chip rp-filter-active-chip"
						   title="<?php esc_attr_e( 'Remove filter', 'restropress' ); ?>">
							<span><?php echo esc_html( $chip['label'] ); ?></span>
							<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
						</a>
					<?php endforeach; ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-payment-history' ) ); ?>"
					   class="rp-filter-clear-all">
						<?php esc_html_e( 'Clear all', 'restropress' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<!-- More filters panel -->
			<div id="rpress-payment-filters-advanced" class="rp-filters-advanced"<?php echo $expand_advanced ? '' : ' hidden'; ?>>
				<div class="rp-grid rp-grid-auto rp-filters-advanced-grid">
					<span class="rp-filter-field">
						<label for="service-date-filter"><?php esc_html_e( 'Service date', 'restropress' ); ?></label>
						<input type="text" id="service-date-filter" name="service-date" class="rp-input rpress_datepicker" value="<?php echo esc_attr( $service_date ); ?>" placeholder="<?php echo esc_attr( $date_placeholder ); ?>" autocomplete="off" />
					</span>
					<span class="rp-filter-field">
						<label for="rpress-payment-status-filter"><?php esc_html_e( 'Payment', 'restropress' ); ?></label>
						<select id="rpress-payment-status-filter" name="status" class="rp-select rp-filter-autosubmit">
							<option value="" <?php selected( $payment_status, '' ); ?>><?php esc_html_e( 'All payments', 'restropress' ); ?></option>
							<?php foreach ( $payment_statuses as $slug => $label ) :
								if ( $slug === 'all' ) { continue; }
							?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $payment_status, $slug ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</span>
					<span class="rp-filter-field" id="rpress-payment-gateway-filter">
						<label for="gateway"><?php esc_html_e( 'Gateway', 'restropress' ); ?></label>
						<?php
						if ( ! empty( $gateways ) ) {
							echo wp_kses(
								RPRESS()->html->select( array(
									'options'          => $gateways,
									'name'             => 'gateway',
									'id'               => 'gateway',
									'selected'         => esc_attr( $selected_gateway ),
									'show_option_all'  => false,
									'show_option_none' => false,
								) ),
								array(
									'select' => array( 'name' => array(), 'id' => array(), 'class' => array() ),
									'option' => array( 'value' => array(), 'selected' => array() ),
								)
							);
						}
						?>
					</span>
				</div>

				<?php do_action( 'rpress_payment_advanced_filters_after_fields' ); ?>

				<div class="rp-filters-advanced-actions">
					<input type="submit" class="button button-primary rp-btn rp-btn-primary" value="<?php esc_attr_e( 'Apply filters', 'restropress' ); ?>" />
					<?php if ( ! empty( $active_chips ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-payment-history' ) ); ?>" class="button button-secondary rp-btn rp-btn-secondary">
							<?php esc_html_e( 'Reset', 'restropress' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php do_action( 'rpress_payment_advanced_filters_row' ); ?>
		</div>
		<?php
	}

	/**
	 * Keep the extra tablenav slot empty so WP_List_Table can render its
	 * default bulk actions and pagination controls without custom clutter.
	 *
	 * @since 3.3
	 * @param string $which 'top' | 'bottom'
	 */
	protected function extra_tablenav( $which ) {
		return;
	}

	/**
	 * Use the native WP_List_Table pagination/count UI. This keeps the
	 * back-office Orders table familiar to WordPress/WooCommerce users.
	 *
	 * @since 3.3
	 * @param string $which 'top' | 'bottom'
	 */
	protected function pagination( $which ) {
		parent::pagination( $which );
	}

	/**
	 * Compute the set of order IDs that fall into the "Needs Attention"
	 * bucket: new (pending order_status) OR late OR payment pending.
	 *
	 * Walks the same ±1-day window as get_urgent_order_ids() so the cache
	 * key surface stays small.
	 *
	 * @since 3.3
	 * @return int[]
	 */
	public static function get_needs_attention_ids( $start_ymd = null, $end_ymd = null, $all_time = false ) {
		$cache_key = 'rpress_orders_needs_attention_' . md5( (string) $start_ymd . '|' . (string) $end_ymd . '|' . ( $all_time ? 'all' : 'range' ) );
		$cached    = wp_cache_get( $cache_key, 'rpress' );
		if ( false !== $cached ) {
			return $cached;
		}

		$now  = current_time( 'timestamp' );
		$args = array(
			'post_type'              => 'rpress_payment',
			'post_status'            => array( 'publish', 'pending', 'processing' ),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);
		if ( ! $all_time ) {
			$args['date_query'] = array(
				array(
					'after'     => ( $start_ymd ? $start_ymd : date_i18n( 'Y-m-d', strtotime( '-1 day' ) ) ) . ' 00:00:00',
					'before'    => ( $end_ymd ? $end_ymd : date_i18n( 'Y-m-d', strtotime( '+1 day' ) ) ) . ' 23:59:59',
					'inclusive' => true,
				),
			);
		}

		$q   = new WP_Query( $args );
		$ids = $q->posts;

		$done    = array( 'completed', 'transit', 'cancelled', 'refunded', 'failed' );
		$matched = array();
		foreach ( $ids as $id ) {
			$order_status = rpress_get_order_status( $id );
			if ( in_array( $order_status, $done, true ) ) {
				continue;
			}
			$post_status  = get_post_status( $id );
			$is_new       = ( $order_status === 'pending' );
			$is_pay_pend  = ( $post_status  === 'pending' );
			$is_late      = false;
			$svc_ts       = self::parse_service_timestamp( $id );
			if ( $svc_ts && $svc_ts <= $now ) {
				$is_late = true;
			}
			if ( $is_new || $is_pay_pend || $is_late ) {
				$matched[] = (int) $id;
			}
		}

		wp_cache_set( $cache_key, $matched, 'rpress', 60 );
		return $matched;
	}

	/**
	 * Bucket today's + yesterday's + tomorrow's active orders into
	 * "Due Soon" (service time within the configured window) and "Late" (service
	 * time already passed but order not yet completed/cancelled/refunded).
	 *
	 * Returns an array `{ due_soon: int[], late: int[] }` of order IDs.
	 * Cached for 60 seconds because urgency state changes continuously.
	 *
	 * @since 3.3
	 * @return array
	 */
	public static function get_urgent_order_ids() {
		$cache_key = 'rpress_orders_urgent';
		$cached    = wp_cache_get( $cache_key, 'rpress' );
		if ( false !== $cached ) {
			return $cached;
		}

		$now = current_time( 'timestamp' );

		$args = array(
			'post_type'      => 'rpress_payment',
			'post_status'    => array( 'publish', 'pending', 'processing' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'after'  => date_i18n( 'Y-m-d 00:00:00', strtotime( '-1 day' ) ),
					'before' => date_i18n( 'Y-m-d 23:59:59', strtotime( '+1 day' ) ),
					'inclusive' => true,
				),
			),
			'no_found_rows'  => true,
			'update_post_term_cache' => false,
		);

		$q   = new WP_Query( $args );
		$ids = $q->posts;

		$due_soon = array();
		$late     = array();

		foreach ( $ids as $id ) {
			$order_status = rpress_get_order_status( $id );
			// Orders that are already done shouldn't be flagged urgent.
			if ( in_array( $order_status, array( 'completed', 'transit', 'cancelled', 'refunded', 'failed' ), true ) ) {
				continue;
			}
			// ASAP orders carry a working promise (created + prep window) from
			// parse_service_timestamp, so they bucket by time like any other
			// order instead of sitting in Due Soon forever.
			$service_ts = self::parse_service_timestamp( $id );
			if ( ! $service_ts ) {
				continue;
			}
			$delta_minutes = ( $service_ts - $now ) / 60;
			if ( $delta_minutes <= 0 ) {
				$late[] = $id;
			} elseif ( $delta_minutes <= self::get_due_soon_window_minutes() ) {
				$due_soon[] = $id;
			}
		}

		$result = array(
			'due_soon' => $due_soon,
			'late'     => $late,
		);
		wp_cache_set( $cache_key, $result, 'rpress', 60 );
		return $result;
	}

	/**
	 * Due Soon threshold for operational labels and urgency filtering.
	 *
	 * @since 3.3
	 * @return int
	 */
	protected static function get_due_soon_window_minutes() {
		return max( 1, (int) apply_filters( 'rpress_orders_due_soon_window_minutes', 60 ) );
	}

	/**
	 * Whether a gateway is cash-like (cash due on handoff), using the single
	 * shared list so this screen, the dashboard, and reports never disagree on
	 * which gateways count as cash - including the bundled cash_on_delivery.
	 *
	 * @since 3.3
	 * @param string $gateway Gateway slug.
	 * @return bool
	 */
	protected static function is_cash_gateway( $gateway ) {
		if ( class_exists( 'RPRESS_Command_Center_Dashboard' ) && method_exists( 'RPRESS_Command_Center_Dashboard', 'cash_gateways' ) ) {
			$cash = RPRESS_Command_Center_Dashboard::cash_gateways();
		} else {
			$cash = apply_filters( 'rpress_command_center_cash_gateways', array( 'cash_on_delivery', 'cash', 'cod', 'manual' ) );
		}
		return in_array( $gateway, $cash, true );
	}

	/**
	 * Working prep window for ASAP orders, in minutes. An ASAP order's promise
	 * is its creation time plus this window, so it ages into "late" instead of
	 * reading "due now" forever. Shares the command center threshold so the
	 * dashboard and orders screen always agree on when an ASAP order is late.
	 *
	 * @since 3.3
	 * @return int
	 */
	protected static function get_asap_prep_minutes() {
		if ( class_exists( 'RPRESS_Command_Center_Dashboard' ) ) {
			$thresholds = RPRESS_Command_Center_Dashboard::thresholds();
			if ( isset( $thresholds['asap_prep_minutes'] ) ) {
				return max( 1, (int) $thresholds['asap_prep_minutes'] );
			}
		}
		return 30;
	}

	/**
	 * Whether the order service time is marked ASAP.
	 *
	 * @since 3.3
	 * @param int $payment_id Payment/order ID.
	 * @return bool
	 */
	protected static function is_asap_service_time( $payment_id ) {
		$time_str = get_post_meta( $payment_id, '_rpress_delivery_time', true );
		return is_string( $time_str ) && stripos( $time_str, 'ASAP' ) !== false;
	}

	/**
	 * Parse an order's service date + time into a UNIX timestamp.
	 *
	 * Service date is stored as a free-form string in `_rpress_delivery_date`
	 * and time in `_rpress_delivery_time` (which may include "ASAP" or a
	 * range like "12:30 PM - 12:45 PM"). Best-effort parse: returns null on
	 * any failure.
	 *
	 * @since 3.3
	 * @param int $payment_id
	 * @return int|null
	 */
	protected static function parse_service_timestamp( $payment_id ) {
		$date_str = get_post_meta( $payment_id, '_rpress_delivery_date', true );
		$time_str = get_post_meta( $payment_id, '_rpress_delivery_time', true );
		if ( empty( $date_str ) ) {
			return null;
		}
		// Strip "ASAP" markers; when no concrete time remains, the promise is
		// creation time plus the prep window so the order ages into "late"
		// naturally instead of reading as due-now forever.
		if ( is_string( $time_str ) && stripos( $time_str, 'ASAP' ) !== false ) {
			$time_str = trim( str_ireplace( 'ASAP', '', $time_str ) );
		}
		// Take the start of any "12:30 PM - 12:45 PM" range.
		$time_parts = preg_split( '/\s*[\-–]\s*/', (string) $time_str );
		$time       = trim( $time_parts[0] ?? '' );
		if ( $time === '' ) {
			return get_post_time( 'U', false, $payment_id ) + self::get_asap_prep_minutes() * MINUTE_IN_SECONDS;
		}
		$combined = trim( $date_str ) . ' ' . $time;
		$ts       = strtotime( $combined );
		return $ts ? (int) $ts : null;
	}

	/**
	 * Service types available to filter by, accounting for the
	 * `enable_service` setting and the optional Dine-in extension.
	 *
	 * @since 3.3
	 * @return array<string, string>  slug => label
	 */
	protected function get_available_service_types() {
		$enabled = rpress_get_option( 'enable_service' );
		if ( empty( $enabled ) ) {
			$enabled = 'delivery_and_pickup';
		}
		$types = array();
		if ( $enabled === 'delivery_and_pickup' || $enabled === 'delivery' ) {
			$types['delivery'] = __( 'Delivery', 'restropress' );
		}
		if ( $enabled === 'delivery_and_pickup' || $enabled === 'pickup' ) {
			$types['pickup'] = __( 'Pickup', 'restropress' );
		}
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'restropress-dinein/restropress-dinein.php' ) ) {
			$types['dinein'] = __( 'Dine-in', 'restropress' );
		}
		return apply_filters( 'rpress_orders_available_service_types', $types );
	}

	/**
	 * Build a payment-history URL preserving current $_GET params with optional
	 * additions/removals. Used by the date shortcut chips and active-filter
	 * remove links so we never lose the user's other filter state when they
	 * click a single chip.
	 *
	 * @since 3.3
	 * @param array $set    Params to set / overwrite (string keys -> string values, or false to drop).
	 * @param array $remove Param keys to remove.
	 * @return string
	 */
	protected function build_filter_url( array $set = array(), array $remove = array() ) {
		$params = array();
		$preserved_keys = array( 'page', 'status', 'service-type', 'gateway', 'order_status', 'start-date', 'end-date', 'date-range', 'service-date', 's', 'orderby', 'order', 'paged', 'view', 'mode', 'urgency' );
		foreach ( $preserved_keys as $key ) {
			if ( isset( $_GET[ $key ] ) && $_GET[ $key ] !== '' && ! in_array( $key, $remove, true ) ) {
				$params[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		foreach ( $set as $key => $value ) {
			if ( $value === false || $value === '' || $value === null ) {
				unset( $params[ $key ] );
			} else {
				$params[ $key ] = $value;
			}
		}
		// Always ensure the page param is correct.
		$params['page'] = 'rpress-payment-history';
		return admin_url( 'admin.php?' . http_build_query( $params ) );
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
		// Operational segmented tabs are hidden in past mode - order-status
		// filtering moves to a Status dropdown in the filter row instead.
		if ( self::is_past_mode() ) {
			return array();
		}

		// A search spans all statuses and all dates, so the today-scoped tab
		// counts (e.g. "All 11") no longer describe the result set. Hide the
		// tabs while searching so their stale counts can't mislead.
		if ( isset( $_GET['s'] ) && '' !== trim( (string) wp_unslash( $_GET['s'] ) ) ) {
			return array();
		}

		// Order-status segmented tabs (replaces the legacy payment-status tabs).
		// Trash is a special case keyed on post_status (so trashed orders are
		// always reachable, regardless of order_status meta).

		$current_order_status = isset( $_GET['order_status'] ) ? sanitize_text_field( wp_unslash( $_GET['order_status'] ) ) : '';
		$current_post_status  = isset( $_GET['status'] )       ? sanitize_text_field( wp_unslash( $_GET['status'] ) )       : '';
		$is_trash_view        = ( $current_post_status === 'trash' );

		$counts = $this->get_order_status_counts();

		$tabs = array(
			'all'             => array(
				'label'  => __( 'All', 'restropress' ),
				'count'  => isset( $counts['all'] ) ? $counts['all'] : 0,
				'url'    => esc_url( remove_query_arg( array( 'order_status', 'status', 'paged' ) ) ),
				'active' => ( ! $is_trash_view && ( $current_order_status === '' || $current_order_status === 'all' ) ),
			),
			'needs_attention' => array(
				'label'  => __( 'Needs Attention', 'restropress' ),
				'count'  => isset( $counts['needs_attention'] ) ? $counts['needs_attention'] : 0,
				'url'    => esc_url( add_query_arg( array( 'order_status' => 'needs_attention', 'paged' => false ), remove_query_arg( 'status' ) ) ),
				'active' => ( ! $is_trash_view && $current_order_status === 'needs_attention' ),
			),
			'new'       => array(
				'label'    => __( 'New', 'restropress' ),
				'count'    => isset( $counts['new'] ) ? $counts['new'] : 0,
				'url'      => esc_url( add_query_arg( array( 'order_status' => 'pending', 'paged' => false ), remove_query_arg( 'status' ) ) ),
				'active'   => ( ! $is_trash_view && $current_order_status === 'pending' ),
			),
			'preparing' => array(
				'label'    => __( 'Preparing', 'restropress' ),
				'count'    => isset( $counts['preparing'] ) ? $counts['preparing'] : 0,
				'url'      => esc_url( add_query_arg( array( 'order_status' => 'accepted,processing', 'paged' => false ), remove_query_arg( 'status' ) ) ),
				'active'   => ( ! $is_trash_view && in_array( $current_order_status, array( 'accepted', 'processing', 'accepted,processing' ), true ) ),
			),
			'ready'     => array(
				'label'    => __( 'Ready', 'restropress' ),
				'count'    => isset( $counts['ready'] ) ? $counts['ready'] : 0,
				'url'      => esc_url( add_query_arg( array( 'order_status' => 'ready', 'paged' => false ), remove_query_arg( 'status' ) ) ),
				'active'   => ( ! $is_trash_view && $current_order_status === 'ready' ),
			),
			'completed' => array(
				'label'    => __( 'Completed', 'restropress' ),
				'count'    => isset( $counts['completed'] ) ? $counts['completed'] : 0,
				'url'      => esc_url( add_query_arg( array( 'order_status' => 'completed,transit', 'paged' => false ), remove_query_arg( 'status' ) ) ),
				'active'   => ( ! $is_trash_view && in_array( $current_order_status, array( 'completed', 'transit', 'completed,transit' ), true ) ),
			),
			'trash'     => array(
				'label'    => __( 'Trash', 'restropress' ),
				'count'    => (int) $this->trash_count,
				'url'      => esc_url( add_query_arg( array( 'status' => 'trash', 'paged' => false ), remove_query_arg( 'order_status' ) ) ),
				'active'   => $is_trash_view,
			),
		);

		$views = array();
		foreach ( $tabs as $key => $tab ) {
			// Hide zero-count Trash unless we're already on the Trash tab.
			if ( $key === 'trash' && (int) $tab['count'] === 0 && ! $tab['active'] ) {
				continue;
			}
			$count_html  = ' <span class="count">' . (int) $tab['count'] . '</span>';
			$views[ $key ] = sprintf(
				'<a href="%s"%s>%s%s</a>',
				$tab['url'],
				$tab['active'] ? ' class="current"' : '',
				esc_html( $tab['label'] ),
				$count_html
			);
		}

		return apply_filters( 'rpress_payments_table_views', $views );
	}

	/**
	 * Compute counts for the order-status segmented tabs.
	 *
	 * The "Preparing" tab covers accepted+processing; "Completed" covers
	 * completed+transit (in-transit deliveries are treated as completed
	 * from a fulfilment-tracking standpoint).
	 *
	 * @since 3.3
	 * @return array<string, int>
	 */
	protected function get_order_status_counts() {
		$start_date = isset( $_GET['start-date'] ) ? sanitize_text_field( wp_unslash( $_GET['start-date'] ) ) : '';
		$end_date   = isset( $_GET['end-date'] )   ? sanitize_text_field( wp_unslash( $_GET['end-date'] ) )   : '';
		if ( '' === $start_date && '' === $end_date ) {
			$start_date = self::format_filter_date();
			$end_date   = self::format_filter_date();
		}
		if ( '' === $end_date ) {
			$end_date = $start_date;
		}

		$cache_key = 'rpress_orders_tab_counts_' . md5( $start_date . '|' . $end_date );
		$cached    = wp_cache_get( $cache_key, 'rpress' );
		if ( false !== $cached ) {
			return $cached;
		}

		// Same population as the table query and the day KPIs (every
		// non-trash order), so the All tab, "N items" count, and Today's
		// Orders card always agree.
		$args = array(
			'post_type'      => 'rpress_payment',
			'post_status'    => array( 'publish', 'pending', 'processing', 'refunded', 'failed' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'update_post_term_cache' => false,
		);
		$start_ymd = '';
		$end_ymd   = '';
		if ( '' !== $start_date || '' !== $end_date ) {
			$start_ymd = self::normalize_filter_date_for_query( $start_date );
			$end_ymd   = self::normalize_filter_date_for_query( $end_date );
			if ( $start_ymd || $end_ymd ) {
				$args['date_query'] = array(
					array(
						'after'     => $start_ymd ? $start_ymd . ' 00:00:00' : '',
						'before'    => $end_ymd ? $end_ymd . ' 23:59:59' : '',
						'inclusive' => true,
					),
				);
			}
		}
		$q   = new WP_Query( $args );
		$ids = $q->posts;

		$buckets = array(
			'all'             => 0,
			'needs_attention' => 0,
			'new'             => 0,
			'preparing'       => 0,
			'ready'           => 0,
			'completed'       => 0,
		);

		// Needs Attention is a compound bucket - let the dedicated helper own
		// the logic so we don't drift between count and filter.
		$buckets['needs_attention'] = count( array_intersect( array_map( 'intval', $ids ), self::get_needs_attention_ids( $start_ymd ?? null, $end_ymd ?? null, self::is_all_time_mode() ) ) );

		foreach ( $ids as $id ) {
			$status = rpress_get_order_status( $id );
			$buckets['all']++;
			if ( $status === 'pending' ) {
				$buckets['new']++;
			} elseif ( in_array( $status, array( 'accepted', 'processing' ), true ) ) {
				$buckets['preparing']++;
			} elseif ( $status === 'ready' ) {
				$buckets['ready']++;
			} elseif ( in_array( $status, array( 'completed', 'transit' ), true ) ) {
				$buckets['completed']++;
			}
		}

		wp_cache_set( $cache_key, $buckets, 'rpress', 60 );
		return $buckets;
	}
  
  	
	/**
	 * Retrieve the table columns
	 *
	 * @since  1.0.0
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		$due_label = self::is_past_mode()
			? esc_html__( 'Date & Time', 'restropress' )
			: esc_html__( 'Due', 'restropress' );

		$columns = array(
			'cb'           => '<input type="checkbox" aria-label="' . esc_attr__( 'Select all orders', 'restropress' ) . '" />',
			'ID'           => esc_html__( 'Order', 'restropress' ),
			'items'        => esc_html__( 'Items', 'restropress' ),
			'due_time'     => $due_label,
			'payment'      => esc_html__( 'Payment', 'restropress' ),
			'order_status' => esc_html__( 'Status', 'restropress' ),
			'amount'       => esc_html__( 'Total', 'restropress' ),
			'actions'      => esc_html__( 'Actions', 'restropress' ),
		);
		$columns = apply_filters( 'rpress_payments_table_columns', $columns );
		// The receipt-print feature appends its own "Print" column; we now fold
		// the print affordance into the Actions cell, so the standalone column
		// is redundant. The print button itself still renders inside Actions.
		unset( $columns['print'] );
		return $columns;
	}

	/**
	 * Add the reusable RestroPress table class while keeping WP_List_Table classes.
	 *
	 * @since 3.3
	 * @return array
	 */
	protected function get_table_classes() {
		$classes   = parent::get_table_classes();
		$classes[] = 'rp-table';

		return array_unique( $classes );
	}
	/**
	 * Retrieve the table's sortable columns
	 *
	 * @since  1.0.0
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns() {
		// Only columns that actually render are sortable. 'date' was declared
		// here but no Date column exists in this table, so it was dead config.
		$columns = array(
			'ID'     => array( 'ID', true ),
			'amount' => array( 'amount', false ),
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
	 * Message shown when the current filter set has no matching orders.
	 *
	 * @since 3.3
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No orders found for the selected filters.', 'restropress' );
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
				$amount      = $payment->total;
				$amount      = ! empty( $amount ) ? $amount : 0;
				$is_refunded = ( $payment->post_status === 'refunded' );
				$formatted   = rpress_currency_filter( rpress_format_amount( $amount ), rpress_get_payment_currency_code( $payment->ID ) );
				if ( $is_refunded ) {
					// Prefix with a minus to reinforce the refund nature visually.
					$formatted = '-' . $formatted;
				}
				$cls = $is_refunded ? 'rp-order-total is-refunded' : 'rp-order-total';
				$value = '<span class="' . esc_attr( $cls ) . '">' . $formatted . '</span>';
			break;
			case 'type' :
				$value = self::render_type_cell( $payment );
			break;
			case 'items' :
				$value = self::render_items_cell( $payment );
			break;
			case 'due_time' :
				$value = self::render_due_time_cell( $payment );
			break;
			case 'payment' :
				$value = self::render_payment_cell( $payment );
			break;
			case 'actions' :
				$value = self::render_actions_cell( $payment );
			break;
			case 'order_status' :
				// Order status - native select styled as an operational pill.
				$order_statuses       = rpress_get_order_statuses();
				$current_order_status = rpress_get_order_status( $payment->ID );
				$current_status_label = isset( $order_statuses[ $current_order_status ] )
					? (string) $order_statuses[ $current_order_status ]
					: ucwords( str_replace( array( '-', '_' ), ' ', $current_order_status ) );
				$is_disabled = ( $payment->post_status === 'trash' );

				// Statuses outside the operational set (refunded, failed, or
				// anything custom) have no <option>, so a select would silently
				// display the first option instead. Render a locked label pill.
				if ( ! isset( $order_statuses[ $current_order_status ] ) ) {
					$value  = '<div class="rp-order-status-cell">';
					$value .= '<span class="rp-status-locked status-' . esc_attr( sanitize_html_class( $current_order_status ) ) . '">' . esc_html( $current_status_label ) . '</span>';
					if ( self::is_past_mode() ) {
						$updated_at = (int) get_post_meta( $payment->ID, '_rpress_order_status_updated_at', true );
						if ( ! $updated_at ) {
							$updated_at = strtotime( $payment->date );
						}
						if ( $updated_at ) {
							$value .= '<div class="rp-status-timestamp">' . esc_html( date_i18n( 'M j, h:i A', $updated_at ) ) . '</div>';
						}
					}
					$value .= '</div>';
					break;
				}

				$pill  = '<div class="rp-order-status-cell">';
				$pill .= '<div class="rp-status-select-wrap status-' . esc_attr( sanitize_html_class( $current_order_status ) ) . '">';
				$pill .= '<label class="screen-reader-text" for="rpress-order-status-' . absint( $payment->ID ) . '">' . esc_html__( 'Order status', 'restropress' ) . '</label>';
				$pill .= sprintf(
					'<select id="rpress-order-status-%1$d" class="rp-status-select rp_order_status status-%2$s" data-payment-id="%1$d" data-current-status="%3$s"%4$s>',
					(int) $payment->ID,
					esc_attr( sanitize_html_class( $current_order_status ) ),
					esc_attr( $current_order_status ),
					$is_disabled ? ' disabled="disabled"' : ''
				);
				foreach ( $order_statuses as $status_id => $status_label ) {
					$pill .= sprintf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $status_id ),
						selected( $current_order_status, $status_id, false ),
						esc_html( $status_label )
					);
				}
				$pill .= '</select>';
				$pill .= '<span class="order-status-loading"></span>';
				$pill .= '</div>';

				// Past mode: append a small timestamp under the pill showing
				// when the status was last set (falls back to order date).
				if ( self::is_past_mode() ) {
					$updated_at = (int) get_post_meta( $payment->ID, '_rpress_order_status_updated_at', true );
					if ( ! $updated_at ) {
						$updated_at = strtotime( $payment->date );
					}
					if ( $updated_at ) {
						$pill .= '<div class="rp-status-timestamp">'
							. esc_html( date_i18n( 'M j, h:i A', $updated_at ) )
							. '</div>';
					}
				}
				$pill .= '</div>';

				$value = $pill;
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
			$row_actions['email_links'] = '<a href="' . esc_url( add_query_arg( array( 'rpress-action' => 'email_links', 'purchase_id' => $payment->ID ), $this->base_url ) ) . '">' . __( 'Resend Purchase Receipt', 'restropress' ) . '</a>';
		}
		$row_actions['delete'] = '<a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'rpress-action' => 'delete_payment', 'purchase_id' => $payment->ID ), $this->base_url ), 'rpress_payment_nonce' ) ) . '">' . __( 'Delete', 'restropress' ) . '</a>';
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
			'<input type="checkbox" name="%1$s[]" value="%2$s" aria-label="%3$s" />',
			'payment',
			$payment->ID,
			esc_attr( sprintf(
				/* translators: %s: order number */
				__( 'Select order %s', 'restropress' ),
				'#' . rpress_get_payment_number( $payment->ID )
			) )
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
		// Row actions (Trash / Restore / Delete) - same behaviour as before, just collected first.
		$row_actions = array();
		if ( rpress_is_order_trashable( $payment->ID ) ) {
			$trash_url = wp_nonce_url( add_query_arg( array(
				'rpress-action' => 'trash_order',
				'purchase_id'   => absint( $payment->ID ),
			), $this->base_url ), 'rpress_payment_nonce' );
			$row_actions['trash'] = '<a href="' . esc_url( $trash_url ) . '">' . esc_html__( 'Trash', 'restropress' ) . '</a>';
		} elseif ( rpress_is_order_restorable( $payment->ID ) ) {
			$restore_url = wp_nonce_url( add_query_arg( array(
				'rpress-action' => 'restore_order',
				'purchase_id'   => absint( $payment->ID ),
			), $this->base_url ), 'rpress_payment_nonce' );
			$row_actions['restore'] = '<a href="' . esc_url( $restore_url ) . '">' . esc_html__( 'Restore', 'restropress' ) . '</a>';
			$delete_url = wp_nonce_url( add_query_arg( array(
				'rpress-action' => 'delete_order',
				'purchase_id'   => absint( $payment->ID ),
			), $this->base_url ), 'rpress_payment_nonce' );
			$row_actions['delete'] = '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete Permanently', 'restropress' ) . '</a>';
		}
		if ( has_filter( 'rpress_payment_row_actions' ) ) {
			$payment_obj = rpress_get_payment( $payment->ID );
			$row_actions = apply_filters_deprecated( 'rpress_payment_row_actions', array( $row_actions, $payment_obj ), '3.0', 'rpress_order_row_actions' );
		}
		$row_actions = apply_filters( 'rpress_order_row_actions', $row_actions, $payment );

		// Customer name resolution (id → customer → user_info fallback).
		$customer_id = rpress_get_payment_customer_id( $payment->ID );
		$cust_name   = '';
		if ( ! empty( $customer_id ) ) {
			$customer  = new RPRESS_Customer( $customer_id );
			$cust_name = $customer->name;
		}
		$payment_meta  = $payment->get_meta();
		$customer_name = ( ! empty( $payment_meta['user_info'] ) && is_array( $payment_meta['user_info'] ) )
			? trim( ( $payment_meta['user_info']['first_name'] ?? '' ) . ' ' . ( $payment_meta['user_info']['last_name'] ?? '' ) )
			: $cust_name;
		if ( $customer_name === '' ) {
			$customer_name = __( 'Guest', 'restropress' );
		}

		// Customer phone (gated by setting: full / last4 / hidden).
		$phone_display = '';
		$phone_mode    = rpress_get_option( 'orders_show_phone', 'full' );
		if ( $phone_mode !== 'hidden' ) {
			$phone = ! empty( $payment_meta['phone'] ) ? $payment_meta['phone']
				: ( ! empty( $payment_meta['user_info']['phone'] ) ? $payment_meta['user_info']['phone'] : '' );
			if ( $phone ) {
				$phone_display = ( $phone_mode === 'last4' )
					? '••••' . substr( preg_replace( '/[^0-9]/', '', $phone ), -4 )
					: $phone;
			}
		}

		// Order number with optional sequential prefix/postfix.
		$order_number = '#' . rpress_get_payment_number( $payment->ID );
		$order_number = apply_filters( 'rpress_orders_table_order_number', $order_number, $payment );

		$details_url = add_query_arg( 'id', $payment->ID, admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details' ) );
		$order_date  = rpress_get_payment_gmt_timestamp( $payment );

		// State dot (left of order number) - colour matches the order status.
		$order_status = rpress_get_order_status( $payment->ID );
		$state_class  = 'rp-order-state-dot status-' . sanitize_html_class( $order_status );

		$out  = '<div class="rp-order-cell">';
		$out .= '<div class="rp-order-cell-row">';
		$out .= '<span class="' . esc_attr( $state_class ) . '" aria-hidden="true"></span>';
		$out .= '<a class="rp-order-number" href="' . esc_url( $details_url ) . '">' . esc_html( $order_number ) . '</a>';
		$out .= '<a href="#" class="rp-order-inline-preview order-preview" data-order-id="' . absint( $payment->ID ) . '" title="' . esc_attr__( 'Quick view order', 'restropress' ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Quick view order', 'restropress' ) . '</span></a>';
		$out .= '</div>';
		$out .= '<a class="rp-order-info-link" href="' . esc_url( $details_url ) . '">';
		$out .= '<div class="rp-order-cell-customer"><span class="rp-order-customer-name">' . esc_html( $customer_name ) . '</span>' . self::render_order_service_badge( $payment ) . '</div>';
		if ( $phone_display ) {
			$out .= '<div class="rp-order-cell-phone">' . esc_html( $phone_display ) . '</div>';
		}
		$out .= '<div class="rp-order-cell-meta"><span class="rp-time-since" data-timestamp="' . esc_attr( $order_date ) . '"></span></div>';
		$out .= '</a>';
		$out .= '</div>';

		return $out;
	}

	/**
	 * Compact service badge shown inside the Order column.
	 *
	 * @since 3.3
	 * @param RPRESS_Payment $payment Payment object.
	 * @return string
	 */
	protected static function render_order_service_badge( $payment ) {
		$service_type  = rpress_get_service_type( $payment->ID );
		$service_type  = $service_type ? sanitize_key( $service_type ) : 'delivery';
		$service_label = rpress_service_label( $service_type );

		if ( 'dinein' === $service_type || 'dine-in' === $service_type ) {
			$service_type  = 'dinein';
			$service_label = __( 'Dine-in', 'restropress' );
		}

		return sprintf(
			'<span class="rp-type-cell rp-order-service-badge badge-%s">%s<span class="rp-type-cell-label">%s</span></span>',
			esc_attr( $service_type ),
			self::get_service_type_icon( $service_type ),
			esc_html( $service_label )
		);
	}

	/* ────────────────────────────────────────────────────────────────
	   Redesign Phase 5 - per-column renderers for the new columns.
	   ──────────────────────────────────────────────────────────────── */

	/**
	 * Type column: service-type icon + badge.
	 */
	protected static function render_type_cell( $payment ) {
		$service_type  = rpress_get_service_type( $payment->ID );
		$service_type  = $service_type ? sanitize_key( $service_type ) : 'delivery';
		$service_label = rpress_service_label( $service_type );

		if ( 'dinein' === $service_type || 'dine-in' === $service_type ) {
			$service_type  = 'dinein';
			$service_label = __( 'Dine-in', 'restropress' );
		}

		// Service-context sub-line (delivery address / "At counter" / table no.).
		$context_line = '';
		if ( $service_type === 'delivery' ) {
			$addr = get_post_meta( $payment->ID, '_rpress_delivery_address', true );
			if ( is_array( $addr ) ) {
				$bits = array();
				if ( ! empty( $addr['address'] ) ) {
					$street = (string) $addr['address'];
					$bits[] = $street;
					if ( ! empty( $addr['postcode'] ) && ! preg_match( '/\b\d{5,6}\b/', $street ) ) { $bits[] = $addr['postcode']; }
				} else {
					if ( ! empty( $addr['city'] ) )     { $bits[] = $addr['city']; }
					if ( ! empty( $addr['postcode'] ) ) { $bits[] = $addr['postcode']; }
				}
				if ( $bits ) {
					$context_line = implode( ', ', $bits );
				}
			}
		} elseif ( $service_type === 'pickup' ) {
			$context_line = __( 'At counter', 'restropress' );
		} elseif ( $service_type === 'dinein' ) {
			$table = get_post_meta( $payment->ID, '_rpress_dinein_table', true );
			if ( ! $table ) {
				$table = get_post_meta( $payment->ID, '_rpress_table_number', true );
			}
			if ( $table ) {
				$table        = preg_replace( '/^table\s+/i', '', (string) $table );
				$context_line = sprintf( __( 'Table %s', 'restropress' ), $table );
			}
		}

		$out  = '<div class="rp-type-cell-wrap">';
		$out .= sprintf(
			'<span class="rp-type-cell badge-%s">%s<span class="rp-type-cell-label">%s</span></span>',
			esc_attr( $service_type ),
			self::get_service_type_icon( $service_type ),
			esc_html( $service_label )
		);
		if ( $context_line ) {
			$out .= '<div class="rp-type-context">' . esc_html( $context_line ) . '</div>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Inline service icons keep the Type column close to the operations mockup
	 * without depending on a separate icon library in wp-admin.
	 *
	 * @since 3.3
	 * @param string $service_type Service type slug.
	 * @return string SVG markup.
	 */
	protected static function get_service_type_icon( $service_type ) {
		$icons = array(
			'delivery' => '<svg class="rp-type-icon rp-type-icon-truck" viewBox="0 0 22 16" aria-hidden="true" focusable="false"><path d="M1.5 3.2h11.1v8.2H8.9a2.5 2.5 0 0 0-4.8 0H1.5V3.2Zm12.1 2.3h3.5l3.4 3.5v2.4h-1.6a2.5 2.5 0 0 0-4.8 0h-.5V5.5Zm1.3 1.3v2.1h3.4l-2-2.1h-1.4ZM6.5 10.7a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm10 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Z" fill="currentColor"/></svg>',
			'pickup'   => '<svg class="rp-type-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4.5 5.8V4.7a3.5 3.5 0 0 1 7 0v1.1h1.3l.7 8.2H2.5l.7-8.2h1.3Zm1.1 0h4.8V4.7a2.4 2.4 0 0 0-4.8 0v1.1Zm-1.4 1-.5 6.1h8.6l-.5-6.1h-1.3v1.5h-1V6.8H5.6v1.5h-1V6.8h-.4Z" fill="currentColor"/></svg>',
			'dinein'   => '<svg class="rp-type-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3.1 1.5h1v5.2h.8V1.5h1v5.2h.8V1.5h1v5.4c0 1.3-.8 2.3-2 2.6v5h-1.4v-5c-1.2-.3-2-1.3-2-2.6V1.5Zm8.9 0c1.3 1 2 2.7 2 4.8 0 1.8-.6 3.2-1.7 3.8v4.4h-1.4V1.5H12Z" fill="currentColor"/></svg>',
		);

		return isset( $icons[ $service_type ] ) ? $icons[ $service_type ] : '<svg class="rp-type-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1.5 14.5 8 8 14.5 1.5 8 8 1.5Zm0 2L3.5 8 8 12.5 12.5 8 8 3.5Z" fill="currentColor"/></svg>';
	}

	/**
	 * Items column: count + truncated names + service-context line
	 * (delivery address for delivery orders, "At counter" for pickup,
	 * table number for dine-in when available).
	 */
	protected static function render_items_cell( $payment ) {
		$cart_details = isset( $payment->cart_details ) && is_array( $payment->cart_details ) ? $payment->cart_details : array();
		if ( empty( $cart_details ) ) {
			return '<span class="rp-items-empty">-</span>';
		}
		$count = 0;
		$names = array();
		foreach ( $cart_details as $row ) {
			$qty   = isset( $row['quantity'] ) ? (int) $row['quantity'] : 1;
			$count += $qty;
			$names[] = isset( $row['name'] ) ? $row['name'] : '';
		}
		$names_shown = array_slice( array_filter( $names ), 0, 3 );
		$has_more    = count( $names ) > 3;
		$names_text  = implode( ', ', $names_shown ) . ( $has_more ? ', …' : '' );
		$count_text  = sprintf(
			/* translators: %d: number of items */
			_n( '%d item', '%d items', $count, 'restropress' ),
			$count
		);

		$out  = '<div class="rp-items-cell">';
		$out .= '<div class="rp-items-count">' . esc_html( $count_text ) . '</div>';
		$out .= '<div class="rp-items-names" title="' . esc_attr( implode( ', ', array_filter( $names ) ) ) . '">' . esc_html( $names_text ) . '</div>';
		$out .= '</div>';
		return $out;
	}

	/**
	 * Due Time column: absolute service time + contextual relative label.
	 *
	 * The relative label is chosen by precedence:
	 *   Out for delivery     - order_status = transit
	 *   Ready for pickup     - order_status = ready
	 *   Due now              - ASAP order, order not done (amber)
	 *   Late by N min        - service time passed, order not done (red)
	 *   Due in N min         - service time within configured due-soon window (amber)
	 *
	 * Absolute date is formatted as "Today, 12:30 PM" when service date
	 * is today, "Tomorrow, 12:30 PM" when tomorrow, otherwise full date.
	 */
	protected static function render_due_time_cell( $payment ) {
		// Past mode: the column is "Date & Time" - show order creation, not
		// service due, and skip the relative-urgency label entirely.
		if ( self::is_past_mode() ) {
			$ts = strtotime( $payment->date );
			if ( ! $ts ) {
				return '<span class="rp-items-empty">-</span>';
			}
			$out  = '<div class="rp-due-cell">';
			$out .= '<div class="rp-due-absolute">' . esc_html( date_i18n( get_option( 'date_format' ), $ts ) ) . '</div>';
			$out .= '<div class="rp-due-relative">' . esc_html( date_i18n( get_option( 'time_format' ), $ts ) ) . '</div>';
			$out .= '</div>';
			return $out;
		}

		$date_str = get_post_meta( $payment->ID, '_rpress_delivery_date', true );
		$time_str = get_post_meta( $payment->ID, '_rpress_delivery_time', true );

		if ( empty( $date_str ) ) {
			return '<span class="rp-items-empty">-</span>';
		}

		$is_asap = ( is_string( $time_str ) && stripos( $time_str, 'ASAP' ) !== false );
		if ( $is_asap ) {
			$time_str = trim( str_ireplace( 'ASAP', '', $time_str ) );
		}
		$time_display = $time_str !== '' ? $time_str : '';
		if ( $is_asap ) {
			$time_display = trim( __( 'ASAP', 'restropress' ) . ' ' . $time_display );
		}

		// "Today, 12:30 PM" / "Tomorrow, 12:30 PM" / full date.
		$svc_ts        = strtotime( $date_str );
		$today_ymd     = date_i18n( 'Y-m-d' );
		$tomorrow_ymd  = date_i18n( 'Y-m-d', strtotime( '+1 day' ) );
		$yesterday_ymd = date_i18n( 'Y-m-d', strtotime( '-1 day' ) );
		$svc_ymd       = $svc_ts ? date_i18n( 'Y-m-d', $svc_ts ) : '';

		if ( $svc_ymd === $today_ymd ) {
			$date_label = __( 'Today', 'restropress' );
		} elseif ( $svc_ymd === $tomorrow_ymd ) {
			$date_label = __( 'Tomorrow', 'restropress' );
		} elseif ( $svc_ymd === $yesterday_ymd ) {
			$date_label = __( 'Yesterday', 'restropress' );
		} else {
			$date_label = rpress_local_date( $date_str );
		}

		$absolute = $time_display !== '' ? $date_label . ', ' . $time_display : $date_label;

		// Status + service-time-driven relative label.
		$order_status = rpress_get_order_status( $payment->ID );
		$service_ts   = self::parse_service_timestamp( $payment->ID );
		$now          = current_time( 'timestamp' );
		$delta_min    = $service_ts ? ( $service_ts - $now ) / 60 : null;
		$is_done      = in_array( $order_status, array( 'completed', 'cancelled', 'trash', 'refunded', 'failed' ), true )
			|| in_array( $payment->post_status, array( 'refunded', 'failed', 'trash' ), true );

		$relative_class = '';
		$relative_label = '';

		if ( $order_status === 'transit' ) {
			$relative_label = __( 'Out for delivery', 'restropress' );
			$relative_class = 'is-info';
		} elseif ( $order_status === 'ready' ) {
			$relative_label = __( 'Ready for pickup', 'restropress' );
			$relative_class = 'is-success';
		} elseif ( null !== $delta_min && $delta_min < 0 && ! $is_done ) {
			$late_min = abs( (int) $delta_min );
			if ( $late_min >= DAY_IN_SECONDS / MINUTE_IN_SECONDS ) {
				$late_days = max( 1, (int) floor( $late_min / ( DAY_IN_SECONDS / MINUTE_IN_SECONDS ) ) );
				$relative_label = sprintf(
					/* translators: %d: number of days the order is late by */
					_n( 'Late by %d day', 'Late by %d days', $late_days, 'restropress' ),
					$late_days
				);
			} elseif ( $late_min >= HOUR_IN_SECONDS / MINUTE_IN_SECONDS ) {
				$late_hours = max( 1, (int) floor( $late_min / ( HOUR_IN_SECONDS / MINUTE_IN_SECONDS ) ) );
				$relative_label = sprintf(
					/* translators: %d: number of hours the order is late by */
					_n( 'Late by %d hr', 'Late by %d hrs', $late_hours, 'restropress' ),
					$late_hours
				);
			} else {
				$relative_label = sprintf(
					/* translators: %d: minutes the order is late by */
					__( 'Late by %d min', 'restropress' ),
					max( 1, $late_min )
				);
			}
			$relative_class = 'is-late';
		} elseif ( null !== $delta_min && $delta_min >= 0 && $delta_min <= self::get_due_soon_window_minutes() && ! $is_done ) {
			$relative_label = sprintf(
				/* translators: %d: minutes until service time */
				__( 'Due in %d min', 'restropress' ),
				max( 1, (int) ceil( $delta_min ) )
			);
			$relative_class = 'is-due-soon';
		}

		$out  = '<div class="rp-due-cell">';
		$out .= '<div class="rp-due-absolute">' . esc_html( $absolute ) . '</div>';
		if ( $relative_label ) {
			$out .= '<div class="rp-due-relative ' . esc_attr( $relative_class ) . '">' . esc_html( $relative_label ) . '</div>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Payment column: payment-status pill + gateway sub-detail.
	 */
	protected static function render_payment_cell( $payment ) {
		$post_status = ( $payment->post_status === 'trash' ) ? 'trash' : $payment->post_status;
		$pill_label  = ( $post_status === 'trash' )
			? esc_html__( 'Trash', 'restropress' )
			: rpress_get_payment_status_label( $post_status );
		$gateway = isset( $payment->gateway ) ? $payment->gateway : '';
		$gateway_label = $gateway ? rpress_get_gateway_admin_label( $gateway ) : '';
		if ( $gateway_label ) {
			$gateway_label = self::format_payment_gateway_label( $gateway_label );
		}
		$status_icon = self::get_payment_status_icon( $post_status );
		$out  = '<div class="rp-payment-cell">';
		$out .= sprintf(
			'<span class="rp-payment-pill status-%s">%s<span class="rp-payment-pill-label">%s</span></span>',
			esc_attr( $post_status ),
			$status_icon,
			esc_html( $pill_label )
		);
		if ( $gateway_label ) {
			$out .= '<div class="rp-payment-gateway">' . esc_html( $gateway_label ) . '</div>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Compact payment status icons for the Orders table.
	 *
	 * @since 3.3
	 * @param string $status Payment post status.
	 * @return string SVG markup.
	 */
	protected static function get_payment_status_icon( $status ) {
		$icons = array(
			'publish'    => '<svg class="rp-payment-pill-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1.6a6.4 6.4 0 1 1 0 12.8A6.4 6.4 0 0 1 8 1.6Zm3.1 4.5-.9-.9-3 3-1.4-1.4-.9.9 2.3 2.3 3.9-3.9Z" fill="currentColor"/></svg>',
			'pending'    => '<svg class="rp-payment-pill-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1.6a6.4 6.4 0 1 1 0 12.8A6.4 6.4 0 0 1 8 1.6Zm-.7 3.1v4.4h1.4V4.7H7.3Zm0 5.7v1.4h1.4v-1.4H7.3Z" fill="currentColor"/></svg>',
			'processing' => '<svg class="rp-payment-pill-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1.6a6.4 6.4 0 1 1 0 12.8A6.4 6.4 0 0 1 8 1.6Zm.6 3.1H7.4v3.8l3 1.8.6-1-2.4-1.4V4.7Z" fill="currentColor"/></svg>',
			'failed'     => '<svg class="rp-payment-pill-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1.6a6.4 6.4 0 1 1 0 12.8A6.4 6.4 0 0 1 8 1.6Zm2.5 4.3-.9-.9L8 6.6 6.4 5l-.9.9L7.1 7.5 5.5 9.1l.9.9L8 8.4l1.6 1.6.9-.9-1.6-1.6 1.6-1.6Z" fill="currentColor"/></svg>',
			'refunded'   => '<svg class="rp-payment-pill-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M6.7 3.2 2.8 7.1l3.9 3.9.9-.9-2.3-2.3H10a2.2 2.2 0 0 1 0 4.4H7.8v1.3H10A3.5 3.5 0 0 0 10 6.5H5.3l2.3-2.3-.9-1Z" fill="currentColor"/></svg>',
			'abandoned'  => '<svg class="rp-payment-pill-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1.6a6.4 6.4 0 1 1 0 12.8A6.4 6.4 0 0 1 8 1.6Zm0 2.7a3.7 3.7 0 0 0-2.6 6.3l5.2-5.2A3.7 3.7 0 0 0 8 4.3Zm2.6 1.1-5.2 5.2a3.7 3.7 0 0 0 5.2-5.2Z" fill="currentColor"/></svg>',
			'trash'      => '<svg class="rp-payment-pill-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M5.5 2.2h5l.5 1.2h2.1v1.2H2.9V3.4H5l.5-1.2Zm-1.4 3.4h7.8l-.5 8.2H4.6l-.5-8.2Z" fill="currentColor"/></svg>',
		);

		return isset( $icons[ $status ] ) ? $icons[ $status ] : $icons['pending'];
	}

	/**
	 * Make gateway labels readable in the compact Payment column.
	 *
	 * @since 3.3
	 * @param string $label Raw gateway admin label.
	 * @return string
	 */
	protected static function format_payment_gateway_label( $label ) {
		$label      = trim( str_replace( array( '_', '-' ), ' ', (string) $label ) );
		$lookup_key = strtolower( preg_replace( '/\s+/', ' ', $label ) );
		$known      = array(
			'upi'              => 'UPI',
			'phonepe'          => 'PhonePe',
			'razorpay'         => 'Razorpay',
			'stripe'           => 'Stripe',
			'pay by cash'      => 'Pay by cash',
			'cash on delivery' => 'Cash',
			'test payment'     => 'Test Payment',
		);

		return isset( $known[ $lookup_key ] ) ? $known[ $lookup_key ] : ucfirst( $label );
	}

	/**
	 * Get the next operational order status for quick actions.
	 *
	 * @param string $status Current order status.
	 * @param string $service_type Order service type.
	 * @return array<string, string>
	 */
	protected static function get_next_order_status_action( $status, $service_type = '' ) {
		$map = array(
			'pending'    => array( 'status' => 'accepted',   'label' => __( 'Accept order', 'restropress' ) ),
			'accepted'   => array( 'status' => 'processing', 'label' => __( 'Start preparing', 'restropress' ) ),
			'processing' => array( 'status' => 'ready',      'label' => __( 'Mark ready', 'restropress' ) ),
			'ready'      => array( 'status' => 'completed',  'label' => __( 'Complete order', 'restropress' ) ),
			'transit'    => array( 'status' => 'completed',  'label' => __( 'Complete order', 'restropress' ) ),
		);

		if ( 'ready' === $status && 'delivery' === $service_type ) {
			$map['ready'] = array( 'status' => 'transit', 'label' => __( 'Out for delivery', 'restropress' ) );
		}

		return isset( $map[ $status ] ) ? $map[ $status ] : array();
	}

	/**
	 * Find orders by customer phone saved in payment/delivery metadata.
	 *
	 * @param string $search Search input.
	 * @return int[]
	 */
		protected static function search_order_ids_by_phone( $search ) {
			global $wpdb;

		$digits = preg_replace( '/\D+/', '', (string) $search );
		if ( strlen( $digits ) < 6 ) {
			return array();
		}

		$like_digits = '%' . $wpdb->esc_like( $digits ) . '%';
		$like_raw    = '%' . $wpdb->esc_like( (string) $search ) . '%';
		$ids         = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type = 'rpress_payment'
				  AND pm.meta_key IN ( '_rpress_payment_meta', '_rpress_delivery_address' )
				  AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s )",
				$like_digits,
				$like_raw
			)
		);

		if ( is_numeric( $search ) ) {
			$post = get_post( absint( $search ) );
			if ( $post && 'rpress_payment' === $post->post_type ) {
				$ids[] = absint( $search );
			}
		}

			return array_values( array_unique( array_map( 'absint', $ids ) ) );
		}

		/**
		 * Find orders by the visible order number, including "#123" searches.
		 *
		 * @since 3.3
		 * @param string $search Search input.
		 * @return int[]
		 */
		protected static function search_order_ids_by_order_number( $search ) {
			global $wpdb;

			$raw      = trim( (string) $search );
			$number   = ltrim( $raw, "# \t\n\r\0\x0B" );
			$ids      = array();
			$is_hash  = 0 === strpos( $raw, '#' );
			$is_short = (bool) preg_match( '/^\d{1,5}$/', $number );

			if ( $is_hash || $is_short ) {
				$post_id = absint( $number );
				if ( $post_id ) {
					$post = get_post( $post_id );
					if ( $post && 'rpress_payment' === $post->post_type ) {
						$ids[] = $post_id;
					}
				}
			}

			if ( rpress_get_option( 'enable_sequential' ) && '' !== $number ) {
				$like = '%' . $wpdb->esc_like( $number ) . '%';
				$seq_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT post_id
						FROM {$wpdb->postmeta}
						WHERE meta_key = '_rpress_payment_number'
						  AND meta_value LIKE %s",
						$like
					)
				);
				$ids = array_merge( $ids, $seq_ids );
			}

			return array_values( array_unique( array_map( 'absint', $ids ) ) );
		}

		/**
		 * Find orders by purchased item, variation, or add-on name.
	 *
	 * @param string $search Search input.
	 * @return int[]
	 */
	protected static function search_order_ids_by_item( $search ) {
		global $wpdb;

		$search = trim( (string) $search );
		if ( strlen( $search ) < 2 ) {
			return array();
		}

		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$ids  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type = 'rpress_payment'
				  AND pm.meta_key = '_rpress_payment_meta'
				  AND pm.meta_value LIKE %s",
				$like
			)
		);

		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Apply a post__in restriction, intersecting with any existing one.
	 *
	 * @param array $args Query args.
	 * @param int[] $ids IDs to restrict to.
	 * @return array
	 */
	protected static function restrict_query_to_order_ids( array $args, array $ids ) {
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		if ( isset( $args['post__in'] ) && is_array( $args['post__in'] ) ) {
			$ids = array_values( array_intersect( array_map( 'absint', $args['post__in'] ), $ids ) );
		}
		$args['post__in'] = empty( $ids ) ? array( 0 ) : $ids;
		return $args;
	}

	/**
	 * Actions column: View / Print / overflow ⋮ menu.
	 */
	protected static function render_actions_cell( $payment ) {
		$details_url = add_query_arg( 'id', $payment->ID, admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details' ) );
		$base_url    = admin_url( 'admin.php?page=rpress-payment-history' );
		$resend_url  = wp_nonce_url(
			add_query_arg( array( 'rpress-action' => 'email_links', 'purchase_id' => $payment->ID ), $base_url ),
			'rpress_payment_nonce'
		);
		$trash_url   = wp_nonce_url(
			add_query_arg( array( 'rpress-action' => 'trash_order', 'purchase_id' => absint( $payment->ID ) ), $base_url ),
			'rpress_payment_nonce'
		);
		$restore_url = wp_nonce_url(
			add_query_arg( array( 'rpress-action' => 'restore_order', 'purchase_id' => absint( $payment->ID ) ), $base_url ),
			'rpress_payment_nonce'
		);
		$delete_url  = wp_nonce_url(
			add_query_arg( array( 'rpress-action' => 'delete_order', 'purchase_id' => absint( $payment->ID ) ), $base_url ),
			'rpress_payment_nonce'
		);

		$out  = '<div class="rp-actions-cell">';
		$out .= '<a href="' . esc_url( $details_url ) . '" class="rp-action-btn" title="' . esc_attr__( 'View', 'restropress' ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><span class="rp-action-btn-label">' . esc_html__( 'View', 'restropress' ) . '</span></a>';

		// Receipt-print: delegate to the print-receipts feature so the Actions
		// button respects enable_printing and order_print_status settings.
		$print_button = '';
		if ( class_exists( 'RPRESS_Print_Receipts' ) ) {
			if ( method_exists( 'RPRESS_Print_Receipts', 'is_print_action_available' ) && RPRESS_Print_Receipts::is_print_action_available( $payment->ID ) ) {
				$print_button  = '<div style="display:none" class="print-display-area" id="print-display-area-' . absint( $payment->ID ) . '"></div>';
				$print_button .= '<button type="button" data-payment-id="' . absint( $payment->ID ) . '" class="rp-action-btn rp_print_now" title="' . esc_attr__( 'Print', 'restropress' ) . '"><span class="dashicons dashicons-printer" aria-hidden="true"></span><span class="rp-action-btn-label">' . esc_html__( 'Print', 'restropress' ) . '</span></button>';
			}
		}
		$out .= $print_button;
		$out .= '<div class="rp-action-overflow">';
		$out .= '<button type="button" class="rp-action-btn rp-action-overflow-toggle" aria-haspopup="true" aria-expanded="false" title="' . esc_attr__( 'More actions', 'restropress' ) . '"><span class="dashicons dashicons-ellipsis" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'More actions', 'restropress' ) . '</span></button>';
		$out .= '<ul class="rp-action-overflow-menu" hidden>';
		if ( rpress_is_payment_complete( $payment->ID ) ) {
			$out .= '<li><a href="' . esc_url( $resend_url ) . '">' . esc_html__( 'Resend Purchase Receipt', 'restropress' ) . '</a></li>';
		}
		if ( rpress_is_order_trashable( $payment->ID ) ) {
			$out .= '<li><a href="' . esc_url( $trash_url ) . '" class="rp-action-destructive">' . esc_html__( 'Trash', 'restropress' ) . '</a></li>';
		} elseif ( rpress_is_order_restorable( $payment->ID ) ) {
			$out .= '<li><a href="' . esc_url( $restore_url ) . '">' . esc_html__( 'Restore', 'restropress' ) . '</a></li>';
			$out .= '<li><a href="' . esc_url( $delete_url ) . '" class="rp-action-destructive">' . esc_html__( 'Delete Permanently', 'restropress' ) . '</a></li>';
		}
		$out .= '</ul>';
		$out .= '</div>';
		$out .= '</div>';
		return $out;
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
		if ( ! self::is_all_time_mode() && empty( $args['start-date'] ) && empty( $args['end-date'] ) && empty( $_GET['s'] ) ) {
			$args['start-date'] = self::format_filter_date();
			$args['end-date']   = self::format_filter_date();
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
		if ( ! empty( $_GET['order_status'] ) && 'all' !== sanitize_text_field( $_GET['order_status'] ) ) {
			$args['order_status'] = sanitize_text_field( $_GET['order_status'] );
		}
		$payment_count          		= rpress_count_payments( $args );
		$this->completed_count   		=  isset( $payment_count->completed ) ? $payment_count->completed : 0;
		$this->pending_count    		=  isset( $payment_count->pending ) ? $payment_count->pending : 0 ;
		$this->paid_count 				=  isset( $payment_count->publish ) ? $payment_count->publish : 0 ;
		$this->trash_count  			= $this->count_trash_order();
		$this->out_for_deliver_count   	=  isset( $payment_count->processing ) ? $payment_count->processing : 0 ;

		$service_count_args = $args;
		unset( $service_count_args['service-type'] );

		$current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		if ( ! empty( $current_status ) && 'all' !== $current_status ) {
			$service_count_args['status'] = ( 'paid' === $current_status ) ? 'publish' : $current_status;
		}

		$this->delivery_count = $this->count_service_type( $service_count_args, 'delivery' );
		$this->pickup_count   = $this->count_service_type( $service_count_args, 'pickup' );
		$this->dinein_count   = $this->count_service_type( $service_count_args, 'dinein' );
		$this->total_count    = $this->sum_payment_count_totals( $payment_count );
	}
	public function count_service_type( $args = array(), $service_type = '' ) {
		if ( empty( $service_type ) ) {
			return 0;
		}

		$args['service-type'] = $service_type;
		$service_counts       = rpress_count_payments( $args );

		return $this->sum_payment_count_totals( $service_counts );
	}

	private function sum_payment_count_totals( $counts ) {
		if ( ! is_object( $counts ) ) {
			return 0;
		}

		$excluded_statuses = array(
			'trash',
			'auto-draft',
			'inherit',
			'future',
			'draft',
			'private',
			'new',
		);

		$total = 0;

		foreach ( (array) $counts as $status => $count ) {
			if ( in_array( $status, $excluded_statuses, true ) ) {
				continue;
			}

			$total += absint( $count );
		}

		return $total;
	}
	public function count_trash_order() {
		global $wpdb;
		$query = "SELECT count( * ) AS num_posts FROM $wpdb->posts WHERE post_type = 'rpress_payment' AND  post_status= 'trash'" ;

		$cache_key = md5( $query );
		$count = wp_cache_get( $cache_key, 'counts' );
		if ( false !== $count ) {
			return (int) $count;
		}
		$rows  = $wpdb->get_row( $query, ARRAY_A );
		$count = isset( $rows['num_posts'] ) ? (int) $rows['num_posts'] : 0;
		// Was never cached before, so the lookup ran on every page load.
		wp_cache_set( $cache_key, $count, 'counts', 60 );

		return $count;
	}
	/**
	 * Retrieve all the data for all the payments
	 *
	 * @since  1.0.0
	 * @return array $payment_data Array of all the data for the payments
	 */
	public function payments_data( $number = null, $page = null, $ids_only = false ) {
		$per_page   	 = null === $number ? $this->per_page : $number;
		$orderby    	 = isset( $_GET['orderby'] )      ? urldecode( sanitize_text_field( $_GET['orderby'] ) ) : 'ID';
		$order      	 = isset( $_GET['order'] )        ? sanitize_text_field( $_GET['order'] ) : 'DESC';
		$user       	 = isset( $_GET['user'] )         ? sanitize_text_field( $_GET['user'] ) : null;
		$customer   	 = isset( $_GET['customer'] )     ? sanitize_text_field( $_GET['customer'] ) : null;
		$status_filter    = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		if ( empty( $status_filter ) || 'all' === $status_filter || 'any' === $status_filter ) {
			$status = rpress_get_payment_status_keys();
		} elseif ( 'paid' === $status_filter ) {
			$status = 'publish';
		} else {
			$status = $status_filter;
		}
		$meta_key   	 = isset( $_GET['meta_key'] )     ? sanitize_text_field( $_GET['meta_key'] ) : null;
		$year       	 = isset( $_GET['year'] )         ? sanitize_text_field( $_GET['year'] ) : null;
		$month      	 = isset( $_GET['m'] )            ? sanitize_text_field( $_GET['m'] ) : null;
		$day        	 = isset( $_GET['day'] )          ? sanitize_text_field( $_GET['day'] ) : null;
		$search     	 = isset( $_GET['s'] )            ? sanitize_text_field( $_GET['s'] ) : null;
		$urgency_filter  = isset( $_GET['urgency'] )      ? sanitize_text_field( wp_unslash( $_GET['urgency'] ) ) : '';
		$start_date 	 = isset( $_GET['start-date'] )   ? sanitize_text_field( $_GET['start-date'] ) : null;
		$end_date   	 = isset( $_GET['end-date'] )     ? sanitize_text_field( $_GET['end-date'] ) : $start_date;
		if ( self::is_all_time_mode() ) {
			$start_date = null;
			$end_date   = null;
		} elseif ( empty( $start_date ) && empty( $end_date ) && empty( $search ) && empty( $urgency_filter ) ) {
			// Implicit "Today" default - skipped for urgency views, where the
			// pre-computed urgent ID set spans yesterday..tomorrow and the
			// implicit date cap would hide cross-day late orders the chip counted.
			$start_date = self::format_filter_date();
			$end_date   = self::format_filter_date();
		}
		$gateway    	 = isset( $_GET['gateway'] )      ? sanitize_text_field( $_GET['gateway'] ) : null;
		$service_date 	 = isset( $_GET['service-date'] ) ? sanitize_text_field( $_GET['service-date'] ) : null;
		$query_start_date = $start_date ? self::normalize_filter_date_for_query( $start_date ) : null;
		$query_end_date   = $end_date ? self::normalize_filter_date_for_query( $end_date ) : null;
		$query_service_date = $service_date ? self::normalize_filter_date_for_query( $service_date ) : null;
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
			'page'         => null === $page ? ( isset( $_GET['paged'] ) ? sanitize_text_field( $_GET['paged'] ) : null ) : $page,
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
			'start_date'   => $query_start_date ? $query_start_date : $start_date,
			'end_date'     => $query_end_date ? $query_end_date : $end_date,
			'gateway'      => $gateway,
			'service_date' => $query_service_date ? $query_service_date : $service_date,
			'order_status' =>$selected_orders
			
		);
		if( is_string( $search ) && false !== strpos( $search, 'txn:' ) ) {
			$args['search_in_notes'] = true;
			$args['s'] = trim( str_replace( 'txn:', '', $args['s'] ) );
		}
		if ( is_string( $search ) ) {
			$order_number_ids = self::search_order_ids_by_order_number( $search );
			if ( ! empty( $order_number_ids ) ) {
				$args = self::restrict_query_to_order_ids( $args, $order_number_ids );
				unset( $args['s'] );
			} else {
				$phone_ids  = self::search_order_ids_by_phone( $search );
				$item_ids   = self::search_order_ids_by_item( $search );
				$search_ids = array_values( array_unique( array_merge( $phone_ids, $item_ids ) ) );
				if ( ! empty( $search_ids ) ) {
					$args = self::restrict_query_to_order_ids( $args, $search_ids );
					unset( $args['s'] );
				}
			}
		}
		$meta_query = array();

		if ( ! empty( $service_type ) ) {
			$meta_query[] = array(
				'key'     => '_rpress_delivery_type',
				'value'   => $service_type,
				'compare' => '=',
			);
		}

		if ( ! empty( $selected_orders ) && 'all' !== $selected_orders ) {
			if ( $selected_orders === 'needs_attention' ) {
				// Compound bucket - resolve via the dedicated helper and
				// constrain the query with post__in. Empty result => no matches.
				$ids  = self::get_needs_attention_ids( $query_start_date, $query_end_date, self::is_all_time_mode() || ( empty( $query_start_date ) && empty( $query_end_date ) && ! empty( $search ) ) );
				$args = self::restrict_query_to_order_ids( $args, $ids );
			} else {
				// Allow comma-separated lists (e.g. "accepted,processing") so the
				// order-status segmented tabs can bucket multiple statuses into one tab.
				$status_values = ( strpos( $selected_orders, ',' ) !== false )
					? array_filter( array_map( 'sanitize_text_field', explode( ',', $selected_orders ) ) )
					: array( $selected_orders );
				$meta_query[] = array(
					'key'     => '_order_status',
					'value'   => $status_values,
					'compare' => count( $status_values ) > 1 ? 'IN' : '=',
				);
			}
		}

		// Phase 4 - urgency filter (Late / Due Soon). We translate the chip
		// click into a post__in restriction using the pre-computed urgent IDs.
		$urgency_filter = isset( $_GET['urgency'] ) ? sanitize_text_field( wp_unslash( $_GET['urgency'] ) ) : '';
		if ( $urgency_filter === 'late' || $urgency_filter === 'due_soon' ) {
			$urgent = self::get_urgent_order_ids();
			$ids    = ( $urgency_filter === 'late' ) ? $urgent['late'] : $urgent['due_soon'];
			// Use post__in to restrict to matching IDs. Empty array => no results
			// (we feed `array(0)` because WP_Query treats an empty post__in as "no filter").
			$args = self::restrict_query_to_order_ids( $args, $ids );
		}

		if ( ! empty( $meta_query ) ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			$args['meta_query'] = $meta_query;
		}
		if ( $ids_only ) {
			// Bare post IDs - no RPRESS_Payment hydration. Used for counting
			// and aggregate passes, where loading full payment objects for
			// every matching order exhausts memory on large stores.
			$args['output'] = 'posts';
			$args['fields'] = 'ids';
		}
		$p_query  = new RPRESS_Payments_Query( $args );
		$results  = $p_query->get_payments();
		if ( $ids_only ) {
			// The non-payments output path returns before the query class
			// removes its posts_where date filter; detach it here so the date
			// clause cannot leak into unrelated queries later in the request.
			remove_filter( 'posts_where', array( $p_query, 'payments_where' ) );
		}
		return $results;
	}

	/**
	 * KPI metrics for the current filtered/search result scope.
	 *
	 * @return array
	 */
	public function get_current_scope_kpi_data() {
		$payment_ids     = array_map( 'absint', (array) $this->payments_data( -1, null, true ) );
		$total           = 0;
		$needs_attention = 0;
		$in_progress     = 0;
		$completed       = 0;
		$revenue         = 0.0;
		$refunds_sum     = 0.0;
		$refunds_count   = 0;
		$now             = current_time( 'timestamp' );
		$done_statuses   = array( 'completed', 'transit', 'cancelled', 'refunded', 'failed' );
		$progress_set    = array( 'accepted', 'processing', 'ready', 'transit' );

		// One query for posts + one for meta instead of hydrating a full
		// RPRESS_Payment object per matching order.
		if ( ! empty( $payment_ids ) ) {
			_prime_post_caches( $payment_ids, false, false );
			update_meta_cache( 'post', $payment_ids );
		}

		foreach ( $payment_ids as $id ) {
			$total++;
			$order_status = rpress_get_order_status( $id );
			$post_status  = get_post_status( $id );
			$amount       = (float) get_post_meta( $id, '_rpress_payment_total', true );

			if ( in_array( $order_status, $progress_set, true ) ) {
				$in_progress++;
			}
			if ( in_array( $order_status, array( 'completed', 'transit' ), true ) ) {
				$completed++;
			}
			if ( 'refunded' === $post_status ) {
				$refunds_sum += $amount;
				$refunds_count++;
			} elseif ( 'failed' !== $post_status ) {
				$gateway = get_post_meta( $id, '_rpress_payment_gateway', true );
				$is_cash = self::is_cash_gateway( $gateway );
				if ( 'publish' === $post_status || ( $is_cash && 'pending' !== $order_status ) ) {
					$revenue += $amount;
				}
			}

			$is_late = false;
			if ( ! in_array( $order_status, $done_statuses, true ) ) {
				$svc_ts = self::parse_service_timestamp( $id );
				if ( $svc_ts && $svc_ts <= $now ) {
					$is_late = true;
				}
			}
			if ( 'pending' === $order_status || 'pending' === $post_status || $is_late ) {
				$needs_attention++;
			}
		}

		$non_refunded  = max( 0, $total - $refunds_count );
		$completed_pct = $total > 0 ? ( $completed / $total ) * 100 : 0;

		return array(
			'total'           => $total,
			'needs_attention' => $needs_attention,
			'in_progress'     => $in_progress,
			'revenue'         => $revenue,
			'completed'       => $completed,
			'completed_pct'   => $completed_pct,
			'refunds_sum'     => $refunds_sum,
			'refunds_count'   => $refunds_count,
			'aov'             => $non_refunded > 0 ? $revenue / $non_refunded : 0,
		);
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
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$total_items = count( $this->payments_data( -1, 1, true ) );
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
	    return (int) $wpdb->get_var(
	      $wpdb->prepare(
	        "SELECT COUNT(DISTINCT p.ID)
	        FROM {$wpdb->posts} p
	        INNER JOIN {$wpdb->postmeta} pm
	          ON pm.post_id = p.ID
	          AND pm.meta_key = '_rpress_delivery_type'
	        WHERE p.post_type = 'rpress_payment'
	          AND p.post_status = 'publish'
	          AND pm.meta_value = %s",
	        $service_type
	      )
	    );
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
	    $customer_details = array(
			'phone'   => '',
			'flat'    => '',
			'postcode'=> '',
			'city'    => '',
			'address' => '',
	    );
	    $gateway  = $payment->gateway;
	    if ( $gateway ) {
	      	$payment_via = sanitize_text_field( self::format_payment_gateway_label( rpress_get_gateway_admin_label( $gateway ) ) );
	    }
	    $payment_meta = $payment->get_meta();
	    $user_info    = $payment->user_info;
	    $customer     = ! empty( $payment->customer_id ) ? new RPRESS_Customer( $payment->customer_id ) : false;

	    if ( ! empty( $user_info['first_name'] ) || ! empty( $user_info['last_name'] ) ) {
	    	$customer_name = trim( $user_info['first_name'] . ' ' . $user_info['last_name'] );
	    } elseif ( $customer && ! empty( $customer->name ) ) {
	    	$customer_name = $customer->name;
	    }
	    $customer_name = sanitize_text_field( $customer_name );

	    if ( ! empty( $user_info['email'] ) ) {
	    	$customer_email = $user_info['email'];
	    } elseif ( $customer && ! empty( $customer->email ) ) {
	    	$customer_email = $customer->email;
	    } elseif ( ! empty( $payment->email ) ) {
	    	$customer_email = $payment->email;
	    }
	    $customer_email = sanitize_email( $customer_email );

	    $delivery_address_meta = get_post_meta( $payment->ID, '_rpress_delivery_address', true );
	    $delivery_address_meta = is_array( $delivery_address_meta ) ? $delivery_address_meta : array();
	    $phone                 = ! empty( $payment_meta['phone'] ) ? $payment_meta['phone'] : '';
	    if ( empty( $phone ) && ! empty( $user_info['phone'] ) ) {
	    	$phone = $user_info['phone'];
	    }
	    if ( empty( $phone ) && ! empty( $delivery_address_meta['phone'] ) ) {
	    	$phone = $delivery_address_meta['phone'];
	    }
	    $phone            = sanitize_text_field( $phone );
	    $flat             = ! empty( $delivery_address_meta['flat'] ) ? sanitize_text_field( $delivery_address_meta['flat'] ) : '';
	    $city             = ! empty( $delivery_address_meta['city'] ) ? sanitize_text_field( $delivery_address_meta['city'] ) : '';
	    $postcode         = ! empty( $delivery_address_meta['postcode'] ) ? sanitize_text_field( $delivery_address_meta['postcode'] ) : '';
	    $customer_address = ! empty( $delivery_address_meta['address'] ) ? sanitize_text_field( $delivery_address_meta['address'] ) : '';
		$customer_details = array(
			'phone'      => $phone,
			'flat'       => $flat,
			'postcode'   => $postcode,
			'city'       => $city,
			'address'    => $customer_address
		);

	    $billing_address 	= isset( $user_info['address'] ) ? $user_info['address'] : '';
	    if ( is_array( $billing_address ) ) {
			$billing_address = implode( ', ', array_filter( array_map( 'sanitize_text_field', $billing_address ) ) );
	    } else {
			$billing_address = sanitize_text_field( (string) $billing_address );
	    }
	    $service_type 		= sanitize_key( rpress_get_service_type( $payment->ID ) );
  		$service_date 		= $payment->get_meta( '_rpress_delivery_date' );
  		$service_date 		= ! empty( $service_date ) ? sanitize_text_field( rpress_local_date( $service_date ) ) : '';
  		$service_time 		= sanitize_text_field( (string) $payment->get_meta( '_rpress_delivery_time' ) );
	    $order_status       = sanitize_key( rpress_get_order_status( $payment->ID ) );
	    $order_status_label = rpress_get_order_status_label( $order_status );
	    $details_url        = add_query_arg( 'id', $payment->ID, admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details' ) );
	    $next_status_action = self::get_next_order_status_action( $order_status, $service_type );
	    $print_available    = class_exists( 'RPRESS_Print_Receipts' )
	    	&& method_exists( 'RPRESS_Print_Receipts', 'is_print_action_available' )
	    	&& RPRESS_Print_Receipts::is_print_action_available( $payment->ID );
	    return apply_filters(
	      	'rpress_admin_order_preview_get_order_details',
	      	array(
		        'id'                        => $payment->ID,
		        'service_type'              => sanitize_text_field( rpress_service_label( $service_type ) ),
		        'service_type_slug'         => $service_type,
		        'service_type_icon'         => self::get_service_type_icon( $service_type ),
		        'service_date'              => $service_date,
		        'service_time'              => $service_time,
		        'status'                    => $order_status,
		        'status_label'              => sanitize_text_field( $order_status_label ? $order_status_label : $order_status ),
		        'payment_via'               => $payment_via,
		        'customer_name'             => $customer_name,
		        'customer_email'            => $customer_email,
		        'customer_details'          => $customer_details,
		        'customer_billing_details'  => $user_info,
		        'item_html'                 => self::get_ordered_items( $payment ),
		        'actions_html'              => self::get_order_preview_actions_html( $payment ),
		        'order_details_url'         => esc_url_raw( $details_url ),
		        'next_status_action'        => $next_status_action,
		        'print_available'           => (bool) $print_available,
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
    		$payment_currency = rpress_get_payment_currency_code( $payment->ID );
      		ob_start(); ?>
      	<div class="rp-order-preview-table-wrapper">
        	<table cellspacing="0" class="rp-order-preview-table">
          		<thead>
            		<tr>
              			<th class="rp-order-preview-table__column--product">
                			<?php esc_html_e( 'Items', 'restropress' ); ?>
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
            			$price      = isset( $fooditems['price'] ) ? $fooditems['price'] : false;
            			$addon_items = array();
            			if ( ! empty( $fooditems['item_number']['options'] ) && is_array( $fooditems['item_number']['options'] ) ) {
            				foreach ( $fooditems['item_number']['options'] as $addon_item ) {
            					if ( is_array( $addon_item ) && ! empty( $addon_item['addon_item_name'] ) ) {
            						$addon_items[] = array(
            							'name'     => sanitize_text_field( $addon_item['addon_item_name'] ),
            							'price'    => isset( $addon_item['price'] ) ? $addon_item['price'] : 0,
            							'quantity' => isset( $addon_item['quantity'] ) ? absint( $addon_item['quantity'] ) : 1,
            						);
            					}
            				}
            			}
            			?>
            		<tr class="rp-order-preview-table">
						<td class="rp-order-preview-table__column--product">
							<div class="rp-preview-item-name"><?php echo esc_html(rpress_get_cart_item_name( $fooditems )); ?></div>
							<?php if ( ! empty( $addon_items ) ) : ?>
								<div class="rp-preview-addons" aria-label="<?php esc_attr_e( 'Add-ons', 'restropress' ); ?>">
									<ul class="rp-preview-addons-list">
										<?php foreach ( $addon_items as $addon_item ) : ?>
											<li class="rp-preview-addon">
												<span class="rp-preview-addon-name">+ <?php echo esc_html( $addon_item['name'] ); ?></span>
												<span class="rp-preview-addon-meta">
													<?php
													echo esc_html(
														rpress_currency_filter(
															rpress_format_amount( $addon_item['price'] ),
															rpress_get_payment_currency_code( $payment->ID )
														)
													);
													echo ' &times; ' . esc_html( $addon_item['quantity'] );
													?>
												</span>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
	              		</td>
	              		<td class="rp-order-preview-table__column--quantity">
	                		<?php echo esc_html(rpress_currency_filter( rpress_format_amount( $fooditems['item_price'] ), $payment_currency ) ) . ' &times; ' . esc_html($fooditems['quantity']); ?>
						</td>
	              		<?php if ( rpress_use_taxes() ) : ?>
	                	<td class="rp-order-preview-table__column--tax">
	                  		<?php echo esc_html(rpress_currency_filter( rpress_format_amount( $item_tax ) )); ?>
	                	</td>
	              		<?php endif; ?>
	              		<td class="rp-order-preview-table__column--price">
	                		<?php echo esc_html(rpress_currency_filter( rpress_format_amount( $price ), $payment_currency )); ?>
	              		</td>
            		</tr>
            		<?php if ( !empty( $special_instruction ) ) : ?>
              		<tr class="rp-order-preview-table special-instruction">
                		<td colspan="3">
                  			<?php printf( esc_html__( 'Special Instruction : %s', 'restropress'), esc_html($special_instruction) ); ?>
                		</td>
              		</tr>
            		<?php endif; ?>
            		<?php endif;
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
		<div class="rp-backbone-modal rp-modal rp-order-preview">
        <div class="rp-backbone-modal-content">
          <section class="rp-backbone-modal-main" role="main">
	            <header class="rp-backbone-modal-header">
	              <div class="rp-order-preview-heading">
	                <div class="rp-order-preview-title-row">
	                  <?php /* translators: %s: order ID */ ?>
	                  <h1><?php echo esc_html( sprintf( __( 'Order #%s', 'restropress' ), '{{ data.id }}' ) ); ?></h1>
	                  <# if ( data.service_type_slug !== '' ) { #>
	                    <mark class="service-type badge-{{ data.service_type_slug }}"><span>{{{ data.service_type_icon }}}{{ data.service_type }}</span></mark>
	                  <# } #>
	                </div>
	                <mark class="order-status status-{{ data.status }}"><span>{{ data.status_label }}</span></mark>
	              </div>
	              <button class="modal-close modal-close-link dashicons dashicons-no-alt">
	                <span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'restropress' ); ?></span>
	              </button>
	            </header>
	            <article>
              <?php do_action( 'rpress_admin_order_preview_start' ); ?>
              <div class="rp-order-preview-wrapper">
	                <div class="rp-order-preview">
		                  <div class="rp-grid rp-grid-2 rp-order-preview-summary">
                    <div class="rp-order-preview-address">
                      <h2><?php esc_html_e( sprintf( __( '%s address', 'restropress' ), '{{ data.service_type }}' ) ); ?></h2>
                      <# if ( data.customer_details.address || data.customer_details.flat || data.customer_details.city || data.customer_details.postcode ) { #>
                        <# if ( data.customer_details.address ) { #>{{ data.customer_details.address }}<br /><# } #>
                        <# if ( data.customer_details.flat ) { #>{{ data.customer_details.flat }}<br /><# } #>
                        <# if ( data.customer_details.city || data.customer_details.postcode ) { #>{{ data.customer_details.city }} {{ data.customer_details.postcode }}<# } #>
                      <# } else { #>
                        <p class="rp-order-preview-empty"><?php esc_html_e( 'No delivery address saved for this order.', 'restropress' ); ?></p>
                      <# } #>
                    </div>
                  <div class="rp-order-preview-customer-details">
                    <h2><?php esc_html_e( 'Customer details', 'restropress' ); ?></h2>
                    <# if ( data.customer_name || data.customer_email || data.customer_details.phone ) { #>
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
                    <# } else { #>
                      <p class="rp-order-preview-empty"><?php esc_html_e( 'No customer contact details saved for this order.', 'restropress' ); ?></p>
                    <# } #>
                  </div>
	                  </div>
			                  <div class="rp-grid rp-grid-3 order-service-meta">
		                    <# if ( data.payment_via ) { #>
		                      <span>
		                        <strong><?php esc_html_e( 'Payment via', 'restropress' ); ?></strong>
		                        {{{ data.payment_via }}}
		                      </span>
		                    <# } #>
		                    <# if ( data.service_date ) { #>
		                      <span>
		                        <strong><?php esc_html_e( 'Service date', 'restropress' ); ?></strong>
		                        {{{ data.service_date }}}
		                      </span>
		                    <# } #>
		                    <# if ( data.service_time ) { #>
		                      <span>
		                        <strong><?php esc_html_e( 'Service time', 'restropress' ); ?></strong>
		                        {{{ data.service_time }}}
		                      </span>
		                    <# } #>
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
	            <footer>
	              <div class="inner">
	                <div class="rp-order-preview-footer-left">
	                <# if ( data.print_available ) { #>
	                  <div style="display:none" class="print-display-area" id="print-display-area-{{ data.id }}"></div>
	                  <button type="button" data-payment-id="{{ data.id }}" class="button rp-btn rp-btn-secondary rp-order-preview-print rp_print_now">
	                    <span class="dashicons dashicons-printer" aria-hidden="true"></span>
	                    <?php esc_html_e( 'Print receipt', 'restropress' ); ?>
	                  </button>
	                <# } #>
	                </div>
	                <button type="button" class="button modal-close"><?php esc_html_e( 'Close', 'restropress' ); ?></button>
	                <a class="button rp-btn rp-btn-secondary rp-order-preview-open" href="{{ data.order_details_url }}"><?php esc_html_e( 'Open order', 'restropress' ); ?></a>
	                <# if ( data.next_status_action && data.next_status_action.status ) { #>
	                  <button type="button"
	                    class="button button-primary rp-btn rp-btn-primary rp-modal-status-action"
	                    data-payment-id="{{ data.id }}"
	                    data-current-status="{{ data.status }}"
	                    data-next-status="{{ data.next_status_action.status }}">
	                    {{ data.next_status_action.label }}
	                  </button>
	                <# } #>
	              </div>
	            </footer>
	          </section>
        </div>
      </div>
      <div class="rp-backbone-modal-backdrop modal-close"></div>
    	</script>
  	<?php }
}
