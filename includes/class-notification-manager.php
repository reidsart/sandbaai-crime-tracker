<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Manager {
    private $settings;
    private $analytics;
    private $providers;
    private $queue;

    public function __construct() {
        $this->settings = new SandCrime_Notification_Settings();
        $this->analytics = new SandCrime_Notification_Analytics();
        $this->queue = new SandCrime_Notification_Queue();
        
        $this->initialize_providers();
        $this->register_hooks();
    }

    private function initialize_providers() {
        $this->providers = array(
            'email' => new SandCrime_Email_Provider($this->settings),
            'push' => new SandCrime_Push_Provider($this->settings),
            'sms' => new SandCrime_SMS_Provider($this->settings)
        );
    }

    private function register_hooks() {
        add_action('sandcrime_process_notification_queue', array($this, 'process_queue'));
        add_action('sandcrime_retry_failed_notifications', array($this, 'retry_failed'));
        add_action('wp_ajax_sandcrime_send_notification', array($this, 'ajax_send_notification'));
        add_action('wp_ajax_sandcrime_bulk_send_notifications', array($this, 'ajax_bulk_send'));
    }

    public function send($notification) {
        try {
            $this->validate_notification($notification);
            
            if (!$this->check_rate_limits($notification)) {
                throw new Exception(__('Rate limit exceeded', 'sandcrime'));
            }

            // Prepare notification data
            $prepared = $this->prepare_notification($notification);

            // Check if notification should be queued
            if ($this->should_queue($prepared)) {
                return $this->queue->add($prepared);
            }

            // Send immediately if not queued
            return $this->process_notification($prepared);

        } catch (Exception $e) {
            $this->log_error($e->getMessage(), $notification);
            throw $e;
        }
    }

    public function bulk_send($notifications) {
        $results = array(
            'success' => array(),
            'failed' => array()
        );

        foreach ($notifications as $notification) {
            try {
                $result = $this->send($notification);
                $results['success'][] = array(
                    'notification' => $notification,
                    'result' => $result
                );
            } catch (Exception $e) {
                $results['failed'][] = array(
                    'notification' => $notification,
                    'error' => $e->getMessage()
                );
            }
        }

        return $results;
    }

    public function process_queue() {
        $batch_size = $this->settings->get_setting('general.batch_size');
        $notifications = $this->queue->get_batch($batch_size);

        foreach ($notifications as $notification) {
            try {
                $result = $this->process_notification($notification);
                
                if ($result['success']) {
                    $this->queue->mark_completed($notification['id']);
                } else {
                    $this->handle_failure($notification);
                }

            } catch (Exception $e) {
                $this->handle_failure($notification, $e->getMessage());
            }
        }
    }

    public function retry_failed() {
        $retry_limit = $this->settings->get_setting('general.retry_attempts');
        $notifications = $this->queue->get_failed_notifications($retry_limit);

        foreach ($notifications as $notification) {
            try {
                $result = $this->process_notification($notification);
                
                if ($result['success']) {
                    $this->queue->mark_completed($notification['id']);
                } else {
                    $this->handle_failure($notification);
                }

            } catch (Exception $e) {
                $this->handle_failure($notification, $e->getMessage());
            }
        }
    }

    private function process_notification($notification) {
        $provider = $this->get_provider($notification['type']);
        
        if (!$provider) {
            throw new Exception(sprintf(
                __('Unsupported notification type: %s', 'sandcrime'),
                $notification['type']
            ));
        }

        // Track delivery attempt
        $delivery_id = $this->analytics->track_delivery(
            $notification['id'],
            $notification['recipient_id'],
            $notification['type'],
            'pending'
        );

        try {
            $result = $provider->send($notification);

            // Update delivery status
            $this->analytics->track_delivery(
                $notification['id'],
                $notification['recipient_id'],
                $notification['type'],
                $result['success'] ? 'delivered' : 'failed',
                array(
                    'provider_response' => $result['response'],
                    'delivery_id' => $delivery_id
                )
            );

            return $result;

        } catch (Exception $e) {
            // Update delivery status with error
            $this->analytics->track_delivery(
                $notification['id'],
                $notification['recipient_id'],
                $notification['type'],
                'failed',
                array(
                    'error' => $e->getMessage(),
                    'delivery_id' => $delivery_id
                )
            );

            throw $e;
        }
    }

    private function validate_notification($notification) {
        $required_fields = array('type', 'recipient', 'message');

        foreach ($required_fields as $field) {
            if (empty($notification[$field])) {
                throw new Exception(sprintf(
                    __('Missing required field: %s', 'sandcrime'),
                    $field
                ));
            }
        }

        if (!in_array($notification['type'], array_keys($this->providers))) {
            throw new Exception(sprintf(
                __('Invalid notification type: %s', 'sandcrime'),
                $notification['type']
            ));
        }

        // Validate recipient format based on type
        $this->validate_recipient($notification['recipient'], $notification['type']);
    }

    private function validate_recipient($recipient, $type) {
        switch ($type) {
            case 'email':
                if (!is_email($recipient)) {
                    throw new Exception(__('Invalid email address', 'sandcrime'));
                }
                break;

            case 'sms':
                if (!preg_match('/^\+?[1-9]\d{1,14}$/', $recipient)) {
                    throw new Exception(__('Invalid phone number', 'sandcrime'));
                }
                break;

            case 'push':
                if (empty($recipient['endpoint']) || empty($recipient['keys'])) {
                    throw new Exception(__('Invalid push subscription data', 'sandcrime'));
                }
                break;
        }
    }

    private function prepare_notification($notification) {
        $prepared = wp_parse_args($notification, array(
            'id' => uniqid('notification_'),
            'title' => '',
            'message' => '',
            'type' => '',
            'recipient' => '',
            'recipient_id' => 0,
            'template_id' => 0,
            'priority' => $this->settings->get_setting('general.default_priority'),
            'metadata' => array(),
            'created_at' => current_time('mysql')
        ));

        // Get recipient ID if not provided
        if (empty($prepared['recipient_id'])) {
            $prepared['recipient_id'] = $this->get_recipient_id($prepared['recipient']);
        }

        // Apply template if specified
        if (!empty($prepared['template_id'])) {
            $prepared = $this->apply_template($prepared);
        }

        return $prepared;
    }

    private function get_recipient_id($recipient) {
        if (is_email($recipient)) {
            $user = get_user_by('email', $recipient);
            return $user ? $user->ID : 0;
        }

        // For other recipient types, try to find associated user
        $user_meta = $this->get_user_by_contact($recipient);
        return $user_meta ? $user_meta->user_id : 0;
    }

    private function apply_template($notification) {
        $template = get_post($notification['template_id']);
        
        if (!$template || 'notification_template' !== $template->post_type) {
            throw new Exception(__('Invalid template ID', 'sandcrime'));
        }

        $template_data = get_post_meta($template->ID, '_template_data', true);
        
        // Replace placeholders with actual values
        $content = $this->replace_placeholders(
            $template_data['content'],
            array_merge($notification, array('recipient_name' => $this->get_recipient_name($notification)))
        );

        $notification['message'] = $content;
        
        if (empty($notification['title']) && !empty($template_data['title'])) {
            $notification['title'] = $this->replace_placeholders(
                $template_data['title'],
                $notification
            );
        }

        return $notification;
    }

    private function replace_placeholders($content, $data) {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) use ($data) {
            $key = trim($matches[1]);
            return isset($data[$key]) ? $data[$key] : $matches[0];
        }, $content);
    }

    private function get_recipient_name($notification) {
        if (!empty($notification['recipient_id'])) {
            $user = get_user_by('ID', $notification['recipient_id']);
            if ($user) {
                return $user->display_name;
            }
        }
        return '';
    }

    private function check_rate_limits($notification) {
        if (!$this->settings->get_setting('rate_limiting.enabled')) {
            return true;
        }

        // Skip rate limiting for exempt roles
        if ($this->is_recipient_exempt($notification['recipient_id'])) {
            return true;
        }

        $hourly_limit = $this->settings->get_setting('rate_limiting.max_per_hour');
        $daily_limit = $this->settings->get_setting('rate_limiting.max_per_day');

        $hourly_count = $this->get_notification_count($notification['recipient_id'], 'hour');
        $daily_count = $this->get_notification_count($notification['recipient_id'], 'day');

        return $hourly_count < $hourly_limit && $daily_count < $daily_limit;
    }

    private function is_recipient_exempt($recipient_id) {
        if (!$recipient_id) {
            return false;
        }

        $user = get_user_by('ID', $recipient_id);
        if (!$user) {
            return false;
        }

        $exempt_roles = $this->settings->get_setting('rate_limiting.exempt_roles');
        return array_intersect($user->roles, $exempt_roles) !== array();
    }

    private function get_notification_count($recipient_id, $period) {
        global $wpdb;
        
        $table = $this->analytics->get_deliveries_table();
        $interval = $period === 'hour' ? '1 HOUR' : '24 HOUR';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM $table 
            WHERE recipient_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL $interval)",
            $recipient_id
        ));
    }

    private function should_queue($notification) {
        // Queue based on priority
        if ($notification['priority'] === 'low') {
            return true;
        }

        // Queue if rate limits are approaching
        if ($this->settings->get_setting('rate_limiting.enabled')) {
            $hourly_count = $this->get_notification_count($notification['recipient_id'], 'hour');
            $hourly_limit = $this->settings->get_setting('rate_limiting.max_per_hour');
            
            if ($hourly_count > ($hourly_limit * 0.8)) {
                return true;
            }
        }

        return false;
    }

    private function handle_failure($notification, $error = '') {
        $retry_attempts = (int)($notification['retry_count'] ?? 0);
        $max_retries = $this->settings->get_setting('general.retry_attempts');

        if ($retry_attempts < $max_retries) {
            $this->queue->schedule_retry($notification);
        } else {
            $this->queue->mark_failed($notification['id'], $error);
        }
    }

    private function get_provider($type) {
        return isset($this->providers[$type]) ? $this->providers[$type] : null;
    }

    private function log_error($message, $context = array()) {
        error_log(sprintf(
            '[SandCrime Notifications] %s - Context: %s',
            $message,
            json_encode($context)
        ));
    }

    public function ajax_send_notification() {
        check_ajax_referer('sandcrime_send_notification', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'sandcrime'));
        }

        $notification = isset($_POST['notification']) ? 
            json_decode(stripslashes($_POST['notification']), true) : 
            array();

        try {
            $result = $this->send($notification);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_bulk_send() {
        check_ajax_referer('sandcrime_bulk_send', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'sandcrime'));
        }

        $notifications = isset($_POST['notifications']) ? 
            json_decode(stripslashes($_POST['notifications']), true) : 
            array();

        try {
            $results = $this->bulk_send($notifications);
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}