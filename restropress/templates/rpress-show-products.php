<div class="fooditem-description">{itemdescription}</div>
<div class="view-food-item-wrap">
	<form id="fooditem-details">{fooditemslist}</form>
	<div class="clear"></div>
	<?php if( apply_filters( 'rpress_special_instructions', true ) ) : ?>
	<div class="rp-col-md-12 md-4-top special-margin <?php
// Check if $color is defined and not null
if (isset($color)) {
    // $color is defined, you can use it safely
    echo wp_kses_post($color);
} else {
    // $color is not defined, handle the case accordingly
    echo "";
}
?>">
		<span class="special-instructions-link">
			<?php echo apply_filters('rpress_special_instruction_text', __('Special Instructions?', 'restropress')); ?>
		</span>
		<textarea placeholder="<?php esc_html_e('Add Instructions...', 'restropress') ?>" class="rp-col-md-12 special-instructions " name="special_instruction"></textarea>
	</div>
	<?php endif; ?>
</div>