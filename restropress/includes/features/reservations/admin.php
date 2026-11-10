<?php
/**
 * Reservations: admin list, row/bulk actions, filters and the edit box.
 *
 * @package     RPRESS
 * @subpackage  Features/Reservations
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Whether the current request is the reservations list screen.
 *
 * @since 3.5
 * @return bool
 */
function rpress_reservation_is_list_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && 'edit-rpress_reservation' === $screen->id;
}

/**
 * List columns.
 *
 * @since 3.5
 * @param array $columns Columns.
 * @return array
 */
function rpress_reservation_columns( $columns ) {
	return array(
		'cb'              => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
		'rpress_res_when' => __( 'Date / Time', 'restropress' ),
		'title'           => __( 'Name', 'restropress' ),
		'rpress_res_party' => __( 'Guests', 'restropress' ),
		'rpress_res_phone' => __( 'Phone', 'restropress' ),
		'rpress_res_email' => __( 'Email', 'restropress' ),
		'rpress_res_status' => __( 'Status', 'restropress' ),
	);
}
add_filter( 'manage_rpress_reservation_posts_columns', 'rpress_reservation_columns' );

/**
 * Sortable columns.
 *
 * @since 3.5
 * @param array $columns Sortable columns.
 * @return array
 */
function rpress_reservation_sortable_columns( $columns ) {
	$columns['rpress_res_when'] = 'rpress_res_when';
	return $columns;
}
add_filter( 'manage_edit-rpress_reservation_sortable_columns', 'rpress_reservation_sortable_columns' );

/**
 * Column output.
 *
 * @since 3.5
 * @param string $column  Column key.
 * @param int    $post_id Reservation ID.
 * @return void
 */
function rpress_reservation_column_content( $column, $post_id ) {
	$res = rpress_get_reservation( $post_id );
	if ( ! $res ) {
		return;
	}
	switch ( $column ) {
		case 'rpress_res_when':
			if ( $res['ts'] ) {
				echo '<strong>' . esc_html( rpress_reservation_format_date( $res['ts'] ) ) . '</strong><br />' . esc_html( rpress_reservation_format_time( $res['ts'] ) );
			}
			$booked = get_post_timestamp( $post_id );
			if ( $booked ) {
				/* translators: %s: time since the booking was made, e.g. "5 mins" */
				echo '<div class="rpress-res-booked">' . esc_html( sprintf( __( 'Booked %s ago', 'restropress' ), human_time_diff( $booked ) ) ) . '</div>';
			}
			break;
		case 'rpress_res_party':
			echo esc_html( $res['party'] );
			break;
		case 'rpress_res_phone':
			if ( $res['phone'] ) {
				echo '<a href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $res['phone'] ) ) . '">' . esc_html( $res['phone'] ) . '</a>';
			}
			break;
		case 'rpress_res_email':
			if ( $res['email'] ) {
				echo '<a href="' . esc_url( 'mailto:' . $res['email'] ) . '">' . esc_html( $res['email'] ) . '</a>';
			}
			break;
		case 'rpress_res_status':
			$labels = rpress_reservation_statuses();
			echo '<span class="rpress-res-badge rpress-res-badge-' . esc_attr( $res['status'] ) . '">' . esc_html( $labels[ $res['status'] ] ) . '</span>';
			if ( '' !== $res['note'] ) {
				echo '<div class="rpress-res-note" title="' . esc_attr( $res['note'] ) . '">' . esc_html( wp_trim_words( $res['note'], 12 ) ) . '</div>';
			}
			break;
	}
}
add_action( 'manage_rpress_reservation_posts_custom_column', 'rpress_reservation_column_content', 10, 2 );

/**
 * Status change URL for a row/edit action.
 *
 * @since 3.5
 * @param int    $id     Reservation ID.
 * @param string $status Target status.
 * @return string
 */
function rpress_reservation_status_url( $id, $status ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'rpress_reservation_status',
				'id'     => (int) $id,
				'status' => $status,
			),
			admin_url( 'admin-post.php' )
		),
		'rpress_res_status_' . (int) $id
	);
}

/**
 * Row actions: Confirm / Decline (pending), Complete / Cancel (confirmed).
 *
 * @since 3.5
 * @param array   $actions Actions.
 * @param WP_Post $post    Post.
 * @return array
 */
function rpress_reservation_row_actions( $actions, $post ) {
	if ( 'rpress_reservation' !== $post->post_type ) {
		return $actions;
	}
	unset( $actions['inline hide-if-no-js'] );
	if ( 'trash' === $post->post_status || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	$status = rpress_get_reservation_status( $post->ID );
	$map    = array(
		'pending'   => array(
			'confirmed' => __( 'Confirm', 'restropress' ),
			'declined'  => __( 'Decline', 'restropress' ),
		),
		'confirmed' => array(
			'completed' => __( 'Complete', 'restropress' ),
			'cancelled' => __( 'Cancel', 'restropress' ),
		),
		'declined'  => array(
			'confirmed' => __( 'Confirm', 'restropress' ),
		),
		'cancelled' => array(
			'confirmed' => __( 'Confirm', 'restropress' ),
		),
	);
	$new = array();
	if ( isset( $map[ $status ] ) ) {
		foreach ( $map[ $status ] as $target => $label ) {
			$new[ 'rpress_res_' . $target ] = '<a href="' . esc_url( rpress_reservation_status_url( $post->ID, $target ) ) . '" class="rpress-res-action-' . esc_attr( $target ) . '">' . esc_html( $label ) . '</a>';
		}
	}
	return array_merge( $new, $actions );
}
add_filter( 'post_row_actions', 'rpress_reservation_row_actions', 10, 2 );

/**
 * admin-post: change one reservation's status.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_handle_status_action() {
	$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	check_admin_referer( 'rpress_res_status_' . $id );
	if ( ! $id || 'rpress_reservation' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html__( 'You are not allowed to change this reservation.', 'restropress' ), '', array( 'response' => 403 ) );
	}
	$changed = rpress_set_reservation_status( $id, $status );

	$back = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=rpress_reservation' );
	$back = remove_query_arg( array( 'rpress_res_updated', 'rpress_res_count' ), $back );
	wp_safe_redirect( add_query_arg( array( 'rpress_res_updated' => $changed ? $status : 'none', 'rpress_res_count' => $changed ? 1 : 0 ), $back ) );
	exit;
}
add_action( 'admin_post_rpress_reservation_status', 'rpress_reservation_handle_status_action' );

/**
 * Bulk actions.
 *
 * @since 3.5
 * @param array $actions Bulk actions.
 * @return array
 */
function rpress_reservation_bulk_actions( $actions ) {
	unset( $actions['edit'] );
	return array_merge(
		array(
			'rpress_res_confirmed' => __( 'Confirm', 'restropress' ),
			'rpress_res_declined'  => __( 'Decline', 'restropress' ),
			'rpress_res_completed' => __( 'Mark completed', 'restropress' ),
			'rpress_res_cancelled' => __( 'Cancel', 'restropress' ),
		),
		$actions
	);
}
add_filter( 'bulk_actions-edit-rpress_reservation', 'rpress_reservation_bulk_actions' );

/**
 * Handle bulk status actions (core already checked the bulk nonce).
 *
 * @since 3.5
 * @param string $redirect Redirect URL.
 * @param string $action   Action.
 * @param array  $ids      Post IDs.
 * @return string
 */
function rpress_reservation_handle_bulk_actions( $redirect, $action, $ids ) {
	if ( 0 !== strpos( $action, 'rpress_res_' ) ) {
		return $redirect;
	}
	$status = substr( $action, strlen( 'rpress_res_' ) );
	$count  = 0;
	foreach ( (array) $ids as $id ) {
		if ( current_user_can( 'edit_post', $id ) && rpress_set_reservation_status( (int) $id, $status ) ) {
			$count++;
		}
	}
	return add_query_arg( array( 'rpress_res_updated' => $status, 'rpress_res_count' => $count ), $redirect );
}
add_filter( 'handle_bulk_actions-edit-rpress_reservation', 'rpress_reservation_handle_bulk_actions', 10, 3 );

/**
 * Notice after a status change.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_admin_notices() {
	if ( ! rpress_reservation_is_list_screen() && ! ( get_current_screen() && 'rpress_reservation' === get_current_screen()->id ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
	if ( empty( $_GET['rpress_res_updated'] ) ) {
		return;
	}
	$status = sanitize_key( wp_unslash( $_GET['rpress_res_updated'] ) );
	$count  = isset( $_GET['rpress_res_count'] ) ? absint( $_GET['rpress_res_count'] ) : 0;
	// phpcs:enable
	$labels = rpress_reservation_statuses();
	if ( ! $count || ! isset( $labels[ $status ] ) ) {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'No reservations were changed.', 'restropress' ) . '</p></div>';
		return;
	}
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
		sprintf(
			/* translators: 1: count, 2: status label */
			_n( '%1$d reservation marked %2$s.', '%1$d reservations marked %2$s.', $count, 'restropress' ),
			$count,
			strtolower( $labels[ $status ] )
		)
	) . '</p></div>';
}
add_action( 'admin_notices', 'rpress_reservation_admin_notices' );

/**
 * Reservations list: a one-line pointer to the Table Booking extension,
 * dismissible per user. Hidden when the extension is active.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_table_booking_notice() {
	if ( ! rpress_reservation_is_list_screen() || rpress_reservation_has_table_booking() || ! current_user_can( 'manage_shop_settings' ) ) {
		return;
	}
	$user_id = get_current_user_id();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked right here.
	if ( isset( $_GET['rpress_res_tb_dismiss'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['rpress_res_tb_dismiss'] ) ), 'rpress_res_tb_dismiss' ) ) {
		update_user_meta( $user_id, 'rpress_res_tb_notice_dismissed', 1 );
	}
	if ( get_user_meta( $user_id, 'rpress_res_tb_notice_dismissed', true ) ) {
		return;
	}
	$dismiss = wp_nonce_url( add_query_arg( array() ), 'rpress_res_tb_dismiss', 'rpress_res_tb_dismiss' );
	echo '<div class="notice notice-info rpress-res-tb-notice"><p>'
		. esc_html__( 'Take deposits to cut no-shows, record special occasions and verify phone numbers by SMS with the Table Booking extension.', 'restropress' )
		. ' <a href="' . esc_url( rpress_reservation_table_booking_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View extension', 'restropress' ) . ' &rarr;</a>'
		. ' <span aria-hidden="true">&middot;</span> <a class="rpress-res-tb-dismiss" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'restropress' ) . '</a>'
		. '</p></div>';
}
add_action( 'admin_notices', 'rpress_reservation_table_booking_notice' );

/**
 * Today / Upcoming / Past / Pending views.
 *
 * @since 3.5
 * @param array $views Views.
 * @return array
 */
function rpress_reservation_views( $views ) {
	$base    = admin_url( 'edit.php?post_type=rpress_reservation' );
	$current = isset( $_GET['rpress_res_view'] ) ? sanitize_key( wp_unslash( $_GET['rpress_res_view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$status  = isset( $_GET['rpress_res_status'] ) ? sanitize_key( wp_unslash( $_GET['rpress_res_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$pending = rpress_reservation_count_pending();

	$link = function ( $args, $label, $active ) use ( $base ) {
		return '<a href="' . esc_url( add_query_arg( $args, $base ) ) . '"' . ( $active ? ' class="current" aria-current="page"' : '' ) . '>' . $label . '</a>';
	};

	$new = array(
		'rpress_res_today'    => $link( array( 'rpress_res_view' => 'today' ), esc_html__( 'Today', 'restropress' ) . ' <span class="count">(' . number_format_i18n( rpress_reservation_count_today() ) . ')</span>', 'today' === $current ),
		'rpress_res_upcoming' => $link( array( 'rpress_res_view' => 'upcoming' ), esc_html__( 'Upcoming', 'restropress' ), 'upcoming' === $current && 'pending' !== $status ),
		'rpress_res_pending'  => $link( array( 'rpress_res_view' => 'upcoming', 'rpress_res_status' => 'pending' ), esc_html__( 'Needs action', 'restropress' ) . ' <span class="count">(' . number_format_i18n( $pending ) . ')</span>', 'pending' === $status ),
		'rpress_res_past'     => $link( array( 'rpress_res_view' => 'past' ), esc_html__( 'Past', 'restropress' ), 'past' === $current ),
	);

	if ( isset( $views['all'] ) ) {
		// "All" carries its own view so the landing redirect to Needs action
		// (rpress_reservation_landing_view) never catches it.
		$views['all'] = preg_replace( '/href="[^"]*"/', 'href="' . esc_url( add_query_arg( 'rpress_res_view', 'all', $base ) ) . '"', $views['all'], 1 );
		$views['all'] = str_replace( array( ' class="current"', ' aria-current="page"' ), '', $views['all'] );
		if ( in_array( $current, array( '', 'all' ), true ) && ! $status ) {
			$views['all'] = str_replace( '<a ', '<a class="current" aria-current="page" ', $views['all'] );
		}
	}
	unset( $views['publish'] );
	if ( isset( $views['all'] ) ) {
		$all = $views['all'];
		unset( $views['all'] );
		return array_merge( array( 'all' => $all ), $new, $views );
	}
	return array_merge( $new, $views );
}
add_filter( 'views_edit-rpress_reservation', 'rpress_reservation_views' );

/**
 * Opening Reservations with no filter lands on "Needs action" while requests
 * are waiting, so new ones are never buried; otherwise All, newest first.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_landing_view() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect.
	if ( ! isset( $_GET['post_type'] ) || 'rpress_reservation' !== $_GET['post_type'] ) {
		return;
	}
	$args = array_filter(
		array_diff_key( $_GET, array( 'post_type' => 1 ) ),
		function ( $value ) {
			return is_array( $value ) || '' !== (string) $value;
		}
	);
	// phpcs:enable
	if ( $args || ! rpress_reservation_count_pending() ) {
		return;
	}
	wp_safe_redirect( add_query_arg( array( 'rpress_res_view' => 'upcoming', 'rpress_res_status' => 'pending' ), admin_url( 'edit.php?post_type=rpress_reservation' ) ) );
	exit;
}
add_action( 'load-edit.php', 'rpress_reservation_landing_view' );

/**
 * Status and date filters above the list.
 *
 * @since 3.5
 * @param string $post_type Post type.
 * @return void
 */
function rpress_reservation_list_filters( $post_type ) {
	if ( 'rpress_reservation' !== $post_type ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters.
	$status = isset( $_GET['rpress_res_status'] ) ? sanitize_key( wp_unslash( $_GET['rpress_res_status'] ) ) : '';
	$date   = isset( $_GET['rpress_res_date'] ) ? sanitize_text_field( wp_unslash( $_GET['rpress_res_date'] ) ) : '';
	$view   = isset( $_GET['rpress_res_view'] ) ? sanitize_key( wp_unslash( $_GET['rpress_res_view'] ) ) : '';
	// phpcs:enable
	echo '<label class="screen-reader-text" for="rpress-res-status-filter">' . esc_html__( 'Filter by status', 'restropress' ) . '</label>';
	echo '<select name="rpress_res_status" id="rpress-res-status-filter"><option value="">' . esc_html__( 'All statuses', 'restropress' ) . '</option>';
	foreach ( rpress_reservation_statuses() as $key => $label ) {
		echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select>';
	echo '<label class="screen-reader-text" for="rpress-res-date-filter">' . esc_html__( 'Filter by date', 'restropress' ) . '</label>';
	echo '<input type="date" name="rpress_res_date" id="rpress-res-date-filter" value="' . esc_attr( rpress_reservation_is_valid_date( $date ) ? $date : '' ) . '" />';
	if ( $view ) {
		echo '<input type="hidden" name="rpress_res_view" value="' . esc_attr( $view ) . '" />';
	}
}
add_action( 'restrict_manage_posts', 'rpress_reservation_list_filters' );

/**
 * No months dropdown: reservations are filtered by booking date instead.
 *
 * @since 3.5
 * @param bool   $disable   Whether to disable.
 * @param string $post_type Post type.
 * @return bool
 */
function rpress_reservation_disable_months( $disable, $post_type ) {
	return 'rpress_reservation' === $post_type ? true : $disable;
}
add_filter( 'disable_months_dropdown', 'rpress_reservation_disable_months', 10, 2 );

/**
 * Apply views, filters and booking-time ordering to the list query.
 *
 * @since 3.5
 * @param WP_Query $query Query.
 * @return void
 */
function rpress_reservation_list_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'rpress_reservation' !== $query->get( 'post_type' ) ) {
		return;
	}
	global $pagenow;
	if ( 'edit.php' !== $pagenow ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters.
	$view   = isset( $_GET['rpress_res_view'] ) ? sanitize_key( wp_unslash( $_GET['rpress_res_view'] ) ) : '';
	$status = isset( $_GET['rpress_res_status'] ) ? sanitize_key( wp_unslash( $_GET['rpress_res_status'] ) ) : '';
	$date   = isset( $_GET['rpress_res_date'] ) ? sanitize_text_field( wp_unslash( $_GET['rpress_res_date'] ) ) : '';
	// phpcs:enable

	$meta  = array( 'relation' => 'AND' );
	$order = 'DESC';
	if ( 'today' === $view ) {
		$meta[] = array( 'key' => '_rpress_res_date', 'value' => rpress_get_wp_now()->format( 'Y-m-d' ) );
		$order  = 'ASC';
	} elseif ( 'upcoming' === $view ) {
		$meta[] = array( 'key' => '_rpress_res_ts', 'value' => time(), 'compare' => '>=', 'type' => 'NUMERIC' );
		if ( ! $status ) {
			$meta[] = array( 'key' => '_rpress_res_status', 'value' => rpress_reservation_active_statuses(), 'compare' => 'IN' );
		}
		$order = 'ASC';
	} elseif ( 'past' === $view ) {
		$meta[] = array( 'key' => '_rpress_res_ts', 'value' => time(), 'compare' => '<', 'type' => 'NUMERIC' );
	}
	if ( $status && isset( rpress_reservation_statuses()[ $status ] ) ) {
		$meta[] = array( 'key' => '_rpress_res_status', 'value' => $status );
	}
	if ( rpress_reservation_is_valid_date( $date ) ) {
		$meta[] = array( 'key' => '_rpress_res_date', 'value' => $date );
		$order  = 'ASC';
	}
	if ( count( $meta ) > 1 ) {
		$query->set( 'meta_query', $meta );
	}

	$orderby = $query->get( 'orderby' );
	// All (no view, status or date): newest bookings first, so a request
	// that just came in is at the top.
	if ( ! $orderby && in_array( $view, array( '', 'all' ), true ) && ! $status && ! rpress_reservation_is_valid_date( $date ) ) {
		$query->set( 'orderby', 'date' );
		if ( ! isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query->set( 'order', 'DESC' );
		}
		return;
	}
	if ( ! $orderby || 'rpress_res_when' === $orderby ) {
		$query->set( 'meta_key', '_rpress_res_ts' );
		$query->set( 'orderby', 'meta_value_num' );
		if ( ! isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query->set( 'order', $order );
		}
	}
}
add_action( 'pre_get_posts', 'rpress_reservation_list_query' );

/**
 * Edit screen: details box (replaces title/editor).
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_meta_boxes() {
	add_meta_box( 'rpress-reservation-details', __( 'Reservation details', 'restropress' ), 'rpress_reservation_details_box', 'rpress_reservation', 'normal', 'high' );
}
add_action( 'add_meta_boxes_rpress_reservation', 'rpress_reservation_meta_boxes' );

/**
 * Render the details box.
 *
 * @since 3.5
 * @param WP_Post $post Post.
 * @return void
 */
function rpress_reservation_details_box( $post ) {
	$res = rpress_get_reservation( $post->ID );
	$ts  = $res && $res['ts'] ? $res['ts'] : 0;
	$tz  = rpress_get_wp_timezone();

	wp_nonce_field( 'rpress_res_save_' . $post->ID, 'rpress_res_meta_nonce' );
	$fields = array(
		'date'  => array( __( 'Date', 'restropress' ), 'date', $ts ? wp_date( 'Y-m-d', $ts, $tz ) : rpress_get_wp_now()->format( 'Y-m-d' ) ),
		'time'  => array( __( 'Time', 'restropress' ), 'time', $ts ? wp_date( 'H:i', $ts, $tz ) : '19:00' ),
		'party' => array( __( 'Guests', 'restropress' ), 'number', $res ? $res['party'] : 2 ),
		'name'  => array( __( 'Name', 'restropress' ), 'text', $res ? $res['name'] : '' ),
		'phone' => array( __( 'Phone', 'restropress' ), 'tel', $res ? $res['phone'] : '' ),
		'email' => array( __( 'Email', 'restropress' ), 'email', $res ? $res['email'] : '' ),
	);
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $fields as $key => $field ) {
		echo '<tr><th scope="row"><label for="rpress-res-admin-' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label></th><td>';
		echo '<input type="' . esc_attr( $field[1] ) . '" id="rpress-res-admin-' . esc_attr( $key ) . '" name="rpress_res[' . esc_attr( $key ) . ']" value="' . esc_attr( $field[2] ) . '" class="' . ( in_array( $field[1], array( 'text', 'email', 'tel' ), true ) ? 'regular-text' : ( 'number' === $field[1] ? 'small-text' : 'rpress-res-when-input' ) ) . '"' . ( 'number' === $field[1] ? ' min="1" step="1"' : '' ) . ( in_array( $key, array( 'date', 'time', 'party', 'name' ), true ) ? ' required' : '' ) . ' />';
		echo '</td></tr>';
	}
	echo '<tr><th scope="row"><label for="rpress-res-admin-note">' . esc_html__( 'Note', 'restropress' ) . '</label></th><td><textarea id="rpress-res-admin-note" name="rpress_res[note]" rows="3" class="large-text">' . esc_textarea( $res ? $res['note'] : '' ) . '</textarea></td></tr>';
	echo '<tr><th scope="row"><label for="rpress-res-admin-status">' . esc_html__( 'Status', 'restropress' ) . '</label></th><td><select id="rpress-res-admin-status" name="rpress_res[status]">';
	$current = $res ? $res['status'] : 'confirmed';
	foreach ( rpress_reservation_statuses() as $key => $label ) {
		echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Notify', 'restropress' ) . '</th><td><label><input type="checkbox" name="rpress_res[notify]" value="1" checked /> ' . esc_html__( 'Email the customer when the status changes to confirmed or declined', 'restropress' ) . '</label></td></tr>';
	/**
	 * Extra rows in the reservation details box (table, occasion, deposit...).
	 * Print complete <tr> rows; save them on rpress_reservation_admin_save.
	 *
	 * @since 3.5
	 * @param int         $post_id Reservation ID.
	 * @param array|false $res     Reservation, false for a new one.
	 */
	do_action( 'rpress_reservation_admin_details', $post->ID, $res );
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Reservations entered here are not limited by slot capacity or opening hours.', 'restropress' ) . '</p>';
}

/**
 * Use the guest name as the post title.
 *
 * @since 3.5
 * @param array $data    Post data.
 * @param array $postarr Raw post array.
 * @return array
 */
function rpress_reservation_insert_post_data( $data, $postarr ) {
	if ( 'rpress_reservation' !== $data['post_type'] || empty( $postarr['ID'] ) || empty( $_POST['rpress_res_meta_nonce'] ) ) {
		return $data;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rpress_res_meta_nonce'] ) ), 'rpress_res_save_' . (int) $postarr['ID'] ) ) {
		return $data;
	}
	$raw = isset( $_POST['rpress_res'] ) && is_array( $_POST['rpress_res'] ) ? wp_unslash( $_POST['rpress_res'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
	if ( isset( $raw['name'] ) ) {
		$data['post_title'] = sanitize_text_field( $raw['name'] );
	}
	if ( 'auto-draft' === $data['post_status'] || 'draft' === $data['post_status'] ) {
		$data['post_status'] = 'publish';
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'rpress_reservation_insert_post_data', 10, 2 );

/**
 * Save the details box.
 *
 * @since 3.5
 * @param int $post_id Post ID.
 * @return void
 */
function rpress_reservation_save_details( $post_id ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( empty( $_POST['rpress_res_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rpress_res_meta_nonce'] ) ), 'rpress_res_save_' . $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$raw = isset( $_POST['rpress_res'] ) && is_array( $_POST['rpress_res'] ) ? wp_unslash( $_POST['rpress_res'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.

	$date = isset( $raw['date'] ) ? sanitize_text_field( $raw['date'] ) : '';
	$time = isset( $raw['time'] ) ? sanitize_text_field( $raw['time'] ) : '';
	if ( ! rpress_reservation_is_valid_date( $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
		return;
	}
	$ts = rpress_get_wp_timestamp( $date . ' ' . $time );
	if ( false === $ts ) {
		return;
	}

	$is_new  = '' === (string) get_post_meta( $post_id, '_rpress_res_status', true );
	$old_ts  = (int) get_post_meta( $post_id, '_rpress_res_ts', true );
	$status  = isset( $raw['status'] ) ? sanitize_key( $raw['status'] ) : 'confirmed';
	$status  = isset( rpress_reservation_statuses()[ $status ] ) ? $status : 'confirmed';
	$notify  = ! empty( $raw['notify'] );

	/**
	 * Save extension fields from the details box. Runs after the nonce and
	 * capability checks, before status changes and notifications.
	 *
	 * @since 3.5
	 * @param int   $post_id Reservation ID.
	 * @param array $raw     Unslashed rpress_res[] values.
	 */
	do_action( 'rpress_reservation_admin_save', $post_id, $raw );

	update_post_meta( $post_id, '_rpress_res_ts', $ts );
	update_post_meta( $post_id, '_rpress_res_date', wp_date( 'Y-m-d', $ts, rpress_get_wp_timezone() ) );
	update_post_meta( $post_id, '_rpress_res_party', max( 1, isset( $raw['party'] ) ? absint( $raw['party'] ) : 1 ) );
	update_post_meta( $post_id, '_rpress_res_name', isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '' );
	update_post_meta( $post_id, '_rpress_res_phone', isset( $raw['phone'] ) ? sanitize_text_field( $raw['phone'] ) : '' );
	update_post_meta( $post_id, '_rpress_res_email', isset( $raw['email'] ) ? sanitize_email( $raw['email'] ) : '' );
	update_post_meta( $post_id, '_rpress_res_note', isset( $raw['note'] ) ? sanitize_textarea_field( $raw['note'] ) : '' );

	if ( $is_new ) {
		update_post_meta( $post_id, '_rpress_res_status', $status );
		update_post_meta( $post_id, '_rpress_res_source', 'admin' );
		do_action( 'rpress_reservation_created', $post_id, $status, array( 'source' => 'admin', 'notify' => $notify ) );
		return;
	}

	if ( $old_ts !== $ts ) {
		delete_post_meta( $post_id, '_rpress_res_reminded' );
		rpress_reservation_schedule_reminder( $post_id );
	}
	rpress_set_reservation_status( $post_id, $status, $notify );
}
add_action( 'save_post_rpress_reservation', 'rpress_reservation_save_details' );

/**
 * Small list/edit styles (status badges, quick status links).
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_admin_styles() {
	$screen = get_current_screen();
	if ( ! $screen || 'rpress_reservation' !== $screen->post_type ) {
		return;
	}
	?>
	<style>
		.rpress-res-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; line-height: 18px; background: #f0f0f1; color: #3c434a; }
		.rpress-res-badge-pending { background: #fcf0e3; color: #8a4b00; }
		.rpress-res-badge-confirmed { background: #e6f4ea; color: #1e6b34; }
		.rpress-res-badge-declined, .rpress-res-badge-cancelled { background: #fbeaea; color: #8a1f11; }
		.rpress-res-badge-completed { background: #e8f0fe; color: #1d4f91; }
		.rpress-res-note { margin-top: 4px; color: #646970; font-size: 12px; }
		.rpress-res-booked { margin-top: 2px; color: #646970; font-size: 12px; }
		.column-rpress_res_when { width: 14%; }
		.column-rpress_res_party { width: 7%; }
		.column-rpress_res_status { width: 16%; }
		.row-actions .rpress-res-action-confirmed { color: #1e6b34; }
		.row-actions .rpress-res-action-declined { color: #b32d2e; }
		#rpress-res-date-filter { vertical-align: middle; }
		/* Date and time inputs: wide enough for the full value and picker icon. */
		.rpress-res-when-input { width: auto; min-width: 11em; }
	</style>
	<?php
}
add_action( 'admin_head', 'rpress_reservation_admin_styles' );

/**
 * Hide the Publish box clutter on the edit screen (no visibility/schedule).
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_edit_screen_tweaks() {
	$screen = get_current_screen();
	if ( ! $screen || 'rpress_reservation' !== $screen->id ) {
		return;
	}
	echo '<style>#misc-publishing-actions, #minor-publishing-actions { display: none; }</style>';
}
add_action( 'admin_head', 'rpress_reservation_edit_screen_tweaks' );
