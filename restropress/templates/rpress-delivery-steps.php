<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
    'service_enabled' => $enabled_service,
] = $context;

$old_ui_ux_enabled = ! empty( rpress_get_option( 'old_ui_ux' ) );
if ( $old_ui_ux_enabled && 'delivery_and_pickup' === $enabled_service ) {
    $services = array( 'delivery', 'pickup' );
}
if ( empty( $services ) || ! in_array( $current_service, $services, true ) ) {
    $current_service = ! empty( $services ) ? $services[0] : 'delivery';
}

$preorder_enabled      = false;
$show_service_tabs      = false;
$closed_message_display = '';

if ( class_exists( 'RP_StoreTiming_Settings' ) ) {
    $location_id = isset( $_COOKIE['branch'] ) ? absint( wp_unslash( $_COOKIE['branch'] ) ) : 0;

    if ( $location_id <= 0 ) {
        $rpress_settings = get_option( 'rpress_settings', array() );
        $location_id     = ! empty( $rpress_settings['default_location'] ) ? absint( $rpress_settings['default_location'] ) : 0;
    }

    if ( $location_id <= 0 ) {
        $multi_location_settings = get_option( 'rp_multi_location', array() );
        $location_id             = ! empty( $multi_location_settings['default_location'] ) ? absint( $multi_location_settings['default_location'] ) : 0;
    }

    $timing_settings  = RP_StoreTiming_Settings::rpress_timing_options( $location_id );
    $preorder_enabled = ( 'delivery' === $current_service && ! empty( $timing_settings['pre_order'] ) )
        || ( 'pickup' === $current_service && ! empty( $timing_settings['pre_order_pickup'] ) );
}

if ( 'delivery_and_pickup' === $enabled_service ) {
    // Show both tabs and let each tab render its own open/closed state.
    $show_service_tabs = true;
} else {
    if ( ( empty( $store_timings ) || ! $is_store_open ) && ! $preorder_enabled ) {
        $closed_message_display = rpress_store_closed_message( $current_service );
    } else {
        $show_service_tabs = true;
    }
}
?>
<div class="rpress-delivery-wrap">
    <?php if ( ! $show_service_tabs && ! empty( $closed_message_display ) ) : ?>
        <div class="alert alert-warning">
            <?php echo wp_kses_post( $closed_message_display ); ?>
        </div>
    <?php elseif ( $show_service_tabs ) : ?>
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
                        <?php $is_active = ($service === $current_service); ?>
                        <li class="nav-item <?php echo $is_active ? 'active' : ''; ?>" role="presentation">
                            <a class="nav-link single-service-selected <?php echo $service === $current_service ? 'active' : ''; ?>"
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
