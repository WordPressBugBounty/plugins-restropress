/**
 * Settings > General > Webhooks: endpoints and the delivery log. Every change is an admin-ajax call with a nonce. The panels
 * sit inside the core settings form, so none of the inputs has a name and
 * Enter never submits that form from here.
 */
( function ( $ ) {
	'use strict';

	if ( typeof rpressWebhooksAdmin === 'undefined' ) {
		return;
	}
	var cfg = rpressWebhooksAdmin;
	var t = cfg.i18n;

	// Fallback for browsers without :has(): see admin.css.
	$( '.rpress-webhooks-panel' ).closest( 'table.form-table' ).addClass( 'rpress-webhooks-form-table' )
		.closest( 'form' ).addClass( 'rpress-webhooks-settings-form' );

	function notice( $panel, message, ok ) {
		var $box = $panel.find( '.rpress-webhooks-notice' ).first();
		$box.text( message || '' )
			.removeClass( 'is-error is-success' )
			.addClass( ok ? 'is-success' : 'is-error' )
			.prop( 'hidden', ! message );
		if ( message && $box[ 0 ] && $box[ 0 ].scrollIntoView ) {
			// The notice sits at the top of the panel, which can be far above the button that was pressed.
			$box[ 0 ].scrollIntoView( { block: 'center', behavior: 'smooth' } );
		}
	}

	function call( data ) {
		data.action = 'rpress_webhooks_admin';
		data.security = cfg.nonce;
		return $.post( cfg.ajaxUrl, data );
	}

	function failMessage( xhr ) {
		var json = xhr && xhr.responseJSON;
		return ( json && json.data && json.data.message ) || t.failed;
	}

	function reload() {
		window.location.reload();
	}

	function copyText( text, $button ) {
		var done = function () {
			$button.text( t.copied );
			window.setTimeout( function () {
				$button.text( t.copy );
			}, 1500 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}
		var $tmp = $( '<textarea readonly></textarea>' ).val( text ).css( { position: 'fixed', top: 0, left: '-9999px' } ).appendTo( document.body );
		$tmp[ 0 ].select();
		try {
			document.execCommand( 'copy' );
			done();
		} catch ( e ) {}
		$tmp.remove();
	}

	/* Row buttons: send test, pause / resume, delete, reveal secret, resend. */
	$( document ).on( 'click', '.rpress-webhooks-action', function ( e ) {
		e.preventDefault();
		var $button = $( this );
		var $panel = $button.closest( '.rpress-webhooks-panel' );
		var $row = $button.closest( 'tr' );
		var op = $button.data( 'op' );
		var confirmKey = $button.data( 'confirm' );
		if ( confirmKey && ! window.confirm( t[ confirmKey ] ) ) {
			return;
		}
		var label = $button.text();
		$button.prop( 'disabled', true ).text( t.working );
		notice( $panel, '', true );

		call( { op: op, id: $row.data( 'id' ) } ).done( function ( response ) {
			var data = ( response && response.data ) || {};
			if ( op === 'reveal_secret' && response && response.success ) {
				$row.find( '.rpress-webhooks-secret-value' ).text( data.secret );
				$row.find( '.rpress-webhooks-secret-box' ).prop( 'hidden', false );
				$button.remove();
				return;
			}
			if ( response && response.success && $button.data( 'reload' ) ) {
				reload();
				return;
			}
			$button.prop( 'disabled', false ).text( label );
			notice( $panel, data.message || t.failed, !! ( response && response.success ) );
		} ).fail( function ( xhr ) {
			$button.prop( 'disabled', false ).text( label );
			notice( $panel, failMessage( xhr ), false );
		} );
	} );

	/* Copy button (signing secret). */
	$( document ).on( 'click', '.rpress-webhooks-copy', function ( e ) {
		e.preventDefault();
		var $button = $( this );
		copyText( $button.siblings( 'code' ).first().text(), $button );
	} );

	/* Endpoint form: add and edit. */
	var $form = $( '#rpress-webhooks-endpoint-form' );
	var $save = $( '#rpress-webhooks-endpoint-save' );
	var $cancel = $( '#rpress-webhooks-endpoint-cancel' );
	var $title = $form.find( '.rpress-webhooks-form-title' );

	function resetForm() {
		$form.attr( 'data-id', '' );
		$( '#rpress-webhooks-endpoint-label, #rpress-webhooks-endpoint-url' ).val( '' );
		$form.find( '.rpress-webhooks-endpoint-event' ).prop( 'checked', false );
		$save.text( t.addEndpoint );
		$title.text( $title.data( 'add' ) );
		$cancel.prop( 'hidden', true );
	}

	$( document ).on( 'click', '.rpress-webhooks-edit', function ( e ) {
		e.preventDefault();
		var $row = $( this ).closest( 'tr' );
		var events = String( $row.data( 'events' ) || '' ).split( ',' );
		$form.attr( 'data-id', $row.data( 'id' ) );
		$( '#rpress-webhooks-endpoint-label' ).val( $row.data( 'label' ) );
		$( '#rpress-webhooks-endpoint-url' ).val( $row.data( 'url' ) );
		$form.find( '.rpress-webhooks-endpoint-event' ).each( function () {
			$( this ).prop( 'checked', events.indexOf( this.value ) !== -1 );
		} );
		$save.text( t.saveEndpoint );
		$title.text( $title.data( 'edit' ) );
		$cancel.prop( 'hidden', false );
		$( '#rpress-webhooks-endpoint-label' ).trigger( 'focus' );
	} );

	$cancel.on( 'click', function ( e ) {
		e.preventDefault();
		resetForm();
	} );

	$save.on( 'click', function ( e ) {
		e.preventDefault();
		var $panel = $form.closest( '.rpress-webhooks-panel' );
		var label = $save.text();
		var events = $form.find( '.rpress-webhooks-endpoint-event:checked' ).map( function () {
			return this.value;
		} ).get();
		$save.prop( 'disabled', true ).text( t.working );
		call( {
			op: 'save_endpoint',
			id: $form.attr( 'data-id' ),
			label: $( '#rpress-webhooks-endpoint-label' ).val(),
			url: $( '#rpress-webhooks-endpoint-url' ).val(),
			events: events
		} ).done( function ( response ) {
			if ( response && response.success ) {
				reload();
				return;
			}
			$save.prop( 'disabled', false ).text( label );
			notice( $panel, ( response && response.data && response.data.message ) || t.failed, false );
		} ).fail( function ( xhr ) {
			$save.prop( 'disabled', false ).text( label );
			notice( $panel, failMessage( xhr ), false );
		} );
	} );

	/* Enter in a panel field presses that panel's button instead of saving the core settings form. */
	$( document ).on( 'keydown', '.rpress-webhooks-panel input', function ( e ) {
		if ( e.key !== 'Enter' ) {
			return;
		}
		e.preventDefault();
		if ( $( this ).closest( '#rpress-webhooks-endpoint-form' ).length && this.type !== 'checkbox' ) {
			$save.trigger( 'click' );
		}
	} );

	/* Delivery log details. */
	$( document ).on( 'click', '.rpress-webhooks-details', function ( e ) {
		e.preventDefault();
		var $button = $( this );
		var $detail = $button.closest( 'tr' ).next( '.rpress-webhooks-detail-row' );
		var open = $detail.prop( 'hidden' );
		$detail.prop( 'hidden', ! open );
		$button.attr( 'aria-expanded', open ? 'true' : 'false' ).text( open ? t.hide : t.details );
	} );
}( jQuery ) );
