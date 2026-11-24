<?php
/**
 * Payment claims: who may write to a pending payment right now.
 *
 * Checkout can "resume" a pending payment kept in the buyer's session
 * (`rpress_resume_payment`): rpress_insert_payment() then rewrites its cart,
 * total, gateway and key. When a buyer has a card payment in flight on that
 * order and posts checkout again (for example with cash on delivery), the
 * second request used to rewrite the order being charged.
 *
 * A claim is an exclusive, expiring lock on one payment, stored as a row in
 * the options table. Taking it is a single INSERT IGNORE against the unique
 * option_name index (the pattern WP_Upgrader::create_lock() uses), so two
 * requests can never both hold it.
 *
 * How gateways use it:
 *
 *   // Before contacting the processor for payment 123:
 *   if ( ! rpress_claim_payment( 123, 'my_gateway', 15 * MINUTE_IN_SECONDS ) ) {
 *       // Another request is changing this order. Refuse or retry.
 *   }
 *   // ... charge ...
 *   rpress_release_payment_claim( 123, 'my_gateway' ); // or keep it until the payment is final
 *
 * While another owner holds a claim, core never resumes that payment: the
 * checkout creates a new payment instead. Core takes its own short claim
 * (owner `rpress_core_resume-<random>`, one per request) for the duration of
 * a resume, so a gateway, or a second checkout request, that asks for a claim
 * while core is rewriting the order is refused.
 *
 * Gateways that prefer not to lock can veto a resume with the
 * `rpress_can_resume_payment` filter, and can make a payment non-resumable
 * everywhere with `rpress_payment_is_recoverable`.
 *
 * @package RestroPress
 * @since   3.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prefix of the claim owner core uses while it resumes and rewrites a payment.
 */
const RPRESS_RESUME_CLAIM_OWNER = 'rpress_core_resume';

/**
 * Option name holding the claim for one payment.
 *
 * @since 3.5.1
 * @param int $payment_id Payment ID.
 * @return string
 */
function rpress_payment_claim_key( $payment_id ) {
	return '_rpress_payment_claim_' . absint( $payment_id );
}

/**
 * Read a claim row straight from the database (never the options cache).
 *
 * @since 3.5.1
 * @param int $payment_id Payment ID.
 * @return array|false { owner, expires, raw } or false when there is no row.
 */
function rpress_read_payment_claim_row( $payment_id ) {
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", rpress_payment_claim_key( $payment_id ) ) );
	if ( null === $raw ) {
		return false;
	}
	$parts = explode( '|', (string) $raw );
	return array(
		'owner'   => isset( $parts[0] ) ? $parts[0] : '',
		'expires' => isset( $parts[1] ) ? (int) $parts[1] : 0,
		'raw'     => (string) $raw,
	);
}

/**
 * Current live claim on a payment.
 *
 * @since 3.5.1
 * @param int $payment_id Payment ID.
 * @return array|false { owner: string, expires: int unix time } or false when unclaimed or expired.
 */
function rpress_get_payment_claim( $payment_id ) {
	$row = rpress_read_payment_claim_row( $payment_id );
	if ( ! $row || $row['expires'] <= time() ) {
		return false;
	}
	return array(
		'owner'   => $row['owner'],
		'expires' => $row['expires'],
	);
}

/**
 * Atomically claim a payment.
 *
 * Succeeds when nobody holds a live claim, or when $owner already holds it
 * (the expiry is then extended). An expired claim is removed with a
 * compare-and-delete on its exact value, so only one request can take it over.
 *
 * @since 3.5.1
 * @param int    $payment_id Payment ID.
 * @param string $owner      Who claims it, e.g. the gateway ID. No "|" characters.
 * @param int    $ttl        Seconds until the claim expires on its own. Default 5 minutes.
 * @return bool True when $owner holds the claim after the call.
 */
function rpress_claim_payment( $payment_id, $owner, $ttl = 300 ) {
	global $wpdb;
	$payment_id = absint( $payment_id );
	$owner      = str_replace( '|', '', (string) $owner );
	if ( ! $payment_id || '' === $owner ) {
		return false;
	}
	$key   = rpress_payment_claim_key( $payment_id );
	$value = $owner . '|' . ( time() + max( 1, (int) $ttl ) ) . '|' . wp_generate_password( 12, false );

	for ( $attempt = 0; $attempt < 2; $attempt++ ) {
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )", $key, $value ) );
		if ( 1 === (int) $inserted ) {
			return true;
		}
		$row = rpress_read_payment_claim_row( $payment_id );
		if ( ! $row ) {
			continue; // Released between our insert and read: try once more.
		}
		if ( $row['owner'] === $owner ) {
			// Re-entrant: extend our own claim, only if it is still the row we read.
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $key, $row['raw'] ) );
			return true;
		}
		if ( $row['expires'] > time() ) {
			return false; // Someone else holds a live claim.
		}
		// Expired: remove exactly that stale row, then retry the insert.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $row['raw'] ) );
	}
	return false;
}

/**
 * Release a claim held by $owner. Does nothing when another owner holds it.
 *
 * @since 3.5.1
 * @param int    $payment_id Payment ID.
 * @param string $owner      Owner passed to rpress_claim_payment().
 * @return bool True when a claim of $owner was removed.
 */
function rpress_release_payment_claim( $payment_id, $owner ) {
	global $wpdb;
	$owner = str_replace( '|', '', (string) $owner );
	$row   = rpress_read_payment_claim_row( $payment_id );
	if ( ! $row || $row['owner'] !== $owner ) {
		return false;
	}
	return 1 === (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", rpress_payment_claim_key( $payment_id ), $row['raw'] ) );
}

/**
 * Whether checkout may resume (and rewrite) a pending payment.
 *
 * True only when the payment is recoverable, no one other than core holds a
 * live claim on it, and no `rpress_can_resume_payment` callback refuses.
 *
 * @since 3.5.1
 * @param RPRESS_Payment $payment       Payment from the buyer's session.
 * @param array          $purchase_data Purchase data of the current checkout.
 * @param string         $context       'purchase' while process-purchase picks the purchase key,
 *                                      'insert' for the final, claimed decision in rpress_insert_payment().
 * @param string         $own_claim     Claim owner the caller holds itself (core's per-request
 *                                      resume claim). Any other live claim makes this false.
 * @return bool
 */
function rpress_is_payment_resumable( $payment, $purchase_data = array(), $context = 'insert', $own_claim = '' ) {
	if ( ! $payment instanceof RPRESS_Payment || empty( $payment->ID ) || ! $payment->is_recoverable() ) {
		return false;
	}
	$claim = rpress_get_payment_claim( $payment->ID );
	if ( $claim && ( '' === $own_claim || $claim['owner'] !== $own_claim ) ) {
		return false; // A gateway, or another checkout request, is working on this payment.
	}

	/**
	 * Filters whether checkout may resume a pending payment and rewrite its
	 * cart, total, gateway and key with the current checkout.
	 *
	 * Runs at most twice per checkout request: once with context 'purchase'
	 * (process-purchase, before the gateway runs, to choose the purchase key)
	 * and once with context 'insert' inside rpress_insert_payment(). With
	 * 'insert' it runs after core holds its claim on the payment and has
	 * re-read it from the database, before any write. That second call is
	 * the authoritative one.
	 *
	 * Return false to keep the payment untouched: checkout then creates a
	 * new payment with a new purchase key. Gateways with a charge in flight
	 * on the payment should return false (or hold a claim, see
	 * rpress_claim_payment()).
	 *
	 * @since 3.5.1
	 * @param bool   $can           Whether the payment may be resumed. Default true.
	 * @param int    $payment_id    Pending payment ID from the buyer's session.
	 * @param array  $purchase_data Purchase data of the current checkout.
	 * @param string $context       'purchase' or 'insert'.
	 */
	return (bool) apply_filters( 'rpress_can_resume_payment', true, (int) $payment->ID, (array) $purchase_data, $context );
}
