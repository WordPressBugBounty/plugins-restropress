<?php
/**
 * Modern pack: list row image.
 *
 * Override of templates/list/content-image.php. The Modern list shows a 116px
 * (96px mobile) rounded thumbnail; `medium` (300) keeps it crisp on retina
 * where the core 150px `thumbnail` softens. Markup mirrors core.
 *
 * @package RestroPress/Templates/Packs/Modern
 * @version 3.4.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="rpress-thumbnail-holder rpress-default-bg rpress-icon-bg">
  <?php
  $fooditems_overlay = rpress_get_option( 'enable_food_image_popup', false );
  $image_placeholder = rpress_get_option( 'enable_image_placeholder', false );
  if ( has_post_thumbnail( $post->ID ) ) :
    $image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'full' );
    ?>
    <?php if ( $fooditems_overlay == 1 ) : ?>
      <a href="<?php echo esc_url( $image[0] ); ?>" class="rpress-thumbnail-popup">
        <?php echo get_the_post_thumbnail( get_the_ID(), 'medium' ); ?>
      </a>
    <?php else : ?>
      <?php echo get_the_post_thumbnail( get_the_ID(), 'medium' ); ?>
    <?php endif; ?>
  <?php elseif ( $image_placeholder == 1 ) : ?>
    <?php $image_src = RP_PLUGIN_URL . 'assets/images/no-image.png'; ?>
    <img src="<?php echo esc_url( $image_src ); ?>" alt="" />
  <?php endif; ?>
</div>
