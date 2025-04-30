<?php
if (!defined('ABSPATH')) {
    exit;
}

function sandcrime_reports_page() {
    // Handle viewing/editing single report
    if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
        require_once SANDCRIME_PLUGIN_DIR . 'admin/reports/report-edit.php';
        sandcrime_report_edit_page(intval($_GET['id']));
        return;
    }

    // Display reports list
    require_once SANDCRIME_PLUGIN_DIR . 'admin/reports/report-list.php';
    sandcrime_report_list_page();
}