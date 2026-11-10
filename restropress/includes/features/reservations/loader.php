<?php
/**
 * Basic table reservations (free, 3.5)
 *
 * A reservation form (shortcode [rpress_reservation] and the "Table
 * Reservation" block) with time slots derived from store hours, a per-slot
 * covers capacity, an admin list with confirm/decline, and customer/admin
 * emails including a reminder.
 *
 * Off by default: turn on under General > Reservations, which also creates
 * the booking page (see pages.php).
 *
 * Storage is a non-public post type `rpress_reservation` with meta prefixed
 * `_rpress_res_`. The paid Table Booking extension keeps its own data
 * (`rp_tb_bookings`, [table_booking], `table_booking_*` options); nothing
 * here reads or writes it.
 *
 * @package     RPRESS
 * @subpackage  Features/Reservations
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RPRESS_RESERVATIONS_DIR', __DIR__ . '/' );
define( 'RPRESS_RESERVATIONS_ASSETS_URL', RP_PLUGIN_URL . 'assets/features/reservations/' );

require_once RPRESS_RESERVATIONS_DIR . 'functions.php';
require_once RPRESS_RESERVATIONS_DIR . 'pages.php';
require_once RPRESS_RESERVATIONS_DIR . 'settings.php';

rpress_reservation_maybe_migrate_switch();

// Off by default (General > Reservations). When off, only the switch and the
// page setting load; saved reservations stay in the database untouched.
if ( ! rpress_reservations_enabled() ) {
	// Pages that still hold the shortcode show nothing instead of the raw tag.
	add_shortcode( 'rpress_reservation', '__return_empty_string' );
	return;
}

require_once RPRESS_RESERVATIONS_DIR . 'post-type.php';
require_once RPRESS_RESERVATIONS_DIR . 'emails.php';
require_once RPRESS_RESERVATIONS_DIR . 'frontend.php';
require_once RPRESS_RESERVATIONS_DIR . 'account.php';

if ( is_admin() ) {
	require_once RPRESS_RESERVATIONS_DIR . 'admin.php';
}
