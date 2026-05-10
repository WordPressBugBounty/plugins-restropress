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
$pickup_date_value = ! empty( $service_date_raw ) ? $service_date_raw : current_time( 'Y-m-d' );
$pickup_date_label = date_i18n( get_option( 'date_format' ), strtotime( $pickup_date_value ) );
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
            $has_date_selector = false !== strpos( $service_time_preface_markup, 'rpress_get_delivery_dates' );

            if ( '' !== $service_time_preface_markup ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $service_time_preface_markup;
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
            if ($asap_option_only == 1 && is_array($store_timings)) {
                array_splice($store_timings, 1);
            }
           
            ?>

                <select
                    class="rpress-pickup rpress-allowed-pickup-hrs rpress-hrs rp-form-control"
                    id="rpress-pickup-hours"
                    name="rpress_allowed_hours"
                    aria-label="<?php esc_attr_e('Pickup time', 'restropress'); ?>">

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
            </div>

            <?php do_action('rpress_after_service_time', 'pickup'); ?>
        <?php endif; ?>
    </div>
</div>
