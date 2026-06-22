/**
 * Live Orders kanban - polling, drag-and-drop, sound, animations.
 *
 * @since 3.3
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $root = $( '.rp-live-orders' );
		if ( ! $root.length ) {
			return;
		}

		var cfg;
		try {
			cfg = JSON.parse( $root.attr( 'data-config' ) || '{}' );
		} catch ( e ) {
			cfg = {};
		}
		if ( ! cfg.ajaxUrl || ! cfg.nonce ) {
			return;
		}

		var $audio          = $( '#rp-live-orders-audio' );
		var $statusText     = $root.find( '.rp-live-status-text' );
		var $soundToggle    = $root.find( '.rp-live-sound-toggle' );
		var $soundOverlay   = $root.find( '.rp-live-sound-overlay' );
		var $refreshButton  = $root.find( '.rp-live-refresh' );
		var $pauseButton    = $root.find( '.rp-live-pause' );
		var $filterButtons  = $root.find( '.rp-live-filter-chip' );
		var $searchInput    = $root.find( '.rp-live-search-input' );
		var $tabTitle       = $( 'title' );
		var originalTitle   = document.title;
		var knownOrderIds   = ( cfg.initialIds || [] ).slice();
		var lastPolledAt    = Date.now();
		var soundOn         = ( storageGet( 'rp_live_orders_sound' ) !== 'off' );
		var audioUnlocked   = false;
		var audioContext    = null;
		var pendingTitleFlash = null;
		var isPaused        = false;
		var isPolling       = false;
		var activeServiceFilter = 'all';
		var activeSearchQuery   = '';

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

		// ── Sound toggle ────────────────────────────────────────────────
		function hasCustomAudio() {
			return !! ( cfg.soundUrl && $audio.length );
		}

		function renderSoundButton() {
			if ( ! $soundToggle.length ) {
				return;
			}
			var icon = soundOn ? 'dashicons-controls-volumeon' : 'dashicons-controls-volumeoff';
			$soundToggle
				.prop( 'disabled', false )
				.removeClass( 'is-disabled' )
				.attr( 'aria-pressed', soundOn ? 'true' : 'false' )
				.find( '.dashicons' )
				.attr( 'class', 'dashicons ' + icon );
			$soundToggle.find( '.rp-live-sound-label' ).text(
				soundOn ? ( cfg.strings.soundOn || 'Sound: On' ) : ( cfg.strings.soundOff || 'Sound: Off' )
			);
		}
		renderSoundButton();

		$soundToggle.on( 'click', function ( e ) {
			e.preventDefault();
			soundOn = ! soundOn;
			storageSet( 'rp_live_orders_sound', soundOn ? 'on' : 'off' );
			renderSoundButton();
			if ( soundOn && ! audioUnlocked ) {
				tryUnlockAudio();
			}
		} );

		// ── Audio context unlock (browsers block until user gesture) ────
		function getAudioContext() {
			var AudioContext = window.AudioContext || window.webkitAudioContext;
			if ( ! AudioContext ) {
				return null;
			}
			if ( ! audioContext ) {
				audioContext = new AudioContext();
			}
			return audioContext;
		}

		function playFallbackBeep( silent ) {
			var context = getAudioContext();
			if ( ! context ) {
				audioUnlocked = true;
				$soundOverlay.attr( 'hidden', true );
				return Promise.resolve();
			}

			function playTone() {
				var oscillator = context.createOscillator();
				var gain = context.createGain();
				oscillator.type = 'sine';
				oscillator.frequency.value = 880;
				gain.gain.value = silent ? 0.0001 : 0.16;
				oscillator.connect( gain );
				gain.connect( context.destination );
				oscillator.start();
				oscillator.stop( context.currentTime + ( silent ? 0.03 : 0.22 ) );
			}

			if ( context.state === 'suspended' ) {
				return context.resume().then( playTone );
			}

			playTone();
			return Promise.resolve();
		}

		// Captured once before any unlock attempt tampers with the element.
		// Restoring from a live audio.volume read instead caused a race: two
		// overlapping unlock attempts (page load + first gesture) would save
		// each other's muted 0 and leave every future chime silent.
		var baseVolume = hasCustomAudio() ? $audio[ 0 ].volume : 1;
		var unlockInFlight = false;

		function tryUnlockAudio() {
			if ( audioUnlocked || unlockInFlight ) {
				return;
			}
			unlockInFlight = true;
			if ( ! hasCustomAudio() ) {
				playFallbackBeep( true ).then( function () {
					audioUnlocked = true;
					unlockInFlight = false;
					$soundOverlay.attr( 'hidden', true );
				} ).catch( function () {
					unlockInFlight = false;
					$soundOverlay.attr( 'hidden', false );
				} );
				return;
			}
			var audio = $audio[ 0 ];
			audio.volume = 0;
			var promise = audio.play();
			if ( promise && typeof promise.then === 'function' ) {
				promise.then( function () {
					audio.pause();
					audio.currentTime = 0;
					audio.volume = baseVolume;
					audioUnlocked = true;
					unlockInFlight = false;
					$soundOverlay.attr( 'hidden', true );
				} ).catch( function () {
					audio.volume = baseVolume;
					unlockInFlight = false;
					$soundOverlay.attr( 'hidden', false );
				} );
			} else {
				audio.volume = baseVolume;
				audioUnlocked = true;
				unlockInFlight = false;
				$soundOverlay.attr( 'hidden', true );
			}
		}

		// On first user gesture anywhere on the page, attempt to unlock audio.
		$( document ).one( 'click keydown', function () {
			if ( soundOn && ! audioUnlocked ) {
				tryUnlockAudio();
			}
		} );
		if ( soundOn ) {
			tryUnlockAudio();
		}

		function renderPauseButton() {
			$pauseButton
				.attr( 'aria-pressed', isPaused ? 'true' : 'false' )
				.toggleClass( 'is-paused', isPaused )
				.find( '.dashicons' )
				.attr( 'class', 'dashicons ' + ( isPaused ? 'dashicons-controls-play' : 'dashicons-controls-pause' ) );
			$pauseButton.find( '.rp-live-pause-label' ).text(
				isPaused ? ( cfg.strings.resume || 'Resume' ) : ( cfg.strings.pause || 'Pause' )
			);
		}

		$pauseButton.on( 'click', function ( e ) {
			e.preventDefault();
			isPaused = ! isPaused;
			renderPauseButton();
			if ( isPaused ) {
				$statusText.text( cfg.strings.paused || 'Live updates paused' );
			} else {
				poll( true );
			}
		} );
		renderPauseButton();

		$refreshButton.on( 'click', function ( e ) {
			e.preventDefault();
			poll( true );
		} );

		$filterButtons.on( 'click', function ( e ) {
			e.preventDefault();
			activeServiceFilter = $( this ).data( 'service-filter' ) || 'all';
			$filterButtons
				.removeClass( 'is-active' )
				.attr( 'aria-pressed', 'false' );
			$( this )
				.addClass( 'is-active' )
				.attr( 'aria-pressed', 'true' );
			applyClientFilters();
		} );

		$searchInput.on( 'input', function () {
			activeSearchQuery = ( $( this ).val() || '' ).toString().toLowerCase().trim();
			applyClientFilters();
		} );

		function playSound() {
			if ( ! soundOn || ! audioUnlocked ) return;
			if ( ! hasCustomAudio() ) {
				playFallbackBeep( false );
				return;
			}
			var audio = $audio[ 0 ];
			try {
				audio.pause();
				audio.currentTime = 0;
				// Defensive: never ring at the muted unlock volume.
				audio.volume = baseVolume;
				audio.play();
				// Stop after configured duration (the audio file may be long or looping).
				var duration = ( cfg.soundDuration || 5 ) * 1000;
				window.setTimeout( function () {
					try { audio.pause(); audio.currentTime = 0; } catch ( e ) {}
				}, duration );
			} catch ( e ) {}
		}

		// ── Tab title flash ────────────────────────────────────────────
		function flashTabTitle( count ) {
			if ( pendingTitleFlash ) {
				window.clearTimeout( pendingTitleFlash );
			}
			var marker = '🔔 (' + count + ') ';
			document.title = marker + originalTitle;
			var clearTitle = function () {
				if ( document.hasFocus() ) {
					document.title = originalTitle;
				} else {
					pendingTitleFlash = window.setTimeout( clearTitle, 5000 );
				}
			};
			pendingTitleFlash = window.setTimeout( clearTitle, 15000 );
		}
		$( window ).on( 'focus', function () {
			if ( pendingTitleFlash ) {
				window.clearTimeout( pendingTitleFlash );
				pendingTitleFlash = null;
			}
			document.title = originalTitle;
		} );

		// ── Updated-X-ago indicator ─────────────────────────────────────
		function renderStatusFreshness() {
			if ( isPaused ) {
				$statusText.text( cfg.strings.paused || 'Live updates paused' );
				return;
			}
			var seconds = Math.round( ( Date.now() - lastPolledAt ) / 1000 );
			var label;
			if ( seconds < 5 ) {
				label = cfg.strings.updatedJustNow || 'Updated just now';
			} else if ( seconds < 60 ) {
				label = ( cfg.strings.updatedSecAgo || 'Updated %ds ago' ).replace( '%d', seconds );
			} else {
				var minutes = Math.floor( seconds / 60 );
				label = ( cfg.strings.updatedMinAgo || 'Updated %dm ago' ).replace( '%d', minutes );
			}
			$statusText.text( label );
		}

		// ── Card time-since timestamps ──────────────────────────────────
		function renderCardTimes() {
			$root.find( '.rp-live-card' ).each( function () {
				var $card = $( this );
				var createdAt = parseInt( $card.attr( 'data-created-at' ), 10 ) || 0;
				if ( ! createdAt ) return;
				var nowSec = Math.floor( Date.now() / 1000 );
				var seconds = nowSec - createdAt;
				var label;
				if ( seconds < 60 ) {
					label = cfg.strings.justNow || 'just now';
				} else if ( seconds < 3600 ) {
					label = ( cfg.strings.minAgo || '%dm ago' ).replace( '%d', Math.floor( seconds / 60 ) );
				} else {
					label = ( cfg.strings.hourAgo || '%dh ago' ).replace( '%d', Math.floor( seconds / 3600 ) );
				}
				$card.find( '.rp-live-card-time' ).text( label );
			} );
		}

		// 1s tick - updates freshness + card times.
		window.setInterval( function () {
			renderStatusFreshness();
			renderCardTimes();
		}, 1000 );
		renderStatusFreshness();
		renderCardTimes();

		// ── Polling ─────────────────────────────────────────────────────
		var pollHalted = false;

		// A kanban tab left open past the nonce lifetime must not go quietly
		// stale (no new cards, no sound) on an unattended restaurant tablet.
		// On an auth failure we reload the page once to mint a fresh nonce;
		// if that didn't help (reloaded recently already), halt and say so.
		function handleAuthFailure() {
			var last = parseInt( storageGet( 'rp_live_orders_reload_at' ) || '0', 10 );
			if ( Date.now() - last > 5 * 60 * 1000 ) {
				storageSet( 'rp_live_orders_reload_at', String( Date.now() ) );
				window.location.reload();
				return;
			}
			pollHalted = true;
			$statusText
				.text( cfg.strings.sessionExpired || 'Session expired - reload the page to resume live updates.' )
				.addClass( 'is-stale' );
		}

		function poll( force ) {
			if ( isPolling || pollHalted || ( isPaused && ! force ) ) return;
			isPolling = true;
			$refreshButton.addClass( 'is-busy' ).prop( 'disabled', true );
			$.ajax( {
				url:  cfg.ajaxUrl,
				type: 'POST',
				data: {
					action:          'rpress_live_orders_refresh',
					security:        cfg.nonce,
					known_order_ids: knownOrderIds
				},
				dataType: 'json'
			} ).done( function ( response ) {
				if ( ! response || ! response.success ) return;
				var data = response.data;
				applySnapshot( data );
				lastPolledAt = Date.now();
				renderStatusFreshness();
				if ( data.new_order_ids && data.new_order_ids.length ) {
					playSound();
					flashTabTitle( data.new_order_ids.length );
				}
				knownOrderIds = ( data.all_order_ids || [] ).slice();
			} ).fail( function ( xhr ) {
				if ( xhr && ( xhr.status === 403 || xhr.status === 401 ) ) {
					handleAuthFailure();
				}
				// Other failures (server hiccup, network): keep polling; the
				// "Updated Xm ago" freshness label surfaces the staleness.
			} ).always( function () {
				isPolling = false;
				$refreshButton.removeClass( 'is-busy' ).prop( 'disabled', false );
			} );
		}

		function applySnapshot( data ) {
			if ( ! data || ! data.columns ) return;
			var newIdSet = {};
			( data.new_order_ids || [] ).forEach( function ( id ) { newIdSet[ id ] = true; } );

			$.each( data.columns, function ( columnKey, col ) {
				var $col   = $root.find( '.rp-live-column[data-column="' + columnKey + '"]' );
				var $body  = $col.find( '.rp-live-column-body' );
				var $empty = $col.find( '.rp-live-column-empty' );

				// Build an order_id -> card markup map from the server HTML.
				var $serverCards = $( '<div>' + ( col.html || '' ) + '</div>' ).children( '.rp-live-card' );
				var serverIds = {};
				$serverCards.each( function () {
					serverIds[ $( this ).data( 'order-id' ) ] = this.outerHTML;
				} );

				// Remove cards that are no longer in this column.
				$body.children( '.rp-live-card' ).each( function () {
					var id = $( this ).data( 'order-id' );
					if ( ! serverIds.hasOwnProperty( id ) ) {
						$( this ).addClass( 'is-leaving' );
						var $card = $( this );
						window.setTimeout( function () { $card.remove(); maybeShowEmpty( $col ); }, 250 );
					}
				} );

				// Add or update cards.
				var existingIds = {};
				$body.children( '.rp-live-card' ).each( function () {
					existingIds[ $( this ).data( 'order-id' ) ] = $( this );
				} );

				$.each( serverIds, function ( id, markup ) {
					if ( existingIds[ id ] ) {
						// Card already in this column - refresh the server-rendered card in place.
						var $existing = existingIds[ id ];
						var $fresh    = $( markup );
						$existing.replaceWith( $fresh );
					} else {
						// New (or moved-from-another-column) card.
						var $newCard = $( markup );
						if ( newIdSet[ id ] ) {
							$newCard.addClass( 'is-new' );
							window.setTimeout( function () { $newCard.removeClass( 'is-new' ); }, 4000 );
						}
						$body.prepend( $newCard );
					}
				} );

				// Update column count + empty state.
				$col.find( '.rp-live-column-count' ).text( col.count );
				$empty.attr( 'hidden', col.count > 0 ? true : null );
			} );

			updateKpis( data.kpis || {} );
			renderCardTimes();
			applyClientFilters();
			rebindSortables();
		}

		function updateKpis( kpis ) {
			$.each( kpis, function ( key, value ) {
				$root.find( '.rp-live-kpi[data-kpi="' + key + '"] .rp-live-kpi-count' ).text( value );
			} );
		}

		$( document ).on( 'rpress:orderStatusChanged', function () {
			poll( true );
		} );

		function maybeShowEmpty( $col ) {
			var hasCards = $col.find( '.rp-live-card:not(.is-filtered-out)' ).length > 0;
			$col.find( '.rp-live-column-empty' ).attr( 'hidden', hasCards ? true : null );
			$col.find( '.rp-live-column-count' ).text( $col.find( '.rp-live-card:not(.is-filtered-out)' ).length );
		}

		function applyClientFilters() {
			$root.find( '.rp-live-card' ).each( function () {
				var $card = $( this );
				var service = ( $card.data( 'service-type' ) || '' ).toString();
				var haystack = ( $card.data( 'search' ) || '' ).toString();
				var serviceMatches = ( 'all' === activeServiceFilter || service === activeServiceFilter );
				var searchMatches = ( ! activeSearchQuery || haystack.indexOf( activeSearchQuery ) !== -1 );
				$card.toggleClass( 'is-filtered-out', ! ( serviceMatches && searchMatches ) );
			} );
			$root.find( '.rp-live-column' ).each( function () {
				maybeShowEmpty( $( this ) );
			} );
			recomputeKpisFromVisible();
		}

		// Keep the KPI tiles in step with the active type/search filter so they
		// never contradict the column counts directly below them. Each column maps
		// 1:1 to a KPI tile (data-column === data-kpi); "late" is counted from the
		// late-toned service-time badge on whichever cards remain visible.
		function recomputeKpisFromVisible() {
			var lateTotal = 0;
			$root.find( '.rp-live-column' ).each( function () {
				var $col = $( this );
				var key = $col.attr( 'data-column' );
				var $visible = $col.find( '.rp-live-card:not(.is-filtered-out)' );
				if ( key ) {
					$root.find( '.rp-live-kpi[data-kpi="' + key + '"] .rp-live-kpi-count' ).text( $visible.length );
				}
				$visible.each( function () {
					if ( $( this ).find( '.rp-live-card-service-time--late' ).length ) {
						lateTotal++;
					}
				} );
			} );
			$root.find( '.rp-live-kpi[data-kpi="late"] .rp-live-kpi-count' ).text( lateTotal );
		}

		// ── Drag-and-drop between columns ───────────────────────────────
		function bindSortables() {
			if ( ! $.fn.sortable ) return;
			$root.find( '.rp-live-column-body' ).sortable( {
				connectWith: '.rp-live-column-body',
				placeholder: 'rp-live-card-placeholder',
				items:       '> .rp-live-card',
				tolerance:   'pointer',
				forcePlaceholderSize: true,
				receive: function ( event, ui ) {
					var $card        = ui.item;
					var $newColumn   = $card.closest( '.rp-live-column' );
					var newStatus    = $newColumn.attr( 'data-default-status' );
					var orderId      = $card.attr( 'data-order-id' );
					var prevStatus   = $card.attr( 'data-status' );
					if ( ! newStatus || newStatus === prevStatus ) return;
					updateCardStatus( $card, prevStatus, newStatus );
				}
			} );
		}
		function rebindSortables() {
			if ( ! $.fn.sortable ) return;
			$root.find( '.rp-live-column-body' ).each( function () {
				if ( $( this ).hasClass( 'ui-sortable' ) ) {
					$( this ).sortable( 'refresh' );
				}
			} );
		}
		bindSortables();

		$root.on( 'click', '.rp-live-card', function ( e ) {
			if ( $( e.target ).closest( 'a, button, input, select, textarea, [role="button"]' ).length ) {
				return;
			}
			$( this ).find( '.rp-live-card-quickview' ).trigger( 'click' );
		} );

		$root.on( 'click', '.rp-live-card-action', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			var $button = $( this );
			var $card = $button.closest( '.rp-live-card' );
			var nextStatus = $button.data( 'next-status' );
			var prevStatus = $card.attr( 'data-status' );
			if ( ! nextStatus || nextStatus === prevStatus || $card.hasClass( 'is-saving' ) ) {
				return;
			}
			updateCardStatus( $card, prevStatus, nextStatus );
		} );

		function updateCardStatus( $card, prevStatus, newStatus ) {
			var orderId = $card.attr( 'data-order-id' );
			$card.attr( 'data-status', newStatus );
			$card.addClass( 'is-saving' );

			$.ajax( {
				url:      cfg.ajaxUrl,
				type:     'POST',
				dataType: 'json',
				data: {
					action:     'rpress_update_order_status',
					payment_id: orderId,
					status:     newStatus,
					security:   ( window.rp_orders_params && rp_orders_params.order_nonce ) || ''
				}
			} ).done( function () {
				$card.removeClass( 'is-saving' ).addClass( 'is-saved' );
				window.setTimeout( function () { $card.removeClass( 'is-saved' ); }, 1200 );
				// Force a quick poll so the column counts / placement get authoritative.
				window.setTimeout( poll, 200 );
				$( document ).trigger( 'rpress:orderStatusChanged', [ orderId, newStatus, prevStatus ] );
			} ).fail( function () {
				$card.removeClass( 'is-saving' ).addClass( 'is-error' );
				window.setTimeout( function () { $card.removeClass( 'is-error' ); }, 2000 );
				// Revert (best-effort).
				$card.attr( 'data-status', prevStatus );
			} );
		}

		// Kick off polling.
		var pollIntervalMs = Math.max( 10, cfg.pollInterval || 30 ) * 1000;
		window.setInterval( poll, pollIntervalMs );
	} );
} )( jQuery );
