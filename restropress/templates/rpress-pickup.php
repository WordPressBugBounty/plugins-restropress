<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pickup tab – time selection
 * Context-driven (no cookies, no recomputation)
 */

$active_context = rpress_get_service_context();
$context        = rpress_get_service_context( 'pickup' );

[
    'service_type'  => $service_type,
    'store_timings' => $store_timings,
    'selected_time' => $selected_time,
    'is_store_open' => $is_store_open,
    'service_date_raw' => $service_date_raw,
] = $context;

$current_service = $active_context['service_type'];
$pickup_date_value = ! empty( $service_date_raw ) ? $service_date_raw : rpress_get_wp_now()->format( 'Y-m-d' );
$pickup_date_label = rpress_format_service_date( $pickup_date_value );
?>

<?php $pickup_pane_classes = 'tab-pane fade delivery-settings-wrapper'; ?>
<?php if ( 'pickup' === $current_service ) : ?>
    <?php $pickup_pane_classes .= ' active show'; ?>
<?php endif; ?>

<div class="<?php echo esc_attr( $pickup_pane_classes ); ?>"
     id="nav-pickup"
     role="tabpanel"
     aria-labelledby="nav-pickup-tab">

    <div class="rpress-pickup-time-wrap rpress-time-wrap">
        <?php if ( empty( $store_timings ) || ! $is_store_open ) : ?>
            <div class="alert alert-warning rpress-service-closed-message">
                <?php echo esc_html( rpress_store_closed_message( 'pickup' ) ); ?>
            </div>
        <?php elseif (rpress_is_service_enabled('pickup')) : ?>
            <?php
            ob_start();
            do_action( 'rpress_before_service_time', 'pickup' );
            $service_time_preface_markup = trim( ob_get_clean() );
            $has_date_selector = false !== strpos( $service_time_preface_markup, 'rpress_get_delivery_dates' )
                || false !== strpos( $service_time_preface_markup, 'rpress_get_pickup_dates' );

            if ( '' !== $service_time_preface_markup ) {
                if ( $has_date_selector ) {
                    echo '<div class="rpress-service-date-row rpress-service-date-row-extension">';
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $service_time_preface_markup;
                    echo '</div>';
                } else {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $service_time_preface_markup;
                }
            }

            if ( ! $has_date_selector ) :
            ?>
                <div class="rpress-service-date-row">
                    <div class="pickup-time-text rpress-service-date-label">
                        <?php esc_html_e( 'Pickup date', 'restropress' ); ?>
                    </div>
                    <select
                        class="rpress-select rp-form-control rpress_get_delivery_dates rpress-service-date-select"
                        id="rpress_get_pickup_dates"
                        name="rpress_service_date"
                        aria-label="<?php esc_attr_e( 'Pickup date', 'restropress' ); ?>">
                        <option value="<?php echo esc_attr( $pickup_date_value ); ?>" selected>
                            <?php echo esc_html( $pickup_date_label ); ?>
                        </option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="rpress-service-hours-row rpress-service-time-row">
                <div class="pickup-time-text">
                    <?php
                    echo esc_html(
                        apply_filters(
                            'rpress_pickup_time_string',
                            __('Pickup time', 'restropress')
                        )
                    );
                    ?>
                </div>

            <?php
            $asap_option       = rpress_get_option('enable_asap_option', '');
            $asap_option_only  = rpress_get_option('enable_asap_option_only', '');
            $pickup_asap_text  = rpress_get_option('pickup_asap_text', '');

            // ASAP-only mode
            // ASAP (today only) is its own option ahead of the times.
            $list_asap = $asap_option && ! class_exists('RPRESS_SlotLimit') && rpress_service_asap_allowed($pickup_date_value) && ! empty($store_timings);
            if ($asap_option_only == 1 && $list_asap && is_array($store_timings)) {
                $store_timings = array();
            }
           
            ?>

                <select
                    class="rpress-pickup rpress-allowed-pickup-hrs rpress-hrs rp-form-control"
                    id="rpress-pickup-hours"
                    name="rpress_allowed_hours"
                    aria-label="<?php esc_attr_e('Pickup time', 'restropress'); ?>">

                <?php if ($list_asap) : ?>
                    <option value="<?php echo esc_attr('ASAP' . $pickup_asap_text); ?>" <?php selected(0 === stripos((string) $selected_time, 'asap')); ?>>
                        <?php echo esc_html(trim(__('ASAP', 'restropress') . ' ' . $pickup_asap_text)); ?>
                    </option>
                <?php endif; ?>
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

                        $is_asap = false; // ASAP is printed above the loop.
                        ?>

                        <?php if (class_exists('RPRESS_SlotLimit')) : ?>

                            <option
                                value="<?php echo esc_attr($filtered_time); ?>"
                                <?php selected($selected_time, $filtered_time); ?>
                            >
                                <?php echo esc_html(rpress_service_time_label($filtered_time, $service_type, $pickup_date_value)); ?>
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
                                        : rpress_service_time_label($filtered_time, $service_type, $pickup_date_value)
                                ); ?>
                            </option>

                        <?php endif; ?>

                    <?php endforeach; ?>
                <?php endif; ?>

                </select>
            </div>

            <?php do_action('rpress_after_service_time', 'pickup'); ?>
        <?php endif; ?>
    </div>
</div>
