<?php
/**
 * Discount Codes
 *
 * @package     RPRESS
 * @subpackage  Admin/Discounts
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Renders the Discount Pages Admin Page
 *
 * @since 1.4
 * @author Magnigenie
 * @return void
*/
function rpress_discounts_page() {
	$action = isset( $_GET['rpress-action'] ) ? sanitize_key( wp_unslash( $_GET['rpress-action'] ) ) : '';

	if ( 'edit_discount' === $action || 'add_discount' === $action ) {
		$is_edit = 'edit_discount' === $action;
		?>
		<div class="wrap rp-admin-scope rp-discounts-page rp-discount-form-page">
			<div class="rp-page-header rp-discounts-header">
				<div class="rp-page-header-titles">
					<h1 class="wp-heading-inline rp-page-title">
						<?php echo esc_html( $is_edit ? __( 'Edit Discount', 'restropress' ) : __( 'Add New Discount', 'restropress' ) ); ?>
					</h1>
					<p class="rp-page-subtitle"><?php esc_html_e( 'Create and manage promotional codes for restaurant ordering.', 'restropress' ); ?></p>
				</div>
				<div class="rp-page-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-discounts' ) ); ?>" class="button button-secondary rp-btn rp-btn-secondary">
						<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
						<?php esc_html_e( 'Back to Discounts', 'restropress' ); ?>
					</a>
				</div>
			</div>
			<hr class="wp-header-end">
			<?php
			if ( $is_edit ) {
				require_once RP_PLUGIN_DIR . 'includes/admin/discounts/edit-discount.php';
			} else {
				require_once RP_PLUGIN_DIR . 'includes/admin/discounts/add-discount.php';
			}
			?>
		</div>
		<?php
		return;
	}

	require_once RP_PLUGIN_DIR . 'includes/admin/discounts/class-discount-codes-table.php';
	$discount_codes_table = new RPRESS_Discount_Codes_Table();
	$discount_codes_table->prepare_items();
	$has_discount_rows = $discount_codes_table->has_items();
	?>
	<div class="wrap rp-admin-scope rp-discounts-page rp-discounts-list-page">
		<div class="rp-page-header rp-discounts-header">
			<div class="rp-page-header-titles">
				<h1 class="wp-heading-inline rp-page-title"><?php esc_html_e( 'Discount Codes', 'restropress' ); ?></h1>
				<p class="rp-page-subtitle"><?php esc_html_e( 'Manage promotions, coupon status, usage limits, and expiration dates.', 'restropress' ); ?></p>
			</div>
			<?php if ( $has_discount_rows ) : ?>
				<div class="rp-page-actions">
					<a href="<?php echo esc_url( add_query_arg( array( 'rpress-action' => 'add_discount' ) ) ); ?>" class="button button-primary rp-btn rp-btn-primary">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<?php esc_html_e( 'Add New', 'restropress' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<hr class="wp-header-end">
		<?php do_action( 'rpress_discounts_page_top' ); ?>
		<form id="rpress-discounts-filter" class="rp-list-table-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php?page=rpress-discounts' ) ); ?>">
			<input type="hidden" name="page" value="rpress-discounts" />
			<div class="rp-table-toolbar rp-list-table-toolbar">
				<div class="rp-table-toolbar-primary">
					<?php $discount_codes_table->views(); ?>
				</div>
			</div>
			<?php $discount_codes_table->display(); ?>
		</form>
		<?php do_action( 'rpress_discounts_page_bottom' ); ?>
	</div>
	<?php
}
