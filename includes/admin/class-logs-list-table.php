<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class SandCrime_Logs_List_Table extends WP_List_Table {
    private $items_per_page = 25;
    private $total_items = 0;

    public function __construct() {
        parent::__construct(array(
            'singular' => 'log',
            'plural' => 'logs',
            'ajax' => false
        ));
    }

    public function get_columns() {
        return array(
            'id' => __('ID', 'sandcrime'),
            'type' => __('Type', 'sandcrime'),
            'recipient' => __('Recipient', 'sandcrime'),
            'title' => __('Title', 'sandcrime'),
            'status' => __('Status', 'sandcrime'),
            'error' => __('Error', 'sandcrime'),
            'created_at' => __('Date', 'sandcrime')
        );
    }

    public function get_sortable_columns() {
        return array(
            'id' => array('id', false),
            'type' => array('type', false),
            'status' => array('status', false),
            'created_at' => array('created_at', true)
        );
    }

    public function prepare_items() {
        global $wpdb;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);

        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $this->items_per_page;

        // Build query
        $where = array('1=1');
        $values = array();

        // Date range filter
        if (!empty($_REQUEST['start_date']) && !empty($_REQUEST['end_date'])) {
            $where[] = 'created_at BETWEEN %s AND %s';
            $values[] = date('Y-m-d 00:00:00', strtotime($_REQUEST['start_date']));
            $values[] = date('Y-m-d 23:59:59', strtotime($_REQUEST['end_date']));
        }

        // Search
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';
            $where[] = '(title LIKE %s OR message LIKE %s OR recipient LIKE %s OR error_message LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        // Type filter
        if (!empty($_REQUEST['type'])) {
            $where[] = 'type = %s';
            $values[] = sanitize_key($_REQUEST['type']);
        }

        // Status filter
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
            case 'status':
            case 'created_at':
                break;
            default:
                $orderby = 'created_at';
        }

        // Build complete query
        $where = implode(' AND ', $where);
        $sql = $wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS *
            FROM {$wpdb->prefix}sandcrime_notification_logs
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
                return $this->get_title_with_details($item);
            case 'status':
                return $this->get_status_badge($item['status']);
            case 'error':
                return $this->get_error_display($item);
            case 'created_at':
                return $this->format_datetime($item['created_at']);
            default:
                return print_r($item, true);
        }
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

    private function get_title_with_details($item) {
        $title = !empty($item['title']) ? $item['title'] : __('(no title)', 'sandcrime');
        
        $actions = array(
            'view' => sprintf(
                '<a href="#" class="view-log-details" data-id="%s">%s</a>',
                esc_attr($item['id']),
                __('View Details', 'sandcrime')
            )
        );

        if ($item['status'] === 'failed' && current_user_can('manage_options')) {
            $actions['retry'] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(
                    add_query_arg(
                        array(
                            'action' => 'retry_failed',
                            'log' => $item['id']
                        )
                    ),
                    'sandcrime_retry_failed_' . $item['id']
                ),
                __('Retry', 'sandcrime')
            );
        }

        return sprintf(
            '%1$s %2$s',
            '<strong>' . esc_html($title) . '</strong>',
            $this->row_actions($actions)
        );
    }

    private function get_status_badge($status) {
        $labels = array(
            'sent' => __('Sent', 'sandcrime'),
            'delivered' => __('Delivered', 'sandcrime'),
            'failed' => __('Failed', 'sandcrime'),
            'opened' => __('Opened', 'sandcrime'),
            'clicked' => __('Clicked', 'sandcrime')
        );

        $label = isset($labels[$status]) ? $labels[$status] : ucfirst($status);

        return sprintf(
            '<span class="status-badge status-%s">%s</span>',
            esc_attr($status),
            esc_html($label)
        );
    }

    private function get_error_display($item) {
        if (empty($item['error_message'])) {
            return '—';
        }

        $error = wp_strip_all_tags($item['error_message']);
        if (strlen($error) > 50) {
            $error = substr($error, 0, 47) . '...';
        }

        return sprintf(
            '<span class="error-message" title="%s">%s</span>',
            esc_attr($item['error_message']),
            esc_html($error)
        );
    }

    private function format_datetime($datetime) {
        $timestamp = strtotime($datetime);
        
        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)),
            esc_html(human_time_diff($timestamp) . ' ' . __('ago', 'sandcrime'))
        );
    }

    protected function get_views() {
        global $wpdb;

        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count
            FROM {$wpdb->prefix}sandcrime_notification_logs
            GROUP BY status",
            ARRAY_A
        );

        $counts = array();
        $total = 0;
        foreach ($status_counts as $row) {
            $counts[$row['status']] = $row['count'];
            $total += $row['count'];
        }

        $current = isset($_REQUEST['status']) ? sanitize_key($_REQUEST['status']) : 'all';
        $base_url = remove_query_arg('status');

        $views = array(
            'all' => sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                esc_url($base_url),
                $current === 'all' ? 'current' : '',
                __('All', 'sandcrime'),
                number_format_i18n($total)
            )
        );

        $statuses = array(
            'sent' => __('Sent', 'sandcrime'),
            'delivered' => __('Delivered', 'sandcrime'),
            'failed' => __('Failed', 'sandcrime'),
            'opened' => __('Opened', 'sandcrime'),
            'clicked' => __('Clicked', 'sandcrime')
        );

        foreach ($statuses as $status => $label) {
            if (!isset($counts[$status])) {
                continue;
            }

            $views[$status] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                esc_url(add_query_arg('status', $status, $base_url)),
                $current === $status ? 'current' : '',
                $label,
                number_format_i18n($counts[$status])
            );
        }

        return $views;
    }

    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        ?>
        <div class="alignleft actions">
            <input type="date" name="start_date" 
                   value="<?php echo esc_attr(isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : ''); ?>" 
                   placeholder="<?php esc_attr_e('Start Date', 'sandcrime'); ?>">
            
            <input type="date" name="end_date" 
                   value="<?php echo esc_attr(isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : ''); ?>" 
                   placeholder="<?php esc_attr_e('End Date', 'sandcrime'); ?>">
            
            <select name="type">
                <option value=""><?php esc_html_e('All Types', 'sandcrime'); ?></option>
                <?php
                $types = array(
                    'email' => __('Email', 'sandcrime'),
                    'push' => __('Push', 'sandcrime'),
                    'sms' => __('SMS', 'sandcrime')
                );
                
                foreach ($types as $value => $label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($value),
                        selected($value, isset($_REQUEST['type']) ? $_REQUEST['type'] : '', false),
                        esc_html($label)
                    );
                }
                ?>
            </select>

            <?php submit_button(__('Filter', 'sandcrime'), '', 'filter_action', false); ?>
            <?php submit_button(__('Export CSV', 'sandcrime'), 'secondary', 'export_csv', false); ?>
        </div>
        <?php
    }
}