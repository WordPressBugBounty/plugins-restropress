<?php
/**
 * Pickup tab – time selection
 * Context-driven (no cookies, no recomputation)
 */

$context = rpress_get_service_context();

[
    'service_type'  => $current_service,
    'store_timings' => $store_timings,
    'selected_time' => $selected_time,
] = $context;
?>

<div class="tab-pane fade delivery-settings-wrapper"
     id="nav-pickup"
     role="tabpanel"
     aria-labelledby="nav-pickup-tab">

    <div class="rpress-pickup-time-wrap rpress-time-wrap">

        <?php do_action('rpress_before_service_time', 'pickup'); ?>

        <?php if (rpress_is_service_enabled('pickup')) : ?>

            <div class="pickup-time-text">
                <?php
                echo esc_html(
                    apply_filters(
                        'rpress_pickup_time_string',
                        __('Select a pickup time', 'restropress')
                    )
                );
                ?>
            </div>

            <?php
            $asap_option       = rpress_get_option('enable_asap_option', '');
            $asap_option_only  = rpress_get_option('enable_asap_option_only', '');
            $pickup_asap_text  = rpress_get_option('pickup_asap_text', '');

            // ASAP-only mode
            if ($asap_option_only == 1 && is_array($store_timings)) {
                array_splice($store_timings, 1);
            }
           
            ?>

            <select
                class="rpress-pickup rpress-allowed-pickup-hrs rpress-hrs rp-form-control"
                id="rpress-pickup-hours"
                name="rpress_allowed_hours">

                <?php if (is_array($store_timings)) : ?>
                    <?php foreach ($store_timings as $key => $time) : ?>

                        <?php
                        // Allow slot filtering
                        $filtered_time = apply_filters(
                            'rpress_store_delivery_timings_slot_remaining',
                            $time
                        );

                        if (empty($filtered_time)) {
                            continue;
                        }

                        $is_asap = $asap_option && $key === 0;
                        ?>

                        <?php if (class_exists('RPRESS_SlotLimit')) : ?>

                            <option
                                value="<?php echo esc_attr($filtered_time); ?>"
                                <?php selected($selected_time, $filtered_time); ?>
                            >
                                <?php echo esc_html($filtered_time); ?>
                            </option>

                        <?php else : ?>

                            <option
                                value="<?php echo esc_attr(
                                    $is_asap
                                        ? 'ASAP' . $pickup_asap_text
                                        : $filtered_time
                                ); ?>"
                                <?php selected($selected_time, $filtered_time); ?>
                            >
                                <?php echo esc_html(
                                    $is_asap
                                        ? __('ASAP', 'restropress') . ' ' . $pickup_asap_text
                                        : $filtered_time
                                ); ?>
                            </option>

                        <?php endif; ?>

                    <?php endforeach; ?>
                <?php endif; ?>

            </select>

        <?php endif; ?>

        <?php do_action('rpress_after_service_time', 'pickup'); ?>

    </div>
</div>
