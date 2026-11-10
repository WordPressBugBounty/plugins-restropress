<?php
/**
 * Online payments done well (3.5): Stripe Apple Pay / Google Pay, hardened
 * Stripe webhooks and one paid/unpaid label shared by every screen.
 *
 * Builds on the core Stripe gateway (includes/gateways/stripe.php): same keys,
 * same API helper, same order records. Nothing here loads when the separate
 * RestroPress Stripe extension is active (core Stripe steps aside then).
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/payment-state.php';
require_once __DIR__ . '/stripe-webhooks.php';
require_once __DIR__ . '/stripe-wallets.php';
require_once __DIR__ . '/settings.php';
