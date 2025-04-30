<?php
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$settings = SandCrime_Notification_Handler::get_user_settings($user_id);
$timezones = DateTimeZone::listIdentifiers();
?>

<div class="notification-settings-wrapper">
    <h2><?php _e('Notification Settings', 'sandcrime'); ?></h2>
    
    <form id="notification-settings-form" method="post">
        <?php wp_nonce_field('sandcrime_notification_settings', 'settings_nonce'); ?>

        <div class="settings-section">
            <h3><?php _e('Email Notifications', 'sandcrime'); ?></h3>
            
            <div class="form-group">
                <label class="switch">
                    <input type="checkbox" name="email_enabled" 
                           <?php checked($settings['email_enabled'], 1); ?>>
                    <span class="slider"></span>
                </label>
                <span class="setting-label">
                    <?php _e('Receive email notifications', 'sandcrime'); ?>
                </span>
            </div>

            <div class="form-group email-frequency" 
                 <?php echo !$settings['email_enabled'] ? 'style="display:none;"' : ''; ?>>
                <label><?php _e('Email Frequency', 'sandcrime'); ?></label>
                <select name="email_frequency">
                    <option value="immediate" <?php selected($settings['email_frequency'], 'immediate'); ?>>
                        <?php _e('Immediately', 'sandcrime'); ?>
                    </option>
                    <option value="hourly" <?php selected($settings['email_frequency'], 'hourly'); ?>>
                        <?php _e('Hourly Digest', 'sandcrime'); ?>
                    </option>
                    <option value="daily" <?php selected($settings['email_frequency'], 'daily'); ?>>
                        <?php _e('Daily Digest', 'sandcrime'); ?>
                    </option>
                    <option value="weekly" <?php selected($settings['email_frequency'], 'weekly'); ?>>
                        <?php _e('Weekly Digest', 'sandcrime'); ?>
                    </option>
                </select>
            </div>
        </div>

        <div class="settings-section">
            <h3><?php _e('Push Notifications', 'sandcrime'); ?></h3>
            
            <div class="form-group">
                <label class="switch">
                    <input type="checkbox" name="push_enabled" 
                           <?php checked($settings['push_enabled'], 1); ?>>
                    <span class="slider"></span>
                </label>
                <span class="setting-label">
                    <?php _e('Receive push notifications', 'sandcrime'); ?>
                </span>
            </div>

            <div class="push-devices" <?php echo !$settings['push_enabled'] ? 'style="display:none;"' : ''; ?>>
                <h4><?php _e('Registered Devices', 'sandcrime'); ?></h4>
                <div id="registered-devices-list">
                    <!-- Dynamically populated via JavaScript -->
                </div>
            </div>
        </div>

        <div class="settings-section">
            <h3><?php _e('Notification Types', 'sandcrime'); ?></h3>
            
            <?php
            $notification_types = array(
                'report' => __('Report Updates', 'sandcrime'),
                'group_message' => __('Group Messages', 'sandcrime'),
                'group_alert' => __('Group Alerts', 'sandcrime'),
                'comment' => __('Comments', 'sandcrime'),
                'system' => __('System Notifications', 'sandcrime')
            );
            
            $enabled_types = json_decode($settings['notification_types'] ?? '[]', true);
            
            foreach ($notification_types as $type => $label): ?>
                <div class="form-group">
                    <label class="switch">
                        <input type="checkbox" name="notification_types[]" 
                               value="<?php echo esc_attr($type); ?>"
                               <?php checked(in_array($type, $enabled_types), true); ?>>
                        <span class="slider"></span>
                    </label>
                    <span class="setting-label"><?php echo esc_html($label); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="settings-section">
            <h3><?php _e('Priority Settings', 'sandcrime'); ?></h3>
            
            <div class="form-group">
                <label><?php _e('Minimum Priority', 'sandcrime'); ?></label>
                <select name="priority_threshold">
                    <option value="all" <?php selected($settings['priority_threshold'], 'all'); ?>>
                        <?php _e('All Notifications', 'sandcrime'); ?>
                    </option>
                    <option value="low" <?php selected($settings['priority_threshold'], 'low'); ?>>
                        <?php _e('Low and Above', 'sandcrime'); ?>
                    </option>
                    <option value="normal" <?php selected($settings['priority_threshold'], 'normal'); ?>>
                        <?php _e('Normal and Above', 'sandcrime'); ?>
                    </option>
                    <option value="high" <?php selected($settings['priority_threshold'], 'high'); ?>>
                        <?php _e('High and Above', 'sandcrime'); ?>
                    </option>
                    <option value="urgent" <?php selected($settings['priority_threshold'], 'urgent'); ?>>
                        <?php _e('Urgent Only', 'sandcrime'); ?>
                    </option>
                </select>
            </div>
        </div>

        <div class="settings-section">
            <h3><?php _e('Quiet Hours', 'sandcrime'); ?></h3>
            
            <div class="form-group">
                <label class="switch">
                    <input type="checkbox" name="quiet_hours_enabled"
                           <?php checked($settings['quiet_hours_enabled'], 1); ?>>
                    <span class="slider"></span>
                </label>
                <span class="setting-label">
                    <?php _e('Enable quiet hours', 'sandcrime'); ?>
                </span>
            </div>

            <div class="quiet-hours-settings" 
                 <?php echo !$settings['quiet_hours_enabled'] ? 'style="display:none;"' : ''; ?>>
                <div class="form-group">
                    <label><?php _e('Start Time', 'sandcrime'); ?></label>
                    <input type="time" name="quiet_hours_start" 
                           value="<?php echo esc_attr($settings['quiet_hours_start']); ?>">
                </div>

                <div class="form-group">
                    <label><?php _e('End Time', 'sandcrime'); ?></label>
                    <input type="time" name="quiet_hours_end" 
                           value="<?php echo esc_attr($settings['quiet_hours_end']); ?>">
                </div>

                <div class="form-group">
                    <label><?php _e('Timezone', 'sandcrime'); ?></label>
                    <select name="timezone">
                        <?php foreach ($timezones as $tz): ?>
                            <option value="<?php echo esc_attr($tz); ?>" 
                                    <?php selected($settings['timezone'], $tz); ?>>
                                <?php echo esc_html($tz); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="settings-actions">
            <button type="submit" class="button button-primary">
                <?php _e('Save Settings', 'sandcrime'); ?>
            </button>
            <button type="button" class="button reset-defaults">
                <?php _e('Reset to Defaults', 'sandcrime'); ?>
            </button>
        </div>
    </form>
</div>