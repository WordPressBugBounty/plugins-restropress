jQuery(function ($) {
    $(document).on('click', '.rpress-reorder-btn', function (e) {
        e.preventDefault();
        var self = $(this);
        var $modal = $('#rpressModal');
        if (!$modal.length) {
          $('body').append('<div class="modal micromodal-slide" id="rpressModal" aria-hidden="true"><div class="modal__overlay" tabindex="-1" data-micromodal-close><div class="modal__container modal-content" role="dialog" aria-modal="true"><header class="modal__header modal-header"><h2 class="modal__title modal-title"></h2><button class="modal__close" aria-label="Close modal" data-micromodal-close></button></header><main class="modal__content modal-body"></main></div></div></div>');
          $modal = $('#rpressModal');
        }
        if (!$modal.length) {
          return;
        }
        var action = 'rpress_reorder';
        var order_id = self.attr('data-order-id');
        var data = {
          action: action,
          order_id: order_id,
          security: rp_scripts.order_details_nonce
        };
        
        $modal
          .addClass('rpress-order-details-context')
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
            if (!response || response.success !== true || !response.data || typeof response.data.html !== 'string') {
              return;
            }
            $modal.find('.modal__container')
              .html(response.data.html);
            if (typeof MicroModal !== 'undefined' && typeof MicroModal.show === 'function') {
              MicroModal.show('rpressModal');
            } else {
              $modal
                .addClass('is-open')
                .attr('aria-hidden', 'false');
              $('html, body')
                .addClass('modal-open')
                .css('overflow', 'hidden');
            }
          },
          error: function () {
            $modal.removeClass('show-order-details rpress-order-details-context');
          }
        })
    });
});
