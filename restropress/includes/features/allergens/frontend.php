<?php
/**
 * Allergens storefront: badges on menu cards and in the item popup, and the
 * "Hide items containing" filter next to the menu search.
 *
 * @package RestroPress/Features/Allergens
 * @since 3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Card badges for the Classic templates (list, grid and the legacy
 * [fooditems] card), right after the dietary labels.
 *
 * @since 3.5
 * @return void
 */
function rpress_allergens_render_card_badges() {
	rpress_allergens_render_badges_for( get_the_ID() );
}
add_action( 'rpress_fooditem_after_title', 'rpress_allergens_render_card_badges', 6 );

/**
 * Card badges for template packs that build their own badge row (Modern
 * list card) and fire `rpress_fooditem_after_badges` with the item ID.
 *
 * @since 3.5
 * @param int $fooditem_id Food item ID.
 * @return void
 */
function rpress_allergens_render_badges_for( $fooditem_id ) {
	if ( 'off' === rpress_allergens_display_mode() ) {
		return;
	}
	echo rpress_get_allergen_badges_html( $fooditem_id, 'card' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
}
add_action( 'rpress_fooditem_after_badges', 'rpress_allergens_render_badges_for' );

/**
 * Popup: prepend the allergen block to the item popup body (the html the
 * show_products AJAX handler returns, used by every pack).
 *
 * @since 3.5
 * @param string $html        Popup body HTML.
 * @param int    $fooditem_id Food item ID.
 * @return string
 */
function rpress_allergens_popup_html( $html, $fooditem_id ) {
	if ( 'off' === rpress_allergens_display_mode() ) {
		return $html;
	}
	return rpress_get_allergen_badges_html( $fooditem_id, 'popup' ) . $html;
}
add_filter( 'rpress_show_products_html', 'rpress_allergens_popup_html', 10, 2 );

/**
 * Allergens that are in use on at least one published item (the filter only
 * offers these).
 *
 * @since 3.5
 * @return WP_Term[]
 */
function rpress_allergens_in_use() {
	if ( ! taxonomy_exists( RPRESS_ALLERGEN_TAXONOMY ) ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => RPRESS_ALLERGEN_TAXONOMY,
			'hide_empty' => true,
			'orderby'    => 'name',
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Filter markup. A native <details> disclosure holds toggle chips
 * (aria-pressed); the JS in assets/features/allergens hides matching items.
 *
 * @since 3.5
 * @return string
 */
function rpress_get_allergen_filter_html() {
	if ( 'all' !== rpress_allergens_display_mode() ) {
		return '';
	}
	$terms = rpress_allergens_in_use();
	if ( empty( $terms ) ) {
		return '';
	}

	rpress_allergens_enqueue_filter_script();

	ob_start();
	?>
	<div class="rp-allergen-filter" data-rp-allergen-filter>
		<details class="rp-allergen-filter__details">
			<summary class="rp-allergen-filter__toggle">
				<svg class="rp-allergen-filter__icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
				<span class="rp-allergen-filter__label"><?php esc_html_e( 'Allergens', 'restropress' ); ?></span>
				<span class="rp-allergen-filter__count" data-rp-allergen-count hidden></span>
			</summary>
			<div class="rp-allergen-filter__panel">
				<p class="rp-allergen-filter__title" id="rp-allergen-filter-title"><?php esc_html_e( 'Hide items containing', 'restropress' ); ?></p>
				<div class="rp-allergen-filter__chips" role="group" aria-labelledby="rp-allergen-filter-title">
					<?php foreach ( $terms as $term ) : ?>
						<button type="button" class="rp-allergen-chip" data-allergen="<?php echo esc_attr( $term->slug ); ?>" aria-pressed="false">
							<span class="rp-allergen__code" aria-hidden="true"><?php echo esc_html( rpress_allergen_code( $term ) ); ?></span>
							<span class="rp-allergen-chip__name"><?php echo esc_html( $term->name ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<label class="rp-allergen-filter__traces">
					<input type="checkbox" data-rp-allergen-traces>
					<?php esc_html_e( 'Also hide items that may contain traces', 'restropress' ); ?>
				</label>
				<div class="rp-allergen-filter__footer">
					<span class="rp-allergen-filter__status" data-rp-allergen-status aria-live="polite"></span>
					<button type="button" class="rp-allergen-filter__clear" data-rp-allergen-clear><?php esc_html_e( 'Clear', 'restropress' ); ?></button>
				</div>
				<p class="rp-allergen-filter__note"><?php esc_html_e( 'Allergen information is provided by the restaurant. If you have a severe allergy, please contact us before ordering.', 'restropress' ); ?></p>
			</div>
		</details>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Print the filter next to the menu search box.
 *
 * @since 3.5
 * @return void
 */
function rpress_allergens_render_filter() {
	echo rpress_get_allergen_filter_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building.
}
add_action( 'before_fooditems_list', 'rpress_allergens_render_filter', 20 );

/**
 * Whether any food item has an allergen (or a trace) assigned, so stores that
 * never use allergens do not load the stylesheet. Cached in a transient that
 * is cleared whenever assignments change.
 *
 * @since 3.5
 * @return bool
 */
function rpress_allergens_any_assigned() {
	$cached = get_transient( 'rpress_allergens_assigned' );
	if ( 'yes' === $cached || 'no' === $cached ) {
		return 'yes' === $cached;
	}
	$assigned = false;
	if ( taxonomy_exists( RPRESS_ALLERGEN_TAXONOMY ) ) {
		$terms    = get_terms(
			array(
				'taxonomy'   => RPRESS_ALLERGEN_TAXONOMY,
				'hide_empty' => true,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);
		$assigned = ! is_wp_error( $terms ) && ! empty( $terms );
	}
	if ( ! $assigned ) {
		$assigned = (bool) get_posts(
			array(
				'post_type'        => 'fooditem',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => RPRESS_ALLERGEN_TRACES_META,
						'value'   => array( '', 'a:0:{}' ),
						'compare' => 'NOT IN',
					),
				),
			)
		);
	}
	set_transient( 'rpress_allergens_assigned', $assigned ? 'yes' : 'no', DAY_IN_SECONDS );
	return (bool) apply_filters( 'rpress_allergens_any_assigned', $assigned );
}

/**
 * Forget the cached "any allergen assigned" answer.
 *
 * @since 3.5
 * @return void
 */
function rpress_allergens_flush_assigned() {
	delete_transient( 'rpress_allergens_assigned' );
}

/**
 * Flush when allergen terms are set on a post.
 *
 * @param int    $object_id Object ID.
 * @param array  $terms     Terms.
 * @param array  $tt_ids    Term taxonomy IDs.
 * @param string $taxonomy  Taxonomy.
 * @return void
 */
function rpress_allergens_flush_on_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( RPRESS_ALLERGEN_TAXONOMY === $taxonomy ) {
		rpress_allergens_flush_assigned();
	}
}
add_action( 'set_object_terms', 'rpress_allergens_flush_on_terms', 10, 4 );

/**
 * Flush when an allergen term is deleted.
 *
 * @param int    $term_id  Term ID.
 * @param int    $tt_id    Term taxonomy ID.
 * @param string $taxonomy Taxonomy.
 * @return void
 */
function rpress_allergens_flush_on_term_delete( $term_id, $tt_id, $taxonomy ) {
	if ( RPRESS_ALLERGEN_TAXONOMY === $taxonomy ) {
		rpress_allergens_flush_assigned();
	}
}
add_action( 'delete_term', 'rpress_allergens_flush_on_term_delete', 10, 3 );

/**
 * Flush when an item's traces change.
 *
 * @param int    $meta_id   Meta ID (or IDs on delete).
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key.
 * @return void
 */
function rpress_allergens_flush_on_meta( $meta_id, $object_id, $meta_key ) {
	if ( RPRESS_ALLERGEN_TRACES_META === $meta_key ) {
		rpress_allergens_flush_assigned();
	}
}
add_action( 'added_post_meta', 'rpress_allergens_flush_on_meta', 10, 3 );
add_action( 'updated_post_meta', 'rpress_allergens_flush_on_meta', 10, 3 );
add_action( 'deleted_post_meta', 'rpress_allergens_flush_on_meta', 10, 3 );
// Publishing, trashing or deleting an item changes the counts too.
add_action( 'transition_post_status', 'rpress_allergens_flush_on_status', 10, 3 );

/**
 * Flush when a food item changes status.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post.
 * @return void
 */
function rpress_allergens_flush_on_status( $new_status, $old_status, $post ) {
	if ( $new_status !== $old_status && $post instanceof WP_Post && 'fooditem' === $post->post_type ) {
		rpress_allergens_flush_assigned();
	}
}

/**
 * Badge and filter styles: tiny, so they load wherever the storefront styles
 * do (the popup is filled by AJAX on any page with a menu).
 *
 * @since 3.5
 * @return void
 */
function rpress_allergens_enqueue_styles() {
	if ( 'off' === rpress_allergens_display_mode() || ! rpress_allergens_any_assigned() ) {
		return;
	}
	wp_enqueue_style(
		'rpress-allergens',
		RP_PLUGIN_URL . 'assets/features/allergens/allergens.css',
		array(),
		RP_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'rpress_allergens_enqueue_styles', 20 );

/**
 * Filter script, enqueued only when the filter renders (prints in the footer).
 *
 * @since 3.5
 * @return void
 */
function rpress_allergens_enqueue_filter_script() {
	wp_enqueue_script(
		'rpress-allergens-filter',
		RP_PLUGIN_URL . 'assets/features/allergens/allergen-filter.js',
		array(),
		RP_VERSION,
		true
	);
	wp_localize_script(
		'rpress-allergens-filter',
		'rpressAllergens',
		array(
			/* translators: %d: number of menu items hidden by the allergen filter */
			'hiddenOne'  => __( '%d item hidden', 'restropress' ),
			/* translators: %d: number of menu items hidden by the allergen filter */
			'hiddenMany' => __( '%d items hidden', 'restropress' ),
		)
	);
}
