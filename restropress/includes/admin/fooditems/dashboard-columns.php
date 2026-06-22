<?php
/**
 * Dashboard Columns
 *
 * @package     RPRESS
 * @subpackage  Admin/RestroPress
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * RestroPress Columns
 *
 * Defines the custom columns and their order
 *
 * @since 1.0
 * @param array $fooditem_columns Array of fooditem columns
 * @return array $fooditem_columns Updated array of fooditem columns for RestroPress
 *  Post Type List Table
 */
function rpress_fooditem_columns( $fooditem_columns ) {
	$category_labels = rpress_get_taxonomy_labels( 'food-category' );
	$tag_labels      = rpress_get_taxonomy_labels( 'fooditem_tag' );
	$fooditem_columns = array(
		'cb'                => '<input type="checkbox"/>',
		'title'             => esc_html__( 'Name', 'restropress' ),
		'food_category' 	=> $category_labels['menu_name'],
		'fooditem_tag'      => $tag_labels['menu_name'],
		'price'             => esc_html__( 'Price', 'restropress' ),
		'earnings'          => esc_html__( 'Earnings', 'restropress' ),
		'date'              => esc_html__( 'Date', 'restropress' )
	);
	return apply_filters( 'rpress_fooditem_columns', $fooditem_columns );
}
add_filter( 'manage_edit-fooditem_columns', 'rpress_fooditem_columns' );
/**
 * Render FoodItem Columns
 *
 * @since 1.0
 * @param string $column_name Column name
 * @param int $post_id FoodItem (Post) ID
 * @return void
 */
function rpress_render_fooditem_columns( $column_name, $post_id ) {
	if ( get_post_type( $post_id ) == 'fooditem' ) {
		switch ( $column_name ) {
			case 'food_category':
				echo get_the_term_list( $post_id, 'food-category', '', ', ', '');
				break;
			case 'fooditem_tag':
				echo get_the_term_list( $post_id, 'fooditem_tag', '', ', ', '');
				break;
			case 'price':
				if ( rpress_has_variable_prices( $post_id ) ) {
					$allowed_html = array(
						'span' => array(
							'class' => true,
							'id'    => true,
						),
					);
					  
					echo wp_kses( rpress_price_range( $post_id ), $allowed_html );
				} else {
					echo wp_kses(
						rpress_price( $post_id, false ),
						array(
							'span' => array(
								'class' => true,
								'id'    => true,
							),
						)
					);
					echo '<input type="hidden" class="fooditemprice-' . esc_attr($post_id) . '" value="' . esc_attr(rpress_get_fooditem_price( $post_id )) . '" />';
				}
				break;
			case 'sales':
				if ( current_user_can( 'view_product_stats', $post_id ) ) {
					echo '<a href="' . esc_url( admin_url( 'admin.php?page=rpress-reports&tab=logs&view=sales&fooditem=' . $post_id ) ) . '">';
						echo esc_html(rpress_get_fooditem_sales_stats( $post_id ));
					echo '</a>';
				} else {
					echo '-';
				}
				break;
			case 'earnings':
				if ( current_user_can( 'view_product_stats', $post_id ) ) {
					echo '<a href="' . esc_url( admin_url( 'admin.php?page=rpress-reports&view=fooditems&fooditem-id=' . $post_id ) ) . '">';
						echo esc_html(rpress_currency_filter( rpress_format_amount( rpress_get_fooditem_earnings_stats( $post_id ) ) ));
					echo '</a>';
				} else {
					echo '-';
				}
				break;
		}
	}
}
add_action( 'manage_posts_custom_column', 'rpress_render_fooditem_columns', 10, 2 );
/**
 * Registers the sortable columns in the list table
 *
 * @since 1.0
 * @param array $columns Array of the columns
 * @return array $columns Array of sortable columns
 */
function rpress_sortable_fooditem_columns( $columns ) {
	$columns['price']    = 'price';
	$columns['sales']    = 'sales';
	$columns['earnings'] = 'earnings';
	return $columns;
}
add_filter( 'manage_edit-fooditem_sortable_columns', 'rpress_sortable_fooditem_columns' );
/**
 * Sorts Columns in the RestroPress List Table
 *
 * @since 1.0
 * @param array $vars Array of all the sort variables
 * @return array $vars Array of all the sort variables
 */
function rpress_sort_fooditems( $vars ) {
	// Check if we're viewing the "fooditem" post type
	if ( isset( $vars['post_type'] ) && 'fooditem' == $vars['post_type'] ) {
		// Check if 'orderby' is set to "sales"
		if ( isset( $vars['orderby'] ) && 'sales' == $vars['orderby'] ) {
			$vars = array_merge(
				$vars,
				array(
					'meta_key' => '_rpress_fooditem_sales',
					'orderby'  => 'meta_value_num'
				)
			);
		}
		// Check if "orderby" is set to "earnings"
		if ( isset( $vars['orderby'] ) && 'earnings' == $vars['orderby'] ) {
			$vars = array_merge(
				$vars,
				array(
					'meta_key' => '_rpress_fooditem_earnings',
					'orderby'  => 'meta_value_num'
				)
			);
		}
		// Check if "orderby" is set to "earnings"
		if ( isset( $vars['orderby'] ) && 'price' == $vars['orderby'] ) {
			$vars = array_merge(
				$vars,
				array(
					'meta_key' => 'rpress_price',
					'orderby'  => 'meta_value_num'
				)
			);
		}
	}
	return $vars;
}
/**
 * Sets restrictions on author of RestroPress List Table
 *
 * @since 1.0
 * @param  array $vars Array of all sort varialbes
 * @return array       Array of all sort variables
 */
function rpress_filter_fooditems( $vars ) {
	if ( isset( $vars['post_type'] ) && 'fooditem' == $vars['post_type'] ) {
		// If an author ID was passed, use it
		if ( isset( $_REQUEST['author'] ) && ! current_user_can( 'view_shop_reports' ) ) {
			$author_id = sanitize_text_field( $_REQUEST['author'] );
			if ( (int) $author_id !== get_current_user_id() ) {
				// Tried to view the products of another person, sorry
				wp_die( esc_html__( 'You do not have permission to view this data.', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
			}
			$vars = array_merge(
				$vars,
				array(
					'author' => get_current_user_id()
				)
			);
		}
	}
	return $vars;
}
/**
 * RestroPress Load
 *
 * Sorts the fooditems.
 *
 * @since 1.0
 * @return void
 */
function rpress_fooditem_load() {
	add_filter( 'request', 'rpress_sort_fooditems' );
	add_filter( 'request', 'rpress_filter_fooditems' );
}
add_action( 'load-edit.php', 'rpress_fooditem_load', 9999 );
/**
 * Add RestroPress Filters
 *
 * Adds taxonomy drop down filters for fooditems.
 *
 * @since 1.0
 * @return void
 */
function rpress_add_fooditem_filters() {
	global $typenow;
	// Checks if the current post type is 'fooditem'
	if ( $typenow == 'fooditem') {
		// Category Filters
		$terms = get_terms( 'food-category' );
		if(count($terms) > 0) {
			echo "<select name='food-category' id='food-category' class='postform'>";
			$category_labels = rpress_get_taxonomy_labels( 'food-category' );
			echo "<option value=''>" . sprintf( esc_html__( 'Show all %s', 'restropress' ), esc_html(strtolower( $category_labels['name'] )) ) . "</option>";
			foreach ($terms as $term) {
				$selected = isset( $_GET['food-category'] ) && $_GET['food-category'] == $term->slug ? ' selected="selected"' : '';
				echo '<option value="' . esc_attr( $term->slug ) . '"' . esc_attr($selected) . '>' . esc_html( $term->name ) .' (' . esc_html($term->count) .')</option>';
			}
			echo "</select>";
		}
		// Addons Filters
		$terms = get_terms( 'addon_category' );
		if ( count( $terms ) > 0 ) {
			echo "<select name='addon_category' id='addon_category' class='postform'>";
			$category_labels = rpress_get_taxonomy_labels( 'addon_category' );
			echo "<option value=''>" . sprintf( esc_html__( 'Show all %s', 'restropress' ), esc_html(strtolower( $category_labels['name'] )) ) . "</option>";
			foreach ( $terms as $term ) {
				$selected = isset( $_GET['addon_category'] ) && $_GET['addon_category'] == $term->slug ? ' selected="selected"' : '';
				echo '<option value="' . esc_attr( $term->slug ) . '"' . esc_attr($selected) . '>' . esc_html( $term->name ) .' (' . esc_html($term->count) .')</option>';
			}
			echo "</select>";
		}
		// Tags Filter
		$terms = get_terms( 'fooditem_tag' );
		if ( count( $terms ) > 0) {
			echo "<select name='fooditem_tag' id='fooditem_tag' class='postform'>";
			$tag_labels = rpress_get_taxonomy_labels( 'fooditem_tag' );
			echo "<option value=''>" . sprintf( esc_html__( 'Show all %s', 'restropress' ), esc_html(strtolower( $tag_labels['name'] )) ) . "</option>";
			foreach ( $terms as $term ) {
				$selected = isset( $_GET['fooditem_tag'] ) && $_GET['fooditem_tag'] == $term->slug ? ' selected="selected"' : '';
				echo '<option value="' . esc_attr( $term->slug ) . '"' . esc_attr($selected) . '>' . esc_html( $term->name ) .' (' . esc_html($term->count) .')</option>';
			}
			echo "</select>";
		}
		if ( isset( $_REQUEST['all_posts'] ) && '1' === $_REQUEST['all_posts'] ) {
			echo '<input type="hidden" name="all_posts" value="1" />';
		} else if ( ! current_user_can( 'view_shop_reports' ) ) {
			$author_id = get_current_user_id();
			echo '<input type="hidden" name="author" value="' . esc_attr( $author_id ) . '" />';
		}
	}
}
add_action( 'restrict_manage_posts', 'rpress_add_fooditem_filters', 100 );
/**
 * Remove RestroPress Month Filter
 *
 * Removes the drop down filter for fooditems by date.
 *
 * @author RestroPress
 * @since  1.0.0
 * @param array $dates The preset array of dates
 * @global $typenow The post type we are viewing
 * @return array Empty array disables the dropdown
 */
function rpress_remove_month_filter( $dates ) {
	global $typenow;
	if ( $typenow == 'fooditem' ) {
		$dates = array();
	}
	return $dates;
}
add_filter( 'months_dropdown_results', 'rpress_remove_month_filter', 99 );
/**
 * Adds price field to Quick Edit options
 *
 * @since  1.0.0
 * @param string $column_name Name of the column
 * @param string $post_type Current Post Type (i.e. fooditem)
 * @return void
 */
function rpress_price_field_quick_edit( $column_name, $post_type ) {
	if ( $column_name != 'price' || $post_type != 'fooditem' ) return;
	?>
	<fieldset class="inline-edit-rp-col-left">
		<div id="rpress-fooditem-data" class="inline-edit-col">
			<h4><?php echo sprintf( esc_html__( '%s Configuration', 'restropress' ), esc_html(rpress_get_label_singular()) ); ?></h4>
			<label>
				<span class="title"><?php esc_html_e( 'Price', 'restropress' ); ?></span>
				<span class="input-text-wrap">
					<input type="text" name="_rpress_regprice" class="text regprice" />
				</span>
			</label>
			<br class="clear" />
		</div>
	</fieldset>
	<?php
}
add_action( 'quick_edit_custom_box', 'rpress_price_field_quick_edit', 10, 2 );
add_action( 'bulk_edit_custom_box', 'rpress_price_field_quick_edit', 10, 2 );
/**
 * Updates price when saving post
 *
 * @since  1.0.0
 * @param int $post_id RestroPress (Post) ID
 * @return void
 */
function rpress_price_save_quick_edit( $post_id ) {
	if ( ! isset( $_POST['post_type']) || 'fooditem' !== $_POST['post_type'] ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return $post_id;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;
	if ( isset( $_REQUEST['_rpress_regprice'] ) ) {
		update_post_meta( $post_id, 'rpress_price', strip_tags( stripslashes( sanitize_text_field( $_REQUEST['_rpress_regprice'] ) ) ) );
	}
}
add_action( 'save_post', 'rpress_price_save_quick_edit' );
/**
 * Process bulk edit actions via AJAX
 *
 * @since  1.0.0
 * @return void
 */
function rpress_save_bulk_edit() {
	check_ajax_referer( 'rpress-bulk-edit', 'rpress_bulk_nonce' );

	if ( ! current_user_can( 'edit_products' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit menu items.', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
	}

	$post_ids = ( isset( $_POST['post_ids'] ) && ! empty( $_POST['post_ids'] ) ) ? rpress_sanitize_array( wp_unslash( $_POST['post_ids'] ) ) : array();
	if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
		$price = isset( $_POST['price'] ) ? strip_tags( stripslashes( sanitize_text_field( wp_unslash( $_POST['price'] ) ) ) ) : 0;
		foreach ( $post_ids as $post_id ) {
			if( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			if ( ! empty( $price ) ) {
				update_post_meta( $post_id, 'rpress_price', rpress_sanitize_amount( $price ) );
			}
		}
	}
	die();
}
add_action( 'wp_ajax_rpress_save_bulk_edit', 'rpress_save_bulk_edit' );
/**
 * Add Duplicate link to food item row actions.
 */
function rpress_fooditem_duplicate_row_action( $actions, $post ) {
	if ( $post->post_type !== 'fooditem' || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}
	$nonce = wp_create_nonce( 'rpress_duplicate_fooditem_' . $post->ID );
	$url   = admin_url( 'admin.php?action=rpress_duplicate_fooditem&post=' . $post->ID . '&_wpnonce=' . $nonce );
	$actions['rp-row-action-duplicate'] = '<a href="' . esc_url( $url ) . '" title="' . esc_attr__( 'Duplicate this menu item as a draft', 'restropress' ) . '">' . esc_html__( 'Duplicate', 'restropress' ) . '</a>';
	return $actions;
}
add_filter( 'post_row_actions', 'rpress_fooditem_duplicate_row_action', 10, 2 );

/**
 * Handle duplicate food item action.
 */
function rpress_handle_duplicate_fooditem() {
	if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'rpress_duplicate_fooditem' ) {
		return;
	}
	if ( empty( $_GET['post'] ) || empty( $_GET['_wpnonce'] ) ) {
		wp_die( esc_html__( 'Missing parameters.', 'restropress' ) );
	}

	$post_id = absint( $_GET['post'] );
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rpress_duplicate_fooditem_' . $post_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'restropress' ) );
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You do not have permission to duplicate this item.', 'restropress' ) );
	}

	$original = get_post( $post_id );
	if ( ! $original || $original->post_type !== 'fooditem' ) {
		wp_die( esc_html__( 'Invalid menu item.', 'restropress' ) );
	}

	$new_id = wp_insert_post(
		array(
			'post_title'   => $original->post_title . ' ' . esc_html__( '(Copy)', 'restropress' ),
			'post_content' => $original->post_content,
			'post_excerpt' => $original->post_excerpt,
			'post_type'    => 'fooditem',
			'post_status'  => 'draft',
			'post_author'  => get_current_user_id(),
		)
	);

	if ( is_wp_error( $new_id ) ) {
		wp_die( esc_html__( 'Could not duplicate the menu item.', 'restropress' ) );
	}

	// Copy all post meta except thumbnail (handled separately below).
	$meta_rows = get_post_meta( $post_id );
	foreach ( $meta_rows as $meta_key => $meta_values ) {
		if ( $meta_key === '_thumbnail_id' ) {
			continue;
		}
		foreach ( $meta_values as $value ) {
			add_post_meta( $new_id, $meta_key, maybe_unserialize( $value ) );
		}
	}

	// Copy featured image.
	$thumbnail_id = get_post_thumbnail_id( $post_id );
	if ( $thumbnail_id ) {
		set_post_thumbnail( $new_id, $thumbnail_id );
	}

	// Copy taxonomies (food-category, addon_category, fooditem_tag).
	$taxonomies = get_object_taxonomies( 'fooditem' );
	foreach ( $taxonomies as $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			wp_set_object_terms( $new_id, $terms, $taxonomy );
		}
	}

	do_action( 'rpress_duplicate_fooditem', $new_id, $post_id );

	wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
	exit;
}
add_action( 'admin_action_rpress_duplicate_fooditem', 'rpress_handle_duplicate_fooditem' );

function add_addons_price_type_columns( $columns ) {
    $columns['price-type'] = 'Price/Type';
    return $columns;
}
add_filter( 'manage_edit-addon_category_columns', 'add_addons_price_type_columns' );
function add_addons_price_type_column_content( $content, $column_name, $term_id ) {
    $term = get_term( $term_id, 'addon_category' );
    switch ( $column_name ) {
        case 'price-type':
            if( $term->parent == 0 ) {
  				$addon_type = get_term_meta( $term_id, '_type', true );
  				$content = !empty( $addon_type ) ? ucfirst( $addon_type ) : 'Multiple';
            } else {
            	$price = !empty( get_term_meta( $term_id, '_price', true ) ) ? get_term_meta( $term_id, '_price', true ) : '0';
            	$content = rpress_currency_filter( rpress_format_amount( $price ), rpress_get_payment_currency_code() );
            }
            break;
        default:
            break;
    }
    return $content;
}
add_filter( 'manage_addon_category_custom_column', 'add_addons_price_type_column_content', 10, 3 );

/**
 * Add "Import" and "Export" buttons to the Menu Items list header, next to
 * "Add New Menu Item" - the WooCommerce Products pattern. The import/export
 * pages are registered but hidden from the submenu (see admin-pages.php), so
 * these buttons are their primary entry point.
 *
 * @since 3.3
 * @return void
 */
function rpress_fooditem_list_header_buttons() {
	$screen = get_current_screen();
	if ( ! $screen || 'fooditem' !== $screen->post_type || 'edit' !== $screen->base ) {
		return;
	}
	$can_import = current_user_can( 'edit_products' );
	$can_export = current_user_can( 'export_shop_reports' );
	if ( ! $can_import && ! $can_export ) {
		return;
	}
	$import_url = esc_url( admin_url( 'edit.php?post_type=fooditem&page=rpress-menu-import' ) );
	$export_url = esc_url( admin_url( 'edit.php?post_type=fooditem&page=rpress-menu-export' ) );
	$buttons    = '';
	if ( $can_import ) {
		$buttons .= '<a href="' . $import_url . '" class="page-title-action rpress-menu-import-action">' . esc_html__( 'Import', 'restropress' ) . '</a>';
	}
	if ( $can_export ) {
		$buttons .= '<a href="' . $export_url . '" class="page-title-action rpress-menu-export-action">' . esc_html__( 'Export', 'restropress' ) . '</a>';
	}
	?>
	<script type="text/javascript">
	jQuery(function ($) {
		var $heading = $('.wrap h1.wp-heading-inline').first();
		if (!$heading.length) { return; }
		// Guard against double insertion (the hook can fire more than once).
		if ($('.page-title-action.rpress-menu-import-action, .page-title-action.rpress-menu-export-action').length) { return; }
		var $anchor = $heading.nextAll('a.page-title-action').last();
		var html = <?php echo wp_json_encode( $buttons ); ?>;
		if ($anchor.length) { $anchor.after(html); } else { $heading.after(html); }
	});
	</script>
	<?php
}
add_action( 'admin_head-edit.php', 'rpress_fooditem_list_header_buttons' );
