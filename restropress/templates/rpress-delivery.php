<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delivery tab – time selection
 * Context-driven (no cookies, no recomputation)
 */

$active_context = rpress_get_service_context();
$context        = rpress_get_service_context( 'delivery' );

[
    'service_type'   => $service_type,
    'store_timings'  => $store_timings,
    'selected_time'  => $selected_time,
    'is_store_open'  => $is_store_open,
] = $context;

$current_service = $active_context['service_type'];
?>

<?php $delivery_pane_classes = 'tab-pane fade delivery-settings-wrapper'; ?>
<?php if ( 'delivery' === $current_service ) : ?>
    <?php $delivery_pane_classes .= ' active show'; ?>
<?php endif; ?>

<div class="<?php echo esc_attr( $delivery_pane_classes ); ?>"
     id="nav-delivery"
     role="tabpanel"
     aria-labelledby="nav-delivery-tab">

    <div class="rpress-delivery-time-wrap rpress-time-wrap">
        <?php if ( empty( $store_timings ) || ! $is_store_open ) : ?>
            <div class="alert alert-warning rpress-service-closed-message">
                <?php echo esc_html( rpress_store_closed_message( 'delivery' ) ); ?>
            </div>
        <?php elseif (rpress_is_service_enabled('delivery')) : ?>
            <?php do_action('rpress_before_service_time', 'delivery'); ?>

            <div class="delivery-time-text">
                <?php
                echo esc_html(
                    apply_filters(
                        'rpress_delivery_time_string',
                        __('Select a delivery time', 'restropress')
                    )
                );
                ?>
            </div>

            <?php
            $asap_option        = rpress_get_option('enable_asap_option', '');
            $asap_option_only   = rpress_get_option('enable_asap_option_only', '');
            $delivery_asap_text = rpress_get_option('delivery_asap_text', '');

            // ASAP-only mode: keep only first slot
            if ($asap_option_only == 1 && is_array($store_timings)) {
                array_splice($store_timings, 1);
            }
           
            ?>

            <select
                class="rpress-delivery rpress-allowed-delivery-hrs rpress-hrs rp-form-control"
                id="rpress-delivery-hours"
                name="rpress_allowed_hours"
                aria-label="<?php esc_attr_e('Delivery time', 'restropress'); ?>">

                <?php if (is_array($store_timings)) : ?>
                    <?php foreach ($store_timings as $key => $time) : ?>

                        <?php
                        // Allow dynamic slot removal
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
                                        ? 'ASAP' . $delivery_asap_text
                                        : $filtered_time
                                ); ?>"
                                <?php selected($selected_time, $filtered_time); ?>
                            >
                                <?php echo esc_html(
                                    $is_asap
                                        ? __('ASAP', 'restropress') . ' ' . $delivery_asap_text
                                        : $filtered_time
                                ); ?>
                            </option>

                        <?php endif; ?>

                    <?php endforeach; ?>
                <?php endif; ?>

            </select>

            <?php do_action('rpress_after_service_time', 'delivery'); ?>
        <?php endif; ?>
    </div>
</div>
