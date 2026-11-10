/**
 * Apple Pay / Google Pay on checkout via Stripe's Payment Request Button.
 *
 * Shown only while Stripe is the selected gateway and the browser has a
 * wallet. The sheet total follows the cart (service switch, tip, coupon,
 * taxes). After the customer approves, the form is validated with the normal
 * checkout AJAX check, then posted with the wallet PaymentMethod; the server
 * answers with JSON (see includes/features/payments/stripe-wallets.php).
 */
/* global jQuery, Stripe, rpress_global_vars */
(function ($) {
	'use strict';

	var cfg = window.rpress_stripe_wallet;
	if (!cfg || typeof window.Stripe !== 'function' || !cfg.key) {
		return;
	}

	var stripe = Stripe(cfg.key);
	var paymentRequest = null;
	var prButton = null;
	var available = null; // null = unknown, true/false after canMakePayment.
	var total = cfg.total;
	var busy = false;
	var refreshTimer = null;
	var lastSeenTotal = null;

	function selectedGateway() {
		var v = $('#rpress-gateway option:selected, input.rpress-gateway:checked').val();
		if (!v) {
			v = $('#rpress_purchase_form input[name="rpress-gateway"]').val();
		}
		return v || '';
	}

	function wrap() {
		return $('#rpress-stripe-wallet');
	}

	function showError(html) {
		$('.rpress_errors').remove();
		var anchor = (window.rpress_global_vars && rpress_global_vars.checkout_error_anchor) || '#rpress_purchase_submit';
		var $msg = /^\s*</.test(html) ? $(html) : $('<div class="rpress_errors rpress-alert rpress-alert-error"><p class="rpress_error"></p></div>').find('p').text(html).end();
		$(anchor).before($msg);
		$(document.body).trigger('rpress_checkout_error', [html]);
		var $first = $('.rpress_errors:visible').first();
		if ($first.length) {
			$('html, body').stop().animate({ scrollTop: Math.max(0, $first.offset().top - 120) }, 400);
		}
	}

	function buildRequest() {
		paymentRequest = stripe.paymentRequest({
			country: cfg.country || 'US',
			currency: total.currency,
			total: { label: total.label, amount: total.amount },
			requestPayerName: false,
			requestPayerEmail: false
		});
		paymentRequest.on('paymentmethod', onPaymentMethod);
		prButton = stripe.elements().create('paymentRequestButton', {
			paymentRequest: paymentRequest,
			style: { paymentRequestButton: { type: 'buy', theme: 'dark', height: '46px' } }
		});
		prButton.on('click', function (ev) {
			var form = document.getElementById('rpress_purchase_form');
			if (busy) {
				ev.preventDefault();
				return;
			}
			if (form && typeof form.checkValidity === 'function' && !form.checkValidity()) {
				ev.preventDefault();
				if (typeof form.reportValidity === 'function') {
					form.reportValidity();
				}
			}
		});
		return paymentRequest.canMakePayment().then(function (result) {
			available = !!result;
		}, function () {
			available = false;
		});
	}

	// Mount into the (re-rendered) container when Stripe is the chosen gateway.
	function render() {
		var $wrap = wrap();
		if (!$wrap.length) {
			return;
		}
		if ('stripe' !== selectedGateway() || !available || total.amount <= 0) {
			$wrap.attr('hidden', true);
			return;
		}
		var el = document.getElementById('rpress-stripe-wallet-button');
		if (el && !el.querySelector('iframe')) {
			try { prButton.unmount(); } catch (e) { /* not mounted yet */ }
			prButton.mount(el);
		}
		$wrap.removeAttr('hidden');
	}

	function setup() {
		if (paymentRequest) {
			render();
			return;
		}
		buildRequest().then(render);
	}

	function refreshTotal() {
		return $.post(cfg.ajaxurl, { action: 'rpress_stripe_wallet_total', security: cfg.nonce }).then(function (res) {
			if (!res || !res.success || !res.data) {
				return;
			}
			var changed = res.data.amount !== total.amount;
			total = res.data;
			if (paymentRequest && changed && !busy) {
				try {
					paymentRequest.update({ total: { label: total.label, amount: total.amount } });
				} catch (e) { /* sheet open: next refresh catches up */ }
			}
			render();
		});
	}

	function scheduleRefresh() {
		clearTimeout(refreshTimer);
		refreshTimer = setTimeout(refreshTotal, 350);
	}

	// Same service fields the core purchase click sends.
	function serviceFields() {
		var out = {};
		var $tab = $('.rp-checkout-service-option #rpressdeliveryTab .single-service-selected.active, #rpress_checkout_order_details #rpressdeliveryTab .single-service-selected.active').first();
		var type = $tab.length ? String($tab.data('service-type') || '').toLowerCase() : '';
		var $scope = $('.rp-checkout-service-option').first();
		if (!$scope.length) {
			$scope = $('#rpress_checkout_order_details').first();
		}
		var $pane = type ? $scope.find('.delivery-settings-wrapper#nav-' + type).first() : $();
		if (!$pane.length) {
			$pane = $scope.find('.delivery-settings-wrapper.active').first();
		}
		if (type) {
			out.rpress_service_type = type;
		}
		var time = String($pane.find('.rpress-hrs').first().val() || '');
		var date = String($pane.find('.rpress_get_delivery_dates').first().val() || '');
		if (time) {
			out.rpress_service_time = time;
		}
		if (date) {
			out.rpress_service_date = date;
		}
		return out;
	}

	function onPaymentMethod(ev) {
		var $form = $('#rpress_purchase_form');
		busy = true;
		$form.find('input[name="rpress-gateway"]').val('stripe');
		var base = $form.serialize() + '&' + $.param($.extend({ 'rpress-gateway': 'stripe' }, serviceFields()));
		var finish = function (state, message) {
			busy = false;
			ev.complete(state);
			if (message) {
				showError(message);
			}
		};

		$.post(cfg.ajaxurl, base + '&action=rpress_process_checkout&rpress_ajax=true').then(function (data) {
			if ('success' !== $.trim(data)) {
				finish('fail', data);
				return;
			}
			$.ajax({
				url: $form.attr('action') || window.location.href,
				type: 'POST',
				dataType: 'json',
				data: base + '&' + $.param({
					rpress_stripe_wallet_pm: ev.paymentMethod.id,
					rpress_stripe_wallet_amount: total.amount
				})
			}).then(function (res) {
				if (!res || !res.result) {
					finish('fail', cfg.i18n.generic);
					return;
				}
				if ('success' === res.result) {
					ev.complete('success');
					window.location.href = res.redirect;
					return;
				}
				if ('requires_action' === res.result) {
					ev.complete('success');
					var next = typeof stripe.handleNextAction === 'function'
						? stripe.handleNextAction({ clientSecret: res.client_secret })
						: stripe.confirmCardPayment(res.client_secret);
					next.then(function (r) {
						busy = false;
						if (r.error) {
							showError(r.error.message || cfg.i18n.generic);
							return;
						}
						window.location.href = res.redirect;
					});
					return;
				}
				if ('total_changed' === res.result && res.total) {
					total = res.total;
				}
				finish('fail', res.message || cfg.i18n.generic);
				refreshTotal();
			}, function () {
				finish('fail', cfg.i18n.generic);
			});
		}, function () {
			finish('fail', cfg.i18n.generic);
		});
	}

	$(document.body).on('rpress_gateway_loaded', setup);
	$(document.body).on('change', 'select#rpress-gateway, input.rpress-gateway', function () {
		setTimeout(render, 0);
	});
	$(document.body).on('rpress_discount_applied rpress_discount_removed rpress_taxes_recalculated rpress_cart_updated', scheduleRefresh);

	// Service switches and tips re-render the cart totals; watch the amount.
	if (window.MutationObserver) {
		new MutationObserver(function () {
			var $amt = $('.rpress_cart_amount').first();
			var seen = $amt.attr('data-total') + '|' + $.trim($amt.text());
			if (seen !== lastSeenTotal) {
				lastSeenTotal = seen;
				scheduleRefresh();
			}
		}).observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['data-total'] });
	}

	$(function () {
		if (wrap().length) {
			setup();
		}
	});
})(jQuery);
