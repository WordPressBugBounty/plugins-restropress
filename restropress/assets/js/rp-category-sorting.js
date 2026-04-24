( function( $ ) {
	'use strict';

	$( document ).ready( function() {
		if ( typeof rp_pro_sorting_data === 'undefined' ) {
			return;
		}

		let userHasSorted = false;

		const perPageValue = parseInt( $( '#' + rp_pro_sorting_data.per_page_id ).val(), 10 );
		const currentPage  = parseInt( rp_pro_sorting_data.paged, 10 );
		const baseIndex    = currentPage > 0 && ! isNaN( perPageValue ) ? ( currentPage - 1 ) * perPageValue : 0;
		const taxTable     = $( '#the-list' );

		if ( ! taxTable.length || typeof taxTable.sortable !== 'function' ) {
			return;
		}

		$.ajax( {
			type: 'POST',
			url: window.ajaxurl,
			data: {
				action: 'rp_get_category_order',
				term_order_nonce: rp_pro_sorting_data.term_order_nonce
			},
			dataType: 'json',
			success: function( response ) {
				if ( ! response || ! response.success || ! response.data ) {
					return;
				}

				// Avoid resetting the DOM order if user already performed a manual sort.
				if ( userHasSorted ) {
					return;
				}

				const keysSorted = Object.keys( response.data ).sort( function( a, b ) {
					return response.data[ a ] - response.data[ b ];
				} );

				$.each( keysSorted, function( index, value ) {
					const $target = taxTable.find( '#tag-' + value );
					$target.appendTo( taxTable );
				} );
			}
		} );

		// If the taxonomy table contains items.
		if ( ! taxTable.find( 'tr:first-child' ).hasClass( 'no-items' ) ) {
			taxTable.sortable( {
				placeholder: 'rp-drag-drop-tax-placeholder',
				axis: 'y',
				start: function( event, ui ) {
					const item = $( ui.item[ 0 ] );
					$( '.rp-drag-drop-tax-placeholder' )
						.css( 'height', item.css( 'height' ) )
						.css( 'display', 'flex' )
						.css( 'width', '0' );
				},
				update: function( event, ui ) {
					userHasSorted = true;

					const item = $( ui.item[ 0 ] );
					item.find( 'input[type="checkbox"]' )
						.hide()
						.after( '<img src="' + rp_pro_sorting_data.preloader_url + '" class="rp-drag-drop-preloader" />' );

					const taxonomyOrderingData = [];
					taxTable.find( 'tr.ui-sortable-handle' ).each( function() {
						const ele = $( this );
						const rowId = ele.attr( 'id' ) || '';
						if ( rowId.indexOf( 'tag-' ) !== 0 ) {
							return;
						}

						taxonomyOrderingData.push( {
							term_id: rowId.replace( 'tag-', '' ),
							order: parseInt( ele.index(), 10 ) + 1
						} );
					} );

					$.ajax( {
						type: 'POST',
						url: window.ajaxurl,
						data: {
							action: 'rp_update_category_order',
							taxonomy_ordering_data: taxonomyOrderingData,
							base_index: baseIndex,
							term_order_nonce: rp_pro_sorting_data.term_order_nonce
						},
						dataType: 'json',
						complete: function() {
							$( '.rp-drag-drop-preloader' ).remove();
							item.find( 'input[type="checkbox"]' ).show();
						}
					} );
				}
			} );
		}
	} );
} )( jQuery );
