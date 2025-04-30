<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Push_Notification extends SandCrime_Notification_Delivery {
    private $vapid_public_key;
    private $vapid_private_key;
    private $webpush;

    public function __construct() {
        parent::__construct();

        $this->vapid_public_key = isset($this->settings['vapid_public_key']) ? 
            $this->settings['vapid_public_key'] : '';
        $this->vapid_private_key = isset($this->settings['vapid_private_key']) ? 
            $this->settings['vapid_private_key'] : '';

        if ($this->vapid_public_key && $this->vapid_private_key) {
            $this->webpush = new WebPush(array(
                'VAPID' => array(
                    'subject' => get_bloginfo('admin_email'),
                    'publicKey' => $this->vapid_public_key,
                    'privateKey' => $this->vapid_private_key
                )
            ));
        }
    }

    public function deliver($notification_id, $recipient_id) {
        try {
            if (!$this->webpush) {
                throw new Exception('WebPush not configured');
            }

            $notification = $this->get_notification($notification_id);
            if (!$notification) {
                throw new Exception('Notification not found');
            }

            $preferences = $this->get_recipient_preferences($recipient_id);
            if (!$preferences['push_enabled'] || !$this->should_deliver($notification, $preferences)) {
                return false;
            }

            $subscriptions = $this->get_push_subscriptions($recipient_id);
            if (empty($subscriptions)) {
                return false;
            }

            $payload = $this->prepare_push_payload($notification);
            $successful_deliveries = 0;

            foreach ($subscriptions as $subscription) {
                try {
                    $result = $this->send_push_notification($subscription, $payload);
                    if ($result) {
                        $successful_deliveries++;
                    }
                } catch (Exception $e) {
                    if ($this->is_subscription_expired($e)) {
                        $this->remove_subscription($subscription->id);
                    }
                    continue;
                }
            }

            if ($successful_deliveries > 0) {
                $this->track_delivery(
                    $notification_id,
                    $recipient_id,
                    'delivered',
                    'push',
                    array('successful_deliveries' => $successful_deliveries)
                );
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->log_error('Push delivery failed', array(
                'notification_id' => $notification_id,
                'recipient_id' => $recipient_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    private function get_push_subscriptions($recipient_id) {
        return $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}sandcrime_push_subscriptions
            WHERE user_id = %d AND active = 1",
            $recipient_id
        ));
    }

    private function prepare_push_payload($notification) {
        $payload = array(
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'icon' => $this->get_notification_icon($notification),
            'badge' => plugins_url('assets/images/notification-badge.png', SANDCRIME_PLUGIN_FILE),
            'timestamp' => strtotime($notification->created_at) * 1000,
            'requireInteraction' => $notification->priority === 'urgent',
            'silent' => $notification->priority === 'low',
            'renotify' => true,
            'data' => array(
                'url' => $notification->url,
                'notification_id' => $notification->id
            )
        );

        // Add actions if available
        if (!empty($notification->actions)) {
            $actions = json_decode($notification->actions, true);
            if (is_array($actions)) {
                $payload['actions'] = array_map(function($action) {
                    return array(
                        'action' => $action['id'],
                        'title' => $action['label'],
                        'icon' => isset($action['icon']) ? $action['icon'] : null
                    );
                }, $actions);
            }
        }

        return json_encode($payload);
    }

    private function send_push_notification($subscription, $payload) {
        $subscription_data = json_decode($subscription->subscription_data, true);
        
        $push_subscription = Subscription::create($subscription_data);
        
        $report = $this->webpush->sendOneNotification(
            $push_subscription,
            $payload,
            array('timeout' => 10)
        );

        if ($report->isSuccess()) {
            $this->log_event('push_sent', array(
                'subscription_id' => $subscription->id
            ));
            return true;
        }

        $this->log_error('Push send failed', array(
            'subscription_id' => $subscription->id,
            'reason' => $report->getReason()
        ));

        return false;
    }

    private function is_subscription_expired(Exception $e) {
        return (
            $e instanceof ExpiredException ||
            strpos($e->getMessage(), '410 Gone') !== false ||
            strpos($e->getMessage(), '404 Not Found') !== false
        );
    }

    private function remove_subscription($subscription_id) {
        return $this->db->update(
            $this->db->prefix . 'sandcrime_push_subscriptions',
            array('active' => 0),
            array('id' => $subscription_id),
            array('%d'),
            array('%d')
        );
    }

    private function get_notification_icon($notification) {
        if (!empty($notification->icon)) {
            return $notification->icon;
        }

        switch ($notification->type) {
            case 'alert':
                return plugins_url('assets/images/alert-icon.png', SANDCRIME_PLUGIN_FILE);
            case 'message':
                return plugins_url('assets/images/message-icon.png', SANDCRIME_PLUGIN_FILE);
            case 'update':
                return plugins_url('assets/images/update-icon.png', SANDCRIME_PLUGIN_FILE);
            default:
                return plugins_url('assets/images/notification-icon.png', SANDCRIME_PLUGIN_FILE);
        }
    }
}