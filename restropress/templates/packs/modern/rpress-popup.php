<?php
/**
 * Modern pack: item customization modal shell.
 *
 * Structural override of templates/rpress-popup.php per the Modern design
 * handoff: hero photo on top with the close button overlapping it, then the
 * title row, description, option groups, and the sticky footer. The frontend
 * JS fills the same classes (.modal-title, .item-image, .item-description,
 * .modal__content) so behavior is untouched.
 *
 * @package RestroPress/Templates/Packs/Modern
 * @version 3.4.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$button_style = rpress_get_option( 'button_style', 'button' );
?>
<div class="modal micromodal-slide addon-popup-wrap rp-modern-modal" id="rpressModal" aria-hidden="true">
  <div class="modal__overlay" tabindex="-1" data-micromodal-close>
    <div class="modal__container modal-content" role="dialog" aria-modal="true">
      <div class="rp-modern-hero">
        <img class="item-image" alt="" hidden />
        <button class="modal__close" aria-label="<?php esc_attr_e( 'Close modal', 'restropress' ); ?>" data-micromodal-close></button>
      </div>
      <header class="modal__header">
        <h2 class="modal__title modal-title"></h2>
      </header>
      <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
        <div class="rp-row">
          <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
            <div class="row addon-modal-overlap-bg">
              <div class="modal__image-section">
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
                <input type="button" value="&#8722;" class="qtyminus qtyminus-style qtyminus-style-edit" aria-label="<?php esc_attr_e( 'Decrease quantity', 'restropress' ); ?>">
              </div>
              <div class="qty-num-wrap">
                <input type="text" name="quantity" value="1" class="qty qty-style" readonly aria-label="<?php esc_attr_e( 'Quantity', 'restropress' ); ?>">
              </div>
              <div class="qtyplus-wrap">
                <input type="button" value="&#43;" class="qtyplus qtyplus-style qtyplus-style-edit" aria-label="<?php esc_attr_e( 'Increase quantity', 'restropress' ); ?>">
              </div>
            </div>
          </div>
          <div class="rp-col-md-8 rp-col-xs-8 rpress-popup-submit-wrap">
            <a href="javascript:void(0);" data-title="" data-item-qty="1" data-cart-key="" data-item-id=""
              data-variable-id="" data-item-price="" data-cart-action=""
              class="center submit-fooditem-button <?php echo esc_attr( $button_style ); ?> text-center inline rp-col-md-6">
              <span class="cart-action-text rp-ajax-toggle-text"></span>
              <span class="cart-item-price"></span>
            </a>
          </div>
        </div>
      </footer>
    </div>
  </div>
</div>
