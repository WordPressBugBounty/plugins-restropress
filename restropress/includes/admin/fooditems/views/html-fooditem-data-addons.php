<?php
/**
 * Food Item Addons data - Section 4.
 *
 * @package RestroPress/Admin
 */
defined( 'ABSPATH' ) || exit;

$addon_categories = rpress_get_addons();
$addon_types      = rpress_get_addon_types();
$post_id          = get_the_ID();

// Count total addon groups already attached.
$existing_addons  = get_post_meta( $post_id, '_addon_items', true );
$addon_group_count = ( is_array( $existing_addons ) && ! empty( $existing_addons ) ) ? count( $existing_addons ) : 0;
?>

<div class="rp-fi-section" id="addons_fooditem_data">
	<div class="rp-fi-section-header">
		<span class="rp-fi-section-number"><?php echo esc_html( RP_FoodItem_Meta_Boxes::next_section_number() ); ?></span>
		<?php esc_html_e( 'Add-ons', 'restropress' ); ?>
		<span style="font-size:11px;font-weight:normal;color:#646970;margin-left:4px;">
			<?php esc_html_e( '(Optional)', 'restropress' ); ?>
		</span>
		<?php if ( $addon_group_count > 0 ) : ?>
			<span class="rp-fi-section-badge" id="rp-fi-addon-group-count">
				<?php echo esc_html(
					sprintf(
						/* translators: %d: number of add-on groups */
						_n( '%d group', '%d groups', $addon_group_count, 'restropress' ),
						$addon_group_count
					)
				); ?>
			</span>
		<?php else : ?>
			<span class="rp-fi-section-badge" id="rp-fi-addon-group-count" style="display:none;"></span>
		<?php endif; ?>
		<button type="button" class="button button-secondary create-addon" style="margin-left:auto;font-size:12px;padding:2px 10px;height:auto;">
			<span class="dashicons dashicons-plus-alt" style="font-size:14px;width:14px;height:14px;margin-top:1px;"></span>
			<?php esc_html_e( 'Create New Add-on Group', 'restropress' ); ?>
		</button>
	</div>

	<div class="rp-fi-section-body" style="padding-bottom:8px;">
		<p class="rp-fi-section-help">
			<?php esc_html_e( 'Add-on groups let you offer extras (like toppings or sides) that customers can add to this item. In POS systems they\'re sometimes called "modifier groups".', 'restropress' ); ?>
		</p>

		<div class="rp-addons rp-metaboxes">
			<?php include 'html-fooditem-addon.php'; ?>
		</div>

		<div class="rp-fi-addons-actions">
			<button type="button"
				data-item-id="<?php echo esc_attr( $post_id ); ?>"
				class="button button-primary add-new-addon"
			>
				<span class="dashicons dashicons-plus" style="font-size:14px;width:14px;height:14px;margin-top:1px;"></span>
				<?php esc_html_e( 'Add Existing Add-on Group', 'restropress' ); ?>
			</button>
			<span class="description" style="font-size:12px;color:#646970;">
				<?php esc_html_e( 'Pick from add-on groups you\'ve already set up, or create a new one above.', 'restropress' ); ?>
			</span>
		</div>
	</div>

	<?php do_action( 'rpress_fooditem_options_addons_data' ); ?>
</div>
