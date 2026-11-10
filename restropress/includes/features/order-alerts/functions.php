<?php
/**
 * Print status API for orders.
 *
 * Any printing integration (the Live Orders auto-print, a POS, a cloud
 * printer extension) can record the outcome with rpress_set_order_print_status()
 * so the Live Orders card shows it.
 *
 * Meta `_rpress_print_status`: array( status, time, message, source ).
 * Status is one of: printed, failed, not_attempted.
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'rpress_get_order_print_status' ) ) {
	/**
	 * Get the recorded print status of an order.
	 *
	 * @since 3.5
	 * @param int $order_id Order ID.
	 * @return array { status: string, time: int, message: string, source: string }
	 */
	function rpress_get_order_print_status( $order_id ) {
		$raw = get_post_meta( absint( $order_id ), '_rpress_print_status', true );
		$raw = is_array( $raw ) ? $raw : array();

		$status = isset( $raw['status'] ) ? sanitize_key( $raw['status'] ) : '';
		if ( ! in_array( $status, array( 'printed', 'failed', 'not_attempted' ), true ) ) {
			$status = 'not_attempted';
		}

		return array(
			'status'  => $status,
			'time'    => isset( $raw['time'] ) ? absint( $raw['time'] ) : 0,
			'message' => isset( $raw['message'] ) ? (string) $raw['message'] : '',
			'source'  => isset( $raw['source'] ) ? (string) $raw['source'] : '',
		);
	}
}

if ( ! function_exists( 'rpress_set_order_print_status' ) ) {
	/**
	 * Record the print status of an order.
	 *
	 * @since 3.5
	 * @param int    $order_id Order ID.
	 * @param string $status   printed, failed or not_attempted.
	 * @param string $message  Optional detail (e.g. the failure reason).
	 * @param string $source   Optional source label (board, pos, cloud...).
	 * @return bool False for an unknown order or status.
	 */
	function rpress_set_order_print_status( $order_id, $status, $message = '', $source = '' ) {
		$order_id = absint( $order_id );
		$status   = sanitize_key( $status );
		if ( ! $order_id || 'rpress_payment' !== get_post_type( $order_id ) ) {
			return false;
		}
		if ( ! in_array( $status, array( 'printed', 'failed', 'not_attempted' ), true ) ) {
			return false;
		}

		if ( 'not_attempted' === $status ) {
			delete_post_meta( $order_id, '_rpress_print_status' );
			delete_post_meta( $order_id, '_rpress_print_claim' );
		} else {
			$time = class_exists( 'RPRESS_Order_Alerts' ) ? RPRESS_Order_Alerts::now() : time();
			update_post_meta(
				$order_id,
				'_rpress_print_status',
				array(
					'status'  => $status,
					'time'    => $time,
					'message' => sanitize_text_field( $message ),
					'source'  => sanitize_key( $source ),
				)
			);
		}

		/**
		 * Fires after an order's print status was recorded.
		 *
		 * @since 3.5
		 * @param int    $order_id Order ID.
		 * @param string $status   New status.
		 * @param string $message  Detail message.
		 * @param string $source   Source label.
		 */
		do_action( 'rpress_order_print_status_updated', $order_id, $status, $message, $source );
		return true;
	}
}

if ( ! function_exists( 'rpress_claim_order_auto_print' ) ) {
	/**
	 * Claim the automatic print of an order. Returns true only for the first
	 * caller, so two open Live Orders screens never both auto-print an order.
	 * Manual prints and retries do not need a claim.
	 *
	 * @since 3.5
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	function rpress_claim_order_auto_print( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id || 'rpress_payment' !== get_post_type( $order_id ) ) {
			return false;
		}
		$current = rpress_get_order_print_status( $order_id );
		if ( 'not_attempted' !== $current['status'] ) {
			return false;
		}
		// add_post_meta() with $unique is a single INSERT guarded by a lookup,
		// close enough to atomic for two tablets polling a few seconds apart.
		$now = class_exists( 'RPRESS_Order_Alerts' ) ? RPRESS_Order_Alerts::now() : time();
		return (bool) add_post_meta( $order_id, '_rpress_print_claim', $now, true );
	}
}
