<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Push_Provider implements SandCrime_Provider_Interface {
    private $settings;
    private $vapid;

    public function __construct($settings) {
        $this->settings = $settings;
        $this->initialize_vapid();
    }

    private function initialize_vapid() {
        require_once SANDCRIME_PLUGIN_DIR . 'includes/lib/web-push/autoload.php';

        $this->vapid = new Minishlink\WebPush\WebPush(array(
            'VAPID' => array(
                'subject' => get_bloginfo('url'),
                'publicKey' => $this->settings->get_setting('push.vapid_public_key'),
                'privateKey' => $this->settings->get_setting('push.vapid_private_key')
            )
        ));
    }

    public function send($notification) {
        try {
            // Validate subscription data
            $subscription = $this->validate_subscription($notification['recipient']);

            // Prepare notification payload
            $payload = $this->prepare_payload($notification);

            // Configure options
            $options = $this->prepare_options($notification);

            // Send push notification
            $report = $this->vapid->sendNotification(
                $subscription,
                json_encode($payload),
                $options
            );

            // Process response
            $response = $this->process_report($report);

            // Update subscription if needed
            if ($response['success'] && !empty($response['endpoint'])) {
                $this->update_subscription($notification['recipient_id'], $response['endpoint']);
            }

            return $response;

        } catch (Exception $e) {
            throw new Exception(sprintf(
                __('Push notification failed: %s', 'sandcrime'),
                $e->getMessage()
            ));
        }
    }

    private function validate_subscription($subscription_data) {
        if (empty($subscription_data['endpoint']) || empty($subscription_data['keys'])) {
            throw new Exception(__('Invalid push subscription data', 'sandcrime'));
        }

        return Minishlink\WebPush\Subscription::create(array(
            'endpoint' => $subscription_data['endpoint'],
            'publicKey' => $subscription_data['keys']['p256dh'],
            'authToken' => $subscription_data['keys']['auth']
        ));
    }

    private function prepare_payload($notification) {
        $payload = array(
            'notification' => array(
                'title' => $notification['title'],
                'body' => $notification['message'],
                'icon' => $this->settings->get_setting('push.icon'),
                'badge' => $this->settings->get_setting('push.badge'),
                'tag' => $notification['id'],
                'timestamp' => time() * 1000,
                'requireInteraction' => true,
                'silent' => false
            ),
            'data' => array(
                'id' => $notification['id'],
                'url' => home_url('/'),
                'type' => 'notification'
            )
        );

        // Add actions if defined
        if (!empty($notification['actions'])) {
            $payload['notification']['actions'] = array_map(function($action) {
                return array(
                    'action' => $action['id'],
                    'title' => $action['title'],
                    'icon' => $action['icon'] ?? ''
                );
            }, $notification['actions']);
        }

        // Add custom data
        if (!empty($notification['metadata'])) {
            $payload['data'] = array_merge($payload['data'], $notification['metadata']);
        }

        // Add image if provided
        if (!empty($notification['image'])) {
            $payload['notification']['image'] = $notification['image'];
        }

        return $payload;
    }

    private function prepare_options($notification) {
        $options = array(
            'TTL' => 86400, // 24 hours default
            'urgency' => 'normal',
            'topic' => $notification['id']
        );

        // Set TTL based on notification priority
        if (!empty($notification['priority'])) {
            switch ($notification['priority']) {
                case 'high':
                    $options['TTL'] = 3600; // 1 hour
                    $options['urgency'] = 'high';
                    break;
                case 'low':
                    $options['TTL'] = 259200; // 3 days
                    $options['urgency'] = 'low';
                    break;
            }
        }

        return $options;
    }

    private function process_report($report) {
        $response = array(
            'success' => false,
            'message' => '',
            'endpoint' => '',
            'statusCode' => 0
        );

        if ($report->isSuccess()) {
            $response['success'] = true;
            $response['message'] = 'Successfully delivered';
            $response['endpoint'] = $report->getEndpoint();
            $response['statusCode'] = $report->getResponse()->getStatusCode();
        } else {
            $response['message'] = $report->getReason();
            $response['statusCode'] = $report->getResponse()->getStatusCode();

            // Handle specific error cases
            if ($response['statusCode'] === 410) {
                $response['message'] = 'Subscription has expired';
            } elseif ($response['statusCode'] === 404) {
                $response['message'] = 'Subscription not found';
            }
        }

        return $response;
    }

    private function update_subscription($user_id, $endpoint) {
        if (!$user_id) {
            return;
        }

        $subscriptions = get_user_meta($user_id, '_push_subscriptions', true) ?: array();

        // Update or add subscription
        $found = false;
        foreach ($subscriptions as &$sub) {
            if ($sub['endpoint'] === $endpoint) {
                $sub['last_used'] = current_time('mysql');
                $found = true;
                break;
            }
        }

        if (!$found) {
            $subscriptions[] = array(
                'endpoint' => $endpoint,
                'last_used' => current_time('mysql')
            );
        }

        // Clean up old subscriptions
        $subscriptions = array_filter($subscriptions, function($sub) {
            $last_used = strtotime($sub['last_used']);
            return (time() - $last_used) < (30 * DAY_IN_SECONDS);
        });

        update_user_meta($user_id, '_push_subscriptions', $subscriptions);
    }

    public function generate_vapid_keys() {
        try {
            $vapid = VAPID::createVapidKeys();
            
            return array(
                'public_key' => $vapid['publicKey'],
                'private_key' => $vapid['privateKey']
            );
        } catch (Exception $e) {
            throw new Exception(__('Failed to generate VAPID keys', 'sandcrime'));
        }
    }

    public function get_public_key() {
        return $this->settings->get_setting('push.vapid_public_key');
    }

    public function store_subscription($user_id, $subscription_data) {
        try {
            // Validate subscription data
            $this->validate_subscription($subscription_data);

            // Get existing subscriptions
            $subscriptions = get_user_meta($user_id, '_push_subscriptions', true) ?: array();

            // Add new subscription if not exists
            $exists = false;
            foreach ($subscriptions as &$sub) {
                if ($sub['endpoint'] === $subscription_data['endpoint']) {
                    $sub = array_merge($sub, $subscription_data);
                    $sub['updated_at'] = current_time('mysql');
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $subscriptions[] = array_merge($subscription_data, array(
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ));
            }

            // Update user meta
            update_user_meta($user_id, '_push_subscriptions', $subscriptions);

            return true;

        } catch (Exception $e) {
            throw new Exception(sprintf(
                __('Failed to store push subscription: %s', 'sandcrime'),
                $e->getMessage()
            ));
        }
    }

    public function remove_subscription($user_id, $endpoint) {
        $subscriptions = get_user_meta($user_id, '_push_subscriptions', true) ?: array();

        // Remove subscription with matching endpoint
        $subscriptions = array_filter($subscriptions, function($sub) use ($endpoint) {
            return $sub['endpoint'] !== $endpoint;
        });

        update_user_meta($user_id, '_push_subscriptions', array_values($subscriptions));

        return true;
    }
}