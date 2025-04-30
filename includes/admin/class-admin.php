<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Admin {
    private $settings;
    private $analytics;

    public function __construct() {
        $this->settings = new SandCrime_Notification_Settings();
        $this->analytics = new SandCrime_Notification_Analytics();
        
        $this->init_hooks();
    }

    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_menu_pages'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Ajax handlers
        add_action('wp_ajax_sandcrime_search_users', array($this, 'ajax_search_users'));
        add_action('wp_ajax_sandcrime_preview_notification', array($this, 'ajax_preview_notification'));
        add_action('wp_ajax_sandcrime_test_notification', array($this, 'ajax_test_notification'));
        
        // Dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    public function add_menu_pages() {
        add_menu_page(
            __('Notifications', 'sandcrime'),
            __('Notifications', 'sandcrime'),
            'manage_options',
            'sandcrime-notifications',
            array($this, 'render_main_page'),
            'dashicons-bell',
            30
        );

        add_submenu_page(
            'sandcrime-notifications',
            __('Send Notification', 'sandcrime'),
            __('Send New', 'sandcrime'),
            'manage_options',
            'sandcrime-send-notification',
            array($this, 'render_send_page')
        );

        add_submenu_page(
            'sandcrime-notifications',
            __('Security Groups', 'sandcrime'),
            __('Security Groups', 'sandcrime'),
            'manage_options',
            'sandcrime-security-groups',
            array($this, 'render_groups_page')
        );

        add_submenu_page(
            'sandcrime-notifications',
            __('Analytics', 'sandcrime'),
            __('Analytics', 'sandcrime'),
            'manage_options',
            'sandcrime-analytics',
            array($this, 'render_analytics_page')
        );

        add_submenu_page(
            'sandcrime-notifications',
            __('Templates', 'sandcrime'),
            __('Templates', 'sandcrime'),
            'manage_options',
            'sandcrime-templates',
            array($this, 'render_templates_page')
        );

        add_submenu_page(
            'sandcrime-notifications',
            __('Settings', 'sandcrime'),
            __('Settings', 'sandcrime'),
            'manage_options',
            'sandcrime-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_main_page() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=sandcrime-notifications&tab=overview" 
                   class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Overview', 'sandcrime'); ?>
                </a>
                <a href="?page=sandcrime-notifications&tab=queue" 
                   class="nav-tab <?php echo $tab === 'queue' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Queue', 'sandcrime'); ?>
                </a>
                <a href="?page=sandcrime-notifications&tab=logs" 
                   class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Logs', 'sandcrime'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($tab) {
                    case 'queue':
                        $this->render_queue_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    default:
                        $this->render_overview_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_overview_tab() {
        $stats = $this->analytics->get_overview_stats();
        ?>
        <div class="sandcrime-dashboard-widgets">
            <div class="stats-grid">
                <div class="stat-box">
                    <h3><?php _e('Total Sent Today', 'sandcrime'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['sent_today']); ?></div>
                </div>
                <div class="stat-box">
                    <h3><?php _e('Queue Size', 'sandcrime'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['queue_size']); ?></div>
                </div>
                <div class="stat-box">
                    <h3><?php _e('Success Rate', 'sandcrime'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['success_rate']); ?>%</div>
                </div>
                <div class="stat-box">
                    <h3><?php _e('Active Recipients', 'sandcrime'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['active_recipients']); ?></div>
                </div>
            </div>

            <div class="recent-activity">
                <h3><?php _e('Recent Activity', 'sandcrime'); ?></h3>
                <?php $this->render_recent_activity(); ?>
            </div>

            <div class="status-summary">
                <h3><?php _e('System Status', 'sandcrime'); ?></h3>
                <?php $this->render_system_status(); ?>
            </div>
        </div>
        <?php
    }

    private function render_queue_tab() {
        $queue_table = new SandCrime_Queue_List_Table();
        $queue_table->prepare_items();
        ?>
        <div class="wrap">
            <form id="queue-filter" method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
                <input type="hidden" name="tab" value="queue" />
                <?php
                $queue_table->search_box(__('Search Queue', 'sandcrime'), 'queue_search');
                $queue_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    private function render_logs_tab() {
        $logs_table = new SandCrime_Logs_List_Table();
        $logs_table->prepare_items();
        ?>
        <div class="wrap">
            <form id="logs-filter" method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
                <input type="hidden" name="tab" value="logs" />
                <?php
                $logs_table->search_box(__('Search Logs', 'sandcrime'), 'logs_search');
                $logs_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_send_page() {
        $form = new SandCrime_Send_Notification_Form();
        $form->render();
    }

    public function render_groups_page() {
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        
        switch ($action) {
            case 'add':
            case 'edit':
                $group_id = isset($_GET['group']) ? absint($_GET['group']) : 0;
                $group = $group_id ? new SandCrime_Security_Group($group_id) : null;
                $form = new SandCrime_Security_Group_Form($group);
                $form->render();
                break;
                
            default:
                $groups_table = new SandCrime_Security_Groups_List_Table();
                $groups_table->prepare_items();
                ?>
                <div class="wrap">
                    <h1 class="wp-heading-inline"><?php _e('Security Groups', 'sandcrime'); ?></h1>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sandcrime-security-groups&action=add')); ?>" 
                       class="page-title-action">
                        <?php _e('Add New', 'sandcrime'); ?>
                    </a>
                    <hr class="wp-header-end">
                    
                    <form method="get">
                        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
                        <?php
                        $groups_table->search_box(__('Search Groups', 'sandcrime'), 'group_search');
                        $groups_table->display();
                        ?>
                    </form>
                </div>
                <?php
                break;
        }
    }

    public function render_analytics_page() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        ?>
        <div class="wrap">
            <h1><?php _e('Notification Analytics', 'sandcrime'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=sandcrime-analytics&tab=overview" 
                   class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Overview', 'sandcrime'); ?>
                </a>
                <a href="?page=sandcrime-analytics&tab=delivery" 
                   class="nav-tab <?php echo $tab === 'delivery' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Delivery', 'sandcrime'); ?>
                </a>
                <a href="?page=sandcrime-analytics&tab=engagement" 
                   class="nav-tab <?php echo $tab === 'engagement' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Engagement', 'sandcrime'); ?>
                </a>
                <a href="?page=sandcrime-analytics&tab=recipients" 
                   class="nav-tab <?php echo $tab === 'recipients' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Recipients', 'sandcrime'); ?>
                </a>
            </nav>

            <?php $this->render_analytics_filters(); ?>

            <div class="sandcrime-analytics" id="sandcrime-analytics-container">
                <div class="loading-overlay">
                    <div class="spinner"></div>
                </div>
                
                <div class="analytics-content">
                    <?php
                    switch ($tab) {
                        case 'delivery':
                            $this->render_delivery_analytics();
                            break;
                        case 'engagement':
                            $this->render_engagement_analytics();
                            break;
                        case 'recipients':
                            $this->render_recipients_analytics();
                            break;
                        default:
                            $this->render_analytics_overview();
                            break;
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_analytics_filters() {
        ?>
        <div class="analytics-filters">
            <div class="filter-group">
                <label for="start-date"><?php _e('Start Date:', 'sandcrime'); ?></label>
                <input type="date" id="start-date" name="start_date" 
                       value="<?php echo esc_attr(date('Y-m-d', strtotime('-30 days'))); ?>">
            </div>
            
            <div class="filter-group">
                <label for="end-date"><?php _e('End Date:', 'sandcrime'); ?></label>
                <input type="date" id="end-date" name="end_date" 
                       value="<?php echo esc_attr(date('Y-m-d')); ?>">
            </div>
            
            <div class="filter-group">
                <label for="delivery-type-filter"><?php _e('Delivery Type:', 'sandcrime'); ?></label>
                <select id="delivery-type-filter" name="delivery_type">
                    <option value=""><?php _e('All Types', 'sandcrime'); ?></option>
                    <option value="email"><?php _e('Email', 'sandcrime'); ?></option>
                    <option value="push"><?php _e('Push', 'sandcrime'); ?></option>
                    <option value="sms"><?php _e('SMS', 'sandcrime'); ?></option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="group-by-filter"><?php _e('Group By:', 'sandcrime'); ?></label>
                <select id="group-by-filter" name="group_by">
                    <option value="day"><?php _e('Day', 'sandcrime'); ?></option>
                    <option value="week"><?php _e('Week', 'sandcrime'); ?></option>
                    <option value="month"><?php _e('Month', 'sandcrime'); ?></option>
                </select>
            </div>
            
            <button type="button" id="refresh-analytics" class="button button-primary">
                <?php _e('Refresh', 'sandcrime'); ?>
            </button>
            
            <button type="button" id="export-analytics" class="button">
                <?php _e('Export', 'sandcrime'); ?>
            </button>
        </div>
        <?php
    }

    public function enqueue_assets($hook) {
        $screen = get_current_screen();

        // Only load on plugin pages
        if (strpos($hook, 'sandcrime') === false) {
            return;
        }

        // Common assets
        wp_enqueue_style(
            'sandcrime-admin',
            SANDCRIME_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SANDCRIME_VERSION
        );

        wp_enqueue_script(
            'sandcrime-admin',
            SANDCRIME_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SANDCRIME_VERSION,
            true
        );

        // Page specific assets
        switch ($screen->id) {
            case 'notifications_page_sandcrime-analytics':
                wp_enqueue_script(
                    'sandcrime-charts',
                    SANDCRIME_PLUGIN_URL . 'assets/js/charts.js',
                    array('jquery'),
                    SANDCRIME_VERSION,
                    true
                );
                break;

            case 'notifications_page_sandcrime-send-notification':
                wp_enqueue_editor();
                wp_enqueue_script(
                    'sandcrime-send',
                    SANDCRIME_PLUGIN_URL . 'assets/js/send-notification.js',
                    array('jquery'),
                    SANDCRIME_VERSION,
                    true
                );
                break;
        }

        wp_localize_script('sandcrime-admin', 'sandcrimeAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sandcrime_admin'),
            'i18n' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'sandcrime'),
                'confirm_bulk_delete' => __('Are you sure you want to delete these items?', 'sandcrime'),
                'error_occurred' => __('An error occurred. Please try again.', 'sandcrime'),
                'loading' => __('Loading...', 'sandcrime')
            )
        ));
    }

    public function ajax_search_users() {
        check_ajax_referer('sandcrime_search_users', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'sandcrime'));
        }

        $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        if (empty($search)) {
            wp_send_json_array(array());
        }

        $users = get_users(array(
            'search' => '*' . $search . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'number' => 20,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));

        $results = array_map(function($user) {
            return array(
                'id' => $user->ID,
                'text' => sprintf(
                    '%s (%s)',
                    $user->display_name,
                    $user->user_email
                )
            );
        }, $users);

        wp_send_json_array($results);
    }

    public function ajax_preview_notification() {
        check_ajax_referer('sandcrime_preview_notification', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'sandcrime'));
        }

        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $data = isset($_POST['data']) ? $_POST['data'] : array();

        try {
            $preview = $this->generate_preview($type, $template_id, $data);
            wp_send_json_success($preview);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    private function generate_preview($type, $template_id, $data) {
        $template = get_post($template_id);
        if (!$template || 'notification_template' !== $template->post_type) {
            throw new Exception(__('Invalid template', 'sandcrime'));
        }

        $template_data = get_post_meta($template->ID, '_template_data', true);
        
        // Replace placeholders
        $content = $this->replace_placeholders(
            $template_data['content'],
            array_merge($data, array(
                'recipient_name' => 'John Doe',
                'site_name' => get_bloginfo('name'),
                'site_url' => get_bloginfo('url')
            ))
        );

        switch ($type) {
            case 'email':
                return $this->generate_email_preview($content, $template_data);
            case 'push':
                return $this->generate_push_preview($content, $template_data);
            case 'sms':
                return $this->generate_sms_preview($content, $template_data);
            default:
                throw new Exception(__('Invalid notification type', 'sandcrime'));
        }
    }

    private function generate_email_preview($content, $template_data) {
        $email_template = new SandCrime_Email_Template();
        return $email_template->render(array(
            'content' => $content,
            'title' => $template_data['title'],
            'preview' => true
        ));
    }

    private function generate_push_preview($content, $template_data) {
        return array(
            'title' => $template_data['title'],
            'body' => wp_strip_all_tags($content),
            'icon' => $this->settings->get_setting('push.icon'),
            'badge' => $this->settings->get_setting('push.badge')
        );
    }

    private function generate_sms_preview($content, $template_data) {
        $text = wp_strip_all_tags($content);
        $prefix = $this->settings->get_setting('sms.message_prefix');
        $suffix = $this->settings->get_setting('sms.message_suffix');

        if (!empty($prefix)) {
            $text = $prefix . ' ' . $text;
        }
        if (!empty($suffix)) {
            $text .= ' ' . $suffix;
        }

        return array(
            'message' => $text,
            'length' => mb_strlen($text),
            'segments' => ceil(mb_strlen($text) / 160)
        );
    }

    public function ajax_test_notification() {
        check_ajax_referer('sandcrime_test_notification', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'sandcrime'));
        }

        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
        $recipient = isset($_POST['recipient']) ? sanitize_text_field($_POST['recipient']) : '';
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        try {
            $result = $this->send_test_notification($type, $recipient, $template_id);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    private function send_test_notification($type, $recipient, $template_id) {
        $notification = array(
            'type' => $type,
            'recipient' => $recipient,
            'template_id' => $template_id,
            'metadata' => array(
                'is_test' => true,
                'sent_by' => get_current_user_id()
            )
        );

        $manager = new SandCrime_Notification_Manager();
        return $manager->send($notification);
    }

    public function add_dashboard_widget() {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'sandcrime_notification_status',
            __('Notification System Status', 'sandcrime'),
            array($this, 'render_dashboard_widget')
        );
    }

    public function render_dashboard_widget() {
        $stats = $this->analytics->get_overview_stats();
        ?>
        <div class="sandcrime-dashboard-widget">
            <div class="stats-summary">
                <div class="stat-item">
                    <span class="label"><?php _e('Queue Size:', 'sandcrime'); ?></span>
                    <span class="value"><?php echo esc_html($stats['queue_size']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label"><?php _e('Sent Today:', 'sandcrime'); ?></span>
                    <span class="value"><?php echo esc_html($stats['sent_today']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="label"><?php _e('Success Rate:', 'sandcrime'); ?></span>
                    <span class="value"><?php echo esc_html($stats['success_rate']); ?>%</span>
                </div>
            </div>

            <?php if (!empty($stats['recent_errors'])): ?>
                <div class="recent-errors">
                    <h4><?php _e('Recent Errors', 'sandcrime'); ?></h4>
                    <ul>
                        <?php foreach ($stats['recent_errors'] as $error): ?>
                            <li>
                                <time datetime="<?php echo esc_attr($error['time']); ?>">
                                    <?php echo esc_html(human_time_diff(strtotime($error['time']))); ?>
                                </time>
                                <span class="error-message">
                                    <?php echo esc_html($error['message']); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="widget-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sandcrime-notifications')); ?>" 
                   class="button button-secondary">
                    <?php _e('View Details', 'sandcrime'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    private function replace_placeholders($content, $data) {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) use ($data) {
            $key = trim($matches[1]);
            return isset($data[$key]) ? $data[$key] : $matches[0];
        }, $content);
    }

    private function render_recent_activity() {
        $activities = $this->analytics->get_recent_activity();
        if (empty($activities)) {
            echo '<p class="no-activity">' . __('No recent activity', 'sandcrime') . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Time', 'sandcrime'); ?></th>
                    <th><?php _e('Type', 'sandcrime'); ?></th>
                    <th><?php _e('Status', 'sandcrime'); ?></th>
                    <th><?php _e('Details', 'sandcrime'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td>
                            <?php echo esc_html(human_time_diff(strtotime($activity['time']))); ?>
                        </td>
                        <td>
                            <?php echo esc_html($activity['type']); ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($activity['status']); ?>">
                                <?php echo esc_html($activity['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html($activity['details']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_system_status() {
        $status = $this->get_system_status();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <tbody>
                <?php foreach ($status as $item): ?>
                    <tr>
                        <td class="status-label"><?php echo esc_html($item['label']); ?></td>
                        <td class="status-value">
                            <span class="status-indicator status-<?php echo esc_attr($item['status']); ?>"></span>
                            <?php echo esc_html($item['value']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function get_system_status() {
        return array(
            array(
                'label' => __('Queue Processing', 'sandcrime'),
                'status' => wp_next_scheduled('sandcrime_process_queue') ? 'ok' : 'error',
                'value' => wp_next_scheduled('sandcrime_process_queue') ? 
                    __('Running', 'sandcrime') : 
                    __('Not Scheduled', 'sandcrime')
            ),
            array(
                'label' => __('Email Service', 'sandcrime'),
                'status' => $this->settings->get_setting('email.enabled') ? 'ok' : 'warning',
                'value' => $this->settings->get_setting('email.enabled') ? 
                    __('Enabled', 'sandcrime') : 
                    __('Disabled', 'sandcrime')
            ),
            array(
                'label' => __('Push Service', 'sandcrime'),
                'status' => $this->settings->get_setting('push.enabled') ? 'ok' : 'warning',
                'value' => $this->settings->get_setting('push.enabled') ? 
                    __('Enabled', 'sandcrime') : 
                    __('Disabled', 'sandcrime')
            ),
            array(
                'label' => __('SMS Service', 'sandcrime'),
                'status' => $this->settings->get_setting('sms.enabled') ? 'ok' : 'warning',
                'value' => $this->settings->get_setting('sms.enabled') ? 
                    __('Enabled', 'sandcrime') : 
                    __('Disabled', 'sandcrime')
            ),
            array(
                'label' => __('Database Health', 'sandcrime'),
                'status' => $this->check_database_health() ? 'ok' : 'error',
                'value' => $this->check_database_health() ? 
                    __('Healthy', 'sandcrime') : 
                    __('Issues Detected', 'sandcrime')
            )
        );
    }

    private function check_database_health() {
        global $wpdb;
        
        $tables = array(
            'notification_queue',
            'notification_logs',
            'notification_tracking',
            'security_groups',
            'group_members'
        );

        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . 'sandcrime_' . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                return false;
            }
        }

        return true;
    }
}

// Initialize admin
new SandCrime_Admin();