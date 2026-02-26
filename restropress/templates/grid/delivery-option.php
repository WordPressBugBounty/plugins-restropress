<?php
/**
 * Service Tabs Wrapper (Delivery / Pickup)
 * Context-driven, no cookie or logic duplication
 */

$context = rpress_get_service_context();

[
    'service_type' => $current_service,
    'services' => $services,
    'service_date' => $service_date,
    'store_timings' => $store_timings,
    'is_store_open' => $is_store_open,
    'service_enabled' => $service_enabled
] = $context;

/**
 * Determine visibility
 */
$show_service_tabs = false;
$closed_message = '';

if ($service_enabled === 'delivery_and_pickup') {
    $show_service_tabs = true;
} else {
    if (!empty($store_timings) && $is_store_open) {
        $show_service_tabs = true;
    } else {
        $closed_message = rpress_store_closed_message($current_service);
    }
}
?>

<div class="rpress-delivery-wrap">

    <?php if (!$show_service_tabs && !empty($closed_message)): ?>
        <div class="alert alert-warning">
            <?php echo wp_kses_post($closed_message); ?>
        </div>

    <?php elseif ($show_service_tabs): ?>

        <div class="rpress-row">

            <!-- Error Message -->
            <div class="alert alert-warning rpress-errors-wrap disabled"></div>

            <?php
            /**
             * Location / branch selector
             */
            do_action('rpress_delivery_location_field');
            ?>

            <div class="rpress-tabs-wrapper rpress-delivery-options text-center
                service-option-<?php echo esc_attr($current_service); ?>">

                <ul class="nav nav-pills order-online-servicetabs" id="rpressdeliveryTab" role="tablist">

                    <?php foreach ($services as $service): ?>
                        <?php $is_active = ($service === $current_service); ?>

                        <li class="nav-item <?php echo $is_active ? 'active' : ''; ?>" role="presentation">
                            <a class="nav-link single-service-selected <?php echo $is_active ? 'active' : ''; ?>"
                                id="nav-<?php echo esc_attr($service); ?>-tab"
                                data-service-type="<?php echo esc_attr($service); ?>" data-toggle="tab"
                                href="#nav-<?php echo esc_attr($service); ?>" role="tab"
                                aria-controls="nav-<?php echo esc_attr($service); ?>"
                                aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
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
