<?php
/**
 * Admin Home Page
 *
 * @package     RPRESS
 * @subpackage  Admin/Home
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.0
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;
function rpress_admin_home_page()
{
    // Handle form submission before any output
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (isset($_POST['service-pickup']) && isset($_POST['service-delivery'])) {
            $enable_service = 'delivery_and_pickup';
        } elseif (isset($_POST['service-pickup'])) {
            $enable_service = 'pickup';
        } elseif (isset($_POST['service-delivery'])) {
            $enable_service = 'delivery';
        } else {
            $enable_service = ''; // fallback
        }

        rpress_update_option('enable_service', $enable_service);

        if (isset($_POST['default_service']) && !empty($_POST['default_service'])) {
            rpress_update_option('default_service', sanitize_text_field($_POST['default_service']));
        }

        if (isset($_POST['enable_asap_option']) && !empty($_POST['enable_asap_option'])) {
            rpress_update_option('enable_asap_option', sanitize_text_field($_POST['enable_asap_option']));
        }

        if (isset($_POST['store_time_format']) && !empty($_POST['store_time_format'])) {
            rpress_update_option('store_time_format', sanitize_text_field($_POST['store_time_format']));
        }

        if (isset($_POST['open_time']) && !empty($_POST['open_time'])) {
            rpress_update_option('open_time', sanitize_text_field($_POST['open_time']));
        }

        if (isset($_POST['close_time']) && !empty($_POST['close_time'])) {
            rpress_update_option('close_time', sanitize_text_field($_POST['close_time']));
        }

        if (isset($_POST['country']) && !empty($_POST['country'])) {
            rpress_update_option('base_country', sanitize_text_field($_POST['country']));
        }

        if (isset($_POST['state']) && !empty($_POST['state'])) {
            rpress_update_option('base_state', sanitize_text_field($_POST['state']));
        }

        if (isset($_POST['address']) && !empty($_POST['address'])) {
            rpress_update_option('store_address', sanitize_text_field($_POST['address']));
        }

        if (isset($_POST['currency']) && !empty($_POST['currency'])) {
            rpress_update_option('currency', sanitize_text_field($_POST['currency']));
        }

        if (isset($_POST['enable_order_notification']) && !empty($_POST['enable_order_notification'])) {
            rpress_update_option('enable_order_notification', sanitize_text_field($_POST['enable_order_notification']));
        }

        if (isset($_POST['enable_printing']) && !empty($_POST['enable_printing'])) {
            rpress_update_option('enable_printing', sanitize_text_field($_POST['enable_printing']));
        }

        if (isset($_POST['template']) && !empty($_POST['template'])) {
            rpress_update_option('template', sanitize_text_field($_POST['template']));
        }

        if ( isset($_FILES['store_logo']) && ! empty($_FILES['store_logo']['name']) ) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        
            $attachment_id = media_handle_upload('store_logo', 0);
        
            if ( is_wp_error($attachment_id) ) {
                // Handle error
            } else {
                // Set as site icon
                update_option('site_icon', $attachment_id);
            }
        }        

        // Instead, just let the form re-render and add a flag
        $redirect_after_submit = true;
    }
    $base_country = '';
    $base_state = '';
    $store_address = '';
    $service_pickup_checked = '';
    $service_delivery_checked = '';
    $default_service = '';
    $enable_asap_option = '0';

    if (rpress_get_option('enable_service') == 'pickup' || rpress_get_option('enable_service') == 'delivery_and_pickup') {
        $service_pickup_checked = 'checked';
    }
    if (rpress_get_option('enable_service') == 'delivery' || rpress_get_option('enable_service') == 'delivery_and_pickup') {
        $service_delivery_checked = 'checked';
    }
    if (!empty(rpress_get_option('base_country'))) {
        $base_country = rpress_get_option('base_country');
    }
    if (!empty(rpress_get_option('default_service'))) {
        $default_service = rpress_get_option('default_service');
    }
    if (!empty(rpress_get_option('enable_asap_option'))) {
        $enable_asap_option = rpress_get_option('enable_asap_option');
    }
    if (!empty(rpress_get_option('base_state'))) {
        $base_state = rpress_get_option('base_state');
    }
    if (!empty(rpress_get_option('store_address'))) {
        $store_address = rpress_get_option('store_address');
    }


    $store_time_format          = rpress_get_option('store_time_format', '');
    $open_time                  = rpress_get_option('open_time', '9:00am');
    $close_time                 = rpress_get_option('close_time', '10:00pm');
    $currency                   = rpress_get_option('currency', 'USD');
    $enable_order_notification = rpress_get_option('enable_order_notification', '');
    $enable_printing            = rpress_get_option('enable_printing', '');
    $template                   = rpress_get_option('template', 'list');

    $site_icon_id = get_option('site_icon');
    $site_icon_url = wp_get_attachment_url($site_icon_id);

    ?>
    <div class="wrap">
        <div class="graybg rpress-welcome-wrap card">
            <div class="card-header">
                <h4 class="card-title"><?php esc_html_e('Getting started', 'restropress'); ?></h4>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rpress-settings')); ?>">
                    <span class="blue-text"
                        id="skiptext"><?php esc_html_e('Skip this, I\'ll set it later', 'restropress'); ?></span>
                </a>
            </div>
            <div class="card-body">
                <!-- tab -->
                <form class="multiStep" action="" method="POST">
                    <div class="tab">
                        <div class="welcome-step-1">
                        <!-- tab1_content -->
                        <div class="br-b-3 mb-3">
                            <h2 class="main-content-title">
                                <?php esc_html_e('Welcome to RestroPress!', 'restropress'); ?>
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="40" height="40" viewBox="0 0 74 74">
                                    <image id="confetti" width="74" height="74" xlink:href="data:image/png;base64,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" />
                                </svg>
                            </h2>
                            <p class="mg-b-0 main-title-description">
                                Let's start automating your store processes, <b>This short wizard will guide you though
                                    configuring Restropress.</b>
                            </p>
                        </div>
                        <div>
                            <h4><?php esc_html_e('Which services do you offer?', 'restropress'); ?></h4>
                            <div class="service-offer-wrap">
                                <div class="col-lg-6">
                                    <div class="card <?php echo esc_attr($service_pickup_checked); ?>">
                                        <div class="card-body">
                                            <div class="text-align-center">
                                                <div class="service-offer-icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"
                                                        viewBox="0 0 329.334 245.393">
                                                        <g id="Free_shipping-rafiki" data-name="Free shipping-rafiki"
                                                            transform="translate(0 -55)">
                                                            <g id="freepik--background-complete--inject-17"
                                                                transform="translate(0 55)">
                                                                <rect id="Rectangle_2499" data-name="Rectangle 2499"
                                                                    width="329.334" height="0.165"
                                                                    transform="translate(0 215.648)" fill="#ebebeb" />
                                                                <rect id="Rectangle_2500" data-name="Rectangle 2500"
                                                                    width="21.815" height="0.165"
                                                                    transform="translate(274.519 226.246)" fill="#ebebeb" />
                                                                <rect id="Rectangle_2501" data-name="Rectangle 2501"
                                                                    width="5.724" height="0.165"
                                                                    transform="translate(212.44 228.037)" fill="#ebebeb" />
                                                                <rect id="Rectangle_2502" data-name="Rectangle 2502"
                                                                    width="12.64" height="0.165"
                                                                    transform="translate(261.221 220.133)" fill="#ebebeb" />
                                                                <rect id="Rectangle_2503" data-name="Rectangle 2503"
                                                                    width="28.448" height="0.165"
                                                                    transform="translate(34.554 221.24)" fill="#ebebeb" />
                                                                <rect id="Rectangle_2504" data-name="Rectangle 2504"
                                                                    width="4.169" height="0.165"
                                                                    transform="translate(68.87 221.24)" fill="#ebebeb" />
                                                                <rect id="Rectangle_2505" data-name="Rectangle 2505"
                                                                    width="61.704" height="0.165"
                                                                    transform="translate(86.595 224.019)" fill="#ebebeb" />
                                                                <path id="Path_664" data-name="Path 664"
                                                                    d="M169.146,241.271H41.964a3.761,3.761,0,0,1-3.754-3.761V58.728A3.761,3.761,0,0,1,41.964,55H169.146a3.761,3.761,0,0,1,3.761,3.761V237.51A3.761,3.761,0,0,1,169.146,241.271ZM41.964,55.132a3.6,3.6,0,0,0-3.59,3.6V237.51a3.6,3.6,0,0,0,3.59,3.6H169.146a3.6,3.6,0,0,0,3.6-3.6V58.728a3.6,3.6,0,0,0-3.6-3.6Z"
                                                                    transform="translate(-13.042 -55)" fill="#ebebeb" />
                                                                <path id="Path_665" data-name="Path 665"
                                                                    d="M385.45,241.271H258.261a3.768,3.768,0,0,1-3.761-3.761V58.728A3.768,3.768,0,0,1,258.261,55H385.45a3.761,3.761,0,0,1,3.748,3.728V237.51A3.761,3.761,0,0,1,385.45,241.271ZM258.261,55.132a3.6,3.6,0,0,0-3.6,3.6V237.51a3.6,3.6,0,0,0,3.6,3.6H385.45a3.6,3.6,0,0,0,3.6-3.6V58.728a3.6,3.6,0,0,0-3.6-3.6Z"
                                                                    transform="translate(-86.869 -55)" fill="#ebebeb" />
                                                                <path id="Path_666" data-name="Path 666"
                                                                    d="M139.09,344.7l3.234,7.4a7.825,7.825,0,0,0,7.179,4.709h13.483a6.751,6.751,0,0,0,4.769-1.976h25.181a6.138,6.138,0,0,0,4.979-2.549l3.326-4.611Z"
                                                                    transform="translate(-47.476 -153.884)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_667" data-name="Path 667"
                                                                    d="M150.7,286.6c0,3.55,14.148,28.764,15.564,29.765s19.885-.119,19.885-.119l-1.357,5.23H134.077L113,280.579l13.931-20.109,25.392.7S150.7,283.056,150.7,286.6Z"
                                                                    transform="translate(-38.571 -125.134)"
                                                                    fill="#e6e6e6" />
                                                                <path id="Path_668" data-name="Path 668"
                                                                    d="M150.7,286.6c0,3.55,14.148,28.764,15.564,29.765s19.885-.119,19.885-.119l-1.357,5.23H134.077L113,280.579l13.931-20.109,25.392.7S150.7,283.056,150.7,286.6Z"
                                                                    transform="translate(-38.571 -125.134)"
                                                                    fill="#f0f0f0" />
                                                                <ellipse id="Ellipse_78" data-name="Ellipse 78" cx="20.689"
                                                                    cy="24.957" rx="20.689" ry="24.957"
                                                                    transform="translate(50.243 165.912)" fill="#e0e0e0" />
                                                                <ellipse id="Ellipse_79" data-name="Ellipse 79" cx="13.048"
                                                                    cy="15.742" rx="13.048" ry="15.742"
                                                                    transform="translate(54.419 175.126)" fill="#ebebeb" />
                                                                <path id="Path_669" data-name="Path 669"
                                                                    d="M95.064,334.51c-3,0-5.434,2.931-5.434,6.554s2.43,6.587,5.434,6.587,5.434-2.938,5.434-6.587S98.068,334.51,95.064,334.51Z"
                                                                    transform="translate(-30.594 -150.406)"
                                                                    fill="#e6e6e6" />
                                                                <path id="Path_670" data-name="Path 670"
                                                                    d="M95.064,334.51c-3,0-5.434,2.931-5.434,6.554s2.43,6.587,5.434,6.587,5.434-2.938,5.434-6.587S98.068,334.51,95.064,334.51Z"
                                                                    transform="translate(-30.594 -150.406)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_671" data-name="Path 671"
                                                                    d="M253.8,283.144S235.124,297.463,228,326.978H198.1l28.323-46.107,14.939-6.211Z"
                                                                    transform="translate(-67.618 -129.977)"
                                                                    fill="#e6e6e6" />
                                                                <path id="Path_672" data-name="Path 672"
                                                                    d="M255.76,281.688s-17.468,13.358-25.807,38.473a9.88,9.88,0,0,1-9.366,6.817H198.1l28.323-46.107,14.939-6.211Z"
                                                                    transform="translate(-67.618 -129.977)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_673" data-name="Path 673"
                                                                    d="M210.114,269.33H198.58a11.027,11.027,0,0,0-6.165,1.8c-6.844,4.558-23.053,17.27-24.74,37.412a5.335,5.335,0,0,1-5.269,4.914H112.275a47.613,47.613,0,0,1-23.98-6.477l-14.328-8.349a8.563,8.563,0,0,1-4.057-9.1c1.706-8.22,6.514-22.342,20.274-27.862.217-.1-.112-.184.547-.263v-8.563h35.937s-7.206,17.185-7.206,24.272c0,6.712,7.087,32.373,16.256,35.008h12.515a9.472,9.472,0,0,0,8.088-4.94c6.1-10.888,19.648-39.118,15.426-65.8-.494-3.116,1.9-6.323,5.065-6.323h16.137s14.524,16.809,19.194,31.017C212.61,267.48,211.576,269.33,210.114,269.33Z"
                                                                    transform="translate(-23.803 -116.46)" fill="#e6e6e6" />
                                                                <path id="Path_674" data-name="Path 674"
                                                                    d="M223.739,220.8h-4.611a5.316,5.316,0,0,1-3.326-1.179l-9.478-7.673a2.108,2.108,0,0,0-1.317-.468h-18.2a1.317,1.317,0,0,1,0-2.635h18.206a4.749,4.749,0,0,1,2.977,1.054l9.485,7.673a2.635,2.635,0,0,0,1.666.593h4.611a2.674,2.674,0,0,0,2.635-2.286l.415-2.957a4.769,4.769,0,0,1,4.7-4.077h3.471a1.317,1.317,0,1,1,0,2.635H231.5a2.121,2.121,0,0,0-2.088,1.811l-.415,2.957A5.322,5.322,0,0,1,223.739,220.8Z"
                                                                    transform="translate(-63.314 -107.514)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_675" data-name="Path 675"
                                                                    d="M229.2,218.49s.059,3.109-2.859,2.911l-.948,3.353a3.531,3.531,0,0,0,.4,2.839l2.062,3.293h19.1l1.5-2.832a2.4,2.4,0,0,0-.4-2.812l-5.665-5.757a3.2,3.2,0,0,0-2.292-.968Z"
                                                                    transform="translate(-76.887 -110.804)"
                                                                    fill="#e6e6e6" />
                                                                <path id="Path_676" data-name="Path 676"
                                                                    d="M229.2,218.49s.059,3.109-2.859,2.911l-.948,3.353a3.531,3.531,0,0,0,.4,2.839l2.062,3.293h19.1l1.5-2.832a2.4,2.4,0,0,0-.4-2.812l-5.665-5.757a3.2,3.2,0,0,0-2.292-.968Z"
                                                                    transform="translate(-76.887 -110.804)"
                                                                    fill="#e0e0e0" />
                                                                <ellipse id="Ellipse_80" data-name="Ellipse 80" cx="20.689"
                                                                    cy="24.957" rx="20.689" ry="24.957"
                                                                    transform="translate(164.423 165.912)" fill="#e0e0e0" />
                                                                <ellipse id="Ellipse_81" data-name="Ellipse 81" cx="13.048"
                                                                    cy="15.742" rx="13.048" ry="15.742"
                                                                    transform="translate(168.606 175.126)" fill="#ebebeb" />
                                                                <path id="Path_677" data-name="Path 677"
                                                                    d="M288.921,310.687c-2.18-3.7-8.009-11.724-18.489-14.069a20.471,20.471,0,0,0-8.187-.237l-.046-.112.046-.1h-.079l-1.8-4.11h-2.048A7.673,7.673,0,0,0,253.7,293.6l-3.6,2.7,1.693,5.869a16.917,16.917,0,0,0-1.37,1.666,29.706,29.706,0,0,0-6.027,12.969,1.39,1.39,0,0,0,1.377,1.6h1.08a1.39,1.39,0,0,0,1.317-.883,18.027,18.027,0,0,1,4.472-6.718l5.974,15.637,4.828-2.029-1.693-19.47s11.105-3.116,18.377,5.862a4.532,4.532,0,0,0,3.517,1.693h4.222a1.205,1.205,0,0,0,1.047-1.811Z"
                                                                    transform="translate(-83.417 -135.916)"
                                                                    fill="#e6e6e6" />
                                                                <path id="Path_678" data-name="Path 678"
                                                                    d="M288.921,310.687c-2.18-3.7-8.009-11.724-18.489-14.069a20.471,20.471,0,0,0-8.187-.237l-.046-.112.046-.1h-.079l-1.8-4.11h-2.048A7.673,7.673,0,0,0,253.7,293.6l-3.6,2.7,1.693,5.869a16.917,16.917,0,0,0-1.37,1.666,29.706,29.706,0,0,0-6.027,12.969,1.39,1.39,0,0,0,1.377,1.6h1.08a1.39,1.39,0,0,0,1.317-.883,18.027,18.027,0,0,1,4.472-6.718l5.974,15.637,4.828-2.029-1.693-19.47s11.105-3.116,18.377,5.862a4.532,4.532,0,0,0,3.517,1.693h4.222a1.205,1.205,0,0,0,1.047-1.811Z"
                                                                    transform="translate(-83.417 -135.916)"
                                                                    fill="#f0f0f0" />
                                                                <path id="Path_679" data-name="Path 679"
                                                                    d="M268.424,334.51c-3,0-5.434,2.931-5.434,6.554s2.43,6.587,5.434,6.587,5.434-2.938,5.434-6.587S271.427,334.51,268.424,334.51Z"
                                                                    transform="translate(-89.767 -150.406)"
                                                                    fill="#e6e6e6" />
                                                                <path id="Path_680" data-name="Path 680"
                                                                    d="M268.424,334.51c-3,0-5.434,2.931-5.434,6.554s2.43,6.587,5.434,6.587,5.434-2.938,5.434-6.587S271.427,334.51,268.424,334.51Z"
                                                                    transform="translate(-89.767 -150.406)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_681" data-name="Path 681"
                                                                    d="M241.53,237.254s3.418,21.519,14.708,21.67c10.585.138,11.573-20.07,1.475-23.172C249.559,233.243,241.53,237.254,241.53,237.254Z"
                                                                    transform="translate(-82.442 -116.415)"
                                                                    fill="#e6e6e6" />
                                                                <path id="Path_682" data-name="Path 682"
                                                                    d="M241.53,237.254s3.418,21.519,14.708,21.67c10.585.138,11.573-20.07,1.475-23.172C249.559,233.243,241.53,237.254,241.53,237.254Z"
                                                                    transform="translate(-82.442 -116.415)"
                                                                    fill="#f0f0f0" />
                                                                <ellipse id="Ellipse_82" data-name="Ellipse 82" cx="7.785"
                                                                    cy="9.92" rx="7.785" ry="9.92"
                                                                    transform="translate(164.886 121.807) rotate(-6.86)"
                                                                    fill="#f5f5f5" />
                                                                <path id="Path_683" data-name="Path 683"
                                                                    d="M254.338,231.395H244.53l.777-1.265h9.03Z"
                                                                    transform="translate(-83.466 -114.778)"
                                                                    fill="#e6e6e6" />
                                                                <path id="Path_684" data-name="Path 684"
                                                                    d="M150.283,258.792v2.477a3.788,3.788,0,0,1-4.09,3.7H101.654a3.761,3.761,0,0,1-4.064-3.7v-2.477a3.761,3.761,0,0,1,4.064-3.7h44.539A3.788,3.788,0,0,1,150.283,258.792Z"
                                                                    transform="translate(-33.311 -123.297)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_685" data-name="Path 685"
                                                                    d="M135.793,258.787v2.477a3.537,3.537,0,0,1-3.794,3.7H101.654a3.761,3.761,0,0,1-4.064-3.7v-2.477a3.761,3.761,0,0,1,4.064-3.7H132a3.537,3.537,0,0,1,3.794,3.7Z"
                                                                    transform="translate(-33.311 -123.292)"
                                                                    fill="#f0f0f0" />
                                                                <path id="Path_686" data-name="Path 686"
                                                                    d="M120.68,311.384v15.887h-8.4a47.614,47.614,0,0,1-23.98-6.477l-14.328-8.369a8.563,8.563,0,0,1-4.057-9.1c1.706-8.22,6.587-22.342,20.373-27.862l.659.033h5.566a16.9,16.9,0,0,1,15.294,9.735l5.928,12.712A31.722,31.722,0,0,1,120.68,311.384Z"
                                                                    transform="translate(-23.803 -130.25)" fill="#f0f0f0" />
                                                                <rect id="Rectangle_2506" data-name="Rectangle 2506"
                                                                    width="1.317" height="11.744"
                                                                    transform="matrix(0.794, -0.608, 0.608, 0.794, 133.024, 93.214)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_687" data-name="Path 687"
                                                                    d="M196.076,193.59h3.359a1.792,1.792,0,0,1,1.64,1.08l.448,1.047a1.785,1.785,0,0,1-1.64,2.483h-3.656a1.778,1.778,0,0,1-1.765-1.528l-.151-1.047a1.785,1.785,0,0,1,1.765-2.035Z"
                                                                    transform="translate(-66.319 -102.305)"
                                                                    fill="#e6e6e6" />
                                                                <rect id="Rectangle_2507" data-name="Rectangle 2507"
                                                                    width="8.523" height="1.317"
                                                                    transform="matrix(0.206, -0.979, 0.979, 0.206, 165.832, 102.229)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_688" data-name="Path 688"
                                                                    d="M258.648,193.59h-3.616a1.469,1.469,0,0,0-1.456,1.265l-.244,1.666a1.475,1.475,0,0,0,1.462,1.68h3.583a1.469,1.469,0,0,0,1.456-1.265l.244-1.666a1.475,1.475,0,0,0-1.429-1.68Z"
                                                                    transform="translate(-86.466 -102.305)"
                                                                    fill="#e6e6e6" />
                                                                <path id="Path_689" data-name="Path 689"
                                                                    d="M94.333,223.022v9l1.581.876v4.966c-8.5,1.41-13.463,18.93-13.463,18.93H53.324a4.854,4.854,0,0,1-4.854-4.854V165.994a4.854,4.854,0,0,1,4.854-4.854h57.06a4.762,4.762,0,0,1,2.2.534,4.841,4.841,0,0,1,2.635,4.321v57.027Z"
                                                                    transform="translate(-16.544 -91.229)" fill="#e6e6e6" />
                                                                <path id="Path_690" data-name="Path 690"
                                                                    d="M108.85,223.3v9l1.581.876v4.966c-8.5,1.41-13.463,18.93-13.463,18.93H91V166.231a4.281,4.281,0,0,1,4.281-4.281h31.833a4.841,4.841,0,0,1,2.635,4.321V223.3Z"
                                                                    transform="translate(-31.061 -91.506)" fill="#ebebeb" />
                                                                <rect id="Rectangle_2508" data-name="Rectangle 2508"
                                                                    width="94.262" height="191.356"
                                                                    transform="translate(203.383 23.58)" fill="#ebebeb" />
                                                                <rect id="Rectangle_2509" data-name="Rectangle 2509"
                                                                    width="22.612" height="22.612"
                                                                    transform="translate(253.33 45.382)" fill="#f5f5f5" />
                                                                <rect id="Rectangle_2510" data-name="Rectangle 2510"
                                                                    width="22.612" height="22.612"
                                                                    transform="translate(225.416 45.382)" fill="#f5f5f5" />
                                                                <rect id="Rectangle_2511" data-name="Rectangle 2511"
                                                                    width="22.612" height="22.612"
                                                                    transform="translate(225.416 73.303)" fill="#f5f5f5" />
                                                                <rect id="Rectangle_2512" data-name="Rectangle 2512"
                                                                    width="22.612" height="22.612"
                                                                    transform="translate(253.33 73.303)" fill="#f5f5f5" />
                                                                <path id="Path_691" data-name="Path 691"
                                                                    d="M324.007,241.319a3.689,3.689,0,1,1-3.688-3.689A3.689,3.689,0,0,1,324.007,241.319Z"
                                                                    transform="translate(-108.076 -117.338)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_692" data-name="Path 692"
                                                                    d="M100.547,111.595a3.708,3.708,0,0,0-3.7-3.708,3.755,3.755,0,0,0-1.06.158,8.078,8.078,0,0,0,.079-1.139,7.851,7.851,0,0,0-7.851-7.851,7.438,7.438,0,0,0-1.442.138,6.863,6.863,0,0,0-12.725,3.465h-.119a6.33,6.33,0,0,0,0,12.653H96.95A3.7,3.7,0,0,0,100.547,111.595Z"
                                                                    transform="translate(-23.074 -68.959)" fill="#f5f5f5" />
                                                                <path id="Path_693" data-name="Path 693"
                                                                    d="M230.321,82.446a2.272,2.272,0,0,0-2.272-2.259,2.325,2.325,0,0,0-.659.092,4.744,4.744,0,0,0,.053-.692,4.8,4.8,0,0,0-5.7-4.729,4.2,4.2,0,0,0-7.8,2.128h-.04a3.88,3.88,0,1,0,0,7.753h14.227a2.272,2.272,0,0,0,2.187-2.292Z"
                                                                    transform="translate(-71.635 -61.086)" fill="#f5f5f5" />
                                                            </g>
                                                            <g id="freepik--Shadow--inject-17"
                                                                transform="translate(36.958 285.481)">
                                                                <ellipse id="freepik--path--inject-17" cx="127.709"
                                                                    cy="7.456" rx="127.709" ry="7.456" fill="#f5f5f5" />
                                                            </g>
                                                            <g id="freepik--Plant--inject-17"
                                                                transform="translate(63.647 226.745)">
                                                                <path id="Path_694" data-name="Path 694"
                                                                    d="M112.837,365.853s3.293-7.147-.461-14.194-4.835-8.708-2.444-10.157,3.478,5.638,3.478,5.638a15.88,15.88,0,0,0,.356-9.966c-1.666-5.1-2.707-13.4-.435-14.3s.428,10.361,2.4,14.2a33.043,33.043,0,0,0,1.923-8.365c.277-4.1-.151-10.637,1.64-12.317s2.938-.514,1.179,8.523a94.651,94.651,0,0,0-1.706,16.388s3.886-11.856,6.494-12.205,2.635,3.5.375,6.824-6.541,9.294-7.245,12.363c0,0,4.268-8.918,8.1-6.185s-3.339,8.121-6.541,10.071-5.421,14.155-5.421,14.155Z"
                                                                    transform="translate(-100.737 -315.746)"
                                                                    fill="#007aff" />
                                                                <path id="Path_695" data-name="Path 695"
                                                                    d="M112.837,365.853s3.293-7.147-.461-14.194-4.835-8.708-2.444-10.157,3.478,5.638,3.478,5.638a15.88,15.88,0,0,0,.356-9.966c-1.666-5.1-2.707-13.4-.435-14.3s.428,10.361,2.4,14.2a33.043,33.043,0,0,0,1.923-8.365c.277-4.1-.151-10.637,1.64-12.317s2.938-.514,1.179,8.523a94.651,94.651,0,0,0-1.706,16.388s3.886-11.856,6.494-12.205,2.635,3.5.375,6.824-6.541,9.294-7.245,12.363c0,0,4.268-8.918,8.1-6.185s-3.339,8.121-6.541,10.071-5.421,14.155-5.421,14.155Z"
                                                                    transform="translate(-100.737 -315.746)" fill="#fff"
                                                                    opacity="0.6" />
                                                                <path id="Path_696" data-name="Path 696"
                                                                    d="M111.5,370.251s-3.188-7.193.659-14.188,4.933-8.635,2.562-10.111-3.55,5.586-3.55,5.586a15.96,15.96,0,0,1-.231-9.979c1.726-5.072,2.865-13.364.606-14.286s-.553,10.354-2.569,14.161a33.165,33.165,0,0,1-1.825-8.4c-.224-4.1.277-10.631-1.489-12.33s-2.938-.566-1.317,8.5a94.748,94.748,0,0,1,1.554,16.414s-3.748-11.915-6.356-12.3-2.635,3.451-.454,6.817,6.435,9.393,7.1,12.469c0,0-4.156-8.984-8.029-6.3s3.241,8.168,6.422,10.163,5.269,14.234,5.269,14.234Z"
                                                                    transform="translate(-96.77 -317.212)" fill="#007aff" />
                                                                <path id="Path_697" data-name="Path 697"
                                                                    d="M111.5,370.251s-3.188-7.193.659-14.188,4.933-8.635,2.562-10.111-3.55,5.586-3.55,5.586a15.96,15.96,0,0,1-.231-9.979c1.726-5.072,2.865-13.364.606-14.286s-.553,10.354-2.569,14.161a33.165,33.165,0,0,1-1.825-8.4c-.224-4.1.277-10.631-1.489-12.33s-2.938-.566-1.317,8.5a94.748,94.748,0,0,1,1.554,16.414s-3.748-11.915-6.356-12.3-2.635,3.451-.454,6.817,6.435,9.393,7.1,12.469c0,0-4.156-8.984-8.029-6.3s3.241,8.168,6.422,10.163,5.269,14.234,5.269,14.234Z"
                                                                    transform="translate(-96.77 -317.212)" fill="#fff"
                                                                    opacity="0.7" />
                                                                <path id="Path_698" data-name="Path 698"
                                                                    d="M96.63,388.48h26.36l-2.134,18.864H98.764Z"
                                                                    transform="translate(-96.63 -340.572)" fill="#007aff" />
                                                            </g>
                                                            <g id="freepik--character-1--inject-17"
                                                                transform="translate(94.189 86.161)">
                                                                <g id="freepik--group--inject-17">
                                                                    <path id="Path_699" data-name="Path 699"
                                                                        d="M195.57,403.279l5.513.58,1.034-11.889-5.513-.58Z"
                                                                        transform="translate(-160.944 -200.982)"
                                                                        fill="#ebb376" />
                                                                    <path id="Path_700" data-name="Path 700"
                                                                        d="M153.82,402.42h5.342l.441-12.37h-5.348Z"
                                                                        transform="translate(-146.693 -200.524)"
                                                                        fill="#ebb376" />
                                                                    <path id="Path_701" data-name="Path 701"
                                                                        d="M158.9,407.89H152.41a.468.468,0,0,0-.455.369l-1.054,4.756a.784.784,0,0,0,.606.929.817.817,0,0,0,.178,0c2.095-.033,3.623-.165,6.264-.165,1.62,0,6.521.171,8.76.171s2.536-2.213,1.62-2.417c-4.117-.9-7.245-2.141-8.563-3.326A1.317,1.317,0,0,0,158.9,407.89Z"
                                                                        transform="translate(-145.691 -206.614)"
                                                                        fill="#263238" />
                                                                    <path id="Path_702" data-name="Path 702"
                                                                        d="M163.558,408.4a.125.125,0,0,1-.1-.105.132.132,0,0,1,.059-.125c.191-.119,1.917-1.179,2.51-.876a.375.375,0,0,1,.217.356.757.757,0,0,1-.263.659,1.587,1.587,0,0,1-1.027.27A6.211,6.211,0,0,1,163.558,408.4Zm.362-.171a2.635,2.635,0,0,0,1.9-.1.514.514,0,0,0,.171-.454c0-.105-.046-.151-.079-.151a.3.3,0,0,0-.165-.033,5.15,5.15,0,0,0-1.831.744Z"
                                                                        transform="translate(-149.983 -206.391)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_703" data-name="Path 703"
                                                                        d="M163.519,407.809a.139.139,0,0,1-.059-.112c0-.072.138-1.614.757-2.114a.712.712,0,0,1,.533-.165.415.415,0,0,1,.408.336c.112.566-.955,1.739-1.508,2.035h-.059A.092.092,0,0,1,163.519,407.809Zm.856-2.075a2.964,2.964,0,0,0-.626,1.673c.547-.415,1.225-1.317,1.159-1.653,0-.033,0-.132-.184-.132h-.059A.435.435,0,0,0,164.376,405.734Z"
                                                                        transform="translate(-149.984 -205.769)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_704" data-name="Path 704"
                                                                        d="M200.678,407.63H194.21a.468.468,0,0,0-.454.369l-1.054,4.756a.8.8,0,0,0,.613.935h.171c2.1-.033,3.629-.165,6.264-.165,1.62,0,6.521.171,8.76.171s2.536-2.213,1.62-2.417c-4.117-.9-7.245-2.141-8.563-3.293A1.317,1.317,0,0,0,200.678,407.63Z"
                                                                        transform="translate(-159.959 -206.525)"
                                                                        fill="#263238" />
                                                                    <path id="Path_705" data-name="Path 705"
                                                                        d="M205.329,408.155a.128.128,0,0,1-.033-.237c.191-.119,1.917-1.179,2.5-.876a.375.375,0,0,1,.217.356.725.725,0,0,1-.263.659,1.574,1.574,0,0,1-1.028.27A6.658,6.658,0,0,1,205.329,408.155Zm.369-.178a2.556,2.556,0,0,0,1.9-.1.514.514,0,0,0,.171-.455c0-.1-.046-.151-.079-.151a.348.348,0,0,0-.165-.033A5.071,5.071,0,0,0,205.7,407.977Z"
                                                                        transform="translate(-164.241 -206.306)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_706" data-name="Path 706"
                                                                        d="M205.3,407.557a.145.145,0,0,1-.066-.119c0-.072.138-1.607.757-2.114a.659.659,0,0,1,.534-.158.408.408,0,0,1,.408.329c.112.566-.955,1.739-1.508,2.042h-.059A.126.126,0,0,1,205.3,407.557Zm.856-2.081a2.977,2.977,0,0,0-.659,1.673c.547-.415,1.225-1.317,1.159-1.653,0-.033,0-.125-.184-.125h-.059a.441.441,0,0,0-.257.105Z"
                                                                        transform="translate(-164.241 -205.682)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_707" data-name="Path 707"
                                                                        d="M196.86,391.39l5.513.58-.54,6.132-5.513-.58Z"
                                                                        transform="translate(-161.2 -200.982)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_708" data-name="Path 708"
                                                                        d="M154.361,390.06h5.348l-.231,6.376H154.13Z"
                                                                        transform="translate(-146.799 -200.528)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_709" data-name="Path 709"
                                                                        d="M155.546,225.3h28.323c2.694,14.715,11.131,66.8,5.078,113.2l-9.577-1.186s.889-61.605-7.581-84.382c-.975,12.192-2.134,25.161-3.135,32.057-2.457,16.987-7.726,54.1-7.726,54.1H151.14s1.976-35.529,2.918-52.219C155.066,268.752,155.507,229.054,155.546,225.3Z"
                                                                        transform="translate(-145.778 -144.29)"
                                                                        fill="#263238" />
                                                                    <path id="Path_710" data-name="Path 710"
                                                                        d="M155.546,225.3h28.323c2.694,14.715,11.131,66.8,5.078,113.2l-9.577-1.186s.889-61.605-7.581-84.382c-.975,12.192-2.134,25.161-3.135,32.057-2.457,16.987-7.726,54.1-7.726,54.1H151.14s1.976-35.529,2.918-52.219C155.066,268.752,155.507,229.054,155.546,225.3Z"
                                                                        transform="translate(-145.778 -144.29)" fill="#fff"
                                                                        opacity="0.2" />
                                                                    <path id="Path_711" data-name="Path 711"
                                                                        d="M201.055,156.64S218.18,172.8,226.084,175.5,254.308,188.2,254.308,188.2l3.116-2.457c0-.138,2.272,1.62,2.3,2.839,0,.883-2.233,8.279-2.7,8.273-2.174,0-4.578-4.98-4.578-4.98s-26.017-6.778-30.687-8.82-23.877-17.033-23.877-17.033Z"
                                                                        transform="translate(-161.732 -120.854)"
                                                                        fill="#ebb376" />
                                                                    <path id="Path_712" data-name="Path 712"
                                                                        d="M196.087,150.75c3.913-.119,24.549,17.995,26.149,19.819s17.633,7.291,19.45,10.031-.909,5.974-5.342,6.369-34.995-13.325-39.645-20.01S196.087,150.75,196.087,150.75Z"
                                                                        transform="translate(-160.565 -118.843)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_713" data-name="Path 713"
                                                                        d="M196.087,150.75c3.913-.119,24.549,17.995,26.149,19.819s17.633,7.291,19.45,10.031-.909,5.974-5.342,6.369-34.995-13.325-39.645-20.01S196.087,150.75,196.087,150.75Z"
                                                                        transform="translate(-160.565 -118.843)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_714" data-name="Path 714"
                                                                        d="M212.2,171.633l2.259,45.9H245.14l23.449-5.612V168.774l-32.808-.184Z"
                                                                        transform="translate(-166.62 -124.933)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_715" data-name="Path 715"
                                                                        d="M212.2,171.633l2.259,45.9H245.14l23.449-5.612V168.774l-32.808-.184Z"
                                                                        transform="translate(-166.62 -124.933)" fill="#fff"
                                                                        opacity="0.6" />
                                                                    <path id="Path_716" data-name="Path 716"
                                                                        d="M212.2,173.21l31.115,1.047,1.824,44.849H214.459Z"
                                                                        transform="translate(-166.62 -126.51)" fill="#fff"
                                                                        opacity="0.2" />
                                                                    <path id="Path_717" data-name="Path 717"
                                                                        d="M243.315,172.68l25.273-3.906-32.808-.184-23.58,3.043Z"
                                                                        transform="translate(-166.62 -124.933)" fill="#fff"
                                                                        opacity="0.4" />
                                                                    <path id="Path_718" data-name="Path 718"
                                                                        d="M229.85,173.479l3.985-.619,4.953.231-4.472.54v2.523l-4.262-.3Z"
                                                                        transform="translate(-172.645 -126.39)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_719" data-name="Path 719"
                                                                        d="M264.834,168.69l-1.3.27h4.426l1.607-.244Z"
                                                                        transform="translate(-184.141 -124.967)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_720" data-name="Path 720"
                                                                        d="M262.21,179.733l.349,4.433,8.424-1.436v-4.36Z"
                                                                        transform="translate(-183.69 -128.271)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_721" data-name="Path 721"
                                                                        d="M282.25,226.98v5.381l9.208-2.167V225.3Z"
                                                                        transform="translate(-190.53 -144.29)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_722" data-name="Path 722"
                                                                        d="M246.52,225.3s.869,2.694,2.516,3.53v4.663l-1.258,1.317h4.327l-2.292-1.475-.086-4.486a4.611,4.611,0,0,0,2.378-3.55h-2.371l-.422,1.416.764.086-.764,1.594v-.817h-.626l.626-2.253Z"
                                                                        transform="translate(-178.335 -144.29)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_723" data-name="Path 723"
                                                                        d="M182.49,262.031a75.747,75.747,0,0,1,3.168,12.258c.79-10.842-2.378-22.349-2.378-22.349Z"
                                                                        transform="translate(-156.479 -153.383)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_724" data-name="Path 724"
                                                                        d="M180.625,150.7s-18.028-.876-31.465,2.516c0,0,4.1,36.675,4.354,55.157a2.964,2.964,0,0,0,3.069,2.911l27.065-.942a2.964,2.964,0,0,0,2.859-3.083C186.111,197.37,184.557,165.412,180.625,150.7Z"
                                                                        transform="translate(-145.103 -118.791)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_725" data-name="Path 725"
                                                                        d="M156.91,240.708V243.8l31.781-1.541-.244-2.536Z"
                                                                        transform="translate(-147.748 -149.212)"
                                                                        fill="#263238" />
                                                                    <path id="Path_726" data-name="Path 726"
                                                                        d="M179.63,162s6.06,27.229,5.177,52.529"
                                                                        transform="translate(-155.503 -122.683)" fill="none"
                                                                        stroke="#263238" stroke-miterlimit="10"
                                                                        stroke-width="1" />
                                                                    <path id="Path_727" data-name="Path 727"
                                                                        d="M148.41,175.239s5.632,15.538,7.3,18.8,34.468,6.475,34.468,6.475,7.245,4.69,8.563,4.472,4.334-6.369,3.017-7.028-5.322-5.19-7.489-5.118-4.064,2.483-4.064,2.483-27.005-7.739-28.461-8.312c-1.041-.415-5.309-15.459-5.309-15.459Z"
                                                                        transform="translate(-144.847 -125.943)"
                                                                        fill="#ebb376" />
                                                                    <path id="Path_728" data-name="Path 728"
                                                                        d="M147.064,154.569c-4.459.112-6.1,7.331-.5,23.376,3.6,10.328,12.225,15.808,25.741,18.206s13.766-5.388,10.947-8.082-18.318-6.685-20.814-8.945S156.424,154.339,147.064,154.569Z"
                                                                        transform="translate(-143 -120.146)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_729" data-name="Path 729"
                                                                        d="M203.673,205.43l-.843,7.324,2.272.757.79-8.082Z"
                                                                        transform="translate(-163.422 -137.508)"
                                                                        fill="#263238" />
                                                                    <path id="Path_730" data-name="Path 730"
                                                                        d="M147.064,154.569c-4.459.112-6.1,7.331-.5,23.376,3.6,10.328,12.225,15.808,25.741,18.206s13.766-5.388,10.947-8.082-18.318-6.685-20.814-8.945S156.424,154.339,147.064,154.569Z"
                                                                        transform="translate(-143 -120.146)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_731" data-name="Path 731"
                                                                        d="M164.72,150.75s8.971,8.326,10.486,7.436,1.976-6.9,1.976-6.9Z"
                                                                        transform="translate(-150.414 -118.843)"
                                                                        fill="#fff" />
                                                                    <path id="Path_732" data-name="Path 732"
                                                                        d="M164.73,144.693c2.635,2.365,8.978,1.489,8.978,1.489s3.952-1.087,3.142-2.332c-2.345-.389-3.478-1.482-3.952-2.878a6.778,6.778,0,0,1-.237-2.681,13.1,13.1,0,0,1,.2-1.5l-7.9-5.836C166.014,134.813,167.279,141.9,164.73,144.693Z"
                                                                        transform="translate(-150.417 -112.088)"
                                                                        fill="#ebb376" />
                                                                    <path id="Path_733" data-name="Path 733"
                                                                        d="M167.88,136.6a6.686,6.686,0,0,0,6.1,6.3,6.778,6.778,0,0,1-.237-2.681Z"
                                                                        transform="translate(-151.492 -114.014)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_734" data-name="Path 734"
                                                                        d="M186.412,114.45s1.186,5.408.231,9.287-2.095,4.281-3.293,4.538-1.159-13.358-1.159-13.358Z"
                                                                        transform="translate(-156.377 -106.453)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_735" data-name="Path 735"
                                                                        d="M185.035,115.61s1.824,5.335.869,9.221-2.095,4.281-3.333,4.538-1.153-13.358-1.153-13.358Z"
                                                                        transform="translate(-156.113 -106.849)"
                                                                        opacity="0.5" />
                                                                    <path id="Path_736" data-name="Path 736"
                                                                        d="M182.452,111.75s2.312,7.081-1.08,10.539S175.74,112.2,175.74,112.2Z"
                                                                        transform="translate(-154.175 -105.531)"
                                                                        fill="#263238" />
                                                                    <path id="Path_737" data-name="Path 737"
                                                                        d="M167.269,129.535a6.685,6.685,0,0,0,7.99,1.317,7.43,7.43,0,0,0,3.623-4.11c.086-.237.165-.494.231-.751,1.159-4.756.468-12.613-4.7-14.741a7.245,7.245,0,0,0-10.018,7.193C164.681,123.7,164.595,126.821,167.269,129.535Z"
                                                                        transform="translate(-150.295 -105.17)"
                                                                        fill="#ebb376" />
                                                                    <path id="Path_738" data-name="Path 738"
                                                                        d="M176.579,124.317c-.033.428.224.784.507.784s.514-.356.514-.784-.224-.777-.507-.777S176.585,123.883,176.579,124.317Z"
                                                                        transform="translate(-154.46 -109.556)"
                                                                        fill="#263238" />
                                                                    <path id="Path_739" data-name="Path 739"
                                                                        d="M184,124.347c0,.428.231.784.507.784s.514-.349.52-.777-.231-.784-.514-.784S184,123.939,184,124.347Z"
                                                                        transform="translate(-156.995 -109.566)"
                                                                        fill="#263238" />
                                                                    <path id="Path_740" data-name="Path 740"
                                                                        d="M181.606,125.07a15.405,15.405,0,0,0,2.042,3.689,2.483,2.483,0,0,1-2.068.382Z"
                                                                        transform="translate(-156.169 -110.078)"
                                                                        fill="#d58745" />
                                                                    <path id="Path_741" data-name="Path 741"
                                                                        d="M174.19,121.572a.27.27,0,0,0,.244-.1,2.035,2.035,0,0,1,1.62-.837.257.257,0,0,0,.27-.237.25.25,0,0,0-.231-.27h0a2.529,2.529,0,0,0-2.062,1.034.257.257,0,0,0,.04.356h0A.349.349,0,0,0,174.19,121.572Z"
                                                                        transform="translate(-153.574 -108.392)"
                                                                        fill="#263238" />
                                                                    <path id="Path_742" data-name="Path 742"
                                                                        d="M186.214,121.3a.29.29,0,0,0,.151,0,.25.25,0,0,0,.119-.336h0a2.5,2.5,0,0,0-1.811-1.429.25.25,0,0,0-.283.217h0a.257.257,0,0,0,.217.283h0a2.035,2.035,0,0,1,1.416,1.153A.283.283,0,0,0,186.214,121.3Z"
                                                                        transform="translate(-157.127 -108.189)"
                                                                        fill="#263238" />
                                                                    <path id="Path_743" data-name="Path 743"
                                                                        d="M168.78,134.41a6.382,6.382,0,0,0,4.36,4.492c2.938.8,4.3-.711,4.3-.711a5.059,5.059,0,0,1-5.928.981,10.229,10.229,0,0,1-4.143-3.866Z"
                                                                        transform="translate(-151.318 -113.266)"
                                                                        fill="#263238" />
                                                                    <path id="Path_744" data-name="Path 744"
                                                                        d="M167.009,112.43l.382,2.318a1.745,1.745,0,0,0-.777,2.226c.836,1.923-.145,5.012-1.594,6.29,0,0-.079-.85-.79-.619s-.316,4.65,1.172,6.692c0,0-2.2-1.811-3.59-6.4-1.317-4.242-1.581-7.812-.777-9.511Z"
                                                                        transform="translate(-149.001 -105.764)"
                                                                        fill="#263238" />
                                                                    <path id="Path_745" data-name="Path 745"
                                                                        d="M160.725,127.259a5.059,5.059,0,0,0,2.878,2.562c1.666.566,2.635-.909,2.226-2.536-.349-1.449-1.633-3.53-3.353-3.379A2.249,2.249,0,0,0,160.725,127.259Z"
                                                                        transform="translate(-148.961 -109.678)"
                                                                        fill="#ebb376" />
                                                                    <path id="Path_746" data-name="Path 746"
                                                                        d="M177.043,111.366s-10.894-.349-11.507,1.778-.039,6.5.112,9.537c.105,2.081-1.594,3.1-3.241,3.2h0a3.515,3.515,0,0,1-.527,0,3.4,3.4,0,0,1-1.528-.428c-1.976-1.186-4.334-3.023-5.671-8.418-1.745-7.041,1.6-14.636,11.672-14.728S177.043,111.366,177.043,111.366Z"
                                                                        transform="translate(-146.837 -102.309)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_747" data-name="Path 747"
                                                                        d="M180.65,115.872s-10.269-.474-10.881,1.653-.039,6.5.112,9.538c.105,2.081-1.594,3.1-3.241,3.2a1.847,1.847,0,0,0,1.475-1.976c.072-1.653-1.034-8.925,0-10.321s4.538-2.5,8.141-2.773S180.65,115.872,180.65,115.872Z"
                                                                        transform="translate(-151.069 -106.69)"
                                                                        opacity="0.5" />
                                                                    <path id="Path_748" data-name="Path 748"
                                                                        d="M166.643,114.448s-1.067,1.37-.547,2.207,16.868-.534,17.066-.942-1.838-1.126-2.141-1.317-.389-1.976-.981-2.147S171.253,111.51,166.643,114.448Z"
                                                                        transform="translate(-150.834 -105.647)"
                                                                        fill="#263238" />
                                                                    <path id="Path_749" data-name="Path 749"
                                                                        d="M168.564,116.816a.422.422,0,1,1-.171-.276.422.422,0,0,1,.171.276Z"
                                                                        transform="translate(-151.438 -107.139)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_750" data-name="Path 750"
                                                                        d="M180.017,132.333a14.048,14.048,0,0,0-.553,1.976H179.1c-1.64-.079-2.378-.659-2.694-1.271a2.18,2.18,0,0,1-.217-.981,1.975,1.975,0,0,1,.079-.566,7.693,7.693,0,0,0,3.188.81C179.78,132.327,180.017,132.333,180.017,132.333Z"
                                                                        transform="translate(-154.329 -112.269)"
                                                                        fill="#263238" />
                                                                    <path id="Path_751" data-name="Path 751"
                                                                        d="M179.427,132.29l-.178.586c-1.548-.079-2.635-.336-3.089-.83a1.976,1.976,0,0,1,.079-.566,7.693,7.693,0,0,0,3.188.81Z"
                                                                        transform="translate(-154.318 -112.266)"
                                                                        fill="#fff" />
                                                                    <path id="Path_752" data-name="Path 752"
                                                                        d="M179.184,135.1c-1.64-.079-2.378-.659-2.694-1.271a5.27,5.27,0,0,1,1.8.441,1.923,1.923,0,0,1,.889.83Z"
                                                                        transform="translate(-154.431 -113.068)"
                                                                        fill="#de5753" />
                                                                    <path id="Path_753" data-name="Path 753"
                                                                        d="M162.19,150.61l.869-3.01s9.123,3.392,10.618,9.511c0,0-.659-7.423,1.6-9.511l1.554,1.976s.2,7.41-2.49,8.319S162.19,150.61,162.19,150.61Z"
                                                                        transform="translate(-149.55 -117.768)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_754" data-name="Path 754"
                                                                        d="M162.19,150.61l.869-3.01s9.123,3.392,10.618,9.511c0,0-.659-7.423,1.6-9.511l1.554,1.976s.2,7.41-2.49,8.319S162.19,150.61,162.19,150.61Z"
                                                                        transform="translate(-149.55 -117.768)"
                                                                        opacity="0.2" />
                                                                    <path id="Path_755" data-name="Path 755"
                                                                        d="M204.086,397.939l-11.876-1.765.362-3.293,12.35,1.62Z"
                                                                        transform="translate(-159.797 -201.49)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_756" data-name="Path 756"
                                                                        d="M204.086,397.939l-11.876-1.765.362-3.293,12.35,1.62Z"
                                                                        transform="translate(-159.797 -201.49)" fill="#fff"
                                                                        opacity="0.6" />
                                                                    <path id="Path_757" data-name="Path 757"
                                                                        d="M161.78,396.279H150.26v-3.445l11.955-.224Z"
                                                                        transform="translate(-145.478 -201.398)"
                                                                        fill="#007aff" />
                                                                    <path id="Path_758" data-name="Path 758"
                                                                        d="M161.78,396.279H150.26v-3.445l11.955-.224Z"
                                                                        transform="translate(-145.478 -201.398)" fill="#fff"
                                                                        opacity="0.6" />
                                                                </g>
                                                            </g>
                                                        </g>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <label class="switch">
                                                        <input type="checkbox" name="service-pickup" value="pickup" <?php echo esc_attr($service_pickup_checked); ?>>
                                                        <span class="slider round"></span>
                                                    </label>
                                                    <h3><?php esc_html_e('Pickup', 'restropress'); ?></h3>
                                                    <p class="service-offer-description">
                                                        Let customers collect orders from your location— quick, easy, and
                                                        convenient.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card <?php echo esc_attr($service_delivery_checked); ?>">
                                        <div class="card-body">
                                            <div class="text-align-center">
                                                <div class="service-offer-icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="180" height="180"
                                                        viewBox="0 0 245.112 236">
                                                        <g id="In_no_time-amico" data-name="In no time-amico"
                                                            transform="translate(-18.211 -28.88)">
                                                            <g id="freepik--Floor--inject-2"
                                                                transform="translate(18.211 123.474)">
                                                                <path id="Path_395" data-name="Path 395"
                                                                    d="M226.3,229.2c-47.822-27.613-125.105-27.613-172.621,0s-47.258,72.38.564,99.988,125.11,27.613,172.621,0S274.13,256.811,226.3,229.2Z"
                                                                    transform="translate(-18.211 -208.488)"
                                                                    fill="#f5f5f5" />
                                                            </g>
                                                            <g id="freepik--Shadows--inject-2"
                                                                transform="translate(34.647 167.738)">
                                                                <path id="Path_396" data-name="Path 396"
                                                                    d="M443.72,338.63a14.058,14.058,0,0,1,12.735,0c3.508,2.033,3.487,5.335-.042,7.373a14.026,14.026,0,0,1-12.735,0C440.17,343.965,440.191,340.678,443.72,338.63Z"
                                                                    transform="translate(-234.794 -313.631)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_397" data-name="Path 397"
                                                                    d="M172,320.508l-6.525-8.869a7.732,7.732,0,0,0-5.63-3.128l-9.617-.737-15.273-9.285,5.714-3.3a.527.527,0,0,0,0-.89l-2.375-1.353a3.1,3.1,0,0,0-3.076,0L79.21,325.328a.527.527,0,0,0,0,.89l2.317,1.338a3.086,3.086,0,0,0,3.092,0l24.311-14.041L124,322.157l1.97,5.546a8.759,8.759,0,0,0,8.248,5.82h14.062l9.543,5.509,23.058-13.4Z"
                                                                    transform="translate(-63.404 -292.534)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_398" data-name="Path 398"
                                                                    d="M336.494,324.182c-15.242-8.8-39.874-8.8-55.016,0-13.383,7.774-14.868,19.813-4.519,28.588-8.353-.527-17,1.053-23.379,4.777-11.192,6.5-11.134,17.048.132,23.553s29.493,6.5,40.659,0c8.9-5.177,10.676-12.909,5.361-19.129,12.83,1.859,26.95-.116,36.946-5.92C351.82,347.255,351.741,332.987,336.494,324.182Z"
                                                                    transform="translate(-142.1 -304.391)" fill="#e0e0e0" />
                                                                <path id="Path_399" data-name="Path 399"
                                                                    d="M66.884,374.668a15.967,15.967,0,0,0-14.489,0c-3.987,2.317-3.966,6.078.047,8.4a15.967,15.967,0,0,0,14.489,0C70.918,380.746,70.9,376.985,66.884,374.668Z"
                                                                    transform="translate(-49.419 -330.588)"
                                                                    fill="#e0e0e0" />
                                                            </g>
                                                            <g id="freepik--Windows--inject-2"
                                                                transform="translate(40.204 28.88)">
                                                                <path id="Path_400" data-name="Path 400"
                                                                    d="M138.883,30.492,141.7,28.88l.174,61.589-.005.005-22.71,12.967-.011-3.223-.169-58.366Z"
                                                                    transform="translate(-87.901 -28.88)" fill="#f0f0f0" />
                                                                <path id="Path_401" data-name="Path 401"
                                                                    d="M156.939,88.852l-.169-58.36,2.818-1.612.174,61.589-.005.005-2.828-1.617Z"
                                                                    transform="translate(-105.788 -28.88)" opacity="0.1" />
                                                                <path id="Path_402" data-name="Path 402"
                                                                    d="M119.3,154.12l19.892-11.36,2.828,1.617-22.71,12.967Z"
                                                                    transform="translate(-88.053 -82.783)" opacity="0.2" />
                                                                <path id="Path_403" data-name="Path 403"
                                                                    d="M79.873,64.182l2.818-1.612.174,61.589-.005.005-22.71,12.967-.011-3.223L59.97,75.542Z"
                                                                    transform="translate(-59.97 -44.827)" fill="#f0f0f0" />
                                                                <path id="Path_404" data-name="Path 404"
                                                                    d="M97.929,122.542l-.169-58.36,2.818-1.612.174,61.589-.005.005-2.828-1.617Z"
                                                                    transform="translate(-77.857 -44.827)" opacity="0.1" />
                                                                <path id="Path_405" data-name="Path 405"
                                                                    d="M60.29,187.81l19.892-11.36,2.828,1.617L60.3,191.033Z"
                                                                    transform="translate(-60.121 -98.729)" opacity="0.2" />
                                                            </g>
                                                            <g id="freepik--Clock--inject-2"
                                                                transform="translate(149.304 39.009)">
                                                                <path id="Path_406" data-name="Path 406"
                                                                    d="M293.7,56.882h0a2.061,2.061,0,0,1-.169-.132h0Z"
                                                                    transform="translate(-279.621 -52.2)" fill="none" />
                                                                <path id="Path_407" data-name="Path 407"
                                                                    d="M295.612,58.5h0Z"
                                                                    transform="translate(-280.591 -53.029)" fill="none" />
                                                                <path id="Path_408" data-name="Path 408"
                                                                    d="M282.787,51.477a1.261,1.261,0,0,1-.237-.047l.226.042Z"
                                                                    transform="translate(-274.423 -49.682)" fill="none" />
                                                                <path id="Path_409" data-name="Path 409"
                                                                    d="M283.856,51.723l-.216-.053.205.053Z"
                                                                    transform="translate(-274.939 -49.796)" fill="none" />
                                                                <path id="Path_410" data-name="Path 410" d="M288.36,53.47Z"
                                                                    transform="translate(-277.174 -50.648)" fill="none" />
                                                                <path id="Path_411" data-name="Path 411"
                                                                    d="M302.38,106.478q.232-.074.442-.158Q302.606,106.41,302.38,106.478Z"
                                                                    transform="translate(-283.81 -75.663)" fill="none" />
                                                                <path id="Path_412" data-name="Path 412"
                                                                    d="M305.585,70.793h0l-.095-.163h0Z"
                                                                    transform="translate(-285.282 -58.77)" fill="none" />
                                                                <path id="Path_413" data-name="Path 413"
                                                                    d="M304.466,68.964l-.116-.184h0Z"
                                                                    transform="translate(-284.742 -57.894)" fill="none" />
                                                                <path id="Path_414" data-name="Path 414"
                                                                    d="M297.345,60.095,297.24,60Z"
                                                                    transform="translate(-281.377 -53.739)" fill="none" />
                                                                <path id="Path_415" data-name="Path 415"
                                                                    d="M298.635,61.415l-.005-.005.005.005Z"
                                                                    transform="translate(-282.035 -54.406)" fill="none" />
                                                                <path id="Path_416" data-name="Path 416" d="M301.63,64.94Z"
                                                                    transform="translate(-283.455 -56.077)" fill="none" />
                                                                <path id="Path_417" data-name="Path 417"
                                                                    d="M300,107.008a3.034,3.034,0,0,0,.49-.058C300.321,106.976,300.163,106.976,300,107.008Z"
                                                                    transform="translate(-282.683 -75.961)" fill="none" />
                                                                <path id="Path_418" data-name="Path 418"
                                                                    d="M282.81,99c.248.237.5.474.753.7C283.305,99.453,283.058,99.216,282.81,99Z"
                                                                    transform="translate(-274.547 -72.198)" fill="none" />
                                                                <path id="Path_419" data-name="Path 419"
                                                                    d="M289.65,104.23c.237.137.469.258.7.374C290.119,104.488,289.887,104.367,289.65,104.23Z"
                                                                    transform="translate(-277.784 -74.674)" fill="none" />
                                                                <path id="Path_420" data-name="Path 420"
                                                                    d="M288,103.2a9.982,9.982,0,0,0,.864.527C288.579,103.574,288.29,103.39,288,103.2Z"
                                                                    transform="translate(-277.003 -74.186)" fill="none" />
                                                                <path id="Path_421" data-name="Path 421"
                                                                    d="M296.38,106.84l.253.047Z"
                                                                    transform="translate(-280.97 -75.909)" fill="none" />
                                                                <path id="Path_422" data-name="Path 422"
                                                                    d="M307.552,74.468l-.032-.068h0Z"
                                                                    transform="translate(-286.243 -60.555)" fill="none" />
                                                                <path id="Path_423" data-name="Path 423" d="M277.62,51.3Z"
                                                                    transform="translate(-272.09 -49.621)" fill="none" />
                                                                <path id="Path_424" data-name="Path 424"
                                                                    d="M280.643,51.19h-.032Z"
                                                                    transform="translate(-273.448 -49.569)" fill="none" />
                                                                <path id="Path_425" data-name="Path 425" d="M278.737,51.17Z"
                                                                    transform="translate(-272.554 -49.559)" fill="none" />
                                                                <path id="Path_426" data-name="Path 426"
                                                                    d="M281.778,51.292h0l-.248-.032h.221Z"
                                                                    transform="translate(-273.941 -49.602)" fill="none" />
                                                                <path id="Path_427" data-name="Path 427" d="M276.09,51.62Z"
                                                                    transform="translate(-271.323 -49.772)" fill="none" />
                                                                <path id="Path_428" data-name="Path 428"
                                                                    d="M275.044,52l-.084.032.074-.032Z"
                                                                    transform="translate(-270.831 -49.952)" fill="none" />
                                                                <path id="Path_429" data-name="Path 429"
                                                                    d="M291.842,55.536l-.032-.026Z"
                                                                    transform="translate(-278.807 -51.613)" fill="none" />
                                                                <path id="Path_430" data-name="Path 430"
                                                                    d="M311.948,97.57a1.883,1.883,0,0,1-.058.337A.946.946,0,0,1,311.948,97.57Z"
                                                                    transform="translate(-288.311 -71.522)" fill="none" />
                                                                <path id="Path_431" data-name="Path 431"
                                                                    d="M311.374,99.81c-.026.1-.053.2-.084.3C311.322,100.01,311.348,99.91,311.374,99.81Z"
                                                                    transform="translate(-288.027 -72.582)" fill="none" />
                                                                <path id="Path_432" data-name="Path 432"
                                                                    d="M311.664,98.73a1.273,1.273,0,0,1-.074.311C311.616,98.941,311.643,98.835,311.664,98.73Z"
                                                                    transform="translate(-288.169 -72.071)" fill="none" />
                                                                <path id="Path_433" data-name="Path 433"
                                                                    d="M310.641,101.82l-.111.263Z"
                                                                    transform="translate(-287.667 -73.533)" fill="none" />
                                                                <path id="Path_434" data-name="Path 434"
                                                                    d="M311.03,100.84c-.032.095-.063.19-.1.284C310.967,101.03,311,100.924,311.03,100.84Z"
                                                                    transform="translate(-287.857 -73.069)" fill="none" />
                                                                <path id="Path_435" data-name="Path 435"
                                                                    d="M310.2,102.74c-.042.084-.079.169-.126.248C310.117,102.909,310.154,102.824,310.2,102.74Z"
                                                                    transform="translate(-287.45 -73.969)" fill="none" />
                                                                <path id="Path_436" data-name="Path 436"
                                                                    d="M310.18,80.905v0Z"
                                                                    transform="translate(-287.502 -63.631)" fill="none" />
                                                                <path id="Path_437" data-name="Path 437"
                                                                    d="M289.79,54.24c.169.095.332.195.5.3C290.122,54.435,289.959,54.335,289.79,54.24Z"
                                                                    transform="translate(-277.85 -51.012)" fill="none" />
                                                                <path id="Path_438" data-name="Path 438"
                                                                    d="M312.232,95l-.032.448Z"
                                                                    transform="translate(-288.458 -70.305)" fill="none" />
                                                                <path id="Path_439" data-name="Path 439"
                                                                    d="M312.1,96.31l-.047.39Z"
                                                                    transform="translate(-288.387 -70.925)" fill="none" />
                                                                <path id="Path_440" data-name="Path 440"
                                                                    d="M312.32,93.26v0Z"
                                                                    transform="translate(-288.515 -69.482)" fill="none" />
                                                                <path id="Path_441" data-name="Path 441"
                                                                    d="M312.258,91.238V91.17A.329.329,0,0,0,312.258,91.238Z"
                                                                    transform="translate(-288.485 -68.492)" fill="none" />
                                                                <path id="Path_442" data-name="Path 442" d="M312,88.882v0Z"
                                                                    transform="translate(-288.363 -67.347)" fill="none" />
                                                                <path id="Path_443" data-name="Path 443"
                                                                    d="M307.81,106.019l.174-.179Z"
                                                                    transform="translate(-286.38 -75.436)" fill="none" />
                                                                <path id="Path_444" data-name="Path 444"
                                                                    d="M311.55,85.934v0Z"
                                                                    transform="translate(-288.15 -65.979)" fill="none" />
                                                                <path id="Path_445" data-name="Path 445"
                                                                    d="M311.778,87.2h0a1.8,1.8,0,0,0-.058-.321h0C311.741,86.991,311.757,87.1,311.778,87.2Z"
                                                                    transform="translate(-288.231 -66.462)" fill="none" />
                                                                <path id="Path_446" data-name="Path 446"
                                                                    d="M309.707,103.6c-.042.079-.09.158-.137.232C309.617,103.758,309.665,103.679,309.707,103.6Z"
                                                                    transform="translate(-287.213 -74.376)" fill="none" />
                                                                <path id="Path_447" data-name="Path 447"
                                                                    d="M306.63,107l-.2.147Z"
                                                                    transform="translate(-285.727 -75.985)" fill="none" />
                                                                <path id="Path_448" data-name="Path 448"
                                                                    d="M308.6,105.15a2,2,0,0,1-.163.195A2,2,0,0,0,308.6,105.15Z"
                                                                    transform="translate(-286.678 -75.109)" fill="none" />
                                                                <path id="Path_449" data-name="Path 449"
                                                                    d="M309.177,104.41c-.047.068-.095.142-.147.211C309.083,104.552,309.13,104.478,309.177,104.41Z"
                                                                    transform="translate(-286.957 -74.759)" fill="none" />
                                                                <path id="Path_450" data-name="Path 450"
                                                                    d="M307.324,106.46a1.782,1.782,0,0,1-.184.163,1.78,1.78,0,0,0,.184-.163Z"
                                                                    transform="translate(-286.063 -75.729)" fill="none" />
                                                                <path id="Path_451" data-name="Path 451"
                                                                    d="M274.434,54.93l-.174.116Z"
                                                                    transform="translate(-270.5 -51.339)" fill="none" />
                                                                <path id="Path_452" data-name="Path 452"
                                                                    d="M271.157,80.606c-.116-.305-.227-.611-.327-.916C270.93,80,271.041,80.3,271.157,80.606Z"
                                                                    transform="translate(-268.876 -63.058)" fill="none" />
                                                                <path id="Path_453" data-name="Path 453"
                                                                    d="M270.52,78.7h0c-.105-.316-.2-.632-.29-.948h0C270.32,78.066,270.414,78.408,270.52,78.7Z"
                                                                    transform="translate(-268.592 -62.14)" fill="none" />
                                                                <path id="Path_454" data-name="Path 454"
                                                                    d="M291.261,79.882a5,5,0,0,1-.5.058h-.89a6.032,6.032,0,0,1-.737-.074,2.393,2.393,0,0,1-.5-.09l-.158-.042-.348-.084c-.195-.053-.4-.116-.6-.184l-.121-.042-.485-.184-.184-.079-.527-.242-.195-.095c-.232-.121-.463-.242-.7-.379s-.579-.353-.869-.527l-.142-.095c-.274-.19-.527-.384-.811-.59l-.158-.121c-.269-.216-.527-.432-.8-.664l-.068-.063c-.258-.226-.527-.463-.753-.7-.042-.042-.084-.079-.121-.121-.19-.184-.379-.379-.564-.574l-.032-.032-.527-.574-.105-.126c-.279-.321-.527-.648-.811-.985l-.084-.111c-.211-.274-.411-.527-.611-.832l-.09-.121c-.19-.274-.374-.527-.558-.832l-.126-.2c-.147-.232-.29-.469-.427-.7-.032-.053-.063-.1-.09-.153-.158-.269-.311-.527-.458-.816l-.105-.205c-.116-.221-.226-.442-.337-.664-.037-.074-.074-.147-.105-.221-.137-.279-.263-.558-.39-.843l-.047-.121c-.111-.258-.216-.527-.316-.769-.032-.074-.058-.153-.09-.232-.116-.3-.226-.606-.327-.916a.118.118,0,0,0-.026-.074c-.105-.316-.2-.632-.29-.943a1.883,1.883,0,0,0-.053-.184c-.1-.369-.19-.737-.269-1.1a.95.95,0,0,0-.053-.242l-.079-.432a2,2,0,0,0-.053-.3c-.016-.1-.047-.295-.068-.442s-.026-.174-.037-.263c-.042-.316-.074-.632-.095-.943v-.2c0-.321-.032-.643-.032-.959a8.789,8.789,0,0,1,1.891-5.972,8.928,8.928,0,0,1,6.241,1.375c6.078,3.508,11,12.019,10.976,19.008a8.773,8.773,0,0,1-1.885,5.967l-.158.037Z"
                                                                    transform="translate(-270.542 -50.589)" fill="none" />
                                                                <path id="Path_455" data-name="Path 455"
                                                                    d="M275.557,89.76c-.147-.226-.29-.463-.427-.7C275.267,89.3,275.409,89.529,275.557,89.76Z"
                                                                    transform="translate(-270.911 -67.494)" fill="none" />
                                                                <path id="Path_456" data-name="Path 456"
                                                                    d="M279.541,95.365c-.279-.321-.527-.648-.811-.985C278.993,94.717,279.257,95.044,279.541,95.365Z"
                                                                    transform="translate(-272.615 -70.012)" fill="none" />
                                                                <path id="Path_457" data-name="Path 457"
                                                                    d="M273.25,85.58c.111.221.221.442.337.658C273.471,86.022,273.361,85.8,273.25,85.58Z"
                                                                    transform="translate(-270.022 -65.846)" fill="none" />
                                                                <path id="Path_458" data-name="Path 458"
                                                                    d="M274.588,88.046h0c-.158-.269-.311-.527-.458-.816h0A6.036,6.036,0,0,0,274.588,88.046Z"
                                                                    transform="translate(-270.438 -66.627)" fill="none" />
                                                                <path id="Path_459" data-name="Path 459"
                                                                    d="M303.906,105.82h.037c-.147.079-.3.153-.453.221h0C303.637,105.957,303.759,105.915,303.906,105.82Z"
                                                                    transform="translate(-284.335 -75.427)" fill="none" />
                                                                <path id="Path_460" data-name="Path 460"
                                                                    d="M276.743,91.612c-.19-.274-.374-.527-.553-.832C276.369,91.059,276.553,91.338,276.743,91.612Z"
                                                                    transform="translate(-271.413 -68.308)" fill="none" />
                                                                <path id="Path_461" data-name="Path 461"
                                                                    d="M280.977,97.059c-.174-.184-.342-.374-.527-.569h0C280.634,96.685,280.8,96.874,280.977,97.059Z"
                                                                    transform="translate(-273.429 -71.01)" fill="none" />
                                                                <path id="Path_462" data-name="Path 462"
                                                                    d="M285.133,101.036c-.284-.226-.569-.458-.843-.706a.313.313,0,0,0,.042.042C284.6,100.6,284.864,100.82,285.133,101.036Z"
                                                                    transform="translate(-275.247 -72.828)" fill="none" />
                                                                <path id="Path_463" data-name="Path 463"
                                                                    d="M286.911,102.4l.105.068c-.342-.232-.685-.479-1.016-.737l.1.079C286.369,102.025,286.637,102.22,286.911,102.4Z"
                                                                    transform="translate(-276.056 -73.491)" fill="none" />
                                                                <path id="Path_464" data-name="Path 464" d="M304.45,105.74Z"
                                                                    transform="translate(-284.789 -75.389)" fill="none" />
                                                                <path id="Path_465" data-name="Path 465"
                                                                    d="M282.074,98.214c-.19-.184-.379-.379-.564-.574C281.694,97.83,281.884,98.03,282.074,98.214Z"
                                                                    transform="translate(-273.931 -71.555)" fill="none" />
                                                                <path id="Path_466" data-name="Path 466"
                                                                    d="M291.773,105.31h0c-.216-.095-.427-.19-.643-.3l.116.058Q291.515,105.2,291.773,105.31Z"
                                                                    transform="translate(-278.485 -75.043)" fill="none" />
                                                                <path id="Path_467" data-name="Path 467"
                                                                    d="M293.185,105.92l.121.042c.195.068.39.126.585.184a11.257,11.257,0,0,1-1.2-.416h0Z"
                                                                    transform="translate(-279.223 -75.384)" fill="none" />
                                                                <path id="Path_468" data-name="Path 468"
                                                                    d="M297.968,107.07h0Z"
                                                                    transform="translate(-281.557 -76.018)" fill="none" />
                                                                <path id="Path_469" data-name="Path 469"
                                                                    d="M299.177,107.08h-.637C298.782,107.091,298.972,107.091,299.177,107.08Z"
                                                                    transform="translate(-281.992 -76.023)" fill="none" />
                                                                <path id="Path_470" data-name="Path 470"
                                                                    d="M301.757,106.67c-.179.047-.363.09-.527.126h0C301.4,106.76,301.583,106.717,301.757,106.67Z"
                                                                    transform="translate(-283.265 -75.829)" fill="none" />
                                                                <path id="Path_471" data-name="Path 471"
                                                                    d="M295.75,106.707a.833.833,0,0,1-.19-.047h.042Z"
                                                                    transform="translate(-280.582 -75.824)" fill="none" />
                                                                <path id="Path_472" data-name="Path 472"
                                                                    d="M278.021,93.412h0c-.211-.274-.411-.527-.611-.832h0C277.61,92.87,277.81,93.144,278.021,93.412Z"
                                                                    transform="translate(-271.991 -69.16)" fill="none" />
                                                                <path id="Path_473" data-name="Path 473"
                                                                    d="M271.476,57.83l-.126.179Z"
                                                                    transform="translate(-269.122 -52.711)" fill="none" />
                                                                <path id="Path_474" data-name="Path 474"
                                                                    d="M272,57.14c-.047.058-.095.111-.137.169A.96.96,0,0,1,272,57.14Z"
                                                                    transform="translate(-269.364 -52.385)" fill="none" />
                                                                <path id="Path_475" data-name="Path 475"
                                                                    d="M271.006,58.58l-.116.19Z"
                                                                    transform="translate(-268.904 -53.066)" fill="none" />
                                                                <path id="Path_476" data-name="Path 476"
                                                                    d="M273.138,55.92l-.158.142Z"
                                                                    transform="translate(-269.894 -51.807)" fill="none" />
                                                                <path id="Path_477" data-name="Path 477"
                                                                    d="M273.769,55.4l-.169.126Z"
                                                                    transform="translate(-270.187 -51.561)" fill="none" />
                                                                <path id="Path_478" data-name="Path 478"
                                                                    d="M272.547,56.5c-.047.053-.1.1-.147.158C272.447,56.6,272.5,56.553,272.547,56.5Z"
                                                                    transform="translate(-269.619 -52.082)" fill="none" />
                                                                <path id="Path_479" data-name="Path 479"
                                                                    d="M271.62,81.87c.1.258.205.527.316.769C271.82,82.381,271.72,82.128,271.62,81.87Z"
                                                                    transform="translate(-269.25 -64.09)" fill="none" />
                                                                <path id="Path_480" data-name="Path 480"
                                                                    d="M272.31,83.56c.126.284.253.564.39.843C272.563,84.124,272.436,83.844,272.31,83.56Z"
                                                                    transform="translate(-269.577 -64.89)" fill="none" />
                                                                <path id="Path_481" data-name="Path 481"
                                                                    d="M275.467,76.777c-.174-.184-.342-.374-.527-.569h0l-.111-.126h0c-.279-.321-.527-.648-.811-.985l-.084-.111h0c-.211-.269-.411-.527-.611-.827h0l-.09-.121h0c-.19-.274-.374-.527-.553-.832-.047-.068-.09-.137-.132-.205h0c-.147-.232-.29-.463-.427-.7a1.377,1.377,0,0,1-.09-.147h0c-.158-.269-.311-.527-.458-.816h0l-.105-.211c-.116-.216-.227-.437-.337-.658l-.105-.221c-.137-.279-.263-.558-.39-.843l-.047-.121c-.111-.258-.216-.527-.316-.769l-.09-.232h0c-.116-.305-.226-.611-.327-.916a.118.118,0,0,0-.026-.074h0c-.105-.316-.2-.632-.29-.948h0l-.047-.179c-.105-.369-.195-.737-.274-1.1h0a.947.947,0,0,0-.053-.242l-.079-.432a2.719,2.719,0,0,0-.047-.274h0a3.209,3.209,0,0,0-.068-.442c-.021-.147-.026-.174-.037-.263-.042-.316-.068-.632-.095-.943v-.2c0-.321-.032-.643-.032-.959v-.874a1.52,1.52,0,0,1,.032-.379c.032-.121,0-.158.026-.237s0-.242.042-.358a.8.8,0,0,0,.042-.242c.01-.079.037-.216.058-.321s.037-.163.053-.248.047-.195.068-.295.047-.158.068-.237.053-.184.084-.274l.079-.232c.032-.084.058-.174.095-.258s.063-.142.09-.216.068-.163.105-.242.069-.137.105-.205.074-.153.116-.226l.116-.19.126-.216.126-.179c.047-.063.09-.132.142-.195s.09-.111.137-.169.095-.121.147-.179.1-.105.147-.158l.158-.163.158-.142.169-.147.169-.126.179-.132.174-.116a5.451,5.451,0,0,1,1.764-.711,8.881,8.881,0,0,0-1.891,5.972c0,.316,0,.637.032.959v.2a7.235,7.235,0,0,0,.095.943,2.109,2.109,0,0,0,.037.263c.011.084.042.295.068.442s.032.2.053.3l.079.432a1.435,1.435,0,0,0,.053.242c.079.363.169.732.269,1.1a1.858,1.858,0,0,1,.053.184c.09.311.184.627.29.943a.11.11,0,0,0,.026.074c.1.311.211.616.327.916.032.079.058.158.09.232.1.258.205.527.316.769l.047.121c.126.284.253.564.39.843.032.074.068.147.105.221.111.221.221.442.337.664l.105.205c.147.274.3.527.458.816.026.053.058.1.09.153.137.232.279.469.427.7l.126.2c.184.279.369.558.558.832l.09.121c.2.284.4.558.611.832l.084.111c.263.337.527.664.811.985l.105.126.527.574.032.032c.184.195.374.39.564.574.037.042.079.079.121.121.247.237.5.474.753.7l.068.063c.263.232.527.448.8.664l.158.121c.263.205.527.4.811.59l.142.095c.29.19.579.374.869.527s.469.258.7.379l.195.095.527.242.184.079.485.184.121.042c.2.068.4.132.6.184l.348.084.158.042c.169.037.332.063.5.09a6.026,6.026,0,0,0,.737.074h.89a5.006,5.006,0,0,0,.5-.058h.153l.158-.037a5.4,5.4,0,0,1-1.364,1.117h0l-.053.026h-.037c-.132.074-.269.137-.406.2h0l-.142.058q-.216.09-.442.158H286.4c-.174.047-.353.09-.527.121h-.142c-.163,0-.321.042-.49.058H284l-.4-.047-.253-.047L283.1,81.6l-.147-.037h-.042l-.311-.074c-.195-.058-.39-.116-.585-.184l-.121-.042-.49-.184h0l-.174-.074h0q-.263-.111-.527-.237l-.116-.058a.421.421,0,0,1-.084-.042c-.232-.116-.464-.237-.7-.374s-.579-.353-.864-.527H278.9L278.8,79.7c-.274-.19-.527-.384-.811-.59l-.1-.079-.058-.042c-.269-.216-.527-.437-.8-.664a.321.321,0,0,1-.042-.042h-.026c-.258-.221-.527-.458-.753-.7l-.121-.121c-.19-.184-.379-.379-.564-.574Z"
                                                                    transform="translate(-267.92 -50.728)" fill="#ebebeb" />
                                                                <path id="Path_482" data-name="Path 482"
                                                                    d="M306.7,86.16v.679l1.912,1.1v-.679Z"
                                                                    transform="translate(-285.854 -66.121)" fill="#fff" />
                                                                <path id="Path_483" data-name="Path 483"
                                                                    d="M277.085,59.04l-.295.411,1.654,2.059.295-.416Z"
                                                                    transform="translate(-271.697 -53.284)" fill="#fff" />
                                                                <path id="Path_484" data-name="Path 484"
                                                                    d="M293.605,72.22c0,6.989-4.966,9.807-11.06,6.32S271.564,66.5,271.58,59.507s4.966-9.807,11.06-6.32S293.626,65.232,293.605,72.22Zm-16.011-6.747a.658.658,0,0,0,.274.358.248.248,0,0,0,.116.037l4.677.369a.153.153,0,0,0,.174-.068l3.06-5.188a.588.588,0,0,0-.263-.637c-.105-.058-.211-.053-.263.037l-3.007,5.1-4.608-.363c-.147,0-.221.147-.158.358m9-4.582.959-1.348-.527-.632-.922,1.364.527.632m.4,17.054.527-.042-1-2.475-.527.042.948,2.454m-12.382-11,1.659-.142-.29-.758-1.664.142.295.758m15.979,9.227.295-.416L289.208,73.7l-.295.416,1.654,2.059m-15.948-20.63-.295.411,1.654,2.059.295-.416-1.654-2.054m14.615,10.144,1.659-.142-.295-.753-1.659.137.295.758m-11.06-11.9-.527.042.948,2.454.527-.042-.953-2.454m-.047,19.034.964-1.354-.527-.627-.964,1.348.527.632m-3.013-10.955V61.2L273.2,60.1v.674l1.912,1.106m16.88,9.069-1.912-1.106v.679l1.912,1.1v-.674m-9.717,5.541.585.337v-2.2l-.585-.342v2.2m.648-21.256-.585-.337v2.223l.585.342v-2.2"
                                                                    transform="translate(-269.231 -49.806)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_485" data-name="Path 485"
                                                                    d="M291.985,57.78l-.005,2.2.585.342.011-2.2Z"
                                                                    transform="translate(-278.887 -52.688)" fill="#fff" />
                                                                <path id="Path_486" data-name="Path 486"
                                                                    d="M300.114,65.4l-.964,1.348.511.632.959-1.348Z"
                                                                    transform="translate(-282.281 -56.295)" fill="#fff" />
                                                                <path id="Path_487" data-name="Path 487"
                                                                    d="M306.2,76.56l-1.659.137.295.758,1.659-.142Z"
                                                                    transform="translate(-284.832 -61.577)" fill="#fff" />
                                                                <path id="Path_488" data-name="Path 488"
                                                                    d="M304.785,93.47l-.295.416,1.654,2.059.295-.416Z"
                                                                    transform="translate(-284.808 -69.581)" fill="#fff" />
                                                                <path id="Path_489" data-name="Path 489"
                                                                    d="M299.581,96.8l-.511.042.948,2.454.511-.042Z"
                                                                    transform="translate(-282.243 -71.157)" fill="#fff" />
                                                                <path id="Path_490" data-name="Path 490"
                                                                    d="M291.885,94.6l-.005,2.2.585.337.005-2.2Z"
                                                                    transform="translate(-278.84 -70.116)" fill="#fff" />
                                                                <path id="Path_491" data-name="Path 491"
                                                                    d="M284,88.04l-.964,1.348.506.632.964-1.354Z"
                                                                    transform="translate(-274.656 -67.011)" fill="#fff" />
                                                                <path id="Path_492" data-name="Path 492"
                                                                    d="M278.389,78.94l-1.659.142.295.758,1.659-.142Z"
                                                                    transform="translate(-271.669 -62.703)" fill="#fff" />
                                                                <path id="Path_493" data-name="Path 493"
                                                                    d="M274.65,67.66v.674l1.912,1.106v-.679Z"
                                                                    transform="translate(-270.684 -57.364)" fill="#fff" />
                                                                <path id="Path_494" data-name="Path 494"
                                                                    d="M283.646,55.66l-.506.042.948,2.454.511-.042Z"
                                                                    transform="translate(-274.703 -51.684)" fill="#fff" />
                                                                <path id="Path_495" data-name="Path 495"
                                                                    d="M288,77.64a.247.247,0,0,0-.116-.037h-.095l-4.608-.363c-.147,0-.221.147-.158.358a.658.658,0,0,0,.274.358.247.247,0,0,0,.116.037l4.677.369h0c.147,0,.216-.142.153-.353A.632.632,0,0,0,288,77.64Z"
                                                                    transform="translate(-274.635 -61.899)" fill="#fff" />
                                                                <path id="Path_496" data-name="Path 496"
                                                                    d="M295.361,68.076c-.105-.058-.211-.053-.263.037l-3.007,5.1h.095a.248.248,0,0,1,.116.037.632.632,0,0,1,.269.358c.063.211,0,.369-.153.353h0a.153.153,0,0,0,.174-.068l3.06-5.188a.517.517,0,0,0-.29-.632Z"
                                                                    transform="translate(-278.939 -57.543)"
                                                                    fill="#f5f5f5" />
                                                                <path id="Path_497" data-name="Path 497"
                                                                    d="M291.242,79.126l.174-.137a1.783,1.783,0,0,0,.184-.163c.058-.047.116-.095.169-.147l.174-.179.158-.169a1.991,1.991,0,0,0,.163-.195c.047-.058.1-.116.148-.179s.1-.142.147-.211.095-.126.137-.195.095-.153.137-.232.09-.137.126-.205.084-.163.126-.248l.116-.221.111-.263c.032-.079.069-.153.1-.232s.068-.19.1-.284.063-.163.09-.242.058-.2.084-.3.053-.169.074-.258.053-.205.074-.311l.063-.274a1.885,1.885,0,0,1,.058-.337L294,73.87l.047-.39.032-.248.032-.448v-.911c0-.342,0-.685-.032-1.053h0a.329.329,0,0,1,0-.068c-.026-.384-.058-.774-.111-1.169h0v-.132l-.095-.637h0a1.8,1.8,0,0,0-.058-.321h0c-.032-.153-.058-.311-.09-.469h0V67.95a23.54,23.54,0,0,0-.706-2.6h0a26.865,26.865,0,0,0-1.369-3.355l-.032-.068h0c-.306-.616-.627-1.227-.974-1.822h0l-.095-.163h0c-.158-.269-.321-.527-.485-.79l-.116-.184h0c-.453-.7-.927-1.375-1.433-2.022h0a23.68,23.68,0,0,0-1.58-1.828h0c-.205-.221-.416-.432-.627-.648h0l-.105-.095c-.274-.274-.553-.527-.843-.79h-.032c-.295-.263-.6-.527-.906-.758h0l-.163-.132h0c-.29-.221-.579-.432-.88-.632h0l-.032-.026c-.19-.126-.379-.253-.569-.369s-.327-.205-.5-.3c-.253-.147-.527-.279-.753-.406h0a12.4,12.4,0,0,0-2.249-.885h0l-.205-.053-.327-.079h0l-.226-.042-.305-.053h-.248l-.39-.042h-.153a7.59,7.59,0,0,0-.848,0h-.121a3.43,3.43,0,0,0-.527.058h0a5.543,5.543,0,0,0-.737.158h-.09c-.163.053-.321.111-.479.174h0l-.074.032c-.216.095-.432.195-.637.311h0l2.918-1.7.063-.037.079-.042c.142-.074.29-.147.437-.211a.633.633,0,0,1,.084-.042h.068q.263-.105.527-.19h0a5.731,5.731,0,0,1,.6-.137h.163a4.663,4.663,0,0,1,.527-.063h.116a6.023,6.023,0,0,1,.69,0h.453a3.115,3.115,0,0,1,.485.058h0l.527.1.169.042.342.084h.032c.211.058.427.121.643.195l.132.053c.174.058.348.126.527.2l.074.026.132.058c.184.079.374.169.564.258l.216.105a8.55,8.55,0,0,1,.753.406c.227.147.627.379.937.59l.158.105c.295.2.59.411.88.632l.163.132c.295.232.585.469.869.722a.453.453,0,0,1,.074.068c.279.242.527.5.816.758l.079.074.053.053c.205.2.405.406.606.621l.032.032c.19.205.374.411.558.621l.053.058.063.079c.3.348.6.7.88,1.053v.032a.892.892,0,0,1,.063.084q.342.442.664.9l.068.09a.214.214,0,0,1,.026.047c.211.29.411.59.606.9a.476.476,0,0,0,.058.084l.079.137c.158.248.311.527.463.753l.068.116a.212.212,0,0,1,0,.047c.174.295.337.59.5.885l.037.074.079.147.363.716c0,.047.047.1.074.147s.026.063.042.09c.142.305.284.611.416.916h0a.7.7,0,0,0,.037.095c.121.279.232.558.342.837,0,.047.037.09.058.137a.586.586,0,0,1,.037.116c.126.327.247.658.358.99h0V63.7q.174.527.316,1.053l.037.132a.347.347,0,0,1,0,.068c.105.4.205.8.29,1.19a.768.768,0,0,1,0,.105l.032.158c.032.153.058.311.09.463s.037.216.058.327.047.321.068.479a1.839,1.839,0,0,0,.037.226.236.236,0,0,0,0,.058c.042.342.074.685.1,1.022V69.2c0,.348.037.69.037,1.053,0,3.755-1.338,6.394-3.471,7.637l-2.918,1.7.184-.121a2.174,2.174,0,0,0,.184-.116Z"
                                                                    transform="translate(-270.339 -48.111)"
                                                                    fill="#ebebeb" />
                                                                <path id="Path_498" data-name="Path 498"
                                                                    d="M267.12,59.628c0,7.552,5.267,16.753,11.866,20.54,3.4,1.964,6.478,2.107,8.653.711a2.176,2.176,0,0,0,.184-.116l.2-.147.174-.137a1.782,1.782,0,0,0,.184-.163c.058-.047.116-.095.169-.147l.174-.179.158-.169a2.006,2.006,0,0,0,.163-.195c.047-.058.1-.116.147-.179s.1-.142.147-.211.095-.126.137-.195.095-.153.137-.232.09-.137.126-.205.084-.163.126-.248l.116-.221.111-.263c.032-.079.068-.153.1-.232s.068-.19.1-.284.063-.163.09-.242.058-.2.084-.3.053-.169.074-.258.053-.205.074-.311l.063-.274a1.885,1.885,0,0,1,.058-.337l.047-.274.047-.39.032-.248.032-.448v-.911c0-.342,0-.685-.032-1.053v-.068c-.026-.384-.058-.774-.111-1.169v-.132l-.095-.637a1.8,1.8,0,0,0-.058-.321c-.021-.111-.058-.311-.09-.469V69.44a23.525,23.525,0,0,0-.706-2.6h0a26.86,26.86,0,0,0-1.369-3.355l-.032-.068c-.305-.616-.627-1.227-.974-1.822l-.095-.163c-.158-.269-.321-.527-.485-.79l-.116-.184c-.453-.7-.927-1.375-1.433-2.022h0a23.665,23.665,0,0,0-1.58-1.828h0c-.205-.221-.416-.432-.627-.648l-.105-.095c-.274-.274-.553-.527-.843-.79h-.032c-.295-.263-.6-.527-.906-.758a2.055,2.055,0,0,1-.169-.132c-.29-.221-.579-.432-.88-.632l-.032-.026c-.19-.126-.379-.253-.569-.369s-.326-.205-.5-.3c-.253-.147-.527-.279-.753-.406h0a12.4,12.4,0,0,0-2.249-.885l-.216-.053-.327-.079a1.261,1.261,0,0,1-.237-.047l-.305-.053-.247-.032-.39-.042h-.153a7.59,7.59,0,0,0-.848,0H273.2a3.428,3.428,0,0,0-.527.058h0a5.545,5.545,0,0,0-.737.158h-.09c-.163.053-.321.111-.479.174l-.084.032c-.216.095-.432.195-.637.311C268.463,53.224,267.131,55.857,267.12,59.628ZM274.7,75.681l-.032-.037c-.174-.184-.342-.374-.527-.569l-.111-.126c-.279-.321-.527-.648-.811-.985l-.084-.111c-.211-.274-.411-.527-.611-.832l-.09-.121c-.19-.274-.374-.527-.553-.832-.047-.068-.09-.137-.132-.205-.147-.226-.29-.463-.427-.7a1.372,1.372,0,0,1-.09-.147c-.158-.269-.311-.527-.458-.816l-.105-.211c-.116-.216-.227-.437-.337-.658l-.105-.221c-.137-.279-.263-.558-.39-.843l-.047-.121c-.111-.258-.216-.527-.316-.769l-.09-.232c-.116-.305-.226-.611-.327-.916a.118.118,0,0,0-.026-.074c-.105-.316-.2-.632-.29-.948l-.047-.179c-.105-.369-.195-.737-.274-1.1a.948.948,0,0,0-.053-.242l-.079-.432a2.709,2.709,0,0,0-.047-.274c-.011-.095-.053-.311-.074-.469s-.026-.174-.037-.263c-.042-.316-.068-.632-.095-.943v-.2c0-.321-.032-.643-.032-.959V59.27a1.516,1.516,0,0,1,.032-.379c.032-.121,0-.158.026-.237s0-.242.042-.358a.8.8,0,0,0,.042-.242c.01-.079.037-.216.058-.321s.037-.163.053-.248.047-.195.068-.295.047-.158.068-.237.053-.184.084-.274l.079-.232c.032-.084.058-.174.095-.258s.063-.142.09-.216.068-.163.105-.242.068-.137.105-.205.074-.153.116-.226l.116-.19.126-.216.126-.179c.047-.063.09-.132.142-.195s.09-.111.137-.169.095-.121.147-.179.1-.105.148-.158l.158-.163.158-.142.169-.147.169-.126.179-.132.174-.116a5.451,5.451,0,0,1,1.764-.711,8.928,8.928,0,0,1,6.241,1.375c6.078,3.508,11,12.019,10.976,19.008a8.773,8.773,0,0,1-1.885,5.967,5.4,5.4,0,0,1-1.364,1.117h0l-.053.026c-.147.079-.3.153-.453.221l-.142.058q-.216.09-.442.158h-.079c-.179.047-.363.09-.527.126h-.142c-.163,0-.321.042-.49.058h-1.248l-.4-.047-.253-.047-.242-.047a.833.833,0,0,1-.19-.047l-.311-.074a11.257,11.257,0,0,1-1.2-.416l-.174-.074c-.216-.095-.427-.19-.642-.3a.419.419,0,0,1-.084-.042c-.232-.116-.464-.237-.7-.374s-.579-.353-.864-.527h-.042c-.342-.232-.685-.479-1.016-.737L277,77.814c-.284-.226-.569-.458-.843-.706h-.026c-.258-.221-.527-.458-.753-.7l-.121-.121C275.073,76.071,274.883,75.871,274.7,75.681Z"
                                                                    transform="translate(-267.12 -49.596)" fill="#fff" />
                                                            </g>
                                                            <g id="freepik--Pictures--inject-2"
                                                                transform="translate(229.695 59.401)">
                                                                <path id="Path_499" data-name="Path 499"
                                                                    d="M426.062,143.34l16.1,9.3-.037,12.3-16.1-9.3Z"
                                                                    transform="translate(-422.728 -113.578)" fill="none" />
                                                                <path id="Path_500" data-name="Path 500"
                                                                    d="M424.722,142.573l-.032,12.3-1.49.869.037-14.025Z"
                                                                    transform="translate(-421.388 -112.811)"
                                                                    fill="#f0f0f0" />
                                                                <path id="Path_501" data-name="Path 501"
                                                                    d="M440.786,176l-.005,1.722L423.2,167.569l1.49-.869Z"
                                                                    transform="translate(-421.388 -124.635)"
                                                                    fill="#f5f5f5" />
                                                                <path id="Path_502" data-name="Path 502"
                                                                    d="M419.76,153.975l21.193,12.235.053-18.165L419.818,135.81Zm1.812-1.053.037-14.03,1.485.858,16.1,9.327-.037,12.3V163.1Z"
                                                                    transform="translate(-419.76 -110.014)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_503" data-name="Path 503"
                                                                    d="M419.86,135.034l1.49-.864L442.538,146.4l-1.491.864Z"
                                                                    transform="translate(-419.807 -109.238)"
                                                                    fill="#f5f5f5" />
                                                                <path id="Path_504" data-name="Path 504"
                                                                    d="M461.533,157.4l-.053,18.16-1.49.869.053-18.165Z"
                                                                    transform="translate(-438.802 -120.233)"
                                                                    fill="#f0f0f0" />
                                                                <path id="Path_505" data-name="Path 505"
                                                                    d="M427.262,96.01l16.1,9.3-.037,12.3-16.095-9.29Z"
                                                                    transform="translate(-423.296 -91.175)" fill="none" />
                                                                <path id="Path_506" data-name="Path 506"
                                                                    d="M425.917,95.238l-.032,12.3-1.5.864.042-14.025Z"
                                                                    transform="translate(-421.952 -90.404)"
                                                                    fill="#f0f0f0" />
                                                                <path id="Path_507" data-name="Path 507"
                                                                    d="M441.981,128.66l-.005,1.727L424.39,120.234l1.5-.864Z"
                                                                    transform="translate(-421.952 -102.232)"
                                                                    fill="#f5f5f5" />
                                                                <path id="Path_508" data-name="Path 508"
                                                                    d="M421,106.661,442.188,118.9l.053-18.165L421.032,88.48Zm1.806-1.053.042-14.025,1.464.858,16.1,9.3-.037,12.3v1.727Z"
                                                                    transform="translate(-420.347 -87.611)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_509" data-name="Path 509"
                                                                    d="M421.06,87.7l1.491-.869,21.188,12.235-1.49.869Z"
                                                                    transform="translate(-420.375 -86.83)" fill="#f5f5f5" />
                                                                <path id="Path_510" data-name="Path 510"
                                                                    d="M462.733,110.06l-.053,18.165-1.491.864.053-18.16Z"
                                                                    transform="translate(-439.37 -97.825)" fill="#f0f0f0" />
                                                                <path id="Path_511" data-name="Path 511"
                                                                    d="M431.853,152.21l-4.392,3.46-.011,3.376,14.6,8.427.011-3.376-2.781-5.525-2.5,1.938.88,2.744-1.28-2.433Z"
                                                                    transform="translate(-423.4 -117.776)" fill="#e0e0e0" />
                                                                <path id="Path_512" data-name="Path 512"
                                                                    d="M443.788,155.288c-.685-.395-1.248-.074-1.248.716a2.723,2.723,0,0,0,1.243,2.144c.685.4,1.243.079,1.248-.711a2.739,2.739,0,0,0-1.243-2.149Z"
                                                                    transform="translate(-430.543 -119.153)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_513" data-name="Path 513"
                                                                    d="M433.048,104.88l-4.387,3.455-.011,3.376,14.6,8.432.011-3.376-2.781-5.53-2.5,1.943.885,2.744-1.28-2.438Z"
                                                                    transform="translate(-423.968 -95.374)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_514" data-name="Path 514"
                                                                    d="M444.988,107.957c-.69-.4-1.248-.079-1.248.711a2.728,2.728,0,0,0,1.238,2.149c.69.4,1.248.079,1.248-.711A2.728,2.728,0,0,0,444.988,107.957Z"
                                                                    transform="translate(-431.111 -96.75)" fill="#e0e0e0" />
                                                            </g>
                                                            <g id="freepik--speech-bubble--inject-2"
                                                                transform="translate(183.711 49.771)">
                                                                <path id="Path_515" data-name="Path 515"
                                                                    d="M337.743,68.843l31.532,18.233a3.524,3.524,0,0,1,1.759,3.039v31.706a3.46,3.46,0,0,1-5.267,2.849L352.6,116.854l-.284.458-.042.042h-.032c-.527.342-1.417.827-1.617.932h0a.19.19,0,0,1-.237-.116l-1.148-3.255h0l-15.026-8.437a3.5,3.5,0,0,1-1.764-3.039V71.692C332.45,69.164,336.168,67.937,337.743,68.843Z"
                                                                    transform="translate(-332.45 -68.546)" fill="#007aff" />
                                                                <path id="Path_516" data-name="Path 516" d="M396.45,107.77Z"
                                                                    transform="translate(-362.743 -87.112)" fill="none" />
                                                                <path id="Path_517" data-name="Path 517"
                                                                    d="M368.561,160.212l-.042.042h-.032c-.527.342-1.417.827-1.617.932h0a.163.163,0,0,0,.116-.079l1.111-1.8-.221-.126h0l.98.558Z"
                                                                    transform="translate(-348.742 -111.446)" fill="#fff"
                                                                    opacity="0.2" />
                                                                <path id="Path_518" data-name="Path 518"
                                                                    d="M367.094,90.379a3.613,3.613,0,0,0-.937-1.174h0a3.2,3.2,0,0,0-.427-.29L334.2,70.676a1.169,1.169,0,0,0-1.748,1.016c0-2.528,3.718-3.755,5.293-2.849l31.532,18.233a3.534,3.534,0,0,1,1.306,1.322Z"
                                                                    transform="translate(-332.45 -68.546)" fill="#fff"
                                                                    opacity="0.4" />
                                                                <path id="Path_519" data-name="Path 519"
                                                                    d="M397.452,141.5V109.8a3.35,3.35,0,0,0-.395-1.58l3.487-1.98a3.513,3.513,0,0,1,.453,1.717v31.706a3.46,3.46,0,0,1-5.267,2.849,1.169,1.169,0,0,0,1.722-1.011Z"
                                                                    transform="translate(-362.402 -86.388)" fill="#fff"
                                                                    opacity="0.2" />
                                                                <path id="Path_520" data-name="Path 520"
                                                                    d="M342.542,91.577l18.3,10.565-1.1,12.545c-.063.764-.653,1.053-1.354.632l-14.02-8.111a2.565,2.565,0,0,1-.722-.658l.026.358a5.382,5.382,0,0,0,2.423,3.95l.616.353a2.386,2.386,0,0,1,.658.263,3.25,3.25,0,0,1,.658.527l6.568,3.792a2.18,2.18,0,0,1,.658.263,3.422,3.422,0,0,1,.658.5l1.4.806a1.19,1.19,0,0,1,.527.937c0,.342-.248.479-.527.311l-.111-.063a3.728,3.728,0,0,1,.242,1.3c0,1.39-.985,1.954-2.2,1.253a4.835,4.835,0,0,1-2.186-3.787,1.933,1.933,0,0,1,.253-1.016l-4.013-2.317a3.9,3.9,0,0,1,.242,1.3c0,1.4-.99,1.954-2.2,1.253a4.8,4.8,0,0,1-2.186-3.787,1.9,1.9,0,0,1,.279-1.053,7.942,7.942,0,0,1-2.855-5.267l-1.243-16.853a2.391,2.391,0,0,0-1.08-1.754l-2.265-1.311a1.19,1.19,0,0,1-.527-.932c0-.348.248-.485.527-.311l2.265,1.306a4.8,4.8,0,0,1,2.154,3.513Zm9.58,17.859.026-10.049a1.622,1.622,0,0,0-.732-1.269c-.406-.232-.732-.047-.737.421l-.026,10.049a1.627,1.627,0,0,0,.732,1.253c.406.232.737.042.737-.421m3.16,1.833.026-10.049a1.622,1.622,0,0,0-.732-1.269c-.406-.232-.737-.047-.737.421v10.049a1.611,1.611,0,0,0,.732,1.269c.405.232.732.042.737-.421m3.16,1.833.032-10.049a1.622,1.622,0,0,0-.743-1.253c-.406-.232-.737-.047-.737.421l-.026,10.049a1.612,1.612,0,0,0,.764,1.275c.4.232.732.042.732-.421m-9.522-5.5.026-10.049a1.58,1.58,0,0,0-.732-1.269c-.406-.232-.732-.047-.737.421v10.049a1.627,1.627,0,0,0,.732,1.269c.406.232.737.042.737-.421m-3.16-1.833.032-10.049a1.633,1.633,0,0,0-.732-1.269c-.406-.232-.737-.047-.737.421l-.032,10.049a1.612,1.612,0,0,0,.732,1.264c.405.237.737.047.737-.416m1.58,7.294c-.453-.263-.822-.053-.827.469a1.822,1.822,0,0,0,.822,1.422c.458.263.822.053.827-.469a1.807,1.807,0,0,0-.822-1.422m7.9,4.566c-.453-.263-.822-.053-.822.469a1.8,1.8,0,0,0,.816,1.422c.453.263.827.053.827-.474a1.812,1.812,0,0,0-.822-1.417"
                                                                    transform="translate(-334.821 -76.423)" fill="#fff" />
                                                                <path id="Path_521" data-name="Path 521"
                                                                    d="M384.868,116.606a1.581,1.581,0,0,1,.564.59c.311.527.311,1.117,0,1.29l-4.651,2.633a.558.558,0,0,1-.564-.058,1.649,1.649,0,0,1-.564-.59l-2.275-3.95c-.316-.527-.311-1.117,0-1.3a.569.569,0,0,1,.564.058,1.648,1.648,0,0,1,.564.59l1.722,3,4.087-2.328a.558.558,0,0,1,.553.058Z"
                                                                    transform="translate(-353.605 -90.618)" fill="#fff" />
                                                                <path id="Path_522" data-name="Path 522"
                                                                    d="M380.195,107.949a12.327,12.327,0,0,1,5.593,9.691c-.011,3.56-2.528,4.993-5.63,3.2a12.3,12.3,0,0,1-5.588-9.685C374.581,107.591,377.1,106.163,380.195,107.949Zm-.658,9.406,4.651-2.633c.316-.174.316-.753,0-1.29a1.581,1.581,0,0,0-.564-.59.558.558,0,0,0-.564-.058l-4.05,2.307-1.717-2.981a1.648,1.648,0,0,0-.564-.59.569.569,0,0,0-.564-.058c-.311.179-.316.753,0,1.3l2.275,3.95a1.649,1.649,0,0,0,.564.59.558.558,0,0,0,.564.058"
                                                                    transform="translate(-352.387 -86.833)"
                                                                    fill="#007aff" />
                                                                <path id="Path_523" data-name="Path 523"
                                                                    d="M380.195,107.949a12.327,12.327,0,0,1,5.593,9.691c-.011,3.56-2.528,4.993-5.63,3.2a12.3,12.3,0,0,1-5.588-9.685C374.581,107.591,377.1,106.163,380.195,107.949Zm-.658,9.406,4.651-2.633c.316-.174.316-.753,0-1.29a1.581,1.581,0,0,0-.564-.59.558.558,0,0,0-.564-.058l-4.05,2.307-1.717-2.981a1.648,1.648,0,0,0-.564-.59.569.569,0,0,0-.564-.058c-.311.179-.316.753,0,1.3l2.275,3.95a1.649,1.649,0,0,0,.564.59.558.558,0,0,0,.564.058"
                                                                    transform="translate(-352.387 -86.833)" fill="#fff"
                                                                    opacity="0.3" />
                                                            </g>
                                                            <g id="freepik--character-2--inject-2"
                                                                transform="translate(50.474 51.884)">
                                                                <path id="Path_524" data-name="Path 524"
                                                                    d="M139.426,76.293h0a6.771,6.771,0,0,0-5.646-3.724,2.275,2.275,0,0,0-.737.147L81.961,101.763A4.819,4.819,0,0,0,80,103.869a4.877,4.877,0,0,0-.527,2.144V216.534c0,2.812,3.244,5.5,6.294,4.213h0a1.053,1.053,0,0,0,.2-.079l51.087-29.114a4.893,4.893,0,0,0,2.465-4.245V76.825a1.185,1.185,0,0,0-.09-.532Z"
                                                                    transform="translate(-79.47 -72.563)" fill="#455a64" />
                                                                <path id="Path_525" data-name="Path 525"
                                                                    d="M93,131.965a2.433,2.433,0,0,1,1.232-2.107l47.458-27a1.222,1.222,0,0,1,1.828,1.053v89.112a2.46,2.46,0,0,1-1.232,2.107l-47.437,27.05a1.222,1.222,0,0,1-1.828-1.053Z"
                                                                    transform="translate(-85.874 -86.823)" fill="#007aff" />
                                                                <path id="Path_526" data-name="Path 526"
                                                                    d="M93,131.965a2.433,2.433,0,0,1,1.232-2.107l47.458-27a1.222,1.222,0,0,1,1.828,1.053v89.112a2.46,2.46,0,0,1-1.232,2.107l-47.437,27.05a1.222,1.222,0,0,1-1.828-1.053Z"
                                                                    transform="translate(-85.874 -86.823)" opacity="0.2" />
                                                                <path id="Path_527" data-name="Path 527"
                                                                    d="M93,131.965a2.433,2.433,0,0,1,1.232-2.107l47.458-27a1.222,1.222,0,0,1,1.828,1.053v89.112a2.46,2.46,0,0,1-1.232,2.107l-47.437,27.05a1.222,1.222,0,0,1-1.828-1.053Z"
                                                                    transform="translate(-85.874 -86.823)" fill="#fff"
                                                                    opacity="0.1" />
                                                                <path id="Path_528" data-name="Path 528"
                                                                    d="M79.51,134.154V244.665c0,2.812,3.244,5.5,6.294,4.213h0a1.211,1.211,0,0,1-1.627-1.138V137.209a4.872,4.872,0,0,1,.858-2.7L80.016,132A4.877,4.877,0,0,0,79.51,134.154Z"
                                                                    transform="translate(-79.489 -100.694)" opacity="0.3" />
                                                                <path id="Path_529" data-name="Path 529"
                                                                    d="M134.259,72.559a2.275,2.275,0,0,0-.737.147L82.434,101.758a4.819,4.819,0,0,0-1.964,2.107l5.019,2.512a4.861,4.861,0,0,1,1.648-1.58l51.087-29.062a1.211,1.211,0,0,1,1.7.527h0A6.85,6.85,0,0,0,134.259,72.559Z"
                                                                    transform="translate(-79.943 -72.558)" fill="#fff"
                                                                    opacity="0.1" />
                                                                <path id="Path_530" data-name="Path 530"
                                                                    d="M143.359,113.2c.363-.205.658-.042.658.369a1.464,1.464,0,0,1-.653,1.132l-10.007,5.793c-.358.211-.648.037-.648-.379a1.427,1.427,0,0,1,.643-1.117Z"
                                                                    transform="translate(-104.67 -91.754)" fill="#263238" />
                                                                <path id="Path_531" data-name="Path 531"
                                                                    d="M128.413,127.01c.358-.205.648-.042.648.374a1.448,1.448,0,0,1-.648,1.127c-.358.205-.653.037-.653-.374A1.422,1.422,0,0,1,128.413,127.01Z"
                                                                    transform="translate(-102.327 -98.29)" fill="#263238" />
                                                                <path id="Path_532" data-name="Path 532"
                                                                    d="M140.026,111.529c.716-.416,1.3-.084,1.3.737a2.855,2.855,0,0,1-1.29,2.249c-.716.416-1.311.079-1.311-.748A2.865,2.865,0,0,1,140.026,111.529Z"
                                                                    transform="translate(-107.515 -90.919)"
                                                                    fill="#263238" />
                                                                <path id="Path_533" data-name="Path 533"
                                                                    d="M138.8,308.894c1.464-.843,2.633-.169,2.633,1.506a5.835,5.835,0,0,1-2.633,4.587c-1.459.843-2.67.153-2.675-1.527A5.835,5.835,0,0,1,138.8,308.894Z"
                                                                    transform="translate(-106.284 -184.251)"
                                                                    fill="#263238" />
                                                                <path id="Path_534" data-name="Path 534"
                                                                    d="M182.973,100.34l17.254,14.32-6.288,3.634-17.248-14.32Z"
                                                                    transform="translate(-125.487 -85.708)"
                                                                    fill="#007aff" />
                                                                <path id="Path_535" data-name="Path 535"
                                                                    d="M159.1,114.14l17.248,14.315-6.288,3.634L152.82,117.774Z"
                                                                    transform="translate(-114.189 -92.24)" fill="#007aff" />
                                                                <path id="Path_536" data-name="Path 536"
                                                                    d="M147.168,121.04l17.243,14.315-6.288,3.634L140.88,124.674Z"
                                                                    transform="translate(-108.537 -95.506)" fill="#fff" />
                                                                <path id="Path_537" data-name="Path 537"
                                                                    d="M171.038,107.24l17.248,14.32L182,125.189,164.75,110.874Z"
                                                                    transform="translate(-119.836 -88.974)" fill="#fff" />
                                                                <path id="Path_538" data-name="Path 538"
                                                                    d="M111.358,141.73,128.6,156.045l-6.288,3.634L105.07,145.364Z"
                                                                    transform="translate(-91.587 -105.299)"
                                                                    fill="#007aff" />
                                                                <path id="Path_539" data-name="Path 539"
                                                                    d="M135.228,127.94l17.243,14.315-6.288,3.629L128.94,131.569Z"
                                                                    transform="translate(-102.886 -98.772)"
                                                                    fill="#007aff" />
                                                                <path id="Path_540" data-name="Path 540"
                                                                    d="M92.91,152.333l6.4-3.7,17.238,14.315-6.42,3.708Z"
                                                                    transform="translate(-85.832 -108.565)" fill="#fff" />
                                                                <path id="Path_541" data-name="Path 541"
                                                                    d="M123.293,134.83l17.243,14.315-6.288,3.634L117.01,138.464Z"
                                                                    transform="translate(-97.239 -102.033)" fill="#fff" />
                                                                <path id="Path_542" data-name="Path 542"
                                                                    d="M215.728,127.53l-6.288,3.634h0a2.428,2.428,0,0,0,3.639,2.107h0a5.314,5.314,0,0,0,2.633-4.593Z"
                                                                    transform="translate(-140.989 -98.578)"
                                                                    fill="#007aff" />
                                                                <path id="Path_543" data-name="Path 543"
                                                                    d="M203.788,134.43l-6.288,3.634h0a2.428,2.428,0,0,0,3.639,2.107h0a5.3,5.3,0,0,0,2.633-4.593Z"
                                                                    transform="translate(-135.337 -101.844)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_544" data-name="Path 544"
                                                                    d="M191.848,141.33l-6.288,3.629h0a2.428,2.428,0,0,0,3.639,2.107h0a5.3,5.3,0,0,0,2.633-4.593Z"
                                                                    transform="translate(-129.686 -105.11)"
                                                                    fill="#007aff" />
                                                                <path id="Path_545" data-name="Path 545"
                                                                    d="M179.908,148.22l-6.288,3.634h0a2.423,2.423,0,0,0,3.639,2.107h0a5.3,5.3,0,0,0,2.633-4.587Z"
                                                                    transform="translate(-124.034 -108.371)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_546" data-name="Path 546"
                                                                    d="M167.968,155.12l-6.288,3.623h0a2.428,2.428,0,0,0,3.639,2.107h0a5.3,5.3,0,0,0,2.633-4.593Z"
                                                                    transform="translate(-118.382 -111.637)"
                                                                    fill="#007aff" />
                                                                <path id="Path_547" data-name="Path 547"
                                                                    d="M156.028,162l-6.288,3.629h0a2.428,2.428,0,0,0,3.639,2.107h0a5.314,5.314,0,0,0,2.633-4.593Z"
                                                                    transform="translate(-112.731 -114.894)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_548" data-name="Path 548"
                                                                    d="M144.088,168.91l-6.288,3.634h0a2.423,2.423,0,0,0,3.639,2.107h0a5.267,5.267,0,0,0,2.633-4.593Z"
                                                                    transform="translate(-107.079 -118.165)"
                                                                    fill="#007aff" />
                                                                <path id="Path_549" data-name="Path 549"
                                                                    d="M215.728,127.53l-6.288,3.634h0a2.428,2.428,0,0,0,3.639,2.107h0a5.314,5.314,0,0,0,2.633-4.593Z"
                                                                    transform="translate(-140.989 -98.578)" opacity="0.2" />
                                                                <path id="Path_550" data-name="Path 550"
                                                                    d="M191.848,141.33l-6.288,3.629h0a2.428,2.428,0,0,0,3.639,2.107h0a5.3,5.3,0,0,0,2.633-4.593Z"
                                                                    transform="translate(-129.686 -105.11)" opacity="0.2" />
                                                                <path id="Path_551" data-name="Path 551"
                                                                    d="M167.968,155.12l-6.288,3.623h0a2.428,2.428,0,0,0,3.639,2.107h0a5.3,5.3,0,0,0,2.633-4.593Z"
                                                                    transform="translate(-118.382 -111.637)"
                                                                    opacity="0.2" />
                                                                <path id="Path_552" data-name="Path 552"
                                                                    d="M144.088,168.91l-6.288,3.634h0a2.423,2.423,0,0,0,3.639,2.107h0a5.267,5.267,0,0,0,2.633-4.593Z"
                                                                    transform="translate(-107.079 -118.165)"
                                                                    opacity="0.2" />
                                                                <path id="Path_553" data-name="Path 553"
                                                                    d="M132.03,175.81l-6.42,3.687h0a2.423,2.423,0,0,0,3.634,2.107h0a5.572,5.572,0,0,0,2.786-4.83Z"
                                                                    transform="translate(-101.309 -121.431)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_554" data-name="Path 554"
                                                                    d="M138.676,211a15.49,15.49,0,0,0-5.34,1.648,17.07,17.07,0,0,0-8.29,9.543,15.526,15.526,0,0,0-.88,5.52,26.136,26.136,0,0,1,1.785-5.151,21.925,21.925,0,0,1,7.874-9.069A26.4,26.4,0,0,1,138.676,211Z"
                                                                    transform="translate(-100.624 -138.087)"
                                                                    fill="#007aff" />
                                                                <path id="Path_555" data-name="Path 555"
                                                                    d="M134.367,256.355a9.8,9.8,0,0,1-3.007-.527,8.469,8.469,0,0,1-4.961-4.356,9.659,9.659,0,0,1-.911-2.913,6.847,6.847,0,0,0,.406,3.128,7.373,7.373,0,0,0,5.319,4.666A6.846,6.846,0,0,0,134.367,256.355Z"
                                                                    transform="translate(-101.234 -155.865)"
                                                                    fill="#007aff" />
                                                                <path id="Path_556" data-name="Path 556"
                                                                    d="M152.83,231.2a23.946,23.946,0,0,1-.063,4.213,21.33,21.33,0,0,1-3.034,8.922,23.917,23.917,0,0,1-2.523,3.4,13.2,13.2,0,0,0,3.16-2.97,15.326,15.326,0,0,0,3.16-9.29A13.214,13.214,0,0,0,152.83,231.2Z"
                                                                    transform="translate(-111.533 -147.648)"
                                                                    fill="#007aff" />
                                                                <path id="Path_557" data-name="Path 557"
                                                                    d="M122.339,220.16a14.6,14.6,0,0,0-3.07,4.266,16.085,16.085,0,0,0-.832,11.866,14.821,14.821,0,0,0,2.449,4.656,25.279,25.279,0,0,1-1.58-4.887,20.7,20.7,0,0,1,.79-11.276A24.521,24.521,0,0,1,122.339,220.16Z"
                                                                    transform="translate(-97.554 -142.423)"
                                                                    fill="#007aff" />
                                                                <path id="Path_558" data-name="Path 558"
                                                                    d="M152.031,214.406a8.111,8.111,0,0,0-1.5-3.987,8.658,8.658,0,0,0-8.785-3.566,8.19,8.19,0,0,0-3.85,1.822,10.97,10.97,0,0,1,3.95-1.085,9.533,9.533,0,0,1,8.105,3.286A10.945,10.945,0,0,1,152.031,214.406Z"
                                                                    transform="translate(-107.127 -136.048)"
                                                                    fill="#007aff" />
                                                                <path id="Path_559" data-name="Path 559"
                                                                    d="M206.456,189.969h0a6.652,6.652,0,0,1,7.221,5.446l2.444,13.693,15.326,10.718-3.423,4.166-14.578-7.658a7.263,7.263,0,0,1-3.587-4.361l-4.7-15.848Z"
                                                                    transform="translate(-138.963 -128.116)"
                                                                    fill="#ffa8a7" />
                                                                <path id="Path_560" data-name="Path 560"
                                                                    d="M212.144,204.684a7.1,7.1,0,0,0,3.092-3.25l-1.375-6.278a6.652,6.652,0,0,0-7.173-5.188l-.232.026-1.3,6.167,2.022,9.08A7.147,7.147,0,0,0,212.144,204.684Z"
                                                                    transform="translate(-138.963 -128.116)"
                                                                    fill="#455a64" />
                                                                <path id="Path_561" data-name="Path 561"
                                                                    d="M213.961,215.01a7.147,7.147,0,0,1-4.961.558l.527,2.238c1.9.427,6.773.284,8.042-3.739l-.527-2.317A7.1,7.1,0,0,1,213.961,215.01Z"
                                                                    transform="translate(-140.781 -138.442)"
                                                                    fill="#455a64" />
                                                                <path id="Path_562" data-name="Path 562"
                                                                    d="M212.144,204.684a7.1,7.1,0,0,0,3.092-3.25l-1.375-6.278a6.652,6.652,0,0,0-7.173-5.188l-.232.026-1.3,6.167,2.022,9.08A7.147,7.147,0,0,0,212.144,204.684Z"
                                                                    transform="translate(-138.963 -128.116)" fill="#fff"
                                                                    opacity="0.2" />
                                                                <path id="Path_563" data-name="Path 563"
                                                                    d="M213.961,215.01a7.147,7.147,0,0,1-4.961.558l.527,2.238c1.9.427,6.773.284,8.042-3.739l-.527-2.317A7.1,7.1,0,0,1,213.961,215.01Z"
                                                                    transform="translate(-140.781 -138.442)" fill="#fff"
                                                                    opacity="0.5" />
                                                                <path id="Path_564" data-name="Path 564"
                                                                    d="M174.906,195.908s-.611-4.661-3.392-5.878c0,0-6.657,2-12.177,4.092a63.989,63.989,0,0,0-6.4,2.739c-1.254,1.085-4.851,4.313-6.541,5.182a8.032,8.032,0,0,1-2.633.79h0a9.2,9.2,0,0,0-3.276,1.2c-5.067,2.928-9.159,10.007-9.143,15.837,0,3.818,1.791,6.13,4.44,6.452h.121a5.95,5.95,0,0,0,1.259,0c4.608-.042,12-.079,14.9-.911C165.616,221.425,176.275,211.608,174.906,195.908Z"
                                                                    transform="translate(-104.022 -128.161)"
                                                                    fill="#455a64" />
                                                                <path id="Path_565" data-name="Path 565"
                                                                    d="M189.068,194.322l1.986-3.6,7.99,5.383-3.16,4.245a5.32,5.32,0,0,1-4.287.174,5.456,5.456,0,0,1-2.9-3.244A3.808,3.808,0,0,1,189.068,194.322Z"
                                                                    transform="translate(-131.102 -128.488)"
                                                                    fill="#ffa8a7" />
                                                                <path id="Path_566" data-name="Path 566"
                                                                    d="M194.72,191.67c0,4.34,3.45,6.373,5.556,7.142l1.685-2.259Z"
                                                                    transform="translate(-134.021 -128.938)"
                                                                    opacity="0.2" />
                                                                <path id="Path_567" data-name="Path 567"
                                                                    d="M187.5,171.864a14.66,14.66,0,0,1-.258-5.24,4.951,4.951,0,0,1,3.16-3.918,5.909,5.909,0,0,1,3.566-3.123,16.258,16.258,0,0,1,4.682-.569,22.69,22.69,0,0,1,5.214.29,7.716,7.716,0,0,1,4.466,2.533,7.436,7.436,0,0,1,1.3,5.72,16.721,16.721,0,0,1-2.165,5.6c-3.35.237-6.762.621-10.112.858a26.992,26.992,0,0,1-5.135.047,9.549,9.549,0,0,1-4.714-2.2Z"
                                                                    transform="translate(-130.413 -113.461)"
                                                                    fill="#263238" />
                                                                <path id="Path_568" data-name="Path 568"
                                                                    d="M207,168.088a28.635,28.635,0,0,1-1.643,15.3,5.554,5.554,0,0,1-2.08,2.881,5.858,5.858,0,0,1-3.687.311,19.024,19.024,0,0,1-5.862-1.648,8,8,0,0,1-4.055-4.371,3.523,3.523,0,0,1-3.555-1.4,6.647,6.647,0,0,1-1.153-3.876c0-.88.163-1.917.953-2.3a2.135,2.135,0,0,1,2.249.558,6.683,6.683,0,0,1,2.191,3.286c.579-2.144,1.48-4.213,2.059-6.32a3.675,3.675,0,0,1,1.248-2.228c.9-.574,2.065-.248,3.1,0A20.372,20.372,0,0,0,207,168.088Z"
                                                                    transform="translate(-129.405 -117.717)"
                                                                    fill="#ffa8a7" />
                                                                <path id="Path_569" data-name="Path 569"
                                                                    d="M214.539,180.53l2.5,4.208-2.981.564Z"
                                                                    transform="translate(-143.176 -123.665)"
                                                                    fill="#f28f8f" />
                                                                <path id="Path_570" data-name="Path 570"
                                                                    d="M208.528,192.34l2.265,1.1a1.475,1.475,0,0,1-1.717.49,1.338,1.338,0,0,1-.548-1.591Z"
                                                                    transform="translate(-140.519 -129.255)"
                                                                    fill="#f28f8f" />
                                                                <path id="Path_571" data-name="Path 571"
                                                                    d="M208.528,192.34l2.265,1.1a1.475,1.475,0,0,1-1.717.49,1.338,1.338,0,0,1-.548-1.591Z"
                                                                    transform="translate(-140.519 -129.255)"
                                                                    opacity="0.3" />
                                                                <path id="Path_572" data-name="Path 572"
                                                                    d="M209.872,194.359a1.359,1.359,0,0,1-.785-.074,1.2,1.2,0,0,1-.606-1.2,1.453,1.453,0,0,1,.9.311A1.438,1.438,0,0,1,209.872,194.359Z"
                                                                    transform="translate(-140.531 -129.61)"
                                                                    fill="#f28f8f" />
                                                                <path id="Path_573" data-name="Path 573"
                                                                    d="M206.766,180.155a.858.858,0,1,0,.937-.769A.858.858,0,0,0,206.766,180.155Z"
                                                                    transform="translate(-139.721 -123.121)"
                                                                    fill="#263238" />
                                                                <path id="Path_574" data-name="Path 574"
                                                                    d="M220.406,181a.853.853,0,1,0,.31-.577A.853.853,0,0,0,220.406,181Z"
                                                                    transform="translate(-146.177 -123.523)"
                                                                    fill="#263238" />
                                                                <path id="Path_575" data-name="Path 575"
                                                                    d="M222.63,175.265l1.759,1.5a1.222,1.222,0,0,0-.237-1.375A1.448,1.448,0,0,0,222.63,175.265Z"
                                                                    transform="translate(-147.232 -121.097)"
                                                                    fill="#263238" />
                                                                <path id="Path_576" data-name="Path 576"
                                                                    d="M207.486,174.9l-2.017,1.117a1.222,1.222,0,0,1,.5-1.3A1.454,1.454,0,0,1,207.486,174.9Z"
                                                                    transform="translate(-139.092 -120.851)"
                                                                    fill="#263238" />
                                                                <path id="Path_577" data-name="Path 577"
                                                                    d="M209.97,160.807a69.975,69.975,0,0,1-22.12,0c.342-8.953,4.371-11.887,11.323-11.887S210.049,152.649,209.97,160.807Z"
                                                                    transform="translate(-130.77 -108.703)"
                                                                    fill="#455a64" />
                                                                <path id="Path_578" data-name="Path 578"
                                                                    d="M215.3,163.268c0-3.429-.664-6.062-1.964-7.974l-2.912-.816a9.607,9.607,0,0,0-4.213-.221l-2.776.464a4.419,4.419,0,0,0-3.687,3.85l-.637,5.4h0a70.342,70.342,0,0,0,16.19-.706Z"
                                                                    transform="translate(-136.099 -111.164)"
                                                                    fill="#455a64" />
                                                                <path id="Path_579" data-name="Path 579"
                                                                    d="M215.3,163.268c0-3.429-.664-6.062-1.964-7.974l-2.912-.816a9.607,9.607,0,0,0-4.213-.221l-2.776.464a4.419,4.419,0,0,0-3.687,3.85l-.637,5.4h0a70.342,70.342,0,0,0,16.19-.706Z"
                                                                    transform="translate(-136.099 -111.164)" fill="#fff"
                                                                    opacity="0.29" />
                                                                <path id="Path_580" data-name="Path 580"
                                                                    d="M216.216,157.18h-1.285a10.757,10.757,0,0,0-1.238.047,17.085,17.085,0,0,0-10.154,4.308l-4.329,3.887a70.345,70.345,0,0,0,16.137-.706l2.818-3.418a2.528,2.528,0,0,0-1.949-4.119Z"
                                                                    transform="translate(-136.147 -112.612)"
                                                                    fill="#455a64" />
                                                                <path id="Path_581" data-name="Path 581"
                                                                    d="M216.216,157.18h-1.285a10.757,10.757,0,0,0-1.238.047,17.085,17.085,0,0,0-10.154,4.308l-4.329,3.887a70.345,70.345,0,0,0,16.137-.706l2.818-3.418a2.528,2.528,0,0,0-1.949-4.119Z"
                                                                    transform="translate(-136.147 -112.612)"
                                                                    opacity="0.2" />
                                                                <path id="Path_582" data-name="Path 582"
                                                                    d="M238.365,237.8l-19.634,11.408L198.97,237.8,218.6,226.39Z"
                                                                    transform="translate(-136.033 -145.372)"
                                                                    fill="#007aff" />
                                                                <g id="Group_1239" data-name="Group 1239"
                                                                    transform="translate(62.937 81.018)" opacity="0.3">
                                                                    <path id="Path_583" data-name="Path 583"
                                                                        d="M238.365,237.8l-19.634,11.408L198.97,237.8,218.6,226.39Z"
                                                                        transform="translate(-198.97 -226.39)"
                                                                        fill="#fff" />
                                                                </g>
                                                                <path id="Path_584" data-name="Path 584"
                                                                    d="M256.072,248.06l-.058,19.971L236.38,279.439l.058-19.971Z"
                                                                    transform="translate(-153.74 -155.629)"
                                                                    fill="#007aff" />
                                                                <g id="Group_1240" data-name="Group 1240"
                                                                    transform="translate(82.64 92.431)" opacity="0.1">
                                                                    <path id="Path_585" data-name="Path 585"
                                                                        d="M256.072,248.06l-.058,19.971L236.38,279.439l.058-19.971Z"
                                                                        transform="translate(-236.38 -248.06)"
                                                                        fill="#fff" />
                                                                </g>
                                                                <path id="Path_586" data-name="Path 586"
                                                                    d="M218.679,259.468l-.058,19.971L198.86,268.031l.058-19.971Z"
                                                                    transform="translate(-135.981 -155.629)"
                                                                    fill="#007aff" />
                                                                <path id="Path_587" data-name="Path 587"
                                                                    d="M239.908,239.87l6.647-4.061-3.392-1.959-6.673,3.982Z"
                                                                    transform="translate(-153.792 -148.903)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_588" data-name="Path 588"
                                                                    d="M230.778,239.813l-3.418-2.038-6.778-4.045-3.381,1.964,6.736,4.119,3.423,2.091,9.038,5.53,3.623-2.107Z"
                                                                    transform="translate(-144.662 -148.846)" fill="#fff" />
                                                                <path id="Path_589" data-name="Path 589"
                                                                    d="M221.65,245.28l-9.28,5.535,3.634,2.1,9.069-5.546Z"
                                                                    transform="translate(-142.376 -154.313)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_590" data-name="Path 590"
                                                                    d="M257.273,255.75l-3.624,2.107.032.016.053,19.871,3.666-2.128-.053-19.829Z"
                                                                    transform="translate(-161.915 -159.269)"
                                                                    fill="#e0e0e0" />
                                                                <path id="Path_591" data-name="Path 591"
                                                                    d="M215.924,257.891l-3.634-2.1-.032.016-.058,19.945,3.666,2.112.058-19.971Z"
                                                                    transform="translate(-142.295 -159.288)" fill="#fff" />
                                                                <path id="Path_592" data-name="Path 592"
                                                                    d="M215.924,257.891l-3.634-2.1-.032.016-.058,19.945,3.666,2.112.058-19.971Z"
                                                                    transform="translate(-142.295 -159.288)"
                                                                    opacity="0.2" />
                                                                <path id="Path_593" data-name="Path 593"
                                                                    d="M232.778,256.74l-4.108,2.512,4.108,2.375,4.1-2.381Z"
                                                                    transform="translate(-150.091 -159.737)" fill="#fff" />
                                                                <path id="Path_594" data-name="Path 594"
                                                                    d="M162.634,230.73s1.533-22.3,1.854-23.7a4.913,4.913,0,0,1,9.649.469c.3,2.2-3.213,23.579-3.213,23.579l12.724,13.462,2.575,2.365,2.3-.463,5.235.5a.643.643,0,0,1,.569.527h0a.648.648,0,0,1-.474.758l-3.46.843,2.5,1.706a3.016,3.016,0,0,0,1.164.479l3.913.743a.716.716,0,0,1,.585.753h0a.722.722,0,0,1-.463.632l-2.107.785a10.128,10.128,0,0,1-8.427-.6l-4.529-2.517a11.2,11.2,0,0,1-1.87-1.264l-16.237-13.43A6.357,6.357,0,0,1,162.634,230.73Z"
                                                                    transform="translate(-118.815 -134.417)"
                                                                    fill="#ffa8a7" />
                                                                <path id="Path_595" data-name="Path 595"
                                                                    d="M173.063,221.93l1.627-12.113a6.333,6.333,0,0,0-12.572-1.538L160.76,220.64a15.147,15.147,0,0,0,12.3,1.275Z"
                                                                    transform="translate(-117.947 -134.165)"
                                                                    fill="#455a64" />
                                                                <path id="Path_596" data-name="Path 596"
                                                                    d="M173.063,221.93l1.627-12.113a6.333,6.333,0,0,0-12.572-1.538L160.76,220.64a15.147,15.147,0,0,0,12.3,1.275Z"
                                                                    transform="translate(-117.947 -134.165)" fill="#fff"
                                                                    opacity="0.2" />
                                                                <path id="Path_597" data-name="Path 597"
                                                                    d="M166.224,238.7a15.5,15.5,0,0,1-5.793-1.922l-.311,2.849c5.472,4.213,12.182,1.612,12.182,1.612l.432-3.186a15.448,15.448,0,0,1-6.51.648Z"
                                                                    transform="translate(-117.644 -150.29)"
                                                                    fill="#455a64" />
                                                                <path id="Path_598" data-name="Path 598"
                                                                    d="M166.224,238.7a15.5,15.5,0,0,1-5.793-1.922l-.311,2.849c5.472,4.213,12.182,1.612,12.182,1.612l.432-3.186a15.448,15.448,0,0,1-6.51.648Z"
                                                                    transform="translate(-117.644 -150.29)" fill="#fff"
                                                                    opacity="0.5" />
                                                            </g>
                                                            <g id="freepik--Plants--inject-2"
                                                                transform="translate(23.777 151.558)">
                                                                <path id="Path_599" data-name="Path 599"
                                                                    d="M441.878,317.77l8.543.253,8.543-.253c.363,5.793-.758,11.65-2.312,14.52h0a3.892,3.892,0,0,1-1.691,1.717,10.009,10.009,0,0,1-9.1,0,3.8,3.8,0,0,1-1.685-1.764C442.626,329.357,441.514,323.511,441.878,317.77Z"
                                                                    transform="translate(-224.279 -288.298)" fill="#fff" />
                                                                <path id="Path_600" data-name="Path 600"
                                                                    d="M444.464,309.827c-3.344,1.933-3.365,5.067-.042,7a13.328,13.328,0,0,0,12.082,0c3.35-1.928,3.365-5.067.037-7A13.3,13.3,0,0,0,444.464,309.827Z"
                                                                    transform="translate(-224.342 -283.853)"
                                                                    fill="#ebebeb" />
                                                                <path id="Path_601" data-name="Path 601"
                                                                    d="M447.462,311.575c-2.633,1.506-2.633,3.95-.032,5.462a10.382,10.382,0,0,0,9.427,0c2.612-1.506,2.633-3.955.026-5.462A10.388,10.388,0,0,0,447.462,311.575Z"
                                                                    transform="translate(-226.018 -284.831)"
                                                                    fill="#e0e0e0" />
                                                                <g id="Group_1241" data-name="Group 1241"
                                                                    transform="translate(219.464 25.614)" opacity="0.15">
                                                                    <path id="Path_602" data-name="Path 602"
                                                                        d="M447.462,311.575c-2.633,1.506-2.633,3.95-.032,5.462a10.382,10.382,0,0,0,9.427,0c2.612-1.506,2.633-3.955.026-5.462A10.388,10.388,0,0,0,447.462,311.575Z"
                                                                        transform="translate(-445.483 -310.445)" />
                                                                </g>
                                                                <path id="Path_603" data-name="Path 603"
                                                                    d="M456.529,273.808c1.39-1.728,3-3.276,4.277-5.088a5.427,5.427,0,0,0,1.227-4.213,3.766,3.766,0,0,0-2.986-2.633,5.725,5.725,0,0,0-4.013.9A12.246,12.246,0,0,0,452,265.655a30.142,30.142,0,0,0-6.352,21.536,4.535,4.535,0,0,0,.8,2.475,2.481,2.481,0,0,0,2.554.779c2.328-.69,2.017-3.834,2.4-5.683a25.85,25.85,0,0,1,2.676-7.252A25.032,25.032,0,0,1,456.529,273.808Z"
                                                                    transform="translate(-226.032 -261.811)"
                                                                    fill="#007aff" />
                                                                <path id="Path_604" data-name="Path 604"
                                                                    d="M456.529,273.808c1.39-1.728,3-3.276,4.277-5.088a5.427,5.427,0,0,0,1.227-4.213,3.766,3.766,0,0,0-2.986-2.633,5.725,5.725,0,0,0-4.013.9A12.246,12.246,0,0,0,452,265.655a30.142,30.142,0,0,0-6.352,21.536,4.535,4.535,0,0,0,.8,2.475,2.481,2.481,0,0,0,2.554.779c2.328-.69,2.017-3.834,2.4-5.683a25.85,25.85,0,0,1,2.676-7.252A25.032,25.032,0,0,1,456.529,273.808Z"
                                                                    transform="translate(-226.032 -261.811)" fill="#fff"
                                                                    opacity="0.2" />
                                                                <path id="Path_605" data-name="Path 605"
                                                                    d="M451.632,289.9a.205.205,0,0,0,.163-.195c.163-3.734,1.38-16.479,10.165-23.7a.195.195,0,0,0,.026-.284.2.2,0,0,0-.284-.032c-8.917,7.342-10.149,20.24-10.312,24.016a.205.205,0,0,0,.195.211Z"
                                                                    transform="translate(-228.814 -263.626)"
                                                                    fill="#f0f0f0" />
                                                                <path id="Path_606" data-name="Path 606"
                                                                    d="M449.19,299.346a6.215,6.215,0,0,0,2.849,3.955,7.99,7.99,0,0,0,4.319.727,19.1,19.1,0,0,0,4.382-1.053c.706-.237,1.485-.553,1.58-1.427a1.58,1.58,0,0,0-.5-1.227,4.778,4.778,0,0,0-.453-.395.448.448,0,0,1,.237-.806,10.92,10.92,0,0,0,1.248-.158,5.451,5.451,0,0,0,2.633-1.185,4.351,4.351,0,0,0,1.211-1.791,1.687,1.687,0,0,0-.174-1.764,1.88,1.88,0,0,0-.969-.421c-.311-.068-.627-.126-.937-.174a.416.416,0,0,1-.1-.8l1.327-.553a2.464,2.464,0,0,0,1.006-.627,2.191,2.191,0,0,0,.253-1.822c-.163-.832-.448-1.633-.643-2.454a5.268,5.268,0,0,1-.111-2.491,21.067,21.067,0,0,0-7.421.5,5.394,5.394,0,0,0-2.028.885,2.948,2.948,0,0,0-.953,2.733.19.19,0,0,1-.358.1l-.827-1.58a1.006,1.006,0,0,0-1.317-.421,6.121,6.121,0,0,0-1.938,1.527,3.355,3.355,0,0,0-.827,2.475,21.8,21.8,0,0,0,.616,3.639.332.332,0,0,1-.527.353h-.037a1.133,1.133,0,0,0-.8-.232c-.748.09-.937.848-.99,1.5a12.545,12.545,0,0,0,.248,2.991Z"
                                                                    transform="translate(-227.651 -272.671)"
                                                                    fill="#007aff" />
                                                                <path id="Path_607" data-name="Path 607"
                                                                    d="M449.19,299.346a6.215,6.215,0,0,0,2.849,3.955,7.99,7.99,0,0,0,4.319.727,19.1,19.1,0,0,0,4.382-1.053c.706-.237,1.485-.553,1.58-1.427a1.58,1.58,0,0,0-.5-1.227,4.778,4.778,0,0,0-.453-.395.448.448,0,0,1,.237-.806,10.92,10.92,0,0,0,1.248-.158,5.451,5.451,0,0,0,2.633-1.185,4.351,4.351,0,0,0,1.211-1.791,1.687,1.687,0,0,0-.174-1.764,1.88,1.88,0,0,0-.969-.421c-.311-.068-.627-.126-.937-.174a.416.416,0,0,1-.1-.8l1.327-.553a2.464,2.464,0,0,0,1.006-.627,2.191,2.191,0,0,0,.253-1.822c-.163-.832-.448-1.633-.643-2.454a5.268,5.268,0,0,1-.111-2.491,21.067,21.067,0,0,0-7.421.5,5.394,5.394,0,0,0-2.028.885,2.948,2.948,0,0,0-.953,2.733.19.19,0,0,1-.358.1l-.827-1.58a1.006,1.006,0,0,0-1.317-.421,6.121,6.121,0,0,0-1.938,1.527,3.355,3.355,0,0,0-.827,2.475,21.8,21.8,0,0,0,.616,3.639.332.332,0,0,1-.527.353h-.037a1.133,1.133,0,0,0-.8-.232c-.748.09-.937.848-.99,1.5a12.545,12.545,0,0,0,.248,2.991Z"
                                                                    transform="translate(-227.651 -272.671)"
                                                                    opacity="0.2" />
                                                                <path id="Path_608" data-name="Path 608"
                                                                    d="M449.19,299.346a6.215,6.215,0,0,0,2.849,3.955,7.99,7.99,0,0,0,4.319.727,19.1,19.1,0,0,0,4.382-1.053c.706-.237,1.485-.553,1.58-1.427a1.58,1.58,0,0,0-.5-1.227,4.778,4.778,0,0,0-.453-.395.448.448,0,0,1,.237-.806,10.92,10.92,0,0,0,1.248-.158,5.451,5.451,0,0,0,2.633-1.185,4.351,4.351,0,0,0,1.211-1.791,1.687,1.687,0,0,0-.174-1.764,1.88,1.88,0,0,0-.969-.421c-.311-.068-.627-.126-.937-.174a.416.416,0,0,1-.1-.8l1.327-.553a2.464,2.464,0,0,0,1.006-.627,2.191,2.191,0,0,0,.253-1.822c-.163-.832-.448-1.633-.643-2.454a5.268,5.268,0,0,1-.111-2.491,21.067,21.067,0,0,0-7.421.5,5.394,5.394,0,0,0-2.028.885,2.948,2.948,0,0,0-.953,2.733.19.19,0,0,1-.358.1l-.827-1.58a1.006,1.006,0,0,0-1.317-.421,6.121,6.121,0,0,0-1.938,1.527,3.355,3.355,0,0,0-.827,2.475,21.8,21.8,0,0,0,.616,3.639.332.332,0,0,1-.527.353h-.037a1.133,1.133,0,0,0-.8-.232c-.748.09-.937.848-.99,1.5a12.545,12.545,0,0,0,.248,2.991Z"
                                                                    transform="translate(-227.651 -272.671)" fill="#fff"
                                                                    opacity="0.2" />
                                                                <path id="Path_609" data-name="Path 609"
                                                                    d="M456.887,304.132a29.925,29.925,0,0,1,2-5.793.215.215,0,0,1,.068-.153l.142-.3a18.739,18.739,0,0,0-1.98-6.625.211.211,0,0,1,.074-.279.205.205,0,0,1,.279.079,18.882,18.882,0,0,1,1.949,6.157,26.956,26.956,0,0,1,8.548-10.249.205.205,0,0,1,.284.053.2.2,0,0,1-.053.279,26.945,26.945,0,0,0-8.643,10.565,12.5,12.5,0,0,1,7.073-.958.211.211,0,0,1,.163.242.2.2,0,0,1-.163.158h-.079a12.166,12.166,0,0,0-7.279,1.164,29.492,29.492,0,0,0-1.98,5.736.211.211,0,0,1-.163.158h-.079A.2.2,0,0,1,456.887,304.132Z"
                                                                    transform="translate(-231.415 -273.702)"
                                                                    fill="#f0f0f0" />
                                                                <path id="Path_610" data-name="Path 610"
                                                                    d="M69.787,350.91l-9.722.284-9.717-.284c-.421,6.562.864,13.251,2.633,16.522h0a4.413,4.413,0,0,0,1.928,1.959,11.382,11.382,0,0,0,10.349,0,4.287,4.287,0,0,0,1.922-2.007C68.934,364.093,70.2,357.441,69.787,350.91Z"
                                                                    transform="translate(-38.951 -303.984)" fill="#fff" />
                                                                <path id="Path_611" data-name="Path 611"
                                                                    d="M66.99,341.868c3.808,2.2,3.829,5.762.047,7.963a15.147,15.147,0,0,1-13.746,0c-3.808-2.2-3.829-5.762-.047-7.963A15.147,15.147,0,0,1,66.99,341.868Z"
                                                                    transform="translate(-39.023 -298.924)"
                                                                    fill="#ebebeb" />
                                                                <path id="Path_612" data-name="Path 612"
                                                                    d="M67.384,343.846c2.97,1.717,2.986,4.5.037,6.215a11.822,11.822,0,0,1-10.723,0c-2.97-1.712-2.986-4.5-.037-6.215A11.838,11.838,0,0,1,67.384,343.846Z"
                                                                    transform="translate(-40.935 -300.033)"
                                                                    fill="#e0e0e0" />
                                                                <g id="Group_1242" data-name="Group 1242"
                                                                    transform="translate(13.525 42.529)" opacity="0.15">
                                                                    <path id="Path_613" data-name="Path 613"
                                                                        d="M67.384,343.846c2.97,1.717,2.986,4.5.037,6.215a11.822,11.822,0,0,1-10.723,0c-2.97-1.712-2.986-4.5-.037-6.215A11.838,11.838,0,0,1,67.384,343.846Z"
                                                                        transform="translate(-54.46 -342.563)" />
                                                                </g>
                                                                <path id="Path_614" data-name="Path 614"
                                                                    d="M81.432,272.16c-21.9,7.173-22.12,21.825-17.164,32.259,1.243,2.107,5.014,3.249,8.29-9.728l-5.751.737,6.7-3.4,1.743-5.2-5.593.416,7.1-2.986a104.608,104.608,0,0,1,4.672-12.092Z"
                                                                    transform="translate(-44.334 -266.71)" fill="#007aff" />
                                                                <path id="Path_615" data-name="Path 615"
                                                                    d="M81.432,272.16c-21.9,7.173-22.12,21.825-17.164,32.259,1.243,2.107,5.014,3.249,8.29-9.728l-5.751.737,6.7-3.4,1.743-5.2-5.593.416,7.1-2.986a104.608,104.608,0,0,1,4.672-12.092Z"
                                                                    transform="translate(-44.334 -266.71)" opacity="0.2" />
                                                                <path id="Path_616" data-name="Path 616"
                                                                    d="M81.432,272.16c-21.9,7.173-22.12,21.825-17.164,32.259,1.243,2.107,5.014,3.249,8.29-9.728l-5.751.737,6.7-3.4,1.743-5.2-5.593.416,7.1-2.986a104.608,104.608,0,0,1,4.672-12.092Z"
                                                                    transform="translate(-44.334 -266.71)" fill="#fff"
                                                                    opacity="0.2" />
                                                                <path id="Path_617" data-name="Path 617"
                                                                    d="M77.62,279.12c-11.55,8.764-12.593,18.818-9.327,29.309C67.14,299.191,65.891,289.848,77.62,279.12Z"
                                                                    transform="translate(-46.721 -270.004)" fill="#fff" />
                                                                <path id="Path_618" data-name="Path 618"
                                                                    d="M28.78,300.42c21.593,3.039,24.227,16.653,21.341,27.192-.806,2.175-4.129,3.861-9.338-7.684l5.493-.242-6.847-2.075-2.486-4.561,5.267-.527-7.1-1.58A100.517,100.517,0,0,0,28.78,300.42Z"
                                                                    transform="translate(-28.78 -280.086)" fill="#007aff" />
                                                                <path id="Path_619" data-name="Path 619"
                                                                    d="M28.78,300.42c21.593,3.039,24.227,16.653,21.341,27.192-.806,2.175-4.129,3.861-9.338-7.684l5.493-.242-6.847-2.075-2.486-4.561,5.267-.527-7.1-1.58A100.517,100.517,0,0,0,28.78,300.42Z"
                                                                    transform="translate(-28.78 -280.086)" fill="#fff"
                                                                    opacity="0.2" />
                                                                <path id="Path_620" data-name="Path 620"
                                                                    d="M28.78,300.42c21.593,3.039,24.227,16.653,21.341,27.192-.806,2.175-4.129,3.861-9.338-7.684l5.493-.242-6.847-2.075-2.486-4.561,5.267-.527-7.1-1.58A100.517,100.517,0,0,0,28.78,300.42Z"
                                                                    transform="translate(-28.78 -280.086)" opacity="0.1" />
                                                                <path id="Path_621" data-name="Path 621"
                                                                    d="M40.89,305c12.213,6.246,14.852,15.431,13.556,25.744C53.988,321.922,53.588,313.016,40.89,305Z"
                                                                    transform="translate(-34.512 -282.254)" fill="#fff" />
                                                                <path id="Path_622" data-name="Path 622"
                                                                    d="M90.951,310.11c-19.8.321-23.7,12.3-22.278,22.12.485,2.049,3.292,3.939,9.28-5.883l-4.914-.853,6.367-1.09,2.754-3.818-4.7-1.08,6.583-.648A90.544,90.544,0,0,1,90.951,310.11Z"
                                                                    transform="translate(-47.538 -284.673)"
                                                                    fill="#007aff" />
                                                                <path id="Path_623" data-name="Path 623"
                                                                    d="M86.22,312.83c-11.708,4.261-15.115,12.25-15.1,21.688C72.516,326.645,73.864,318.66,86.22,312.83Z"
                                                                    transform="translate(-48.821 -285.96)" fill="#fff" />
                                                                <path id="Path_624" data-name="Path 624"
                                                                    d="M90.951,310.11c-19.8.321-23.7,12.3-22.278,22.12.485,2.049,3.292,3.939,9.28-5.883l-4.914-.853,6.367-1.09,2.754-3.818-4.7-1.08,6.583-.648A90.544,90.544,0,0,1,90.951,310.11Z"
                                                                    transform="translate(-47.538 -284.673)" fill="#fff"
                                                                    opacity="0.2" />
                                                            </g>
                                                            <g id="freepik--character-1--inject-2"
                                                                transform="translate(150.96 106.735)">
                                                                <path id="Path_625" data-name="Path 625"
                                                                    d="M308.395,256.069c-5.2,3.613-11.06,6.109-15.395,10.949-8.105,9.038-8.3,23.584-1.928,33.923s18.212,16.548,30.273,17.97A48.6,48.6,0,0,0,357.2,308.9c9.928-8.005,16.242-20.461,16.237-33.18s-6.531-25.428-17.2-32.39c-9.738-6.32-23.726-8.158-33.5-.9-4.935,3.687-8.232,9.1-13.167,12.782C309.216,255.505,308.816,255.785,308.395,256.069Z"
                                                                    transform="translate(-277.987 -205.595)"
                                                                    fill="#007aff" />
                                                                <path id="Path_626" data-name="Path 626"
                                                                    d="M308.395,256.069c-5.2,3.613-11.06,6.109-15.395,10.949-8.105,9.038-8.3,23.584-1.928,33.923s18.212,16.548,30.273,17.97A48.6,48.6,0,0,0,357.2,308.9c9.928-8.005,16.242-20.461,16.237-33.18s-6.531-25.428-17.2-32.39c-9.738-6.32-23.726-8.158-33.5-.9-4.935,3.687-8.232,9.1-13.167,12.782C309.216,255.505,308.816,255.785,308.395,256.069Z"
                                                                    transform="translate(-277.987 -205.595)"
                                                                    opacity="0.2" />
                                                                <path id="Path_627" data-name="Path 627"
                                                                    d="M308.395,256.069c-5.2,3.613-11.06,6.109-15.395,10.949-8.105,9.038-8.3,23.584-1.928,33.923s18.212,16.548,30.273,17.97A48.6,48.6,0,0,0,357.2,308.9c9.928-8.005,16.242-20.461,16.237-33.18s-6.531-25.428-17.2-32.39c-9.738-6.32-23.726-8.158-33.5-.9-4.935,3.687-8.232,9.1-13.167,12.782C309.216,255.505,308.816,255.785,308.395,256.069Z"
                                                                    transform="translate(-277.987 -205.595)" fill="#fff"
                                                                    opacity="0.1" />
                                                                <path id="Path_628" data-name="Path 628"
                                                                    d="M317.893,320.036a2.031,2.031,0,0,1,.037-.258,13.693,13.693,0,0,1,11.086-11.387,43.188,43.188,0,0,0,14.22-5.441c3.1-1.875,6.036-4.176,9.517-5.193a15.521,15.521,0,0,1,10.26.6,11.9,11.9,0,0,0-11.95-3.034c2.77-4.25,5.214-8.822,6.173-13.8s.274-10.454-2.728-14.541a14.631,14.631,0,0,0-22.6-1.243c-2.976,3.255-4.65,7.837-6.6,11.787-1.406,2.839-2.855,5.746-5.172,7.9-3.487,3.265-8.511,4.44-12.113,7.563-4.1,3.545-5.872,9.206-5.888,14.626a42.867,42.867,0,0,0,1.58,10.813,41.033,41.033,0,0,0,14.842,9.075A16.085,16.085,0,0,1,317.893,320.036Z"
                                                                    transform="translate(-285.347 -216.595)"
                                                                    opacity="0.1" />
                                                                <path id="Path_629" data-name="Path 629"
                                                                    d="M397.276,274.093A37.193,37.193,0,0,0,365.555,238a27.254,27.254,0,0,0-3.587.6,36.251,36.251,0,0,1,34.323,35.524c.237,11.855-5.567,23.942-15.516,32.327-7.795,6.573-18.149,10.934-29.536,12.5.242.037.49.068.732.095,1.022.121,2.049.205,3.081.263a57.743,57.743,0,0,0,26.333-12.113C391.583,298.614,397.513,286.243,397.276,274.093Z"
                                                                    transform="translate(-308.592 -205.718)" fill="#fff" />
                                                                <path id="Path_630" data-name="Path 630"
                                                                    d="M323.34,384.458l1.306,2.586a7.137,7.137,0,0,0,2.175-3.081c-.6-2.107-1.29-4.25-1.638-5.293-.174,1.454-1.164,2.7-1.58,4.119a7.208,7.208,0,0,0-.263,1.67Z"
                                                                    transform="translate(-295.386 -272.301)"
                                                                    fill="#37474f" />
                                                                <path id="Path_631" data-name="Path 631"
                                                                    d="M323.394,392.309l1.243-.063-1.306-2.586C323.294,390.534,323.368,391.419,323.394,392.309Z"
                                                                    transform="translate(-295.377 -277.503)"
                                                                    fill="#263238" />
                                                                <path id="Path_632" data-name="Path 632"
                                                                    d="M313.226,371.261a11.428,11.428,0,0,0-1.543-1.8,6.246,6.246,0,0,1-.026,3.45c-.384,1.5-1.085,2.9-1.438,4.408-.427,1.833-.327,3.8-1.106,5.519a5.9,5.9,0,0,1-4.134,3.3,2.6,2.6,0,0,1-1.97-.237,1.4,1.4,0,0,1-.527-.732,12.983,12.983,0,0,1-1.417,2.307c-.611.79-1.375,1.664-1.206,2.633a2.107,2.107,0,0,0,1.728,1.58,5.582,5.582,0,0,0,2.46-.205,15.334,15.334,0,0,0,4.924-2.007,7.373,7.373,0,0,0,3.128-4.213,9.414,9.414,0,0,0,.19-2.554c0-.885-.1-1.775-.063-2.633a7.209,7.209,0,0,1,.258-1.67c.421-1.417,1.412-2.665,1.58-4.119,0-.079.026-.153.032-.232A4.74,4.74,0,0,0,313.226,371.261Z"
                                                                    transform="translate(-284.261 -267.942)"
                                                                    fill="#455a64" />
                                                                <path id="Path_633" data-name="Path 633"
                                                                    d="M313.226,371.261a11.428,11.428,0,0,0-1.543-1.8,6.246,6.246,0,0,1-.026,3.45c-.384,1.5-1.085,2.9-1.438,4.408-.427,1.833-.327,3.8-1.106,5.519a5.9,5.9,0,0,1-4.134,3.3,2.6,2.6,0,0,1-1.97-.237,1.4,1.4,0,0,1-.527-.732,12.983,12.983,0,0,1-1.417,2.307c-.611.79-1.375,1.664-1.206,2.633a2.107,2.107,0,0,0,1.728,1.58,5.582,5.582,0,0,0,2.46-.205,15.334,15.334,0,0,0,4.924-2.007,7.373,7.373,0,0,0,3.128-4.213,9.414,9.414,0,0,0,.19-2.554c0-.885-.1-1.775-.063-2.633a7.209,7.209,0,0,1,.258-1.67c.421-1.417,1.412-2.665,1.58-4.119,0-.079.026-.153.032-.232A4.74,4.74,0,0,0,313.226,371.261Z"
                                                                    transform="translate(-284.261 -267.942)"
                                                                    opacity="0.1" />
                                                                <path id="Path_634" data-name="Path 634"
                                                                    d="M307.347,385.742a5.9,5.9,0,0,0,4.134-3.3c.78-1.717.679-3.687,1.106-5.519.353-1.506,1.053-2.912,1.438-4.408a6.246,6.246,0,0,0,.026-3.45c-.147-.148-.295-.3-.448-.437a52.75,52.75,0,0,0-6.768,3.571,48.258,48.258,0,0,1-.5,7.753,18.3,18.3,0,0,1-1.5,4.819,1.4,1.4,0,0,0,.527.732A2.6,2.6,0,0,0,307.347,385.742Z"
                                                                    transform="translate(-286.63 -267.549)"
                                                                    fill="#ab6160" />
                                                                <path id="Path_635" data-name="Path 635"
                                                                    d="M293.467,388.14a51.42,51.42,0,0,1-2.007,6.51s7.695.311,7.868-.042l2.259-3.5Z"
                                                                    transform="translate(-280.297 -276.784)"
                                                                    fill="#c48281" />
                                                                <path id="Path_636" data-name="Path 636"
                                                                    d="M316.88,291.26l-19.618,10.923a9.728,9.728,0,0,0-4.83,10.275L296.24,333l-3.123,9.928c2.7,3.613,8.885,3.8,8.885,3.8,3.455-6.926,5.988-9.865,7.947-15.668s3.271-9.807,3.271-9.807l16.327-4.456a19.329,19.329,0,0,0,14.131-16.443c.279-2.465.29-4.145.29-4.145Z"
                                                                    transform="translate(-280.679 -230.927)"
                                                                    fill="#455a64" />
                                                                <path id="Path_637" data-name="Path 637"
                                                                    d="M319.134,303.53a9.28,9.28,0,0,0,2.2,5.983l-12.371,7.726a9.964,9.964,0,0,0-4.245,5.53l-4.9,16.037,4.424-17.859a8.1,8.1,0,0,1,3.6-4.94l11.313-6.963A6.628,6.628,0,0,1,319.134,303.53Z"
                                                                    transform="translate(-284.249 -236.735)"
                                                                    opacity="0.2" />
                                                                <path id="Path_638" data-name="Path 638"
                                                                    d="M299.39,415.263l.179,2.886a7.093,7.093,0,0,0,3.213-1.975c.279-2.175.485-4.419.579-5.514-.737,1.269-2.138,2.028-3.081,3.16a6.748,6.748,0,0,0-.89,1.443Z"
                                                                    transform="translate(-284.05 -287.443)"
                                                                    fill="#37474f" />
                                                                <path id="Path_639" data-name="Path 639"
                                                                    d="M297.52,421.854l1.164.432L298.5,419.4C298.126,420.185,297.841,421.033,297.52,421.854Z"
                                                                    transform="translate(-283.165 -291.58)"
                                                                    fill="#263238" />
                                                                <path id="Path_640" data-name="Path 640"
                                                                    d="M290,402.679a11.581,11.581,0,0,0-.706-2.259,6.267,6.267,0,0,1-1.38,3.16c-.943,1.222-2.144,2.238-3.055,3.481-1.117,1.522-1.8,3.371-3.186,4.64a5.92,5.92,0,0,1-5.1,1.412,2.6,2.6,0,0,1-1.712-1,1.406,1.406,0,0,1-.211-.885,13.159,13.159,0,0,1-2.207,1.58c-.874.49-1.922.99-2.154,1.964a2.106,2.106,0,0,0,.969,2.107,5.572,5.572,0,0,0,2.338.78,15.305,15.305,0,0,0,5.319.09,7.326,7.326,0,0,0,4.519-2.633,9.435,9.435,0,0,0,1.185-2.275c.321-.822.606-1.67.985-2.454a6.745,6.745,0,0,1,.89-1.438c.943-1.138,2.344-1.9,3.081-3.16.037-.063.084-.126.121-.2A4.739,4.739,0,0,0,290,402.679Z"
                                                                    transform="translate(-270.264 -282.596)"
                                                                    fill="#455a64" />
                                                                <path id="Path_641" data-name="Path 641"
                                                                    d="M280.507,412.6a5.92,5.92,0,0,0,5.1-1.411c1.385-1.269,2.07-3.118,3.186-4.64.911-1.243,2.107-2.259,3.055-3.481a6.267,6.267,0,0,0,1.38-3.16l-.237-.579a51.846,51.846,0,0,0-7.631.621,47.736,47.736,0,0,1-3.5,6.931,18.046,18.046,0,0,1-3.276,3.845,1.407,1.407,0,0,0,.211.885A2.6,2.6,0,0,0,280.507,412.6Z"
                                                                    transform="translate(-274.199 -282.08)"
                                                                    fill="#c48281" />
                                                                <path id="Path_642" data-name="Path 642"
                                                                    d="M352.864,208.034a31.236,31.236,0,0,0,.527-23.384,12.81,12.81,0,0,0-4.334-6.2c-2.718-1.833-6.23-1.806-9.506-1.712a16.249,16.249,0,0,0-5.146.706,5.3,5.3,0,0,0-3.4,3.576,4.558,4.558,0,0,0-4.882,3.4,7.161,7.161,0,0,0,1.512,6.115,17.622,17.622,0,0,0,5.035,4.1,5.549,5.549,0,0,0-2.349,3.16,4.982,4.982,0,0,0,.258,3.687,4.935,4.935,0,0,0-4.6,3.276,6.2,6.2,0,0,0,1.111,5.583,9.027,9.027,0,0,0,4.93,3.086,16.853,16.853,0,0,0,5.888.316c2.033,1.97,5.757,1.928,8.311.706A14.763,14.763,0,0,0,352.864,208.034Z"
                                                                    transform="translate(-296.523 -176.705)"
                                                                    fill="#263238" />
                                                                <path id="Path_643" data-name="Path 643"
                                                                    d="M326.346,226.8h-.5c-3.344.037-7.579,2.317-9.649,8.153l-8.037,15.568,9.175,4.066,5.019-14.694Z"
                                                                    transform="translate(-288.201 -200.416)"
                                                                    fill="#c48281" />
                                                                <path id="Path_644" data-name="Path 644"
                                                                    d="M321.376,241.663a20.785,20.785,0,0,0-.779,5.793,15.443,15.443,0,0,1-1.217,6.02,5.525,5.525,0,0,0-6.615.242,5.1,5.1,0,0,0-1.485,2.107l7.531,3.339,5.019-14.694,2.47-8.1A9.4,9.4,0,0,0,321.376,241.663Z"
                                                                    transform="translate(-289.678 -204.946)"
                                                                    fill="#ab6160" />
                                                                <path id="Path_645" data-name="Path 645"
                                                                    d="M316.774,271.176a5.467,5.467,0,0,0-4.15-3.355,5.825,5.825,0,0,0-5.109,2.107,4.036,4.036,0,0,0-.211,4.282,5.34,5.34,0,0,0,6.12,2.9C315.794,276.437,317.669,273.467,316.774,271.176Z"
                                                                    transform="translate(-287.556 -219.807)"
                                                                    fill="#c48281" />
                                                                <path id="Path_646" data-name="Path 646"
                                                                    d="M337.526,226.089c-3.539.564-5.735,9.517-6.884,12.919-1.19,3.513.427,6.062,2.228,8.184h0a3.634,3.634,0,0,1,.827,2.812L330,263.841c10.165,7.547,27.208,3.892,31.832,1.427a32.653,32.653,0,0,0-2.986-14.009,23.073,23.073,0,0,1-2.254-9.064l-.174-5.077,1.227-8.764-8.827-2.107-7.779-.348A30.971,30.971,0,0,0,337.526,226.089Z"
                                                                    transform="translate(-298.539 -199.99)"
                                                                    fill="#007aff" />
                                                                <path id="Path_647" data-name="Path 647"
                                                                    d="M337.526,226.089c-3.539.564-5.735,9.517-6.884,12.919-1.19,3.513.427,6.062,2.228,8.184h0a3.634,3.634,0,0,1,.827,2.812L330,263.841c10.165,7.547,27.208,3.892,31.832,1.427a32.653,32.653,0,0,0-2.986-14.009,23.073,23.073,0,0,1-2.254-9.064l-.174-5.077,1.227-8.764-8.827-2.107-7.779-.348A30.971,30.971,0,0,0,337.526,226.089Z"
                                                                    transform="translate(-298.539 -199.99)" opacity="0.4" />
                                                                <g id="Group_1243" data-name="Group 1243"
                                                                    transform="translate(23.137 49.526)">
                                                                    <path id="Path_648" data-name="Path 648"
                                                                        d="M329.966,287.288h0V287.1h0l-6-11.181v-.095l-.026-.037v-.037l-.053-.074-.032-.032-.026-.037v-.079h0l-.09-.079h-.126l-.037-.026h-.042l-7.858-4.687h-.527a.163.163,0,0,0-.105.042l-.822.943h.026c-.068.053-.074.163.026.348l6,11.181a3.871,3.871,0,0,0,1.327,1.254l6.9,3.887c.353.2.569.205.6.058v.063h0l.827-.943a.106.106,0,0,0,0-.042.091.091,0,0,0,0-.037ZM314.4,271.53h0Zm7.9,4.461c.063.053.132.105.19.163h0a1.034,1.034,0,0,0-.174-.163Zm.432.421.084.09h0Zm6.41,11.845a.615.615,0,0,0-.074-.226.652.652,0,0,1,.074.205h0S329.134,288.225,329.139,288.257Z"
                                                                        transform="translate(-314.195 -270.74)"
                                                                        fill="#455a64" />
                                                                </g>
                                                                <path id="Path_649" data-name="Path 649"
                                                                    d="M337.92,291.946h0v-.184h0l-6-11.181-.916.906,6.009,11.181a.654.654,0,0,1,.074.205h0a.137.137,0,0,1-.026.126h0l.827-.943a.1.1,0,0,0,0-.042.091.091,0,0,0,0-.037Z"
                                                                    transform="translate(-299.012 -225.872)" fill="#fff"
                                                                    opacity="0.15" />
                                                                <path id="Path_650" data-name="Path 650"
                                                                    d="M323.883,275.854v-.063l-.026-.037v-.037l-.053-.074-.032-.032-.026-.037V275.5h0l-.09-.079h-.126l-.037-.026h-.042l-7.758-4.65h-.527a.163.163,0,0,0-.105.042l-.822.943q.058-.063.205-.032h0a1.463,1.463,0,0,1,.379.158l6.9,3.887a2.809,2.809,0,0,1,.395.258h0a4.427,4.427,0,0,1,.379.327h0a3.334,3.334,0,0,1,.327.348h0a2.059,2.059,0,0,1,.226.327l.827-.943Z"
                                                                    transform="translate(-291.079 -221.215)" fill="#fff"
                                                                    opacity="0.2" />
                                                                <path id="Path_651" data-name="Path 651"
                                                                    d="M358.886,233.318c-.98-1.68-2.265-2.97-4.213-3.118,0,0-3.16,1.643-3.16,9.1l.211,18.66h0c-.458.184-.916.379-1.369.569a55.824,55.824,0,0,1-12,3.629,11.855,11.855,0,0,0-7.574-2.57l1.016,1.9c-1.928,1.238-6.288-.99-6.288-.99-.321,2.718,1.443,5.541,3.782,6.968s24.638-.669,29.367-3.729c3.113-2.012,2.375-21.251,2.375-21.251a16.1,16.1,0,0,0-2.144-9.164Z"
                                                                    transform="translate(-296.394 -202.026)"
                                                                    fill="#c48281" />
                                                                <path id="Path_652" data-name="Path 652"
                                                                    d="M316.363,277a12.893,12.893,0,0,0-.253,2.228A8.158,8.158,0,0,0,318.259,284a2.423,2.423,0,0,0,.927.653,1.053,1.053,0,0,0,1.053-.169,1.617,1.617,0,0,0,.374-1.122,5.926,5.926,0,0,0-.1-1.806,2.5,2.5,0,0,0-.953-1.5,5.381,5.381,0,0,0-1.206-.527,2.95,2.95,0,0,1-1.053-.779Z"
                                                                    transform="translate(-291.964 -224.178)"
                                                                    fill="#c48281" />
                                                                <path id="Path_653" data-name="Path 653"
                                                                    d="M349.636,216.334v7.047a3.775,3.775,0,0,0-1.417,2.333,2.433,2.433,0,0,0,1.259,2.2,3.429,3.429,0,0,0,2.591.253,5.91,5.91,0,0,0,2.291-1.327,9.523,9.523,0,0,0,3.055-6.689V213Z"
                                                                    transform="translate(-307.162 -193.884)"
                                                                    fill="#c48281" />
                                                                <path id="Path_654" data-name="Path 654"
                                                                    d="M358.389,213c-.737,3.876-2.249,7.236-7.479,8.427v-5.119Z"
                                                                    transform="translate(-308.436 -193.884)"
                                                                    fill="#ab6160" />
                                                                <path id="Path_655" data-name="Path 655"
                                                                    d="M349.349,197.332a7.653,7.653,0,0,0,0,1.87,2.212,2.212,0,0,0,.669,1.222,1.4,1.4,0,0,0,1.275.321,2.438,2.438,0,0,1,4.682-1.348,3.044,3.044,0,0,1-.406,2.554,5.793,5.793,0,0,1-1.965,1.78,2.059,2.059,0,0,1-2.5-.864c-2.249,3.1-6.288,5.525-10.17,5.356a5.54,5.54,0,0,1-2.986-.916c-1.506-1.053-1.685-2.776-1.938-4.461a23.74,23.74,0,0,1-.216-6.436,12.586,12.586,0,0,1,3.75-7.9C341.112,194.841,347.085,196.953,349.349,197.332Z"
                                                                    transform="translate(-301.224 -182.293)"
                                                                    fill="#c48281" />
                                                                <path id="Path_656" data-name="Path 656"
                                                                    d="M344.018,204.5l-2.518,3.376,2.5.748Z"
                                                                    transform="translate(-303.982 -189.861)"
                                                                    fill="#ab6160" />
                                                                <path id="Path_657" data-name="Path 657"
                                                                    d="M348.838,215.18l-2.038.737a1.28,1.28,0,0,0,1.427.569,1.148,1.148,0,0,0,.611-1.306Z"
                                                                    transform="translate(-306.491 -194.916)"
                                                                    fill="#ab6160" />
                                                                <path id="Path_658" data-name="Path 658"
                                                                    d="M348.838,215.18l-2.038.737a1.28,1.28,0,0,0,1.427.569,1.148,1.148,0,0,0,.611-1.306Z"
                                                                    transform="translate(-306.491 -194.916)"
                                                                    opacity="0.3" />
                                                                <path id="Path_659" data-name="Path 659"
                                                                    d="M348.22,216.776a1.2,1.2,0,0,0,.679,0,1.053,1.053,0,0,0,.621-.964,1.174,1.174,0,0,0-1.3.959Z"
                                                                    transform="translate(-307.163 -195.211)"
                                                                    fill="#ab6160" />
                                                                <path id="Path_660" data-name="Path 660"
                                                                    d="M351.935,204.847a.732.732,0,1,1-.213-.521A.737.737,0,0,1,351.935,204.847Z"
                                                                    transform="translate(-308.223 -189.676)"
                                                                    fill="#263238" />
                                                                <path id="Path_661" data-name="Path 661"
                                                                    d="M340.2,204.333a.74.74,0,1,1-.743-.743A.743.743,0,0,1,340.2,204.333Z"
                                                                    transform="translate(-302.666 -189.43)"
                                                                    fill="#263238" />
                                                                <path id="Path_662" data-name="Path 662"
                                                                    d="M338.667,199.257l-1.638,1.122a1.053,1.053,0,0,1,.327-1.153A1.243,1.243,0,0,1,338.667,199.257Z"
                                                                    transform="translate(-301.839 -187.283)"
                                                                    fill="#263238" />
                                                                <path id="Path_663" data-name="Path 663"
                                                                    d="M352.16,199.142l1.627,1.138a1.053,1.053,0,0,0-.311-1.159A1.248,1.248,0,0,0,352.16,199.142Z"
                                                                    transform="translate(-309.028 -187.231)"
                                                                    fill="#263238" />
                                                            </g>
                                                        </g>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <label class="switch">
                                                        <input type="checkbox" name="service-delivery" value="delivery"
                                                            <?php echo esc_attr($service_delivery_checked); ?>>
                                                        <span class="slider round"></span>
                                                    </label>
                                                    <h3><?php esc_html_e('Delivery', 'restropress'); ?></h3>
                                                    <p class="service-offer-description">
                                                        Get orders delivered to your customers’ doorstep— fast and
                                                        effortless.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="row services-option_wrap">
                                <div class="col-lg-6">
                                    <div>
                                        <h4>Which services do you offer?</h4>
                                        <div class="default-service-type_wrap">
                                            <label for="f-option" class="l-radio">
                                                <input type="radio" id="f-option" name="default_service" tabindex="1"
                                                    value="pickup" <?php checked($default_service, 'pickup'); ?>>
                                                <span>Pickup</span>
                                            </label>
                                            <label for="s-option" class="l-radio">
                                                <input type="radio" id="s-option" name="default_service" tabindex="2"
                                                    value="delivery" <?php checked($default_service, 'delivery'); ?>>
                                                <span>Delivery <span class="light-gray-txt">(Default)</span></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <h4>Enable ASAP Option</h4>
                                        <div class="enable-asap-option_wrap">
                                            <input type="checkbox" name="enable_asap_option" value="1" <?php checked($enable_asap_option, '1'); ?>>
                                            <label for="enable_asap_option"
                                                data-content="Check this box if you want to add ASAP option on your time slot">
                                                Check this box if you want to add ASAP option on your time slot
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div>
                                        <h4>Store Time Format</h4>
                                        <div class="store-time_wrap">
                                            <p>Use 24 - hour format</p>
                                            <label class="switch">
                                                <input type="checkbox" name="store_time_format" value="24hrs" <?php checked($store_time_format, '24hrs'); ?>>
                                            <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <h4>Select restaurant open/close time</h4>
                                        <div class="open-close_time-wrap">
                                            <label class="col-form-label">Time</label>
                                            <div class="chosen-wrapper">                                               
                                                <select name="open_time" class="chosen-select rpress_timings">
                                                    <option value=""  <?php selected( $open_time, '' ); ?>>
                                                       Open Time
                                                    </option>
                                                    <?php 
                                                    $start = strtotime("00:00");
                                                    $end   = strtotime("23:30");

                                                    for ($time = $start; $time <= $end; $time = strtotime("+30 minutes", $time)) {
                                                        $formatted = date("H:i", $time); // 24hr format
                                                        ?>
                                                        <option value="<?php echo esc_attr($formatted); ?>" 
                                                            <?php selected( $open_time, $formatted ); ?>>
                                                            <?php echo esc_html($formatted); ?>
                                                        </option>
                                                        <?php
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <label class="col-form-label">to</label>
                                            <div class="chosen-wrapper">                                               
                                                <select name="close_time" class="chosen-select rpress_timings">
                                                    <option value=""  <?php selected( $close_time, '' ); ?>>
                                                       Close Time
                                                    </option>
                                                    <?php 
                                                    $start = strtotime("00:00");
                                                    $end   = strtotime("23:30");

                                                    for ($time = $start; $time <= $end; $time = strtotime("+30 minutes", $time)) {
                                                        $formatted = date("H:i", $time); // 24hr format
                                                        ?>
                                                        <option value="<?php echo esc_attr($formatted); ?>" 
                                                            <?php selected( $close_time, $formatted ); ?>>
                                                            <?php echo esc_html($formatted); ?>
                                                        </option>
                                                        <?php
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="d-flex service-offer-alert-description">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 30.095 30.103">
                                <g id="exclamation-mark-in-a-circle" transform="translate(-0.4 511.731)">
                                    <path id="Path_394" data-name="Path 394"
                                        d="M14.3-511.712c-.159.012-.653.076-1.089.141A15.106,15.106,0,0,0,2.9-505.006a15.386,15.386,0,0,0-2.355,6.123,17.318,17.318,0,0,0,0,4.415,15.112,15.112,0,0,0,6.576,10.344,15.385,15.385,0,0,0,6.123,2.355,17.31,17.31,0,0,0,4.415,0,14.609,14.609,0,0,0,4.5-1.431,14.9,14.9,0,0,0,6.77-6.77,14.612,14.612,0,0,0,1.431-4.5,17.317,17.317,0,0,0,0-4.415,15.111,15.111,0,0,0-6.576-10.344,15.381,15.381,0,0,0-6.064-2.343A22.266,22.266,0,0,0,14.3-511.712Zm1.919,5.175a2.612,2.612,0,0,1,1.554,1.825,13.808,13.808,0,0,1-.353,4.886,41.31,41.31,0,0,1-1.33,5.1.676.676,0,0,1-1.283,0,41.667,41.667,0,0,1-1.507-6.1,12.388,12.388,0,0,1-.177-3.785,2.542,2.542,0,0,1,1.913-2.008A2.778,2.778,0,0,1,16.219-506.537Zm.141,15.524a1.937,1.937,0,0,1,1.03,1.484,2.061,2.061,0,0,1-1.2,2.325,2.305,2.305,0,0,1-1.489,0,2.078,2.078,0,0,1-1.2-2.308,1.928,1.928,0,0,1,.571-1.148,1.686,1.686,0,0,1,1.46-.524A1.656,1.656,0,0,1,16.36-491.012Z"
                                        transform="translate(0 0)" fill="#4c4761" />
                                </g>
                            </svg><?php esc_html_e('Don\'t worry. You can change these settings any time', 'restropress'); ?>
                        </p>
                        </div>
                    </div>
                    <div class="tab">
                        <!-- tab2_content -->
                        <div class="welcome-step-2">
                            <!-- <h4><?php //esc_html_e('Location Info', 'restropress'); ?></h4> -->
                            <div class="service-offer-wrap offer-tab2">
                                <div class="col-lg-12">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="col-lg-6">
                                                    <label class="col-form-label">
                                                        <?php esc_html_e('Base Country', 'restropress'); ?>
                                                        <div class="tooltip">
                                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                                                xmlns="http://www.w3.org/2000/svg">
                                                                <path
                                                                    d="M6.36942 0.08599C4.74463 0.252007 3.26409 0.951454 2.11558 2.09724C-0.611444 4.81882 -0.611444 9.22779 2.11558 11.9494C3.22055 13.0516 4.56773 13.713 6.17891 13.9416C6.50278 13.9878 7.57236 13.9851 7.90711 13.9389C9.48563 13.7211 10.8709 13.0407 11.9596 11.9467C13.2224 10.6757 13.9273 9.05905 13.9953 7.26825C14.0661 5.33321 13.3367 3.47708 11.9568 2.09724C10.8872 1.02494 9.5319 0.347262 8.00237 0.12137C7.67034 0.072382 6.70417 0.0506096 6.36942 0.08599ZM7.37096 3.78462C7.8173 3.99419 7.90439 4.52489 7.54514 4.83243C7.39001 4.96579 7.21039 5.02022 6.97089 5.00389C6.57354 4.97668 6.29321 4.70724 6.29321 4.35071C6.29049 3.87716 6.89468 3.56145 7.37096 3.78462ZM7.43628 5.94012C7.51793 5.97006 7.61046 6.02993 7.65945 6.08708L7.74382 6.18234V8.02213C7.74382 10.1559 7.76831 9.97351 7.44989 10.1314C7.28659 10.213 7.24577 10.2212 7.03621 10.2212C6.76405 10.2184 6.5817 10.1504 6.43474 10.0007L6.3422 9.90547L6.33404 8.06023L6.32859 6.21772L6.39119 6.12246C6.55449 5.87752 7.03893 5.79315 7.43628 5.94012Z"
                                                                    fill="#2C3447" />
                                                            </svg>
                                                            <span class="tooltiptext mg-b-0 mt-3">Where does your store
                                                                operate from?</span>
                                                        </div>
                                                    </label>
                                                    <div class="chosen-wrapper global_select statetext"
                                                        data-js="custom-scroll">
                                                        <select name="country" class="chosen-select">
                                                            <?php
                                                            $counties = rpress_get_country_list();
                                                            foreach ($counties as $code => $country) {
                                                                echo '<option value="' . esc_attr($code) . '" ' . ($base_country == $code ? "selected" : "") . '>' . esc_html($country) . '</option>';
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <label class="col-form-label">
                                                        <?php esc_html_e('Currency', 'restropress'); ?>
                                                        <div class="tooltip">
                                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                                                xmlns="http://www.w3.org/2000/svg">
                                                                <path
                                                                    d="M6.36942 0.08599C4.74463 0.252007 3.26409 0.951454 2.11558 2.09724C-0.611444 4.81882 -0.611444 9.22779 2.11558 11.9494C3.22055 13.0516 4.56773 13.713 6.17891 13.9416C6.50278 13.9878 7.57236 13.9851 7.90711 13.9389C9.48563 13.7211 10.8709 13.0407 11.9596 11.9467C13.2224 10.6757 13.9273 9.05905 13.9953 7.26825C14.0661 5.33321 13.3367 3.47708 11.9568 2.09724C10.8872 1.02494 9.5319 0.347262 8.00237 0.12137C7.67034 0.072382 6.70417 0.0506096 6.36942 0.08599ZM7.37096 3.78462C7.8173 3.99419 7.90439 4.52489 7.54514 4.83243C7.39001 4.96579 7.21039 5.02022 6.97089 5.00389C6.57354 4.97668 6.29321 4.70724 6.29321 4.35071C6.29049 3.87716 6.89468 3.56145 7.37096 3.78462ZM7.43628 5.94012C7.51793 5.97006 7.61046 6.02993 7.65945 6.08708L7.74382 6.18234V8.02213C7.74382 10.1559 7.76831 9.97351 7.44989 10.1314C7.28659 10.213 7.24577 10.2212 7.03621 10.2212C6.76405 10.2184 6.5817 10.1504 6.43474 10.0007L6.3422 9.90547L6.33404 8.06023L6.32859 6.21772L6.39119 6.12246C6.55449 5.87752 7.03893 5.79315 7.43628 5.94012Z"
                                                                    fill="#2C3447" />
                                                            </svg>
                                                            <span class="tooltiptext mg-b-0 mt-3">
                                                                <?php esc_html_e('Medium of exchange used to buy goods and services.', 'restropress'); ?>
                                                            </span>
                                                        </div>
                                                    </label>
                                                    <div class="chosen-wrapper global_select statetext" data-js="custom-scroll">
                                                        <select name="currency" class="chosen-select">
                                                            <?php 
                                                                $currencies = rpress_get_currencies();
                                                                foreach ( $currencies as $code => $label ) {
                                                                    echo '<option value="' . esc_attr( $code ) . '" ' . selected($currency, $code, false) . '>' . esc_html( $label ) . '</option>';
                                                                }
                                                            ?>
                                                        </select>
                                                    </div>

                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center mb-3 find-state">
                                                <div class="col-lg-6">
                                                    <label class="col-form-label">
                                                        <?php esc_html_e('Base State / Province', 'restropress'); ?>
                                                        <div class="tooltip">
                                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                                                xmlns="http://www.w3.org/2000/svg">
                                                                <path
                                                                    d="M6.36942 0.08599C4.74463 0.252007 3.26409 0.951454 2.11558 2.09724C-0.611444 4.81882 -0.611444 9.22779 2.11558 11.9494C3.22055 13.0516 4.56773 13.713 6.17891 13.9416C6.50278 13.9878 7.57236 13.9851 7.90711 13.9389C9.48563 13.7211 10.8709 13.0407 11.9596 11.9467C13.2224 10.6757 13.9273 9.05905 13.9953 7.26825C14.0661 5.33321 13.3367 3.47708 11.9568 2.09724C10.8872 1.02494 9.5319 0.347262 8.00237 0.12137C7.67034 0.072382 6.70417 0.0506096 6.36942 0.08599ZM7.37096 3.78462C7.8173 3.99419 7.90439 4.52489 7.54514 4.83243C7.39001 4.96579 7.21039 5.02022 6.97089 5.00389C6.57354 4.97668 6.29321 4.70724 6.29321 4.35071C6.29049 3.87716 6.89468 3.56145 7.37096 3.78462ZM7.43628 5.94012C7.51793 5.97006 7.61046 6.02993 7.65945 6.08708L7.74382 6.18234V8.02213C7.74382 10.1559 7.76831 9.97351 7.44989 10.1314C7.28659 10.213 7.24577 10.2212 7.03621 10.2212C6.76405 10.2184 6.5817 10.1504 6.43474 10.0007L6.3422 9.90547L6.33404 8.06023L6.32859 6.21772L6.39119 6.12246C6.55449 5.87752 7.03893 5.79315 7.43628 5.94012Z"
                                                                    fill="#2C3447" />
                                                            </svg>
                                                            <span class="tooltiptext mg-b-0 mt-3">
                                                                <?php esc_html_e('What state / province does your store operate from?', 'restropress'); ?>
                                                            </span>
                                                        </div>
                                                    </label>
                                                    <div class="chosen-wrapper global_select statetext"
                                                        data-js="custom-scroll">
                                                        <select class="chosen-select" name="state">
                                                            <?php
                                                            $states = rpress_get_states_list();
                                                            foreach ($states as $code => $state) {
                                                                echo '<option value="' . esc_attr($code) . '" ' . ($base_state == $code ? "selected" : "") . '>' . esc_html($state) . '</option>';
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6 mg-b-0 mt-3">
                                                    <label class="col-form-label"
                                                        for="validationCustom01"><?php esc_html_e('Enable Notification', 'restropress'); ?></label>
                                                    <div class="enable-order-notification_wrap enable-asap-option_wrap">
                                                        <input type="checkbox" name="enable_order_notification" value="1" <?php checked($enable_order_notification, '1'); ?>>
                                                        <label for="enable-box" data-content="Enable Notification">
                                                            Enable order notification
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="col-lg-6">
                                                    <label class="col-form-label" for="validationCustom01">
                                                        <?php esc_html_e('Store Address', 'restropress'); ?>
                                                        <div class="tooltip">
                                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                                                xmlns="http://www.w3.org/2000/svg">
                                                                <path
                                                                    d="M6.36942 0.08599C4.74463 0.252007 3.26409 0.951454 2.11558 2.09724C-0.611444 4.81882 -0.611444 9.22779 2.11558 11.9494C3.22055 13.0516 4.56773 13.713 6.17891 13.9416C6.50278 13.9878 7.57236 13.9851 7.90711 13.9389C9.48563 13.7211 10.8709 13.0407 11.9596 11.9467C13.2224 10.6757 13.9273 9.05905 13.9953 7.26825C14.0661 5.33321 13.3367 3.47708 11.9568 2.09724C10.8872 1.02494 9.5319 0.347262 8.00237 0.12137C7.67034 0.072382 6.70417 0.0506096 6.36942 0.08599ZM7.37096 3.78462C7.8173 3.99419 7.90439 4.52489 7.54514 4.83243C7.39001 4.96579 7.21039 5.02022 6.97089 5.00389C6.57354 4.97668 6.29321 4.70724 6.29321 4.35071C6.29049 3.87716 6.89468 3.56145 7.37096 3.78462ZM7.43628 5.94012C7.51793 5.97006 7.61046 6.02993 7.65945 6.08708L7.74382 6.18234V8.02213C7.74382 10.1559 7.76831 9.97351 7.44989 10.1314C7.28659 10.213 7.24577 10.2212 7.03621 10.2212C6.76405 10.2184 6.5817 10.1504 6.43474 10.0007L6.3422 9.90547L6.33404 8.06023L6.32859 6.21772L6.39119 6.12246C6.55449 5.87752 7.03893 5.79315 7.43628 5.94012Z"
                                                                    fill="#2C3447" />
                                                            </svg>
                                                            <span class="tooltiptext mg-b-0 mt-3">Business location where
                                                                customers visit for goods or services.</span>
                                                        </div>
                                                    </label>
                                                    <textarea class="form-control"
                                                        placeholder="Enter your complete Store Address" rows="3"
                                                        name="address"><?php echo esc_html($store_address); ?></textarea>
                                                </div>
                                                <div class="col-lg-6">
                                                    <label class="col-form-label"
                                                        for="validationCustom01"><?php esc_html_e('Enable Printing Option', 'restropress'); ?></label>
                                                    <div class="enable-printing-invoice_wrap enable-asap-option_wrap">
                                                        <input type="checkbox" name="enable_printing" value="1" <?php checked($enable_printing, '1'); ?>>
                                                        <label for="enable-printing" data-content="Enable Printing Option">
                                                            Check this option to enable printing of invoice
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <p class="d-flex service-offer-alert-description">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 30.095 30.103">
                                <g id="exclamation-mark-in-a-circle" transform="translate(-0.4 511.731)">
                                    <path id="Path_394" data-name="Path 394"
                                        d="M14.3-511.712c-.159.012-.653.076-1.089.141A15.106,15.106,0,0,0,2.9-505.006a15.386,15.386,0,0,0-2.355,6.123,17.318,17.318,0,0,0,0,4.415,15.112,15.112,0,0,0,6.576,10.344,15.385,15.385,0,0,0,6.123,2.355,17.31,17.31,0,0,0,4.415,0,14.609,14.609,0,0,0,4.5-1.431,14.9,14.9,0,0,0,6.77-6.77,14.612,14.612,0,0,0,1.431-4.5,17.317,17.317,0,0,0,0-4.415,15.111,15.111,0,0,0-6.576-10.344,15.381,15.381,0,0,0-6.064-2.343A22.266,22.266,0,0,0,14.3-511.712Zm1.919,5.175a2.612,2.612,0,0,1,1.554,1.825,13.808,13.808,0,0,1-.353,4.886,41.31,41.31,0,0,1-1.33,5.1.676.676,0,0,1-1.283,0,41.667,41.667,0,0,1-1.507-6.1,12.388,12.388,0,0,1-.177-3.785,2.542,2.542,0,0,1,1.913-2.008A2.778,2.778,0,0,1,16.219-506.537Zm.141,15.524a1.937,1.937,0,0,1,1.03,1.484,2.061,2.061,0,0,1-1.2,2.325,2.305,2.305,0,0,1-1.489,0,2.078,2.078,0,0,1-1.2-2.308,1.928,1.928,0,0,1,.571-1.148,1.686,1.686,0,0,1,1.46-.524A1.656,1.656,0,0,1,16.36-491.012Z"
                                        transform="translate(0 0)" fill="#4c4761" />
                                </g>
                            </svg>Don't worry. You can change these settings any time.
                        </p>
                        </div>
                    </div>
                    <div class="tab">
                        <!-- tab3_content -->
                        <div class="welcome-step-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="col-lg-12">
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="d-flex">
                                                    <label class="col-form-label">
                                                        Choose a Food Item Template
                                                        <div class="tooltip">
                                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                                                xmlns="http://www.w3.org/2000/svg">
                                                                <path
                                                                    d="M6.36942 0.08599C4.74463 0.252007 3.26409 0.951454 2.11558 2.09724C-0.611444 4.81882 -0.611444 9.22779 2.11558 11.9494C3.22055 13.0516 4.56773 13.713 6.17891 13.9416C6.50278 13.9878 7.57236 13.9851 7.90711 13.9389C9.48563 13.7211 10.8709 13.0407 11.9596 11.9467C13.2224 10.6757 13.9273 9.05905 13.9953 7.26825C14.0661 5.33321 13.3367 3.47708 11.9568 2.09724C10.8872 1.02494 9.5319 0.347262 8.00237 0.12137C7.67034 0.072382 6.70417 0.0506096 6.36942 0.08599ZM7.37096 3.78462C7.8173 3.99419 7.90439 4.52489 7.54514 4.83243C7.39001 4.96579 7.21039 5.02022 6.97089 5.00389C6.57354 4.97668 6.29321 4.70724 6.29321 4.35071C6.29049 3.87716 6.89468 3.56145 7.37096 3.78462ZM7.43628 5.94012C7.51793 5.97006 7.61046 6.02993 7.65945 6.08708L7.74382 6.18234V8.02213C7.74382 10.1559 7.76831 9.97351 7.44989 10.1314C7.28659 10.213 7.24577 10.2212 7.03621 10.2212C6.76405 10.2184 6.5817 10.1504 6.43474 10.0007L6.3422 9.90547L6.33404 8.06023L6.32859 6.21772L6.39119 6.12246C6.55449 5.87752 7.03893 5.79315 7.43628 5.94012Z"
                                                                    fill="#2C3447"></path>
                                                            </svg>
                                                            <span class="tooltiptext mg-b-0 mt-3">
                                                                Please Choose a Food Item Template design.
                                                            </span>
                                                        </div>
                                                    </label>
                                                </div>
                                                <div class="col-12 pb-5">
                                                    <div class="grid-wrapper grid-col-auto">
                                                        <label for="radio-card-1" class="radio-card">
                                                            <input type="radio" name="template" id="radio-card-1" value="list" <?php checked($template, 'list'); ?> />
                                                            <div class="card-content-wrapper">
                                                                <span class="check-icon"></span>
                                                                <div class="card-content">
                                                                    <svg width="38" height="36" viewBox="0 0 38 36"
                                                                        fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path
                                                                            d="M2.14281 0.147015C1.62902 0.372584 1.10269 1.0117 1.01497 1.51296C0.964844 1.73853 0.952312 2.99169 0.977375 4.32004C1.01497 6.95167 1.04003 7.05193 1.87965 7.66597C2.20547 7.91661 2.39345 7.92914 4.96242 7.92914C7.98254 7.92914 8.0076 7.91661 8.64671 7.06446C8.89735 6.73864 8.90988 6.55066 8.90988 3.98168C8.90988 1.41271 8.89735 1.22473 8.64671 0.898911C8.02013 0.0592942 7.95748 0.0342307 5.1128 0.00916767C3.14534 -0.0158954 2.43104 0.00916767 2.14281 0.147015Z"
                                                                            fill="#007AFF" />
                                                                        <path
                                                                            d="M12.9824 3.9817V5.98676H25.0128H37.0431V3.9817V1.97664H25.0128H12.9824V3.9817Z"
                                                                            fill="#007AFF" />
                                                                        <path
                                                                            d="M2.14281 14.1824C1.62902 14.408 1.10269 15.0471 1.01497 15.5484C0.964844 15.774 0.952312 17.0271 0.977375 18.3555C1.01497 20.9871 1.04003 21.0874 1.87965 21.7014C2.20547 21.952 2.39345 21.9646 4.96242 21.9646C7.5314 21.9646 7.71938 21.952 8.0452 21.7014C8.89735 21.0623 8.90988 21.0372 8.90988 18.0171C8.90988 15.4481 8.89735 15.2602 8.64671 14.9343C8.02013 14.0947 7.95748 14.0697 5.1128 14.0446C3.14534 14.0195 2.43104 14.0446 2.14281 14.1824Z"
                                                                            fill="#007AFF" />
                                                                        <path
                                                                            d="M12.9824 18.017V20.0221H25.0128H37.0431V18.017V16.012H25.0128H12.9824V18.017Z"
                                                                            fill="#007AFF" />
                                                                        <path
                                                                            d="M2.14281 28.2179C1.62902 28.4434 1.10269 29.0826 1.01497 29.5838C0.964844 29.8094 0.952312 31.0626 0.977375 32.3909C1.01497 35.0225 1.04003 35.1228 1.87965 35.7368C2.20547 35.9875 2.39345 36 4.96242 36C7.5314 36 7.71938 35.9875 8.0452 35.7368C8.89735 35.0977 8.90988 35.0727 8.90988 32.0525C8.90988 29.4836 8.89735 29.2956 8.64671 28.9698C8.02013 28.1302 7.95748 28.1051 5.1128 28.08C3.14534 28.055 2.43104 28.08 2.14281 28.2179Z"
                                                                            fill="#007AFF" />
                                                                        <path
                                                                            d="M12.9824 32.0525V34.0575H25.0128H37.0431V32.0525V30.0474H25.0128H12.9824V32.0525Z"
                                                                            fill="#007AFF" />
                                                                    </svg>
                                                                    <h4>List</h4>
                                                                </div>
                                                            </div>
                                                        </label>
                                                        <label for="radio-card-2" class="radio-card">
                                                            <input type="radio" name="template" id="radio-card-2" value="grid" <?php checked($template, 'grid'); ?> />
                                                            <div class="card-content-wrapper">
                                                                <span class="check-icon"></span>
                                                                <div class="card-content">
                                                                    <svg width="44" height="44" viewBox="0 0 44 44"
                                                                        fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path
                                                                            d="M3.98542 0.134274C2.46496 0.46929 1.07335 1.5946 0.446271 3.01198C-0.0090088 4.01703 -0.017599 4.19743 0.00817154 10.4339L0.0339421 16.1979L0.265877 16.8336C0.824238 18.3626 2.01827 19.5395 3.53014 20.0291L4.15723 20.2353H10.1274H16.0976L16.7247 20.0291C18.2623 19.5223 19.5079 18.2767 20.0147 16.7391L20.2209 16.112V10.1418V4.17166L20.0147 3.54457C19.5251 2.0327 18.3482 0.838667 16.8192 0.280306L16.1835 0.0483704L10.3422 0.0311909C5.73782 0.0226002 4.38916 0.0397816 3.98542 0.134274ZM15.8399 3.6047C16.2522 3.79369 16.5185 4.06857 16.6989 4.48949C16.8965 4.97054 16.8965 15.3131 16.6989 15.7942C16.5185 16.2151 16.2522 16.49 15.8399 16.679C15.5134 16.825 15.1612 16.8422 10.1446 16.8422C5.50589 16.8422 4.74995 16.825 4.47506 16.7133C4.05415 16.5329 3.77926 16.2666 3.59028 15.8543C3.44424 15.5279 3.42706 15.1757 3.42706 10.159C3.42706 5.52031 3.44424 4.76438 3.55592 4.48949C3.73631 4.06857 4.0026 3.79369 4.41493 3.6047C4.74136 3.45867 5.08497 3.44149 10.1274 3.44149C15.1698 3.44149 15.5134 3.45867 15.8399 3.6047Z"
                                                                            fill="white" />
                                                                        <path
                                                                            d="M27.7378 0.142841C25.8393 0.546579 24.233 2.17012 23.8292 4.08573C23.6403 4.97052 23.6403 15.3131 23.8292 16.1979C24.233 18.1307 25.8479 19.7456 27.7807 20.1494C28.6655 20.3384 39.0081 20.3384 39.8929 20.1494C41.8257 19.7456 43.4406 18.1307 43.8444 16.1979C44.0333 15.3131 44.0333 4.97052 43.8444 4.08573C43.4406 2.15294 41.8257 0.537989 39.8929 0.134251C39.0339 -0.0461426 28.5796 -0.0461426 27.7378 0.142841ZM39.7039 3.69917C39.8843 3.81944 40.1334 4.06855 40.2623 4.24895L40.4942 4.58396V10.1418V15.6997L40.2623 16.0347C40.1334 16.2151 39.8843 16.4642 39.7039 16.5844L39.3861 16.7992H33.8368H28.279L27.9439 16.5673C27.7635 16.4384 27.5144 16.1893 27.3942 16.0089L27.1794 15.6911L27.1536 10.3394C27.1365 6.826 27.1622 4.86744 27.2224 4.65269C27.3254 4.24895 27.9096 3.64763 28.2875 3.53596C28.4422 3.48442 30.8646 3.45865 33.9742 3.46724L39.3861 3.48442L39.7039 3.69917Z"
                                                                            fill="white" />
                                                                        <path
                                                                            d="M3.98542 23.8431C2.46496 24.1782 1.07335 25.3035 0.446271 26.7208C-0.0090088 27.7259 -0.017599 27.9063 0.00817154 34.1428L0.0339421 39.9068L0.265877 40.5424C0.824238 42.0715 2.01827 43.2483 3.53014 43.738L4.15723 43.9442H10.1274H16.0976L16.7247 43.738C18.2623 43.2312 19.5079 41.9856 20.0147 40.448L20.2209 39.8209V33.8507V27.8805L20.0147 27.2534C19.5251 25.7416 18.3482 24.5475 16.8192 23.9892L16.1835 23.7572L10.3422 23.7401C5.73782 23.7315 4.38916 23.7486 3.98542 23.8431ZM15.8399 27.3136C16.2522 27.5026 16.5185 27.7774 16.6989 28.1984C16.8965 28.6794 16.8965 39.022 16.6989 39.503C16.5185 39.9239 16.2522 40.1988 15.8399 40.3878C15.5134 40.5339 15.1612 40.551 10.1446 40.551C5.50589 40.551 4.74995 40.5339 4.47506 40.4222C4.05415 40.2418 3.77926 39.9755 3.59028 39.5632C3.44424 39.2367 3.42706 38.8845 3.42706 33.8679C3.42706 29.2292 3.44424 28.4732 3.55592 28.1984C3.73631 27.7774 4.0026 27.5026 4.41493 27.3136C4.74136 27.1675 5.08497 27.1504 10.1274 27.1504C15.1698 27.1504 15.5134 27.1675 15.8399 27.3136Z"
                                                                            fill="white" />
                                                                        <path
                                                                            d="M27.7378 23.8517C25.8393 24.2555 24.233 25.879 23.8292 27.7946C23.6403 28.6794 23.6403 39.022 23.8292 39.9068C24.233 41.8396 25.8479 43.4545 27.7807 43.8583C28.6655 44.0472 39.0081 44.0472 39.8929 43.8583C41.8257 43.4545 43.4406 41.8396 43.8444 39.9068C44.0333 39.022 44.0333 28.6794 43.8444 27.7946C43.4406 25.8618 41.8257 24.2469 39.8929 23.8431C39.0339 23.6628 28.5796 23.6628 27.7378 23.8517ZM39.7039 27.4081C39.8843 27.5283 40.1334 27.7774 40.2623 27.9578L40.4942 28.2929V33.8507V39.4085L40.2623 39.7436C40.1334 39.924 39.8843 40.1731 39.7039 40.2933L39.3861 40.5081H33.8368H28.279L27.9439 40.2762C27.7635 40.1473 27.5144 39.8982 27.3942 39.7178L27.1794 39.4L27.1536 34.0483C27.1365 30.5349 27.1622 28.5763 27.2224 28.3616C27.3254 27.9578 27.9096 27.3565 28.2875 27.2449C28.4422 27.1933 30.8646 27.1675 33.9742 27.1761L39.3861 27.1933L39.7039 27.4081Z"
                                                                            fill="white" />
                                                                    </svg>

                                                                    <h4>Grid</h4>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="store-logo-wrap">
                                                    <h4>Set Store Logo</h4>
                                                    <div class="store-time_wrap">
                                                        <div class="file-upload">
                                                            <div class="file-upload-content">
                                                                <?php
                                                                if ( $site_icon_url ) {
                                                                    echo '<img class="file-upload-image" src="' . esc_url($site_icon_url) . '" alt="Site Icon" />';
                                                                } else {
                                                                    echo '<img class="file-upload-image" src="' . esc_url(RP_PLUGIN_URL . 'assets/images/group.png') . '" alt="Default Icon" />';
                                                                }
                                                                ?>
                                                            </div>
                                                            <div class="d-flex image-upload-wrap-box">
                                                                <div class="">
                                                                    <div class="image-upload-wrap">
                                                                        <input class="file-upload-input" name="store_logo" type='file'
                                                                            onchange="readURL(this);" accept="image/*" />
                                                                        <!-- <div class="drag-text">
                                                                                    <h3>Drag and drop a file or select add Image</h3>
                                                                                </div> -->
                                                                    </div>
                                                                    <!-- <div class="image-title-wrap">
                                                                                <button type="button" onclick="removeUpload()" class="remove-image">Remove <span class="image-title">Uploaded Image</span></button>
                                                                            </div>   -->
                                                                    <p>Recommended size 280x75</p>
                                                                </div>
                                                                <button class="file-upload-btn" type="button"
                                                                    onclick="$('.file-upload-input').trigger( 'click' )">Upload
                                                                    File</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- <h1>Initial setup succeeded</h1>
                                <p class="success_text">
                                    You have successfully set up restropress wizard
                                </p>
                                <svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 215.721 215.62">
                                    <g id="check-_4_" transform="translate(-0.234 511.526)">
                                        <path id="Path_782" data-name="Path 782" d="M98.888-511.3c-20.974,1.983-39.163,9.116-56.55,22.114-5.149,3.84-15.826,14.517-19.666,19.666C11.7-454.88,5.157-440.194,2.034-423.229a107.671,107.671,0,0,0,28.7,94.617,108.66,108.66,0,0,0,55.622,30.639,92.177,92.177,0,0,0,22.029,2.068,100.329,100.329,0,0,0,33.635-5.275,108.523,108.523,0,0,0,68.536-68.367,99.171,99.171,0,0,0,5.4-34.141,100.343,100.343,0,0,0-5.317-33.93,108.532,108.532,0,0,0-88.117-73.094A153.653,153.653,0,0,0,98.888-511.3Zm56.888,68.747a8.162,8.162,0,0,1,3.925,10.8c-1.139,2.532-58.956,66.046-60.982,66.974a9.251,9.251,0,0,1-7.343-.042c-2.152-1.1-34.141-33.761-34.9-35.661-2.7-6.457,3.418-13.125,10.213-11.184,1.73.464,3.292,1.857,15.066,13.8l13.167,13.251,26-28.655c15.826-17.429,26.587-28.908,27.431-29.33A9.644,9.644,0,0,1,155.776-442.557Z" fill="#53c755" />
                                    </g>
                                </svg>
                                <p class="finish_text">
                                    Click "Finish" to start using Restropress
                                
                                </p> -->
                        <p class="d-flex service-offer-alert-description">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 30.095 30.103">
                                <g id="exclamation-mark-in-a-circle" transform="translate(-0.4 511.731)">
                                    <path id="Path_394" data-name="Path 394"
                                        d="M14.3-511.712c-.159.012-.653.076-1.089.141A15.106,15.106,0,0,0,2.9-505.006a15.386,15.386,0,0,0-2.355,6.123,17.318,17.318,0,0,0,0,4.415,15.112,15.112,0,0,0,6.576,10.344,15.385,15.385,0,0,0,6.123,2.355,17.31,17.31,0,0,0,4.415,0,14.609,14.609,0,0,0,4.5-1.431,14.9,14.9,0,0,0,6.77-6.77,14.612,14.612,0,0,0,1.431-4.5,17.317,17.317,0,0,0,0-4.415,15.111,15.111,0,0,0-6.576-10.344,15.381,15.381,0,0,0-6.064-2.343A22.266,22.266,0,0,0,14.3-511.712Zm1.919,5.175a2.612,2.612,0,0,1,1.554,1.825,13.808,13.808,0,0,1-.353,4.886,41.31,41.31,0,0,1-1.33,5.1.676.676,0,0,1-1.283,0,41.667,41.667,0,0,1-1.507-6.1,12.388,12.388,0,0,1-.177-3.785,2.542,2.542,0,0,1,1.913-2.008A2.778,2.778,0,0,1,16.219-506.537Zm.141,15.524a1.937,1.937,0,0,1,1.03,1.484,2.061,2.061,0,0,1-1.2,2.325,2.305,2.305,0,0,1-1.489,0,2.078,2.078,0,0,1-1.2-2.308,1.928,1.928,0,0,1,.571-1.148,1.686,1.686,0,0,1,1.46-.524A1.656,1.656,0,0,1,16.36-491.012Z"
                                        transform="translate(0 0)" fill="#4c4761" />
                                </g>
                            </svg><?php esc_html_e('Don\'t worry. You can change these settings any time', 'restropress'); ?>
                        </p>
                        </div>
                    </div>
                    <div class="d-flex flex-row-reverse">
                        <div class="buttons">
                            <!-- <button class="button button-primary next-prev-btn" type="button" id="prevBtn"
                                nextTab="-1">Previous</button> -->
                            <button class="button button-primary next-prev-btn" type="button" id="nextBtn"
                                nextTab="1">Next</button>
                            <input type="submit" value="Finish" class="button button-primary finish-btn"
                                style="display:none;">
                        </div>
                        <div class="d-flex multiStep-white-wrap me-3">
                            <span class="multiStep-text">Step <span class="current-page-num">1</span> of 3</span>
                            <ul class="multiStep__circles">
                                <li class="step"></li>
                                <li class="step"></li>
                                <li class="step"></li>
                            </ul>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    if (!empty($redirect_after_submit)): ?>
        <script type="text/javascript">
            window.location.href = "<?php echo esc_url(admin_url('admin.php?page=rpress-settings')); ?>";
        </script>
    <?php endif;
}