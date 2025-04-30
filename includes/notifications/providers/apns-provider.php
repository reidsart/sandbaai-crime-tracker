<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_APNS_Provider {
    private $team_id;
    private $key_id;
    private $app_bundle_id;
    private $private_key_path;
    private $production_mode;
    private $jwt_token;
    private $jwt_expiry;

    public function __construct() {
        $this->team_id = get_option('sandcrime_apns_team_id');
        $this->key_id = get_option('sandcrime_apns_key_id');
        $this->app_bundle_id = get_option('sandcrime_apns_bundle_id');
        $this->private_key_path = get_option('sandcrime_apns_private_key_path');
        $this->production_mode = get_option('sandcrime_apns_production_mode', false);
    }

    public function send($device_token, $payload) {
        if (!$this->validate_configuration()) {
            throw new Exception('APNS configuration incomplete');
        }

        $url = $this->get_api_url($device_token);
        $headers = $this->get_request_headers();
        $message = $this->format_payload($payload);

        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($message),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $error_response = json_decode(wp_remote_retrieve_body($response), true);
            throw new Exception(
                'APNS delivery failed: ' . 
                ($error_response['reason'] ?? 'Unknown error')
            );
        }

        return true;
    }

    private function validate_configuration() {
        return $this->team_id && 
               $this->key_id && 
               $this->app_bundle_id && 
               $this->private_key_path &&
               file_exists($this->private_key_path);
    }

    private function get_api_url($device_token) {
        $base_url = $this->production_mode ? 
            'https://api.push.apple.com' : 
            'https://api.sandbox.push.apple.com';
        
        return sprintf(
            '%s/3/device/%s',
            $base_url,
            $device_token
        );
    }

    private function get_request_headers() {
        return array(
            'apns-topic' => $this->app_bundle_id,
            'authorization' => 'bearer ' . $this->get_jwt_token(),
            'apns-push-type' => 'alert',
            'apns-priority' => '10',
            'content-type' => 'application/json'
        );
    }

    private function format_payload($payload) {
        return array(
            'aps' => array(
                'alert' => array(
                    'title' => $payload['title'],
                    'body' => $payload['body']
                ),
                'sound' => 'default',
                'mutable-content' => 1,
                'category' => 'SANDCRIME_NOTIFICATION'
            ),
            'notificationId' => $payload['notification_id'],
            'data' => $payload['data']
        );
    }

    private function get_jwt_token() {
        if ($this->jwt_token && time() < $this->jwt_expiry) {
            return $this->jwt_token;
        }

        $private_key = openssl_pkey_get_private(
            'file://' . $this->private_key_path
        );

        if (!$private_key) {
            throw new Exception('Failed to load APNS private key');
        }

        $header = base64_encode(json_encode(array(
            'alg' => 'ES256',
            'kid' => $this->key_id
        )));

        $now = time();
        $payload = base64_encode(json_encode(array(
            'iss' => $this->team_id,
            'iat' => $now,
            'exp' => $now + 3600 // Token valid for 1 hour
        )));

        $signature_input = $header . '.' . $payload;
        openssl_sign(
            $signature_input,
            $signature,
            $private_key,
            OPENSSL_ALGO_SHA256
        );

        $this->jwt_token = $header . '.' . 
                          $payload . '.' . 
                          base64_encode($signature);
        $this->jwt_expiry = $now + 3300; // Expire token after 55 minutes

        return $this->jwt_token;
    }
}