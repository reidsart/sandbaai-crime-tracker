<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Statistics_Dashboard {
    public static function init() {
        add_shortcode('sandcrime_statistics', array(__CLASS__, 'render_statistics'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    public static function enqueue_assets() {
        // Chart.js library
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );

        wp_enqueue_script(
            'sandcrime-statistics',
            SANDCRIME_PLUGIN_URL . 'assets/js/statistics.js',
            array('jquery', 'chartjs'),
            SANDCRIME_PLUGIN_VERSION,
            true
        );

        wp_localize_script('sandcrime-statistics', 'sandcrimeStats', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sandcrime_statistics_nonce')
        ));
    }

    public static function render_statistics($atts = array()) {
        $atts = shortcode_atts(array(
            'show_trends' => true,
            'show_heatmap' => true,
            'show_categories' => true,
            'show_response_times' => true
        ), $atts);

        global $wpdb;
        $reports_table = $wpdb->prefix . 'sandcrime_reports';

        // Get overall statistics
        $stats = array(
            'total_reports' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table"),
            'resolved_reports' => $wpdb->get_var(
                "SELECT COUNT(*) FROM $reports_table WHERE result_status IN ('Resolved', 'Closed')"
            ),
            'pending_reports' => $wpdb->get_var(
                "SELECT COUNT(*) FROM $reports_table WHERE result_status = 'Pending Review'"
            ),
            'reports_this_month' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $reports_table WHERE MONTH(date_time) = %d AND YEAR(date_time) = %d",
                current_time('n'),
                current_time('Y')
            ))
        );

        // Get category distribution
        $categories = $wpdb->get_results("
            SELECT category, COUNT(*) as count
            FROM $reports_table
            GROUP BY category
            ORDER BY count DESC
        ");

        // Get monthly trends
        $trends = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(date_time, '%Y-%m') as month,
                COUNT(*) as count
            FROM $reports_table
            GROUP BY month
            ORDER BY month DESC
            LIMIT 12
        ");

        // Get average response times
        $response_times = $wpdb->get_results("
            SELECT 
                category,
                AVG(TIMESTAMPDIFF(HOUR, date_time, last_edited_at)) as avg_hours
            FROM $reports_table
            WHERE result_status IN ('Resolved', 'Closed')
            GROUP BY category
            ORDER BY avg_hours DESC
        ");

        ob_start();
        ?>
        <div class="sandcrime-statistics-wrapper">
            <!-- Summary Cards -->
            <div class="statistics-summary">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <h3>Total Reports</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_reports']); ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <h3>Resolved Reports</h3>
                        <div class="stat-number">
                            <?php 
                            echo number_format($stats['resolved_reports']); 
                            if ($stats['total_reports'] > 0) {
                                $percentage = round(($stats['resolved_reports'] / $stats['total_reports']) * 100);
                                echo " <span class=\"stat-percentage\">($percentage%)</span>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-content">
                        <h3>Pending Reports</h3>
                        <div class="stat-number"><?php echo number_format($stats['pending_reports']); ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-content">
                        <h3>This Month</h3>
                        <div class="stat-number"><?php echo number_format($stats['reports_this_month']); ?></div>
                    </div>
                </div>
            </div>

            <div class="statistics-grid">
                <?php if ($atts['show_trends']): ?>
                    <!-- Monthly Trends Chart -->
                    <div class="stat-panel">
                        <h3>Monthly Trends</h3>
                        <canvas id="trendsChart"></canvas>
                        <script>
                        var trendsData = <?php echo json_encode(array_reverse($trends)); ?>;
                        </script>
                    </div>
                <?php endif; ?>

                <?php if ($atts['show_categories']): ?>
                    <!-- Category Distribution Chart -->
                    <div class="stat-panel">
                        <h3>Reports by Category</h3>
                        <canvas id="categoriesChart"></canvas>
                        <script>
                        var categoriesData = <?php echo json_encode($categories); ?>;
                        </script>
                    </div>
                <?php endif; ?>

                <?php if ($atts['show_response_times']): ?>
                    <!-- Response Times Chart -->
                    <div class="stat-panel">
                        <h3>Average Response Times (Hours)</h3>
                        <canvas id="responseTimesChart"></canvas>
                        <script>
                        var responseTimesData = <?php echo json_encode($response_times); ?>;
                        </script>
                    </div>
                <?php endif; ?>

                <?php if ($atts['show_heatmap']): ?>
                    <!-- Crime Heatmap -->
                    <div class="stat-panel full-width">
                        <h3>Crime Heatmap</h3>
                        <div id="crimeHeatmap" class="heatmap"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
        .sandcrime-statistics-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .statistics-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
        }
        .stat-icon {
            font-size: 24px;
            margin-right: 15px;
        }
        .stat-content h3 {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-top: 5px;
        }
        .stat-percentage {
            font-size: 14px;
            color: #666;
        }
        .statistics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .stat-panel {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-panel h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #333;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .heatmap {
            height: 400px;
            border-radius: 4px;
            overflow: hidden;
        }
        canvas {
            max-width: 100%;
        }
        @media (max-width: 768px) {
            .statistics-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
}

// Initialize the statistics dashboard
SandCrime_Statistics_Dashboard::init();

// AJAX handler for heatmap data
add_action('wp_ajax_get_heatmap_data', 'get_heatmap_data');
add_action('wp_ajax_nopriv_get_heatmap_data', 'get_heatmap_data');

function get_heatmap_data() {
    check_ajax_referer('sandcrime_statistics_nonce', 'nonce');

    global $wpdb;
    $reports_table = $wpdb->prefix . 'sandcrime_reports';

    $points = $wpdb->get_results("
        SELECT 
            ST_X(coordinates) as lat,
            ST_Y(coordinates) as lng,
            COUNT(*) as weight
        FROM $reports_table
        WHERE coordinates IS NOT NULL
        GROUP BY ST_X(coordinates), ST_Y(coordinates)
    ");

    wp_send_json_success($points);
}