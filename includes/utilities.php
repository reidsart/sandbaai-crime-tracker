<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!function_exists('sandcrime_render_security_groups_options')) {
    function sandcrime_render_security_groups_options() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_groups';
        $groups = $wpdb->get_results("SELECT id, title FROM $table_name");

        foreach ($groups as $group) {
            echo '<option value="' . esc_attr($group->id) . '">' . esc_html($group->title) . '</option>';
        }
    }
}