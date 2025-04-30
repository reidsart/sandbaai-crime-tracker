<?php
if (!defined('ABSPATH')) {
    exit;
}

function sandcrime_settings_page() {
    if (isset($_POST['submit']) && check_admin_referer('sandcrime_settings_action', 'sandcrime_settings_nonce')) {
        sandcrime_save_settings($_POST);
    }

    $settings = get_option('sandcrime_settings', array());
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('sandcrime_settings_action', 'sandcrime_settings_nonce'); ?>
            
            <div class="settings-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active">General Settings</a>
                    <a href="#notifications" class="nav-tab">Notifications</a>
                    <a href="#security" class="nav-tab">Security</a>
                    <a href="#integrations" class="nav-tab">Integrations</a>
                </nav>

                <!-- General Settings -->
                <div id="general" class="tab-content active">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="default_report_status">Default Report Status</label>
                            </th>
                            <td>
                                <select name="settings[default_report_status]" id="default_report_status">
                                    <option value="pending" <?php selected($settings['default_report_status'] ?? 'pending', 'pending'); ?>>
                                        Pending Review
                                    </option>
                                    <option value="auto_approved" <?php selected($settings['default_report_status'] ?? 'pending', 'auto_approved'); ?>>
                                        Auto-Approved
                                    </option>
                                </select>
                                <p class="description">Select the default status for new reports.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="reports_per_page">Reports Per Page</label>
                            </th>
                            <td>
                                <input type="number" id="reports_per_page" 
                                       name="settings[reports_per_page]" 
                                       value="<?php echo esc_attr($settings['reports_per_page'] ?? '20'); ?>" 
                                       min="5" max="100">
                                <p class="description">Number of reports to display per page in the admin area.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="enable_public_reports">Public Reports</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_public_reports" 
                                           name="settings[enable_public_reports]" value="1" 
                                           <?php checked($settings['enable_public_reports'] ?? false, true); ?>>
                                    Allow public users to submit reports
                                </label>
                                <p class="description">If disabled, only logged-in users can submit reports.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Notifications -->
                <div id="notifications" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_email_notifications">Email Notifications</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_email_notifications" 
                                           name="settings[enable_email_notifications]" value="1" 
                                           <?php checked($settings['enable_email_notifications'] ?? false, true); ?>>
                                    Enable email notifications
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="notification_emails">Notification Recipients</label>
                            </th>
                            <td>
                                <textarea id="notification_emails" name="settings[notification_emails]" 
                                          class="large-text" rows="3"><?php 
                                    echo esc_textarea($settings['notification_emails'] ?? ''); 
                                ?></textarea>
                                <p class="description">Enter email addresses (one per line) that should receive notifications.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="notification_types">Notification Events</label>
                            </th>
                            <td>
                                <?php
                                $notification_types = array(
                                    'new_report' => 'New Report Submitted',
                                    'report_updated' => 'Report Status Updated',
                                    'new_comment' => 'New Comment Added',
                                    'group_assigned' => 'Security Group Assigned'
                                );
                                foreach ($notification_types as $type => $label): ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="settings[notification_types][]" 
                                               value="<?php echo esc_attr($type); ?>" 
                                               <?php checked(in_array($type, $settings['notification_types'] ?? array())); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Security -->
                <div id="security" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="required_capabilities">Required Capabilities</label>
                            </th>
                            <td>
                                <select id="required_capabilities" name="settings[required_capabilities]">
                                    <option value="edit_posts" <?php selected($settings['required_capabilities'] ?? 'edit_posts', 'edit_posts'); ?>>
                                        Author
                                    </option>
                                    <option value="edit_others_posts" <?php selected($settings['required_capabilities'] ?? 'edit_posts', 'edit_others_posts'); ?>>
                                        Editor
                                    </option>
                                    <option value="manage_options" <?php selected($settings['required_capabilities'] ?? 'edit_posts', 'manage_options'); ?>>
                                        Administrator
                                    </option>
                                </select>
                                <p class="description">Minimum capability required to manage reports and security groups.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="enable_recaptcha">reCAPTCHA Protection</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_recaptcha" 
                                           name="settings[enable_recaptcha]" value="1" 
                                           <?php checked($settings['enable_recaptcha'] ?? false, true); ?>>
                                    Enable reCAPTCHA on public forms
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="recaptcha_site_key">reCAPTCHA Site Key</label>
                            </th>
                            <td>
                                <input type="text" id="recaptcha_site_key" 
                                       name="settings[recaptcha_site_key]" 
                                       value="<?php echo esc_attr($settings['recaptcha_site_key'] ?? ''); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="recaptcha_secret_key">reCAPTCHA Secret Key</label>
                            </th>
                            <td>
                                <input type="password" id="recaptcha_secret_key" 
                                       name="settings[recaptcha_secret_key]" 
                                       value="<?php echo esc_attr($settings['recaptcha_secret_key'] ?? ''); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Integrations -->
                <div id="integrations" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_whatsapp">WhatsApp Integration</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_whatsapp" 
                                           name="settings[enable_whatsapp]" value="1" 
                                           <?php checked($settings['enable_whatsapp'] ?? false, true); ?>>
                                    Enable WhatsApp notifications
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="whatsapp_api_key">WhatsApp API Key</label>
                            </th>
                            <td>
                                <input type="password" id="whatsapp_api_key" 
                                       name="settings[whatsapp_api_key]" 
                                       value="<?php echo esc_attr($settings['whatsapp_api_key'] ?? ''); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="enable_telegram">Telegram Integration</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_telegram" 
                                           name="settings[enable_telegram]" value="1" 
                                           <?php checked($settings['enable_telegram'] ?? false, true); ?>>
                                    Enable Telegram notifications
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="telegram_bot_token">Telegram Bot Token</label>
                            </th>
                            <td>
                                <input type="password" id="telegram_bot_token" 
                                       name="settings[telegram_bot_token]" 
                                       value="<?php echo esc_attr($settings['telegram_bot_token'] ?? ''); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>

    <style>
    .settings-tabs .nav-tab-wrapper {
        margin-bottom: 20px;
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            // Update tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Update content
            $('.tab-content').removeClass('active').hide();
            $(target).addClass('active').show();
        });
    });
    </script>
    <?php
}

function sandcrime_save_settings($post_data) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = array();
    
    // General Settings
    $settings['default_report_status'] = sanitize_text_field($post_data['settings']['default_report_status']);
    $settings['reports_per_page'] = absint($post_data['settings']['reports_per_page']);
    $settings['enable_public_reports'] = isset($post_data['settings']['enable_public_reports']) ? 1 : 0;
    
    // Notification Settings
    $settings['enable_email_notifications'] = isset($post_data['settings']['enable_email_notifications']) ? 1 : 0;
    $settings['notification_emails'] = sanitize_textarea_field($post_data['settings']['notification_emails']);
    $settings['notification_types'] = isset($post_data['settings']['notification_types']) ? 
        array_map('sanitize_text_field', $post_data['settings']['notification_types']) : array();
    
    // Security Settings
    $settings['required_capabilities'] = sanitize_text_field($post_data['settings']['required_capabilities']);
    $settings['enable_recaptcha'] = isset($post_data['settings']['enable_recaptcha']) ? 1 : 0;
    $settings['recaptcha_site_key'] = sanitize_text_field($post_data['settings']['recaptcha_site_key']);
    $settings['recaptcha_secret_key'] = sanitize_text_field($post_data['settings']['recaptcha_secret_key']);
    
    // Integration Settings
    $settings['enable_whatsapp'] = isset($post_data['settings']['enable_whatsapp']) ? 1 : 0;
    $settings['whatsapp_api_key'] = sanitize_text_field($post_data['settings']['whatsapp_api_key']);
    $settings['enable_telegram'] = isset($post_data['settings']['enable_telegram']) ? 1 : 0;
    $settings['telegram_bot_token'] = sanitize_text_field($post_data['settings']['telegram_bot_token']);
    
    update_option('sandcrime_settings', $settings);
    
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
    });
}