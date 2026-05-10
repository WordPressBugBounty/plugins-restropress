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

    if (typeof DataTable !== 'undefined') {
    new DataTable('#user-orders', {
        responsive: true,
        dom: 'frtip',
        order: [[ 0, 'desc' ]],
        initComplete: function () {
            $('#user-orders_filter input').attr('placeholder','Search orders');
        }
    });
}
    $('body .user-dashboard-wrapper.user-profile ul li').click(function(){
        $('body .user-dashboard-wrapper.user-profile ul li').removeClass('active');
        $(this).addClass('active');
    });
    $('body .user-dashboard-wrapper.user-profile ul li.user-profile').click(function(){
        $('body .user-dashboard-wrapper.user-profile #user-profile').show();
        $('body .user-dashboard-wrapper.user-profile #user-my-address').hide();
        $('body .user-dashboard-wrapper.user-profile #user-my-orders').hide();
    });
    $('body .user-dashboard-wrapper.user-profile ul li.user-my-address').click(function(){
        $('body .user-dashboard-wrapper.user-profile #user-profile').hide();
        $('body .user-dashboard-wrapper.user-profile #user-my-address').show();
        $('body .user-dashboard-wrapper.user-profile #user-my-orders').hide();
    });
    $('body .user-dashboard-wrapper.user-profile ul li.user-my-orders').click(function(){
        $('body .user-dashboard-wrapper.user-profile #user-profile').hide();
        $('body .user-dashboard-wrapper.user-profile #user-my-address').hide();
        $('body .user-dashboard-wrapper.user-profile #user-my-orders').show();
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
                    alert(getUserDashboardMessage('delete_success_text', 'Address Deleted'));
                    location.reload();
                    return;
                }

                alert(getUserDashboardMessage('delete_failed_text', 'Unable to delete the address. Please try again.'));
            },
            error: function(){
                alert(getUserDashboardMessage('delete_error_text', 'Something went wrong while deleting the address. Please try again.'));
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
    var hasCookiesApi = (typeof Cookies !== 'undefined' && Cookies && typeof Cookies.get === 'function' && typeof Cookies.set === 'function');
    if (hasCookiesApi) {
        $('.sidebar-menu li').click(function(){
            var clickedClass = $(this).attr('class');
            clickedClass = clickedClass.replace(/\bactive\b/, '').trim();
            var expirationDate = new Date();
            expirationDate.setTime(expirationDate.getTime() + (5 * 60 * 1000));
            Cookies.set('nav_location', clickedClass,{ expires: expirationDate });
        });
    }
    var navLocation = hasCookiesApi ? Cookies.get('nav_location') : '';
    if (navLocation) {
        $('body .user-dashboard-wrapper.user-profile ul li').removeClass('active');
        $('body .user-dashboard-wrapper.user-profile ul li.'+navLocation).addClass('active');
        if(navLocation == 'user-profile'){
            $('body .user-dashboard-wrapper.user-profile #user-profile').show();
            $('body .user-dashboard-wrapper.user-profile #user-my-address').hide();
            $('body .user-dashboard-wrapper.user-profile #user-my-orders').hide();
        }
        else if(navLocation == 'user-my-orders'){
            $('body .user-dashboard-wrapper.user-profile #user-profile').hide();
            $('body .user-dashboard-wrapper.user-profile #user-my-address').hide();
            $('body .user-dashboard-wrapper.user-profile #user-my-orders').show();
        }             
        else if(navLocation == 'user-my-address'){
            $('body .user-dashboard-wrapper.user-profile #user-profile').hide();
            $('body .user-dashboard-wrapper.user-profile #user-my-address').show();
            $('body .user-dashboard-wrapper.user-profile #user-my-orders').hide();
        }
    }

    function ensureOrderDetailsModal() {
        var $modal = jQuery('#rpressModal').first();

        if ($modal.length) {
            return $modal;
        }

        var modalHtml = [
            '<div class="modal micromodal-slide" id="rpressModal" aria-hidden="true">',
            '  <div class="modal__overlay" tabindex="-1" data-micromodal-close>',
            '    <div class="modal__container modal-content" role="dialog" aria-modal="true">',
            '      <header class="modal__header modal-header">',
            '        <h2 class="modal__title modal-title"></h2>',
            '        <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>',
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
            error: function () {
                $modal.removeClass('show-order-details rpress-order-details-context');
            }
        });
    }

    if (jQuery('.user-dashboard-wrapper, #rpress_user_history').length) {
        jQuery(document).off('click', '.rpress-view-order-btn');
        jQuery(document).on('click.rpressDashboardOrderModal', '.user-dashboard-wrapper .rpress-view-order-btn, #rpress_user_history .rpress-view-order-btn', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            openDashboardOrderModal(jQuery(this));
        });

        jQuery(document).on('click.rpressDashboardOrderModalClose', '#rpressModal.show-order-details [data-micromodal-close], #rpressModal.show-order-details .modal__close', function (e) {
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
