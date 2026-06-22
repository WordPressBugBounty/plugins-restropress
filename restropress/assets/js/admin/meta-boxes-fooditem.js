/* Food Item metabox scripts */
jQuery( function( $ ) {

	/* ── Category form toggle ── */
	$( document ).on( 'click', '.rp_add_category', function() {
		var $form = $( '.rp-add-category' );
		var $icon = $( this ).find( '.dashicons' );
		$form.slideToggle( 180 );
		if ( $icon.hasClass( 'dashicons-plus-alt2' ) ) {
			$icon.removeClass( 'dashicons-plus-alt2' ).addClass( 'dashicons-no-alt' );
		} else {
			$icon.removeClass( 'dashicons-no-alt' ).addClass( 'dashicons-plus-alt2' );
		}
	});

	/* ── Pricing mode: radio cards sync with hidden _variable_pricing field ── */
	$('input[name="_rp_price_mode"]').on('change', function() {
		var isVariable = $(this).val() === 'variable';

		// Sync hidden field: 'yes' for variable, '' for fixed (empty = delete meta on save)
		$('#_variable_pricing').val( isVariable ? 'yes' : '' );

		// Active card style
		$('.rp-fi-price-mode-option').removeClass('is-active');
		$(this).closest('.rp-fi-price-mode-option').addClass('is-active');

		if ( isVariable ) {
			$('.rp-variable-prices').slideDown();
			$('.rpress_price_field').slideUp();
			// Show nudge if no option rows exist yet
			if ( $( '.rp-varprice-row' ).length === 0 ) {
				if ( ! $( '.rp-varprice-empty' ).length ) {
					$( '.rp-variable-prices .rp-metaboxes' ).prepend(
						'<div class="rp-varprice-empty">' +
						( fooditem_meta_boxes.variable_empty || 'No options yet - click "+ Add Option" to add sizes or variants.' ) +
						'</div>'
					);
				}
			}
		} else {
			$('.rp-variable-prices').slideUp();
			$('.rpress_price_field').slideDown();
			$( '.rp-varprice-empty' ).remove();
		}
	});

	/* Keep legacy #_variable_pricing change handler alive for extensions that might toggle it directly. */
	$( document ).on( 'change', '#_variable_pricing', function(){
		var isVariable = $(this).val() === 'yes';
		$('input[name="_rp_price_mode"][value="' + (isVariable ? 'variable' : 'fixed') + '"]').trigger('change');
	});

	/* On page load: enforce visible state in case CSS hidden class was not rendered (paranoid guard). */
	(function initPricingMode() {
		var isVariable = $('#_variable_pricing').val() === 'yes';
		if ( isVariable ) {
			$('.rpress_price_field').hide();
			$('.rp-variable-prices').show();
		} else {
			$('.rp-variable-prices').hide();
			$('.rpress_price_field').show();
		}
	})();

	/* ── Food type radio - active style ── */
	function syncFoodTypeStyle() {
		$('.rp-fi-type-option').each(function() {
			var val = $(this).find('input[type="radio"]').val();
			var checked = $(this).find('input[type="radio"]').is(':checked');
			$(this).removeClass('is-veg is-non-veg');
			if ( checked ) {
				if ( val === 'veg' ) $(this).addClass('is-veg');
				if ( val === 'non_veg' ) $(this).addClass('is-non-veg');
			}
		});
	}
	syncFoodTypeStyle();
	$('.rp-fi-type-options').on('change', 'input[type="radio"]', syncFoodTypeStyle);

	/* ── Advanced section toggle ── */
	$( document ).on( 'click', '.rp-fi-section.is-advanced > .rp-fi-section-header', function() {
		$(this).closest('.rp-fi-section').toggleClass('is-open');
	});

	/* ── Image upload via wp.media ── */
	var rpFoodImageFrame;

	function rpFoodImageUpdatePreview( url, label ) {
		var $img = $( '#rp-fi-image-preview' );
		if ( url ) {
			$img.attr( 'src', url ).show();
			$( '.rp-fi-image-empty' ).hide();
			$( '#rp-fi-remove-image' ).show();
			$( '#rp-fi-set-image' ).text( label || fooditem_meta_boxes.change_image || 'Change Image' );
		} else {
			$img.attr( 'src', '' ).hide();
			$( '.rp-fi-image-empty' ).show();
			$( '#rp-fi-remove-image' ).hide();
			$( '#rp-fi-set-image' ).text( fooditem_meta_boxes.set_image || 'Set Food Image' );
		}
	}

	$( '#rp-fi-set-image, #rp-fi-image-frame' ).on( 'click', function(e) {
		e.preventDefault();

		if ( rpFoodImageFrame ) {
			rpFoodImageFrame.open();
			return;
		}

		rpFoodImageFrame = wp.media({
			title  : fooditem_meta_boxes.image_title  || 'Select Food Image',
			button : { text: fooditem_meta_boxes.image_button || 'Use as food image' },
			multiple: false,
			library : { type: 'image' }
		});

		rpFoodImageFrame.on( 'select', function() {
			var attachment = rpFoodImageFrame.state().get('selection').first().toJSON();
			// Set WordPress featured image
			wp.media.featuredImage.set( attachment.id );
			// Update our preview
			var thumbUrl = ( attachment.sizes && attachment.sizes.thumbnail )
				? attachment.sizes.thumbnail.url
				: attachment.url;
			rpFoodImageUpdatePreview( thumbUrl, fooditem_meta_boxes.change_image );
		});

		rpFoodImageFrame.open();
	});

	$( '#rp-fi-remove-image' ).on( 'click', function(e) {
		e.preventDefault();
		wp.media.featuredImage.set(-1);
		rpFoodImageUpdatePreview( '', fooditem_meta_boxes.set_image );
	});

	// Keep preview in sync if user sets image via native sidebar box
	$( document ).ajaxComplete( function( e, xhr, settings ) {
		if ( settings.data && typeof settings.data === 'string' && settings.data.indexOf('action=set-post-thumbnail') > -1 ) {
			setTimeout( function() {
				var $nativePrev = $( '#postimagediv img:first' );
				if ( $nativePrev.length && $nativePrev.attr('src') ) {
					rpFoodImageUpdatePreview( $nativePrev.attr('src'), fooditem_meta_boxes.change_image );
				}
			}, 400 );
		}
	});

	/* ── Remove variable-price row ── */
	$( '#rpress-fooditem-data' ).on( 'click', '.remove_row.delete', function(e) {
		e.preventDefault();
		if( window.confirm( fooditem_meta_boxes.delete_pricing ) ) {
			$( this ).parents( '.rp-metabox' ).remove();
		}
	});

	/* ── Remove addon category ── */
	$( '#rpress-fooditem-data' ).on( 'click', '.remove.rp-addon-cat', function(e) {
		e.preventDefault();
		var rowCount = $('.addon-items-row').length;
		if( rowCount > 1 ){
			$(this).closest('tr').remove();
		} else if( window.confirm( fooditem_meta_boxes.delete_new_category ) ) {
			$( this ).parents( '.rp-addon.create-new-addon' ).remove();
		}
		rpUpdateAddonGroupCount();
	});

	/* ── Addon category name preview ── */
	$( '#rpress-fooditem-data' ).on( 'input keypress', '.rp-input.addon-category-name', function(event) {
		var _self = $( this );
		var category_name = _self.val();
		if( event.currentTarget.value.length >= 1 ) {
			if( category_name !== '' ) {
				_self.parents( '.rp-metabox.create-new-addon' ).find( '.addon_category_name' ).text( category_name );
			}
		} else {
			_self.parents( '.rp-metabox.create-new-addon' ).find( '.addon_category_name' ).text( 'Addon category Name' );
		}
	});

	/* ── Variable price option name preview ── */
	$( document ).on( 'input keypress', '.rp-input-variable-name', function(event) {
		var _self = $( this );
		var option_name = _self.val();
		if( event.currentTarget.value.length >= 1 ) {
			if( option_name !== '' ) {
				_self.parents( '.rp-metabox.variable-price' ).find( '.price_name' ).text( option_name );
			}
		} else {
			_self.parents( '.rp-metabox.variable-price' ).find( '.price_name' ).text( 'Option Name' );
		}
	});

	/* ── Addon multiple rows ── */
	$( '#rpress-fooditem-data' ).on( 'click', '.add-new-addon.add-addon-multiple-item', function(e) {
		e.preventDefault();
		var SeletedRow = $(this).parents('.rp-metabox-content').find('tr.addon-items-row');
		var ParentRow = SeletedRow.first().clone(true);
		ParentRow.find( 'input' ).each( function(){
			$(this).val('');
		});
		var LastRow = SeletedRow.last();
		$( ParentRow ).insertAfter( LastRow );
	});

	/* ── Select/Unselect all addons ── */
	$('#rpress-fooditem-data').on('change', '.rp-select-all', function(){
		var is_checked = $(this).prop('checked');
		$(this).parents('.rp-addon-items:eq(0)').find('.rp-addon-select .rp-checkbox').prop('checked', is_checked);
		rpUpdateAddonCountBadge( $(this).closest('.rp-addon') );
	});

	/* ── Update count badge when individual addon checkbox changes ── */
	$( '#rpress-fooditem-data' ).on( 'change', '.rp-addon-select .rp-checkbox', function() {
		rpUpdateAddonCountBadge( $(this).closest('.rp-addon') );
	});

	function rpUpdateAddonCountBadge( $addonGroup ) {
		if ( ! $addonGroup.length ) return;
		var checked = $addonGroup.find('.rp-addon-select .rp-checkbox:checked').length;
		var $badge  = $addonGroup.find('.rp-addon-count-badge');
		if ( ! $badge.length ) return;
		$badge.toggleClass( 'is-zero', checked === 0 );
		var label = checked === 1
			? ( fooditem_meta_boxes.option_single || '1 option' )
			: ( checked + ' ' + ( fooditem_meta_boxes.option_plural || 'options' ) );
		$badge.text( label );
	}

	function rpUpdateAddonGroupCount() {
		var count = $( '#addons_fooditem_data .rp-addon.rp-metabox' ).length;
		var $badge = $( '#rp-fi-addon-group-count' );
		if ( count > 0 ) {
			var label = count === 1
				? ( fooditem_meta_boxes.group_single || '1 group' )
				: ( count + ' ' + ( fooditem_meta_boxes.group_plural || 'groups' ) );
			$badge.text( label ).show();
		} else {
			$badge.hide();
		}
	}

	/* ── Add price row ── */
	$( document ).on( 'click', 'button.add-new-price', function() {
		var size     = $( '.rp-variable-prices .variable-price' ).length;
		var $wrapper = $( this ).closest( '.pricing' );
		var $prices  = $wrapper.find( '.rp-variable-prices' );
		var data     = {
			action   : 'rpress_add_price',
			i        : size,
			security : fooditem_meta_boxes.add_price_nonce
		};
		$wrapper.block({
			message: null,
			overlayCSS: { background: '#fff', opacity: 0.6 }
		});
		$.post( fooditem_meta_boxes.ajax_url, data, function( response ) {
			$prices.find('.add-new-price').before( response );
			$( '.rp-varprice-empty' ).remove();
			$wrapper.unblock();
			$( document.body ).trigger( 'rpress_added_price' );
		});
		return false;
	});

	/* ── Add new category ── */
	$( document ).on( 'click', 'button.add-category', function() {
		var $wrapper = $( this ).closest( '.rp-category' );
		var name     = $wrapper.find('#rp-category-name').val();
		var parent   = $wrapper.find('#rp-parent-category').val();
		if( name === '' ){
			$wrapper.find('#rp-category-name').focus();
			return;
		}
		var data = {
			action   : 'rpress_add_category',
			name     : name,
			parent   : parent,
			security : fooditem_meta_boxes.add_category_nonce
		};
		$wrapper.block({
			message: null,
			overlayCSS: { background: '#fff', opacity: 0.6 }
		});
		$.post( fooditem_meta_boxes.ajax_url, data, function( response ) {
			if( undefined !== response.term_id ){
				var newOption = new Option( name, response.term_id, true, true );
				$('.rp-category-select,#rp-parent-category').append( newOption ).trigger('change');
				$wrapper.find('#rp-category-name,#rp-parent-category').val('').trigger('change');
				// Collapse the form and reset the toggle icon
				$('.rp-add-category').slideUp( 180 );
				$('.rp_add_category .dashicons').removeClass('dashicons-no-alt').addClass('dashicons-plus-alt2');
			}
			$wrapper.unblock();
			$( document.body ).trigger( 'rpress_added_category' );
		});
		return false;
	});

	/* ── Enable sorting for addons and variations ── */
	$( ".rp-metaboxes" ).sortable({
		connectWith: ".rp-metaboxes",
		stack: '.rp-metaboxes .rp-metabox'
	}).disableSelection();

	/* ── Create addon / Add existing addon ── */
	$( document ).on( 'click', 'button.add-new-addon,button.create-addon', function(e) {
		var isCreate = $( e.target ).hasClass( 'create-addon' );
		var size     = Math.round( (new Date()).getTime() / 1000 );
		var $wrapper = $( this ).closest( '#addons_fooditem_data' );
		var $addons  = $wrapper.find( '.rp-addons' );
		var item_id  = $(this).attr('data-item-id');
		var data     = {
			action   : 'rpress_add_addon',
			item_id  : item_id,
			i        : size,
			iscreate : isCreate,
			security : fooditem_meta_boxes.add_addon_nonce
		};
		$wrapper.block({
			message: null,
			overlayCSS: { background: '#fff', opacity: 0.6 }
		});
		$.post( fooditem_meta_boxes.ajax_url, data, function( response ) {
			$addons.append( response );
			$wrapper.unblock();
			rpUpdateAddonGroupCount();
			$( document.body ).trigger( 'rpress_added_addon' );
		});
		return false;
	});

	/* ── Load addon items after selecting a category ── */
	$( '#addons_fooditem_data' ).on( 'click', 'button.load-addon', function(e) {
		var _self    = $(this);
		var parent   = _self.closest( '.addon-category' ).find( 'select' ).val();
		var fooditem = _self.attr('data-item-id');
		if( parent === '' ) {
			tata.error('Error', fooditem_meta_boxes.select_addon_category, {position : 'mr'});
			return false;
		} else if( $( '.addon-category select option:checked[value="' + parent +'"]' ).length > 1 ) {
			tata.error('Error', fooditem_meta_boxes.addon_category_already_selected, {position : 'mr'});
			return false;
		}
		var size     = _self.closest('.addon-category').find('select').attr('data-row-id');
		var $wrapper = _self.closest( '.rp-metabox-content' );
		var $addons  = $wrapper.find( '.addon-items' );
		var data     = {
			action  : 'rpress_load_addon_child',
			parent  : parent,
			item_id : fooditem,
			i       : size,
			security: fooditem_meta_boxes.load_addon_nonce
		};
		$wrapper.block({
			message: null,
			overlayCSS: { background: '#fff', opacity: 0.6 }
		});
		$.post( fooditem_meta_boxes.ajax_url, data, function( response ) {
			$addons.html( response );
			$wrapper.unblock();
			$( document.body ).trigger( 'rpress_loaded_addon' );
		});
		return false;
	});

	/* ── Set addon group header label + type badge when category is selected ── */
	function rpSyncAddonHeader( $select ) {
		var $card         = $select.closest('.rp-addon');
		var $selectedOpt  = $select.find(':selected');
		var selected_text = $selectedOpt.text();
		$card.find('h3 strong.addon_category_name').text( selected_text || fooditem_meta_boxes.select_addon || 'Select Addon' );
	}

	$( '#rpress-fooditem-data' ).on( 'change', '.rp-addon-lists', function() {
		rpSyncAddonHeader( $(this) );
	});

	$( '.rp-addon-lists' ).each( function() {
		rpSyncAddonHeader( $(this) );
	});

	/* ── Addon card collapse / expand ── */
	$( document ).on( 'click', '.rp-addon-collapse-btn', function(e) {
		e.preventDefault();
		$(this).closest('.rp-addon.rp-metabox').toggleClass('is-collapsed');
	});

	/* ── Required field validation before publish/update ── */
	$( '#publish, #save-post' ).on( 'click', function(e) {
		if ( $( 'body' ).hasClass( 'post-type-fooditem' ) ) {
			rpValidateRequiredFields(e);
		}
	});

	function rpValidateRequiredFields(e) {
		var hasError = false;

		// Price check
		var $pricingSection = $( '#general_pricing_data' );
		var isVariable = $( '#_variable_pricing' ).val() === 'yes';
		var priceOk = false;
		if ( isVariable ) {
			priceOk = $( '.rp-variable-prices .variable-price' ).length > 0;
		} else {
			var fixedPrice = $.trim( $( '#rpress_price' ).val() );
			priceOk = fixedPrice !== '' && parseFloat( fixedPrice ) >= 0;
		}
		if ( ! priceOk ) {
			$pricingSection.addClass('has-error');
			$( '#rp-fi-price-error' ).addClass('is-visible');
			hasError = true;
		} else {
			$pricingSection.removeClass('has-error');
			$( '#rp-fi-price-error' ).removeClass('is-visible');
		}

		// Category check
		var $categorySection = $( '#category_fooditem_data' );
		var categoryOk = $( '.rp-category-select' ).val() && $( '.rp-category-select' ).val().length > 0;
		if ( ! categoryOk ) {
			$categorySection.addClass('has-error');
			$( '#rp-fi-category-error' ).addClass('is-visible');
			hasError = true;
		} else {
			$categorySection.removeClass('has-error');
			$( '#rp-fi-category-error' ).removeClass('is-visible');
		}

		if ( hasError ) {
			e.preventDefault();
			$( 'html, body' ).animate({ scrollTop: $( '.has-error:first' ).offset().top - 40 }, 300);
		}
	}

	/* Clear error state on interaction */
	$( '#rpress_price' ).on( 'input', function() {
		$( '#general_pricing_data' ).removeClass('has-error');
		$( '#rp-fi-price-error' ).removeClass('is-visible');
	});
	$( document ).on( 'change', '.rp-category-select', function() {
		$( '#category_fooditem_data' ).removeClass('has-error');
		$( '#rp-fi-category-error' ).removeClass('is-visible');
	});

});

/* ── Single-type addon default selection enforcement ── */
jQuery(document).ready(function($){
	jQuery(document).on('click','td.td_checkbox .rps-checkbox', function(e) {
		let $this = $(this);
		if('single' === $(this).parent().parent().parent().parent().data('addon_type')){
			var variationName = $(this).data('variation_name');
			$.when( verifiedCheck(variationName) ).then(function(data){
				if( data > 1 ){
					$this.removeAttr('checked');
				}else{
					$this.attr('checked', true);
				}
			});
		}
	});
});

const verifiedCheck = function(variationName){
	let variationChecked = 0;
	jQuery(`table[data-addon_type="single"] [data-variation_name="${variationName}"]`).each(function(){
		if(jQuery(this).is(":checked")){
			variationChecked += 1;
		}
	});
	return variationChecked;
};

jQuery(document).ready(function($){
	jQuery('td.tds_checkbox .rp-checkbox').click(function() {
		if('single' === $(this).parent().parent().parent().parent().data('addon_type')){
			jQuery('td.tds_checkbox .rp-checkbox').not(this).prop('checked', false);
		}
	});
});
