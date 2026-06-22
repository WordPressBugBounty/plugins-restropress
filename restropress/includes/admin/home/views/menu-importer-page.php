<?php
/**
 * Standalone Menu Import screen (Menu Items -> Import).
 *
 * Reuses the same menu-import pane the onboarding wizard uses, without the
 * wizard rail / live preview / step footer. The shared admin-home.js boots in
 * "menu_import" mode (via data-mode on the root) so it skips wizard chrome and
 * redirects to the menu list after publishing.
 *
 * @package RPRESS\Admin\Home
 *
 * @var array $ai_settings
 * @var array $ai_status
 */

defined( 'ABSPATH' ) || exit;

$ai_settings = isset( $ai_settings ) && is_array( $ai_settings ) ? $ai_settings : array();
$ai_status   = isset( $ai_status ) && is_array( $ai_status ) ? $ai_status : array();
$cur_code    = rpress_get_option( 'currency', 'USD' );
$cur_symbol  = function_exists( 'rpress_currency_symbol' ) ? html_entity_decode( rpress_currency_symbol( $cur_code ) ) : '$';
$enable_vegmark = rpress_get_option( 'enable_food_type', false );
$store_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
$menu_list   = admin_url( 'edit.php?post_type=fooditem' );
?>
<div class="wrap rp-admin-scope rp-menu-import-wrap">
	<div class="rp-page-header">
		<div class="rp-page-header-titles">
			<p class="rp-page-eyebrow"><?php esc_html_e( 'Menu Items', 'restropress' ); ?></p>
			<h1 class="wp-heading-inline rp-page-title"><?php esc_html_e( 'Import Menu', 'restropress' ); ?></h1>
			<p class="rp-page-subtitle"><?php esc_html_e( 'Bring your menu in from a spreadsheet, a PDF or photo, or load a sample to start.', 'restropress' ); ?></p>
		</div>
		<div class="rp-page-actions">
			<a href="<?php echo esc_url( $menu_list ); ?>" class="button rp-btn rp-btn-secondary"><?php esc_html_e( 'Back to Menu Items', 'restropress' ); ?></a>
		</div>
	</div>
	<hr class="wp-header-end">

	<div class="rp-onboard rp-admin-scope rp-onboard--import"
		data-mode="menu_import"
		data-cur="<?php echo esc_attr( $cur_symbol ); ?>"
		data-vegmark="<?php echo $enable_vegmark ? '1' : '0'; ?>"
		data-store-name="<?php echo esc_attr( $store_name ); ?>"
		data-menu-list="<?php echo esc_url( $menu_list ); ?>">
		<div class="rp-ob-main">
			<div class="rp-ob-scroll"><div class="rp-ob-wrap">
				<?php include __DIR__ . '/menu-pane.php'; ?>
			</div></div>
			<div class="rp-ob-footer" id="rp-ob-footer">
				<button type="button" class="rp-btn rp-btn-secondary" id="rp-ob-back"><?php esc_html_e( '← Back', 'restropress' ); ?></button>
				<button type="button" class="rp-btn rp-btn-primary" id="rp-ob-next"><?php esc_html_e( 'Continue', 'restropress' ); ?></button>
			</div>
		</div>
	</div>
</div>
