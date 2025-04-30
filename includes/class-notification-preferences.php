<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Preferences {
    private $default_preferences = array(
        'email_enabled' => true,
        'email_frequency' => 'immediate',
        'push_enabled' => false,
        'notification_types' => array('alert', 'message'),
        'priority_threshold' => 'normal',
        'quiet_hours_enabled' => false,
        'quiet_hours_start' => '22:00:00',
        'quiet_hours_end' => '07:00:00',
        'digest_enabled' => true,
        'digest_frequency' => 'daily',
        'mobile_notifications' => false,
        'language' => 'en_US'
    );

    public function __construct() {
        add_action('show_user_profile', array($this, 'add_notification_preferences'));
        add_action('edit_user_profile', array($this, 'add_notification_preferences'));
        add_action('personal_options_update', array($this, 'save_notification_preferences'));
        add_action('edit_user_profile_update', array($this, 'save_notification_preferences'));
        add_action('wp_ajax_update_notification_preferences', array($this, 'ajax_update_preferences'));
        add_action('wp_ajax_test_notification_preferences', array($this, 'ajax_test_preferences'));
    }

    public function get_user_preferences($user_id) {
        $preferences = get_user_meta($user_id, 'sandcrime_notification_preferences', true);
        return wp_parse_args($preferences, $this->default_preferences);
    }

    public function add_notification_preferences($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $preferences = $this->get_user_preferences($user->ID);
        ?>
        <h2><?php _e('Notification Preferences', 'sandcrime'); ?></h2>
        <table class="form-table">
            <tr>
                <th>
                    <label for="email_notifications"><?php _e('Email Notifications', 'sandcrime'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="notification_preferences[email_enabled]" 
                                   value="1"
                                   <?php checked($preferences['email_enabled'], true); ?>>
                            <?php _e('Enable email notifications', 'sandcrime'); ?>
                        </label>
                        <br>
                        <select name="notification_preferences[email_frequency]" 
                                class="regular-text"
                                <?php disabled(!$preferences['email_enabled']); ?>>
                            <option value="immediate" <?php selected($preferences['email_frequency'], 'immediate'); ?>>
                                <?php _e('Send Immediately', 'sandcrime'); ?>
                            </option>
                            <option value="daily" <?php selected($preferences['email_frequency'], 'daily'); ?>>
                                <?php _e('Daily Digest', 'sandcrime'); ?>
                            </option>
                            <option value="weekly" <?php selected($preferences['email_frequency'], 'weekly'); ?>>
                                <?php _e('Weekly Digest', 'sandcrime'); ?>
                            </option>
                        </select>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="push_notifications"><?php _e('Push Notifications', 'sandcrime'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="notification_preferences[push_enabled]" 
                                   value="1"
                                   <?php checked($preferences['push_enabled'], true); ?>>
                            <?php _e('Enable browser push notifications', 'sandcrime'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Receive notifications even when you\'re not on the site', 'sandcrime'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="notification_types"><?php _e('Notification Types', 'sandcrime'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <?php foreach ($this->get_available_notification_types() as $type => $label): ?>
                            <label>
                                <input type="checkbox" 
                                       name="notification_preferences[notification_types][]" 
                                       value="<?php echo esc_attr($type); ?>"
                                       <?php checked(in_array($type, $preferences['notification_types'])); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                            <br>
                        <?php endforeach; ?>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="priority_threshold"><?php _e('Priority Threshold', 'sandcrime'); ?></label>
                </th>
                <td>
                    <select name="notification_preferences[priority_threshold]" class="regular-text">
                        <option value="low" <?php selected($preferences['priority_threshold'], 'low'); ?>>
                            <?php _e('All Notifications (Low and above)', 'sandcrime'); ?>
                        </option>
                        <option value="normal" <?php selected($preferences['priority_threshold'], 'normal'); ?>>
                            <?php _e('Normal and above', 'sandcrime'); ?>
                        </option>
                        <option value="high" <?php selected($preferences['priority_threshold'], 'high'); ?>>
                            <?php _e('High priority only', 'sandcrime'); ?>
                        </option>
                        <option value="urgent" <?php selected($preferences['priority_threshold'], 'urgent'); ?>>
                            <?php _e('Urgent only', 'sandcrime'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <th>
                    <label><?php _e('Quiet Hours', 'sandcrime'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="notification_preferences[quiet_hours_enabled]" 
                                   value="1"
                                   <?php checked($preferences['quiet_hours_enabled'], true); ?>>
                            <?php _e('Enable quiet hours', 'sandcrime'); ?>
                        </label>
                        <br>
                        <div class="quiet-hours-settings" <?php echo !$preferences['quiet_hours_enabled'] ? 'style="display:none;"' : ''; ?>>
                            <label>
                                <?php _e('Start Time:', 'sandcrime'); ?>
                                <input type="time" 
                                       name="notification_preferences[quiet_hours_start]" 
                                       value="<?php echo esc_attr($preferences['quiet_hours_start']); ?>">
                            </label>
                            <br>
                            <label>
                                <?php _e('End Time:', 'sandcrime'); ?>
                                <input type="time" 
                                       name="notification_preferences[quiet_hours_end]" 
                                       value="<?php echo esc_attr($preferences['quiet_hours_end']); ?>">
                            </label>
                        </div>
                    </fieldset>
                </td>
            </tr>
        </table>

        <div class="notification-test-panel">
            <h3><?php _e('Test Your Notification Settings', 'sandcrime'); ?></h3>
            <button type="button" class="button" id="test-notifications">
                <?php _e('Send Test Notification', 'sandcrime'); ?>
            </button>
            <span class="spinner"></span>
            <div class="test-result"></div>
        </div>
        <?php
    }

    public function save_notification_preferences($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (!isset($_POST['notification_preferences'])) {
            return false;
        }

        $preferences = $this->sanitize_preferences($_POST['notification_preferences']);
        update_user_meta($user_id, 'sandcrime_notification_preferences', $preferences);

        return true;
    }

    public function ajax_update_preferences() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not logged in');
        }

        $preferences = $this->sanitize_preferences($_POST['preferences']);
        $success = update_user_meta($user_id, 'sandcrime_notification_preferences', $preferences);

        if ($success) {
            wp_send_json_success('Preferences updated successfully');
        } else {
            wp_send_json_error('Failed to update preferences');
        }
    }

    public function ajax_test_preferences() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not logged in');
        }

        try {
            $notification_handler = new SandCrime_Notification_Handler();
            $result = $notification_handler->create_notification(
                $user_id,
                array(
                    'type' => 'test',
                    'title' => __('Test Notification', 'sandcrime'),
                    'message' => __('This is a test notification to verify your preferences.', 'sandcrime'),
                    'priority' => 'normal'
                )
            );

            if ($result) {
                wp_send_json_success('Test notification sent successfully');
            } else {
                throw new Exception('Failed to send test notification');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    private function sanitize_preferences($preferences) {
        $sanitized = array();

        // Boolean values
        $boolean_fields = array('email_enabled', 'push_enabled', 'quiet_hours_enabled', 'digest_enabled', 'mobile_notifications');
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($preferences[$field]) ? (bool) $preferences[$field] : false;
        }

        // Arrays
        $sanitized['notification_types'] = isset($preferences['notification_types']) ? 
            array_map('sanitize_text_field', $preferences['notification_types']) : 
            array();

        // String values
        $string_fields = array(
            'email_frequency', 'priority_threshold', 'digest_frequency', 
            'quiet_hours_start', 'quiet_hours_end', 'language'
        );
        foreach ($string_fields as $field) {
            $sanitized[$field] = isset($preferences[$field]) ? 
                sanitize_text_field($preferences[$field]) : 
                $this->default_preferences[$field];
        }

        return $sanitized;
    }

    private function get_available_notification_types() {
        return array(
            'alert' => __('Alerts', 'sandcrime'),
            'message' => __('Messages', 'sandcrime'),
            'report' => __('Reports', 'sandcrime'),
            'update' => __('Updates', 'sandcrime'),
            'mention' => __('Mentions', 'sandcrime'),
            'comment' => __('Comments', 'sandcrime')
        );
    }
}

// Initialize the preferences system
new SandCrime_Notification_Preferences();