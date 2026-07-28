<?php
/**
 * Modern pack: list dish row (ListCard design handoff).
 *
 * Structural override of templates/list/single.php. Rebuilds the content
 * column in the mockup order: [veg dot + name] row, description, badge row
 * (tags then dietary, styled distinctly), price; with the photo + add button
 * on the right. Reuses rpress_get_purchase_link() so the cart flow and the
 * shortcode/scroll-spy classes are untouched.
 *
 * @package RestroPress/Templates/Packs/Modern
 * @version 3.4.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $rpress_fooditem_shortcode_item_atts, $rpress_fooditem_shortcode_item_i;

$id        = get_the_ID();
$schema    = rpress_add_schema_microdata() ? 'itemscope itemtype="http://schema.org/Product" ' : '';
$post_terms = wp_get_post_terms( $id, 'food-category' );
$term_id   = ( ! is_wp_error( $post_terms ) && ! empty( $post_terms ) ) ? $post_terms[0]->term_taxonomy_id : 0;
$has_photo = has_post_thumbnail( $id );
$available = rpress_fooditem_available( $id );
$food_type = get_post_meta( $id, 'rpress_food_type', true );

$tags = get_the_terms( $id, 'fooditem_tag' );
$tags = ( $tags && ! is_wp_error( $tags ) ) ? $tags : array();
$show_tags = ! empty( rpress_get_option( 'enable_tags_display', false ) ) && ! empty( $tags );

$dietary_html = function_exists( 'rpress_get_dietary_labels_html' ) ? rpress_get_dietary_labels_html( $id ) : '';

$card_classes = array( 'rpress_fooditem', 'rpress-list' );
if ( $show_tags ) {
	$card_classes[] = 'has-tags';
}
if ( ! $available ) {
	$card_classes[] = 'product_not_available';
}
$card_classes[] = apply_filters( 'rpress_fooditem_class', 'rpress_fooditem', $id, $rpress_fooditem_shortcode_item_atts, $rpress_fooditem_shortcode_item_i );
$card_classes   = array_unique( array_filter( array_map( 'sanitize_html_class', $card_classes ) ) );

// The image placeholder graphic renders inside the photo column, so a card with
// the placeholder enabled still has an image to show and must not be flagged
// rp-no-img (which drops the photo column).
$image_placeholder = rpress_get_option( 'enable_image_placeholder', false );
$inner_classes = trim( ( ( $has_photo || $image_placeholder ) ? '' : 'rp-no-img ' ) . apply_filters( 'rpress_fooditem_inner_class', 'rpress_fooditem_inner', $id, $rpress_fooditem_shortcode_item_atts, $rpress_fooditem_shortcode_item_i ) );

$purchase_link_kses = array(
	'form'  => array( 'id' => true, 'class' => true, 'method' => true, 'action' => true ),
	'div'   => array( 'class' => true ),
	'a'     => array( 'href' => true, 'class' => true, 'data-title' => true, 'data-action' => true, 'data-fooditem-id' => true, 'data-variable-price' => true, 'data-price' => true, 'data-price-mode' => true, 'style' => true ),
	'span'  => array( 'class' => true ),
	'svg'   => array( 'xmlns' => true, 'width' => true, 'height' => true, 'viewbox' => true ),
	'path'  => array( 'd' => true ),
	'input' => array( 'type' => true, 'name' => true, 'class' => true, 'value' => true, 'id' => true ),
);
?>
<div <?php echo esc_html( $schema ); ?>class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>" data-term-id="<?php echo esc_attr( $term_id ); ?>" id="rpress_fooditem_<?php the_ID(); ?>">
	<div class="<?php echo esc_attr( $inner_classes ); ?>">
		<?php do_action( 'rpress_fooditem_before' ); ?>
		<div class="rp-col-md-8 rp-col-sm-8 rp-col-xs-8 rp-grid-view-wrap">
			<div class="rpress-title-holder">
				<div class="rp-name-row">
					<?php if ( 'veg' === $food_type ) : ?>
						<span class="vegbg"><span class="veg_sub"></span></span>
					<?php elseif ( 'non_veg' === $food_type ) : ?>
						<span class="non_vegbg"><span class="non_vegsub"></span></span>
					<?php endif; ?>
					<h3 class="rpress_fooditem_title"><span class="food-title"><?php the_title(); ?></span></h3>
				</div>

				<?php
				$excerpt_length = apply_filters( 'excerpt_length', 40 );
				if ( has_excerpt() ) {
					$description = get_post_field( 'post_excerpt', $id );
					echo '<div class="rpress_fooditem_excerpt">' . wp_kses( apply_filters( 'rpress_fooditems_excerpt', wp_trim_words( $description, $excerpt_length ), $description ), array( 'p' => array() ) ) . '</div>';
				} elseif ( get_the_content() ) {
					$description = get_post_field( 'post_content', $id );
					echo '<div class="rpress_fooditem_excerpt">' . wp_kses( apply_filters( 'rpress_fooditems_content', wp_trim_words( $description, $excerpt_length ), $description ), array( 'p' => array() ) ) . '</div>';
				}
				?>

				<?php if ( $show_tags || $dietary_html ) : ?>
					<div class="rp-badge-row">
						<?php if ( $show_tags ) : ?>
							<div class="rpress_fooditem_tags">
								<?php foreach ( $tags as $term ) : ?>
									<span class="fooditem_tag <?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php echo wp_kses_post( $dietary_html ); ?>
					</div>
				<?php endif; ?>

				<div class="rpress-price-holder rpress-grid-view-holder">
					<span class="price">
						<?php
						if ( rpress_has_variable_prices( $id ) ) {
							echo wp_kses( rpress_price_range( $id ), array( 'span' => array( 'class' => true, 'id' => true ) ) );
						} else {
							echo esc_html( rpress_currency_filter( rpress_format_amount( (float) get_post_meta( $id, 'rpress_price', true ) ) ) );
						}
						?>
					</span>
				</div>
			</div>
		</div>
		<div class="rp-col-md-4 rp-col-sm-4 rp-col-xs-4">
			<div class="rpress-list-view-image-wrapper">
				<?php
				// content-image renders the photo (and the storefront JS injects
				// the .rp-soldout-badge into its holder for unavailable items).
				rpress_get_template_part( 'list/content-image' );
				do_action( 'rpress_fooditem_after_thumbnail' );
				?>
				<?php if ( $available ) : ?>
					<div class="rpress_fooditem_buy_button">
						<?php echo wp_kses( rpress_get_purchase_link( array( 'fooditem_id' => $id ) ), $purchase_link_kses ); ?>
					</div>
				<?php endif; ?>
				<?php do_action( 'rpress_fooditem_after_cart_button' ); ?>
			</div>
		</div>
		<?php do_action( 'rpress_fooditem_after' ); ?>
	</div>
</div>
