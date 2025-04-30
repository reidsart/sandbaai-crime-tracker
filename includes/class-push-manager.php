<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Push_Manager {
    private $db;
    private $settings;
    private $webpush;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->settings = new SandCrime_Notification_Settings();
        
        $this->init_webpush();
        $this->init_hooks();
    }

    private function init_webpush() {
        if (!$this->settings->get_setting('push.enabled')) {
            return;
        }

        require_once SANDCRIME_PLUGIN_DIR . 'vendor/autoload.php';

        $auth = array(
            'VAPID' => array(
                'subject' => get_bloginfo('url'),
                'publicKey' => $this->settings->get_setting('push.public_key'),
                'privateKey' => $this->settings->get_setting('push.private_key')
            )
        );

        $this->webpush = new Minishlink\WebPush\WebPush($auth);
        $this->webpush->setAutomaticPadding(true);
    }

    private function init_hooks() {
        add_action('sandcrime_send_push_notification', array($this, 'process_push_notification'), 10, 2);
        add_action('sandcrime_cleanup_subscriptions', array($this, 'cleanup_expired_subscriptions'));
        
        // Schedule cleanup
        if (!wp_next_scheduled('sandcrime_cleanup_subscriptions')) {
            wp_schedule_event(time(), 'daily', 'sandcrime_cleanup_subscriptions');
        }
    }

    public function add_subscription($user_id, $subscription_data) {
        if (!$this->settings->get_setting('push.enabled')) {
            throw new Exception(__('Push notifications are not enabled.', 'sandcrime'));
        }

        if (empty($subscription_data['endpoint']) || 
            empty($subscription_data['keys']['auth']) || 
            empty($subscription_data['keys']['p256dh'])) {
            throw new Exception(__('Invalid subscription data.', 'sandcrime'));
        }

        $existing = $this->db->get_row(
            $this->db->prepare(
                "SELECT id FROM {$this->db->prefix}sandcrime_push_subscriptions
                WHERE endpoint = %s",
                $subscription_data['endpoint']
            )
        );

        if ($existing) {
            // Update existing subscription
            $result = $this->db->update(
                $this->db->prefix . 'sandcrime_push_subscriptions',
                array(
                    'user_id' => $user_id,
                    'auth_token' => $subscription_data['keys']['auth'],
                    'public_key' => $subscription_data['keys']['p256dh'],
                    'content_encoding' => isset($subscription_data['contentEncoding']) ? 
                        $subscription_data['contentEncoding'] : 'aesgcm',
                    'metadata' => json_encode($subscription_data),
                    'updated_at' => current_time('mysql', true)
                ),
                array('id' => $existing->id),
                array('%d', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new subscription
            $result = $this->db->insert(
                $this->db->prefix . 'sandcrime_push_subscriptions',
                array(
                    'user_id' => $user_id,
                    'endpoint' => $subscription_data['endpoint'],
                    'auth_token' => $subscription_data['keys']['auth'],
                    'public_key' => $subscription_data['keys']['p256dh'],
                    'content_encoding' => isset($subscription_data['contentEncoding']) ? 
                        $subscription_data['contentEncoding'] : 'aesgcm',
                    'metadata' => json_encode($subscription_data),
                    'created_at' => current_time('mysql', true),
                    'updated_at' => current_time('mysql', true)
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }

        if ($result === false) {
            throw new Exception(__('Failed to save subscription.', 'sandcrime'));
        }

        return true;
    }

    public function remove_subscription($user_id, $endpoint) {
        $result = $this->db->delete(
            $this->db->prefix . 'sandcrime_push_subscriptions',
            array(
                'user_id' => $user_id,
                'endpoint' => $endpoint
            ),
            array('%d', '%s')
        );

        if ($result === false) {
            throw new Exception(__('Failed to remove subscription.', 'sandcrime'));
        }

        return true;
    }

    public function process_push_notification($notification_id, $recipient_id) {
        if (!$this->settings->get_setting('push.enabled')) {
            return;
        }

        $subscriptions = $this->get_user_subscriptions($recipient_id);
        if (empty($subscriptions)) {
            return;
        }

        $notification = $this->get_notification_data($notification_id);
        if (!$notification) {
            return;
        }

        $payload = json_encode(array(
            'notification' => array(
                'title' => $notification['title'],
                'body' => $notification['message'],
                'icon' => $this->settings->get_setting('push.icon'),
                'badge' => $this->settings->get_setting('push.badge'),
                'tag' => $notification_id,
                'data' => array(
                    'notification_id' => $notification_id,
                    'url' => $notification['url']
                )
            )
        ));

        foreach ($subscriptions as $subscription) {
            try {
                $this->send_push_notification($subscription, $payload);
            } catch (Exception $e) {
                // Log error and continue with other subscriptions
                error_log(sprintf(
                    'Push notification failed for subscription %s: %s',
                    $subscription->endpoint,
                    $e->getMessage()
                ));

                if ($this->is_subscription_expired($e)) {
                    $this->remove_subscription($recipient_id, $subscription->endpoint);
                }
            }
        }
    }

    private function send_push_notification($subscription, $payload) {
        if (!$this->webpush) {
            throw new Exception(__('WebPush not initialized.', 'sandcrime'));
        }

        $auth = array(
            'VAPID' => array(
                'subject' => get_bloginfo('url'),
                'publicKey' => $this->settings->get_setting('push.public_key'),
                'privateKey' => $this->settings->get_setting('push.private_key')
            )
        );

        $subscription_object = Minishlink\WebPush\Subscription::create(array(
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->public_key,
            'authToken' => $subscription->auth_token,
            'contentEncoding' => $subscription->content_encoding
        ));

        $this->webpush->queueNotification(
            $subscription_object,
            $payload
        );

        $report = $this->webpush->flush();

        if (!$report->isSuccess()) {
            throw new Exception($report->getReason());
        }

        // Update last used timestamp
        $this->db->update(
            $this->db->prefix . 'sandcrime_push_subscriptions',
            array(
                'last_used' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true)
            ),
            array('endpoint' => $subscription->endpoint),
            array('%s', '%s'),
            array('%s')
        );

        return true;
    }

    public function is_user_subscribed($user_id) {
        return (bool) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*)
                FROM {$this->db->prefix}sandcrime_push_subscriptions
                WHERE user_id = %d",
                $user_id
            )
        );
    }

    private function get_user_subscriptions($user_id) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT *
                FROM {$this->db->prefix}sandcrime_push_subscriptions
                WHERE user_id = %d",
                $user_id
            )
        );
    }

    private function get_notification_data($notification_id) {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT *
                FROM {$this->db->prefix}sandcrime_notification_queue
                WHERE notification_id = %s",
                $notification_id
            ),
            ARRAY_A
        );
    }

    private function is_subscription_expired($error) {
        $expired_messages = array(
            'expired subscription',
            'subscription expired',
            'invalid subscription'
        );

        foreach ($expired_messages as $message) {
            if (stripos($error->getMessage(), $message) !== false) {
                return true;
            }
        }

        return false;
    }

    public function cleanup_expired_subscriptions() {
        // Remove subscriptions not used in last 30 days
        $this->db->query(
            "DELETE FROM {$this->db->prefix}sandcrime_push_subscriptions
            WHERE last_used < DATE_SUB(NOW(), INTERVAL 30 DAY)
            OR last_used IS NULL"
        );

        // Remove subscriptions for deleted users
        $this->db->query(
            "DELETE ps FROM {$this->db->prefix}sandcrime_push_subscriptions ps
            LEFT JOIN {$this->db->prefix}users u ON ps.user_id = u.ID
            WHERE u.ID IS NULL"
        );
    }

    public function generate_vapid_keys() {
        if (!function_exists('openssl_pkey_new')) {
            throw new Exception(__('OpenSSL extension is required to generate VAPID keys.', 'sandcrime'));
        }

        $res = openssl_pkey_new(array(
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ));

        if (!$res) {
            throw new Exception(__('Failed to generate VAPID keys.', 'sandcrime'));
        }

        openssl_pkey_export($res, $private_key);
        $details = openssl_pkey_get_details($res);

        return array(
            'publicKey' => base64_encode($details['key']),
            'privateKey' => base64_encode($private_key)
        );
    }
}