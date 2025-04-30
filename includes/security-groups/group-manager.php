<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Group_Manager {
    public static function init() {
        add_shortcode('sandcrime_groups', array(__CLASS__, 'render_groups_page'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('init', array(__CLASS__, 'register_group_post_type'));
    }

    public static function enqueue_assets() {
        wp_enqueue_style(
            'sandcrime-groups',
            SANDCRIME_PLUGIN_URL . 'assets/css/security-groups.css',
            array(),
            SANDCRIME_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'sandcrime-groups',
            SANDCRIME_PLUGIN_URL . 'assets/js/security-groups.js',
            array('jquery'),
            SANDCRIME_PLUGIN_VERSION,
            true
        );

        wp_localize_script('sandcrime-groups', 'sandcrimeGroups', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sandcrime_groups_nonce')
        ));
    }

    public static function register_group_post_type() {
        register_post_type('security_group', array(
            'labels' => array(
                'name' => 'Security Groups',
                'singular_name' => 'Security Group',
                'add_new' => 'Add New Group',
                'add_new_item' => 'Add New Security Group',
                'edit_item' => 'Edit Security Group',
                'view_item' => 'View Security Group',
                'search_items' => 'Search Security Groups',
                'not_found' => 'No security groups found',
                'not_found_in_trash' => 'No security groups found in trash'
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-groups',
            'supports' => array('title', 'editor', 'thumbnail'),
            'rewrite' => array('slug' => 'security-groups'),
            'show_in_rest' => true,
            'capability_type' => 'post',
            'map_meta_cap' => true
        ));

        register_taxonomy('group_category', 'security_group', array(
            'labels' => array(
                'name' => 'Group Categories',
                'singular_name' => 'Group Category'
            ),
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'group-category')
        ));
    }

    public static function get_group_details($group_id) {
        global $wpdb;
        
        $group = $wpdb->get_row($wpdb->prepare("
            SELECT g.*, 
                   COUNT(DISTINCT m.id) as member_count,
                   COUNT(DISTINCT r.id) as report_count
            FROM {$wpdb->prefix}sandcrime_groups g
            LEFT JOIN {$wpdb->prefix}sandcrime_group_members m ON g.id = m.group_id
            LEFT JOIN {$wpdb->prefix}sandcrime_reports r ON FIND_IN_SET(g.id, r.security_groups)
            WHERE g.id = %d
            GROUP BY g.id
        ", $group_id));

        if (!$group) {
            return false;
        }

        // Get group contacts
        $group->contacts = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}sandcrime_group_phones
            WHERE group_id = %d
            ORDER BY sort_order ASC
        ", $group_id));

        // Get group admins
        $group->admins = $wpdb->get_results($wpdb->prepare("
            SELECT m.*, u.display_name, u.user_email
            FROM {$wpdb->prefix}sandcrime_group_members m
            JOIN {$wpdb->users} u ON m.user_id = u.ID
            WHERE m.group_id = %d AND m.role = 'admin'
        ", $group_id));

        // Get coverage area
        $group->coverage_area = $wpdb->get_var($wpdb->prepare("
            SELECT ST_AsGeoJSON(coverage_area)
            FROM {$wpdb->prefix}sandcrime_groups
            WHERE id = %d
        ", $group_id));

        return $group;
    }

    public static function add_group_member($group_id, $user_id, $role = 'member') {
        global $wpdb;

        // Check if already a member
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}sandcrime_group_members
            WHERE group_id = %d AND user_id = %d
        ", $group_id, $user_id));

        if ($existing) {
            return false;
        }

        // Add member
        $result = $wpdb->insert(
            $wpdb->prefix . 'sandcrime_group_members',
            array(
                'group_id' => $group_id,
                'user_id' => $user_id,
                'role' => $role,
                'added_by' => get_current_user_id(),
                'added_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%d', '%s')
        );

        if ($result) {
            do_action('sandcrime_group_member_added', $group_id, $user_id, $role);
            return true;
        }

        return false;
    }

    public static function remove_group_member($group_id, $user_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . 'sandcrime_group_members',
            array(
                'group_id' => $group_id,
                'user_id' => $user_id
            ),
            array('%d', '%d')
        );

        if ($result) {
            do_action('sandcrime_group_member_removed', $group_id, $user_id);
            return true;
        }

        return false;
    }

    public static function update_member_role($group_id, $user_id, $new_role) {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'sandcrime_group_members',
            array('role' => $new_role),
            array('group_id' => $group_id, 'user_id' => $user_id),
            array('%s'),
            array('%d', '%d')
        );

        if ($result) {
            do_action('sandcrime_group_member_role_updated', $group_id, $user_id, $new_role);
            return true;
        }

        return false;
    }

    public static function update_group_coverage($group_id, $geojson) {
        global $wpdb;

        $result = $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->prefix}sandcrime_groups
            SET coverage_area = ST_GeomFromGeoJSON(%s)
            WHERE id = %d
        ", $geojson, $group_id));

        if ($result) {
            do_action('sandcrime_group_coverage_updated', $group_id, $geojson);
            return true;
        }

        return false;
    }

    public static function get_group_reports($group_id, $filters = array()) {
        global $wpdb;

        $where = array("FIND_IN_SET(%d, security_groups)");
        $where_values = array($group_id);

        if (!empty($filters['status'])) {
            $where[] = "result_status = %s";
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['date_start'])) {
            $where[] = "date_time >= %s";
            $where_values[] = $filters['date_start'];
        }

        if (!empty($filters['date_end'])) {
            $where[] = "date_time <= %s";
            $where_values[] = $filters['date_end'];
        }

        $where_sql = implode(' AND ', $where);

        return $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}sandcrime_reports
            WHERE $where_sql
            ORDER BY date_time DESC
        ", $where_values));
    }

    public static function can_manage_group($group_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        global $wpdb;
        
        $role = $wpdb->get_var($wpdb->prepare("
            SELECT role
            FROM {$wpdb->prefix}sandcrime_group_members
            WHERE group_id = %d AND user_id = %d
        ", $group_id, $user_id));

        return $role === 'admin';
    }
}

// Initialize the group manager
SandCrime_Group_Manager::init();

// AJAX handlers for group management
add_action('wp_ajax_join_security_group', function() {
    check_ajax_referer('sandcrime_groups_nonce', 'nonce');

    $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
    if (!$group_id) {
        wp_send_json_error('Invalid group ID');
    }

    $result = SandCrime_Group_Manager::add_group_member($group_id, get_current_user_id());
    
    if ($result) {
        wp_send_json_success('Successfully joined the group');
    } else {
        wp_send_json_error('Could not join group');
    }
});

add_action('wp_ajax_leave_security_group', function() {
    check_ajax_referer('sandcrime_groups_nonce', 'nonce');

    $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
    if (!$group_id) {
        wp_send_json_error('Invalid group ID');
    }

    $result = SandCrime_Group_Manager::remove_group_member($group_id, get_current_user_id());
    
    if ($result) {
        wp_send_json_success('Successfully left the group');
    } else {
        wp_send_json_error('Could not leave group');
    }
});