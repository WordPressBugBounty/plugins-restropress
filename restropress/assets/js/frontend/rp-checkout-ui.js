/**
 * RestroPress checkout redesign behaviors (3.4).
 *
 * Pure enhancement layer over the existing checkout JS: nothing here posts
 * the order or applies discounts itself; it drives the new UI (live CTA
 * total, coupon collapse, create-account toggle, gateway radio-cards,
 * quantity stepper, tips-on-pickup hiding, mobile summary + sticky bar)
 * around the established AJAX flows.
 */
jQuery(function ($) {
	'use strict';

	var $wrap = $('#rpress_checkout_wrap.rpress-checkout-v2');
	if (!$wrap.length) {
		return;
	}
	var i18n = window.rpress_checkout_ui || {};

	/* ---------------------------------------------------------------- */
	/* Live CTA label: "Place order · ₹X" synced to the summary total     */
	/* ---------------------------------------------------------------- */
	function currentTotalText() {
		var $amount = $wrap.find('.rpress_cart_total .rpress_cart_amount').first();
		return $.trim($amount.text());
	}
	function syncCtaLabel() {
		var total = currentTotalText();
		var label = (i18n.place_order || 'Place order') + (total ? ' · ' + total : '');
		var $btn = $('#rpress-purchase-button');
		if ($btn.length && !$btn.prop('disabled')) {
			$btn.val(label);
		}
		$wrap.find('.rpress-sbar-total').text(total);
		$wrap.find('.rpress-msum-total').text(total);
	}
	// The summary re-renders through several AJAX flows; observe it.
	var summaryNode = document.getElementById('rpress_checkout_cart_wrap');
	if (summaryNode && window.MutationObserver) {
		new MutationObserver(function () {
			syncCtaLabel();
		}).observe(summaryNode, { childList: true, subtree: true, characterData: true });
	}
	// Gateway reloads recreate the button; watch the form wrap too.
	var formWrapNode = document.getElementById('rpress_purchase_form_wrap');
	if (formWrapNode && window.MutationObserver) {
		new MutationObserver(function () {
			syncCtaLabel();
		}).observe(formWrapNode, { childList: true, subtree: true });
	}
	syncCtaLabel();

	/* ---------------------------------------------------------------- */
	/* Coupon collapse                                                    */
	/* ---------------------------------------------------------------- */
	$wrap.on('click', '.rpress-coupon-toggle', function (e) {
		e.preventDefault();
		var $toggle = $(this);
		var $body = $toggle.siblings('.rpress-coupon-body');
		$toggle.hide();
		$body.slideDown(150, function () {
			$body.find('#rpress-discount').trigger('focus');
		});
	});
	$(document.body).on('rpress_discount_applied', function () {
		$wrap.find('.rpress-checkout-coupon').slideUp(150);
	});
	$(document.body).on('rpress_discount_removed', function () {
		$wrap.find('.rpress-checkout-coupon').slideDown(150);
		$wrap.find('.rpress-coupon-toggle').show();
		$wrap.find('.rpress-coupon-body').hide();
	});

	/* ---------------------------------------------------------------- */
	/* Create-account toggle: enable/disable the register fields          */
	/* ---------------------------------------------------------------- */
	$wrap.on('change', '#rpress-create-account', function () {
		var on = this.checked;
		var $fields = $('#rpress-create-account-fields');
		$fields.toggleClass('rpress-hidden', !on);
		$fields.find('input').prop('disabled', !on);
	});

	/* ---------------------------------------------------------------- */
	/* Inline login panel                                                 */
	/* ---------------------------------------------------------------- */
	// The link reuses the stock rpress_checkout_login AJAX (rp-ajax.js binds
	// .rpress_checkout_register_login on #rpress_checkout_form_wrap and
	// returns false, so bind directly on the link — target handlers run
	// before that delegated one stops propagation).
	// The login form is pre-rendered (hidden); just reveal it and hide the
	// guest fields (either/or, per the mockup).
	$wrap.on('click', '.rpress-checkout-login-link', function (e) {
		e.preventDefault();
		$('#rpress_checkout_login_register').show();
		$(this).hide();
		$(this).closest('.rpress-checkout-account-inline').addClass('panel-open');
		$wrap.find('.rpress-contact-editable').addClass('rpress-hidden');
	});
	// "Continue as guest" closes the login panel and restores the guest fields.
	$wrap.on('click', '.rpress-continue-guest', function () {
		$('#rpress_checkout_login_register').hide();
		$wrap.find('.rpress-checkout-account-inline').removeClass('panel-open');
		$wrap.find('.rpress-contact-editable').removeClass('rpress-hidden');
		$wrap.find('.rpress-checkout-login-link').show();
	});


	/* ---------------------------------------------------------------- */
	/* Service tabs: sub-label (fee / free) + radio dot                   */
	/* ---------------------------------------------------------------- */
	function enhanceServiceTabs() {
		$wrap.find('#rpressdeliveryTab .nav-link').each(function () {
			var $link = $(this);
			var $inner = $link.find('.rpress-service-tab-inner');
			if (!$inner.length || $inner.attr('data-rp-enhanced')) {
				return;
			}
			$inner.attr('data-rp-enhanced', '1');
			var type = ($link.attr('data-service-type') || '').toLowerCase();
			var $label = $inner.find('.rpress-service-tab-label');
			var sub = /pickup/.test(type) ? (i18n.pickup_sub || '') : (i18n.delivery_sub || '');
			var $tw = $('<span class="rpress-service-tab-textwrap"></span>');
			$label.before($tw);
			$tw.append($label);
			if (sub) {
				$tw.append($('<span class="rpress-service-tab-sub"></span>').text(sub));
			}
			$inner.append('<span class="rpress-service-tab-dot" aria-hidden="true"></span>');
		});
	}
	enhanceServiceTabs();

	// Switching delivery/pickup replaces #rpress_checkout_order_details via a
	// fetch() (not jQuery ajax), so ajaxComplete never fires. Watch the form
	// column and re-decorate the freshly-rendered tabs. enhanceServiceTabs is
	// idempotent (guards on data-rp-enhanced), so repeat calls are cheap.
	var formColNode = document.getElementById('rpress_checkout_form_wrap');
	if (formColNode && window.MutationObserver) {
		var feeRefreshTimer;
		new MutationObserver(function () {
			enhanceServiceTabs();
			syncServiceClass();
			// The service switch uses fetch() (no ajaxComplete), so refresh the
			// chip fees here. Debounced to collapse the mutation burst; the
			// text-change guard in refreshServiceFees stops it looping.
			clearTimeout(feeRefreshTimer);
			feeRefreshTimer = setTimeout(refreshServiceFees, 250);
		}).observe(formColNode, { childList: true, subtree: true });
	}

	/* ---------------------------------------------------------------- */
	/* Payment gateways: radio + icon + two-line label + badges           */
	/* ---------------------------------------------------------------- */
	var GW_ICONS = {
		cash: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2.5"></circle><path d="M6 12h.01M18 12h.01"></path></svg>',
		card: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20"></path></svg>'
	};
	function enhanceGateways() {
		var meta = i18n.gateways || {};
		$wrap.find('#rpress-payment-mode-wrap label.rpress-gateway-option').each(function () {
			var $label = $(this);
			if ($label.attr('data-rp-enhanced')) {
				return;
			}
			var $input = $label.find('input.rpress-gateway');
			if (!$input.length) {
				return;
			}
			$label.attr('data-rp-enhanced', '1');
			var val = $input.val();
			var m = meta[val] || {};
			var title = $.trim($label.text());
			var iconKey = m.icon || 'card';
			var badges = m.badges || [];
			$label.empty().append($input);
			$label.append('<span class="rpress-gw-radio" aria-hidden="true"></span>');
			$label.append('<span class="rpress-gw-icon">' + (GW_ICONS[iconKey] || GW_ICONS.card) + '</span>');
			var $body = $('<span class="rpress-gw-body"></span>');
			$body.append($('<span class="rpress-gw-title"></span>').text(title));
			if (m.sub) {
				$body.append($('<span class="rpress-gw-sub"></span>').text(m.sub));
			}
			$label.append($body);
			var icons = m.icons || [];
			if (icons.length) {
				// Real card icons from the Accepted Cards setting.
				var $cards = $('<span class="rpress-gw-badges"></span>');
				icons.forEach(function (url) {
					$cards.append($('<img class="rpress-gw-card" alt="" loading="lazy" />').attr('src', url));
				});
				$label.append($cards);
			} else if (badges.length) {
				// Fallback: text badges (VISA/UPI style).
				var $badges = $('<span class="rpress-gw-badges"></span>');
				badges.forEach(function (bd) {
					var low = String(bd).toLowerCase();
					var cls = 'visa' === low ? 'is-visa' : ('upi' === low ? 'is-upi' : '');
					$badges.append($('<span class="rpress-gw-badge ' + cls + '"></span>').text(bd));
				});
				$label.append($badges);
			}
		});
	}
	enhanceGateways();

	/* ---------------------------------------------------------------- */
	/* Tips hidden on pickup (per design: tip your driver = delivery)     */
	/* ---------------------------------------------------------------- */
	function readServiceType() {
		var match = document.cookie.match(/(?:^|;\s*)service_type=([^;]*)/);
		return match ? decodeURIComponent(match[1]) : '';
	}
	function syncServiceClass() {
		var type = readServiceType();
		var isPickup = 'pickup' === type;
		var hasTips = $wrap.find('.rpress-tips').length > 0;
		$wrap.toggleClass('rpress-service-pickup', isPickup);
		// Join the tips block onto the fulfillment card only while it shows.
		$wrap.toggleClass('rpress-has-tips', hasTips && !isPickup);
		// The tips UI is delivery-only; drop an applied tip when switching
		// to pickup so the hidden fee cannot ride along on the order.
		if (isPickup) {
			$wrap.find('.rpress-remove-tip.enable').trigger('click');
		}
	}
	$(document).on('click', '#rpressdeliveryTab .nav-link, .single-service-selected', function () {
		window.setTimeout(syncServiceClass, 300);
	});
	// Pull the current per-service fees from the server (they depend on backend
	// criteria — delivery zone/zip, extra-fee rules) and refresh the chip
	// sublabels, so e.g. changing the delivery zip updates the amount.
	function refreshServiceFees() {
		if (!window.rpress_scripts || !rpress_scripts.ajaxurl) {
			return;
		}
		$.post(rpress_scripts.ajaxurl, { action: 'rpress_checkout_service_fees' }).done(function (res) {
			if (!res || !res.success || !res.data) {
				return;
			}
			$wrap.find('#rpressdeliveryTab .nav-link').each(function () {
				var type = ($(this).attr('data-service-type') || '').toLowerCase();
				var val = /pickup/.test(type) ? res.data.pickup : res.data.delivery;
				if (!val) {
					return;
				}
				var $sub = $(this).find('.rpress-service-tab-sub');
				if (!$sub.length) {
					var $tw = $(this).find('.rpress-service-tab-textwrap');
					if ($tw.length) {
						$sub = $('<span class="rpress-service-tab-sub"></span>').appendTo($tw);
					}
				}
				// Only write when it actually changed — updating the text mutates
				// the tab (which the form-column observer watches), so an
				// unconditional set would loop.
				if ($sub.length && $sub.text() !== val) {
					$sub.text(val);
				}
			});
		});
	}
	$(document).ajaxComplete(function (event, xhr, settings) {
		var data = (settings && settings.data) ? String(settings.data) : '';
		if (data.indexOf('rpress_checkout_update_service_option') !== -1) {
			// The order-details card is replaced by this AJAX; re-decorate it.
			enhanceServiceTabs();
			syncServiceClass();
			syncCtaLabel();
		}
		// After any fee-affecting request (but not our own fee lookup), re-pull
		// the chip fees so a changed zip/zone/cart is reflected.
		if (data.indexOf('rpress_checkout_service_fees') === -1 &&
			/check_delivery_fee|rpress_checkout_update_service_option|add_to_cart|remove_from_cart|update_cart|reorder|check_service_slot/.test(data)) {
			refreshServiceFees();
		}
	});
	syncServiceClass();


	/* ---------------------------------------------------------------- */
	/* Encrypted note under the CTA                                       */
	/* ---------------------------------------------------------------- */
	function injectEncryptedNote() {
		var $submit = $('#rpress_purchase_submit');
		if ($submit.length && !$submit.next('.rpress-checkout-encrypted-note').length) {
			var $note = $('<div class="rpress-checkout-encrypted-note"></div>');
			$note.append('<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>');
			$note.append(document.createTextNode(' ' + (i18n.encrypted_note || 'Payments are encrypted') + ' · ' + (i18n.need_help || 'Need help?') + ' '));
			if (i18n.support_url) {
				$note.append($('<a></a>').attr('href', i18n.support_url).text(i18n.contact_support || 'Contact support'));
			}
			$submit.after($note);
		}
	}
	if (formWrapNode && window.MutationObserver) {
		new MutationObserver(injectEncryptedNote).observe(formWrapNode, { childList: true });
	}
	injectEncryptedNote();

	/* ---------------------------------------------------------------- */
	/* Mobile: collapsible summary + sticky bottom bar                    */
	/* ---------------------------------------------------------------- */
	function buildMobileChrome() {
		if ($wrap.find('.rpress-mobile-summary-toggle').length) {
			return;
		}
		var itemCount = $wrap.find('.rpress_cart_item').length;
		var itemsLabel = (1 === itemCount ? (i18n.item_singular || '1 item') : itemCount + ' ' + (i18n.items_plural || 'items'));
		$('#rpress_checkout_cart_form').prepend(
			'<button type="button" class="rpress-mobile-summary-toggle">' +
			'<span>' + itemsLabel + ' · ' + (i18n.view_order || 'view order details') + '</span>' +
			'<span class="rpress-msum-total"></span>' +
			'<svg class="rpress-msum-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>' +
			'</button>'
		);
		$wrap.append(
			'<div class="rpress-checkout-sticky-bar">' +
			'<div class="rpress-sbar-row">' +
			'<div><div class="rpress-sbar-total-label">' + (i18n.total_label || 'Total') + '</div><div class="rpress-sbar-total"></div></div>' +
			'<button type="button" class="rpress-sbar-button">' + (i18n.place_order || 'Place order') + '</button>' +
			'</div>' +
			'<div class="rpress-sbar-note">' +
			'<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> ' +
			(i18n.encrypted_note || 'Payments are encrypted') +
			'</div>' +
			'</div>'
		);
		syncCtaLabel();
	}
	function syncMobileChrome() {
		if (window.matchMedia('(max-width: 782px)').matches) {
			buildMobileChrome();
			$wrap.addClass('rpress-has-sticky-bar');
		} else {
			$wrap.removeClass('rpress-has-sticky-bar rpress-msum-open');
		}
	}
	$wrap.on('click', '.rpress-mobile-summary-toggle', function () {
		$wrap.toggleClass('rpress-msum-open');
	});
	$wrap.on('click', '.rpress-sbar-button', function () {
		var real = document.getElementById('rpress-purchase-button');
		if (real) {
			$(real).trigger('click');
		}
	});
	$(window).on('resize', syncMobileChrome);
	syncMobileChrome();

	/* ---------------------------------------------------------------- */
	/* Error banner: scroll it into view when the ajax checkout errors    */
	/* ---------------------------------------------------------------- */
	$(document).ajaxComplete(function () {
		var $errors = $wrap.find('.rpress_errors').first();
		if ($errors.length && $errors.is(':visible') && !$errors.data('rpress-scrolled')) {
			$errors.data('rpress-scrolled', true);
			window.setTimeout(function () {
				$errors[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
				window.setTimeout(function () { $errors.removeData('rpress-scrolled'); }, 2000);
			}, 100);
		}
	});

	/* ---------------------------------------------------------------- */
	/* Coupon: swap the input for a green "applied" pill on apply/remove  */
	/* (core apply/remove AJAX is untouched; we react to its events).     */
	/* ---------------------------------------------------------------- */
	function esc(t) { return $('<i>').text(t == null ? '' : t).html(); }
	function renderCouponApplied() {
		var $coupon = $wrap.find('.rpress-checkout-coupon');
		if (!$coupon.length) { return; }
		var $disc = $wrap.find('.rpress_cart_discount').first();
		var code = $disc.find('.rpress_discount_remove').data('code') || '';
		var amount = $.trim($disc.find('.rpress_discount_rate').text());
		if (!code) { return; }
		var text = (i18n.coupon_applied || 'applied · %s off').replace('%s', amount);
		var check = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>';
		var html = '<span class="rpress-coupon-applied-icon" aria-hidden="true">' + check + '</span>'
			+ '<span class="rpress-coupon-applied-text"><strong>' + esc(code) + '</strong> ' + esc(text) + '</span>'
			+ '<a href="#" data-code="' + esc(code) + '" class="rpress-coupon-remove rpress_discount_remove">' + esc(i18n.coupon_remove || 'Remove') + '</a>';
		$coupon.find('.rpress-coupon-applied').html(html);
		$coupon.addClass('is-applied');
	}
	$(document.body).on('rpress_discount_applied', renderCouponApplied);
	$(document.body).on('rpress_discount_removed', function () {
		var $coupon = $wrap.find('.rpress-checkout-coupon');
		$coupon.removeClass('is-applied').find('.rpress-coupon-applied').empty();
		$coupon.find('.rpress-coupon-body').hide();
	});

	/* ---------------------------------------------------------------- */
	/* Client-side validation: mockup error UI (banner + red inputs +     */
	/* inline messages) in place of the browser's native tooltips.        */
	/* ---------------------------------------------------------------- */
	(function () {
		var form = document.getElementById('rpress_purchase_form');
		if (!form) {
			return;
		}
		var $form = $(form);
		// Drive our own error UI, not the browser's native bubbles.
		form.setAttribute('novalidate', 'novalidate');

		function messageFor($f, empty) {
			var name = ($f.attr('name') || '').toLowerCase();
			if (name === 'rpress_email') {
				return empty ? (i18n.err_email || 'Please enter a valid email address')
				             : (i18n.err_email_format || 'That email doesn\u2019t look right, check for typos');
			}
			if (name === 'rpress_phone') { return i18n.err_phone || 'Please enter a valid phone number'; }
			if (name === 'rpress_first') { return i18n.err_first || 'Please enter your first name'; }
			return i18n.err_required || 'This field is required.';
		}

		function collect() {
			var bad = [];
			$form.find('input.required, input[required], select.required, textarea.required').each(function () {
				var $f = $(this);
				if (!$f.is(':visible') || $f.prop('disabled')) {
					return;
				}
				var val = $.trim($f.val() || '');
				var empty = val === '';
				var ok = !empty;
				if (ok && ($f.attr('type') === 'email' || ($f.attr('name') || '').toLowerCase() === 'rpress_email')) {
					ok = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(val);
				}
				if (!ok) {
					bad.push({ $f: $f, msg: messageFor($f, empty) });
				}
			});
			return bad;
		}

		function clearFieldError($f) {
			$f.removeClass('error');
			$f.closest('p, label').find('.rpress-field-error').remove();
		}

		function clearAll() {
			$form.find('.error').removeClass('error');
			$form.find('.rpress-field-error').remove();
			$form.find('.rpress-checkout-error-banner').remove();
		}

		function bannerHtml(count) {
			var icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>';
			var tmpl = i18n.err_banner || 'Please fix the %d highlighted field(s) below to place your order.';
			return '<div class="rpress-checkout-error-banner" role="alert">' + icon + '<span></span></div>';
		}

		function show(bad) {
			clearAll();
			bad.forEach(function (item) {
				item.$f.addClass('error');
				var $host = item.$f.closest('p, label');
				if (!$host.length) { $host = item.$f.parent(); }
				$host.append($('<span class="rpress-field-error"></span>').text(item.msg));
			});
			var tmpl = i18n.err_banner || 'Please fix the %d highlighted field(s) below to place your order.';
			var $banner = $(bannerHtml(bad.length));
			$banner.find('span').text(tmpl.replace('%d', bad.length));
			var $card = $form.find('#rpress_checkout_user_info');
			if ($card.length) {
				var $legend = $card.children('legend').first();
				if ($legend.length) { $legend.after($banner); } else { $card.prepend($banner); }
			} else {
				$form.prepend($banner);
			}
			$('html, body').animate({ scrollTop: Math.max(0, bad[0].$f.offset().top - 130) }, 250);
			bad[0].$f.trigger('focus');
		}

		// Capture phase: run before RestroPress's submit handler so we can block
		// the submit while the form is invalid.
		form.addEventListener('submit', function (e) {
			var bad = collect();
			if (bad.length) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				show(bad);
			}
		}, true);

		// Clear a field's error the moment the customer starts fixing it.
		$form.on('input change', '.error', function () {
			clearFieldError($(this));
			if (!$form.find('.error').length) {
				$form.find('.rpress-checkout-error-banner').remove();
			}
		});
	})();
});
