jQuery('document').ready(function($) {
    function parseAddressAjaxResponse(response) {
        if (typeof response === 'string') {
            try {
                return JSON.parse(response);
            } catch (error) {
                return {};
            }
        }

        return response || {};
    }

    function getUserDashboardMessage(key, fallback) {
        if (typeof users !== 'undefined' && users[key]) {
            return users[key];
        }

        return fallback;
    }

    function dashboardError(message) {
        if (typeof window.rpress_notify === 'function') {
            window.rpress_notify(message, { type: 'error' });
        } else {
            window.alert(message);
        }
    }

    // Profile: a new email address needs the current password (3.5.1).
    $(document).on('input change', '.profile-form-wrap input[name="email"]', function () {
        var current = String($(this).data('current') || '').toLowerCase();
        var changed = $.trim(String($(this).val() || '')).toLowerCase() !== current;
        var $row = $(this).closest('form').find('.rpress-email-password');
        $row.prop('hidden', !changed);
        $row.find('input').prop('required', changed);
        if (!changed) {
            $row.find('input').val('');
        }
    });

    var $account = $('.user-dashboard-wrapper.rpress-account').first();

    // 3.5 links pointed at a tab with a #hash (e.g. /my-account/#user-my-reservations).
    // Each section now has its own URL: send old links there.
    if ($account.length && window.location.hash) {
        var sectionUrls = $account.data('section-urls') || {};
        var legacyId = window.location.hash.replace('#', '');
        if (sectionUrls[legacyId] && legacyId !== ($account.find('.rpress-account-section').attr('id') || '')) {
            window.location.replace(sectionUrls[legacyId]);
            return;
        }
    }

    // Phones show the menu as one scrollable row: bring the open section's item into view.
    var $activeNav = $account.find('ul.sidebar-menu li.active').first();
    if ($activeNav.length) {
        var menu = $activeNav.parent()[0];
        if (menu.scrollWidth > menu.clientWidth) {
            var itemBox = $activeNav[0].getBoundingClientRect();
            var menuBox = menu.getBoundingClientRect();
            menu.scrollLeft += (itemBox.left - menuBox.left) - (menu.clientWidth - itemBox.width) / 2;
        }
    }

    // Panels added through the 3.5 hooks (rpress_user_dashboard_menu_items /
    // rpress_user_dashboard_panels) still open in place: an <li class="user-xyz">
    // without a link shows <div id="user-xyz">.
    $account.on('click', 'ul.sidebar-menu li', function (e) {
        var $item = $(this);
        if ($item.children('a').length) {
            return;
        }
        var match = ($item.attr('class') || '').match(/\buser-[a-z0-9-]+\b/);
        var $panel = match ? $account.find('.col-lg-9[id="' + match[0] + '"]') : $();
        if (!$panel.length) {
            return;
        }
        e.preventDefault();
        $account.find('.col-lg-9[id^="user-"], .rpress-account-section').hide();
        $panel.show();
        $account.find('ul.sidebar-menu li').removeClass('active').find('a').removeAttr('aria-current');
        $item.addClass('active');
    });

    $('body .user-profile .delete-address').click(function(e){
        e.preventDefault();

        if (!window.confirm(getUserDashboardMessage('delete_confirm_text', 'Are you sure you want to delete this address?'))) {
            return;
        }

        var index = $(this).data('index');
        
        $.ajax({
            url: users.ajaxurl,
            type: 'POST',
            data: {
                index: index,
                security: users.address_nonce,
                action: 'rpress_delete_user_address'
            },
            success: function(response){
                var parsedResponse = parseAddressAjaxResponse(response);

                if (parsedResponse.success) {
                    // Reload with a notice: the default address may have moved.
                    var url = new URL(window.location.href);
                    url.searchParams.set('rpress_account', 'address_deleted');
                    window.location.replace(url.toString());
                    return;
                }

                dashboardError(getUserDashboardMessage('delete_failed_text', 'Unable to delete the address. Please try again.'));
            },
            error: function(){
                dashboardError(getUserDashboardMessage('delete_error_text', 'Something went wrong while deleting the address. Please try again.'));
            }
        });
    });
    $('body .user-profile .default-address').click(function(e) {
        e.preventDefault();
        var index = $(this).data('index');
        
        $.ajax({
            url: users.ajaxurl,
            type: 'POST',
            data: {
                index: index,
                security: users.address_nonce,
                action: 'rpress_default_user_address'
            },
            success: function(response){
                location.reload();
            },
    
        });
    });
    // "Load more orders" under the order cards (account Orders and [order_history]).
    $(document).on('click', '.rpress-orders-load-more', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.prop('disabled')) {
            return;
        }
        var $wrap = $btn.closest('.rpress-orders-more');
        var $list = $wrap.prevAll('.rpress-order-cards').first();
        var next = (parseInt($btn.attr('data-page'), 10) || 1) + 1;
        var ajaxUrl = (typeof rp_scripts !== 'undefined' && rp_scripts.ajaxurl) ? rp_scripts.ajaxurl : users.ajaxurl;
        var nonce = (typeof users !== 'undefined' && users.order_details_nonce) ? users.order_details_nonce : (typeof rp_scripts !== 'undefined' ? rp_scripts.order_details_nonce : '');

        $btn.prop('disabled', true).attr('aria-busy', 'true').addClass('rp-loading');
        $btn.find('.rp-ajax-toggle-text').addClass('rp-text-visibility');
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'rpress_more_order_history',
                security: nonce,
                paged: next,
                context: $btn.attr('data-context') || 'history'
            }
        }).done(function (response) {
            var data = response && response.data ? response.data : {};
            if (!response || !response.success || typeof data.html !== 'string') {
                orderListFailed();
                return;
            }
            var $cards = $($.parseHTML(data.html.trim())).filter('article');
            $list.append($cards);
            $btn.attr('data-page', next);
            if (!data.has_more) {
                $wrap.remove();
            }
            // Move focus to the first new order so keyboard users carry on from there.
            var $first = $cards.first().find('a, button').first();
            if ($first.length) {
                $first.trigger('focus');
            }
        }).fail(orderListFailed).always(function () {
            $btn.prop('disabled', false).removeAttr('aria-busy').removeClass('rp-loading');
            $btn.find('.rp-ajax-toggle-text').removeClass('rp-text-visibility');
        });

        function orderListFailed() {
            var message = getUserDashboardMessage('load_orders_error_text', 'Could not load more orders. Please try again.');
            if (typeof window.rpress_notify === 'function') {
                rpress_notify(message, { type: 'error', duration: 5000 });
            } else {
                window.alert(message);
            }
        }
    });

    function ensureOrderDetailsModal() {
        var $modal = jQuery('#rpressModal').first();

        if ($modal.length) {
            return $modal;
        }

        var modalHtml = [
            '<div class="modal micromodal-slide" id="rpressModal" aria-hidden="true">',
            '  <div class="modal__overlay" tabindex="-1" data-micromodal-close>',
            '    <div class="modal__container modal-content" role="dialog" aria-modal="true" aria-labelledby="rpressModal-title">',
            '      <header class="modal__header modal-header">',
            '        <h2 id="rpressModal-title" class="modal__title modal-title"></h2>',
            '        <button class="modal__close" aria-label="' + ((typeof rp_scripts !== 'undefined' && rp_scripts.close_modal) ? rp_scripts.close_modal : 'Close modal') + '" data-micromodal-close></button>',
            '      </header>',
            '      <main class="modal__content modal-body"></main>',
            '    </div>',
            '  </div>',
            '</div>'
        ].join('');

        jQuery('body').append(modalHtml);
        return jQuery('#rpressModal').first();
    }

    function openDashboardOrderModal($trigger) {
        var $modal = ensureOrderDetailsModal();
        var orderId = $trigger.attr('data-order-id');
        var ajaxUrl = (typeof rp_scripts !== 'undefined' && rp_scripts.ajaxurl) ? rp_scripts.ajaxurl : users.ajaxurl;
        var nonce = (typeof rp_scripts !== 'undefined' && rp_scripts.order_details_nonce) ? rp_scripts.order_details_nonce : ((typeof users !== 'undefined' && users.order_details_nonce) ? users.order_details_nonce : '');

        if (!orderId || !ajaxUrl || !nonce) {
            return;
        }

        $modal.addClass('show-order-details rpress-order-details-context');

        jQuery.ajax({
            type: 'POST',
            url: ajaxUrl,
            dataType: 'json',
            data: {
                action: 'rpress_show_order_details',
                order_id: orderId,
                security: nonce
            },
            beforeSend: function () {
                $trigger.addClass('rp-loading');
                $trigger.find('.rp-ajax-toggle-text').addClass('rp-text-visibility');
            },
            complete: function () {
                $trigger.removeClass('rp-loading');
                $trigger.find('.rp-ajax-toggle-text').removeClass('rp-text-visibility');
            },
            success: function (response) {
                if (!response || response.success !== true || !response.data || typeof response.data.html !== 'string') {
                    orderDetailsFailed($modal, response);
                    return;
                }

                $modal.find('.modal__container').html(response.data.html);

                if (typeof MicroModal !== 'undefined' && typeof MicroModal.show === 'function') {
                    MicroModal.show('rpressModal', { disableScroll: true });
                } else {
                    $modal.addClass('is-open').attr('aria-hidden', 'false');
                    jQuery('html, body').addClass('modal-open').css('overflow', 'hidden');
                }
            },
            error: function (xhr) {
                orderDetailsFailed($modal, xhr && xhr.responseJSON);
            }
        });
    }

    // Say why nothing opened instead of failing silently.
    function orderDetailsFailed($modal, response) {
        $modal.removeClass('show-order-details rpress-order-details-context');
        var message = (response && response.data && response.data.message) ? response.data.message : getUserDashboardMessage('order_details_error_text', 'Could not load this order. Please try again.');
        if (typeof window.rpress_notify === 'function') {
            rpress_notify(message, { type: 'error', duration: 5000 });
        } else {
            window.alert(message);
        }
    }

    if (jQuery('.user-dashboard-wrapper, #rpress_user_history').length) {
        jQuery(document).off('click', '.rpress-view-order-btn');
        jQuery(document).on('click.rpressDashboardOrderModal', '.user-dashboard-wrapper .rpress-view-order-btn, #rpress_user_history .rpress-view-order-btn', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            openDashboardOrderModal(jQuery(this));
        });

        jQuery(document).on('click.rpressDashboardOrderModalClose', '#rpressModal.show-order-details [data-micromodal-close], #rpressModal.show-order-details .modal__close', function (e) {
            // The backdrop wraps the popup, so a delegated handler also sees
            // clicks inside it (buttons, links, text). Only a click on the
            // backdrop itself closes; everything inside keeps working.
            if (e.target !== this && jQuery(this).hasClass('modal__overlay')) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();

            if (typeof MicroModal !== 'undefined' && typeof MicroModal.close === 'function') {
                MicroModal.close('rpressModal');
            } else {
                jQuery('#rpressModal').removeClass('is-open show-order-details rpress-order-details-context').attr('aria-hidden', 'true');
                jQuery('html, body').removeClass('modal-open').css('overflow', '');
            }
        });
    }
});
function addaddress() {
    var div = document.getElementById("add-address-bg");

    if (!div) {
        return;
    }

    if (div.className === "") {
        div.className = "active";
    } else {
        div.className = "";
        return;
    }

    var $form = jQuery('#add-address-bg .profile-form-wrap');

    if ($form.length) {
        $form[0].reset();
    }

    jQuery('#add-address-bg .box-header .box-title').text(
        (typeof users !== 'undefined' && users.add_address_title_text) ? users.add_address_title_text : 'Add Delivery Address'
    );
    jQuery('#form_submit_button').val(
        (typeof users !== 'undefined' && users.save_address_text) ? users.save_address_text : 'Save Address'
    );
    jQuery('#edit_user_address_index').val('');
    jQuery('#default-address-checkboxInput6').prop('checked', false);
    jQuery('input[name="address_type"]').prop('checked', false);
    jQuery('input[name="address_type"][value="Home"]').prop('checked', true);
}
function editaddress(context) {
    var $context = jQuery();

    if (context && context.currentTarget) {
        $context = jQuery(context.currentTarget);
    } else if (context && context.target) {
        $context = jQuery(context.target);
    } else if (context) {
        $context = jQuery(context);
    }

    var addressWrap = $context.closest('.address-wrap');

    if (!addressWrap.length) {
        return;
    }

    var div = document.getElementById("add-address-bg");

    if (!div) {
        return;
    }

    if (div.className === "") {
        div.className = "active";
    }
    
    // Get the values within the nearest 'address-wrap' element
    var addressType     = addressWrap.find('.type-of-address').text().trim();
    var firstname       = addressWrap.find('.user-firstname').text().trim();
    var lastname        = addressWrap.find('.user-lastname').text().trim();
    var phone           = addressWrap.find('.user-contact').text().trim();
    var pincode         = addressWrap.find('.user-pin').text().trim();
    var city            = addressWrap.find('.user-city').text().trim();
    var address         = addressWrap.find('.user-street-address').text().trim();
    var apartment       = addressWrap.find('.user-apt-suite').text().trim();
    var addressIndex    = addressWrap.find('.user-address-index').text().trim();
    var defaultIndex    = addressWrap.find('.user-default-address').text().trim();
    
    // Example: Update a form with the retrieved values
    jQuery('#add-address-bg .box-header .box-title').text(
        (typeof users !== 'undefined' && users.edit_address_title_text) ? users.edit_address_title_text : 'Edit Delivery Address'
    );
    jQuery('#form_submit_button').val(
        (typeof users !== 'undefined' && users.save_changes_text) ? users.save_changes_text : 'Save Changes'
    );
    jQuery('#fastNameInput').val(firstname);
    jQuery('#lastNameInput').val(lastname);
    jQuery('#phoneInput').val(phone);
    jQuery('#pincodeInput').val(pincode);
    jQuery('#cityAddress').val(city);
    jQuery('#streetaddress').val(address);
    jQuery('#aptsuite').val(apartment);
    jQuery('#edit_user_address_index').val(addressIndex);
    jQuery('#default-address-checkboxInput6').prop('checked', defaultIndex === '1');

    jQuery('input[name="address_type"]').prop('checked', false);

    if (addressType) {
        jQuery('input[name="address_type"]').filter(function () {
            return jQuery(this).val().toLowerCase() === addressType.toLowerCase();
        }).prop('checked', true);
    }

    if (!jQuery('input[name="address_type"]:checked').length) {
        jQuery('input[name="address_type"][value="Home"]').prop('checked', true);
    }
}
jQuery(function($) {
    if (window.location.pathname === '/order-online/') {
        $('body').addClass('order-online');
    }
});
