<?php
/**
 * Feature: storefront layout components (3.5.1).
 *
 * Renders the storefront layout that rpress_get_storefront_layout() resolves
 * (core defaults, then the pack and its parents, then the merchant's saved
 * choice). Each key switches one shared component, built once here and
 * skinned by every pack through the --rpc-* tokens:
 *
 * - masthead:   cover band, compact strip or centred editorial header.
 * - nav_style:  topbar (underline tabs above the menu) or the sidebar.
 * - nav_media:  photo tiles in the top bar.
 * - columns, card_style, show_image: dish card grid and card shape.
 * - rails:      Popular, Order again and Deals rows above the menu.
 * - cart_style: right-hand cart drawer on desktop (phones keep the sheet).
 * - item_style: item popup as a right side sheet on desktop.
 * - sections:   order of the rails, category nav and service row.
 *
 * Packs describe how the components look with `components` settings
 * (components.php); core prints that CSS so packs never fight its selectors.
 *
 * Defaults reproduce today's storefront, so nothing changes until a pack or
 * the merchant asks for a different layout. Phones keep the 3.5 mobile
 * ordering layer (cart bar, bottom sheets) whatever the desktop layout is.
 *
 * @package RestroPress
 * @since   3.5.1
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/components.php';
