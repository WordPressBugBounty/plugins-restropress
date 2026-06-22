<?php
/**
 * Food Item general data - Section 1 (Image & Type) + Section 2 (Pricing).
 *
 * @package RestroPress/Admin
 */
defined( 'ABSPATH' ) || exit;

$has_variable_prices = $fooditem_object->has_variable_prices();
$thumbnail_id        = get_post_thumbnail_id( $thepostid );
$thumbnail_url       = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'medium' ) : '';
$food_type           = $fooditem_object->get_food_type();

$vegan_options = apply_filters(
	'rpress_vegan_options',
	array(
		''        => esc_html__( 'N/A', 'restropress' ),
		'veg'     => esc_html__( 'Veg', 'restropress' ),
		'non_veg' => esc_html__( 'Non Veg', 'restropress' ),
	)
);
?>

<?php /* ── SECTION 1: Image & Type ── */ ?>
<div class="rp-fi-section" id="general_fooditem_data">
	<div class="rp-fi-section-header">
		<span class="rp-fi-section-number"><?php echo esc_html( RP_FoodItem_Meta_Boxes::next_section_number() ); ?></span>
		<?php esc_html_e( 'Item Image & Type', 'restropress' ); ?>
	</div>
	<div class="rp-fi-section-body">
		<div class="rp-fi-image-row">

			<?php /* Image upload block */ ?>
			<div class="rp-fi-image-block">
				<div class="rp-fi-image-frame" id="rp-fi-image-frame">
					<?php if ( $thumbnail_url ) : ?>
						<img id="rp-fi-image-preview" src="<?php echo esc_url( $thumbnail_url ); ?>" alt="">
					<?php else : ?>
						<img id="rp-fi-image-preview" src="" alt="" style="display:none;">
						<div class="rp-fi-image-empty">
							<span class="dashicons dashicons-format-image"></span>
							<span><?php esc_html_e( 'Click to add image', 'restropress' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
				<div class="rp-fi-image-actions">
					<button type="button" id="rp-fi-set-image" class="button button-secondary">
						<?php echo $thumbnail_url ? esc_html__( 'Change Image', 'restropress' ) : esc_html__( 'Set Food Image', 'restropress' ); ?>
					</button>
					<button type="button" id="rp-fi-remove-image" class="button button-link-delete"<?php echo $thumbnail_url ? '' : ' style="display:none"'; ?>>
						<?php esc_html_e( 'Remove', 'restropress' ); ?>
					</button>
				</div>
			</div>

			<?php /* Food type */ ?>
			<div class="rp-fi-type-block">
				<span class="rp-fi-type-label"><?php esc_html_e( 'Food Type', 'restropress' ); ?></span>
				<div class="rp-fi-type-options admin_vegan_radio">
					<?php foreach ( $vegan_options as $value => $label ) :
						$is_checked = ( $food_type === $value ) || ( $value === '' && $food_type === '' );
						$type_class = '';
						if ( $value === 'veg' ) {
							$type_class = 'is-veg';
						} elseif ( $value === 'non_veg' ) {
							$type_class = 'is-non-veg';
						}
						if ( $is_checked && $type_class ) {
							$type_class .= ' ' . $type_class; // already set, keep active class via JS
						}
					?>
						<label class="rp-fi-type-option<?php echo $is_checked && $type_class ? ' ' . esc_attr( $type_class ) : ''; ?>">
							<input type="radio"
								name="rpress_food_type"
								value="<?php echo esc_attr( $value ); ?>"
								<?php checked( $food_type, $value ); ?>
							>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description" style="margin-top:10px;">
					<?php esc_html_e( 'This label is shown on the menu so customers know what to expect.', 'restropress' ); ?>
				</p>
			</div>

		</div>
		<?php do_action( 'rpress_fooditem_options_general_top' ); ?>
	</div>
</div>

<?php
/**
 * Extension hook - render full custom sections between Image & Type and
 * Description. Plugins should output complete `.rp-fi-section` blocks here,
 * using `RP_FoodItem_Meta_Boxes::next_section_number()` for the heading number.
 */
do_action( 'rpress_fooditem_section_after_image_type', $post );
?>

<?php /* ── SECTION: Description ── */ ?>
<div class="rp-fi-section" id="general_description_data">
	<div class="rp-fi-section-header">
		<span class="rp-fi-section-number"><?php echo esc_html( RP_FoodItem_Meta_Boxes::next_section_number() ); ?></span>
		<?php esc_html_e( 'Description', 'restropress' ); ?>
	</div>
	<div class="rp-fi-section-body">
		<div class="rp-fi-desc-row">

			<?php /* Short description (post_excerpt) */ ?>
			<div class="rp-fi-desc-field">
				<label for="excerpt" class="rp-fi-desc-label">
					<?php esc_html_e( 'Short description', 'restropress' ); ?>
				</label>
				<p class="rp-fi-desc-help">
					<?php esc_html_e( 'Shown on the menu card under the item name. Keep it short - 1–2 lines.', 'restropress' ); ?>
				</p>
				<textarea
					id="excerpt"
					name="excerpt"
					rows="2"
					class="rp-input rp-fi-desc-textarea"
					placeholder="<?php esc_attr_e( 'e.g. Crispy fried chicken in a soft brioche bun', 'restropress' ); ?>"
				><?php echo esc_textarea( $post->post_excerpt ); ?></textarea>
			</div>

			<?php /* Full description (post_content) */ ?>
			<div class="rp-fi-desc-field">
				<label for="rp_fi_content" class="rp-fi-desc-label">
					<?php esc_html_e( 'Full description', 'restropress' ); ?>
				</label>
				<p class="rp-fi-desc-help">
					<?php esc_html_e( 'Shown in the order modal alongside modifiers. Use this for ingredients, allergens, preparation notes, etc.', 'restropress' ); ?>
				</p>
				<?php
				wp_editor(
					$post->post_content,
					'rp_fi_content',
					array(
						'textarea_name' => 'content',
						'textarea_rows' => 6,
						'media_buttons' => false,
						'tinymce'       => array(
							'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
							'toolbar2' => '',
						),
						'quicktags'     => array( 'buttons' => 'strong,em,ul,ol,li,link' ),
					)
				);
				?>
			</div>

		</div>
	</div>
</div>

<?php
/**
 * Extension hook - render full custom sections between Description and
 * Pricing. Plugins should output complete `.rp-fi-section` blocks here,
 * using `RP_FoodItem_Meta_Boxes::next_section_number()` for the heading number.
 */
do_action( 'rpress_fooditem_section_after_description', $post );
?>

<?php /* ── SECTION: Pricing ── */ ?>
<div class="rp-fi-section" id="general_pricing_data">
	<div class="rp-fi-section-header">
		<span class="rp-fi-section-number"><?php echo esc_html( RP_FoodItem_Meta_Boxes::next_section_number() ); ?></span>
		<?php esc_html_e( 'Pricing', 'restropress' ); ?>
		<span class="rp-fi-section-required" title="<?php esc_attr_e( 'Required', 'restropress' ); ?>">*</span>
	</div>
	<div class="rp-fi-section-body">

		<?php /* Pricing mode toggle */ ?>
		<div class="rp-fi-price-mode" id="rp-fi-price-mode">
			<label class="rp-fi-price-mode-option<?php echo ! $has_variable_prices ? ' is-active' : ''; ?>" id="rp-fi-mode-fixed">
				<input type="radio" name="_rp_price_mode" value="fixed" <?php checked( ! $has_variable_prices ); ?>>
				<?php esc_html_e( 'Fixed Price', 'restropress' ); ?>
			</label>
			<label class="rp-fi-price-mode-option<?php echo $has_variable_prices ? ' is-active' : ''; ?>" id="rp-fi-mode-variable">
				<input type="radio" name="_rp_price_mode" value="variable" <?php checked( $has_variable_prices ); ?>>
				<?php esc_html_e( 'Variable Pricing', 'restropress' ); ?>
			</label>
		</div>

		<?php /* Hidden field: 'yes' for variable, empty string for fixed so save logic deletes the meta */ ?>
		<input type="hidden" id="_variable_pricing" name="_variable_pricing" value="<?php echo $has_variable_prices ? 'yes' : ''; ?>">

		<?php /* Fixed price field */ ?>
		<div class="options_group pricing">
			<div class="rp-fi-price-fixed-row rpress_price_field<?php echo $has_variable_prices ? ' hidden' : ''; ?>">
				<label for="rpress_price">
					<?php esc_html_e( 'Price', 'restropress' ); ?>
					(<?php echo esc_html( rpress_currency_symbol() ); ?>)
				</label>
				<input type="number"
					id="rpress_price"
					name="rpress_price"
					step="0.01"
					min="0"
					value="<?php echo esc_attr( $fooditem_object->get_price() ); ?>"
					placeholder="0.00"
					class="rp-input"
					data-type="price"
				>
			</div>

			<?php /* Variable price label */ ?>
			<div class="rp-variable-prices<?php echo ! $has_variable_prices ? ' hidden' : ''; ?>">
				<div style="margin-bottom:12px;">
					<label for="rpress_variable_price_label" style="display:block;margin-bottom:4px;font-weight:600;font-size:12px;color:#50575e;">
						<?php esc_html_e( 'Option Group Label', 'restropress' ); ?>
					</label>
					<input type="text"
						id="rpress_variable_price_label"
						name="rpress_variable_price_label"
						value="<?php echo esc_attr( get_post_meta( $fooditem_object->ID, 'rpress_variable_price_label', true ) ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. Size', 'restropress' ); ?>"
						class="rp-input"
					>
				</div>

				<div class="rp-metaboxes">
					<?php
					if ( $has_variable_prices ) :
						$prices  = (array) $fooditem_object->get_prices();
						$current = 0;
						foreach ( $prices as $price ) :
							include 'html-fooditem-variable-price.php';
							$current++;
						endforeach;
					endif;
					?>
					<button type="button" class="button button-secondary add-new-price">
						<?php esc_html_e( '+ Add Option', 'restropress' ); ?>
					</button>
				</div>
			</div>

			<p class="rp-fi-field-error-msg" id="rp-fi-price-error">
				<?php esc_html_e( 'Please set a price before saving.', 'restropress' ); ?>
			</p>

			<?php do_action( 'rpress_fooditem_options_general_before_pricing' ); ?>
			<?php do_action( 'rpress_fooditem_options_general_after_pricing' ); ?>
			<?php do_action( 'rpress_fooditem_options_general_end' ); ?>
		</div>

	</div>
</div>

<?php /* ── SECTION 5 (Advanced) - SKU + notes ── */ ?>
<div class="rp-fi-section is-advanced" id="general_advanced_data">
	<div class="rp-fi-section-header">
		<?php esc_html_e( 'Advanced Settings', 'restropress' ); ?>
		<span class="rp-fi-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
	</div>
	<div class="rp-fi-section-body">
		<div class="rp-grid rp-grid-2 rp-fi-advanced-grid">
			<?php if ( ! empty( rpress_use_skus() ) ) : ?>
			<div class="rp-fi-field-group">
				<label for="rpress_sku"><?php esc_html_e( 'SKU', 'restropress' ); ?></label>
				<input type="text"
					id="rpress_sku"
					name="rpress_sku"
					value="<?php echo esc_attr( $fooditem_object->get_sku() ); ?>"
					class="rp-input"
					placeholder="<?php esc_attr_e( 'Optional SKU code', 'restropress' ); ?>"
				>
			</div>
			<?php endif; ?>
			<div class="rp-fi-field-group">
				<label for="rpress_product_notes"><?php esc_html_e( 'Admin Notes', 'restropress' ); ?></label>
				<textarea id="rpress_product_notes" name="rpress_product_notes" rows="3" class="rp-input" placeholder="<?php esc_attr_e( 'Internal notes (not shown to customers)', 'restropress' ); ?>"><?php echo esc_textarea( get_post_meta( $thepostid, 'rpress_product_notes', true ) ); ?></textarea>
			</div>
		</div>
		<?php do_action( 'rpress_fooditem_options_general_data' ); ?>
	</div>
</div>
