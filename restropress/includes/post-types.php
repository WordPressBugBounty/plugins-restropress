<?php
/**
 * Post Type Functions
 *
 * @package     RPRESS
 * @subpackage  Functions
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Registers and sets up the RestroPress custom post type
 *
 * @since 1.0
 * @return void
 */
function rpress_setup_rpress_post_types() {
	$archives = defined( 'RPRESS_DISABLE_ARCHIVE' ) && RPRESS_DISABLE_ARCHIVE ? false : true;
	$slug     = defined( 'RPRESS_SLUG' ) ? RPRESS_SLUG : 'fooditems';
	$rewrite  = defined( 'RPRESS_DISABLE_REWRITE' ) && RPRESS_DISABLE_REWRITE ? false : array( 'slug' => $slug, 'with_front' => false );
	$fooditem_labels =  apply_filters( 'rpress_fooditem_labels', array(
		'name'                  => _x( '%2$s', 'fooditem post type name', 'restropress' ),
		'singular_name'         => _x( '%1$s', 'singular fooditem post type name', 'restropress' ),
		'add_new'               => __( 'Add New', 'restropress' ),
		'add_new_item'          => __( 'Add New %1$s', 'restropress' ),
		'edit_item'             => __( 'Edit %1$s', 'restropress' ),
		'new_item'              => __( 'New %1$s', 'restropress' ),
		'all_items'             => __( 'All %2$s', 'restropress' ),
		'view_item'             => __( 'View %1$s', 'restropress' ),
		'search_items'          => __( 'Search %2$s', 'restropress' ),
		'not_found'             => __( 'No %2$s found', 'restropress' ),
		'not_found_in_trash'    => __( 'No %2$s found in Trash', 'restropress' ),
		'parent_item_colon'     => '',
		'menu_name'             => _x( 'Menu Items', 'fooditem post type menu name', 'restropress' ),
		'featured_image'        => __( '%1$s Image', 'restropress' ),
		'set_featured_image'    => __( 'Set %1$s Image', 'restropress' ),
		'remove_featured_image' => __( 'Remove %1$s Image', 'restropress' ),
		'use_featured_image'    => __( 'Use as %1$s Image', 'restropress' ),
		'attributes'            => __( '%1$s Attributes', 'restropress' ),
		'filter_items_list'     => __( 'Filter %2$s list', 'restropress' ),
		'items_list_navigation' => __( '%2$s list navigation', 'restropress' ),
		'items_list'            => __( '%2$s list', 'restropress' ),
	) );
	foreach ( $fooditem_labels as $key => $value ) {
		$fooditem_labels[ $key ] = sprintf( $value, rpress_get_label_singular(), rpress_get_label_plural() );
	}
	$fooditem_args = array(
		'labels'             => $fooditem_labels,
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => false,
		'capability_type'    => 'product',
		'map_meta_cap'       => true,
		'publicly_queryable' => false,
		'has_archive'        => false,
		'hierarchical'       => false,
		'supports'           => apply_filters( 'rpress_fooditem_supports', array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author' ) ),
	);
	register_post_type( 'fooditem', apply_filters( 'rpress_fooditem_post_type_args', $fooditem_args ) );
	/** Payment Post Type */
	$payment_labels = array(
		'name'               => _x( 'Orders', 'post type general name', 'restropress' ),
		'singular_name'      => _x( 'Order', 'post type singular name', 'restropress' ),
		'add_new'            => __( 'Add New', 'restropress' ),
		'add_new_item'       => __( 'Add New Order', 'restropress' ),
		'edit_item'          => __( 'Edit Order', 'restropress' ),
		'new_item'           => __( 'New Order', 'restropress' ),
		'all_items'          => __( 'All Orders', 'restropress' ),
		'view_item'          => __( 'View Order', 'restropress' ),
		'search_items'       => __( 'Search Orders', 'restropress' ),
		'not_found'          => __( 'No Orders found', 'restropress' ),
		'not_found_in_trash' => __( 'No Orders found in Trash', 'restropress' ),
		'parent_item_colon'  => '',
		'menu_name'          => __( 'Orders', 'restropress' )
	);
	$payment_args = array(
		'labels'          => apply_filters( 'rpress_payment_labels', $payment_labels ),
		'public'          => false,
		'query_var'       => false,
		'rewrite'         => false,
		'capability_type' => 'shop_payment',
		'map_meta_cap'    => true,
		'supports'        => array( 'title' ),
		'can_export'      => true
	);
	register_post_type( 'rpress_payment', $payment_args );
	/** Discounts Post Type */
	$discount_labels = array(
		'name'               => _x( 'Discounts', 'post type general name', 'restropress' ),
		'singular_name'      => _x( 'Discount', 'post type singular name', 'restropress' ),
		'add_new'            => __( 'Add New', 'restropress' ),
		'add_new_item'       => __( 'Add New Discount', 'restropress' ),
		'edit_item'          => __( 'Edit Discount', 'restropress' ),
		'new_item'           => __( 'New Discount', 'restropress' ),
		'all_items'          => __( 'All Discounts', 'restropress' ),
		'view_item'          => __( 'View Discount', 'restropress' ),
		'search_items'       => __( 'Search Discounts', 'restropress' ),
		'not_found'          => __( 'No Discounts found', 'restropress' ),
		'not_found_in_trash' => __( 'No Discounts found in Trash', 'restropress' ),
		'parent_item_colon'  => '',
		'menu_name'          => __( 'Discounts', 'restropress' )
	);
	$discount_args = array(
		'labels'          => apply_filters( 'rpress_discount_labels', $discount_labels ),
		'public'          => false,
		'query_var'       => false,
		'rewrite'         => false,
		'show_ui'         => false,
		'capability_type' => 'shop_discount',
		'map_meta_cap'    => true,
		'supports'        => array( 'title' ),
		'can_export'      => true
	);
	register_post_type( 'rpress_discount', $discount_args );
}
add_action( 'init', 'rpress_setup_rpress_post_types', 1 );
/**
 * Get Default Labels
 *
 * @since 1.0
 * @return array $defaults Default labels
 */
function rpress_get_default_labels() {
	$defaults = array(
	   'singular' => __( 'Menu Item', 'restropress' ),
	   'plural'   => __( 'Menu Items', 'restropress' )
	);
	return apply_filters( 'rpress_default_fooditems_name', $defaults );
}
/**
 * Get Singular Label
 *
 * @since 1.0
 *
 * @param bool $lowercase
 * @return string $defaults['singular'] Singular label
 */
function rpress_get_label_singular( $lowercase = false ) {
	$defaults = rpress_get_default_labels();
	return ( $lowercase ) ? strtolower( $defaults['singular'] ) : $defaults['singular'];
}
/**
 * Get Plural Label
 *
 * @since 1.0
 * @return string $defaults['plural'] Plural label
 */
function rpress_get_label_plural( $lowercase = false ) {
	$defaults = rpress_get_default_labels();
	return ( $lowercase ) ? strtolower( $defaults['plural'] ) : $defaults['plural'];
}
/**
 * Change default "Enter title here" input
 *
 * @since  1.0.2
 * @param string $title Default title placeholder text
 * @return string $title New placeholder text
 */
function rpress_change_default_title( $title ) {
	 // If a frontend plugin uses this filter (check extensions before changing this function)
	 if ( !is_admin() ) {
		$label = rpress_get_label_singular();
		$title = sprintf( __( 'Enter %s name here', 'restropress' ), $label );
		return $title;
	 }
	 $screen = get_current_screen();
	 if ( 'fooditem' == $screen->post_type ) {
		$label = rpress_get_label_singular();
		$title = sprintf( __( 'Enter %s name here', 'restropress' ), $label );
	 }
	 return $title;
}
add_filter( 'enter_title_here', 'rpress_change_default_title' );
/**
 * Registers the custom taxonomies for the fooditems custom post type
 *
 * @since 1.0
 * @return void
*/
function rpress_setup_fooditem_taxonomies() {
	$slug = defined( 'RPRESS_SLUG' ) ? RPRESS_SLUG : 'fooditems';
	$food_category_label = array(
    'name'              => _x( 'Food Category', 'taxonomy general name', 'restropress' ),
    'singular_name'     => _x( 'Food Category', 'taxonomy singular name', 'restropress' ),
    'search_items'      => __( 'Search Food Category', 'restropress' ),
    'all_items'         => __( 'All Food Category', 'restropress' ),
    'parent_item'       => __( 'Parent Food Category', 'restropress' ),
    'parent_item_colon' => __( 'Parent Food Category:', 'restropress' ),
    'edit_item'         => __( 'Edit Food Category', 'restropress' ),
    'update_item'       => __( 'Update Food Category', 'restropress' ),
    'add_new_item'      => __( 'Add New Food Category', 'restropress' ),
    'new_item_name'     => __( 'New Food Category', 'restropress' ),
    'menu_name'         => __( 'Categories', 'restropress' ),
  );
  $food_item_args = array(
    'hierarchical' 		=> true,
    'show_admin_column' => true,
    'labels'            => $food_category_label,
    'show_ui'           => true,
    'query_var'         => true,
    'show_in_rest'      => true, // Needed for the Food Menu block's category picker.
    'rewrite'           => array( 'slug' => 'food-category' ),
  );
  register_taxonomy( 'food-category', array( 'fooditem' ), $food_item_args );
  //Register taxonomy for food category
  register_taxonomy_for_object_type( 'food-category', 'fooditem' );
	/** Add-on Groups */
	$category_labels = array(
		'name'              => _x( 'Add-on Groups', 'taxonomy general name', 'restropress' ),
		'singular_name'     => _x( 'Add-on Group', 'taxonomy singular name', 'restropress' ),
		'search_items'      => __( 'Search Add-on Groups', 'restropress' ),
		'all_items'         => __( 'All Add-on Groups', 'restropress' ),
		'parent_item'       => __( 'Parent Add-on Group', 'restropress' ),
		'parent_item_colon' => __( 'Parent Add-on Group:', 'restropress' ),
		'edit_item'         => __( 'Edit Add-on Group', 'restropress' ),
		'update_item'       => __( 'Update Add-on Group', 'restropress' ),
		'add_new_item'      => __( 'Add New Add-on Group', 'restropress' ),
		'new_item_name'     => __( 'New Add-on Group Name', 'restropress' ),
		'menu_name'         => __( 'Add-ons', 'restropress' ),
	);
	$category_args = apply_filters( 'rpress_addon_category_args', array(
			'hierarchical' => true,
			'labels'       => apply_filters('rpress_addon_category_labels', $category_labels),
			'show_ui'      => true,
			'show_admin_column' => false,
			'query_var'    => 'addon_category',
			'rewrite'      => array('slug' => $slug . '/category', 'with_front' => false, 'hierarchical' => true ),
			'capabilities' => array( 'manage_terms' => 'manage_product_terms','edit_terms' => 'edit_product_terms','assign_terms' => 'assign_product_terms','delete_terms' => 'delete_product_terms' )
		)
	);
	register_taxonomy( 'addon_category', array( 'fooditem' ), $category_args );
	register_taxonomy_for_object_type( 'addon_category', 'fooditem' );
	/** Tags */
	$tag_labels = array(
		'name'                  => sprintf( _x( '%s Tags', 'taxonomy general name', 'restropress' ), rpress_get_label_singular() ),
		'singular_name'         => sprintf( _x( '%s Tag', 'taxonomy singular name', 'restropress' ), rpress_get_label_singular() ),
		'search_items'          => sprintf( __( 'Search %s Tags', 'restropress' ), rpress_get_label_singular() ),
		'all_items'             => sprintf( __( 'All %s Tags', 'restropress' ), rpress_get_label_singular() ),
		'parent_item'           => sprintf( __( 'Parent %s Tag', 'restropress' ), rpress_get_label_singular() ),
		'parent_item_colon'     => sprintf( __( 'Parent %s Tag:', 'restropress' ), rpress_get_label_singular() ),
		'edit_item'             => sprintf( __( 'Edit %s Tag', 'restropress' ), rpress_get_label_singular() ),
		'update_item'           => sprintf( __( 'Update %s Tag', 'restropress' ), rpress_get_label_singular() ),
		'add_new_item'          => sprintf( __( 'Add New %s Tag', 'restropress' ), rpress_get_label_singular() ),
		'new_item_name'         => sprintf( __( 'New %s Tag Name', 'restropress' ), rpress_get_label_singular() ),
		'menu_name'             => __( 'Tags', 'restropress' ),
		'choose_from_most_used' => sprintf( __( 'Choose from most used %s tags', 'restropress' ), rpress_get_label_singular() ),
	);
	$tag_args = apply_filters( 'rpress_fooditem_tag_args', array(
			'hierarchical' => false,
			'labels'       => apply_filters( 'rpress_fooditem_tag_labels', $tag_labels ),
			'show_ui'      => true,
			'query_var'    => 'fooditem_tag',
			'rewrite'      => array( 'slug' => $slug . '/tag', 'with_front' => false, 'hierarchical' => true  ),
			'capabilities' => array( 'manage_terms' => 'manage_product_terms','edit_terms' => 'edit_product_terms','assign_terms' => 'assign_product_terms','delete_terms' => 'delete_product_terms' )
		)
	);
	register_taxonomy( 'fooditem_tag', array( 'fooditem' ), $tag_args );
	register_taxonomy_for_object_type( 'fooditem_tag', 'fooditem' );

	/** Dietary preferences (worldwide standard: vegetarian, vegan, gluten-free, halal, etc.) */
	$dietary_labels = array(
		'name'                  => __( 'Dietary Labels', 'restropress' ),
		'singular_name'         => __( 'Dietary Label', 'restropress' ),
		'search_items'          => __( 'Search Dietary Labels', 'restropress' ),
		'all_items'             => __( 'All Dietary Labels', 'restropress' ),
		'edit_item'             => __( 'Edit Dietary Label', 'restropress' ),
		'update_item'           => __( 'Update Dietary Label', 'restropress' ),
		'add_new_item'          => __( 'Add New Dietary Label', 'restropress' ),
		'new_item_name'         => __( 'New Dietary Label Name', 'restropress' ),
		'menu_name'             => __( 'Dietary Labels', 'restropress' ),
		'choose_from_most_used' => __( 'Choose from the most used dietary labels', 'restropress' ),
	);
	$dietary_args = apply_filters( 'rpress_dietary_args', array(
			'hierarchical' => false,
			'labels'       => apply_filters( 'rpress_dietary_labels', $dietary_labels ),
			'show_ui'      => true,
			'show_in_rest' => true,
			'query_var'    => 'dietary',
			'rewrite'      => array( 'slug' => $slug . '/dietary', 'with_front' => false ),
			'capabilities' => array( 'manage_terms' => 'manage_product_terms', 'edit_terms' => 'edit_product_terms', 'assign_terms' => 'assign_product_terms', 'delete_terms' => 'delete_product_terms' ),
		)
	);
	register_taxonomy( 'dietary', array( 'fooditem' ), $dietary_args );
	register_taxonomy_for_object_type( 'dietary', 'fooditem' );
}
add_action( 'init', 'rpress_setup_fooditem_taxonomies', 0 );

/**
 * Seed the default dietary labels once (worldwide standard set).
 *
 * Runs after the taxonomy is registered; guarded by an option so it only
 * inserts the defaults a single time and never fights manual edits.
 *
 * @since 3.3
 * @return void
 */
function rpress_seed_default_dietary_terms() {
	if ( ! taxonomy_exists( 'dietary' ) ) {
		return;
	}
	// Versioned guard: '2' also runs a one-time de-duplication pass.
	if ( '2' === (string) get_option( 'rpress_dietary_seeded' ) ) {
		return;
	}

	$defaults = apply_filters( 'rpress_default_dietary_terms', array(
		'Vegetarian', 'Vegan', 'Gluten-free', 'Dairy-free', 'Nut-free', 'Halal', 'Kosher', 'Spicy',
	) );

	// Index existing terms by lower-cased name so we can detect duplicates.
	$existing = get_terms( array( 'taxonomy' => 'dietary', 'hide_empty' => false ) );
	$by_name  = array();
	if ( is_array( $existing ) ) {
		foreach ( $existing as $t ) {
			$by_name[ strtolower( $t->name ) ][] = $t;
		}
	}

	foreach ( $defaults as $name ) {
		$key = strtolower( $name );
		if ( empty( $by_name[ $key ] ) ) {
			wp_insert_term( $name, 'dietary' ); // missing - create once.
		} elseif ( count( $by_name[ $key ] ) > 1 ) {
			// Duplicates exist - keep the first, delete the rest (only safe for unused terms).
			$dupes = $by_name[ $key ];
			array_shift( $dupes );
			foreach ( $dupes as $extra ) {
				if ( 0 === (int) $extra->count ) {
					wp_delete_term( $extra->term_id, 'dietary' );
				}
			}
		}
	}

	update_option( 'rpress_dietary_seeded', '2' );
}
add_action( 'init', 'rpress_seed_default_dietary_terms', 11 );
/**
 * Get the singular and plural labels for a fooditem taxonomy
 *
 * @since 1.0
 * @param  string $taxonomy The Taxonomy to get labels for
 * @return array            Associative array of labels (name = plural)
 */
function rpress_get_taxonomy_labels( $taxonomy = 'addon_category' ) {
	$allowed_taxonomies = apply_filters( 'rpress_allowed_fooditem_taxonomies', array( 'addon_category', 'fooditem_tag', 'food-category' ) );
	if ( ! in_array( $taxonomy, $allowed_taxonomies ) ) {
		return false;
	}
	$labels   = array();
	$taxonomy = get_taxonomy( $taxonomy );
	if ( false !== $taxonomy ) {
		$singular  = $taxonomy->labels->singular_name;
		$name      = $taxonomy->labels->name;
		$menu_name = $taxonomy->labels->menu_name;
		$labels = array(
			'name'          => $name,
			'singular_name' => $singular,
			'menu_name'     => $menu_name,
		);
	}
	return apply_filters( 'rpress_get_taxonomy_labels', $labels, $taxonomy );
}
/**
 * Registers Custom Post Statuses which are used by the Payments and Discount
 * Codes
 *
 * @since 1.0.9.1
 * @return void
 */
function rpress_register_post_type_statuses() {
	// Payment Statuses
	register_post_status( 'refunded', array(
		'label'                     => _x( 'Refunded', 'Refunded payment status', 'restropress' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Refunded <span class="count">(%s)</span>', 'Refunded <span class="count">(%s)</span>', 'restropress' )
	) );
	register_post_status( 'paid', array(
		'label'                     => _x( 'Paid', 'Paid payment status', 'restropress' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Paid <span class="count">(%s)</span>', 'Paid <span class="count">(%s)</span>', 'restropress' )
	) );
	register_post_status( 'failed', array(
		'label'                     => _x( 'Failed', 'Failed payment status', 'restropress' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Failed <span class="count">(%s)</span>', 'Failed <span class="count">(%s)</span>', 'restropress' )
	)  );
	register_post_status( 'revoked', array(
		'label'                     => _x( 'Revoked', 'Revoked payment status', 'restropress' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Revoked <span class="count">(%s)</span>', 'Revoked <span class="count">(%s)</span>', 'restropress' )
	)  );
	register_post_status( 'abandoned', array(
		'label'                     => _x( 'Abandoned', 'Abandoned payment status', 'restropress' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Abandoned <span class="count">(%s)</span>', 'Abandoned <span class="count">(%s)</span>', 'restropress' )
	)  );
	register_post_status( 'processing', array(
		'label'                     => _x( 'Processing', 'Processing payment status', 'restropress' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Processing <span class="count">(%s)</span>', 'Processing <span class="count">(%s)</span>', 'restropress' )
	)  );
	// Discount Code Statuses
	register_post_status( 'active', array(
		'label'                     => _x( 'Active', 'Active discount code status', 'restropress' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'restropress' )
	)  );
	register_post_status( 'inactive', array(
		'label'                     => _x( 'Inactive', 'Inactive discount code status', 'restropress' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Inactive <span class="count">(%s)</span>', 'Inactive <span class="count">(%s)</span>', 'restropress' )
	)  );
}
add_action( 'init', 'rpress_register_post_type_statuses', 2 );
/**
 * Updated Messages
 *
 * Returns an array of with all updated messages.
 *
 * @since 1.0
 * @param array $messages Post updated message
 * @return array $messages New post updated messages
 */
function rpress_updated_messages( $messages ) {
	global $post, $post_ID;
	$url1 = '<a href="' . get_permalink( $post_ID ) . '">';
	$url2 = rpress_get_label_singular();
	$url3 = '</a>';
	$messages['fooditem'] = array(
		1 => sprintf( __( '%2$s updated. %1$sView %2$s%3$s.', 'restropress' ), $url1, $url2, $url3 ),
		4 => sprintf( __( '%2$s updated. %1$sView %2$s%3$s.', 'restropress' ), $url1, $url2, $url3 ),
		6 => sprintf( __( '%2$s published. %1$sView %2$s%3$s.', 'restropress' ), $url1, $url2, $url3 ),
		7 => sprintf( __( '%2$s saved. %1$sView %2$s%3$s.', 'restropress' ), $url1, $url2, $url3 ),
		8 => sprintf( __( '%2$s submitted. %1$sView %2$s%3$s.', 'restropress' ), $url1, $url2, $url3 )
	);
	return $messages;
}
add_filter( 'post_updated_messages', 'rpress_updated_messages' );
/**
 * Updated bulk messages
 *
 * @since 1.0
 * @param array $bulk_messages Post updated messages
 * @param array $bulk_counts Post counts
 * @return array $bulk_messages New post updated messages
 */
function rpress_bulk_updated_messages( $bulk_messages, $bulk_counts ) {
	$singular = rpress_get_label_singular();
	$plural   = rpress_get_label_plural();
	$bulk_messages['fooditem'] = array(
		'updated'   => sprintf( _n( '%1$s %2$s updated.', '%1$s %3$s updated.', $bulk_counts['updated'], 'restropress' ), $bulk_counts['updated'], $singular, $plural ),
		'locked'    => sprintf( _n( '%1$s %2$s not updated, somebody is editing it.', '%1$s %3$s not updated, somebody is editing them.', $bulk_counts['locked'], 'restropress' ), $bulk_counts['locked'], $singular, $plural ),
		'deleted'   => sprintf( _n( '%1$s %2$s permanently deleted.', '%1$s %3$s permanently deleted.', $bulk_counts['deleted'], 'restropress' ), $bulk_counts['deleted'], $singular, $plural ),
		'trashed'   => sprintf( _n( '%1$s %2$s moved to the Trash.', '%1$s %3$s moved to the Trash.', $bulk_counts['trashed'], 'restropress' ), $bulk_counts['trashed'], $singular, $plural ),
		'untrashed' => sprintf( _n( '%1$s %2$s restored from the Trash.', '%1$s %3$s restored from the Trash.', $bulk_counts['untrashed'], 'restropress' ), $bulk_counts['untrashed'], $singular, $plural )
	);
	return $bulk_messages;
}
add_filter( 'bulk_post_updated_messages', 'rpress_bulk_updated_messages', 10, 2 );
/**
 * Add row actions for the fooditems custom post type
 *
 * @since  1.0.0
 * @param  array $actions
 * @param  WP_Post $post
 * @return array
 */
function  rpress_fooditem_row_actions( $actions, $post ) {
	if ( 'fooditem' === $post->post_type ) {
		return array_merge( array( 'id' => 'ID: ' . $post->ID ), $actions );
	}
	return $actions;
}
add_filter( 'post_row_actions', 'rpress_fooditem_row_actions', 2, 100 );
