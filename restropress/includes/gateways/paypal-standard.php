<?php

/**
 * PayPal Standard Gateway
 *
 * @package     RPRESS
 * @subpackage  Gateways
 * @copyright   Copyright (c) 2018, MagniGenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
// Exit if accessed directly
if (! defined('ABSPATH')) exit;
/**
 * PayPal Remove CC Form
 *
 * PayPal Standard does not need a CC form, so remove it.
 *
 * @access private
 * @since 1.0
 */
add_action('rpress_paypal_cc_form', '__return_false');
/**
 * Register the PayPal Standard gateway subsection
 *
 * @since  1.0
 * @param  array $gateway_sections  Current Gateway Tab subsections
 * @return array                    Gateway subsections with PayPal Standard
 */
function rpress_register_paypal_gateway_section($gateway_sections)
{
    $gateway_sections['paypal'] = __('PayPal Standard', 'restropress');
    return $gateway_sections;
}
add_filter('rpress_settings_sections_gateways', 'rpress_register_paypal_gateway_section', 1, 1);
/**
 * Registers the PayPal Standard settings for the PayPal Standard subsection
 *
 * @since  1.0
 * @param  array $gateway_settings  Gateway tab settings
 * @return array                    Gateway tab settings with the PayPal Standard settings
 */
function rpress_register_paypal_gateway_settings($gateway_settings)
{
    $paypal_settings = array(
        'paypal_settings' => array(
            'id'   => 'paypal_settings',
            'name' => '<strong>' . __('PayPal Standard Settings', 'restropress') . '</strong>',
            'type' => 'header',
        ),
        'paypal_connect_account' => array(
            'id'   => 'paypal_connect_account',
            'name' => __('PayPal Connect', 'restropress'),
            'desc' => __('Add your PayPal REST app credentials, then click Connect PayPal to securely pull your account email and merchant ID.', 'restropress'),
            'type' => 'paypal_connect',
        ),
        'paypal_rest_client_id' => array(
            'id'   => 'paypal_rest_client_id',
            'name' => __('PayPal REST Client ID', 'restropress'),
            'desc' => __('Used for one-click PayPal account connection.', 'restropress'),
            'type' => 'text',
            'size' => 'regular',
        ),
        'paypal_rest_client_secret' => array(
            'id'   => 'paypal_rest_client_secret',
            'name' => __('PayPal REST Client Secret', 'restropress'),
            'desc' => __('Used for one-click PayPal account connection.', 'restropress'),
            'type' => 'password',
            'size' => 'regular',
        ),
        'paypal_email' => array(
            'id'   => 'paypal_email',
            'name' => __('PayPal Merchant Email', 'restropress'),
            'desc' => __('Business merchant email used for checkout and display.', 'restropress'),
            'type' => 'text',
            'size' => 'regular',
        ),
        'paypal_simple_mode_note' => array(
            'id'   => 'paypal_simple_mode_note',
            'name' => __('Setup', 'restropress'),
            'type' => 'descriptive_text',
            'desc' => __('Use Connect PayPal for setup. Account email and merchant ID are saved automatically after authorization.', 'restropress'),
        ),
    );

    $paypal_settings            = apply_filters('rpress_paypal_settings', $paypal_settings);
    $gateway_settings['paypal'] = $paypal_settings;

    return $gateway_settings;
}
add_filter('rpress_settings_gateways', 'rpress_register_paypal_gateway_settings', 1, 1);
add_action('admin_post_rpress_paypal_connect', 'rpress_handle_paypal_connect_request');
add_action('admin_post', 'rpress_handle_paypal_connect_callback');
add_action('admin_post_rpress_paypal_connect_callback', 'rpress_handle_paypal_connect_callback');

/**
 * Get PayPal settings page URL.
 *
 * @since 3.2.6
 * @param array $args Additional URL args.
 * @return string
 */
function rpress_get_paypal_settings_page_url($args = array())
{
    $defaults = array(
        'page'    => 'rpress-settings',
        'tab'     => 'gateways',
        'section' => 'paypal',
    );
    $args = wp_parse_args($args, $defaults);
    return add_query_arg($args, admin_url('admin.php'));
}

/**
 * Get the transient key used for PayPal connect state.
 *
 * @since 3.2.6
 * @param int $user_id User ID.
 * @return string
 */
function rpress_get_paypal_connect_state_transient_key($user_id)
{
    return 'rpress_paypal_connect_state_' . absint($user_id);
}

/**
 * Get transient key used for PayPal connect mode.
 *
 * @since 3.2.6
 * @param int $user_id User ID.
 * @return string
 */
function rpress_get_paypal_connect_mode_transient_key($user_id)
{
    return 'rpress_paypal_connect_mode_' . absint($user_id);
}

/**
 * Get transient key used for PayPal connect ID token.
 *
 * @since 3.2.6
 * @param int $user_id User ID.
 * @return string
 */
function rpress_get_paypal_connect_id_token_transient_key($user_id)
{
    return 'rpress_paypal_connect_id_token_' . absint($user_id);
}

/**
 * Get transient key used for PayPal connect error details.
 *
 * @since 3.2.6
 * @param int $user_id User ID.
 * @return string
 */
function rpress_get_paypal_connect_error_transient_key($user_id)
{
    return 'rpress_paypal_connect_error_' . absint($user_id);
}

/**
 * Save PayPal connect error detail for next admin page load.
 *
 * @since 3.2.6
 * @param string $message Error detail message.
 * @return void
 */
function rpress_set_paypal_connect_error_detail($message)
{
    $message = is_string($message) ? trim(wp_strip_all_tags($message)) : '';
    if (empty($message)) {
        return;
    }

    set_transient(
        rpress_get_paypal_connect_error_transient_key(get_current_user_id()),
        $message,
        5 * MINUTE_IN_SECONDS
    );
}

/**
 * Retrieve and clear PayPal connect error detail.
 *
 * @since 3.2.6
 * @return string
 */
function rpress_pop_paypal_connect_error_detail()
{
    $key     = rpress_get_paypal_connect_error_transient_key(get_current_user_id());
    $message = get_transient($key);
    delete_transient($key);

    if (! is_string($message)) {
        return '';
    }

    return trim($message);
}

/**
 * Build readable error detail from a WP_Error object.
 *
 * @since 3.2.6
 * @param WP_Error $error Error object.
 * @return string
 */
function rpress_get_paypal_error_detail_from_wp_error($error)
{
    if (! $error instanceof WP_Error) {
        return '';
    }

    $data = $error->get_error_data();
    if (is_array($data)) {
        if (! empty($data['paypal_error_description'])) {
            $description = (string) $data['paypal_error_description'];
            if (! empty($data['paypal_error'])) {
                return $description . ' (' . (string) $data['paypal_error'] . ')';
            }
            return $description;
        }

        if (! empty($data['paypal_error'])) {
            return (string) $data['paypal_error'];
        }
    }

    return (string) $error->get_error_message();
}

/**
 * Get the PayPal OAuth API base URL.
 *
 * @since 3.2.6
 * @param string $mode Optional mode override: sandbox|live.
 * @return string
 */
function rpress_get_paypal_oauth_api_base_url($mode = '')
{
    if (empty($mode)) {
        $mode = rpress_is_test_mode() ? 'sandbox' : 'live';
    }

    if ('sandbox' === $mode) {
        return 'https://api-m.sandbox.paypal.com';
    }

    return 'https://api-m.paypal.com';
}

/**
 * Get the PayPal OAuth web base URL.
 *
 * @since 3.2.6
 * @param string $mode Optional mode override: sandbox|live.
 * @return string
 */
function rpress_get_paypal_oauth_web_base_url($mode = '')
{
    if (empty($mode)) {
        $mode = rpress_is_test_mode() ? 'sandbox' : 'live';
    }

    if ('sandbox' === $mode) {
        return 'https://www.sandbox.paypal.com';
    }

    return 'https://www.paypal.com';
}

/**
 * Validate PayPal client credentials against the given API base URL.
 *
 * @since 3.2.6
 * @param string $api_base      PayPal API base URL.
 * @param string $client_id     PayPal client ID.
 * @param string $client_secret PayPal client secret.
 * @return bool
 */
function rpress_validate_paypal_client_credentials($api_base, $client_id, $client_secret)
{
    $response = wp_remote_post(
        trailingslashit($api_base) . 'v1/oauth2/token',
        array(
            'timeout' => 25,
            'headers' => array(
                'Authorization'   => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Accept'          => 'application/json',
                'Accept-Language' => 'en_US',
                'Content-Type'    => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
            ),
        )
    );

    if (is_wp_error($response)) {
        return false;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body   = json_decode(wp_remote_retrieve_body($response), true);

    return (200 <= $status && 300 > $status && is_array($body) && ! empty($body['access_token']));
}

/**
 * Detect whether PayPal credentials belong to sandbox or live.
 *
 * @since 3.2.6
 * @param string $client_id     PayPal client ID.
 * @param string $client_secret PayPal client secret.
 * @return string
 */
function rpress_detect_paypal_credentials_mode($client_id, $client_secret)
{
    if (rpress_validate_paypal_client_credentials('https://api-m.sandbox.paypal.com', $client_id, $client_secret)) {
        return 'sandbox';
    }

    if (rpress_validate_paypal_client_credentials('https://api-m.paypal.com', $client_id, $client_secret)) {
        return 'live';
    }

    return 'unknown';
}

/**
 * Get token endpoints to try for PayPal OAuth code exchange.
 *
 * @since 3.2.6
 * @param string $api_base PayPal API base URL.
 * @return array
 */
function rpress_get_paypal_oauth_token_endpoints($api_base)
{
    $api_base = untrailingslashit($api_base);

    $endpoints = array(
        $api_base . '/v1/identity/openidconnect/tokenservice',
        $api_base . '/v1/oauth2/token',
    );

    return array_values(array_unique($endpoints));
}

/**
 * Extract PayPal OAuth error code and description from API response.
 *
 * @since 3.2.6
 * @param mixed $decoded Decoded API response body.
 * @return array
 */
function rpress_extract_paypal_oauth_error($decoded)
{
    $paypal_error      = '';
    $paypal_error_desc = '';

    if (is_array($decoded)) {
        if (! empty($decoded['error'])) {
            $paypal_error = sanitize_text_field($decoded['error']);
        } elseif (! empty($decoded['name'])) {
            $paypal_error = sanitize_text_field($decoded['name']);
        }

        if (! empty($decoded['error_description'])) {
            $paypal_error_desc = sanitize_text_field($decoded['error_description']);
        } elseif (! empty($decoded['message'])) {
            $paypal_error_desc = sanitize_text_field($decoded['message']);
        } elseif (
            ! empty($decoded['details']) &&
            is_array($decoded['details']) &&
            ! empty($decoded['details'][0]['description'])
        ) {
            $paypal_error_desc = sanitize_text_field($decoded['details'][0]['description']);
        }
    }

    return array(
        'paypal_error'             => $paypal_error,
        'paypal_error_description' => $paypal_error_desc,
    );
}

/**
 * Request access token for PayPal OAuth authorization code.
 *
 * @since 3.2.6
 * @param string $request_url        PayPal token endpoint URL.
 * @param string $authorization_code OAuth authorization code.
 * @param string $client_id          PayPal client ID.
 * @param string $client_secret      PayPal client secret.
 * @param bool   $use_basic_auth     Whether to use HTTP Basic auth.
 * @return array
 */
function rpress_request_paypal_oauth_token($request_url, $authorization_code, $client_id, $client_secret, $use_basic_auth = true)
{
    $headers = array(
        'Accept'          => 'application/json',
        'Accept-Language' => 'en_US',
        'Content-Type'    => 'application/x-www-form-urlencoded',
    );

    $body = array(
        'grant_type'   => 'authorization_code',
        'code'         => $authorization_code,
        'redirect_uri' => rpress_get_paypal_oauth_redirect_url(),
    );

    if ($use_basic_auth) {
        $headers['Authorization'] = 'Basic ' . base64_encode($client_id . ':' . $client_secret);
    } else {
        $body['client_id']     = $client_id;
        $body['client_secret'] = $client_secret;
    }

    $response = wp_remote_post(
        $request_url,
        array(
            'timeout' => 45,
            'headers' => $headers,
            'body'    => $body,
        )
    );

    if (is_wp_error($response)) {
        return array(
            'access_token'             => '',
            'id_token'                 => '',
            'response_code'            => 0,
            'paypal_error'             => 'request_failed',
            'paypal_error_description' => (string) $response->get_error_message(),
        );
    }

    $response_code = (int) wp_remote_retrieve_response_code($response);
    $decoded       = json_decode(wp_remote_retrieve_body($response), true);

    if (200 <= $response_code && 300 > $response_code && is_array($decoded) && ! empty($decoded['access_token'])) {
        return array(
            'access_token'             => sanitize_text_field((string) $decoded['access_token']),
            'id_token'                 => ! empty($decoded['id_token']) ? sanitize_text_field((string) $decoded['id_token']) : '',
            'response_code'            => $response_code,
            'paypal_error'             => '',
            'paypal_error_description' => '',
        );
    }

    $error_data = rpress_extract_paypal_oauth_error($decoded);

    return array(
        'access_token'             => '',
        'id_token'                 => '',
        'response_code'            => $response_code,
        'paypal_error'             => (string) $error_data['paypal_error'],
        'paypal_error_description' => (string) $error_data['paypal_error_description'],
    );
}

/**
 * Get the callback URL used by PayPal OAuth.
 *
 * @since 3.2.6
 * @return string
 */
function rpress_get_paypal_oauth_redirect_url()
{
    return admin_url('admin-post.php');
}

/**
 * Get PayPal OAuth scopes used for quick onboarding.
 *
 * @since 3.2.6
 * @return array
 */
function rpress_get_paypal_oauth_scopes()
{
    $scopes = array(
        'openid',
        'email',
    );
    return apply_filters('rpress_paypal_oauth_scopes', $scopes);
}

/**
 * Get the PayPal connect action URL.
 *
 * @since 3.2.6
 * @return string
 */
function rpress_get_paypal_connect_action_url()
{
    return wp_nonce_url(
        add_query_arg(
            'action',
            'rpress_paypal_connect',
            admin_url('admin-post.php')
        ),
        'rpress_paypal_connect'
    );
}

/**
 * Get a human-readable PayPal connect notice.
 *
 * @since 3.2.6
 * @param string $notice Notice key.
 * @return array
 */
function rpress_get_paypal_connect_notice($notice)
{
    $notices = array(
        'success' => array(
            'class'   => 'notice-success',
            'message' => __('PayPal account connected successfully.', 'restropress'),
        ),
        'missing_credentials' => array(
            'class'   => 'notice-error',
            'message' => __('Please add the PayPal REST Client ID and Client Secret before connecting.', 'restropress'),
        ),
        'access_denied' => array(
            'class'   => 'notice-warning',
            'message' => __('PayPal authorization was canceled.', 'restropress'),
        ),
        'missing_code' => array(
            'class'   => 'notice-error',
            'message' => __('PayPal did not return an authorization code. Please try again.', 'restropress'),
        ),
        'invalid_state' => array(
            'class'   => 'notice-error',
            'message' => __('PayPal connect session expired or is invalid. Please try connecting again.', 'restropress'),
        ),
        'token_failed' => array(
            'class'   => 'notice-error',
            'message' => __('Unable to complete PayPal token exchange. Check your client credentials and try again.', 'restropress'),
        ),
        'profile_failed' => array(
            'class'   => 'notice-error',
            'message' => __('Unable to retrieve PayPal account details after authorization.', 'restropress'),
        ),
        'save_failed' => array(
            'class'   => 'notice-error',
            'message' => __('PayPal account was authorized but account details could not be saved.', 'restropress'),
        ),
        'missing_email_scope' => array(
            'class'   => 'notice-warning',
            'message' => __('PayPal authorized successfully, but email was not returned. Enable Email scope in your PayPal app Log in with PayPal settings, then reconnect.', 'restropress'),
        ),
    );

    if (isset($notices[$notice])) {
        return $notices[$notice];
    }

    return array(
        'class'   => '',
        'message' => '',
    );
}

/**
 * Render the PayPal connect settings row.
 *
 * @since 3.2.6
 * @param array $args Field args.
 * @return void
 */
function rpress_paypal_connect_callback($args)
{
    $notice_key = '';
    if (! empty($_GET['rpress-paypal-connect'])) {
        $notice_key = sanitize_key(wp_unslash($_GET['rpress-paypal-connect']));
    }

    $notice           = rpress_get_paypal_connect_notice($notice_key);
    $connect_url      = rpress_get_paypal_connect_action_url();
    $client_id        = trim((string) rpress_get_option('paypal_rest_client_id', ''));
    $client_secret    = trim((string) rpress_get_option('paypal_rest_client_secret', ''));
    $paypal_email     = trim((string) rpress_get_option('paypal_email', ''));
    $paypal_id        = trim((string) rpress_get_option('paypal_id', ''));
    $connected_at     = trim((string) rpress_get_option('paypal_connected_at', ''));
    $connected_mode   = trim((string) rpress_get_option('paypal_connected_mode', ''));
    $is_connected     = (! empty($paypal_email) && ! empty($connected_at));
    $button_label     = $is_connected ? __('Reconnect PayPal', 'restropress') : __('Connect PayPal', 'restropress');
    $mode_label       = ('sandbox' === $connected_mode) ? __('Sandbox', 'restropress') : __('Live', 'restropress');
    $connected_pretty = '';
    $callback_url     = rpress_get_paypal_oauth_redirect_url();
    $error_detail     = rpress_pop_paypal_connect_error_detail();

    if (! empty($paypal_id) && preg_match('~identity/user/([^/?#]+)~', $paypal_id, $paypal_id_match)) {
        $paypal_id = sanitize_text_field($paypal_id_match[1]);
    }

    if (! empty($connected_at)) {
        $timestamp = strtotime($connected_at);
        if ($timestamp) {
            $connected_pretty = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
        }
    }

    if (! empty($notice['message'])) {
        echo '<div class="notice inline ' . esc_attr($notice['class']) . '"><p>' . esc_html($notice['message']) . '</p></div>';

        if (! empty($error_detail)) {
            echo '<p class="description"><strong>' . esc_html__('PayPal response:', 'restropress') . '</strong> ' . esc_html($error_detail) . '</p>';
        }
    }

    if ($is_connected || ! empty($paypal_email)) {
        $identity = ! empty($paypal_email) ? $paypal_email : __('Connected account', 'restropress');
        $details  = sprintf(
            /* translators: 1: PayPal email, 2: mode label, 3: connected date */
            __('Connected as %1$s in %2$s mode%3$s.', 'restropress'),
            '<strong>' . esc_html($identity) . '</strong>',
            esc_html($mode_label),
            $connected_pretty ? ' ' . sprintf(
                /* translators: %s: connected date */
                __('on %s', 'restropress'),
                esc_html($connected_pretty)
            ) : ''
        );

        echo '<p>' . wp_kses_post($details) . '</p>';

        if (! empty($paypal_id)) {
            echo '<p class="description">' . sprintf(
                /* translators: %s: merchant ID */
                esc_html__('Merchant ID: %s', 'restropress'),
                esc_html($paypal_id)
            ) . '</p>';
        }

    }

    if (empty($client_id) || empty($client_secret)) {
        echo '<p class="description">' . esc_html__('Enter your PayPal REST Client ID and Client Secret, then click Connect PayPal.', 'restropress') . '</p>';
    }

    echo '<p><a href="' . esc_url($connect_url) . '" class="button button-primary">' . esc_html($button_label) . '</a></p>';
    echo '<p class="description"><strong>' . esc_html__('Important:', 'restropress') . '</strong> ' .
        esc_html__('In your PayPal app, enable "Log in with PayPal", use Client ID and Secret from the same app, and set Return URL exactly to:', 'restropress') .
        ' <code>' . esc_html($callback_url) . '</code></p>';
    echo '<p class="description">' .
        esc_html__('If you want automatic account email fill, enable Email in Log in with PayPal -> Advanced Options.', 'restropress') .
        '</p>';
    echo '<p class="description">' . wp_kses_post($args['desc']) . '</p>';
}

/**
 * Start PayPal OAuth connect flow from admin settings.
 *
 * @since 3.2.6
 * @return void
 */
function rpress_handle_paypal_connect_request()
{
    if (! current_user_can('manage_shop_settings')) {
        wp_die(esc_html__('You do not have permission to manage payment gateway settings.', 'restropress'), esc_html__('Error', 'restropress'), array('response' => 403));
    }

    check_admin_referer('rpress_paypal_connect');

    $client_id     = trim((string) rpress_get_option('paypal_rest_client_id', ''));
    $client_secret = trim((string) rpress_get_option('paypal_rest_client_secret', ''));

    if (empty($client_id) || empty($client_secret)) {
        wp_safe_redirect(rpress_get_paypal_settings_page_url(array('rpress-paypal-connect' => 'missing_credentials')));
        exit;
    }

    $oauth_mode = rpress_detect_paypal_credentials_mode($client_id, $client_secret);
    if (! in_array($oauth_mode, array('sandbox', 'live'), true)) {
        rpress_set_paypal_connect_error_detail(__('Client Authentication failed. Verify the REST Client ID and Secret from the same PayPal app.', 'restropress'));
        wp_safe_redirect(rpress_get_paypal_settings_page_url(array('rpress-paypal-connect' => 'token_failed')));
        exit;
    }

    $state = wp_generate_password(32, false, false);
    set_transient(
        rpress_get_paypal_connect_state_transient_key(get_current_user_id()),
        $state,
        15 * MINUTE_IN_SECONDS
    );
    set_transient(
        rpress_get_paypal_connect_mode_transient_key(get_current_user_id()),
        $oauth_mode,
        15 * MINUTE_IN_SECONDS
    );

    $authorize_url = add_query_arg(
        array(
            'response_type' => 'code',
            'client_id'     => $client_id,
            'scope'         => implode(' ', rpress_get_paypal_oauth_scopes()),
            'prompt'        => 'login',
            'redirect_uri'  => rpress_get_paypal_oauth_redirect_url(),
            'state'         => $state,
        ),
        trailingslashit(rpress_get_paypal_oauth_web_base_url($oauth_mode)) . 'signin/authorize'
    );

    wp_redirect($authorize_url);
    exit;
}

/**
 * Handle PayPal OAuth callback and save account details.
 *
 * @since 3.2.6
 * @return void
 */
function rpress_handle_paypal_connect_callback()
{
    // Allow other no-action admin-post handlers to run untouched.
    if (empty($_GET['error']) && empty($_GET['state']) && empty($_GET['code'])) {
        return;
    }

    if (! current_user_can('manage_shop_settings')) {
        wp_die(esc_html__('You do not have permission to manage payment gateway settings.', 'restropress'), esc_html__('Error', 'restropress'), array('response' => 403));
    }

    if (! empty($_GET['error'])) {
        wp_safe_redirect(rpress_get_paypal_settings_page_url(array('rpress-paypal-connect' => 'access_denied')));
        exit;
    }

    $state = '';
    if (! empty($_GET['state'])) {
        $state = wp_unslash($_GET['state']);
        $state = is_string($state) ? trim($state) : '';
    }

    $authorization_code = '';
    if (! empty($_GET['code'])) {
        $authorization_code = wp_unslash($_GET['code']);
        $authorization_code = is_string($authorization_code) ? trim($authorization_code) : '';
    }

    $state_key    = rpress_get_paypal_connect_state_transient_key(get_current_user_id());
    $mode_key     = rpress_get_paypal_connect_mode_transient_key(get_current_user_id());
    $id_token_key = rpress_get_paypal_connect_id_token_transient_key(get_current_user_id());
    $saved_state  = (string) get_transient($state_key);
    $oauth_mode   = (string) get_transient($mode_key);

    if (empty($state) || empty($saved_state) || ! hash_equals($saved_state, $state)) {
        wp_safe_redirect(rpress_get_paypal_settings_page_url(array('rpress-paypal-connect' => 'invalid_state')));
        exit;
    }

    delete_transient($state_key);
    delete_transient($mode_key);
    delete_transient($id_token_key);

    if (! in_array($oauth_mode, array('sandbox', 'live'), true)) {
        $oauth_mode = rpress_is_test_mode() ? 'sandbox' : 'live';
    }

    if (empty($authorization_code)) {
        wp_safe_redirect(rpress_get_paypal_settings_page_url(array('rpress-paypal-connect' => 'missing_code')));
        exit;
    }

    $access_token = rpress_exchange_paypal_oauth_code($authorization_code, $oauth_mode);

    if (is_wp_error($access_token)) {
        rpress_set_paypal_connect_error_detail(rpress_get_paypal_error_detail_from_wp_error($access_token));
        wp_safe_redirect(rpress_get_paypal_settings_page_url(array('rpress-paypal-connect' => 'token_failed')));
        exit;
    }

    $account = rpress_get_paypal_oauth_account($access_token, $oauth_mode);

    if (is_wp_error($account)) {
        rpress_set_paypal_connect_error_detail(rpress_get_paypal_error_detail_from_wp_error($account));
        wp_safe_redirect(rpress_get_paypal_settings_page_url(array('rpress-paypal-connect' => 'profile_failed')));
        exit;
    }

    $saved = rpress_store_connected_paypal_account($account, $oauth_mode);
    delete_transient($id_token_key);

    if (is_wp_error($saved)) {
        $notice_key = 'save_failed';
        if ('rpress_paypal_missing_email_scope' === $saved->get_error_code()) {
            $notice_key = 'missing_email_scope';
        }

        wp_safe_redirect(rpress_get_paypal_settings_page_url(array('rpress-paypal-connect' => $notice_key)));
        exit;
    }

    wp_safe_redirect(rpress_get_paypal_settings_page_url(array('rpress-paypal-connect' => 'success')));
    exit;
}

/**
 * Exchange OAuth authorization code for access token.
 *
 * @since 3.2.6
 * @param string $authorization_code OAuth authorization code.
 * @param string $oauth_mode         Optional mode override: sandbox|live.
 * @return string|WP_Error
 */
function rpress_exchange_paypal_oauth_code($authorization_code, $oauth_mode = '')
{
    $client_id     = trim((string) rpress_get_option('paypal_rest_client_id', ''));
    $client_secret = trim((string) rpress_get_option('paypal_rest_client_secret', ''));

    if (empty($client_id) || empty($client_secret)) {
        return new WP_Error('rpress_paypal_missing_credentials', __('PayPal credentials are missing.', 'restropress'));
    }

    if (! in_array($oauth_mode, array('sandbox', 'live'), true)) {
        $oauth_mode = rpress_is_test_mode() ? 'sandbox' : 'live';
    }

    $request_urls = rpress_get_paypal_oauth_token_endpoints(rpress_get_paypal_oauth_api_base_url($oauth_mode));

    $last_response_code    = 0;
    $paypal_error_code     = '';
    $paypal_error_desc     = '';
    $client_auth_failed    = false;
    $auth_strategies       = array(true, false);

    foreach ($request_urls as $request_url) {
        foreach ($auth_strategies as $use_basic_auth) {
            $result = rpress_request_paypal_oauth_token(
                $request_url,
                $authorization_code,
                $client_id,
                $client_secret,
                $use_basic_auth
            );

            if (! empty($result['access_token'])) {
                $id_token_key = rpress_get_paypal_connect_id_token_transient_key(get_current_user_id());
                if (! empty($result['id_token'])) {
                    set_transient($id_token_key, (string) $result['id_token'], 15 * MINUTE_IN_SECONDS);
                } else {
                    delete_transient($id_token_key);
                }

                return (string) $result['access_token'];
            }

            if (! empty($result['response_code'])) {
                $last_response_code = (int) $result['response_code'];
            }

            if (! empty($result['paypal_error'])) {
                $paypal_error_code = sanitize_text_field((string) $result['paypal_error']);
            }

            if (! empty($result['paypal_error_description'])) {
                $paypal_error_desc = sanitize_text_field((string) $result['paypal_error_description']);
            }

            $combined_error_text = strtolower($paypal_error_code . ' ' . $paypal_error_desc);
            if (
                false !== strpos($combined_error_text, 'invalid_client') ||
                false !== strpos($combined_error_text, 'unauthorized_client') ||
                false !== strpos($combined_error_text, 'client authentication failed')
            ) {
                $client_auth_failed = true;
            }
        }
    }

    if ($client_auth_failed) {
        $current_mode  = $oauth_mode;
        $detected_mode = rpress_detect_paypal_credentials_mode($client_id, $client_secret);

        if ('unknown' !== $detected_mode && $detected_mode !== $current_mode) {
            $paypal_error_code = 'mode_mismatch';
            $paypal_error_desc = sprintf(
                /* translators: 1: credentials mode, 2: current RestroPress mode */
                __('PayPal credentials are for %1$s mode, but RestroPress is in %2$s mode. Switch mode or use matching credentials.', 'restropress'),
                ('sandbox' === $detected_mode) ? __('Sandbox', 'restropress') : __('Live', 'restropress'),
                ('sandbox' === $current_mode) ? __('Sandbox', 'restropress') : __('Live', 'restropress')
            );
        } elseif (empty($paypal_error_desc)) {
            $paypal_error_desc = __('Client Authentication failed. Verify the REST Client ID and Secret from the same PayPal app and mode.', 'restropress');
        }
    }

    return new WP_Error(
        'rpress_paypal_token_failed',
        __('PayPal token exchange failed.', 'restropress'),
        array(
            'paypal_error'             => $paypal_error_code,
            'paypal_error_description' => $paypal_error_desc,
            'response_code'            => $last_response_code,
        )
    );
}

/**
 * Extract email from PayPal profile response.
 *
 * @since 3.2.6
 * @param array $decoded Decoded profile response.
 * @return string
 */
function rpress_extract_paypal_profile_email($decoded, $oauth_mode = '')
{
    if (! is_array($decoded)) {
        return '';
    }

    $emails = array();

    if (! empty($decoded['email'])) {
        $primary_email = sanitize_email($decoded['email']);
        if (! empty($primary_email)) {
            $emails[] = $primary_email;
        }
    }

    if (! empty($decoded['emails']) && is_array($decoded['emails'])) {
        foreach ($decoded['emails'] as $email_row) {
            if (! empty($email_row['value'])) {
                $candidate = sanitize_email($email_row['value']);
                if (! empty($candidate)) {
                    $emails[] = $candidate;
                }
            }
        }
    }

    $emails = array_values(array_unique(array_filter($emails)));

    if ('sandbox' === $oauth_mode) {
        foreach ($emails as $candidate) {
            if (preg_match('/@business\.example\.com$/i', $candidate)) {
                return $candidate;
            }
        }
    }

    if (! empty($emails)) {
        return $emails[0];
    }

    return '';
}

/**
 * Extract merchant/payer ID from PayPal profile response.
 *
 * @since 3.2.6
 * @param array $decoded Decoded profile response.
 * @return string
 */
function rpress_extract_paypal_profile_payer_id($decoded)
{
    if (! is_array($decoded)) {
        return '';
    }

    foreach (array('payer_id', 'payerId', 'merchant_id') as $id_key) {
        if (! empty($decoded[$id_key])) {
            return sanitize_text_field((string) $decoded[$id_key]);
        }
    }

    foreach (array('user_id', 'sub') as $url_key) {
        if (empty($decoded[$url_key]) || ! is_string($decoded[$url_key])) {
            continue;
        }

        if (preg_match('~identity/user/([^/?#]+)~', $decoded[$url_key], $match)) {
            return sanitize_text_field($match[1]);
        }
    }

    return '';
}

/**
 * Extract account type from PayPal profile response.
 *
 * @since 3.2.6
 * @param array $decoded Decoded profile response.
 * @return string
 */
function rpress_extract_paypal_profile_account_type($decoded)
{
    if (! is_array($decoded)) {
        return '';
    }

    if (! empty($decoded['account_type']) && is_string($decoded['account_type'])) {
        return strtoupper(sanitize_text_field($decoded['account_type']));
    }

    if (! empty($decoded['accountType']) && is_string($decoded['accountType'])) {
        return strtoupper(sanitize_text_field($decoded['accountType']));
    }

    return '';
}

/**
 * Decode a base64url string.
 *
 * @since 3.2.6
 * @param string $value Base64url value.
 * @return string
 */
function rpress_base64url_decode($value)
{
    $value = is_string($value) ? trim($value) : '';
    if ('' === $value) {
        return '';
    }

    $value = strtr($value, '-_', '+/');
    $pad   = strlen($value) % 4;
    if ($pad > 0) {
        $value .= str_repeat('=', 4 - $pad);
    }

    $decoded = base64_decode($value, true);
    if (false === $decoded) {
        return '';
    }

    return (string) $decoded;
}

/**
 * Extract PayPal account details from an ID token.
 *
 * @since 3.2.6
 * @param string $id_token ID token.
 * @return array
 */
function rpress_get_paypal_account_from_id_token($id_token, $oauth_mode = '')
{
    if (! is_string($id_token) || '' === trim($id_token)) {
        return array(
            'email'    => '',
            'payer_id' => '',
        );
    }

    $parts = explode('.', trim($id_token));
    if (count($parts) < 2) {
        return array(
            'email'    => '',
            'payer_id' => '',
        );
    }

    $payload_json = rpress_base64url_decode($parts[1]);
    if ('' === $payload_json) {
        return array(
            'email'    => '',
            'payer_id' => '',
        );
    }

    $payload = json_decode($payload_json, true);
    if (! is_array($payload)) {
        return array(
            'email'    => '',
            'payer_id' => '',
        );
    }

    return array(
        'email'        => rpress_extract_paypal_profile_email($payload, $oauth_mode),
        'payer_id'     => rpress_extract_paypal_profile_payer_id($payload),
        'account_type' => rpress_extract_paypal_profile_account_type($payload),
    );
}

/**
 * Get PayPal profile endpoint URLs to try.
 *
 * @since 3.2.6
 * @param string $oauth_mode Optional mode override: sandbox|live.
 * @return array
 */
function rpress_get_paypal_profile_endpoints($oauth_mode = '')
{
    $base = untrailingslashit(rpress_get_paypal_oauth_api_base_url($oauth_mode));

    return array(
        $base . '/v1/identity/openidconnect/userinfo/?schema=openid',
        $base . '/v1/identity/openidconnect/userinfo?schema=openid',
        $base . '/v1/oauth2/token/userinfo?schema=openid',
        $base . '/v1/identity/oauth2/userinfo?schema=paypalv1.1',
    );
}

/**
 * Retrieve account details from PayPal user info endpoint.
 *
 * @since 3.2.6
 * @param string $access_token OAuth access token.
 * @param string $oauth_mode   Optional mode override: sandbox|live.
 * @return array|WP_Error
 */
function rpress_get_paypal_oauth_account($access_token, $oauth_mode = '')
{
    if (! in_array($oauth_mode, array('sandbox', 'live'), true)) {
        $oauth_mode = rpress_is_test_mode() ? 'sandbox' : 'live';
    }

    $profile_endpoints   = rpress_get_paypal_profile_endpoints($oauth_mode);
    $last_response_code  = 0;
    $paypal_error_code   = '';
    $paypal_error_detail = '';

    foreach ($profile_endpoints as $profile_url) {
        $response = wp_remote_get(
            $profile_url,
            array(
                'timeout' => 45,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json',
                ),
            )
        );

        if (is_wp_error($response)) {
            $paypal_error_detail = (string) $response->get_error_message();
            continue;
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        $decoded       = json_decode(wp_remote_retrieve_body($response), true);

        if (! empty($response_code)) {
            $last_response_code = $response_code;
        }

        if (200 <= $response_code && 300 > $response_code && is_array($decoded)) {
            $email        = rpress_extract_paypal_profile_email($decoded, $oauth_mode);
            $payer_id     = rpress_extract_paypal_profile_payer_id($decoded);
            $account_type = rpress_extract_paypal_profile_account_type($decoded);

            if (! empty($email) || ! empty($payer_id)) {
                return array(
                    'email'        => $email,
                    'payer_id'     => $payer_id,
                    'account_type' => $account_type,
                );
            }

            $paypal_error_code   = 'profile_empty';
            $paypal_error_detail = __('PayPal profile did not return an email or merchant ID.', 'restropress');
            continue;
        }

        $error_data = rpress_extract_paypal_oauth_error($decoded);

        if (! empty($error_data['paypal_error'])) {
            $paypal_error_code = (string) $error_data['paypal_error'];
        }

        if (! empty($error_data['paypal_error_description'])) {
            $paypal_error_detail = (string) $error_data['paypal_error_description'];
        }
    }

    $id_token = (string) get_transient(rpress_get_paypal_connect_id_token_transient_key(get_current_user_id()));
    if ('' !== $id_token) {
        $account_from_token = rpress_get_paypal_account_from_id_token($id_token, $oauth_mode);
        if (! empty($account_from_token['email']) || ! empty($account_from_token['payer_id'])) {
            return $account_from_token;
        }
    }

    return new WP_Error(
        'rpress_paypal_profile_failed',
        __('Failed to retrieve PayPal account profile.', 'restropress'),
        array(
            'paypal_error'             => $paypal_error_code,
            'paypal_error_description' => $paypal_error_detail,
            'response_code'            => $last_response_code,
        )
    );
}

/**
 * Store connected PayPal account details in plugin settings.
 *
 * @since 3.2.6
 * @param array  $account    Account values.
 * @param string $oauth_mode Optional mode override: sandbox|live.
 * @return true|WP_Error
 */
function rpress_store_connected_paypal_account($account, $oauth_mode = '')
{
    if (! in_array($oauth_mode, array('sandbox', 'live'), true)) {
        $oauth_mode = rpress_is_test_mode() ? 'sandbox' : 'live';
    }

    $settings = get_option('rpress_settings', array());
    if (! is_array($settings)) {
        $settings = array();
    }

    $existing_email = '';
    if (! empty($settings['paypal_email'])) {
        $existing_email = sanitize_email($settings['paypal_email']);
    }

    if (! empty($account['email'])) {
        $incoming_email = sanitize_email($account['email']);

        // In sandbox, keep an existing business email if OAuth returns a personal login email.
        if (
            'sandbox' === $oauth_mode &&
            ! empty($existing_email) &&
            preg_match('/@business\.example\.com$/i', $existing_email) &&
            ! empty($incoming_email) &&
            preg_match('/@personal\.example\.com$/i', $incoming_email)
        ) {
            $settings['paypal_email'] = $existing_email;
        } else {
            $settings['paypal_email'] = $incoming_email;
        }
    }

    if (! empty($account['payer_id'])) {
        $settings['paypal_id'] = sanitize_text_field($account['payer_id']);
    }

    if (! empty($account['account_type']) && is_string($account['account_type'])) {
        $settings['paypal_account_type'] = strtoupper(sanitize_text_field($account['account_type']));
    } else {
        unset($settings['paypal_account_type']);
    }

    if (empty($settings['paypal_email'])) {
        return new WP_Error('rpress_paypal_missing_email_scope', __('PayPal email is missing from the connected account.', 'restropress'));
    }

    $settings['paypal_connected_at']   = current_time('mysql');
    $settings['paypal_connected_mode'] = $oauth_mode;

    update_option('rpress_settings', $settings);

    return true;
}

/**
 * Get PayPal merchant identifier used in checkout redirect.
 *
 * @since 3.2.6
 * @return string
 */
function rpress_get_paypal_merchant_identifier()
{
    $merchant_email = sanitize_email((string) rpress_get_option('paypal_email', ''));
    if (! empty($merchant_email)) {
        return $merchant_email;
    }

    $merchant_id = trim((string) rpress_get_option('paypal_id', ''));
    if (! empty($merchant_id)) {
        return sanitize_text_field($merchant_id);
    }

    return '';
}

/**
 * Get the currency used for PayPal Standard checkout.
 *
 * PayPal Sandbox classic checkout returns shopping cart errors for INR in many
 * test account setups. In test mode, fallback to USD for checkout requests.
 *
 * @since 3.2.6
 * @param string $store_currency Optional store currency.
 * @return string
 */
function rpress_get_paypal_checkout_currency($store_currency = '')
{
    if (empty($store_currency)) {
        $store_currency = rpress_get_currency();
    }

    $store_currency = strtoupper(trim((string) $store_currency));

    if (rpress_is_test_mode() && 'INR' === $store_currency) {
        $fallback_currency = apply_filters('rpress_paypal_sandbox_fallback_currency', 'USD', $store_currency);
        $fallback_currency = strtoupper(trim((string) $fallback_currency));

        if (empty($fallback_currency)) {
            $fallback_currency = 'USD';
        }

        return $fallback_currency;
    }

    return $store_currency;
}

/**
 * Process PayPal Purchase
 *
 * @since 1.0
 * @param array   $purchase_data Purchase Data
 * @return void
 */
function rpress_process_paypal_purchase($purchase_data)
{
    if (! wp_verify_nonce($purchase_data['gateway_nonce'], 'rpress-gateway')) {
        wp_die(esc_html__('Nonce verification has failed', 'restropress'), esc_html__('Error', 'restropress'), array('response' => 403));
    }

    $merchant_email = strtolower(trim((string) rpress_get_option('paypal_email', '')));
    $buyer_email    = '';
    if (! empty($purchase_data['user_email'])) {
        $buyer_email = strtolower(sanitize_email($purchase_data['user_email']));
    }

    $merchant_identifier = rpress_get_paypal_merchant_identifier();
    $gateway_mode        = ! empty($purchase_data['post_data']['rpress-gateway']) ? $purchase_data['post_data']['rpress-gateway'] : 'paypal';
    $store_currency      = strtoupper(trim((string) rpress_get_currency()));
    $paypal_currency     = rpress_get_paypal_checkout_currency($store_currency);

    if (empty($merchant_identifier)) {
        rpress_set_error('paypal_missing_merchant', __('PayPal merchant account is not configured. Reconnect your PayPal account in gateway settings.', 'restropress'));
        rpress_send_back_to_checkout('?payment-mode=' . $gateway_mode);
        return;
    }

    if (! empty($merchant_email) && ! empty($buyer_email) && $merchant_email === $buyer_email) {
        rpress_set_error('paypal_same_account', __('Use a different PayPal buyer account. The connected PayPal merchant account cannot pay its own order.', 'restropress'));
        rpress_send_back_to_checkout('?payment-mode=' . $gateway_mode);
        return;
    }

    if ($store_currency !== $paypal_currency) {
        rpress_debug_log(
            sprintf(
                'PayPal sandbox currency fallback applied. Store currency: %1$s, checkout currency: %2$s.',
                $store_currency,
                $paypal_currency
            )
        );
    }

    // Collect payment data
    $payment_data = array(
        'price'         => $purchase_data['price'],
        'date'          => $purchase_data['date'],
        'user_email'    => $purchase_data['user_email'],
        'purchase_key'  => $purchase_data['purchase_key'],
        'currency'      => $paypal_currency,
        'fooditems'     => $purchase_data['fooditems'],
        'user_info'     => $purchase_data['user_info'],
        'cart_details'  => $purchase_data['cart_details'],
        'gateway'       => 'paypal',
        'status'        => ! empty($purchase_data['buy_now']) ? 'private' : 'pending'
    );
    // Record the pending payment
    $payment = rpress_insert_payment($payment_data);
    // Check payment
    if (! $payment) {
        // Record the error
        rpress_record_gateway_error(__('Payment Error', 'restropress'), sprintf(__('Payment creation failed before sending buyer to PayPal. Payment data: %s', 'restropress'), json_encode($payment_data)), $payment);
        // Problems? send back
        rpress_send_back_to_checkout('?payment-mode=' . $gateway_mode);
    } else {
        // Only send to PayPal if the pending payment is created successfully
        $listener_url = add_query_arg('rpress-listener', 'IPN', home_url('index.php'));
        // Set the session data to recover this payment in the event of abandonment or error.
        RPRESS()->session->set('rpress_resume_payment', $payment);
        // Get the success url
        $return_url = add_query_arg(array(
            'payment-confirmation' => 'paypal',
            'payment-id' => $payment
        ), get_permalink(rpress_get_option('success_page', false)));
        // Get the PayPal redirect uri
        $paypal_redirect = trailingslashit(rpress_get_paypal_redirect()) . '?';
        // Setup PayPal arguments
        $paypal_args = array(
            'business'      => $merchant_identifier,
            'cmd'           => '_xclick',
            'invoice'       => $purchase_data['purchase_key'],
            'no_note'       => '1',
            'currency_code' => $paypal_currency,
            'custom'        => $payment,
            'rm'            => '2',
            'return'        => $return_url,
            'cancel_return' => rpress_get_failed_transaction_uri('?payment-id=' . $payment),
            'notify_url'    => $listener_url,
            'item_name'     => get_bloginfo('name') . ' Order ID :' . $payment,
            'amount'        => rpress_sanitize_amount((string) $purchase_data['price']),
            'quantity'      => '1',
            'bn'            => 'RestroPress_SP',
        );

        $image_url = rpress_get_paypal_image_url();
        if (! empty($image_url)) {
            $paypal_args['image_url'] = $image_url;
        }

        $paypal_args = apply_filters('rpress_paypal_redirect_args', $paypal_args, $purchase_data);
        rpress_debug_log('PayPal arguments: ' . print_r($paypal_args, true));

        // Build query
        $paypal_redirect .= http_build_query($paypal_args);
        // Fix for some sites that encode the entities
        $paypal_redirect = str_replace('&amp;', '&', $paypal_redirect);
        // Redirect to PayPal
        wp_redirect($paypal_redirect);
        exit;
    }
}
add_action('rpress_gateway_paypal', 'rpress_process_paypal_purchase');
/**
 * Listens for a PayPal IPN requests and then sends to the processing function
 *
 * @since 1.0
 * @return void
 */
function rpress_listen_for_paypal_ipn()
{
    // Regular PayPal IPN

    if (isset($_REQUEST['rpress-listener']) && 'ipn' === strtolower($_REQUEST['rpress-listener'])) {
        rpress_debug_log('PayPal IPN endpoint loaded');
        /**
         * This is necessary to delay execution of PayPal PDT and to avoid a race condition causing the order status
         * updates to be triggered twice.
         *
         */
        $token = rpress_get_option('paypal_identity_token');
        if ($token) {
            sleep(8);
        }
        do_action('rpress_verify_paypal_ipn');
    }
}
add_action('init', 'rpress_listen_for_paypal_ipn');
/**
 * Process PayPal IPN
 *
 * @since 1.0
 * @return void
 */
function rpress_process_paypal_ipn()
{
    // Check the request method is POST
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
        return;
    }
    rpress_debug_log('rpress_process_paypal_ipn() running during PayPal IPN processing');
    // Set initial post data to empty string
    $post_data = '';
    // Start the encoded data collection with notification command
    $encoded_data = 'cmd=_notify-validate';
    // Get current arg separator
    $arg_separator = rpress_get_php_arg_separator_output();
    // Verify there is a post_data
    if ($post_data || strlen($post_data) > 0) {
        // Append the data
        $encoded_data .= $arg_separator . $post_data;
    } else {
        // Check if POST is empty
        if (empty($_POST)) {
            // Nothing to do
            return;
        } else {
            $data = rpress_sanitize_array($_POST);
            // Loop through each POST
            foreach ($data as $key => $value) {
                // Encode the value and append the data
                $encoded_data .= $arg_separator . "$key=" . urlencode($value);
            }
        }
    }
    // Convert collected post data to an array
    parse_str($encoded_data, $encoded_data_array);
    foreach ($encoded_data_array as $key => $value) {
        if (false !== strpos($key, 'amp;')) {
            $new_key = str_replace('&amp;', '&', $key);
            $new_key = str_replace('amp;', '&', $new_key);
            unset($encoded_data_array[$key]);
            $encoded_data_array[$new_key] = $value;
        }
    }
    /**
     * PayPal Web IPN Verification
     *
     * Allows filtering the IPN Verification data that PayPal passes back in via IPN with PayPal Standard
     *
     * @since 2.8.13
     *
     * @param array $data      The PayPal Web Accept Data
     */
    $encoded_data_array = apply_filters('rpress_process_paypal_ipn_data', $encoded_data_array);
    rpress_debug_log('encoded_data_array data array: ' . print_r($encoded_data_array, true));
    if (! rpress_get_option('disable_paypal_verification')) {
        // Validate the IPN
        $host = rpress_is_test_mode() ? 'sandbox.paypal.com' : 'www.paypal.com';
        $remote_post_vars = array(
            'method'      => 'POST',
            'timeout'     => 45,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array(
                'host'         => $host,
                'connection'   => 'close',
                'content-type' => 'application/x-www-form-urlencoded',
                'post'         => '/cgi-bin/webscr HTTP/1.1',
                'user-agent'   => 'RPRESS IPN Verification/' . RP_VERSION . '; ' . get_bloginfo('url')
            ),
            'sslverify'   => false,
            'body'        => $encoded_data_array
        );
        rpress_debug_log('Attempting to verify PayPal IPN. Data sent for verification: ' . print_r($remote_post_vars, true));
        // Get response
        $api_response = wp_remote_post(rpress_get_paypal_redirect(true, true), $remote_post_vars);
        if (is_wp_error($api_response)) {
            rpress_record_gateway_error(__('IPN Error', 'restropress'), sprintf(__('Invalid IPN verification response. IPN data: %s', 'restropress'), json_encode($api_response)));
            rpress_debug_log('Invalid IPN verification response. IPN data: ' . print_r($api_response, true));
            return; // Something went wrong
        }
        if (wp_remote_retrieve_body($api_response) !== 'VERIFIED') {
            rpress_record_gateway_error(__('IPN Error', 'restropress'), sprintf(__('Invalid IPN verification response. IPN data: %s', 'restropress'), json_encode($api_response)));
            rpress_debug_log('Invalid IPN verification response. IPN data: ' . print_r($api_response, true));
            return; // Response not okay
        }
        rpress_debug_log('IPN verified successfully');
    }
    // Check if $post_data_array has been populated
    if (! is_array($encoded_data_array) && ! empty($encoded_data_array)) {
        return;
    }
    $defaults = array(
        'txn_type'       => '',
        'payment_status' => ''
    );
    $encoded_data_array = wp_parse_args($encoded_data_array, $defaults);
    $payment_id = 0;
    if (! empty($encoded_data_array['parent_txn_id'])) {
        $payment_id = rpress_get_purchase_id_by_transaction_id($encoded_data_array['parent_txn_id']);
    } elseif (! empty($encoded_data_array['txn_id'])) {
        $payment_id = rpress_get_purchase_id_by_transaction_id($encoded_data_array['txn_id']);
    }
    if (empty($payment_id)) {
        $payment_id = ! empty($encoded_data_array['custom']) ? absint($encoded_data_array['custom']) : 0;
    }
    if (has_action('rpress_paypal_' . $encoded_data_array['txn_type'])) {
        // Allow PayPal IPN types to be processed separately
        do_action('rpress_paypal_' . $encoded_data_array['txn_type'], $encoded_data_array, $payment_id);
    } else {
        // Fallback to web accept just in case the txn_type isn't present
        do_action('rpress_paypal_web_accept', $encoded_data_array, $payment_id);
    }
    exit;
}
add_action('rpress_verify_paypal_ipn', 'rpress_process_paypal_ipn');
/**
 * Process web accept (one time) payment IPNs
 *
 * @since 1.0
 * @param array   $data IPN Data
 * @return void
 */
function rpress_process_paypal_web_accept_and_cart($data, $payment_id)
{
    /**
     * PayPal Web Accept Data
     *
     * Allows filtering the Web Accept data that PayPal passes back in via IPN with PayPal Standard
     *
     * @since 1.0
     *
     * @param array $data      The PayPal Web Accept Data
     * @param int  $payment_id The Payment ID associated with this IPN request
     */
    $data = apply_filters('rpress_paypal_web_accept_and_cart_data', $data, $payment_id);
    if ($data['txn_type'] != 'web_accept' && $data['txn_type'] != 'cart' && $data['payment_status'] != 'Refunded') {
        return;
    }
    if (empty($payment_id)) {
        return;
    }
    $payment = new RPRESS_Payment($payment_id);
    // Collect payment details
    $purchase_key   = isset($data['invoice']) ? $data['invoice'] : $data['item_number'];
    $paypal_amount  = $data['mc_gross'];
    $payment_status = strtolower($data['payment_status']);
    $currency_code  = strtolower($data['mc_currency']);
    $receiver_id    = isset($data['receiver_id']) ? sanitize_text_field($data['receiver_id']) : '';
    $receiver_email = isset($data['receiver_email']) ? sanitize_email($data['receiver_email']) : '';
    if ($payment->gateway != 'paypal') {
        return; // this isn't a PayPal standard IPN
    }
    // Verify payment recipient
    $expected_receiver_id    = trim((string) rpress_get_option('paypal_id', true));
    $expected_receiver_email = strtolower(trim((string) rpress_get_option('paypal_email', true)));
    $receiver_matches        = false;

    if (! empty($expected_receiver_id) && $receiver_id === $expected_receiver_id) {
        $receiver_matches = true;
    }

    if (! $receiver_matches && ! empty($expected_receiver_email) && ! empty($receiver_email) && strtolower($receiver_email) === $expected_receiver_email) {
        $receiver_matches = true;
    }

    if (! $receiver_matches) {
        rpress_record_gateway_error(__('IPN Error', 'restropress'), sprintf(__('Invalid business email in IPN response. IPN data: %s', 'restropress'), json_encode($data)), $payment_id);
        rpress_debug_log('Invalid business email in IPN response. IPN data: ' . print_r($data, true));
        rpress_update_payment_status($payment_id, 'failed');
        rpress_insert_payment_note($payment_id, __('Payment failed due to invalid PayPal business email.', 'restropress'));
        return;
    }
    // Verify payment currency
    if ($currency_code != strtolower($payment->currency)) {
        rpress_record_gateway_error(__('IPN Error', 'restropress'), sprintf(__('Invalid currency in IPN response. IPN data: %s', 'restropress'), json_encode($data)), $payment_id);
        rpress_debug_log('Invalid currency in IPN response. IPN data: ' . print_r($data, true));
        rpress_update_payment_status($payment_id, 'failed');
        rpress_insert_payment_note($payment_id, __('Payment failed due to invalid currency in PayPal IPN.', 'restropress'));
        return;
    }
    if (empty($payment->email)) {
        // This runs when a Buy Now purchase was made. It bypasses checkout so no personal info is collected until PayPal
        // Setup and store the customers's details
        $address = array();
        $address['line1']    = ! empty($data['address_street']) ? sanitize_text_field($data['address_street'])       : false;
        $address['city']     = ! empty($data['address_city']) ? sanitize_text_field($data['address_city'])         : false;
        $address['state']    = ! empty($data['address_state']) ? sanitize_text_field($data['address_state'])        : false;
        $address['country']  = ! empty($data['address_country_code']) ? sanitize_text_field($data['address_country_code']) : false;
        $address['zip']      = ! empty($data['address_zip']) ? sanitize_text_field($data['address_zip'])          : false;
        $payment->email      = sanitize_text_field($data['payer_email']);
        $payment->first_name = sanitize_text_field($data['first_name']);
        $payment->last_name  = sanitize_text_field($data['last_name']);
        $payment->address    = $address;
        if (empty($payment->customer_id)) {
            $customer = new RPRESS_Customer($payment->email);
            if (! $customer || $customer->id < 1) {
                $customer->create(array(
                    'email'   => $payment->email,
                    'name'    => $payment->first_name . ' ' . $payment->last_name,
                    'user_id' => $payment->user_id
                ));
            }
            $payment->customer_id = $customer->id;
        }
        $payment->save();
    }
    if (empty($customer)) {
        $customer = new RPRESS_Customer($payment->customer_id);
    }
    // Record the payer email on the RPRESS_Customer record if it is different than the email entered on checkout
    if (! empty($data['payer_email']) && ! in_array(strtolower($data['payer_email']), array_map('strtolower', $customer->emails))) {
        $customer->add_email(strtolower($data['payer_email']));
    }
    if ($payment_status == 'refunded' || $payment_status == 'reversed') {
        // Process a refund
        rpress_process_paypal_refund($data, $payment_id);
    } else {
        if (get_post_status($payment_id) == 'publish') {
            return; // Only complete payments once
        }
        // Retrieve the total purchase amount (before PayPal)
        $payment_amount = rpress_get_payment_amount($payment_id);
        if (number_format((float) $paypal_amount, 2) < number_format((float) $payment_amount, 2)) {
            // The prices don't match
            rpress_record_gateway_error(__('IPN Error', 'restropress'), sprintf(__('Invalid payment amount in IPN response. IPN data: %s', 'restropress'), json_encode($data)), $payment_id);
            rpress_debug_log('Invalid payment amount in IPN response. IPN data: ' . print_r($data, true));
            rpress_update_payment_status($payment_id, 'failed');
            rpress_insert_payment_note($payment_id, __('Payment failed due to invalid amount in PayPal IPN.', 'restropress'));
            return;
        }
        if ($purchase_key != rpress_get_payment_key($payment_id)) {
            // Purchase keys don't match
            rpress_debug_log('Invalid purchase key in IPN response. IPN data: ' . print_r($data, true));
            rpress_record_gateway_error(__('IPN Error', 'restropress'), sprintf(__('Invalid purchase key in IPN response. IPN data: %s', 'restropress'), json_encode($data)), $payment_id);
            rpress_update_payment_status($payment_id, 'failed');
            rpress_insert_payment_note($payment_id, __('Payment failed due to invalid purchase key in PayPal IPN.', 'restropress'));
            return;
        }
        if ('completed' == $payment_status || rpress_is_test_mode()) {
            rpress_insert_payment_note($payment_id, sprintf(__('PayPal Transaction ID: %s', 'restropress'), $data['txn_id']));
            rpress_set_payment_transaction_id($payment_id, $data['txn_id']);
            rpress_update_payment_status($payment_id, 'publish');
            $sucess_url = add_query_arg(array(
                'payment_key' => $purchase_key,
            ), get_permalink(rpress_get_option('success_page', false)));
            wp_redirect($sucess_url);
        } else if ('pending' == $payment_status && isset($data['pending_reason'])) {
            // Look for possible pending reasons, such as an echeck
            $note = '';
            switch (strtolower($data['pending_reason'])) {
                case 'echeck':
                    $note = __('Payment made via eCheck and will clear automatically in 5-8 days', 'restropress');
                    $payment->status = 'processing';
                    $payment->save();
                    break;
                case 'address':
                    $note = __('Payment requires a confirmed customer address and must be accepted manually through PayPal', 'restropress');
                    break;
                case 'intl':
                    $note = __('Payment must be accepted manually through PayPal due to international account regulations', 'restropress');
                    break;
                case 'multi-currency':
                    $note = __('Payment received in non-shop currency and must be accepted manually through PayPal', 'restropress');
                    break;
                case 'paymentreview':
                case 'regulatory_review':
                    $note = __('Payment is being reviewed by PayPal staff as high-risk or in possible violation of government regulations', 'restropress');
                    break;
                case 'unilateral':
                    $note = __('Payment was sent to non-confirmed or non-registered email address.', 'restropress');
                    break;
                case 'upgrade':
                    $note = __('PayPal account must be upgraded before this payment can be accepted', 'restropress');
                    break;
                case 'verify':
                    $note = __('PayPal account is not verified. Verify account in order to accept this payment', 'restropress');
                    break;
                case 'other':
                    $note = __('Payment is pending for unknown reasons. Contact PayPal support for assistance', 'restropress');
                    break;
            }
            if (! empty($note)) {
                rpress_debug_log('Payment not marked as completed because: ' . $note);
                rpress_insert_payment_note($payment_id, $note);
            }
        }
    }
}
add_action('rpress_paypal_web_accept', 'rpress_process_paypal_web_accept_and_cart', 10, 2);
/**
 * Process PayPal IPN Refunds
 *
 * @since 1.0
 * @param array   $data IPN Data
 * @return void
 */
function rpress_process_paypal_refund($data, $payment_id = 0)
{
    /**
     * PayPal Process Refund Data
     *
     * Allows filtering the Refund data that PayPal passes back in via IPN with PayPal Standard
     *
     * @since 1.0
     *
     * @param array $data      The PayPal Refund data
     * @param int  $payment_id The Payment ID associated with this IPN request
     */
    $data = apply_filters('rpress_process_paypal_refund_data', $data, $payment_id);
    // Collect payment details
    if (empty($payment_id)) {
        return;
    }
    if (get_post_status($payment_id) == 'refunded') {
        return; // Only refund payments once
    }
    $payment_amount = rpress_get_payment_amount($payment_id);
    $refund_amount  = $data['mc_gross'] * -1;
    if (number_format((float) $refund_amount, 2) < number_format((float) $payment_amount, 2)) {
        rpress_insert_payment_note($payment_id, sprintf(__('Partial PayPal refund processed: %s', 'restropress'), $data['parent_txn_id']));
        return; // This is a partial refund
    }
    rpress_insert_payment_note($payment_id, sprintf(__('PayPal Payment #%s Refunded for reason: %s', 'restropress'), $data['parent_txn_id'], $data['reason_code']));
    rpress_insert_payment_note($payment_id, sprintf(__('PayPal Refund Transaction ID: %s', 'restropress'), $data['txn_id']));
    rpress_update_payment_status($payment_id, 'refunded');
}
/**
 * Get PayPal Redirect
 *
 * @since 1.0
 * @param bool    $ssl_check Is SSL?
 * @param bool    $ipn       Is this an IPN verification check?
 * @return string
 */
function rpress_get_paypal_redirect($ssl_check = false, $ipn = false)
{
    $protocol = 'http://';
    if (is_ssl() || ! $ssl_check) {
        $protocol = 'https://';
    }
    // Check the current payment mode
    if (rpress_is_test_mode()) {
        // Test mode
        if ($ipn) {
            $paypal_uri = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
        } else {
            $paypal_uri = $protocol . 'www.sandbox.paypal.com/cgi-bin/webscr';
        }
    } else {
        // Live mode
        if ($ipn) {
            $paypal_uri = 'https://ipnpb.paypal.com/cgi-bin/webscr';
        } else {
            $paypal_uri = $protocol . 'www.paypal.com/cgi-bin/webscr';
        }
    }
    return apply_filters('rpress_paypal_uri', $paypal_uri, $ssl_check, $ipn);
}
/**
 * Get the image for the PayPal purchase page.
 *
 * @since 1.0
 * @return string
 */
function rpress_get_paypal_image_url()
{
    $image_url = trim(rpress_get_option('paypal_image_url', ''));
    return apply_filters('rpress_paypal_image_url', $image_url);
}
/**
 * Shows "Purchase Processing" message for PayPal payments are still pending on site return.
 *
 * This helps address the Race Condition, as detailed in issue #1839
 *
 * @since 1.0
 * @return string
 */
function rpress_paypal_success_page_content($content)
{
    if (! isset($_REQUEST['payment-id']) && ! rpress_get_purchase_session()) {
        return $content;
    }
    rpress_empty_cart();
    $payment_id = isset($_REQUEST['payment-id']) ? absint($_REQUEST['payment-id']) : false;
    if (! $payment_id) {
        $session    = rpress_get_purchase_session();
        $payment_id = rpress_get_purchase_id_by_key($session['purchase_key']);
    }
    $payment = new RPRESS_Payment($payment_id);
    if ($payment->ID > 0 && 'pending' == $payment->status) {
        // Payment is still pending so show processing indicator to fix the Race Condition, issue #
        ob_start();
        rpress_get_template_part('payment', 'processing');
        $content = ob_get_clean();
    }
    return $content;
}
add_filter('rpress_payment_confirm_paypal', 'rpress_paypal_success_page_content');
/**
 * Mark payment as complete on return from PayPal if a PayPal Identity Token is present.
 *
 * @since 1.0
 * @return void
 */
function rpress_paypal_process_pdt_on_return()
{
    if (! isset($_REQUEST['payment-id']) || ! isset($_REQUEST['tx'])) {
        return;
    }
    $token = rpress_get_option('paypal_identity_token');
    if (! rpress_is_success_page() || ! $token || ! rpress_is_gateway_active('paypal')) {
        return;
    }
    $payment_id = isset($_REQUEST['payment-id']) ? absint($_REQUEST['payment-id']) : false;
    if (empty($payment_id)) {
        return;
    }
    $purchase_session = rpress_get_purchase_session();
    $payment          = new RPRESS_Payment($payment_id);
    // If there is no purchase session, don't try and fire PDT.
    if (empty($purchase_session)) {
        return;
    }
    // // Do not fire a PDT verification if the purchase session does not match the payment-id PDT is asking to verify.
    if (! empty($purchase_session['purchase_key']) && $payment->key !== $purchase_session['purchase_key']) {
        return;
    }
    if ($token && ! empty($_REQUEST['tx']) && $payment->ID > 0) {
        // An identity token has been provided in settings so let's immediately verify the purchase
        $host = rpress_is_test_mode() ? 'sandbox.paypal.com' : 'www.paypal.com';
        $remote_post_vars = array(
            'method'      => 'POST',
            'timeout'     => 45,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array(
                // 'host'         => $host,
                // 'connection'   => 'close',
                'content-type' => 'application/x-www-form-urlencoded',
                // 'post'         => '/cgi-bin/webscr HTTP/1.1',
                // 'user-agent'   => 'RPRESS PDT Verification/' . RP_VERSION . '; ' . get_bloginfo( 'url' )
            ),
            'sslverify'   => false,
            'body'        => array(
                'tx'  => sanitize_text_field($_REQUEST['tx']),
                'at'  => $token,
                'cmd' => '_notify-synch',
            )
        );
        // Sanitize the data for debug logging.
        $debug_args               = $remote_post_vars;
        $debug_args['body']['at'] = str_pad(substr($debug_args['body']['at'], -6), strlen($debug_args['body']['at']), '*', STR_PAD_LEFT);
        rpress_debug_log('Attempting to verify PayPal payment with PDT. Args: ' . print_r($debug_args, true));
        rpress_debug_log('Sending PDT Verification request to ' . rpress_get_paypal_redirect());
        $request = wp_remote_post(rpress_get_paypal_redirect(), $remote_post_vars);
        if (! is_wp_error($request)) {
            $body = wp_remote_retrieve_body($request);
            // parse the data
            $lines = explode("\n", trim($body));
            $data  = array();

            if (strcmp($lines[0], "SUCCESS") == 0) {
                rpress_debug_log('SUCCESS PDT Verification request to ' . rpress_get_paypal_redirect());
                for ($i = 1; $i < count($lines); $i++) {
                    $parsed_line = explode("=", $lines[$i], 2);
                    $data[urldecode($parsed_line[0])] = urldecode($parsed_line[1]);
                }
                if (isset($data['mc_gross'])) {
                    $total = $data['mc_gross'];
                } else if (isset($data['payment_gross'])) {
                    $total = $data['payment_gross'];
                } else if (isset($_REQUEST['amt'])) {
                    $total = sanitize_text_field($_REQUEST['amt']);
                } else {
                    $total = null;
                }
                if (is_null($total)) {
                    rpress_debug_log('Attempt to verify PayPal payment with PDT failed due to payment total missing');
                    $payment->add_note(__('Payment could not be verified while validating PayPal PDT. Missing payment total fields.', 'restropress'));
                    $payment->status = 'pending';
                } elseif ((float) $total < (float) $payment->total) {
                    /**
                     * Here we account for payments that are less than the expected results only. There are times that
                     * PayPal will sometimes round and have $0.01 more than the amount. The goal here is to protect store owners
                     * from getting paid less than expected.
                     */
                    rpress_debug_log('Attempt to verify PayPal payment with PDT failed due to payment total discrepancy');
                    $payment->add_note(sprintf(__('Payment failed while validating PayPal PDT. Amount expected: %f. Amount Received: %f', 'restropress'), $payment->total, $total));
                    $payment->status = 'failed';
                } else {
                    // Verify the status
                    switch (strtolower($data['payment_status'])) {
                        case 'completed':
                            $payment->status = 'publish';
                            break;
                        case 'failed':
                            $payment->status = 'failed';
                            break;
                        default:
                            $payment->status = 'pending';
                            break;
                    }
                }
                $payment->transaction_id = sanitize_text_field($_REQUEST['tx']);
                $payment->save();
            } elseif (strcmp($lines[0], "FAIL") == 0) {
                rpress_debug_log('Attempt to verify PayPal payment with PDT failed due to PDT failure response: ' . print_r($body, true));
                $payment->add_note(__('Payment failed while validating PayPal PDT.', 'restropress'));
                $payment->status = 'failed';
                $payment->save();
            } else {
                rpress_debug_log('Attempt to verify PayPal payment with PDT met with an unexpected result: ' . print_r($body, true));
                $payment->add_note(__('PayPal PDT encountered an unexpected result, payment set to pending', 'restropress'));
                $payment->status = 'pending';
                $payment->save();
            }
        } else {
            rpress_debug_log('Attempt to verify PayPal payment with PDT failed. Request return: ' . print_r($request, true));
        }
    }
}
add_action('template_redirect', 'rpress_paypal_process_pdt_on_return');
/**
 * Given a Payment ID, extract the transaction ID
 *
 * @since  1.0
 * @param  string $payment_id       Payment ID
 * @return string                   Transaction ID
 */
function rpress_paypal_get_payment_transaction_id($payment_id)
{
    $transaction_id = '';
    $notes = rpress_get_payment_notes($payment_id);
    foreach ($notes as $note) {
        if (preg_match('/^PayPal Transaction ID: ([^\s]+)/', $note->comment_content, $match)) {
            $transaction_id = $match[1];
            continue;
        }
    }
    return apply_filters('rpress_paypal_set_payment_transaction_id', $transaction_id, $payment_id);
}
add_filter('rpress_get_payment_transaction_id-paypal', 'rpress_paypal_get_payment_transaction_id', 10, 1);
/**
 * Given a transaction ID, generate a link to the PayPal transaction ID details
 *
 * @since  1.0
 * @param  string $transaction_id The Transaction ID
 * @param  int    $payment_id     The payment ID for this transaction
 * @return string                 A link to the PayPal transaction details
 */
function rpress_paypal_link_transaction_id($transaction_id, $payment_id)
{
    $payment = new RPRESS_Payment($payment_id);
    $sandbox = 'test' == $payment->mode ? 'sandbox.' : '';
    $paypal_base_url = 'https://www.' . $sandbox . 'paypal.com/webscr?cmd=_history-details-from-hub&id=';
    $transaction_url = '<a href="' . esc_url($paypal_base_url . $transaction_id) . '" target="_blank">' . $transaction_id . '</a>';
    return apply_filters('rpress_paypal_link_payment_details_transaction_id', $transaction_url);
}
add_filter('rpress_payment_details_transaction_id-paypal', 'rpress_paypal_link_transaction_id', 10, 2);
/**
 * Shows checkbox to automatically refund payments made in PayPal.
 *
 * @since  1.0
 *
 * @param int $payment_id The current payment ID.
 * @return void
 */
function rpress_paypal_refund_admin_js($payment_id = 0)
{
    // If not the proper gateway, return early.
    if ('paypal' !== rpress_get_payment_gateway($payment_id)) {
        return;
    }
    // If our credentials are not set, return early.
    $key       = rpress_get_payment_meta($payment_id, '_rpress_payment_mode', true);
    $username  = rpress_get_option('paypal_' . $key . '_api_username');
    $password  = rpress_get_option('paypal_' . $key . '_api_password');
    $signature = rpress_get_option('paypal_' . $key . '_api_signature');
    if (empty($username) || empty($password) || empty($signature)) {
        return;
    }
    // Localize the refund checkbox label.
    $label = __('Refund Payment in PayPal', 'restropress');
?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('select[name=rpress-payment-status]').change(function() {
                if ('refunded' == $(this).val()) {
                    $(this).parent().parent().append('<input type="checkbox" id="rpress-paypal-refund" name="rpress-paypal-refund" value="1" style="margin-top:0">');
                    $(this).parent().parent().append('<label for="rpress-paypal-refund"><?php echo esc_html($label); ?></label>');
                } else {
                    $('#rpress-paypal-refund').remove();
                    $('label[for="rpress-paypal-refund"]').remove();
                }
            });
        });
    </script>
<?php
}
add_action('rpress_view_order_details_before', 'rpress_paypal_refund_admin_js', 100);
/**
 * Possibly refunds a payment made with PayPal Standard or PayPal Express.
 *
 * @since  1.0
 *
 * @param int $payment_id The current payment ID.
 * @return void
 */
function rpress_maybe_refund_paypal_purchase(RPRESS_Payment $payment)
{
    if (! current_user_can('edit_shop_payments', $payment->ID)) {
        return;
    }
    if (empty($_POST['rpress-paypal-refund'])) {
        return;
    }
    $processed = $payment->get_meta('_rpress_paypal_refunded', true);
    // If the status is not set to "refunded", return early.
    if ('publish' !== $payment->old_status && 'revoked' !== $payment->old_status) {
        return;
    }
    // If not PayPal/PayPal Express, return early.
    if ('paypal' !== $payment->gateway) {
        return;
    }
    // If the payment has already been refunded in the past, return early.
    if ($processed) {
        return;
    }
    // Process the refund in PayPal.
    rpress_refund_paypal_purchase($payment);
}
add_action('rpress_pre_refund_payment', 'rpress_maybe_refund_paypal_purchase', 999);
/**
 * Refunds a purchase made via PayPal.
 *
 * @since  1.0
 *
 * @param object|int $payment The payment ID or object to refund.
 * @return void
 */
function rpress_refund_paypal_purchase($payment)
{
    if (! $payment instanceof RPRESS_Payment && is_numeric($payment)) {
        $payment = new RPRESS_Payment($payment);
    }
    // Set PayPal API key credentials.
    $credentials = array(
        'api_endpoint'  => 'test' == $payment->mode ? 'https://api-3t.sandbox.paypal.com/nvp' : 'https://api-3t.paypal.com/nvp',
        'api_username'  => rpress_get_option('paypal_' . $payment->mode . '_api_username'),
        'api_password'  => rpress_get_option('paypal_' . $payment->mode . '_api_password'),
        'api_signature' => rpress_get_option('paypal_' . $payment->mode . '_api_signature')
    );
    $credentials = apply_filters('rpress_paypal_refund_api_credentials', $credentials, $payment);
    $body = array(
        'USER'             => $credentials['api_username'],
        'PWD'              => $credentials['api_password'],
        'SIGNATURE'     => $credentials['api_signature'],
        'VERSION'       => '124',
        'METHOD'        => 'RefundTransaction',
        'TRANSACTIONID' => $payment->transaction_id,
        'REFUNDTYPE'    => 'Full'
    );
    $body = apply_filters('rpress_paypal_refund_body_args', $body, $payment);
    // Prepare the headers of the refund request.
    $headers = array(
        'Content-Type'  => 'application/x-www-form-urlencoded',
        'Cache-Control' => 'no-cache'
    );
    $headers = apply_filters('rpress_paypal_refund_header_args', $headers, $payment);
    // Prepare args of the refund request.
    $args = array(
        'body'           => $body,
        'headers'     => $headers,
        'httpversion' => '1.1'
    );
    $args = apply_filters('rpress_paypal_refund_request_args', $args, $payment);
    $error_msg = '';
    $request   = wp_remote_post($credentials['api_endpoint'], $args);
    if (is_wp_error($request)) {
        $success   = false;
        $error_msg = $request->get_error_message();
    } else {
        $body    = wp_remote_retrieve_body($request);
        $code    = wp_remote_retrieve_response_code($request);
        $message = wp_remote_retrieve_response_message($request);
        if (is_string($body)) {
            wp_parse_str($body, $body);
        }
        if (empty($code) || 200 !== (int) $code) {
            $success = false;
        }
        if (empty($message) || 'OK' !== $message) {
            $success = false;
        }
        if (isset($body['ACK']) && 'success' === strtolower($body['ACK'])) {
            $success = true;
        } else {
            $success = false;
            if (isset($body['L_LONGMESSAGE0'])) {
                $error_msg = $body['L_LONGMESSAGE0'];
            } else {
                $error_msg = __('PayPal refund failed for unknown reason.', 'restropress');
            }
        }
    }
    if ($success) {
        // Prevents the PayPal Express one-time gateway from trying to process the refundl
        $payment->update_meta('_rpress_paypal_refunded', true);
        $payment->add_note(sprintf(__('PayPal refund transaction ID: %s', 'restropress'), $body['REFUNDTRANSACTIONID']));
    } else {
        $payment->add_note(sprintf(__('PayPal refund failed: %s', 'restropress'), $error_msg));
    }
    // Run hook letting people know the payment has been refunded successfully.
    do_action('rpress_paypal_refund_purchase', $payment);
}
