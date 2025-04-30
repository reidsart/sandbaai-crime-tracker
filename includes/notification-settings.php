<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Settings {
    private $options_key = 'sandcrime_notification_settings';
    private $default_settings;

    public function __construct() {
        $this->default_settings = array(
            'email' => array(
                'enabled' => true,
                'from_name' => get_bloginfo('name'),
                'from_email' => get_bloginfo('admin_email'),
                'reply_to' => get_bloginfo('admin_email'),
                'template_id' => 0
            ),
            'push' => array(
                'enabled' => false,
                'vapid_public_key' => '',
                'vapid_private_key' => '',
                'icon' => '',
                'template_id' => 0
            ),
            'sms' => array(
                'enabled' => false,
                'provider' => 'twilio',
                'twilio_sid' => '',
                'twilio_token' => '',
                'twilio_number' => '',
                'template_id' => 0
            ),
            'general' => array(
                'batch_size' => 50,
                'retry_attempts' => 3,
                'retry_interval' => 300, // 5 minutes
                'log_retention' => 30, // days
                'default_priority' => 'normal'
            ),
            'rate_limiting' => array(
                'enabled' => true,
                'max_per_hour' => 100,
                'max_per_day' => 1000,
                'exempt_roles' => array('administrator')
            )
        );

        add_action('admin_menu', array($this, 'add_settings_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_test_notification_settings', array($this, 'ajax_test_settings'));
    }

    public function add_settings_menu() {
        add_submenu_page(
            'sandcrime-notifications',
            __('Notification Settings', 'sandcrime'),
            __('Settings', 'sandcrime'),
            'manage_options',
            'sandcrime-notification-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(
            'sandcrime_notification_settings',
            $this->options_key,
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );

        // General Settings Section
        add_settings_section(
            'sandcrime_general_settings',
            __('General Settings', 'sandcrime'),
            array($this, 'render_general_section'),
            'sandcrime-notification-settings'
        );

        // Email Settings Section
        add_settings_section(
            'sandcrime_email_settings',
            __('Email Settings', 'sandcrime'),
            array($this, 'render_email_section'),
            'sandcrime-notification-settings'
        );

        // Push Notification Settings Section
        add_settings_section(
            'sandcrime_push_settings',
            __('Push Notification Settings', 'sandcrime'),
            array($this, 'render_push_section'),
            'sandcrime-notification-settings'
        );

        // SMS Settings Section
        add_settings_section(
            'sandcrime_sms_settings',
            __('SMS Settings', 'sandcrime'),
            array($this, 'render_sms_section'),
            'sandcrime-notification-settings'
        );

        // Rate Limiting Settings Section
        add_settings_section(
            'sandcrime_rate_limiting_settings',
            __('Rate Limiting', 'sandcrime'),
            array($this, 'render_rate_limiting_section'),
            'sandcrime-notification-settings'
        );

        $this->add_settings_fields();
    }

    private function add_settings_fields() {
        // General Settings Fields
        add_settings_field(
            'batch_size',
            __('Batch Size', 'sandcrime'),
            array($this, 'render_number_field'),
            'sandcrime-notification-settings',
            'sandcrime_general_settings',
            array(
                'label_for' => 'batch_size',
                'path' => 'general.batch_size',
                'description' => __('Number of notifications to process in each batch', 'sandcrime'),
                'min' => 1,
                'max' => 100
            )
        );

        add_settings_field(
            'retry_attempts',
            __('Retry Attempts', 'sandcrime'),
            array($this, 'render_number_field'),
            'sandcrime-notification-settings',
            'sandcrime_general_settings',
            array(
                'label_for' => 'retry_attempts',
                'path' => 'general.retry_attempts',
                'description' => __('Number of times to retry failed notifications', 'sandcrime'),
                'min' => 0,
                'max' => 10
            )
        );

        // Email Settings Fields
        add_settings_field(
            'email_enabled',
            __('Enable Email Notifications', 'sandcrime'),
            array($this, 'render_checkbox_field'),
            'sandcrime-notification-settings',
            'sandcrime_email_settings',
            array(
                'label_for' => 'email_enabled',
                'path' => 'email.enabled'
            )
        );

        add_settings_field(
            'from_name',
            __('From Name', 'sandcrime'),
            array($this, 'render_text_field'),
            'sandcrime-notification-settings',
            'sandcrime_email_settings',
            array(
                'label_for' => 'from_name',
                'path' => 'email.from_name'
            )
        );

        // Push Notification Settings Fields
        add_settings_field(
            'push_enabled',
            __('Enable Push Notifications', 'sandcrime'),
            array($this, 'render_checkbox_field'),
            'sandcrime-notification-settings',
            'sandcrime_push_settings',
            array(
                'label_for' => 'push_enabled',
                'path' => 'push.enabled'
            )
        );

        add_settings_field(
            'vapid_keys',
            __('VAPID Keys', 'sandcrime'),
            array($this, 'render_vapid_keys_field'),
            'sandcrime-notification-settings',
            'sandcrime_push_settings'
        );

        // SMS Settings Fields
        add_settings_field(
            'sms_enabled',
            __('Enable SMS Notifications', 'sandcrime'),
            array($this, 'render_checkbox_field'),
            'sandcrime-notification-settings',
            'sandcrime_sms_settings',
            array(
                'label_for' => 'sms_enabled',
                'path' => 'sms.enabled'
            )
        );

        add_settings_field(
            'twilio_credentials',
            __('Twilio Credentials', 'sandcrime'),
            array($this, 'render_twilio_credentials_field'),
            'sandcrime-notification-settings',
            'sandcrime_sms_settings'
        );

        // Rate Limiting Settings Fields
        add_settings_field(
            'rate_limiting_enabled',
            __('Enable Rate Limiting', 'sandcrime'),
            array($this, 'render_checkbox_field'),
            'sandcrime-notification-settings',
            'sandcrime_rate_limiting_settings',
            array(
                'label_for' => 'rate_limiting_enabled',
                'path' => 'rate_limiting.enabled'
            )
        );

        add_settings_field(
            'rate_limits',
            __('Rate Limits', 'sandcrime'),
            array($this, 'render_rate_limits_field'),
            'sandcrime-notification-settings',
            'sandcrime_rate_limiting_settings'
        );
    }

    public function get_settings() {
        $settings = get_option($this->options_key, array());
        return wp_parse_args($settings, $this->default_settings);
    }

    public function get_setting($path) {
        $settings = $this->get_settings();
        $parts = explode('.', $path);
        $value = $settings;

        foreach ($parts as $part) {
            if (!isset($value[$part])) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=sandcrime-notification-settings&tab=general" 
                   class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'sandcrime'); ?>
                </a>
                <a href="?page=sandcrime-notification-settings&tab=email" 
                   class="nav-tab <?php echo $active_tab == 'email' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Email', 'sandcrime'); ?>
                </a>
                <a href="?page=sandcrime-notification-settings&tab=push" 
                   class="nav-tab <?php echo $active_tab == 'push' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Push', 'sandcrime'); ?>
                </a>
                <a href="?page=sandcrime-notification-settings&tab=sms" 
                   class="nav-tab <?php echo $active_tab == 'sms' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('SMS', 'sandcrime'); ?>
                </a>
                <a href="?page=sandcrime-notification-settings&tab=rate-limiting" 
                   class="nav-tab <?php echo $active_tab == 'rate-limiting' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Rate Limiting', 'sandcrime'); ?>
                </a>
            </h2>

            <form action="options.php" method="post">
                <?php
                settings_fields('sandcrime_notification_settings');
                do_settings_sections('sandcrime-notification-settings');
                submit_button();
                ?>
            </form>

            <div class="test-settings-section">
                <h3><?php _e('Test Settings', 'sandcrime'); ?></h3>
                <p><?php _e('Send a test notification to verify your settings.', 'sandcrime'); ?></p>
                <div class="test-controls">
                    <input type="email" 
                           id="test-recipient" 
                           placeholder="<?php esc_attr_e('Recipient email/phone', 'sandcrime'); ?>">
                    <select id="test-type">
                        <option value="email"><?php _e('Email', 'sandcrime'); ?></option>
                        <option value="push"><?php _e('Push', 'sandcrime'); ?></option>
                        <option value="sms"><?php _e('SMS', 'sandcrime'); ?></option>
                    </select>
                    <button type="button" 
                            class="button button-secondary" 
                            id="send-test">
                        <?php _e('Send Test', 'sandcrime'); ?>
                    </button>
                </div>
                <div id="test-result"></div>
            </div>
        </div>
        <?php
    }
    
    public function render_text_field($args) {
        $settings = $this->get_settings();
        $value = $this->get_setting($args['path']);
        ?>
        <input type="text" 
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($this->options_key . '[' . $args['path'] . ']'); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <?php
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_number_field($args) {
        $value = $this->get_setting($args['path']);
        ?>
        <input type="number"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($this->options_key . '[' . $args['path'] . ']'); ?>"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo isset($args['min']) ? esc_attr($args['min']) : '0'; ?>"
               max="<?php echo isset($args['max']) ? esc_attr($args['max']) : ''; ?>"
               class="small-text">
        <?php
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_checkbox_field($args) {
        $value = $this->get_setting($args['path']);
        ?>
        <label>
            <input type="checkbox"
                   id="<?php echo esc_attr($args['label_for']); ?>"
                   name="<?php echo esc_attr($this->options_key . '[' . $args['path'] . ']'); ?>"
                   value="1"
                   <?php checked(1, $value); ?>>
            <?php echo isset($args['label']) ? esc_html($args['label']) : ''; ?>
        </label>
        <?php
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_select_field($args) {
        $value = $this->get_setting($args['path']);
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>"
                name="<?php echo esc_attr($this->options_key . '[' . $args['path'] . ']'); ?>">
            <?php foreach ($args['options'] as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>"
                        <?php selected($key, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_vapid_keys_field() {
        $public_key = $this->get_setting('push.vapid_public_key');
        $private_key = $this->get_setting('push.vapid_private_key');
        ?>
        <div class="vapid-keys-wrapper">
            <div class="vapid-key-field">
                <label for="vapid_public_key"><?php _e('Public Key', 'sandcrime'); ?></label>
                <input type="text"
                       id="vapid_public_key"
                       name="<?php echo esc_attr($this->options_key . '[push][vapid_public_key]'); ?>"
                       value="<?php echo esc_attr($public_key); ?>"
                       class="regular-text">
            </div>
            <div class="vapid-key-field">
                <label for="vapid_private_key"><?php _e('Private Key', 'sandcrime'); ?></label>
                <input type="password"
                       id="vapid_private_key"
                       name="<?php echo esc_attr($this->options_key . '[push][vapid_private_key]'); ?>"
                       value="<?php echo esc_attr($private_key); ?>"
                       class="regular-text">
            </div>
            <button type="button" 
                    class="button button-secondary" 
                    id="generate-vapid-keys">
                <?php _e('Generate Keys', 'sandcrime'); ?>
            </button>
            <p class="description">
                <?php _e('VAPID keys are required for push notifications. Click "Generate Keys" to create a new pair.', 'sandcrime'); ?>
            </p>
        </div>
        <?php
    }

    public function render_twilio_credentials_field() {
        $sid = $this->get_setting('sms.twilio_sid');
        $token = $this->get_setting('sms.twilio_token');
        $number = $this->get_setting('sms.twilio_number');
        ?>
        <div class="twilio-credentials-wrapper">
            <div class="twilio-field">
                <label for="twilio_sid"><?php _e('Account SID', 'sandcrime'); ?></label>
                <input type="text"
                       id="twilio_sid"
                       name="<?php echo esc_attr($this->options_key . '[sms][twilio_sid]'); ?>"
                       value="<?php echo esc_attr($sid); ?>"
                       class="regular-text">
            </div>
            <div class="twilio-field">
                <label for="twilio_token"><?php _e('Auth Token', 'sandcrime'); ?></label>
                <input type="password"
                       id="twilio_token"
                       name="<?php echo esc_attr($this->options_key . '[sms][twilio_token]'); ?>"
                       value="<?php echo esc_attr($token); ?>"
                       class="regular-text">
            </div>
            <div class="twilio-field">
                <label for="twilio_number"><?php _e('From Number', 'sandcrime'); ?></label>
                <input type="text"
                       id="twilio_number"
                       name="<?php echo esc_attr($this->options_key . '[sms][twilio_number]'); ?>"
                       value="<?php echo esc_attr($number); ?>"
                       class="regular-text"
                       placeholder="+1234567890">
            </div>
            <p class="description">
                <?php _e('Enter your Twilio credentials to enable SMS notifications.', 'sandcrime'); ?>
            </p>
        </div>
        <?php
    }

    public function render_rate_limits_field() {
        $max_per_hour = $this->get_setting('rate_limiting.max_per_hour');
        $max_per_day = $this->get_setting('rate_limiting.max_per_day');
        $exempt_roles = $this->get_setting('rate_limiting.exempt_roles');
        ?>
        <div class="rate-limits-wrapper">
            <div class="rate-limit-field">
                <label for="max_per_hour"><?php _e('Max Per Hour', 'sandcrime'); ?></label>
                <input type="number"
                       id="max_per_hour"
                       name="<?php echo esc_attr($this->options_key . '[rate_limiting][max_per_hour]'); ?>"
                       value="<?php echo esc_attr($max_per_hour); ?>"
                       min="0"
                       class="small-text">
            </div>
            <div class="rate-limit-field">
                <label for="max_per_day"><?php _e('Max Per Day', 'sandcrime'); ?></label>
                <input type="number"
                       id="max_per_day"
                       name="<?php echo esc_attr($this->options_key . '[rate_limiting][max_per_day]'); ?>"
                       value="<?php echo esc_attr($max_per_day); ?>"
                       min="0"
                       class="small-text">
            </div>
            <div class="rate-limit-field">
                <label><?php _e('Exempt Roles', 'sandcrime'); ?></label>
                <?php
                $all_roles = wp_roles()->get_names();
                foreach ($all_roles as $role => $label) {
                    ?>
                    <label class="role-checkbox">
                        <input type="checkbox"
                               name="<?php echo esc_attr($this->options_key . '[rate_limiting][exempt_roles][]'); ?>"
                               value="<?php echo esc_attr($role); ?>"
                               <?php checked(in_array($role, (array)$exempt_roles)); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                    <?php
                }
                ?>
            </div>
            <p class="description">
                <?php _e('Configure rate limiting settings to prevent notification abuse.', 'sandcrime'); ?>
            </p>
        </div>
        <?php
    }

    public function sanitize_settings($input) {
        $sanitized = array();

        // General Settings
        $sanitized['general']['batch_size'] = absint($input['general']['batch_size']);
        $sanitized['general']['retry_attempts'] = absint($input['general']['retry_attempts']);
        $sanitized['general']['retry_interval'] = absint($input['general']['retry_interval']);
        $sanitized['general']['log_retention'] = absint($input['general']['log_retention']);
        $sanitized['general']['default_priority'] = sanitize_text_field($input['general']['default_priority']);

        // Email Settings
        $sanitized['email']['enabled'] = isset($input['email']['enabled']);
        $sanitized['email']['from_name'] = sanitize_text_field($input['email']['from_name']);
        $sanitized['email']['from_email'] = sanitize_email($input['email']['from_email']);
        $sanitized['email']['reply_to'] = sanitize_email($input['email']['reply_to']);
        $sanitized['email']['template_id'] = absint($input['email']['template_id']);

        // Push Settings
        $sanitized['push']['enabled'] = isset($input['push']['enabled']);
        $sanitized['push']['vapid_public_key'] = sanitize_text_field($input['push']['vapid_public_key']);
        $sanitized['push']['vapid_private_key'] = sanitize_text_field($input['push']['vapid_private_key']);
        $sanitized['push']['icon'] = esc_url_raw($input['push']['icon']);
        $sanitized['push']['template_id'] = absint($input['push']['template_id']);

        // SMS Settings
        $sanitized['sms']['enabled'] = isset($input['sms']['enabled']);
        $sanitized['sms']['provider'] = sanitize_text_field($input['sms']['provider']);
        $sanitized['sms']['twilio_sid'] = sanitize_text_field($input['sms']['twilio_sid']);
        $sanitized['sms']['twilio_token'] = sanitize_text_field($input['sms']['twilio_token']);
        $sanitized['sms']['twilio_number'] = sanitize_text_field($input['sms']['twilio_number']);
        $sanitized['sms']['template_id'] = absint($input['sms']['template_id']);

        // Rate Limiting Settings
        $sanitized['rate_limiting']['enabled'] = isset($input['rate_limiting']['enabled']);
        $sanitized['rate_limiting']['max_per_hour'] = absint($input['rate_limiting']['max_per_hour']);
        $sanitized['rate_limiting']['max_per_day'] = absint($input['rate_limiting']['max_per_day']);
        $sanitized['rate_limiting']['exempt_roles'] = isset($input['rate_limiting']['exempt_roles']) ? 
            array_map('sanitize_text_field', $input['rate_limiting']['exempt_roles']) : array();

        return $sanitized;
    }

    public function ajax_test_settings() {
        check_ajax_referer('sandcrime_test_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'sandcrime'));
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $recipient = isset($_POST['recipient']) ? sanitize_text_field($_POST['recipient']) : '';

        if (empty($type) || empty($recipient)) {
            wp_send_json_error(__('Missing required parameters', 'sandcrime'));
        }

        try {
            $result = $this->send_test_notification($type, $recipient);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    private function send_test_notification($type, $recipient) {
        $notification = array(
            'title' => __('Test Notification', 'sandcrime'),
            'message' => __('This is a test notification from SandCrime.', 'sandcrime'),
            'type' => $type,
            'recipient' => $recipient
        );

        // Use the notification manager to send the test
        $notification_manager = new SandCrime_Notification_Manager();
        return $notification_manager->send($notification);
    }

    public function enqueue_scripts($hook) {
        if ('sandcrime_page_sandcrime-notification-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'sandcrime-settings',
            SANDCRIME_PLUGIN_URL . 'assets/css/notification-settings.css',
            array(),
            SANDCRIME_VERSION
        );

        wp_enqueue_script(
            'sandcrime-settings',
            SANDCRIME_PLUGIN_URL . 'assets/js/notification-settings.js',
            array('jquery'),
            SANDCRIME_VERSION,
            true
        );

        wp_localize_script('sandcrime-settings', 'sandcrimeSettings', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sandcrime_test_settings'),
            'i18n' => array(
                'testSuccess' => __('Test notification sent successfully!', 'sandcrime'),
                'testError' => __('Failed to send test notification:', 'sandcrime'),
                'confirmReset' => __('Are you sure you want to reset all settings to defaults?', 'sandcrime')
            )
        ));
    }
}

// Initialize settings
new SandCrime_Notification_Settings();