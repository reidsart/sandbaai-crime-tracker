<?php if (!defined('ABSPATH')) exit; ?>

<div class="notification-center" id="notification-center">
    <div class="notification-header">
        <div class="header-info">
            <h3>Notifications</h3>
            <span class="notification-count" id="notification-count"></span>
        </div>
        <div class="header-actions">
            <button class="mark-all-read" id="mark-all-read">
                Mark all as read
            </button>
            <button class="notification-settings-btn" id="notification-settings-btn">
                <i class="dashicons dashicons-admin-generic"></i>
            </button>
        </div>
    </div>

    <div class="notification-filters">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="unread">Unread</button>
        <button class="filter-btn" data-filter="reports">Reports</button>
        <button class="filter-btn" data-filter="groups">Groups</button>
        <button class="filter-btn" data-filter="alerts">Alerts</button>
    </div>

    <div class="notification-list" id="notification-list">
        <!-- Notifications will be loaded here -->
    </div>

    <div class="notification-footer">
        <button class="load-more-btn" id="load-more-notifications">
            Load More
        </button>
    </div>

    <!-- Notification Settings Modal -->
    <div id="notification-settings-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Notification Settings</h3>
                <button class="close-modal">&times;</button>
            </div>

            <form id="notification-settings-form">
                <div class="settings-section">
                    <h4>Email Notifications</h4>
                    <div class="setting-group">
                        <label>
                            <input type="checkbox" name="email_enabled" checked>
                            Enable email notifications
                        </label>
                    </div>
                    <div class="setting-group">
                        <label>Email Digest Frequency:</label>
                        <select name="email_frequency">
                            <option value="immediate">Immediate</option>
                            <option value="hourly">Hourly Digest</option>
                            <option value="daily">Daily Digest</option>
                            <option value="weekly">Weekly Digest</option>
                        </select>
                    </div>
                </div>

                <div class="settings-section">
                    <h4>Push Notifications</h4>
                    <div class="setting-group">
                        <label>
                            <input type="checkbox" name="push_enabled" checked>
                            Enable push notifications
                        </label>
                    </div>
                </div>

                <div class="settings-section">
                    <h4>Notification Types</h4>
                    <div class="setting-group">
                        <label>Reports:</label>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="notify_report_updates" checked>
                                Report updates
                            </label>
                            <label>
                                <input type="checkbox" name="notify_report_comments" checked>
                                Comments on reports
                            </label>
                            <label>
                                <input type="checkbox" name="notify_report_area" checked>
                                New reports in my area
                            </label>
                        </div>
                    </div>

                    <div class="setting-group">
                        <label>Security Groups:</label>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="notify_group_messages" checked>
                                Group messages
                            </label>
                            <label>
                                <input type="checkbox" name="notify_group_alerts" checked>
                                Group alerts
                            </label>
                            <label>
                                <input type="checkbox" name="notify_group_updates" checked>
                                Group updates
                            </label>
                        </div>
                    </div>

                    <div class="setting-group">
                        <label>Priority Level:</label>
                        <select name="priority_threshold">
                            <option value="all">All notifications</option>
                            <option value="normal">Normal and above</option>
                            <option value="high">High and urgent only</option>
                            <option value="urgent">Urgent only</option>
                        </select>
                    </div>
                </div>

                <div class="settings-section">
                    <h4>Quiet Hours</h4>
                    <div class="setting-group">
                        <label>
                            <input type="checkbox" name="quiet_hours_enabled">
                            Enable quiet hours
                        </label>
                    </div>
                    <div class="quiet-hours-settings">
                        <div class="time-range">
                            <label>From:</label>
                            <input type="time" name="quiet_hours_start">
                            <label>To:</label>
                            <input type="time" name="quiet_hours_end">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary">Save Settings</button>
                    <button type="button" class="button cancel-settings">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.notification-center {
    max-width: 600px;
    margin: 0 auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
}

.header-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.notification-count {
    background: #e53935;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.notification-filters {
    display: flex;
    gap: 10px;
    padding: 10px 20px;
    border-bottom: 1px solid #eee;
    overflow-x: auto;
}

.filter-btn {
    padding: 5px 15px;
    border: none;
    background: none;
    border-radius: 15px;
    cursor: pointer;
    white-space: nowrap;
}

.filter-btn.active {
    background: #e3f2fd;
    color: #1976d2;
}

.notification-list {
    max-height: 500px;
    overflow-y: auto;
}

.notification-item {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background-color 0.2s;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item.unread {
    background: #f8f9fa;
}

.notification-item.unread::before {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #1976d2;
    border-radius: 50%;
    margin-right: 10px;
}

.notification-content {
    display: flex;
    gap: 15px;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-details {
    flex: 1;
}

.notification-title {
    font-weight: 500;
    margin-bottom: 5px;
}

.notification-message {
    color: #666;
    font-size: 14px;
    margin-bottom: 5px;
}

.notification-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #888;
}

.notification-footer {
    padding: 15px;
    text-align: center;
    border-top: 1px solid #eee;
}

.load-more-btn {
    color: #1976d2;
    background: none;
    border: none;
    cursor: pointer;
}

.load-more-btn:hover {
    text-decoration: underline;
}

/* Settings Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal-content {
    position: relative;
    background: white;
    max-width: 500px;
    margin: 50px auto;
    border-radius: 8px;
    padding: 20px;
}

.settings-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.settings-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.setting-group {
    margin-bottom: 15px;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 5px;
}

.quiet-hours-settings {
    margin-top: 10px;
    padding-left: 20px;
}

.time-range {
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .notification-center {
        margin: 0;
        border-radius: 0;
    }

    .modal-content {
        margin: 0;
        border-radius: 0;
        height: 100%;
        overflow-y: auto;
    }
}
</style>