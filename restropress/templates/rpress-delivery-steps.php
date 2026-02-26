<?php
/**
 * Delivery / Pickup Service Selector
 *
 * This template relies ONLY on rpress_get_service_context().
 * No cookies, no duplicated logic.
 */

$context = rpress_get_service_context();
[
    'service_type' => $current_service,
    'services' => $services,
    'store_timings' => $store_timings,
    'is_store_open' => $is_store_open,
] = $context;
?>
<div class="rpress-delivery-wrap">
    <?php if (empty($store_timings) || !$is_store_open): ?>
        <div class="alert alert-warning">
            <?php echo wp_kses_post(
                rpress_store_closed_message($current_service)
            ); ?>
        </div>
    <?php else: ?>
        <div class="rpress-row">
            <!-- Error Message -->
            <div class="alert alert-warning rpress-errors-wrap disabled"></div>
            <?php
            /**
             * Location field (multilocation / delivery address)
             */
            do_action('rpress_delivery_location_field');
            ?>
            <div class="rpress-tabs-wrapper rpress-delivery-options text-center
                service-option-<?php echo esc_attr($current_service); ?>">
                <!-- Service Tabs -->
                <ul class="nav nav-pills" id="rpressdeliveryTab" role="tablist">
                    <?php foreach ($services as $service): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link single-service-selected <?php echo $service === $current_service ? 'active' : ''; ?>"
                                id="nav-<?php echo esc_attr($service); ?>-tab"
                                data-service-type="<?php echo esc_attr($service); ?>" data-toggle="tab"
                                href="#nav-<?php echo esc_attr($service); ?>" role="tab"
                                aria-controls="nav-<?php echo esc_attr($service); ?>"
                                aria-selected="<?php echo $service === $current_service ? 'true' : 'false'; ?>">
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
                <!-- Tab Content -->
                <div class="tab-content" id="rpress-tab-content">
                    <?php
                    /**
                     * Load service-specific templates
                     * rpress-delivery.php / rpress-pickup.php
                     */
                    foreach ($services as $service) {
                        rpress_get_template_part('rpress', $service);
                    }
                    ?>
                    <a href="javascript:void(0);" class="btn btn-primary btn-block rpress-delivery-opt-update">
                        <span class="rp-ajax-toggle-text">
                            <?php esc_html_e('Update', 'restropress'); ?>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>