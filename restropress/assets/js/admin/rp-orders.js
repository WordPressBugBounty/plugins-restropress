jQuery(function ($) {
  /**
   * RPOrdersTable class.
   */
  var RPOrdersTable = function () {
    $(document)
      .on('click', '.order-preview:not(.disabled)', this.onPreview);
  };
  /**
   * Order status change by dropdown.
   *
   * Updates the order status via AJAX without reloading the page. The PHP
   * handler returns `force_reload: true` when an extension has explicitly
   * opted into the legacy reload behaviour via the
   * `rpress_status_change_force_reload` filter -- in that case we still
   * honour it for backward compatibility.
   */
  jQuery(document)
    .on('change', '#rpress-payments-filter .rp_order_status', function (e) {
      e.preventDefault();
      var _self = jQuery(this);
      var selectedStatus = _self.val();
      var currentStatus = _self.attr('data-current-status');
      var payment_id = _self.attr('data-payment-id');
      if (selectedStatus === '') {
        return;
      }
      // Optimistic UI: swap classes + data attr before the request fires.
      _self.removeClass('rp_current_status_' + currentStatus);
      _self.addClass('rp_current_status_' + selectedStatus);
      _self.removeClass('status-' + currentStatus).addClass('status-' + selectedStatus);
      _self.closest('.rp-status-select-wrap').removeClass('status-' + currentStatus).addClass('status-' + selectedStatus);
      _self.attr('data-current-status', selectedStatus);
      var $cell = _self.closest('td');
      var $row  = _self.closest('tr');
      $cell.find('.order-status-loading').addClass('disabled');

      $.ajax({
        url: rp_orders_params.ajax_url,
        data: {
          payment_id: payment_id,
          status: selectedStatus,
          action: 'rpress_update_order_status',
          security: rp_orders_params.order_nonce
        },
        type: 'GET',
        dataType: 'JSON',
        success: function (response) {
          $cell.find('.order-status-loading').removeClass('disabled');
          if (!response) {
            return;
          }
          // Extension escape hatch -- only reload if the server explicitly says so.
          if (response.force_reload && response.redirect) {
            window.location.href = response.redirect;
            return;
	          }
	          // Brief saved-state flash on the row.
	          $row.addClass('rp-status-saved');
	          window.setTimeout(function () {
	            $row.removeClass('rp-status-saved');
	          }, 1200);
	          jQuery(document).trigger('rpress:orderStatusChanged', [{
	            paymentId: payment_id,
	            status: selectedStatus,
	            statusLabel: response.status_label || selectedStatus
	          }]);
	        },
        error: function () {
          // Revert optimistic UI if the request failed.
          $cell.find('.order-status-loading').removeClass('disabled');
          _self.removeClass('rp_current_status_' + selectedStatus);
          _self.addClass('rp_current_status_' + currentStatus);
          _self.removeClass('status-' + selectedStatus).addClass('status-' + currentStatus);
          _self.closest('.rp-status-select-wrap').removeClass('status-' + selectedStatus).addClass('status-' + currentStatus);
          _self.attr('data-current-status', currentStatus);
          _self.val(currentStatus);
          $row.addClass('rp-status-error');
          window.setTimeout(function () {
            $row.removeClass('rp-status-error');
          }, 2000);
        }
      });
    });
  /**
   * Status pill - open/close popover.
   */
  jQuery(document)
    .on('click', '.rp-status-pill-button:not(.is-disabled)', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var $btn = jQuery(this);
      var $wrap = $btn.closest('.rp-status-pill-wrap');
      var $popover = $wrap.find('.rp-status-pill-popover');
      var isOpen = !$popover.prop('hidden');
      // Close every other open popover.
      jQuery('.rp-status-pill-popover').prop('hidden', true);
      jQuery('.rp-status-pill-button').attr('aria-expanded', 'false');
      // Toggle this one.
      $popover.prop('hidden', isOpen);
      $btn.attr('aria-expanded', isOpen ? 'false' : 'true');
    });

  // Close popovers when clicking anywhere else.
  jQuery(document).on('click', function (e) {
    if (!jQuery(e.target).closest('.rp-status-pill-wrap').length) {
      jQuery('.rp-status-pill-popover').prop('hidden', true);
      jQuery('.rp-status-pill-button').attr('aria-expanded', 'false');
    }
  });

  // Close with Escape.
  jQuery(document).on('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      jQuery('.rp-status-pill-popover').prop('hidden', true);
      jQuery('.rp-status-pill-button').attr('aria-expanded', 'false');
    }
  });

  /**
   * Status pill - selecting an option fires the same AJAX as the legacy
   * <select> change handler above, but updates the pill UI in place.
   */
  jQuery(document)
    .on('click', '.rp-status-pill-option', function (e) {
      e.preventDefault();
      var $option = jQuery(this);
      var $wrap = $option.closest('.rp-status-pill-wrap');
      var $btn = $wrap.find('.rp-status-pill-button');
      var newStatus = $option.data('status');
      var newLabel = $option.find('.rp-status-pill-option-label').text();
      var currentStatus = $btn.attr('data-current-status');
      var paymentId = $btn.attr('data-payment-id');
      // Close popover first, regardless of outcome.
      $wrap.find('.rp-status-pill-popover').prop('hidden', true);
      $btn.attr('aria-expanded', 'false');
      if (newStatus === currentStatus) {
        return;
      }
      // Optimistic UI: swap classes, label, dot color before request fires.
      $btn.removeClass('status-' + currentStatus).addClass('status-' + newStatus);
      $btn.attr('data-current-status', newStatus);
      $btn.find('.rp-status-pill-label').text(newLabel);
      // Update the "current" marker in the popover for next open.
      $wrap.find('.rp-status-pill-option').each(function () {
        var $opt = jQuery(this);
        var isCurrent = ($opt.data('status') === newStatus);
        $opt.toggleClass('is-current', isCurrent).attr('aria-selected', isCurrent ? 'true' : 'false');
      });
      var $cell = $btn.closest('td');
      var $row  = $btn.closest('tr');
      $cell.find('.order-status-loading').addClass('disabled');

      jQuery.ajax({
        url: rp_orders_params.ajax_url,
        data: {
          payment_id: paymentId,
          status: newStatus,
          action: 'rpress_update_order_status',
          security: rp_orders_params.order_nonce
        },
        type: 'GET',
        dataType: 'JSON',
        success: function (response) {
          $cell.find('.order-status-loading').removeClass('disabled');
          if (!response) {
            return;
          }
          if (response.force_reload && response.redirect) {
            window.location.href = response.redirect;
            return;
          }
	          $row.addClass('rp-status-saved');
	          window.setTimeout(function () {
	            $row.removeClass('rp-status-saved');
	          }, 1200);
	          jQuery(document).trigger('rpress:orderStatusChanged', [{
	            paymentId: paymentId,
	            status: newStatus,
	            statusLabel: response.status_label || newLabel
	          }]);
	        },
        error: function () {
          // Revert optimistic UI on failure.
          $cell.find('.order-status-loading').removeClass('disabled');
          $btn.removeClass('status-' + newStatus).addClass('status-' + currentStatus);
          $btn.attr('data-current-status', currentStatus);
          $btn.find('.rp-status-pill-label').text(
            $wrap.find('.rp-status-pill-option[data-status="' + currentStatus + '"] .rp-status-pill-option-label').text()
          );
          $wrap.find('.rp-status-pill-option').each(function () {
            var $opt = jQuery(this);
            var isCurrent = ($opt.data('status') === currentStatus);
            $opt.toggleClass('is-current', isCurrent).attr('aria-selected', isCurrent ? 'true' : 'false');
          });
          $row.addClass('rp-status-error');
          window.setTimeout(function () {
            $row.removeClass('rp-status-error');
          }, 2000);
        }
      });
    });

  /**
   * Preview an order
   */
  RPOrdersTable.prototype.onPreview = function () {
    var $previewButton = $(this),
      $order_id = $previewButton.data('order-id');
    if ($previewButton.data('order-data')) {
      $(this)
        .RPBackboneModal({
          template: 'rp-modal-view-order',
          variable: $previewButton.data('order-data')
        });
    } else {
      $previewButton.addClass('disabled');
      $.ajax({
        url: rp_orders_params.ajax_url,
        data: {
          order_id: $order_id,
          action: 'rpress_get_order_details',
          security: rp_orders_params.preview_nonce
        },
        type: 'GET',
        success: function (response) {
          $('.order-preview')
            .removeClass('disabled');
	          if (response.success) {
	            $previewButton.data('order-data', response.data);
	            $previewButton
	              .RPBackboneModal({
	                template: 'rp-modal-view-order',
	                variable: response.data
              });
          }
        }
      });
    }
    return false;
  };
  /**
   * Phase E - live "time since" labels next to absolute dates on the list view.
   * Picks up <span class="rp-time-since" data-timestamp="UNIX"> and rewrites
   * its text every 60s so admins watching the page see fresh values.
   */
  function renderTimeSince() {
    var nowSec = Math.floor(Date.now() / 1000);
    jQuery('.rp-time-since[data-timestamp]').each(function () {
      var $el = jQuery(this);
      var ts = parseInt($el.attr('data-timestamp'), 10) || 0;
      if (!ts) return;
      var diff = nowSec - ts;
      var label;
      if (diff < 60) {
        label = 'just now';
      } else if (diff < 3600) {
        label = Math.floor(diff / 60) + 'm ago';
      } else if (diff < 86400) {
        label = Math.floor(diff / 3600) + 'h ago';
      } else {
        label = Math.floor(diff / 86400) + 'd ago';
      }
      $el.text(label);
    });
  }
  renderTimeSince();
  window.setInterval(renderTimeSince, 60 * 1000);

  /**
   * Phase E - confirm destructive bulk actions before they submit.
   */
  jQuery('#rpress-payments-filter').on('submit', function (e) {
    var $form = jQuery(this);
    var $topSelect = $form.find('select[name="action"]');
    var $bottomSelect = $form.find('select[name="action2"]');
    var action = '';
    if ($topSelect.length && $topSelect.val() && $topSelect.val() !== '-1') {
      action = $topSelect.val();
    } else if ($bottomSelect.length && $bottomSelect.val() && $bottomSelect.val() !== '-1') {
      action = $bottomSelect.val();
    }
    var destructive = {
      'delete':                       'permanently delete the selected orders',
      'trash':                        'move the selected orders to Trash',
      'set-payment-status-refunded':  'mark the selected payments as Refunded',
      'set-payment-status-failed':    'mark the selected payments as Failed'
    };
    if (destructive[action]) {
      var checked = $form.find('input[name="payment[]"]:checked').length;
      if (checked === 0) return; // WP's own warning fires
      var msg = 'Are you sure you want to ' + destructive[action] + '? (' + checked + ' selected)';
      if (!window.confirm(msg)) {
        e.preventDefault();
        return false;
      }
    }
  });

  /**
   * Quick-view modal - advance order status without leaving the modal.
   */
  jQuery(document).on('click', '.rp-modal-status-action', function (e) {
    e.preventDefault();
    var $btn = jQuery(this);
    var paymentId = $btn.attr('data-payment-id');
    var currentStatus = $btn.attr('data-current-status');
    var nextStatus = $btn.attr('data-next-status');
    var originalText = $btn.text();

    if (!paymentId || !nextStatus) {
      return;
    }

    $btn.prop('disabled', true).addClass('is-loading').text('Updating...');

    jQuery.ajax({
      url: rp_orders_params.ajax_url,
      data: {
        payment_id: paymentId,
        status: nextStatus,
        action: 'rpress_update_order_status',
        security: rp_orders_params.order_nonce
      },
      type: 'GET',
      dataType: 'JSON',
      success: function (response) {
        if (!response || !response.success) {
          $btn.prop('disabled', false).removeClass('is-loading').text(originalText);
          return;
        }

        var label = response.status_label || nextStatus;
        var $modal = $btn.closest('.rp-backbone-modal');
        var $badge = $modal.find('.order-status');
        $badge.removeClass('status-' + currentStatus).addClass('status-' + nextStatus);
        $badge.find('span').text(label);

        var $rowSelect = jQuery('#rpress-order-status-' + paymentId);
        if ($rowSelect.length) {
          $rowSelect.val(nextStatus).attr('data-current-status', nextStatus);
          $rowSelect.removeClass('status-' + currentStatus).addClass('status-' + nextStatus);
          $rowSelect.closest('.rp-status-select-wrap').removeClass('status-' + currentStatus).addClass('status-' + nextStatus);
          $rowSelect.closest('tr').addClass('rp-status-saved');
          window.setTimeout(function () {
            $rowSelect.closest('tr').removeClass('rp-status-saved');
          }, 1200);
        }

        var nextMap = {
          pending: { status: 'accepted', label: 'Accept order' },
          accepted: { status: 'processing', label: 'Start preparing' },
          processing: { status: 'ready', label: 'Mark ready' },
          ready: { status: 'completed', label: 'Complete order' },
          transit: { status: 'completed', label: 'Complete order' }
        };
        var next = nextMap[nextStatus];
	        jQuery('.order-preview[data-order-id="' + paymentId + '"]').each(function () {
	          var $preview = jQuery(this);
	          var cached = $preview.data('order-data');
          if (cached) {
            cached.status = nextStatus;
            cached.status_label = label;
            cached.next_status_action = next || {};
	            $preview.data('order-data', cached);
	          }
	        });
	        jQuery(document).trigger('rpress:orderStatusChanged', [{
	          paymentId: paymentId,
	          status: nextStatus,
	          statusLabel: label
	        }]);
	        if (next) {
          $btn.attr('data-current-status', nextStatus).attr('data-next-status', next.status).text(next.label);
          $btn.prop('disabled', false).removeClass('is-loading');
        } else {
          $btn.remove();
        }
      },
      error: function () {
        $btn.prop('disabled', false).removeClass('is-loading').text(originalText);
      }
    });
  });

  /**
   * Init RPOrdersTable.
   */
  new RPOrdersTable();
});
