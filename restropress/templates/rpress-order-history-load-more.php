<?php
/**
 * "Load more" on the order lists: the next page of order cards.
 * Called by RP_AJAX::more_order_history(), which reads $html and $found_post.
 *
 * @package RestroPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification -- the AJAX handler checks the nonce.
$rpress_paged   = isset( $_REQUEST['paged'] ) ? max( 1, absint( wp_unslash( $_REQUEST['paged'] ) ) ) : 1;
$rpress_context = ( isset( $_REQUEST['context'] ) && 'account' === sanitize_key( wp_unslash( $_REQUEST['context'] ) ) ) ? 'account' : 'history';
// phpcs:enable

$rpress_page = rpress_account_order_cards( $rpress_paged, $rpress_context );
$found_post  = $rpress_page['count'];
$has_more    = $rpress_page['has_more'];
