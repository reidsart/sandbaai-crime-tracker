<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_User_Dashboard {
    public static function init() {
        add_shortcode('sandcrime_dashboard', array(__CLASS__, 'render_dashboard'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('init', array(__CLASS__, 'handle_user_actions'));
    }

    public static function enqueue_assets() {
        wp_enqueue_style(
            'sandcrime-dashboard',
            SANDCRIME_PLUGIN_URL . 'assets/css/dashboard.css',
            array(),
            SANDCRIME_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'sandcrime-dashboard',
            SANDCRIME_PLUGIN_URL . 'assets/js/dashboard.js',
            array('jquery'),
            SANDCRIME_PLUGIN_VERSION,
            true
        );

        wp_localize_script('sandcrime-dashboard', 'sandcrimeDashboard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sandcrime_dashboard_nonce')
        ));
    }

    public static function handle_user_actions() {
        if (!is_user_logged_in()) {
            return;
        }

        if (isset($_POST['sandcrime_notification_settings'])) {
            check_admin_referer('sandcrime_notification_settings', 'sandcrime_nonce');
            
            $user_id = get_current_user_id();
            $settings = array(
                'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
                'notification_types' => isset($_POST['notification_types']) ? 
                    array_map('sanitize_text_field', $_POST['notification_types']) : array()
            );
            
            update_user_meta($user_id, 'sandcrime_notification_settings', $settings);
            
            add_action('sandcrime_dashboard_notices', function() {
                echo '<div class="notice notice-success">Notification settings updated successfully.</div>';
            });
        }
    }

    public static function render_dashboard($atts = array()) {
        if (!is_user_logged_in()) {
            return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your dashboard.</p>';
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Get user's reports
        global $wpdb;
        $reports_table = $wpdb->prefix . 'sandcrime_reports';
        
        $user_reports = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM $reports_table
            WHERE user_id = %d
            ORDER BY date_time DESC
        ", $user_id));

        // Get user's security groups
        $groups = SandCrime_Map_Manager::get_user_security_groups($user_id);

        // Get notification settings
        $notification_settings = get_user_meta($user_id, 'sandcrime_notification_settings', true) ?: array();

        ob_start();
        ?>
        <div class="sandcrime-dashboard-wrapper">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <h2>Welcome, <?php echo esc_html($user->display_name); ?></h2>
                <div class="dashboard-actions">
                    <a href="#" class="button" id="toggle-notifications-panel">
                        <span class="dashicons dashicons-bell"></span> Notifications
                    </a>
                </div>
            </div>

            <!-- Dashboard Notices -->
            <div class="dashboard-notices">
                <?php do_action('sandcrime_dashboard_notices'); ?>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-box">
                    <h3>Your Reports</h3>
                    <div class="stat-number"><?php echo count($user_reports); ?></div>
                </div>
                <div class="stat-box">
                    <h3>Security Groups</h3>
                    <div class="stat-number"><?php echo count($groups); ?></div>
                </div>
                <div class="stat-box">
                    <h3>Active Reports</h3>
                    <div class="stat-number">
                        <?php
                        echo count(array_filter($user_reports, function($report) {
                            return $report->result_status != 'Closed';
                        }));
                        ?>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Content -->
            <div class="dashboard-content">
                <!-- Your Reports -->
                <div class="dashboard-section">
                    <h3>Your Recent Reports</h3>
                    <?php if ($user_reports): ?>
                        <div class="reports-list">
                            <?php foreach ($user_reports as $report): ?>
                                <div class="report-item">
                                    <div class="report-header">
                                        <h4><?php echo esc_html($report->title); ?></h4>
                                        <span class="status-badge status-<?php 
                                            echo sanitize_html_class(strtolower($report->result_status)); 
                                        ?>">
                                            <?php echo esc_html($report->result_status); ?>
                                        </span>
                                    </div>
                                    <div class="report-meta">
                                        <span class="report-date">
                                            <?php echo esc_html(SandCrime_Utilities::time_elapsed_string($report->date_time)); ?>
                                        </span>
                                        <span class="report-category">
                                            <?php echo esc_html($report->category); ?>
                                        </span>
                                    </div>
                                    <div class="report-actions">
                                        <button class="button view-report" data-id="<?php echo esc_attr($report->id); ?>">
                                            View Details
                                        </button>
                                        <?php if ($report->result_status == 'Pending Review'): ?>
                                            <button class="button edit-report" data-id="<?php echo esc_attr($report->id); ?>">
                                                Edit
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($user_reports) > 5): ?>
                            <a href="<?php echo esc_url(home_url('/reports/')); ?>" class="button view-all-reports">
                                View All Reports
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>You haven't submitted any reports yet.</p>
                        <a href="<?php echo esc_url(home_url('/submit-report/')); ?>" class="button">
                            Submit Your First Report
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Security Groups -->
                <div class="dashboard-section">
                    <h3>Your Security Groups</h3>
                    <?php if ($groups): ?>
                        <div class="security-groups-list">
                            <?php foreach ($groups as $group): ?>
                                <div class="group-item">
                                    <?php if ($group->logo_url): ?>
                                        <div class="group-logo">
                                            <img src="<?php echo esc_url($group->logo_url); ?>" 
                                                 alt="<?php echo esc_attr($group->title); ?>">
                                        </div>
                                    <?php endif; ?>
                                    <div class="group-info">
                                        <h4><?php echo esc_html($group->title); ?></h4>
                                        <div class="group-meta">
                                            <?php if ($group->role == 'admin'): ?>
                                                <span class="role-badge">Admin</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="group-actions">
                                        <button class="button view-group" data-id="<?php echo esc_attr($group->id); ?>">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>You're not a member of any security groups yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notification Settings Panel -->
            <div id="notifications-panel" class="notifications-panel" style="display: none;">
                <div class="panel-header">
                    <h3>Notification Settings</h3>
                    <button class="close-panel">&times;</button>
                </div>
                <div class="panel-content">
                    <form method="post" class="notification-settings-form">
                        <?php wp_nonce_field('sandcrime_notification_settings', 'sandcrime_nonce'); ?>
                        
                        <div class="form-row">
                            <label>
                                <input type="checkbox" name="email_notifications" value="1"
                                       <?php checked(!empty($notification_settings['email_notifications'])); ?>>
                                Email Notifications
                            </label>
                        </div>

                        <div class="form-row">
                            <label>
                                <input type="checkbox" name="sms_notifications" value="1"
                                       <?php checked(!empty($notification_settings['sms_notifications'])); ?>>
                                SMS Notifications
                            </label>
                        </div>

                        <div class="form-row">
                            <h4>Notify me about:</h4>
                            <?php
                            $notification_types = array(
                                'report_updates' => 'Updates to my reports',
                                'area_reports' => 'New reports in my area',
                                'group_updates' => 'Security group updates',
                                'comments' => 'Comments on my reports'
                            );
                            foreach ($notification_types as $type => $label): ?>
                                <label>
                                    <input type="checkbox" name="notification_types[]" value="<?php echo esc_attr($type); ?>"
                                           <?php checked(in_array($type, $notification_settings['notification_types'] ?? array())); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-row">
                            <button type="submit" name="sandcrime_notification_settings" class="button button-primary">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Report Details Modal -->
        <div id="report-modal" class="modal">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <div id="report-details"></div>
            </div>
        </div>

        <style>
        .sandcrime-dashboard-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-box h3 {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        .stat-box .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-top: 5px;
        }
        .dashboard-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .report-item, .group-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .report-item:last-child, .group-item:last-child {
            border-bottom: none;
        }
        .report-header, .group-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .report-meta {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        .role-badge {
            background: #e5f5fa;
            color: #0a4b78;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        .notifications-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100%;
            background: white;
            box-shadow: -2px 0 4px rgba(0,0,0,0.1);
            transition: right 0.3s ease;
            z-index: 1000;
        }
        .notifications-panel.active {
            right: 0;
        }
        .panel-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .panel-content {
            padding: 20px;
        }
        .close-panel {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
        }
        .form-row {
            margin-bottom: 15px;
        }
        .form-row label {
            display: block;
            margin-bottom: 5px;
        }
        </style>
        <?php
        return ob_get_clean();
    }
}

// Initialize the user dashboard
SandCrime_User_Dashboard::init();