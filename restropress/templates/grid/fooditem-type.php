<?php
$id = get_the_id();
$food_type = get_post_meta($id, 'rpress_food_type', true);

if(isset($food_type) && $food_type == "veg") {
    ?>
    <div class="vegbg"><div class="veg_sub"></div></div>
    <?php
} else if (isset($food_type) && $food_type == "non_veg") {
    ?>
    <div class="non_vegbg"><div class="non_vegsub"></div></div>
    <?php
}
?>