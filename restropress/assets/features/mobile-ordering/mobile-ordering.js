/**
 * RestroPress 3.5: mobile ordering layer for the [fooditems] menu page.
 *
 * Works on top of rp-frontend.js: it reuses the core add-to-cart, cart
 * quantity (rpress_cart_item_quantity), remove and service/time flows and
 * only changes how they are presented. See
 * includes/features/mobile-ordering/loader.php for the feature summary.
 */
(function ($) {
	'use strict';

	var cfg = window.rpress_mobile_ordering || {};
	var i18n = cfg.i18n || {};
	var PHONE = window.matchMedia ? window.matchMedia('(max-width: 767px)') : { matches: false, addListener: function () {} };

	function t(key, fallback) {
		return i18n[key] || fallback;
	}
	function fmt(str, n) {
		return String(str).replace('%d', n);
	}
	function toInt(text) {
		var n = parseInt(String(text || '').replace(/[^0-9]/g, ''), 10);
		return isNaN(n) ? 0 : n;
	}

	var $menu = $();

	/* -------------------------------------------------------------- */
	/* 1. Store card: refresh, and "Schedule for later"                */
	/* -------------------------------------------------------------- */
	var storeTimer = null;
	function refreshStore() {
		if (!cfg.ajaxurl || !$('.rp-mo-store').length) {
			return;
		}
		$.get(cfg.ajaxurl, { action: 'rpress_mobile_ordering_store' }, function (res) {
			if (!res || !res.success || !res.data || !res.data.html) {
				return;
			}
			var $old = $('.rp-mo-store').first();
			var $new = $(res.data.html);
			if ($old.length && $old.prop('outerHTML') !== $new.prop('outerHTML')) {
				$old.replaceWith($new);
			}
		});
	}
	function queueStoreRefresh() {
		clearTimeout(storeTimer);
		storeTimer = setTimeout(refreshStore, 400);
	}

	function openTimePicker() {
		// Core opens the date/time picker from #editDateTime. A closed store
		// prints no Edit link, so add a hidden one for the core handler.
		var $edit = $('#editDateTime');
		if (!$edit.length) {
			$edit = $('<a id="editDateTime" class="rpress-edit-datetime-link" hidden></a>').appendTo($menu.first());
		}
		$edit.trigger('click');
	}

	/* -------------------------------------------------------------- */
	/* 2. Phones: search in the sticky bar, compact service/time row   */
	/* -------------------------------------------------------------- */
	var SEARCH_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>';

	function setupSearch() {
		var $wrap = $menu.find('.rpress-mobile-menu-wrap').first();
		var $bar = $wrap.find('.make-me-sticky').first();
		var $col = $menu.find('.rpress-options-row > .rpress-search-wrap').first();
		var $search = $col.children('.rpress-search-wrap').first();
		if (!$wrap.length || !$bar.length || !$search.length) {
			return;
		}
		var $slot = $('<div class="rp-mo-search-slot"></div>').appendTo($wrap);
		var $btn = $('<button type="button" class="rp-mo-search-btn" aria-expanded="false"></button>')
			.attr('aria-label', t('search', 'Search menu'))
			.html(SEARCH_ICON)
			.appendTo($bar);

		function place() {
			// The allergen filter shares the search column, which is hidden on
			// phones: it moves into the search drop-down with the box.
			var $filters = $col.children().not($search);
			if (PHONE.matches) {
				if (!$slot.children().length) {
					$search.appendTo($slot);
					$filters.addClass('rp-mo-moved-filter').appendTo($slot);
					$col.addClass('rp-mo-moved');
				}
			} else if ($slot.children().length) {
				$search.appendTo($col);
				$slot.children('.rp-mo-moved-filter').removeClass('rp-mo-moved-filter').appendTo($col);
				$col.removeClass('rp-mo-moved');
				$menu.removeClass('rp-mo-search-open');
				$btn.attr('aria-expanded', 'false');
			}
		}
		place();
		// Allergen panel as a bottom sheet on phones: the cart bar and the
		// floating Menu button step aside while it is open.
		$slot[0].addEventListener('toggle', function (e) {
			if (e.target && e.target.tagName === 'DETAILS') {
				$('body').toggleClass('rp-mo-filter-open', PHONE.matches && e.target.open);
			}
		}, true);
		if (PHONE.addEventListener) {
			PHONE.addEventListener('change', place);
		} else {
			PHONE.addListener(place);
		}

		$btn.on('click', function () {
			var open = !$menu.hasClass('rp-mo-search-open');
			$menu.toggleClass('rp-mo-search-open', open);
			$btn.attr('aria-expanded', open ? 'true' : 'false');
			if (open) {
				$('#rpress-food-search').trigger('focus');
			}
		});
		// A search in progress keeps the box open.
		if ($.trim($('#rpress-food-search').val() || '')) {
			$btn.trigger('click');
		}
	}

	function compactTimeRow() {
		var $row = $menu.find('.rpress-options-row').first();
		if (!$row.length) {
			return;
		}
		$row.addClass('rp-mo-compact');
		labelServiceDate();
	}

	function labelServiceDate() {
		var $date = $menu.find('.rpress-order-address-summary #deliveryDate').first();
		if (!$date.length) {
			return;
		}
		var text = $.trim($date.text()).replace(/,$/, '');
		$date.removeClass('rp-mo-today');
		if (cfg.today && text === cfg.today) {
			$date.addClass('rp-mo-today');
		} else if (cfg.tomorrow && text === cfg.tomorrow && !$date.attr('data-rp-mo-label')) {
			$date.attr('data-rp-mo-label', '1').text(t('tomorrow', 'Tomorrow'));
		}
	}

	/* -------------------------------------------------------------- */
	/* 3. Cart bar and cart sheet                                      */
	/* -------------------------------------------------------------- */
	function setupCartBar() {
		var $bar = $menu.find('.rpress-mobile-cart-icons').first();
		if (!$bar.length) {
			return;
		}
		$bar.addClass('rp-mo-cartbar').attr({ role: 'button', tabindex: '0' });
		var $label = $('<span class="rp-mo-cartbar-label"><span class="rp-mo-cartbar-count"></span><span class="rp-mo-cartbar-text"></span></span>');
		var $total = $('<span class="rp-mo-cartbar-total"></span>');
		$bar.append($label, $total);
		$label.find('.rp-mo-cartbar-text').text(t('view_cart', 'View cart'));
		// A minimum-order hint printed before this ran sits in the hidden
		// left column; the checkout UX script keeps it on the bar from now on.
		$bar.find('.rp-cart-left-wrap .rpress-cux-min-hint.is-bar').appendTo($bar);

		function sync() {
			var qty = toInt($bar.find('.rp-mb-quantity').first().text());
			$label.find('.rp-mo-cartbar-count').text(qty);
			$total.text($.trim($bar.find('.rp-mb-price').first().text()));
			$bar.attr('aria-label', t('view_cart', 'View cart') + ', ' + fmt(qty === 1 ? t('items_one', '%d item') : t('items_many', '%d items'), qty) + ', ' + $total.text());
			var shown = qty > 0 && $bar.css('display') !== 'none';
			$('body').toggleClass('rp-mo-has-cartbar', shown)
				.toggleClass('rp-mo-has-hint', shown && $bar.children('.rpress-cux-min-hint.is-bar').length > 0);
		}
		sync();
		if (window.MutationObserver) {
			var mo = new MutationObserver(sync);
			$bar.find('.rp-mb-quantity, .rp-mb-price').each(function () {
				mo.observe(this, { childList: true, characterData: true, subtree: true });
			});
			mo.observe($bar[0], { attributes: true, attributeFilter: ['style'], childList: true });
		}

		// The whole bar opens the cart sheet; the sheet has the Checkout button.
		function openSheet(e) {
			if (e) {
				e.preventDefault();
				e.stopPropagation();
			}
			$bar.find('.rp-cart-left-wrap').trigger('click');
		}
		$bar[0].addEventListener('click', openSheet, true);
		$bar.on('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				openSheet(e);
			}
		});

		// Close the sheet: backdrop tap, Escape, swipe down at the top.
		$(document).on('click', function (e) {
			if ($('body').hasClass('rp-mobile-cart-open') && e.target === document.body) {
				$('.close-cart-ic').first().trigger('click');
			}
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $('body').hasClass('rp-mobile-cart-open')) {
				$('.close-cart-ic').first().trigger('click');
			}
		});
		var $sheet = $menu.find('.rpress-sidebar-main-wrap').first();
		swipeToClose($sheet[0], function () {
			$('.close-cart-ic').first().trigger('click');
		}, function () {
			return PHONE.matches && $('body').hasClass('rp-mobile-cart-open');
		});
	}

	// Drag a sheet down from its top edge to dismiss it.
	function swipeToClose(el, close, active) {
		if (!el) {
			return;
		}
		var startY = null;
		var dy = 0;
		// True when the touch starts inside something scrolled down (the
		// legacy popup scrolls an inner panel): then the drag scrolls it.
		function insideScrolled(node) {
			for (; node && node !== el.parentNode; node = node.parentNode) {
				if (node.scrollTop > 0) {
					return true;
				}
			}
			return false;
		}
		el.addEventListener('touchstart', function (e) {
			if (!active() || e.touches.length !== 1 || insideScrolled(e.target)) {
				startY = null;
				return;
			}
			startY = e.touches[0].clientY;
			dy = 0;
		}, { passive: true });
		el.addEventListener('touchmove', function (e) {
			if (startY === null) {
				return;
			}
			dy = e.touches[0].clientY - startY;
			if (dy > 0 && !insideScrolled(e.target)) {
				el.classList.add('rp-mo-dragging');
				el.style.transform = 'translateY(' + dy + 'px)';
			}
		}, { passive: true });
		el.addEventListener('touchend', function () {
			if (startY === null) {
				return;
			}
			el.classList.remove('rp-mo-dragging');
			el.style.transform = '';
			if (dy > 110) {
				close();
			}
			startY = null;
		});
	}

	/* -------------------------------------------------------------- */
	/* 4. Dish cards: tap anywhere, stepper once in the cart           */
	/* -------------------------------------------------------------- */
	function cartLines() {
		var byItem = {};
		$('ul.rpress-cart').first().children('li.rpress-cart-item').each(function () {
			var $li = $(this);
			var id = String($li.find('[data-item-id]').first().attr('data-item-id') || $li.find('[data-fooditem-id]').first().attr('data-fooditem-id') || '');
			if (!id) {
				return;
			}
			var qty = toInt($li.find('.rpress-cart-item-qty').first().text()) || 1;
			(byItem[id] = byItem[id] || []).push({ key: String($li.attr('data-cart-key') || ''), qty: qty });
		});
		return byItem;
	}

	function syncCards() {
		var lines = cartLines();
		$menu.find('.rpress_fooditem').each(function () {
			var $card = $(this);
			var $add = $card.find('.rpress-add-to-cart').first();
			var $wrap = $add.closest('.rpress_purchase_submit_wrapper');
			if (!$add.length || !$wrap.length) {
				return;
			}
			var id = String($add.attr('data-fooditem-id') || '');
			var mine = lines[id] || [];
			var $stepper = $wrap.children('.rp-mo-stepper');
			var $badge = $wrap.children('.rp-mo-incart');

			var total = 0;
			$.each(mine, function (i, l) {
				total += l.qty;
			});

			// Phones: one cart line shows a - n + stepper in place of ADD.
			// Desktop keeps ADD (the cart is always on screen) with a count.
			if (mine.length === 1 && mine[0].key) {
				if (!$stepper.length) {
					$stepper = $('<div class="rpress-qty-stepper rp-mo-stepper" role="group"></div>')
						.append($('<button type="button" class="rp-qty-btn rp-qty-dec"></button>').attr('aria-label', t('decrease', 'Remove one')))
						.append('<span class="rp-qty-val" aria-live="polite"></span>')
						.append($('<button type="button" class="rp-qty-btn rp-qty-inc"></button>').attr('aria-label', t('increase', 'Add one more')))
						.appendTo($wrap);
				}
				$stepper.attr('data-cart-key', mine[0].key).attr('aria-label', $.trim($add.attr('data-title') || ''));
				$stepper.find('.rp-qty-val').text(mine[0].qty);
				// At 1 the minus shows a bin (core stepper icons), as it removes the dish.
				$stepper.toggleClass('is-one', mine[0].qty <= 1);
				$card.addClass('rp-mo-has-stepper');
			} else {
				$stepper.remove();
				$card.removeClass('rp-mo-has-stepper');
			}
			if (total > 0) {
				if (!$badge.length) {
					$badge = $('<span class="rp-mo-incart"></span>').appendTo($wrap);
				}
				$badge.text(total).attr('aria-label', fmt(t('in_cart', '%d in cart'), total));
			} else {
				$badge.remove();
			}
		});
	}

	function setupCards() {
		$menu.find('.rpress_fooditem').addClass('rp-mo-tappable');
		syncCards();
		$(document.body).on('rpress_items_updated rpress_cart_item_removed rpress_added_to_cart rpress_cart_item_added rpress_quantity_updated', function () {
			setTimeout(syncCards, 50);
		});
		if (window.MutationObserver) {
			var $cart = $('ul.rpress-cart').first();
			if ($cart.length) {
				new MutationObserver(function () {
					clearTimeout(setupCards.timer);
					setupCards.timer = setTimeout(syncCards, 60);
				}).observe($cart[0], { childList: true, subtree: true, characterData: true });
			}
		}

		// At 1 the minus removes the line. The core stepper handler looks for
		// the remove link inside the cart line, which a card stepper is not,
		// so take this click first (capture phase) and use the line's link.
		document.addEventListener('click', function (e) {
			var dec = e.target.closest ? e.target.closest('.rp-mo-stepper .rp-qty-dec') : null;
			if (!dec) {
				return;
			}
			var stepper = dec.closest('.rp-mo-stepper');
			if (toInt($(stepper).find('.rp-qty-val').text()) > 1) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			var key = stepper.getAttribute('data-cart-key');
			$('ul.rpress-cart li.rpress-cart-item[data-cart-key="' + key + '"]').first().find('.rpress-remove-from-cart').first().trigger('click');
		}, true);

		// Tap anywhere on a dish card to open it, as on delivery apps.
		$menu.on('click', '.rpress_fooditem.rp-mo-tappable', function (e) {
			if ($(e.target).closest('a, button, input, select, textarea, label, summary, details, .rp-mo-stepper, .rpress_purchase_submit_wrapper, [role="button"]').length) {
				return;
			}
			var $add = $(this).find('.rpress-add-to-cart').first();
			if ($add.length) {
				e.preventDefault();
				$add.trigger('click');
			}
		});
	}

	/* -------------------------------------------------------------- */
	/* 5. Item popup: sheet on phones, Required / Optional labels      */
	/* -------------------------------------------------------------- */
	function labelOptions() {
		var $modal = $('#rpressModal');
		var $content = $modal.find('.modal__content');
		if (!$content.length) {
			return;
		}
		// Sizes (variable prices): one is always chosen.
		$content.find('.rp-variable-price-wrapper').each(function () {
			var $h = $(this).prev('h6');
			if ($h.length && !$h.find('.rp-mo-rule').length) {
				$h.append($('<span class="rp-mo-rule is-required"></span>').text(t('required', 'Required')));
			}
		});
		$content.find('.addons-wrapper').each(function () {
			var $w = $(this);
			var $h = $w.children('h6').first();
			if (!$h.length || $h.find('.rp-mo-rule').length) {
				return;
			}
			var required = $w.find('.addon_is_required').val() === 'yes';
			var min = toInt($w.find('.addon_min_limit').val());
			var max = toInt($w.find('.addon_max_limit').val());
			var text;
			if (required) {
				text = t('required', 'Required');
				if (min > 1 || (min === 1 && max === 1)) {
					text += ' · ' + fmt(t('pick_n', 'pick %d'), Math.max(min, 1));
				}
			} else {
				text = t('optional', 'Optional');
				if (max > 0) {
					text += ' · ' + fmt(t('pick_up_to', 'up to %d'), max);
				}
			}
			$h.append($('<span class="rp-mo-rule"></span>').toggleClass('is-required', required).text(text));
		});
		$modal.addClass('rp-mo-labelled');
	}

	function setupPopup() {
		var modal = document.getElementById('rpressModal');
		if (!modal) {
			return;
		}
		if (window.MutationObserver) {
			var timer = null;
			new MutationObserver(function () {
				clearTimeout(timer);
				timer = setTimeout(labelOptions, 30);
			}).observe(modal, { childList: true, subtree: true });
		}
		var container = modal.querySelector('.modal__container');
		swipeToClose(container, function () {
			var close = modal.querySelector('.modal__close');
			if (close) {
				close.click();
			}
		}, function () {
			return PHONE.matches && modal.classList.contains('is-open');
		});
	}

	/* -------------------------------------------------------------- */
	$(function () {
		$menu = $('.rpress-menu-v2').has('.rpress_fooditems_list');
		var popupOnly = !!(window.rpress_mobile_ordering && rpress_mobile_ordering.popup_only);
		if (popupOnly || !$menu.length) {
			// Checkout upsell and legacy menus: only the item popup (labels, phone sheet).
			if (document.getElementById('rpressModal')) {
				setupPopup();
			}
			return;
		}
		$('body').addClass('rp-mo-sheet-cart rp-mo-sheet-item');

		setupSearch();
		compactTimeRow();
		setupCartBar();
		setupCards();
		setupPopup();

		// Whole time pill opens the date/time picker.
		$menu.on('click', '.rp-mo-compact .rpress-order-address-inline', function (e) {
			if (!PHONE.matches || $(e.target).closest('a, button').length) {
				return;
			}
			$('#editDateTime').trigger('click');
		});
		$menu.on('click', '.rp-mo-schedule', function (e) {
			e.preventDefault();
			openTimePicker();
		});

		// Page caches can serve an old card; service/time changes alter it.
		refreshStore();
		$(document).ajaxComplete(function (event, xhr, settings) {
			var data = settings && typeof settings.data === 'string' ? settings.data : '';
			if (data.indexOf('rpress_mobile_ordering_store') !== -1) {
				return;
			}
			if (/service|delivery_time|rpress_check_service_slot/.test(data)) {
				queueStoreRefresh();
				setTimeout(labelServiceDate, 300);
			}
		});
	});
})(jQuery);
