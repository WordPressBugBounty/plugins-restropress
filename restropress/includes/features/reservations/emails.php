<?php
/**
 * Reservations: customer and admin emails.
 *
 * Sent through the shared RPRESS_Emails instance so they use the store's
 * email template, sender name and address. Subjects and bodies are
 * editable under Settings > General > Reservations.
 *
 * @package     RPRESS
 * @subpackage  Features/Reservations
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Default subject and body per email type.
 *
 * @since 3.5
 * @return array type => { subject, body, label }
 */
function rpress_reservation_email_defaults() {
	return apply_filters(
		'rpress_reservation_email_defaults',
		array(
			'received'  => array(
				'label'   => __( 'Customer: request received', 'restropress' ),
				'subject' => __( 'We received your reservation request', 'restropress' ),
				'body'    => __( "Hi {name},\n\nThanks for your reservation request at {sitename}.\n\nDate: {date}\nTime: {time}\nGuests: {party_size}\n\nWe will email you as soon as it is confirmed.", 'restropress' ),
			),
			'confirmed' => array(
				'label'   => __( 'Customer: reservation confirmed', 'restropress' ),
				'subject' => __( 'Your reservation is confirmed', 'restropress' ),
				'body'    => __( "Hi {name},\n\nYour table at {sitename} is confirmed.\n\nDate: {date}\nTime: {time}\nGuests: {party_size}\n\nSee you soon!", 'restropress' ),
			),
			'declined'  => array(
				'label'   => __( 'Customer: reservation declined', 'restropress' ),
				'subject' => __( 'About your reservation request', 'restropress' ),
				'body'    => __( "Hi {name},\n\nSorry, we cannot accommodate your reservation for {party_size} on {date} at {time}.\n\nPlease try another time or contact us directly.", 'restropress' ),
			),
			'reminder'  => array(
				'label'   => __( 'Customer: reminder', 'restropress' ),
				'subject' => __( 'Reminder: your table today at {time}', 'restropress' ),
				'body'    => __( "Hi {name},\n\nA quick reminder of your reservation at {sitename}.\n\nDate: {date}\nTime: {time}\nGuests: {party_size}\n\nIf your plans change, please let us know.", 'restropress' ),
			),
			'admin'     => array(
				'label'   => __( 'Admin: new reservation', 'restropress' ),
				'subject' => __( 'New reservation: {party_size} guests on {date} at {time}', 'restropress' ),
				'body'    => __( "A new reservation was made.\n\nName: {name}\nPhone: {phone}\nEmail: {email}\nDate: {date}\nTime: {time}\nGuests: {party_size}\nStatus: {status}\nNote: {note}\n\nManage it here: {admin_link}", 'restropress' ),
			),
		)
	);
}

/**
 * Replace reservation tags in a string.
 *
 * @since 3.5
 * @param string $text  Text with {tags}.
 * @param array  $res   Reservation from rpress_get_reservation().
 * @param bool   $html  Escape values for HTML.
 * @return string
 */
function rpress_reservation_email_tags( $text, $res, $html = true ) {
	$statuses = rpress_reservation_statuses();
	$values   = array(
		'{name}'           => $res['name'],
		'{phone}'          => $res['phone'],
		'{email}'          => $res['email'],
		'{note}'           => '' !== $res['note'] ? $res['note'] : '-',
		'{party_size}'     => (string) $res['party'],
		'{date}'           => rpress_reservation_format_date( $res['ts'] ),
		'{time}'           => rpress_reservation_format_time( $res['ts'] ),
		'{status}'         => isset( $statuses[ $res['status'] ] ) ? $statuses[ $res['status'] ] : $res['status'],
		'{reservation_id}' => (string) $res['id'],
		'{sitename}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'{admin_link}'     => admin_url( 'edit.php?post_type=rpress_reservation&rpress_res_view=upcoming' ),
	);
	$values = apply_filters( 'rpress_reservation_email_tag_values', $values, $res );
	if ( $html ) {
		$values = array_map( 'esc_html', $values );
	}
	return strtr( $text, $values );
}

/**
 * Send one reservation email.
 *
 * @since 3.5
 * @param int    $id   Reservation ID.
 * @param string $type received|confirmed|declined|reminder|admin.
 * @return bool
 */
function rpress_reservation_send_email( $id, $type ) {
	$defaults = rpress_reservation_email_defaults();
	$res      = rpress_get_reservation( $id );
	if ( ! $res || ! isset( $defaults[ $type ] ) ) {
		return false;
	}

	$to = 'admin' === $type ? rpress_get_admin_notice_emails() : $res['email'];
	if ( empty( $to ) ) {
		return false;
	}
	if ( ! apply_filters( 'rpress_reservation_send_email', true, $type, $res ) ) {
		return false;
	}

	$subject = rpress_get_option( 'reservation_email_' . $type . '_subject', '' );
	$body    = rpress_get_option( 'reservation_email_' . $type . '_body', '' );
	$subject = '' !== trim( (string) $subject ) ? $subject : $defaults[ $type ]['subject'];
	$body    = '' !== trim( (string) $body ) ? $body : $defaults[ $type ]['body'];

	$subject = wp_strip_all_tags( rpress_reservation_email_tags( $subject, $res, false ) );
	$body    = rpress_reservation_email_tags( $body, $res, true );

	$emails       = RPRESS()->emails;
	$prev_heading = $emails->heading;
	$prev_headers = $emails->headers;

	$emails->__set( 'from_name', rpress_get_option( 'from_name', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) );
	$emails->__set( 'from_email', rpress_get_option( 'from_email', get_bloginfo( 'admin_email' ) ) );
	$emails->__set( 'heading', $subject );
	$emails->__set( 'headers', '' );
	$headers = $emails->get_headers();
	if ( 'admin' === $type && is_email( $res['email'] ) ) {
		$headers = preg_replace( '/^Reply-To:.*$/mi', 'Reply-To: ' . $res['email'], $headers );
	}
	$emails->__set( 'headers', apply_filters( 'rpress_reservation_email_headers', $headers, $type, $res ) );

	$footer = rpress_reservation_email_footer_filter( $id, $type );
	$sent   = $emails->send( $to, $subject, $body );
	remove_filter( 'rpress_email_footer_text', $footer, 99 );
	remove_filter( 'rpress_email_show_footer_link', '__return_true', 99 );

	$emails->__set( 'heading', $prev_heading );
	$emails->__set( 'headers', $prev_headers );

	do_action( 'rpress_reservation_email_sent', $id, $type, $sent );
	return (bool) $sent;
}

/**
 * New reservation: acknowledge the customer, notify the admin.
 *
 * @since 3.5
 * @param int    $id     Reservation ID.
 * @param string $status Initial status.
 * @param array  $args   Insert args.
 * @return void
 */
function rpress_reservation_created_emails( $id, $status, $args = array() ) {
	if ( isset( $args['notify'] ) && ! $args['notify'] ) {
		return;
	}
	rpress_reservation_send_email( $id, 'confirmed' === $status ? 'confirmed' : 'received' );
	if ( ! rpress_get_option( 'reservation_disable_admin_email', false ) && ( empty( $args['source'] ) || 'admin' !== $args['source'] ) ) {
		rpress_reservation_send_email( $id, 'admin' );
	}
}
add_action( 'rpress_reservation_created', 'rpress_reservation_created_emails', 10, 3 );

/**
 * Status change: email the customer on confirm or decline.
 *
 * @since 3.5
 * @param int    $id     Reservation ID.
 * @param string $status New status.
 * @param string $old    Old status.
 * @param bool   $notify Whether to send.
 * @return void
 */
function rpress_reservation_status_emails( $id, $status, $old, $notify ) {
	if ( $notify && in_array( $status, array( 'confirmed', 'declined' ), true ) ) {
		rpress_reservation_send_email( $id, $status );
	}
}
add_action( 'rpress_reservation_status_changed', 'rpress_reservation_status_emails', 10, 4 );

/**
 * Cron: send the reminder once, only for confirmed future reservations.
 *
 * @since 3.5
 * @param int $id Reservation ID.
 * @return void
 */
function rpress_reservation_send_reminder( $id ) {
	$res = rpress_get_reservation( $id );
	if ( ! $res || 'confirmed' !== $res['status'] || $res['ts'] < time() || get_post_meta( $id, '_rpress_res_reminded', true ) ) {
		return;
	}
	if ( 'publish' !== get_post_status( $id ) ) {
		return;
	}
	if ( rpress_reservation_send_email( $id, 'reminder' ) ) {
		update_post_meta( $id, '_rpress_res_reminded', time() );
	}
}
add_action( 'rpress_reservation_reminder', 'rpress_reservation_send_reminder' );
