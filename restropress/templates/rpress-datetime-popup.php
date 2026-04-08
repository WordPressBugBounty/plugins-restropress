<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified service context
 */
$context = rpress_get_service_context();

$service_type   = $context['service_type'];
$services       = ! empty( $context['services'] ) && is_array( $context['services'] ) ? $context['services'] : array( $service_type );
$store_timings  = $context['store_timings'];
$enabled_service = ! empty( $context['service_enabled'] ) ? $context['service_enabled'] : '';
$old_ui_ux_enabled = ! empty( rpress_get_option( 'old_ui_ux' ) );



/**
 * Options
 */
$asap_option        = rpress_get_option('enable_asap_option', '');
$asap_option_only   = rpress_get_option('enable_asap_option_only', '');
$button_style       = rpress_get_option('button_style', 'button');
$has_store_timings  = is_array($store_timings) && !empty($store_timings);
$show_update_button = rpress_is_service_enabled( $service_type ) && $has_store_timings;

$asap_text_key      = $service_type . '_asap_text';
$delivery_asap_text = rpress_get_option($asap_text_key, '');
$schedule_heading   = ( 'pickup' === $service_type )
    ? __( 'Choose Your Pickup Schedule', 'restropress' )
    : __( 'Choose Your Delivery Schedule', 'restropress' );
$schedule_subtext   = __( 'Review the available date and time, then click Update to apply your selection.', 'restropress' );

$popup_service_type_markup = '';
ob_start();
do_action( 'rpress_popup_service_time', $service_type );
$popup_service_type_markup = trim( (string) ob_get_clean() );
$popup_service_type_plain  = trim( wp_strip_all_tags( $popup_service_type_markup ) );
$popup_service_type_has_markup = '' !== $popup_service_type_plain
	|| (bool) preg_match( '/<(input|select|button|a|ul|li|div|span|p|label)\b/i', $popup_service_type_markup );

$show_popup_service_tabs = $old_ui_ux_enabled
	&& ! $popup_service_type_has_markup
	&& 'delivery_and_pickup' === $enabled_service
	&& count( $services ) > 1;

$popup_services = $show_popup_service_tabs ? $services : array( $service_type );
$popup_contexts = array();
foreach ( $popup_services as $popup_service ) {
	$popup_contexts[ $popup_service ] = rpress_get_service_context( $popup_service );
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
					<?php if ( $popup_service_type_has_markup || $show_popup_service_tabs ) : ?>
                        <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12 rpress-service-type-message">
							<?php if ( $popup_service_type_has_markup ) : ?>
								<?php echo $popup_service_type_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php elseif ( $show_popup_service_tabs ) : ?>
                                <div class="rpress-tabs-wrapper rpress-delivery-options text-center service-option-<?php echo esc_attr( $service_type ); ?>">
                                    <ul class="nav nav-pills order-online-servicetabs" id="rpressdeliveryTab" role="tablist">
										<?php foreach ( $popup_services as $popup_service ) : ?>
                                            <?php $is_active = ( $popup_service === $service_type ); ?>
                                            <li class="nav-item <?php echo $is_active ? 'active' : ''; ?>" role="presentation">
                                                <a
                                                    class="nav-link single-service-selected <?php echo $is_active ? 'active' : ''; ?>"
                                                    id="nav-<?php echo esc_attr( $popup_service ); ?>-tab"
                                                    data-service-type="<?php echo esc_attr( $popup_service ); ?>"
                                                    data-toggle="tab"
                                                    href="#nav-<?php echo esc_attr( $popup_service ); ?>"
                                                    role="tab"
                                                    aria-controls="nav-<?php echo esc_attr( $popup_service ); ?>"
                                                    aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                                                >
													<?php
													$popup_service_label = apply_filters(
														'rpress_modify_service_label',
														rpress_service_label( $popup_service )
													);
													echo wp_kses(
														$popup_service_label,
														array(
															'i' => array(
																'class' => true,
																'style' => true,
															),
															'br'   => array(),
															'span' => array(
																'class' => true,
																'style' => true,
															),
														)
													);
													?>
                                                </a>
                                            </li>
										<?php endforeach; ?>
                                    </ul>
                                </div>
							<?php endif; ?>
                        </div>
					<?php endif; ?>
                    <div class="tab-content rpress-popup-service-content" id="rpress-tab-content">
						<?php foreach ( $popup_contexts as $popup_service => $popup_context ) : ?>
                            <?php
                            $popup_store_timings = ! empty( $popup_context['store_timings'] ) && is_array( $popup_context['store_timings'] )
                                ? $popup_context['store_timings']
                                : array();
                            $popup_is_store_open  = ! empty( $popup_context['is_store_open'] );
                            $popup_selected_time  = ! empty( $popup_context['selected_time'] ) ? $popup_context['selected_time'] : '';
                            $popup_has_store_time = ! empty( $popup_store_timings );
                            $popup_closed_notice  = rpress_store_closed_message( $popup_service );
                            $popup_is_active      = ( $popup_service === $service_type );

                            if ( $asap_option_only == 1 && ! empty( $popup_store_timings ) ) {
                                array_splice( $popup_store_timings, 1 );
                            }

                            $popup_time_label_text = ( 'pickup' === $popup_service )
                                ? apply_filters( 'rpress_pickup_time_string', __( 'Select a pickup time', 'restropress' ) )
                                : apply_filters( 'rpress_delivery_time_string', __( 'Select a delivery time', 'restropress' ) );
                            $popup_time_label_class = ( 'pickup' === $popup_service ) ? 'pickup-time-text' : 'delivery-time-text';
                            $popup_time_aria_label  = ( 'pickup' === $popup_service ) ? __( 'Pickup time', 'restropress' ) : __( 'Delivery time', 'restropress' );
                            $popup_tab_classes      = 'tab-pane fade delivery-settings-wrapper';
                            if ( $popup_is_active ) {
                                $popup_tab_classes .= ' active show';
                            }
                            ?>
                            <div class="<?php echo esc_attr( $popup_tab_classes ); ?>" id="nav-<?php echo esc_attr( $popup_service ); ?>" role="tabpanel" aria-labelledby="nav-<?php echo esc_attr( $popup_service ); ?>-tab">
								<?php if ( empty( $popup_store_timings ) || ! $popup_is_store_open ) : ?>
                                    <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
                                        <div class="alert alert-warning rpress-service-closed-message rp-store-timing-notice-row" data-service-type="<?php echo esc_attr( $popup_service ); ?>">
                                            <span class="rp-store-timing-notice"><?php echo esc_html( $popup_closed_notice ); ?></span>
                                        </div>
                                    </div>
								<?php elseif ( rpress_is_service_enabled( $popup_service ) ) : ?>
                                    <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12">
										<?php do_action( 'rpress_before_service_time', $popup_service ); ?>
                                    </div>
                                    <div class="rp-col-lg-12 rp-col-md-12 rp-col-sm-12 rp-col-xs-12 rpress-service-hours-row">
                                        <div class="<?php echo esc_attr( $popup_time_label_class ); ?>">
											<?php echo esc_html( $popup_time_label_text ); ?>
                                        </div>
                                        <select
                                            class="rpress-delivery rpress-allowed-delivery-hrs rpress-hrs rp-form-control"
                                            id="rpress-delivery-hours"
                                            name="rpress_allowed_hours"
                                            aria-label="<?php echo esc_attr( $popup_time_aria_label ); ?>"
                                        >
											<?php if ( $popup_has_store_time ) : ?>
												<?php foreach ( $popup_store_timings as $index => $time_slot ) : ?>
                                                    <?php
                                                    $filtered_time = apply_filters(
                                                        'rpress_store_delivery_timings_slot_remaining',
                                                        $time_slot
                                                    );

                                                    if ( empty( $filtered_time ) ) {
                                                        continue;
                                                    }

                                                    $is_asap = ( $asap_option && $index === 0 );
                                                    ?>
													<?php if ( class_exists( 'RPRESS_SlotLimit' ) ) : ?>
                                                        <option
                                                            value="<?php echo esc_attr( $time_slot ); ?>"
															<?php selected( $popup_selected_time, $filtered_time ); ?>
                                                        >
															<?php echo esc_html( $filtered_time ); ?>
                                                        </option>
													<?php else : ?>
                                                        <?php
                                                        $option_value = $is_asap
                                                            ? 'ASAP' . esc_html( $delivery_asap_text )
                                                            : esc_attr( $filtered_time );

                                                        $option_label = $is_asap
                                                            ? __( 'ASAP', 'restropress' ) . ' ' . esc_html( $delivery_asap_text )
                                                            : esc_html( $filtered_time );
                                                        ?>
                                                        <option
                                                            value="<?php echo esc_attr( $option_value ); ?>"
															<?php selected( $popup_selected_time, $filtered_time ); ?>
                                                        >
															<?php echo esc_html( $option_label ); ?>
                                                        </option>
													<?php endif; ?>
												<?php endforeach; ?>
											<?php endif; ?>
                                        </select>
                                    </div>
								<?php endif; ?>
                            </div>
						<?php endforeach; ?>
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
