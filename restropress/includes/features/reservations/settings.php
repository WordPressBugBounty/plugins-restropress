<?php
/**
 * Reservations: settings (General > Reservations).
 *
 * @package     RPRESS
 * @subpackage  Features/Reservations
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add the Reservations section to the General tab.
 *
 * @since 3.5
 * @param array $sections General sections.
 * @return array
 */
function rpress_reservation_settings_section( $sections ) {
	$sections['reservations'] = esc_html__( 'Reservations', 'restropress' );
	return $sections;
}
add_filter( 'rpress_settings_sections_general', 'rpress_reservation_settings_section' );

/**
 * Emails > Reservation Notifications, next to Order Notifications.
 *
 * @since 3.5
 * @param array $sections Email sections.
 * @return array
 */
function rpress_reservation_email_settings_section( $sections ) {
	if ( ! rpress_reservations_enabled() ) {
		return $sections;
	}
	$sections['reservation_notifications'] = esc_html__( 'Reservation Notifications', 'restropress' );
	return $sections;
}
add_filter( 'rpress_settings_sections_emails', 'rpress_reservation_email_settings_section' );

/**
 * Register the reservation settings.
 *
 * @since 3.5
 * @param array $settings Registered settings.
 * @return array
 */
function rpress_reservation_register_settings( $settings ) {
	$fields = array(
		'reservation_settings'       => array(
			'id'   => 'reservation_settings',
			'name' => '<h3>' . esc_html__( 'Table Reservations', 'restropress' ) . '</h3>',
			'desc' => '',
			'type' => 'header',
		),
		'enable_reservations'        => array(
			'id'   => 'enable_reservations',
			'name' => esc_html__( 'Enable reservations', 'restropress' ),
			'desc' => esc_html__( 'Let guests request a table from your website. Turning this on creates a "Reserve a Table" page (General > Store Setup > Reservation Page) and adds Reservations to the RestroPress menu and the customer dashboard.', 'restropress' ),
			'type' => 'checkbox',
		),
		'reservation_intro'          => array(
			'id'   => 'reservation_intro',
			'name' => '',
			'desc' => sprintf(
				/* translators: 1: shortcode, 2: link to the reservations list */
				__( 'Guests book on the Reservation Page (General > Store Setup). You can also add the form to any page with the %1$s shortcode or the "Table Reservation" block. Time slots follow your store hours and holidays from Service &amp; Hours. Manage requests under %2$s.', 'restropress' ),
				'<code>[rpress_reservation]</code>',
				'<a href="' . esc_url( admin_url( 'edit.php?post_type=rpress_reservation' ) ) . '">' . esc_html__( 'RestroPress > Reservations', 'restropress' ) . '</a>'
			),
			'type' => 'descriptive_text',
		),
		'reservation_table_booking'  => array(
			'id'   => 'reservation_table_booking',
			'name' => '',
			'desc' => function_exists( 'rpress_settings_upsell_nudge' ) ? rpress_settings_upsell_nudge( __( 'Need deposits to cut no-shows, special occasions, or SMS phone verification? The Table Booking extension adds them to your reservations.', 'restropress' ), rpress_reservation_table_booking_url() ) : '',
			'type' => 'descriptive_text',
		),
		'reservation_paused'         => array(
			'id'   => 'reservation_paused',
			'name' => esc_html__( 'Pause reservations', 'restropress' ),
			'desc' => esc_html__( 'Hide the form and show a "not taking reservations" message instead.', 'restropress' ),
			'type' => 'checkbox',
		),
		'reservation_auto_confirm'   => array(
			'id'   => 'reservation_auto_confirm',
			'name' => esc_html__( 'Confirm automatically', 'restropress' ),
			'desc' => esc_html__( 'Confirm new reservations right away while seats are available. When off, they wait as Pending until you confirm or decline them.', 'restropress' ),
			'type' => 'checkbox',
		),
		'reservation_slot_interval'  => array(
			'id'   => 'reservation_slot_interval',
			'name' => esc_html__( 'Time slot interval (minutes)', 'restropress' ),
			'desc' => esc_html__( 'Guests pick a time from slots this many minutes apart, starting at opening time.', 'restropress' ),
			'type' => 'number',
			'size' => 'small',
			'min'  => 5,
			'step' => 5,
			'std'  => '30',
		),
		'reservation_slot_capacity'  => array(
			'id'   => 'reservation_slot_capacity',
			'name' => esc_html__( 'Seats per time slot', 'restropress' ),
			'desc' => esc_html__( 'Total guests (covers) you can seat in one time slot. Pending and confirmed reservations count.', 'restropress' ),
			'type' => 'number',
			'size' => 'small',
			'min'  => 1,
			'std'  => '20',
		),
		'reservation_max_party'      => array(
			'id'   => 'reservation_max_party',
			'name' => esc_html__( 'Largest party online', 'restropress' ),
			'desc' => esc_html__( 'Bigger groups are asked to call.', 'restropress' ),
			'type' => 'number',
			'size' => 'small',
			'min'  => 1,
			'std'  => '10',
		),
		'reservation_lead_time'      => array(
			'id'   => 'reservation_lead_time',
			'name' => esc_html__( 'Minimum notice (minutes)', 'restropress' ),
			'desc' => esc_html__( 'How far ahead of the slot a reservation must be made.', 'restropress' ),
			'type' => 'number',
			'size' => 'small',
			'min'  => 5,
			'step' => 5,
			'std'  => '60',
		),
		'reservation_max_days'       => array(
			'id'   => 'reservation_max_days',
			'name' => esc_html__( 'Book up to (days ahead)', 'restropress' ),
			'type' => 'number',
			'desc' => esc_html__( 'How many days ahead guests can book.', 'restropress' ),
			'size' => 'small',
			'min'  => 1,
			'std'  => '30',
		),
		'reservation_emails_link'    => array(
			'id'   => 'reservation_emails_link',
			'name' => esc_html__( 'Emails', 'restropress' ),
			'desc' => sprintf(
				/* translators: %s: link to the reservation email settings */
				__( 'Edit the reservation emails, reminders and admin notification under %s.', 'restropress' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=rpress-settings&tab=emails&section=reservation_notifications' ) ) . '">' . esc_html__( 'Emails > Reservation Notifications', 'restropress' ) . '</a>'
			),
			'type' => 'descriptive_text',
		),
	);

	if ( ! rpress_reservations_enabled() ) {
		// Only the switch while off; the rest appears once it is saved on.
		if ( ! isset( $settings['general'] ) || ! is_array( $settings['general'] ) ) {
			$settings['general'] = array();
		}
		$settings['general']['reservations'] = array(
			'reservation_settings' => $fields['reservation_settings'],
			'enable_reservations'  => $fields['enable_reservations'],
		);
		return $settings;
	}

	$email_fields = array(
		'reservation_emails_header'  => array(
			'id'   => 'reservation_emails_header',
			'name' => '<h3>' . esc_html__( 'Reservation Emails', 'restropress' ) . '</h3>',
			'desc' => '',
			'type' => 'header',
		),
		'reservation_email_tags'     => array(
			'id'   => 'reservation_email_tags',
			'name' => '',
			'desc' => esc_html__( 'Available tags: {name} {phone} {email} {date} {time} {party_size} {note} {status} {reservation_id} {sitename} {admin_link}. Leave a field empty to use the default text.', 'restropress' ),
			'type' => 'descriptive_text',
		),
		'reservation_disable_admin_email' => array(
			'id'   => 'reservation_disable_admin_email',
			'name' => esc_html__( 'Turn off admin notification', 'restropress' ),
			'desc' => esc_html__( 'Do not email the store admins (Emails > Order Notifications recipients) about new reservations.', 'restropress' ),
			'type' => 'checkbox',
		),
		'reservation_disable_reminder' => array(
			'id'   => 'reservation_disable_reminder',
			'name' => esc_html__( 'Turn off reminders', 'restropress' ),
			'desc' => esc_html__( 'Do not send the reminder email before confirmed reservations.', 'restropress' ),
			'type' => 'checkbox',
		),
		'reservation_reminder_hours' => array(
			'id'   => 'reservation_reminder_hours',
			'name' => esc_html__( 'Send reminder (hours before)', 'restropress' ),
			'desc' => esc_html__( 'Uses WP-Cron, so it goes out on the first site visit after this time.', 'restropress' ),
			'type' => 'number',
			'size' => 'small',
			'min'  => 1,
			'std'  => '3',
		),
	);

	foreach ( rpress_reservation_email_defaults() as $type => $default ) {
		$email_fields[ 'reservation_email_' . $type . '_subject' ] = array(
			'id'          => 'reservation_email_' . $type . '_subject',
			/* translators: %s: email name */
			'name'        => sprintf( esc_html__( '%s subject', 'restropress' ), esc_html( $default['label'] ) ),
			'desc'        => '',
			'type'        => 'text',
			'std'         => $default['subject'],
		);
		$email_fields[ 'reservation_email_' . $type . '_body' ] = array(
			'id'          => 'reservation_email_' . $type . '_body',
			/* translators: %s: email name */
			'name'        => sprintf( esc_html__( '%s message', 'restropress' ), esc_html( $default['label'] ) ),
			'desc'        => '',
			'type'        => 'textarea',
			'std'         => $default['body'],
		);
	}

	if ( ! isset( $settings['general'] ) || ! is_array( $settings['general'] ) ) {
		$settings['general'] = array();
	}
	if ( rpress_reservation_has_table_booking() || '' === $fields['reservation_table_booking']['desc'] ) {
		unset( $fields['reservation_table_booking'] );
	}
	if ( ! isset( $settings['emails'] ) || ! is_array( $settings['emails'] ) ) {
		$settings['emails'] = array();
	}
	/*
	 * The filter still receives every field, as before the emails moved to
	 * their own tab; fields are then placed by key.
	 */
	$all = apply_filters( 'rpress_reservation_registered_settings', array_merge( $fields, $email_fields ) );
	$settings['general']['reservations']            = array_diff_key( $all, $email_fields );
	$settings['emails']['reservation_notifications'] = array_intersect_key( $all, $email_fields );
	return $settings;
}
add_filter( 'rpress_registered_settings', 'rpress_reservation_register_settings' );
