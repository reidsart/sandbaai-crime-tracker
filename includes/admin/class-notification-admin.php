<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Admin {
    private $notification_handler;
    private $queue_manager;
    private $digest_manager;

    public function __construct() {
        $this->notification_handler = new SandCrime_Notification_Handler();
        $this->queue_manager = new SandCrime_Notification_Queue_Manager();
        $this->digest_manager = new SandCrime_Notification_Digest_Manager();

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_get_notification_stats', array($this, 'ajax_get_notification_stats'));
        add_action('wp_ajax_get_notification_log', array($this, 'ajax_get_notification_log'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Notifications', 'sandcrime'),
            __('Notifications', 'sandcrime'),
            'manage_options',
            'sandcrime-notifications',
            array($this, 'render_main_page'),
            'dashicons-bell',
            30
        );

        add_submenu_page(
            'sandcrime-notifications',
            __('Settings', 'sandcrime'),
            __('Settings', 'sandcrime'),
            'manage_options',
            'sandcrime-notification-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'sandcrime-notifications',
            __('Queue', 'sandcrime'),
            __('Queue', 'sandcrime'),
            'manage_options',
            'sandcrime-notification-queue',
            array($this, 'render_queue_page')
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sandcrime-notification') !== false) {
            wp_enqueue_style(
                'sandcrime-admin-notifications',
                plugins_url('assets/css/admin-notifications.css', SANDCRIME_PLUGIN_FILE),
                array(),
                SANDCRIME_VERSION
            );

            wp_enqueue_script(
                'sandcrime-admin-notifications',
                plugins_url('assets/js/admin-notifications.js', SANDCRIME_PLUGIN_FILE),
                array('jquery', 'wp-util'),
                SANDCRIME_VERSION,
                true
            );

            wp_localize_script('sandcrime-admin-notifications', 'sandcrimeAdmin', array(
                'nonce' => wp_create_nonce('sandcrime_admin'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'i18n' => array(
                    'confirmDelete' => __('Are you sure you want to delete this?', 'sandcrime'),
                    'error' => __('An error occurred', 'sandcrime'),
                    'success' => __('Operation completed successfully', 'sandcrime')
                )
            ));
        }
    }

    public function render_main_page() {
        $stats = $this->get_notification_stats();
        $recent_notifications = $this->notification_handler->get_recent_notifications(10);
        
        include SANDCRIME_PLUGIN_DIR . 'templates/admin/notifications-dashboard.php';
    }

    public function render_settings_page() {
        if ($_POST && check_admin_referer('sandcrime_notification_settings')) {
            $this->save_notification_settings($_POST);
        }

        $settings = $this->get_notification_settings();
        include SANDCRIME_PLUGIN_DIR . 'templates/admin/notifications-settings.php';
    }

    public function render_queue_page() {
        $queue_stats = $this->queue_manager->get_queue_stats();
        $recent_queue_items = $this->queue_manager->get_recent_items(20);
        
        include SANDCRIME_PLUGIN_DIR . 'templates/admin/notifications-queue.php';
    }

    private function get_notification_stats() {
        global $wpdb;

        return array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_notifications"),
            'unread' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_notifications WHERE read_at IS NULL"),
            'today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_notifications WHERE created_at >= %s",
                date('Y-m-d 00:00:00')
            )),
            'queued' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_notification_queue WHERE status = 'pending'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_notification_queue WHERE status = 'failed'")
        );
    }

    private function get_notification_settings() {
        return array(
            'email_batch_size' => get_option('sandcrime_email_batch_size', 50),
            'push_batch_size' => get_option('sandcrime_push_batch_size', 100),
            'retention_days' => get_option('sandcrime_notification_retention_days', 90),
            'queue_retry_limit' => get_option('sandcrime_queue_retry_limit', 3),
            'digest_enabled' => get_option('sandcrime_digest_enabled', true),
            'push_enabled' => get_option('sandcrime_push_enabled', true)
        );
    }

    private function save_notification_settings($data) {
        $settings = array(
            'email_batch_size' => absint($data['email_batch_size']),
            'push_batch_size' => absint($data['push_batch_size']),
            'retention_days' => absint($data['retention_days']),
            'queue_retry_limit' => absint($data['queue_retry_limit']),
            'digest_enabled' => isset($data['digest_enabled']),
            'push_enabled' => isset($data['push_enabled'])
        );

        foreach ($settings as $key => $value) {
            update_option('sandcrime_' . $key, $value);
        }

        add_settings_error(
            'sandcrime_notifications',
            'settings_updated',
            __('Settings saved successfully.', 'sandcrime'),
            'updated'
        );
    }

    public function ajax_get_notification_stats() {
        check_ajax_referer('sandcrime_admin');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success($this->get_notification_stats());
    }

    public function ajax_get_notification_log() {
        check_ajax_referer('sandcrime_admin');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
        $per_page = 50;

        global $wpdb;
        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT n.*, u.display_name as user_name
            FROM {$wpdb->prefix}sandcrime_notifications n
            LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
            ORDER BY n.created_at DESC
            LIMIT %d OFFSET %d
        ", $per_page, ($page - 1) * $per_page));

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_notifications");

        wp_send_json_success(array(
            'notifications' => $notifications,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ));
    }
}

// Initialize the admin interface
new SandCrime_Notification_Admin();