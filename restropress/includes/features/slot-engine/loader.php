<?php
/**
 * Slot engine (3.5): one tested service for store hours, slots and pre-order dates.
 *
 * @package     RPRESS
 * @subpackage  Features/SlotEngine
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/class-rpress-service-hours.php';

// Engine API level for extensions (2 = several windows per day, last-order buffer, lead time).
if ( ! defined( 'RPRESS_SERVICE_HOURS_API' ) ) {
	define( 'RPRESS_SERVICE_HOURS_API', RPRESS_Service_Hours::API_VERSION );
}
require_once __DIR__ . '/functions.php';
