<?php
if (!defined('ABSPATH')) {
    exit;
}

// AJAX handler for updating member roles
add_action('wp_ajax_update_member_role', 'sandcrime_update_member_role');
function sandcrime_update_member_role() {
    check_ajax_referer('bulk_member_actions', 'nonce');
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sandcrime_group_members';
    
    $user_id = intval($_POST['user_id']);
    $group_id = intval($_POST['group_id']);
    $role = sanitize_text_field($_POST['role']);
    
    $result = $wpdb->update(
        $members_table,
        ['role' => $role],
        ['user_id' => $user_id, 'group_id' => $group_id]
    );
    
    wp_send_json_success([
        'message' => 'Role updated successfully',
        'timestamp' => current_time('mysql')
    ]);
}

// AJAX handler for bulk member actions
add_action('wp_ajax_bulk_update_members', 'sandcrime_bulk_update_members');
function sandcrime_bulk_update_members() {
    check_ajax_referer('bulk_member_actions', 'nonce');
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sandcrime_group_members';
    
    $group_id = intval($_POST['group_id']);
    $member_ids = array_map('intval', $_POST['member_ids']);
    $action = sanitize_text_field($_POST['bulk_action']);
    
    $results = ['success' => [], 'failed' => []];
    
    switch ($action) {
        case 'remove':
            foreach ($member_ids as $user_id) {
                $result = $wpdb->delete(
                    $members_table,
                    ['user_id' => $user_id, 'group_id' => $group_id]
                );
                
                if ($result) {
                    $results['success'][] = $user_id;
                } else {
                    $results['failed'][] = $user_id;
                }
            }
            break;
            
        case 'make_member':
        case 'make_admin':
            $role = ($action === 'make_member') ? 'member' : 'admin';
            foreach ($member_ids as $user_id) {
                $result = $wpdb->update(
                    $members_table,
                    ['role' => $role],
                    ['user_id' => $user_id, 'group_id' => $group_id]
                );
                
                if ($result !== false) {
                    $results['success'][] = $user_id;
                } else {
                    $results['failed'][] = $user_id;
                }
            }
            break;
    }
    
    wp_send_json_success([
        'results' => $results,
        'timestamp' => current_time('mysql')
    ]);
}

// AJAX handler for adding multiple members
add_action('wp_ajax_add_group_members', 'sandcrime_add_group_members');
function sandcrime_add_group_members() {
    check_ajax_referer('add_group_members', 'nonce');
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sandcrime_group_members';
    
    $group_id = intval($_POST['group_id']);
    $user_ids = array_map('intval', $_POST['user_ids']);
    $role = sanitize_text_field($_POST['role']);
    $current_user_id = get_current_user_id();
    
    $results = ['success' => [], 'failed' => []];
    
    foreach ($user_ids as $user_id) {
        // Check if user is already a member
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $members_table WHERE group_id = %d AND user_id = %d",
            $group_id,
            $user_id
        ));
        
        if ($existing) {
            $results['failed'][] = $user_id;
            continue;
        }
        
        $result = $wpdb->insert(
            $members_table,
            [
                'group_id' => $group_id,
                'user_id' => $user_id,
                'role' => $role,
                'added_by' => $current_user_id,
                'added_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%d', '%s']
        );
        
        if ($result) {
            $results['success'][] = $user_id;
        } else {
            $results['failed'][] = $user_id;
        }
    }
    
    // Get updated member list HTML
    ob_start();
    display_group_members($group_id);
    $member_list_html = ob_get_clean();
    
    wp_send_json_success([
        'results' => $results,
        'memberListHtml' => $member_list_html,
        'timestamp' => current_time('mysql')
    ]);
}

// AJAX handler for member search
add_action('wp_ajax_search_users_for_group', 'sandcrime_search_users_for_group');
function sandcrime_search_users_for_group() {
    check_ajax_referer('search_users_nonce', 'nonce');
    
    $search_term = sanitize_text_field($_POST['term']);
    $group_id = intval($_POST['group_id']);
    
    global $wpdb;
    
    // Get existing member IDs
    $existing_members = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->prefix}sandcrime_group_members WHERE group_id = %d",
        $group_id
    ));
    
    // Search users
    $users = get_users([
        'search' => "*{$search_term}*",
        'search_columns' => ['user_login', 'user_email', 'display_name'],
        'exclude' => $existing_members,
        'number' => 10
    ]);
    
    $results = array_map(function($user) {
        return [
            'id' => $user->ID,
            'text' => sprintf(
                '%s (%s) - %s',
                $user->display_name,
                $user->user_login,
                $user->user_email
            )
        ];
    }, $users);
    
    wp_send_json_success([
        'results' => $results,
        'timestamp' => current_time('mysql')
    ]);
}