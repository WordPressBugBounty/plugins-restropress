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

// Time variables
$store_times        = rp_get_store_timings(true, 'delivery');
$store_timings      = apply_filters('rpress_store_delivery_timings', $store_times);
$store_time_format  = rpress_get_option('store_time_format');
$time_format        = !empty($store_time_format) && $store_time_format == '24hrs' ? 'H:i' : 'h:ia';
$time_format        = apply_filters('rpress_store_time_format', $time_format, $store_time_format);
$default_time       = rpress_get_option('default_time');
// Convert default_time to DateTime and format it
if ( ! empty( $default_time ) ) {
    // Try parsing both with and without AM/PM
    $date_obj = DateTime::createFromFormat('h:ia', strtolower($default_time));
    if ( ! $date_obj ) {
        $date_obj = DateTime::createFromFormat('H:i', $default_time);
    }

    if ( $date_obj ) {
        $default_time = $date_obj->format($time_format);
    }
}
$selected_time = (isset($_COOKIE['service_time']) && !empty($_COOKIE['service_time'])) 
    ? $_COOKIE['service_time'] 
    : $default_time;
$selected_date      = isset($_COOKIE['service_date']) ? $_COOKIE['service_date'] : date('Y-m-d');

$asap_option        = rpress_get_option('enable_asap_option', '');
$asap_option_only   = rpress_get_option('enable_asap_option_only', '');
$key = $default . '_asap_text';
$delivery_asap_text = rpress_get_option( $key, '' );

// If "ASAP Only", keep only first slot
if ($asap_option_only == 1) {
    array_splice($store_timings, 1);
}
$button_style = rpress_get_option('button_style', 'button');
?>


<div class="modal micromodal-slide rpress-edit-address-popup" id="rpressDateTime" aria-hidden="true">
    <div class="modal__overlay" tabindex="-1" data-micromodal-close>
        <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="rpressDateTime-title">
            <header class="modal__header">
                <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
            </header>
            <main class="modal__content" id="rpressDateTime-content">
                <!-- <p>Adewunmi Adudu street</p>
                <span>Ajao Estate, Logos.</span> -->

                <?php do_action( 'rpress_after_service_time', $default ); ?>
                <h3>Time preference</h3>
                <div class="bg-gray rpress-time-preference-wrap">
                    <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
                        <label>
                            <span class="rpress-service-label"><?php esc_html_e('Delivery', 'restropress'); ?></span>
                            <?php esc_html_e('time', 'restropress'); ?>
                        </label>
                        <select class="rpress-delivery rpress-allowed-delivery-hrs rpress-hrs rp-form-control"
                                id="rpress-delivery-hours"
                                name="rpress_allowed_hours">
                            <?php
                            if (is_array($store_timings)) :
                                foreach ($store_timings as $key => $time) :
                                    $loop_time = gmdate($time_format, $time);

                                    // Allow hooks to hide time slots dynamically
                                    $filtered_time = apply_filters('rpress_store_delivery_timings_slot_remaining', $loop_time);
                                    if (empty($filtered_time)) {
                                        continue;
                                    }

                                    // Build option label and value
                                    if (class_exists('RPRESS_SlotLimit')) {
                                        ?>
                                        <option value="<?php echo esc_attr($filtered_time); ?>"
                                            <?php if ( $selected_time == $filtered_time || $asap_option == $filtered_time ) echo 'selected'; ?>>
                                            <?php echo esc_html($filtered_time); ?>
                                        </option>
                                        <?php
                                    } else {
                                        $is_asap = $asap_option && $key == 0;
                                        $value = $is_asap ? 'ASAP' . esc_html($delivery_asap_text) : esc_attr($filtered_time);
                                        $label = $is_asap ? __('ASAP', 'restropress') . ' ' . esc_html($delivery_asap_text) : esc_html($filtered_time);
                                        ?>
                                        <option value="<?php echo $value; ?>"
                                            <?php if ( $selected_time == $filtered_time || $asap_option == $filtered_time ) echo 'selected'; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                        <?php
                                    }
                                endforeach;
                            endif;
                            ?>
                        </select>
                    </div>

                    <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
                        <?php do_action( 'rpress_before_service_time', $default ); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default rpress-editaddress-cancel-btn <?php echo esc_attr($button_style); ?>" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="rpress-editaddress-submit-btn <?php echo esc_attr($button_style); ?>"><span class="rp-ajax-toggle-text">Update</span></button>
                </div>
            </main>
        </div>
    </div>
</div>