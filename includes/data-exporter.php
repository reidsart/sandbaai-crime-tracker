<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Data_Exporter {
    /**
     * Export reports to CSV
     */
    public static function export_reports_csv($filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_reports';

        // Build query
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
            "SELECT * FROM $table_name $where_sql ORDER BY date_time DESC",
            $where_values
        );

        $reports = $wpdb->get_results($query);

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=crime-reports-' . date('Y-m-d') . '.csv');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV headers
        fputcsv($output, array(
            'ID',
            'Title',
            'Description',
            'Category',
            'Date/Time',
            'Location',
            'Status',
            'Reported By',
            'Last Edited By',
            'Last Edited At'
        ));

        // Write data
        foreach ($reports as $report) {
            fputcsv($output, array(
                $report->id,
                $report->title,
                $report->description,
                $report->category,
                $report->date_time,
                $report->location,
                $report->result_status,
                $report->user_login ?: 'Anonymous',
                $report->last_edited_by,
                $report->last_edited_at
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Export security groups to CSV
     */
    public static function export_groups_csv() {
        global $wpdb;
        $groups_table = $wpdb->prefix . 'sandcrime_groups';
        $phones_table = $wpdb->prefix . 'sandcrime_group_phones';
        $members_table = $wpdb->prefix . 'sandcrime_group_members';

        $groups = $wpdb->get_results("
            SELECT g.*,
                   GROUP_CONCAT(DISTINCT p.phone_number ORDER BY p.sort_order SEPARATOR '|') as phone_numbers,
                   COUNT(DISTINCT m.id) as member_count
            FROM $groups_table g
            LEFT JOIN $phones_table p ON g.id = p.group_id
            LEFT JOIN $members_table m ON g.id = m.group_id
            GROUP BY g.id
            ORDER BY g.title
        ");

        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=security-groups-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV headers
        fputcsv($output, array(
            'ID',
            'Name',
            'Email',
            'Phone Numbers',
            'Website',
            'Address',
            'Member Count',
            'Description'
        ));

        // Write data
        foreach ($groups as $group) {
            fputcsv($output, array(
                $group->id,
                $group->title,
                $group->email,
                $group->phone_numbers,
                $group->website,
                $group->address,
                $group->member_count,
                $group->description
            ));
        }

        fclose($output);
        exit;
    }
}

// Add export handlers
add_action('admin_post_export_reports_csv', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    $filters = array(
        'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
        'category' => isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '',
        'date_start' => isset($_GET['date_start']) ? sanitize_text_field($_GET['date_start']) : '',
        'date_end' => isset($_GET['date_end']) ? sanitize_text_field($_GET['date_end']) : ''
    );

    SandCrime_Data_Exporter::export_reports_csv($filters);
});

add_action('admin_post_export_groups_csv', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    SandCrime_Data_Exporter::export_groups_csv();
});