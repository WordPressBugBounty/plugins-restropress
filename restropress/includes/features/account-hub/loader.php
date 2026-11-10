<?php
/**
 * Customer account hub (3.5, item 11).
 *
 * - Saved delivery addresses: an address book in user meta, a picker and a
 *   "Save this address" option at checkout, management in the customer
 *   dashboard.
 * - One-tap reorder: on by default for new stores, surfaced to existing stores
 *   through a one-time Settings notice; skips items that can no longer be
 *   bought and tells the customer which ones.
 * - Live tracking: every order in the history links to its receipt, which
 *   shows the live order status.
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RPRESS_ACCOUNT_HUB_DIR', __DIR__ . '/' );

require_once RPRESS_ACCOUNT_HUB_DIR . 'addresses.php';
require_once RPRESS_ACCOUNT_HUB_DIR . 'reorder.php';
require_once RPRESS_ACCOUNT_HUB_DIR . 'frontend.php';

if ( is_admin() ) {
	require_once RPRESS_ACCOUNT_HUB_DIR . 'admin.php';
}
