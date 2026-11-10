/**
 * RestroPress block storefront (3.5): shared Interactivity API store.
 *
 * Plain ES module, no build step: it imports @wordpress/interactivity from
 * WordPress core. Every storefront block reads the "restropress" store, so
 * search, category filtering and the cart stay in sync wherever the blocks
 * sit on the page.
 *
 * The item popup, add-to-cart and service selection stay in rp-frontend.js
 * (jQuery). This module listens to the events that code already triggers
 * (rpress_added_to_cart, rpress_items_updated, ...) and refreshes the cart
 * state from the server, so there is one source of truth for add-ons,
 * variations and totals.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

const NS = 'restropress';

/**
 * Same normalization as rpress_block_storefront_normalize() in PHP.
 *
 * @param {string} text Text.
 * @return {string} Normalized text.
 */
const normalize = ( text ) =>
	String( text || '' )
		.toLowerCase()
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.replace( /[^\p{L}\p{N}\s]+/gu, ' ' )
		.replace( /\s+/g, ' ' )
		.trim();

const matches = ( title, query ) => ! query || String( title || '' ).indexOf( query ) !== -1;

const sprintf = ( format, ...args ) => {
	let i = 0;
	return String( format || '' ).replace( /%(?:(\d+)\$)?[sd]/g, ( match, pos ) => {
		const value = pos ? args[ Number( pos ) - 1 ] : args[ i++ ];
		return value === undefined ? '' : String( value );
	} );
};

const { state, actions } = store( NS, {
	state: {
		get normalizedQuery() {
			return normalize( state.query );
		},
		get isItemHidden() {
			const ctx = getContext();
			if ( state.filterCategory && ctx.cat !== state.filterCategory ) {
				return true;
			}
			return ! matches( ctx.title, state.normalizedQuery );
		},
		get isSectionHidden() {
			const ctx = getContext();
			if ( state.filterCategory && ctx.cat !== state.filterCategory ) {
				return true;
			}
			const query = state.normalizedQuery;
			if ( ! query ) {
				return false;
			}
			return ! ( ctx.titles || [] ).some( ( title ) => matches( title, query ) );
		},
		get hasNoResults() {
			const ctx = getContext();
			const query = state.normalizedQuery;
			if ( ! query ) {
				return false;
			}
			return ! ( ctx.titles || [] ).some( ( title ) => matches( title, query ) );
		},
		get noResultsText() {
			return sprintf( state.i18n && state.i18n.noResults, state.query );
		},
		get isCategoryActive() {
			const ctx = getContext();
			const current = ctx.filter ? state.filterCategory : state.activeCategory;
			return ctx.cat === current;
		},
		get categoryCurrent() {
			const ctx = getContext();
			// null drops the attribute. false would render aria-current="false",
			// which still matches [aria-current] selectors.
			if ( ! state.isCategoryActive ) {
				return null;
			}
			return ctx.filter ? 'true' : 'location';
		},
		get checkoutDisabled() {
			return state.busy || ! ( state.store && state.store.open ) ? 'true' : false;
		},
	},
	actions: {
		setQuery( event ) {
			state.query = event.target.value;
		},
		clearQuery() {
			state.query = '';
			const { ref } = getElement();
			const input = ref && ref.closest( 'form' ) && ref.closest( 'form' ).querySelector( 'input[type="search"]' );
			if ( input ) {
				input.focus();
			}
		},
		searchKeydown( event ) {
			if ( event.key === 'Escape' && state.query ) {
				event.preventDefault();
				state.query = '';
			}
		},
		preventSubmit( event ) {
			event.preventDefault();
		},
		selectCategory( event ) {
			const ctx = getContext();
			if ( ctx.filter ) {
				event.preventDefault();
				state.filterCategory = ctx.cat;
				state.activeCategory = ctx.cat;
				return;
			}
			// Jump mode: the browser follows the anchor (sections carry
			// scroll-margin for the sticky nav). Move focus to the heading so
			// keyboard and screen reader users land in the section.
			state.activeCategory = ctx.cat;
			const section = document.getElementById( 'rpress-sf-cat-' + ctx.cat );
			if ( section ) {
				event.preventDefault();
				section.scrollIntoView( { behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' } );
				const heading = section.querySelector( '.rpress-sf-section-title' );
				if ( heading ) {
					heading.focus( { preventScroll: true } );
				}
				if ( window.history && window.history.replaceState ) {
					window.history.replaceState( null, '', '#rpress-sf-cat-' + ctx.cat );
				}
			}
		},
		*increment() {
			const ctx = getContext();
			yield* changeQuantity( ctx.line.key, Number( ctx.line.quantity ) + 1 );
		},
		*decrement() {
			const ctx = getContext();
			yield* changeQuantity( ctx.line.key, Number( ctx.line.quantity ) - 1 );
		},
		*checkout( event ) {
			event.preventDefault();
			if ( state.busy || ! state.cart.hasItems ) {
				return;
			}
			state.error = '';
			if ( state.store && ! state.store.open ) {
				state.error = state.store.message;
				return;
			}
			state.busy = true;
			try {
				const response = yield post( {
					action: 'rpress_proceed_checkout',
					security: state.nonces.checkout,
				} );
				if ( response && response.status === 'error' ) {
					state.error = response.error_msg || ( state.i18n && state.i18n.cartError ) || '';
					if ( response.error_type === 'service_time_not_set' ) {
						openServicePopup();
					}
					return;
				}
				window.location.assign( state.checkoutUrl );
			} catch ( e ) {
				state.error = ( state.i18n && state.i18n.cartError ) || '';
			} finally {
				state.busy = false;
			}
		},
		*refreshCart() {
			yield refresh();
		},
	},
	callbacks: {
		initGrid() {
			// The classic add button falls back to clicking #editDateTime when
			// no service time is chosen. Pages without a Service Bar block have
			// no such link, so provide a hidden one; it opens the existing
			// date and time popup.
			if ( ! document.getElementById( 'editDateTime' ) ) {
				const trigger = document.createElement( 'a' );
				trigger.id = 'editDateTime';
				trigger.href = '#';
				trigger.hidden = true;
				trigger.className = 'rpress-sf-service-trigger';
				document.body.appendChild( trigger );
			}
		},
		initScrollspy() {
			const ctx = getContext();
			if ( ctx.filter || typeof window.IntersectionObserver !== 'function' ) {
				return;
			}
			const sections = Array.prototype.slice.call( document.querySelectorAll( '[data-rpress-sf-section]' ) );
			if ( ! sections.length ) {
				return;
			}
			const visible = new Map();
			const observer = new window.IntersectionObserver(
				( entries ) => {
					entries.forEach( ( entry ) => {
						if ( entry.isIntersecting ) {
							visible.set( entry.target, entry.boundingClientRect.top );
						} else {
							visible.delete( entry.target );
						}
					} );
					// The topmost section inside the band wins.
					let best = null;
					sections.forEach( ( section ) => {
						if ( best === null && visible.has( section ) && ! section.hidden ) {
							best = section;
						}
					} );
					if ( best ) {
						state.activeCategory = best.getAttribute( 'data-rpress-sf-section' );
					}
				},
				{ rootMargin: '-20% 0px -65% 0px', threshold: 0 }
			);
			sections.forEach( ( section ) => observer.observe( section ) );
			return () => observer.disconnect();
		},
		revealActiveCategory() {
			const ctx = getContext();
			const current = ctx.filter ? state.filterCategory : state.activeCategory;
			const { ref } = getElement();
			if ( ! ref || ! current ) {
				return;
			}
			const list = ref.querySelector( '.rpress-sf-catnav-list' );
			const link = list && list.querySelector( 'a[href="#rpress-sf-cat-' + current + '"]' );
			if ( ! list || ! link || list.scrollWidth <= list.clientWidth ) {
				return;
			}
			// Scroll the strip only (never the page) so the active chip shows.
			const target = link.offsetLeft - ( list.clientWidth - link.offsetWidth ) / 2;
			list.scrollTo( { left: Math.max( 0, target ), behavior: prefersReducedMotion() ? 'auto' : 'smooth' } );
		},
	},
} );

function prefersReducedMotion() {
	return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
}

function post( data ) {
	const body = new window.URLSearchParams();
	Object.keys( data ).forEach( ( key ) => body.append( key, data[ key ] ) );
	return window
		.fetch( state.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		} )
		.then( ( r ) => r.json() );
}

/**
 * Fetch the cart state (rpress_storefront_cart) and apply it. Plain async
 * function so it can run outside directive scope (jQuery event bridge).
 *
 * @return {Promise<void>}
 */
function refresh() {
	const url = state.ajaxUrl + ( state.ajaxUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'action=rpress_storefront_cart';
	return window
		.fetch( url, { credentials: 'same-origin' } )
		.then( ( r ) => r.json() )
		.then( ( response ) => {
			if ( response && response.success && response.data ) {
				applyCart( response.data );
			}
		} )
		.catch( () => {
			// Keep the last known state; the next cart event retries.
		} );
}

function applyCart( data ) {
	if ( data.cart ) {
		state.cart = data.cart;
	}
	if ( data.store ) {
		state.store = data.store;
	}
	state.announcement = sprintf( state.i18n && state.i18n.cartUpdated, state.cart.countLabel, state.cart.total );
}

function* changeQuantity( key, quantity ) {
	if ( state.busy ) {
		return;
	}
	state.busy = true;
	state.error = '';
	try {
		const response = yield post( {
			action: 'rpress_cart_item_quantity',
			cart_key: key,
			quantity: Math.max( 0, quantity ),
			security: state.nonces.quantity,
		} );
		if ( response && response.success === false ) {
			state.error = ( state.i18n && state.i18n.cartError ) || '';
		}
		yield refresh();
		// Let the classic cart widgets (sidebar cart, mobile bar) resync too.
		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'rpress_storefront_cart_changed', [ response ] );
		}
	} catch ( e ) {
		state.error = ( state.i18n && state.i18n.cartError ) || '';
	} finally {
		state.busy = false;
	}
}

function openServicePopup() {
	const trigger = document.getElementById( 'editDateTime' );
	if ( trigger && window.jQuery ) {
		window.jQuery( trigger ).trigger( 'click' );
	}
}

// Bridge: the classic jQuery flows announce cart and service changes with
// jQuery events. Refresh the shared cart state whenever one fires.
const CART_EVENTS = [
	'rpress_added_to_cart',
	'rpress_items_updated',
	'rpress_cart_item_removed',
	'rpress_cart_item_added',
	'rpress_checked_slots',
	'rp-refresh-checkout-service',
].join( ' ' );

if ( window.jQuery ) {
	window.jQuery( document.body ).on( CART_EVENTS, () => {
		refresh();
	} );
}
