/**
 * RestroPress reservation form (3.5).
 *
 * Refreshes the time list when the date or party size changes. The form
 * itself posts normally, so it still works without this script.
 */
( function () {
	'use strict';

	var cfg = window.rpressReservation || {};

	function option( value, label, disabled ) {
		var o = document.createElement( 'option' );
		o.value = value;
		o.textContent = label;
		if ( disabled ) {
			o.disabled = true;
		}
		return o;
	}

	function refresh( form ) {
		var date = form.querySelector( '[name="rpress_res_date"]' );
		var party = form.querySelector( '[name="rpress_res_party"]' );
		var slot = form.querySelector( '[name="rpress_res_slot"]' );
		if ( ! date || ! party || ! slot || ! cfg.ajaxUrl ) {
			return;
		}

		var previous = slot.value;
		var token = String( Date.now() );
		slot.setAttribute( 'data-request', token );
		// Keep the current list and choice while loading: a disabled or empty
		// select is not submitted, which made the form ask for a time again.
		form.classList.add( 'is-loading-slots' );

		var url = cfg.ajaxUrl + '?action=rpress_reservation_slots&date=' + encodeURIComponent( date.value ) + '&party=' + encodeURIComponent( party.value );

		form.rpressSlotsPending = fetch( url, { credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( slot.getAttribute( 'data-request' ) !== token ) {
					return;
				}
				var slots = res && res.success && Array.isArray( res.data ) ? res.data : [];
				// The guest may have picked a time while this list was loading.
				previous = slot.value || previous;
				slot.innerHTML = '';
				if ( ! slots.length ) {
					slot.appendChild( option( '', cfg.none || '', false ) );
				} else {
					slot.appendChild( option( '', cfg.choose || '', false ) );
					slots.forEach( function ( s ) {
						var o = option( s.ts, s.available ? s.label : s.label + ' (' + ( cfg.full || 'full' ) + ')', ! s.available );
						if ( String( s.ts ) === previous && s.available ) {
							o.selected = true;
						}
						slot.appendChild( o );
					} );
				}
			} )
			.catch( function () {
				// Keep the current list; the server checks the slot again on submit.
			} )
			.then( function () {
				if ( slot.getAttribute( 'data-request' ) === token ) {
					form.classList.remove( 'is-loading-slots' );
					form.rpressSlotsPending = null;
				}
			} );
	}

	function init() {
		document.querySelectorAll( '.rpress-res-form' ).forEach( function ( form ) {
			[ 'rpress_res_date', 'rpress_res_party' ].forEach( function ( name ) {
				var field = form.querySelector( '[name="' + name + '"]' );
				if ( field ) {
					field.addEventListener( 'change', function () { refresh( form ); } );
				}
			} );
			form.addEventListener( 'submit', function ( e ) {
				// Wait for a time list that is still loading, then send.
				if ( form.rpressSlotsPending ) {
					e.preventDefault();
					form.rpressSlotsPending.then( function () { form.submit(); } );
				}
				var button = form.querySelector( '.rpress-res-submit' );
				if ( button ) {
					button.disabled = true;
				}
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
