<?php
/**
 * Admin Pages
 *
 * @package     RPRESS
 * @subpackage  Admin/Pages
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */
// Exit if accessed directly
defined('ABSPATH') || exit;
if (class_exists('RP_Admin_Menus', false)) {
	return new RP_Admin_Menus();
}
/**
 * RP_Admin_Menus Class.
 */
class RP_Admin_Menus
{
	/**
	 * Hook in tabs.
	 */
	public function __construct()
	{
		// Add menus.
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('admin_menu', array($this, 'menu_order_count'));
		// add_action('admin_menu', array($this, 'rpress_add_setup_wizard_menu'));


		//Custom menu ordering
		add_filter('custom_menu_order', '__return_true');
		add_filter('menu_order', array($this, 'menu_order'));
	}
	/**
	 * Add menu items.
	 */
	public function admin_menu()
	{
		global $menu;
		$menu[] = array('', 'read', 'separator-restropress', '', 'wp-menu-separator restropress');
		$rpress_payment = get_post_type_object('rpress_payment');
		$customer_view_role = apply_filters('rpress_view_customers_role', 'view_shop_reports');
		add_menu_page(esc_html__('RestroPress', 'restropress'), esc_html__('RestroPress', 'restropress'), 'manage_shop_settings', 'restropress', null, null, '55.5');
		//Added version 3.1
		add_submenu_page('restropress', esc_html__('Dashboard', 'restropress'), esc_html__('Dashboard', 'restropress'), $customer_view_role, 'rpress-dashboard', array($this, 'rpress_dashboard_page'), null, null);
		add_submenu_page('', __( 'Home', 'restropress' ), __( 'Home', 'restropress' ), 'manage_shop_settings', 'rpress-setup', 'rpress_admin_home_page', null , null );
		add_submenu_page('restropress', $rpress_payment->labels->name, $rpress_payment->labels->menu_name, 'edit_shop_payments', 'rpress-payment-history', 'rpress_payment_history_page', null, null);
		add_submenu_page('restropress', esc_html__('Customers', 'restropress'), esc_html__('Customers', 'restropress'), $customer_view_role, 'rpress-customers', 'rpress_customers_page', null, null);
		add_submenu_page('restropress', esc_html__('Discount Codes', 'restropress'), esc_html__('Discount Codes', 'restropress'), 'manage_shop_discounts', 'rpress-discounts', 'rpress_discounts_page');
		add_submenu_page('restropress', esc_html__('Earnings and Sales Reports', 'restropress'), esc_html__('Reports', 'restropress'), 'view_shop_reports', 'rpress-reports', 'rpress_reports_page');
		add_submenu_page('restropress', esc_html__('RestroPress Settings', 'restropress'), esc_html__('Settings', 'restropress'), 'manage_shop_settings', 'rpress-settings', 'rpress_options_page');
		add_submenu_page('restropress', esc_html__('RestroPress Info and Tools', 'restropress'), esc_html__('Tools', 'restropress'), 'manage_shop_settings', 'rpress-tools', 'rpress_tools_page');
		add_submenu_page('restropress', esc_html__('RestroPress Extensions', 'restropress'), '<span style="color:#f39c12;">' . esc_html__('Extensions', 'restropress') . '</span>', 'manage_shop_settings', 'rpress-extensions', 'rpress_extensions_page');
		// Remove the additional restropress menu
		remove_submenu_page('restropress', 'restropress');
	}
	public function rpress_dashboard_page()
	{
		// Define the path to the file
		// Get the plugin directory path
		$plugin_dir = plugin_dir_path(__FILE__);
		// Define the relative path to the file within the plugin
		$file_relative_path = '/dashboard/rp-dashboard.php';

		// Build the full path to the file
		$file_path = $plugin_dir . $file_relative_path;

		// Check if the file exists before including it
		if (file_exists($file_path)) {
			// Include the file
			include_once($file_path);
		} else {
			// Display an error message if the file doesn't exist
			echo esc_html("Error: File not found - $file_relative_path");
		}
	}
	/**
	 * Adds the order pending count to the menu.
	 */
	public function menu_order_count()
	{
		global $submenu;
		if (isset($submenu['restropress'])) {
			// Remove 'RestroPress' sub menu item.
			unset($submenu['restropress'][0]);
			// Add count if user has access.
			if (apply_filters('rpress_include_pending_order_count_in_menu', true) && current_user_can('edit_shop_payments')) {
				$order_count = apply_filters('rpress_menu_order_count', rp_get_order_count('pending'));
				if ($order_count) {
					foreach ($submenu['restropress'] as $key => $menu_item) {
						if (0 === strpos($menu_item[0], _x('Orders', 'Admin menu name', 'restropress'))) {
							$submenu['restropress'][$key][0] .= ' <span class="awaiting-mod update-plugins count-' . esc_attr($order_count) . '"><span class="processing-count">' . number_format_i18n($order_count) . '</span></span>';
							break;
						}
					}
				}
			}
		}
	}

	public function rpress_add_setup_wizard_menu()
	{
		add_submenu_page(
			null, // ← no menu entry, hidden
			__('RestroPress Setup', 'restropress'),
			'', // No label in menu
			'manage_options',
			'rpress-setup',
			[$this, 'rpress_admin_home_page'],
		);
	}

	public function rpress_setup_wizard_page_callback()
	{
		$enable_service = get_option('enable_service', 'delivery_and_pickup');
		$default_service = get_option('default_service', 'delivery');
		// $default_time = get_option('default_time', '9:00am');
		?>
		<div class="wrap">
			<h1>RestroPress Setup Wizard</h1>
			<form method="post" id="rpress-setup-form">
				<?php wp_nonce_field('my_plugin_save_setup', 'my_plugin_setup_nonce'); ?>

				<!-- Step 1 -->
				<div class="rpress-setup-step" data-step="1" style="display:block;">
					<h2>Step 1: Choose Services</h2>
					<p>
						<label><input type="radio" name="enable_service" value="delivery_and_pickup" <?php checked($enable_service, 'delivery_and_pickup'); ?>> Both Delivery and Pickup</label><br>
						<label><input type="radio" name="enable_service" value="delivery" <?php checked($enable_service, 'delivery'); ?>> Delivery Only</label><br>
						<label><input type="radio" name="enable_service" value="pickup" <?php checked($enable_service, 'pickup'); ?>> Pickup Only</label>
					</p>
					<button type="button" class="button button-primary next-step">Next</button>
				</div>

				<!-- Step 2 -->
				<div class="rpress-setup-step" data-step="2" style="display:none;">
					<h2>Step 2: Default Settings</h2>
					<p>
						<label><input type="radio" name="default_service" value="delivery" <?php checked($default_service, 'delivery'); ?>> Delivery</label><br>
						<label><input type="radio" name="default_service" value="pickup" <?php checked($default_service, 'pickup'); ?>> Pickup</label>
					</p>
					<button type="button" class="button back-step">Back</button>
					<button type="button" class="button button-primary next-step">Next</button>
				</div>

				<!-- Step 3 -->
				<div class="rpress-setup-step" data-step="3" style="display:none;">
					<h2>Step 3: Finish</h2>
					<p>You're all set! Click below to save and complete setup.</p>
					<button type="button" class="button back-step">Back</button>
					<?php submit_button('Save & Finish Setup'); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Reorder the RestroPress menu items in admin.
	 *
	 * @param int $menu_order Menu order.
	 * @return array
	 */
	public function menu_order($menu_order)
	{
		// Initialize our custom order array.
		$rpress_menu_order = array();
		// Get the index of our custom separator.
		$rpress_separator = array_search('separator-restropress', $menu_order, true);
		// Get index of fooditem menu.
		$rpress_fooditems = array_search('edit.php?post_type=fooditem', $menu_order, true);
		//Remove the custom separator and fooditems menu so that we can re-order them
		unset($menu_order[$rpress_separator]);
		unset($menu_order[$rpress_fooditems]);
		// Loop through menu order and do some rearranging.
		foreach ($menu_order as $index => $item) {
			if ('restropress' === $item) {
				$rpress_menu_order[] = 'separator-restropress';
				$rpress_menu_order[] = $item;
				$rpress_menu_order[] = 'edit.php?post_type=fooditem';
			} elseif (!in_array($item, array('separator-restropress'), true)) {
				$rpress_menu_order[] = $item;
			}
		}
		// Return order.
		return $rpress_menu_order;
	}
}
return new RP_Admin_Menus();
