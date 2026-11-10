<?php
/**
 * Service Bar block (rpress/service-bar).
 *
 * Renders the classic menu's own service toolbar parts ({template}/delivery-option
 * and {template}/time-option), so the delivery/pickup tabs, the date and time
 * popup, the slot check AJAX and the service cookies are the existing ones.
 * The block only adds a wrapper; the closed notice comes from time-option.
 *
 * @package RPRESS
 * @since   3.5
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

if ( ! rpress_block_storefront_supported() ) {
	echo rpress_block_storefront_fallback( 'service-bar', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

rpress_block_storefront_seed_state();

$rpress_sf_template = rpress_get_option( 'template', 'list' );
$rpress_sf_template = in_array( $rpress_sf_template, array( 'list', 'grid' ), true ) ? $rpress_sf_template : 'list';
$rpress_sf_old_ui   = ! empty( rpress_get_option( 'old_ui_ux' ) );

ob_start();
if ( ! $rpress_sf_old_ui ) {
	echo '<div class="rpress-option-col">';
	rpress_get_template_part( $rpress_sf_template . '/delivery-option' );
	echo '</div>';
}
echo '<div class="rpress-option-col rpress-edit-address-wrap"><span class="left-brdr"></span>';
rpress_get_template_part( $rpress_sf_template . '/time-option' );
echo '</div>';
$rpress_sf_inner = trim( (string) ob_get_clean() );

// Not an Interactivity region: rp-frontend.js rewrites this markup (service
// switch, date and time updates), so the runtime must not own it.
$rpress_sf_wrapper = rpress_block_storefront_wrapper( 'rpress-sf-service-bar' );
?>
<div <?php echo $rpress_sf_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="<?php echo esc_attr( $rpress_sf_old_ui ? 'rpress-sf-service-inner' : 'rpress-sf-service-inner rpress-menu-v2' ); ?>">
		<div class="rpress-options-row" role="group" aria-label="<?php esc_attr_e( 'Order type, date and time', 'restropress' ); ?>">
			<?php echo $rpress_sf_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core template parts escape their own output. ?>
		</div>
	</div>
</div>
