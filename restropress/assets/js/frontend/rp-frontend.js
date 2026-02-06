/* Get RestroPress Cookie */
function rp_getCookie(cname) {
  var name = cname + "=";
  var ca = document.cookie.split(';');
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) == ' ') c = c.substring(1);
    if (c.indexOf(name) != -1) return c.substring(name.length, c.length);
  }
  return "";
}
function remove_show_service_options() {
  jQuery('#rpressModal')
    .removeClass('show-service-options');
}
/* Set default addons */
function rp_checked_default_subaddon() {
  if (jQuery('#fooditem-details .rp-addons-data-wrapper .food-item-list.active').length > 0) {
    jQuery('#fooditem-details .rp-addons-data-wrapper .food-item-list.active').each(function () {
      var element = jQuery(this).find('input');
      if (element.hasClass('checked')) {
        jQuery(this).find('input').prop('checked', true);
      }
    });
  }
}
/* Set RestroPress Cookie */
function rp_setCookie(cname, cvalue, ex_time) {
  var d = new Date();
  d.setTime(d.getTime() + (ex_time * 60 * 1000));
  var expires = "expires=" + d.toUTCString();
  document.cookie = cname + "=" + cvalue + "; " + expires + ";path=/";
}
/* Get RestroPress Storage Data */
function rp_get_storage_data() {
  var serviceType = rp_getCookie('service_type');
  var serviceTime = rp_getCookie('service_time');
  if (typeof serviceType == undefined || serviceType == '') {
    return false;
  } else {
    return true;
  }
}
/* Display Dynamic Addon Price Based on Selected Variation */
function show_dymanic_pricing(container, ele) {
  var price_key = ele.val();
  if (price_key !== 'undefined') {
    var $wrapper = jQuery('#' + container + ' .rp-addons-data-wrapper');
    jQuery('#' + container + ' .rp-addons-data-wrapper .food-item-list')
      .removeClass('active');
    jQuery('#' + container + ' .rp-addons-data-wrapper .food-item-list.list_' + price_key)
      .addClass('active');
    // remove border from the last active only
    $wrapper.find('.food-item-list.active').last().css('border-bottom', '0');
  }
}
/* Calculate Live Price On Click */
function update_modal_live_price(fooditem_container) {
  //Add changes code
  var single_price = parseFloat(jQuery('#rpressModal .cart-item-price')
    .attr('data-price'));
  var quantity = parseInt(jQuery('input[name=quantity]')
    .val());
  /* Act on the variations */
  jQuery('#' + fooditem_container + ' .rp-variable-price-wrapper .food-item-list')
    .each(function () {
      var element = jQuery(this)
        .find('input');
      if (element.is(':checked')) {
        // Dynamic addon Price
        show_dymanic_pricing(fooditem_container, element);
        var attrs = element.attr('data-value');
        var attrs_arr = attrs.split('|');
        var price = attrs_arr[2];
        single_price = parseFloat(price);
      }
    });
  /* Act on the addons */
  jQuery('#' + fooditem_container + ' .rp-addons-data-wrapper .food-item-list.active')
    .each(function () {
      var element = jQuery(this)
        .find('input');
      if (element.is(':checked')) {
        var attrs = element.val();
        var attrs_arr = attrs.split('|');
        var price = attrs_arr[2];
        if (price != '') {
          single_price = parseFloat(single_price) + parseFloat(price);
        }
      }
    });
  /* Updating as per current quantity */
  total_price = single_price * quantity;
  /* Update the price in Submit Button */
  if (rp_scripts.decimal_separator == ',') {
    total_price_v = total_price.toFixed(2)
      .split('.')
      .join(',');
  } else {
    total_price_v = total_price.toFixed(2);
  }
  if (rp_scripts.currency_pos === 'before') {
    jQuery('#rpressModal .cart-item-price')
      .html(rp_scripts.currency_sign + total_price_v);
  }
  else {
    jQuery('#rpressModal .cart-item-price')
      .html(total_price_v + rp_scripts.currency_sign);
  }

  jQuery('#rpressModal .cart-item-price')
    .attr('data-current', single_price.toFixed(2));
}
function isStoreOpen() {
  if (typeof rp_scripts === 'undefined') return true; // fail-safe

  if (rp_scripts.open_hours === '' || rp_scripts.close_hours === '') return false; // no hours set, assume close

  var open = rp_scripts.open_hours;   // e.g. "09:00am" or "12:00am"
  var close = rp_scripts.close_hours; // e.g. "11:00pm"

  // Convert to 24hr Date objects for today
  function parseTime(t) {
    var d = new Date();
    var time = t.toLowerCase().replace(/\s/g, '');
    var match = time.match(/(\d+):(\d+)(am|pm)/);

    if (!match) return d; // fallback to now

    var hours = parseInt(match[1], 10);
    var minutes = parseInt(match[2], 10);
    var ampm = match[3];

    if (ampm === "pm" && hours < 12) hours += 12;
    if (ampm === "am" && hours === 12) hours = 0;

    d.setHours(hours);
    d.setMinutes(minutes);
    d.setSeconds(0);
    return d;
  }

  var now = new Date();
  var openTime = parseTime(open);
  var closeTime = parseTime(close);

  // Handle case where close time is after midnight (e.g. open 6pm close 2am)
  if (closeTime <= openTime) {
    if (now < openTime && now > closeTime) {
      return false;
    }
    return true;
  }

  return (now >= openTime && now <= closeTime);
}
/* RestroPress Frontend Functions */
jQuery(function ($) {
  // Show order details on popup
  $(document)
    .on('click', '.rpress-view-order-btn', function (e) {
      e.preventDefault();
      var self = $(this);
      var action = 'rpress_show_order_details';
      var order_id = self.attr('data-order-id');
      var data = {
        action: action,
        order_id: order_id,
        security: rp_scripts.order_details_nonce
      };
      $('#rpressModal')
        .addClass('show-order-details');
      $.ajax({
        type: "POST",
        data: data,
        dataType: "json",
        url: rp_scripts.ajaxurl,
        beforeSend: (jqXHR, object) => {
          self.addClass('rp-loading');
          self.find('.rp-ajax-toggle-text')
            .addClass('rp-text-visibility');
        },
        complete: (jqXHR, object) => {
          self.removeClass('rp-loading');
          self.find('.rp-ajax-toggle-text')
            .removeClass('rp-text-visibility');
        },
        success: function (response) {
          $('#rpressModal .modal__container')
            .html(response.data.html);
          MicroModal.show('rpressModal', {
            disableScroll: true
          });
        }
      })
    });
  // Sticky category menu on mobile
  $(window)
    .resize(function () {
      if ($(window)
        .width() > 769) {
        $('.sticky-sidebar.cat-lists')
          .removeClass('rp-mobile-cat');
      } else {
        $('.sticky-sidebar.cat-lists')
          .addClass('rp-mobile-cat');
      }
    })
    .resize();
  // Hide Mneu on Click Float Cart
  $(document)
    .on('click', '.rpress-category-item', function () {
      $('.rp-mb-cat-ft-btn')
        .click();
    });
  // Hide Mneu on Click Items
  $(document)
    .on('click', '.rpress-mobile-cart-icons', function () {
      $('.rp-cat-overlay')
        .click();
    });
  // RP Hide overlay
  $(document)
    .on('click', '.rp-cat-overlay', function () {
      $('.rp-mb-cat-ft-btn')
        .click();
    });
  // Toggel mobile category menu
  $('.rp-mb-cat-ft-btn')
    .on('click', function () {
      $('.rp-mb-cat-ft-btn')
        .toggleClass('rp-close-menu');
      $('.sticky-sidebar.cat-lists')
        .toggleClass('rp-hide');
      $('body')
        .toggleClass('rp-cat-no-scroll');
      if ($('.sticky-sidebar.cat-lists')
        .hasClass('rp-hide'))
        $('body')
          .append('<div class="rp-cat-overlay"></div>');
      else
        $('.rp-cat-overlay')
          .remove();
    });
  $('.rp-mb-cat-ft-btn')
    .click(function () {
      $('.rp-mobile-cat')
        .toggle('fast');
      if ($(this)
        .hasClass('rp-close-menu')) {
        $('.rp-mb-cat-txt')
          .html(`<i class="fa fa-cutlery" aria-hidden="true"></i> ${rpress_scripts.close}`);
      } else {
        $('.rp-mb-cat-txt')
          .html(`<i class="fa fa-cutlery" aria-hidden="true"></i> ${rpress_scripts.menu}`);
      }
    });
  //Remove loading from modal
  $('#rpressModal')
    .removeClass('loading');
  //Remove service options from modal
  $('#rpressModal')
    .removeClass('show-service-options');
  $('#rpressModal')
    .removeClass('minimum-order-notice');
  $('#rpressModal')
    .on('hidden.bs.modal', function () {
      $('#rpressModal')
        .removeClass('show-service-options');
      $('#rpressModal')
        .removeClass('minimum-order-notice');
    });
  var ServiceType = rp_scripts.service_options;
  if (ServiceType == 'delivery_and_pickup') {
    ServiceType = 'delivery';
  }
  // Add to Cart
  $('.rpress-add-to-cart')
    .click(function (e) {
      e.preventDefault();
      if (!isStoreOpen()) {
        tata.error('Error', rp_scripts.closed_message, { position: "tr" });
        return;
      }
      var delivery_zip = rp_getCookie('delivery_zip');
      var delivery_location = rp_getCookie('delivery_location');
      var delivery_address = rp_getCookie('delivery_address');
      var serviceDate = rp_getCookie('delivery_date');


      if (!delivery_zip) {
        var $rp_delivery_zone = $('input#rp_delivery_zone'); // adjust selector if needed
        // Check if the delivery zone input exists and has a value
        if ($rp_delivery_zone.length > 0 && !$rp_delivery_zone.val().trim()) {
          // Show error
          tata.error('Error', 'Please enter ZIP code', { position: "tr" });

          // Trigger Edit button click after 1 second
          setTimeout(function () {
            $('#editDateTime').trigger('click');
          }, 500);

          return; // stop further execution
        }
      }

      if (!delivery_location) {
        var $rp_delivery_location = $('input#rp_delivery_location'); // adjust selector if needed
        if (!delivery_address) {
          // Check if the delivery zone input exists and has a value
          if ($rp_delivery_location.length > 0 && !$rp_delivery_location.val().trim()) {
            // Show error
            tata.error('Error', 'Please enter location', { position: "tr" });

            // Trigger Edit button click after 1 second
            setTimeout(function () {
              $('#editDateTime').trigger('click');
            }, 500);

            return; // stop further execution
          }
        }
      }

      var serviceType = rp_getCookie('service_type');
      if (!serviceType) {
        serviceType = rp_scripts.default_service || 'delivery';
        rp_setCookie('service_type', serviceType, rp_scripts.expire_cookie_time);
      }
      var serviceTime = rp_getCookie('service_time');
      if (!serviceTime.trim()) {
        var $sel = $('#rpress-delivery-hours');
        if (!$sel.length) return;

        // find option marked selected in HTML
        var $opt = $sel.find('option').first();
        if ($opt.length) {
          var val = $opt.val();
          rp_setCookie('service_time', val, rp_scripts.expire_cookie_time);
        }
      } else {
        var $sel = $('#rpress-delivery-hours');
        if (!$sel.length) return;
        // cookie exists, check against options
        if ($sel.find('option[value="' + serviceTime + '"]').length) {
          // valid option exists → select it
          // $sel.val(serviceTime).trigger('change');
        } else {
          // invalid cookie value → select first option
          var $firstOpt = $sel.find('option').first();
          if ($firstOpt.length) {
            // $sel.val($firstOpt.val()).trigger('change');
            rp_setCookie('service_time', $firstOpt.val(), rp_scripts.expire_cookie_time);
          }
        }
      }
      var rp_get_delivery_data = rp_get_storage_data();
      $('#rpressModal')
        .removeClass('rpress-delivery-options rpress-food-options checkout-error');
      $('#rpressModal .qty')
        .val('1');
      $('#rpressModal')
        .find('.cart-action-text')
        .html(rp_scripts.add_to_cart);
      if (!rp_get_delivery_data) {
        var action = 'rpress_show_delivery_options';
        var security = rp_scripts.service_type_nonce;
        $('#rpressModal')
          .addClass('show-service-options');
      } else {
        $('#rpressModal')
          .removeClass('show-service-options');
        var action = 'rpress_show_products';
        var security = rp_scripts.show_products_nonce;
      }
      var _self = $(this);
      var fooditem_id = _self.attr('data-fooditem-id');
      var foodItemName = _self.attr('data-title');
      var price = _self.attr('data-price');
      var variable_price = _self.attr('data-variable-price');
      var data = {
        action: action,
        fooditem_id: fooditem_id,
        security: security,
        service_type: serviceType,
        selected_date: serviceDate
      };
      $.ajax({
        type: "POST",
        data: data,
        dataType: "json",
        url: rp_scripts.ajaxurl,
        beforeSend: (jqXHR, status) => {
          _self.addClass('rp-loading');
          _self.find('.add-icon').addClass('icon-loading-toggle');
          _self.find('.rp-ajax-toggle-text')
            .addClass('rp-text-visibility');
        },
        xhrFields: {
          withCredentials: true
        },
        complete: (jqXHR, object) => {
          _self.removeClass('rp-loading');
          _self.find('.add-icon').removeClass('icon-loading-toggle');
          _self.find('.rp-ajax-toggle-text')
            .removeClass('rp-text-visibility');

        },
        success: function (response) {
          if (response?.success === false && response?.data?.status === 'error') {
            tata.error('Error', response?.data?.error_msg, { position: "tr" });
            return;
          }
          MicroModal.show('rpressModal', {
            disableScroll: true
          });
          $('#rpressModal').removeClass('loading');

          $('#rpressModal .modal-title')
            .html(response.data.html_title);
          $('#rpressModal .modal-body')
            .html(response.data.html);
          $('#rpressModal .cart-item-price')
            .html(response.data.price);
          $('#rpressModal .item-description')
            .html(response.data.description);

          $('#rpressModal .item-image').attr('src', '');
          if (response.data.image_url) {
            $('#rpressModal .item-image').attr('src', response.data.image_url);
          }
          $('#rpressModal .cart-item-price')
            .attr('data-price', response.data.price_raw);
          // Trigger event so themes can refresh other areas.
          $(document.body)
            .trigger('opened_service_options', [response.data]);
          $('#rpressModal')
            .find('.submit-fooditem-button')
            .attr('data-cart-action', 'add-cart');
          $('#rpressModal')
            .find('.cart-action-text')
            .html(rp_scripts.add_to_cart);
          if (fooditem_id !== '' && price !== '') {
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .attr('data-item-id', fooditem_id);
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .attr('data-title', foodItemName);
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .attr('data-item-price', price);
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .attr('data-item-qty', 1);
          }
          update_modal_live_price('fooditem-details');
          rp_checked_default_subaddon();
        }
      });
    });
  // Update Cart
  $('.rpress-sidebar-cart')
    .on('click', 'a.rpress-edit-from-cart', function (e) {
      e.preventDefault();
      var _self = $(this);
      _self.parents('.rpress-cart-item')
        .addClass('edited');
      var CartItemId = _self.attr('data-remove-item');
      var FoodItemId = _self.attr('data-item-id');
      var FoodItemName = _self.attr('data-item-name');
      var FoodQuantity = _self.parents('.rpress-cart-item')
        .find('.rpress-cart-item-qty')
        .text();
      var action = 'rpress_edit_cart_fooditem';
      var security = rp_scripts.edit_cart_fooditem_nonce;
      var data = {
        action: action,
        cartitem_id: CartItemId,
        fooditem_id: FoodItemId,
        fooditem_name: FoodItemName,
        security: security,
      };
      if (CartItemId !== '') {
        $.ajax({
          type: "POST",
          data: data,
          dataType: "json",
          url: rp_scripts.ajaxurl,
          beforeSend: (jqXHR, status) => {
            _self.addClass('rp-loading');
            _self.find('.rp-ajax-toggle-text')
              .addClass('rp-text-visibility');
          },
          complete: (jqXHR, object) => {
            _self.removeClass('rp-loading');
            _self.find('.rp-ajax-toggle-text')
              .removeClass('rp-text-visibility');
          },
          xhrFields: {
            withCredentials: true
          },
          success: function (response) {
            MicroModal.show('rpressModal', {
              disableScroll: true
            });
            $('#rpressModal')
              .removeClass('checkout-error');
            $('#rpressModal')
              .removeClass('show-service-options');
            $('#rpressModal')
              .removeClass('loading');
            $('#rpressModal .modal-title')
              .html(response.data.html_title);
            $('#rpressModal')
              .find(".qty")
              .val(FoodQuantity);
            $('#rpressModal .item-description').html(response.data.description);
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .attr('data-item-id', FoodItemId);
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .attr('data-title', FoodItemName);
            $('#rpressModal .item-image').attr('src', '');
            if (response.data.image_url) {
              $('#rpressModal .item-image').attr('src', response.data.image_url);
            }
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .attr('data-cart-key', CartItemId);
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .attr('data-cart-action', 'update-cart');
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .find('.cart-action-text')
              .html(rp_scripts.update_cart);
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .find('.cart-item-price')
              .html(response.data.price);
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .find('.cart-item-price')
              .attr('data-price', response.data.price_raw);
            $('#rpressModal')
              .find('.submit-fooditem-button')
              .attr('data-item-qty', FoodQuantity);
            $('#rpressModal .modal-body')
              .html(response.data.html);

            // ✅ Auto-check addons that were already selected
            if (response.data.addon_items) {
              $.each(response.data.addon_items, function (i, addon) {
                var addonId = addon.addon_id;
                var qty = addon.quantity;

                // Find all checkboxes that belong to this addon_id (multiple clones may exist)
                var $checkboxes = $('#rpressModal .modal-body input[type="checkbox"][value^="' + addonId + '|"]');

                if ($checkboxes.length) {
                  $checkboxes.each(function (index, checkbox) {
                    var $cb = $(checkbox);
                    $cb.prop('checked', true);

                    // Go to its quantity input inside .food-item-list
                    var $qtyInput = $cb.closest('.food-item-list').find('.addon_qty');

                    if ($qtyInput.length) {
                      $qtyInput.val(qty);   // set correct quantity

                      // 🔥 Optional: also mark its parent .food-item-list as "active" if your UI needs it
                      $cb.closest('.food-item-list').addClass('active');
                    }
                  });
                }
              });
            }
            update_modal_live_price('fooditem-update-details');
          }
        });
      }
    });
  // Add to Cart / Update Cart Button From Popup
  $(document).on('click', '.submit-fooditem-button', function (e) {
    e.preventDefault();
    var self = $(this);
    var cartAction = self.attr('data-cart-action');
    var text = self.find('span.cart-action-text').text();
    var validation = '';
    // Checking the Required & Max addon settings for Addons
    if (jQuery('.addons-wrapper').length > 0) {
      jQuery('.addons-wrapper').each(function (index, el) {
        var _self = jQuery(this);
        var addon = _self.attr('data-id');
        var is_required = _self.children('input.addon_is_required').val();
        var max_addons = _self.children('input.addon_max_limit').val();
        var min_addons = _self.children('input.addon_min_limit').val();
        var checked = _self.find('.food-item-list.active input:checked').length;

        var customValidation = {
          isValid: true,
          message: '',
          element: _self,
          minAddons: min_addons,
          maxAddons: max_addons,
          checkedAddons: checked
        };

        // Trigger custom validation hook
        $(document).trigger('rpress_addon_validation', customValidation);
        _self.find('.rp-addon-error')
          .removeClass('rp-addon-error');
        if (is_required == 'yes' && checked == 0) {
          _self.find('.rp-addon-required').addClass('rp-addon-error');
          validation = 1;
        } else if (max_addons != 0 && checked > max_addons) {
          _self.find('.rp-addon-required').addClass('rp-addon-error');
          _self.find('.rp-max-addon').addClass('rp-addon-error');
          _self.find(".rpress-addon-category").addClass('rp-addon-error');

          validation = 1;
        } else if (min_addons != 0 && checked < min_addons) {
          _self.find('.rp-addon-required').addClass('rp-addon-error');
          _self.find('.rp-min-addon').addClass('rp-addon-error');
          _self.find(".rpress-addon-category").addClass('rp-addon-error');

          validation = 1;
        } else if (!customValidation.isValid) {
          _self.find('.rp-addon-required').addClass('rp-addon-error');
          _self.find('.rp-min-addon').addClass('rp-addon-error');
          _self.find(".rpress-addon-category").addClass('rp-addon-error');
          validation = 1;
        }

        if (validation === 1) {
          var $error = $('#rpressModal .rp-addon-error').first();
          if ($error.length) {
            $error[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }

        if (validation != '') {
          self.removeClass('disable_click');
          self.find('.cart-action-text')
            .text(text);
          return false;
        }
      });
    }
    if (cartAction == 'add-cart' && validation == '') {
      self.addClass('disable_click');
      var this_form = self.parents('.modal')
        .find('form#fooditem-details .food-item-list.active input');
      var itemId = self.attr('data-item-id');
      var itemName = self.attr('data-title');
      var itemQty = self.attr('data-item-qty');
      var FormData = this_form.serializeArray();
      var SpecialInstruction = self.parents('.modal')
        .find('textarea.special-instructions')
        .val();
      var action = 'rpress_add_to_cart';
      var data = {
        action: action,
        fooditem_id: itemId,
        fooditem_qty: itemQty,
        special_instruction: SpecialInstruction,
        post_data: FormData,
        security: rp_scripts.add_to_cart_nonce
      };
      if (itemId !== '') {
        $.ajax({
          type: "POST",
          data: data,
          dataType: "json",
          url: rp_scripts.ajaxurl,
          xhrFields: {
            withCredentials: true
          },
          beforeSend: (jqXHR, object) => {
            self.addClass('rp-loading');
            self.find('.rp-ajax-toggle-text')
              .addClass('rp-text-visibility')
          },
          complete: (jqXHR, object) => {
            self.removeClass('rp-loading');
            self.find('.rp-ajax-toggle-text')
              .removeClass('rp-text-visibility')
          },
          success: function (response) {
            if (response) {
              $('.rpress-mobile-cart-icons')
                .css({
                  display: 'flex'
                });
              self.removeClass('disable_click');
              self.find('.cart-action-text')
                .text(text);
              var serviceType = rp_getCookie('service_type');
              var serviceTime = rp_getCookie('service_time');
              var serviceTimeText = rp_getCookie('service_time_text');
              var serviceDate = rp_getCookie('delivery_date');
              $('ul.rpress-cart')
                .find('li.cart_item.empty')
                .remove();
              $('ul.rpress-cart')
                .find('li.cart_item.rpress_subtotal')
                .remove();
              $('ul.rpress-cart')
                .find('li.cart_item.cart-sub-total')
                .remove();
              $('ul.rpress-cart')
                .find('li.cart_item.rpress_cart_tax')
                .remove();
              $('ul.rpress-cart')
                .find('li.cart_item.rpress-cart-meta.rpress-delivery-fee')
                .remove();
              $('ul.rpress-cart')
                .find('li.cart_item.rpress-cart-meta.rpress_subtotal')
                .remove();
              var $target = $('ul.rpress-cart div.rpress-cart-total-wrap');

              if ($target.length) {
                $(response.cart_item).insertBefore($target);
              } else {
                $(response.cart_item).insertBefore('ul.rpress-cart li.cart_item.rpress_total');
              }

              if ($('.rpress-cart')
                .find('.rpress-cart-meta.rpress_subtotal')
                .is(':first-child')) {
                $(this)
                  .hide();
              }
              $('.rpress-cart-quantity')
                .show();
              $('.rp-mb-price')
                .text(response.total);
              $('.rp-mb-quantity')
                .text(response.cart_quantity);
              $('.cart_item.rpress-cart-meta.rpress_total')
                .find('.cart-total')
                .text(response.total);
              $('.cart_item.rpress-cart-meta.rpress_subtotal')
                .find('.subtotal')
                .text(response.total);
              $('.cart_item.rpress-cart-meta.rpress_total')
                .css('display', 'block');
              $('.cart_item.rpress-cart-meta.rpress_subtotal')
                .css('display', 'block');
              $('.cart_item.rpress_checkout')
                .addClass(rp_scripts.button_color);
              $('.cart_item.rpress_checkout')
                .css('display', 'block');
              if (serviceType !== undefined) {
                serviceLabel = window.localStorage.getItem('serviceLabel');
                var orderInfo = '<span class="delMethod">' + serviceLabel + ', ' + serviceDate + '</span>';
                if (serviceTime !== undefined) {
                  // Check if the string contains 'ASAP'
                  if (serviceTime.includes('ASAP')) {
                    // Remove 'ASAP' from the string
                    serviceTimeText = serviceTime.replace('ASAP', '');
                    serviceTimeText = rp_scripts.asap_txt + ' ' + serviceTimeText;
                  }
                  orderInfo += '<span class="delTime">, ' + serviceTimeText + '</span>';
                }
                $('.delivery-items-options')
                  .find('.delivery-opts')
                  .html(orderInfo);
                if ($('.delivery-wrap .delivery-change')
                  .length == 0) {
                  $("<a href='#' class='delivery-change'>" + rp_scripts.change_txt + "</a>")
                    .insertAfter(".delivery-opts");
                }
              }
              $('.delivery-items-options')
                .css('display', 'block');
              var subTotal = '<li class="cart_item rpress-cart-meta rpress_subtotal">' + rp_scripts.total_text + '<span class="cart-subtotal">' + response.subtotal + '</span></li>';
              if (response.subtotal) {
                var cartLastChild = $('ul.rpress-cart>li.rpress-cart-item:last');
                $(subTotal).insertAfter(cartLastChild);
                // $('ul.rpress-cart>div.rpress-cart-total-wrap').prepend(subTotal);
              }
              var newCartKey = $('ul.rpress-cart li.rpress-cart-item').last().attr('data-cart-key');
              if (newCartKey) {
                let carttotal = parseInt(newCartKey) + 1;
                $('.cart_item.rpress-cart-meta.rpress_total')
                  .find('.rpress-cart-quantity')
                  .text(carttotal);
              } else {
                let carttotal = 1;
                $('.cart_item.rpress-cart-meta.rpress_total')
                  .find('.rpress-cart-quantity')
                  .text(carttotal);
              }
              if (response.taxes) {
                var taxHtml = '<li class="cart_item rpress-cart-meta rpress_cart_tax">' + rp_scripts.estimated_tax + '<span class="cart-tax">' + response.taxes + '</span></li>';
                $(taxHtml)
                  .insertBefore('ul.rpress-cart li.cart_item.rpress_total');
              }
              if (response.taxes === undefined) {
                $('ul.rpress-cart')
                  .find('.cart_item.rpress-cart-meta.rpress_subtotal')
                  .remove();
                var cartLastChild = $('ul.rpress-cart>li.rpress-cart-item:last');
                $(subTotal).insertAfter(cartLastChild);
                // $('ul.rpress-cart>div.rpress-cart-total-wrap').prepend(subTotal);
              }
              if ($('div.rpress.item-order').length == 0) {
                var orderitems = $('<div class="rpress item-order"><h4>Ordered menu</h4><span>1 items</span></div>');
                $('div.rpress-sidebar-main-wrap div.rpress-sidebar-cart-wrap').removeClass('empty-cart');
                $('div.rpress-sidebar-main-wrap div.rpress-sidebar-cart-wrap').prepend(orderitems);
              } else {
                // $('div.rpress.item-order span').html(rp_scripts.cart_quantity + " " + rp_scripts.items);
              }
              $(document.body)
                .trigger('rpress_added_to_cart', [response]);
              $('ul.rpress-cart')
                .find('.cart-total')
                .html(response.total);
              $('ul.rpress-cart')
                .find('.cart-subtotal')
                .html(response.subtotal);
              if ($('li.rpress-cart-item')
                .length > 0) {
                $('a.rpress-clear-cart')
                  .show();
              } else {
                $('a.rpress-clear-cart')
                  .hide();
              }
              // Target subtotal and total li elements
              var $subtotal = $('.rpress-cart .rpress_subtotal');
              var $tax = $('ul.rpress-cart li.rpress_cart_tax');
              var $total = $('.rpress-cart .rpress_total');

              // Remove any previously added wrapper
              $('div.rpress-sidebar-main-wrap div.rpress-sidebar-cart-wrap').find('.rpress-cart-total-wrap').children().unwrap();

              // Wrap them together only if they exist
              if ($subtotal.length && $total.length) {
                $subtotal.add($tax).add($total).wrapAll('<div class="rpress-cart-total-wrap"></div>');
              }
              $(document.body)
                .trigger('rpress_added_to_cart', [response]);
              MicroModal.close('rpressModal');
              tata.success(window.rp_scripts.success, self.attr('data-title') + window.rp_scripts.added_to_cart, {
                position: "tr"
              })
            }
          }
        })
      }
    }
    if (cartAction == 'update-cart' && validation == '') {

      self.addClass('disable_click');
      var this_form = self.parents('.modal')
        .find('form#fooditem-update-details .food-item-list.active input');
      var itemId = self.attr('data-item-id');
      var itemPrice = self.attr('data-item-price');
      var cartKey = self.attr('data-cart-key');
      var itemQty = self.attr('data-item-qty');
      var FormData = this_form.serializeArray();
      var SpecialInstruction = self.parents('.modal')
        .find('textarea.special-instructions')
        .val();
      var action = 'rpress_update_cart_items';
      var data = {
        action: action,
        fooditem_id: itemId,
        fooditem_qty: itemQty,
        fooditem_cartkey: cartKey,
        special_instruction: SpecialInstruction,
        post_data: FormData,
        security: rp_scripts.update_cart_item_nonce
      };

      if (itemId !== '') {
        $.ajax({
          type: "POST",
          data: data,
          dataType: "json",
          url: rp_scripts.ajaxurl,
          xhrFields: {
            withCredentials: true
          },
          success: function (response) {
            self.removeClass('disable_click');
            self.find('.cart-action-text')
              .text(text);
            if (response) {
              html = response.cart_item;
              $('ul.rpress-cart')
                .find('li.cart_item.empty')
                .remove();
              $('.rpress-cart >li.rpress-cart-item')
                .each(function (index, item) {
                  $(this)
                    .find("[data-cart-item]")
                    .attr('data-cart-item', index);
                  $(this)
                    .attr('data-cart-key', index);
                  $(this)
                    .attr('data-remove-item', index);
                });
              $('ul.rpress-cart')
                .find('li.edited')
                .replaceWith(function () {
                  let obj = $(html);
                  obj.attr('data-cart-key', response.cart_key);
                  obj.find("a.rpress-edit-from-cart")
                    .attr("data-cart-item", response.cart_key);
                  obj.find("a.rpress-edit-from-cart")
                    .attr("data-remove-item", response.cart_key);
                  obj.find("a.rpress_remove_from_cart")
                    .attr("data-cart-item", response.cart_key);
                  obj.find("a.rpress_remove_from_cart")
                    .attr("data-remove-item", response.cart_key);
                  return obj;
                });
              $('ul.rpress-cart')
                .find('.cart-total')
                .html(response.total);
              $('ul.rpress-cart')
                .find('.cart-subtotal')
                .html(response.subtotal);
              $('ul.rpress-cart')
                .find('.cart-tax')
                .html(response.tax);
              $(document.body)
                .trigger('rpress_items_updated', [response]);
              MicroModal.close('rpressModal');
            }
          }
        });
      }
    }
  });
  // Add Service Date and Time
  $('body')
    .on('click', '.rpress-delivery-opt-update', function (e) {
      e.preventDefault();
      var _self = $(this);
      var foodItemId = _self.attr('data-food-id');
      if ($('.rpress-tabs-wrapper')
        .find('.nav-item.active a')
        .length > 0) {
        var serviceType = $('.rpress-tabs-wrapper')
          .find('.nav-item.active a')
          .attr('data-service-type');
        var serviceLabel = $('.rpress-tabs-wrapper')
          .find('.nav-item.active a')
          .text()
          .trim();
        //Store the service label for later use
        window.localStorage.setItem('serviceLabel', serviceLabel);
      }
      var serviceTime = _self.parents('.rpress-tabs-wrapper')
        .find('.delivery-settings-wrapper.active .rpress-hrs')
        .val();
      if (!serviceTime && rp_getCookie('service_time')) {
        serviceTime = rp_getCookie('service_time');
      }
      var serviceTimeText = _self.parents('.rpress-tabs-wrapper')
        .find('.delivery-settings-wrapper.active .rpress-hrs option:selected')
        .text();
      var serviceDate = _self.parents('.rpress-tabs-wrapper')
        .find('.delivery-settings-wrapper.active .rpress_get_delivery_dates')
        .val();
      var sDate = serviceDate === undefined ? rpress_scripts.current_date : serviceDate;
      var action = 'rpress_check_service_slot';
      var data = {
        action: action,
        serviceType: serviceType,
        serviceTime: serviceTime,
        service_date: sDate,
      };
      $.ajax({
        type: "POST",
        data: data,
        dataType: "json",
        url: rpress_scripts.ajaxurl,
        xhrFields: {
          withCredentials: true
        },
        beforeSend: (jqXHR, status) => {
          _self.addClass('rp-loading');
          _self.find('.rp-ajax-toggle-text')
            .addClass('rp-text-visibility');
        },
        complete: (jqXHR, oject) => {
          _self.removeClass('rp-loading');
          _self.find('.rp-ajax-toggle-text')
            .removeClass('rp-text-visibility');
        },
        success: function (response) {
          _self.removeClass('rp-loading');
          _self.find('.rp-ajax-toggle-text')
            .removeClass('rp-text-visibility');
          if (response.status == 'error') {
            _self.text(rpress_scripts.update);
            tata.error(rp_scripts.error, response.msg);
            return false;
          } else {
            rp_setCookie('service_type', serviceType, rp_scripts.expire_cookie_time);
            if (serviceDate === undefined) {
              rp_setCookie('service_date', rpress_scripts.current_date, rp_scripts.expire_cookie_time);
              rp_setCookie('delivery_date', rpress_scripts.display_date, rp_scripts.expire_cookie_time);
            } else {
              var delivery_date = $('.delivery-settings-wrapper.active .rpress_get_delivery_dates option:selected')
                .text();
              rp_setCookie('service_date', serviceDate, rp_scripts.expire_cookie_time);
              rp_setCookie('delivery_date', delivery_date, rp_scripts.expire_cookie_time);
            }
            if (serviceTime === undefined) {
              rp_setCookie('service_time', '', rp_scripts.expire_cookie_time);
            } else {
              rp_setCookie('service_time', serviceTime, rp_scripts.expire_cookie_time);
              rp_setCookie('service_time_text', serviceTimeText, rp_scripts.expire_cookie_time);
            }
            $('#rpressModal')
              .removeClass('show-service-options');
            if (foodItemId) {
              $('#rpressModal')
                .addClass('loading');
              $('#rpress_fooditem_' + foodItemId)
                .find('.rpress-add-to-cart')
                .trigger('click');
              MicroModal.close('rpressModal');
            } else {
              if (jQuery('#rpressModal').length) {
                MicroModal.close('rpressModal');
              }
              if (typeof serviceType !== 'undefined' && typeof serviceTime !== 'undefined') {
                $('.delivery-wrap .delivery-opts')
                  .html('<span class="delMethod">' + serviceLabel + ',</span> <span class="delTime"> ' + Cookies.get('delivery_date') + ', ' + serviceTimeText + '</span>');
              } else if (typeof serviceTime == 'undefined') {
                $('.delivery-items-options')
                  .find('.delivery-opts')
                  .html('<span class="delMethod">' + serviceLabel + ',</span> <span class="delTime"> ' + Cookies.get('delivery_date') + '</span>');
              }
            }

            //Trigger checked slot event so that it can be used by theme/plugins
            $(document.body)
              .trigger('rpress_checked_slots', [response]);
            //If it's checkout page then refresh the page to reflect the updated changes.
            // if (rpress_scripts.is_checkout == '1')
            window.location.reload();
          }
        }
      });
    });
  $('body')
    .on('click', '.rpress-editaddress-submit-btn', function (e) {
      e.preventDefault();
      var _self = $(this);
      var foodItemId = _self.attr('data-food-id');
      if ($('.rpress-tabs-wrapper')
        .find('.nav-item.active a')
        .length > 0) {
        var serviceType = $('.rpress-tabs-wrapper')
          .find('.nav-item.active a')
          .attr('data-service-type');
        var serviceLabel = $('.rpress-tabs-wrapper')
          .find('.nav-item.active a')
          .text()
          .trim();
        //Store the service label for later use
        window.localStorage.setItem('serviceLabel', serviceLabel);
      }
      var serviceTime = _self.parents('.rpress-tabs-wrapper')
        .find('.delivery-settings-wrapper.active .rpress-hrs')
        .val();
      var serviceTimeText = _self.parents('.rpress-tabs-wrapper')
        .find('.delivery-settings-wrapper.active .rpress-hrs option:selected')
        .text();
      var serviceDate = _self.parents('.rpress-tabs-wrapper')
        .find('.delivery-settings-wrapper.active .rpress_get_delivery_dates')
        .val();
      // if (serviceTime === undefined && (rpress_scripts.pickup_time_enabled == 1 && serviceType == 'pickup' || rpress_scripts.delivery_time_enabled == 1 && serviceType == 'delivery')) {
      //   tata.error(rp_scripts.error, select_time_error + serviceLabel);
      //   return false;
      // }
      var sDate = serviceDate === undefined ? rpress_scripts.current_date : serviceDate;
      var action = 'rpress_check_service_slot';
      var data = {
        action: action,
        serviceType: serviceType,
        serviceTime: serviceTime,
        service_date: sDate,
      };
      $.ajax({
        type: "POST",
        data: data,
        dataType: "json",
        url: rpress_scripts.ajaxurl,
        xhrFields: {
          withCredentials: true
        },
        beforeSend: (jqXHR, status) => {
          _self.addClass('rp-loading');
          _self.find('.rp-ajax-toggle-text')
            .addClass('rp-text-visibility');
        },
        complete: (jqXHR, oject) => {
          _self.removeClass('rp-loading');
          _self.find('.rp-ajax-toggle-text')
            .removeClass('rp-text-visibility');
        },
        success: function (response) {
          _self.removeClass('rp-loading');
          _self.find('.rp-ajax-toggle-text')
            .removeClass('rp-text-visibility');
          if (response.status == 'error') {
            _self.text(rpress_scripts.update);
            tata.error(rp_scripts.error, response.msg);
            return false;
          } else {
            $('#rpressModal')
              .removeClass('show-service-options');
            if (foodItemId) {
              $('#rpressModal')
                .addClass('loading');
              $('#rpress_fooditem_' + foodItemId)
                .find('.rpress-add-to-cart')
                .trigger('click');
              MicroModal.close('rpressModal');
            } else {
              MicroModal.close('rpressModal');
              if (typeof serviceType !== 'undefined' && typeof serviceTime !== 'undefined') {
                $('.delivery-wrap .delivery-opts')
                  .html('<span class="delMethod">' + serviceLabel + ',</span> <span class="delTime"> ' + Cookies.get('delivery_date') + ', ' + serviceTimeText + '</span>');
              } else if (typeof serviceTime == 'undefined') {
                $('.delivery-items-options')
                  .find('.delivery-opts')
                  .html('<span class="delMethod">' + serviceLabel + ',</span> <span class="delTime"> ' + Cookies.get('delivery_date') + '</span>');
              }
            }

            //Trigger checked slot event so that it can be used by theme/plugins
            $(document.body)
              .trigger('rpress_checked_slots', [response]);
            //If it's checkout page then refresh the page to reflect the updated changes.
            if (rpress_scripts.is_checkout == '1')
              window.location.reload();
          }

          // Close the modal
          MicroModal.close('rpressDateTime');
        }
      });
    });
  // Open modal when button is clicked
  $(document).on('click', '#editDateTime', function (e) {
    e.preventDefault();
    var self = $(this);

    var serviceType = rp_getCookie('service_type');
    if (!serviceType) {
      serviceType = rp_scripts.default_service || 'delivery';
      rp_setCookie('service_type', serviceType, rp_scripts.expire_cookie_time);
    }
    var serviceTime = rp_getCookie('service_time');
    if (!serviceTime) {
      var $sel = $('#rpress-delivery-hours');
      if (!$sel.length) return;

      // find option marked selected in HTML
      var $opt = $sel.find('option').first();
      if ($opt.length) {
        var val = $opt.val();
        $sel.val(val).trigger('change');
      }
    } else {
      var $sel = $('#rpress-delivery-hours');
      if (!$sel.length) return;
      // cookie exists, check against options
      if ($sel.find('option[value="' + serviceTime + '"]').length) {
        // valid option exists → select it
        $sel.val(serviceTime).trigger('change');
      } else {
        // invalid cookie value → select first option
        var $firstOpt = $sel.find('option').first();
        if ($firstOpt.length) {
          $sel.val($firstOpt.val()).trigger('change');
        }
      }
    }

    $('.rpress-mobile-cart-icons').hide();

    MicroModal.show('rpressDateTime', {
      disableScroll: true
    });

  });

  $('body').on('click', '.rpress-editaddress-submit-btn', function (e) {
    e.preventDefault();

    var $modal = $('#rpressDateTime-content');

    // Get selected time
    var deliveryTime = $modal.find('#rpress-delivery-hours').val();

    var deliveryTimeText = $modal.find('.rpress-hrs option:selected').text();

    // Get selected date
    var deliveryDate = $modal.find('.rpress_get_delivery_dates').val();

    // Update cookies
    rp_setCookie('service_time', deliveryTime, rp_scripts.expire_cookie_time);
    rp_setCookie('service_time_text', deliveryTimeText, rp_scripts.expire_cookie_time);
    if (deliveryDate) {
      rp_setCookie('service_date', deliveryDate, rp_scripts.expire_cookie_time);
      rp_setCookie('delivery_date', deliveryDate, rp_scripts.expire_cookie_time);
    }

    // Get the selected date and time
    var selectedDate = $('.rpress_get_delivery_dates').val();
    var selectedTimeText = $('.rpress-hrs option:selected').text();

    // Format the date to "Month Day"
    var formattedDate = '';
    if (selectedDate) {
      var dateObj = new Date(selectedDate);
      var options = { month: 'long', day: 'numeric' };
      formattedDate = dateObj.toLocaleDateString('en-US', options);
    }

    // Update date span
    if (formattedDate) {
      $('#deliveryDate').text(formattedDate);
    }

    // Update time span
    if (selectedTimeText) {
      $('#deliveryTime').text(selectedTimeText);
    }

    $('.rpress-mobile-cart-icons').show();
  });
  // Update Service Date and Time
  $(document).on('click', '.delivery-change', function (e) {
    e.preventDefault();
    var self = $(this);
    var action = 'rpress_show_delivery_options';
    var ServiceType = rp_getCookie('service_type');
    var ServiceTime = rp_getCookie('service_time');
    var text = self.text();
    var data = {
      action: action,
      security: rp_scripts.service_type_nonce
    }
    $('#rpressModal').addClass('show-service-options');
    $.ajax({
      type: "POST",
      data: data,
      dataType: "json",
      url: rp_scripts.ajaxurl,
      beforeSend: (jqXHR, obj) => {
        self.addClass('rp-loading');
        self.find('.rp-ajax-toggle-text')
          .addClass('rp-text-visibility')
      },
      complete: (jqXHR, obj) => {
        self.removeClass('rp-loading');
        self.find('.rp-ajax-toggle-text')
          .removeClass('rp-text-visibility')
      },
      success: function (response) {
        // self.text(text);
        $('#rpressModal .modal-title')
          .html(response.data.html_title);
        $('#rpressModal .modal-body')
          .html(response.data.html);
        MicroModal.show('rpressModal', {
          disableScroll: true
        });
        if ($('.rpress-tabs-wrapper')
          .length) {
          if (ServiceTime !== '') {
            $('.rpress-delivery-wrap')
              .find('select#rpress-' + ServiceType + '-hours')
              .val(ServiceTime);
          }
          $('.rpress-delivery-wrap')
            .find('a#nav-' + ServiceType + '-tab')
            .trigger('click');
        }
        // Trigger event so themes can refresh other areas.
        $(document.body).trigger('opened_service_options', [response.data]);
      }
    })
  });
  // Remove Item from Cart
  $('.rpress-cart')
    .on('click', '.rpress-remove-from-cart', function (event) {
      if ($('.rpress-remove-from-cart')
        .length == 1 && !confirm(rp_scripts.confirm_empty_cart)) {
        return false;
      }
      var $this = $(this),
        item = $this.data('cart-item'),
        action = $this.data('action'),
        id = $this.data('fooditem-id'),
        security = rp_scripts.edit_cart_fooditem_nonce,
        data = {
          action: action,
          cart_item: item,
          security: security
        };
      $.ajax({
        type: "POST",
        data: data,
        dataType: "json",
        url: rpress_scripts.ajaxurl,
        xhrFields: {
          withCredentials: true
        },
        success: function (response) {
          if (response.removed) {
            // Remove the $this cart item
            $('.rpress-cart .rpress-cart-item')
              .each(function () {
                $(this)
                  .find("[data-cart-item='" + item + "']")
                  .parents('.rpress-cart-item')
                  .remove();
              });
            // Check to see if the purchase form(s) for this fooditem is present on this page
            if ($('[id^=rpress_purchase_' + id + ']')
              .length) {
              $('[id^=rpress_purchase_' + id + '] .rpress_go_to_checkout')
                .hide();
              $('[id^=rpress_purchase_' + id + '] a.rpress-add-to-cart')
                .show()
                .removeAttr('data-rpress-loading');
              if (rpress_scripts.quantities_enabled == '1') {
                $('[id^=rpress_purchase_' + id + '] .rpress_fooditem_quantity_wrapper')
                  .show();
              }
            }
            $('span.rpress-cart-quantity')
              .html(`${response.cart_quantity}<span>${rpress_scripts.items}</span>`);
            $('.rp-mb-price')
              .text(response.total);
            $('.rp-mb-quantity')
              .text(response.cart_quantity);
            $(document.body)
              .trigger('rpress_quantity_updated', [response.cart_quantity]);
            if (rpress_scripts.taxes_enabled) {
              $('.cart_item.rpress_subtotal span')
                .html(response.subtotal);
              $('.cart_item.rpress_cart_tax span')
                .html(response.tax);
            }
            $('.cart_item.rpress_total span.rpress-cart-quantity')
              .html(response.cart_quantity);
            $('.cart_item.rpress_total span.cart-total')
              .html(response.total);
            if (response.cart_quantity == 0) {
              $('li.rpress-cart-meta, .cart_item.rpress_subtotal, .rpress-cart-number-of-items, .cart_item.rpress_checkout, .cart_item.rpress_cart_tax, .cart_item.rpress_total')
                .hide();
              $('.rpress-cart')
                .each(function () {
                  var cart_wrapper = $(this)
                    .parent();
                  if (cart_wrapper) {
                    cart_wrapper.addClass('cart-empty')
                    cart_wrapper.removeClass('cart-not-empty');
                  }
                  $(this)
                    .append('<li class="cart_item empty">' + rpress_scripts.empty_cart_message + '</li>');
                });
            }
            $(document.body)
              .trigger('rpress_cart_item_removed', [response]);
            $('ul.rpress-cart > li.rpress-cart-item')
              .each(function (index, item) {
                $(this)
                  .find("[data-cart-item]")
                  .attr('data-cart-item', index);
                $(this)
                  .find("[data-remove-item]")
                  .attr('data-remove-item', index);
                $(this)
                  .attr('data-cart-key', index);
              });
            // check if no item in cart left
            if ($('li.rpress-cart-item')
              .length == 0) {
              // $('a.rpress-clear-cart').trigger('click');
              $('div.rpress.item-order').hide();
              $('li.rpress-cart-meta')
                .hide();
              $('li.delivery-items-options')
                .hide();
              $('a.rpress-clear-cart')
                .hide();
            }
          }
        }
      });
      return false;
    });
  // Clear All Fooditems from Cart
  $(document)
    .on('click', 'a.rpress-clear-cart', function (e) {
      e.preventDefault();
      if (confirm(rp_scripts.confirm_empty_cart)) {
        var self = $(this);
        var old_text = self.html();
        var action = 'rpress_clear_cart';
        var data = {
          security: rp_scripts.clear_cart_nonce,
          action: action
        }
        $.ajax({
          type: "POST",
          data: data,
          dataType: "json",
          url: rp_scripts.ajaxurl,
          xhrFields: {
            withCredentials: true
          },
          beforeSend: (jqXHR, object) => {
            self.addClass('rp-loading')
            self.find('.rp-ajax-toggle-text')
              .addClass('rp-text-visibility')
          },
          complete: (jqXHR, object) => {
            self.removeClass('rp-loading')
            self.find('.rp-ajax-toggle-text')
              .removeClass('rp-text-visibility')
          },
          success: function (response) {
            if (response.status == 'success') {
              $('span.rpress-cart-quantity')
                .html(`${0}<span> Items</span>`);
              $('.rp-mb-price')
                .text(`${rp_scripts.currency_sign}0.00`);
              $(document.body)
                .trigger('rpress_quantity_updated', [0]);
              $(".rpress-sidebar-main-wrap")
                .css("left", "100%");
              $('ul.rpress-cart')
                .find('li.cart_item.rpress_total')
                .css('display', 'none');
              $('ul.rpress-cart')
                .find('li.cart_item.rpress_checkout')
                .css('display', 'none');
              $('ul.rpress-cart')
                .find('li.rpress-cart-item')
                .remove();
              $('ul.rpress-cart')
                .find('li.cart_item.empty')
                .remove();
              $('ul.rpress-cart')
                .find('li.rpress_subtotal')
                .remove();
              $('ul.rpress-cart')
                .find('li.rpress_cart_tax')
                .remove();
              $('ul.rpress-cart')
                .find('li.rpress-delivery-fee')
                .remove();
              $('ul.rpress-cart')
                .append(response.response);
              $('.rpress-cart-number-of-items')
                .css('display', 'none');
              $('.delivery-items-options')
                .css('display', 'none');
              $('.rpress-mobile-cart-icons')
                .hide();
              self.hide();
              tata.success(window.rp_scripts.success, window.rp_scripts.success_empty_cart, {
                position: "tr"
              })
            }
          }
        });
      }
    });
  // Proceed to Checkout
  $(document)
    .on('click', '.cart_item.rpress_checkout a', function (e) {
      e.preventDefault();
      var CheckoutUrl = rp_scripts.checkout_page;
      var _self = $(this);
      var OrderText = _self.text();
      var action = 'rpress_proceed_checkout';
      var data = {
        action: action,
        security: rp_scripts.proceed_checkout_nonce,
      }

      $.ajax({
        type: "POST",
        data: data,
        dataType: "json",
        url: rp_scripts.ajaxurl,
        beforeSend: function () {
          _self.addClass('rp-loading');
          _self.children('.rp-ajax-toggle-text')
            .addClass('rp-text-visibility');
        },
        success: function (response) {
          if (response.status == 'error') {
            if (response.error_msg) {
              errorString = response.error_msg;
            }
            tata.error(rp_scripts.error, errorString);
            _self.removeClass('rp-loading');
            _self.children('.rp-ajax-toggle-text')
              .removeClass('rp-text-visibility');
          } else {
            window.location.replace(rp_scripts.checkout_page)
            return;
          }
        }
      })
    });
  $(document)
    .on('click', 'span.special-instructions-link', function (e) {
      e.preventDefault();
      $(this)
        .parent('div')
        .find('.special-instructions')
        .toggleClass('hide');
    });
  $('body')
    .on('click', '.rpress-filter-toggle', function () {
      $('div.rpress-filter-wrapper')
        .toggleClass('active');
    });
  $(".rp-cart-left-wrap")
    .click(function () {
      $(".rpress-sidebar-main-wrap")
        .css("left", "0%");
    });
  //Triggering cart
  $(".rp-cart-right-wrap")
    .click(function () {
      $(".cart_item.rpress_checkout a")
        .trigger('click');
    });
  $(".close-cart-ic")
    .click(function () {
      $(".rpress-sidebar-main-wrap")
        .css("left", "100%");
    });
  // Show Image on Modal
  $(".rpress-thumbnail-popup")
    .fancybox({
      openEffect: 'elastic',
      closeEffect: 'elastic',
      helpers: {
        title: {
          type: 'inside'
        }
      }
    });
  if ($(window)
    .width() > 991) {
    var totalHeight = $('header:eq(0)')
      .length > 0 ? $('header:eq(0)')
        .height() + 30 : 120;
    if ($(".sticky-sidebar")
      .length != '') {
      $('.sticky-sidebar')
        .rpressStickySidebar({
          additionalMarginTop: totalHeight
        });
    }
  } else {
    var totalHeight = $('header:eq(0)')
      .length > 0 ? $('header:eq(0)')
        .height() + 30 : 70;
  }
});
/* Make Addons and Variables clickable for Live Price */
jQuery(document)
  .ajaxComplete(function () {
    jQuery('#fooditem-details .food-item-list input')
      .on('click', function (event) {
        update_modal_live_price('fooditem-details');
      });
    jQuery('#fooditem-update-details .food-item-list input')
      .on('click', function (event) {
        update_modal_live_price('fooditem-update-details');
      });
  });
/* RestroPress Sticky Sidebar - Imported from rp-sticky-sidebar.js */
jQuery(function ($) {
  if ($(window).width() > 991) {
    var totalHeight = $('header:eq(0)').length > 0 ? $('header:eq(0)').height() + 30 : 120;
    if ($(".sticky-sidebar").length > 0) {
      $('.sticky-sidebar').rpressStickySidebar({
        additionalMarginTop: totalHeight
      });
    }
  } else {
    var totalHeight = $('header:eq(0)').length > 0 ? $('header:eq(0)').height() + 30 : 70;
  }
  // Category Navigation
  $('body')
    .on('click', '.rpress-category-link', function (e) {
      e.preventDefault();
      var this_id = $(this)
        .data('id');
      var gotom = setInterval(function () {
        rpress_go_to_navtab(this_id);
        clearInterval(gotom);
      }, 100);
    });
  function rpress_go_to_navtab(id) {
    var scrolling_div = jQuery('div.rpress_fooditems_list')
      .find('div#menu-category-' + id);
    if (scrolling_div.length) {
      offSet = scrolling_div.offset()
        .top;
      var body = jQuery("html, body");
      body.animate({
        scrollTop: offSet - totalHeight
      }, 500);
    }
  }
  $('.rpress-category-item')
    .on('click', function () {
      $('.rpress-category-item')
        .removeClass('current');
      $(this)
        .addClass('current');
    });
});
const totalHeight = 100; // Adjust for sticky header height

jQuery(function($){

  // Smooth scroll for category dropdown and horizontal nav
  $('body').on('click', '.cd-dropdown-content a, .pn-ProductNav_Link', function (e) {
    e.preventDefault();

    const targetSelector = $(this).attr('href'); // #menu-category-12
    const $target = $(targetSelector);

    if ($target.length) {
      const offset = $target.offset().top;
      const headerOffset = typeof totalHeight !== 'undefined' ? totalHeight : 80;

      $('html, body').animate({
        scrollTop: offset - headerOffset
      }, 500);
    }

    // Set aria-selected on horizontal nav
    if ($(this).hasClass('pn-ProductNav_Link')) {
      $('.pn-ProductNav_Link').removeAttr('aria-selected');
      $(this).attr('aria-selected', 'true');
    }

    // Highlight dropdown item
    if ($(this).closest('.cd-dropdown-content').length) {
      $('.cd-dropdown-content li a').removeClass('mnuactive');
      $(this).addClass('mnuactive');
    }
  });

});

/* Cart Quantity Changer - Imported from cart-quantity-changer.js */
jQuery(function ($) {
  //quantity Minus
  var liveQtyVal;
  jQuery(document)
    .on('click', '.qtyminus', function (e) {
      // Stop acting like a button
      e.preventDefault();
      // Get the field name
      fieldName = 'quantity';
      // Get its current value
      var currentVal = parseInt(jQuery('input[name=' + fieldName + ']')
        .val());
      // If it isn't undefined or its greater than 0
      if (!isNaN(currentVal) && currentVal > 1) {
        // Decrement one only if value is > 1
        jQuery('input[name=' + fieldName + ']')
          .val(currentVal - 1);
        jQuery('.qtyplus')
          .removeAttr('style');
        liveQtyVal = currentVal - 1;
      } else {
        // Otherwise put a 0 there
        jQuery('input[name=' + fieldName + ']')
          .val(1);
        jQuery('.qtyminus')
          .css('color', '#aaa')
          .css('cursor', 'not-allowed');
        liveQtyVal = 1;
      }
      jQuery(this)
        .parents('footer.modal-footer')
        .find('a.submit-fooditem-button')
        .attr('data-item-qty', liveQtyVal);
      jQuery(this)
        .parents('footer.modal-footer')
        .find('a.submit-fooditem-button')
        .attr('data-item-qty', liveQtyVal);
      // Updating live price as per quantity
      var total_price = parseFloat(jQuery('#rpressModal .cart-item-price')
        .attr('data-current'));
      var new_price = parseFloat(total_price * liveQtyVal);
      if (rp_scripts.decimal_separator == ',') {
        new_price_v = new_price.toFixed(2)
          .split('.')
          .join(',');
      } else {
        new_price_v = new_price.toFixed(2);
      }
      jQuery('#rpressModal .cart-item-price')
        .html(rp_scripts.currency_sign + new_price_v);
    });
  jQuery(document)
    .on('click', '.qtyplus', function (e) {
      // Stop acting like a button
      e.preventDefault();
      // Get the field name
      fieldName = 'quantity';
      // Get its current value
      var currentVal = parseInt(jQuery('input[name=' + fieldName + ']').val());
      // If is not undefined
      if (!isNaN(currentVal)) {
        jQuery('input[name=' + fieldName + ']')
          .val(currentVal + 1);
        jQuery('.qtyminus')
          .removeAttr('style');
        liveQtyVal = currentVal + 1;
      } else {
        // Otherwise put a 0 there
        jQuery('input[name=' + fieldName + ']')
          .val(1);
        liveQtyVal = 1;
      }
      jQuery(this)
        .parents('footer.modal__footer')
        .find('a.submit-fooditem-button')
        .attr('data-item-qty', liveQtyVal);
      jQuery(this)
        .parents('footer.modal__footer')
        .find('a.submit-fooditem-button')
        .attr('data-item-qty', liveQtyVal);
      // Updating live price as per quantity
      var total_price = parseFloat(jQuery('#rpressModal .cart-item-price')
        .attr('data-current'));
      var new_price = parseFloat(total_price * liveQtyVal);
      if (rp_scripts.decimal_separator == ',') {
        new_price_v = new_price.toFixed(2)
          .split('.')
          .join(',');
      } else {
        new_price_v = new_price.toFixed(2);
      }
      jQuery('#rpressModal .cart-item-price')
        .html(rp_scripts.currency_sign + new_price_v);
    });
  jQuery(document)
    .on("input", ".qty", function () {
      this.value = this.value.replace(/\D/g, '');
    });
  jQuery(document)
    .on('keyup', '.qty', function (e) {
      // Updating live price as per quantity
      liveQtyVal = jQuery(this)
        .val();
      var total_price = parseFloat(jQuery('#rpressModal .cart-item-price')
        .attr('data-current'));
      var new_price = parseFloat(total_price * liveQtyVal);
      if (rp_scripts.decimal_separator == ',') {
        new_price_v = new_price.toFixed(2)
          .split('.')
          .join(',');
      } else {
        new_price_v = new_price.toFixed(2);
      }
      jQuery('#rpressModal .cart-item-price')
        .html(rp_scripts.currency_sign + new_price_v);
    });
});
jQuery(function ($) {
  // Detect whether list or grid is currently visible
  function getCurrentViewContainer() {
    if ($('.rpress_fooditems_list:visible').length) {
      return $('.rpress_fooditems_list');
    } else if ($('.rpress_fooditems_grid:visible').length) {
      return $('.rpress_fooditems_grid');
    } else {
      return $('.rpress_fooditems_list'); // default fallback
    }
  }

  // Step 1: Add searchable data attribute to all .rpress-title-holder elements (in both views)
  $('.rpress-title-holder').each(function () {
    $(this).attr('data-search-term', $(this).text().toLowerCase());
  });

  // Step 2: Live search
  $('#rpress-food-search').on('keyup', function () {
    var searchTerm = $(this).val().toLowerCase();
    var $viewContainer = getCurrentViewContainer();
    var visibleTermIds = [];

    // Reset states
    $viewContainer.find('.rpress-element-title').removeClass('matched not-matched');

    $viewContainer.find('.rpress_fooditem').each(function () {
      var $foodItem = $(this);
      var $titleHolder = $foodItem.find('.rpress-title-holder');
      var itemText = $titleHolder.data('search-term') || $titleHolder.text().toLowerCase();
      var termId = $foodItem.attr('data-term-id');

      if (searchTerm === '' || itemText.includes(searchTerm)) {
        $foodItem.show();
        visibleTermIds.push(termId);
      } else {
        $foodItem.hide();
      }
    });

    // Show/hide category headings
    $viewContainer.find('.rpress-element-title').each(function () {
      var termId = $(this).data('term-id');
      if (visibleTermIds.includes(termId.toString())) {
        $(this).show().addClass('matched');
      } else {
        $(this).hide().addClass('not-matched');
      }
    });

    // === CATEGORY NAVIGATION HIDE/SHOW ===
    $('.rpress-category-lists .rpress-category-link').each(function () {
      var termId = $(this).data('id');
      if (visibleTermIds.includes(termId.toString())) {
        $(this).parent().show();
      } else {
        $(this).parent().hide();
      }
    });

    // Dropdown menu in grid view
    $('.cd-dropdown-content a[href^="#menu-category-"]').each(function () {
      var termId = $(this).attr('href').replace('#menu-category-', '');
      if (visibleTermIds.includes(termId)) {
        $(this).closest('li').show();
      } else {
        $(this).closest('li').hide();
      }
    });

    // Horizontal nav in grid view
    $('.pn-ProductNav_Contents .pn-ProductNav_Link').each(function () {
      var termId = $(this).attr('href').replace('#menu-category-', '');
      if (visibleTermIds.includes(termId)) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });

  });
});

/* RestroPress active category highlighter */
jQuery(function ($) {
  const rp_category_links = $('.rpress-category-lists .rpress-category-link');
  const rp_category_links_grid = $('#pnProductNavContents .pn-ProductNav_Link');
  if (rp_category_links.length > 0) {
    const header_height = $('header:eq(0)').height();
    let current_category = rp_category_links.eq('0').attr('href').substr(1);
    function RpScrollingCategories() {
      rp_category_links.each(function () {
        const section_id = $(this).attr('href').substr(1);
        const section = document.querySelector(`.menu-category-wrap[data-cat-id="${section_id}"]`);
        if (section.getBoundingClientRect().top < header_height + 40) {
          current_category = section_id;
        }
        $('.rpress-category-lists .rpress-category-link').removeClass('active');
        $(`.rpress-category-lists .rpress-category-link[href="#${current_category}"]`).addClass('active');

        $('.rpress-category-lists .rpress-category-link').parent().removeClass('current');
        $(`.rpress-category-lists .rpress-category-link[href="#${current_category}"]`).parent().addClass('current');
      });
    }
    window.onscroll = function () {
      RpScrollingCategories();
    }
  }

  if (rp_category_links_grid.length > 0) {
    const header_height = $('header:eq(0)').height();
    const $nav = $('#pnProductNav');          // the actual scroller
    const $navContents = $('#pnProductNavContents');  // inner content
    const $indicator = $('#pnIndicator');
    let current_category = rp_category_links_grid.eq(0).attr('href').substr(1);

    // Make sure navContents is positioned so indicator can be positioned inside it
    if ($navContents.length && $navContents.css('position') === 'static') {
      $navContents.css('position', 'relative');
    }

    // ---- helper: move indicator visually under the link ----
    function moveIndicatorTo($link) {
      if (!$link || !$link.length) return;

      // Use bounding rects so calculation is robust even with transforms / scrolling
      const linkRect = $link[0].getBoundingClientRect();
      const contentsRect = $navContents[0].getBoundingClientRect();

      // left relative to contents' left (visual coordinate)
      const left = Math.round(linkRect.left - contentsRect.left + ($navContents.scrollLeft() || 0));
      const width = Math.round(linkRect.width);

      // Apply width and translateX in px (reliable)
      $indicator.css({
        width: width + 'px',
        transform: 'translateX(' + left + 'px)'
      });

      // Ensure the active link is visible / centered inside the scroller
      scrollActiveIntoView($link);
    }

    // ---- helper: scroll the scroller so the link is visible/centered ----
    function scrollActiveIntoView($link) {
      if (!$link || !$link.length) return;

      // Choose real scroller (outer nav) - fallback to contents if outer missing
      const $scroller = $nav.length ? $nav : $navContents;
      if (!$scroller.length) return;

      const scrollerEl = $scroller[0];
      const maxScroll = scrollerEl.scrollWidth - scrollerEl.clientWidth;

      const linkRect = $link[0].getBoundingClientRect();
      const scrollerRect = scrollerEl.getBoundingClientRect();

      // Current scrollLeft
      const currentScroll = $scroller.scrollLeft();

      // left of link relative to scrollable content coordinates:
      // (element left on screen - scroller left on screen) + currentScroll
      let leftRelativeToScroller = linkRect.left - scrollerRect.left + currentScroll;

      // center the link
      let desired = leftRelativeToScroller - (scrollerEl.clientWidth / 2) + ($link.outerWidth() / 2);

      // clamp
      desired = Math.max(0, Math.min(desired, maxScroll));

      // If the inner contents currently has a transform (arrow animation in progress),
      // animated scrollLeft may behave unexpectedly. In that case use scrollIntoView fallback:
      const contentsTransform = getComputedStyle($navContents[0]).transform;
      if (contentsTransform && contentsTransform !== 'none') {
        // fallback - ask the browser to scroll the nearest scrollable ancestor
        try {
          $link[0].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
          return;
        } catch (e) {
          // if not supported, continue to jQuery animate fallback
        }
      }

      // animate scrollLeft (smooth)
      $scroller.stop().animate({ scrollLeft: desired }, 350);
    }

    // ---- main scanning function (updates current_category + classes) ----
    function RpScrollingCategoriesgrid() {
      // determine current category based on section positions
      rp_category_links_grid.each(function () {
        const section_id = $(this).attr('href').substr(1);
        const section = document.getElementById(section_id);
        if (section && section.getBoundingClientRect().top < header_height + 40) {
          current_category = section_id;
        }
      });

      // Update active link classes/aria
      const $activeLink = rp_category_links_grid
        .removeAttr('aria-selected').removeClass('mnuactive')
        .filter(`[href="#${current_category}"]`)
        .attr('aria-selected', 'true').addClass('mnuactive');

      // Move indicator + ensure visible
      if ($activeLink.length) {
        moveIndicatorTo($activeLink);
      }

      // Sync the dropdown menu
      $(".cd-dropdown-content a")
        .removeClass("mnuactive")
        .filter(`[href="#${current_category}"]`)
        .addClass("mnuactive");
    }

    // Run once on load
    RpScrollingCategoriesgrid();

    // Update on page scroll / resize (throttle-friendly native listeners)
    window.addEventListener('scroll', RpScrollingCategoriesgrid, { passive: true });
    window.addEventListener('resize', RpScrollingCategoriesgrid);

    // Utility to get current section in view (used elsewhere)
    function getCurrentCategoryInView() {
      let scrollTop = $(window).scrollTop();
      let current = null;

      $(".rpress-category-lists .rpress-category-wrap").each(function () {
        if ($(this).offset().top <= scrollTop + 100) {
          current = $(this).attr("id");
        }
      });

      return current;
    }

    // On window scroll (content scroll, not nav scroll) — keep dropdown and indicator in sync
    $(window).on("scroll", function () {
      let current = getCurrentCategoryInView();
      if (!current) return;

      const $active = rp_category_links_grid.filter(`[href="#${current}"]`);
      if ($active.length) {
        moveIndicatorTo($active);

        // Sync dropdown
        const activeHref = $active.attr("href");
        $(".cd-dropdown-content a").removeClass("mnuactive");
        $(`.cd-dropdown-content a[href="${activeHref}"]`).addClass("mnuactive");
      }
    });

    // Nav link click instant feedback
    $(document).on("click", ".pn-ProductNav_Link", function (e) {
      const $this = $(this);
      // Move indicator & scroll into view immediately
      moveIndicatorTo($this);

      // Sync dropdown
      const activeHref = $this.attr("href");
      $(".cd-dropdown-content a").removeClass("mnuactive");
      $(`.cd-dropdown-content a[href="${activeHref}"]`).addClass("mnuactive");
    });

  }

  $(document)
    .ready(function () {
      // Infinate scroll for order history page 
      createObserver();
      //Select service type on checkout page 
      let chkServiceType = rp_getCookie('service_type');
      if (chkServiceType) {
        $(`.rp-checkout-service-option .single-service-selected[data-service-type=${chkServiceType}]`)
          .trigger('click');
      }
      $('.rp-checkout-service-option .single-service-selected')
        .on('click', async function () {
          const serviceType_ = $(this)
            .data('service-type');
          rp_setCookie('service_type', serviceType_, rp_scripts.expire_cookie_time);
          $('#rpress_checkout_order_details')
            .addClass('rp-loading')
          const promiseData = await fetch(`${rp_scripts.ajaxurl}?action=rpress_checkout_update_service_option`);
          const html = await promiseData.json();
          $('#rpress_checkout_order_details')
            .removeClass('rp-loading')
          $('#rpress_checkout_order_details')
            .replaceWith(html.data['order_html']);
          $('#rpress_checkout_cart_wrap')
            .html(html.data['cart_html']);
          $('.rpress_cart_amount')
            .replaceWith(html.data['total_amount']);
          $(document.body)
            .trigger('rp-checkout-update-service-option', [html]);
        });
      //set cookies on checkout page onchange service type
      $('.rp-checkout-service-option .rpress-hrs')
        .on('change', function () {
          $('.rp-checkout-service-option .rpress-delivery-opt-update')
            .trigger('click');
        })
      //Remove the additional service date dropdown
      if (typeof rp_st_vars !== 'undefined' && rp_st_vars.enabled_sevice_type == 'delivery_and_pickup') {
        $('.delivery-settings-wrapper#nav-pickup .delivery-time-wrapper:eq(0)')
          .remove();
      }
      var ServiceTime = rp_getCookie('service_time');
      $('.rpress-delivery')
        .val(ServiceTime);
      $('.rpress-pickup')
        .val(ServiceTime);
    })

  $(document).on('click', 'a.rpress_cart_remove_item_btn', function (e) {
    // e.preventDefault();
    var btnCount = $('.rpress_cart_remove_item_btn').length;

    // Check if there's only one element
    if (btnCount === 1) {
      var postData = {
        action: 'rpress_remove_fees_after_empty_cart',
        gateway: rpress_gateway
      };

      $.ajax({
        type: "POST",
        data: postData,
        dataType: "json",
        url: rpress_global_vars.ajaxurl,
        success: function (response) {
          // Trigger a click event on the same button
          $('a.rpress_cart_remove_item_btn').click();
        }
      }).fail(function (data) {
        if (window.console && window.console.log) {
        }
      });
    }
  });
  // 
});
let page = 1;
const infinateCallback = async function (entries, observer) {
  for (var i = 0; i < entries.length; i++) {
    let cahnge = entries[i];
    if (cahnge.isIntersecting) {
      jQuery('#rp-order-history-infi-load-container')
        .html('<h2 class="rp-infi-load"><div class="rp-infi-loading">Loading...</div></h2>');
      page = page + 1;
      const data = await fetch(`${rp_scripts.ajaxurl}?action=rpress_more_order_history&security=${rp_scripts.order_details_nonce}&paged=${page}`);
      const html = await data.json();
      jQuery('#rpress_user_history .repress-history-inner')
        .append(html.data['html']);
      if (html.data['found_post'] == '0') {
        jQuery('#rp-order-history-infi-load-container')
          .hide();
      }
    }
  }
}
const createObserver = () => {
  const options = {
    threshold: 0,
  }
  const lastDiv = document.getElementById('rp-order-history-infi-load-container');
  if (!lastDiv) return;
  const observer = new IntersectionObserver(infinateCallback, options);
  observer.observe(lastDiv)
}
// Refresh page after coming from prev page
if (performance.navigation.type == 2) {
  location.reload(true);
}
jQuery('.rpress_fooditems_list').find('.rpress_fooditem_inner').each(function (index, item) {
  if (0 === jQuery(this).find('.rpress-thumbnail-holder').length) {
    jQuery(this).addClass('no-thumbnail-img');
  }
});
jQuery(document).ready(function ($) {
  $(document).on('change', '.rp-variable-price-option', function () {
    rp_checked_default_subaddon();
  });
});

jQuery(document).ready(function ($) {
  // Handle tab switching and cookie update on service tab click
  $('.rpress-tabs-wrapper').on('click', '.nav-link', function (e) {
    e.preventDefault();

    var $this = $(this);
    var $wrapper = $this.closest('.rpress-tabs-wrapper');

    // Remove active class from all nav-items and nav-links
    $wrapper.find('.nav-item').removeClass('active');
    $wrapper.find('.nav-link').removeClass('active');

    // Add active class to clicked tab
    $this.addClass('active');
    $this.closest('.nav-item').addClass('active');

    // Show the tab content if needed
    var targetTab = $this.attr('href');
    if (targetTab && $(targetTab).length) {
      $wrapper.find('.tab-pane').removeClass('active show');
      $(targetTab).addClass('active show');
    }

    // Update cookies for service type and label
    var serviceType = $this.data('service-type');
    var serviceLabel = $this.text().trim();

    if (typeof Cookies !== 'undefined') {
      Cookies.set('service_type', serviceType, { expires: 1 });
      Cookies.set('service_label', serviceLabel, { expires: 1 });
    }

    // Optionally update UI somewhere else if needed
    $('.delivery-opts').html('<span class="delMethod">' + serviceLabel + '</span>');
  });

  // // 🔁 Trigger default tab programmatically AFTER short delay to ensure DOM is ready
  // setTimeout(function () {
  //   let $defaultActive = $('.rpress-tabs-wrapper .nav-item.active .nav-link');
  //   if ($defaultActive.length) {
  //     $defaultActive.trigger('click');
  //   }
  // }, 100); // slight delay ensures it works after render
});



/*---foodmenutab---*/
var SETTINGS = {
  navBarTravelling: false,
  navBarTravelDirection: "",
  navBarTravelDistance: 150
};

var colours = {
  0: "#867100",
  1: "#7F4200",
  2: "#99813D",
  3: "#40FEFF",
  4: "#14CC99",
  5: "#00BAFF",
  6: "#0082B2",
  7: "#B25D7A",
  8: "#00FF17",
  9: "#006B49",
  10: "#00B27A",
  11: "#996B3D",
  12: "#CC7014",
  13: "#40FF8C",
  14: "#FF3400",
  15: "#ECBB5E",
  16: "#ECBB0C",
  17: "#B9D912",
  18: "#253A93",
  19: "#125FB9"
};

document.documentElement.classList.remove("no-js");
document.documentElement.classList.add("js");

function initProductNav(config) {
  const nav = document.getElementById(config.nav);
  const contents = document.getElementById(config.contents);
  const indicator = document.getElementById(config.indicator);
  const leftBtn = document.getElementById(config.leftBtn);
  const rightBtn = document.getElementById(config.rightBtn);

  if (!nav || !contents || !indicator || !leftBtn || !rightBtn) return;

  nav.setAttribute("data-overflowing", determineOverflow(contents, nav));
  moveIndicator(nav.querySelector('[aria-selected="true"]'), indicator, colours[0], contents);

  let ticking = false;
  nav.addEventListener("scroll", function () {
    if (!ticking) {
      window.requestAnimationFrame(function () {
        nav.setAttribute("data-overflowing", determineOverflow(contents, nav));
        ticking = false;
      });
    }
    ticking = true;
  });

  leftBtn.addEventListener("click", function () {
    if (SETTINGS.navBarTravelling) return;
    const overflow = determineOverflow(contents, nav);
    if (overflow === "left" || overflow === "both") {
      const availableScrollLeft = nav.scrollLeft;
      const distance = availableScrollLeft < SETTINGS.navBarTravelDistance * 2
        ? availableScrollLeft
        : SETTINGS.navBarTravelDistance;

      contents.style.transform = `translateX(${distance}px)`;
      contents.classList.remove("pn-ProductNav_Contents-no-transition");
      SETTINGS.navBarTravelDirection = "left";
      SETTINGS.navBarTravelling = true;
    }
    nav.setAttribute("data-overflowing", determineOverflow(contents, nav));
  });

  rightBtn.addEventListener("click", function () {
    if (SETTINGS.navBarTravelling) return;
    const overflow = determineOverflow(contents, nav);
    if (overflow === "right" || overflow === "both") {
      const navBarRightEdge = contents.getBoundingClientRect().right;
      const scrollerRightEdge = nav.getBoundingClientRect().right;
      const availableScrollRight = Math.floor(navBarRightEdge - scrollerRightEdge);
      const distance = availableScrollRight < SETTINGS.navBarTravelDistance * 2
        ? availableScrollRight
        : SETTINGS.navBarTravelDistance;

      contents.style.transform = `translateX(-${distance}px)`;
      contents.classList.remove("pn-ProductNav_Contents-no-transition");
      SETTINGS.navBarTravelDirection = "right";
      SETTINGS.navBarTravelling = true;
    }
    nav.setAttribute("data-overflowing", determineOverflow(contents, nav));
  });

  contents.addEventListener("transitionend", function () {
    const tr = getComputedStyle(contents).transform;
    const amount = Math.abs(parseInt(tr.split(",")[4]) || 0);
    contents.style.transform = "none";
    contents.classList.add("pn-ProductNav_Contents-no-transition");
    if (SETTINGS.navBarTravelDirection === "left") {
      nav.scrollLeft -= amount;
    } else {
      nav.scrollLeft += amount;
    }
    SETTINGS.navBarTravelling = false;
  });

  contents.addEventListener("click", function (e) {
    if (!e.target.classList.contains('pn-ProductNav_Link')) return;
    const links = Array.from(contents.querySelectorAll(".pn-ProductNav_Link"));
    links.forEach(link => link.setAttribute("aria-selected", "false"));
    e.target.setAttribute("aria-selected", "true");
    moveIndicator(e.target, indicator, colours[links.indexOf(e.target)], contents);
  });

  window.addEventListener("resize", function () {
    const selected = nav.querySelector('[aria-selected="true"]');
    if (selected) moveIndicator(selected, indicator, null, contents);
  });
}

function moveIndicator(item, indicator, color, container) {
  if (!item || !indicator) return;
  const textPosition = item.getBoundingClientRect();
  const containerLeft = container.getBoundingClientRect().left;
  const distance = textPosition.left - containerLeft;
  const scroll = container.scrollLeft;
  indicator.style.transform = `translateX(${distance + scroll}px) scaleX(${textPosition.width * 0.01})`;
  if (color) indicator.style.backgroundColor = color;
}

function determineOverflow(content, container) {
  const containerRect = container.getBoundingClientRect();
  const contentRect = content.getBoundingClientRect();
  if (
    containerRect.left > contentRect.left &&
    containerRect.right < contentRect.right
  ) {
    return "both";
  } else if (contentRect.left < containerRect.left) {
    return "left";
  } else if (contentRect.right > containerRect.right) {
    return "right";
  } else {
    return "none";
  }
}

// Initialize both desktop and mobile tab navigations
initProductNav({
  nav: 'pnProductNav',
  contents: 'pnProductNavContents',
  indicator: 'pnIndicator',
  leftBtn: 'pnAdvancerLeft',
  rightBtn: 'pnAdvancerRight'
});

initProductNav({
  nav: 'pnProductNavMobile',
  contents: 'pnProductNavContentsMobile',
  indicator: 'pnIndicatorMobile',
  leftBtn: 'pnAdvancerLeftMobile',
  rightBtn: 'pnAdvancerRightMobile'
});

jQuery(function ($) {
  if (window.location.pathname === '/order-online/') {
    $('body').addClass('order-online');
  }
  $('.rpress-category-lists .rpress-category-link:first').addClass('current');
  $('.rpress-category-lists li:first').addClass('current');

  $('.cd-dropdown-trigger').on('click', function () {
    $('body').toggleClass('cd-overlay-open');

    if ($('body').hasClass('cd-overlay-open')) {
      // Add overlay if opening
      if ($('.rpress-cat-overlay').length === 0) {
        // $('body').append('<div class="rpress-cat-overlay"></div>');
        var cdDropdown = $('.cd-dropdown-wrapper .cd-dropdown');
        $('<div class="rpress-cat-overlay"></div>').insertAfter(cdDropdown);
      }
    } else {
      // Remove overlay if closing
      $('.rpress-cat-overlay').remove();
    }
  });
  $('.cd-close').on('click', function () {
    $('body').removeClass('cd-overlay-open');
    $('.rpress-cat-overlay').remove();
  });
  $('.rpress-editaddress-cancel-btn').on('click', function () {
    $('.rpress-mobile-cart-icons').show();
    MicroModal.close('rpressDateTime');
  });
  $('.cd-dropdown-trigger').on('click', function (event) {
    event.preventDefault();
    $('.rpress-mobile-cart-icons').hide();
    toggleNav();
  });

  $('.cd-dropdown .cd-close').on('click', function (event) {
    event.preventDefault();
    $('.rpress-mobile-cart-icons').show();
    toggleNav();
  });

  function toggleNav() {
    var navIsVisible = (!$('.cd-dropdown').hasClass('dropdown-is-active'));
    $('.cd-dropdown').toggleClass('dropdown-is-active', navIsVisible);
    $('.cd-dropdown-trigger').toggleClass('dropdown-is-active', navIsVisible);

    if (!navIsVisible) {
      $('.cd-dropdown').one('transitionend webkitTransitionEnd oTransitionEnd MSTransitionEnd', function () {
        $('.has-children ul').addClass('is-hidden');
        $('.move-out').removeClass('move-out');
        $('.is-active').removeClass('is-active');
      });
    }
  }

  var serviceType = rp_getCookie('service_type');
  if (serviceType) {
    var capitalized = serviceType.charAt(0).toUpperCase() + serviceType.slice(1);
    $('.rpress-service-label').text(capitalized);
  }
  $('#rpressdeliveryTab').on('click', '.single-service-selected', function (e) {
    // Get the selected service type from data attribute
    var serviceType = $(this).data('service-type');

    if (serviceType) {
      var capitalized = serviceType.charAt(0).toUpperCase() + serviceType.slice(1);
      $('.rpress-service-label').text(capitalized);
    }
  });

  $(document.body).on('click', '.order-online-servicetabs .single-service-selected', function () {
    setTimeout(function () {
      location.reload();
    }, 400);
  });

  setTimeout(function () {
    // get the already selected value
    var selectedVal = $("#rpress-delivery-hours option:selected").val();

    // re-select it (forces selection again)
    $("#rpress-delivery-hours").val(selectedVal).trigger("change");
  }, 1000); // 1 second delay

  $(document).on('click', '.rp-variable-price-wrapper .radio-container', function () {
    var $radio = $(this).find('input[type="radio"]');

    if ($radio.length) {
      var name = $radio.attr('name');

      // Uncheck all radios in this group
      $('input[name="' + name + '"]').prop('checked', false);

      // Check the clicked one
      $radio.prop('checked', true).trigger('change');

      // Optional: manage "checked" class on container
      $('.radio-container').removeClass('checked');
      $(this).addClass('checked');
    }
  });
});
jQuery(document).ready(function ($) {
  $('.menu-category-wrap').first().addClass('first-menu-category-wrap');


  var $nav = $('#pnProductNav');

  function checkOverflow() {
    if ($nav.attr('data-overflowing') !== 'right') {
      $nav.parent('.pn-ProductNav_Wrapper').addClass('background-hidden');
    } else {
      $nav.parent('.pn-ProductNav_Wrapper').removeClass('background-hidden');
    }
  }

  // Run once on page load
  checkOverflow();

  function updateTooManyAdds($span) {
    var textOnly = $span.text().trim();
    var $parent = $span.closest('.rpress-price-holder');

    if ($parent.length) {
      if (textOnly.length > 3) {
        $parent.addClass('too-many-adds');
      } else {
        $parent.removeClass('too-many-adds');
      }
    }
  }

  // Initial check on page load
  $('span.rpress-add-to-cart-label.rp-ajax-toggle-text').each(function () {
    updateTooManyAdds($(this));
  });

  // Observe only the spans
  $('span.rpress-add-to-cart-label.rp-ajax-toggle-text').each(function () {
    var targetNode = this;

    var observer = new MutationObserver(function () {
      updateTooManyAdds($(targetNode));
    });

    observer.observe(targetNode, {
      characterData: true,
      subtree: true,
      childList: true
    });
  });
});

jQuery(document).ready(function ($) {

  var serviceType = rp_getCookie('service_type');
  if (!serviceType) {
    if (rp_scripts.service_options == 'delivery' || rp_scripts.service_options == 'pickup') {
      serviceType = rp_scripts.default_service || 'delivery';
      rp_setCookie('service_type', serviceType, rp_scripts.expire_cookie_time);
      location.reload();
    }
  }

  var $select = $('#rpress-delivery-hours');
  var serviceTime = rp_getCookie('service_time');

  if ($select.length > 0) {
    // Check if the select has an option matching the cookie value
    if (!serviceTime || $select.find('option').filter(function () {
      return $(this).text().trim() === serviceTime;
    }).length === 0) {
      // Cookie missing or not in options, fallback to first option
      serviceTime = $select.find('option:first').text().trim();
    }
  }

  // Print the value in the span
  $('#deliveryTime').text(serviceTime);

  function initStickyMenu() {
    var $menu = $('.desktop-scroll-menu .make-me-sticky');
    var $sidebar = $('.sticky-sidebar');

    if ($(window).width() > 991 && $menu.length > 0) {
      // Fixed offset from top
      var stickyTop = 0;

      var menuOffset = $menu.offset().top;

      $(window).off('.stickyMenu'); // Remove previous events

      $(window).on('scroll.stickyMenu', function () {
        var scrollTop = $(window).scrollTop();

        if (scrollTop + stickyTop >= menuOffset) {
          $menu.css({
            'position': 'fixed',
            'top': stickyTop + 'px',
            'left': $menu.offset().left + 'px',
            'width': $menu.outerWidth(),
            'z-index': 999
          });
        } else {
          $menu.css({
            'position': 'static',
            'width': '',
            'top': '',
            'left': '',
            'z-index': ''
          });
        }
      });

      // Recalculate left & width on resize
      $(window).on('resize.stickyMenu', function () {
        $menu.css({
          'position': 'static',
          'width': '',
          'top': '',
          'left': '',
          'height': $sidebar.length > 0 ? $sidebar.outerHeight() : 'auto'
        });
        menuOffset = $menu.offset().top;
      });

    } else {
      // Reset sticky behavior on mobile
      $menu.css({
        'position': 'static',
        'height': 'auto'
      });
    }
  }

  initStickyMenu();
  $(window).on('resize', initStickyMenu);
  /**
   * Action Menu Toggle for Mobile
   */
  var actionContainer = document.querySelector(".container-actionmenu");
  var burger = document.getElementById("actionburger");
  var menu = document.querySelector(".actionmenu");

  if (actionContainer) {
    actionContainer.addEventListener("click", (event) => {
      //if (event.target.tagName.toLowerCase() === "button") {
      event.preventDefault();
      // event.stopPropagation();
      // event.stopImmediatePropagation();

      burger.classList.toggle("is-expanded");

      // Get the icon and text elements
      var icon = burger.querySelector('i');
      var textNode = burger.querySelector('.menu-text');
      var isExpanded = burger.classList.contains('is-expanded');

      // Get texts and icons from data attributes
      var menuText = burger.getAttribute('data-text-menu');
      var closeText = burger.getAttribute('data-text-close');
      var menuIcon = burger.getAttribute('data-icon-menu');
      var closeIcon = burger.getAttribute('data-icon-close');

      if (isExpanded) {
        menu.classList.replace("slides-down", "slides-up");
        icon.className = closeIcon;
        textNode.textContent = closeText;
      } else {
        menu.classList.replace("slides-up", "slides-down");
        icon.className = menuIcon;
        textNode.textContent = menuText;
      }
      // }
    });
  }
});
