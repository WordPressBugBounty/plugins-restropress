<?php
/**
 * Food item availability - simple Available / Unavailable toggle in core.
 *
 * Stores `rp_stock_status` post meta (values: 'available' | 'unavailable').
 * The RestroPress Inventory extension uses the same meta key, so this module
 * is forward-compatible: with or without the extension, the toggle works.
 *
 * @package RPRESS
 */

defined( 'ABSPATH' ) || exit;

/**
 * True if the food item is marked unavailable in core.
 */
function rpress_fooditem_is_unavailable( $post_id ) {
	return get_post_meta( $post_id, 'rp_stock_status', true ) === 'unavailable';
}

/**
 * Render the availability row inside the WP Publish metabox.
 * Matches the visual idiom of the existing rows (Status, Visibility) so the
 * availability state lives alongside the other "state" fields.
 */
function rpress_render_availability_publish_row( $post = null ) {
	$post = $post ?: get_post();
	if ( ! $post || $post->post_type !== 'fooditem' ) {
		return;
	}

	$is_unavailable = rpress_fooditem_is_unavailable( $post->ID );
	$status         = $is_unavailable ? 'unavailable' : 'available';
	?>
	<div class="misc-pub-section misc-pub-rp-availability<?php echo $is_unavailable ? ' is-unavailable' : ''; ?>">
		<span class="dashicons dashicons-cart" aria-hidden="true"></span>
		<span class="rp-availability-pub-label"><?php esc_html_e( 'Availability:', 'restropress' ); ?></span>
		<strong class="rp-availability-pub-state">
			<span class="rp-when-available"><?php esc_html_e( 'Available', 'restropress' ); ?></span>
			<span class="rp-when-unavailable"><?php esc_html_e( 'Sold out', 'restropress' ); ?></span>
		</strong>
		<label class="rp-availability-toggle rp-availability-toggle-pub" title="<?php esc_attr_e( 'Toggle availability', 'restropress' ); ?>">
			<input type="checkbox" class="rp-availability-checkbox"<?php checked( ! $is_unavailable ); ?>>
			<span class="rp-availability-switch" aria-hidden="true"><span></span></span>
		</label>
		<input type="hidden" name="rp_stock_status" class="rp-availability-value" value="<?php echo esc_attr( $status ); ?>">
	</div>
	<?php
}
add_action( 'post_submitbox_misc_actions', 'rpress_render_availability_publish_row' );

/**
 * Save `rp_stock_status` when the food item is saved from the editor.
 *
 * Piggybacks on the existing nonce already output by RP_FoodItem_Meta_Boxes
 * (`restropress_meta_nonce` / `restropress_save_data`).
 */
function rpress_save_fooditem_availability( $post_id, $post ) {
	if ( empty( $post_id ) || empty( $post ) || $post->post_type !== 'fooditem' ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || is_int( wp_is_post_revision( $post ) ) || is_int( wp_is_post_autosave( $post ) ) ) {
		return;
	}
	if ( empty( $_POST['restropress_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['restropress_meta_nonce'] ) ), 'restropress_save_data' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['rp_stock_status'] ) ) {
		return;
	}

	$value = sanitize_text_field( wp_unslash( $_POST['rp_stock_status'] ) );
	if ( $value === 'unavailable' ) {
		update_post_meta( $post_id, 'rp_stock_status', 'unavailable' );
	} else {
		update_post_meta( $post_id, 'rp_stock_status', 'available' );
	}
}
add_action( 'save_post', 'rpress_save_fooditem_availability', 20, 2 );

/**
 * Frontend: block ordering when marked unavailable.
 */
function rpress_availability_filter_orderable( $orderable, $fooditem_id ) {
	if ( rpress_fooditem_is_unavailable( $fooditem_id ) ) {
		return false;
	}
	return $orderable;
}
add_filter( 'rpress_is_orderable', 'rpress_availability_filter_orderable', 5, 2 );

/**
 * Frontend: replace the order button label when unavailable.
 *
 * Defers to the inventory extension's configurable "Out of Stock Button Text"
 * setting when present, so admins keep a single place to manage that copy.
 */
function rpress_availability_filter_label( $label, $item_id ) {
	if ( ! rpress_fooditem_is_unavailable( $item_id ) ) {
		return $label;
	}

	$default = __( 'Sold Out', 'restropress' );

	// Respect inventory extension's configured button text when set.
	$settings   = get_option( 'rpress_settings', array() );
	$configured = isset( $settings['no_stock_text'] ) ? trim( (string) $settings['no_stock_text'] ) : '';
	if ( $configured !== '' ) {
		$default = $configured;
	}

	return apply_filters( 'rpress_availability_unavailable_label', $default, $item_id );
}
add_filter( 'rpress_not_available', 'rpress_availability_filter_label', 5, 2 );

/**
 * Frontend: append the `item-disabled` class to the food item wrapper when
 * marked unavailable. Mirrors the approach used by the inventory extension
 * so a single greyscale style can target both.
 */
function rpress_availability_filter_item_class( $classes, $item_id ) {
	if ( rpress_fooditem_is_unavailable( $item_id ) ) {
		return $classes . ' item-disabled';
	}
	return $classes;
}
add_filter( 'rpress_fooditem_class', 'rpress_availability_filter_item_class', 10, 2 );

/**
 * Frontend: emit the visual styling for unavailable items.
 *
 * Kept minimal so the existing card layout is unchanged - no positioning
 * tweaks on parent wrappers. The badge is moved into the thumbnail holder
 * at runtime (see footer script below), which is the only element we set
 * `position: relative` on, and only inline when we inject the badge.
 */
function rpress_availability_emit_disabled_styles() {
	?>
	<style type="text/css">
		.restropress .item-disabled {
			-webkit-filter: grayscale(1);
			filter: grayscale(1);
			opacity: 0.7;
		}
		/* Hide the not-available link visually but keep its layout footprint,
		   so cards with and without the button line up identically. */
		.restropress .rpress_purchase_submit_wrapper a.rpress-not-available {
			visibility: hidden !important;
		}
		.rp-soldout-badge {
			position: absolute;
			top: 8px;
			left: 8px;
			z-index: 5;
			background: rgba(33, 33, 33, 0.88);
			color: #fff;
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			padding: 4px 8px;
			border-radius: 3px;
			line-height: 1;
			pointer-events: none;
			/* Keep the badge full-colour even when the card is greyscaled. */
			-webkit-filter: grayscale(0);
			filter: grayscale(0);
		}
	</style>
	<?php
}
add_action( 'wp_head', 'rpress_availability_emit_disabled_styles' );

/**
 * Frontend: render a "Sold out" corner badge after the thumbnail.
 * The badge is moved into the thumbnail holder at runtime (see footer JS)
 * so it can be positioned over the image without forcing layout changes
 * on any parent wrapper.
 */
function rpress_availability_render_soldout_badge() {
	$post_id = get_the_ID();
	if ( ! $post_id || ! rpress_fooditem_is_unavailable( $post_id ) ) {
		return;
	}
	$label = apply_filters( 'rpress_availability_unavailable_label', __( 'Sold Out', 'restropress' ), $post_id );
	echo '<span class="rp-soldout-badge">' . esc_html( $label ) . '</span>';
}
add_action( 'rpress_fooditem_after_thumbnail', 'rpress_availability_render_soldout_badge' );

/**
 * Frontend: relocate sold-out badges into their sibling thumbnail holder.
 * Sets `position: relative` inline on just that holder so absolute
 * positioning of the badge is scoped tightly and no other layout changes.
 */
function rpress_availability_relocate_badge_js() {
	?>
	<script>
	( function() {
		document.addEventListener( 'DOMContentLoaded', function() {
			var badges = document.querySelectorAll( '.rp-soldout-badge' );
			for ( var i = 0; i < badges.length; i++ ) {
				var badge  = badges[ i ];
				var parent = badge.parentNode;
				if ( ! parent ) continue;
				var holder = parent.querySelector( '.rpress-thumbnail-holder' );
				if ( ! holder ) continue;
				holder.style.position = 'relative';
				holder.appendChild( badge );
			}
		} );
	} )();
	</script>
	<?php
}
add_action( 'wp_footer', 'rpress_availability_relocate_badge_js' );

/**
 * Decide whether core should render its own availability column.
 * If the inventory extension already adds its `stock_toggle` column we step
 * aside to avoid duplicate UI during the transition.
 */
function rpress_availability_should_render_column() {
	if ( class_exists( 'RP_Inventory' ) ) {
		$settings = get_option( 'rpress_settings', array() );
		if ( ! empty( $settings['enable_stock'] ) && ! empty( $settings['enable_stock_toggle'] ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Add "Availability" column to the food items list table.
 */
function rpress_availability_add_column( $columns ) {
	if ( ! rpress_availability_should_render_column() ) {
		return $columns;
	}
	$columns['rp_availability'] = esc_html__( 'Availability', 'restropress' );
	return $columns;
}
add_filter( 'rpress_fooditem_columns', 'rpress_availability_add_column' );

/**
 * Render the availability column content.
 */
function rpress_availability_render_column( $column_name, $post_id ) {
	if ( $column_name !== 'rp_availability' || get_post_type( $post_id ) !== 'fooditem' ) {
		return;
	}
	$is_unavailable = rpress_fooditem_is_unavailable( $post_id );
	?>
	<label class="rp-availability-col-toggle">
		<input type="checkbox" class="rp-availability-quick" data-item-id="<?php echo esc_attr( $post_id ); ?>"<?php checked( ! $is_unavailable ); ?>>
		<span class="rp-availability-switch" aria-hidden="true"><span></span></span>
	</label>
	<span class="rp-availability-col-spinner spinner"></span>
	<?php
}
add_action( 'manage_posts_custom_column', 'rpress_availability_render_column', 9, 2 );

/**
 * AJAX handler - toggle availability from the list table.
 */
function rpress_availability_ajax_toggle() {
	check_ajax_referer( 'rp_availability_toggle', 'nonce' );

	$post_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
	$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

	if ( $post_id <= 0 || ! in_array( $status, array( 'available', 'unavailable' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'restropress' ) ), 400 );
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'restropress' ) ), 403 );
	}

	update_post_meta( $post_id, 'rp_stock_status', $status );

	wp_send_json_success( array( 'status' => $status ) );
}
add_action( 'wp_ajax_rpress_toggle_availability', 'rpress_availability_ajax_toggle' );

/**
 * Inline JS - toggle behaviour for the editor strip and list-table column.
 */
function rpress_availability_admin_inline_js() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'fooditem' ) {
		return;
	}
	$nonce = wp_create_nonce( 'rp_availability_toggle' );
	$ajax  = admin_url( 'admin-ajax.php' );
	?>
	<script>
	( function( $ ) {
		$( function() {
			// Publish-box toggle - sync the hidden input + visual state on change.
			$( document ).on( 'change', '.rp-availability-checkbox', function() {
				var $container = $( this ).closest( '.misc-pub-rp-availability' );
				var checked    = $( this ).is( ':checked' );
				$container.toggleClass( 'is-unavailable', ! checked );
				$container.find( '.rp-availability-value' ).val( checked ? 'available' : 'unavailable' );
			} );

			// List-table quick toggle.
			$( document ).on( 'change', '.rp-availability-quick', function() {
				var $cb     = $( this );
				var $row    = $cb.closest( 'tr' );
				var itemId  = $cb.data( 'item-id' );
				var status  = $cb.is( ':checked' ) ? 'available' : 'unavailable';
				$cb.prop( 'disabled', true );
				$row.find( '.rp-availability-col-spinner' ).addClass( 'is-active' );
				$.post( <?php echo wp_json_encode( $ajax ); ?>, {
					action:  'rpress_toggle_availability',
					item_id: itemId,
					status:  status,
					nonce:   <?php echo wp_json_encode( $nonce ); ?>
				} ).always( function() {
					$cb.prop( 'disabled', false );
					$row.find( '.rp-availability-col-spinner' ).removeClass( 'is-active' );
				} );
			} );
		} );
	} )( jQuery );
	</script>
	<?php
}
add_action( 'admin_footer', 'rpress_availability_admin_inline_js' );
