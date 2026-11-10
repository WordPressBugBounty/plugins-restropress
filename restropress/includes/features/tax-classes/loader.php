<?php
/**
 * Tax classes: admin UI, per-order tax breakdown and its display.
 *
 * The tax maths lives in includes/tax-functions.php and the cart; this module
 * adds the class editor (Settings > Taxes), class pickers on food categories
 * and food items, stores a per-class breakdown on each order, and shows it on
 * checkout, receipts, emails and the admin order screen when an order mixes
 * tax rates.
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings > Taxes: classes editor and the Standard class code.
 */
add_filter( 'rpress_settings_taxes', function ( $settings ) {
	if ( ! isset( $settings['main'] ) || ! is_array( $settings['main'] ) ) {
		return $settings;
	}
	$extra = array(
		'tax_class_standard_code' => array(
			'id'   => 'tax_class_standard_code',
			'name' => esc_html__( 'Standard tax code', 'restropress' ),
			'desc' => esc_html__( 'Optional code printed with the Standard tax on receipts (for example an HSN/SAC code).', 'restropress' ),
			'type' => 'text',
			'size' => 'medium',
		),
		'tax_classes_editor'      => array(
			'id'   => 'tax_classes_editor',
			'name' => esc_html__( 'Tax classes', 'restropress' ),
			'type' => 'hook',
		),
	);
	// Place right after the Standard rate field.
	$main = array();
	foreach ( $settings['main'] as $key => $field ) {
		$main[ $key ] = $field;
		if ( 'tax_rate' === $key ) {
			$main += $extra;
		}
	}
	$settings['main'] = $main + $extra;
	return $settings;
} );

add_action( 'rpress_tax_classes_editor', 'rpress_tax_classes_editor_render' );
/**
 * Render the tax classes table.
 *
 * @return void
 */
function rpress_tax_classes_editor_render() {
	$classes       = rpress_get_tax_classes();
	$standard_rate = rpress_get_option( 'tax_rate', '' );
	$standard_code = rpress_get_option( 'tax_class_standard_code', '' );
	unset( $classes['standard'] );
	?>
	<style>
		.rpress-tc { max-width: 760px; }
		.rpress-tc-intro { margin: 0 0 12px; }
		.rpress-tc-box { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; overflow: hidden; }
		.rpress-tc-row { display: grid; grid-template-columns: minmax(140px, 1fr) 110px 150px 40px; gap: 12px; align-items: center; padding: 10px 14px; border-top: 1px solid #f0f0f1; }
		.rpress-tc-head { border-top: 0; background: #f6f7f7; padding-top: 9px; padding-bottom: 9px; font-size: 12px; font-weight: 600; color: #50575e; text-transform: uppercase; letter-spacing: .03em; }
		.rpress-tc-row input[type="text"], .rpress-tc-row input[type="number"] { width: 100%; max-width: none; margin: 0; min-height: 34px; }
		.rpress-tc-rate { position: relative; }
		.rpress-tc-rate input[type="number"] { padding-right: 28px; }
		.rpress-tc-rate span { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #787c82; pointer-events: none; }
		.rpress-tc-standard { background: #fbfbfc; color: #50575e; }
		.rpress-tc-standard strong { color: #1d2327; }
		.rpress-tc-badge { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 10px; background: #f0f0f1; font-size: 11px; color: #50575e; vertical-align: 1px; }
		.rpress-tc-muted { color: #787c82; }
		.rpress-tc-remove { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; border: 1px solid transparent; border-radius: 4px; background: none; color: #787c82; cursor: pointer; }
		.rpress-tc-remove:hover, .rpress-tc-remove:focus { color: #b32d2e; border-color: #dcdcde; background: #fff; }
		.rpress-tc-remove .dashicons { font-size: 18px; width: 18px; height: 18px; }
		.rpress-tc-row.is-deleted input { opacity: .45; text-decoration: line-through; pointer-events: none; }
		.rpress-tc-undo { display: none; padding: 0; border: 0; background: none; color: #2271b1; text-decoration: underline; cursor: pointer; font-size: 12px; }
		.rpress-tc-row.is-deleted .rpress-tc-undo { display: inline; }
		.rpress-tc-row.is-deleted .rpress-tc-remove { display: none; }
		.rpress-tc-empty { padding: 16px 14px; border-top: 1px solid #f0f0f1; color: #787c82; }
		.rpress-tc-foot { padding: 12px 14px; border-top: 1px solid #f0f0f1; background: #fbfbfc; }
		.rpress-tc-foot .button .dashicons { font-size: 16px; width: 16px; height: 16px; margin: 4px 4px 0 -2px; }
		@media (max-width: 782px) {
			.rpress-tc-head { display: none; }
			.rpress-tc-row { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 36px; gap: 8px; }
			.rpress-tc-row > :first-child { grid-column: 1 / -1; }
		}
	</style>
	<div class="rpress-tc">
		<p class="description rpress-tc-intro"><?php esc_html_e( 'Use tax classes when some items are taxed at a different rate (for example drinks). Assign a class to a food category, or to a single food item. Items without a class use the Standard rate.', 'restropress' ); ?></p>
		<div class="rpress-tc-box">
			<div class="rpress-tc-row rpress-tc-head" aria-hidden="true">
				<span><?php esc_html_e( 'Class name', 'restropress' ); ?></span>
				<span><?php esc_html_e( 'Rate', 'restropress' ); ?></span>
				<span><?php esc_html_e( 'Tax code', 'restropress' ); ?></span>
				<span></span>
			</div>
			<div class="rpress-tc-row rpress-tc-standard">
				<span><strong><?php esc_html_e( 'Standard', 'restropress' ); ?></strong><span class="rpress-tc-badge"><?php esc_html_e( 'Default', 'restropress' ); ?></span></span>
				<span><?php echo '' === (string) $standard_rate ? '<span class="rpress-tc-muted">' . esc_html__( 'Not set', 'restropress' ) . '</span>' : esc_html( (float) $standard_rate . '%' ); ?></span>
				<span><?php echo '' === (string) $standard_code ? '<span class="rpress-tc-muted">&ndash;</span>' : esc_html( $standard_code ); ?></span>
				<span class="rpress-tc-muted" title="<?php esc_attr_e( 'Set with Tax rate and Standard tax code above', 'restropress' ); ?>"><span class="dashicons dashicons-lock" aria-hidden="true"></span></span>
			</div>
			<div class="rpress-tc-list">
				<?php foreach ( $classes as $slug => $class ) : ?>
					<div class="rpress-tc-row">
						<span><input type="text" aria-label="<?php esc_attr_e( 'Class name', 'restropress' ); ?>" name="rpress_tax_classes[<?php echo esc_attr( $slug ); ?>][name]" value="<?php echo esc_attr( $class['name'] ); ?>" /></span>
						<span class="rpress-tc-rate"><input type="number" step="0.0001" min="0" max="100" aria-label="<?php esc_attr_e( 'Rate in percent', 'restropress' ); ?>" name="rpress_tax_classes[<?php echo esc_attr( $slug ); ?>][rate]" value="<?php echo esc_attr( $class['rate'] ); ?>" /><span>%</span></span>
						<span><input type="text" aria-label="<?php esc_attr_e( 'Tax code', 'restropress' ); ?>" placeholder="<?php esc_attr_e( 'Optional', 'restropress' ); ?>" name="rpress_tax_classes[<?php echo esc_attr( $slug ); ?>][code]" value="<?php echo esc_attr( $class['code'] ); ?>" /></span>
						<span>
							<input type="hidden" class="rpress-tc-delete" name="rpress_tax_classes[<?php echo esc_attr( $slug ); ?>][delete]" value="" />
							<button type="button" class="rpress-tc-remove" title="<?php esc_attr_e( 'Delete class', 'restropress' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Delete class', 'restropress' ); ?></span></button>
							<button type="button" class="rpress-tc-undo"><?php esc_html_e( 'Undo', 'restropress' ); ?></button>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="rpress-tc-empty"<?php echo $classes ? ' hidden' : ''; ?>><?php esc_html_e( 'No extra tax classes yet. Everything is taxed at the Standard rate.', 'restropress' ); ?></div>
			<div class="rpress-tc-foot">
				<button type="button" class="button rpress-tc-add"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add tax class', 'restropress' ); ?></button>
			</div>
		</div>
		<template id="rpress-tc-template">
			<div class="rpress-tc-row rpress-tc-new">
				<span><input type="text" aria-label="<?php esc_attr_e( 'Class name', 'restropress' ); ?>" name="rpress_tax_classes_new[__i__][name]" placeholder="<?php esc_attr_e( 'For example Beverages', 'restropress' ); ?>" /></span>
				<span class="rpress-tc-rate"><input type="number" step="0.0001" min="0" max="100" aria-label="<?php esc_attr_e( 'Rate in percent', 'restropress' ); ?>" name="rpress_tax_classes_new[__i__][rate]" placeholder="0" /><span>%</span></span>
				<span><input type="text" aria-label="<?php esc_attr_e( 'Tax code', 'restropress' ); ?>" placeholder="<?php esc_attr_e( 'Optional', 'restropress' ); ?>" name="rpress_tax_classes_new[__i__][code]" /></span>
				<span><button type="button" class="rpress-tc-remove" title="<?php esc_attr_e( 'Remove', 'restropress' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'restropress' ); ?></span></button></span>
			</div>
		</template>
	</div>
	<input type="hidden" name="rpress_tax_classes_submitted" value="1" />
	<script>
	( function () {
		var root = document.querySelector( '.rpress-tc' );
		if ( ! root ) { return; }
		var list = root.querySelector( '.rpress-tc-list' ), empty = root.querySelector( '.rpress-tc-empty' ), tpl = document.getElementById( 'rpress-tc-template' ), n = 0;
		function sync() { empty.hidden = list.children.length > 0; }
		root.querySelector( '.rpress-tc-add' ).addEventListener( 'click', function () {
			var wrap = document.createElement( 'div' );
			wrap.innerHTML = tpl.innerHTML.replace( /__i__/g, String( n++ ) );
			var row = wrap.firstElementChild;
			list.appendChild( row );
			sync();
			row.querySelector( 'input' ).focus();
		} );
		list.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( 'button' );
			if ( ! btn ) { return; }
			var row = btn.closest( '.rpress-tc-row' ), del = row.querySelector( '.rpress-tc-delete' );
			if ( btn.classList.contains( 'rpress-tc-remove' ) ) {
				if ( ! del ) { row.remove(); sync(); return; }
				del.value = '1'; row.classList.add( 'is-deleted' );
				row.querySelector( '.rpress-tc-undo' ).focus();
			} else if ( btn.classList.contains( 'rpress-tc-undo' ) ) {
				del.value = ''; row.classList.remove( 'is-deleted' );
				row.querySelector( '.rpress-tc-remove' ).focus();
			}
		} );
	} )();
	</script>
	<?php
}

/**
 * Save the classes table when the Taxes tab is saved. options.php has already
 * checked the settings nonce and the manage_shop_settings capability.
 */
add_filter( 'rpress_settings_taxes_sanitize', function ( $input ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by options.php for the rpress_settings group.
	if ( empty( $_POST['rpress_tax_classes_submitted'] ) || ! current_user_can( 'manage_shop_settings' ) ) {
		return $input;
	}
	$saved = array();
	$rows  = isset( $_POST['rpress_tax_classes'] ) && is_array( $_POST['rpress_tax_classes'] ) ? wp_unslash( $_POST['rpress_tax_classes'] ) : array();
	foreach ( $rows as $slug => $row ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug || 'standard' === $slug || ! empty( $row['delete'] ) ) {
			continue;
		}
		$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
		if ( '' === $name ) {
			continue;
		}
		$saved[ $slug ] = rpress_tax_class_clean_row( $name, $row );
	}
	$new = isset( $_POST['rpress_tax_classes_new'] ) && is_array( $_POST['rpress_tax_classes_new'] ) ? wp_unslash( $_POST['rpress_tax_classes_new'] ) : array();
	foreach ( $new as $row ) {
		$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
		if ( '' === $name ) {
			continue;
		}
		$slug = sanitize_key( sanitize_title( $name ) );
		if ( '' === $slug || 'standard' === $slug ) {
			$slug = 'class-' . wp_generate_password( 6, false, false );
		}
		$base = $slug;
		for ( $n = 2; isset( $saved[ $slug ] ); $n++ ) {
			$slug = $base . '-' . $n;
		}
		$saved[ $slug ] = rpress_tax_class_clean_row( $name, $row );
	}
	// phpcs:enable
	update_option( 'rpress_tax_classes', $saved, false );
	return $input;
} );

/**
 * Normalise one tax class row.
 *
 * @param string $name Class name.
 * @param array  $row  Posted row.
 * @return array
 */
function rpress_tax_class_clean_row( $name, $row ) {
	$rate = isset( $row['rate'] ) ? (float) str_replace( ',', '.', (string) $row['rate'] ) : 0.0;
	return array(
		'name' => $name,
		'rate' => max( 0.0, min( 100.0, $rate ) ),
		'code' => isset( $row['code'] ) ? sanitize_text_field( $row['code'] ) : '',
	);
}

/**
 * Tax class <select> markup.
 *
 * @param string $name     Field name.
 * @param string $selected Selected slug ('' = inherit).
 * @param string $inherit  Label of the empty option.
 * @return string
 */
function rpress_tax_class_select( $name, $selected, $inherit ) {
	$html = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
	$html .= '<option value="">' . esc_html( $inherit ) . '</option>';
	foreach ( rpress_get_tax_classes() as $slug => $class ) {
		/* translators: 1: tax class name, 2: rate in percent */
		$label = sprintf( __( '%1$s (%2$s%%)', 'restropress' ), $class['name'], 'standard' === $slug ? (float) rpress_get_option( 'tax_rate', 0 ) : $class['rate'] );
		$html .= '<option value="' . esc_attr( $slug ) . '"' . selected( $selected, $slug, false ) . '>' . esc_html( $label ) . '</option>';
	}
	return $html . '</select>';
}

/**
 * Food category: tax class field (add + edit forms).
 */
add_action( 'food-category_add_form_fields', function () {
	if ( ! rpress_use_taxes() ) {
		return;
	}
	wp_nonce_field( 'rpress_term_tax_class', 'rpress_term_tax_class_nonce' );
	?>
	<div class="form-field term-tax-class-wrap">
		<label for="rpress_tax_class"><?php esc_html_e( 'Tax class', 'restropress' ); ?></label>
		<?php echo rpress_tax_class_select( 'rpress_tax_class', '', __( 'Standard (default)', 'restropress' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
		<p><?php esc_html_e( 'Items in this category are taxed at this class unless an item sets its own.', 'restropress' ); ?></p>
	</div>
	<?php
} );
add_action( 'food-category_edit_form_fields', function ( $term ) {
	if ( ! rpress_use_taxes() ) {
		return;
	}
	?>
	<tr class="form-field term-tax-class-wrap">
		<th scope="row"><label for="rpress_tax_class"><?php esc_html_e( 'Tax class', 'restropress' ); ?></label></th>
		<td>
			<?php wp_nonce_field( 'rpress_term_tax_class', 'rpress_term_tax_class_nonce' ); ?>
			<?php echo rpress_tax_class_select( 'rpress_tax_class', (string) get_term_meta( $term->term_id, '_rpress_tax_class', true ), __( 'Standard (default)', 'restropress' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
			<p class="description"><?php esc_html_e( 'Items in this category are taxed at this class unless an item sets its own.', 'restropress' ); ?></p>
		</td>
	</tr>
	<?php
} );
/**
 * Save a category's tax class.
 *
 * @param int $term_id Term ID.
 * @return void
 */
function rpress_save_term_tax_class( $term_id ) {
	if ( ! isset( $_POST['rpress_term_tax_class_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rpress_term_tax_class_nonce'] ) ), 'rpress_term_tax_class' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_product_terms' ) && ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$class = isset( $_POST['rpress_tax_class'] ) ? sanitize_key( wp_unslash( $_POST['rpress_tax_class'] ) ) : '';
	$classes = rpress_get_tax_classes();
	if ( '' === $class || 'standard' === $class || ! isset( $classes[ $class ] ) ) {
		delete_term_meta( $term_id, '_rpress_tax_class' );
	} else {
		update_term_meta( $term_id, '_rpress_tax_class', $class );
	}
}
add_action( 'created_food-category', 'rpress_save_term_tax_class' );
add_action( 'edited_food-category', 'rpress_save_term_tax_class' );

/**
 * Food item editor: tax class box.
 */
add_action( 'add_meta_boxes_fooditem', function () {
	if ( ! rpress_use_taxes() ) {
		return;
	}
	add_meta_box( 'rpress-fooditem-tax-class', __( 'Tax class', 'restropress' ), function ( $post ) {
		wp_nonce_field( 'rpress_item_tax_class', 'rpress_item_tax_class_nonce' );
		echo rpress_tax_class_select( 'rpress_item_tax_class', (string) get_post_meta( $post->ID, '_rpress_tax_class', true ), __( 'Use category default', 'restropress' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		echo '<p class="description">' . esc_html__( 'Override the tax class of this item\'s category.', 'restropress' ) . '</p>';
	}, 'fooditem', 'side', 'default' );
} );
add_action( 'save_post_fooditem', function ( $post_id ) {
	if ( ! isset( $_POST['rpress_item_tax_class_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rpress_item_tax_class_nonce'] ) ), 'rpress_item_tax_class' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$class   = isset( $_POST['rpress_item_tax_class'] ) ? sanitize_key( wp_unslash( $_POST['rpress_item_tax_class'] ) ) : '';
	$classes = rpress_get_tax_classes();
	if ( '' === $class || ! isset( $classes[ $class ] ) ) {
		delete_post_meta( $post_id, '_rpress_tax_class' );
	} else {
		update_post_meta( $post_id, '_rpress_tax_class', $class );
	}
} );

/**
 * Store the per-class breakdown on each order so receipts never recompute it.
 */
add_action( 'rpress_insert_payment', function ( $payment_id, $payment_data ) {
	if ( ! rpress_use_taxes() || empty( $payment_data['cart_details'] ) ) {
		return;
	}
	update_post_meta( $payment_id, '_rpress_tax_breakdown', rpress_get_tax_breakdown( $payment_data['cart_details'], isset( $payment_data['tax'] ) ? $payment_data['tax'] : null ) );
}, 10, 2 );

/**
 * Breakdown rows to show for an order, or an empty array for single-rate orders.
 *
 * @param int $payment_id Order ID.
 * @return array
 */
function rpress_get_order_tax_breakdown( $payment_id ) {
	$breakdown = get_post_meta( absint( $payment_id ), '_rpress_tax_breakdown', true );
	return is_array( $breakdown ) && count( $breakdown ) > 1 ? $breakdown : array();
}

/**
 * Label for one breakdown row: "GST Beverages 18%" style.
 *
 * @param array $row Breakdown row.
 * @return string
 */
function rpress_tax_breakdown_label( $row ) {
	/* translators: 1: tax name (for example GST), 2: tax class name, 3: rate in percent */
	$label = sprintf( __( '%1$s %2$s %3$s%%', 'restropress' ), rpress_get_tax_name(), $row['name'], rtrim( rtrim( number_format( (float) $row['rate'], 4, '.', '' ), '0' ), '.' ) );
	if ( ! empty( $row['code'] ) ) {
		$label .= ' (' . $row['code'] . ')';
	}
	return $label;
}

/**
 * Checkout: per-class rows under the tax total when the cart mixes rates.
 */
add_action( 'rpress_checkout_table_tax_last', function () {
	$lines = rpress_get_cart_content_details();
	if ( ! rpress_lines_have_mixed_tax_rates( $lines ) ) {
		return;
	}
	foreach ( rpress_get_tax_breakdown( $lines, rpress_get_cart_tax() ) as $row ) {
		printf(
			'<tr class="rpress_cart_footer_row rpress_cart_tax_class_row"><th colspan="%1$d" class="rpress_cart_tax_class"><span class="pull-left" style="padding-left:12px;opacity:.8;">%2$s</span><span class="pull-right" style="opacity:.8;">%3$s</span></th></tr>',
			(int) rpress_checkout_cart_columns(),
			esc_html( rpress_tax_breakdown_label( $row ) ),
			esc_html( rpress_currency_filter( rpress_format_amount( $row['tax'] ) ) )
		);
	}
} );

/**
 * Receipt and admin order screen: breakdown rows (action added in the templates).
 */
add_action( 'rpress_receipt_after_tax_row', 'rpress_render_order_tax_breakdown', 10, 2 );
add_action( 'rpress_admin_order_after_tax_row', 'rpress_render_order_tax_breakdown', 10, 2 );
/**
 * Print breakdown rows for an order.
 *
 * @param int    $payment_id Order ID.
 * @param string $context    'receipt' or 'admin'.
 * @return void
 */
function rpress_render_order_tax_breakdown( $payment_id, $context = 'receipt' ) {
	$currency = rpress_get_payment_currency_code( $payment_id );
	foreach ( rpress_get_order_tax_breakdown( $payment_id ) as $row ) {
		$amount = rpress_currency_filter( rpress_format_amount( $row['tax'] ), $currency );
		if ( 'admin' === $context ) {
			printf( '<div class="rp-order-items-total-tax-class" style="opacity:.8;font-size:12px;"><span>%1$s</span><strong>%2$s</strong></div>', esc_html( rpress_tax_breakdown_label( $row ) ), wp_kses_post( $amount ) );
		} else {
			printf( '<div class="rp-track-total-row rp-track-tax-class-row" style="opacity:.8;font-size:.92em;"><span>%1$s</span><span>%2$s</span></div>', esc_html( rpress_tax_breakdown_label( $row ) ), esc_html( $amount ) );
		}
	}
}

/**
 * Email tag {tax_breakdown}.
 */
add_action( 'rpress_add_email_tags', function () {
	rpress_add_email_tag( 'tax_breakdown', __( 'Tax per tax class (only when an order mixes tax rates)', 'restropress' ), function ( $payment_id ) {
		$lines = array();
		$currency = rpress_get_payment_currency_code( $payment_id );
		foreach ( rpress_get_order_tax_breakdown( $payment_id ) as $row ) {
			$lines[] = esc_html( rpress_tax_breakdown_label( $row ) . ': ' . rpress_currency_filter( rpress_format_amount( $row['tax'] ), $currency ) );
		}
		return implode( '<br/>', $lines );
	} );
}, 20 );
