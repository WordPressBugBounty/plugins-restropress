/**
 * RestroPress checkout UX (3.5).
 *
 * Enhancement layer over the redesigned checkout and the cart sidebar. It
 * never posts an order itself; it reacts to the existing AJAX flows:
 *
 * 1. Live minimum-order hint ("Add ₹40.00 more for pickup") at checkout, in
 *    the sidebar cart and in the mobile cart bar. Place order stays disabled
 *    while the cart is below the minimum; the server check remains the
 *    source of truth.
 * 2. Delivery/pickup switch: typed fields survive the order-details
 *    re-render, and the total change is shown and announced.
 * 3. Inline validation on blur and on submit with aria-invalid and
 *    aria-describedby messages; the first invalid field is focused and
 *    scrolled into view.
 *
 * Data comes from wp_localize_script (rpress_checkout_ux) and the
 * rpress_checkout_ux_state AJAX endpoint (includes/features/checkout-ux).
 */
jQuery(function ($) {
	'use strict';

	var cfg = window.rpress_checkout_ux || {};
	var i18n = cfg.i18n || {};
	var state = cfg.state || null;
	var isCheckout = '1' === String(cfg.is_checkout);
	var $wrap = $('#rpress_checkout_wrap');
	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ---------------------------------------------------------------- */
	/* Helpers                                                            */
	/* ---------------------------------------------------------------- */
	function readCookie(name) {
		var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function formatMoney(amount) {
		var c = cfg.currency || {};
		var decimals = parseInt(c.decimals, 10);
		if (isNaN(decimals) || decimals < 0) {
			decimals = 2;
		}
		var parts = Math.abs(Number(amount) || 0).toFixed(decimals).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousands || '');
		var num = parts.length > 1 ? parts[0] + (c.decimal || '.') + parts[1] : parts[0];
		return String(c.template || '%s').replace('%s', num);
	}

	function sprintf(tmpl, args) {
		var i = 0;
		return String(tmpl || '').replace(/%(\d+)\$s|%s|%d/g, function (m, pos) {
			var v = pos ? args[parseInt(pos, 10) - 1] : args[i++];
			return v === undefined ? '' : String(v);
		});
	}

	// One polite live region for announcements (switch result, blocked submit).
	var $live = $('<div class="rpress-cux-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>').appendTo(document.body);
	function announce(text) {
		$live.text('');
		window.setTimeout(function () { $live.text(text || ''); }, 60);
	}

	var ICON_INFO = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>';

	/* ---------------------------------------------------------------- */
	/* 1. Minimum-order hint                                              */
	/* ---------------------------------------------------------------- */
	var HINT_ID = 'rpress-cux-min-hint';

	function minState() {
		var m = state && state.minimum;
		var below = !!(m && m.enabled && m.below && state.quantity > 0);
		return { below: below, m: m || {} };
	}

	// Build or update a hint element in place. Only touches text that changed
	// so repeated renders do not churn the DOM.
	function upsertHint($existing, cls, id, m) {
		var $hint = $existing;
		if (!$hint.length) {
			$hint = $('<div class="rpress-cux-min-hint"><span class="rpress-cux-min-icon"></span><span class="rpress-cux-min-text"><strong class="rpress-cux-min-msg"></strong> <span class="rpress-cux-min-detail"></span></span></div>');
			$hint.addClass(cls).find('.rpress-cux-min-icon').html(ICON_INFO);
			if (id) {
				$hint.attr('id', id);
			}
		}
		var $msg = $hint.find('.rpress-cux-min-msg');
		var $detail = $hint.find('.rpress-cux-min-detail');
		if ($msg.text() !== (m.message || '')) {
			$msg.text(m.message || '');
		}
		if ($detail.text() !== (m.detail || '')) {
			$detail.text(m.detail || '');
		}
		return $hint;
	}

	function addToken(el, attr, token) {
		var list = (el.getAttribute(attr) || '').split(/\s+/).filter(Boolean);
		if (list.indexOf(token) === -1) {
			list.push(token);
			el.setAttribute(attr, list.join(' '));
		}
	}
	function removeToken(el, attr, token) {
		var list = (el.getAttribute(attr) || '').split(/\s+/).filter(function (t) { return t && t !== token; });
		if (list.length) {
			el.setAttribute(attr, list.join(' '));
		} else {
			el.removeAttribute(attr);
		}
	}

	// rp-checkout-ui.js skips its "Place order · ₹X" label sync while the
	// button is disabled, so refresh it the same way once we re-enable it.
	function syncPlaceOrderLabel($btn) {
		if (!$wrap.hasClass('rpress-checkout-v2')) {
			return;
		}
		var ui = window.rpress_checkout_ui || {};
		var total = $.trim($wrap.find('.rpress_cart_total .rpress_cart_amount').first().text());
		$btn.val((ui.place_order || 'Place order') + (total ? ' · ' + total : ''));
	}

	function setButtonBlocked($btn, blocked, describedBy) {
		$btn.each(function () {
			var $b = $(this);
			if (blocked) {
				if (!$b.prop('disabled')) {
					$b.prop('disabled', true).attr('data-cux-disabled', '1');
				}
				$b.attr('aria-disabled', 'true').addClass('rpress-cux-blocked');
				addToken(this, 'aria-describedby', describedBy);
			} else if ($b.attr('data-cux-disabled')) {
				// Only undo what we did; rp-ajax disables it while submitting.
				$b.prop('disabled', false).removeAttr('data-cux-disabled aria-disabled').removeClass('rpress-cux-blocked');
				removeToken(this, 'aria-describedby', describedBy);
				if ('rpress-purchase-button' === this.id) {
					syncPlaceOrderLabel($b);
				}
			} else {
				$b.removeClass('rpress-cux-blocked');
				removeToken(this, 'aria-describedby', describedBy);
			}
		});
	}

	function renderCheckoutMinimum(s) {
		if (!isCheckout || !$wrap.length) {
			return;
		}
		var $submit = $('#rpress_purchase_submit');
		var $form = $('#' + HINT_ID);
		var $sbar = $wrap.find('.rpress-checkout-sticky-bar');
		var $sbarHint = $sbar.find('.rpress-cux-min-hint');

		if (s.below) {
			if ($submit.length) {
				var $hint = upsertHint($form, 'is-checkout', HINT_ID, s.m);
				if (!$hint.parent().length || $hint.next()[0] !== $submit[0]) {
					$submit.before($hint);
				}
			}
			if ($sbar.length) {
				var $sh = upsertHint($sbarHint, 'is-sbar', HINT_ID + '-sbar', s.m);
				if (!$sh.parent().length) {
					$sbar.prepend($sh);
				}
			}
		} else {
			$form.remove();
			$sbarHint.remove();
		}
		$wrap.toggleClass('rpress-cux-below-min', s.below);
		setButtonBlocked($('#rpress-purchase-button'), s.below, HINT_ID);
		setButtonBlocked($wrap.find('.rpress-sbar-button'), s.below, HINT_ID + '-sbar');
	}

	function renderCartMinimum(s) {
		// Sidebar cart: hint above the totals/checkout summary (that area is
		// re-rendered by core, so the hint sits beside it, not inside).
		$('.rpress-sidebar-cart-wrap').each(function () {
			var $area = $(this).find('.rpress-cart-summary-area').first();
			var $hint = $(this).find('.rpress-cux-min-hint.is-cart');
			if (s.below && $area.length) {
				$hint = upsertHint($hint, 'is-cart', '', s.m);
				if (!$hint.parent().length) {
					$area.before($hint);
				}
			} else {
				$hint.remove();
			}
		});
		// Mobile cart bar: a compact hint. With the 3.5 mobile ordering layer
		// (.rp-mo-cartbar) it sits on the bar itself as a strip with a
		// progress line (--rp-mo-progress); otherwise under the total.
		$('.rpress-mobile-cart-icons').each(function () {
			var $bar = $(this);
			var $host = $bar.hasClass('rp-mo-cartbar') ? $bar : $bar.find('.rp-cart-left-wrap').first();
			var $hint = $bar.find('.rpress-cux-min-hint.is-bar');
			if (s.below && $host.length) {
				$hint = upsertHint($hint, 'is-bar', '', s.m);
				if (!$hint.parent().is($host)) {
					$host.append($hint);
				}
				var pct = s.m.minimum > 0 ? Math.max(0, Math.min(100, (s.m.subtotal / s.m.minimum) * 100)) : 0;
				$hint[0].style.setProperty('--rp-mo-progress', pct.toFixed(1) + '%');
			} else {
				$hint.remove();
			}
		});
	}

	function renderMinimum() {
		var s = minState();
		renderCheckoutMinimum(s);
		renderCartMinimum(s);
	}

	/* ---------------------------------------------------------------- */
	/* State refresh                                                      */
	/* ---------------------------------------------------------------- */
	var refreshTimer = null;
	var refreshXhr = null;

	function setState(next, opts) {
		if (!next || typeof next !== 'object') {
			return;
		}
		var prev = state;
		state = next;
		renderMinimum();
		if (opts && opts.switched) {
			showSwitchNote(prev, next);
		}
	}

	function refresh() {
		if (!cfg.ajaxurl || !cfg.nonce) {
			return;
		}
		window.clearTimeout(refreshTimer);
		refreshTimer = window.setTimeout(function () {
			if (refreshXhr && refreshXhr.abort) {
				refreshXhr.abort();
			}
			refreshXhr = $.post(cfg.ajaxurl, {
				action: 'rpress_checkout_ux_state',
				security: cfg.nonce,
				service: readCookie('service_type')
			}).done(function (res) {
				if (res && res.success && res.data) {
					setState(res.data);
				} else {
					clearState();
				}
			}).fail(function (xhr, status) {
				// An expired nonce (e.g. a cached page) fails quietly: no hint.
				if ('abort' !== status) {
					clearState();
				}
			});
		}, 200);
	}

	// Drop the hint rather than show a stale or unknown cart.
	function clearState() {
		state = null;
		renderMinimum();
	}

	$(document.body).on(
		'rpress_items_updated rpress_added_to_cart rpress_cart_item_removed rpress_quantity_updated ' +
		'rpress_discount_applied rpress_discount_removed rpress_checked_slots',
		refresh
	);

	// Catch cart/fee/service changes made by flows that fire no custom event
	// (checkout summary stepper, tips, delivery fee checks, clear cart).
	$(document).ajaxComplete(function (event, xhr, settings) {
		var data = (settings && typeof settings.data === 'string') ? settings.data : '';
		if (!data || /rpress_checkout_ux_state|rpress_checkout_service_fees|rpress_load_gateway|rpress_process_checkout/.test(data)) {
			return;
		}
		if (/cart|quantity|discount|service|delivery_fee|tip|reorder|fee/i.test(data)) {
			refresh();
		}
	});

	// The checkout submit area is re-rendered on gateway change, and rp-ajax
	// re-enables Place order after a failed attempt: re-apply the block.
	$(document.body).on('rpress_gateway_loaded rpress_checkout_error', function () {
		window.setTimeout(renderMinimum, 0);
	});

	// rp-checkout-ui builds the sticky bar when the viewport crosses 782px.
	var resizeTimer = null;
	$(window).on('resize', function () {
		window.clearTimeout(resizeTimer);
		resizeTimer = window.setTimeout(renderMinimum, 150);
	});

	/* ---------------------------------------------------------------- */
	/* 2. Delivery/pickup switch: keep fields, show the total change      */
	/* ---------------------------------------------------------------- */
	var FIELD_SCOPE = '#rpress_checkout_order_details, #rpress_checkout_user_info';
	var fieldCache = {};

	function cacheable(el) {
		if (!el || !el.name || el.disabled) {
			return false;
		}
		if ($(el).closest('.rp-checkout-service-option').length) {
			return false; // Date/time controls are managed by core.
		}
		if ('TEXTAREA' === el.tagName) {
			return true;
		}
		if ('INPUT' !== el.tagName) {
			return false;
		}
		var type = (el.getAttribute('type') || 'text').toLowerCase();
		return ['text', 'email', 'tel', 'number', 'search', 'url'].indexOf(type) !== -1;
	}

	function snapshotFields() {
		$(FIELD_SCOPE).find('input, textarea').each(function () {
			if (cacheable(this)) {
				fieldCache[this.name] = this.value;
			}
		});
	}

	// The cache holds exactly what was in each field before the re-render, so
	// it wins over the server's prefill (e.g. a logged-in customer's saved
	// address would otherwise replace the address they just typed).
	function restoreFields() {
		$(FIELD_SCOPE).find('input, textarea').each(function () {
			if (cacheable(this) && Object.prototype.hasOwnProperty.call(fieldCache, this.name) && this.value !== fieldCache[this.name]) {
				this.value = fieldCache[this.name];
			}
		});
	}

	if (isCheckout) {
		$(document).on('input change', '#rpress_checkout_order_details :input, #rpress_checkout_user_info :input', function () {
			if (cacheable(this)) {
				fieldCache[this.name] = this.value;
			}
		});
		// Capture phase: snapshot before core's click handler starts the
		// re-render, so autofilled values (no input event) are kept too.
		document.addEventListener('click', function (e) {
			var t = e.target;
			if (t && t.closest && t.closest('#rpressdeliveryTab .nav-link, #rpressdeliveryTab .single-service-selected')) {
				snapshotFields();
			}
		}, true);
	}

	function showSwitchNote(prev, next) {
		var $details = $('#rpress_checkout_order_details');
		$details.find('.rpress-cux-switch-note').remove();
		if (!next) {
			return;
		}
		var label = next.service_label || next.service || '';
		var text;
		var diff = prev ? Math.round(((next.total || 0) - (prev.total || 0)) * 100) / 100 : 0;
		if (!prev || prev.service === next.service || 0 === diff) {
			text = sprintf(i18n.switch_same || '%1$s selected. Your total is still %2$s.', [label, next.total_formatted || formatMoney(next.total)]);
		} else if (diff > 0) {
			text = sprintf(i18n.switch_more || '%1$s selected. Your total is now %2$s (%3$s more).', [label, next.total_formatted || formatMoney(next.total), formatMoney(diff)]);
		} else {
			text = sprintf(i18n.switch_less || '%1$s selected. Your total is now %2$s (%3$s less).', [label, next.total_formatted || formatMoney(next.total), formatMoney(-diff)]);
		}
		var $note = $('<p class="rpress-cux-switch-note"></p>').text(text);
		$note.addClass(diff > 0 ? 'is-more' : (diff < 0 ? 'is-less' : 'is-same'));
		var $anchor = $details.children('.rp-checkout-service-option').first();
		if ($anchor.length) {
			$anchor.after($note);
		} else {
			$details.prepend($note);
		}
		announce(text);
	}

	// Fired by rp-frontend.js after the order-details card is re-rendered.
	$(document.body).on('rp-checkout-update-service-option', function (e, payload) {
		restoreFields();
		var next = payload && payload.data && payload.data.checkout_ux;
		if (next) {
			setState(next, { switched: true });
		} else {
			refresh();
		}
		// Re-validate fields that were flagged before the re-render.
		$(FIELD_SCOPE).find('[aria-invalid="true"]').each(function () {
			if (!validateField(this, false)) {
				clearError(this);
			}
		});
	});

	/* ---------------------------------------------------------------- */
	/* 3. Inline validation                                               */
	/* ---------------------------------------------------------------- */
	var addressFields = cfg.address_fields || {};
	var EMAIL_RE = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;

	function fieldName(el) {
		return String(el.name || '').toLowerCase();
	}

	function isRequired(el) {
		return !!(el.required || $(el).hasClass('required') || Object.prototype.hasOwnProperty.call(addressFields, fieldName(el)));
	}

	function isCandidate(el) {
		if (!el || el.disabled || !el.name) {
			return false;
		}
		var type = (el.getAttribute('type') || '').toLowerCase();
		if (['hidden', 'submit', 'button', 'radio', 'checkbox', 'image', 'reset'].indexOf(type) !== -1) {
			return false;
		}
		if (!$(el).is(':visible')) {
			return false;
		}
		var name = fieldName(el);
		return isRequired(el) || 'rpress_email' === name || 'rpress_phone' === name;
	}

	function emptyMessage(el) {
		var name = fieldName(el);
		if ('rpress_email' === name) { return i18n.err_email; }
		if ('rpress_phone' === name) { return i18n.err_phone; }
		if ('rpress_first' === name) { return i18n.err_first; }
		if ('rpress_last' === name) { return i18n.err_last; }
		if (addressFields[name]) { return addressFields[name]; }
		return i18n.err_required || 'This field is required.';
	}

	// Returns the error message for a field, or '' when it is valid.
	function validateField(el) {
		if (!isCandidate(el)) {
			return '';
		}
		var val = $.trim(el.value || '');
		var name = fieldName(el);
		if ('' === val) {
			return isRequired(el) ? emptyMessage(el) : '';
		}
		if ('rpress_email' === name || 'email' === (el.getAttribute('type') || '').toLowerCase()) {
			if (!EMAIL_RE.test(val)) {
				return i18n.err_email_format || 'Please enter a valid email address';
			}
		}
		if ('rpress_phone' === name) {
			// Mirrors rpress_validate_phone_field(): 8 to 15 characters, nothing more,
			// so the browser never rejects a number the server would accept.
			if (val.length < 8 || val.length > 15) {
				return i18n.err_phone_format || 'Please enter a valid phone number';
			}
		}
		return '';
	}

	function errorId(el) {
		return String(el.id || el.name || 'field').replace(/[^A-Za-z0-9_-]/g, '_') + '-cux-error';
	}

	function showError(el, msg) {
		var $el = $(el);
		var id = errorId(el);
		var $host = $el.closest('p, .rpress-checkout-fields, label');
		if (!$host.length) {
			$host = $el.parent();
		}
		// Drop a core-rendered message for the same field so it never doubles.
		$host.find('.rpress-field-error').not('#' + id).remove();
		var $msg = $('#' + id);
		if (!$msg.length) {
			$msg = $('<span class="rpress-field-error rpress-cux-field-error"></span>').attr('id', id);
			$host.append($msg);
		}
		$msg.text(msg);
		$el.addClass('error').attr('aria-invalid', 'true');
		addToken(el, 'aria-describedby', id);
	}

	function clearError(el) {
		var id = errorId(el);
		$('#' + id).remove();
		$(el).removeClass('error').removeAttr('aria-invalid');
		removeToken(el, 'aria-describedby', id);
	}

	function syncBanner(count) {
		var form = document.getElementById('rpress_purchase_form');
		if (!form) {
			return;
		}
		var $form = $(form);
		var $banner = $form.find('.rpress-checkout-error-banner');
		if (!count) {
			$banner.remove();
			return;
		}
		var tmpl = i18n.err_banner || 'Please fix the %d highlighted field(s) below to place your order.';
		if (!$banner.length) {
			$banner = $('<div class="rpress-checkout-error-banner" role="alert"><span class="rpress-cux-banner-icon" aria-hidden="true"></span><span class="rpress-cux-banner-text"></span></div>');
			$banner.find('.rpress-cux-banner-icon').html(ICON_INFO);
			var $card = $form.find('#rpress_checkout_user_info');
			var $legend = $card.children('legend').first();
			if ($legend.length) {
				$legend.after($banner);
			} else if ($card.length) {
				$card.prepend($banner);
			} else {
				$form.prepend($banner);
			}
		}
		var $text = $banner.find('.rpress-cux-banner-text');
		if (!$text.length) {
			$text = $banner.find('span').last();
		}
		$text.text(tmpl.replace('%d', count));
	}

	function focusField(el) {
		try {
			el.focus({ preventScroll: true });
		} catch (err) {
			el.focus();
		}
		if (el.scrollIntoView) {
			el.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
		}
	}

	function validateAll() {
		var form = document.getElementById('rpress_purchase_form');
		var bad = [];
		if (!form) {
			return bad;
		}
		$(form).find('input, select, textarea').each(function () {
			var msg = validateField(this);
			if (msg) {
				showError(this, msg);
				bad.push(this);
			} else if ('true' === this.getAttribute('aria-invalid')) {
				clearError(this);
			}
		});
		return bad;
	}

	function stopEvent(e) {
		e.preventDefault();
		e.stopPropagation();
		if (e.stopImmediatePropagation) {
			e.stopImmediatePropagation();
		}
	}

	// Runs in the capture phase on document, before rp-ajax.js (delegated
	// click) and rp-checkout-ui.js (form capture submit), so an invalid form
	// or a below-minimum cart never starts the checkout request.
	function guard(e) {
		var t = e.target;
		if ('click' === e.type) {
			if (!t || !t.closest || !t.closest('#rpress-purchase-button, .rpress-sbar-button')) {
				return;
			}
		} else {
			if (!t || 'rpress_purchase_form' !== t.id) {
				return;
			}
			// A login submit inside the form is not a Place order attempt.
			if (e.submitter && e.submitter.id && 'rpress-purchase-button' !== e.submitter.id) {
				return;
			}
		}
		var s = minState();
		if (s.below) {
			stopEvent(e);
			renderMinimum();
			announce(s.m.message || '');
			var hint = document.getElementById(HINT_ID);
			if (hint && $(hint).is(':visible') && hint.scrollIntoView) {
				hint.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
			}
			return;
		}
		var bad = validateAll();
		if (bad.length) {
			stopEvent(e);
			syncBanner(bad.length);
			focusField(bad[0]);
		}
	}

	if (isCheckout && document.getElementById('rpress_purchase_form')) {
		document.addEventListener('click', guard, true);
		document.addEventListener('submit', guard, true);

		// Blur: validate once the customer has typed in the field, or when it
		// is already flagged, so tabbing through an empty form stays quiet.
		$(document).on('input', '#rpress_purchase_form :input', function () {
			this.setAttribute('data-cux-touched', '1');
			if ('true' === this.getAttribute('aria-invalid') && !validateField(this)) {
				clearError(this);
				if (!$('#rpress_purchase_form [aria-invalid="true"]').length) {
					syncBanner(0);
				}
			}
		});
		$(document).on('focusout', '#rpress_purchase_form :input', function () {
			var el = this;
			if (!isCandidate(el)) {
				return;
			}
			if (!el.getAttribute('data-cux-touched') && 'true' !== el.getAttribute('aria-invalid') && '' === $.trim(el.value || '')) {
				return;
			}
			var msg = validateField(el);
			if (msg) {
				showError(el, msg);
			} else {
				clearError(el);
				if (!$('#rpress_purchase_form [aria-invalid="true"]').length) {
					syncBanner(0);
				}
			}
		});
	}

	/* ---------------------------------------------------------------- */
	/* Boot                                                               */
	/* ---------------------------------------------------------------- */
	renderMinimum();
	if (isCheckout) {
		snapshotFields();
	} else {
		// Off checkout the page may be cached, so the cart state is never
		// printed into it: fetch this visitor's state once on load.
		refresh();
	}
});
