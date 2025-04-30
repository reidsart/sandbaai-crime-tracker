<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_WebSocket_Handler {
    private $pusher;
    private $channels = array();

    public function __construct() {
        require_once SANDCRIME_PLUGIN_DIR . 'vendor/autoload.php';

        $this->pusher = new Pusher\Pusher(
            get_option('sandcrime_pusher_app_key'),
            get_option('sandcrime_pusher_app_secret'),
            get_option('sandcrime_pusher_app_id'),
            array(
                'cluster' => get_option('sandcrime_pusher_cluster'),
                'useTLS' => true
            )
        );

        add_action('init', array($this, 'register_hooks'));
    }

    public function register_hooks() {
        add_action('wp_ajax_get_websocket_auth', array($this, 'handle_auth_request'));
        add_action('sandcrime_notification_created', array($this, 'broadcast_notification'), 10, 2);
        add_action('sandcrime_notification_read', array($this, 'broadcast_read_status'), 10, 2);
    }

    public function handle_auth_request() {
        check_ajax_referer('sandcrime_websocket_auth', 'nonce');

        $channel_name = $_POST['channel_name'];
        $socket_id = $_POST['socket_id'];
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error('Unauthorized');
        }

        try {
            // Validate channel access
            if (!$this->can_access_channel($user_id, $channel_name)) {
                wp_send_json_error('Channel access denied');
            }

            // Generate auth token
            $auth = $this->pusher->authorizeChannel(
                $socket_id,
                $channel_name,
                array(
                    'user_id' => $user_id,
                    'user_info' => array(
                        'name' => get_userdata($user_id)->display_name
                    )
                )
            );

            wp_send_json_success($auth);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function broadcast_notification($notification_id, $user_id) {
        try {
            global $wpdb;
            $notification = $wpdb->get_row($wpdb->prepare("
                SELECT * 
                FROM {$wpdb->prefix}sandcrime_notifications
                WHERE id = %d
            ", $notification_id));

            if (!$notification) {
                return;
            }

            $channel = "private-user-{$user_id}";
            $event = "notification.new";
            $data = array(
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => json_decode($notification->data, true),
                'created_at' => $notification->created_at
            );

            $this->pusher->trigger($channel, $event, $data);
        } catch (Exception $e) {
            error_log("WebSocket broadcast failed: " . $e->getMessage());
        }
    }

    public function broadcast_read_status($notification_id, $user_id) {
        try {
            $channel = "private-user-{$user_id}";
            $event = "notification.read";
            $data = array(
                'notification_id' => $notification_id,
                'read_at' => current_time('mysql')
            );

            $this->pusher->trigger($channel, $event, $data);
        } catch (Exception $e) {
            error_log("WebSocket read status broadcast failed: " . $e->getMessage());
        }
    }

    private function can_access_channel($user_id, $channel_name) {
        // Validate private user channel
        if (preg_match('/^private-user-(\d+)$/', $channel_name, $matches)) {
            return $user_id === (int)$matches[1];
        }

        // Validate private group channel
        if (preg_match('/^private-group-(\d+)$/', $channel_name, $matches)) {
            $group_id = (int)$matches[1];
            return $this->is_group_member($user_id, $group_id);
        }

        return false;
    }

    private function is_group_member($user_id, $group_id) {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare("
            SELECT 1 
            FROM {$wpdb->prefix}sandcrime_group_members
            WHERE user_id = %d AND group_id = %d
        ", $user_id, $group_id));
    }
}

// Initialize WebSocket handler
new SandCrime_WebSocket_Handler();