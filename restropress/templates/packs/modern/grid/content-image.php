<?php
/**
 * Modern pack: grid card image.
 *
 * Override of templates/grid/content-image.php. The Modern grid shows the photo
 * as a large full-bleed hero (~380px, more on retina), so the core 150px
 * `thumbnail` renders blurry. Request `large` (1024); the browser still picks a
 * smaller srcset candidate on small/1x displays. Markup mirrors core.
 *
 * @package RestroPress/Templates/Packs/Modern
 * @version 3.4.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fooditems_overlay = rpress_get_option( 'enable_food_image_popup', false );
$image_placeholder = rpress_get_option( 'enable_image_placeholder', false );
if ( has_post_thumbnail( $post->ID ) ) :
	$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'full' );
	?>
  <div class="rpress-thumbnail-holder rpress-bg rpress-icon-bg">
    <?php if ( $fooditems_overlay == 1 ) : ?>
      <a href="<?php echo esc_url( $image[0] ); ?>" class="rpress-thumbnail-popup">
          <?php echo get_the_post_thumbnail( get_the_ID(), 'large' ); ?>
      </a>
    <?php else : ?>
      <?php echo get_the_post_thumbnail( get_the_ID(), 'large' ); ?>
    <?php endif; ?>
  </div>
<?php elseif ( $image_placeholder == 1 ) : ?>
    <?php $image_src = RP_PLUGIN_URL . 'assets/images/no-image.png'; ?>
    <div class="rpress-thumbnail-holder rpress-default-bg rpress-icon-bg">
        <img src="<?php echo esc_url( $image_src ); ?>" alt=""/>
    </div>
<?php endif; ?>
