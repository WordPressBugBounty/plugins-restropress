<?php
/**
 * Admin onboarding entrypoint.
 *
 * @package RPRESS\Admin\Home
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the RestroPress setup and launch hub.
 *
 * @return void
 */
function rpress_admin_home_page() {
	if ( ! class_exists( 'RPress_Onboarding' ) ) {
		require_once RP_PLUGIN_DIR . 'includes/admin/home/class-rpress-onboarding.php';
	}

	RPress_Onboarding::render();
}
