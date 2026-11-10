<?php
/**
 * Allergens admin: the food item editor section and the badge code field on
 * the Allergens term screens.
 *
 * @package RestroPress/Features/Allergens
 * @since 3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the "Allergens" section in the food item editor, right after
 * "Category, Tags & Dietary". Mirrors the Dietary Labels field: a select2
 * multi-select posted through tax_input, so WordPress saves it with its own
 * capability checks. Values are term IDs (the taxonomy uses the checkbox
 * sanitizer), so names with commas are safe.
 *
 * @since 3.5
 * @param WP_Post $post Food item being edited.
 * @return void
 */
function rpress_allergens_editor_section( $post ) {
	if ( ! $post instanceof WP_Post || ! taxonomy_exists( RPRESS_ALLERGEN_TAXONOMY ) ) {
		return;
	}

	$all = get_terms(
		array(
			'taxonomy'   => RPRESS_ALLERGEN_TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	$all          = is_wp_error( $all ) ? array() : $all;
	$contains_ids = array_map( 'intval', wp_list_pluck( rpress_get_fooditem_allergens( $post->ID ), 'term_id' ) );
	$trace_ids    = rpress_get_fooditem_allergen_trace_ids( $post->ID );
	$count        = count( $contains_ids );
	$manage_url   = admin_url( 'edit-tags.php?taxonomy=' . RPRESS_ALLERGEN_TAXONOMY . '&post_type=fooditem' );
	$section_num  = class_exists( 'RP_FoodItem_Meta_Boxes' ) ? RP_FoodItem_Meta_Boxes::next_section_number() : '';
	?>
	<div class="rp-fi-section" id="allergens_fooditem_data">
		<div class="rp-fi-section-header">
			<span class="rp-fi-section-number"><?php echo esc_html( $section_num ); ?></span>
			<?php esc_html_e( 'Allergens', 'restropress' ); ?>
			<?php if ( $count > 0 ) : ?>
				<span class="rp-fi-section-badge"><?php echo esc_html( $count ); ?></span>
			<?php endif; ?>
		</div>
		<div class="rp-fi-section-body">
			<div class="rp-fi-tax-grid">
				<div class="rp-fi-tax-field">
					<label for="rp-fi-allergens-select" class="rp-fi-tax-label">
						<?php esc_html_e( 'Contains', 'restropress' ); ?>
					</label>
					<p class="rp-fi-tax-help">
						<?php esc_html_e( 'Allergens in this item. Shown to customers on the menu and in the item popup, and used by the "Hide items containing" filter.', 'restropress' ); ?>
					</p>
					<input type="hidden" name="tax_input[<?php echo esc_attr( RPRESS_ALLERGEN_TAXONOMY ); ?>][]" value="">
					<select
						id="rp-fi-allergens-select"
						name="tax_input[<?php echo esc_attr( RPRESS_ALLERGEN_TAXONOMY ); ?>][]"
						class="rp-fi-allergens-select rp-select2"
						multiple
						style="width:100%;"
						data-placeholder="<?php esc_attr_e( 'Select allergens...', 'restropress' ); ?>"
					>
						<?php foreach ( $all as $term ) : ?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>"<?php selected( in_array( (int) $term->term_id, $contains_ids, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="rp-fi-tax-field">
					<label for="rp-fi-allergen-traces-select" class="rp-fi-tax-label">
						<?php esc_html_e( 'May contain traces of', 'restropress' ); ?>
					</label>
					<p class="rp-fi-tax-help">
						<?php
						printf(
							/* translators: %s: link to the Allergens admin screen */
							esc_html__( 'Optional cross-contamination warning. Need another allergen? %s.', 'restropress' ),
							'<a href="' . esc_url( $manage_url ) . '">' . esc_html__( 'Manage allergens', 'restropress' ) . '</a>'
						);
						?>
					</p>
					<input type="hidden" name="rpress_allergen_traces[]" value="">
					<select
						id="rp-fi-allergen-traces-select"
						name="rpress_allergen_traces[]"
						class="rp-fi-allergen-traces-select rp-select2"
						multiple
						style="width:100%;"
						data-placeholder="<?php esc_attr_e( 'Select allergens...', 'restropress' ); ?>"
					>
						<?php foreach ( $all as $term ) : ?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>"<?php selected( in_array( (int) $term->term_id, $trace_ids, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'rpress_fooditem_section_after_category', 'rpress_allergens_editor_section' );

/**
 * Save "May contain traces of". Only runs for a real editor submit (the field
 * and the editor nonce must be present), so quick edit and imports never wipe
 * it. The "contains" list is saved by WordPress through tax_input.
 *
 * @since 3.5
 * @param int $post_id Post ID.
 * @return void
 */
function rpress_allergens_save_traces( $post_id ) {
	if ( ! isset( $_POST['rpress_allergen_traces'], $_POST['restropress_meta_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['restropress_meta_nonce'] ) ), 'restropress_save_data' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['rpress_allergen_traces'] ) ) ) ) );
	$ids = array_values(
		array_filter(
			$ids,
			function ( $id ) {
				$term = get_term( $id, RPRESS_ALLERGEN_TAXONOMY );
				return $term && ! is_wp_error( $term );
			}
		)
	);

	if ( $ids ) {
		update_post_meta( $post_id, RPRESS_ALLERGEN_TRACES_META, $ids );
	} else {
		delete_post_meta( $post_id, RPRESS_ALLERGEN_TRACES_META );
	}
}
add_action( 'save_post_fooditem', 'rpress_allergens_save_traces' );

/**
 * Badge code field on the "Add New Allergen" form.
 *
 * @since 3.5
 * @return void
 */
function rpress_allergens_term_add_field() {
	?>
	<div class="form-field term-rpress-allergen-code-wrap">
		<label for="rpress_allergen_code"><?php esc_html_e( 'Badge code', 'restropress' ); ?></label>
		<input name="rpress_allergen_code" id="rpress_allergen_code" type="text" value="" maxlength="3" size="4">
		<p><?php esc_html_e( 'One to three letters shown in the round menu badge (e.g. MI for Milk). Leave empty to use the first two letters of the name.', 'restropress' ); ?></p>
	</div>
	<?php
}
add_action( RPRESS_ALLERGEN_TAXONOMY . '_add_form_fields', 'rpress_allergens_term_add_field' );

/**
 * Badge code field on the "Edit Allergen" form.
 *
 * @since 3.5
 * @param WP_Term $term Term being edited.
 * @return void
 */
function rpress_allergens_term_edit_field( $term ) {
	$code = (string) get_term_meta( $term->term_id, 'rpress_allergen_code', true );
	?>
	<tr class="form-field term-rpress-allergen-code-wrap">
		<th scope="row"><label for="rpress_allergen_code"><?php esc_html_e( 'Badge code', 'restropress' ); ?></label></th>
		<td>
			<input name="rpress_allergen_code" id="rpress_allergen_code" type="text" value="<?php echo esc_attr( $code ); ?>" maxlength="3" size="4">
			<p class="description"><?php esc_html_e( 'One to three letters shown in the round menu badge (e.g. MI for Milk). Leave empty to use the first two letters of the name.', 'restropress' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( RPRESS_ALLERGEN_TAXONOMY . '_edit_form_fields', 'rpress_allergens_term_edit_field' );

/**
 * Save the badge code from the term add/edit forms. WordPress has already
 * checked the form nonce by the time these hooks fire; check it again here
 * because created_/edited_ hooks also run for programmatic inserts.
 *
 * @since 3.5
 * @param int $term_id Term ID.
 * @return void
 */
function rpress_allergens_term_save_code( $term_id ) {
	if ( ! isset( $_POST['rpress_allergen_code'] ) ) {
		return;
	}
	$nonce_ok = ( isset( $_POST['_wpnonce_add-tag'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_add-tag'] ) ), 'add-tag' ) )
		|| ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-tag_' . $term_id ) );
	$tax      = get_taxonomy( RPRESS_ALLERGEN_TAXONOMY );
	if ( ! $nonce_ok || ! $tax || ! current_user_can( $tax->cap->edit_terms ) ) {
		return;
	}

	$code = sanitize_text_field( wp_unslash( $_POST['rpress_allergen_code'] ) );
	$code = preg_replace( '/[^\p{L}\p{N}]/u', '', $code );
	$code = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( (string) $code, 0, 3 ) ) : strtoupper( substr( (string) $code, 0, 3 ) );

	if ( '' === $code ) {
		delete_term_meta( $term_id, 'rpress_allergen_code' );
	} else {
		update_term_meta( $term_id, 'rpress_allergen_code', $code );
	}
}
add_action( 'created_' . RPRESS_ALLERGEN_TAXONOMY, 'rpress_allergens_term_save_code' );
add_action( 'edited_' . RPRESS_ALLERGEN_TAXONOMY, 'rpress_allergens_term_save_code' );

/**
 * Show the badge code in the Allergens term list.
 *
 * @since 3.5
 * @param array $columns Columns.
 * @return array
 */
function rpress_allergens_term_columns( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'name' === $key ) {
			$out['rpress_allergen_code'] = __( 'Badge', 'restropress' );
		}
	}
	return $out;
}
add_filter( 'manage_edit-' . RPRESS_ALLERGEN_TAXONOMY . '_columns', 'rpress_allergens_term_columns' );

/**
 * Badge column content.
 *
 * @since 3.5
 * @param string $content Column content.
 * @param string $column  Column name.
 * @param int    $term_id Term ID.
 * @return string
 */
function rpress_allergens_term_column_content( $content, $column, $term_id ) {
	if ( 'rpress_allergen_code' !== $column ) {
		return $content;
	}
	$term = get_term( $term_id, RPRESS_ALLERGEN_TAXONOMY );
	return ( $term && ! is_wp_error( $term ) ) ? esc_html( rpress_allergen_code( $term ) ) : $content;
}
add_filter( 'manage_' . RPRESS_ALLERGEN_TAXONOMY . '_custom_column', 'rpress_allergens_term_column_content', 10, 3 );
