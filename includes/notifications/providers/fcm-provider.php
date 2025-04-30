<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_FCM_Provider {
    private $api_key;
    private $api_url = 'https://fcm.googleapis.com/fcm/send';

    public function __construct() {
        $this->api_key = get_option('sandcrime_fcm_api_key');
    }

    public function send($device_token, $payload) {
        if (!$this->api_key) {
            throw new Exception('FCM API key not configured');
        }

        $message = array(
            'to' => $device_token,
            'notification' => array(
                'title' => $payload['title'],
                'body' => $payload['body'],
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default'
            ),
            'data' => array_merge($payload['data'] ?? array(), array(
                'notification_id' => $payload['notification_id']
            )),
            'priority' => $payload['priority'] === 'urgent' ? 'high' : 'normal'
        );

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Authorization' => 'key=' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($message)
        ));

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['success'])) {
            throw new Exception('FCM delivery failed: ' . 
                ($body['results'][0]['error'] ?? 'Unknown error'));
        }

        return true;
    }
}