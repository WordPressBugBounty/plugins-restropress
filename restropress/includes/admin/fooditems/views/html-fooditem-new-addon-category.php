<?php
/**
 * Food Item - Create New Addon Category card.
 *
 * @package RestroPress/Admin
 */
defined( 'ABSPATH' ) || exit;
$row = isset( $_POST['i'] ) ? absint( $_POST['i'] ) : 0;
?>
<!-- Create new addon category card -->
<div class="rp-addon rp-metabox create-new-addon">
  <h3>
    <span class="dashicons dashicons-menu rp-addon-drag-handle tips sort"
      data-tip="<?php esc_attr_e( 'Drag to reorder.', 'restropress' ); ?>"></span>
    <strong class="addon_category_name">
      <?php esc_html_e( 'New Add-on Group', 'restropress' ); ?>
    </strong>
    <span class="rp-addon-type-badge"><?php esc_html_e( 'New', 'restropress' ); ?></span>
    <a href="#" class="remove_row delete" title="<?php esc_attr_e( 'Remove', 'restropress' ); ?>">
      <span class="dashicons dashicons-trash"></span>
    </a>
  </h3>

  <div class="rp-metabox-content">
    <!-- Config row: name + type -->
    <div class="rp-new-addon-config">
      <div class="rp-new-addon-field is-grow">
        <label for="addon-cat-name-<?php echo esc_attr( $row ); ?>">
          <?php esc_html_e( 'Category Name', 'restropress' ); ?> <span style="color:#d63638;">*</span>
        </label>
        <input type="text"
          id="addon-cat-name-<?php echo esc_attr( $row ); ?>"
          name="addon_category[<?php echo sanitize_key( $row ); ?>][name]"
          class="rp-input addon-category-name"
          placeholder="<?php esc_attr_e( 'e.g. Sauce Options, Extras…', 'restropress' ); ?>"
          autocomplete="off" />
      </div>

      <div class="rp-new-addon-field">
        <label for="addon-cat-type-<?php echo esc_attr( $row ); ?>">
          <?php esc_html_e( 'Selection Type', 'restropress' ); ?>
        </label>
        <select id="addon-cat-type-<?php echo esc_attr( $row ); ?>"
          name="addon_category[<?php echo sanitize_key( $row ); ?>][type]"
          class="rp-input addon-category-type">
          <?php foreach ( $addon_types as $k => $type ) : ?>
            <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $type ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div><!-- /.rp-new-addon-config -->

    <!-- Items list -->
    <div class="rp-new-addon-items-header">
      <span><?php esc_html_e( 'Items', 'restropress' ); ?></span>
      <span class="addon-price-symbol">
        <?php echo esc_html( sprintf( __( 'Price (%s)', 'restropress' ), rpress_currency_symbol() ) ); ?>
      </span>
    </div>

    <div class="rp-new-addon-items-body">
      <table style="width:100%;border-collapse:collapse;">
        <tbody>
          <tr class="addon-items-row">
            <td style="padding:0 6px 0 0;">
              <input type="text"
                name="addon_category[<?php echo esc_attr( $row ); ?>][addon_name][]"
                class="rp-input"
                placeholder="<?php esc_attr_e( 'Item name (e.g. Spicy Sauce)', 'restropress' ); ?>"
                style="width:100%;height:30px;font-size:13px;" />
            </td>
            <td style="padding:0 6px;width:110px;">
              <input type="number"
                name="addon_category[<?php echo esc_attr( $row ); ?>][addon_price][]"
                class="rp-input rp-addon-price"
                step="any" min="0.00"
                placeholder="0.00"
                style="width:100%;height:30px;font-size:13px;text-align:right;" />
            </td>
            <td style="padding:0;width:30px;text-align:center;">
              <span class="remove rp-addon-cat" title="<?php esc_attr_e( 'Remove item', 'restropress' ); ?>">
                <span class="dashicons dashicons-dismiss"></span>
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div><!-- /.rp-new-addon-items-body -->

    <div class="rp-new-addon-add-item">
      <button type="button" class="button add-new-addon add-addon-multiple-item">
        + <?php esc_html_e( 'Add Item', 'restropress' ); ?>
      </button>
    </div>
  </div><!-- /.rp-metabox-content -->
</div>
<!-- /create new addon category card -->