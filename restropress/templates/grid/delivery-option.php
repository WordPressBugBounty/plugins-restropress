<?php
global $rpress_options;
$service_type = rpress_get_option('enable_service', 'delivery_and_pickup');
$services = $service_type == 'delivery_and_pickup' ? ['delivery', 'pickup'] : [$service_type];
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
$current_time = current_time('timestamp');
if (empty(rpress_get_option('enable_always_open'))) {
    $open_time = !empty(rpress_get_option('open_time')) ? rpress_get_option('open_time') : '9:00am';
    $close_time = !empty(rpress_get_option('close_time')) ? rpress_get_option('close_time') : '11:30pm';
} else {
    $open_time = '12:00am';
    $close_time = '11:59pm';
}
$open_time = strtotime(date_i18n('Y-m-d') . ' ' . $open_time);
$close_time = strtotime(date_i18n('Y-m-d') . ' ' . $close_time);
//If empty check if pickup hours are available
if (empty($store_times)) {
    $store_times = apply_filters('rpress_store_pickup_timings', $store_time);
}
$closed_message = rpress_get_option('store_closed_msg', __('Sorry, we are closed for ordering now.', 'restropress'));
?>
<div class="rpress-delivery-wrap">
    <?php if (empty($store_times) || ($current_time < $open_time)): ?>
        <div class="alert alert-warning">
            <?php echo wp_kses_post($closed_message); ?>
        </div>
    <?php else: ?>
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
                                <?php echo apply_filters('rpress_modify_service_label', rpress_service_label($service)); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>