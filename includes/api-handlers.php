<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_API_Handlers {
    /**
     * Register REST API endpoints
     */
    public static function register_endpoints() {
        add_action('rest_api_init', function() {
            register_rest_route('sandcrime/v1', '/reports', array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_reports'),
                'permission_callback' => array(__CLASS__, 'check_api_permissions')
            ));
            
            register_rest_route('sandcrime/v1', '/reports', array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'create_report'),
                'permission_callback' => array(__CLASS__, 'check_api_permissions')
            ));
            
            register_rest_route('sandcrime/v1', '/security-groups', array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_security_groups'),
                'permission_callback' => array(__CLASS__, 'check_api_permissions')
            ));
        });
    }

    /**
     * Check API permissions
     */
    public static function check_api_permissions() {
        return current_user_can('edit_posts');
    }

    /**
     * Get reports endpoint
     */
    public static function get_reports($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_reports';
        
        $params = $request->get_params();
        $page = isset($params['page']) ? absint($params['page']) : 1;
        $per_page = isset($params['per_page']) ? absint($params['per_page']) : 10;
        $offset = ($page - 1) * $per_page;
        
        $where = array();
        $where_values = array();
        
        if (!empty($params['status'])) {
            $where[] = 'result_status = %s';
            $where_values[] = sanitize_text_field($params['status']);
        }
        
        if (!empty($params['category'])) {
            $where[] = 'category = %s';
            $where_values[] = sanitize_text_field($params['category']);
        }
        
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name $where_sql ORDER BY date_time DESC LIMIT %d OFFSET %d",
            array_merge($where_values, array($per_page, $offset))
        );
        
        $reports = $wpdb->get_results($query);
        
        return new WP_REST_Response($reports, 200);
    }

    /**
     * Create report endpoint
     */
    public static function create_report($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_reports';
        
        $params = $request->get_params();
        
        // Validate required fields
        $required_fields = array('title', 'description', 'category', 'location');
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new WP_Error(
                    'missing_field',
                    sprintf('Missing required field: %s', $field),
                    array('status' => 400)
                );
            }
        }
        
        $data = array(
            'title' => sanitize_text_field($params['title']),
            'description' => sanitize_textarea_field($params['description']),
            'category' => sanitize_text_field($params['category']),
            'location' => sanitize_text_field($params['location']),
            'date_time' => current_time('mysql'),
            'result_status' => 'Pending Review',
            'user_id' => get_current_user_id()
        );
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            return new WP_Error(
                'db_error',
                'Could not create report',
                array('status' => 500)
            );
        }
        
        $report_id = $wpdb->insert_id;
        
        // Send notifications
        SandCrime_Utilities::send_notifications('new_report', array_merge(
            $data,
            array('id' => $report_id)
        ));
        
        return new WP_REST_Response(
            array('id' => $report_id, 'message' => 'Report created successfully'),
            201
        );
    }

    /**
     * Get security groups endpoint
     */
    public static function get_security_groups($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_groups';
        
        $groups = $wpdb->get_results("
            SELECT g.*, 
                   COUNT(DISTINCT m.id) as member_count,
                   GROUP_CONCAT(DISTINCT p.phone_number ORDER BY p.sort_order) as phone_numbers
            FROM $table_name g
            LEFT JOIN {$wpdb->prefix}sandcrime_group_members m ON g.id = m.group_id
            LEFT JOIN {$wpdb->prefix}sandcrime_group_phones p ON g.id = p.group_id
            GROUP BY g.id
            ORDER BY g.title ASC
        ");
        
        return new WP_REST_Response($groups, 200);
    }
}

// Initialize API endpoints
add_action('init', array('SandCrime_API_Handlers', 'register_endpoints'));