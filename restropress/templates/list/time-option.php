<?php
/**
 * List service context
 */
$context = rpress_get_service_context();

[
    "service_type" => $service_type,
    "service_date" => $service_date,
    "service_time" => $service_time,
    "delivery_address" => $delivery_address,
    "is_store_open" => $is_store_open,
    "store_timings" => $store_timings,
    "service_enabled" => $enabled_service,
    "service_date_raw" => $service_date_raw
] = $context;

/**
 * Store closed handling
 */
if ((empty($store_timings) || !$is_store_open) && ($enabled_service === 'delivery_and_pickup')) {
    echo '<p class="rpress_order-address-wrap">' . esc_html(
        rpress_store_closed_message($service_type)
    ) . '</p>';
    return;
}
if ((empty($store_timings) || !$is_store_open) && ($enabled_service !== 'delivery_and_pickup')) {

    return;
}
?>

<p class="rpress_order-address-wrap">
    <?php
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (is_plugin_active('restropress-multilocation/restropress-multilocation.php')):
        ?>
        <svg width="12" height="14" viewBox="0 0 12 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M5.23944 0.0278769C4.12912 0.142737 3.09265 0.569361 2.19018 1.28587C1.90576 1.51012 1.38342 2.0434 1.17558 2.32234C0.122693 3.73075 -0.2465 5.50834 0.163715 7.17655C0.308657 7.76452 0.483682 8.2185 0.792711 8.81741C1.62681 10.4419 3.17196 12.162 5.06441 13.5841Z"
                fill="black" />
            <path
                d="M5.46348 3.52606C4.55007 3.69014 3.83356 4.33008 3.58196 5.19973C3.49992 5.48688 3.48077 5.98734 3.54367 6.2909Z"
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

    <span id="deliveryTime">
        <?php echo esc_html($service_time); ?>
    </span>

    <a id="editDateTime">
        <?php esc_html_e('Edit', 'restropress'); ?>
    </a>
</p>