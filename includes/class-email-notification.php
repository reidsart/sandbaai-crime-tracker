<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Email_Notification extends SandCrime_Notification_Delivery {
    private $template;
    private $from_email;
    private $from_name;

    public function __construct() {
        parent::__construct();
        
        $this->template = isset($this->settings['email_template']) ? 
            $this->settings['email_template'] : $this->get_default_template();
        
        $this->from_email = isset($this->settings['from_email']) ? 
            $this->settings['from_email'] : get_option('admin_email');
        
        $this->from_name = isset($this->settings['from_name']) ? 
            $this->settings['from_name'] : get_option('blogname');

        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }

    public function deliver($notification_id, $recipient_id) {
        try {
            $notification = $this->get_notification($notification_id);
            if (!$notification) {
                throw new Exception('Notification not found');
            }

            $recipient = $this->get_recipient($recipient_id);
            if (!$recipient) {
                throw new Exception('Recipient not found');
            }

            $preferences = $this->get_recipient_preferences($recipient_id);
            if (!$preferences['email_enabled'] || !$this->should_deliver($notification, $preferences)) {
                return false;
            }

            $email_content = $this->prepare_email_content($notification, $recipient);
            $headers = $this->prepare_email_headers();

            $sent = wp_mail(
                $recipient->user_email,
                $email_content['subject'],
                $email_content['body'],
                $headers
            );

            if ($sent) {
                $this->track_delivery(
                    $notification_id,
                    $recipient_id,
                    'delivered',
                    'email',
                    array('email' => $recipient->user_email)
                );
                $this->log_event('email_sent', array(
                    'notification_id' => $notification_id,
                    'recipient_id' => $recipient_id
                ));
                return true;
            } else {
                throw new Exception('Failed to send email');
            }

        } catch (Exception $e) {
            $this->log_error('Email delivery failed', array(
                'notification_id' => $notification_id,
                'recipient_id' => $recipient_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    private function prepare_email_content($notification, $recipient) {
        $data = array(
            'recipient_name' => $recipient->display_name,
            'site_name' => get_option('blogname'),
            'notification_title' => $notification->title,
            'notification_message' => $notification->message,
            'notification_date' => wp_date(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($notification->created_at)
            )
        );

        // Add action links if available
        if (!empty($notification->actions)) {
            $actions = json_decode($notification->actions, true);
            if (is_array($actions)) {
                $data['action_buttons'] = $this->generate_action_buttons($actions);
            }
        }

        $subject = $this->format_content($notification->title, $data);
        $body = $this->format_content($this->template, array_merge($data, array(
            'content' => $notification->message
        )));

        return array(
            'subject' => $subject,
            'body' => $body
        );
    }

    private function prepare_email_headers() {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'X-Notification-ID: ' . uniqid('notification_', true)
        );

        return $headers;
    }

    private function generate_action_buttons($actions) {
        $buttons = '';
        foreach ($actions as $action) {
            $buttons .= sprintf(
                '<a href="%s" class="action-button" style="%s">%s</a>',
                esc_url($action['url']),
                $this->get_button_styles($action),
                esc_html($action['label'])
            );
        }
        return $buttons;
    }

    private function get_button_styles($action) {
        $default_styles = array(
            'display' => 'inline-block',
            'padding' => '10px 20px',
            'margin' => '10px 5px',
            'background-color' => '#0d6efd',
            'color' => '#ffffff',
            'text-decoration' => 'none',
            'border-radius' => '4px',
            'font-weight' => 'bold'
        );

        if (isset($action['style']) && is_array($action['style'])) {
            $default_styles = array_merge($default_styles, $action['style']);
        }

        return implode(';', array_map(
            function($key, $value) { return "$key: $value"; },
            array_keys($default_styles),
            $default_styles
        ));
    }

    private function get_default_template() {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; background-color: #f8f9fa; font-family: Arial, sans-serif;">
            <div style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h1 style="color: #333333; margin: 0;">{{notification_title}}</h1>
                </div>
                <div style="color: #666666; line-height: 1.6;">
                    {{content}}
                </div>
                {{action_buttons}}
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eeeeee; color: #999999; font-size: 12px;">
                    <p>This notification was sent from {{site_name}} on {{notification_date}}</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    public function set_html_content_type() {
        return 'text/html';
    }
}