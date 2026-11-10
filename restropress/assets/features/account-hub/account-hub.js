/**
 * Account hub: saved address picker at checkout.
 *
 * Picking a saved address fills the delivery fields; editing a field by hand
 * switches the picker to "Use a new address" and shows "Save this address".
 * The order details card is re-rendered over AJAX when the service changes,
 * so everything is delegated and re-initialised after AJAX calls.
 */
(function ($) {
  'use strict';

  var FIELDS = {
    address: 'rpress_street_address',
    flat: 'rpress_apt_suite',
    city: 'rpress_city',
    postcode: 'rpress_postcode'
  };
  var PICKER = '#rpress-saved-address';
  var filling = false;

  function field(name) {
    return $('#rpress_checkout_order_details [name="' + name + '"]');
  }

  function setFields(values) {
    filling = true;
    $.each(FIELDS, function (key, name) {
      var $field = field(name);
      if ($field.length) {
        $field.val(values[key] || '').trigger('input').trigger('change');
      }
    });
    filling = false;
  }

  function fieldsEmpty() {
    var empty = true;
    $.each(FIELDS, function (key, name) {
      if ($.trim(field(name).val() || '') !== '') {
        empty = false;
      }
    });
    return empty;
  }

  function syncSaveOption() {
    var $picker = $(PICKER);
    var usingSaved = $picker.length && $picker.val() !== '';
    $('.rpress-save-address-wrap').toggle(!usingSaved);
    if (usingSaved) {
      $('#rpress-save-address').prop('checked', false);
    }
  }

  function fillFromPicker() {
    var $option = $(PICKER).find('option:selected');
    if (!$option.length || $option.val() === '') {
      return;
    }
    setFields({
      address: $option.data('address'),
      flat: $option.data('flat'),
      city: $option.data('city'),
      postcode: $option.data('postcode')
    });
  }

  function init() {
    var $picker = $(PICKER);
    if ($picker.length && !$picker.data('rpressReady')) {
      $picker.data('rpressReady', true);
      // The default address is preselected; fill it in when checkout left the fields blank.
      if ($picker.val() !== '' && fieldsEmpty()) {
        fillFromPicker();
      }
    }
    syncSaveOption();
  }

  $(document).on('change', PICKER, function () {
    if ($(this).val() === '') {
      setFields({});
      // A new address is usually one worth keeping.
      $('#rpress-save-address').prop('checked', true);
      field(FIELDS.address).trigger('focus');
    } else {
      fillFromPicker();
    }
    syncSaveOption();
  });

  var selectors = $.map(FIELDS, function (name) {
    return '#rpress_checkout_order_details [name="' + name + '"]';
  }).join(', ');

  $(document).on('input', selectors, function () {
    if (filling) {
      return;
    }
    var $picker = $(PICKER);
    if ($picker.length && $picker.val() !== '') {
      $picker.val('');
      syncSaveOption();
    }
  });

  $(init);
  $(document).ajaxComplete(function () {
    window.setTimeout(init, 0);
  });
})(jQuery);
