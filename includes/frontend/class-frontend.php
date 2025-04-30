<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Frontend {
    private $settings;
    private $push_manager;

    public function __construct() {
        $this->settings = new SandCrime_Notification_Settings();
        $this->push_manager = new SandCrime_Push_Manager();
        $this->init_hooks();
    }

    private function init_hooks() {
        // User preferences
        add_action('show_user_profile', array($this, 'add_notification_preferences'));
        add_action('edit_user_profile', array($this, 'add_notification_preferences'));
        add_action('personal_options_update', array($this, 'save_notification_preferences'));
        add_action('edit_user_profile_update', array($this, 'save_notification_preferences'));

        // Push notification subscription
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'render_push_dialog'));
        add_action('wp_ajax_sandcrime_push_subscribe', array($this, 'ajax_push_subscribe'));
        add_action('wp_ajax_sandcrime_push_unsubscribe', array($this, 'ajax_push_unsubscribe'));

        // Notification center
        add_shortcode('sandcrime_notification_center', array($this, 'render_notification_center'));
        add_action('wp_ajax_sandcrime_mark_notification_read', array($this, 'ajax_mark_notification_read'));
        add_action('wp_ajax_sandcrime_get_notifications', array($this, 'ajax_get_notifications'));
    }

    public function enqueue_assets() {
        if (!is_user_logged_in()) {
            return;
        }

        wp_enqueue_style(
            'sandcrime-frontend',
            SANDCRIME_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            SANDCRIME_VERSION
        );

        wp_enqueue_script(
            'sandcrime-frontend',
            SANDCRIME_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            SANDCRIME_VERSION,
            true
        );

        if ($this->settings->get_setting('push.enabled')) {
            wp_enqueue_script(
                'sandcrime-push',
                SANDCRIME_PLUGIN_URL . 'assets/js/push.js',
                array('sandcrime-frontend'),
                SANDCRIME_VERSION,
                true
            );
        }

        wp_localize_script('sandcrime-frontend', 'sandcrimeFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sandcrime_frontend'),
            'pushEnabled' => $this->settings->get_setting('push.enabled'),
            'pushPublicKey' => $this->settings->get_setting('push.public_key'),
            'i18n' => array(
                'confirmUnsubscribe' => __('Are you sure you want to unsubscribe from notifications?', 'sandcrime'),
                'errorOccurred' => __('An error occurred. Please try again.', 'sandcrime'),
                'noNotifications' => __('No notifications found.', 'sandcrime'),
                'loadMore' => __('Load More', 'sandcrime'),
                'loading' => __('Loading...', 'sandcrime')
            )
        ));
    }

    public function add_notification_preferences($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $preferences = get_user_meta($user->ID, '_notification_preferences', true);
        if (!$preferences) {
            $preferences = array(
                'email' => true,
                'push' => true,
                'sms' => false,
                'quiet_hours' => array(
                    'enabled' => false,
                    'start' => '22:00',
                    'end' => '07:00',
                    'timezone' => get_user_meta($user->ID, 'timezone', true) ?: 'UTC'
                )
            );
        }
        ?>
        <h2><?php esc_html_e('Notification Preferences', 'sandcrime'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Notification Methods', 'sandcrime'); ?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <?php esc_html_e('Notification Methods', 'sandcrime'); ?>
                        </legend>
                        
                        <label>
                            <input type="checkbox" 
                                   name="notification_preferences[email]" 
                                   value="1"
                                   <?php checked($preferences['email']); ?>>
                            <?php esc_html_e('Email Notifications', 'sandcrime'); ?>
                        </label><br>
                        
                        <label>
                            <input type="checkbox" 
                                   name="notification_preferences[push]" 
                                   value="1"
                                   <?php checked($preferences['push']); ?>>
                            <?php esc_html_e('Push Notifications', 'sandcrime'); ?>
                        </label><br>
                        
                        <label>
                            <input type="checkbox" 
                                   name="notification_preferences[sms]" 
                                   value="1"
                                   <?php checked($preferences['sms']); ?>>
                            <?php esc_html_e('SMS Notifications', 'sandcrime'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Phone Number', 'sandcrime'); ?></th>
                <td>
                    <input type="tel" 
                           name="notification_preferences[phone]" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, '_phone_number', true)); ?>"
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e('Required for SMS notifications. International format: +1234567890', 'sandcrime'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Quiet Hours', 'sandcrime'); ?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <?php esc_html_e('Quiet Hours', 'sandcrime'); ?>
                        </legend>
                        
                        <label>
                            <input type="checkbox" 
                                   name="notification_preferences[quiet_hours][enabled]" 
                                   value="1"
                                   <?php checked($preferences['quiet_hours']['enabled']); ?>>
                            <?php esc_html_e('Enable Quiet Hours', 'sandcrime'); ?>
                        </label><br>
                        
                        <div class="quiet-hours-settings" 
                             style="<?php echo $preferences['quiet_hours']['enabled'] ? '' : 'display: none;'; ?>">
                            <label>
                                <?php esc_html_e('Start Time:', 'sandcrime'); ?>
                                <input type="time" 
                                       name="notification_preferences[quiet_hours][start]" 
                                       value="<?php echo esc_attr($preferences['quiet_hours']['start']); ?>">
                            </label><br>
                            
                            <label>
                                <?php esc_html_e('End Time:', 'sandcrime'); ?>
                                <input type="time" 
                                       name="notification_preferences[quiet_hours][end]" 
                                       value="<?php echo esc_attr($preferences['quiet_hours']['end']); ?>">
                            </label><br>
                            
                            <label>
                                <?php esc_html_e('Timezone:', 'sandcrime'); ?>
                                <select name="notification_preferences[quiet_hours][timezone]">
                                    <?php
                                    $timezones = DateTimeZone::listIdentifiers();
                                    foreach ($timezones as $timezone) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($timezone),
                                            selected($timezone, $preferences['quiet_hours']['timezone'], false),
                                            esc_html($timezone)
                                        );
                                    }
                                    ?>
                                </select>
                            </label>
                        </div>
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_notification_preferences($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        $preferences = isset($_POST['notification_preferences']) ? 
            $this->sanitize_preferences($_POST['notification_preferences']) : 
            array();

        update_user_meta($user_id, '_notification_preferences', $preferences);

        if (isset($preferences['phone'])) {
            update_user_meta($user_id, '_phone_number', $preferences['phone']);
        }
    }

    private function sanitize_preferences($preferences) {
        return array(
            'email' => isset($preferences['email']),
            'push' => isset($preferences['push']),
            'sms' => isset($preferences['sms']),
            'phone' => isset($preferences['phone']) ? 
                sanitize_text_field($preferences['phone']) : '',
            'quiet_hours' => array(
                'enabled' => isset($preferences['quiet_hours']['enabled']),
                'start' => isset($preferences['quiet_hours']['start']) ? 
                    sanitize_text_field($preferences['quiet_hours']['start']) : '22:00',
                'end' => isset($preferences['quiet_hours']['end']) ? 
                    sanitize_text_field($preferences['quiet_hours']['end']) : '07:00',
                'timezone' => isset($preferences['quiet_hours']['timezone']) ? 
                    sanitize_text_field($preferences['quiet_hours']['timezone']) : 'UTC'
            )
        );
    }

    public function render_push_dialog() {
        if (!is_user_logged_in() || 
            !$this->settings->get_setting('push.enabled') || 
            !$this->should_show_push_prompt()) {
            return;
        }
        ?>
        <div id="sandcrime-push-prompt" style="display: none;">
            <div class="push-prompt-content">
                <h3><?php esc_html_e('Enable Push Notifications', 'sandcrime'); ?></h3>
                <p>
                    <?php esc_html_e('Get instant notifications about important updates and alerts.', 'sandcrime'); ?>
                </p>
                <div class="push-prompt-actions">
                    <button type="button" class="button button-primary" id="enable-push">
                        <?php esc_html_e('Enable', 'sandcrime'); ?>
                    </button>
                    <button type="button" class="button" id="maybe-later">
                        <?php esc_html_e('Maybe Later', 'sandcrime'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    private function should_show_push_prompt() {
        $user_id = get_current_user_id();
        $last_prompt = get_user_meta($user_id, '_push_prompt_last_shown', true);
        $prompt_count = (int) get_user_meta($user_id, '_push_prompt_count', true);

        // Don't show if already subscribed
        if ($this->push_manager->is_user_subscribed($user_id)) {
            return false;
        }

        // Don't show if user has dismissed more than 3 times
        if ($prompt_count >= 3) {
            return false;
        }

        // Don't show if last prompt was less than 7 days ago
        if ($last_prompt && (time() - strtotime($last_prompt)) < (7 * DAY_IN_SECONDS)) {
            return false;
        }

        return true;
    }

    public function ajax_push_subscribe() {
        check_ajax_referer('sandcrime_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to subscribe.', 'sandcrime'));
        }

        $subscription = isset($_POST['subscription']) ? 
            json_decode(stripslashes($_POST['subscription']), true) : 
            null;

        if (!$subscription) {
            wp_send_json_error(__('Invalid subscription data.', 'sandcrime'));
        }

        try {
            $result = $this->push_manager->add_subscription(
                get_current_user_id(),
                $subscription
            );
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_push_unsubscribe() {
        check_ajax_referer('sandcrime_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to unsubscribe.', 'sandcrime'));
        }

        $endpoint = isset($_POST['endpoint']) ? sanitize_text_field($_POST['endpoint']) : '';

        if (!$endpoint) {
            wp_send_json_error(__('Invalid endpoint.', 'sandcrime'));
        }

        try {
            $result = $this->push_manager->remove_subscription(
                get_current_user_id(),
                $endpoint
            );
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function render_notification_center($atts) {
        if (!is_user_logged_in()) {
            return sprintf(
                '<p class="sandcrime-login-required">%s</p>',
                esc_html__('Please log in to view your notifications.', 'sandcrime')
            );
        }

        $atts = shortcode_atts(array(
            'limit' => 10,
            'type' => 'all'
        ), $atts, 'sandcrime_notification_center');

        ob_start();
        ?>
        <div class="sandcrime-notification-center" data-limit="<?php echo esc_attr($atts['limit']); ?>">
            <div class="notification-filters">
                <select class="notification-type-filter">
                    <option value="all"><?php esc_html_e('All Notifications', 'sandcrime'); ?></option>
                    <option value="unread"><?php esc_html_e('Unread', 'sandcrime'); ?></option>
                    <option value="alert"><?php esc_html_e('Alerts', 'sandcrime'); ?></option>
                    <option value="update"><?php esc_html_e('Updates', 'sandcrime'); ?></option>
                </select>
            </div>

            <div class="notification-list"></div>

            <div class="notification-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Loading notifications...', 'sandcrime'); ?>
            </div>

            <div class="notification-load-more" style="display: none;">
                <button type="button" class="button">
                    <?php esc_html_e('Load More', 'sandcrime'); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_mark_notification_read() {
        check_ajax_referer('sandcrime_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to perform this action.', 'sandcrime'));
        }

        $notification_id = isset($_POST['notification_id']) ? 
            sanitize_text_field($_POST['notification_id']) : '';

        if (!$notification_id) {
            wp_send_json_error(__('Invalid notification ID.', 'sandcrime'));
        }

        try {
            $tracking = new SandCrime_Notification_Tracking();
            $result = $tracking->mark_as_read(
                $notification_id,
                get_current_user_id()
            );
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_get_notifications() {
        check_ajax_referer('sandcrime_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to view notifications.', 'sandcrime'));
        }

        $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? absint($_GET['limit']) : 10;
        $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'all';

        try {
            $tracking = new SandCrime_Notification_Tracking();
            $notifications = $tracking->get_user_notifications(
                get_current_user_id(),
                array(
                    'page' => $page,
                    'limit' => $limit,
                    'type' => $type
                )
            );

            $html = '';
            foreach ($notifications['items'] as $notification) {
                $html .= $this->render_notification_item($notification);
            }

            wp_send_json_success(array(
                'html' => $html,
                'has_more' => $notifications['total'] > ($page * $limit),
                'total' => $notifications['total'],
                'unread' => $notifications['unread']
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    private function render_notification_item($notification) {
        $classes = array('notification-item');
        if (!$notification['read']) {
            $classes[] = 'unread';
        }
        if ($notification['priority'] === 'high') {
            $classes[] = 'priority-high';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" 
             data-id="<?php echo esc_attr($notification['id']); ?>">
            
            <div class="notification-icon">
                <?php echo $this->get_notification_icon($notification['type']); ?>
            </div>
            
            <div class="notification-content">
                <div class="notification-header">
                    <h4 class="notification-title">
                        <?php echo esc_html($notification['title']); ?>
                    </h4>
                    <span class="notification-time" title="<?php echo esc_attr($notification['created_at']); ?>">
                        <?php echo esc_html(human_time_diff(
                            strtotime($notification['created_at']),
                            current_time('timestamp')
                        ) . ' ' . __('ago', 'sandcrime')); ?>
                    </span>
                </div>
                
                <div class="notification-body">
                    <?php echo wp_kses_post($notification['message']); ?>
                </div>
                
                <?php if (!empty($notification['actions'])): ?>
                    <div class="notification-actions">
                        <?php foreach ($notification['actions'] as $action): ?>
                            <a href="<?php echo esc_url($action['url']); ?>" 
                               class="button <?php echo esc_attr($action['class']); ?>"
                               <?php echo !empty($action['target']) ? 
                                   'target="' . esc_attr($action['target']) . '"' : ''; ?>>
                                <?php echo esc_html($action['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!$notification['read']): ?>
                <button type="button" class="mark-read" title="<?php esc_attr_e('Mark as read', 'sandcrime'); ?>">
                    <span class="screen-reader-text">
                        <?php esc_html_e('Mark as read', 'sandcrime'); ?>
                    </span>
                </button>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_notification_icon($type) {
        $icons = array(
            'alert' => '<svg class="icon icon-alert" viewBox="0 0 24 24">
                           <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/>
                       </svg>',
            'update' => '<svg class="icon icon-update" viewBox="0 0 24 24">
                            <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/>
                        </svg>',
            'message' => '<svg class="icon icon-message" viewBox="0 0 24 24">
                             <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                         </svg>'
        );

        return isset($icons[$type]) ? $icons[$type] : $icons['message'];
    }

    public function register_notification_endpoint() {
        register_rest_route('sandcrime/v1', '/notifications', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_notifications_endpoint'),
                'permission_callback' => array($this, 'check_notification_permissions'),
                'args' => array(
                    'page' => array(
                        'default' => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'limit' => array(
                        'default' => 10,
                        'sanitize_callback' => 'absint',
                    ),
                    'type' => array(
                        'default' => 'all',
                        'sanitize_callback' => 'sanitize_key',
                    ),
                ),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'mark_notification_read_endpoint'),
                'permission_callback' => array($this, 'check_notification_permissions'),
                'args' => array(
                    'notification_id' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));
    }

    public function check_notification_permissions() {
        return is_user_logged_in();
    }

    public function get_notifications_endpoint(WP_REST_Request $request) {
        try {
            $tracking = new SandCrime_Notification_Tracking();
            $notifications = $tracking->get_user_notifications(
                get_current_user_id(),
                array(
                    'page' => $request->get_param('page'),
                    'limit' => $request->get_param('limit'),
                    'type' => $request->get_param('type')
                )
            );

            return new WP_REST_Response($notifications, 200);
        } catch (Exception $e) {
            return new WP_Error(
                'sandcrime_notification_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    public function mark_notification_read_endpoint(WP_REST_Request $request) {
        try {
            $tracking = new SandCrime_Notification_Tracking();
            $result = $tracking->mark_as_read(
                $request->get_param('notification_id'),
                get_current_user_id()
            );

            return new WP_REST_Response($result, 200);
        } catch (Exception $e) {
            return new WP_Error(
                'sandcrime_notification_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}

// Initialize frontend
new SandCrime_Frontend();