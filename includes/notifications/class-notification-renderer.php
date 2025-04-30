<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Renderer {
    private $template_engine;
    private $type_icons = array(
        'report' => 'dashicons-clipboard',
        'group_message' => 'dashicons-format-chat',
        'group_alert' => 'dashicons-warning',
        'comment' => 'dashicons-admin-comments',
        'system' => 'dashicons-admin-generic'
    );

    private $priority_classes = array(
        'urgent' => 'notification-urgent',
        'high' => 'notification-high',
        'normal' => 'notification-normal',
        'low' => 'notification-low'
    );

    public function __construct() {
        $this->template_engine = new SandCrime_Template_Engine();
    }

    public function render_notification($notification, $format = 'html') {
        switch ($format) {
            case 'html':
                return $this->render_html_notification($notification);
            case 'text':
                return $this->render_text_notification($notification);
            case 'json':
                return $this->render_json_notification($notification);
            default:
                throw new Exception("Unsupported format: {$format}");
        }
    }

    private function render_html_notification($notification) {
        $template_data = array(
            'notification' => $notification,
            'icon' => $this->get_type_icon($notification->type),
            'priority_class' => $this->get_priority_class($notification->priority),
            'timestamp' => $this->format_timestamp($notification->created_at),
            'actions' => $this->get_notification_actions($notification)
        );

        return $this->template_engine->render('notification/single.php', $template_data);
    }

    private function render_text_notification($notification) {
        $text = sprintf(
            "%s\n%s\n%s\n",
            $notification->title,
            $notification->message,
            $this->format_timestamp($notification->created_at)
        );

        if ($notification->priority === 'urgent' || $notification->priority === 'high') {
            $text = "⚠️ " . $text;
        }

        return $text;
    }

    private function render_json_notification($notification) {
        return wp_json_encode(array(
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'priority' => $notification->priority,
            'created_at' => $notification->created_at,
            'formatted_date' => $this->format_timestamp($notification->created_at),
            'icon' => $this->get_type_icon($notification->type),
            'actions' => $this->get_notification_actions($notification)
        ));
    }

    public function render_notification_list($notifications, $format = 'html') {
        if ($format === 'html') {
            $template_data = array(
                'notifications' => $notifications,
                'renderer' => $this
            );
            return $this->template_engine->render('notification/list.php', $template_data);
        }

        $rendered = array();
        foreach ($notifications as $notification) {
            $rendered[] = $this->render_notification($notification, $format);
        }

        return $format === 'json' ? wp_json_encode($rendered) : implode("\n\n", $rendered);
    }

    private function get_type_icon($type) {
        return isset($this->type_icons[$type]) ? 
            $this->type_icons[$type] : 
            'dashicons-bell';
    }

    private function get_priority_class($priority) {
        return isset($this->priority_classes[$priority]) ? 
            $this->priority_classes[$priority] : 
            'notification-normal';
    }

    private function format_timestamp($timestamp) {
        $date = strtotime($timestamp);
        $now = current_time('timestamp');
        $diff = $now - $date;

        if ($diff < 60) {
            return __('Just now', 'sandcrime');
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return sprintf(
                _n('%s minute ago', '%s minutes ago', $minutes, 'sandcrime'),
                $minutes
            );
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return sprintf(
                _n('%s hour ago', '%s hours ago', $hours, 'sandcrime'),
                $hours
            );
        } else {
            return date_i18n(get_option('date_format'), $date);
        }
    }

    private function get_notification_actions($notification) {
        $actions = array();

        switch ($notification->type) {
            case 'report':
                $data = json_decode($notification->data, true);
                if (isset($data['report_id'])) {
                    $actions[] = array(
                        'label' => __('View Report', 'sandcrime'),
                        'url' => admin_url("admin.php?page=sandcrime-reports&report={$data['report_id']}")
                    );
                }
                break;

            case 'group_message':
                $data = json_decode($notification->data, true);
                if (isset($data['group_id'], $data['message_id'])) {
                    $actions[] = array(
                        'label' => __('View Message', 'sandcrime'),
                        'url' => admin_url("admin.php?page=sandcrime-groups&group={$data['group_id']}&message={$data['message_id']}")
                    );
                }
                break;

            case 'group_alert':
                $data = json_decode($notification->data, true);
                if (isset($data['group_id'], $data['alert_id'])) {
                    $actions[] = array(
                        'label' => __('View Alert', 'sandcrime'),
                        'url' => admin_url("admin.php?page=sandcrime-groups&group={$data['group_id']}&alert={$data['alert_id']}")
                    );
                    $actions[] = array(
                        'label' => __('Acknowledge', 'sandcrime'),
                        'url' => '#',
                        'class' => 'acknowledge-alert',
                        'data' => array(
                            'alert-id' => $data['alert_id']
                        )
                    );
                }
                break;
        }

        return apply_filters('sandcrime_notification_actions', $actions, $notification);
    }
}