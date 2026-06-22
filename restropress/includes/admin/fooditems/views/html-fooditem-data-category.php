<?php
/**
 * Food Item category data - Section 3.
 *
 * @package RestroPress/Admin
 */
defined( 'ABSPATH' ) || exit;

$categories      = rpress_get_categories( array( 'hide_empty' => false ) );
$food_categories = $fooditem_object->get_food_categories();
$category_count  = is_array( $food_categories ) ? count( array_filter( $food_categories ) ) : 0;

$current_tags = get_the_terms( $post->ID, 'fooditem_tag' );
$tag_names    = ( is_array( $current_tags ) && ! is_wp_error( $current_tags ) ) ? wp_list_pluck( $current_tags, 'name' ) : array();

$all_tags = get_terms( array(
	'taxonomy'   => 'fooditem_tag',
	'hide_empty' => false,
) );
if ( is_wp_error( $all_tags ) ) {
	$all_tags = array();
}

// Dietary labels are a controlled vocabulary (seeded in post-types.php), so the
// field offers the existing labels to pick from rather than free-text tagging.
$dietary_supported = taxonomy_exists( 'dietary' );
$all_dietary       = $dietary_supported ? get_terms( array( 'taxonomy' => 'dietary', 'hide_empty' => false ) ) : array();
if ( is_wp_error( $all_dietary ) ) {
	$all_dietary = array();
}
$current_dietary = $dietary_supported ? get_the_terms( $post->ID, 'dietary' ) : array();
$dietary_names   = ( is_array( $current_dietary ) && ! is_wp_error( $current_dietary ) ) ? wp_list_pluck( $current_dietary, 'name' ) : array();
?>

<div class="rp-fi-section" id="category_fooditem_data">
	<div class="rp-fi-section-header">
		<span class="rp-fi-section-number"><?php echo esc_html( RP_FoodItem_Meta_Boxes::next_section_number() ); ?></span>
		<?php esc_html_e( 'Category, Tags &amp; Dietary', 'restropress' ); ?>
		<span class="rp-fi-section-required" title="<?php esc_attr_e( 'Category is required', 'restropress' ); ?>">*</span>
		<?php if ( $category_count > 0 ) : ?>
			<span class="rp-fi-section-badge"><?php echo esc_html( $category_count ); ?></span>
		<?php endif; ?>
	</div>

	<div class="rp-fi-section-body">
		<div class="rp-fi-tax-grid">

		<?php /* ── Category subfield ── */ ?>
		<div class="rp-fi-tax-field">
			<label class="rp-fi-tax-label">
				<?php esc_html_e( 'Category', 'restropress' ); ?>
				<span class="rp-fi-required-mark">*</span>
			</label>
			<p class="rp-fi-tax-help">
				<?php esc_html_e( 'Controls which section this item appears under on the menu (e.g. Pizzas, Drinks).', 'restropress' ); ?>
			</p>

			<div class="options_group rp-category">
				<div class="rp-metabox">
					<div class="rp-fi-category-row" style="margin-bottom:10px;">
						<select name="food_categories[]"
							class="rp-category-select rp-select2"
							multiple="multiple"
							style="flex:1;min-width:0;"
						>
							<?php foreach ( $categories as $category ) :
								echo '<option ' . esc_attr( rp_selected( $category->term_id, $food_categories ) ) . ' value="' . esc_attr( $category->term_id ) . '">' . esc_html( $category->name ) . '</option>';
							endforeach; ?>
						</select>
					</div>

					<p class="rp-fi-field-error-msg" id="rp-fi-category-error">
						<?php esc_html_e( 'Please select at least one category before saving.', 'restropress' ); ?>
					</p>

					<?php /* Inline new-category form */ ?>
					<div class="rp-add-category hidden" style="margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f1;">
						<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
							<input type="text"
								class="rp-input"
								name="rp_category"
								id="rp-category-name"
								placeholder="<?php esc_attr_e( 'New category name', 'restropress' ); ?>"
								style="flex:1;min-width:140px;"
							>
							<select name="_parent_category" id="rp-parent-category" class="rp-input rp-select2" style="min-width:140px;">
								<option value=""><?php esc_html_e( 'No parent', 'restropress' ); ?></option>
								<?php foreach ( $categories as $category ) :
									echo '<option value="' . esc_attr( $category->term_id ) . '">' . esc_html( $category->name ) . '</option>';
								endforeach; ?>
							</select>
							<button type="button" class="button button-primary add-category">
								<?php esc_html_e( 'Save Category', 'restropress' ); ?>
							</button>
						</div>
					</div>

					<div style="margin-top:8px;">
						<button type="button" class="button button-secondary rp_add_category rp-toggle-new-category" style="display:inline-flex;align-items:center;gap:4px;">
							<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span>
							<?php esc_html_e( 'Add New Category', 'restropress' ); ?>
						</button>
					</div>

					<?php do_action( 'rpress_fooditem_categories' ); ?>
				</div>
			</div>
		</div>

		<?php /* ── Tags subfield ── */ ?>
		<div class="rp-fi-tax-field">
			<label for="rp-fi-tags-select" class="rp-fi-tax-label">
				<?php esc_html_e( 'Tags', 'restropress' ); ?>
			</label>
			<p class="rp-fi-tax-help">
				<?php esc_html_e( 'Optional descriptors that help customers find or filter the item (e.g. Spicy, Vegan, Bestseller). Pick from existing or type a new tag and press enter.', 'restropress' ); ?>
			</p>
			<select
				id="rp-fi-tags-select"
				name="tax_input[fooditem_tag][]"
				class="rp-fi-tags-select"
				multiple
				data-placeholder="<?php esc_attr_e( 'Add tags…', 'restropress' ); ?>"
			>
				<?php
				$rendered = array();
				foreach ( $all_tags as $tag ) {
					$is_selected = in_array( $tag->name, $tag_names, true );
					echo '<option value="' . esc_attr( $tag->name ) . '"' . selected( $is_selected, true, false ) . '>' . esc_html( $tag->name ) . '</option>';
					$rendered[] = $tag->name;
				}
				// Ensure any currently-attached tag missing from the term list is still selected.
				foreach ( $tag_names as $name ) {
					if ( ! in_array( $name, $rendered, true ) ) {
						echo '<option value="' . esc_attr( $name ) . '" selected>' . esc_html( $name ) . '</option>';
					}
				}
				?>
			</select>
		</div>

		<?php /* ── Dietary subfield ── */ ?>
		<?php if ( $dietary_supported ) : ?>
			<div class="rp-fi-tax-field">
				<label for="rp-fi-dietary-select" class="rp-fi-tax-label">
					<?php esc_html_e( 'Dietary Labels', 'restropress' ); ?>
				</label>
				<p class="rp-fi-tax-help">
					<?php esc_html_e( 'Standard diet labels shown to customers (e.g. Vegetarian, Vegan, Gluten-free, Halal). Pick all that apply.', 'restropress' ); ?>
				</p>
				<select
					id="rp-fi-dietary-select"
					name="tax_input[dietary][]"
					class="rp-fi-dietary-select rp-select2"
					multiple
					data-placeholder="<?php esc_attr_e( 'Select dietary labels…', 'restropress' ); ?>"
				>
					<?php
					$rendered_diet = array();
					foreach ( $all_dietary as $dietary_term ) {
						$is_selected = in_array( $dietary_term->name, $dietary_names, true );
						echo '<option value="' . esc_attr( $dietary_term->name ) . '"' . selected( $is_selected, true, false ) . '>' . esc_html( $dietary_term->name ) . '</option>';
						$rendered_diet[] = $dietary_term->name;
					}
					// Keep any currently-attached label that is no longer in the term list selected.
					foreach ( $dietary_names as $name ) {
						if ( ! in_array( $name, $rendered_diet, true ) ) {
							echo '<option value="' . esc_attr( $name ) . '" selected>' . esc_html( $name ) . '</option>';
						}
					}
					?>
				</select>
			</div>
		<?php endif; ?>

		</div>
	</div>

	<?php do_action( 'rpress_fooditem_options_category_data' ); ?>
</div>
