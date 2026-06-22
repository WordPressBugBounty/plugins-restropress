<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

function rpress_customers_admin_legacy_css_version( $src, $handle ) {
	$files = array(
		'rpress_admin_styles' => 'assets/css/admin.css',
		'rpress-admin'        => 'assets/css/rpress-admin.css',
	);

	if ( ! isset( $files[ $handle ] ) ) {
		return $src;
	}

	$file = RP_PLUGIN_DIR . $files[ $handle ];
	if ( ! file_exists( $file ) ) {
		return $src;
	}

	return add_query_arg( 'ver', filemtime( $file ), remove_query_arg( 'ver', $src ) );
}
add_filter( 'style_loader_src', 'rpress_customers_admin_legacy_css_version', 20, 2 );

/**
 * Customers Page
 *
 * Renders the customers page contents.
 *
 * @since  1.0.0
 * @return void
*/
function rpress_customers_page() {
	$default_views = rpress_customer_views();
	$requested_view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'customers';
	if ( array_key_exists( $requested_view, $default_views ) && is_callable( $default_views[$requested_view] ) ) {
		rpress_render_customer_view( $requested_view, $default_views );
	} else {
		rpress_customers_list();
	}
}
/**
 * Register the views for customer management
 *
 * @since  1.0.0
 * @return array Array of views and their callbacks
 */
function rpress_customer_views() {
	$views = array();
	return apply_filters( 'rpress_customer_views', $views );
}
/**
 * Register the tabs for customer management
 *
 * @since  1.0.0
 * @return array Array of tabs for the customer
 */
function rpress_customer_tabs() {
	$tabs = array();
	return apply_filters( 'rpress_customer_tabs', $tabs );
}
/**
 * List table of customers
 *
 * @since  1.0.0
 * @return void
 */
function rpress_customers_list() {
	include( dirname( __FILE__ ) . '/class-customer-table.php' );
	$customers_table = new RPRESS_Customer_Reports_Table();
	$customers_table->prepare_items();
	?>
	<div class="wrap rp-admin-scope rp-customers-page rp-customers-list-page">
		<div class="rp-page-header rp-customers-header">
			<div class="rp-page-header-titles">
				<h1 class="wp-heading-inline rp-page-title"><?php esc_html_e( 'Customers', 'restropress' ); ?></h1>
				<p class="rp-page-subtitle"><?php esc_html_e( 'View customer profiles, order history, emails, and notes.', 'restropress' ); ?></p>
			</div>
		</div>
		<hr class="wp-header-end">
		<?php do_action( 'rpress_customers_table_top' ); ?>
		<form id="rpress-customers-filter" class="rp-list-table-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php?page=rpress-customers' ) ); ?>">
			<input type="hidden" name="page" value="rpress-customers" />
			<input type="hidden" name="view" value="customers" />
			<div class="rp-table-toolbar rp-list-table-toolbar">
				<div class="rp-table-toolbar-primary">
					<?php $customers_table->views(); ?>
				</div>
			</div>
			<?php $customers_table->display(); ?>
		</form>
		<?php do_action( 'rpress_customers_table_bottom' ); ?>
	</div>
	<?php
}
/**
 * Renders the customer view wrapper
 *
 * @since  1.0.0
 * @param  string $view      The View being requested
 * @param  array $callbacks  The Registered views and their callback functions
 * @return void
 */
function rpress_render_customer_view( $view, $callbacks ) {
	$render = true;
	$customer_view_role = apply_filters( 'rpress_view_customers_role', 'view_shop_reports' );
	if ( ! current_user_can( $customer_view_role ) ) {
		rpress_set_error( 'rpress-no-access', __( 'You are not permitted to view this data.', 'restropress' ) );
		$render = false;
	}
	if ( ! isset( $_GET['id'] ) || ! is_numeric( $_GET['id'] ) ) {
		rpress_set_error( 'rpress-invalid_customer', __( 'Invalid Customer ID Provided.', 'restropress' ) );
		$render = false;
	}
	$customer_id = absint( $_GET['id'] );
	$customer    = new RPRESS_Customer( $customer_id );
	if ( empty( $customer->id ) ) {
		rpress_set_error( 'rpress-invalid_customer', __( 'Invalid Customer ID Provided.', 'restropress' ) );
		$render = false;
	}
	?>
	<div class="wrap rp-admin-scope rp-customers-page rp-customer-detail-page">
		<div class="rp-page-header rp-customers-header">
			<div class="rp-page-header-titles">
				<h1 class="wp-heading-inline rp-page-title"><?php esc_html_e( 'Customer Details', 'restropress' ); ?></h1>
				<p class="rp-page-subtitle"><?php esc_html_e( 'Manage customer profile, emails, notes, and related order history.', 'restropress' ); ?></p>
			</div>
			<div class="rp-page-actions">
				<?php do_action( 'rpress_after_customer_details_header', $customer ); ?>
			</div>
		</div>
		<hr class="wp-header-end">
		<?php if ( rpress_get_errors() ) :?>
			<div class="error settings-error rp-notice">
				<?php rpress_print_errors(); ?>
			</div>
		<?php endif; ?>
		<?php if ( $customer && $render ) : ?>
			<div id="rpress-item-wrapper" class="rpress-clearfix rp-customer-shell">
				<div id="rpress-item-card-wrapper" class="rpress-customer-card-wrapper rp-customer-content">
					<?php call_user_func( $callbacks[ $view ], $customer ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
function rpress_customer_admin_get_payments( $customer, $limit = 10 ) {
	$payments = method_exists( $customer, 'get_payments' ) ? $customer->get_payments() : array();

	usort(
		$payments,
		function( $a, $b ) {
			return strtotime( $b->date ) - strtotime( $a->date );
		}
	);

	if ( $limit > 0 ) {
		$payments = array_slice( $payments, 0, $limit );
	}

	return $payments;
}

function rpress_customer_admin_get_address( $customer ) {
	$defaults = array(
		'line1'   => '',
		'line2'   => '',
		'city'    => '',
		'state'   => '',
		'country' => '',
		'zip'     => '',
	);

	if ( empty( $customer->user_id ) ) {
		return $defaults;
	}

	$address = get_user_meta( $customer->user_id, '_rpress_user_address', true );
	if ( is_array( $address ) && array_filter( $address ) ) {
		return wp_parse_args( $address, $defaults );
	}

	$delivery_address = get_user_meta( $customer->user_id, '_rpress_user_delivery_address', true );
	if ( is_array( $delivery_address ) && array_filter( $delivery_address ) ) {
		return wp_parse_args(
			array(
				'line1' => isset( $delivery_address['address'] ) ? $delivery_address['address'] : '',
				'line2' => isset( $delivery_address['flat'] ) ? $delivery_address['flat'] : '',
				'city'  => isset( $delivery_address['city'] ) ? $delivery_address['city'] : '',
				'zip'   => isset( $delivery_address['postcode'] ) ? $delivery_address['postcode'] : '',
			),
			$defaults
		);
	}

	return $defaults;
}

function rpress_customer_admin_format_address( $address ) {
	$parts = array_filter(
		array(
			isset( $address['line1'] ) ? $address['line1'] : '',
			isset( $address['line2'] ) ? $address['line2'] : '',
			isset( $address['city'] ) ? $address['city'] : '',
			isset( $address['state'] ) ? $address['state'] : '',
			isset( $address['zip'] ) ? $address['zip'] : '',
			isset( $address['country'] ) ? $address['country'] : '',
		)
	);

	return $parts ? implode( ', ', $parts ) : '';
}

function rpress_customer_admin_get_phone( $customer, $payments = array() ) {
	if ( ! empty( $customer->user_id ) ) {
		$phone = get_user_meta( $customer->user_id, '_rpress_phone', true );
		if ( ! empty( $phone ) ) {
			return $phone;
		}
	}

	foreach ( $payments as $payment ) {
		$payment_meta = rpress_get_payment_meta( $payment->ID );
		if ( ! empty( $payment_meta['phone'] ) ) {
			return $payment_meta['phone'];
		}
	}

	return '';
}

function rpress_customer_admin_get_service_label( $payment_id ) {
	$service_type = function_exists( 'rpress_get_service_type' ) ? rpress_get_service_type( $payment_id ) : get_post_meta( $payment_id, '_rpress_delivery_type', true );
	return $service_type && function_exists( 'rpress_service_label' ) ? rpress_service_label( $service_type ) : __( 'Not captured', 'restropress' );
}

function rpress_customer_admin_get_order_status_tone( $status ) {
	$tones = array(
		'pending'    => 'is-warning',
		'accepted'   => 'is-info',
		'processing' => 'is-info',
		'ready'      => 'is-success',
		'transit'    => 'is-info',
		'completed'  => 'is-success',
		'cancelled'  => 'is-danger',
		'refunded'   => 'is-danger',
		'failed'     => 'is-danger',
	);

	return isset( $tones[ $status ] ) ? $tones[ $status ] : 'is-info';
}

function rpress_customer_admin_status_badge( $payment ) {
	$status = function_exists( 'rpress_get_order_status' ) ? sanitize_key( rpress_get_order_status( $payment->ID ) ) : '';
	$label  = $status && function_exists( 'rpress_get_order_status_label' ) ? rpress_get_order_status_label( $status ) : '';

	if ( empty( $label ) ) {
		$label = $status ? ucwords( str_replace( array( '-', '_' ), ' ', $status ) ) : __( 'Unknown', 'restropress' );
	}

	printf(
		'<span class="rp-status-badge %1$s status-%2$s">%3$s</span>',
		esc_attr( rpress_customer_admin_get_order_status_tone( $status ) ),
		esc_attr( sanitize_html_class( $status ) ),
		esc_html( $label )
	);
}

function rpress_customer_admin_empty_state( $title, $description, $icon = 'dashicons-info' ) {
	?>
	<div class="rp-empty-state rp-empty-state-compact">
		<span class="dashicons <?php echo sanitize_html_class( $icon ); ?>" aria-hidden="true"></span>
		<h3><?php echo esc_html( $title ); ?></h3>
		<p><?php echo esc_html( $description ); ?></p>
	</div>
	<?php
}

function rpress_customer_admin_render_order_table( $customer, $payments, $compact = false ) {
	?>
	<div class="rp-table-scroll">
		<table class="wp-list-table widefat striped payments rp-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'restropress' ); ?></th>
					<th><?php esc_html_e( 'Service Type', 'restropress' ); ?></th>
					<th><?php esc_html_e( 'Status', 'restropress' ); ?></th>
					<th><?php esc_html_e( 'Total', 'restropress' ); ?></th>
					<th><?php esc_html_e( 'Date', 'restropress' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'restropress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $payments ) ) : ?>
					<?php foreach ( $payments as $payment ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details&id=' . $payment->ID ) ); ?>">#<?php echo esc_html( absint( $payment->ID ) ); ?></a></td>
							<td><?php echo esc_html( rpress_customer_admin_get_service_label( $payment->ID ) ); ?></td>
							<td><?php rpress_customer_admin_status_badge( $payment ); ?></td>
							<td><?php echo esc_html( rpress_payment_amount( $payment->ID ) ); ?></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $payment->date ) ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-payment-history&view=view-order-details&id=' . $payment->ID ) ); ?>"><?php esc_html_e( 'View Details', 'restropress' ); ?></a>
								<?php do_action( 'rpress_customer_recent_purchases_actions', $customer, $payment ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="6">
							<?php rpress_customer_admin_empty_state( __( 'No orders yet', 'restropress' ), __( 'Orders placed by this customer will appear here.', 'restropress' ), 'dashicons-cart' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function rpress_customer_admin_render_menu_items( $customer ) {
	$fooditems = rpress_get_users_ordered_products( $customer->email );
	?>
	<div class="rp-table-scroll">
		<table class="wp-list-table widefat striped fooditems rp-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Menu Item', 'restropress' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'restropress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $fooditems ) ) : ?>
					<?php foreach ( $fooditems as $fooditem ) : ?>
						<tr>
							<td><?php echo esc_html( $fooditem->post_title ); ?></td>
							<td><a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $fooditem->ID ) ); ?>"><?php esc_html_e( 'View Item', 'restropress' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="2">
							<?php rpress_customer_admin_empty_state( __( 'No menu items ordered', 'restropress' ), __( 'Menu item history will appear after the customer places an order.', 'restropress' ), 'dashicons-food' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function rpress_customer_admin_render_emails( $customer ) {
	$primary_email     = $customer->email;
	$additional_emails = $customer->emails;
	$all_emails        = array( 'primary' => $primary_email );

	foreach ( $additional_emails as $key => $email ) {
		if ( $primary_email === $email ) {
			continue;
		}
		$all_emails[ $key ] = $email;
	}
	?>
	<table class="wp-list-table widefat striped emails rp-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Contact Email', 'restropress' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'restropress' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $all_emails as $key => $email ) : ?>
				<tr data-key="<?php echo esc_attr( $key ); ?>">
					<td>
						<?php echo esc_html( $email ); ?>
						<?php if ( 'primary' === $key ) : ?>
							<span class="dashicons dashicons-star-filled primary-email-icon" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Primary email', 'restropress' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( 'primary' !== $key ) : ?>
							<?php
							$base_url    = admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . $customer->id );
							$promote_url = wp_nonce_url( add_query_arg( array( 'email' => rawurlencode( $email ), 'rpress_action' => 'customer-primary-email' ), $base_url ), 'rpress-set-customer-primary-email' );
							$remove_url  = wp_nonce_url( add_query_arg( array( 'email' => rawurlencode( $email ), 'rpress_action' => 'customer-remove-email' ), $base_url ), 'rpress-remove-customer-email' );
							?>
							<a href="<?php echo esc_url( $promote_url ); ?>"><?php esc_html_e( 'Make Primary', 'restropress' ); ?></a>
							&nbsp;|&nbsp;
							<a href="<?php echo esc_url( $remove_url ); ?>" class="delete"><?php esc_html_e( 'Remove', 'restropress' ); ?></a>
						<?php else : ?>
							<span class="rp-muted-text"><?php esc_html_e( 'Primary', 'restropress' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr class="add-customer-email-row">
				<td colspan="2" class="add-customer-email-td">
					<div class="add-customer-email-wrapper">
						<input type="hidden" name="customer-id" value="<?php echo esc_attr( $customer->id ); ?>" />
						<?php wp_nonce_field( 'rpress-add-customer-email', 'add_email_nonce', false, true ); ?>
						<input type="email" class="rp-input" name="additional-email" value="" placeholder="<?php esc_html_e( 'Email Address', 'restropress' ); ?>" />
						<label class="rp-checkbox-inline" for="make-additional-primary">
							<input type="checkbox" name="make-additional-primary" value="1" id="make-additional-primary" />
							<?php esc_html_e( 'Make Primary', 'restropress' ); ?>
						</label>
						<button class="button button-secondary rp-btn rp-btn-secondary rpress-add-customer-email" id="add-customer-email"><?php esc_html_e( 'Add Email', 'restropress' ); ?></button>
						<span class="spinner"></span>
					</div>
					<div class="notice-container"></div>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
}

function rpress_customer_admin_render_profile_form( $customer ) {
	$customer_edit_role = apply_filters( 'rpress_edit_customers_role', 'edit_shop_payments' );
	$address            = rpress_customer_admin_get_address( $customer );
	$display_name       = ! empty( $customer->name ) ? $customer->name : $customer->email;
	$initial            = strtoupper( substr( $display_name, 0, 1 ) );
	?>
	<form id="edit-customer-info" class="rp-customer-profile-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=rpress-customers&view=overview&id=' . $customer->id ) ); ?>">
		<div class="rp-customer-hero-main rpress-item-info customer-info">
			<div class="rp-customer-identity">
				<div class="avatar-wrap rp-customer-avatar" id="customer-avatar" aria-hidden="true"><?php echo esc_html( $initial ); ?></div>
				<div class="customer-main-wrapper">
					<span class="customer-name info-item edit-item"><input size="15" class="rp-input" data-key="name" name="customerinfo[name]" type="text" value="<?php echo esc_attr( $customer->name ); ?>" placeholder="<?php esc_attr_e( 'Customer Name', 'restropress' ); ?>" /></span>
					<h2 class="customer-name info-item editable rp-customer-name"><span data-key="name"><?php echo esc_html( $customer->name ); ?></span></h2>
					<span class="customer-name info-item edit-item"><input size="20" class="rp-input" data-key="email" name="customerinfo[email]" type="text" value="<?php echo esc_attr( $customer->email ); ?>" placeholder="<?php esc_attr_e( 'Customer Email', 'restropress' ); ?>" /></span>
					<span class="customer-email info-item editable" data-key="email"><?php echo esc_html( $customer->email ); ?></span>
					<span class="customer-since info-item">
						<?php
						printf(
							/* translators: %s: customer creation date. */
							esc_html__( 'Customer since %s', 'restropress' ),
							esc_html( date_i18n( get_option( 'date_format' ), strtotime( $customer->date_created ) ) )
						);
						?>
					</span>
					<span class="customer-user-id info-item edit-item">
						<?php
						$user_id   = $customer->user_id > 0 ? $customer->user_id : '';
						$user_args = array(
							'name'  => 'customerinfo[user_login]',
							'class' => 'rpress-user-dropdown',
							'data'  => array( 'key' => 'user_login', 'exclude' => $user_id ),
						);
						if ( ! empty( $user_id ) ) {
							$userdata = get_userdata( $user_id );
							if ( $userdata ) {
								$user_args['value'] = $userdata->user_login;
							}
						}
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- RestroPress helper returns the complete admin user-search field markup.
						echo RPRESS()->html->ajax_user_search( $user_args );
						?>
						<input type="hidden" name="customerinfo[user_id]" data-key="user_id" value="<?php echo esc_attr( $customer->user_id ); ?>" />
					</span>
					<span class="customer-user-id info-item editable">
						<?php esc_html_e( 'Linked WP User', 'restropress' ); ?>:
						<?php if ( intval( $customer->user_id ) > 0 ) : ?>
							<span data-key="user_id"><a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $customer->user_id ) ); ?>">#<?php echo esc_html( $customer->user_id ); ?></a></span>
						<?php else : ?>
							<span data-key="user_id"><?php esc_html_e( 'None', 'restropress' ); ?></span>
						<?php endif; ?>
						<?php if ( current_user_can( $customer_edit_role ) && intval( $customer->user_id ) > 0 ) : ?>
							<span class="disconnect-user"> - <a id="disconnect-customer" href="#disconnect"><?php esc_html_e( 'Disconnect User', 'restropress' ); ?></a></span>
						<?php endif; ?>
					</span>
				</div>
			</div>
			<div class="rp-customer-actions">
				<span class="customer-id rp-status-badge">#<?php echo esc_html( absint( $customer->id ) ); ?></span>
				<?php if ( current_user_can( $customer_edit_role ) ) : ?>
					<span class="info-item editable customer-edit-link"><a href="#" id="edit-customer" class="button button-secondary rp-btn rp-btn-secondary"><?php esc_html_e( 'Edit Customer', 'restropress' ); ?></a></span>
					<?php do_action( 'rpress_after_customer_edit_link', $customer ); ?>
				<?php endif; ?>
			</div>
		</div>
		<div class="customer-address-wrapper rp-customer-edit-address">
			<span class="customer-address info-item editable">
				<span class="info-item" data-key="line1"><?php echo esc_html( $address['line1'] ); ?></span>
				<span class="info-item" data-key="line2"><?php echo esc_html( $address['line2'] ); ?></span>
				<span class="info-item" data-key="city"><?php echo esc_html( $address['city'] ); ?></span>
				<span class="info-item" data-key="state"><?php echo esc_html( rpress_get_state_name( $address['country'], $address['state'] ) ); ?></span>
				<span class="info-item" data-key="country"><?php echo wp_kses_post( rpress_get_country_name( $address['country'] ) ); ?></span>
				<span class="info-item" data-key="zip"><?php echo esc_html( $address['zip'] ); ?></span>
			</span>
			<span class="customer-address info-item edit-item rp-grid rp-grid-2">
				<input class="info-item rp-input" type="text" data-key="line1" name="customerinfo[line1]" placeholder="<?php esc_attr_e( 'Address 1', 'restropress' ); ?>" value="<?php echo esc_attr( $address['line1'] ); ?>" />
				<input class="info-item rp-input" type="text" data-key="line2" name="customerinfo[line2]" placeholder="<?php esc_attr_e( 'Address 2', 'restropress' ); ?>" value="<?php echo esc_attr( $address['line2'] ); ?>" />
				<input class="info-item rp-input" type="text" data-key="city" name="customerinfo[city]" placeholder="<?php esc_attr_e( 'City', 'restropress' ); ?>" value="<?php echo esc_attr( $address['city'] ); ?>" />
				<?php $selected_country = wp_kses_post( $address['country'] ); ?>
				<select data-key="country" name="customerinfo[country]" id="billing_country" class="billing_country rpress-select rp-select">
					<?php foreach ( rpress_get_country_list() as $country_code => $country ) : ?>
						<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( $country_code, $selected_country ); ?>><?php echo wp_kses_post( $country ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
				$states         = rpress_get_states( $selected_country );
				$selected_state = isset( $address['state'] ) ? $address['state'] : rpress_get_shop_state();
				if ( ! empty( $states ) ) :
					?>
					<select data-key="state" name="customerinfo[state]" id="card_state" class="card_state rpress-select info-item rp-select">
						<?php foreach ( $states as $state_code => $state ) : ?>
							<option value="<?php echo esc_attr( $state_code ); ?>" <?php selected( $state_code, $selected_state ); ?>><?php echo wp_kses_post( $state ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="text" data-key="state" name="customerinfo[state]" id="card_state" class="card_state rpress-input info-item rp-input" placeholder="<?php esc_attr_e( 'State / Province', 'restropress' ); ?>" value="<?php echo esc_attr( $address['state'] ); ?>" />
				<?php endif; ?>
				<input class="info-item rp-input" type="text" data-key="zip" name="customerinfo[zip]" placeholder="<?php esc_attr_e( 'Postal', 'restropress' ); ?>" value="<?php echo esc_attr( $address['zip'] ); ?>" />
			</span>
		</div>
		<span id="customer-edit-actions" class="edit-item">
			<input type="hidden" data-key="id" name="customerinfo[id]" value="<?php echo esc_attr( $customer->id ); ?>" />
			<?php wp_nonce_field( 'edit-customer', '_wpnonce', false, true ); ?>
			<input type="hidden" name="rpress_action" value="edit-customer" />
			<input type="submit" id="rpress-edit-customer-save" class="button button-primary rp-btn rp-btn-primary" value="<?php esc_attr_e( 'Update Customer', 'restropress' ); ?>" />
			<a id="rpress-edit-customer-cancel" href="" class="button button-secondary rp-btn rp-btn-secondary"><?php esc_html_e( 'Cancel', 'restropress' ); ?></a>
		</span>
	</form>
	<?php
}

/**
 * View a customer.
 *
 * @since 1.0.0
 * @param RPRESS_Customer $customer The Customer object being displayed.
 * @return void
 */
function rpress_customers_view( $customer ) {
	$payments       = rpress_customer_admin_get_payments( $customer, 10 );
	$all_payments   = rpress_customer_admin_get_payments( $customer, 0 );
	$last_payment   = ! empty( $all_payments ) ? reset( $all_payments ) : false;
	$orders_count   = max( absint( $customer->purchase_count ), count( $all_payments ) );
	$lifetime_value = (float) $customer->purchase_value;

	if ( $lifetime_value <= 0 && ! empty( $all_payments ) ) {
		foreach ( $all_payments as $payment ) {
			$lifetime_value += (float) $payment->total;
		}
	}

	$average_order  = $orders_count > 0 ? $lifetime_value / $orders_count : 0;
	$address        = rpress_customer_admin_get_address( $customer );
	$phone          = rpress_customer_admin_get_phone( $customer, $all_payments );
	$address_string = rpress_customer_admin_format_address( $address );
	?>
	<?php do_action( 'rpress_customer_card_top', $customer ); ?>
	<div class="info-wrapper customer-section rp-card rp-customer-section rp-customer-hero">
		<?php rpress_customer_admin_render_profile_form( $customer ); ?>
	</div>
	<?php do_action( 'rpress_customer_before_stats', $customer ); ?>
	<div id="rpress-item-stats-wrapper" class="customer-stats-wrapper customer-section rp-customer-metrics rp-grid rp-grid-4">
		<div class="rp-metric-card">
			<span class="rp-metric-label"><?php esc_html_e( 'Lifetime Orders', 'restropress' ); ?></span>
			<strong class="rp-metric-value"><?php echo esc_html( absint( $orders_count ) ); ?></strong>
		</div>
		<div class="rp-metric-card">
			<span class="rp-metric-label"><?php esc_html_e( 'Lifetime Spend', 'restropress' ); ?></span>
			<strong class="rp-metric-value"><?php echo esc_html( rpress_currency_filter( rpress_format_amount( $lifetime_value ) ) ); ?></strong>
		</div>
		<div class="rp-metric-card">
			<span class="rp-metric-label"><?php esc_html_e( 'Average Order', 'restropress' ); ?></span>
			<strong class="rp-metric-value"><?php echo esc_html( rpress_currency_filter( rpress_format_amount( $average_order ) ) ); ?></strong>
		</div>
		<div class="rp-metric-card">
			<span class="rp-metric-label"><?php esc_html_e( 'Last Order', 'restropress' ); ?></span>
			<strong class="rp-metric-value"><?php echo $last_payment ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $last_payment->date ) ) ) : esc_html__( 'None', 'restropress' ); ?></strong>
		</div>
		<ul class="rp-customer-extension-stats">
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-payment-history&customer=' . $customer->id ) ); ?>"><span class="dashicons dashicons-cart" aria-hidden="true"></span><?php echo esc_html( sprintf( _n( '%d Order', '%d Orders', $orders_count, 'restropress' ), $orders_count ) ); ?></a></li>
			<?php do_action( 'rpress_customer_stats_list', $customer ); ?>
		</ul>
	</div>
	<?php do_action( 'rpress_customer_before_tables_wrapper', $customer ); ?>
	<div id="rpress-item-tables-wrapper" class="customer-tables-wrapper customer-section rp-customer-crm">
		<?php do_action( 'rpress_customer_before_tables', $customer ); ?>
		<div class="rp-grid rp-customer-main-grid">
			<div class="rp-card rp-customer-section">
				<div class="rp-card-header">
					<div>
						<h3><?php esc_html_e( 'Recent Orders', 'restropress' ); ?></h3>
						<p class="rp-card-subtitle"><?php esc_html_e( 'Latest orders tied to this customer profile.', 'restropress' ); ?></p>
					</div>
					<a class="button button-secondary rp-btn rp-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-payment-history&customer=' . $customer->id ) ); ?>"><?php esc_html_e( 'View All Orders', 'restropress' ); ?></a>
				</div>
				<?php rpress_customer_admin_render_order_table( $customer, $payments, true ); ?>
			</div>
			<div class="rp-card rp-customer-section rp-customer-contact-card">
				<h3><?php esc_html_e( 'Contact & Address', 'restropress' ); ?></h3>
				<dl class="rp-customer-contact-list">
					<div><dt><?php esc_html_e( 'Phone', 'restropress' ); ?></dt><dd><?php echo $phone ? esc_html( $phone ) : esc_html__( 'Not captured', 'restropress' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Primary Email', 'restropress' ); ?></dt><dd><?php echo esc_html( $customer->email ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Linked WP User', 'restropress' ); ?></dt><dd><?php echo $customer->user_id ? '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . $customer->user_id ) ) . '">#' . esc_html( $customer->user_id ) . '</a>' : esc_html__( 'None', 'restropress' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Known Address', 'restropress' ); ?></dt><dd><?php echo $address_string ? esc_html( $address_string ) : esc_html__( 'No saved address', 'restropress' ); ?></dd></div>
				</dl>
				<h3><?php esc_html_e( 'Contact Emails', 'restropress' ); ?></h3>
				<?php rpress_customer_admin_render_emails( $customer ); ?>
			</div>
		</div>
		<div class="rp-card rp-customer-section">
			<div class="rp-card-header">
				<div>
					<h3><?php esc_html_e( 'Menu Items Ordered', 'restropress' ); ?></h3>
					<p class="rp-card-subtitle"><?php esc_html_e( 'A quick reference for repeat order support and customer preferences.', 'restropress' ); ?></p>
				</div>
			</div>
			<?php rpress_customer_admin_render_menu_items( $customer ); ?>
		</div>
		<?php do_action( 'rpress_customer_after_tables', $customer ); ?>
	</div>
	<?php rpress_customer_notes_view( $customer ); ?>
	<?php rpress_customer_tools_view( $customer ); ?>
	<?php rpress_customers_delete_view( $customer ); ?>
	<?php do_action( 'rpress_customer_card_bottom', $customer ); ?>
	<?php
}

function rpress_customer_orders_view( $customer ) {
	$payments = rpress_customer_admin_get_payments( $customer, 0 );
	?>
	<div class="rp-card rp-customer-section">
		<div class="rp-card-header">
			<div>
				<h3><?php esc_html_e( 'Orders', 'restropress' ); ?></h3>
				<p class="rp-card-subtitle"><?php esc_html_e( 'Complete order history for this customer.', 'restropress' ); ?></p>
			</div>
			<a class="button button-secondary rp-btn rp-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-payment-history&customer=' . $customer->id ) ); ?>"><?php esc_html_e( 'Open Orders List', 'restropress' ); ?></a>
		</div>
		<?php rpress_customer_admin_render_order_table( $customer, $payments ); ?>
	</div>
	<div class="rp-card rp-customer-section">
		<h3><?php esc_html_e( 'Menu Items Ordered', 'restropress' ); ?></h3>
		<?php rpress_customer_admin_render_menu_items( $customer ); ?>
	</div>
	<?php
}
/**
 * View the notes of a customer
 *
 * @since  1.0.0
 * @param  $customer The Customer being displayed
 * @return void
 */
function rpress_customer_notes_view( $customer ) {
	$paged       = isset( $_GET['paged'] ) && is_numeric( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	$paged       = absint( $paged );
	$note_count  = $customer->get_notes_count();
	$per_page    = apply_filters( 'rpress_customer_notes_per_page', 20 );
	$total_pages = ceil( $note_count / $per_page );
	$customer_notes = $customer->get_notes( $per_page, $paged );
	?>
	<div id="rpress-item-notes-wrapper" class="rp-customer-notes-layout">
		<div class="rp-card rp-customer-section">
			<div class="rp-card-header">
				<div class="rpress-item-notes-header">
					<?php echo get_avatar( $customer->email, 30 ); ?>
					<div>
						<h3><?php esc_html_e( 'Staff Notes', 'restropress' ); ?></h3>
						<p class="rp-card-subtitle"><?php echo esc_html( $customer->name ); ?></p>
					</div>
				</div>
			</div>
			<?php if ( 1 === $paged ) : ?>
				<div class="rp-customer-note-form">
					<form id="rpress-add-customer-note" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=rpress-customers&view=notes&id=' . $customer->id ) ); ?>">
						<label class="screen-reader-text" for="customer-note"><?php esc_html_e( 'Customer note', 'restropress' ); ?></label>
						<textarea id="customer-note" name="customer_note" class="customer-note-input rp-input" rows="7" placeholder="<?php esc_attr_e( 'Add staff context, delivery preferences, allergies, or recovery notes.', 'restropress' ); ?>"></textarea>
						<input type="hidden" id="customer-id" name="customer_id" value="<?php echo esc_attr( $customer->id ); ?>" />
						<input type="hidden" name="rpress_action" value="add-customer-note" />
						<?php wp_nonce_field( 'add-customer-note', 'add_customer_note_nonce', true, true ); ?>
						<div class="rp-form-actions">
							<input id="add-customer-note" class="button button-primary rp-btn rp-btn-primary" type="submit" value="<?php esc_attr_e( 'Add Note', 'restropress' ); ?>" />
						</div>
					</form>
				</div>
			<?php endif; ?>
			<?php
			$pagination_args = array(
				'base'     => add_query_arg(
					array(
						'page'  => 'rpress-customers',
						'view'  => 'overview',
						'id'    => $customer->id,
						'paged' => '%#%',
					),
					admin_url( 'admin.php' )
				),
				'format'   => '?paged=%#%',
				'total'    => $total_pages,
				'current'  => $paged,
				'show_all' => true,
			);
			echo wp_kses_post( paginate_links( $pagination_args ) );
			?>
			<div id="rpress-customer-notes" class="rp-customer-notes-list">
				<?php if ( count( $customer_notes ) > 0 ) : ?>
					<?php foreach ( $customer_notes as $key => $note ) : ?>
						<div class="customer-note-wrapper dashboard-comment-wrap comment-item">
							<span class="note-content-wrap">
								<?php echo esc_html( stripslashes( $note ) ); ?>
							</span>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="rpress-no-customer-notes rp-empty-state rp-empty-state-compact">
						<span class="dashicons dashicons-admin-comments" aria-hidden="true"></span>
						<h3><?php esc_html_e( 'No notes yet', 'restropress' ); ?></h3>
						<p><?php esc_html_e( 'Staff notes for this customer will appear here.', 'restropress' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
			<?php echo wp_kses_post( paginate_links( $pagination_args ) ); ?>
		</div>
		<div class="rp-card rp-customer-section rp-customer-agreements">
			<h3><?php esc_html_e( 'Customer Agreements', 'restropress' ); ?></h3>
			<p class="rp-card-subtitle"><?php esc_html_e( 'Compliance timestamps retained from existing customer data.', 'restropress' ); ?></p>
		<?php
		$show_agree_to_terms   = rpress_get_option( 'show_agree_to_terms', false );
		$show_agree_to_privacy = rpress_get_option( 'show_agree_to_privacy_policy', false );
		$agreement_timestamps = $customer->get_meta( 'agree_to_terms_time', false );
		$privacy_timestamps   = $customer->get_meta( 'agree_to_privacy_time', false );
		$payments = rpress_get_payments( array(
			'output'         => 'payments',
			'post__in'       => explode( ',', $customer->payment_ids ),
			'orderby'        => 'date',
			'posts_per_page' => 1
		) );
		$last_payment_date = '';
		foreach ( $payments as $payment ) {
			if ( empty( $payment->gateway ) ) {
				continue;
			}
			// We should be using `date` here, as that is the date the button was clicked.
			$last_payment_date = strtotime( $payment->date );
			break;
		}
		if ( is_array( $agreement_timestamps ) ) {
			$agreement_timestamp = array_pop( $agreement_timestamps );
		}
		if ( is_array( $privacy_timestamps ) ) {
			$privacy_timestamp = array_pop( $privacy_timestamps );
		}
		?>
		<div class="rp-customer-agreement-list">
		<span class="customer-terms-agreement-date info-item">
			<?php esc_html_e( 'Last Agreed to Terms', 'restropress' ); ?>:
			<?php if ( ! empty( $agreement_timestamp ) ) : ?>
				<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i:s', $agreement_timestamp ) ); ?>
				<?php if ( ! empty( $agreement_timestamps ) ) : ?>
					<?php
					$tooltip_text = esc_html__( 'Previous Agreement Dates', 'restropress' ) . "\n";

					foreach ( $agreement_timestamps as $timestamp ) {
						$tooltip_text .= esc_html( date_i18n( get_option( 'date_format' ) . ' H:i:s', $timestamp ) ) . "\n";
					}
					?>
					<span class="rpress-help-tip dashicons dashicons-editor-help" title="<?php echo esc_attr( $tooltip_text ); ?>"></span>
				<?php endif; ?>
			<?php else: ?>
				<?php
				if ( empty( $last_payment_date ) ) {
					esc_html_e( 'No date found.', 'restropress' );
				} else {
					echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i:s', $last_payment_date ) );
					?>
					<span alt="f223" class="rpress-help-tip dashicons dashicons-editor-help" title="<strong><?php esc_html_e( 'Estimated Privacy Policy Date', 'restropress' ); ?></strong><br /><?php esc_html_e( 'This customer made a purchase prior to agreement dates being logged, this is the date of their last purchase. If your site was displaying the agreement checkbox at that time, this is our best estimate as to when they last agreed to your terms.', 'restropress' ); ?>"></span>
					<?php
				}
				?>
			<?php endif; ?>
		</span>
		<span class="customer-privacy-policy-date info-item">
			<?php esc_html_e( 'Last Agreed to Privacy Policy', 'restropress' ); ?>:
			<?php if ( ! empty( $privacy_timestamp ) ) : ?>
				<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i:s', $privacy_timestamp ) ); ?>
				<?php if ( ! empty( $privacy_timestamps ) ) : ?>
					<?php
					$tooltip_text = esc_html__( 'Previous Agreement Dates', 'restropress' ) . "\n";

					foreach ( $privacy_timestamps as $timestamp ) {
						$tooltip_text .= esc_html( date_i18n( get_option( 'date_format' ) . ' H:i:s', $timestamp ) ) . "\n";
					}
					?>
					<span class="rpress-help-tip dashicons dashicons-editor-help" title="<?php echo esc_attr( $tooltip_text ); ?>"></span>
				<?php endif; ?>
			<?php else: ?>
				<?php
				if ( empty( $last_payment_date ) ) {
					esc_html_e( 'No date found.', 'restropress' );
				} else {
					echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i:s', $last_payment_date ) );
					?>
					<span alt="f223" class="rpress-help-tip dashicons dashicons-editor-help" title="<strong><?php esc_html_e( 'Estimated Privacy Policy Date', 'restropress' ); ?></strong><br /><?php esc_html_e( 'This customer made a purchase prior to privacy policy dates being logged, this is the date of their last purchase. If your site was displaying the privacy policy checkbox at that time, this is our best estimate as to when they last agreed to your privacy policy.', 'restropress' ); ?>"></span>
					<?php
				}
				?>
			<?php endif; ?>
		</span>
		</div>
		</div>
	</div>
	<?php
}
function rpress_customers_delete_view( $customer ) {
	?>
	<?php do_action( 'rpress_customer_delete_top', $customer ); ?>
	<div class="info-wrapper customer-section rp-card rp-customer-section rp-danger-zone">
		<form id="delete-customer" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=rpress-customers&view=delete&id=' . $customer->id ) ); ?>">
			<div class="rp-card-header">
				<div class="rpress-item-notes-header">
					<?php echo get_avatar( $customer->email, 30 ); ?>
					<div>
						<h3><?php esc_html_e( 'Delete Customer', 'restropress' ); ?></h3>
						<p class="rp-card-subtitle"><?php echo esc_html( $customer->name ); ?></p>
					</div>
				</div>
			</div>
			<div class="customer-info delete-customer">
				<span class="delete-customer-options">
					<p class="rp-help-text"><?php esc_html_e( 'Move this customer record to trash only when the profile is no longer needed. Existing order records are preserved.', 'restropress' ); ?></p>
					<p>
						<?php echo RPRESS()->html->checkbox( array( 'name' => 'rpress-customer-delete-confirm' ) ); ?>
						<label for="rpress-customer-delete-confirm"><?php esc_html_e( 'Are you sure you want to move this customer to trash?', 'restropress' ); ?></label>
					</p>
					<?php do_action( 'rpress_customer_delete_inputs', $customer ); ?>
				</span>
				<span id="customer-edit-actions">
					<input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer->id ); ?>" />
					<?php wp_nonce_field( 'delete-customer', '_wpnonce', false, true ); ?>
					<input type="hidden" name="rpress_action" value="delete-customer" />
					<input type="submit" id="rpress-delete-customer" class="button button-primary rp-btn rp-btn-danger button-disabled" disabled="disabled" value="<?php esc_html_e( 'Move To Trash', 'restropress' ); ?>" />
				</span>
			</div>
		</form>
		</div>
	<?php
	do_action( 'rpress_customer_delete_bottom', $customer );
}
function rpress_customer_tools_view( $customer ) {
	?>
	<?php do_action( 'rpress_customer_tools_top', $customer ); ?>
	<div class="info-wrapper customer-section rp-card rp-customer-section">
		<div class="rp-card-header">
			<div class="customer-notes-header">
				<?php echo get_avatar( $customer->email, 30 ); ?>
				<div>
					<h3><?php esc_html_e( 'Tools', 'restropress' ); ?></h3>
					<p class="rp-card-subtitle"><?php echo esc_html( $customer->name ); ?></p>
				</div>
			</div>
		</div>
		<div class="rp-tool-card">
			<h4><?php esc_html_e( 'Recalculate Customer Order Stats', 'restropress' ); ?></h4>
			<p class="rpress-item-description"><?php esc_html_e( 'Use this utility to recalculate this customer’s order count and lifetime order value from existing order records.', 'restropress' ); ?></p>
			<form method="post" id="rpress-tools-recount-form" class="rpress-export-form rpress-import-export-form">
				<span>
					<?php wp_nonce_field( 'rpress_ajax_export', 'rpress_ajax_export' ); ?>
					<input type="hidden" name="rpress-export-class" data-type="recount-single-customer-stats" value="RPRESS_Tools_Recount_Single_Customer_Stats" />
					<input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer->id ); ?>" />
					<input type="submit" id="recount-stats-submit" value="<?php esc_attr_e( 'Recalculate Stats', 'restropress' ); ?>" class="button button-secondary rp-btn rp-btn-secondary"/>
					<span class="spinner"></span>
				</span>
			</form>
		</div>
	</div>
	<?php
	do_action( 'rpress_customer_tools_bottom', $customer );
}
/**
 * Display a notice on customer account if they are pending verification
 *
 * @since  1.0.0
 * @return void
 */
function rpress_verify_customer_notice( $customer ) {
	if ( ! rpress_user_pending_verification( $customer->user_id ) ) {
		return;
	}
	$url = wp_nonce_url( admin_url( 'admin.php?page=rpress-customers&view=overview&rpress_action=verify_user_admin&id=' . $customer->id ), 'rpress-verify-user' );
	echo '<div class="update error"><p>';
	esc_html_e( 'This customer\'s user account is pending verification.', 'restropress' );
	echo ' ';
	echo '<a href="' .esc_url( $url )  . '">' . esc_html__( 'Verify account.', 'restropress' ) . '</a>';
	echo "\n\n";
	echo '</p></div>';
}
add_action( 'rpress_customer_card_top', 'rpress_verify_customer_notice', 10, 1 );
