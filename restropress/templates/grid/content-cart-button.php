<div class="rpress-price-holder">
  <span class="price">  
    <?php    
    global $post;
    global $rpress_options;
    $price = get_post_meta( $post->ID,'rpress_price', true ) ;
    $variable_pricing = rpress_has_variable_prices( $post->ID );
    $rate = (float) rpress_get_option( 'tax_rate', 0 );
    // Convert to a number we can use
    $item_tax = (float) $price - ( (float) $price / ( ( (float) $rate / 100 ) + 1 ) );
    $include_tax  = rpress_get_option( 'prices_include_tax', true );
    $tax_inc_exc_item_option = rpress_get_option('tax_item', true );
    if ( $variable_pricing ) {
      echo wp_kses(
        rpress_price_range( $post->ID ),
        array(
            'span' => array(
                'class' => true,
                'id'    => true,
            ),
        )
      );    
    } else {
 /** 
    * Condition added to show the item price as included or excluded Tax
    * @since 2.9.6
    */
    if( $include_tax == 'yes' && $tax_inc_exc_item_option == 'inc_tax' ) {
        $price = get_post_meta( $post->ID,'rpress_price', true );
      } elseif ( $include_tax == 'yes' && $tax_inc_exc_item_option == 'exc_tax' ) {
        $item_tax = ( float ) $price - ( (float) $price / ( ( (float) $rate / 100 ) + 1 ) );
        $price = $price - $item_tax;
      } elseif ($include_tax == 'no' && $tax_inc_exc_item_option == 'inc_tax') {
        $item_tax = ( float ) $price * ( (float) $rate / 100 );
        $price = ( float ) $price + ( float ) $item_tax;
      } else {
        $price = get_post_meta( $post->ID,'rpress_price', true ) ;
      }
      if ( $variable_pricing ) {
        echo wp_kses(
          apply_filters( 'rpress_item_price_display', rpress_price_range( $post->ID ), $post ),
          array(
            'span' => array(
              'class' => true,
              'id'    => true,
            ),
            'del' => array(),
            'ins' => array(),
          )
        );
      } else {
        echo esc_html(
          apply_filters(
            'rpress_item_price_display',
            rpress_currency_filter( rpress_format_amount( $price ) ),
            $post
          )
        );
      }      
    }
    ?>
  </span>
  
  <div class="rpress_fooditem_buy_button">
    <?php
    echo wp_kses(
      rpress_get_purchase_link_grid( array( 'fooditem_id' => get_the_ID() ) ),
      array(
        'form' => array(
          'id'    => true,
          'class' => true,
          'method'=> true,
          'action'=> true,
        ),
        'div' => array(
          'class' => true,
        ),
        'a' => array(
          'href' => true,
          'class' => true,
          'data-title' => true,
          'data-action' => true,
          'data-fooditem-id' => true,
          'data-variable-price' => true,
          'data-price' => true,
          'data-price-mode' => true,
          'style' => true,
        ),
        'span' => array(
          'class' => true,
        ),
        'svg' => array(
          'xmlns' => true,
          'width' => true,
          'height' => true,
          'viewbox' => true,
        ),
        'path' => array(
          'd' => true,
        ),
        'input' => array(
          'type' => true,
          'name' => true,
          'class' => true,
          'value' => true,
          'id' => true,
        ),
      )
    );
    ?>
  </div>
</div>