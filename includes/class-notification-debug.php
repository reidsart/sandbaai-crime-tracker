<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Debug {
    private $log_dir;
    private $max_log_size = 10485760; // 10MB
    private $max_log_files = 5;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/sandcrime-logs';

        add_action('admin_menu', array($this, 'add_debug_page'));
        add_action('wp_ajax_get_notification_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_clear_notification_logs', array($this, 'ajax_clear_logs'));
        add_action('sandcrime_notification_error', array($this, 'log_error'), 10, 3);
        add_action('sandcrime_notification_event', array($this, 'log_event'), 10, 2);
    }

    public function initialize_logging() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            file_put_contents($this->log_dir . '/.htaccess', 'deny from all');
            file_put_contents($this->log_dir . '/index.php', '<?php // Silence is golden');
        }
    }

    public function log_error($error_message, $context = array(), $severity = 'error') {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'type' => $severity,
            'message' => $error_message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'ip' => $this->get_client_ip(),
            'request_url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        );

        $this->write_log($log_entry);
    }

    public function log_event($event_type, $data = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'type' => 'event',
            'event' => $event_type,
            'data' => $data,
            'user_id' => get_current_user_id()
        );

        $this->write_log($log_entry);
    }

    private function write_log($entry) {
        $this->initialize_logging();
        
        $log_file = $this->get_current_log_file();
        $entry_formatted = $this->format_log_entry($entry);

        if (file_exists($log_file) && filesize($log_file) > $this->max_log_size) {
            $this->rotate_logs();
        }

        file_put_contents(
            $log_file,
            $entry_formatted . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function format_log_entry($entry) {
        return json_encode($entry);
    }

    private function get_current_log_file() {
        return $this->log_dir . '/notification-' . date('Y-m-d') . '.log';
    }

    private function rotate_logs() {
        $log_files = glob($this->log_dir . '/notification-*.log');
        usort($log_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        while (count($log_files) >= $this->max_log_files) {
            $old_file = array_pop($log_files);
            @unlink($old_file);
        }

        $current_file = $this->get_current_log_file();
        $backup_file = $this->log_dir . '/notification-' . date('Y-m-d-His') . '.log';
        @rename($current_file, $backup_file);
    }

    public function add_debug_page() {
        add_submenu_page(
            'sandcrime-notifications',
            __('Debug Logs', 'sandcrime'),
            __('Debug Logs', 'sandcrime'),
            'manage_options',
            'sandcrime-notification-debug',
            array($this, 'render_debug_page')
        );
    }

    public function render_debug_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include SANDCRIME_PLUGIN_DIR . 'templates/admin/notification-debug.php';
    }

    public function ajax_get_logs() {
        check_ajax_referer('sandcrime_debug', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';
        $date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

        $logs = $this->get_filtered_logs($type, $date, $search);
        wp_send_json_success($logs);
    }

    public function ajax_clear_logs() {
        check_ajax_referer('sandcrime_debug', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $log_files = glob($this->log_dir . '/notification-*.log');
        foreach ($log_files as $file) {
            @unlink($file);
        }

        wp_send_json_success(__('Logs cleared successfully', 'sandcrime'));
    }

    private function get_filtered_logs($type, $date, $search) {
        $log_file = $this->log_dir . '/notification-' . $date . '.log';
        if (!file_exists($log_file)) {
            return array();
        }

        $logs = array();
        $handle = fopen($log_file, 'r');
        
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $entry = json_decode($line, true);
                if ($entry === null) continue;

                if ($type !== 'all' && $entry['type'] !== $type) {
                    continue;
                }

                if ($search && !$this->log_entry_matches_search($entry, $search)) {
                    continue;
                }

                $logs[] = $this->format_log_entry_for_display($entry);
            }
            fclose($handle);
        }

        return array_reverse($logs);
    }

    private function log_entry_matches_search($entry, $search) {
        $search = strtolower($search);
        $message = isset($entry['message']) ? strtolower($entry['message']) : '';
        $event = isset($entry['event']) ? strtolower($entry['event']) : '';
        
        return strpos($message, $search) !== false || 
               strpos($event, $search) !== false ||
               strpos(json_encode($entry), $search) !== false;
    }

    private function format_log_entry_for_display($entry) {
        $formatted = array(
            'timestamp' => $entry['timestamp'],
            'type' => $entry['type'],
            'message' => isset($entry['message']) ? $entry['message'] : '',
            'event' => isset($entry['event']) ? $entry['event'] : '',
            'user' => $entry['user_id'] ? get_userdata($entry['user_id'])->display_name : 'System',
            'context' => isset($entry['context']) ? $entry['context'] : array()
        );

        if (isset($entry['trace'])) {
            $formatted['trace'] = array_map(function($item) {
                return array(
                    'file' => isset($item['file']) ? str_replace(ABSPATH, '', $item['file']) : '',
                    'line' => isset($item['line']) ? $item['line'] : '',
                    'function' => isset($item['function']) ? $item['function'] : ''
                );
            }, $entry['trace']);
        }

        return $formatted;
    }

    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (isset($_SERVER[$header])) {
                foreach (explode(',', $_SERVER[$header]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return '0.0.0.0';
    }
}

// Initialize the debug system
new SandCrime_Notification_Debug();