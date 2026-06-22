<?php
/**
 * Food Item Addons data panel.
 *
 * @package RestroPress/Admin
 */
defined('ABSPATH') || exit;
$count = !empty($current) ? $current : time();
$post_id = get_the_ID();
$addons = get_post_meta($post_id, '_addon_items', true);
$variation_label = '';
if (is_array($addons) && !empty($addons)):
  if (!is_null($post_id) && rpress_has_variable_prices($post_id)) {
    $variation_label = get_post_meta($post_id, 'rpress_variable_price_label', true);
    $variation_label = !empty($variation_label) ? $variation_label : esc_html__('Variation', 'restropress');
  }
  foreach ($addons as $key => $addon_item):
    if (!isset($addon_item['category']))
      continue;
    $addon_id   = $addon_item['category'];
    $addon_type = get_term_meta($addon_id, '_type', true);
    $addon_term = get_term($addon_id, 'addon_category');
    $addon_name = ($addon_term && !is_wp_error($addon_term)) ? $addon_term->name : esc_html__('Select Addon', 'restropress');

    $is_required = (isset($addon_item['is_required']) && $addon_item['is_required'] === 'yes') ? 'checked' : '';

    $selected_item_count = isset($addon_item['items']) ? count((array) $addon_item['items']) : 0;

    $type_label = ($addon_type === 'single') ? esc_html__('Single', 'restropress') : esc_html__('Multiple', 'restropress');
    $type_class = ($addon_type === 'single') ? 'is-single' : 'is-multiple';
    ?>
    <!-- Addon group card -->
    <div class="rp-addon rp-metabox">
      <h3>
        <span class="dashicons dashicons-menu rp-addon-drag-handle tips sort"
          data-tip="<?php esc_attr_e('Drag to reorder add-on groups.', 'restropress'); ?>"></span>
        <strong class="addon_category_name"><?php echo esc_html($addon_name); ?></strong>
        <?php if ($addon_id): ?>
          <span class="rp-addon-type-badge <?php echo esc_attr($type_class); ?>"><?php echo esc_html($type_label); ?></span>
        <?php endif; ?>
        <?php if ($is_required): ?>
          <span class="rp-addon-required-badge"><?php esc_html_e('Required', 'restropress'); ?></span>
        <?php endif; ?>
        <span class="rp-addon-count-badge<?php echo $selected_item_count === 0 ? ' is-zero' : ''; ?>">
          <?php echo esc_html(sprintf(_n('%d option', '%d options', $selected_item_count, 'restropress'), $selected_item_count)); ?>
        </span>
        <button type="button" class="rp-addon-collapse-btn" title="<?php esc_attr_e('Expand / Collapse', 'restropress'); ?>">
          <span class="dashicons dashicons-arrow-down-alt2"></span>
        </button>
        <a href="#" class="remove_row delete" title="<?php esc_attr_e('Remove add-on group', 'restropress'); ?>">
          <span class="dashicons dashicons-trash"></span>
        </a>
      </h3>

      <div class="rp-metabox-content">
        <!-- Settings row: group select + constraints + required -->
        <div class="rp-addon-settings addon-category">
          <div class="rp-addon-setting-group is-grow">
            <label class="rp-addon-group-label"><?php esc_html_e('Add-on Group', 'restropress'); ?></label>
            <div class="rp-addon-select-wrap">
              <select name="addons[<?php echo sanitize_key($key); ?>][category]"
                class="rp-input rp-addon-lists"
                data-row-id="<?php echo esc_attr($key); ?>">
                <?php if ($addon_id == ''): ?>
                  <option value=""><?php esc_html_e('Select Add-on Group', 'restropress'); ?></option>
                <?php endif; ?>
                <?php foreach ($addon_categories as $category): ?>
                  <option data-name="<?php echo esc_attr($category->name); ?>"
                    <?php selected($addon_item['category'], $category->term_id); ?>
                    value="<?php echo esc_attr($category->term_id); ?>">
                    <?php echo esc_html($category->name); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="button load-addon"
                data-item-id="<?php echo isset($post_id) ? esc_attr($post_id) : ''; ?>">
                <?php esc_html_e('Load Items', 'restropress'); ?>
              </button>
            </div>
          </div>

          <div class="rp-addon-setting-group">
            <label class="rp-addon-group-label"><?php esc_html_e('Min Selections', 'restropress'); ?></label>
            <input type="number" min="0"
              name="addons[<?php echo sanitize_key($key); ?>][min_addons]"
              value="<?php echo isset($addon_item['min_addons']) ? esc_attr($addon_item['min_addons']) : ''; ?>"
              placeholder="0" />
          </div>

          <div class="rp-addon-setting-group">
            <label class="rp-addon-group-label"><?php esc_html_e('Max Selections', 'restropress'); ?></label>
            <input type="number" min="1"
              name="addons[<?php echo sanitize_key($key); ?>][max_addons]"
              value="<?php echo isset($addon_item['max_addons']) ? esc_attr($addon_item['max_addons']) : ''; ?>"
              placeholder="-" />
          </div>

          <div class="rp-addon-required-row">
            <input type="checkbox"
              name="addons[<?php echo sanitize_key($key); ?>][is_required]"
              value="yes" <?php echo esc_html($is_required); ?> />
            <span><?php esc_html_e('Required', 'restropress'); ?></span>
          </div>
        </div><!-- /.rp-addon-settings -->

        <!-- Addon items table -->
        <div class="addon-items">
          <?php
          $get_addons = rpress_get_addons($addon_id);

          if (!empty($addon_id) && is_array($get_addons) && !empty($get_addons)): ?>
            <table class="rp-addon-items" data-addon_type="<?php echo esc_attr($addon_type); ?>">
              <thead>
                <tr>
                  <th class="select_addon">
                    <input type="checkbox" class="rp-select-all" title="<?php esc_attr_e('Select all', 'restropress'); ?>">
                    <?php esc_html_e('Enable', 'restropress'); ?>
                  </th>
                  <th class="addon_name"><?php esc_html_e('Addon Name', 'restropress'); ?></th>
                  <th class="variation_name"><?php echo esc_html($variation_label); ?></th>
                  <th class="addon_price"><?php esc_html_e('Price', 'restropress'); ?></th>
                  <th class="default_addon"><?php esc_html_e('Default', 'restropress'); ?></th>
                </tr>
              </thead>
              <?php
              // Sort by position
              $positions = [];
              foreach ($get_addons as $addon):
                $positions[$addon->term_id] = get_term_meta($addon->term_id, 'addon_position', true) ?? 0;
              endforeach;
              uasort($get_addons, function ($a, $b) use ($positions) {
                $posA = isset($positions[$a->term_id]) ? $positions[$a->term_id] : PHP_INT_MAX;
                $posB = isset($positions[$b->term_id]) ? $positions[$b->term_id] : PHP_INT_MAX;
                return $posA - $posB;
              });
              ?>

              <?php if (!rpress_has_variable_prices($post_id)): ?>
                <tbody id="rp-addon-item-list">
              <?php endif; ?>

              <?php foreach ($get_addons as $get_addon):
                $addon_item_id   = $get_addon->term_id;
                $addon_item_name = $get_addon->name;
                $addon_slug      = $get_addon->slug;
                $addon_price     = rpress_get_addon_data($addon_item_id, '_price');
                $addon_price     = !empty($addon_price) ? $addon_price : 0;

                $selected         = (isset($addon_item['items']) && in_array($addon_item_id, $addon_item['items'])) ? 'checked' : '';
                $req_selected     = (isset($addon_item['required']) && in_array($addon_item_id, $addon_item['required'])) ? 'checked' : '';
                $default_selected = (isset($addon_item['default']) && in_array($addon_item_id, $addon_item['default'])) ? 'checked' : '';

                if (rpress_has_variable_prices($post_id)):
                  echo '<tbody class="rp-addon-variation-group" id="addon-group-' . esc_attr($addon_item_id) . '">';
                  $vcount = 1;
                  foreach (rpress_get_variable_prices($post_id) as $price):
                    $addon_price_v = (!empty($addon_item['prices']) && !empty($addon_item['prices'][$addon_item_id][$price['name']]))
                      ? sanitize_text_field($addon_item['prices'][$addon_item_id][$price['name']])
                      : $addon_price;
                    $default_var_selected = (isset($addon_item['default']) && in_array($addon_item_id . '|' . $price['name'], $addon_item['default']))
                      ? 'checked'
                      : '';
                    ?>
                    <?php if ($vcount == 1): ?>
                      <tr class="rp-child-addon addon-root-row" id="tag-<?php echo esc_attr($addon_item_id); ?>"
                        data-term="<?php echo esc_attr($addon_item_id); ?>">
                    <?php else: ?>
                      <tr class="rp-child-addon addon-child-row" data-term="<?php echo esc_attr($addon_item_id); ?>">
                    <?php endif; ?>
                      <?php if ($vcount == 1): ?>
                        <td class="rp-addon-select td_checkbox">
                          <input type="checkbox" value="<?php echo esc_attr($addon_item_id); ?>"
                            id="<?php echo esc_attr($addon_slug); ?>"
                            name="addons[<?php echo sanitize_key($key); ?>][items][]"
                            class="rp-checkbox" <?php echo esc_html($selected); ?> />
                        </td>
                      <?php else: ?>
                        <td class="td_checkbox">&nbsp;</td>
                      <?php endif; ?>
                      <td class="add_label">
                        <label for="<?php echo esc_attr($addon_slug); ?>"><?php echo esc_html($addon_item_name); ?></label>
                      </td>
                      <td class="variation_label">
                        <label for="<?php echo esc_attr($price['name']); ?>"><?php echo esc_html($price['name']); ?></label>
                      </td>
                      <td class="addon_price">
                        <input class="addon-custom-price" type="number" step="any" min="0.00" placeholder="0.00"
                          value="<?php echo esc_attr(rpress_sanitize_amount($addon_price_v)); ?>"
                          name="addons[<?php echo esc_attr($key); ?>][prices][<?php echo esc_attr($addon_item_id); ?>][<?php echo esc_attr($price['name']); ?>]">
                      </td>
                      <td class="td_checkbox">
                        <input type="checkbox"
                          data-variation_name="<?php echo esc_attr($price['name']); ?>"
                          value="<?php echo esc_attr($addon_item_id . '|' . $price['name']); ?>"
                          id="<?php echo esc_attr($addon_slug); ?>"
                          name="addons[<?php echo sanitize_key($key); ?>][default][]"
                          class="rps-checkbox" <?php echo esc_html($default_var_selected); ?> />
                      </td>
                    </tr>
                    <?php $vcount++;
                  endforeach;
                  echo '</tbody>';

                else: // non-variable pricing
                  $addon_price = (isset($addon_item['prices'][$addon_item_id]) && !is_array($addon_item['prices'][$addon_item_id]))
                    ? $addon_item['prices'][$addon_item_id]
                    : $addon_price;
                  ?>
                  <tr class="rp-child-addon" id="tag-<?php echo esc_attr($addon_item_id); ?>">
                    <td class="rp-addon-select td_checkbox">
                      <input type="checkbox" value="<?php echo esc_attr($addon_item_id); ?>"
                        id="<?php echo esc_attr($addon_slug); ?>"
                        name="addons[<?php echo sanitize_key($key); ?>][items][]"
                        class="rp-checkbox" <?php echo esc_html($selected); ?> />
                    </td>
                    <td class="add_label">
                      <label for="<?php echo esc_attr($addon_slug); ?>"><?php echo esc_html($addon_item_name); ?></label>
                    </td>
                    <td class="variation_label">&nbsp;</td>
                    <td class="addon_price">
                      <input class="addon-custom-price" type="number" step="any" min="0.00" placeholder="0.00"
                        value="<?php echo esc_attr(rpress_sanitize_amount($addon_price)); ?>"
                        name="addons[<?php echo esc_attr($key); ?>][prices][<?php echo esc_attr($addon_item_id); ?>]">
                    </td>
                    <td class="tds_checkbox">
                      <input type="checkbox" value="<?php echo esc_attr($addon_item_id); ?>"
                        id="<?php echo esc_attr($addon_slug); ?>"
                        name="addons[<?php echo sanitize_key($key); ?>][default][]"
                        class="rp-checkbox" <?php echo esc_attr($default_selected); ?> />
                    </td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>

              <?php if (!rpress_has_variable_prices($post_id)): ?>
                </tbody>
              <?php endif; ?>
            </table>

          <?php else: ?>
            <div class="rp-addon-msg">
              <?php esc_html_e('Select an add-on group above and click "Load Items" to pick which options are available for this menu item.', 'restropress'); ?>
            </div>
          <?php endif; ?>
        </div><!-- /.addon-items -->
      </div><!-- /.rp-metabox-content -->
    </div>
    <!-- /addon group card -->
  <?php endforeach; ?>

<?php else: ?>
  <!-- Default empty card when no addons saved yet -->
  <div class="rp-addon rp-metabox">
    <h3>
      <span class="dashicons dashicons-menu rp-addon-drag-handle tips sort"
        data-tip="<?php esc_attr_e('Drag to reorder add-on groups.', 'restropress'); ?>"></span>
      <strong class="addon_category_name"><?php esc_html_e('Select Addon', 'restropress'); ?></strong>
      <span class="rp-addon-count-badge is-zero"><?php esc_html_e('0 options', 'restropress'); ?></span>
      <button type="button" class="rp-addon-collapse-btn" title="<?php esc_attr_e('Expand / Collapse', 'restropress'); ?>">
        <span class="dashicons dashicons-arrow-down-alt2"></span>
      </button>
      <a href="#" class="remove_row delete" title="<?php esc_attr_e('Remove add-on group', 'restropress'); ?>">
        <span class="dashicons dashicons-trash"></span>
      </a>
    </h3>

    <div class="rp-metabox-content">
      <div class="rp-addon-settings addon-category">
        <div class="rp-addon-setting-group is-grow">
          <label class="rp-addon-group-label"><?php esc_html_e('Add-on Group', 'restropress'); ?></label>
          <div class="rp-addon-select-wrap">
            <select name="addons[<?php echo sanitize_key($count); ?>][category]"
              class="rp-input rp-addon-items-list rp-addon-lists"
              data-row-id="<?php echo esc_attr($count); ?>">
              <option value=""><?php esc_html_e('Select Add-on Group', 'restropress'); ?></option>
              <?php foreach ($addon_categories as $category): ?>
                <option value="<?php echo esc_attr($category->term_id); ?>">
                  <?php echo esc_html($category->name); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="button load-addon"
              data-item-id="<?php echo isset($item_id) ? esc_attr($item_id) : esc_attr($post_id); ?>">
              <?php esc_html_e('Load Items', 'restropress'); ?>
            </button>
          </div>
        </div>

        <div class="rp-addon-setting-group">
          <label class="rp-addon-group-label"><?php esc_html_e('Min Selections', 'restropress'); ?></label>
          <input type="number" min="0"
            name="addons[<?php echo sanitize_key($count); ?>][min_addons]"
            value="" placeholder="0" />
        </div>

        <div class="rp-addon-setting-group">
          <label class="rp-addon-group-label"><?php esc_html_e('Max Selections', 'restropress'); ?></label>
          <input type="number" min="1"
            name="addons[<?php echo sanitize_key($count); ?>][max_addons]"
            value="" placeholder="-" />
        </div>

        <div class="rp-addon-required-row">
          <input type="checkbox"
            name="addons[<?php echo sanitize_key($count); ?>][is_required]"
            value="yes" />
          <span><?php esc_html_e('Required', 'restropress'); ?></span>
        </div>
      </div><!-- /.rp-addon-settings -->

      <div class="addon-items">
        <div class="rp-addon-msg">
          <?php esc_html_e('Select an add-on group above and click "Load Items" to pick which options are available for this menu item.', 'restropress'); ?>
        </div>
      </div>
    </div><!-- /.rp-metabox-content -->
  </div>
  <!-- /default empty card -->
<?php endif; ?>
