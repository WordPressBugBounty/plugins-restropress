<?php
/**
 * Feature: checkout flow and order details (3.5.1, Pro packs build plan phase 2).
 *
 * Structure changes every pack shares:
 *
 * - Checkout order: delivery or pickup details first, then contact, then
 *   payment. The time and a filled-in address collapse to summary cards with
 *   a Change button.
 * - One total: the grey "Order Total" bar is hidden and the order summary
 *   carries the Place order button on desktop (phones keep the sticky bar).
 * - A tip slot in the payment step that the Tips extension fills.
 * - Order details: one heading, "Arrives around 7:55 pm" and, for guests, a
 *   note that the tracking link was emailed. (Reorder only once an order is
 *   done already ships with the 3.5.1 account order cards.)
 *
 * @package RestroPress
 * @since   3.5.1
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/functions.php';
