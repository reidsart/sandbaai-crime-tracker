<?php
if (!defined('ABSPATH')) {
    exit;
}

function sandcrime_get_group_data() {
    check_ajax_referer('sandcrime_security_groups', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied.', 'sandcrime'));
    }

    $group_id = isset($_POST['group_id']) ? absint($_POST['group_id']) : 0;
    if (!$group_id) {
        wp_send_json_error(__('Invalid group ID.', 'sandcrime'));
    }

    global $wpdb;
    $group = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sandcrime_security_groups WHERE id = %d",
        $group_id
    ));

    if (!$group) {
        wp_send_json_error(__('Group not found.', 'sandcrime'));
    }

    wp_send_json_success($group);
}
add_action('wp_ajax_sandcrime_get_group_data', 'sandcrime_get_group_data');

function sandcrime_get_group_members() {
    check_ajax_referer('sandcrime_security_groups', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied.', 'sandcrime'));
    }

    $group_id = isset($_POST['group_id']) ? absint($_POST['group_id']) : 0;
    if (!$group_id) {
        wp_send_json_error(__('Invalid group ID.', 'sandcrime'));
    }

    global $wpdb;
    $members = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.display_name, u.user_email
        FROM {$wpdb->users} u
        JOIN {$wpdb->prefix}sandcrime_group_members gm ON u.ID = gm.user_id
        WHERE gm.group_id = %d
        ORDER BY u.display_name ASC",
        $group_id
    ));

    wp_send_json_success($members);
}
add_action('wp_ajax_sandcrime_get_group_members', 'sandcrime_get_group_members');

function sandcrime_search_users() {
    check_ajax_referer('sandcrime_security_groups', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied.', 'sandcrime'));
    }

    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $group_id = isset($_POST['group_id']) ? absint($_POST['group_id']) : 0;

    $args = array(
        'search' => '*' . $search . '*',
        'search_columns' => array('user_login', 'user_email', 'display_name'),
        'number' => 10,
        'orderby' => 'display_name',
        'order' => 'ASC'
    );

    $users = get_users($args);
    $results = array();

    foreach ($users as $user) {
        $results[] = array(
            'id' => $user->ID,
            'text' => sprintf(
                '%s (%s)',
                $user->display_name,
                $user->user_email
            )
        );
    }

    wp_send_json_success($results);
}
add_action('wp_ajax_sandcrime_search_users', 'sandcrime_search_users');