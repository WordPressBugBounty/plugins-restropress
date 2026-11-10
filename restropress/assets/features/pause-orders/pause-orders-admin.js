/**
 * Pause orders: Live Orders panel controls.
 *
 * @since 3.5
 */
(function ($) {
	'use strict';

	var cfg = window.rpressPauseOrdersAdmin || {};
	var strings = cfg.strings || {};
	var expiryTimer = null;

	function panel() {
		return $('.rp-pause-orders').first();
	}

	// Refresh the panel shortly after a pause or busy period ends on its own.
	function scheduleExpiryRefresh() {
		window.clearTimeout(expiryTimer);
		var $panel = panel();
		if (!$panel.length) {
			return;
		}
		var now = parseInt($panel.attr('data-now'), 10) || 0;
		var ends = [parseInt($panel.attr('data-until'), 10) || 0, parseInt($panel.attr('data-extra-until'), 10) || 0]
			.filter(function (t) { return t > now; });
		if (!ends.length) {
			return;
		}
		var wait = (Math.min.apply(null, ends) - now + 2) * 1000;
		expiryTimer = window.setTimeout(function () { send('get', 0, true); }, Math.min(wait, 2147483000));
	}

	function send(op, minutes, quiet) {
		var $panel = panel();
		if (!quiet) {
			$panel.addClass('is-loading');
		}
		return $.post(cfg.ajaxUrl, {
			action: 'rpress_pause_orders',
			security: cfg.nonce,
			op: op,
			minutes: minutes
		}).done(function (response) {
			if (response && response.success && response.data && response.data.html) {
				panel().replaceWith(response.data.html);
				scheduleExpiryRefresh();
			} else if (!quiet) {
				window.alert((response && response.data && response.data.message) || strings.error);
			}
		}).fail(function () {
			if (!quiet) {
				window.alert(strings.error);
			}
		}).always(function () {
			panel().removeClass('is-loading');
		});
	}

	$(document).on('click', '.rp-pause-orders-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var op = $btn.attr('data-op');
		var minutes = parseInt($btn.attr('data-minutes'), 10) || 0;
		if (op === 'pause' && minutes === 0 && strings.confirmIndefinite && !window.confirm(strings.confirmIndefinite)) {
			return;
		}
		send(op, minutes, false);
	});

	$(function () {
		scheduleExpiryRefresh();
		// Pick up changes made on another screen or device.
		window.setInterval(function () {
			if (!document.hidden) {
				send('get', 0, true);
			}
		}, 60000);
	});
})(jQuery);
