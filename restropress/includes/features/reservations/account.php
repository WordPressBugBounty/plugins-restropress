<?php
/**
 * Reservations: the customer's account dashboard and email links.
 *
 * @package     RPRESS
 * @subpackage  Features/Reservations
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * A user's reservations: booked while logged in, or with their account email.
 *
 * @since 3.5
 * @param int $user_id User ID.
 * @return array Reservations from rpress_get_reservation(), newest visit first.
 */
function rpress_reservation_get_user_reservations( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return array();
	}
	$query = new WP_Query(
		array(
			'post_type'      => 'rpress_reservation',
			'post_status'    => 'publish',
			'posts_per_page' => (int) apply_filters( 'rpress_reservation_account_limit', 50 ),
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_rpress_res_ts', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => '_rpress_res_user', 'value' => (int) $user->ID ),
				array( 'key' => '_rpress_res_email', 'value' => $user->user_email ),
			),
		)
	);
	return array_values( array_filter( array_map( 'rpress_get_reservation', $query->posts ) ) );
}

/**
 * URL of the Reservations section in the customer dashboard, or '' when no
 * published page has the [customer_dashboard] shortcode. (The order history
 * page is a different view without the dashboard tabs.)
 *
 * @since 3.5
 * @return string
 */
function rpress_reservation_account_url() {
	$url = function_exists( 'rpress_account_url' ) ? rpress_account_url( 'reservations' ) : '';
	return (string) apply_filters( 'rpress_reservation_account_url', $url );
}

/**
 * 3.5 emails linked to /my-account/?rpress_account=reservations#user-my-reservations.
 * Send those to the Reservations section URL.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_reservation_redirect_legacy_account_link() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
	if ( empty( $_GET['rpress_account'] ) || 'reservations' !== $_GET['rpress_account'] || ! is_page() ) {
		return;
	}
	$post = get_post();
	if ( ! $post || ! has_shortcode( $post->post_content, 'customer_dashboard' ) ) {
		return;
	}
	$url = rpress_account_url( 'reservations', $post->ID );
	if ( $url ) {
		wp_safe_redirect( $url );
		exit;
	}
}
add_action( 'template_redirect', 'rpress_reservation_redirect_legacy_account_link' );

/**
 * URL of the booking page (see rpress_reservation_booking_page_id()), or ''.
 *
 * @since 3.5
 * @return string
 */
function rpress_reservation_booking_page_url() {
	$page_id = rpress_reservation_booking_page_id();
	$url     = $page_id ? get_permalink( $page_id ) : '';
	return (string) apply_filters( 'rpress_reservation_booking_page_url', $url ? $url : '' );
}

/**
 * Does the current customer have any reservations? (Cached per request.)
 *
 * @since 3.5.1
 * @return bool
 */
function rpress_reservation_account_has_bookings() {
	static $has = null;
	if ( null === $has ) {
		$has = (bool) rpress_reservation_get_user_reservations( get_current_user_id() );
	}
	return $has;
}

/**
 * Reservations section of the customer account, shown to customers who
 * have bookings, at /my-account/reservations/.
 *
 * @since 3.5.1
 * @param array $sections Account sections.
 * @return array
 */
function rpress_reservation_account_section( $sections ) {
	$sections['reservations'] = array(
		'label'     => __( 'Reservations', 'restropress' ),
		// Inline so it needs no new image; sized and tinted like the other sidebar icons (img rules).
		'icon'      => 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>' ),
		'endpoint'  => 'reservations',
		'callback'  => 'rpress_reservation_account_panel',
		'visible'   => 'rpress_reservation_account_has_bookings',
		'legacy_id' => 'user-my-reservations',
	);
	return $sections;
}
add_filter( 'rpress_account_sections', 'rpress_reservation_account_section' );

/**
 * Reservations panel: upcoming first (soonest on top), then past.
 *
 * @since 3.5
 * @return void
 */
function rpress_reservation_account_panel() {
	$all      = rpress_reservation_get_user_reservations( get_current_user_id() );
	$book_url = rpress_reservation_booking_page_url();
	if ( ! $all && ! $book_url ) {
		return;
	}
	if ( ! $all ) {
		?>
			<div class="box-bg pd-2">
				<div class="light-gray-bg">
					<div class="box-body rpress-account-reservations rpress-account-res-empty">
						<h4 class="box-title"><?php esc_html_e( 'Reservations', 'restropress' ); ?></h4>
						<p><?php esc_html_e( 'You have no reservations yet.', 'restropress' ); ?></p>
						<a class="rpress-account-res-book" href="<?php echo esc_url( $book_url ); ?>"><?php esc_html_e( 'Book a table', 'restropress' ); ?></a>
					</div>
				</div>
			</div>
		<?php
		return;
	}
	$now      = time();
	$upcoming = array_reverse( array_filter( $all, function ( $res ) use ( $now ) { return $res['ts'] >= $now; } ) );
	$past     = array_filter( $all, function ( $res ) use ( $now ) { return $res['ts'] < $now; } );
	$labels   = rpress_reservation_statuses();
	// Reuse the order status dots: pending looks like processing, confirmed like accepted.
	$dots     = array(
		'pending'   => 'processing',
		'confirmed' => 'accepted',
		'completed' => 'completed',
		'declined'  => 'cancelled',
		'cancelled' => 'cancelled',
	);
	$groups   = array(
		array( __( 'Upcoming', 'restropress' ), $upcoming, __( 'No upcoming reservations.', 'restropress' ) ),
		array( __( 'Past', 'restropress' ), $past, '' ),
	);
	?>
		<div class="box-bg pd-2">
			<div class="light-gray-bg">
				<div class="box-body">
					<?php foreach ( $groups as $group ) : ?>
						<?php
						list( $title, $rows, $empty ) = $group;
						if ( ! $rows && ! $empty ) {
							continue;
						}
						?>
						<div class="table-responsive rpress-account-reservations">
							<div class="flex justify-content-space-between align-items-center pd-4">
								<h4 class="box-title"><?php echo esc_html( $title ); ?></h4>
							</div>
							<?php if ( ! $rows ) : ?>
								<p class="pd-4"><?php echo esc_html( $empty ); ?></p>
							<?php else : ?>
								<table class="table rpress-account-res-table">
									<thead>
										<tr>
											<th class="text-align-left"><?php esc_html_e( 'DATE', 'restropress' ); ?></th>
											<th class="text-align-left"><?php esc_html_e( 'TIME', 'restropress' ); ?></th>
											<th class="text-align-left"><?php esc_html_e( 'GUESTS', 'restropress' ); ?></th>
											<th class="text-align-left"><?php esc_html_e( 'STATUS', 'restropress' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $rows as $res ) : ?>
											<?php $status = isset( $labels[ $res['status'] ] ) ? $res['status'] : 'pending'; ?>
											<tr>
												<td>
													<?php echo esc_html( rpress_reservation_format_date( $res['ts'] ) ); ?>
													<?php if ( '' !== $res['note'] ) : ?>
														<div class="rpress-account-res-note"><?php echo esc_html( $res['note'] ); ?></div>
													<?php endif; ?>
												</td>
												<td><?php echo esc_html( rpress_reservation_format_time( $res['ts'] ) ); ?></td>
												<td>
													<span class="rpress-account-res-count"><?php echo esc_html( number_format_i18n( $res['party'] ) ); ?></span>
													<?php /* translators: %s: number of guests */ ?>
													<span class="rpress-account-res-guests"><?php echo esc_html( sprintf( _n( '%s guest', '%s guests', $res['party'], 'restropress' ), number_format_i18n( $res['party'] ) ) ); ?></span>
												</td>
												<td>
													<i class="fa-solid fa-circle order-status order-status-<?php echo esc_attr( $dots[ $status ] ?? 'processing' ); ?>"></i>
													<?php echo esc_html( $labels[ $status ] ); ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php
}

/**
 * Footer link in reservation emails. The shared footer links to the order
 * history ("View Details"), which is wrong for a reservation: the admin gets
 * the reservation in wp-admin, the customer their account's Reservations.
 *
 * @since 3.5
 * @param int    $id   Reservation ID.
 * @param string $type Email type.
 * @return callable The filter, so the caller can remove it after sending.
 */
function rpress_reservation_email_footer_filter( $id, $type ) {
	$filter = function () use ( $id, $type ) {
		if ( 'admin' === $type ) {
			$url   = admin_url( 'post.php?post=' . (int) $id . '&action=edit' );
			$label = __( 'View reservation', 'restropress' );
		} else {
			$res   = rpress_get_reservation( $id );
			$url   = ( $res && email_exists( $res['email'] ) ) ? rpress_reservation_account_url() : '';
			$label = __( 'View your reservations', 'restropress' );
		}
		return $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : '';
	};
	add_filter( 'rpress_email_footer_text', $filter, 99 );
	// The footer only prints its link while someone is logged in, which
	// missed reminders (WP-Cron) and requests made while logged out.
	add_filter( 'rpress_email_show_footer_link', '__return_true', 99 );
	return $filter;
}
