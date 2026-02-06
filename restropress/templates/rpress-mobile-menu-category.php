<?php
ob_start();
global $data;

// Fetch categories
if (is_array($data) && isset($data['category_menu']) && $data['category_menu']) {
    $get_all_items = rpress_get_child_cats($data['ids']);
} else {
    $get_all_items = rpress_get_categories($data);
}

// Allow filtering
$get_all_items = apply_filters('time_based_menu_rpress_categories', $get_all_items, $data);

// Handle excluded categories
$category_string = isset($data['excluded_category']) ? $data['excluded_category'] : '';
$category_string = rtrim($category_string, ',');
$category_array = explode(',', $category_string);
$disable_category = rpress_get_option('disable_category_menu', false);
$button_style = rpress_get_option('button_style', 'button');
if (!$disable_category && is_array($get_all_items) && !empty($get_all_items)):
    ?>
    <div class="container-actionmenu">
        <button id="actionburger" data-text-menu="<?php esc_attr_e('Menu', 'restropress'); ?>"
            data-text-close="<?php esc_attr_e('Close', 'restropress'); ?>" data-icon-menu="fa fa-cutlery"
            data-icon-close="fa fa-times" class="<?php esc_html_e( $button_style )?>">
            <div><i class="fa fa-cutlery" aria-hidden="true"></i></div>
            <span class="menu-text"><?php esc_html_e('Menu', 'restropress'); ?></span>
        </button>

        <nav class="actionmenu slides-down">
            <h2><?php esc_html_e('Menu', 'restropress'); ?></h2>
            <ul class="cd-dropdown-content">
                <?php foreach ($get_all_items as $cat):
                    if (!empty($category_array) && in_array($cat->slug, $category_array))
                        continue;
                    $count = intval($cat->count);
                    ?>
                    <li>
                        <a href="#menu-category-<?php echo esc_attr($cat->term_id); ?>">
                            <?php echo esc_html($cat->name); ?>
                            <span><?php echo esc_html($count); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
    <?php
endif; 
$allowed_html = array(
    'div' => array(
        'class' => array(),
        'id' => array(),
        'style' => array(),
        'data-*' => true,
    ),
    "i" => array(
        'class' => array(),
        'aria-hidden' => array(),
    ),
    'span' => array(
        'class' => array(),
        'id' => array(),
        'aria-selected' => array(),
    ),
    'a' => array(
        'href' => array(),
        'class' => array(),
        'id' => array(),
        'title' => array(),
        'aria-selected' => array(),
        'data-*' => true,
    ),
    'ul' => array(
        'class' => array(),
        'id' => array(),
    ),
    'li' => array(
        'class' => array(),
        'id' => array(),
    ),
    'nav' => array(
        'class' => array(),
        'id' => array(),
        'data-*' => true,
    ),
    'h2' => array(),
    'button' => array(
        'class' => array(),
        'id' => array(),
        'type' => array(),
        'aria-label' => array(),
        "data-text-menu" => true,
        "data-text-close" => true,
        "data-icon-menu" => true,
        "data-icon-close" => true,

    ),
    'svg' => array(
        'class' => array(),
        'xmlns' => array(),
        'viewBox' => array(),
    ),
    'path' => array(
        'd' => array(),
    ),
);
$output = ob_get_clean();
echo wp_kses($output, $allowed_html);

?>