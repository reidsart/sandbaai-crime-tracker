<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_SMS_Notification extends SandCrime_Notification_Delivery {
    private $twilio_sid;
    private $twilio_token;
    private $twilio_number;
    private $client;

    public function __construct() {
        parent::__construct();

        $this->twilio_sid = isset($this->settings['twilio_sid']) ? 
            $this->settings['twilio_sid'] : '';
        $this->twilio_token = isset($this->settings['twilio_token']) ? 
            $this->settings['twilio_token'] : '';
        $this->twilio_number = isset($this->settings['twilio_number']) ? 
            $this->settings['twilio_number'] : '';

        if ($this->twilio_sid && $this->twilio_token) {
            require_once SANDCRIME_PLUGIN_DIR . 'vendor/autoload.php';
            $this->client = new Twilio\Rest\Client($this->twilio_sid, $this->twilio_token);
        }
    }

    public function deliver($notification_id, $recipient_id) {
        try {
            if (!$this->client) {
                throw new Exception('SMS provider not configured');
            }

            $notification = $this->get_notification($notification_id);
            if (!$notification) {
                throw new Exception('Notification not found');
            }

            $preferences = $this->get_recipient_preferences($recipient_id);
            if (!isset($preferences['sms_enabled']) || 
                !$preferences['sms_enabled'] || 
                !$this->should_deliver($notification, $preferences)) {
                return false;
            }

            $phone_number = $this->get_recipient_phone_number($recipient_id);
            if (!$phone_number) {
                throw new Exception('No valid phone number found for recipient');
            }

            $message = $this->prepare_sms_content($notification);
            $result = $this->send_sms($phone_number, $message);

            if ($result) {
                $this->track_delivery(
                    $notification_id,
                    $recipient_id,
                    'delivered',
                    'sms',
                    array('phone_number' => $this->mask_phone_number($phone_number))
                );
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->log_error('SMS delivery failed', array(
                'notification_id' => $notification_id,
                'recipient_id' => $recipient_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    private function get_recipient_phone_number($recipient_id) {
        $phone = get_user_meta($recipient_id, 'sandcrime_phone_number', true);
        
        if (empty($phone)) {
            return false;
        }

        // Ensure phone number is in E.164 format
        return $this->format_phone_number($phone);
    }

    private function prepare_sms_content($notification) {
        $site_name = get_option('blogname');
        $priority_prefix = $this->get_priority_prefix($notification->priority);
        
        $message = $priority_prefix . $notification->title;
        
        // Add message body if there's room
        if (strlen($message) + strlen($notification->message) <= 160) {
            $message .= "\n" . $notification->message;
        }

        // Add URL if available and there's room
        if (!empty($notification->url)) {
            $short_url = $this->shorten_url($notification->url);
            if (strlen($message) + strlen($short_url) + 1 <= 160) {
                $message .= "\n" . $short_url;
            }
        }

        return $message;
    }

    private function send_sms($to, $message) {
        try {
            $result = $this->client->messages->create($to, array(
                'from' => $this->twilio_number,
                'body' => $message,
                'statusCallback' => $this->get_status_callback_url()
            ));

            $this->log_event('sms_sent', array(
                'message_sid' => $result->sid,
                'to' => $this->mask_phone_number($to)
            ));

            return true;

        } catch (Twilio\Exceptions\TwilioException $e) {
            $this->log_error('Twilio SMS send failed', array(
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage()
            ));
            return false;
        }
    }

    private function format_phone_number($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Check if number starts with country code
        if (substr($phone, 0, 1) !== '+') {
            // Add US country code by default
            $phone = '+1' . $phone;
        }

        return $phone;
    }

    private function get_priority_prefix($priority) {
        switch ($priority) {
            case 'urgent':
                return '🚨 URGENT: ';
            case 'high':
                return '⚠️ ';
            case 'low':
                return 'FYI: ';
            default:
                return '';
        }
    }

    private function shorten_url($url) {
        // Implementation depends on URL shortening service
        // For this example, we'll just return the original URL
        // TODO: Implement URL shortening service integration
        return $url;
    }

    private function get_status_callback_url() {
        return add_query_arg(array(
            'action' => 'sandcrime_sms_status_callback',
            'nonce' => wp_create_nonce('sandcrime_sms_callback')
        ), admin_url('admin-ajax.php'));
    }

    private function mask_phone_number($phone) {
        // Only show last 4 digits
        return substr($phone, 0, -4) . str_repeat('*', 4);
    }

    public function handle_status_callback() {
        if (!isset($_POST['MessageSid']) || !wp_verify_nonce($_REQUEST['nonce'], 'sandcrime_sms_callback')) {
            wp_die('Invalid request');
        }

        $message_sid = sanitize_text_field($_POST['MessageSid']);
        $status = sanitize_text_field($_POST['MessageStatus']);

        $this->log_event('sms_status_update', array(
            'message_sid' => $message_sid,
            'status' => $status
        ));

        // Update delivery status in database if needed
        if (in_array($status, array('failed', 'undelivered'))) {
            $this->handle_delivery_failure($message_sid, $status);
        }

        wp_send_json_success();
    }

    private function handle_delivery_failure($message_sid, $status) {
        // Look up the delivery record by message_sid
        $delivery = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}sandcrime_notification_deliveries
            WHERE metadata LIKE %s",
            '%' . $message_sid . '%'
        ));

        if ($delivery) {
            // Update the delivery status
            $this->db->update(
                $this->db->prefix . 'sandcrime_notification_deliveries',
                array(
                    'status' => 'failed',
                    'metadata' => json_encode(array(
                        'message_sid' => $message_sid,
                        'failure_status' => $status,
                        'failed_at' => current_time('mysql')
                    ))
                ),
                array('id' => $delivery->id),
                array('%s', '%s'),
                array('%d')
            );

            // Log the failure
            $this->log_error('SMS delivery failed after send', array(
                'message_sid' => $message_sid,
                'status' => $status,
                'delivery_id' => $delivery->id
            ));
        }
    }
}