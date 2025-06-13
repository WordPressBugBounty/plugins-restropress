<?php
/**
 * Template: Food Category
 *
 * This template displays the food category header and description in the food menu.
 *
 * @package RestroPress/Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

global $curr_cat_var;
global $fooditem_term_slug;
global $rpress_fooditem_id;
$class = ($curr_cat_var == $fooditem_term_slug )? 'rpress-same-cat' : 'rpress-different-cat';
$curr_cat_var = $fooditem_term_slug;
$food_category = get_term_by( 'slug', $fooditem_term_slug, 'food-category' );
if( $class == 'rpress-different-cat' ) : ?>
<div class="rpress-element-title" id="menu-category-<?php echo esc_attr( $food_category->term_id ); ?>" data-term-id="<?php echo esc_attr( $food_category->term_id ); ?>">
  <div class="menu-category-wrap" data-cat-id="<?php echo esc_attr( $fooditem_term_slug ); ?>">
    <div class="menu-category-wrap" data-cat-id="<?php echo esc_attr( $fooditem_term_slug ); ?>">
      <h5 class="rpress-cat rpress-different-cat"><?php echo wp_kses_post( $food_category->name ); ?></h5>
        <?php if( !empty( $food_category->description ) ) : ?>
          <span><?php echo wp_kses_post( $food_category->description ); ?></span>
        <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>