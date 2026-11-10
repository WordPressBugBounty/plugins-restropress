<?php
/**
 * Allergens (3.5, free): an allergen taxonomy seeded with the 14 EU allergens,
 * an editor field, badges on the menu and in the item popup, a "Hide items
 * containing" storefront filter, a display setting and REST output.
 *
 * Nutrition facts and printable allergen sheets are not part of core; they
 * belong to the separate Allergen & Nutrition extension.
 *
 * @package RestroPress/Features/Allergens
 * @since 3.5
 */

defined( 'ABSPATH' ) || exit;

define( 'RPRESS_ALLERGEN_TAXONOMY', 'rpress_allergen' );
define( 'RPRESS_ALLERGEN_TRACES_META', '_rpress_allergen_traces' );
define( 'RPRESS_ALLERGEN_SEED_VERSION', '1' );

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/frontend.php';

/**
 * Register the allergen taxonomy.
 *
 * Not public (no archives, no sitemap), but managed in the admin like the
 * dietary labels. The editor renders its own field, so the default metabox is
 * off; values arrive as term IDs through tax_input, hence the checkbox
 * sanitizer.
 *
 * @since 3.5
 * @return void
 */
function rpress_allergens_register_taxonomy() {
	$labels = array(
		'name'                       => __( 'Allergens', 'restropress' ),
		'singular_name'              => __( 'Allergen', 'restropress' ),
		'search_items'               => __( 'Search Allergens', 'restropress' ),
		'all_items'                  => __( 'All Allergens', 'restropress' ),
		'edit_item'                  => __( 'Edit Allergen', 'restropress' ),
		'update_item'                => __( 'Update Allergen', 'restropress' ),
		'add_new_item'               => __( 'Add New Allergen', 'restropress' ),
		'new_item_name'              => __( 'New Allergen Name', 'restropress' ),
		'menu_name'                  => __( 'Allergens', 'restropress' ),
		'not_found'                  => __( 'No allergens found.', 'restropress' ),
		'back_to_items'              => __( 'Back to Allergens', 'restropress' ),
		'choose_from_most_used'      => __( 'Choose from the most used allergens', 'restropress' ),
		'separate_items_with_commas' => __( 'Separate allergens with commas', 'restropress' ),
	);

	$args = apply_filters(
		'rpress_allergen_taxonomy_args',
		array(
			'hierarchical'         => false,
			'labels'               => $labels,
			'public'               => false,
			'publicly_queryable'   => false,
			'show_ui'              => true,
			'show_in_menu'         => true,
			'show_in_nav_menus'    => false,
			'show_tagcloud'        => false,
			'show_admin_column'    => true,
			// Quick edit would post names, which the ID-based sanitizer below drops.
			'show_in_quick_edit'   => false,
			'show_in_rest'         => true,
			'query_var'            => false,
			'rewrite'              => false,
			'meta_box_cb'          => false,
			'meta_box_sanitize_cb' => 'taxonomy_meta_box_sanitize_cb_checkboxes',
			'capabilities'         => array(
				'manage_terms' => 'manage_product_terms',
				'edit_terms'   => 'edit_product_terms',
				'assign_terms' => 'assign_product_terms',
				'delete_terms' => 'delete_product_terms',
			),
		)
	);

	register_taxonomy( RPRESS_ALLERGEN_TAXONOMY, array( 'fooditem' ), $args );
	register_taxonomy_for_object_type( RPRESS_ALLERGEN_TAXONOMY, 'fooditem' );
}
add_action( 'init', 'rpress_allergens_register_taxonomy', 5 );

/**
 * The default allergen set: the 14 EU allergens (Regulation 1169/2011), which
 * also covers the US "major 9". Keyed by slug, each with a name and a short
 * code shown as the badge icon.
 *
 * @since 3.5
 * @return array<string, array{name: string, code: string}>
 */
function rpress_allergens_default_terms() {
	return apply_filters(
		'rpress_default_allergens',
		array(
			'celery'      => array( 'name' => __( 'Celery', 'restropress' ), 'code' => 'CE' ),
			'gluten'      => array( 'name' => __( 'Cereals containing gluten', 'restropress' ), 'code' => 'GL' ),
			'crustaceans' => array( 'name' => __( 'Crustaceans', 'restropress' ), 'code' => 'CR' ),
			'eggs'        => array( 'name' => __( 'Eggs', 'restropress' ), 'code' => 'EG' ),
			'fish'        => array( 'name' => __( 'Fish', 'restropress' ), 'code' => 'FI' ),
			'lupin'       => array( 'name' => __( 'Lupin', 'restropress' ), 'code' => 'LU' ),
			'milk'        => array( 'name' => __( 'Milk', 'restropress' ), 'code' => 'MI' ),
			'molluscs'    => array( 'name' => __( 'Molluscs', 'restropress' ), 'code' => 'MO' ),
			'mustard'     => array( 'name' => __( 'Mustard', 'restropress' ), 'code' => 'MU' ),
			'tree-nuts'   => array( 'name' => __( 'Tree nuts', 'restropress' ), 'code' => 'NU' ),
			'peanuts'     => array( 'name' => __( 'Peanuts', 'restropress' ), 'code' => 'PE' ),
			'sesame'      => array( 'name' => __( 'Sesame', 'restropress' ), 'code' => 'SE' ),
			'soya'        => array( 'name' => __( 'Soya', 'restropress' ), 'code' => 'SO' ),
			'sulphites'   => array( 'name' => __( 'Sulphites', 'restropress' ), 'code' => 'SU' ),
		)
	);
}

/**
 * Seed the default allergens once (on the first load after install or
 * upgrade). Idempotent: terms are matched by slug, so a re-run never creates
 * duplicates, and once the guard option is set, terms the merchant deleted are
 * not brought back.
 *
 * @since 3.5
 * @param bool $force Run even when the guard option says it already ran.
 * @return int Number of terms created.
 */
function rpress_allergens_seed( $force = false ) {
	if ( ! taxonomy_exists( RPRESS_ALLERGEN_TAXONOMY ) ) {
		return 0;
	}
	if ( ! $force && RPRESS_ALLERGEN_SEED_VERSION === (string) get_option( 'rpress_allergens_seeded' ) ) {
		return 0;
	}

	$created = 0;
	foreach ( rpress_allergens_default_terms() as $slug => $def ) {
		$term = get_term_by( 'slug', $slug, RPRESS_ALLERGEN_TAXONOMY );
		if ( ! $term ) {
			$result = wp_insert_term( $def['name'], RPRESS_ALLERGEN_TAXONOMY, array( 'slug' => $slug ) );
			if ( is_wp_error( $result ) ) {
				continue;
			}
			$term_id = (int) $result['term_id'];
			++$created;
		} else {
			$term_id = (int) $term->term_id;
		}
		if ( '' === (string) get_term_meta( $term_id, 'rpress_allergen_code', true ) ) {
			update_term_meta( $term_id, 'rpress_allergen_code', $def['code'] );
		}
	}

	update_option( 'rpress_allergens_seeded', RPRESS_ALLERGEN_SEED_VERSION );
	return $created;
}
add_action( 'init', 'rpress_allergens_seed', 12 );

/**
 * Settings: one "Allergens on menu" select in Styles > Menu Display.
 *
 * A select (not a checkbox) because an unchecked default-on checkbox is
 * indistinguishable from "never saved" in the settings store.
 *
 * @since 3.5
 * @param array $settings Registered settings.
 * @return array
 */
function rpress_allergens_register_settings( $settings ) {
	if ( ! isset( $settings['styles']['main'] ) || ! is_array( $settings['styles']['main'] ) ) {
		return $settings;
	}

	$field = array(
		'allergens_display' => array(
			'id'      => 'allergens_display',
			'name'    => esc_html__( 'Allergens on Menu', 'restropress' ),
			'desc'    => esc_html__( 'Show allergen badges on menu items and in the item popup, plus a "Hide items containing" filter. Badges only appear on items that have allergens set.', 'restropress' ),
			'type'    => 'select',
			'options' => array(
				'all'    => esc_html__( 'Show badges and filter', 'restropress' ),
				'badges' => esc_html__( 'Show badges only', 'restropress' ),
				'off'    => esc_html__( 'Hide', 'restropress' ),
			),
			'std'     => 'all',
		),
	);

	$main  = $settings['styles']['main'];
	$after = array_search( 'enable_tags_display', array_keys( $main ), true );
	if ( false === $after ) {
		$settings['styles']['main'] = $main + $field;
	} else {
		$settings['styles']['main'] = array_slice( $main, 0, $after + 1, true ) + $field + array_slice( $main, $after + 1, null, true );
	}
	return $settings;
}
add_filter( 'rpress_registered_settings', 'rpress_allergens_register_settings' );

/**
 * REST: add readable allergen data to the rp/v1 food response (the taxonomy
 * itself already adds the raw term IDs under `rpress_allergen`).
 *
 * @since 3.5
 * @param WP_REST_Response $response Response.
 * @param WP_Post          $post     Food item.
 * @return WP_REST_Response
 */
function rpress_allergens_rest_prepare( $response, $post ) {
	if ( ! $response instanceof WP_REST_Response || ! $post instanceof WP_Post ) {
		return $response;
	}
	$data                    = $response->get_data();
	$data['allergens']       = rpress_allergens_for_rest( rpress_get_fooditem_allergens( $post->ID ) );
	$data['allergen_traces'] = rpress_allergens_for_rest( rpress_get_fooditem_allergen_traces( $post->ID ) );
	$response->set_data( $data );
	return $response;
}
add_filter( 'rest_prepare_fooditem', 'rpress_allergens_rest_prepare', 20, 2 );

/**
 * REST schema for the allergen fields.
 *
 * @since 3.5
 * @param array $schema Additional schema properties.
 * @return array
 */
function rpress_allergens_rest_schema( $schema ) {
	$item = array(
		'type'       => 'object',
		'properties' => array(
			'id'   => array( 'type' => 'integer' ),
			'slug' => array( 'type' => 'string' ),
			'name' => array( 'type' => 'string' ),
			'code' => array( 'type' => 'string' ),
		),
	);
	$schema['allergens']       = array(
		'description' => __( 'Allergens the item contains.', 'restropress' ),
		'type'        => 'array',
		'items'       => $item,
		'context'     => array( 'view', 'edit', 'embed' ),
		'readonly'    => true,
	);
	$schema['allergen_traces'] = array(
		'description' => __( 'Allergens the item may contain traces of.', 'restropress' ),
		'type'        => 'array',
		'items'       => $item,
		'context'     => array( 'view', 'edit', 'embed' ),
		'readonly'    => true,
	);
	return $schema;
}
add_filter( 'rp_rest_api_fooditem_schema', 'rpress_allergens_rest_schema' );
