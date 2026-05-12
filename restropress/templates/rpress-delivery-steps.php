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

$current_service_label = apply_filters(
	'rpress_modify_service_label',
	rpress_service_label( $current_service )
);
$current_service_label = trim( wp_strip_all_tags( (string) $current_service_label ) );
$service_section_heading = apply_filters(
	'rpress_checkout_service_section_heading',
	$current_service_label,
	$current_service,
	$enabled_service
);
$service_section_heading = trim( wp_strip_all_tags( (string) $service_section_heading ) );

$button_style = sanitize_html_class( rpress_get_option( 'button_style', 'th-rounded' ) );
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

$location_field_markup         = '';
$location_field_plain          = '';
$location_field_has_form_input = false;

if ( $show_service_tabs ) {
	ob_start();
	do_action( 'rpress_delivery_location_field' );
	$location_field_markup = trim( (string) ob_get_clean() );
	$location_field_plain  = trim( wp_strip_all_tags( $location_field_markup ) );

	$location_field_has_form_input = (bool) preg_match( '/<(select|textarea|button)\b/i', $location_field_markup );

	if ( ! $location_field_has_form_input && preg_match_all( '/<input\b[^>]*>/i', $location_field_markup, $input_matches ) ) {
		foreach ( $input_matches[0] as $input_tag ) {
			$input_type = '';
			if ( preg_match( '/\btype\s*=\s*["\']?([a-zA-Z0-9_-]+)["\']?/i', $input_tag, $type_match ) ) {
				$input_type = strtolower( (string) $type_match[1] );
			}

			// Treat hidden-only fields as non-interactive for the mobile service modal.
			if ( '' === $input_type || 'hidden' !== $input_type ) {
				$location_field_has_form_input = true;
				break;
			}
		}
	}
}

$show_location_field = '' !== $location_field_markup && ( $location_field_has_form_input || '' !== $location_field_plain );
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
            <?php if ( $show_location_field ) : ?>
                <div class="rpress-delivery-location-field-wrap">
                    <?php echo $location_field_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
            <div class="rpress-tabs-wrapper rpress-delivery-options text-center
                service-option-<?php echo esc_attr($current_service); ?>">
                <div class="rpress-checkout-service-head">
                    <h6 class="rpress-checkout-service-heading is-service-<?php echo esc_attr( $current_service ); ?>">
                        <span class="rpress-checkout-service-heading-icon" aria-hidden="true">
                            <span class="service-icon-delivery">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 8h11v8H3z"></path>
                                    <path d="M14 11h3l2 2v3h-5z"></path>
                                    <circle cx="7" cy="18" r="1.7"></circle>
                                    <circle cx="17" cy="18" r="1.7"></circle>
                                </svg>
                            </span>
                            <span class="service-icon-pickup">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 2h12l1.1 6.3a2 2 0 0 1-2 2.3H6.9a2 2 0 0 1-2-2.3L6 2z"></path>
                                    <path d="M8 10.5V7.8a4 4 0 0 1 8 0v2.7"></path>
                                </svg>
                            </span>
                        </span>
                        <span class="rpress-checkout-service-heading-text"><?php echo esc_html( $service_section_heading ); ?></span>
                    </h6>
                </div>
                <!-- Service Tabs -->
                <ul class="nav nav-pills" id="rpressdeliveryTab" role="tablist">
                    <?php foreach ($services as $service): ?>
                        <?php $is_active = ($service === $current_service); ?>
                        <?php
                        $filtered_label = apply_filters(
                            'rpress_modify_service_label',
                            rpress_service_label($service)
                        );
                        $tab_label_text = trim( wp_strip_all_tags( (string) $filtered_label ) );
                        if ( '' === $tab_label_text ) {
                            $tab_label_text = ucfirst( (string) $service );
                        }
                        $tab_icon_class = ( 'pickup' === $service ) ? 'fa fa-shopping-bag' : 'fa fa-truck';
                        ?>
                        <li class="nav-item <?php echo $is_active ? 'active' : ''; ?>" role="presentation">
                            <a class="nav-link single-service-selected <?php echo $service === $current_service ? 'active' : ''; ?>"
                                id="nav-<?php echo esc_attr($service); ?>-tab"
                                data-service-type="<?php echo esc_attr($service); ?>" data-toggle="tab"
                                href="#nav-<?php echo esc_attr($service); ?>" role="tab"
                                aria-controls="nav-<?php echo esc_attr($service); ?>"
                                aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
                                <span class="rpress-service-tab-inner">
                                    <i class="rpress-service-tab-icon <?php echo esc_attr( $tab_icon_class ); ?>" aria-hidden="true"></i>
                                    <span class="rpress-service-tab-label"><?php echo esc_html( $tab_label_text ); ?></span>
                                </span>
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
                    <a href="javascript:void(0);" class="btn btn-primary btn-block rpress-delivery-opt-update <?php echo esc_attr( $button_style ); ?>" data-food-id="{fooditem_id}">
                        <span class="rp-ajax-toggle-text">
                            <?php esc_html_e('Update', 'restropress'); ?>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
