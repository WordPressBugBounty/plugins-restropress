<?php
/**
 * RestroPress Meta Box Functions
 *
 * @author      MagniGenie
 * @category    Core
 * @package     RestroPress/Admin/Functions
 * @version     3.0
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}
/**
 * Output a text input box.
 *
 * @param array $field
 */
function rpress_text_input( $field ) {
  global $thepostid, $post;
  $thepostid              = empty( $thepostid ) ? $post->ID : $thepostid;
  $field['placeholder']   = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
  $field['class']         = isset( $field['class'] ) ? $field['class'] : 'short';
  $field['style']         = isset( $field['style'] ) ? $field['style'] : '';
  $field['wrapper_class'] = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
  $field['value']         = isset( $field['value'] ) ? $field['value'] : get_post_meta( $thepostid, $field['id'], true );
  $field['name']          = isset( $field['name'] ) ? $field['name'] : $field['id'];
  $field['type']          = isset( $field['type'] ) ? $field['type'] : 'text';
  $field['desc_tip']      = isset( $field['desc_tip'] ) ? $field['desc_tip'] : false;
  $data_type              = empty( $field['data_type'] ) ? '' : $field['data_type'];
  switch ( $data_type ) {
    case 'price':
      $field['class'] .= ' rpress_input_price" min="0.00" step="any';
      $field['value']  = rpress_sanitize_amount( $field['value'] );
      $field['type']  = 'number';
      break;
    case 'decimal':
      $field['class'] .= ' rpress_input_decimal';
      $field['value']  = rpress_format_localized_decimal( $field['value'] );
      break;
    case 'url':
      $field['class'] .= ' rpress_input_url';
      $field['value']  = esc_url( $field['value'] );
      break;
    default:
      break;
  }
  // Custom attribute handling
  $custom_attributes = array();
  if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
    foreach ( $field['custom_attributes'] as $attribute => $value ) {
      $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
    }
  }
  echo '<p class="form-field ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '">
    <label for="' . esc_attr( $field['id'] ) . '">' . wp_kses_post( $field['label'] ) . '</label>';
  if ( ! empty( $field['description'] ) && false !== $field['desc_tip'] ) {
    echo rp_help_tip( $field['description'] );
  }
  echo '<input type="' . esc_attr( $field['type'] ) . '" class="' .  $field['class']  . '" style="' . esc_attr( $field['style'] ) . '" name="' . esc_attr( $field['name'] ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $field['value'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" ' . implode( ' ', $custom_attributes ) . ' /> ';
  if ( ! empty( $field['description'] ) && false === $field['desc_tip'] ) {
    echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
  }
  echo '</p>';
}
/**
 * Output a hidden input box.
 *
 * @param array $field
 */
function rpress_hidden_input( $field ) {
  global $thepostid, $post;
  $thepostid      = empty( $thepostid ) ? $post->ID : $thepostid;
  $field['value'] = isset( $field['value'] ) ? $field['value'] : get_post_meta( $thepostid, $field['id'], true );
  $field['class'] = isset( $field['class'] ) ? $field['class'] : '';
  echo '<input type="hidden" class="' . esc_attr( $field['class'] ) . '" name="' . esc_attr( $field['id'] ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $field['value'] ) . '" /> ';
}
/**
 * Output a textarea input box.
 *
 * @param array $field
 */
function rpress_textarea_input( $field ) {
  global $thepostid, $post;
  $thepostid              = empty( $thepostid ) ? $post->ID : $thepostid;
  $field['placeholder']   = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
  $field['class']         = isset( $field['class'] ) ? $field['class'] : 'short';
  $field['style']         = isset( $field['style'] ) ? $field['style'] : '';
  $field['wrapper_class'] = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
  $field['value']         = isset( $field['value'] ) ? $field['value'] : get_post_meta( $thepostid, $field['id'], true );
  $field['desc_tip']      = isset( $field['desc_tip'] ) ? $field['desc_tip'] : false;
  $field['name']          = isset( $field['name'] ) ? $field['name'] : $field['id'];
  $field['rows']          = isset( $field['rows'] ) ? $field['rows'] : 2;
  $field['cols']          = isset( $field['cols'] ) ? $field['cols'] : 20;
  // Custom attribute handling
  $custom_attributes = array();
  if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
    foreach ( $field['custom_attributes'] as $attribute => $value ) {
      $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
    }
  }
  echo '<p class="form-field ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '">
    <label for="' . esc_attr( $field['id'] ) . '">' . wp_kses_post( $field['label'] ) . '</label>';
  if ( ! empty( $field['description'] ) && false !== $field['desc_tip'] ) {
    echo rp_help_tip( $field['description'] );
  }
  echo '<textarea class="' . esc_attr( $field['class'] ) . '" style="' . esc_attr( $field['style'] ) . '"  name="' . esc_attr( $field['name'] ) . '" id="' . esc_attr( $field['id'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" rows="' . esc_attr( $field['rows'] ) . '" cols="' . esc_attr( $field['cols'] ) . '" ' . implode( ' ', $custom_attributes ) . '>' . esc_textarea( $field['value'] ) . '</textarea> ';
  if ( ! empty( $field['description'] ) && false === $field['desc_tip'] ) {
    echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
  }
  echo '</p>';
}
/**
 * Output a checkbox input box.
 *
 * @param array $field
 */
function rpress_checkbox( $field ) {
  global $thepostid, $post;
  $thepostid              = empty( $thepostid ) ? $post->ID : $thepostid;
  $field['class']         = isset( $field['class'] ) ? $field['class'] : 'checkbox';
  $field['style']         = isset( $field['style'] ) ? $field['style'] : '';
  $field['wrapper_class'] = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
  $field['value']         = isset( $field['value'] ) ? $field['value'] : get_post_meta( $thepostid, $field['id'], true );
  $field['cbvalue']       = isset( $field['cbvalue'] ) ? $field['cbvalue'] : 'yes';
  $field['name']          = isset( $field['name'] ) ? $field['name'] : $field['id'];
  $field['desc_tip']      = isset( $field['desc_tip'] ) ? $field['desc_tip'] : false;
  // Custom attribute handling
  $custom_attributes = array();
  if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
    foreach ( $field['custom_attributes'] as $attribute => $value ) {
      $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
    }
  }
  echo '<p class="form-field ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '">
    <label for="' . esc_attr( $field['id'] ) . '">' . wp_kses_post( $field['label'] ) . '</label>';
  if ( ! empty( $field['description'] ) && false !== $field['desc_tip'] ) {
    echo rp_help_tip( $field['description'] );
  }
  echo '<input type="checkbox" class="' . esc_attr( $field['class'] ) . '" style="' . esc_attr( $field['style'] ) . '" name="' . esc_attr( $field['name'] ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $field['cbvalue'] ) . '" ' . checked( $field['value'], $field['cbvalue'], false ) . '  ' . implode( ' ', $custom_attributes ) . '/> ';
  if ( ! empty( $field['description'] ) && false === $field['desc_tip'] ) {
    echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
  }
  echo '</p>';
}
/**
 * Output a select input box.
 *
 * @param array $field Data about the field to render.
 */
function rpress_select( $field ) {
  global $thepostid, $post;
  $thepostid = empty( $thepostid ) ? $post->ID : $thepostid;
  $field     = wp_parse_args(
    $field, array(
      'class'             => 'select short',
      'style'             => '',
      'wrapper_class'     => '',
      'value'             => get_post_meta( $thepostid, $field['id'], true ),
      'name'              => $field['id'],
      'desc_tip'          => false,
      'custom_attributes' => array(),
    )
  );
  $wrapper_attributes = array(
    'class' => $field['wrapper_class'] . " form-field {$field['id']}_field",
  );
  $label_attributes = array(
    'for' => $field['id'],
  );
  $field_attributes          = (array) $field['custom_attributes'];
  $field_attributes['style'] = $field['style'];
  $field_attributes['id']    = $field['id'];
  $field_attributes['name']  = $field['name'];
  $field_attributes['class'] = $field['class'];
  $tooltip     = ! empty( $field['description'] ) && false !== $field['desc_tip'] ? $field['description'] : '';
  $description = ! empty( $field['description'] ) && false === $field['desc_tip'] ? $field['description'] : '';
  ?>
  <p <?php echo rpress_implode_html_attributes( $wrapper_attributes ); // WPCS: XSS ok. ?>>
    <label <?php echo rpress_implode_html_attributes( $label_attributes ); // WPCS: XSS ok. ?>>
      <?php echo wp_kses_post( $field['label'] ); ?>
    </label>
    <?php if ( $tooltip ) : ?>
      <?php echo rp_help_tip( $tooltip ); // WPCS: XSS ok. ?>
    <?php endif; ?>
    <select <?php echo rpress_implode_html_attributes( $field_attributes ); // WPCS: XSS ok. ?>>
      <?php
      foreach ( $field['options'] as $key => $value ) {
        echo '<option value="' . esc_attr( $key ) . '"' . rp_selected( $key, $field['value'] ) . '>' . esc_html( $value ) . '</option>';
      }
      ?>
    </select>
    <?php if ( $description ) : ?>
      <span class="description"><?php echo wp_kses_post( $description ); ?></span>
    <?php endif; ?>
  </p>
  <?php
}
/**
 * Output a radio input box.
 *
 * @param array $field
 */
function rpress_radio( $field ) {
  global $thepostid, $post;
  $thepostid              = empty( $thepostid ) ? $post->ID : $thepostid;
  $field['class']         = isset( $field['class'] ) ? $field['class'] : 'select short';
  $field['style']         = isset( $field['style'] ) ? $field['style'] : '';
  $field['wrapper_class'] = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
  $field['value']         = isset( $field['value'] ) ? $field['value'] : get_post_meta( $thepostid, $field['id'], true );
  $field['name']          = isset( $field['name'] ) ? $field['name'] : $field['id'];
  $field['desc_tip']      = isset( $field['desc_tip'] ) ? $field['desc_tip'] : false;
  echo '<fieldset class="form-field ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '"><legend>' . wp_kses_post( $field['label'] ) . '</legend>';
  if ( ! empty( $field['description'] ) && false !== $field['desc_tip'] ) {
    echo rp_help_tip( $field['description'] );
  }
  echo '<ul class="wc-radios">';
  foreach ( $field['options'] as $key => $value ) {
    echo '<li><label><input
        name="' . esc_attr( $field['name'] ) . '"
        value="' . esc_attr( $key ) . '"
        type="radio"
        class="' . esc_attr( $field['class'] ) . '"
        style="' . esc_attr( $field['style'] ) . '"
        ' . checked( esc_attr( $field['value'] ), esc_attr( $key ), false ) . '
        /> ' . esc_html( $value ) . '</label>
    </li>';
  }
  echo '</ul>';
  if ( ! empty( $field['description'] ) && false === $field['desc_tip'] ) {
    echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
  }
  echo '</fieldset>';
}
