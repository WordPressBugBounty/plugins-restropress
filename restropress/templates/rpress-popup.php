<?php
$button_style = rpress_get_option('button_style', 'button');
?>
<div class="modal micromodal-slide addon-popup-wrap" id="rpressModal" aria-hidden="true">
  <div class="modal__overlay" tabindex="-1" data-micromodal-close>
    <div class="modal__container modal-content" role="dialog" aria-modal="true">
    <header class="modal__header">
          <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
        <h2 class="modal__title modal-title"></h2>
    </header>
      <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12"> 
        <div class="rp-row">
          <!-- <div class="rp-col-lg-6 rp-col-md-6 rp-col-sm-6 rp-col-xs-12">          
          </div> -->
          <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
            <div class="row addon-modal-overlap-bg">
              <div class="modal__image-section">
                  <img class="item-image" src="">
                  <p class="item-description"></p>
              </div>
              <main class="modal__content modal-body">             
              </main>
            </div>
          </div>
        </div>
      </div>
      <footer class="modal__footer rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
        <div class="rpress-popup-actions rp-row">
          <div class="rp-col-md-4 rp-col-xs-4">
            <div class="btn-count">
              <div class="qtyminus-wrap">
                <input type="button" value="&#8722;" class="qtyminus qtyminus-style qtyminus-style-edit">
              </div>
              <div class="qty-num-wrap">
                <input type="text" name="quantity" value="1" class="qty qty-style" readonly>
              </div>
              <div class="qtyplus-wrap">
                <input type="button" value="&#43;" class="qtyplus qtyplus-style qtyplus-style-edit">
              </div>
            </div>
          </div>
          <div class="rp-col-md-8 rp-col-xs-8">
            <a href="javascript:void(0);" data-title="" data-item-qty="1" data-cart-key="" data-item-id=""
              data-variable-id="" data-item-price="" data-cart-action=""
              class="center submit-fooditem-button <?php echo esc_attr($button_style); ?> text-center inline rp-col-md-6">
              <span class="cart-action-text rp-ajax-toggle-text"></span>
              <span class="cart-item-price"></span>
            </a>
          </div>
        </div>
      </footer>
    </div>
  </div>
</div>