<?php
/**
 * Reservations: on/off switch and the booking page.
 *
 * Loaded even when reservations are off, so the switch and the page setting
 * are there to find. Turning reservations on creates the booking page (or
 * adopts an existing one) and stores it in the "Reservation Page" setting.
 *
 * @package     RPRESS
 * @subpackage  Features/Reservations
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Whether table reservations are turned on (off by default).
 *
 * @since 3.5
 * @return bool
 */
function rpress_reservations_enabled() {
	return (bool) apply_filters( 'rpress_reservations_enabled', (bool) rpress_get_option( 'enable_reservations', false ) );
}

/**
 * Once per site: sites that already took reservations on an early 3.5 build
 * (before the switch existed) keep them on, with their booking page selected.
 * Everyone else starts with reservations off.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_maybe_migrate_switch() {
	if ( get_option( 'rpress_reservations_switch_set' ) ) {
		return;
	}
	update_option( 'rpress_reservations_switch_set', 1 );
	global $wpdb, $rpress_options;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- runs once.
	$has = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'rpress_reservation' LIMIT 1" );
	if ( ! $has ) {
		return;
	}
	$settings = get_option( 'rpress_settings' );
	$settings = is_array( $settings ) ? $settings : array();
	$settings['enable_reservations'] = 1;
	$page_id = rpress_reservation_find_booking_page();
	if ( $page_id && empty( $settings['reservation_page'] ) ) {
		$settings['reservation_page'] = $page_id;
	}
	// Stored as is: no page is created this early in the load.
	remove_filter( 'pre_update_option_rpress_settings', 'rpress_reservation_settings_before_save', 10 );
	update_option( 'rpress_settings', $settings );
	add_filter( 'pre_update_option_rpress_settings', 'rpress_reservation_settings_before_save', 10, 2 );
	$rpress_options = array_merge( (array) $rpress_options, array_intersect_key( $settings, array_flip( array( 'enable_reservations', 'reservation_page' ) ) ) );
}

/**
 * Published page with the booking form ([rpress_reservation] or the Table
 * Reservation block), found by searching page content. Oldest first.
 *
 * @since 3.5
 * @return int Page ID or 0.
 */
function rpress_reservation_find_booking_page() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- callers cache the result.
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s ) ORDER BY ID ASC LIMIT 1",
			'%' . $wpdb->esc_like( '[rpress_reservation]' ) . '%',
			'%' . $wpdb->esc_like( '[rpress_reservation ' ) . '%',
			'%' . $wpdb->esc_like( '<!-- wp:rpress/reservation-form' ) . '%'
		)
	);
}

/**
 * Whether a page ID points at a published page.
 *
 * @since 3.5
 * @param int $page_id Page ID.
 * @return bool
 */
function rpress_reservation_is_live_page( $page_id ) {
	$page_id = absint( $page_id );
	return $page_id && 'page' === get_post_type( $page_id ) && 'publish' === get_post_status( $page_id );
}

/**
 * The booking page: the "Reservation Page" setting when it is published,
 * otherwise a page found by its content (stores that added the form by hand).
 *
 * @since 3.5
 * @return int Page ID or 0.
 */
function rpress_reservation_booking_page_id() {
	$page_id = absint( rpress_get_option( 'reservation_page', 0 ) );
	if ( rpress_reservation_is_live_page( $page_id ) ) {
		return $page_id;
	}
	$found = get_transient( 'rpress_reservation_booking_page' );
	if ( false === $found ) {
		$found = rpress_reservation_find_booking_page();
		set_transient( 'rpress_reservation_booking_page', $found, DAY_IN_SECONDS );
	}
	return (int) $found;
}

/**
 * Create the booking page, or reuse a published one that already has the
 * form.
 *
 * @since 3.5
 * @return int Page ID or 0 on failure.
 */
function rpress_reservation_create_booking_page() {
	$existing = rpress_reservation_find_booking_page();
	if ( $existing ) {
		return $existing;
	}
	$page_id = wp_insert_post(
		array(
			'post_title'     => __( 'Reserve a Table', 'restropress' ),
			'post_name'      => 'reserve-a-table',
			'post_content'   => '[rpress_reservation]',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'comment_status' => 'closed',
		),
		true
	);
	if ( is_wp_error( $page_id ) ) {
		return 0;
	}
	set_transient( 'rpress_reservation_page_created', (int) $page_id, HOUR_IN_SECONDS );
	return (int) $page_id;
}

/**
 * When reservations are turned on, make sure the Reservation Page setting
 * points at a published page, creating "Reserve a Table" if needed.
 *
 * Runs on every save of the settings (settings screen, rpress_update_option(),
 * onboarding), before the value is stored.
 *
 * @since 3.5
 * @param mixed $new New settings.
 * @param mixed $old Old settings.
 * @return mixed
 */
function rpress_reservation_settings_before_save( $new, $old ) {
	if ( ! is_array( $new ) || empty( $new['enable_reservations'] ) ) {
		return $new;
	}
	$turned_on = ! is_array( $old ) || empty( $old['enable_reservations'] );
	$page_id   = isset( $new['reservation_page'] ) ? absint( $new['reservation_page'] ) : 0;
	if ( ! $turned_on || rpress_reservation_is_live_page( $page_id ) ) {
		return $new;
	}
	$page_id = rpress_reservation_create_booking_page();
	if ( $page_id ) {
		$new['reservation_page'] = $page_id;
		// rpress_update_option() only refreshes the key it was given.
		global $rpress_options;
		if ( is_array( $rpress_options ) ) {
			$rpress_options['reservation_page'] = $page_id;
		}
	}
	return $new;
}
add_filter( 'pre_update_option_rpress_settings', 'rpress_reservation_settings_before_save', 10, 2 );

/**
 * Forget the found booking page when a page is saved, trashed or deleted.
 *
 * @since 3.5
 * @param int $post_id Post ID.
 * @return void
 */
function rpress_reservation_flush_booking_page( $post_id ) {
	if ( 'page' === get_post_type( $post_id ) ) {
		delete_transient( 'rpress_customer_dashboard_page' );
		delete_transient( 'rpress_reservation_booking_page' );
	}
}
add_action( 'save_post', 'rpress_reservation_flush_booking_page' );
add_action( 'trashed_post', 'rpress_reservation_flush_booking_page' );
add_action( 'before_delete_post', 'rpress_reservation_flush_booking_page' );

/**
 * "Reservation Page" with the other page settings, after Order History Page.
 *
 * @since 3.5
 * @param array $settings Registered settings.
 * @return array
 */
function rpress_reservation_page_setting( $settings ) {
	if ( empty( $settings['general'] ) || ! is_array( $settings['general'] ) ) {
		return $settings;
	}
	// The section that holds the other page settings (General > Store Setup
	// once the 3.5 settings layout has moved them).
	$section = '';
	foreach ( $settings['general'] as $key => $fields ) {
		if ( is_array( $fields ) && isset( $fields['purchase_page'] ) ) {
			$section = $key;
			break;
		}
	}
	if ( '' === $section ) {
		return $settings;
	}
	$field = array(
		'reservation_page' => array(
			'id'          => 'reservation_page',
			'name'        => esc_html__( 'Reservation Page', 'restropress' ),
			'desc'        => esc_html__( 'The page where guests book a table. The [rpress_reservation] shortcode or the Table Reservation block must be on this page. Created for you when you turn on reservations.', 'restropress' ),
			'type'        => 'select',
			'options'     => rpress_get_pages(),
			'chosen'      => true,
			'placeholder' => esc_html__( 'Select a page', 'restropress' ),
		),
	);
	$main = $settings['general'][ $section ];
	$keys = array_keys( $main );
	$pos  = array_search( 'order_history_page', $keys, true );
	$pos  = false === $pos ? array_search( 'purchase_page', $keys, true ) : $pos;
	$pos  = false === $pos ? count( $main ) : $pos + 1;
	$settings['general'][ $section ] = array_slice( $main, 0, $pos, true ) + $field + array_slice( $main, $pos, null, true );
	return $settings;
}
add_filter( 'rpress_registered_settings', 'rpress_reservation_page_setting', 30 );

/**
 * Notice after turning reservations on created the booking page.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_page_created_notice() {
	$page_id = (int) get_transient( 'rpress_reservation_page_created' );
	if ( ! $page_id || ! current_user_can( 'manage_shop_settings' ) ) {
		return;
	}
	delete_transient( 'rpress_reservation_page_created' );
	if ( ! rpress_reservation_is_live_page( $page_id ) ) {
		return;
	}
	printf(
		'<div class="notice notice-success is-dismissible"><p>%1$s <a href="%2$s">%3$s</a> | <a href="%4$s">%5$s</a></p></div>',
		esc_html__( 'Reservations are on. We created a "Reserve a Table" page with the booking form.', 'restropress' ),
		esc_url( get_permalink( $page_id ) ),
		esc_html__( 'View page', 'restropress' ),
		esc_url( get_edit_post_link( $page_id ) ),
		esc_html__( 'Edit page', 'restropress' )
	);
}
add_action( 'admin_notices', 'rpress_reservation_page_created_notice' );
