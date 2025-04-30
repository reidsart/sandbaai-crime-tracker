<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap sandcrime-notifications-dashboard">
    <h1><?php _e('Notifications Dashboard', 'sandcrime'); ?></h1>

    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php _e('Total Notifications', 'sandcrime'); ?></h3>
            <div class="stat-value"><?php echo number_format_i18n($stats['total']); ?></div>
            <div class="stat-chart" data-type="total"></div>
        </div>

        <div class="stat-card">
            <h3><?php _e('Unread Notifications', 'sandcrime'); ?></h3>
            <div class="stat-value"><?php echo number_format_i18n($stats['unread']); ?></div>
            <div class="stat-chart" data-type="unread"></div>
        </div>

        <div class="stat-card">
            <h3><?php _e('Today\'s Notifications', 'sandcrime'); ?></h3>
            <div class="stat-value"><?php echo number_format_i18n($stats['today']); ?></div>
            <div class="stat-chart" data-type="today"></div>
        </div>

        <div class="stat-card">
            <h3><?php _e('Queued', 'sandcrime'); ?></h3>
            <div class="stat-value"><?php echo number_format_i18n($stats['queued']); ?></div>
            <div class="stat-badge <?php echo $stats['queued'] > 1000 ? 'warning' : 'normal'; ?>">
                <?php echo $stats['queued'] > 1000 ? __('High', 'sandcrime') : __('Normal', 'sandcrime'); ?>
            </div>
        </div>

        <div class="stat-card">
            <h3><?php _e('Failed Deliveries', 'sandcrime'); ?></h3>
            <div class="stat-value"><?php echo number_format_i18n($stats['failed']); ?></div>
            <div class="stat-badge <?php echo $stats['failed'] > 0 ? 'error' : 'success'; ?>">
                <?php echo $stats['failed'] > 0 ? __('Action Needed', 'sandcrime') : __('All Good', 'sandcrime'); ?>
            </div>
        </div>
    </div>

    <div class="notification-panels">
        <div class="panel">
            <h2><?php _e('Recent Notifications', 'sandcrime'); ?></h2>
            <div class="notification-list">
                <?php if (empty($recent_notifications)): ?>
                    <p class="no-items"><?php _e('No recent notifications', 'sandcrime'); ?></p>
                <?php else: ?>
                    <?php foreach ($recent_notifications as $notification): ?>
                        <div class="notification-item <?php echo esc_attr($notification->priority); ?>">
                            <div class="notification-header">
                                <h4><?php echo esc_html($notification->title); ?></h4>
                                <span class="notification-meta">
                                    <?php echo esc_html(human_time_diff(strtotime($notification->created_at), current_time('timestamp'))); ?> <?php _e('ago', 'sandcrime'); ?>
                                </span>
                            </div>
                            <div class="notification-content">
                                <?php echo wp_kses_post($notification->message); ?>
                            </div>
                            <div class="notification-footer">
                                <span class="notification-type"><?php echo esc_html(ucfirst($notification->type)); ?></span>
                                <?php if (!$notification->read_at): ?>
                                    <span class="unread-badge"><?php _e('Unread', 'sandcrime'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="view-all">
                        <a href="<?php echo admin_url('admin.php?page=sandcrime-notifications&view=all'); ?>" class="button">
                            <?php _e('View All Notifications', 'sandcrime'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <h2><?php _e('Quick Actions', 'sandcrime'); ?></h2>
            <div class="quick-actions">
                <button type="button" class="button button-primary" data-action="process-queue">
                    <?php _e('Process Queue Now', 'sandcrime'); ?>
                </button>
                <button type="button" class="button" data-action="retry-failed">
                    <?php _e('Retry Failed Notifications', 'sandcrime'); ?>
                </button>
                <button type="button" class="button" data-action="clear-old">
                    <?php _e('Clear Old Notifications', 'sandcrime'); ?>
                </button>
            </div>

            <div class="action-status" style="display: none;">
                <div class="spinner"></div>
                <span class="status-message"></span>
            </div>
        </div>
    </div>

    <div id="notification-log" class="panel">
        <h2><?php _e('Notification Log', 'sandcrime'); ?></h2>
        <div class="log-filters">
            <select id="log-type-filter">
                <option value=""><?php _e('All Types', 'sandcrime'); ?></option>
                <option value="report"><?php _e('Reports', 'sandcrime'); ?></option>
                <option value="alert"><?php _e('Alerts', 'sandcrime'); ?></option>
                <option value="message"><?php _e('Messages', 'sandcrime'); ?></option>
            </select>

            <select id="log-priority-filter">
                <option value=""><?php _e('All Priorities', 'sandcrime'); ?></option>
                <option value="urgent"><?php _e('Urgent', 'sandcrime'); ?></option>
                <option value="high"><?php _e('High', 'sandcrime'); ?></option>
                <option value="normal"><?php _e('Normal', 'sandcrime'); ?></option>
                <option value="low"><?php _e('Low', 'sandcrime'); ?></option>
            </select>

            <input type="text" id="log-search" placeholder="<?php esc_attr_e('Search notifications...', 'sandcrime'); ?>">
        </div>

        <div class="log-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('ID', 'sandcrime'); ?></th>
                        <th scope="col"><?php _e('Title', 'sandcrime'); ?></th>
                        <th scope="col"><?php _e('Type', 'sandcrime'); ?></th>
                        <th scope="col"><?php _e('Priority', 'sandcrime'); ?></th>
                        <th scope="col"><?php _e('User', 'sandcrime'); ?></th>
                        <th scope="col"><?php _e('Created', 'sandcrime'); ?></th>
                        <th scope="col"><?php _e('Status', 'sandcrime'); ?></th>
                    </tr>
                </thead>
                <tbody id="log-entries">
                    <!-- Populated via AJAX -->
                </tbody>
            </table>
        </div>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="pagination-links">
                    <!-- Pagination controls added via JavaScript -->
                </span>
            </div>
        </div>
    </div>
</div>