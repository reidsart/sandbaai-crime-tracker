<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function auto_approve_security_group_reports($report_id, $user_id) {
    global $wpdb;
    
    // Check if user is a member of any security group
    $is_security_member = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}sandcrime_group_members
        WHERE user_id = %d
    ", $user_id));

    if ($is_security_member > 0) {
        // Auto-approve the report
        $wpdb->update(
            $wpdb->prefix . 'sandcrime_reports',
            array('result_status' => 'Approved'),
            array('id' => $report_id),
            array('%s'),
            array('%d')
        );
        return true;
    }
    
    return false;
}

// Hook this function to your report submission process
add_action('sandcrime_report_submitted', 'auto_approve_security_group_reports', 10, 2);

// Function to check if a user is a member of any security group
function is_security_group_member($user_id) {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}sandcrime_group_members
        WHERE user_id = %d
    ", $user_id));
}

// Function to get user's security groups
function get_user_security_groups($user_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("
        SELECT g.*, m.role
        FROM {$wpdb->prefix}sandcrime_groups g
        JOIN {$wpdb->prefix}sandcrime_group_members m ON g.id = m.group_id
        WHERE m.user_id = %d
        ORDER BY g.title ASC
    ", $user_id));
}