<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Analytics {
    private $db;
    private $cache_group = 'sandcrime_analytics';
    private $cache_duration = 300; // 5 minutes

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    public function get_overview_stats() {
        $cache_key = 'overview_stats';
        $stats = wp_cache_get($cache_key, $this->cache_group);

        if ($stats === false) {
            $stats = array(
                'sent_today' => $this->get_sent_today_count(),
                'queue_size' => $this->get_queue_size(),
                'success_rate' => $this->calculate_success_rate(),
                'active_recipients' => $this->get_active_recipients_count(),
                'recent_errors' => $this->get_recent_errors()
            );

            wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_duration);
        }

        return $stats;
    }

    public function get_delivery_stats($start_date = null, $end_date = null, $type = null) {
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }

        $cache_key = "delivery_stats_{$start_date}_{$end_date}_" . ($type ?: 'all');
        $stats = wp_cache_get($cache_key, $this->cache_group);

        if ($stats === false) {
            $where = array(
                $this->db->prepare("created_at BETWEEN %s AND %s", 
                    $start_date . ' 00:00:00',
                    $end_date . ' 23:59:59'
                )
            );

            if ($type) {
                $where[] = $this->db->prepare("type = %s", $type);
            }

            $where = implode(' AND ', $where);

            $stats = $this->db->get_results(
                "SELECT 
                    DATE(created_at) as date,
                    status,
                    COUNT(*) as count
                FROM {$this->db->prefix}sandcrime_notification_logs
                WHERE {$where}
                GROUP BY DATE(created_at), status
                ORDER BY date ASC",
                ARRAY_A
            );

            wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_duration);
        }

        return $this->format_daily_stats($stats);
    }

    public function get_engagement_stats($start_date = null, $end_date = null) {
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }

        $cache_key = "engagement_stats_{$start_date}_{$end_date}";
        $stats = wp_cache_get($cache_key, $this->cache_group);

        if ($stats === false) {
            $stats = $this->db->get_results(
                $this->db->prepare(
                    "SELECT 
                        DATE(created_at) as date,
                        type,
                        SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opens,
                        SUM(CASE WHEN clicked = 1 THEN 1 ELSE 0 END) as clicks,
                        COUNT(*) as total
                    FROM {$this->db->prefix}sandcrime_notification_tracking
                    WHERE created_at BETWEEN %s AND %s
                    GROUP BY DATE(created_at), type
                    ORDER BY date ASC",
                    $start_date . ' 00:00:00',
                    $end_date . ' 23:59:59'
                ),
                ARRAY_A
            );

            wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_duration);
        }

        return $this->format_engagement_stats($stats);
    }

    public function get_recipient_stats($start_date = null, $end_date = null) {
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }

        $cache_key = "recipient_stats_{$start_date}_{$end_date}";
        $stats = wp_cache_get($cache_key, $this->cache_group);

        if ($stats === false) {
            $stats = array(
                'top_recipients' => $this->get_top_recipients($start_date, $end_date),
                'group_activity' => $this->get_group_activity($start_date, $end_date),
                'delivery_success' => $this->get_delivery_success_by_recipient($start_date, $end_date)
            );

            wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_duration);
        }

        return $stats;
    }

    public function get_performance_metrics($period = 'day') {
        $cache_key = "performance_metrics_{$period}";
        $metrics = wp_cache_get($cache_key, $this->cache_group);

        if ($metrics === false) {
            $metrics = array(
                'delivery_time' => $this->calculate_average_delivery_time($period),
                'success_rate' => $this->calculate_success_rate_by_period($period),
                'engagement_rate' => $this->calculate_engagement_rate_by_period($period),
                'queue_health' => $this->analyze_queue_health()
            );

            wp_cache_set($cache_key, $metrics, $this->cache_group, $this->cache_duration);
        }

        return $metrics;
    }

    private function get_sent_today_count() {
        return (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) 
                FROM {$this->db->prefix}sandcrime_notification_logs
                WHERE created_at >= %s",
                date('Y-m-d 00:00:00')
            )
        );
    }

    private function get_queue_size() {
        return (int) $this->db->get_var(
            "SELECT COUNT(*) 
            FROM {$this->db->prefix}sandcrime_notification_queue
            WHERE status IN ('pending', 'retry')"
        );
    }

    private function calculate_success_rate() {
        $stats = $this->db->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) as successful
            FROM {$this->db->prefix}sandcrime_notification_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            ARRAY_A
        );

        if (!$stats['total']) {
            return 100;
        }

        return round(($stats['successful'] / $stats['total']) * 100, 2);
    }

    private function get_active_recipients_count() {
        return (int) $this->db->get_var(
            "SELECT COUNT(DISTINCT recipient_id)
            FROM {$this->db->prefix}sandcrime_notification_tracking
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND recipient_id > 0"
        );
    }

    private function get_recent_errors($limit = 5) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT 
                    notification_id,
                    type,
                    error_message,
                    created_at as time
                FROM {$this->db->prefix}sandcrime_notification_logs
                WHERE status = 'failed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    private function format_daily_stats($raw_stats) {
        $formatted = array();
        
        foreach ($raw_stats as $row) {
            if (!isset($formatted[$row['date']])) {
                $formatted[$row['date']] = array(
                    'sent' => 0,
                    'delivered' => 0,
                    'failed' => 0
                );
            }
            $formatted[$row['date']][$row['status']] = (int) $row['count'];
        }

        return $formatted;
    }

    private function format_engagement_stats($raw_stats) {
        $formatted = array();
        
        foreach ($raw_stats as $row) {
            $date = $row['date'];
            $type = $row['type'];
            
            if (!isset($formatted[$date])) {
                $formatted[$date] = array();
            }
            
            $formatted[$date][$type] = array(
                'open_rate' => $row['total'] ? round(($row['opens'] / $row['total']) * 100, 2) : 0,
                'click_rate' => $row['total'] ? round(($row['clicks'] / $row['total']) * 100, 2) : 0,
                'total' => (int) $row['total']
            );
        }

        return $formatted;
    }

    private function get_top_recipients($start_date, $end_date) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT 
                    recipient_id,
                    COUNT(*) as total_received,
                    SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as total_opened,
                    SUM(CASE WHEN clicked = 1 THEN 1 ELSE 0 END) as total_clicked
                FROM {$this->db->prefix}sandcrime_notification_tracking
                WHERE created_at BETWEEN %s AND %s
                AND recipient_id > 0
                GROUP BY recipient_id
                ORDER BY total_received DESC
                LIMIT 10",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            ),
            ARRAY_A
        );
    }

    private function get_group_activity($start_date, $end_date) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT 
                    g.name as group_name,
                    COUNT(*) as total_notifications,
                    COUNT(DISTINCT nt.recipient_id) as unique_recipients
                FROM {$this->db->prefix}sandcrime_security_groups g
                JOIN {$this->db->prefix}sandcrime_group_members gm ON g.id = gm.group_id
                JOIN {$this->db->prefix}sandcrime_notification_tracking nt ON gm.user_id = nt.recipient_id
                WHERE nt.created_at BETWEEN %s AND %s
                GROUP BY g.id
                ORDER BY total_notifications DESC",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            ),
            ARRAY_A
        );
    }

    private function get_delivery_success_by_recipient($start_date, $end_date) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT 
                    recipient_id,
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) as successful
                FROM {$this->db->prefix}sandcrime_notification_logs
                WHERE created_at BETWEEN %s AND %s
                AND recipient_id > 0
                GROUP BY recipient_id
                HAVING total_sent >= 10
                ORDER BY (successful/total_sent) DESC
                LIMIT 10",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            ),
            ARRAY_A
        );
    }

    private function calculate_average_delivery_time($period) {
        $interval = $this->get_period_interval($period);
        
        return $this->db->get_var(
            $this->db->prepare(
                "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at))
                FROM {$this->db->prefix}sandcrime_notification_queue
                WHERE status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL %s)",
                $interval
            )
        );
    }

    private function calculate_success_rate_by_period($period) {
        $interval = $this->get_period_interval($period);
        
        $stats = $this->db->get_row(
            $this->db->prepare(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) as successful
                FROM {$this->db->prefix}sandcrime_notification_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s)",
                $interval
            ),
            ARRAY_A
        );

        return $stats['total'] ? round(($stats['successful'] / $stats['total']) * 100, 2) : 100;
    }

    private function calculate_engagement_rate_by_period($period) {
        $interval = $this->get_period_interval($period);
        
        $stats = $this->db->get_row(
            $this->db->prepare(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN opened = 1 OR clicked = 1 THEN 1 ELSE 0 END) as engaged
                FROM {$this->db->prefix}sandcrime_notification_tracking
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s)",
                $interval
            ),
            ARRAY_A
        );

        return $stats['total'] ? round(($stats['engaged'] / $stats['total']) * 100, 2) : 0;
    }

    private function analyze_queue_health() {
        $stats = $this->db->get_row(
            "SELECT 
                COUNT(*) as total_queued,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'retry' THEN 1 ELSE 0 END) as retrying,
                AVG(retry_count) as avg_retries
            FROM {$this->db->prefix}sandcrime_notification_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            ARRAY_A
        );

        return array(
            'queue_size' => (int) $stats['total_queued'],
            'failure_rate' => $stats['total_queued'] ? 
                round(($stats['failed'] / $stats['total_queued']) * 100, 2) : 0,
            'retry_rate' => $stats['total_queued'] ? 
                round(($stats['retrying'] / $stats['total_queued']) * 100, 2) : 0,
            'avg_retries' => round($stats['avg_retries'], 2)
        );
    }

    private function get_period_interval($period) {
        switch ($period) {
            case 'hour':
                return '1 HOUR';
            case 'day':
                return '24 HOUR';
            case 'week':
                return '7 DAY';
            case 'month':
                return '30 DAY';
            default:
                return '24 HOUR';
        }
    }
}