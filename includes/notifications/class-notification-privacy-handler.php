<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Privacy_Handler {
    public function __construct() {
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_eraser'));
    }

    public function register_exporter($exporters) {
        $exporters['sandcrime-notifications'] = array(
            'exporter_friendly_name' => __('SandCrime Notifications', 'sandcrime'),
            'callback' => array($this, 'export_user_data'),
        );
        return $exporters;
    }

    public function register_eraser($erasers) {
        $erasers['sandcrime-notifications'] = array(
            'eraser_friendly_name' => __('SandCrime Notifications', 'sandcrime'),
            'callback' => array($this, 'erase_user_data'),
        );
        return $erasers;
    }

    public function export_user_data($email_address, $page = 1) {
        $user = get_user_by('email', $email_address);
        if (!$user) {
            return array(
                'data' => array(),
                'done' => true
            );
        }

        $export_items = array();
        
        // Export notification settings
        $settings = SandCrime_Notification_Handler::get_user_settings($user->ID);
        if ($settings) {
            $export_items[] = array(
                'group_id' => 'sandcrime-notification-settings',
                'group_label' => __('Notification Settings', 'sandcrime'),
                'item_id' => 'notification-settings-' . $user->ID,
                'data' => array(
                    array(
                        'name' => __('Email Notifications', 'sandcrime'),
                        'value' => $settings['email_enabled'] ? __('Enabled', 'sandcrime') : __('Disabled', 'sandcrime')
                    ),
                    array(
                        'name' => __('Push Notifications', 'sandcrime'),
                        'value' => $settings['push_enabled'] ? __('Enabled', 'sandcrime') : __('Disabled', 'sandcrime')
                    ),
                    array(
                        'name' => __('Email Frequency', 'sandcrime'),
                        'value' => ucfirst($settings['email_frequency'])
                    ),
                    array(
                        'name' => __('Notification Types', 'sandcrime'),
                        'value' => implode(', ', json_decode($settings['notification_types'], true))
                    )
                )
            );
        }

        // Export notifications
        global $wpdb;
        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}sandcrime_notifications
            WHERE user_id = %d
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d
        ", $user->ID, 50, ($page - 1) * 50));

        if ($notifications) {
            foreach ($notifications as $notification) {
                $export_items[] = array(
                    'group_id' => 'sandcrime-notifications',
                    'group_label' => __('Notifications', 'sandcrime'),
                    'item_id' => 'notification-' . $notification->id,
                    'data' => array(
                        array(
                            'name' => __('Title', 'sandcrime'),
                            'value' => $notification->title
                        ),
                        array(
                            'name' => __('Message', 'sandcrime'),
                            'value' => $notification->message
                        ),
                        array(
                            'name' => __('Type', 'sandcrime'),
                            'value' => $notification->type
                        ),
                        array(
                            'name' => __('Priority', 'sandcrime'),
                            'value' => $notification->priority
                        ),
                        array(
                            'name' => __('Created', 'sandcrime'),
                            'value' => $notification->created_at
                        )
                    )
                );
            }
        }

        // Export push subscriptions
        $subscriptions = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}sandcrime_push_subscriptions
            WHERE user_id = %d
        ", $user->ID));

        if ($subscriptions) {
            foreach ($subscriptions as $subscription) {
                $export_items[] = array(
                    'group_id' => 'sandcrime-push-subscriptions',
                    'group_label' => __('Push Notification Subscriptions', 'sandcrime'),
                    'item_id' => 'push-subscription-' . $subscription->id,
                    'data' => array(
                        array(
                            'name' => __('Device Platform', 'sandcrime'),
                            'value' => $subscription->platform
                        ),
                        array(
                            'name' => __('Language', 'sandcrime'),
                            'value' => $subscription->language
                        ),
                        array(
                            'name' => __('Last Used', 'sandcrime'),
                            'value' => $subscription->last_used
                        )
                    )
                );
            }
        }

        return array(
            'data' => $export_items,
            'done' => count($notifications) < 50
        );
    }

    public function erase_user_data($email_address, $page = 1) {
        $user = get_user_by('email', $email_address);
        if (!$user) {
            return array(
                'items_removed' => false,
                'items_retained' => false,
                'messages' => array(),
                'done' => true
            );
        }

        global $wpdb;
        $items_removed = false;
        $messages = array();

        // Remove notifications
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'sandcrime_notifications',
            array('user_id' => $user->ID)
        );
        if ($deleted) {
            $items_removed = true;
            $messages[] = __('Notifications deleted.', 'sandcrime');
        }

        // Remove notification settings
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'sandcrime_notification_settings',
            array('user_id' => $user->ID)
        );
        if ($deleted) {
            $items_removed = true;
            $messages[] = __('Notification settings deleted.', 'sandcrime');
        }

        // Remove push subscriptions
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'sandcrime_push_subscriptions',
            array('user_id' => $user->ID)
        );
        if ($deleted) {
            $items_removed = true;
            $messages[] = __('Push notification subscriptions deleted.', 'sandcrime');
        }

        return array(
            'items_removed' => $items_removed,
            'items_retained' => false,
            'messages' => $messages,
            'done' => true
        );
    }
}

// Initialize the privacy handler
new SandCrime_Notification_Privacy_Handler();