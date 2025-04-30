<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class SandCrime_Queue_List_Table extends WP_List_Table {
    private $items_per_page = 20;
    private $total_items = 0;

    public function __construct() {
        parent::__construct(array(
            'singular' => 'notification',
            'plural' => 'notifications',
            'ajax' => false
        ));
    }

    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'sandcrime'),
            'type' => __('Type', 'sandcrime'),
            'recipient' => __('Recipient', 'sandcrime'),
            'title' => __('Title', 'sandcrime'),
            'priority' => __('Priority', 'sandcrime'),
            'status' => __('Status', 'sandcrime'),
            'retry_count' => __('Retries', 'sandcrime'),
            'scheduled_for' => __('Scheduled', 'sandcrime'),
            'created_at' => __('Created', 'sandcrime')
        );
    }

    public function get_sortable_columns() {
        return array(
            'id' => array('id', false),
            'type' => array('type', false),
            'priority' => array('priority', false),
            'status' => array('status', false),
            'scheduled_for' => array('scheduled_for', true),
            'created_at' => array('created_at', false)
        );
    }

    public function get_bulk_actions() {
        return array(
            'retry' => __('Retry', 'sandcrime'),
            'cancel' => __('Cancel', 'sandcrime'),
            'delete' => __('Delete', 'sandcrime')
        );
    }

    public function prepare_items() {
        global $wpdb;

        $this->process_bulk_action();

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);

        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $this->items_per_page;

        // Build query
        $where = array('1=1');
        $values = array();

        // Search
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';
            $where[] = '(title LIKE %s OR message LIKE %s OR recipient LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        // Filter by type
        if (!empty($_REQUEST['type'])) {
            $where[] = 'type = %s';
            $values[] = sanitize_key($_REQUEST['type']);
        }

        // Filter by status
        if (!empty($_REQUEST['status'])) {
            $where[] = 'status = %s';
            $values[] = sanitize_key($_REQUEST['status']);
        }

        // Order
        $order = isset($_REQUEST['order']) && in_array(strtolower($_REQUEST['order']), array('asc', 'desc')) ? 
            strtoupper($_REQUEST['order']) : 'DESC';
        
        $orderby = isset($_REQUEST['orderby']) ? sanitize_key($_REQUEST['orderby']) : 'created_at';
        switch ($orderby) {
            case 'id':
            case 'type':
            case 'priority':
            case 'status':
            case 'scheduled_for':
            case 'created_at':
                break;
            default:
                $orderby = 'created_at';
        }

        // Build complete query
        $where = implode(' AND ', $where);
        $sql = $wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS *
            FROM {$wpdb->prefix}sandcrime_notification_queue
            WHERE {$where}
            ORDER BY {$orderby} {$order}
            LIMIT %d OFFSET %d",
            array_merge($values, array($this->items_per_page, $offset))
        );

        $this->items = $wpdb->get_results($sql, ARRAY_A);
        $this->total_items = $wpdb->get_var("SELECT FOUND_ROWS()");

        $this->set_pagination_args(array(
            'total_items' => $this->total_items,
            'per_page' => $this->items_per_page,
            'total_pages' => ceil($this->total_items / $this->items_per_page)
        ));
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return '#' . $item['notification_id'];
            case 'type':
                return $this->get_type_label($item['type']);
            case 'recipient':
                return $this->get_recipient_display($item);
            case 'title':
                return $this->get_title_with_preview($item);
            case 'priority':
                return $this->get_priority_badge($item['priority']);
            case 'status':
                return $this->get_status_badge($item['status']);
            case 'retry_count':
                return $item['retry_count'];
            case 'scheduled_for':
                return $this->format_datetime($item['scheduled_for']);
            case 'created_at':
                return $this->format_datetime($item['created_at']);
            default:
                return print_r($item, true);
        }
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="notifications[]" value="%s" />',
            $item['id']
        );
    }

    private function get_type_label($type) {
        $types = array(
            'email' => __('Email', 'sandcrime'),
            'push' => __('Push', 'sandcrime'),
            'sms' => __('SMS', 'sandcrime')
        );

        return isset($types[$type]) ? 
            '<span class="type-badge type-' . esc_attr($type) . '">' . $types[$type] . '</span>' : 
            $type;
    }

    private function get_recipient_display($item) {
        $recipient = $item['recipient'];
        
        if ($item['recipient_id']) {
            $user = get_user_by('ID', $item['recipient_id']);
            if ($user) {
                $recipient = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(get_edit_user_link($user->ID)),
                    esc_html($user->display_name)
                );
            }
        }

        return $recipient;
    }

    private function get_title_with_preview($item) {
        $title = !empty($item['title']) ? $item['title'] : __('(no title)', 'sandcrime');
        
        $actions = array(
            'preview' => sprintf(
                '<a href="#" class="preview-notification" data-id="%s">%s</a>',
                esc_attr($item['id']),
                __('Preview', 'sandcrime')
            ),
            'retry' => sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(
                    add_query_arg(
                        array(
                            'action' => 'retry',
                            'notification' => $item['id']
                        )
                    ),
                    'sandcrime_retry_notification_' . $item['id']
                ),
                __('Retry', 'sandcrime')
            ),
            'cancel' => sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(
                    add_query_arg(
                        array(
                            'action' => 'cancel',
                            'notification' => $item['id']
                        )
                    ),
                    'sandcrime_cancel_notification_' . $item['id']
                ),
                __('Cancel', 'sandcrime')
            )
        );

        return sprintf(
            '%1$s %2$s',
            '<strong>' . esc_html($title) . '</strong>',
            $this->row_actions($actions)
        );
    }

    private function get_priority_badge($priority) {
        return sprintf(
            '<span class="priority-badge priority-%s">%s</span>',
            esc_attr($priority),
            esc_html(ucfirst($priority))
        );
    }

    private function get_status_badge($status) {
        return sprintf(
            '<span class="status-badge status-%s">%s</span>',
            esc_attr($status),
            esc_html(ucfirst($status))
        );
    }

    private function format_datetime($datetime) {
        $timestamp = strtotime($datetime);
        
        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)),
            esc_html(human_time_diff($timestamp) . ' ' . ($timestamp > time() ? __('from now', 'sandcrime') : __('ago', 'sandcrime')))
        );
    }

    protected function process_bulk_action() {
        if (empty($_REQUEST['notifications'])) {
            return;
        }

        $action = $this->current_action();
        if (!$action) {
            return;
        }

        $notifications = array_map('absint', $_REQUEST['notifications']);

        switch ($action) {
            case 'retry':
                $this->process_bulk_retry($notifications);
                break;
            case 'cancel':
                $this->process_bulk_cancel($notifications);
                break;
            case 'delete':
                $this->process_bulk_delete($notifications);
                break;
        }
    }

    private function process_bulk_retry($notifications) {
        check_admin_referer('bulk-' . $this->_args['plural']);

        $queue = new SandCrime_Notification_Queue();
        $retried = 0;

        foreach ($notifications as $id) {
            if ($queue->schedule_retry(array('id' => $id))) {
                $retried++;
            }
        }

        $message = sprintf(
            _n(
                '%s notification scheduled for retry.',
                '%s notifications scheduled for retry.',
                $retried,
                'sandcrime'
            ),
            number_format_i18n($retried)
        );

        add_settings_error(
            'sandcrime_messages',
            'sandcrime_bulk_retry',
            $message,
            'updated'
        );
    }

    private function process_bulk_cancel($notifications) {
        check_admin_referer('bulk-' . $this->_args['plural']);

        global $wpdb;
        $table = $wpdb->prefix . 'sandcrime_notification_queue';
        $cancelled = 0;

        foreach ($notifications as $id) {
            $result = $wpdb->update(
                $table,
                array('status' => 'cancelled'),
                array('id' => $id),
                array('%s'),
                array('%d')
            );

            if ($result) {
                $cancelled++;
            }
        }

        $message = sprintf(
            _n(
                '%s notification cancelled.',
                '%s notifications cancelled.',
                $cancelled,
                'sandcrime'
            ),
            number_format_i18n($cancelled)
        );

        add_settings_error(
            'sandcrime_messages',
            'sandcrime_bulk_cancel',
            $message,
            'updated'
        );
    }

    private function process_bulk_delete($notifications) {
        check_admin_referer('bulk-' . $this->_args['plural']);

        global $wpdb;
        $table = $wpdb->prefix . 'sandcrime_notification_queue';
        $deleted = 0;

        foreach ($notifications as $id) {
            $result = $wpdb->delete(
                $table,
                array('id' => $id),
                array('%d')
            );

            if ($result) {
                $deleted++;
            }
        }

        $message = sprintf(
            _n(
                '%s notification permanently deleted.',
                '%s notifications permanently deleted.',
                $deleted,
                'sandcrime'
            ),
            number_format_i18n($deleted)
        );

        add_settings_error(
            'sandcrime_messages',
            'sandcrime_bulk_delete',
            $message,
            'updated'
        );
    }
}