<?php
/**
 * Feature: webhooks (3.5.1, free).
 *
 * Sends signed JSON events about orders, customers, reservations and menu
 * items to URLs the shop manager adds at Settings > General > Webhooks.
 * Inert until an endpoint exists or an extension registers a delivery target
 * type: no queue row, no cron event and no HTTP request before that.
 *
 * - install.php: deliveries table (queue and log), DB version option.
 * - functions.php: event registry, signing, retry schedule, URL rule,
 *   endpoints store, delivery target type registry, trigger decisions.
 * - payloads.php: the public JSON schema (envelope and resources), samples.
 * - triggers.php: listeners on core hooks, they only queue rows.
 * - delivery.php: dispatch, cron worker, retries, resend, send test, pruning.
 * - admin.php: the settings section and its AJAX handler (admin only).
 *
 * HTTP calls never run inside the request that triggers an event (checkout,
 * an order status update, a menu edit): the trigger queues rows and the cron
 * event rpress_webhooks_process_queue does the sending.
 *
 * Extensions deliver through the same queue, signature, retries, log and
 * resend with rpress_webhooks_register_target_type(). The payload schema is
 * "api_version": "1"; RPRESS_WEBHOOKS_API_VERSION versions the PHP API.
 *
 * @package RestroPress
 * @since   3.5.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version of the public PHP API extensions build against.
 */
define( 'RPRESS_WEBHOOKS_API_VERSION', '1.0' );

/**
 * Schema version of the deliveries table.
 */
define( 'RPRESS_WEBHOOKS_DB_VERSION', '1' );

require_once __DIR__ . '/install.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/payloads.php';
require_once __DIR__ . '/triggers.php';
require_once __DIR__ . '/delivery.php';

if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}

add_action( 'admin_init', 'rpress_webhooks_maybe_install' );
add_filter( 'wpmu_drop_tables', 'rpress_webhooks_wpmu_drop_tables', 10, 2 );

// Orders.
add_action( 'rpress_insert_payment', 'rpress_webhooks_on_insert_payment', 20, 1 );
add_action( 'rpress_update_payment_status', 'rpress_webhooks_on_payment_status', 10, 3 );
// Runs after the status transaction commits (see rpress_update_order_status_with_meta()).
add_action( 'rpress_update_order_status', 'rpress_webhooks_on_order_status', 30, 2 );

// Customers.
add_action( 'rpress_customer_pre_create', 'rpress_webhooks_before_customer_create', 10, 1 );
add_action( 'rpress_customer_post_create', 'rpress_webhooks_on_customer_created', 10, 2 );

// Reservations.
add_action( 'rpress_reservation_created', 'rpress_webhooks_on_reservation_created', 20, 1 );

// Menu item availability: there is no action for it, the meta write is the signal.
add_action( 'update_post_meta', 'rpress_webhooks_before_stock_update', 10, 3 );
add_action( 'updated_post_meta', 'rpress_webhooks_on_stock_updated', 10, 4 );
add_action( 'added_post_meta', 'rpress_webhooks_on_stock_added', 10, 4 );

// Delivery worker (single cron events scheduled by rpress_webhooks_dispatch()) and daily log pruning.
add_action( 'rpress_webhooks_process_queue', 'rpress_webhooks_process_queue' );
add_action( 'rpress_daily_scheduled_events', 'rpress_webhooks_prune_log' );
