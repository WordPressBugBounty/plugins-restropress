<?php
/**
 * View Order Details
 *
 * @package     RPRESS
 * @subpackage  Admin/Payments
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since  1.0.0
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;
/**
 * View Order Details Page
 *
 * @since  1.0.0
 * @return void
 */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
	wp_die(esc_html__('Payment ID not supplied. Please try again', 'restropress'), esc_html__('Error', 'restropress'));
}
// Setup the variables
$payment_id = absint($_GET['id']);
$payment = new RPRESS_Payment($payment_id);
// Sanity check... fail if purchase ID is invalid
$payment_exists = $payment->ID;
if (empty($payment_exists)) {
	wp_die(esc_html__('The specified ID does not belong to a payment. Please try again', 'restropress'), esc_html__('Error', 'restropress'));
}
$number = $payment->number;
$payment_meta = $payment->get_meta();
$transaction_id = esc_attr($payment->transaction_id);
$cart_items = $payment->cart_details;
$trash = $payment->post_status;
$user_id = $payment->user_id;
$tax_rate = $payment->tax_rate;
$payment_date = strtotime($payment->date);
$unlimited = $payment->has_unlimited_fooditems;
$user_info = rpress_get_payment_meta_user_info($payment_id);
$address = $payment->address;
$gateway = $payment->gateway;
$currency_code = $payment->currency;
$discount_code = isset($user_info['discount']) ? $user_info['discount'] : '';
$discount_id = !empty($discount_code) ? rpress_get_discount_id_by_code($discount_code) : '';
$billing_address_values = array(
	'line1' => !empty($address['line1']) ? $address['line1'] : '',
	'line2' => !empty($address['line2']) ? $address['line2'] : '',
	'city' => !empty($address['city']) ? $address['city'] : '',
	'zip' => !empty($address['zip']) ? $address['zip'] : '',
	'country' => !empty($address['country']) ? $address['country'] : '',
	'state' => !empty($address['state']) ? $address['state'] : '',
);
$billing_address_has_values = '' !== trim(implode('', array_map('strval', $billing_address_values)));


$customer = new RPRESS_Customer($payment->customer_id);
$customer_emails = is_array($customer->emails) ? $customer->emails : array();
$order_status = rpress_get_order_status($payment_id);
$address_info = get_post_meta($payment_id, '_rpress_delivery_address', true);
$phone = !empty($payment_meta['phone']) ? $payment_meta['phone'] : (!empty($address_info['phone']) ? $address_info['phone'] : '');
$user_address_parts = array();
if ( ! empty( $address_info['address'] ) ) {
	$user_address_parts[] = sanitize_text_field( (string) $address_info['address'] );
}
if ( ! empty( $address_info['flat'] ) ) {
	$user_address_parts[] = sanitize_text_field( (string) $address_info['flat'] );
}
if ( ! empty( $address_info['city'] ) ) {
	$user_address_parts[] = sanitize_text_field( (string) $address_info['city'] );
}
if ( ! empty( $address_info['postcode'] ) ) {
	$user_address_parts[] = sanitize_text_field( (string) $address_info['postcode'] );
}
$user_address = implode( ', ', $user_address_parts );
$prefix = rpress_get_option('sequential_prefix');
$postfix = rpress_get_option('sequential_postfix');
$order_id = $prefix . $number . $postfix;
$service_type = $payment->get_meta('_rpress_delivery_type');
$service_time = $payment->get_meta('_rpress_delivery_time');
$service_date = $payment->get_meta('_rpress_delivery_date');
$order_note = $payment->get_meta('_rpress_order_note');
$discount = rpress_get_discount_price_by_payment_id($payment_id);
$payment_user_info = isset($payment_meta['user_info']) && is_array($payment_meta['user_info']) ? $payment_meta['user_info'] : array();
$customer_name = !empty($payment_user_info['first_name']) || !empty($payment_user_info['last_name'])
	? trim($payment_user_info['first_name'] . ' ' . $payment_user_info['last_name'])
	: $customer->name;
$customer_email = !empty($payment_user_info['email']) ? $payment_user_info['email'] : $customer->email;
$receipt_emails = array_filter(array_unique(array_map('sanitize_email', array_merge($customer_emails, array($customer_email)))));
$receipt_available = rpress_is_payment_complete($payment_id);
$order_title_number = rpress_get_option('enable_sequential') ? $number : $order_id;
$list_url = admin_url('admin.php?page=rpress-payment-history&view=list');
$order_status_label = function_exists('rpress_get_order_status_label') ? rpress_get_order_status_label($order_status) : ucwords(str_replace('-', ' ', $order_status));
$payment_status_label = function_exists('rpress_get_payment_status_label') ? rpress_get_payment_status_label($payment->status) : ucwords(str_replace('-', ' ', $payment->status));
if ('' === trim((string) $order_status_label) && '' !== trim((string) $order_status)) {
	$order_status_label = ucwords(str_replace(array('-', '_'), ' ', (string) $order_status));
}
if ('' === trim((string) $payment_status_label) && '' !== trim((string) $payment->status)) {
	$payment_status_label = ucwords(str_replace(array('-', '_'), ' ', (string) $payment->status));
}
$service_label = !empty($service_type) ? rpress_service_label($service_type) : __('Service', 'restropress');
$service_badge_slug = sanitize_html_class(str_replace('_', '-', strtolower((string) $service_type)));
$placed_ago = human_time_diff($payment_date, current_time('timestamp')) . ' ' . esc_html__('ago', 'restropress');
$service_date_display = !empty($service_date) ? rpress_local_date($service_date) : '';
$service_time_display = !empty($service_time) ? $service_time : __('ASAP', 'restropress');
$customer_url = !empty($customer->id) ? admin_url('admin.php?page=rpress-customers&view=overview&id=' . $customer->id) : '';
$table_id = get_post_meta($payment_id, 'rpress_dinein_table_id', true);
$service_location = '';
if ('delivery' === $service_type && !empty($user_address)) {
	$service_location = wp_strip_all_tags(apply_filters('rpress_admin_receipt_delivery_address', $user_address, $address_info));
} elseif ('pickup' === $service_type) {
	$service_location = __('At counter', 'restropress');
} elseif (!empty($table_id)) {
	$service_location = sprintf(
		/* translators: %s: table number. */
		__('Table %s', 'restropress'),
		$table_id
	);
}
$order_statuses = rpress_get_order_statuses();
$status_keys = array_keys($order_statuses);
$current_status_index = array_search($order_status, $status_keys, true);
$next_status_key = false !== $current_status_index && isset($status_keys[$current_status_index + 1]) ? $status_keys[$current_status_index + 1] : '';
$next_status_label = !empty($next_status_key) && isset($order_statuses[$next_status_key]) ? $order_statuses[$next_status_key] : __('No next step', 'restropress');
$next_action_label = '';
if ('pending' === $order_status) {
	$next_action_label = __('Accept order', 'restropress');
} elseif ('accepted' === $order_status) {
	$next_action_label = __('Start preparing', 'restropress');
} elseif ('processing' === $order_status) {
	$next_action_label = __('Mark ready', 'restropress');
} elseif ('ready' === $order_status) {
	$next_action_label = 'delivery' === $service_type ? __('Dispatch order', 'restropress') : __('Complete order', 'restropress');
} elseif ('transit' === $order_status) {
	$next_action_label = __('Complete delivery', 'restropress');
}
$fulfilment_status_keys = array('pending', 'accepted', 'processing', 'ready');
if ('delivery' === $service_type) {
	$fulfilment_status_keys[] = 'transit';
}
$fulfilment_status_keys[] = 'completed';
$fulfilment_status_keys = array_values(array_filter($fulfilment_status_keys, function ($status_key) use ($order_statuses) {
	return isset($order_statuses[$status_key]);
}));
$fulfilment_status_descriptions = array(
	'pending' => __('Order received', 'restropress'),
	'accepted' => __('Order accepted', 'restropress'),
	'processing' => __('Order is being prepared', 'restropress'),
	'ready' => __('Order ready', 'restropress'),
	'transit' => __('Out for delivery', 'restropress'),
	'completed' => __('Order completed', 'restropress'),
);
$current_fulfilment_index = array_search($order_status, $fulfilment_status_keys, true);
$payment_key_short = !empty($payment->key) ? substr($payment->key, 0, 14) . '...' : '';
$trash_order_url = wp_nonce_url(
	add_query_arg(
		array(
			'rpress-action' => 'trash_order',
			'purchase_id' => $payment_id,
		),
		admin_url('admin.php?page=rpress-payment-history')
	),
	'rpress_payment_nonce'
);
$print_available = class_exists('RPRESS_Print_Receipts')
	&& method_exists('RPRESS_Print_Receipts', 'is_print_action_available')
	&& RPRESS_Print_Receipts::is_print_action_available($payment_id);
ob_start();
do_action('rpress_view_order_details_before', $payment_id);
$order_details_notices = ob_get_clean();
ob_start();
do_action('rpress_after_order_title', $payment_id);
$order_title_extras = ob_get_clean();
?>
<div class="wrap rpress-wrap rp-admin-scope rp-order-details-page">
	<form id="rpress-edit-order-form" class="rp-order-shell" method="post">
		<div class="rp-page-header rp-card rp-order-header">
			<div class="rp-order-detail-header-main">
				<a class="rp-order-detail-back" href="<?php echo esc_url($list_url); ?>">
					<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
					<?php esc_html_e('All Orders', 'restropress'); ?>
				</a>
				<div class="rp-order-detail-title-row">
					<h1 class="rp-order-detail-title">
						<?php
						printf(
							/* translators: %s: order number. */
							esc_html__('Order #%s', 'restropress'),
							esc_html($order_title_number)
						);
						?>
					</h1>
					<span class="rp-badge rp-order-detail-badge rp-order-detail-service badge-<?php echo esc_attr($service_badge_slug); ?>">
						<?php echo esc_html($service_label); ?>
					</span>
					<span class="rp-badge rp-order-detail-badge status-<?php echo esc_attr(sanitize_html_class($order_status)); ?>">
						<?php echo esc_html($order_status_label); ?>
					</span>
					<span class="rp-badge rp-order-detail-badge payment-<?php echo esc_attr(sanitize_html_class($payment->status)); ?>">
						<span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
						<?php echo esc_html($payment_status_label); ?>
						<?php if (!empty($gateway)): ?>
							<small><?php echo esc_html(rpress_get_gateway_admin_label($gateway)); ?></small>
						<?php endif; ?>
					</span>
					<span class="rp-order-placed-time">
						<span class="dashicons dashicons-clock" aria-hidden="true"></span>
						<?php
						printf(
							/* translators: %s: relative placed time. */
							esc_html__('Placed %s', 'restropress'),
							esc_html($placed_ago)
						);
						?>
					</span>
				</div>
			</div>
			<div class="rp-order-detail-actions">
				<div style="display:none" class="print-display-area" id="print-display-area-<?php echo absint($payment_id); ?>"></div>
				<button type="button" data-payment-id="<?php echo absint($payment_id); ?>" class="button rp-btn rp-btn-secondary rp-order-detail-button rp-order-detail-print <?php echo $print_available ? 'rp_print_now' : 'is-disabled'; ?>" <?php disabled(!$print_available); ?>>
					<span class="dashicons dashicons-printer" aria-hidden="true"></span>
					<?php esc_html_e('Print', 'restropress'); ?>
				</button>
				<?php if ($receipt_available): ?>
					<a href="<?php echo esc_url(add_query_arg(array('rpress-action' => 'email_links', 'purchase_id' => $payment_id))); ?>" id="<?php echo count($receipt_emails) > 1 ? 'rpress-select-receipt-email' : 'rpress-resend-receipt'; ?>" class="button rp-btn rp-btn-secondary rp-order-detail-button rp-order-detail-receipt">
						<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
						<?php esc_html_e('Send receipt', 'restropress'); ?>
					</a>
					<?php if (count($receipt_emails) > 1): ?>
						<div class="rpress-order-resend-receipt-addresses rp-order-receipt-email-picker" style="display:none;">
							<select class="rpress-order-resend-receipt-email">
								<option value=""><?php esc_html_e('Select receipt email...', 'restropress'); ?></option>
								<?php foreach ($receipt_emails as $email): ?>
									<option value="<?php echo esc_attr(rawurlencode($email)); ?>">
										<?php echo esc_html($email); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
				<?php else: ?>
					<button type="button" class="button rp-btn rp-btn-secondary rp-order-detail-button rp-order-detail-receipt is-disabled" disabled>
						<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
						<?php esc_html_e('Send receipt', 'restropress'); ?>
					</button>
				<?php endif; ?>
				<?php if (!empty($next_action_label) && !empty($next_status_key)): ?>
					<button type="button" class="button button-primary rp-btn rp-btn-primary rp-order-next-submit" data-next-status="<?php echo esc_attr($next_status_key); ?>">
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
						<?php echo esc_html($next_action_label); ?>
					</button>
				<?php endif; ?>
				<button type="submit" class="button button-primary rp-btn rp-btn-primary rp-order-detail-save">
					<span class="dashicons dashicons-clock" aria-hidden="true"></span>
					<?php esc_html_e('Save changes', 'restropress'); ?>
				</button>
			</div>
		</div>
		<div class="rp-order-top-alert">
			<?php echo wp_kses_post($order_details_notices); ?>
			<?php echo wp_kses_post($order_title_extras); ?>
		</div>

		<div class="rp-grid rp-grid-4 rp-order-detail-summary" aria-label="<?php esc_attr_e('Order summary', 'restropress'); ?>">
			<div class="rp-card rp-order-summary-card">
				<span class="rp-order-summary-icon dashicons dashicons-admin-users" aria-hidden="true"></span>
				<div class="rp-order-summary-body">
					<div class="rp-order-summary-label">
						<span><?php esc_html_e('Customer', 'restropress'); ?></span>
					</div>
					<strong><?php echo esc_html($customer_name ? $customer_name : esc_html__('Guest', 'restropress')); ?></strong>
					<?php if (!empty($phone)): ?><small><?php echo esc_html($phone); ?></small><?php endif; ?>
				</div>
			</div>
			<div class="rp-card rp-order-summary-card">
				<span class="rp-order-summary-icon dashicons dashicons-store" aria-hidden="true"></span>
				<div class="rp-order-summary-body">
					<div class="rp-order-summary-label">
						<span><?php esc_html_e('Service', 'restropress'); ?></span>
					</div>
					<strong><?php echo esc_html($service_label); ?> <?php if ('ASAP' === strtoupper((string) $service_time_display)): ?><em><?php esc_html_e('ASAP', 'restropress'); ?></em><?php endif; ?></strong>
					<small><?php echo esc_html($service_date_display ? $service_date_display : __('No date set', 'restropress')); ?><?php echo $service_time_display ? ' · ' . esc_html($service_time_display) : ''; ?></small>
				</div>
			</div>
			<div class="rp-card rp-order-summary-card">
				<span class="rp-order-summary-icon dashicons dashicons-money-alt" aria-hidden="true"></span>
				<div class="rp-order-summary-body">
					<div class="rp-order-summary-label">
						<span><?php esc_html_e('Payment', 'restropress'); ?></span>
					</div>
					<strong><?php echo esc_html($gateway ? rpress_get_gateway_admin_label($gateway) : __('No gateway', 'restropress')); ?></strong>
					<small><?php echo esc_html($payment_status_label); ?></small>
				</div>
			</div>
			<div class="rp-card rp-order-summary-card rp-order-summary-total">
				<span class="rp-order-summary-icon dashicons dashicons-media-text" aria-hidden="true"></span>
				<div class="rp-order-summary-body">
					<div class="rp-order-summary-label">
						<span><?php esc_html_e('Total', 'restropress'); ?></span>
					</div>
					<strong><?php echo wp_kses_post(rpress_currency_filter(rpress_format_amount($payment->total), $currency_code)); ?></strong>
					<small>
						<?php
						printf(
							/* translators: %d: order item count. */
							esc_html__('%d items', 'restropress'),
							is_array($cart_items) ? count($cart_items) : 0
						);
						?>
					</small>
				</div>
			</div>
		</div>

		<?php do_action('rpress_view_order_details_form_top', $payment_id); ?>
		<div id="poststuff">
			<div id="rpress-dashboard-widgets-wrap">
					<div id="post-body" class="metabox-holder columns-2 rp-grid rp-grid-sidebar-360 rp-order-main-grid">
						<div id="postbox-container-1" class="postbox-container rp-order-side-panel">
							<div id="side-sortables" class="meta-box-sortables ui-sortable">
								<?php do_action('rpress_view_order_details_sidebar_before', $payment_id); ?>
								<div id="rpress-order-update" class="postbox rpress-order-data rp-order-section rp-order-status-panel is-editing">
									<h3 class="hndle rp-order-section-header">
										<span class="rp-order-section-heading">
											<span class="dashicons dashicons-car" aria-hidden="true"></span>
											<?php esc_html_e('Fulfilment', 'restropress'); ?>
										</span>
									</h3>
									<div class="rp-order-read rp-order-status-read">
										<div class="rp-order-status-track" aria-label="<?php esc_attr_e('Order status progress', 'restropress'); ?>">
											<?php foreach ($fulfilment_status_keys as $status_index => $status_key): ?>
												<?php
												$status_name = $order_statuses[$status_key];
												$is_current_status = $status_key === $order_status;
												$is_complete_status = false !== $current_fulfilment_index && $status_index < $current_fulfilment_index;
												$status_class = $is_current_status ? 'is-current' : ($is_complete_status ? 'is-complete' : '');
												?>
												<div class="rp-order-status-step <?php echo esc_attr($status_class); ?>">
													<span class="rp-order-status-dot" aria-hidden="true"></span>
													<span class="rp-order-status-copy">
														<strong><?php echo esc_html($status_name); ?></strong>
														<small><?php echo esc_html(isset($fulfilment_status_descriptions[$status_key]) ? $fulfilment_status_descriptions[$status_key] : $status_name); ?></small>
													</span>
													<?php if ($is_current_status): ?>
														<span class="rp-order-status-current-badge"><?php esc_html_e('Current', 'restropress'); ?></span>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										</div>
										<?php if (!empty($next_status_key) && !empty($next_action_label)): ?>
											<button type="button" class="button button-primary rp-order-fulfilment-action rp-order-next-submit" data-next-status="<?php echo esc_attr($next_status_key); ?>">
												<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
												<?php echo esc_html($next_action_label); ?>
											</button>
										<?php endif; ?>
										<?php if (!empty($order_note)): ?>
											<div class="rp-order-customer-instruction">
												<span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
												<div>
													<strong><?php esc_html_e('Customer instruction', 'restropress'); ?></strong>
													<p><?php echo esc_html($order_note); ?></p>
												</div>
											</div>
										<?php endif; ?>
									</div>
									<div class="inside">
									<div class="rpress-admin-box">
										<?php do_action('rpress_view_order_details_totals_before', $payment_id); ?>
										<div class="rpress-admin-box-inside rp-order-fulfilment-change">
											<p>
												<span
													class="label"><?php esc_html_e('Change status', 'restropress'); ?></span>
												<select name="rpress_order_status" class="medium-text">
													<?php
													// Scope to the statuses valid for this order's service type; always
													// keep the current status visible even if it is off-list (legacy).
													$dropdown_statuses = function_exists('rpress_get_order_statuses_for_service')
														? rpress_get_order_statuses_for_service($service_type)
														: rpress_get_order_statuses();
													if (!isset($dropdown_statuses[$order_status])) {
														$fallback_label = rpress_get_order_status_label($order_status);
														$dropdown_statuses[$order_status] = $fallback_label ? $fallback_label : ucfirst((string) $order_status);
													}
													foreach ($dropdown_statuses as $key => $status): ?>
														<option value="<?php echo esc_attr($key); ?>" <?php selected($order_status, $key, true); ?>>
															<?php echo esc_html($status); ?>
														</option>
													<?php endforeach; ?>
												</select>
												<?php
												$order_status_help = '<ul>';
												$order_status_help .= '<li>' . esc_html__('<strong>Pending</strong>: When the order is initially received by the restaurant.', 'restropress') . '</li>';
												$order_status_help .= '<li>' . esc_html__('<strong>Accepted</strong>: When the restaurant accepts the order.', 'restropress') . '</li>';
												$order_status_help .= '<li>' . esc_html__('<strong>Processing</strong>: When the restaurant starts preparing the food.', 'restropress') . '</li>';
												$order_status_help .= '<li>' . esc_html__('<strong>Ready</strong>: When the order has been prepared by the restaurant.', 'restropress') . '</li>';
												$order_status_help .= '<li>' . esc_html__('<strong>In Transit</strong>: When the order is out for delivery', 'restropress') . '</li>';
												$order_status_help .= '<li>' . esc_html__('<strong>Cancelled</strong>: Order has been cancelled', 'restropress') . '</li>';
												$order_status_help .= '<li>' . esc_html__('<strong>Completed</strong>: Payment has been done and the order has been completed.', 'restropress') . '</li>';
												$order_status_help .= '</ul>';
												?>
												<span alt="f223" class="rpress-help-tip dashicons dashicons-editor-help"
													title="<?php echo esc_attr($order_status_help); ?>"></span>
											</p>
										</div>
										<?php if ($payment->is_recoverable()): ?>
											<div class="rpress-admin-box-inside">
												<p>
													<span
														class="label"><?php esc_html_e('Recovery URL', 'restropress'); ?>:</span>
													<?php $recover_help = esc_html__('Pending and abandoned payments can be resumed by the customer, using this custom URL. Payments can be resumed only when they do not have a transaction ID from the gateway.', 'restropress'); ?>
													<span alt="f223" class="rpress-help-tip dashicons dashicons-editor-help"
														title="<?php echo esc_attr($recover_help); ?>"></span>
													<input type="text" class="large-text" readonly="readonly"
														value="<?php echo esc_url($payment->get_recovery_url()); ?>" />
												</p>
											</div>
										<?php endif; ?>
										<div class="rpress-admin-box-inside">
											<p>
												<span class="label"><?php esc_html_e('Date:', 'restropress'); ?></span>
												<input type="text" name="rpress-payment-date"
													value="<?php echo esc_attr(gmdate('m/d/Y', $payment_date)); ?>"
													class="medium-text rpress_datepicker" />
											</p>
										</div>
										<div class="rpress-admin-box-inside">
											<p>
												<span class="label"><?php esc_html_e('Time:', 'restropress'); ?></span>
												<input type="text" maxlength="2" name="rpress-payment-time-hour"
													value="<?php echo esc_attr(gmdate('H', $payment_date)); ?>"
													class="small-text rpress-payment-time-hour" />
												<input type="text" maxlength="2" name="rpress-payment-time-min"
													value="<?php echo esc_attr(gmdate('i', $payment_date)); ?>"
													class="small-text rpress-payment-time-min" />
											</p>
										</div>
										<div class="rpress-admin-box-inside">
											<p class="rpress-order-payment-subtotal">
												<span
													class="label"><?php esc_html_e('Subtotal', 'restropress'); ?>:</span>&nbsp;
												<span class="value"><?php echo esc_html(rpress_currency_symbol($payment->currency));
												echo esc_html(rpress_format_amount($payment->subtotal)); ?></span>
											</p>
										</div>
										<?php
										$fees = $payment->fees;
										if (!empty($fees)): ?>
											<div class="rpress-admin-box-inside">
												<p class="rpress-order-fees strong">
													<span class="label"><?php esc_html_e('Fees:', 'restropress'); ?></span>
												<ul class="rpress-payment-fees">
													<?php foreach ($fees as $fee): ?>
														<li data-fee-id="<?php echo esc_attr($fee['id']); ?>"><span
																class="fee-label"><?php echo esc_html($fee['label']) . ':</span> ' . '<span class="fee-amount" data-fee="' . esc_attr($fee['amount']) . '">' . esc_html(rpress_currency_filter($fee['amount'], $currency_code)); ?></span>
														</li>
													<?php endforeach; ?>
												</ul>
												</p>
											</div>
										<?php endif; ?>
										<?php if (!empty($discount)): ?>
											<div class="rpress-admin-box-inside">
												<p class="rpress-order-discount">
													<span class="label"><?php esc_html_e('Coupon', 'restropress');
													esc_html_e(' (' . $discount_code . ')', 'restropress'); ?>:</span>&nbsp;
													<?php echo esc_html($discount); ?>
												</p>
											</div>
										<?php endif; ?>
										<?php if (rpress_use_taxes()): ?>
											<div class="rpress-admin-box-inside">
												<p class="rpress-order-taxes">
													<span
														class="label"><?php echo esc_html(rpress_get_tax_name()); ?>:</span>
													<input name="rpress-payment-tax" class="med-text" type="text"
														value="<?php echo esc_attr(rpress_format_amount($payment->tax)); ?>" />
													<?php if (!empty($payment->tax_rate)): ?>
														<span class="rpress-tax-rate">
															<?php echo floatval($payment->tax_rate * 100); ?>%
														</span>
													<?php endif; ?>
												</p>
											</div>
										<?php endif; ?>

										<div class="rpress-admin-box-inside">
											<p class="rpress-order-payment">
												<span
													class="label"><?php esc_html_e('Total Price', 'restropress'); ?>:</span>&nbsp;
												<?php echo esc_html(rpress_currency_symbol($payment->currency)); ?>&nbsp;<input
													name="rpress-payment-total" type="text" class="med-text"
													value="<?php echo esc_attr(rpress_format_amount($payment->total)); ?>" />
											</p>
										</div>

										<div class="rpress-order-payment-recalc-totals rpress-admin-box-inside"
											style="display:none">
											<p>
												<span
													class="label"><?php esc_html_e('Recalculate Totals', 'restropress'); ?>:</span>&nbsp;
												<a href="" id="rpress-order-recalc-total"
													class="button button-secondary right"><?php esc_html_e('Recalculate', 'restropress'); ?></a>
											</p>
										</div>
										<?php do_action('rpress_view_order_details_totals_after', $payment_id); ?>
									</div><!-- /.rpress-admin-box -->
								</div><!-- /.inside -->
								<?php if ($trash == 'trash') { ?>
									<div class="rpress-order-update-box rpress-admin-box" style="display: none;">
										<?php do_action('rpress_view_order_details_update_before', $payment_id); ?>
										<div id="major-publishing-actions">
											<div id="delete-action">
												<a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('rpress-action' => 'delete_payment', 'purchase_id' => $payment_id), admin_url('admin.php?page=rpress-payment-history')), 'rpress_payment_nonce')) ?>"
													class="rpress-delete-payment rpress-delete"><?php esc_html_e('Delete Order', 'restropress'); ?></a>
											</div>
											<input type="submit" class="button button-primary right"
												value="<?php esc_attr_e('Save Order', 'restropress'); ?>" />
											<div class="clear"></div>
										</div>
										<?php do_action('rpress_view_order_details_update_after', $payment_id); ?>
									</div><!-- /.rpress-order-update-box -->
								<?php } else { ?>
									<div class="rpress-order-update-box rpress-admin-box">
										<?php do_action('rpress_view_order_details_update_before', $payment_id); ?>
										<div id="major-publishing-actions">
											<?php
											// Permanent deletion is only offered for trashed orders
											// (matching the list view); active orders use the Danger
											// zone -> Move to trash first.
											?>
											<input type="submit" class="button button-primary right"
												value="<?php esc_attr_e('Save Order', 'restropress'); ?>" />
											<div class="clear"></div>
										</div>
										<?php do_action('rpress_view_order_details_update_after', $payment_id); ?>
									</div>
								<?php } ?>
							</div><!-- /#rpress-order-data -->
								<div id="rpress-order-details" class="postbox rpress-order-data rpress-payment-info-wrap rp-order-section">
									<h3 class="hndle rp-order-section-header">
										<span class="rp-order-section-heading">
											<span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
											<?php esc_html_e('Payment', 'restropress'); ?>
										</span>
									</h3>
									<div class="rp-order-read rp-order-payment-card">
										<div class="rp-order-payment-row">
											<span><?php esc_html_e('Payment status', 'restropress'); ?></span>
											<strong>
												<select name="rpress-payment-status" class="rpress-payment-status rp-order-payment-status-select">
										<?php foreach ( rpress_get_payment_statuses() as $rp_ps_key => $rp_ps_label ) : ?>
											<option value="<?php echo esc_attr( $rp_ps_key ); ?>" <?php selected( $payment->status, $rp_ps_key, true ); ?>><?php echo esc_html( $rp_ps_label ); ?></option>
										<?php endforeach; ?>
									</select>
											</strong>
										</div>
										<div class="rp-order-payment-row">
											<span><?php esc_html_e('Gateway', 'restropress'); ?></span>
											<strong><?php echo esc_html($gateway ? rpress_get_gateway_admin_label($gateway) : __('No gateway', 'restropress')); ?></strong>
										</div>
										<div class="rp-order-payment-row">
											<span><?php esc_html_e('Transaction ID', 'restropress'); ?></span>
											<strong><?php echo $transaction_id ? esc_html(apply_filters('rpress_payment_details_transaction_id-' . $gateway, $transaction_id, $payment_id)) : esc_html__('Not available', 'restropress'); ?></strong>
										</div>
										<div class="rp-order-payment-row">
											<span><?php esc_html_e('Payment key', 'restropress'); ?></span>
											<strong class="rp-order-payment-key-value">
												<?php echo esc_html($payment_key_short ? $payment_key_short : __('Not available', 'restropress')); ?>
												<?php if (!empty($payment->key)): ?>
													<button type="button" class="button-link rp-order-copy-key" data-copy-value="<?php echo esc_attr($payment->key); ?>" aria-label="<?php esc_attr_e('Copy payment key', 'restropress'); ?>">
														<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
													</button>
												<?php endif; ?>
											</strong>
										</div>
									</div>
									<div class="inside">
									<div class="rpress-admin-box order-payment-info">
										<?php do_action('rpress_view_order_details_payment_meta_before', $payment_id); ?>
										<?php if ($gateway): ?>
											<div class="rpress-admin-box-inside">
												<p class="rpress-order-gateway">
													<span
														class="label"><?php esc_html_e('Gateway:', 'restropress'); ?></span>
													<?php echo esc_html(rpress_get_gateway_admin_label($gateway)); ?>
												</p>
											</div>
										<?php endif; ?>
										<div class="rpress-admin-box-inside">
											<p class="rpress-order-payment-key">
												<span
													class="label"><?php esc_html_e('Key:', 'restropress'); ?></span><?php echo esc_html($payment->key); ?>
											</p>
										</div>
										<div class="rpress-admin-box-inside">
											<p class="rpress-order-ip">
												<span class="label"><?php esc_html_e('IP:', 'restropress'); ?></span>
												<span>
													<?php
													echo wp_kses(
														rpress_payment_get_ip_address_url($payment_id),
														array(
															'a' => array(
																'href' => array(),
																'target' => array(),
															),
														)
													);
													?>
												</span>
											</p>
										</div>
										<?php if ($transaction_id): ?>
											<div class="rpress-admin-box-inside">
												<p class="rpress-order-tx-id">
													<span
														class="label"><?php esc_html_e('Transaction ID:', 'restropress'); ?></span>
													<span><?php echo esc_html(apply_filters('rpress_payment_details_transaction_id-' . $gateway, $transaction_id, $payment_id)); ?></span>
												</p>
											</div>
										<?php endif; ?>
										<?php do_action('rpress_view_order_details_payment_meta_after', $payment_id); ?>
										</div><!-- /.column-container -->
									</div><!-- /.inside -->
							<?php do_action('rpress_view_order_details_billing_before', $payment_id); ?>
							<?php if (rpress_show_billing_fields()): ?>
								<div id="rpress-billing-details" class="rp-order-payment-billing <?php echo $billing_address_has_values ? 'has-billing-address' : 'is-billing-collapsed'; ?>">
									<h4 class="rp-order-payment-subheading">
										<span>
											<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
											<?php esc_html_e('Billing Address', 'restropress'); ?>
										</span>
										<?php if (!$billing_address_has_values): ?>
											<button type="button" class="button-link rp-order-billing-toggle" aria-expanded="false" aria-controls="rpress-order-address">
												<span class="dashicons dashicons-edit" aria-hidden="true"></span>
												<?php esc_html_e('Add', 'restropress'); ?>
											</button>
										<?php endif; ?>
									</h4>
									<?php if (!$billing_address_has_values): ?>
										<p class="rp-order-billing-empty"><?php esc_html_e('No billing address added.', 'restropress'); ?></p>
									<?php endif; ?>
									<div class="inside rpress-clearfix" <?php echo $billing_address_has_values ? '' : 'hidden'; ?>>
										<div id="rpress-order-address">
										<div class="order-data-address">
											<div class="data column-container">
												<div class="column">
														<p>
															<strong
																class="order-data-address-line"><?php esc_html_e('Street Address Line 1', 'restropress'); ?></strong>
														<input type="text" name="rpress-payment-address[0][line1]"
															value="<?php echo esc_attr($billing_address_values['line1']); ?>"
															class="large-text" />
													</p>
														<p>
															<strong
																class="order-data-address-line"><?php esc_html_e('Street Address Line 2', 'restropress'); ?></strong>
														<input type="text" name="rpress-payment-address[0][line2]"
															value="<?php echo esc_attr($billing_address_values['line2']); ?>"
															class="large-text" />
													</p>
												</div>
												<div class="column">
														<p>
															<strong
																class="order-data-address-line"><?php echo esc_html__('City', 'restropress'); ?></strong>
														<input type="text" name="rpress-payment-address[0][city]"
															value="<?php echo esc_attr($billing_address_values['city']); ?>" class="large-text" />
													</p>
														<p>
															<strong
																class="order-data-address-line"><?php echo esc_html__('Zip / Postal Code', 'restropress'); ?></strong>
														<input type="text" name="rpress-payment-address[0][zip]"
															value="<?php echo esc_attr($billing_address_values['zip']); ?>" class="large-text" />
													</p>
												</div>
												<div class="column">
													<p id="rpress-order-address-country-wrap">
															<strong
																class="order-data-address-line"><?php echo esc_html__('Country','restropress'); ?></strong>
														<?php
														$allowed_html = array(
															'select' => array(
																'name' => true,
																'id' => true,
																'class' => true,
																'data-*' => true,
																'multiple' => true,
																'placeholder' => true,
															),
															'option' => array(
																'value' => true,
																'selected' => true,
															),
														);

														echo wp_kses(
															RPRESS()->html->select(array(
																'options' => array_map('esc_html', (array) rpress_get_country_list()),
																'name' => 'rpress-payment-address[0][country]',
																'id' => 'rpress-payment-address-country',
																'selected' => esc_attr($billing_address_values['country']),
																'show_option_all' => false,
																'show_option_none' => false,
																'chosen' => true,
																'placeholder' => esc_html__('Select a country', 'restropress'),
																'data' => array(
																	'search-type' => 'no_ajax',
																	'search-placeholder' => esc_html__('Type to search all Countries', 'restropress'),
																),
															)),
															$allowed_html
														);
														?>
													</p>
													<p id="rpress-order-address-state-wrap">
															<strong class="order-data-address-line">
																<?php echo esc_html__('State / Province', 'restropress'); ?>
															</strong>
														<?php
														$states = rpress_get_states($billing_address_values['country']);

														if (!empty($states)) {
															$states = array_map('esc_html', $states);
															$allowed_html = array(
																'select' => array(
																	'name' => true,
																	'id' => true,
																	'class' => true,
																	'data-*' => true,
																	'multiple' => true,
																	'placeholder' => true,
																),
																'option' => array(
																	'value' => true,
																	'selected' => true,
																),
															);
															echo wp_kses(
																RPRESS()->html->select(array(
																		'options' => $states,
																		'name' => 'rpress-payment-address[0][state]',
																		'id' => 'rpress-payment-address-state',
																		'selected' => esc_attr($billing_address_values['state']),
																		'show_option_all' => false,
																		'show_option_none' => false,
																		'chosen' => true,
																	'placeholder' => esc_html__('Select a state', 'restropress'),
																	'data' => array(
																		'search-type' => 'no_ajax',
																		'search-placeholder' => esc_html__('Type to search all States/Provinces', 'restropress'),
																	),
																)),
																$allowed_html
															);




														} else { ?>
															<input type="text" name="rpress-payment-address[0][state]"
																value="<?php echo esc_attr($billing_address_values['state']); ?>"
																class="large-text" />
															<?php
														} ?>
													</p>
												</div>
											</div>
										</div>
									</div><!-- /#rpress-order-address -->
										<?php do_action('rpress_payment_billing_details', $payment_id); ?>
									</div><!-- /.inside -->
								</div><!-- /#rpress-billing-details -->
							<?php endif; ?>
							<?php do_action('rpress_view_order_details_billing_after', $payment_id); ?>
								</div><!-- /#rpress-order-data -->
								<div id="rpress-order-danger-zone" class="postbox rp-order-section rp-order-danger-zone">
									<h3 class="hndle rp-order-section-header">
										<span class="rp-order-section-heading">
											<span class="dashicons dashicons-warning" aria-hidden="true"></span>
											<?php esc_html_e('Danger zone', 'restropress'); ?>
										</span>
									</h3>
									<div class="inside">
										<p><?php esc_html_e('Move this order to trash. You can restore it later from the Trash view.', 'restropress'); ?></p>
										<a href="<?php echo esc_url($trash_order_url); ?>" class="rpress-delete-payment rpress-delete rp-order-danger-delete">
											<span class="dashicons dashicons-trash" aria-hidden="true"></span>
											<?php esc_html_e('Move to trash', 'restropress'); ?>
										</a>
									</div>
								</div>
							<div id="rpress-order-logs" class="postbox rpress-order-logs">
								<h3 class="hndle">
									<span><?php esc_html_e('Logs', 'restropress'); ?></span>
								</h3>
								<div class="inside">
									<div class="rpress-admin-box">
										<div class="rpress-admin-box-inside">
											<p>
												<?php $purchase_url = admin_url('admin.php?page=rpress-payment-history&user=' . esc_attr(rpress_get_payment_user_email($payment_id))); ?>
												<a class="customer-order-logs"
													href="<?php echo esc_url($purchase_url); ?>"><?php esc_html_e('View all orders for this customer', 'restropress'); ?></a>
											</p>
										</div>
										<?php do_action('rpress_view_order_details_logs_inner', $payment_id); ?>
									</div><!-- /.column-container -->
								</div><!-- /.inside -->
							</div><!-- /#rpress-order-logs -->
							<?php do_action('rpress_view_order_details_sidebar_after', $payment_id); ?>
						</div><!-- /#side-sortables -->
					</div><!-- /#postbox-container-1 -->
					<div id="postbox-container-2" class="postbox-container rp-order-content-panel">
						<div id="rpress-customer-details" class="postbox rp-order-section" data-rp-section="customer-service">
							<h3 class="hndle rp-order-section-header">
								<span class="rp-order-section-heading">
									<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
									<?php esc_html_e('Customer & Service Details', 'restropress'); ?>
								</span>
								<button type="button" class="button-link rp-order-edit-toggle" data-rp-edit-section="customer-service">
									<span class="dashicons dashicons-edit" aria-hidden="true"></span>
									<?php esc_html_e('Edit details', 'restropress'); ?>
								</button>
							</h3>
							<div class="rp-order-read rp-grid rp-grid-2 rp-order-detail-cards">
								<div class="rp-order-detail-card">
									<h4>
										<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
										<?php esc_html_e('Customer Details', 'restropress'); ?>
									</h4>
									<div class="rp-order-detail-list">
											<div class="rp-order-detail-row">
												<span><?php esc_html_e('Name', 'restropress'); ?></span>
												<strong>
													<?php echo esc_html($customer_name ? $customer_name : __('Guest', 'restropress')); ?>
												</strong>
											</div>
											<div class="rp-order-detail-row">
												<span><?php esc_html_e('Phone', 'restropress'); ?></span>
												<strong>
													<?php echo esc_html(!empty($phone) ? $phone : __('Not provided', 'restropress')); ?>
												</strong>
											</div>
										<div class="rp-order-detail-row">
											<span><?php esc_html_e('Email', 'restropress'); ?></span>
											<strong>
												<?php if (!empty($customer_email)): ?>
													<a href="mailto:<?php echo esc_attr(sanitize_email($customer_email)); ?>"><?php echo esc_html(sanitize_email($customer_email)); ?></a>
													<?php else: ?>
														<?php esc_html_e('Not provided', 'restropress'); ?>
													<?php endif; ?>
												</strong>
											</div>
									</div>
									<?php if (!empty($customer_url)): ?>
										<a class="rp-order-detail-secondary-link" href="<?php echo esc_url($customer_url); ?>"><?php esc_html_e('View customer profile', 'restropress'); ?></a>
									<?php endif; ?>
								</div>
								<div class="rp-order-detail-card">
									<h4>
										<span class="dashicons dashicons-store" aria-hidden="true"></span>
										<?php esc_html_e('Service Details', 'restropress'); ?>
									</h4>
									<div class="rp-order-detail-list">
											<div class="rp-order-detail-row">
												<span><?php esc_html_e('Service type', 'restropress'); ?></span>
												<strong>
													<?php echo esc_html($service_label); ?>
												</strong>
											</div>
											<div class="rp-order-detail-row">
												<span><?php esc_html_e('Service date', 'restropress'); ?></span>
												<strong>
													<?php echo esc_html($service_date_display ? $service_date_display : __('No date set', 'restropress')); ?>
												</strong>
											</div>
											<div class="rp-order-detail-row">
												<span><?php esc_html_e('Service time', 'restropress'); ?></span>
												<strong>
													<?php echo esc_html($service_time_display); ?>
												</strong>
											</div>
										<div class="rp-order-detail-row">
											<span>
												<?php
												printf(
													/* translators: %s: service label. */
													esc_html__('%s details', 'restropress'),
													esc_html($service_label)
												);
												?>
												</span>
												<strong>
													<?php echo esc_html(!empty($service_location) ? $service_location : __('Not provided', 'restropress')); ?>
												</strong>
											</div>
									</div>
								</div>
							</div>
							<div class="inside rpress-clearfix rp-order-edit rp-grid rp-grid-2 rp-customer-service-edit" hidden>
								<div class="column-container customer-info rp-edit-panel rp-edit-panel-customer">
									<h4><?php esc_html_e('Customer', 'restropress'); ?></h4>
									<div class="column">
										<?php if (!empty($customer->id)): ?>
											<a href="<?php echo esc_url($customer_url); ?>"><?php echo esc_html($customer_name); ?>
												- <?php echo esc_html(sanitize_email($customer_email)); ?></a>
										<?php else: ?>
											<strong><?php esc_html_e('Guest customer', 'restropress'); ?></strong>
										<?php endif; ?>
										<input type="hidden" name="rpress-current-customer"
											value="<?php echo esc_attr($customer->id); ?>" />
										<div style="margin-top:10px; margin-bottom:10px;">
											<strong><?php esc_html_e('Phone:', 'restropress'); ?> </strong>
											<?php echo esc_html($phone); ?>
										</div>
									</div>
									<div class="column">
										<a href="#change"
											class="rpress-payment-change-customer"><?php esc_html_e('Assign to another customer', 'restropress'); ?></a>
										<a href="#new"
											class="rpress-payment-new-customer"><?php esc_html_e('New Customer', 'restropress'); ?></a>
									</div>
								</div>
								<div class="column-container change-customer rp-edit-panel rp-edit-panel-customer-select" style="display: none">
									<h4><?php esc_html_e('Assign customer', 'restropress'); ?></h4>
									<div class="column">
										<strong><?php esc_html_e('Select a customer', 'restropress'); ?></strong>
										<?php
										$args = array(
											'class' => 'rpress-payment-change-customer-input',
											'selected' => $customer->id,
											'name' => 'customer-id',
											'placeholder' => esc_html__('Type to search all Customers', 'restropress'),
										);
										// Allowed tags and attributes for wp_kses
										$allowed_html = array(
											'select' => array(
												'name' => true,
												'id' => true,
												'class' => true,
												'multiple' => true,
												'data-placeholder' => true,
												'data-search-type' => true,
												'data-search-placeholder' => true,
												'disabled' => true,
												'readonly' => true,
											),
											'option' => array(
												'value' => true,
												'selected' => true,
											),
										);

										echo wp_kses(RPRESS()->html->customer_dropdown($args), $allowed_html);
										?>
									</div>
									<div class="rp-edit-panel-footer">
										<input type="hidden" id="rpress-change-customer" name="rpress-change-customer"
											value="0" />
										<small><?php esc_html_e('Save changes to assign this order to the selected customer.', 'restropress'); ?></small>
										<a href="#cancel"
											class="rpress-payment-change-customer-cancel rpress-delete"><?php esc_html_e('Cancel', 'restropress'); ?></a>
									</div>
								</div>
								<div class="column-container new-customer rp-edit-panel rp-edit-panel-new-customer" style="display: none">
									<h4><?php esc_html_e('Create customer', 'restropress'); ?></h4>
									<div class="column">
										<strong><?php esc_html_e('Name', 'restropress'); ?></strong>
										<input type="text" name="rpress-new-customer-name" value=""
											class="medium-text" />
									</div>
									<div class="column">
										<strong><?php esc_html_e('Email', 'restropress'); ?></strong>
										<input type="email" name="rpress-new-customer-email" value=""
											class="medium-text" />
									</div>
									<div class="rp-edit-panel-footer">
										<input type="hidden" id="rpress-new-customer" name="rpress-new-customer"
											value="0" />
										<small><?php esc_html_e('Save changes to create this customer and attach the order.', 'restropress'); ?></small>
										<a href="#cancel"
											class="rpress-payment-new-customer-cancel rpress-delete"><?php esc_html_e('Cancel', 'restropress'); ?></a>
									</div>
								</div>
								<div class="column-container order-info rp-edit-panel rp-edit-panel-service">
									<h4><?php esc_html_e('Service', 'restropress'); ?></h4>
									<div class="column rp-service-edit-fields">
										<?php apply_filters('rpress_view_service_details_before', $payment_id); ?>
										<div class="rpress-delivery-details">
											<p class="rp-service-details">

												<strong><?php esc_html_e('Service type', 'restropress'); ?></strong>
												<select class="medium-text" name="rp_service_type">
													<?php
													$service_types = rpress_get_service_types();
													foreach ($service_types as $service_id => $service_label) { ?>
														<option value="<?php echo esc_attr($service_id); ?>" <?php echo selected($service_type, $service_id, true) ?>>
															<?php echo esc_html($service_label); ?>
														</option>
													<?php } ?>
												</select>
												<?php if (get_post_meta($payment_id, "rpress_dinein_table_id", true)): ?>
													&nbsp;&nbsp;<strong><?php esc_html_e('Table No: ', 'restropress'); ?></strong>
													<?php echo esc_html($table_id); ?>

												<?php endif; ?>
											</p>
										</div>
										<div class="rp-grid rp-grid-2 rp-service-date-time-row">
											<div class="rpress-delivery-details">
												<p>
													<strong><?php esc_html_e('Service date', 'restropress'); ?></strong>
													<input type="text" name="rp_service_date" value="<?php echo esc_attr($service_date_display); ?>" class="medium-text rpress_datepicker" autocomplete="off" />
												</p>
											</div>
											<?php
											$asap_option = rpress_get_option('enable_asap_option', '');
											if (!empty($service_time)): ?>
												<div class="rpress-delivery-details">
													<p class="rp-service-time">
														<strong><?php esc_html_e('Service time', 'restropress'); ?></strong>
														<select name="rp_service_time" class="medium-text">
															<?php echo esc_html(rp_get_store_service_hours($service_type, false, $service_time, $asap_option , $service_date)); ?>
														</select>
													</p>
												</div>
											<?php endif; ?>
										</div>
										<?php apply_filters('rpress_view_service_details_after', $payment_id); ?>
									</div>
									<?php if ($service_type == 'delivery'): ?>
										<div class="column">
											<div class="rpress-delivery-address">
												<h3><?php echo sprintf(
													/* translators: %s: Placeholder for the service type */
													esc_html__('%s address:', 'restropress'),
													esc_html(rpress_service_label($service_type))
												); ?></h3>
												<p><?php echo wp_kses_post( apply_filters('rpress_admin_receipt_delivery_address', $user_address, $address_info) ); ?>
												</p>
											</div>
										</div>
									<?php endif; ?>
								</div>
								<?php
								// The rpress_payment_personal_details_list hook is left here for backwards compatibility
								do_action('rpress_payment_personal_details_list', $payment_id, $payment_meta, $user_info);
								do_action('rpress_payment_view_details', $payment_id);
								?>
								<div class="rp-order-edit-actions">
									<button type="button" class="button-link rp-order-cancel-edit" data-rp-cancel-section="customer-service"><?php esc_html_e('Cancel', 'restropress'); ?></button>
								</div>
							</div><!-- /.inside -->
						</div><!-- /#rpress-customer-details -->
						<?php do_action('rpress_view_order_details_main_before', $payment_id); ?>
						<?php $column_count = rpress_use_taxes() ? 'columns-5' : 'columns-4'; ?>
						<?php
						if (is_array($cart_items)):
							$is_qty_enabled = rpress_item_quantities_enabled() ? ' item_quantity' : ''; ?>
							<div id="rpress-purchased-items"
								class="postbox rpress-edit-purchase-element rp-order-section <?php echo esc_attr($column_count); ?>" data-rp-section="items">
								<h3 class="hndle rp-order-items-title rp-order-section-header">
									<span class="rp-order-section-heading rp-order-items-heading">
										<span class="rp-order-section-heading-main">
											<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
											<?php esc_html_e('Order Items', 'restropress'); ?>
											<?php if (!empty($cart_items)): ?>
												<span class="rp-order-items-count"><?php echo esc_html(sprintf(_n('%d item', '%d items', count($cart_items), 'restropress'), count($cart_items))); ?></span>
											<?php endif; ?>
										</span>
										<span class="rp-order-items-helper"><?php esc_html_e('Click Edit to change size, add-ons, quantity, or instructions.', 'restropress'); ?></span>
									</span>
									<button type="button" class="button button-primary rp-btn rp-btn-primary rp-order-item-modal-trigger" data-rp-order-item-mode="add">
										<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
										<?php esc_html_e('Add item', 'restropress'); ?>
									</button>
								</h3>
								<div class="rp-order-read rp-order-items-read">
									<?php if (is_array($cart_items) && !empty($cart_items)): ?>
										<div class="rp-order-items-table" role="table" aria-label="<?php esc_attr_e('Order items', 'restropress'); ?>">
											<div class="rp-order-items-table-head" role="row">
												<span role="columnheader"><?php esc_html_e('Item', 'restropress'); ?></span>
												<span role="columnheader"><?php esc_html_e('Qty', 'restropress'); ?></span>
												<span role="columnheader"><?php esc_html_e('Add-ons', 'restropress'); ?></span>
												<span role="columnheader"><?php esc_html_e('Unit Price', 'restropress'); ?></span>
												<span role="columnheader"><?php esc_html_e('Subtotal', 'restropress'); ?></span>
												<span role="columnheader"><?php esc_html_e('Actions', 'restropress'); ?></span>
											</div>
											<?php foreach ($cart_items as $read_key => $cart_item): ?>
												<?php
												$read_item_id = isset($cart_item['id']) ? $cart_item['id'] : $cart_item;
												$read_fooditem = new RPRESS_Fooditem($read_item_id);
												$read_quantity = isset($cart_item['quantity']) && $cart_item['quantity'] > 0 ? absint($cart_item['quantity']) : 1;
												$read_price = isset($cart_item['item_price']) ? $cart_item['item_price'] : rpress_get_fooditem_final_price($read_item_id, $user_info, null);
												$read_price = is_numeric($read_price) ? (float) $read_price : 0;
												$read_subtotal = isset($cart_item['subtotal']) && is_numeric($cart_item['subtotal']) ? (float) $cart_item['subtotal'] : $read_price * $read_quantity;
												$read_name = !empty($read_fooditem->ID) ? $read_fooditem->get_name() : (!empty($cart_item['name']) ? $cart_item['name'] : rpress_get_label_singular());
												$read_price_id = isset($cart_item['item_number']['options']['price_id']) ? $cart_item['item_number']['options']['price_id'] : null;

												if (!empty($read_fooditem->ID) && rpress_has_variable_prices($read_item_id) && null !== $read_price_id) {
													$read_name .= ' - ' . rpress_get_price_option_name($read_item_id, $read_price_id, $payment_id);
												}

												$read_thumb = !empty($read_fooditem->ID) ? get_the_post_thumbnail_url($read_item_id, 'thumbnail') : '';
												$read_addons = array();
												$read_instruction = !empty($cart_item['instruction']) ? trim(wp_strip_all_tags((string) $cart_item['instruction'])) : '';

												if (!empty($cart_item['addon_items']) && is_array($cart_item['addon_items'])) {
													foreach ($cart_item['addon_items'] as $addon_item) {
														if (is_array($addon_item)) {
															$addon_name = !empty($addon_item['addon_item_name']) ? $addon_item['addon_item_name'] : (!empty($addon_item['name']) ? $addon_item['name'] : '');
															$addon_price = isset($addon_item['price_without_tax']) ? $addon_item['price_without_tax'] : (isset($addon_item['price']) ? $addon_item['price'] : '');
															$addon_quantity = !empty($addon_item['quantity']) ? absint($addon_item['quantity']) : 1;

															if ('' !== $addon_name) {
																$addon_label = $addon_quantity > 1 ? $addon_quantity . ' x ' . $addon_name : $addon_name;

																if ('' !== $addon_price) {
																	if (is_numeric($addon_price)) {
																		$addon_label .= ' +' . wp_strip_all_tags(rpress_currency_filter(rpress_format_amount(abs((float) $addon_price)), $currency_code));
																	} else {
																		$addon_price_label = wp_strip_all_tags((string) $addon_price);
																		if (preg_match('/-?\d+(?:\.\d+)?/', $addon_price_label, $addon_price_match)) {
																			$addon_label .= ' +' . wp_strip_all_tags(rpress_currency_filter(rpress_format_amount(abs((float) $addon_price_match[0])), $currency_code));
																		} else {
																			$addon_label .= ' +' . ltrim($addon_price_label, '-');
																		}
																	}
																}

																$read_addons[] = $addon_label;
															}
														} elseif (is_string($addon_item) && '' !== $addon_item) {
															$read_addons[] = $addon_item;
														}
													}
												}
												?>
												<div class="rp-order-item-read-row" role="row" data-rp-order-item-key="<?php echo esc_attr($read_key); ?>" data-rp-order-item-id="<?php echo esc_attr($read_item_id); ?>" data-rp-order-item-base-name="<?php echo esc_attr(!empty($read_fooditem->ID) ? $read_fooditem->get_name() : $read_name); ?>">
													<div class="rp-order-item-main" role="cell">
														<span class="rp-order-item-thumb" aria-hidden="true">
															<?php if (!empty($read_thumb)): ?>
																<img src="<?php echo esc_url($read_thumb); ?>" alt="" />
															<?php else: ?>
																<span class="rp-order-item-thumb-placeholder">
																	<span class="dashicons dashicons-store" aria-hidden="true"></span>
																</span>
															<?php endif; ?>
														</span>
														<span class="rp-order-item-copy">
															<strong><?php echo esc_html($read_name); ?></strong>
															<?php if (!empty($read_addons)): ?>
																<span class="rp-order-item-mobile-addons">
																	<?php echo esc_html(implode(', ', $read_addons)); ?>
																</span>
															<?php endif; ?>
															<?php if ('' !== $read_instruction): ?>
																<span class="rp-order-item-instruction">
																	<?php echo esc_html($read_instruction); ?>
																</span>
															<?php endif; ?>
														</span>
													</div>
													<span class="rp-order-item-qty" role="cell" data-rp-order-item-field="quantity"><?php echo esc_html($read_quantity); ?></span>
													<span class="rp-order-item-addons" role="cell">
														<?php if (!empty($read_addons)): ?>
															<?php foreach ($read_addons as $read_addon): ?>
																<span><?php echo esc_html($read_addon); ?></span>
															<?php endforeach; ?>
														<?php else: ?>
															<span class="rp-order-item-muted"><?php esc_html_e('None', 'restropress'); ?></span>
														<?php endif; ?>
													</span>
													<span class="rp-order-item-money" role="cell" data-rp-order-item-field="unit-price"><?php echo wp_kses_post(rpress_currency_filter(rpress_format_amount($read_price), $currency_code)); ?></span>
													<span class="rp-order-item-money" role="cell" data-rp-order-item-field="subtotal"><?php echo wp_kses_post(rpress_currency_filter(rpress_format_amount($read_subtotal), $currency_code)); ?></span>
													<span class="rp-order-item-actions" role="cell">
														<button type="button" class="button button-secondary rp-order-item-modal-trigger" data-rp-order-item-mode="edit" data-rp-order-item-key="<?php echo esc_attr($read_key); ?>">
															<span class="dashicons dashicons-edit" aria-hidden="true"></span>
															<?php esc_html_e('Edit', 'restropress'); ?>
														</button>
													</span>
												</div>
											<?php endforeach; ?>
											<div class="rp-order-items-totals">
												<div class="rp-order-items-totals-spacer"></div>
												<div class="rp-order-items-total-box">
													<div>
														<span><?php esc_html_e('Subtotal', 'restropress'); ?></span>
														<strong><?php echo wp_kses_post(rpress_currency_filter(rpress_format_amount($payment->subtotal), $currency_code)); ?></strong>
													</div>
													<div>
														<span><?php echo esc_html(rpress_use_taxes() ? rpress_get_tax_name() : __('Tax', 'restropress')); ?></span>
														<strong><?php echo wp_kses_post(rpress_currency_filter(rpress_format_amount($payment->tax), $currency_code)); ?></strong>
													</div>
													<div class="rp-order-items-total-final">
														<span><?php esc_html_e('Total', 'restropress'); ?></span>
														<strong><?php echo wp_kses_post(rpress_currency_filter(rpress_format_amount($payment->total), $currency_code)); ?></strong>
													</div>
												</div>
											</div>
										</div>
									<?php endif; ?>
								</div>
								<div class="rp-order-edit rp-order-items-edit" hidden>
								<div class="rp-order-items-edit-toolbar">
									<div>
										<h4><?php esc_html_e('Edit order items', 'restropress'); ?></h4>
										<p><?php esc_html_e('Update quantities, pricing, add-ons, and items. Existing RestroPress save fields are preserved.', 'restropress'); ?></p>
									</div>
									<div class="rp-order-items-edit-toolbar-actions">
										<button type="button" class="button-link rp-order-cancel-edit" data-rp-cancel-section="items"><?php esc_html_e('Cancel', 'restropress'); ?></button>
										<button type="submit" class="button button-primary"><?php esc_html_e('Save changes', 'restropress'); ?></button>
									</div>
								</div>
								<div class="rpress-purchased-items-header row header">
									<ul class="rpress-purchased-items-list-header">
										<li class="fooditem">
											<?php printf(esc_html_x('%s Purchased', 'payment details purchased item title - full screen', 'restropress'), esc_html(rp_get_label_singular())); ?>
										</li>
										<li class="item_price">
											<?php esc_html_x('Price', 'payment details purchased item price - full screen', 'restropress'); ?>
											<?php esc_html_x(' & Quantity', 'payment details purchased item quantity - full screen', 'restropress'); ?>
										</li>
										<?php if (rpress_use_taxes()): ?>
											<li class="item_tax">
												<?php esc_html_x('Tax', 'payment details purchased item tax - full screen', 'restropress'); ?>
											</li>
										<?php endif; ?>
										<?php if (!empty($discount)): ?>
											<li class="item_discount">
												<?php esc_html_x('Discount', 'payment details purchased item discount - full screen', 'restropress'); ?>
											</li>
										<?php endif; ?>
										<li class="price">
											<?php printf(esc_html_x('%s Total', 'payment details purchased item total - full screen', 'restropress'), esc_html(rp_get_label_singular())); ?>
										</li>
										<li class="item_gross_price">
											<?php printf(esc_html_x('%s Gross Total', 'payment details purchased item gross total - full screen', 'restropress'), esc_html(rp_get_label_singular())); ?>
										</li>
									</ul>
								</div>
								<div class="rp-order-items-edit-list">
								<?php
								$i = 0;
								foreach ($cart_items as $key => $cart_item):
									$item_id = isset($cart_item['id']) ? $cart_item['id'] : $cart_item;
									$fooditem = new RPRESS_Fooditem($item_id);
									$fooditem_name = !empty($fooditem->ID) ? $fooditem->get_name() : '';
									$price = isset($cart_item['price']) ? $cart_item['price'] : false;
									$item_price = isset($cart_item['item_price']) ? $cart_item['item_price'] : $price;
									$subtotal = isset($cart_item['subtotal']) ? $cart_item['subtotal'] : $price;
									$item_tax = isset($cart_item['tax']) ? $cart_item['tax'] : 0;
									$item_discount = isset($cart_item['discount']) ? $cart_item['discount'] : 0;
									$price_id = isset($cart_item['item_number']['options']['price_id']) ? $cart_item['item_number']['options']['price_id'] : null;
									$quantity = isset($cart_item['quantity']) && $cart_item['quantity'] > 0 ? $cart_item['quantity'] : 1;
									$edit_thumb = !empty($fooditem->ID) ? get_the_post_thumbnail_url($item_id, 'thumbnail') : '';
									if (false === $price) {
										// This function is only used on payments with near 1.0 cart data structure
										$price = rpress_get_fooditem_final_price($item_id, $user_info, null);
									} ?>
									<div class="row rpress-purchased-row">
										<div class="rpress-order-items-wrapper">
											<ul class="rpress-purchased-items-list-wrapper <?php echo esc_attr($key); ?>">
												<li class="fooditem">
													<span class="rp-order-edit-item-thumb" aria-hidden="true">
														<?php if (!empty($edit_thumb)): ?>
															<img src="<?php echo esc_url($edit_thumb); ?>" alt="" />
														<?php else: ?>
															<span class="rp-order-edit-item-thumb-placeholder">
																<span class="dashicons dashicons-store" aria-hidden="true"></span>
															</span>
														<?php endif; ?>
													</span>
													<span class="rpress-purchased-fooditem-actions actions">
														<input type="hidden" class="rpress-payment-details-fooditem-has-log"
															name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][has_log]"
															value="1" />
														<a href="" class="rpress-order-remove-fooditem rpress-delete"
															data-key="<?php echo esc_attr($key); ?>" aria-label="<?php esc_attr_e('Remove item', 'restropress'); ?>">
															<span class="dashicons dashicons-trash" aria-hidden="true"></span>
														</a>
													</span>
													<span class="rpress-purchased-fooditem-title">
														<?php if (!empty($fooditem->ID)): ?>
															<a
																href="<?php echo esc_url(admin_url('post.php?post=' . $item_id . '&action=edit')); ?>">
																<?php echo esc_html($fooditem->get_name());
																if (isset($cart_items[$key]['item_number']) && isset($cart_items[$key]['item_number']['options'])) {
																	$price_options = $cart_items[$key]['item_number']['options'];
																	if (rpress_has_variable_prices($item_id) && isset($price_id)) {
																		echo ' - ' . esc_html(rpress_get_price_option_name($item_id, $price_id, $payment_id));
																	}
																} ?>
															</a>
														<?php else: ?>
															<span class="deleted">
																<?php if (!empty($cart_item['name'])): ?>
																	<?php echo esc_html($cart_item['name']); ?>&nbsp;-&nbsp;
																	<em>(<?php esc_html_e('Deleted', 'restropress'); ?>)</em>
																<?php else: ?>
																	<em><?php printf(esc_html__('%s deleted', 'restropress'), esc_html(rpress_get_label_singular())); ?></em>
																<?php endif; ?>
															</span>
														<?php endif; ?>
													</span>
													<input type="hidden"
														name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][id]"
														class="rpress-payment-details-fooditem-id"
														value="<?php echo esc_attr($item_id); ?>" />
													<input type="hidden"
														name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][price_id]"
														class="rpress-payment-details-fooditem-price-id"
														value="<?php echo esc_attr($price_id); ?>" />
													<input type="hidden"
														name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][instruction]"
														class="rpress-payment-details-fooditem-instruction"
														value="<?php echo isset($cart_items[$key]['instruction']) ? esc_attr($cart_items[$key]['instruction']) : ''; ?>" />
													<input type="hidden"
														name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][quantity]"
														class="rpress-payment-details-fooditem-quantity"
														value="<?php echo esc_attr($quantity); ?>" />
													<?php if (!rpress_use_taxes()): ?>
														<input type="hidden"
															name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][item_tax]"
															class="rpress-payment-details-fooditem-item-tax"
															value="<?php echo esc_attr($item_tax); ?>" />
													<?php endif; ?>
													<?php if (!empty($cart_items[$key]['fees'])):
														$fees = array_keys($cart_items[$key]['fees']); ?>
														<input type="hidden"
															name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][fees]"
															class="rpress-payment-details-fooditem-fees"
															value="<?php echo esc_attr(wp_json_encode($fees)); ?>" />
													<?php endif; ?>
												</li>
												<li class="item_price">
													<span class="rpres-order-price-wrap">
														<span class="rpress-payment-details-label-mobile">
															<?php esc_html_x('Price', 'payment details purchased item price - mobile', 'restropress'); ?>
														</span>
														<?php echo esc_html(rpress_currency_symbol($currency_code)); ?>
														<input type="text"
															class="rpress-order-input medium-text rpress-price-field rpress-payment-details-fooditem-item-price rpress-payment-item-input"
															name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][item_price]"
															value="<?php echo esc_attr(rpress_format_amount($item_price)); ?>" />
													</span>
													<span class="rpres-order-quantity-wrap">
														<span class="rpress-payment-details-label-mobile">
															<?php esc_html_x('Quantity', 'payment details purchased item quantity - mobile', 'restropress'); ?>
														</span>
														<input type="number"
															name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][quantity]"
															class="small-text rpress-payment-details-fooditem-quantity rpress-payment-item-input rpress-order-input"
															min="1" step="1" value="<?php echo esc_attr($quantity); ?>" />
													</span>
												</li>
												<?php if (rpress_use_taxes()): ?>
													<li class="item_tax">
														<span
															class="rpress-payment-details-label-mobile"><?php echo esc_html(rpress_get_tax_name()); ?></span>
														<?php echo esc_html(rpress_currency_symbol($currency_code)); ?>
														<input type="text"
															class="small-text rpress-price-field rpress-payment-details-fooditem-item-tax rpress-payment-item-input rpress-order-input"
															name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][item_tax]"
															value="<?php echo esc_attr(rpress_format_amount($item_tax)); ?>" />
													</li>
												<?php endif; ?>
												<?php if (!empty($discount)): ?>
													<li class="item_discount">
														<span
															class="rpress-payment-details-label-mobile"><?php echo esc_html($discount_code); ?></span>
														<?php echo esc_html(rpress_currency_symbol($currency_code)); ?>
														<input type="text"
															class="small-text rpress-price-field rpress-payment-details-fooditem-item-discount rpress-payment-item-input rpress-order-input"
															name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][item_discount]"
															value="<?php echo esc_attr(rpress_format_amount($item_discount)); ?>" />
													</li>
												<?php endif; ?>


												<li class="price">
													<span class="rpress-payment-details-label-mobile">
														<?php esc_html_e('Subtotal', 'restropress'); ?>
													</span>
													<span
														class="rpress-price-currency"><?php echo esc_html(rpress_currency_symbol($currency_code)); ?></span>
													<span
														class="price-text rpress-payment-details-fooditem-amount"><?php echo esc_html(rpress_format_amount($item_price * $quantity)); ?></span>
													<input type="hidden"
														name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][amount]"
														class="rpress-payment-details-fooditem-amount"
														value="<?php echo esc_attr($item_price * $quantity); ?>" />
												</li>
												<li class="item_gross_price">
													<span class="rpress-payment-details-label-mobile">
														<?php printf(esc_html_x('%s Gross Total Price', 'payment details purchased item total - mobile', 'restropress'), esc_html(rpress_get_label_singular())); ?>
													</span>
													<span
														class="rpress-price-currency"><?php echo esc_html(rpress_currency_symbol($currency_code)); ?></span>
													<span
														class="price-text rpress-payment-details-fooditem-gross-amount"><?php echo esc_html(rpress_format_amount($price)); ?></span>
													<input type="hidden"
														name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][gross_amount]"
														class="rpress-payment-details-fooditem-gross-amount"
														value="<?php echo esc_attr($item_price); ?>" />
												</li>
											</ul>
											<!-- Addon Items Starts Here -->
											<div class="rpress-addon-items">
												<?php if (!empty($fooditem->ID)): ?>
													<span class="order-addon-items">
														<?php esc_html_e('Addon Items', 'restropress'); ?>
													</span>
													<div class="food-item-list">
														<select multiple class="addon-items-list"
															name="rpress-payment-details-fooditems[<?php echo esc_attr($key); ?>][addon_items][]">
															<?php
															$addons = get_post_meta($fooditem->ID, '_addon_items', array());

															if (is_array($addons) && !empty($addons)):
																foreach ($addons as $addon_items):
																	if (is_array($addon_items)):
																		foreach ($addon_items as $addon_key => $addon_item):
																			$addon_id = isset($addon_item['category']) ? $addon_item['category'] : '';
																			$add_ps = isset($addon_item['prices']) ? $addon_item['prices'] : array();

																			$get_addons = rpress_get_addons($addon_id);
																			if (is_array($get_addons) && !empty($get_addons)):
																				foreach ($get_addons as $get_addon):

																					$addon_item_id = $get_addon->term_id;
																					$addon_item_name = $get_addon->name;

																					$addon_slug = $get_addon->slug;
																					$selected_addon_items = isset($cart_item['addon_items']) ? $cart_item['addon_items'] : array();
																					if (!empty($selected_addon_items)) {
																						foreach ($selected_addon_items as $selected_addon_item) {
																							$selected_addon_id = !empty($selected_addon_item['addon_id']) ? $selected_addon_item['addon_id'] : '';
																							$item_addon_quantity = !empty($selected_addon_item['quantity']) ? $selected_addon_item['quantity'] : 1;

																							if ($selected_addon_id == $addon_item_id) {
																								$addon_price = !empty($selected_addon_item['price_without_tax'])
																									? rpress_currency_filter(rpress_format_amount($selected_addon_item['price_without_tax']))
																									: (!empty($selected_addon_item['price'])
																										? rpress_currency_filter(rpress_format_amount($selected_addon_item['price']))
																										: '');

																								?>
																								<option selected data-price="<?php echo esc_attr($addon_price); ?>"
																									data-id="<?php echo esc_attr($addon_item_id); ?>"
																									value="<?php echo esc_attr($addon_item_name) . '|' . esc_attr($addon_item_id) . '|' . esc_attr($addon_price) . '|' . '1'; ?>">
																									<?php
																									if (class_exists('Rpress_addon_quantity_Admin')) {
																										if (!empty($item_addon_quantity)) ?>
																										<small><?php echo esc_html($item_addon_quantity) . " x "; ?></small>
																									<?php } ?> 												<?php
																																					   echo esc_html($addon_item_name);
																																					   if (!empty($addon_price))
																																						   echo ' (' . esc_html($addon_price) . ')';
																																					   ?>
																								</option> <?php
																							} else {
																								$addon_price = '';
																							}
																						}
																					} ?>
																					<option data-price="<?php echo esc_attr($addon_price); ?>"
																						data-id="<?php echo esc_attr($addon_item_id); ?>"
																						value="<?php echo esc_attr($addon_item_name) . '|' . esc_attr($addon_item_id) . '|' . esc_attr($addon_price) . '|' . esc_attr($item_addon_quantity); ?>">
																						<?php if (!empty($item_addon_quantity))
																							echo esc_html($item_addon_quantity) . " x "; ?>
																						<?php echo esc_html($addon_item_name);
																						if (!empty($addon_price))
																							echo ' (' . esc_html($addon_price) . ')'; ?>
																					</option>
																				<?php endforeach;
																			endif;
																		endforeach;
																	endif;
																endforeach;
															endif; ?>
														</select>
													</div>
												<?php endif; ?>
											</div> <!-- end of addon items-->
											<!-- Addon Items Ends Here -->
											<div class="clear"></div>
											<?php
											if (isset($cart_items[$key]['instruction']) && !empty($cart_items[$key]['instruction'])): ?>
												<div class="rpress-special-instruction">
													<span class="special-instruction-label">
														<?php esc_html_e('Special Instruction:', 'restropress'); ?>
													</span>
													<?php echo esc_html($cart_items[$key]['instruction']); ?>
												</div> <!-- //end of special instruction-->
											<?php endif; ?>
										</div>
									</div>
									<?php $i++;
								endforeach; ?>
								</div>
							</div>
						<?php else:
							$key = 0; ?>
							<div class="row">
								<p><?php printf(esc_html__('No %s included with this purchase', 'restropress'), esc_html(rp_get_label_plural())); ?>
								</p>
							</div>
						<?php endif; ?>
							<div
								class="postbox rpress-edit-purchase-element rp-add-update-elements rp-order-add-item-card <?php echo esc_attr($column_count); ?>">
							<div class="rpress-add-fooditem-to-purchase-header row header">
								<ul class="rpress-purchased-items-list-wrapper">
									<li class="fooditem">
										<?php printf(esc_html__('Add %s', 'restropress'), esc_html(rpress_get_label_singular())); ?>
									</li>
									<li class="item_price<?php echo esc_attr($is_qty_enabled); ?>">
										<?php esc_html_e('Price', 'restropress'); ?>
										<?php esc_html_e(' & Quantity', 'restropress'); ?>
									</li>
									<?php if (rpress_use_taxes()): ?>
										<li class="item_tax">
											<?php esc_html_e('Tax', 'restropress'); ?>
										</li>
									<?php endif; ?>
									<li class="price"><?php esc_html_e('Actions', 'restropress'); ?></li>
								</ul>
							</div>
							<div class="rpress-add-fooditem-to-purchase aa inside">
								<ul>
									<li class="fooditem">
										<span class="rpress-payment-details-label-mobile">
											<?php esc_html_e('Menu item', 'restropress'); ?>
										</span>
										<?php
										$allowed_html = array(
											'span' => array('class' => true),
											'strong' => array(),
											'em' => array(),
											'br' => array(),
											'select' => array(
												'name' => true,
												'id' => true,
												'class' => true,
												'data-*' => true,
												'multiple' => true,
												'placeholder' => true,
											),
											'option' => array(
												'value' => true,
												'selected' => true,
											),
										);

										echo wp_kses(
											RPRESS()->html->product_dropdown(array(
												'name' => 'rpress-order-fooditem-select',
												'id' => 'rpress-order-fooditem-select',
												'chosen' => true
											)),
											$allowed_html
										); ?>
									</li>
									<li class="item_price<?php echo esc_attr($is_qty_enabled); ?>">
										<span class="rpress-payment-details-label-mobile">
											<?php
											esc_html_x('Price', 'payment details add item price - mobile', 'restropress');
											esc_html_x(' & Quantity', 'payment details add item quantity - mobile', 'restropress'); ?>
										</span>
										<span class="rpress-fooditem-to-purchase-wrapper">
											<span class="rpress-get-variable-prices"></span>
											<span class="rpress-fooditem-price"></span>
										</span>
										<span>&nbsp;&times;&nbsp;</span>
										<input type="number" id="rpress-order-fooditem-quantity"
											name="rpress-order-fooditem-quantity"
											class="small-text rpress-add-fooditem-field rpress-order-input" min="1"
											step="1" value="1" />
									</li>
									<?php if (rpress_use_taxes()): ?>
										<li class="item_tax">
											<span class="rpress-payment-details-label-mobile">
												<?php esc_html_x('Tax', 'payment details add item tax - mobile', 'restropress'); ?>
											</span>
											<?php
											echo esc_html(rpress_currency_symbol($currency_code)) . '&nbsp;';
											$allowed_html = array(
												'input' => array(
													'type' => true,
													'name' => true,
													'id' => true,
													'class' => true,
													'value' => true,
													'placeholder' => true,
													'checked' => true,
													'readonly' => true,
													'disabled' => true,
													'data-*' => true,
												),
											);

											echo wp_kses(
												RPRESS()->html->text(
													array(
														'name' => 'rpress-order-fooditem-tax',
														'id' => 'rpress-order-fooditem-tax',
														'class' => 'small-text rpress-order-fooditem-tax rpress-add-fooditem-field rpress-order-input',
													)
												),
												$allowed_html
											);
											?>
										</li>
									<?php endif; ?>
									<li class="rpress-add-fooditem-to-purchase-actions actions">
										<span class="rpress-payment-details-label-mobile">
											<?php esc_html_e('Actions', 'restropress'); ?>
										</span>
										<a href="" id="rpress-order-add-fooditem"
											class="button button-secondary"><?php esc_html_e('Add item', 'restropress'); ?></a>
									</li>
								</ul>
								<input type="hidden" name="rpress-payment-fooditems-changed"
									id="rpress-payment-fooditems-changed" value="" />
								<input type="hidden" name="rpress-payment-removed" id="rpress-payment-removed"
									value="{}" />
								<input type="hidden" id="rpress-order-fooditem-quantity"
									name="rpress-order-fooditem-quantity" value="1" />
								<?php if (!rpress_use_taxes()): ?>
									<input type="hidden" id="rpress-order-fooditem-tax" name="rpress-order-fooditem-tax"
										value="0" />
								<?php endif; ?>
								</div><!-- /.inside -->
							</div>
							<div class="rp-order-edit-actions">
								<button type="button" class="button-link rp-order-cancel-edit" data-rp-cancel-section="items"><?php esc_html_e('Cancel', 'restropress'); ?></button>
							</div>
								</div><!-- /.rp-order-edit -->
								<?php
								$rp_modal_fooditems = get_posts(
									array(
										'post_type'      => 'fooditem',
										'post_status'    => 'publish',
										'posts_per_page' => 100,
										'orderby'        => 'title',
										'order'          => 'ASC',
									)
								);
								?>
								<div class="rp-order-item-modal" id="rp-order-item-modal" hidden aria-hidden="true" data-currency-symbol="<?php echo esc_attr(rpress_currency_symbol($currency_code)); ?>">
									<div class="rp-order-item-modal__overlay" data-rp-order-item-close></div>
									<div class="rp-order-item-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="rp-order-item-modal-title">
										<button type="button" class="rp-order-item-modal__close" data-rp-order-item-close aria-label="<?php esc_attr_e('Close item editor', 'restropress'); ?>">
											<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
										</button>
										<div class="rp-order-item-modal__media">
											<span class="rp-order-item-modal__thumb" aria-hidden="true">
												<span class="dashicons dashicons-store" aria-hidden="true"></span>
											</span>
										</div>
										<div class="rp-order-item-modal__content">
											<div class="rp-order-item-modal__header">
												<p class="rp-order-item-modal__eyebrow"><?php esc_html_e('Order item', 'restropress'); ?></p>
												<h2 id="rp-order-item-modal-title"><?php esc_html_e('Edit item', 'restropress'); ?></h2>
												<p class="rp-order-item-modal__help"><?php esc_html_e('Changes apply to this order, then save the order.', 'restropress'); ?></p>
											</div>
											<div class="rp-order-item-modal__add-field" hidden>
												<label for="rp-order-item-modal-fooditem"><?php esc_html_e('Menu item', 'restropress'); ?></label>
												<select id="rp-order-item-modal-fooditem" class="rp-order-item-modal__select rpress-select-chosen" data-placeholder="<?php esc_attr_e('Choose a menu item', 'restropress'); ?>" data-search-placeholder="<?php esc_attr_e('Search menu items...', 'restropress'); ?>">
													<option value=""><?php esc_html_e('Choose an item', 'restropress'); ?></option>
													<?php foreach ($rp_modal_fooditems as $rp_modal_fooditem): ?>
														<?php
														$rp_modal_item_id = absint($rp_modal_fooditem->ID);
														$rp_modal_price_id = '';
														$rp_modal_price = rpress_get_fooditem_price($rp_modal_item_id);
														$rp_modal_prices = array();
														$rp_modal_variation_label = __('Variation', 'restropress');
														if (rpress_has_variable_prices($rp_modal_item_id)) {
															$rp_modal_price_id = rpress_get_default_variable_price($rp_modal_item_id);
															$rp_modal_price = rpress_get_price_option_amount($rp_modal_item_id, $rp_modal_price_id);
															$rp_modal_variation_label = get_post_meta($rp_modal_item_id, 'rpress_variable_price_label', true);
															$rp_modal_variation_label = !empty($rp_modal_variation_label) ? $rp_modal_variation_label : __('Variation', 'restropress');
															foreach ((array) rpress_get_variable_prices($rp_modal_item_id) as $rp_price_key => $rp_price) {
																if (!is_array($rp_price) || !isset($rp_price['amount'])) {
																	continue;
																}
																$rp_modal_prices[] = array(
																	'id'     => (string) $rp_price_key,
																	'name'   => isset($rp_price['name']) ? (string) $rp_price['name'] : sprintf(__('Option %s', 'restropress'), $rp_price_key),
																	'amount' => rpress_format_amount($rp_price['amount']),
																);
															}
														}
														$rp_modal_thumb = get_the_post_thumbnail_url($rp_modal_item_id, 'thumbnail');
														?>
														<option value="<?php echo esc_attr($rp_modal_item_id); ?>"
															data-price="<?php echo esc_attr(rpress_format_amount($rp_modal_price)); ?>"
															data-price-id="<?php echo esc_attr($rp_modal_price_id); ?>"
															data-variation-label="<?php echo esc_attr($rp_modal_variation_label); ?>"
															data-prices="<?php echo esc_attr(wp_json_encode($rp_modal_prices)); ?>"
															data-thumb="<?php echo esc_url($rp_modal_thumb); ?>">
															<?php echo esc_html(get_the_title($rp_modal_item_id)); ?>
														</option>
													<?php endforeach; ?>
												</select>
											</div>
											<div class="rp-order-item-modal__field rp-order-item-modal__variation-field" hidden>
												<span class="rp-order-item-modal__variation-label"><?php esc_html_e('Variation', 'restropress'); ?></span>
												<div class="rp-order-item-modal__variation-options" id="rp-order-item-modal-variation" role="radiogroup" aria-label="<?php esc_attr_e('Variation', 'restropress'); ?>"></div>
											</div>
											<div class="rp-order-item-modal__controls">
												<div class="rp-order-item-modal__field">
													<label for="rp-order-item-modal-price"><?php esc_html_e('Unit price', 'restropress'); ?></label>
													<div class="rp-order-item-modal__money">
														<span><?php echo esc_html(rpress_currency_symbol($currency_code)); ?></span>
														<input type="text" id="rp-order-item-modal-price" inputmode="decimal" />
													</div>
												</div>
												<div class="rp-order-item-modal__field">
													<label for="rp-order-item-modal-qty"><?php esc_html_e('Quantity', 'restropress'); ?></label>
													<div class="rp-order-item-modal__qty">
														<button type="button" data-rp-order-item-qty="-1">-</button>
														<input type="number" id="rp-order-item-modal-qty" min="1" step="1" value="1" />
														<button type="button" data-rp-order-item-qty="1">+</button>
													</div>
												</div>
											</div>
											<div class="rp-order-item-modal__addons">
												<div class="rp-order-item-modal__group-title">
													<strong><?php esc_html_e('Add-ons', 'restropress'); ?></strong>
													<span><?php esc_html_e('Choose modifiers for this item.', 'restropress'); ?></span>
												</div>
												<div class="rp-order-item-modal__addon-list"></div>
											</div>
											<div class="rp-order-item-modal__field">
												<label for="rp-order-item-modal-instruction"><?php esc_html_e('Item instructions', 'restropress'); ?></label>
												<textarea id="rp-order-item-modal-instruction" rows="3" placeholder="<?php esc_attr_e('Example: no onions, extra spicy...', 'restropress'); ?>"></textarea>
											</div>
											<div class="rp-order-item-modal__summary">
												<span><?php esc_html_e('Item subtotal', 'restropress'); ?></span>
												<strong id="rp-order-item-modal-subtotal"><?php echo esc_html(rpress_currency_filter(rpress_format_amount(0), $currency_code)); ?></strong>
											</div>
											<div class="rp-order-item-modal__footer">
												<button type="button" class="button button-secondary" data-rp-order-item-close><?php esc_html_e('Cancel', 'restropress'); ?></button>
												<button type="button" class="button button-primary" id="rp-order-item-modal-apply"><?php esc_html_e('Apply changes', 'restropress'); ?></button>
											</div>
										</div>
									</div>
								</div>
								<?php do_action('rpress_view_order_details_files_after', $payment_id); ?>
							<div id="rpress-payment-notes" class="postbox rp-order-section" data-rp-section="notes">
								<h3 class="hndle rp-order-section-header">
									<span class="rp-order-section-heading">
										<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
										<?php esc_html_e('Order Notes', 'restropress'); ?>
									</span>
								</h3>
								<div class="inside rp-order-notes-layout">
									<div id="rpress-payment-notes-inner" class="rp-order-notes-timeline">
										<?php
										$notes = rpress_get_payment_notes($payment_id);
										if (!empty($notes)):
											foreach ($notes as $note):
												$note_user = __('RPRESS Bot', 'restropress');
												if (!empty($note->user_id)) {
													$note_user_data = get_userdata($note->user_id);
													if (!empty($note_user_data->display_name)) {
														$note_user = $note_user_data->display_name;
													}
												}
												$note_time = strtotime($note->comment_date);
												$note_relative = human_time_diff($note_time, current_time('timestamp')) . ' ' . __('ago', 'restropress');
												$delete_note_url = wp_nonce_url(
													add_query_arg(
														array(
															'rpress-action' => 'delete_payment_note',
															'note_id' => $note->comment_ID,
															'payment_id' => $payment_id,
														)
													),
													'rpress_delete_payment_note_' . $note->comment_ID
												);
												?>
												<div class="rpress-payment-note rp-order-note-entry" id="rpress-payment-note-<?php echo absint($note->comment_ID); ?>">
													<span class="rp-order-note-dot" aria-hidden="true"></span>
													<div class="rp-order-note-body">
														<div class="rp-order-note-meta">
															<strong><?php echo esc_html($note_relative); ?></strong>
															<span><?php echo esc_html($note_user); ?></span>
														</div>
														<div class="rp-order-note-content">
															<?php echo wp_kses_post(make_clickable($note->comment_content)); ?>
														</div>
														<a href="<?php echo esc_url($delete_note_url); ?>" class="rpress-delete-payment-note rp-order-note-delete" data-note-id="<?php echo absint($note->comment_ID); ?>" data-payment-id="<?php echo absint($payment_id); ?>"><?php esc_html_e('Delete', 'restropress'); ?></a>
													</div>
												</div>
											<?php endforeach;
										else:
										endif;
										?>
										<p class="rpress-no-payment-notes rp-order-notes-empty" <?php echo !empty($notes) ? 'hidden' : ''; ?>><?php esc_html_e('No order notes yet.', 'restropress'); ?></p>
									</div>
									<div class="rp-order-note-composer">
										<label for="rpress-payment-note"><?php esc_html_e('Add internal note', 'restropress'); ?></label>
										<textarea name="rpress-payment-note" id="rpress-payment-note" class="large-text" placeholder="<?php esc_attr_e('Type your note here...', 'restropress'); ?>"></textarea>
										<button id="rpress-add-payment-note" class="button button-primary" data-payment-id="<?php echo absint($payment_id); ?>"><?php esc_html_e('Add note', 'restropress'); ?></button>
									</div>
								</div><!-- /.inside -->
							</div><!-- /#rpress-payment-notes -->
							<?php do_action('rpress_view_order_details_main_after', $payment_id); ?>
					</div><!-- #postbox-container-2 -->
				</div><!-- /#post-body -->
			</div><!-- #rpress-dashboard-widgets-wrap -->
		</div><!-- /#post-stuff -->
		<?php do_action('rpress_view_order_details_form_bottom', $payment_id); ?>
		<?php wp_nonce_field('rpress_update_payment_details_nonce'); ?>
		<input type="hidden" name="rpress_payment_id" value="<?php echo esc_attr($payment_id); ?>" />
		<input type="hidden" name="rpress_action" value="update_payment_details" />
	</form>
	<?php do_action('rpress_view_order_details_after', $payment_id); ?>
</div><!-- /.wrap -->
<div id="rpress-fooditem-link"></div>
