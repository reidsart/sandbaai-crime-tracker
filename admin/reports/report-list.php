<?php
if (!defined('ABSPATH')) {
    exit;
}

function sandcrime_report_list_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_reports';
    
    // Handle bulk actions
    if (isset($_POST['bulk_action']) && isset($_POST['report_ids']) && 
        wp_verify_nonce($_POST['bulk_reports_nonce'], 'bulk_reports_action')) {
        
        $report_ids = array_map('intval', $_POST['report_ids']);
        $action = sanitize_text_field($_POST['bulk_action']);
        $current_user = wp_get_current_user();
        
        switch ($action) {
            case 'approve':
            case 'in-progress':
            case 'resolve':
            case 'close':
                $status_map = array(
                    'approve' => 'Approved',
                    'in-progress' => 'In Progress',
                    'resolve' => 'Resolved',
                    'close' => 'Closed'
                );
                
                foreach ($report_ids as $id) {
                    $wpdb->update(
                        $table_name,
                        array(
                            'result_status' => $status_map[$action],
                            'last_edited_by' => $current_user->user_login,
                            'last_edited_at' => current_time('mysql')
                        ),
                        array('id' => $id)
                    );
                }
                break;
            
            case 'delete':
                foreach ($report_ids as $id) {
                    $wpdb->delete($table_name, array('id' => $id));
                }
                break;
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Reports updated successfully.</p></div>';
        });
    }

    // Get filters
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
    $date_filter = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';

    // Build query
    $where_clauses = array();
    $where_values = array();
    
    if ($status_filter) {
        $where_clauses[] = 'result_status = %s';
        $where_values[] = $status_filter;
    }
    
    if ($category_filter) {
        $where_clauses[] = 'category = %s';
        $where_values[] = $category_filter;
    }
    
    if ($date_filter) {
        $where_clauses[] = 'DATE(date_time) = %s';
        $where_values[] = $date_filter;
    }
    
    $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    // Get reports with pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    $query = $wpdb->prepare(
        "SELECT * FROM $table_name $where_sql ORDER BY date_time DESC LIMIT %d OFFSET %d",
        array_merge($where_values, array($per_page, $offset))
    );
    
    $reports = $wpdb->get_results($query);
    
    // Get total count for pagination
    $total_query = "SELECT COUNT(*) FROM $table_name $where_sql";
    $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
    $total_pages = ceil($total_items / $per_page);
    ?>
    
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <!-- Filters -->
        <div class="tablenav top">
            <form method="get" class="reports-filter">
                <input type="hidden" name="page" value="sandcrime-reports">
                
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php
                    $statuses = array('Pending Review', 'In Progress', 'Resolved', 'Closed');
                    foreach ($statuses as $status) {
                        echo '<option value="' . esc_attr($status) . '" ' . 
                             selected($status_filter, $status, false) . '>' . 
                             esc_html($status) . '</option>';
                    }
                    ?>
                </select>
                
                <select name="category">
                    <option value="">All Categories</option>
                    <?php
                    $categories = $wpdb->get_col("SELECT DISTINCT category FROM $table_name ORDER BY category");
                    foreach ($categories as $category) {
                        echo '<option value="' . esc_attr($category) . '" ' . 
                             selected($category_filter, $category, false) . '>' . 
                             esc_html($category) . '</option>';
                    }
                    ?>
                </select>
                
                <input type="date" name="date" value="<?php echo esc_attr($date_filter); ?>">
                
                <?php submit_button('Filter', 'secondary', 'filter', false); ?>
                <?php
                if ($status_filter || $category_filter || $date_filter) {
                    echo ' <a href="' . esc_url(admin_url('admin.php?page=sandcrime-reports')) . 
                         '" class="button">Reset Filters</a>';
                }
                ?>
            </form>
        </div>
        
        <?php if ($reports): ?>
            <form method="post">
                <?php wp_nonce_field('bulk_reports_action', 'bulk_reports_nonce'); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action">
                            <option value="">Bulk Actions</option>
                            <option value="approve">Mark as Approved</option>
                            <option value="in-progress">Mark as In Progress</option>
                            <option value="resolve">Mark as Resolved</option>
                            <option value="close">Mark as Closed</option>
                            <option value="delete">Delete</option>
                        </select>
                        <?php submit_button('Apply', 'action', 'doBulkAction', false); ?>
                    </div>
                    
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php echo esc_html($total_items . ' item' . ($total_items != 1 ? 's' : '')); ?>
                        </span>
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" id="reports-select-all">
                            </th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date/Time</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Reported By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="report_ids[]" 
                                           value="<?php echo esc_attr($report->id); ?>">
                                </td>
                                <td>
                                    <strong>
                                        <a href="?page=sandcrime-reports&action=view&id=<?php echo esc_attr($report->id); ?>">
                                            <?php echo esc_html($report->title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($report->category); ?></td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($report->date_time))); ?></td>
                                <td><?php echo esc_html($report->location); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo sanitize_html_class(strtolower($report->result_status)); ?>">
                                        <?php echo esc_html($report->result_status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($report->user_login ? $report->user_login : 'Anonymous'); ?>
                                    <?php if ($report->last_edited_by): ?>
                                        <br>
                                        <small>Edited by: <?php echo esc_html($report->last_edited_by); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=sandcrime-reports&action=view&id=<?php echo esc_attr($report->id); ?>" 
                                       class="button button-small">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        <?php else: ?>
            <p>No reports found.</p>
        <?php endif; ?>
    </div>

    <style>
    .reports-filter {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 15px;
    }
    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
    }
    .status-pending-review { background: #f0f0f1; }
    .status-in-progress { background: #e5f5fa; color: #0a4b78; }
    .status-resolved { background: #edfaef; color: #0a7s1b; }
    .status-closed { background: #f1f1f1; color: #666; }
    </style>
    <?php
}