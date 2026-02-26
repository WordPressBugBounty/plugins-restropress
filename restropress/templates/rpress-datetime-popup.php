<?php
/**
 * Unified service context
 */
$context = rpress_get_service_context();

$service_type   = $context['service_type'];
$service_date   = $context['service_date'];
$selected_time  = $context['selected_time'];
$store_timings  = $context['store_timings'];



/**
 * Options
 */
$asap_option        = rpress_get_option('enable_asap_option', '');
$asap_option_only   = rpress_get_option('enable_asap_option_only', '');
$button_style       = rpress_get_option('button_style', 'button');

$asap_text_key      = $service_type . '_asap_text';
$delivery_asap_text = rpress_get_option($asap_text_key, '');

/**
 * Time format
 */
$store_time_format = rpress_get_option('store_time_format');
// $time_format = (!empty($store_time_format) && $store_time_format === '24hrs')
//     ? 'H:i'
//     : 'h:ia';

// $time_format = apply_filters(
//     'rpress_store_time_format',
//     $time_format,
//     $store_time_format
// );

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

                <?php do_action('rpress_after_service_time', $service_type); ?>

                <h3><?php esc_html_e('Time preference', 'restropress'); ?></h3>

                <div class="bg-gray rpress-time-preference-wrap">
                    <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">

                        <label>
                            <span class="rpress-service-label">
                                <?php echo esc_html(rpress_service_label($service_type)); ?>
                            </span>
                            <?php esc_html_e('time', 'restropress'); ?>
                        </label>
                        <select
                            class="rpress-delivery rpress-allowed-delivery-hrs rpress-hrs rp-form-control"
                            id="rpress-delivery-hours"
                            name="rpress_allowed_hours"
                        >
                            <?php if (is_array($store_timings)) : ?>
                               
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

                    <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
                        <?php do_action('rpress_before_service_time', $service_type); ?>
                    </div>
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
