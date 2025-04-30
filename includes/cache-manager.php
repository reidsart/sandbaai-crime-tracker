<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Cache_Manager {
    private static $cache_group = 'sandcrime';
    private static $cache_time = 3600; // 1 hour default

    /**
     * Initialize cache manager
     */
    public static function init() {
        add_action('save_post', array(__CLASS__, 'flush_cache'));
        add_action('deleted_post', array(__CLASS__, 'flush_cache'));
        add_action('sandcrime_report_updated', array(__CLASS__, 'flush_report_cache'));
        add_action('sandcrime_group_updated', array(__CLASS__, 'flush_group_cache'));
    }

    /**
     * Get cached data
     */
    public static function get($key, $callback = null, $expiration = null) {
        $cache_key = self::generate_key($key);
        $data = wp_cache_get($cache_key, self::$cache_group);

        if ($data === false && is_callable($callback)) {
            $data = $callback();
            self::set($key, $data, $expiration);
        }

        return $data;
    }

    /**
     * Set cached data
     */
    public static function set($key, $data, $expiration = null) {
        $cache_key = self::generate_key($key);
        $exp = $expiration ?: self::$cache_time;
        wp_cache_set($cache_key, $data, self::$cache_group, $exp);
    }

    /**
     * Delete cached item
     */
    public static function delete($key) {
        $cache_key = self::generate_key($key);
        wp_cache_delete($cache_key, self::$cache_group);
    }

    /**
     * Generate cache key
     */
    private static function generate_key($key) {
        return 'sandcrime_' . md5(serialize($key));
    }

    /**
     * Flush all plugin cache
     */
    public static function flush_cache() {
        wp_cache_flush();
    }

    /**
     * Flush report specific cache
     */
    public static function flush_report_cache($report_id) {
        self::delete('report_' . $report_id);
        self::delete('recent_reports');
        self::delete('report_stats');
    }

    /**
     * Flush group specific cache
     */
    public static function flush_group_cache($group_id) {
        self::delete('group_' . $group_id);
        self::delete('all_groups');
        self::delete('group_stats');
    }

    /**
     * Cache reports query
     */
    public static function get_cached_reports($args = array()) {
        $cache_key = 'reports_' . md5(serialize($args));
        
        return self::get($cache_key, function() use ($args) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'sandcrime_reports';
            
            $where = array();
            $where_values = array();
            
            if (!empty($args['status'])) {
                $where[] = 'result_status = %s';
                $where_values[] = $args['status'];
            }
            
            if (!empty($args['category'])) {
                $where[] = 'category = %s';
                $where_values[] = $args['category'];
            }
            
            $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $limit = isset($args['per_page']) ? absint($args['per_page']) : 10;
            $offset = isset($args['page']) ? (absint($args['page']) - 1) * $limit : 0;
            
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name $where_sql ORDER BY date_time DESC LIMIT %d OFFSET %d",
                array_merge($where_values, array($limit, $offset))
            );
            
            return $wpdb->get_results($query);
        });
    }

    /**
     * Cache security groups query
     */
    public static function get_cached_groups() {
        return self::get('all_groups', function() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'sandcrime_groups';
            
            return $wpdb->get_results("
                SELECT g.*, 
                       COUNT(DISTINCT m.id) as member_count,
                       GROUP_CONCAT(DISTINCT p.phone_number ORDER BY p.sort_order) as phone_numbers
                FROM $table_name g
                LEFT JOIN {$wpdb->prefix}sandcrime_group_members m ON g.id = m.group_id
                LEFT JOIN {$wpdb->prefix}sandcrime_group_phones p ON g.id = p.group_id
                GROUP BY g.id
                ORDER BY g.title ASC
            ");
        });
    }

    /**
     * Cache statistics
     */
    public static function get_cached_stats() {
        return self::get('system_stats', function() {
            global $wpdb;
            
            $stats = array(
                'total_reports' => $wpdb->get_var("
                    SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_reports
                "),
                'pending_reports' => $wpdb->get_var("
                    SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_reports 
                    WHERE result_status = 'Pending Review'
                "),
                'total_groups' => $wpdb->get_var("
                    SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_groups
                "),
                'total_members' => $wpdb->get_var("
                    SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_group_members
                "),
                'reports_today' => $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_reports 
                    WHERE DATE(date_time) = %s",
                    current_time('Y-m-d')
                )),
                'reports_this_month' => $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_reports 
                    WHERE MONTH(date_time) = %d AND YEAR(date_time) = %d",
                    current_time('m'),
                    current_time('Y')
                ))
            );
            
            return $stats;
        }, 1800); // Cache for 30 minutes
    }

    /**
     * Preload common data
     */
    public static function preload_common_data() {
        // Preload recent reports
        self::get_cached_reports(array(
            'per_page' => 5,
            'page' => 1
        ));

        // Preload security groups
        self::get_cached_groups();

        // Preload statistics
        self::get_cached_stats();
    }

    /**
     * Get transient timeout
     */
    private static function get_transient_timeout($transient) {
        global $wpdb;
        $timeout = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value 
             FROM $wpdb->options 
             WHERE option_name = %s",
            '_transient_timeout_' . $transient
        ));
        
        return $timeout ? ($timeout - time()) : 0;
    }

    /**
     * Clean expired transients
     */
    public static function clean_expired_transients() {
        global $wpdb;
        
        $wpdb->query("
            DELETE a, b FROM $wpdb->options a, $wpdb->options b
            WHERE a.option_name LIKE '%_transient_timeout_sandcrime%'
            AND a.option_name NOT LIKE '%_transient_timeout_sandcrime_running%'
            AND b.option_name = REPLACE(a.option_name, '_timeout', '')
            AND a.option_value < UNIX_TIMESTAMP()
        ");
    }
}

// Initialize cache manager
SandCrime_Cache_Manager::init();

// Add cleanup schedule
add_action('wp', function() {
    if (!wp_next_scheduled('sandcrime_cache_cleanup')) {
        wp_schedule_event(time(), 'daily', 'sandcrime_cache_cleanup');
    }
});

add_action('sandcrime_cache_cleanup', array('SandCrime_Cache_Manager', 'clean_expired_transients'));

// Preload data on admin page load
add_action('admin_init', array('SandCrime_Cache_Manager', 'preload_common_data'));