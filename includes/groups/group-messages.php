<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Group_Messages {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_message_post_type'));
        add_action('wp_ajax_send_group_message', array(__CLASS__, 'handle_message_send'));
        add_action('wp_ajax_get_group_messages', array(__CLASS__, 'handle_message_fetch'));
        add_action('wp_ajax_mark_message_read', array(__CLASS__, 'handle_message_read'));
    }

    public static function register_message_post_type() {
        register_post_type('group_message', array(
            'labels' => array(
                'name' => 'Group Messages',
                'singular_name' => 'Group Message'
            ),
            'public' => false,
            'show_ui' => true,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'do_not_allow'
            ),
            'map_meta_cap' => true,
            'supports' => array('title', 'editor')
        ));
    }

    public static function send_message($group_id, $data) {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'sandcrime_group_messages',
            array(
                'group_id' => $group_id,
                'user_id' => get_current_user_id(),
                'message' => $data['message'],
                'message_type' => $data['type'],
                'priority' => $data['priority'] ?? 'normal',
                'created_at' => current_time('mysql'),
                'expires_at' => $data['expires_at'] ?? null
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            $message_id = $wpdb->insert_id;

            // Handle attachments if any
            if (!empty($data['attachments'])) {
                self::process_attachments($message_id, $data['attachments']);
            }

            // Send notifications to group members
            self::notify_group_members($group_id, $message_id, $data);

            return $message_id;
        }

        return false;
    }

    private static function process_attachments($message_id, $attachments) {
        global $wpdb;

        foreach ($attachments as $attachment) {
            $wpdb->insert(
                $wpdb->prefix . 'sandcrime_message_attachments',
                array(
                    'message_id' => $message_id,
                    'file_url' => $attachment['url'],
                    'file_type' => $attachment['type'],
                    'file_name' => $attachment['name']
                ),
                array('%d', '%s', '%s', '%s')
            );
        }
    }

    private static function notify_group_members($group_id, $message_id, $data) {
        global $wpdb;

        // Get all group members
        $members = $wpdb->get_results($wpdb->prepare("
            SELECT user_id, notification_preferences 
            FROM {$wpdb->prefix}sandcrime_group_members 
            WHERE group_id = %d
        ", $group_id));

        foreach ($members as $member) {
            // Skip sender
            if ($member->user_id == get_current_user_id()) {
                continue;
            }

            // Check member's notification preferences
            $preferences = json_decode($member->notification_preferences, true);
            
            if (self::should_notify_member($preferences, $data)) {
                // Create notification record
                $wpdb->insert(
                    $wpdb->prefix . 'sandcrime_notifications',
                    array(
                        'user_id' => $member->user_id,
                        'type' => 'group_message',
                        'reference_id' => $message_id,
                        'content' => json_encode(array(
                            'group_id' => $group_id,
                            'message_type' => $data['type'],
                            'priority' => $data['priority']
                        )),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%d', '%s', '%s')
                );

                // Trigger real-time notification
                do_action('sandcrime_send_realtime_notification', array(
                    'user_id' => $member->user_id,
                    'type' => 'group_message',
                    'data' => array(
                        'message_id' => $message_id,
                        'group_id' => $group_id,
                        'message_preview' => wp_trim_words($data['message'], 10),
                        'priority' => $data['priority']
                    )
                ));
            }
        }
    }

    private static function should_notify_member($preferences, $message_data) {
        if (empty($preferences)) {
            return true; // Default to notify if no preferences set
        }

        // Check message type preference
        if (isset($preferences['message_types'])) {
            if (!in_array($message_data['type'], $preferences['message_types'])) {
                return false;
            }
        }

        // Check priority preference
        if (isset($preferences['priority_threshold'])) {
            $priority_levels = array(
                'low' => 1,
                'normal' => 2,
                'high' => 3,
                'urgent' => 4
            );

            if ($priority_levels[$message_data['priority']] < 
                $priority_levels[$preferences['priority_threshold']]) {
                return false;
            }
        }

        return true;
    }

    public static function get_messages($group_id, $params = array()) {
        global $wpdb;

        $where = array('m.group_id = %d');
        $where_values = array($group_id);

        if (!empty($params['after'])) {
            $where[] = 'm.created_at > %s';
            $where_values[] = $params['after'];
        }

        if (!empty($params['type'])) {
            $where[] = 'm.message_type = %s';
            $where_values[] = $params['type'];
        }

        $limit = isset($params['limit']) ? intval($params['limit']) : 50;
        $offset = isset($params['offset']) ? intval($params['offset']) : 0;

        $messages = $wpdb->get_results($wpdb->prepare("
            SELECT 
                m.*,
                u.display_name as sender_name,
                GROUP_CONCAT(a.file_url) as attachments,
                (
                    SELECT COUNT(*) 
                    FROM {$wpdb->prefix}sandcrime_message_reads r 
                    WHERE r.message_id = m.id
                ) as read_count
            FROM {$wpdb->prefix}sandcrime_group_messages m
            LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}sandcrime_message_attachments a ON m.id = a.message_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY m.id
            ORDER BY m.created_at DESC
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($limit, $offset))));

        // Get read status for current user
        $user_id = get_current_user_id();
        foreach ($messages as &$message) {
            $message->is_read = $wpdb->get_var($wpdb->prepare("
                SELECT 1 FROM {$wpdb->prefix}sandcrime_message_reads
                WHERE message_id = %d AND user_id = %d
            ", $message->id, $user_id)) ? true : false;
        }

        return $messages;
    }

    public static function mark_as_read($message_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . 'sandcrime_message_reads',
            array(
                'message_id' => $message_id,
                'user_id' => $user_id,
                'read_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s')
        );
    }

    public static function can_send_message($group_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        global $wpdb;

        $role = $wpdb->get_var($wpdb->prepare("
            SELECT role 
            FROM {$wpdb->prefix}sandcrime_group_members
            WHERE group_id = %d AND user_id = %d
        ", $group_id, $user_id));

        return in_array($role, array('admin', 'moderator'));
    }

    // AJAX Handlers
    public static function handle_message_send() {
        check_ajax_referer('sandcrime_group_messages', 'nonce');

        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        
        if (!$group_id || !self::can_send_message($group_id)) {
            wp_send_json_error('Unauthorized');
        }

        $message_data = array(
            'message' => sanitize_textarea_field($_POST['message']),
            'type' => sanitize_text_field($_POST['type']),
            'priority' => sanitize_text_field($_POST['priority']),
            'expires_at' => sanitize_text_field($_POST['expires_at'])
        );

        // Handle file uploads
        if (!empty($_FILES['attachments'])) {
            $message_data['attachments'] = self::handle_file_uploads($_FILES['attachments']);
        }

        $result = self::send_message($group_id, $message_data);

        if ($result) {
            wp_send_json_success(array('message_id' => $result));
        } else {
            wp_send_json_error('Failed to send message');
        }
    }

    public static function handle_message_fetch() {
        check_ajax_referer('sandcrime_group_messages', 'nonce');

        $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
        
        if (!$group_id) {
            wp_send_json_error('Invalid group ID');
        }

        $params = array(
            'after' => isset($_GET['after']) ? sanitize_text_field($_GET['after']) : null,
            'type' => isset($_GET['type']) ? sanitize_text_field($_GET['type']) : null,
            'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 50,
            'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0
        );

        $messages = self::get_messages($group_id, $params);
        wp_send_json_success($messages);
    }

    public static function handle_message_read() {
        check_ajax_referer('sandcrime_group_messages', 'nonce');

        $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
        
        if (!$message_id) {
            wp_send_json_error('Invalid message ID');
        }

        $result = self::mark_as_read($message_id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to mark message as read');
        }
    }

    private static function handle_file_uploads($files) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $attachments = array();
        $allowed_types = array(
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        foreach ($files['name'] as $key => $value) {
            if ($files['error'][$key] === 0) {
                $file = array(
                    'name'     => $files['name'][$key],
                    'type'     => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error'    => $files['error'][$key],
                    'size'     => $files['size'][$key]
                );

                // Check file type
                if (!in_array($file['type'], $allowed_types)) {
                    continue;
                }

                $upload_overrides = array('test_form' => false);
                $movefile = wp_handle_upload($file, $upload_overrides);

                if ($movefile && !isset($movefile['error'])) {
                    $attachments[] = array(
                        'url' => $movefile['url'],
                        'type' => $file['type'],
                        'name' => $file['name']
                    );
                }
            }
        }

        return $attachments;
    }
}

// Initialize the group messages system
SandCrime_Group_Messages::init();