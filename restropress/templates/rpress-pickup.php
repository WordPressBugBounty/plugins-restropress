<div class="tab-pane fade delivery-settings-wrapper" id="nav-pickup" role="tabpanel" aria-labelledby="nav-pickup-tab">
  <!-- Pickup Time Wrap -->
  <div class="rpress-pickup-time-wrap rpress-time-wrap">
    <?php do_action( 'rpress_before_service_time', 'pickup' ); ?>
    <?php
    if ( rpress_is_service_enabled( 'pickup' ) ) :
      $store_times        = rp_get_store_timings( true, 'pickup' );
      $store_timings      = apply_filters( 'rpress_store_pickup_timings', $store_times );
      $store_time_format  = rpress_get_option( 'store_time_format' );
      $time_format        = ! empty( $store_time_format ) && $store_time_format == '24hrs' ? 'H:i' : 'h:ia';
      $time_format        = apply_filters( 'rpress_store_time_format', $time_format, $store_time_format );
      $selected_time = isset( $_COOKIE['service_time'] ) ? $_COOKIE['service_time'] : '';
      $asap_option = rpress_get_option( 'enable_asap_option', '' );
      $asap_option_only = rpress_get_option( 'enable_asap_option_only', '' );
      $pickup_asap_text = rpress_get_option( 'pickup_asap_text', '' );
      
      ?>
      <div class="pickup-time-text">
        <?php echo apply_filters( 'rpress_pickup_time_string', esc_html_e( 'Select a pickup time', 'restropress' ) ); ?>
      </div>
      <?php
      if ( $asap_option_only == 1 ) {
        array_splice( $store_timings, 1 );
      }
      ?>
      <select class="rpress-pickup rpress-allowed-pickup-hrs rpress-hrs rp-form-control" id="rpress-pickup-hours" name="rpress_allowed_hours">
       <?php if( is_array( $store_timings ) ) :
         foreach( $store_timings as $key => $time ) :
          $loop_time = gmdate( $time_format, $time );

          // Apply your custom hook to potentially remove certain times
          $filtered_time = apply_filters( 'rpress_store_delivery_timings_slot_remaining', $loop_time );

          // Skip this iteration if the filtered time is empty (indicating it's been "removed")
          if ( empty( $filtered_time ) ) {
            continue;
          }

          if(class_exists('RPRESS_SlotLimit')){
            ?>
            <option value='<?php echo $loop_time; ?>' <?php selected( $selected_time,$filtered_time,$asap_option, true ); ?>>
              <?php echo $filtered_time; ?>
            </option><?php
          } else {
            ?><option value='<?php echo ( $asap_option && $key == 0 ) ?  'ASAP' . esc_html( $pickup_asap_text ) : $filtered_time; ?>'<?php selected( $selected_time, $filtered_time, $asap_option, true ); ?>><?php echo ( $asap_option && $key == 0 ) ? __( 'ASAP' , 'restropress' ) . ' ' . esc_html( $pickup_asap_text ) : $filtered_time; ?></option><?php
          }
        endforeach; ?>
       <?php endif; ?>
      </select>
    <?php endif; ?>
    <?php do_action( 'rpress_after_service_time', 'pickup' ); ?>
  </div>
  <!-- Pickup Time Wrap Ends -->
</div>