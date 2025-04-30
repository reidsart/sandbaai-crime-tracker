<?php
if (!defined('ABSPATH')) {
    exit;
}

// Load admin components
$admin_components = [
    'dashboard/dashboard-page.php',
    'reports/reports-page.php',
    'reports/report-edit.php',
    'reports/report-list.php',
    'security-groups/security-groups-page.php',
    'security-groups/group-form.php',
    'security-groups/group-list.php',
    'security-groups/group-members.php',
    'security-groups/group-handlers.php',
    'settings/settings-page.php'
];

foreach ($admin_components as $component) {
    $file_path = SANDCRIME_PLUGIN_DIR . 'admin/' . $component;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        add_action('admin_notices', function() use ($component) {
            echo '<div class="notice notice-error"><p>Sandbaai Crime Tracker: Missing admin component: ' . 
                 esc_html($component) . '</p></div>';
        });
    }
}

// Create admin menu
add_action('admin_menu', 'sandcrime_create_admin_menu');

function sandcrime_create_admin_menu() {
    // Main menu
    add_menu_page(
        'Sandbaai Crime Tracker',
        'Crime Tracker',
        'manage_options',
        'sandcrime-dashboard',
        'sandcrime_dashboard_page',
        'dashicons-shield',
        6
    );

    // Submenus
    $submenus = [
        ['Dashboard', 'sandcrime-dashboard', 'sandcrime_dashboard_page'],
        ['Crime Reports', 'sandcrime-reports', 'sandcrime_reports_page'],
        ['Security Groups', 'sandcrime-security-groups', 'sandcrime_security_groups_page'],
        ['Settings', 'sandcrime-settings', 'sandcrime_settings_page']
    ];

    foreach ($submenus as $submenu) {
        add_submenu_page(
            'sandcrime-dashboard',
            $submenu[0],
            $submenu[0],
            'manage_options',
            $submenu[1],
            $submenu[2]
        );
    }
}

// Register settings
add_action('admin_init', 'sandcrime_register_settings');
function sandcrime_register_settings() {
    register_setting('sandcrime_settings', 'sandcrime_settings');
}