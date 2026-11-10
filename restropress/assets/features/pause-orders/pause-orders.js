/**
 * Pause orders: storefront.
 *
 * Several core handlers (add to cart, the service picker) treat any JSON
 * reply as success, so wrap their callbacks: when the server says orders are
 * paused, show its error, stop, and trigger `rpress_orders_paused` on document
 * (args: error data, ajax options) so other listeners can reset their state.
 *
 * @since 3.5
 */
(function ($) {
	'use strict';

	var cfg = window.rpressPauseOrders || {};
	var guarded = /(^|&)action=rpress_(add_to_cart|show_products|show_delivery_options)(&|$)/;

	function isPausedError(response) {
		return response && response.success === false && response.data && response.data.error_type === 'orders_paused';
	}

	function showError(message) {
		if (window.tata && typeof window.tata.error === 'function') {
			window.tata.error(cfg.errorTitle || 'Error', message, { position: 'tr' });
		} else {
			window.alert(message);
		}
	}

	$.ajaxPrefilter(function (options) {
		var data = typeof options.data === 'string' ? options.data : '';
		if (!guarded.test(data) || typeof options.success !== 'function') {
			return;
		}
		var original = options.success;
		options.success = function (response) {
			if (isPausedError(response)) {
				showError(response.data.error_msg);
				$('#rpressModal .submit-fooditem-button').removeClass('disable_click rp-loading');
				// The wrapped success callback is skipped, so tell other scripts
				// (checkout upsell and friends) the request failed.
				$(document).trigger('rpress_orders_paused', [response.data, options]);
				return;
			}
			return original.apply(this, arguments);
		};
	});
})(jQuery);
