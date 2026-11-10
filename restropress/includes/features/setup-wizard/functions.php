<?php
/**
 * Setup wizard (3.5): step handlers, progress state and the launch checklist.
 *
 * Every step is saved through rpress_setup_wizard_save_step(), which the AJAX
 * endpoint and tests/setup-wizard-test.php both call, so the CLI test checks
 * exactly what the screen saves. Values go to the same options the settings
 * screens use (rpress_settings keys, plus the WordPress timezone and title).
 *
 * @package     RPRESS
 * @subpackage  Features/SetupWizard
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Option that stores wizard progress (shared with the onboarding controller).
 */
if ( ! defined( 'RPRESS_SETUP_WIZARD_STATE' ) ) {
	define( 'RPRESS_SETUP_WIZARD_STATE', 'rpress_onboarding' );
}

/**
 * Ordered wizard steps.
 *
 * @since 3.5
 * @return array slug => label
 */
function rpress_setup_wizard_steps() {
	return array(
		'store'    => __( 'Store basics', 'restropress' ),
		'services' => __( 'Services', 'restropress' ),
		'hours'    => __( 'Hours', 'restropress' ),
		'delivery' => __( 'Delivery', 'restropress' ),
		'payments' => __( 'Payments', 'restropress' ),
		'menu'     => __( 'Menu', 'restropress' ),
		'test'     => __( 'Test order', 'restropress' ),
	);
}

/**
 * Map step slugs used by earlier onboarding builds onto the wizard steps.
 *
 * @since 3.5
 * @param string $step Step slug.
 * @return string Wizard step slug, or '' when unknown.
 */
function rpress_setup_wizard_normalize_step( $step ) {
	$legacy = array(
		'profile'    => 'store',
		'appearance' => 'store',
		'ordering'   => 'services',
		'operations' => 'services',
		'config'     => 'services',
		'review'     => 'menu',
		'launch'     => 'test',
		'golive'     => 'test',
	);
	$step = sanitize_key( $step );
	if ( isset( $legacy[ $step ] ) ) {
		$step = $legacy[ $step ];
	}
	return array_key_exists( $step, rpress_setup_wizard_steps() ) ? $step : '';
}

/**
 * Wizard progress.
 *
 * @since 3.5
 * @return array
 */
function rpress_setup_wizard_get_state() {
	$defaults = array(
		'version'         => '3.5',
		'status'          => 'in_progress',
		'current_step'    => 'store',
		'completed_steps' => array(),
		'completed_tasks' => array(),
		'skipped_tasks'   => array(),
		'updated_at'      => '',
	);
	$state = get_option( RPRESS_SETUP_WIZARD_STATE, array() );
	$state = wp_parse_args( is_array( $state ) ? $state : array(), $defaults );
	foreach ( array( 'completed_steps', 'completed_tasks', 'skipped_tasks' ) as $key ) {
		$state[ $key ] = is_array( $state[ $key ] ) ? array_values( $state[ $key ] ) : array();
	}
	return $state;
}

/**
 * Merge changes into the wizard progress.
 *
 * @since 3.5
 * @param array $changes Changes.
 * @return array New state.
 */
function rpress_setup_wizard_update_state( $changes ) {
	$state               = array_merge( rpress_setup_wizard_get_state(), $changes );
	$state['updated_at'] = current_time( 'mysql' );
	update_option( RPRESS_SETUP_WIZARD_STATE, $state, false );
	return $state;
}

/**
 * Wizard steps already done (saved or skipped), legacy slugs mapped.
 *
 * @since 3.5
 * @return array { completed: string[], skipped: string[] }
 */
function rpress_setup_wizard_progress() {
	$state     = rpress_setup_wizard_get_state();
	$completed = array();
	foreach ( array_merge( $state['completed_steps'], $state['completed_tasks'] ) as $step ) {
		$step = rpress_setup_wizard_normalize_step( $step );
		if ( $step ) {
			$completed[ $step ] = true;
		}
	}
	$skipped = array();
	foreach ( $state['skipped_tasks'] as $step ) {
		$step = rpress_setup_wizard_normalize_step( $step );
		if ( $step && empty( $completed[ $step ] ) ) {
			$skipped[ $step ] = true;
		}
	}
	return array(
		'completed' => array_keys( $completed ),
		'skipped'   => array_keys( $skipped ),
	);
}

/**
 * The step to resume at: the saved current step, else the first open step.
 *
 * @since 3.5
 * @return string
 */
function rpress_setup_wizard_resume_step() {
	$state   = rpress_setup_wizard_get_state();
	$current = rpress_setup_wizard_normalize_step( $state['current_step'] );
	if ( $current ) {
		return $current;
	}
	$progress = rpress_setup_wizard_progress();
	foreach ( array_keys( rpress_setup_wizard_steps() ) as $step ) {
		if ( ! in_array( $step, $progress['completed'], true ) && ! in_array( $step, $progress['skipped'], true ) ) {
			return $step;
		}
	}
	return 'test';
}

/**
 * Whether the wizard has been finished.
 *
 * @since 3.5
 * @return bool
 */
function rpress_setup_wizard_is_complete() {
	$state = rpress_setup_wizard_get_state();
	return 'launched' === $state['status'];
}

/**
 * Record a step as saved or skipped and move the resume point on.
 *
 * @since 3.5
 * @param string $step    Wizard step slug.
 * @param bool   $skipped True when the merchant skipped the step.
 * @return array New state.
 */
function rpress_setup_wizard_mark_step( $step, $skipped = false ) {
	$state = rpress_setup_wizard_get_state();
	if ( $skipped ) {
		if ( ! in_array( $step, $state['skipped_tasks'], true ) ) {
			$state['skipped_tasks'][] = $step;
		}
	} else {
		foreach ( array( 'completed_steps', 'completed_tasks' ) as $key ) {
			if ( ! in_array( $step, $state[ $key ], true ) ) {
				$state[ $key ][] = $step;
			}
		}
		$state['skipped_tasks'] = array_values( array_diff( $state['skipped_tasks'], array( $step ) ) );
	}

	$slugs = array_keys( rpress_setup_wizard_steps() );
	$index = array_search( $step, $slugs, true );
	$next  = ( false !== $index && isset( $slugs[ $index + 1 ] ) ) ? $slugs[ $index + 1 ] : 'test';

	return rpress_setup_wizard_update_state(
		array(
			'completed_steps' => $state['completed_steps'],
			'completed_tasks' => $state['completed_tasks'],
			'skipped_tasks'   => $state['skipped_tasks'],
			'current_step'    => $next,
		)
	);
}

/**
 * Save one wizard step.
 *
 * @since 3.5
 * @param string $step Wizard step slug.
 * @param array  $data Raw (unslashed) step data.
 * @return true|WP_Error
 */
function rpress_setup_wizard_save_step( $step, $data ) {
	$step = rpress_setup_wizard_normalize_step( $step );
	if ( ! $step ) {
		return new WP_Error( 'rpress_setup_wizard_step', __( 'Unknown setup step.', 'restropress' ) );
	}
	$data   = is_array( $data ) ? $data : array();
	$result = call_user_func( 'rpress_setup_wizard_save_' . $step, $data );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	rpress_setup_wizard_mark_step( $step );
	if ( 'test' === $step ) {
		delete_option( 'rpress_show_setup_wizard' );
		rpress_setup_wizard_update_state( array( 'status' => 'launched', 'launch_confirmed_at' => current_time( 'mysql' ) ) );
	}
	do_action( 'rpress_setup_wizard_step_saved', $step, $data );
	return true;
}

/**
 * Skip a step without saving anything.
 *
 * @since 3.5
 * @param string $step Wizard step slug.
 * @return true|WP_Error
 */
function rpress_setup_wizard_skip_step( $step ) {
	$step = rpress_setup_wizard_normalize_step( $step );
	if ( ! $step ) {
		return new WP_Error( 'rpress_setup_wizard_step', __( 'Unknown setup step.', 'restropress' ) );
	}
	rpress_setup_wizard_mark_step( $step, true );
	return true;
}

/**
 * Read a scalar text value from step data.
 *
 * @param array  $data Step data.
 * @param string $key  Key.
 * @return string|null Null when the key is absent.
 */
function rpress_setup_wizard_text( $data, $key ) {
	if ( ! isset( $data[ $key ] ) || is_array( $data[ $key ] ) ) {
		return null;
	}
	return sanitize_text_field( (string) $data[ $key ] );
}

/**
 * Read a non-negative amount from step data.
 *
 * @param array  $data Step data.
 * @param string $key  Key.
 * @return float|null|false Null when absent or empty, false when invalid.
 */
function rpress_setup_wizard_amount( $data, $key ) {
	$value = rpress_setup_wizard_text( $data, $key );
	if ( null === $value || '' === trim( $value ) ) {
		return null;
	}
	$value = str_replace( ',', '.', trim( $value ) );
	if ( ! is_numeric( $value ) || (float) $value < 0 ) {
		return false;
	}
	return round( (float) $value, 2 );
}

/**
 * Format an amount for storage the way the settings screen does.
 *
 * @param float $amount Amount.
 * @return string
 */
function rpress_setup_wizard_amount_string( $amount ) {
	$amount = (float) $amount;
	return $amount == (int) $amount ? (string) (int) $amount : (string) $amount; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
}

/**
 * The value the timezone select should preselect (matches Settings > General).
 *
 * @since 3.5
 * @return string Named zone, or "UTC+5.5" style offset.
 */
function rpress_setup_wizard_timezone_choice() {
	$tz = get_option( 'timezone_string' );
	if ( $tz ) {
		return $tz;
	}
	$offset = (float) get_option( 'gmt_offset', 0 );
	if ( 0.0 === $offset ) {
		return 'UTC+0';
	}
	return $offset < 0 ? 'UTC' . $offset : 'UTC+' . $offset;
}

/**
 * Whether the site uses a named (DST-aware) timezone.
 *
 * @since 3.5
 * @return bool
 */
function rpress_setup_wizard_has_named_timezone() {
	$tz = get_option( 'timezone_string' );
	return ! empty( $tz ) && 'UTC' !== $tz && false === strpos( $tz, 'Etc/' );
}

/**
 * Step 1: store basics.
 *
 * @since 3.5
 * @param array $data Step data.
 * @return true|WP_Error
 */
function rpress_setup_wizard_save_store( $data ) {
	$name = rpress_setup_wizard_text( $data, 'restaurant_name' );
	if ( null !== $name && '' === trim( $name ) ) {
		return new WP_Error( 'rpress_setup_wizard_name', __( 'Enter your restaurant name.', 'restropress' ) );
	}

	$currency = rpress_setup_wizard_text( $data, 'currency' );
	if ( null !== $currency && function_exists( 'rpress_get_currencies' ) && ! array_key_exists( $currency, rpress_get_currencies() ) ) {
		return new WP_Error( 'rpress_setup_wizard_currency', __( 'Choose a currency from the list.', 'restropress' ) );
	}

	$timezone = rpress_setup_wizard_text( $data, 'timezone_string' );
	$offset   = null;
	if ( null !== $timezone && '' !== $timezone ) {
		if ( preg_match( '/^UTC([+-]\d{1,2}(?:\.\d{1,2})?)$/', $timezone, $m ) ) {
			$offset = (float) $m[1];
			if ( $offset < -12 || $offset > 14 ) {
				return new WP_Error( 'rpress_setup_wizard_timezone', __( 'Choose a valid timezone.', 'restropress' ) );
			}
		} elseif ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			return new WP_Error( 'rpress_setup_wizard_timezone', __( 'Choose a valid timezone.', 'restropress' ) );
		}
	}

	if ( null !== $name ) {
		// The WordPress site title is the store name on receipts, emails and the storefront.
		update_option( 'blogname', $name );
		if ( ! rpress_get_option( 'from_name' ) ) {
			rpress_update_option( 'from_name', $name );
		}
	}

	$map = array(
		'store_phone'    => 'store_phone',
		'store_address'  => 'store_address',
		'store_city'     => 'store_city',
		'store_postcode' => 'store_postcode',
		'base_country'   => 'base_country',
		'base_state'     => 'base_state',
	);
	foreach ( $map as $key => $option ) {
		if ( isset( $data[ $key ] ) && ! is_array( $data[ $key ] ) ) {
			$value = 'store_address' === $key ? sanitize_textarea_field( (string) $data[ $key ] ) : sanitize_text_field( (string) $data[ $key ] );
			rpress_update_option( $option, $value );
		}
	}
	if ( null !== $currency ) {
		rpress_update_option( 'currency', $currency );
	}

	if ( null !== $offset ) {
		update_option( 'gmt_offset', $offset );
		update_option( 'timezone_string', '' );
	} elseif ( null !== $timezone && '' !== $timezone ) {
		update_option( 'timezone_string', $timezone );
		update_option( 'gmt_offset', '' );
	}

	$time_format = rpress_setup_wizard_text( $data, 'time_format' );
	if ( in_array( $time_format, array( '12', '24' ), true ) ) {
		update_option( 'time_format', '24' === $time_format ? 'H:i' : 'g:i A' );
		rpress_update_option( 'store_time_format', '24' === $time_format ? '24hrs' : '12hrs' );
	}

	// Optional storefront look (Settings > Styles).
	$pack = rpress_setup_wizard_text( $data, 'template_pack' );
	if ( in_array( $pack, array( 'classic', 'modern' ), true ) ) {
		rpress_update_option( 'template_pack', $pack );
	}
	if ( isset( $data['primary_color'] ) && ! is_array( $data['primary_color'] ) ) {
		$color = sanitize_hex_color( (string) $data['primary_color'] );
		if ( $color ) {
			rpress_update_option( 'primary_color', $color );
		}
	}
	$layout = rpress_setup_wizard_text( $data, 'template' );
	if ( in_array( $layout, array( 'list', 'grid' ), true ) ) {
		rpress_update_option( 'template', $layout );
	}

	return true;
}

/**
 * Step 2: services, timing and order alerts.
 *
 * @since 3.5
 * @param array $data Step data.
 * @return true|WP_Error
 */
function rpress_setup_wizard_save_services( $data ) {
	$service = rpress_setup_wizard_text( $data, 'enable_service' );
	if ( ! in_array( $service, array( 'delivery_and_pickup', 'delivery', 'pickup' ), true ) ) {
		return new WP_Error( 'rpress_setup_wizard_service', __( 'Choose delivery, pickup, or both.', 'restropress' ) );
	}

	$default = rpress_setup_wizard_text( $data, 'default_service' );
	if ( 'delivery_and_pickup' !== $service ) {
		$default = $service;
	} elseif ( ! in_array( $default, array( 'delivery', 'pickup' ), true ) ) {
		$default = 'delivery';
	}

	$prep = rpress_setup_wizard_text( $data, 'prep_time' );
	if ( null !== $prep && '' !== $prep && ( ! is_numeric( $prep ) || (int) $prep < 0 || (int) $prep > 1440 ) ) {
		return new WP_Error( 'rpress_setup_wizard_prep', __( 'Prep time must be a number of minutes between 0 and 1440.', 'restropress' ) );
	}

	$emails = rpress_setup_wizard_text( $data, 'admin_notice_emails' );
	if ( null !== $emails && '' !== $emails ) {
		$list = array_filter( array_map( 'trim', preg_split( '/[\s,]+/', $emails ) ) );
		foreach ( $list as $email ) {
			if ( ! is_email( $email ) ) {
				/* translators: %s: email address */
				return new WP_Error( 'rpress_setup_wizard_email', sprintf( __( '%s is not a valid email address.', 'restropress' ), $email ) );
			}
		}
		$emails = implode( "\n", $list );
	}

	rpress_update_option( 'enable_service', $service );
	rpress_update_option( 'default_service', $default );
	if ( null !== $prep && '' !== $prep ) {
		rpress_update_option( 'prep_time', (string) (int) $prep );
	}
	if ( array_key_exists( 'enable_asap_option', $data ) ) {
		rpress_update_option( 'enable_asap_option', empty( $data['enable_asap_option'] ) ? '' : '1' );
	}
	if ( null !== $emails ) {
		rpress_update_option( 'admin_notice_emails', $emails );
	}
	if ( array_key_exists( 'enable_order_notification', $data ) ) {
		rpress_update_option( 'enable_order_notification', empty( $data['enable_order_notification'] ) ? '' : '1' );
	}
	if ( array_key_exists( 'enable_food_type', $data ) ) {
		rpress_update_option( 'enable_food_type', empty( $data['enable_food_type'] ) ? '' : '1' );
	}

	return true;
}

/**
 * Parse a time of day into array( hour, minute ).
 *
 * Uses the slot engine parser when loaded so the wizard accepts exactly what
 * the engine reads.
 *
 * @since 3.5
 * @param string $value Time.
 * @return array|null
 */
function rpress_setup_wizard_parse_time( $value ) {
	if ( class_exists( 'RPRESS_Service_Hours' ) ) {
		return RPRESS_Service_Hours::parse_time( $value );
	}
	$value = strtolower( trim( (string) $value ) );
	if ( preg_match( '/^(\d{1,2}):(\d{2})\s*([ap])m?$/', $value, $m ) ) {
		$hour = (int) $m[1] % 12 + ( 'p' === $m[3] ? 12 : 0 );
		return ( (int) $m[1] >= 1 && (int) $m[1] <= 12 && (int) $m[2] < 60 ) ? array( $hour, (int) $m[2] ) : null;
	}
	if ( preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $m ) ) {
		return ( (int) $m[1] < 24 && (int) $m[2] < 60 ) ? array( (int) $m[1], (int) $m[2] ) : null;
	}
	return null;
}

/**
 * Format hour/minute the way Settings > General stores hours ("9:00am").
 *
 * @param array $time array( hour, minute ).
 * @return string
 */
function rpress_setup_wizard_store_time( $time ) {
	$hour = (int) $time[0] % 24;
	return sprintf( '%d:%02d%s', ( $hour % 12 ) ? $hour % 12 : 12, (int) $time[1], $hour < 12 ? 'am' : 'pm' );
}

/**
 * Weekly hours for the wizard grid, as "HH:MM" values for time inputs.
 *
 * @since 3.5
 * @return array 1..7 => { open, close, closed }
 */
function rpress_setup_wizard_get_weekly_hours() {
	$saved  = rpress_get_option( 'store_hours', array() );
	$saved  = is_array( $saved ) ? $saved : array();
	$legacy = array(
		'open'  => rpress_get_option( 'open_time' ) ? rpress_get_option( 'open_time' ) : '9:00am',
		'close' => rpress_get_option( 'close_time' ) ? rpress_get_option( 'close_time' ) : '10:00pm',
	);
	$days = array();
	for ( $day = 1; $day <= 7; $day++ ) {
		$row = isset( $saved[ $day ] ) && is_array( $saved[ $day ] ) ? $saved[ $day ] : array();
		$out = array( 'closed' => ! empty( $row['closed'] ) );
		foreach ( array( 'open', 'close' ) as $key ) {
			$value       = ! empty( $row[ $key ] ) ? $row[ $key ] : $legacy[ $key ];
			$parsed      = rpress_setup_wizard_parse_time( $value );
			$out[ $key ] = $parsed ? sprintf( '%02d:%02d', $parsed[0] % 24, $parsed[1] ) : '';
		}
		$days[ $day ] = $out;
	}
	return $days;
}

/**
 * Day names keyed 1 (Monday) to 7 (Sunday), as store_hours uses.
 *
 * @since 3.5
 * @return array
 */
function rpress_setup_wizard_day_names() {
	return array(
		1 => __( 'Monday', 'restropress' ),
		2 => __( 'Tuesday', 'restropress' ),
		3 => __( 'Wednesday', 'restropress' ),
		4 => __( 'Thursday', 'restropress' ),
		5 => __( 'Friday', 'restropress' ),
		6 => __( 'Saturday', 'restropress' ),
		7 => __( 'Sunday', 'restropress' ),
	);
}

/**
 * Whether core's weekly hours are in charge (not the Store Timing extension).
 *
 * @since 3.5
 * @return bool
 */
function rpress_setup_wizard_core_hours() {
	return ! function_exists( 'rpress_basic_store_hours_active' ) || rpress_basic_store_hours_active();
}

/**
 * Step 3: weekly hours, saved to store_hours[1..7] like Settings > General.
 *
 * @since 3.5
 * @param array $data Step data: days[1..7][open|close|closed].
 * @return true|WP_Error
 */
function rpress_setup_wizard_save_hours( $data ) {
	if ( ! rpress_setup_wizard_core_hours() ) {
		// The Store Timing extension owns the schedule; nothing to save here.
		return true;
	}
	$days = isset( $data['days'] ) && is_array( $data['days'] ) ? $data['days'] : array();
	if ( ! $days ) {
		return new WP_Error( 'rpress_setup_wizard_hours', __( 'Set your opening hours.', 'restropress' ) );
	}

	$names = rpress_setup_wizard_day_names();
	$clean = array();
	$first = null;
	foreach ( $names as $number => $label ) {
		$row = isset( $days[ $number ] ) && is_array( $days[ $number ] ) ? $days[ $number ] : array();
		$open  = rpress_setup_wizard_parse_time( isset( $row['open'] ) && ! is_array( $row['open'] ) ? sanitize_text_field( (string) $row['open'] ) : '' );
		$close = rpress_setup_wizard_parse_time( isset( $row['close'] ) && ! is_array( $row['close'] ) ? sanitize_text_field( (string) $row['close'] ) : '' );
		if ( ! empty( $row['closed'] ) ) {
			// Keep the times so reopening the day later restores them.
			$clean[ $number ] = array(
				'open'   => $open ? rpress_setup_wizard_store_time( $open ) : '',
				'close'  => $close ? rpress_setup_wizard_store_time( $close ) : '',
				'closed' => '1',
			);
			continue;
		}
		if ( ! $open || ! $close ) {
			/* translators: %s: day name */
			return new WP_Error( 'rpress_setup_wizard_hours', sprintf( __( 'Enter an opening and closing time for %s, or mark it closed.', 'restropress' ), $label ) );
		}
		if ( $open[0] * 60 + $open[1] === ( $close[0] % 24 ) * 60 + $close[1] ) {
			/* translators: %s: day name */
			return new WP_Error( 'rpress_setup_wizard_hours', sprintf( __( 'Opening and closing times on %s are the same. Change one, or mark the day closed.', 'restropress' ), $label ) );
		}
		$clean[ $number ] = array(
			'open'  => rpress_setup_wizard_store_time( $open ),
			'close' => rpress_setup_wizard_store_time( $close ),
		);
		if ( null === $first ) {
			$first = $clean[ $number ];
		}
	}

	if ( function_exists( 'rpress_settings_sanitize_store_hours_type' ) ) {
		$clean = rpress_settings_sanitize_store_hours_type( $clean );
	}
	rpress_update_option( 'store_hours', $clean );
	// Keep the legacy pair (fallback for unconfigured days and older add-ons) in step.
	if ( $first ) {
		rpress_update_option( 'open_time', $first['open'] );
		rpress_update_option( 'close_time', $first['close'] );
	}
	return true;
}

/**
 * Whether core prices delivery with the flat fee (no Delivery Fee extension).
 *
 * @since 3.5
 * @return bool
 */
function rpress_setup_wizard_core_delivery_fee() {
	return ! function_exists( 'rpress_flat_delivery_fee_active' ) || rpress_flat_delivery_fee_active();
}

/**
 * Step 4: delivery fee and minimum order amounts.
 *
 * @since 3.5
 * @param array $data Step data.
 * @return true|WP_Error
 */
function rpress_setup_wizard_save_delivery( $data ) {
	$fee     = rpress_setup_wizard_amount( $data, 'flat_delivery_fee' );
	$min_del = rpress_setup_wizard_amount( $data, 'minimum_order_price' );
	$min_pu  = rpress_setup_wizard_amount( $data, 'minimum_order_price_pickup' );
	if ( false === $fee || false === $min_del || false === $min_pu ) {
		return new WP_Error( 'rpress_setup_wizard_amount', __( 'Amounts must be numbers of 0 or more.', 'restropress' ) );
	}

	if ( array_key_exists( 'flat_delivery_fee', $data ) && rpress_setup_wizard_core_delivery_fee() ) {
		rpress_update_option( 'flat_delivery_fee', $fee ? rpress_setup_wizard_amount_string( $fee ) : '' );
	}
	if ( array_key_exists( 'minimum_order_price', $data ) ) {
		rpress_update_option( 'minimum_order_price', $min_del ? rpress_setup_wizard_amount_string( $min_del ) : '' );
	}
	if ( array_key_exists( 'minimum_order_price_pickup', $data ) ) {
		rpress_update_option( 'minimum_order_price_pickup', $min_pu ? rpress_setup_wizard_amount_string( $min_pu ) : '' );
	}
	if ( array_key_exists( 'minimum_order_price', $data ) || array_key_exists( 'minimum_order_price_pickup', $data ) ) {
		$has_min = (float) rpress_get_option( 'minimum_order_price', 0 ) > 0 || (float) rpress_get_option( 'minimum_order_price_pickup', 0 ) > 0;
		rpress_update_option( 'allow_minimum_order', $has_min ? '1' : '' );
	}
	return true;
}

/**
 * Enable or disable a gateway, keeping default_gateway valid.
 *
 * @since 3.5
 * @param string $gateway Gateway id.
 * @param bool   $enabled Enable.
 * @return void
 */
function rpress_setup_wizard_set_gateway( $gateway, $enabled ) {
	$gateways = rpress_get_option( 'gateways', array() );
	$gateways = is_array( $gateways ) ? $gateways : array();
	$default  = rpress_get_option( 'default_gateway', '' );

	if ( $enabled ) {
		$gateways[ $gateway ] = 1;
		if ( ! $default || empty( $gateways[ $default ] ) ) {
			rpress_update_option( 'default_gateway', $gateway );
		}
	} else {
		unset( $gateways[ $gateway ] );
		if ( $gateway === $default ) {
			reset( $gateways );
			rpress_update_option( 'default_gateway', $gateways ? key( $gateways ) : '' );
		}
	}
	rpress_update_option( 'gateways', $gateways );
}

/**
 * Whether the store is in payment test mode.
 *
 * @since 3.5
 * @return bool
 */
function rpress_setup_wizard_test_mode() {
	return function_exists( 'rpress_is_test_mode' ) ? (bool) rpress_is_test_mode() : (bool) rpress_get_option( 'test_mode', false );
}

/**
 * Step 5: payments. Cash on by default; Stripe keys for the active mode; PayPal on/off.
 *
 * @since 3.5
 * @param array $data Step data.
 * @return true|WP_Error
 */
function rpress_setup_wizard_save_payments( $data ) {
	$test   = rpress_setup_wizard_test_mode();
	$prefix = $test ? 'test_' : 'live_';
	$pk     = rpress_setup_wizard_text( $data, 'stripe_publishable_key' );
	$sk     = rpress_setup_wizard_text( $data, 'stripe_secret_key' );
	$pk     = null === $pk ? '' : trim( $pk );
	$sk     = null === $sk ? '' : trim( $sk );

	if ( '' !== $pk && ! preg_match( '/^pk_(test|live)_[A-Za-z0-9]+$/', $pk ) ) {
		return new WP_Error( 'rpress_setup_wizard_stripe', __( 'The Stripe publishable key should start with pk_test_ or pk_live_.', 'restropress' ) );
	}
	if ( '' !== $sk && ! preg_match( '/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $sk ) ) {
		return new WP_Error( 'rpress_setup_wizard_stripe', __( 'The Stripe secret key should start with sk_test_, sk_live_ or rk_.', 'restropress' ) );
	}
	foreach ( array( $pk, $sk ) as $key ) {
		if ( '' !== $key && ( false !== strpos( $key, '_test_' ) ) !== $test ) {
			return new WP_Error(
				'rpress_setup_wizard_stripe_mode',
				$test
					? __( 'Your store is in test mode, so use your Stripe test keys (pk_test_ and sk_test_).', 'restropress' )
					: __( 'Your store takes live payments, so use your Stripe live keys (pk_live_ and sk_live_).', 'restropress' )
			);
		}
	}

	if ( array_key_exists( 'cash_gateway', $data ) ) {
		rpress_setup_wizard_set_gateway( 'cash_on_delivery', ! empty( $data['cash_gateway'] ) );
	}
	if ( array_key_exists( 'paypal_enabled', $data ) ) {
		rpress_setup_wizard_set_gateway( 'paypal', ! empty( $data['paypal_enabled'] ) );
	}
	if ( array_key_exists( 'stripe_enabled', $data ) ) {
		rpress_setup_wizard_set_gateway( 'stripe', ! empty( $data['stripe_enabled'] ) );
	}
	if ( '' !== $pk ) {
		rpress_update_option( $prefix . 'publishable_key', $pk );
	}
	if ( '' !== $sk ) {
		// Only replaced when a new key is typed; the saved key is never sent back to the browser.
		rpress_update_option( $prefix . 'secret_key', $sk );
	}
	return true;
}

/**
 * Step 6: menu. Menu items are created by the importer screens; this records the step.
 *
 * @since 3.5
 * @param array $data Step data.
 * @return true
 */
function rpress_setup_wizard_save_menu( $data ) {
	return true;
}

/**
 * Step 7: test order. Finishing never blocks; the checklist shows what is left.
 *
 * @since 3.5
 * @param array $data Step data.
 * @return true
 */
function rpress_setup_wizard_save_test( $data ) {
	return true;
}

/**
 * The storefront menu page URL (Order Online page, else checkout, else home).
 *
 * @since 3.5
 * @return string
 */
function rpress_setup_wizard_storefront_url() {
	foreach ( array( 'food_items_page', 'purchase_page' ) as $option ) {
		$page_id = (int) rpress_get_option( $option, 0 );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}
	}
	return home_url( '/' );
}

/**
 * Live Orders screen URL.
 *
 * @since 3.5
 * @return string
 */
function rpress_setup_wizard_live_orders_url() {
	return admin_url( 'admin.php?page=rpress-payment-history&view=live' );
}

/**
 * Whether Stripe is enabled but missing keys for the active mode.
 *
 * @since 3.5
 * @return bool
 */
function rpress_setup_wizard_stripe_needs_keys() {
	$gateways = (array) rpress_get_option( 'gateways', array() );
	if ( empty( $gateways['stripe'] ) ) {
		return false;
	}
	$prefix = rpress_setup_wizard_test_mode() ? 'test_' : 'live_';
	return ! rpress_get_option( $prefix . 'publishable_key', '' ) || ! rpress_get_option( $prefix . 'secret_key', '' );
}

/**
 * Launch checklist: what is set and what is still missing.
 *
 * @since 3.5
 * @return array[] Each: id, label, done (bool), hint, step (wizard step to fix it) or url.
 */
function rpress_setup_wizard_checklist() {
	$service  = rpress_get_option( 'enable_service', 'delivery_and_pickup' );
	$gateways = array_filter( (array) rpress_get_option( 'gateways', array() ) );
	$hours    = rpress_get_option( 'store_hours', array() );
	$menu     = wp_count_posts( 'fooditem' );
	$orders   = wp_count_posts( 'rpress_payment' );
	$orders   = $orders ? array_sum( array_map( 'intval', (array) $orders ) ) : 0;
	$page_id  = (int) rpress_get_option( 'food_items_page', 0 );
	$emails   = rpress_get_option( 'admin_notice_emails', get_option( 'admin_email' ) );

	$items = array(
		array(
			'id'    => 'store',
			'label' => __( 'Store name and address', 'restropress' ),
			'done'  => '' !== trim( (string) get_bloginfo( 'name' ) ) && '' !== trim( (string) rpress_get_option( 'store_address', '' ) ),
			'hint'  => __( 'Shown on receipts, emails and your storefront.', 'restropress' ),
			'step'  => 'store',
		),
		array(
			'id'    => 'timezone',
			'label' => __( 'Timezone set to your city', 'restropress' ),
			'done'  => rpress_setup_wizard_has_named_timezone(),
			'hint'  => __( 'A city timezone keeps hours right through daylight saving changes.', 'restropress' ),
			'step'  => 'store',
		),
		array(
			'id'    => 'hours',
			'label' => __( 'Opening hours', 'restropress' ),
			'done'  => ! rpress_setup_wizard_core_hours() || ! empty( rpress_get_option( 'enable_always_open' ) ) || ( is_array( $hours ) && count( $hours ) >= 7 ),
			'hint'  => __( 'Customers can only order while you are open.', 'restropress' ),
			'step'  => 'hours',
		),
		array(
			'id'    => 'payments',
			'label' => __( 'A payment method is on', 'restropress' ),
			'done'  => ! empty( $gateways ),
			'hint'  => __( 'Cash on delivery is enough to start.', 'restropress' ),
			'step'  => 'payments',
		),
	);
	if ( rpress_setup_wizard_stripe_needs_keys() ) {
		$items[] = array(
			'id'    => 'stripe',
			'label' => __( 'Stripe API keys', 'restropress' ),
			'done'  => false,
			'hint'  => __( 'Stripe is on but has no keys, so card payments will fail.', 'restropress' ),
			'step'  => 'payments',
		);
	}
	$items[] = array(
		'id'    => 'menu',
		'label' => __( 'At least one menu item published', 'restropress' ),
		'done'  => $menu && ! empty( $menu->publish ),
		'hint'  => __( 'Import your menu, load the sample menu, or add an item.', 'restropress' ),
		'step'  => 'menu',
	);
	$items[] = array(
		'id'    => 'storefront',
		'label' => __( 'Storefront menu page published', 'restropress' ),
		'done'  => $page_id && 'publish' === get_post_status( $page_id ),
		'hint'  => __( 'The page customers order from (Settings > General > Pages).', 'restropress' ),
		'url'   => admin_url( 'admin.php?page=rpress-settings&tab=general' ),
	);
	$items[] = array(
		'id'    => 'alerts',
		'label' => __( 'New order alert email', 'restropress' ),
		'done'  => '' !== trim( (string) $emails ),
		'hint'  => __( 'Where new order emails go.', 'restropress' ),
		'step'  => 'services',
	);
	if ( 'pickup' !== $service && ! rpress_setup_wizard_core_delivery_fee() ) {
		$items[] = array(
			'id'    => 'zones',
			'label' => __( 'Delivery zones', 'restropress' ),
			'done'  => true,
			'hint'  => __( 'Managed by the Delivery Fee extension.', 'restropress' ),
			'url'   => admin_url( 'admin.php?page=rpress-settings&tab=general' ),
		);
	}
	$items[] = array(
		'id'    => 'test_order',
		'label' => __( 'A test order placed', 'restropress' ),
		'done'  => $orders > 0,
		'hint'  => __( 'Place one from your storefront to check checkout, emails and Live Orders.', 'restropress' ),
		'url'   => rpress_setup_wizard_storefront_url(),
	);

	return apply_filters( 'rpress_setup_wizard_checklist', $items );
}

/**
 * Checklist markup (the wizard script renders the same structure on refresh).
 *
 * @since 3.5
 * @param array[] $items Checklist items.
 * @return void
 */
function rpress_setup_wizard_render_checklist( $items ) {
	echo '<ul class="rp-sw-checklist">';
	foreach ( $items as $item ) {
		$done = ! empty( $item['done'] );
		echo '<li class="' . ( $done ? 'is-done' : 'is-missing' ) . '" data-id="' . esc_attr( $item['id'] ) . '">';
		echo '<span class="rp-sw-ck-icon" aria-hidden="true">' . ( $done ? '&#10003;' : '!' ) . '</span>';
		echo '<span class="rp-sw-ck-text"><b>' . esc_html( $item['label'] ) . '</b>';
		echo '<span class="screen-reader-text"> ' . ( $done ? esc_html__( 'Done', 'restropress' ) : esc_html__( 'Still missing', 'restropress' ) ) . '</span>';
		if ( ! $done && ! empty( $item['hint'] ) ) {
			echo '<small>' . esc_html( $item['hint'] ) . '</small>';
		}
		echo '</span>';
		if ( ! $done ) {
			if ( ! empty( $item['step'] ) ) {
				echo '<button type="button" class="rp-btn rp-btn-secondary rp-ob-btn-sm rp-sw-ck-fix" data-step="' . esc_attr( $item['step'] ) . '">' . esc_html__( 'Fix', 'restropress' ) . '</button>';
			} elseif ( ! empty( $item['url'] ) ) {
				echo '<a class="rp-btn rp-btn-secondary rp-ob-btn-sm" href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open', 'restropress' ) . '</a>';
			}
		}
		echo '</li>';
	}
	echo '</ul>';
}

/**
 * Whether this activation is a brand new install (no earlier RestroPress data).
 *
 * Call before the install routine writes rpress_version.
 *
 * @since 3.5
 * @return bool
 */
function rpress_setup_wizard_is_fresh_install() {
	return false === get_option( 'rpress_version', false ) && false === get_option( RPRESS_SETUP_WIZARD_STATE, false );
}

/**
 * Whether a request may be redirected to the wizard after activation.
 *
 * Never on AJAX, cron, REST, CLI, the network admin, or a bulk activation.
 *
 * @since 3.5
 * @return bool
 */
function rpress_setup_wizard_can_redirect() {
	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return false;
	}
	if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return false;
	}
	return current_user_can( 'manage_shop_settings' );
}
