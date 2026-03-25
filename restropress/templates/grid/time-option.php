<?php
/**
 * Grid service context
 */
$context = rpress_get_service_context();

[
    'service_type' => $service_type,
    'service_date' => $service_date,
    'service_time' => $service_time,
    'selected_time' => $selected_time,
    'delivery_address' => $delivery_address,
    'is_store_open' => $is_store_open,
    'service_enabled' => $enabled_service,
    'store_timings' => $store_timings,
    'service_date_raw' => $service_date_raw,
] = $context;

$hide_service_time = function_exists( 'rp_otil_is_service_time_hidden' )
    ? rp_otil_is_service_time_hidden( $service_type )
    : false;

$preorder_enabled = false;
if ( class_exists( 'RP_StoreTiming_Settings' ) ) {
    $location_id = isset( $_COOKIE['branch'] ) ? absint( wp_unslash( $_COOKIE['branch'] ) ) : 0;
    if ( $location_id <= 0 ) {
        $rpress_settings = get_option( 'rpress_settings', array() );
        $location_id = ! empty( $rpress_settings['default_location'] ) ? absint( $rpress_settings['default_location'] ) : 0;
    }
    if ( $location_id <= 0 ) {
        $multi_location_settings = get_option( 'rp_multi_location', array() );
        $location_id = ! empty( $multi_location_settings['default_location'] ) ? absint( $multi_location_settings['default_location'] ) : 0;
    }
    $timing_settings = RP_StoreTiming_Settings::rpress_timing_options( $location_id );
    $preorder_enabled = ( 'delivery' === $service_type && ! empty( $timing_settings['pre_order'] ) )
        || ( 'pickup' === $service_type && ! empty( $timing_settings['pre_order_pickup'] ) );
}
/**
 * Store closed handling
 */
if ((empty($store_timings) || !$is_store_open) && ! $preorder_enabled && ($enabled_service === 'delivery_and_pickup')) {

    echo '<p class="rpress_order-address-wrap">' . esc_html(
        rpress_store_closed_message($service_type)
    ) . '</p>';

    return;
}

if ((empty($store_timings) || !$is_store_open) && ! $preorder_enabled && ($enabled_service !== 'delivery_and_pickup')) {
    return;
}
?>

<p class="rpress_order-address-wrap rpress-order-address-inline">
    <span class="rpress-order-address-summary">
    <?php
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (is_plugin_active('restropress-multilocation/restropress-multilocation.php')):
        ?>
        <svg class="rpress-location-pin" width="12" height="14" viewBox="0 0 12 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path
                d="M5.23944 0.0278769C4.12912 0.142737 3.09265 0.569361 2.19018 1.28587C1.90576 1.51012 1.38342 2.0434 1.17558 2.32234C0.122693 3.73075 -0.2465 5.50834 0.163715 7.17655C0.308657 7.76452 0.483682 8.2185 0.792711 8.81741C1.62681 10.4419 3.17196 12.162 5.06441 13.5841C5.46642 13.8849 5.677 13.9998 5.82741 13.9998C5.97235 13.9998 6.19113 13.8849 6.53845 13.6251C8.19025 12.3863 9.57677 10.9369 10.4792 9.49563C10.7336 9.09362 11.111 8.33609 11.2532 7.95322C11.5321 7.19569 11.6525 6.55029 11.6525 5.81737C11.6525 3.84834 10.6597 2.03519 8.97239 0.927614C7.9113 0.230249 6.51931 -0.103392 5.23944 0.0278769Z"
                fill="black" />
            <path
                d="M5.46348 3.52606C4.55007 3.69014 3.83356 4.33008 3.58196 5.19973C3.49992 5.48688 3.48077 5.98734 3.54367 6.2909C3.7187 7.16329 4.38598 7.86066 5.26657 8.09585C5.57287 8.17789 6.08153 8.17789 6.38783 8.09585C7.27116 7.86066 7.9357 7.16056 8.11346 6.27723Z"
                fill="black" />
        </svg>

        <span id="deliveryAddress">
            <?php echo esc_html($delivery_address); ?>
        </span>

        <svg width="4" height="4" viewBox="0 0 4 4" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="2" cy="2" r="2" fill="#616161" />
        </svg>
    <?php endif; ?>

    <span id="deliveryDate">
        <?php echo esc_html(!empty($service_date_raw) ? date_i18n('F j', strtotime($service_date_raw)) : $service_date); ?>,
    </span>

    <?php if ( ! $hide_service_time ) : ?>
        <span id="deliveryTime">
            <?php echo esc_html( ! empty( $service_time ) ? $service_time : $selected_time ); ?>
        </span>
    <?php endif; ?>
    </span>

    <a id="editDateTime" class="rpress-edit-datetime-link">
        <?php esc_html_e('Edit', 'restropress'); ?>
    </a>
</p>
