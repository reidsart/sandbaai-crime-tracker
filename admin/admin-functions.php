<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Add debugging
add_action('admin_notices', function() {
    echo '<div class="notice notice-info"><p>Admin functions file loaded at: ' . current_time('Y-m-d H:i:s') . '</p></div>';
});

// Define the admin menu and pages
add_action('admin_menu', 'sandcrime_create_admin_menu');

function sandcrime_create_admin_menu() {
    // Main menu
    add_menu_page(
        'Sandbaai Crime Tracker', // Page title
        'Crime Tracker',          // Menu title
        'manage_options',         // Capability
        'sandcrime-dashboard',    // Menu slug
        'sandcrime_dashboard_page', // Callback function
        'dashicons-chart-pie',    // Icon
        6                        // Position
    );

    // Dashboard submenu
    add_submenu_page(
        'sandcrime-dashboard',    // Parent slug
        'Dashboard',             // Page title
        'Dashboard',             // Menu title
        'manage_options',         // Capability
        'sandcrime-dashboard',    // Menu slug (same as parent)
        'sandcrime_dashboard_page' // Callback function
    );

    // Crime Reports submenu
    add_submenu_page(
        'sandcrime-dashboard',    // Parent slug
        'Crime Reports',          // Page title
        'Crime Reports',          // Menu title
        'manage_options',         // Capability
        'sandcrime-reports',      // Menu slug
        'sandcrime_reports_page'  // Callback function
    );

    // Security Groups submenu
    add_submenu_page(
        'sandcrime-dashboard',      // Parent slug
        'Security Groups',          // Page title
        'Security Groups',          // Menu title
        'manage_options',           // Capability
        'sandcrime-security-groups', // Menu slug
        'sandcrime_security_groups_page' // Callback function
    );

    // Settings submenu
    add_submenu_page(
        'sandcrime-dashboard',    // Parent slug
        'Settings',              // Page title
        'Settings',              // Menu title
        'manage_options',         // Capability
        'sandcrime-settings',     // Menu slug
        'sandcrime_settings_page' // Callback function
    );
}

// Dashboard Page
function sandcrime_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="card">
            <h2>Recent Reports</h2>
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'sandcrime_reports';
            $recent_reports = $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY date_time DESC LIMIT 5"
            );
            
            if ($recent_reports): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_reports as $report): ?>
                            <tr>
                                <td><?php echo esc_html($report->title); ?></td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($report->date_time))); ?></td>
                                <td><?php echo esc_html($report->result_status); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No reports found.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Reports Page
function sandcrime_reports_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_reports';
        $reports = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date_time DESC");
        
        if ($reports): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Date/Time</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?php echo esc_html($report->title); ?></td>
                            <td><?php echo esc_html($report->category); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($report->date_time))); ?></td>
                            <td><?php echo esc_html($report->location); ?></td>
                            <td><?php echo esc_html($report->result_status); ?></td>
                            <td>
                                <a href="?page=sandcrime-reports&action=view&id=<?php echo esc_attr($report->id); ?>" 
                                   class="button button-secondary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No reports found.</p>
        <?php endif; ?>
    </div>
    <?php
}

// Security Groups Page
function sandcrime_security_groups_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <!-- Add New Security Group Form -->
        <div class="card">
            <h2>Add New Security Group</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('sandcrime_security_group_action', 'sandcrime_security_group_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="title">Group Name</label></th>
                        <td><input type="text" id="title" name="title" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="contact_numbers">Contact Numbers</label></th>
                        <td><input type="text" id="contact_numbers" name="contact_numbers" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="email">Email</label></th>
                        <td><input type="email" id="email" name="email" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button('Add Security Group'); ?>
            </form>
        </div>

        <!-- List Existing Security Groups -->
        <h2>Existing Security Groups</h2>
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_groups';
        $groups = $wpdb->get_results("SELECT * FROM $table_name ORDER BY title ASC");
        
        if ($groups): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact Numbers</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td><?php echo esc_html($group->title); ?></td>
                            <td><?php echo esc_html($group->contact_numbers); ?></td>
                            <td><?php echo esc_html($group->email); ?></td>
                            <td>
                                <a href="?page=sandcrime-security-groups&action=edit&id=<?php echo esc_attr($group->id); ?>" 
                                   class="button button-secondary">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No security groups found.</p>
        <?php endif; ?>
    </div>
    <?php
}

// Settings Page
function sandcrime_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="post" action="options.php">
            <?php
                settings_fields('sandcrime_settings');
                do_settings_sections('sandcrime_settings');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sandcrime_email_notifications">Enable Email Notifications</label>
                    </th>
                    <td>
                        <input type="checkbox" id="sandcrime_email_notifications" 
                               name="sandcrime_settings[email_notifications]" 
                               value="1" <?php checked(1, get_option('sandcrime_settings')['email_notifications'] ?? 0); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sandcrime_notification_email">Notification Email</label>
                    </th>
                    <td>
                        <input type="email" id="sandcrime_notification_email" 
                               name="sandcrime_settings[notification_email]" 
                               value="<?php echo esc_attr(get_option('sandcrime_settings')['notification_email'] ?? ''); ?>" 
                               class="regular-text">
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'sandcrime_register_settings');
function sandcrime_register_settings() {
    register_setting('sandcrime_settings', 'sandcrime_settings');
}