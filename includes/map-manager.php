<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Map_Manager {
    /**
     * Initialize map functionality
     */
    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_map_assets'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_map_assets'));
    }

    /**
     * Enqueue map assets for frontend
     */
    public static function enqueue_map_assets() {
        // Leaflet CSS and JS
        wp_enqueue_style(
            'leaflet-css',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            array(),
            '1.9.4'
        );
        
        wp_enqueue_script(
            'leaflet-js',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            array(),
            '1.9.4',
            true
        );

        // Clustering plugin
        wp_enqueue_style(
            'leaflet-markercluster-css',
            'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css',
            array(),
            '1.4.1'
        );
        
        wp_enqueue_style(
            'leaflet-markercluster-default-css',
            'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css',
            array(),
            '1.4.1'
        );
        
        wp_enqueue_script(
            'leaflet-markercluster-js',
            'https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js',
            array('leaflet-js'),
            '1.4.1',
            true
        );

        // Draw plugin
        wp_enqueue_style(
            'leaflet-draw-css',
            'https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css',
            array(),
            '1.0.4'
        );
        
        wp_enqueue_script(
            'leaflet-draw-js',
            'https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js',
            array('leaflet-js'),
            '1.0.4',
            true
        );

        // Custom map script
        wp_enqueue_script(
            'sandcrime-map',
            SANDCRIME_PLUGIN_URL . 'assets/js/map.js',
            array('leaflet-js', 'leaflet-markercluster-js', 'leaflet-draw-js'),
            SANDCRIME_PLUGIN_VERSION,
            true
        );

        // Pass PHP variables to JavaScript
        wp_localize_script('sandcrime-map', 'sandcrimeMapData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sandcrime_map_nonce'),
            'defaultLat' => '-34.4125',  // Sandbaai default coordinates
            'defaultLng' => '19.2280',
            'defaultZoom' => 14
        ));
    }

    /**
     * Enqueue map assets for admin
     */
    public static function enqueue_admin_map_assets($hook) {
        if (!in_array($hook, array('sandcrime_page_sandcrime-reports', 'sandcrime_page_sandcrime-security-groups'))) {
            return;
        }

        self::enqueue_map_assets();

        // Admin-specific map script
        wp_enqueue_script(
            'sandcrime-admin-map',
            SANDCRIME_PLUGIN_URL . 'assets/js/admin-map.js',
            array('sandcrime-map'),
            SANDCRIME_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Get reports for map
     */
    public static function get_map_reports($filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_reports';

        $where = array();
        $where_values = array();

        if (!empty($filters['status'])) {
            $where[] = 'result_status = %s';
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $where_values[] = $filters['category'];
        }

        if (!empty($filters['date_start'])) {
            $where[] = 'date_time >= %s';
            $where_values[] = $filters['date_start'];
        }

        if (!empty($filters['date_end'])) {
            $where[] = 'date_time <= %s';
            $where_values[] = $filters['date_end'];
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = $wpdb->prepare(
            "SELECT id, title, category, date_time, location, result_status, 
                    ST_X(coordinates) as lat, ST_Y(coordinates) as lng
             FROM $table_name
             $where_sql
             ORDER BY date_time DESC",
            $where_values
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get security group coverage areas
     */
    public static function get_coverage_areas() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_groups';

        return $wpdb->get_results("
            SELECT id, title, 
                   ST_AsGeoJSON(coverage_area) as coverage_geojson
            FROM $table_name
            WHERE coverage_area IS NOT NULL
        ");
    }

    /**
     * Save report coordinates
     */
    public static function save_coordinates($report_id, $lat, $lng) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_reports';

        return $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET coordinates = ST_PointFromText('POINT(%f %f)')
             WHERE id = %d",
            $lat,
            $lng,
            $report_id
        ));
    }

    /**
     * Save security group coverage area
     */
    public static function save_coverage_area($group_id, $geojson) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_groups';

        return $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET coverage_area = ST_GeomFromGeoJSON(%s)
             WHERE id = %d",
            $geojson,
            $group_id
        ));
    }

    /**
     * Find security groups for location
     */
    public static function find_groups_for_location($lat, $lng) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_groups';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, title 
             FROM $table_name 
             WHERE ST_Contains(coverage_area, ST_PointFromText('POINT(%f %f)'))",
            $lat,
            $lng
        ));
    }
}

// Initialize map manager
SandCrime_Map_Manager::init();

// AJAX handlers for map functionality
add_action('wp_ajax_get_map_reports', function() {
    check_ajax_referer('sandcrime_map_nonce', 'nonce');

    $filters = array(
        'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
        'category' => isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '',
        'date_start' => isset($_POST['date_start']) ? sanitize_text_field($_POST['date_start']) : '',
        'date_end' => isset($_POST['date_end']) ? sanitize_text_field($_POST['date_end']) : ''
    );

    $reports = SandCrime_Map_Manager::get_map_reports($filters);
    wp_send_json_success($reports);
});

add_action('wp_ajax_get_coverage_areas', function() {
    check_ajax_referer('sandcrime_map_nonce', 'nonce');
    
    $areas = SandCrime_Map_Manager::get_coverage_areas();
    wp_send_json_success($areas);
});

add_action('wp_ajax_save_report_location', function() {
    check_ajax_referer('sandcrime_map_nonce', 'nonce');

    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
    $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : 0;
    $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : 0;

    if (!$report_id || !$lat || !$lng) {
        wp_send_json_error('Invalid parameters');
    }

    $result = SandCrime_Map_Manager::save_coordinates($report_id, $lat, $lng);
    
    if ($result) {
        $groups = SandCrime_Map_Manager::find_groups_for_location($lat, $lng);
        wp_send_json_success(array(
            'message' => 'Location saved successfully',
            'groups' => $groups
        ));
    } else {
        wp_send_json_error('Could not save location');
    }
});

add_action('wp_ajax_save_coverage_area', function() {
    check_ajax_referer('sandcrime_map_nonce', 'nonce');

    $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
    $geojson = isset($_POST['geojson']) ? $_POST['geojson'] : '';

    if (!$group_id || !$geojson) {
        wp_send_json_error('Invalid parameters');
    }

    $result = SandCrime_Map_Manager::save_coverage_area($group_id, $geojson);
    
    if ($result) {
        wp_send_json_success('Coverage area saved successfully');
    } else {
        wp_send_json_error('Could not save coverage area');
    }
});