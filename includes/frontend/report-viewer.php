<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Report_Viewer {
    public static function init() {
        add_shortcode('sandcrime_reports', array(__CLASS__, 'render_reports_page'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    public static function enqueue_assets() {
        wp_enqueue_style(
            'sandcrime-reports',
            SANDCRIME_PLUGIN_URL . 'assets/css/reports.css',
            array(),
            SANDCRIME_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'sandcrime-reports',
            SANDCRIME_PLUGIN_URL . 'assets/js/reports.js',
            array('jquery'),
            SANDCRIME_PLUGIN_VERSION,
            true
        );

        wp_localize_script('sandcrime-reports', 'sandcrimeReports', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sandcrime_reports_nonce')
        ));
    }

    public static function render_reports_page($atts = array()) {
        $atts = shortcode_atts(array(
            'per_page' => 10,
            'show_map' => true,
            'show_filters' => true
        ), $atts);

        global $wpdb;
        
        // Get filter values
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '';
        
        // Build query
        $where = array('1=1');
        $where_values = array();
        
        if ($category) {
            $where[] = 'category = %s';
            $where_values[] = $category;
        }
        
        if ($status) {
            $where[] = 'result_status = %s';
            $where_values[] = $status;
        }
        
        if ($date_range) {
            switch ($date_range) {
                case 'today':
                    $where[] = 'DATE(date_time) = CURDATE()';
                    break;
                case 'week':
                    $where[] = 'date_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'month':
                    $where[] = 'date_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                    break;
            }
        }

        // Get total count
        $total_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_reports WHERE " . implode(' AND ', $where),
            $where_values
        );
        $total_items = $wpdb->get_var($total_query);

        // Pagination
        $current_page = max(1, get_query_var('paged'));
        $per_page = intval($atts['per_page']);
        $offset = ($current_page - 1) * $per_page;
        
        // Get reports
        $reports_query = $wpdb->prepare(
            "SELECT *, ST_X(coordinates) as lat, ST_Y(coordinates) as lng 
             FROM {$wpdb->prefix}sandcrime_reports 
             WHERE " . implode(' AND ', $where) . "
             ORDER BY date_time DESC LIMIT %d OFFSET %d",
            array_merge($where_values, array($per_page, $offset))
        );
        
        $reports = $wpdb->get_results($reports_query);

        ob_start();
        ?>
        <div class="sandcrime-reports-wrapper">
            <?php if ($atts['show_map']): ?>
                <div class="reports-map-container">
                    <div id="reports-map" class="reports-map"></div>
                </div>
            <?php endif; ?>

            <?php if ($atts['show_filters']): ?>
                <div class="reports-filters">
                    <form method="get" class="filters-form">
                        <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>">
                        
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php
                            $categories = $wpdb->get_col(
                                "SELECT DISTINCT category FROM {$wpdb->prefix}sandcrime_reports ORDER BY category"
                            );
                            foreach ($categories as $cat) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($cat),
                                    selected($category, $cat, false),
                                    esc_html($cat)
                                );
                            }
                            ?>
                        </select>

                        <select name="status">
                            <option value="">All Statuses</option>
                            <?php
                            $statuses = $wpdb->get_col(
                                "SELECT DISTINCT result_status FROM {$wpdb->prefix}sandcrime_reports ORDER BY result_status"
                            );
                            foreach ($statuses as $stat) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($stat),
                                    selected($status, $stat, false),
                                    esc_html($stat)
                                );
                            }
                            ?>
                        </select>

                        <select name="date_range">
                            <option value="">All Time</option>
                            <option value="today" <?php selected($date_range, 'today'); ?>>Today</option>
                            <option value="week" <?php selected($date_range, 'week'); ?>>Last 7 Days</option>
                            <option value="month" <?php selected($date_range, 'month'); ?>>Last 30 Days</option>
                        </select>

                        <button type="submit" class="button">Apply Filters</button>
                        <?php if ($category || $status || $date_range): ?>
                            <a href="<?php echo esc_url(remove_query_arg(array('category', 'status', 'date_range'))); ?>" 
                               class="button">Clear Filters</a>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>

            <div class="reports-list">
                <?php if ($reports): ?>
                    <?php foreach ($reports as $report): ?>
                        <div class="report-card" data-id="<?php echo esc_attr($report->id); ?>">
                            <div class="report-header">
                                <h3><?php echo esc_html($report->title); ?></h3>
                                <span class="report-status status-<?php echo sanitize_html_class(strtolower($report->result_status)); ?>">
                                    <?php echo esc_html($report->result_status); ?>
                                </span>
                            </div>
                            
                            <div class="report-meta">
                                <span class="report-category">
                                    <?php echo esc_html($report->category); ?>
                                </span>
                                <span class="report-date">
                                    <?php echo esc_html(SandCrime_Utilities::time_elapsed_string($report->date_time)); ?>
                                </span>
                            </div>
                            
                            <div class="report-location">
                                <i class="dashicons dashicons-location"></i>
                                <?php echo esc_html($report->location); ?>
                            </div>

                            <?php if ($report->photo_attachments): ?>
                                <div class="report-photos">
                                    <?php
                                    $photos = explode(',', $report->photo_attachments);
                                    foreach (array_slice($photos, 0, 3) as $index => $photo): ?>
                                        <div class="photo-thumbnail" 
                                             style="background-image: url('<?php echo esc_url($photo); ?>')">
                                            <?php if ($index === 2 && count($photos) > 3): ?>
                                                <div class="more-photos">
                                                    +<?php echo count($photos) - 3; ?> more
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="report-footer">
                                <button class="button view-details" data-id="<?php echo esc_attr($report->id); ?>">
                                    View Details
                                </button>
                                <?php if ($report->lat && $report->lng): ?>
                                    <button class="button show-on-map" 
                                            data-lat="<?php echo esc_attr($report->lat); ?>"
                                            data-lng="<?php echo esc_attr($report->lng); ?>">
                                        Show on Map
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php
                    // Pagination
                    $total_pages = ceil($total_items / $per_page);
                    if ($total_pages > 1) {
                        echo '<div class="reports-pagination">';
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        echo '</div>';
                    }
                    ?>
                <?php else: ?>
                    <div class="no-reports">
                        <p>No reports found.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Report Details Modal -->
            <div id="report-modal" class="modal">
                <div class="modal-content">
                    <span class="close-modal">&times;</span>
                    <div id="report-details"></div>
                </div>
            </div>
        </div>

        <style>
        .sandcrime-reports-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .reports-map {
            height: 400px;
            margin-bottom: 20px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .reports-filters {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .filters-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .report-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .report-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .report-status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pending-review { background: #f0f0f1; }
        .status-in-progress { background: #e5f5fa; color: #0a4b78; }
        .status-resolved { background: #edfaef; color: #0a7s1b; }
        .status-closed { background: #f1f1f1; color: #666; }
        .report-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 14px;
            color: #666;
        }
        .report-location {
            margin-bottom: 10px;
        }
        .report-photos {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .photo-thumbnail {
            width: 100px;
            height: 100px;
            background-size: cover;
            background-position: center;
            border-radius: 4px;
            position: relative;
        }
        .more-photos {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        .report-footer {
            display: flex;
            gap: 10px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .modal-content {
            position: relative;
            background: white;
            margin: 50px auto;
            padding: 20px;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            border-radius: 4px;
        }
        .close-modal {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            cursor: pointer;
        }
        </style>
        <?php
        return ob_get_clean();
    }
}

// Initialize the report viewer
SandCrime_Report_Viewer::init();

// AJAX handler for report details
add_action('wp_ajax_get_report_details', 'get_report_details');
add_action('wp_ajax_nopriv_get_report_details', 'get_report_details');

function get_report_details() {
    check_ajax_referer('sandcrime_reports_nonce', 'nonce');

    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
    if (!$report_id) {
        wp_send_json_error('Invalid report ID');
    }

    global $wpdb;
    $report = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sandcrime_reports WHERE id = %d",
        $report_id
    ));

    if (!$report) {
        wp_send_json_error('Report not found');
    }

    ob_start();
    ?>
    <div class="report-details">
        <h2><?php echo esc_html($report->title); ?></h2>
        
        <div class="report-info">
            <div class="info-row">
                <strong>Category:</strong> <?php echo esc_html($report->category); ?>
            </div>
            <div class="info-row">
                <strong>Status:</strong> 
                <span class="status-<?php echo sanitize_html_class(strtolower($report->result_status)); ?>">
                    <?php echo esc_html($report->result_status); ?>
                </span>
            </div>
            <div class="info-row">
                <strong>Date/Time:</strong> 
                <?php echo esc_html(date('F j, Y g:i a', strtotime($report->date_time))); ?>
            </div>
            <div class="info-row">
                <strong>Location:</strong> <?php echo esc_html($report->location); ?>
            </div>
        </div>

        <div class="report-description">
            <?php echo wpautop(esc_html($report->description)); ?>
        </div>

        <?php if ($report->photo_attachments): ?>
            <div class="report-gallery">
                <?php
                $photos = explode(',', $report->photo_attachments);
                foreach ($photos as $photo): ?>
                    <a href="<?php echo esc_url($photo); ?>" target="_blank" class="gallery-item">
                        <img src="<?php echo esc_url($photo); ?>" alt="Report photo">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .report-details {
        padding: 20px;
    }
    .report-info {
        margin: 20px 0;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 4px;
    }
    .info-row {
        margin-bottom: 10px;
    }
    .info-row:last-child {
        margin-bottom: 0;
    }
    .report-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    .gallery-item img {
        width: 100%;
        height: auto;
        border-radius: 4px;
        transition: transform 0.2s;
    }
    .gallery-item img:hover {
        transform: scale(1.05);
    }
    </style>
    <?php
    $html = ob_get_clean();
    wp_send_json_success($html);
}