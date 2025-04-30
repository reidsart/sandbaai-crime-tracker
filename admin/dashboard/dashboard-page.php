<?php
if (!defined('ABSPATH')) {
    exit;
}

function sandcrime_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php
        global $wpdb;
        $reports_table = $wpdb->prefix . 'sandcrime_reports';
        $groups_table = $wpdb->prefix . 'sandcrime_groups';
        
        // Get statistics
        $stats = array(
            'total_reports' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table"),
            'pending_reports' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE result_status = 'Pending Review'"),
            'approved_reports' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE result_status = 'Approved'"),
            'total_groups' => $wpdb->get_var("SELECT COUNT(*) FROM $groups_table"),
            'reports_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $reports_table WHERE DATE(date_time) = %s",
                current_time('Y-m-d')
            )),
            'reports_this_month' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $reports_table WHERE MONTH(date_time) = %d AND YEAR(date_time) = %d",
                current_time('m'),
                current_time('Y')
            ))
        );
        ?>
        
        <!-- Statistics Overview -->
        <div class="dashboard-stats-grid">
            <?php
            $stat_cards = array(
                array('Total Reports', $stats['total_reports'], 'dashicons-list-view'),
                array('Pending Review', $stats['pending_reports'], 'dashicons-clock'),
                array('Approved Reports', $stats['approved_reports'], 'dashicons-yes-alt'),
                array('Security Groups', $stats['total_groups'], 'dashicons-shield'),
                array('Reports Today', $stats['reports_today'], 'dashicons-calendar-alt'),
                array('Reports This Month', $stats['reports_this_month'], 'dashicons-chart-bar')
            );

            foreach ($stat_cards as $card): ?>
                <div class="stat-card">
                    <span class="dashicons <?php echo esc_attr($card[2]); ?>"></span>
                    <div class="stat-content">
                        <h3><?php echo esc_html($card[0]); ?></h3>
                        <span class="stat-number"><?php echo esc_html($card[1]); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Activity -->
        <div class="dashboard-recent-activity">
            <div class="activity-column">
                <div class="card">
                    <h2>Recent Reports</h2>
                    <?php
                    $recent_reports = $wpdb->get_results("
                        SELECT * FROM $reports_table 
                        ORDER BY date_time DESC 
                        LIMIT 5
                    ");
                    
                    if ($recent_reports): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Reported By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reports as $report): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=sandcrime-reports&action=view&id=' . $report->id)); ?>">
                                                <?php echo esc_html($report->title); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html($report->result_status); ?></td>
                                        <td><?php echo esc_html($report->user_login ?: 'Anonymous'); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($report->date_time))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="view-all">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sandcrime-reports')); ?>" class="button button-secondary">
                                View All Reports
                            </a>
                        </p>
                    <?php else: ?>
                        <p>No reports found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="activity-column">
                <div class="card">
                    <h2>Active Security Groups</h2>
                    <?php
                    $active_groups = $wpdb->get_results("
                        SELECT g.*, COUNT(m.id) as member_count 
                        FROM $groups_table g
                        LEFT JOIN {$wpdb->prefix}sandcrime_group_members m ON g.id = m.group_id
                        GROUP BY g.id
                        ORDER BY member_count DESC
                        LIMIT 5
                    ");
                    
                    if ($active_groups): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Group</th>
                                    <th>Members</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_groups as $group): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=sandcrime-security-groups&action=edit&id=' . $group->id)); ?>">
                                                <?php echo esc_html($group->title); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html($group->member_count); ?></td>
                                        <td><?php echo esc_html($group->email); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="view-all">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sandcrime-security-groups')); ?>" class="button button-secondary">
                                View All Groups
                            </a>
                        </p>
                    <?php else: ?>
                        <p>No security groups found.</p>
                    <?php endif; ?>
                </div>

                <!-- System Status -->
                <div class="card system-status">
                    <h2>System Status</h2>
                    <ul>
                        <li>
                            <strong>Last Update:</strong> 
                            <?php echo esc_html(current_time('Y-m-d H:i:s')); ?>
                        </li>
                        <li>
                            <strong>Current User:</strong> 
                            <?php echo esc_html(wp_get_current_user()->user_login); ?>
                        </li>
                        <li>
                            <strong>Plugin Version:</strong> 
                            <?php echo esc_html(SANDCRIME_PLUGIN_VERSION); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <style>
    .dashboard-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: white;
        padding: 20px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        display: flex;
        align-items: center;
    }
    .stat-card .dashicons {
        font-size: 48px;
        width: 48px;
        height: 48px;
        margin-right: 15px;
        color: #2271b1;
    }
    .stat-content h3 {
        margin: 0 0 5px 0;
        font-size: 14px;
        color: #50575e;
    }
    .stat-number {
        font-size: 24px;
        font-weight: 600;
        color: #1d2327;
    }
    .dashboard-recent-activity {
        display: grid;
        grid-template-columns: 3fr 2fr;
        gap: 20px;
    }
    .activity-column .card {
        background: white;
        padding: 20px;
        border: 1px solid #ccd0d4;
        margin-bottom: 20px;
    }
    .view-all {
        margin-top: 15px;
        text-align: right;
    }
    .system-status ul {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .system-status li {
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f1;
    }
    .system-status li:last-child {
        border-bottom: none;
    }
    </style>
    <?php
}