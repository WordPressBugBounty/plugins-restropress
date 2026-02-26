<?php
/**
 * Plugin Name: RestroPress
 * Plugin URI: https://www.restropress.com
 * Description: RestroPress is an online ordering system for WordPress.
 * Version: 3.2.5
 * Author: MagniGenie
 * Author URI: https://magnigenie.com
 * Text Domain: restropress
 * Domain Path: languages
 *
 * @package RPRESS
 */

// Source - https://stackoverflow.com/a
// Posted by Fancy John, modified by community. See post 'Timeline' for change history
// Retrieved 2025-11-29, License - CC BY-SA 4.0

// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);

defined('ABSPATH') || exit;
if (!defined('RP_PLUGIN_FILE')) {
	define('RP_PLUGIN_FILE', __FILE__);
}
// Include the main RestroPress class.
if (!class_exists('RestroPress', false)) {
	include_once dirname(__FILE__) . '/includes/class-rpress.php';
}
/**
 * Returns the main instance of RestroPress.
 *
 * @return RestroPress
 */
function RPRESS()
{
	
	return RestroPress::instance();
}
//Get RestroPress Running.
RPRESS();