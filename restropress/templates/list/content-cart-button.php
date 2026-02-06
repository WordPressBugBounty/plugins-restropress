  
  <div class="rpress_fooditem_buy_button">
    <?php
    echo wp_kses(
      rpress_get_purchase_link( array( 'fooditem_id' => get_the_ID() ) ),
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