<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Push_Notification_Manager {
    private $webpush;
    
    public function __construct() {
        $this->init_webpush();
        add_action('wp_ajax_update_push_subscription', array($this, 'handle_subscription_update'));
        add_action('wp_ajax_remove_push_subscription', array($this, 'handle_subscription_removal'));
    }

    private function init_webpush() {
        require_once SANDCRIME_PLUGIN_DIR . 'vendor/autoload.php';

        $auth = array(
            'VAPID' => array(
                'subject' => home_url('/'),
                'publicKey' => get_option('sandcrime_vapid_public_key'),
                'privateKey' => get_option('sandcrime_vapid_private_key')
            )
        );

        $this->webpush = new Minishlink\WebPush\WebPush($auth);
    }

    public function send_notification_to_user($user_id, $payload) {
        global $wpdb;

        $subscriptions = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}sandcrime_push_subscriptions
            WHERE user_id = %d
        ", $user_id));

        $results = array();
        foreach ($subscriptions as $subscription) {
            try {
                $subscription_data = array(
                    'endpoint' => $subscription->endpoint,
                    'keys' => array(
                        'p256dh' => $subscription->public_key,
                        'auth' => $subscription->auth_token
                    )
                );

                $this->webpush->sendNotification(
                    Minishlink\WebPush\Subscription::create($subscription_data),
                    json_encode($payload)
                );

                $results[] = array(
                    'subscription_id' => $subscription->id,
                    'status' => 'success'
                );
            } catch (Exception $e) {
                $results[] = array(
                    'subscription_id' => $subscription->id,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                );

                if ($this->should_remove_subscription($e)) {
                    $this->remove_subscription($subscription->id);
                }
            }
        }

        $this->webpush->flush();
        return $results;
    }

    public function handle_subscription_update() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not authenticated');
        }

        $subscription_data = json_decode(file_get_contents('php://input'), true);
        if (!$subscription_data) {
            wp_send_json_error('Invalid subscription data');
        }

        $result = $this->update_subscription($user_id, $subscription_data);
        if ($result) {
            wp_send_json_success(array('subscription_id' => $result));
        } else {
            wp_send_json_error('Failed to update subscription');
        }
    }

    public function handle_subscription_removal() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
        if (!$subscription_id) {
            wp_send_json_error('Invalid subscription ID');
        }

        if ($this->remove_subscription($subscription_id)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to remove subscription');
        }
    }

    private function update_subscription($user_id, $subscription_data) {
        global $wpdb;

        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}sandcrime_push_subscriptions
            WHERE endpoint = %s
        ", $subscription_data['endpoint']));

        if ($existing) {
            $updated = $wpdb->update(
                $wpdb->prefix . 'sandcrime_push_subscriptions',
                array(
                    'public_key' => $subscription_data['keys']['p256dh'],
                    'auth_token' => $subscription_data['keys']['auth'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'last_used' => current_time('mysql')
                ),
                array('id' => $existing->id)
            );

            return $updated ? $existing->id : false;
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'sandcrime_push_subscriptions',
            array(
                'user_id' => $user_id,
                'endpoint' => $subscription_data['endpoint'],
                'public_key' => $subscription_data['keys']['p256dh'],
                'auth_token' => $subscription_data['keys']['auth'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'platform' => $this->detect_platform($_SERVER['HTTP_USER_AGENT']),
                'language' => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? 
                    substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : 'en',
                'last_used' => current_time('mysql'),
                'created_at' => current_time('mysql')
            )
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    private function remove_subscription($subscription_id) {
        global $wpdb;

        return $wpdb->delete(
            $wpdb->prefix . 'sandcrime_push_subscriptions',
            array('id' => $subscription_id)
        );
    }

    private function should_remove_subscription($exception) {
        return $exception instanceof \Minishlink\WebPush\ExpiredException ||
               $exception instanceof \Minishlink\WebPush\InvalidSubscriptionException;
    }

    private function detect_platform($user_agent) {
        $platforms = array(
            'Windows' => 'windows',
            'Android' => 'android',
            'iPhone' => 'ios',
            'iPad' => 'ios',
            'Macintosh' => 'macos',
            'Linux' => 'linux'
        );

        foreach ($platforms as $pattern => $platform) {
            if (stripos($user_agent, $pattern) !== false) {
                return $platform;
            }
        }

        return 'unknown';
    }

    public function cleanup_old_subscriptions() {
        global $wpdb;

        // Remove subscriptions not used in the last 30 days
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}sandcrime_push_subscriptions
            WHERE last_used < DATE_SUB(%s, INTERVAL 30 DAY)
        ", current_time('mysql')));
    }

    public static function generate_vapid_keys() {
        if (!function_exists('openssl_pkey_new')) {
            throw new Exception('OpenSSL extension is required for VAPID key generation');
        }

        $res = openssl_pkey_new(array(
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC
        ));

        if (!$res) {
            throw new Exception('Failed to generate VAPID keys');
        }

        openssl_pkey_export($res, $privateKey);
        $details = openssl_pkey_get_details($res);

        return array(
            'publicKey' => base64_encode($details['key']),
            'privateKey' => base64_encode($privateKey)
        );
    }
}