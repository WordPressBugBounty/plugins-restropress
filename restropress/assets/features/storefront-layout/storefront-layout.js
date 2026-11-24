/**
 * RestroPress storefront layout components (3.5.1).
 *
 * - Rails: paddles scroll the row; a card opens the dish's own item popup by
 *   pressing that dish's add button in the menu, so the cart flow stays core's.
 * - Cart drawer (desktop, body.rp-sf-cart-drawer): a floating button with the
 *   count and total opens the side cart as a drawer. Focus moves into it, Tab
 *   stays inside, Escape or the backdrop closes it and focus returns.
 */
(function ($) {
	'use strict';

	var cfg = window.rpress_storefront_layout || {};
	var DESKTOP = window.matchMedia ? window.matchMedia('(min-width: 1025px)') : { matches: true, addListener: function () {} };

	function t(key, fallback) {
		return (cfg.i18n && cfg.i18n[key]) || fallback;
	}

	/* -------------------------------------------------------------- */
	/* Rails                                                            */
	/* -------------------------------------------------------------- */
	function setupRails() {
		$('.rp-sf-rail').each(function () {
			var $rail = $(this);
			var track = $rail.find('.rp-sf-rail-track')[0];
			var $prev = $rail.find('.rp-sf-paddle.is-prev');
			var $next = $rail.find('.rp-sf-paddle.is-next');

			// Drop cards whose dish is not on this menu (filtered categories).
			$rail.find('.rp-sf-rail-card').each(function () {
				var id = $(this).attr('data-fooditem-id');
				if (!$('#rpress_fooditem_' + id).length) {
					$(this).closest('.rp-sf-rail-item').remove();
				}
			});
			if (!$rail.find('.rp-sf-rail-item').length) {
				$rail.remove();
				return;
			}

			function sync() {
				var max = track.scrollWidth - track.clientWidth - 2;
				$prev.prop('disabled', track.scrollLeft <= 2);
				$next.prop('disabled', track.scrollLeft >= max);
				$rail.find('.rp-sf-rail-paddles').toggle(max > 2);
			}
			function step(dir) {
				var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
				track.scrollBy({ left: dir * track.clientWidth * 0.8, behavior: reduce ? 'auto' : 'smooth' });
			}
			$prev.on('click', function () { step(-1); });
			$next.on('click', function () { step(1); });
			track.addEventListener('scroll', sync, { passive: true });
			$(window).on('resize', sync);
			sync();
		});

		$(document).on('click', '.rp-sf-rail-card', function (e) {
			e.preventDefault();
			var id = $(this).attr('data-fooditem-id');
			var add = $('#rpress_fooditem_' + id + ' .rpress-add-to-cart').first()[0];
			if (add) {
				add.click();
			}
		});
	}

	/* -------------------------------------------------------------- */
	/* Cart drawer                                                      */
	/* -------------------------------------------------------------- */
	function setupDrawer() {
		if (!$('body').hasClass('rp-sf-cart-drawer')) {
			return;
		}
		var $menu = $('.rpress-menu-v2').has('.rpress_fooditems_list').first();
		var $drawer = $menu.children('.rpress-sidebar-cart').first();
		if (!$drawer.length) {
			return;
		}
		$drawer.attr({ id: $drawer.attr('id') || 'rp-sf-cart-drawer', 'aria-label': t('cart', 'Your order') });

		var $close = $('<button type="button" class="rp-sf-drawer-close"><span aria-hidden="true">&times;</span></button>')
			.attr('aria-label', t('close', 'Close'));
		$drawer.prepend($close);
		var $backdrop = $('<div class="rp-sf-drawer-backdrop" aria-hidden="true"></div>').appendTo(document.body);
		var $fab = $('<button type="button" class="rp-sf-cart-fab" hidden><span class="rp-sf-cart-fab-count"></span><span class="rp-sf-cart-fab-label"></span><span class="rp-sf-cart-fab-total"></span></button>')
			.attr('aria-controls', $drawer.attr('id'))
			.attr('aria-expanded', 'false')
			.appendTo(document.body);
		$fab.find('.rp-sf-cart-fab-label').text(t('open_cart', 'View cart'));

		var lastFocus = null;

		function isOpen() {
			return $('body').hasClass('rp-sf-cart-open');
		}
		function focusables() {
			return $drawer.find('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
		}
		function open() {
			if (!DESKTOP.matches || isOpen()) {
				return;
			}
			lastFocus = document.activeElement;
			$('body').addClass('rp-sf-cart-open');
			$drawer.attr({ role: 'dialog', 'aria-modal': 'true' });
			$fab.attr('aria-expanded', 'true');
			setTimeout(function () { $close.trigger('focus'); }, 30);
		}
		function close() {
			if (!isOpen()) {
				return;
			}
			$('body').removeClass('rp-sf-cart-open');
			$drawer.removeAttr('role aria-modal');
			$fab.attr('aria-expanded', 'false');
			var back = lastFocus && lastFocus !== document.body && document.contains(lastFocus) && $(lastFocus).is(':visible') ? lastFocus : $fab[0];
			if (back && back.focus) {
				back.focus();
			}
		}

		$fab.on('click', open);
		$close.on('click', close);
		$backdrop.on('click', close);
		$(document).on('keydown', function (e) {
			if (!isOpen()) {
				return;
			}
			if (e.key === 'Escape') {
				// The item popup can sit above the drawer; let it close first.
				if ($('#rpressModal').hasClass('is-open')) {
					return;
				}
				e.preventDefault();
				close();
				return;
			}
			if (e.key === 'Tab') {
				var $f = focusables();
				if (!$f.length) {
					return;
				}
				var first = $f[0];
				var last = $f[$f.length - 1];
				if (e.shiftKey && document.activeElement === first) {
					e.preventDefault();
					last.focus();
				} else if (!e.shiftKey && document.activeElement === last) {
					e.preventDefault();
					first.focus();
				} else if (!$drawer[0].contains(document.activeElement)) {
					e.preventDefault();
					first.focus();
				}
			}
		});

		// Leaving desktop width closes the drawer (phones use the cart sheet).
		var onChange = function () {
			if (!DESKTOP.matches) {
				close();
			}
			sync();
		};
		if (DESKTOP.addEventListener) {
			DESKTOP.addEventListener('change', onChange);
		} else {
			DESKTOP.addListener(onChange);
		}

		// Count and total come from the cart bar core keeps up to date.
		function toInt(text) {
			var n = parseInt(String(text || '').replace(/[^0-9]/g, ''), 10);
			return isNaN(n) ? 0 : n;
		}
		function sync() {
			var $bar = $('.rpress-mobile-cart-icons').first();
			var qty = toInt($bar.find('.rp-mb-quantity').first().text());
			if (!qty) {
				// No cart bar (old markup): count the cart lines.
				qty = $('ul.rpress-cart').first().children('li.rpress-cart-item').length;
			}
			var total = $.trim($bar.find('.rp-mb-price').first().text());
			$fab.find('.rp-sf-cart-fab-count').text(qty);
			$fab.find('.rp-sf-cart-fab-total').text(total);
			var byCount = (window.rpress_storefront_layout || {}).items_by_count;
			var items = byCount && byCount[qty] ? byCount[qty] : (qty === 1 ? t('items_one', '%d item') : t('items_many', '%d items')).replace('%d', qty);
			$fab.attr('aria-label', t('open_cart', 'View cart') + ', ' + items + (total ? ', ' + total : ''));
			$fab.prop('hidden', qty < 1 || !DESKTOP.matches);
			if (qty < 1) {
				close();
			}
		}
		sync();
		$(document.body).on('rpress_items_updated rpress_cart_item_removed rpress_added_to_cart rpress_cart_item_added rpress_quantity_updated', function () {
			setTimeout(sync, 80);
		});
		if (window.MutationObserver) {
			var timer = null;
			var mo = new MutationObserver(function () {
				clearTimeout(timer);
				timer = setTimeout(sync, 60);
			});
			$('.rpress-mobile-cart-icons, ul.rpress-cart').each(function () {
				mo.observe(this, { childList: true, characterData: true, subtree: true });
			});
		}
	}

	$(function () {
		setupRails();
		setupDrawer();
	});
})(jQuery);
