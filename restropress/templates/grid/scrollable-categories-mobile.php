<?php
/*
* Sidebar category template with dynamic dropdown and nav
*/
global $data;
ob_start();

// Fetch categories
if ( is_array($data) && isset($data['category_menu']) && $data['category_menu'] ) {
    $get_all_items = rpress_get_child_cats($data['ids']);
} else {
    $get_all_items = rpress_get_categories($data);
}

// Allow filtering
$get_all_items = apply_filters('time_based_menu_rpress_categories', $get_all_items, $data);

// Handle excluded categories
$category_string = isset($data['excluded_category']) ? $data['excluded_category'] : '';
$category_string  = rtrim($category_string, ',');
$category_array   = explode(',', $category_string);
$disable_category = rpress_get_option('disable_category_menu', false);

if (!$disable_category && is_array($get_all_items) && !empty($get_all_items)) :
?>
<div class="make-me-sticky">
    <div class="cd-dropdown-wrapper">
        <a class="cd-dropdown-trigger" href="#0"></a>
        <nav class="cd-dropdown">
            <h2>Menu</h2>
            <a href="#0" class="cd-close">Close</a>
            <ul class="cd-dropdown-content">
                <?php foreach ($get_all_items as $cat) :
                    if (!empty($category_array) && in_array($cat->slug, $category_array)) continue;
                    $count = intval($cat->count);
                ?>
                    <li>
                        <a href="#menu-category-<?php echo esc_attr($cat->term_id); ?>">
                            <?php echo esc_html($cat->name); ?>
                            <span><?php echo $count; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>

    <div class="pn-ProductNav_Wrapper">
        <nav id="pnProductNavMobile" class="pn-ProductNav">
            <div id="pnProductNavContentsMobile" class="pn-ProductNav_Contents">
                <?php
                $first = true;
                foreach ($get_all_items as $cat) :
                    if (!empty($category_array) && in_array($cat->slug, $category_array)) continue;
                ?>
                    <a href="#menu-category-<?php echo esc_attr($cat->term_id); ?>"
                    class="pn-ProductNav_Link<?php echo $first ? ' mnuactive' : ''; ?>"
                    aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                        <?php echo esc_html($cat->name); ?>
                    </a>
                <?php $first = false; endforeach; ?>
                <span id="pnIndicatorMobile" class="pn-ProductNav_Indicator"></span>
            </div>
        </nav>
        <button id="pnAdvancerLeftMobile" class="pn-Advancer pn-Advancer_Left" type="button">
            <svg class="pn-Advancer_Icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 551 1024">
                <path d="M445.44 38.183L-2.53 512l447.97 473.817 85.857-81.173-409.6-433.23v81.172l409.6-433.23L445.44 38.18z" />
            </svg>
        </button>
        <button id="pnAdvancerRightMobile" class="pn-Advancer pn-Advancer_Right" type="button">
            <svg class="pn-Advancer_Icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 551 1024">
                <path d="M105.56 985.817L553.53 512 105.56 38.183l-85.857 81.173 409.6 433.23v-81.172l-409.6 433.23 85.856 81.174z" />
            </svg>
        </button>
    </div>
</div>
<?php
endif;

echo ob_get_clean();
?>
