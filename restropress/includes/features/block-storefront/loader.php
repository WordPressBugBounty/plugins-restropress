<?php
/**
 * Block storefront (3.5): composable menu blocks on the Interactivity API.
 *
 * Five dynamic blocks (category nav, item grid, cart panel, service bar,
 * menu search) share one Interactivity store (namespace "restropress"), a
 * "RestroPress Storefront" pattern combines them, and block themes get a
 * selectable "RestroPress Storefront" page template. No build step: block
 * metadata lives in blocks/<name>/block.json, markup in render.php, the
 * frontend logic in a plain script module that imports
 * @wordpress/interactivity from core, and the editor script uses wp.*
 * globals.
 *
 * The classic [fooditems] storefront and template packs are untouched; the
 * blocks are opt-in. The item popup, add-to-cart, service selection and
 * cart AJAX are the existing ones.
 *
 * @package     RPRESS
 * @subpackage  Features/BlockStorefront
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/functions.php';

add_action( 'init', 'rpress_block_storefront_register', 20 );
/**
 * Register assets, blocks, the pattern and the block template.
 *
 * @since 3.5
 * @return void
 */
function rpress_block_storefront_register() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	$base_dir = RP_PLUGIN_DIR . 'assets/features/block-storefront/';
	$base_url = RP_PLUGIN_URL . 'assets/features/block-storefront/';
	$version  = function ( $file ) use ( $base_dir ) {
		return file_exists( $base_dir . $file ) ? RP_VERSION . '.' . filemtime( $base_dir . $file ) : RP_VERSION;
	};

	wp_register_style( 'rpress-storefront-blocks', $base_url . 'style.css', array(), $version( 'style.css' ) );
	wp_register_script(
		'rpress-storefront-blocks-editor',
		$base_url . 'editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n', 'wp-data', 'wp-core-data' ),
		$version( 'editor.js' ),
		true
	);
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'rpress-storefront-blocks-editor', 'restropress', RP_PLUGIN_DIR . 'languages' );
	}
	if ( function_exists( 'wp_register_script_module' ) ) {
		wp_register_script_module(
			'@restropress/storefront',
			$base_url . 'view.js',
			array( '@wordpress/interactivity' ),
			$version( 'view.js' )
		);
	}

	foreach ( array_keys( rpress_block_storefront_blocks() ) as $folder ) {
		register_block_type( RP_PLUGIN_DIR . 'blocks/' . $folder );
	}

	rpress_block_storefront_register_pattern();
	rpress_block_storefront_register_template();
}

/**
 * Pattern markup: service bar and category nav on top, search and items in
 * the main column, the cart panel in a side column (stacks below on mobile).
 *
 * @since 3.5
 * @return string
 */
function rpress_block_storefront_pattern_content() {
	return '<!-- wp:group {"align":"wide","className":"rpress-storefront","layout":{"type":"constrained","wideSize":"1200px"}} -->
<div class="wp-block-group alignwide rpress-storefront"><!-- wp:rpress/service-bar /-->

<!-- wp:rpress/category-nav /-->

<!-- wp:columns {"className":"rpress-storefront-columns"} -->
<div class="wp-block-columns rpress-storefront-columns"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:rpress/menu-search /-->

<!-- wp:rpress/item-grid /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%"} -->
<div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:rpress/cart-panel /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->';
}

/**
 * Register the "RestroPress Storefront" pattern and its category.
 *
 * @since 3.5
 * @return void
 */
function rpress_block_storefront_register_pattern() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'restropress', array( 'label' => __( 'RestroPress', 'restropress' ) ) );
	}
	register_block_pattern(
		'restropress/storefront',
		array(
			'title'       => __( 'RestroPress Storefront', 'restropress' ),
			'description' => __( 'Online ordering page: delivery or pickup bar, sticky categories, menu search, menu items and a cart panel.', 'restropress' ),
			'categories'  => array( 'restropress' ),
			'keywords'    => array( 'menu', 'restaurant', 'order', 'storefront', 'cart' ),
			'blockTypes'  => array( 'core/post-content' ),
			'content'     => rpress_block_storefront_pattern_content(),
		)
	);
}

/**
 * Block themes: a selectable page template with the storefront pattern.
 *
 * Opt-in: it is offered in the page Template picker (assign it to the menu
 * page) and never replaces any template on its own. Needs WordPress 6.7+
 * (register_block_template()).
 *
 * @since 3.5
 * @return void
 */
function rpress_block_storefront_register_template() {
	if ( ! function_exists( 'register_block_template' ) || ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
		return;
	}
	if ( class_exists( 'WP_Block_Templates_Registry' ) && WP_Block_Templates_Registry::get_instance()->is_registered( 'restropress//rpress-storefront' ) ) {
		return;
	}
	register_block_template(
		'restropress//rpress-storefront',
		array(
			'title'       => __( 'RestroPress Storefront', 'restropress' ),
			'description' => __( 'Menu page with the RestroPress storefront blocks: service bar, categories, search, items and cart.', 'restropress' ),
			'post_types'  => array( 'page' ),
			'content'     => '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->
<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull"><!-- wp:post-title {"align":"wide"} /-->
<!-- wp:pattern {"slug":"restropress/storefront"} /--></main>
<!-- /wp:group -->
<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
		)
	);
}

/**
 * Pages with the storefront blocks are RestroPress pages: they get the
 * storefront styles, the item popup assets and the menu tokens.
 */
add_filter( 'is_a_restropress_page', function ( $is_page ) {
	if ( $is_page ) {
		return $is_page;
	}
	$post = get_post();
	if ( ! $post ) {
		$queried = get_queried_object_id();
		$post    = $queried ? get_post( $queried ) : null;
	}
	return $post ? rpress_block_storefront_in_post( $post ) : $is_page;
} );

/**
 * Read-only cart state for the blocks, refreshed after every cart change.
 */
add_action( 'wp_ajax_rpress_storefront_cart', 'rpress_block_storefront_ajax_cart' );
add_action( 'wp_ajax_nopriv_rpress_storefront_cart', 'rpress_block_storefront_ajax_cart' );
