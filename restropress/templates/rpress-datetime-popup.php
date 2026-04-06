<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified service context
 */
$context = rpress_get_service_context();

$service_type   = $context['service_type'];
$service_date   = $context['service_date'];
$selected_time  = $context['selected_time'];
$store_timings  = $context['store_timings'];
$is_store_open  = $context['is_store_open'];



/**
 * Options
 */
$asap_option        = rpress_get_option('enable_asap_option', '');
$asap_option_only   = rpress_get_option('enable_asap_option_only', '');
$button_style       = rpress_get_option('button_style', 'button');
$has_store_timings  = is_array($store_timings) && !empty($store_timings);
$closed_notice      = rpress_store_closed_message($service_type);
$show_update_button = rpress_is_service_enabled( $service_type ) && $has_store_timings;

$asap_text_key      = $service_type . '_asap_text';
$delivery_asap_text = rpress_get_option($asap_text_key, '');
$schedule_heading   = ( 'pickup' === $service_type )
    ? __( 'Choose Your Pickup Schedule', 'restropress' )
    : __( 'Choose Your Delivery Schedule', 'restropress' );
$schedule_subtext   = __( 'Review the available date and time, then click Update to apply your selection.', 'restropress' );

/**
 * ASAP-only handling
 */
if ($asap_option_only == 1 && is_array($store_timings)) {
    array_splice($store_timings, 1);
}
?>

<div class="modal micromodal-slide rpress-edit-address-popup" id="rpressDateTime" aria-hidden="true">
    <div class="modal__overlay" tabindex="-1" data-micromodal-close>
        <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="rpressDateTime-title">

            <header class="modal__header">
                <button
                    class="modal__close"
                    aria-label="<?php esc_attr_e('Close modal', 'restropress'); ?>"
                    data-micromodal-close>
                </button>
            </header>

            <main class="modal__content" id="rpressDateTime-content">

                <div class="rpress-popup-header-copy">
                    <h3 id="rpressDateTime-title"><?php echo esc_html( $schedule_heading ); ?></h3>
                    <p class="rpress-popup-subtext"><?php echo esc_html( $schedule_subtext ); ?></p>
                </div>

                <div class="bg-gray rpress-time-preference-wrap">
                    <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12 rpress-service-type-message">
                        <?php do_action('rpress_popup_service_time', $service_type); ?>
                    </div>
                    <?php if ( empty( $store_timings ) || ! $is_store_open ) : ?>
                        <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
                            <div class="alert alert-warning rpress-service-closed-message rp-store-timing-notice-row" data-service-type="<?php echo esc_attr( $service_type ); ?>">
                                <span class="rp-store-timing-notice"><?php echo esc_html( $closed_notice ); ?></span>
                            </div>
                        </div>
                    <?php elseif(rpress_is_service_enabled($service_type)) : ?>
                        <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
                            <?php do_action('rpress_before_service_time', $service_type); ?>
                        </div>
                        <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12 rpress-service-hours-row">
                            <?php
                            $time_label_text = ( 'pickup' === $service_type )
                                ? apply_filters( 'rpress_pickup_time_string', __( 'Select a pickup time', 'restropress' ) )
                                : apply_filters( 'rpress_delivery_time_string', __( 'Select a delivery time', 'restropress' ) );
                            $time_label_class = ( 'pickup' === $service_type ) ? 'pickup-time-text' : 'delivery-time-text';
                            $time_aria_label  = ( 'pickup' === $service_type ) ? __( 'Pickup time', 'restropress' ) : __( 'Delivery time', 'restropress' );
                            ?>
                            <div class="<?php echo esc_attr( $time_label_class ); ?>">
                                <?php echo esc_html( $time_label_text ); ?>
                            </div>
                            <select
                                class="rpress-delivery rpress-allowed-delivery-hrs rpress-hrs rp-form-control"
                                id="rpress-delivery-hours"
                                name="rpress_allowed_hours"
                                aria-label="<?php echo esc_attr( $time_aria_label ); ?>"
                            >
                                <?php if ($has_store_timings) : ?>
                                    <?php foreach ($store_timings as $index => $time_slot) : ?>

                                        <?php
                                        // Allow hooks to hide time slots dynamically
                                        $filtered_time = apply_filters(
                                            'rpress_store_delivery_timings_slot_remaining',
                                            $time_slot
                                        );

                                        if (empty($filtered_time)) {
                                            continue;
                                        }

                                        $is_asap = ($asap_option && $index === 0);
                                        ?>

                                        <?php if (class_exists('RPRESS_SlotLimit')) : ?>

                                            <option
                                                value="<?php echo esc_attr($time_slot); ?>"
                                                <?php selected($selected_time, $filtered_time); ?>
                                                >
                                                <?php echo esc_html($filtered_time); ?>
                                            </option>

                                        <?php else : ?>

                                            <?php
                                            $option_value = $is_asap
                                                ? 'ASAP' . esc_html($delivery_asap_text)
                                                : esc_attr($filtered_time);

                                            $option_label = $is_asap
                                                ? __('ASAP', 'restropress') . ' ' . esc_html($delivery_asap_text)
                                                : esc_html($filtered_time);
                                            ?>

                                            <option
                                                value="<?php echo esc_attr($option_value); ?>"
                                                <?php selected($selected_time, $filtered_time); ?>
                                            >
                                                <?php echo esc_html($option_label); ?>
                                            </option>

                                        <?php endif; ?>

                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-default rpress-editaddress-cancel-btn <?php echo esc_attr($button_style); ?>"
                        data-dismiss="modal"
                    >
                        <?php esc_html_e('Cancel', 'restropress'); ?>
                    </button>

                    <button
                        type="submit"
                        class="rpress-editaddress-submit-btn <?php echo esc_attr($button_style); ?>"
                        <?php echo $show_update_button ? '' : 'style="display:none;"'; ?>
                    >
                        <span class="rp-ajax-toggle-text">
                            <?php esc_html_e('Update', 'restropress'); ?>
                        </span>
                    </button>
                </div>

            </main>
        </div>
    </div>
</div>
