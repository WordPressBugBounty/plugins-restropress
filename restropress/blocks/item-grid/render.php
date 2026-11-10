<?php
/**
 * Menu Items block (rpress/item-grid).
 *
 * Items grouped by category. Each card's Add button is the existing
 * purchase link, so it opens the classic item popup. Search and category
 * filtering hide cards through the shared "restropress" store.
 *
 * @package RPRESS
 * @since   3.5
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

if ( ! rpress_block_storefront_supported() ) {
	echo rpress_block_storefront_fallback( 'item-grid', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

rpress_block_storefront_seed_state();

$rpress_sf_layout   = isset( $attributes['layout'] ) && 'list' === $attributes['layout'] ? 'list' : 'grid';
$rpress_sf_columns  = isset( $attributes['columns'] ) ? max( 1, min( 4, (int) $attributes['columns'] ) ) : 2;
$rpress_sf_image    = ! isset( $attributes['showImage'] ) || ! empty( $attributes['showImage'] );
$rpress_sf_desc     = ! isset( $attributes['showDescription'] ) || ! empty( $attributes['showDescription'] );
$rpress_sf_price    = ! isset( $attributes['showPrice'] ) || ! empty( $attributes['showPrice'] );
$rpress_sf_terms    = rpress_block_storefront_categories(
	isset( $attributes['categories'] ) ? $attributes['categories'] : '',
	isset( $attributes['categoryExclude'] ) ? $attributes['categoryExclude'] : ''
);
$rpress_sf_sections = array();
$rpress_sf_old_ui   = ! empty( rpress_get_option( 'old_ui_ux' ) );
$rpress_sf_all      = array();

global $post;
$rpress_sf_prev_post = $post;

foreach ( $rpress_sf_terms as $rpress_sf_term ) {
	$rpress_sf_items = rpress_block_storefront_category_items(
		$rpress_sf_term,
		isset( $attributes['orderby'] ) ? $attributes['orderby'] : 'title',
		isset( $attributes['order'] ) ? $attributes['order'] : 'ASC'
	);
	if ( empty( $rpress_sf_items ) ) {
		continue;
	}
	$rpress_sf_cards  = '';
	$rpress_sf_titles = array();
	foreach ( $rpress_sf_items as $rpress_sf_item ) {
		// The purchase link reads the global post for the button behavior.
		$post = $rpress_sf_item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );

		$rpress_sf_id        = (int) $rpress_sf_item->ID;
		$rpress_sf_title     = get_the_title( $rpress_sf_item );
		$rpress_sf_norm      = rpress_block_storefront_normalize( $rpress_sf_title );
		$rpress_sf_titles[]  = $rpress_sf_norm;
		$rpress_sf_all[]     = $rpress_sf_norm;
		$rpress_sf_available = rpress_fooditem_available( $rpress_sf_id );
		$rpress_sf_classes   = 'rpress-sf-card' . ( $rpress_sf_available ? '' : ' is-unavailable' );

		$rpress_sf_card  = '<article class="' . esc_attr( $rpress_sf_classes ) . '" id="rpress-sf-item-' . esc_attr( $rpress_sf_id ) . '"';
		$rpress_sf_card .= ' data-wp-context="' . esc_attr( wp_json_encode( array( 'title' => $rpress_sf_norm ) ) ) . '"';
		$rpress_sf_card .= ' data-wp-bind--hidden="state.isItemHidden" aria-labelledby="rpress-sf-item-title-' . esc_attr( $rpress_sf_id ) . '">';
		$rpress_sf_card .= '<div class="rpress-sf-card-body">';
		$rpress_sf_card .= '<h3 class="rpress-sf-card-title" id="rpress-sf-item-title-' . esc_attr( $rpress_sf_id ) . '">' . esc_html( $rpress_sf_title ) . '</h3>';
		if ( $rpress_sf_desc ) {
			$rpress_sf_excerpt = has_excerpt( $rpress_sf_item ) ? get_post_field( 'post_excerpt', $rpress_sf_id ) : '';
			if ( '' !== trim( (string) $rpress_sf_excerpt ) ) {
				$rpress_sf_card .= '<p class="rpress-sf-card-desc">' . esc_html( wp_trim_words( wp_strip_all_tags( $rpress_sf_excerpt ), 24 ) ) . '</p>';
			}
		}
		$rpress_sf_card .= '<div class="rpress-sf-card-footer">';
		if ( $rpress_sf_price ) {
			$rpress_sf_card .= '<span class="rpress-sf-card-price">' . esc_html( rpress_block_storefront_price_text( $rpress_sf_id ) ) . '</span>';
		}
		// .rpress-menu-v2 gives the Add button the 3.4 skin, the merchant's
		// add-button settings and the active template pack's styling.
		$rpress_sf_card .= '<div class="rpress-sf-card-action' . ( $rpress_sf_old_ui ? '' : ' rpress-menu-v2' ) . '">' . rpress_block_storefront_add_button( $rpress_sf_id ) . '</div>';
		$rpress_sf_card .= '</div></div>';
		if ( $rpress_sf_image && has_post_thumbnail( $rpress_sf_id ) ) {
			$rpress_sf_card .= '<div class="rpress-sf-card-media">' . get_the_post_thumbnail(
				$rpress_sf_id,
				'medium',
				array(
					'class'    => 'rpress-sf-card-img',
					'alt'      => '',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			) . '</div>';
		}
		$rpress_sf_card  .= '</article>';
		$rpress_sf_cards .= $rpress_sf_card;
	}

	$rpress_sf_slug       = sanitize_title( $rpress_sf_term->slug );
	$rpress_sf_sections[] = '<section class="rpress-sf-section" id="rpress-sf-cat-' . esc_attr( $rpress_sf_slug ) . '"'
		. ' data-rpress-sf-section="' . esc_attr( $rpress_sf_slug ) . '"'
		. ' data-wp-context="' . esc_attr( wp_json_encode( array( 'cat' => $rpress_sf_slug, 'titles' => $rpress_sf_titles ) ) ) . '"'
		. ' data-wp-bind--hidden="state.isSectionHidden"'
		. ' aria-labelledby="rpress-sf-cat-title-' . esc_attr( $rpress_sf_slug ) . '">'
		. '<h2 class="rpress-sf-section-title" id="rpress-sf-cat-title-' . esc_attr( $rpress_sf_slug ) . '" tabindex="-1">' . esc_html( $rpress_sf_term->name ) . '</h2>'
		. '<div class="rpress-sf-items">' . $rpress_sf_cards . '</div>'
		. '</section>';
}

$post = $rpress_sf_prev_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
if ( $post instanceof WP_Post ) {
	setup_postdata( $post );
} else {
	wp_reset_postdata();
}

$rpress_sf_wrapper = rpress_block_storefront_wrapper(
	'rpress-sf-item-grid is-layout-' . $rpress_sf_layout,
	array(
		'data-wp-interactive' => RPRESS_STOREFRONT_NAMESPACE,
		'data-wp-context'     => wp_json_encode( array( 'titles' => $rpress_sf_all ) ),
		'data-wp-init'        => 'callbacks.initGrid',
		'style'               => '--rpress-sf-columns:' . $rpress_sf_columns . ';',
	)
);
?>
<div <?php echo $rpress_sf_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( empty( $rpress_sf_sections ) ) : ?>
		<p class="rpress-sf-empty">
			<?php
			/* translators: %s: post type plural label */
			printf( esc_html__( 'No %s found', 'restropress' ), esc_html( rp_get_label_plural() ) );
			?>
		</p>
	<?php else : ?>
		<?php echo implode( '', $rpress_sf_sections ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while built above. ?>
		<p class="rpress-sf-no-results" role="status" hidden data-wp-bind--hidden="!state.hasNoResults" data-wp-text="state.noResultsText"></p>
	<?php endif; ?>
</div>
