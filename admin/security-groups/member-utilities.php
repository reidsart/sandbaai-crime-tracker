<?php
if (!defined('ABSPATH')) {
    exit;
}

function get_group_member_count($group_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_group_members WHERE group_id = %d",
        $group_id
    ));
}

function get_group_admins($group_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT m.*, u.display_name, u.user_login, u.user_email
         FROM {$wpdb->prefix}sandcrime_group_members m
         JOIN {$wpdb->users} u ON m.user_id = u.ID
         WHERE m.group_id = %d AND m.role = 'admin'
         ORDER BY u.display_name",
        $group_id
    ));
}

function is_group_admin($user_id, $group_id) {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_group_members
         WHERE group_id = %d AND user_id = %d AND role = 'admin'",
        $group_id,
        $user_id
    ));
}

function get_user_groups($user_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT g.*, m.role
         FROM {$wpdb->prefix}sandcrime_groups g
         JOIN {$wpdb->prefix}sandcrime_group_members m ON g.id = m.group_id
         WHERE m.user_id = %d
         ORDER BY g.title",
        $user_id
    ));
}