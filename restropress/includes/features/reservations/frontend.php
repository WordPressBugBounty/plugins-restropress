<?php
/**
 * Reservations: storefront form, submission handling, slot AJAX and block.
 *
 * The form posts normally (works without JavaScript). The script only
 * refreshes the time list when the date or party size changes.
 *
 * @package     RPRESS
 * @subpackage  Features/Reservations
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register front assets and the block.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_register_assets() {
	$base = RP_PLUGIN_DIR . 'assets/features/reservations/';
	$ver  = function ( $file ) use ( $base ) {
		return file_exists( $base . $file ) ? RP_VERSION . '.' . filemtime( $base . $file ) : RP_VERSION;
	};

	wp_register_style( 'rpress-reservation', RPRESS_RESERVATIONS_ASSETS_URL . 'reservation.css', array(), $ver( 'reservation.css' ) );
	wp_register_script( 'rpress-reservation', RPRESS_RESERVATIONS_ASSETS_URL . 'reservation.js', array(), $ver( 'reservation.js' ), true );
	wp_localize_script(
		'rpress-reservation',
		'rpressReservation',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'loading'  => __( 'Loading times...', 'restropress' ),
			'none'     => __( 'No times available on this day', 'restropress' ),
			'choose'   => __( 'Choose a time', 'restropress' ),
			'full'     => __( 'full', 'restropress' ),
		)
	);
	wp_register_script(
		'rpress-reservation-block',
		RPRESS_RESERVATIONS_ASSETS_URL . 'block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
		$ver( 'block.js' ),
		true
	);
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'rpress-reservation-block', 'restropress', RP_PLUGIN_DIR . 'languages' );
	}

	if ( function_exists( 'register_block_type' ) ) {
		register_block_type(
			RPRESS_RESERVATIONS_DIR . 'block',
			array( 'render_callback' => 'rpress_reservation_render_block' )
		);
	}
}
add_action( 'init', 'rpress_reservation_register_assets', 20 );

/**
 * Block render: the shortcode inside the block wrapper.
 *
 * @since 3.5
 * @param array $attributes Block attributes.
 * @return string
 */
function rpress_reservation_render_block( $attributes = array() ) {
	$title   = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
	$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';
	return '<div ' . $wrapper . '>' . rpress_reservation_shortcode( array( 'title' => $title ) ) . '</div>';
}

/**
 * Result of a POSTed submission in this request (errors and sticky values).
 *
 * @since 3.5
 * @param array|null $set Set the result.
 * @return array
 */
function rpress_reservation_request_result( $set = null ) {
	static $result = array();
	if ( null !== $set ) {
		$result = $set;
	}
	return $result;
}

/**
 * Per-IP submission limit.
 *
 * @since 3.5
 * @return array { key: string, count: int, limit: int }
 */
function rpress_reservation_rate_limit_state() {
	$key = 'rpress_res_rl_' . md5( rpress_reservation_client_ip() );
	return array(
		'key'   => $key,
		'count' => (int) get_transient( $key ),
		'limit' => (int) apply_filters( 'rpress_reservation_rate_limit', 5 ),
	);
}

/**
 * Handle a reservation form POST, then redirect on success (PRG).
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_handle_submit() {
	if ( empty( $_POST['rpress_reservation_action'] ) || 'submit' !== $_POST['rpress_reservation_action'] ) {
		return;
	}

	$input  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
	$errors = new WP_Error();
	// The form posts to its own page, and wp_get_referer() returns false when
	// the referer is the current URL, which sent customers to the home page.
	$back   = wp_validate_redirect( wp_get_raw_referer(), home_url( '/' ) );
	$back   = remove_query_arg( array( 'rpress_reservation', 'rid', 'rkey' ), $back );

	// Honeypot: bots fill every field. Pretend it worked, store nothing.
	// The field is named so browsers do not autofill it (an autofilled
	// "website" field silently dropped real requests); the old name is still
	// read for pages cached before the rename.
	// Only when the form was also sent faster than a person can fill it, so a
	// browser extension that fills hidden fields cannot drop a real request.
	$rendered  = isset( $input['rpress_res_t'] ) ? absint( $input['rpress_res_t'] ) : 0;
	$too_quick = ! $rendered || ( time() - $rendered ) < (int) apply_filters( 'rpress_reservation_min_fill_seconds', 4 );
	if ( ( ! empty( $input['rpress_res_hpv'] ) || ! empty( $input['rpress_res_website'] ) ) && $too_quick ) {
		wp_safe_redirect( add_query_arg( 'rpress_reservation', 'received', $back ) );
		exit;
	}

	if ( empty( $input['rpress_reservation_nonce'] ) || ! wp_verify_nonce( $input['rpress_reservation_nonce'], 'rpress_reservation_submit' ) ) {
		$errors->add( 'form', __( 'Your session expired. Please submit the form again.', 'restropress' ) );
	}

	$settings = rpress_reservation_settings();
	if ( $settings['paused'] ) {
		$errors->add( 'form', __( 'We are not taking reservations right now.', 'restropress' ) );
	}

	$limit  = rpress_reservation_rate_limit_state();
	$exempt = current_user_can( 'edit_shop_payments' );
	if ( ! $exempt && $limit['count'] >= $limit['limit'] ) {
		$errors->add( 'form', __( 'Too many reservation requests from your connection. Please try again later or call us.', 'restropress' ) );
	}

	$data = array(
		'date'  => isset( $input['rpress_res_date'] ) ? $input['rpress_res_date'] : '',
		'slot'  => isset( $input['rpress_res_slot'] ) ? $input['rpress_res_slot'] : '',
		'party' => isset( $input['rpress_res_party'] ) ? $input['rpress_res_party'] : '',
		'name'  => isset( $input['rpress_res_name'] ) ? $input['rpress_res_name'] : '',
		'phone' => isset( $input['rpress_res_phone'] ) ? $input['rpress_res_phone'] : '',
		'email' => isset( $input['rpress_res_email'] ) ? $input['rpress_res_email'] : '',
		'note'  => isset( $input['rpress_res_note'] ) ? $input['rpress_res_note'] : '',
	);
	$data = array_map(
		function ( $value ) {
			return is_scalar( $value ) ? (string) $value : '';
		},
		$data
	);
	/**
	 * Reservation data read from the form. Extensions add their own posted
	 * fields here (sanitized by them); they reach rpress_reservation_validation_errors
	 * and rpress_reservation_insert_meta as $input.
	 *
	 * @since 3.5
	 * @param array $data  Core fields (strings).
	 * @param array $input Raw unslashed $_POST.
	 */
	$data = apply_filters( 'rpress_reservation_submit_data', $data, $input );

	if ( ! $errors->has_errors() ) {
		$id = rpress_insert_reservation( $data );
		if ( ! is_wp_error( $id ) ) {
			if ( ! $exempt ) {
				set_transient( $limit['key'], $limit['count'] + 1, HOUR_IN_SECONDS );
			}
			$url = add_query_arg(
				array(
					'rpress_reservation' => 'received',
					'rid'                => $id,
					'rkey'               => rpress_reservation_view_key( $id ),
				),
				$back
			);
			/**
			 * Where to send the guest after a successful request, for example
			 * to a deposit payment. External hosts must be allowed through
			 * allowed_redirect_hosts.
			 *
			 * @since 3.5
			 * @param string $url  Success screen URL.
			 * @param int    $id   Reservation ID.
			 * @param string $back The form page.
			 */
			$url = apply_filters( 'rpress_reservation_submit_redirect', $url, $id, $back );
			wp_safe_redirect( $url );
			exit;
		}
		$errors = $id;
	}

	rpress_reservation_request_result(
		array(
			'errors' => $errors,
			'values' => array_map( 'sanitize_text_field', $data ),
		)
	);
}
add_action( 'template_redirect', 'rpress_reservation_handle_submit' );

/**
 * Key that lets the success screen show a reservation summary.
 *
 * @since 3.5
 * @param int $id Reservation ID.
 * @return string
 */
function rpress_reservation_view_key( $id ) {
	return substr( wp_hash( 'rpress_reservation|' . (int) $id . '|' . get_post_meta( $id, '_rpress_res_email', true ) ), 0, 20 );
}

/**
 * AJAX: slots for a date and party size.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_ajax_slots() {
	$date  = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only lookup.
	$party = isset( $_GET['party'] ) ? absint( $_GET['party'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$slots = rpress_reservation_get_slots( $date, max( 1, $party ) );
	wp_send_json_success(
		array_map(
			function ( $slot ) {
				return array(
					'ts'        => $slot['ts'],
					'label'     => $slot['label'],
					'available' => $slot['available'],
				);
			},
			$slots
		)
	);
}
add_action( 'wp_ajax_rpress_reservation_slots', 'rpress_reservation_ajax_slots' );
add_action( 'wp_ajax_nopriv_rpress_reservation_slots', 'rpress_reservation_ajax_slots' );

/**
 * Enqueue CSS early on pages that contain the shortcode.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_maybe_enqueue() {
	$post = get_post();
	if ( $post && ( has_shortcode( $post->post_content, 'rpress_reservation' ) || has_block( 'rpress/reservation-form', $post ) ) ) {
		wp_enqueue_style( 'rpress-reservation' );
		wp_enqueue_script( 'rpress-reservation' );
	}
}
add_action( 'wp_enqueue_scripts', 'rpress_reservation_maybe_enqueue' );

/**
 * Shortcode [rpress_reservation title=""].
 *
 * @since 3.5
 * @param array $atts Shortcode attributes.
 * @return string
 */
function rpress_reservation_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array( 'title' => __( 'Reserve a table', 'restropress' ) ),
		$atts,
		'rpress_reservation'
	);

	wp_enqueue_style( 'rpress-reservation' );
	wp_enqueue_script( 'rpress-reservation' );

	$settings = rpress_reservation_settings();
	$title    = '' !== trim( $atts['title'] ) ? '<h2 class="rpress-res-title">' . esc_html( $atts['title'] ) . '</h2>' : '';

	// Success screen after the redirect.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display keyed by a hash.
	if ( isset( $_GET['rpress_reservation'] ) && 'received' === $_GET['rpress_reservation'] ) {
		$summary = '';
		$id      = isset( $_GET['rid'] ) ? absint( $_GET['rid'] ) : 0;
		$key     = isset( $_GET['rkey'] ) ? sanitize_text_field( wp_unslash( $_GET['rkey'] ) ) : '';
		$res     = $id ? rpress_get_reservation( $id ) : false;
		$status  = 'pending';
		if ( $res && hash_equals( rpress_reservation_view_key( $id ), $key ) ) {
			$status  = $res['status'];
			$summary = '<dl class="rpress-res-summary">'
				. '<dt>' . esc_html__( 'Date', 'restropress' ) . '</dt><dd>' . esc_html( rpress_reservation_format_date( $res['ts'] ) ) . '</dd>'
				. '<dt>' . esc_html__( 'Time', 'restropress' ) . '</dt><dd>' . esc_html( rpress_reservation_format_time( $res['ts'] ) ) . '</dd>'
				. '<dt>' . esc_html__( 'Guests', 'restropress' ) . '</dt><dd>' . esc_html( $res['party'] ) . '</dd>'
				. '</dl>';
		}
		$message = 'confirmed' === $status
			? __( 'Your table is confirmed. We have emailed you the details.', 'restropress' )
			: __( 'Thanks! We received your request and will email you once it is confirmed.', 'restropress' );
		$verified = $res && hash_equals( rpress_reservation_view_key( $id ), $key );
		/**
		 * Success screen message and summary (summary is HTML, escape your values).
		 *
		 * @since 3.5
		 * @param string      $message Plain text message.
		 * @param string      $status  Reservation status.
		 * @param array|false $res     Reservation, or false when the link does not verify.
		 */
		$message = apply_filters( 'rpress_reservation_success_message', $message, $status, $verified ? $res : false );
		$summary = apply_filters( 'rpress_reservation_success_summary', $summary, $verified ? $res : false );
		return '<div class="rpress-reservation rpress-res-done" role="status">' . $title
			. '<p class="rpress-res-success">' . esc_html( $message ) . '</p>' . $summary
			. '<p><a class="rpress-res-again" href="' . esc_url( remove_query_arg( array( 'rpress_reservation', 'rid', 'rkey' ) ) ) . '">' . esc_html__( 'Make another reservation', 'restropress' ) . '</a></p>'
			. '</div>';
	}
	// phpcs:enable

	if ( $settings['paused'] ) {
		return '<div class="rpress-reservation rpress-res-paused">' . $title . '<p>' . esc_html__( 'We are not taking online reservations right now. Please call us.', 'restropress' ) . '</p></div>';
	}

	$result = rpress_reservation_request_result();
	$errors = isset( $result['errors'] ) && is_wp_error( $result['errors'] ) ? $result['errors'] : new WP_Error();
	$values = isset( $result['values'] ) ? $result['values'] : array();

	$party  = ! empty( $values['party'] ) ? min( $settings['max_party'], max( 1, absint( $values['party'] ) ) ) : min( 2, $settings['max_party'] );
	$window = rpress_reservation_date_window();
	$date   = ! empty( $values['date'] ) && rpress_reservation_is_valid_date( $values['date'] ) ? $values['date'] : rpress_reservation_first_open_date( $party );
	$slots  = rpress_reservation_get_slots( $date, $party );
	$chosen = ! empty( $values['slot'] ) ? absint( $values['slot'] ) : 0;

	$field_error = function ( $field ) use ( $errors ) {
		$message = $errors->get_error_message( $field );
		return $message ? '<span class="rpress-res-error" id="rpress-res-' . esc_attr( $field ) . '-error">' . esc_html( $message ) . '</span>' : '';
	};
	$invalid = function ( $field ) use ( $errors ) {
		return $errors->get_error_message( $field ) ? ' aria-invalid="true" aria-describedby="rpress-res-' . esc_attr( $field ) . '-error"' : '';
	};
	$value = function ( $field ) use ( $values ) {
		return isset( $values[ $field ] ) ? $values[ $field ] : '';
	};

	ob_start();
	?>
	<div class="rpress-reservation">
		<?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
		<?php if ( $errors->has_errors() ) : ?>
			<div class="rpress-res-alert" role="alert">
				<?php
				$form_error = $errors->get_error_message( 'form' );
				echo esc_html( $form_error ? $form_error : __( 'Please check the highlighted fields.', 'restropress' ) );
				?>
			</div>
		<?php endif; ?>
		<form class="rpress-res-form" method="post" action="" novalidate>
			<?php wp_nonce_field( 'rpress_reservation_submit', 'rpress_reservation_nonce', true ); ?>
			<input type="hidden" name="rpress_reservation_action" value="submit" />
			<input type="hidden" name="rpress_res_t" value="<?php echo esc_attr( time() ); ?>" />
			<div class="rpress-res-hp" aria-hidden="true">
				<label for="rpress-res-hpv"><?php esc_html_e( 'Leave this field empty', 'restropress' ); ?></label>
				<input type="text" id="rpress-res-hpv" name="rpress_res_hpv" value="" tabindex="-1" autocomplete="off" data-lpignore="true" data-1p-ignore="true" />
			</div>

			<div class="rpress-res-row rpress-res-row-3">
				<p class="rpress-res-field">
					<label for="rpress-res-date"><?php esc_html_e( 'Date', 'restropress' ); ?></label>
					<input type="date" id="rpress-res-date" name="rpress_res_date" required
						value="<?php echo esc_attr( $date ); ?>"
						min="<?php echo esc_attr( $window['today'] ); ?>"
						max="<?php echo esc_attr( $window['last'] ); ?>"<?php echo $invalid( 'date' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
					<?php echo $field_error( 'date' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
				<p class="rpress-res-field">
					<label for="rpress-res-party"><?php esc_html_e( 'Guests', 'restropress' ); ?></label>
					<select id="rpress-res-party" name="rpress_res_party" required<?php echo $invalid( 'party' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php for ( $i = 1; $i <= $settings['max_party']; $i++ ) : ?>
							<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $party, $i ); ?>><?php echo esc_html( $i ); ?></option>
						<?php endfor; ?>
					</select>
					<?php echo $field_error( 'party' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
				<p class="rpress-res-field">
					<label for="rpress-res-slot"><?php esc_html_e( 'Time', 'restropress' ); ?></label>
					<select id="rpress-res-slot" name="rpress_res_slot" required<?php echo $invalid( 'slot' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php if ( empty( $slots ) ) : ?>
							<option value=""><?php esc_html_e( 'No times available on this day', 'restropress' ); ?></option>
						<?php else : ?>
							<option value=""><?php esc_html_e( 'Choose a time', 'restropress' ); ?></option>
							<?php foreach ( $slots as $slot ) : ?>
								<option value="<?php echo esc_attr( $slot['ts'] ); ?>" <?php selected( $chosen, $slot['ts'] ); ?> <?php disabled( ! $slot['available'] ); ?>>
									<?php echo esc_html( $slot['available'] ? $slot['label'] : $slot['label'] . ' (' . __( 'full', 'restropress' ) . ')' ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<?php echo $field_error( 'slot' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
			</div>
			<?php
			/**
			 * Extra fields after date, guests and time (for example a table picker).
			 *
			 * @since 3.5
			 * @param array    $values Submitted values when the form is shown again.
			 * @param WP_Error $errors Field errors keyed by field name.
			 * @param string   $date   Selected date (Y-m-d).
			 * @param int      $party  Selected party size.
			 */
			do_action( 'rpress_reservation_form_after_time', $values, $errors, $date, $party );
			?>

			<p class="rpress-res-field">
				<label for="rpress-res-name"><?php esc_html_e( 'Name', 'restropress' ); ?></label>
				<input type="text" id="rpress-res-name" name="rpress_res_name" required maxlength="100" autocomplete="name"
					value="<?php echo esc_attr( $value( 'name' ) ); ?>"<?php echo $invalid( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
				<?php echo $field_error( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>
			<div class="rpress-res-row">
				<p class="rpress-res-field">
					<label for="rpress-res-phone"><?php esc_html_e( 'Phone', 'restropress' ); ?></label>
					<input type="tel" id="rpress-res-phone" name="rpress_res_phone" required maxlength="30" autocomplete="tel"
						value="<?php echo esc_attr( $value( 'phone' ) ); ?>"<?php echo $invalid( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
					<?php echo $field_error( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
				<p class="rpress-res-field">
					<label for="rpress-res-email"><?php esc_html_e( 'Email', 'restropress' ); ?></label>
					<input type="email" id="rpress-res-email" name="rpress_res_email" required autocomplete="email"
						value="<?php echo esc_attr( $value( 'email' ) ); ?>"<?php echo $invalid( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
					<?php echo $field_error( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
			</div>
			<p class="rpress-res-field">
				<label for="rpress-res-note"><?php esc_html_e( 'Note (optional)', 'restropress' ); ?></label>
				<textarea id="rpress-res-note" name="rpress_res_note" rows="3" maxlength="500" placeholder="<?php esc_attr_e( 'Allergies, high chair, occasion...', 'restropress' ); ?>"><?php echo esc_textarea( $value( 'note' ) ); ?></textarea>
			</p>
			<?php
			/**
			 * Extra fields before the submit button (for example an occasion or a deposit notice).
			 *
			 * @since 3.5
			 * @param array    $values Submitted values when the form is shown again.
			 * @param WP_Error $errors Field errors keyed by field name.
			 */
			do_action( 'rpress_reservation_form_before_submit', $values, $errors );
			?>
			<p class="rpress-res-actions">
				<button type="submit" class="rpress-res-submit"><?php esc_html_e( 'Request reservation', 'restropress' ); ?></button>
			</p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'rpress_reservation', 'rpress_reservation_shortcode' );

/**
 * Live Orders: a one-line count of today's reservations.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_live_orders_notice() {
	if ( ! current_user_can( 'edit_shop_payments' ) ) {
		return;
	}
	$upcoming = rpress_reservation_count_today( true );
	if ( ! $upcoming ) {
		return;
	}
	printf(
		'<div class="rp-notice rpress-res-live-notice" role="status"><span class="dashicons dashicons-groups" aria-hidden="true"></span> %1$s <a href="%2$s">%3$s</a></div>',
		esc_html(
			sprintf(
				/* translators: %d: number of reservations */
				_n( '%d reservation still to come today.', '%d reservations still to come today.', $upcoming, 'restropress' ),
				$upcoming
			)
		),
		esc_url( admin_url( 'edit.php?post_type=rpress_reservation&rpress_res_view=today' ) ),
		esc_html__( 'View reservations', 'restropress' )
	);
}
add_action( 'rpress_payments_page_top', 'rpress_reservation_live_orders_notice' );
