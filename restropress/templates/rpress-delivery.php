<div class="tab-pane fade delivery-settings-wrapper" id="nav-delivery" role="tabpanel" aria-labelledby="nav-delivery-tab">
	<!-- Delivery Time Wrap -->
	<div class="rpress-delivery-time-wrap rpress-time-wrap">
	<?php do_action( 'rpress_before_service_time', 'delivery' ); ?>
	<?php
	if ( rpress_is_service_enabled( 'delivery' ) ) :
		$store_times        = rp_get_store_timings( true, 'delivery' );
		$cookie_service = isset($_COOKIE['service_type']) ? sanitize_text_field($_COOKIE['service_type']) : '';
		$service_date = isset($_COOKIE['service_date']) ? sanitize_text_field($_COOKIE['service_date']) : '';

		$store_timings      = apply_filters( 'rpress_store_timings', $store_times, $cookie_service, $service_date );
		$store_time_format  = rpress_get_option( 'store_time_format' );
		$time_format        = ! empty( $store_time_format ) && $store_time_format == '24hrs' ? 'H:i' : 'h:ia';
		$time_format        = apply_filters( 'rpress_store_time_format', $time_format, $store_time_format );
		$selected_time      = isset( $_COOKIE['service_time'] ) ? $_COOKIE['service_time'] : '';
		$asap_option        = rpress_get_option( 'enable_asap_option', '' );
		$asap_option_only   = rpress_get_option( 'enable_asap_option_only', '' );
		$delivery_asap_text = rpress_get_option( 'delivery_asap_text', '' );
		?>
		<div class="delivery-time-text">
		<?php echo esc_html( apply_filters( 'rpress_delivery_time_string', esc_html_e( 'Select a delivery time', 'restropress' ) ) ); ?>
		</div>
		<?php
		if ( $asap_option_only == 1 ) {
			array_splice( $store_timings, 1 );
		}
		?>
			<select class="rpress-delivery rpress-allowed-delivery-hrs rpress-hrs rp-form-control" id="rpress-delivery-hours" name="rpress_allowed_hours">
		<?php
		if ( is_array( $store_timings ) ) :
			foreach ( $store_timings as $key => $time ) :
				//$loop_time = gmdate( $time_format, $time );

				// Apply your custom hook to potentially remove certain times
				 $filtered_time = apply_filters( 'rpress_store_delivery_timings_slot_remaining', $time );

				// Skip this iteration if the filtered time is empty (indicating it's been "removed")
				if ( empty( $filtered_time ) ) {
					continue;
				}

				if ( class_exists( 'RPRESS_SlotLimit' ) ) {
					?>
				<option value='<?php echo esc_attr( $filtered_time ); ?>' <?php selected( $selected_time, $filtered_time, $asap_option, true ); ?>>
					<?php echo esc_html( $filtered_time ); ?>
				</option>
					<?php
				} else {
					?>
				<option value='<?php echo ( $asap_option && $key == 0 ) ? 'ASAP' . esc_html( $delivery_asap_text ) : esc_attr( $filtered_time ); ?>' <?php selected( $selected_time, $filtered_time, $asap_option, true ); ?>>
					<?php echo ( $asap_option && $key == 0 ) ? esc_html__( 'ASAP', 'restropress' ) . ' ' . esc_html( $delivery_asap_text ) : esc_html( $filtered_time ); ?>
				</option>
					<?php
				}
			endforeach;
		endif;
		?>
		</select>
	<?php endif; ?>
	<?php do_action( 'rpress_after_service_time', 'delivery' ); ?>
	</div>
	<!-- Delivery Time Wrap Ends -->
</div>
