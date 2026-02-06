<?php
/*
 * Sidebar category template
 *
 */
global $data;
ob_start();
if ($data['category_menu']) {
	$get_all_items = rpress_get_child_cats($data['ids']);
} else {
	$get_all_items = rpress_get_categories($data);
}

// Apply custom filter to $get_all_items
$get_all_items = apply_filters('time_based_menu_rpress_categories', $get_all_items, $data);

$category_string = $data['excluded_category'];
$category_string = rtrim($category_string, ',');
$category_array = explode(',', $category_string);
$disable_category = rpress_get_option('disable_category_menu', false);
if (!$disable_category):
	?>
	<div class="rp-col-lg-4 rp-col-md-4 rp-col-sm-3 rp-col-xs-12 sticky-sidebar cat-lists">
		<div class="rpress-filter-wrapper">
			<div class="rpress-categories-menu">
				<?php do_action('rpress_before_category_list'); ?>
				<?php
				if (is_array($get_all_items) && !empty($get_all_items)):
					?>
					<ul class="rpress-category-lists">
						<?php
						foreach ($get_all_items as $key => $get_all_item):
							if (!empty($category_array) && in_array($get_all_item->slug, $category_array)) {
								continue;
							}
							?>
							<li class="rpress-category-item ">
								<?php
								// Get the count of published fooditems in this category
								$count = $get_all_item->count;

								// Build the link text with count
								$link_text = $get_all_item->name . ' (' . intval($count) . ')';
								?>
								<a href="#<?php echo esc_html($get_all_item->slug); ?>"
									data-id="<?php echo esc_attr($get_all_item->term_id); ?>"
									class="rpress-category-link nav-scroller-item">
									<?php echo esc_html($link_text); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php
				endif;
				?>
				<?php do_action('rpress_after_category_list'); ?>
			</div>
		</div>
	</div>	
	<?php
endif;
// Allowed HTML for wp_kses
$allowed_html = array(
	'div' => array('class' => true),
	'ul' => array('class' => true),
	'li' => array('class' => true),
	'a' => array(
		'href' => true,
		'class' => true,
		'data-id' => true,
		'aria-label' => true,
	),
	'span' => array('class' => true),
);

// Output safely
echo wp_kses(ob_get_clean(), $allowed_html);