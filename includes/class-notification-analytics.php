<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Analytics {
    private $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        add_action('wp_ajax_get_notification_stats', array($this, 'get_stats'));
        add_action('wp_ajax_export_notification_stats', array($this, 'export_stats'));
    }

    public function get_stats() {
        check_ajax_referer('sandcrime_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : '7d';
        $stats = $this->calculate_stats($range);

        wp_send_json_success($stats);
    }

    private function calculate_stats($range) {
        $interval = $this->get_interval($range);
        $start_date = $this->get_start_date($range);

        return array(
            'timeline' => $this->get_timeline_data($start_date, $interval),
            'types' => $this->get_types_distribution($start_date),
            'priorities' => $this->get_priority_distribution($start_date),
            'delivery' => $this->get_delivery_success_rates($start_date),
            'engagement' => $this->get_engagement_metrics($start_date),
            'summary' => $this->get_summary_stats($start_date)
        );
    }

    private function get_timeline_data($start_date, $interval) {
        $total_query = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, %s) as period,
                COUNT(*) as total
            FROM {$this->db->prefix}sandcrime_notifications
            WHERE created_at >= %s
            GROUP BY period
            ORDER BY period
        ", $this->get_date_format($interval), $start_date);

        $read_query = $this->db->prepare("
            SELECT 
                DATE_FORMAT(read_at, %s) as period,
                COUNT(*) as total
            FROM {$this->db->prefix}sandcrime_notifications
            WHERE read_at >= %s
            GROUP BY period
            ORDER BY period
        ", $this->get_date_format($interval), $start_date);

        $total_data = $this->db->get_results($total_query);
        $read_data = $this->db->get_results($read_query);

        return array(
            'labels' => array_column($total_data, 'period'),
            'total' => array_column($total_data, 'total'),
            'read' => array_column($read_data, 'total')
        );
    }

    private function get_types_distribution($start_date) {
        $query = $this->db->prepare("
            SELECT 
                type,
                COUNT(*) as count
            FROM {$this->db->prefix}sandcrime_notifications
            WHERE created_at >= %s
            GROUP BY type
            ORDER BY count DESC
        ", $start_date);

        $results = $this->db->get_results($query);

        return array(
            'labels' => array_column($results, 'type'),
            'values' => array_column($results, 'count')
        );
    }

    private function get_priority_distribution($start_date) {
        $query = $this->db->prepare("
            SELECT 
                priority,
                COUNT(*) as count
            FROM {$this->db->prefix}sandcrime_notifications
            WHERE created_at >= %s
            GROUP BY priority
            ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low')
        ", $start_date);

        $results = $this->db->get_results($query);
        return array_column($results, 'count');
    }

    private function get_delivery_success_rates($start_date) {
        $query = $this->db->prepare("
            SELECT 
                delivery_type,
                (SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as success_rate
            FROM {$this->db->prefix}sandcrime_notification_queue
            WHERE created_at >= %s
            GROUP BY delivery_type
        ", $start_date);

        $results = $this->db->get_results($query);
        return array_column($results, 'success_rate');
    }

    private function get_engagement_metrics($start_date) {
        $query = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, %s) as period,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, read_at)) as avg_time
            FROM {$this->db->prefix}sandcrime_notifications
            WHERE created_at >= %s
                AND read_at IS NOT NULL
            GROUP BY period
            ORDER BY period
        ", $this->get_date_format($this->get_interval($range)), $start_date);

        $results = $this->db->get_results($query);

        return array(
            'labels' => array_column($results, 'period'),
            'values' => array_column($results, 'avg_time')
        );
    }

    private function get_summary_stats($start_date) {
        return array(
            'avg_read_time' => $this->calculate_average_read_time($start_date),
            'open_rate' => $this->calculate_open_rate($start_date),
            'click_rate' => $this->calculate_click_rate($start_date),
            'delivery_rate' => $this->calculate_delivery_rate($start_date)
        );
    }

    private function calculate_average_read_time($start_date) {
        return (float) $this->db->get_var($this->db->prepare("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, read_at))
            FROM {$this->db->prefix}sandcrime_notifications
            WHERE created_at >= %s AND read_at IS NOT NULL
        ", $start_date));
    }

    private function calculate_open_rate($start_date) {
        $stats = $this->db->get_row($this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read_count
            FROM {$this->db->prefix}sandcrime_notifications
            WHERE created_at >= %s
        ", $start_date));

        return $stats->total > 0 ? ($stats->read_count * 100.0 / $stats->total) : 0;
    }

    private function calculate_click_rate($start_date) {
        $stats = $this->db->get_row($this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as click_count
            FROM {$this->db->prefix}sandcrime_notifications
            WHERE created_at >= %s
        ", $start_date));

        return $stats->total > 0 ? ($stats->click_count * 100.0 / $stats->total) : 0;
    }

    private function calculate_delivery_rate($start_date) {
        $stats = $this->db->get_row($this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count
            FROM {$this->db->prefix}sandcrime_notification_queue
            WHERE created_at >= %s
        ", $start_date));

        return $stats->total > 0 ? ($stats->delivered_count * 100.0 / $stats->total) : 0;
    }

    private function get_interval($range) {
        switch ($range) {
            case '24h':
                return 'hour';
            case '7d':
            case '30d':
                return 'day';
            case '12m':
                return 'month';
            default:
                return 'day';
        }
    }

    private function get_start_date($range) {
        switch ($range) {
            case '24h':
                return date('Y-m-d H:i:s', strtotime('-24 hours'));
            case '7d':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case '30d':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            case '12m':
                return date('Y-m-d H:i:s', strtotime('-12 months'));
            default:
                return date('Y-m-d H:i:s', strtotime('-7 days'));
        }
    }

    private function get_date_format($interval) {
        switch ($interval) {
            case 'hour':
                return '%Y-%m-%d %H:00:00';
            case 'day':
                return '%Y-%m-%d';
            case 'month':
                return '%Y-%m';
            default:
                return '%Y-%m-%d';
        }
    }

    public function export_stats() {
        check_ajax_referer('sandcrime_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : '7d';
        $stats = $this->calculate_stats($range);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=notification-stats-' . $range . '.csv');

        $output = fopen('php://output', 'w');

        // Write Timeline Data
        fputcsv($output, array('Timeline'));
        fputcsv($output, array('Period', 'Total', 'Read'));
        foreach ($stats['timeline']['labels'] as $i => $period) {
            fputcsv($output, array(
                $period,
                $stats['timeline']['total'][$i],
                $stats['timeline']['read'][$i]
            ));
        }

        // Add blank line
        fputcsv($output, array(''));

        // Write Types Distribution
        fputcsv($output, array('Notification Types'));
        fputcsv($output, array('Type', 'Count'));
        foreach ($stats['types']['labels'] as $i => $type) {
            fputcsv($output, array($type, $stats['types']['values'][$i]));
        }

        fclose($output);
        exit;
    }
}

// Initialize the analytics
new SandCrime_Notification_Analytics();