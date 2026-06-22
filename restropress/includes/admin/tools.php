<?php
/**
 * Tools
 *
 * These are functions used for displaying RPRESS tools such as the import/export system.
 *
 * @package     RPRESS
 * @subpackage  Admin/Tools
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;
/**
 * Tools
 *
 * Shows the tools panel which contains RPRESS-specific tools including the
 * built-in import/export system.
 *
 * @since 1.0
 * @author      RestroPress
 * @return      void
 */
function rpress_tools_page()
{
	$active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
	$active_tab = rpress_map_legacy_tools_tab($active_tab);
	?>
	<div class="wrap rp-admin-scope rp-tools-page">
		<div class="rp-page-header rp-tools-header">
			<div class="rp-page-header-titles">
				<h1 class="wp-heading-inline rp-page-title"><?php esc_html_e('RestroPress Tools', 'restropress'); ?></h1>
				<p class="rp-page-subtitle"><?php esc_html_e('Check restaurant setup health, repair operational data, validate menu data, and collect diagnostics for support.', 'restropress'); ?></p>
			</div>
		</div>
		<hr class="wp-header-end">
		<nav class="nav-tab-wrapper rp-tabs" aria-label="<?php esc_attr_e('RestroPress tools tabs', 'restropress'); ?>">
			<?php
			foreach (rpress_get_tools_tabs() as $tab_id => $tab_name) {
				$tab_url = add_query_arg(array(
					'tab' => $tab_id
				));
				$tab_url = remove_query_arg(array(
					'rpress-message'
				), $tab_url);
				$active = $active_tab == $tab_id ? ' nav-tab-active' : '';
				echo '<a href="' . esc_url($tab_url) . '" class="nav-tab rp-tab' . esc_attr($active) . '">' . esc_html($tab_name) . '</a>';
			}
			?>
		</nav>
		<div class="metabox-holder rp-tools-panels">
			<?php
			do_action('rpress_tools_tab_' . $active_tab);
			?>
		</div><!-- .metabox-holder -->
	</div><!-- .wrap -->
	<?php
}
/**
 * Retrieve tools tabs
 *
 * @since       2.0
 * @return      array
 */
function rpress_get_tools_tabs()
{
	$tabs = array();
	$tabs['health_check'] = esc_html__('Health Check', 'restropress');
	$tabs['data_repair'] = esc_html__('Data Repair', 'restropress');
	$tabs['menu_utilities'] = esc_html__('Menu Utilities', 'restropress');
	$tabs['order_utilities'] = esc_html__('Order Utilities', 'restropress');
	$tabs['customer_access'] = esc_html__('Customer & Access', 'restropress');
	// Import/Export moved to Menu Items (menu) and Settings (configuration).
	// The import_export handler still answers a direct ?tab=import_export URL
	// with signposts, but it is no longer a primary Tools tab.
	$tabs['diagnostics'] = esc_html__('Diagnostics', 'restropress');
	if (count(rpress_get_beta_enabled_extensions()) > 0) {
		$tabs['betas'] = esc_html__('Beta Versions', 'restropress');
	}
	return apply_filters('rpress_tools_tabs', $tabs);
}

/**
 * Map older tool tab URLs to the restaurant-focused tools layout.
 *
 * @since 3.3
 *
 * @param string $tab Tool tab slug.
 * @return string
 */
function rpress_map_legacy_tools_tab($tab)
{
	$map = array(
		'general'     => 'health_check',
		'system_info' => 'diagnostics',
		'debug_log'   => 'diagnostics',
		'advanced'    => 'data_repair',
		'api_keys'    => 'customer_access',
	);

	return isset($map[$tab]) ? $map[$tab] : $tab;
}

/**
 * Display a restaurant setup health check.
 *
 * @since 3.3
 * @return void
 */
function rpress_tools_health_check_display()
{
	if (!current_user_can('manage_shop_settings')) {
		return;
	}

	$checks = rpress_tools_get_health_checks();
	$needs_attention = 0;
	foreach ($checks as $check) {
		if ('good' !== $check['status']) {
			$needs_attention++;
		}
	}
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e('Restaurant System Health', 'restropress'); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e('Use this page before launch, after updates, or when the restaurant reports ordering problems. It checks the operational pieces that decide whether customers can place orders cleanly.', 'restropress'); ?></p>
			<p>
				<strong>
					<?php
					echo esc_html(
						0 === $needs_attention
							? __('All critical checks are passing.', 'restropress')
							: sprintf(_n('%d item needs attention.', '%d items need attention.', $needs_attention, 'restropress'), $needs_attention)
					);
					?>
				</strong>
			</p>
			<table class="widefat striped rp-tools-health-table">
				<thead>
					<tr>
						<th><?php esc_html_e('Area', 'restropress'); ?></th>
						<th><?php esc_html_e('Status', 'restropress'); ?></th>
						<th><?php esc_html_e('What it means', 'restropress'); ?></th>
						<th><?php esc_html_e('Action', 'restropress'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($checks as $check) : ?>
						<tr>
							<td><strong><?php echo esc_html($check['label']); ?></strong></td>
							<td><?php echo esc_html(rpress_tools_get_status_label($check['status'])); ?></td>
							<td><?php echo esc_html($check['message']); ?></td>
							<td>
								<?php if (!empty($check['url'])) : ?>
									<a class="button button-secondary" href="<?php echo esc_url($check['url']); ?>"><?php echo esc_html($check['action']); ?></a>
								<?php else : ?>
									<span aria-hidden="true">-</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}
add_action('rpress_tools_tab_health_check', 'rpress_tools_health_check_display');

/**
 * Build health checks for the Tools screen.
 *
 * @since 3.3
 * @return array
 */
function rpress_tools_get_health_checks()
{
	$settings_url      = admin_url('admin.php?page=rpress-settings&tab=general&section=store_setup');
	$service_url       = admin_url('admin.php?page=rpress-settings&tab=general&section=service_hours');
	$gateways_url      = admin_url('admin.php?page=rpress-settings&tab=gateways');
	$permalinks_url    = admin_url('options-permalink.php');
	$diagnostics_url   = admin_url('admin.php?page=rpress-tools&tab=diagnostics');
	$checks            = array();
	$page_options      = array(
		'food_items_page' => __('Menu page', 'restropress'),
		'purchase_page'   => __('Checkout page', 'restropress'),
		'success_page'    => __('Order confirmation page', 'restropress'),
		'failure_page'    => __('Failed order page', 'restropress'),
	);

	foreach ($page_options as $option => $label) {
		$page_id = absint(rpress_get_option($option, 0));
		$valid   = $page_id && 'trash' !== get_post_status($page_id);
		$checks[] = array(
			'label'   => $label,
			'status'  => $valid ? 'good' : 'warning',
			'message' => $valid ? sprintf(__('Page is set to #%d.', 'restropress'), $page_id) : __('This page is not configured or no longer exists.', 'restropress'),
			'action'  => __('Fix Page', 'restropress'),
			'url'     => $valid ? get_edit_post_link($page_id, '') : $settings_url,
		);
	}

	$service_mode = rpress_get_option('enable_service', 'delivery_and_pickup');
	$service_labels = array(
		'delivery_and_pickup' => __('Delivery and pickup are enabled.', 'restropress'),
		'delivery'            => __('Delivery only is enabled.', 'restropress'),
		'pickup'              => __('Pickup only is enabled.', 'restropress'),
	);
	$checks[] = array(
		'label'   => __('Service modes', 'restropress'),
		'status'  => isset($service_labels[$service_mode]) ? 'good' : 'warning',
		'message' => isset($service_labels[$service_mode]) ? $service_labels[$service_mode] : __('Service mode is not configured.', 'restropress'),
		'action'  => __('Manage Services', 'restropress'),
		'url'     => $service_url,
	);

	$is_always_open = (bool) rpress_get_option('enable_always_open', false);
	$open_time      = rpress_get_option('open_time', '');
	$close_time     = rpress_get_option('close_time', '');
	$checks[]       = array(
		'label'   => __('Store hours', 'restropress'),
		'status'  => ($is_always_open || ($open_time && $close_time)) ? 'good' : 'warning',
		'message' => $is_always_open ? __('Always open ordering is enabled.', 'restropress') : sprintf(__('Ordering window: %1$s to %2$s.', 'restropress'), $open_time ? $open_time : __('not set', 'restropress'), $close_time ? $close_time : __('not set', 'restropress')),
		'action'  => __('Manage Hours', 'restropress'),
		'url'     => $service_url,
	);

	$gateways = rpress_get_enabled_payment_gateways();
	$checks[] = array(
		'label'   => __('Payment gateways', 'restropress'),
		'status'  => !empty($gateways) ? 'good' : 'warning',
		'message' => !empty($gateways) ? sprintf(_n('%d gateway is enabled.', '%d gateways are enabled.', count($gateways), 'restropress'), count($gateways)) : __('No payment gateway is enabled.', 'restropress'),
		'action'  => __('Configure Payments', 'restropress'),
		'url'     => $gateways_url,
	);

	$checks[] = array(
		'label'   => __('Permalinks', 'restropress'),
		'status'  => get_option('permalink_structure') ? 'good' : 'warning',
		'message' => get_option('permalink_structure') ? __('Pretty permalinks are enabled.', 'restropress') : __('Default permalinks are active. Pretty permalinks are recommended for public ordering pages.', 'restropress'),
		'action'  => __('Manage Permalinks', 'restropress'),
		'url'     => $permalinks_url,
	);

	$checks[] = array(
		'label'   => __('Diagnostics', 'restropress'),
		'status'  => 'good',
		'message' => __('System information and logs are available for support review.', 'restropress'),
		'action'  => __('Open Diagnostics', 'restropress'),
		'url'     => $diagnostics_url,
	);

	return apply_filters('rpress_tools_health_checks', $checks);
}

/**
 * Return a readable status label.
 *
 * @since 3.3
 *
 * @param string $status Status slug.
 * @return string
 */
function rpress_tools_get_status_label($status)
{
	$labels = array(
		'good'     => __('Good', 'restropress'),
		'warning'  => __('Needs attention', 'restropress'),
		'critical' => __('Critical', 'restropress'),
	);

	return isset($labels[$status]) ? $labels[$status] : $status;
}
/**
 * Display the ban emails tab
 *
 * @since       2.0
 * @return      void
 */
function rpress_tools_banned_emails_display()
{
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	do_action('rpress_tools_banned_emails_before');
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e('Blocked Emails & Domains', 'restropress'); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e('Customers using emails, domains, or top-level domains listed below will not be allowed to place orders.', 'restropress'); ?>
			</p>
			<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rpress-tools&tab=customer_access')); ?>">
				<p>
					<textarea name="banned_emails" rows="10"
						class="large-text"><?php echo esc_textarea(implode("\n", rpress_get_banned_emails())); ?></textarea>
					<span
						class="description"><?php esc_html_e('Enter emails and/or domains (starting with "@") and/or TLDs (starting with ".") to disallow, one per line.', 'restropress'); ?></span>
				</p>
				<p>
					<input type="hidden" name="rpress_action" value="save_banned_emails" />
					<?php wp_nonce_field('rpress_banned_emails_nonce', 'rpress_banned_emails_nonce'); ?>
					<?php submit_button(esc_html__('Save', 'restropress'), 'secondary', 'submit', false); ?>
				</p>
			</form>
		</div><!-- .inside -->
	</div><!-- .postbox -->
	<?php
	do_action('rpress_tools_banned_emails_after');
	do_action('rpress_tools_after');
}
add_action('rpress_tools_tab_customer_access', 'rpress_tools_banned_emails_display');
/**
 * Display the recount stats
 *
 * @since 1.0
 * @return      void
 */
function rpress_tools_recount_stats_display()
{
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	do_action('rpress_tools_recount_stats_before');
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e('Repair Restaurant Stats', 'restropress'); ?></span></h3>
		<div class="inside recount-stats-controls">
			<p><?php esc_html_e('Use these tools when reports, menu item totals, or customer order counts look stale after an import, migration, or plugin update.', 'restropress'); ?></p>
			<form method="post" id="rpress-tools-recount-form" class="rpress-export-form rpress-import-export-form">
				<span>
					<?php wp_nonce_field('rpress_ajax_export', 'rpress_ajax_export'); ?>
					<select name="rpress-export-class" id="recount-stats-type">
						<option value="0" selected="selected" disabled="disabled">
							<?php esc_html_e('Please select an option', 'restropress'); ?></option>
						<option data-type="recount-store" value="RPRESS_Tools_Recount_Store_Earnings">
							<?php esc_html_e('Recalculate Restaurant Totals', 'restropress'); ?></option>
						<option data-type="recount-fooditem" value="RPRESS_Tools_Recount_Download_Stats">
							<?php esc_html_e('Recalculate One Menu Item', 'restropress'); ?>
						</option>
						<option data-type="recount-all" value="RPRESS_Tools_Recount_All_Stats">
							<?php esc_html_e('Recalculate All Menu Items', 'restropress'); ?>
						</option>
						<option data-type="recount-customer-stats" value="RPRESS_Tools_Recount_Customer_Stats">
							<?php esc_html_e('Recalculate Customer Stats', 'restropress'); ?></option>
						<?php do_action('rpress_recount_tool_options'); ?>
						<option data-type="reset-stats" value="RPRESS_Tools_Reset_Stats">
							<?php esc_html_e('Reset Restaurant Data', 'restropress'); ?></option>
					</select>
					<span id="tools-product-dropdown" style="display: none">
						<?php
						$args = array(
							'name' => 'fooditem_id',
							'number' => -1,
							'chosen' => true,
						);

						echo wp_kses(
							RPRESS()->html->product_dropdown($args),
							array(
								'select' => array(
									'name' => array(),
									'id' => array(),
									'class' => array(),
								),
								'option' => array(
									'value' => array(),
									'selected' => array(),
								),
								'optgroup' => array(
									'label' => array(),
								),
							)
						);
						?>
					</span>
					<input type="submit" id="recount-stats-submit" value="<?php esc_html_e('Submit', 'restropress'); ?>"
						class="button-secondary" />
					<br />
					<span class="rpress-recount-stats-descriptions">
						<span
							id="recount-store"><?php esc_html_e('Recalculates total restaurant earnings and order counts.', 'restropress'); ?></span>
						<span
							id="recount-fooditem"><?php esc_html_e('Recalculates order and earnings stats for one menu item.', 'restropress'); ?></span>
						<span
							id="recount-all"><?php esc_html_e('Recalculates order and earnings stats for all menu items.', 'restropress'); ?></span>
						<span
							id="recount-customer-stats"><?php esc_html_e('Recalculates lifetime spend and order counts for all customers.', 'restropress'); ?></span>
						<?php do_action('rpress_recount_tool_descriptions'); ?>
						<span
							id="reset-stats"><?php esc_html_e('Deletes all order records, customers, and related log entries. Use only on test or staging sites.', 'restropress'); ?></span>
					</span>
					<span class="spinner"></span>
				</span>
			</form>
			<?php do_action('rpress_tools_recount_forms'); ?>
		</div><!-- .inside -->
	</div><!-- .postbox -->
	<?php
	do_action('rpress_tools_recount_stats_after');
}
add_action('rpress_tools_tab_data_repair', 'rpress_tools_recount_stats_display');
/**
 * Display the clear upgrades tab
 *
 * @since       2.3.5
 * @return      void
 */
function rpress_tools_clear_doing_upgrade_display()
{
	if (!current_user_can('manage_shop_settings') || false === get_option('rpress_doing_upgrade')) {
		return;
	}
	do_action('rpress_tools_clear_doing_upgrade_before');
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e('Clear Incomplete Upgrade Notice', 'restropress'); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e('Sometimes a database upgrade notice may not be cleared after an upgrade is completed due to conflicts with other extensions or other minor issues.', 'restropress'); ?>
			</p>
			<p><?php esc_html_e('If you\'re certain these upgrades have been completed, you can clear these upgrade notices by clicking the button below. If you have any questions about this, please contact the RestroPress support team and we\'ll be happy to help.', 'restropress'); ?>
			</p>
			<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rpress-tools&tab=data_repair')); ?>">
				<p>
					<input type="hidden" name="rpress_action" value="clear_doing_upgrade" />
					<?php wp_nonce_field('rpress_clear_upgrades_nonce', 'rpress_clear_upgrades_nonce'); ?>
					<?php submit_button(esc_html__('Clear Incomplete Upgrade Notice', 'restropress'), 'secondary', 'submit', false); ?>
				</p>
			</form>
		</div><!-- .inside -->
	</div><!-- .postbox -->
	<?php
	do_action('rpress_tools_clear_doing_upgrade_after');
}
add_action('rpress_tools_tab_data_repair', 'rpress_tools_clear_doing_upgrade_display');

/**
 * Display menu validation utilities.
 *
 * @since 3.3
 * @return void
 */
function rpress_tools_menu_utilities_display()
{
	if (!current_user_can('manage_shop_settings')) {
		return;
	}

	$total_items       = wp_count_posts('fooditem');
	$published_items   = isset($total_items->publish) ? absint($total_items->publish) : 0;
	$missing_prices    = rpress_tools_count_fooditems_missing_meta('rpress_price');
	$missing_images    = rpress_tools_count_fooditems_missing_thumbnail();
	$missing_categories = rpress_tools_count_fooditems_missing_terms('food-category');
	$addon_groups      = wp_count_terms(array('taxonomy' => 'addon_category', 'hide_empty' => false));
	$addon_groups      = is_wp_error($addon_groups) ? 0 : absint($addon_groups);
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e('Menu Readiness Utilities', 'restropress'); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e('Use these checks before launch, after a menu import, or when the restaurant says items are missing, priced wrong, or hard to order.', 'restropress'); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e('Check', 'restropress'); ?></th>
						<th><?php esc_html_e('Result', 'restropress'); ?></th>
						<th><?php esc_html_e('Why it matters', 'restropress'); ?></th>
						<th><?php esc_html_e('Action', 'restropress'); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e('Published menu items', 'restropress'); ?></strong></td>
						<td><?php echo esc_html($published_items); ?></td>
						<td><?php esc_html_e('Published items are visible to customers when the menu page is available.', 'restropress'); ?></td>
						<td><a class="button button-secondary" href="<?php echo esc_url(admin_url('edit.php?post_type=fooditem')); ?>"><?php esc_html_e('Manage Menu', 'restropress'); ?></a></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e('Items missing price', 'restropress'); ?></strong></td>
						<td><?php echo esc_html($missing_prices); ?></td>
						<td><?php esc_html_e('Menu items without prices can confuse customers or fail during ordering.', 'restropress'); ?></td>
						<td><a class="button button-secondary" href="<?php echo esc_url(admin_url('edit.php?post_type=fooditem')); ?>"><?php esc_html_e('Review Items', 'restropress'); ?></a></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e('Items missing image', 'restropress'); ?></strong></td>
						<td><?php echo esc_html($missing_images); ?></td>
						<td><?php esc_html_e('Images improve menu confidence and conversion for online ordering.', 'restropress'); ?></td>
						<td><a class="button button-secondary" href="<?php echo esc_url(admin_url('edit.php?post_type=fooditem')); ?>"><?php esc_html_e('Review Items', 'restropress'); ?></a></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e('Items without category', 'restropress'); ?></strong></td>
						<td><?php echo esc_html($missing_categories); ?></td>
						<td><?php esc_html_e('Categories keep the customer menu easy to scan during busy ordering windows.', 'restropress'); ?></td>
						<td><a class="button button-secondary" href="<?php echo esc_url(admin_url('edit.php?post_type=fooditem')); ?>"><?php esc_html_e('Review Categories', 'restropress'); ?></a></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e('Add-on groups', 'restropress'); ?></strong></td>
						<td><?php echo esc_html($addon_groups); ?></td>
						<td><?php esc_html_e('Add-ons and modifiers should be checked after catalog imports or menu migrations.', 'restropress'); ?></td>
						<td><a class="button button-secondary" href="<?php echo esc_url(admin_url('edit.php?post_type=fooditem&page=rpress-menu-import')); ?>"><?php esc_html_e('Import / Export Menu', 'restropress'); ?></a></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}
add_action('rpress_tools_tab_menu_utilities', 'rpress_tools_menu_utilities_display');

/**
 * Display order data utilities.
 *
 * @since 3.3
 * @return void
 */
function rpress_tools_order_utilities_display()
{
	if (!current_user_can('manage_shop_settings')) {
		return;
	}

	$pending_orders = rpress_count_payments()->pending ?? 0;
	$failed_orders  = rpress_count_payments()->failed ?? 0;
	$refunded_orders = rpress_count_payments()->refunded ?? 0;
	$orders_url     = admin_url('admin.php?page=rpress-payment-history');
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e('Order Data Utilities', 'restropress'); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e('Use this area to review operational order data that may need cleanup or follow-up. Actual order handling still belongs in Orders and Live Orders.', 'restropress'); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e('Review Area', 'restropress'); ?></th>
						<th><?php esc_html_e('Count', 'restropress'); ?></th>
						<th><?php esc_html_e('Why it matters', 'restropress'); ?></th>
						<th><?php esc_html_e('Action', 'restropress'); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e('Pending orders', 'restropress'); ?></strong></td>
						<td><?php echo esc_html(absint($pending_orders)); ?></td>
						<td><?php esc_html_e('Pending orders may need payment or manager review before fulfillment.', 'restropress'); ?></td>
						<td><a class="button button-secondary" href="<?php echo esc_url(add_query_arg('status', 'pending', $orders_url)); ?>"><?php esc_html_e('Review Pending', 'restropress'); ?></a></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e('Failed orders', 'restropress'); ?></strong></td>
						<td><?php echo esc_html(absint($failed_orders)); ?></td>
						<td><?php esc_html_e('Failed payments can explain customer complaints or missing revenue.', 'restropress'); ?></td>
						<td><a class="button button-secondary" href="<?php echo esc_url(add_query_arg('status', 'failed', $orders_url)); ?>"><?php esc_html_e('Review Failed', 'restropress'); ?></a></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e('Refunded orders', 'restropress'); ?></strong></td>
						<td><?php echo esc_html(absint($refunded_orders)); ?></td>
						<td><?php esc_html_e('Refunds should match the restaurant recovery and accounting process.', 'restropress'); ?></td>
						<td><a class="button button-secondary" href="<?php echo esc_url(add_query_arg('status', 'refunded', $orders_url)); ?>"><?php esc_html_e('Review Refunds', 'restropress'); ?></a></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e('Repair order totals', 'restropress'); ?></strong></td>
						<td><?php esc_html_e('Available', 'restropress'); ?></td>
						<td><?php esc_html_e('If historical order totals look stale, run the restaurant stats repair tools.', 'restropress'); ?></td>
						<td><a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=rpress-tools&tab=data_repair')); ?>"><?php esc_html_e('Open Data Repair', 'restropress'); ?></a></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}
add_action('rpress_tools_tab_order_utilities', 'rpress_tools_order_utilities_display');

/**
 * Count published food items missing a post meta value.
 *
 * @since 3.3
 *
 * @param string $meta_key Meta key.
 * @return int
 */
function rpress_tools_count_fooditems_missing_meta($meta_key)
{
	$query = new WP_Query(array(
		'post_type'      => 'fooditem',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'     => $meta_key,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $meta_key,
				'value'   => '',
				'compare' => '=',
			),
		),
	));

	return absint($query->found_posts);
}

/**
 * Count published food items missing images.
 *
 * @since 3.3
 * @return int
 */
function rpress_tools_count_fooditems_missing_thumbnail()
{
	return rpress_tools_count_fooditems_missing_meta('_thumbnail_id');
}

/**
 * Count published food items missing terms for a taxonomy.
 *
 * @since 3.3
 *
 * @param string $taxonomy Taxonomy.
 * @return int
 */
function rpress_tools_count_fooditems_missing_terms($taxonomy)
{
	$query = new WP_Query(array(
		'post_type'      => 'fooditem',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => -1,
	));

	$count = 0;
	foreach ($query->posts as $post_id) {
		if (!has_term('', $taxonomy, $post_id)) {
			$count++;
		}
	}

	return $count;
}
/**
 * Display beta opt-ins
 *
 * @since 1.01
 * @return      void
 */
function rpress_tools_betas_display()
{
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	$has_beta = rpress_get_beta_enabled_extensions();
	if (empty($has_beta)) {
		return;
	}
	do_action('rpress_tools_betas_before');
	?>
	<div class="postbox rpress-beta-support">
		<h3><span><?php esc_html_e('Enable Beta Versions', 'restropress'); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e('Checking any of the below checkboxes will opt you in to receive pre-release update notifications. You can opt-out at any time. Pre-release updates do not install automatically, you will still have the opportunity to ignore update notifications.', 'restropress'); ?>
			</p>
			<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rpress-tools&tab=betas')); ?>">
				<table class="form-table rpress-beta-support">
					<tbody>
						<?php foreach ($has_beta as $slug => $product): ?>
							<tr>
								<?php $checked = rpress_extension_has_beta_support($slug); ?>
								<th scope="row"><?php echo esc_html($product); ?></th>
								<td>
									<input type="checkbox" name="enabled_betas[<?php echo esc_attr($slug); ?>]"
										id="enabled_betas[<?php echo esc_attr($slug); ?>]" <?php echo checked($checked, true, false); ?> value="1" />
									<label
										for="enabled_betas[<?php echo esc_attr($slug); ?>]"><?php printf(esc_html__('Get updates for pre-release versions of %s', 'restropress'), esc_html($product)); ?></label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<input type="hidden" name="rpress_action" value="save_enabled_betas" />
				<?php wp_nonce_field('rpress_save_betas_nonce', 'rpress_save_betas_nonce'); ?>
				<?php submit_button(esc_html__('Save', 'restropress'), 'secondary', 'submit', false); ?>
			</form>
		</div>
	</div>
	<?php
	do_action('rpress_tools_betas_after');
}
add_action('rpress_tools_tab_betas', 'rpress_tools_betas_display');

/**
 * Display customer and access tools.
 *
 * @since 3.3
 * @return void
 */
function rpress_tools_customer_access_guidance_display()
{
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e('Customer & API Access', 'restropress'); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e('Use this area for customer access controls and integration access guidance. Customer analytics and exports belong in Reports; API behavior is configured in Settings.', 'restropress'); ?></p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=rpress-settings&tab=general&section=developer_api')); ?>"><?php esc_html_e('Developer API Settings', 'restropress'); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url(admin_url('profile.php')); ?>"><?php esc_html_e('Manage Application Passwords', 'restropress'); ?></a>
			</p>
		</div>
	</div>
	<?php
}
add_action('rpress_tools_tab_customer_access', 'rpress_tools_customer_access_guidance_display', 5);
/**
 * Return an array of all extensions with beta support
 *
 * Extensions should be added as 'extension-slug' => 'Extension Name'
 *
 * @since 1.01
 * @return      array $extensions The array of extensions
 */
function rpress_get_beta_enabled_extensions()
{
	return apply_filters('rpress_beta_enabled_extensions', array());
}
/**
 * Check if a given extensions has beta support enabled
 *
 * @since 1.01
 * @param       string $slug The slug of the extension to check
 * @return      bool True if enabled, false otherwise
 */
function rpress_extension_has_beta_support($slug)
{
	$enabled_betas = rpress_get_option('enabled_betas', array());
	$return = false;
	if (array_key_exists($slug, $enabled_betas)) {
		$return = true;
	}
	return $return;
}
/**
 * Save enabled betas
 *
 * @since 1.01
 * @return      void
 */
function rpress_tools_enabled_betas_save()
{
	if (empty($_POST['rpress_save_betas_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rpress_save_betas_nonce'])), 'rpress_save_betas_nonce')) {
		return;
	}
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	if (!empty($_POST['enabled_betas'])) {
		$enabled_betas = wp_unslash($_POST['enabled_betas']);
		$enabled_betas = is_array($enabled_betas) ? array_map('sanitize_text_field', $enabled_betas) : array(sanitize_text_field($enabled_betas));
		$enabled_betas = array_filter(array_map('rpress_tools_enabled_betas_sanitize_value', $enabled_betas));
		rpress_update_option('enabled_betas', $enabled_betas);
	} else {
		rpress_delete_option('enabled_betas');
	}
}
add_action('rpress_save_enabled_betas', 'rpress_tools_enabled_betas_save');
/**
 * Sanitize the supported beta values by making them booleans
 *
 * @since 1.0.0.11
 * @param mixed $value The value being sent in, determining if beta support is enabled.
 *
 * @return bool
 */
function rpress_tools_enabled_betas_sanitize_value($value)
{
	return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}
/**
 * Save banned emails
 *
 * @since       2.0
 * @return      void
 */
function rpress_tools_banned_emails_save()
{
	if (empty($_POST['rpress_banned_emails_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rpress_banned_emails_nonce'])), 'rpress_banned_emails_nonce')) {
		return;
	}
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	if (!empty($_POST['banned_emails'])) {
		// Sanitize the input
		$emails = array_map('trim', explode("\n", sanitize_email(wp_unslash($_POST['banned_emails']))));
		$emails = array_unique($emails);
		$emails = array_map('sanitize_text_field', $emails);
		foreach ($emails as $id => $email) {
			if (!is_email($email) && $email[0] != '@' && $email[0] != '.') {
				unset($emails[$id]);
			}
		}
	} else {
		$emails = '';
	}
	rpress_update_option('banned_emails', $emails);
}
add_action('rpress_save_banned_emails', 'rpress_tools_banned_emails_save');
/**
 * Execute upgrade notice clear
 *
 * @since       2.3.5
 * @return      void
 */
function rpress_tools_clear_upgrade_notice()
{
	if (empty($_POST['rpress_clear_upgrades_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rpress_clear_upgrades_nonce'])), 'rpress_clear_upgrades_nonce')) {
		return;
	}
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	delete_option('rpress_doing_upgrade');
}
add_action('rpress_clear_doing_upgrade', 'rpress_tools_clear_upgrade_notice');
/**
 * Display the tools import/export tab
 *
 * @since       2.0
 * @return      void
 */
function rpress_tools_import_export_display()
{
	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		return;
	}
	do_action( 'rpress_tools_import_export_before' );
	$menu_import = admin_url( 'edit.php?post_type=fooditem&page=rpress-menu-import' );
	$menu_export = admin_url( 'edit.php?post_type=fooditem&page=rpress-menu-export' );
	$settings_io = admin_url( 'admin.php?page=rpress-settings&tab=misc&section=main' );
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e( 'Import & Export have moved', 'restropress' ); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e( 'Import and export now live where the data is managed:', 'restropress' ); ?></p>
			<ul style="list-style:disc;margin-left:20px;">
				<li><a href="<?php echo esc_url( $menu_import ); ?>"><?php esc_html_e( 'Import your menu', 'restropress' ); ?></a> &mdash; <?php esc_html_e( 'AI / photo / PDF / spreadsheet, under Menu Items.', 'restropress' ); ?></li>
				<li><a href="<?php echo esc_url( $menu_export ); ?>"><?php esc_html_e( 'Export your menu', 'restropress' ); ?></a> &mdash; <?php esc_html_e( 'menu catalog CSV, under Menu Items.', 'restropress' ); ?></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-reports&tab=export' ) ); ?>"><?php esc_html_e( 'Export reports', 'restropress' ); ?></a> &mdash; <?php esc_html_e( 'orders, customers, payments, taxes.', 'restropress' ); ?></li>
				<li><a href="<?php echo esc_url( $settings_io ); ?>"><?php esc_html_e( 'Import / export settings', 'restropress' ); ?></a> &mdash; <?php esc_html_e( 'configuration .json, under Settings &rarr; Misc.', 'restropress' ); ?></li>
			</ul>
		</div>
	</div>
	<?php
	do_action( 'rpress_tools_import_export_after' );
}
add_action('rpress_tools_tab_import_export', 'rpress_tools_import_export_display');
/**
 * Process a settings export that generates a .json file of the shop settings
 *
 * @since 1.0
 * @return      void
 */
function rpress_tools_import_export_process_export()
{
	if (empty($_POST['rpress_export_nonce']))
		return;
	if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rpress_export_nonce'])), 'rpress_export_nonce'))
		return;
	if (!current_user_can('manage_shop_settings'))
		return;
	$rpress_settings = get_option('rpress_settings');
	$rpress_tax_rates = get_option('rpress_tax_rates');
	$settings = array(
		'rpress_settings' => $rpress_settings,
		'rpress_tax_rates' => $rpress_tax_rates,
	);
	ignore_user_abort(true);
	if (!rpress_is_func_disabled('set_time_limit'))
		set_time_limit(0);
	nocache_headers();
	header('Content-Type: application/json; charset=utf-8');
	header('Content-Disposition: attachment; filename=' . apply_filters('rpress_settings_export_filename', 'rpress-settings-export-' . gmdate('m-d-Y')) . '.json');
	header("Expires: 0");
	echo wp_json_encode($settings);
	exit;
}
add_action('rpress_export_settings', 'rpress_tools_import_export_process_export');
/**
 * Process a settings import from a json file
 *
 * @since  1.0.0
 * @return void
 */
function rpress_tools_import_export_process_import()
{
	if (empty($_POST['rpress_import_nonce']))
		return;
	if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rpress_import_nonce'])), 'rpress_import_nonce'))
		return;
	if (!current_user_can('manage_shop_settings'))
		return;
	if (rpress_get_file_extension(sanitize_file_name($_FILES['import_file']['name'])) != 'json') {
		wp_die(esc_html__('Please upload a valid .json file', 'restropress'), esc_html__('Error', 'restropress'), array('response' => 400));
	}
	$import_file = sanitize_file_name($_FILES['import_file']['tmp_name']);
	if (empty($import_file)) {
		wp_die(esc_html__('Please upload a file to import', 'restropress'), esc_html__('Error', 'restropress'), array('response' => 400));
	}
	// Retrieve the settings from the file and convert the json object to an array
	$settings = rpress_object_to_array(json_decode(file_get_contents($import_file)));
	if (!isset($settings['rpress_settings'])) {
		// Process a settings export from a pre 2.8 version of RPRESS
		update_option('rpress_settings', $settings);
	} else {
		// Update the settings from a 2.8+ export file
		$rpress_settings = $settings['rpress_settings'];
		update_option('rpress_settings', $rpress_settings);
		$rpress_tax_rates = $settings['rpress_tax_rates'];
		update_option('rpress_tax_rates', $rpress_tax_rates);
	}
	// Return to wherever the import was started from (Settings now hosts the
	// configuration import/export UI), falling back to the Settings page.
	$redirect = wp_get_referer();
	if ( ! $redirect ) {
		$redirect = admin_url( 'admin.php?page=rpress-settings&tab=misc&section=main' );
	}
	$redirect = add_query_arg( 'rpress-message', 'settings-imported', remove_query_arg( 'rpress-message', $redirect ) );
	wp_safe_redirect($redirect);
	exit;
}
add_action('rpress_import_settings', 'rpress_tools_import_export_process_import');

/**
 * Render the configuration (settings .json) export/import UI. Configuration
 * portability belongs with the configuration, so this renders on
 * Settings -> Misc rather than under Tools.
 *
 * @since 3.3
 * @return void
 */
function rpress_settings_render_config_io()
{
	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		return;
	}
	$reports_link = admin_url( 'admin.php?page=rpress-reports&tab=export' );
	$menu_link    = admin_url( 'edit.php?post_type=fooditem&page=rpress-menu-export' );
	?>
	<div class="rp-card rp-settings-config-io" style="margin-top:20px;padding:16px 20px;">
		<h3><?php esc_html_e( 'Import / Export configuration', 'restropress' ); ?></h3>
		<p class="rp-help-text">
			<?php
			printf(
				/* translators: 1: Reports export URL, 2: menu export URL. */
				wp_kses( __( 'This moves your RestroPress <strong>settings</strong> between sites. For order/customer/tax data use <a href="%1$s">Reports → Export</a>; for your menu use <a href="%2$s">Menu Items → Export</a>.', 'restropress' ), array( 'strong' => array(), 'a' => array( 'href' => array() ) ) ),
				esc_url( $reports_link ),
				esc_url( $menu_link )
			);
			?>
		</p>
		<div class="rp-grid rp-grid-2" style="gap:20px;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=rpress-settings&tab=misc&section=main' ) ); ?>">
				<h4><?php esc_html_e( 'Export settings', 'restropress' ); ?></h4>
				<p class="rp-help-text"><?php esc_html_e( 'Download this site’s RestroPress settings as a .json file.', 'restropress' ); ?></p>
				<input type="hidden" name="rpress_action" value="export_settings" />
				<?php wp_nonce_field( 'rpress_export_nonce', 'rpress_export_nonce' ); ?>
				<?php submit_button( esc_html__( 'Export', 'restropress' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin.php?page=rpress-settings&tab=misc&section=main' ) ); ?>">
				<h4><?php esc_html_e( 'Import settings', 'restropress' ); ?></h4>
				<p class="rp-help-text"><?php esc_html_e( 'Upload a settings .json exported from another site.', 'restropress' ); ?></p>
				<input type="file" name="import_file" accept=".json" />
				<input type="hidden" name="rpress_action" value="import_settings" />
				<?php wp_nonce_field( 'rpress_import_nonce', 'rpress_import_nonce' ); ?>
				<?php submit_button( esc_html__( 'Import', 'restropress' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
	</div>
	<?php
}
add_action( 'rpress_settings_tab_bottom_misc_main', 'rpress_settings_render_config_io' );

/**
 * Menu Items -> Export screen. Exports the menu catalog (items, prices,
 * variants, add-ons, dietary) as CSV via the existing batch exporter. Lives
 * with the catalog rather than under Reports (which is for period performance).
 *
 * @since 3.3
 * @return void
 */
function rpress_menu_export_page()
{
	if ( ! current_user_can( 'export_shop_reports' ) ) {
		wp_die( esc_html__( 'You do not have permission to export menu items.', 'restropress' ) );
	}
	$import_url = admin_url( 'edit.php?post_type=fooditem&page=rpress-menu-import' );
	$menu_list  = admin_url( 'edit.php?post_type=fooditem' );
	?>
	<div class="wrap rp-admin-scope rp-menu-export-wrap">
		<div class="rp-page-header">
			<div class="rp-page-header-titles">
				<p class="rp-page-eyebrow"><?php esc_html_e( 'Menu Items', 'restropress' ); ?></p>
				<h1 class="wp-heading-inline rp-page-title"><?php esc_html_e( 'Export Menu', 'restropress' ); ?></h1>
				<p class="rp-page-subtitle"><?php esc_html_e( 'Download your full menu catalog - items, prices, sizes, add-ons and dietary labels - as a CSV for backup, migration, or review.', 'restropress' ); ?></p>
			</div>
			<div class="rp-page-actions">
				<a href="<?php echo esc_url( $import_url ); ?>" class="button rp-btn rp-btn-secondary"><?php esc_html_e( 'Import Menu', 'restropress' ); ?></a>
				<a href="<?php echo esc_url( $menu_list ); ?>" class="button rp-btn rp-btn-secondary"><?php esc_html_e( 'Back to Menu Items', 'restropress' ); ?></a>
			</div>
		</div>
		<hr class="wp-header-end">
		<?php
		// Columns are grouped for the screen so related fields - especially the
		// add-on set - stay visually together and self-explanatory. The keys/labels
		// mirror RPRESS_Batch_RestroPress_Export::csv_cols(); keep them in sync. ID
		// is always exported (the exporter forces it) so it shows disabled/checked.
		$export_column_groups = array(
			esc_html__( 'Item details', 'restropress' ) => array(
				'post_title'                  => esc_html__( 'Name', 'restropress' ),
				'categories'                  => esc_html__( 'Categories', 'restropress' ),
				'rpress_price'                => esc_html__( 'Price', 'restropress' ),
				'rpress_variable_price_label' => esc_html__( 'Variable Price Label', 'restropress' ),
				'post_content'                => esc_html__( 'Description', 'restropress' ),
				'post_excerpt'                => esc_html__( 'Short Description', 'restropress' ),
				'tag_mark'                    => esc_html__( 'Veg / Non-Veg', 'restropress' ),
				'dietary'                     => esc_html__( 'Dietary', 'restropress' ),
				'tags'                        => esc_html__( 'Tags', 'restropress' ),
			),
			esc_html__( 'Add-ons', 'restropress' ) => array(
				'addons'            => esc_html__( 'Add-ons', 'restropress' ),
				'addon_prices'      => esc_html__( 'Add-on Prices', 'restropress' ),
				'addon_max'         => esc_html__( 'Max Add-ons', 'restropress' ),
				'addon_default'     => esc_html__( 'Default Add-ons', 'restropress' ),
				'addon_is_required' => esc_html__( 'Add-ons Required', 'restropress' ),
			),
			esc_html__( 'Media & identifiers', 'restropress' ) => array(
				'ID'                   => esc_html__( 'ID', 'restropress' ),
				'_thumbnail_id'        => esc_html__( 'Featured Image', 'restropress' ),
				'rpress_sku'           => esc_html__( 'SKU', 'restropress' ),
				'rpress_product_notes' => esc_html__( 'Notes', 'restropress' ),
				'post_status'          => esc_html__( 'Status', 'restropress' ),
				'post_name'            => esc_html__( 'Slug', 'restropress' ),
				'post_date'            => esc_html__( 'Date Created', 'restropress' ),
				'post_author'          => esc_html__( 'Author', 'restropress' ),
			),
			esc_html__( 'Sales stats (read-only)', 'restropress' ) => array(
				'_rpress_fooditem_sales'    => esc_html__( 'Order Count', 'restropress' ),
				'_rpress_fooditem_earnings' => esc_html__( 'Total Revenue', 'restropress' ),
			),
		);
		$food_categories = get_terms(
			array(
				'taxonomy'   => 'food-category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $food_categories ) ) {
			$food_categories = array();
		}
		// Depth lookup so child categories can be indented under their parents.
		$cat_depth = array();
		$depth_of  = function ( $term ) use ( &$depth_of, $food_categories, &$cat_depth ) {
			if ( isset( $cat_depth[ $term->term_id ] ) ) {
				return $cat_depth[ $term->term_id ];
			}
			$depth = 0;
			$parent = (int) $term->parent;
			$guard  = 0;
			while ( $parent && $guard < 20 ) {
				$depth++;
				$guard++;
				$found = false;
				foreach ( $food_categories as $candidate ) {
					if ( (int) $candidate->term_id === $parent ) {
						$parent = (int) $candidate->parent;
						$found  = true;
						break;
					}
				}
				if ( ! $found ) {
					break;
				}
			}
			$cat_depth[ $term->term_id ] = $depth;
			return $depth;
		};
		$statuses = array(
			'publish' => esc_html__( 'Published', 'restropress' ),
			'draft'   => esc_html__( 'Draft', 'restropress' ),
			'pending' => esc_html__( 'Pending review', 'restropress' ),
			'private' => esc_html__( 'Private', 'restropress' ),
		);
		$food_types = array(
			'veg'     => esc_html__( 'Veg', 'restropress' ),
			'non_veg' => esc_html__( 'Non Veg', 'restropress' ),
		);
		?>
		<div class="rp-card rp-menu-export-card">
			<form id="rpress-export-menu" class="rpress-export-form rpress-import-export-form" method="post">
				<?php wp_nonce_field( 'rpress_ajax_export', 'rpress_ajax_export' ); ?>
				<input type="hidden" name="rpress-export-class" value="RPRESS_Batch_RestroPress_Export" />

				<div class="rp-export-row">
					<div class="rp-export-row-label">
						<label><?php esc_html_e( 'Which columns should be exported?', 'restropress' ); ?></label>
						<p class="rp-help-text"><?php esc_html_e( 'Leave all ticked to export the full catalog.', 'restropress' ); ?></p>
						<p class="rp-export-col-tools">
							<button type="button" class="button-link rp-export-select-all"><?php esc_html_e( 'Select all', 'restropress' ); ?></button>
							<span aria-hidden="true">·</span>
							<button type="button" class="button-link rp-export-select-none"><?php esc_html_e( 'Select none', 'restropress' ); ?></button>
						</p>
					</div>
					<div class="rp-export-row-field">
						<?php foreach ( $export_column_groups as $group_label => $group_cols ) : ?>
							<div class="rp-export-colgroup">
								<p class="rp-export-colgroup-title"><?php echo esc_html( $group_label ); ?></p>
								<div class="rp-export-columns">
									<?php foreach ( $group_cols as $key => $label ) :
										$is_id = ( 'ID' === $key );
										?>
										<label class="rp-export-col">
											<input type="checkbox" name="columns[]" value="<?php echo esc_attr( $key ); ?>" checked<?php echo $is_id ? ' disabled' : ''; ?> />
											<?php echo esc_html( $label ); ?>
										</label>
										<?php if ( $is_id ) : ?>
											<input type="hidden" name="columns[]" value="ID" />
										<?php endif; ?>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="rp-export-row">
					<div class="rp-export-row-label">
						<label><?php esc_html_e( 'Which categories should be exported?', 'restropress' ); ?></label>
						<p class="rp-help-text"><?php esc_html_e( 'Leave blank to include every category.', 'restropress' ); ?></p>
					</div>
					<div class="rp-export-row-field">
						<?php if ( empty( $food_categories ) ) : ?>
							<p class="rp-help-text"><?php esc_html_e( 'No menu categories found yet.', 'restropress' ); ?></p>
						<?php else : ?>
							<div class="rp-export-categories">
								<?php foreach ( $food_categories as $term ) :
									$depth = (int) $depth_of( $term );
									?>
									<label class="rp-export-cat" style="padding-left:<?php echo esc_attr( $depth * 18 ); ?>px;">
										<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $term->term_id ); ?>" />
										<?php echo esc_html( $term->name ); ?>
										<span class="rp-export-cat-count">(<?php echo esc_html( $term->count ); ?>)</span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="rp-export-row">
					<div class="rp-export-row-label">
						<label><?php esc_html_e( 'Which statuses should be exported?', 'restropress' ); ?></label>
						<p class="rp-help-text"><?php esc_html_e( 'Leave blank to include items in any status.', 'restropress' ); ?></p>
					</div>
					<div class="rp-export-row-field">
						<div class="rp-export-inline-options">
							<?php foreach ( $statuses as $status => $label ) : ?>
								<label class="rp-export-col">
									<input type="checkbox" name="statuses[]" value="<?php echo esc_attr( $status ); ?>" />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="rp-export-row">
					<div class="rp-export-row-label">
						<label for="rp-export-food-type"><?php esc_html_e( 'Food type', 'restropress' ); ?></label>
					</div>
					<div class="rp-export-row-field">
						<select id="rp-export-food-type" name="food_type">
							<option value=""><?php esc_html_e( 'All food types', 'restropress' ); ?></option>
							<?php foreach ( $food_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="rp-export-actions">
					<input type="submit" value="<?php esc_attr_e( 'Generate CSV', 'restropress' ); ?>" class="button button-primary button-hero rp-btn rp-btn-primary" />
					<p class="rp-help-text"><?php esc_html_e( 'For order, customer, payment and tax data, use Reports → Export instead.', 'restropress' ); ?></p>
				</div>
			</form>
		</div>
	</div>
	<?php
	rpress_menu_export_inline_assets();
}
/**
 * Inline styles + behaviour for the full-width menu export screen.
 *
 * Kept alongside the page so the exporter remains self-contained; the heavy
 * lifting (AJAX batching, progress bar) is handled by the shared
 * RPRESS_Export handler bound to .rpress-export-form.
 *
 * @since 3.3
 * @return void
 */
function rpress_menu_export_inline_assets()
{
	?>
	<style>
		.rp-menu-export-card { max-width: 960px; padding: 0; }
		.rp-export-row { display: flex; gap: 24px; padding: 22px 24px; border-bottom: 1px solid #f0f0f1; }
		.rp-export-row:last-of-type { border-bottom: 0; }
		.rp-export-row-label { flex: 0 0 240px; }
		.rp-export-row-label label { font-weight: 600; display: block; }
		.rp-export-row-label .rp-help-text { margin: 6px 0 0; }
		.rp-export-row-field { flex: 1 1 auto; min-width: 0; }
		.rp-export-col-tools { margin: 10px 0 0; }
		.rp-export-col-tools .button-link { color: #2271b1; }
		.rp-export-colgroup { margin: 0 0 16px; }
		.rp-export-colgroup:last-child { margin-bottom: 0; }
		.rp-export-colgroup-title { margin: 0 0 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #646970; }
		.rp-export-columns,
		.rp-export-categories { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 6px 18px; }
		.rp-export-categories { max-height: 260px; overflow-y: auto; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px; background: #fff; display: block; }
		.rp-export-cat { display: block; padding: 3px 0; }
		.rp-export-cat-count { color: #787c82; }
		.rp-export-inline-options { display: flex; flex-wrap: wrap; gap: 6px 18px; }
		.rp-export-col { display: flex; align-items: center; gap: 6px; }
		.rp-export-actions { padding: 22px 24px; }
		.rp-export-actions .rp-help-text { margin: 10px 0 0; }
		@media (max-width: 782px) {
			.rp-export-row { flex-direction: column; gap: 10px; }
			.rp-export-row-label { flex-basis: auto; }
		}
	</style>
	<script>
	( function () {
		var form = document.getElementById( 'rpress-export-menu' );
		if ( ! form ) { return; }
		function setCols( checked ) {
			var boxes = form.querySelectorAll( '.rp-export-columns input[type="checkbox"]:not([disabled])' );
			for ( var i = 0; i < boxes.length; i++ ) { boxes[ i ].checked = checked; }
		}
		var all  = form.querySelector( '.rp-export-select-all' );
		var none = form.querySelector( '.rp-export-select-none' );
		if ( all )  { all.addEventListener( 'click', function () { setCols( true ); } ); }
		if ( none ) { none.addEventListener( 'click', function () { setCols( false ); } ); }
	} )();
	</script>
	<?php
}
/**
 * Render the structured CSV menu importer (upload -> column map -> run).
 *
 * Drives RPRESS_Batch_FoodItems_Import through the shared RPRESS_Import JS, so it
 * ingests the exact export layout and updates existing items by ID/SKU - no AI.
 * Embedded in the "Spreadsheet" track of the menu import view, which is shared by
 * the onboarding wizard and the standalone Menu Items -> Import screen.
 *
 * @since 3.3
 * @return void
 */
function rpress_menu_csv_importer_form()
{
	if ( ! current_user_can( 'edit_products' ) ) {
		return;
	}
	// Each mapping row's data-field must match the export column header so a
	// RestroPress export auto-maps on upload (see RPRESS_Import in rp-admin.js).
	$fields = array(
		'id'                   => array( esc_html__( 'Menu Item ID', 'restropress' ), 'ID' ),
		'post_title'           => array( esc_html__( 'Name', 'restropress' ), 'Name' ),
		'categories'           => array( esc_html__( 'Categories', 'restropress' ), 'Categories' ),
		'price'                => array( esc_html__( 'Price(s)', 'restropress' ), 'Price' ),
		'variable_price_label' => array( esc_html__( 'Variable Price Label', 'restropress' ), 'Variable Price Label' ),
		'post_content'         => array( esc_html__( 'Description', 'restropress' ), 'Description' ),
		'post_excerpt'         => array( esc_html__( 'Short Description', 'restropress' ), 'Short Description' ),
		'tag_mark'             => array( esc_html__( 'Veg / Non-Veg', 'restropress' ), 'Veg / Non-Veg' ),
		'dietary'              => array( esc_html__( 'Dietary', 'restropress' ), 'Dietary' ),
		'tags'                 => array( esc_html__( 'Tags', 'restropress' ), 'Tags' ),
		'addons'               => array( esc_html__( 'Add-ons', 'restropress' ), 'Add-ons' ),
		'addon_prices'         => array( esc_html__( 'Add-on Prices', 'restropress' ), 'Add-on Prices' ),
		'addon_max'            => array( esc_html__( 'Max Add-ons', 'restropress' ), 'Max Add-ons' ),
		'default_addons'       => array( esc_html__( 'Default Add-ons', 'restropress' ), 'Default Add-ons' ),
		'addon_is_required'    => array( esc_html__( 'Add-ons Required', 'restropress' ), 'Add-ons Required' ),
		'featured_image'       => array( esc_html__( 'Featured Image', 'restropress' ), 'Featured Image' ),
		'sku'                  => array( esc_html__( 'SKU', 'restropress' ), 'SKU' ),
		'notes'                => array( esc_html__( 'Notes', 'restropress' ), 'Notes' ),
		'post_status'          => array( esc_html__( 'Status', 'restropress' ), 'Status' ),
		'post_name'            => array( esc_html__( 'Slug', 'restropress' ), 'Slug' ),
		'post_date'            => array( esc_html__( 'Date Created', 'restropress' ), 'Date Created' ),
		'post_author'          => array( esc_html__( 'Author', 'restropress' ), 'Author' ),
		'sales'                => array( esc_html__( 'Order Count', 'restropress' ), 'Order Count' ),
		'earnings'             => array( esc_html__( 'Total Revenue', 'restropress' ), 'Total Revenue' ),
	);
	?>
	<form id="rpress-import-fooditems" class="rpress-import-form rpress-import-export-form rp-csv-importer"
		action="<?php echo esc_url( add_query_arg( 'rpress_action', 'upload_import_file', admin_url() ) ); ?>"
		method="post" enctype="multipart/form-data">
		<div class="rpress-import-file-wrap">
			<?php wp_nonce_field( 'rpress_ajax_import', 'rpress_ajax_import' ); ?>
			<input type="hidden" name="rpress-import-class" value="RPRESS_Batch_FoodItems_Import" />
			<div class="rp-ob-dz rp-csv-dz" id="rp-csv-dz">
				<input name="rpress-import-file" id="rpress-fooditems-import-file" type="file" accept=".csv" hidden />
				<div class="rp-ob-dz-big" id="rp-csv-dz-ic">📄</div>
				<b class="rp-csv-dz-title"><?php esc_html_e( 'Drop your CSV here, or click to choose', 'restropress' ); ?></b>
				<small id="rp-csv-dzhint"><?php esc_html_e( 'CSV in the RestroPress layout · up to 10 MB', 'restropress' ); ?></small>
			</div>
			<div class="rp-csv-actions" id="rp-csv-actions" hidden>
				<input type="submit" value="<?php esc_attr_e( 'Upload &amp; map columns', 'restropress' ); ?>" class="button button-primary rp-btn rp-btn-primary" />
				<button type="button" class="button-link rp-csv-change"><?php esc_html_e( 'Choose a different file', 'restropress' ); ?></button>
				<span class="spinner"></span>
			</div>
		</div>
		<div class="rpress-import-options" id="rpress-import-fooditems-options" style="display:none;">
			<p><?php esc_html_e( 'Map each menu field to a column from your CSV. A RestroPress export maps itself automatically; ignore any field you do not want to import.', 'restropress' ); ?></p>
			<table class="widefat rpress_repeatable_table striped" width="100%" cellpadding="0" cellspacing="0">
				<thead>
					<tr>
						<th><strong><?php esc_html_e( 'Menu Item Field', 'restropress' ); ?></strong></th>
						<th><strong><?php esc_html_e( 'CSV Column', 'restropress' ); ?></strong></th>
						<th><strong><?php esc_html_e( 'Data Preview', 'restropress' ); ?></strong></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $fields as $field_key => $field ) :
						list( $label, $data_field ) = $field;
						?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td>
								<select name="rpress-import-field[<?php echo esc_attr( $field_key ); ?>]" class="rpress-import-csv-column" data-field="<?php echo esc_attr( $data_field ); ?>">
									<option value="">&mdash; <?php esc_html_e( 'Ignore this field', 'restropress' ); ?> &mdash;</option>
								</select>
							</td>
							<td class="rpress-import-preview-field"><?php esc_html_e( '- select field to preview data -', 'restropress' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="submit">
				<button class="rpress-import-proceed button-primary rp-btn rp-btn-primary"><?php esc_html_e( 'Run import', 'restropress' ); ?></button>
			</p>
		</div>
	</form>
	<style>
		.rp-csv-importer { margin-top: 4px; }
		.rp-csv-importer .rp-csv-dz { margin-top: 0; }
		.rp-csv-importer .rp-csv-dz.rp-csv-dz-has-file { border-style: solid; border-color: #46b450; background: #f6fbf6; cursor: default; }
		.rp-csv-actions { display: flex; align-items: center; gap: 14px; margin-top: 14px; }
		.rp-csv-actions .rp-csv-change { color: #2271b1; cursor: pointer; }
		.rp-csv-actions .spinner { float: none; margin: 0; }
		.rp-csv-importer .rpress-import-options { margin-top: 18px; }
		.rp-csv-importer .rpress-import-options > p:first-child { margin: 0 0 10px; color: #50575e; }
		.rp-csv-importer table.widefat { border-radius: 10px; overflow: hidden; box-shadow: none; }
		.rp-csv-importer table.widefat th { font-weight: 600; }
		.rp-csv-importer table.widefat td,
		.rp-csv-importer table.widefat th { padding: 10px 12px; vertical-align: middle; }
		.rp-csv-importer .rpress-import-csv-column { width: 100%; max-width: 260px; }
		.rp-csv-importer .rpress-import-preview-field { color: #787c82; max-width: 320px; overflow-wrap: anywhere; }
		.rp-csv-importer .submit { padding: 12px 0 0; margin: 0; }
		.rp-csv-importer .notice-wrap { margin-top: 12px; }
	</style>
	<?php
}
/**
 * Display the debug log tab
 *
 * @since 1.0.7
 * @return      void
 */
function rpress_tools_debug_log_display()
{
	global $rpress_logs;
	if (!current_user_can('manage_shop_settings') || !rpress_is_debug_mode()) {
		return;
	}
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e('Debug Log', 'restropress'); ?></span></h3>
		<div class="inside">
			<form id="rpress-debug-log" method="post">
				<textarea readonly="readonly" class="large-text" rows="15"
					name="rpress-debug-log-contents"><?php echo esc_textarea($rpress_logs->get_file_contents()); ?></textarea>
				<p class="submit">
					<input type="hidden" name="rpress_action" value="submit_debug_log" />
					<?php
					submit_button(esc_html__('Download Debug Log File', 'restropress'), 'primary', 'rpress-fooditem-debug-log', false);
					submit_button(esc_html__('Clear Log', 'restropress'), 'secondary rpress-inline-button', 'rpress-clear-debug-log', false);
					submit_button(esc_html__('Copy Entire Log', 'restropress'), 'secondary rpress-inline-button', 'rpress-copy-debug-log', false, array('onclick' => "this.form['rpress-debug-log-contents'].focus();this.form['rpress-debug-log-contents'].select();document.execCommand('copy');return false;"));
					?>
				</p>
				<?php wp_nonce_field('rpress-debug-log-action'); ?>
			</form>
			<p><?php esc_html_e('Log file', 'restropress'); ?>:
				<code><?php echo esc_url($rpress_logs->get_log_file_path()); ?></code></p>
		</div><!-- .inside -->
	</div><!-- .postbox -->
	<?php
}
/**
 * Handles submit actions for the debug log.
 *
 * @since 1.0
 */
function rpress_handle_submit_debug_log()
{
	global $rpress_logs;
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	check_admin_referer('rpress-debug-log-action');
	if (isset($_REQUEST['rpress-fooditem-debug-log'])) {
		nocache_headers();
		header('Content-Type: text/plain');
		header('Content-Disposition: attachment; filename="rpress-debug-log.txt"');
		echo esc_html(wp_strip_all_tags(sanitize_text_field(wp_unslash($_REQUEST['rpress-debug-log-contents']))));
		exit;
	} elseif (isset($_REQUEST['rpress-clear-debug-log'])) {
		// Clear the debug log.
		$rpress_logs->clear_log_file();
		wp_safe_redirect(admin_url('admin.php?page=rpress-tools&tab=diagnostics'));
		exit;
	}
}
add_action('rpress_submit_debug_log', 'rpress_handle_submit_debug_log');

/**
 * Display diagnostics tools.
 *
 * @since 3.3
 * @return void
 */
function rpress_tools_diagnostics_display()
{
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	$summary = rpress_tools_get_diagnostics_summary();
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e('Support Diagnostics', 'restropress'); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e('Use these tools when support, a developer, or a site manager needs environment details for order, checkout, payment, or menu issues.', 'restropress'); ?></p>
			<?php if (!rpress_is_debug_mode()) : ?>
				<p><?php esc_html_e('Debug logging is currently disabled, so only system information is shown.', 'restropress'); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<div class="postbox">
		<h3><span><?php esc_html_e('Restaurant Support Summary', 'restropress'); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e('This is the quick version to review before opening a support ticket or investigating a client report.', 'restropress'); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e('Area', 'restropress'); ?></th>
						<th><?php esc_html_e('Current Value', 'restropress'); ?></th>
						<th><?php esc_html_e('Why it matters', 'restropress'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($summary as $row) : ?>
						<tr>
							<td><strong><?php echo esc_html($row['label']); ?></strong></td>
							<td><?php echo esc_html($row['value']); ?></td>
							<td><?php echo esc_html($row['note']); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
	rpress_tools_sysinfo_display();
	if (rpress_is_debug_mode()) {
		rpress_tools_debug_log_display();
	}
}
add_action('rpress_tools_tab_diagnostics', 'rpress_tools_diagnostics_display');

/**
 * Build a readable diagnostics summary for support review.
 *
 * @since 3.3
 * @return array
 */
function rpress_tools_get_diagnostics_summary()
{
	$timezone_string = wp_timezone_string();
	$menu_page       = absint(rpress_get_option('food_items_page', 0));
	$checkout_page   = absint(rpress_get_option('purchase_page', 0));
	$success_page    = absint(rpress_get_option('success_page', 0));
	$failure_page    = absint(rpress_get_option('failure_page', 0));
	$gateways        = rpress_get_enabled_payment_gateways();
	$gateway_labels  = array();
	foreach ($gateways as $gateway) {
		if (!empty($gateway['admin_label'])) {
			$gateway_labels[] = $gateway['admin_label'];
		}
	}
	$service_mode = rpress_get_option('enable_service', 'delivery_and_pickup');
	$service_modes = array(
		'delivery_and_pickup' => __('Delivery and pickup', 'restropress'),
		'delivery'            => __('Delivery only', 'restropress'),
		'pickup'              => __('Pickup only', 'restropress'),
	);
	$uploads = wp_upload_dir();

	return apply_filters('rpress_tools_diagnostics_summary', array(
		array(
			'label' => __('RestroPress Version', 'restropress'),
			'value' => defined('RP_VERSION') ? RP_VERSION : __('Unknown', 'restropress'),
			'note'  => __('Support uses this to confirm feature availability and upgrade history.', 'restropress'),
		),
		array(
			'label' => __('WordPress / PHP', 'restropress'),
			'value' => sprintf('%1$s / %2$s', get_bloginfo('version'), PHP_VERSION),
			'note'  => __('Old platform versions can affect checkout, payments, imports, and background tasks.', 'restropress'),
		),
		array(
			'label' => __('Site Timezone', 'restropress'),
			'value' => sprintf('%1$s (%2$s)', $timezone_string, date_i18n(get_option('date_format') . ' ' . get_option('time_format'), current_time('timestamp'))),
			'note'  => __('Restaurant hours, service slots, dashboard dates, and reports depend on this.', 'restropress'),
		),
		array(
			'label' => __('Service Mode', 'restropress'),
			'value' => isset($service_modes[$service_mode]) ? $service_modes[$service_mode] : __('Not configured', 'restropress'),
			'note'  => __('This tells support whether delivery, pickup, or both should appear at checkout.', 'restropress'),
		),
		array(
			'label' => __('Ordering Window', 'restropress'),
			'value' => rpress_get_option('enable_always_open', false) ? __('Always open', 'restropress') : sprintf('%1$s - %2$s', rpress_get_option('open_time', __('Not set', 'restropress')), rpress_get_option('close_time', __('Not set', 'restropress'))),
			'note'  => __('Incorrect hours are a common reason customers cannot place orders.', 'restropress'),
		),
		array(
			'label' => __('Required Pages', 'restropress'),
			'value' => sprintf(
				/* translators: 1: menu page status, 2: checkout page status, 3: success page status, 4: failure page status */
				__('Menu: %1$s, Checkout: %2$s, Success: %3$s, Failed: %4$s', 'restropress'),
				$menu_page ? __('set', 'restropress') : __('missing', 'restropress'),
				$checkout_page ? __('set', 'restropress') : __('missing', 'restropress'),
				$success_page ? __('set', 'restropress') : __('missing', 'restropress'),
				$failure_page ? __('set', 'restropress') : __('missing', 'restropress')
			),
			'note'  => __('Missing pages usually break menu browsing, checkout, or post-payment redirects.', 'restropress'),
		),
		array(
			'label' => __('Payment Gateways', 'restropress'),
			'value' => !empty($gateway_labels) ? implode(', ', $gateway_labels) : __('None enabled', 'restropress'),
			'note'  => __('Gateway setup is the first thing to check for payment and failed-order reports.', 'restropress'),
		),
		array(
			'label' => __('REST API Base', 'restropress'),
			'value' => rest_url('rp/v1'),
			'note'  => __('Useful when checking integrations, mobile apps, or endpoint availability.', 'restropress'),
		),
		array(
			'label' => __('Debug Logging', 'restropress'),
			'value' => rpress_is_debug_mode() ? __('Enabled', 'restropress') : __('Disabled', 'restropress'),
			'note'  => __('Enable only when investigating issues, then turn it off after collecting logs.', 'restropress'),
		),
		array(
			'label' => __('Upload Directory', 'restropress'),
			'value' => empty($uploads['error']) ? __('Writable', 'restropress') : $uploads['error'],
			'note'  => __('Imports, exports, logs, and generated files can fail if uploads are not writable.', 'restropress'),
		),
	));
}
/**
 * Display the system info tab
 *
 * @since       2.0
 * @return      void
 */
function rpress_tools_sysinfo_display()
{
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	?>
	<div class="postbox">
		<h3><span><?php esc_html_e('Full System Report', 'restropress'); ?></span></h3>
		<div class="inside">
			<p><?php esc_html_e('Use the full report when support needs complete site, server, plugin, page, gateway, and environment details. It is intentionally detailed so it can be pasted into a private support ticket.', 'restropress'); ?></p>
			<form action="<?php echo esc_url(admin_url('admin.php?page=rpress-tools&tab=diagnostics')); ?>" method="post"
				dir="ltr">
				<textarea readonly="readonly" onclick="this.focus(); this.select()" id="system-info-textarea" name="rpress-sysinfo">
					<?php echo esc_textarea(rpress_tools_sysinfo_get()); ?>
				</textarea>
				<p class="submit">
					<input type="hidden" name="rpress-action" value="fooditem_sysinfo" />
					<?php submit_button('Download System Info File', 'primary', 'rpress-fooditem-sysinfo', false); ?>
					<?php submit_button(esc_html__('Copy Full Report', 'restropress'), 'secondary rpress-inline-button', 'rpress-copy-sysinfo', false, array('onclick' => "this.form['rpress-sysinfo'].focus();this.form['rpress-sysinfo'].select();document.execCommand('copy');return false;")); ?>
				</p>
			</form>
		</div>
	</div>
	<?php
}
/**
 * Get system info
 *
 * @since       2.0
 * @global      object $wpdb Used to query the database using the WordPress Database API
 * @return      string $return A string containing the info to output
 */
function rpress_tools_sysinfo_get()
{
	global $wpdb;
	if (!class_exists('Browser'))
		require_once RP_PLUGIN_DIR . 'includes/libraries/browser.php';
	$browser = new Browser();
	// Get theme info
	$theme_data = wp_get_theme();
	$theme = $theme_data->Name . ' ' . $theme_data->Version;
	$parent_theme = $theme_data->Template;
	if (!empty($parent_theme)) {
		$parent_theme_data = wp_get_theme($parent_theme);
		$parent_theme = $parent_theme_data->Name . ' ' . $parent_theme_data->Version;
	}
	// Try to identify the hosting provider
	$host = rpress_get_host();
	$return = '### Begin System Info ###' . "\n\n";
	// Start with the basics...
	$return .= '-- Site Info' . "\n\n";
	$return .= 'Site URL:                 ' . site_url() . "\n";
	$return .= 'Home URL:                 ' . home_url() . "\n";
	$return .= 'Multisite:                ' . (is_multisite() ? 'Yes' : 'No') . "\n";
	$return = apply_filters('rpress_sysinfo_after_site_info', $return);
	// Can we determine the site's host?
	if ($host) {
		$return .= "\n" . '-- Hosting Provider' . "\n\n";
		$return .= 'Host:                     ' . $host . "\n";
		$return = apply_filters('rpress_sysinfo_after_host_info', $return);
	}
	// The local users' browser information, handled by the Browser class
	$return .= "\n" . '-- User Browser' . "\n\n";
	$return .= $browser;
	$return = apply_filters('rpress_sysinfo_after_user_browser', $return);
	$locale = get_locale();
	// WordPress configuration
	$return .= "\n" . '-- WordPress Configuration' . "\n\n";
	$return .= 'Version:                  ' . get_bloginfo('version') . "\n";
	$return .= 'Language:                 ' . (!empty($locale) ? $locale : 'en_US') . "\n";
	$return .= 'Permalink Structure:      ' . (get_option('permalink_structure') ? get_option('permalink_structure') : 'Default') . "\n";
	$return .= 'Active Theme:             ' . $theme . "\n";
	if ($parent_theme !== $theme) {
		$return .= 'Parent Theme:             ' . $parent_theme . "\n";
	}
	$return .= 'Show On Front:            ' . get_option('show_on_front') . "\n";
	// Only show page specs if frontpage is set to 'page'
	if (get_option('show_on_front') == 'page') {
		$front_page_id = get_option('page_on_front');
		$blog_page_id = get_option('page_for_posts');
		$return .= 'Page On Front:            ' . ($front_page_id != 0 ? get_the_title($front_page_id) . ' (#' . $front_page_id . ')' : 'Unset') . "\n";
		$return .= 'Page For Posts:           ' . ($blog_page_id != 0 ? get_the_title($blog_page_id) . ' (#' . $blog_page_id . ')' : 'Unset') . "\n";
	}
	$return .= 'ABSPATH:                  ' . ABSPATH . "\n";
	// Make sure wp_remote_post() is working
	$request['cmd'] = '_notify-validate';
	$params = array(
		'sslverify' => false,
		'timeout' => 60,
		'user-agent' => 'RPRESS/' . RP_VERSION,
		'body' => $request
	);
	$response = wp_remote_post('https://www.paypal.com/cgi-bin/webscr', $params);
	if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300) {
		$WP_REMOTE_POST = 'wp_remote_post() works';
	} else {
		$WP_REMOTE_POST = 'wp_remote_post() does not work';
	}
	$return .= 'Remote Post:              ' . $WP_REMOTE_POST . "\n";
	$return .= 'Table Prefix:             ' . 'Length: ' . strlen($wpdb->prefix) . '   Status: ' . (strlen($wpdb->prefix) > 16 ? 'ERROR: Too long' : 'Acceptable') . "\n";

	$return .= 'WP_DEBUG:                 ' . (defined('WP_DEBUG') ? WP_DEBUG ? 'Enabled' : 'Disabled' : 'Not set') . "\n";
	$return .= 'Memory Limit:             ' . WP_MEMORY_LIMIT . "\n";
	$return .= 'Registered Post Stati:    ' . implode(', ', get_post_stati()) . "\n";
	$return = apply_filters('rpress_sysinfo_after_wordpress_config', $return);
	// RPRESS configuration
	$return .= "\n" . '-- RPRESS Configuration' . "\n\n";
	$return .= 'Version:                  ' . RP_VERSION . "\n";
	$return .= 'Upgraded From:            ' . get_option('rpress_version_upgraded_from', 'None') . "\n";
	$return .= 'Test Mode:                ' . (rpress_is_test_mode() ? "Enabled\n" : "Disabled\n");
	$return .= 'AJAX:                     ' . (!rpress_is_ajax_disabled() ? "Enabled\n" : "Disabled\n");
	$return .= 'Guest Checkout:           ' . (rpress_no_guest_checkout() ? "Disabled\n" : "Enabled\n");
	$return .= 'File Access Method:       ' . ucfirst(rpress_get_file_fooditem_method()) . "\n";
	$return .= 'Currency Code:            ' . rpress_get_currency() . "\n";
	$return .= 'Currency Position:        ' . rpress_get_option('currency_position', 'before') . "\n";
	$return .= 'Currency Value Type:      ' . rpress_get_currency_value_type() . "\n";
	$return .= 'Decimal Separator:        ' . rpress_get_option('decimal_separator', '.') . "\n";
	$return .= 'Thousands Separator:      ' . rpress_get_option('thousands_separator', ',') . "\n";
	$return .= 'Upgrades Completed:       ' . implode(',', rpress_get_completed_upgrades()) . "\n";
	$return .= 'Menu File Link Expiration:' . rpress_get_option('fooditem_link_expiration') . " hour(s)\n";
	$return = apply_filters('rpress_sysinfo_after_rpress_config', $return);
	// RPRESS pages
	$menu_page = rpress_get_option('food_items_page', '');
	$purchase_page = rpress_get_option('purchase_page', '');
	$success_page = rpress_get_option('success_page', '');
	$failure_page = rpress_get_option('failure_page', '');
	$return .= "\n" . '-- RPRESS Page Configuration' . "\n\n";
	$return .= 'Food Menu:                 ' . (!empty($menu_page) ? "Valid\n" : "Invalid\n");
	$return .= 'Checkout:                 ' . (!empty($purchase_page) ? "Valid\n" : "Invalid\n");
	$return .= 'Checkout Page:            ' . (!empty($purchase_page) ? get_permalink($purchase_page) . "\n" : "Unset\n");
	$return .= 'Success Page:             ' . (!empty($success_page) ? get_permalink($success_page) . "\n" : "Unset\n");
	$return .= 'Failure Page:             ' . (!empty($failure_page) ? get_permalink($failure_page) . "\n" : "Unset\n");
	$return .= 'RestroPress Slug:           ' . (defined('RPRESS_SLUG') ? '/' . RPRESS_SLUG . "\n" : "/fooditems\n");
	$return = apply_filters('rpress_sysinfo_after_rpress_pages', $return);
	// RPRESS gateways
	$return .= "\n" . '-- RPRESS Gateway Configuration' . "\n\n";
	$active_gateways = rpress_get_enabled_payment_gateways();
	if ($active_gateways) {
		$default_gateway_is_active = rpress_is_gateway_active(rpress_get_default_gateway());
		if ($default_gateway_is_active) {
			$default_gateway = rpress_get_default_gateway();
			$default_gateway = $active_gateways[$default_gateway]['admin_label'];
		} else {
			$default_gateway = 'Test Payment';
		}
		$gateways = array();
		foreach ($active_gateways as $gateway) {
			$gateways[] = $gateway['admin_label'];
		}
		$return .= 'Enabled Gateways:         ' . implode(', ', $gateways) . "\n";
		$return .= 'Default Gateway:          ' . $default_gateway . "\n";
	} else {
		$return .= 'Enabled Gateways:         None' . "\n";
	}
	$return = apply_filters('rpress_sysinfo_after_rpress_gateways', $return);
	// RPRESS Taxes
	$return .= "\n" . '-- RPRESS Tax Configuration' . "\n\n";
	$return .= 'Taxes:                    ' . (rpress_use_taxes() ? "Enabled\n" : "Disabled\n");
	$return .= 'Tax Rate:                 ' . rpress_get_tax_rate() * 100 . "\n";
	$return .= 'Display On Checkout:      ' . (rpress_get_option('checkout_include_tax', false) ? "Displayed\n" : "Not Displayed\n");
	$return .= 'Prices Include Tax:       ' . (rpress_prices_include_tax() ? "Yes\n" : "No\n");
	$return = apply_filters('rpress_sysinfo_after_rpress_taxes', $return);
	// RPRESS Templates
	$dir = get_stylesheet_directory() . '/rpress_templates/*';
	if (is_dir($dir) && (count(glob("$dir/*")) !== 0)) {
		$return .= "\n" . '-- RPRESS Template Overrides' . "\n\n";
		foreach (glob($dir) as $file) {
			$return .= 'Filename:                 ' . basename($file) . "\n";
		}
		$return = apply_filters('rpress_sysinfo_after_rpress_templates', $return);
	}
	// Get plugins that have an update
	$updates = get_plugin_updates();
	// Must-use plugins
	// NOTE: MU plugins can't show updates!
	$muplugins = get_mu_plugins();
	if (count($muplugins) > 0) {
		$return .= "\n" . '-- Must-Use Plugins' . "\n\n";
		foreach ($muplugins as $plugin => $plugin_data) {
			$return .= $plugin_data['Name'] . ': ' . $plugin_data['Version'] . "\n";
		}
		$return = apply_filters('rpress_sysinfo_after_wordpress_mu_plugins', $return);
	}
	// WordPress active plugins
	$return .= "\n" . '-- WordPress Active Plugins' . "\n\n";
	$plugins = get_plugins();
	$active_plugins = get_option('active_plugins', array());
	foreach ($plugins as $plugin_path => $plugin) {
		if (!in_array($plugin_path, $active_plugins))
			continue;
		$update = (array_key_exists($plugin_path, $updates)) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
		$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
	}
	$return = apply_filters('rpress_sysinfo_after_wordpress_plugins', $return);
	// WordPress inactive plugins
	$return .= "\n" . '-- WordPress Inactive Plugins' . "\n\n";
	foreach ($plugins as $plugin_path => $plugin) {
		if (in_array($plugin_path, $active_plugins))
			continue;
		$update = (array_key_exists($plugin_path, $updates)) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
		$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
	}
	$return = apply_filters('rpress_sysinfo_after_wordpress_plugins_inactive', $return);
	if (is_multisite()) {
		// WordPress Multisite active plugins
		$return .= "\n" . '-- Network Active Plugins' . "\n\n";
		$plugins = wp_get_active_network_plugins();
		$active_plugins = get_site_option('active_sitewide_plugins', array());
		foreach ($plugins as $plugin_path) {
			$plugin_base = plugin_basename($plugin_path);
			if (!array_key_exists($plugin_base, $active_plugins))
				continue;
			$update = (array_key_exists($plugin_path, $updates)) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
			$plugin = get_plugin_data($plugin_path);
			$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
		}
		$return = apply_filters('rpress_sysinfo_after_wordpress_ms_plugins', $return);
	}
	// Server configuration (really just versioning)
	$return .= "\n" . '-- Webserver Configuration' . "\n\n";
	$return .= 'PHP Version:              ' . PHP_VERSION . "\n";
	$return .= 'MySQL Version:            ' . $wpdb->db_version() . "\n";
	$return .= 'Webserver Info:           ' . $_SERVER['SERVER_SOFTWARE'] . "\n";
	$return = apply_filters('rpress_sysinfo_after_webserver_config', $return);
	// PHP configs... now we're getting to the important stuff
	$return .= "\n" . '-- PHP Configuration' . "\n\n";
	$return .= 'Memory Limit:             ' . ini_get('memory_limit') . "\n";
	$return .= 'Upload Max Size:          ' . ini_get('upload_max_filesize') . "\n";
	$return .= 'Post Max Size:            ' . ini_get('post_max_size') . "\n";
	$return .= 'Upload Max Filesize:      ' . ini_get('upload_max_filesize') . "\n";
	$return .= 'Time Limit:               ' . ini_get('max_execution_time') . "\n";
	$return .= 'Max Input Vars:           ' . ini_get('max_input_vars') . "\n";
	$return .= 'Display Errors:           ' . (ini_get('display_errors') ? 'On (' . ini_get('display_errors') . ')' : 'N/A') . "\n";
	$return .= 'PHP Arg Separator:        ' . rpress_get_php_arg_separator_output() . "\n";
	$return = apply_filters('rpress_sysinfo_after_php_config', $return);
	// PHP extensions and such
	$return .= "\n" . '-- PHP Extensions' . "\n\n";
	$return .= 'cURL:                     ' . (function_exists('curl_init') ? 'Supported' : 'Not Supported') . "\n";
	$return .= 'fsockopen:                ' . (function_exists('fsockopen') ? 'Supported' : 'Not Supported') . "\n";
	$return .= 'SOAP Client:              ' . (class_exists('SoapClient') ? 'Installed' : 'Not Installed') . "\n";
	$return .= 'Suhosin:                  ' . (extension_loaded('suhosin') ? 'Installed' : 'Not Installed') . "\n";
	$return = apply_filters('rpress_sysinfo_after_php_ext', $return);
	// Session stuff
	$return .= "\n" . '-- Session Configuration' . "\n\n";
	$return .= 'RPRESS Use Sessions:         ' . (defined('RPRESS_USE_PHP_SESSIONS') && RPRESS_USE_PHP_SESSIONS ? 'Enforced' : (RPRESS()->session->use_php_sessions() ? 'Enabled' : 'Disabled')) . "\n";
	$return .= 'Session:                  ' . (isset($_SESSION) ? 'Enabled' : 'Disabled') . "\n";
	// The rest of this is only relevant is session is enabled
	if (isset($_SESSION)) {
		$return .= 'Session Name:             ' . esc_html(ini_get('session.name')) . "\n";
		$return .= 'Cookie Path:              ' . esc_html(ini_get('session.cookie_path')) . "\n";
		$return .= 'Save Path:                ' . esc_html(ini_get('session.save_path')) . "\n";
		$return .= 'Use Cookies:              ' . (ini_get('session.use_cookies') ? 'On' : 'Off') . "\n";
		$return .= 'Use Only Cookies:         ' . (ini_get('session.use_only_cookies') ? 'On' : 'Off') . "\n";
	}
	$return = apply_filters('rpress_sysinfo_after_session_config', $return);
	$return .= "\n" . '### End System Info ###';
	return $return;
}
/**
 * Generates a System Info fooditem file
 *
 * @since       2.0
 * @return      void
 */
function rpress_tools_sysinfo_fooditem()
{
	if (!current_user_can('manage_shop_settings')) {
		return;
	}
	nocache_headers();
	header('Content-Type: text/plain');
	header('Content-Disposition: attachment; filename="rpress-system-info.txt"');
	echo esc_html(wp_strip_all_tags(sanitize_text_field(wp_unslash($_POST['rpress-sysinfo']))));
	rpress_die();
}
add_action('rpress_fooditem_sysinfo', 'rpress_tools_sysinfo_fooditem');
