/**
 * Menu QR generator (Settings > General > QR Code & SEO).
 * Keeps the preview, the "Opens:" link and the download buttons in sync.
 * The inputs have no name attribute, so saving the settings form ignores them.
 */
( function () {
	'use strict';

	var box = document.querySelector( '.rpress-menu-qr' );
	if ( ! box ) {
		return;
	}

	var page = document.getElementById( 'rpress-menu-qr-page' );
	var url = document.getElementById( 'rpress-menu-qr-url' );
	var table = document.getElementById( 'rpress-menu-qr-table' );
	var name = document.getElementById( 'rpress-menu-qr-name' );
	var target = document.getElementById( 'rpress-menu-qr-target' );
	var preview = document.getElementById( 'rpress-menu-qr-preview' );
	var svg = document.getElementById( 'rpress-menu-qr-svg' );
	var png = document.getElementById( 'rpress-menu-qr-png' );
	var custom = box.querySelector( '.rpress-menu-qr__custom' );
	var timer = null;

	function endpoint( format, download ) {
		var params = new URLSearchParams( {
			action: 'rpress_menu_qr',
			page_id: page.value,
			url: 'custom' === page.value ? url.value.trim() : '',
			table: table.value.trim(),
			name: name.checked ? '1' : '0',
			format: format,
			download: download ? '1' : '0',
			_wpnonce: box.getAttribute( 'data-nonce' )
		} );
		return box.getAttribute( 'data-endpoint' ) + '?' + params.toString();
	}

	function link() {
		var base = 'custom' === page.value ? url.value.trim() : ( page.options[ page.selectedIndex ].getAttribute( 'data-url' ) || '' );
		var label = table.value.trim();
		if ( ! base || ! label ) {
			return base;
		}
		try {
			var u = new URL( base );
			u.searchParams.set( 'table', label );
			return u.toString();
		} catch ( e ) {
			return base;
		}
	}

	function valid() {
		if ( 'custom' !== page.value ) {
			return true;
		}
		return /^https?:\/\/\S+$/i.test( url.value.trim() );
	}

	function update() {
		custom.hidden = 'custom' !== page.value;
		var ok = valid();
		target.textContent = ok ? link() : '';
		[ svg, png ].forEach( function ( btn ) {
			if ( ! btn ) {
				return;
			}
			btn.classList.toggle( 'disabled', ! ok );
			btn.setAttribute( 'aria-disabled', ok ? 'false' : 'true' );
		} );
		if ( ! ok ) {
			preview.style.visibility = 'hidden';
			return;
		}
		preview.style.visibility = '';
		svg.href = endpoint( 'svg', true );
		if ( png ) {
			png.href = endpoint( 'png', true );
		}
		preview.src = endpoint( 'svg', false );
	}

	function later() {
		clearTimeout( timer );
		timer = setTimeout( update, 300 );
	}

	page.addEventListener( 'change', update );
	name.addEventListener( 'change', update );
	url.addEventListener( 'input', later );
	table.addEventListener( 'input', later );

	// Enter in these fields must not submit the settings form.
	[ url, table ].forEach( function ( input ) {
		input.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				update();
			}
		} );
	} );

	[ svg, png ].forEach( function ( btn ) {
		if ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				if ( 'true' === btn.getAttribute( 'aria-disabled' ) ) {
					e.preventDefault();
				}
			} );
		}
	} );

	update();
}() );
