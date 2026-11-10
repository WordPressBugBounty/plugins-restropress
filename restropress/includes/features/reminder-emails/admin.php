<?php
/**
 * Reminder emails: Settings > Emails > Order Notifications entry.
 *
 * @package RestroPress
 * @since   3.5
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List "Order Reminder" with the other order emails.
 *
 * @param array $emails Extra emails.
 * @return array
 */
function rpress_reminder_register_email( $emails ) {
	$settings = rpress_reminder_settings();
	$defaults = rpress_reminder_default_copy();

	$emails[ RPRESS_REMINDER_OPTION ] = array(
		'label'     => __( 'Order Reminder (scheduled orders)', 'restropress' ),
		'recipient' => __( 'Customer', 'restropress' ),
		'enabled'   => $settings['enabled'],
		'subject'   => $defaults['subject'],
		'heading'   => $defaults['heading'],
		'content'   => $defaults['content'],
	);
	return $emails;
}
add_filter( 'rpress_order_notification_extra_emails', 'rpress_reminder_register_email' );

/**
 * Lead time field and the feature's tags on the reminder's Manage screen.
 *
 * @param string $email_key Email being managed.
 * @return void
 */
function rpress_reminder_manage_fields( $email_key ) {
	if ( RPRESS_REMINDER_OPTION !== $email_key ) {
		return;
	}
	$settings = rpress_reminder_settings();
	?>
	<tr>
		<td>
			<label for="rpress_reminder_minutes"><?php esc_html_e( 'Send before', 'restropress' ); ?></label>
		</td>
		<td>
			<input id="rpress_reminder_minutes" class="small-text" type="number" min="5" max="1440" step="1"
				name="<?php echo esc_attr( 'rpress_settings[' . RPRESS_REMINDER_OPTION . '][minutes_before]' ); ?>"
				value="<?php echo esc_attr( $settings['minutes'] ); ?>">
			<?php esc_html_e( 'minutes before the scheduled pickup or delivery time', 'restropress' ); ?>
			<p class="help-text">
				<?php esc_html_e( 'Sent once to the customer for orders placed for a later time. ASAP orders, cancelled or completed orders, and orders placed less than this many minutes ahead are skipped.', 'restropress' ); ?>
			</p>
			<p class="help-text">
				<?php esc_html_e( 'Extra tags for this email: {reminder_message} (for example "Your order #123 will be ready at 7:30 pm"), {service_time} and {service_date} (formatted in the site timezone).', 'restropress' ); ?>
			</p>
		</td>
	</tr>
	<?php
}
add_action( 'rpress_order_notification_manage_fields', 'rpress_reminder_manage_fields' );

/**
 * Sanitize the reminder settings when the Emails tab is saved.
 *
 * @param array $input Posted settings.
 * @return array
 */
function rpress_reminder_sanitize_settings( $input ) {
	if ( ! isset( $input[ RPRESS_REMINDER_OPTION ] ) || ! is_array( $input[ RPRESS_REMINDER_OPTION ] ) ) {
		return $input;
	}
	$raw   = $input[ RPRESS_REMINDER_OPTION ];
	$clean = array(
		'minutes_before' => isset( $raw['minutes_before'] ) ? rpress_reminder_clamp_minutes( $raw['minutes_before'] ) : 30,
		'subject'        => isset( $raw['subject'] ) ? sanitize_text_field( $raw['subject'] ) : '',
		'heading'        => isset( $raw['heading'] ) ? sanitize_text_field( $raw['heading'] ) : '',
		'content'        => isset( $raw['content'] ) ? wp_kses_post( $raw['content'] ) : '',
	);
	if ( ! empty( $raw['enable_notification'] ) ) {
		$clean['enable_notification'] = 'yes';
	}
	$input[ RPRESS_REMINDER_OPTION ] = $clean;
	return $input;
}
add_filter( 'rpress_settings_emails_sanitize', 'rpress_reminder_sanitize_settings' );
