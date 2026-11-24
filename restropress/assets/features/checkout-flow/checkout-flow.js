/**
 * Checkout flow (3.5.1).
 *
 * 1. Summary cards: the service and time ("Pickup · Today, ASAP") and a
 *    filled-in delivery address collapse to one line with a Change button.
 *    A field flagged invalid inside a collapsed card opens it again.
 * 2. Place order in the order summary on desktop. It mirrors the real
 *    #rpress-purchase-button (label, disabled state) and clicks it, so the
 *    3.5 checks (minimum order, inline errors) and the gateways run as before.
 *
 * Phones keep the sticky bar from rp-checkout-ui.js.
 */
jQuery(function ($) {
	'use strict';

	var cfg = window.rpress_checkout_flow || {};
	var i18n = cfg.i18n || {};
	var $wrap = $('#rpress_checkout_wrap');
	if (!$wrap.length || !document.getElementById('rpress_purchase_form')) {
		return;
	}
	var DESKTOP = window.matchMedia('(min-width: 783px)');
	var uid = 0;

	/* ---------------------------------------------------------------- */
	/* Summary cards                                                      */
	/* ---------------------------------------------------------------- */
	var whenOpen = false; // Stays open across the service-switch re-render.
	var addressOpen = false;

	// Write text only when it changes. rp-checkout-ui.js refetches the
	// service fees on any change in the form column, and that AJAX call
	// ends in refreshCardText(), so an unconditional write would loop.
	function setText($el, value) {
		if ($el.length && $el.text() !== value) {
			$el.text(value);
		}
	}

	function text(el) {
		return $.trim($(el).text()).replace(/\s+/g, ' ');
	}

	function selectedText(select) {
		if (!select || select.selectedIndex < 0) {
			return '';
		}
		return $.trim(select.options[select.selectedIndex].text).replace(/^ASAP(?=\S)/i, 'ASAP ');
	}

	function whenSummary($details) {
		var $tabs = $details.find('.rpress-tabs-wrapper').first();
		var service = text($tabs.find('#rpressdeliveryTab .nav-link.active .rpress-service-tab-label').first()) ||
			text($tabs.find('.rpress-checkout-service-heading-text').first());
		var $pane = $tabs.find('.tab-pane.active').first();
		if (!$pane.length) {
			$pane = $tabs;
		}
		var date = $pane.find('select.rpress_get_delivery_dates')[0];
		var hours = $pane.find('select.rpress-hrs')[0];
		var parts = [];
		if (date) {
			parts.push(cfg.today && date.value === cfg.today ? (i18n.today || 'Today') : selectedText(date));
		}
		if (hours) {
			var h = selectedText(hours);
			if (!h) {
				return ''; // No time picked: keep the controls open.
			}
			parts.push(h);
		}
		return { service: service, when: parts.join(', ') };
	}

	function addressSummary($details) {
		var $fields = $details.find('p.rpress-checkout-fields').not('.hidden');
		if (!$fields.length) {
			return '';
		}
		var values = [];
		var complete = true;
		$fields.each(function () {
			var input = $(this).find('input')[0];
			if (!input) {
				return;
			}
			var v = $.trim(input.value || '');
			var required = $(this).find('.rpress-required-indicator').length > 0 || input.required;
			if (required && !v) {
				complete = false;
			}
			if ('true' === input.getAttribute('aria-invalid')) {
				complete = false;
			}
			if (v) {
				values.push(v);
			}
		});
		return complete && values.length ? values.join(', ') : '';
	}

	function card(kind, title, value, regionId) {
		var id = 'rp-cf-card-' + kind;
		var label = 'when' === kind ? (i18n.change_when || 'Change service and time') : (i18n.change_address || 'Change delivery address');
		return $(
			'<div class="rp-cf-card" data-kind="' + kind + '" id="' + id + '">' +
				'<div class="rp-cf-card-text"><span class="rp-cf-card-title"></span><span class="rp-cf-card-value"></span></div>' +
				'<button type="button" class="rp-cf-change" aria-expanded="false" aria-controls="' + regionId + '"></button>' +
			'</div>'
		)
			.find('.rp-cf-card-title').text(title).end()
			.find('.rp-cf-card-value').text(value).end()
			.find('.rp-cf-change').text(i18n.change || 'Change').attr('aria-label', label).end();
	}

	function regionId(el) {
		if (!el.id) {
			el.id = 'rp-cf-region-' + (++uid);
		}
		return el.id;
	}

	function setOpen($details, kind, open) {
		var $card = $details.find('.rp-cf-card[data-kind="' + kind + '"]');
		var $region = $('#' + $card.find('.rp-cf-change').attr('aria-controls'));
		$card.toggleClass('is-open', open);
		$card.find('.rp-cf-change')
			.attr('aria-expanded', open ? 'true' : 'false')
			.text(open ? (i18n.done || 'Done') : (i18n.change || 'Change'));
		$region.toggleClass('rp-cf-collapsed', !open);
		if ('when' === kind) {
			whenOpen = open;
		} else {
			addressOpen = open;
		}
	}

	function buildCards() {
		if (!cfg.summary_cards) {
			return;
		}
		var $details = $('#rpress_checkout_order_details');
		if (!$details.length) {
			return;
		}
		$details.find('.rp-cf-card').remove();
		$details.find('.rp-cf-collapsed').removeClass('rp-cf-collapsed');
		$details.find('.rp-cf-address-region').children().unwrap();

		// When: service plus date and time.
		var $tabs = $details.find('.rpress-tabs-wrapper').first();
		var when = $tabs.length ? whenSummary($details) : '';
		if (when && when.when) {
			var $when = card('when', when.service || (i18n.when || 'When'), when.when, regionId($tabs[0]));
			$tabs.before($when);
			setOpen($details, 'when', whenOpen);
		}

		// Delivery address, when every required part is already filled in.
		var address = addressSummary($details);
		if (address) {
			var $fields = $details.find('p.rpress-checkout-fields');
			$fields.wrapAll('<div class="rp-cf-address-region"></div>');
			var $region = $details.find('.rp-cf-address-region');
			$region.before(card('address', i18n.address || 'Delivery address', address, regionId($region[0])));
			setOpen($details, 'address', addressOpen);
		}
	}

	function refreshCardText() {
		var $details = $('#rpress_checkout_order_details');
		var $when = $details.find('.rp-cf-card[data-kind="when"]');
		if ($when.length) {
			var w = whenSummary($details);
			if (w && w.when) {
				setText($when.find('.rp-cf-card-title'), w.service || (i18n.when || 'When'));
				setText($when.find('.rp-cf-card-value'), w.when);
			}
		}
		var $addr = $details.find('.rp-cf-card[data-kind="address"]');
		if ($addr.length && !$addr.hasClass('is-open')) {
			var a = addressSummary($details);
			if (a) {
				setText($addr.find('.rp-cf-card-value'), a);
			}
		}
	}

	$(document).on('click', '#rpress_checkout_order_details .rp-cf-change', function () {
		var $card = $(this).closest('.rp-cf-card');
		var $details = $('#rpress_checkout_order_details');
		var open = !$card.hasClass('is-open');
		if (!open) {
			refreshCardText();
		}
		setOpen($details, $card.data('kind'), open);
		if (open) {
			var $first = $('#' + $(this).attr('aria-controls')).find('select:visible, input:visible, a.nav-link.active').first();
			if ($first.length) {
				$first.trigger('focus');
			}
		}
	});

	$(document).on('change input', '#rpress_checkout_order_details select, #rpress_checkout_order_details input', refreshCardText);

	// A field flagged invalid inside a collapsed card: open the card so the
	// customer can see and fix it (checkout-ux focuses the first one).
	if (window.MutationObserver) {
		new MutationObserver(function (records) {
			records.forEach(function (r) {
				if ('true' !== r.target.getAttribute('aria-invalid')) {
					return;
				}
				var $region = $(r.target).closest('.rp-cf-collapsed');
				if ($region.length) {
					var $card = $('.rp-cf-change[aria-controls="' + $region.attr('id') + '"]').closest('.rp-cf-card');
					setOpen($('#rpress_checkout_order_details'), $card.data('kind'), true);
				}
			});
		}).observe(document.getElementById('rpress_purchase_form'), { subtree: true, attributes: true, attributeFilter: ['aria-invalid'] });
	}

	// rp-frontend.js re-renders the order details card on a service switch.
	$(document.body).on('rp-checkout-update-service-option', function () {
		window.setTimeout(buildCards, 0);
	});
	// Hours are reloaded by AJAX when the date changes.
	$(document).ajaxComplete(function () {
		window.setTimeout(refreshCardText, 0);
	});

	buildCards();

	/* ---------------------------------------------------------------- */
	/* Place order in the summary (desktop)                               */
	/* ---------------------------------------------------------------- */
	var $cta = null;

	function realButton() {
		return document.getElementById('rpress-purchase-button');
	}

	function syncCta() {
		var real = realButton();
		var usable = DESKTOP.matches && real && 'none' !== real.style.display;
		$wrap.toggleClass('rp-cf-summary-cta', !!usable);
		if (!usable) {
			if ($cta) {
				$cta.prop('hidden', true);
			}
			return;
		}
		if (!$cta || !document.body.contains($cta[0])) {
			$cta = $(
				'<div class="rp-cf-cta">' +
					'<button type="button" class="rp-cf-place-order"></button>' +
					'<p class="rp-cf-cta-hint" id="rp-cf-cta-hint" hidden></p>' +
				'</div>'
			);
			var $table = $('#rpress_checkout_cart_wrap');
			if (!$table.length) {
				return;
			}
			$table.append($cta);
		}
		$cta.prop('hidden', false);
		var label = real.value || text(real) || i18n.place_order || 'Place order';
		var $btn = $cta.find('.rp-cf-place-order');
		setText($btn, label);
		var blocked = real.disabled || 'true' === real.getAttribute('aria-disabled');
		$btn.attr('aria-disabled', blocked ? 'true' : null).toggleClass('is-blocked', blocked)
			.attr('aria-describedby', blocked ? 'rp-cf-cta-hint' : null);

		// The minimum-order hint from checkout-ux, shown under the button.
		var hint = document.getElementById('rpress-cux-min-hint');
		var $hint = $cta.find('.rp-cf-cta-hint');
		if (hint && $wrap.hasClass('rpress-cux-below-min') && text(hint)) {
			// Copy checkout-ux's own markup (bold amount, then the rule) without ids.
			var copy = hint.cloneNode(true);
			$(copy).find('[id]').removeAttr('id');
			$(copy).find('svg, .rpress-cux-min-icon').remove();
			if ($hint.html() !== copy.innerHTML) {
				$hint.html(copy.innerHTML);
			}
			$hint.prop('hidden', false);
		} else if (!$hint.prop('hidden')) {
			$hint.prop('hidden', true).empty();
		}
	}

	$(document).on('click', '.rp-cf-place-order', function () {
		var real = realButton();
		if (real) {
			// Same path as the phone sticky bar: checkout-ux and rp-ajax see a
			// click on the real button.
			real.click();
		}
	});

	var syncTimer = null;
	function queueSync() {
		window.clearTimeout(syncTimer);
		syncTimer = window.setTimeout(syncCta, 30);
	}
	if (window.MutationObserver) {
		var formWrap = document.getElementById('rpress_checkout_form_wrap') || document.body;
		new MutationObserver(queueSync).observe(formWrap, { subtree: true, childList: true, attributes: true, attributeFilter: ['disabled', 'aria-disabled', 'value', 'style', 'class', 'hidden'] });
	}
	if (DESKTOP.addEventListener) {
		DESKTOP.addEventListener('change', syncCta);
	} else if (DESKTOP.addListener) {
		DESKTOP.addListener(syncCta);
	}
	$(document.body).on('rpress_gateway_loaded updated_cart rpress_cart_item_removed', queueSync);
	syncCta();
});
