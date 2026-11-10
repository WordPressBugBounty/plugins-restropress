<?php
/**
 * Feature: reminder emails for scheduled orders (3.5, free).
 *
 * Orders placed for a later pickup/delivery time get one customer email N
 * minutes (default 30) before that time. A single WP-Cron event is scheduled per
 * order, and a throttled check on init sends anything that is due, so sites with
 * WP-Cron disabled still get their reminders. ASAP orders are never reminded.
 *
 * Settings live at Settings > Emails > Order Notifications > "Order Reminder".
 *
 * @package RestroPress
 * @since   3.5
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/functions.php';

if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}
