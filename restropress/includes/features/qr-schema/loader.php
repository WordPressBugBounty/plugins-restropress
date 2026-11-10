<?php
/**
 * Feature: menu QR code + Google rich results (schema.org JSON-LD).
 *
 * - Settings > General > QR Code & SEO: printable QR code for the menu page
 *   (or any page/URL) with an optional table label, downloadable as SVG/PNG.
 *   Generated locally by lib/class-rpress-qr-encoder.php (no web service).
 * - JSON-LD Restaurant > Menu > MenuSection > MenuItem on menu pages.
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

define( 'RPRESS_QR_SCHEMA_DIR', __DIR__ . '/' );

require_once RPRESS_QR_SCHEMA_DIR . 'lib/class-rpress-qr-encoder.php';
require_once RPRESS_QR_SCHEMA_DIR . 'class-rpress-menu-qr.php';
require_once RPRESS_QR_SCHEMA_DIR . 'class-rpress-menu-schema.php';

/**
 * Public store name: the RestroPress restaurant name, else the site title.
 *
 * @since 3.5
 * @return string
 */
function rpress_qr_schema_store_name() {
	$name = function_exists( 'rpress_get_option' ) ? (string) rpress_get_option( 'restaurant_name', '' ) : '';
	if ( '' === trim( $name ) ) {
		$name = (string) get_bloginfo( 'name' );
	}
	return trim( wp_strip_all_tags( html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) ) );
}

RPRESS_Menu_QR::init();
RPRESS_Menu_Schema::init();
