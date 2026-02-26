<?php
/**
 * Service tabs (Delivery / Pickup)
 * Modern context-driven version
 * NO cookies, NO recomputation, NO duplication
 */

$context = rpress_get_service_context();

[
    'service_type'  => $current_service,
    'services'      => $services,
    'service_date'  => $service_date,
    'store_timings' => $store_timings,
    'is_store_open' => $is_store_open,
    'service_enabled' => $enabled_service
] = $context;

/**
 * Determine whether to show service tabs
 * (Preserves original behavior exactly)
 */
$show_service_tabs      = false;
$closed_message_display = '';

if ($enabled_service === 'delivery_and_pickup') {
    // Always show tabs when both services are enabled
    $show_service_tabs = true;
} else {
    // Single service mode → show only if store is open and timings exist
    if (empty($store_timings) || !$is_store_open) {
        $closed_message_display = rpress_store_closed_message($current_service);
    } else {
        $show_service_tabs = true;
    }
}
?>

<div class="rpress-delivery-wrap">

    <?php if (!$show_service_tabs && !empty($closed_message_display)) : ?>

        <div class="alert alert-warning">
            <?php echo wp_kses_post($closed_message_display); ?>
        </div>

    <?php elseif ($show_service_tabs) : ?>

        <div class="rpress-row">

            <!-- Error Message Starts Here -->
            <div class="alert alert-warning rpress-errors-wrap disabled"></div>
            <!-- Error Message Ends Here -->

            <?php
            /**
             * Delivery location / branch selector
             * (multilocation compatible)
             */
            do_action('rpress_delivery_location_field');
            ?>

            <div class="rpress-tabs-wrapper rpress-delivery-options text-center
                service-option-<?php echo esc_attr($current_service); ?>">

                <ul class="nav nav-pills order-online-servicetabs"
                    id="rpressdeliveryTab"
                    role="tablist">

                    <?php foreach ($services as $service) : ?>
                        <?php $is_active = ($service === $current_service); ?>

                        <li class="nav-item <?php echo $is_active ? 'active' : ''; ?>" role="presentation">
                            <a
                                class="nav-link single-service-selected <?php echo $is_active ? 'active' : ''; ?>"
                                id="nav-<?php echo esc_attr($service); ?>-tab"
                                data-service-type="<?php echo esc_attr($service); ?>"
                                data-toggle="tab"
                                href="#nav-<?php echo esc_attr($service); ?>"
                                role="tab"
                                aria-controls="nav-<?php echo esc_attr($service); ?>"
                                aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                            >
                                <?php
                                  $filtered_label = apply_filters(
                                    'rpress_modify_service_label',
                                    rpress_service_label($service)
                                );
                                echo wp_kses(
                                    $filtered_label,
                                    array(
                                        'i' => array('class' => true, 'style' => true),
                                        "br" => array(),
                                        "span" => array('class' => true, 'style' => true),
                                        )
                                );
                                ?>
                            </a>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        </div>

    <?php endif; ?>

</div>
