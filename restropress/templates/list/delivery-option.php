<?php
global $rpress_options;
$service_type = rpress_get_option('enable_service', 'delivery_and_pickup');
$services = $service_type == 'delivery_and_pickup' ? ['delivery', 'pickup'] : [$service_type];
$services = apply_filters('rpress_enable_services', $services);
$service_date = isset($_COOKIE['service_date']) ? sanitize_text_field($_COOKIE['service_date']) : '';


// Check for cookie first
$cookie_service = isset($_COOKIE['service_type']) ? sanitize_text_field($_COOKIE['service_type']) : '';

// If the cookie value is a valid service, use it; otherwise fallback to default
if ($cookie_service && in_array($cookie_service, $services, true)) {
    $default = $cookie_service;
} else {
    $default = !empty(rpress_get_option('default_service')) ? rpress_get_option('default_service') : 'delivery';
}

$store_time = rp_get_store_timings(true, '');
$store_times = apply_filters('rpress_store_delivery_timings', $store_time);

// If empty check if pickup hours are available
if (empty($store_times)) {
    $store_times = apply_filters('rpress_store_pickup_timings', $store_time);
}

$service_date_raw = apply_filters( "rpress_service_date_raw", $service_date, $cookie_service );
// Format date if it exists, else use today's date
if (!empty($service_date_raw)) {
    // Convert from Y-m-d (cookie) to F j (e.g., July 9)
    $date_object = DateTime::createFromFormat('Y-m-d', $service_date_raw);
    $service_date = $date_object ? $date_object->format('F j') : date_i18n('F j');
} else {
    $service_date = date_i18n('F j');
}

// Determine if we should show the service tabs
$show_service_tabs = false;
$closed_message_display = '';

if ($service_type === 'delivery_and_pickup') {
    // Always show switch for delivery_and_pickup
    $show_service_tabs = true;
} else {
    // For individual services, check if store is open
    $current_service = $services[0];
    if (empty($store_times) || !rpress_is_store_open($current_service, $service_date)) {
        $closed_message_display = rpress_store_closed_message($current_service);
    } else {
        $show_service_tabs = true;
    }
}
?>
<div class="rpress-delivery-wrap">
    <?php if (!$show_service_tabs && !empty($closed_message_display)): ?>
        <div class="alert alert-warning">
            <?php echo wp_kses_post($closed_message_display); ?>
        </div>
    <?php elseif ($show_service_tabs): ?>
        <div class="rpress-row">
            <!-- Error Message Starts Here -->
            <div class="alert alert-warning rpress-errors-wrap disabled"></div>
            <!-- Error Message Ends Here -->
            <?php do_action('rpress_delivery_location_field'); ?>
            <div class="rpress-tabs-wrapper rpress-delivery-options text-center service-option-<?php echo esc_attr($service_type); ?>">
                <ul class="nav nav-pills order-online-servicetabs" id="rpressdeliveryTab">
                    <?php foreach ($services as $service):             
                        $is_active = $service === $default; ?>
                        <li class="nav-item <?php echo $is_active ? 'active' : ''; ?>">
                            <a class="nav-link single-service-selected" id="nav-<?php echo esc_attr($service); ?>-tab"
                                data-service-type="<?php echo esc_attr($service); ?>" data-toggle="tab"
                                href="#nav-<?php echo esc_attr($service); ?>" role="tab"
                                aria-controls="nav-<?php echo esc_attr($service); ?>" aria-selected="false">
                                <?php echo esc_html( apply_filters( 'rpress_modify_service_label', rpress_service_label( $service ) ) ); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>