<?php
/**
 * Food item taxonomy metaboxes - suppress the default category and tags
 * metaboxes so admins only see our combined "Category & Tags" section.
 *
 * Saving still works because WP processes `food_categories[]` (via our
 * own save_meta_boxes handler) and `tax_input[fooditem_tag]` (via WP's
 * native post-save path) regardless of whether the default UI renders.
 *
 * @package RPRESS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remove WordPress's default taxonomy metaboxes on the fooditem edit screen.
 *
 * The food-category metabox ID is `food-categorydiv` (hierarchical taxonomies
 * use `{taxonomy}div`); non-hierarchical taxonomies use `tagsdiv-{taxonomy}`,
 * so tags is `tagsdiv-fooditem_tag` and dietary is `tagsdiv-dietary`. All three
 * are rendered together in our custom "Category, Tags & Dietary" section.
 */
function rpress_fooditem_remove_default_taxonomy_metaboxes() {
	remove_meta_box( 'food-categorydiv', 'fooditem', 'side' );
	remove_meta_box( 'food-categorydiv', 'fooditem', 'normal' );
	remove_meta_box( 'tagsdiv-fooditem_tag', 'fooditem', 'side' );
	remove_meta_box( 'tagsdiv-fooditem_tag', 'fooditem', 'normal' );
	remove_meta_box( 'tagsdiv-dietary', 'fooditem', 'side' );
	remove_meta_box( 'tagsdiv-dietary', 'fooditem', 'normal' );
}
add_action( 'add_meta_boxes', 'rpress_fooditem_remove_default_taxonomy_metaboxes', 100 );

/**
 * Initialise the tags Select2 in chip / tag-creation mode.
 * The global `rp-select2` init in meta-boxes.js doesn't enable tags mode,
 * so the tags element uses a different class and gets its own init here.
 */
function rpress_fooditem_tags_select2_init() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'fooditem' ) {
		return;
	}
	?>
	<script>
	jQuery( function( $ ) {
		var $tags = $( '.rp-fi-tags-select' );
		if ( ! $tags.length || typeof $tags.select2 !== 'function' ) {
			return;
		}
		$tags.select2( {
			tags: true,
			tokenSeparators: [ ',' ],
			placeholder: $tags.data( 'placeholder' ) || '',
			width: '100%',
			createTag: function( params ) {
				var term = $.trim( params.term );
				if ( '' === term ) { return null; }
				return { id: term, text: term, newTag: true };
			}
		} );
	} );
	</script>
	<?php
}
add_action( 'admin_footer', 'rpress_fooditem_tags_select2_init' );
