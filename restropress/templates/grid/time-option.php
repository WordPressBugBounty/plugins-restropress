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

$has_service_slots = $hide_service_time || ( is_array( $store_timings ) && ! empty( $store_timings ) );
$show_closed_state = ! $is_store_open || ! $has_service_slots;
$closed_notice = rpress_store_closed_message( $service_type );
$show_edit_link = ! $show_closed_state;
$service_date_display = '';
$is_multilocation_active = false;

if ( ! function_exists( 'is_plugin_active' ) ) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if ( function_exists( 'is_plugin_active' ) ) {
    $is_multilocation_active = is_plugin_active( 'restropress-multilocation/restropress-multilocation.php' );
}
$show_change_location_link = $show_closed_state && $is_multilocation_active;
/**
 * Store closed handling
 */
if ( $show_closed_state && ( 'delivery_and_pickup' !== $enabled_service ) ) {
    return;
}
?>

<p class="rpress_order-address-wrap rpress-order-address-inline">
    <span class="rpress-order-address-summary">
    <?php
    if ( $is_multilocation_active ):
        ?>
        <span class="rpress-location-pin-wrap" aria-hidden="true">
            <span class="rpress-location-pin-pulse"></span>
            <svg class="rpress-location-pin" width="12" height="12" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" focusable="false">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z"></path>
            </svg>
        </span>

        <span id="deliveryAddress">
            <?php echo esc_html($delivery_address); ?>
        </span>

        <svg width="4" height="4" viewBox="0 0 4 4" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="2" cy="2" r="2" fill="#616161" />
        </svg>
    <?php endif; ?>

    <?php if ( $show_closed_state ) : ?>
        <span id="deliveryDate" class="rp-store-timing-notice">
            <?php echo esc_html( $closed_notice ); ?>
        </span>
    <?php else : ?>
        <?php
        if ( ! empty( $service_date_raw ) ) {
            $service_date_display = rpress_format_service_date( $service_date_raw );
        } elseif ( ! empty( $service_date ) ) {
            $service_date_display = rpress_format_service_date( $service_date );
        }
        ?>
        <span id="deliveryDate">
            <?php echo esc_html( $service_date_display ); ?><?php echo ! empty( $service_date_display ) ? ',' : ''; ?>
        </span>

        <?php if ( ! $hide_service_time && ! empty( $service_date_display ) ) : ?>
            <?php
            $service_time_value = ! empty( $service_time ) ? $service_time : $selected_time;
            $service_time_display = rpress_format_service_time( $service_time_value, $service_date_raw );
            ?>
            <span id="deliveryTime">
                <?php echo esc_html( $service_time_display ); ?>
            </span>
        <?php else : ?>
            <span id="deliveryTime"></span>
        <?php endif; ?>
    <?php endif; ?>
    </span>

    <?php if ( $show_change_location_link ) : ?>
        <a id="editDateTime" class="rpress-change-location-link">
            <?php esc_html_e( 'Change location', 'restropress' ); ?>
        </a>
    <?php elseif ( $show_edit_link ) : ?>
        <a id="editDateTime" class="rpress-edit-datetime-link">
            <?php esc_html_e('Edit', 'restropress'); ?>
        </a>
    <?php endif; ?>
</p>
