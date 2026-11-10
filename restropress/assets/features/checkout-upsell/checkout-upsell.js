/**
 * Checkout upsell row (3.5).
 *
 * Simple items add straight through the menu's rpress_add_to_cart AJAX. Items
 * with choices carry the .rpress-add-to-cart class, so the menu's own click
 * handler in rp-frontend.js opens the normal item popup for them. Either way
 * the add fires `rpress_added_to_cart`, and on that we drop the card and
 * refresh the checkout summary the same way removing a line does
 * (`rp-refresh-checkout-service.rpress`), with a reload as the fallback.
 */
jQuery(function ($) {
	'use strict';

	var $row = $('#rpress-checkout-upsell');
	if (!$row.length) {
		return;
	}
	var vars = window.rpress_checkout_upsell || {};
	var pendingId = null;

	function removeCard(id) {
		var $item = $row.find('.rpress-upsell-item[data-fooditem-id="' + id + '"]');
		$item.remove();
		if (!$row.find('.rpress-upsell-item').length) {
			$row.remove();
		}
	}

	// Forget the pending add and free the buttons (failed or blocked add).
	function resetPending() {
		pendingId = null;
		$row.find('.rpress-upsell-add').removeClass('rp-loading').prop('disabled', false).removeAttr('aria-busy');
	}

	function refreshCheckout() {
		if ($('#rpress_checkout_order_details').length) {
			$(document.body).trigger('rp-refresh-checkout-service.rpress');
		} else {
			window.location.reload();
		}
	}

	function notifyError(message) {
		if (window.tata && typeof window.tata.error === 'function') {
			window.tata.error((window.rp_scripts && window.rp_scripts.error) || 'Error', message, { position: 'tr' });
		} else {
			window.alert(message);
		}
	}

	// Popup items: remember which card opened the popup. The menu handler
	// (bound on document) does the rest.
	$row.on('click', '.rpress-upsell-add.rpress-add-to-cart', function () {
		pendingId = String($(this).attr('data-fooditem-id'));
	});

	// Simple items: add directly.
	$row.on('click', '.rpress-upsell-add:not(.rpress-add-to-cart)', function (e) {
		e.preventDefault();
		var $btn = $(this);
		if ($btn.hasClass('rp-loading') || $btn.prop('disabled')) {
			return;
		}
		var id = String($btn.attr('data-fooditem-id'));
		pendingId = id;
		$.ajax({
			type: 'POST',
			url: vars.ajaxurl || (window.rp_scripts && window.rp_scripts.ajaxurl),
			dataType: 'json',
			xhrFields: { withCredentials: true },
			data: {
				action: 'rpress_add_to_cart',
				fooditem_id: id,
				fooditem_qty: 1,
				special_instruction: '',
				security: vars.nonce || (window.rp_scripts && window.rp_scripts.add_to_cart_nonce)
			},
			beforeSend: function () {
				$btn.addClass('rp-loading').prop('disabled', true).attr('aria-busy', 'true');
			},
			complete: function () {
				$btn.removeClass('rp-loading').prop('disabled', false).removeAttr('aria-busy');
			},
			success: function (response) {
				if (!response || response.success === false || typeof response.cart_key === 'undefined') {
					resetPending();
					notifyError((response && response.data && response.data.error_msg) || vars.error || 'Error');
					return;
				}
				$(document.body).trigger('rpress_added_to_cart', [response]);
				if (window.tata && typeof window.tata.success === 'function' && window.rp_scripts) {
					var added = (response.merged && window.rp_scripts.quantity_updated_in_cart) ? window.rp_scripts.quantity_updated_in_cart : window.rp_scripts.added_to_cart;
					window.tata.success(window.rp_scripts.success || '', $btn.attr('data-title') + (added || ''), { position: 'tr' });
				}
			},
			error: function () {
				resetPending();
				notifyError(vars.error || 'Error');
			}
		});
	});

	// Orders are paused: the pause script shows the error and skips the add
	// callbacks (ours and the popup's), so drop the pending add here.
	$(document).on('rpress_orders_paused', resetPending);

	// Any add on the checkout page (ours or the popup's) refreshes the summary.
	$(document.body).on('rpress_added_to_cart', function () {
		if (pendingId) {
			removeCard(pendingId);
			pendingId = null;
		}
		refreshCheckout();
	});
});
