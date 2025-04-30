<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Settings {
    private $options;

    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_assets'));
        add_action('wp_ajax_test_notification_delivery', array($this, 'test_notification_delivery'));
    }

    public function register_settings() {
        register_setting('sandcrime_notification_settings', 'sandcrime_notification_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        // General Settings
        add_settings_section(
            'sandcrime_general_settings',
            __('General Settings', 'sandcrime'),
            array($this, 'render_general_section'),
            'sandcrime_notification_settings'
        );

        // Email Settings
        add_settings_section(
            'sandcrime_email_settings',
            __('Email Settings', 'sandcrime'),
            array($this, 'render_email_section'),
            'sandcrime_notification_settings'
        );

        // Push Notification Settings
        add_settings_section(
            'sandcrime_push_settings',
            __('Push Notification Settings', 'sandcrime'),
            array($this, 'render_push_section'),
            'sandcrime_notification_settings'
        );

        // Queue Settings
        add_settings_section(
            'sandcrime_queue_settings',
            __('Queue Settings', 'sandcrime'),
            array($this, 'render_queue_section'),
            'sandcrime_notification_settings'
        );

        $this->register_setting_fields();
    }

    private function register_setting_fields() {
        // General Settings Fields
        add_settings_field(
            'enable_notifications',
            __('Enable Notifications', 'sandcrime'),
            array($this, 'render_checkbox_field'),
            'sandcrime_notification_settings',
            'sandcrime_general_settings',
            array(
                'id' => 'enable_notifications',
                'description' => __('Enable or disable the entire notification system', 'sandcrime')
            )
        );

        add_settings_field(
            'notification_types',
            __('Notification Types', 'sandcrime'),
            array($this, 'render_multiselect_field'),
            'sandcrime_notification_settings',
            'sandcrime_general_settings',
            array(
                'id' => 'notification_types',
                'options' => array(
                    'alert' => __('Alerts', 'sandcrime'),
                    'report' => __('Reports', 'sandcrime'),
                    'message' => __('Messages', 'sandcrime'),
                    'update' => __('Updates', 'sandcrime')
                ),
                'description' => __('Select the types of notifications to enable', 'sandcrime')
            )
        );

        // Email Settings Fields
        add_settings_field(
            'email_template',
            __('Email Template', 'sandcrime'),
            array($this, 'render_editor_field'),
            'sandcrime_notification_settings',
            'sandcrime_email_settings',
            array(
                'id' => 'email_template',
                'description' => __('HTML template for notification emails. Use {{content}} for notification content', 'sandcrime')
            )
        );

        add_settings_field(
            'email_batch_size',
            __('Email Batch Size', 'sandcrime'),
            array($this, 'render_number_field'),
            'sandcrime_notification_settings',
            'sandcrime_email_settings',
            array(
                'id' => 'email_batch_size',
                'min' => 1,
                'max' => 1000,
                'description' => __('Number of emails to send in each batch', 'sandcrime')
            )
        );

        // Push Settings Fields
        add_settings_field(
            'vapid_public_key',
            __('VAPID Public Key', 'sandcrime'),
            array($this, 'render_text_field'),
            'sandcrime_notification_settings',
            'sandcrime_push_settings',
            array(
                'id' => 'vapid_public_key',
                'description' => __('Public key for Web Push notifications', 'sandcrime')
            )
        );

        add_settings_field(
            'vapid_private_key',
            __('VAPID Private Key', 'sandcrime'),
            array($this, 'render_password_field'),
            'sandcrime_notification_settings',
            'sandcrime_push_settings',
            array(
                'id' => 'vapid_private_key',
                'description' => __('Private key for Web Push notifications', 'sandcrime')
            )
        );

        // Queue Settings Fields
        add_settings_field(
            'queue_batch_size',
            __('Queue Batch Size', 'sandcrime'),
            array($this, 'render_number_field'),
            'sandcrime_notification_settings',
            'sandcrime_queue_settings',
            array(
                'id' => 'queue_batch_size',
                'min' => 1,
                'max' => 1000,
                'description' => __('Number of notifications to process in each queue run', 'sandcrime')
            )
        );

        add_settings_field(
            'queue_retry_limit',
            __('Retry Limit', 'sandcrime'),
            array($this, 'render_number_field'),
            'sandcrime_notification_settings',
            'sandcrime_queue_settings',
            array(
                'id' => 'queue_retry_limit',
                'min' => 1,
                'max' => 10,
                'description' => __('Maximum number of retry attempts for failed notifications', 'sandcrime')
            )
        );
    }

    public function render_checkbox_field($args) {
        $id = $args['id'];
        $value = $this->get_option($id);
        ?>
        <label>
            <input type="checkbox" 
                   id="<?php echo esc_attr($id); ?>"
                   name="sandcrime_notification_settings[<?php echo esc_attr($id); ?>]"
                   value="1"
                   <?php checked(1, $value); ?>>
            <?php echo esc_html($args['description']); ?>
        </label>
        <?php
    }

    public function render_multiselect_field($args) {
        $id = $args['id'];
        $value = $this->get_option($id, array());
        ?>
        <select multiple
                id="<?php echo esc_attr($id); ?>"
                name="sandcrime_notification_settings[<?php echo esc_attr($id); ?>][]"
                class="sandcrime-multiselect">
            <?php foreach ($args['options'] as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>"
                        <?php selected(in_array($key, $value), true); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function render_editor_field($args) {
        $id = $args['id'];
        $value = $this->get_option($id);
        wp_editor(
            $value,
            $id,
            array(
                'textarea_name' => "sandcrime_notification_settings[$id]",
                'textarea_rows' => 10,
                'media_buttons' => false,
                'teeny' => true
            )
        );
        ?>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function render_number_field($args) {
        $id = $args['id'];
        $value = $this->get_option($id);
        ?>
        <input type="number"
               id="<?php echo esc_attr($id); ?>"
               name="sandcrime_notification_settings[<?php echo esc_attr($id); ?>]"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($args['min']); ?>"
               max="<?php echo esc_attr($args['max']); ?>"
               class="small-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function render_text_field($args) {
        $id = $args['id'];
        $value = $this->get_option($id);
        ?>
        <input type="text"
               id="<?php echo esc_attr($id); ?>"
               name="sandcrime_notification_settings[<?php echo esc_attr($id); ?>]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function render_password_field($args) {
        $id = $args['id'];
        $value = $this->get_option($id);
        ?>
        <input type="password"
               id="<?php echo esc_attr($id); ?>"
               name="sandcrime_notification_settings[<?php echo esc_attr($id); ?>]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function sanitize_settings($input) {
        $sanitized = array();

        foreach ($input as $key => $value) {
            switch ($key) {
                case 'enable_notifications':
                    $sanitized[$key] = (bool) $value;
                    break;
                case 'notification_types':
                    $sanitized[$key] = array_map('sanitize_text_field', $value);
                    break;
                case 'email_template':
                    $sanitized[$key] = wp_kses_post($value);
                    break;
                case 'email_batch_size':
                case 'queue_batch_size':
                case 'queue_retry_limit':
                    $sanitized[$key] = absint($value);
                    break;
                case 'vapid_public_key':
                case 'vapid_private_key':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    private function get_option($key, $default = '') {
        if (empty($this->options)) {
            $this->options = get_option('sandcrime_notification_settings', array());
        }
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    public function enqueue_settings_assets($hook) {
        if ('settings_page_sandcrime-notification-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('select2');
        wp_enqueue_script('select2');

        wp_enqueue_script(
            'sandcrime-notification-settings',
            plugins_url('assets/js/notification-settings.js', SANDCRIME_PLUGIN_FILE),
            array('jquery', 'select2'),
            SANDCRIME_VERSION,
            true
        );

        wp_localize_script('sandcrime-notification-settings', 'sandcrimeSettings', array(
            'nonce' => wp_create_nonce('sandcrime_settings'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => array(
                'testSuccess' => __('Test notification sent successfully!', 'sandcrime'),
                'testError' => __('Failed to send test notification', 'sandcrime')
            )
        ));
    }

    public function test_notification_delivery() {
        check_ajax_referer('sandcrime_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $user_id = get_current_user_id();

        try {
            $notification_handler = new SandCrime_Notification_Handler();
            $result = $notification_handler->create_notification(
                $user_id,
                array(
                    'type' => 'test',
                    'title' => __('Test Notification', 'sandcrime'),
                    'message' => __('This is a test notification from the settings page.', 'sandcrime'),
                    'priority' => 'normal'
                )
            );

            if ($result) {
                wp_send_json_success(__('Test notification created successfully', 'sandcrime'));
            } else {
                throw new Exception(__('Failed to create test notification', 'sandcrime'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}

// Initialize the settings
new SandCrime_Notification_Settings();