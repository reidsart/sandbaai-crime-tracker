<?php
// Report Approval Functions

add_action('admin_menu', 'sandcrime_report_approval_menu');
function sandcrime_report_approval_menu() {
    add_submenu_page(
        'sandcrime_dashboard',
        'Approve Crime Reports',
        'Approve Reports',
        'manage_options',
        'sandcrime_report_approval',
        'sandcrime_report_approval_page'
    );
}

function sandcrime_report_approval_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_reports';

    if (isset($_GET['approve'])) {
        $id = intval($_GET['approve']);
        $wpdb->update($table_name, ['result_status' => 'Approved'], ['id' => $id]);
        echo '<div class="updated"><p>Report approved successfully!</p></div>';
    }

    if (isset($_GET['reject'])) {
        $id = intval($_GET['reject']);
        $wpdb->update($table_name, ['result_status' => 'Rejected'], ['id' => $id]);
        echo '<div class="updated"><p>Report rejected successfully!</p></div>';
    }

    $reports = $wpdb->get_results("SELECT * FROM $table_name WHERE result_status = 'Pending Review'");
    ?>
    <div class="wrap">
        <h1>Approve Crime Reports</h1>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><?php echo $report->id; ?></td>
                        <td><?php echo esc_html($report->title); ?></td>
                        <td><?php echo esc_html($report->description); ?></td>
                        <td><?php echo esc_html($report->result_status); ?></td>
                        <td>
                            <a href="?page=sandcrime_report_approval&approve=<?php echo $report->id; ?>">Approve</a> |
                            <a href="?page=sandcrime_report_approval&reject=<?php echo $report->id; ?>">Reject</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>