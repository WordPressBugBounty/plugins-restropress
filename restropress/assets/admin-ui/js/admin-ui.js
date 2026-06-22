(function () {
  document.documentElement.classList.add('rp-admin-ui-ready');

  var config = window.rpressAdminUI || {};
  var dateRangeConfig = config.dateRange || {};

  function pad(value) {
    return String(value).padStart(2, '0');
  }

  function formatDate(date) {
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
  }

  function parseDate(value) {
    var parts = String(value || '').split('-').map(Number);

    if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) {
      return null;
    }

    return new Date(parts[0], parts[1] - 1, parts[2]);
  }

  function displayDate(date) {
    if (!date || !window.jQuery || !window.jQuery.datepicker) {
      return date ? formatDate(date) : '';
    }

    return window.jQuery.datepicker.formatDate(dateRangeConfig.displayFormat || 'yy-mm-dd', date);
  }

  function parseDisplayDate(value) {
    if (window.jQuery && window.jQuery.datepicker) {
      try {
        return window.jQuery.datepicker.parseDate(dateRangeConfig.displayFormat || 'yy-mm-dd', value);
      } catch (error) {
        return parseDate(value);
      }
    }

    return parseDate(value);
  }

  function addDays(date, days) {
    var next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getStandardRangeDates() {
    var today = new Date();
    var firstDay = typeof dateRangeConfig.firstDay === 'number' ? dateRangeConfig.firstDay : 1;
    var offset = (today.getDay() - firstDay + 7) % 7;
    var weekStart = addDays(today, -offset);
    var lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);

    return {
      today: [today, today],
      yesterday: [addDays(today, -1), addDays(today, -1)],
      this_week: [weekStart, today],
      last_7: [addDays(today, -6), today],
      last_30: [addDays(today, -29), today],
      this_month: [new Date(today.getFullYear(), today.getMonth(), 1), today],
      last_month: [lastMonth, new Date(today.getFullYear(), today.getMonth(), 0)]
    };
  }

  function applyPresetRange(form, rangeKey) {
    var dates = getStandardRangeDates()[rangeKey];
    var rangeInput = form.querySelector('input[name="range"]');
    var startInput = form.querySelector('input[name="start_date"]');
    var endInput = form.querySelector('input[name="end_date"]');

    if (!dates) {
      return false;
    }

    if (rangeInput) {
      rangeInput.value = rangeKey;
    }

    if (startInput) {
      startInput.value = formatDate(dates[0]);
    }

    if (endInput) {
      endInput.value = formatDate(dates[1]);
    }

    return true;
  }

  function closeDateRangePopover(popover) {
    if (!popover) {
      return;
    }

    popover.hidden = true;
  }

  function positionDateRangePopover(trigger, popover) {
    var rect = trigger.getBoundingClientRect();
    var viewportPadding = 16;
    var viewportWidth = document.documentElement.clientWidth || window.innerWidth;
    var popoverWidth = popover.offsetWidth || 440;
    var minLeft = window.scrollX + viewportPadding;
    var maxLeft = window.scrollX + viewportWidth - popoverWidth - viewportPadding;
    var preferredLeft = window.scrollX + rect.right - popoverWidth;
    var left = Math.min(Math.max(preferredLeft, minLeft), Math.max(minLeft, maxLeft));

    popover.style.top = window.scrollY + rect.bottom + 8 + 'px';
    popover.style.left = left + 'px';

    var placedRect = popover.getBoundingClientRect();
    var overflowRight = placedRect.right - (viewportWidth - viewportPadding);

    if (overflowRight > 0) {
      popover.style.left = Math.max(minLeft, left - overflowRight) + 'px';
    }
  }

  function getDateRangeForm(trigger) {
    return trigger.closest('form') || document.querySelector('.rp-reports-intelligence-filter');
  }

  function syncDateRangePickerLimits(form, changedField) {
    var startField = form.querySelector('.rp-date-range-start');
    var endField = form.querySelector('.rp-date-range-end');
    var startDate = startField && parseDisplayDate(startField.value);
    var endDate = endField && parseDisplayDate(endField.value);

    if (!startField || !endField) {
      return;
    }

    if (changedField === startField && startDate && endDate && startDate > endDate) {
      endField.value = displayDate(startDate);
      endDate = startDate;
    }

    if (changedField === endField && startDate && endDate && startDate > endDate) {
      startField.value = displayDate(endDate);
      startDate = endDate;
    }

  }

  function openDateRangePopover(trigger) {
    var form = getDateRangeForm(trigger);
    var popover = form && form.querySelector('.rp-date-range-popover');
    var startInput = form && form.querySelector('input[name="start_date"]');
    var endInput = form && form.querySelector('input[name="end_date"]');

    if (!popover || !startInput || !endInput) {
      return;
    }

    popover.querySelector('.rp-date-range-start').value = displayDate(parseDate(startInput.value));
    popover.querySelector('.rp-date-range-end').value = displayDate(parseDate(endInput.value));
    popover.hidden = false;
    popover.style.visibility = 'hidden';
    syncDateRangePickerLimits(form);
    positionDateRangePopover(trigger, popover);
    popover.style.visibility = '';
  }

  function initDateRangePopover(form) {
    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.datepicker || form.dataset.rpDateRangeReady) {
      return;
    }

    form.dataset.rpDateRangeReady = '1';
    form.insertAdjacentHTML('beforeend',
      '<div class="rp-date-range-popover" hidden>' +
        '<div class="rp-date-range-popover-fields">' +
          '<label><span>' + escapeHtml(dateRangeConfig.startLabel || 'Start') + '</span><input type="text" class="rp-input rp-date-range-start" autocomplete="off" placeholder="' + escapeHtml(dateRangeConfig.datePlaceholder || '') + '"></label>' +
          '<label><span>' + escapeHtml(dateRangeConfig.endLabel || 'End') + '</span><input type="text" class="rp-input rp-date-range-end" autocomplete="off" placeholder="' + escapeHtml(dateRangeConfig.datePlaceholder || '') + '"></label>' +
        '</div>' +
        '<div class="rp-date-range-popover-actions">' +
          '<button type="button" class="button rp-btn rp-btn-secondary rp-date-range-cancel">' + escapeHtml(dateRangeConfig.cancelLabel || 'Cancel') + '</button>' +
          '<button type="button" class="button button-primary rp-btn rp-btn-primary rp-date-range-apply">' + escapeHtml(dateRangeConfig.applyLabel || 'Apply') + '</button>' +
        '</div>' +
      '</div>'
    );

    window.jQuery(form.querySelectorAll('.rp-date-range-start, .rp-date-range-end')).datepicker({
      dateFormat: dateRangeConfig.displayFormat || 'yy-mm-dd',
      changeMonth: true,
      changeYear: true,
      yearRange: '-10:+10',
      firstDay: typeof dateRangeConfig.firstDay === 'number' ? dateRangeConfig.firstDay : 1,
      onSelect: function () {
        syncDateRangePickerLimits(form, this);
      }
    }).on('focus click', function () {
      syncDateRangePickerLimits(form);
      window.jQuery(this).datepicker('show');
    }).on('change', function () {
      syncDateRangePickerLimits(form, this);
    });

    form.querySelectorAll('.rp-date-range-start, .rp-date-range-end').forEach(function (field) {
      field.addEventListener('input', function () {
        syncDateRangePickerLimits(form, field);
      });

      field.addEventListener('change', function () {
        syncDateRangePickerLimits(form, field);
      });

      field.addEventListener('blur', function () {
        syncDateRangePickerLimits(form, field);
      });
    });

  }

  function initDateRangePopovers() {
    document.querySelectorAll('.rp-reports-intelligence-filter').forEach(initDateRangePopover);
  }

  initDateRangePopovers();
  window.setTimeout(initDateRangePopovers, 250);
  window.addEventListener('load', initDateRangePopovers);

  function syncReportsDateFields(form) {
    var range = form.querySelector('[name="range"]');
    var isCustom = range && range.value === 'custom';
    var customFields = form.querySelectorAll('.rp-reports-custom-date, .rp-reports-custom-apply');

    customFields.forEach(function (field) {
      field.classList.toggle('is-active', !!isCustom);
      field.hidden = !isCustom;
    });
  }

  document.querySelectorAll('.rp-reports-intelligence-filter').forEach(syncReportsDateFields);

  document.addEventListener('change', function (event) {
    var field = event.target;
    var form = field && field.closest ? field.closest('.rp-reports-intelligence-filter') : null;

    if (!form || field.disabled) {
      return;
    }

    syncReportsDateFields(form);

    if (field.classList.contains('rp-date-range-preset')) {
      if (field.value === 'custom') {
        var hiddenRange = form.querySelector('input[name="range"]');

        if (hiddenRange) {
          hiddenRange.value = 'custom';
        }

        openDateRangePopover(field);
      } else if (applyPresetRange(form, field.value)) {
        form.submit();
      }

      return;
    }

    if (field.name === 'range' && field.value === 'custom') {
      return;
    }

    if ((field.name === 'start_date' || field.name === 'end_date') && form.querySelector('[name="range"]').value === 'custom') {
      return;
    }

    form.submit();
  });

  document.addEventListener('click', function (event) {
    var edit = event.target.closest('.rp-date-range-edit');
    var cancel = event.target.closest('.rp-date-range-cancel');
    var apply = event.target.closest('.rp-date-range-apply');

    if (edit) {
      var editForm = getDateRangeForm(edit);
      var editRange = editForm && editForm.querySelector('input[name="range"]');
      var editSelect = editForm && editForm.querySelector('.rp-date-range-preset');

      if (editRange) {
        editRange.value = 'custom';
      }

      if (editSelect) {
        editSelect.value = 'custom';
      }

      openDateRangePopover(edit);
      return;
    }

    if (cancel) {
      var cancelForm = cancel.closest('form');
      var cancelRange = cancelForm.querySelector('input[name="range"]');
      var cancelSelect = cancelForm.querySelector('.rp-date-range-preset');

      if (cancelSelect && cancelRange) {
        cancelSelect.value = cancelRange.value;
      }

      closeDateRangePopover(cancel.closest('.rp-date-range-popover'));
      return;
    }

    if (apply) {
      var form = apply.closest('form');
      var picker = apply.closest('.rp-date-range-popover');
      var rangeInput = form.querySelector('input[name="range"]');
      var startInput = form.querySelector('input[name="start_date"]');
      var endInput = form.querySelector('input[name="end_date"]');
      var startDate = parseDisplayDate(picker.querySelector('.rp-date-range-start').value);
      var endDate = parseDisplayDate(picker.querySelector('.rp-date-range-end').value);

      if (!startDate || !endDate) {
        return;
      }

      if (startDate > endDate) {
        var tmp = startDate;
        startDate = endDate;
        endDate = tmp;
      }

      rangeInput.value = 'custom';
      startInput.value = formatDate(startDate);
      endInput.value = formatDate(endDate);
      form.submit();
    }
  });
}());
