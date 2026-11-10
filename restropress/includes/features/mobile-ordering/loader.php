<?php
/**
 * Feature: mobile ordering (3.5).
 *
 * Brings the menu page in line with how people order on phones today. It is
 * an enhancement layer over the existing [fooditems] menu: no template is
 * replaced and the add-to-cart, cart and checkout flows stay the same.
 *
 * - Store card above the menu: open or closed for the selected service, when
 *   it opens next, ready time and minimum order. A closed store says so
 *   before anyone fills a cart, with a way to schedule for later.
 * - Phones: the service toggle and time sit on one compact row, search moves
 *   into the sticky category bar, and the first dish shows much higher up.
 * - Cart: one full-width "View cart" bar with a minimum-order progress strip,
 *   the cart opens as a bottom sheet, and a dish already in the cart shows a
 *   - n + stepper on its card.
 * - Item popup: a bottom sheet on phones (swipe down to close), the title
 *   stays pinned while scrolling, option groups say Required or Optional, and
 *   the whole dish card opens it.
 *
 * Filter rpress_mobile_ordering_enabled to turn it off.
 *
 * @package RestroPress
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/functions.php';
