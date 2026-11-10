<?php
/**
 * Feature: checkout UX (3.5, item 2 "Unified mobile-first checkout").
 *
 * Enhancement layer over the existing redesigned checkout. It does not
 * replace any template or AJAX flow; it adds:
 *
 * - a live, service-aware minimum-order hint ("Add ₹40.00 more for pickup")
 *   at checkout, in the sidebar cart and in the mobile cart bar, and keeps
 *   Place order disabled while the cart is below the minimum (the server-side
 *   check in rpress_pre_validate_order() stays the source of truth);
 * - delivery/pickup switch clarity: typed address and instruction fields
 *   survive the order-details re-render, and the total change is announced;
 * - inline, accessible field validation on blur and on submit;
 * - mobile polish for the sticky Place order bar (390px target).
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/hooks.php';
