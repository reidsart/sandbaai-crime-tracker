<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class SandCrime_Notification_Delivery {
    protected $db;
    protected $settings;
    protected $logger;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->settings = get_option('sandcrime_notification_settings', array());
        $this->logger = new SandCrime_Notification_Debug();
    }

    abstract public function deliver($notification_id, $recipient_id);

    protected function get_notification($notification_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}sandcrime_notifications
            WHERE id = %d",
            $notification_id
        ));
    }

    protected function get_recipient($recipient_id) {
        return get_userdata($recipient_id);
    }

    protected function get_recipient_preferences($recipient_id) {
        return get_user_meta($recipient_id, 'sandcrime_notification_preferences', true);
    }

    protected function track_delivery($notification_id, $recipient_id, $status, $delivery_type, $metadata = array()) {
        return $this->db->insert(
            $this->db->prefix . 'sandcrime_notification_deliveries',
            array(
                'notification_id' => $notification_id,
                'recipient_id' => $recipient_id,
                'delivery_type' => $delivery_type,
                'status' => $status,
                'metadata' => json_encode($metadata),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
    }

    protected function should_deliver($notification, $recipient_preferences) {
        // Check if notification type is enabled
        if (!in_array($notification->type, $recipient_preferences['notification_types'])) {
            return false;
        }

        // Check priority threshold
        $priority_levels = array('low' => 0, 'normal' => 1, 'high' => 2, 'urgent' => 3);
        $notification_priority = isset($priority_levels[$notification->priority]) ? 
            $priority_levels[$notification->priority] : 1;
        $threshold_priority = isset($priority_levels[$recipient_preferences['priority_threshold']]) ? 
            $priority_levels[$recipient_preferences['priority_threshold']] : 1;

        if ($notification_priority < $threshold_priority) {
            return false;
        }

        return true;
    }

    protected function format_content($content, $data = array()) {
        if (empty($content)) {
            return '';
        }

        // Replace placeholders
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    protected function log_error($message, $context = array()) {
        $this->logger->log_error($message, $context, 'delivery');
    }

    protected function log_event($event_type, $data = array()) {
        $this->logger->log_event($event_type, $data);
    }
}