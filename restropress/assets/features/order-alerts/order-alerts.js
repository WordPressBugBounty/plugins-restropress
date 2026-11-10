/**
 * Missed-order protection on the Live Orders board.
 *
 * - Repeats the new-order sound every N seconds while any new order is
 *   unaccepted and not acknowledged; pulses the card and New column and
 *   flashes the tab title.
 * - Opt-in desktop notifications (Notification API) for orders that arrive
 *   while the tab is in the background.
 * - Optional auto-print of new orders, with the result recorded per order and
 *   a printer badge (Print / Retry / Reprint) on each card.
 *
 * Everything here runs only while the Live Orders page is open.
 *
 * @since 3.5
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $root = $( '.rp-live-orders' );
		var cfg   = window.rpressOrderAlerts;
		if ( ! $root.length || ! cfg ) {
			return;
		}
		var s             = cfg.strings || {};
		var board         = function () { return window.rpressLiveOrders || null; };
		var ACK_KEY       = 'rpress_order_alerts_ack';
		var NOTIFY_KEY    = 'rpress_order_alerts_notify';
		var repeatMs      = Math.max( 5, parseInt( cfg.repeatInterval, 10 ) || 10 ) * 1000;
		var originalTitle = document.title;
		var lastRingAt    = 0;
		var titleTick     = false;
		var titleActive   = false;
		var acked         = loadAcks();

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

		function loadAcks() {
			var map = {};
			try {
				( JSON.parse( storageGet( ACK_KEY ) || '[]' ) || [] ).forEach( function ( id ) {
					map[ String( id ) ] = true;
				} );
			} catch ( e ) {}
			return map;
		}

		function saveAcks() {
			// Keep only orders still on the board so the list never grows.
			var onBoard = {};
			$root.find( '.rp-live-card' ).each( function () {
				onBoard[ String( $( this ).attr( 'data-order-id' ) ) ] = true;
			} );
			var keep = Object.keys( acked ).filter( function ( id ) { return onBoard[ id ]; } );
			storageSet( ACK_KEY, JSON.stringify( keep ) );
		}

		function fmt( str, value ) {
			return String( str || '' ).replace( '%d', value ).replace( '%s', value );
		}

		// ── Alert state ────────────────────────────────────────────────
		function alertingCards() {
			return $root.find( '.rp-live-card' ).filter( function () {
				var $card = $( this );
				var id    = String( $card.attr( 'data-order-id' ) );
				return 'pending' === $card.attr( 'data-status' ) &&
					'1' === $card.find( '.rp-oa-row' ).attr( 'data-oa-alert' ) &&
					! acked[ id ];
			} );
		}

		function render() {
			$root.find( '.rp-live-card' ).each( function () {
				var $card    = $( this );
				var id       = String( $card.attr( 'data-order-id' ) );
				var isAcked  = !! acked[ id ];
				$card.find( '.rp-oa-ack' ).prop( 'hidden', isAcked );
				$card.find( '.rp-oa-acked' ).prop( 'hidden', ! isAcked );
			} );
			var $alerting = alertingCards();
			$root.find( '.rp-live-card' ).removeClass( 'rp-oa-alerting' );
			$alerting.addClass( 'rp-oa-alerting' );
			$root.find( '.rp-live-column' ).each( function () {
				var $col = $( this );
				$col.toggleClass( 'rp-oa-column-alerting', $col.find( '.rp-oa-alerting' ).length > 0 );
			} );
			$root.toggleClass( 'rp-oa-has-alerts', $alerting.length > 0 );
			$root.attr( 'data-oa-alerting', $alerting.length );
			return $alerting.length;
		}

		function renderTitle( count ) {
			if ( ! count ) {
				if ( titleActive ) {
					document.title = originalTitle;
					titleActive = false;
				}
				return;
			}
			titleActive = true;
			titleTick   = ! titleTick;
			document.title = titleTick
				? fmt( 1 === count ? s.titleOne : s.titleMany, count )
				: originalTitle;
		}

		function tick() {
			var count = render();
			renderTitle( count );
			if ( ! count ) {
				return;
			}
			var api = board();
			if ( api && api.isSoundOn() && Date.now() - lastRingAt >= repeatMs ) {
				lastRingAt = Date.now();
				api.playSound();
			}
		}
		window.setInterval( tick, 1000 );

		$root.on( 'click', '.rp-oa-ack', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			acked[ String( $( this ).attr( 'data-order-id' ) ) ] = true;
			saveAcks();
			tick();
		} );

		$( document ).on( 'rpress:orderStatusChanged', function () {
			tick();
		} );

		// ── Desktop notifications ──────────────────────────────────────
		var hasNotify = 'Notification' in window;
		var $notifyBtn = $( '<button type="button" class="button rp-oa-notify-toggle" aria-pressed="false"><span class="dashicons dashicons-desktop" aria-hidden="true"></span><span class="rp-oa-notify-label"></span></button>' );

		function notifyEnabled() {
			return hasNotify && 'granted' === window.Notification.permission && 'off' !== storageGet( NOTIFY_KEY );
		}

		function renderNotifyButton() {
			var perm = window.Notification.permission;
			var label;
			var title = s.notifyHelp || '';
			if ( 'denied' === perm ) {
				label = s.notifyBlocked;
				title = s.notifyBlockedHelp || title;
			} else if ( 'granted' === perm ) {
				label = notifyEnabled() ? s.notifyOn : s.notifyOff;
			} else {
				label = s.notifyEnable;
			}
			$notifyBtn
				.attr( 'aria-pressed', notifyEnabled() ? 'true' : 'false' )
				.attr( 'title', title )
				.toggleClass( 'is-blocked', 'denied' === perm )
				.find( '.rp-oa-notify-label' ).text( label );
		}

		if ( hasNotify ) {
			$root.find( '.rp-live-toolbar-right' ).append( $notifyBtn );
			renderNotifyButton();
			$notifyBtn.on( 'click', function ( e ) {
				e.preventDefault();
				var perm = window.Notification.permission;
				if ( 'granted' === perm ) {
					storageSet( NOTIFY_KEY, notifyEnabled() ? 'off' : 'on' );
					renderNotifyButton();
					return;
				}
				if ( 'denied' === perm ) {
					window.alert( s.notifyBlockedHelp );
					return;
				}
				var done = function () {
					storageSet( NOTIFY_KEY, 'on' );
					renderNotifyButton();
				};
				try {
					var req = window.Notification.requestPermission( done );
					if ( req && typeof req.then === 'function' ) {
						req.then( done );
					}
				} catch ( err ) {
					done();
				}
			} );
		}

		function cardSummary( $card ) {
			var parts = [];
			var service = $.trim( $card.find( '.rp-type-cell-label' ).first().text() );
			var items   = $.trim( $card.find( '.rp-live-card-meta strong' ).first().text() );
			var total   = $.trim( $card.find( '.rp-live-card-payment strong' ).first().text() );
			if ( service ) { parts.push( service ); }
			if ( items ) { parts.push( items ); }
			if ( total ) { parts.push( total ); }
			return parts.join( ', ' );
		}

		function notifyNewOrder( $card ) {
			if ( ! notifyEnabled() || ( ! document.hidden && document.hasFocus() ) ) {
				return;
			}
			var id     = $card.attr( 'data-order-id' );
			var number = $.trim( $card.find( '.rp-live-card-number' ).first().text() ) || '#' + id;
			try {
				var n = new window.Notification( fmt( s.notifyTitle, number ), {
					body: cardSummary( $card ),
					icon: cfg.icon || undefined,
					tag: 'rpress-order-' + id
				} );
				n.onclick = function ( ev ) {
					if ( ev && ev.preventDefault ) { ev.preventDefault(); }
					window.focus();
					var $target = $root.find( '.rp-live-card[data-order-id="' + id + '"]' );
					if ( $target.length && $target[ 0 ].scrollIntoView ) {
						$target[ 0 ].scrollIntoView( { block: 'center' } );
					}
					try { n.close(); } catch ( err ) {}
				};
			} catch ( err ) {}
		}

		// ── Printing ───────────────────────────────────────────────────
		var printQueue = [];
		var printBusy  = false;

		function post( data ) {
			return $.ajax( {
				url:      cfg.ajaxUrl,
				type:     'POST',
				dataType: 'json',
				data:     $.extend( { action: 'rpress_order_alerts_print', security: cfg.nonce }, data )
			} );
		}

		function setBadge( id, html ) {
			if ( ! html ) {
				return;
			}
			$root.find( '.rp-oa-print[data-order-id="' + id + '"]' ).replaceWith( html );
		}

		function report( id, status, message ) {
			return post( { op: 'status', order_id: id, status: status, message: message || '' } ).done( function ( res ) {
				if ( res && res.success ) {
					setBadge( id, res.data.html );
				}
			} );
		}

		function enqueuePrint( id, auto ) {
			printQueue.push( { id: id, auto: auto } );
			nextPrint();
		}

		function nextPrint() {
			if ( printBusy || ! printQueue.length ) {
				return;
			}
			printBusy = true;
			var job = printQueue.shift();
			var finish = function () {
				printBusy = false;
				nextPrint();
			};
			if ( ! job.auto ) {
				printOrder( job.id ).always( finish );
				return;
			}
			post( { op: 'claim', order_id: job.id } ).done( function ( res ) {
				if ( res && res.success && res.data.claimed ) {
					printOrder( job.id ).always( finish );
				} else {
					finish();
				}
			} ).fail( finish );
		}

		// Load the receipt, print it through a hidden iframe and record the
		// result. The browser cannot tell us whether paper came out: "printed"
		// means the print dialog flow completed (afterprint or print() returned).
		function printOrder( id ) {
			var deferred = $.Deferred();
			var settled  = false;
			var settle = function ( status, message ) {
				if ( settled ) {
					return;
				}
				settled = true;
				report( id, status, message ).always( function () { deferred.resolve(); } );
			};
			var $badge = $root.find( '.rp-oa-print[data-order-id="' + id + '"]' );
			$badge.addClass( 'is-busy' ).find( '.rp-oa-print-action' ).prop( 'disabled', true ).text( s.printing );

			$.ajax( {
				url:      cfg.ajaxUrl,
				type:     'GET',
				dataType: 'html',
				data:     { action: 'rp_print_payment_data', payment_id: id, security: cfg.printNonce }
			} ).done( function ( html ) {
				if ( ! html || ! $.trim( String( html ).replace( /<[^>]*>/g, '' ) ) ) {
					settle( 'failed', s.printEmpty );
					return;
				}
				var old = document.getElementById( 'rp-oa-print-frame' );
				if ( old ) {
					old.parentNode.removeChild( old );
				}
				var iframe = document.createElement( 'iframe' );
				iframe.id = 'rp-oa-print-frame';
				iframe.setAttribute( 'aria-hidden', 'true' );
				iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
				document.body.appendChild( iframe );
				var win = iframe.contentWindow;
				try {
					win.document.open();
					win.document.write( html );
					win.document.close();
				} catch ( err ) {
					settle( 'failed', s.printError );
					return;
				}
				var fire = function () {
					try {
						win.addEventListener( 'afterprint', function () { settle( 'printed' ); } );
						win.focus();
						win.print();
						// print() blocks until the dialog closes in most browsers;
						// where it does not, afterprint (or this fallback) settles it.
						window.setTimeout( function () { settle( 'printed' ); }, 1500 );
					} catch ( err ) {
						settle( 'failed', s.printError );
					}
				};
				if ( 'complete' === win.document.readyState ) {
					window.setTimeout( fire, 250 );
				} else {
					iframe.onload = function () { window.setTimeout( fire, 250 ); };
				}
			} ).fail( function () {
				settle( 'failed', s.printLoad );
			} );

			return deferred.promise();
		}

		$root.on( 'click', '.rp-oa-print-action', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			var id = parseInt( $( this ).attr( 'data-order-id' ), 10 );
			if ( id ) {
				enqueuePrint( id, false );
			}
		} );

		// ── Poll updates from the board ────────────────────────────────
		$( document ).on( 'rpress_live_orders_updated', function ( e, data ) {
			var newIds = ( data && data.new_order_ids ) || [];
			var rang   = false;
			newIds.forEach( function ( id ) {
				var $card = $root.find( '.rp-live-card[data-order-id="' + id + '"]' );
				var $row  = $card.find( '.rp-oa-row' );
				if ( ! $card.length ) {
					return;
				}
				if ( 'pending' === $card.attr( 'data-status' ) && '1' === $row.attr( 'data-oa-alert' ) ) {
					rang = true;
					notifyNewOrder( $card );
				}
				if ( cfg.autoPrint && '1' === $row.attr( 'data-oa-autoprint' ) ) {
					enqueuePrint( parseInt( id, 10 ), true );
				}
			} );
			// The board already chimed for these orders; start the repeat timer now.
			if ( rang ) {
				lastRingAt = Date.now();
			}
			tick();
		} );

		tick();
	} );
} )( jQuery );
