<?php
/**
 * Menu Categories block (rpress/category-nav).
 *
 * Jump links to the item grid's category sections. A scrollspy marks the
 * section in view with aria-current; in filter mode a click shows only that
 * category instead of scrolling.
 *
 * @package RPRESS
 * @since   3.5
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

if ( ! rpress_block_storefront_supported() ) {
	echo rpress_block_storefront_fallback( 'category-nav', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

rpress_block_storefront_seed_state();

$rpress_sf_terms = rpress_block_storefront_categories(
	isset( $attributes['categories'] ) ? $attributes['categories'] : '',
	isset( $attributes['categoryExclude'] ) ? $attributes['categoryExclude'] : ''
);
if ( empty( $rpress_sf_terms ) ) {
	return;
}

$rpress_sf_filter      = ! empty( $attributes['filter'] );
$rpress_sf_sticky      = ! isset( $attributes['sticky'] ) || ! empty( $attributes['sticky'] );
$rpress_sf_orientation = isset( $attributes['orientation'] ) && 'vertical' === $attributes['orientation'] ? 'vertical' : 'horizontal';

$rpress_sf_classes = 'rpress-sf-catnav is-' . $rpress_sf_orientation;
if ( $rpress_sf_sticky ) {
	$rpress_sf_classes .= ' is-sticky';
}
if ( $rpress_sf_filter ) {
	$rpress_sf_classes .= ' is-filter';
}

$rpress_sf_wrapper = rpress_block_storefront_wrapper(
	$rpress_sf_classes,
	array(
		'data-wp-interactive' => RPRESS_STOREFRONT_NAMESPACE,
		'data-wp-context'     => wp_json_encode( array( 'filter' => $rpress_sf_filter ) ),
		'data-wp-init'        => 'callbacks.initScrollspy',
		'data-wp-watch'       => 'callbacks.revealActiveCategory',
	)
);
?>
<nav <?php echo $rpress_sf_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php esc_attr_e( 'Menu categories', 'restropress' ); ?>">
	<ul class="rpress-sf-catnav-list">
		<?php if ( $rpress_sf_filter ) : ?>
			<li class="rpress-sf-catnav-item">
				<a class="rpress-sf-catnav-link" href="#" data-wp-context='{"cat":""}' data-wp-on--click="actions.selectCategory" data-wp-bind--aria-current="state.categoryCurrent" data-wp-class--is-active="state.isCategoryActive"><?php esc_html_e( 'All', 'restropress' ); ?></a>
			</li>
		<?php endif; ?>
		<?php foreach ( $rpress_sf_terms as $rpress_sf_term ) : ?>
			<?php $rpress_sf_slug = sanitize_title( $rpress_sf_term->slug ); ?>
			<li class="rpress-sf-catnav-item">
				<a class="rpress-sf-catnav-link" href="#rpress-sf-cat-<?php echo esc_attr( $rpress_sf_slug ); ?>" data-wp-context="<?php echo esc_attr( wp_json_encode( array( 'cat' => $rpress_sf_slug ) ) ); ?>" data-wp-on--click="actions.selectCategory" data-wp-bind--aria-current="state.categoryCurrent" data-wp-class--is-active="state.isCategoryActive"><?php echo esc_html( $rpress_sf_term->name ); ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
