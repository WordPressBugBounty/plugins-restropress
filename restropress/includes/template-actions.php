<?php
/**
 * Manage actions and callbacks related to templates.
 *
 * @package     RPRESS
 * @subpackage  Templates
 * @copyright   Copyright (c) 2017, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 1.0
 */
/**
 * Output a message and login form on the profile editor when the
 * current visitor is not logged in.
 *
 * @since 1.0.0
 */
function rpress_profile_editor_logged_out() {
	echo '<p class="rpress-logged-out">' . esc_html__( 'You need to log in to edit your profile.', 'restropress' ) . '</p>';
	// Define allowed HTML tags and attributes
	$allowed_html = array(
		'form' => array(
			'action' => true,
			'method' => true,
			'id'     => true,
			'class'  => true,
		),
		'input' => array(
			'type'        => true,
			'name'        => true,
			'value'       => true,
			'id'          => true,
			'class'       => true,
			'placeholder' => true,
		),
		'label' => array(
			'for'   => true,
			'class' => true,
		),
		'button' => array(
			'type'  => true,
			'class' => true,
		),
		'div' => array(
			'class' => true,
			'id'    => true,
		),
		'span' => array(
			'class' => true,
			'id'    => true,
		),
		'a' => array(
			'href'  => true,
			'class' => true,
			'title' => true,
		),
	);
	
	// Output the login form safely
	echo wp_kses( rpress_login_form(), $allowed_html );
}
add_action( 'rpress_profile_editor_logged_out', 'rpress_profile_editor_logged_out' );
/**
 * Output a message on the login form when a user is already logged in.
 *
 * This remains mainly for backwards compatibility.
 *
 * @since 1.0.0
 */
function rpress_login_form_logged_in() {
	echo '<p class="rpress-logged-in">' . esc_html__( 'You are already logged in', 'restropress' ) . '</p>';
}
add_action( 'rpress_login_form_logged_in', 'rpress_login_form_logged_in' );