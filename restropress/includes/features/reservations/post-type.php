<?php
/**
 * Reservations: post type registration.
 *
 * @package     RPRESS
 * @subpackage  Features/Reservations
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the `rpress_reservation` post type.
 *
 * Non-public, listed under the RestroPress menu. Uses the order
 * (shop_payment) capabilities so anyone who manages orders manages
 * reservations too.
 *
 * @since 3.5
 * @return void
 */
function rpress_register_reservation_post_type() {
	$labels = array(
		'name'               => _x( 'Reservations', 'post type general name', 'restropress' ),
		'singular_name'      => _x( 'Reservation', 'post type singular name', 'restropress' ),
		'add_new'            => __( 'Add Reservation', 'restropress' ),
		'add_new_item'       => __( 'Add Reservation', 'restropress' ),
		'edit_item'          => __( 'Edit Reservation', 'restropress' ),
		'new_item'           => __( 'New Reservation', 'restropress' ),
		'all_items'          => __( 'Reservations', 'restropress' ),
		'view_item'          => __( 'View Reservation', 'restropress' ),
		'search_items'       => __( 'Search Reservations', 'restropress' ),
		'not_found'          => __( 'No reservations found', 'restropress' ),
		'not_found_in_trash' => __( 'No reservations found in Trash', 'restropress' ),
		'menu_name'          => __( 'Reservations', 'restropress' ),
	);

	register_post_type(
		'rpress_reservation',
		apply_filters(
			'rpress_reservation_post_type_args',
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				// Added under RestroPress by rpress_reservation_admin_menu(), after Orders.
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'capability_type'     => 'shop_payment',
				'map_meta_cap'        => true,
				'supports'            => false,
			)
		)
	);
}
add_action( 'init', 'rpress_register_reservation_post_type', 5 );

/**
 * Lists Reservations under the RestroPress menu, right after Orders.
 *
 * Registering it as a plain submenu (instead of show_in_menu) keeps it out of
 * the first slot, which would make it the target of the RestroPress menu link.
 */
function rpress_reservation_admin_menu() {
	$type = get_post_type_object( 'rpress_reservation' );
	if ( ! $type ) {
		return;
	}
	$title   = $type->labels->menu_name;
	$pending = current_user_can( $type->cap->edit_posts ) ? rpress_reservation_count_pending() : 0;
	if ( $pending ) {
		$title .= ' <span class="awaiting-mod count-' . absint( $pending ) . '"><span class="pending-count">' . number_format_i18n( $pending ) . '</span></span>';
	}
	add_submenu_page(
		'restropress',
		$type->labels->name,
		$title,
		$type->cap->edit_posts,
		'edit.php?post_type=rpress_reservation',
		'',
		2
	);
}
add_action( 'admin_menu', 'rpress_reservation_admin_menu', 11 );
