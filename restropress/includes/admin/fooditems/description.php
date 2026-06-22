<?php
/**
 * Food item description fields - surfaces the WP excerpt and content in our
 * own labelled section, and suppresses the duplicate default UI so admins
 * only see one place to edit each field.
 *
 * The fields themselves remain standard WP `post_excerpt` and `post_content`
 * - no migration needed and existing data renders unchanged. We just stop
 * the default editor + excerpt metabox from rendering.
 *
 * @package RPRESS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Drop the default post editor and excerpt support from the fooditem CPT.
 * Our section renders both with `wp_editor()` and a textarea using the
 * standard `content` / `excerpt` field names, so WordPress still saves
 * them through its normal post-save path.
 */
function rpress_fooditem_remove_default_editor_support() {
	remove_post_type_support( 'fooditem', 'editor' );
	remove_post_type_support( 'fooditem', 'excerpt' );
}
add_action( 'init', 'rpress_fooditem_remove_default_editor_support', 99 );

/**
 * Belt-and-braces: explicitly remove the legacy excerpt metabox in case any
 * other code re-adds excerpt support after init.
 */
function rpress_fooditem_remove_default_excerpt_metabox() {
	remove_meta_box( 'postexcerpt', 'fooditem', 'normal' );
}
add_action( 'add_meta_boxes', 'rpress_fooditem_remove_default_excerpt_metabox', 100 );
