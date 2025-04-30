<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Push_Notification_Manager {
    private $vapid_public_key;
    private $vapid_private_key;
    private $webpush;

    public function __construct() {
        require_once SANDCRIME_PLUGIN_DIR . 'vendor/autoload.php';

        $this->vapid_public_key = get_option('sandcrime_vapid_public_key');
        $this->vapid_private_key = get_option('sandcrime_vapid_private_key');

        $auth = array(
            'VAPID' => array(
                'subject' => get_site_url(),
                'publicKey' => $this->vapid_public_key,
                'privateKey' => $this->vapid_private_key
            )
        );

        $this->webpush = new Minishlink\WebPush\WebPush($auth);

        add_action('init', array($this, 'register_hooks'));
    }

    public function register_hooks() {
        add_action('wp_ajax_save_push_subscription', array($this, 'handle_save_subscription'));
        add_action('wp_ajax_remove_push_subscription', array($this, 'handle_remove_subscription'));
        add_action('wp_ajax_validate_push_subscription', array($this, 'handle_validate_subscription'));
    }

    public function handle_save_subscription() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $subscription = json_decode(file_get_contents('php://input'), true);
        if (!isset($subscription['subscription'])) {
            wp_send_json_error(array('message' => 'Invalid subscription data'));
        }

        $result = $this->save_subscription($user_id, $subscription['subscription'], $subscription['device_info'] ?? array());
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to save subscription'));
        }
    }

    public function handle_remove_subscription() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $subscription = json_decode(file_get_contents('php://input'), true);
        if (!isset($subscription['subscription'])) {
            wp_send_json_error(array('message' => 'Invalid subscription data'));
        }

        $result = $this->remove_subscription($user_id, $subscription['subscription']);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to remove subscription'));
        }
    }

    public function handle_validate_subscription() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $subscription = json_decode(file_get_contents('php://input'), true);
        if (!isset($subscription['subscription'])) {
            wp_send_json_error(array('message' => 'Invalid subscription data'));
        }

        $is_valid = $this->validate_subscription($user_id, $subscription['subscription']);
        
        wp_send_json_success(array('valid' => $is_valid));
    }

    private function save_subscription($user_id, $subscription, $device_info) {
        global $wpdb;

        $endpoint = $subscription['endpoint'];
        $keys = $subscription['keys'];

        return $wpdb->replace(
            $wpdb->prefix . 'sandcrime_push_subscriptions',
            array(
                'user_id' => $user_id,
                'endpoint' => $endpoint,
                'public_key' => $keys['p256dh'],
                'auth_token' => $keys['auth'],
                'user_agent' => $device_info['userAgent'] ?? '',
                'platform' => $device_info['platform'] ?? '',
                'language' => $device_info['language'] ?? '',
                'last_used' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ),
            array(
                '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            )
        );
    }

    private function remove_subscription($user_id, $subscription) {
        global $wpdb;

        return $wpdb->delete(
            $wpdb->prefix . 'sandcrime_push_subscriptions',
            array(
                'user_id' => $user_id,
                'endpoint' => $subscription['endpoint']
            ),
            array('%d', '%s')
        );
    }

    private function validate_subscription($user_id, $subscription) {
        global $wpdb;

        $stored = $wpdb->get_row($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}sandcrime_push_subscriptions
            WHERE user_id = %d AND endpoint = %s
        ", $user_id, $subscription['endpoint']));

        if (!$stored) {
            return false;
        }

        // Verify keys match
        return $stored->public_key === $subscription['keys']['p256dh'] &&
               $stored->auth_token === $subscription['keys']['auth'];
    }

    public function send_notification($subscription, $payload) {
        try {
            $options = array(
                'TTL' => 86400, // 24 hours
                'urgency' => $payload['priority'] === 'urgent' ? 'high' : 'normal',
                'topic' => 'sandcrime-notification'
            );

            $auth = array(
                'VAPID' => array(
                    'subject' => get_site_url(),
                    'publicKey' => $this->vapid_public_key,
                    'privateKey' => $this->vapid_private_key
                )
            );

            $webPush = new Minishlink\WebPush\WebPush($auth);
            $report = $webPush->sendNotification(
                new Minishlink\WebPush\Subscription(
                    $subscription['endpoint'],
                    $subscription['keys']['p256dh'],
                    $subscription['keys']['auth']
                ),
                json_encode($payload),
                $options
            );

            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    throw new Exception($report->getReason());
                }
            }

            return true;
        } catch (Exception $e) {
            error_log('Push notification failed: ' . $e->getMessage());
            return false;
        }
    }

    public function send_notification_to_user($user_id, $payload) {
        global $wpdb;

        $subscriptions = $wpdb->get_results($wpdb->prepare("
            SELECT endpoint, public_key, auth_token
            FROM {$wpdb->prefix}sandcrime_push_subscriptions
            WHERE user_id = %d
            AND last_used > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $user_id));

        $success = true;
        foreach ($subscriptions as $sub) {
            $subscription = array(
                'endpoint' => $sub->endpoint,
                'keys' => array(
                    'p256dh' => $sub->public_key,
                    'auth' => $sub->auth_token
                )
            );

            if (!$this->send_notification($subscription, $payload)) {
                $success = false;
            }
        }

        return $success;
    }

    public function cleanup_old_subscriptions() {
        global $wpdb;

        // Remove subscriptions not used in the last 60 days
        return $wpdb->query("
            DELETE FROM {$wpdb->prefix}sandcrime_push_subscriptions
            WHERE last_used < DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");
    }

    public static function generate_vapid_keys() {
        if (!function_exists('openssl_pkey_new')) {
            throw new Exception('OpenSSL extension required');
        }

        $res = openssl_pkey_new(array(
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC
        ));

        if (!$res) {
            throw new Exception('Failed to generate VAPID keys');
        }

        openssl_pkey_export($res, $private_key);
        $details = openssl_pkey_get_details($res);

        return array(
            'publicKey' => base64_encode($details['key']),
            'privateKey' => base64_encode($private_key)
        );
    }
}

// Initialize the push notification manager
new SandCrime_Push_Notification_Manager();