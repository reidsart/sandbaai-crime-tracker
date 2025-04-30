<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Add debugging
add_action('admin_notices', function() {
    echo '<div class="notice notice-info is-dismissible"><p>Admin functions file loaded at: ' . current_time('Y-m-d H:i:s') . '</p></div>';
});

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

    // Dashboard submenu (same as main menu)
    add_submenu_page(
        'sandcrime-dashboard',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'sandcrime-dashboard',
        'sandcrime_dashboard_page'
    );

    // Reports submenu
    add_submenu_page(
        'sandcrime-dashboard',
        'Crime Reports',
        'Crime Reports',
        'manage_options',
        'sandcrime-reports',
        'sandcrime_reports_page'
    );

    // Security Groups submenu
    add_submenu_page(
        'sandcrime-dashboard',
        'Security Groups',
        'Security Groups',
        'manage_options',
        'sandcrime-security-groups',
        'sandcrime_security_groups_page'
    );

    // Settings submenu
    add_submenu_page(
        'sandcrime-dashboard',
        'Settings',
        'Settings',
        'manage_options',
        'sandcrime-settings',
        'sandcrime_settings_page'
    );
}

// Dashboard Page
function sandcrime_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <!-- Stats Overview -->
        <div class="dashboard-stats">
            <?php
            global $wpdb;
            $reports_table = $wpdb->prefix . 'sandcrime_reports';
            $groups_table = $wpdb->prefix . 'sandcrime_groups';
            
            $total_reports = $wpdb->get_var("SELECT COUNT(*) FROM $reports_table");
            $pending_reports = $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE result_status = 'Pending Review'");
            $total_groups = $wpdb->get_var("SELECT COUNT(*) FROM $groups_table");
            ?>
            
            <div class="card">
                <h2>Overview</h2>
                <table class="widefat">
                    <tr>
                        <th>Total Reports</th>
                        <td><?php echo intval($total_reports); ?></td>
                    </tr>
                    <tr>
                        <th>Pending Reports</th>
                        <td><?php echo intval($pending_reports); ?></td>
                    </tr>
                    <tr>
                        <th>Security Groups</th>
                        <td><?php echo intval($total_groups); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Recent Reports -->
        <div class="card">
            <h2>Recent Reports</h2>
            <?php
            $recent_reports = $wpdb->get_results(
                "SELECT * FROM $reports_table ORDER BY date_time DESC LIMIT 5"
            );
            
            if ($recent_reports): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_reports as $report): ?>
                            <tr>
                                <td><?php echo esc_html($report->title); ?></td>
                                <td><?php echo esc_html($report->category); ?></td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($report->date_time))); ?></td>
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
    </div>
    <?php
}

// Reports Page
function sandcrime_reports_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_reports';

    // Handle report status updates
    if (isset($_POST['report_action']) && isset($_POST['report_id']) && 
        wp_verify_nonce($_POST['sandcrime_report_nonce'], 'sandcrime_report_action')) {
        
        $report_id = intval($_POST['report_id']);
        $new_status = sanitize_text_field($_POST['report_action']);
        
        $wpdb->update(
            $table_name,
            ['result_status' => $new_status],
            ['id' => $report_id],
            ['%s'],
            ['%d']
        );
    }

    // Handle viewing single report
    if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
        $report_id = intval($_GET['id']);
        $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $report_id));
        
        if ($report): ?>
            <div class="wrap">
                <h1>
                    View Report
                    <a href="?page=sandcrime-reports" class="page-title-action">Back to Reports</a>
                </h1>
                
                <div class="card">
                    <h2><?php echo esc_html($report->title); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th>Category</th>
                            <td><?php echo esc_html($report->category); ?></td>
                        </tr>
                        <tr>
                            <th>Date/Time</th>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($report->date_time))); ?></td>
                        </tr>
                        <tr>
                            <th>Location</th>
                            <td><?php echo esc_html($report->location); ?></td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td><?php echo nl2br(esc_html($report->description)); ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <form method="post">
                                    <?php wp_nonce_field('sandcrime_report_action', 'sandcrime_report_nonce'); ?>
                                    <input type="hidden" name="report_id" value="<?php echo esc_attr($report->id); ?>">
                                    <select name="report_action">
                                        <option value="Pending Review" <?php selected($report->result_status, 'Pending Review'); ?>>Pending Review</option>
                                        <option value="In Progress" <?php selected($report->result_status, 'In Progress'); ?>>In Progress</option>
                                        <option value="Resolved" <?php selected($report->result_status, 'Resolved'); ?>>Resolved</option>
                                        <option value="Closed" <?php selected($report->result_status, 'Closed'); ?>>Closed</option>
                                    </select>
                                    <?php submit_button('Update Status', 'secondary', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                        <?php if (!empty($report->photo_attachments)): ?>
                            <tr>
                                <th>Photos</th>
                                <td>
                                    <?php
                                    $photos = explode(',', $report->photo_attachments);
                                    foreach ($photos as $photo_url): ?>
                                        <a href="<?php echo esc_url($photo_url); ?>" target="_blank">
                                            <img src="<?php echo esc_url($photo_url); ?>" 
                                                 style="max-width: 150px; margin: 5px; vertical-align: top;">
                                        </a>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        <?php
        else:
            echo '<div class="wrap"><p>Report not found.</p></div>';
        endif;
        return;
    }

    // Display reports list
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php
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

// Security Groups Page Functions
function handle_security_group_submission() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sandcrime_security_group_nonce'])) {
        if (!wp_verify_nonce($_POST['sandcrime_security_group_nonce'], 'sandcrime_security_group_action')) {
            wp_die('Invalid nonce');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_groups';

        // Handle logo upload
        $logo_id = null;
        $logo_url = null;
        if (!empty($_FILES['logo']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $logo_id = media_handle_upload('logo', 0);
            if (!is_wp_error($logo_id)) {
                $logo_url = wp_get_attachment_url($logo_id);
            }
        }

        // Clean website URL - allow without https://
        $website = sanitize_text_field($_POST['website']);
        if (!empty($website) && !preg_match("~^(?:f|ht)tps?://~i", $website)) {
            $website = "http://" . $website;
        }

        $data = array(
            'title' => sanitize_text_field($_POST['title']),
            'contact_numbers' => sanitize_text_field($_POST['contact_numbers']),
            'email' => sanitize_email($_POST['email']),
            'address' => isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '',
            'website' => $website ? esc_url_raw($website) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : ''
        );

        // Add logo data if uploaded
        if ($logo_id && $logo_url) {
            $data['logo_id'] = $logo_id;
            $data['logo_url'] = $logo_url;
        }

        if (isset($_POST['group_id']) && !empty($_POST['group_id'])) {
            // Update existing group
            $group_id = intval($_POST['group_id']);
            
            // If updating and there's a new logo, delete the old one
            if ($logo_id) {
                $old_logo_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT logo_id FROM $table_name WHERE id = %d",
                    $group_id
                ));
                if ($old_logo_id) {
                    wp_delete_attachment($old_logo_id, true);
                }
            }

            $result = $wpdb->update(
                $table_name,
                $data,
                ['id' => $group_id]
            );
        } else {
            // Insert new group
            $result = $wpdb->insert($table_name, $data);
        }

        if ($result === false) {
            add_action('admin_notices', function() use ($wpdb) {
                echo '<div class="notice notice-error is-dismissible"><p>Database error: ' . esc_html($wpdb->last_error) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Security group saved successfully!</p></div>';
            });
        }
    }
}

function handle_security_group_deletion() {
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_security_group_' . $_GET['id'])) {
            wp_die('Invalid nonce');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_groups';
        $group_id = intval($_GET['id']);
        
        $result = $wpdb->delete($table_name, ['id' => $group_id], ['%d']);
        
        if ($result === false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>Error deleting security group.</p></div>';
            });
        } else {
            wp_redirect(add_query_arg(['page' => 'sandcrime-security-groups', 'deleted' => '1'], admin_url('admin.php')));
            exit;
        }
    }
}

function get_security_group($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_groups';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
}

function sandcrime_security_groups_page() {
    handle_security_group_submission();
    handle_security_group_deletion();

    $editing_group = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $editing_group = get_security_group(intval($_GET['id']));
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <!-- Add/Edit Security Group Form -->
        <div class="card">
            <h2><?php echo $editing_group ? 'Edit Security Group' : 'Add New Security Group'; ?></h2>
            <form method="post">
                <?php wp_nonce_field('sandcrime_security_group_action', 'sandcrime_security_group_nonce'); ?>
                <?php if ($editing_group): ?>
                    <input type="hidden" name="group_id" value="<?php echo esc_attr($editing_group->id); ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="title">Group Name *</label></th>
                        <td>
                            <input type="text" id="title" name="title" class="regular-text" required 
                                value="<?php echo $editing_group ? esc_attr($editing_group->title) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="contact_numbers">Contact Numbers *</label></th>
                        <td>
                            <input type="text" id="contact_numbers" name="contact_numbers" class="regular-text" required 
                                placeholder="e.g., +27 123 456 7890"
                                value="<?php echo $editing_group ? esc_attr($editing_group->contact_numbers) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email">Email *</label></th>
                        <td>
                            <input type="email" id="email" name="email" class="regular-text" required 
                                value="<?php echo $editing_group ? esc_attr($editing_group->email) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="website">Website</label></th>
                        <td>
                            <input type="url" id="website" name="website" class="regular-text" 
                                placeholder="https://"
                                value="<?php echo $editing_group ? esc_url($editing_group->website) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="address">Address</label></th>
                        <td>
                            <textarea id="address" name="address" class="large-text" rows="3"><?php 
                                echo $editing_group ? esc_textarea($editing_group->address) : ''; 
                            ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td>
                            <textarea id="description" name="description" class="large-text" rows="5"><?php 
                                echo $editing_group ? esc_textarea($editing_group->description) : ''; 
                            ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($editing_group ? 'Update Security Group' : 'Add Security Group'); ?>
                
                <?php if ($editing_group): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sandcrime-security-groups')); ?>" 
                       class="button button-secondary">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!$editing_group): // Only show the list when not editing ?>
            <!-- List Existing Security Groups -->
            <h2 class="title">Existing Security Groups</h2>
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
                            <th>Website</th>
                            <th>Address</th>
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
                                    <?php if ($group->website): ?>
                                        <a href="<?php echo esc_url($group->website); ?>" target="_blank">Visit Website</a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($group->address); ?></td>
                                <td>
                                    <a href="?page=sandcrime-security-groups&action=edit&id=<?php echo esc_attr($group->id); ?>" 
                                       class="button button-secondary">Edit</a>
                                    <a href="?page=sandcrime-security-groups&action=delete&id=<?php echo esc_attr($group->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_security_group_' . $group->id); ?>" 
                                       class="button button-link-delete" 
                                       onclick="return confirm('Are you sure you want to delete this security group?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No security groups found.</p>
            <?php endif; ?>
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
                        <p class="description">Send email notifications when new reports are submitted.</p>
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
                        <p class="description">Email address where notifications will be sent.</p>
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