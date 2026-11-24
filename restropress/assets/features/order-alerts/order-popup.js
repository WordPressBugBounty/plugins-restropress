/**
 * New-order popup on every admin screen except Live Orders.
 *
 * Lists the orders waiting to be accepted (Open, Acknowledge per order,
 * Acknowledge all) and rings while it shows unacknowledged orders. Dismiss
 * hides it until the next new order. The sound follows the per-browser Sound
 * switch shared with Live Orders (localStorage rp_live_orders_sound).
 *
 * @since 3.5.1
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var cfg = window.rpressOrderPopup;
		if ( ! cfg || ! cfg.ajaxUrl ) {
			return;
		}
		var s            = cfg.strings || {};
		var SOUND_KEY    = 'rp_live_orders_sound';
		var NOTIFY_KEY   = 'rpress_order_alerts_notify';
		var DISMISS_KEY  = 'rpress_order_popup_dismissed';
		var RANG_KEY     = 'rpress_order_popup_rang_at';
		var repeatMs     = Math.max( 5, parseInt( cfg.repeatInterval, 10 ) || 10 ) * 1000;
		var pollMs       = Math.max( 10, parseInt( cfg.pollInterval, 10 ) || 15 ) * 1000;
		var orders       = [];
		var total        = 0;
		var known        = null; // Order ids from the previous poll (null before the first).
		var clockSkew    = 0;    // Server time minus browser time, in seconds.
		var halted       = false;
		var audio        = null;
		var stopTimer    = null;
		var blocked      = false;
		var originalTitle = document.title;

		function storageGet( key ) {
			try {
				return window.localStorage ? window.localStorage.getItem( key ) : null;
			} catch ( e ) {
				return null;
			}
		}
		function storageSet( key, value ) {
			try {
				if ( window.localStorage ) {
					window.localStorage.setItem( key, value );
				}
			} catch ( e ) {}
		}
		function fmt( str, value ) {
			return String( str || '' ).replace( '%d', value ).replace( '%s', value );
		}
		function soundOn() {
			return 'off' !== storageGet( SOUND_KEY );
		}
		function dismissed() {
			try {
				return JSON.parse( storageGet( DISMISS_KEY ) || '[]' ) || [];
			} catch ( e ) {
				return [];
			}
		}

		// ── Markup ─────────────────────────────────────────────────────
		var $pop = $(
			'<section class="rp-op" role="region" hidden>' +
				'<header class="rp-op-head">' +
					'<span class="rp-op-bell dashicons dashicons-bell" aria-hidden="true"></span>' +
					'<h2 class="rp-op-title" aria-live="polite"></h2>' +
					'<button type="button" class="rp-op-icon rp-op-mute"><span class="dashicons" aria-hidden="true"></span></button>' +
					'<button type="button" class="rp-op-icon rp-op-close"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' +
				'</header>' +
				'<p class="rp-op-blocked" hidden></p>' +
				'<ul class="rp-op-list"></ul>' +
				'<footer class="rp-op-foot">' +
					'<a class="rp-op-live"></a>' +
					'<button type="button" class="button-link rp-op-ack-all"></button>' +
				'</footer>' +
			'</section>'
		);
		$pop.attr( 'aria-label', s.region || 'New orders' );
		$pop.find( '.rp-op-close' ).attr( { title: s.dismiss, 'aria-label': s.dismiss } );
		$pop.find( '.rp-op-live' ).attr( 'href', cfg.liveUrl ).text( s.live );
		$pop.find( '.rp-op-ack-all' ).text( s.ackAll );
		$pop.find( '.rp-op-blocked' ).text( s.blocked );
		$( 'body' ).append( $pop );

		function ago( createdAt ) {
			var secs = Math.max( 0, Math.floor( Date.now() / 1000 ) + clockSkew - createdAt );
			if ( secs < 60 ) {
				return s.justNow;
			}
			if ( secs < 3600 ) {
				return fmt( s.minAgo, Math.floor( secs / 60 ) );
			}
			return fmt( s.hourAgo, Math.floor( secs / 3600 ) );
		}

		function renderMute() {
			var on = soundOn();
			$pop.find( '.rp-op-mute' )
				.attr( { 'aria-pressed': on ? 'false' : 'true', title: on ? s.soundOn : s.soundOff, 'aria-label': on ? s.soundOn : s.soundOff } )
				.toggleClass( 'is-off', ! on )
				.find( '.dashicons' ).attr( 'class', 'dashicons ' + ( on ? 'dashicons-controls-volumeon' : 'dashicons-controls-volumeoff' ) );
		}

		function itemHtml( o ) {
			var $li = $( '<li class="rp-op-item"></li>' ).attr( 'data-order-id', o.id );
			var $top = $( '<div class="rp-op-line"></div>' );
			$top.append( $( '<a class="rp-op-number"></a>' ).attr( 'href', o.url ).text( o.number ) );
			if ( o.service ) {
				$top.append( $( '<span class="rp-op-service"></span>' ).text( o.service ) );
			}
			$top.append( $( '<span class="rp-op-ago"></span>' ).attr( 'data-created', o.createdAt ).text( ago( o.createdAt ) ) );
			var meta = [ o.items, o.total ];
			if ( o.time ) {
				meta.push( fmt( s.due, o.time ) );
			}
			var $meta = $( '<div class="rp-op-meta"></div>' ).text( meta.filter( Boolean ).join( ' · ' ) );
			var $actions = $( '<div class="rp-op-actions"></div>' );
			$actions.append( $( '<a class="button button-primary button-small rp-op-open"></a>' ).attr( 'href', o.url ).text( s.open ) );
			$actions.append( $( '<button type="button" class="button button-small rp-op-ack"></button>' ).attr( { 'data-order-id': o.id, title: s.ackHelp } ).text( s.ack ) );
			return $li.append( $top, $meta, $actions );
		}

		function isVisible() {
			if ( ! orders.length ) {
				return false;
			}
			var gone = dismissed();
			return orders.some( function ( o ) { return gone.indexOf( o.id ) === -1; } );
		}

		function render() {
			var show = isVisible();
			if ( ! show ) {
				$pop.prop( 'hidden', true );
				document.title = originalTitle;
				stopSound();
				return;
			}
			$pop.find( '.rp-op-title' ).text( fmt( 1 === total ? s.titleOne : s.titleMany, total ) );
			// Rebuilding the list must not lose the scroll position or the
			// keyboard focus of someone working through it.
			var $list     = $pop.find( '.rp-op-list' );
			var scrollTop = $list.scrollTop();
			var focused   = document.activeElement;
			var focusKey  = focused && $.contains( $list[ 0 ], focused )
				? { id: $( focused ).closest( '.rp-op-item' ).attr( 'data-order-id' ), cls: $( focused ).hasClass( 'rp-op-ack' ) ? '.rp-op-ack' : ( $( focused ).hasClass( 'rp-op-open' ) ? '.rp-op-open' : '.rp-op-number' ) }
				: null;
			$list.empty();
			orders.forEach( function ( o ) { $list.append( itemHtml( o ) ); } );
			$list.scrollTop( scrollTop );
			if ( focusKey && focusKey.id ) {
				$list.find( '.rp-op-item[data-order-id="' + focusKey.id + '"] ' + focusKey.cls ).trigger( 'focus' );
			}
			if ( total > orders.length ) {
				$list.append( $( '<li class="rp-op-more"></li>' ).append( $( '<a></a>' ).attr( 'href', cfg.liveUrl ).text( fmt( s.more, total - orders.length ) ) ) );
			}
			$pop.find( '.rp-op-ack-all' ).prop( 'hidden', orders.length < 2 );
			$pop.find( '.rp-op-blocked' ).prop( 'hidden', ! ( blocked && soundOn() ) );
			renderMute();
			$pop.prop( 'hidden', false );
			document.title = '(' + total + ') ' + originalTitle;
		}

		// ── Sound ──────────────────────────────────────────────────────
		function stopSound() {
			window.clearTimeout( stopTimer );
			if ( audio ) {
				try { audio.pause(); audio.currentTime = 0; } catch ( e ) {}
			}
		}

		function ring( force ) {
			if ( ! soundOn() || ! cfg.soundUrl || ! isVisible() ) {
				return;
			}
			// Several admin tabs share one ring window, so they do not all
			// chime at once. A brand new order (force) rings unless another
			// tab rang in the last few seconds.
			var last = parseInt( storageGet( RANG_KEY ) || '0', 10 );
			var gap  = Date.now() - last;
			if ( gap < ( force ? 3000 : repeatMs - 500 ) ) {
				return;
			}
			storageSet( RANG_KEY, String( Date.now() ) );
			if ( ! audio ) {
				audio = new window.Audio( cfg.soundUrl );
			}
			stopSound();
			var p;
			try {
				p = audio.play();
			} catch ( e ) {
				p = null;
			}
			if ( p && typeof p.then === 'function' ) {
				p.then( function () {
					if ( blocked ) {
						blocked = false;
						render();
					}
				} ).catch( function () {
					if ( ! blocked ) {
						blocked = true;
						render();
					}
				} );
			}
			stopTimer = window.setTimeout( stopSound, ( parseInt( cfg.soundDuration, 10 ) || 5 ) * 1000 );
		}

		// A click or key press on the page lets the browser play sound.
		$( document ).on( 'click keydown', function () {
			if ( ! blocked || ! soundOn() ) {
				return;
			}
			if ( ! audio ) {
				audio = new window.Audio( cfg.soundUrl );
			}
			audio.muted = true;
			var p = audio.play();
			var done = function () {
				try { audio.pause(); audio.currentTime = 0; } catch ( e ) {}
				audio.muted = false;
			};
			if ( p && typeof p.then === 'function' ) {
				p.then( function () { done(); blocked = false; render(); } ).catch( done );
			} else {
				done();
			}
		} );

		// ── Desktop notification (opt-in on Live Orders; background tab only) ─
		function notify( o ) {
			if ( ! ( 'Notification' in window ) || 'granted' !== window.Notification.permission || 'off' === storageGet( NOTIFY_KEY ) ) {
				return;
			}
			if ( ! document.hidden && document.hasFocus() ) {
				return;
			}
			try {
				var n = new window.Notification( o.note.title, { body: o.note.body, icon: cfg.icon || undefined, tag: 'rpress-order-' + o.id } );
				n.onclick = function () {
					window.focus();
					try { n.close(); } catch ( e ) {}
				};
			} catch ( e ) {}
		}

		// ── Data ───────────────────────────────────────────────────────
		function apply( data ) {
			if ( ! data ) {
				return;
			}
			orders    = data.orders || [];
			total     = parseInt( data.total, 10 ) || orders.length;
			clockSkew = data.now ? data.now - Math.floor( Date.now() / 1000 ) : 0;
			var ids   = orders.map( function ( o ) { return o.id; } );
			// Forget dismissals of orders that are no longer listed.
			storageSet( DISMISS_KEY, JSON.stringify( dismissed().filter( function ( id ) { return ids.indexOf( id ) !== -1; } ) ) );
			var fresh = known ? orders.filter( function ( o ) { return known.indexOf( o.id ) === -1; } ) : [];
			known = ids;
			render();
			fresh.forEach( notify );
			ring( fresh.length > 0 );
		}

		function request( action, extra ) {
			return $.ajax( {
				url:      cfg.ajaxUrl,
				type:     'POST',
				dataType: 'json',
				data:     $.extend( { action: action, security: cfg.nonce }, extra || {} )
			} ).done( function ( res ) {
				if ( res && res.success ) {
					apply( res.data );
				}
			} ).fail( function ( xhr ) {
				// Session or nonce expired: stop quietly, a reload starts again.
				if ( xhr && ( 403 === xhr.status || 401 === xhr.status ) ) {
					halted = true;
				}
			} );
		}

		function poll() {
			if ( ! halted ) {
				request( 'rpress_order_popup_poll' );
			}
		}

		function acknowledge( ids ) {
			orders = orders.filter( function ( o ) { return ids.indexOf( o.id ) === -1; } );
			total  = Math.max( orders.length, total - ids.length );
			if ( known ) {
				known = known.filter( function ( id ) { return ids.indexOf( id ) === -1; } );
			}
			render();
			request( 'rpress_order_popup_ack', { order_ids: ids } );
		}

		$pop.on( 'click', '.rp-op-ack', function ( e ) {
			e.preventDefault();
			acknowledge( [ parseInt( $( this ).attr( 'data-order-id' ), 10 ) ] );
			var $first = $pop.find( '.rp-op-ack' ).first();
			( $first.length ? $first : $pop.find( '.rp-op-live' ) ).trigger( 'focus' );
		} );
		$pop.on( 'click', '.rp-op-ack-all', function ( e ) {
			e.preventDefault();
			acknowledge( orders.map( function ( o ) { return o.id; } ) );
		} );
		$pop.on( 'click', '.rp-op-close', function ( e ) {
			e.preventDefault();
			storageSet( DISMISS_KEY, JSON.stringify( orders.map( function ( o ) { return o.id; } ) ) );
			render();
		} );
		$pop.on( 'click', '.rp-op-mute', function ( e ) {
			e.preventDefault();
			storageSet( SOUND_KEY, soundOn() ? 'off' : 'on' );
			if ( ! soundOn() ) {
				stopSound();
			}
			render();
		} );

		// Other tabs: sound switch, dismissals.
		$( window ).on( 'storage', function ( e ) {
			var key = ( e.originalEvent || {} ).key;
			if ( SOUND_KEY === key && ! soundOn() ) {
				stopSound();
			}
			if ( SOUND_KEY === key || DISMISS_KEY === key ) {
				render();
			}
		} );

		// Repeat the ring while orders wait; keep "x min ago" fresh.
		window.setInterval( function () {
			$pop.find( '.rp-op-ago' ).each( function () {
				$( this ).text( ago( parseInt( $( this ).attr( 'data-created' ), 10 ) || 0 ) );
			} );
			ring( false );
		}, 1000 );

		window.setInterval( poll, pollMs );
		poll();
	} );
} )( jQuery );
