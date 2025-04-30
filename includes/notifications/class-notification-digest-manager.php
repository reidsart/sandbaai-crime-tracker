<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Digest_Manager {
    private $digest_types = array('hourly', 'daily', 'weekly');
    private $template_engine;

    public function __construct() {
        $this->template_engine = new SandCrime_Template_Engine();
        add_action('init', array($this, 'register_hooks'));
    }

    public function register_hooks() {
        foreach ($this->digest_types as $type) {
            add_action("sandcrime_process_{$type}_digest", array($this, 'process_digest'));
        }
    }

    public function create_digest($user_id, $frequency, $notifications) {
        global $wpdb;

        try {
            $notification_ids = wp_list_pluck($notifications, 'id');
            
            $wpdb->insert(
                $wpdb->prefix . 'sandcrime_notification_digests',
                array(
                    'user_id' => $user_id,
                    'frequency' => $frequency,
                    'notifications' => implode(',', $notification_ids),
                    'scheduled_for' => $this->calculate_next_schedule($frequency),
                    'created_at' => current_time('mysql')
                )
            );

            return $wpdb->insert_id;
        } catch (Exception $e) {
            error_log('Failed to create digest: ' . $e->getMessage());
            return false;
        }
    }

    public function process_digest($frequency) {
        global $wpdb;

        $digests = $wpdb->get_results($wpdb->prepare("
            SELECT d.*, GROUP_CONCAT(n.id) as notification_ids
            FROM {$wpdb->prefix}sandcrime_notification_digests d
            JOIN {$wpdb->prefix}sandcrime_notifications n 
                ON FIND_IN_SET(n.id, d.notifications)
            WHERE d.frequency = %s
            AND d.sent_at IS NULL
            AND d.scheduled_for <= %s
            GROUP BY d.id
        ", $frequency, current_time('mysql')));

        foreach ($digests as $digest) {
            $this->send_digest($digest);
        }
    }

    private function send_digest($digest) {
        global $wpdb;

        try {
            $user = get_userdata($digest->user_id);
            if (!$user) {
                throw new Exception('User not found');
            }

            $notification_ids = explode(',', $digest->notification_ids);
            $notifications = $this->get_notifications_for_digest($notification_ids);
            
            if (empty($notifications)) {
                $this->mark_digest_sent($digest->id);
                return true;
            }

            $grouped_notifications = $this->group_notifications($notifications);
            $email_content = $this->generate_digest_email($user, $grouped_notifications, $digest->frequency);

            $subject = $this->get_digest_subject($digest->frequency);
            
            $success = wp_mail(
                $user->user_email,
                $subject,
                $email_content,
                array(
                    'Content-Type: text/html; charset=UTF-8',
                    'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
                )
            );

            if ($success) {
                $this->mark_digest_sent($digest->id);
                $this->mark_notifications_included($notification_ids);
            }

            return $success;
        } catch (Exception $e) {
            error_log('Failed to send digest: ' . $e->getMessage());
            return false;
        }
    }

    private function get_notifications_for_digest($notification_ids) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notifications 
            WHERE id IN (" . implode(',', array_fill(0, count($notification_ids), '%d')) . ")
            ORDER BY created_at DESC",
            $notification_ids
        ));
    }

    private function group_notifications($notifications) {
        $grouped = array();

        foreach ($notifications as $notification) {
            if (!isset($grouped[$notification->type])) {
                $grouped[$notification->type] = array();
            }
            $grouped[$notification->type][] = $notification;
        }

        return $grouped;
    }

    private function generate_digest_email($user, $grouped_notifications, $frequency) {
        $template_data = array(
            'user' => $user,
            'notifications' => $grouped_notifications,
            'frequency' => $frequency,
            'period' => $this->get_digest_period($frequency),
            'site_name' => get_option('blogname'),
            'site_url' => get_option('siteurl')
        );

        return $this->template_engine->render('email/digest.php', $template_data);
    }

    private function get_digest_subject($frequency) {
        $site_name = get_option('blogname');
        $period = $this->get_digest_period($frequency);

        return sprintf(
            '%s: Your %s Notification Digest',
            $site_name,
            ucfirst($period)
        );
    }

    private function get_digest_period($frequency) {
        switch ($frequency) {
            case 'hourly':
                return 'hourly';
            case 'daily':
                return 'daily';
            case 'weekly':
                return 'weekly';
            default:
                return '';
        }
    }

    private function calculate_next_schedule($frequency) {
        $now = current_time('timestamp');

        switch ($frequency) {
            case 'hourly':
                $next = strtotime('next hour', $now);
                break;
            case 'daily':
                $next = strtotime('tomorrow 6:00', $now);
                break;
            case 'weekly':
                $next = strtotime('next monday 6:00', $now);
                break;
            default:
                $next = $now;
        }

        return date('Y-m-d H:i:s', $next);
    }

    private function mark_digest_sent($digest_id) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'sandcrime_notification_digests',
            array(
                'sent_at' => current_time('mysql')
            ),
            array('id' => $digest_id)
        );
    }

    private function mark_notifications_included($notification_ids) {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}sandcrime_notifications 
            SET included_in_digest = 1 
            WHERE id IN (" . implode(',', array_fill(0, count($notification_ids), '%d')) . ")",
            $notification_ids
        ));
    }

    public function cleanup_old_digests() {
        global $wpdb;

        // Remove digests older than 90 days
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}sandcrime_notification_digests
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
    }
}

// Initialize the digest manager
new SandCrime_Notification_Digest_Manager();