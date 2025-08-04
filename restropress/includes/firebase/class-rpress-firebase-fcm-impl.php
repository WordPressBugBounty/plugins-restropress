<?php
/**
 * Firebase Cloud Messaging Implemention
 * @author Dibya <dibya.das@magnigeeks.com>
 */
class Rpress_Firebase_FCM_Impl
{
    /**
     * Summary of get_firebase_access_token
     * @param mixed $service_account_key
     * @return mixed
     */
    private function get_firebase_access_token($service_account_key)
{
    $cache_key = 'rpress_firebase_access_token_' . md5($service_account_key['client_email']);
    $cached_token = get_transient($cache_key);

    if ($cached_token) {
        return $cached_token;
    }

    $now = time();
    $payload = [
        'iss' => $service_account_key['client_email'],
        'sub' => $service_account_key['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    ];

    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

    $dataToSign = $base64UrlHeader . "." . $base64UrlPayload;
    $signature = '';
    openssl_sign($dataToSign, $signature, $service_account_key['private_key'], 'sha256');
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    $post_data = [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ];

    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'method' => 'POST',
        'body' => $post_data,
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
    ]);

    if (is_wp_error($response)) {
        error_log('Error fetching access token: ' . $response->get_error_message());
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $access_token = $data['access_token'] ?? null;

    if ($access_token) {
        // Cache the token for 59 minutes to avoid expiry issues
        set_transient($cache_key, $access_token,3500);
    }

    return $access_token;
}

    /**
     * *
     * @param mixed $service_account_key
     * @param mixed $payload
     * @return array
     */
    public function send_fcm_notification($service_account_key, $payload)
    {

        // Get the access token
        $access_token = $this->get_firebase_access_token($service_account_key);
        if (!$access_token) {
            return ['success' => false, 'message' => 'Failed to obtain access token'];
        }

        // FCM URL
        $fcm_url = 'https://fcm.googleapis.com/v1/projects/' . $service_account_key['project_id'] . '/messages:send';

        // Make the POST request
        $response = wp_remote_post($fcm_url, [
            'method' => 'POST',
            'body' => json_encode($payload),
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
        ]);

        // Handle response
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Error sending notification: ' . $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code === 200) {
            return [
                'success' => true,
                'response' => json_decode($response_body, true),
            ];
        }

        return [
            'success' => false,
            'message' => 'Error sending notification: ' . $response_body,
        ];
    }
    /**
     * Summary of subscribe_to_topic
     * @param mixed $service_account_key
     * @param mixed $device_token_ides
     * @param mixed $topic
     * @return array
     */
    public function subscribe_to_topic($service_account_key, array $device_token_ides, $topic)
    {
        // Load service account key

        // Get the access token
        $access_token = $this->get_firebase_access_token($service_account_key);
        if (!$access_token) {
            return ['success' => false, 'message' => 'Failed to obtain access token'];
        }

        // FCM Topic Subscription URL
        $url = "https://iid.googleapis.com/iid/v1:batchAdd";

        // Prepare the payload
        $payload = [
            'to' => '/topics/' . $topic,
            'registration_tokens' => $device_token_ides,
        ];

        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            "access_token_auth" => "true",
        ];

      

        // Make the POST request
        $response = wp_remote_post($url, [
            'method' => 'POST',
            'body' => json_encode($payload),
            'headers' => $headers,
        ]);

        // Handle response
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Error subscribing to topic: ' . $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code === 200) {
            return [
                'success' => true,
                'response' => json_decode($response_body, true),
            ];
        }

        return [
            'success' => false,
            'message' => 'Error subscribing to topic: ' . $response_body,
        ];
    }
    public function remove_device_from_topic($service_account_key, array $device_token_ides, $topic)
    {
        // Load service account key

        // Get the access token
        $access_token = $this->get_firebase_access_token($service_account_key);
        if (!$access_token) {
            return ['success' => false, 'message' => 'Failed to obtain access token'];
        }

        // FCM Topic Removal URL
        $url = "https://iid.googleapis.com/iid/v1:batchRemove";

        // Prepare the payload
        $payload = [
            'to' => '/topics/' . $topic,
            'registration_tokens' => $device_token_ides,
        ];

        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            "access_token_auth" => "true",
        ];

        // Make the POST request
        $response = wp_remote_post($url, [
            'method' => 'POST',
            'body' => json_encode($payload),
            'headers' => $headers,
        ]);

        // Handle response
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Error removing device from topic: ' . $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code === 200) {
            return [
                'success' => true,
                'response' => json_decode($response_body, true),
            ];
        }

        return [
            'success' => false,
            'message' => 'Error removing device from topic: ' . $response_body,
        ];
    }

    /**
     * Subscribe a single device to a topic
     *
     * @param array $service_account_key
     * @param string $device_id
     * @param string $topic
     * @return array
     */
    public function subscribe_single_device_to_topic($service_account_key, $device_id, $topic)
    {
        $access_token = $this->get_firebase_access_token($service_account_key);
        if (!$access_token) {
            return ['success' => false, 'message' => 'Failed to obtain access token'];
        }

        $url = 'https://iid.googleapis.com/iid/v1/' . $device_id . '/rel/topics/' . $topic;

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            "access_token_auth" => "true",
        ];

        $response = wp_remote_post($url, [
            'method' => 'POST',
            'headers' => $headers,
            'body' => '', // Empty body required
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Error subscribing single device to topic: ' . $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code === 200) {
            return [
                'success' => true,
                'response' => json_decode($response_body, true),
            ];
        }

        return [
            'success' => false,
            'message' => 'Error subscribing single device to topic: ' . $response_body,
        ];
    }


    /**
     * Forcefully fetch a new Firebase access token
     *
     * @param array $service_account_key
     * @return string|null
     */
    public function refresh_firebase_access_token($service_account_key)
    {
        return $this->get_firebase_access_token($service_account_key);
    }

}