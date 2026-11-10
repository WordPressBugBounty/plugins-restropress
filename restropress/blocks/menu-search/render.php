<?php
/**
 * Menu Search block (rpress/menu-search).
 *
 * Filters the item grid live through the shared store: cards whose name
 * does not match hide, empty categories hide with them.
 *
 * @package RPRESS
 * @since   3.5
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

if ( ! rpress_block_storefront_supported() ) {
	echo rpress_block_storefront_fallback( 'menu-search', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

rpress_block_storefront_seed_state();

$rpress_sf_placeholder = ! empty( $attributes['placeholder'] ) ? $attributes['placeholder'] : __( 'Search menu items...', 'restropress' );
$rpress_sf_input_id    = wp_unique_id( 'rpress-sf-search-' );

$rpress_sf_wrapper = rpress_block_storefront_wrapper(
	'rpress-sf-search',
	array( 'data-wp-interactive' => RPRESS_STOREFRONT_NAMESPACE )
);
?>
<div <?php echo $rpress_sf_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<form role="search" class="rpress-sf-search-form" data-wp-on--submit="actions.preventSubmit">
		<label class="screen-reader-text" for="<?php echo esc_attr( $rpress_sf_input_id ); ?>"><?php esc_html_e( 'Search menu items', 'restropress' ); ?></label>
		<input
			type="search"
			id="<?php echo esc_attr( $rpress_sf_input_id ); ?>"
			class="rpress-sf-search-input"
			placeholder="<?php echo esc_attr( $rpress_sf_placeholder ); ?>"
			autocomplete="off"
			spellcheck="false"
			data-wp-bind--value="state.query"
			data-wp-on--input="actions.setQuery"
			data-wp-on--keydown="actions.searchKeydown"
		/>
		<button type="button" class="rpress-sf-search-clear" hidden data-wp-bind--hidden="!state.query" data-wp-on--click="actions.clearQuery" aria-label="<?php esc_attr_e( 'Clear search', 'restropress' ); ?>">
			<span aria-hidden="true">&times;</span>
		</button>
	</form>
</div>
